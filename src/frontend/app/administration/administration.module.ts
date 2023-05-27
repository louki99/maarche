import { NgModule } from '@angular/core';

import { SharedModule } from '../app-common.module';

import { InternationalizationModule } from '@service/translate/internationalization.module';

import { AdministrationRoutingModule } from './administration-routing.module';
// import { NgxChartsModule } from '@swimlane/ngx-charts';
import { JoyrideModule } from 'ngx-joyride';
import { DocumentViewerModule } from '../viewer/document-viewer.module';

import { AdministrationService } from './administration.service';

import { AccountLinkComponent } from './user/account-link/account-link.component';
import { ActionAdministrationComponent } from './action/action-administration.component';
import { ActionsAdministrationComponent } from './action/actions-administration.component';
import { AlfrescoAdministrationComponent } from './alfresco/alfresco-administration.component';
import { AlfrescoListAdministrationComponent } from './alfresco/alfresco-list-administration.component';
import { BasketAdministrationComponent, BasketAdministrationSettingsModalComponent, BasketAdministrationGroupListModalComponent } from './basket/basket-administration.component';
import { BasketsAdministrationComponent } from './basket/baskets-administration.component';
import { ContactDuplicateComponent } from './contact/contact-duplicate/contact-duplicate.component';
import { ContactExportComponent } from './contact/list/export/contact-export.component';
import { ContactImportComponent } from './contact/list/import/contact-import.component';
import { ContactsCustomFieldsAdministrationComponent } from './contact/customField/contacts-custom-fields-administration.component';
import { ContactsGroupAdministrationComponent } from './contact/group/contacts-group-administration.component';
import { ContactsGroupsAdministrationComponent } from './contact/group/contacts-groups-administration.component';
import { ContactsListAdministrationComponent, ContactsListAdministrationRedirectModalComponent } from './contact/list/contacts-list-administration.component';
import { ContactsPageAdministrationComponent } from './contact/page/contacts-page-administration.component';
import { ContactsParametersAdministrationComponent } from './contact/parameter/contacts-parameters-administration.component';
import { CustomFieldsAdministrationComponent } from './customField/custom-fields-administration.component';
import { DiffusionModelAdministrationComponent } from './diffusionModel/diffusionModel-administration.component';
import { DiffusionModelsAdministrationComponent } from './diffusionModel/diffusionModels-administration.component';
import { DocserverAdministrationComponent } from './docserver/docserver-administration.component';
import { DocserversAdministrationComponent } from './docserver/docservers-administration.component';
import { DoctypesAdministrationComponent, DoctypesAdministrationRedirectModalComponent } from './doctype/doctypes-administration.component';
import { EntitiesAdministrationComponent, EntitiesAdministrationRedirectModalComponent } from './entity/entities-administration.component';
import { GroupAdministrationComponent } from './group/group-administration.component';
import { GroupsAdministrationComponent, GroupsAdministrationRedirectModalComponent } from './group/groups-administration.component';
import { HistoryAdministrationComponent } from './history/history-administration.component';
import { HistoryBatchAdministrationComponent } from './history/batch/history-batch-administration.component';
import { IndexingAdministrationComponent } from './group/indexing/indexing-administration.component';
import { IndexingModelAdministrationComponent } from './indexingModel/indexing-model-administration.component';
import { IndexingModelsAdministrationComponent } from './indexingModel/indexing-models-administration.component';
import { ListAdministrationComponent } from './basket/list/list-administration.component';
import { ManageDuplicateComponent } from './contact/contact-duplicate/manage-duplicate/manage-duplicate.component';
import { NotificationAdministrationComponent } from './notification/notification-administration.component';
import { NotificationsAdministrationComponent } from './notification/notifications-administration.component';
import { ParameterAdministrationComponent } from './parameter/parameter-administration.component';
import { ParametersAdministrationComponent } from './parameter/parameters-administration.component';
import { ParametersCustomizationComponent } from './parameter/customization/parameters-customization.component';
import { PrioritiesAdministrationComponent } from './priority/priorities-administration.component';
import { PriorityAdministrationComponent } from './priority/priority-administration.component';
import { SecuritiesAdministrationComponent } from './security/securities-administration.component';
import { SendmailAdministrationComponent } from './sendmail/sendmail-administration.component';
import { CheckMailServerModalComponent } from './sendmail/checkMailServer/check-mail-server-modal.component';
import { OrganizationEmailSignaturesAdministrationComponent } from './organizationEmailSignatures/organization-email-signatures-administration.component';

import { ShippingAdministrationComponent } from './shipping/shipping-administration.component';
import { ShippingsAdministrationComponent } from './shipping/shippings-administration.component';
import { StatusAdministrationComponent } from './status/status-administration.component';
import { StatusesAdministrationComponent } from './status/statuses-administration.component';
import { TagAdministrationComponent } from './tag/tag-administration.component';
import { TagsAdministrationComponent } from './tag/tags-administration.component';
import { TemplateAdministrationComponent, TemplateAdministrationCheckEntitiesModalComponent } from './template/template-administration.component';
import { TemplateFileEditorModalComponent } from './template/templateFileEditorModal/template-file-editor-modal.component';
import { TemplatesAdministrationComponent } from './template/templates-administration.component';
import { UpdateStatusAdministrationComponent } from './updateStatus/update-status-administration.component';
import { UserAdministrationComponent, UserAdministrationRedirectModalComponent } from './user/user-administration.component';
import { VersionsUpdateAdministrationComponent } from './versionUpdate/versions-update-administration.component';
import { AdministrationComponent } from './home/administration.component';
import { UsersAdministrationComponent } from './user/users-administration.component';
import { UsersAdministrationRedirectModalComponent } from './user/redirect-modal/users-administration-redirect-modal.component';
import { UsersImportComponent } from './user/import/users-import.component';
import { UsersExportComponent } from './user/export/users-export.component';
import { TranslateService } from '@ngx-translate/core';
import { EntitiesExportComponent } from './entity/export/entities-export.component';
import { RegisteredMailComponent } from './registered-mail/registered-mail.component';
import { IssuingSiteListComponent } from './registered-mail/issuing-site/issuing-site-list.component';
import { IssuingSiteComponent } from './registered-mail/issuing-site/issuing-site.component';
import { RegisteredMailListComponent } from './registered-mail/registered-mail-list.component';
import { SearchAdministrationComponent } from './search/search-administration.component';
import { SsoAdministrationComponent } from './connection/sso/sso-administration.component';
import { LifeCycleComponent } from './parameter/lifeCycle/life-cycle.component';
import { AttachmentTypesAdministrationComponent } from './attachment/attachment-types-administration.component';
import { AttachmentTypeAdministrationComponent } from './attachment/attachment-type-administration.component';
import { VisaParametersComponent } from '@appRoot/administration/parameter/visa/visa-parameters.component';
import { OtherParametersComponent } from '@appRoot/administration/parameter/other/other-parameters.component';
import { MaarchToMaarchParametersComponent } from '@appRoot/administration/parameter/maarchToMaarch/maarch-to-maarch-parameters.component';
import { MultigestAdministrationComponent } from './multigest/multigest-administration.component';
import { MultigestListAdministrationComponent } from './multigest/multigest-list-administration.component';
import { HistoryExportComponent } from './history/export/history-export.component';

@NgModule({
    imports: [
        SharedModule,
        // NgxChartsModule,
        InternationalizationModule,
        JoyrideModule.forChild(),
        AdministrationRoutingModule,
        DocumentViewerModule
    ],
    declarations: [
        AccountLinkComponent,
        ActionAdministrationComponent,
        ActionsAdministrationComponent,
        AlfrescoAdministrationComponent,
        AlfrescoListAdministrationComponent,
        BasketAdministrationComponent,
        BasketAdministrationGroupListModalComponent,
        BasketAdministrationSettingsModalComponent,
        BasketsAdministrationComponent,
        ContactDuplicateComponent,
        ContactExportComponent,
        ContactImportComponent,
        ContactsCustomFieldsAdministrationComponent,
        ContactsGroupAdministrationComponent,
        ContactsGroupsAdministrationComponent,
        ContactsListAdministrationComponent,
        ContactsListAdministrationRedirectModalComponent,
        ContactsPageAdministrationComponent,
        ContactsParametersAdministrationComponent,
        CustomFieldsAdministrationComponent,
        DiffusionModelAdministrationComponent,
        DiffusionModelsAdministrationComponent,
        DocserverAdministrationComponent,
        DocserversAdministrationComponent,
        DoctypesAdministrationComponent,
        DoctypesAdministrationRedirectModalComponent,
        EntitiesAdministrationComponent,
        EntitiesAdministrationRedirectModalComponent,
        GroupAdministrationComponent,
        GroupsAdministrationComponent,
        GroupsAdministrationRedirectModalComponent,
        HistoryAdministrationComponent,
        HistoryBatchAdministrationComponent,
        IndexingAdministrationComponent,
        IndexingModelAdministrationComponent,
        IndexingModelsAdministrationComponent,
        ListAdministrationComponent,
        ManageDuplicateComponent,
        NotificationAdministrationComponent,
        NotificationsAdministrationComponent,
        ParameterAdministrationComponent,
        ParametersAdministrationComponent,
        ParametersCustomizationComponent,
        PrioritiesAdministrationComponent,
        PriorityAdministrationComponent,
        SecuritiesAdministrationComponent,
        SendmailAdministrationComponent,
        OrganizationEmailSignaturesAdministrationComponent,
        CheckMailServerModalComponent,
        ShippingAdministrationComponent,
        ShippingsAdministrationComponent,
        StatusAdministrationComponent,
        StatusesAdministrationComponent,
        TagAdministrationComponent,
        TagsAdministrationComponent,
        TemplateAdministrationCheckEntitiesModalComponent,
        TemplateAdministrationComponent,
        TemplateFileEditorModalComponent,
        TemplatesAdministrationComponent,
        UpdateStatusAdministrationComponent,
        UserAdministrationComponent,
        UserAdministrationRedirectModalComponent,
        VersionsUpdateAdministrationComponent,
        AdministrationComponent,
        UsersAdministrationComponent,
        UsersAdministrationRedirectModalComponent,
        UsersImportComponent,
        UsersExportComponent,
        EntitiesExportComponent,
        RegisteredMailComponent,
        IssuingSiteListComponent,
        IssuingSiteComponent,
        RegisteredMailListComponent,
        SearchAdministrationComponent,
        SsoAdministrationComponent,
        LifeCycleComponent,
        AttachmentTypeAdministrationComponent,
        AttachmentTypesAdministrationComponent,
        VisaParametersComponent,
        OtherParametersComponent,
        MaarchToMaarchParametersComponent,
        MultigestAdministrationComponent,
        MultigestListAdministrationComponent,
        HistoryExportComponent
    ],
    providers: [
        AdministrationService
    ]
})
export class AdministrationModule {
    constructor(translate: TranslateService) {
        translate.setDefaultLang('fr');
    }
}
