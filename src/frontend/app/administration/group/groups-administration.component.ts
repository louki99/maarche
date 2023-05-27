import { Component, OnInit, ViewChild, Inject, TemplateRef, ViewContainerRef } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { TranslateService } from '@ngx-translate/core';
import { NotificationService } from '@service/notification/notification.service';
import { MatDialog, MatDialogRef, MAT_DIALOG_DATA } from '@angular/material/dialog';
import { MatPaginator } from '@angular/material/paginator';
import { MatSidenav } from '@angular/material/sidenav';
import { MatSort } from '@angular/material/sort';
import { HeaderService } from '@service/header.service';
import { AppService } from '@service/app.service';
import { FunctionsService } from '@service/functions.service';
import { AdministrationService } from '../administration.service';
import { catchError, exhaustMap, filter, tap } from 'rxjs/operators';
import { of } from 'rxjs';
import { ConfirmComponent } from '@plugins/modal/confirm.component';

@Component({
    templateUrl: 'groups-administration.component.html'
})
export class GroupsAdministrationComponent implements OnInit {

    @ViewChild('snav2', { static: true }) public sidenavRight: MatSidenav;
    @ViewChild('adminMenuTemplate', { static: true }) adminMenuTemplate: TemplateRef<any>;
    @ViewChild(MatPaginator, { static: false }) paginator: MatPaginator;
    @ViewChild(MatSort, { static: false }) sort: MatSort;

    dialogRef: MatDialogRef<any>;

    loading: boolean = false;

    config: any = {};
    groups: any[] = [];
    groupsForAssign: any[] = [];


    displayedColumns = ['group_id', 'group_desc', 'actions'];
    filterColumns = ['group_id', 'group_desc'];

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

    ngOnInit(): void {
        this.headerService.setHeader(this.translate.instant('lang.administration') + ' ' + this.translate.instant('lang.groups'));

        this.headerService.injectInSideBarLeft(this.adminMenuTemplate, this.viewContainerRef, 'adminMenu');

        this.loading = true;

        this.http.get('../rest/groups')
            .subscribe((data: any) => {
                this.groups = data['groups'];
                this.loading = false;
                setTimeout(() => {
                    this.adminService.setDataSource('admin_groups', this.groups, this.sort, this.paginator, this.filterColumns);
                }, 0);
            }, (err) => {
                this.notify.handleErrors(err);
            });
    }

    preDelete(group: any) {
        if (group.users.length === 0) {
            const dialogRef = this.dialog.open(ConfirmComponent, { panelClass: 'maarch-modal', autoFocus: false, disableClose: true, data: { title: `${this.translate.instant('lang.deleteGroupConfirm')} « ${group.group_desc} »`, msg: this.translate.instant('lang.confirmAction') } });
            dialogRef.afterClosed().pipe(
                filter((data: string) => data === 'ok'),
                exhaustMap(async () => this.deleteGroup(group)),
                catchError((err: any) => {
                    this.notify.handleSoftErrors(err);
                    return of(false);
                })
            ).subscribe();
        } else {
            this.groupsForAssign = [];
            this.groups.forEach((tmpGroup) => {
                if (group.group_id !== tmpGroup.group_id) {
                    this.groupsForAssign.push(tmpGroup);
                }
            });
            this.config = { panelClass: 'maarch-modal', data: { id: group.id, group_desc: group.group_desc, groupsForAssign: this.groupsForAssign, users: group.users } };
            this.dialogRef = this.dialog.open(GroupsAdministrationRedirectModalComponent, this.config);
            this.dialogRef.afterClosed().subscribe((result: string) => {
                if (result) {
                    if (result === '_NO_REPLACEMENT') {
                        this.deleteGroup(group);
                    } else {
                        this.http.put('../rest/groups/' + group.id + '/reassign/' + result, {})
                            .subscribe((data: any) => {
                                this.deleteGroup(group);
                            }, (err) => {
                                this.notify.error(err.error.errors);
                            });
                    }
                }
                this.dialogRef = null;
            });
        }

    }

    deleteGroup(group: any) {
        this.http.delete('../rest/groups/' + group['id'])
            .subscribe((data: any) => {
                setTimeout(() => {
                    this.groups = data['groups'];
                    this.adminService.setDataSource('admin_groups', this.groups, this.sort, this.paginator, this.filterColumns);
                }, 0);
                this.notify.success(this.translate.instant('lang.groupDeleted'));

            }, (err) => {
                this.notify.error(err.error.errors);
            });
    }
}

@Component({
    templateUrl: 'groups-administration-redirect-modal.component.html'
})
export class GroupsAdministrationRedirectModalComponent {

    constructor(public http: HttpClient, @Inject(MAT_DIALOG_DATA) public data: any, public dialogRef: MatDialogRef<GroupsAdministrationRedirectModalComponent>) {
    }
}
