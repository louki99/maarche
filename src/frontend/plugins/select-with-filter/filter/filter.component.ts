import {
    Component,
    OnInit,
    Input,
    EventEmitter,
    Output,
    ViewChild,
    OnDestroy
} from '@angular/core';
import { UntypedFormGroup, UntypedFormBuilder } from '@angular/forms';
import { A, Z, ZERO, NINE, SPACE, END, HOME } from '@angular/cdk/keycodes';
import { LatinisePipe } from 'ngx-pipes';
import { TranslateService } from '@ngx-translate/core';
import { FunctionsService } from '@service/functions.service';
@Component({
    selector: 'app-filter',
    templateUrl: './filter.component.html',
    styleUrls: ['./filter.component.scss']
})
export class FilterComponent implements OnInit, OnDestroy {
    @ViewChild('input', { static: true }) input;

    @Input() array: any;
    @Input() currentValues: any;
    @Input() placeholder: string = this.translate.instant('lang.filterBy');
    @Input() color: string;
    @Input() displayMember: string;
    @Input() noResultsMessage = this.translate.instant('lang.noResult');
    @Input() hasGroup: boolean; // EXPERIMENTAL
    @Input() groupArrayName: string; // EXPERIMENTAL

    @Output() filteredReturn = new EventEmitter<any>();

    selectedItemsMode: boolean = false;
    noResults = false;

    localSpinner = false;

    public filteredItems: any = [];
    public searchForm: UntypedFormGroup;

    constructor(
        public translate: TranslateService,
        public functionsService: FunctionsService,
        private latinisePipe: LatinisePipe,
        fb: UntypedFormBuilder
    ) {
        this.searchForm = fb.group({
            value: ''
        });
    }

    ngOnInit() {
        this.array = JSON.parse(JSON.stringify(this.array));
        let groupId = '';
        let index = 1;
        this.array.forEach((element: any) => {
            if (element.isTitle) {
                groupId = `group_${index}`;
                element.groupId = groupId;
                index++;
            } else {
                element.group = groupId;
            }
        });
        this.searchForm.valueChanges.subscribe(value => {
            this.selectedItemsMode = false;
            if (value['value']) {
                // IF THE DISPLAY MEMBER INPUT IS SET WE CHECK THE SPECIFIC PROPERTY
                if (this.displayMember == null) {
                    this.filteredItems = this.array.filter(name =>
                        this.latinisePipe.transform(name.toLowerCase()).includes(this.latinisePipe.transform(value['value'].toLowerCase()))
                    );
                    // OTHERWISE, WE CHECK THE ENTIRE STRING
                } else if (this.hasGroup && this.groupArrayName && this.displayMember) {
                    this.filteredItems = this.array
                        .map(a => {
                            const objCopy = Object.assign({}, a);
                            objCopy[this.groupArrayName] = objCopy[
                                this.groupArrayName
                            ].filter(g =>
                                g[this.displayMember]
                                    .toLowerCase()
                                    .includes(value['value'].toLowerCase())
                            );
                            return objCopy;
                        })
                        .filter(x => x[this.groupArrayName].length > 0);
                } else {
                    const group = this.array
                        .filter(
                            (option: any) =>
                                option['isTitle'] &&
                                this.latinisePipe.transform(option[this.displayMember]
                                    .toLowerCase())
                                    .includes(this.latinisePipe.transform(value['value'].toLowerCase()))
                        )
                        .map((opt: any) => opt.groupId);
                    this.filteredItems = this.array.filter(
                        (option: any) =>
                            (option['isTitle'] && group.indexOf(option['groupId']) > -1) ||
                            (group.indexOf(option['group']) > -1 ||
                            this.latinisePipe.transform(option[this.displayMember]
                                .toLowerCase())
                                .includes(this.latinisePipe.transform(value['value'].toLowerCase())))
                    );
                }
                // NO RESULTS VALIDATION

                this.noResults =
                    this.filteredItems == null || this.filteredItems.length === 0;
            } else {
                this.filteredItems = this.array.slice();
                this.noResults = false;
            }
            this.filteredReturn.emit(this.filteredItems);
        });
    }

    handleKeydown(event: KeyboardEvent) {
        // PREVENT PROPAGATION FOR ALL ALPHANUMERIC CHARACTERS IN ORDER TO AVOID SELECTION ISSUES
        if (
            (event.key && event.key.length === 1) ||
            (event.keyCode >= A && event.keyCode <= Z) ||
            (event.keyCode >= ZERO && event.keyCode <= NINE) ||
            event.keyCode === SPACE
        ) {
            event.stopPropagation();
        }
    }
    ngOnDestroy() {
        this.filteredReturn.emit(this.array);
    }

    resetList() {
        this.searchForm.reset();
    }

    focusInput() {
        const box = document.querySelector('.appSelectFilterContainer');
        const width = box.clientWidth;
        (document.querySelector('.mat-filter') as HTMLElement).style.width = width + 'px';
        this.input.nativeElement.focus();
    }

    showSelectedItems() {
        if (!this.selectedItemsMode) {
            if (this.displayMember == null) {
                this.filteredItems = this.array.filter(name =>
                    name.toLowerCase().includes(this.currentValues.toLowerCase())
                );
                // OTHERWISE, WE CHECK THE ENTIRE STRING
            } else {
                this.filteredItems = this.array.filter(name =>
                    this.currentValues.includes(name['id'])
                );
                this.filteredReturn.emit(this.filteredItems);
            }
        } else {
            this.filteredReturn.emit(this.array);
        }
        this.selectedItemsMode = !this.selectedItemsMode;
    }

    resetSelectedItems() {
        this.selectedItemsMode = false;
        this.filteredReturn.emit(this.array);
    }

    isEmptyCurrentValues() {
        return Array.isArray(this.currentValues) && this.currentValues.length > 0 && this.functionsService.empty(this.input.nativeElement.value);
    }
}
