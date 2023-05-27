import { HttpClient } from '@angular/common/http';
import { Component, Inject, OnInit } from '@angular/core';
import { MAT_DIALOG_DATA, MatDialogRef } from '@angular/material/dialog';
import { TranslateService } from '@ngx-translate/core';
import { NotificationService } from '@service/notification/notification.service';
import { of } from 'rxjs';
import { catchError, tap } from 'rxjs/operators';

@Component({
    templateUrl: 'contacts-group-merge-modal.component.html',
    styleUrls: ['contacts-group-merge-modal.component.scss'],
})
export class ContactsGroupMergeModalComponent implements OnInit {

    loading: boolean = false;
    label: string = '';
    description: string = '';
    itemsToMerge: number[] = [];

    constructor(
        public http: HttpClient,
        private notify: NotificationService,
        public translate: TranslateService,
        @Inject(MAT_DIALOG_DATA) public data: any,
        public dialogRef: MatDialogRef<ContactsGroupMergeModalComponent>
    ) { }

    ngOnInit(): void {
        this.itemsToMerge = this.data.itemsToMerge;
        console.log(this.itemsToMerge);
    }

    onSubmit() {
        this.loading = true;
        this.http.put('../rest/contactsGroups/merge', this.formatData()).pipe(
            tap((data: any) => {
                this.notify.success(this.translate.instant('lang.contactsGroupMerged'));
                this.dialogRef.close('success');
            }),
            catchError((err: any) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    formatData() {
        return {
            contactsGroups: this.itemsToMerge.map((item: any) => item.id),
            label: this.label,
            description: this.description
        };
    }

    isValid() {
        return this.label !== '' && this.description !== '';
    }

}
