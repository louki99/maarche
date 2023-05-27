import { Component, OnInit, Inject, ViewChild } from '@angular/core';
import { TranslateService } from '@ngx-translate/core';
import { NotificationService } from '@service/notification/notification.service';
import { MAT_DIALOG_DATA, MatDialogRef } from '@angular/material/dialog';
import { HttpClient } from '@angular/common/http';
import { NoteEditorComponent } from '../../notes/note-editor.component';
import { tap, exhaustMap, catchError, finalize } from 'rxjs/operators';
import { of } from 'rxjs';
import { FunctionsService } from '@service/functions.service';
import { SessionStorageService } from '@service/session-storage.service';

@Component({
    templateUrl: 'print-registered-mail-action.component.html',
    styleUrls: ['print-registered-mail-action.component.scss'],
})
export class PrintRegisteredMailActionComponent implements OnInit {

    @ViewChild('noteEditor', { static: true }) noteEditor: NoteEditorComponent;

    loading: boolean = false;

    canGoToNextRes: boolean = false;
    showToggle: boolean = false;
    inLocalStorage: boolean = false;

    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        private notify: NotificationService,
        private functions: FunctionsService,
        public dialogRef: MatDialogRef<PrintRegisteredMailActionComponent>,
        @Inject(MAT_DIALOG_DATA) public data: any,
        private sessionStorage: SessionStorageService
    ) { }

    ngOnInit(): void {
        this.showToggle = this.data.additionalInfo.showToggle;
        this.canGoToNextRes = this.data.additionalInfo.canGoToNextRes;
        this.inLocalStorage = this.data.additionalInfo.inLocalStorage;
    }

    onSubmit() {
        this.loading = true;
        this.sessionStorage.checkSessionStorage(this.inLocalStorage, this.canGoToNextRes, this.data);
        this.executeAction();
    }

    executeAction() {
        const downloadLink = document.createElement('a');
        this.http.put(this.data.processActionRoute, { resources: this.data.resIds, note: this.noteEditor.getNote() }).pipe(
            tap((data: any) => {
                if (data && data.errors != null) {
                    this.notify.error(data.errors);
                } else {
                    Object.values(data.data).forEach((encodedFile: string) => {
                        if (!this.functions.empty(encodedFile)) {
                            let filenameDetail: string;
                            downloadLink.href = `data:application/pdf;base64,${encodedFile}`;
                            if (this.data.resIds.length === 1) {
                                filenameDetail = this.data.resource.chrono.split(' ').join('_');
                                downloadLink.setAttribute('download', this.translate.instant('lang.registeredMail') + '_' + filenameDetail + '.pdf');
                            } else {
                                downloadLink.setAttribute('download', this.functions.getFormatedFileName(this.translate.instant('lang.registeredMail'), 'pdf'));
                            }
                            document.body.appendChild(downloadLink);
                            downloadLink.click();
                            this.dialogRef.close(this.data.resIds);
                        }
                    });
                }
            }),
            finalize(() => this.loading = false),
            catchError((err: any) => {
                this.notify.handleSoftErrors(err);
                return of(false);
            })
        ).subscribe();
    }
}
