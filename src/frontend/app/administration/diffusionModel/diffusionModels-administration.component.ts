import { Component, OnInit, ViewChild, TemplateRef, ViewContainerRef } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { TranslateService } from '@ngx-translate/core';
import { NotificationService } from '@service/notification/notification.service';
import { HeaderService } from '@service/header.service';
import { MatDialog } from '@angular/material/dialog';
import { MatPaginator } from '@angular/material/paginator';
import { MatSort } from '@angular/material/sort';
import { AppService } from '@service/app.service';
import { catchError, map, finalize, filter, exhaustMap, tap } from 'rxjs/operators';
import { ConfirmComponent } from '../../../plugins/modal/confirm.component';
import { FunctionsService } from '@service/functions.service';
import { AdministrationService } from '../administration.service';
import { of } from 'rxjs';

@Component({
    templateUrl: 'diffusionModels-administration.component.html'
})
export class DiffusionModelsAdministrationComponent implements OnInit {

    @ViewChild('adminMenuTemplate', { static: true }) adminMenuTemplate: TemplateRef<any>;
    @ViewChild(MatPaginator, { static: false }) paginator: MatPaginator;
    @ViewChild(MatSort, { static: false }) sort: MatSort;

    loading: boolean = false;

    listTemplates: any[] = [];
    listTemplatesForAssign: any[] = [];

    displayedColumns = ['title', 'description', 'typeLabel', 'actions'];
    filterColumns = ['title', 'description', 'typeLabel'];

    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        private notify: NotificationService,
        public dialog: MatDialog,
        private headerService: HeaderService,
        public appService: AppService,
        public functions: FunctionsService,
        public adminService: AdministrationService,
        private viewContainerRef: ViewContainerRef
    ) { }

    async ngOnInit(): Promise<void> {
        this.headerService.setHeader(this.translate.instant('lang.administration') + ' ' + this.translate.instant('lang.workflowModels'));

        this.headerService.injectInSideBarLeft(this.adminMenuTemplate, this.viewContainerRef, 'adminMenu');

        this.loading = true;

        await this.getListemplates();

        this.loadList();

        this.loading = false;
    }

    getListemplates() {
        return new Promise((resolve, reject) => {
            this.http.get('../rest/listTemplates').pipe(
                map((data: any) => {
                    data.listTemplates = data.listTemplates.filter((template: any) => template.entityId === null && ['visaCircuit', 'opinionCircuit'].indexOf(template.type) > -1).map((template: any) => ({
                        ...template,
                        typeLabel: this.translate.instant('lang.' + template.type)
                    }));
                    return data.listTemplates;
                }),
                tap((listTemplates: any) => {
                    this.listTemplates = listTemplates;
                    resolve(true);
                }),
                finalize(() => this.loading = false),
                catchError((err: any) => {
                    this.notify.handleErrors(err);
                    return of(false);
                })
            ).subscribe();
        });
    }

    loadList() {
        setTimeout(() => {
            this.adminService.setDataSource('admin_listmodels', this.listTemplates, this.sort, this.paginator, this.filterColumns);
        }, 0);
    }

    delete(listTemplate: any) {
        const dialogRef = this.dialog.open(ConfirmComponent, { panelClass: 'maarch-modal', autoFocus: false, disableClose: true, data: { title: this.translate.instant('lang.delete'), msg: this.translate.instant('lang.confirmAction') } });
        dialogRef.afterClosed().pipe(
            filter((data: string) => data === 'ok'),
            exhaustMap(() => this.http.delete('../rest/listTemplates/' + listTemplate['id'])),
            tap(() => {
                this.listTemplates = this.listTemplates.filter((template: any) => template.id !== listTemplate.id);
                this.notify.success(this.translate.instant('lang.diffusionModelDeleted'));
                this.loadList();
            }),
            catchError((err: any) => {
                this.notify.handleSoftErrors(err);
                return of(false);
            })
        ).subscribe();
    }
}
