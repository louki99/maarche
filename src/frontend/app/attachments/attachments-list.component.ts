import { Component, OnInit, Output, Input, EventEmitter } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { TranslateService } from '@ngx-translate/core';
import { NotificationService } from '@service/notification/notification.service';
import { tap, finalize, catchError, filter, exhaustMap } from 'rxjs/operators';
import { of } from 'rxjs';
import { MatDialog, MatDialogRef } from '@angular/material/dialog';
import { trigger, transition, style, animate } from '@angular/animations';
import { AttachmentPageComponent } from './attachments-page/attachment-page.component';
import { AttachmentCreateComponent } from './attachment-create/attachment-create.component';
import { ConfirmComponent } from '../../plugins/modal/confirm.component';
import { PrivilegeService } from '@service/privileges.service';
import { HeaderService } from '@service/header.service';
import { VisaWorkflowModalComponent } from '../visa/modal/visa-workflow-modal.component';
import { AppService } from '@service/app.service';
import { ExternalSignatoryBookManagerService } from '@service/externalSignatoryBook/external-signatory-book-manager.service';
import { FunctionsService } from '@service/functions.service';

@Component({
    selector: 'app-attachments-list',
    templateUrl: 'attachments-list.component.html',
    styleUrls: ['attachments-list.component.scss'],
    providers: [ExternalSignatoryBookManagerService],
    animations: [
        trigger(
            'myAnimation',
            [
                transition(
                    ':enter', [
                        style({ transform: 'translateY(-10%)', opacity: 0 }),
                        animate('150ms', style({ transform: 'translateY(0)', 'opacity': 1 }))
                    ]
                ),
                transition(
                    ':leave', [
                        style({ transform: 'translateY(0)', 'opacity': 1 }),
                        animate('150ms', style({ transform: 'translateY(-10%)', 'opacity': 0 })),
                    ]
                )]
        )
    ],
})
export class AttachmentsListComponent implements OnInit {


    @Input() injectDatas: any;
    @Input() resId: number = null;
    @Input() target: string = 'panel';
    @Input() autoOpenCreation: boolean = false;
    @Input() canModify: boolean = null;
    @Output() reloadBadgeAttachments = new EventEmitter<string>();

    @Output() afterActionAttachment = new EventEmitter<string>();

    attachments: any;
    loading: boolean = true;
    pos = 0;
    mailevaEnabled: boolean = false;
    hideMainInfo: boolean = false;

    filterAttachTypes: any[] = [];
    currentFilter: string = '';

    dialogRef: MatDialogRef<any>;


    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        public dialog: MatDialog,
        public appService: AppService,
        public externalSignatoryBook: ExternalSignatoryBookManagerService,
        public functions: FunctionsService,
        private notify: NotificationService,
        private headerService: HeaderService,
        private privilegeService: PrivilegeService
    ) { }

    ngOnInit(): void {
        if (this.autoOpenCreation) {
            this.createAttachment();
        }

        if (this.resId !== null) {
            this.http.get(`../rest/resources/${this.resId}/attachments`).pipe(
                tap((data: any) => {
                    this.mailevaEnabled = data.mailevaEnabled;
                    this.attachments = data.attachments;
                    this.attachments.forEach((element: any) => {
                        if (this.filterAttachTypes.filter(attachType => attachType.id === element.type).length === 0) {
                            this.filterAttachTypes.push({
                                id: element.type,
                                label: element.typeLabel
                            });
                        }
                        element.thumbnailUrl = '../rest/attachments/' + element.resId + '/thumbnail';
                        element.canDelete = this.privilegeService.hasCurrentUserPrivilege('manage_attachments') || this.headerService.user.id === element.typist;
                    });
                }),
                finalize(() => this.loading = false),
                catchError((err: any) => {
                    this.notify.handleErrors(err);
                    return of(false);
                })
            ).subscribe();
        }
    }

    loadAttachments(resId: number) {
        const timeStamp = +new Date();
        this.resId = resId;
        this.loading = true;
        this.filterAttachTypes = [];
        this.http.get('../rest/resources/' + this.resId + '/attachments')
            .subscribe((data: any) => {
                this.mailevaEnabled = data.mailevaEnabled;
                this.attachments = data.attachments;
                this.attachments.forEach((element: any) => {
                    if (this.filterAttachTypes.filter(attachType => attachType.id === element.type).length === 0) {
                        this.filterAttachTypes.push({
                            id: element.type,
                            label: element.typeLabel
                        });
                    }
                    element.thumbnailUrl = '../rest/attachments/' + element.resId + '/thumbnail?tsp=' + timeStamp;
                    element.canDelete = this.privilegeService.hasCurrentUserPrivilege('manage_attachments') || this.headerService.user.id === element.typist;
                });
                if (this.attachments.filter((attach: any) => attach.type === this.currentFilter).length === 0) {
                    this.currentFilter = '';
                }
                this.reloadBadgeAttachments.emit(`${this.attachments.length}`);
                this.loading = false;
            }, (err: any) => {
                this.notify.error(err.error.errors);
            });
    }

    setInSignatureBook(attachment: any) {
        this.http.put('../rest/attachments/' + attachment.resId + '/inSignatureBook', {})
            .subscribe(() => {
                attachment.inSignatureBook = !attachment.inSignatureBook;
                this.afterActionAttachment.emit('setInSignatureBook');
                this.notify.success(this.translate.instant('lang.actionDone'));
            }, (err: any) => {
                this.notify.error(err.error.errors);
            });
    }

    setInSendAttachment(attachment: any) {
        this.http.put('../rest/attachments/' + attachment.resId + '/inSendAttachment', {})
            .subscribe(() => {
                attachment.inSendAttach = !attachment.inSendAttach;
                this.afterActionAttachment.emit('setInSendAttachment');
                this.notify.success(this.translate.instant('lang.actionDone'));
            }, (err: any) => {
                this.notify.error(err.error.errors);
            });
    }

    toggleInfo(attachment: any, state: boolean) {
        this.attachments.forEach((element: any) => {
            element.hideMainInfo = false;
        });
        attachment.hideMainInfo = state;
    }

    resetToggleInfo() {
        this.attachments.forEach((element: any) => {
            element.hideMainInfo = false;
        });
    }

    showAttachment(attachment: any) {
        this.dialogRef = this.dialog.open(AttachmentPageComponent, { height: '99vh', width: this.appService.getViewMode() ? '99vw' : '90vw', maxWidth: this.appService.getViewMode() ? '99vw' : '90vw', panelClass: 'attachment-modal-container', disableClose: true, data: { resId: attachment.resId, editMode : this.canModify } });

        this.dialogRef.afterClosed().pipe(
            filter((data: string) => data === 'success'),
            tap(() => {
                this.loadAttachments(this.resId);
            }),
            catchError((err: any) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    createAttachment() {
        this.dialogRef = this.dialog.open(AttachmentCreateComponent, { disableClose: true, panelClass: 'attachment-modal-container', height: '90vh', width: this.appService.getViewMode() ? '99vw' : '90vw', maxWidth: this.appService.getViewMode() ? '99vw' : '90vw', data: { resIdMaster: this.resId } });

        this.dialogRef.afterClosed().pipe(
            filter((data: string) => data === 'success'),
            tap(() => {
                this.loadAttachments(this.resId);
                this.afterActionAttachment.emit('setInSendAttachment');
            }),
            catchError((err: any) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    deleteAttachment(attachment: any) {
        const dialogRef = this.dialog.open(ConfirmComponent, { panelClass: 'maarch-modal', autoFocus: false, disableClose: true, data: { title: this.translate.instant('lang.delete'), msg: this.translate.instant('lang.confirmAction') } });

        dialogRef.afterClosed().pipe(
            filter((data: string) => data === 'ok'),
            exhaustMap(() => this.http.delete(`../rest/attachments/${attachment.resId}`)),
            tap(() => {
                this.loadAttachments(this.resId);
                this.afterActionAttachment.emit('setInSendAttachment');
                this.notify.success(this.translate.instant('lang.attachmentDeleted'));
            }),
            catchError((err: any) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    filterType(ev: any) {
        this.currentFilter = ev.value;
    }

    openExternalSignatoryBookWorkflow(attachment: any) {
        this.dialog.open(VisaWorkflowModalComponent, {
            panelClass: 'maarch-modal',
            data: {
                id: attachment.resId,
                type: 'attachment',
                title: this.translate.instant(`lang.${this.externalSignatoryBook.signatoryBookEnabled}Workflow`),
                linkedToExternalSignatoryBook: true
            }
        });
    }

    getTitle(): string {
        return !this.externalSignatoryBook.canViewWorkflow() ? this.translate.instant('lang.unavailableForSignatoryBook') : this.translate.instant('lang.' + this.externalSignatoryBook.signatoryBookEnabled + 'Workflow');
    }
}
