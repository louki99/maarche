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
import { HeaderService } from '@service/header.service';
import { SessionStorageService } from '@service/session-storage.service';

@Component({
    templateUrl: 'validate-avis-parallel-action.component.html',
    styleUrls: ['validate-avis-parallel-action.component.scss'],
})
export class ValidateAvisParallelComponent implements OnInit, AfterViewInit {

    @ViewChild('noteEditor', { static: false }) noteEditor: NoteEditorComponent;
    @ViewChild('appAvisWorkflow', { static: false }) appAvisWorkflow: AvisWorkflowComponent;

    loading: boolean = false;

    resourcesWarnings: any[] = [];
    resourcesErrors: any[] = [];

    ownerOpinion: string = '';
    opinionContent: string = '';

    noResourceToProcess: boolean = null;

    opinionLimitDate: Date = null;

    today: Date = new Date();

    availableRoles: any[] = [];

    delegation: any = {
        isDelegated: false,
        userDelegated: null
    };

    canGoToNextRes: boolean = false;
    showToggle: boolean = false;
    inLocalStorage: boolean = false;

    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        private notify: NotificationService,
        public dialogRef: MatDialogRef<ValidateAvisParallelComponent>,
        @Inject(MAT_DIALOG_DATA) public data: any,
        public functions: FunctionsService,
        private headerService: HeaderService,
        private sessionStorage: SessionStorageService
    ) { }

    ngOnInit() {
        this.showToggle = this.data.additionalInfo.showToggle;
        this.canGoToNextRes = this.data.additionalInfo.canGoToNextRes;
        this.inLocalStorage = this.data.additionalInfo.inLocalStorage;
        this.checkAvisCircuit();
        const userId: number = parseInt(this.data.userId, 10);
        if (userId !== this.headerService.user.id && !this.noResourceToProcess) {
            this.delegation.isDelegated = true;
            this.http.get('../rest/users/' + userId).pipe(
                tap((user: any) => {
                    this.delegation.userDelegated = `${user.firstname} ${user.lastname}`;
                })
            ).subscribe();
        }
    }

    checkAvisCircuit() {
        this.loading = true;
        this.resourcesErrors = [];
        this.resourcesWarnings = [];
        this.http.post('../rest/resourcesList/users/' + this.data.userId + '/groups/' + this.data.groupId + '/baskets/' + this.data.basketId + '/actions/' + this.data.action.id + '/checkValidateParallelOpinion', { resources: this.data.resIds }).pipe(
            tap((data: any) => {
                if (!this.functions.empty(data.resourcesInformations.warning)) {
                    this.resourcesWarnings = data.resourcesInformations.warning;
                }

                if (!this.functions.empty(data.resourcesInformations.error)) {
                    this.resourcesErrors = data.resourcesInformations.error;
                    this.noResourceToProcess = this.resourcesErrors.length === this.data.resIds.length;
                }

                if (!this.noResourceToProcess) {
                    this.ownerOpinion = data.resourcesInformations.success[0].avisUserAsk;
                    this.opinionContent = data.resourcesInformations.success[0].note;
                    this.opinionLimitDate = new Date(data.resourcesInformations.success[0].opinionLimitDate);
                }
            }),
            finalize(() => this.loading = false),
            catchError((err: any) => {
                this.notify.handleSoftErrors(err);
                this.dialogRef.close();
                return of(false);
            })
        ).subscribe();
    }

    async ngAfterViewInit(): Promise<void> {
        if (this.data.resIds.length === 1) {
            await this.appAvisWorkflow.loadParallelWorkflow(this.data.resIds[0]);
        }
    }

    async onSubmit() {
        const realResSelected: number[] = this.data.resIds.filter((resId: any) => this.resourcesErrors.map(resErr => resErr.res_id).indexOf(resId) === -1);
        this.sessionStorage.checkSessionStorage(this.inLocalStorage, this.canGoToNextRes, this.data);
        this.executeAction(realResSelected);

    }

    executeAction(realResSelected: number[]) {
        const insteadOfMsg: string = `${this.translate.instant('lang.insteadOf').replace(/^.{1}/g, this.translate.instant('lang.insteadOf')[0].toLowerCase())} ${this.delegation.userDelegated}`;
        const validatedOpinionMsg: string = `[${this.translate.instant('lang.avisUserAsk').toUpperCase()}] ${this.noteEditor.getNoteContent()} ← ${this.translate.instant('lang.validateBy')} ${this.headerService.user.firstname} ${this.headerService.user.lastname}`;
        const noteContent: string = !this.delegation.isDelegated ?  validatedOpinionMsg : `${validatedOpinionMsg} ${insteadOfMsg}`;
        this.noteEditor.setNoteContent(noteContent);
        this.http.put(this.data.processActionRoute, { resources: realResSelected, data: { note: this.noteEditor.getNote(), opinionLimitDate: this.functions.formatDateObjectToDateString(this.opinionLimitDate, true), opinionCircuit: this.appAvisWorkflow.getWorkflow() } }).pipe(
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
        if (this.data.resIds.length === 1) {
            return !this.noResourceToProcess && this.noteEditor !== undefined && this.appAvisWorkflow !== undefined && !this.appAvisWorkflow.emptyWorkflow() && !this.appAvisWorkflow.workflowEnd() && !this.functions.empty(this.noteEditor.getNoteContent()) && !this.functions.empty(this.functions.formatDateObjectToDateString(this.opinionLimitDate));
        } else {
            return !this.noResourceToProcess;
        }
    }
}
