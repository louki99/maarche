import { Component, OnInit, ViewChild } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { TranslateService } from '@ngx-translate/core';
import { NotificationService } from '@service/notification/notification.service';
import { HeaderService } from '@service/header.service';
import { MatDialog, MatDialogRef } from '@angular/material/dialog';
import { MatSidenav } from '@angular/material/sidenav';
import { AppService } from '@service/app.service';
import { tap, catchError, filter, exhaustMap, map, finalize } from 'rxjs/operators';
import { ConfirmComponent } from '@plugins/modal/confirm.component';
import { SortPipe } from '@plugins/sorting.pipe';
import { of } from 'rxjs';
import { FunctionsService } from '@service/functions.service';

@Component({
    templateUrl: 'custom-fields-administration.component.html',
    styleUrls: [
        'custom-fields-administration.component.scss',
        '../../indexation/indexing-form/indexing-form.component.scss'
    ],
    providers: [SortPipe]
})

export class CustomFieldsAdministrationComponent implements OnInit {

    @ViewChild('snav2', { static: true }) public sidenavRight: MatSidenav;


    loading: boolean = true;

    idTable: any = [];

    customFieldsTypes: any[] = [
        {
            label: this.translate.instant('lang.stringInput'),
            type: 'string'
        },
        {
            label: this.translate.instant('lang.integerInput'),
            type: 'integer'
        },
        {
            label: this.translate.instant('lang.selectInput'),
            type: 'select'
        },
        {
            label: this.translate.instant('lang.dateInput'),
            type: 'date'
        },
        {
            label: this.translate.instant('lang.radioInput'),
            type: 'radio'
        },
        {
            label: this.translate.instant('lang.checkboxInput'),
            type: 'checkbox'
        },
        {
            label: this.translate.instant('lang.banAutocompleteInput'),
            type: 'banAutocomplete'
        },
        {
            label: this.translate.instant('lang.contactInput'),
            type: 'contact'
        }
    ];
    customFields: any[] = [];
    customFieldsClone: any[] = [];
    mode: any[] = [
        {
            'label' : 'displayInForm',
            'value' : 'form'
        },
        {
            'label' : 'displayAsTechnicalData',
            'value' : 'technical'
        }
    ];

    incrementCreation: number = 1;

    sampleIncrement: number[] = [1, 2, 3, 4];

    SQLMode: boolean = false;
    availableTables: any = {};

    dialogRef: MatDialogRef<any>;

    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        private notify: NotificationService,
        public dialog: MatDialog,
        private headerService: HeaderService,
        public appService: AppService,
        private sortPipe: SortPipe,
        public functionsService: FunctionsService
    ) {

    }

    ngOnInit() {
        this.headerService.setHeader(this.translate.instant('lang.administration') + ' ' + this.translate.instant('lang.customFieldsAdmin'));

        this.getTables();

        this.loadCustomFields();
    }

    loadCustomFields() {
        this.http.get('../rest/customFields?admin=true').pipe(
            // TO FIX DATA BINDING SIMPLE ARRAY VALUES
            map((data: any) => {
                data.customFields.forEach((element: any) => {
                    element.SQLMode = element.SQLMode !== false;
                    const label = element.label;
                    if (label.includes(this.translate.instant('lang.newField'))) {
                        const tmpField = label.substr(this.translate.instant('lang.newField').length + 1);
                        if (!isNaN(Number(tmpField))) {
                            this.idTable.push(tmpField);
                            this.incrementCreation = Math.max( ... this.idTable) + 1;
                        }
                    }
                });
                return data;
            }),
            tap((data: any) => {
                this.customFields = data.customFields;
                this.customFieldsClone = JSON.parse(JSON.stringify(this.customFields));
            }),
            finalize(() => this.loading = false),
            catchError((err: any) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    addCustomField(customFieldType: any) {

        let newCustomField: any = {};

        this.dialogRef = this.dialog.open(ConfirmComponent, { panelClass: 'maarch-modal', autoFocus: false, disableClose: true, data: { title: this.translate.instant('lang.add'), msg: this.translate.instant('lang.confirmAction') } });
        this.dialogRef.afterClosed().pipe(
            filter((data: string) => data === 'ok'),
            tap(() => {
                newCustomField = {
                    label: this.translate.instant('lang.newField') + ' ' + this.incrementCreation,
                    type: customFieldType.type,
                    values: [this.translate.instant('lang.value') + ' 1', this.translate.instant('lang.value') + ' 2'],
                    mode : 'form'
                };
            }),
            exhaustMap(() => this.http.post('../rest/customFields', newCustomField)),
            tap((data: any) => {
                newCustomField.id = data.customFieldId;
                newCustomField.values = newCustomField.values.map((val: any) => ({
                    label : val
                }));
                this.customFields.push(newCustomField);
                this.customFieldsClone = JSON.parse(JSON.stringify(this.customFields));
                this.notify.success(this.translate.instant('lang.customFieldAdded'));
                this.incrementCreation++;
            }),
            catchError((err: any) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    addValue(indexCustom: number) {
        this.customFields[indexCustom].values.push(
            {
                key: this.customFields[indexCustom].values.length,
                label: ''
            }
        );
    }

    removeValue(customField: any, indexValue: number) {
        customField.values[indexValue].label = null;
    }

    removeCustomField(indexCustom: number) {
        this.dialogRef = this.dialog.open(ConfirmComponent, { panelClass: 'maarch-modal', autoFocus: false, disableClose: true, data: { title: this.translate.instant('lang.delete') + ' "' + this.customFields[indexCustom].label + '"', msg: this.translate.instant('lang.confirmAction') } });

        this.dialogRef.afterClosed().pipe(
            filter((data: string) => data === 'ok'),
            exhaustMap(() => this.http.delete('../rest/customFields/' + this.customFields[indexCustom].id)),
            tap(() => {
                this.customFields.splice(indexCustom, 1);
                this.notify.success(this.translate.instant('lang.customFieldDeleted'));
            }),
            catchError((err: any) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    updateCustomField(customField: any, indexCustom: number) {
        const customFieldToUpdate = { ...customField };
        if (!customField.SQLMode) {
            customField.values = customField.values.filter((x: any, i: any, a: any) => a.map((info: any) => info.label).indexOf(x.label) === i);
            // TO FIX DATA BINDING SIMPLE ARRAY VALUES
            const alreadyExists = this.customFields.filter(customFieldItem => customFieldItem.label === customFieldToUpdate.label);
            if (alreadyExists.length > 1) {
                this.notify.handleErrors(this.translate.instant('lang.customFieldAlreadyExists'));
                return of(false);
            }
        } else {
            if (['string', 'integer', 'date'].indexOf(customField.type) > -1) {
                customField.values.label = [{
                    column: customField.values.key,
                    delimiterEnd: '',
                    delimiterStart: ''
                }];
            }
        }
        this.http.put('../rest/customFields/' + customField.id, customFieldToUpdate).pipe(
            tap((data: any) => {
                this.customFieldsClone[indexCustom] = JSON.parse(JSON.stringify(data.customField));
                this.customFields[indexCustom].values = this.customFieldsClone[indexCustom].values;

                this.notify.success(this.translate.instant('lang.customFieldUpdated'));
                this.customFieldsClone = JSON.parse(JSON.stringify(this.customFields));
            }),
            catchError((err: any) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    sortValues(customField: any) {
        customField.values = this.sortPipe.transform(customField.values, 'label');
    }

    isModified(customField: any, indexCustomField: number) {
        if (!this.functionsService.empty(customField.oldValues)) {
            this.customFieldsClone[indexCustomField].oldValues = customField.oldValues;
        }
        if (JSON.stringify(customField) === JSON.stringify(this.customFieldsClone[indexCustomField]) || customField.label === '' || this.SQLMode || customField.mode === '') {
            return true;
        } else {
            return false;
        }
    }

    switchSQLMode(custom: any) {
        custom.SQLMode = !custom.SQLMode;
        const oldValues = custom.values;
        if (custom.SQLMode) {
            custom.values = {
                key: 'id',
                label:  [{
                    column: 'id',
                    delimiterEnd: '',
                    delimiterStart: ''
                }],
                table: 'users',
                clause: '1=1'
            };
        } else {
            custom.values = [];
        }
        if (!this.functionsService.empty(custom.oldValues)) {
            custom.values = custom.oldValues;
        }
        custom.oldValues = oldValues;
    }

    getTables() {
        this.http.get('../rest/customFieldsWhiteList').pipe(
            tap((data: any) => {
                data.allowedTables.forEach((table: any) => {
                    this.availableTables[table.name] = table.columns;
                });
            }),
            catchError((err: any) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    addColumnLabel(field: any, column: any) {
        field.push({
            delimiterStart: '',
            delimiterEnd: '',
            column: column

        });
    }

    removeColumnLabel(field: any, index: number) {
        field.splice(index, 1);
    }

    isValidField(field: any) {
        if (field.SQLMode) {
            return !this.functionsService.empty(field.values.key) && !this.functionsService.empty(field.values.label) && !this.functionsService.empty(field.values.table) && !this.functionsService.empty(field.values.clause);
        } else {
            return true;
        }
    }

    checkIfUntranslated(tableName: string) {
        let value: boolean = false;
        this.translate.get('lang.' + tableName).subscribe((res: string) => {
            value = res.includes('lang.') ? true : false;
        });
        return value;
    }
}
