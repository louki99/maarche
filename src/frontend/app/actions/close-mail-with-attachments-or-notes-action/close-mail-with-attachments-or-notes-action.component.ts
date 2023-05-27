import { Component, OnInit, Inject, ViewChild } from '@angular/core';
import { TranslateService } from '@ngx-translate/core';
import { NotificationService } from '@service/notification/notification.service';
import { MAT_DIALOG_DATA, MatDialogRef } from '@angular/material/dialog';
import { HttpClient } from '@angular/common/http';
import { NoteEditorComponent } from '../../notes/note-editor.component';
import { tap, finalize, catchError } from 'rxjs/operators';
import { of } from 'rxjs';
import { SessionStorageService } from '@service/session-storage.service';

@Component({
    templateUrl: 'close-mail-with-attachments-or-notes-action.component.html',
    styleUrls: ['close-mail-with-attachments-or-notes-action.component.scss'],
})
export class closeMailWithAttachmentsOrNotesActionComponent implements OnInit {

    @ViewChild('noteEditor', { static: false }) noteEditor: NoteEditorComponent;

    loading: boolean = false;
    loadingInit: boolean = false;
    resourcesInfo: any = {
        withEntity: [],
        withoutEntity: []
    };

    loadingExport: boolean;

    canGoToNextRes: boolean = false;
    showToggle: boolean = false;
    inLocalStorage: boolean = false;

    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        private notify: NotificationService,
        public dialogRef: MatDialogRef<closeMailWithAttachmentsOrNotesActionComponent>,
        @Inject(MAT_DIALOG_DATA) public data: any,
        private sessionStorage: SessionStorageService,
    ) { }

    ngOnInit(): void {
        this.loadingInit = true;
        this.showToggle = this.data.additionalInfo.showToggle;
        this.canGoToNextRes = this.data.additionalInfo.canGoToNextRes;
        this.inLocalStorage = this.data.additionalInfo.inLocalStorage;
        this.http.post('../rest/resourcesList/users/' + this.data.userId + '/groups/' + this.data.groupId + '/baskets/' + this.data.basketId + '/checkAttachmentsAndNotes', { resources: this.data.resIds })
            .pipe(
                tap(( data: any) => {
                    this.resourcesInfo = data;
                    this.loadingInit = false;
                }),
                catchError((err: any) => {
                    this.notify.handleSoftErrors(err.error.errors);
                    this.loadingInit = false;
                    return of(false);
                })
            ).subscribe();
    }

    onSubmit() {
        this.loading = true;
        this.sessionStorage.checkSessionStorage(this.inLocalStorage, this.canGoToNextRes, this.data);
        this.executeAction();
    }

    checkNote () {
        if (this.noteEditor) {
            if (this.noteEditor.getNoteContent()) {
                return true;
            }
        }
        return false;
    }

    executeAction() {
        this.http.put(this.data.processActionRoute, { resources: this.data.resIds, note: this.noteEditor.getNote() }).pipe(
            tap((data: any) => {
                if (data && data.errors != null) {
                    this.notify.error(data.errors);
                }
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
