import { Component, OnInit, Input, EventEmitter, Output } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { TranslateService } from '@ngx-translate/core';
import { catchError, tap, finalize, map } from 'rxjs/operators';
import { of } from 'rxjs';
import { NotificationService } from '@service/notification/notification.service';
import { FunctionsService } from '@service/functions.service';


@Component({
    selector: 'app-mail-resume',
    templateUrl: 'mail-resume.component.html',
    styleUrls: [
        'mail-resume.component.scss',
    ]
})

export class MailResumeComponent implements OnInit {

    @Input() resId: number = null;
    @Output() goTo = new EventEmitter<string>();

    loading: boolean = true;

    mails: any[] = [];

    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        private notify: NotificationService,
        public functions: FunctionsService
    ) {
    }

    ngOnInit(): void {
        this.loading = true;
        this.loadMails(this.resId);
    }

    loadMails(resId: number) {
        this.loading = true;
        this.http.get(`../rest/externalSummary/${resId}?limit=3`).pipe(
            map((data: any) => {
                data.elementsSend = data.elementsSend.map((elem: any) => {
                    let object = elem.object;
                    let type = elem.type;
                    if (elem.type == 'aknowledgement_receipt' && this.functions.empty(elem.object)) {
                        object = this.translate.instant('lang.ARPaper');
                        type = 'aknowledgement_receipt';
                    } else if (elem.type == 'aknowledgement_receipt' && elem.object.startsWith('[AR]')) {
                        object = this.translate.instant('lang.ARelectronic');
                        type = 'aknowledgement_receipt';
                    }

                    return {
                        object: !this.functions.empty(object) ? object : `<i>${this.translate.instant('lang.emptySubject')}<i>`,
                        send_date: elem.send_date,
                        status: elem.status,
                        userInfo: elem.userInfo,
                        type: type
                    };
                });
                return data;
            }),
            tap((data: any) => {
                this.mails = data.elementsSend;
            }),
            finalize(() => this.loading = false),
            catchError((err: any) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    showMore() {
        this.goTo.emit();
    }
}
