import { Injectable } from '@angular/core';
import { TranslateService } from '@ngx-translate/core';

interface actionPages {
    'id': string; // identifier
    'label': string; // title
    'name': string; // name
    'component': string; // action service component
    'category': 'application' | 'acknowledgementReceipt' | 'externalSignatoryBook' | 'visa' | 'avis' | 'maileva' | 'alfresco' | 'multigest' | 'registeredMail' | 'recordManagement'; // category
    'description': string; // description
}

@Injectable()
export class ActionPagesService {

    private actionPages: actionPages[] = [
        {
            'id': 'redirect',
            'label': this.translate.instant('lang.redirection'),
            'name': 'redirect',
            'component': 'redirectAction',
            'category': 'application',
            'description': this.translate.instant('lang.redirectionDesc')
        },
        {
            'id': 'confirm_status',
            'label': this.translate.instant('lang.simpleConfirm'),
            'name': 'confirm_status',
            'component': 'confirmAction',
            'category': 'application',
            'description': this.translate.instant('lang.simpleConfirmDesc')
        },
        {
            'id': 'confirm_status_with_update_date',
            'label': this.translate.instant('lang.simpleConfirmWithUpdateDate'),
            'name': 'confirm_status_with_update_date',
            'component': 'updateDepartureDateAction',
            'category': 'application',
            'description': this.translate.instant('lang.simpleConfirmWithUpdateDate')
        },
        {
            'id': 'close_mail',
            'label': this.translate.instant('lang.closeMail'),
            'name': 'close_mail',
            'component': 'closeMailAction',
            'category': 'application',
            'description': this.translate.instant('lang.closeMailDesc')
        },
        {
            'id': 'set_persistent_mode_on',
            'label': this.translate.instant('lang.setPersistentModeOn'),
            'name': 'set_persistent_mode_on',
            'component': 'enabledBasketPersistenceAction',
            'category': 'application',
            'description': this.translate.instant('lang.setPersistentModeOnDesc')
        },
        {
            'id': 'set_persistent_mode_off',
            'label': this.translate.instant('lang.setPersistentModeOff'),
            'name': 'set_persistent_mode_off',
            'component': 'disabledBasketPersistenceAction',
            'category': 'application',
            'description': this.translate.instant('lang.setPersistentModeOffDesc')
        },
        {
            'id': 'mark_as_read',
            'label': this.translate.instant('lang.markAsRead'),
            'name': 'mark_as_read',
            'component': 'resMarkAsReadAction',
            'category': 'application',
            'description': this.translate.instant('lang.markAsReadDesc')
        },
        {
            'id': 'create_acknowledgement_receipt',
            'label': this.translate.instant('lang.createAcknowledgementReceipt'),
            'name': 'create_acknowledgement_receipt',
            'component': 'createAcknowledgementReceiptsAction',
            'category': 'acknowledgementReceipt',
            'description': this.translate.instant('lang.createAcknowledgementReceipt')
        },
        {
            'id': 'update_acknowledgement_send_date',
            'label': this.translate.instant('lang.updateAcknowledgementSendDateAction'),
            'name': 'update_acknowledgement_send_date',
            'component': 'updateAcknowledgementSendDateAction',
            'category': 'acknowledgementReceipt',
            'description': this.translate.instant('lang.updateAcknowledgementSendDateAction')
        },
        {
            'id': 'sendToExternalSignatureBook',
            'label': this.translate.instant('lang.sendToExternalSb'),
            'name': 'sendToExternalSignatureBook',
            'component': 'sendExternalSignatoryBookAction',
            'category': 'externalSignatoryBook',
            'description': this.translate.instant('sendExternalSignatoryBookAction', )
        },
        {
            'id': 'sendToExternalNoteBook',
            'label': this.translate.instant('lang.sendToExternalNotebook'),
            'name': 'sendToExternalNoteBook',
            'component': 'sendExternalNoteBookAction',
            'category': 'externalSignatoryBook',
            'description': this.translate.instant('lang.sendToExternalNotebook')
        },
        {
            'id': 'close_mail_and_index',
            'label': this.translate.instant('lang.closeMailAndIndex'),
            'name': 'close_mail_and_index',
            'component': 'closeAndIndexAction',
            'category': 'application',
            'description': this.translate.instant('lang.closeMailAndIndexDesc')
        },
        {
            'id': 'close_mail_with_attachment',
            'label': this.translate.instant('lang.closeMailWithAttachment'),
            'name': 'close_mail_with_attachment',
            'component': 'closeMailWithAttachmentsOrNotesAction',
            'category': 'application',
            'description': this.translate.instant('lang.closeMailWithAttachmentDesc')
        },
        {
            'id': 'visa_workflow',
            'label': this.translate.instant('lang.proceedWorkflow'),
            'name': 'visa_workflow',
            'component': 'continueVisaCircuitAction',
            'category': 'visa',
            'description': this.translate.instant('lang.proceedWorkflowDesc')
        },
        {
            'id': 'interrupt_visa',
            'label': this.translate.instant('lang.interruptWorkflow'),
            'name': 'interrupt_visa',
            'component': 'interruptVisaAction',
            'category': 'visa',
            'description': this.translate.instant('lang.interruptWorkflowDesc')
        },
        {
            'id': 'rejection_visa_redactor',
            'label': this.translate.instant('lang.resetVisaWorkflowAction'),
            'name': 'rejection_visa_redactor',
            'component': 'resetVisaAction',
            'category': 'visa',
            'description': this.translate.instant('lang.resetVisaWorkflowDesc')
        },
        {
            'id': 'rejection_visa_previous',
            'label': this.translate.instant('lang.rejectionWorkflowPrevious'),
            'name': 'rejection_visa_previous',
            'component': 'rejectVisaBackToPreviousAction',
            'category': 'visa',
            'description': this.translate.instant('lang.rejectionWorkflowPreviousDesc')
        },
        {
            'id': 'redirect_visa_entity',
            'label': this.translate.instant('lang.redirectWorkflowEntity'),
            'name': 'redirect_visa_entity',
            'component': 'redirectInitiatorEntityAction',
            'category': 'visa',
            'description': this.translate.instant('lang.redirectWorkflowEntityDesc')
        },
        {
            'id': 'send_to_visa',
            'label': this.translate.instant('lang.sendToVisa'),
            'name': 'send_to_visa',
            'component': 'sendSignatureBookAction',
            'category': 'visa',
            'description': this.translate.instant('lang.sendToVisaDesc')
        },
        {
            'id': 'send_docs_to_recommendation',
            'label': this.translate.instant('lang.sendDocsToRecommendation'),
            'name': 'send_docs_to_recommendation',
            'component': 'sendToParallelOpinion',
            'category': 'avis',
            'description': this.translate.instant('lang.sendDocsToRecommendationDesc')
        },
        {
            'id': 'validate_recommendation',
            'label': this.translate.instant('lang.validateRecommendation'),
            'name': 'validate_recommendation',
            'component': 'validateParallelOpinionDiffusionAction',
            'category': 'avis',
            'description': this.translate.instant('lang.validateRecommendationDesc')
        },
        {
            'id': 'send_to_avis',
            'label': this.translate.instant('lang.sendToAvisWf'),
            'name': 'send_to_avis',
            'component': 'sendToOpinionCircuitAction',
            'category': 'avis',
            'description': this.translate.instant('lang.sendToAvisWfDesc')
        },
        {
            'id': 'avis_workflow',
            'label': this.translate.instant('lang.proceedWorkflowAvis'),
            'name': 'avis_workflow',
            'component': 'continueOpinionCircuitAction',
            'category': 'avis',
            'description': this.translate.instant('lang.proceedWorkflowAvisDesc')
        },
        {
            'id': 'avis_workflow_simple',
            'label': this.translate.instant('lang.proceedWorkflowAvisSimple'),
            'name': 'avis_simple',
            'component': 'giveOpinionParallelAction',
            'category': 'avis',
            'description': this.translate.instant('lang.proceedWorkflowAvisSimpleDesc')
        },
        {
            'id': 'send_shipping',
            'label': this.translate.instant('lang.sendMaileva'),
            'name': 'send_shipping',
            'component': 'sendShippingAction',
            'category': 'maileva',
            'description': this.translate.instant('lang.sendMaileva', )
        },
        {
            'id': 'no_confirm_status',
            'label': this.translate.instant('lang.noConfirm'),
            'name': 'no_confirm_status',
            'component': 'noConfirmAction',
            'category': 'application',
            'description': this.translate.instant('lang.noConfirmDesc')
        },
        {
            'id': 'reconcile',
            'label': this.translate.instant('lang.reconcileResource'),
            'name': 'reconcile',
            'component': 'reconcileAction',
            'category': 'application',
            'description': this.translate.instant('lang.reconcileResourceDesc')
        },
        {
            'id': 'send_alfresco',
            'label': this.translate.instant('lang.sendAlfresco'),
            'name': 'send_alfresco',
            'component': 'sendAlfrescoAction',
            'category': 'alfresco',
            'description': this.translate.instant('lang.sendAlfresco')
        },
        {
            'id': 'send_multigest',
            'label': this.translate.instant('lang.sendMultigest'),
            'name': 'send_multigest',
            'component': 'sendMultigestAction',
            'category': 'multigest',
            'description': this.translate.instant('lang.sendMultigest')
        },
        {
            'id': 'saveRegisteredMail',
            'label': this.translate.instant('lang.saveRegisteredMail'),
            'name': 'saveRegisteredMail',
            'component': 'saveRegisteredMailAction',
            'category': 'registeredMail',
            'description': this.translate.instant('lang.saveRegisteredMail')
        },
        {
            'id': 'saveAndPrintRegisteredMail',
            'label': this.translate.instant('lang.savePrintRegisteredMail'),
            'name': 'saveAndPrintRegisteredMail',
            'component': 'saveAndPrintRegisteredMailAction',
            'category': 'registeredMail',
            'description': this.translate.instant('lang.savePrintRegisteredMail')
        },
        {
            'id': 'saveAndIndexRegisteredMail',
            'label': this.translate.instant('lang.saveIndexRegisteredMail'),
            'name': 'saveAndIndexRegisteredMail',
            'component': 'saveAndIndexRegisteredMailAction',
            'category': 'registeredMail',
            'description': this.translate.instant('lang.saveIndexRegisteredMail')
        },
        {
            'id': 'printRegisteredMail',
            'label': this.translate.instant('lang.printRegisteredMail'),
            'name': 'printRegisteredMail',
            'component': 'printRegisteredMailAction',
            'category': 'registeredMail',
            'description': this.translate.instant('lang.printRegisteredMail')
        },
        {
            'id': 'printDepositList',
            'label': this.translate.instant('lang.printDepositList'),
            'name': 'printDepositList',
            'component': 'printDepositListAction',
            'category': 'registeredMail',
            'description': this.translate.instant('lang.printDepositList')
        },
        {
            'id': 'sendToRecordManagement',
            'label': this.translate.instant('lang.sendToRecordManagement'),
            'name': 'sendToRecordManagement',
            'component': 'sendToRecordManagementAction',
            'category': 'recordManagement',
            'description': this.translate.instant('lang.sendToRecordManagement')
        },
        {
            'id': 'checkReplyRecordManagement',
            'label': this.translate.instant('lang.checkReplyRecordManagement'),
            'name': 'checkReplyRecordManagement',
            'component': 'checkReplyRecordManagementAction',
            'category': 'recordManagement',
            'description': this.translate.instant('lang.checkReplyRecordManagement')
        },
        {
            'id': 'checkAcknowledgmentRecordManagement',
            'label': this.translate.instant('lang.checkAcknowledgmentRecordManagement'),
            'name': 'checkAcknowledgmentRecordManagement',
            'component': 'checkAcknowledgmentRecordManagementAction',
            'category': 'recordManagement',
            'description': this.translate.instant('lang.checkAcknowledgmentRecordManagement')
        },
        {
            'id': 'resetRecordManagement',
            'label': this.translate.instant('lang.resetRecordManagement'),
            'name': 'resetRecordManagement',
            'component': 'resetRecordManagementAction',
            'category': 'recordManagement',
            'description': this.translate.instant('lang.resetRecordManagement')
        }
    ];


    constructor(public translate: TranslateService) { }

    getAllActionPages(id: string = null) {
        let actionPagesList: any[] = [];

        actionPagesList = actionPagesList.concat(this.actionPages);

        if (id !== null) {
            return actionPagesList.filter(elem => id == elem.id)[0];
        } else {
            return actionPagesList.sort((obj1, obj2) => {
                if (obj1.label > obj2.label) {
                    return 1;
                }

                if (obj1.label < obj2.label) {
                    return -1;
                }

                return 0;
            });
        }
    }

    getActionPageByComponent(component: string) {
        return this.actionPages.filter(elem => component == elem.component)[0];
    }
}
