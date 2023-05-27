import { Component, OnInit, Inject, ViewChild } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { TranslateService } from '@ngx-translate/core';
import { NotificationService } from '@service/notification/notification.service';
import { MatDialogRef, MAT_DIALOG_DATA } from '@angular/material/dialog';
import { catchError, tap, finalize } from 'rxjs/operators';
import { of } from 'rxjs';
import { LocalStorageService } from '@service/local-storage.service';
import { HeaderService } from '@service/header.service';
import { FunctionsService } from '@service/functions.service';
import { CdkDragDrop, moveItemInArray, transferArrayItem } from '@angular/cdk/drag-drop';
import { SortPipe } from '@plugins/sorting.pipe';

@Component({
    templateUrl: 'users-export.component.html',
    styleUrls: ['users-export.component.scss'],
    providers: [SortPipe],
})
export class UsersExportComponent implements OnInit {

    @ViewChild('listFilter', { static: false }) private listFilter: any;

    loading: boolean = false;
    loadingExport: boolean = false;

    delimiters = [';', ',', 'TAB'];
    formats = ['csv'];

    exportModel: any = {
        delimiter: ';',
        format: 'csv',
        data: []
    };

    exportModelList: any;

    canModifyHeaders: boolean = false;

    dataAvailable: any[] = [
        {
            value: 'id',
            label: this.translate.instant('lang.usersParameters_id')
        },
        {
            value: 'userId',
            label: this.translate.instant('lang.usersParameters_userId')
        },
        {
            value: 'firstname',
            label: this.translate.instant('lang.usersParameters_firstname')
        },
        {
            value: 'lastname',
            label: this.translate.instant('lang.usersParameters_lastname')
        },
        {
            value: 'mail',
            label: this.translate.instant('lang.usersParameters_mail')
        },
        {
            value: 'phone',
            label: this.translate.instant('lang.usersParameters_phone')
        },
        {
            value: 'status',
            label: this.translate.instant('lang.status')
        },
        {
            value: 'accountType',
            label: this.translate.instant('lang.accountType')
        },
        {
            value: 'groups',
            label: this.translate.instant('lang.groups')
        },
        {
            value : 'entities',
            label: this.translate.instant('lang.entities')
        },
        {
            value: 'baskets',
            label: this.translate.instant('lang.baskets')
        },
        {
            value: 'redirectedBaskets',
            label: this.translate.instant('lang.redirectedBaskets')
        },
        {
            value: 'assignedBaskets',
            label: this.translate.instant('lang.assignedBaskets')
        }
    ];

    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        private notify: NotificationService,
        public dialogRef: MatDialogRef<UsersExportComponent>,
        @Inject(MAT_DIALOG_DATA) public data: any,
        private localStorage: LocalStorageService,
        private headerService: HeaderService,
        private functionsService: FunctionsService,
        private sortPipe: SortPipe,
    ) { }

    ngOnInit(): any {
        if (this.localStorage.get(`exportUsersFields_${this.headerService.user.id}`) !== null) {
            this.canModifyHeaders = JSON.parse(this.localStorage.get(`exportUsersFields_${this.headerService.user.id}`)).data.length > 0 ? true : false;
        } else {
            this.canModifyHeaders = false;
        }
        this.setConfiguration();
    }

    drop(event: CdkDragDrop<string[]>) {
        if (event.previousContainer === event.container) {
            moveItemInArray(event.container.data, event.previousIndex, event.currentIndex);
        } else {
            let realIndex = event.previousIndex;
            if (event.container.id === 'selectedElements') {
                realIndex = 0;
                if ($('.available-data .columns')[event.previousIndex] !== undefined) {
                    const fakeIndex = $('.available-data .columns')[event.previousIndex].id;
                    realIndex = this.dataAvailable.map((dataAv: any) => (dataAv.value)).indexOf(fakeIndex);
                }
            }

            transferArrayItem(event.previousContainer.data,
                event.container.data,
                realIndex,
                event.currentIndex);
            const curFilter = this.listFilter.nativeElement.value;
            this.listFilter.nativeElement.value = '';
            setTimeout(() => {
                this.listFilter.nativeElement.value = curFilter;
            }, 10);

        }
        if (event.item.dropContainer.id !== 'availableElements') {
            this.dataAvailable = [... new Set(this.dataAvailable)];
        }
    }

    addData(item: any) {
        let realIndex = 0;

        this.dataAvailable.forEach((value: any, index: number) => {
            if (value.value === item.value) {
                realIndex = index;
            }
        });

        transferArrayItem(this.dataAvailable, this.exportModel.data, realIndex, this.exportModel.data.length);
        if (this.listFilter !== undefined) {
            const curFilter = this.listFilter.nativeElement.value;
            this.listFilter.nativeElement.value = '';
            setTimeout(() => {
                this.listFilter.nativeElement.value = curFilter;
            }, 10);
        }
    }

    removeData(i: number) {
        this.dataAvailable = this.dataAvailable.concat(this.exportModel.data[i]);
        this.exportModel.data.splice(i, 1);
        this.sortPipe.transform(this.dataAvailable, 'label');
    }

    removeAllData() {
        this.dataAvailable = this.dataAvailable.concat(this.exportModel.data);
        this.exportModel.data = [];
    }

    addAllData() {
        this.exportModel.data = this.exportModel.data.concat(this.dataAvailable);
        while (this.dataAvailable.length > 0) {
            this.dataAvailable.pop();
        }
        this.listFilter.nativeElement.value = '';
    }

    setValues() {
        this.canModifyHeaders = !this.canModifyHeaders;
        if (!this.canModifyHeaders) {
            this.dataAvailable = this.dataAvailable.concat(this.exportModel.data);
            this.exportModel.data = [];
        }
    }

    exportData() {
        this.localStorage.save(`exportUsersFields_${this.headerService.user.id}`, JSON.stringify(this.exportModel));
        this.loadingExport = true;
        this.http.put('../rest/users/export', this.exportModel, { responseType: 'blob' }).pipe(
            tap((data: any) => {
                if (data.type !== 'text/html') {
                    const downloadLink = document.createElement('a');
                    downloadLink.href = window.URL.createObjectURL(data);
                    downloadLink.setAttribute('download', this.functionsService.getFormatedFileName('export_users_maarch', this.exportModel.format.toLowerCase()));
                    document.body.appendChild(downloadLink);
                    downloadLink.click();
                    this.dialogRef.close();
                } else {
                    alert(this.translate.instant('lang.tooMuchDatas'));
                }
            }),
            finalize(() => this.loadingExport = false),
            catchError((err: any) => {
                this.notify.handleBlobErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    setConfiguration() {
        if (this.localStorage.get(`exportUsersFields_${this.headerService.user.id}`) !== null) {
            JSON.parse(this.localStorage.get(`exportUsersFields_${this.headerService.user.id}`)).data.forEach((element: any) => {
                this.addData(element);
            });
            this.exportModel.delimiter = JSON.parse(this.localStorage.get(`exportUsersFields_${this.headerService.user.id}`)).delimiter;
        }
    }
}
