import { Component, OnInit, ViewChild, TemplateRef, ViewContainerRef } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { TranslateService } from '@ngx-translate/core';
import { AppService } from '@service/app.service';
import { FunctionsService } from '@service/functions.service';
import { HistoryComponent } from '../../history/history.component';
import { PrivilegeService } from '@service/privileges.service';
import { HeaderService } from '@service/header.service';
import { HistoryExportComponent } from './export/history-export.component';
import { MatDialog } from '@angular/material/dialog';

@Component({
    selector: 'app-admin-history',
    templateUrl: 'history-administration.component.html',
    styleUrls: ['history-administration.component.scss']
})
export class HistoryAdministrationComponent implements OnInit {

    @ViewChild('adminMenuTemplate', { static: true }) adminMenuTemplate: TemplateRef<any>;
    @ViewChild('appHistoryList', { static: false }) appHistoryList: HistoryComponent;

    startDateFilter: any = '';
    endDateFilter: any = '';

    subMenus: any[] = [
        {
            icon: 'fa fa-history',
            route: '/administration/history',
            label: this.translate.instant('lang.history'),
            current: true
        },
        {
            icon: 'fa fa-history',
            route: '/administration/history-batch',
            label: this.translate.instant('lang.historyBatch'),
            current: false
        }
    ];

    history: any[] = [
        {
            value: 'event_date',
            label: this.translate.instant('lang.event')
        },
        {
            value: 'record_id',
            label: this.translate.instant('lang.technicalId')
        },
        {
            value: 'userLabel',
            label: this.translate.instant('lang.contact_user')
        },
        {
            value: 'info',
            label: this.translate.instant('lang.information')
        },
        {
            value: 'remote_ip',
            label: this.translate.instant('lang.ip')
        }
    ];

    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        public appService: AppService,
        public functions: FunctionsService,
        public dialog: MatDialog,
        private privilegeService: PrivilegeService,
        private headerService: HeaderService,
        private viewContainerRef: ViewContainerRef
    ) { }

    ngOnInit(): void {
        this.headerService.setHeader(this.translate.instant('lang.administration') + ' ' + this.translate.instant('lang.history').toLowerCase(), '', '');

        this.headerService.injectInSideBarLeft(this.adminMenuTemplate, this.viewContainerRef, 'adminMenu');

        if (this.privilegeService.hasCurrentUserPrivilege('view_history_batch')) {
            this.subMenus = [
                {
                    icon: 'fa fa-history',
                    route: '/administration/history',
                    label: this.translate.instant('lang.history'),
                    current: true
                },
                {
                    icon: 'fa fa-history',
                    route: '/administration/history-batch',
                    label: this.translate.instant('lang.historyBatch'),
                    current: false
                }
            ];
        } else {
            this.subMenus = [
                {
                    icon: 'fa fa-history',
                    route: '/administration/history',
                    label: this.translate.instant('lang.history'),
                    current: true
                }
            ];
        }
    }

    openHistoryExport() {
        const parameters: any = {
            filterUsed : this.appHistoryList.filterUsed,
            startDate: this.functions.empty(this.appHistoryList.startDateFilter) ? '' : this.functions.formatDateObjectToDateString(this.appHistoryList.startDateFilter),
            endDate: this.functions.empty(this.appHistoryList.endDateFilter) ? '' : this.functions.formatDateObjectToDateString(this.appHistoryList.endDateFilter)
        };
        this.dialog.open(HistoryExportComponent,
            {
                panelClass: 'maarch-modal',
                width: '800px',
                autoFocus: false,
                data: {
                    origin: 'history',
                    dataAvailable: this.history,
                    parameters: parameters
                }
            });
    }
}
