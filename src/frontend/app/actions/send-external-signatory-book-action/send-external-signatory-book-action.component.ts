import { Component, OnInit, Inject, ViewChild, ChangeDetectorRef } from '@angular/core';
import { TranslateService } from '@ngx-translate/core';
import { NotificationService } from '@service/notification/notification.service';
import { MAT_DIALOG_DATA, MatDialogRef } from '@angular/material/dialog';
import { HttpClient } from '@angular/common/http';
import { NoteEditorComponent } from '../../notes/note-editor.component';
import { XParaphComponent } from './x-paraph/x-paraph.component';
import { MaarchParaphComponent } from './maarch-paraph/maarch-paraph.component';
import { IParaphComponent } from './i-paraph/i-paraph.component';
import { IxbusParaphComponent } from './ixbus-paraph/ixbus-paraph.component';
import { tap, finalize, catchError } from 'rxjs/operators';
import { of } from 'rxjs';
import { SessionStorageService } from '@service/session-storage.service';
import { ExternalSignatoryBookManagerService } from '@service/externalSignatoryBook/external-signatory-book-manager.service';
import { FunctionsService } from '@service/functions.service';
import { FastParaphComponent } from './fast-paraph/fast-paraph.component';
import { AuthService } from '@service/auth.service';

@Component({
    templateUrl: 'send-external-signatory-book-action.component.html',
    styleUrls: ['send-external-signatory-book-action.component.scss'],
    providers: [ExternalSignatoryBookManagerService]
})
export class SendExternalSignatoryBookActionComponent implements OnInit {

    @ViewChild('noteEditor', { static: false }) noteEditor: NoteEditorComponent;

    @ViewChild('xParaph', { static: false }) xParaph: XParaphComponent;
    @ViewChild('externalSignatoryBookComponent', { static: false }) externalSignatoryBookComponent: MaarchParaphComponent;
    @ViewChild('fastParapheur', { static: false}) fastParapheur: FastParaphComponent;
    @ViewChild('iParapheur', { static: false }) iParapheur: IParaphComponent;
    @ViewChild('ixbus', { static: false }) ixbus: IxbusParaphComponent;

    loading: boolean = false;

    additionalsInfos: any = {
        destinationId: '',
        users: [],
        attachments: [],
        noAttachment: []
    };
    resourcesToSign: any[] = [];
    resourcesMailing: any[] = [];

    externalSignatoryBookDatas: any = {
        steps: [],
        objectSent: 'attachment'
    };

    integrationsInfo: any = {
        inSignatureBook: {
            icon: 'fas fa-file-signature'
        }
    };

    errors: any;

    mainDocumentSigned: boolean = false;

    canGoToNextRes: boolean = false;
    showToggle: boolean = false;
    inLocalStorage: boolean = false;

    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        public dialogRef: MatDialogRef<SendExternalSignatoryBookActionComponent>,
        public externalSignatoryBook: ExternalSignatoryBookManagerService,
        public functions: FunctionsService,
        public authService: AuthService,
        @Inject(MAT_DIALOG_DATA) public data: any,
        private notify: NotificationService,
        private changeDetectorRef: ChangeDetectorRef,
        private sessionStorage: SessionStorageService
    ) { }

    async ngOnInit(): Promise<void> {
        this.loading = true;
        if (!this.functions.empty(this.authService?.externalSignatoryBook)) {
            await this.checkExternalSignatureBook();
            this.showToggle = this.data.additionalInfo.showToggle;
            this.canGoToNextRes = this.data.additionalInfo.canGoToNextRes;
            this.inLocalStorage = this.data.additionalInfo.inLocalStorage;
            if (this.data.resource.integrations['inSignatureBook']) {
                this.http.get(`../rest/resources/${this.data.resource.resId}/versionsInformations`).pipe(
                    tap((data: any) => {
                        this.mainDocumentSigned = data.SIGN.length !== 0;
                        if (!this.mainDocumentSigned) {
                            this.toggleDocToSign(true, this.data.resource, true);
                        }
                    }),
                    catchError((err: any) => {
                        this.notify.handleSoftErrors(err);
                        return of(false);
                    })
                ).subscribe();
            }
        } else {
            this.dialogRef.close();
            this.notify.handleSoftErrors(this.translate.instant('lang.externalSignoryBookNotEnabled'));
            this.loading = false;
        }
    }

    async onSubmit() {
        if (this.hasEmptyOtpSignaturePosition()) {
            this.notify.error(this.translate.instant('lang.mustSign'));
        } else {
            this.loading = true;
            if (this.data.resIds.length > 0) {
                this.sessionStorage.checkSessionStorage(this.inLocalStorage, this.canGoToNextRes, this.data);
                this.executeAction();
            }
        }
    }

    async checkExternalSignatureBook() {
        this.loading = true;
        const data: any = await this.externalSignatoryBook.checkExternalSignatureBook(this.data);
        if (!this.functions.empty(data)) {
            this.additionalsInfos = data.additionalsInfos;
            if (this.additionalsInfos.attachments.length > 0) {
                this.resourcesMailing = data.additionalsInfos.attachments.filter((element: any) => element.mailing);
                data.availableResources.filter((element: any) => !element.mainDocument).forEach((element: any) => {
                    this.toggleDocToSign(true, element, false);
                });
            }
            this.errors = data.errors;
        } else {
            this.dialogRef.close();
        }
        this.loading = false;
    }

    executeAction() {
        let realResSelected: string[];
        let datas: any;
        if (this.functions.empty(this.externalSignatoryBook.signatoryBookEnabled)) {
            realResSelected = this[this.authService.externalSignatoryBook.id].getRessources();
            datas = this[this.authService.externalSignatoryBook.id].getDatas();
        } else {
            realResSelected = this.externalSignatoryBook.getRessources(this.additionalsInfos);
            const workflow: any[] = this.externalSignatoryBookComponent.appExternalVisaWorkflow.getWorkflow();
            datas = this.externalSignatoryBook.getDatas(workflow, this.resourcesToSign, this.externalSignatoryBookComponent.appExternalVisaWorkflow.workflowType);
        }

        this.http.put(this.data.processActionRoute, { resources: realResSelected, note: this.noteEditor.getNote(), data: datas }).pipe(
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

    isValidAction(): boolean {
        if (!this.functions.empty(this.externalSignatoryBook.signatoryBookEnabled) && this.authService.externalSignatoryBook.integratedWorkflow) {
            return this.externalSignatoryBook.isValidParaph(
                this.additionalsInfos,
                this.externalSignatoryBookComponent?.appExternalVisaWorkflow.getWorkflow(),
                this.resourcesToSign,
                this.externalSignatoryBookComponent?.appExternalVisaWorkflow.getUserOtpsWorkflow()
            );
        } else {
            if (this[this.authService.externalSignatoryBook?.id] !== undefined) {
                return this[this.authService.externalSignatoryBook?.id].isValidParaph();
            } else {
                return false;
            }
        }
    }

    toggleIntegration(integrationId: string) {
        this.resourcesToSign = [];
        this.http.put('../rest/resourcesList/integrations', { resources: this.data.resIds, integrations: { [integrationId]: !this.data.resource.integrations[integrationId] } }).pipe(
            tap(async () => {
                this.data.resource.integrations[integrationId] = !this.data.resource.integrations[integrationId];

                if (!this.mainDocumentSigned) {
                    this.toggleDocToSign(this.data.resource.integrations[integrationId], this.data.resource, true);
                }
                await this.checkExternalSignatureBook();
                this.changeDetectorRef.detectChanges();
            }),
            catchError((err: any) => {
                this.notify.handleSoftErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    toggleDocToSign(state: boolean, document: any, mainDocument: boolean = true) {
        if (state) {
            this.resourcesToSign.push(
                {
                    resId: document.resId,
                    chrono: document.chrono,
                    title: document.subject,
                    mainDocument: mainDocument,
                });
        } else {
            const index = this.resourcesToSign.map((item: any) => `${item.resId}_${item.mainDocument}`).indexOf(`${document.resId}_${mainDocument}`);
            this.resourcesToSign.splice(index, 1);
        }
    }

    hasEmptyOtpSignaturePosition(): boolean {
        if (this.authService.externalSignatoryBook.integratedWorkflow && this.externalSignatoryBook.allowedSignatoryBook.indexOf(this.authService.externalSignatoryBook?.id) > -1) {
            const externalUsers: any[] = this.externalSignatoryBookComponent.appExternalVisaWorkflow.visaWorkflow.items.filter((user: any) => user.item_id === null && user.role === 'sign' && user.externalInformations.type !== 'fast');
            if (externalUsers.length > 0) {
                let state: boolean = false;
                this.resourcesToSign.forEach((resource: any) => {
                    if (this.externalSignatoryBookComponent.appExternalVisaWorkflow.hasOtpNoSignaturePositionFromResource(resource)) {
                        state = true;
                    }
                });
                return state;
            }
        } else {
            return false;
        }
    }

    getTitle(): string {
        return this.authService.externalSignatoryBook !== null ? this.translate.instant('lang.' + this.authService.externalSignatoryBook.id) : this.translate.instant('lang.sendToExternalSignatoryBook');
    }
}
