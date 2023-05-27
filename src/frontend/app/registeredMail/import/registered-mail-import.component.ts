import { Component, OnInit, Inject, ViewChild } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { NotificationService } from '@service/notification/notification.service';
import { MAT_DIALOG_DATA, MatDialogRef, MatDialog } from '@angular/material/dialog';
import { TranslateService } from '@ngx-translate/core';
import { MatTableDataSource } from '@angular/material/table';
import { FunctionsService } from '@service/functions.service';
import { ConfirmComponent } from '../../../plugins/modal/confirm.component';
import { filter, exhaustMap, tap, catchError, map, finalize } from 'rxjs/operators';
import { of } from 'rxjs';
import { AlertComponent } from '../../../plugins/modal/alert.component';
import { LocalStorageService } from '@service/local-storage.service';
import { HeaderService } from '@service/header.service';
import { MatPaginator } from '@angular/material/paginator';
import { Papa } from 'ngx-papaparse';
import { IndexingFieldsService } from '@service/indexing-fields.service';

@Component({
    templateUrl: 'registered-mail-import.component.html',
    styleUrls: ['registered-mail-import.component.scss']
})
export class RegisteredMailImportComponent implements OnInit {

    @ViewChild(MatPaginator, { static: false }) paginator: MatPaginator;

    loading: boolean = false;

    registeredMailFields: string[] = ['registeredMail_issuingSite', 'registeredMail_warranty', 'registeredMail_type', 'departureDate', 'registeredMail_letter'];

    contactColumns: any[] = [
        {
            id: 'company',
            label: this.translate.instant('lang.contactsParameters_company'),
        },
        {
            id: 'civility',
            label: this.translate.instant('lang.contactsParameters_civility'),
        },
        {
            id: 'firstname',
            label: this.translate.instant('lang.contactsParameters_firstname'),
        },
        {
            id: 'lastname',
            label: this.translate.instant('lang.contactsParameters_lastname'),
        },
        {
            id: 'department',
            label: this.translate.instant('lang.contactsParameters_department'),
        },
        {
            id: 'addressAdditional1',
            label: this.translate.instant('lang.contactsParameters_addressAdditional1'),
        },
        {
            id: 'addressNumber',
            label: this.translate.instant('lang.contactsParameters_addressNumber'),
        },
        {
            id: 'addressStreet',
            label: this.translate.instant('lang.contactsParameters_addressStreet'),
        },
        {
            id: 'addressAdditional2',
            label: this.translate.instant('lang.contactsParameters_addressAdditional2'),
        },
        {
            id: 'addressPostcode',
            label: this.translate.instant('lang.contactsParameters_addressPostcode'),
        },
        {
            id: 'addressTown',
            label: this.translate.instant('lang.contactsParameters_addressTown'),
        },
        {
            id: 'registeredMail_reference',
            label: this.translate.instant('lang.registeredMailReference'),
        },
    ];

    csvColumns: string[] = [];

    currentIndexingModel: number;
    currentDoctype: number;

    indexingModels: any[] = [];

    delimiters = [';', ',', '\t'];
    currentDelimiter = ';';

    associatedColumns: any = {
        company : '0',
        civility : '1',
        firstname : '3',
        lastname : '2',
        addressAdditional1 : '5',
        addressStreet : '4',
        addressAdditional2 : '6',
        addressPostcode : '7',
        addressTown : '8',
        registeredMail_reference : '9',
    };
    dataSource = new MatTableDataSource(null);
    hasHeader: boolean = false;
    csvData: any[] = [];
    contactData: any[] = [];
    countAll: number = 0;
    countAdd: number = 0;
    countUp: number = 0;

    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        private notify: NotificationService,
        public functionsService: FunctionsService,
        private localStorage: LocalStorageService,
        private headerService: HeaderService,
        public dialog: MatDialog,
        public dialogRef: MatDialogRef<RegisteredMailImportComponent>,
        private papa: Papa,
        public indexingFields: IndexingFieldsService,
        @Inject(MAT_DIALOG_DATA) public data: any,
    ) {
    }

    async ngOnInit(): Promise<void> {
        this.registeredMailFields.forEach(element => {
            const field = this.indexingFields.getField(element);

            this.contactColumns.unshift({
                id: field.identifier,
                label: field.label,
                values: field.values,
                form: true
            });
        });

        await this.getRegisteredMailIndexingModels();
        await this.getIssuingSites();
        await this.getDefaultValues();

        // this.initCustomFields();
    }

    getRegisteredMailIndexingModels() {
        return new Promise((resolve) => {
            this.http.get('../rest/indexingModels').pipe(
                map((data: any) => {
                    data = data.indexingModels.filter((model: any) => model.category === 'registeredMail' && model.master === null).map((model: any) => ({
                        id: model.id,
                        label: model.label
                    }));
                    return data;
                }),
                tap((data: any) => {
                    if (data.length > 0) {
                        this.indexingModels = data;
                        this.currentIndexingModel = this.indexingModels[0].id;
                        resolve(true);
                    } else {
                        this.dialogRef.close();
                        this.notify.error(this.translate.instant('lang.noRegisteredMailModelAvailaible'));
                    }
                }),
                catchError((err: any) => {
                    this.notify.handleSoftErrors(err);
                    return of(false);
                })
            ).subscribe();
        });
    }

    getIssuingSites() {
        return new Promise((resolve) => {
            this.http.get('../rest/registeredMail/sites').pipe(
                tap((data) => {
                    if (data['sites'].length > 0) {
                        this.contactColumns.filter((col: any) => col.id === 'registeredMail_issuingSite')[0].values = data['sites'].map((site: any) => ({
                            id: site.id,
                            label: site.label
                        }));
                        resolve(true);
                    } else {
                        this.dialogRef.close();
                        this.notify.error(this.translate.instant('lang.noIssuingSitesAvailaible'));
                    }
                }),
                catchError((err: any) => {
                    this.notify.handleSoftErrors(err);
                    return of(false);
                })
            ).subscribe();
        });
    }

    getLabel(id: string, value: any) {
        if (id === 'registeredMail_issuingSite' && !this.functionsService.empty(value)) {
            const sites = this.contactColumns.filter((col: any) => col.id === 'registeredMail_issuingSite')[0].values;
            return sites.filter((site: any) => site.id === value)[0].label;
        } else if ([true, false].indexOf(value) > -1) {
            return this.translate.instant('lang.' + value);
        } else if (this.functionsService.isDate(value)) {
            return this.functionsService.formatDateObjectToDateString(value);
        } else {
            return value;
        }
    }

    getDefaultValues() {
        return new Promise((resolve) => {
            this.http.get(`../rest/indexingModels/${this.currentIndexingModel}`).pipe(
                tap((data: any) => {
                    this.registeredMailFields.forEach(element => {
                        if (element === 'departureDate') {
                            if (this.contactColumns.filter((field: any) => field.id === element)[0].default_value === '_TODAY') {
                                this.contactColumns.filter((field: any) => field.id === element)[0].default_value = new Date();
                            } else {
                                this.contactColumns.filter((field: any) => field.id === element)[0].default_value = !this.functionsService.empty(this.contactColumns.filter((field: any) => field.id === element)[0].default_value) ? this.functionsService.formatFrenchDateToObjectDate(data.indexingModel.fields.filter((field: any) => field.identifier === element)[0].default_value) : new Date();
                            }
                        } else {
                            this.contactColumns.filter((field: any) => field.id === element)[0].default_value = data.indexingModel.fields.filter((field: any) => field.identifier === element)[0].default_value;
                        }
                    });
                    this.currentDoctype = data.indexingModel.fields.filter((field: any) => field.identifier === 'doctype')[0].default_value;
                }),
                finalize(() => resolve(true)),
                catchError((err: any) => {
                    this.notify.handleSoftErrors(err);
                    return of(false);
                })
            ).subscribe();
        });
    }

    getcontactColumnsIds() {
        return this.contactColumns.map(col => col.id);
    }

    /* initCustomFields() {
        this.http.get(`../rest/contactsCustomFields`).pipe(
            map((data: any) => {
                data = data.customFields.map(custom => {
                    return {
                        id: `contactCustomField_${custom.id}`,
                        label: custom.label,
                        type: custom.type
                    };
                });
                return data;
            }),
            tap((customFields) => {
                this.contactColumns = this.contactColumns.concat(customFields);
            }),
            catchError((err: any) => {
                this.notify.handleSoftErrors(err);
                return of(false);
            })
        ).subscribe();
    }*/

    changeColumn(coldb: any, colCsv: any) {
        this.contactData = [];

        for (let index = this.hasHeader ? 1 : 0; index < this.csvData.length; index++) {
            const data = this.csvData[index];

            const objContact = {};

            this.contactColumns.forEach(key => {
                if (coldb.form) {
                    if (coldb.id === key.id) {
                        data[coldb.id] = colCsv;
                        objContact[key.id] = colCsv;
                    } else {
                        objContact[key.id] = data[this.associatedColumns[key.id]];
                    }
                } else {
                    objContact[key.id] = coldb.id === key.id ? data[this.csvColumns.filter(col => col === colCsv)[0]] : data[this.associatedColumns[key.id]];

                }
            });

            this.contactData.push(objContact);
        }

        setTimeout(() => {
            this.dataSource = new MatTableDataSource(this.contactData);
            this.dataSource.paginator = this.paginator;
        }, 0);
    }

    uploadCsv(fileInput: any) {
        if (fileInput.target.files && fileInput.target.files[0] && (fileInput.target.files[0].type === 'text/csv' || fileInput.target.files[0].type === 'application/vnd.ms-excel')) {
            this.loading = true;

            let rawCsv = [];
            const reader = new FileReader();

            reader.readAsText(fileInput.target.files[0]);

            reader.onload = (value: any) => {
                this.papa.parse(value.target.result, {
                    complete: (result) => {
                        rawCsv = result.data;
                        rawCsv = rawCsv.filter(data => data.length === rawCsv[0].length);

                        let dataCol = [];
                        let objData = {};

                        this.setCsvColumns(rawCsv[0]);
                        this.countAll = this.hasHeader ? rawCsv.length - 1 : rawCsv.length;

                        for (let index = 0; index < rawCsv.length; index++) {
                            objData = {};
                            dataCol = rawCsv[index];
                            dataCol.forEach((element: any, index2: number) => {
                                objData[this.csvColumns[index2]] = element;
                            });
                            this.csvData.push(objData);
                        }
                        this.initData();
                        this.localStorage.save(`importContactFields_${this.headerService.user.id}`, this.currentDelimiter);

                        this.loading = false;
                    }
                });
            };
        } else {
            this.dialog.open(AlertComponent, { panelClass: 'maarch-modal', autoFocus: false, disableClose: true, data: { title: this.translate.instant('lang.notAllowedExtension') + ' !', msg: this.translate.instant('lang.file') + ' : <b>' + fileInput.target.files[0].name + '</b>, ' + this.translate.instant('lang.type') + ' : <b>' + fileInput.target.files[0].type + '</b><br/><br/><u>' + this.translate.instant('lang.allowedExtensions') + '</u> : <br/>' + 'text/csv' } });
        }
    }

    setCsvColumns(headerData: string[] = null) {
        if (headerData.filter(col => this.functionsService.empty(col)).length > 0) {
            this.csvColumns = Object.keys(headerData).map((val, index) => `${index}`);
        } else {
            this.csvColumns = headerData;
        }
    }

    toggleHeader() {
        this.hasHeader = !this.hasHeader;
        this.countAll = this.hasHeader ? this.csvData.length - 1 : this.csvData.length;
        if (this.hasHeader) {
            this.countAdd = this.csvData.filter((data: any, index: number) => index > 0 && this.functionsService.empty(data[this.associatedColumns['id']])).length;
            this.countUp = this.csvData.filter((data: any, index: number) => index > 0 && !this.functionsService.empty(data[this.associatedColumns['id']])).length;
        } else {
            this.countAdd = this.csvData.filter((data: any, index: number) => this.functionsService.empty(data[this.associatedColumns['id']])).length;
            this.countUp = this.csvData.filter((data: any, index: number) => !this.functionsService.empty(data[this.associatedColumns['id']])).length;
        }
        this.initData();
    }

    initData() {
        this.contactData = [];
        for (let index = this.hasHeader ? 1 : 0; index < this.csvData.length; index++) {
            const data = this.csvData[index];
            const objContact = {};

            this.contactColumns.forEach((key, indexCol) => {
                const indexCsvCol = this.csvColumns.indexOf(key.label);

                if (key.form) {
                    this.associatedColumns[key.id] = key.id;
                    this.csvData[index][key.id] = key.default_value;
                    objContact[key.id] = key.default_value;
                } else if (indexCsvCol > -1) {
                    this.associatedColumns[key.id] = this.csvColumns[indexCsvCol];
                    objContact[key.id] = data[this.csvColumns[indexCsvCol]];
                } else {
                    if (this.functionsService.empty(this.associatedColumns[key.id])) {
                        this.associatedColumns[key.id] = data[indexCol];
                        objContact[key.id] = data[indexCol];

                    } else {
                        objContact[key.id] = data[this.associatedColumns[key.id]];
                    }
                    // this.associatedColumns[key.id] = !this.functionsService.empty(this.associatedColumns[key.id]) ? data[this.associatedColumns[key.id]] : data[indexCol];
                }

            });
            this.contactData.push(objContact);

        }
        setTimeout(() => {
            this.dataSource = new MatTableDataSource(this.contactData);
            this.dataSource.paginator = this.paginator;
        }, 0);
    }

    dndUploadFile(event: any) {
        const fileInput = {
            target: {
                files: [
                    event[0]
                ]
            }
        };
        this.uploadCsv(fileInput);
    }

    onSubmit() {
        let dialogRef: any = null;
        const dataToSend: any[] = [];
        let confirmText = '';
        this.translate.get('lang.confirmImportRegisteredMails', { 0: this.countAll }).subscribe((res: string) => {
            confirmText = `${res} ?<br/><br/>`;
        });
        dialogRef = this.dialog.open(ConfirmComponent, { panelClass: 'maarch-modal', autoFocus: false, disableClose: true, data: { title: this.translate.instant('lang.import'), msg: confirmText } });
        dialogRef.afterClosed().pipe(
            filter((data: string) => data === 'ok'),
            tap(() => {
                this.loading = true;
                this.csvData.forEach((element: any, index: number) => {
                    if ((this.hasHeader && index > 0) || !this.hasHeader) {
                        const objContact = {};
                        this.contactColumns.forEach((key) => {
                            if (element[this.associatedColumns[key.id]] === undefined) {
                                objContact[key.id] = '';
                            } else {
                                if (element[this.associatedColumns[key.id]] instanceof Date && !isNaN(element[this.associatedColumns[key.id]].valueOf())) {
                                    objContact[key.id] = this.functionsService.formatDateObjectToDateString(element[this.associatedColumns[key.id]], false, 'yyyy-mm-dd');
                                } else {
                                    objContact[key.id] = element[this.associatedColumns[key.id]];
                                }
                            }
                        });
                        objContact['doctype'] = this.currentDoctype;
                        objContact['modelId'] = this.currentIndexingModel;
                        dataToSend.push(objContact);
                    }
                });
            }),
            exhaustMap(() => this.http.put('../rest/registeredMails/import', { registeredMails: dataToSend })),
            tap((data: any) => {
                let textModal = '';
                if (data.errors.count > 0) {
                    textModal += `<br/>${data.errors.count} ${this.translate.instant('lang.withErrors')}  : <ul>`;
                    data.errors.details.forEach(element => {
                        textModal += `<li> ${this.translate.instant('lang.' + element.lang, { 0: element.langParam })} (${this.translate.instant('lang.line')} : ${this.hasHeader ? element.index + 2 : element.index + 1})</li>`;
                    });
                    textModal += '</ul>';
                }
                dialogRef = this.dialog.open(AlertComponent, { panelClass: 'maarch-modal', autoFocus: false, disableClose: true, data: { title: this.translate.instant('lang.import'), msg: '<b>' + data.success + '</b> / <b>' + this.countAll + '</b> ' + this.translate.instant('lang.importedRegisteredMails') + '.' + textModal } });
            }),
            exhaustMap(() => dialogRef.afterClosed()),
            tap(() => {
                this.dialogRef.close('success');
            }),
            catchError((err: any) => {
                this.loading = false;
                this.notify.handleSoftErrors(err);
                return of(false);
            })
        ).subscribe();
    }
}
