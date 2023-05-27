import { Component, ViewChild, OnInit, TemplateRef, ViewContainerRef } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { TranslateService } from '@ngx-translate/core';
import { NotificationService } from '@service/notification/notification.service';
import { MatPaginator } from '@angular/material/paginator';
import { MatSidenav } from '@angular/material/sidenav';
import { MatSort } from '@angular/material/sort';
import { HeaderService } from '@service/header.service';
import { AppService } from '@service/app.service';
import { FunctionsService } from '@service/functions.service';
import { AdministrationService } from '../administration.service';
import { ConfirmComponent } from '@plugins/modal/confirm.component';
import { MatDialog } from '@angular/material/dialog';
import { catchError, exhaustMap, filter, tap } from 'rxjs/operators';
import { of } from 'rxjs';


@Component({
    templateUrl: 'actions-administration.component.html'
})

export class ActionsAdministrationComponent implements OnInit {

    @ViewChild('snav2', { static: true }) public sidenavRight: MatSidenav;
    @ViewChild('adminMenuTemplate', { static: true }) adminMenuTemplate: TemplateRef<any>;
    @ViewChild(MatPaginator, { static: false }) paginator: MatPaginator;
    @ViewChild(MatSort, { static: false }) sort: MatSort;

    search: string = null;

    actions: any[] = [];
    titles: any[] = [];

    loading: boolean = false;

    displayedColumns = ['id', 'label_action', 'history', 'actions'];
    filterColumns = ['id', 'label_action'];

    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        private notify: NotificationService,
        private headerService: HeaderService,
        public appService: AppService,
        public adminService: AdministrationService,
        public functions: FunctionsService,
        private viewContainerRef: ViewContainerRef,
        public dialog: MatDialog
    ) { }

    ngOnInit(): void {
        this.headerService.injectInSideBarLeft(this.adminMenuTemplate, this.viewContainerRef, 'adminMenu');

        this.loading = true;

        this.http.get('../rest/actions')
            .subscribe((data) => {
                this.actions = data['actions'];
                this.headerService.setHeader(this.translate.instant('lang.administration') + ' ' + this.translate.instant('lang.actions'));
                this.loading = false;
                setTimeout(() => {
                    this.adminService.setDataSource('admin_actions', this.actions, this.sort, this.paginator, this.filterColumns);
                }, 0);
            }, (err) => {
                this.notify.handleErrors(err);
            });
    }

    deleteAction(action: any) {
        const dialogRef = this.dialog.open(ConfirmComponent, { panelClass: 'maarch-modal', autoFocus: false, disableClose: true, data: { title: `${this.translate.instant('lang.delete')} «${action.label_action}»`, msg: this.translate.instant('lang.confirmAction') } });
        dialogRef.afterClosed().pipe(
            filter((data: string) => data === 'ok'),
            exhaustMap(() => this.http.delete('../rest/actions/' + action.id)),
            tap((data: any) => {
                this.actions = data.actions;
                this.adminService.setDataSource('admin_actions', this.actions, this.sort, this.paginator, this.filterColumns);
                this.notify.success(this.translate.instant('lang.actionDeleted'));
            }),
            catchError((err: any) => {
                this.notify.handleSoftErrors(err);
                return of(false);
            })
        ).subscribe();
    }
}
