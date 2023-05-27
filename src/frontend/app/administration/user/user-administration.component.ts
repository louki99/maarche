import { Component, OnInit, NgZone, ViewChild, Inject, TemplateRef, ViewContainerRef } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { ActivatedRoute, Router } from '@angular/router';
import { TranslateService } from '@ngx-translate/core';
import { MatDialog, MatDialogRef, MAT_DIALOG_DATA } from '@angular/material/dialog';
import { MatPaginator } from '@angular/material/paginator';
import { MatSidenav } from '@angular/material/sidenav';
import { MatSort } from '@angular/material/sort';
import { MatTableDataSource } from '@angular/material/table';
import { UntypedFormGroup, Validators, AbstractControl, ValidationErrors, ValidatorFn, UntypedFormBuilder } from '@angular/forms';
import { NotificationService } from '@service/notification/notification.service';
import { HeaderService } from '@service/header.service';
import { SelectionModel } from '@angular/cdk/collections';
import { AccountLinkComponent } from './account-link/account-link.component';
import { AppService } from '@service/app.service';
import { PrivilegeService } from '@service/privileges.service';
import { MaarchFlatTreeComponent } from '../../../plugins/tree/maarch-flat-tree.component';
import { environment } from '../../../environments/environment';
import { InputCorrespondentGroupComponent } from '../contact/group/inputCorrespondent/input-correspondent-group.component';
import { AuthService } from '@service/auth.service';
import { ConfirmComponent } from '@plugins/modal/confirm.component';
import { catchError, exhaustMap, filter, tap } from 'rxjs/operators';
import { of } from 'rxjs';
import { ExternalSignatoryBookManagerService } from '@service/externalSignatoryBook/external-signatory-book-manager.service';
import { FunctionsService } from '@service/functions.service';

declare let $: any;

@Component({
    templateUrl: 'user-administration.component.html',
    styleUrls: ['user-administration.component.scss'],
    providers: [ExternalSignatoryBookManagerService]
})
export class UserAdministrationComponent implements OnInit {

    @ViewChild('snav2', { static: true }) public sidenavRight: MatSidenav;
    @ViewChild('adminMenuTemplate', { static: true }) adminMenuTemplate: TemplateRef<any>;
    @ViewChild('maarchTree', { static: false }) maarchTree: MaarchFlatTreeComponent;
    @ViewChild('appInputCorrespondentGroup', { static: false }) appInputCorrespondentGroup: InputCorrespondentGroupComponent;
    @ViewChild(MatPaginator, { static: false }) paginator: MatPaginator;
    @ViewChild(MatSort, { static: false }) sort: MatSort;

    loading: boolean = false;
    dialogRef: MatDialogRef<any>;
    highlightMe: boolean = false;
    config: any = {};
    serialId: number;
    userId: string;
    mode: string = '';
    user: any = {
        mode: 'standard',
        authorizedApi: ''
    };
    _search: string = '';
    creationMode: boolean;

    signatureModel: any = {
        base64: '',
        base64ForJs: '',
        name: '',
        type: '',
        size: 0,
        label: '',
    };
    userAbsenceModel: any[] = [];
    userList: any[] = [];
    externalSignatoryBookLink: any = {
        login: '',
        picture: ''
    };
    selectedSignature: number = -1;
    selectedSignatureLabel: string = '';
    loadingSign: boolean = false;
    data: any[] = [];
    currentYear: number = new Date().getFullYear();
    currentMonth: number = new Date().getMonth() + 1;
    minDate: Date = new Date();
    firstFormGroup: UntypedFormGroup;
    ruleText: string = '';
    otherRuleText: string;
    validPassword: boolean = false;
    showPassword: boolean = false;
    hidePassword: boolean = true;
    passwordModel: any = {
        currentPassword: '',
        newPassword: '',
        reNewPassword: ''
    };
    passwordRules: any = {
        minLength: { enabled: false, value: 0 },
        complexityUpper: { enabled: false, value: 0 },
        complexityNumber: { enabled: false, value: 0 },
        complexitySpecial: { enabled: false, value: 0 },
        renewal: { enabled: false, value: 0 },
        historyLastUse: { enabled: false, value: 0 }
    };

    displayedColumns = ['event_date', 'record_id', 'info', 'remote_ip'];
    dataSource = new MatTableDataSource(this.data);
    selectedTabIndex: number = 0;
    externalSignatoryBookConnectionStatus = true;

    canViewPersonalDatas: boolean = false;
    canManagePersonalDatas: boolean = false;

    adminModes: any[] = [
        {
            id: 'standard',
            label: this.translate.instant('lang.standard')
        },
        {
            id: 'root_visible',
            label: this.translate.instant('lang.root_visible')
        },
        {
            id: 'root_invisible',
            label: this.translate.instant('lang.root_invisible')
        },
        {
            id: 'rest',
            label: this.translate.instant('lang.rest')
        }
    ];

    docApiUrl: string = this.functions.getDocBaseUrl() + '/guat/guat_architecture/API_REST/home.html';

    // Redirect Baskets
    selectionBaskets = new SelectionModel<Element>(true, []);

    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        public dialog: MatDialog,
        public headerService: HeaderService,
        public appService: AppService,
        public authService: AuthService,
        public functions: FunctionsService,
        public externalSignatoryBook: ExternalSignatoryBookManagerService,
        private privilegeService: PrivilegeService,
        private viewContainerRef: ViewContainerRef,
        private route: ActivatedRoute,
        private router: Router,
        private zone: NgZone,
        private notify: NotificationService,
        private _formBuilder: UntypedFormBuilder,
    ) {
        window['angularUserAdministrationComponent'] = {
            componentAfterUpload: (base64Content: any) => this.processAfterUpload(base64Content),
        };
    }

    applyFilter(filterValue: string) {
        filterValue = filterValue.trim();
        filterValue = filterValue.toLowerCase();
        this.dataSource.filter = filterValue;
    }

    masterToggleBaskets(event: any) {
        if (event.checked) {
            this.user.baskets.forEach((basket: any) => {
                if (!basket.userToDisplay) {
                    this.selectionBaskets.select(basket);
                }
            });
        } else {
            this.selectionBaskets.clear();
        }
    }

    ngOnInit(): void {
        this.loading = true;

        this.headerService.injectInSideBarLeft(this.adminMenuTemplate, this.viewContainerRef, 'adminMenu');

        this.route.params.subscribe((params: any) => {

            if (typeof params['id'] === 'undefined') {

                this.headerService.setHeader(this.translate.instant('lang.userCreation'));
                this.creationMode = true;
                this.canViewPersonalDatas = false;
                this.canManagePersonalDatas = this.privilegeService.hasCurrentUserPrivilege('manage_personal_data');
                this.loading = false;
            } else {

                this.creationMode = false;
                this.serialId = params['id'];
                this.http.get('../rest/users/' + this.serialId + '/details')
                    .subscribe(async (data: any) => {
                        this.user = data;

                        if (this.user.mode === 'rest') {
                            this.user.authorizedApi = this.user.authorizedApi.join('\n');
                        }

                        if (this.headerService.user.id === this.user.id) {
                            this.canViewPersonalDatas = true;
                            this.canManagePersonalDatas = true;
                        } else {
                            this.canViewPersonalDatas = this.privilegeService.hasCurrentUserPrivilege('view_personal_data');
                            this.canManagePersonalDatas = this.privilegeService.hasCurrentUserPrivilege('manage_personal_data');
                        }

                        if (this.canManagePersonalDatas) {
                            this.canViewPersonalDatas = true;
                        }
                        if (!this.canViewPersonalDatas) {
                            this.user.phone = '****';
                        }
                        this.data = data.history;
                        this.userId = data.user_id;
                        this.minDate = new Date(this.currentYear + '-' + this.currentMonth + '-01');
                        this.headerService.setHeader(this.translate.instant('lang.userModification'), data.firstname + ' ' + data.lastname);

                        if (this.user.external_id[this.externalSignatoryBook.signatoryBookEnabled] !== undefined) {
                            this.loading = true;
                            this.checkInfoExternalSignatoryBookAccount();
                        }

                        this.loading = false;
                        setTimeout(() => {
                            this.dataSource = new MatTableDataSource(this.data);
                            this.dataSource.paginator = this.paginator;
                            this.dataSource.sort = this.sort;
                        }, 0);
                    }, (err) => {
                        this.notify.handleErrors(err);
                    });
            }
        });
    }

    async checkInfoExternalSignatoryBookAccount() {
        const data: any = await this.externalSignatoryBook.checkInfoExternalSignatoryBookAccount(this.serialId);
        if (!this.functions.empty(data)) {
            this.externalSignatoryBookLink.login = data.link;
            this.loading = false;
            if (this.externalSignatoryBookLink.login !== '') {
                this.getUserAvatar(this.user.external_id[this.authService?.externalSignatoryBook?.id]);
            } else {
                this.externalSignatoryBookConnectionStatus = false;
            }
        }
    }

    linkExternalSignatoryBookAccount() {
        const dialogRef = this.dialog.open(AccountLinkComponent,
            {
                panelClass: 'maarch-modal',
                autoFocus: false,
                disableClose: true,
                data: {
                    user: this.user,
                    title: this.getLabelById('linkAccount')
                }
            });
        dialogRef.afterClosed().subscribe(result => {
            if (result) {
                if (result.inExternalSignatoryBook) {
                    this.linkAccountToSignatoryBook(result);
                } else {
                    this.createExternalSignatoryBookAccount(result, result.login);
                }

            }
        });
    }

    async linkAccountToSignatoryBook(result: any) {
        const data: any = await this.externalSignatoryBook.linkAccountToSignatoryBook(result, this.serialId);
        if (data) {
            this.user.canLinkToExternalSignatoryBook = false;
            this.user.external_id[this.externalSignatoryBook.signatoryBookEnabled] = result.id;
            this.checkInfoExternalSignatoryBookAccount();
        }
    }

    async createExternalSignatoryBookAccount(result: any, login: string) {
        const data: any = await this.externalSignatoryBook.createExternalSignatoryBookAccount(result.id, login, this.serialId);
        if (!this.functions.empty(data)) {
            this.user.canLinkToExternalSignatoryBook = false;
            this.user.external_id[this.externalSignatoryBook.signatoryBookEnabled] = data.externalId;
            this.checkInfoExternalSignatoryBookAccount();
        }
    }

    async getUserAvatar(externalId: number) {
        this.externalSignatoryBookLink.picture = await this.externalSignatoryBook.getUserAvatar(externalId);
    }

    unlinkSignatoryBookAccount() {
        const dialogRef = this.dialog.open(ConfirmComponent,
            {
                panelClass: 'maarch-modal',
                autoFocus: false,
                disableClose: true,
                data: {
                    title: `${this.translate.instant('lang.unlinkAccount')}`,
                    msg: this.translate.instant('lang.confirmAction')
                }
            });
        dialogRef.afterClosed().pipe(
            filter((data: string) => data === 'ok'),
            exhaustMap(async () => await this.externalSignatoryBook.unlinkSignatoryBookAccount(this.serialId)),
            tap(() => {
                this.user.canLinkToExternalSignatoryBook = true;
                this.externalSignatoryBookLink.login = '';
                this.externalSignatoryBookLink.picture = '';
                this.notify.success(this.translate.instant('lang.accountUnlinked'));
                this.externalSignatoryBookConnectionStatus = true;
            }),
            catchError((err: any) => {
                this.notify.handleSoftErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    toogleRedirect(basket: any) {
        $('#redirectUser_' + basket.group_id + '_' + basket.basket_id).toggle();

        this.http.get('../rest/users')
            .subscribe((data: any) => {
                // this.userList = data['users'];

            }, (err) => {
                this.notify.handleErrors(err);
            });
    }

    initService() {
        if (this.maarchTree.rawData.length === 0) {
            this.maarchTree.initData(this.user.allEntities.map((ent: any) => ({
                ...ent,
                parent_id : ent.parent,
            })));
        }
        /* if ($('.jstree-container-ul').length === 0) {
            $('#jstree').jstree({
                'checkbox': {
                    'three_state': false // no cascade selection
                },
                'core': {
                    force_text: true,
                    'themes': {
                        'name': 'proton',
                        'responsive': true
                    },
                    'data': this.user.allEntities
                },
                'plugins': ['checkbox', 'search']
            });
            $('#jstree')
                // listen for event
                .on('select_node.jstree', (e: any, data: any) => {
                    if (this.mode === '') {
                        this.addEntity(data.node.id);
                    }
                }).on('deselect_node.jstree', (e: any, data: any) => {
                    this.deleteEntity(data.node.id);
                })
                // create the instance
                .jstree();

            let to: any = false;
            $('#jstree_search').keyup(function () {
                if (to) { clearTimeout(to); }
                to = setTimeout(function () {
                    const v: any = $('#jstree_search').val();
                    $('#jstree').jstree(true).search(v);
                }, 250);
            });
        }*/
    }

    processAfterUpload(b64Content: any) {
        this.zone.run(() => this.resfreshUpload(b64Content));
    }

    resfreshUpload(b64Content: any) {
        if (this.signatureModel.size <= 2000000) {
            this.signatureModel.base64 = b64Content.replace(/^data:.*?;base64,/, '');
            this.signatureModel.base64ForJs = b64Content;
        } else {
            this.signatureModel.name = '';
            this.signatureModel.size = 0;
            this.signatureModel.type = '';
            this.signatureModel.base64 = '';
            this.signatureModel.base64ForJs = '';

            this.notify.error('Taille maximum de fichier dépassée (2 MB)');
        }
    }

    clickOnUploader(id: string) {
        $('#' + id).click();
    }

    uploadSignatureTrigger(fileInput: any) {
        if (fileInput.target.files && fileInput.target.files[0]) {
            const reader = new FileReader();

            this.signatureModel.name = fileInput.target.files[0].name;
            this.signatureModel.size = fileInput.target.files[0].size;
            this.signatureModel.type = fileInput.target.files[0].type;
            if (this.signatureModel.label === '') {
                this.signatureModel.label = this.signatureModel.name;
            }

            reader.readAsDataURL(fileInput.target.files[0]);

            reader.onload = (value: any) => {
                window['angularUserAdministrationComponent'].componentAfterUpload(value.target.result);
                this.submitSignature();
            };
        }
    }

    displaySignatureEditionForm(index: number) {
        this.selectedSignature = index;
        this.selectedSignatureLabel = this.user.signatures[index].signature_label;
    }

    resendActivationNotification() {
        const dialogRef = this.dialog.open(ConfirmComponent, { panelClass: 'maarch-modal', autoFocus: false, disableClose: true, data: { title: `${this.translate.instant('lang.sendActivationNotification')}`, msg: this.translate.instant('lang.confirmAction') } });
        dialogRef.afterClosed().pipe(
            filter((data: string) => data === 'ok'),
            exhaustMap(() => this.http.put('../rest/users/' + this.serialId + '/accountActivationNotification', {})),
            tap(() => {
                this.notify.success(this.translate.instant('lang.activationNotificationSend'));
            }),
            catchError((err: any) => {
                this.notify.handleSoftErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    toggleGroup(group: any) {
        if ($('#' + group.group_id + '-input').is(':checked') === true) {
            const groupReq = {
                'groupId': group.group_id,
                'role': group.role
            };
            this.http.post('../rest/users/' + this.serialId + '/groups', groupReq)
                .subscribe(async (data: any) => {
                    this.user.groups = data.groups;
                    this.user.baskets = data.baskets;
                    if (this.headerService.user.id == this.serialId) {
                        await this.headerService.resfreshCurrentUser();
                        this.privilegeService.resfreshUserShortcuts();
                    }
                    this.notify.success(this.translate.instant('lang.groupAdded'));
                }, (err) => {
                    this.notify.error(err.error.errors);
                });
        } else {
            this.http.delete('../rest/users/' + this.serialId + '/groups/' + group.group_id)
                .subscribe(async (data: any) => {
                    this.user.groups = data.groups;
                    this.user.baskets = data.baskets;
                    this.user.redirectedBaskets = data.redirectedBaskets;
                    if (this.headerService.user.id == this.serialId) {
                        await this.headerService.resfreshCurrentUser();
                        this.privilegeService.resfreshUserShortcuts();
                    }
                    this.notify.success(this.translate.instant('lang.groupDeleted'));
                }, (err) => {
                    this.notify.error(err.error.errors);
                });
        }
    }

    updateGroup(group: any) {
        this.http.put('../rest/users/' + this.serialId + '/groups/' + group.group_id, group)
            .subscribe((data: any) => {
                this.notify.success(this.translate.instant('lang.groupUpdated'));
            }, (err) => {
                this.notify.error(err.error.errors);
            });
    }

    addEntity(entities: any[]) {
        entities.forEach(ent => {
            const entity = {
                'entityId': ent.entity_id,
                'role': ''
            };
            this.http.post('../rest/users/' + this.serialId + '/entities', entity)
                .subscribe((data: any) => {
                    this.user.entities = data.entities;
                    this.user.allEntities = data.allEntities;
                    if (this.headerService.user.id == this.serialId) {
                        this.headerService.resfreshCurrentUser();
                    }
                    this.notify.success(this.translate.instant('lang.entityAdded'));
                }, (err) => {
                    this.notify.error(err.error.errors);
                });
        });

    }

    updateEntity(entity: any) {
        this.http.put('../rest/users/' + this.serialId + '/entities/' + entity.entity_id, entity)
            .subscribe(() => {
                this.notify.success(this.translate.instant('lang.entityUpdated'));
            }, (err) => {
                this.notify.error(err.error.errors);
            });
    }

    updatePrimaryEntity(entity: any) {
        this.http.put('../rest/users/' + this.serialId + '/entities/' + entity.entity_id + '/primaryEntity', {})
            .subscribe((data: any) => {
                this.user['entities'] = data.entities;
                this.notify.success(this.translate.instant('lang.entityTooglePrimary') + ' « ' + entity.entity_id + ' »');
            }, (err) => {
                this.notify.error(err.error.errors);
            });
    }

    deleteEntity(entities: any[]) {

        entities.forEach(ent => {
            const entityId = ent.entity_id;
            // first check confidential state
            this.http.get('../rest/users/' + this.serialId + '/entities/' + entityId)
                .subscribe((data: any) => {
                    if (!data['hasConfidentialityInstances'] && !data['hasListTemplates']) {
                        this.http.delete('../rest/users/' + this.serialId + '/entities/' + entityId)
                            .subscribe((dataEntities: any) => {
                                this.user.entities = dataEntities.entities;
                                this.user.allEntities = dataEntities.allEntities;
                                if (this.headerService.user.id == this.serialId) {
                                    this.headerService.resfreshCurrentUser();
                                }
                                this.notify.success(this.translate.instant('lang.entityDeleted'));
                            }, (err) => {
                                this.notify.error(err.error.errors);
                            });
                    } else {
                        this.config = { panelClass: 'maarch-modal', data: { hasConfidentialityInstances: data['hasConfidentialityInstances'], hasListTemplates: data['hasListTemplates'] } };
                        this.dialogRef = this.dialog.open(UserAdministrationRedirectModalComponent, this.config);
                        this.dialogRef.afterClosed().subscribe((result: any) => {
                            this.mode = 'delete';
                            if (result) {
                                this.mode = result.processMode;
                                this.http.request('DELETE', '../rest/users/' + this.serialId + '/entities/' + entityId, { body: { 'mode': this.mode, 'newUser': result.newUser } })
                                    .subscribe((dataEntities: any) => {
                                        this.user.entities = dataEntities.entities;
                                        this.user.allEntities = dataEntities.allEntities;
                                        if (this.headerService.user.id == this.serialId) {
                                            this.headerService.resfreshCurrentUser();
                                        }
                                        this.notify.success(this.translate.instant('lang.entityDeleted'));
                                    }, (err) => {
                                        this.notify.error(err.error.errors);
                                    });
                            } else {
                                this.maarchTree.toggleNode(
                                    this.maarchTree.dataSource.data,
                                    {
                                        selected: true,
                                        opened: true
                                    },
                                    [ent.id]
                                );
                                // $('#jstree').jstree('select_node', entityId);
                                this.mode = '';
                            }
                            this.dialogRef = null;
                        });
                    }

                }, (err) => {
                    this.notify.error(err.error.errors);
                });
        });

    }

    submitSignature() {
        this.http.post('../rest/users/' + this.serialId + '/signatures', this.signatureModel)
            .subscribe((data: any) => {
                this.user.signatures = data.signatures;
                this.notify.success(this.translate.instant('lang.signAdded'));
                this.signatureModel = {
                    base64: '',
                    base64ForJs: '',
                    name: '',
                    type: '',
                    size: 0,
                    label: '',
                };
            }, (err) => {
                this.notify.error(err.error.errors);
            });
    }

    updateSignature(selectedSignature: any) {
        const id = this.user.signatures[selectedSignature].id;
        const label = this.user.signatures[selectedSignature].signature_label;

        this.http.put('../rest/users/' + this.serialId + '/signatures/' + id, { 'label': label })
            .subscribe((data: any) => {
                this.user.signatures[selectedSignature].signature_label = data.signature.signature_label;
                this.notify.success(this.translate.instant('lang.signUpdated'));
            }, (err) => {
                this.notify.error(err.error.errors);
            });
    }

    deleteSignature(signature: any) {
        const dialogRef = this.dialog.open(ConfirmComponent, { panelClass: 'maarch-modal', autoFocus: false, disableClose: true, data: { title: `${this.translate.instant('lang.delete')} « ${signature.signature_label} »`, msg: this.translate.instant('lang.confirmAction') } });
        dialogRef.afterClosed().pipe(
            filter((data: string) => data === 'ok'),
            exhaustMap(() => this.http.delete('../rest/users/' + this.serialId + '/signatures/' + signature.id)),
            tap((data: any) => {
                this.user.signatures = data.signatures;
                this.notify.success(this.translate.instant('lang.signDeleted'));
            }),
            catchError((err: any) => {
                this.notify.handleSoftErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    test(event: any) {
        if (event.mouseEvent.dataTransfer.files && event.mouseEvent.dataTransfer.files[0]) {
            const reader = new FileReader();

            this.signatureModel.name = event.mouseEvent.dataTransfer.files[0].name;
            this.signatureModel.size = event.mouseEvent.dataTransfer.files[0].size;
            this.signatureModel.type = event.mouseEvent.dataTransfer.files[0].type;
            if (this.signatureModel.label === '') {
                this.signatureModel.label = this.signatureModel.name;
            }

            reader.readAsDataURL(event.mouseEvent.dataTransfer.files[0]);

            reader.onload = (value: any) => {
                window['angularUserAdministrationComponent'].componentAfterUpload(value.target.result);
                this.submitSignature();
            };
        }
    }

    addBasketRedirection(newUser: any) {
        const basketsRedirect: any[] = [];
        this.selectionBaskets.selected.forEach((elem: any) => {
            basketsRedirect.push(
                {
                    actual_user_id: newUser.serialId,
                    basket_id: elem.basket_id,
                    group_id: elem.groupSerialId,
                    originalOwner: null
                }
            );
        });
        const dialogRef = this.dialog.open(ConfirmComponent, { panelClass: 'maarch-modal', autoFocus: false, disableClose: true, data: { title: `${this.translate.instant('lang.redirectBasket')}`, msg: this.translate.instant('lang.confirmAction') } });
        dialogRef.afterClosed().pipe(
            filter((data: string) => data === 'ok'),
            exhaustMap(() => this.http.post('../rest/users/' + this.serialId + '/redirectedBaskets', basketsRedirect)),
            tap((data: any) => {
                this.user.baskets = data['baskets'];
                this.user.redirectedBaskets = data['redirectedBaskets'];
                this.selectionBaskets.clear();
                this.notify.success(this.translate.instant('lang.basketUpdated'));
            }),
            catchError((err: any) => {
                this.notify.handleSoftErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    reassignBasketRedirection(newUser: any, basket: any, i: number) {
        const dialogRef = this.dialog.open(ConfirmComponent, { panelClass: 'maarch-modal', autoFocus: false, disableClose: true, data: { title: `${this.translate.instant('lang.redirectBasket')}`, msg: this.translate.instant('lang.confirmAction') } });
        dialogRef.afterClosed().pipe(
            filter((data: string) => data === 'ok'),
            exhaustMap(() => this.http.post('../rest/users/' + this.serialId + '/redirectedBaskets', [
                {
                    'actual_user_id': newUser.serialId,
                    'basket_id': basket.basket_id,
                    'group_id': basket.group_id,
                    'originalOwner': basket.owner_user_id,
                }
            ])),
            tap((data: any) => {
                this.user.baskets = data['baskets'];
                this.user.assignedBaskets.splice(i, 1);
                this.notify.success(this.translate.instant('lang.basketUpdated'));
            }),
            catchError((err: any) => {
                this.notify.handleSoftErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    delBasketRedirection(basket: any, i: number) {
        const dialogRef = this.dialog.open(ConfirmComponent, { panelClass: 'maarch-modal', autoFocus: false, disableClose: true, data: { title: `${this.translate.instant('lang.deleteRedirection')}`, msg: this.translate.instant('lang.confirmAction') } });
        dialogRef.afterClosed().pipe(
            filter((data: string) => data === 'ok'),
            exhaustMap(() => this.http.delete('../rest/users/' + this.serialId + '/redirectedBaskets?redirectedBasketIds[]=' + basket.id)),
            tap((data: any) => {
                this.user.baskets = data['baskets'];
                this.user.redirectedBaskets.splice(i, 1);
                this.notify.success(this.translate.instant('lang.basketUpdated'));
            }),
            catchError((err: any) => {
                this.notify.handleSoftErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    delBasketAssignRedirection(basket: any, i: number) {
        const dialogRef = this.dialog.open(ConfirmComponent, { panelClass: 'maarch-modal', autoFocus: false, disableClose: true, data: { title: `${this.translate.instant('lang.deleteAssignation')}`, msg: this.translate.instant('lang.confirmAction') } });
        dialogRef.afterClosed().pipe(
            filter((data: string) => data === 'ok'),
            exhaustMap(() => this.http.delete('../rest/users/' + this.serialId + '/redirectedBaskets?redirectedBasketIds[]=' + basket.id)),
            tap((data: any) => {
                this.user.baskets = data['baskets'];
                this.user.assignedBaskets.splice(i, 1);
                this.notify.success(this.translate.instant('lang.basketUpdated'));
            }),
            catchError((err: any) => {
                this.notify.handleSoftErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    toggleBasket(state: boolean) {
        const basketsDisable: any = [];
        this.user.baskets.forEach((elem: any) => {
            this.selectionBaskets.selected.forEach((selected: any) => {
                if (elem.basket_id === selected.basket_id && elem.group_id === selected.group_id && elem.allowed !== state) {
                    elem.allowed = state;
                    basketsDisable.push({ 'basketId': elem.basket_id, 'groupSerialId': elem.groupSerialId, 'allowed': state });
                }
            });
        });
        if (basketsDisable.length > 0) {
            this.http.put('../rest/users/' + this.serialId + '/baskets', { 'baskets': basketsDisable })
                .subscribe((data: any) => {
                    this.selectionBaskets.clear();
                    this.notify.success(this.translate.instant('lang.basketsUpdated'));
                }, (err: any) => {
                    this.notify.error(err.error.errors);
                });
        }
    }

    activateAbsence() {
        this.http.put('../rest/users/' + this.serialId + '/status', { 'status': 'ABS' })
            .subscribe((data: any) => {
                this.user.status = data.user.status;
                this.userAbsenceModel = [];
                this.notify.success(this.translate.instant('lang.absOn'));
            }, (err) => {
                this.notify.error(err.error.errors);
            });
    }

    desactivateAbsence() {
        this.http.put('../rest/users/' + this.serialId + '/status', { 'status': 'OK' })
            .subscribe((data: any) => {
                this.user.status = data.user.status;
                this.notify.success(this.translate.instant('lang.absOff'));
            }, (err: any) => {
                this.notify.error(err.error.errors);
            });
    }

    getErrorMessage() {
        if (this.firstFormGroup.controls['newPasswordCtrl'].value !== this.firstFormGroup.controls['retypePasswordCtrl'].value) {
            this.firstFormGroup.controls['retypePasswordCtrl'].setErrors({ 'mismatch': true });
        } else {
            this.firstFormGroup.controls['retypePasswordCtrl'].setErrors(null);
        }
        if (this.firstFormGroup.controls['newPasswordCtrl'].hasError('required')) {
            return this.translate.instant('lang.requiredField') + ' !';

        } else if (this.firstFormGroup.controls['newPasswordCtrl'].hasError('minlength') && this.passwordRules.minLength.enabled) {
            return this.passwordRules.minLength.value + ' ' + this.translate.instant('lang.passwordminLength') + ' !';

        } else if (this.firstFormGroup.controls['newPasswordCtrl'].errors != null && this.firstFormGroup.controls['newPasswordCtrl'].errors.complexityUpper !== undefined && this.passwordRules.complexityUpper.enabled) {
            return this.translate.instant('lang.passwordcomplexityUpper') + ' !';

        } else if (this.firstFormGroup.controls['newPasswordCtrl'].errors != null && this.firstFormGroup.controls['newPasswordCtrl'].errors.complexityNumber !== undefined && this.passwordRules.complexityNumber.enabled) {
            return this.translate.instant('lang.passwordcomplexityNumber') + ' !';

        } else if (this.firstFormGroup.controls['newPasswordCtrl'].errors != null && this.firstFormGroup.controls['newPasswordCtrl'].errors.complexitySpecial !== undefined && this.passwordRules.complexitySpecial.enabled) {
            return this.translate.instant('lang.passwordcomplexitySpecial') + ' !';

        } else {
            this.firstFormGroup.controls['newPasswordCtrl'].setErrors(null);
            this.validPassword = true;
            return '';
        }
    }

    matchValidator(group: UntypedFormGroup) {
        if (group.controls['newPasswordCtrl'].value === group.controls['retypePasswordCtrl'].value) {
            return false;
        } else {
            group.controls['retypePasswordCtrl'].setErrors({ 'mismatch': true });
            return { 'mismatch': true };
        }
    }

    regexValidator(regex: RegExp, error: ValidationErrors): ValidatorFn {
        return (control: AbstractControl): { [key: string]: any } => {
            if (!control.value) {
                return null;
            }
            const valid = regex.test(control.value);
            return valid ? null : error;
        };
    }

    changePasswd() {
        this.http.get('../rest/passwordRules')
            .subscribe((data: any) => {
                const valArr: ValidatorFn[] = [];
                const ruleTextArr: String[] = [];
                const otherRuleTextArr: String[] = [];

                valArr.push(Validators.required);

                data.rules.forEach((rule: any) => {
                    if (rule.label === 'minLength') {
                        this.passwordRules.minLength.enabled = rule.enabled;
                        this.passwordRules.minLength.value = rule.value;
                        if (rule.enabled) {
                            valArr.push(Validators.minLength(this.passwordRules.minLength.value));
                            ruleTextArr.push(rule.value + ' ' + this.translate.instant('lang.password' + rule.label));
                        }


                    } else if (rule.label === 'complexityUpper') {
                        this.passwordRules.complexityUpper.enabled = rule.enabled;
                        this.passwordRules.complexityUpper.value = rule.value;
                        if (rule.enabled) {
                            valArr.push(this.regexValidator(new RegExp('[A-Z]'), { 'complexityUpper': '' }));
                            ruleTextArr.push(this.translate.instant('lang.password' + rule.label));
                        }


                    } else if (rule.label === 'complexityNumber') {
                        this.passwordRules.complexityNumber.enabled = rule.enabled;
                        this.passwordRules.complexityNumber.value = rule.value;
                        if (rule.enabled) {
                            valArr.push(this.regexValidator(new RegExp('[0-9]'), { 'complexityNumber': '' }));
                            ruleTextArr.push(this.translate.instant('lang.password' + rule.label));
                        }


                    } else if (rule.label === 'complexitySpecial') {
                        this.passwordRules.complexitySpecial.enabled = rule.enabled;
                        this.passwordRules.complexitySpecial.value = rule.value;
                        if (rule.enabled) {
                            valArr.push(this.regexValidator(new RegExp('[^A-Za-z0-9]'), { 'complexitySpecial': '' }));
                            ruleTextArr.push(this.translate.instant('lang.password' + rule.label));
                        }
                    } else if (rule.label === 'renewal') {
                        this.passwordRules.renewal.enabled = rule.enabled;
                        this.passwordRules.renewal.value = rule.value;
                        if (rule.enabled) {
                            otherRuleTextArr.push(this.translate.instant('lang.password' + rule.label) + ' <b>' + rule.value + ' ' + this.translate.instant('lang.days') + '</b>. ' + this.translate.instant('lang.password2' + rule.label) + '.');
                        }
                    } else if (rule.label === 'historyLastUse') {
                        this.passwordRules.historyLastUse.enabled = rule.enabled;
                        this.passwordRules.historyLastUse.value = rule.value;
                        if (rule.enabled) {
                            otherRuleTextArr.push(this.translate.instant('lang.passwordhistoryLastUseDesc') + ' <b>' + rule.value + '</b> ' + this.translate.instant('lang.passwordhistoryLastUseDesc2') + '.');
                        }
                    }

                });
                this.ruleText = ruleTextArr.join(', ');
                this.otherRuleText = otherRuleTextArr.join('<br/>');
                this.firstFormGroup.controls['newPasswordCtrl'].setValidators(valArr);
            }, (err: any) => {
                this.notify.error(err.error.errors);
            });

        this.firstFormGroup = this._formBuilder.group({
            newPasswordCtrl: [
                ''
            ],
            retypePasswordCtrl: [
                '',
                Validators.compose([Validators.required])
            ],
            currentPasswordCtrl: [
                '',
                Validators.compose([Validators.required])
            ]
        }, {
            validator: this.matchValidator
        });

        this.validPassword = false;
        this.firstFormGroup.controls['currentPasswordCtrl'].setErrors(null);
        this.firstFormGroup.controls['newPasswordCtrl'].setErrors(null);
        this.firstFormGroup.controls['retypePasswordCtrl'].setErrors(null);
        this.showPassword = true;
        this.selectedTabIndex = 0;
    }

    updatePassword() {
        this.passwordModel.currentPassword = this.firstFormGroup.controls['currentPasswordCtrl'].value;
        this.passwordModel.newPassword = this.firstFormGroup.controls['newPasswordCtrl'].value;
        this.passwordModel.reNewPassword = this.firstFormGroup.controls['retypePasswordCtrl'].value;
        this.http.put('../rest/users/' + this.serialId + '/password', this.passwordModel)
            .subscribe(() => {
                this.showPassword = false;
                this.passwordModel = {
                    currentPassword: '',
                    newPassword: '',
                    reNewPassword: '',
                };
                this.notify.success(this.translate.instant('lang.passwordUpdated'));
            }, (err: any) => {
                this.notify.error(err.error.errors);
            });
    }

    onSubmit() {
        if (this.creationMode) {
            this.http.get('../rest/users/' + this.user.userId + '/status')
                .subscribe((data: any) => {
                    if (data.status && data.status === 'DEL') {
                        const dialogRef = this.dialog.open(ConfirmComponent, { panelClass: 'maarch-modal', autoFocus: false, disableClose: true, data: { title: `${this.translate.instant('lang.reactivateUserDeleted')}`, msg: this.translate.instant('lang.confirmAction') } });
                        dialogRef.afterClosed().pipe(
                            filter((response: string) => response === 'ok'),
                            exhaustMap(() => this.http.post('../rest/users', this.user)),
                            tap((result: any) => {
                                this.subscribeUserCreation(true, result.id);
                            }),
                            catchError((err: any) => {
                                this.notify.error(err.error.errors);
                                return of(false);
                            })
                        ).subscribe();
                    } else {
                        this.http.post('../rest/users', this.user)
                            .subscribe((result: any) => {
                                this.subscribeUserCreation(false, result.id);
                            }, (err: any) => {
                                this.notify.handleSoftErrors(err);
                            });
                    }
                }, (err: any) => {
                    this.notify.error(err.error.errors);
                });
        } else {
            const user = {
                ...this.user
            };
            if (this.user.mode === 'rest') {
                user.authorizedApi = this.user.authorizedApi.split('\n')[0] !== '' ? this.user.authorizedApi.split('\n') : [];
            }
            this.http.put('../rest/users/' + this.serialId, user)
                .subscribe((data: any) => {
                    if (this.headerService.user.id == this.serialId) {
                        this.headerService.resfreshCurrentUser();
                    }
                    this.notify.success(this.translate.instant('lang.userUpdated'));
                }, (err: any) => {
                    this.notify.handleSoftErrors(err);
                });
        }
    }

    subscribeUserCreation(deletedUser: boolean, userId: any) {
        if (deletedUser) {
            this.notify.success(this.translate.instant('lang.userUpdated'));
        } else {
            this.notify.success(this.translate.instant('lang.userAdded'));
        }
        this.appInputCorrespondentGroup.linkGrpAfterCreation(userId, 'user');
        this.router.navigate(['/administration/users/' + userId]);
    }

    setUserModeLogin(event: any) {
        if (event.checked) {
            this.user.mode = 'rest';
        } else {
            this.user.mode = 'standard';
        }
    }

    setLowerUserId() {
        this.user.userId = this.user.userId.toLowerCase();
    }

    async synchronizeSignatures() {
        this.loadingSign = true;
        await this.externalSignatoryBook.synchronizeSignatures(this.user).finally(() => this.loadingSign = false);
    }

    getLabelById(varLang: string): string {
        return `${this.translate.instant('lang.' + varLang)} ${this.translate.instant('lang.' + this.authService.externalSignatoryBook?.id)}`;
    }
}

@Component({
    templateUrl: 'user-administration-redirect-modal.component.html',
    styles: ['.mat-dialog-content{max-height: 65vh;width:600px;}']
})
export class UserAdministrationRedirectModalComponent {


    redirectUser: String = '';
    processMode: String = '';

    constructor(public http: HttpClient, @Inject(MAT_DIALOG_DATA) public data: any, public dialogRef: MatDialogRef<UserAdministrationRedirectModalComponent>) {
    }

    setRedirectUser(user: any) {
        this.redirectUser = user;
    }
}
