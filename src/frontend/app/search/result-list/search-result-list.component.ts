import { Component, OnInit, ViewChild, EventEmitter, ViewContainerRef, OnDestroy, TemplateRef, Input, Output } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { TranslateService } from '@ngx-translate/core';
import { NotificationService } from '@service/notification/notification.service';
import { MatDialog, MatDialogRef } from '@angular/material/dialog';
import { MatPaginator } from '@angular/material/paginator';
import { MatSidenav } from '@angular/material/sidenav';
import { MatSort } from '@angular/material/sort';
import { DomSanitizer, SafeHtml } from '@angular/platform-browser';
import { startWith, switchMap, map, catchError, takeUntil, tap } from 'rxjs/operators';
import { ActivatedRoute, Router } from '@angular/router';
import { HeaderService } from '@service/header.service';
import { Overlay } from '@angular/cdk/overlay';
import { PanelListComponent } from '@appRoot/list/panel/panel-list.component';
import { AppService } from '@service/app.service';
import { BasketHomeComponent } from '@appRoot/basket/basket-home.component';
import { FolderActionListComponent } from '@appRoot/folder/folder-action-list/folder-action-list.component';
import { FoldersService } from '@appRoot/folder/folders.service';
import { FunctionsService } from '@service/functions.service';
import { of, merge, Subject, Subscription, Observable } from 'rxjs';
import { CriteriaToolComponent } from '@appRoot/search/criteria-tool/criteria-tool.component';
import { IndexingFieldsService } from '@service/indexing-fields.service';
import { CriteriaSearchService } from '@service/criteriaSearch.service';
import { HighlightPipe } from '@plugins/highlight.pipe';
import { FilterToolComponent } from '@appRoot/search/filter-tool/filter-tool.component';
import { ContactResourceModalComponent } from '@appRoot/contact/contact-resource/modal/contact-resource-modal.component';
import { PrivilegeService } from '@service/privileges.service';

declare let $: any;

@Component({
    selector: 'app-search-result-list',
    templateUrl: 'search-result-list.component.html',
    styleUrls: ['search-result-list.component.scss'],
    providers: [HighlightPipe]
})
export class SearchResultListComponent implements OnInit, OnDestroy {

    @Input() searchTerm: string = '';
    @Input() actionMode: boolean = true;
    @Input() singleSelection: boolean = false;
    @Input() standalone: boolean = false;
    @Input() hideFilter: boolean = false;
    @Input() appCriteriaTool: CriteriaToolComponent;
    @Input() sidenavRight: MatSidenav;
    @Input() linkedRes: any[] = [];
    @Input() from: string = '';

    @Output() loadingResult = new EventEmitter<boolean>();
    @Output() dataResult = new EventEmitter<any>();

    @ViewChild('filterTemplate', { static: true }) filterTemplate: TemplateRef<any>;
    @ViewChild('toolTemplate', { static: true }) toolTemplate: TemplateRef<any>;
    @ViewChild('panelTemplate', { static: true }) panelTemplate: TemplateRef<any>;
    @ViewChild('adminMenuTemplate', { static: true }) adminMenuTemplate: TemplateRef<any>;
    @ViewChild('actionsListContext', { static: false }) actionsList: FolderActionListComponent;
    @ViewChild('appPanelList', { static: false }) appPanelList: PanelListComponent;
    @ViewChild('appFilterToolAdvSearch', { static: false }) appFilterToolAdvSearch: FilterToolComponent;

    @ViewChild(MatPaginator, { static: true }) paginator: MatPaginator;
    @ViewChild('tableBasketListSort', { static: true }) sort: MatSort;
    @ViewChild('basketHome', { static: true }) basketHome: BasketHomeComponent;

    loading: boolean = true;
    initSearch: boolean = false;
    docUrl: string = '';
    public innerHtml: SafeHtml;
    searchUrl: string = '../rest/search';
    // searchTerm: string = '';
    criteria: any = {};
    homeData: any;

    injectDatasParam = {
        resId: 0,
        editable: false
    };
    currentResource: any = {};

    filtersChange = new EventEmitter();

    dragInit: boolean = true;

    dialogRef: MatDialogRef<any>;

    displayedColumnsBasket: string[] = ['resId'];

    displayedMainData: any = [
        {
            'value': 'chrono',
            'cssClasses': ['softColorData', 'align_centerData', 'chronoData'],
            'icon': ''
        },
        {
            'value': 'subject',
            'cssClasses': ['longData'],
            'icon': ''
        }
    ];

    resultListDatabase: ResultListHttpDao | null;
    data: any = [];
    resultsLength = 0;
    isLoadingResults = false;
    dataFilters: any = {};
    listProperties: any = {};
    currentChrono: string = '';
    currentMode: string = '';

    thumbnailUrl: string = '';

    selectedRes: Array<number> = [];
    allResInBasket: number[] = [];
    selectedDiffusionTab: number = 0;
    folderInfo: any = {
        id: 0,
        'label': '',
        'ownerDisplayName': '',
        'entitiesSharing': []
    };
    folderInfoOpened: boolean = false;

    subscription: Subscription;

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


    currentSelectedChrono: string = '';
    templateColumns: number = 7;

    paginatorLength: any;
    private destroy$ = new Subject<boolean>();


    constructor(
        private _activatedRoute: ActivatedRoute,
        public translate: TranslateService,
        public router: Router,
        private route: ActivatedRoute,
        public http: HttpClient,
        public dialog: MatDialog,
        private sanitizer: DomSanitizer,
        private headerService: HeaderService,
        public criteriaSearchService: CriteriaSearchService,
        private notify: NotificationService,
        public overlay: Overlay,
        public viewContainerRef: ViewContainerRef,
        public appService: AppService,
        public foldersService: FoldersService,
        public functions: FunctionsService,
        public indexingFieldService: IndexingFieldsService,
        public highlightPipe: HighlightPipe,
        public privilegeService: PrivilegeService,
    ) {
        _activatedRoute.queryParams.subscribe(
            params => {
                if (!this.functions.empty(params.value)) {
                    if (params.target === 'searchTerm') {
                        this.searchTerm = params.value;
                        this.initSearch = true;
                        this.criteria = {
                            meta: {
                                values: this.searchTerm
                            }
                        };
                    }
                }
            }
        );
    }

    ngOnInit(): void {
        if (this.functions.empty(this.linkedRes)) {
            if (!this.functions.empty(this.searchTerm)) {
                this.initSearch = true;
                this.criteria = {
                    meta: {
                        values: this.searchTerm
                    }
                };
            }
            this.headerService.sideBarAdmin = true;

            this.isLoadingResults = false;

            if (this.toolTemplate !== undefined) {
                this.headerService.initTemplate(this.toolTemplate, this.viewContainerRef, 'toolTemplate');
            }

            if (this.panelTemplate !== undefined && this.sidenavRight !== undefined) {
                this.headerService.initTemplate(this.panelTemplate, this.viewContainerRef, 'panelTemplate');
            }

            if (this.filterTemplate !== undefined && !this.hideFilter) {
                this.headerService.initTemplate(this.filterTemplate, this.viewContainerRef, 'filterTemplate');
            }

            this.listProperties = this.criteriaSearchService.initListsProperties(this.headerService.user.id);

            if (!this.functions.empty(this.searchTerm)) {
                this.listProperties.criteria = {};
                this.listProperties.criteria.meta = this.criteria.meta;
            }
        } else {
            this.resultsLength = this.linkedRes['resources'].length;
            this.allResInBasket = this.linkedRes['resources'].map((item: any) => item.resId);
            this.selectedRes = this.linkedRes['resources'].filter((item: any) => item.checked).map((el: any) => el.resId);
            this.paginatorLength = this.linkedRes['resources'].length > 10000 ? 10000 : this.linkedRes['resources'].length;
            this.dataFilters = this.linkedRes['filters'];
            this.templateColumns = this.linkedRes['templateColumns'];
            const processData: any[] = this.processPostData(this.linkedRes);
            this.data = processData['resources'];
            this.isLoadingResults = false;
            this.hideFilter = true;
        }


        this.loading = false;
        this.dataResult.emit([]);
    }


    initSavedCriteria() {
        if (Object.keys(this.listProperties.criteria).length > 0) {
            const obj = { query: [] };
            Object.keys(this.listProperties.criteria).forEach(key => {
                const objectItem = {};
                objectItem['identifier'] = key;
                objectItem['values'] = this.listProperties.criteria[key].values;
                obj.query.push(objectItem);
            });
            this.appCriteriaTool.selectSearchTemplate(obj, false);
            this.criteria = this.listProperties.criteria;
            if (!this.functions.empty(this.listProperties.filters)) {
                this.dataFilters = this.listProperties.filters;
            }
            this.initResultList();
        } else if (this.initSearch) {
            this.initResultList();
        }
    }

    ngOnDestroy() {
        this.destroy$.next(true);
    }

    launch(row: any) {
        const thisSelect = { checked: true };
        const thisDeselect = { checked: false };

        if (this.actionMode) {
            row.checked = true;
            this.toggleAllRes(thisDeselect);
            this.toggleRes(thisSelect, row);
            this.router.navigate([`/resources/${row.resId}`], {queryParams: {fromSearch: true}});
        } else {
            row.checked = !row.checked;
            this.toggleRes(row.checked ? thisSelect : thisDeselect, row);
        }
    }

    launchEventSubData(data: any, row: any) {
        if (data.event) {
            if (['getSenders', 'getRecipients'].indexOf(data.value) > -1 && data.displayValue !== this.translate.instant('lang.undefined')) {
                const mode = data.value === 'getSenders' ? 'senders' : 'recipients';
                this.openContact(row, mode);
            }
        }
    }

    openContact(row: any, mode: string) {
        this.dialog.open(ContactResourceModalComponent, { panelClass: 'maarch-modal', data: { title: `${row.chrono} - ${row.subject}`, mode: mode, resId: row.resId } });

    }

    launchSearch(criteria: any = this.criteria, initSearch = false) {
        this.listProperties.page = 0;
        this.listProperties.pageSize = 0;
        if (initSearch) {
            this.dataFilters = {};
        }
        this.criteria = JSON.parse(JSON.stringify(criteria));
        if (!this.initSearch) {
            this.initResultList();
            this.initSearch = true;
        } else {
            this.refreshDao();
        }
    }

    initResultList() {
        this.resultListDatabase = new ResultListHttpDao(this.http, this.criteriaSearchService);
        // If the user changes the sort order, reset back to the first page.
        this.paginator.pageIndex = this.listProperties.page;
        this.paginator.pageSize = this.listProperties.pageSize;
        this.sort.sortChange.subscribe(() => this.paginator.pageIndex = 0);
        // When list is refresh (sort, page, filters)
        merge(this.sort.sortChange, this.paginator.page, this.filtersChange)
            .pipe(
                takeUntil(this.destroy$),
                startWith({}),
                switchMap(() => {
                    if (!this.isLoadingResults) {
                        // To Reset scroll
                        this.data = [];
                        if (this.sidenavRight !== undefined) {
                            this.sidenavRight.close();
                        }
                        this.isLoadingResults = true;
                        this.loadingResult.emit(true);
                        return this.resultListDatabase!.getRepoIssues(
                            this.sort.active, this.sort.direction, this.paginator.pageIndex, this.searchUrl, this.listProperties, this.paginator.pageSize, this.criteria, this.dataFilters, this.from);
                    } else {
                        /**
                         * To resolve the error :
                         * You provided 'undefined' where a stream was expected. You can provide an Observable, Promise, Array, or Iterable
                         */
                        return new Observable<BasketList>();
                    }
                }),
                map((data: any) => {
                    this.selectedRes = [];
                    // Flip flag to show that loading has finished.
                    this.isLoadingResults = false;
                    this.loadingResult.emit(false);
                    data = this.processPostData(data);
                    this.dataResult.emit(data);
                    this.templateColumns = data.templateColumns;
                    this.dataFilters = data.filters;
                    this.criteriaSearchService.updateListsPropertiesFilters(data.filters);
                    this.resultsLength = data.count;
                    this.paginatorLength = data.count > 10000 ? 10000 : data.count;
                    this.allResInBasket = data.allResources;
                    return data.resources;
                }),
                catchError((err: any) => {
                    this.notify.handleErrors(err);
                    this.selectedRes = [];
                    this.data = [];
                    this.resultsLength = 0;
                    this.paginatorLength = 0;
                    this.dataFilters = {};
                    this.allResInBasket = [];
                    this.isLoadingResults = false;
                    this.loadingResult.emit(false);
                    this.dataResult.emit([]);
                    this.initSearch = false;
                    return of(false);
                })
            ).subscribe(data => this.data = data);
    }

    goTo(row: any) {
        // this.criteriaSearchService.filterMode = false;
        if (this.docUrl === '../rest/resources/' + row.resId + '/content' && this.sidenavRight.opened) {
            this.sidenavRight.close();
        } else {
            this.docUrl = '../rest/resources/' + row.resId + '/content';
            this.currentChrono = row.chrono;
            this.innerHtml = this.sanitizer.bypassSecurityTrustHtml(
                '<iframe style=\'height:100%;width:100%;\' src=\'' + this.docUrl + '\' class=\'embed-responsive-item\'>' +
                '</iframe>');
            this.sidenavRight.open();
        }
    }

    goToDetail(row: any) {
        this.router.navigate([`/resources/${row.resId}`], {queryParams: {fromSearch: true}});
    }

    goToFolder(folder: any) {
        this.router.navigate([`/folders/${folder.id}`]);
    }

    togglePanel(mode: string, row: any) {
        const thisSelect = { checked: true };
        const thisDeselect = { checked: false };
        row.checked = true;
        this.toggleAllRes(thisDeselect);
        this.toggleRes(thisSelect, row);

        if (this.currentResource.resId === row.resId && this.sidenavRight.opened && this.currentMode === mode) {
            this.sidenavRight.close();
        } else {
            this.currentMode = mode;
            this.currentResource = row;
            this.appPanelList.loadComponent(mode, row);
            this.sidenavRight.open();
        }
    }

    refreshBadgeNotes(nb: number) {
        this.currentResource.countNotes = nb;
    }

    refreshFolderInformations() {
        this.http.get('../rest/folders/' + this.folderInfo.id)
            .subscribe((data: any) => {
                const keywordEntities = [{
                    keyword: 'ALL_ENTITIES',
                    text: this.translate.instant('lang.allEntities'),
                }];
                this.folderInfo = {
                    'id': data.folder.id,
                    'label': data.folder.label,
                    'ownerDisplayName': data.folder.ownerDisplayName,
                    'entitiesSharing': data.folder.sharing.entities.map((entity: any) => {
                        if (!this.functions.empty(entity.label)) {
                            return entity.label;
                        } else {
                            return keywordEntities.filter((element: any) => element.keyword === entity.keyword)[0].text;
                        }
                    }),
                };
                this.headerService.setHeader(this.folderInfo.label, '', 'fa fa-folder-open');
            });
    }

    refreshBadgeAttachments(nb: number) {
        this.currentResource.countAttachments = nb;
    }

    refreshBadgeSentResource(nb: number) {
        this.currentResource.countSentResources = nb;
    }

    refreshDao() {
        this.paginator.pageIndex = this.listProperties.page;
        this.filtersChange.emit();
    }

    refreshDaoAfterAction() {
        this.sidenavRight.close();
        this.refreshDao();
        const e: any = { checked: false };
        this.toggleAllRes(e);
    }

    viewThumbnail(row: any) {
        if (row.hasDocument) {
            this.thumbnailUrl = '../rest/resources/' + row.resId + '/thumbnail';
            $('#viewThumbnailDoc').show();
            $('#listContent').css({ 'overflow': 'hidden' });
        }
    }

    closeThumbnail() {
        $('#viewThumbnailDoc').hide();
        $('#listContent').css({ 'overflow': 'auto' });
    }

    getTitle(row: any) {
        if (!row.hasDocument) {
            return this.translate.instant('lang.noDocument');
        } else if (row.hasDocument && row.canConvert) {
            return this.translate.instant('lang.viewResource');
        } else if (row.hasDocument && !row.canConvert) {
            return this.translate.instant('lang.noAvailablePreview');
        }
    }

    processPostData(data: any) {
        data.resources.forEach((element: any) => {
            if (Object.keys(this.criteria).filter((crit) => crit.indexOf('role_') > -1).length > 0) {
                element['inDiffusions'] = true;
            }
            // Process main datas
            Object.keys(element).forEach((key) => {
                element[key + '_title'] = element[key];
                if (key === 'statusImage' && element[key] == null) {
                    element[key] = 'fa-question undefined';
                } else if ((element[key] == null || element[key] === '') && ['closingDate', 'countAttachments', 'countNotes', 'display', 'mailTracking', 'hasDocument', 'binding'].indexOf(key) === -1) {
                    element[key] = this.translate.instant('lang.undefined');
                }

                // HighLight data
                if (Object.keys(this.criteria).indexOf(key) > -1) {
                    element[key] = this.highlightPipe.transform(element[key], this.criteria[key].values);
                } else if (['subject', 'chrono', 'resId'].indexOf(key) > -1 && Object.keys(this.criteria).indexOf('meta') > -1) {
                    element[key] = this.highlightPipe.transform(element[key], this.criteria['meta'].values);
                }
                if (key === 'countAttachments' && Object.keys(this.criteria).indexOf('attachment_type') > -1) {
                    element['inAttachments'] = true;
                }
                if (key === 'countNotes' && Object.keys(this.criteria).indexOf('notes') > -1) {
                    element['inNotes'] = true;
                }
                if (key === 'statusLabel' && Object.keys(this.criteria).indexOf('status') > -1) {
                    element['inStatus'] = true;
                }
            });
            // Process secondary datas
            element.display.forEach((key: any) => {
                key.event = false;
                key.displayTitle = key.displayValue;
                if ((key.displayValue == null || key.displayValue === '') && ['getCreationAndProcessLimitDates', 'getParallelOpinionsNumber'].indexOf(key.value) === -1) {
                    key.displayValue = this.translate.instant('lang.undefined');
                    key.displayTitle = '';
                } else if (['getSenders', 'getRecipients'].indexOf(key.value) > -1) {
                    key.event = true;
                    if (key.displayValue.length > 1) {
                        key.displayTitle = Array.isArray(key.displayValue) ? key.displayValue.join(' - ') : key.displayValue;
                        key.displayValue = '<b>' + key.displayValue.length + '</b> ' + this.translate.instant('lang.contactsAlt');
                    } else if (key.displayValue.length === 1) {
                        key.displayValue = key.displayValue[0];
                    } else {
                        key.displayValue = this.translate.instant('lang.undefined');
                    }
                } else if (key.value === 'getCreationAndProcessLimitDates') {
                    key.icon = '';
                } else if (key.value === 'getVisaWorkflow') {
                    let formatWorkflow: any = [];
                    let content = '';
                    let user = '';
                    const displayTitle: string[] = [];

                    key.displayValue.forEach((visa: any, keyVis: number) => {
                        content = '';
                        user = visa.user;
                        displayTitle.push(user);

                        if (visa.mode === 'sign') {
                            user = '<u>' + user + '</u>';
                        }
                        if (visa.date === '') {
                            content = '<i class="fa fa-hourglass-half"></i> <span title="' + this.translate.instant('lang.' + visa.mode + 'User') + '">' + user + '</span>';
                        } else {
                            content = '<span color="accent" style=""><i class="fa fa-check"></i> <span title="' + this.translate.instant('lang.' + visa.mode + 'User') + '">' + user + '</span></span>';
                        }

                        if (visa.current && keyVis >= 0) {
                            content = '<b color="primary">' + content + '</b>';
                        }

                        formatWorkflow.push(content);

                    });

                    // TRUNCATE DISPLAY LIST
                    const index = key.displayValue.map((e: any) => e.current).indexOf(true);
                    if (index > 0) {
                        formatWorkflow = formatWorkflow.slice(index - 1);
                        formatWorkflow = formatWorkflow.reverse();
                        const indexReverse = key.displayValue.map((e: any) => e.current).reverse().indexOf(true);
                        if (indexReverse > 1) {
                            formatWorkflow = formatWorkflow.slice(indexReverse - 1);
                        }
                        formatWorkflow = formatWorkflow.reverse();
                    } else if (index === 0) {
                        formatWorkflow = formatWorkflow.reverse();
                        formatWorkflow = formatWorkflow.slice(index - 2);
                        formatWorkflow = formatWorkflow.reverse();
                    } else if (index === -1) {
                        formatWorkflow = formatWorkflow.slice(formatWorkflow.length - 2);
                    }
                    if (index >= 2 || (index === -1 && key.displayValue.length >= 3)) {
                        formatWorkflow.unshift('...');
                    }
                    if (index !== -1 && index - 2 <= key.displayValue.length && index + 2 < key.displayValue.length && key.displayValue.length >= 3) {
                        formatWorkflow.push('...');
                    }

                    key.displayValue = formatWorkflow.join(' <i class="fas fa-long-arrow-alt-right"></i> ');
                    key.displayTitle = displayTitle.join(' - ');
                } else if (key.value === 'getSignatories') {
                    const userList: any[] = [];
                    key.displayValue.forEach((visa: any) => {
                        userList.push(visa.user);
                    });
                    key.displayValue = userList.join(', ');
                    key.displayTitle = userList.join(', ');
                } else if (key.value === 'getParallelOpinionsNumber') {
                    key.displayTitle = key.displayValue + ' ' + this.translate.instant('lang.opinionsSent');

                    if (key.displayValue > 0) {
                        key.displayValue = '<b color="primary">' + key.displayValue + '</b> ' + this.translate.instant('lang.opinionsSent');
                    } else {
                        key.displayValue = key.displayValue + ' ' + this.translate.instant('lang.opinionsSent');
                    }
                }
                key.label = key.displayLabel === undefined ? this.translate.instant('lang.' + key.value) : key.displayLabel;

                // HighLight sub data
                key.displayValue = this.setHighLightData(key);

            });
            element['checked'] = this.selectedRes.indexOf(element['resId']) !== -1;
        });

        return data;
    }

    setHighLightData(data: any) {
        const regex = /indexingCustomField_[.]*/g;

        if (Object.keys(this.criteria).indexOf(this.indexingFieldService.mappingdata[data.value]) > -1) {
            if (Array.isArray(this.criteria[this.indexingFieldService.mappingdata[data.value]].values)) {
                this.criteria[this.indexingFieldService.mappingdata[data.value]].values.forEach((val: any) => {
                    data.displayValue = this.highlightPipe.transform(data.displayValue, !this.functions.empty(val.label) ? val.label.replace(/&nbsp;/g, '') : val);
                });
            } else {
                data.displayValue = this.highlightPipe.transform(data.displayValue, this.criteria[this.indexingFieldService.mappingdata[data.value]].values);
            }
        } else if (data.value === 'getAssignee') {
            if (Object.keys(this.criteria).indexOf('role_dest') > -1) {
                this.criteria['role_dest'].values.forEach((val: any) => {
                    if (val !== null) {
                        data.displayValue = this.highlightPipe.transform(data.displayValue, val.label.replace(/&nbsp;/g, ''));
                    }
                });
            }
            if (Object.keys(this.criteria).indexOf('destination') > -1) {
                this.criteria['destination'].values.forEach((val: any) => {
                    data.displayValue = this.highlightPipe.transform(data.displayValue, val.label.replace(/&nbsp;/g, ''));
                });
            }
        } else if (data.value === 'getCreationAndProcessLimitDates') {
            if (Object.keys(this.criteria).indexOf('creationDate') > -1) {
                data.displayValue.creationDateHighlighted = true;
            }
            if (Object.keys(this.criteria).indexOf('processLimitDate') > -1) {
                data.displayValue.processLimitDateHighlighted = true;
            }
            if (Object.keys(this.criteria).indexOf('closingDate') > -1) {
                data.displayValue.closingDateHighlighted = true;
            }
        } else if (data.value.match(regex) !== null && Object.keys(this.criteria).indexOf(data.value) > -1) {
            if (Array.isArray(this.criteria[data.value].values)) {
                this.criteria[data.value].values.forEach((val: any) => {
                    data.displayValue = this.highlightPipe.transform(data.displayValue, val.label.replace(/&nbsp;/g, ''));
                });
            } else {
                data.displayValue = this.highlightPipe.transform(data.displayValue, this.criteria[data.value].values);
            }
        }
        return data.displayValue;
    }

    toggleRes(e: any, row: any) {
        if (this.singleSelection) {
            this.toggleAllRes({ checked: false });
        }
        if (e.checked) {
            if (this.selectedRes.indexOf(row.resId) === -1) {
                this.selectedRes.push(row.resId);
                row.checked = true;
            }
        } else {
            const index = this.selectedRes.indexOf(row.resId);
            this.selectedRes.splice(index, 1);
            row.checked = false;
        }
    }

    toggleAllRes(e: any) {
        this.selectedRes = [];
        if (e.checked) {
            this.data.forEach((element: any) => {
                element['checked'] = true;
            });
            this.selectedRes = JSON.parse(JSON.stringify(this.allResInBasket));
        } else {
            this.data.forEach((element: any) => {
                element['checked'] = false;
            });
        }
    }

    selectSpecificRes(row: any) {
        const thisSelect = { checked: true };
        const thisDeselect = { checked: false };

        this.toggleAllRes(thisDeselect);
        this.toggleRes(thisSelect, row);
    }

    open({ x, y }: MouseEvent, row: any) {
        const thisSelect = { checked: true };
        const thisDeselect = { checked: false };
        if (row.checked === false) {
            row.checked = true;
            this.toggleAllRes(thisDeselect);
            this.toggleRes(thisSelect, row);
        }
        if (this.actionMode) {
            this.actionsList.open(x, y, row);
        }

        // prevents default
        return false;
    }

    listTodrag() {
        return this.foldersService.getDragIds();
    }

    toggleMailTracking(row: any) {
        if (!row.mailTracking) {
            this.http.post('../rest/resources/follow', { resources: [row.resId] }).pipe(
                tap(() => {
                    this.headerService.nbResourcesFollowed++;
                    row.mailTracking = !row.mailTracking;
                }),
                catchError((err: any) => {
                    this.notify.handleSoftErrors(err);
                    return of(false);
                })
            ).subscribe();
        } else {
            this.http.request('DELETE', '../rest/resources/unfollow', { body: { resources: [row.resId] } }).pipe(
                tap(() => {
                    this.headerService.nbResourcesFollowed--;
                    row.mailTracking = !row.mailTracking;
                }),
                catchError((err: any) => {
                    this.notify.handleSoftErrors(err);
                    return of(false);
                })
            ).subscribe();
        }
    }

    viewDocument(row: any) {
        this.http.get(`../rest/resources/${row.resId}/fileInformation`).pipe(
            tap((res: any) => {
                if (res.information.canConvert) {
                    this.http.get(`../rest/resources/${row.resId}/content?mode=view`, { responseType: 'blob' }).pipe(
                        tap((data: any) => {
                            const file = new Blob([data], { type: 'application/pdf' });
                            const fileURL = URL.createObjectURL(file);
                            const newWindow = window.open();
                            newWindow.document.write(`<iframe style="width: 100%;height: 100%;margin: 0;padding: 0;" src="${fileURL}" frameborder="0" allowfullscreen></iframe>`);
                            newWindow.document.title = row.chrono;
                        }),
                        catchError((err: any) => {
                            this.notify.handleBlobErrors(err);
                            return of(false);
                        })
                    ).subscribe();
                } else {
                    this.notify.handleSoftErrors(this.translate.instant('lang.noAvailablePreview'));
                }
            }),
            catchError((err: any) => {
                this.notify.handleSoftErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    emptyCriteria() {
        return Object.keys(this.criteria).length === 0;
    }

    isArrayType(value: any) {
        return (Array.isArray(value));
    }

    removeCriteria(identifier: string, value: any = null) {
        if (!this.isLoadingResults) {
            this.appCriteriaTool.toggleTool(true);
            if (identifier !== '_ALL') {
                const tmpArrCrit = [];
                if (value === null || this.criteria[identifier].values.length === 1) {
                    this.criteria[identifier].values = [];
                } else {
                    const indexArr = this.criteria[identifier].values.indexOf(value);
                    this.criteria[identifier].values.splice(indexArr, 1);
                }
                this.appCriteriaTool.resetCriteria(identifier, value);
            } else {
                Object.keys(this.criteria).forEach(key => {
                    this.criteria[key].values = [];
                });
                this.appCriteriaTool.resetAllCriteria();
            }
        }
    }

    getSelectedResources() {
        return this.selectedRes;
    }
}
export interface BasketList {
    folder: any;
    resources: any[];
    countResources: number;
    allResources: number[];
    filter: any[];
}

export class ResultListHttpDao {

    constructor(private http: HttpClient, private criteriaSearchService: CriteriaSearchService) { }

    getRepoIssues(sort: string, order: string, page: number, href: string, filters: any, pageSize: number, criteria: any, sideFilters: any, from: string = ''): Observable<BasketList> {
        this.criteriaSearchService.updateListsPropertiesPage(page);
        this.criteriaSearchService.updateListsPropertiesPageSize(pageSize);
        this.criteriaSearchService.updateListsPropertiesCriteria(criteria);
        const offset = page * pageSize;
        const requestUrl = `${href}?limit=${pageSize}&offset=${offset}&order=${filters.order}&orderDir=${filters.orderDir}`;
        let dataToSend = Object.assign({}, this.criteriaSearchService.formatDatas(JSON.parse(JSON.stringify(criteria))), { filters: sideFilters });
        dataToSend = {
            ... dataToSend,
            linkedResource: from === 'linkedResource' ? true : false
        };

        return this.http.post<BasketList>(requestUrl, dataToSend);
    }
}
