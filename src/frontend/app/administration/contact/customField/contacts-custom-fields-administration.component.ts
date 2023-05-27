import { Component, OnInit, ViewChild, TemplateRef, ViewContainerRef } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { TranslateService } from '@ngx-translate/core';
import { NotificationService } from '@service/notification/notification.service';
import { HeaderService } from '@service/header.service';
import { MatDialog, MatDialogRef } from '@angular/material/dialog';
import { MatSidenav } from '@angular/material/sidenav';
import { AppService } from '@service/app.service';
import { tap, catchError, filter, exhaustMap, map, finalize } from 'rxjs/operators';
import { ConfirmComponent } from '@plugins/modal/confirm.component';
import { SortPipe } from '@plugins/sorting.pipe';
import { of } from 'rxjs';
import { ContactService } from '@service/contact.service';

@Component({
    templateUrl: 'contacts-custom-fields-administration.component.html',
    styleUrls: [
        'contacts-custom-fields-administration.component.scss',
        '../../../indexation/indexing-form/indexing-form.component.scss'
    ],
    providers: [SortPipe, ContactService]
})

export class ContactsCustomFieldsAdministrationComponent implements OnInit {

    @ViewChild('snav2', { static: true }) public sidenavRight: MatSidenav;
    @ViewChild('adminMenuTemplate', { static: true }) adminMenuTemplate: TemplateRef<any>;

    subMenus: any[] = [];

    loading: boolean = true;

    customFieldsTypes: any[] = [
        {
            label: this.translate.instant('lang.stringInput'),
            type: 'string'
        },
        {
            label: this.translate.instant('lang.integerInput'),
            type: 'integer'
        },
        {
            label: this.translate.instant('lang.selectInput'),
            type: 'select'
        },
        {
            label: this.translate.instant('lang.dateInput'),
            type: 'date'
        },
        {
            label: this.translate.instant('lang.radioInput'),
            type: 'radio'
        },
        {
            label: this.translate.instant('lang.checkboxInput'),
            type: 'checkbox'
        }
    ];
    customFields: any[] = [];
    customFieldsClone: any[] = [];

    incrementCreation: number = 1;

    sampleIncrement: number[] = [1, 2, 3, 4];


    dialogRef: MatDialogRef<any>;

    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        private notify: NotificationService,
        public dialog: MatDialog,
        private headerService: HeaderService,
        public appService: AppService,
        private sortPipe: SortPipe,
        public contactService: ContactService,
        private viewContainerRef: ViewContainerRef
    ) {
        this.subMenus = contactService.getAdminMenu();
    }

    ngOnInit(): void {
        this.headerService.setHeader(this.translate.instant('lang.administration') + ' ' + this.translate.instant('lang.customFields') + ' ' + this.translate.instant('lang.contacts'));

        this.headerService.injectInSideBarLeft(this.adminMenuTemplate, this.viewContainerRef, 'adminMenu');

        this.http.get('../rest/contactsCustomFields').pipe(
            // TO FIX DATA BINDING SIMPLE ARRAY VALUES
            map((data: any) => {
                data.customFields.forEach((element: any) => {
                    element.values = element.values.map((info: any) => ({
                        label: info
                    }));

                });
                return data;
            }),
            tap((data: any) => {
                this.customFields = data.customFields;
                this.customFieldsClone = JSON.parse(JSON.stringify(this.customFields));
            }),
            finalize(() => this.loading = false),
            catchError((err: any) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    addCustomField(customFieldType: any) {

        let newCustomField: any = {};

        this.dialogRef = this.dialog.open(ConfirmComponent, { panelClass: 'maarch-modal', autoFocus: false, disableClose: true, data: { title: this.translate.instant('lang.add'), msg: this.translate.instant('lang.confirmAction') } });

        this.dialogRef.afterClosed().pipe(
            filter((data: string) => data === 'ok'),
            tap(() => {
                newCustomField = {
                    label: this.translate.instant('lang.newField') + ' ' + this.incrementCreation,
                    type: customFieldType.type,
                    values: []
                };
            }),
            exhaustMap((data) => this.http.post('../rest/contactsCustomFields', newCustomField)),
            tap((data: any) => {
                newCustomField.id = data.id;
                this.customFields.push(newCustomField);
                this.notify.success(this.translate.instant('lang.customFieldAdded'));
                this.incrementCreation++;
            }),
            catchError((err: any) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    addValue(indexCustom: number) {
        this.customFields[indexCustom].values.push(
            {
                label: ''
            }
        );
    }

    removeValue(customField: any, indexValue: number) {
        customField.values.splice(indexValue, 1);
    }

    removeCustomField(indexCustom: number) {
        this.dialogRef = this.dialog.open(ConfirmComponent, { panelClass: 'maarch-modal', autoFocus: false, disableClose: true, data: { title: this.translate.instant('lang.delete') + ' "' + this.customFields[indexCustom].label + '"', msg: this.translate.instant('lang.confirmAction') } });

        this.dialogRef.afterClosed().pipe(
            filter((data: string) => data === 'ok'),
            exhaustMap(() => this.http.delete('../rest/contactsCustomFields/' + this.customFields[indexCustom].id)),
            tap(() => {
                this.customFields.splice(indexCustom, 1);
                this.notify.success(this.translate.instant('lang.customFieldDeleted'));
            }),
            catchError((err: any) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    updateCustomField(customField: any, indexCustom: number) {

        customField.values = customField.values.filter((x: any, i: any, a: any) => a.map((info: any) => info.label).indexOf(x.label) === i);

        // TO FIX DATA BINDING SIMPLE ARRAY VALUES
        const customFieldToUpdate = { ...customField };

        customFieldToUpdate.values = customField.values.map((data: any) => data.label);

        const alreadyExists = this.customFields.filter(customFieldItem => customFieldItem.label === customFieldToUpdate.label);
        if (alreadyExists.length > 1) {
            this.notify.handleErrors(this.translate.instant('lang.customFieldAlreadyExists'));
            return of(false);
        }

        this.http.put('../rest/contactsCustomFields/' + customField.id, customFieldToUpdate).pipe(
            tap(() => {
                this.customFieldsClone[indexCustom] = JSON.parse(JSON.stringify(customField));
                this.notify.success(this.translate.instant('lang.customFieldUpdated'));
            }),
            catchError((err: any) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    sortValues(customField: any) {
        customField.values = this.sortPipe.transform(customField.values, 'label');
    }

    isModified(customField: any, indexCustomField: number) {
        if (JSON.stringify(customField) === JSON.stringify(this.customFieldsClone[indexCustomField]) || customField.label === '') {
            return true;
        } else {
            return false;
        }
    }
}
