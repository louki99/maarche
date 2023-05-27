import { Component, OnInit, Inject, ViewChild } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { TranslateService } from '@ngx-translate/core';
import { NotificationService } from '@service/notification/notification.service';
import { MatDialog, MAT_DIALOG_DATA, MatDialogRef } from '@angular/material/dialog';
import { catchError, filter, exhaustMap, tap } from 'rxjs/operators';
import { FunctionsService } from '@service/functions.service';
import { ContactService } from '@service/contact.service';
import { AppService } from '@service/app.service';
import { ConfirmComponent } from '@plugins/modal/confirm.component';
import { PrivilegeService } from '@service/privileges.service';
import { HeaderService } from '@service/header.service';
import { of } from 'rxjs';
import { MailEditorComponent } from '@plugins/mail-editor/mail-editor.component';

@Component({
    selector: 'app-sent-resource-page',
    templateUrl: 'sent-resource-page.component.html',
    styleUrls: ['sent-resource-page.component.scss'],
    providers: [ContactService, AppService],
})
export class SentResourcePageComponent implements OnInit {

    @ViewChild('appMailEditor', { static: false }) appMailEditor: MailEditorComponent;

    loading: boolean = true;

    paperArContent: string = null;

    constructor(
        public http: HttpClient,
        private notify: NotificationService,
        @Inject(MAT_DIALOG_DATA) public data: any,
        public dialog: MatDialog,
        public dialogRef: MatDialogRef<SentResourcePageComponent>,
        public functions: FunctionsService,
        private contactService: ContactService,
        public privilegeService: PrivilegeService,
        public headerService: HeaderService,
        public translate: TranslateService
    ) { }

    async ngOnInit(): Promise<void> {

        this.loading = false;
    }

    async closeModal() {
        this.appMailEditor.loading = true;
        const res = await this.appMailEditor.saveDraft();
        this.dialogRef.close(res === true ? 'success' : '');
    }

    async onSubmit() {
        this.appMailEditor.emailStatus = 'WAITING';

        if (this.data.emailId === null) {
            if (!this.appMailEditor.isAllEmailRightFormat()) {
                this.notify.error(this.translate.instant('lang.badEmailsFormat'));
            } else {
                if (this.appMailEditor.emailSubject === '') {
                    const dialogRef = this.dialog.open(ConfirmComponent, { panelClass: 'maarch-modal', autoFocus: false, disableClose: true, data: { title: this.translate.instant('lang.confirm'), msg: this.translate.instant('lang.warnEmptySubject') } });

                    dialogRef.afterClosed().pipe(
                        filter((data: string) => data === 'ok'),
                        tap(async () => {
                            await this.appMailEditor.createEmail();
                            this.notify.success(`${this.translate.instant('lang.sendingEmail')}...`);
                            this.dialogRef.close('success');
                        })
                    ).subscribe();
                } else {
                    await this.appMailEditor.createEmail();
                    this.notify.success(`${this.translate.instant('lang.sendingEmail')}...`);
                    this.dialogRef.close('success');
                }
            }
        } else {
            await this.appMailEditor.updateEmail();
            this.notify.success(`${this.translate.instant('lang.sendingEmail')}...`);
            this.dialogRef.close('success');
        }
    }

    deleteEmail() {
        const dialogRef = this.dialog.open(ConfirmComponent, { panelClass: 'maarch-modal', autoFocus: false, disableClose: true, data: { title: this.translate.instant('lang.delete'), msg: this.translate.instant('lang.confirmAction') } });

        dialogRef.afterClosed().pipe(
            filter((data: string) => data === 'ok'),
            exhaustMap(() => this.http.delete(`../rest/emails/${this.data.emailId}`)),
            tap(() => {
                this.notify.success(this.translate.instant('lang.emailDeleted'));
                this.dialogRef.close('success');
            }),
            catchError((err) => {
                this.notify.handleSoftErrors(err);
                return of(false);
            })
        ).subscribe();
    }
}
