import { Component, OnInit, ViewChild, EventEmitter, ElementRef, TemplateRef, ViewContainerRef } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { TranslateService } from '@ngx-translate/core';
import { NotificationService } from '@service/notification/notification.service';
import { HeaderService } from '@service/header.service';
import { AppService } from '@service/app.service';
import { Observable, merge, Subject, of as observableOf, of } from 'rxjs';
import { MatDialog } from '@angular/material/dialog';
import { MatPaginator, PageEvent } from '@angular/material/paginator';
import { MatSort } from '@angular/material/sort';
import { takeUntil, startWith, switchMap, map, catchError, tap, finalize } from 'rxjs/operators';
import { UntypedFormControl } from '@angular/forms';
import { FunctionsService } from '@service/functions.service';
import { LatinisePipe } from 'ngx-pipes';
import { PrivilegeService } from '@service/privileges.service';
import { HistoryExportComponent } from '../export/history-export.component';

@Component({
    templateUrl: 'history-batch-administration.component.html',
    styleUrls: ['history-batch-administration.component.scss']
})
export class HistoryBatchAdministrationComponent implements OnInit {

    @ViewChild('adminMenuTemplate', { static: true }) adminMenuTemplate: TemplateRef<any>;
    @ViewChild(MatPaginator, { static: true }) paginator: MatPaginator;
    @ViewChild('tableHistoryListSort', { static: true }) sort: MatSort;
    @ViewChild('autoCompleteInput', { static: true }) autoCompleteInput: ElementRef;

    historyBatch: any[] = [
        {
            value: 'event_date',
            label: this.translate.instant('lang.event')
        },
        {
            value: 'total_processed',
            label: this.translate.instant('lang.totalProcessed')
        },
        {
            value: 'total_errors',
            label: this.translate.instant('lang.totalErrors')
        },
        {
            value: 'info',
            label: this.translate.instant('lang.information')
        },
        {
            value: 'module_name',
            label: this.translate.instant('lang.module')
        }
    ];

    filtersChange = new EventEmitter();

    data: any;

    displayedColumnsHistory: string[] = ['event_date', 'total_processed', 'total_errors', 'info', 'module_name'];

    isLoadingResults = true;
    routeUrl: string = '../rest/batchHistory';
    resultListDatabase: HistoryListHttpDao | null;
    resultsLength = 0;

    searchHistory = new UntypedFormControl();
    startDateFilter: any = '';
    endDateFilter: any = '';
    filterUrl: string = '';
    filterList: any = null;
    filteredList: any = {};
    filterUsed: any = {};

    filterColor = {
        startDate: '#b5cfd8',
        endDate: '#7393a7',
        totalErrors: '#fc6471',
        modules: '#009dc5',
    };

    loadingFilters: boolean = true;
    loading: boolean = false;

    subMenus: any[] = [];

    pageSize: number = 10;

    private destroy$ = new Subject<boolean>();

    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        private notify: NotificationService,
        private headerService: HeaderService,
        public appService: AppService,
        public dialog: MatDialog,
        public functions: FunctionsService,
        private latinisePipe: LatinisePipe,
        private privilegeService: PrivilegeService,
        private viewContainerRef: ViewContainerRef) { }

    ngOnInit(): void {
        this.headerService.injectInSideBarLeft(this.adminMenuTemplate, this.viewContainerRef, 'adminMenu');

        if (this.privilegeService.hasCurrentUserPrivilege('view_history')) {
            this.subMenus = [
                {
                    icon: 'fa fa-history',
                    route: '/administration/history',
                    label: this.translate.instant('lang.history'),
                    current: false
                },
                {
                    icon: 'fa fa-history',
                    route: '/administration/history-batch',
                    label: this.translate.instant('lang.historyBatch'),
                    current: true
                }
            ];
        } else {
            this.subMenus = [
                {
                    icon: 'fa fa-history',
                    route: '/administration/history-batch',
                    label: this.translate.instant('lang.historyBatch'),
                    current: true
                }
            ];
        }
        this.loading = true;
        this.initHistoryList();
    }

    initHistoryList() {
        this.resultListDatabase = new HistoryListHttpDao(this.http);
        this.paginator.pageIndex = 0;
        this.sort.active = 'event_date';
        this.sort.direction = 'desc';
        this.sort.sortChange.subscribe(() => this.paginator.pageIndex = 0);

        // When list is refresh (sort, page, filters)
        merge(this.sort.sortChange, this.paginator.page, this.filtersChange)
            .pipe(
                takeUntil(this.destroy$),
                startWith({}),
                switchMap(() => {
                    this.isLoadingResults = true;
                    let searchValue = '';
                    if (!this.functions.empty(this.searchHistory.value)) {
                        searchValue = '&search=' + this.searchHistory.value;
                    }
                    return this.resultListDatabase!.getRepoIssues(
                        this.sort.active, this.sort.direction, this.paginator.pageIndex, this.routeUrl, this.filterUrl, searchValue, this.pageSize);
                }),
                map(data => {
                    this.isLoadingResults = false;
                    data = this.processPostData(data);
                    this.resultsLength = data.count;
                    this.headerService.setHeader(this.translate.instant('lang.administration') + ' ' + this.translate.instant('lang.historyBatch').toLowerCase(), '', '');
                    return data.history;
                }),
                catchError((err: any) => {
                    this.notify.handleErrors(err);
                    this.isLoadingResults = false;
                    return observableOf([]);
                })
            ).subscribe(data => this.data = data);
    }

    processPostData(data: any) {
        data.history = data.history.map((item: any) => ({
            ...item,
            total_errors: item.total_errors === null ? 0 : item.total_errors
        }));
        return data;
    }


    refreshDao() {
        this.paginator.pageIndex = 0;
        this.filtersChange.emit();
    }

    initFilterListHistory() {

        if (this.filterList === null) {
            this.filterList = {};
            this.loadingFilters = true;

            this.http.get('../rest/batchHistory/availableFilters').pipe(
                map((data: any) => {
                    const returnData = { modules: [{}], totalErrors: [{}] };
                    returnData.modules = data.modules;

                    returnData.totalErrors = [
                        {
                            id: 'errorElement',
                            label: this.translate.instant('lang.totalErrors')
                        }
                    ];

                    return returnData;
                }),
                tap((data: any) => {
                    Object.keys(data).forEach((filterType: any) => {
                        if (this.functions.empty(this.filterList[filterType])) {
                            this.filterList[filterType] = [];
                            this.filteredList[filterType] = [];
                        }
                        data[filterType].forEach((element: any) => {
                            this.filterList[filterType].push(element);
                        });

                        this.filteredList[filterType] = this.searchHistory.valueChanges
                            .pipe(
                                startWith(''),
                                map(element => element ? this.filter(element, filterType) : this.filterList[filterType].slice())
                            );
                    });

                }),
                finalize(() => this.loadingFilters = false),
                catchError((err: any) => {
                    this.notify.handleSoftErrors(err);
                    return of(false);
                })
            ).subscribe();

        }
    }


    filterStartDate() {
        if (this.functions.empty(this.filterUsed['startDate'])) {
            this.filterUsed['startDate'] = [];
        }
        this.filterUsed['startDate'][0] = {
            id: this.functions.empty(this.startDateFilter) ? '' : this.functions.formatDateObjectToDateString(this.startDateFilter),
            label: this.functions.empty(this.startDateFilter) ? '' : this.functions.formatDateObjectToDateString(this.startDateFilter)
        };
        this.generateUrlFilter();
        this.refreshDao();
    }

    filterEndDate() {
        if (this.functions.empty(this.filterUsed['endDate'])) {
            this.filterUsed['endDate'] = [];
        }
        this.filterUsed['endDate'][0] = {
            id: this.functions.empty(this.endDateFilter) ? '' : this.functions.formatDateObjectToDateString(this.endDateFilter, true),
            label: this.functions.empty(this.endDateFilter) ? '' : this.functions.formatDateObjectToDateString(this.endDateFilter)
        };
        this.generateUrlFilter();
        this.refreshDao();
    }

    addItemFilter(elem: any) {
        elem.value.used = true;
        if (this.functions.empty(this.filterUsed[elem.id])) {
            this.filterUsed[elem.id] = [];
        }
        this.filterUsed[elem.id].push(elem.value);
        this.generateUrlFilter();
        this.searchHistory.reset();
        this.autoCompleteInput.nativeElement.blur();
        this.refreshDao();
    }

    removeItemFilter(elem: any, type: string, index: number) {
        elem.used = false;
        this.filterUsed[type].splice(index, 1);
        this.generateUrlFilter();
        this.refreshDao();
    }


    generateUrlFilter() {
        this.filterUrl = '';
        const arrTmpUrl: any[] = [];
        Object.keys(this.filterUsed).forEach((type: any) => {
            this.filterUsed[type].forEach((filter: any) => {
                if (!this.functions.empty(filter.id)) {
                    if (['startDate', 'endDate'].indexOf(type) > -1) {
                        arrTmpUrl.push(`${type}=${filter.id}`);
                    } else {
                        arrTmpUrl.push(`${type}[]=${filter.id}`);
                    }
                }
            });
        });
        if (arrTmpUrl.length > 0) {
            this.filterUrl = '&' + arrTmpUrl.join('&');
        }
    }

    directSearchHistory() {
        this.refreshDao();
    }

    handlePageEvent(event: PageEvent) {
        this.pageSize = event.pageSize;
    }

    openHistoryExport() {
        const parameters: any = {
            filterUsed : this.filterUsed,
            startDate: this.functions.empty(this.startDateFilter) ? '' : this.functions.formatDateObjectToDateString(this.startDateFilter),
            endDate: this.functions.empty(this.endDateFilter) ? '' : this.functions.formatDateObjectToDateString(this.endDateFilter)
        };

        delete parameters.filterUsed.startDate;
        delete parameters.filterUsed.endDate;

        this.dialog.open(HistoryExportComponent,
            {
                panelClass: 'maarch-modal',
                width: '800px',
                autoFocus: false,
                data: {
                    origin: 'batchHistory',
                    dataAvailable: this.historyBatch,
                    parameters: parameters
                }
            });
    }

    private filter(value: string, type: string): any[] {
        if (typeof value === 'string') {
            const filterValue = this.latinisePipe.transform(value.toLowerCase());
            return this.filterList[type].filter((elem: any) => this.latinisePipe.transform(elem.label.toLowerCase()).includes(filterValue));
        } else {
            return this.filterList[type];
        }
    }
}

export interface HistoryList {
    history: any[];
    count: number;
}
export class HistoryListHttpDao {

    constructor(private http: HttpClient) { }

    getRepoIssues(sort: string, order: string, page: number, href: string, search: string, directSearchValue: string, pageSize: number): Observable<HistoryList> {

        const offset = page * pageSize;
        const requestUrl = `${href}?limit=${pageSize}&offset=${offset}&order=${order}&orderBy=${sort}${search}${directSearchValue}`;

        return this.http.get<HistoryList>(requestUrl);
    }
}
