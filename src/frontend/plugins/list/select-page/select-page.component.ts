import { Component, Input, OnInit } from '@angular/core';
import { MatPaginator } from '@angular/material/paginator';

@Component({
    selector: 'app-select-page',
    templateUrl: 'select-page.component.html',
    styleUrls: ['select-page.component.scss'],
})
export class SelectPageComponent implements OnInit {

    @Input() paginator: MatPaginator;

    constructor() { }

    ngOnInit() { }

    counter(i: number) {
        return new Array(i);
    }

    goToPage(page: number) {
        this.paginator.pageIndex = page;
        this.paginator.page.next({
            pageIndex: page,
            pageSize: this.paginator.pageSize,
            length: this.paginator.length
        });
    }

}
