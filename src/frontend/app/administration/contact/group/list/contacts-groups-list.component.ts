import { Component, ViewChild, OnInit, Input } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { TranslateService } from '@ngx-translate/core';
import { NotificationService } from '@service/notification/notification.service';
import { HeaderService } from '@service/header.service';
import { MatPaginator } from '@angular/material/paginator';
import { MatSort } from '@angular/material/sort';
import { AppService } from '@service/app.service';
import { FunctionsService } from '@service/functions.service';
import { AdministrationService } from '../../../administration.service';
import { SelectionModel } from '@angular/cdk/collections';
import { catchError, exhaustMap, filter, tap } from 'rxjs/operators';
import { of } from 'rxjs';
import { ConfirmComponent } from '@plugins/modal/confirm.component';
import { MatDialog } from '@angular/material/dialog';
import { ContactsGroupFormModalComponent } from '../form/modal/contacts-group-form-modal.component';
import { Router } from '@angular/router';
import { PrivilegeService } from '@service/privileges.service';
import { ContactsGroupMergeModalComponent } from './merge-modal/contacts-group-merge-modal.component';

@Component({
    selector: 'app-contacts-groups-list',
    templateUrl: 'contacts-groups-list.component.html',
    styleUrls: ['contacts-groups-list.component.scss']
})

export class ContactsGroupsListComponent implements OnInit {

    @Input() allPerimeters: boolean = false;
    @Input() contactGroupFormMode: 'route' | 'modal' = 'route';
    @Input() showAddButton: boolean = true;
    @Input() inProfile: boolean;

    @ViewChild(MatPaginator, { static: false }) paginator: MatPaginator;
    @ViewChild(MatSort, { static: false }) sort: MatSort;

    search: string = null;

    contactsGroups: any[] = [];
    userEntitiesIds: number[] = [];
    titles: any[] = [];

    loading: boolean = false;


    displayedColumns = ['select', 'label', 'description', 'nbCorrespondents', 'shared', 'labelledOwner', 'actions'];
    filterColumns = ['label', 'description'];
    selection = new SelectionModel<Element>(true, []);


    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        private notify: NotificationService,
        public headerService: HeaderService,
        public dialog: MatDialog,
        public appService: AppService,
        public functions: FunctionsService,
        public adminService: AdministrationService,
        public privilegeService: PrivilegeService,
        private router: Router
    ) { }

    async ngOnInit(): Promise<void> {
        this.userEntitiesIds = this.headerService.user.entities.map((entity: any) => entity.id);
        this.loading = true;
        await this.getContactsGroups();
        this.loading = false;
    }

    getContactsGroups() {
        this.selection.clear();
        return new Promise((resolve) => {
            const param = !this.allPerimeters ? '?profile=true' : '';

            this.http.get('../rest/contactsGroups' + param)
                .subscribe((data) => {
                    this.contactsGroups = data['contactsGroups'].map((contactGroup: any) => ({
                        ...contactGroup,
                        shared : contactGroup.entities.length > 0,
                        allowed: !this.isLocked(contactGroup)
                    }));
                    setTimeout(() => {
                        this.adminService.setDataSource('admin_contacts_groups', this.contactsGroups, this.sort, this.paginator, this.filterColumns);
                    }, 0);
                    resolve(true);
                }, (err) => {
                    this.notify.handleErrors(err);
                });
        });
    }

    deleteContactsGroup(element: any) {
        const dialogRef = this.dialog.open(ConfirmComponent, { panelClass: 'maarch-modal', autoFocus: false, disableClose: true, data: { title: this.translate.instant('lang.delete'), msg: this.translate.instant('lang.confirmAction') } });
        dialogRef.afterClosed().pipe(
            filter((data: string) => data === 'ok'),
            exhaustMap(() => this.http.delete(`../rest/contactsGroups/${element.id}`)),
            tap(() => {
                this.notify.success(this.translate.instant('lang.contactsGroupDeleted'));
                this.getContactsGroups();
            }),
            catchError((err: any) => {
                this.notify.handleSoftErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    mergeContactsGroups() {
        const dialogRef = this.dialog.open(ContactsGroupMergeModalComponent, { panelClass: 'maarch-modal', autoFocus: false, data: { itemsToMerge: this.selection.selected } });
        dialogRef.afterClosed().pipe(
            filter((data: string) => data === 'success'),
            tap(() => {
                this.getContactsGroups();
            }),
            catchError((err: any) => {
                this.notify.handleSoftErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    copyContactsGroup(element: any) {
        const dialogRef = this.dialog.open(ConfirmComponent, { panelClass: 'maarch-modal', autoFocus: false, disableClose: true, data: { title: this.translate.instant('lang.duplicate'), msg: this.translate.instant('lang.confirmAction') } });
        dialogRef.afterClosed().pipe(
            filter((data: string) => data === 'ok'),
            exhaustMap(() => this.http.post(`../rest/contactsGroups/${element.id}/duplicate`, {})),
            tap(() => {
                this.notify.success(this.translate.instant('lang.contactsGroupDuplicated'));
                this.getContactsGroups();
            }),
            catchError((err: any) => {
                this.notify.handleSoftErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    toggleContactGroup(element: any) {
        this.selection.toggle(element);
    }

    isAllSelected() {
        const numSelected = this.selection.selected.length;
        const numRows = this.contactsGroups.filter(element => element.allowed).length;
        return numSelected === numRows;
    }

    toggleAllContactsGroups() {
        this.isAllSelected() ? this.selection.clear() : this.contactsGroups.filter(element => element.allowed).forEach(element => this.selection.select(element));
    }

    isLocked(element: any) {
        if (this.allPerimeters) {
            return false;
        } else {
            return element.owner !== this.headerService.user.id;
        }
    }

    goTo(element: any = null) {
        if (element === null) {
            this.router.navigate(['/administration/contacts/contacts-groups/new']);
        } else {
            if (this.contactGroupFormMode === 'modal') {
                this.openContactsGroupModal(element);
            } else {
                this.router.navigate([`/administration/contacts/contacts-groups/${element.id}`]);
            }
        }
    }

    openContactsGroupModal(element: any = null) {
        const dialogRef = this.dialog.open(ContactsGroupFormModalComponent, {
            panelClass: 'maarch-modal',
            disableClose: true,
            width: '99%',
            height: '99%',
            data: {
                modalTitle: element !== null ? element.label : null,
                contactGroupId: element !== null ? element.id : null,
                canAddCorrespondents: (element !== null && element.owner === this.headerService.user.id) || this.privilegeService.hasCurrentUserPrivilege('add_correspondent_in_shared_groups_on_profile'),
                canModifyGroupInfo: (element !== null && element.owner === this.headerService.user.id),
                allPerimeters: this.allPerimeters
            }
        });
        dialogRef.afterClosed().pipe(
            tap(async (res: any) => {
                await this.getContactsGroups();
                if (!this.functions.empty(res) && res.state === 'create') {
                    const newGrp = this.contactsGroups.find((grp: any) => grp.id === res.id);
                    this.openContactsGroupModal(newGrp);
                }
            }),
            catchError((err: any) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }
}
