import { Component, OnInit, Inject, ViewChild } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { NotificationService } from '@service/notification/notification.service';
import { MAT_DIALOG_DATA, MatDialogRef, MatDialog } from '@angular/material/dialog';
import { TranslateService } from '@ngx-translate/core';
import { MatTableDataSource } from '@angular/material/table';
import { FunctionsService } from '@service/functions.service';
import { ConfirmComponent } from '../../../../../plugins/modal/confirm.component';
import { filter, exhaustMap, tap, catchError, map } from 'rxjs/operators';
import { of } from 'rxjs';
import { AlertComponent } from '../../../../../plugins/modal/alert.component';
import { LocalStorageService } from '@service/local-storage.service';
import { HeaderService } from '@service/header.service';
import { MatPaginator } from '@angular/material/paginator';
import { Papa } from 'ngx-papaparse';

@Component({
    templateUrl: 'contact-import.component.html',
    styleUrls: ['contact-import.component.scss']
})
export class ContactImportComponent implements OnInit {

    @ViewChild(MatPaginator, { static: false }) paginator: MatPaginator;

    loading: boolean = false;

    contactColumns: any[] = [
        {
            id: 'id',
            label: this.translate.instant('lang.id'),
            emptyValueMode: false
        },
        {
            id: 'company',
            label: this.translate.instant('lang.contactsParameters_company'),
            emptyValueMode: false,
        },
        {
            id: 'civility',
            label: this.translate.instant('lang.contactsParameters_civility'),
            emptyValueMode: false
        },
        {
            id: 'firstname',
            label: this.translate.instant('lang.contactsParameters_firstname'),
            emptyValueMode: false
        },
        {
            id: 'lastname',
            label: this.translate.instant('lang.contactsParameters_lastname'),
            emptyValueMode: false
        },
        {
            id: 'function',
            label: this.translate.instant('lang.contactsParameters_function'),
            emptyValueMode: false
        },
        {
            id: 'department',
            label: this.translate.instant('lang.contactsParameters_department'),
            emptyValueMode: false
        },
        {
            id: 'email',
            label: this.translate.instant('lang.contactsParameters_email'),
            emptyValueMode: false
        },
        {
            id: 'phone',
            label: this.translate.instant('lang.contactsParameters_phone'),
            emptyValueMode: false
        },
        {
            id: 'addressAdditional1',
            label: this.translate.instant('lang.contactsParameters_addressAdditional1'),
            emptyValueMode: false
        },
        {
            id: 'addressNumber',
            label: this.translate.instant('lang.contactsParameters_addressNumber'),
            emptyValueMode: false
        },
        {
            id: 'addressStreet',
            label: this.translate.instant('lang.contactsParameters_addressStreet'),
            emptyValueMode: false
        },
        {
            id: 'addressAdditional2',
            label: this.translate.instant('lang.contactsParameters_addressAdditional2'),
            emptyValueMode: false
        },
        {
            id: 'addressPostcode',
            label: this.translate.instant('lang.contactsParameters_addressPostcode'),
            emptyValueMode: false
        },
        {
            id: 'addressTown',
            label: this.translate.instant('lang.contactsParameters_addressTown'),
            emptyValueMode: false
        },
        {
            id: 'addressCountry',
            label: this.translate.instant('lang.contactsParameters_addressCountry'),
            emptyValueMode: false
        },
    ];

    csvColumns: string[] = [];

    associatedColmuns: any = {};
    dataSource = new MatTableDataSource(null);
    hasHeader: boolean = true;
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
        public dialogRef: MatDialogRef<ContactImportComponent>,
        private papa: Papa,
        @Inject(MAT_DIALOG_DATA) public data: any,
    ) {
    }

    ngOnInit(): void {
        this.initCustomFields();
    }

    getcontactColumnsIds() {
        return this.contactColumns.map(col => col.id);
    }

    initCustomFields() {
        this.http.get('../rest/contactsCustomFields').pipe(
            map((data: any) => {
                data = data.customFields.map(custom => ({
                    id: `contactCustomField_${custom.id}`,
                    label: custom.label,
                    type: custom.type
                }));
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
    }


    toggleEmptyMode(id: string, state: boolean) {
        this.contactColumns.filter(col => col.id === id)[0].emptyValueMode = state;
    }

    changeColumn(coldb: any, colCsv: string) {
        this.contactData = [];

        for (let index = this.hasHeader ? 1 : 0; index < this.csvData.length; index++) {
            const data = this.csvData[index];

            const objContact = {};

            this.contactColumns.forEach(key => {
                objContact[key.id] = coldb === key.id ? data[this.csvColumns.filter(col => col === colCsv)[0]] : data[this.associatedColmuns[key.id]];
            });

            this.contactData.push(objContact);
        }

        this.countAdd = this.csvData.filter((data: any, index: number) => index > 0 && this.functionsService.empty(data[this.associatedColmuns['id']])).length;
        this.countUp = this.csvData.filter((data: any, index: number) => index > 0 && !this.functionsService.empty(data[this.associatedColmuns['id']])).length;

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
                        this.countAdd = this.csvData.filter((data: any, index: number) => index > 0 && this.functionsService.empty(data[this.associatedColmuns['id']])).length;
                        this.countUp = this.csvData.filter((data: any, index: number) => index > 0 && !this.functionsService.empty(data[this.associatedColmuns['id']])).length;

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
            this.countAdd = this.csvData.filter((data: any, index: number) => index > 0 && this.functionsService.empty(data[this.associatedColmuns['id']])).length;
            this.countUp = this.csvData.filter((data: any, index: number) => index > 0 && !this.functionsService.empty(data[this.associatedColmuns['id']])).length;
        } else {
            this.countAdd = this.csvData.filter((data: any, index: number) => this.functionsService.empty(data[this.associatedColmuns['id']])).length;
            this.countUp = this.csvData.filter((data: any, index: number) => !this.functionsService.empty(data[this.associatedColmuns['id']])).length;
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
                this.associatedColmuns[key.id] = indexCsvCol > -1 ? this.csvColumns[indexCsvCol] : '';
                objContact[key.id] = indexCsvCol > -1 ? data[this.csvColumns[indexCsvCol]] : '';
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
        this.translate.get('lang.confirmImportContacts', { 0: this.countAll }).subscribe((res: string) => {
            confirmText = `${res} ?<br/><br/>`;
            confirmText += `<ul><li><b>${this.countAdd}</b> ${this.translate.instant('lang.additions')}</li><li><b>${this.countUp}</b> ${this.translate.instant('lang.modifications')}</li></ul>`;
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
                            if (key.emptyValueMode && (element[this.associatedColmuns[key.id]] === undefined || this.functionsService.empty(element[this.associatedColmuns[key.id]]))) {
                                objContact[key.id] = false;
                            } else {
                                if (element[this.associatedColmuns[key.id]] === undefined) {
                                    objContact[key.id] = '';
                                } else {
                                    if (key.type === 'checkbox') {
                                        objContact[key.id] = !this.functionsService.empty(element[this.associatedColmuns[key.id]]) ? element[this.associatedColmuns[key.id]].split('\n') : [];

                                    } else {
                                        objContact[key.id] = element[this.associatedColmuns[key.id]];
                                    }
                                }
                            }
                        });
                        dataToSend.push(objContact);
                    }
                });
            }),
            exhaustMap(() => this.http.put('../rest/contacts/import', { contacts: dataToSend })),
            tap((data: any) => {
                let textModal = '';
                if (data.errors.count > 0) {
                    textModal += `<br/>${data.errors.count} ${this.translate.instant('lang.withErrors')}  : <ul>`;
                    data.errors.details.forEach(element => {
                        textModal += `<li> ${this.translate.instant('lang.' + element.lang, {0: element.langParam})} (${this.translate.instant('lang.line')} : ${this.hasHeader ? element.index + 2 : element.index + 1})</li>`;
                    });
                    textModal += '</ul>';
                }
                dialogRef = this.dialog.open(AlertComponent, { panelClass: 'maarch-modal', autoFocus: false, disableClose: true, data: { title: this.translate.instant('lang.import'), msg: '<b>' + data.success + '</b> / <b>' + this.countAll + '</b> ' + this.translate.instant('lang.importedContacts') + '.' + textModal } });
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
