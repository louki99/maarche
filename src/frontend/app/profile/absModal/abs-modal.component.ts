import { Component, OnInit, Inject, ViewChild } from '@angular/core';
import { TranslateService } from '@ngx-translate/core';
import { NotificationService } from '@service/notification/notification.service';
import { MAT_DIALOG_DATA, MatDialogRef } from '@angular/material/dialog';
import { HttpClient } from '@angular/common/http';
import { NoteEditorComponent } from '../../notes/note-editor.component';
import { tap, exhaustMap, catchError, finalize } from 'rxjs/operators';
import { of } from 'rxjs';
import { HeaderService } from '@service/header.service';
import { AuthService } from '@service/auth.service';
import { FunctionsService } from '@service/functions.service';

@Component({
    templateUrl: 'abs-modal.component.html',
    styleUrls: ['abs-modal.component.scss'],
})
export class AbsModalComponent implements OnInit {

    @ViewChild('noteEditor', { static: true }) noteEditor: NoteEditorComponent;

    loading: boolean = false;

    isAbsScheduled: boolean = false;
    canceled: boolean = false;
    valueChanged: boolean = false;

    userId: number = 0;
    baskets: any[] = [];
    basketsClone: any[] = [];

    today: Date = new Date();
    startDate: Date = null;
    endDate: Date = null;

    redirectedBaskets: any[] = [];
    showCalendar: boolean = false;

    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        private notify: NotificationService,
        public headerService: HeaderService,
        public dialogRef: MatDialogRef<AbsModalComponent>,
        private authService: AuthService,
        public functions: FunctionsService,
        @Inject(MAT_DIALOG_DATA) public data: any
    ) { }

    async ngOnInit(): Promise<void> {
        await this.getBasketInfo();
        await this.getAbsenceInfo();
    }

    getBasketInfo() {
        return new Promise(async (resolve, reject) => {
            let objBasket = {};
            this.data.user.baskets.filter((basket: any) => !basket.basketSearch).forEach((basket: any) => {
                objBasket = { ...basket };

                const redirBasket = this.data.user.redirectedBaskets.find((redBask: any) => redBask.basket_id === basket.basket_id && redBask.group_id === basket.groupSerialId);
                if (redirBasket !== undefined) {
                    objBasket['actual_user_id'] = redirBasket.actual_user_id;
                }
                this.baskets.push(objBasket);
            });
            this.basketsClone = JSON.parse(JSON.stringify(this.baskets));
            resolve(true);
        });
    }

    async onSubmit() {
        this.loading = true;
        const today = this.functions.formatDateObjectToDateString(new Date());
        const startDate = this.functions.formatDateObjectToDateString(this.startDate);
        if (this.startDate && startDate !== today) {
            await this.activateAbsence();
        } else {
            const res = await this.redirectBaskets();
            if (res) {
                await this.activateAbsence();
            }
        }
        this.loading = false;
        this.dialogRef.close();
    }

    isRedirectedBasket(basket: any) {
        if (!this.isAbsScheduled) {
            return basket.userToDisplay !== null;
        } else {
            const checkAfterSchedule: any[] = this.redirectedBaskets.filter((item: any) => item.basket_id === basket.basket_id);
            const checkInCurrentRedirection: any[] = this.data.user.redirectedBaskets.filter((item: any) => item.basket_id === basket.basket_id);
            return basket.userToDisplay !== null && (checkAfterSchedule.length === 1 || checkInCurrentRedirection.length === 0);
        }
    }

    addBasketRedirection(newUser: any) {
        this.baskets.forEach((basket: any, index: number) => {
            if (basket.selected) {
                this.baskets[index] = {
                    ...basket,
                    actual_user_id: newUser.serialId,
                    userToDisplay: newUser.idToDisplay,
                    selected: false
                };
            }
        });
    }

    delBasketRedirection(basket: any) {
        basket.actual_user_id = null;
        basket.userToDisplay = null;
    }

    redirectBaskets() {
        return new Promise(async (resolve, reject) => {
            const res = await this.clearRedirections();
            if (res) {
                const basketsRedirect: any[] = [];

                this.baskets.filter((item: any) => item.userToDisplay !== null).forEach((elem: any) => {
                    if (!this.isInitialRedirection(elem)) {
                        basketsRedirect.push(
                            {
                                actual_user_id: elem.actual_user_id,
                                basket_id: elem.basket_id,
                                group_id: elem.groupSerialId,
                                originalOwner: null
                            }
                        );
                    }
                });
                if (basketsRedirect.length > 0) {
                    this.http.post('../rest/users/' + this.data.user.id + '/redirectedBaskets', basketsRedirect).pipe(
                        tap((data: any) => {
                            resolve(true);
                        }),
                        catchError((err: any) => {
                            this.notify.handleErrors(err);
                            resolve(false);
                            return of(false);
                        })
                    ).subscribe();
                } else {
                    resolve(true);
                }
            } else {
                resolve(false);
            }
        });
    }

    isInitialRedirection(basket: any) {
        return this.data.user.redirectedBaskets.find((redBasket: any) => basket.basket_id === redBasket.basket_id && basket.groupSerialId === redBasket.group_id && basket.actual_user_id === redBasket.actual_user_id);
    }

    clearRedirections() {
        return new Promise(async (resolve, reject) => {
            const redirectedBasketIds: number[] = [];
            this.data.user.redirectedBaskets.forEach((redBasket: any) => {
                if (this.baskets.find((basket: any) => basket.basket_id === redBasket.basket_id && basket.groupSerialId === redBasket.group_id && basket.actual_user_id !== redBasket.actual_user_id) !== undefined) {
                    redirectedBasketIds.push(redBasket.id);
                }
            });
            if (redirectedBasketIds.length > 0) {
                const res = await this.delBasketAssignRedirection(redirectedBasketIds);
                resolve(res);
            } else {
                resolve(true);
            }
        });
    }

    delBasketAssignRedirection(redirectedBasketIds: number[]) {
        const queryParam = '?redirectedBasketIds[]=' + redirectedBasketIds.join('&redirectedBasketIds[]=');

        return new Promise((resolve, reject) => {
            this.http.delete('../rest/users/' + this.data.user.id + '/redirectedBaskets' + queryParam).pipe(
                tap((data: any) => {
                    resolve(true);
                }),
                catchError((err: any) => {
                    this.notify.handleErrors(err);
                    resolve(false);
                    return of(false);
                })
            ).subscribe();
        });
    }

    basketSelected() {
        return this.baskets.filter((item: any) => item.selected).length > 0;
    }

    activateAbsence() {
        const redirectedBaskets: any[] = [];
        let absenceDate: any =  {
            startDate: null,
            endDate: null
        };
        if (!this.isAbsScheduled) {
            this.baskets.filter((item: any) => item.userToDisplay !== null).forEach((elem: any) => {
                redirectedBaskets.push(
                    {
                        actual_user_id: elem.actual_user_id,
                        basket_id: elem.basket_id,
                        group_id: elem.groupSerialId,
                        userToDisplay: elem.userToDisplay,
                        originalOwner: null
                    }
                );
            });
            absenceDate = {
                startDate: this.functions.formatDateObjectToDateString(this.startDate),
                endDate: this.functions.formatDateObjectToDateString(this.endDate, true)
            };
        }
        return new Promise((resolve, reject) => {
            if ((this.startDate && (redirectedBaskets.length > 0 || redirectedBaskets.length === 0)) || this.canceled) {
                this.http.put('../rest/users/' + this.data.user.id + '/absence', { absenceDate, redirectedBaskets }).pipe(
                    tap(() => {
                        const today = this.functions.formatDateObjectToDateString(new Date());
                        const startDate = this.functions.formatDateObjectToDateString(this.startDate);
                        if (this.isAbsScheduled) {
                            if ((!this.startDate || (today === startDate)) && !this.canceled) {
                                this.authService.logout();
                            } else {
                                this.notify.success(this.translate.instant('lang.absOff'));
                                this.isAbsScheduled = false;
                                this.startDate = null;
                                this.endDate = null;
                                this.headerService.user.status = 'OK';
                                this.authService.setEvent('absOff');
                            }
                        } else {
                            if (startDate === today || !this.startDate) {
                                this.authService.logout();
                            } else {
                                this.notify.success(this.translate.instant('lang.absenceDateSaved'));
                                this.authService.setEvent('isAbsScheduled');
                            }
                        }
                        resolve(true);
                    }),
                    catchError((err: any) => {
                        this.notify.handleErrors(err);
                        resolve(false);
                        return of(false);
                    })
                ).subscribe();
            } else if (!this.startDate && !this.endDate && !this.canceled) {
                this.http.put('../rest/users/' + this.data.user.id + '/status', { 'status': 'ABS' }).pipe(
                    tap(() => {
                        this.authService.logout();
                        resolve(true);
                    }),
                    catchError((err: any) => {
                        this.notify.handleErrors(err);
                        resolve(false);
                        return of(false);
                    })
                ).subscribe();
            }
        });
    }

    toggleAll() {
        if (this.allSelected()) {
            this.baskets.forEach(element => {
                element.selected = false;
            });
        } else {
            this.baskets.forEach(element => {
                if (!this.isRedirectedBasket(element)) {
                    element.selected = true;
                }
            });
        }
    }

    allSelected() {
        const basketsNotSelected: number = this.baskets.filter((basket: any) => !this.isRedirectedBasket(basket)).length;
        const hasRedirectedBasket: boolean = this.baskets.filter((basket: any) => this.isRedirectedBasket(basket)).length >= 1;
        if (hasRedirectedBasket) {
            return hasRedirectedBasket && basketsNotSelected === this.baskets.filter((item: any) => item.selected).length;
        } else {
            return this.baskets.filter((item: any) => item.selected).length === this.baskets.filter((item: any) => !this.isRedirectedBasket(item)).length;
        }
    }

    oneOrMoreSelected() {
        return this.baskets.filter((item: any) => item.selected).length > 0 && !this.allSelected();
    }

    getAbsenceInfo() {
        return new Promise((resolve) => {
            this.http.get('../rest/currentUser/profile').pipe(
                tap((data: any) => {
                    if (data.absence) {
                        this.isAbsScheduled = true;
                        const absenceDate: any = data.absence.absenceDate;
                        data.absence.redirectedBaskets.forEach((basket: any) => {
                            this.baskets.find((basketItem: any) => basketItem.basket_id === basket.basket_id && basketItem.groupSerialId === basket.group_id).selected = true;
                            const user = {
                                serialId: basket.actual_user_id,
                                idToDisplay: basket.userToDisplay,
                            };
                            this.addBasketRedirection(user);
                        });
                        this.startDate = new Date(this.functions.formatFrenchDateToTechnicalDate(absenceDate.startDate));
                        this.endDate = new Date(this.functions.formatFrenchDateToTechnicalDate(absenceDate.endDate));
                        this.basketsClone = JSON.parse(JSON.stringify(this.baskets));
                        this.redirectedBaskets = data.absence.redirectedBaskets;
                    }
                    resolve(true);
                }),
                catchError((err) => {
                    this.notify.handleSoftErrors(err);
                    return of(false);
                })
            ).subscribe();
        });
    }

    async cancelSchedule() {
        this.redirectedBaskets = [];
        this.onSubmit();
    }

    checkIfExist(basket: any, field: string) {
        if (field === 'user') {
            return this.redirectedBaskets.filter((item: any) => item.basketId === basket.basket_id && item.userToDisplay !== null).map((el) => el.userToDisplay).toString();
        } else if (field === 'id') {
            return this.redirectedBaskets.find((item: any) => item.basketId === basket.basket_id);
        }
    }

    isModified() {
        const baskets =  this.baskets.map((basket: any) => ({
            ...basket,
            selected : false,
        }));
        const basketsClone =  this.basketsClone.map((basket: any) => ({
            ...basket,
            selected : false,
        }));
        return !(JSON.stringify(baskets) === JSON.stringify(basketsClone)) || this.valueChanged;
    }

    changeValues(event: any) {
        event.stopPropagation();
        this.startDate = this.endDate = null;
        this.showCalendar = false;
        this.valueChanged = true;
    }
}
