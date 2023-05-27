import { CommonModule } from '@angular/common';

import { NgModule } from '@angular/core';

/* CORE IMPORTS*/
import { FormsModule, ReactiveFormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { DragDropModule } from '@angular/cdk/drag-drop';


/* PLUGINS IMPORTS*/
import { AppServiceModule } from './app-service.module';
import { NotificationModule } from '@service/notification/notification.module';

import { MaarchTreeComponent } from '../plugins/tree/maarch-tree.component';
import { MaarchFlatTreeComponent } from '../plugins/tree/maarch-flat-tree.component';
import { AutocompleteListComponent } from '../plugins/autocomplete-list/autocomplete-list.component';
import { MessageBoxComponent } from '../plugins/messageBox/message-box.component';

/* FRONT IMPORTS*/
import { AppMaterialModule } from './app-material.module';

import { SmdFabSpeedDialComponent, SmdFabSpeedDialTriggerComponent, SmdFabSpeedDialActionsComponent } from '../plugins/fab-speed-dial';

/* MENU COMPONENT*/
import { HeaderRightComponent } from './header/header-right.component';
import { HeaderLeftComponent } from './header/header-left.component';
import { HeaderPanelComponent } from './header/header-panel.component';
import { MenuNavComponent } from './menu/menu-nav.component';
import { MenuShortcutComponent, IndexingGroupModalComponent } from './menu/menu-shortcut.component';

import { BasketHomeComponent } from './basket/basket-home.component';

import { FieldListComponent } from './indexation/field-list/field-list.component';

// DOCUMENT FORM
import { IndexingFormComponent } from './indexation/indexing-form/indexing-form.component';
import { TagInputComponent } from './tag/indexing/tag-input.component';
import { FolderInputComponent } from '../app/folder/indexing/folder-input.component';
import { IssuingSiteInputComponent } from '../app/administration/registered-mail/issuing-site/indexing/issuing-site-input.component';
import { RegisteredMailRecipientInputComponent } from '../app/administration/registered-mail/indexing/recipient-input.component';

/* MODAL*/
import { AlertComponent } from '../plugins/modal/alert.component';
import { ConfirmComponent } from '../plugins/modal/confirm.component';

/* PLUGIN COMPONENT*/
import { NotesListComponent } from './notes/notes-list.component';
import { NoteEditorComponent } from './notes/note-editor.component';

import { PluginAutocompleteComponent } from '../plugins/autocomplete/autocomplete.component';
import { PluginSelectSearchComponent } from '../plugins/select-search/select-search.component';
import { PluginSelectAutocompleteSearchComponent } from '../plugins/select-autocomplete-search/plugin-select-autocomplete-search.component';

import { DragDropDirective } from '../app/viewer/upload-file-dnd.directive';
import { AddressBanAutocompleteComponent } from './contact/ban-autocomplete/address-ban-autocomplete.component';

import { ContactAutocompleteComponent } from './contact/autocomplete/contact-autocomplete.component';
import { ContactsFormComponent } from './administration/contact/page/form/contacts-form.component';

import { HistoryComponent } from './history/history.component';

import { DiffusionsListComponent } from './diffusions/diffusions-list.component';
import { HistoryDiffusionsListComponent } from './diffusions/history/history-diffusions-list.component';
import { VisaWorkflowComponent } from './visa/visa-workflow.component';
import { HistoryVisaWorkflowComponent } from './visa/history/history-visa-workflow.component';
import { AvisWorkflowComponent } from './avis/avis-workflow.component';

import { ContactResourceComponent } from './contact/contact-resource/contact-resource.component';
import { ContactDetailComponent } from './contact/contact-detail/contact-detail.component';
import { ContactsGroupFormComponent } from './administration/contact/group/form/contacts-group-form.component';
import { ContactsGroupsListComponent } from './administration/contact/group/list/contacts-groups-list.component';
import { ContactsGroupFormModalComponent } from './administration/contact/group/form/modal/contacts-group-form-modal.component';
import { ContactsGroupMergeModalComponent } from './administration/contact/group/list/merge-modal/contacts-group-merge-modal.component';
import { ContactsFormModalComponent } from './administration/contact/page/form/modal/contacts-form-modal.component';
import { InputCorrespondentGroupComponent } from './administration/contact/group/inputCorrespondent/input-correspondent-group.component';


import { AttachmentsListComponent } from './attachments/attachments-list.component';

import { FolderMenuComponent } from './folder/folder-menu/folder-menu.component';
import { FolderActionListComponent } from './folder/folder-action-list/folder-action-list.component';

import { LinkedResourceListComponent } from './linkedResource/linked-resource-list.component';

import { InternationalizationModule } from '@service/translate/internationalization.module';
import { TranslateService } from '@ngx-translate/core';

import { RegisteredMailImportComponent } from '@appRoot/registeredMail/import/registered-mail-import.component';
import { CriteriaToolComponent } from '@appRoot/search/criteria-tool/criteria-tool.component';
import { ColorGithubModule } from 'ngx-color/github';
import { MailSignaturesAdministrationComponent } from './administration/organizationEmailSignatures/mailSignatures/mail-signatures-administration.component';
import { SelectPageComponent } from '@plugins/list/select-page/select-page.component';
import { MailEditorComponent } from '@plugins/mail-editor/mail-editor.component';
import { SelectWithFilterComponent } from '@plugins/select-with-filter/select-with-filter.component';
import { FilterComponent } from '@plugins/select-with-filter/filter/filter.component';


@NgModule({
    imports: [
        CommonModule,
        RouterModule,
        FormsModule,
        ReactiveFormsModule,
        AppMaterialModule,
        DragDropModule,
        AppServiceModule,
        NotificationModule,
        InternationalizationModule,
        ColorGithubModule,
    ],
    declarations: [
        MenuNavComponent,
        MenuShortcutComponent,
        HeaderRightComponent,
        HeaderLeftComponent,
        HeaderPanelComponent,
        BasketHomeComponent,
        IndexingGroupModalComponent,
        RegisteredMailImportComponent,
        SmdFabSpeedDialComponent,
        SmdFabSpeedDialTriggerComponent,
        SmdFabSpeedDialActionsComponent,
        IndexingFormComponent,
        TagInputComponent,
        FolderInputComponent,
        IssuingSiteInputComponent,
        RegisteredMailRecipientInputComponent,
        MessageBoxComponent,
        AlertComponent,
        ConfirmComponent,
        PluginAutocompleteComponent,
        FieldListComponent,
        PluginSelectSearchComponent,
        PluginSelectAutocompleteSearchComponent,
        DiffusionsListComponent,
        HistoryDiffusionsListComponent,
        DragDropDirective,
        ContactAutocompleteComponent,
        ContactsFormComponent,
        ContactsGroupsListComponent,
        HistoryComponent,
        AddressBanAutocompleteComponent,
        VisaWorkflowComponent,
        HistoryVisaWorkflowComponent,
        AvisWorkflowComponent,
        MaarchTreeComponent,
        MaarchFlatTreeComponent,
        ContactResourceComponent,
        ContactDetailComponent,
        ContactsGroupFormComponent,
        AutocompleteListComponent,
        AttachmentsListComponent,
        FolderMenuComponent,
        FolderActionListComponent,
        LinkedResourceListComponent,
        NotesListComponent,
        NoteEditorComponent,
        CriteriaToolComponent,
        ContactsGroupFormModalComponent,
        ContactsGroupMergeModalComponent,
        ContactsFormModalComponent,
        InputCorrespondentGroupComponent,
        MailSignaturesAdministrationComponent,
        SelectPageComponent,
        MailEditorComponent,
        SelectWithFilterComponent,
        FilterComponent
    ],
    exports: [
        CommonModule,
        MenuNavComponent,
        MenuShortcutComponent,
        HeaderRightComponent,
        HeaderLeftComponent,
        HeaderPanelComponent,
        BasketHomeComponent,
        FormsModule,
        ReactiveFormsModule,
        RouterModule,
        AppMaterialModule,
        AppServiceModule,
        NotificationModule,
        SmdFabSpeedDialComponent,
        SmdFabSpeedDialTriggerComponent,
        SmdFabSpeedDialActionsComponent,
        DragDropModule,
        PluginAutocompleteComponent,
        FieldListComponent,
        PluginSelectSearchComponent,
        PluginSelectAutocompleteSearchComponent,
        DiffusionsListComponent,
        HistoryDiffusionsListComponent,
        DragDropDirective,
        ContactAutocompleteComponent,
        ContactsFormComponent,
        ContactsGroupFormComponent,
        ContactsGroupsListComponent,
        HistoryComponent,
        AddressBanAutocompleteComponent,
        VisaWorkflowComponent,
        HistoryVisaWorkflowComponent,
        AvisWorkflowComponent,
        MaarchTreeComponent,
        MaarchFlatTreeComponent,
        ContactResourceComponent,
        ContactDetailComponent,
        AutocompleteListComponent,
        AttachmentsListComponent,
        FolderMenuComponent,
        FolderActionListComponent,
        LinkedResourceListComponent,
        NotesListComponent,
        NoteEditorComponent,
        IndexingFormComponent,
        TagInputComponent,
        FolderInputComponent,
        IssuingSiteInputComponent,
        RegisteredMailRecipientInputComponent,
        CriteriaToolComponent,
        InputCorrespondentGroupComponent,
        MessageBoxComponent,
        ColorGithubModule,
        MailSignaturesAdministrationComponent,
        SelectPageComponent,
        MailEditorComponent,
        SelectWithFilterComponent,
        FilterComponent
    ],
    providers: []
})
export class SharedModule {
    constructor(translate: TranslateService) {
        translate.setDefaultLang('fr');
    }
}
