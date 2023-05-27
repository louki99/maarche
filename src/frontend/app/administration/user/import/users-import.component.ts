import { Component, OnInit, Inject, ViewChild } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { NotificationService } from '@service/notification/notification.service';
import { MAT_DIALOG_DATA, MatDialogRef, MatDialog } from '@angular/material/dialog';
import { TranslateService } from '@ngx-translate/core';
import { MatTableDataSource } from '@angular/material/table';
import { FunctionsService } from '@service/functions.service';
import { ConfirmComponent } from '../../../../plugins/modal/confirm.component';
import { filter, exhaustMap, tap, catchError } from 'rxjs/operators';
import { of } from 'rxjs';
import { AlertComponent } from '../../../../plugins/modal/alert.component';
import { LocalStorageService } from '@service/local-storage.service';
import { HeaderService } from '@service/header.service';
import { MatPaginator } from '@angular/material/paginator';
import { Papa } from 'ngx-papaparse';

@Component({
    templateUrl: 'users-import.component.html',
    styleUrls: ['users-import.component.scss']
})
export class UsersImportComponent implements OnInit {

    @ViewChild(MatPaginator, { static: false }) paginator: MatPaginator;

    loading: boolean = false;
    userColmuns: string[] = [
        'id',
        'user_id',
        'firstname',
        'lastname',
        'mail',
        'phone',
    ];

    csvColumns: string[] = [];

    associatedColmuns: any = {};
    dataSource = new MatTableDataSource(null);
    hasHeader: boolean = true;
    csvData: any[] = [];
    userData: any[] = [];
    countAll: number = 0;
    countAdd: number = 0;
    countUp: number = 0;

    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        private notify: NotificationService,
        private functionsService: FunctionsService,
        private localStorage: LocalStorageService,
        private headerService: HeaderService,
        public dialog: MatDialog,
        public dialogRef: MatDialogRef<UsersImportComponent>,
        private papa: Papa,
        @Inject(MAT_DIALOG_DATA) public data: any,
    ) {
    }

    ngOnInit(): void {
    }

    changeColumn(coldb: string, colCsv: string) {
        this.userData = [];
        for (let index = this.hasHeader ? 1 : 0; index < this.csvData.length; index++) {
            const data = this.csvData[index];
            this.userData.push({
                'id': coldb === 'id' ? data[this.csvColumns.filter(col => col === colCsv)[0]] : data[this.associatedColmuns['id']],
                'user_id': coldb === 'user_id' ? data[this.csvColumns.filter(col => col === colCsv)[0]] : data[this.associatedColmuns['user_id']],
                'firstname': coldb === 'firstname' ? data[this.csvColumns.filter(col => col === colCsv)[0]] : data[this.associatedColmuns['firstname']],
                'lastname': coldb === 'lastname' ? data[this.csvColumns.filter(col => col === colCsv)[0]] : data[this.associatedColmuns['lastname']],
                'mail': coldb === 'mail' ? data[this.csvColumns.filter(col => col === colCsv)[0]] : data[this.associatedColmuns['mail']],
                'phone': coldb === 'phone' ? data[this.csvColumns.filter(col => col === colCsv)[0]] : data[this.associatedColmuns['phone']]
            });
        }

        this.countAdd = this.csvData.filter((data: any, index: number) => index > 0 && this.functionsService.empty(data[this.associatedColmuns['id']])).length;
        this.countUp = this.csvData.filter((data: any, index: number) => index > 0 && !this.functionsService.empty(data[this.associatedColmuns['id']])).length;

        setTimeout(() => {
            this.dataSource = new MatTableDataSource(this.userData);
            this.dataSource.paginator = this.paginator;
        }, 0);
    }

    uploadCsv(fileInput: any) {
        if (fileInput.target.files && fileInput.target.files[0] && (fileInput.target.files[0].type === 'text/csv' || fileInput.target.files[0].type === 'application/vnd.ms-excel')) {
            this.loading = true;

            let rawCsv = [];
            const reader = new FileReader();

            reader.readAsText(fileInput.target.files[0], 'ISO-8859-1');

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
        this.userData = [];
        for (let index = this.hasHeader ? 1 : 0; index < this.csvData.length; index++) {
            const data = this.csvData[index];
            this.associatedColmuns['id'] = this.csvColumns[0];
            this.associatedColmuns['user_id'] = this.csvColumns[1];
            this.associatedColmuns['firstname'] = this.csvColumns[2];
            this.associatedColmuns['lastname'] = this.csvColumns[3];
            this.associatedColmuns['mail'] = this.csvColumns[4];
            this.associatedColmuns['phone'] = this.csvColumns[5];


            this.userData.push({
                'id': data[this.csvColumns[0]],
                'user_id': data[this.csvColumns[1]],
                'firstname': data[this.csvColumns[2]],
                'lastname': data[this.csvColumns[3]],
                'mail': data[this.csvColumns[4]],
                'phone': data[this.csvColumns[5]]
            });
        }
        setTimeout(() => {
            this.dataSource = new MatTableDataSource(this.userData);
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
        this.translate.get('lang.confirmImportUsers', { 0: this.countAll }).subscribe((res: string) => {
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
                        dataToSend.push({
                            'id': element[this.associatedColmuns['id']],
                            'user_id': element[this.associatedColmuns['user_id']],
                            'firstname': element[this.associatedColmuns['firstname']],
                            'lastname': element[this.associatedColmuns['lastname']],
                            'mail': element[this.associatedColmuns['mail']],
                            'phone': element[this.associatedColmuns['phone']]
                        });
                    }
                });
            }),
            exhaustMap(() => this.http.put('../rest/users/import', { users: dataToSend })),
            tap((data: any) => {
                let textModal = '';
                if (data.warnings.count > 0) {
                    textModal = `<br/>${data.warnings.count} ${this.translate.instant('lang.withWarnings')}  : <ul>`;
                    data.warnings.details.forEach(element => {
                        textModal += `<li> ${this.translate.instant('lang.' + element.lang)} (${this.translate.instant('lang.line')} : ${this.hasHeader ? element.index + 2 : element.index + 1})</li>`;
                    });
                    textModal += '</ul>';
                }

                if (data.errors.count > 0) {
                    textModal += `<br/>${data.errors.count} ${this.translate.instant('lang.withErrors')}  : <ul>`;
                    data.errors.details.forEach(element => {
                        textModal += `<li> ${this.translate.instant('lang.' + element.lang)} (${this.translate.instant('lang.line')} : ${this.hasHeader ? element.index + 2 : element.index + 1})</li>`;
                    });
                    textModal += '</ul>';
                }
                dialogRef = this.dialog.open(AlertComponent, { panelClass: 'maarch-modal', autoFocus: false, disableClose: true, data: { title: this.translate.instant('lang.import'), msg: '<b>' + data.success + '</b> / <b>' + this.countAll + '</b> ' + this.translate.instant('lang.importedUsers') + '.' + textModal } });
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
