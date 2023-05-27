import { Component, ViewChild, OnInit, TemplateRef, ViewContainerRef } from '@angular/core';
import { TranslateService } from '@ngx-translate/core';
import { HeaderService } from '@service/header.service';
import { AppService } from '@service/app.service';
import { FunctionsService } from '@service/functions.service';
import { ContactService } from '@service/contact.service';

@Component({
    templateUrl: 'contacts-groups-administration.component.html',
    styleUrls: [
        'contacts-groups-administration.component.scss'
    ],
    providers: [ContactService]
})

export class ContactsGroupsAdministrationComponent implements OnInit {

    @ViewChild('adminMenuTemplate', { static: true }) adminMenuTemplate: TemplateRef<any>;

    subMenus: any[] = [];

    constructor(
        public translate: TranslateService,
        private headerService: HeaderService,
        public appService: AppService,
        public functions: FunctionsService,
        private viewContainerRef: ViewContainerRef,
        public contactService: ContactService
    ) {
        this.subMenus = contactService.getAdminMenu();
    }

    ngOnInit(): void {
        this.headerService.setHeader(this.translate.instant('lang.administration') + ' ' + this.translate.instant('lang.contactsGroups'));
        this.headerService.injectInSideBarLeft(this.adminMenuTemplate, this.viewContainerRef, 'adminMenu');
    }
}
