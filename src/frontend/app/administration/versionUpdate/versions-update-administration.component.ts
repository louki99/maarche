import { Component, OnInit, ViewChild } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { TranslateService } from '@ngx-translate/core';
import { MatDialog, MatDialogRef } from '@angular/material/dialog';
import { MatSidenav } from '@angular/material/sidenav';
import { HeaderService } from '@service/header.service';
import { tap, catchError, exhaustMap, filter } from 'rxjs/operators';
import { NotificationService } from '@service/notification/notification.service';
import { AlertComponent } from '../../../plugins/modal/alert.component';
import { ConfirmComponent } from '../../../plugins/modal/confirm.component';
import { AppService } from '@service/app.service';
import { of } from 'rxjs';

@Component({
    templateUrl: 'versions-update-administration.component.html',
    styleUrls: ['versions-update-administration.component.scss'],
})
export class VersionsUpdateAdministrationComponent implements OnInit {

    @ViewChild('snav2', { static: true }) public sidenavRight: MatSidenav;


    loading: boolean = false;
    updateInprogress: boolean = false;
    dialogRef: MatDialogRef<any>;

    versions: any = {};

    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        private headerService: HeaderService,
        private notify: NotificationService,
        public dialog: MatDialog,
        public appService: AppService
    ) { }

    ngOnInit(): void {
        this.headerService.setHeader(this.translate.instant('lang.updateVersionControl'));

        this.loading = true;

        this.http.get('../rest/versionsUpdate').pipe(
            tap((data: any) => {
                this.versions = data;
                this.loading = false;
            }),
            catchError(err => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    updateVersionAccess() {

        this.dialogRef = this.dialog.open(ConfirmComponent, { panelClass: 'maarch-modal', data: { title: this.translate.instant('lang.confirm') + ' ?', msg: this.translate.instant('lang.updateInfo') } });
        this.dialogRef.afterClosed().pipe(
            filter((data) => {
                this.dialogRef = null;

                if (data === 'ok') {
                    this.updateInprogress = true;
                    return true;
                } else {
                    this.updateInprogress = false;
                    return false;
                }
            }),
            exhaustMap(() => this.http.put('../rest/versionsUpdate', { tag : this.versions.lastAvailableMinorVersion })),
            tap(() => {
                this.dialogRef = this.dialog.open(AlertComponent, { panelClass: 'maarch-modal', autoFocus: false, disableClose: true, data: { title: this.translate.instant('lang.updateOk'), msg: this.translate.instant('lang.saveInDocserversInfo') } });
            }),
            exhaustMap(() => this.dialogRef.afterClosed()),
            tap(() => {
                this.dialogRef = null;
                window.location.reload();
            }),
            catchError(err => {
                this.dialogRef = this.dialog.open(AlertComponent, { panelClass: 'maarch-modal', autoFocus: false, disableClose: true, data: { title: this.translate.instant('lang.updateKO'), msg: this.translate.instant('lang.saveInDocserversInfo') } });
                this.notify.handleErrors(err);
                return of(false);
            }),
            tap(() => {
                this.updateInprogress = false;
            }),

        ).subscribe();

    }
}
