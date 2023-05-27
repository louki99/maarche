import { Component, OnInit, Inject, ViewChild } from '@angular/core';
import { TranslateService } from '@ngx-translate/core';
import { NotificationService } from '@service/notification/notification.service';
import { MAT_DIALOG_DATA, MatDialogRef } from '@angular/material/dialog';
import { HttpClient } from '@angular/common/http';
import { NoteEditorComponent } from '../../notes/note-editor.component';
import { tap, finalize, catchError } from 'rxjs/operators';
import { of } from 'rxjs';
import { VisaWorkflowComponent } from '../../visa/visa-workflow.component';
import { FunctionsService } from '@service/functions.service';
import { SessionStorageService } from '@service/session-storage.service';

@Component({
    templateUrl: 'reset-visa-action.component.html',
    styleUrls: ['reset-visa-action.component.scss'],
})
export class ResetVisaActionComponent implements OnInit {

    @ViewChild('noteEditor', { static: true }) noteEditor: NoteEditorComponent;
    @ViewChild('appVisaWorkflow', { static: false }) appVisaWorkflow: VisaWorkflowComponent;

    loading: boolean = false;

    resourcesWarnings: any[] = [];
    resourcesErrors: any[] = [];

    noResourceToProcess: boolean = null;

    canGoToNextRes: boolean = false;
    showToggle: boolean = false;
    inLocalStorage: boolean = false;

    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        private notify: NotificationService,
        public dialogRef: MatDialogRef<ResetVisaActionComponent>,
        @Inject(MAT_DIALOG_DATA) public data: any,
        public functions: FunctionsService,
        private sessionStorage: SessionStorageService
    ) { }

    async ngOnInit() {
        this.loading = true;
        this.showToggle = this.data.additionalInfo.showToggle;
        this.canGoToNextRes = this.data.additionalInfo.canGoToNextRes;
        this.inLocalStorage = this.data.additionalInfo.inLocalStorage;
        await this.checkResetVisa();
        this.loading = false;
    }

    checkResetVisa() {
        this.resourcesErrors = [];
        this.resourcesWarnings = [];

        return new Promise((resolve, reject) => {
            this.http.post('../rest/resourcesList/users/' + this.data.userId + '/groups/' + this.data.groupId + '/baskets/' + this.data.basketId + '/actions/' + this.data.action.id + '/checkInterruptResetVisa', { resources: this.data.resIds })
                .pipe(
                    tap((data: any) => {
                        if (!this.functions.empty(data.resourcesInformations.warning)) {
                            this.resourcesWarnings = data.resourcesInformations.warning;
                        }
                        if (!this.functions.empty(data.resourcesInformations.error)) {
                            this.resourcesErrors = data.resourcesInformations.error;
                            this.noResourceToProcess = this.resourcesErrors.length === this.data.resIds.length;
                        }
                        resolve(true);
                    }),
                    catchError((err: any) => {
                        this.notify.handleSoftErrors(err);
                        this.dialogRef.close();
                        return of(false);
                    })
                ).subscribe();
        });
    }

    onSubmit() {
        this.loading = true;
        this.sessionStorage.checkSessionStorage(this.inLocalStorage, this.canGoToNextRes, this.data);
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
