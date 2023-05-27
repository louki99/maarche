import {
    Component,
    EventEmitter,
    Input,
    OnInit,
    Optional,
    Output,
    Self,
    ViewChild
} from '@angular/core';
import { AbstractControl, ControlValueAccessor, UntypedFormControl, NgControl } from '@angular/forms';
import { MatSelect } from '@angular/material/select';
import { TranslateService } from '@ngx-translate/core';
import { FilterComponent } from './filter/filter.component';

export const hasRequiredField = (abstractControl: AbstractControl): boolean => {
    if (abstractControl.validator) {
        const validator = abstractControl.validator({} as AbstractControl);
        if (validator && validator.required) {
            return true;
        }
    }
    return false;
};

@Component({
    selector: 'app-select-with-filter',
    templateUrl: './select-with-filter.component.html',
    styleUrls: ['./select-with-filter.component.scss']
})
export class SelectWithFilterComponent implements OnInit, ControlValueAccessor {
    @ViewChild('select', { static: true }) matSelect: MatSelect;
    @ViewChild('appFilter', { static: false }) appFilter: FilterComponent;

    @Input()
    public appearance: any = 'legacy';

    @Input()
    public array: any[];

    @Input()
    public showResetOption = false;

    @Input()
    public label: string = null;

    @Input()
    public placeholder: string = this.translate.instant('lang.chooseValue');

    @Input()
    public required = false;

    @Input()
    public disabled = false;

    @Input()
    public data: any;

    @Input()
    public multiple = false;

    @Input()
    public showMsgErrors: boolean = false;

    @Input()
    public formCtrl: UntypedFormControl;

    @Output()
    returnSelectedObjects = new EventEmitter<any>();

    @Output()
    selectionChange = new EventEmitter<any>();

    filteredList: any[];

    private errorMessages = new Map<string, () => string>();

    constructor(
        public translate: TranslateService,
        @Self() @Optional() public control: NgControl
    ) {
        // eslint-disable-next-line @typescript-eslint/no-unused-expressions
        this.control && (this.control.valueAccessor = this);
        this.errorMessages.set('required', () => `${this.label} <b>${this.translate.instant('lang.isRequired')}</b>.`);
    }

    public onChangeFn = (_: any) => { };

    public onTouchedFn = () => { };

    ngOnInit() {
        if (this.formCtrl) {
            (this.control as unknown as UntypedFormControl) = this.formCtrl;
        }
        this.filteredList = this.array.slice();
        setTimeout(() => {
            this.onChange();
        }, 0);
        setTimeout(() => {
            this.appFilter?.resetList();
        }, 200);
    }

    public get invalid(): boolean {
        return this.control ? this.control.invalid : false;
    }

    public get showError(): boolean {
        if (!this.control) {
            return false;
        }

        const { dirty, touched } = this.control;

        return this.invalid ? dirty || touched : false;
    }

    public get errors(): Array<string> {
        if (!this.control) {
            return [];
        }

        const { errors } = this.control;
        return Object.keys(errors).map(key =>
            this.errorMessages.has(key)
                ? this.errorMessages.get(key)()
                : <string>errors[key] || key
        );
    }

    public registerOnChange(fn: any): void {
        this.onChangeFn = fn;
    }

    public registerOnTouched(fn: any): void {
        this.onTouchedFn = fn;
    }

    public setDisabledState(isDisabled: boolean): void {
        this.disabled = isDisabled;
    }

    public writeValue(obj: any): void {
        this.data = obj;
    }

    public onChange(ev: any = null) {
        if (ev !== null) {
            if (Array.isArray(ev)) {
                this.returnSelectedObjects.emit(
                    this.array
                        .map((item: any) => {
                            delete item.group;
                            return item;
                        })
                        .filter((item: any) => ev.includes(item.id))
                );
                if (this.data.length === 0) {
                    this.appFilter.resetSelectedItems();
                }
            } else {
                this.returnSelectedObjects.emit(
                    this.array
                        .map((item: any) => {
                            delete item.group;
                            return item;
                        })
                        .find((item: any) => ev === item.id)
                );
            }
            if (this.formCtrl) {
                (this.control as unknown as UntypedFormControl).markAsTouched();
                (this.control as unknown as UntypedFormControl).setValue(this.data);
            }
            this.selectionChange.emit(this.data);
        }
        this.onChangeFn(this.data);
    }

    inFilteredItems(item: any) {
        return this.filteredList.map(arrItem => arrItem.id).indexOf(item.id) > -1;
    }

    emptyData() {
        return { id: null, label: this.translate.instant('lang.emptyValue') };
    }

    getSelectedItemsLabel() {
        if (Array.isArray(this.data)) {
            return this.array
                .filter((item: any) => this.data.includes(item.id))
                .map((item: any) => item.label.replace(/&nbsp;/g, ''));
        } else {
            return this.array
                .filter((item: any) => this.data === item.id)
                .map((item: any) => item.label.replace(/&nbsp;/g, ''));
        }
    }

    togglePanel(state: boolean) {
        if (!state) {
            this.appFilter.resetList();
        } else {
            this.appFilter.focusInput();
        }
    }

    isRequired() {
        return this.formCtrl ? hasRequiredField(this.control as unknown as UntypedFormControl) : this.required;
    }

    isDisabled() {
        return this.formCtrl ? this.control.disabled : this.disabled;
    }
}
