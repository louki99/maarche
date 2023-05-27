import { Component, OnInit, ViewChild, TemplateRef, ViewContainerRef } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { TranslateService } from '@ngx-translate/core';
import { NotificationService } from '@service/notification/notification.service';
import { HeaderService } from '@service/header.service';
import { MatPaginator } from '@angular/material/paginator';
import { MatSort } from '@angular/material/sort';
import { AppService } from '@service/app.service';
import { FunctionsService } from '@service/functions.service';
import { AdministrationService } from '../administration.service';
import { ConfirmComponent } from '@plugins/modal/confirm.component';
import { MatDialog } from '@angular/material/dialog';
import { catchError, exhaustMap, filter, tap } from 'rxjs/operators';
import { of } from 'rxjs';

@Component({
    templateUrl: 'parameters-administration.component.html'
})
export class ParametersAdministrationComponent implements OnInit {

    @ViewChild('adminMenuTemplate', { static: true }) adminMenuTemplate: TemplateRef<any>;
    @ViewChild(MatPaginator, { static: false }) paginator: MatPaginator;
    @ViewChild(MatSort, { static: false }) sort: MatSort;

    parameters: any = {};

    loading: boolean = false;

    displayedColumns = ['id', 'description', 'value', 'actions'];
    filterColumns = ['id', 'description', 'value'];

    hiddenParameters = ['homepage_message', 'loginpage_message', 'traffic_record_summary_sheet', 'bindingDocumentFinalAction',
        'nonBindingDocumentFinalAction', 'minimumVisaRole', 'maximumSignRole', 'workflowSignatoryRole'];

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
        this.headerService.setHeader(this.translate.instant('lang.administration') + ' ' + this.translate.instant('lang.parameters'));

        this.headerService.injectInSideBarLeft(this.adminMenuTemplate, this.viewContainerRef, 'adminMenu');

        this.loading = true;

        this.http.get('../rest/parameters')
            .subscribe((data: any) => {
                this.parameters = data.parameters.filter((item: any) => this.hiddenParameters.indexOf(item.id) === -1);
                this.loading = false;
                setTimeout(() => {
                    this.adminService.setDataSource('admin_parameters', this.parameters, this.sort, this.paginator, this.filterColumns);
                }, 0);
            });
    }

    deleteParameter(paramId: string) {
        const dialogRef = this.dialog.open(ConfirmComponent, { panelClass: 'maarch-modal', autoFocus: false, disableClose: true, data: { title: `${this.translate.instant('lang.deleteParameterConfirm')}`, msg: this.translate.instant('lang.confirmAction') } });
        dialogRef.afterClosed().pipe(
            filter((data: string) => data === 'ok'),
            exhaustMap(() => this.http.delete('../rest/parameters/' + paramId)),
            tap((data: any) => {
                this.parameters = data.parameters.filter((item: any) => this.hiddenParameters.indexOf(item.id) === -1);
                this.adminService.setDataSource('admin_parameters', this.parameters, this.sort, this.paginator, this.filterColumns);
                this.notify.success(this.translate.instant('lang.parameterDeleted'));
            }),
            catchError((err: any) => {
                this.notify.handleSoftErrors(err);
                return of(false);
            })
        ).subscribe();
    }
}
