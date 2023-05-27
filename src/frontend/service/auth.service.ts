import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Router } from '@angular/router';
import { LocalStorageService } from './local-storage.service';
import { NotificationService } from './notification/notification.service';
import { HeaderService } from './header.service';
import { Observable, of, Subject, Subscription, timer } from 'rxjs';
import { catchError, finalize, map, switchMap, tap } from 'rxjs/operators';
import { PrivilegeService } from './privileges.service';
import { AlertComponent } from '@plugins/modal/alert.component';
import { FunctionsService } from './functions.service';
import { MatDialog } from '@angular/material/dialog';
import { TranslateService } from '@ngx-translate/core';
import { AdministrationService } from '@appRoot/administration/administration.service';

@Injectable({
    providedIn: 'root'
})
export class AuthService {

    applicationName: string = '';
    loginMessage: string = '';
    authMode: string = 'standard';
    authUri: string = '';
    mailServerOnline = false;
    changeKey: boolean = null;
    user: any = {};
    maarchUrl: string = '';
    noInstall: boolean = false;
    externalSignatoryBook: any = null;
    idleTime: number; // Inactivity time

    public userActivitySubscription: Subscription;
    public inactivitySubscription: Subscription;
    private warningTime: number; // warning time before disconnection in milliseconds
    private inactivityTime: number; // inactivity time in milliseconds

    private eventAction = new Subject<any>();

    constructor(public http: HttpClient,
        private router: Router,
        private headerService: HeaderService,
        private notify: NotificationService,
        private localStorage: LocalStorageService,
        private privilegeService: PrivilegeService,
        private functionsService: FunctionsService,
        public dialog: MatDialog,
        public translate: TranslateService,
        public adminService: AdministrationService,
    ) { }

    catchEvent(): Observable<any> {
        return this.eventAction.asObservable();
    }

    setEvent(content: any) {
        return this.eventAction.next(content);
    }

    getToken() {
        return this.localStorage.get('MaarchCourrierToken');
    }

    getAppSession() {
        return this.localStorage.getAppSession();
    }

    setAppSession(id: string) {
        this.localStorage.setAppSession(id);
    }

    setCachedUrl(url: string) {
        this.localStorage.save('MaarchCourrierCachedUrl', url);
    }

    getCachedUrl() {
        return this.localStorage.get('MaarchCourrierCachedUrl');
    }

    cleanCachedUrl() {
        return this.localStorage.remove('MaarchCourrierCachedUrl');
    }

    getUrl(id: number) {
        return this.localStorage.get(`MaarchCourrierUrl_${id}`);
    }

    setUrl(url: string) {
        const arrUrl = url.split('/');

        if (arrUrl.indexOf('resources') === -1 && arrUrl.indexOf('content') === -1) {
            this.localStorage.save(`MaarchCourrierUrl_${JSON.parse(atob(this.getToken().split('.')[1])).user.id}`, url);
        }
    }

    cleanUrl(id: number) {
        return this.localStorage.remove(`MaarchCourrierUrl_${id}`);
    }

    setToken(token: string) {
        this.localStorage.save('MaarchCourrierToken', token);
    }

    getRefreshToken() {
        return this.localStorage.get('MaarchCourrierRefreshToken');
    }

    setRefreshToken(refreshToken: string) {
        this.localStorage.save('MaarchCourrierRefreshToken', refreshToken);
    }

    clearTokens() {
        this.localStorage.remove('MaarchCourrierToken');
        this.localStorage.remove('MaarchCourrierRefreshToken');
    }

    refreshToken() {
        return this.http
            .get<any>('../rest/authenticate/token', { params: { refreshToken: this.getRefreshToken() } })
            .pipe(
                tap((data) => {
                    // Update stored token
                    this.setToken(data.token);

                    // Update user info
                    this.updateUserInfo(data.token);
                }),
                catchError((error) => {
                    this.logout(false, true);
                    this.notify.error(this.translate.instant('lang.sessionExpired'));
                    return of(false);
                })
            );
    }

    async logout(cleanUrl: boolean = true, forcePageLogin: boolean = false, history: boolean = false) {
        this.clearFilters();
        if (['cas', 'keycloak'].indexOf(this.authMode) > -1 && !forcePageLogin) {
            this.SsoLogout(cleanUrl);
        } else {
            if (history) {
                this.http.get('../rest/authenticate/logout').subscribe();
            }
            // HANDLE LOGOUT IN GUARD FOR PROCESS
            if (['process'].indexOf(this.router.url.split('/')[1]) > -1) {
                this.router.navigate(['/login']);
            } else {
                await this.router.navigate(['/login']);
                this.redirectAfterLogout(cleanUrl);
            }
        }
        if (this.canLogOut()) {
            this.userActivitySubscription?.unsubscribe();
            this.inactivitySubscription?.unsubscribe();
        }
        this.dialog.closeAll();
        return new Observable<void>();
    }

    SsoLogout(cleanUrl: boolean = true) {
        this.http.get('../rest/authenticate/logout').pipe(
            tap(async (data: any) => {
                this.redirectAfterLogout(cleanUrl);
                window.location.href = data.logoutUrl;
            })
        ).subscribe();
    }

    redirectAfterLogout(cleanUrl: boolean = true) {
        if (this.getToken() !== null && cleanUrl) {
            this.cleanUrl(JSON.parse(atob(this.getToken().split('.')[1])).user.id);
        }
        this.headerService.setUser();
        this.clearTokens();
    }

    saveTokens(token: string, refreshToken: string) {
        this.setToken(token);
        this.setRefreshToken(refreshToken);
    }

    isAuth(): boolean {
        return this.headerService.user.id !== undefined;
    }

    updateUserInfo(token: string) {
        const currentPicture = this.user.picture;

        this.user = JSON.parse(atob(token.split('.')[1])).user;

        this.user.picture = currentPicture;
    }

    updateUserInfoWithTokenRefresh() {
        this.http.get('../rest/authenticate/token', {
            params: {
                refreshToken: this.getRefreshToken()
            }
        }).subscribe({
            next: (data: any) => {
                this.setToken(data.token);

                this.updateUserInfo(this.getToken());
            },
            error: err => {
                this.notify.handleSoftErrors(err);
            }
        });
    }

    setUser(value: any) {
        this.user = value;
    }

    applyMinorUpdate() {
        console.debug('applyMinorUpdate');
        const loader = '<div id="updateLoading" style="position:absolute;left:50%;top:50%;transform:translate(-50%,-50%);width: 200px;text-align: center;"><img src="assets/spinner.gif"></div>';
        $('body').append(loader);
        this.http.put('../rest/versionsUpdateSQL', {}).pipe(
            finalize(() => $('#updateLoading').remove()),
            catchError((err: any) => {
                this.notify.handleSoftErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    checkAppSecurity() {
        console.debug('checkAppSecurity');
        if (this.changeKey) {
            setTimeout(() => {
                this.dialog.open(AlertComponent, {
                    panelClass: 'maarch-modal',
                    autoFocus: false,
                    disableClose: true,
                    data: {
                        mode: 'danger',
                        title: this.translate.instant('lang.warnPrivateKeyTitle'),
                        msg: this.translate.instant('lang.warnPrivateKey')
                    }
                });
            }, 1000);
        }
    }

    getLoginInformations(currentRoute: string): Observable<any> {
        if (this.noInstall) {
            if (currentRoute === '/install') {
                return of(true);
            } else {
                this.router.navigate(['/install']);
                return of(false);
            }
        } else if (this.getAppSession() !== null) {
            return of(true);
        } else {
            return this.http
                .get('../rest/authenticationInformations')
                .pipe(
                    tap((data: any) => {
                        console.debug('getLoginInformations');
                        this.setAppSession(data.instanceId);

                        this.localStorage.save('lang', data.lang);
                        this.translate.use(data.lang);
                        this.mailServerOnline = data.mailServerOnline;
                        this.changeKey = data.changeKey;
                        this.applicationName = data.applicationName;
                        this.loginMessage = data.loginMessage;
                        this.externalSignatoryBook = data.externalSignatoryBook;
                        this.setEvent('authenticationInformations');
                        this.authMode = data.authMode;
                        this.authUri = data.authUri;
                        this.maarchUrl = data.maarchUrl;
                        this.idleTime = data.idleTime;

                        if (this.authMode === 'keycloak') {
                            const keycloakState = this.localStorage.get('keycloakState');
                            if (keycloakState === null || keycloakState === 'null') {
                                this.localStorage.save('keycloakState', data.keycloakState);
                            }
                        }
                        this.applyMinorUpdate();
                        this.checkAppSecurity();
                    }),
                    catchError((err: any) => {
                        console.log(err);
                        return this.http.get('../rest/validUrl').pipe(
                            map((data: any) => {
                                if (!this.functionsService.empty(data.url)) {
                                    window.location.href = data.url;
                                    return false;
                                } else if (data.lang === 'moreOneCustom') {
                                    this.dialog.open(AlertComponent, { panelClass: 'maarch-modal', autoFocus: false, disableClose: true, data: { title: this.translate.instant('lang.accessNotFound'), msg: this.translate.instant('lang.moreOneCustom'), hideButton: true } });
                                    return false;
                                } else if (data.lang === 'noConfiguration') {
                                    this.noInstall = true;
                                    if (currentRoute === '/install') {
                                        return true;
                                    } else {
                                        console.log(this.router.url, 'navigate to install');
                                        this.router.navigate(['/install']);
                                        return false;
                                    }
                                } else {
                                    // this.notify.handleSoftErrors(err);
                                }
                            })
                        );
                    })
                );
        }
    }

    getCurrentUserInfo(): Observable<any> {
        if (this.isAuth()) {
            return of(true);
        } else {
            return this.http
                .get('../rest/currentUser/profile')
                .pipe(
                    tap((data: any) => {
                        console.debug('getCurrentUserInfo');
                        this.headerService.user = {
                            mode: data.mode,
                            id: data.id,
                            status: data.status,
                            userId: data.user_id,
                            mail: data.mail,
                            firstname: data.firstname,
                            lastname: data.lastname,
                            entities: data.entities,
                            groups: data.groups,
                            preferences: data.preferences,
                            privileges: data.privileges[0] === 'ALL_PRIVILEGES' ? this.privilegeService.getAllPrivileges(!data.lockAdvancedPrivileges, this.authMode) : data.privileges,
                            featureTour: data.featureTour
                        };
                        this.headerService.nbResourcesFollowed = data.nbFollowedResources;
                        this.privilegeService.resfreshUserShortcuts();
                    })
                );
        }
    }

    clearFilters() {
        this.adminService.filters = {};
        this.adminService.searchTerm.setValue('');
    }

    canLogOut(): boolean {
        return ['sso', 'azure_saml'].indexOf(this.authMode) > -1 && this.functionsService.empty(this.authUri) ? false : true;
    }

    resetTimer() {
        this.inactivityTime = this.idleTime * 60 * 1000; // convert to milliseconds
        this.warningTime = (this.idleTime * 60 * 1000) - (10 * 1000); // subtract 10 seconds from the remaining time
        if (this.userActivitySubscription) {
            this.userActivitySubscription.unsubscribe();
        }

        if (this.inactivitySubscription) {
            this.inactivitySubscription.unsubscribe();
        }
        this.userActivitySubscription = timer(this.warningTime).subscribe(() => {
            const dialogRef = this.dialog.open(AlertComponent, {
                panelClass: 'maarch-modal',
                autoFocus: false,
                disableClose: true,
                data: {
                    title: this.translate.instant('lang.warning') + ' !',
                    isCounter: true,
                    buttonValidate: this.translate.instant('lang.keepLogin'),
                }
            });

            dialogRef.afterClosed().pipe(
                tap((data: any) => {
                    if (data === 'resetTimer') {
                        this.inactivitySubscription.unsubscribe();
                        this.resetTimer();
                    }
                })
            ).subscribe();
        });
        this.inactivitySubscription = timer(this.inactivityTime)
            .pipe(
                switchMap(() => this.logout()),
            ).subscribe();
    }
}
