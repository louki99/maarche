import { Component, OnInit, Input } from '@angular/core';
import { UntypedFormControl, FormGroup, Validators, FormBuilder } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { TranslateService } from '@ngx-translate/core';
import { NotificationService } from '@service/notification/notification.service';
import { tap, catchError, debounceTime, filter, distinctUntilChanged, switchMap, startWith, map } from 'rxjs/operators';
import { Observable, of } from 'rxjs';
import { LatinisePipe } from 'ngx-pipes';
import { FunctionsService } from '@service/functions.service';

@Component({
    selector: 'app-registered-mail-recipient-input',
    templateUrl: './recipient-input.component.html',
    styleUrls: ['./recipient-input.component.scss']
})
export class RegisteredMailRecipientInputComponent implements OnInit {

    /**
     * FormControl used when autocomplete is used in form and must be catched in a form control.
     */
    @Input() control: UntypedFormControl;

    @Input() registeredMailType: string;

    manualAddress: boolean = false;
    civilities: any[] = [];
    addressBANInfo: string = '';
    addressBANMode: boolean = true;
    addressBANControl = new UntypedFormControl();
    addressBANLoading: boolean = false;
    addressBANResult: any[] = [];
    addressBANFilteredResult: Observable<string[]>;
    addressBANCurrentDepartment: string = '75';
    departmentList: any[] = [];

    countries: any = [];
    countriesFilteredResult: Observable<string[]>;
    countryControl = new UntypedFormControl();

    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        private notify: NotificationService,
        private latinisePipe: LatinisePipe,
        public functions: FunctionsService,
    ) { }

    ngOnInit(): void {
        this.getCivilities();
        this.getCountries();
        this.initAutocompleteCountries();
        this.initBanSearch();
        this.initAutocompleteAddressBan();
        if (this.control.value === null) {
            this.control.setValue({});
            this.control.setErrors({ 'required': true });
        }
    }

    getCivilities() {
        this.http.get('../rest/civilities').pipe(
            tap((data: any) => {
                this.civilities = data.civilities;
            }),
            catchError((err: any) => {
                this.notify.handleSoftErrors(err);
                return of(false);
            })
        ).subscribe();
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

    initAutocompleteCountries() {
        this.countriesFilteredResult = this.countryControl.valueChanges
            .pipe(
                startWith(''),
                map(value => this._filter(value))
            );
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
                this.notify.handleSoftErrors(err);
                return of(false);
            })
        ).subscribe();
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
        this.control.value.addressNumber = ev.option.value.number;
        this.control.value.addressStreet = ev.option.value.afnorName;
        this.control.value.addressPostcode = ev.option.value.postalCode;
        this.control.value.addressTown = ev.option.value.city;
        this.control.value.addressCountry = 'FRANCE';
        this.countryControl.setValue('FRANCE');
        this.addressBANControl.setValue('');
        this.checkRequiredFields();
        this.control.markAsTouched();
    }

    getFormatedAdress() {
        const formatedAddress = {};
        Object.keys(this.control.value).forEach(key => {
            formatedAddress[key] = this.control.value[key];
        });
        return formatedAddress;
    }

    emptyAddress() {
        let state: boolean = true;
        Object.keys(this.control.value).forEach(key => {
            if (!this.functions.empty(this.control.value[key])) {
                state = false;
            }
        });
        return state;
    }

    toUpperCase(target: string, ev: any) {
        setTimeout(() => {
            const test = this.latinisePipe.transform(this.control.value[target].toUpperCase());
            this.control.value[target] = test;
            this.checkRequiredFields();
        }, 100);
    }

    checkRequiredFields() {
        // CASE PRO ADDRESS
        if (!this.functions.empty(this.control.value.company)) {
            if (this.functions.empty(this.control.value.addressNumber) || this.functions.empty(this.control.value.addressStreet) || this.functions.empty(this.control.value.addressPostcode) || this.functions.empty(this.control.value.addressTown) || (this.registeredMailType === 'RW' && this.functions.empty(this.control.value.addressCountry))) {
                this.control.setErrors({ 'required': true });
            } else {
                this.control.setErrors(null);
            }
        // CASE PERSON ADDRESS
        } else if (this.functions.empty(this.control.value.company)) {
            if (this.functions.empty(this.control.value.firstname) || this.functions.empty(this.control.value.lastname) || this.functions.empty(this.control.value.addressNumber) || this.functions.empty(this.control.value.addressStreet) || this.functions.empty(this.control.value.addressPostcode) || this.functions.empty(this.control.value.addressTown) || (this.registeredMailType === 'RW' && this.functions.empty(this.control.value.addressCountry))) {
                this.control.setErrors({ 'required': true });
            } else {
                this.control.setErrors(null);
            }
        }
    }

    goTo() {
        window.open(`https://www.google.com/maps/search/${this.control.value.addressNumber}+${this.control.value.addressStreet},+${this.control.value.addressPostcode}+${this.control.value.addressTown},+${this.control.value.addressCountry}`, '_blank');
    }

    getContact(contact: any) {
        this.http.get('../rest/contacts/' + contact.id).pipe(
            tap((data: any) => {
                this.control.value.firstname = data.firstname;
                this.control.value.lastname = data.lastname;
                this.control.value.addressStreet = data.addressStreet;
                this.control.value.addressPostcode = data.addressPostcode;
                this.control.value.addressTown = data.addressTown;
                this.control.value.addressCountry = data.addressCountry;
                this.control.value.addressNumber = data.addressNumber;
                this.control.value.company = data.company;
                this.control.value.civility = data.civility.label.toUpperCase();
                this.control.value.addressAdditional1 = data.addressAdditional1;
                this.control.value.addressAdditional2 = data.addressAdditional2;
                this.countryControl.setValue(data.addressCountry);
                this.control.markAsTouched();
            }),
            catchError((err: any) => {
                this.notify.error(err.error.errors);
                return of(false);
            })
        ).subscribe();
    }

    private _filter(value: string): string[] {
        const filterValue = value.toLowerCase();
        return this.countries.filter((option: any) => option.toLowerCase().includes(filterValue));
    }
}
