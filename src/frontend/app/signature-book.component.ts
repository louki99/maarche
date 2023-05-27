import { Component, OnInit, NgZone, ViewChild, OnDestroy } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Router, ActivatedRoute } from '@angular/router';
import { TranslateService } from '@ngx-translate/core';
import { NotificationService } from '@service/notification/notification.service';
import { tap, catchError, filter, exhaustMap, finalize } from 'rxjs/operators';
import { PrivilegeService } from '@service/privileges.service';
import { MatDialogRef, MatDialog } from '@angular/material/dialog';
import { AttachmentCreateComponent } from './attachments/attachment-create/attachment-create.component';
import { FunctionsService } from '@service/functions.service';
import { AttachmentPageComponent } from './attachments/attachments-page/attachment-page.component';
import { VisaWorkflowComponent } from './visa/visa-workflow.component';
import { ActionsService } from './actions/actions.service';
import { HeaderService } from '@service/header.service';
import { AppService } from '@service/app.service';
import { of, Subscription } from 'rxjs';
import { DocumentViewerComponent } from './viewer/document-viewer.component';
import { ConfirmComponent } from '@plugins/modal/confirm.component';
import { NotesListComponent } from './notes/notes-list.component';
import { FiltersListService } from '@service/filtersList.service';

declare let $: any;

@Component({
    templateUrl: 'signature-book.component.html',
    styleUrls: ['signature-book.component.scss'],
})
export class SignatureBookComponent implements OnInit, OnDestroy {

    @ViewChild('appVisaWorkflow', { static: false }) appVisaWorkflow: VisaWorkflowComponent;
    @ViewChild('appDocumentViewer', { static: false }) appDocumentViewer: DocumentViewerComponent;
    @ViewChild('appNotesList', { static: false }) appNotesList: NotesListComponent;

    resId: number;
    basketId: number;
    groupId: number;
    userId: number;

    signatureBook: any = {
        consigne: '',
        documents: [],
        attachments: [],
        resList: [],
        resListIndex: 0,
        lang: {}
    };

    rightSelectedThumbnail: number = 0;
    leftSelectedThumbnail: number = 0;
    rightViewerLink: string = '';
    leftViewerLink: string = '';
    headerTab: string = 'document';
    showTopRightPanel: boolean = false;
    showTopLeftPanel: boolean = false;
    showResLeftPanel: boolean = true;
    showLeftPanel: boolean = true;
    showRightPanel: boolean = true;
    showAttachmentPanel: boolean = false;
    showSignaturesPanel: boolean = false;
    loading: boolean = false;
    loadingSign: boolean = false;
    canUpdateDocument: boolean = false;

    pathToRedirect: string = '';
    allResources: number[] = [];

    subscription: Subscription;
    currentResourceLock: any = null;

    leftContentWidth: string = '44%';
    rightContentWidth: string = '44%';
    dialogRef: MatDialogRef<any>;

    listProperties: any = null;

    processTool: any[] = [
        {
            id: 'notes',
            icon: 'fas fa-pen-square fa-2x',
            label: this.translate.instant('lang.notesAlt'),
            count: 0
        },
        {
            id: 'visaCircuit',
            icon: 'fas fa-list-ol fa-2x',
            label: this.translate.instant('lang.visaWorkflow'),
            count: 0
        },
        {
            id: 'history',
            icon: 'fas fa-history fa-2x',
            label: this.translate.instant('lang.history'),
            count: 0
        },
        {
            id: 'linkedResources',
            icon: 'fas fa-link fa-2x',
            label: this.translate.instant('lang.links'),
            count: 0
        }
    ];

    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        public privilegeService: PrivilegeService,
        public dialog: MatDialog,
        public functions: FunctionsService,
        public actionService: ActionsService,
        public headerService: HeaderService,
        public filtersListService: FiltersListService,
        private appService: AppService,
        private route: ActivatedRoute,
        private router: Router,
        private zone: NgZone,
        private notify: NotificationService
    ) {
        (<any>window).pdfWorkerSrc = 'pdfjs/pdf.worker.min.js';

        // Event after process action
        this.subscription = this.actionService.catchAction().subscribe(message => {
            this.processAfterAction();
        });
    }

    ngOnInit(): void {
        this.loading = true;

        this.route.params.subscribe(params => {
            this.resId = +params['resId'];
            this.basketId = params['basketId'];
            this.groupId = params['groupId'];
            this.userId = params['userId'];

            this.signatureBook.resList = []; // This line is added because of manage action behaviour (processAfterAction is called twice)

            this.actionService.lockResource(this.userId, this.groupId, this.basketId, [this.resId]);

            this.http.get('../rest/signatureBook/users/' + this.userId + '/groups/' + this.groupId + '/baskets/' + this.basketId + '/resources/' + this.resId)
                .subscribe((data: any) => {
                    if (data.error) {
                        location.hash = '';
                        location.search = '';
                        return;
                    }
                    this.signatureBook = data;
                    this.canUpdateDocument = data.canUpdateDocuments;
                    this.headerTab = 'document';
                    this.leftSelectedThumbnail = 0;
                    this.rightSelectedThumbnail = 0;
                    this.leftViewerLink = '';
                    this.rightViewerLink = '';
                    this.showLeftPanel = true;
                    this.showRightPanel = true;
                    this.showResLeftPanel = true;
                    this.showTopLeftPanel = false;
                    this.showTopRightPanel = false;
                    this.showAttachmentPanel = false;

                    this.leftContentWidth = '44%';
                    this.rightContentWidth = '44%';
                    if (this.signatureBook.documents[0]) {
                        this.leftViewerLink = this.signatureBook.documents[0].viewerLink;
                        if (this.signatureBook.documents[0].inSignatureBook) {
                            this.headerTab = 'visaCircuit';
                        }
                    }
                    if (this.signatureBook.attachments[0]) {
                        this.rightViewerLink = this.signatureBook.attachments[0].viewerLink;
                    }

                    this.signatureBook.resListIndex = this.signatureBook.resList.map((e: any) => e.res_id).indexOf(this.resId);

                    this.displayPanel('RESLEFT');
                    this.loading = false;

                    setTimeout(() => {
                        $('#rightPanelContent').niceScroll({ touchbehavior: false, cursorcolor: '#666', cursoropacitymax: 0.6, cursorwidth: '4' });

                        if ($('.tooltipstered').length === 0) {
                            $('#obsVersion').tooltipster({
                                theme: 'tooltipster-light',
                                interactive: true
                            });
                        }
                    }, 0);
                    this.loadBadges();
                    this.loadActions();

                    const path: string = `resourcesList/users/${this.userId}/groups/${this.groupId}/baskets/${this.basketId}?limit=10&offset=0`;
                    this.http.get(`../rest/${path}`).pipe(
                        tap((res: any) => {
                            this.allResources = res.allResources;
                        }),
                        catchError((err: any) => {
                            this.notify.handleSoftErrors(err);
                            return of(false);
                        })
                    ).subscribe();

                    if (this.appDocumentViewer !== undefined) {
                        this.appDocumentViewer.loadRessource(this.signatureBook.attachments[this.rightSelectedThumbnail].signed ? this.signatureBook.attachments[this.rightSelectedThumbnail].viewerId : this.signatureBook.attachments[this.rightSelectedThumbnail].res_id, this.signatureBook.attachments[this.rightSelectedThumbnail].isResource ? 'mainDocument' : 'attachment');
                    }
                }, (err) => {
                    this.notify.error(err.error.errors);
                    setTimeout(() => {
                        this.backToBasket();
                    }, 2000);

                });
        });
    }

    loadActions() {
        this.http.get('../rest/resourcesList/users/' + this.userId + '/groups/' + this.groupId + '/baskets/' + this.basketId + '/actions?resId=' + this.resId)
            .subscribe((data: any) => {
                this.signatureBook.actions = data.actions;
            }, (err) => {
                this.notify.error(err.error.errors);
            });
    }

    processAfterAction() {
        let idToGo = -1;
        const c = this.signatureBook.resList.length;

        for (let i = 0; i < c; i++) {
            if (this.signatureBook.resList[i].res_id === this.resId) {
                if (this.signatureBook.resList[i + 1]) {
                    idToGo = this.signatureBook.resList[i + 1].res_id;
                } else if (i > 0) {
                    idToGo = this.signatureBook.resList[i - 1].res_id;
                }
            }
        }

        if (c > 0) { // This (if)line is added because of manage action behaviour (processAfterAction is called twice)
            if (idToGo >= 0) {
                $('#send').removeAttr('disabled');
                $('#send').css('opacity', '1');

                this.changeLocation(idToGo, 'action');
            } else {
                this.backToBasket();
            }
        }
    }

    changeSignatureBookLeftContent(id: string) {
        if (this.isToolModified()) {
            const dialogRef = this.openConfirmModification();

            dialogRef.afterClosed().pipe(
                tap((data: string) => {
                    if (data !== 'ok') {
                        this.headerTab = id;
                        this.showTopLeftPanel = false;
                    }
                }),
                filter((data: string) => data === 'ok'),
                tap(() => {
                    this.saveTool();
                    this.headerTab = id;
                    this.showTopLeftPanel = false;
                }),
                catchError((err: any) => {
                    this.notify.handleErrors(err);
                    return of(false);
                })
            ).subscribe();
        } else {
            this.headerTab = id;
            this.showTopLeftPanel = false;
        }
    }

    isToolModified() {
        if (this.headerTab === 'visaCircuit' && this.appVisaWorkflow !== undefined && this.appVisaWorkflow.isModified()) {
            return true;
        } else if (this.headerTab === 'notes' && this.appNotesList !== undefined && this.appNotesList.isModified()) {
            return true;
        } else {
            return false;
        }
    }

    async saveTool() {
        if (this.headerTab === 'visaCircuit' && this.appVisaWorkflow !== undefined) {
            if (this.appVisaWorkflow.getWorkflow().filter((user: any) => this.functions.empty(user.process_date))[0]?.item_id !== this.headerService.user.id) {
                this.actionService.stopRefreshResourceLock();
                this.actionService.unlockResource(this.userId, this.groupId, this.basketId, [this.resId]);
            }
            const resWorkflow = await this.appVisaWorkflow.saveVisaWorkflow();
            if (resWorkflow) {
                let assignedBasket: any;
                this.http.get('../rest/home').pipe(
                    tap((data: any) => {
                        assignedBasket = data.assignedBaskets.find((basket: any) =>
                            basket.id.toString() === this.basketId &&
                            basket.owner_user_id.toString() === this.userId &&
                            basket.group_id.toString() === this.groupId
                        );
                    }),
                    finalize(() => {
                        if (this.canChange(assignedBasket)) {
                            this.goToNextDocument();
                        }
                    }),
                    catchError((err: any) => {
                        this.notify.handleSoftErrors(err);
                        return of(false);
                    })
                ).subscribe();
            } else {
                this.actionService.lockResource(this.userId, this.groupId, this.basketId, [this.resId]);
            }
            this.loadBadges();

        } else if (this.headerTab === 'notes' && this.appNotesList !== undefined) {
            this.appNotesList.addNote();
            this.loadBadges();
        }
    }

    canChange(assignedBasket: any) {
        const usersHasNotSigned: any[] = this.appVisaWorkflow.getWorkflow().filter((user: any) =>  this.functions.empty(user.process_date));
        if (assignedBasket === undefined) {
            return usersHasNotSigned[0].item_id !== this.headerService.user.id;
        } else {
            return (this.userId === this.headerService.user.id && usersHasNotSigned[0].item_id !== this.headerService.user.id) || (this.userId !== this.headerService.user.id && assignedBasket.owner_user_id !== usersHasNotSigned[0].item_id);
        }
    }

    goToNextDocument() {
        this.pathToRedirect = '';
        this.actionService.stopRefreshResourceLock();
        const path: string = `resourcesList/users/${this.userId}/groups/${this.groupId}/baskets/${this.basketId}?limit=10&offset=0`;
        this.http.get(`../rest/${path}`).pipe(
            tap((data: any) => {
                if (data.defaultAction?.component === 'signatureBookAction' && data.defaultAction?.data.goToNextDocument) {
                    if (data.count > 0) {
                        let index: number;
                        if (data.allResources.indexOf(this.resId) > -1) {
                            index = data.allResources.indexOf(this.resId);
                        } else {
                            if (this.allResources.length > 2 && this.allResources.indexOf(this.resId) !== this.allResources.length - 1) {
                                index = this.allResources.indexOf(this.resId) + 1;
                            } else {
                                index = 0;
                            }
                        }
                        this.pathToRedirect = `/signatureBook/users/${this.userId}/groups/${this.groupId}/baskets/${this.basketId}/resources/${data.allResources[index]}`;
                        this.router.navigate([this.pathToRedirect]);
                    } else {
                        this.pathToRedirect = `/basketList/users/${this.userId}/groups/${this.groupId}/baskets/${this.basketId}`;
                        this.router.navigate([this.pathToRedirect]);
                    }
                    // this.actionService.unlockResource(this.userId, this.groupId, this.basketId, [this.resId]);
                } else {
                    if (data.allResources.indexOf(this.resId) === -1) {
                        this.pathToRedirect = `/basketList/users/${this.userId}/groups/${this.groupId}/baskets/${this.basketId}`;
                        this.router.navigate([this.pathToRedirect]);
                    }
                }
            }),
            catchError((err: any) => {
                this.notify.handleSoftErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    openConfirmModification() {
        return this.dialog.open(ConfirmComponent, { panelClass: 'maarch-modal', autoFocus: false, disableClose: true, data: { title: this.translate.instant('lang.confirm'), msg: this.translate.instant('lang.saveModifiedData'), buttonValidate: this.translate.instant('lang.yes'), buttonCancel: this.translate.instant('lang.no') } });
    }

    changeRightViewer(index: number) {
        this.showAttachmentPanel = false;
        if (this.signatureBook.attachments[index]) {
            this.rightViewerLink = this.signatureBook.attachments[index].viewerLink;
        } else {
            this.rightViewerLink = '';
        }
        this.rightSelectedThumbnail = index;
        this.appDocumentViewer.loadRessource(this.signatureBook.attachments[this.rightSelectedThumbnail].signed ? this.signatureBook.attachments[this.rightSelectedThumbnail].viewerId : this.signatureBook.attachments[this.rightSelectedThumbnail].res_id, this.signatureBook.attachments[this.rightSelectedThumbnail].isResource ? 'mainDocument' : 'attachment');
    }

    changeLeftViewer(index: number) {
        this.leftViewerLink = this.signatureBook.documents[index].viewerLink;
        this.leftSelectedThumbnail = index;
    }

    displayPanel(panel: string) {
        if (panel === 'TOPRIGHT') {
            this.showTopRightPanel = !this.showTopRightPanel;
        } else if (panel === 'TOPLEFT') {
            this.showTopLeftPanel = !this.showTopLeftPanel;
        } else if (panel === 'LEFT') {
            this.showLeftPanel = !this.showLeftPanel;
            this.showResLeftPanel = false;
            if (!this.showLeftPanel) {
                this.rightContentWidth = '96%';
                $('#hideLeftContent').css('background', 'none');
            } else {
                this.rightContentWidth = '48%';
                this.leftContentWidth = '48%';
                $('#hideLeftContent').css('background', '#fbfbfb');
            }
        } else if (panel === 'RESLEFT') {
            this.showResLeftPanel = !this.showResLeftPanel;
            if (!this.showResLeftPanel) {
                this.rightContentWidth = '48%';
                this.leftContentWidth = '48%';
            } else {
                this.rightContentWidth = '44%';
                this.leftContentWidth = '44%';
                if (this.signatureBook.resList.length === 0 || typeof this.signatureBook.resList[0].creation_date === 'undefined') {
                    this.listProperties = this.filtersListService.initListsProperties(this.userId, this.groupId, this.basketId, 'basket');
                    const offset: number =  this.listProperties.page * this.listProperties.pageSize;
                    const limit: number = this.listProperties.pageSize;
                    const filters: string = this.filtersListService.getUrlFilters();
                    this.http.get(`../rest/signatureBook/users/${this.userId}/groups/${this.groupId}/baskets/${this.basketId}/resources?limit=${limit}&offset=${offset}${filters}`).pipe(
                        tap((data: any) => {
                            this.signatureBook.resList = data.resources;
                            this.signatureBook.resList.forEach((value: any, index: number) => {
                                if (value.res_id === this.resId) {
                                    this.signatureBook.resListIndex = index;
                                }
                            });
                            setTimeout(() => {
                                $('#resListContent').niceScroll({ touchbehavior: false, cursorcolor: '#666', cursoropacitymax: 0.6, cursorwidth: '4' });
                                $('#resListContent').scrollTop(0);
                                $('#resListContent').scrollTop($('.resListContentFrameSelected').offset().top - 42);
                            }, 0);
                        }),
                        catchError((err: any) => {
                            this.notify.handleSoftErrors(err);
                            return of(false);
                        })
                    ).subscribe();
                }
            }
        } else if (panel === 'MIDDLE') {
            this.showRightPanel = !this.showRightPanel;
            this.showResLeftPanel = false;
            if (!this.showRightPanel) {
                this.leftContentWidth = '96%';
                $('#contentLeft').css('border-right', 'none');
            } else {
                this.rightContentWidth = '48%';
                this.leftContentWidth = '48%';
                $('#contentLeft').css('border-right', 'solid 1px');
            }
        }
    }

    displayAttachmentPanel() {
        this.showAttachmentPanel = !this.showAttachmentPanel;
        this.rightSelectedThumbnail = 0;
        if (this.signatureBook.attachments[0]) {
            this.rightViewerLink = this.signatureBook.attachments[0].viewerLink;
        }
    }

    refreshAttachments(mode: string = 'rightContent') {
        if (mode === 'rightContent') {
            this.http.get('../rest/signatureBook/' + this.resId + '/incomingMailAttachments')
                .subscribe((data: any) => {
                    this.signatureBook.documents = data;
                });
        }
        this.http.get('../rest/signatureBook/' + this.resId + '/attachments')
            .subscribe((data: any) => {
                let i = 0;
                if (mode === 'add') {
                    let found = false;
                    data.forEach((elem: any, index: number) => {
                        if (!found && (!this.signatureBook.attachments[index] || elem.res_id != this.signatureBook.attachments[index].res_id)) {
                            i = index;
                            found = true;
                        }
                    });
                } else if (mode === 'edit') {
                    const id = this.signatureBook.attachments[this.rightSelectedThumbnail].res_id;
                    data.forEach((elem: any, index: number) => {
                        if (elem.res_id == id) {
                            i = index;
                        }
                    });
                }

                this.signatureBook.attachments = data;

                if (mode === 'add' || mode === 'edit') {
                    this.changeRightViewer(i);
                } else if (mode === 'del') {
                    this.changeRightViewer(0);
                }
            });
    }

    delAttachment(attachment: any) {
        if (this.canUpdateDocument) {
            const title = this.signatureBook.attachments.length <= 1 ? this.translate.instant('lang.deleteLastAttachmentSignatureBook') : this.translate.instant('lang.deleteAttachmentSignatureBook');
            const dialogRef = this.dialog.open(ConfirmComponent, { panelClass: 'maarch-modal', autoFocus: false, disableClose: true, data: { title: `${this.translate.instant('lang.delete')}`, msg: title } });
            dialogRef.afterClosed().pipe(
                filter((data: string) => data === 'ok'),
                exhaustMap(() => this.http.delete('../rest/attachments/' + attachment.res_id)),
                tap(() => {
                    this.refreshAttachments('del');
                }),
                catchError((err: any) => {
                    this.notify.handleSoftErrors(err);
                    return of(false);
                })
            ).subscribe();
        }
    }

    signFile(attachment: any, signature: any) {
        if (!this.loadingSign && this.signatureBook.canSign) {
            this.loadingSign = true;
            const route = attachment.isResource ? '../rest/resources/' + attachment.res_id + '/sign' : '../rest/attachments/' + attachment.res_id + '/sign';
            this.http.put(route, { 'signatureId': signature.id })
                .subscribe((data: any) => {
                    if (!attachment.isResource) {
                        this.appDocumentViewer.loadRessource(data.id, 'attachment');
                        this.rightViewerLink = '../rest/attachments/' + data.id + '/content';
                        this.signatureBook.attachments[this.rightSelectedThumbnail].status = 'SIGN';
                        this.signatureBook.attachments[this.rightSelectedThumbnail].idToDl = data.new_id;
                        this.signatureBook.attachments[this.rightSelectedThumbnail].signed = true;
                        this.signatureBook.attachments[this.rightSelectedThumbnail].viewerId = data.id;
                    } else {
                        this.appDocumentViewer.loadRessource(attachment.res_id, 'mainDocument');
                        this.rightViewerLink += '?tsp=' + Math.floor(Math.random() * 100);
                        this.signatureBook.attachments[this.rightSelectedThumbnail].status = 'SIGN';
                    }
                    this.signatureBook.attachments[this.rightSelectedThumbnail].viewerLink = this.rightViewerLink;
                    let allSigned = true;
                    this.signatureBook.attachments.forEach((value: any) => {
                        if (value.sign && value.status !== 'SIGN') {
                            allSigned = false;
                        }
                    });
                    if (this.signatureBook.resList.length > 0) {
                        this.signatureBook.resList[this.signatureBook.resListIndex].allSigned = allSigned;
                    }

                    this.showSignaturesPanel = false;
                    this.loadingSign = false;
                }, (error: any) => {
                    this.notify.handleSoftErrors(error);
                    this.loadingSign = false;
                });
        }
    }

    unsignFile(attachment: any) {
        if (attachment.isResource) {
            this.unSignMainDocument(attachment);
        } else {
            this.unSignAttachment(attachment);
        }
    }

    unSignMainDocument(attachment: any) {
        this.http.put(`../rest/resources/${attachment.res_id}/unsign`, {}).pipe(
            tap(() => {
                this.appDocumentViewer.loadRessource(attachment.res_id, 'mainDocument');
                this.rightViewerLink += '?tsp=' + Math.floor(Math.random() * 100);
                this.signatureBook.attachments[this.rightSelectedThumbnail].status = 'A_TRA';

                if (this.signatureBook.resList.length > 0) {
                    this.signatureBook.resList[this.signatureBook.resListIndex].allSigned = false;
                }
                if (this.headerTab === 'visaCircuit') {
                    this.changeSignatureBookLeftContent('document');
                    setTimeout(() => {
                        this.changeSignatureBookLeftContent('visaCircuit');
                    }, 0);
                }
            }),
            catchError((err: any) => {
                if (err.status === 403) {
                    this.notify.error(this.translate.instant('lang.youCannotUnsign'));
                } else {
                    this.notify.handleSoftErrors(err);
                }
                return of(false);
            })
        ).subscribe();
    }

    unSignAttachment(attachment: any) {
        this.http.put('../rest/attachments/' + attachment.res_id + '/unsign', {}).pipe(
            tap(() => {
                this.appDocumentViewer.loadRessource(attachment.res_id, 'attachment');
                this.rightViewerLink = '../rest/attachments/' + attachment.res_id + '/content';
                this.signatureBook.attachments[this.rightSelectedThumbnail].viewerLink = this.rightViewerLink;
                this.signatureBook.attachments[this.rightSelectedThumbnail].status = 'A_TRA';
                this.signatureBook.attachments[this.rightSelectedThumbnail].idToDl = attachment.res_id;
                this.signatureBook.attachments[this.rightSelectedThumbnail].signed = false;
                this.signatureBook.attachments[this.rightSelectedThumbnail].viewerId = attachment.res_id;

                if (this.signatureBook.resList.length > 0) {
                    this.signatureBook.resList[this.signatureBook.resListIndex].allSigned = false;
                }
                if (this.headerTab === 'visaCircuit') {
                    this.changeSignatureBookLeftContent('document');
                    setTimeout(() => {
                        this.changeSignatureBookLeftContent('visaCircuit');
                    }, 0);
                }
            }),
            catchError((err: any) => {
                if (err.status === 403) {
                    this.notify.error(this.translate.instant('lang.youCannotUnsign'));
                } else {
                    this.notify.handleSoftErrors(err);
                }
                return of(false);
            })
        ).subscribe();
    }

    backToBasket() {
        const path = '/basketList/users/' + this.userId + '/groups/' + this.groupId + '/baskets/' + this.basketId;
        this.router.navigate([path]);
    }

    backToDetails() {
        this.http.put('../rest/resourcesList/users/' + this.userId + '/groups/' + this.groupId + '/baskets/' + this.basketId + '/unlock', { resources: [this.resId] })
            .subscribe((data: any) => {
                this.router.navigate([`/resources/${this.resId}`]);
            }, (err: any) => { });
    }

    async changeLocation(resId: number, origin: string) {
        if (resId !== this.resId) {
            const data: any = await this.actionService.canExecuteAction([resId], this.userId, this.groupId, this.basketId);

            if (data === true) {
                this.actionService.stopRefreshResourceLock();
                if (!this.actionService.actionEnded) {
                    this.actionService.unlockResource(this.userId, this.groupId, this.basketId, [this.resId]);
                }
                const path = 'signatureBook/users/' + this.userId + '/groups/' + this.groupId + '/baskets/' + this.basketId + '/resources/' + resId;
                this.router.navigate([path]);
            } else {
                this.backToBasket();
            }
        }
    }

    validForm() {
        if ($('#signatureBookActions option:selected').val() !== '') {
            this.processAction();
        } else {
            alert('Aucune action choisie');
        }
    }

    processAction() {
        this.http.get(`../rest/resources/${this.resId}?light=true`).pipe(
            tap((data: any) => {
                const actionId = $('#signatureBookActions option:selected').val();
                const selectedAction = this.signatureBook.actions.filter((action: any) => action.id == actionId)[0];
                this.actionService.launchAction(selectedAction, this.userId, this.groupId, this.basketId, [this.resId], data, false);
            }),
            catchError((err: any) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    refreshBadge(nbRres: any, id: string) {
        this.processTool.filter(tool => tool.id === id)[0].count = nbRres;
    }

    loadBadges() {
        this.http.get(`../rest/resources/${this.resId}/items`).pipe(
            tap((data: any) => {
                this.processTool.forEach(element => {
                    element.count = data[element.id] !== undefined ? data[element.id] : 0;
                });
            }),
            catchError((err: any) => {
                this.notify.handleSoftErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    createAttachment() {
        this.dialogRef = this.dialog.open(AttachmentCreateComponent, { disableClose: true, panelClass: 'attachment-modal-container', height: '90vh', width: '90vw', data: { resIdMaster: this.resId } });

        this.dialogRef.afterClosed().pipe(
            filter((data: string) => data === 'success'),
            tap(() => {
                this.refreshAttachments('add');
            }),
            catchError((err: any) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    showAttachment(attachment: any) {
        if (this.canUpdateDocument && attachment.status !== 'SIGN') {
            if (attachment.isResource) {
                this.appDocumentViewer.editResource();
            } else {
                this.dialogRef = this.dialog.open(AttachmentPageComponent, { height: '99vh', width: this.appService.getViewMode() ? '99vw' : '90vw', maxWidth: this.appService.getViewMode() ? '99vw' : '90vw', panelClass: 'attachment-modal-container', disableClose: true, data: { resId: attachment.res_id } });

                this.dialogRef.afterClosed().pipe(
                    filter((data: string) => data === 'success'),
                    tap(() => {
                        this.refreshAttachments('edit');
                    }),
                    catchError((err: any) => {
                        this.notify.handleErrors(err);
                        return of(false);
                    })
                ).subscribe();
            }
        }
    }

    saveVisaWorkflow() {
        this.appVisaWorkflow.saveVisaWorkflow();
    }

    downloadOriginalFile(resId: any) {
        const downloadLink = document.createElement('a');
        this.http.get(`../rest/attachments/${resId}/originalContent?mode=base64`).pipe(
            tap((data: any) => {
                downloadLink.href = `data:${data.mimeType};base64,${data.encodedDocument}`;
                downloadLink.setAttribute('download', `${resId}.${data.extension}`);
                document.body.appendChild(downloadLink);
                downloadLink.click();
            }),
            catchError((err: any) => {
                this.notify.handleSoftErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    ngOnDestroy() {
        this.actionService.stopRefreshResourceLock();

        if (!this.actionService.actionEnded) {
            this.actionService.unlockResource(this.userId, this.groupId, this.basketId, [this.resId], this.pathToRedirect);
        }

        // unsubscribe to ensure no memory leaks
        this.subscription.unsubscribe();
    }

    pdfViewerError(viewerLink: any) {
        this.http.get(viewerLink)
            .pipe(
                catchError((err: any) => {
                    if (err.status !== 200) {
                        this.notify.handleSoftErrors(err);
                    }
                    return of(false);
                })
            ).subscribe();
    }

    isToolEnabled(id: string) {
        if (id === 'history') {
            if (!this.privilegeService.hasCurrentUserPrivilege('view_full_history') && !this.privilegeService.hasCurrentUserPrivilege('view_doc_history')) {
                return false;
            } else {
                return true;
            }
        } else {
            return true;
        }
    }
}
