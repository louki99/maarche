import { Component, OnInit, ViewChild, TemplateRef, ViewContainerRef } from '@angular/core';
import { MatPaginator } from '@angular/material/paginator';
import { MatSort } from '@angular/material/sort';
import { TranslateService } from '@ngx-translate/core';
import { HttpClient } from '@angular/common/http';
import { NotificationService } from '@service/notification/notification.service';
import { HeaderService } from '@service/header.service';
import { AppService } from '@service/app.service';
import { FunctionsService } from '@service/functions.service';
import { AdministrationService } from '../administration.service';
import { MatDialog } from '@angular/material/dialog';
import { tap, catchError, filter, exhaustMap } from 'rxjs/operators';
import { ConfirmComponent } from '../../../plugins/modal/confirm.component';
import { of } from 'rxjs';

@Component({
    selector: 'app-registered-mail-list',
    templateUrl: './registered-mail-list.component.html',
    styleUrls: ['./registered-mail-list.component.scss']
})
export class RegisteredMailListComponent implements OnInit {

    @ViewChild('adminMenuTemplate', { static: true }) adminMenuTemplate: TemplateRef<any>;
    @ViewChild(MatPaginator, { static: false }) paginator: MatPaginator;
    @ViewChild(MatSort, { static: false }) sort: MatSort;

    subMenus: any[] = [
        {
            icon: 'fas fa-dolly-flatbed',
            route: '/administration/registeredMails',
            label: this.translate.instant('lang.registeredMailNumberRanges'),
            current: true
        },
        {
            icon: 'fas fa-warehouse',
            route: '/administration/issuingSites',
            label: this.translate.instant('lang.issuingSites'),
            current: false
        }
    ];

    parameters: any = {};

    loading: boolean = true;

    data: any[] = [];

    displayedColumns = ['trackerNumber', 'typeLabel', 'rangeNumber', 'currentNumber', 'status', 'fullness', 'actions'];
    filterColumns = ['trackerNumber', 'typeLabel', 'rangeNumber', 'currentNumber', 'fullness', 'statusLabel'];

    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        private notify: NotificationService,
        private headerService: HeaderService,
        public appService: AppService,
        public functions: FunctionsService,
        public adminService: AdministrationService,
        private viewContainerRef: ViewContainerRef,
        public dialog: MatDialog
    ) { }

    ngOnInit(): void {
        this.headerService.setHeader(this.translate.instant('lang.administration') + ' ' + this.translate.instant('lang.registeredMailNumberRanges'));

        this.headerService.injectInSideBarLeft(this.adminMenuTemplate, this.viewContainerRef, 'adminMenu');
        this.loading = false;
        this.getData();
    }

    getData() {
        this.data = [];

        this.http.get('../rest/registeredMail/ranges').pipe(
            tap((data: any) => {
                this.data = data['ranges'].map((item: any) => ({
                    ...item,
                    statusLabel : this.translate.instant('lang.registeredMail_' + item.status),
                    typeLabel : this.translate.instant('lang.registeredMail_' + item.registeredMailType),
                    rangeNumber : `${item.rangeStart} - ${item.rangeEnd}`,
                }));
                this.loading = false;
                setTimeout(() => {
                    this.adminService.setDataSource('admin_regitered_mail', this.data, this.sort, this.paginator, this.filterColumns);
                }, 0);
            }),
            catchError((err: any) => {
                this.notify.handleSoftErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    activate(row: any) {
        const dataTosend = JSON.parse(JSON.stringify(row));
        dataTosend.status = 'OK';

        const dialogRef = this.dialog.open(ConfirmComponent, { panelClass: 'maarch-modal', autoFocus: false, disableClose: true, data: { title: this.translate.instant('lang.activateRegisteredMailNumberRange'), msg: this.translate.instant('lang.registeredMailMsgActivate')} });

        dialogRef.afterClosed().pipe(
            filter((data: string) => data === 'ok'),
            exhaustMap(() => this.http.put(`../rest/registeredMail/ranges/${row.id}`, dataTosend)),
            tap(() => {
                this.data.forEach(item => {
                    if (item.status === 'OK' && item.registeredMailType === row.registeredMailType && item.siteId === row.siteId) {
                        item.status = 'END';
                        item.currentNumber  = null;
                        item.statusLabel = this.translate.instant('lang.registeredMail_' + item.status);

                    } else if (item.id === row.id) {
                        item.status = 'OK';
                        item.currentNumber  = item.rangeStart;
                        item.statusLabel = this.translate.instant('lang.registeredMail_' + item.status);
                    }
                });
                setTimeout(() => {
                    this.adminService.setDataSource('admin_regitered_mail', this.data, this.sort, this.paginator, this.filterColumns);
                }, 0);
                this.notify.success(this.translate.instant('lang.registeredMailNumberRangesActivated'));
            }),
            catchError((err: any) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    stop(row: any) {
        const dataTosend = JSON.parse(JSON.stringify(row));
        dataTosend.status = 'END';

        const dialogRef = this.dialog.open(ConfirmComponent, { panelClass: 'maarch-modal', autoFocus: false, disableClose: true, data: { title: this.translate.instant('lang.suspend'), msg: 'En clôturant la plage, vous ne pourrez plus utiliser de recommandé de ce type tant que vous n\'en n\'aurez pas activé une autre.' } });

        dialogRef.afterClosed().pipe(
            filter((data: string) => data === 'ok'),
            exhaustMap(() => this.http.put(`../rest/registeredMail/ranges/${row.id}`, dataTosend)),
            tap(() => {
                row.status = 'END';
                row.statusLabel = this.translate.instant('lang.registeredMail_' + row.status);
                setTimeout(() => {
                    this.adminService.setDataSource('admin_regitered_mail', this.data, this.sort, this.paginator, this.filterColumns);
                }, 0);
                this.notify.success(this.translate.instant('lang.registeredMailNumberRangesClosed'));
            }),
            catchError((err: any) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    delete(row: any) {
        const dialogRef = this.dialog.open(ConfirmComponent, { panelClass: 'maarch-modal', autoFocus: false, disableClose: true, data: { title: this.translate.instant('lang.delete'), msg: this.translate.instant('lang.confirmAction') } });

        dialogRef.afterClosed().pipe(
            filter((data: string) => data === 'ok'),
            exhaustMap(() => this.http.delete(`../rest/registeredMail/ranges/${row.id}`)),
            tap(() => {
                this.data = this.data.filter((item: any) => item.id !== row.id);
                setTimeout(() => {
                    this.adminService.setDataSource('admin_regitered_mail', this.data, this.sort, this.paginator, this.filterColumns);
                }, 0);
                this.notify.success(this.translate.instant('lang.registeredMailNumberRangesRemoved'));
            }),
            catchError((err: any) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }

}
