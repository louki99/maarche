import { Component, OnInit, Input, EventEmitter, Output, ViewChild, OnDestroy } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { TranslateService } from '@ngx-translate/core';
import { MatSidenav } from '@angular/material/sidenav';
import { AppService } from '@service/app.service';
import { tap, catchError } from 'rxjs/operators';
import { MatExpansionPanel } from '@angular/material/expansion';
import { NotificationService } from '@service/notification/notification.service';
import { HeaderService } from '@service/header.service';
import { ActionsService } from '../actions/actions.service';
import { of, Subscription } from 'rxjs';

@Component({
    selector: 'app-basket-home',
    templateUrl: 'basket-home.component.html',
    styleUrls: ['basket-home.component.scss'],
})
export class BasketHomeComponent implements OnInit, OnDestroy {


    @Input() currentBasketInfo: any = {
        ownerId: 0,
        groupId: 0,
        basketId: ''
    };
    @Input() snavL: MatSidenav;
    @Output() refreshEvent = new EventEmitter<string>();

    @ViewChild('basketPanel', { static: true }) basketPanel: MatExpansionPanel;

    loading: boolean = true;
    hoverEditGroupOrder: boolean = false;

    homeData: any = null;

    editOrderGroups: boolean = false;
    subscription: Subscription;

    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        public appService: AppService,
        public headerService: HeaderService,
        private notify: NotificationService,
        public actionService: ActionsService,
    ) {

        this.subscription = this.actionService.catchAction().subscribe(message => {
            this.refreshBasketHome();
        });
    }

    ngOnInit(): void {
        this.getMyBaskets();
    }

    ngOnDestroy() {
        // unsubscribe to ensure no memory leaks
        this.subscription.unsubscribe();
    }

    getMyBaskets() {
        this.loading = true;

        this.http.get('../rest/home').pipe(
            tap((data: any) => {
                this.homeData = data;
                this.loading = false;
            })
        ).subscribe();
    }

    closePanelLeft() {
        if (this.appService.getViewMode()) {
            this.snavL.close();
        }
    }

    refreshDatas(basket: any) {
        this.refreshBasketHome();

        // AVOID DOUBLE REQUEST IF ANOTHER BASKET IS SELECTED
        if (this.headerService.headerMessage === basket.basket_name) {
            /**
             * TO refresh basket list
             */
            this.actionService.emitAction();
        }
    }

    refreshBasketHome() {
        this.http.get('../rest/home')
            .subscribe((data: any) => {
                this.homeData = data;
            });
    }

    togglePanel(state: boolean) {
        if (state) {
            this.basketPanel.open();
        } else {
            this.basketPanel.close();
        }
    }

    editGroupOrder() {
        this.editOrderGroups = !this.editOrderGroups;
    }

    updateGroupsOrder() {
        const groupsOrder = this.homeData.regroupedBaskets.map((element: any) => element.groupSerialId);
        this.http.put('../rest/currentUser/profile/preferences', { homeGroups: groupsOrder }).pipe(
            tap(() => this.notify.success(this.translate.instant('lang.parameterUpdated'))),
            catchError((err) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }
}
