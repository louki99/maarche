import { HttpClient } from '@angular/common/http';
import { Component, Inject, OnInit } from '@angular/core';
import { MAT_DIALOG_DATA, MatDialogRef } from '@angular/material/dialog';
import { TranslateService } from '@ngx-translate/core';
import { FunctionsService } from '@service/functions.service';
import { of } from 'rxjs';
import { catchError, finalize, tap } from 'rxjs/operators';

@Component({
    templateUrl: 'check-sae-interconnection.component.html',
    styleUrls: ['check-sae-interconnection.component.scss'],
})

export class CheckSaeInterconnectionComponent implements OnInit {

    loading: boolean = true;
    hasError: boolean = false;
    isSae: boolean = false;
    archivalError: string = '';
    result: string  = this.translate.instant('lang.loadingTest');
    urlSAEService: string = '';


    constructor (
        public http: HttpClient,
        public translate: TranslateService,
        public dialogRef: MatDialogRef<CheckSaeInterconnectionComponent>,
        public functions: FunctionsService,
        @Inject(MAT_DIALOG_DATA) public data: any,
    ) { }

    ngOnInit() {
        this.loading = true;
        this.urlSAEService = !this.functions.empty(this.data.urlSAEService) ? this.data.urlSAEService : '';
        setTimeout(() => {
            this.checkInterconnection();
        }, 500);
    }

    checkInterconnection() {
        this.http.get('../rest/archival/retentionRules').pipe(
            tap(() => {
                this.loading = false;
                this.hasError = false;
                this.result = this.translate.instant('lang.interconnectionSuccess');
            }),
            catchError((err: any) => {
                this.hasError = true;
                this.loading = false;
                this.archivalError = err.error.errors;
                const index: number = this.archivalError.indexOf(':');
                const getError: string = this.archivalError.slice(index + 1, this.archivalError.length).replace(/^[\s]/, '');
                this.archivalError = !this.functions.empty(getError) ? `(${getError})` : '';
                this.result = this.translate.instant('lang.interconnectionFailed') + ` ${this.urlSAEService} ` + this.archivalError;
                return of(false);
            })
        ).subscribe();
    }
}
