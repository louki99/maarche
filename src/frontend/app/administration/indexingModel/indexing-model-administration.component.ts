import { Component, OnInit, ViewChild } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { TranslateService } from '@ngx-translate/core';
import { NotificationService } from '@service/notification/notification.service';
import { HeaderService } from '@service/header.service';
import { MatDialog } from '@angular/material/dialog';
import { MatSidenav } from '@angular/material/sidenav';
import { AppService } from '@service/app.service';
import { tap, catchError, finalize, exhaustMap, filter } from 'rxjs/operators';
import { SortPipe } from '../../../plugins/sorting.pipe';
import { IndexingFormComponent } from '../../indexation/indexing-form/indexing-form.component';
import { ActivatedRoute, Router } from '@angular/router';
import { of } from 'rxjs';

@Component({
    templateUrl: 'indexing-model-administration.component.html',
    styleUrls: [
        'indexing-model-administration.component.scss',
        '../../indexation/indexing-form/indexing-form.component.scss'
    ],
    providers: [AppService, SortPipe]
})

export class IndexingModelAdministrationComponent implements OnInit {

    @ViewChild('snav2', { static: true }) public sidenavRight: MatSidenav;

    @ViewChild('indexingForm', { static: false }) indexingForm: IndexingFormComponent;

    loading: boolean = true;

    indexingModel: any = {
        id: 0,
        label: '',
        category: 'incoming',
        default: false,
        owner: 0,
        private: false,
        mandatoryFile: false
    };

    indexingModelClone: any;

    indexingModelsCustomFields: any[] = [];

    creationMode: boolean = true;

    categoriesList: any[];

    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        private route: ActivatedRoute,
        private router: Router,
        private notify: NotificationService,
        public dialog: MatDialog,
        private headerService: HeaderService,
        public appService: AppService,
    ) {

    }

    ngOnInit(): void {
        this.route.params.subscribe((params) => {
            if (typeof params['id'] === 'undefined') {
                this.creationMode = true;

                this.headerService.setHeader(this.translate.instant('lang.indexingModelCreation'));

                this.http.get('../rest/categories').pipe(
                    tap((data: any) => {
                        this.categoriesList = data.categories;

                    }),
                    tap((data: any) => {
                        this.loading = false;
                        setTimeout(() => {
                            this.indexingForm.changeCategory(this.indexingModel.category);
                        }, 0);
                    }),
                    catchError((err: any) => {
                        this.notify.handleErrors(err);
                        return of(false);
                    })
                ).subscribe();
                this.indexingModelClone = JSON.parse(JSON.stringify(this.indexingModel));

            } else {
                this.creationMode = false;

                this.http.get('../rest/indexingModels/' + params['id']).pipe(
                    tap((data: any) => {
                        this.indexingModel = data.indexingModel;

                        this.headerService.setHeader(this.translate.instant('lang.indexingModelModification'), this.indexingModel.label);

                        this.indexingModelClone = JSON.parse(JSON.stringify(this.indexingModel));

                    }),
                    exhaustMap(() => this.http.get('../rest/categories')),
                    tap((data: any) => {
                        this.categoriesList = data.categories;
                    }),
                    finalize(() => this.loading = false),
                    catchError((err: any) => {
                        this.notify.handleErrors(err);
                        return of(false);
                    })
                ).subscribe();
            }
        });


    }

    onSubmit() {
        const fields = this.indexingForm.getDatas();
        fields.forEach((element, key) => {
            fields[key].default_value = ['string', 'integer', 'date'].indexOf(fields[key].type) > -1 && fields[key].SQLMode ? null : fields[key].default_value;
            delete fields[key].event;
            delete fields[key].label;
            delete fields[key].system;
            delete fields[key].type;
            delete fields[key].values;
        });
        this.indexingModel.fields = fields;
        this.indexingModel = {...this.indexingModel, allDoctypes: this.indexingForm.allDoctypes};

        if (this.creationMode) {
            this.http.post('../rest/indexingModels', this.indexingModel).pipe(
                tap((data: any) => {
                    this.indexingForm.setModification();
                    this.setModification();
                    this.router.navigate(['/administration/indexingModels']);
                    this.notify.success(this.translate.instant('lang.indexingModelAdded'));
                }),
                finalize(() => this.loading = false),
                catchError((err: any) => {
                    this.notify.handleErrors(err);
                    return of(false);
                })
            ).subscribe();
        } else {
            this.http.put('../rest/indexingModels/' + this.indexingModel.id, this.indexingModel).pipe(
                tap((data: any) => {
                    this.indexingForm.setModification();
                    this.setModification();
                    this.router.navigate(['/administration/indexingModels']);
                    this.notify.success(this.translate.instant('lang.indexingModelUpdated'));
                }),
                finalize(() => this.loading = false),
                catchError((err: any) => {
                    this.notify.handleErrors(err);
                    return of(false);
                })
            ).subscribe();
        }

    }

    isModified() {
        let compare: string = '';
        let compareClone: string = '';

        compare = JSON.stringify(this.indexingModel);
        compareClone = JSON.stringify(this.indexingModelClone);

        if (compare !== compareClone) {
            return true;
        } else {
            return false;
        }
    }

    setModification() {
        this.indexingModelClone = JSON.parse(JSON.stringify(this.indexingModel));
    }

    changeCategory(ev: any) {
        this.indexingForm.changeCategory(ev.value);
    }
}
