import { Component, OnInit, Input, ViewChild, ElementRef, Output, EventEmitter } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { UntypedFormControl } from '@angular/forms';
import { tap, catchError, finalize } from 'rxjs/operators';
import { of } from 'rxjs';
import { NotificationService } from '@service/notification/notification.service';
import { HeaderService } from '@service/header.service';
import { FunctionsService } from '@service/functions.service';

@Component({
    selector: 'app-issuing-site-input',
    templateUrl: 'issuing-site-input.component.html',
    styleUrls: [
        'issuing-site-input.component.scss',
        '../../../../indexation/indexing-form/indexing-form.component.scss'
    ],
})

export class IssuingSiteInputComponent implements OnInit {
    /**
     * FormControl used when autocomplete is used in form and must be catched in a form control.
     */
    @Input() control: UntypedFormControl = new UntypedFormControl('');
    @Input() registedMailType: string = null;
    @Input() showResetOption: boolean = false;

    @Output() afterSelected = new EventEmitter<string>();

    @ViewChild('autoCompleteInput', { static: true }) autoCompleteInput: ElementRef;

    loading: boolean = false;

    issuingSiteList: any[] = [];

    issuingSiteAddress: any = null;

    constructor(
        public http: HttpClient,
        private notify: NotificationService,
        private headerService: HeaderService,
        public functions: FunctionsService
    ) {

    }

    ngOnInit() {
        this.getIssuingSites();
        if (!this.functions.empty(this.control.value)) {
            setTimeout(() => {
                this.setAddress(this.control.value);
            }, 0);
        }
    }

    getIssuingSites() {
        this.loading = true;
        this.http.get('../rest/registeredMail/sites').pipe(
            tap((data: any) => {
                this.issuingSiteAddress = null;
                if (this.functions.empty(this.headerService.user.entities)) {
                    this.issuingSiteList = data['sites'].map((item: any) => ({
                        ...item,
                        id: item.id,
                        label: `${item.label} (${item.accountNumber})`
                    }));
                } else {
                    this.issuingSiteList = data['sites'].filter((item: any) => item.entities.indexOf(this.headerService.user.entities[0].id) > -1).map((item: any) => ({
                        ...item,
                        id: item.id,
                        label: `${item.label} (${item.accountNumber})`
                    }));
                }
            }),
            finalize(() => this.loading = false),
            catchError((err: any) => {
                this.notify.handleSoftErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    setAddress(id: any) {
        if (id === null) {
            this.issuingSiteAddress = null;
        } else {
            this.http.get(`../rest/registeredMail/sites/${id}`).pipe(
                tap((data: any) => {
                    this.issuingSiteAddress = data['site'];
                }),
                catchError((err: any) => {
                    this.notify.handleSoftErrors(err);
                    return of(false);
                })
            ).subscribe();
        }
    }

    getSiteLabel(id: string) {
        return this.issuingSiteList.filter((site: any) => site.id === id)[0].label;
    }

    goTo() {
        window.open(`https://www.google.com/maps/search/${this.issuingSiteAddress.addressNumber}+${this.issuingSiteAddress.addressStreet},+${this.issuingSiteAddress.addressPostcode}+${this.issuingSiteAddress.addressTown},+${this.issuingSiteAddress.addressCountry}`, '_blank');
    }
}
