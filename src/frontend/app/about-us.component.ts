import { Component, OnInit } from '@angular/core';
import { TranslateService } from '@ngx-translate/core';
import { AppService } from '@service/app.service';
import { environment } from '../environments/environment';
import { catchError, tap } from 'rxjs/operators';
import { HttpClient } from '@angular/common/http';
import { NotificationService } from '@service/notification/notification.service';
import { of } from 'rxjs';
import { MatDialogRef } from '@angular/material/dialog';
import { MatIconRegistry } from '@angular/material/icon';
import { DomSanitizer } from '@angular/platform-browser';

@Component({
    templateUrl: 'about-us.component.html',
    styleUrls: ['about-us.component.css']
})
export class AboutUsComponent implements OnInit {

    applicationVersion: string;
    currentYear: number;


    loading: boolean = false;

    commitHash: string = this.translate.instant('lang.undefined');

    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        private notify: NotificationService,
        public appService: AppService,
        public dialogRef: MatDialogRef<AboutUsComponent>,
        iconReg: MatIconRegistry,
        sanitizer: DomSanitizer,
    ) {
        iconReg.addSvgIcon('maarchBox', sanitizer.bypassSecurityTrustResourceUrl('assets/maarch_box.svg'));
    }

    async ngOnInit() {
        this.applicationVersion = environment.VERSION;
        this.currentYear = new Date().getFullYear();
        this.loading = false;

        await this.loadCommitInformation();
    }

    loadCommitInformation() {
        return new Promise((resolve) => {
            this.http.get('../rest/commitInformation').pipe(
                tap((data: any) => {
                    this.commitHash = data.hash !== null ? data.hash : this.translate.instant('lang.undefined');
                    resolve(true);
                }),
                catchError((err: any) => {
                    this.notify.handleSoftErrors(err);
                    return of(false);
                })
            ).subscribe();
        });
    }
}

