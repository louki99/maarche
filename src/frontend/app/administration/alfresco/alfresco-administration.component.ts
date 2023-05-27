import { Component, OnInit, ViewChild } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { TranslateService } from '@ngx-translate/core';
import { AppService } from '@service/app.service';
import { HeaderService } from '@service/header.service';
import { NotificationService } from '@service/notification/notification.service';
import { MatSidenav } from '@angular/material/sidenav';
import { FunctionsService } from '@service/functions.service';
import { ActivatedRoute, Router } from '@angular/router';
import { of } from 'rxjs';
import { MaarchFlatTreeComponent } from '../../../plugins/tree/maarch-flat-tree.component';
import { catchError, map, tap } from 'rxjs/operators';

@Component({
    selector: 'app-alfresco',
    templateUrl: './alfresco-administration.component.html',
    styleUrls: ['./alfresco-administration.component.scss']
})
export class AlfrescoAdministrationComponent implements OnInit {

    @ViewChild('snav2', { static: true }) public sidenavRight: MatSidenav;
    @ViewChild('maarchTree', { static: true }) maarchTree: MaarchFlatTreeComponent;

    loading: boolean = false;
    creationMode: boolean = true;

    entities: any[] = [];
    availableEntities: any[] = [];

    alfresco: any = {
        id: 0,
        label: '',
        account: {
            id: '',
            password: '',
        },
        rootFolder: null,
        linkedEntities: []
    };

    hidePassword: boolean = true;
    alfrescoTreeLoaded: boolean = false;

    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        private route: ActivatedRoute,
        private router: Router,
        private notify: NotificationService,
        private headerService: HeaderService,
        public appService: AppService,
        public functionsService: FunctionsService
    ) { }

    ngOnInit() {
        this.loading = false;
        this.route.params.subscribe(async params => {
            if (typeof params['id'] === 'undefined') {
                this.headerService.setHeader(this.translate.instant('lang.alfrescoCreation'));
                this.creationMode = true;
            } else {
                this.headerService.setHeader(this.translate.instant('lang.alfrescoModification'));

                this.alfresco.id = params['id'];
                this.creationMode = false;
            }
            await this.getEntities();
            await this.getAvailableEntities();
            await this.initAccount();
            this.loading = false;
        });
    }

    onSubmit() {
        if (this.creationMode) {
            this.createAccount();
        } else {
            this.updateAccount();
        }
    }

    createAccount() {
        this.http.post('../rest/alfresco/accounts', this.formatData()).pipe(
            tap(() => {
                this.notify.success(this.translate.instant('lang.accountAdded'));
                this.router.navigate(['/administration/alfresco']);
            }),
            catchError((err: any) => {
                this.notify.handleSoftErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    updateAccount() {
        this.http.put(`../rest/alfresco/accounts/${this.alfresco.id}`, this.formatData()).pipe(
            tap(() => {
                this.notify.success(this.translate.instant('lang.accountUpdated'));
                this.router.navigate(['/administration/alfresco']);
            }),
            catchError((err: any) => {
                this.notify.handleSoftErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    formatData() {
        const alfresco: any = {
            label: this.alfresco.label,
            login: this.alfresco.account.id,
            nodeId: this.alfresco.rootFolder,
            entities: this.maarchTree.getSelectedNodes().map(ent => ent.id)
        };

        if (!this.functionsService.empty(this.alfresco.account.password)) {
            alfresco.password = this.alfresco.account.password;
        }

        return alfresco;
    }

    getAvailableEntities() {
        return new Promise((resolve, reject) => {
            this.http.get('../rest/alfresco/availableEntities').pipe(
                tap((data: any) => {
                    this.availableEntities = data['availableEntities'];
                    resolve(true);
                }),
                catchError((err: any) => {
                    this.notify.handleSoftErrors(err);
                    return of(false);
                })
            ).subscribe();
        });
    }

    getEntities() {
        return new Promise((resolve, reject) => {
            this.http.get('../rest/entities').pipe(
                map((data: any) => {
                    data.entities = data.entities.map((entity: any) => ({
                        text: entity.entity_label,
                        icon: entity.icon,
                        parent_id: entity.parentSerialId,
                        id: entity.serialId,
                        state: {
                            opened: true
                        }
                    }));
                    return data.entities;
                }),
                tap((entities: any) => {
                    this.entities = entities;
                    resolve(true);
                }),
                catchError((err: any) => {
                    this.notify.handleSoftErrors(err);
                    return of(false);
                })
            ).subscribe();
        });
    }

    initAccount() {
        return new Promise((resolve, reject) => {
            if (this.creationMode) {
                this.http.get('../rest/entities').pipe(
                    map((data: any) => {
                        data.entities = data.entities.map((entity: any) => ({
                            text: entity.entity_label,
                            icon: entity.icon,
                            parent_id: entity.parentSerialId,
                            id: entity.serialId,
                            state: {
                                opened: true
                            }
                        }));
                        return data.entities;
                    }),
                    tap((entities: any) => {
                        this.entities = entities;

                        this.entities.forEach(element => {
                            if (this.availableEntities.indexOf(+element.id) > -1) {
                                element.state.disabled = false;
                            } else {
                                element.state.disabled = true;
                            }
                        });

                        setTimeout(() => {
                            this.initEntitiesTree(this.entities);
                        }, 0);
                        resolve(true);
                    }),
                    catchError((err: any) => {
                        this.notify.handleErrors(err);
                        return of(false);
                    })
                ).subscribe();

            } else {
                this.http.get(`../rest/alfresco/accounts/${this.alfresco.id}`).pipe(
                    tap((data: any) => {
                        this.alfresco = {
                            id: data.id,
                            label: data.label,
                            account: {
                                id: data.login
                            },
                            rootFolder: data.nodeId,
                            linkedEntities: data.entities
                        };

                        this.entities.forEach(element => {
                            if (this.availableEntities.indexOf(+element.id) > -1) {
                                element.state.disabled = false;
                            } else {
                                element.state.disabled = true;
                            }
                            if (this.alfresco.linkedEntities.indexOf(+element.id) > -1) {
                                element.state.disabled = false;
                                element.state.selected = true;
                            }
                        });
                        setTimeout(() => {
                            this.initEntitiesTree(this.entities);
                        }, 0);
                        resolve(true);
                    }),
                    catchError((err: any) => {
                        this.notify.handleErrors(err);
                        return of(false);
                    })
                ).subscribe();
            }

        });
    }

    initEntitiesTree(entities: any) {
        this.maarchTree.initData(entities);
    }

    validAccount() {
        if (this.functionsService.empty(this.alfresco.rootFolder) || this.maarchTree.getSelectedNodes().length === 0) {
            return false;
        } else {
            return true;
        }
    }

    checkAccount() {
        let alfresco  = {};
        if (!this.creationMode) {
            alfresco = {
                accountId : this.alfresco.id,
                login: this.alfresco.account.id,
                password: this.alfresco.account.password,
                nodeId : this.alfresco.rootFolder
            };
        } else {
            alfresco = {
                login: this.alfresco.account.id,
                password: this.alfresco.account.password,
                nodeId : this.alfresco.rootFolder
            };
        }

        this.http.post('../rest/alfresco/checkAccounts', alfresco).pipe(
            tap(() => {
                this.notify.success(this.translate.instant('lang.testSucceeded'));
            }),
            catchError((err: any) => {
                this.notify.handleSoftErrors(err);
                return of(false);
            })
        ).subscribe();
    }
}
