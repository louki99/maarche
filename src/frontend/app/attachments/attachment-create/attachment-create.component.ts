import { Component, OnInit, Input, Inject, ViewChildren, QueryList, AfterViewInit } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { TranslateService } from '@ngx-translate/core';
import { catchError, tap, filter, distinctUntilChanged, take } from 'rxjs/operators';
import { of } from 'rxjs';
import { NotificationService } from '@service/notification/notification.service';
import { MatDialog, MAT_DIALOG_DATA, MatDialogRef } from '@angular/material/dialog';
import { AppService } from '@service/app.service';
import { DocumentViewerComponent } from '../../viewer/document-viewer.component';
import { SortPipe } from '../../../plugins/sorting.pipe';
import { UntypedFormControl, UntypedFormGroup, Validators } from '@angular/forms';
import { ConfirmComponent } from '../../../plugins/modal/confirm.component';
import { FunctionsService } from '@service/functions.service';
import { ContactService } from '@service/contact.service';
import { ContactAutocompleteComponent } from '../../contact/autocomplete/contact-autocomplete.component';

@Component({
    templateUrl: 'attachment-create.component.html',
    styleUrls: [
        'attachment-create.component.scss',
        '../../indexation/indexing-form/indexing-form.component.scss'
    ],
    providers: [SortPipe, ContactService],
})

export class AttachmentCreateComponent implements OnInit {

    @Input() resId: number = null;

    @ViewChildren('appDocumentViewer') appDocumentViewer: QueryList<DocumentViewerComponent>;
    @ViewChildren('contactAutocomplete') contactAutocomplete: ContactAutocompleteComponent;

    loading: boolean = true;

    sendMassMode: boolean = false;

    sendingData: boolean = false;

    attachmentsTypes: any[] = [];

    creationMode: boolean = true;

    attachFormGroup: UntypedFormGroup[] = [];

    attachments: any[] = [];

    now: Date = new Date();

    // To catch previous index to update file
    asyncIndexTab: number = 0;

    indexTab: number = 0;

    resourceSubject: string = '';
    resourceContacts: any[] = [];

    selectedContact = new UntypedFormControl();

    loadingContact: boolean = false;

    defaultType: any = null;

    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        @Inject(MAT_DIALOG_DATA) public data: any,
        public dialogRef: MatDialogRef<AttachmentCreateComponent>,
        public appService: AppService,
        private notify: NotificationService,
        private sortPipe: SortPipe,
        public dialog: MatDialog,
        public functions: FunctionsService,
        private contactService: ContactService) {
    }

    async ngOnInit(): Promise<void> {

        await this.loadAttachmentTypes();

        await this.loadResource();

        this.loading = false;

        setTimeout(() => {
            if (this.sendMassMode) {
                this.appDocumentViewer.toArray()[0].setDatas({inMailing: true});
            } else {
                this.appDocumentViewer.toArray()[0].setDatas({inMailing: false});
            }
        }, 0);
    }

    loadAttachmentTypes() {
        return new Promise((resolve) => {
            this.http.get('../rest/attachmentsTypes').pipe(
                tap((data: any) => {
                    Object.keys(data.attachmentsTypes).forEach(templateType => {
                        if (data.attachmentsTypes[templateType].visible) {
                            if (templateType === 'response_project' || this.defaultType === null) {
                                this.defaultType = templateType;
                            }
                            this.attachmentsTypes.push({
                                ...data.attachmentsTypes[templateType],
                                id: templateType
                            });
                        }
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

    loadResource() {
        return new Promise((resolve, reject) => {
            this.http.get(`../rest/resources/${this.data.resIdMaster}?light=true`).pipe(
                tap(async (data: any) => {
                    let contact: any = '';
                    if (data.categoryId === 'outgoing') {
                        if (!this.functions.empty(data.recipients) && data.recipients.length > 0) {
                            await this.getCorrespondents('recipients');
                            contact = !this.functions.empty(this.resourceContacts) ? [{ id: this.resourceContacts[0].id, type: this.resourceContacts[0].type }] : '';
                        }
                    } else {
                        if (!this.functions.empty(data.senders) && data.senders.length > 0) {
                            await this.getCorrespondents('senders');
                            contact = !this.functions.empty(this.resourceContacts) ? [{ id: this.resourceContacts[0].id, type: this.resourceContacts[0].type }] : '';
                        }
                    }

                    this.resourceSubject = data.subject;
                    this.attachments.push({
                        title: new UntypedFormControl({ value: data.subject, disabled: false }, [Validators.required]),
                        recipient: new UntypedFormControl({ value: contact, disabled: false }),
                        type: new UntypedFormControl({ value: this.defaultType, disabled: false }, [Validators.required]),
                        validationDate: new UntypedFormControl({ value: '', disabled: false }),
                        format: new UntypedFormControl({ value: '', disabled: false }, [Validators.required]),
                        encodedFile: new UntypedFormControl({ value: '', disabled: false }, [Validators.required])
                    });
                    setTimeout(() => {
                        this.getAttachType(this.defaultType, 0);
                    }, 800);

                    this.attachFormGroup.push(new UntypedFormGroup(this.attachments[0]));

                    if (this.canSendMass()) {
                        this.toggleSendMass();
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

    async getCorrespondents(type) {
        this.resourceContacts = [];
        return new Promise((resolve, reject) => {
            this.http.get(`../rest/resources/${this.data.resIdMaster}/contacts?type=${type}`).pipe(
                tap((correspondents: any) => {
                    correspondents = correspondents.contacts.filter((contact: any) => contact.type !== 'entity').map((item: any) => ({
                        id: item.id,
                        type: item.type,
                        label: item.type === 'contact' ? this.contactService.formatContact(item) : `${item.firstname} ${item.lastname}`
                    }));
                    correspondents.forEach((element: any) => {
                        this.resourceContacts.push(element);
                    });
                    this.resourceContacts = this.sortPipe.transform(this.resourceContacts, 'label');
                    resolve(true);
                }),
                catchError((err: any) => {
                    this.notify.handleSoftErrors(err);
                    resolve(false);
                    return of(false);
                })
            ).subscribe();
        });
    }

    async getContacts(contacts: any) {
        this.resourceContacts = [];
        await Promise.all(contacts.map(async (elem: any) => {
            await this.getContact(elem.id, elem.type);
        }));

        this.resourceContacts = this.sortPipe.transform(this.resourceContacts, 'label');
    }

    selectContact(contact: any) {

        this.loadingContact = true;
        const contactChosen = JSON.parse(JSON.stringify(this.resourceContacts.filter(resContact => resContact.id === contact.id && resContact.type === contact.type)[0]));

        this.attachments[this.indexTab].recipient.setValue([contactChosen]);

        setTimeout(() => {
            this.loadingContact = false;
        }, 0);

        this.selectedContact.reset();
    }

    getContact(contactId: number, type: string) {
        return new Promise((resolve, reject) => {
            if (type === 'contact') {
                this.http.get('../rest/contacts/' + contactId).pipe(
                    tap((data: any) => {
                        this.resourceContacts.push({
                            id: data.id,
                            type: 'contact',
                            label: this.contactService.formatContact(data)
                        });
                        resolve(true);
                    }),
                    catchError((err: any) => {
                        this.notify.handleSoftErrors(err);
                        resolve(false);
                        return of(false);
                    })
                ).subscribe();
            } else if (type === 'user') {
                this.http.get('../rest/users/' + contactId).pipe(
                    tap((data: any) => {
                        this.resourceContacts.push({
                            id: data.id,
                            type: 'user',
                            label: `${data.firstname} ${data.lastname}`
                        });
                        resolve(true);
                    }),
                    catchError((err: any) => {
                        this.notify.handleSoftErrors(err);
                        resolve(false);
                        return of(false);
                    })
                ).subscribe();
            } else if (type === 'entity') {
                this.http.get('../rest/entities/' + contactId).pipe(
                    tap((data: any) => {
                        this.resourceContacts.push({
                            id: data.id,
                            type: 'entity',
                            label: data.entity_label
                        });
                        resolve(true);
                    }),
                    catchError((err: any) => {
                        this.notify.handleSoftErrors(err);
                        resolve(false);
                        return of(false);
                    })
                ).subscribe();
            }
        });
    }

    selectAttachType(attachment: any, type: any) {
        attachment.type = type.id;
    }

    formatAttachments() {
        const formattedAttachments: any[] = [];
        this.attachments.forEach((element, index: number) => {
            formattedAttachments.push({
                resIdMaster: this.data.resIdMaster,
                type: element.type.value,
                title: element.title.value,
                recipientId: element.recipient.value.length > 0 ? element.recipient.value[0].id : null,
                recipientType: element.recipient.value.length > 0 ? element.recipient.value[0].type : null,
                validationDate: element.validationDate.value !== '' ? element.validationDate.value : null,
                encodedFile: element.encodedFile.value,
                format: element.format.value
            });
        });

        return formattedAttachments;
    }

    onSubmit(mode: string = 'default') {
        this.appDocumentViewer.toArray()[this.indexTab].getFile().pipe(
            distinctUntilChanged(),
            tap((data) => {
                this.attachments[this.indexTab].encodedFile.setValue(data.content);
                this.attachments[this.indexTab].format.setValue(data.format);
            }),
            tap(async () => {
                if (this.isValid()) {
                    let resId: any = null;
                    this.sendingData = true;
                    const attach = this.formatAttachments();

                    for (const attachment of attach) {
                        resId = await this.saveAttachment(attachment);
                    }

                    if (this.sendMassMode && resId !== null && mode === 'mailing') {
                        await this.generateMailling(resId);
                    }

                    this.sendingData = false;
                    this.notify.success(this.translate.instant('lang.attachmentAdded'));
                    this.dialogRef.close('success');

                } else {
                    this.sendingData = false;
                    this.notify.error(this.translate.instant('lang.mustCompleteAllAttachments'));
                }
            })
        ).subscribe();
    }

    saveAttachment(attachment: any) {
        attachment.status = this.sendMassMode ? 'SEND_MASS' : 'A_TRA';
        if (this.sendMassMode) {
            attachment.recipientId = null;
            attachment.recipientType = null;
        }

        return new Promise((resolve, reject) => {
            this.http.post('../rest/attachments', attachment).pipe(
                tap((data: any) => {
                    resolve(data.id);
                }),
                catchError((err: any) => {
                    this.notify.handleSoftErrors(err);
                    this.dialogRef.close('');
                    return of(false);
                })
            ).subscribe();
        });
    }

    generateMailling(resId: number) {
        return new Promise((resolve, reject) => {
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

    isValid() {
        let state = true;
        this.attachFormGroup.forEach(formgroup => {
            Object.keys(formgroup.controls).forEach(key => {
                formgroup.controls[key].markAsTouched();
            });
            if (formgroup.status === 'INVALID') {
                state = false;
            }

        });
        return state;
    }

    isPjValid(index: number) {
        let state = true;
        if (this.attachFormGroup[index].status === 'INVALID') {
            state = false;
        }
        return state;
    }

    isDocLoading() {
        let state = false;
        this.appDocumentViewer.toArray().forEach((app, index: number) => {
            if (app.isEditingTemplate()) {
                state = true;
            }
        });
        return state;
    }

    canValidatePj() {
        let state = false;
        this.appDocumentViewer.toArray().forEach((app, index: number) => {
            if (app.isEditingTemplate() && app.editor.async) {
                state = true;
            }
        });
        return state;
    }

    setDatasViewer(ev: any, i: number) {
        const datas: any = {};
        Object.keys(this.attachments[i]).forEach(element => {
            if (['title', 'validationDate', 'recipient'].indexOf(element) > -1) {
                if (element === 'recipient' && this.attachments[i][element].value.length > 0) {
                    datas['recipientId'] = this.attachments[i][element].value[0].id;
                    datas['recipientType'] = this.attachments[i][element].value[0].type;
                } else {
                    datas['attachment_' + element] = this.attachments[i][element].value;
                }
            }
        });
        let trKey = 1;
        this.attachments.forEach((attachment) => {
            if (attachment.type.value == 'transmission') {
                datas['transmissionRecipientId' + trKey] = attachment.recipient.value[0].id;
                datas['transmissionRecipientType' + trKey] = attachment.recipient.value[0].type;
                trKey++;
            }
        });
        datas['resId'] = this.data.resIdMaster;
        if (this.sendMassMode) {
            datas['inMailing'] = true;
        }
        this.appDocumentViewer.toArray()[i].setDatas(datas);

        if (ev === 'uploadFile') {
            const filename = this.appDocumentViewer.toArray()[i].file.name;
            this.attachments[i].title.setValue(filename.substr(0, filename.lastIndexOf('.')));
        }
    }

    newPj() {
        this.attachments.push({
            title: new UntypedFormControl({ value: this.resourceSubject, disabled: false }, [Validators.required]),
            recipient: new UntypedFormControl({ value: !this.functions.empty(this.resourceContacts[this.attachments.length]) ? [{ id: this.resourceContacts[this.attachments.length].id, type: this.resourceContacts[this.attachments.length].type }] : null, disabled: false }),
            type: new UntypedFormControl({ value: 'response_project', disabled: false }, [Validators.required]),
            validationDate: new UntypedFormControl({ value: null, disabled: false }),
            encodedFile: new UntypedFormControl({ value: '', disabled: false }, [Validators.required]),
            format: new UntypedFormControl({ value: '', disabled: false }, [Validators.required])
        });
        this.attachFormGroup.push(new UntypedFormGroup(this.attachments[this.attachments.length - 1]));
        setTimeout(() => {
            this.getAttachType(this.defaultType, this.attachments.length - 1);
        }, 250);
    }

    updateFile(index: number) {
        if (this.functions.empty(this.attachments[this.asyncIndexTab].encodedFile.value)) {
            this.appDocumentViewer.toArray()[this.asyncIndexTab].getFile().pipe(
                take(1),
                tap((data) => {
                    this.attachments[this.asyncIndexTab].encodedFile.setValue(data.content);
                    this.attachments[this.asyncIndexTab].format.setValue(data.format);
                    this.asyncIndexTab = index;
                }),
            ).subscribe();
        } else {
            this.asyncIndexTab = index;
        }
    }

    removePj(i: number) {
        const dialogRef = this.dialog.open(ConfirmComponent, { panelClass: 'maarch-modal', autoFocus: false, disableClose: true, data: { title: this.translate.instant('lang.delete') + ' : ' + this.translate.instant('lang.attachmentShort') + ' n°' + (i + 1), msg: this.translate.instant('lang.confirmAction') } });

        dialogRef.afterClosed().pipe(
            filter((data: string) => data === 'ok'),
            tap(() => {
                const attachLength: number = this.attachments.length - 1;
                this.attachments.splice(i, 1);
                this.attachFormGroup.splice(i, 1);
                if (i === attachLength || this.asyncIndexTab === attachLength) {
                    this.indexTab = this.attachments.length - 1;
                } else if (i === this.asyncIndexTab) {
                    this.indexTab = i;
                }
                this.asyncIndexTab = this.indexTab;
            }),
            catchError((err: any) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();

    }

    getAttachType(attachType: any, i: number) {
        this.indexTab = i;
        this.asyncIndexTab = this.indexTab;
        this.appDocumentViewer.toArray()[i].loadTemplatesByResId(this.data.resIdMaster, attachType);
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

    toggleSendMass() {
        if (this.sendMassMode) {
            this.sendMassMode = !this.sendMassMode;
            this.selectedContact.enable();
            this.appDocumentViewer?.toArray()[0]?.setDatas({inMailing: false});
        } else {
            if (this.attachments.length === 1) {
                this.sendMassMode = !this.sendMassMode;
                this.selectedContact.disable();
                this.appDocumentViewer?.toArray()[0]?.setDatas({inMailing: true});
                if (!this.functions.empty(this.appDocumentViewer?.toArray()[0]?.file.content)) {
                    if (!this.appDocumentViewer?.toArray()[0]?.isExtensionAllowed(this.appDocumentViewer?.toArray()[0]?.file)) {
                        this.appDocumentViewer?.toArray()[0]?.cleanFile(false);
                    }
                }
            } else {
                this.notify.error(this.translate.instant('lang.mustDeleteOtherTabsBeforeSendMassMode'));
            }
        }
    }

    canSendMass() {
        return this.resourceContacts.filter((contact: any) => contact.type === 'contact').length > 1;
    }

    getNbContacts() {
        return this.resourceContacts.filter((contact: any) => contact.type === 'contact').length;
    }
}
