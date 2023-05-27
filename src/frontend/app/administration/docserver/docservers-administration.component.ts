import { Component, OnInit, ViewChild, TemplateRef, ViewContainerRef } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { TranslateService } from '@ngx-translate/core';
import { NotificationService } from '@service/notification/notification.service';
import { MatPaginator } from '@angular/material/paginator';
import { MatSort } from '@angular/material/sort';
import { HeaderService } from '@service/header.service';
import { AppService } from '@service/app.service';
import { ConfirmComponent } from '@plugins/modal/confirm.component';
import { catchError, exhaustMap, filter, tap } from 'rxjs/operators';
import { of } from 'rxjs';
import { MatDialog } from '@angular/material/dialog';
@Component({
    templateUrl: 'docservers-administration.component.html'
})

export class DocserversAdministrationComponent implements OnInit {

    @ViewChild('adminMenuTemplate', { static: true }) adminMenuTemplate: TemplateRef<any>;
    @ViewChild(MatPaginator, { static: false }) paginator: MatPaginator;
    @ViewChild(MatSort, { static: false }) sort: MatSort;

    loading: boolean = false;
    dataSource: any;

    docservers: any = [];
    docserversClone: any = [];
    docserversTypes: any = {};

    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        private notify: NotificationService,
        private headerService: HeaderService,
        public appService: AppService,
        private viewContainerRef: ViewContainerRef,
        public dialog: MatDialog
    ) { }

    ngOnInit(): void {
        this.headerService.setHeader(this.translate.instant('lang.administration') + ' ' + this.translate.instant('lang.docservers'));

        this.headerService.injectInSideBarLeft(this.adminMenuTemplate, this.viewContainerRef, 'adminMenu');

        this.loading = true;

        this.http.get('../rest/docservers')
            .subscribe((data: any) => {
                this.docservers = data.docservers;
                this.docserversClone = JSON.parse(JSON.stringify(this.docservers));
                this.docserversTypes = data.types;
                this.loading = false;
            });
    }

    toggleDocserver(docserver: any) {

        docserver.is_readonly = !docserver.is_readonly;
    }

    cancelModification(docserverType: any, index: number) {
        this.docservers[docserverType][index] = JSON.parse(JSON.stringify(this.docserversClone[docserverType][index]));
    }

    checkModif(docserver: any, docserversClone: any) {
        docserver.size_limit_number = docserver.limitSizeFormatted * 1000000000;
        if (JSON.stringify(docserver) === JSON.stringify(docserversClone)) {
            return true;
        } else {
            if (docserver.size_limit_number >= docserver.actual_size_number && docserver.limitSizeFormatted > 0 && /^[\d]*$/.test(docserver.limitSizeFormatted)) {
                return false;
            } else {
                return true;
            }
        }
    }

    onSubmit(docserver: any, i: number) {
        docserver.size_limit_number = docserver.limitSizeFormatted * 1000000000;
        this.http.put('../rest/docservers/' + docserver.id, docserver)
            .subscribe((data: any) => {
                this.docservers[docserver.docserver_type_id][i] = data['docserver'];
                this.docserversClone[docserver.docserver_type_id][i] = JSON.parse(JSON.stringify(this.docservers[docserver.docserver_type_id][i]));
                this.notify.success(this.translate.instant('lang.docserverUpdated'));
            }, (err) => {
                this.notify.error(err.error.errors);
            });
    }

    delete(docserver: any, i: number) {
        const title: string = docserver.actual_size_number === 0 ? this.translate.instant('lang.delete') + ' ?' : this.translate.instant('lang.docserverdeleteWarning');
        const dialogRef = this.dialog.open(ConfirmComponent, { panelClass: 'maarch-modal', autoFocus: false, disableClose: true, data: { title: this.translate.instant('lang.confirmAction'), msg: title } });
        dialogRef.afterClosed().pipe(
            filter((data: string) => data === 'ok'),
            exhaustMap(() => this.http.delete('../rest/docservers/' + docserver.id)),
            tap(() => {
                this.docservers[docserver.docserver_type_id].splice(i, 1);
                this.notify.success(this.translate.instant('lang.docserverDeleted'));
            }),
            catchError((err: any) => {
                this.notify.handleSoftErrors(err);
                return of(false);
            })
        ).subscribe();

    }
}
