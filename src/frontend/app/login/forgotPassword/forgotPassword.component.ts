import { Component, OnInit } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { NotificationService } from '@service/notification/notification.service';
import { finalize } from 'rxjs/operators';
import { TranslateService } from '@ngx-translate/core';
import { HeaderService } from '@service/header.service';
import { Router } from '@angular/router';

@Component({
    templateUrl: 'forgotPassword.component.html',
    styleUrls: ['forgotPassword.component.scss'],
})
export class ForgotPasswordComponent implements OnInit {


    loadingForm: boolean = false;
    loading: boolean = false;
    newLogin: any = {
        login: ''
    };
    labelButton: string = this.translate.instant('lang.send');

    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        private router: Router,
        public notificationService: NotificationService,
        private headerService: HeaderService
    ) {
    }

    ngOnInit(): void {
        this.headerService.hideSideBar = true;
    }

    generateLink() {
        this.labelButton = this.translate.instant('lang.generation');
        this.loading = true;

        this.http.post('../rest/password', { 'login': this.newLogin.login })
            .pipe(
                finalize(() => {
                    this.labelButton = this.translate.instant('lang.send');
                    this.loading = false;
                })
            )
            .subscribe((data: any) => {
                this.loadingForm = true;
                this.notificationService.success(this.translate.instant('lang.requestSentByEmail'));
                this.router.navigate(['/login']);
            }, (err: any) => {
                this.notificationService.handleErrors(err);
            });
    }

    cancel() {
        this.router.navigate(['/login']);
    }
}
