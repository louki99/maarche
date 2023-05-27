import { Component, Input, OnInit, ViewChild } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { NotificationService } from '@service/notification/notification.service';
import { TranslateService } from '@ngx-translate/core';
import { FunctionsService } from '@service/functions.service';
import { HeaderService } from '@service/header.service';
import { MatTableDataSource } from '@angular/material/table';
import { MatPaginator } from '@angular/material/paginator';
import { MatSort } from '@angular/material/sort';

@Component({
    selector: 'app-profile-history',
    templateUrl: './history.component.html',
    styleUrls: ['./history.component.scss'],
})

export class ProfileHistoryComponent implements OnInit {
    @ViewChild('paginatorHistory', { static: false }) paginatorHistory: MatPaginator;
    @ViewChild('tableHistorySort', { static: false }) sortHistory: MatSort;

    displayedColumns = ['event_date', 'record_id', 'info'];

    dataSource: any;
    histories: any;

    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        private notify: NotificationService,
        private functions: FunctionsService,
        public headerService: HeaderService,

    ) {}

    ngOnInit(): void {
        this.getHistories();
    }

    getHistories() {
        this.http.get('../rest/history/users/' + this.headerService.user.id)
            .subscribe((data: any) => {
                this.histories = data.histories;
                this.dataSource = new MatTableDataSource(this.histories);
                this.dataSource.sortingDataAccessor = this.functions.listSortingDataAccessor;
                this.dataSource.paginator = this.paginatorHistory;
                this.dataSource.sort = this.sortHistory;
            }, (err) => {
                this.notify.error(err.error.errors);
            });
    }

    applyFilter(filterValue: string) {
        filterValue = filterValue.trim(); // Remove whitespace
        filterValue = filterValue.toLowerCase(); // MatTableDataSource defaults to lowercase matches
        this.dataSource.filter = filterValue;
    }
}
