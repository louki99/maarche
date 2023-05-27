import { Component, OnInit, ViewChild, Input, Output, EventEmitter } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { TranslateService } from '@ngx-translate/core';
import { NotificationService } from '@service/notification/notification.service';
import { MatPaginator } from '@angular/material/paginator';
import { MatSort } from '@angular/material/sort';
import { MatTableDataSource } from '@angular/material/table';
import { AppService } from '@service/app.service';
import { tap, catchError, finalize, filter, exhaustMap } from 'rxjs/operators';
import { ConfirmComponent } from '../../plugins/modal/confirm.component';
import { MatDialog } from '@angular/material/dialog';
import { LinkResourceModalComponent } from './linkResourceModal/link-resource-modal.component';
import { FunctionsService } from '@service/functions.service';
import { ContactResourceModalComponent } from '../contact/contact-resource/modal/contact-resource-modal.component';
import { of } from 'rxjs';
import { PrivilegeService } from '@service/privileges.service';
import { Router } from '@angular/router';

declare let $: any;

@Component({
    selector: 'app-linked-resource-list',
    templateUrl: 'linked-resource-list.component.html',
    styleUrls: ['linked-resource-list.component.scss']
})
export class LinkedResourceListComponent implements OnInit {

    @Input() editMode: boolean = true;
    @Input() canAccessResources: boolean = true;
    @Input() resId: number;
    @Output() reloadBadgeLinkedResources = new EventEmitter<string>();

    @ViewChild(MatPaginator, { static: false }) paginator: MatPaginator;
    @ViewChild(MatSort, { static: false }) sort: MatSort;

    loading: boolean = true;

    linkedResources: any[] = [];
    dataSource: any;
    displayedColumns = ['resId'];

    thumbnailUrl: string = '';


    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        private router: Router,
        private notify: NotificationService,
        public appService: AppService,
        public dialog: MatDialog,
        public functions: FunctionsService,
        public privilegeService: PrivilegeService
    ) { }

    ngOnInit(): void {
        this.loading = true;
        this.editMode = this.editMode ? this.editMode && this.privilegeService.hasCurrentUserPrivilege('add_links') : false;
        this.initLinkedResources();
    }

    initLinkedResources() {
        this.http.get(`../rest/resources/${this.resId}/linkedResources`).pipe(
            tap((data: any) => {
                this.linkedResources = data.linkedResources;
                this.reloadBadgeLinkedResources.emit(`${this.linkedResources.length}`);
                setTimeout(() => {
                    this.linkedResources = this.processPostData(this.linkedResources);
                    this.dataSource = new MatTableDataSource(this.linkedResources);
                    this.dataSource.paginator = this.paginator;
                    this.dataSource.sort = this.sort;
                }, 0);
            }),
            finalize(() => this.loading = false),
            catchError((err: any) => {
                this.notify.handleSoftErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    processPostData(data: any) {

        data.forEach((linkeRes: any) => {
            Object.keys(linkeRes).forEach((key) => {
                if (key === 'statusImage' && this.functions.empty(linkeRes[key])) {
                    linkeRes[key] = 'fa-question undefined';
                } else if (this.functions.empty(linkeRes[key]) && ['senders', 'recipients', 'attachments', 'hasDocument', 'confidentiality', 'visaCircuit'].indexOf(key) === -1) {
                    linkeRes[key] = this.translate.instant('lang.undefined');
                }

                if (key === 'senders' && linkeRes[key].length > 1) {
                    if (linkeRes[key].length > 1) {
                        linkeRes[key] = linkeRes[key].length + ' ' + this.translate.instant('lang.contactsAlt');
                    } else {
                        linkeRes[key] = linkeRes[key][0];
                    }
                }
            });
        });

        return data;
    }

    getUsersVisaCircuit(row: any) {
        if (row.visaCircuit.length > 0) {
            return row.visaCircuit.map((item: any) => item.userLabel);
        } else {
            return '';
        }

    }

    unlinkResource(row: any) {
        const dialogRef = this.dialog.open(ConfirmComponent, { panelClass: 'maarch-modal', autoFocus: false, disableClose: true, data: { title: this.translate.instant('lang.unlink'), msg: this.translate.instant('lang.confirmAction') } });

        dialogRef.afterClosed().pipe(
            filter((data: string) => data === 'ok'),
            exhaustMap(() => this.http.delete(`../rest/resources/${this.resId}/linkedResources/${row.resId}`)),
            tap(() => {
                this.linkedResources = this.linkedResources.filter(resource => resource.resId !== row.resId);
                this.reloadBadgeLinkedResources.emit(`${this.linkedResources.length}`);
                this.dataSource = new MatTableDataSource(this.linkedResources);
                this.dataSource.paginator = this.paginator;
                this.dataSource.sort = this.sort;
                this.notify.success(this.translate.instant('lang.resourceUnlinked'));
            }),
            catchError((err: any) => {
                this.notify.handleSoftErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    viewThumbnail(row: any) {
        if (row.hasDocument && this.canAccessResources) {
            this.thumbnailUrl = '../rest/resources/' + row.resId + '/thumbnail';
            $('#viewThumbnail').show();
        }
    }

    closeThumbnail() {
        $('#viewThumbnail').hide();
    }

    openSearchResourceModal() {
        const dialogRef = this.dialog.open(LinkResourceModalComponent, { panelClass: 'maarch-full-height-modal', minWidth: '80%', data: { resId: this.resId, currentLinkedRes: this.linkedResources.map(res => res.resId) } });
        dialogRef.afterClosed().pipe(
            filter((data: string) => data === 'success'),
            tap(() => {
                this.initLinkedResources();
                this.notify.success(this.translate.instant('lang.resourcesLinked'));
            }),
            catchError((err: any) => {
                this.notify.handleSoftErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    openContact(row: any, mode: string) {
        this.dialog.open(ContactResourceModalComponent, { panelClass: 'maarch-modal', data: { title: `${row.chrono} - ${row.subject}`, mode: mode, resId: row.resId } });
    }

    goTo(resId: number) {
        if (this.canAccessResources) {
            this.router.navigate([`/resources/${resId}`]);
        }
    }
}
