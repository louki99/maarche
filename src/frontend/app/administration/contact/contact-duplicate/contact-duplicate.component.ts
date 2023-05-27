import { Component, OnInit, ViewChild, TemplateRef, ViewContainerRef } from '@angular/core';
import { TranslateService } from '@ngx-translate/core';
import { HttpClient } from '@angular/common/http';
import { NotificationService } from '@service/notification/notification.service';
import { HeaderService } from '@service/header.service';
import { AppService } from '@service/app.service';
import { MatDialog } from '@angular/material/dialog';
import { FunctionsService } from '@service/functions.service';
import { of } from 'rxjs';
import { catchError, tap, map, exhaustMap, filter, finalize } from 'rxjs/operators';
import { SortPipe } from '@plugins/sorting.pipe';
import { UntypedFormControl } from '@angular/forms';
import { ManageDuplicateComponent } from './manage-duplicate/manage-duplicate.component';
import { ContactService } from '@service/contact.service';

@Component({
    selector: 'app-contact-duplicate',
    templateUrl: './contact-duplicate.component.html',
    styleUrls: ['./contact-duplicate.component.scss'],
    providers: [SortPipe, ContactService]
})
export class ContactDuplicateComponent implements OnInit {

    @ViewChild('adminMenuTemplate', { static: true }) adminMenuTemplate: TemplateRef<any>;

    subMenus: any[] = [];

    loading: boolean = true;

    contactFields: any = [];

    addCriteriaSelect = new UntypedFormControl();

    currentFieldsSearch: any = [];

    currentDuplicateId: string = null;

    duplicatesContacts: any[] = [];

    duplicatesContactsCount: number = -1;
    duplicatesContactsRealCount: number = 0;

    displayedColumns = ['companyLastname', 'lastname', 'company'];
    dataSource: any;
    isLoadingResults: boolean = false;
    openedSearchTool: boolean = true;

    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        private notify: NotificationService,
        private headerService: HeaderService,
        public appService: AppService,
        public dialog: MatDialog,
        public functions: FunctionsService,
        private sortPipe: SortPipe,
        public contactService: ContactService,
        private viewContainerRef: ViewContainerRef,
        private functionsService: FunctionsService
    ) {
        this.subMenus = contactService.getAdminMenu();
    }

    async ngOnInit(): Promise<void> {
        this.headerService.injectInSideBarLeft(this.adminMenuTemplate, this.viewContainerRef, 'adminMenu');
        this.headerService.setHeader(this.translate.instant('lang.contactsDuplicates'), '', '');
        await this.getContactFields();
        this.setDefaultSearchCriteria();
        this.loading = false;
    }


    getContactFields() {
        return new Promise((resolve, reject) => {
            this.http.get('../rest/contactsParameters').pipe(
                map((data: any) => {
                    const regex = /contactCustomField_[.]*/g;
                    data.contactsParameters = data.contactsParameters.filter((field: any) => field.identifier.match(regex) === null).map((field: any) => ({
                        ...field,
                        label: this.translate.instant('lang.contactsParameters_' + field.identifier)
                    }));
                    return data.contactsParameters;
                }),
                tap((fields: any) => {
                    this.contactFields = fields;
                }),
                exhaustMap(() => this.http.get('../rest/contactsCustomFields')),
                map((data: any) => {
                    data.customFields = data.customFields.map((field: any) => ({
                        ...field,
                        id: `contactCustomField_${field.id}`,
                        identifier: `contactCustomField_${field.id}`
                    }));
                    return data.customFields;
                }),
                tap((fields: any) => {
                    this.contactFields = this.contactFields.concat(fields);
                    this.contactFields = this.sortPipe.transform(this.contactFields, 'label');
                    resolve(true);
                }),
                catchError((err: any) => {
                    this.notify.handleSoftErrors(err);
                    return of(false);
                })
            ).subscribe();
        });
    }

    setDefaultSearchCriteria(target: string[] = ['lastname', 'company']) {
        this.contactFields.filter((contact: any) => target.indexOf(contact.identifier) > -1).forEach((field: any) => {
            field.disabled = true;
            this.currentFieldsSearch.push(field);
        });
    }

    addCriteria(id: any) {
        this.contactFields.filter((contact: any) => contact.id === id).forEach((field: any) => {
            field.disabled = true;
            this.currentFieldsSearch.push(field);
        });
        this.addCriteriaSelect.reset();
    }

    removeCriteria(field: any) {
        this.contactFields.forEach((contact: any, index: number) => {
            if (contact.id === field.id) {
                this.currentFieldsSearch = this.currentFieldsSearch.filter((currField: any) => currField.id !== field.id);
                contact.disabled = false;
            }
        });
    }

    searchDuplicates() {
        this.duplicatesContacts = [];
        this.isLoadingResults = true;
        const queryParam = '?criteria[]=' + this.currentFieldsSearch.map((field: any) => field.identifier).join('&criteria[]=');
        this.http.get(`../rest/duplicatedContacts${queryParam}`).pipe(
            map((data: any) => {
                this.duplicatesContactsRealCount = data.realCount;
                this.duplicatesContactsCount = data.returnedCount;
                data.contacts.forEach((element: any, index: number) => {
                    if (index === 0) {
                        element.odd = true;
                    } else if (data.contacts[index - 1] !== undefined && data.contacts[index - 1].duplicateId === element.duplicateId) {
                        element.odd = data.contacts[index - 1].odd;
                    } else {
                        element.odd = !data.contacts[index - 1].odd;
                    }
                    const tmpFormated = [];
                    tmpFormated.push(element.company);
                    tmpFormated.push(element.lastname);
                    element.companyLastname = tmpFormated.filter(item => !this.functions.empty(item)).join(' / ');

                    if (!this.functionsService.empty(element.customFields)) {
                        Object.keys(element.customFields).forEach((customIndex: any) => {
                            element[customIndex] = element.customFields[customIndex];
                        });
                    }
                });
                return data.contacts;
            }),
            tap((contacts: any) => {
                this.duplicatesContacts = contacts;

                setTimeout(() => {
                    const regex = /contactCustomField_[.]*/g;
                    this.displayedColumns = this.currentFieldsSearch.filter((field: any) => field.identifier.match(regex) === null).map((field: any) => field.identifier).concat(this.currentFieldsSearch.filter((field: any) => field.identifier.match(regex) !== null).map((field: any) => field.identifier.replace('contactCustomField_', '')));
                    this.displayedColumns.unshift('companyLastname');
                    this.openedSearchTool = false;
                }, 0);
            }),
            finalize(() => this.isLoadingResults = false),
            catchError((err: any) => {
                this.notify.handleSoftErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    selectDuplicates(duplicateId: string) {
        this.currentDuplicateId = duplicateId;
    }

    manageDuplicate(duplicateId: string) {
        const dialogRef = this.dialog.open(ManageDuplicateComponent, {
            panelClass: 'maarch-modal',
            data: { duplicate: this.duplicatesContacts.filter((contact: any) => contact.duplicateId === duplicateId).map((contact: any) => ({ id: contact.id, type: 'contact'})) }
        });
        dialogRef.afterClosed().pipe(
            filter((data: any) => !this.functionsService.empty(data)),
            tap((data) => {
                this.notify.success(this.translate.instant('lang.contactsMerged'));
                this.duplicatesContactsCount--;
                this.duplicatesContactsRealCount--;
                if (data !== 'removeAll') {
                    this.duplicatesContacts = this.duplicatesContacts.filter((contact: any) => data.indexOf(contact.id) === -1);
                } else {
                    this.duplicatesContacts = this.duplicatesContacts.filter((contact: any) => contact.duplicateId !== duplicateId);
                }
            }),
            catchError((err: any) => {
                this.notify.handleSoftErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    getLabel(item: any) {
        if (this.translate.instant('lang.contactsParameters_' + item) !== undefined) {
            return this.translate.instant('lang.contactsParameters_' + item);
        } else if (this.translate.instant('lang.' + item) !== undefined) {
            return this.translate.instant('lang.' + item);
        } else {
            return this.contactFields.filter((field: any) => field.id === 'contactCustomField_' + item)[0].label;
        }
    }
}
