import { Component, OnInit, ViewChild, Inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { TranslateService } from '@ngx-translate/core';
import { NotificationService } from '@service/notification/notification.service';
import { CdkDragDrop, moveItemInArray, transferArrayItem } from '@angular/cdk/drag-drop';
import { MAT_DIALOG_DATA } from '@angular/material/dialog';
import { SortPipe } from '@plugins/sorting.pipe';
import { catchError, tap, finalize } from 'rxjs/operators';
import { of } from 'rxjs/internal/observable/of';
import { LocalStorageService } from '@service/local-storage.service';
import { HeaderService } from '@service/header.service';
import { FunctionsService } from '@service/functions.service';

declare function $j(selector: any): any;

@Component({
    templateUrl: 'history-export.component.html',
    styleUrls: ['history-export.component.scss'],
    providers: [SortPipe],
})
export class HistoryExportComponent implements OnInit {

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

    localStorageNames: any = {
        history: 'History',
        batchHistory: 'BatchHistory'
    };

    origin: string = '';
    dataAvailable: any[] = [];



    constructor(
        @Inject(MAT_DIALOG_DATA) public dialogData: any,
        public http: HttpClient,
        public functions: FunctionsService,
        public translate: TranslateService,
        private notify: NotificationService,
        private sortPipe: SortPipe,
        private localStorage: LocalStorageService,
        private headerService: HeaderService
    ) { }

    ngOnInit() {
        this.dataAvailable = JSON.parse(JSON.stringify(this.dialogData.dataAvailable));
        this.setConfiguration();
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

            transferArrayItem(event.previousContainer.data, event.container.data, realIndex, event.currentIndex);
            const curFilter = this.listFilter.nativeElement.value;
            this.listFilter.nativeElement.value = '';
            setTimeout(() => {
                this.listFilter.nativeElement.value = curFilter;
            }, 10);
        }
    }

    addAllData() {
        this.exportModel.data = this.exportModel.data.concat(this.dataAvailable);
        while (this.dataAvailable.length > 0) {
            this.dataAvailable.pop();
        }
        this.listFilter.nativeElement.value = '';
    }

    removeData(i: number) {
        transferArrayItem(this.exportModel.data, this.dataAvailable, i, this.dataAvailable.length);
        this.sortPipe.transform(this.dataAvailable, 'label');
    }

    removeAllData() {
        this.dataAvailable = this.dataAvailable.concat(this.exportModel.data);
        this.exportModel.data = [];
    }

    exportData() {
        this.localStorage.save(`export${this.localStorageNames[this.dialogData.origin]}Fields_${this.headerService.user.id}`, JSON.stringify(this.exportModel));
        this.loadingExport = true;
        this.http.put(`../rest/${this.dialogData.origin}/export?limit=1000`, {... this.exportModel, 'parameters': this.dialogData.parameters}, { responseType: 'blob' }).pipe(
            tap((data: any) => {
                if (data.type !== 'text/html') {
                    const downloadLink = document.createElement('a');
                    downloadLink.href = window.URL.createObjectURL(data);
                    let today: any, dd: any, mm: any;

                    today = new Date();
                    dd = today.getDate();
                    mm = today.getMonth() + 1;
                    const yyyy = today.getFullYear();

                    if (dd < 10) {
                        dd = '0' + dd;
                    }
                    if (mm < 10) {
                        mm = '0' + mm;
                    }
                    today = dd + '-' + mm + '-' + yyyy;
                    downloadLink.setAttribute('download', `export_${this.dialogData.origin}_maarch_` + today + '.' + this.exportModel.format.toLowerCase());
                    document.body.appendChild(downloadLink);
                    downloadLink.click();
                } else {
                    alert(this.translate.instant('lang.tooMuchDatas'));
                }
            }),
            finalize(() => this.loadingExport = false),
            catchError((err: any) => {
                this.notify.handleSoftErrors(err);
                return of(false);
            })
        ).subscribe();
    }


    setConfiguration() {
        if (!this.functions.empty(this.localStorage.get(`export${this.localStorageNames[this.dialogData.origin]}Fields_${this.headerService.user.id}`))) {
            JSON.parse(this.localStorage.get(`export${this.localStorageNames[this.dialogData.origin]}Fields_${this.headerService.user.id}`)).data.forEach((element: any) => {
                this.addData(element);
            });
            this.exportModel.delimiter = JSON.parse(this.localStorage.get(`export${this.localStorageNames[this.dialogData.origin]}Fields_${this.headerService.user.id}`)).delimiter;
        }
    }
}
