import {
    AfterViewInit, ChangeDetectorRef,
    Component, ElementRef, EventEmitter, Input, OnDestroy, OnInit, QueryList,
    ViewChild,
    Renderer2,
    Output
} from '@angular/core';
import { ControlValueAccessor, UntypedFormControl } from '@angular/forms';
import { MatOption } from '@angular/material/core';
import { MatSelect } from '@angular/material/select';
import { take, takeUntil, startWith, map, debounceTime, filter, tap, switchMap, finalize } from 'rxjs/operators';
import { Subject, ReplaySubject, Observable, forkJoin, of } from 'rxjs';
import { LatinisePipe } from 'ngx-pipes';
import { TranslateService } from '@ngx-translate/core';
import { AppService } from '@service/app.service';
import { SortPipe } from '../sorting.pipe';
import { FunctionsService } from '@service/functions.service';
import { HttpClient } from '@angular/common/http';

@Component({
    selector: 'app-plugin-select-autocomplete-search',
    templateUrl: 'plugin-select-autocomplete-search.component.html',
    styleUrls: ['plugin-select-autocomplete-search.component.scss', '../../app/indexation/indexing-form/indexing-form.component.scss'],
    providers: [SortPipe]
})
export class PluginSelectAutocompleteSearchComponent implements OnInit, OnDestroy, AfterViewInit, ControlValueAccessor {
    /** Label of the search placeholder */
    @Input() placeholderLabel = this.translate.instant('lang.chooseValue');

    @Input() formControlSelect: UntypedFormControl = new UntypedFormControl();

    @Input() datas: any = [];

    @Input() returnValue: 'id' | 'object' = 'id';

    @Input() label: string;

    @Input() id: string = '';

    @Input() showResetOption: boolean;

    @Input() showLabel: boolean = false;

    @Input() required: boolean = false;

    @Input() hideErrorDesc: boolean = true;

    @Input() multiple: boolean = false;

    @Input() optGroupTarget: string = null;

    /**
     * Route datas used in async autocomplete. Incompatible with @datas
     * ex : ['/rest/autocomplete/users']
     */
    @Input() routeDatas: string[];

    /**
     * Define extra structure to concatenate with id and label datas. Incompatible with @datas
     * ex : ['type']
     */
    @Input() extraModel: string[];

    /**
     * ex : [ { id : 'group1' , label: 'Group 1'} ]
     */
    @Input() optGroupList: any = null;


    /**
     * ex : {class:'fa-circle', color:'#fffff', title: 'foo'}
     */
    @Input() suffixIcon: any = null;

    @Input() class: string = 'input-form';

    /**
     * Catch external event after select an element in autocomplete
     */
    @Output() afterSelected = new EventEmitter();
    @Output() afterOpened = new EventEmitter();

    /** Reference to the search input field */
    @ViewChild('searchSelectInput', { read: ElementRef, static: true }) searchSelectInput: ElementRef;

    @ViewChild('test', { static: true }) matSelect: MatSelect;

    formControlSearch = new UntypedFormControl();
    noResult: boolean = null;
    loadingSearch: boolean = null;

    public filteredDatas: Observable<string[]>;

    public filteredDatasMulti: ReplaySubject<any[]> = new ReplaySubject<any[]>(1);

    /** Reference to the MatSelect options */
    public _options: QueryList<MatOption>;

    /** Previously selected values when using <mat-select [multiple]="true">*/
    private previousSelectedValues: any[];

    /** Whether the backdrop class has been set */
    private overlayClassSet = false;

    /** Event that emits when the current value changes */
    private change = new EventEmitter<string>();

    /** Subject that emits when the component has been destroyed. */
    private _onDestroy = new Subject<void>();

    /** Current search value */
    get value(): string {
        return this._value;
    }
    private _value: string;

    constructor(
        public http: HttpClient,
        public translate: TranslateService,
        private latinisePipe: LatinisePipe,
        private changeDetectorRef: ChangeDetectorRef,
        private renderer: Renderer2,
        public appService: AppService,
        public functions: FunctionsService,
        private sortPipe: SortPipe) { }


    onChange: Function = (_: any) => { };
    onTouched: Function = (_: any) => { };

    ngOnInit() {
        if (this.optGroupList !== null) {
            this.initOptGroups();
        }

        // set custom panel class
        const panelClass = 'mat-select-search-panel';
        if (this.matSelect.panelClass) {
            if (Array.isArray(this.matSelect.panelClass)) {
                this.matSelect.panelClass.push(panelClass);
            } else if (typeof this.matSelect.panelClass === 'string') {
                this.matSelect.panelClass = [this.matSelect.panelClass, panelClass];
            } else if (typeof this.matSelect.panelClass === 'object') {
                this.matSelect.panelClass[panelClass] = true;
            }
        } else {
            this.matSelect.panelClass = panelClass;
        }

        // when the select dropdown panel is opened or closed
        this.matSelect.openedChange
            .pipe(takeUntil(this._onDestroy))
            .subscribe((opened) => {
                if (opened) {
                    // focus the search field when opening
                    if (!this.appService.getViewMode()) {
                        this.noResult = null;
                        this._focus();
                    }
                } else {
                    // clear it when closing
                    // this._reset();
                    this.formControlSearch.reset();
                }
            });

        // set the first item active after the options changed
        this.matSelect.openedChange
            .pipe(take(1))
            .pipe(takeUntil(this._onDestroy))
            .subscribe(() => {
                this._options = this.matSelect.options;
                this._options.changes
                    .pipe(takeUntil(this._onDestroy))
                    .subscribe(() => {
                        const keyManager = this.matSelect._keyManager;
                        if (keyManager && this.matSelect.panelOpen) {
                            // avoid "expression has been changed" error
                            setTimeout(() => {
                                keyManager.setFirstItemActive();
                            });
                        }
                    });
            });

        this.formControlSearch.valueChanges
            .pipe(
                debounceTime(300),
                filter(value => value !== null && value.length > 2),
                tap(() => this.loadingSearch = true),
                // distinctUntilChanged(),
                // tap(() => this.loading = true),
                switchMap((data: any) => this.getDatas(data)),
                tap((data: any) => {
                    let selectedDatas = [];
                    let unselectedDatasSearch = [];
                    let selectedDatasId = [];

                    if (!this.functions.empty(this.formControlSelect.value)) {
                        selectedDatasId = this.returnValue === 'id' ? this.formControlSelect.value : this.formControlSelect.value.map((item: any) => item !== null ? item.id : null);
                    }

                    selectedDatas = this.datas.filter((val: any) => selectedDatasId.indexOf(val.id) > -1);
                    unselectedDatasSearch = data.filter((val: any) => selectedDatasId.indexOf(val.id) === -1);

                    this.datas = selectedDatas.concat(unselectedDatasSearch);
                    this.filteredDatas = of(this.datas);
                    this.noResult = this.datas.filter((val: any) => this.formControlSelect.value.indexOf(val.id) === -1).length === 0;
                    this.loadingSearch = false;
                    // this.loading = false;
                })
            ).subscribe();


        // this.initMultipleHandling();
    }

    resetACDatas() {
        if (this.returnValue === 'id') {
            this.datas = this.datas.filter((val: any) => this.formControlSelect.value.indexOf(val.id) > -1);

        } else {
            this.datas = this.datas.filter((val: any) => this.formControlSelect.value.map((item: any) => item !== null ? item.id : null).indexOf(val.id) > -1);
        }
        this.filteredDatas = of(this.datas);
    }

    initOptGroups() {
        this.datas.unshift({ id: 0, label: 'toto', disabled: true });

        let tmpArr = [];

        this.optGroupList = this.sortPipe.transform(this.optGroupList, 'label');
        this.optGroupList.forEach(group => {
            tmpArr.push({ id: group.id, label: group.label, disabled: true });
            tmpArr = tmpArr.concat(this.datas.filter(data => data[this.optGroupTarget] === group.id).map(data => ({
                ...data,
                title: data.label,
                label: '&nbsp;&nbsp;&nbsp' + data.label
            })));
        });

        this.datas = tmpArr;
    }

    ngOnDestroy() {
        this._onDestroy.next();
        this._onDestroy.complete();
    }

    ngAfterViewInit() {
        if (this.datas.length > 5) {
            this.setOverlayClass();
        }
    }

    /**
     * Handles the key down event with MatSelect.
     * Allows e.g. selecting with enter key, navigation with arrow keys, etc.
     * @param {KeyboardEvent} event
     * @private
     */
    _handleKeydown(event: KeyboardEvent) {
        if (event.keyCode === 32) {
            // do not propagate spaces to MatSelect, as this would select the currently active option
            event.stopPropagation();
        }

    }


    writeValue(value: string) {
        const valueChanged = value !== this._value;
        if (valueChanged) {
            this._value = value;
            this.change.emit(value);
        }
    }

    onInputChange(value: any) {
        const valueChanged = value !== this._value;
        if (valueChanged) {
            this._value = value;
            this.onChange(value);
            this.change.emit(value);
        }
    }

    onBlur(value: string) {
        this.writeValue(value);
        this.onTouched();
    }

    registerOnChange(fn: Function) {
        this.onChange = fn;
    }

    registerOnTouched(fn: Function) {
        this.onTouched = fn;
    }

    /**
     * Focuses the search input field
     * @private
     */
    public _focus() {
        // save and restore scrollTop of panel, since it will be reset by focus()
        // note: this is hacky
        const panel = this.matSelect.panel.nativeElement;
        const scrollTop = panel.scrollTop;

        // focus
        this.renderer.selectRootElement('#searchSelectInput').focus();

        panel.scrollTop = scrollTop;
    }

    /**
     * Resets the current search value
     * @param {boolean} focus whether to focus after resetting
     * @private
     */
    public _reset(focus?: boolean) {

        this.formControlSearch.reset();

        this.resetACDatas();

        this.renderer.selectRootElement('#searchSelectInput').focus();

    }

    launchEvent(ev: any) {
        if (this.afterSelected !== undefined) {
            this.afterSelected.emit(this.datas.filter((val: any) => val.id === ev.value)[0]);
        }
    }

    getErrorMsg(error: any) {
        if (error.required !== undefined) {
            return this.translate.instant('lang.requiredField');
        } else if (error.pattern !== undefined || error.email !== undefined) {
            return this.translate.instant('lang.badFormat');
        } else {
            return 'unknow validator';
        }
    }

    getDatas(data: string) {
        const arrayObs: any = [];
        const test: any = [];
        this.routeDatas.forEach(element => {
            arrayObs.push(this.http.get('..' + element, { params: { 'search': data } }));
        });

        return forkJoin(arrayObs).pipe(
            map((items: any[]) => {
                items.forEach((element: any) => {
                    element.forEach((element2: any) => {
                        let obj = {
                            id: element2.id,
                            label: element2.idToDisplay
                        };
                        if (this.extraModel.length > 0) {
                            const extraObj = this.getExtraDatas(element2);
                            obj = { ...obj, ...extraObj };
                        }
                        test.push(obj);
                    });
                });
                return test;
            })
        );
    }

    getExtraDatas(element: any) {
        const obj = {};
        Object.keys(element).forEach(key => {
            if (this.extraModel.indexOf(key) > -1) {
                obj[key] = element[key];
            }
        });
        return obj;
    }

    getFirstDataLabel() {
        return this.returnValue === 'id' ? this.formControlSelect.value[0].label : this.formControlSelect.value.map((item: any) => item !== null ? item.label : this.translate.instant('lang.emptyValue'))[0];
    }

    getDataLabel(data: any) {
        return this.returnValue === 'id' ? this.datas.filter((item: any) => item.id === data)[0].label : this.datas.filter((item: any) => item.id === data.id)[0].label;
    }

    setDatas(value: any) {
        this.datas = value;
    }

    /**
 * Sets the overlay class  to correct offsetY
 * so that the selected option is at the position of the select box when opening
 */
    private setOverlayClass() {
        if (this.overlayClassSet) {
            return;
        }
        const overlayClass = 'cdk-overlay-pane-select-search';

        this.matSelect.stateChanges
            .pipe(takeUntil(this._onDestroy))
            .subscribe(() => {
                // note: this is hacky, but currently there is no better way to do this
                if (this.searchSelectInput !== undefined) {
                    this.searchSelectInput.nativeElement.parentElement.parentElement
                        .parentElement.parentElement.parentElement.classList.add(overlayClass);
                }
            });

        this.overlayClassSet = true;
    }


    /**
     * Initializes handling <mat-select [multiple]="true">
     * Note: to improve this code, mat-select should be extended to allow disabling resetting the selection while filtering.
     */
    private initMultipleHandling() {
        // if <mat-select [multiple]="true">
        // store previously selected values and restore them when they are deselected
        // because the option is not available while we are currently filtering
        this.matSelect.valueChange
            .pipe(takeUntil(this._onDestroy))
            .subscribe((values) => {
                if (this.matSelect.multiple) {
                    let restoreSelectedValues = false;
                    if (this._value && this._value.length
                        && this.previousSelectedValues && Array.isArray(this.previousSelectedValues)) {
                        if (!values || !Array.isArray(values)) {
                            values = [];
                        }
                        const optionValues = this.matSelect.options.map(option => option.value);
                        this.previousSelectedValues.forEach(previousValue => {
                            if (values.indexOf(previousValue) === -1 && optionValues.indexOf(previousValue) === -1) {
                                // if a value that was selected before is deselected and not found in the options, it was deselected
                                // due to the filtering, so we restore it.
                                values.push(previousValue);
                                restoreSelectedValues = true;
                            }
                        });
                    }

                    if (restoreSelectedValues) {
                        this.matSelect._onChange(values);
                    }

                    this.previousSelectedValues = values;
                }
            });
    }

    private _filter(value: string, showSelectedValues: boolean = false): string[] {
        if (value === '__SELECTED') {
            return this.datas.filter((option: any) => this.formControlSelect.value.indexOf(option['id']) > -1);
        } else if (typeof value === 'string' && value !== '') {
            const filterValue = this.latinisePipe.transform(value.toLowerCase());
            return this.datas.filter((option: any) => !option['disabled'] && this.latinisePipe.transform(option['label'].toLowerCase()).includes(filterValue));
        } else {
            return this.datas;
        }
    }
}
