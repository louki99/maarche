import { Component, Inject, OnInit } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { TranslateService } from '@ngx-translate/core';
import { MatDialogRef, MAT_DIALOG_DATA } from '@angular/material/dialog';
import { NotificationService } from '@service/notification/notification.service';
import { tap, catchError } from 'rxjs/operators';
import { of } from 'rxjs';
import { PrivilegeService } from '@service/privileges.service';

@Component({
    templateUrl: 'add-private-indexing-model-modal.component.html',
    styleUrls: ['add-private-indexing-model-modal.component.scss'],
})
export class AddPrivateIndexingModelModalComponent implements OnInit {


    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        @Inject(MAT_DIALOG_DATA) public data: any,
        public dialogRef: MatDialogRef<AddPrivateIndexingModelModalComponent>,
        private notify: NotificationService,
        public privilegeService: PrivilegeService) {
    }

    ngOnInit(): void { }

    onSubmit() {
        this.http.post('../rest/indexingModels', this.data.indexingModel).pipe(
            tap((data: any) => {
                this.data.indexingModel.id = data.id;
                this.notify.success(this.translate.instant('lang.indexingModelAdded'));
                this.dialogRef.close(this.data);
            }),
            catchError((err: any) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }
}
