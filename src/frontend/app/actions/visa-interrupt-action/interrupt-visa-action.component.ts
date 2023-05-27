import { Component, OnInit, Inject, ViewChild } from '@angular/core';
import { TranslateService } from '@ngx-translate/core';
import { NotificationService } from '@service/notification/notification.service';
import { MAT_DIALOG_DATA, MatDialogRef } from '@angular/material/dialog';
import { HttpClient } from '@angular/common/http';
import { NoteEditorComponent } from '../../notes/note-editor.component';
import { tap, finalize, catchError } from 'rxjs/operators';
import { of } from 'rxjs';
import { FunctionsService } from '@service/functions.service';

@Component({
    templateUrl: 'interrupt-visa-action.component.html',
    styleUrls: ['interrupt-visa-action.component.scss'],
})
export class InterruptVisaActionComponent implements OnInit {

    @ViewChild('noteEditor', { static: true }) noteEditor: NoteEditorComponent;

    loading: boolean = false;

    resourcesWarnings: any[] = [];
    resourcesErrors: any[] = [];

    noResourceToProcess: boolean = null;

    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        private notify: NotificationService,
        public dialogRef: MatDialogRef<InterruptVisaActionComponent>,
        @Inject(MAT_DIALOG_DATA) public data: any,
        public functions: FunctionsService
    ) { }

    async ngOnInit() {
        this.loading = true;
        await this.checkInterruptVisa();
        this.loading = false;
    }

    checkInterruptVisa() {
        this.resourcesErrors = [];
        this.resourcesWarnings = [];

        return new Promise((resolve, reject) => {
            this.http.post('../rest/resourcesList/users/' + this.data.userId + '/groups/' + this.data.groupId + '/baskets/' + this.data.basketId + '/actions/' + this.data.action.id + '/checkInterruptResetVisa', { resources: this.data.resIds })
                .subscribe((data: any) => {
                    if (!this.functions.empty(data.resourcesInformations.warning)) {
                        this.resourcesWarnings = data.resourcesInformations.warning;
                    }

                    if (!this.functions.empty(data.resourcesInformations.error)) {
                        this.resourcesErrors = data.resourcesInformations.error;
                        this.noResourceToProcess = this.resourcesErrors.length === this.data.resIds.length;
                    }
                    resolve(true);
                }, (err: any) => {
                    this.notify.handleSoftErrors(err);
                    this.dialogRef.close();
                });
        });
    }

    onSubmit() {
        this.loading = true;
        this.executeAction();
    }

    executeAction() {
        this.http.put(this.data.processActionRoute, { resources: this.data.resIds, note: this.noteEditor.getNote() }).pipe(
            tap(() => {
                this.dialogRef.close(this.data.resIds);
            }),
            finalize(() => this.loading = false),
            catchError((err: any) => {
                this.notify.handleSoftErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    isValidAction() {
        return !this.noResourceToProcess;
    }
}
