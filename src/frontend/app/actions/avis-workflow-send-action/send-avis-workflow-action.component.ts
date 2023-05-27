import { Component, OnInit, Inject, ViewChild, AfterViewInit } from '@angular/core';
import { TranslateService } from '@ngx-translate/core';
import { NotificationService } from '@service/notification/notification.service';
import { MAT_DIALOG_DATA, MatDialogRef } from '@angular/material/dialog';
import { HttpClient } from '@angular/common/http';
import { NoteEditorComponent } from '../../notes/note-editor.component';
import { tap, finalize, catchError } from 'rxjs/operators';
import { of } from 'rxjs';
import { FunctionsService } from '@service/functions.service';
import { AvisWorkflowComponent } from '../../avis/avis-workflow.component';
import { SessionStorageService } from '@service/session-storage.service';

@Component({
    templateUrl: 'send-avis-workflow-action.component.html',
    styleUrls: ['send-avis-workflow-action.component.scss'],
})
export class SendAvisWorkflowComponent implements AfterViewInit {

    @ViewChild('noteEditor', { static: true }) noteEditor: NoteEditorComponent;
    @ViewChild('appAvisWorkflow', { static: false }) appAvisWorkflow: AvisWorkflowComponent;

    loading: boolean = false;

    resourcesError: any[] = [];

    noResourceToProcess: boolean = null;

    opinionLimitDate: Date = null;

    today: Date = new Date();

    canGoToNextRes: boolean = false;
    showToggle: boolean = false;
    inLocalStorage: boolean = false;

    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        private notify: NotificationService,
        public dialogRef: MatDialogRef<SendAvisWorkflowComponent>,
        @Inject(MAT_DIALOG_DATA) public data: any,
        public functions: FunctionsService,
        private sessionStorage: SessionStorageService
    ) { }

    async ngAfterViewInit(): Promise<void> {
        this.showToggle = this.data.additionalInfo.showToggle;
        this.canGoToNextRes = this.data.additionalInfo.canGoToNextRes;
        this.inLocalStorage = this.data.additionalInfo.inLocalStorage;
        if (this.data.resIds.length === 1) {
            await this.appAvisWorkflow.loadWorkflow(this.data.resIds[0]);
            if (this.appAvisWorkflow.emptyWorkflow()) {
                this.appAvisWorkflow.loadDefaultWorkflow(this.data.resIds[0]);
            }
        }
    }

    async onSubmit() {
        this.loading = true;
        if (this.data.resIds.length === 0) {
            let res = await this.indexDocument();
            if (res) {
                res = await this.appAvisWorkflow.saveAvisWorkflow(this.data.resIds);
            }
            if (res) {
                this.executeAction(this.data.resIds);
            }
        } else {
            const realResSelected: number[] = this.data.resIds.filter((resId: any) => this.resourcesError.map(resErr => resErr.res_id).indexOf(resId) === -1);
            const res = await this.appAvisWorkflow.saveAvisWorkflow(realResSelected);
            if (res) {
                this.sessionStorage.checkSessionStorage(this.inLocalStorage, this.canGoToNextRes, this.data);
                this.executeAction(realResSelected);
            }
        }
        this.loading = false;
    }

    indexDocument() {
        return new Promise((resolve, reject) => {
            this.http.post('../rest/resources', this.data.resource).pipe(
                tap((data: any) => {
                    this.data.resIds = [data.resId];
                    resolve(true);
                }),
                catchError((err: any) => {
                    this.notify.handleErrors(err);
                    resolve(false);
                    return of(false);
                })
            ).subscribe();
        });
    }

    executeAction(realResSelected: number[]) {
        const noteContent: string = `[${this.translate.instant('lang.avisUserAsk').toUpperCase()}] ${this.noteEditor.getNoteContent()}`;
        this.noteEditor.setNoteContent(noteContent);
        this.http.put(this.data.processActionRoute, { resources: realResSelected, note: this.noteEditor.getNote(), data: { opinionLimitDate: this.functions.formatDateObjectToDateString(this.opinionLimitDate, true) } }).pipe(
            tap((data: any) => {
                if (!data) {
                    this.dialogRef.close(realResSelected);
                }
                if (data && data.errors != null) {
                    this.notify.error(data.errors);
                }
            }),
            finalize(() => this.loading = false),
            catchError((err: any) => {
                this.notify.handleSoftErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    isValidAction() {
        return !this.noResourceToProcess && this.appAvisWorkflow !== undefined && !this.appAvisWorkflow.emptyWorkflow() && !this.appAvisWorkflow.workflowEnd() && !this.functions.empty(this.noteEditor.getNoteContent()) && !this.functions.empty(this.functions.formatDateObjectToDateString(this.opinionLimitDate));
    }
}
