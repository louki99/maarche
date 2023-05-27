import { Component, Input, OnInit } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { NotificationService } from '@service/notification/notification.service';
import { TranslateService } from '@ngx-translate/core';
import { FunctionsService } from '@service/functions.service';
import { HeaderService } from '@service/header.service';
import { ConfirmComponent } from '@plugins/modal/confirm.component';
import { MatDialog } from '@angular/material/dialog';
import { catchError, exhaustMap, filter, tap } from 'rxjs/operators';
import { of } from 'rxjs';

declare let tinymce: any;

@Component({
    selector: 'app-signature-mail',
    templateUrl: './signature-mail.component.html',
    styleUrls: ['./signature-mail.component.scss'],
})

export class MySignatureMailComponent implements OnInit {

    @Input() mailSignatureModel: any;
    @Input() userEmailSignatures: any[];

    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        private notify: NotificationService,
        public functionsService: FunctionsService,
        public headerService: HeaderService,
        public dialog: MatDialog
    ){}

    ngOnInit(): void {}

    initMce() {
        tinymce.remove('textarea');
        // LOAD EDITOR TINYMCE for MAIL SIGN
        tinymce.baseURL = '../node_modules/tinymce';
        tinymce.suffix = '.min';
        tinymce.init({
            selector: 'textarea#emailSignature',
            convert_urls: false,
            statusbar: false,
            language: this.translate.instant('lang.langISO').replace('-', '_'),
            language_url: `../node_modules/tinymce-i18n/langs/${this.translate.instant('lang.langISO').replace('-', '_')}.js`,
            height: '200',
            external_plugins: {
                'maarch_b64image': '../../src/frontend/plugins/tinymce/maarch_b64image/plugin.min.js'
            },
            menubar: false,
            toolbar: 'undo | bold italic underline | alignleft aligncenter alignright | maarch_b64image | forecolor',
            theme_buttons1_add: 'fontselect,fontsizeselect',
            theme_buttons2_add_before: 'cut,copy,paste,pastetext,pasteword,separator,search,replace,separator',
            theme_buttons2_add: 'separator,insertdate,inserttime,preview,separator,forecolor,backcolor',
            theme_buttons3_add_before: 'tablecontrols,separator',
            theme_buttons3_add: 'separator,print,separator,ltr,rtl,separator,fullscreen,separator,insertlayer,moveforward,movebackward,absolut',
            theme_toolbar_align: 'left',
            theme_advanced_toolbar_location: 'top',
            theme_styles: 'Header 1=header1;Header 2=header2;Header 3=header3;Table Row=tableRow1'

        });
    }

    submitEmailSignature() {
        this.mailSignatureModel.htmlBody = tinymce.get('emailSignature').getContent();

        this.http.post('../rest/currentUser/emailSignature', this.mailSignatureModel)
            .subscribe((data: any) => {
                if (data.errors) {
                    this.notify.error(data.errors);
                } else {
                    this.userEmailSignatures = data.emailSignatures;
                    this.mailSignatureModel = {
                        selected: -1,
                        htmlBody: '',
                        title: '',
                    };
                    tinymce.get('emailSignature').setContent('');
                    this.notify.success(this.translate.instant('lang.emailSignatureAdded'));
                }
            });
    }

    updateEmailSignature() {
        this.mailSignatureModel.htmlBody = tinymce.get('emailSignature').getContent();
        const id = this.userEmailSignatures[this.mailSignatureModel.selected].id;

        this.http.put('../rest/currentUser/emailSignature/' + id, this.mailSignatureModel)
            .subscribe((data: any) => {
                if (data.errors) {
                    this.notify.error(data.errors);
                } else {
                    this.userEmailSignatures[this.mailSignatureModel.selected].title = data.emailSignature.title;
                    this.userEmailSignatures[this.mailSignatureModel.selected].html_body = data.emailSignature.html_body;
                    this.notify.success(this.translate.instant('lang.emailSignatureUpdated'));
                }
            });
    }

    deleteEmailSignature() {
        let id: any = null;
        const dialogRef = this.dialog.open(ConfirmComponent, { panelClass: 'maarch-modal', autoFocus: false, disableClose: true, data: { title: `${this.translate.instant('lang.confirmDeleteMailSignature')}`, msg: this.translate.instant('lang.confirmAction') } });
        dialogRef.afterClosed().pipe(
            filter((data: string) => data === 'ok'),
            tap(() => id = this.userEmailSignatures[this.mailSignatureModel.selected].id),
            exhaustMap(() => this.http.delete('../rest/currentUser/emailSignature/' + id)),
            tap((data: any) => {
                this.userEmailSignatures = data.emailSignatures;
                this.mailSignatureModel = {
                    selected: -1,
                    htmlBody: '',
                    title: '',
                };
                tinymce.get('emailSignature').setContent('');
                this.notify.success(this.translate.instant('lang.emailSignatureDeleted'));
            }),
            catchError((err: any) => {
                this.notify.handleSoftErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    resetEmailSignature() {
        this.mailSignatureModel.selected = -1;
        tinymce.get('emailSignature').setContent('');
        this.mailSignatureModel.title = '';
    }

    changeEmailSignature(i: any) {
        this.mailSignatureModel.selected = i;
        tinymce.get('emailSignature').setContent(this.userEmailSignatures[i].html_body);
        this.mailSignatureModel.title = this.userEmailSignatures[i].title;
    }
}
