import { Component, OnInit, AfterViewInit, Input } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { TranslateService } from '@ngx-translate/core';
import { AppService } from '@service/app.service';
import { DashboardService } from '@appRoot/home/dashboard/dashboard.service';
import { Router } from '@angular/router';

@Component({
    selector: 'app-tile-view-summary',
    templateUrl: 'tile-view-summary.component.html',
    styleUrls: ['tile-view-summary.component.scss'],
})
export class TileViewSummaryComponent implements OnInit, AfterViewInit {

    @Input() countResources: any[];
    @Input() icon: string = '';
    @Input() resourceLabel: string = '';
    @Input() route: string = null;
    @Input() tile: any;

    constructor(
        private router: Router,
        public translate: TranslateService,
        public http: HttpClient,
        public appService: AppService,
        private dashboardService: DashboardService,
    ) { }

    ngOnInit(): void { }

    ngAfterViewInit(): void { }

    goTo() {
        const data = { ...this.tile.parameters, ...this.tile };
        delete data.parameters;
        const link = this.dashboardService.getFormatedRoute(this.route, data);
        if (link) {
            const regex = /http[.]*/g;
            if (link.route.match(regex) === null) {
                this.router.navigate([link.route], { queryParams: link.params });
            } else {
                window.open(link.route, '_blank');
            }
        }
    }
}
