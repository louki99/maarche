import { Component, OnInit, ViewChild, EventEmitter, Inject, TemplateRef, ViewContainerRef, ViewChildren } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { TranslateService } from '@ngx-translate/core';
import { NotificationService } from '@service/notification/notification.service';
import { HeaderService } from '@service/header.service';
import { MatSidenav } from '@angular/material/sidenav';
import { AppService } from '@service/app.service';
import { Observable, merge, Subject, of as observableOf, of } from 'rxjs';
import { MatDialog, MAT_DIALOG_DATA, MatDialogRef } from '@angular/material/dialog';
import { MatPaginator, PageEvent } from '@angular/material/paginator';
import { MatSort } from '@angular/material/sort';
import { takeUntil, startWith, switchMap, map, catchError, filter, exhaustMap, tap, debounceTime, distinctUntilChanged } from 'rxjs/operators';
import { ConfirmComponent } from '../../../../plugins/modal/confirm.component';
import { UntypedFormControl } from '@angular/forms';
import { FunctionsService } from '@service/functions.service';
import { ContactExportComponent } from './export/contact-export.component';
import { AdministrationService } from '../../../../app/administration/administration.service';
import { ContactImportComponent } from './import/contact-import.component';
import { SelectionModel } from '@angular/cdk/collections';
import { ContactsGroupFormModalComponent } from '../group/form/modal/contacts-group-form-modal.component';
import { MatMenuTrigger } from '@angular/material/menu';
import { LatinisePipe } from 'ngx-pipes';
import { ContactService } from '@service/contact.service';
import { ManageDuplicateComponent } from '../contact-duplicate/manage-duplicate/manage-duplicate.component';

@Component({
    selector: 'app-contact-list',
    templateUrl: 'contacts-list-administration.component.html',
    styleUrls: ['contacts-list-administration.component.scss'],
    providers: [ContactService]
})
export class ContactsListAdministrationComponent implements OnInit {

    @ViewChild('snav2', { static: true }) public sidenavRight: MatSidenav;
    @ViewChild('adminMenuTemplate', { static: true }) adminMenuTemplate: TemplateRef<any>;
    @ViewChild(MatPaginator, { static: true }) paginator: MatPaginator;
    @ViewChild('tableContactListSort', { static: true }) sort: MatSort;
    @ViewChild(MatMenuTrigger, { static: false }) contextMenu: MatMenuTrigger;
    @ViewChildren(MatMenuTrigger) contextMenus: any;

    subMenus: any[] = [];

    loading: boolean = false;

    filtersChange = new EventEmitter();

    data: any;

    displayedColumnsContact: string[] = ['filling', 'firstname', 'lastname', 'company', 'formatedAddress', 'actions'];

    isLoadingResults = true;
    allContacts: any = [];
    routeUrl: string = '../rest/contacts';
    resultListDatabase: ContactListHttpDao | null;
    resultsLength = 0;
    correspondentsGroups: any = [];
    selection = new SelectionModel<Element>(true, []);

    searchContact = new UntypedFormControl();
    search: string = '';
    dialogRef: MatDialogRef<any>;
    filterCorrespondentsGroups = new UntypedFormControl();
    filteredCorrespondentsGroups: Observable<string[]>;

    contextMenuPosition = { x: '0px', y: '0px' };

    pageSize: number = 10;

    private destroy$ = new Subject<boolean>();

    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        private notify: NotificationService,
        private headerService: HeaderService,
        public appService: AppService,
        public dialog: MatDialog,
        public functions: FunctionsService,
        private latinisePipe: LatinisePipe,
        public adminService: AdministrationService,
        public contactService: ContactService,
        private viewContainerRef: ViewContainerRef
    ) {
        this.subMenus = contactService.getAdminMenu();
    }


    ngOnInit(): void {
        this.headerService.injectInSideBarLeft(this.adminMenuTemplate, this.viewContainerRef, 'adminMenu');
        this.loading = true;
        this.adminService.setAdminId('admin_contacts_list');
        if (this.functions.empty(this.adminService.getFilter())) {
            this.adminService.saveDefaultFilter();
        }
        this.initContactList();

        this.initAutocompleteContacts();
    }

    initContactList() {
        this.resultListDatabase = new ContactListHttpDao(this.http);
        this.paginator.pageIndex = this.adminService.getFilter('page');
        this.sort.active = this.adminService.getFilter('sort');
        this.sort.direction = this.adminService.getFilter('sortDirection');
        this.sort.sortChange.subscribe(() => this.paginator.pageIndex = 0);

        // When list is refresh (sort, page, filters)
        merge(this.sort.sortChange, this.paginator.page, this.filtersChange)
            .pipe(
                takeUntil(this.destroy$),
                startWith({}),
                switchMap(() => {
                    this.adminService.saveFilter(
                        {
                            sort: this.sort.active,
                            sortDirection: this.sort.direction,
                            page: this.paginator.pageIndex,
                            field: this.adminService.getFilter('field')
                        }
                    );
                    // this.searchContact.setValue(this.adminService.getFilter('field'));
                    this.search = this.adminService.getFilter('field');
                    this.isLoadingResults = true;
                    return this.resultListDatabase!.getRepoIssues(
                        this.sort.active, this.sort.direction, this.paginator.pageIndex, this.routeUrl, this.search, this.pageSize);
                }),
                map(data => {
                    this.isLoadingResults = false;
                    data = this.processPostData(data);
                    this.resultsLength = data.count;
                    this.headerService.setHeader(this.translate.instant('lang.administration') + ' ' + this.translate.instant('lang.contacts').toLowerCase(), '', '');
                    return data.contacts;
                }),
                catchError((err: any) => {
                    this.notify.handleErrors(err);
                    this.isLoadingResults = false;
                    return observableOf([]);
                })
            ).subscribe(data => this.data = data);
    }

    getCorrespondentsGroups() {
        this.filterCorrespondentsGroups.reset();
        this.http.get('../rest/contactsGroups').pipe(
            tap((data: any) => {
                this.correspondentsGroups = data['contactsGroups'];
                this.filteredCorrespondentsGroups = this.filterCorrespondentsGroups.valueChanges
                    .pipe(
                        startWith(''),
                        map(state => state ? this._filter(state) : this.correspondentsGroups.slice())
                    );
            })
        ).subscribe();
    }

    addContactsToCorrespondentsGroup(groupId: number) {
        const objTosend = this.selection.selected.map((contactId: any) => ({
            id: contactId,
            type: 'contact'
        }));
        this.http.post('../rest/contactsGroups/' + groupId + '/correspondents', { correspondents: objTosend }).pipe(
            tap(() => {
                this.selection.clear();
                this.notify.success('Contact(s) associé(s)');
            }),
            catchError((err: any) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    processPostData(data: any) {
        data.contacts.forEach((element: any) => {
            const tmpFormatedAddress = [];
            tmpFormatedAddress.push(element.addressNumber);
            tmpFormatedAddress.push(element.addressStreet);
            tmpFormatedAddress.push(element.addressPostcode);
            tmpFormatedAddress.push(element.addressTown);
            tmpFormatedAddress.push(element.addressCountry);
            element.formatedAddress = tmpFormatedAddress.filter(address => !this.isEmptyValue(address)).join(' ');
        });

        if (!this.functions.empty(data.contacts[0]) && !this.functions.empty(data.contacts[0].filling)) {
            this.displayedColumnsContact = ['select', 'filling', 'firstname', 'lastname', 'company', 'formatedAddress', 'actions'];
        } else {
            this.displayedColumnsContact = ['select', 'firstname', 'lastname', 'company', 'formatedAddress', 'actions'];
        }
        return data;
    }

    deleteContact(contact: any) {

        if (contact.isUsed) {
            this.dialogRef = this.dialog.open(ContactsListAdministrationRedirectModalComponent, { panelClass: 'maarch-modal', autoFocus: false });
            this.dialogRef.afterClosed().subscribe((result: any) => {
                if (typeof result != 'undefined' && result != '') {
                    let queryparams = '';
                    if (result.processMode == 'reaffect') {
                        queryparams = '?redirect=' + result.contactId;
                    }
                    this.http.request('DELETE', `../rest/contacts/${contact.id}${queryparams}`)
                        .subscribe(() => {
                            this.refreshDao();
                            this.notify.success(this.translate.instant('lang.contactDeleted'));
                        }, (err) => {
                            this.notify.error(err.error.errors);
                        });
                }
                this.dialogRef = null;
            });
        } else {
            const dialogRef = this.dialog.open(ConfirmComponent, { panelClass: 'maarch-modal', autoFocus: false, disableClose: true, data: { title: this.translate.instant('lang.delete'), msg: this.translate.instant('lang.confirmAction') } });
            dialogRef.afterClosed().pipe(
                filter((data: string) => data === 'ok'),
                exhaustMap(() => this.http.delete(`../rest/contacts/${contact.id}`)),
                tap((data: any) => {
                    this.refreshDao();
                    this.notify.success(this.translate.instant('lang.contactDeleted'));
                }),
                catchError((err: any) => {
                    this.notify.handleErrors(err);
                    return of(false);
                })
            ).subscribe();
        }
    }

    toggleContact(contact: any) {
        const dialogRef = this.dialog.open(ConfirmComponent, { panelClass: 'maarch-modal', autoFocus: false, disableClose: true, data: { title: this.translate.instant('lang.suspend'), msg: this.translate.instant('lang.confirmAction') } });

        dialogRef.afterClosed().pipe(
            filter((data: string) => data === 'ok'),
            exhaustMap(() => this.http.put(`../rest/contacts/${contact.id}/activation`, { enabled: !contact.enabled })),
            tap((data: any) => {
                this.refreshDao();
                if (!contact.enabled === true) {
                    this.notify.success(this.translate.instant('lang.contactEnabled'));
                } else {
                    this.notify.success(this.translate.instant('lang.contactDisabled'));
                }
            }),
            catchError((err: any) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    openContactExport() {
        this.dialog.open(ContactExportComponent, { panelClass: 'maarch-modal', width: '800px', autoFocus: false });
    }

    openContactImportModal() {
        const dialogRef = this.dialog.open(ContactImportComponent, {
            disableClose: true,
            width: '99vw',
            maxWidth: '99vw',
            panelClass: 'maarch-full-height-modal'
        });

        dialogRef.afterClosed().pipe(
            filter((data: any) => data === 'success'),
            tap(() => {
                this.refreshDao();
            }),
            catchError((err: any) => {
                this.notify.handleSoftErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    refreshDao() {
        this.selection.clear();
        this.filtersChange.emit();
    }

    initAutocompleteContacts() {
        this.searchContact = new UntypedFormControl(this.adminService.getFilter('field'));
        this.searchContact.valueChanges
            .pipe(
                tap((value) => {
                    this.adminService.setFilter('field', value);
                    this.adminService.saveFilter(this.adminService.getFilter());

                    if (value.length === 0) {
                        this.search = '';
                        this.paginator.pageIndex = 0;
                        this.refreshDao();
                    }
                }),
                debounceTime(300),
                filter(value => value.length > 2),
                distinctUntilChanged(),
                tap((data) => {
                    this.search = data;
                    this.paginator.pageIndex = 0;
                    this.refreshDao();
                }),
            ).subscribe();
    }

    isEmptyValue(value: string) {

        if (value === null) {
            return true;

        } else if (Array.isArray(value)) {
            if (value.length > 0) {
                return false;
            } else {
                return true;
            }
        } else if (String(value) !== '') {
            return false;
        } else {
            return true;
        }
    }

    selectContact(contactId: any) {
        this.selection.toggle(contactId);
    }

    isAllSelected() {
        const numSelected = this.selection.selected.length;
        const numRows = this.allContacts.length;
        return numSelected === numRows;
    }

    selectAllContacts() {
        this.isAllSelected() ? this.selection.clear() : this.allContacts.forEach(contactId => this.selection.select(contactId));
    }

    openContactsGroupModal() {
        const dialogRef = this.dialog.open(ContactsGroupFormModalComponent, {
            panelClass: 'maarch-modal',
            disableClose: true,
            width: '99%',
            height: '99%',
            data: {
                contactIds: this.selection.selected,
                allPerimeters: true
            }
        });
        dialogRef.afterClosed().pipe(
            filter((data: any) => !this.functions.empty(data)),
            tap(async (res: any) => {
                this.refreshDao();
            }),
            catchError((err: any) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    open({ x, y }: MouseEvent, element: any) {
        if (!this.selection.isSelected(element.id)) {
            this.selection.clear();
            this.selection.select(element.id);
        }
        // Adjust the menu anchor position
        this.contextMenuPosition.x = x + 'px';
        this.contextMenuPosition.y = y + 'px';

        // Opens the menu
        this.contextMenus.toArray()[this.contextMenus.toArray().map((item: any) => item._element.nativeElement.id).indexOf('menuButtonContext')].openMenu();

        // prevents default
        return false;
    }

    handlePageEvent(event: PageEvent) {
        this.pageSize = event.pageSize;
    }

    mergeContacts(selection: any) {
        const dialogRef = this.dialog.open(ManageDuplicateComponent, {
            panelClass: 'maarch-modal',
            disableClose: true,
            data: { duplicate: selection._selected.map((contactId: any) => ({ id: contactId, type: 'contact'})) }
        });
        dialogRef.afterClosed().pipe(
            filter((data: any) => !this.functions.empty(data)),
            tap(() => {
                this.notify.success(this.translate.instant('lang.contactsMerged'));
                this.selection.clear();
                this.initContactList();
            }),
            catchError((err: any) => {
                this.notify.handleSoftErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    private _filter(value: string): string[] {
        const filterValue = this.latinisePipe.transform(value.toLowerCase());
        return this.correspondentsGroups.filter((option: any) => this.latinisePipe.transform(this.translate.instant(option['label']).toLowerCase()).includes(filterValue));
    }
}

export interface ContactList {
    contacts: any[];
    count: number;
}
export class ContactListHttpDao {
    constructor(private http: HttpClient) { }

    getRepoIssues(sort: string, order: string, page: number, href: string, search: string, pageSize: number): Observable<ContactList> {

        const offset = page * pageSize;
        const requestUrl = `${href}?limit=${pageSize}&offset=${offset}&order=${order}&orderBy=${sort}&search=${search}`;
        return this.http.get<ContactList>(requestUrl);
    }
}
@Component({
    templateUrl: 'contacts-list-administration-redirect-modal.component.html',
    styleUrls: [],
})
export class ContactsListAdministrationRedirectModalComponent implements OnInit {

    modalTitle: string = this.translate.instant('lang.confirmAction');
    redirectContact: number;
    processMode: string = 'delete';

    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        @Inject(MAT_DIALOG_DATA) public data: any,
        public dialogRef: MatDialogRef<ContactsListAdministrationRedirectModalComponent>,
        private notify: NotificationService) {
    }

    ngOnInit(): void {}

    setRedirectUser(contact: any) {
        this.redirectContact = contact.id;
    }
}
