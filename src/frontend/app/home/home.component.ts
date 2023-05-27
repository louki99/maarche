import { Component, OnInit, AfterViewInit } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { TranslateService } from '@ngx-translate/core';
import { MatDialog } from '@angular/material/dialog';
import { NotificationService } from '@service/notification/notification.service';
import { HeaderService } from '@service/header.service';
import { AppService } from '@service/app.service';
import { Router } from '@angular/router';
import { FeatureTourService } from '@service/featureTour.service';
import { DomSanitizer } from '@angular/platform-browser';

declare let $: any;

@Component({
    templateUrl: 'home.component.html',
    styleUrls: ['home.component.scss']
})
export class HomeComponent implements OnInit, AfterViewInit {


    loading: boolean = false;

    homeData: any;
    homeMessage: any;

    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        public dialog: MatDialog,
        private notify: NotificationService,
        private headerService: HeaderService,
        public appService: AppService,
        private router: Router,
        private featureTourService: FeatureTourService,
        private sanitizer: DomSanitizer
    ) { }

    ngOnInit(): void {
        this.headerService.setHeader(this.translate.instant('lang.home'));

        this.http.get('../rest/home')
            .subscribe((data: any) => {
                this.homeData = data;
                this.homeMessage = this.sanitizer.bypassSecurityTrustHtml(data['homeMessage']);
            });
    }

    ngAfterViewInit(): void {
        if (!this.featureTourService.isComplete()) {
            this.featureTourService.init();
        }
    }
}
