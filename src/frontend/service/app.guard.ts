
import { Injectable } from '@angular/core';
import { ActivatedRouteSnapshot, CanActivate, Router, RouterStateSnapshot, CanDeactivate } from '@angular/router';
import { HttpClient } from '@angular/common/http';
import { Observable, of } from 'rxjs';
import { map, catchError, exhaustMap, filter, tap } from 'rxjs/operators';
import { HeaderService } from './header.service';
import { ProcessComponent } from '../app/process/process.component';
import { AuthService } from './auth.service';
import { TranslateService } from '@ngx-translate/core';
import { NotificationService } from './notification/notification.service';
import { MatDialog } from '@angular/material/dialog';
import { ConfirmComponent } from '@plugins/modal/confirm.component';
import { ActionsService } from '@appRoot/actions/actions.service';

@Injectable({
    providedIn: 'root'
})
export class AppGuard implements CanActivate {

    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        private router: Router,
        private authService: AuthService,
        public headerService: HeaderService
    ) { }

    canActivate(route: ActivatedRouteSnapshot, state: RouterStateSnapshot): Observable<any> {
        const urlArr = state.url.replace(/^\/+|\/+$/g, '').split('/');

        console.debug('== ROUTE GUARD ==');
        console.debug(state.url);

        this.headerService.resetSideNavSelection();

        return this.authService.getLoginInformations(state.url)
            .pipe(
                map((data: any) => {
                    if (!data) {
                        return false;
                    } else if (this.authService.getToken() === null) {
                        this.authService.setCachedUrl(state.url.replace(/^\//g, ''));
                        console.debug('Aucun token trouvé ! Redirection sur login ...');
                        this.authService.logout(false, true);
                        return false;
                    }
                }),
                filter(() => this.authService.getToken() !== null),
                exhaustMap(() => this.authService.getCurrentUserInfo()),
                map((data: any) => {
                    if (this.headerService.user.status === 'ABS') {
                        this.router.navigate(['/activate-user']);
                        return false;
                    } else {
                        if (urlArr.filter((url: any) => ['signatureBook', 'content'].indexOf(url) > -1).length > 0) {
                            this.headerService.hideSideBar = true;
                        } else {
                            this.headerService.hideSideBar = false;
                        }
                        if (urlArr.filter((url: any) => url === 'administration').length > 0 || urlArr.filter((url: any) => url === 'profile').length > 0) {
                            this.headerService.sideBarAdmin = true;
                        } else {
                            this.headerService.sideBarAdmin = false;
                        }
                        return true;
                    }

                }),
                catchError((err: any) => {
                    console.log(err);
                    if (err.error.errors === 'User must change his password') {
                        return this.router.navigate(['/password-modification']);
                    } else {
                        return of(false);
                    }
                })
            );
    }
}

@Injectable({
    providedIn: 'root'
})
export class AfterProcessGuard implements CanDeactivate<ProcessComponent> {
    constructor(
        public translate: TranslateService,
        private notify: NotificationService,
        public dialog: MatDialog,
        public authService: AuthService,
        public actionService: ActionsService,
        public router: Router
    ) {}

    async canDeactivate(component: ProcessComponent, currentRoute: ActivatedRouteSnapshot, currentState: RouterStateSnapshot, nextState: RouterStateSnapshot): Promise<boolean> {
        /* if (nextState.url !== '/login' && !component.isActionEnded() && !component.detailMode) {
            component.actionService.unlockResource(component.currentUserId, component.currentGroupId, component.currentBasketId, [component.currentResourceInformations.resId]);
        }*/
        if ((component.isToolModified() && !component.isModalOpen()) || (component.appDocumentViewer !== undefined && component.appDocumentViewer.isEditingTemplate())) {
            const value = await this.getConfirmation();
            if (value) {
                await component.saveModificationBeforeClose();
            }
            if (nextState.url === '/login') {
                component.logoutTrigger = true;
                this.authService.setEvent('login');
                await component.unlockResource();
                this.authService.redirectAfterLogout(true);
            }
            return true;

        } else {
            if (nextState.url === '/login') {
                component.logoutTrigger = true;
                await component.unlockResource();
                this.authService.redirectAfterLogout(true);
            }
            return true;
        }
    }

    getConfirmation() {
        return new Promise((resolve) => {
            const dialogRef = this.dialog.open(ConfirmComponent, { panelClass: 'maarch-modal', autoFocus: false, disableClose: true, data: { title: this.translate.instant('lang.confirm'), msg: this.translate.instant('lang.saveModifiedData'), buttonValidate: this.translate.instant('lang.yes'), buttonCancel: this.translate.instant('lang.no') } });
            dialogRef.afterClosed().pipe(
                tap((data: string) => {
                    resolve(data);
                }),
                catchError((err: any) => {
                    this.notify.handleErrors(err);
                    return of(false);
                })
            ).subscribe();
        });
    }
}
