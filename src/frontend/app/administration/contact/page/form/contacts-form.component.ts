import { Component, OnInit, ViewChild, EventEmitter, Input, Output } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { TranslateService } from '@ngx-translate/core';
import { NotificationService } from '@service/notification/notification.service';
import { MatSidenav } from '@angular/material/sidenav';
import { AppService } from '@service/app.service';
import { MatDialog } from '@angular/material/dialog';
import { switchMap, catchError, filter, exhaustMap, tap, debounceTime, distinctUntilChanged, finalize, map, startWith } from 'rxjs/operators';
import { UntypedFormControl, Validators, ValidatorFn } from '@angular/forms';
import { ActivatedRoute, Router } from '@angular/router';
import { ContactService } from '@service/contact.service';
import { FunctionsService } from '@service/functions.service';
import { trigger, transition, style, animate } from '@angular/animations';
import { Observable, of } from 'rxjs';
import { environment } from '../../../../../environments/environment';
import { LatinisePipe } from 'ngx-pipes';
import { InputCorrespondentGroupComponent } from '../../group/inputCorrespondent/input-correspondent-group.component';
import { ConfirmComponent } from '@plugins/modal/confirm.component';
import { ContactSearchModalComponentComponent } from './contactSearchModal/contact-search-modal.component';

@Component({
    selector: 'app-contact-form',
    templateUrl: 'contacts-form.component.html',
    styleUrls: ['contacts-form.component.scss'],
    providers: [ContactService],
    animations: [
        trigger('hideShow', [
            transition(
                ':enter', [
                    style({ height: '0px' }),
                    animate('200ms', style({ 'height': '30px' }))
                ]
            ),
            transition(
                ':leave', [
                    style({ height: '30px' }),
                    animate('200ms', style({ 'height': '0px' }))
                ]
            )
        ]),
    ],
})
export class ContactsFormComponent implements OnInit {

    @ViewChild('snav2', { static: true }) public sidenavRight: MatSidenav;
    @ViewChild('appInputCorrespondentGroup', { static: false }) appInputCorrespondentGroup: InputCorrespondentGroupComponent;

    @Input() creationMode: boolean = true;
    @Input() contactId: number = null;
    @Input() actionButton: boolean = true;
    @Input() defaultName: string = '';

    @Output() onSubmitEvent = new EventEmitter<number>();
    @Output() linkContact = new EventEmitter<number>();

    countries: any = [];
    countriesFilteredResult: Observable<string[]>;
    postcodesTownFilteredResult: Observable<string[]>;
    postcodesFilteredResult: Observable<string[]>;
    countryControl = new UntypedFormControl();

    loading: boolean = false;

    maarch2maarchUrl: string = this.functions.getDocBaseUrl() + '/guat/guat_exploitation/maarch2maarch.html';

    contactUnit = [
        {
            id: 'mainInfo',
            label: this.translate.instant('lang.denomination')
        },
        {
            id: 'address',
            label: this.translate.instant('lang.address')
        },
        {
            id: 'complement',
            label: this.translate.instant('lang.additionals')
        },
        {
            id: 'maarch2maarch',
            label: 'Maarch2Maarch'
        }
    ];

    contactForm: any[] = [
        {
            id: 'company',
            unit: 'mainInfo',
            label: this.translate.instant('lang.contactsParameters_company'),
            type: 'string',
            control: new UntypedFormControl(),
            required: false,
            display: true,
            filling: false,
            values: []
        },
        {
            id: 'civility',
            unit: 'mainInfo',
            label: this.translate.instant('lang.contactsParameters_civility'),
            type: 'select',
            control: new UntypedFormControl(),
            required: false,
            display: false,
            filling: false,
            values: []
        },
        {
            id: 'firstname',
            unit: 'mainInfo',
            label: this.translate.instant('lang.contactsParameters_firstname'),
            type: 'string',
            control: new UntypedFormControl(),
            required: false,
            display: false,
            filling: false,
            values: []
        },
        {
            id: 'lastname',
            unit: 'mainInfo',
            label: this.translate.instant('lang.contactsParameters_lastname'),
            type: 'string',
            control: new UntypedFormControl(),
            required: false,
            display: false,
            filling: false,
            values: []
        },
        {
            id: 'function',
            unit: 'mainInfo',
            label: this.translate.instant('lang.contactsParameters_function'),
            type: 'string',
            control: new UntypedFormControl(),
            required: false,
            display: false,
            filling: false,
            values: []
        },
        {
            id: 'department',
            unit: 'mainInfo',
            label: this.translate.instant('lang.contactsParameters_department'),
            type: 'string',
            control: new UntypedFormControl(),
            required: false,
            display: false,
            filling: false,
            values: []
        },
        {
            id: 'email',
            unit: 'mainInfo',
            label: this.translate.instant('lang.email'),
            type: 'string',
            control: new UntypedFormControl(),
            required: false,
            display: true,
            filling: false,
            values: []
        },
        {
            id: 'phone',
            unit: 'mainInfo',
            label: this.translate.instant('lang.phoneNumber'),
            type: 'string',
            control: new UntypedFormControl(),
            required: false,
            display: true,
            filling: false,
            values: []
        },
        {
            id: 'notes',
            unit: 'mainInfo',
            label: this.translate.instant('lang.note'),
            type: 'string',
            control: new UntypedFormControl(),
            required: false,
            display: false,
            filling: false,
            values: []
        },
        {
            id: 'addressAdditional1',
            unit: 'address',
            label: this.translate.instant('lang.contactsParameters_addressAdditional1'),
            type: 'string',
            control: new UntypedFormControl(),
            required: false,
            display: false,
            filling: false,
            values: []
        },
        {
            id: 'addressNumber',
            unit: 'address',
            label: this.translate.instant('lang.contactsParameters_addressNumber'),
            type: 'string',
            control: new UntypedFormControl(),
            required: false,
            display: false,
            filling: false,
            values: []
        },
        {
            id: 'addressStreet',
            unit: 'address',
            label: this.translate.instant('lang.contactsParameters_addressStreet'),
            type: 'string',
            control: new UntypedFormControl(),
            required: false,
            display: false,
            filling: false,
            values: []
        },
        {
            id: 'addressAdditional2',
            unit: 'address',
            label: this.translate.instant('lang.contactsParameters_addressAdditional2'),
            type: 'string',
            control: new UntypedFormControl(),
            required: false,
            display: false,
            filling: false,
            values: []
        },
        {
            id: 'addressPostcode',
            unit: 'address',
            label: this.translate.instant('lang.contactsParameters_addressPostcode'),
            type: 'string',
            control: new UntypedFormControl(),
            required: false,
            display: false,
            filling: false,
            values: []
        },
        {
            id: 'addressTown',
            unit: 'address',
            label: this.translate.instant('lang.contactsParameters_addressTown'),
            type: 'string',
            control: new UntypedFormControl(),
            required: false,
            display: false,
            filling: false,
            values: []
        },
        {
            id: 'addressCountry',
            unit: 'address',
            label: this.translate.instant('lang.contactsParameters_addressCountry'),
            type: 'string',
            control: new UntypedFormControl(),
            required: false,
            display: false,
            filling: false,
            values: []
        },
        {
            id: 'sector',
            unit: 'address',
            label: this.translate.instant('lang.contactsParameters_sector'),
            type: 'string',
            control: new UntypedFormControl({ value: '', disabled: true }),
            required: false,
            display: false,
            filling: false,
            values: []
        },
        {
            id: 'url',
            unit: 'maarch2maarch',
            label: this.translate.instant('lang.communicationMean'),
            desc: `${this.translate.instant('lang.communicationMeanDesc')} (${this.translate.instant('lang.see')} <a href="${this.maarch2maarchUrl}" target="_blank">MAARCH2MAARCH</a>)`,
            type: 'string',
            control: new UntypedFormControl(),
            required: false,
            display: false,
            filling: false,
            values: []
        },
        {
            id: 'externalId_m2m',
            unit: 'maarch2maarch',
            label: this.translate.instant('lang.IdMaarch2Maarch'),
            desc: `${this.translate.instant('lang.m2mContactInfo')} (${this.translate.instant('lang.see')} <a href="${this.maarch2maarchUrl}" target="_blank">MAARCH2MAARCH</a>)`,
            type: 'string',
            control: new UntypedFormControl(),
            required: false,
            display: false,
            filling: false,
            values: []
        },
        {
            id: 'login',
            unit: 'maarch2maarch',
            label: this.translate.instant('lang.userIdMaarch2Maarch'),
            desc: `${this.translate.instant('lang.userIdMaarch2MaarchDesc')}`,
            type: 'string',
            control: new UntypedFormControl(),
            required: false,
            display: false,
            filling: false,
            values: []
        },
        {
            id: 'password',
            unit: 'maarch2maarch',
            label: this.translate.instant('lang.userPasswordMaarch2Maarch'),
            desc: `${this.translate.instant('lang.userPasswordMaarch2MaarchDesc')}`,
            type: 'string',
            control: new UntypedFormControl(),
            required: false,
            display: false,
            filling: false,
            values: []
        },
        {
            id: 'email_m2m',
            unit: 'maarch2maarch',
            label: this.translate.instant('lang.email'),
            desc: `${this.translate.instant('lang.m2mEmailDesc')}`,
            type: 'string',
            control: new UntypedFormControl(),
            required: false,
            display: false,
            filling: false,
            values: []
        },
        {
            id: 'correspondentsGroups',
            unit: 'complement',
            label: this.translate.instant('lang.correspondentsGroups'),
            desc: this.translate.instant('lang.correspondentsGroups'),
            type: 'correspondentsGroups',
            control: new UntypedFormControl(),
            required: false,
            display: true,
            filling: false,
            values: []
        }
    ];
    initCorrespondentsGroups: boolean = true;

    addressBANInfo: string = '';
    addressBANMode: boolean = true;
    addressBANControl = new UntypedFormControl();
    addressBANLoading: boolean = false;
    addressBANResult: any[] = [];
    addressBANFilteredResult: Observable<string[]>;
    addressBANCurrentDepartment: string = '75';
    departmentList: any[] = [];

    fillingParameters: any = null;
    fillingRate: any = {
        class: 'warn',
        color: this.contactService.getFillingColor('first'),
        value: 0
    };

    companyFound: any = null;
    communicationMeanInfo: string = '';
    communicationMeanResult: any[] = [];
    communicationMeanLoading: boolean = false;
    communicationMeanFilteredResult: Observable<any[]>;

    externalId_m2mInfo: string = '';
    externalId_m2mResult: any[] = [];
    externalId_m2mLoading: boolean = false;
    externalId_m2mFilteredResult: Observable<any[]>;
    annuaryM2MId: any = null;

    annuaryEnabled: boolean = false;

    autocompleteContactName: any[] = [];
    contactChanged: boolean = false;

    contactNameClone: any = null;

    fromAdministration: boolean = false;
    currentRoute: string = '';

    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        private router: Router,
        private notify: NotificationService,
        public appService: AppService,
        public dialog: MatDialog,
        public contactService: ContactService,
        public functions: FunctionsService,
        private latinisePipe: LatinisePipe,
        private activatedRoute: ActivatedRoute,
    ) { }

    ngOnInit(): void {

        this.loading = true;

        this.currentRoute = this.activatedRoute.snapshot['_routerState'].url;
        this.fromAdministration = this.currentRoute.includes('administration') ? true : false;

        this.initBanSearch();

        if (this.contactId === null) {

            this.creationMode = true;

            this.http.get('../rest/contactsParameters').pipe(
                tap((data: any) => {
                    this.fillingParameters = data.contactsFilling;
                    this.initElemForm(data);
                    if (!this.functions.empty(this.defaultName)) {
                        this.contactForm.find(contact => contact.id === 'company').control.setValue(this.defaultName);
                    }
                    this.annuaryEnabled = data.annuaryEnabled;
                }),
                exhaustMap(() => this.http.get('../rest/civilities')),
                tap((data: any) => {
                    this.initCivilities(data.civilities);
                }),
                exhaustMap(() => this.http.get('../rest/contactsCustomFields')),
                tap((data: any) => {
                    this.initCustomElementForm(data);
                    this.initAutocompleteAddressBan();
                    this.initAutocompleteCommunicationMeans();
                    this.initAutocompleteExternalIdM2M();
                    this.getCountries();
                    this.initAutocompleteCountriesAndPostcodes();
                }),
                finalize(() => this.loading = false),
                catchError((err: any) => {
                    this.notify.handleErrors(err);
                    return of(false);
                })
            ).subscribe();
        } else {
            this.creationMode = false;

            this.contactForm.forEach(element => {
                element.display = element.id === 'correspondentsGroups';
            });

            this.http.get('../rest/contactsParameters').pipe(
                tap((data: any) => {
                    this.fillingParameters = data.contactsFilling;
                    this.initElemForm(data);
                    this.annuaryEnabled = data.annuaryEnabled;
                }),
                exhaustMap(() => this.http.get('../rest/civilities')),
                tap((data: any) => {
                    this.initCivilities(data.civilities);
                }),
                exhaustMap(() => this.http.get('../rest/contactsCustomFields')),
                tap((data: any) => {
                    this.initCustomElementForm(data);
                    this.initAutocompleteAddressBan();
                    this.initAutocompleteCommunicationMeans();
                    this.initAutocompleteExternalIdM2M();
                    this.getCountries();
                    this.initAutocompleteCountriesAndPostcodes();
                }),
                exhaustMap(() => this.http.get('../rest/contacts/' + this.contactId)),
                map((data: any) => {
                    // data.civility = this.contactService.formatCivilityObject(data.civility);
                    if (data.communicationMeans !== null) {
                        this.setCommunicationMeans(data.communicationMeans);
                    }
                    data.fillingRate = this.contactService.formatFillingObject(data.fillingRate);
                    return data;
                }),
                tap((data) => {
                    this.setContactData(data);
                    this.setContactDataExternal(data);
                }),
                filter((data: any) => data.customFields !== null),
                tap((data: any) => {
                    this.setContactCustomData(data);
                }),
                finalize(() => this.loading = false),
                catchError((err: any) => {
                    this.notify.handleErrors(err);
                    return of(false);
                })
            ).subscribe();
        }
    }

    initElemForm(data: any) {
        let valArr: ValidatorFn[] = [];

        data.contactsParameters.forEach((element: any) => {
            let targetField: any = this.contactForm.filter(contact => contact.id === element.identifier)[0];

            valArr = [];

            if (targetField === undefined && element.identifier.split('_')[1] !== undefined) {
                let field: any = {};

                field = {
                    id: `customField_${element.identifier.split('_')[1]}`,
                    unit: 'complement',
                    label: null,
                    type: null,
                    control: new UntypedFormControl(),
                    required: false,
                    display: false,
                    values: []
                };
                this.contactForm.push(field);

                targetField = this.contactForm.filter(contact => contact.id === field.id)[0];
            }
            if (targetField !== undefined) {

                if ((element.filling && this.fillingParameters.enable && this.creationMode) || element.mandatory) {
                    targetField.display = true;
                }

                if (element.filling && this.fillingParameters.enable) {
                    targetField.filling = true;
                }

                if (element.identifier === 'email') {
                    valArr.push(Validators.email);
                } else if (element.identifier === 'phone') {
                    valArr.push(Validators.pattern(/^\+?((|\ |\.|\(|\)|\-)?(\d)*)*\d$/));
                }

                if (element.mandatory) {
                    targetField.required = true;
                    valArr.push(Validators.required);
                }

                targetField.control.setValidators(valArr);
            }
        });
    }

    initCivilities(civilities: any) {
        this.contactForm.filter(contact => contact.id === 'civility')[0].values = civilities;
    }

    getCountries() {
        this.http.get('../rest/registeredMail/countries').pipe(
            tap((data: any) => {
                this.countries = data.countries.map(
                    (item: any) => this.latinisePipe.transform(item.toUpperCase()));
            }),
            catchError((err: any) => {
                this.notify.handleSoftErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    initAutocompleteCountriesAndPostcodes() {
        this.contactForm.map((field: any) => {
            if (field.id === 'addressCountry') {
                this.countriesFilteredResult = field.control.valueChanges
                    .pipe(
                        startWith(''),
                        map((value: any) => this._filter(value))
                    );
            }
            if (field.id === 'addressPostcode') {
                this.postcodesFilteredResult = field.control.valueChanges
                    .pipe(
                        debounceTime(300),
                        filter((value: string) => value.length > 2),
                        distinctUntilChanged(),
                        exhaustMap((value: string) => this.http.get('../rest/autocomplete/postcodes?postcode=' + value)),
                        map((data: any) => data.postcodes),
                    );
            }
            if (field.id === 'addressTown') {
                this.postcodesTownFilteredResult = field.control.valueChanges
                    .pipe(
                        debounceTime(300),
                        filter((value: string) => value.length > 2),
                        distinctUntilChanged(),
                        exhaustMap((value: string) => this.http.get('../rest/autocomplete/postcodes?town=' + value)),
                        map((data: any) => data.postcodes),
                    );
            }
        });
    }

    selectPostcode(ev: any) {
        this.contactForm.find(contact => contact.id === 'addressPostcode')?.control.setValue(ev.option.value.postcode);
        this.contactForm.find(contact => contact.id === 'addressTown')?.control.setValue(ev.option.value.town);
    }

    selectCountry(ev: any) {
        const indexFieldAddressCountry = this.contactForm.map(field => field.id).indexOf('addressCountry');
        this.contactForm[indexFieldAddressCountry].control.setValue(ev.option.value);
    }

    initCustomElementForm(data: any) {
        let valArr: ValidatorFn[] = [];

        let field: any = {};

        data.customFields.forEach((element: any) => {
            valArr = [];
            field = this.contactForm.filter(contact => contact.id === 'customField_' + element.id)[0];

            if (field !== undefined) {
                field.label = element.label;
                field.type = element.type;
                field.values = element.values.map((value: any) => ({ id: value, label: value }));
                if (element.type === 'integer') {
                    valArr.push(Validators.pattern(/^[+-]?([0-9]+([.][0-9]*)?|[.][0-9]+)$/));
                    field.control.setValidators(valArr);
                }
            }
        });
    }

    setContactData(data: any) {
        let indexField = -1;

        Object.keys(data).forEach(element => {
            indexField = this.contactForm.map(field => field.id).indexOf(element);

            if (!this.isEmptyValue(data[element]) && indexField > -1) {
                if (element === 'civility') {
                    this.contactForm[indexField].control.setValue(data[element].id);
                } else {
                    this.contactForm[indexField].control.setValue(data[element]);
                }

                if (element === 'company' && this.isEmptyValue(this.contactForm.filter(contact => contact.id === 'lastname')[0].control.value)) {
                    this.contactForm.filter(contact => contact.id === 'lastname')[0].display = false;
                } else if (element === 'lastname' && this.isEmptyValue(this.contactForm.filter(contact => contact.id === 'company')[0].control.value)) {
                    this.contactForm.filter(contact => contact.id === 'company')[0].display = false;
                }

                this.contactForm[indexField].display = true;
            }
        });

        if (this.isEmptyValue(this.contactForm.filter(contact => contact.id === 'company')[0].control.value) && !this.isEmptyValue(this.contactForm.filter(contact => contact.id === 'lastname')[0].control.value)) {
            this.contactForm.filter(contact => contact.id === 'company')[0].display = false;
        }

        this.checkFilling();
    }

    setContactDataExternal(data: any) {
        if (data.externalId !== undefined) {
            Object.keys(data.externalId).forEach(id => {

                if (!this.isEmptyValue(data.externalId[id])) {
                    if (id === 'm2m') {
                        this.contactForm.filter(contact => contact.id === 'externalId_m2m')[0].control.setValue(data.externalId[id]);
                        this.contactForm.filter(contact => contact.id === 'externalId_m2m')[0].display = true;
                    } else if (id === 'm2m_annuary_id') {
                        this.contactForm.push({
                            id: `externalId_${id}`,
                            unit: 'maarch2maarch',
                            label: id,
                            type: 'string',
                            control: new UntypedFormControl({ value: data.externalId[id], disabled: true }),
                            required: false,
                            display: true,
                            filling: false,
                            values: []
                        });
                    } else {
                        this.contactForm.push({
                            id: `externalId_${id}`,
                            unit: 'complement',
                            label: id,
                            type: 'string',
                            control: new UntypedFormControl({ value: data.externalId[id], disabled: true }),
                            required: false,
                            display: true,
                            filling: false,
                            values: []
                        });

                    }
                }
            });
        }
    }

    setContactCustomData(data: any) {
        let indexField = -1;
        Object.keys(data.customFields).forEach(element => {
            indexField = this.contactForm.map(field => field.id).indexOf('customField_' + element);
            if (!this.isEmptyValue(data.customFields[element]) && indexField > -1) {
                if (this.contactForm[indexField].type === 'date') {
                    const date = new Date(this.functions.formatFrenchDateToTechnicalDate(data.customFields[element]));
                    data.customFields[element] = date;
                }
                this.contactForm[indexField].control.setValue(data.customFields[element]);
                this.contactForm[indexField].display = true;
            }
        });
        this.checkFilling();
    }

    initBanSearch() {
        this.http.get('../rest/ban/availableDepartments').pipe(
            tap((data: any) => {
                if (data.default !== null && data.departments.indexOf(data.default.toString()) !== - 1) {
                    this.addressBANCurrentDepartment = data.default;
                }
                this.departmentList = data.departments;
            }),
            catchError((err: any) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    isValidForm() {
        let state = true;

        this.contactForm.filter(contact => contact.display).forEach(element => {
            if (element.control.status !== 'DISABLED' && element.control.status !== 'VALID') {
                state = false;
            }
            element.control.markAsTouched();
        });

        return state;
    }

    onSubmit() {
        this.checkFilling();
        if (this.addressBANMode && this.emptyAddress() && !this.noAddressRequired()) {
            this.notify.error(this.translate.instant('lang.chooseBAN'));
        } else if (this.isValidForm()) {
            if (this.contactId !== null) {
                this.updateContact();
            } else {
                this.createContact();
            }
        } else {
            this.notify.error(this.translate.instant('lang.mustFixErrors'));
        }

    }

    createContact() {
        this.http.post('../rest/contacts', this.formatContact()).pipe(
            tap((data: any) => {
                this.onSubmitEvent.emit(data.id);
                if (this.appInputCorrespondentGroup !== undefined) {
                    this.appInputCorrespondentGroup.linkGrpAfterCreation(data.id, 'contact');
                }
                this.notify.success(this.translate.instant('lang.contactAdded'));
                if (!this.functions.empty(data.warning)) {
                    this.notify.error(data.warning);
                }
            }),
            // finalize(() => this.loading = false),
            catchError((err: any) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    updateContact() {
        this.http.put(`../rest/contacts/${this.contactId}`, this.formatContact()).pipe(
            tap((data: any) => {
                this.onSubmitEvent.emit(this.contactId);
                this.notify.success(this.translate.instant('lang.contactUpdated'));
                if (!this.functions.empty(data) && !this.functions.empty(data.warning)) {
                    this.notify.error(data.warning);
                }
            }),
            // finalize(() => this.loading = false),
            catchError((err: any) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    formatContact() {
        const contact: any = {};
        contact['customFields'] = {};
        contact['externalId'] = {};
        const regex = /customField_[.]*/g;
        const regex2 = /externalId_[.]*/g;

        this.contactForm.filter(field => field.display).forEach(element => {
            if (element.type === 'date' && !this.functions.empty(element.control.value)) {
                const date = new Date(element.control.value);
                element.control.value = this.functions.formatDateObjectToDateString(date);
            }
            if (element.id.match(regex) !== null) {
                contact['customFields'][element.id.split(/_(.+)/)[1]] = element.control.value;
            } else if (element.id.match(regex2) !== null) {
                contact['externalId'][element.id.split(/_(.+)/)[1]] = element.control.value;
            } else if (element.utit !== 'maarch2maarch') {
                contact[element.id] = element.control.value;
            }
        });
        const m2mData: any[] = this.contactForm.filter((element: any) => element.unit === 'maarch2maarch').map((item: any) => ({
            id: item.id,
            value: item.control.value
        }));
        let communicationMeans = {
            url: m2mData.find((item: any) => item.id === 'url').value,
            externalId_m2m: m2mData.find((item: any) => item.id === 'externalId_m2m').value,
            login: m2mData.find((item: any) => item.id === 'login').value,
            password: m2mData.find((item: any) => item.id === 'password').value,
            email: m2mData.find((item: any) => item.id === 'email_m2m').value,
        };

        if (Object.keys(communicationMeans).every((item: any) => this.functions.empty(communicationMeans[item]))) {
            communicationMeans = null;
        }
        return { ... contact, communicationMeans};
    }

    isEmptyUnit(id: string) {
        if (this.contactForm.filter(field => field.display && field.unit === id).length === 0) {
            return true;
        } else {
            return false;
        }
    }

    initForm() {
        this.contactForm.forEach(element => {
            element.control = new UntypedFormControl({ value: '', disabled: false });
        });
    }

    toogleAllFieldsUnit(idUnit: string) {
        this.contactForm.filter(field => field.unit === idUnit && field.id !== 'sector').forEach((element: any) => {
            element.display = true;
        });
    }

    noField(id: string) {
        if (this.contactForm.filter(field => !field.display && field.unit === id && field.id !== 'sector').length === 0) {
            return true;
        } else {
            return false;
        }
    }

    isEmptyValue(value: string) {

        if (value === null || value === undefined) {
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

    checkCompany(field: any) {

        if (field.id === 'company' && field.control.value !== '' && (this.companyFound === null || this.companyFound.company !== field.control.value)) {
            this.http.get(`../rest/autocomplete/contacts/company?search=${field.control.value}`).pipe(
                tap(() => this.companyFound = null),
                filter((data: any) => data.length > 0),
                tap((data) => {
                    if (!this.functions.empty(data[0].addressNumber) || !this.functions.empty(data[0].addressStreet) || !this.functions.empty(data[0].addressPostcode) || !this.functions.empty(data[0].addressTown) || !this.functions.empty(data[0].addressCountry)) {
                        this.companyFound = data[0];
                    }

                }),
                // finalize(() => this.loading = false),
                catchError((err: any) => {
                    this.notify.handleErrors(err);
                    return of(false);
                })
            ).subscribe();
        } else if (field.id === 'company' && field.control.value === '') {
            this.companyFound = null;
        }
    }

    setAddress(contact: any, disableBan: boolean = true) {
        this.companyFound = null;
        let indexField = -1;
        Object.keys(contact).forEach(element => {
            indexField = this.contactForm.map(field => field.id).indexOf(element);
            if (!this.isEmptyValue(contact[element]) && indexField > -1 && ['company', 'addressNumber', 'addressStreet', 'addressAdditional2', 'addressPostcode', 'addressTown', 'addressCountry'].indexOf(element) > -1) {
                this.contactForm[indexField].control.setValue(contact[element]);
                this.contactForm[indexField].display = true;
            }
        });
        this.checkFilling();
        this.http.get('../rest/contacts/sector', { params: { 'addressNumber': contact['addressNumber'], 'addressStreet': contact['addressStreet'], 'addressPostcode': contact['addressPostcode'], 'addressTown': contact['addressTown'] } }).pipe(
            tap((data: any) => {
                const sectorIndex = this.contactForm.findIndex(element => element.id === 'sector');
                if (data.sector !== null) {
                    this.contactForm[sectorIndex].control.setValue(data.sector.label);
                    this.contactForm[sectorIndex].display = true;
                } else {
                    this.contactForm[sectorIndex].control.setValue('');
                    this.contactForm[sectorIndex].display = false;
                }
            }),
            catchError((err: any) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();

        this.addressBANMode = disableBan ? false : true;
    }

    setCommunicationMeans(communicationMeans: any) {
        let indexField = -1;
        Object.keys(communicationMeans).forEach(element => {
            element = element === 'email' ? 'email_m2m' : element;
            indexField = this.contactForm.map(field => field.id).indexOf(element);
            if (!this.isEmptyValue(communicationMeans[element === 'email_m2m' ? 'email' : element]) && indexField > -1 && ['url', 'login', 'email_m2m'].indexOf(element) > -1) {
                if (element === 'email_m2m') {
                    this.contactForm[indexField].control.setValue(communicationMeans['email']);
                } else {
                    this.contactForm[indexField].control.setValue(communicationMeans[element]);
                }
                this.contactForm[indexField].display = true;
            }
        });
    }

    canDelete(field: any) {
        if (field.id === 'company') {
            const lastname = this.contactForm.filter(contact => contact.id === 'lastname')[0];
            if (lastname.display && !this.isEmptyValue(lastname.control.value)) {
                const valArr: ValidatorFn[] = [];
                field.control.setValidators(valArr);
                field.required = false;
                return true;
            } else {
                const valArr: ValidatorFn[] = [];
                valArr.push(Validators.required);
                field.control.setValidators(valArr);
                field.required = true;
                return false;
            }
        } else if (field.id === 'lastname') {
            const company = this.contactForm.filter(contact => contact.id === 'company')[0];
            if (company.display && !this.isEmptyValue(company.control.value)) {
                const valArr: ValidatorFn[] = [];
                field.control.setValidators(valArr);
                field.required = false;
                return true;
            } else {
                const valArr: ValidatorFn[] = [];
                valArr.push(Validators.required);
                field.control.setValidators(valArr);
                field.required = true;
                return false;
            }
        } else if (field.required || field.control.disabled) {
            return false;
        } else {
            return true;
        }
    }

    removeField(field: any) {
        field.display = !field.display;
        field.control.reset();
        if ((field.id === 'externalId_m2m' || field.id === 'url') && !field.display) {
            const indexFieldAnnuaryId = this.contactForm.map(item => item.id).indexOf('externalId_m2m_annuary_id');
            if (indexFieldAnnuaryId > -1) {
                this.contactForm.splice(indexFieldAnnuaryId, 1);
            }
        }
        this.checkFilling();
        this.checkContactName(field);
    }

    handleCorrespondentsGroupsField(correspondentsGroups: any, field: any) {
        if (!this.functions.empty(this.contactId) && correspondentsGroups.length === 0 && this.initCorrespondentsGroups) {
            this.removeField(field);
        }
        this.initCorrespondentsGroups = false;
    }

    initAutocompleteCommunicationMeans() {
        this.communicationMeanInfo = this.translate.instant('lang.autocompleteInfo');
        this.communicationMeanResult = [];
        const indexFieldCommunicationMeans = this.contactForm.map(field => field.id).indexOf('url');
        this.contactForm[indexFieldCommunicationMeans].control.valueChanges
            .pipe(
                debounceTime(300),
                filter((value: string) => value.length > 2),
                distinctUntilChanged(),
                tap(() => this.communicationMeanLoading = true),
                switchMap((data: any) => this.http.get('../rest/autocomplete/ouM2MAnnuary', { params: { 'company': data } })),
                tap((data: any) => {
                    if (this.isEmptyValue(data)) {
                        this.communicationMeanInfo = this.translate.instant('lang.noAvailableValue');
                    } else {
                        this.communicationMeanInfo = '';
                    }
                    this.communicationMeanResult = data;
                    this.communicationMeanFilteredResult = of(this.communicationMeanResult);
                    this.communicationMeanLoading = false;
                }),
                catchError((err: any) => {
                    this.communicationMeanInfo = err.error.errors;
                    this.communicationMeanLoading = false;
                    return of(false);
                })
            ).subscribe();
    }

    selectCommunicationMean(ev: any) {
        const indexFieldCommunicationMeans = this.contactForm.map(field => field.id).indexOf('url');
        this.contactForm[indexFieldCommunicationMeans].control.setValue(ev.option.value.communicationValue);

        const indexFieldExternalId = this.contactForm.map(field => field.id).indexOf('externalId_m2m');
        this.contactForm[indexFieldExternalId].control.setValue(ev.option.value.businessIdValue + '/');
        this.contactForm[indexFieldExternalId].display = true;

        const indexFieldDepartment = this.contactForm.map(field => field.id).indexOf('department');
        this.contactForm[indexFieldDepartment].display = true;
    }

    initAutocompleteExternalIdM2M() {
        this.externalId_m2mInfo = this.translate.instant('lang.autocompleteInfo');
        this.externalId_m2mResult = [];
        const indexFieldCommunicationMeans = this.contactForm.map(field => field.id).indexOf('url');
        const indexFieldExternalId = this.contactForm.map(field => field.id).indexOf('externalId_m2m');
        this.contactForm[indexFieldExternalId].control.valueChanges
            .pipe(
                debounceTime(300),
                distinctUntilChanged(),
                tap(() => this.externalId_m2mLoading = true),
                switchMap((data: any) => this.http.get('../rest/autocomplete/businessIdM2MAnnuary', { params: { 'query': data, 'communicationValue': this.contactForm[indexFieldCommunicationMeans].control.value } })),
                tap((data: any) => {
                    if (this.isEmptyValue(data)) {
                        this.externalId_m2mInfo = this.translate.instant('lang.noAvailableValue');
                    } else {
                        this.externalId_m2mInfo = '';
                    }
                    this.externalId_m2mResult = data;
                    this.externalId_m2mFilteredResult = of(this.externalId_m2mResult);
                    this.externalId_m2mLoading = false;
                }),
                catchError((err: any) => {
                    this.externalId_m2mInfo = err.error.errors;
                    this.externalId_m2mLoading = false;
                    return of(false);
                })
            ).subscribe();
    }

    selectExternalIdM2M(ev: any) {
        const indexFieldExternalId = this.contactForm.map(field => field.id).indexOf('externalId_m2m');
        this.contactForm[indexFieldExternalId].control.setValue(ev.option.value.businessIdValue);

        const indexFieldAnnuaryId = this.contactForm.map(field => field.id).indexOf('externalId_m2m_annuary_id');
        this.contactForm[indexFieldAnnuaryId].control.setValue(ev.option.value.entryuuid);
    }

    resetAutocompleteExternalIdM2M() {
        let indexFieldAnnuaryId = -1;
        indexFieldAnnuaryId = this.contactForm.map(field => field.id).indexOf('externalId_m2m_annuary_id');
        if (indexFieldAnnuaryId > -1) {
            this.contactForm[indexFieldAnnuaryId].control.setValue('');
        } else {
            this.contactForm.push({
                id: 'externalId_m2m_annuary_id',
                unit: 'maarch2maarch',
                label: 'm2m_annuary_id',
                type: 'string',
                control: new UntypedFormControl({ value: '', disabled: true }),
                required: false,
                display: true,
                filling: false,
                values: []
            });
        }
    }

    resetM2MFields() {
        let indexFieldAnnuaryId = -1;
        indexFieldAnnuaryId = this.contactForm.map(field => field.id).indexOf('externalId_m2m');
        this.contactForm[indexFieldAnnuaryId].control.setValue('');
        this.resetAutocompleteExternalIdM2M();
    }

    initAutocompleteAddressBan() {
        this.addressBANInfo = this.translate.instant('lang.autocompleteInfo');
        this.addressBANResult = [];
        this.addressBANControl.valueChanges
            .pipe(
                debounceTime(300),
                filter(value => value.length > 2),
                distinctUntilChanged(),
                tap(() => this.addressBANLoading = true),
                switchMap((data: any) => this.http.get('../rest/autocomplete/banAddresses', { params: { 'address': data, 'department': this.addressBANCurrentDepartment } })),
                tap((data: any) => {
                    if (data.length === 0) {
                        this.addressBANInfo = this.translate.instant('lang.noAvailableValue');
                    } else {
                        this.addressBANInfo = '';
                    }
                    this.addressBANResult = data;
                    this.addressBANFilteredResult = of(this.addressBANResult);
                    this.addressBANLoading = false;
                })
            ).subscribe();
    }

    resetAutocompleteAddressBan() {
        this.addressBANResult = [];
        this.addressBANInfo = this.translate.instant('lang.autocompleteInfo');
    }

    selectAddressBan(ev: any) {
        const contact = {
            addressNumber: ev.option.value.number,
            addressStreet: ev.option.value.afnorName,
            addressPostcode: ev.option.value.postalCode,
            addressTown: ev.option.value.city,
            addressCountry: 'FRANCE'
        };
        this.setAddress(contact, false);
        this.addressBANControl.setValue('');
    }

    getValue(identifier: string) {
        return this.contactForm.filter(contact => contact.id === identifier)[0].control.value;
    }

    emptyAddress() {
        if (this.contactForm.filter(contact => this.isEmptyValue(contact.control.value) && ['addressNumber', 'addressStreet', 'addressPostcode', 'addressTown', 'addressCountry'].indexOf(contact.id) > -1).length === 5) {
            return true;
        } else {
            return false;
        }
    }

    noAddressRequired() {
        if (this.contactForm.filter(contact => !contact.required && ['addressNumber', 'addressStreet', 'addressPostcode', 'addressTown', 'addressCountry'].indexOf(contact.id) > -1).length === 5) {
            return true;
        } else {
            return false;
        }
    }

    goTo() {
        const contact = {
            addressNumber: this.contactForm.filter(field => field.id === 'addressNumber')[0].control.value,
            addressStreet: this.contactForm.filter(field => field.id === 'addressStreet')[0].control.value,
            addressPostcode: this.contactForm.filter(field => field.id === 'addressPostcode')[0].control.value,
            addressTown: this.contactForm.filter(field => field.id === 'addressTown')[0].control.value,
            addressCountry: this.contactForm.filter(field => field.id === 'addressCountry')[0].control.value
        };
        window.open(`https://www.google.com/maps/search/${contact.addressNumber}+${contact.addressStreet},+${contact.addressPostcode}+${contact.addressTown},+${contact.addressCountry}`, '_blank');
    }

    switchAddressMode() {
        const valArr: ValidatorFn[] = [];
        if (this.addressBANMode) {

            valArr.push(Validators.required);

            this.contactForm.filter(contact => ['addressNumber', 'addressStreet', 'addressPostcode', 'addressTown', 'addressCountry'].indexOf(contact.id) > -1).forEach((element: any) => {
                if (element.mandatory) {
                    element.control.setValidators(valArr);
                }
            });
            this.addressBANMode = !this.addressBANMode;
        } else {
            this.contactForm.filter(contact => ['addressNumber', 'addressStreet', 'addressPostcode', 'addressTown', 'addressCountry'].indexOf(contact.id) > -1).forEach((element: any) => {
                if (element.mandatory) {
                    element.control.setValidators(valArr);
                }
            });
            this.addressBANMode = !this.addressBANMode;
        }
    }

    getErrorMsg(error: any) {
        if (!this.isEmptyValue(error)) {
            if (error.required !== undefined) {
                return this.translate.instant('lang.requiredField');
            } else if (error.pattern !== undefined || error.email !== undefined) {
                return this.translate.instant('lang.badFormat');
            } else {
                return 'unknow validator';
            }
        }
    }

    checkFilling() {
        const countFilling = this.contactForm.filter(contact => contact.filling).length;
        const countValNotEmpty = this.contactForm.filter(contact => !this.isEmptyValue(contact.control.value) && contact.filling).length;

        this.fillingRate.value = Math.round((countValNotEmpty * 100) / countFilling);

        if (this.fillingRate.value <= this.fillingParameters.first_threshold) {
            this.fillingRate.color = this.contactService.getFillingColor('first');
            this.fillingRate.class = 'warn';
        } else if (this.fillingRate.value <= this.fillingParameters.second_threshold) {
            this.fillingRate.color = this.contactService.getFillingColor('second');
            this.fillingRate.class = 'primary';
        } else {
            this.fillingRate.color = this.contactService.getFillingColor('third');
            this.fillingRate.class = 'accent';
        }
    }

    toUpperCase(target: any, ev: any) {
        setTimeout(() => {
            const test = target.control.value;
            if (['lastname'].indexOf(target.id) > -1 && target.display) {
                target.control.setValue(test.toUpperCase());
            } else if (['firstname'].indexOf(target.id) > -1 && target.display) {
                let splitStr = test.toLowerCase().split(' ');
                for (let i = 0; i < splitStr.length; i++) {
                    splitStr[i] = splitStr[i].charAt(0).toUpperCase() + splitStr[i].substring(1);
                }
                splitStr = splitStr.join(' ');
                splitStr = splitStr.split('-');
                for (let i = 0; i < splitStr.length; i++) {
                    splitStr[i] = splitStr[i].charAt(0).toUpperCase() + splitStr[i].substring(1);
                }
                target.control.setValue(splitStr.join('-'));
            }
        }, 100);
    }


    checkContactName(field: any) {
        const contactName: any = {
            firstname: this.contactForm.find((item: any) => item.id === 'firstname').control.value,
            lastname: this.contactForm.find((item: any) => item.id === 'lastname').control.value
        };
        const alreadyExist: boolean = this.autocompleteContactName.find((contact: any) => contact.firstname === contactName.firstname && contact.lastname === contactName.lastname) !== undefined ? true : false;
        if (this.creationMode && ['firstname', 'lastname'].indexOf(field.id) > -1 && this.canSearchContact() && !alreadyExist) {
            if (JSON.stringify(contactName) !== JSON.stringify(this.contactNameClone)) {
                this.http.get(`../rest/autocomplete/contacts/name?firstname=${contactName.firstname}&lastname=${contactName.lastname}`).pipe(
                    tap((data: any) => {
                        this.autocompleteContactName = [];
                        this.autocompleteContactName = JSON.parse(JSON.stringify(data));
                        this.contactChanged = false;
                        this.contactNameClone = JSON.parse(JSON.stringify(
                            {
                                firstname: this.contactForm.find((item: any) => item.id === 'firstname').control.value,
                                lastname: this.contactForm.find((item: any) => item.id === 'lastname').control.value
                            }
                        ));
                    }),
                    catchError((err: any) => {
                        this.notify.handleSoftErrors(err);
                        return of(false);
                    })
                ).subscribe();
            }
        } else if (!this.canSearchContact()) {
            this.contactChanged = true;
        }
    }

    canSearchContact() {
        const firstname: any = this.contactForm.find((item: any) => item.id === 'firstname');
        const lastname: any = this.contactForm.find((item: any) => item.id === 'lastname');
        if (!this.functions.empty(firstname.control.value) && !this.functions.empty(lastname.control.value) && firstname.display && lastname.display) {
            return true;
        } else {
            this.contactChanged = true;
            return false;
        }
    }

    setContact(id: number) {
        if (!this.fromAdministration) {
            this.linkContact.emit(id);
        } else {
            const dialogRef = this.dialog.open(ConfirmComponent,
                { panelClass: 'maarch-modal',
                    autoFocus: false, disableClose: true,
                    data: {
                        title: this.translate.instant('lang.setContactInfos'),
                        msg: this.translate.instant('lang.goToContact')
                    }
                });
            dialogRef.afterClosed().pipe(
                filter((data: string) => data === 'ok'),
                exhaustMap(() => this.router.navigate([`/administration/contacts/list/${id}`])),
                catchError((err: any) => {
                    this.notify.handleErrors(err);
                    return of(false);
                })
            ).subscribe();
        }
    }

    showAllContact() {
        const dialogRef = this.dialog.open(ContactSearchModalComponentComponent, {
            disableClose: true,
            width: '800px',
            panelClass: 'maarch-modal',
            data: {
                contacts: this.autocompleteContactName,
                fromAdministration: this.fromAdministration
            }
        });

        dialogRef.afterClosed().pipe(
            tap((id: number) => {
                if (!this.functions.empty(id)) {
                    this.setContact(id);
                }
            })
        ).subscribe();
    }

    private _filter(value: string): string[] {
        const filterValue = value.toLowerCase();
        return this.countries.filter((option: any) => option.toLowerCase().includes(filterValue));
    }
}
