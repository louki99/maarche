import { Component, OnInit } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { of } from 'rxjs';
import { catchError, filter, finalize, map, tap } from 'rxjs/operators';
import { NotificationService } from '../service/notification/notification.service';
import { AuthService } from '../service/auth.service';
import { TranslateService } from '@ngx-translate/core';
import { FunctionsService } from '../service/functions.service';
import { KeyValue } from '@angular/common';

declare const Office: any;
@Component({
    selector: 'app-panel',
    templateUrl: './panel.component.html',
    styleUrls: ['./panel.component.scss']
})
export class PanelComponent implements OnInit {
    status: string = 'loading';

    inApp: boolean = false;
    resId: number = null;

    displayResInfo: any = {};
    displayMailInfo: any = {};
    docFromMail: any = {};
    contactInfos: any = {};
    userInfos: any;
    mailBody: any;
    attachments: any[] = [];
    contactId: number;

    addinConfig: any = {}

    connectionTry: any = null;

    serviceRequest: any = {};

    constructor(
        public http: HttpClient,
        private notificationService: NotificationService,
        public authService: AuthService,
        public translate: TranslateService,
        public functions: FunctionsService
    ) {
        this.authService.catchEvent().subscribe(async (result: any) => {
            if (result === 'connected') {
                this.inApp = await this.checkMailInApp();

                if (!this.inApp) {
                    this.initMailInfo();
                    this.status = 'end';
                }
            } else if (result === 'not connected') {
                this.status = 'end';
            }
        });
    }

    ngOnInit() {
        const res = this.authService.getConnection();
        if (!res) {
            this.authService.tryConnection();
        }
    }

    async sendToMaarch() {
        this.status = 'loading';
        this.attachments = this.attachments.filter((attachment: any) => attachment.selected);
        await this.getMailBody();
        await this.createContact();
        await this.createDocFromMail();
        if (this.attachments.length > 0 && this.addinConfig.outlookPasswordSaved) {
            this.createAttachments(this.resId);
        }
    }

    checkMailInApp(): Promise<boolean> {
        let emailId: string = '"' + Office.context.mailbox.item.itemId + '"';
        let infoEmail: any = {
            type: 'emailId',
            value: emailId
        };
        return new Promise((resolve) => {
            this.http.put('../rest/resources/external', infoEmail).pipe(
                tap((data: any) => {
                    this.status = 'end';
                    const result = data.resId !== undefined ? true : false;
                    resolve(result);
                }),
                catchError((err: any) => {
                    if (err.error.errors === 'Document not found') {
                        this.status = 'end';
                        this.initMailInfo();
                    } else {
                        this.notificationService.handleErrors(err);
                    }
                    resolve(false);
                    return of(false);
                })
            ).subscribe();
        });
    }

    async initMailInfo() {
        await this.getConfiguration();
        this.displayResInfo = {
            typist: `${this.authService.user.firstname} ${this.authService.user.lastname}`,
            indexingModel: this.addinConfig?.indexingModelLabel,
            doctype: this.addinConfig?.typeLabel,
            status: this.addinConfig?.statusLabel,
        }
        this.displayMailInfo = {
            sender: Office.context.mailbox.item.from.displayName,
            subject: Office.context.mailbox.item.subject,
            documentDate: this.functions.formatObjectToDateFullFormat(Office.context.mailbox.item.dateTimeCreated),
            emailId: Office.context.mailbox.item.itemId, 
        };
        this.attachments = Office.context.mailbox.item.attachments.filter((attachment: any) => !attachment.isInline).map((attachment: any) => {
            return {
                ...attachment,
                selected: true
            };
        });
    }

    getConfiguration() {
        return new Promise((resolve) => {
            this.http.get(`../rest/plugins/outlook/configuration`).pipe(
                filter((data: any) => !this.functions.empty(data.configuration)),
                map((data: any) => data.configuration),
                tap((data: any) => {
                    this.addinConfig = data;
                    resolve(true);
                })
            ).subscribe();
        });
    }

    createDocFromMail() {
        this.docFromMail = {
            modelId: this.addinConfig.indexingModelId,
            doctype: this.addinConfig.typeId,
            subject: Office.context.mailbox.item.subject,
            chrono: true,
            typist: this.authService.user.id,
            status: this.addinConfig.status,
            documentDate: Office.context.mailbox.item.dateTimeCreated,
            arrivalDate: Office.context.mailbox.item.dateTimeCreated,
            format: 'html',
            encodedFile: btoa(unescape(encodeURIComponent(this.mailBody))),
            externalId: { emailId: Office.context.mailbox.item.itemId },
            senders: [{ id: this.contactId, type: 'contact' }]
        };
        return new Promise((resolve) => {
            this.http.post('../rest/resources', this.docFromMail).pipe(
                tap((data: any) => {
                    this.resId = data.resId;
                    this.notificationService.success(this.translate.instant('lang.emailSent'));
                    this.inApp = true;
                    resolve(true);
                }),
                finalize(() => this.status = 'end'),
                catchError((err: any) => {
                    this.notificationService.handleErrors(err);
                    return of(false);
                })
            ).subscribe();
        });
    }

    getMailBody() {
        return new Promise((resolve) => {
            Office.context.mailbox.item.body.getAsync(Office.CoercionType.Html, ((res: { value: any; }) => {
                this.mailBody = res.value;
                resolve(true);
            }));
        });

    }

    createContact() {
        const userName: string = Office.context.mailbox.item.from.displayName;
        const index = userName.lastIndexOf(' ');
        this.contactInfos = {
            firstname: userName.substring(0, index),
            lastname: userName.substring(index + 1),
            email: Office.context.mailbox.item.from.emailAddress,
        };
        return new Promise((resolve) => {
            this.http.post('../rest/contacts', this.contactInfos).pipe(
                tap((data: any) => {
                    this.contactId = data.id;
                    resolve(true);
                }),
                catchError((err: any) => {
                    this.notificationService.handleErrors(err);
                    return of(false);
                })
            ).subscribe();
        });
    }

    createAttachments(resId: number) {
        const objToSend = {
            resId: resId,
            ewsUrl: Office.context.mailbox.ewsUrl.replace('https://', ''),
            ewsVersion: `Exchange20${Office.context.diagnostics.version.split('.')[0]}`,
            emailId: Office.context.mailbox.item.itemId,
            userId: Office.context.mailbox.userProfile.emailAddress,
            attachments: this.attachments.map((attachment: any) => attachment.id)
        };
        return new Promise((resolve) => {
            this.http.put('../rest/plugins/outlook/attachments', objToSend).pipe(
                finalize(() => resolve(true)),
                catchError((err: any) => {
                    this.notificationService.handleSoftErrors(this.translate.instant('lang.' + err.error.lang))
                    return of(false);
                })
            ).subscribe();
        });
    }

    originalOrder = (a: KeyValue<string, any>, b: KeyValue<string, any>): number => {
        return 0;
    }
}
