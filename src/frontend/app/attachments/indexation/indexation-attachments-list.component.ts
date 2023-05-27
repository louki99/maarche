import { Component, OnInit, Output, EventEmitter } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { TranslateService } from '@ngx-translate/core';
import { NotificationService } from '@service/notification/notification.service';
import { AppService } from '@service/app.service';
import { catchError, filter, tap } from 'rxjs/operators';
import { of } from 'rxjs';
import { SortPipe } from '@plugins/sorting.pipe';
import { UntypedFormControl, Validators } from '@angular/forms';
import { AlertComponent } from '@plugins/modal/alert.component';
import { MatDialog } from '@angular/material/dialog';
import { FunctionsService } from '@service/functions.service';
import { ConfirmComponent } from '@plugins/modal/confirm.component';

@Component({
    selector: 'app-indexation-attachments-list',
    templateUrl: 'indexation-attachments-list.component.html',
    styleUrls: ['indexation-attachments-list.component.scss'],
    providers: [SortPipe]
})
export class IndexationAttachmentsListComponent implements OnInit {

    @Output() getAttachments = new EventEmitter<string>();

    loading: boolean = true;

    resId: number = null;

    attachmentsTypes: any[] = [];
    defaultAttachmentType: string = '';

    attachments: any[] = [];

    allowedExtensions: any[] = [];
    maxFileSize: number = 0;
    maxFileSizeLabel: string = '';

    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        private notify: NotificationService,
        public appService: AppService,
        private sortPipe: SortPipe,
        private dialog: MatDialog,
        public functions: FunctionsService,
    ) { }

    async ngOnInit(): Promise<void> {
        this.getFileUploadConf();
        await this.getAttachmentTypes();
        this.loading = false;
    }

    getAttachmentTypes() {
        return new Promise((resolve) => {
            this.http.get('../rest/attachmentsTypes').pipe(
                tap((data: any) => {
                    Object.keys(data.attachmentsTypes).forEach(templateType => {
                        if (data.attachmentsTypes[templateType].visible) {
                            this.attachmentsTypes.push({
                                ...data.attachmentsTypes[templateType],
                                id: templateType
                            });
                        }
                    });
                    this.attachmentsTypes = this.sortPipe.transform(this.attachmentsTypes, 'label');
                    this.setDefaultAttachment();
                    resolve(true);
                }),
                catchError((err: any) => {
                    this.notify.handleSoftErrors(err);
                    return of(false);
                })
            ).subscribe();
        });
    }

    setDefaultAttachment() {
        if (this.attachmentsTypes.filter((attach: any) => attach.id === 'simple_attachment').length > 0) {
            this.defaultAttachmentType = 'simple_attachment';
        } else {
            this.defaultAttachmentType = this.attachmentsTypes[0].id;
        }
    }

    deleteAttachment(index: number = null) {
        const dialogRef = this.dialog.open(ConfirmComponent, { panelClass: 'maarch-modal', autoFocus: false, disableClose: true, data: { title: this.translate.instant('lang.delete'), msg: this.translate.instant('lang.confirmAction') } });

        dialogRef.afterClosed().pipe(
            filter((data: string) => data === 'ok'),
            tap(() => {
                if (index === null) {
                    this.attachments = [];
                } else {
                    this.attachments.splice(index, 1);
                }
            })
        ).subscribe();
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

    async uploadTrigger(fileInput: any) {
        if (fileInput.target.files && fileInput.target.files[0] && this.isExtensionAllowed(fileInput.target.files)) {
            for (let index = 0; index < fileInput.target.files.length; index++) {
                await this.uploadFile(fileInput.target.files[index]);
            }
        }
    }

    uploadFile(file: any) {
        return new Promise((resolve) => {
            const reader = new FileReader();
            reader.readAsArrayBuffer(file);

            reader.onload = (value: any) => {
                file.content = this.getBase64Document(value.target.result);
                file.format = file.name.split('.').pop();
                this.attachments.push({
                    subject: new UntypedFormControl(file.name.substr(0, file.name.lastIndexOf('.')), [Validators.required]),
                    type: new UntypedFormControl(this.defaultAttachmentType, [Validators.required]),
                    file : file
                });
                resolve(true);
            };
        });
    }

    isExtensionAllowed(files: any[]) {
        for (let it = 0; it < files.length; it++) {
            const file = files[it];
            const fileExtension = '.' + file.name.toLowerCase().split('.').pop();
            const allowedExtensions = this.allowedExtensions;

            if (allowedExtensions.filter(ext => (ext.mimeType === file.type || (this.functions.empty(ext.mimeType) && this.functions.empty(file.type))) && ext.extension === fileExtension).length === 0) {
                this.dialog.open(AlertComponent, { panelClass: 'maarch-modal', autoFocus: false, disableClose: true, data: { title: this.translate.instant('lang.notAllowedExtension') + ' !', msg: this.translate.instant('lang.file') + ' : <b>' + file.name + '</b>, ' + this.translate.instant('lang.type') + ' : <b>' + file.type + '</b><br/><br/><u>' + this.translate.instant('lang.allowedExtensions') + '</u> : <br/>' + allowedExtensions.map(ext => ext.extension).filter((elem: any, index: any, self: any) => index === self.indexOf(elem)).join(', ') } });
                return false;
            } else if (file.size > this.maxFileSize && this.maxFileSize > 0) {
                this.dialog.open(AlertComponent, { panelClass: 'maarch-modal', autoFocus: false, disableClose: true, data: { title: this.translate.instant('lang.maxFileSizeReached') + ' ! ', msg: this.translate.instant('lang.maxFileSize') + ' : ' + this.maxFileSizeLabel } });
                return false;
            }
        }
        return true;
    }

    getFileUploadConf() {
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

            }),
            catchError((err: any) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    getBase64Document(buffer: ArrayBuffer) {
        const TYPED_ARRAY = new Uint8Array(buffer);
        const STRING_CHAR = TYPED_ARRAY.reduce((data, byte) => data + String.fromCharCode(byte), '');

        return btoa(STRING_CHAR);
    }

    getCountAttachments() {
        return this.attachments.length;
    }

    isValid() {
        let state = true;
        this.attachments.forEach(element => {
            if (!element.subject.valid || !element.type.valid) {
                state = false;
            }
        });
        return state;
    }

    saveAttachments(resId: number) {
        this.resId = resId;
        this.attachments.forEach(attachment => {
            this.http.post('../rest/attachments', this.formatAttachment(attachment)).pipe(
                catchError((err: any) => {
                    this.notify.handleSoftErrors(err);
                    return of(false);
                })
            ).subscribe();
        });
    }

    formatAttachment(attachment: any) {
        return {
            resIdMaster: this.resId,
            title: attachment.subject.value,
            encodedFile : attachment.file.content,
            format:	attachment.file.format,
            status: 'A_TRA',
            type: attachment.type.value
        };
    }
}
