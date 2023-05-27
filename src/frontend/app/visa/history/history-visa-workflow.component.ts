import { Component, Input, OnInit } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { TranslateService } from '@ngx-translate/core';
import { NotificationService } from '@service/notification/notification.service';
import { FunctionsService } from '@service/functions.service';
import { tap, catchError, finalize } from 'rxjs/operators';
import { of } from 'rxjs';
import { MatDialog } from '@angular/material/dialog';


@Component({
    selector: 'app-history-visa-workflow',
    templateUrl: 'history-visa-workflow.component.html',
    styleUrls: ['history-visa-workflow.component.scss'],
})
export class HistoryVisaWorkflowComponent implements OnInit {

    @Input() resId: number = null;

    visaWorkflowHistory: any[] = [];

    loading: boolean = false;
    data: any;


    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        private notify: NotificationService,
        public functions: FunctionsService,
        public dialog: MatDialog,
    ) { }

    ngOnInit(): void {
        if (!this.functions.empty(this.resId)) {
            this.loadWorkflowHistory();
        }
        this.loading = false;
    }


    loadWorkflowHistory() {
        this.loading = true;

        return new Promise((resolve, reject) => {
            this.http.get(`../rest/resources/${this.resId}/circuitsHistory?type=visaCircuit`).pipe(
                tap((data: any) => {
                    this.visaWorkflowHistory = data['listInstanceHistory'];
                }),
                finalize(() => {
                    this.loading = false;
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
