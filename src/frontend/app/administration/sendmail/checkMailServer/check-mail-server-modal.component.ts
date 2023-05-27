import { HttpClient } from '@angular/common/http';
import { Component, Inject, OnInit } from '@angular/core';
import { MAT_DIALOG_DATA, MatDialogRef } from '@angular/material/dialog';
import { TranslateService } from '@ngx-translate/core';
import { AuthService } from '@service/auth.service';
import { of } from 'rxjs';
import { catchError, finalize, tap } from 'rxjs/operators';

@Component({
    templateUrl: 'check-mail-server-modal.component.html',
    styleUrls: ['check-mail-server-modal.component.scss'],
})
export class CheckMailServerModalComponent implements OnInit {

    loading: boolean = true;

    serverConf: any = null;
    recipient: string = null;

    statusMsg: string = this.translate.instant('lang.emailSendInProgressShort');
    error: string = null;

    constructor(
        public http: HttpClient,
        public translate: TranslateService,
        @Inject(MAT_DIALOG_DATA) public data: any,
        public dialogRef: MatDialogRef<CheckMailServerModalComponent>,
        private authService: AuthService
    ) { }

    ngOnInit(): void {
        this.serverConf = this.data.serverConf;
        this.recipient = this.data.recipient;
        this.statusMsg = this.translate.instant('lang.emailSendInProgress', {0: this.recipient});
        this.testEmailServer();
    }

    testEmailServer() {
        const email = {
            'sender': { 'email': this.serverConf.from },
            'recipients': [this.recipient],
            'object': '[' + this.translate.instant('lang.doNotReply') + '] ' + this.translate.instant('lang.emailSendTest'),
            'status': 'EXPRESS',
            'body': this.translate.instant('lang.emailSendTest'),
            'isHtml': false
        };

        this.http.post('../rest/emails', email).pipe(
            tap((data: any) => {
                this.statusMsg = this.translate.instant('lang.emailSendSuccess', {0: this.recipient});
                this.authService.mailServerOnline = true;
                setTimeout(() => {
                    this.dialogRef.close('success');
                }, 2000);
            }),
            finalize(() => this.loading = false),
            catchError((err: any) => {
                this.authService.mailServerOnline = false;
                this.statusMsg = this.translate.instant('lang.emailSendFailed', {sender: this.serverConf.from, recipient: this.recipient});
                this.error = err.error.errors;
                return of(false);
            })
        ).subscribe();
    }
}
