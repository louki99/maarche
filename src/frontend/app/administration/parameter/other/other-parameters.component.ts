import { Component, OnInit } from '@angular/core';
import { TranslateService } from '@ngx-translate/core';
import { HttpClient } from '@angular/common/http';
import { KeyValue } from '@angular/common';
import { UntypedFormControl, Validators } from '@angular/forms';
import { catchError, debounceTime, filter, finalize, map, tap } from 'rxjs/operators';
import { ColorEvent } from 'ngx-color';
import { FunctionsService } from '@service/functions.service';
import { environment } from '../../../../environments/environment';
import {
    amber,
    blue,
    blueGrey,
    brown,
    cyan,
    deepOrange,
    deepPurple,
    green,
    indigo,
    lightBlue,
    lightGreen,
    lime,
    orange,
    pink,
    purple,
    red,
    teal,
    yellow,
} from 'material-colors';
import { ConfirmComponent } from '@plugins/modal/confirm.component';
import { of } from 'rxjs';
import { NotificationService } from '@service/notification/notification.service';
import { MatDialog } from '@angular/material/dialog';
import { CheckSaeInterconnectionComponent } from './checkSaeInterconnection/check-sae-interconnection.component';

@Component({
    selector: 'app-other-parameters',
    templateUrl: './other-parameters.component.html',
    styleUrls: ['./other-parameters.component.scss'],
})
export class OtherParametersComponent implements OnInit {

    editorsConf: any = {
        java: {},
        onlyoffice: {
            ssl: new UntypedFormControl(false),
            uri: new UntypedFormControl('192.168.0.11', [Validators.required]),
            port: new UntypedFormControl(8765, [Validators.required]),
            token: new UntypedFormControl(''),
            authorizationHeader: new UntypedFormControl('Authorization')
        },
        collaboraonline: {
            ssl: new UntypedFormControl(false),
            uri: new UntypedFormControl('192.168.0.11', [Validators.required]),
            port: new UntypedFormControl(9980, [Validators.required]),
        },
        office365sharepoint: {
            tenantId: new UntypedFormControl('abc-123456789-efd', [Validators.required]),
            clientId: new UntypedFormControl('abc-123456789-efd', [Validators.required]),
            clientSecret: new UntypedFormControl('abc-123456789-efd'),
            siteUrl: new UntypedFormControl('https://exemple.sharepoint.com/sites/example', [Validators.required]),
        }
    };

    addinOutlookConf = {
        indexingModelId: new UntypedFormControl(null, [Validators.required]),
        typeId: new UntypedFormControl(null, [Validators.required]),
        statusId: new UntypedFormControl(null, [Validators.required]),
        attachmentTypeId: new UntypedFormControl(null, [Validators.required]),
    };

    watermark = {
        enabled: new UntypedFormControl(true),
        text: new UntypedFormControl('Copie conforme de [alt_identifier] le [date_now] [hour_now]'),
        posX: new UntypedFormControl(30),
        posY: new UntypedFormControl(35),
        angle: new UntypedFormControl(0),
        opacity: new UntypedFormControl(0.5),
        font: new UntypedFormControl('helvetica'),
        size: new UntypedFormControl(10),
        color: new UntypedFormControl([20, 192, 30]),
    };

    saeConfig = {
        maarchRM: {
            sae: new UntypedFormControl('MaarchRM'),
            urlSAEService: new UntypedFormControl('https://demo-ap.maarchrm.com'),
            token: new UntypedFormControl(''),
            senderOrgRegNumber: new UntypedFormControl('org_987654321_DGS_SA'),
            accessRuleCode: new UntypedFormControl('AR039'),
            certificateSSL: new UntypedFormControl(''),
            userAgent: new UntypedFormControl('service'),
            M2M: new UntypedFormControl('maarch_courrier'),
            statusReplyReceived: new UntypedFormControl('REPLY_SEDA'),
            statusReplyRejected: new UntypedFormControl('REPLY_SEDA'),
            statusMailToPurge: new UntypedFormControl('REPLY_SEDA'),
        },
        externalSAE: {
            retentionRules: {
                id: new UntypedFormControl('id1'),
                label: new UntypedFormControl('label1')
            },
            archiveEntities: {
                id: new UntypedFormControl('id1'),
                label: new UntypedFormControl('label1')
            },
            archivalAgreements: {
                id: new UntypedFormControl('id1'),
                label: new UntypedFormControl('label1')
            }
        }
    };

    saeEnabled: 'maarchRM' | 'externalSAE' = 'maarchRM';

    externalSaeName: string = '';

    editorsEnabled = [];

    fonts = [
        {
            id: 'courier',
            label: 'courier'
        },
        {
            id: 'courierB',
            label: 'courierB'
        },
        {
            id: 'courierI',
            label: 'courierI'
        },
        {
            id: 'courierBI',
            label: 'courierBI'
        },
        {
            id: 'helvetica',
            label: 'helvetica'
        },
        {
            id: 'helveticaB',
            label: 'helveticaB'
        },
        {
            id: 'helveticaI',
            label: 'helveticaI'
        },
        {
            id: 'helveticaBI',
            label: 'helveticaBI'
        },
        {
            id: 'times',
            label: 'times'
        },
        {
            id: 'timesB',
            label: 'timesB'
        },
        {
            id: 'timesI',
            label: 'timesI'
        },
        {
            id: 'timesBI',
            label: 'timesBI'
        },
        {
            id: 'symbol',
            label: 'symbol'
        },
        {
            id: 'zapfdingbats',
            label: 'zapfdingbats'
        }
    ];

    colors: string[] = [
        red['900'],
        red['700'],
        red['500'],
        red['300'],
        red['100'],
        pink['900'],
        pink['700'],
        pink['500'],
        pink['300'],
        pink['100'],
        purple['900'],
        purple['700'],
        purple['500'],
        purple['300'],
        purple['100'],
        deepPurple['900'],
        deepPurple['700'],
        deepPurple['500'],
        deepPurple['300'],
        deepPurple['100'],
        indigo['900'],
        indigo['700'],
        indigo['500'],
        indigo['300'],
        indigo['100'],
        blue['900'],
        blue['700'],
        blue['500'],
        blue['300'],
        blue['100'],
        lightBlue['900'],
        lightBlue['700'],
        lightBlue['500'],
        lightBlue['300'],
        lightBlue['100'],
        cyan['900'],
        cyan['700'],
        cyan['500'],
        cyan['300'],
        cyan['100'],
        teal['900'],
        teal['700'],
        teal['500'],
        teal['300'],
        teal['100'],
        '#194D33',
        green['700'],
        green['500'],
        green['300'],
        green['100'],
        lightGreen['900'],
        lightGreen['700'],
        lightGreen['500'],
        lightGreen['300'],
        lightGreen['100'],
        lime['900'],
        lime['700'],
        lime['500'],
        lime['300'],
        lime['100'],
        yellow['900'],
        yellow['700'],
        yellow['500'],
        yellow['300'],
        yellow['100'],
        amber['900'],
        amber['700'],
        amber['500'],
        amber['300'],
        amber['100'],
        orange['900'],
        orange['700'],
        orange['500'],
        orange['300'],
        orange['100'],
        deepOrange['900'],
        deepOrange['700'],
        deepOrange['500'],
        deepOrange['300'],
        deepOrange['100'],
        brown['900'],
        brown['700'],
        brown['500'],
        brown['300'],
        brown['100'],
        blueGrey['900'],
        blueGrey['700'],
        blueGrey['500'],
        blueGrey['300'],
        blueGrey['100'],
    ];

    retentionRules: any[] = [
        {
            'id': 'id1',
            'label': 'label1'
        }
    ];
    archiveEntities: any[] = [
        {
            'id': 'id1',
            'label': 'label1'
        }
    ];
    archivalAgreements: any[] = [
        {
            'id': 'id1',
            'label': 'label1'
        }
    ];

    indexingModels: any = [];
    doctypes: any = [];
    statuses: any = [];
    attachmentsTypes: any = [];

    loading: boolean = false;
    hasError: boolean = false;

    exportSedaUrl: string = this.functions.getDocBaseUrl() + '/guat/guat_exploitation/Seda_send.html';


    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        private dialog: MatDialog,
        private notify: NotificationService,
        public functions: FunctionsService,
    ) { }

    async ngOnInit() {
        this.getStatuses();
        this.getDoctypes();
        this.getIndexingModels();
        this.getAttachmentTypes();
        await this.getWatermarkConfiguration();
        await this.getEditorsConfiguration();
        await this.getAddinOutlookConfConfiguration();
        await this.getSaeConfig();
        Object.keys(this.editorsConf).forEach(editorId => {
            Object.keys(this.editorsConf[editorId]).forEach((elementId: any) => {
                this.editorsConf[editorId][elementId].valueChanges
                    .pipe(
                        debounceTime(1000),
                        filter(() => this.editorsConf[editorId][elementId].valid),
                        tap(() => {
                            this.saveConfEditor();
                        }),
                    ).subscribe();
            });
        });
        Object.keys(this.watermark).forEach(elemId => {
            this.watermark[elemId].valueChanges
                .pipe(
                    debounceTime(1000),
                    tap((value: any) => {
                        this.saveWatermarkConf();
                    }),
                ).subscribe();
        });

        Object.keys(this.addinOutlookConf).forEach(elemId => {
            this.addinOutlookConf[elemId].valueChanges
                .pipe(
                    debounceTime(1000),
                    tap((value: any) => {
                        this.saveAddinOutlookConf();
                    }),
                ).subscribe();
        });
    }

    getWatermarkConfiguration() {
        return new Promise((resolve, reject) => {
            this.http.get('../rest/watermark/configuration').pipe(
                tap((data: any) => {
                    if (!this.functions.empty(data.configuration)) {
                        this.watermark = {
                            enabled: new UntypedFormControl(data.configuration.enabled),
                            text: new UntypedFormControl(data.configuration.text),
                            posX: new UntypedFormControl(data.configuration.posX),
                            posY: new UntypedFormControl(data.configuration.posY),
                            angle: new UntypedFormControl(data.configuration.angle),
                            opacity: new UntypedFormControl(data.configuration.opacity),
                            font: new UntypedFormControl(data.configuration.font),
                            size: new UntypedFormControl(data.configuration.size),
                            color: new UntypedFormControl(data.configuration.color),
                        };
                    }
                    resolve(true);
                })
            ).subscribe();
        });
    }

    getAddinOutlookConfConfiguration() {
        return new Promise((resolve, reject) => {
            this.http.get('../rest/plugins/outlook/configuration').pipe(
                tap(async (data: any) => {
                    if (!this.functions.empty(data.configuration) && Object.keys(data.configuration).length > 1) {
                        this.addinOutlookConf = {
                            indexingModelId: new UntypedFormControl(data.configuration.indexingModelId),
                            typeId: new UntypedFormControl(data.configuration.typeId),
                            statusId: new UntypedFormControl(data.configuration.statusId),
                            attachmentTypeId: new UntypedFormControl(data.configuration.attachmentTypeId),
                        };
                    } else {
                        await this.setDefaultValues();
                        this.saveAddinOutlookConf();
                    }
                    resolve(true);
                })
            ).subscribe();
        });
    }

    getEditorsConfiguration() {
        return new Promise((resolve, reject) => {
            this.http.get('../rest/configurations/admin_document_editors').pipe(
                map((data: any) => data.configuration.value),
                tap((data: any) => {
                    Object.keys(data).forEach(confId => {
                        this.editorsEnabled.push(confId);
                        Object.keys(data[confId]).forEach(itemId => {
                            // console.log(confId, itemId);

                            if (!this.functions.empty(this.editorsConf[confId][itemId])) {
                                this.editorsConf[confId][itemId].setValue(data[confId][itemId]);
                            }
                        });
                    });
                    resolve(true);
                })
            ).subscribe();
        });
    }

    getInputType(value: any) {
        return typeof value;
    }

    originalOrder = (a: KeyValue<string, any>, b: KeyValue<string, any>): number => 0;

    addEditor(id: string) {
        this.editorsEnabled.push(id);
        this.saveConfEditor();
    }

    removeEditor(index: number) {
        const dialogRef = this.dialog.open(ConfirmComponent, { panelClass: 'maarch-modal', autoFocus: false, disableClose: true, data: { title: this.translate.instant('lang.delete'), msg: this.translate.instant('lang.confirmAction') } });

        dialogRef.afterClosed().pipe(
            filter((data: string) => data === 'ok'),
            tap(() => {
                this.editorsEnabled.splice(index, 1);
                this.saveConfEditor();
            }),
            catchError((err: any) => {
                this.notify.handleSoftErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    getAvailableEditors() {
        const allEditors = Object.keys(this.editorsConf);
        const availableEditors = allEditors.filter(editor => this.editorsEnabled.indexOf(editor) === -1);
        return availableEditors;
    }

    allEditorsEnabled() {
        return Object.keys(this.editorsConf).length === this.editorsEnabled.length;
    }

    saveWatermarkConf() {
        this.http.put('../rest/watermark/configuration', this.formatWatermarkConfig()).pipe(
            tap(() => {
                this.notify.success(this.translate.instant('lang.dataUpdated'));
            }),
            catchError((err: any) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    saveAddinOutlookConf() {
        this.http.put('../rest/plugins/outlook/configuration', this.formatAddinOutlookConfig()).pipe(
            tap(() => {
                this.notify.success(this.translate.instant('lang.dataUpdated'));
            }),
            catchError((err: any) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    saveConfEditor() {
        this.http.put('../rest/configurations/admin_document_editors', this.formatEditorsConfig()).pipe(
            tap(() => {
                this.notify.success(this.translate.instant('lang.dataUpdated'));
            }),
            catchError((err: any) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    formatAddinOutlookConfig() {
        const obj: any = {};
        Object.keys(this.addinOutlookConf).forEach(elemId => {
            obj[elemId] = this.addinOutlookConf[elemId].value;

        });
        return obj;
    }

    formatWatermarkConfig() {
        const obj: any = {};
        Object.keys(this.watermark).forEach(elemId => {
            obj[elemId] = this.watermark[elemId].value;

        });
        return obj;
    }

    formatEditorsConfig() {
        const obj: any = {};
        this.editorsEnabled.forEach(id => {
            if (this.editorsEnabled.indexOf(id) > -1) {
                obj[id] = {};
                Object.keys(this.editorsConf[id]).forEach(elemId => {
                    obj[id][elemId] = this.editorsConf[id][elemId].value;
                });
            }
        });
        return obj;
    }

    handleChange($event: ColorEvent) {
        this.watermark.color.setValue([$event.color.rgb.r, $event.color.rgb.g, $event.color.rgb.b]);
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
                    const defaultDoctype = arrValues.filter((struct: any) => !struct.disabled)[0].id;
                    resolve(defaultDoctype);
                })
            ).subscribe();
        });
    }

    getIndexingModels() {
        return new Promise((resolve, reject) => {
            this.http.get('../rest/indexingModels').pipe(
                tap((data: any) => {
                    this.indexingModels = data.indexingModels.filter((info: any) => info.private === false);
                    const defaultIndexingModel = data.indexingModels[0].id;
                    resolve(defaultIndexingModel);
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
                        label: status.label_status,
                        statusId: status.id
                    }));
                    const defaultStatus = data.statuses[0].identifier;
                    resolve(defaultStatus);
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
                    const defAttachment: any = Object.values(data.attachmentsTypes)[0];
                    resolve(defAttachment.id);
                })
            ).subscribe();
        });
    }

    setDefaultValues() {
        return new Promise((resolve, reject) => {
            Promise.all([this.getIndexingModels(), this.getDoctypes(), this.getStatuses(), this.getAttachmentTypes()]).then((data: any) => {
                this.addinOutlookConf.indexingModelId.setValue(data[0]);
                this.addinOutlookConf.typeId.setValue(data[1]);
                this.addinOutlookConf.statusId.setValue(data[2]);
                this.addinOutlookConf.attachmentTypeId.setValue(data[3]);
                resolve(true);
            });
        });
    }

    getSaeConfig() {
        return new Promise((resolve) => {
            this.http.get('../rest/seda/configuration').pipe(
                map((data: any) => data.configuration),
                tap((data: any) => {
                    if (!this.functions.empty(data)) {
                        this.saeEnabled = data.sae.toLowerCase() === 'maarchrm' ? 'maarchRM' : 'externalSAE';
                        this.saeConfig['maarchRM']['sae'].setValue(this.saeEnabled === 'externalSAE' ? data.sae : 'maarchRM');
                        if (this.saeEnabled === 'maarchRM') {
                            this.saeConfig[this.saeEnabled] = {
                                sae: new UntypedFormControl(data.sae),
                                urlSAEService: new UntypedFormControl(data.urlSAEService),
                                token: new UntypedFormControl(data.token),
                                senderOrgRegNumber: new UntypedFormControl(data.senderOrgRegNumber),
                                accessRuleCode: new UntypedFormControl(data.accessRuleCode),
                                certificateSSL: new UntypedFormControl(data.certificateSSL),
                                userAgent: new UntypedFormControl(data.userAgent),
                                M2M: new UntypedFormControl(data.M2M.gec),
                                statusReplyReceived: new UntypedFormControl(data.statusReplyReceived),
                                statusReplyRejected: new UntypedFormControl(data.statusReplyRejected),
                                statusMailToPurge: new UntypedFormControl(data.statusMailToPurge),
                            };
                        } else {
                            this.externalSaeName = data.sae;
                        }
                        if (data.externalSAE !== undefined) {
                            this.setEXternalSaeData(data.externalSAE);
                        }
                    }
                    resolve(true);
                }),
                catchError((err: any) => {
                    this.notify.handleErrors(err);
                    return of(false);
                })
            ).subscribe();
        });
    }

    saveSaeConfig() {
        this.loading = true;
        this.hasError = false;
        this.http.put('../rest/seda/configuration', this.formatSaeConfig()).pipe(
            tap(() => {
                this.notify.success(this.translate.instant('lang.dataUpdated'));
            }),
            finalize(() => {
                this.loading = false;
                this.hasError = false;
            }),
            catchError((err: any) => {
                this.notify.handleErrors(err);
                this.hasError = true;
                return of(false);
            })
        ).subscribe();

        if (!this.hasError && this.saeEnabled === 'maarchRM') {
            this.dialog.open(CheckSaeInterconnectionComponent, {
                panelClass: 'maarch-modal',
                disableClose: true,
                width: '500px',
                data: {
                    urlSAEService: this.saeConfig['maarchRM']['urlSAEService'].value
                }
            });
        }
    }

    formatSaeConfig() {
        const maarchRM: any = {};
        const externalSAE: any = {};
        let objToSend: any = {};
        Object.keys(this.saeConfig).forEach(elemId => {
            if (elemId === 'maarchRM') {
                Object.keys(this.saeConfig[elemId]).forEach((item: any) => {
                    maarchRM[item] =  item !== 'M2M' ? this.saeConfig[elemId][item].value : { gec: this.saeConfig[elemId][item].value};
                });
            } else {
                Object.keys(this.saeConfig[elemId]).forEach((item: any) => {
                    switch (item) {
                        case 'retentionRules':
                            externalSAE[item] = this.retentionRules;
                            break;
                        case 'archiveEntities':
                            externalSAE[item] = this.archiveEntities;
                            break;
                        case 'archivalAgreements':
                            externalSAE[item] = this.archivalAgreements;
                            break;
                    }
                });
            }
        });
        return objToSend = {
            ... maarchRM,
            externalSAE: externalSAE
        };
    }

    setEXternalSaeData(data: any) {
        Object.keys(data).forEach((element: any) => {
            if (element === 'retentionRules') {
                this.retentionRules = data.retentionRules;
            } else if (element === 'archiveEntities') {
                this.archiveEntities = data.archiveEntities;
            } else if (element === 'archivalAgreements') {
                this.archivalAgreements = data.archivalAgreements;
            }
        });
    }

    getLabel(id: number, item: string) {
        switch (item) {
            case 'retentionRules': {
                return this.retentionRules.find((elem: any) => elem.id === id).label;
            }
            case 'archiveEntities': {
                return this.archiveEntities.find((elem: any) => elem.id === id).label;
            }
            case 'archivalAgreements': {
                return this.archivalAgreements.find((elem: any) => elem.id === id).label;
            }
        }
    }

    switchSae() {
        this.saeEnabled = this.saeEnabled === 'maarchRM' ? 'externalSAE' : 'maarchRM';
        this.saeConfig['maarchRM']['sae'].setValue(this.saeEnabled === 'externalSAE' ? this.externalSaeName : 'maarchRM');
    }

    addValue(externalData: string) {
        switch (externalData) {
            case 'retentionRules':
                this.retentionRules.push(
                    {
                        id: null,
                        labe: null
                    }
                );
                break;
            case 'archiveEntities':
                this.archiveEntities.push(
                    {
                        id: null,
                        labe: null
                    }
                );
                break;
            case 'archivalAgreements':
                this.archivalAgreements.push(
                    {
                        id: null,
                        labe: null
                    }
                );
                break;
        }
    }

    removeField(index: number, externalData: string) {
        if (externalData === 'retentionRules') {
            this.retentionRules.splice(index, 1);
        } else if (externalData === 'archiveEntities') {
            this.archiveEntities.splice(index, 1);
        } else if (externalData === 'archivalAgreements') {
            this.archivalAgreements.splice(index, 1);
        }
    }

    allValid() {
        if (this.saeEnabled === 'maarchRM') {
            const saeKeys: string[] = Object.keys(this.saeConfig['maarchRM']);
            return saeKeys.filter((element: any) => ['certificateSSL', 'M2M'].indexOf(element) === -1).every((item: any) => !this.functions.empty(this.saeConfig['maarchRM'][item].value));
        } else {
            return this.retentionRules.every((item: any) => !this.functions.empty(item.label) && !this.functions.empty(item.id)) &&
                    this.archiveEntities.every((item: any) => !this.functions.empty(item.label) && !this.functions.empty(item.id)) &&
                    this.archivalAgreements.every((item: any) => !this.functions.empty(item.label) && !this.functions.empty(item.id)) &&
                    !this.functions.empty(this.saeConfig['maarchRM']['sae'].value);

        }
    }
}
