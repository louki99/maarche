import { Component, OnInit, ViewChild, TemplateRef, ViewContainerRef, EventEmitter, Input, AfterViewInit, Output } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Router, ActivatedRoute } from '@angular/router';
import { TranslateService } from '@ngx-translate/core';
import { NotificationService } from '@service/notification/notification.service';
import { HeaderService } from '@service/header.service';
import { UntypedFormControl, NgForm } from '@angular/forms';
import { debounceTime, switchMap, distinctUntilChanged, filter, tap, map, catchError, takeUntil, startWith, exhaustMap } from 'rxjs/operators';
import { MatPaginator, PageEvent } from '@angular/material/paginator';
import { MatSort } from '@angular/material/sort';
import { MatTableDataSource } from '@angular/material/table';
import { SelectionModel } from '@angular/cdk/collections';
import { AppService } from '@service/app.service';
import { MaarchFlatTreeComponent } from '@plugins/tree/maarch-flat-tree.component';
import { MatAutocompleteTrigger } from '@angular/material/autocomplete';
import { merge, Observable, of, Subject } from 'rxjs';
import { ContactService } from '@service/contact.service';
import { FunctionsService } from '@service/functions.service';
import { ConfirmComponent } from '@plugins/modal/confirm.component';
import { MatDialog } from '@angular/material/dialog';
import { ContactsFormModalComponent } from '../../page/form/modal/contacts-form-modal.component';
import { PrivilegeService } from '@service/privileges.service';

@Component({
    selector: 'app-contacts-group-form',
    templateUrl: 'contacts-group-form.component.html',
    styleUrls: ['contacts-group-form.component.scss'],
    providers: [ContactService]
})
export class ContactsGroupFormComponent implements OnInit, AfterViewInit {

    @ViewChild('contactsGroupTreeTemplate', { static: true }) contactsGroupTreeTemplate: TemplateRef<any>;
    @ViewChild('paginatorLinkedCorrespondents', { static: false }) paginatorLinkedCorrespondents: MatPaginator;
    @ViewChild('sortLinkedCorrespondents', { static: false }) sortLinkedCorrespondents: MatSort;
    @ViewChild('maarchTree', { static: false }) maarchTree: MaarchFlatTreeComponent;
    @ViewChild('contactsGroupFormUp', { static: false }) contactsGroupFormUp: NgForm;

    @Input() contactGroupId: number = null;
    @Input() hideSaveButton: boolean = false;
    @Input() canAddCorrespondents: boolean = true;
    @Input() canModifyGroupInfo: boolean = true;
    @Input() allPerimeters: boolean = true;
    @Input() contactIds: number[] = [];

    @Output() afterUpdate = new EventEmitter<any>();

    filtersChange = new EventEmitter();
    filterInputControl = new UntypedFormControl('');
    searchCorrespondentInputControl = new UntypedFormControl('');

    pageSize: number = 10;

    creationMode: boolean = true;
    displayCorrespondents: boolean = true; // fix issue virtual scroll

    contactsGroup: any = {
        entities: []
    };
    nbLinkedCorrespondents: number;
    nbFilteredLinkedCorrespondents: number;

    loading: boolean = false;

    allRelatedCorrespondents: any = [];
    relatedCorrespondents: any = [];
    relatedCorrespondentsSelected = new SelectionModel<Element>(true, []);

    loadingCorrespondents: boolean = false;
    loadingLinkedCorrespondents: boolean = false;
    savingCorrespondents: boolean = false;
    initAutoCompleteContact = true;

    searchResult: any = [];

    displayedColumns = ['type', 'name', 'address'];
    displayedColumnsAdded = ['select', 'type', 'name', 'address', 'actions'];
    dataSource: any;
    // dataSourceLinkedCorrespondents: any;
    dataSourceLinkedCorrespondents: CorrespondentListHttpDao | null;
    selection = new SelectionModel<Element>(true, []);
    private destroy$ = new Subject<boolean>();


    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        private route: ActivatedRoute,
        private router: Router,
        public dialog: MatDialog,
        private notify: NotificationService,
        private headerService: HeaderService,
        public appService: AppService,
        public contactService: ContactService,
        private viewContainerRef: ViewContainerRef,
        private functionsService: FunctionsService,
        public privilegeService: PrivilegeService,
    ) { }

    ngOnInit(): void {
        this.headerService.initTemplate(this.contactsGroupTreeTemplate, this.viewContainerRef, 'contactsGroupTree');
    }

    ngAfterViewInit(): void {
        if (this.contactGroupId === null) {
            this.creationMode = true;
            this.initTree();
            this.canAddCorrespondents = false;
            this.canModifyGroupInfo = true;
            if (this.contactIds.length > 0) {
                this.initContacts();
            }
        } else {
            this.creationMode = false;
            this.getContactGroup(this.contactGroupId);
            this.searchCorrespondentInputControl.valueChanges.pipe(
                startWith(''),
                debounceTime(300),
                tap((value: any) => {
                    if (this.functionsService.empty(value)) {
                        this.dataSource = null;
                    } else {
                        this.searchContact(value);
                    }
                })
            ).subscribe();
        }
    }

    async initContacts() {
        this.displayedColumnsAdded = this.displayedColumnsAdded.filter((col: any) => ['select', 'actions'].indexOf(col) === -1);
        this.nbLinkedCorrespondents = this.contactIds.length;
        this.nbFilteredLinkedCorrespondents = this.contactIds.length;
        const arrContact = [];
        for (let index = 0; index < this.contactIds.length; index++) {
            const contact = await this.getContact(this.contactIds[index]);
            arrContact.push(contact);
        }
        this.relatedCorrespondents = new MatTableDataSource(arrContact);
        this.relatedCorrespondents.paginator = this.paginatorLinkedCorrespondents;

    }

    getContact(contactId: number) {
        return new Promise((resolve) => {
            this.http.get('../rest/contacts/' + contactId).pipe(
                tap((data: any) => {
                    const formatedContact = this.contactService.formatContactAddress(data);
                    data = {
                        id: data.id,
                        type: 'contact',
                        name: this.contactService.formatContact(data),
                        address: !this.functionsService.empty(formatedContact) ? formatedContact : this.translate.instant('lang.addressNotSet')
                    };
                    resolve(data);
                }),
                catchError((err: any) => {
                    this.notify.handleErrors(err);
                    return of(false);
                })
            ).subscribe();
        });
    }

    getContactGroup(contactGroupId: number) {
        const param = !this.allPerimeters ? '?profile=true' : '';

        this.http.get('../rest/contactsGroups/' + contactGroupId + param).pipe(
            tap((data: any) => {
                this.contactsGroup = data.contactsGroup;
                data.entities = data.entities.map((entity: any) => ({
                    ...entity,
                    id: parseInt(entity.id)
                }));
                if (!this.canModifyGroupInfo) {
                    data.entities.forEach((entity: any) => {
                        entity.state.disabled = true;
                    });
                }
                if (!this.canAddCorrespondents) {
                    this.displayedColumnsAdded = this.displayedColumnsAdded.filter((col: any) => ['select', 'actions'].indexOf(col) === -1);
                }
                this.maarchTree.initData(data.entities);
                this.headerService.setHeader(this.translate.instant('lang.contactsGroupModification'), this.contactsGroup.label);
                this.loading = false;
                setTimeout(() => {
                    this.initRelatedCorrespondentList();
                    this.initAutocompleteContacts();
                }, 0);
            }),
            catchError((err: any) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    initTree() {
        const param = !this.allPerimeters ? '?profile=true' : '';
        this.http.get('../rest/contactsGroupsEntities' + param).pipe(
            map((data: any) => {
                data.entities = data.entities.map((entity: any) => ({
                    ...entity,
                    id: parseInt(entity.id)
                }));
                return data.entities;
            }),
            tap((entities: any) => {
                this.maarchTree.initData(entities);
            }),
            catchError((err: any) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    initRelatedCorrespondentList() {
        this.dataSourceLinkedCorrespondents = new CorrespondentListHttpDao(this.http);
        this.sortLinkedCorrespondents.sortChange.subscribe(() => this.paginatorLinkedCorrespondents.pageIndex = 0);

        // When list is refresh (sort, page, filters)
        merge(this.sortLinkedCorrespondents.sortChange, this.paginatorLinkedCorrespondents.page, this.filtersChange)
            .pipe(
                takeUntil(this.destroy$),
                startWith({}),
                switchMap(() => {
                    this.loadingLinkedCorrespondents = true;
                    return this.dataSourceLinkedCorrespondents!.getRepoIssues(
                        this.sortLinkedCorrespondents.active, this.sortLinkedCorrespondents.direction, this.paginatorLinkedCorrespondents.pageIndex, '../rest/contactsGroups/' + this.contactsGroup.id + '/correspondents', this.filterInputControl.value, this.pageSize);
                }),
                map(data => {
                    this.loadingLinkedCorrespondents = false;
                    data = this.processPostData(data);
                    if (this.filterInputControl.value === '' || this.nbLinkedCorrespondents === 0) {
                        this.nbLinkedCorrespondents = data.count;
                    }
                    this.nbFilteredLinkedCorrespondents = data.count;
                    if (this.allRelatedCorrespondents.length === 0) {
                        this.allRelatedCorrespondents = data.allCorrespondents;
                    }
                    return data.correspondents;
                }),
                catchError((err: any) => {
                    this.notify.handleErrors(err);
                    this.loadingLinkedCorrespondents = false;
                    return of(false);
                })
            ).subscribe(data => this.relatedCorrespondents = data);
    }

    processPostData(data: any) {
        data.correspondents = data.correspondents.map((item: any) => ({
            ...item,
            address: !this.functionsService.empty(item.address) ? item.address : this.translate.instant('lang.addressNotSet')
        }));

        return data;
    }

    initAutocompleteContacts() {
        this.filterInputControl = new UntypedFormControl('');
        this.filterInputControl.valueChanges
            .pipe(
                tap((value) => {
                    this.dataSource = null;
                    if (value.length === 0) {
                        this.paginatorLinkedCorrespondents.pageIndex = 0;
                        this.refreshDao();
                    }
                }),
                debounceTime(300),
                filter(value => value.length > 2),
                distinctUntilChanged(),
                tap((data) => {
                    this.paginatorLinkedCorrespondents.pageIndex = 0;
                    this.refreshDao();
                }),
            ).subscribe();
    }

    refreshDao() {
        this.relatedCorrespondentsSelected.clear();
        this.filtersChange.emit();
    }

    searchContact(search: string) {
        this.loadingCorrespondents = true;
        this.displayCorrespondents = true;
        this.http.get('../rest/autocomplete/correspondents', { params: { 'noContactsGroups': 'true', 'limit': '1000', 'search': search } }).pipe(
            tap((data: any) => {
                this.searchResult = data.map((contact: any) => {
                    const formatedContact = this.contactService.formatContactAddress(contact);
                    return {
                        id: contact.id,
                        type: contact.type,
                        name: this.contactService.formatContact(contact),
                        address: !this.functionsService.empty(formatedContact) ? formatedContact : this.translate.instant('lang.addressNotSet')
                    };
                });
                this.dataSource = new MatTableDataSource(this.searchResult);
                this.loadingCorrespondents = false;
                this.displayCorrespondents = false;
            })
        ).subscribe();
    }

    selectEntities(entities: any[]) {
        const formatedEntities = entities.map((entity: any) => entity.id);
        this.contactsGroup.entities = [...this.contactsGroup.entities, ...formatedEntities];
        this.contactsGroup.entities = this.contactsGroup.entities.filter((entity: any, index: number) => this.contactsGroup.entities.indexOf(entity) === index);
    }

    deselectEntities(entities: any[]) {
        const formatedEntities = entities.map((entity: any) => entity.id);
        this.contactsGroup.entities = this.contactsGroup.entities.filter((entity: any) => formatedEntities.indexOf(entity) === -1);
    }

    saveContactsList(cGroupId: number = this.contactsGroup.id): void {
        this.savingCorrespondents = true;
        this.http.post('../rest/contactsGroups/' + cGroupId + '/correspondents', { 'correspondents': this.formatCorrespondents() })
            .subscribe((data: any) => {
                this.notify.success(this.translate.instant('lang.correspondentAdded'));
                this.selection.clear();
                this.savingCorrespondents = false;
                this.allRelatedCorrespondents = [];
                this.refreshDao();
            }, (err) => {
                this.notify.error(err.error.errors);
            });
    }

    formatCorrespondents() {
        return this.selection.selected.map((correspondent: any) => ({
            type: correspondent.type,
            id: correspondent.id
        }));
    }

    isValid() {
        return this.contactsGroupFormUp !== undefined && this.contactsGroupFormUp.form.valid;
    }

    onSubmit() {
        if (this.creationMode) {
            this.http.post('../rest/contactsGroups', this.contactsGroup)
                .subscribe((data: any) => {
                    if (this.contactIds.length > 0) {
                        this.contactIds.forEach((contactId) => {
                            const objContact: any = {
                                id: contactId,
                                type: 'contact'
                            };
                            this.selection.select(objContact);
                        });
                        this.saveContactsList(data.id);
                    }
                    if (!this.hideSaveButton) {
                        // WORKAROUND ISSUE TREE DOESNT LOAD
                        this.router.navigate(['/administration/contacts/contacts-groups']);
                        setTimeout(() => {
                            this.router.navigate(['/administration/contacts/contacts-groups/' + data.id]);
                        }, 100);
                    }
                    this.notify.success(this.translate.instant('lang.contactsGroupAdded'));
                    this.afterUpdate.emit(data.id);
                }, (err) => {
                    this.notify.error(err.error.errors);
                });
        } else {
            this.http.put('../rest/contactsGroups/' + this.contactsGroup.id, this.contactsGroup)
                .subscribe(() => {
                    if (!this.hideSaveButton) {
                        this.router.navigate(['/administration/contacts/contacts-groups']);
                    }
                    this.notify.success(this.translate.instant('lang.contactsGroupUpdated'));
                    this.afterUpdate.emit(this.contactsGroup.id);
                }, (err) => {
                    this.notify.error(err.error.errors);
                });
        }
    }

    removeContact(contact: any = null) {
        const objTosend = contact === null ? this.formatRelatedCorrespondentsSelected() : [{ type: contact.type, id: contact.id }];

        const dialogRef = this.dialog.open(ConfirmComponent, { panelClass: 'maarch-modal', autoFocus: false, disableClose: true, data: { title: this.translate.instant('lang.delete'), msg: this.translate.instant('lang.confirmAction') } });

        dialogRef.afterClosed().pipe(
            filter((data: string) => data === 'ok'),
            exhaustMap(() => this.http.request('DELETE', `../rest/contactsGroups/${this.contactsGroup.id}/correspondents`, { body: { correspondents: objTosend } })),
            tap(() => {
                this.notify.success(this.translate.instant('lang.contactDeletedFromGroup'));
                this.selection.clear();
                this.allRelatedCorrespondents = [];
                this.refreshDao();
            }),
            catchError((err: any) => {
                this.notify.handleSoftErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    isInGrp(contact: any): boolean {
        let isInGrp = false;
        this.allRelatedCorrespondents.forEach((row: any) => {
            if (row.id === contact.id && row.type === contact.type) {
                isInGrp = true;
            }
        });
        return isInGrp;
    }

    toggleAll(trigger: MatAutocompleteTrigger) {
        this.dataSource.data.forEach((row: any) => {
            if (!this.isInGrp(row)) {
                this.selection.select(row);
            }
        });
        if (this.selection.selected.length > 0) {
            this.saveContactsList();
        }
        trigger.closePanel();
    }

    toggleCorrespondent(element: any) {
        if (!this.isInGrp(element)) {
            this.selection.toggle(element);
            this.saveContactsList();
        }
    }

    toggleRelatedCorrespondent(element: any) {
        this.relatedCorrespondentsSelected.toggle(this.allRelatedCorrespondents.find((item: any) => item.id === element.id && item.type === element.type));
    }

    toggleAllRelatedCorrespondents() {
        if (this.isAllRelatedCorrespondentsSelected()) {
            this.relatedCorrespondentsSelected.clear();
        } else {
            this.allRelatedCorrespondents.forEach((row: any) => {
                this.relatedCorrespondentsSelected.select(row);
            });
        }
    }

    isRelatedCorrespondentsSelected(element: any) {
        return this.relatedCorrespondentsSelected.isSelected(this.allRelatedCorrespondents.find((item: any) => item.id === element.id && item.type === element.type));
    }

    isAllRelatedCorrespondentsSelected() {
        const numSelected = this.relatedCorrespondentsSelected.selected.length;
        const numRows = this.allRelatedCorrespondents.length;
        return numSelected === numRows;
    }

    formatRelatedCorrespondentsSelected() {
        return this.relatedCorrespondentsSelected.selected.map((correspondent: any) => ({
            type: correspondent.type,
            id: correspondent.id
        }));
    }


    open(event: Event, trigger: MatAutocompleteTrigger) {
        event.stopPropagation();
        setTimeout(() => {
            trigger.openPanel();
        }, 100);
    }

    openContactForm() {
        const dialogRef = this.dialog.open(ContactsFormModalComponent, {
            panelClass: 'maarch-modal',
            disableClose: true,
            width: '99%',
            height: '99%',
            data: { defaultName: this.filterInputControl.value }
        });
        dialogRef.afterClosed().pipe(
            filter((data: any) => !this.functionsService.empty(data)),
            tap((data: any) => {
                const objContact: any = {
                    id: data.id,
                    type: 'contact'
                };
                this.selection.select(objContact);
                this.saveContactsList();
            }),
            catchError((err: any) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    handlePageEvent(event: PageEvent) {
        this.pageSize = event.pageSize;
    }
}
export interface CorrespondentList {
    correspondents: any[];
    count: number;
    allCorrespondents: any[];
}
export class CorrespondentListHttpDao {

    constructor(private http: HttpClient) { }

    getRepoIssues(sort: string, order: string, page: number, href: string, search: string, pageSize: number): Observable<CorrespondentList> {

        const offset = page * pageSize;
        const requestUrl = `${href}?limit=${pageSize}&offset=${offset}&order=${order}&orderBy=${sort}&search=${search}`;

        return this.http.get<CorrespondentList>(requestUrl);
    }
}
