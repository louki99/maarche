import { Component, OnInit, ViewChildren, QueryList } from '@angular/core';
import { UntypedFormBuilder, UntypedFormGroup, Validators } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { NotificationService } from '@service/notification/notification.service';
import { of } from 'rxjs';
import { TranslateService } from '@ngx-translate/core';
import { catchError, tap } from 'rxjs/operators';
import { FunctionsService } from '@service/functions.service';

@Component({
    selector: 'app-prerequisite',
    templateUrl: './prerequisite.component.html',
    styleUrls: ['./prerequisite.component.scss']
})
export class PrerequisiteComponent implements OnInit {

    @ViewChildren('packageItem') packageItem: QueryList<any>;

    stepFormGroup: UntypedFormGroup;

    prerequisites: any = {};

    packagesList: any = {
        general: [
            {
                label: 'phpVersionValid',
                required: true
            },
            {
                label: 'writable',
                required: true
            },
        ],
        tools: [
            {
                label: 'unoconv',
                required: true
            },
            {
                label: 'netcatOrNmap',
                required: true
            },
            {
                label: 'pgsql',
                required: true
            },
            {
                label: 'curl',
                required: true
            },
            {
                label: 'zip',
                required: true
            },
            {
                label: 'wkhtmlToPdf',
                required: true
            },
            {
                label: 'imagick',
                required: true
            },

        ],
        phpExtensions: [
            {
                label: 'fileinfo',
                required: true
            }, {
                label: 'pdoPgsql',
                required: true
            },
            {
                label: 'gd',
                required: true
            },
            {
                label: 'mbstring',
                required: true
            },
            {
                label: 'json',
                required: true
            },
            {
                label: 'gettext',
                required: true
            },
            {
                label: 'xml',
                required: true
            },
        ],
        phpConfiguration: [
            {
                label: 'errorReporting',
                required: true
            },
            {
                label: 'displayErrors',
                required: true
            }
        ],
    };

    docMaarchUrl: string = this.functionsService.getDocBaseUrl() + '/guat/guat_prerequisites/home.html';

    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        private notify: NotificationService,
        private _formBuilder: UntypedFormBuilder,
        private functionsService: FunctionsService
    ) { }

    ngOnInit(): void {
        this.stepFormGroup = this._formBuilder.group({
            firstCtrl: ['', Validators.required]
        });
        this.getStepData();
    }

    getStepData() {
        this.http.get('../rest/installer/prerequisites').pipe(
            tap((data: any) => {
                this.prerequisites = data.prerequisites;
                Object.keys(this.packagesList).forEach(group => {
                    this.packagesList[group].forEach((item: any, key: number) => {
                        this.packagesList[group][key].state = this.prerequisites[this.packagesList[group][key].label] ? 'ok' : 'ko';
                        if (this.packagesList[group][key].label === 'phpVersionValid') {
                            this.translate.setTranslation(this.translate.getDefaultLang(), {
                                lang: {
                                    install_phpVersionValid_desc: this.translate.instant('lang.currentVersion') + ' : ' + this.prerequisites['phpVersion']
                                }
                            }, true);
                        }
                    });
                });
                this.stepFormGroup.controls['firstCtrl'].setValue(this.checkStep());
                this.stepFormGroup.controls['firstCtrl'].markAsUntouched();
            }),
            catchError((err: any) => {
                this.notify.handleSoftErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    initStep() {
        let i = 0;
        Object.keys(this.packagesList).forEach(group => {
            this.packagesList[group].forEach((item: any, key: number) => {
                if (this.packagesList[group][key].state === 'ko') {
                    this.packageItem.toArray().filter((itemKo: any) => itemKo._elementRef.nativeElement.id === this.packagesList[group][key].label)[0].toggle();
                }
                i++;
            });
        });
    }

    checkStep() {
        let state = 'success';
        Object.keys(this.packagesList).forEach((group: any) => {
            this.packagesList[group].forEach((item: any) => {
                if (item.state === 'ko') {
                    state = '';
                }
            });
        });
        return state;
    }

    isValidStep() {
        return this.stepFormGroup === undefined ? false : this.stepFormGroup.controls['firstCtrl'].value === 'success';
    }

    getFormGroup() {
        return this.stepFormGroup;
    }

    getInfoToInstall(): any[] {
        return [];
    }
}
