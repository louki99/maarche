import { Component, OnInit, ViewChild, TemplateRef, ViewContainerRef } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { TranslateService } from '@ngx-translate/core';
import { HeaderService } from '@service/header.service';
import { AppService } from '@service/app.service';
import { MatDialog } from '@angular/material/dialog';
import { ActivatedRoute, Router } from '@angular/router';
import { ContactService } from '@service/contact.service';
import { FunctionsService } from '@service/functions.service';

@Component({
    templateUrl: 'contacts-page-administration.component.html',
    styleUrls: ['contacts-page-administration.component.scss'],
    providers: [ContactService]
})
export class ContactsPageAdministrationComponent implements OnInit {

    @ViewChild('adminMenuTemplate', { static: true }) adminMenuTemplate: TemplateRef<any>;

    subMenus: any[] = [];

    loading: boolean = false;

    creationMode: boolean = true;

    contactId: number = null;

    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        private route: ActivatedRoute,
        private router: Router,
        private headerService: HeaderService,
        public appService: AppService,
        public dialog: MatDialog,
        public contactService: ContactService,
        public functionsService: FunctionsService,
        private viewContainerRef: ViewContainerRef
    ) {
        this.subMenus = contactService.getAdminMenu();
    }

    ngOnInit(): void {

        this.loading = true;

        this.headerService.injectInSideBarLeft(this.adminMenuTemplate, this.viewContainerRef, 'adminMenu');

        this.route.params.subscribe((params: any) => {

            if (typeof params['id'] === 'undefined') {
                this.headerService.setHeader(this.translate.instant('lang.contactCreation'));
                this.creationMode = true;
                this.loading = false;

            } else {

                this.headerService.setHeader(this.translate.instant('lang.contactModification'));

                this.creationMode = false;

                this.contactId = params['id'];

                this.loading = false;
            }
        });
    }

    goToList() {
        this.router.navigate(['/administration/contacts']);
    }
}
