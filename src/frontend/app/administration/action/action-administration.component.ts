import { Component, OnInit, ViewChild } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Router, ActivatedRoute } from '@angular/router';
import { TranslateService } from '@ngx-translate/core';
import { NotificationService } from '@service/notification/notification.service';
import { MatSidenav } from '@angular/material/sidenav';
import { HeaderService } from '@service/header.service';
import { AppService } from '@service/app.service';
import { tap, catchError } from 'rxjs/operators';
import { FunctionsService } from '@service/functions.service';
import { UntypedFormControl } from '@angular/forms';
import { ActionPagesService } from '@service/actionPages.service';
import { of } from 'rxjs';


@Component({
    templateUrl: 'action-administration.component.html',
    styleUrls: ['action-administration.component.scss']
})
export class ActionAdministrationComponent implements OnInit {

    @ViewChild('snav2', { static: true }) public sidenavRight: MatSidenav;

    today: Date = new Date();
    creationMode: boolean;
    action: any = {};
    statuses: any[] = [];
    actionPages: any[] = [];
    categoriesList: any[] = [];
    keywordsList: any[] = [];

    group: any[] = [];

    loading: boolean = false;
    availableCustomFields: Array<any> = [];
    availableCustomFieldsClone: Array<any> = [];
    customFieldsFormControl = new UntypedFormControl({ value: '', disabled: false });
    selectedFieldsValue: Array<any> = [];
    selectedFieldsId: Array<any> = [];

    availableFillCustomFields: Array<any> = [];
    fillcustomFieldsFormControl = new UntypedFormControl({ value: '', disabled: false });
    selectedFieldItems = {selectedFieldsId: [], selectedFieldsValue: []};

    selectedValue: any;
    arMode: any;
    canAddCopies: boolean;
    successStatus: any;
    errorStatus: any;
    intermediateStatus: any;


    selectActionPageId = new UntypedFormControl();
    selectStatusId = new UntypedFormControl();
    selectSuccessStatusId = new UntypedFormControl();
    selectErrorStatusId = new UntypedFormControl();
    selectIntermidiateStatusId = new UntypedFormControl();

    intermediateStatusActions = ['sendToRecordManagement', 'sendToExternalSignatureBook', 'send_to_visa', 'visa_workflow', 'send_shipping'];

    mailevaStatus: any[] = [
        {
            id: 'ON_STATUS_ACCEPTED',
            label: this.translate.instant('lang.ON_STATUS_ACCEPTED'),
            actionStatus: null,
            disabled: false
        },
        {
            id: 'ON_STATUS_REJECTED',
            label: this.translate.instant('lang.ON_STATUS_REJECTED'),
            actionStatus: null,
            disabled: false
        },
        {
            id: 'ON_STATUS_PROCESSED',
            label: this.translate.instant('lang.ON_STATUS_PROCESSED'),
            actionStatus: null,
            disabled: false
        },
        {
            id: 'ON_STATUS_ARCHIVED',
            label: this.translate.instant('lang.ON_STATUS_ARCHIVED'),
            actionStatus: null,
            disabled: false
        },
    ];

    intermediateSelectedStatus: any[] = [];
    finalSelectedStatus: any[] = [];
    errorSelectedStatus: any[] = [];

    intermediateStatusParams: any = {};
    finalStatusParams: any = {};
    errorStatusParams: any = {};

    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        private route: ActivatedRoute,
        private router: Router,
        private notify: NotificationService,
        private headerService: HeaderService,
        public appService: AppService,
        public functions: FunctionsService,
        public actionPagesService: ActionPagesService) { }

    ngOnInit(): void {
        this.loading = true;

        this.setIntermediateStatus();
        this.setFinalStatus();
        this.setErrorStatus();

        this.route.params.subscribe(params => {

            if (typeof params['id'] === 'undefined') {

                this.creationMode = true;

                this.http.get('../rest/initAction')
                    .subscribe(async (data: any) => {
                        this.action = data.action;
                        this.action.actionPageId = 'confirm_status';
                        this.selectActionPageId.setValue('confirm_status');
                        this.selectStatusId.setValue(this.action.id_status);
                        this.categoriesList = data.categoriesList;
                        this.statuses = data.statuses.map((status: any) => ({
                            id: status.id,
                            label: status.label_status
                        }));

                        this.actionPages = this.actionPagesService.getAllActionPages();
                        this.actionPages.map(action => action.category).filter((cat, index, self) => self.indexOf(cat) === index).forEach(element => {
                            this.group.push({
                                id : element,
                                label : this.translate.instant('lang.' + element)
                            });
                        });

                        this.keywordsList = data.keywordsList;
                        this.headerService.setHeader(this.translate.instant('lang.actionCreation'));
                        await this.getCustomFields();
                        this.loading = false;
                    });
            } else {
                this.creationMode = false;

                this.intermediateSelectedStatus = this.mailevaStatus.filter((item: any) => item.actionStatus === 'intermediateStatus').map((el: any) => el.id);
                this.finalSelectedStatus = this.mailevaStatus.filter((item: any) => item.actionStatus === 'finalStatus').map((el: any) => el.id);
                this.errorSelectedStatus = this.mailevaStatus.filter((item: any) => item.actionStatus === 'errorStatus').map((el: any) => el.id);

                this.http.get('../rest/actions/' + params['id'])
                    .subscribe(async (data: any) => {
                        this.action = data.action;
                        const currentAction = this.actionPagesService.getActionPageByComponent(this.action.component);
                        this.action.actionPageId = currentAction?.id;
                        this.selectActionPageId.setValue(this.action.actionPageId);
                        this.selectStatusId.setValue(this.action.id_status);
                        this.categoriesList = data.categoriesList;
                        this.statuses = data.statuses.map((status: any) => ({
                            id: status.id,
                            label: status.label_status
                        }));
                        this.actionPages = this.actionPagesService.getAllActionPages();
                        this.actionPages.map(action => action.category).filter((cat, index, self) => self.indexOf(cat) === index).forEach(element => {
                            this.group.push({
                                id : element,
                                label : this.translate.instant('lang.' + element)
                            });
                        });
                        this.keywordsList = data.keywordsList;
                        this.headerService.setHeader(this.translate.instant('lang.actionCreation'), data.action.label_action);
                        await this.getCustomFields();
                        this.loading = false;
                        if (this.action.actionPageId === 'confirm_status') {
                            this.customFieldsFormControl = new UntypedFormControl({ value: this.action.parameters.fillRequiredFields, disabled: false });
                            this.selectedFieldItems.selectedFieldsId = [];
                            if (this.action.parameters.fillRequiredFields) {
                                this.selectedFieldItems.selectedFieldsId = this.action.parameters.fillRequiredFields;
                            }
                            this.selectedFieldItems.selectedFieldsId.forEach((element: any) => {
                                this.availableFillCustomFields.forEach((availableElement: any) => {
                                    if (availableElement.id === element.id) {
                                        availableElement.selectedValues = this.functions.empty(element.value) ? null : element.value;

                                        if (availableElement.type === 'date' && !this.functions.empty(element.value)) {
                                            if (element.value === '_TODAY') {
                                                availableElement.today = true;
                                                availableElement.selectedValues = new Date();
                                            } else if (this.functions.formatDateObjectToDateString(new Date()) === this.functions.formatDateObjectToDateString(new Date(element.value))) {
                                                availableElement.today = true;
                                            } else{
                                                availableElement.today = false;
                                            }
                                        } else if (['contact', 'banAutocomplete'].includes(availableElement.type)) {
                                            availableElement.formControl = new UntypedFormControl({ value: element.value, disabled: false });
                                        }

                                        this.selectedFieldItems.selectedFieldsValue.push(availableElement);
                                        this.availableFillCustomFields = this.availableFillCustomFields.filter((item: any) => item.id !== availableElement.id);
                                    }
                                });
                            });
                        } else if (this.action.actionPageId === 'close_mail') {
                            this.customFieldsFormControl = new UntypedFormControl({ value: this.action.parameters.requiredFields, disabled: false });
                            this.selectedFieldsId = [];
                            if (this.action.parameters.requiredFields) {
                                this.selectedFieldsId = this.action.parameters.requiredFields;
                            }
                            this.selectedFieldsId.forEach((element: any) => {
                                this.availableCustomFields.forEach((availableElement: any) => {
                                    if (availableElement.id === element) {
                                        this.selectedFieldsValue.push(availableElement.label);
                                        this.availableCustomFields = this.availableCustomFields.filter((item: any) => item.id !== availableElement.id);
                                    }
                                });
                            });
                        } else if (this.action.actionPageId === 'create_acknowledgement_receipt') {
                            this.arMode = this.action.parameters.mode;
                            this.canAddCopies = this.action.parameters.canAddCopies;
                        }  else if (this.action.actionPageId === 'send_shipping') {
                            if (!this.functions.empty(this.action.parameters.intermediateStatus)) {
                                this.selectIntermidiateStatusId.setValue(this.action.parameters.intermediateStatus.actionStatus);
                                this.selectSuccessStatusId.setValue(this.action.parameters.finalStatus.actionStatus);
                                this.selectErrorStatusId.setValue(this.action.parameters.errorStatus.actionStatus);
                                this.getSelectedStatus(this.action.parameters.intermediateStatus.mailevaStatus, 'intermediateStatus');
                                this.getSelectedStatus(this.action.parameters.finalStatus.mailevaStatus, 'finalStatus');
                                this.getSelectedStatus(this.action.parameters.errorStatus.mailevaStatus, 'errorStatus');
                            }
                        } else if (this.intermediateStatusActions.indexOf(this.action.actionPageId) !== -1) {
                            this.selectSuccessStatusId.setValue(this.action.parameters.successStatus);
                            this.selectErrorStatusId.setValue(this.action.parameters.errorStatus);
                            this.errorStatus = this.action.parameters.errorStatus;
                            this.successStatus = this.action.parameters.successStatus;
                        }
                    });
            }
        });
    }

    getCustomFields() {
        this.action.actionPageId = this.selectActionPageId.value;
        this.action.actionPageGroup = this.actionPages.filter(action => action.id === this.action.actionPageId)[0]?.category;

        if (this.action.actionPageGroup === 'registeredMail') {
            this.action.actionCategories = ['registeredMail'];
        }

        if (this.intermediateStatusActions.indexOf(this.action.actionPageId) !== -1) {
            this.selectSuccessStatusId.setValue('_NOSTATUS_');
            this.selectErrorStatusId.setValue('_NOSTATUS_');
            this.selectIntermidiateStatusId.setValue('_NOSTATUS_');
        }

        return new Promise((resolve) => {
            if (['confirm_status', 'close_mail'].includes(this.action.actionPageId)) {
                this.http.get('../rest/customFields').pipe(
                    tap((data: any) => {
                        if (this.action.actionPageId === 'confirm_status' && this.functions.empty(this.availableFillCustomFields)) {
                            this.availableFillCustomFields = data.customFields.map((info: any) => {
                                info.id = 'indexingCustomField_' + info.id;
                                return info;
                            });
                        } else if (this.action.actionPageId === 'close_mail' && this.functions.empty(this.availableCustomFields)) {
                            this.availableCustomFields = data.customFields.map((info: any) => {
                                info.id = 'indexingCustomField_' + info.id;
                                return info;
                            });
                            this.availableCustomFieldsClone = JSON.parse(JSON.stringify(this.availableCustomFields));
                        }
                        return resolve(true);
                    }),
                    catchError((err: any) => {
                        this.notify.handleSoftErrors(err);
                        return of(false);
                    })
                ).subscribe();
            } else {
                resolve(true);
            }
        });

    }

    /**
     * @description Add selected custom field to selectedFieldsValue and selectedFieldsId list
     */
    getSelectedFields() {
        this.availableCustomFields.forEach((element: any) => {
            if (element.id === this.customFieldsFormControl.value) {
                this.selectedValue = element;
            }
        });
        if (this.selectedFieldsId.indexOf(this.customFieldsFormControl.value) < 0) {
            this.selectedFieldsValue.push(this.selectedValue.label);
            this.selectedFieldsId.push(this.customFieldsFormControl.value);
            this.availableCustomFields = this.availableCustomFields.filter((item: any) => item.id !== this.selectedValue.id);
        }
        this.customFieldsFormControl.reset();
    }

    /**
     * @description Add selected custom field to selectedFieldItems.selectedFieldsValue and selectedFieldItems.selectedFieldsId list
     */
    getSelectedFieldsToFill() {
        this.availableFillCustomFields.forEach((element: any) => {
            if (!this.functions.empty(element.id) && element.id === this.fillcustomFieldsFormControl.value) {
                this.selectedValue = element;
            }
        });

        const checkField = this.selectedFieldItems.selectedFieldsId.find((field: any) => field.id === this.fillcustomFieldsFormControl.value);
        if (!this.functions.empty(this.selectedValue) && this.functions.empty(checkField) &&
            this.selectedFieldItems.selectedFieldsId.indexOf(this.fillcustomFieldsFormControl.value) < 0 ) {

            this.selectedValue.selectedValues = null;
            if (this.selectedValue.type === 'date') {
                this.selectedValue.today = false;
            } else if (['contact', 'banAutocomplete'].includes(this.selectedValue.type)) {
                this.selectedValue.formControl = new UntypedFormControl({ value: this.selectedValue.selectedValues, disabled: false });
            }
            this.selectedFieldItems.selectedFieldsValue.push(this.selectedValue);
            this.selectedFieldItems.selectedFieldsId.push(this.fillcustomFieldsFormControl.value);
            this.availableFillCustomFields = this.availableFillCustomFields.filter((item: any) => item.id !== this.selectedValue.id);
        }
        this.fillcustomFieldsFormControl.reset();
    }

    /**
     * @description Remove custom fields
     * @param index custom fields position in selectedFieldsValue
     */
    removeSelectedFields(index: number) {
        const removedItem = this.availableCustomFieldsClone.find((item: any) => item.id === this.selectedFieldsId[index]);
        this.selectedFieldsValue.splice(index, 1);
        this.selectedFieldsId.splice(index, 1);
        this.availableCustomFields.push(removedItem);
        this.availableCustomFields.sort((a, b) => a.label.localeCompare(b.label));
    }

    /**
     * @description Remove custom fields
     * @param index custom fields position in selectedFieldItems.selectedFieldsValue
     */
    removeSelectedFieldsToFill(index: number) {
        const fieldItem = this.selectedFieldItems.selectedFieldsValue[index];
        this.selectedFieldItems.selectedFieldsValue.splice(index, 1);
        this.selectedFieldItems.selectedFieldsId.splice(index, 1);
        this.availableFillCustomFields.push(fieldItem);
        this.availableFillCustomFields.sort((a, b) => a.label.localeCompare(b.label));
    }

    /**
     * @description Set date for today or not
     * @param fieldItemValue Date field object
     */
    toggleTodayDate(fieldItemValue: any) {
        fieldItemValue.today = !fieldItemValue.today;
        if (fieldItemValue.today) {
            fieldItemValue.selectedValues = new Date();
        } else {
            fieldItemValue.selectedValues = null;
        }
    }

    /**
     * @description Change date event
     * @param changedDate
     * @param fieldItemValue
     */
    onDateChange(changedDate: any, fieldItemValue: any) {
        const currentDate = fieldItemValue.selectedValues;
        fieldItemValue.today = false;
        fieldItemValue.selectedValues = changedDate.value;
        if (this.functions.formatDateObjectToDateString(new Date(changedDate.value)) === this.functions.formatDateObjectToDateString(new Date(currentDate))) {
            fieldItemValue.today = true;
        }
    }

    /**
     * @description Rest selectedValues from selectedFieldsValue
     * @param fieldItemValue Field object
     * @param position Position from selectedFieldsValue
     */
    resetFieldItemValue(fieldItemValue: any, position: any) {
        const field = this.selectedFieldItems.selectedFieldsValue[position];
        field.selectedValues = null;
        if (fieldItemValue.type === 'date' && field.today !== undefined) {
            field.today = false;
        }
    }

    /**
     * @description Get label from an array
     * @param selectedLabel selected label
     * @param fieldItemValues List of values
     * @returns Labels
     */
    getCheckboxListLabel(selectedLabel: any, fieldItemValues: any) {
        return fieldItemValues.filter((item: any) => item.label === selectedLabel)[0].label;
    }

    /**
     * @description Add selection contact (user or entity)
     * @param contact User or Entity object
     * @param fieldItemValue Contact field object
     */
    selectedContact(contact: any, fieldItemValue: any) {
        const arrInfo = [];
        arrInfo.push(contact.firstname);
        arrInfo.push(contact.lastname);

        if (!this.functions.empty(fieldItemValue.selectedValues)) {
            const contactExist = fieldItemValue.selectedValues.find((elem: any) => elem.id === contact.id && elem.type === contact.type);
            if (this.functions.empty(contactExist)) {
                fieldItemValue.selectedValues.push({id: contact.id, type: contact.type, label: arrInfo.filter(info => !this.functions.empty(info)).join(' ')});
            }
        } else {
            fieldItemValue.selectedValues = [{id: contact.id, type: contact.type, label: arrInfo.filter(info => !this.functions.empty(info)).join(' ')}];
        }
    }

    /**
     * @description Remove contact (user or entity)
     * @param concatId Number or false if remove all contacts
     * @param fieldItemValue Contact field object
     */
    removeContactEvent(concatId: any, fieldItemValue: any) {
        if (!this.functions.empty(concatId) && Number.isInteger(concatId)) {
            fieldItemValue.selectedValues = fieldItemValue.selectedValues.filter((element: any) => element.id !== concatId);
        } else {
            fieldItemValue.selectedValues = [];
        }
    }

    /**
     * @description Add selected Ban address
     * @param addressBan Selected address
     * @param fieldItemValue Address Auto Complete field
     */
    selectedAddressBan(addressBan: any, fieldItemValue: any) {
        fieldItemValue.selectedValues = [addressBan];
    }

    /**
     * @description Remove Ban address
     * @param addressBanId Number
     * @param fieldItemValue Address Ban field object
     */
    removeAddressBanEvent(addressBanId: any, fieldItemValue: any) {
        if (!this.functions.empty(addressBanId) && Number.isInteger(addressBanId)) {
            fieldItemValue.selectedValues = fieldItemValue.selectedValues.filter((element: any) => element.id !== addressBanId);
        } else {
            fieldItemValue.selectedValues = [];
        }
    }

    /**
     * @description check if custom field values aren't empty
     */
    checkCurrentFieldValue() {
        if (!this.functions.empty(this.selectedFieldItems.selectedFieldsValue)) {
            const fieldsAreEmpty = this.selectedFieldItems.selectedFieldsValue.filter(item => this.functions.empty(item.selectedValues));
            return this.functions.empty(fieldsAreEmpty);
        }
        return true;
    }

    /**
     * @description Map action object before API create/update call.
     */
    onSubmit() {
        if (this.action.actionPageId === 'confirm_status') {
            const fillRequiredFields = [];
            this.selectedFieldItems.selectedFieldsValue.forEach((item: any) => {
                if (!this.functions.empty(item.selectedValues)) {
                    item.selectedValues = (item.type === 'date' && item.today !== undefined && item.today) ? '_TODAY' : item.selectedValues;
                    fillRequiredFields.push({id: item.id, value: item.selectedValues});
                }
            });
            this.action.parameters = { fillRequiredFields: fillRequiredFields };
        } else if (this.action.actionPageId === 'close_mail') {
            this.action.parameters = { requiredFields: this.selectedFieldsId };
        } else if (this.action.actionPageId === 'create_acknowledgement_receipt') {
            this.action.parameters = { mode: this.arMode, canAddCopies : this.canAddCopies };
        } else if (this.action.actionPageId === 'send_shipping') {
            const intermediateStatus = {
                actionStatus: this.selectIntermidiateStatusId.value,
                mailevaStatus: this.intermediateSelectedStatus
            };
            const finalStatus = {
                actionStatus: this.selectSuccessStatusId.value,
                mailevaStatus: this.finalSelectedStatus
            };
            const errorStatus = {
                actionStatus: this.selectErrorStatusId.value,
                mailevaStatus: this.errorSelectedStatus
            };
            this.action.parameters = {
                intermediateStatus: intermediateStatus,
                finalStatus: finalStatus,
                errorStatus: errorStatus
            };
        } else if (this.intermediateStatusActions.indexOf(this.action.actionPageId) !== -1) {
            this.action.parameters = { successStatus: this.successStatus, errorStatus: this.errorStatus };
        }
        this.action.action_page = this.action.actionPageId;
        this.action.component = this.actionPagesService.getAllActionPages(this.action.actionPageId).component;

        if (this.creationMode) {
            this.http.post('../rest/actions', this.action)
                .subscribe(() => {
                    this.router.navigate(['/administration/actions']);
                    this.notify.success(this.translate.instant('lang.actionAdded'));

                }, (err) => {
                    this.notify.error(err.error.errors);
                });
        } else {
            this.http.put('../rest/actions/' + this.action.id, this.action)
                .subscribe(() => {
                    this.router.navigate(['/administration/actions']);
                    this.notify.success(this.translate.instant('lang.actionUpdated'));

                }, (err) => {
                    this.notify.error(err.error.errors);
                });
        }
    }

    getSelectedStatus(mailevaStatus: any[], actionStatus: string) {
        if (actionStatus === 'intermediateStatus') {
            this.checkSelection(mailevaStatus, 'intermediateStatus');
            this.setIntermediateStatus(mailevaStatus);
            this.finalStatusParams.data.forEach((element: any) => {
                element.disabled = mailevaStatus.indexOf(element.id) > - 1 || element.disabled ? true : false;
            });
            this.errorStatusParams.data.forEach((element: any) => {
                element.disabled = mailevaStatus.indexOf(element.id) > - 1 || element.disabled ? true : false;
            });

        } else if (actionStatus === 'finalStatus') {
            this.checkSelection(mailevaStatus, 'finalStatus');
            this.setFinalStatus(mailevaStatus);
            this.intermediateStatusParams.data.forEach((element: any) => {
                element.disabled = mailevaStatus.indexOf(element.id) > - 1 || element.disabled  ? true : false;
            });
            this.errorStatusParams.data.forEach((element: any) => {
                element.disabled = mailevaStatus.indexOf(element.id) > - 1 || element.disabled  ? true : false;
            });
        } else if (actionStatus === 'errorStatus') {
            this.checkSelection(mailevaStatus, 'errorStatus');
            this.setErrorStatus(mailevaStatus);
            this.intermediateStatusParams.data.forEach((element: any) => {
                element.disabled = mailevaStatus.indexOf(element.id) > - 1 || element.disabled  ? true : false;
            });
            this.finalStatusParams.data.forEach((element: any) => {
                element.disabled = mailevaStatus.indexOf(element.id) > - 1 || element.disabled  ? true : false;
            });
        }
    }

    setIntermediateStatus(mailevaStatus: any[] = null) {
        if (mailevaStatus !== null) {
            const data: any[] = this.intermediateStatusParams.data;
            data.forEach((element: any, index: number) => {
                element.actionStatus = mailevaStatus.indexOf(element.id) > -1 ? 'intermediateStatus' : null;
            });
            this.intermediateSelectedStatus = mailevaStatus;
        } else {
            const data: any = JSON.parse(JSON.stringify(this.mailevaStatus));
            this.intermediateStatusParams = {
                id: 'intermediateStatus',
                data: data
            };
        }
    }

    setFinalStatus(mailevaStatus: any[] = null) {
        if (mailevaStatus !== null) {
            const data: any[] = this.finalStatusParams.data;
            data.forEach((element: any, index: number) => {
                element.actionStatus = mailevaStatus.indexOf(element.id) > -1 ? 'finalStatus' : null;
            });
            this.finalSelectedStatus = mailevaStatus;
        } else {
            const data: any = JSON.parse(JSON.stringify(this.mailevaStatus));
            this.finalStatusParams = {
                id: 'finalStatus',
                data: data
            };
        }
    }

    setErrorStatus(mailevaStatus: any[] = null) {
        if (mailevaStatus !== null) {
            const data: any[] = this.errorStatusParams.data;
            data.forEach((element: any, index: number) => {
                element.actionStatus = mailevaStatus.indexOf(element.id) > -1 ? 'errorStatus' : null;
            });
            this.errorSelectedStatus = mailevaStatus;
        } else {
            const data: any = JSON.parse(JSON.stringify(this.mailevaStatus));
            this.errorStatusParams = {
                id: 'errorStatus',
                data: data
            };
        }
    }

    checkSelection(mailevaStatus: any[], actionStatus: string) {
        if (actionStatus === 'intermediateStatus') {
            const array: any[] = this.intermediateStatusParams.data.filter((item: any) => item.actionStatus === 'intermediateStatus').map((el: any) => el.id);
            const deselectedItem: string = array.filter((element: any) => !mailevaStatus.includes(element)).toString();
            if (!this.functions.empty(deselectedItem)) {
                this.finalStatusParams.data.find((el: any) => el.id === deselectedItem).disabled = false;
                this.errorStatusParams.data.find((el: any) => el.id === deselectedItem).disabled = false;
            }
        } else if (actionStatus === 'finalStatus') {
            const array: any[] = this.finalStatusParams.data.filter((item: any) => item.actionStatus === 'finalStatus').map((el: any) => el.id);
            const deselectedItem: string = array.filter((element: any) => !mailevaStatus.includes(element)).toString();
            if (!this.functions.empty(deselectedItem)) {
                this.intermediateStatusParams.data.find((el: any) => el.id === deselectedItem).disabled = false;
                this.errorStatusParams.data.find((el: any) => el.id === deselectedItem).disabled = false;
            }
        } else if (actionStatus === 'errorStatus') {
            const array: any[] = this.errorStatusParams.data.filter((item: any) => item.actionStatus === 'errorStatus').map((el: any) => el.id);
            const deselectedItem: string = array.filter((element: any) => !mailevaStatus.includes(element)).toString();
            if (!this.functions.empty(deselectedItem)) {
                this.intermediateStatusParams.data.find((el: any) => el.id === deselectedItem).disabled = false;
                this.finalStatusParams.data.find((el: any) => el.id === deselectedItem).disabled = false;
            }
        }
    }
}
