import { AfterViewInit, Component, EventEmitter, Input, OnDestroy, OnInit, Output } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { catchError, tap } from 'rxjs/operators';
import { TranslateService } from '@ngx-translate/core';
import { MatDialog, MatDialogRef } from '@angular/material/dialog';
import { HeaderService } from '@service/header.service';
import { NotificationService } from '@service/notification/notification.service';
import { of, Subject } from 'rxjs';

@Component({
    selector: 'app-office-sharepoint-viewer',
    templateUrl: 'office365-sharepoint-viewer.component.html',
    styleUrls: [
        'office365-sharepoint-viewer.component.scss'
    ],
})
export class Office365SharepointViewerComponent implements OnInit, AfterViewInit, OnDestroy {

    @Input() editMode: boolean = false;
    @Input() file: any = {};
    @Input() params: any = {};

    @Output() triggerAfterUpdatedDoc = new EventEmitter<string>();
    @Output() triggerCloseEditor = new EventEmitter<string>();
    @Output() triggerModifiedDocument = new EventEmitter<string>();
    @Output() triggerDocumentDownload = new EventEmitter<string>();

    loading: boolean = true;

    editorConfig: any;
    key: number = 0;
    isSaving: boolean = false;
    isModified: boolean = false;

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

    documentId: any;
    documentWebUrl: any = null;

    private eventAction = new Subject<any>();

    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        public dialog: MatDialog,
        private notify: NotificationService,
        public headerService: HeaderService) { }

    async ngOnInit() {
        this.key = this.generateUniqueId(10);

        if (this.canLaunchOffice365Sharepoint()) {
            this.params.objectPath = undefined;
            if (typeof this.params.objectId === 'string' && (this.params.objectType === 'templateModification' || this.params.objectType === 'templateCreation')) {
                this.params.objectPath = this.params.objectId;
                this.params.objectId = this.key;
            } else if (typeof this.params.objectId === 'string' && this.params.objectType === 'encodedResource') {
                this.params.content = this.params.objectId;
                this.params.objectId = this.key;
                this.params.objectType = 'templateEncoded';
            }

            await this.sendDocument();
            this.loading = false;
        }
    }

    closeEditor() {
        if (this.headerService.sideNavLeft !== null && !this.headerService.hideSideBar) {
            this.headerService.sideNavLeft.open();
        }

        this.triggerCloseEditor.emit();

        setTimeout(() => {
            this.deleteDocument();
        }, 10000);
    }

    canLaunchOffice365Sharepoint() {
        if (this.isAllowedEditExtension(this.file.format)) {
            return true;
        } else {
            this.notify.error(this.translate.instant('lang.onlyofficeEditDenied') + ' <b>' + this.file.format + '</b> ' + this.translate.instant('lang.officeSharepointEditDenied'));
            this.triggerCloseEditor.emit();
            return false;
        }
    }

    getEncodedDocument() {
        return new Promise((resolve) => {
            this.http.get('../rest/office365/' + this.documentId).pipe(
                tap((data: any) => {
                    this.file = {
                        name: this.key,
                        type: null,
                        contentMode: 'base64',
                        content: data.content,
                        src: null,
                        format: this.file.format
                    };
                    this.eventAction.next(this.file);
                    setTimeout(() => {
                        this.deleteDocument();
                    }, 10000);
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

    sendDocument() {
        return new Promise((resolve) => {
            this.http.post('../rest/office365', {
                resId: this.params.objectId,
                type: this.params.objectType,
                format: this.file.format,
                path: this.params.objectPath,
                data: this.params.dataToMerge,
                encodedContent: this.params.content
            }).pipe(
                tap((data: any) => {
                    this.documentId = data.documentId;
                    this.documentWebUrl = data.webUrl;
                    this.openDocument();
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

    deleteDocument() {
        return new Promise((resolve) => {
            this.http.request('DELETE', '../rest/office365/' + this.documentId, {body: {resId: this.params.objectId}}).pipe(
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

    getFile() {
        this.isSaving = true;
        this.getEncodedDocument();
        return this.eventAction.asObservable();
    }

    ngOnDestroy() {
        this.eventAction.complete();
    }

    isAllowedEditExtension(extension: string) {
        return this.allowedExtension.filter(ext => ext.toLowerCase() === extension.toLowerCase()).length > 0;
    }

    openDocument() {
        this.triggerModifiedDocument.emit();
        this.isModified = true;
        window.open(this.documentWebUrl, '_blank');
    }

    downloadDocument() {
        this.isSaving = true;
        this.triggerDocumentDownload.emit();
    }
}
