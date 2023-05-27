import { Component, OnInit, ViewChild, EventEmitter, ViewContainerRef, OnDestroy } from '@angular/core';
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
import { PanelListComponent } from '../../list/panel/panel-list.component';
import { AppService } from '@service/app.service';
import { BasketHomeComponent } from '../../basket/basket-home.component';
import { FolderActionListComponent } from '../folder-action-list/folder-action-list.component';
import { FiltersListService } from '@service/filtersList.service';
import { FoldersService } from '../folders.service';
import { FunctionsService } from '@service/functions.service';
import { merge, Observable, of, Subject, Subscription } from 'rxjs';

declare let $: any;

@Component({
    templateUrl: 'folder-document-list.component.html',
    styleUrls: ['folder-document-list.component.scss']
})
export class FolderDocumentListComponent implements OnInit, OnDestroy {

    @ViewChild('snav2', { static: true }) sidenavRight: MatSidenav;

    @ViewChild('actionsListContext', { static: true }) actionsList: FolderActionListComponent;
    @ViewChild('appPanelList', { static: true }) appPanelList: PanelListComponent;

    @ViewChild(MatPaginator, { static: true }) paginator: MatPaginator;
    @ViewChild('tableBasketListSort', { static: true }) sort: MatSort;
    @ViewChild('basketHome', { static: true }) basketHome: BasketHomeComponent;

    subscription: Subscription;

    currentSelectedChrono: string = '';


    loading: boolean = false;
    docUrl: string = '';
    public innerHtml: SafeHtml;
    basketUrl: string;
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
    data: any;
    resultsLength = 0;
    isLoadingResults = true;
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

    private destroy$ = new Subject<boolean>();

    constructor(
        public translate: TranslateService,
        private router: Router,
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
        public functions: FunctionsService) {

        // Event after process action
        this.subscription = this.foldersService.catchEvent().subscribe((result: any) => {

            if (result.type === 'refreshFolderInformations') {
                if (result.content.id == this.folderInfo.id) {
                    this.refreshFolderInformations();
                }
            }
            if (result.type === 'function' && result.content === 'refreshDao') {
                this.refreshDao();
            }
        });
    }

    ngOnInit(): void {

        this.loading = false;

        this.isLoadingResults = false;

        this.route.params.subscribe(params => {
            this.folderInfoOpened = false;
            this.dragInit = true;
            this.destroy$.next(true);

            this.http.get('../rest/folders/' + params['folderId'])
                .subscribe((data: any) => {
                    const keywordEntities = [{
                        keyword: 'ALL_ENTITIES',
                        text: this.translate.instant('lang.allEntities'),
                    }];
                    this.folderInfo = {
                        'id': params['folderId'],
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
                    this.foldersService.setFolder(this.folderInfo);
                    this.headerService.setHeader(this.folderInfo.label, '', 'fa fa-folder-open');

                });
            this.basketUrl = '../rest/folders/' + params['folderId'] + '/resources';
            this.filtersListService.filterMode = false;
            this.selectedRes = [];
            this.sidenavRight.close();

            this.listProperties = this.filtersListService.initListsProperties(this.headerService.user.id, 0, params['folderId'], 'folder');

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
                    this.isLoadingResults = true;
                    return this.resultListDatabase!.getRepoIssues(
                        this.sort.active, this.sort.direction, this.paginator.pageIndex, this.basketUrl, this.filtersListService.getUrlFilters(), this.paginator.pageSize);
                }),
                map((data: any) => {
                    // Flip flag to show that loading has finished.
                    this.isLoadingResults = false;
                    data = this.processPostData(data);
                    this.resultsLength = data.countResources;
                    this.allResInBasket = data.allResources;
                    // this.headerService.setHeader('Dossier : ' + this.folderInfo.label);
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
            $('#viewThumbnail').show();
            $('#listContent').css({ 'overflow': 'hidden' });
        }
    }

    closeThumbnail() {
        $('#viewThumbnail').hide();
        $('#listContent').css({ 'overflow': 'auto' });
    }

    processPostData(data: any) {
        data.resources.forEach((element: any) => {
            // Process main datas
            Object.keys(element).forEach((key) => {
                if (key === 'statusImage' && element[key] == null) {
                    element[key] = 'fa-question undefined';
                } else if ((element[key] == null || element[key] === '') && ['closingDate', 'countAttachments', 'countNotes', 'display', 'mailTracking', 'hasDocument', 'binding'].indexOf(key) === -1) {
                    element[key] = this.translate.instant('lang.undefined');
                }
            });

            element['checked'] = this.selectedRes.indexOf(element['resId']) !== -1;
        });

        return data;
    }

    toggleRes(e: any, row: any) {
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
        this.actionsList.open(x, y, row);

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
}
export interface BasketList {
    folder: any;
    resources: any[];
    countResources: number;
    allResources: number[];
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
