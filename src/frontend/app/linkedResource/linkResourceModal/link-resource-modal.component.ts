import { Component, Inject, OnInit, ViewChild } from '@angular/core';
import { MAT_DIALOG_DATA, MatDialogRef } from '@angular/material/dialog';
import { TranslateService } from '@ngx-translate/core';
import { HttpClient } from '@angular/common/http';
import { NotificationService } from '@service/notification/notification.service';
import { of } from 'rxjs';
import { catchError, tap } from 'rxjs/operators';
import { SearchResultListComponent } from '@appRoot/search/result-list/search-result-list.component';
import { Router } from '@angular/router';
import { CriteriaToolComponent } from '@appRoot/search/criteria-tool/criteria-tool.component';
import { FunctionsService } from '@service/functions.service';

@Component({
    templateUrl: 'link-resource-modal.component.html',
    styleUrls: ['link-resource-modal.component.scss'],
})
export class LinkResourceModalComponent implements OnInit {

    @ViewChild('appSearchResultList', { static: false }) appSearchResultList: SearchResultListComponent;
    @ViewChild('appCriteriaTool', { static: false }) appCriteriaTool: CriteriaToolComponent;

    searchUrl: string = '';
    result: any;

    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        public router: Router,
        public functions: FunctionsService,
        private notify: NotificationService,
        @Inject(MAT_DIALOG_DATA) public data: any,
        public dialogRef: MatDialogRef<LinkResourceModalComponent>) {
    }

    ngOnInit(): void { }

    linkResources() {
        if (this.router.url.includes('indexing')) {
            this.dialogRef.close(this.appSearchResultList.selectedRes);
        } else {
            const selectedRes = this.appSearchResultList.getSelectedResources().filter(res => res !== this.data.resId);
            this.http.post(`../rest/resources/${this.data.resId}/linkedResources`, { linkedResources: selectedRes }).pipe(
                tap(() => {
                    this.dialogRef.close('success');
                }),
                catchError((err: any) => {
                    this.notify.handleSoftErrors(err);
                    return of(false);
                })
            ).subscribe();
        }
    }

    isSelectedResources() {
        return this.appSearchResultList !== undefined && this.appSearchResultList.getSelectedResources().filter(res => res !== this.data.resId).length > 0;
    }
}
