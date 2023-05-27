import { Component, OnInit, Inject, ViewChild } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { TranslateService } from '@ngx-translate/core';
import { catchError, tap, finalize, exhaustMap, filter } from 'rxjs/operators';
import { of } from 'rxjs';
import { NotificationService } from '@service/notification/notification.service';
import { MatDialog, MAT_DIALOG_DATA, MatDialogRef } from '@angular/material/dialog';
import { AppService } from '@service/app.service';
import { SortPipe } from '../../../plugins/sorting.pipe';
import { UntypedFormControl, Validators, UntypedFormGroup } from '@angular/forms';
import { DocumentViewerComponent } from '../../viewer/document-viewer.component';
import { PrivilegeService } from '@service/privileges.service';
import { HeaderService } from '@service/header.service';
import { ConfirmComponent } from '../../../plugins/modal/confirm.component';
import { FunctionsService } from '@service/functions.service';

@Component({
    selector: 'app-attachment-page',
    templateUrl: 'attachment-page.component.html',
    styleUrls: [
        'attachment-page.component.scss',
        '../../indexation/indexing-form/indexing-form.component.scss'
    ],
    providers: [SortPipe],
})

export class AttachmentPageComponent implements OnInit {

    @ViewChild('appAttachmentViewer', { static: false }) appAttachmentViewer: DocumentViewerComponent;

    loading: boolean = true;
    sendMassMode: boolean = false;
    sendingData: boolean = false;

    attachmentsTypes: any[] = [];
    attachment: any;

    versions: any[] = [];
    hidePanel: boolean = false;
    newVersion: boolean = false;
    signedByDefault: boolean = false;

    attachFormGroup: UntypedFormGroup = null;

    editMode: boolean = false;

    now: Date = new Date();

    resourceContacts: any = [];

    selectedIndex: number = 1;

    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        @Inject(MAT_DIALOG_DATA) public data: any,
        public dialog: MatDialog,
        public dialogRef: MatDialogRef<AttachmentPageComponent>,
        public appService: AppService,
        private notify: NotificationService,
        private sortPipe: SortPipe,
        public headerService: HeaderService,
        public privilegeService: PrivilegeService,
        public functions: FunctionsService) {
    }

    async ngOnInit(): Promise<void> {
        this.hidePanel = this.data.hidePanel !== undefined ? this.data.hidePanel : false;

        await this.loadAttachmentTypes();
        await this.loadAttachment();

        if (this.sendMassMode) {
            await this.getContacts();
        }

        this.loading = false;
    }

    loadAttachmentTypes() {
        return new Promise((resolve) => {
            this.http.get('../rest/attachmentsTypes').pipe(
                tap((data: any) => {
                    Object.keys(data.attachmentsTypes).forEach(templateType => {
                        this.attachmentsTypes.push({
                            ...data.attachmentsTypes[templateType],
                            id: templateType
                        });
                    });
                    this.attachmentsTypes = this.sortPipe.transform(this.attachmentsTypes, 'label');
                    resolve(true);
                }),
                catchError((err: any) => {
                    this.notify.handleSoftErrors(err);
                    this.dialogRef.close('');
                    return of(false);
                })
            ).subscribe();
        });
    }

    loadAttachment() {
        return new Promise((resolve) => {
            this.http.get(`../rest/attachments/${this.data.resId}`).pipe(
                tap((data: any) => {
                    let contact: any = null;

                    if (!this.functions.empty(this.data.editMode)) {
                        this.editMode = this.data.editMode;
                    } else if ((this.privilegeService.hasCurrentUserPrivilege('manage_attachments') || this.headerService.user.id === data.typist) && data.status !== 'SIGN' && data.status !== 'FRZ') {
                        this.editMode = true;
                    }

                    if (data.type === 'acknowledgement_record_management' || data.type === 'reply_record_management') {
                        this.editMode = false;
                    }

                    if (data.recipientId !== null && data.status !== 'SEND_MASS') {
                        contact = [{
                            id: data.recipientId,
                            type: data.recipientType
                        }];
                    }

                    this.sendMassMode = data.status === 'SEND_MASS';

                    this.attachment = {
                        typist: new UntypedFormControl({ value: data.typist, disabled: true }, [Validators.required]),
                        typistLabel: new UntypedFormControl({ value: data.typistLabel, disabled: true }, [Validators.required]),
                        creationDate: new UntypedFormControl({ value: data.creationDate, disabled: true }, [Validators.required]),
                        modificationDate: new UntypedFormControl({ value: data.modificationDate, disabled: true }),
                        modifiedBy: new UntypedFormControl({ value: data.modifiedBy, disabled: true }),
                        signatory: new UntypedFormControl({ value: data.signatory, disabled: true }),
                        signatoryId: new UntypedFormControl({ value: data.signatoryId, disabled: true }),
                        signDate: new UntypedFormControl({ value: data.signDate, disabled: true }),
                        resId: new UntypedFormControl({ value: this.data.resId, disabled: true }, [Validators.required]),
                        chrono: new UntypedFormControl({ value: data.chrono, disabled: true }),
                        originId: new UntypedFormControl({ value: data.originId, disabled: true }),
                        resIdMaster: new UntypedFormControl({ value: data.resIdMaster, disabled: true }, [Validators.required]),
                        status: new UntypedFormControl({ value: data.status, disabled: true }, [Validators.required]),
                        relation: new UntypedFormControl({ value: data.relation, disabled: true }, [Validators.required]),
                        title: new UntypedFormControl({ value: data.title, disabled: !this.editMode }, [Validators.required]),
                        recipient: new UntypedFormControl({ value: contact, disabled: !this.editMode }),
                        type: new UntypedFormControl({ value: data.type, disabled: !this.editMode }, [Validators.required]),
                        validationDate: new UntypedFormControl({ value: data.validationDate !== null ? new Date(data.validationDate) : null, disabled: !this.editMode }),
                        signedResponse: new UntypedFormControl({ value: data.signedResponse, disabled: false }),
                        encodedFile: new UntypedFormControl({ value: '_CURRENT_FILE', disabled: !this.editMode }, [Validators.required]),
                        format: new UntypedFormControl({ value: data.format, disabled: true }, [Validators.required])
                    };

                    this.versions = data.versions;

                    this.attachmentsTypes = this.attachmentsTypes.filter((item: any) => item.typeId === data.type || item.visible);
                    this.newVersion = this.attachmentsTypes.filter((item: any) => item.typeId === data.type)[0].newVersionDefault;
                    this.signedByDefault = this.attachmentsTypes.filter((item: any) => item.typeId === data.type)[0].signedByDefault;

                    this.attachFormGroup = new UntypedFormGroup(this.attachment);
                    resolve(true);
                }),
                catchError((err: any) => {
                    this.notify.handleSoftErrors(err);
                    this.dialogRef.close('');
                    return of(false);
                })
            ).subscribe();
        });
    }

    getContacts() {
        return new Promise((resolve, reject) => {
            this.http.get(`../rest/resources/${this.attachment.resIdMaster.value}?light=true`).pipe(
                tap(async (data: any) => {
                    if (data.categoryId === 'outgoing') {
                        if (!this.functions.empty(data.recipients) && data.recipients.length > 0) {
                            this.resourceContacts = data.recipients;
                        }
                    } else {
                        if (!this.functions.empty(data.senders) && data.senders.length > 0) {
                            this.resourceContacts = data.senders;
                        }
                    }
                    resolve(true);
                }),
                catchError((err: any) => {
                    this.notify.handleSoftErrors(err);
                    this.dialogRef.close('');
                    return of(false);
                })
            ).subscribe();
        });
    }

    isVersionEnabled() {

        const versionEnabled = this.attachmentsTypes.filter((item: any) => item.typeId === this.attachment.type.value)[0].versionEnabled;
        if (!versionEnabled) {
            this.newVersion = false;
        }
        return versionEnabled;
    }

    createNewVersion(mode: string = 'default') {
        this.sendingData = true;
        this.appAttachmentViewer.getFile().pipe(
            tap((data) => {
                this.attachment.encodedFile.setValue(data.content);
                this.attachment.format.setValue(data.format);
                if (this.functions.empty(this.attachment.encodedFile.value)) {
                    this.notify.error(this.translate.instant('lang.mustEditAttachmentFirst'));
                    this.sendingData = false;
                }
            }),
            filter(() => !this.functions.empty(this.attachment.encodedFile.value)),
            exhaustMap(() => this.http.post('../rest/attachments', this.getAttachmentValues(true, mode))),
            tap(async (data: any) => {
                if (this.sendMassMode && mode === 'mailing') {
                    await this.generateMailling(data.id);
                    this.notify.success(this.translate.instant('lang.attachmentGenerated'));
                } else {
                    this.notify.success(this.translate.instant('lang.newVersionAdded'));
                }
                this.dialogRef.close('success');
            }),
            finalize(() => this.sendingData = false),
            catchError((err: any) => {
                this.notify.handleSoftErrors(err);
                this.dialogRef.close('');
                return of(false);
            })
        ).subscribe();
    }

    updateAttachment(mode: string = 'default') {

        this.sendingData = true;
        this.appAttachmentViewer.getFile().pipe(
            tap((data) => {
                this.attachment.encodedFile.setValue(data.content);
                this.attachment.format.setValue(data.format);
            }),
            exhaustMap(() => this.http.put(`../rest/attachments/${this.attachment.resId.value}`, this.getAttachmentValues(false, mode))),
            tap(async () => {
                if (this.sendMassMode && mode === 'mailing') {
                    await this.generateMailling(this.attachment.resId.value);
                    this.notify.success(this.translate.instant('lang.attachmentGenerated'));
                } else {
                    this.notify.success(this.translate.instant('lang.attachmentUpdated'));
                }
                this.dialogRef.close('success');
            }),
            finalize(() => this.sendingData = false),
            catchError((err: any) => {
                this.notify.handleSoftErrors(err);
                this.dialogRef.close('');
                return of(false);
            })
        ).subscribe();
    }

    generateMailling(resId: number) {
        return new Promise((resolve) => {
            this.http.post(`../rest/attachments/${resId}/mailing`, {}).pipe(
                tap(() => {
                    resolve(true);
                }),
                catchError((err: any) => {
                    this.notify.handleSoftErrors(err);
                    this.dialogRef.close('');
                    return of(false);
                })
            ).subscribe();
        });
    }

    enableForm(state: boolean) {
        Object.keys(this.attachment).forEach(element => {
            if (['status', 'typistLabel', 'creationDate', 'relation', 'modificationDate', 'modifiedBy'].indexOf(element) === -1) {

                if (state) {
                    this.attachment[element].enable();
                } else {
                    this.attachment[element].disable();
                }
            }

        });
    }

    getAttachmentValues(newAttachment: boolean = false, mode: string) {
        const attachmentValues = {};
        Object.keys(this.attachment).forEach(element => {
            if (this.attachment[element] !== undefined && (this.attachment[element].value !== null && this.attachment[element].value !== undefined)) {
                if (element === 'validationDate') {
                    const day = this.attachment[element].value.getDate();
                    const month = this.attachment[element].value.getMonth() + 1;
                    const year = this.attachment[element].value.getFullYear();
                    attachmentValues[element] = ('00' + day).slice(-2) + '-' + ('00' + month).slice(-2) + '-' + year + ' 23:59:59';
                } else if (element === 'recipient') {
                    attachmentValues['recipientId'] = this.attachment[element].value.length > 0 ? this.attachment[element].value[0].id : null;
                    attachmentValues['recipientType'] = this.attachment[element].value.length > 0 ? this.attachment[element].value[0].type : null;
                } else {
                    attachmentValues[element] = this.attachment[element].value;
                }
                if (element === 'encodedFile') {
                    if (this.attachment[element].value === '_CURRENT_FILE') {
                        attachmentValues['encodedFile'] = null;
                    }
                    // attachmentValues['format'] = this.appAttachmentViewer.getFile().format;
                }
                if (mode === 'mailing') {
                    attachmentValues['inMailing'] = true;
                }
            }
        });

        if (newAttachment) {
            attachmentValues['originId'] = this.attachment['originId'].value !== null ? this.attachment['originId'].value : attachmentValues['resId'];

            attachmentValues['relation'] = this.attachment['relation'].value + 1;
            delete attachmentValues['resId'];
        }

        return attachmentValues;
    }

    setDatasViewer(ev: any) {
        const datas: any = {};
        Object.keys(this.attachment).forEach(element => {
            if (['title', 'validationDate', 'effectiveDate'].indexOf(element) > -1) {
                datas['attachment_' + element] = this.attachment[element].value;
            }
        });
        if (ev === 'setData') {
            this.appAttachmentViewer.setDatas(datas);
        } else if (ev === 'cleanFile') {
            this.attachment['encodedFile'].setValue(null);
        } else {
            datas['resId'] = this.attachment['resIdMaster'].value;
            // this.attachment.encodedFile.setValue(this.appAttachmentViewer.getFile().content);
            this.appAttachmentViewer.setDatas(datas);
            // this.setNewVersion();
        }
    }

    getAttachType(attachType: any) {
        this.appAttachmentViewer.loadTemplatesByResId(this.attachment['resIdMaster'].value, attachType);
        this.newVersion = this.attachmentsTypes.filter((item: any) => item.typeId === attachType)[0].newVersionDefault;
    }

    setNewVersion() {
        if (!this.newVersion) {
            const dialogRef = this.dialog.open(ConfirmComponent, { panelClass: 'maarch-modal', autoFocus: false, disableClose: true, data: { title: this.translate.instant('lang.createNewVersion'), msg: this.translate.instant('lang.confirmAction') } });

            dialogRef.afterClosed().pipe(
                filter((data: string) => data === 'ok'),
                tap(() => {
                    this.newVersion = true;
                }),
                catchError((err: any) => {
                    this.notify.handleErrors(err);
                    return of(false);
                })
            ).subscribe();
        }

    }

    deleteSignedVersion() {
        const dialogRef = this.dialog.open(ConfirmComponent, { panelClass: 'maarch-modal', autoFocus: false, disableClose: true, data: { title: this.translate.instant('lang.deleteSignedVersion'), msg: this.translate.instant('lang.confirmAction') } });

        dialogRef.afterClosed().pipe(
            filter((data: string) => data === 'ok'),
            exhaustMap(() => this.http.put(`../rest/attachments/${this.attachment['resId'].value}/unsign`, {})),
            tap(() => {
                this.attachment.status.setValue('A_TRA');
                this.attachment.signedResponse.setValue(null);
                if (this.privilegeService.hasCurrentUserPrivilege('manage_attachments') || this.headerService.user.id === this.attachment['typist'].value) {
                    this.editMode = true;
                    this.enableForm(this.editMode);
                }
                this.notify.success(this.translate.instant('lang.signedVersionDeleted'));
                this.dialogRef.close('success');
            }),
            catchError((err: any) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    isEmptyField(field: any) {

        if (field.value === null) {
            return true;

        } else if (Array.isArray(field.value)) {
            if (field.value.length > 0) {
                return false;
            } else {
                return true;
            }
        } else if (String(field.value) !== '') {
            return false;
        } else {
            return true;
        }
    }

    isEditing() {
        if (this.functions.empty(this.appAttachmentViewer)) {
            return false;
        }

        return this.appAttachmentViewer.isEditorLoaded();
    }

    closeModal() {

        if (this.appAttachmentViewer.isEditingTemplate()) {
            const dialogRef = this.dialog.open(ConfirmComponent, { panelClass: 'maarch-modal', autoFocus: false, disableClose: true, data: { title: this.translate.instant('lang.close'), msg: this.translate.instant('lang.editingDocumentMsg') } });

            dialogRef.afterClosed().pipe(
                filter((data: string) => data === 'ok'),
                tap(() => {
                    this.dialogRef.close();
                }),
                catchError((err: any) => {
                    this.notify.handleErrors(err);
                    return of(false);
                })
            ).subscribe();
        } else {
            this.dialogRef.close();
        }
    }

    getNbContacts() {
        return this.resourceContacts.filter((contact: any) => contact.type === 'contact').length;
    }
}
