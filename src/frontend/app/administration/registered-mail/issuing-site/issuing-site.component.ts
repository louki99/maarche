import { Component, OnInit, ViewChild } from '@angular/core';
import { UntypedFormGroup, UntypedFormBuilder, Validators, UntypedFormControl } from '@angular/forms';
import { TranslateService } from '@ngx-translate/core';
import { HttpClient } from '@angular/common/http';
import { ActivatedRoute, Router } from '@angular/router';
import { NotificationService } from '@service/notification/notification.service';
import { HeaderService } from '@service/header.service';
import { AppService } from '@service/app.service';
import { MaarchFlatTreeComponent } from '../../../../plugins/tree/maarch-flat-tree.component';
import { map, tap, catchError, debounceTime, filter, distinctUntilChanged, switchMap, startWith } from 'rxjs/operators';
import { Observable, of } from 'rxjs';
import { LatinisePipe } from 'ngx-pipes';

@Component({
    selector: 'app-issuing-site',
    templateUrl: './issuing-site.component.html',
    styleUrls: ['./issuing-site.component.scss']
})
export class IssuingSiteComponent implements OnInit {

    @ViewChild('maarchTree', { static: true }) maarchTree: MaarchFlatTreeComponent;

    creationMode: boolean;
    loading: boolean = true;

    adminFormGroup: UntypedFormGroup;
    entities: any = [];

    countries: any = [];
    countriesFilteredResult: Observable<string[]>;

    id: number = null;

    addressBANInfo: string = '';
    addressBANMode: boolean = true;
    addressBANControl = new UntypedFormControl();
    addressBANLoading: boolean = false;
    addressBANResult: any[] = [];
    addressBANFilteredResult: Observable<string[]>;
    addressBANCurrentDepartment: string = '75';
    departmentList: any[] = [];

    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        private route: ActivatedRoute,
        private router: Router,
        private notify: NotificationService,
        private headerService: HeaderService,
        public appService: AppService,
        private _formBuilder: UntypedFormBuilder,
        private latinisePipe: LatinisePipe,
    ) { }

    ngOnInit(): void {
        this.route.params.subscribe(async (params) => {

            if (typeof params['id'] === 'undefined') {
                this.creationMode = true;
                this.headerService.setHeader(this.translate.instant('lang.issuingSiteCreation'));
                this.initBanSearch();
                this.initAutocompleteAddressBan();
                this.adminFormGroup = this._formBuilder.group({
                    id: [null],
                    label: ['', Validators.required],
                    postOfficeLabel: ['', Validators.required],
                    accountNumber: ['', Validators.required],
                    addressNumber: ['', Validators.required],
                    addressStreet: ['', Validators.required],
                    addressAdditional1: [''],
                    addressAdditional2: [''],
                    addressPostcode: ['', Validators.required],
                    addressTown: ['', Validators.required],
                    addressCountry: ['']
                });
                this.getCountries();
                this.initAutocompleteCountries();
                this.loading = false;

                await this.getEntities();
                this.maarchTree.initData(this.entities);
            } else {
                this.id = params['id'];
                this.creationMode = false;
                this.headerService.setHeader(this.translate.instant('lang.issuingSiteModification'));
                this.initBanSearch();
                this.initAutocompleteAddressBan();

                await this.getEntities();
                await this.getData();

                this.getCountries();
                this.initAutocompleteCountries();
                this.maarchTree.initData(this.entities);
            }
        });
    }

    getData() {
        return new Promise((resolve) => {
            this.http.get(`../rest/registeredMail/sites/${this.id}`).pipe(
                tap((data: any) => {
                    this.adminFormGroup = this._formBuilder.group({
                        id: [this.id],
                        label: [data.site.label, Validators.required],
                        postOfficeLabel: [data.site.postOfficeLabel, Validators.required],
                        accountNumber: [data.site.accountNumber, Validators.required],
                        addressNumber: [data.site.addressNumber, Validators.required],
                        addressStreet: [data.site.addressStreet, Validators.required],
                        addressAdditional1: [data.site.addressAdditional1],
                        addressAdditional2: [data.site.addressAdditional2],
                        addressPostcode: [data.site.addressPostcode, Validators.required],
                        addressTown: [data.site.addressTown, Validators.required],
                        addressCountry: [data.site.addressCountry],
                        entities: [data.site.entities]
                    });

                    this.entities = this.entities.map((entity: any) => ({
                        ...entity,
                        state: {
                            opened: true,
                            selected: data.site.entities.indexOf(entity.id) > -1
                        }
                    }));
                    resolve(true);
                    this.loading = false;
                }),
                catchError((err: any) => {
                    this.notify.handleSoftErrors(err);
                    return of(false);
                })
            ).subscribe();
        });
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

    initAutocompleteCountries() {
        this.countriesFilteredResult = this.adminFormGroup.controls['addressCountry'].valueChanges
            .pipe(
                startWith(''),
                map(value => this._filter(value))
            );
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
        this.adminFormGroup.controls['addressNumber'].setValue(ev.option.value.number);
        this.adminFormGroup.controls['addressStreet'].setValue(ev.option.value.afnorName);
        this.adminFormGroup.controls['addressPostcode'].setValue(ev.option.value.postalCode);
        this.adminFormGroup.controls['addressTown'].setValue(ev.option.value.city);
        this.adminFormGroup.controls['addressCountry'].setValue('FRANCE');
        this.addressBANControl.setValue('');
    }

    getEntities() {
        return new Promise((resolve) => {
            this.http.get('../rest/entities').pipe(
                map((data: any) => {
                    data.entities = data.entities.map((entity: any) => ({
                        text: entity.entity_label,
                        icon: entity.icon,
                        parent_id: entity.parentSerialId,
                        id: entity.serialId,
                        state: {
                            opened: true,
                        }
                    }));
                    return data.entities;
                }),
                tap((entities: any) => {
                    this.entities = entities;
                    resolve(true);
                }),
                catchError((err: any) => {
                    this.notify.handleSoftErrors(err);
                    return of(false);
                })
            ).subscribe();
        });
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

    onSubmit() {
        const objToSubmit = {};
        Object.keys(this.adminFormGroup.controls).forEach(key => {
            objToSubmit[key] = this.adminFormGroup.controls[key].value;
        });

        objToSubmit['entities'] = this.maarchTree.getSelectedNodes().map((ent: any) => ent.id);

        if (this.creationMode) {
            this.http.post('../rest/registeredMail/sites', objToSubmit)
                .subscribe(() => {
                    this.notify.success(this.translate.instant('lang.issuingSiteAdded'));
                    this.router.navigate(['/administration/issuingSites']);
                }, (err) => {
                    this.notify.handleSoftErrors(err);
                });
        } else {
            this.http.put('../rest/registeredMail/sites/' + this.id, objToSubmit)
                .subscribe(() => {
                    this.notify.success(this.translate.instant('lang.issuingSiteUpdated'));
                    this.router.navigate(['/administration/issuingSites']);
                }, (err) => {
                    this.notify.handleSoftErrors(err);
                });
        }
    }

    private _filter(value: string): string[] {
        const filterValue = value.toLowerCase();
        return this.countries.filter(option => option.toLowerCase().includes(filterValue));
    }
}
