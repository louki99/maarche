import { Component, Input, OnInit } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { NotificationService } from '@service/notification/notification.service';
import { TranslateService } from '@ngx-translate/core';
import { FunctionsService } from '@service/functions.service';
import { HeaderService } from '@service/header.service';
import { tap } from 'rxjs/operators';

@Component({
    selector: 'app-editor-option',
    templateUrl: './editor-option.component.html',
    styleUrls: ['./editor-option.component.scss'],
})

export class EditorOptionComponent implements OnInit {

    @Input() docEdition: any;

    editorsList: any;

    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        private notify: NotificationService,
        public functionsService: FunctionsService,
        public headerService: HeaderService,

    ) {
        this.http.get('../rest/documentEditors').pipe(
            tap((data: any) => {
                this.editorsList = data;
            })
        ).subscribe();
    }

    ngOnInit(): void {}

    updateUserPreferences() {
        this.http.put('../rest/currentUser/profile/preferences', { documentEdition: this.docEdition})
            .subscribe(() => {
                this.notify.success(this.translate.instant('lang.modificationSaved'));
                this.headerService.resfreshCurrentUser();
            }, (err) => {
                this.notify.error(err.error.errors);
            });
    }

}
