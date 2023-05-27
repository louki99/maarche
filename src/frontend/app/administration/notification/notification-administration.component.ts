import { Component, OnInit, ViewChild, TemplateRef, ViewContainerRef } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Router, ActivatedRoute } from '@angular/router';
import { TranslateService } from '@ngx-translate/core';
import { NotificationService } from '@service/notification/notification.service';
import { HeaderService } from '@service/header.service';
import { AppService } from '@service/app.service';
import { SortPipe } from '@plugins/sorting.pipe';
import { catchError, tap } from 'rxjs/operators';
import { of } from 'rxjs';

@Component({
    templateUrl: 'notification-administration.component.html',
    providers: [SortPipe]
})
export class NotificationAdministrationComponent implements OnInit {

    @ViewChild('adminMenuTemplate', { static: true }) adminMenuTemplate: TemplateRef<any>;

    creationMode: boolean;
    notification: any = {
        diffusionType_label: null
    };

    loading: boolean = false;

    events: any[] = [];
    templatesMailSend: any[] = [];
    diffusionTypes: any[] = [];
    groups: any[] = [];
    users: any[] = [];
    entities: any[] = [];
    statuses: any[] = [];

    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        private route: ActivatedRoute,
        private router: Router,
        private notify: NotificationService,
        private headerService: HeaderService,
        public appService: AppService,
        private viewContainerRef: ViewContainerRef,
        private sortPipe: SortPipe
    ) { }

    ngOnInit(): void {
        this.loading = true;

        this.headerService.injectInSideBarLeft(this.adminMenuTemplate, this.viewContainerRef, 'adminMenu');

        this.route.params.subscribe((params: any) => {

            if (typeof params['identifier'] === 'undefined') {
                this.headerService.setHeader(this.translate.instant('lang.notificationCreation'));

                this.creationMode = true;
                this.http.get('../rest/administration/notifications/new')
                    .pipe(
                        tap((data: any) => {
                            this.formatData(data);
                            this.loading = false;
                        }),
                        catchError((err: any) => {
                            this.notify.handleSoftErrors(err);
                            return of(false);
                        })
                    ).subscribe();
            } else {

                this.creationMode = false;
                this.http.get('../rest/notifications/' + params['identifier'])
                    .pipe(
                        tap((data: any) => {
                            this.headerService.setHeader(this.translate.instant('lang.notificationModification'), data.notification.description);
                            this.formatData(data);
                            this.loading = false;
                        }),
                        catchError((err: any) => {
                            this.notify.handleSoftErrors(err);
                            return of(false);
                        })
                    ).subscribe();
            }
        });
    }

    formatData(data: any) {
        this.notification = data.notification;
        this.notification.attachfor_properties = [];

        this.getEventsList(data.notification.data.event);
        this.getTemplatesMailSend(data.notification.data.template);
        this.getDiffusionTypes(data.notification.data.diffusionType);
        this.getGroups(data.notification.data.groups);
        this.getUsers(data.notification.data.users);
        this.getEntities(data.notification.data.entities);
        this.getStatuses(data.notification.data.status);
        delete this.notification.data;
    }

    getStatuses(statuses: any[]) {
        this.statuses = statuses.map((status: any) => ({
            id: status.id,
            label: status.label_status
        }));
        this.statuses = this.sortPipe.transform(this.statuses, 'label');
    }

    getEntities(entities: any[]) {
        this.entities = entities.map((entity: any) => ({
            id: entity.entity_id,
            label: entity.entity_label
        }));
        this.entities = this.sortPipe.transform(this.entities, 'label');
    }


    getUsers(users: any[]) {
        this.users = users.map((user: any) => ({
            id: user.id,
            label: user.label
        }));
        this.users = this.sortPipe.transform(this.users, 'label');
    }

    getGroups(groups: any[]) {
        this.groups = groups.map((group: any) => ({
            id: group.group_id,
            label: group.group_desc
        }));
        this.groups = this.sortPipe.transform(this.groups, 'label');
    }


    getEventsList(events: any[]) {
        this.events.push({
            id: 'notificationEvent',
            label: this.translate.instant('lang.NotificationEvent'),
            title: this.translate.instant('lang.NotificationEvent'),
            disabled: true,
            isTitle: true
        });

        let filteredEvents = events.filter((event: any) => isNaN(event.id)).map((event: any) => ({
            id: event.id,
            label: '&nbsp;&nbsp;&nbsp;&nbsp;' + event.label_action,
            title: event.label_action
        }));

        filteredEvents = this.sortPipe.transform(filteredEvents, 'label');

        this.events = [...this.events, ...filteredEvents];

        this.events.push({
            id: 'triggerAction',
            label: this.translate.instant('lang.triggerAction'),
            title: this.translate.instant('lang.triggerAction'),
            disabled: true,
            isTitle: true
        });

        filteredEvents = events.filter((event: any) => !isNaN(event.id)).map((event: any) => ({
            id: event.id,
            label: '&nbsp;&nbsp;&nbsp;&nbsp;' + event.label_action,
            title: event.label_action
        }));

        filteredEvents = this.sortPipe.transform(filteredEvents, 'label');

        this.events = [...this.events, ...filteredEvents];
    }

    getTemplatesMailSend(templates: any[]) {
        this.templatesMailSend = templates.map((template: any) => ({
            id: template.template_id,
            label: template.template_label
        }));
        this.templatesMailSend = this.sortPipe.transform(this.templatesMailSend, 'label');
    }

    getDiffusionTypes(diffTypes: any[]) {
        this.diffusionTypes.push({
            id: 'memberUserDest',
            label: this.translate.instant('lang.memberUserDest'),
            title: this.translate.instant('lang.memberUserDest'),
            disabled: true,
            isTitle: true
        });

        let filteredDiffTypes = diffTypes.filter((type: any) => type.id === 'dest_user').map((type: any) => ({
            id: type.id,
            label: '&nbsp;&nbsp;&nbsp;&nbsp;' + type.label,
            title: type.label
        }));

        filteredDiffTypes = this.sortPipe.transform(filteredDiffTypes, 'label');

        this.diffusionTypes = [...this.diffusionTypes, ...filteredDiffTypes];

        this.diffusionTypes.push({
            id: 'memberUsersCopy',
            label: this.translate.instant('lang.memberUsersCopy'),
            title: this.translate.instant('lang.memberUsersCopy'),
            disabled: true,
            isTitle: true
        });

        filteredDiffTypes = diffTypes.filter((type: any) => type.id === 'copy_list').map((type: any) => ({
            id: type.id,
            label: '&nbsp;&nbsp;&nbsp;&nbsp;' + type.label,
            title: type.label
        }));

        filteredDiffTypes = this.sortPipe.transform(filteredDiffTypes, 'label');

        this.diffusionTypes = [...this.diffusionTypes, ...filteredDiffTypes];

        this.diffusionTypes.push({
            id: 'memberAllUsers',
            label: this.translate.instant('lang.memberAllUsers'),
            title: this.translate.instant('lang.memberAllUsers'),
            disabled: true,
            isTitle: true
        });

        filteredDiffTypes = diffTypes.filter((type: any) => type.id === 'group' || (type.id === 'entity' && type.event_id !== 'baskets') || (type.id === 'user' && type.event_id !== 'baskets')).map((type: any) => ({
            id: type.id,
            label: '&nbsp;&nbsp;&nbsp;&nbsp;' + type.label,
            title: type.label
        }));

        filteredDiffTypes = this.sortPipe.transform(filteredDiffTypes, 'label');

        this.diffusionTypes = [...this.diffusionTypes, ...filteredDiffTypes];

        this.diffusionTypes.push({
            id: 'others',
            label: this.translate.instant('lang.others'),
            title: this.translate.instant('lang.others'),
            disabled: true,
            isTitle: true
        });

        filteredDiffTypes = diffTypes.filter((type: any) => type.id !== 'group' && type.id !== 'entity' && type.id !== 'user' && type.id !== 'copy_list' && type.id !== 'group' && type.id !== 'dest_user').map((type: any) => ({
            id: type.id,
            label: '&nbsp;&nbsp;&nbsp;&nbsp;' + type.label,
            title: type.label
        }));

        filteredDiffTypes = this.sortPipe.transform(filteredDiffTypes, 'label');

        this.diffusionTypes = [...this.diffusionTypes, ...filteredDiffTypes];

    }

    createScript() {
        this.http.post('../rest/scriptNotification', this.notification)
            .subscribe((data: any) => {
                this.notification.scriptcreated = data;
                this.notify.success(this.translate.instant('lang.scriptCreated'));
            }, (err) => {
                this.notify.error(err.error.errors);
            });
    }

    onSubmit() {
        if (this.creationMode) {
            this.notification.is_enabled = 'Y';
            this.http.post('../rest/notifications', this.notification)
                .pipe(
                    tap(() => {
                        this.router.navigate(['/administration/notifications']);
                        this.notify.success(this.translate.instant('lang.notificationAdded'));
                    }),
                    catchError((err: any) => {
                        this.notify.handleSoftErrors(err);
                        return of(false);
                    })
                ).subscribe();
        } else {
            this.http.put('../rest/notifications/' + this.notification.notification_sid, this.notification)
                .pipe(
                    tap(() => {
                        this.router.navigate(['/administration/notifications']);
                        this.notify.success(this.translate.instant('lang.notificationUpdated'));
                    }),
                    catchError((err: any) => {
                        this.notify.handleSoftErrors(err);
                        return of(false);
                    })
                ).subscribe();
        }
    }

    toggleNotif() {
        if (this.notification.is_enabled === 'Y') {
            this.notification.is_enabled = 'N';
        } else {
            this.notification.is_enabled = 'Y';
        }
        this.http.put('../rest/notifications/' + this.notification.notification_sid, this.notification)
            .pipe(
                tap(() => {
                    this.notify.success(this.translate.instant('lang.notificationUpdated'));
                }),
                catchError((err: any) => {
                    this.notify.handleSoftErrors(err);
                    return of(false);
                })
            ).subscribe();
    }

    updateDiffusionType(type: any) {
        this.notification.diffusion_properties = [];
    }
}
