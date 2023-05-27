import { Component, OnInit, Inject, ViewChild } from '@angular/core';
import { TranslateService } from '@ngx-translate/core';
import { NotificationService } from '@service/notification/notification.service';
import { MAT_DIALOG_DATA, MatDialogRef } from '@angular/material/dialog';
import { HttpClient } from '@angular/common/http';
import { NoteEditorComponent } from '../../notes/note-editor.component';
import { tap, finalize, catchError } from 'rxjs/operators';
import { of } from 'rxjs';
import { FunctionsService } from '@service/functions.service';
import { HeaderService } from '@service/header.service';
import { SessionStorageService } from '@service/session-storage.service';

@Component({
    templateUrl: 'give-avis-parallel-action.component.html',
    styleUrls: ['give-avis-parallel-action.component.scss'],
})
export class GiveAvisParallelActionComponent implements OnInit {

    @ViewChild('noteEditor', { static: true }) noteEditor: NoteEditorComponent;

    loading: boolean = false;

    resourcesWarnings: any[] = [];
    resourcesErrors: any[] = [];

    noResourceToProcess: boolean = null;

    opinionLimitDate: string | Date = null;

    ownerOpinion: string = '';
    opinionContent: string = '';

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
        public dialogRef: MatDialogRef<GiveAvisParallelActionComponent>,
        @Inject(MAT_DIALOG_DATA) public data: any,
        public functions: FunctionsService,
        public headerService: HeaderService,
        private sessionStorage: SessionStorageService
    ) { }

    ngOnInit() {
        this.showToggle = this.data.additionalInfo.showToggle;
        this.canGoToNextRes = this.data.additionalInfo.canGoToNextRes;
        this.inLocalStorage = this.data.additionalInfo.inLocalStorage;
        this.checkAvisParallel();
    }

    checkAvisParallel() {
        this.loading = true;
        this.resourcesErrors = [];
        this.resourcesWarnings = [];

        this.http.post('../rest/resourcesList/users/' + this.data.userId + '/groups/' + this.data.groupId + '/baskets/' + this.data.basketId + '/actions/' + this.data.action.id + '/checkGiveParallelOpinion', { resources: this.data.resIds }).pipe(
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
                    this.opinionLimitDate = this.functions.formatDateObjectToDateString(this.opinionLimitDate);
                }
                const userId: number = parseInt(this.data.userId, 10);
                this.delegation.isDelegated = userId !== this.headerService.user.id ? true : false;
                if (this.delegation.isDelegated && !this.noResourceToProcess) {
                    this.delegation.userDelegated = data.resourcesInformations.success[0].delegatingUser;
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

    onSubmit() {
        const realResSelected: number[] = this.data.resIds.filter((resId: any) => this.resourcesErrors.map(resErr => resErr.res_id).indexOf(resId) === -1);
        this.sessionStorage.checkSessionStorage(this.inLocalStorage, this.canGoToNextRes, this.data);
        this.executeAction(realResSelected);
    }

    executeAction(realResSelected: number[]) {
        const opinionUserState: string = this.translate.instant('lang.delegatedOpinion').concat(' ', this.delegation.userDelegated);
        const noteContent: string = this.delegation.isDelegated ? `[${this.translate.instant('lang.opinionUserState')}] ${this.noteEditor.getNoteContent()} ← ${opinionUserState}` : `[${this.translate.instant('lang.opinionUserState')}] ${this.noteEditor.getNoteContent()}`;
        this.noteEditor.setNoteContent(noteContent);
        this.http.put(this.data.processActionRoute, { resources: realResSelected, note: this.noteEditor.getNote()}).pipe(
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
        return !this.noResourceToProcess && !this.functions.empty(this.noteEditor.getNoteContent());
    }
}
