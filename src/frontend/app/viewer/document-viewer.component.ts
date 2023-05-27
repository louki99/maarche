import { Component, OnInit, Input, ViewChild, EventEmitter, Output, OnDestroy } from '@angular/core';
import { HttpClient, HttpEventType } from '@angular/common/http';
import { TranslateService } from '@ngx-translate/core';
import { NotificationService } from '@service/notification/notification.service';
import { HeaderService } from '@service/header.service';
import { AppService } from '@service/app.service';
import { tap, catchError, filter, map, exhaustMap, take, finalize } from 'rxjs/operators';
import { ConfirmComponent } from '@plugins/modal/confirm.component';
import { MatDialogRef, MatDialog } from '@angular/material/dialog';
import { AlertComponent } from '@plugins/modal/alert.component';
import { SortPipe } from '@plugins/sorting.pipe';
import { PluginSelectSearchComponent } from '@plugins/select-search/select-search.component';
import { UntypedFormControl } from '@angular/forms';
import { EcplOnlyofficeViewerComponent } from '@plugins/onlyoffice-api-js/onlyoffice-viewer.component';
import { FunctionsService } from '@service/functions.service';
import { DocumentViewerModalComponent } from './modal/document-viewer-modal.component';
import { PrivilegeService } from '@service/privileges.service';
import { VisaWorkflowModalComponent } from '../visa/modal/visa-workflow-modal.component';
import { of } from 'rxjs';
import { CollaboraOnlineViewerComponent } from '@plugins/collabora-online/collabora-online-viewer.component';
import { AuthService } from '@service/auth.service';
import { LocalStorageService } from '@service/local-storage.service';
import { Office365SharepointViewerComponent } from '@plugins/office365-sharepoint/office365-sharepoint-viewer.component';
import { ExternalSignatoryBookManagerService } from '@service/externalSignatoryBook/external-signatory-book-manager.service';

@Component({
    selector: 'app-document-viewer',
    templateUrl: 'document-viewer.component.html',
    styleUrls: [
        'document-viewer.component.scss',
        '../indexation/indexing-form/indexing-form.component.scss',
    ],
    providers: [SortPipe, ExternalSignatoryBookManagerService]
})

export class DocumentViewerComponent implements OnInit, OnDestroy {

    @ViewChild('templateList', { static: true }) templateList: PluginSelectSearchComponent;
    @ViewChild('onlyofficeViewer', { static: false }) onlyofficeViewer: EcplOnlyofficeViewerComponent;
    @ViewChild('collaboraOnlineViewer', { static: false }) collaboraOnlineViewer: CollaboraOnlineViewerComponent;
    @ViewChild('officeSharepointViewer', { static: false }) officeSharepointViewer: Office365SharepointViewerComponent;

    /**
     * document name stored in server (in tmp folder)
     */
    @Input() tmpFilename: string;

    /**
     * base64 of document  (@format is required!)
     */
    @Input() base64: any = null;
    @Input() format: string = null;
    @Input() filename: string = null;

    /**
     * Target of resource (document or attachment)
     */
    @Input() mode: 'mainDocument' | 'attachment' = 'mainDocument';

    /**
     * Resource of document or attachment (based on @mode)
     */
    @Input() resId: number = null;


    /**
     * Resource of document link to attachment (@mode = 'attachment' required!)
     */
    @Input() resIdMaster: number = null;

    /**
     * Can manage document ? (create, delete, update)
     */
    @Input() editMode: boolean = false;

    /**
     * Hide tool document viewer
     */
    @Input() hideTools: boolean = false;

    /**
     * Title of new tab when open document in external tab
     */
    @Input() title: string = '';

    /**
     * To load specific attachment type in template list (to create document)
     */
    @Input() attachType: string = null;

    /**
     * Download actions
     */
    @Input() downloadActions: any[] = [];

    @Input() isSigned: boolean = false;

    @Input() newPjVersion: boolean = false;

    @Input() isNewVersion: boolean = false;

    /**
      * Event emitter
      */
    @Output() triggerEvent = new EventEmitter<string>();


    /**
     * Use in resourceDatas.inMailing = true
     */
    allowedExtensionsMailing: string[] = [
        'doc',
        'docx',
        'dotx',
        'odt',
        'ott',
        'html',
        'xlsl',
        'xlsx',
        'xltx',
        'ods',
        'ots',
        'csv',
    ];

    loading: boolean = true;
    noConvertedFound: boolean = false;

    noFile: boolean = false;

    file: any = {
        name: '',
        type: '',
        contentMode: 'base64',
        content: null,
        src: null
    };

    allowedExtensions: any[] = [];
    maxFileSize: number = 0;
    maxFileSizeLabel: string = '';

    percentInProgress: number = 0;

    intervalLockFile: any;
    editInProgress: boolean = false;


    listTemplates: any[] = [];
    externalId: any = {};

    templateListForm = new UntypedFormControl();

    resourceDatas: any;

    loadingInfo: any = {
        mode: 'indeterminate',
        percent: 0,
        message: '',
    };

    dialogRef: MatDialogRef<any>;
    editor: any = {
        mode: '',
        async: true,
        options: {
            docUrl: null,
            dataToMerge: null
        }
    };

    isDocModified: boolean = false;

    docToUploadValue: any;

    logoutTrigger: boolean = false;

    status: string = '';


    rotation: number = null;
    zoom: number = 1;

    isFullScreen: boolean = false;

    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        public headerService: HeaderService,
        public appService: AppService,
        public functions: FunctionsService,
        public privilegeService: PrivilegeService,
        public externalSignatoryBook: ExternalSignatoryBookManagerService,
        private notify: NotificationService,
        private dialog: MatDialog,
        private sortPipe: SortPipe,
        private authService: AuthService,
        private localStorage: LocalStorageService
    ) {
        (<any>window).pdfWorkerSrc = 'pdfjs/pdf.worker.min.js';
    }

    ngOnInit() {
        if (this.downloadActions === undefined) {
            this.downloadActions = [];
        }

        this.setEditor();

        this.http.get('../rest/indexing/fileInformations').pipe(
            tap((data: any) => {
                this.allowedExtensions = data.informations.allowedFiles.map((ext: any) => ({
                    extension: '.' + ext.extension.toLowerCase(),
                    mimeType: ext.mimeType,
                    canConvert: ext.canConvert
                }));
                this.allowedExtensions = this.sortPipe.transform(this.allowedExtensions, 'extension');

                this.maxFileSize = data.informations.maximumSize;
                this.maxFileSizeLabel = data.informations.maximumSizeLabel;

                if (this.resId !== null) {
                    this.loadRessource(this.resId, this.mode);
                    if (this.editMode) {
                        if (this.attachType !== null && this.mode === 'attachment') {
                            this.loadTemplatesByResId(this.resIdMaster, this.attachType);
                        } else {
                            this.loadTemplates();
                        }
                    }
                } else {
                    this.loadTemplates();
                    this.loading = false;
                }
            }),
            catchError((err: any) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();

        if (!this.functions.empty(this.base64)) {
            this.loadFileFromBase64();
        } else if (this.tmpFilename !== '' && this.tmpFilename !== undefined) {
            this.http.get('../rest/convertedFile/' + this.tmpFilename).pipe(
                tap((data: any) => {
                    this.file = {
                        name: this.tmpFilename,
                        format: 'pdf',
                        type: 'application/pdf',
                        contentMode: 'base64',
                        content: this.getBase64Document(this.base64ToArrayBuffer(data.encodedResource)),
                        src: this.base64ToArrayBuffer(data.encodedResource)
                    };
                    this.noConvertedFound = false;
                    this.loading = false;
                }),
                catchError((err: any) => {
                    this.notify.handleErrors(err);
                    return of(false);
                })
            ).subscribe();
        }
    }

    ngOnDestroy() {
        if (this.intervalLockFile) {
            this.cancelTemplateEdition();
        }
        this.rotation = null;
    }

    loadFileFromBase64() {
        this.loading = true;
        this.file = {
            name: 'maarch',
            format: 'pdf',
            type: 'application/pdf',
            contentMode: 'base64',
            content: this.base64,
            src: this.base64ToArrayBuffer(this.base64)
        };
        if (!this.functions.empty(this.filename)) {
            this.file.name = this.filename.substring(0, this.filename.lastIndexOf('.'));
            this.file.format = this.filename.split('.').pop();
        }
        this.loading = false;
    }

    loadTmpFile(filenameOnTmp: string) {
        return new Promise((resolve, reject) => {
            this.loading = true;
            this.loadingInfo.mode = 'determinate';

            this.requestWithLoader(`../rest/convertedFile/${filenameOnTmp}?convert=true`).subscribe(
                (data: any) => {
                    if (data.encodedResource) {
                        this.file = {
                            name: filenameOnTmp,
                            format: data.extension,
                            type: data.type,
                            contentMode: 'base64',
                            content: data.encodedResource,
                            src: data.encodedConvertedResource !== undefined ? this.base64ToArrayBuffer(data.encodedConvertedResource) : null
                        };
                        this.editMode = true;
                        this.triggerEvent.emit();
                        if (data.encodedConvertedResource !== undefined) {
                            this.noConvertedFound = false;
                        } else {
                            this.noConvertedFound = true;
                            this.notify.error(data.convertedResourceErrors);
                        }
                        this.loading = false;
                        resolve(true);
                    }
                },
                (err: any) => {
                    this.noConvertedFound = true;
                    this.notify.handleErrors(err);
                    this.loading = false;
                    resolve(true);
                    return of(false);
                }
            );
        });
    }

    uploadTrigger(fileInput: any) {
        if (fileInput.target.files && fileInput.target.files[0] && this.isExtensionAllowed(fileInput.target.files[0])) {
            const fileData: any = {
                name : fileInput.target.files[0].name,
                type : fileInput.target.files[0].type,
                format : fileInput.target.files[0].name.split('.').pop(),
                content: null
            };
            // CHECK IF WE ARE UPLOADING NEW VERSION FOR DOCUMENT
            if (this.isNewVersion && this.mode !== 'attachment') {
                this.setNewVersion(fileInput, fileData);
            } else {
                this.initUpload();

                const reader = new FileReader();
                this.file.name = fileData.name;
                this.file.type = fileData.type;
                this.file.format = fileData.format;

                reader.readAsArrayBuffer(fileInput.target.files[0]);

                reader.onload = (value: any) => {
                    this.file.content = this.getBase64Document(value.target.result);
                    this.triggerEvent.emit('uploadFile');
                    if (this.file.type !== 'application/pdf') {
                        this.convertDocument(this.file);
                    } else {
                        this.file.src = value.target.result;
                        this.loading = false;
                    }
                };
            }
        } else {
            this.loading = false;
        }
    }

    setNewVersion(fileInput: any, file: any) {
        if (this.canBeConverted(file)) {
            const reader = new FileReader();
            reader.readAsArrayBuffer(fileInput.target.files[0]);
            reader.onload = async (value: any) => {
                file.content = this.getBase64Document(value.target.result);
                this.triggerEvent.emit('uploadFile');
                if (file.type !== 'application/pdf') {
                    this.loading = true;
                    this.convertDocument(file);
                } else {
                    file.base64 = this.getBase64Document(value.target.result);
                    this.openDocumentViewerModal(file);
                }
            };
        } else {
            this.notify.error(this.translate.instant('lang.fileNotConvertible'));
        }
    }

    openDocumentViewerModal(file: any) {
        this.loading = false;
        const dialogRef = this.dialog.open(DocumentViewerModalComponent, {
            autoFocus: false,
            disableClose: true,
            panelClass: ['maarch-full-height-modal', 'maarch-doc-modal'],
            data: {
                title: file.name,
                filename: file.name,
                base64: file.base64,
                isNewVersion: true
            }
        });

        dialogRef.afterClosed().pipe(
            tap((data: any) => {
                if (data === 'createNewVersion' && this.mode === 'mainDocument') {
                    const objToSend: any = {
                        resId: this.resId,
                        encodedFile: file.content,
                        format: file.format
                    };
                    this.http.put(`../rest/resources/${this.resId}?onlyDocument=true`, objToSend).pipe(
                        tap(() => {
                            this.loadRessource(this.resId);
                            this.isNewVersion = false;
                        }),
                        catchError((err: any) => {
                            this.notify.handleSoftErrors(err);
                            return of(false);
                        })
                    ).subscribe();
                } else {
                    this.isNewVersion = false;
                }
            })
        ).subscribe();

    }

    canUploadNewVersion() {
        if (this.editMode) {
            if ((this.externalId.signatureBookId !== undefined) || (this.resId != null && ((this.mode === 'mainDocument' && (this.noConvertedFound || this.isSigned)) || (this.mode === 'attachment' && (!this.newPjVersion || this.noConvertedFound))))) {
                return false;
            } else {
                return ((this.file.contentView !== undefined || this.base64 !== null) || (this.file.content !== null && !this.noConvertedFound)) && this.resId !== null;
            }
        } else {
            return false;
        }
    }


    initUpload() {
        this.loading = true;
        this.file = {
            name: '',
            type: '',
            contentMode: 'base64',
            content: null,
            src: null
        };
        this.noConvertedFound = false;
        this.loadingInfo.message = this.translate.instant('lang.loadingFile') + '...';
        this.loadingInfo.mode = 'indeterminate';
    }

    getBase64Document(buffer: ArrayBuffer) {
        const TYPED_ARRAY = new Uint8Array(buffer);
        const STRING_CHAR = TYPED_ARRAY.reduce((data, byte) => data + String.fromCharCode(byte), '');

        return btoa(STRING_CHAR);
    }

    base64ToArrayBuffer(base64: string) {
        const binary_string = window.atob(base64);
        const len = binary_string.length;
        const bytes = new Uint8Array(len);
        for (let i = 0; i < len; i++) {
            bytes[i] = binary_string.charCodeAt(i);
        }
        return bytes.buffer;
    }

    b64toBlob(b64Data: any, contentType = '', sliceSize = 512) {
        const byteCharacters = atob(b64Data);
        const byteArrays = [];

        for (let offset = 0; offset < byteCharacters.length; offset += sliceSize) {
            const slice = byteCharacters.slice(offset, offset + sliceSize);

            const byteNumbers = new Array(slice.length);
            for (let i = 0; i < slice.length; i++) {
                byteNumbers[i] = slice.charCodeAt(i);
            }

            const byteArray = new Uint8Array(byteNumbers);
            byteArrays.push(byteArray);
        }

        const blob = new Blob(byteArrays, { type: contentType });
        return blob;
    }

    convertDocument(file: any) {
        if (this.canBeConverted(file)) {
            return new Promise((resolve) => {
                const data = { name: file.name, base64: file.content };
                this.upload(data).pipe(
                    tap((res: any) => {
                        if (res.encodedResource) {
                            if (this.isNewVersion && this.mode !== 'attachment') {
                                file.base64 = res.encodedResource;
                                file.src = this.base64ToArrayBuffer(res.encodedResource);
                                this.openDocumentViewerModal(file);
                            } else {
                                this.file.base64src = res.encodedResource;
                                this.file.src = this.base64ToArrayBuffer(res.encodedResource);
                                this.loading = false;
                            }
                        }
                        resolve(file);
                    }),
                    catchError((err: any) => {
                        this.noConvertedFound = true;
                        this.notify.handleErrors(err);
                        this.loading = false;
                        return of(false);
                    })
                ).subscribe();
            });
        } else {
            this.noConvertedFound = true;
            this.loading = false;
        }
    }

    upload(data: any) {
        const uploadURL = '../rest/convertedFile';

        return this.http.post<any>(uploadURL, data, {
            reportProgress: true,
            observe: 'events'
        }).pipe(map((event) => {

            switch (event.type) {
                case HttpEventType.DownloadProgress:

                    const downloadProgress = Math.round(100 * event.loaded / event.total);
                    this.loadingInfo.percent = downloadProgress;
                    this.loadingInfo.mode = 'determinate';
                    this.loadingInfo.message = `3/3 ${this.translate.instant('lang.downloadConvertedFile')}...`;

                    return { status: 'progress', message: downloadProgress };

                case HttpEventType.UploadProgress:
                    const progress = Math.round(100 * event.loaded / event.total);
                    this.loadingInfo.percent = progress;

                    if (progress === 100) {
                        this.loadingInfo.mode = 'indeterminate';
                        this.loadingInfo.message = `2/3 ${this.translate.instant('lang.convertingFile')}...`;
                    } else {
                        this.loadingInfo.mode = 'determinate';
                        this.loadingInfo.message = `1/3 ${this.translate.instant('lang.loadingFile')}...`;
                    }
                    return { status: 'progress', message: progress };

                case HttpEventType.Response:
                    return event.body;
                default:
                    return `Unhandled event: ${event.type}`;
            }
        })
        );
    }

    requestWithLoader(url: string) {
        this.loadingInfo.percent = 0;

        return this.http.get<any>(url, {
            reportProgress: true,
            observe: 'events'
        }).pipe(map((event) => {
            switch (event.type) {
                case HttpEventType.DownloadProgress:

                    const downloadProgress = Math.round(100 * event.loaded / event.total);
                    this.loadingInfo.percent = downloadProgress;
                    this.loadingInfo.mode = 'determinate';
                    this.loadingInfo.message = '';

                    return { status: 'progressDownload', message: downloadProgress };

                case HttpEventType.Response:
                    return event.body;
                default:
                    return `Unhandled event: ${event.type}`;
            }
        })
        );
    }

    onError(error: any) {
        console.log(error);
    }

    cleanFile(confirm: boolean = true) {
        if (confirm) {
            this.dialogRef = this.dialog.open(ConfirmComponent, { panelClass: 'maarch-modal', autoFocus: false, disableClose: true, data: { title: this.translate.instant('lang.delete'), msg: this.translate.instant('lang.confirmAction') } });

            this.dialogRef.afterClosed().pipe(
                filter((data: string) => data === 'ok'),
                tap(() => {
                    this.resetFileData();
                }),
                catchError((err: any) => {
                    this.notify.handleErrors(err);
                    return of(false);
                })
            ).subscribe();
        } else {
            this.resetFileData();
        }

    }

    resetFileData() {
        this.templateListForm.reset();
        this.file = {
            name: '',
            type: '',
            content: null,
            src: null
        };
        this.docToUploadValue = '';
        this.triggerEvent.emit('cleanFile');
    }

    async saveDocService() {
        this.headerService.setLoadedFile(null);
        const data: any = await this.getFilePdf();

        this.headerService.setLoadedFile(data);
    }

    getFile() {
        if (this.editor.mode === 'onlyoffice' && this.onlyofficeViewer !== undefined) {
            return this.onlyofficeViewer.getFile();
        } else if (this.editor.mode === 'collaboraOnline' && this.collaboraOnlineViewer !== undefined) {
            return this.collaboraOnlineViewer.getFile();
        } else if (this.editor.mode === 'office365sharepoint' && this.officeSharepointViewer !== undefined) {
            return this.officeSharepointViewer.getFile();
        } else {
            const objFile = JSON.parse(JSON.stringify(this.file));
            objFile.content = objFile.contentMode === 'route' ? null : objFile.content;

            return of(objFile);
        }
    }

    getFilePdf() {
        return new Promise((resolve) => {
            if (!this.functions.empty(this.file.src)) {
                resolve(this.getBase64Document(this.file.src));
            } else {
                this.getFile().pipe(
                    take(1),
                    tap((data: any) => {
                        if (this.editor.mode === 'collaboraOnline' && this.collaboraOnlineViewer !== undefined) {
                            this.collaboraOnlineViewer.isSaving = false;
                        }
                        return data;
                    }),
                    filter((data: any) => !this.functions.empty(data.content)),
                    exhaustMap((data: any) => this.http.post('../rest/convertedFile', { name: `${data.name}.${data.format}`, base64: `${data.content}` })),
                    tap((data: any) => {
                        resolve(data.encodedResource);
                    })
                ).subscribe();
            }
        });
    }

    dndUploadFile(event: any) {
        const fileInput = {
            target: {
                files: [
                    event[0]
                ]
            }
        };
        this.uploadTrigger(fileInput);
    }

    canBeConverted(file: any): boolean {
        const fileExtension = '.' + file.name.toLowerCase().split('.').pop();
        if (this.allowedExtensions.filter(ext => ext.canConvert === true && ext.mimeType === file.type && ext.extension === fileExtension).length > 0) {
            return true;
        } else {
            return false;
        }
    }

    isExtensionAllowed(file: any) {
        const fileExtension = '.' + file.name.toLowerCase().split('.').pop();
        const allowedExtensions = this.resourceDatas?.inMailing ? this.allowedExtensions.filter(ext => this.allowedExtensionsMailing.indexOf(ext.extension.replace('.', '')) > -1) : this.allowedExtensions;

        if (allowedExtensions.filter(ext => (ext.mimeType === file.type || (this.functions.empty(ext.mimeType) && this.functions.empty(file.type))) && ext.extension === fileExtension).length === 0) {
            this.dialog.open(AlertComponent, { panelClass: 'maarch-modal', autoFocus: false, disableClose: true, data: { title: this.translate.instant('lang.notAllowedExtension') + ' !', msg: this.translate.instant('lang.file') + ' : <b>' + file.name + '</b>, ' + this.translate.instant('lang.type') + ' : <b>' + file.type + '</b><br/><br/><u>' + this.translate.instant('lang.allowedExtensions') + '</u> : <br/>' + allowedExtensions.map(ext => ext.extension).filter((elem: any, index: any, self: any) => index === self.indexOf(elem)).join(', ') } });
            return false;
        } else if (file.size > this.maxFileSize && this.maxFileSize > 0) {
            this.dialog.open(AlertComponent, { panelClass: 'maarch-modal', autoFocus: false, disableClose: true, data: { title: this.translate.instant('lang.maxFileSizeReached') + ' ! ', msg: this.translate.instant('lang.maxFileSize') + ' : ' + this.maxFileSizeLabel } });
            return false;
        } else {
            return true;
        }
    }

    downloadOriginalFile(data: any) {
        const downloadLink = document.createElement('a');
        downloadLink.href = `data:${data.mimeType};base64,${data.encodedDocument}`;
        downloadLink.setAttribute('download', data.filename);
        document.body.appendChild(downloadLink);
        downloadLink.click();
    }

    downloadConvertedFile() {
        const downloadLink = document.createElement('a');
        if (this.file.contentMode === 'base64') {
            let fileName: string = '';
            if (this.isSigned) {
                fileName = this.file.name.substring(0, this.file.name.indexOf('_V'));
            }
            downloadLink.href = `data:${this.file.type};base64,${this.file.content}`;
            downloadLink.setAttribute('download', fileName !== '' ? fileName : this.file.name);
            document.body.appendChild(downloadLink);
            downloadLink.click();
        } else {
            this.http.get(this.file.content).pipe(
                tap((data: any) => {
                    const formatFileName: any = data.filename.substring(0, data.filename.lastIndexOf('.'));
                    if (formatFileName !== undefined) {
                        data.filename = this.file.subinfos?.signedDocVersions || this.file.subinfos?.commentedDocVersions ? formatFileName : data.filename;
                    }
                    downloadLink.href = `data:${data.mimeType};base64,${data.encodedDocument}`;
                    downloadLink.setAttribute('download', data.filename);
                    document.body.appendChild(downloadLink);
                    downloadLink.click();
                }),
                catchError((err: any) => {
                    this.notify.handleSoftErrors(err);
                    return of(false);
                })
            ).subscribe();
        }
    }

    openPdfInTab() {
        const src = '';
        if (this.file.contentMode === 'route'){
            this.http.get(this.file.contentView, { responseType: 'json' }).pipe(
                tap((data: any) => {
                    const contentBlob = this.b64toBlob(data.encodedDocument, data.mimeType);
                    const fileURL = URL.createObjectURL(contentBlob);
                    const newWindow = window.open();
                    newWindow.document.write(`<iframe style="width: 100%;height: 100%;margin: 0;padding: 0;" src="${fileURL}" frameborder="0" allowfullscreen></iframe>`);
                    newWindow.document.title = data.filename;
                }),
                catchError((err: any) => {
                    this.notify.handleSoftErrors(err);
                    return of(false);
                })
            ).subscribe();
        } else if (this.file.contentMode === 'base64') {
            this.http.get(this.file.contentView, { responseType: 'json' }).pipe(
                tap((data: any) => {
                    const contentBlob = this.b64toBlob(data.encodedDocument, data.mimeType);
                    const fileURL = URL.createObjectURL(contentBlob);
                    const newWindow = window.open();
                    newWindow.document.write(`<iframe style="width: 100%;height: 100%;margin: 0;padding: 0;" src="${fileURL}" frameborder="0" allowfullscreen></iframe>`);
                    newWindow.document.title = data.filename;
                }),
                catchError((err: any) => {
                    this.notify.handleSoftErrors(err);
                    return of(false);
                })
            ).subscribe();
        } else {
            this.http.get(this.file.contentView).pipe(
                tap((data: any) => {
                    const contentBlob = this.b64toBlob(data.encodedDocument, data.mimeType);
                    const fileURL = URL.createObjectURL(contentBlob);
                    const newWindow = window.open();
                    newWindow.document.write(`<iframe style="width: 100%;height: 100%;margin: 0;padding: 0;" src="${fileURL}" frameborder="0" allowfullscreen></iframe>`);
                    newWindow.document.title = data.title;
                }),
                catchError((err: any) => {
                    this.notify.handleSoftErrors(err);
                    return of(false);
                })
            ).subscribe();
        }
    }

    async loadRessource(resId: any, target: any = 'mainDocument') {
        this.resId = resId;
        this.mode = target;
        this.loading = true;
        if (target === 'attachment') {
            this.requestWithLoader(`../rest/attachments/${resId}/content?mode=base64`).subscribe(
                (data: any) => {
                    if (data.encodedDocument) {
                        this.file.contentMode = 'route';
                        this.file.name = `${data.filename}`;
                        this.file.format = data.originalFormat;
                        this.file.creatorId = data.originalCreatorId;
                        this.file.signatoryId = data.signatoryId;
                        this.file.content = `../rest/attachments/${resId}/originalContent?mode=base64`;
                        this.file.contentView = `../rest/attachments/${resId}/content?mode=base64`;
                        this.file.src = this.base64ToArrayBuffer(data.encodedDocument);
                        this.loading = false;
                        this.noFile = false;
                    }
                },
                (err: any) => {
                    if (err.error.errors === 'Document has no file') {
                        this.noFile = true;
                    } else if (err.error.errors === 'Converted Document not found' || err.error.errors === 'Document can not be converted') {
                        this.file.contentMode = 'route';
                        this.file.content = `../rest/attachments/${resId}/originalContent?mode=base64`;
                        this.noConvertedFound = true;
                    } else {
                        this.notify.error(err.error.errors);
                        this.noFile = true;
                    }
                    this.loading = false;
                    return of(false);
                }
            );
        } else {
            await this.loadMainDocumentSubInformations();

            if (this.file.subinfos.mainDocVersions.length === 0) {
                this.noFile = true;
                this.loading = false;
            } else if (!this.file.subinfos.canConvert) {
                this.file.contentMode = 'route';
                this.file.content = `../rest/resources/${resId}/originalContent?mode=base64`;
                this.noConvertedFound = true;
                this.loading = false;
            } else {
                this.requestWithLoader(`../rest/resources/${resId}/content?mode=base64`).subscribe(
                    (data: any) => {
                        if (data.encodedDocument) {
                            this.isSigned = this.file.subinfos.signedDocVersions;
                            const fileToDownload: string = this.file.subinfos.signedDocVersions || this.file.subinfos.commentedDocVersions ? 'originalContent?signedVersion=true&mode=base64' : 'originalContent?mode=base64';                            this.file.contentMode = 'route';
                            this.file.name = `${data.filename}`;
                            this.file.format = data.originalFormat;
                            this.file.signatoryId = data.signatoryId;
                            this.file.content = `../rest/resources/${resId}/${fileToDownload}`;
                            this.file.contentView = `../rest/resources/${resId}/content?mode=base64`;
                            this.file.src = this.base64ToArrayBuffer(data.encodedDocument);
                            this.loading = false;
                            this.noFile = false;
                        }
                    },
                    (err: any) => {
                        this.notify.error(err.error.errors);
                        this.noFile = true;
                        this.loading = false;
                        return of(false);
                    }
                );
                this.http.get(`../rest/resources/${this.resId}/fields/externalId`).pipe(
                    tap((data: any) => {
                        this.externalId = data.field;
                    }),
                    catchError((err: any) => {
                        this.notify.handleSoftErrors(err);
                        return of(false);
                    })
                ).subscribe();
            }
        }
    }

    loadMainDocumentSubInformations() {
        return new Promise((resolve, reject) => {
            this.http.get(`../rest/resources/${this.resId}/versionsInformations`).pipe(
                tap((data: any) => {
                    const mainDocVersions = data.DOC;
                    let mainDocPDFVersions = false;
                    let signedDocVersions = false;
                    let commentedDocVersions = false;
                    if (data.DOC[data.DOC.length - 1] !== undefined) {
                        signedDocVersions = data.SIGN.indexOf(data.DOC[data.DOC.length - 1]) > -1 ? true : false;
                        commentedDocVersions = data.NOTE.indexOf(data.DOC[data.DOC.length - 1]) > -1 ? true : false;
                        mainDocPDFVersions = data.PDF.indexOf(data.DOC[data.DOC.length - 1]) > -1 ? true : false;
                    }

                    this.file.subinfos = {
                        mainDocVersions: mainDocVersions,
                        signedDocVersions: signedDocVersions,
                        commentedDocVersions: commentedDocVersions,
                        mainDocPDFVersions: mainDocPDFVersions
                    };
                }),
                exhaustMap(() => this.http.get(`../rest/resources/${this.resId}/fileInformation`)),
                tap((data: any) => {
                    this.file.subinfos.canConvert = data.information.canConvert;
                    resolve(true);
                }),
                catchError((err: any) => {
                    this.notify.handleSoftErrors(err);
                    return of(false);
                })
            ).subscribe();
        });
    }

    editTemplate(templateId: number) {
        if (this.localStorage.get(`modal_confirmEditTemplate_${this.headerService.user.id}`) !== null) {
            this.launchEditTemplate(templateId);
        } else {
            this.confirmEditTemplate(templateId);
        }
    }

    confirmEditTemplate(templateId: number) {
        let confirmMsg = '';
        if (this.mode === 'attachment') {
            confirmMsg = this.translate.instant('lang.editionAttachmentConfirmFirst') + '<br><br>' + this.translate.instant('lang.editionAttachmentConfirmThird');
        } else {
            confirmMsg = this.translate.instant('lang.editionAttachmentConfirmFirst') + '<br><br>' + this.translate.instant('lang.editionAttachmentConfirmSecond');
        }
        this.dialogRef = this.dialog.open(ConfirmComponent, { panelClass: 'maarch-modal', autoFocus: false, disableClose: true, data: { idModal: 'confirmEditTemplate', title: this.translate.instant('lang.templateEdition'), msg: confirmMsg } });

        this.dialogRef.afterClosed().pipe(
            tap((data: string) => {
                if (data !== 'ok') {
                    this.templateListForm.reset();
                }
            }),
            filter((data: string) => data === 'ok'),
            tap(() => {
                this.launchEditTemplate(templateId);
            }),
            catchError((err: any) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    launchEditTemplate(templateId: number) {
        this.triggerEvent.emit();
        const template = this.listTemplates.filter(templateItem => templateItem.id === templateId)[0];

        this.file.format = template.extension;

        if (this.editor.mode === 'onlyoffice') {

            this.editor.async = false;
            this.editor.options = {
                objectType: 'attachmentCreation',
                objectId: template.id,
                docUrl: 'rest/onlyOffice/mergedFile',
                dataToMerge: this.resourceDatas
            };
            this.editInProgress = true;

        } else if (this.editor.mode === 'collaboraOnline') {
            this.editor.async = false;
            this.editInProgress = true;
            this.editor.options = {
                objectType: this.mode === 'attachment' ? 'attachmentCreation' : 'resourceCreation',
                objectId: template.id,
                dataToMerge: this.resourceDatas
            };
        } else if (this.editor.mode === 'office365sharepoint') {
            this.editor.async = true;
            this.editInProgress = true;
            this.editor.options = {
                objectType: this.mode === 'attachment' ? 'attachmentCreation' : 'resourceCreation',
                objectId: template.id,
                dataToMerge: this.resourceDatas
            };
        } else {
            this.editor.async = true;
            this.editor.options = {
                objectType: 'attachmentCreation',
                objectId: template.id,
                cookie: document.cookie,
                authToken: this.authService.getToken(),
                data: this.resourceDatas,
            };
            this.editInProgress = true;

            this.http.post('../rest/jnlp', this.editor.options).pipe(
                tap((data: any) => {
                    window.location.href = '../rest/jnlp/' + data.generatedJnlp;
                    this.checkLockFile(data.jnlpUniqueId, template.extension);
                })
            ).subscribe();
        }
    }

    editResource() {
        if (!this.functions.empty(this.externalId.signatureBookId)) {
            this.notify.error(this.translate.instant('lang.sendInExternalSignatoryBook'));
        } else if (this.editor.mode === 'java' && this.file.format.toLowerCase() === 'pdf') {
            this.notify.error(this.translate.instant('lang.javaEditDenied') + ' <b>PDF</b> ' + this.translate.instant('lang.javaEditDenied2'));
        } else {
            if (this.mode === 'attachment') {
                this.editAttachment();
            } else {
                this.editMainDocument();
            }
        }
    }

    editAttachment() {
        this.triggerEvent.emit('setData');

        if (this.editor.mode === 'onlyoffice') {
            this.editor.async = false;
            this.editor.options = {
                objectType: 'attachmentModification',
                objectId: this.resId,
                docUrl: 'rest/onlyOffice/mergedFile',
                dataToMerge: this.resourceDatas
            };
            this.editInProgress = true;

        } else if (this.editor.mode === 'collaboraOnline') {
            this.editor.async = false;
            this.editInProgress = true;
            this.editor.options = {
                objectType: 'attachmentModification',
                objectId: this.resId,
                dataToMerge: this.resourceDatas
            };
        } else if (this.editor.mode === 'office365sharepoint') {
            this.editor.async = false;
            this.editInProgress = true;
            this.editor.options = {
                objectType: 'attachmentModification',
                objectId: this.resId,
                dataToMerge: this.resourceDatas
            };
        } else {
            this.editor.async = true;
            this.editor.options = {
                objectType: 'attachmentModification',
                objectId: this.resId,
                cookie: document.cookie,
                authToken: this.authService.getToken(),
                data: this.resourceDatas,
            };
            this.editInProgress = true;

            this.http.post('../rest/jnlp', this.editor.options).pipe(
                tap((data: any) => {
                    window.location.href = '../rest/jnlp/' + data.generatedJnlp;
                    this.checkLockFile(data.jnlpUniqueId, this.file.format);
                })
            ).subscribe();
        }
    }

    editMainDocument() {
        if (this.editor.mode === 'onlyoffice') {
            this.editor.async = false;
            this.editor.options = {
                objectType: 'resourceModification',
                objectId: this.resId,
                docUrl: 'rest/onlyOffice/mergedFile'
            };
            this.editInProgress = true;

        } else if (this.editor.mode === 'collaboraOnline') {
            this.editor.async = false;
            this.editor.options = {
                objectType: 'resourceModification',
                objectId: this.resId,
                dataToMerge: this.resourceDatas
            };
            this.editInProgress = true;
        } else if (this.editor.mode === 'office365sharepoint') {
            this.editor.async = true;
            this.editor.options = {
                objectType: 'resourceModification',
                objectId: this.resId,
                dataToMerge: this.resourceDatas
            };
            this.editInProgress = true;
        } else {
            this.editor.async = true;
            this.editor.options = {
                objectType: 'resourceModification',
                objectId: this.resId,
                cookie: document.cookie,
                authToken: this.authService.getToken()
            };
            this.editInProgress = true;

            this.http.post('../rest/jnlp', this.editor.options).pipe(
                tap((data: any) => {
                    window.location.href = '../rest/jnlp/' + data.generatedJnlp;
                    this.checkLockFile(data.jnlpUniqueId, this.file.format);
                })
            ).subscribe();
        }
    }

    setDatas(resourceDatas: any) {
        this.resourceDatas = resourceDatas;
    }

    checkLockFile(id: string, extension: string) {
        this.intervalLockFile = setInterval(() => {
            this.http.get('../rest/jnlp/lock/' + id)
                .subscribe(async (data: any) => {
                    if (!data.lockFileFound) {
                        this.editInProgress = false;
                        clearInterval(this.intervalLockFile);
                        await this.loadTmpFile(`${data.fileTrunk}.${extension}`);
                        if (this.mode === 'mainDocument' && this.resId !== null) {
                            this.saveMainDocument();
                        }
                    }
                });
        }, 1000);
    }

    cancelTemplateEdition() {
        clearInterval(this.intervalLockFile);
        this.editInProgress = false;
    }

    isEditingTemplate() {
        if (this.editor.mode === 'onlyoffice') {
            return this.onlyofficeViewer !== undefined;
        } else if (this.editor.mode === 'collaboraOnline') {
            return this.collaboraOnlineViewer !== undefined;
        }  else if (this.editor.mode === 'office365sharepoint') {
            return this.officeSharepointViewer !== undefined;
        } else {
            return this.editInProgress;
        }
    }

    loadTemplatesByResId(resId: number, attachType: string) {
        const arrValues: any[] = [];
        let arrTypes: any = [];
        this.listTemplates = [];
        this.http.get('../rest/attachmentsTypes').pipe(
            tap((data: any) => {

                Object.keys(data.attachmentsTypes).forEach(templateType => {
                    arrTypes.push({
                        id: templateType,
                        label: data.attachmentsTypes[templateType].label
                    });
                });
                arrTypes = this.sortPipe.transform(arrTypes, 'label');
                arrTypes.push({
                    id: 'all',
                    label: this.translate.instant('lang.others')
                });

            }),
            exhaustMap(() => this.http.get(`../rest/resources/${resId}/templates?attachmentType=${attachType},all`)),
            tap((data: any) => {
                this.listTemplates = data.templates;

                arrTypes = arrTypes.filter((type: any) => data.templates.map((template: any) => template.attachmentType).indexOf(type.id) > -1);

                arrTypes.forEach((arrType: any) => {
                    arrValues.push({
                        id: arrType.id,
                        label: arrType.label,
                        title: arrType.label,
                        disabled: true,
                        isTitle: true,
                        color: '#135f7f'
                    });
                    data.templates.filter((template: any) => template.attachmentType === arrType.id).forEach((template: any) => {
                        arrValues.push({
                            id: template.id,
                            label: '&nbsp;&nbsp;&nbsp;&nbsp;' + template.label,
                            title: template.exists ? template.label : this.translate.instant('lang.fileDoesNotExists'),
                            extension: template.extension,
                            disabled: !template.exists,
                        });
                    });
                });

                this.listTemplates = arrValues;
            })

        ).subscribe();
    }

    loadTemplates() {
        if (this.listTemplates.length === 0) {
            const arrValues: any[] = [];
            if (this.mode === 'mainDocument') {
                this.http.get('../rest/currentUser/templates?target=indexingFile').pipe(
                    tap((data: any) => {
                        this.listTemplates = data.templates;
                        arrValues.push({
                            id: 'all',
                            label: this.translate.instant('lang.indexation'),
                            title: this.translate.instant('lang.indexation'),
                            disabled: true,
                            isTitle: true,
                            color: '#135f7f'
                        });
                        data.templates.forEach((template: any) => {
                            arrValues.push({
                                id: template.id,
                                label: '&nbsp;&nbsp;&nbsp;&nbsp;' + template.label,
                                title: template.exists ? template.label : this.translate.instant('lang.fileDoesNotExists'),
                                extension: template.extension,
                                disabled: !template.exists,
                            });
                        });
                        this.listTemplates = arrValues;
                    })

                ).subscribe();
            } else {
                let arrTypes: any = [];
                this.http.get('../rest/attachmentsTypes').pipe(
                    tap((data: any) => {
                        arrTypes.push({
                            id: 'all',
                            label: this.translate.instant('lang.others')
                        });
                        Object.keys(data.attachmentsTypes).forEach(templateType => {
                            arrTypes.push({
                                id: templateType,
                                label: data.attachmentsTypes[templateType].label
                            });
                            arrTypes = this.sortPipe.transform(arrTypes, 'label');
                        });
                    }),
                    exhaustMap(() => this.http.get('../rest/currentUser/templates?target=attachments&type=office')),
                    tap((data: any) => {
                        this.listTemplates = data.templates;

                        arrTypes = arrTypes.filter((type: any) => data.templates.map((template: any) => template.attachmentType).indexOf(type.id) > -1);

                        arrTypes.forEach((arrType: any) => {
                            arrValues.push({
                                id: arrType.id,
                                label: arrType.label,
                                title: arrType.label,
                                disabled: true,
                                isTitle: true,
                                color: '#135f7f'
                            });
                            data.templates.filter((template: any) => template.attachmentType === arrType.id).forEach((template: any) => {
                                arrValues.push({
                                    id: template.id,
                                    label: '&nbsp;&nbsp;&nbsp;&nbsp;' + template.label,
                                    title: template.exists ? template.label : this.translate.instant('lang.fileDoesNotExists'),
                                    extension: template.extension,
                                    disabled: !template.exists,
                                });
                            });
                        });

                        this.listTemplates = arrValues;
                    })

                ).subscribe();
            }
        }
    }

    closeEditor() {
        this.templateListForm.reset();
        this.editInProgress = false;
        this.isDocModified = false;
    }

    setEditor() {
        if (this.headerService.user.preferences.documentEdition === 'java') {
            this.editor.mode = 'java';
            this.editor.async = true;
        } else if (this.headerService.user.preferences.documentEdition === 'onlyoffice') {
            this.editor.mode = 'onlyoffice';
            this.editor.async = false;
        } else if (this.headerService.user.preferences.documentEdition === 'collaboraonline') {
            this.editor.mode = 'collaboraOnline';
            this.editor.async = false;
        } else if (this.headerService.user.preferences.documentEdition === 'office365sharepoint') {
            this.editor.mode = 'office365sharepoint';
            this.editor.async = true;
        }
    }

    saveMainDocument() {
        this.loading = true;
        return new Promise((resolve) => {
            this.getFile().pipe(
                map((data: any) => {
                    const formatdatas = {
                        encodedFile: data.content,
                        format: data.format,
                        resId: this.resId
                    };
                    return formatdatas;
                }),
                exhaustMap((data) => this.http.put(`../rest/resources/${this.resId}?onlyDocument=true`, data)),
                tap(() => {
                    this.closeEditor();
                    this.authService.catchEvent().subscribe((res: string) => {
                        if (res === 'login') {
                            this.logoutTrigger = true;
                        }
                    });
                    resolve(true);
                }),
                finalize(() => {
                    if (!this.logoutTrigger) {
                        this.loadRessource(this.resId);
                    }
                    this.loading = false;
                }),
                catchError((err: any) => {
                    this.notify.handleSoftErrors(err);
                    resolve(false);
                    return of(false);
                })
            ).subscribe();
        });
    }

    loadTmpDocument(base64Content: string, format: string) {
        return new Promise((resolve, reject) => {
            this.http.post('../rest/convertedFile/encodedFile', { format: format, encodedFile: base64Content }).pipe(
                tap((data: any) => {
                    this.file = {
                        name: 'maarch',
                        format: format,
                        type: 'application/pdf',
                        contentMode: 'base64',
                        content: base64Content,
                        src: this.base64ToArrayBuffer(data.encodedResource)
                    };
                }),
                // exhaustMap((data) => this.http.post(`../rest/convertedFile/encodedFile`, data.content)),
                catchError((err: any) => {
                    this.notify.handleSoftErrors(err);
                    resolve(false);
                    return of(false);
                })
            ).subscribe();
        });
    }

    saveTmpDocument() {
        this.loading = true;
        return new Promise((resolve) => {
            this.getFile().pipe(
                tap((data: any) => {
                    this.file = {
                        name: 'maarch',
                        format: data.format,
                        type: 'application/pdf',
                        contentMode: 'base64',
                        content: data.content,
                        src: null
                    };
                }),
                exhaustMap((data) => this.http.post('../rest/convertedFile/encodedFile', { format: data.format, encodedFile: data.content })),
                tap((data: any) => {
                    this.file.src = this.base64ToArrayBuffer(data.encodedResource);
                    this.closeEditor();
                    resolve(true);
                }),
                finalize(() => this.loading = false),
                catchError((err: any) => {
                    this.notify.handleSoftErrors(err);
                    resolve(false);
                    return of(false);
                })
            ).subscribe();
        });
    }

    async openResourceVersion(version: number, type: string) {
        this.downloadActions.push(
            {
                id: 'converted',
                icon: 'fas fa-file-pdf',
                label: this.translate.instant('lang.pdfFormat')
            }
        );
        await this.getOriginalFileInfos(version);
        const title = type !== 'PDF' ? this.translate.instant('lang.' + type + '_version') : `${this.translate.instant('lang.version')} ${version}`;

        // TO SHOW ORIGINAL DOC (because autoload signed doc)
        type = type === 'SIGN' ? 'PDF' : type;

        this.http.get(`../rest/resources/${this.resId}/content/${version}?type=${type}`).pipe(
            tap((data: any) => {
                const dialogRef = this.dialog.open(DocumentViewerModalComponent, {
                    autoFocus: false,
                    panelClass: ['maarch-full-height-modal', 'maarch-doc-modal'],
                    data: {
                        title: `${title}`,
                        base64: data.encodedDocument,
                        filename: data.filename,
                        downloadActions: this.downloadActions,
                        isSigned: this.isSigned
                    }
                });

                dialogRef.afterClosed().pipe(
                    tap(() => {
                        this.downloadActions = [];
                    })
                ).subscribe();
            }),
            catchError((err: any) => {
                this.notify.handleSoftErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    unsignMainDocument() {
        this.dialogRef = this.dialog.open(ConfirmComponent, { panelClass: 'maarch-modal', autoFocus: false, disableClose: true, data: { title: this.translate.instant('lang.UNSIGN'), msg: this.translate.instant('lang.confirmAction') } });

        this.dialogRef.afterClosed().pipe(
            filter((data: string) => data === 'ok'),
            exhaustMap(() => this.http.put(`../rest/resources/${this.resId}/unsign`, {})),
            tap(() => {
                this.notify.success(this.translate.instant('lang.documentUnsigned'));
                this.loadRessource(this.resId);
            }),
            catchError((err: any) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    isEditorLoaded() {
        if (this.isEditingTemplate()) {
            return this.isEditingTemplate() && this.isDocModified;
        } else {
            return true;
        }
    }

    openExternalSignatoryBookWorkflow() {
        this.dialog.open(VisaWorkflowModalComponent, {
            panelClass: 'maarch-modal',
            data: {
                id: this.resId,
                type: 'resource',
                title: this.translate.instant(`lang.${this.externalSignatoryBook.signatoryBookEnabled}Workflow`),
                linkedToExternalSignatoryBook: true
            }
        });
    }

    getOriginalFileInfos(version: number) {
        return new Promise((resolve) => {
            Promise.all([this.versionsInformations(version), this.fileInformation()]).then((result: any) => {
                if (result[0] === 'SIGN' && !this.functions.empty(result[1])) {
                    this.http.get(`../rest/resources/${this.resId}/originalContent?mode=base64`).pipe(
                        tap((data: any) => {
                            this.downloadActions.push(
                                {
                                    id: 'original',
                                    icon: 'fas fa-file-word',
                                    label: `${this.translate.instant('lang.format')} ${this.format}`,
                                    fileData: data
                                }
                            );
                            resolve(true);
                        }),
                        catchError((err: any) => {
                            this.notify.handleSoftErrors(err);
                            return of(false);
                        })
                    ).subscribe();
                } else {
                    this.downloadActions = [];
                }
                resolve(true);
            });
        });
    }

    versionsInformations(version: number) {
        return new Promise((resolve) => {
            this.http.get(`../rest/resources/${this.resId}/versionsInformations`).pipe(
                tap((result: any) => {
                    this.status = result.SIGN.find((id: any) => id === version) !== undefined && result.DOC.find((id: any) => id === version) !== undefined ? 'SIGN' : '';
                    resolve(this.status);
                }),
                catchError((err: any) => {
                    this.notify.handleSoftErrors(err);
                    return of(false);
                })
            ).subscribe();
        });
    }

    fileInformation() {
        return new Promise((resolve) => {
            this.http.get(`../rest/resources/${this.resId}/fileInformation`).pipe(
                tap((infos: any) => {
                    this.format = this.allowedExtensionsMailing.indexOf(infos.information.format) > -1 ? infos.information.format : '';
                    resolve(this.format);
                }),
                catchError((err: any) => {
                    this.notify.handleSoftErrors(err);
                    return of(false);
                })
            ).subscribe();
        });
    }

    canSaveModifications(currentMode: string) {
        const commonConditions: boolean = this.isDocModified && this.mode === currentMode && this.editor.mode !== 'office365sharepoint' && !this.loading;
        return currentMode === 'mainDocument' ? commonConditions && this.resId !== null : commonConditions;
    }

    rotateDocument(side: string) {
        if (side === 'left') {
            this.rotation = this.rotation - 90;
        } else if (side === 'right') {
            this.rotation = this.rotation + 90;
        }
    }

    getTitle(): string {
        return !this.externalSignatoryBook.canViewWorkflow() ? this.translate.instant('lang.unavailableForSignatoryBook') : this.translate.instant('lang.' + this.externalSignatoryBook.signatoryBookEnabled + 'Workflow');
    }

    zoomDocument(type: string) {
        if (type === 'in') {
            this.zoom = this.zoom + 0.5;
        } else if (type === 'out' && this.zoom >= 0) {
            this.zoom = this.zoom - 0.5;
        }
    }
}
