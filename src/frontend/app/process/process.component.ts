import { Component, OnInit, ViewChild, ViewContainerRef, TemplateRef, OnDestroy } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { TranslateService } from '@ngx-translate/core';
import { NotificationService } from '@service/notification/notification.service';
import { MatDialog } from '@angular/material/dialog';
import { MatSidenav } from '@angular/material/sidenav';

import { ActivatedRoute, Router, ParamMap } from '@angular/router';
import { HeaderService } from '@service/header.service';
import { FiltersListService } from '@service/filtersList.service';

import { Overlay } from '@angular/cdk/overlay';
import { AppService } from '@service/app.service';
import { ActionsService } from '../actions/actions.service';
import { tap, catchError, map, finalize, filter, take } from 'rxjs/operators';
import { DocumentViewerComponent } from '../viewer/document-viewer.component';
import { IndexingFormComponent } from '../indexation/indexing-form/indexing-form.component';
import { ConfirmComponent } from '@plugins/modal/confirm.component';
import { ContactResourceModalComponent } from '../contact/contact-resource/modal/contact-resource-modal.component';
import { DiffusionsListComponent } from '../diffusions/diffusions-list.component';

import { ContactService } from '@service/contact.service';
import { VisaWorkflowComponent } from '../visa/visa-workflow.component';
import { PrivilegeService } from '@service/privileges.service';
import { AvisWorkflowComponent } from '../avis/avis-workflow.component';
import { FunctionsService } from '@service/functions.service';
import { PrintedFolderModalComponent } from '../printedFolder/printed-folder-modal.component';
import { of, Subscription } from 'rxjs';
import { TechnicalInformationComponent } from '@appRoot/indexation/technical-information/technical-information.component';
import { NotesListComponent } from '@appRoot/notes/notes-list.component';
import { AuthService } from '@service/auth.service';
import { SessionStorageService } from '@service/session-storage.service';


@Component({
    templateUrl: 'process.component.html',
    styleUrls: [
        'process.component.scss',
        '../indexation/indexing-form/indexing-form.component.scss'
    ],
    providers: [ActionsService, ContactService],
})
export class ProcessComponent implements OnInit, OnDestroy {

    @ViewChild('snav2', { static: true }) sidenavRight: MatSidenav;
    @ViewChild('adminMenuTemplate', { static: true }) adminMenuTemplate: TemplateRef<any>;

    @ViewChild('appDocumentViewer', { static: false }) appDocumentViewer: DocumentViewerComponent;
    @ViewChild('indexingForm', { static: false }) indexingForm: IndexingFormComponent;
    @ViewChild('appDiffusionsList', { static: false }) appDiffusionsList: DiffusionsListComponent;
    @ViewChild('appVisaWorkflow', { static: false }) appVisaWorkflow: VisaWorkflowComponent;
    @ViewChild('appAvisWorkflow', { static: false }) appAvisWorkflow: AvisWorkflowComponent;
    @ViewChild('appNotesList', { static: false }) appNotesList: NotesListComponent;

    loading: boolean = true;
    detailMode: boolean = false;
    isMailing: boolean = false;
    isFromSearch: boolean = false;
    actionsListLoaded: boolean = false;
    blocOpened: boolean = true;
    logoutTrigger: boolean = false;

    canShowDivBrowsing: boolean = false;
    canGoToNext: boolean = false;
    canGoToPrevious: boolean = false;

    canGoToNextRes: any = null;

    actionsList: any[] = [];
    allResources: any[] = [];

    currentUserId: number = null;
    currentBasketId: number = null;
    currentGroupId: number = null;



    selectedAction: any = {
        id: 0,
        label: '',
        component: '',
        default: false,
        categoryUse: []
    };

    currentResourceInformations: any = {};

    prevCategory: string = '';
    currentCategory: string = '';

    processTool: any[] = [
        {
            id: 'dashboard',
            icon: 'fas fa-columns',
            label: this.translate.instant('lang.newsFeed'),
            count: 0
        },
        {
            id: 'history',
            icon: 'fas fa-history',
            label: this.translate.instant('lang.history'),
            count: 0
        },
        {
            id: 'notes',
            icon: 'fas fa-pen-square',
            label: this.translate.instant('lang.notesAlt'),
            count: 0
        },
        {
            id: 'attachments',
            icon: 'fas fa-paperclip',
            label: this.translate.instant('lang.attachments'),
            count: 0
        },
        {
            id: 'linkedResources',
            icon: 'fas fa-link',
            label: this.translate.instant('lang.links'),
            count: 0
        },
        {
            id: 'emails',
            icon: 'fas fa-envelope',
            label: this.translate.instant('lang.mailsSentAlt'),
            count: 0
        },
        {
            id: 'diffusionList',
            icon: 'fas fa-share-alt',
            label: this.translate.instant('lang.diffusionList'),
            editMode: false,
            count: 0
        },
        {
            id: 'visaCircuit',
            icon: 'fas fa-list-ol',
            label: this.translate.instant('lang.visaWorkflow'),
            count: 0
        },
        {
            id: 'opinionCircuit',
            icon: 'fas fa-comment-alt',
            label: this.translate.instant('lang.avis'),
            count: 0
        },
        {
            id: 'info',
            icon: 'fas fa-info-circle',
            label: this.translate.instant('lang.informations'),
            count: 0
        }
    ];

    modalModule: any[] = [];

    currentTool: string = 'dashboard';

    subscription: Subscription;

    actionEnded: boolean = false;

    canEditData: boolean = false;
    canChangeModel: boolean = false;

    autoAction: boolean = false;

    integrationsInfo: any = {
        inSignatureBook: {
            icon: 'fas fa-file-signature',
        }
    };

    senderLightInfo: any = { 'displayName': null, 'fillingRate': null };
    hasContact: boolean = false;

    resourceFollowed: boolean = false;
    resourceFreezed: boolean = false;
    resourceBinded: boolean = false;

    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        public dialog: MatDialog,
        public filtersListService: FiltersListService,
        public overlay: Overlay,
        public viewContainerRef: ViewContainerRef,
        public appService: AppService,
        public actionService: ActionsService,
        public privilegeService: PrivilegeService,
        public functions: FunctionsService,
        public authService: AuthService,
        private headerService: HeaderService,
        private notify: NotificationService,
        private contactService: ContactService,
        private router: Router,
        private route: ActivatedRoute,
        private _activatedRoute: ActivatedRoute,
        private sessionStorage: SessionStorageService
    ) {

        // ngOnInit does not call if navigate in the same component route : must be in constructor for this case
        this.route.params.subscribe(params => {
            this.loading = true;

            this.headerService.sideBarForm = true;
            this.headerService.showhHeaderPanel = true;
            this.headerService.showMenuShortcut = false;
            this.headerService.showMenuNav = false;
            this.headerService.sideBarAdmin = true;

            if (typeof params['detailResId'] !== 'undefined') {
                this.initDetailPage(params);
            } else {
                this.initProcessPage(params);
            }
        }, (err: any) => {
            this.notify.handleErrors(err);
        });


        // Event after process action
        this.subscription = this.actionService.catchAction().subscribe(message => {
            this.actionEnded = true;
            this.router.navigate([`/basketList/users/${this.currentUserId}/groups/${this.currentGroupId}/baskets/${this.currentBasketId}`]);
        });
    }

    ngOnInit(): void {
        this.headerService.injectInSideBarLeft(this.adminMenuTemplate, this.viewContainerRef, 'adminMenu', 'form');
        this.headerService.setHeader(this.translate.instant('lang.eventProcessDoc'));
    }


    checkAccesDocument(resId: number) {
        return new Promise((resolve, reject) => {
            this.http.get(`../rest/resources/${resId}/isAllowed`).pipe(
                tap((data: any) => {
                    if (data.isAllowed) {
                        resolve(true);
                    } else {
                        this.notify.error(this.translate.instant('lang.documentOutOfPerimeter'));
                        this.router.navigate(['/home']);
                    }
                }),
                catchError((err: any) => {
                    this.notify.handleSoftErrors(err);
                    this.router.navigate(['/home']);
                    return of(false);
                })
            )
                .subscribe();
        });
    }

    async initProcessPage(params: any) {

        this.detailMode = false;

        this.currentUserId = params['userSerialId'];
        this.currentGroupId = params['groupSerialId'];
        this.currentBasketId = params['basketId'];

        this.currentResourceInformations = {
            resId: params['resId'],
            mailtracking: false,
        };

        this.headerService.sideBarButton = {
            icon: 'fa fa-inbox',
            label: this.translate.instant('lang.backBasket'),
            route: `/basketList/users/${this.currentUserId}/groups/${this.currentGroupId}/baskets/${this.currentBasketId}`
        };

        await this.checkAccesDocument(this.currentResourceInformations.resId);
        this.loadAllResources();

        this.actionService.lockResource(this.currentUserId, this.currentGroupId, this.currentBasketId, [this.currentResourceInformations.resId]);

        this.loadBadges();
        this.loadResource();

        if (this.appService.getViewMode()) {
            setTimeout(() => {
                this.headerService.sideNavLeft.open();
            }, 800);
        }

        await this.getActions();
    }

    getActions() {
        return new Promise((resolve) => {
            this.http.get(`../rest/resourcesList/users/${this.currentUserId}/groups/${this.currentGroupId}/baskets/${this.currentBasketId}/actions?resId=${this.currentResourceInformations.resId}`).pipe(
                map((data: any) => {
                    data.actions = data.actions.map((action: any, index: number) => ({
                        id: action.id,
                        label: action.label,
                        component: action.component,
                        categoryUse: action.categories
                    }));
                    return data;
                }),
                tap((data: any) => {
                    this.selectedAction = data.actions[0];
                    this.actionsList = data.actions;
                    this.actionsListLoaded = true;
                    resolve(true);
                }),
                catchError((err: any) => {
                    this.notify.handleErrors(err);
                    return of(false);
                })
            ).subscribe();
        });
    }

    async initDetailPage(params: any) {
        this._activatedRoute.queryParamMap.subscribe((paramMap: ParamMap) => {
            this.isMailing = !this.functions.empty(paramMap.get('isMailing'));
            this.isFromSearch = !this.functions.empty(paramMap.get('fromSearch'));
        });

        this.detailMode = true;
        this.currentResourceInformations = {
            resId: params['detailResId'],
            mailtracking: false,
            retentionFrozen : false
        };
        this.headerService.sideBarButton = {
            icon: 'fas fa-arrow-left',
            label: this.translate.instant('lang.back'),
            route: '__GOBACK'
        };

        await this.checkAccesDocument(this.currentResourceInformations.resId);

        this.loadBadges();
        this.loadResource();

        if (this.appService.getViewMode()) {
            setTimeout(() => {
                this.headerService.sideNavLeft.open();
            }, 800);
        }
    }

    isActionEnded() {
        return this.actionEnded;
    }

    loadResource(redirectDefautlTool: boolean = true) {
        if (!this.logoutTrigger) {
            this.http.get(`../rest/resources/${this.currentResourceInformations.resId}?light=true`).pipe(
                tap((data: any) => {
                    this.currentResourceInformations = data;
                    this.resourceFollowed = data.followed;
                    this.resourceBinded = data.binding;
                    this.resourceFreezed = data.retentionFrozen;
                    if (this.currentResourceInformations.categoryId !== 'outgoing') {
                        this.loadSenders();
                    } else {
                        this.loadRecipients();
                    }
                    if (redirectDefautlTool) {
                        this.setEditDataPrivilege();
                    }

                    this.loadAvaibleIntegrations(data.integrations);
                    this.headerService.setHeader(this.detailMode ? this.translate.instant('lang.detailDoc') : this.translate.instant('lang.eventProcessDoc'), this.translate.instant('lang.' + this.currentResourceInformations.categoryId));
                }),
                finalize(() => this.loading = false),
                catchError((err: any) => {
                    this.notify.handleErrors(err);
                    return of(false);
                })
            ).subscribe();
        }
    }

    setEditDataPrivilege() {
        if (this.detailMode) {
            if (this.isFromSearch) {
                this.http.get('../rest/search/configuration').pipe(
                    tap((myData: any) => {
                        if (myData.configuration.listEvent.defaultTab == null) {
                            this.currentTool = 'dashboard';
                        } else {
                            this.currentTool = myData.configuration.listEvent.defaultTab;
                        }
                    }),
                    catchError((err: any) => {
                        this.notify.handleErrors(err);
                        return of(false);
                    })
                ).subscribe();
            }
            this.canEditData = this.privilegeService.hasCurrentUserPrivilege('edit_resource') && this.currentResourceInformations.statusAlterable && this.functions.empty(this.currentResourceInformations.registeredMail_deposit_id);
            if (this.isMailing && this.isToolEnabled('attachments')) {
                this.currentTool = 'attachments';

                // Avoid auto open if the user click one more time on tab attachments
                setTimeout(() => {
                    this.isMailing = false;
                }, 200);
            }
        } else {
            this.http.get(`../rest/resources/${this.currentResourceInformations.resId}/users/${this.currentUserId}/groups/${this.currentGroupId}/baskets/${this.currentBasketId}/processingData`).pipe(
                tap((data: any) => {
                    if (data.listEventData !== null) {
                        if (this.isToolEnabled(data.listEventData.defaultTab)) {
                            this.currentTool = !this.functions.empty(this.sessionStorage.get('currentTool')) ? this.sessionStorage.get('currentTool') : data.listEventData.defaultTab;
                        }
                        this.canEditData = data.listEventData.canUpdateData && this.functions.empty(this.currentResourceInformations.registeredMail_deposit_id);
                        this.canChangeModel = data.listEventData.canUpdateModel;
                        this.canGoToNextRes = !this.functions.empty(data.listEventData.canGoToNextRes) ? data.listEventData.canGoToNextRes : null;
                        this.currentResourceInformations = {... this.currentResourceInformations, canGoToNextRes: this.canGoToNextRes};
                    }
                }),
                catchError((err: any) => {
                    this.notify.handleErrors(err);
                    return of(false);
                })
            ).subscribe();
        }
    }

    loadAvaibleIntegrations(integrationsData: any) {
        this.integrationsInfo['inSignatureBook'].enable = !this.functions.empty(integrationsData['inSignatureBook']) ? integrationsData['inSignatureBook'] : false;

        this.http.get('../rest/externalConnectionsEnabled').pipe(
            tap((data: any) => {
                Object.keys(data.connection).filter(connectionId => connectionId !== 'maarchParapheur').forEach(connectionId => {
                    if (connectionId === 'maileva') {
                        this.integrationsInfo['inShipping'] = {
                            icon: 'fas fa-shipping-fast'
                        };
                    }
                });
            }),
            catchError((err: any) => {
                this.notify.handleSoftErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    toggleIntegration(integrationId: string) {
        this.http.put('../rest/resourcesList/integrations', { resources: [this.currentResourceInformations.resId], integrations: { [integrationId]: !this.currentResourceInformations.integrations[integrationId] } }).pipe(
            tap(() => {
                this.currentResourceInformations.integrations[integrationId] = !this.currentResourceInformations.integrations[integrationId];
                this.notify.success(this.translate.instant('lang.actionDone'));
            }),
            catchError((err: any) => {
                this.notify.handleSoftErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    loadBadges() {
        this.http.get(`../rest/resources/${this.currentResourceInformations.resId}/items`).pipe(
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

    loadSenders() {

        if (this.currentResourceInformations.senders === undefined || this.currentResourceInformations.senders.length === 0) {
            this.hasContact = false;
            this.senderLightInfo = { 'displayName': this.translate.instant('lang.noSelectedContact'), 'filling': null };
        } else if (this.currentResourceInformations.senders.length === 1) {
            this.hasContact = true;
            if (this.currentResourceInformations.senders[0].type === 'contact') {
                this.http.get('../rest/contacts/' + this.currentResourceInformations.senders[0].id).pipe(
                    tap((data: any) => {
                        const arrInfo = [];
                        if (this.empty(data.firstname) && this.empty(data.lastname)) {
                            if (!this.functions.empty(data.fillingRate)) {
                                this.senderLightInfo = { 'displayName': data.company, 'filling': this.contactService.getFillingColor(data.fillingRate.thresholdLevel) };
                            } else {
                                this.senderLightInfo = { 'displayName': data.company };
                            }

                        } else {
                            arrInfo.push(data.firstname);
                            arrInfo.push(data.lastname);
                            if (!this.empty(data.company)) {
                                arrInfo.push('(' + data.company + ')');
                            }
                            if (!this.functions.empty(data.fillingRate)) {
                                this.senderLightInfo = { 'displayName': arrInfo.filter(info => info !== '').join(' '), 'filling': this.contactService.getFillingColor(data.fillingRate.thresholdLevel) };
                            } else {
                                this.senderLightInfo = { 'displayName': arrInfo.filter(info => info !== '').join(' ') };
                            }

                        }
                    })
                ).subscribe();
            } else if (this.currentResourceInformations.senders[0].type === 'entity') {
                this.http.get('../rest/entities/' + this.currentResourceInformations.senders[0].id).pipe(
                    tap((data: any) => {
                        this.senderLightInfo = { 'displayName': data.entity_label, 'filling': null };
                    })
                ).subscribe();
            } else if (this.currentResourceInformations.senders[0].type === 'user') {
                this.http.get('../rest/users/' + this.currentResourceInformations.senders[0].id).pipe(
                    tap((data: any) => {
                        this.senderLightInfo = { 'displayName': data.firstname + ' ' + data.lastname, 'filling': null };
                    })
                ).subscribe();
            }
        } else if (this.currentResourceInformations.senders.length > 1) {
            this.hasContact = true;
            this.senderLightInfo = { 'displayName': this.currentResourceInformations.senders.length + ' ' + this.translate.instant('lang.senders'), 'filling': null };
        }
    }

    loadRecipients() {

        if (this.currentResourceInformations.recipients === undefined || this.currentResourceInformations.recipients.length === 0) {
            this.hasContact = false;
            this.senderLightInfo = { 'displayName': this.translate.instant('lang.noSelectedContact'), 'filling': null };
        } else if (this.currentResourceInformations.recipients.length === 1) {
            this.hasContact = true;
            if (this.currentResourceInformations.recipients[0].type === 'contact') {
                this.http.get('../rest/contacts/' + this.currentResourceInformations.recipients[0].id).pipe(
                    tap((data: any) => {
                        const arrInfo = [];
                        if (this.empty(data.firstname) && this.empty(data.lastname)) {
                            if (!this.functions.empty(data.fillingRate)) {
                                this.senderLightInfo = { 'displayName': data.company, 'filling': this.contactService.getFillingColor(data.fillingRate.thresholdLevel) };
                            } else {
                                this.senderLightInfo = { 'displayName': data.company };
                            }

                        } else {
                            arrInfo.push(data.firstname);
                            arrInfo.push(data.lastname);
                            if (!this.empty(data.company)) {
                                arrInfo.push('(' + data.company + ')');
                            }
                            if (!this.functions.empty(data.fillingRate)) {
                                this.senderLightInfo = { 'displayName': arrInfo.filter(info => info !== '').join(' '), 'filling': this.contactService.getFillingColor(data.fillingRate.thresholdLevel) };
                            } else {
                                this.senderLightInfo = { 'displayName': arrInfo.filter(info => info !== '').join(' ') };
                            }

                        }
                    })
                ).subscribe();
            } else if (this.currentResourceInformations.recipients[0].type === 'entity') {
                this.http.get('../rest/entities/' + this.currentResourceInformations.recipients[0].id).pipe(
                    tap((data: any) => {
                        this.senderLightInfo = { 'displayName': data.entity_label, 'filling': null };
                    })
                ).subscribe();
            } else if (this.currentResourceInformations.recipients[0].type === 'user') {
                this.http.get('../rest/users/' + this.currentResourceInformations.recipients[0].id).pipe(
                    tap((data: any) => {
                        this.senderLightInfo = { 'displayName': data.firstname + ' ' + data.lastname, 'filling': null };
                    })
                ).subscribe();
            }
        } else if (this.currentResourceInformations.recipients.length > 1) {
            this.hasContact = true;
            this.senderLightInfo = { 'displayName': this.currentResourceInformations.recipients.length + ' ' + this.translate.instant('lang.recipients'), 'filling': null };
        }
    }

    onSubmit() {
        if (this.currentTool === 'info' || this.isModalOpen('info')) {
            this.processAction();
        } else {
            if (this.isToolModified()) {
                const dialogRef = this.openConfirmModification();
                dialogRef.afterClosed().pipe(
                    filter((data: string) => data === 'ok'),
                    tap(() => {
                        this.saveTool();
                    }),
                    finalize(() => {
                        this.autoAction = true;
                        this.currentTool = 'info';
                    }),
                    catchError((err: any) => {
                        this.notify.handleErrors(err);
                        return of(false);
                    })
                ).subscribe();
            } else {
                this.autoAction = true;
                this.currentTool = 'info';
            }
        }
    }

    triggerProcessAction() {
        if (this.autoAction) {
            this.processAction();
            this.autoAction = !this.autoAction;
        }
    }

    async processAction() {
        if (this.indexingForm.isValidForm()) {
            this.actionService.loading = true;
            if (this.isToolModified()) {
                const dialogRef = this.openConfirmModification();
                dialogRef.afterClosed().pipe(
                    tap((data: string) => {
                        if (data !== 'ok') {
                            this.refreshTool();
                            this.actionService.loading = false;
                        }
                    }),
                    tap(async (data: string) => {
                        if (data === 'ok') {
                            await this.saveTool();
                        }
                        if (this.appDocumentViewer.isEditingTemplate()) {
                            await this.appDocumentViewer.saveMainDocument();
                        }
                        this.canLaunchAction();
                    }),
                    catchError((err: any) => {
                        this.notify.handleSoftErrors(err);
                        this.actionService.loading = false;
                        return of(false);
                    })
                ).subscribe();
            } else {
                if (this.appDocumentViewer.isEditingTemplate()) {
                    await this.appDocumentViewer.saveMainDocument();
                }
                this.canLaunchAction();
            }
        } else {
            this.notify.error(this.translate.instant('lang.mustFixErrors'));
        }
    }

    showActionInCurrentCategory(action: any) {

        if (this.selectedAction.categoryUse.indexOf(this.currentResourceInformations.categoryId) === -1) {
            const newAction = this.actionsList.filter(actionItem => actionItem.categoryUse.indexOf(this.currentResourceInformations.categoryId) > -1)[0];
            if (newAction !== undefined) {
                this.selectedAction = this.actionsList.filter(actionItem => actionItem.categoryUse.indexOf(this.currentResourceInformations.categoryId) > -1)[0];
            } else {
                this.selectedAction = {
                    id: 0,
                    label: '',
                    component: '',
                    default: false,
                    categoryUse: []
                };
            }
        }
        return action.categoryUse.indexOf(this.currentResourceInformations.categoryId) > -1;
    }

    selectAction(action: any) {
        this.selectedAction = action;
    }

    createModal() {
        this.modalModule.push(this.processTool.filter(module => module.id === this.currentTool)[0]);
    }

    openTechnicalInfo() {
        this.dialog.open(TechnicalInformationComponent, { panelClass: 'maarch-modal', autoFocus: false, data: { resId : this.currentResourceInformations.resId} });
    }

    removeModal(index: number) {
        if (this.modalModule[index].id === 'info' && this.indexingForm.isResourceModified()) {
            const dialogRef = this.openConfirmModification();

            dialogRef.afterClosed().pipe(
                tap((data: string) => {
                    if (data !== 'ok') {
                        this.modalModule.splice(index, 1);
                    }
                }),
                filter((data: string) => data === 'ok'),
                tap(() => {
                    this.indexingForm.saveData();
                    setTimeout(() => {
                        this.loadResource(false);
                    }, 400);
                    this.modalModule.splice(index, 1);
                }),
                catchError((err: any) => {
                    this.notify.handleErrors(err);
                    return of(false);
                })
            ).subscribe();
        } else {
            this.modalModule.splice(index, 1);
        }
    }

    isModalOpen(tool = this.currentTool) {
        return this.modalModule.map(module => module.id).indexOf(tool) > -1;
    }

    async ngOnDestroy() {
        if (!this.detailMode && !this.logoutTrigger) {
            this.actionService.stopRefreshResourceLock();
            if (!this.actionService.actionEnded) {
                await this.actionService.unlockResource(this.currentUserId, this.currentGroupId, this.currentBasketId, [this.currentResourceInformations.resId]);
            }
        }
        // unsubscribe to ensure no memory leaks
        this.subscription.unsubscribe();
        // Remove the temporarily saved item in the session
        this.sessionStorage.remove('currentTool');
    }

    changeTab(tabId: string) {
        if (this.isToolModified() && !this.isModalOpen()) {
            const dialogRef = this.openConfirmModification();

            dialogRef.afterClosed().pipe(
                tap((data: string) => {
                    if (data !== 'ok') {
                        this.currentTool = tabId;
                        this.currentResourceInformations.categoryId  = !this.functions.empty(this.prevCategory) ? this.prevCategory : this.currentResourceInformations.categoryId;
                    }
                }),
                filter((data: string) => data === 'ok'),
                tap(() => {
                    this.saveTool();
                    if (!this.indexingForm?.mustFixErrors) {
                        setTimeout(() => {
                            this.loadResource(false);
                        }, 400);
                        this.currentTool = tabId;
                        this.currentResourceInformations.categoryId = !this.functions.empty(this.currentCategory) ? this.currentCategory : this.currentResourceInformations.categoryId;
                        this.prevCategory = this.currentResourceInformations.categoryId;
                    }
                }),
                catchError((err: any) => {
                    this.notify.handleErrors(err);
                    return of(false);
                })
            ).subscribe();
        } else {
            this.currentTool = tabId;
        }
    }

    openConfirmModification() {
        return this.dialog.open(ConfirmComponent, { panelClass: 'maarch-modal', autoFocus: false, disableClose: true, data: { title: this.translate.instant('lang.confirm'), msg: this.translate.instant('lang.saveModifiedData'), buttonValidate: this.translate.instant('lang.yes'), buttonCancel: this.translate.instant('lang.no') } });
    }

    confirmModification() {
        this.indexingForm.saveData();
        setTimeout(() => {
            this.loadResource(false);
        }, 400);
    }

    async saveModificationBeforeClose() {
        if (this.isToolModified() && !this.isModalOpen()) {
            await this.saveTool();
        }

        if (this.appDocumentViewer.isEditingTemplate()) {
            await this.appDocumentViewer.saveMainDocument();
        }
    }

    refreshData() {
        this.appDocumentViewer.loadRessource(this.currentResourceInformations.resId);
    }

    refreshBadge(nbRres: any, id: string) {
        this.processTool.filter(tool => tool.id === id)[0].count = nbRres;
    }

    openContact() {
        if (this.hasContact) {
            this.dialog.open(ContactResourceModalComponent, { panelClass: 'maarch-modal', data: { title: `${this.currentResourceInformations.chrono} - ${this.currentResourceInformations.subject}`, mode: this.currentResourceInformations.categoryId !== 'outgoing' ? 'senders' : 'recipients', resId: this.currentResourceInformations.resId } });
        }
    }

    saveListinstance() {
        this.appDiffusionsList.saveListinstance();
    }

    saveVisaWorkflow() {
        this.appVisaWorkflow.saveVisaWorkflow();
    }

    isToolModified() {
        if (this.currentTool === 'info' && this.indexingForm !== undefined && this.indexingForm.isResourceModified()) {
            return true;
        } else if (this.currentTool === 'diffusionList' && this.appDiffusionsList !== undefined && this.appDiffusionsList.isModified()) {
            return true;
        } else if (this.currentTool === 'visaCircuit' && this.appVisaWorkflow !== undefined && this.appVisaWorkflow.isModified()) {
            return true;
        } else if (this.currentTool === 'opinionCircuit' && this.appAvisWorkflow !== undefined && this.appAvisWorkflow.isModified()) {
            return true;
        } else if (this.currentTool === 'notes' && this.appNotesList !== undefined && this.appNotesList.isModified()) {
            return true;
        } else {
            return false;
        }
    }

    refreshTool() {
        const tmpTool = this.currentTool;
        this.currentTool = '';
        setTimeout(() => {
            this.currentTool = tmpTool;
        }, 0);
    }

    async saveTool() {
        if (this.currentTool === 'info' && this.indexingForm !== undefined) {
            this.appDocumentViewer.getFile().pipe(
                take(1),
                tap(async (data: any) => {
                    if (this.functions.empty(data.contentView) && this.indexingForm.mandatoryFile) {
                        this.notify.error(this.translate.instant('lang.mandatoryFile'));
                    } else {
                        if (this.indexingForm.isValidForm()) {
                            this.currentResourceInformations.categoryId = !this.functions.empty(this.currentCategory) ? this.currentCategory : this.currentResourceInformations.categoryId;
                            this.prevCategory = this.currentResourceInformations.categoryId;
                            this.actionService.loading = false;
                        }
                        await this.indexingForm.saveData();
                        if (!this.detailMode) {
                            await this.getActions();
                        }
                        setTimeout(() => {
                            this.loadResource(false);
                        }, 400);
                    }
                })
            ).subscribe();
        } else if (this.currentTool === 'diffusionList' && this.appDiffusionsList !== undefined) {
            await this.appDiffusionsList.saveListinstance();
            this.loadBadges();
        } else if (this.currentTool === 'visaCircuit' && this.appVisaWorkflow !== undefined) {
            await this.appVisaWorkflow.saveVisaWorkflow();
            this.loadBadges();
        } else if (this.currentTool === 'opinionCircuit' && this.appAvisWorkflow !== undefined) {
            await this.appAvisWorkflow.saveAvisWorkflow();
            this.loadBadges();
        } else if (this.currentTool === 'notes' && this.appNotesList !== undefined) {
            this.appNotesList.addNote();
            this.loadBadges();
        }
    }

    empty(value: string) {

        if (value === null || value === undefined) {
            return true;

        } else if (Array.isArray(value)) {
            if (value.length > 0) {
                return false;
            } else {
                return true;
            }
        } else if (String(value) !== '') {
            return false;
        } else {
            return true;
        }
    }

    toggleFollow() {
        this.resourceFollowed = !this.resourceFollowed;

        if (this.resourceFollowed) {
            this.http.post('../rest/resources/follow', { resources: [this.currentResourceInformations.resId] }).pipe(
                tap(() => this.headerService.nbResourcesFollowed++),
                catchError((err: any) => {
                    this.notify.handleErrors(err);
                    return of(false);
                })
            ).subscribe();
        } else {
            this.http.request('DELETE', '../rest/resources/unfollow', { body: { resources: [this.currentResourceInformations.resId] } }).pipe(
                tap(() => this.headerService.nbResourcesFollowed--),
                catchError((err: any) => {
                    this.notify.handleErrors(err);
                    return of(false);
                })
            ).subscribe();
        }
    }

    toggleFreezing() {
        this.resourceFreezed = !this.resourceFreezed;
        this.http.put('../rest/archival/freezeRetentionRule', { resources: [this.currentResourceInformations.resId], freeze : this.resourceFreezed }).pipe(
            tap(() => {
                if (this.resourceFreezed) {
                    this.notify.success(this.translate.instant('lang.retentionRuleFrozen'));
                } else {
                    this.notify.success(this.translate.instant('lang.retentionRuleUnfrozen'));
                }
            }
            ),
            catchError((err: any) => {
                this.resourceFreezed = !this.resourceFreezed;
                this.notify.handleSoftErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    toggleBinding(value) {
        this.resourceBinded = value;
        this.http.put('../rest/archival/binding', { resources: [this.currentResourceInformations.resId], binding : value }).pipe(
            tap(() => {
                if (value) {
                    this.notify.success(this.translate.instant('lang.bindingMail'));
                } else if (value === false) {
                    this.notify.success(this.translate.instant('lang.noBindingMail'));
                } else {
                    this.notify.success(this.translate.instant('lang.bindingUndefined'));

                }
            }
            ),
            catchError((err: any) => {
                this.resourceBinded = !this.resourceBinded;
                this.notify.handleSoftErrors(err);
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

    openPrintedFolderPrompt() {
        this.dialog.open(PrintedFolderModalComponent, { panelClass: 'maarch-modal', data: { resId: [this.currentResourceInformations.resId], multiple: false } });
    }

    async unlockResource() {
        if (!this.detailMode && !this.logoutTrigger) {
            this.actionService.stopRefreshResourceLock();
            if (!this.actionService.actionEnded) {
                await this.actionService.unlockResource(this.currentUserId, this.currentGroupId, this.currentBasketId, [this.currentResourceInformations.resId]);
            }
        }
        // unsubscribe to ensure no memory leaks
        this.subscription.unsubscribe();
    }

    hasActions() {
        return this.loading ? true : this.actionsList.filter(action => action.categoryUse.indexOf(this.currentResourceInformations.categoryId) > -1).length > 0;
    }

    setValues(event: any) {
        this.prevCategory = event.prevCategory;
        this.currentCategory = event.indexingModel.category;
    }

    canLaunchAction() {
        const currentActions: any[] = this.actionsList.filter((action: any) => action.categoryUse.indexOf(this.currentResourceInformations.categoryId) > -1);
        if (currentActions.length > 0 && currentActions.find((action: any) => action.id === this.selectedAction.id) !== undefined) {
            this.actionService.loading = true;
            this.actionService.launchAction(this.selectedAction, this.currentUserId, this.currentGroupId, this.currentBasketId, [this.currentResourceInformations.resId], this.currentResourceInformations, false);
        }
    }

    loadAllResources() {
        this.actionService.loadResources(this.currentUserId, this.currentGroupId, this.currentBasketId).pipe(
            tap((data: any) => {
                this.canShowDivBrowsing = data.allResources.length > 1;
                if (this.canShowDivBrowsing) {
                    this.allResources = data.allResources;
                    const index: number = this.allResources.indexOf(parseInt(this.currentResourceInformations.resId, 10));
                    this.canGoToNext = !this.functions.empty(this.allResources[index + 1]);
                    this.canGoToPrevious = !this.functions.empty(this.allResources[index - 1]);
                }
            }),
            catchError((err: any) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    goToResource(event: string = 'next' || 'previous') {
        this.sessionStorage.save('currentTool', this.currentTool);
        const index: number = this.allResources.indexOf(parseInt(this.currentResourceInformations.resId, 10));
        if (event === 'next') {
            this.router.navigate(['/process/users/' + this.currentUserId + '/groups/' + this.currentGroupId + '/baskets/' + this.currentBasketId + '/resId/' + this.allResources[index + 1]]);
        } else if (event === 'previous') {
            this.router.navigate(['/process/users/' + this.currentUserId + '/groups/' + this.currentGroupId + '/baskets/' + this.currentBasketId + '/resId/' + this.allResources[index - 1]]);
        }
    }
}
