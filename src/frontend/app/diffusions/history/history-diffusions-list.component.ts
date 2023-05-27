import { Component, Input, OnInit, Renderer2 } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { TranslateService } from '@ngx-translate/core';
import { NotificationService } from '@service/notification/notification.service';
import { catchError, map, tap } from 'rxjs/operators';
import { of } from 'rxjs';
import { MatDialog } from '@angular/material/dialog';
import { FunctionsService } from '@service/functions.service';
import { HeaderService } from '@service/header.service';

@Component({
    selector: 'app-history-diffusions-list',
    templateUrl: 'history-diffusions-list.component.html',
    styleUrls: ['history-diffusions-list.component.scss'],
})
export class HistoryDiffusionsListComponent implements OnInit {

    /**
     * Ressource identifier to load listinstance (Incompatible with templateId)
     */
    @Input() resId: number = null;

    /**
      * Expand all roles
      */
    @Input() expanded: boolean = true;

    roles: any = [];
    loading: boolean = true;
    availableRoles: any[] = [];
    currentEntityId: number = 0;
    userDestList: any[] = [];

    diffListHistory: any[] = [];

    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        private notify: NotificationService,
        private renderer: Renderer2,
        public dialog: MatDialog,
        public functions: FunctionsService,
        private headerService: HeaderService
    ) { }

    async ngOnInit(): Promise<void> {
        await this.initRoles();
        if (this.resId !== null) {
            this.getListinstanceHistory();
        }
        this.loading = false;
    }

    getListinstanceHistory() {
        return new Promise((resolve, reject) => {
            this.http.get(`../rest/resources/${this.resId}/listInstanceHistory`).pipe(
                tap((data: any) => {
                    this.diffListHistory = data['listInstanceHistory'];
                }),
                catchError((err: any) => {
                    this.notify.handleSoftErrors(err);
                    return of(false);
                })
            ).subscribe();
        });
    }

    initRoles() {
        return new Promise((resolve, reject) => {
            this.http.get('../rest/roles').pipe(
                map((data: any) => {
                    data.roles = data.roles.map((role: any) => ({
                        ...role,
                        id: role.id,
                    }));
                    return data.roles;
                }),
                tap((roles: any) => {
                    this.availableRoles = roles;
                    resolve(true);
                }),
                catchError((err: any) => {
                    this.notify.handleSoftErrors(err);
                    return of(false);
                })
            ).subscribe();
        });
    }
}
