import { Component, OnInit, ViewChild, ViewContainerRef, TemplateRef } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { TranslateService } from '@ngx-translate/core';
import { MatPaginator } from '@angular/material/paginator';
import { MatSidenav } from '@angular/material/sidenav';
import { MatSort } from '@angular/material/sort';
import { NotificationService } from '@service/notification/notification.service';
import { HeaderService } from '@service/header.service';
import { AppService } from '@service/app.service';
import { FunctionsService } from '@service/functions.service';
import { AdministrationService } from '../administration.service';
import { catchError, tap, finalize, filter, exhaustMap } from 'rxjs/operators';
import { of } from 'rxjs';
import { ConfirmComponent } from '@plugins/modal/confirm.component';
import { MatDialog } from '@angular/material/dialog';

@Component({
    templateUrl: 'baskets-administration.component.html'
})
export class BasketsAdministrationComponent implements OnInit {

    @ViewChild('snav2', { static: true }) public sidenavRight: MatSidenav;
    @ViewChild('adminMenuTemplate', { static: true }) adminMenuTemplate: TemplateRef<any>;
    @ViewChild(MatPaginator, { static: false }) paginator: MatPaginator;
    @ViewChild(MatSort, { static: false }) sort: MatSort;

    loading: boolean = false;

    baskets: any[] = [];
    basketsOrder: any[] = [];

    displayedColumns = ['basket_id', 'basket_name', 'basket_desc', 'actions'];
    filterColumns = ['basket_id', 'basket_name', 'basket_desc'];

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
        this.headerService.injectInSideBarLeft(this.adminMenuTemplate, this.viewContainerRef, 'adminMenu');
        this.headerService.setHeader(this.translate.instant('lang.administration') + ' ' + this.translate.instant('lang.baskets'));

        this.loading = true;

        this.getSortedBasket();

        this.http.get('../rest/baskets')
            .subscribe((data: any) => {
                this.baskets = data['baskets'];
                this.loading = false;
                setTimeout(() => {
                    this.adminService.setDataSource('admin_baskets', this.baskets, this.sort, this.paginator, this.filterColumns);
                }, 0);
            }, (err) => {
                this.notify.handleErrors(err);
            });
    }

    getSortedBasket() {
        this.http.get('../rest/sortedBaskets').pipe(
            tap((dataSort: any) => {
                this.basketsOrder = dataSort['baskets'];
            }),
            catchError((err: any) => {
                this.notify.handleSoftErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    delete(basket: any) {
        const dialogRef = this.dialog.open(ConfirmComponent, { panelClass: 'maarch-modal', autoFocus: false, disableClose: true, data: { title: `${this.translate.instant('lang.delete')} « ${basket['basket_name']} »`, msg: this.translate.instant('lang.confirmAction') } });
        dialogRef.afterClosed().pipe(
            filter((data: string) => data === 'ok'),
            exhaustMap(() => this.http.delete('../rest/baskets/' + basket['basket_id'])),
            tap((data: any) => {
                this.notify.success(this.translate.instant('lang.basketDeleted'));
                this.baskets = data['baskets'];
                this.adminService.setDataSource('admin_baskets', this.baskets, this.sort, this.paginator, this.filterColumns);
                this.getSortedBasket();
            }),
            catchError((err: any) => {
                this.notify.handleSoftErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    updateBasketOrder(currentBasket: any) {
        this.http.put('../rest/sortedBaskets/' + currentBasket.basket_id, this.basketsOrder)
            .subscribe((data: any) => {
                this.baskets = data['baskets'];
                this.notify.success(this.translate.instant('lang.modificationSaved'));
            }, (err: any) => {
                this.notify.error(err.error.errors);
            });
    }
}
