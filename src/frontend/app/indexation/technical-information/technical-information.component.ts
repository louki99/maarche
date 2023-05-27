import { Component, OnInit, Inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { TranslateService } from '@ngx-translate/core';
import { MatDialog, MatDialogRef, MAT_DIALOG_DATA } from '@angular/material/dialog';
import { AppService } from '@service/app.service';
import { FunctionsService } from '@service/functions.service';
import { catchError, exhaustMap, tap } from 'rxjs/operators';
import { NotificationService } from '@service/notification/notification.service';
import { of } from 'rxjs';
import { DatePipe, KeyValue } from '@angular/common';


@Component({
    templateUrl: 'technical-information.component.html',
    styleUrls: ['technical-information.component.scss']
})
export class TechnicalInformationComponent implements OnInit {

    loading: boolean = false;

    techData: {[key: string]: any} = {
        initiator: {
            label: 'initiator',
            value: '',
            icon: 'fas fa-user'
        },
        creationDate: {
            label: 'creationDate',
            value: '',
            icon: 'fas fa-calendar-day'
        },
        size: {
            label: 'filesize',
            value: '',
            icon: 'fas fa-cubes'
        },
        format: {
            label: 'fileFormat',
            value: '',
            icon: 'far fa-file-archive'
        },
        filename: {
            label: 'filename',
            value: '',
            icon: 'fas fa-quote-right'
        },
        docserverPathFile: {
            label: 'docserverPathFile',
            value: '',
            icon: 'fas fa-terminal'
        },
        fingerprint: {
            label: 'fingerprint',
            value: '',
            icon: 'fas fa-fingerprint'
        },
        fulltext: {
            label: 'fulltext',
            value: '',
            icon: 'far fa-file-alt'
        }
    };

    customsData: any = {};
    customs: any = {};

    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        private notify: NotificationService,
        public dialog: MatDialog,
        public dialogRef: MatDialogRef<TechnicalInformationComponent>,
        @Inject(MAT_DIALOG_DATA) public data: any,
        public appService: AppService,
        public functions: FunctionsService,
        private datePipe: DatePipe
    ) { }

    ngOnInit(): void {
        this.fetchData();
    }

    fetchData() {
        this.http.get(`../rest/resources/${this.data.resId}/fileInformation`).pipe(
            tap((data: any) => {
                this.techData.format.value = data.information.format;
                this.techData.fingerprint.value = data.information.fingerprint;
                this.techData.size.value = this.functions.formatBytes(data.information.filesize);
                this.techData.fulltext.value = data.information.fulltext_result;
                this.techData.docserverPathFile.value = data.information.docserverPathFile;
                this.techData.filename.value = data.information.filename;
                this.techData.initiator.value = data.information.typistLabel;
                this.techData.creationDate.value = this.datePipe.transform(data.information.creationDate, 'dd/MM/y HH:mm');
                this.loading = false;
            }),
            exhaustMap(() => this.http.get('../rest/customFields')),
            tap((data: any) => {
                data.customFields.filter((item: { mode: any }) => item.mode === 'technical').map((info: any) => {
                    this.customs[info.id] = {
                        label : info.label,
                        type : info.type
                    };
                });
            }),
            exhaustMap(() => this.http.get(`../rest/resources/${this.data.resId}`)),
            tap((data: any) => {
                Object.keys(data.customFields).forEach(key => {
                    if (!this.functions.empty(this.customs[key])) {
                        this.customsData[key] = {
                            label: this.customs[key].label,
                            value: data.customFields[key],
                            icon: 'fas fa-hashtag'
                        };
                    }
                });
                Object.keys(data.externalId).forEach(key => {
                    if (this.functions.empty(this.techData[key])) {
                        this.techData[key] = {
                            label: key,
                            value: data.externalId[key],
                            icon: 'fa fa-key'
                        };
                    }
                });
            }),
            catchError((err: any) => {
                this.notify.handleSoftErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    isEmptyCustom() {
        return Object.keys(this.customsData).length === 0;
    }

    originalOrder = (a: KeyValue<string, any>, b: KeyValue<string, any>): number => 0;
}
