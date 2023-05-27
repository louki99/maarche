import { Component, OnInit } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { TranslateService } from '@ngx-translate/core';
import { NotificationService } from '@service/notification/notification.service';
import { Router } from '@angular/router';
import { SelectionModel } from '@angular/cdk/collections';
import { AppService } from '@service/app.service';
import { HeaderService } from '@service/header.service';
import { AuthService } from '@service/auth.service';

declare let $: any;

@Component({
    templateUrl: 'activate-user.component.html',
})

export class ActivateUserComponent implements OnInit {

    user: any = {
        baskets: []
    };

    userAbsenceModel: any[] = [];
    basketsToRedirect: string[] = [];

    loading: boolean = false;
    selectedIndex: number = 0;

    // Redirect Baskets
    selectionBaskets = new SelectionModel<Element>(true, []);
    myBasketExpansionPanel: boolean = false;

    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        private authService: AuthService,
        private headerService: HeaderService,
        private notify: NotificationService,
        private router: Router,
        public appService: AppService
    ) { }

    ngOnInit(): void {
        this.loading = true;
        if (this.headerService.user.status === 'ABS') {
            this.http.get('../rest/currentUser/profile')
                .subscribe((data: any) => {
                    this.user = data;

                    this.user.baskets.forEach((value: any, index: number) => {
                        this.user.baskets[index]['disabled'] = false;
                        this.user.redirectedBaskets.forEach((redirectedBasket: any) => {
                            if (value.basket_id == redirectedBasket.basket_id && value.basket_owner == redirectedBasket.basket_owner) {
                                this.user.baskets[index]['disabled'] = true;
                            }
                        });
                    });
                    this.loading = false;
                });
        } else {
            this.router.navigate(['/home']);
        }
    }

    masterToggleBaskets(event: any) {
        if (event.checked) {
            this.user.redirectedBaskets.forEach((basket: any) => {
                this.selectionBaskets.select(basket);
            });
        } else {
            this.selectionBaskets.clear();
        }
    }

    showActions(basket: any) {
        $('#' + basket.basket_id + '_' + basket.group_id).show();
    }

    hideActions(basket: any) {
        $('#' + basket.basket_id + '_' + basket.group_id).hide();
    }

    // action on user
    activateUser(): void {

        this.http.put('../rest/users/' + this.headerService.user.id + '/status', { 'status': 'OK' })
            .subscribe(() => {
                this.headerService.user.status = 'OK';
                let basketsRedirectedIds: any = '';

                this.user.redirectedBaskets.forEach((elem: any) => {
                    if (this.selectionBaskets.selected.map((e: any) => e.basket_id).indexOf(elem.basket_id) !== -1
                        && this.selectionBaskets.selected.map((e: any) => e.group_id).indexOf(elem.group_id) !== -1) {
                        if (basketsRedirectedIds !== '') {
                            basketsRedirectedIds = basketsRedirectedIds + '&redirectedBasketIds[]=';
                        }
                        basketsRedirectedIds = basketsRedirectedIds + elem.id;
                    }
                });

                if (basketsRedirectedIds !== '') {
                    this.http.delete('../rest/users/' + this.headerService.user.id + '/redirectedBaskets?redirectedBasketIds[]=' + basketsRedirectedIds)
                        .subscribe((data: any) => {
                            this.router.navigate(['/home']);
                            this.notify.success(this.translate.instant('lang.absOff'));
                        }, (err) => {
                            this.notify.error(err.error.errors);
                        });
                } else {
                    this.router.navigate(['/home']);
                    this.notify.success(this.translate.instant('lang.absOff'));
                }


            }, (err: any) => {
                this.notify.error(err.error.errors);
            });

    }

    logout() {
        this.authService.logout();
    }
}
