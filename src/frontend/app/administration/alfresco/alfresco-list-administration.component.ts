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
    templateUrl: 'alfresco-list-administration.component.html',
    styleUrls: ['./alfresco-list-administration.component.scss']
})
export class AlfrescoListAdministrationComponent implements OnInit {

    @ViewChild('adminMenuTemplate', { static: true }) adminMenuTemplate: TemplateRef<any>;
    @ViewChild(MatPaginator, { static: false }) paginator: MatPaginator;
    @ViewChild(MatSort, { static: false }) sort: MatSort;

    alfrescoUrl: string = '';

    accounts: any[] = [];

    loading: boolean = false;

    displayedColumns = ['label', 'entitiesLabel', 'actions'];
    filterColumns = ['label', 'entitiesLabel'];

    dialogRef: MatDialogRef<any>;

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

    ngOnInit(): void {
        this.headerService.setHeader(this.translate.instant('lang.administration') + ' ' + this.translate.instant('lang.alfresco'));

        this.headerService.injectInSideBarLeft(this.adminMenuTemplate, this.viewContainerRef, 'adminMenu');

        this.loading = true;

        this.http.get('../rest/alfresco/configuration').pipe(
            filter((data: any) => !this.functions.empty(data.configuration)),
            tap((data: any) => {
                this.alfrescoUrl = data.configuration.uri;
            })
        ).subscribe();

        this.http.get('../rest/alfresco/accounts')
            .subscribe((data: any) => {
                this.accounts = data.accounts;
                this.loading = false;
                setTimeout(() => {
                    this.adminService.setDataSource('admin_alfresco', this.accounts, this.sort, this.paginator, this.filterColumns);
                }, 0);
            });
    }

    deleteAccount(id: number) {

        this.dialogRef = this.dialog.open(ConfirmComponent, { panelClass: 'maarch-modal', autoFocus: false, disableClose: true, data: { title: this.translate.instant('lang.delete'), msg: this.translate.instant('lang.confirmAction') } });

        this.dialogRef.afterClosed().pipe(
            filter((data: string) => data === 'ok'),
            exhaustMap(() => this.http.delete('../rest/alfresco/accounts/' + id)),
            tap(() => {
                this.accounts = this.accounts.filter((account: any) => account.id !== id);
                setTimeout(() => {
                    this.adminService.setDataSource('admin_alfresco', this.accounts, this.sort, this.paginator, this.filterColumns);
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
        this.http.put('../rest/alfresco/configuration', { uri: this.alfrescoUrl }).pipe(
            catchError((err: any) => {
                this.notify.handleSoftErrors(err);
                return of(false);
            })
        ).subscribe();
    }
}
