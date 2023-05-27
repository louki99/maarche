import { Component, OnInit, Input, EventEmitter, Output } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { NotificationService } from '@service/notification/notification.service';
import { TranslateService } from '@ngx-translate/core';
import { SortPipe } from '@plugins/sorting.pipe';
import { finalize, catchError, exhaustMap, filter, tap } from 'rxjs/operators';
import { of } from 'rxjs';
import { ConfirmComponent } from '@plugins/modal/confirm.component';
import { MatDialog } from '@angular/material/dialog';
import { AddPrivateIndexingModelModalComponent } from '../private-indexing-model/add-private-indexing-model-modal.component';
import { HeaderService } from '@service/header.service';
import { IndexingFormComponent } from '../indexing-form/indexing-form.component';
import {PrivilegeService} from '@service/privileges.service';

@Component({
    selector: 'app-select-indexing-model',
    templateUrl: './select-indexing-model.component.html',
    styleUrls: ['./select-indexing-model.component.scss', '../indexing-form/indexing-form.component.scss'],
    providers: [SortPipe]
})
export class SelectIndexingModelComponent implements OnInit {

    @Input() defaultIndexingModelId: number = null;
    @Input() indexingModels: any = [];
    @Input() indexingForm: IndexingFormComponent;
    @Input() adminMode: boolean = false;

    @Output() afterListModelsLoaded = new EventEmitter<any>();
    @Output() afterSelectedListModel = new EventEmitter<any>();

    loading: boolean = true;


    currentIndexingModel: any = {};

    constructor(
        public translate: TranslateService,
        private http: HttpClient,
        public headerService: HeaderService,
        private notify: NotificationService,
        private sortPipe: SortPipe,
        private dialog: MatDialog,
        public privilegeService: PrivilegeService
    ) { }

    ngOnInit(): void {
        this.getIndexingModelList();
    }

    getIndexingModelList() {
        this.http.get('../rest/indexingModels').pipe(
            tap((data: any) => {
                this.indexingModels = data.indexingModels;
                if (this.indexingModels.length > 0) {
                    this.currentIndexingModel = this.defaultIndexingModelId === null ? this.indexingModels.filter((model: any) => model.default === true)[0] : this.indexingModels.filter((model: any) => model.id === this.defaultIndexingModelId)[0];

                    if (this.currentIndexingModel === undefined) {
                        this.currentIndexingModel = this.indexingModels[0];
                        this.notify.error(this.translate.instant('lang.noDefaultIndexingModel'));
                    }
                    this.loadIndexingModelsList();
                }
            }),
            finalize(() => this.loading = false),
            catchError((err: any) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    loadIndexingModelsList() {
        const tmpIndexingModels: any[] = this.sortPipe.transform(this.indexingModels.filter((elem: any) => elem.master === null), 'label');
        const privateTmpIndexingModels: any[] = this.sortPipe.transform(this.indexingModels.filter((elem: any) => elem.master !== null), 'label');
        this.indexingModels = [];
        tmpIndexingModels.forEach(indexingModel => {
            this.indexingModels.push(indexingModel);
            privateTmpIndexingModels.forEach(privateIndexingModel => {
                if (privateIndexingModel.master === indexingModel.id) {
                    this.indexingModels.push(privateIndexingModel);
                }
            });
        });
        this.afterListModelsLoaded.emit(this.currentIndexingModel);
    }

    resetIndexingModel() {
        this.currentIndexingModel = this.indexingModels.filter((model: any) => model.default === true)[0];
        this.afterSelectedListModel.emit(this.currentIndexingModel);
    }

    selectIndexingModel(indexingModel: any) {
        this.afterSelectedListModel.emit({indexingModel: indexingModel, prevCategory: JSON.parse(JSON.stringify(this.currentIndexingModel.category))});
        this.currentIndexingModel = indexingModel;
    }

    getCurrentIndexingModel() {
        return this.currentIndexingModel;
    }

    getIndexingModels() {
        return this.indexingModels;
    }

    savePrivateIndexingModel() {
        const fields = JSON.parse(JSON.stringify(this.indexingForm.getDatas()));
        fields.forEach((element: any, key: any) => {
            delete fields[key].event;
            delete fields[key].label;
            delete fields[key].system;
            delete fields[key].type;
            delete fields[key].values;
        });

        const privateIndexingModel = {
            category: this.indexingForm.getCategory(),
            label: '',
            owner: this.headerService.user.id,
            private: true,
            fields: fields,
            master: this.currentIndexingModel.master !== null ? this.currentIndexingModel.master : this.currentIndexingModel.id
        };

        const masterIndexingModel = this.indexingModels.filter((indexingModel: any) => indexingModel.id === privateIndexingModel.master)[0];
        const dialogRef = this.dialog.open(
            AddPrivateIndexingModelModalComponent,
            {
                panelClass: 'maarch-modal',
                autoFocus: true,
                disableClose: true,
                data: {
                    indexingModel: privateIndexingModel,
                    masterIndexingModel: masterIndexingModel
                }
            }
        );

        dialogRef.afterClosed().pipe(
            filter((data: any) => data !== undefined),
            tap((data) => {
                this.indexingModels.push(data.indexingModel);
                this.currentIndexingModel = this.indexingModels.filter((indexingModel: any) => indexingModel.id === data.indexingModel.id)[0];
                this.loadIndexingModelsList();
            }),
            catchError((err: any) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    deletePrivateIndexingModel(id: number, index: number) {
        const dialogRef = this.dialog.open(
            ConfirmComponent,
            {
                panelClass: 'maarch-modal',
                autoFocus: false,
                disableClose: true,
                data: {
                    title: this.translate.instant('lang.delete'),
                    msg: this.translate.instant('lang.confirmAction')
                }
            }
        );

        dialogRef.afterClosed().pipe(
            filter((data: string) => data === 'ok'),
            exhaustMap(() => this.http.delete(`../rest/indexingModels/${id}`)),
            tap(() => {
                this.indexingModels.splice(index, 1);
                this.notify.success(this.translate.instant('lang.indexingModelDeleted'));
            }),
            catchError((err: any) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }
}
