import { Component, OnInit, Inject, ViewChild } from '@angular/core';
import { TranslateService } from '@ngx-translate/core';
import { NotificationService } from '@service/notification/notification.service';
import { MAT_DIALOG_DATA, MatDialogRef } from '@angular/material/dialog';
import { HttpClient } from '@angular/common/http';
import { NoteEditorComponent } from '../../notes/note-editor.component';
import { tap, exhaustMap, catchError, finalize } from 'rxjs/operators';
import { of } from 'rxjs';
import { SessionStorageService } from '@service/session-storage.service';
import { FunctionsService } from '@service/functions.service';

@Component({
    templateUrl: 'confirm-action.component.html',
    styleUrls: ['confirm-action.component.scss'],
})
export class ConfirmActionComponent implements OnInit {

    @ViewChild('noteEditor', { static: true }) noteEditor: NoteEditorComponent;

    loading: boolean = false;

    canGoToNextRes: boolean = false;
    showToggle: boolean = false;
    inLocalStorage: boolean = false;

    fillMandatoryFields: Array<any> = [];
    fillRequiredFields: any;
    customFields: Array<any> = [];

    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        private notify: NotificationService,
        public dialogRef: MatDialogRef<ConfirmActionComponent>,
        @Inject(MAT_DIALOG_DATA) public data: any,
        private sessionStorage: SessionStorageService,
        private functions: FunctionsService
    ) { }

    ngOnInit(): void {
        this.showToggle = this.data.additionalInfo.showToggle;
        this.canGoToNextRes = this.data.additionalInfo.canGoToNextRes;
        this.inLocalStorage = this.data.additionalInfo.inLocalStorage;
        if (this.data.resIds.length > 0) {
            this.loading = true;
            this.checkConfirm();
        } else {
            this.checkIndexingConfirm();
        }
    }

    checkIndexingConfirm() {
        this.http.get(`../rest/actions/${this.data.action.id}`).pipe(
            tap((data: any) => {
                this.fillRequiredFields = !this.functions.empty(data.action.parameters.fillRequiredFields) ? data.action.parameters.requiredFields : [];
            }),
            exhaustMap(() => this.http.get('../rest/customFields')),
            tap((data: any) => this.customFields = data.customFields),
            tap(() => {
                const fillFields: Array<any> = [];
                this.fillRequiredFields.forEach((element: any) => {
                    for (const key of Object.keys(this.data.resource.customFields)) {
                        if (element['id'] === 'indexingCustomField_' + key && this.functions.empty(this.data.resource.customFields[key])) {
                            fillFields.push(this.customFields.filter(elem => elem.id === key)[0].label);
                        }
                    }
                });
                if (!this.functions.empty(fillFields)) {
                    this.fillMandatoryFields.push({ fields: fillFields.join(', ') });
                }
            }),
            catchError((err: any) => {
                this.notify.handleSoftErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    checkConfirm() {
        this.http.post(`../rest/resourcesList/users/${this.data.userId}/groups/${this.data.groupId}/baskets/${this.data.basketId}/actions/${this.data.action.id}/checkConfirmWithFieldsAction`, { resources: this.data.resIds }).pipe(
            tap((data: any) => {
                this.fillMandatoryFields = data.fillCustomFields;
            }),
            finalize(() => this.loading = false),
            catchError((err: any) => {
                this.notify.handleSoftErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    onSubmit() {
        this.loading = true;
        if (this.data.resIds.length === 0) {
            this.indexDocumentAndExecuteAction();
        } else {
            this.sessionStorage.checkSessionStorage(this.inLocalStorage, this.canGoToNextRes, this.data);
            this.executeAction();
        }
    }

    indexDocumentAndExecuteAction() {
        this.http.post('../rest/resources', this.data.resource).pipe(
            tap((data: any) => {
                this.data.resIds = [data.resId];
            }),
            exhaustMap(() => this.http.put(this.data.indexActionRoute, { resource: this.data.resIds[0], note: this.noteEditor.getNote() })),
            tap(() => {
                this.dialogRef.close(this.data.resIds);
            }),
            finalize(() => this.loading = false),
            catchError((err: any) => {
                this.notify.handleSoftErrors(err);
                this.dialogRef.close();
                return of(false);
            })
        ).subscribe();
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
}
