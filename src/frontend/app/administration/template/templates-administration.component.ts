import { Component, ViewChild, OnInit, TemplateRef, ViewContainerRef } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { TranslateService } from '@ngx-translate/core';
import { NotificationService } from '@service/notification/notification.service';
import { MatPaginator } from '@angular/material/paginator';
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
    templateUrl: 'templates-administration.component.html'
})

export class TemplatesAdministrationComponent implements OnInit {

    @ViewChild('adminMenuTemplate', { static: true }) adminMenuTemplate: TemplateRef<any>;
    @ViewChild(MatPaginator, { static: false }) paginator: MatPaginator;
    @ViewChild(MatSort, { static: false }) sort: MatSort;

    search: string = null;

    templates: any[] = [];
    titles: any[] = [];

    loading: boolean = false;

    displayedColumns = ['template_id', 'template_label', 'template_comment', 'template_type', 'template_target', 'actions'];
    filterColumns = ['template_label', 'template_comment', 'template_type', 'template_target'];

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
        this.headerService.setHeader(this.translate.instant('lang.administration') + ' ' + this.translate.instant('lang.templates'));

        this.headerService.injectInSideBarLeft(this.adminMenuTemplate, this.viewContainerRef, 'adminMenu');

        this.loading = true;

        this.http.get('../rest/templates')
            .subscribe((data) => {
                this.templates = data['templates'];
                this.loading = false;
                setTimeout(() => {
                    this.adminService.setDataSource('admin_templates', this.templates, this.sort, this.paginator, this.filterColumns);
                }, 0);
            }, (err) => {
                this.notify.handleErrors(err);
            });
    }

    deleteTemplate(template: any) {
        const dialogRef = this.dialog.open(ConfirmComponent, { panelClass: 'maarch-modal', autoFocus: false, disableClose: true, data: { title: `${this.translate.instant('lang.delete')} « ${template.template_label} »`, msg: this.translate.instant('lang.confirmAction') } });
        dialogRef.afterClosed().pipe(
            filter((data: string) => data === 'ok'),
            exhaustMap(() => this.http.delete('../rest/templates/' + template.template_id)),
            tap(() => {
                for (const i in this.templates) {
                    if (this.templates[i].template_id === template.template_id) {
                        this.templates.splice(Number(i), 1);
                    }
                }
                this.adminService.setDataSource('admin_templates', this.templates, this.sort, this.paginator, this.filterColumns);
                this.notify.success(this.translate.instant('lang.templateDeleted'));
            }),
            catchError((err: any) => {
                this.notify.handleSoftErrors(err);
                return of(false);
            })
        ).subscribe();
    }
}
