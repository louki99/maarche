import {
    Component,
    OnInit,
    AfterViewInit,
    Input,
    EventEmitter,
    Output,
    HostListener,
    OnDestroy,
    Renderer2
} from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { catchError, tap, filter } from 'rxjs/operators';
import { TranslateService } from '@ngx-translate/core';
import { ConfirmComponent } from '../modal/confirm.component';
import { MatDialogRef, MatDialog } from '@angular/material/dialog';
import { HeaderService } from '@service/header.service';
import { of, Subject } from 'rxjs';
import { NotificationService } from '@service/notification/notification.service';
import { ScriptInjectorService } from '@service/script-injector.service';
import { Router } from '@angular/router';
import { FunctionsService } from '@service/functions.service';

declare let $: any;
declare let DocsAPI: any;

@Component({
    selector: 'app-onlyoffice-viewer',
    templateUrl: 'onlyoffice-viewer.component.html',
    styleUrls: ['onlyoffice-viewer.component.scss'],
})
export class EcplOnlyofficeViewerComponent implements OnInit, AfterViewInit, OnDestroy {

    @Input() editMode: boolean = false;
    @Input() file: any = {};
    @Input() params: any = {};
    @Input() hideCloseEditor: any = false;
    @Input() loading: boolean = false;

    @Output() triggerAfterUpdatedDoc = new EventEmitter<string>();
    @Output() triggerCloseEditor = new EventEmitter<string>();
    @Output() triggerModifiedDocument = new EventEmitter<string>();
    @Output() triggerModeModified = new EventEmitter<boolean>();

    editorConfig: any;
    docEditor: any;
    key: string = '';
    documentLoaded: boolean = false;
    canUpdateDocument: boolean = false;
    isSaving: boolean = false;
    fullscreenMode: boolean = false;

    tmpFilename: string = '';

    appUrl: string = '';
    onlyOfficeUrl: string = '';
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

    private eventAction = new Subject<any>();

    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        public dialog: MatDialog,
        public router: Router,
        private renderer: Renderer2,
        private notify: NotificationService,
        public headerService: HeaderService,
        public functions: FunctionsService,
        private scriptInjectorService: ScriptInjectorService
    ) { }

    @HostListener('window:message', ['$event'])
    onMessage(e: any) {
        const response = JSON.parse(e.data);
        // EVENT TO CONSTANTLY UPDATE CURRENT DOCUMENT
        if (response.event === 'onDownloadAs') {
            this.getEncodedDocument(response.data);
        } else if (response.event === 'onDocumentReady') {
            this.triggerModifiedDocument.emit();
        }
    }

    quit() {
        this.dialogRef = this.dialog.open(ConfirmComponent, { panelClass: 'maarch-modal', autoFocus: false, disableClose: true, data: { title: this.translate.instant('lang.close'), msg: this.translate.instant('lang.confirmCloseEditor') } });

        this.dialogRef.afterClosed().pipe(
            filter((data: string) => data === 'ok'),
            tap(() => {
                this.docEditor?.destroyEditor();
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
        this.triggerAfterUpdatedDoc.emit();
        this.triggerCloseEditor.emit();
    }

    getDocument() {
        this.isSaving = true;
        this.docEditor.downloadAs(this.file.format);
    }

    getEncodedDocument(data: any) {
        this.http.get('../rest/onlyOffice/encodedFile', { params: { url: data.url } }).pipe(
            tap((result: any) => {
                this.file.content = result.encodedFile;
                this.isSaving = false;
                this.triggerAfterUpdatedDoc.emit();
                this.eventAction.next(this.file);
            })
        ).subscribe();
    }

    getEditorMode(extension: string) {
        if (['csv', 'fods', 'ods', 'ots', 'xls', 'xlsm', 'xlsx', 'xlt', 'xltm', 'xltx'].indexOf(extension) > -1) {
            return 'spreadsheet';
        } else if (['fodp', 'odp', 'otp', 'pot', 'potm', 'potx', 'pps', 'ppsm', 'ppsx', 'ppt', 'pptm', 'pptx'].indexOf(extension) > -1) {
            return 'presentation';
        } else {
            return 'text';
        }
    }


    async ngOnInit() {
        this.key = this.generateUniqueId();

        if (this.canLaunchOnlyOffice()) {
            await this.getServerConfiguration();
            this.loadApi();
        }
    }

    loadApi() {
        const scriptElement = this.scriptInjectorService.loadJsScript(
            this.renderer,
            this.onlyOfficeUrl + '/web-apps/apps/api/documents/api.js'
        );
        scriptElement.onload = async () => {
            await this.checkServerStatus();

            await this.getMergedFileTemplate();

            this.setEditorConfig();

            await this.getTokenOOServer();

            this.initOfficeEditor();

            this.loading = false;
        };
        scriptElement.onerror = () => {
            console.log('Could not load the onlyoffice API Script!');
            this.triggerCloseEditor.emit();
        };
    }

    canLaunchOnlyOffice() {
        if (this.isAllowedEditExtension(this.file.format)) {
            return true;
        } else {
            this.notify.error(this.translate.instant('lang.onlyofficeEditDenied') + ' <b>' + this.file.format + '</b> ' + this.translate.instant('lang.onlyofficeEditDenied2'));
            this.triggerCloseEditor.emit();
            return false;
        }
    }

    getServerConfiguration() {
        return new Promise((resolve, reject) => {
            this.http.get('../rest/onlyOffice/configuration').pipe(
                tap((data: any) => {
                    if (data.enabled) {

                        const serverUriArr = data.serverUri.split('/');
                        const protocol = data.serverSsl ? 'https://' : 'http://';
                        const domain = data.serverUri.split('/')[0];
                        const path = serverUriArr.slice(1).join('/');
                        const port = data.serverPort ? `:${data.serverPort}` : ':80';

                        const serverUri = [domain + port, path].join('/');

                        this.onlyOfficeUrl = `${protocol}${serverUri}`;
                        this.appUrl = data.coreUrl;
                        resolve(true);
                    } else {
                        this.triggerCloseEditor.emit();
                    }
                }),
                catchError((err) => {
                    this.notify.handleErrors(err);
                    this.triggerCloseEditor.emit();
                    return of(false);
                }),
            ).subscribe();
        });
    }


    checkServerStatus() {
        return new Promise((resolve, reject) => {
            const regex = /127\.0\.0\.1/g;
            const regex2 = /localhost/g;
            if (this.appUrl.match(regex) !== null || this.appUrl.match(regex2) !== null) {
                this.notify.error(`${this.translate.instant('lang.errorOnlyoffice1')}`);
                this.triggerCloseEditor.emit();
            } else {
                this.http.get('../rest/onlyOffice/available').pipe(
                    tap((data: any) => {
                        if (data.isAvailable) {
                            resolve(true);
                        } else {
                            this.notify.error(`${this.translate.instant('lang.errorOnlyoffice2')} ${this.onlyOfficeUrl}`);
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

    getMergedFileTemplate() {
        return new Promise((resolve, reject) => {
            this.http.post(`../${this.params.docUrl}`, { objectId: this.params.objectId, objectType: this.params.objectType, format: this.file.format, onlyOfficeKey: this.key, data: this.params.dataToMerge }).pipe(
                tap((data: any) => {
                    this.tmpFilename = data.filename;

                    this.file = {
                        name: this.key,
                        format: data.filename.split('.').pop(),
                        type: null,
                        contentMode: 'base64',
                        content: null,
                        src: null
                    };
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
        const characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
        const charactersLength = characters.length;
        for (let i = 0; i < length; i++) {
            result += characters.charAt(Math.floor(Math.random() * charactersLength));
        }
        return result;
    }

    ngAfterViewInit() {

    }

    initOfficeEditor() {
        this.docEditor = new DocsAPI.DocEditor('placeholder', this.editorConfig, this.onlyOfficeUrl);
    }

    getTokenOOServer() {
        return new Promise((resolve, reject) => {
            this.http.post('../rest/onlyOffice/token', { config: this.editorConfig }).pipe(
                tap((data: any) => {
                    if (data !== null) {
                        this.editorConfig.token = data;
                    }
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

    setEditorConfig() {
        this.editorConfig = {
            documentType: this.getEditorMode(this.file.format),
            document: {
                fileType: this.file.format,
                key: this.key,
                title: 'Edition',
                url: `${this.appUrl}${this.params.docUrl}?filename=${this.tmpFilename}`,
                permissions: {
                    comment: true,
                    download: true,
                    edit: this.editMode,
                    print: true,
                    deleteCommentAuthorOnly: true,
                    editCommentAuthorOnly: true,
                    review: false,
                    commentGroups: {
                        edit: ['owner'],
                        remove: ['owner'],
                        view: ''
                    },
                }
            },
            editorConfig: {
                callbackUrl: `${this.appUrl}rest/onlyOfficeCallback`,
                lang: this.translate.instant('lang.language'),
                region: this.translate.instant('lang.langISO'),
                mode: 'edit',
                customization: {
                    chat: false,
                    comments: true,
                    compactToolbar: false,
                    feedback: false,
                    forcesave: false,
                    goback: false,
                    hideRightMenu: true,
                    showReviewChanges: false,
                    zoom: -2,
                },
                user: {
                    id: this.headerService.user.id.toString(),
                    name: `${this.headerService.user.firstname} ${this.headerService.user.lastname}`,
                    group: 'owner'
                },
            },
        };
    }

    isLocked() {
        if (this.isSaving) {
            return true;
        } else {
            return false;
        }
    }

    getFile() {
        // return this.file;
        this.getDocument();
        return this.eventAction.asObservable();
    }

    ngOnDestroy() {
        this.eventAction.complete();
    }

    openFullscreen() {
        $('iframe[name=\'frameEditor\']').css('top', '0px');
        $('iframe[name=\'frameEditor\']').css('left', '0px');

        if (!this.fullscreenMode) {
            this.formatAppToolsCss('fullscreen');
            this.triggerModeModified.emit(true);
            if (this.headerService.sideNavLeft !== null) {
                this.headerService.sideNavLeft.close();
            }
            $('iframe[name=\'frameEditor\']').css('position', 'fixed');
            $('iframe[name=\'frameEditor\']').css('z-index', '2');
        } else {
            this.formatAppToolsCss('default');
            this.triggerModeModified.emit(false);
            if (this.headerService.sideNavLeft !== null && !this.headerService.hideSideBar) {
                this.headerService.sideNavLeft.open();
            }
            $('iframe[name=\'frameEditor\']').css('position', 'initial');
            $('iframe[name=\'frameEditor\']').css('z-index', '1');
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
                    appTools.style.display = 'none';
                    appTools.style.transition =  'all 0.5s';
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
