import { Component, OnInit, Inject, ViewChild } from '@angular/core';
import { TranslateService } from '@ngx-translate/core';
import { NotificationService } from '@service/notification/notification.service';
import { MAT_DIALOG_DATA, MatDialogRef } from '@angular/material/dialog';
import { HttpClient } from '@angular/common/http';
import { NoteEditorComponent } from '../../notes/note-editor.component';
import { tap, finalize, catchError } from 'rxjs/operators';
import { of } from 'rxjs';
import { FunctionsService } from '@service/functions.service';
import { MailEditorComponent } from '@plugins/mail-editor/mail-editor.component';
import { SessionStorageService } from '@service/session-storage.service';

@Component({
    templateUrl: 'create-acknowledgement-receipt-action.component.html',
    styleUrls: ['create-acknowledgement-receipt-action.component.scss'],
})
export class CreateAcknowledgementReceiptActionComponent implements OnInit {

    @ViewChild('appMailEditor', { static: false }) appMailEditor: MailEditorComponent;
    @ViewChild('noteEditor', { static: false }) noteEditor: NoteEditorComponent;

    loading: boolean = true;

    acknowledgement: any = {
        alReadyGenerated: {},
        alReadySend: {},
        noSendAR: {},
        sendEmail: 0,
        sendPaper: 0,
        sendList: []
    };

    realResSelected: number[] = [];
    currentMode: string = '';

    manualAR: boolean = false;
    arMode: 'auto' | 'manual' | 'both' = 'auto';
    canAddCopies: boolean  = false;

    senders: any[] = [];

    loadingExport: boolean;

    canGoToNextRes: boolean = false;
    showToggle: boolean = false;
    inLocalStorage: boolean = false;

    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        private notify: NotificationService,
        public dialogRef: MatDialogRef<CreateAcknowledgementReceiptActionComponent>,
        public functions: FunctionsService,
        @Inject(MAT_DIALOG_DATA) public data: any,
        private sessionStorage: SessionStorageService,
    ) { }

    async ngOnInit(): Promise<void> {
        this.showToggle = this.data.additionalInfo.showToggle;
        this.canGoToNextRes = this.data.additionalInfo.canGoToNextRes;
        this.inLocalStorage = this.data.additionalInfo.inLocalStorage;
        await this.checkAcknowledgementReceipt();
        this.loading = false;
    }

    startLoader() {
        document.getElementById('modal-content').scrollTo(0, 0);
        document.getElementById('modal-content').classList.add('no-scroll');
        this.loading = true;
    }

    endLoader() {
        document.getElementById('modal-content').classList.remove('no-scroll');
        this.loading = false;
    }

    checkAcknowledgementReceipt() {
        return new Promise((resolve) => {
            this.http.post('../rest/resourcesList/users/' + this.data.userId + '/groups/' + this.data.groupId + '/baskets/' + this.data.basketId + '/actions/' + this.data.action.id + '/checkAcknowledgementReceipt?' + this.currentMode, { resources: this.data.resIds }).pipe(
                tap((data: any) => {
                    this.acknowledgement = data;
                    this.realResSelected = data.sendList;
                    this.arMode = data.mode;
                    this.canAddCopies = data.canAddCopies;
                    this.senders = data.emailSenders;
                    if (this.arMode === 'manual') {
                        this.toggleArManual(true);
                    }
                }),
                finalize(() => resolve(true)),
                catchError((err: any) => {
                    this.notify.error(err.error.errors);
                    this.dialogRef.close();
                    return of(false);
                })
            ).subscribe();
        });
    }

    onSubmit() {
        this.startLoader();
        if (this.data.resIds.length > 0) {
            this.sessionStorage.checkSessionStorage(this.inLocalStorage, this.canGoToNextRes, this.data);
            this.executeAction();
        }
    }

    executeAction() {
        let data = {};
        const emaildata = this.appMailEditor.formatEmail();
        if (this.manualAR) {
            if (this.functions.empty(emaildata.body)) {
                this.notify.error(this.translate.instant('lang.arContentIsEmpty'));
                this.loading = false;
                return false;
            }
            data = {
                subject : emaildata.object,
                content : emaildata.body,
                manual  : true
            };
        }
        if (this.canAddCopies) {
            data = {...data, sender : this.appMailEditor.getSender(), cc : this.appMailEditor.getCopies(), cci : this.appMailEditor.getInvisibleCopies()};
        }
        this.http.put(this.data.processActionRoute, { resources: this.realResSelected, note: this.noteEditor.getNote(), data }).pipe(
            tap((res: any) => {
                if (res && res.data != null) {
                    this.downloadAcknowledgementReceipt(res.data);
                }
                if (res && res.errors != null) {
                    this.notify.error(res.errors);
                }
                this.dialogRef.close(this.realResSelected);
            }),
            finalize(() => this.endLoader()),
            catchError((err: any) => {
                this.notify.handleSoftErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    downloadAcknowledgementReceipt(data: any) {
        this.loadingExport = true;
        this.http.post('../rest/acknowledgementReceipts', { 'resources': data }, { responseType: 'blob' })
            .subscribe((dataFile) => {
                const downloadLink = document.createElement('a');
                downloadLink.href = window.URL.createObjectURL(dataFile);
                downloadLink.setAttribute('download', this.functions.getFormatedFileName('acknowledgement_receipt_maarch', 'pdf'));
                document.body.appendChild(downloadLink);
                downloadLink.click();
                this.loadingExport = false;
            }, (err: any) => {
                this.notify.handleBlobErrors(err);
            });
    }

    toggleArManual(state: boolean) {
        if (state) {
            if (this.currentMode !== 'mode=manual') {
                this.currentMode = 'mode=manual';
                this.checkAcknowledgementReceipt();
            }
            this.manualAR = true;
        } else {
            this.currentMode = 'mode=auto';
            this.checkAcknowledgementReceipt();
            this.manualAR = false;
        }
    }
}
