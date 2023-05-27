import { Component, OnInit, Input, EventEmitter, Output } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { TranslateService } from '@ngx-translate/core';
import { catchError, tap, finalize } from 'rxjs/operators';
import { of } from 'rxjs';
import { NotificationService } from '@service/notification/notification.service';
import { PrivilegeService } from '@service/privileges.service';


@Component({
    selector: 'app-history-workflow-resume',
    templateUrl: 'history-workflow-resume.component.html',
    styleUrls: [
        'history-workflow-resume.component.scss',
    ]
})

export class HistoryWorkflowResumeComponent implements OnInit {

    @Input('resId') resId: number = null;
    @Output('goTo') goTo = new EventEmitter<string>();

    loading: boolean = true;
    disabledHistory: boolean = true;

    histories: any[] = [];

    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        private notify: NotificationService,
        public privilegeService: PrivilegeService
    ) {
    }

    ngOnInit(): void {
        this.loading = true;
        if (this.privilegeService.hasCurrentUserPrivilege('view_full_history') || this.privilegeService.hasCurrentUserPrivilege('view_doc_history')) {
            this.disabledHistory = false;
            this.loadHistory(this.resId);
        } else {
            this.loading = false;
        }
    }

    loadHistory(resId: number) {
        this.loading = true;
        this.http.get(`../rest/history?resId=${resId}&limit=3&onlyActions=true`).pipe(
            tap((data: any) => {
                this.histories = data.history;
            }),
            finalize(() => this.loading = false),
            catchError((err: any) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    showMore() {
        this.goTo.emit();
    }
}
