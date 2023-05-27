import { Component, OnInit, Inject, ElementRef, ViewChild } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { TranslateService } from '@ngx-translate/core';
import { NotificationService } from '@service/notification/notification.service';
import { MatDialog, MAT_DIALOG_DATA, MatDialogRef } from '@angular/material/dialog';
import { switchMap, catchError, filter, exhaustMap, tap, debounceTime, distinctUntilChanged, finalize, map } from 'rxjs/operators';
import { UntypedFormControl } from '@angular/forms';
import { FunctionsService } from '@service/functions.service';
import { ContactService } from '@service/contact.service';
import { ConfirmComponent } from '@plugins/modal/confirm.component';
import { PrivilegeService } from '@service/privileges.service';
import { HeaderService } from '@service/header.service';
import { StripTagsPipe, ReversePipe } from 'ngx-pipes';
import { Observable, of } from 'rxjs';
import { environment } from '../../../environments/environment';
import { SplitLoginPwdPipe } from '@plugins/splitLoginPwd.pipe';

@Component({
    selector: 'app-sent-numeric-package-page',
    templateUrl: './sent-numeric-package-page.component.html',
    styleUrls: ['./sent-numeric-package-page.component.scss'],
    providers: [ContactService, StripTagsPipe, ReversePipe, SplitLoginPwdPipe],
})
export class SentNumericPackagePageComponent implements OnInit {

    @ViewChild('recipientsInput', { static: true }) recipientsInput: ElementRef<HTMLInputElement>;

    loading: boolean = true;

    availableEmailModels: any[] = [];
    availableSignEmailModels: any[] = [];

    resourceData: any = null;
    availableSenders: any[] = [];
    currentSender: any = {};

    recipients: any[] = [];

    recipientsCtrl: UntypedFormControl = new UntypedFormControl();

    emailSignListForm = new UntypedFormControl();
    templateEmailListForm = new UntypedFormControl();

    filteredEmails: Observable<string[]>;

    numericPackageCreatorId: number = null;
    numericPackageStatus: string = 'WAITING';
    numericPackageCurrentAttachTool: string = '';
    numericPackageAttachTool: any = {
        document: {
            icon: 'fa fa-file',
            title: this.translate.instant('lang.attachMainDocument'),
            list: []
        },
        notes: {
            icon: 'fas fa-pen-square',
            title: this.translate.instant('lang.attachNote'),
            list: []
        },
        attachments: {
            icon: 'fa fa-paperclip',
            title: this.translate.instant('lang.attachAttachment'),
            list: []
        },
    };
    numericPackageAttach: any = [];

    numericPackage: any = {
        mainExchangeDoc: null,
        'object': '',
        'contacts': [],
        'joinFile': [],
        'joinAttachment': [],
        'notes': [],
        'content': '',
        'senderEmail': null
    };

    reference: string = null;
    messageReview: any[] = [];

    maarch2maarchUrl: string = this.functions.getDocBaseUrl() + '/guat/guat_exploitation/maarch2maarch.html';

    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        private notify: NotificationService,
        @Inject(MAT_DIALOG_DATA) public data: any,
        public dialog: MatDialog,
        public dialogRef: MatDialogRef<SentNumericPackagePageComponent>,
        public functions: FunctionsService,
        private contactService: ContactService,
        public privilegeService: PrivilegeService,
        public headerService: HeaderService,
        private stringPipe: StripTagsPipe,
        private reversePipe: ReversePipe,
        private splitLoginPwd: SplitLoginPwdPipe
    ) { }

    async ngOnInit(): Promise<void> {

        await this.getAttachElements();

        if (this.data.emailId) {
            await this.getNumericPackageData(this.data.emailId);
        }

        if (this.canManageMail()) {
            this.initEmailModelsList();
            this.initM2MList();
            this.initSignEmailModelsList();

            await this.getResourceData();
            await this.getM2MSenders();

            this.setDefaultInfo();
        }
        this.loading = false;
    }

    isBadEmailFormat(email: string) {
        const regex = /^[a-zA-Z0-9_.+-]+@[a-zA-Z0-9-]+\.[a-zA-Z0-9-.]+$/g;

        return email.trim().match(regex) === null;
    }

    closeModal(state: string = '') {
        this.dialogRef.close(state);
    }

    addRecipient(item: any) {
        this.recipients.push(item);
        this.recipientsInput.nativeElement.value = '';
        this.recipientsCtrl.setValue('');
    }

    mergeEmailTemplate(templateId: any) {

        this.templateEmailListForm.reset();

        this.http.post(`../rest/templates/${templateId}/mergeEmail`, { data: { resId: this.data.resId } }).pipe(
            tap((data: any) => {
                const textArea = document.createElement('textarea');
                textArea.innerHTML = data.mergedDocument;
                this.numericPackage.content += this.stringPipe.transform(textArea.value);
            }),
            catchError((err) => {
                this.notify.handleSoftErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    mergeSignEmailTemplate(template: any) {

        this.emailSignListForm.reset();

        let route = '../rest/currentUser/emailSignatures/';
        if (template.public) {
            route = '../rest/currentUser/globalEmailSignatures/';
        }

        this.http.get(`${route}${template.id}`).pipe(
            tap((data: any) => {
                const textArea = document.createElement('textarea');
                textArea.innerHTML = data.emailSignature.content;
                this.numericPackage.content += this.stringPipe.transform(textArea.value);
            }),
            catchError((err) => {
                this.notify.handleSoftErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    remove(item: any, type: string): void {
        if (this.canManageMail()) {
            const index = this[type].indexOf(item);

            if (index >= 0) {
                this[type].splice(index, 1);
            }
        }
    }

    getNumericPackageData(emailId: number) {
        return new Promise((resolve) => {
            this.http.get(`../rest/messageExchanges/${emailId}`).pipe(
                map((data: any) => data.messageExchange),
                tap((data: any) => {
                    this.numericPackageCreatorId = data.userId;

                    data.recipient.communicationMeans = data.communicationType;
                    this.recipients = [data.recipient];

                    this.currentSender.label = data.sender;
                    this.numericPackage.object = data.object;
                    this.numericPackageStatus = data.status.toUpperCase();
                    this.numericPackage.content = data.body;
                    this.reference = data.reference;
                    this.messageReview = data.messageReview.map((item: any) => ({
                        date: this.functions.formatFrenchDateToObjectDate(item.substring(1, 19), '/'),
                        content: item.substring(21),
                    }));
                    this.messageReview = this.reversePipe.transform(this.messageReview);

                    if (data.disposition.tablename === 'res_letterbox') {
                        this.numericPackage.mainExchangeDoc = {
                            ...this.numericPackageAttachTool['document'].list[0],
                            typeLabel: this.translate.instant('lang.mainDocument'),
                            type: 'document'
                        };

                        this.numericPackageAttach = this.numericPackageAttach.concat(this.numericPackageAttachTool['attachments'].list.filter((item: any) => data.attachments.indexOf(item.id.toString()) > -1));
                    } else {
                        this.numericPackage.mainExchangeDoc = {
                            ...this.numericPackageAttachTool['attachments'].list.filter((item: any) => item.id == data.disposition.res_id)[0],
                            type: 'attachments'
                        };
                        this.numericPackageAttach = this.numericPackageAttach.concat(this.numericPackageAttachTool['attachments'].list.filter((item: any) => data.attachments.indexOf(item.id.toString()) > -1 && item.id != data.disposition.res_id));

                    }

                    if (data.resMasterAttached && data.disposition.tablename !== 'res_letterbox') {
                        this.numericPackageAttach.push({
                            ...this.numericPackageAttachTool['document'].list[0],
                            typeLabel: this.translate.instant('lang.mainDocument'),
                            type: 'document'
                        });
                    }

                    this.numericPackageAttach = this.numericPackageAttach.concat(this.numericPackageAttachTool['notes'].list.filter((item: any) => data.notes.indexOf(item.id.toString()) > -1));

                    resolve(true);
                }),
                catchError((err) => {
                    this.notify.handleSoftErrors(err);
                    resolve(false);
                    return of(false);
                })
            ).subscribe();
        });
    }

    getResourceData() {
        return new Promise((resolve) => {
            this.http.get(`../rest/resources/${this.data.resId}?light=true`).pipe(
                tap((data: any) => {
                    this.resourceData = data;
                    this.numericPackage.object = this.resourceData.subject;
                    resolve(true);
                }),
                catchError((err) => {
                    this.notify.handleSoftErrors(err);
                    resolve(false);
                    return of(false);
                })
            ).subscribe();
        });
    }

    setDefaultInfo() {
        if (!this.functions.empty(this.resourceData.senders)) {
            this.resourceData.senders.forEach((sender: any) => {
                if (sender.type === 'contact') {
                    this.setSender(sender.id);
                }
            });
        }
    }

    setSender(id: number) {
        this.http.get(`../rest/contacts/${id}`).pipe(
            tap((data: any) => {
                if (!this.functions.empty(data.communicationMeans) && !this.functions.empty(data.externalId['m2m'])) {
                    this.recipients.push(
                        {
                            id: id,
                            label: this.contactService.formatContact(data),
                            email: data.email,
                            m2m: data.externalId['m2m'],
                            communicationMeans: data.communicationMeans
                        }
                    );
                }
            }),
            catchError((err) => {
                this.notify.handleSoftErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    getM2MSenders() {
        return new Promise((resolve) => {
            this.http.get('../rest/messageExchangesInitialization').pipe(
                tap((data: any) => {
                    this.availableSenders = data.entities;
                    this.currentSender = this.availableSenders[0];
                    resolve(true);
                }),
                catchError((err) => {
                    this.notify.handleSoftErrors(err);
                    resolve(false);
                    return of(false);
                })
            ).subscribe();
        });
    }

    getAttachElements() {
        return new Promise((resolve) => {
            this.http.get(`../rest/resources/${this.data.resId}/emailsInitialization`).pipe(
                tap((data: any) => {
                    Object.keys(data).forEach(element => {
                        if (element === 'resource') {
                            this.numericPackageAttachTool.document.list = [];
                            if (!this.functions.empty(data[element])) {
                                this.numericPackageAttachTool.document.list = [data[element]];
                            }
                        } else {
                            this.numericPackageAttachTool[element].list = data[element].map((item: any) => ({
                                ...item,
                                original: item.original !== undefined ? item.original : true,
                                title: item.chrono !== undefined ? `${item.chrono} - ${item.label} (${item.typeLabel})` : `${item.label} (${item.typeLabel})`
                            }));
                        }
                    });
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

    initM2MList() {
        this.recipientsCtrl.valueChanges.pipe(
            filter(value => value !== null),
            debounceTime(300),
            tap((value) => {
                if (value.length === 0) {
                    this.filteredEmails = of([]);
                }
            }),
            filter(value => value.length > 2),
            distinctUntilChanged(),
            switchMap(data => this.http.get('../rest/autocomplete/contacts/m2m', { params: { 'search': data } })),
            tap((data: any) => {
                data = data.map((contact: any) => ({
                    ...contact,
                    address: this.contactService.formatContact(contact),
                    label: this.contactService.formatContact(contact)
                }));
                this.filteredEmails = of(data);
            }),
            catchError((err) => {
                this.notify.handleSoftErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    initEmailModelsList() {
        this.http.get(`../rest/resources/${this.data.resId}/emailTemplates`).pipe(
            tap((data: any) => {
                this.availableEmailModels = data.templates;
            }),
            catchError((err) => {
                this.notify.handleSoftErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    initSignEmailModelsList() {
        this.http.get('../rest/currentUser/emailSignaturesList').pipe(
            tap((data: any) => {
                this.availableSignEmailModels = data.emailSignatures;
            }),
            catchError((err) => {
                this.notify.handleSoftErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    resetAutocomplete() {
        this.filteredEmails = of([]);
    }

    onSubmit() {
        this.loading = true;
        this.numericPackageStatus = 'WAITING';
        if (this.data.emailId === null) {
            if (this.numericPackage.object === '') {
                const dialogRef = this.dialog.open(ConfirmComponent, { panelClass: 'maarch-modal', autoFocus: false, disableClose: true, data: { title: this.translate.instant('lang.confirm'), msg: this.translate.instant('lang.warnEmptySubject') } });

                dialogRef.afterClosed().pipe(
                    filter((data: string) => data === 'ok'),
                    tap(() => {
                        this.createEmail(true);
                    })
                ).subscribe();
            } else {
                this.createEmail(true);
            }

        } else {
            this.updateEmail(true);
        }
    }

    createEmail(closeModal: boolean = true) {
        this.http.post(`../rest/resources/${this.data.resId}/messageExchange`, this.formatNumericPackage()).pipe(
            tap(() => {
                this.notify.success(this.translate.instant('lang.numericPackageSent'));

                this.closeModal('success');
            }),
            finalize(() => this.loading = false),
            catchError((err) => {
                this.notify.handleSoftErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    deleteEmail() {
        const dialogRef = this.dialog.open(ConfirmComponent, { panelClass: 'maarch-modal', autoFocus: false, disableClose: true, data: { title: this.translate.instant('lang.delete'), msg: this.translate.instant('lang.confirmAction') } });

        dialogRef.afterClosed().pipe(
            filter((data: string) => data === 'ok'),
            exhaustMap(() => this.http.delete(`../rest/messageExchanges/${this.data.emailId}`)),
            tap(() => {
                this.notify.success(this.translate.instant('lang.numericPackageDeleted'));
                this.closeModal('success');
            }),
            finalize(() => this.loading = false),
            catchError((err) => {
                this.notify.handleSoftErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    updateEmail(closeModal: boolean = true) {
        this.http.put(`../rest/emails/${this.data.emailId}`, this.formatNumericPackage()).pipe(
            tap(() => {

                this.notify.success(this.translate.instant('lang.numericPackageSent'));

                if (closeModal) {
                    this.closeModal('success');
                }
            }),
            finalize(() => this.loading = false),
            catchError((err) => {
                this.notify.handleSoftErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    toggleAttach(item: any, type: string, mode: string) {
        if (this.numericPackage.mainExchangeDoc === null && type !== 'notes') {
            this.numericPackage.mainExchangeDoc = {
                ...item,
                typeLabel: item.typeLabel !== undefined ? item.typeLabel : this.translate.instant('lang.mainDocument'),
                type: type
            };
        } else {
            this.numericPackageAttach.push({
                ...item,
                typeLabel: item.typeLabel !== undefined ? item.typeLabel : this.translate.instant('lang.mainDocument'),
                type: type
            });
        }
    }

    removeAttach(index: number) {
        this.numericPackageAttach.splice(index, 1);
    }

    formatNumericPackage() {
        const numericPackage: any = {};
        if (this.numericPackage.mainExchangeDoc !== null) {
            let typeDoc = 'res_letterbox';
            if (this.numericPackage.mainExchangeDoc.type === 'attachments') {
                typeDoc = 'res_attachments';
            } else if (this.numericPackage.mainExchangeDoc.type === 'notes') {
                typeDoc = 'notes';
            }
            numericPackage.joinFile = [parseInt(this.numericPackage.mainExchangeDoc.id, 10)];
            numericPackage.mainExchangeDoc = `${typeDoc}__${this.numericPackage.mainExchangeDoc.id}`;
        }
        numericPackage.object = this.numericPackage.object;
        numericPackage.content = this.numericPackage.content;
        numericPackage.contacts = this.recipients.map(recipient => recipient.id);
        numericPackage.joinAttachment = this.numericPackageAttach.filter((attach: any) => attach.type === 'attachments').map((attach: any) => attach.id);
        numericPackage.notes = this.numericPackageAttach.filter((attach: any) => attach.type === 'notes').map((attach: any) => attach.id);
        numericPackage.senderEmail = this.currentSender.id;

        return numericPackage;
    }

    isSelectedAttach(item: any, type: string) {
        return this.numericPackageAttach.filter((attach: any) => attach.id === item.id && attach.type === type).length > 0 || (this.numericPackage.mainExchangeDoc !== null && this.numericPackage.mainExchangeDoc.id === item.id && type === this.numericPackage.mainExchangeDoc.type);
    }

    isSelectedAttachType(type: string) {
        return this.numericPackageAttach.filter((attach: any) => attach.type === type).length > 0 || (this.numericPackage.mainExchangeDoc !== null && type === this.numericPackage.mainExchangeDoc.type);
    }

    canManageMail() {
        if ((this.data.emailId === null) || (this.numericPackageStatus !== 'SENT' && this.headerService.user.id === this.numericPackageCreatorId)) {
            this.recipientsCtrl.enable();
            return true;
        } else {
            this.recipientsCtrl.disable();
            return false;
        }
    }

    compareSenders(sender1: any, sender2: any) {
        return (sender1.label === sender2.label || ((sender1.label === null || sender2.label === null) && (sender1.entityId === null || sender2.entityId === null))) && sender1.entityId === sender2.entityId && sender1.email === sender2.email;
    }

    saveNumericPackageFile() {
        this.http.get(`../rest/messageExchanges/${this.data.emailId}/archiveContent`, { responseType: 'blob' }).pipe(
            tap((data: any) => {
                const downloadLink = document.createElement('a');
                downloadLink.href = window.URL.createObjectURL(data);
                downloadLink.setAttribute('download', this.functions.getFormatedFileName('NumericPackage', 'zip'));
                document.body.appendChild(downloadLink);
                downloadLink.click();
            }),
            catchError((err) => {
                this.notify.handleBlobErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    canSendNumericPackage() {
        return this.privilegeService.getCurrentUserMenus().filter((item: any) => item.id === 'manage_numeric_package').length > 0;
    }

    getCommunicationMean(value: any) {
        if (!this.functions.empty(value.url)) {
            return this.splitLoginPwd.transform(value.url);
        } else if (!this.functions.empty(value.email)) {
            return value.email;
        }
    }
}
