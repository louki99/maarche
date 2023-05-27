import { Component, Inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { TranslateService } from '@ngx-translate/core';
import { MatDialogRef, MAT_DIALOG_DATA } from '@angular/material/dialog';
import { NotificationService } from '@service/notification/notification.service';
import { tap, catchError } from 'rxjs/operators';
import { of } from 'rxjs';
import { DatePipe } from '@angular/common';
import { FunctionsService } from '@service/functions.service';

@Component({
    templateUrl: 'search-template-modal.component.html',
    styleUrls: ['search-template-modal.component.scss'],
})
export class AddSearchTemplateModalComponent {

    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        @Inject(MAT_DIALOG_DATA) public data: any,
        public dialogRef: MatDialogRef<AddSearchTemplateModalComponent>,
        private notify: NotificationService,
        private datePipe: DatePipe,
        public functions: FunctionsService) {
    }

    onSubmit() {
        Object.keys(this.data.searchTemplate.query).map((data: any) => {
            if (!this.functions.empty(this.data.searchTemplate.query[data].values?.start)) {
                this.data.searchTemplate.query[data].values.start = this.datePipe.transform(this.data.searchTemplate.query[data].values.start, 'y-MM-dd');
            }
            if (!this.functions.empty(this.data.searchTemplate.query[data].values?.end)) {
                this.data.searchTemplate.query[data].values.end = this.datePipe.transform(this.data.searchTemplate.query[data].values.end, 'y-MM-dd');
            }
        });
        this.http.post('../rest/searchTemplates', this.data.searchTemplate).pipe(
            tap((data: any) => {
                this.data.searchTemplate.id = data.id;
                this.notify.success(this.translate.instant('lang.searchTemplateAdded'));
                this.dialogRef.close(this.data);
            }),
            catchError((err: any) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }
}
