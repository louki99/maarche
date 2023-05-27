import { Component, OnInit, Input, ViewChild, EventEmitter, Output } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { TranslateService } from '@ngx-translate/core';
import { NotificationService } from '@service/notification/notification.service';
import { HeaderService } from '@service/header.service';
import { MatDialog, MatDialogRef } from '@angular/material/dialog';
import { AppService } from '@service/app.service';
import { tap, catchError, exhaustMap, filter, finalize } from 'rxjs/operators';
import { of } from 'rxjs';
import { SortPipe } from '../../../plugins/sorting.pipe';
import { CdkDragDrop, moveItemInArray, transferArrayItem } from '@angular/cdk/drag-drop';
import { UntypedFormControl, Validators, UntypedFormGroup, ValidationErrors, ValidatorFn, AbstractControl } from '@angular/forms';
import { DiffusionsListComponent } from '../../diffusions/diffusions-list.component';
import { FunctionsService } from '@service/functions.service';
import { ConfirmComponent } from '../../../plugins/modal/confirm.component';
import { IssuingSiteInputComponent } from '../../administration/registered-mail/issuing-site/indexing/issuing-site-input.component';
import { RegisteredMailRecipientInputComponent } from '../../administration/registered-mail/indexing/recipient-input.component';
import { Router } from '@angular/router';
import { LinkResourceModalComponent } from '@appRoot/linkedResource/linkResourceModal/link-resource-modal.component';
import { IndexingModelValuesSelectorComponent } from '@appRoot/administration/indexingModel/valuesSelector/values-selector.component';


@Component({
    selector: 'app-indexing-form',
    templateUrl: 'indexing-form.component.html',
    styleUrls: ['indexing-form.component.scss'],
    providers: [SortPipe]
})

export class IndexingFormComponent implements OnInit {

    @Input() indexingFormId: number;
    @Input() resId: number = null;
    @Input() groupId: number;
    @Input() adminMode: boolean = false;
    @Input() canEdit: boolean = true;
    @Input() mode: string = 'indexation';

    @Input() hideDiffusionList: boolean = false;

    @Output() retrieveDocumentEvent = new EventEmitter<string>();
    @Output() loadingFormEndEvent = new EventEmitter<string>();
    @Output() afterSaveEvent = new EventEmitter<string>();
    @Output() reloadBadge = new EventEmitter<any>();
    @Output() resourceToLinkEvent = new EventEmitter<any>();

    @ViewChild('appDiffusionsList', { static: false }) appDiffusionsList: DiffusionsListComponent;
    @ViewChild('appIssuingSiteInput', { static: false }) appIssuingSiteInput: IssuingSiteInputComponent;
    @ViewChild('appRegisteredMailRecipientInput', { static: false }) appRegisteredMailRecipientInput: RegisteredMailRecipientInputComponent;

    loading: boolean = true;


    fieldCategories: any[] = ['mail', 'contact', 'process', 'classifying'];

    indexingModelsCore: any[] = [
        {
            identifier: 'doctype',
            label: this.translate.instant('lang.doctype'),
            unit: 'mail',
            type: 'select',
            system: true,
            mandatory: true,
            enabled: true,
            default_value: '',
            values: []
        },
        {
            identifier: 'subject',
            label: this.translate.instant('lang.subject'),
            unit: 'mail',
            type: 'string',
            system: true,
            mandatory: true,
            enabled: true,
            default_value: '',
            values: []
        },
    ];

    indexingModels_mail: any[] = [];
    indexingModels_contact: any[] = [];
    indexingModels_process: any[] = [];
    indexingModels_classement: any[] = [];

    indexingModels_mailClone: any[] = [];
    indexingModels_contactClone: any[] = [];
    indexingModels_processClone: any[] = [];
    indexingModels_classementClone: any[] = [];

    indexingModelsCustomFields: any[] = [];

    allowedValues: number[] = [];

    availableFields: any[] = [
        {
            identifier: 'recipients',
            label: this.translate.instant('lang.getRecipients'),
            type: 'autocomplete',
            default_value: [],
            values: [],
            enabled: true,
        },
        {
            identifier: 'priority',
            label: this.translate.instant('lang.priority'),
            type: 'select',
            default_value: null,
            values: [],
            enabled: true,
        },
        {
            identifier: 'confidentiality',
            label: this.translate.instant('lang.confidential'),
            type: 'radio',
            default_value: null,
            values: [{ 'id': true, 'label': this.translate.instant('lang.yes') }, { 'id': false, 'label': this.translate.instant('lang.no') }],
            enabled: true,
        },
        {
            identifier: 'initiator',
            label: this.translate.instant('lang.initiatorEntityAlt'),
            type: 'select',
            default_value: null,
            values: [],
            enabled: true,
        },
        {
            identifier: 'departureDate',
            label: this.translate.instant('lang.departureDate'),
            type: 'date',
            default_value: null,
            values: [],
            enabled: true,
        },
        {
            identifier: 'processLimitDate',
            label: this.translate.instant('lang.processLimitDate'),
            type: 'date',
            default_value: null,
            values: [],
            enabled: false,
        },
        {
            identifier: 'tags',
            label: this.translate.instant('lang.tags'),
            type: 'autocomplete',
            default_value: [],
            values: ['/rest/autocomplete/tags', '/rest/tags'],
            enabled: true,
        },
        {
            identifier: 'senders',
            label: this.translate.instant('lang.getSenders'),
            type: 'autocomplete',
            default_value: [],
            values: ['/rest/autocomplete/correspondents'],
            enabled: true,
        },
        {
            identifier: 'destination',
            label: this.translate.instant('lang.destination'),
            type: 'select',
            default_value: null,
            values: [],
            enabled: true,
        },
        {
            identifier: 'folders',
            label: this.translate.instant('lang.folders'),
            type: 'autocomplete',
            default_value: [],
            values: ['/rest/autocomplete/folders', '/rest/folders'],
            enabled: true,
        },
        {
            identifier: 'documentDate',
            label: this.translate.instant('lang.docDate'),
            unit: 'mail',
            type: 'date',
            default_value: null,
            values: [],
            enabled: true,
        },
        {
            identifier: 'arrivalDate',
            label: this.translate.instant('lang.arrivalDate'),
            unit: 'mail',
            type: 'date',
            default_value: null,
            values: [],
            enabled: true,
        },
        {
            identifier: 'registeredMail_type',
            label: this.translate.instant('lang.registeredMailType'),
            type: 'select',
            default_value: null,
            values: [{ 'id': '2D', 'label': this.translate.instant('lang.registeredMail_2D') }, { 'id': '2C', 'label': this.translate.instant('lang.registeredMail_2C') }, { 'id': 'RW', 'label': this.translate.instant('lang.registeredMail_RW') }],
            enabled: true,
        },
        {
            identifier: 'registeredMail_issuingSite',
            label: this.translate.instant('lang.issuingSite'),
            type: 'issuingSite',
            default_value: null,
            values: [],
            enabled: true,
        },
        {
            identifier: 'registeredMail_number',
            label: this.translate.instant('lang.registeredMailNumber'),
            type: 'string',
            default_value: null,
            values: [],
            enabled: false,
        },
        {
            identifier: 'registeredMail_warranty',
            label: this.translate.instant('lang.warrantyLevel'),
            type: 'radio',
            default_value: null,
            values: [{ 'id': 'R1', 'label': 'R1' }, { 'id': 'R2', 'label': 'R2' }, { 'id': 'R3', 'label': 'R3' }],
            enabled: true,
        },
        {
            identifier: 'registeredMail_letter',
            label: this.translate.instant('lang.letter'),
            type: 'radio',
            default_value: null,
            values: [{ 'id': true, 'label': this.translate.instant('lang.yes') }, { 'id': false, 'label': this.translate.instant('lang.no') }],
            enabled: true,
        },
        {
            identifier: 'registeredMail_recipient',
            label: this.translate.instant('lang.registeredMailRecipient'),
            type: 'autocomplete',
            default_value: null,
            values: ['/rest/autocomplete/correspondents'],
            enabled: true,
        },
        {
            identifier: 'registeredMail_reference',
            label: this.translate.instant('lang.registeredMailReference'),
            type: 'string',
            default_value: null,
            values: [],
            enabled: true,
        },
    ];
    availableFieldsClone: any[] = [];

    availableCustomFields: any[] = [];
    availableCustomFieldsClone: any[] = null;

    indexingFormGroup: UntypedFormGroup;

    arrFormControl: any = {};

    mandatoryFile = false;
    currentCategory: string = '';
    currentPriorityColor: string = '';

    currentResourceValues: any = null;

    selfDest: boolean = false;
    customDiffusion: any = [];

    dialogRef: MatDialogRef<any>;

    mustFixErrors: boolean = false;

    isPrivate: boolean = false;
    allDoctypes: boolean = true;

    hasLinkedRes: boolean = false;
    linkedResources: any[] = [];
    selectedContactClone: any = null;
    suggestLinksNdaysAgo: number;
    creationDateClone: Date;
    msgToDisplay: string = '';

    indexingModelClone: any;
    resDataClone: any;

    entitiesArray: any[] = [];

    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        private notify: NotificationService,
        public dialog: MatDialog,
        private headerService: HeaderService,
        public appService: AppService,
        public functions: FunctionsService,
        private sortPipe: SortPipe,
        private route: Router
    ) {

    }

    async ngOnInit(): Promise<void> {

        this.availableFieldsClone = JSON.parse(JSON.stringify(this.availableFields));

        this.fieldCategories.forEach(category => {
            this['indexingModels_' + category] = [];
        });

        if (!this.adminMode) {
            await this.getParameter();
        }

        if (this.indexingFormId <= 0 || this.indexingFormId === undefined) {

            await this.initFields();
            await this.initCustomFields();

            this.initElemForm();
        } else {
            this.loadForm(this.indexingFormId);
        }
    }

    initFields() {
        return new Promise((resolve, reject) => {
            this.fieldCategories.forEach(element => {
                this['indexingModels_' + element] = this.indexingModelsCore.filter((x: any, i: any, a: any) => x.unit === element);
                this['indexingModels_' + element].forEach((field: any) => {
                    this.initValidator(field);
                });
            });
            resolve(true);
        });
    }

    initCustomFields() {
        return new Promise((resolve, reject) => {

            this.http.get(`../rest/customFields?resId=${this.resId}`).pipe(
                tap((data: any) => {
                    const withFormMode = data.customFields.filter((item: { mode: any }) => item.mode === 'form');
                    this.availableCustomFields = withFormMode.map((info: any) => {
                        info.identifier = 'indexingCustomField_' + info.id;
                        info.system = false;
                        info.enabled = true;

                        if (['integer', 'string', 'date'].indexOf(info.type) > -1 && !this.functions.empty(info.values)) {
                            info.default_value = info.values[0].key;
                        } else {
                            info.default_value = ['contact', 'banAutocomplete'].indexOf(info.type) > -1 ? [] : null;
                        }
                        info.values = info.values.length > 0 ? info.values.map((custVal: any) => ({
                            id: custVal.key,
                            label: custVal.label
                        })) : info.values;
                        return info;
                    });
                    this.availableCustomFieldsClone = JSON.parse(JSON.stringify(this.availableCustomFields));
                    resolve(true);
                }),
                catchError((err: any) => {
                    this.notify.handleErrors(err);
                    return of(false);
                })
            ).subscribe();
        });
    }

    drop(event: CdkDragDrop<string[]>) {
        event.item.data.unit = event.container.id.split('_')[1];

        if (event.previousContainer === event.container) {
            moveItemInArray(event.container.data, event.previousIndex, event.currentIndex);
        } else {
            const regex = /registeredMail_[.]*/g;

            if (event.item.data.identifier.match(regex) !== null && event.previousContainer.id === 'fieldsList') {
                this.transferRegisteredMailInput(event);
            } else {
                this.transferInput(event);
            }
            if (['destination', 'priority'].indexOf(event.item.data.identifier) > -1) {
                this.initElemForm();
            }
        }
    }

    transferInput(event: CdkDragDrop<string[]>) {
        this.initValidator(event.item.data);
        transferArrayItem(event.previousContainer.data,
            event.container.data,
            event.previousIndex,
            event.currentIndex);
    }

    onSubmit() {
        let arrIndexingModels: any[] = [];
        this.fieldCategories.forEach(category => {
            arrIndexingModels = arrIndexingModels.concat(this['indexingModels_' + category]);
        });
    }

    removeItem(arrTarget: string, item: any, index: number) {
        this.dialogRef = this.dialog.open(ConfirmComponent, { panelClass: 'maarch-modal', autoFocus: false, disableClose: true, data: { title: this.translate.instant('lang.indexingModelModification'), msg: this.translate.instant('lang.updateIndexingFieldWarning') } });

        this.dialogRef.afterClosed().pipe(
            filter((data: string) => data === 'ok'),
            tap(() => {
                item.mandatory = false;
                if (item.identifier !== 'processLimitDate') {
                    item.enabled = true;
                }
                if (item.identifier.indexOf('indexingCustomField') > -1) {
                    this.availableCustomFields.push(item);
                    this[arrTarget].splice(index, 1);
                } else {
                    this.availableFields.push(item);
                    this[arrTarget].splice(index, 1);
                }
            }),
            catchError((err: any) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    getDatas(withDiffusionList = true) {
        let arrIndexingModels: any[] = [];
        this.fieldCategories.forEach(category => {
            arrIndexingModels = arrIndexingModels.concat(this['indexingModels_' + category]);
        });
        arrIndexingModels.forEach(element => {
            if (element.type === 'date' && !this.functions.empty(this.arrFormControl[element.identifier].value)) {
                if (element.today === true) {
                    if (this.adminMode) {
                        element.default_value = '_TODAY';
                    } else {
                        element.default_value = this.functions.formatDateObjectToDateString(this.arrFormControl[element.identifier].value, false);
                    }
                } else {
                    if (element.identifier === 'processLimitDate') {
                        element.default_value = this.functions.formatDateObjectToDateString(this.arrFormControl[element.identifier].value, true);
                    } else {
                        element.default_value = this.functions.formatDateObjectToDateString(this.arrFormControl[element.identifier].value, false);
                    }
                }
            } else {
                element.default_value = this.functions.empty(this.arrFormControl[element.identifier].value) ? null : this.arrFormControl[element.identifier].value;
            }

            if (element.identifier === 'destination' && !this.adminMode && withDiffusionList) {
                arrIndexingModels.push({
                    identifier: 'diffusionList',
                    default_value: this.arrFormControl['diffusionList'].value
                });
            }

        });

        if (!this.adminMode) {
            arrIndexingModels.push({
                identifier: 'modelId',
                default_value: this.indexingFormId
            });

            if (this.mode === 'indexation') {
                arrIndexingModels.push({
                    identifier: 'followed',
                    default_value: this.arrFormControl['mail­tracking'].value
                });
            }
        }

        return arrIndexingModels;
    }

    saveData() {
        return new Promise((resolve, reject) => {
            if (this.isValidForm()) {
                this.mustFixErrors = false;
                const formatdatas = this.formatDatas(this.getDatas());

                this.http.put(`../rest/resources/${this.resId}`, formatdatas).pipe(
                    tap(() => {
                        if (this.currentCategory === 'registeredMail') {
                            this.http.put(`../rest/registeredMails/${this.resId}`, {
                                type: formatdatas.registeredMail_type,
                                warranty: formatdatas.registeredMail_warranty,
                                issuingSiteId: formatdatas.registeredMail_issuingSite,
                                letter: formatdatas.registeredMail_letter,
                                recipient: formatdatas.registeredMail_recipient,
                                reference: formatdatas.registeredMail_reference
                            }).pipe(
                                tap(() => {
                                    this.loadForm(this.indexingFormId);
                                    this.afterSaveEvent.emit();
                                }),
                                catchError((err: any) => {
                                    this.notify.handleErrors(err);
                                    return of(false);
                                })
                            ).subscribe();
                        }
                    }),
                    tap(() => {
                        if (this.currentResourceValues.find((item: any) => item.identifier === 'doctype').default_value !== this['indexingModels_mail'].find((item: any) => item.identifier === 'doctype').default_value) {
                            this.setAllowedValues(this['indexingModels_mail'].find((item: any) => item.identifier === 'doctype'), true);
                        }
                        this.currentResourceValues = JSON.parse(JSON.stringify(this.getDatas(false)));
                        this.notify.success(this.translate.instant('lang.dataUpdated'));
                        resolve(true);
                    }),
                    catchError((err: any) => {
                        this.notify.handleErrors(err);
                        return of(false);
                    })
                ).subscribe();
                return true;
            } else {
                this.mustFixErrors = true;
                this.notify.error(this.translate.instant('lang.mustFixErrors'));
                return false;
            }
        });

    }

    formatDatas(datas: any) {
        const formatData: any = {};
        const regex = /indexingCustomField_[.]*/g;

        formatData['customFields'] = {};

        datas.forEach((element: any) => {

            if (element.identifier.match(regex) !== null) {

                formatData['customFields'][element.identifier.split('_')[1]] = element.default_value;

                // } else if (element.identifier === 'registeredMail_recipient') {
                //     formatData[element.identifier] = this.appRegisteredMailRecipientInput.getFormatedAdress();
            } else {
                formatData[element.identifier] = element.default_value;
            }
        });
        return formatData;
    }

    getCategory() {
        return this.currentCategory;
    }

    getAvailableFields() {
        return this.availableFields;
    }

    getAvailableCustomFields() {
        return this.availableCustomFields;
    }

    isModified() {
        let state = false;
        let compare: string = '';
        let compareClone: string = '';

        this.fieldCategories.forEach(category => {

            compare = JSON.stringify((this['indexingModels_' + category]));
            compareClone = JSON.stringify((this['indexingModels_' + category + 'Clone']));

            if (compare !== compareClone) {
                state = true;
            }
        });
        return state;
    }

    isResourceModified() {
        if (this.loading || JSON.stringify(this.currentResourceValues) === JSON.stringify(this.getDatas(false))) {
            return false;
        } else {
            return true;
        }
    }

    setModification() {
        this.fieldCategories.forEach(element => {
            this['indexingModels_' + element + 'Clone'] = JSON.parse(JSON.stringify(this['indexingModels_' + element]));
        });
    }

    cancelModification() {
        this.fieldCategories.forEach(element => {
            this['indexingModels_' + element] = JSON.parse(JSON.stringify(this['indexingModels_' + element + 'Clone']));
        });
    }

    setDocumentDateField(elem: any) {
        elem.startDate = '';
        elem.endDate = '_TODAY';

        this.fieldCategories.forEach(element => {
            if (this['indexingModels_' + element].filter((field: any) => field.identifier === 'arrivalDate').length > 0) {
                elem.endDate = 'arrivalDate';
            } else if (this['indexingModels_' + element].filter((field: any) => field.identifier === 'departureDate').length > 0) {
                elem.endDate = 'departureDate';
            }
        });
    }

    setDestinationField(elem: any) {
        const route = this.adminMode || this.mode !== 'indexation' ? '../rest/indexingModels/entities' : `../rest/indexing/groups/${this.groupId}/entities`;

        return new Promise((resolve, reject) => {
            this.http.get(route).pipe(
                tap((data: any) => {
                    this.entitiesArray = [];
                    const myEntities: any[] = data.entities.map((entity: any) => ({
                        id: entity.id,
                        entityId: entity.entity_id,
                        title: entity.entity_label,
                        label: entity.entity_label,
                        parentId: entity.parent_entity_id ?? null,
                        level: entity.level,
                        enabled: entity.enabled
                    }));

                    const parents: any[] = this.sortPipe.transform(myEntities.filter((item: any) => this.functions.empty(item.parentId)), 'title');
                    parents.forEach((entity: any, index: number) => {
                        this.entitiesArray.push(entity);
                        const soretdArray: any[] = this.sortPipe.transform(myEntities.filter((item: any) => item.parentId === entity.entityId), 'title');
                        soretdArray.forEach((element: any) => {
                            const nonBreakingSpace: string = '&nbsp;&nbsp;&nbsp;&nbsp;';
                            element.label = nonBreakingSpace.repeat(element.level) + element.label;
                            this.entitiesArray.push(element);
                            this.getEntity(myEntities, element);
                        });
                    });

                    this.entitiesArray = [... new Set(this.entitiesArray)];

                    if (this.adminMode) {
                        const title = '';
                        elem.values = [
                            {
                                id: '#myPrimaryEntity',
                                title: this.translate.instant('lang.myPrimaryEntity'),
                                label: `<i class="fa fa-hashtag"></i>&nbsp;${this.translate.instant('lang.myPrimaryEntity')}`,
                                disabled: false
                            }
                        ];
                        elem.values = elem.values.concat(this.entitiesArray.map((entity: any) => ({
                            id: entity.id,
                            title: entity.title,
                            label: entity.label,
                            disabled: false
                        })));
                    } else {
                        const title = '';
                        if (elem.default_value === '#myPrimaryEntity') {
                            this.selfDest = this.currentCategory === 'outgoing';
                            elem.default_value = this.headerService.user.entities[0]?.id;
                            this.arrFormControl[elem.identifier].setValue(elem.default_value);
                        } else {
                            this.selfDest = false;
                            const defaultVal = data.entities.filter((entity: any) => entity.enabled === true && entity.id === elem.default_value);
                            elem.default_value = defaultVal.length > 0 ? defaultVal[0].id : null;
                            this.arrFormControl[elem.identifier].setValue(defaultVal.length > 0 ? defaultVal[0].id : '');
                        }
                        elem.values = this.entitiesArray.map((entity: any) => ({
                            id: entity.id,
                            title: entity.title,
                            label: entity.label,
                            disabled: !entity.enabled
                        }));
                        elem.values = [... new Set(elem.values)];
                        elem.event = 'loadDiffusionList';
                        elem.allowedEntities = elem.values.filter((val: any) => val.disabled === false).map((entities: any) => entities.id);
                    }
                    resolve(true);
                })
            ).subscribe();
        });

    }

    getEntity(all: any[], entity: any) {
        const soretdArray: any[] = this.sortPipe.transform(all.filter((item: any) => item.parentId === entity.entityId), 'title');
        soretdArray.forEach((element: any) => {
            const nonBreakingSpace: string = '&nbsp;&nbsp;&nbsp;&nbsp;';
            element.label = nonBreakingSpace.repeat(element.level) + element.label;
            this.entitiesArray.push(element);
            this.getEntity(all, element);
        });
    }

    setInitiatorField(elem: any) {
        elem.values = this.headerService.user.entities.map((entity: any) => ({
            id: entity.id,
            label: entity.entity_label
        }));
    }

    setCategoryField(elem: any) {
        return new Promise((resolve, reject) => {
            this.http.get('../rest/categories').pipe(
                tap((data: any) => {
                    elem.values = data.categories;
                    resolve(true);
                })
            ).subscribe();
        });
    }

    setPriorityField(elem: any) {
        return new Promise((resolve, reject) => {
            this.http.get('../rest/priorities').pipe(
                tap((data: any) => {
                    elem.values = data.priorities;
                    elem.event = 'calcLimitDateByPriority';
                    if (elem.default_value !== null) {
                        this.calcLimitDateByPriority(elem, elem.default_value);
                    }
                    resolve(true);
                })
            ).subscribe();
        });
    }

    setDoctypeField(elem: any) {
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
                                    color: secondDoctype.css_style,
                                    firstLevelId: doctype.doctypes_first_level_id
                                });
                                arrValues = arrValues.concat(data.structure.filter((infoDoctype: any) => infoDoctype.doctypes_second_level_id === secondDoctype.doctypes_second_level_id && infoDoctype.description !== undefined).map((infoType: any) => ({
                                    id: infoType.type_id,
                                    secondLevelId: secondDoctype.doctypes_second_level_id,
                                    label: '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;' + infoType.description,
                                    title: infoType.description,
                                    disabled: false,
                                    isTitle: false,
                                })));
                            });
                        }
                    });
                    elem.values = arrValues;
                    elem.event = 'calcLimitDate';
                    if (!this.functions.empty(elem.default_value) && !this.adminMode) {
                        this.calcLimitDate(elem, elem.default_value);
                    }
                    this.setAllowedValues(elem);
                    resolve(true);
                })
            ).subscribe();
        });
    }

    async initElemForm(saveResourceState: boolean = true) {
        this.loading = true;

        if (!this.adminMode) {
            this.arrFormControl['mail­tracking'].setValue(false);
        }
        this.currentPriorityColor = '';


        await Promise.all(this.fieldCategories.map(async (element) => {
            await Promise.all(this['indexingModels_' + element].map(async (elem: any) => {
                if (elem.identifier === 'documentDate') {
                    this.setDocumentDateField(elem);

                } else if (elem.identifier === 'destination') {
                    await this.setDestinationField(elem);

                } else if (elem.identifier === 'arrivalDate') {
                    elem.startDate = 'documentDate';
                    elem.endDate = '_TODAY';

                } else if (elem.identifier === 'initiator' && !this.adminMode) {
                    this.setInitiatorField(elem);

                } else if (elem.identifier === 'processLimitDate') {
                    elem.startDate = '_TODAY';
                    elem.endDate = '';
                    elem.event = 'setPriorityColorByLimitDate';

                } else if (elem.identifier === 'departureDate') {
                    elem.startDate = 'documentDate';
                    elem.endDate = '';

                } else if (elem.identifier === 'folders') {
                    elem.values = null;

                } else if (elem.identifier === 'category_id') {
                    await this.setCategoryField(elem);

                } else if (elem.identifier === 'priority') {
                    await this.setPriorityField(elem);

                } else if (elem.identifier === 'doctype') {
                    await this.setDoctypeField(elem);
                } else if (elem.identifier === 'registeredMail_type') {
                    elem.event = 'getIssuingSites';
                    // await this.setDoctypeField(elem);
                }
            }));
        }));

        if (this.resId !== null) {
            await this.setResource(saveResourceState);
        }

        this.loading = false;
    }

    setResource(saveResourceState: boolean = true) {
        return new Promise((resolve, reject) => {
            this.http.get(`../rest/resources/${this.resId}`).pipe(
                tap(async (data: any) => {
                    this.resDataClone = JSON.parse(JSON.stringify(data));
                    this.creationDateClone = JSON.parse(JSON.stringify(data['creationDate']));
                    await Promise.all(this.fieldCategories.map(async (element: any) => {

                        // this.fieldCategories.forEach(async element => {
                        await Promise.all(this['indexingModels_' + element].map(async (elem: any) => {
                            // this['indexingModels_' + element].forEach((elem: any) => {
                            const customId: any = Object.keys(data.customFields).filter(index => index === elem.identifier.split('indexingCustomField_')[1])[0];

                            if (Object.keys(data).indexOf(elem.identifier) > -1 || customId !== undefined) {
                                let fieldValue: any = '';
                                if (customId !== undefined) {
                                    const myCustomField: any = this.availableCustomFieldsClone.find((custom: any) => custom.id.toString() === customId);
                                    if (['select', 'radio'].indexOf(myCustomField?.type) > -1) {
                                        fieldValue = !this.functions.empty(myCustomField.values.find((item: any) => item.id === data.customFields[customId])) ? data.customFields[customId] : '';
                                    } else if (myCustomField?.type === 'checkbox') {
                                        fieldValue = myCustomField.values.map((item: any) => item.id).filter((el: any) => data.customFields[customId].includes(el));
                                    } else {
                                        fieldValue = data.customFields[customId];
                                    }
                                } else {
                                    fieldValue = data[elem.identifier];
                                }

                                if (elem.identifier === 'registeredMail_type') {
                                    this.getIssuingSites(null, fieldValue);
                                }

                                if (elem.identifier === 'priority') {
                                    this.setPriorityColor(null, elem.default_value !== null ? elem.default_value : fieldValue);
                                } else if (elem.identifier === 'processLimitDate' && !this.functions.empty(fieldValue)) {
                                    elem.startDate = '';
                                } else if (elem.identifier === 'destination') {
                                    if (this.mode === 'process') {
                                        this.arrFormControl[elem.identifier].disable();
                                    }
                                    this.arrFormControl['diffusionList'].disable();
                                } else if (elem.identifier === 'initiator' && elem.values.filter((val: any) => val.id === fieldValue).length === 0 && !this.functions.empty(fieldValue)) {
                                    await this.getCurrentInitiator(elem, fieldValue);
                                }

                                if (elem.type === 'date') {
                                    if (!this.isPrivate && this.functions.empty(fieldValue)) {
                                        elem.default_value = null;
                                        this.arrFormControl[elem.identifier].value = null;
                                    } else if (!this.functions.empty(fieldValue)) {
                                        fieldValue = new Date(fieldValue);
                                    }
                                }
                                if (!this.functions.empty(fieldValue)) {
                                    this.arrFormControl[elem.identifier].setValue(fieldValue);
                                }
                            } else if (!saveResourceState && elem.identifier === 'destination') {
                                this.arrFormControl[elem.identifier].disable();
                                this.arrFormControl[elem.identifier].setValidators([]);
                                this.arrFormControl['diffusionList'].disable();
                            }

                            if (!this.canEdit) {
                                this.arrFormControl[elem.identifier].disable();
                            }
                        }));
                    }));
                    this.arrFormControl['mail­tracking'].setValue(data.followed);
                    if (saveResourceState) {
                        this.currentResourceValues = JSON.parse(JSON.stringify(this.getDatas(false)));
                    }
                    if (this.indexingModelClone.master === null) {
                        const mandatoryFields: any[] = this.indexingModelClone.fields.filter((item: any) => !item.mandatory && !this.functions.empty(item.default_value) && item.identifier !== 'documentDate');
                        mandatoryFields.forEach((element: any) => {
                            if (this.functions.empty(this.resDataClone[element.identifier]) && !element.identifier.includes('CustomField')) {
                                this.arrFormControl[element.identifier].setValue(this.resDataClone[element.identifier]);
                            } else if (element.identifier.includes('CustomField')) {
                                const customFieldId: number = +element.identifier.substr(element.identifier.indexOf('_') + 1);
                                if (this.functions.empty(this.resDataClone.customFields[customFieldId])) {
                                    this.arrFormControl[element.identifier].setValue('');
                                }
                            }
                        });
                        this.currentResourceValues = JSON.parse(JSON.stringify(this.getDatas(false)));
                    }
                    const priorityField: any = this.currentResourceValues.find((field: any) => field.identifier === 'priority')?.default_value;
                    this.setPriorityColor(null, !this.functions.empty(priorityField) ? priorityField : '');
                    resolve(true);
                }),
                catchError((err: any) => {
                    this.notify.handleErrors(err);
                    return of(false);
                })
            ).subscribe();
        });
    }

    getCurrentInitiator(field: any, initiatorId: number) {
        return new Promise((resolve, reject) => {
            this.http.get(`../rest/entities/${initiatorId}`).pipe(
                tap((data: any) => {
                    field.values.unshift({
                        id: data.id,
                        label: data.entity_label
                    });
                    resolve(true);
                })
            ).subscribe();
        });
    }

    createForm() {
        this.indexingFormGroup = new UntypedFormGroup(this.arrFormControl);
        this.loadingFormEndEvent.emit();
    }

    async resetForm() {
        Object.keys(this.arrFormControl).forEach(element => {
            delete this.arrFormControl[element];
        });

        this.availableFields = JSON.parse(JSON.stringify(this.availableFieldsClone));
        this.fieldCategories.forEach(category => {
            this['indexingModels_' + category] = [];
        });

        if (this.availableCustomFieldsClone === null) {
            await this.initCustomFields();
        } else {
            this.availableCustomFields = JSON.parse(JSON.stringify(this.availableCustomFieldsClone));
        }
    }

    async loadForm(indexModelId: number, saveResourceState: boolean = true) {
        this.loading = true;
        this.hasLinkedRes = false;
        this.linkedResources = [];
        this.customDiffusion = [];
        this.indexingFormId = indexModelId;
        this.resourceToLinkEvent.emit([]);

        await this.resetForm();

        if (!this.adminMode) {
            this.arrFormControl['mail­tracking'] = new UntypedFormControl({ value: '', disabled: this.adminMode ? true : false });
        }

        this.http.get(`../rest/indexingModels/${indexModelId}`).pipe(
            tap(async (data: any) => {
                this.isPrivate = data.indexingModel.private || data.indexingModel.master !== null;
                this.indexingFormId = data.indexingModel.master !== null ? data.indexingModel.master : data.indexingModel.id;
                this.currentCategory = data.indexingModel.category;
                this.mandatoryFile = data.indexingModel.mandatoryFile;
                this.allDoctypes = data.indexingModel.allDoctypes;
                if (data.indexingModel.master !== null) {
                    this.getAllowedValues(data.indexingModel.master);
                }
                let fieldExist: boolean;
                if (data.indexingModel.fields.length === 0) {
                    this.initFields();
                    this.notify.error(this.translate.instant('lang.noFieldInModelMsg'));
                } else {
                    data.indexingModel.fields.forEach((field: any) => {
                        fieldExist = false;
                        field.system = false;
                        field.values = [];

                        let indexFound = this.availableFields.map(avField => avField.identifier).indexOf(field.identifier);

                        if (indexFound > -1) {
                            field.label = this.availableFields[indexFound].label;
                            field.default_value = !this.functions.empty(field.default_value) ? field.default_value : this.availableFields[indexFound].default_value;
                            field.values = this.availableFields[indexFound].values;
                            field.type = this.availableFields[indexFound].type;
                            this.availableFields.splice(indexFound, 1);
                            fieldExist = true;
                        }

                        indexFound = this.availableCustomFields.map(avField => avField.identifier).indexOf(field.identifier);

                        if (indexFound > -1) {
                            field.type = this.availableCustomFields[indexFound].type;
                            field.label = this.availableCustomFields[indexFound].label;
                            field.values = this.availableCustomFields[indexFound].values;
                            field.type = this.availableCustomFields[indexFound].type;
                            field.SQLMode = this.availableCustomFields[indexFound].SQLMode;
                            if (['select', 'radio', 'checkbox'].indexOf(field.type) > -1) {
                                if (!this.functions.empty(field.default_value)) {
                                    if (['select', 'radio'].indexOf(field.type) > -1) {
                                        field.default_value = field.values.map((item: any) => item.id).find((elem: any) => elem.indexOf(field.default_value) > -1) ? field.default_value : null;
                                    } else if (field.type === 'checkbox') {
                                        field.default_value = field.values.map((item: any) => item.id).filter((element: any) => field.default_value.incoming(element));
                                    }
                                }
                            } else {
                                field.default_value = !this.functions.empty(field.default_value) ? field.default_value : this.availableCustomFields[indexFound].default_value;
                            }
                            this.availableCustomFields.splice(indexFound, 1);
                            fieldExist = true;
                        }

                        indexFound = this.indexingModelsCore.map(info => info.identifier).indexOf(field.identifier);

                        if (indexFound > -1) {
                            field.label = this.indexingModelsCore[indexFound].label;
                            field.default_value = !this.functions.empty(field.default_value) ? field.default_value : this.indexingModelsCore[indexFound].default_value;
                            field.values = this.indexingModelsCore[indexFound].values;
                            field.type = this.indexingModelsCore[indexFound].type;
                            fieldExist = true;
                            field.system = true;
                        }

                        if (field.type === 'date' && field.default_value === '_TODAY') {
                            field.today = true;
                            field.default_value = new Date();
                        }

                        if (field.identifier === 'initiator' && this.mode === 'indexation' && this.functions.empty(field.default_value)) {
                            if (this.headerService.user.entities[0]) {
                                field.default_value = this.headerService.user.entities.filter((entity: any) => entity.primary_entity === 'Y')[0].id;
                            }
                        }

                        if (field.identifier === 'diffusionList') {
                            this.customDiffusion = field.default_value;
                        }

                        if (fieldExist) {
                            this['indexingModels_' + field.unit].push(field);
                            this.initValidator(field);
                        } else if (field.identifier !== 'diffusionList') {
                            this.notify.error(this.translate.instant('lang.fieldNotExist') + ': ' + field.identifier);
                        }

                    });
                    this.indexingModelClone = JSON.parse(JSON.stringify(data.indexingModel));
                }

                await this.initElemForm(saveResourceState).then(() => {
                    if (this.adminMode && !this.functions.empty(this.arrFormControl['processLimitDate'])) {
                        this.arrFormControl['processLimitDate'].disable();
                        this.indexingModelClone.fields.find((field: any) => field.identifier === 'processLimitDate').enabled = false;
                    }
                });
                this.createForm();

            }),
            catchError((err: any) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    getAllowedValues(id: number) {
        this.http.get(`../rest/indexingModels/${id}`).pipe(
            tap((data: any) => {
                this.allowedValues = data.indexingModel.fields.find((item: any) => item.identifier === 'doctype').allowedValues;
                if (this.functions.empty(this['indexingModels_mail'].find((item: any) => item.identifier === 'doctype').allowedValues)) {
                    this['indexingModels_mail'].find((item: any) => item.identifier === 'doctype').allowedValues = this.allowedValues;
                    if (this.allowedValues?.length > 0) {
                        this.setAllowedValues(this['indexingModels_mail'].find((item: any) => item.identifier === 'doctype'));
                    }
                }
            }),
            catchError((err: any) => {
                this.notify.handleSoftErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    enableField(field: any, enable: boolean) {
        if (enable) {
            if (!this.isAlwaysDisabledField(field)) {
                this.arrFormControl[field.identifier].enable();
            }
            field.enabled = true;
        } else {
            if (this.functions.empty(this.arrFormControl[field.identifier].value) && field.mandatory) {
                alert(this.translate.instant('lang.canNotDisabledField'));
                return false;
            }
            this.arrFormControl[field.identifier].disable();
            field.enabled = false;
        }
    }

    isAlwaysDisabledField(field: any) {
        if (this.adminMode && ((['integer', 'string', 'date'].indexOf(field.type) > -1 && !this.functions.empty(field.values)) || field.today)) {
            return true;
        }
        return false;
    }

    initValidator(field: any) {
        const valArr: ValidatorFn[] = [];

        const disabledState = !field.enabled || this.isAlwaysDisabledField(field);
        if (!disabledState && field.identifier !== 'processLimitDate') {
            field.enabled = true;
        }

        this.arrFormControl[field.identifier] = new UntypedFormControl({ value: field.default_value, disabled: disabledState });

        if (field.type === 'integer') {
            valArr.push(this.regexValidator(new RegExp('[+-]?([0-9]*[.])?[0-9]+'), { 'floatNumber': '' }));
        } else if (field.type === 'date' && !this.functions.empty(field.default_value)) {
            this.arrFormControl[field.identifier].setValue(new Date(field.default_value));
        }

        if (field.mandatory && !this.adminMode) {
            valArr.push(Validators.required);
        }

        this.arrFormControl[field.identifier].setValidators(valArr);

        if (field.identifier === 'destination') {
            const valArrDest: ValidatorFn[] = [];
            if (field.mandatory) {
                valArrDest.push(Validators.required);
                valArrDest.push(this.requireDestValidator({ 'isDest': '' }));
            } else {
                valArrDest.push(this.requireDestValidatorOrEmpty({ 'isDest': '' }));
            }

            this.arrFormControl['diffusionList'] = new UntypedFormControl({ value: null, disabled: false });

            this.arrFormControl['diffusionList'].setValidators(valArrDest);

            this.arrFormControl['diffusionList'].setValue([]);

        }
    }

    requireDestValidator(error: ValidationErrors): ValidatorFn {
        return (control: AbstractControl): { [key: string]: any } => {
            if (!control.value) {
                return null;
            }
            return control.value.filter((item: any) => item.mode === 'dest').length > 0 ? null : error;
        };
    }

    requireDestValidatorOrEmpty(error: ValidationErrors): ValidatorFn {
        return (control: AbstractControl): { [key: string]: any } => {
            if (!control.value) {
                return null;
            }
            return control.value.filter((item: any) => item.mode === 'dest').length > 0 || this.functions.empty(this.arrFormControl['destination'].value) ? null : error;
        };
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

    isValidForm() {
        if (!this.indexingFormGroup.valid) {
            Object.keys(this.indexingFormGroup.controls).forEach(key => {

                const controlErrors: ValidationErrors = this.indexingFormGroup.get(key).errors;
                if (controlErrors != null) {
                    this.indexingFormGroup.controls[key].markAsTouched();
                }
            });
        }
        return this.indexingFormGroup.valid;
    }

    isEmptyField(field: any) {
        if (this.arrFormControl[field.identifier].value === null) {
            return true;
        } else if (Array.isArray(this.arrFormControl[field.identifier].value)) {
            if (this.arrFormControl[field.identifier].value.length > 0) {
                return false;
            } else {
                return true;
            }
        } else if (String(this.arrFormControl[field.identifier].value) !== '') {
            return false;
        } else {
            return true;
        }
    }

    getMinDate(id: string) {
        if (this.arrFormControl[id] !== undefined) {
            return this.arrFormControl[id].value;
        } else if (id === '_TODAY') {
            return new Date();
        } else {
            return '';
        }
    }

    getMaxDate(id: string) {
        if (this.arrFormControl[id] !== undefined) {
            return this.arrFormControl[id].value;
        } else if (id === '_TODAY') {
            return new Date();
        } else {
            return '';
        }
    }

    toggleTodayDate(field: any) {
        field.today = !field.today;
        if (field.today) {
            this.arrFormControl[field.identifier].disable();
            this.arrFormControl[field.identifier].setValue(new Date());
        } else {
            this.arrFormControl[field.identifier].setValue('');
            this.arrFormControl[field.identifier].enable();
        }
    }

    toggleMailTracking() {
        this.arrFormControl['mail­tracking'].setValue(!this.arrFormControl['mail­tracking'].value);
    }

    changeCategory(categoryId: string) {
        this.currentCategory = categoryId;
        this.changeRegisteredMailItems(categoryId);
    }

    changeDestination(entityIds: number[], allowedEntities: number[]) {

        if (entityIds.indexOf(this.arrFormControl['destination'].value) === -1) {
            this.arrFormControl['destination'].setValue(entityIds[0]);
        }
    }

    launchEvent(value: any, field: any) {
        if (field.event !== undefined && field.identifier === 'priority' && value === null) {
            this[field.event](field, value);
        } else if (field.event !== undefined && value !== null && !this.adminMode) {
            this[field.event](field, value);
        }
    }

    calcLimitDate(field: any, value: any) {
        let limitDate: any = null;
        const objToSend: any = {
            doctype: value,
            priority: this.arrFormControl['priority']?.value
        };
        if (this.functions.empty(this.arrFormControl['priority']?.value)) {
            delete objToSend.priority;
        }
        if (!this.adminMode && this.arrFormControl['processLimitDate'] !== undefined) {
            this.http.get('../rest/indexing/processLimitDate', { params: objToSend }).pipe(
                tap((data: any) => {
                    limitDate = data.processLimitDate !== null ? new Date(data.processLimitDate) : '';
                    this.arrFormControl['processLimitDate'].setValue(limitDate);
                }),
                filter((data) => this.arrFormControl['priority'] !== undefined && data.processLimitDate !== null),
                exhaustMap(() => this.http.get('../rest/indexing/priority', { params: { 'processLimitDate': limitDate.toDateString() } })),
                tap((data: any) => {
                    if (!this.functions.empty(this.arrFormControl['priority'])) {
                        this.arrFormControl['priority'].setValue(data.priority);
                    }
                    this.setPriorityColor(null, data.priority);
                }),
                catchError((err: any) => {
                    this.notify.handleErrors(err);
                    return of(false);
                })
            ).subscribe();
        }
    }

    calcLimitDateByPriority(field: any, value: any) {
        if (this.functions.empty(value) && !this.functions.empty(this.arrFormControl['processLimitDate'])) {
            this.arrFormControl['processLimitDate'].setValue(null);
            return;
        }
        let limitDate: any = null;
        const objToSend: any = {
            priority: value,
            doctype: this.arrFormControl['doctype']?.value
        };
        if (!this.functions.empty(objToSend.priority) && this.functions.empty(this.arrFormControl['doctype']?.value)) {
            delete objToSend.doctype;
        } else if (this.functions.empty(objToSend.priority) && !this.functions.empty(this.arrFormControl['doctype']?.value)) {
            delete objToSend.priority;
        }
        if (!this.adminMode && this.arrFormControl['processLimitDate'] !== undefined) {
            this.http.get('../rest/indexing/processLimitDate', { params: objToSend }).pipe(
                tap((data: any) => {
                    limitDate = data.processLimitDate !== null ? new Date(data.processLimitDate) : '';
                    this.arrFormControl['processLimitDate'].setValue(limitDate);
                    this.setPriorityColor(field, value);
                }),
                catchError((err: any) => {
                    this.notify.handleErrors(err);
                    return of(false);
                })
            ).subscribe();
        } else {
            this.setPriorityColor(field, value);
        }
    }

    setPriorityColor(field: any, value: any) {
        if (field !== null) {
            this.currentPriorityColor = field.values.filter((fieldVal: any) => fieldVal.id === value).map((fieldVal: any) => fieldVal.color)[0];
        } else {
            this.fieldCategories.forEach(element => {
                if (this['indexingModels_' + element].filter((fieldItem: any) => fieldItem.identifier === 'priority').length > 0) {
                    this.currentPriorityColor = this['indexingModels_' + element].filter((fieldItem: any) => fieldItem.identifier === 'priority')[0].values.filter((fieldVal: any) => fieldVal.id === value).map((fieldVal: any) => fieldVal.color)[0];
                }
            });
        }
    }

    setPriorityColorByLimitDate(field: any, value: any) {
        const limitDate = new Date(value.value);
        this.http.get('../rest/indexing/priority', { params: { 'processLimitDate': limitDate.toDateString() } }).pipe(
            tap((data: any) => {
                if (!this.functions.empty(this.arrFormControl['priority'])) {
                    this.arrFormControl['priority'].setValue(data.priority);
                }
                this.setPriorityColor(null, data.priority);
            }),
            catchError((err: any) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();

    }

    loadDiffusionList(field: any, value: any) {
        if (!this.functions.empty(this.appDiffusionsList)) {
            this.appDiffusionsList.loadListModel(value);
        }
    }

    getCheckboxListLabel(selectedItemId: any, items: any) {
        return items.filter((item: any) => item.id === selectedItemId)[0].label;
    }

    setAllowedValues(field: any, afterSaveEvent: boolean = false) {
        if (!this.functions.empty(field.allowedValues)) {
            field.values.filter((val: any) => !val.isTitle).forEach((item: any) => {
                item.disabled = field.allowedValues.indexOf(item.id) === -1;
            });
        }
        if (!this.adminMode && field.identifier === 'doctype') {
            this.checkDisabledValues(field, afterSaveEvent);
        }
    }

    checkDisabledValues(field: any, afterSaveEvent: boolean = false) {
        if (!this.functions.empty(this.resId) && !afterSaveEvent) {
            this.http.get(`../rest/resources/${this.resId}`).pipe(
                tap ((data: any) => {
                    if (!this.functions.empty(data['doctype']) && field.allowedValues?.indexOf(data['doctype']) === -1) {
                        field.values.find((item: any) => item.id === data['doctype']).disabled = false;
                    }
                }),
                finalize(() => {
                    this.formatData(field);
                }),
                catchError((err: any) => {
                    this.notify.handleSoftErrors(err);
                    return of(false);
                })
            ).subscribe();
        } else {
            this.formatData(field);
        }
    }

    formatData(field: any) {
        let disabledItems: number[] = [];
        // CHECK SECOND LEVEL
        disabledItems = field.values.filter((element: any) => element.disabled && !element.isTitle).map((item: any) => item.id);
        field.values = field.values.filter((element: any) => disabledItems.indexOf(element.id) === -1 || (element.firstLevelId === undefined && element.secondLevelId === undefined));

        // CHECK FIRST LEVEL
        field.values.filter((element: any) => element.firstLevelId !== undefined).forEach((item: any) => {
            if (field.values.filter((element: any) => element.secondLevelId !== undefined && element.secondLevelId === item.id).length === 0) {
                disabledItems.push(item.id);
            }
        });
        field.values = field.values.filter((element: any) => disabledItems.indexOf(element.id) === -1 || (element.firstLevelId === undefined && element.secondLevelId === undefined));
    }


    openValuesSelector(field: any) {
        const dialogRef = this.dialog.open(IndexingModelValuesSelectorComponent, {
            panelClass: 'maarch-modal',
            disableClose: true,
            data: {...field, allDoctypes: this.allDoctypes}
        });
        dialogRef.afterClosed().pipe(
            filter((data: any) => !this.functions.empty(data)),
            tap((result: any) => {
                this.allDoctypes = result.allDoctypes;
                field.values = result.values;
                field.allowedValues = result.values.filter((item: any) => !item.isTitle && !item.disabled).map((el: any) => el.id);
                // WORK AROUND UPDATING DATA
                field.type = null;
                setTimeout(() => {
                    field.type = 'select';
                }, 0);
                if (field.allowedValues.indexOf(this.arrFormControl[field.identifier].value) === -1) {
                    this.arrFormControl[field.identifier].reset();
                }
            }),
            catchError((err: any) => {
                this.notify.handleSoftErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    /**
     * [Registered mail module]
     */
    getIssuingSites(field: any, value: any) {
        this.fieldCategories.forEach(element => {
            this['indexingModels_' + element].forEach((fieldItem: any) => {
                if (fieldItem.identifier === 'registeredMail_warranty') {
                    fieldItem.values[2].disabled = value === 'RW';
                }
            });
            if (value === 'RW' && this.arrFormControl['registeredMail_warranty'].value === 'R3') {
                this.arrFormControl['registeredMail_warranty'].setValue('R1');
            }
        });
        if (!this.functions.empty(this.appIssuingSiteInput)) {
            this.appIssuingSiteInput.registedMailType = value;
        }
    }

    /**
     * [Registered mail module]
     */
    transferRegisteredMailInput(event: CdkDragDrop<string[]>) {
        const regex = /registeredMail_[.]*/g;

        this.transferInput(event);
        if (event.item.data.identifier !== 'registeredMail_type') {
            const obj = event.previousContainer.data.map((item: any, indexItem: number) => ({ index: indexItem, identifier: item.identifier })).filter((item: any) => item.identifier === 'registeredMail_type')[0];

            this.initValidator(event.previousContainer.data[obj.index]);

            event.previousContainer.data[obj.index]['unit'] = event.container.id.split('_')[1];
            event.container.data.splice(event.currentIndex, 0, event.previousContainer.data[obj.index]);
            event.previousContainer.data.splice(obj.index, 1);
        }

        event.previousContainer.data.forEach((item: any, indexData: number) => {
            if (item.identifier.match(regex) !== null) {
                this.initValidator(item);
                item.unit = event.container.id.split('_')[1];
                event.container.data.splice(event.currentIndex, 0, item);
                event.previousContainer.data.splice(indexData, 1);
            }
        });
        this.initElemForm();
    }

    /**
     * [Registered mail module]
     */
    changeRegisteredMailItems(categoryId: string) {
        if (categoryId !== 'registeredMail') {
            this.fieldCategories.forEach(category => {
                this.availableFields = this.availableFields.concat(this['indexingModels_' + category].filter((item: any) => item.identifier.indexOf('registeredMail_') > -1));
                this['indexingModels_' + category] = this['indexingModels_' + category].filter((item: any) => item.identifier.indexOf('registeredMail_') === -1);
            });
        } else {
            this['indexingModels_mail'] = this['indexingModels_mail'].concat(this.availableFields.filter((field: any) => field.identifier.indexOf('registeredMail_') > -1 || field.identifier === 'departureDate'));
            this['indexingModels_mail'].forEach((item: any) => {
                if (item.identifier.indexOf('registeredMail_') > -1 || item.identifier === 'departureDate') {
                    if (this.functions.empty(item.unit)) {
                        item.unit = 'mail';
                    }
                    if (item.identifier !== 'registeredMail_number') {
                        item.mandatory = true;
                    }

                    this.initValidator(item);
                }
            });
            this.availableFields = this.availableFields.filter((item: any) => item.identifier.indexOf('registeredMail_') === -1 && item.identifier !== 'departureDate');
        }
    }

    selectedContact(contact: any, identifier: string, removeEvent: boolean = false) {
        if (this.getCategory() === 'incoming' && identifier === 'senders' && (this.arrFormControl['senders'].value.length === 1 || removeEvent) && this.suggestLinksNdaysAgo > 0) {
            const documentDate: Date = this.functions.empty(this.creationDateClone) ? new Date() : new Date(this.creationDateClone);
            const resourceNotBefore = new Date(documentDate.setDate(documentDate.getDate() - this.suggestLinksNdaysAgo)).toISOString().split('T')[0];
            const objToSend: any = {
                creationDate : {
                    values : {
                        start: resourceNotBefore,
                        end: null,
                    }
                },
                senders: {
                    values: [contact]
                }
            };
            this.http.post('../rest/search?limit=10&offset=0&order=asc&orderBy=creationDate', objToSend).pipe(
                tap((data: any) => {
                    if (!this.functions.empty(data.resources)) {
                        if (data.allResources.length === 1 && data.allResources.indexOf(this.resId) > -1) {
                            this.hasLinkedRes = false;
                            this.linkedResources = [];
                            this.resourceToLinkEvent.emit([]);
                        } else {
                            if (!this.functions.empty(this.resId)) {
                                data.resources = data.resources.filter((item: any) => item.resId !== this.resId);
                            }
                            data.resources = data.resources.map((element: any) => ({
                                ...element,
                                checked: false
                            }));
                            this.linkedResources = data;
                            this.selectedContactClone = JSON.parse(JSON.stringify(contact));
                            this.msgToDisplay = this.translate.instant('lang.hasLinkedRes', {'nbDays': this.suggestLinksNdaysAgo});
                            this.hasLinkedRes = true;
                        }
                    } else {
                        this.hasLinkedRes = false;
                        this.linkedResources = [];
                        this.resourceToLinkEvent.emit([]);
                    }
                }),
                catchError((err: any) => {
                    this.notify.error(err);
                    return of(false);
                })
            ).subscribe();
        } else {
            this.hasLinkedRes = false;
            this.linkedResources = [];
            this.resourceToLinkEvent.emit([]);
        }
    }

    getParameter() {
        return new Promise((resolve) => {
            this.http.get('../rest/parameters/suggest_links_n_days_ago').pipe(
                tap((data: any) => {
                    this.suggestLinksNdaysAgo = data.parameter.param_value_int;
                    resolve(true);
                }),
                catchError((err: any) => {
                    this.notify.handleSoftErrors(err);
                    return of(false);
                })
            ).subscribe();
        });
    }

    openSearchResourceModal() {
        const dialogRef = this.dialog.open(LinkResourceModalComponent,
            {
                panelClass: 'maarch-full-height-modal', minWidth: '80%',
                data: {
                    resId: this.resId,
                    linkedRes: this.linkedResources,
                    fromContact: true,
                    selectedContact: this.selectedContactClone
                }
            });
        dialogRef.afterClosed().pipe(
            tap((data: any) => {
                if (Array.isArray(data)) {
                    this.linkedResources['resources'].forEach((element: any) => {
                        element.checked = data.indexOf(element.resId) > -1 ? true : false;
                    });
                    const resToLink: any[] = this.linkedResources['resources'].filter((item: any) => item.checked);
                    if (resToLink.length > 0) {
                        this.resourceToLinkEvent.emit(resToLink.map((res: any) => res.resId));
                    }
                } else if (data === 'success') {
                    this.reloadBadge.emit();
                    this.notify.success(this.translate.instant('lang.resourcesLinked'));
                }
            }),
            catchError((err: any) => {
                this.notify.handleSoftErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    checkRemovedItem(value: any) {
        if (typeof value === 'boolean') {
            this.hasLinkedRes = false;
            this.resourceToLinkEvent.emit([]);
        }

        const senders: any[] = this.arrFormControl['senders'].value.filter((item: any) => item.id !== value);
        if (senders.length === 1) {
            this.selectedContact(senders[0], 'senders', true);
        } else {
            this.hasLinkedRes = false;
            this.resourceToLinkEvent.emit([]);
        }
    }
}
