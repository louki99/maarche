import { Component, Input, OnInit } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { TranslateService } from '@ngx-translate/core';
import { NotificationService } from '@service/notification/notification.service';
import { tap, finalize, catchError } from 'rxjs/operators';
import { ContactService } from '@service/contact.service';
import { FunctionsService } from '@service/functions.service';
import { of } from 'rxjs';

@Component({
    selector: 'app-contact-resource',
    templateUrl: 'contact-resource.component.html',
    styleUrls: ['contact-resource.component.scss'],
    providers: [ContactService]
})
export class ContactResourceComponent implements OnInit {

    /**
     * Ressource identifier to load contact List
     */
    @Input() resId: number = null;

    /**
      * [Filter to load specific contact Type]
      * use with @resId
      */
    @Input() mode: 'recipients' | 'senders' = 'recipients';

    loading: boolean = true;

    contacts: any = [];

    customFields: any[] = [];

    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        private notify: NotificationService,
        private contactService: ContactService,
        private functionsService: FunctionsService
    ) { }

    async ngOnInit(): Promise<void> {

        await this.getCustomFields();

        if (this.resId !== null) {
            this.loadContactsOfResource(this.resId, this.mode);
        }
    }

    getCustomFields() {
        return new Promise((resolve, reject) => {
            this.http.get('../rest/contactsCustomFields').pipe(
                tap((data: any) => {
                    this.customFields = data.customFields.map((custom: any) => ({
                        id: custom.id,
                        label: custom.label
                    }));
                    resolve(true);
                })
            ).subscribe();
        });
    }

    loadContactsOfResource(resId: number, mode: string) {
        this.http.get(`../rest/resources/${resId}/contacts?type=${mode}`).pipe(
            tap((data: any) => {
                this.contacts = data.contacts.map((contact: any) => ({
                    ...contact,
                    civility: this.contactService.formatCivilityObject(contact.civility),
                    fillingRate: this.contactService.formatFillingObject(contact.fillingRate),
                    customFields: !this.functionsService.empty(contact.customFields) ? this.formatCustomField(contact.customFields) : [],
                }));
            }),
            finalize(() => this.loading = false),
            catchError((err: any) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    formatCustomField(data: any) {
        const arrCustomFields: any[] = [];

        Object.keys(data).forEach(element => {
            arrCustomFields.push({
                label: this.customFields.filter(custom => custom.id == element).length > 0 ? this.customFields.filter(custom => custom.id == element)[0].label : element,
                value: data[element]
            });
        });

        return arrCustomFields;
    }
}
