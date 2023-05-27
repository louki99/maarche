import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { catchError, finalize, tap } from 'rxjs/operators';
import { of } from 'rxjs';
import { NotificationService } from './notification/notification.service';
import { AuthService } from './auth.service';
import { AlertComponent } from '../plugins/modal/alert.component';
import { MatDialog } from '@angular/material/dialog';
import { TranslateService } from '@ngx-translate/core';

declare let $: any;

@Injectable({
    providedIn: 'root'
})
export class AppService {

    screenWidth: number = 1920;

    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        private notify: NotificationService,
        public authService: AuthService,
        private dialog: MatDialog,
    ) { }

    getViewMode() {
        if (/Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent)) {
            return true;
        } else {
            return this.screenWidth <= 768;
        }
    }

    setScreenWidth(width: number) {
        this.screenWidth = width;
    }
}
