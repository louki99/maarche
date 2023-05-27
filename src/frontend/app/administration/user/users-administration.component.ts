import { Component, OnInit, ViewChild, TemplateRef, ViewContainerRef } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { TranslateService } from '@ngx-translate/core';
import { MatDialog, MatDialogRef, MAT_DIALOG_DATA } from '@angular/material/dialog';
import { MatPaginator } from '@angular/material/paginator';
import { MatSort } from '@angular/material/sort';
import { NotificationService } from '@service/notification/notification.service';
import { HeaderService } from '@service/header.service';
import { AppService } from '@service/app.service';
import { FunctionsService } from '@service/functions.service';
import { of } from 'rxjs';
import { UsersImportComponent } from './import/users-import.component';
import { UsersExportComponent } from './export/users-export.component';
import { catchError, exhaustMap, filter, tap } from 'rxjs/operators';
import { AdministrationService } from '../administration.service';
import { UsersAdministrationRedirectModalComponent } from './redirect-modal/users-administration-redirect-modal.component';
import { ConfirmComponent } from '@plugins/modal/confirm.component';

@Component({
    templateUrl: 'users-administration.component.html',
    styleUrls: ['users-administration.component.scss']
})
export class UsersAdministrationComponent implements OnInit {

    @ViewChild('adminMenuTemplate', { static: true }) adminMenuTemplate: TemplateRef<any>;
    @ViewChild(MatPaginator, { static: false }) paginator: MatPaginator;
    @ViewChild(MatSort, { static: false }) sort: MatSort;

    dialogRef: MatDialogRef<any>;


    loading: boolean = true;
    updateListModel: boolean = true;
    updateListInstance: boolean = true;

    data: any[] = [];
    config: any = {};
    userDestRedirect: any = {};
    userDestRedirectModels: any[] = [];
    listinstances: any[] = [];
    quota: any = {};
    user: any = {};
    withWebserviceAccount: boolean = false;
    webserviceAccounts: any[] = [];
    noWebserviceAccounts: any[] = [];

    displayedColumns = ['id', 'user_id', 'lastname', 'firstname', 'status', 'mail', 'actions'];
    filterColumns = ['id', 'user_id', 'lastname', 'firstname', 'mail'];

    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        private notify: NotificationService,
        public dialog: MatDialog,
        public headerService: HeaderService,
        public appService: AppService,
        public functions: FunctionsService,
        public adminService: AdministrationService,
        private viewContainerRef: ViewContainerRef,
    ) { }

    ngOnInit(): void {
        this.headerService.setHeader(this.translate.instant('lang.administration') + ' ' + this.translate.instant('lang.users'));

        this.headerService.injectInSideBarLeft(this.adminMenuTemplate, this.viewContainerRef, 'adminMenu');

        this.user = this.headerService.user;

        this.getData();
    }

    getData() {
        this.webserviceAccounts = [];
        this.noWebserviceAccounts = [];
        this.data = [];
        this.http.get('../rest/users').pipe(
            tap((data: any) => {
                this.data = data['users'];
                this.data.forEach(element => {
                    element.statusLabel = this.translate.instant('lang.user' + element.status);
                    if (element.mode === 'rest') {
                        this.webserviceAccounts.push(element);
                    } else {
                        this.noWebserviceAccounts.push(element);
                    }
                });
                this.data = this.noWebserviceAccounts;
                this.quota = data['quota'];
                if (this.quota.actives > this.quota.userQuota) {
                    this.notify.error(this.translate.instant('lang.quotaExceeded'));
                }

                this.loading = false;
                setTimeout(() => {
                    this.adminService.setDataSource('admin_users', this.data, this.sort, this.paginator, this.filterColumns);
                }, 0);
            }),
            catchError((err: any) => {
                this.notify.handleSoftErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    activateUser(user: any) {
        const dialogRef = this.dialog.open(ConfirmComponent, { panelClass: 'maarch-modal', autoFocus: false, disableClose: true, data: { title: `${this.translate.instant('lang.authorize')} « ${user.user_id } »`, msg: this.translate.instant('lang.confirmAction') } });
        dialogRef.afterClosed().pipe(
            filter((data: string) => data === 'ok'),
            tap(() => user.status = 'OK'),
            exhaustMap(() => this.http.put('../rest/users/' + user.id, user)),
            tap(() => {
                this.notify.success(this.translate.instant('lang.userAuthorized'));
                this.updateQuota(user, 'activate');
            }),
            catchError((err: any) => {
                user.status = 'SPD';
                this.notify.handleSoftErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    updateQuota(user: any, mode: string) {
        if (mode === 'delete') {
            if (this.quota.userQuota && user.status === 'OK') {
                this.quota.actives--;
            } else if (this.quota.userQuota && user.status === 'SPD') {
                this.quota.inactives--;
            }
        } else if (mode === 'suspend') {
            if (this.quota.userQuota) {
                this.quota.inactives++;
                this.quota.actives--;
            }
        } else if (mode === 'activate') {
            if (this.quota.userQuota) {
                this.quota.inactives--;
                this.quota.actives++;
                if (this.quota.actives > this.quota.userQuota) {
                    this.notify.error(this.translate.instant('lang.quotaExceeded'));
                }
            }
        }
    }

    actionUserPrompt(user: any, mode: string) {
        user.actionMode = mode;

        this.dialogRef = this.dialog.open(UsersAdministrationRedirectModalComponent, { panelClass: 'maarch-modal', data: { user: user } });
        this.dialogRef.afterClosed().pipe(
            filter((res: any) => res === 'success'),
            tap((res: any) => {
                this.updateQuota(user, mode);
                if (user.actionMode === 'delete') {
                    for (const i in this.data) {
                        if (this.data[i].id == user.id) {
                            this.data.splice(Number(i), 1);
                        }
                    }
                    this.adminService.setDataSource('admin_users', this.data, this.sort, this.paginator, this.filterColumns);
                } else {
                    user.status = 'SPD';
                }
            })
        ).subscribe();
    }

    toggleWebserviceAccount() {
        this.withWebserviceAccount = !this.withWebserviceAccount;
        if (this.withWebserviceAccount) {
            this.data = this.webserviceAccounts;
        } else {
            this.data = this.noWebserviceAccounts;
        }
        this.adminService.setDataSource('admin_users', this.data, this.sort, this.paginator, this.filterColumns);
    }

    openUsersImportModal() {
        const dialogRef = this.dialog.open(UsersImportComponent, {
            disableClose: true,
            width: '99vw',
            maxWidth: '99vw',
            panelClass: 'maarch-full-height-modal'
        });

        dialogRef.afterClosed().pipe(
            filter((data: any) => data === 'success'),
            tap(() => {
                this.getData();
            }),
            catchError((err: any) => {
                this.notify.handleSoftErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    openUsersExportModal() {
        this.dialog.open(UsersExportComponent, { panelClass: 'maarch-modal', width: '800px', autoFocus: false });

    }
}
