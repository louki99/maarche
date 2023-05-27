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
import { MaarchFlatTreeComponent } from '@plugins/tree/maarch-flat-tree.component';
import { catchError, finalize, map, tap } from 'rxjs/operators';

@Component({
    selector: 'app-multigest',
    templateUrl: './multigest-administration.component.html',
    styleUrls: ['./multigest-administration.component.scss']
})
export class MultigestAdministrationComponent implements OnInit {

    @ViewChild('snav2', { static: true }) public sidenavRight: MatSidenav;
    @ViewChild('maarchTree', { static: true }) maarchTree: MaarchFlatTreeComponent;

    loading: boolean = false;
    creationMode: boolean = true;

    entities: any[] = [];
    availableEntities: any[] = [];

    multigest: any = {
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
    multigestTreeLoaded: boolean = false;

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
                this.headerService.setHeader(this.translate.instant('lang.multigestCreation'));
                this.creationMode = true;
            } else {
                this.headerService.setHeader(this.translate.instant('lang.multigestModification'));

                this.multigest.id = params['id'];
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
        this.http.post('../rest/multigest/accounts', this.formatData()).pipe(
            tap(() => {
                this.notify.success(this.translate.instant('lang.accountAdded'));
                this.router.navigate(['/administration/multigest']);
            }),
            catchError((err: any) => {
                this.notify.handleSoftErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    updateAccount() {
        this.http.put(`../rest/multigest/accounts/${this.multigest.id}`, this.formatData()).pipe(
            tap(() => {
                this.notify.success(this.translate.instant('lang.accountUpdated'));
                this.router.navigate(['/administration/multigest']);
            }),
            catchError((err: any) => {
                this.notify.handleSoftErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    formatData() {
        const multigest: any = {
            label: this.multigest.label,
            login: this.multigest.account.id,
            sasId: this.multigest.rootFolder,
            entities: this.maarchTree.getSelectedNodes().map(ent => ent.id)
        };

        if (!this.functionsService.empty(this.multigest.account.password)) {
            multigest.password = this.multigest.account.password;
        }

        return multigest;
    }

    getAvailableEntities() {
        return new Promise((resolve, reject) => {
            this.http.get('../rest/multigest/availableEntities').pipe(
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
                            if (this.availableEntities.indexOf(element.id) > -1) {
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
                this.http.get(`../rest/multigest/accounts/${this.multigest.id}`).pipe(
                    tap((data: any) => {
                        this.multigest = {
                            id: data.id,
                            label: data.label,
                            account: {
                                id: data.login
                            },
                            rootFolder: data.sasId,
                            linkedEntities: data.entities
                        };

                        this.entities.forEach(element => {
                            if (this.availableEntities.indexOf(+element.id) > -1) {
                                element.state.disabled = false;
                            } else {
                                element.state.disabled = true;
                            }
                            if (this.multigest.linkedEntities.indexOf(+element.id) > -1) {
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
        if (this.functionsService.empty(this.multigest.rootFolder) || this.maarchTree.getSelectedNodes().length === 0) {
            return false;
        } else {
            return true;
        }
    }

    checkAccount() {
        this.loading = true;
        let multigest  = {};
        if (!this.creationMode) {
            multigest = {
                accountId : this.multigest.id,
                login: this.multigest.account.id,
                password: this.multigest.account.password,
                sasId : this.multigest.rootFolder
            };
        } else {
            multigest = {
                login: this.multigest.account.id,
                password: this.multigest.account.password,
                sasId : this.multigest.rootFolder
            };
        }

        this.http.post('../rest/multigest/checkAccounts', multigest).pipe(
            tap(() => {
                this.notify.success(this.translate.instant('lang.testSucceeded'));
            }),
            finalize(() => this.loading = false),
            catchError((err: any) => {
                this.notify.handleSoftErrors(err);
                return of(false);
            })
        ).subscribe();
    }
}
