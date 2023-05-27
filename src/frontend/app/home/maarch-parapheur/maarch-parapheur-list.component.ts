import { Component, OnInit, Output, EventEmitter, AfterViewInit } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { TranslateService } from '@ngx-translate/core';
import { MatDialog } from '@angular/material/dialog';
import { MatTableDataSource } from '@angular/material/table';
import { NotificationService } from '@service/notification/notification.service';
import { HeaderService } from '@service/header.service';
import { finalize } from 'rxjs/operators';

export interface MPDocument {
    id: string;
    title: string;
    reference: string;
    mode: string;
    owner: boolean;
}

@Component({
    selector: 'app-maarch-parapheur-list',
    templateUrl: 'maarch-parapheur-list.component.html',
    styleUrls: ['maarch-parapheur-list.component.scss'],
})
export class MaarchParapheurListComponent implements OnInit, AfterViewInit {

    @Output() triggerEvent = new EventEmitter<string>();

    loading: boolean = true;

    userList: MPDocument[] = [];

    dataSource: MatTableDataSource<MPDocument>;

    displayedColumns: string[] = ['id', 'title'];
    maarchParapheurUrl: string = '';

    constructor(public translate: TranslateService, public http: HttpClient, public dialog: MatDialog, private notify: NotificationService, private headerService: HeaderService) {
        this.dataSource = new MatTableDataSource(this.userList);
    }

    ngOnInit(): void {
        this.loading = true;
    }

    ngAfterViewInit(): void {
        this.http.get('../rest/home/maarchParapheurDocuments')
            .pipe(
                finalize(() => this.loading = false)
            )
            .subscribe((data: any) => {
                setTimeout(() => {
                    this.dataSource = new MatTableDataSource(data.documents);
                    this.maarchParapheurUrl = data.url;
                    this.triggerEvent.emit(data.count.current);
                }, 0);
            }, (err: any) => {
                this.notify.handleErrors(err);
            });
    }

    goTo(row: any) {
        window.open(this.maarchParapheurUrl + '/dist/documents/' + row.id, '_blank');
    }
}
