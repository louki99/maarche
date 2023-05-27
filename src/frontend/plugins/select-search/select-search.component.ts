import {
    AfterViewInit,
    Component, ElementRef, EventEmitter, Input, OnDestroy, OnInit, QueryList,
    ViewChild,
    Renderer2,
    Output,
    DoCheck
} from '@angular/core';
import { ControlValueAccessor, UntypedFormControl } from '@angular/forms';
import { MatOption } from '@angular/material/core';
import { MatSelect } from '@angular/material/select';
import { take, takeUntil, startWith, map, tap } from 'rxjs/operators';
import { Subject, ReplaySubject, Observable } from 'rxjs';
import { LatinisePipe } from 'ngx-pipes';
import { TranslateService } from '@ngx-translate/core';
import { AppService } from '@service/app.service';
import { SortPipe } from '../../plugins/sorting.pipe';
import { FunctionsService } from '@service/functions.service';

@Component({
    selector: 'app-plugin-select-search',
    templateUrl: 'select-search.component.html',
    styleUrls: ['select-search.component.scss', '../../app/indexation/indexing-form/indexing-form.component.scss'],
    providers: [SortPipe]
})
export class PluginSelectSearchComponent implements OnInit, OnDestroy, AfterViewInit, ControlValueAccessor, DoCheck {
    /** Label of the search placeholder */
    @Input() placeholderLabel = this.translate.instant('lang.chooseValue');

    @Input() formControlSelect: UntypedFormControl = new UntypedFormControl();

    @Input() datas: any = [];
    @Input() dataKey: string = null;

    @Input() returnValue: 'id' | 'object' = 'id';

    @Input() label: string;

    @Input() id: string = '';

    @Input() showResetOption: boolean;

    @Input() showLabel: boolean = false;

    @Input() required: boolean = false;

    @Input() hideErrorDesc: boolean = true;

    @Input() multiple: boolean = false;

    @Input() optGroupTarget: string = null;

    @Input() disabled: boolean = false;

    /**
     * ex : [ { id : 'group1' , label: 'Group 1'} ]
     */
    @Input() optGroupList: any = null;


    /**
     * ex : {class:'fa-circle', color:'#fffff', title: 'foo'}
     */
    @Input() suffixIcon: any = null;

    @Input() class: string = 'input-form';
    @Input() appearance: string = 'legacy';

    /**
     * Catch external event after select an element in autocomplete
     */
    @Output() afterSelected = new EventEmitter();
    @Output() afterOpened = new EventEmitter();

    /** Reference to the search input field */
    @ViewChild('searchSelectInput', { read: ElementRef, static: true }) searchSelectInput: ElementRef;

    @ViewChild('test', { static: true }) matSelect: MatSelect;

    public filteredDatas: Observable<string[]>;

    public filteredDatasMulti: ReplaySubject<any[]> = new ReplaySubject<any[]>(1);

    datasClone: any = [];
    isModelModified: boolean = false;

    formControlSearch = new UntypedFormControl();

    selected: any[] = [];

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
        public translate: TranslateService,
        private latinisePipe: LatinisePipe,
        private renderer: Renderer2,
        public appService: AppService,
        public functions: FunctionsService,
        private sortPipe: SortPipe
    ) { }

    onChange: Function = (_: any) => { };
    onTouched: Function = (_: any) => { };

    ngOnInit() {
        if (this.multiple) {
            this.matSelect.compareWith = (item1: any, item2: any) => item1 && item2 ? item1.id === item2.id : item1 === item2;
        } else if (this.returnValue === 'object' && this.dataKey !== null) {
            this.matSelect.compareWith = (item1: any, item2: any) => item1 && item2 ? item1[this.dataKey] === item2[this.dataKey] : item1 === item2;
        }
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

        let group = '';
        let index = 1;
        this.datasClone = JSON.parse(JSON.stringify(this.datas));
        this.datasClone.forEach((element: any) => {
            if (element.isTitle) {
                group = `group_${index}`;
                element.id = group;
                index++;
            } else {
                element.group = group;
            }
        });

        this.filteredDatas = this.formControlSearch.valueChanges
            .pipe(
                startWith(''),
                map(value => this._filter(value))
            );

        // this.initMultipleHandling();

    }

    // To resfresh filteredDatas if data is modfied
    ngDoCheck(): void {
        if (this.datasClone.length !== this.datas.length || this.datasClone.length === 0 && this.isModelModified) {
            this.datasClone = JSON.parse(JSON.stringify(this.datas));
            let group = '';
            let index = 1;
            this.datasClone.forEach((element: any) => {
                if (element.isTitle) {
                    group = `group_${index}`;
                    element.id = group;
                    index++;
                } else {
                    element.group = group;
                }
            });
            this.filteredDatas = this.formControlSearch.valueChanges
                .pipe(
                    startWith(''),
                    map(value => this._filter(value))
                );
            this.isModelModified = false;
        }
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
        if (this.datas.length > 5) {
            this.renderer.selectRootElement('#searchSelectInput').focus();
        }
        panel.scrollTop = scrollTop;
    }

    /**
     * Resets the current search value
     * @param {boolean} focus whether to focus after resetting
     * @private
     */
    public _reset(focus?: boolean) {

        this.formControlSearch.reset();

        this.renderer.selectRootElement('#searchSelectInput').focus();

    }

    launchEvent(ev: any) {
        if (this.selected.length > 0) {
            const ids = new Set(this.formControlSelect.value.map(d => d.id));
            const merged = [...this.formControlSelect.value, ...this.selected.filter(d => !ids.has(d.id))];
            this.formControlSelect.setValue(merged);
        }

        if (this.afterSelected !== undefined) {
            this.afterSelected.emit(ev.value);
        }
    }

    selectChange(ev: any) {
        if (this.multiple && ev.isUserInput) {
            if (ev.source._selected) {
                this.selected = this.formControlSelect.value;
            } else {
                this.selected = this.selected.length > 0 ? this.selected.filter((val: any) => val.id !== ev.source.value.id) : [];
            }
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

    getFirstDataLabel() {
        return this.formControlSelect.value[0].label.replace(/&nbsp;/g, '');
        // return this.returnValue === 'id' ? this.formControlSelect.value[0].label.replace(/\u00a0/g, '') : this.formControlSelect.value.map((item: any) => item !== null ? item.label : this.translate.instant('lang.emptyValue'))[0];
    }

    emptyData() {
        return this.returnValue === 'id' ? null : { id: null, label: this.translate.instant('lang.emptyValue') };
    }

    getLabel(value: any) {
        if (Array.isArray(value)) {
            const ids: any = value.map((item: any) => item.id);
            return this.datas.filter((item: any) => ids.indexOf(item.id) > -1).map((item: any) => item.label.replace(/&nbsp;/g, '')).filter(Boolean).join(', ');
        } else {
            return this.datas?.find((item: any) => item.id === value)?.label?.replaceAll(/&nbsp;/g, '');
        }
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
            return this.returnValue === 'id' ? this.datas.filter((option: any) => this.formControlSelect.value.indexOf(option['id']) > -1) : this.datas.filter((option: any) => this.formControlSelect.value.map((val: any) => val.id).indexOf(option['id']) > -1);
        } else if (typeof value === 'string' && value !== '') {
            const filterValue = this.latinisePipe.transform(value.toLowerCase());

            const group = this.datasClone.filter((option: any) => option['isTitle'] && this.latinisePipe.transform(option['label'].toLowerCase()).includes(filterValue)).map((opt: any) => opt.id);

            return this.datasClone.filter((option: any) => (option['isTitle'] && group.indexOf(option['id']) > -1) || (group.indexOf(option['group']) > -1 || this.latinisePipe.transform(option['label'].toLowerCase()).includes(filterValue)));
        } else {
            return this.datas;
        }
    }
}
