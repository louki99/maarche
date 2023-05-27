
import { Injectable } from '@angular/core';
import { ActivatedRouteSnapshot, CanActivate, Router, RouterStateSnapshot, CanDeactivate, UrlTree } from '@angular/router';
import { HttpClient } from '@angular/common/http';
import { Observable, of } from 'rxjs';
import { map, tap, catchError, exhaustMap, filter, finalize } from 'rxjs/operators';
import { HeaderService } from './header.service';
import { ProcessComponent } from '../app/process/process.component';
import { PrivilegeService } from './privileges.service';
import { AuthService } from './auth.service';
import { LocalStorageService } from './local-storage.service';
import { FunctionsService } from './functions.service';
import { AlertComponent } from '../plugins/modal/alert.component';
import { MatDialog } from '@angular/material/dialog';
import { TranslateService } from '@ngx-translate/core';

@Injectable({
    providedIn: 'root'
})
export class AppLightGuard implements CanActivate {

    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        private authService: AuthService,
        public headerService: HeaderService,
    ) { }

    canActivate(route: ActivatedRouteSnapshot, state: RouterStateSnapshot): Observable<any> {
        const urlArr = state.url.replace(/^\/+|\/+$/g, '').split('/');

        console.debug('== ROUTE LIGHT GUARD ==');
        console.debug(state.url);

        this.headerService.resetSideNavSelection();

        return this.authService.getLoginInformations(state.url).pipe(
            exhaustMap(() => this.authService.getToken() !== null && state.url !== '/login' ? this.authService.getCurrentUserInfo() : of(false)),
            map(() => true),
            catchError((err: any) => of(true))
        );
    }
}
