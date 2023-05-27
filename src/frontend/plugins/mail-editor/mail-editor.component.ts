import { CdkDragDrop, transferArrayItem } from '@angular/cdk/drag-drop';
import { COMMA, FF_SEMICOLON, SEMICOLON } from '@angular/cdk/keycodes';
import { HttpClient } from '@angular/common/http';
import { Component, ElementRef, EventEmitter, Input, OnDestroy, OnInit, Output, ViewChild } from '@angular/core';
import { UntypedFormControl } from '@angular/forms';
import { MatChipInputEvent } from '@angular/material/chips';
import { MatDialog } from '@angular/material/dialog';
import { SummarySheetComponent } from '@appRoot/list/summarySheet/summary-sheet.component';
import { DocumentViewerModalComponent } from '@appRoot/viewer/modal/document-viewer-modal.component';
import { TranslateService } from '@ngx-translate/core';
import { ConfirmComponent } from '@plugins/modal/confirm.component';
import { ContactService } from '@service/contact.service';
import { FunctionsService } from '@service/functions.service';
import { HeaderService } from '@service/header.service';
import { NotificationService } from '@service/notification/notification.service';
import { Observable, of } from 'rxjs';
import { catchError, debounceTime, distinctUntilChanged, exhaustMap, filter, finalize, map, switchMap, tap } from 'rxjs/operators';

declare let tinymce: any;

@Component({
    selector: 'app-mail-editor',
    templateUrl: 'mail-editor.component.html',
    styleUrls: ['mail-editor.component.scss'],
    providers: [ContactService]
})
export class MailEditorComponent implements OnInit, OnDestroy {

    @ViewChild('recipientsField', {static: false}) recipientsField: ElementRef;
    @ViewChild('copiesField', {static: false}) copiesField: ElementRef;
    @ViewChild('invisibleCopiesField', {static: false}) invisibleCopiesField: ElementRef;

    @Input() resId: number = null;
    @Input() emailId: any = null;
    @Input() emailType: 'email' | 'acknowledgementReceipt' = 'email';

    @Input() senderDisabled: boolean = false;
    @Input() recipientDisabled: boolean = false;

    @Input() recipientHide: boolean = false;
    @Input() senderHide: boolean = false;
    @Input() attachmentsHide: boolean = false;
    @Input() bodyHide: boolean = false;
    @Input() subjectHide: boolean = false;

    @Input() cCDisabled: boolean = false;
    @Input() cCIDisabled: boolean = false;
    @Input() subjectPrefix: string = null;


    @Input() availableSenders: any[];
    @Input() currentSender: any;

    @Input() readonly: boolean = null;

    @Output() afterLoadPaperAr = new EventEmitter<any>();

    loading: boolean = true;
    htmlMode: boolean = true;

    resourceData: any = null;

    readonly separatorKeysCodes: number[] = [COMMA, SEMICOLON, FF_SEMICOLON, 190];

    showCopies: boolean = false;
    showInvisibleCopies: boolean = false;

    recipients: any[] = [];
    copies: any[] = [];
    invisibleCopies: any[] = [];

    emailCreatorId: number = null;
    emailContent: string = '';
    emailSubject: string = '';
    emailStatus: string = 'WAITING';

    recipientsInput: UntypedFormControl = new UntypedFormControl({disabled: this.recipientDisabled});
    filteredEmails: Observable<string[]>;

    emailSignListForm = new UntypedFormControl();
    templateEmailListForm = new UntypedFormControl();
    availableEmailModels: any[] = [];
    availableSignEmailModels: any[] = [];

    currentEmailAttachTool: string = '';
    emailAttachTool: any = {
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
        summarySheet: {
            icon: 'fas fa-scroll',
            title: this.translate.instant('lang.attachSummarySheet'),
            list: []
        },
    };
    emailAttach: any = {};

    summarySheetUnits: any = [];

    correspondents: any[] = [];

    msgToDisplay: string = '';

    constructor(
        public http: HttpClient,
        public translate: TranslateService,
        public functions: FunctionsService,
        private notify: NotificationService,
        public dialog: MatDialog,
        public headerService: HeaderService,
        private contactService: ContactService,
    ) { }

    async ngOnInit() {
        if (this.readonly) {
            this.setReadonly();
        }

        if (!this.availableSenders) {
            this.currentSender = {};
            await this.getAvailableSenders();
        }

        this.initSignEmailModelsList();

        if (this.resId !== null) {
            Object.keys(this.emailAttachTool).forEach(element => {
                if (element === 'document') {
                    this.emailAttach[element] = {
                        id: this.resId,
                        isLinked: false,
                        original: false
                    };
                } else {
                    this.emailAttach[element] = [];
                }
            });

            if (!this.attachmentsHide) {
                await this.getAttachElements(this.emailId === null);
            }

            if (this.emailId !== null) {
                if (this.emailType === 'email') {
                    await this.getEmailData(this.emailId);
                } else {
                    await this.getAcknowledgementReceiptData(this.emailId);
                }
            } else {
                await this.getResourceData();
                this.setDefaultInfoFromResource();
            }

            this.initEmailModelsList();
        }

        if (this.attachmentsHide || this.resId === null) {
            this.emailAttachTool = {};
        }

        this.initEmailsList();

        if (!this.bodyHide) {
            setTimeout(() => {
                this.initMce();
            }, 0);
        } else {
            this.loading = false;
        }
    }

    ngOnDestroy(): void {
        tinymce.remove();
    }

    setReadonly() {
        this.readonly = true;
        this.senderDisabled = true;
        this.recipientDisabled = true;
        this.cCDisabled = true;
        this.cCIDisabled = true;
    }

    getResourceData() {
        return new Promise((resolve) => {
            this.http.get(`../rest/resources/${this.resId}?light=true`).pipe(
                tap((data: any) => {
                    this.resourceData = data;

                    this.emailAttach.document.chrono = this.resourceData.chrono;
                    this.emailAttach.document.label = this.resourceData.subject;

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

    getAcknowledgementReceiptData(emailId: number) {
        return new Promise((resolve) => {
            this.http.get(`../rest/acknowledgementReceipts/${emailId}`).pipe(
                tap((data: any) => {
                    this.copies = data.acknowledgementReceipt.cc.map((item: any) => ({
                        label: item.label,
                        email: item.email,
                        badFormat: this.isBadEmailFormat(item.email)
                    }));
                    this.showCopies = this.copies.length > 0;

                    this.invisibleCopies = data.acknowledgementReceipt.cci.map((item: any) => ({
                        label: item.label,
                        email: item.email,
                        badFormat: this.isBadEmailFormat(item.email)
                    }));
                    this.showInvisibleCopies = this.invisibleCopies.length > 0;
                    this.currentSender = {
                        label: data.acknowledgementReceipt.userLabel,
                        email: data.acknowledgementReceipt.userLabel
                    };
                    this.recipients = [{
                        label: !this.functions.empty(data.acknowledgementReceipt.contact) ? this.contactService.formatContact(data.acknowledgementReceipt.contact) : this.translate.instant('lang.contactDeleted'),
                        email: !this.functions.empty(data.acknowledgementReceipt.contact.email) ? data.acknowledgementReceipt.contact.email : this.translate.instant('lang.withoutEmail')
                    }];

                    this.emailStatus = 'SENT';
                    this.attachmentsHide = true;
                    this.setReadonly();
                }),
                exhaustMap(() => this.http.get(`../rest/acknowledgementReceipts/${emailId}/content`)),
                tap((data: any) => {

                    if (data.format === 'pdf') {
                        this.emailSubject = this.translate.instant('lang.ARPaper');
                        this.bodyHide = true;
                        this.afterLoadPaperAr.emit(data.encodedDocument);
                    } else {
                        this.emailSubject = this.translate.instant('lang.ARelectronic');
                        this.emailContent = this.b64DecodeUnicode(data.encodedDocument);
                    }
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

    getEmailData(emailId: number) {
        return new Promise((resolve) => {
            this.http.get(`../rest/emails/${emailId}`).pipe(
                tap(async (data: any) => {
                    this.emailCreatorId = data.userId;

                    this.recipients = data.recipients.map((item: any) => ({
                        label: item,
                        email: item,
                        badFormat: this.isBadEmailFormat(item)
                    }));

                    this.copies = data.cc.map((item: any) => ({
                        label: item,
                        email: item,
                        badFormat: this.isBadEmailFormat(item)
                    }));

                    this.invisibleCopies = data.cci.map((item: any) => ({
                        label: item,
                        email: item,
                        badFormat: this.isBadEmailFormat(item)
                    }));

                    this.showCopies = this.copies.length > 0;
                    this.showInvisibleCopies = this.invisibleCopies.length > 0;


                    this.emailSubject = data.object;
                    this.emailStatus = data.status;

                    if (this.emailStatus === 'SENT') {
                        this.setReadonly();
                    }

                    this.currentSender = {
                        entityId: data.sender.entityId,
                        label: data.sender.label,
                        email: data.sender.email
                    };

                    this.emailContent = data.body;
                    Object.keys(data.document).forEach(element => {
                        if (['id', 'isLinked', 'original', 'resource'].indexOf(element) === -1) {
                            data.document[element].forEach(item => this.emailAttachTool[element].list.push(item));

                            this.emailAttachTool[element].list = data.document[element].map((item: any) => {
                                this.toggleAttachMail(item, element, item.status === 'SIGN' ? 'pdf' : 'original');
                                return {
                                    ...item,
                                    original: item.original !== undefined ? item.original : true,
                                    title: item.chrono !== undefined ? `${item.chrono} - ${item.label} (${item.typeLabel})` : `${item.label} (${item.typeLabel})`
                                };
                            });
                        } else if (element === 'isLinked' && data.document.isLinked === true) {
                            this.emailAttach.document.isLinked = true;
                            this.emailAttach.document.format = data.document.original || data.document.original === undefined ? data.document.resource.format : 'pdf';
                            this.emailAttach.document.original = data.document.original;
                            this.emailAttach.document.size = data.document.resource.size;
                            this.emailAttach.document.label = data.document.resource.label;
                            this.emailAttach.document.chrono = data.document.resource.chrono;
                        }
                    });
                    await this.getAttachElements(false);
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

    setDefaultInfoFromResource() {
        this.emailSubject = `[${this.resourceData.chrono}] ${this.resourceData.subject}`;
        this.emailSubject = this.emailSubject.substring(0, 70);
        if (this.headerService.user.entities.length === 0) {
            this.currentSender = this.availableSenders[0];
        } else {
            this.currentSender = this.availableSenders.filter(sender => sender.entityId === this.headerService.user.entities[0].id).length > 0 ? this.availableSenders.filter(sender => sender.entityId === this.headerService.user.entities[0].id)[0] : this.availableSenders[0];
        }
        if (!this.functions.empty(this.resourceData.senders)) {
            this.resourceData.senders.forEach((sender: any) => {
                this.formatData(sender);
            });
        }

        if (!this.functions.empty(this.resourceData.recipients)) {
            this.resourceData.recipients.forEach((recipient: any) => {
                this.formatData(recipient);
            });
        }
    }

    formatData(sender: any) {
        switch (sender.type) {
            case 'contact':
                this.http.get(`../rest/contacts/${sender.id}`).pipe(
                    tap((data: any) => {
                        if (!this.functions.empty(data.email)) {
                            this.recipients.push(
                                {
                                    label: this.contactService.formatContact(data),
                                    email: data.email
                                }
                            );
                        }
                    }),
                    catchError((err) => {
                        this.notify.handleSoftErrors(err);
                        return of(false);
                    })
                ).subscribe();
                break;

            case 'user':
                this.http.get(`../rest/users/${sender.id}`).pipe(
                    tap((data: any) => {
                        if (!this.functions.empty(data.mail)) {
                            this.recipients.push(
                                {
                                    label: this.contactService.formatContact(data),
                                    email: data.mail
                                }
                            );
                        }
                    }),
                    catchError((err) => {
                        this.notify.handleSoftErrors(err);
                        return of(false);
                    })
                ).subscribe();
                break;

            default:
                break;
        }
    }

    setSender(sender: any) {
        this.currentSender = sender;
    }

    getSender() {
        return this.currentSender;
    }

    setRecipients(recipients: any) {
        this.recipients = recipients;
    }

    setAttachments(attachInfo: any) {
        Object.keys(attachInfo.document).forEach(element => {
            if (['id', 'isLinked', 'original', 'resource'].indexOf(element) === -1) {
                attachInfo.document[element].forEach(item => this.emailAttachTool[element].list.push(item));

                this.emailAttachTool[element].list = attachInfo.document[element].map((item: any) => {
                    this.toggleAttachMail(item, element, item.status === 'SIGN' ? 'pdf' : 'original');
                    return {
                        ...item,
                        original: item.original !== undefined ? item.original : true,
                        title: item.chrono !== undefined ? `${item.chrono} - ${item.label} (${item.typeLabel})` : `${item.label} (${item.typeLabel})`
                    };
                });
            } else if (element === 'isLinked' && attachInfo.document.isLinked === true) {
                this.emailAttach.document.isLinked = true;
                this.emailAttach.document.format = attachInfo.document.original || attachInfo.document.original === undefined ? attachInfo.document.resource.format : 'pdf';
                this.emailAttach.document.original = attachInfo.document.original;
                this.emailAttach.document.size = attachInfo.document.resource.size;
                this.emailAttach.document.label = attachInfo.document.resource.label;
                this.emailAttach.document.chrono = attachInfo.document.resource.chrono;
            }
        });
    }

    setCopies(copies: any) {
        this.copies = copies;
        this.showCopies = this.copies.length > 0;
    }

    getCopies() {
        if (this.showCopies) {
            return this.copies.map((item: any) => {
                delete item.badFormat;
                return item;
            });
        } else {
            return [];
        }
    }

    setInvisibleCopies(invisibleCopies: any) {
        this.invisibleCopies = invisibleCopies;
        this.showInvisibleCopies = this.invisibleCopies.length > 0;
    }

    getInvisibleCopies() {
        if (this.showInvisibleCopies) {
            return this.invisibleCopies.map((item: any) => {
                delete item.badFormat;
                return item;
            });
        } else {
            return [];
        }
    }

    isSelectedAttachMail(item: any, type: string) {
        if (type === 'document') {
            return this.emailAttach.document.isLinked;
        } else {
            return this.emailAttach[type].filter((attach: any) => attach.id === item.id).length > 0;
        }
    }

    getAttachElements(attachElements: boolean) {
        return new Promise((resolve) => {
            this.http.get(`../rest/resources/${this.resId}/emailsInitialization`).pipe(
                tap((data: any) => {
                    Object.keys(data).forEach(element => {
                        if (element === 'resource') {
                            this.emailAttachTool.document.list = [];
                            if (!this.functions.empty(data[element])) {
                                this.emailAttachTool.document.list = [data[element]];
                            }
                        } else {
                            this.emailAttachTool[element].list = data[element].map((item: any) => {
                                if (item.attachInMail && attachElements) {
                                    this.toggleAttachMail(item, element, item.status === 'SIGN' ? 'pdf' : 'original');
                                }
                                return {
                                    ...item,
                                    original: item.original !== undefined ? item.original : true,
                                    title: item.chrono !== undefined ? `${item.chrono} - ${item.label} (${item.typeLabel})` : `${item.label} (${item.typeLabel})`
                                };
                            });
                        }
                    });
                    this.getRecipientInfos(this.emailAttachTool.attachments.list);
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

    toggleAttachMail(item: any, type: string, mode: 'original' | 'pdf') {
        if (type === 'document') {
            if (this.emailAttach.document.isLinked === false) {
                this.emailAttach.document.isLinked = true;
                this.emailAttach.document.format = mode !== 'pdf' ? item.format : 'pdf';
                this.emailAttach.document.original = mode !== 'pdf';
                this.emailAttach.document.size = mode === 'pdf' ? item.convertedDocument.size : item.size;
            }
        } else {
            if (this.emailAttach[type].filter((attach: any) => attach.id === item.id).length === 0) {
                this.emailAttach[type].push({
                    ...item,
                    format: mode !== 'pdf' ? item.format : 'pdf',
                    original: mode !== 'pdf',
                    size: mode === 'pdf' ? item.convertedDocument.size : item.size
                });
            }
        }
    }

    getAvailableSenders() {
        return new Promise((resolve) => {
            this.http.get('../rest/currentUser/availableEmails').pipe(
                tap((data: any) => {
                    this.availableSenders = data.emails;
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

    compareSenders(sender1: any, sender2: any) {
        return (sender1.label === sender2.label || ((sender1.label === null || sender2.label === null) && (sender1.entityId === null || sender2.entityId === null))) && sender1.entityId === sender2.entityId && sender1.email === sender2.email;
    }

    initMce() {
        tinymce.init({
            setup: (editor: any) => {
                editor.on('init', () => {
                    this.loading = false;
                });
            },
            selector: 'textarea#emailSignature',
            base_url: '../node_modules/tinymce/',
            convert_urls: false,
            readonly: this.readonly,
            height: '400',
            suffix: '.min',
            language: this.translate.instant('lang.langISO').replace('-', '_'),
            language_url: `../node_modules/tinymce-i18n/langs/${this.translate.instant('lang.langISO').replace('-', '_')}.js`,
            menubar: false,
            statusbar: false,
            plugins: [
                'autolink'
            ],
            external_plugins: {
                'maarch_b64image': '../../src/frontend/plugins/tinymce/maarch_b64image/plugin.min.js'
            },
            toolbar_sticky: true,
            toolbar_mode: 'floating',
            toolbar: !this.readonly ?
                'undo redo | fontselect fontsizeselect | bold italic underline strikethrough forecolor | maarch_b64image | \
            alignleft aligncenter alignright alignjustify \
            bullist numlist outdent indent | removeformat' : false
        });
    }

    drop(event: CdkDragDrop<string[]>) {

        if (event.previousContainer !== event.container) {
            transferArrayItem(event.previousContainer.data,
                event.container.data,
                event.previousIndex,
                event.currentIndex);
        }
    }

    add(event: MatChipInputEvent, type: string): void {

        const input = event.input;
        const value = event.value;

        if ((value || '').trim()) {
            this[type].push(
                {
                    label: value.trim(),
                    email: value.trim(),
                    badFormat: this.isBadEmailFormat(value.trim())
                });
        }

        if (input) {
            input.value = '';
        }
    }

    remove(item: any, type: string): void {
        const index = this[type].indexOf(item);

        if (index >= 0) {
            this[type].splice(index, 1);
        }

        if (this.recipients.length === 0) {
            this.msgToDisplay = '';
        }
    }

    initEmailsList() {
        this.recipientsInput.valueChanges.pipe(
            filter(value => value !== null),
            debounceTime(300),
            tap((value) => {
                if (value.length === 0) {
                    this.filteredEmails = of([]);
                }
            }),
            filter(value => value.length > 2),
            distinctUntilChanged(),
            switchMap(data => this.http.get('../rest/autocomplete/correspondents', { params: { 'search': data, 'searchEmails': 'true' } })),
            tap((data: any) => {
                data = data.filter((contact: any) => !this.functions.empty(contact.email) || contact.type === 'contactGroup').map((contact: any) => {
                    let label: string;
                    if (contact.type === 'user' || contact.type === 'contact') {
                        if (!this.functions.empty(contact.firstname) && !this.functions.empty(contact.lastname)) {
                            label = `${contact.firstname} ${contact.lastname}`;
                        } else if (this.functions.empty(contact.firstname) && !this.functions.empty(contact.lastname)) {
                            label = contact.lastname;
                        } else if (!this.functions.empty(contact.firstname) && this.functions.empty(contact.lastname)) {
                            label = contact.firstname;
                        } else {
                            label = contact.company;
                        }
                    } else if (contact.type === 'contactGroup') {
                        label = `${contact.firstname} ${contact.lastname}`;
                    } else {
                        label = `${contact.lastname}`;
                    }
                    return {
                        id: contact.id,
                        type: contact.type,
                        label: label,
                        email: contact.email
                    };
                });
                this.filteredEmails = of(data);
            }),
            catchError((err) => {
                this.notify.handleSoftErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    initEmailModelsList() {
        this.http.get(`../rest/resources/${this.resId}/emailTemplates`).pipe(
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

    isBadEmailFormat(email: string) {
        const regex = /^[a-zA-Z0-9_.+-]+@[a-zA-Z0-9-]+\.[a-zA-Z0-9-.]+$/g;

        return email.trim().match(regex) === null;
    }

    resetAutocomplete() {
        this.filteredEmails = of([]);
    }

    onPaste(event: ClipboardEvent, type: string) {
        const clipboardData = event.clipboardData;
        const pastedText = clipboardData.getData('text');
        this.formatEmailAddress(pastedText, type);
    }

    formatEmailAddress(rawAddresses: string, type: string) {
        const arrRawAdd: string[] = rawAddresses.split(/[,;]+/);

        if (!this.functions.empty(arrRawAdd)) {
            setTimeout(() => {
                this.recipientsInput.setValue(null);
                this[type + 'Field'].nativeElement.value = '';
            }, 0);

            arrRawAdd.forEach((rawAddress: any) => {
                rawAddress = rawAddress.match(/([a-zA-Z0-9._-]+@[a-zA-Z0-9._-]+\.[a-zA-Z0-9_-]+)/gi);

                if (!this.functions.empty(rawAddress)) {
                    this[type].push({ label: rawAddress[0], email: rawAddress[0] });
                }
            });
        }
    }

    addEmail(item: any, type: string) {
        this[type].splice(this[type].length - 1, 1);

        if (item.type === 'contactGroup') {
            this.http.get(`../rest/contactsGroups/${item.id}/correspondents?limit=none`).pipe(
                map((data: any) => {
                    this.correspondents = data.correspondents;
                    data = data.correspondents.filter((contact: any) => !this.functions.empty(contact.email)).map((contact: any) => ({
                        label: contact.name,
                        email: contact.email
                    }));
                    return data;
                }),
                tap((data: any) => {
                    if (this.functions.empty(data)) {
                        this.notify.error(this.translate.instant('lang.emptyEmails'));
                    } else {
                        const emptyMails: number = this.correspondents.filter((contact: any) => this.functions.empty(contact.email)).length;
                        if (emptyMails > 0) {
                            this.msgToDisplay = this.translate.instant('lang.correspondentEmptyEmails', {nbr: emptyMails});
                        } else {
                            this.msgToDisplay = '';
                        }
                        this[type] = this[type].concat(data);
                    }
                }),
                catchError((err) => {
                    this.notify.handleSoftErrors(err);
                    return of(false);
                })
            ).subscribe();
        } else {
            this[type].push({
                label: item.label,
                email: item.email
            });
        }
    }

    mergeEmailTemplate(templateId: any) {

        this.templateEmailListForm.reset();

        this.http.post(`../rest/templates/${templateId}/mergeEmail`, { data: { resId: this.resId } }).pipe(
            tap((data: any) => {

                const div = document.createElement('div');

                div.innerHTML = tinymce.get('emailSignature').getContent();

                if (div.getElementsByClassName('signature').length > 0) {

                    const signatureContent = div.getElementsByClassName('signature')[0].innerHTML;

                    div.getElementsByClassName('signature')[0].remove();

                    tinymce.get('emailSignature').setContent(`${div.innerHTML}${data.mergedDocument}<div class="signature">${signatureContent}</div>`);

                } else {
                    tinymce.get('emailSignature').setContent(`${tinymce.get('emailSignature').getContent()}${data.mergedDocument}`);
                }
                if (!this.htmlMode) {
                    tinymce.get('emailSignature').setContent(tinymce.get('emailSignature').getContent({ format: 'text' }));
                }

                if (!this.functions.empty(data.mergedSubject)) {
                    this.emailSubject = data.mergedSubject;
                }
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
                const div = document.createElement('div');

                div.innerHTML = tinymce.get('emailSignature').getContent();

                if (div.getElementsByClassName('signature').length > 0) {

                    div.getElementsByClassName('signature')[0].remove();

                    tinymce.get('emailSignature').setContent(`${div.innerHTML}<div class="signature">${data.emailSignature.content}</div>`);
                } else {
                    tinymce.get('emailSignature').setContent(`${tinymce.get('emailSignature').getContent()}<div class="signature">${data.emailSignature.content}</div>`);
                }
                if (!this.htmlMode) {
                    tinymce.get('emailSignature').setContent(tinymce.get('emailSignature').getContent({ format: 'text' }));
                }
            }),
            catchError((err) => {
                this.notify.handleSoftErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    switchEditionMode() {
        this.htmlMode = !this.htmlMode;
        if (this.htmlMode) {
            $('.tox-editor-header').show();
            tinymce.get('emailSignature').setContent(tinymce.get('emailSignature').getContent());
        } else {
            const dialogRef = this.dialog.open(ConfirmComponent, { panelClass: 'maarch-modal', autoFocus: false, disableClose: true, data: { title: this.translate.instant('lang.switchInPlainText'), msg: this.translate.instant('lang.confirmSwitchInPlanText') } });
            dialogRef.afterClosed().pipe(
                tap((data: string) => {
                    if (data === 'ok') {
                        $('.tox-editor-header').hide();
                        tinymce.get('emailSignature').setContent(tinymce.get('emailSignature').getContent({ format: 'text' }));
                    } else {
                        this.htmlMode = !this.htmlMode;
                    }
                })
            ).subscribe();

        }
    }

    openSummarySheetModal(keyVal: any): void {
        if (keyVal !== 'summarySheet') {
            return;
        }
        const title = this.functions.getFormatedFileName(this.translate.instant('lang.summarySheet'));

        const dialogRef = this.dialog.open(SummarySheetComponent, {
            panelClass: 'maarch-full-height-modal',
            width: '800px',
            data: {
                paramMode: true
            }
        });
        dialogRef.afterClosed().pipe(
            filter((data: string) => data !== undefined),
            tap((data: any) => {
                this.summarySheetUnits = data;
                this.emailAttach['summarySheet'].push({
                    label: title,
                    format: 'pdf',
                    title: title,
                    list: []
                });
            }),
            catchError((err: any) => {
                this.notify.handleSoftErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    async createSummarySheet() {
        return new Promise(resolve => {
            this.http.post('../rest/resourcesList/summarySheets?mode=base64', {
                units: this.summarySheetUnits,
                resources: [this.resId]
            })
                .pipe(
                    tap(async (sheetData: any) => {
                        await this.saveSummarySheet(sheetData.encodedDocument);

                        this.loading = false;
                        resolve(true);
                    }),
                    catchError((err) => {
                        this.notify.handleErrors(err);
                        resolve(false);
                        return of(false);
                    })
                ).subscribe();
        });
    }

    async saveSummarySheet(encodedDocument: any) {
        return new Promise(resolve => {
            const title = this.functions.getFormatedFileName(this.translate.instant('lang.summarySheet'));
            this.http.post('../rest/attachments', { resIdMaster: this.resId, encodedFile: encodedDocument, type: 'summary_sheet', format: 'PDF', title: title })
                .pipe(
                    tap((dataAttachment: any) => {
                        this.emailAttach['summarySheet'] = undefined;

                        this.emailAttach['attachments'].push({
                            id: dataAttachment.id,
                            label: title,
                            format: 'pdf',
                            title: title,
                            original: true
                        });
                        this.loading = false;
                        resolve(true);
                    }),
                    catchError((err) => {
                        this.notify.handleErrors(err);
                        resolve(false);
                        return of(false);
                    })
                )
                .subscribe();
        });
    }

    saveDraft() {
        return new Promise(async (resolve) => {
            if (!this.readonly && !this.functions.empty(tinymce.get('emailSignature').getContent())) {
                this.emailStatus = 'DRAFT';
                if (this.emailId === null) {
                    await this.createEmail();
                } else {
                    await this.updateEmail();
                }
                resolve(true);
            } else {
                resolve(false);
            }
        });
    }

    async createEmail() {
        if (this.summarySheetUnits.length !== 0) {
            await this.createSummarySheet();
        }

        return new Promise((resolve) => {
            this.http.post('../rest/emails', this.formatEmail()).pipe(
                finalize(() => resolve(true)),
                catchError((err) => {
                    this.notify.handleSoftErrors(err);
                    return of(false);
                })
            ).subscribe();
        });
    }

    async updateEmail() {
        if (this.summarySheetUnits.length !== 0) {
            await this.createSummarySheet();
        }
        return new Promise((resolve) => {
            this.http.put(`../rest/emails/${this.emailId}`, this.formatEmail()).pipe(
                finalize(() => resolve(true)),
                catchError((err) => {
                    this.notify.handleSoftErrors(err);
                    return of(false);
                })
            ).subscribe();
        });
    }

    formatEmail() {
        let objAttach: any = {};
        Object.keys(this.emailAttach).forEach(element => {
            if (!this.functions.empty(this.emailAttach[element])) {
                if (element === 'document') {
                    objAttach = {
                        id: this.emailAttach[element].id,
                        isLinked: this.emailAttach[element].isLinked,
                        original: this.emailAttach[element].original
                    };
                } else if (element === 'notes') {
                    objAttach[element] = this.emailAttach[element].map((item: any) => item.id);
                } else {
                    objAttach[element] = this.emailAttach[element].map((item: any) => ({
                        id: item.id,
                        original: item.original
                    }));
                }
            }
        });

        const formatSender = !this.functions.empty(this.currentSender) ? {
            email: this.currentSender.email,
            entityId: !this.functions.empty(this.currentSender.entityId) ? this.currentSender.entityId : null
        } : null;

        return {
            document: objAttach,
            sender: formatSender,
            recipients: this.recipients.map(recipient => recipient.email),
            cc: this.showCopies ? this.copies.map(copy => copy.email) : [],
            cci: this.showInvisibleCopies ? this.invisibleCopies.map((invCopy => invCopy.email)) : [],
            object: this.subjectPrefix !== null ? this.subjectPrefix + ' ' + this.emailSubject : this.emailSubject,
            body: this.htmlMode ? tinymce.get('emailSignature')?.getContent() : tinymce.get('emailSignature')?.getContent({ format: 'text' }),
            isHtml: true,
            status: this.emailStatus
        };
    }

    openEmailAttach(type: string, attach: any): void {
        if (type === 'attachments') {
            this.http.get(`../rest/attachments/${attach.id}/content?mode=base64`).pipe(
                tap((data: any) => {
                    this.dialog.open(DocumentViewerModalComponent,
                        {
                            autoFocus: false,
                            panelClass: ['maarch-full-height-modal', 'maarch-doc-modal'],
                            data: {
                                title: `${attach.label}`,
                                base64: data.encodedDocument,
                                filename: data.filename,
                                source: 'mailEditor',
                                contentMode: 'route',
                                content: `../rest/attachments/${attach.id}/originalContent?mode=base64`
                            }
                        }
                    );
                }),
                catchError((err: any) => {
                    this.notify.handleSoftErrors(err);
                    return of(false);
                })
            ).subscribe();
        }
    }

    removeAttachMail(index: number, type: string) {
        if (type === 'document') {
            this.emailAttach.document.isLinked = false;
            this.emailAttach.document.original = false;
        } else if (type === 'summarySheet') {
            this.emailAttach.summarySheet = [];
            this.summarySheetUnits = [];
        } else {
            this.emailAttach[type].splice(index, 1);
        }
    }

    isAllEmailRightFormat() {
        let state = true;
        const allEmail = this.recipients.concat(this.copies).concat(this.invisibleCopies);

        allEmail.map(item => item.email).forEach(email => {
            if (this.isBadEmailFormat(email)) {
                state = false;
            }
        });

        return state;
    }

    b64DecodeUnicode(str: string) {
        // Going backwards: from bytestream, to percent-encoding, to original string.
        return decodeURIComponent(atob(str).split('').map(function (c) {
            return '%' + ('00' + c.charCodeAt(0).toString(16)).slice(-2);
        }).join(''));
    }

    getRecipientInfos(attachments: any[]) {
        attachments.forEach((attach: any, index: number) => {
            if (attach.recipientId !== null) {
                switch (attach.recipientType) {
                    case 'user':
                        this.http.get(`../rest/users/${attach.recipientId}`).pipe(
                            tap((data: any) => {
                                this.emailAttachTool.attachments.list[index] = {
                                    ...this.emailAttachTool.attachments.list[index],
                                    recipientLabel: this.formatUserName(data)
                                };
                            }),
                            catchError((err) => {
                                this.notify.handleSoftErrors(err);
                                return of(false);
                            })
                        ).subscribe();
                        break;

                    case 'contact':
                        this.http.get(`../rest/contacts/${attach.recipientId}`).pipe(
                            tap((data: any) => {
                                this.emailAttachTool.attachments.list[index] = {
                                    ...this.emailAttachTool.attachments.list[index],
                                    recipientLabel: this.contactService.formatContact(data),
                                    onlyCompany: !this.functions.empty(data.company) && this.functions.empty(data.firstname) && this.functions.empty(data.lastname) ? true : false
                                };
                            }),
                            catchError((err) => {
                                this.notify.handleSoftErrors(err);
                                return of(false);
                            })
                        ).subscribe();
                        break;
                }
            }
        });
    }

    formatUserName(data: any) {
        if (this.functions.empty(data.firstname) && this.functions.empty(data.lastname)) {
            return null;
        } else {
            const dataUser: any[] = [];
            dataUser.push(data.firstname);
            dataUser.push(data.lastname);
            return dataUser.filter((item: any) => !this.functions.empty(item)).join(' ');
        }
    }

}
