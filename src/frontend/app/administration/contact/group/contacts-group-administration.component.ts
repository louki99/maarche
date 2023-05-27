import { Component, OnInit, ViewChild, TemplateRef, ViewContainerRef } from '@angular/core';
import { ActivatedRoute } from '@angular/router';
import { TranslateService } from '@ngx-translate/core';
import { HeaderService } from '@service/header.service';
import { MatSidenav } from '@angular/material/sidenav';
import { AppService } from '@service/app.service';
import { ContactService } from '@service/contact.service';

@Component({
    templateUrl: 'contacts-group-administration.component.html',
    styleUrls: [
        'contacts-group-administration.component.scss'
    ],
    providers: [ContactService]
})
export class ContactsGroupAdministrationComponent implements OnInit {

    @ViewChild('snav2', { static: true }) public sidenavRight: MatSidenav;
    @ViewChild('adminMenuTemplate', { static: true }) adminMenuTemplate: TemplateRef<any>;

    subMenus: any[] = [];

    contactGroupId: number = null;
    loading: boolean = false;


    constructor(
        public translate: TranslateService,
        private route: ActivatedRoute,
        private headerService: HeaderService,
        public appService: AppService,
        private viewContainerRef: ViewContainerRef,
        public contactService: ContactService
    ) {
        this.subMenus = contactService.getAdminMenu();
    }

    ngOnInit(): void {
        this.headerService.injectInSideBarLeft(this.adminMenuTemplate, this.viewContainerRef, 'adminMenu');
        this.route.params.subscribe(params => {
            if (typeof params['id'] === 'undefined') {
                this.headerService.setHeader(this.translate.instant('lang.contactGroupCreation'));
            } else {
                this.headerService.setHeader(this.translate.instant('lang.contactsGroupModification'));
                this.contactGroupId = params['id'];
            }
        });
    }
}
