import { Component, OnInit } from '@angular/core';
import { MatDialog } from '@angular/material/dialog';
import { DevLangComponent } from './dev-lang.component';
import { HttpClient } from '@angular/common/http';
import { NotificationService } from '@service/notification/notification.service';
import { of } from 'rxjs';
import { FunctionsService } from '@service/functions.service';
import { catchError, filter, tap } from 'rxjs/operators';

@Component({
    selector: 'app-dev-tool',
    templateUrl: 'dev-tool.component.html',
    styleUrls: ['dev-tool.component.scss'],
})
export class DevToolComponent implements OnInit {

    allLang: any;
    countMissingLang = 0;

    constructor(
        private notify: NotificationService,
        public dialog: MatDialog,
        public http: HttpClient,
        private functionsService: FunctionsService
    ) { }

    ngOnInit(): void {
        this.getLangs();
    }

    openLangTool() {
        const dialogRef =  this.dialog.open(DevLangComponent, {
            panelClass: 'maarch-modal',
            height: '99%',
            width: '80%',
            data: {
                countMissingLang : this.countMissingLang
            }
        });

        dialogRef.afterClosed().pipe(
            filter((data: string) => !this.functionsService.empty(data)),
            tap((data: any) => {
                this.countMissingLang = data;
            }),
            catchError((err: any) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    getLangs() {
        this.http.get('../rest/languages').pipe(
            tap((data: any) => {
                this.allLang = data.langs;
                Object.keys(this.allLang).forEach(langName => {
                    this.countMissingLang += Object.keys(this.allLang.fr).filter((keyLang: any) => Object.keys(this.allLang[langName]).indexOf(keyLang) === -1).length;
                });
            }),
            catchError((err: any) => {
                this.notify.handleSoftErrors(err);
                return of(false);
            })
        ).subscribe();
    }

}
