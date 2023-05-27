import { Component, OnInit, Inject, ViewChildren, QueryList } from '@angular/core';
import { MAT_DIALOG_DATA, MatDialogRef } from '@angular/material/dialog';
import { HeaderService } from '@service/header.service';
import { HttpClient } from '@angular/common/http';
import { FunctionsService } from '@service/functions.service';
import { ContactDetailComponent } from '../../../../contact/contact-detail/contact-detail.component';
import { TranslateService } from '@ngx-translate/core';
import { tap, catchError, finalize } from 'rxjs/operators';
import { of } from 'rxjs';
import { NotificationService } from '@service/notification/notification.service';

@Component({
    selector: 'app-manage-duplicate',
    templateUrl: './manage-duplicate.component.html',
    styleUrls: ['./manage-duplicate.component.scss']
})
export class ManageDuplicateComponent implements OnInit {

    @ViewChildren('appContactDetail') appContactDetail: QueryList<ContactDetailComponent>;

    loading: boolean = false;

    contactSelected: number = null;
    contactsExcluded: number[] = [];

    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        private notify: NotificationService,
        @Inject(MAT_DIALOG_DATA) public data: any,
        public dialogRef: MatDialogRef<ManageDuplicateComponent>,
        public headerService: HeaderService,
        private functionsService: FunctionsService) {
    }

    ngOnInit(): void { }

    mergeContact(selectedContact: any = this.appContactDetail.toArray()[this.contactSelected].contact, index: any = this.contactSelected) {
        this.appContactDetail.toArray()[index].resetContact();
        this.appContactDetail.toArray()[index].contact.selected = true;

        if (!this.functionsService.empty(index)) {
            this.contactSelected = index;
        }
        this.data.duplicate.forEach((contactItem: any, indexContact: number) => {
            if (this.contactsExcluded.indexOf(this.appContactDetail.toArray()[indexContact].getContactInfo().id) === -1) {
                Object.keys(this.appContactDetail.toArray()[indexContact].getContactInfo()).forEach(element => {

                    if (element === 'customFields' && !this.functionsService.empty(this.appContactDetail.toArray()[indexContact].getContactInfo()[element])) {
                        this.appContactDetail.toArray()[indexContact].getContactInfo()[element].forEach((custom: any) => {
                            if (this.appContactDetail.toArray()[index].getContactInfo()[element].filter((custom2: any) => custom2.label === custom.label).length === 0) {
                                this.appContactDetail.toArray()[index].setContactInfo(element, custom);
                            }
                        });
                    } else if (element === 'civility' && !this.functionsService.empty(this.appContactDetail.toArray()[indexContact].getContactInfo()[element].id) && this.functionsService.empty(selectedContact?.civility)) {
                        this.appContactDetail.toArray()[index].setContactInfo(element, this.appContactDetail.toArray()[indexContact].getContactInfo()[element]);
                    } else if (
                        this.functionsService.empty(this.appContactDetail.toArray()[index].getContactInfo()[element]) &&
                        this.appContactDetail.toArray()[index].getContactInfo()[element] !== this.appContactDetail.toArray()[indexContact].getContactInfo()[element]
                    ) {
                        this.appContactDetail.toArray()[index].setContactInfo(element, this.appContactDetail.toArray()[indexContact].getContactInfo()[element]);
                    }
                });
            }
        });
    }

    toggleExcludeContact(contact: any) {
        const index = this.contactsExcluded.indexOf(contact.id);
        if (index === -1) {
            this.contactsExcluded.push(contact.id);
        } else {
            this.contactsExcluded.splice(index, 1);
        }
    }

    resetContact(contact: any, index: number) {

        this.contactSelected = null;

        this.appContactDetail.toArray()[index].resetContact();

    }

    onSubmit() {
        this.loading = true;
        const masterContact: number = this.data.duplicate.filter((contact: any, index: number) => index === this.contactSelected).map((contact: any) => contact.id)[0];
        const slaveContacts: number[] = this.data.duplicate.filter((contact: any, index: number) => index !== this.contactSelected).filter((contact: any, index: number) => this.contactsExcluded.indexOf(contact.id) === -1).map((contact: any) => contact.id);

        this.http.put(`../rest/contacts/${masterContact}/merge`, { duplicates : slaveContacts}).pipe(
            tap(() => {
                if (slaveContacts.length === this.data.duplicate.length - 1) {
                    this.dialogRef.close('removeAll');
                } else {
                    this.dialogRef.close(slaveContacts);
                }
            }),
            finalize(() => this.loading = false),
            catchError((err: any) => {
                this.notify.handleSoftErrors(err);
                return of(false);
            })
        ).subscribe();
    }
}
