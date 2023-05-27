import { NgModule, Injectable } from '@angular/core';

import { SharedModule } from './app-common.module';
import { AppRoutingModule } from './app-routing.module';

import localeFr from '@angular/common/locales/fr';
import { registerLocaleData } from '@angular/common';

import { AdministrationModule } from './administration/administration.module';

import { BrowserModule, HammerGestureConfig, HAMMER_GESTURE_CONFIG } from '@angular/platform-browser';
import { BrowserAnimationsModule } from '@angular/platform-browser/animations';
import { HttpClientModule, HTTP_INTERCEPTORS } from '@angular/common/http';
import { InternationalizationModule } from '@service/translate/internationalization.module';
import { AngularDraggableModule } from 'angular2-draggable';

import { JoyrideModule } from 'ngx-joyride';

import { PanelListComponent } from './list/panel/panel-list.component';
import { DocumentViewerModule } from './viewer/document-viewer.module';
import { AppListModule } from './app-list.module';

import { AuthInterceptor, InactivityInterceptor } from '@service/auth-interceptor.service';
import { FiltersListService } from '@service/filtersList.service';
import { CriteriaSearchService } from '@service/criteriaSearch.service';
import { FoldersService } from './folder/folders.service';
import { PrivilegeService } from '@service/privileges.service';
import { ActionPagesService } from '@service/actionPages.service';
import { ActionsService } from './actions/actions.service';

import { AppComponent } from './app.component';

import { DashboardComponent } from './home/dashboard/dashboard.component';
import { TileViewListComponent } from './home/dashboard/tile/view/list/tile-view-list.component';
import { TileViewSummaryComponent } from './home/dashboard/tile/view/summary/tile-view-summary.component';
import { TileViewChartComponent } from './home/dashboard/tile/view/chart/tile-view-chart.component';
import { TileCreateComponent } from './home/dashboard/tile/tile-create.component';
import { TileDashboardComponent } from './home/dashboard/tile/tile.component';
import { NgxChartsModule } from '@swimlane/ngx-charts';

// ACTIONS
import { ConfirmActionComponent } from './actions/confirm-action/confirm-action.component';
import { DisabledBasketPersistenceActionComponent } from './actions/disabled-basket-persistence-action/disabled-basket-persistence-action.component';
import { EnabledBasketPersistenceActionComponent } from './actions/enabled-basket-persistence-action/enabled-basket-persistence-action.component';
import { ResMarkAsReadActionComponent } from './actions/res-mark-as-read-action/res-mark-as-read-action.component';
import { CloseMailActionComponent } from './actions/close-mail-action/close-mail-action.component';
import { RejectVisaBackToPrevousActionComponent } from './actions/visa-reject-back-to-previous-action/reject-visa-back-to-previous-action.component';
import { ResetVisaActionComponent } from './actions/visa-reset-action/reset-visa-action.component';
import { InterruptVisaActionComponent } from './actions/visa-interrupt-action/interrupt-visa-action.component';
import { UpdateAcknowledgementSendDateActionComponent } from './actions/update-acknowledgement-send-date-action/update-acknowledgement-send-date-action.component';
import { CreateAcknowledgementReceiptActionComponent } from './actions/create-acknowledgement-receipt-action/create-acknowledgement-receipt-action.component';
import { CloseAndIndexActionComponent } from './actions/close-and-index-action/close-and-index-action.component';
import { UpdateDepartureDateActionComponent } from './actions/update-departure-date-action/update-departure-date-action.component';
import { SendExternalSignatoryBookActionComponent } from './actions/send-external-signatory-book-action/send-external-signatory-book-action.component';
import { SendExternalNoteBookActionComponent } from './actions/send-external-note-book-action/send-external-note-book-action.component';
import { XParaphComponent } from './actions/send-external-signatory-book-action/x-paraph/x-paraph.component';
import { MaarchParaphComponent } from './actions/send-external-signatory-book-action/maarch-paraph/maarch-paraph.component';
import { SignaturePositionComponent } from './actions/send-external-signatory-book-action/maarch-paraph/signature-position/signature-position.component';
import { DateOptionModalComponent } from './actions/send-external-signatory-book-action/maarch-paraph/signature-position/dateOption/date-option-modal.component';
import { IParaphComponent } from './actions/send-external-signatory-book-action/i-paraph/i-paraph.component';
import { IxbusParaphComponent } from './actions/send-external-signatory-book-action/ixbus-paraph/ixbus-paraph.component';
import { FastParaphComponent } from './actions/send-external-signatory-book-action/fast-paraph/fast-paraph.component';
import { ViewDocActionComponent } from './actions/view-doc-action/view-doc-action.component';
import { RedirectActionComponent } from './actions/redirect-action/redirect-action.component';
import { SendShippingActionComponent } from './actions/send-shipping-action/send-shipping-action.component';
import { RedirectInitiatorEntityActionComponent } from './actions/redirect-initiator-entity-action/redirect-initiator-entity-action.component';
import { closeMailWithAttachmentsOrNotesActionComponent } from './actions/close-mail-with-attachments-or-notes-action/close-mail-with-attachments-or-notes-action.component';
import { SendSignatureBookActionComponent } from './actions/visa-send-signature-book-action/send-signature-book-action.component';
import { ContinueVisaCircuitActionComponent } from './actions/visa-continue-circuit-action/continue-visa-circuit-action.component';
import { ContinueAvisCircuitActionComponent } from './actions/avis-continue-circuit-action/continue-avis-circuit-action.component';
import { SendAvisWorkflowComponent } from './actions/avis-workflow-send-action/send-avis-workflow-action.component';
import { SendAvisParallelComponent } from './actions/avis-parallel-send-action/send-avis-parallel-action.component';
import { GiveAvisParallelActionComponent } from './actions/avis-give-parallel-action/give-avis-parallel-action.component';
import { ValidateAvisParallelComponent } from './actions/avis-parallel-validate-action/validate-avis-parallel-action.component';
import { ReconcileActionComponent } from './actions/reconciliation-action/reconcile-action.component';
import { SendAlfrescoActionComponent } from './actions/send-alfresco-action/send-alfresco-action.component';
import { SendMultigestActionComponent } from './actions/send-multigest-action/send-multigest-action.component';
import { SaveRegisteredMailActionComponent } from './actions/save-registered-mail-action/save-registered-mail-action.component';
import { SaveAndIndexRegisteredMailActionComponent } from './actions/save-and-index-registered-mail-action/save-and-index-registered-mail-action.component';
import { SaveAndPrintRegisteredMailActionComponent } from './actions/save-and-print-registered-mail-action/save-and-print-registered-mail-action.component';
import { PrintRegisteredMailActionComponent } from './actions/print-registered-mail-action/print-registered-mail-action.component';
import { PrintDepositListActionComponent } from './actions/print-deposit-list-action/print-deposit-list-action.component';
import { SendToRecordManagementComponent } from './actions/send-to-record-management-action/send-to-record-management.component';
import { CheckReplyRecordManagementComponent } from './actions/check-reply-record-management-action/check-reply-record-management.component';
import { ResetRecordManagementComponent } from './actions/reset-record-management-action/reset-record-management.component';
import { CheckAcknowledgmentRecordManagementComponent } from './actions/check-acknowledgment-record-management-action/check-acknowledgment-record-management.component';

// PROCESS
import { ProcessComponent } from './process/process.component';
import { ToolsInformationsComponent } from './process/tools-informations/tools-informations.component';
import { IndexationComponent } from './indexation/indexation.component';
import { IndexationAttachmentsListComponent } from './attachments/indexation/indexation-attachments-list.component';
import { LinkResourceModalComponent } from './linkedResource/linkResourceModal/link-resource-modal.component';
import { HistoryWorkflowResumeComponent } from './history/history-workflow-resume/history-workflow-resume.component';
import { NoteResumeComponent } from './notes/note-resume/note-resume.component';
import { AttachmentsResumeComponent } from './attachments/attachments-resume/attachments-resume.component';
import { MailResumeComponent } from './mail/mail-resume/mail-resume.component';
import { SentResourceListComponent } from './sentResource/sent-resource-list.component';
import { SentResourcePageComponent } from './sentResource/sent-resource-page/sent-resource-page.component';
import { SentNumericPackagePageComponent } from './sentResource/sent-numeric-package-page/sent-numeric-package-page.component';
import { ThesaurusModalComponent } from './tag/indexing/thesaurus/thesaurus-modal.component';
import { SelectIndexingModelComponent } from './indexation/select-indexing-model/select-indexing-model.component';
import { FilterToolComponent } from './search/filter-tool/filter-tool.component';
import { TechnicalInformationComponent } from './indexation/technical-information/technical-information.component';
import { IndexingModelValuesSelectorComponent } from './administration/indexingModel/valuesSelector/values-selector.component';

import { SearchComponent } from './search/search.component';
import { SearchResultListComponent } from './search/result-list/search-result-list.component';
import { AboutUsComponent } from './about-us.component';
import { ActivateUserComponent } from './activate-user.component';
import { AddAvisModelModalComponent } from './avis/addAvisModel/add-avis-model-modal.component';
import { AddPrivateIndexingModelModalComponent } from './indexation/private-indexing-model/add-private-indexing-model-modal.component';
import { AddSearchTemplateModalComponent } from './search/criteria-tool/search-template/search-template-modal.component';
import { AddVisaModelModalComponent } from './visa/addVisaModel/add-visa-model-modal.component';
import { AttachmentCreateComponent } from './attachments/attachment-create/attachment-create.component';
import { AttachmentPageComponent } from './attachments/attachments-page/attachment-page.component';
import { BasketListComponent } from './list/basket-list.component';
import { ContactModalComponent } from './administration/contact/modal/contact-modal.component';
import { ContactResourceModalComponent } from './contact/contact-resource/modal/contact-resource-modal.component';
import { DocumentViewerPageComponent } from './viewer/page/document-viewer-page.component';
import { FolderCreateModalComponent } from './folder/folder-create-modal/folder-create-modal.component';
import { FolderDocumentListComponent } from './folder/document-list/folder-document-list.component';
import { FolderPinnedComponent } from './folder/folder-pinned/folder-pinned.component';
import { FolderTreeComponent } from './folder/folder-tree.component';
import { FolderUpdateComponent } from './folder/folder-update/folder-update.component';
import { FollowedDocumentListComponent } from './home/followed-list/followed-document-list.component';
import { ForgotPasswordComponent } from './login/forgotPassword/forgotPassword.component';
import { HomeComponent } from './home/home.component';
import { LoginComponent } from './login/login.component';
import { MaarchParapheurListComponent } from './home/maarch-parapheur/maarch-parapheur-list.component';
import { PanelFolderComponent } from './folder/panel/panel-folder.component';
import { PasswordModificationComponent, InfoChangePasswordModalComponent, } from './login/passwordModification/password-modification.component';
import { PrintSeparatorComponent } from './separator/print-separator/print-separator.component';
import { ProfileComponent } from './profile/profile.component';
import { AbsModalComponent } from './profile/absModal/abs-modal.component';
import { RedirectIndexingModelComponent } from './administration/indexingModel/redirectIndexingModel/redirect-indexing-model.component';
import { ResetPasswordComponent } from './login/resetPassword/reset-password.component';
import { SaveNumericPackageComponent } from './save-numeric-package.component';
import { SignatureBookComponent } from './signature-book.component';
import { VisaWorkflowModalComponent } from './visa/modal/visa-workflow-modal.component';
import { ExternalVisaWorkflowComponent } from './visa/externalVisaWorkflow/external-visa-workflow.component';
import { ProfileContactsGroupsComponent } from './profile/contacts-groups/profile-contacts-groups.component';
import { CreateExternalUserComponent } from './visa/externalVisaWorkflow/createExternalUser/create-external-user.component';

import { EditorOptionComponent } from './profile/parameters/editorOption/editor-option.component';
import { BasketColorComponent } from './profile/parameters/basketsColor/basket-color.component';
import { MyBasketsComponent } from './profile/parameters/baskets/baskets.component';
import { MySignatureMailComponent } from './profile/parameters/signatureMail/signature-mail.component';
import { MySignatureBookComponent } from './profile/parameters/signatureBook/signature-book.component';
import { ProfileHistoryComponent } from './profile/history/history.component';
import { ProfileOtherPluginComponent } from './profile/other-plugin/other-plugin.component';
import { AddinOutlookConfigurationModalComponent } from './profile/other-plugin/configuration/addin-outlook-configuration-modal.component';

import { DevToolComponent } from '@service/debug/dev-tool.component';
import { DevLangComponent } from '@service/debug/dev-lang.component';
import { AcknowledgementReceptionComponent } from './registeredMail/acknowledgement-reception/acknowledgement-reception.component';
import { DatePipe } from '@angular/common';
import { CheckSaeInterconnectionComponent } from './administration/parameter/other/checkSaeInterconnection/check-sae-interconnection.component';
import { ContactSearchModalComponentComponent } from './administration/contact/page/form/contactSearchModal/contact-search-modal.component';
import { ShippingModalComponent } from './sentResource/shippingModal/shipping-modal.component';

registerLocaleData(localeFr, 'fr-FR');
@Injectable()
export class MyHammerConfig extends HammerGestureConfig {
    overrides = <any>{
        'pinch': { enable: false },
        'rotate': { enable: false }
    };
}

@NgModule({
    imports: [
        BrowserModule,
        BrowserAnimationsModule,
        HttpClientModule,
        InternationalizationModule,
        AngularDraggableModule,
        JoyrideModule.forRoot(),
        SharedModule,
        AppRoutingModule,
        AdministrationModule,
        DocumentViewerModule,
        AppListModule,
        NgxChartsModule
    ],
    declarations: [
        AppComponent,
        DashboardComponent,
        TileViewListComponent,
        TileViewSummaryComponent,
        TileViewChartComponent,
        TileCreateComponent,
        TileDashboardComponent,
        ProcessComponent,
        ToolsInformationsComponent,
        IndexationComponent,
        IndexationAttachmentsListComponent,
        LinkResourceModalComponent,
        HistoryWorkflowResumeComponent,
        NoteResumeComponent,
        AttachmentsResumeComponent,
        MailResumeComponent,
        SentResourceListComponent,
        SentResourcePageComponent,
        SentNumericPackagePageComponent,
        ThesaurusModalComponent,
        SelectIndexingModelComponent,
        FilterToolComponent,
        PanelListComponent,
        SearchComponent,
        SearchResultListComponent,
        AboutUsComponent,
        ActivateUserComponent,
        AddAvisModelModalComponent,
        AddPrivateIndexingModelModalComponent,
        AddSearchTemplateModalComponent,
        AddVisaModelModalComponent,
        AttachmentCreateComponent,
        AttachmentPageComponent,
        BasketListComponent,
        ContactModalComponent,
        ContactResourceModalComponent,
        DocumentViewerPageComponent,
        FolderCreateModalComponent,
        FolderDocumentListComponent,
        FolderPinnedComponent,
        FolderTreeComponent,
        FolderUpdateComponent,
        FollowedDocumentListComponent,
        ForgotPasswordComponent,
        HomeComponent,
        InfoChangePasswordModalComponent,
        LoginComponent,
        MaarchParapheurListComponent,
        PanelFolderComponent,
        PasswordModificationComponent,
        PrintSeparatorComponent,
        ProfileComponent,
        AbsModalComponent,
        RedirectIndexingModelComponent,
        ResetPasswordComponent,
        SaveNumericPackageComponent,
        SignatureBookComponent,
        VisaWorkflowModalComponent,
        DevLangComponent,
        DevToolComponent,
        AcknowledgementReceptionComponent,
        ConfirmActionComponent,
        ResMarkAsReadActionComponent,
        EnabledBasketPersistenceActionComponent,
        DisabledBasketPersistenceActionComponent,
        CloseAndIndexActionComponent,
        UpdateAcknowledgementSendDateActionComponent,
        CreateAcknowledgementReceiptActionComponent,
        CloseMailActionComponent,
        RejectVisaBackToPrevousActionComponent,
        ResetVisaActionComponent,
        InterruptVisaActionComponent,
        UpdateDepartureDateActionComponent,
        SendExternalSignatoryBookActionComponent,
        SendExternalNoteBookActionComponent,
        XParaphComponent,
        MaarchParaphComponent,
        SignaturePositionComponent,
        DateOptionModalComponent,
        FastParaphComponent,
        IxbusParaphComponent,
        IParaphComponent,
        ViewDocActionComponent,
        RedirectActionComponent,
        SendShippingActionComponent,
        RedirectInitiatorEntityActionComponent,
        closeMailWithAttachmentsOrNotesActionComponent,
        SendSignatureBookActionComponent,
        ContinueVisaCircuitActionComponent,
        ContinueAvisCircuitActionComponent,
        SendAvisWorkflowComponent,
        SendAvisParallelComponent,
        GiveAvisParallelActionComponent,
        ValidateAvisParallelComponent,
        SendAlfrescoActionComponent,
        SendMultigestActionComponent,
        SaveRegisteredMailActionComponent,
        SaveAndPrintRegisteredMailActionComponent,
        SaveAndIndexRegisteredMailActionComponent,
        PrintRegisteredMailActionComponent,
        PrintDepositListActionComponent,
        ReconcileActionComponent,
        SendToRecordManagementComponent,
        CheckReplyRecordManagementComponent,
        ResetRecordManagementComponent,
        CheckAcknowledgmentRecordManagementComponent,
        TechnicalInformationComponent,
        ExternalVisaWorkflowComponent,
        ProfileContactsGroupsComponent,
        EditorOptionComponent,
        BasketColorComponent,
        MyBasketsComponent,
        MySignatureMailComponent,
        MySignatureBookComponent,
        ProfileHistoryComponent,
        ProfileOtherPluginComponent,
        AddinOutlookConfigurationModalComponent,
        CreateExternalUserComponent,
        CheckSaeInterconnectionComponent,
        ContactSearchModalComponentComponent,
        ShippingModalComponent,
        IndexingModelValuesSelectorComponent
    ],
    exports: [
        SharedModule
    ],
    providers: [
        { provide: HTTP_INTERCEPTORS, useClass: AuthInterceptor, multi: true },
        { provide: HTTP_INTERCEPTORS, useClass: InactivityInterceptor, multi: true },
        DatePipe,
        FiltersListService,
        CriteriaSearchService,
        FoldersService,
        ActionsService,
        PrivilegeService,
        ActionPagesService,
        {
            provide: HAMMER_GESTURE_CONFIG,
            useClass: MyHammerConfig
        }
    ],
    bootstrap: [AppComponent]
})
export class AppModule { }
