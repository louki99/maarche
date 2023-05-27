import { Component, OnInit, ViewChild, TemplateRef, ViewContainerRef } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { TranslateService } from '@ngx-translate/core';
import { NotificationService } from '@service/notification/notification.service';
import { HeaderService } from '@service/header.service';
import { MatPaginator } from '@angular/material/paginator';
import { MatSort } from '@angular/material/sort';
import { AppService } from '@service/app.service';
import { FunctionsService } from '@service/functions.service';
import { of } from 'rxjs';
import { ConfirmComponent } from '../../../plugins/modal/confirm.component';
import { MatDialog, MatDialogRef } from '@angular/material/dialog';
import { AdministrationService } from '../administration.service';
import { catchError, exhaustMap, filter, tap } from 'rxjs/operators';

@Component({
    templateUrl: 'multigest-list-administration.component.html',
    styleUrls: ['./multigest-list-administration.component.scss']
})
export class MultigestListAdministrationComponent implements OnInit {

    @ViewChild('adminMenuTemplate', { static: true }) adminMenuTemplate: TemplateRef<any>;
    @ViewChild(MatPaginator, { static: false }) paginator: MatPaginator;
    @ViewChild(MatSort, { static: false }) sort: MatSort;

    multigestUrl: string = '';

    accounts: any[] = [];

    loading: boolean = false;

    displayedColumns = ['label', 'entitiesLabel', 'actions'];
    filterColumns = ['label', 'entitiesLabel'];

    dialogRef: MatDialogRef<any>;

    multigestUrlClone: string = '';

    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        private notify: NotificationService,
        private headerService: HeaderService,
        public appService: AppService,
        private dialog: MatDialog,
        public functions: FunctionsService,
        public adminService: AdministrationService,
        private viewContainerRef: ViewContainerRef
    ) { }

    async ngOnInit(): Promise<void> {
        this.headerService.setHeader(this.translate.instant('lang.administration') + ' ' + this.translate.instant('lang.multigest'));

        this.headerService.injectInSideBarLeft(this.adminMenuTemplate, this.viewContainerRef, 'adminMenu');

        this.loading = true;

        this.getApiUri();

        await this.getAccounts();

        this.loading = false;

        setTimeout(() => {
            this.adminService.setDataSource('admin_multigest', this.accounts, this.sort, this.paginator, this.filterColumns);
        }, 0);

    }

    getApiUri() {
        this.http.get('../rest/multigest/configuration').pipe(
            filter((data: any) => !this.functions.empty(data.configuration)),
            tap((data: any) => {
                this.multigestUrl = data.configuration.uri;
            }),
            catchError((err: any) => {
                this.notify.handleSoftErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    getAccounts() {
        return new Promise((resolve) => {
            this.http.get('../rest/multigest/accounts').pipe(
                tap((data: any) => {
                    this.accounts = data.accounts;
                    resolve(true);
                }),
                catchError((err: any) => {
                    this.notify.handleSoftErrors(err);
                    return of(false);
                })
            ).subscribe();
        });
    }

    deleteAccount(id: number) {

        this.dialogRef = this.dialog.open(ConfirmComponent, { panelClass: 'maarch-modal', autoFocus: false, disableClose: true, data: { title: this.translate.instant('lang.delete'), msg: this.translate.instant('lang.confirmAction') } });

        this.dialogRef.afterClosed().pipe(
            filter((data: string) => data === 'ok'),
            exhaustMap(() => this.http.delete('../rest/multigest/accounts/' + id)),
            tap(() => {
                this.accounts = this.accounts.filter((account: any) => account.id !== id);
                setTimeout(() => {
                    this.adminService.setDataSource('admin_multigest', this.accounts, this.sort, this.paginator, this.filterColumns);
                }, 0);
                this.notify.success(this.translate.instant('lang.accountDeleted'));
            }),
            catchError((err: any) => {
                this.notify.handleSoftErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    saveUrl() {
        if (JSON.stringify(this.multigestUrl) !== JSON.stringify(this.multigestUrlClone) || this.functions.empty(this.multigestUrl)) {
            this.http.put('../rest/multigest/configuration', { uri: this.multigestUrl }).pipe(
                tap(() => {
                    this.multigestUrlClone = JSON.parse(JSON.stringify(this.multigestUrl));
                    this.notify.success(this.translate.instant('lang.dataUpdated'));
                }),
                catchError((err: any) => {
                    this.notify.handleSoftErrors(this.translate.instant('lang.multigestUriIsEmpty'));
                    return of(false);
                })
            ).subscribe();
        }
    }
}
