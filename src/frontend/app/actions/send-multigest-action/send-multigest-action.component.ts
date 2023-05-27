import { Component, OnInit, Inject, ViewChild } from '@angular/core';
import { TranslateService } from '@ngx-translate/core';
import { NotificationService } from '@service/notification/notification.service';
import { MAT_DIALOG_DATA, MatDialogRef } from '@angular/material/dialog';
import { HttpClient } from '@angular/common/http';
import { NoteEditorComponent } from '../../notes/note-editor.component';
import { tap, finalize, catchError } from 'rxjs/operators';
import { FunctionsService } from '@service/functions.service';
import { of } from 'rxjs';
import { SessionStorageService } from '@service/session-storage.service';


@Component({
    templateUrl: 'send-multigest-action.component.html',
    styleUrls: ['send-multigest-action.component.scss'],
})
export class SendMultigestActionComponent implements OnInit {

    @ViewChild('noteEditor', { static: true }) noteEditor: NoteEditorComponent;

    loading: boolean = false;

    errors: any;

    resourcesErrors: any[] = [];
    noResourceToProcess: boolean = null;

    canGoToNextRes: boolean = false;
    showToggle: boolean = false;
    inLocalStorage: boolean = false;

    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        private notify: NotificationService,
        public dialogRef: MatDialogRef<SendMultigestActionComponent>,
        @Inject(MAT_DIALOG_DATA) public data: any,
        public functions: FunctionsService,
        private sessionStorage: SessionStorageService
    ) { }

    async ngOnInit(): Promise<void> {
        this.loading = true;
        this.showToggle = this.data.additionalInfo.showToggle;
        this.canGoToNextRes = this.data.additionalInfo.canGoToNextRes;
        this.inLocalStorage = this.data.additionalInfo.inLocalStorage;
        await this.checkMultigest();
        this.loading = false;
    }

    checkMultigest() {
        this.resourcesErrors = [];

        return new Promise((resolve, reject) => {
            this.http.post('../rest/resourcesList/users/' + this.data.userId + '/groups/' + this.data.groupId + '/baskets/' + this.data.basketId + '/actions/' + this.data.action.id + '/checkSendMultigest', { resources: this.data.resIds })
                .subscribe((data: any) => {
                    if (!this.functions.empty(data.fatalError)) {
                        this.notify.error(this.translate.instant('lang.' + data.reason));
                        this.dialogRef.close();
                    } else if (!this.functions.empty(data.resourcesInformations.error)) {
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

        if (this.data.resIds.length > 0) {
            this.sessionStorage.checkSessionStorage(this.inLocalStorage, this.canGoToNextRes, this.data);
            this.executeAction();
        }
    }

    executeAction() {

        const realResSelected: number[] = this.data.resIds.filter((resId: any) => this.resourcesErrors.map(resErr => resErr.res_id).indexOf(resId) === -1);

        this.http.put(this.data.processActionRoute, { resources: realResSelected, note: this.noteEditor.getNote()}).pipe(
            tap((data: any) => {
                if (!data) {
                    this.dialogRef.close('success');
                }
                if (data && data.errors != null) {
                    this.notify.error(data.errors);
                }
            }),
            finalize(() => this.loading = false),
            catchError((err: any) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    isValidAction() {
        return !this.noResourceToProcess;
    }

}
