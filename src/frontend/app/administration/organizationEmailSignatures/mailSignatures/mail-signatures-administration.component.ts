import { Component, Input, OnDestroy, OnInit } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { TranslateService } from '@ngx-translate/core';
import { NotificationService } from '@service/notification/notification.service';
import { HeaderService } from '@service/header.service';
import { AppService } from '@service/app.service';
import { catchError, exhaustMap, filter, map, tap } from 'rxjs/operators';
import { of } from 'rxjs';
import { ConfirmComponent } from '@plugins/modal/confirm.component';
import { MatDialog } from '@angular/material/dialog';

declare let tinymce: any;

@Component({
    selector: 'app-mail-signatures-administration',
    templateUrl: 'mail-signatures-administration.component.html',
    styleUrls: ['mail-signatures-administration.component.scss']
})
export class MailSignaturesAdministrationComponent implements OnInit, OnDestroy {

    @Input() mode: 'private' | 'public' = 'private';

    loading: boolean = false;
    addMode: boolean = false;

    route: string = '';

    newSignature: any = {
        label: '',
        content: ''
    };

    signatures: any[] = [];
    signaturesClone: any[] = [];

    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        private notify: NotificationService,
        private headerService: HeaderService,
        public appService: AppService,
        public dialog: MatDialog,
    ) { }

    ngOnInit(): void {
        this.route = this.mode === 'private' ? '../rest/currentUser/emailSignatures' : '../rest/configurations/admin_organization_email_signatures';

        this.getSignatures();
    }

    ngOnDestroy(): void {
        tinymce.remove();
    }

    getSignatures() {
        return new Promise((resolve) => {
            this.http.get(this.route).pipe(
                map((data: any) => {
                    if (this.mode === 'public') {
                        data = data.configuration.value.signatures.map((sign: any, index: number) => ({
                            id: index + 1,
                            label: sign.label,
                            content: sign.content
                        }));
                    } else {
                        data = data.emailSignatures.map((sign: any) => ({
                            id: sign.id,
                            label: sign.label,
                            content: sign.content,
                            public: sign.public
                        }));
                    }
                    return data;
                }),
                tap((data: any) => {
                    this.signatures = data;
                    this.signaturesClone = JSON.parse(JSON.stringify(this.signatures));
                    resolve(true);
                }),
                catchError((err: any) => {
                    this.notify.handleErrors(err);
                    return of(false);
                })
            ).subscribe();
        });
    }

    initMce(id: string = '') {
        tinymce.remove('textarea#emailSignature' + id);
        // LOAD EDITOR TINYMCE for MAIL SIGN
        tinymce.baseURL = '../node_modules/tinymce';
        tinymce.suffix = '.min';
        tinymce.init({
            selector: 'textarea#emailSignature' + id,
            statusbar: false,
            language: this.translate.instant('lang.langISO').replace('-', '_'),
            language_url: `../node_modules/tinymce-i18n/langs/${this.translate.instant('lang.langISO').replace('-', '_')}.js`,
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

    switchMode() {
        this.addMode = !this.addMode;
        if (!this.addMode) {
            tinymce.remove('textarea#emailSignature');
        } else {
            setTimeout(() => {
                this.initMce();
            }, 0);
        }
    }

    editSignature(index: number) {
        this.signatures[index].editMode = true;
        setTimeout(() => {
            this.initMce(this.signatures[index].id);
        }, 0);
    }

    closeEditSignature(index: number) {
        tinymce.remove('textarea#emailSignature' + this.signatures[index].id);
        this.signatures[index].editMode = false;
        this.signatures[index] = JSON.parse(JSON.stringify(this.signaturesClone[index]));
    }

    createSignature() {
        if (this.mode === 'public') {
            this.createPublicSignature();
        } else {
            this.createPrivateSignature();
        }
    }

    createPublicSignature() {
        this.newSignature.id = this.signatures.length + 1;
        this.newSignature.content = tinymce.get('emailSignature').getContent();

        const formatedSignatures = this.signatures.map((sign: any) => this.formatSignature(sign));

        formatedSignatures.push(this.newSignature);

        this.http.put(this.route, {signatures : formatedSignatures}).pipe(
            tap((data: any) => {
                this.signatures.push(this.newSignature);
                this.addMode = false;
                this.signaturesClone = JSON.parse(JSON.stringify(this.signatures));
                this.newSignature = {
                    label: '',
                    content: ''
                };
                this.notify.success(this.translate.instant('lang.signatureAdded'));
            }),
            catchError((err: any) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    createPrivateSignature() {
        this.newSignature.content = tinymce.get('emailSignature').getContent();

        this.http.post(this.route, this.formatSignature(this.newSignature)).pipe(
            tap((data: any) => {
                this.newSignature.id =  data.id;
                this.signatures.push(this.newSignature);
                this.addMode = false;
                this.signaturesClone = JSON.parse(JSON.stringify(this.signatures));
                this.newSignature = {
                    title: '',
                    htmlBody: ''
                };
                this.notify.success(this.translate.instant('lang.signatureAdded'));
            }),
            catchError((err: any) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    saveSignature(index: number) {
        if (this.mode === 'public') {
            this.savePublicSignature(index);
        } else {
            this.savePrivateSignature(index);
        }
        this.signatures[index].content = tinymce.get('emailSignature' + this.signatures[index].id).getContent();
    }

    savePublicSignature(index: number) {
        this.signatures[index].content = tinymce.get('emailSignature' + this.signatures[index].id).getContent();

        const formatedSignatures = this.signatures.map((sign: any) => this.formatSignature(sign));

        this.http.put(this.route, {signatures : formatedSignatures}).pipe(
            tap(() => {
                this.notify.success(this.translate.instant('lang.signatureUpdated'));
                tinymce.remove('textarea#emailSignature' + this.signatures[index].id);
                this.signatures[index].editMode = false;
                this.signaturesClone = JSON.parse(JSON.stringify(this.signatures));
            }),
            catchError((err: any) => {
                this.signatures = JSON.parse(JSON.stringify(this.signaturesClone));
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    savePrivateSignature(index: number) {
        this.signatures[index].content = tinymce.get('emailSignature' + this.signatures[index].id).getContent();
        const formatedSignatures = this.signatures.filter((signature: any) => signature.id === this.signatures[index].id).map((sign: any) => this.formatSignature(sign))[0];

        this.http.put(`../rest/currentUser/emailSignature/${this.signatures[index].id}`, formatedSignatures).pipe(
            tap(() => {
                this.notify.success(this.translate.instant('lang.signatureUpdated'));
                tinymce.remove('textarea#emailSignature' + this.signatures[index].id);
                this.signatures[index].editMode = false;
                this.signaturesClone = JSON.parse(JSON.stringify(this.signatures));
            }),
            catchError((err: any) => {
                this.signatures = JSON.parse(JSON.stringify(this.signaturesClone));
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }


    deleteSignature(index: number) {
        if (this.mode === 'public') {
            this.deletePublicSignature(index);
        } else {
            this.deletePrivateSignature(index);
        }
    }

    deletePrivateSignature(index: number) {
        const dialogRef = this.dialog.open(ConfirmComponent, { panelClass: 'maarch-modal', autoFocus: false, disableClose: true, data: { title: `${this.translate.instant('lang.delete')} "${this.signatures[index].label}"`, msg: this.translate.instant('lang.confirmAction') } });
        dialogRef.afterClosed().pipe(
            filter((data: string) => data === 'ok'),
            exhaustMap(() => this.http.delete(`../rest/currentUser/emailSignature/${this.signatures[index].id}`)),
            tap(() => {
                this.signatures.splice(index, 1);
                this.signaturesClone.splice(index, 1);
                this.notify.success(this.translate.instant('lang.signatureDeleted'));
            }),
            catchError((err: any) => {
                this.notify.handleSoftErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    deletePublicSignature(index: number) {
        const dialogRef = this.dialog.open(ConfirmComponent, { panelClass: 'maarch-modal', autoFocus: false, disableClose: true, data: { title: `${this.translate.instant('lang.delete')} "${this.signatures[index].label}"`, msg: this.translate.instant('lang.confirmAction') } });
        dialogRef.afterClosed().pipe(
            filter((data: string) => data === 'ok'),
            tap(() => {
                this.signatures.splice(index, 1);
            }),
            exhaustMap(() => this.http.put(this.route, {signatures : this.signatures})),
            tap(() => {
                this.signaturesClone.splice(index, 1);
                this.notify.success(this.translate.instant('lang.signatureDeleted'));
            }),
            catchError((err: any) => {
                this.signatures =  JSON.parse(JSON.stringify(this.signaturesClone));
                this.notify.handleSoftErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    formatSignature(signature: any) {
        if (this.mode === 'private') {
            return {
                title: signature.label,
                htmlBody: signature.content
            };
        } else {
            return {
                label: signature.label,
                content: signature.content
            };
        }
    }
}
