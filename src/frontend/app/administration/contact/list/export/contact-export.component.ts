import { Component, OnInit, ViewChild, Inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { TranslateService } from '@ngx-translate/core';
import { NotificationService } from '@service/notification/notification.service';
import { CdkDragDrop, moveItemInArray, transferArrayItem } from '@angular/cdk/drag-drop';
import { MAT_DIALOG_DATA } from '@angular/material/dialog';
import { SortPipe } from '../../../../../plugins/sorting.pipe';
import { catchError, map, tap, finalize, exhaustMap } from 'rxjs/operators';
import { of } from 'rxjs';
import { LocalStorageService } from '@service/local-storage.service';
import { HeaderService } from '@service/header.service';
import { FunctionsService } from '@service/functions.service';

declare let $: any;

@Component({
    templateUrl: 'contact-export.component.html',
    styleUrls: ['contact-export.component.scss'],
    providers: [SortPipe],
})
export class ContactExportComponent implements OnInit {

    @ViewChild('listFilter', { static: true }) private listFilter: any;

    loading: boolean = false;
    loadingExport: boolean = false;

    delimiters = [';', ',', 'TAB'];
    formats = ['csv'];

    exportModel: any = {
        delimiter: ';',
        format: 'csv',
        data: [],
    };

    exportModelList: any;

    dataAvailable: any[] = [
        {
            value: 'id',
            label: this.translate.instant('lang.id')
        },
        {
            value: 'externalId',
            label: 'External Id'
        },
        {
            value: 'enabled',
            label: this.translate.instant('lang.status')
        },
        {
            value: 'communicationMeans',
            label: this.translate.instant('lang.communicationMean')
        }
    ];

    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        private notify: NotificationService,
        @Inject(MAT_DIALOG_DATA) public data: any,
        private sortPipe: SortPipe,
        private localStorage: LocalStorageService,
        private headerService: HeaderService,
        private functionsService: FunctionsService
    ) { }

    async ngOnInit(): Promise<void> {
        await this.getContactFields();
        this.setConfiguration();
    }

    getContactFields() {
        return new Promise((resolve, reject) => {
            this.http.get('../rest/contactsParameters').pipe(
                map((data: any) => {
                    const regex = /contactCustomField_[.]*/g;
                    data.contactsParameters = data.contactsParameters.filter((field: any) => field.identifier.match(regex) === null).map((field: any) => ({
                        value: field.identifier,
                        label: this.translate.instant('lang.contactsParameters_' + field.identifier)
                    }));
                    return data.contactsParameters;
                }),
                tap((fields: any) => {
                    this.dataAvailable = this.dataAvailable.concat(fields);
                }),
                exhaustMap(() => this.http.get('../rest/contactsCustomFields')),
                map((data: any) => {
                    data.customFields = data.customFields.map((field: any) => ({
                        value: `contactCustomField_${field.id}`,
                        label: field.label
                    }));
                    return data.customFields;
                }),
                tap((fields: any) => {
                    this.dataAvailable = this.dataAvailable.concat(fields);
                    this.dataAvailable = this.sortPipe.transform(this.dataAvailable, 'label');
                    resolve(true);
                }),
                catchError((err: any) => {
                    this.notify.handleSoftErrors(err);
                    return of(false);
                })
            ).subscribe();
        });
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
    }

    exportData() {
        this.localStorage.save(`exportContactFields_${this.headerService.user.id}`, JSON.stringify(this.exportModel));
        this.loadingExport = true;
        this.http.put('../rest/contacts/export', this.exportModel, { responseType: 'blob' }).pipe(
            tap((data: any) => {
                if (data.type !== 'text/html') {
                    const downloadLink = document.createElement('a');
                    downloadLink.href = window.URL.createObjectURL(data);
                    downloadLink.setAttribute('download', this.functionsService.getFormatedFileName('export_contact_maarch', this.exportModel.format.toLowerCase()));
                    document.body.appendChild(downloadLink);
                    downloadLink.click();
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

    addData(item: any) {
        let realIndex = 0;

        this.dataAvailable.forEach((value: any, index: number) => {
            if (value.value === item.value) {
                realIndex = index;
            }
        });

        transferArrayItem(this.dataAvailable, this.exportModel.data, realIndex, this.exportModel.data.length);
        const curFilter = this.listFilter.nativeElement.value;
        this.listFilter.nativeElement.value = '';
        setTimeout(() => {
            this.listFilter.nativeElement.value = curFilter;
        }, 10);
    }

    removeData(i: number) {
        transferArrayItem(this.exportModel.data, this.dataAvailable, i, this.dataAvailable.length);
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

    setConfiguration() {
        if (this.localStorage.get(`exportContactFields_${this.headerService.user.id}`) !== null) {
            JSON.parse(this.localStorage.get(`exportContactFields_${this.headerService.user.id}`)).data.forEach((element: any) => {
                this.addData(element);
            });
            this.exportModel.delimiter = JSON.parse(this.localStorage.get(`exportContactFields_${this.headerService.user.id}`)).delimiter;
        }
    }
}
