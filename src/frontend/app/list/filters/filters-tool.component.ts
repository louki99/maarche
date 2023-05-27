import { Component, OnInit, Input, EventEmitter, Output, ViewChild } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { TranslateService } from '@ngx-translate/core';
import { MatAutocompleteSelectedEvent, MatAutocompleteTrigger } from '@angular/material/autocomplete';
import { MatDialog } from '@angular/material/dialog';
import { FiltersListService } from '@service/filtersList.service';
import { UntypedFormBuilder, UntypedFormGroup } from '@angular/forms';
import { startWith, map } from 'rxjs/operators';
import { LatinisePipe } from 'ngx-pipes';
import { Observable } from 'rxjs';

declare let $: any;

export interface StateGroup {
    letter: string;
    names: any[];
}

@Component({
    selector: 'app-filters-tool',
    templateUrl: 'filters-tool.component.html',
    styleUrls: ['filters-tool.component.scss'],
    providers: [LatinisePipe],
})
export class FiltersToolComponent implements OnInit {

    @ViewChild(MatAutocompleteTrigger, { static: true }) autocomplete: MatAutocompleteTrigger;

    @Input() listProperties: any;
    @Input() title: string;
    @Input() routeDatas: string;
    @Input() selectedRes: any;
    @Input() totalRes: number;

    @Output() refreshEvent = new EventEmitter<string>();
    @Output() refreshEventAfterAction = new EventEmitter<string>();
    @Output() toggleAllRes = new EventEmitter<string>();

    stateForm: UntypedFormGroup = this.fb.group({
        stateGroup: '',
    });

    displayColsOrder = [
        { 'id': 'dest_user' },
        { 'id': 'category_id' },
        { 'id': 'creation_date' },
        { 'id': 'process_limit_date' },
        { 'id': 'entity_label' },
        { 'id': 'subject' },
        { 'id': 'alt_identifier' },
        { 'id': 'priority' },
        { 'id': 'status' },
        { 'id': 'type_label' }
    ];

    priorities: any[] = [];
    categories: any[] = [];
    entitiesList: any[] = [];
    statuses: any[] = [];
    metaSearchInput: string = '';

    stateGroups: StateGroup[] = [];
    stateGroupOptions: Observable<StateGroup[]>;

    isLoading: boolean = false;

    constructor(public translate: TranslateService, public http: HttpClient, private filtersListService: FiltersListService, private fb: UntypedFormBuilder, private latinisePipe: LatinisePipe, public dialog: MatDialog) { }

    ngOnInit(): void {

    }

    changeOrderDir() {
        if (this.listProperties.orderDir === 'ASC') {
            this.listProperties.orderDir = 'DESC';
        } else {
            this.listProperties.orderDir = 'ASC';
        }
        this.updateFilters();
    }

    updateFilters() {
        this.listProperties.page = 0;

        this.filtersListService.updateListsProperties(this.listProperties);

        this.refreshEvent.emit();
    }

    refreshAfterAction() {
        this.refreshEventAfterAction.emit();
    }

    setFilters(e: any, id: string) {
        this.listProperties[id] = e.source.checked;
        this.updateFilters();
    }


    selectFilter(e: MatAutocompleteSelectedEvent) {
        this.listProperties[e.option.value.id].push({
            'id': e.option.value.value,
            'label': e.option.value.label
        });
        $('.metaSearch').blur();
        this.stateForm.controls['stateGroup'].reset();
        this.updateFilters();
    }

    metaSearch(e: any) {
        this.listProperties.search = e.target.value;
        $('.metaSearch').blur();
        this.stateForm.controls['stateGroup'].reset();
        this.autocomplete.closePanel();
        this.updateFilters();
    }

    removeFilter(id: string, i: number) {
        this.listProperties[id].splice(i, 1);
        this.updateFilters();
    }

    removeFilters() {
        Object.keys(this.listProperties).forEach((key) => {
            if (Array.isArray(this.listProperties[key])) {
                this.listProperties[key] = [];
            } else if (key === 'search') {
                this.listProperties[key] = '';
            }
        });
        this.updateFilters();
    }

    haveFilters() {
        let state = false;
        Object.keys(this.listProperties).forEach((key) => {
            if ((Array.isArray(this.listProperties[key]) && this.listProperties[key].length > 0) || (key === 'search' && this.listProperties[key] !== '')) {
                state = true;
            }
        });
        return state;
    }

    setInputSearch(value: string) {
        $('.metaSearch').focus();
        this.metaSearchInput = value;
    }

    initFilters() {
        this.isLoading = true;

        this.stateForm.controls['stateGroup'].reset();
        this.stateGroups = [
            {
                letter: this.translate.instant('lang.categories'),
                names: []
            },
            {
                letter: this.translate.instant('lang.priorities'),
                names: []
            },
            {
                letter: this.translate.instant('lang.statuses'),
                names: []
            },
            {
                letter: this.translate.instant('lang.entities'),
                names: []
            },
            {
                letter: this.translate.instant('lang.subEntities'),
                names: []
            },
            {
                letter: this.translate.instant('lang.doctypes'),
                names: []
            },
            {
                letter: this.translate.instant('lang.folders'),
                names: []
            },
        ];

        this.http.get('..' + this.routeDatas + '?init' + this.filtersListService.getUrlFilters())
            .subscribe((data: any) => {
                data.categories.forEach((element: any) => {
                    if (this.listProperties.categories.map((category: any) => (category.id)).indexOf(element.id) === -1) {
                        this.stateGroups[0].names.push(
                            {
                                id: 'categories',
                                value: element.id,
                                label: (element.id !== null ? element.label : this.translate.instant('lang.undefined')),
                                count: element.count
                            }
                        );
                    }
                });
                data.priorities.forEach((element: any) => {
                    if (this.listProperties.priorities.map((priority: any) => (priority.id)).indexOf(element.id) === -1) {
                        this.stateGroups[1].names.push(
                            {
                                id: 'priorities',
                                value: element.id,
                                label: (element.id !== null ? element.label : this.translate.instant('lang.undefined')),
                                count: element.count
                            }
                        );
                    }
                });
                data.statuses.forEach((element: any) => {
                    if (this.listProperties.statuses.map((status: any) => (status.id)).indexOf(element.id) === -1) {
                        this.stateGroups[2].names.push(
                            {
                                id: 'statuses',
                                value: element.id,
                                label: (element.id !== null ? element.label : this.translate.instant('lang.undefined')),
                                count: element.count
                            }
                        );
                    }

                });

                data.entities.forEach((element: any) => {
                    if (this.listProperties.entities.map((entity: any) => (entity.id)).indexOf(element.entityId) === -1 && this.listProperties.subEntities.length === 0) {
                        this.stateGroups[3].names.push(
                            {
                                id: 'entities',
                                value: element.entityId,
                                label: (element.entityId !== null ? element.label : this.translate.instant('lang.undefined')),
                                count: element.count
                            }
                        );
                    }

                });

                data.entitiesChildren.forEach((element: any) => {
                    if (this.listProperties.subEntities.map((entity: any) => (entity.id)).indexOf(element.entityId) === -1 && this.listProperties.entities.length === 0) {
                        this.stateGroups[4].names.push(
                            {
                                id: 'subEntities',
                                value: element.entityId,
                                label: (element.entityId !== null ? element.label : this.translate.instant('lang.undefined')),
                                count: element.count
                            }
                        );
                    }
                });

                data.doctypes.forEach((element: any) => {
                    if (this.listProperties.doctypes.map((doctype: any) => (doctype.id)).indexOf(element.id) === -1) {
                        this.stateGroups[5].names.push(
                            {
                                id: 'doctypes',
                                value: element.id,
                                label: (element.id !== null ? element.label : this.translate.instant('lang.undefined')),
                                count: element.count
                            }
                        );
                    }
                });

                data.folders.forEach((element: any) => {
                    if (this.listProperties.folders.map((doctype: any) => (doctype.id)).indexOf(element.id) === -1) {
                        this.stateGroups[6].names.push(
                            {
                                id: 'folders',
                                value: element.id,
                                label: (element.id !== null ? element.label : this.translate.instant('lang.undefined')),
                                count: element.count
                            }
                        );
                    }
                });
                this.isLoading = false;
                if (this.metaSearchInput.length > 0) {
                    setTimeout(() => {
                        this.stateForm.controls['stateGroup'].setValue(this.metaSearchInput);
                        this.metaSearchInput = '';
                    }, 200);
                }
            });

        this.stateGroupOptions = this.stateForm.get('stateGroup')!.valueChanges
            .pipe(
                startWith(''),
                map((value: any) => this._filterGroup(value))
            );
    }

    private _filter = (opt: string[], value: string): string[] => {

        if (typeof value === 'string') {
            const filterValue = value.toLowerCase();

            return opt.filter(item => this.latinisePipe.transform(item['label'].toLowerCase()).indexOf(this.latinisePipe.transform(filterValue)) !== -1);
        }
    };

    private _filterGroup(value: string): StateGroup[] {
        if (value && typeof value === 'string') {
            return this.stateGroups
                .map(group => ({ letter: group.letter, names: this._filter(group.names, value) }))
                .filter(group => group.names.length > 0);
        }

        return this.stateGroups;
    }
}
