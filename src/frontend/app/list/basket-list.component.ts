import { Component, OnInit, ViewChild, EventEmitter, ViewContainerRef, OnDestroy } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { TranslateService } from '@ngx-translate/core';
import { NotificationService } from '@service/notification/notification.service';
import { MatDialog } from '@angular/material/dialog';
import { MatPaginator } from '@angular/material/paginator';
import { MatSidenav } from '@angular/material/sidenav';
import { MatSort } from '@angular/material/sort';
import { DomSanitizer, SafeHtml } from '@angular/platform-browser';
import { startWith, switchMap, map, catchError, takeUntil, tap } from 'rxjs/operators';
import { ActivatedRoute, Router } from '@angular/router';
import { HeaderService } from '@service/header.service';
import { FiltersListService } from '@service/filtersList.service';
import { FiltersToolComponent } from './filters/filters-tool.component';
import { ActionsListComponent } from '../actions/actions-list.component';
import { Overlay } from '@angular/cdk/overlay';
import { PanelListComponent } from './panel/panel-list.component';
import { AppService } from '@service/app.service';
import { FoldersService } from '../folder/folders.service';
import { ActionsService } from '../actions/actions.service';
import { ContactResourceModalComponent } from '../contact/contact-resource/modal/contact-resource-modal.component';
import { merge, Observable, of, Subject, Subscription } from 'rxjs';

declare let $: any;

@Component({
    templateUrl: 'basket-list.component.html',
    styleUrls: ['basket-list.component.scss'],
})
export class BasketListComponent implements OnInit, OnDestroy {

    @ViewChild('snav2', { static: true }) sidenavRight: MatSidenav;
    @ViewChild('actionsListContext', { static: true }) actionsList: ActionsListComponent;
    @ViewChild('filtersTool', { static: true }) filtersTool: FiltersToolComponent;
    @ViewChild('appPanelList', { static: true }) appPanelList: PanelListComponent;
    @ViewChild('tableBasketListSort', { static: true }) sort: MatSort;
    @ViewChild(MatPaginator, { static: true }) paginator: MatPaginator;

    subscription: Subscription;
    subscription2: Subscription;

    currentSelectedChrono: string = '';

    loading: boolean = false;
    docUrl: string = '';
    public innerHtml: SafeHtml;
    basketUrl: string;

    injectDatasParam = {
        resId: 0,
        editable: false
    };
    currentResource: any = {};

    filtersChange = new EventEmitter();

    dragInit: boolean = true;

    templateColumns: number = 7;
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
    displayedSecondaryData: any = [];

    resultListDatabase: ResultListHttpDao | null;
    data: any;
    resultsLength = 0;
    isLoadingResults = true;
    listProperties: any = {};
    currentBasketInfo: any = {};
    currentChrono: string = '';
    currentMode: string = '';
    defaultAction = {
        id: 19,
        component: 'processAction'
    };
    thumbnailUrl: string = '';

    selectedRes: number[] = [];
    allResInBasket: number[] = [];
    selectedDiffusionTab: number = 0;
    specificChrono: string = '';
    displayFolderTags: boolean = false;

    private destroy$ = new Subject<boolean>();

    constructor(
        public translate: TranslateService,
        private router: Router,
        private _activatedRoute: ActivatedRoute,
        private route: ActivatedRoute,
        public http: HttpClient,
        public dialog: MatDialog,
        private sanitizer: DomSanitizer,
        private headerService: HeaderService,
        public filtersListService: FiltersListService,
        private notify: NotificationService,
        public overlay: Overlay,
        public viewContainerRef: ViewContainerRef,
        public appService: AppService,
        public foldersService: FoldersService,
        private actionService: ActionsService) {
        _activatedRoute.queryParams.subscribe(
            params => this.specificChrono = params.chrono
        );

        // Event after process action
        this.subscription = this.foldersService.catchEvent().subscribe((result: any) => {
            if (result.type === 'function') {
                this[result.content]();
            }
        });
        this.subscription2 = this.actionService.catchAction().subscribe((message: any) => {
            this.refreshDaoAfterAction();
        });
    }

    ngOnInit(): void {
        this.loading = false;

        this.isLoadingResults = false;

        this.route.params.subscribe(params => {

            this.dragInit = true;
            this.destroy$.next(true);

            this.basketUrl = '../rest/resourcesList/users/' + params['userSerialId'] + '/groups/' + params['groupSerialId'] + '/baskets/' + params['basketId'];

            this.currentBasketInfo = {
                ownerId: params['userSerialId'],
                groupId: params['groupSerialId'],
                basketId: params['basketId']
            };
            this.headerService.currentBasketInfo = this.currentBasketInfo;

            this.filtersListService.filterMode = false;
            this.selectedRes = [];
            this.sidenavRight.close();

            this.listProperties = this.filtersListService.initListsProperties(this.currentBasketInfo.ownerId, this.currentBasketInfo.groupId, this.currentBasketInfo.basketId, 'basket', this.specificChrono);


            setTimeout(() => {
                this.dragInit = false;
            }, 1000);
            this.initResultList();

        },
        (err: any) => {
            this.notify.handleErrors(err);
        });
    }

    ngOnDestroy() {
        this.destroy$.next(true);
        this.subscription.unsubscribe();
        this.subscription2.unsubscribe();
    }

    initResultList() {
        this.resultListDatabase = new ResultListHttpDao(this.http, this.filtersListService);
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
                    // To Reset scroll
                    this.data = [];
                    this.isLoadingResults = true;
                    return this.resultListDatabase!.getRepoIssues(
                        this.sort.active, this.sort.direction, this.paginator.pageIndex, this.basketUrl, this.filtersListService.getUrlFilters(), this.paginator.pageSize);
                }),
                map((data: any) => {
                    // Flip flag to show that loading has finished.
                    this.isLoadingResults = false;
                    data = this.processPostData(data);
                    this.resultsLength = data.count;
                    this.allResInBasket = data.allResources;
                    this.currentBasketInfo.basket_id = data.basket_id;
                    this.defaultAction = data.defaultAction;
                    this.displayFolderTags = data.displayFolderTags;
                    this.templateColumns = data.templateColumns;
                    this.headerService.setHeader(data.basketLabel, '', 'fa fa-inbox');
                    return data.resources;
                }),
                catchError((err: any) => {
                    this.notify.handleErrors(err);
                    this.router.navigate(['/home']);
                    this.isLoadingResults = false;
                    return of(false);
                })
            ).subscribe(data => this.data = data);
    }

    goTo(row: any) {
        this.filtersListService.filterMode = false;
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
        this.router.navigate([`/resources/${row.resId}`]);
    }

    goToFolder(folder: any) {
        this.router.navigate([`/folders/${folder.id}`]);
    }

    togglePanel(mode: string, row: any) {
        const thisSelect = { checked: true };
        const thisDeselect = { checked: false };
        const previousRes = this.currentResource;
        row.checked = true;

        this.toggleAllRes(thisDeselect);
        this.toggleRes(thisSelect, row);

        if (previousRes.resId === row.resId && this.sidenavRight.opened && this.currentMode === mode) {
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

    filterThis(value: string) {
        this.filtersTool.setInputSearch(value);
    }

    viewThumbnail(row: any) {
        if (row.hasDocument) {
            const timeStamp = +new Date();
            this.thumbnailUrl = '../rest/resources/' + row.resId + '/thumbnail?tsp=' + timeStamp;
            $('#viewThumbnail').show();
            $('#listContent').css({ 'overflow': 'hidden' });
        }
    }

    closeThumbnail() {
        $('#viewThumbnail').hide();
        $('#listContent').css({ 'overflow': 'auto' });
    }

    processPostData(data: any) {
        this.displayedSecondaryData = [];
        data.resources.forEach((element: any) => {
            // Process main datas
            Object.keys(element).forEach((key) => {
                if (key === 'statusImage' && element[key] == null) {
                    element[key] = 'fa-question undefined';
                } else if ((element[key] == null || element[key] === '') && ['closingDate', 'countAttachments', 'countNotes', 'display', 'folders', 'hasDocument', 'integrations', 'mailTracking'].indexOf(key) === -1) {
                    element[key] = this.translate.instant('lang.undefined');
                }
            });

            // Process secondary datas
            element.display.forEach((key: any) => {
                key.event = false;
                key.displayTitle = key.displayValue;
                if ((key.displayValue == null || key.displayValue === '') && ['getCreationDate', 'getProcessLimitDate', 'getCreationAndProcessLimitDates', 'getParallelOpinionsNumber'].indexOf(key.value) === -1) {
                    key.displayValue = this.translate.instant('lang.undefined');
                    key.displayTitle = '';
                } else if (['getSenders', 'getRecipients'].indexOf(key.value) > -1) {
                    key.event = true;
                    if (key.displayValue.length > 1) {
                        key.displayTitle = key.displayValue.join(' - ');
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
            });

            if (this.selectedRes.indexOf(element['resId']) === -1) {
                element['checked'] = false;
            } else {
                element['checked'] = true;
            }
        });
        return data;
    }

    toggleRes(e: any, row: any) {
        if (e.checked) {
            if (this.selectedRes.indexOf(row.resId) === -1) {
                this.currentResource = row;
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
        this.actionsList.open(x, y, row);

        // prevents default
        return false;
    }

    launch(action: any, row: any) {
        const thisSelect = { checked: true };
        const thisDeselect = { checked: false };
        row.checked = true;
        this.toggleAllRes(thisDeselect);
        this.toggleRes(thisSelect, row);

        setTimeout(() => {
            this.actionsList.launchEvent(action, row);
        }, 200);
    }

    listTodrag() {
        return this.foldersService.getDragIds();
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

    viewDocument(row: any) {
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
    }

    toggleMailTracking(row: any) {
        if (!row.mailTracking) {
            this.http.post('../rest/resources/follow', { resources: [row.resId] }).pipe(
                tap(() => this.headerService.nbResourcesFollowed++),
                catchError((err: any) => {
                    this.notify.handleErrors(err);
                    return of(false);
                })
            ).subscribe();
        } else {
            this.http.request('DELETE', '../rest/resources/unfollow', { body: { resources: [row.resId] } }).pipe(
                tap(() => this.headerService.nbResourcesFollowed--),
                catchError((err: any) => {
                    this.notify.handleErrors(err);
                    return of(false);
                })
            ).subscribe();
        }
        row.mailTracking = !row.mailTracking;
    }
}

export interface BasketList {
    displayFolderTags: boolean;
    resources: any[];
    count: number;
    basketLabel: string;
    basket_id: string;
    defaultAction: any;
    allResources: number[];
    templateColmuns: number;
}

export class ResultListHttpDao {

    constructor(private http: HttpClient, private filtersListService: FiltersListService) { }

    getRepoIssues(sort: string, order: string, page: number, href: string, filters: string, pageSize: number): Observable<BasketList> {
        this.filtersListService.updateListsPropertiesPage(page);
        this.filtersListService.updateListsPropertiesPageSize(pageSize);
        const offset = page * pageSize;
        const requestUrl = `${href}?limit=${pageSize}&offset=${offset}${filters}`;

        return this.http.get<BasketList>(requestUrl);
    }
}
