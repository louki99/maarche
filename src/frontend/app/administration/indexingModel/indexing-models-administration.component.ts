import { Component, ViewChild, OnInit, TemplateRef, ViewContainerRef } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { TranslateService } from '@ngx-translate/core';
import { NotificationService } from '@service/notification/notification.service';
import { MatPaginator } from '@angular/material/paginator';
import { MatSort } from '@angular/material/sort';
import { HeaderService } from '@service/header.service';
import { AppService } from '@service/app.service';
import { tap, finalize, catchError, filter, exhaustMap, map } from 'rxjs/operators';
import { ConfirmComponent } from '../../../plugins/modal/confirm.component';
import { MatDialogRef, MatDialog } from '@angular/material/dialog';
import { FunctionsService } from '@service/functions.service';
import { of } from 'rxjs';
import { RedirectIndexingModelComponent } from './redirectIndexingModel/redirect-indexing-model.component';
import { AdministrationService } from '../administration.service';

@Component({
    templateUrl: 'indexing-models-administration.component.html',
    styleUrls: ['indexing-models-administration.component.scss']
})

export class IndexingModelsAdministrationComponent implements OnInit {

    @ViewChild('adminMenuTemplate', { static: true }) adminMenuTemplate: TemplateRef<any>;
    @ViewChild(MatPaginator, { static: false }) paginator: MatPaginator;
    @ViewChild(MatSort, { static: false }) sort: MatSort;

    search: string = null;

    indexingModels: any[] = [];

    loading: boolean = false;

    displayedColumns = ['id', 'category', 'label', 'private', 'default', 'enabled', 'actions'];
    filterColumns = ['id', 'label'];

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

        this.headerService.injectInSideBarLeft(this.adminMenuTemplate, this.viewContainerRef, 'adminMenu');

        this.loading = true;

        this.http.get('../rest/indexingModels?showDisabled=true').pipe(
            map((data: any) => data.indexingModels.filter((info: any) => info.master === null)),
            tap((data: any) => {
                this.indexingModels = data;
                this.headerService.setHeader(this.translate.instant('lang.administration') + ' ' + this.translate.instant('lang.indexingModels'));
                setTimeout(() => {
                    this.adminService.setDataSource('admin_indexing_models', this.indexingModels, this.sort, this.paginator, this.filterColumns);
                }, 0);
            }),
            finalize(() => this.loading = false),
            catchError((err: any) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    delete(indexingModel: any) {
        this.dialogRef = this.dialog.open(RedirectIndexingModelComponent, { panelClass: 'maarch-modal', autoFocus: false, disableClose: true, data: { indexingModel: indexingModel } });

        this.dialogRef.afterClosed().pipe(
            filter((data: string) => data === 'ok'),
            tap(() => {
                for (const i in this.indexingModels) {
                    if (this.indexingModels[i].id === indexingModel.id) {
                        this.indexingModels.splice(Number(i), 1);
                    }
                }
                this.adminService.setDataSource('admin_indexing_models', this.indexingModels, this.sort, this.paginator, this.filterColumns);
            }),
            catchError((err: any) => {
                this.notify.handleSoftErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    disableIndexingModel(indexingModel: any) {
        this.dialogRef = this.dialog.open(ConfirmComponent, { panelClass: 'maarch-modal', autoFocus: false, disableClose: true, data: { title: this.translate.instant('lang.disable'), msg: this.translate.instant('lang.confirmAction') } });

        this.dialogRef.afterClosed().pipe(
            filter((data: string) => data === 'ok'),
            exhaustMap(() => this.http.request('PUT', '../rest/indexingModels/' + indexingModel.id + '/disable')),
            tap((data: any) => {
                for (const i in this.indexingModels) {
                    if (this.indexingModels[i].id === indexingModel.id) {
                        this.indexingModels[i].enabled = false;
                    }
                }
                this.notify.success(this.translate.instant('lang.indexingModelDisabled'));
            }),
            catchError((err: any) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    enableIndexingModel(indexingModel: any) {
        this.dialogRef = this.dialog.open(ConfirmComponent, { panelClass: 'maarch-modal', autoFocus: false, disableClose: true, data: { title: this.translate.instant('lang.enable'), msg: this.translate.instant('lang.confirmAction') } });

        this.dialogRef.afterClosed().pipe(
            filter((data: string) => data === 'ok'),
            exhaustMap(() => this.http.request('PUT', '../rest/indexingModels/' + indexingModel.id + '/enable')),
            tap((data: any) => {
                for (const i in this.indexingModels) {
                    if (this.indexingModels[i].id === indexingModel.id) {
                        this.indexingModels[i].enabled = true;
                    }
                }
                this.notify.success(this.translate.instant('lang.indexingModelEnabled'));
            }),
            catchError((err: any) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }
}
