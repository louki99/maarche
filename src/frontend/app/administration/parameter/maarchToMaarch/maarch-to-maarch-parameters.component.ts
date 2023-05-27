import { Component, OnInit } from '@angular/core';
import { TranslateService } from '@ngx-translate/core';
import { HttpClient } from '@angular/common/http';
import { UntypedFormControl } from '@angular/forms';
import { catchError, debounceTime, exhaustMap, filter, finalize, map, tap } from 'rxjs/operators';
import { ConfirmComponent } from '@plugins/modal/confirm.component';
import { MatDialog } from '@angular/material/dialog';
import { NotificationService } from '@service/notification/notification.service';
import { of } from 'rxjs';
import { KeyValue } from '@angular/common';
import { environment } from '../../../../environments/environment';
import { FunctionsService } from '@service/functions.service';
import { PrivilegeService } from '@service/privileges.service';


@Component({
    selector: 'app-maarch-to-maarch-parameters',
    templateUrl: './maarch-to-maarch-parameters.component.html',
    styleUrls: ['./maarch-to-maarch-parameters.component.scss'],
})
export class MaarchToMaarchParametersComponent implements OnInit {

    loading: boolean = true;
    doctypes: any = [];
    baskets: any = [];
    statuses: any = [];
    priorities: any = [];
    indexingModels: any = [];
    attachmentsTypes: any = [];
    initialDataModified: boolean = false;

    basketToRedirect = new UntypedFormControl('NumericBasket');
    metadata: any = {
        typeId: new UntypedFormControl(),
        statusId: new UntypedFormControl(),
        priorityId: new UntypedFormControl(),
        indexingModelId: new UntypedFormControl(),
        attachmentTypeId: new UntypedFormControl(),
    };
    communications = {
        url: new UntypedFormControl('https://demo.maarchcourrier.com'),
        login: new UntypedFormControl('cchaplin'),
        password: new UntypedFormControl(null),
        email: new UntypedFormControl(null),
    };
    annuary = {
        enabled: new UntypedFormControl(false),
        organization: new UntypedFormControl('organization'),
        annuaries: [
            {
                uri: new UntypedFormControl('1.1.1.1'),
                baseDN: new UntypedFormControl('base'),
                login: new UntypedFormControl('Administrateur'),
                password: new UntypedFormControl('ThePassword'),
                ssl: new UntypedFormControl(false),
            }
        ]
    };
    maarch2maarchUrl: string = this.functionsService.getDocBaseUrl() + '/guat/guat_exploitation/maarch2maarch.html';

    passwordAlreadyExists: boolean = false;

    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        private dialog: MatDialog,
        private notify: NotificationService,
        public functionsService: FunctionsService,
        public privilegeService: PrivilegeService
    ) { }

    async ngOnInit() {
        if (this.privilegeService.hasCurrentUserPrivilege('admin_baskets')) {
            await this.getBaskets();
        }
        await this.getDoctypes();
        await this.getStatuses();
        await this.getPriorities();
        await this.getIndexingModels();
        await this.getAttachmentTypes();
        await this.getConfiguration();
        if (this.initialDataModified) {
            this.saveConfiguration();
        }
        this.loading = false;
    }

    getDoctypes() {
        return new Promise((resolve, reject) => {
            this.http.get('../rest/doctypes').pipe(
                tap((data: any) => {
                    let arrValues: any[] = [];
                    data.structure.forEach((doctype: any) => {
                        if (doctype['doctypes_second_level_id'] === undefined) {
                            arrValues.push({
                                id: doctype.doctypes_first_level_id,
                                label: doctype.doctypes_first_level_label,
                                title: doctype.doctypes_first_level_label,
                                disabled: true,
                                isTitle: true,
                                color: doctype.css_style
                            });
                            data.structure.filter((info: any) => info.doctypes_first_level_id === doctype.doctypes_first_level_id && info.doctypes_second_level_id !== undefined && info.description === undefined).forEach((secondDoctype: any) => {
                                arrValues.push({
                                    id: secondDoctype.doctypes_second_level_id,
                                    label: '&nbsp;&nbsp;&nbsp;&nbsp;' + secondDoctype.doctypes_second_level_label,
                                    title: secondDoctype.doctypes_second_level_label,
                                    disabled: true,
                                    isTitle: true,
                                    color: secondDoctype.css_style
                                });
                                arrValues = arrValues.concat(data.structure.filter((infoDoctype: any) => infoDoctype.doctypes_second_level_id === secondDoctype.doctypes_second_level_id && infoDoctype.description !== undefined).map((infoType: any) => ({
                                    id: infoType.type_id,
                                    label: '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;' + infoType.description,
                                    title: infoType.description,
                                    disabled: false,
                                    isTitle: false,
                                })));
                            });
                        }
                    });
                    this.doctypes = arrValues;
                    resolve(true);
                })
            ).subscribe();
        });
    }

    getBaskets() {
        return new Promise((resolve, reject) => {
            this.http.get('../rest/baskets').pipe(
                tap((data: any) => {
                    this.baskets = data.baskets.map((basket: any) => ({
                        id: basket.basket_id,
                        label: basket.basket_name
                    }));
                    resolve(true);
                })
            ).subscribe();
        });
    }

    getStatuses() {
        return new Promise((resolve, reject) => {
            this.http.get('../rest/statuses').pipe(
                tap((data: any) => {
                    this.statuses = data.statuses.map((status: any) => ({
                        id: status.identifier,
                        label: status.label_status
                    }));
                    resolve(true);
                })
            ).subscribe();
        });
    }

    getPriorities() {
        return new Promise((resolve, reject) => {
            this.http.get('../rest/priorities').pipe(
                tap((data: any) => {
                    this.priorities = data.priorities;
                    resolve(true);
                })
            ).subscribe();
        });
    }

    getIndexingModels() {
        return new Promise((resolve, reject) => {
            this.http.get('../rest/indexingModels').pipe(
                tap((data: any) => {
                    this.indexingModels = data.indexingModels.filter((info: any) => info.private === false);
                    resolve(true);
                })
            ).subscribe();
        });
    }

    getAttachmentTypes() {
        return new Promise((resolve, reject) => {
            this.http.get('../rest/attachmentsTypes').pipe(
                tap((data: any) => {
                    Object.keys(data.attachmentsTypes).forEach(templateType => {
                        this.attachmentsTypes.push({
                            id: data.attachmentsTypes[templateType].id,
                            label: data.attachmentsTypes[templateType].label
                        });
                    });
                    resolve(true);
                })
            ).subscribe();
        });
    }

    getConfiguration() {
        return new Promise((resolve, reject) => {
            this.http.get('../rest/m2m/configuration').pipe(
                map((data: any) => data.configuration),
                tap((data: any) => {
                    if (!this.functionsService.empty(data)) {
                        this.passwordAlreadyExists = data.communications.passwordAlreadyExists ? true : false;
                        Object.keys(this.communications).forEach((elemId: any) => {
                            if (['url', 'login', 'email'].indexOf(elemId) > -1) {
                                this.communications[elemId].setValue(data.communications[elemId]);
                            }
                            this.communications[elemId].valueChanges
                                .pipe(
                                    debounceTime(1000),
                                    tap((value: any) => {
                                        this.saveConfiguration();
                                    }),
                                ).subscribe();
                        });
                        Object.keys(this.metadata).forEach(elemId => {
                            this.setDefaultValue(elemId, data.metadata[elemId]);
                            this.metadata[elemId].valueChanges
                                .pipe(
                                    debounceTime(300),
                                    tap((value: any) => {
                                        this.saveConfiguration();
                                    }),
                                ).subscribe();
                        });
                        Object.keys(this.annuary).forEach(elemId => {
                            if (['annuaries'].indexOf(elemId) === -1) {
                                this.annuary[elemId].setValue(data.annuary[elemId]);
                                this.annuary[elemId].valueChanges
                                    .pipe(
                                        debounceTime(1000),
                                        tap((value: any) => {
                                            if (elemId === 'enabled' && value === true && this.annuary.annuaries.length === 0) {
                                                this.addAnnuary();
                                            } else {
                                                this.saveConfiguration();
                                            }
                                        }),
                                    ).subscribe();
                            } else {
                                this.annuary[elemId] = [];
                                data.annuary[elemId].forEach((annuaryConf: any, index: number) => {
                                    this.annuary[elemId].push({});
                                    Object.keys(annuaryConf).forEach(annuaryItem => {
                                        this.annuary[elemId][index][annuaryItem] = new UntypedFormControl(data.annuary[elemId][index][annuaryItem]);
                                        this.annuary[elemId][index][annuaryItem].valueChanges
                                            .pipe(
                                                debounceTime(1000),
                                                tap((value: any) => {
                                                    this.saveConfiguration();
                                                }),
                                            ).subscribe();
                                    });
                                });
                            }
                        });
                        this.setDefaultValue('basketToRedirect', data.basketToRedirect);
                        this.basketToRedirect.valueChanges
                            .pipe(
                                debounceTime(300),
                                tap((value: any) => {
                                    this.saveConfiguration();
                                }),
                            ).subscribe();
                    }
                }),
                finalize(() => {
                    resolve(true);
                }),
                catchError((err: any) => {
                    this.notify.handleErrors(err);
                    return of(false);
                })
            ).subscribe();
        });
    }

    setDefaultValue(id: string, value: string) {
        if (id === 'basketToRedirect') {
            if (this.privilegeService.hasCurrentUserPrivilege('admin_baskets')) {
                if (this.baskets.filter((item: any) => item.id === value).length > 0) {
                    this.basketToRedirect.setValue(value);
                } else {
                    this.basketToRedirect.setValue(this.baskets[0].id);
                    this.initialDataModified = true;
                }
            } else {
                this.basketToRedirect.setValue(value);
            }
        } else if (id === 'typeId') {
            if (this.doctypes.filter((item: any) => item.id === value).length > 0) {
                this.metadata[id].setValue(value);
            } else {
                this.metadata[id].setValue(this.doctypes.filter((item: any) => !item.disabled)[0].id);
                this.initialDataModified = true;
            }
        } else if (id === 'statusId') {
            if (this.statuses.filter((item: any) => item.id === value).length > 0) {
                this.metadata[id].setValue(value);
            } else {
                this.metadata[id].setValue(this.statuses[0].id);
                this.initialDataModified = true;
            }
        } else if (id === 'priorityId') {
            if (this.priorities.filter((item: any) => item.id === value).length > 0) {
                this.metadata[id].setValue(value);
            } else {
                this.metadata[id].setValue(this.priorities[0].id);
                this.initialDataModified = true;
            }
        } else if (id === 'indexingModelId') {
            if (this.indexingModels.filter((item: any) => item.id === value).length > 0) {
                this.metadata[id].setValue(value);
            } else {
                this.metadata[id].setValue(this.indexingModels[0].id);
                this.initialDataModified = true;
            }
        } else if (id === 'attachmentTypeId') {
            if (this.attachmentsTypes.filter((item: any) => item.id === value).length > 0) {
                this.metadata[id].setValue(value);
            } else {
                this.metadata[id].setValue(this.attachmentsTypes[0].id);
                this.initialDataModified = true;
            }
        }
    }

    addAnnuary() {
        const newAnnuary = {
            uri: new UntypedFormControl('1.1.1.1'),
            baseDN: new UntypedFormControl('base'),
            login: new UntypedFormControl('Administrateur'),
            password: new UntypedFormControl('ThePassword'),
            ssl: new UntypedFormControl(false),
        };
        Object.keys(newAnnuary).forEach(annuaryItem => {
            newAnnuary[annuaryItem].valueChanges
                .pipe(
                    debounceTime(1000),
                    tap((value: any) => {
                        this.saveConfiguration();
                    }),
                ).subscribe();
        });
        this.annuary.annuaries.push(newAnnuary);
        this.saveConfiguration();
    }

    deleteAnnuary(index: number) {
        const dialogRef = this.dialog.open(ConfirmComponent, { panelClass: 'maarch-modal', autoFocus: false, disableClose: true, data: { title: this.translate.instant('lang.delete'), msg: this.translate.instant('lang.confirmAction') } });

        dialogRef.afterClosed().pipe(
            filter((data: string) => data === 'ok'),
            tap(() => {
                this.annuary.annuaries.splice(index, 1);
                this.saveConfiguration();
            }),
            catchError((err: any) => {
                this.notify.handleSoftErrors(err);
                return of(false);
            })
        ).subscribe();
    }


    originalOrder = (a: KeyValue<string, any>, b: KeyValue<string, any>): number => 0;

    saveConfiguration() {
        this.http.put('../rest/m2m/configuration', { configuration: this.formatConfiguration() }).pipe(
            tap(() => {
                this.notify.success(this.translate.instant('lang.dataUpdated'));
                this.passwordAlreadyExists = (this.functionsService.empty(this.communications['password'].value) && this.passwordAlreadyExists) || (!this.functionsService.empty(this.communications['password'].value));
            }),
            catchError((err: any) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    formatConfiguration() {
        const config = {
            basketToRedirect: this.basketToRedirect.value,
            metadata: {},
            annuary: {
                annuaries: []
            },
            communications: {
                url: this.communications.url.value,
                login: this.communications.login.value,
                password: this.communications.password.value,
                email: this.communications.email.value
            },
        };
        Object.keys(this.metadata).forEach(elemId => {
            config['metadata'][elemId] = this.metadata[elemId].value;
        });
        Object.keys(this.annuary).forEach(elemId => {
            if (elemId !== 'annuaries') {
                config['annuary'][elemId] = this.annuary[elemId].value;
            } else {
                this.annuary[elemId].forEach((annuary: any, index: number) => {
                    const annuaryObj = {};
                    Object.keys(annuary).forEach(annuaryItem => {
                        annuaryObj[annuaryItem] = annuary[annuaryItem].value;
                    });
                    config['annuary'][elemId].push(annuaryObj);
                });
            }
        });
        return config;
    }
}
