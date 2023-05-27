import { Component, OnInit, Inject } from '@angular/core';
import { TranslateService } from '@ngx-translate/core';
import { NotificationService } from '@service/notification/notification.service';
import { MatDialogRef, MAT_DIALOG_DATA } from '@angular/material/dialog';
import { HttpClient } from '@angular/common/http';
import { tap, finalize, catchError } from 'rxjs/operators';
import { of } from 'rxjs';

@Component({
    templateUrl: 'folder-create-modal.component.html',
    styleUrls: ['folder-create-modal.component.scss'],
})
export class FolderCreateModalComponent implements OnInit {


    loading: boolean = false;
    folderName: string = '';

    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        private notify: NotificationService,
        @Inject(MAT_DIALOG_DATA) public data: any,
        public dialogRef: MatDialogRef<FolderCreateModalComponent>
    ) { }

    ngOnInit(): void {
        this.folderName = this.data.folderName !== undefined ? this.data.folderName : '';
    }

    onSubmit() {
        this.loading = true;
        this.http.post('../rest/folders', { label: this.folderName }).pipe(
            tap((data: any) => {
                this.notify.success(this.translate.instant('lang.folderAdded'));
                this.dialogRef.close(data.folder);
            }),
            finalize(() => this.loading = false),
            catchError((err) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }

}
