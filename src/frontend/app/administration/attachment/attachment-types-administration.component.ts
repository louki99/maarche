import { Component, OnInit, ViewChild, TemplateRef, ViewContainerRef } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { TranslateService } from '@ngx-translate/core';
import { NotificationService } from '@service/notification/notification.service';
import { MatPaginator } from '@angular/material/paginator';
import { MatSort } from '@angular/material/sort';
import { HeaderService } from '@service/header.service';
import { AppService } from '@service/app.service';
import { tap, finalize, filter, exhaustMap, catchError, map } from 'rxjs/operators';
import { ConfirmComponent } from '../../../plugins/modal/confirm.component';
import { MatDialog } from '@angular/material/dialog';
import { FunctionsService } from '@service/functions.service';
import { of } from 'rxjs';
import { AdministrationService } from '../administration.service';

@Component({
    templateUrl: 'attachment-types-administration.component.html'
})
export class AttachmentTypesAdministrationComponent implements OnInit {

    @ViewChild('adminMenuTemplate', { static: true }) adminMenuTemplate: TemplateRef<any>;
    @ViewChild(MatPaginator, { static: false }) paginator: MatPaginator;
    @ViewChild(MatSort, { static: false }) sort: MatSort;

    loading: boolean = true;

    attachmentsTypes: any[] = [];
    resultsLength: number = 0;
    displayedColumns = ['id', 'typeId', 'label', 'actions'];
    filterColumns = ['typeId', 'label'];

    unlistedAttachmentTypes: string[] = ['signed_response', 'summary_sheet', 'shipping_deposit_proof', 'shipping_acknowledgement_of_receipt'];

    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        private notify: NotificationService,
        private headerService: HeaderService,
        public appService: AppService,
        public dialog: MatDialog,
        public functions: FunctionsService,
        public adminService: AdministrationService,
        private viewContainerRef: ViewContainerRef
    ) { }

    ngOnInit(): void {
        this.headerService.setHeader(this.translate.instant('lang.administration') + ' ' + this.translate.instant('lang.attachmentsTypes'));

        this.headerService.injectInSideBarLeft(this.adminMenuTemplate, this.viewContainerRef, 'adminMenu');

        this.loadList();
    }

    loadList() {
        this.loading = true;
        this.http.get('../rest/attachmentsTypes').pipe(
            map((data: any) => {
                const formatData = [];
                Object.keys(data.attachmentsTypes).forEach(key => {
                    formatData.push(data.attachmentsTypes[key]);
                });
                return formatData;
            }),
            tap((data: any) => {
                this.attachmentsTypes = data;
                this.resultsLength = data.length;
                setTimeout(() => {
                    this.adminService.setDataSource('admin_attachments', this.attachmentsTypes, this.sort, this.paginator, this.filterColumns);
                }, 0);
            }),
            finalize(() => this.loading = false)
        ).subscribe();
    }

    delete(item: any) {
        const dialogRef = this.dialog.open(ConfirmComponent, { panelClass: 'maarch-modal', autoFocus: false, disableClose: true, data: { title: `${this.translate.instant('lang.delete')} "${item.label}"`, msg: this.translate.instant('lang.confirmAction') } });
        dialogRef.afterClosed().pipe(
            filter((data: string) => data === 'ok'),
            exhaustMap(() => this.http.delete(`../rest/attachmentsTypes/${item.id}`)),
            tap(() => {
                this.loadList();
                this.notify.success(this.translate.instant('lang.attachmentDeleted'));
            }),
            catchError((err: any) => {
                this.notify.handleSoftErrors(err);
                return of(false);
            })
        ).subscribe();
    }
}
