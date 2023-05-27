import { Component, OnInit, ViewChild, ElementRef, EventEmitter, Output, Input, QueryList, ViewChildren } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { TranslateService } from '@ngx-translate/core';
import { AppService } from '@service/app.service';
import { FunctionsService } from '@service/functions.service';
import { Observable, of } from 'rxjs';
import { UntypedFormControl } from '@angular/forms';
import { startWith, map, tap, filter, exhaustMap, catchError } from 'rxjs/operators';
import { LatinisePipe } from 'ngx-pipes';
import { MatExpansionPanel } from '@angular/material/expansion';
import { IndexingFieldsService } from '@service/indexing-fields.service';
import { ActivatedRoute } from '@angular/router';
import { MatDialog } from '@angular/material/dialog';
import { ConfirmComponent } from '../../../plugins/modal/confirm.component';
import { NotificationService } from '@service/notification/notification.service';
import { AddSearchTemplateModalComponent } from './search-template/search-template-modal.component';
import { DatePipe } from '@angular/common';
import { ContactAutocompleteComponent } from '@appRoot/contact/autocomplete/contact-autocomplete.component';
import { PluginSelectAutocompleteSearchComponent } from '@plugins/select-autocomplete-search/plugin-select-autocomplete-search.component';
import { FolderInputComponent } from '@appRoot/folder/indexing/folder-input.component';
import { TagInputComponent } from '@appRoot/tag/indexing/tag-input.component';
import { IssuingSiteInputComponent } from '@appRoot/administration/registered-mail/issuing-site/indexing/issuing-site-input.component';
import { SortPipe } from '@plugins/sorting.pipe';
import { CriteriaSearchService } from '@service/criteriaSearch.service';
import { HeaderService } from '@service/header.service';

@Component({
    selector: 'app-criteria-tool',
    templateUrl: 'criteria-tool.component.html',
    styleUrls: ['criteria-tool.component.scss', '../../indexation/indexing-form/indexing-form.component.scss'],
    providers: [DatePipe, SortPipe]
})
export class CriteriaToolComponent implements OnInit {

    @Input() searchTerm: string = '';
    @Input() defaultCriteria: any = [];
    @Input() adminMode: boolean = false;
    @Input() openedPanel: boolean = true;
    @Input() isLoadingResult: boolean = false;
    @Input() class: 'main' | 'secondary' = 'main';
    @Input() data: any = [];

    @Output() searchUrlGenerated = new EventEmitter<any>();
    @Output() loaded = new EventEmitter<any>();
    @Output() afterGetSearchTemplates = new EventEmitter<any>();
    @Output() refreshDaoResult = new EventEmitter<any>();

    @ViewChild('criteriaTool', { static: false }) criteriaTool: MatExpansionPanel;
    @ViewChild('searchCriteriaInput', { static: false }) searchCriteriaInput: ElementRef;
    @ViewChild('appFolderInput', { static: false }) appFolderInput: FolderInputComponent;
    @ViewChild('appTagInput', { static: false }) appTagInput: TagInputComponent;
    @ViewChild('appIssuingSiteInput', { static: false }) appIssuingSiteInput: IssuingSiteInputComponent;

    @ViewChildren('appContactAutocomplete') appContactAutocomplete: QueryList<ContactAutocompleteComponent>;
    @ViewChildren('pluginSelectAutocompleteSearch') pluginSelectAutocompleteSearch: QueryList<PluginSelectAutocompleteSearchComponent>;

    loading: boolean = true;
    criteria: any = [];
    searchTemplates: any;

    currentCriteria: any = [];

    filteredCriteria: Observable<string[]>;

    searchTermControl = new UntypedFormControl();
    searchCriteria = new UntypedFormControl();

    infoFields: any = [
        {
            id: 1,
            desc: 'lang.searchInAttachmentsInfo'
        },
        {
            id: 2,
            desc: 'lang.searchFulltextInfo'
        },
        {
            id: 3,
            desc: 'lang.manualSearchInfo'
        },
    ];

    displayColsOrder = [
        { 'id': 'destUser' },
        { 'id': 'categoryId' },
        { 'id': 'creationDate' },
        { 'id': 'processLimitDate' },
        { 'id': 'entityLabel' },
        { 'id': 'subject' },
        { 'id': 'chrono' },
        { 'id': 'priority' },
        { 'id': 'status' },
        { 'id': 'typeLabel' }
    ];

    listProperties: any = {};

    currentParam: any = null;

    constructor(
        private _activatedRoute: ActivatedRoute,
        public translate: TranslateService,
        public http: HttpClient,
        public appService: AppService,
        public functions: FunctionsService,
        public indexingFields: IndexingFieldsService,
        public criteriaSearchService: CriteriaSearchService,
        public headerService: HeaderService,
        private dialog: MatDialog,
        private notify: NotificationService,
        private datePipe: DatePipe,
        private latinisePipe: LatinisePipe,
        private sortPipe: SortPipe
    ) {
        _activatedRoute.queryParams.subscribe(
            params => {
                if (params.target === 'searchTerm') {
                    this.searchTerm = params.value;
                } else {
                    this.currentParam = params;
                    this.searchTerm = '';
                    this.searchTermControl.setValue(this.searchTerm);
                }
            }
        );
    }

    async ngOnInit(): Promise<void> {
        this.searchTermControl.setValue(this.searchTerm);
        this.listProperties = this.criteriaSearchService.initListsProperties(this.headerService.user.id);
        this.criteria = await this.indexingFields.getAllSearchFields();

        this.criteria.forEach((element: any) => {
            if (this.defaultCriteria.indexOf(element.identifier) > -1) {
                element.control = new UntypedFormControl('');
                this.addCriteria(element, false);
            }
        });

        if (['senders', 'recipients'].indexOf(this.currentParam?.target) > -1 && !this.functions.empty(this.currentParam.value)) {
            if (this.currentParam.target === 'senders') {
                this.criteriaSearchService.updateListsPropertiesCriteria({senders: {type: 'autocomplete', values: [this.currentParam.value]}});
            } else {
                this.criteriaSearchService.updateListsPropertiesCriteria({recipients: {type: 'autocomplete', values: [this.currentParam.value]}});
            }
        }

        this.loaded.emit(true);

        this.filteredCriteria = this.searchCriteria.valueChanges
            .pipe(
                startWith(''),
                map(value => this._filter(value))
            );
        this.loading = false;
        setTimeout(() => {
            this.searchTermControl.valueChanges
                .pipe(
                    startWith(''),
                    map(value => {
                        if (typeof value === 'string' && !this.functions.empty(value)) {
                            this.searchTerm = value;
                        }
                    })
                ).subscribe();
        }, 500);

        if (!this.adminMode) {
            this.getSearchTemplates();
        }
    }

    isCurrentCriteriaById(criteriaIds: string[]) {
        return this.currentCriteria.filter((currCrit: any) => criteriaIds.indexOf(currCrit.identifier) > -1).length > 0;
    }


    isCurrentCriteriaByType(criteriaTypes: string[]) {
        return this.currentCriteria.filter((currCrit: any) => criteriaTypes.indexOf(currCrit.type) > -1).length > 0;
    }

    async addCriteria(criteria: any, openPanel: boolean = true) {
        if (this.functions.empty(criteria.control) || this.functions.empty(criteria.control.value)) {
            criteria.control = criteria.type === 'date' || criteria.type === 'integer' ? new UntypedFormControl({}) : new UntypedFormControl('');
        }
        this.initField(criteria);
        this.currentCriteria.push(criteria);
        if (this.adminMode) {
            criteria.control.disable();
        }
        this.searchCriteria.reset();
        // this.searchCriteriaInput.nativeElement.blur();
        if (openPanel) {
            setTimeout(() => {
                this.criteriaTool.open();
            }, 0);
        }
    }

    initField(field: any) {
        try {
            const regex = /role_[.]*/g;
            if (field.identifier.match(regex) !== null) {
                this['set_role_field'](field);
            } else {
                this['set_' + field.identifier + '_field'](field);

            }
        } catch (error) {
        }
    }

    removeCriteria(index: number) {
        this.currentCriteria.splice(index, 1);
    }

    getFilterControl() {
        return this.searchCriteria;
    }

    getCriterias() {
        return this.criteria;
    }

    getFilteredCriterias() {
        return this.filteredCriteria;
    }

    focusFilter() {
        setTimeout(() => {
            this.searchCriteriaInput.nativeElement.focus();
        }, 100);
    }

    getCurrentCriteriaValues() {
        const objCriteria = {};
        if (!this.functions.empty(this.searchTermControl.value)) {
            objCriteria['meta'] = {
                values: this.searchTermControl.value
            };
        }
        this.currentCriteria.forEach((field: any) => {
            if (field.type === 'date' || field.type === 'integer') {
                if (!this.functions.empty(field.control.value.start) || !this.functions.empty(field.control.value.end)) {
                    objCriteria[field.identifier] = {
                        type: field.type,
                        values: {
                            start: !this.functions.empty(field.control.value.start) ? field.control.value.start : null,
                            end: !this.functions.empty(field.control.value.end) ? field.control.value.end : null
                        }
                    };
                }
            } else {
                if (!this.functions.empty(field.control.value)) {
                    objCriteria[field.identifier] = {
                        type: field.type,
                        values: field.control.value
                    };
                } else {
                    if (['recipients', 'senders'].indexOf(field.identifier) > -1 || field.type === 'contact') {
                        if (!this.functions.empty(this.appContactAutocomplete.toArray().filter((component: any) => component.id === field.identifier)[0].getInputValue())) {
                            objCriteria[field.identifier] = {
                                type: field.type,
                                values: [this.appContactAutocomplete.toArray().filter((component: any) => component.id === field.identifier)[0].getInputValue()]
                            };
                        }
                    }
                }
            }
        });

        if (!this.functions.empty(this.currentParam?.target) && !this.functions.empty(this.currentParam.value)) {
            const contactArray: any = this.appContactAutocomplete.toArray().find((item: any) => item.id === this.currentParam.target);
            if (!this.functions.empty(contactArray?.myControl) && !this.functions.empty(objCriteria[this.currentParam.target])) {
                if (!this.functions.empty(contactArray.myControl.value) && this.appContactAutocomplete.toArray().find((item: any) => item.id === this.currentParam?.target).controlAutocomplete.value.length === 0) {
                    objCriteria[this.currentParam.target]['values'] = contactArray.myControl.value;
                    if (this.currentParam.target === 'senders') {
                        this.criteriaSearchService.updateListsPropertiesCriteria({senders: {type: 'autocomplete', values: [contactArray.myControl.value]}});
                    } else {
                        this.criteriaSearchService.updateListsPropertiesCriteria({recipients: {type: 'autocomplete', values: [contactArray.myControl.value]}});
                    }
                }
            }
        }

        this.searchUrlGenerated.emit(objCriteria);
    }

    toggleTool(state: boolean) {
        if (state) {
            this.criteriaTool.open();
        } else {
            this.criteriaTool.close();
        }
    }

    getLabelValue(identifier: string, value: any) {
        if (this.functions.empty(value)) {
            return this.translate.instant('lang.undefined');
        } else if (typeof value === 'object') {
            if (Object.keys(value).indexOf('title') > -1) {
                return value.title;
            } else if (Object.keys(value).indexOf('label') > -1) {
                return value.label;
            }
        } else {
            return value;
        }
    }

    getFormatLabel(identifier: string, value: any) {

        if (this.criteria.filter((field: any) => field.identifier === identifier)[0].type === 'date') {
            return `${value.start !== null ? this.datePipe.transform(value.start, 'dd/MM/y') : '∞'} - ${value.end !== null ? this.datePipe.transform(value.end, 'dd/MM/y') : '∞'}`;
        } else if (this.criteria.filter((field: any) => field.identifier === identifier)[0].type === 'integer') {
            return `${value.start !== null ? value.start : '∞'} - ${value.end !== null ? value.end : '∞'}`;
        } else {
            if (identifier === 'registeredMail_issuingSite') {
                return this.appIssuingSiteInput.getSiteLabel(value);
            } else {
                return value;
            }
        }
    }

    getLabelValues(identifier: string, values: string[]) {

        if (values.length === 0) {
            return this.translate.instant('lang.undefined');
        } else if (typeof values[0] === 'object') {
            if (Object.keys(values[0]).indexOf('title') > -1) {
                return values.map((item: any) => item.title);
            } else if (Object.keys(values[0]).indexOf('label') > -1) {
                return values.map((item: any) => item.label);
            }
        } else {
            return values;
        }
    }

    resetAllCriteria() {
        this.currentCriteria.forEach((field: any, index: number) => {
            this.resetCriteria(field.identifier, null, false);
        });

        this.resetCriteria('meta', null, false);

        this.getCurrentCriteriaValues();
    }

    resetCriteria(criteriaId: string, value: any, refresh: boolean = true) {
        if (criteriaId !== 'meta') {
            const criteria = this.currentCriteria.filter((item: any) => item.identifier === criteriaId)[0];

            if (criteria !== undefined) {
                if (criteria.type === 'date' && this.functions.empty(criteria.values)) {
                    criteria.control.setValue({
                        start: null,
                        end: null
                    });
                } else {
                    if (value !== null) {
                        const index = criteria.control.value.map((item: any) => JSON.stringify(item)).indexOf(JSON.stringify(value));
                        const tmpVal = criteria.control.value.slice();
                        tmpVal.splice(index, 1);

                        if (index > -1) {
                            criteria.control.setValue(tmpVal);
                        }
                    } else {
                        criteria.control.setValue([]);
                    }
                }

                if ((['recipients', 'senders'].indexOf(criteria.identifier) > -1 || criteria.type === 'contact') && this.functions.empty(criteria.control.value)) {
                    this.appContactAutocomplete.toArray().filter((component: any) => component.id === criteria.identifier)[0]?.resetInputValue();
                }
            }
        } else {
            this.searchTermControl.setValue('');
        }
        if (refresh) {
            this.getCurrentCriteriaValues();
        }
    }

    searchInAttachments(identifier: string) {
        return ['subject', 'chrono', 'fulltext'].indexOf(identifier) > -1;
    }

    displayInfoSearch(infoSearchNumber: number) {
        if (infoSearchNumber === 1 && (this.isCurrentCriteriaById(['subject', 'chrono', 'fulltext']))) {
            return true;
        } else if (infoSearchNumber === 2 && this.isCurrentCriteriaById(['fulltext'])) {
            return true;
        } else if (infoSearchNumber === 3 && (this.isCurrentCriteriaById(['recipients', 'senders', 'registeredMail_recipient']) || this.isCurrentCriteriaByType(['contact']))) {
            return true;
        }
        return false;
    }

    getBadgesInfoField(field: any) {
        const badges = [];

        if (['subject', 'chrono', 'fulltext'].indexOf(field.identifier) > -1) {
            badges.push(1);
        }
        if (['fulltext'].indexOf(field.identifier) > -1) {
            badges.push(2);
        }
        if (['recipients', 'senders', 'registeredMail_recipient'].indexOf(field.identifier) > -1 || field.type === 'contact') {
            badges.push(3);
        }
        return badges;
    }

    set_meta_field(value: any) {
        this.searchTermControl.setValue(value);
    }

    set_doctype_field(elem: any) {
        return new Promise((resolve, reject) => {
            this.http.get('../rest/doctypes').pipe(
                tap((data: any) => {
                    let arrValues: any[] = [];
                    data.structure.forEach((doctype: any) => {
                        if (doctype['doctypes_second_level_id'] === undefined) {
                            arrValues.push({
                                id: doctype.doctypes_first_level_id,
                                label: doctype.doctypes_first_level_label,
                                title: doctype.doctypes_first_level_label,
                                disabled: true,
                                isTitle: true,
                                color: doctype.css_style
                            });
                            data.structure.filter((info: any) => info.doctypes_first_level_id === doctype.doctypes_first_level_id && info.doctypes_second_level_id !== undefined && info.description === undefined).forEach((secondDoctype: any) => {
                                arrValues.push({
                                    id: secondDoctype.doctypes_second_level_id,
                                    label: '&nbsp;&nbsp;&nbsp;&nbsp;' + secondDoctype.doctypes_second_level_label,
                                    title: secondDoctype.doctypes_second_level_label,
                                    disabled: true,
                                    isTitle: true,
                                    color: secondDoctype.css_style
                                });
                                arrValues = arrValues.concat(data.structure.filter((infoDoctype: any) => infoDoctype.doctypes_second_level_id === secondDoctype.doctypes_second_level_id && infoDoctype.description !== undefined).map((infoType: any) => ({
                                    id: infoType.type_id,
                                    label: '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;' + infoType.description,
                                    title: infoType.description,
                                    disabled: false,
                                    isTitle: false,
                                })));
                            });
                        }
                    });
                    elem.values = arrValues;
                    elem.event = 'calcLimitDate';
                    resolve(true);
                }),
                catchError((err: any) => {
                    this.notify.handleSoftErrors(err);
                    return of(false);
                })
            ).subscribe();
        });
    }

    set_priority_field(elem: any) {
        return new Promise((resolve, reject) => {
            this.http.get('../rest/priorities').pipe(
                tap((data: any) => {
                    elem.values = data.priorities;
                    resolve(true);
                }),
                catchError((err: any) => {
                    this.notify.handleSoftErrors(err);
                    return of(false);
                })
            ).subscribe();
        });
    }

    set_status_field(elem: any) {
        return new Promise((resolve, reject) => {
            this.http.get('../rest/statuses').pipe(
                tap((data: any) => {
                    elem.values = data.statuses.map((val: any) => ({
                        id: val.identifier,
                        label: val.label_status
                    }));
                    resolve(true);
                }),
                catchError((err: any) => {
                    this.notify.handleSoftErrors(err);
                    return of(false);
                })
            ).subscribe();
        });
    }

    set_category_field(elem: any) {
        return new Promise((resolve, reject) => {
            this.http.get('../rest/categories').pipe(
                tap((data: any) => {
                    elem.values = data.categories.map((val: any) => ({
                        id: val.id,
                        label: val.label
                    }));
                    resolve(true);
                }),
                catchError((err: any) => {
                    this.notify.handleSoftErrors(err);
                    return of(false);
                })
            ).subscribe();
        });
    }

    set_attachment_type_field(elem: any) {
        elem.values = [];
        return new Promise((resolve, reject) => {
            this.http.get('../rest/attachmentsTypes').pipe(
                tap((data: any) => {
                    Object.keys(data.attachmentsTypes).forEach(templateType => {
                        elem.values.push({
                            id: templateType,
                            label: data.attachmentsTypes[templateType].label
                        });
                    });
                    elem.values = this.sortPipe.transform(elem.values, 'label');
                    resolve(true);
                }),
                catchError((err: any) => {
                    this.notify.handleSoftErrors(err);
                    return of(false);
                })
            ).subscribe();
        });
    }

    set_groupSign_field(elem: any) {
        return new Promise((resolve, reject) => {
            this.http.get('../rest/groups').pipe(
                tap((data: any) => {
                    elem.values = data.groups.map((group: any) => ({
                        id: group.id,
                        label: group.group_desc
                    }));
                    elem.values = this.sortPipe.transform(elem.values, 'label');
                    resolve(true);
                }),
                catchError((err: any) => {
                    this.notify.handleSoftErrors(err);
                    return of(false);
                })
            ).subscribe();
        });
    }

    set_destination_field(elem: any) {
        elem.values = [];
        return new Promise((resolve, reject) => {
            this.http.get('../rest/indexingModels/entities').pipe(
                tap((data: any) => {
                    let title = '';
                    elem.values = elem.values.concat(data.entities.map((entity: any) => {
                        title = entity.entity_label;

                        for (let index = 0; index < entity.level; index++) {
                            entity.entity_label = '&nbsp;&nbsp;&nbsp;&nbsp;' + entity.entity_label;
                        }
                        return {
                            id: entity.id,
                            title: title,
                            label: entity.entity_label,
                            disabled: false
                        };
                    }));
                    resolve(true);
                }),
                catchError((err: any) => {
                    this.notify.handleSoftErrors(err);
                    return of(false);
                })
            ).subscribe();
        });
    }

    set_initiator_field(elem: any) {
        elem.values = [];
        return new Promise((resolve, reject) => {
            this.http.get('../rest/indexingModels/entities').pipe(
                tap((data: any) => {
                    let title = '';
                    elem.values = elem.values.concat(data.entities.map((entity: any) => {
                        title = entity.entity_label;

                        for (let index = 0; index < entity.level; index++) {
                            entity.entity_label = '&nbsp;&nbsp;&nbsp;&nbsp;' + entity.entity_label;
                        }
                        return {
                            id: entity.id,
                            title: title,
                            label: entity.entity_label,
                            disabled: false
                        };
                    }));
                    resolve(true);
                }),
                catchError((err: any) => {
                    this.notify.handleSoftErrors(err);
                    return of(false);
                })
            ).subscribe();
        });
    }

    set_registeredMail_issuingSite_field(elem: any) {
        return new Promise((resolve, reject) => {
            this.http.get('../rest/registeredMail/sites').pipe(
                tap((data: any) => {
                    elem.values = data['sites'].map((item: any) => ({
                        id: item.id,
                        label: `${item.label} (${item.accountNumber})`
                    }));
                    resolve(true);
                }),
                catchError((err: any) => {
                    this.notify.handleSoftErrors(err);
                    return of(false);
                })
            ).subscribe();
        });
    }

    set_role_field(elem: any) {
        elem.type = 'selectAutocomplete';
        elem.routeDatas = ['role_dest', 'role_visa', 'role_sign'].indexOf(elem.identifier) > -1 ? ['/rest/autocomplete/users?serial=serialId'] : ['/rest/autocomplete/users?serial=serialId', '/rest/autocomplete/entities?serial=serialId'];
        elem.extraModel = ['type'];
        elem.returnValue = 'object';
    }

    set_senderDepartment_field(elem: any) {
        return new Promise((resolve, reject) => {
            this.http.get('../rest/departments').pipe(
                tap((data: any) => {
                    elem.values = [];
                    Object.keys(data.departments).forEach(key => {
                        elem.values.push({
                            id: key,
                            label: `${key} - ${data.departments[key]}`
                        });
                    });
                    elem.values = this.sortPipe.transform(elem.values, 'label');
                    resolve(true);
                }),
                catchError((err: any) => {
                    this.notify.handleSoftErrors(err);
                    return of(false);
                })
            ).subscribe();
        });
    }


    getSearchTemplates() {
        this.http.get('../rest/searchTemplates').pipe(
            tap((data: any) => {
                this.searchTemplates = data.searchTemplates;
                this.afterGetSearchTemplates.emit(this.searchTemplates);
                // this.selectSearchTemplate(this.searchTemplates[0], false);
            })
        ).subscribe();
    }

    saveSearchTemplate() {
        const query: any = [];
        this.currentCriteria.forEach((field: any, index: number) => {
            query.push({ 'identifier': field.identifier, 'values': field.control.value });
        });

        query.push({ 'identifier': 'meta', 'values': this.searchTermControl.value });

        const dialogRef = this.dialog.open(
            AddSearchTemplateModalComponent,
            {
                panelClass: 'maarch-modal',
                autoFocus: true,
                disableClose: true,
                data: {
                    searchTemplate: { query: query }
                }
            }
        );

        dialogRef.afterClosed().pipe(
            filter((data: any) => data !== undefined),
            tap((data) => {
                this.searchTemplates.push(data.searchTemplate);
            }),
            catchError((err: any) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    deleteSearchTemplate(id: number, listFilter: any) {
        const dialogRef = this.dialog.open(
            ConfirmComponent,
            {
                panelClass: 'maarch-modal',
                autoFocus: false,
                disableClose: true,
                data: {
                    title: this.translate.instant('lang.delete'),
                    msg: this.translate.instant('lang.confirmAction')
                }
            }
        );

        dialogRef.afterClosed().pipe(
            filter((data: string) => data === 'ok'),
            exhaustMap(() => this.http.delete(`../rest/searchTemplates/${id}`)),
            tap(() => {
                const element = this.searchTemplates.find((temp: any) => temp.id === id);
                this.searchTemplates.splice(this.searchTemplates.indexOf(element), 1);
                this.notify.success(this.translate.instant('lang.searchTemplateDeleted'));
                listFilter.value = '';
            }),
            catchError((err: any) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    selectSearchTemplate(searchTemplate: any, openPanel: boolean = true) {
        this.currentCriteria = [];
        let index: number;
        this.criteria.forEach((element: any) => {
            index = searchTemplate.query.map((field: any) => field.identifier).indexOf(element.identifier);
            if (index > -1) {
                if (element.control === undefined) {
                    element.control = new UntypedFormControl({ value: searchTemplate.query[index].values, disabled: false });
                }
                element.control.setValue(searchTemplate.query[index].values);

                this.addCriteria(element, openPanel);

                if ((['recipients', 'senders'].indexOf(element.identifier) > -1 || element.type === 'contact') && typeof searchTemplate.query[index].values[0] === 'string') {
                    const val = searchTemplate.query[index].values[0];
                    setTimeout(() => {
                        this.appContactAutocomplete.toArray().filter((component: any) => component.id === element.identifier)[0].setInputValue(val);
                    }, 0);
                }

                if (element.type === 'selectAutocomplete') {
                    setTimeout(() => {
                        this.pluginSelectAutocompleteSearch.toArray().filter((component: any) => component.id === element.identifier)[0].setDatas(element.control.value);
                        this.pluginSelectAutocompleteSearch.toArray().filter((component: any) => component.id === element.identifier)[0].resetACDatas();
                    }, 0);
                }
            }
        });

        index = searchTemplate.query.map((field: any) => field.identifier).indexOf('meta');
        if (index > -1) {
            this.searchTermControl.setValue(searchTemplate.query[index].values);
        }
    }

    updateFilters() {
        this.listProperties.page = 0;
        this.criteriaSearchService.updateListsProperties(this.listProperties);
        this.refreshDaoResult.emit(this.listProperties);
    }

    changeOrderDir() {
        if (this.listProperties.orderDir === 'ASC') {
            this.listProperties.orderDir = 'DESC';
        } else {
            this.listProperties.orderDir = 'ASC';
        }
        this.updateFilters();
    }

    private _filter(value: string): string[] {
        if (typeof value === 'string') {
            const filterValue = this.latinisePipe.transform(value.toLowerCase());
            return this.criteria.filter((option: any) => this.latinisePipe.transform(option['label'].toLowerCase()).includes(filterValue));
        } else {
            return this.criteria;
        }
    }
}
