import { HttpClient } from '@angular/common/http';
import { Component, OnInit, TemplateRef, ViewChild, ViewContainerRef } from '@angular/core';
import { NgForm } from '@angular/forms';
import { MatDialog } from '@angular/material/dialog';
import { MatPaginator } from '@angular/material/paginator';
import { MatSort } from '@angular/material/sort';
import { TranslateService } from '@ngx-translate/core';
import { ConfirmComponent } from '@plugins/modal/confirm.component';
import { AppService } from '@service/app.service';
import { AuthService } from '@service/auth.service';
import { HeaderService } from '@service/header.service';
import { NotificationService } from '@service/notification/notification.service';
import { of } from 'rxjs';
import { catchError, exhaustMap, filter, tap } from 'rxjs/operators';
import { AdministrationService } from '../../administration.service';

@Component({
    selector: 'app-sso-administration',
    templateUrl: './sso-administration.component.html',
    styleUrls: ['./sso-administration.component.scss']
})
export class SsoAdministrationComponent implements OnInit {

    @ViewChild('adminMenuTemplate', { static: true }) adminMenuTemplate: TemplateRef<any>;
    @ViewChild(MatPaginator, { static: false }) paginator: MatPaginator;
    @ViewChild(MatSort, { static: false }) sort: MatSort;

    loading: boolean = true;

    subMenus: any[] = [
        {
            icon: 'fas fa-users-cog',
            route: '/administration/connections/sso',
            label: this.translate.instant('lang.sso'),
            current: true
        }
    ];

    sso = {
        url: '',
        mapping: [
            {
                maarchId: 'login',
                ssoId: 'id',
                desc: 'lang.fieldUserIdDescSso'
            }
        ]
    };

    ssoClone: any;

    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        private notify: NotificationService,
        public appService: AppService,
        private headerService: HeaderService,
        private viewContainerRef: ViewContainerRef,
        public adminService: AdministrationService,
        public dialog: MatDialog,
        private authService: AuthService,
    ) { }

    ngOnInit(): void {
        this.headerService.injectInSideBarLeft(this.adminMenuTemplate, this.viewContainerRef, 'adminMenu');
        this.headerService.setHeader(this.translate.instant('lang.administration') + ' ' + this.translate.instant('lang.ssoConnections'));
        this.getConnection();
    }

    getConnection() {
        this.http.get('../rest/configurations/admin_sso').pipe(
            tap((data: any) => {
                this.sso = data.configuration.value;
                this.ssoClone = JSON.parse(JSON.stringify(this.sso));
                this.loading = false;
            }),
            catchError((err: any) => {
                this.notify.handleSoftErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    isValid(ssoForm: NgForm) {
        return ssoForm.form.valid && JSON.stringify(this.sso) !== JSON.stringify(this.ssoClone);
    }

    cancel() {
        this.sso = JSON.parse(JSON.stringify(this.ssoClone));
    }

    formatData() {
        const objTosend = JSON.parse(JSON.stringify(this.sso));

        objTosend.mapping = objTosend.mapping.map(((item: any) => {
            delete item.desc;
            return item;
        }));

        return objTosend;
    }

    onSubmit() {
        this.formatData();
        if (this.authService.authMode !== 'sso') {
            const dialogRef = this.dialog.open(ConfirmComponent, { panelClass: 'maarch-modal', autoFocus: false, disableClose: true, data: { title: this.translate.instant('lang.warning') + ' !', msg: this.translate.instant('lang.warningConnectionMsg') } });

            dialogRef.afterClosed().pipe(
                filter((data: string) => data === 'ok'),
                exhaustMap(() => this.http.put('../rest/configurations/admin_sso', this.formatData())),
                tap(() => {
                    this.notify.success(this.translate.instant('lang.dataUpdated'));
                    this.ssoClone = JSON.parse(JSON.stringify(this.sso));
                }),
                catchError((err: any) => {
                    this.notify.handleSoftErrors(err);
                    return of(false);
                })
            ).subscribe();
        } else {
            this.http.put('../rest/configurations/admin_sso', this.formatData()).pipe(
                tap(() => {
                    this.notify.success(this.translate.instant('lang.dataUpdated'));
                    this.ssoClone = JSON.parse(JSON.stringify(this.sso));
                }),
                catchError((err: any) => {
                    this.notify.handleSoftErrors(err);
                    return of(false);
                })
            ).subscribe();
        }
    }

}
