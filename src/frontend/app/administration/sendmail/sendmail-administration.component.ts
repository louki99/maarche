import { Component, OnInit, ViewChild, TemplateRef, ViewContainerRef } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { TranslateService } from '@ngx-translate/core';
import { NotificationService } from '@service/notification/notification.service';
import { HeaderService } from '@service/header.service';
import { AppService } from '@service/app.service';
import { NgForm } from '@angular/forms';
import { CheckMailServerModalComponent } from './checkMailServer/check-mail-server-modal.component';
import { catchError, filter, tap } from 'rxjs/operators';
import { FunctionsService } from '@service/functions.service';
import { of } from 'rxjs';
import { MatDialog } from '@angular/material/dialog';

@Component({
    templateUrl: 'sendmail-administration.component.html',
    styleUrls: ['sendmail-administration.component.scss']
})
export class SendmailAdministrationComponent implements OnInit {

    @ViewChild('adminMenuTemplate', { static: true }) adminMenuTemplate: TemplateRef<any>;

    @ViewChild('sendmailForm', { static: false }) public sendmailFormCpt: NgForm;

    loading: boolean = false;

    sendmail: any = {
        'type': 'smtp',
        'host': '',
        'auth': true,
        'user': '',
        'password': '',
        'secure': 'ssl', // tls, ssl, starttls
        'port': '465',
        'charset': 'utf-8',
        'from': '',
    };

    smtpTypeList = [
        {
            id: 'smtp',
            label: this.translate.instant('lang.smtpclient')
        },
        {
            id: 'sendmail',
            label: this.translate.instant('lang.smtprelay')
        },
        {
            id: 'qmail',
            label: this.translate.instant('lang.qmail')
        },
        {
            id: 'mail',
            label: this.translate.instant('lang.phpmail')
        }
    ];
    smtpSecList = [
        {
            id: '',
            label: this.translate.instant('lang.none')
        },
        {
            id: 'ssl',
            label: 'ssl'
        },
        {
            id: 'tls',
            label: 'tls'
        }
    ];
    sendmailClone: any = {};
    hidePassword: boolean = true;
    serverConnectionLoading: boolean = false;
    emailSendLoading: boolean = false;
    emailSendResult = {
        icon: '',
        msg: '',
        debug: ''
    };
    currentUser: any = {};
    recipientTest: string = '';
    passwordLabel: string = '';

    useSMTPAuth: boolean = false;
    
    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        private notify: NotificationService,
        private headerService: HeaderService,
        public appService: AppService,
        public dialog: MatDialog,
        private functionsService: FunctionsService,
        private viewContainerRef: ViewContainerRef
    ) { }

    ngOnInit(): void {
        this.headerService.setHeader(this.translate.instant('lang.sendmailShort'));

        this.headerService.injectInSideBarLeft(this.adminMenuTemplate, this.viewContainerRef, 'adminMenu');

        this.loading = true;

        this.http.get('../rest/configurations/admin_email_server')
            .subscribe((data: any) => {
                this.recipientTest = this.headerService.user.mail;
                this.sendmail = data.configuration.value;
                this.sendmailClone = JSON.parse(JSON.stringify(this.sendmail));

                this.loading = false;
            }, (err) => {
                this.notify.handleErrors(err);
            });
    }

    cancelModification() {
        this.sendmail = JSON.parse(JSON.stringify(this.sendmailClone));
    }

    onSubmit() {
        return new Promise((resolve) => {
            this.http.put('../rest/configurations/admin_email_server', this.sendmail).pipe(
                tap((data: any) => {
                    this.sendmailClone = JSON.parse(JSON.stringify(this.sendmail));
                    this.notify.success(this.translate.instant('lang.configurationUpdated'));
                    resolve(true);
                }),
                catchError((err: any) => {
                    this.notify.handleErrors(err);
                    return of(false);
                })
            ).subscribe();
        });
    }

    checkModif() {
        return (JSON.stringify(this.sendmailClone) === JSON.stringify(this.sendmail));
    }

    cleanAuthInfo(event: any) {
        this.sendmail.passwordAlreadyExists = false;

        this.sendmail.user = '';
        this.sendmail.password = '';
    }

    async openMailServerTest() {
        await this.onSubmit();
        this.dialog.open(CheckMailServerModalComponent, {
            panelClass: 'maarch-modal',
            disableClose: true,
            width: '500px',
            // height: '99%',
            data: {
                serverConf: this.sendmail,
                recipient: this.recipientTest,
                sender: this.emailSendResult
            }
        });
    }
}
