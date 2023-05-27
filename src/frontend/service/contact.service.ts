import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { TranslateService } from '@ngx-translate/core';
import { tap, catchError, map } from 'rxjs/operators';
import { of } from 'rxjs';
import { FunctionsService } from './functions.service';
import { Router } from '@angular/router';

@Injectable()
export class ContactService {

    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        public functions: FunctionsService,
        public router: Router
    ) { }

    getAdminMenu() {
        let route: any = this.router.url.split('?')[0].split('/');
        route = route[route.length - 1];
        return [
            {
                icon: 'fa fa-book',
                route: '/administration/contacts',
                label: this.translate.instant('lang.contactsList'),
                current: route === 'contacts'
            },
            {
                icon: 'fa fa-code',
                route: '/administration/contacts/contactsCustomFields',
                label: this.translate.instant('lang.customFieldsAdmin'),
                current: route === 'contactsCustomFields'
            },
            {
                icon: 'fa fa-cog',
                route: '/administration/contacts/contacts-parameters',
                label: this.translate.instant('lang.contactsParameters'),
                current: route === 'contacts-parameters'
            },
            {
                icon: 'fas fa-atlas',
                route: '/administration/contacts/contacts-groups',
                label: this.translate.instant('lang.contactsGroups'),
                current: route === 'contacts-groups'
            },
            {
                icon: 'fas fa-magic',
                route: '/administration/contacts/duplicates',
                label: this.translate.instant('lang.duplicatesContactsAdmin'),
                current: route === 'duplicates'
            },
        ];
    }

    goTo(route: string) {
        this.router.navigate([route]);
    }

    getFillingColor(thresholdLevel: 'first' | 'second' | 'third') {
        if (thresholdLevel === 'first') {
            return '#E81C2B';
        } else if (thresholdLevel === 'second') {
            return '#F4891E';
        } else if (thresholdLevel === 'third') {
            return '#0AA34F';
        } else {
            return '';
        }
    }

    formatCivilityObject(civility: any) {
        if (!this.empty(civility)) {
            return civility;
        } else {
            return {
                label: '',
                abbreviation: ''
            };
        }
    }

    formatFillingObject(filling: any) {
        if (!this.empty(filling)) {
            return {
                rate: filling.rate,
                color: this.getFillingColor(filling.thresholdLevel)
            };
        } else {
            return {
                rate: '',
                color: ''
            };
        }
    }

    empty(value: any) {
        if (value !== null && value !== '' && value !== undefined) {
            return false;
        } else {
            return true;
        }
    }

    formatContact(contact: any) {
        if (this.functions.empty(contact.firstname) && this.functions.empty(contact.lastname)) {
            return contact.company;

        } else {
            const arrInfo = [];
            arrInfo.push(contact.firstname);
            arrInfo.push(contact.lastname);
            if (!this.functions.empty(contact.company)) {
                arrInfo.push('(' + contact.company + ')');
            }

            return arrInfo.filter(info => !this.functions.empty(info)).join(' ');
        }
    }

    formatContactAddress(contact: any) {
        const arrInfo = [];
        arrInfo.push(contact.addressNumber);
        arrInfo.push(contact.addressStreet);
        arrInfo.push(contact.addressPostcode);
        arrInfo.push(contact.addressTown);

        return arrInfo.filter(info => !this.functions.empty(info)).join(' ');
    }
}
