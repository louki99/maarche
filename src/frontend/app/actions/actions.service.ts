import { Injectable, OnDestroy } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { TranslateService } from '@ngx-translate/core';
import { tap, catchError, filter, finalize, exhaustMap } from 'rxjs/operators';
import { of, Subject, Observable } from 'rxjs';
import { NotificationService } from '@service/notification/notification.service';
import { ConfirmActionComponent } from './confirm-action/confirm-action.component';
import { MatDialog } from '@angular/material/dialog';
import { CloseMailActionComponent } from './close-mail-action/close-mail-action.component';
import { RejectVisaBackToPrevousActionComponent } from './visa-reject-back-to-previous-action/reject-visa-back-to-previous-action.component';
import { ResetVisaActionComponent } from './visa-reset-action/reset-visa-action.component';
import { InterruptVisaActionComponent } from './visa-interrupt-action/interrupt-visa-action.component';
import { CloseAndIndexActionComponent } from './close-and-index-action/close-and-index-action.component';
import { UpdateAcknowledgementSendDateActionComponent } from './update-acknowledgement-send-date-action/update-acknowledgement-send-date-action.component';
import { CreateAcknowledgementReceiptActionComponent } from './create-acknowledgement-receipt-action/create-acknowledgement-receipt-action.component';
import { UpdateDepartureDateActionComponent } from './update-departure-date-action/update-departure-date-action.component';
import { DisabledBasketPersistenceActionComponent } from './disabled-basket-persistence-action/disabled-basket-persistence-action.component';
import { EnabledBasketPersistenceActionComponent } from './enabled-basket-persistence-action/enabled-basket-persistence-action.component';
import { ResMarkAsReadActionComponent } from './res-mark-as-read-action/res-mark-as-read-action.component';
import { ViewDocActionComponent } from './view-doc-action/view-doc-action.component';
import { SendExternalSignatoryBookActionComponent } from './send-external-signatory-book-action/send-external-signatory-book-action.component';
import { SendExternalNoteBookActionComponent } from './send-external-note-book-action/send-external-note-book-action.component';
import { RedirectActionComponent } from './redirect-action/redirect-action.component';
import { SendShippingActionComponent } from './send-shipping-action/send-shipping-action.component';
import { RedirectInitiatorEntityActionComponent } from './redirect-initiator-entity-action/redirect-initiator-entity-action.component';
import { closeMailWithAttachmentsOrNotesActionComponent } from './close-mail-with-attachments-or-notes-action/close-mail-with-attachments-or-notes-action.component';
import { Router } from '@angular/router';
import { SendSignatureBookActionComponent } from './visa-send-signature-book-action/send-signature-book-action.component';
import { ContinueVisaCircuitActionComponent } from './visa-continue-circuit-action/continue-visa-circuit-action.component';
import { SendAvisWorkflowComponent } from './avis-workflow-send-action/send-avis-workflow-action.component';
import { ContinueAvisCircuitActionComponent } from './avis-continue-circuit-action/continue-avis-circuit-action.component';
import { SendAvisParallelComponent } from './avis-parallel-send-action/send-avis-parallel-action.component';
import { GiveAvisParallelActionComponent } from './avis-give-parallel-action/give-avis-parallel-action.component';
import { ValidateAvisParallelComponent } from './avis-parallel-validate-action/validate-avis-parallel-action.component';
import { HeaderService } from '@service/header.service';
import { FunctionsService } from '@service/functions.service';
import { ReconcileActionComponent } from './reconciliation-action/reconcile-action.component';
import { SendAlfrescoActionComponent } from './send-alfresco-action/send-alfresco-action.component';
import { SendMultigestActionComponent } from './send-multigest-action/send-multigest-action.component';
import { SaveRegisteredMailActionComponent } from './save-registered-mail-action/save-registered-mail-action.component';
import { SaveAndPrintRegisteredMailActionComponent } from './save-and-print-registered-mail-action/save-and-print-registered-mail-action.component';
import { SaveAndIndexRegisteredMailActionComponent } from './save-and-index-registered-mail-action/save-and-index-registered-mail-action.component';
import { PrintRegisteredMailActionComponent } from './print-registered-mail-action/print-registered-mail-action.component';
import { PrintDepositListActionComponent } from './print-deposit-list-action/print-deposit-list-action.component';
import { SendToRecordManagementComponent } from './send-to-record-management-action/send-to-record-management.component';
import { CheckReplyRecordManagementComponent } from './check-reply-record-management-action/check-reply-record-management.component';
import { ResetRecordManagementComponent } from './reset-record-management-action/reset-record-management.component';
import { CheckAcknowledgmentRecordManagementComponent } from './check-acknowledgment-record-management-action/check-acknowledgment-record-management.component';
import { FiltersListService } from '@service/filtersList.service';
import { SessionStorageService } from '@service/session-storage.service';

@Injectable()
export class ActionsService implements OnDestroy {

    mode: string = 'indexing';

    currentResourceLock: any = null;
    lockMode: boolean = true;
    actionEnded: boolean = false;

    currentAction: any = null;
    currentUserId: number = null;
    currentGroupId: number = null;
    currentBasketId: number = null;
    currentResIds: number[] = [];
    currentResourceInformations: any = null;

    loading: boolean = false;

    indexActionRoute: string;
    processActionRoute: string;

    listProperties: any = null;

    private eventAction = new Subject<any>();

    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        public dialog: MatDialog,
        private notify: NotificationService,
        private router: Router,
        public headerService: HeaderService,
        private functions: FunctionsService,
        private filtersListService: FiltersListService,
        private sessionStorage: SessionStorageService
    ) { }

    ngOnDestroy(): void {
        if (this.currentResourceLock) {
            this.unlockResourceAfterActionModal(this.currentResIds);
        }
    }

    catchAction(): Observable<any> {
        return this.eventAction.asObservable();
    }

    emitAction() {
        this.eventAction.next(true);
    }

    setLoading(state: boolean) {
        this.loading = state;
    }

    setActionInformations(action: any, userId: number, groupId: number, basketId: number, resIds: number[]) {
        if (action !== null && action.component === null) {
            return false;
        } else if (action !== null && userId > 0 && groupId > 0) {
            this.mode = basketId === null ? 'indexing' : 'process';
            this.currentAction = action;
            this.currentUserId = userId;
            this.currentGroupId = groupId;
            this.currentBasketId = basketId;
            this.currentResIds = resIds === null ? [] : resIds;

            this.indexActionRoute = `../rest/indexing/groups/${this.currentGroupId}/actions/${this.currentAction.id}`;
            this.processActionRoute = `../rest/resourcesList/users/${this.currentUserId}/groups/${this.currentGroupId}/baskets/${this.currentBasketId}/actions/${this.currentAction.id}`;

            return true;
        } else {
            console.log('Bad informations: ');
            console.log({ 'action': action }, { 'userId': userId }, { 'groupId': groupId }, { 'basketId': basketId }, { 'resIds': resIds });

            this.notify.error('Une erreur est survenue');
            return false;
        }
    }

    saveDocument(datas: any) {
        this.loading = true;
        this.setResourceInformations(datas);
        return this.http.post('../rest/resources', this.currentResourceInformations);
    }

    setResourceInformations(datas: any) {
        this.currentResourceInformations = datas;
    }

    setResourceIds(resId: number[]) {
        this.currentResourceInformations['resId'] = resId;
        this.currentResIds = resId;
    }

    loadResources(currentUserId: any = this.currentUserId, currentGroupId: any = this.currentGroupId, currentBasketId: any = this.currentBasketId) {
        this.listProperties = this.filtersListService.initListsProperties(currentUserId, currentGroupId, currentBasketId, 'basket');
        const offset: number =  this.listProperties.page * this.listProperties.pageSize;
        const limit: number = this.listProperties.pageSize;
        const filters: string = this.filtersListService.getUrlFilters();
        return this.http.get(`../rest/resourcesList/users/${currentUserId}/groups/${currentGroupId}/baskets/${currentBasketId}?limit=${limit}&offset=${offset}${filters}`);
    }

    launchIndexingAction(action: any, userId: number, groupId: number, datas: any) {

        if (this.setActionInformations(action, userId, groupId, null, null)) {
            this.setResourceInformations(datas);

            this.loading = true;
            try {
                this[action.component]();
            } catch (error) {
                console.log(error);
                console.log(action.component);
                alert(this.translate.instant('lang.actionNotExist'));
            }
        }
    }

    async launchAction(action: any, userId: number, groupId: number, basketId: number, resIds: number[], datas: any, lockRes: boolean = true) {
        if (this.setActionInformations(action, userId, groupId, basketId, resIds)) {
            this.actionEnded = false;
            this.loading = true;
            this.lockMode = lockRes;
            this.setResourceInformations(datas);

            if (this.lockMode) {
                const res: any = await this.canExecuteAction(resIds);
                if (res === true) {
                    if (['viewDoc', 'documentDetails', 'signatureBookAction', 'processDocument', 'noConfirmAction'].indexOf(action.component) > -1) {
                        this[action.component](action.data);
                    } else {
                        try {
                            this.lockResource();
                            this[action.component](action.data);
                        } catch (error) {
                            console.log(error);
                            console.log(action);
                            this.unlockResourceAfterActionModal([]);
                            alert(this.translate.instant('lang.actionNotExist'));
                        }
                    }
                }
            } else {
                try {
                    this[action.component]();
                } catch (error) {
                    console.log(error);
                    console.log(action);
                    alert(this.translate.instant('lang.actionNotExist'));
                }
            }
        }
    }

    canExecuteAction(resIds: number[], userId: number = this.currentUserId, groupId: number = this.currentGroupId, basketId: number = this.currentBasketId) {
        return new Promise((resolve) => {
            this.http.put(`../rest/resourcesList/users/${userId}/groups/${groupId}/baskets/${basketId}/locked`, { resources: resIds }).pipe(
                tap((data: any) => {
                    let msgWarn = this.translate.instant('lang.warnLockRes') + ' : ' + data.lockers.join(', ');

                    if (data.countLockedResources !== resIds.length) {
                        msgWarn += this.translate.instant('lang.warnLockRes2') + '.';
                    }

                    if (data.countLockedResources > 0) {
                        alert(data.countLockedResources + ' ' + msgWarn);
                    }

                    if (data.countLockedResources !== resIds.length) {
                        this.currentResIds = data.resourcesToProcess;
                        resolve(true);
                    } else {
                        resolve(false);
                    }
                }),
                // tap((data: any) => resolve(data)),
                catchError((err: any) => {
                    this.notify.handleErrors(err);
                    return of(false);
                })
            ).subscribe();
        });
    }

    getDefaultAction(): any {
        const objToSend: any = {
            showToggle: false,
            inLocalStorage: false,
            canGoToNextRes: false
        };
        if (!this.functions.empty(this.currentResourceInformations.canGoToNextRes)) {
            // Check if the option is activated for the current basket
            if (this.currentResourceInformations.canGoToNextRes === true) {
                objToSend.showToggle = this.router.url.includes('process');
                objToSend.inLocalStorage = !this.functions.empty(this.sessionStorage.get(`canGoToNextRes_basket_${this.currentBasketId}_group_${this.currentGroupId}_action_${this.currentAction.id}`));
                objToSend.canGoToNextRes = objToSend.inLocalStorage;
            } else {
                objToSend.showToggle = objToSend.canGoToNextRes = false;
                this.sessionStorage.clearAllById({basketId: this.currentBasketId, groupId: this.currentGroupId, action: this.currentAction});
            }
        } else {
            objToSend.showToggle = objToSend.canGoToNextRes = false;
            this.sessionStorage.clearAllById({basketId: this.currentBasketId, groupId: this.currentGroupId, action: this.currentAction});
        }
        return objToSend;
    }

    hasLockResources() {
        return !this.functions.empty(this.currentResourceLock);
    }

    lockResource(userId: number = this.currentUserId, groupId: number = this.currentGroupId, basketId: number = this.currentBasketId, resIds: number[] = this.currentResIds) {
        console.debug(`Lock resources : ${resIds}`);

        this.http.put(`../rest/resourcesList/users/${userId}/groups/${groupId}/baskets/${basketId}/lock`, { resources: resIds }).pipe(
            tap(() => console.debug('Cycle lock : ', this.currentResourceLock)),
            catchError((err: any) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();

        if (!this.functions.empty(this.currentResourceLock)) {
            clearInterval(this.currentResourceLock);
        }

        this.currentResourceLock = setInterval(() => {
            this.http.put(`../rest/resourcesList/users/${userId}/groups/${groupId}/baskets/${basketId}/lock`, { resources: resIds }).pipe(
                tap(() => console.debug('Cycle lock : ', this.currentResourceLock)),
                catchError((err: any) => {
                    if (err.status === 403) {
                        clearInterval(this.currentResourceLock);
                    }
                    this.notify.handleErrors(err);
                    return of(false);
                })
            ).subscribe();
        }, 50000);
    }

    unlockResource(userId: number = this.currentUserId, groupId: number = this.currentGroupId, basketId: number = this.currentBasketId, resIds: number[] = this.currentResIds, path: string = null) {
        return new Promise((resolve) => {
            if (resIds.length > 0) {
                console.debug(`Unlock resources : ${resIds}`);
                this.http.put(`../rest/resourcesList/users/${userId}/groups/${groupId}/baskets/${basketId}/unlock`, { resources: resIds }).pipe(
                    tap(() => {
                        resolve(true);
                    }),
                    catchError((err: any) => {
                        if (path !== null) {
                            this.router.navigate([`/basketList/users/${this.currentUserId}/groups/${this.currentGroupId}/baskets/${this.currentBasketId}`]);
                        } else {
                            this.notify.handleErrors(err);
                        }
                        return of(false);
                    })
                ).subscribe();
            }
        });
    }

    stopRefreshResourceLock() {
        if (this.currentResourceLock !== null) {
            console.debug('Cycle lock cancel');
            clearInterval(this.currentResourceLock);
        }
    }

    setDatasActionToSend() {
        return {
            resIds: this.currentResIds,
            resource: this.currentResourceInformations,
            action: this.currentAction,
            userId: this.currentUserId,
            groupId: this.currentGroupId,
            basketId: this.currentBasketId,
            indexActionRoute: this.indexActionRoute,
            processActionRoute: this.processActionRoute,
            additionalInfo: this.getDefaultAction()
        };
    }

    unlockResourceAfterActionModal(resIds: any) {
        if (this.lockMode) {
            this.stopRefreshResourceLock();

            // Cancel action modal
            if (this.functions.empty(resIds)) {
                this.unlockResource();
            }
        }
    }

    endAction(resIds: any) {
        if (this.mode === 'indexing' && !this.functions.empty(this.currentResourceInformations['followed']) && this.currentResourceInformations['followed']) {
            this.headerService.nbResourcesFollowed++;
        }

        this.notify.success(this.translate.instant('lang.action') + ' : "' + this.currentAction.label + '" ' + this.translate.instant('lang.done'));

        this.actionEnded = true;
        if (this.router.url.includes('process') && !this.functions.empty(this.sessionStorage.get(`canGoToNextRes_basket_${this.currentBasketId}_group_${this.currentGroupId}_action_${this.currentAction.id}`))) {
            this.loadResources().pipe(
                tap((data: any) => {
                    const index: number = data.allResources.indexOf(parseInt(this.currentResourceInformations.resId, 10));
                    if (!this.functions.empty(data.allResources[index + 1])) {
                        this.router.navigate(['/process/users/' + this.currentUserId + '/groups/' + this.currentGroupId + '/baskets/' + this.currentBasketId + '/resId/' + data.allResources[index + 1]]);
                    } else {
                        this.eventAction.next(resIds);
                    }
                }),
                catchError((err: any) => {
                    this.notify.handleSoftErrors(err);
                    this.eventAction.next(resIds);
                    return of(false);
                })
            ).subscribe();
        } else {
            this.eventAction.next(resIds);
        }
    }

    /* OPEN SPECIFIC ACTION */
    confirmAction(options: any = null) {

        const dialogRef = this.dialog.open(ConfirmActionComponent, {
            panelClass: 'maarch-modal',
            disableClose: true,
            width: '500px',
            data: this.setDatasActionToSend()
        });

        dialogRef.afterClosed().pipe(
            tap((resIds: any) => {
                this.unlockResourceAfterActionModal(resIds);
            }),
            filter((resIds: any) => !this.functions.empty(resIds)),
            tap((resIds: any) => {
                this.endAction(resIds);
            }),
            finalize(() => this.loading = false),
            catchError((err: any) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    closeMailAction(options: any = null) {
        const dialogRef = this.dialog.open(CloseMailActionComponent, {
            disableClose: true,
            width: '500px',
            panelClass: 'maarch-modal',
            data: this.setDatasActionToSend()
        });
        dialogRef.afterClosed().pipe(
            tap((resIds: any) => {
                this.unlockResourceAfterActionModal(resIds);
            }),
            filter((resIds: any) => !this.functions.empty(resIds)),
            tap((resIds: any) => {
                this.endAction(resIds);
            }),
            finalize(() => this.loading = false),
            catchError((err: any) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    closeAndIndexAction(options: any = null) {
        const dialogRef = this.dialog.open(CloseAndIndexActionComponent, {
            panelClass: 'maarch-modal',
            disableClose: true,
            width: '500px',
            data: this.setDatasActionToSend()
        });
        dialogRef.afterClosed().pipe(
            tap((resIds: any) => {
                this.unlockResourceAfterActionModal(resIds);
            }),
            filter((resIds: any) => !this.functions.empty(resIds)),
            tap((resIds: any) => {
                this.endAction(resIds);
            }),
            finalize(() => this.loading = false),
            catchError((err: any) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    redirectInitiatorEntityAction(options: any = null) {
        const dialogRef = this.dialog.open(RedirectInitiatorEntityActionComponent, {
            panelClass: 'maarch-modal',
            disableClose: true,
            width: '500px',
            data: this.setDatasActionToSend()
        });
        dialogRef.afterClosed().pipe(
            tap((resIds: any) => {
                this.unlockResourceAfterActionModal(resIds);
            }),
            filter((resIds: any) => !this.functions.empty(resIds)),
            tap((resIds: any) => {
                this.endAction(resIds);
            }),
            finalize(() => this.loading = false),
            catchError((err: any) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    closeMailWithAttachmentsOrNotesAction(options: any = null) {
        const dialogRef = this.dialog.open(closeMailWithAttachmentsOrNotesActionComponent, {
            panelClass: 'maarch-modal',
            disableClose: true,
            width: '500px',
            data: this.setDatasActionToSend()
        });
        dialogRef.afterClosed().pipe(
            tap((resIds: any) => {
                this.unlockResourceAfterActionModal(resIds);
            }),
            filter((resIds: any) => !this.functions.empty(resIds)),
            tap((resIds: any) => {
                this.endAction(resIds);
            }),
            finalize(() => this.loading = false),
            catchError((err: any) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    updateAcknowledgementSendDateAction(options: any = null) {
        const dialogRef = this.dialog.open(UpdateAcknowledgementSendDateActionComponent, {
            panelClass: 'maarch-modal',
            disableClose: true,
            width: '500px',
            data: this.setDatasActionToSend()
        });
        dialogRef.afterClosed().pipe(
            tap((resIds: any) => {
                this.unlockResourceAfterActionModal(resIds);
            }),
            filter((resIds: any) => !this.functions.empty(resIds)),
            tap((resIds: any) => {
                this.endAction(resIds);
            }),
            finalize(() => this.loading = false),
            catchError((err: any) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    createAcknowledgementReceiptsAction(options: any = null) {
        const dialogRef = this.dialog.open(CreateAcknowledgementReceiptActionComponent, {
            panelClass: 'maarch-modal',
            disableClose: true,
            width: '600px',
            data: this.setDatasActionToSend()
        });
        dialogRef.afterClosed().pipe(
            tap((resIds: any) => {
                this.unlockResourceAfterActionModal(resIds);
            }),
            filter((resIds: any) => !this.functions.empty(resIds)),
            tap((resIds: any) => {
                this.endAction(resIds);
            }),
            finalize(() => this.loading = false),
            catchError((err: any) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    updateDepartureDateAction(options: any = null) {
        const dialogRef = this.dialog.open(UpdateDepartureDateActionComponent, {
            panelClass: 'maarch-modal',
            disableClose: true,
            width: '500px',
            data: this.setDatasActionToSend()
        });
        dialogRef.afterClosed().pipe(
            tap((resIds: any) => {
                this.unlockResourceAfterActionModal(resIds);
            }),
            filter((resIds: any) => !this.functions.empty(resIds)),
            tap((resIds: any) => {
                this.endAction(resIds);
            }),
            finalize(() => this.loading = false),
            catchError((err: any) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    disabledBasketPersistenceAction(options: any = null) {
        const dialogRef = this.dialog.open(DisabledBasketPersistenceActionComponent, {
            panelClass: 'maarch-modal',
            disableClose: true,
            width: '500px',
            data: this.setDatasActionToSend()
        });
        dialogRef.afterClosed().pipe(
            tap((resIds: any) => {
                this.unlockResourceAfterActionModal(resIds);
            }),
            filter((resIds: any) => !this.functions.empty(resIds)),
            tap((resIds: any) => {
                this.endAction(resIds);
            }),
            finalize(() => this.loading = false),
            catchError((err: any) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    enabledBasketPersistenceAction(options: any = null) {
        const dialogRef = this.dialog.open(EnabledBasketPersistenceActionComponent, {
            panelClass: 'maarch-modal',
            disableClose: true,
            width: '500px',
            data: this.setDatasActionToSend()
        });
        dialogRef.afterClosed().pipe(
            tap((resIds: any) => {
                this.unlockResourceAfterActionModal(resIds);
            }),
            filter((resIds: any) => !this.functions.empty(resIds)),
            tap((resIds: any) => {
                this.endAction(resIds);
            }),
            finalize(() => this.loading = false),
            catchError((err: any) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    resMarkAsReadAction(options: any = null) {
        const dialogRef = this.dialog.open(ResMarkAsReadActionComponent, {
            panelClass: 'maarch-modal',
            disableClose: true,
            width: '500px',
            data: this.setDatasActionToSend()
        });
        dialogRef.afterClosed().pipe(
            tap((resIds: any) => {
                this.unlockResourceAfterActionModal(resIds);
            }),
            filter((resIds: any) => !this.functions.empty(resIds)),
            tap((resIds: any) => {
                this.endAction(resIds);
            }),
            finalize(() => this.loading = false),
            catchError((err: any) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    viewDoc(options: any = null) {
        this.dialog.open(ViewDocActionComponent, {
            panelClass: ['maarch-full-height-modal', 'maarch-doc-modal'],
            data: this.setDatasActionToSend()
        });
    }

    sendExternalSignatoryBookAction(options: any = null) {
        const dialogRef = this.dialog.open(SendExternalSignatoryBookActionComponent, {
            panelClass: 'maarch-modal',
            disableClose: true,
            width: '580px',
            data: this.setDatasActionToSend()
        });
        dialogRef.afterClosed().pipe(
            tap((resIds: any) => {
                this.unlockResourceAfterActionModal(resIds);
            }),
            filter((resIds: any) => !this.functions.empty(resIds)),
            tap((resIds: any) => {
                this.endAction(resIds);
            }),
            finalize(() => this.loading = false),
            catchError((err: any) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    sendExternalNoteBookAction(options: any = null) {
        const dialogRef = this.dialog.open(SendExternalNoteBookActionComponent, {
            panelClass: 'maarch-modal',
            disableClose: true,
            width: '500px',
            data: this.setDatasActionToSend()
        });
        dialogRef.afterClosed().pipe(
            tap((resIds: any) => {
                this.unlockResourceAfterActionModal(resIds);
            }),
            filter((resIds: any) => !this.functions.empty(resIds)),
            tap((resIds: any) => {
                this.endAction(resIds);
            }),
            finalize(() => this.loading = false),
            catchError((err: any) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    redirectAction(options: any = null) {
        const dialogRef = this.dialog.open(RedirectActionComponent, {
            panelClass: 'maarch-modal',
            disableClose: true,
            data: this.setDatasActionToSend()
        });
        dialogRef.afterClosed().pipe(
            tap((resIds: any) => {
                this.unlockResourceAfterActionModal(resIds);
            }),
            filter((resIds: any) => !this.functions.empty(resIds)),
            tap((resIds: any) => {
                this.endAction(resIds);
            }),
            finalize(() => this.loading = false),
            catchError((err: any) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    sendShippingAction(options: any = null) {
        const dialogRef = this.dialog.open(SendShippingActionComponent, {
            panelClass: 'maarch-modal',
            disableClose: true,
            minWidth: '500px',
            data: this.setDatasActionToSend()
        });
        dialogRef.afterClosed().pipe(
            tap((resIds: any) => {
                this.unlockResourceAfterActionModal(resIds);
            }),
            filter((resIds: any) => !this.functions.empty(resIds)),
            tap((resIds: any) => {
                this.endAction(resIds);
            }),
            finalize(() => this.loading = false),
            catchError((err: any) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    sendSignatureBookAction(options: any = null) {
        const dialogRef = this.dialog.open(SendSignatureBookActionComponent, {
            panelClass: 'maarch-modal',
            autoFocus: false,
            disableClose: true,
            data: this.setDatasActionToSend()
        });
        dialogRef.afterClosed().pipe(
            tap((resIds: any) => {
                this.unlockResourceAfterActionModal(resIds);
            }),
            filter((resIds: any) => !this.functions.empty(resIds)),
            tap((resIds: any) => {
                this.endAction(resIds);
            }),
            finalize(() => this.loading = false),
            catchError((err: any) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    continueVisaCircuitAction(options: any = null) {
        const dialogRef = this.dialog.open(ContinueVisaCircuitActionComponent, {
            panelClass: 'maarch-modal',
            autoFocus: false,
            disableClose: true,
            data: this.setDatasActionToSend()
        });
        dialogRef.afterClosed().pipe(
            tap((resIds: any) => {
                this.unlockResourceAfterActionModal(resIds);
            }),
            filter((resIds: any) => !this.functions.empty(resIds)),
            tap((resIds: any) => {
                this.endAction(resIds);
            }),
            finalize(() => this.loading = false),
            catchError((err: any) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }


    noConfirmAction(options: any = null) {
        const dataActionToSend = this.setDatasActionToSend();
        if (dataActionToSend.resIds.length === 0) {
            this.http.post('../rest/resources', dataActionToSend.resource).pipe(
                tap((data: any) => {
                    dataActionToSend.resIds = [data.resId];
                }),
                exhaustMap(() => this.http.put(dataActionToSend.indexActionRoute, {
                    resource: dataActionToSend.resIds[0]
                })),
                tap(() => {
                    this.endAction(dataActionToSend.resIds);
                }),
                finalize(() => this.loading = false),
                catchError((err: any) => {
                    this.notify.handleErrors(err);
                    return of(false);
                })
            ).subscribe();
        } else {
            this.http.put(dataActionToSend.processActionRoute, { resources: this.setDatasActionToSend().resIds }).pipe(
                tap((resIds: any) => {
                    this.endAction(resIds);
                }),
                finalize(() => this.loading = false),
                catchError((err: any) => {
                    this.notify.handleErrors(err);
                    return of(false);
                })
            ).subscribe();
        }
    }

    processDocument(options: any = null) {
        this.router.navigate([`/process/users/${this.currentUserId}/groups/${this.currentGroupId}/baskets/${this.currentBasketId}/resId/${this.currentResIds}`]);
    }

    signatureBookAction(options: any = null) {
        this.router.navigate([`/signatureBook/users/${this.currentUserId}/groups/${this.currentGroupId}/baskets/${this.currentBasketId}/resources/${this.currentResIds}`]);
    }

    documentDetails(options: any = null) {
        this.router.navigate([`/resources/${this.currentResIds}`]);
    }

    rejectVisaBackToPreviousAction(options: any = null) {
        const dialogRef = this.dialog.open(RejectVisaBackToPrevousActionComponent, {
            panelClass: 'maarch-modal',
            disableClose: true,
            width: '500px',
            data: this.setDatasActionToSend()
        });
        dialogRef.afterClosed().pipe(
            tap((resIds: any) => {
                this.unlockResourceAfterActionModal(resIds);
            }),
            filter((resIds: any) => !this.functions.empty(resIds)),
            tap((resIds: any) => {
                this.endAction(resIds);
            }),
            finalize(() => this.loading = false),
            catchError((err: any) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    resetVisaAction(options: any = null) {
        const dialogRef = this.dialog.open(ResetVisaActionComponent, {
            panelClass: 'maarch-modal',
            disableClose: true,
            width: '500px',
            data: this.setDatasActionToSend()
        });
        dialogRef.afterClosed().pipe(
            tap((resIds: any) => {
                this.unlockResourceAfterActionModal(resIds);
            }),
            filter((resIds: any) => !this.functions.empty(resIds)),
            tap((resIds: any) => {
                this.endAction(resIds);
            }),
            finalize(() => this.loading = false),
            catchError((err: any) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    interruptVisaAction(options: any = null) {
        const dialogRef = this.dialog.open(InterruptVisaActionComponent, {
            panelClass: 'maarch-modal',
            disableClose: true,
            width: '500px',
            data: this.setDatasActionToSend()
        });
        dialogRef.afterClosed().pipe(
            tap((resIds: any) => {
                this.unlockResourceAfterActionModal(resIds);
            }),
            filter((resIds: any) => !this.functions.empty(resIds)),
            tap((resIds: any) => {
                this.endAction(resIds);
            }),
            finalize(() => this.loading = false),
            catchError((err: any) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    sendToOpinionCircuitAction(options: any = null) {
        const dialogRef = this.dialog.open(SendAvisWorkflowComponent, {
            panelClass: 'maarch-modal',
            autoFocus: false,
            disableClose: true,
            data: this.setDatasActionToSend()
        });
        dialogRef.afterClosed().pipe(
            tap((resIds: any) => {
                this.unlockResourceAfterActionModal(resIds);
            }),
            filter((resIds: any) => !this.functions.empty(resIds)),
            tap((resIds: any) => {
                this.endAction(resIds);
            }),
            finalize(() => this.loading = false),
            catchError((err: any) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    sendToParallelOpinion(options: any = null) {
        const dialogRef = this.dialog.open(SendAvisParallelComponent, {
            panelClass: 'maarch-modal',
            autoFocus: false,
            disableClose: true,
            data: this.setDatasActionToSend()
        });
        dialogRef.afterClosed().pipe(
            tap((resIds: any) => {
                this.unlockResourceAfterActionModal(resIds);
            }),
            filter((resIds: any) => !this.functions.empty(resIds)),
            tap((resIds: any) => {
                this.endAction(resIds);
            }),
            finalize(() => this.loading = false),
            catchError((err: any) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    continueOpinionCircuitAction(options: any = null) {
        const dialogRef = this.dialog.open(ContinueAvisCircuitActionComponent, {
            panelClass: 'maarch-modal',
            autoFocus: false,
            disableClose: true,
            data: this.setDatasActionToSend()
        });
        dialogRef.afterClosed().pipe(
            tap((resIds: any) => {
                this.unlockResourceAfterActionModal(resIds);
            }),
            filter((resIds: any) => !this.functions.empty(resIds)),
            tap((resIds: any) => {
                this.endAction(resIds);
            }),
            finalize(() => this.loading = false),
            catchError((err: any) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    giveOpinionParallelAction(options: any = null) {
        const dialogRef = this.dialog.open(GiveAvisParallelActionComponent, {
            panelClass: 'maarch-modal',
            autoFocus: false,
            disableClose: true,
            data: this.setDatasActionToSend()
        });
        dialogRef.afterClosed().pipe(
            tap((resIds: any) => {
                this.unlockResourceAfterActionModal(resIds);
            }),
            filter((resIds: any) => !this.functions.empty(resIds)),
            tap((resIds: any) => {
                this.endAction(resIds);
            }),
            finalize(() => this.loading = false),
            catchError((err: any) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    validateParallelOpinionDiffusionAction(options: any = null) {
        const dialogRef = this.dialog.open(ValidateAvisParallelComponent, {
            panelClass: 'maarch-modal',
            autoFocus: false,
            disableClose: true,
            data: this.setDatasActionToSend()
        });
        dialogRef.afterClosed().pipe(
            tap((resIds: any) => {
                this.unlockResourceAfterActionModal(resIds);
            }),
            filter((resIds: any) => !this.functions.empty(resIds)),
            tap((resIds: any) => {
                this.endAction(resIds);
            }),
            finalize(() => this.loading = false),
            catchError((err: any) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    reconcileAction(options: any = null) {
        const dialogRef = this.dialog.open(ReconcileActionComponent, {
            panelClass: 'maarch-modal',
            autoFocus: false,
            disableClose: true,
            data: this.setDatasActionToSend()
        });
        dialogRef.afterClosed().pipe(
            tap(() => {
                this.stopRefreshResourceLock();
            }),
            filter((resIds: any) => !this.functions.empty(resIds)),
            tap((resIds: any) => {
                this.endAction(resIds);
            }),
            finalize(() => this.loading = false),
            catchError((err: any) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    sendAlfrescoAction(options: any = null) {
        const dialogRef = this.dialog.open(SendAlfrescoActionComponent, {
            panelClass: 'maarch-modal',
            autoFocus: false,
            disableClose: true,
            data: this.setDatasActionToSend()
        });
        dialogRef.afterClosed().pipe(
            tap((data: any) => {
                this.unlockResourceAfterActionModal(data);
            }),
            filter((data: string) => data === 'success'),
            tap((result: any) => {
                this.endAction(result);
            }),
            finalize(() => this.loading = false),
            catchError((err: any) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    sendMultigestAction(options: any = null) {
        const dialogRef = this.dialog.open(SendMultigestActionComponent, {
            panelClass: 'maarch-modal',
            autoFocus: false,
            disableClose: true,
            data: this.setDatasActionToSend()
        });
        dialogRef.afterClosed().pipe(
            tap((data: any) => {
                this.unlockResourceAfterActionModal(data);
            }),
            filter((data: string) => data === 'success'),
            tap((result: any) => {
                this.endAction(result);
            }),
            finalize(() => this.loading = false),
            catchError((err: any) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    saveRegisteredMailAction(options: any = null) {

        const dialogRef = this.dialog.open(SaveRegisteredMailActionComponent, {
            panelClass: 'maarch-modal',
            disableClose: true,
            width: '500px',
            data: this.setDatasActionToSend()
        });

        dialogRef.afterClosed().pipe(
            tap((resIds: any) => {
                this.unlockResourceAfterActionModal(resIds);
            }),
            filter((resIds: any) => !this.functions.empty(resIds)),
            tap((resIds: any) => {
                this.endAction(resIds);
            }),
            finalize(() => this.loading = false),
            catchError((err: any) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    saveAndPrintRegisteredMailAction(options: any = null) {

        const dialogRef = this.dialog.open(SaveAndPrintRegisteredMailActionComponent, {
            panelClass: 'maarch-modal',
            disableClose: true,
            width: '500px',
            data: this.setDatasActionToSend()
        });

        dialogRef.afterClosed().pipe(
            tap((resIds: any) => {
                this.unlockResourceAfterActionModal(resIds);
            }),
            filter((resIds: any) => !this.functions.empty(resIds)),
            tap((resIds: any) => {
                this.endAction(resIds);
            }),
            finalize(() => this.loading = false),
            catchError((err: any) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    saveAndIndexRegisteredMailAction(options: any = null) {
        const dialogRef = this.dialog.open(SaveAndIndexRegisteredMailActionComponent, {
            panelClass: 'maarch-modal',
            disableClose: true,
            width: '500px',
            data: this.setDatasActionToSend()
        });
        dialogRef.afterClosed().pipe(
            tap((resIds: any) => {
                this.unlockResourceAfterActionModal(resIds);
            }),
            filter((resIds: any) => !this.functions.empty(resIds)),
            tap((resIds: any) => {
                this.endAction(resIds);
            }),
            finalize(() => this.loading = false),
            catchError((err: any) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    printRegisteredMailAction(options: any = null) {

        const dialogRef = this.dialog.open(PrintRegisteredMailActionComponent, {
            panelClass: 'maarch-modal',
            disableClose: true,
            width: '500px',
            data: this.setDatasActionToSend()
        });

        dialogRef.afterClosed().pipe(
            tap((resIds: any) => {
                this.unlockResourceAfterActionModal(resIds);
            }),
            filter((resIds: any) => !this.functions.empty(resIds)),
            tap((resIds: any) => {
                this.endAction(resIds);
            }),
            finalize(() => this.loading = false),
            catchError((err: any) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    printDepositListAction(options: any = null) {
        const dialogRef = this.dialog.open(PrintDepositListActionComponent, {
            panelClass: 'maarch-modal',
            disableClose: true,
            width: '500px',
            data: this.setDatasActionToSend()
        });

        dialogRef.afterClosed().pipe(
            tap((resIds: any) => {
                this.unlockResourceAfterActionModal(resIds);
            }),
            filter((resIds: any) => !this.functions.empty(resIds)),
            tap((resIds: any) => {
                this.endAction(resIds);
            }),
            finalize(() => this.loading = false),
            catchError((err: any) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    sendToRecordManagementAction(options: any = null) {
        const dialogRef = this.dialog.open(SendToRecordManagementComponent, {
            panelClass: 'maarch-modal',
            maxWidth: '100%',
            width: '100% !important',
            autoFocus: false,
            disableClose: true,
            data: this.setDatasActionToSend()
        });
        dialogRef.afterClosed().pipe(
            tap((resIds: any) => {
                this.unlockResourceAfterActionModal(resIds);
            }),
            filter((resIds: any) => !this.functions.empty(resIds)),
            tap((resIds: any) => {
                this.endAction(resIds);
            }),
            finalize(() => this.loading = false),
            catchError((err: any) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    checkReplyRecordManagementAction(options: any = null) {
        const dialogRef = this.dialog.open(CheckReplyRecordManagementComponent, {
            panelClass: 'maarch-modal',
            autoFocus: false,
            disableClose: true,
            data: this.setDatasActionToSend()
        });
        dialogRef.afterClosed().pipe(
            tap((resIds: any) => {
                this.unlockResourceAfterActionModal(resIds);
            }),
            filter((resIds: any) => !this.functions.empty(resIds)),
            tap((resIds: any) => {
                this.endAction(resIds);
            }),
            finalize(() => this.loading = false),
            catchError((err: any) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    resetRecordManagementAction(options: any = null) {
        const dialogRef = this.dialog.open(ResetRecordManagementComponent, {
            panelClass: 'maarch-modal',
            autoFocus: false,
            disableClose: true,
            data: this.setDatasActionToSend()
        });
        dialogRef.afterClosed().pipe(
            tap((resIds: any) => {
                this.unlockResourceAfterActionModal(resIds);
            }),
            filter((resIds: any) => !this.functions.empty(resIds)),
            tap((resIds: any) => {
                this.endAction(resIds);
            }),
            finalize(() => this.loading = false),
            catchError((err: any) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    checkAcknowledgmentRecordManagementAction(options: any = null) {
        const dialogRef = this.dialog.open(CheckAcknowledgmentRecordManagementComponent, {
            panelClass: 'maarch-modal',
            autoFocus: false,
            disableClose: true,
            data: this.setDatasActionToSend()
        });
        dialogRef.afterClosed().pipe(
            tap((resIds: any) => {
                this.unlockResourceAfterActionModal(resIds);
            }),
            filter((resIds: any) => !this.functions.empty(resIds)),
            tap((resIds: any) => {
                this.endAction(resIds);
            }),
            finalize(() => this.loading = false),
            catchError((err: any) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    getUserOtpIcon(id: string): Promise<string> {
        return new Promise((resolve) => {
            this.http.get(`assets/${id}.png`, { responseType: 'blob' }).pipe(
                tap((response: any) => {
                    const reader = new FileReader();
                    reader.readAsDataURL(response);
                    reader.onloadend = () => {
                        resolve(reader.result as any);
                    };
                }),
                catchError(err => {
                    this.notify.handleErrors(err);
                    return of(false);
                })
            ).subscribe();
        });
    }
}
