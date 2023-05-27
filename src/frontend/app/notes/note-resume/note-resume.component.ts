import { Component, OnInit, Input, EventEmitter, Output } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { TranslateService } from '@ngx-translate/core';
import { catchError, tap, finalize } from 'rxjs/operators';
import { of } from 'rxjs';
import { NotificationService } from '@service/notification/notification.service';


@Component({
    selector: 'app-note-resume',
    templateUrl: 'note-resume.component.html',
    styleUrls: [
        'note-resume.component.scss',
    ]
})

export class NoteResumeComponent implements OnInit {

    @Input() resId: number = null;
    @Output() goTo = new EventEmitter<string>();

    loading: boolean = true;

    notes: any[] = [];

    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        private notify: NotificationService,
    ) {
    }

    ngOnInit(): void {
        this.loading = true;
        this.loadNotes(this.resId);
    }

    loadNotes(resId: number) {
        this.loading = true;
        this.http.get(`../rest/resources/${resId}/notes?limit=3`).pipe(
            tap((data: any) => {
                this.notes = data.notes;
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
