import { Component, OnInit } from '@angular/core';
import { TranslateService } from '@ngx-translate/core';
import { MatDialogRef } from '@angular/material/dialog';
import { HttpClient } from '@angular/common/http';
import { NotificationService } from '@service/notification/notification.service';
import { of } from 'rxjs';
import { catchError, tap } from 'rxjs/operators';

@Component({
    templateUrl: 'addin-outlook-configuration-modal.component.html',
    styleUrls: ['addin-outlook-configuration-modal.component.scss'],
})
export class AddinOutlookConfigurationModalComponent implements OnInit {

    outlookPassword: string = '';
    hidePassword: boolean = true;

    constructor(
        public http: HttpClient,
        private notify: NotificationService,
        public translate: TranslateService,
        public dialogRef: MatDialogRef<AddinOutlookConfigurationModalComponent>
    ) { }

    ngOnInit(): void { }

    getManifest() {
        this.http.get('../rest/plugins/outlook/manifest', { responseType: 'blob' }).pipe(
            tap((data: any) => {
                const downloadLink = document.createElement('a');
                downloadLink.href = window.URL.createObjectURL(data);
                downloadLink.setAttribute('download', 'manifest.xml');
                document.body.appendChild(downloadLink);
                downloadLink.click();
            }),
            catchError((err: any) => {
                this.notify.handleBlobErrors(err);
                this.dialogRef.close();
                return of(false);
            })
        ).subscribe();
    }

    savePassword() {
        this.http.put('../rest/plugins/outlook/password', { outlookPassword : this.outlookPassword } ).pipe(
            tap((data: any) => {
                this.notify.success(this.translate.instant('lang.passwordUpdated'));
            }),
            catchError((err: any) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }
}
