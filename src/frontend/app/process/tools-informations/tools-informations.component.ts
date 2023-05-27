import { HttpClient } from '@angular/common/http';
import { Component, Input, OnInit } from '@angular/core';
import { TranslateService } from '@ngx-translate/core';
import { NotificationService } from '@service/notification/notification.service';
import { of } from 'rxjs';
import { catchError, tap } from 'rxjs/operators';


@Component({
    selector: 'app-tools-informations',
    templateUrl: 'tools-informations.component.html',
    styleUrls: [
        'tools-informations.component.scss'
    ],
})
export class ToolsInformationsComponent implements OnInit {

    @Input() resId: number;

    infoTools: any[] = [
        {
            id: 'history',
            icon: 'fas fa-history',
            label: this.translate.instant('lang.history'),
            display: false,
            count : 0
        },
        {
            id: 'notes',
            icon: 'fas fa-pen-square',
            label: this.translate.instant('lang.notesAlt'),
            display: false,
            count : 0
        },
        {
            id: 'linkedResources',
            icon: 'fas fa-link',
            label: this.translate.instant('lang.links'),
            display: false,
            count : 0
        },
        {
            id: 'emails',
            icon: 'fas fa-envelope',
            label: this.translate.instant('lang.mailsSentAlt'),
            display: false,
            count : 0
        },
        {
            id: 'diffusionList',
            icon: 'fas fa-share-alt',
            label: this.translate.instant('lang.diffusionList'),
            display: false,
            count : 0
        },
        {
            id: 'visaCircuit',
            icon: 'fas fa-list-ol',
            label: this.translate.instant('lang.visaWorkflow'),
            display: false,
            count : 0
        },
        {
            id: 'opinionCircuit',
            icon: 'fas fa-comment-alt',
            label: this.translate.instant('lang.avis'),
            display: false,
            count : 0
        }
    ];
    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        private notify: NotificationService,
    ) { }

    ngOnInit(): void {
        this.loadBadges();
    }

    loadBadges() {
        this.http.get(`../rest/resources/${this.resId}/items`).pipe(
            tap((data: any) => {
                this.infoTools.forEach(element => {
                    element.count = data[element.id] !== undefined ? data[element.id] : 0;
                });
            }),
            catchError((err: any) => {
                this.notify.handleSoftErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    toggleModal(modal: any) {
        modal.display = !modal.display;
    }
}
