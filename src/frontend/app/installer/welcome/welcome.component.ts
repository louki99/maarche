import { Component, OnInit } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { NotificationService } from '@service/notification/notification.service';
import { TranslateService } from '@ngx-translate/core';
import { UntypedFormBuilder, UntypedFormGroup, Validators } from '@angular/forms';
import { environment } from '../../../environments/environment';
import { catchError, tap } from 'rxjs/operators';
import { of } from 'rxjs';
import { AuthService } from '@service/auth.service';


@Component({
    selector: 'app-welcome',
    templateUrl: './welcome.component.html',
    styleUrls: ['./welcome.component.scss']
})
export class WelcomeComponent implements OnInit {

    stepFormGroup: UntypedFormGroup;

    langs: string[] = [];

    appVersion: string = environment.BASE_VERSION;

    steps: any[] = [
        {
            icon: 'fas fa-check-square',
            desc: 'lang.prerequisiteCheck'
        },
        {
            icon: 'fa fa-database',
            desc: 'lang.databaseCreation'
        },
        {
            icon: 'fa fa-database',
            desc: 'lang.dataSampleCreation'
        },
        {
            icon: 'fa fa-hdd',
            desc: 'lang.docserverCreation'
        },
        {
            icon: 'fas fa-tools',
            desc: 'lang.stepCustomizationActionDesc'
        },
        {
            icon: 'fa fa-user',
            desc: 'lang.adminUserCreation'
        },
    ];

    customs: any = [];

    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        private notify: NotificationService,
        private _formBuilder: UntypedFormBuilder,
        private authService: AuthService
    ) { }

    ngOnInit(): void {
        this.stepFormGroup = this._formBuilder.group({
            lang: ['fr', Validators.required]
        });

        this.getLang();
        if (!this.authService.noInstall) {
            this.getCustoms();
        }
    }

    getLang() {
        this.http.get('../rest/languages').pipe(
            tap((data: any) => {
                this.langs = Object.keys(data.langs).filter(lang => lang !== 'nl');
            }),
            catchError((err: any) => {
                this.notify.handleSoftErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    changeLang(id: string) {
        this.translate.use(id);
    }

    getCustoms() {
        this.http.get('../rest/installer/customs').pipe(
            tap((data: any) => {
                this.customs = data.customs;
            }),
            catchError((err: any) => {
                this.notify.handleSoftErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    initStep() {
        return false;
    }

    getInfoToInstall(): any[] {
        return [];
    }

}
