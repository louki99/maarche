import { Component, OnInit, Input, ViewChild, ElementRef } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { TranslateService } from '@ngx-translate/core';
import { NotificationService } from '@service/notification/notification.service';
import { MatDialog, MatDialogRef } from '@angular/material/dialog';
import { AppService } from '@service/app.service';
import { SortPipe } from '../../../plugins/sorting.pipe';
import { UntypedFormControl } from '@angular/forms';
import { Observable, of } from 'rxjs';
import { debounceTime, filter, distinctUntilChanged, tap, switchMap, exhaustMap, catchError, map } from 'rxjs/operators';
import { LatinisePipe } from 'ngx-pipes';
import { PrivilegeService } from '@service/privileges.service';
import { FunctionsService } from '@service/functions.service';
import { ThesaurusModalComponent } from './thesaurus/thesaurus-modal.component';

@Component({
    selector: 'app-tag-input',
    templateUrl: 'tag-input.component.html',
    styleUrls: [
        'tag-input.component.scss',
        '../../indexation/indexing-form/indexing-form.component.scss'
    ],
    providers: [SortPipe]
})

export class TagInputComponent implements OnInit {

    /**
     * FormControl used when autocomplete is used in form and must be catched in a form control.
     */
    @Input() control: UntypedFormControl;

    @Input() returnValue: 'id' | 'object' = 'id';

    @ViewChild('autoCompleteInput', { static: true }) autoCompleteInput: ElementRef;

    loading: boolean = false;

    key: string = 'idToDisplay';

    canAdd: boolean = false;

    listInfo: string;
    myControl = new UntypedFormControl();
    filteredOptions: Observable<string[]>;
    options: any;
    valuesToDisplay: any = {};
    dialogRef: MatDialogRef<any>;
    newIds: number[] = [];

    tags: any[] = [];

    tmpObject: any = null;


    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        private notify: NotificationService,
        public dialog: MatDialog,
        public appService: AppService,
        private latinisePipe: LatinisePipe,
        private privilegeService: PrivilegeService,
        private functionsService: FunctionsService
    ) {

    }

    ngOnInit() {
        this.control.valueChanges
            .pipe(
                tap((data: any) => {
                    if (this.returnValue === 'object') {
                        this.valuesToDisplay = {};
                        data.forEach((item: any) => {
                            this.valuesToDisplay[item.id] = item.label;
                        });
                    } else {
                        if (!this.functionsService.empty(this.tmpObject)) {
                            this.valuesToDisplay[this.tmpObject['id']] = this.tmpObject[this.key];
                            this.tmpObject = null;
                        } else {
                            this.initFormValue();
                        }

                    }
                })
            ).subscribe();
        this.control.setValue(this.control.value === null || this.control.value === '' ? [] : this.control.value);
        this.canAdd = this.privilegeService.hasCurrentUserPrivilege('manage_tags_application');
        this.initAutocompleteRoute();
    }

    initAutocompleteRoute() {
        this.listInfo = this.translate.instant('lang.autocompleteInfo');
        this.options = [];
        this.myControl.valueChanges
            .pipe(
                debounceTime(300),
                filter(value => value.length > 2),
                distinctUntilChanged(),
                tap(() => this.loading = true),
                switchMap((data: any) => this.getDatas(data)),
                tap((data: any) => {
                    if (data.length === 0) {
                        this.listInfo = this.translate.instant('lang.noAvailableValue');
                    } else {
                        this.listInfo = '';
                    }
                    this.options = data;
                    this.filteredOptions = of(this.options);
                    this.loading = false;
                })
            ).subscribe();
    }

    getDatas(data: string) {
        return this.http.get('../rest/autocomplete/tags', { params: { 'search': data } });
    }

    selectOpt(ev: any) {
        this.setFormValue(ev.option.value);
        this.myControl.setValue('');

    }

    initFormValue() {
        this.control.value.forEach((ids: any) => {
            this.http.get('../rest/tags/' + ids).pipe(
                tap((data: any) => {
                    this.valuesToDisplay[data.id] = data.label;
                })
            ).subscribe();
        });
    }

    setFormValue(item: any) {
        const isSelected = this.returnValue === 'id' ? this.control.value.indexOf(item['id']) > -1 : this.control.value.map((val: any) => val.id).indexOf(item['id']) > -1;
        if (!isSelected) {
            let arrvalue = [];
            if (this.control.value !== null) {
                arrvalue = this.control.value;
            }
            if (this.returnValue === 'id') {
                arrvalue.push(item['id']);
            } else {
                arrvalue.push({
                    id: item['id'],
                    label: item['idToDisplay']
                });
            }
            this.control.setValue(arrvalue);
        }
    }

    resetAutocomplete() {
        this.options = [];
        this.listInfo = this.translate.instant('lang.autocompleteInfo');
    }

    unsetValue() {
        this.control.setValue('');
        this.myControl.setValue('');
        this.myControl.enable();
    }

    removeItem(index: number) {

        if (this.newIds.indexOf(this.control.value[index]) === -1) {
            const arrValue = this.control.value;
            this.control.value.splice(index, 1);
            this.control.setValue(arrValue);
        } else {
            this.http.delete('../rest/tags/' + this.control.value[index]).pipe(
                tap((data: any) => {
                    const arrValue = this.control.value;
                    this.control.value.splice(index, 1);
                    this.control.setValue(arrValue);
                }),
                catchError((err: any) => {
                    this.notify.handleErrors(err);
                    return of(false);
                })
            ).subscribe();
        }
    }

    addItem() {
        const newElem = {};

        newElem[this.key] = this.myControl.value;

        this.http.post('../rest/tags', { label: newElem[this.key] }).pipe(
            tap((data: any) => {
                Object.keys(data).forEach(key => {
                    newElem['id'] = data[key];
                    this.newIds.push(data[key]);
                });
                this.setFormValue(newElem);
                this.myControl.setValue('');
            }),
            catchError((err: any) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    openThesaurus(tag: any = null) {
        if (tag !== null) {
            tag = this.returnValue === 'id' ? tag : tag.id;
        }

        const dialogRef = this.dialog.open(ThesaurusModalComponent, {
            panelClass: 'maarch-modal',
            width: '600px',
            data: {
                id: tag
            }
        });
        dialogRef.afterClosed().pipe(
            filter((data: any) => !this.functionsService.empty(data)),
            map((data: any) => ({
                id: data.id,
                idToDisplay: data.label
            })),
            tap((tagItem: any) => {
                this.tmpObject = tagItem;
                this.setFormValue(tagItem);
            }),
            catchError((err: any) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    getTagLabel(data: any) {
        return this.returnValue === 'id' ? this.valuesToDisplay[data] : this.valuesToDisplay[data.id];
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
