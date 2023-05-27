import { Component, OnInit, Input, ViewChild, ElementRef, Output, EventEmitter } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { TranslateService } from '@ngx-translate/core';
import { NotificationService } from '@service/notification/notification.service';
import { HeaderService } from '@service/header.service';
import { MatDialog, MatDialogRef } from '@angular/material/dialog';
import { AppService } from '@service/app.service';
import { SortPipe } from '../../../plugins/sorting.pipe';
import { UntypedFormControl } from '@angular/forms';
import { Observable, of } from 'rxjs';
import { debounceTime, filter, tap, switchMap, catchError, finalize, map } from 'rxjs/operators';
import { LatinisePipe } from 'ngx-pipes';
import { PrivilegeService } from '@service/privileges.service';
import { ContactModalComponent } from '../../administration/contact/modal/contact-modal.component';
import { ContactService } from '@service/contact.service';
import { FunctionsService } from '@service/functions.service';
import { CriteriaSearchService } from '@service/criteriaSearch.service';

interface DisplayContactList {
    'contact': any;
    'user': any;
    'entity': any;
}

@Component({
    selector: 'app-contact-autocomplete',
    templateUrl: 'contact-autocomplete.component.html',
    styleUrls: [
        'contact-autocomplete.component.scss',
        '../../indexation/indexing-form/indexing-form.component.scss'
    ],
    providers: [SortPipe, ContactService]
})

export class ContactAutocompleteComponent implements OnInit {

    /**
     * FormControl used when autocomplete is used in form and must be catched in a form control.
     */
    @Input('control') controlAutocomplete: UntypedFormControl = new UntypedFormControl();

    @Input() id: string = 'contact-autocomplete';
    @Input() exclusion: string = '';

    @Input() singleMode: boolean = false;
    @Input() inputMode: boolean = false;
    @Input() fromExternalWorkflow: boolean = false;

    @Output() retrieveDocumentEvent = new EventEmitter<string>();
    @Output() afterSelected = new EventEmitter<any>();
    @Output() removeContactEvent = new EventEmitter<any>();
    @Output() afterContactSelected = new EventEmitter<any>();

    @ViewChild('autoCompleteInput', { static: true }) autoCompleteInput: ElementRef;

    loading: boolean = false;
    loadingValues: boolean = true;

    key: string = 'id';

    canAdd: boolean = false;
    canUpdate: boolean = false;

    noResultFound: boolean = null;

    listInfo: string;
    myControl = new UntypedFormControl();
    filteredOptions: Observable<string[]>;
    options: any;
    valuesToDisplay: DisplayContactList = {
        contact : {},
        user: {},
        entity: {}
    };
    dialogRef: MatDialogRef<any>;
    newIds: number[] = [];
    customFields: any[] = [];

    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        private notify: NotificationService,
        public dialog: MatDialog,
        public functions: FunctionsService,
        public appService: AppService,
        private headerService: HeaderService,
        private latinisePipe: LatinisePipe,
        private privilegeService: PrivilegeService,
        private contactService: ContactService,
        private criteriaSearchService: CriteriaSearchService
    ) {

    }

    ngOnInit() {
        this.controlAutocomplete.setValue(this.controlAutocomplete.value === null || this.controlAutocomplete.value === '' ? [] : this.controlAutocomplete.value);
        this.canAdd = this.privilegeService.hasCurrentUserPrivilege('create_contacts');
        this.canUpdate = this.privilegeService.hasCurrentUserPrivilege('update_contacts');
        this.getCustomFields();
        this.initFormValue();
        this.initAutocompleteRoute();
    }

    initAutocompleteRoute() {
        this.listInfo = this.translate.instant('lang.autocompleteInfo');
        this.options = [];
        this.myControl.valueChanges
            .pipe(
                tap(() => {
                    this.noResultFound = null;
                    this.options = [];
                    this.listInfo = this.translate.instant('lang.autocompleteInfo');
                }),
                debounceTime(300),
                filter(value => value.length > 2),
                // distinctUntilChanged(),
                tap(() => this.loading = true),
                switchMap((data: any) => this.getDatas(data)),
                map((data: any) => {
                    data = data.filter((contact: any) => !this.singleMode || (contact.type !== 'entity' && contact.type !== 'contactGroup' && this.singleMode));
                    data = data.map((contact: any) => ({
                        ...contact,
                        civility: this.contactService.formatCivilityObject(contact.civility),
                        fillingRate: this.contactService.formatFillingObject(contact.fillingRate),
                        customFields: contact.customFields !== undefined ? this.formatCustomField(contact.customFields) : [],
                    }));
                    return data;
                }),
                tap((data: any) => {
                    if (data.length === 0) {
                        this.noResultFound = true;
                        this.listInfo = this.translate.instant('lang.noAvailableValue');
                    } else {
                        this.noResultFound = false;
                        this.listInfo = '';
                    }
                    this.options = data;
                    this.filteredOptions = of(this.options);
                    this.loading = false;
                })
            ).subscribe();
    }

    getCustomFields() {
        this.http.get('../rest/contactsCustomFields').pipe(
            tap((data: any) => {
                this.customFields = data.customFields.map((custom: any) => ({
                    id: custom.id,
                    label: custom.label
                }));
            })
        ).subscribe();
    }

    formatCustomField(data: any) {
        const arrCustomFields: any[] = [];

        Object.keys(data).forEach(element => {
            arrCustomFields.push({
                label: this.customFields.filter(custom => custom.id == element)[0].label,
                value: data[element]
            });
        });

        return arrCustomFields;
    }

    getDatas(data: string) {
        return this.http.get('../rest/autocomplete/correspondents' + this.exclusion, { params: { 'search': data } });
    }

    selectOpt(ev: any) {
        this.setFormValue(ev.option.value);
        if (!this.functions.empty(this.controlAutocomplete.value.find((item: any) => this.functions.empty(item.id)))) {
            this.controlAutocomplete.setValue(this.controlAutocomplete.value.filter((item: any) => !this.functions.empty(item.id)));
        }
        this.myControl.setValue('');
        this.afterContactSelected.emit(ev.option.value);
    }

    initFormValue() {
        this.controlAutocomplete.value.forEach((contact: any) => {
            if (!this.functions.empty(contact) && typeof contact === 'object'){
                this.valuesToDisplay[contact.type][contact.id] = {
                    type: '',
                    firstname: '',
                    lastname: this.translate.instant('lang.undefined'),
                    company: '',
                    fillingRate: {
                        color: ''
                    }
                };

                if (contact.type === 'contact') {
                    this.http.get('../rest/contacts/' + contact.id).pipe(
                        tap((data: any) => {
                            this.valuesToDisplay['contact'][data.id] = {
                                type: 'contact',
                                firstname: data.firstname,
                                lastname: data.lastname,
                                company: data.company,
                                fillingRate: !this.functions.empty(data.fillingRate) ? {
                                    color: this.contactService.getFillingColor(data.fillingRate.thresholdLevel)
                                } : '',
                                sector: data.sector
                            };
                        }),
                        finalize(() => this.loadingValues = false),
                        catchError((err: any) => {
                            this.notify.error(err.error.errors);
                            return of(false);
                        })
                    ).subscribe();
                } else if (contact.type === 'user') {
                    this.http.get('../rest/users/' + contact.id).pipe(
                        tap((data: any) => {
                            this.valuesToDisplay['user'][data.id] = {
                                type: 'user',
                                firstname: data.firstname,
                                lastname: data.lastname,
                                fillingRate: {
                                    color: ''
                                }
                            };
                        }),
                        finalize(() => this.loadingValues = false),
                        catchError((err: any) => {
                            this.notify.error(err.error.errors);
                            return of(false);
                        })
                    ).subscribe();
                } else if (contact.type === 'entity') {
                    this.http.get('../rest/entities/' + contact.id).pipe(
                        tap((data: any) => {
                            this.valuesToDisplay['entity'][data.id] = {
                                type: 'entity',
                                lastname: data.entity_label,
                                fillingRate: {
                                    color: ''
                                }
                            };
                        }),
                        finalize(() => this.loadingValues = false),
                        catchError((err: any) => {
                            this.notify.error(err.error.errors);
                            return of(false);
                        })
                    ).subscribe();
                }
            }
        });
    }

    setFormValue(item: any) {
        if (item.type === 'contactGroup') {
            this.http.get('../rest/contactsGroups/' + item.id + '/correspondents?limit=none').pipe(
                map((data: any) => {
                    const contacts = data.correspondents.map((correspondent: any) => ({
                        id: correspondent.id,
                        type: correspondent.type,
                        lastname: correspondent.name,
                        sector: correspondent.sector,
                        fillingRate: !this.functions.empty(correspondent.thresholdLevel) ? {
                            color: this.contactService.getFillingColor(correspondent.thresholdLevel)
                        } : ''
                    }));
                    return contacts;
                }),
                tap((contacts: any) => {
                    contacts.forEach((contact: any) => {
                        this.setContact(contact);
                    });
                }),
                finalize(() => this.loadingValues = false),
                catchError((err: any) => {
                    this.notify.handleSoftErrors(err);
                    return of(false);
                })
            ).subscribe();
        } else {
            this.setContact(item);
        }

    }

    setContact(contact: any) {
        if (this.controlAutocomplete.value.filter((contactItem: any) => contactItem.id === contact.id && contactItem.type === contact.type).length === 0) {
            let arrvalue = [];
            if (this.controlAutocomplete.value !== null) {
                arrvalue = this.controlAutocomplete.value;
            }
            this.valuesToDisplay[contact['type']][contact['id']] = contact;
            arrvalue.push(
                {
                    type: contact['type'],
                    id: contact['id'],
                    label: this.getFormatedContact(contact['type'], contact['id']),
                    sector: contact['sector']
                });
            this.controlAutocomplete.setValue(arrvalue);
            this.loadingValues = false;
        }
    }

    resetAutocomplete() {
        this.options = [];
        this.listInfo = this.translate.instant('lang.autocompleteInfo');
        this.myControl.setValue('');
    }

    unsetValue() {
        this.controlAutocomplete.setValue('');
        this.myControl.setValue('');
        this.myControl.enable();
    }

    removeItem(index: number) {
        this.removeContactEvent.emit(this.controlAutocomplete.value[index].id);
        if (this.newIds.indexOf(this.controlAutocomplete.value[index]) === -1) {
            const arrValue = this.controlAutocomplete.value;
            this.controlAutocomplete.value.splice(index, 1);
            this.controlAutocomplete.setValue(arrValue);
        } else {
            this.http.delete('../rest/tags/' + this.controlAutocomplete.value[index]).pipe(
                tap((data: any) => {
                    const arrValue = this.controlAutocomplete.value;
                    this.controlAutocomplete.value.splice(index, 1);
                    this.controlAutocomplete.setValue(arrValue);
                }),
                catchError((err: any) => {
                    this.notify.handleErrors(err);
                    return of(false);
                })
            ).subscribe();
        }
    }

    openContact(contact: any = null) {
        this.retrieveDocumentEvent.emit();
        const dialogRef = this.dialog.open(
            ContactModalComponent,
            {
                maxWidth: '100vw',
                width: contact === null ? '99vw' : 'auto',
                panelClass: contact === null ? 'maarch-full-height-modal' : 'maarch-modal',
                disableClose: true,
                data: {
                    editMode: this.canUpdate,
                    contactId: contact !== null ? contact.id : null,
                    contactType: contact !== null ? contact.type : null }
            }
        );

        dialogRef.afterClosed().pipe(
            filter((data: number) => data !== undefined),
            tap((contactId: number) => {
                const newContact = {
                    type: 'contact',
                    id: contactId
                };
                this.setFormValue(newContact);
                this.initFormValue();
            }),
            catchError((err: any) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    empty(value: any) {
        if (value !== null && value !== '' && value !== undefined) {
            return false;
        } else {
            return true;
        }
    }

    resetAll() {
        this.controlAutocomplete.setValue([]);
        this.removeContactEvent.emit(false);
        this.valuesToDisplay = {
            contact : {},
            user: {},
            entity: {}
        };
    }

    getFormatedContact(type: string, id: number) {
        return this.contactService.formatContact(this.valuesToDisplay[type][id]);
    }

    getInputValue() {
        return this.myControl.value;
    }

    setInputValue(value: string) {
        this.myControl.setValue(value);
    }

    resetInputValue() {
        this.myControl.setValue('');
    }

    private _filter(value: string): string[] {
        if (typeof value === 'string') {
            const filterValue = this.latinisePipe.transform(value.toLowerCase());
            return this.options.filter((option: any) => this.latinisePipe.transform(option[this.key].toLowerCase()).includes(filterValue));
        } else {
            return this.options;
        }
    }
}
