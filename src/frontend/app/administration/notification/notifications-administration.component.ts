import { Component, ViewChild, OnInit, TemplateRef, ViewContainerRef } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { TranslateService } from '@ngx-translate/core';
import { MatPaginator } from '@angular/material/paginator';
import { MatSidenav } from '@angular/material/sidenav';
import { MatSort } from '@angular/material/sort';
import { NotificationService } from '@service/notification/notification.service';
import { HeaderService } from '@service/header.service';
import { AppService } from '@service/app.service';
import { FunctionsService } from '@service/functions.service';
import { of } from 'rxjs';
import { catchError, exhaustMap, filter, finalize, tap } from 'rxjs/operators';
import { AdministrationService } from '../administration.service';
import { ConfirmComponent } from '@plugins/modal/confirm.component';
import { MatDialog } from '@angular/material/dialog';

@Component({
    templateUrl: 'notifications-administration.component.html'
})
export class NotificationsAdministrationComponent implements OnInit {

    @ViewChild('snav2', { static: true }) public sidenavRight: MatSidenav;
    @ViewChild('adminMenuTemplate', { static: true }) adminMenuTemplate: TemplateRef<any>;
    @ViewChild(MatPaginator, { static: false }) paginator: MatPaginator;
    @ViewChild(MatSort, { static: false }) sort: MatSort;

    notifications: any[] = [];
    loading: boolean = false;

    hours: any;
    minutes: any;

    months: any = [];

    dom: any = [];

    dow: any = [];

    newCron: any = {
        'm': '',
        'h': '',
        'dom': '',
        'mon': '',
        'cmd': '',
        'state': 'normal'
    };

    authorizedNotification: any;
    crontab: any;

    displayedColumns = ['notification_id', 'description', 'is_enabled', 'notifications'];
    filterColumns = ['notification_id', 'description'];

    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        private notify: NotificationService,
        private headerService: HeaderService,
        public appService: AppService,
        public functions: FunctionsService,
        public adminService: AdministrationService,
        private viewContainerRef: ViewContainerRef,
        public dialog: MatDialog,
    ) { }

    ngOnInit(): void {
        this.headerService.setHeader(this.translate.instant('lang.administration') + ' ' + this.translate.instant('lang.notifications'));

        this.headerService.injectInSideBarLeft(this.adminMenuTemplate, this.viewContainerRef, 'adminMenu');

        this.loading = true;

        this.http.get('../rest/notifications')
            .subscribe((data: any) => {
                this.notifications = data.notifications;
                this.loading = false;
                setTimeout(() => {
                    this.adminService.setDataSource('admin_notif', this.notifications, this.sort, this.paginator, this.filterColumns);
                }, 0);
            }, (err: any) => {
                this.notify.error(err.error.errors);
            });
    }

    deleteNotification(notification: any) {
        const dialogRef = this.dialog.open(ConfirmComponent, { panelClass: 'maarch-modal', autoFocus: false, disableClose: true, data: { title: `${this.translate.instant('lang.deleteNotificationConfirm')} « ${notification.description} »`, msg: this.translate.instant('lang.confirmAction') } });
        dialogRef.afterClosed().pipe(
            filter((data: string) => data === 'ok'),
            exhaustMap(() => this.http.delete('../rest/notifications/' + notification.notification_sid)),
            tap((data: any) => {
                this.notifications = data.notifications;
                setTimeout(() => {
                    this.adminService.setDataSource('admin_notif', this.notifications, this.sort, this.paginator, this.filterColumns);
                }, 0);
                this.sidenavRight.close();
                this.notify.success(this.translate.instant('lang.notificationDeleted'));
            }),
            catchError((err: any) => {
                this.notify.handleSoftErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    loadCron() {
        return new Promise((resolve) => {
            this.hours = [{ label: this.translate.instant('lang.eachHour'), value: '*' }];
            this.minutes = [{ label: this.translate.instant('lang.eachMinute'), value: '*' }];

            this.months = [
                { label: this.translate.instant('lang.eachMonth'), value: '*' },
                { label: this.translate.instant('lang.january'), value: '1' },
                { label: this.translate.instant('lang.february'), value: '2' },
                { label: this.translate.instant('lang.march'), value: '3' },
                { label: this.translate.instant('lang.april'), value: '4' },
                { label: this.translate.instant('lang.may'), value: '5' },
                { label: this.translate.instant('lang.june'), value: '6' },
                { label: this.translate.instant('lang.july'), value: '7' },
                { label: this.translate.instant('lang.august'), value: '8' },
                { label: this.translate.instant('lang.september'), value: '9' },
                { label: this.translate.instant('lang.october'), value: '10' },
                { label: this.translate.instant('lang.november'), value: '11' },
                { label: this.translate.instant('lang.december'), value: '12' }
            ];

            this.dom = [{ label: this.translate.instant('lang.notUsed'), value: '*' }];

            this.dow = [
                { label: this.translate.instant('lang.eachDay'), value: '*' },
                { label: this.translate.instant('lang.monday'), value: '1' },
                { label: this.translate.instant('lang.tuesday'), value: '2' },
                { label: this.translate.instant('lang.wednesday'), value: '3' },
                { label: this.translate.instant('lang.thursday'), value: '4' },
                { label: this.translate.instant('lang.friday'), value: '5' },
                { label: this.translate.instant('lang.saturday'), value: '6' },
                { label: this.translate.instant('lang.sunday'), value: '7' }
            ];

            this.newCron = {
                'm': '',
                'h': '',
                'dom': '',
                'mon': '',
                'cmd': '',
                'state': 'normal'
            };

            for (let it = 0; it <= 23; it++) {
                this.hours.push({ label: it, value: String(it) });
            }

            for (let it = 0; it <= 59; it++) {
                this.minutes.push({ label: it, value: String(it) });
            }

            for (let it = 1; it <= 31; it++) {
                this.dom.push({ label: it, value: String(it) });
            }
            this.http.get('../rest/notifications/schedule').pipe(
                tap((data: any) => {
                    this.crontab = data.crontab;
                    this.authorizedNotification = data.authorizedNotification;
                }),
                finalize(() => resolve(true)),
                catchError((err: any) => {
                    this.notify.handleSoftErrors(err);
                    return of(false);
                })
            ).subscribe();
        });
    }

    saveCron() {
        const description = this.newCron.cmd.split('/');
        this.newCron.description = description[description.length - 1];
        this.crontab.push(this.newCron);
        this.http.post('../rest/notifications/schedule', this.crontab)
            .subscribe((data: any) => {
                this.newCron = {
                    'm': '',
                    'h': '',
                    'dom': '',
                    'mon': '',
                    'cmd': '',
                    'description': '',
                    'state': 'normal'
                };
                this.notify.success(this.translate.instant('lang.notificationScheduleUpdated'));
            }, (err) => {
                this.crontab.pop();
                this.notify.error(err.error.errors);
            });
    }

    deleteCron(i: number) {
        this.crontab[i].state = 'deleted';
        this.http.post('../rest/notifications/schedule', this.crontab)
            .subscribe((data: any) => {
                this.crontab.splice(i, 1);
                this.notify.success(this.translate.instant('lang.notificationScheduleUpdated'));
            }, (err) => {
                this.notify.error(err.error.errors);
            });
    }

    /* FEATURE TOUR */

    onNext() {
        this.sidenavRight.open();
        return false;
    }

    async paramCron() {
        await this.loadCron();
        const notifBasket = this.authorizedNotification.filter((notif: any) => notif.path.indexOf('_BASKETS.sh') > -1)[0];
        this.newCron = {
            'm': '0',
            'h': '8',
            'dom': '*',
            'dow': '*',
            'mon': '*',
            'cmd': notifBasket.path,
            'description': notifBasket.description,
            'state': 'normal'
        };
    }
}
