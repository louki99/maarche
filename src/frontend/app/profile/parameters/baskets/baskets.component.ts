import { Component, EventEmitter, Input, OnInit, Output } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { NotificationService } from '@service/notification/notification.service';
import { TranslateService } from '@ngx-translate/core';
import { FunctionsService } from '@service/functions.service';
import { HeaderService } from '@service/header.service';
import { SelectionModel } from '@angular/cdk/collections';
import { ConfirmComponent } from '@plugins/modal/confirm.component';
import { MatDialog } from '@angular/material/dialog';
import { catchError, exhaustMap, filter, tap } from 'rxjs/operators';
import { of } from 'rxjs';
import { AuthService } from '@service/auth.service';

@Component({
    selector: 'app-my-baskets',
    templateUrl: './baskets.component.html',
    styleUrls: ['./baskets.component.scss'],
})

export class MyBasketsComponent implements OnInit {

    @Input() userBaskets: any[];
    @Input() redirectedBaskets: any[];
    @Input() assignedBaskets: any[];

    @Output() redirectedBasketsEvent = new EventEmitter<any>();

    selectionBaskets = new SelectionModel<Element>(true, []);

    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        private notify: NotificationService,
        public functionsService: FunctionsService,
        public headerService: HeaderService,
        public dialog: MatDialog,

    ) {}

    ngOnInit(): void {}

    masterToggleBaskets(event: any) {
        if (event.checked) {
            this.userBaskets.forEach((basket: any) => {
                if (!basket.userToDisplay) {
                    this.selectionBaskets.select(basket);
                }
            });
        } else {
            this.selectionBaskets.clear();
        }
    }

    addBasketRedirection(newUser: any) {
        const basketsRedirect: any[] = [];
        this.selectionBaskets.selected.forEach((elem: any) => {
            basketsRedirect.push(
                {
                    actual_user_id: newUser.serialId,
                    basket_id: elem.basket_id,
                    group_id: elem.groupSerialId,
                    originalOwner: null
                }
            );
        });

        const dialogRef = this.dialog.open(ConfirmComponent, { panelClass: 'maarch-modal', autoFocus: false, disableClose: true, data: { title: `${this.translate.instant('lang.redirectBasket')}`, msg: this.translate.instant('lang.confirmAction') } });
        dialogRef.afterClosed().pipe(
            filter((data: string) => data === 'ok'),
            exhaustMap(() => this.http.post('../rest/users/' + this.headerService.user.id + '/redirectedBaskets', basketsRedirect)),
            tap((data: any) => {
                this.userBaskets = data['baskets'].filter((basketItem: any) => !basketItem.basketSearch);
                this.redirectedBaskets = data['redirectedBaskets'];
                const objToSend: any = {
                    event: 'add',
                    redirectedBaskets: this.redirectedBaskets
                };
                this.redirectedBasketsEvent.emit(objToSend);
                this.selectionBaskets.clear();
                this.notify.success(this.translate.instant('lang.basketUpdated'));
            }),
            catchError((err: any) => {
                this.notify.handleSoftErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    reassignBasketRedirection(newUser: any, basket: any, i: number) {
        const dialogRef = this.dialog.open(ConfirmComponent, { panelClass: 'maarch-modal', autoFocus: false, disableClose: true, data: { title: `${this.translate.instant('lang.reassign')}`, msg: this.translate.instant('lang.confirmAction') } });
        dialogRef.afterClosed().pipe(
            filter((data: string) => data === 'ok'),
            exhaustMap(() => this.http.post('../rest/users/' + this.headerService.user.id + '/redirectedBaskets', [
                {
                    'actual_user_id': newUser.serialId,
                    'basket_id': basket.basket_id,
                    'group_id': basket.group_id,
                    'originalOwner': basket.owner_user_id,
                }
            ])),
            tap((data: any) => {
                this.userBaskets = data['baskets'].filter((basketItem: any) => !basketItem.basketSearch);
                this.assignedBaskets.splice(i, 1);
                this.notify.success(this.translate.instant('lang.basketUpdated'));
            }),
            catchError((err: any) => {
                this.notify.handleSoftErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    delBasketAssignRedirection(basket: any, i: number) {
        const dialogRef = this.dialog.open(ConfirmComponent, { panelClass: 'maarch-modal', autoFocus: false, disableClose: true, data: { title: `${this.translate.instant('lang.deleteAssignation')}`, msg: this.translate.instant('lang.confirmAction') } });
        dialogRef.afterClosed().pipe(
            filter((data: string) => data === 'ok'),
            exhaustMap(() => this.http.delete('../rest/users/' + this.headerService.user.id + '/redirectedBaskets?redirectedBasketIds[]=' + basket.id)),
            tap((data: any) => {
                this.headerService.user.baskets = data['baskets'].filter((basketItem: any) => !basketItem.basketSearch);
                this.assignedBaskets.splice(i, 1);
                this.notify.success(this.translate.instant('lang.basketUpdated'));
            }),
            catchError((err: any) => {
                this.notify.handleSoftErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    delBasketRedirection(basket: any, i: number) {
        const dialogRef = this.dialog.open(ConfirmComponent, { panelClass: 'maarch-modal', autoFocus: false, disableClose: true, data: { title: `${this.translate.instant('lang.deleteRedirection')}`, msg: this.translate.instant('lang.confirmAction') } });
        dialogRef.afterClosed().pipe(
            filter((data: string) => data === 'ok'),
            exhaustMap(() => this.http.delete('../rest/users/' + this.headerService.user.id + '/redirectedBaskets?redirectedBasketIds[]=' + basket.id)),
            tap((data: any) => {
                this.userBaskets = data['baskets'].filter((basketItem: any) => !basketItem.basketSearch);
                this.redirectedBaskets.splice(i, 1);
                const objToSend: any = {
                    event: 'del',
                    baskeToDel: [basket],
                    redirectedBaskets: this.redirectedBaskets
                };
                this.redirectedBasketsEvent.emit(objToSend);
                this.notify.success(this.translate.instant('lang.basketUpdated'));
            }),
            catchError((err: any) => {
                this.notify.handleSoftErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    showActions(basket: any) {
        $('#' + basket.basket_id + '_' + basket.group_id).show();
    }

    hideActions(basket: any) {
        $('#' + basket.basket_id + '_' + basket.group_id).hide();
    }
}
