import { Component, Inject, OnInit } from '@angular/core';
import { MAT_DIALOG_DATA, MatDialogRef } from '@angular/material/dialog';
import { TranslateService } from '@ngx-translate/core';
import { catchError, finalize, map, tap } from 'rxjs/operators';
import { of } from 'rxjs';
import { HttpClient } from '@angular/common/http';
import { SortPipe } from '../../../../plugins/sorting.pipe';
import { NotificationService } from '@service/notification/notification.service';

@Component({
    templateUrl: 'redirect-indexing-model.component.html',
    styleUrls: ['redirect-indexing-model.component.scss'],
    providers: [SortPipe]
})
export class RedirectIndexingModelComponent implements OnInit {

    title: string = 'lang.delete';
    indexingModels: any[] = [];
    modelIds: any[] = [];

    selectedModelId: any;
    selectedModelFields: any[];

    mainIndexingModel: any = {
        used: []
    };
    mainIndexingModelFields: any[];

    resetFields: any[] = [];

    statuses: any[] = [];
    customFields: any[] = [];

    availableFields: any[] = [
        {
            identifier: 'doctype',
            label: this.translate.instant('lang.doctype')
        },
        {
            identifier: 'subject',
            label: this.translate.instant('lang.subject')
        },
        {
            identifier: 'recipients',
            label: this.translate.instant('lang.getRecipients')
        },
        {
            identifier: 'priority',
            label: this.translate.instant('lang.priority')
        },
        {
            identifier: 'confidentiality',
            label: this.translate.instant('lang.confidential')
        },
        {
            identifier: 'initiator',
            label: this.translate.instant('lang.initiatorEntityAlt')
        },
        {
            identifier: 'departureDate',
            label: this.translate.instant('lang.departureDate')
        },
        {
            identifier: 'processLimitDate',
            label: this.translate.instant('lang.processLimitDate')
        },
        {
            identifier: 'tags',
            label: this.translate.instant('lang.tags')
        },
        {
            identifier: 'senders',
            label: this.translate.instant('lang.getSenders')
        },
        {
            identifier: 'destination',
            label: this.translate.instant('lang.destination')
        },
        {
            identifier: 'folders',
            label: this.translate.instant('lang.folders')
        },
        {
            identifier: 'documentDate',
            label: this.translate.instant('lang.docDate')
        },
        {
            identifier: 'arrivalDate',
            label: this.translate.instant('lang.arrivalDate')
        },
    ];

    loading: boolean = true;

    constructor(
        public translate: TranslateService,
        @Inject(MAT_DIALOG_DATA) public data: any,
        public dialogRef: MatDialogRef<RedirectIndexingModelComponent>,
        public http: HttpClient,
        private notify: NotificationService,
        private sortPipe: SortPipe,
    ) {
        this.mainIndexingModel.id = data.indexingModel.id;
    }

    async ngOnInit() {
        await this.loadIndexingModelFields();

        if (this.mainIndexingModel.used.length > 0) {
            this.title = 'lang.indexingModelReassign';
            await this.loadIndexingModels();

            await this.loadStatuses();

            await this.loadCustomFields();

            this.formatFields();
        }

        this.loading = false;
    }

    loadIndexingModels() {
        return new Promise((resolve) => {
            this.http.get('../rest/indexingModels').pipe(
                map((data: any) => data.indexingModels.filter((info: any) => info.private === false)),
                tap((data: any) => {
                    this.indexingModels = data;

                    this.sortPipe.transform(this.indexingModels, 'label');

                    this.modelIds = this.indexingModels.map(model => model.id);
                }),
                finalize(() => resolve(true)),
                catchError((err: any) => {
                    this.notify.handleErrors(err);
                    return of(false);
                })
            ).subscribe();
        });
    }

    loadIndexingModelFields() {
        return new Promise((resolve) => {
            this.http.get('../rest/indexingModels/' + this.mainIndexingModel.id + '?used=true').pipe(
                tap((data: any) => {
                    this.mainIndexingModel.used = data.indexingModel.used;

                    this.mainIndexingModelFields = data.indexingModel.fields;
                }),
                tap(() => resolve(true)),
                catchError((err: any) => {
                    this.notify.handleErrors(err);
                    return of(false);
                })
            ).subscribe();
        });
    }

    formatFields() {
        this.mainIndexingModelFields = this.mainIndexingModelFields.map(field => {
            const availableField = this.availableFields.find(elem => elem.identifier === field.identifier);
            field.label = availableField === undefined ? this.translate.instant('lang.undefined') : availableField.label;
            return field;
        });
    }

    loadStatuses() {
        return new Promise((resolve) => {
            this.http.get('../rest/statuses').pipe(
                tap((data: any) => {
                    this.statuses = data.statuses;

                    this.mainIndexingModel.used.forEach((element: any) => {
                        const elementStatus = this.statuses.find(status => status.id === element.status);
                        if (elementStatus !== undefined) {
                            element.status = elementStatus.label_status;
                        }
                    });
                }),
                finalize(() => resolve(true)),
                catchError((err: any) => {
                    this.notify.handleErrors(err);
                    return of(false);
                })
            ).subscribe();
        });
    }

    loadCustomFields() {
        return new Promise((resolve) => {
            this.http.get('../rest/customFields').pipe(
                tap((data: any) => {
                    data.customFields = data.customFields.map((custom: any) => ({
                        identifier: 'indexingCustomField_' + custom.id,
                        label: custom.label
                    }));
                    data.customFields.forEach((custom: any) => {
                        this.availableFields.push(custom);
                    });
                    this.sortPipe.transform(this.availableFields, 'label');
                }),
                finalize(() => resolve(true)),
                catchError((err: any) => {
                    this.notify.handleErrors(err);
                    return of(false);
                })
            ).subscribe();
        });
    }

    changeModel(event: any) {
        this.selectedModelId = event.value;
        this.http.get('../rest/indexingModels/' + this.selectedModelId).pipe(
            tap((data: any) => {
                this.selectedModelFields = data.indexingModel.fields;
                this.resetFields = this.mainIndexingModelFields.filter(field =>
                    this.selectedModelFields.find(selectedField => selectedField.identifier === field.identifier) === undefined);

                this.sortPipe.transform(this.resetFields, 'label');
            }),
            catchError((err: any) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    isValid() {
        if (this.loading) {
            return false;
        } else if (this.mainIndexingModel.used.length === 0) {
            return true;
        } else {
            return this.selectedModelId !== undefined;
        }
    }

    onSubmit() {
        if (this.mainIndexingModel.used.length === 0) {
            this.http.delete('../rest/indexingModels/' + this.mainIndexingModel.id).pipe(
                tap(() => {
                    this.notify.success(this.translate.instant('lang.indexingModelDeleted'));
                    this.dialogRef.close('ok');
                }),
                catchError((err: any) => {
                    this.notify.handleSoftErrors(err);
                    return of(false);
                })
            ).subscribe();
        } else {
            this.http.request('DELETE', '../rest/indexingModels/' + this.mainIndexingModel.id, { body: { targetId: this.selectedModelId } }).pipe(
                tap(() => {
                    this.notify.success(this.translate.instant('lang.indexingModelDeleted'));
                    this.dialogRef.close('ok');
                }),
                catchError((err: any) => {
                    this.notify.handleSoftErrors(err);
                    return of(false);
                })
            ).subscribe();
        }

    }
}
