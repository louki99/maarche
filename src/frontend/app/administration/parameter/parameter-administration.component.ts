import { Component, OnInit } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Router, ActivatedRoute } from '@angular/router';
import { TranslateService } from '@ngx-translate/core';
import { NotificationService } from '@service/notification/notification.service';
import { HeaderService } from '@service/header.service';
import { AppService } from '@service/app.service';

@Component({
    templateUrl: 'parameter-administration.component.html'
})
export class ParameterAdministrationComponent implements OnInit {


    loading: boolean = false;

    parameter: any = {};
    type: string;
    creationMode: boolean;


    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        private route: ActivatedRoute,
        private router: Router,
        private notify: NotificationService,
        private headerService: HeaderService,
        public appService: AppService
    ) { }

    ngOnInit(): void {
        this.loading = true;

        this.route.params.subscribe((params) => {

            if (typeof params['id'] === 'undefined') {
                this.headerService.setHeader(this.translate.instant('lang.parameterCreation'));

                this.creationMode = true;
                this.loading = false;
            } else {

                this.creationMode = false;
                this.http.get('../rest/parameters/' + params['id'])
                    .subscribe((data: any) => {
                        this.parameter = data.parameter;
                        this.headerService.setHeader(this.translate.instant('lang.parameterModification'), this.parameter.id);
                        if (typeof (this.parameter.param_value_int) === 'number') {
                            this.type = 'int';
                        } else if (this.parameter.param_value_date) {
                            this.type = 'date';
                        } else {
                            this.type = 'string';
                        }

                        this.loading = false;
                    }, (err) => {
                        this.notify.handleErrors(err);
                    });
            }
        });
    }

    onSubmit() {
        if (this.type === 'date') {
            this.parameter.param_value_int = null;
            this.parameter.param_value_string = null;

        } else if (this.type === 'int') {

            this.parameter.param_value_date = null;
            this.parameter.param_value_string = null;

        } else if (this.type === 'string') {

            this.parameter.param_value_date = null;
            this.parameter.param_value_int = null;
        }

        if (this.creationMode === true) {
            this.http.post('../rest/parameters', this.parameter)
                .subscribe(() => {
                    this.router.navigate(['administration/parameters']);
                    this.notify.success(this.translate.instant('lang.parameterAdded'));
                }, (err) => {
                    this.notify.error(err.error.errors);
                });
        } else if (this.creationMode === false) {
            this.http.put('../rest/parameters/' + this.parameter.id, this.parameter)
                .subscribe(() => {
                    this.router.navigate(['administration/parameters']);
                    this.notify.success(this.translate.instant('lang.parameterUpdated'));
                }, (err) => {
                    this.notify.error(err.error.errors);
                });
        }
    }
}
