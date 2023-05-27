import { Component, OnInit, Inject, ViewChild, ElementRef, TemplateRef, ViewContainerRef } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Router, ActivatedRoute } from '@angular/router';
import { MatDialog, MatDialogRef, MAT_DIALOG_DATA } from '@angular/material/dialog';
import { MatPaginator } from '@angular/material/paginator';
import { MatSidenav } from '@angular/material/sidenav';
import { MatSort } from '@angular/material/sort';
import { MatTableDataSource } from '@angular/material/table';
import { TranslateService } from '@ngx-translate/core';
import { NotificationService } from '@service/notification/notification.service';
import { HeaderService } from '@service/header.service';
import { AppService } from '@service/app.service';
import { ConfirmComponent } from '@plugins/modal/confirm.component';
import { catchError, exhaustMap, filter, tap } from 'rxjs/operators';
import { of } from 'rxjs';

declare let $: any;

@Component({
    templateUrl: 'basket-administration.component.html',
    styleUrls: ['basket-administration.component.scss']
})
export class BasketAdministrationComponent implements OnInit {

    @ViewChild('snav2', { static: true }) public sidenavRight: MatSidenav;
    @ViewChild('adminMenuTemplate', { static: true }) adminMenuTemplate: TemplateRef<any>;
    @ViewChild(MatPaginator, { static: false }) paginator: MatPaginator;
    @ViewChild(MatSort, { static: false }) sort: MatSort;

    dialogRef: MatDialogRef<any>;

    selectedIndex: number = 0;


    loading: boolean = false;

    config: any = {};
    id: string;
    basket: any = {};
    basketClone: any = {};
    basketGroups: any[] = [];
    allGroups: any[] = [];
    basketIdAvailable: boolean;
    actionsList: any[] = [];
    list_display: any[] = [];
    creationMode: boolean;

    displayedColumns = ['label_action', 'actions'];
    orderColumns = ['alt_identifier', 'creation_date', 'process_limit_date', 'res_id', 'priority'];
    orderByColumns = ['asc', 'desc'];
    langVarName = [this.translate.instant('lang.chrono'), this.translate.instant('lang.creationDate'), this.translate.instant('lang.processLimitDate'), this.translate.instant('lang.id'), this.translate.instant('lang.priority')];
    langOrderName = [this.translate.instant('lang.ascending'), this.translate.instant('lang.descending')];
    orderColumnsSelected: any[] = [{ 'column': 'res_id', 'order': 'asc' }];
    dataSource: any;

    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        private route: ActivatedRoute,
        private router: Router,
        private notify: NotificationService,
        public dialog: MatDialog,
        private headerService: HeaderService,
        public appService: AppService,
        private viewContainerRef: ViewContainerRef
    ) { }

    applyFilter(filterValue: string) {
        filterValue = filterValue.trim();
        filterValue = filterValue.toLowerCase();
        this.dataSource.filter = filterValue;
    }

    ngOnInit(): void {
        this.loading = true;
        this.headerService.injectInSideBarLeft(this.adminMenuTemplate, this.viewContainerRef, 'adminMenu');

        this.route.params.subscribe((params: any) => {
            if (typeof params['id'] === 'undefined') {
                this.headerService.setHeader(this.translate.instant('lang.basketCreation'));
                this.creationMode = true;
                this.basketIdAvailable = false;
                this.loading = false;
            } else {
                this.orderColumnsSelected = [];

                this.creationMode = false;
                this.basketIdAvailable = true;
                this.id = params['id'];
                this.http.get('../rest/baskets/' + this.id)
                    .subscribe((data: any) => {
                        this.headerService.setHeader(this.translate.instant('lang.basketModification'), data.basket.basket_name);

                        this.basket = data.basket;
                        this.basket.id = data.basket.basket_id;
                        this.basket.name = data.basket.basket_name;
                        this.basket.description = data.basket.basket_desc;
                        this.basket.clause = data.basket.basket_clause;
                        this.basket.isSearchBasket = data.basket.is_visible !== 'Y';
                        this.basket.flagNotif = data.basket.flag_notif === 'Y';
                        if (this.basket.basket_res_order === '' || this.basket.basket_res_order == null) {
                            this.orderColumnsSelected = [];
                        } else {
                            const tmpOrderByColumnsSelected = this.basket.basket_res_order.split(', ');
                            for (let i = 0; i < tmpOrderByColumnsSelected.length; i++) {
                                const value = tmpOrderByColumnsSelected[i].split(' ');
                                if (!value[1]) {
                                    value[1] = 'desc';
                                }
                                this.orderColumnsSelected.push({ 'column': value[0], 'order': value[1] });
                            }
                        }

                        this.basketClone = JSON.parse(JSON.stringify(this.basket));

                        this.http.get('../rest/baskets/' + this.id + '/groups')
                            .subscribe((dataGroups: any) => {
                                this.allGroups = dataGroups.allGroups;

                                this.allGroups.forEach((tmpAllGroup: any) => {
                                    tmpAllGroup.isUsed = false;
                                    dataGroups.groups.forEach((tmpGroup: any) => {
                                        if (tmpAllGroup.group_id === tmpGroup.group_id) {
                                            tmpAllGroup.isUsed = true;
                                        }
                                    });
                                });

                                dataGroups.groups.forEach((tmpGroup: any) => {
                                    tmpGroup.groupActions.forEach((tmpAction: any) => {
                                        tmpAction.used_in_basketlist = tmpAction.used_in_basketlist === 'Y';
                                        tmpAction.used_in_action_page = tmpAction.used_in_action_page === 'Y';
                                        tmpAction.default_action_list = tmpAction.default_action_list === 'Y';
                                    });
                                });
                                this.basketGroups = dataGroups.groups;

                                this.loading = false;
                            }, (err) => {
                                this.notify.handleErrors(err);
                            });
                    }, (err) => {
                        this.notify.handleErrors(err);
                    });
            }
        });
    }

    openSettings(group: any, action: any) {
        this.config = { panelClass: 'maarch-modal', data: { group: group, action: action } };
        this.dialogRef = this.dialog.open(BasketAdministrationSettingsModalComponent, this.config);
        this.dialogRef.afterClosed().subscribe((result: any) => {
            if (result) {
                this.http.put('../rest/baskets/' + this.id + '/groups/' + result.group.group_id + '/actions', { 'groupActions': result.group.groupActions })
                    .subscribe(() => {
                        this.dialogRef = null;
                        this.notify.success(this.translate.instant('lang.basketUpdated'));
                    }, (err) => {
                        this.dialogRef = null;
                        this.openSettings(group, action);
                        this.notify.error(err.error.errors);
                    });
            }
        });
    }

    isAvailable() {
        if (this.basket.id) {
            this.http.get('../rest/baskets/' + this.basket.id)
                .subscribe(() => {
                    this.basketIdAvailable = false;
                }, (err) => {
                    this.basketIdAvailable = false;
                    if (err.error.errors === this.translate.instant('lang.basketNotFound')) {
                        this.basketIdAvailable = true;
                    }
                });
        } else {
            this.basketIdAvailable = false;
        }
    }

    onSubmit() {
        if (this.orderColumnsSelected !== null && this.orderColumnsSelected.length > 0) {
            const tmpBasketResOrder = [];
            for (let i = 0; i < this.orderColumnsSelected.length; i++) {
                tmpBasketResOrder[i] = this.orderColumnsSelected[i].column + ' ' + this.orderColumnsSelected[i].order;
            }
            this.basket.basket_res_order = tmpBasketResOrder.join(', ');
        } else {
            this.basket.basket_res_order = '';
        }
        if (this.creationMode) {
            this.http.post('../rest/baskets', this.basket)
                .subscribe(() => {
                    this.notify.success(this.translate.instant('lang.basketAdded'));
                    this.router.navigate(['/administration/baskets/' + this.basket.id]);
                }, (err) => {
                    this.notify.error(err.error.errors);
                });
        } else {
            this.http.put('../rest/baskets/' + this.id, this.basket)
                .subscribe(() => {
                    this.notify.success(this.translate.instant('lang.basketUpdated'));
                    this.router.navigate(['/administration/baskets']);
                }, (err) => {
                    this.notify.error(err.error.errors);
                });
        }
    }

    addLine() {
        this.orderColumnsSelected.push(JSON.parse(JSON.stringify(this.orderColumnsSelected[0])));
    }

    removeLine(index: number) {
        this.orderColumnsSelected.splice(index, 1);
    }

    initAction(groupIndex: number) {
        this.dataSource = new MatTableDataSource(this.basketGroups[groupIndex].groupActions);
        this.dataSource.sort = this.sort;
    }

    setDefaultAction(group: any, action: any) {
        group.groupActions.forEach((tmpAction: any) => {
            tmpAction.default_action_list = tmpAction.id === action.id ? true : false;
        });
        this.addAction(group);
    }

    unlinkGroup(groupIndex: any) {
        const dialogRef = this.dialog.open(ConfirmComponent, { panelClass: 'maarch-modal', autoFocus: false, disableClose: true, data: { title: this.translate.instant('lang.unlinkGroup') + ' ?', msg: this.translate.instant('lang.confirmAction') } });
        dialogRef.afterClosed().pipe(
            filter((data: string) => data === 'ok'),
            exhaustMap(() => this.http.delete('../rest/baskets/' + this.id + '/groups/' + this.basketGroups[groupIndex].group_id)),
            tap(() => {
                this.allGroups.forEach((tmpGroup: any) => {
                    if (tmpGroup.group_id === this.basketGroups[groupIndex].group_id) {
                        tmpGroup.isUsed = false;
                    }
                });
                this.basketGroups.splice(groupIndex, 1);
                this.notify.success(this.translate.instant('lang.basketUpdated'));
                this.selectedIndex = 0;
            }),
            catchError((err: any) => {
                this.notify.handleSoftErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    linkGroup() {
        this.config = { panelClass: 'maarch-modal', data: { basketId: this.basket.id, groups: this.allGroups, linkedGroups: this.basketGroups } };
        this.dialogRef = this.dialog.open(BasketAdministrationGroupListModalComponent, this.config);
        this.dialogRef.afterClosed().subscribe((result: any) => {
            if (result) {
                if (this.basketGroups.length > 0) {
                    result.list_display = this.basketGroups[this.basketGroups.length - 1].list_display;
                } else {
                    result.list_display = {'subInfos': [], 'templateColumns' : 0};
                }
                this.http.post('../rest/baskets/' + this.id + '/groups', result)
                    .subscribe(() => {
                        this.basketGroups.push(result);
                        this.allGroups.forEach((tmpGroup: any) => {
                            if (tmpGroup.group_id === result.group_id) {
                                tmpGroup.isUsed = true;
                            }
                        });
                        this.notify.success(this.translate.instant('lang.basketUpdated'));
                        this.selectedIndex = this.basketGroups.length;
                    }, (err) => {
                        this.notify.error(err.error.errors);
                    });
            }
            this.dialogRef = null;
        });
    }

    addAction(group: any) {
        this.http.put('../rest/baskets/' + this.id + '/groups/' + group.group_id + '/actions', { 'groupActions': group.groupActions })
            .subscribe(() => {
                this.notify.success(this.translate.instant('lang.actionsGroupBasketUpdated'));
            }, (err) => {
                this.notify.error(err.error.errors);
            });
    }

    toggleIsSearchBasket(basket: any) {
        basket.isSearchBasket = !basket.isSearchBasket;
        this.basketClone.isSearchBasket = basket.isSearchBasket;

        this.http.put('../rest/baskets/' + this.id, this.basketClone)
            .subscribe(() => {
                this.notify.success(this.translate.instant('lang.basketUpdated'));
            }, (err) => {
                this.notify.error(err.error.errors);
            });
    }

    toggleFlagNotif(basket: any) {
        basket.flagNotif = !basket.flagNotif;
        this.basketClone.flagNotif = basket.flagNotif;

        this.http.put('../rest/baskets/' + this.id, this.basketClone)
            .subscribe(() => {
                this.notify.success(this.translate.instant('lang.basketUpdated'));
            }, (err) => {
                this.notify.error(err.error.errors);
            });
    }

    unlinkAction(group: any, action: any) {
        const dialogRef = this.dialog.open(ConfirmComponent, { panelClass: 'maarch-modal', autoFocus: false, disableClose: true, data: { title: this.translate.instant('lang.unlinkAction') + ' ?', msg: this.translate.instant('lang.confirmAction') } });
        dialogRef.afterClosed().pipe(
            filter((data: string) => data === 'ok'),
            exhaustMap(() => this.http.put('../rest/baskets/' + this.id + '/groups/' + group.group_id + '/actions', { 'groupActions': group.groupActions })),
            tap(() => {
                action.checked = false;
                this.notify.success(this.translate.instant('lang.actionsGroupBasketUpdated'));
            }),
            catchError((err: any) => {
                this.notify.handleSoftErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    updateGroupInfo(group: any, ev: any) {
        group = ev;
    }
}

@Component({
    templateUrl: 'basket-administration-settings-modal.component.html',
    styles: ['.mat-dialog-content{height: 65vh;}']
})
export class BasketAdministrationSettingsModalComponent implements OnInit {

    @ViewChild('statusInput', { static: true }) statusInput: ElementRef;

    allEntities: any[] = [];

    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        private notify: NotificationService,
        @Inject(MAT_DIALOG_DATA) public data: any,
        public dialogRef: MatDialogRef<BasketAdministrationSettingsModalComponent>) {
    }

    ngOnInit(): void {
        this.http.get('../rest/entities')
            .subscribe((entities: any) => {
                const keywordEntities = [{
                    id: 'ALL_ENTITIES',
                    keyword: 'ALL_ENTITIES',
                    parent: '#',
                    icon: 'fa fa-hashtag',
                    allowed: true,
                    text: this.translate.instant('lang.allEntities')
                }, {
                    id: 'ENTITIES_JUST_BELOW',
                    keyword: 'ENTITIES_JUST_BELOW',
                    parent: '#',
                    icon: 'fa fa-hashtag',
                    allowed: true,
                    text: this.translate.instant('lang.immediatelyBelowMyPrimaryEntity')
                }, {
                    id: 'ENTITIES_BELOW',
                    keyword: 'ENTITIES_BELOW',
                    parent: '#',
                    icon: 'fa fa-hashtag',
                    allowed: true,
                    text: this.translate.instant('lang.belowAllMyEntities')
                }, {
                    id: 'ALL_ENTITIES_BELOW',
                    keyword: 'ALL_ENTITIES_BELOW',
                    parent: '#',
                    icon: 'fa fa-hashtag',
                    allowed: true,
                    text: this.translate.instant('lang.belowMyPrimaryEntity')
                }, {
                    id: 'MY_ENTITIES',
                    keyword: 'MY_ENTITIES',
                    parent: '#',
                    icon: 'fa fa-hashtag',
                    allowed: true,
                    text: this.translate.instant('lang.myEntities')
                }, {
                    id: 'MY_PRIMARY_ENTITY',
                    keyword: 'MY_PRIMARY_ENTITY',
                    parent: '#',
                    icon: 'fa fa-hashtag',
                    allowed: true,
                    text: this.translate.instant('lang.myPrimaryEntity')
                }, {
                    id: 'SAME_LEVEL_ENTITIES',
                    keyword: 'SAME_LEVEL_ENTITIES',
                    parent: '#',
                    icon: 'fa fa-hashtag',
                    allowed: true,
                    text: this.translate.instant('lang.sameLevelMyPrimaryEntity')
                }, {
                    id: 'ENTITIES_JUST_UP',
                    keyword: 'ENTITIES_JUST_UP',
                    parent: '#',
                    icon: 'fa fa-hashtag',
                    allowed: true,
                    text: this.translate.instant('lang.immediatelySuperiorMyPrimaryEntity')
                }, {
                    id: 'AUTO_REDIRECT_TO_USER',
                    keyword: 'AUTO_REDIRECT_TO_USER',
                    parent: '#',
                    icon: 'fa fa-hashtag',
                    allowed: true,
                    text: this.translate.instant('lang.autoRedirectToUser')
                }];

                keywordEntities.forEach((keyword: any) => {
                    this.allEntities.push(keyword);
                });
                entities.entities.forEach((entity: any) => {
                    this.allEntities.push(entity);
                });
            }, (err) => {
                this.notify.handleErrors(err);
            });
    }

    initService() {
        this.allEntities.forEach((entity: any) => {
            entity.state = { 'opened': false, 'selected': false };
            this.data.action.redirects.forEach((keyword: any) => {
                if ((entity.id === keyword.keyword && keyword.redirect_mode === 'ENTITY') || (entity.id === keyword.entity_id && keyword.redirect_mode === 'ENTITY')) {
                    entity.state = { 'opened': true, 'selected': true };
                }
            });
        });

        $('#jstree').jstree({
            'checkbox': {
                'three_state': false // no cascade selection
            },
            'core': {
                force_text: true,
                'themes': {
                    'name': 'proton',
                    'responsive': true
                },
                'data': this.allEntities.filter((entity: any) => entity.id !== 'AUTO_REDIRECT_TO_USER')
            },
            'plugins': ['checkbox', 'search']
        });
        $('#jstree')
            // listen for event
            .on('select_node.jstree', (e: any, data: any) => {
                if (data.node.original.keyword) {
                    this.data.action.redirects.push({ action_id: this.data.action.id, entity_id: '', keyword: data.node.id, redirect_mode: 'ENTITY' });
                } else {
                    this.data.action.redirects.push({ action_id: this.data.action.id, entity_id: data.node.id, keyword: '', redirect_mode: 'ENTITY' });
                }

            }).on('deselect_node.jstree', (e: any, data: any) => {
                this.data.action.redirects.forEach((redirect: any) => {
                    if (data.node.original.keyword) {
                        if (redirect.keyword === data.node.original.keyword) {
                            const index = this.data.action.redirects.indexOf(redirect);
                            this.data.action.redirects.splice(index, 1);
                        }
                    } else {
                        if (redirect.entity_id === data.node.id) {
                            const index = this.data.action.redirects.indexOf(redirect);
                            this.data.action.redirects.splice(index, 1);
                        }
                    }

                });
            })
            // create the instance
            .jstree();

        let to: any = false;
        $('#jstree_search').keyup(function () {
            if (to) {
                clearTimeout(to);
            }
            to = setTimeout(function () {
                const v: any = $('#jstree_search').val();
                $('#jstree').jstree(true).search(v);
            }, 250);
        });

    }

    initService2() {
        if (this.data.action.redirects.find((tag: any) => tag.keyword === 'AUTO_REDIRECT_TO_USER') !== undefined) {
            this.data.action.redirects.forEach((element: any, index: number) => {
                if (element.redirect_mode === 'USERS' && element.keyword !== 'AUTO_REDIRECT_TO_USER') {
                    this.data.action.redirects.splice(index, 1);
                }
            });
        }
        this.allEntities.forEach((entity: any) => {
            entity.state = { 'opened': false, 'selected': false };
            this.data.action.redirects.forEach((keyword: any) => {
                if ((entity.id === keyword.keyword && keyword.redirect_mode === 'USERS') || (entity.id === keyword.entity_id && keyword.redirect_mode === 'USERS')) {
                    entity.state = { 'opened': true, 'selected': true };
                }
            });
        });
        $('#jstree2').jstree({
            'checkbox': {
                'three_state': false // no cascade selection
            },
            'core': {
                force_text: true,
                'themes': {
                    'name': 'proton',
                    'responsive': true
                },
                'data': this.allEntities
            },
            'plugins': ['checkbox', 'search']
        });
        $('#jstree2')
            // listen for event
            .on('loaded.jstree', (e: any, data: any) => {
                if (this.data.action.redirects.find((tag: any) => tag.keyword === 'AUTO_REDIRECT_TO_USER') !== undefined) {
                    this.allEntities.forEach((element: any) => {
                        if (element.id !== 'AUTO_REDIRECT_TO_USER') {
                            $('#jstree2').jstree('disable_node', element.id);
                        }
                    });
                }
            })
            .on('select_node.jstree', (e: any, data: any) => {
                if (data.selected.indexOf('AUTO_REDIRECT_TO_USER') > -1) {
                    const index: number = data.selected.indexOf('AUTO_REDIRECT_TO_USER');
                    data.selected.splice(index, 1);
                    this.allEntities.forEach((element: any) => {
                        if (element.id !== 'AUTO_REDIRECT_TO_USER') {
                            $('#jstree2').jstree('disable_node', element.id);
                        }
                    });
                    $('#jstree2').jstree().deselect_node(data.selected);
                }
                if (data.node.original.keyword) {
                    this.data.action.redirects.push({ action_id: this.data.action.id, entity_id: '', keyword: data.node.id, redirect_mode: 'USERS' });
                } else {
                    this.data.action.redirects.push({ action_id: this.data.action.id, entity_id: data.node.id, keyword: '', redirect_mode: 'USERS' });
                }

            }).on('deselect_node.jstree', (e: any, data: any) => {
                if (data.node.id === 'AUTO_REDIRECT_TO_USER') {
                    this.allEntities.forEach((element: any) => {
                        $('#jstree2').jstree('enable_node', element.id);
                    });
                }
                this.data.action.redirects.forEach((redirect: any) => {
                    if (data.node.original.keyword) {
                        if (redirect.keyword === data.node.original.keyword) {
                            const index = this.data.action.redirects.indexOf(redirect);
                            this.data.action.redirects.splice(index, 1);
                        }
                    } else {
                        if (redirect.entity_id === data.node.id) {
                            const index = this.data.action.redirects.indexOf(redirect);
                            this.data.action.redirects.splice(index, 1);
                        }
                    }

                });
            })
            // create the instance
            .jstree();

        let to: any = false;
        $('#jstree_search2').keyup(function () {
            if (to) {
                clearTimeout(to);
            }
            to = setTimeout(function () {
                const v: any = $('#jstree_search2').val();
                $('#jstree2').jstree(true).search(v);
            }, 250);
        });
    }

    saveSettings() {
        this.dialogRef.close(this.data);
    }
}

@Component({
    templateUrl: 'basket-administration-groupList-modal.component.html',
    styles: ['.mat-dialog-content{height: 65vh;}']
})
export class BasketAdministrationGroupListModalComponent implements OnInit {

    coreUrl: string;

    actionAll: any = [];
    newBasketGroup: any = {};

    constructor(
        public http: HttpClient,
        private notify: NotificationService,
        @Inject(MAT_DIALOG_DATA) public data: any,
        public dialogRef: MatDialogRef<BasketAdministrationGroupListModalComponent>) {
    }

    ngOnInit(): void {
        this.http.get('../rest/actions')
            .subscribe((data: any) => {
                data.actions.forEach((tmpAction: any) => {
                    tmpAction.where_clause = '';
                    tmpAction.used_in_basketlist = false;
                    tmpAction.default_action_list = false;
                    tmpAction.used_in_action_page = true;
                    tmpAction.statuses = [];
                    tmpAction.redirects = [];
                    tmpAction.checked = false;
                    this.actionAll.push(tmpAction);
                });

            }, (err) => {
                this.notify.handleErrors(err);
            });

        this.data.groups.forEach((tmpGroup: any) => {
            this.data.linkedGroups.forEach((tmpLinkedGroup: any) => {
                if (tmpGroup.group_id === tmpLinkedGroup.group_id) {
                    const index = this.data.groups.indexOf(tmpGroup);
                    this.data.groups.splice(index, 1);
                }
            });
        });
    }

    validateForm(group: any) {
        if (this.data.linkedGroups.length === 0) {
            this.actionAll[0].used_in_action_page = true;
            this.actionAll[0].default_action_list = true;
            this.actionAll[0].used_in_basketlist = true;
            this.actionAll[0].checked = true;
            this.newBasketGroup.groupActions = this.actionAll;
        } else {
            this.newBasketGroup = JSON.parse(JSON.stringify(this.data.linkedGroups[this.data.linkedGroups.length - 1]));
        }
        this.newBasketGroup.basket_id = this.data.basketId;
        this.newBasketGroup.group_id = group.group_id;
        this.newBasketGroup.group_desc = group.group_desc;
        this.dialogRef.close(this.newBasketGroup);
    }
}
