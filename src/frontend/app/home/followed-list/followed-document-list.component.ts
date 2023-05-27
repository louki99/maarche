import { Component, OnInit, ViewChild, EventEmitter, ViewContainerRef, OnDestroy } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { TranslateService } from '@ngx-translate/core';
import { NotificationService } from '@service/notification/notification.service';
import { MatDialog, MatDialogRef } from '@angular/material/dialog';
import { MatPaginator } from '@angular/material/paginator';
import { MatSidenav } from '@angular/material/sidenav';
import { MatSort } from '@angular/material/sort';
import { DomSanitizer, SafeHtml } from '@angular/platform-browser';
import { startWith, switchMap, map, catchError, takeUntil, tap, exhaustMap, filter } from 'rxjs/operators';
import { ActivatedRoute, Router } from '@angular/router';
import { HeaderService } from '@service/header.service';
import { Overlay } from '@angular/cdk/overlay';
import { PanelListComponent } from '../../list/panel/panel-list.component';
import { AppService } from '@service/app.service';
import { BasketHomeComponent } from '../../basket/basket-home.component';
import { ConfirmComponent } from '../../../plugins/modal/confirm.component';
import { FollowedActionListComponent } from '../followed-action-list/followed-action-list.component';
import { FiltersListService } from '@service/filtersList.service';
import { MenuShortcutComponent } from '../../menu/menu-shortcut.component';
import { FoldersService } from '../../folder/folders.service';
import { of, merge, Subject, Subscription, Observable } from 'rxjs';

declare let $: any;

@Component({
    templateUrl: 'followed-document-list.component.html',
    styleUrls: ['followed-document-list.component.scss']
})
export class FollowedDocumentListComponent implements OnInit, OnDestroy {

    @ViewChild('actionsListContext', { static: true }) actionsList: FollowedActionListComponent;
    @ViewChild('appPanelList', { static: true }) appPanelList: PanelListComponent;
    @ViewChild(MatPaginator, { static: true }) paginator: MatPaginator;
    @ViewChild('tableBasketListSort', { static: true }) sort: MatSort;
    @ViewChild('basketHome', { static: true }) basketHome: BasketHomeComponent;
    @ViewChild('menuShortcut', { static: true }) menuShortcut: MenuShortcutComponent;
    @ViewChild('snav2', { static: true }) sidenavRight: MatSidenav;

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
        public foldersService: FoldersService) {

        // Event after process action
        this.subscription = this.foldersService.catchEvent().subscribe((result: any) => {
            if (result.type === 'function' && result.content === 'refreshDao') {
                this.refreshDao();
            }
        });
    }

    ngOnInit(): void {

        this.loading = false;

        this.isLoadingResults = false;

        this.route.params.subscribe(params => {

            this.dragInit = true;
            this.destroy$.next(true);

            this.http.get('../rest/followedResources')
                .subscribe((data: any) => {
                    this.headerService.setHeader(this.translate.instant('lang.followedMail'), '', 'fas fa-star');
                });
            this.basketUrl = '../rest/followedResources';
            this.filtersListService.filterMode = false;
            this.selectedRes = [];


            this.listProperties = this.filtersListService.initListsProperties(this.headerService.user.id, 0, null, 'followed');

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
                } else if ((element[key] == null || element[key] === '') && ['closingDate', 'countAttachments', 'countNotes', 'display', 'mailTracking', 'hasDocument', 'folders', 'binding'].indexOf(key) === -1) {
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

    unfollowMail(row: any) {
        this.dialogRef = this.dialog.open(ConfirmComponent, { panelClass: 'maarch-modal', autoFocus: false, disableClose: true, data: { title: this.translate.instant('lang.delete'), msg: this.translate.instant('lang.stopFollowingAlert') } });

        this.dialogRef.afterClosed().pipe(
            filter((data: string) => data === 'ok'),
            exhaustMap(() => this.http.request('DELETE', '../rest/resources/unfollow', { body: { resources: [row.resId] } })),
            tap((data: any) => {
                this.headerService.nbResourcesFollowed--;
                this.initResultList();
                if (row.checked) {
                    this.sidenavRight.close();
                }
            }),
            catchError((err: any) => {
                this.notify.handleSoftErrors(err);
                return of(false);
            })
        ).subscribe();
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
