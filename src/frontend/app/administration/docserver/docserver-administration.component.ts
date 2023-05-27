import { Component, OnInit, ViewChild } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Router } from '@angular/router';
import { TranslateService } from '@ngx-translate/core';
import { NotificationService } from '@service/notification/notification.service';
import { MatPaginator } from '@angular/material/paginator';
import { MatSort } from '@angular/material/sort';
import { HeaderService } from '@service/header.service';
import { AppService } from '@service/app.service';

@Component({
    templateUrl: 'docserver-administration.component.html'
})

export class DocserverAdministrationComponent implements OnInit {

    @ViewChild(MatPaginator, { static: false }) paginator: MatPaginator;
    @ViewChild(MatSort, { static: false }) sort: MatSort;

    loading: boolean = false;
    dataSource: any;

    docserver: any = { coll_id: 'letterbox_coll', docserver_type_id: 'DOC', limitSizeFormatted: '50' };
    docserversTypes: any = [];

    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        private router: Router,
        private notify: NotificationService,
        private headerService: HeaderService,
        public appService: AppService
    ) { }

    ngOnInit(): void {
        this.headerService.setHeader(this.translate.instant('lang.docserverCreation'));

        this.loading = true;

        this.http.get('../rest/docserverTypes')
            .subscribe((data: any) => {
                this.docserversTypes = data.docserverTypes;
                this.loading = false;
            });
    }


    onSubmit(docserver: any) {
        docserver.size_limit_number = docserver.limitSizeFormatted * 1000000000;
        this.http.post('../rest/docservers', docserver)
            .subscribe((data: any) => {
                this.notify.success(this.translate.instant('lang.docserverAdded'));
                this.router.navigate(['/administration/docservers/']);
            }, (err) => {
                this.notify.error(err.error.errors);
            });
    }
}
