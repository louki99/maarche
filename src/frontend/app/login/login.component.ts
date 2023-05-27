import { Component, OnInit } from '@angular/core';
import { MatDialog } from '@angular/material/dialog';
import { HttpClient } from '@angular/common/http';
import { Router } from '@angular/router';
import { Validators, UntypedFormGroup, UntypedFormBuilder } from '@angular/forms';
import { tap, catchError } from 'rxjs/operators';
import { AuthService } from '@service/auth.service';
import { NotificationService } from '@service/notification/notification.service';
import { environment } from '../../environments/environment';
import { of } from 'rxjs';
import { HeaderService } from '@service/header.service';
import { FunctionsService } from '@service/functions.service';
import { TimeLimitPipe } from '../../plugins/timeLimit.pipe';
import { TranslateService } from '@ngx-translate/core';
import { LocalStorageService } from '@service/local-storage.service';

@Component({
    templateUrl: 'login.component.html',
    styleUrls: ['login.component.scss'],
    providers: [TimeLimitPipe]
})
export class LoginComponent implements OnInit {
    loginForm: UntypedFormGroup;

    loading: boolean = false;
    showForm: boolean = true;
    environment: any;
    applicationName: string = '';
    loginMessage: string = '';
    hidePassword: boolean = true;

    constructor(
        public translate: TranslateService,
        private http: HttpClient,
        private router: Router,
        private headerService: HeaderService,
        public authService: AuthService,
        private localStorage: LocalStorageService,
        private functionsService: FunctionsService,
        private notify: NotificationService,
        public dialog: MatDialog,
        private formBuilder: UntypedFormBuilder,
        private timeLimit: TimeLimitPipe
    ) { }

    ngOnInit(): void {
        this.headerService.hideSideBar = true;
        this.loginForm = this.formBuilder.group({
            login: [null, Validators.required],
            password: [null, Validators.required]
        });

        this.environment = environment;
        if (this.authService.getToken() !== null) {
            if (!this.functionsService.empty(this.authService.getUrl(JSON.parse(atob(this.authService.getToken().split('.')[1])).user.id))) {
                this.router.navigate([this.authService.getUrl(JSON.parse(atob(this.authService.getToken().split('.')[1])).user.id)]);
            } else {
                this.router.navigate(['/home']);
            }
        } else {
            this.initConnection();
        }
    }

    onSubmit(ssoToken = null) {
        this.loading = true;

        let url = '../rest/authenticate';

        if (ssoToken !== null) {
            url += ssoToken;
        }

        this.http.post(
            url,
            {
                'login': this.loginForm.get('login').value,
                'password': this.loginForm.get('password').value,
            },
            {
                observe: 'response'
            }
        ).pipe(
            tap((data: any) => {
                this.localStorage.resetLocal();
                this.authService.saveTokens(data.headers.get('Token'), data.headers.get('Refresh-Token'));
                this.authService.setUser({});
                if (this.authService.getCachedUrl()) {
                    this.router.navigateByUrl(this.authService.getCachedUrl());
                    this.authService.cleanCachedUrl();
                } else if (!this.functionsService.empty(this.authService.getUrl(JSON.parse(atob(data.headers.get('Token').split('.')[1])).user.id))) {
                    this.router.navigate([this.authService.getUrl(JSON.parse(atob(data.headers.get('Token').split('.')[1])).user.id)]);
                } else {
                    this.router.navigate(['/home']);
                }
            }),
            catchError((err: any) => {
                this.loading = false;
                if (err.error.errors === 'Authentication Failed') {
                    this.notify.error(this.translate.instant('lang.wrongLoginPassword'));
                } else if (err.error.errors === 'Account Locked') {
                    this.notify.error(this.translate.instant('lang.accountLocked') + ' ' + this.timeLimit.transform(err.error.date));
                } else if (this.authService.authMode === 'sso' && err.error.errors === 'Authentication Failed : login not present in header' && !this.functionsService.empty(this.authService.authUri)) {
                    window.location.href = this.authService.authUri;
                } else if (this.authService.authMode === 'openam' && err.error.errors === 'Authentication Failed : User cookie is not set' && !this.functionsService.empty(this.authService.authUri)) {
                    window.location.href = this.authService.authUri;
                } else if (this.authService.authMode === 'azure_saml' && err.error.errors === 'Authentication Failed : not logged') {
                    window.location.href = err.error.authUri;
                } else {
                    this.notify.handleSoftErrors(err);
                }
                return of(false);
            })
        ).subscribe();
    }

    initConnection() {
        if (['sso', 'openam', 'azure_saml'].indexOf(this.authService.authMode) > -1) {
            this.loginForm.disable();
            this.loginForm.setValidators(null);
            this.onSubmit();
        } else if (['cas', 'keycloak'].indexOf(this.authService.authMode) > -1) {
            this.loginForm.disable();
            this.loginForm.setValidators(null);
            const regexCas = /ticket=[.]*/g;
            const regexKeycloak = /code=[.]*/g;
            if (window.location.search.match(regexCas) !== null || window.location.search.match(regexKeycloak) !== null) {
                const ssoToken = window.location.search.substring(1, window.location.search.length);

                const regexKeycloakState = /state=[.]*/g;
                if (ssoToken.match(regexKeycloakState) !== null) {
                    const params = new URLSearchParams(window.location.search.substring(1));
                    const keycloakState = this.localStorage.get('keycloakState');
                    const paramState = params.get('state');

                    this.localStorage.save('keycloakState', null);

                    if (keycloakState !== paramState && keycloakState !== null) {
                        window.location.href = this.authService.authUri;
                        return;
                    }
                }

                window.history.replaceState({}, document.title, window.location.pathname + window.location.hash);
                this.onSubmit(`?${ssoToken}`);
            } else {
                window.location.href = this.authService.authUri;
            }
        }
    }

    goTo(route: string) {
        if (this.authService.mailServerOnline) {
            this.router.navigate([route]);
        } else {
            this.notify.error(this.translate.instant('lang.mailServerOffline'));
        }
    }
}
