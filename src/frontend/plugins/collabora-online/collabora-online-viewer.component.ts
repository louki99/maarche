import { AfterViewInit, Component, EventEmitter, HostListener, Input, OnDestroy, OnInit, Output, ViewChild } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { catchError, filter, tap } from 'rxjs/operators';
import { TranslateService } from '@ngx-translate/core';
import { ConfirmComponent } from '../modal/confirm.component';
import { MatDialog, MatDialogRef } from '@angular/material/dialog';
import { HeaderService } from '@service/header.service';
import { DomSanitizer } from '@angular/platform-browser';
import { NotificationService } from '@service/notification/notification.service';
import { of, Subject } from 'rxjs';
import { Router } from '@angular/router';
import { FunctionsService } from '@service/functions.service';

declare let $: any;

@Component({
    selector: 'app-collabora-online-viewer',
    templateUrl: 'collabora-online-viewer.component.html',
    styleUrls: ['collabora-online-viewer.component.scss'],
})
export class CollaboraOnlineViewerComponent implements OnInit, AfterViewInit, OnDestroy {

    @Input() editMode: boolean = false;
    @Input() file: any = {};
    @Input() params: any = {};
    @Input() loading: boolean = false;

    @Output() triggerAfterUpdatedDoc = new EventEmitter<string>();
    @Output() triggerCloseEditor = new EventEmitter<string>();
    @Output() triggerModifiedDocument = new EventEmitter<string>();
    @Output() triggerModeModified = new EventEmitter<boolean>();

    @ViewChild('collaboraFrame', { static: false }) collaboraFrame: any;

    editorConfig: any;
    key: number = 0;
    isSaving: boolean = false;
    isModified: boolean = false;
    fullscreenMode: boolean = false;
    hideButtons: boolean = false;

    allowedExtension: string[] = [
        'doc',
        'docx',
        'dotx',
        'odt',
        'ott',
        'rtf',
        'txt',
        'html',
        'xlsl',
        'xlsx',
        'xltx',
        'ods',
        'ots',
        'csv',
    ];

    dialogRef: MatDialogRef<any>;

    editorUrl: any = '';
    token: any = '';

    private eventAction = new Subject<any>();

    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        public dialog: MatDialog,
        public router: Router,
        private notify: NotificationService,
        private sanitizer: DomSanitizer,
        public headerService: HeaderService,
        public functions: FunctionsService
    ) { }

    @HostListener('window:message', ['$event'])
    onMessage(e: any) {
        const response = JSON.parse(e.data);
        // EVENT TO CONSTANTLY UPDATE CURRENT DOCUMENT
        if (response.MessageId === 'Doc_ModifiedStatus' && response.Values.Modified === false) {
            this.isModified = false;
        }
        if (response.MessageId === 'Action_Save_Resp' && response.Values.success === true && !this.isModified) {
            setTimeout(() => {
                this.triggerAfterUpdatedDoc.emit();
                this.getTmpFile();
            }, 500);
        } else if (response.MessageId === 'Doc_ModifiedStatus' && response.Values.Modified === false && this.isSaving) {
            // Collabora sends 'Action_Save_Resp' when it starts saving the document, then sends Doc_ModifiedStatus with Modified = false when it is done saving
            this.triggerAfterUpdatedDoc.emit();
            this.getTmpFile();
        } else if (response.MessageId === 'Doc_ModifiedStatus' && response.Values.Modified === true) {
            this.isModified = true;
            this.triggerModifiedDocument.emit();
        } else if (response.MessageId === 'App_LoadingStatus' && response.Values.Status === 'Document_Loaded') {
            const message = { 'MessageId': 'Host_PostmessageReady' };
            this.collaboraFrame.nativeElement.contentWindow.postMessage(JSON.stringify(message), '*');
        }
    }

    quit() {
        this.dialogRef = this.dialog.open(ConfirmComponent, { panelClass: 'maarch-modal', autoFocus: false, disableClose: true, data: { title: this.translate.instant('lang.close'), msg: this.translate.instant('lang.confirmCloseEditor') } });

        this.dialogRef.afterClosed().pipe(
            filter((data: string) => data === 'ok'),
            tap(() => {
                this.closeEditor();
                this.formatAppToolsCss('default');
            })
        ).subscribe();
    }

    closeEditor() {
        if (this.headerService.sideNavLeft !== null && !this.headerService.hideSideBar) {
            this.headerService.sideNavLeft.open();
        }
        $('iframe[name=\'frameEditor\']').css('position', 'initial');
        this.fullscreenMode = false;

        const message = {
            'MessageId': 'Action_Close',
            'Values': null
        };
        this.collaboraFrame.nativeElement.contentWindow.postMessage(JSON.stringify(message), '*');

        this.deleteTmpFile();

        this.triggerAfterUpdatedDoc.emit();
        this.triggerCloseEditor.emit();
    }

    saveDocument() {
        this.isSaving = true;

        const message = {
            'MessageId': 'Action_Save',
            'Values': {
                'Notify': true,
                'ExtendedData': 'FinalSave=True',
                'DontTerminateEdit': true,
                'DontSaveIfUnmodified': false
            }
        };
        this.collaboraFrame.nativeElement.contentWindow.postMessage(JSON.stringify(message), '*');
    }

    async ngOnInit() {
        this.key = this.generateUniqueId(10);

        if (this.canLaunchCollaboraOnline()) {
            await this.checkServerStatus();

            this.params.objectPath = undefined;
            if (typeof this.params.objectId === 'string' && (this.params.objectType === 'templateModification' || this.params.objectType === 'templateCreation')) {
                this.params.objectPath = this.params.objectId;
                this.params.objectId = this.key;
            } else if (typeof this.params.objectId === 'string' && this.params.objectType === 'encodedResource') {
                this.params.content = this.params.objectId;
                this.params.objectId = this.key;
                this.params.objectType = 'templateEncoded';

                await this.saveEncodedFile();
            }

            await this.getConfiguration();

            this.loading = false;
            this.triggerModifiedDocument.emit();
        }
    }

    canLaunchCollaboraOnline() {
        if (this.isAllowedEditExtension(this.file.format)) {
            return true;
        } else {
            this.notify.error(this.translate.instant('lang.onlyofficeEditDenied') + ' <b>' + this.file.format + '</b> ' + this.translate.instant('lang.collaboraOnlineEditDenied2'));
            this.triggerCloseEditor.emit();
            return false;
        }
    }

    checkServerStatus() {
        return new Promise((resolve) => {
            if (location.host === '127.0.0.1' || location.host === 'localhost') {
                this.notify.error(`${this.translate.instant('lang.errorCollaboraOnline1')}`);
                this.triggerCloseEditor.emit();
            } else {
                this.http.get('../rest/collaboraOnline/available').pipe(
                    tap((data: any) => {
                        if (data.isAvailable) {
                            resolve(true);
                        } else {
                            this.notify.error(`${this.translate.instant('lang.errorCollaboraOnline2')}`);
                            this.triggerCloseEditor.emit();
                        }
                    }),
                    catchError((err) => {
                        this.notify.error(this.translate.instant('lang.' + err.error.lang));
                        this.triggerCloseEditor.emit();
                        return of(false);
                    }),
                ).subscribe();
            }
        });
    }

    getTmpFile() {
        return new Promise((resolve) => {
            this.http.post('../rest/collaboraOnline/file', { token: this.token }).pipe(
                tap((data: any) => {
                    this.file = {
                        name: this.key,
                        format: data.format,
                        type: null,
                        contentMode: 'base64',
                        content: data.content,
                        src: null
                    };
                    this.eventAction.next(this.file);
                    resolve(true);
                }),
                catchError((err) => {
                    this.notify.handleErrors(err);
                    this.triggerCloseEditor.emit();
                    return of(false);
                }),
            ).subscribe();
        });
    }

    deleteTmpFile() {
        return new Promise((resolve) => {
            this.http.delete('../rest/collaboraOnline/file?token=' + this.token).pipe(
                tap(() => {
                    resolve(true);
                }),
                catchError((err) => {
                    this.notify.handleErrors(err);
                    this.triggerCloseEditor.emit();
                    return of(false);
                }),
            ).subscribe();
        });
    }

    saveEncodedFile() {
        return new Promise((resolve) => {
            this.http.post('../rest/collaboraOnline/encodedFile', { content: this.params.content, format: this.file.format, key: this.key }).pipe(
                tap(() => {
                    resolve(true);
                }),
                catchError((err) => {
                    this.notify.handleErrors(err);
                    this.triggerCloseEditor.emit();
                    return of(false);
                }),
            ).subscribe();
        });
    }

    generateUniqueId(length: number = 5) {
        let result = '';
        const characters = '0123456789';
        const charactersLength = characters.length;
        for (let i = 0; i < length; i++) {
            result += characters.charAt(Math.floor(Math.random() * charactersLength));
        }
        return parseInt(result, 10);
    }

    ngAfterViewInit() {

    }

    getConfiguration() {
        return new Promise((resolve) => {
            this.http.post('../rest/collaboraOnline/configuration', {
                resId: this.params.objectId,
                type: this.params.objectType,
                format: this.file.format,
                path: this.params.objectPath,
                data: this.params.dataToMerge,
                lang: this.translate.instant('lang.langISO')
            }).pipe(
                tap((data: any) => {
                    this.editorUrl = data.url;
                    this.editorUrl = this.sanitizer.bypassSecurityTrustResourceUrl(this.editorUrl);
                    this.token = data.token;
                    resolve(true);
                }),
                catchError((err) => {
                    this.notify.handleErrors(err);
                    this.triggerCloseEditor.emit();
                    return of(false);
                }),
            ).subscribe();
        });
    }

    getFile() {
        this.saveDocument();
        return this.eventAction.asObservable();
    }

    ngOnDestroy() {
        this.eventAction.complete();
    }

    openFullscreen() {
        const iframe = $('iframe[name=\'frameEditor\']');
        iframe.css('top', '0px');
        iframe.css('left', '0px');

        if (!this.fullscreenMode) {
            this.formatAppToolsCss('fullscreen');
            this.triggerModeModified.emit(true);
            if (this.headerService.sideNavLeft !== null) {
                this.headerService.sideNavLeft.close();
            }
            iframe.css('position', 'fixed');
            iframe.css('z-index', '2');
        } else {
            this.formatAppToolsCss('default');
            this.triggerModeModified.emit(false);
            if (this.headerService.sideNavLeft !== null && !this.headerService.hideSideBar) {
                this.headerService.sideNavLeft.open();
            }
            iframe.css('position', 'initial');
            iframe.css('z-index', '1');
        }
        this.fullscreenMode = !this.fullscreenMode;
    }

    isAllowedEditExtension(extension: string) {
        return this.allowedExtension.filter(ext => ext.toLowerCase() === extension.toLowerCase()).length > 0;
    }

    formatAppToolsCss(mode: string, hide: boolean = false) {
        const appTools: HTMLElement = $('app-tools-informations')[0];
        if (!this.functions.empty(appTools)) {
            if (mode === 'fullscreen') {
                appTools.style.top = '10px';
                appTools.style.right = '160px';
                if (hide) {
                    appTools.style.transition =  'all 0.5s';
                    appTools.style.display = 'none';
                } else {
                    appTools.style.transition =  'all 0.5s';
                    appTools.style.display = 'flex';
                }
            } else {
                appTools.style.top = 'auto';
                appTools.style.right = 'auto';
            }
        }
    }
}
