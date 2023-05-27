import { Injectable } from '@angular/core';
import { TranslateService } from '@ngx-translate/core';
import { HeaderService } from './header.service';

interface Menu {
    'id': string; // identifier
    'label': string; // title
    'comment': string; // description
    'route': string; // navigate to interface
    'style': string; // icon used interface
    'unit': string; // category of administration
    'groups'?: any[]; // groups of indexation if id === 'indexing'
    'shortcut': boolean; // show in panel
}

interface Administration {
    'id': string; // identifier
    'label': string; // title
    'comment': string; // description
    'route': string; // navigate to interface
    'style': string; // icone used interface
    'unit': 'organisation' | 'classement' | 'production' | 'supervision'; // category of administration
    'hasParams': boolean;
}

interface Privilege {
    'id': string; // identifier
    'label': string; // title
    'unit': string; // category of administration
    'comment': string; // description
}

@Injectable()
export class PrivilegeService {

    shortcuts: any[] = [
        {
            'id': 'followed',
            'label': 'lang.followedMail',
            'comment': 'lang.followedMail',
            'route': '/followed',
            'style': 'fas fa-star',
            'unit': 'application',
            'shortcut': true
        }
    ];

    private administrations: Administration[] = [
        {
            'id': 'admin_users',
            'label': 'lang.users',
            'comment': 'lang.adminUsersDesc',
            'route': '/administration/users',
            'unit': 'organisation',
            'style': 'fa fa-user',
            'hasParams': true
        },
        {
            'id': 'admin_groups',
            'label': 'lang.groups',
            'comment': 'lang.adminGroupsDesc',
            'route': '/administration/groups',
            'unit': 'organisation',
            'style': 'fa fa-users',
            'hasParams': false
        },
        {
            'id': 'manage_entities',
            'label': 'lang.entities',
            'comment': 'lang.adminEntitiesDesc',
            'route': '/administration/entities',
            'unit': 'organisation',
            'style': 'fa fa-sitemap',
            'hasParams': false
        },
        {
            'id': 'admin_listmodels',
            'label': 'lang.workflowModels',
            'comment': 'lang.adminWorkflowModelsDesc',
            'route': '/administration/diffusionModels',
            'unit': 'organisation',
            'style': 'fa fa-th-list',
            'hasParams': false
        },
        {
            'id': 'admin_architecture',
            'label': 'lang.documentTypes',
            'comment': 'lang.adminDocumentTypesDesc',
            'route': '/administration/doctypes',
            'unit': 'classement',
            'style': 'fa fa-suitcase',
            'hasParams': false
        },
        {
            'id': 'admin_tag',
            'label': 'lang.tags',
            'comment': 'lang.adminTagsDesc',
            'route': '/administration/tags',
            'unit': 'classement',
            'style': 'fa fa-tags',
            'hasParams': false
        },
        {
            'id': 'admin_baskets',
            'label': 'lang.baskets',
            'comment': 'lang.adminBasketsDesc',
            'route': '/administration/baskets',
            'unit': 'production',
            'style': 'fa fa-inbox',
            'hasParams': false
        },
        {
            'id': 'admin_status',
            'label': 'lang.statuses',
            'comment': 'lang.statusesAdmin',
            'route': '/administration/statuses',
            'unit': 'production',
            'style': 'fa fa-check-circle',
            'hasParams': false
        },
        {
            'id': 'admin_actions',
            'label': 'lang.actions',
            'comment': 'lang.actionsAdmin',
            'route': '/administration/actions',
            'unit': 'production',
            'style': 'fa fa-exchange-alt',
            'hasParams': false
        },
        {
            'id': 'admin_contacts',
            'label': 'lang.contacts',
            'comment': 'lang.contactsAdmin',
            'route': '/administration/contacts',
            'unit': 'production',
            'style': 'fa fa-address-book',
            'hasParams': false
        },
        {
            'id': 'admin_priorities',
            'label': 'lang.priorities',
            'comment': 'lang.priorities',
            'route': '/administration/priorities',
            'unit': 'production',
            'style': 'fa fa-clock',
            'hasParams': false
        },
        {
            'id': 'admin_templates',
            'label': 'lang.templates',
            'comment': 'lang.templatesAdmin',
            'route': '/administration/templates',
            'unit': 'production',
            'style': 'fa fa-file-alt',
            'hasParams': false
        },
        {
            'id': 'admin_indexing_models',
            'label': 'lang.indexingModels',
            'comment': 'lang.indexingModels',
            'route': '/administration/indexingModels',
            'unit': 'production',
            'style': 'fab fa-wpforms',
            'hasParams': false
        },
        {
            'id': 'admin_custom_fields',
            'label': 'lang.customFieldsAdmin',
            'comment': 'lang.customFieldsAdmin',
            'route': '/administration/customFields',
            'unit': 'production',
            'style': 'fa fa-code',
            'hasParams': false
        },
        {
            'id': 'admin_notif',
            'label': 'lang.notifications',
            'comment': 'lang.notificationsAdmin',
            'route': '/administration/notifications',
            'unit': 'production',
            'style': 'fa fa-bell',
            'hasParams': false
        },
        {
            'id': 'update_status_mail',
            'label': 'lang.updateStatus',
            'comment': 'lang.updateStatus',
            'route': '/administration/update-status',
            'unit': 'supervision',
            'style': 'fa fa-envelope-square',
            'hasParams': false
        },
        {
            'id': 'admin_docservers',
            'label': 'lang.docservers',
            'comment': 'lang.docserversAdmin',
            'route': '/administration/docservers',
            'unit': 'supervision',
            'style': 'fa fa-hdd',
            'hasParams': false
        },
        {
            'id': 'admin_parameters',
            'label': 'lang.parameters',
            'comment': 'lang.parameters',
            'route': '/administration/parameters',
            'unit': 'supervision',
            'style': 'fa fa-wrench',
            'hasParams': false
        },
        {
            'id': 'admin_password_rules',
            'label': 'lang.securities',
            'comment': 'lang.securities',
            'route': '/administration/securities',
            'unit': 'supervision',
            'style': 'fa fa-lock',
            'hasParams': false
        },
        {
            'id': 'admin_email_server',
            'label': 'lang.emailServerParam',
            'comment': 'lang.emailServerParamDesc',
            'route': '/administration/sendmail',
            'unit': 'supervision',
            'style': 'fa fa-mail-bulk',
            'hasParams': false
        },
        {
            'id': 'admin_organization_email_signatures',
            'label': 'lang.organizationEmailSignatures',
            'comment': 'lang.organizationEmailSignatures',
            'route': '/administration/organizationEmailSignatures',
            'unit': 'supervision',
            'style': 'fas fa-signature',
            'hasParams': false
        },
        {
            'id': 'admin_shippings',
            'label': 'lang.mailevaAdmin',
            'comment': 'lang.mailevaAdminDesc',
            'route': '/administration/shippings',
            'unit': 'supervision',
            'style': 'fa fa-shipping-fast',
            'hasParams': false
        },
        {
            'id': 'view_history',
            'label': 'lang.history',
            'comment': 'lang.viewHistoryDesc',
            'route': '/administration/history',
            'unit': 'supervision',
            'style': 'fa fa-history',
            'hasParams': false
        },
        {
            'id': 'view_history_batch',
            'label': 'lang.historyBatch',
            'comment': 'lang.historyBatchAdmin',
            'route': '/administration/history-batch',
            'unit': 'supervision',
            'style': 'fa fa-history',
            'hasParams': false
        },
        {
            'id': 'admin_update_control',
            'label': 'lang.updateControl',
            'comment': 'lang.updateControlDesc',
            'route': '/administration/versions-update',
            'unit': 'supervision',
            'style': 'fa fa-sync',
            'hasParams': false
        },
        {
            'id': 'admin_alfresco',
            'label': 'lang.alfresco',
            'comment': 'lang.adminAlfrescoDesc',
            'route': '/administration/alfresco',
            'unit': 'supervision',
            'style': 'alfresco',
            'hasParams': false
        },
        {
            'id': 'admin_registered_mail',
            'label': 'lang.registeredMails',
            'comment': 'lang.adminRegisteredMailDesc',
            'route': '/administration/registeredMails',
            'unit': 'supervision',
            'style': 'fas fa-dolly-flatbed',
            'hasParams': false
        },
        {
            'id': 'admin_search',
            'label': 'lang.search',
            'comment': 'lang.searchAdministration',
            'route': '/administration/search',
            'unit': 'supervision',
            'style': 'fas fa-search',
            'hasParams': false
        },
        {
            'id': 'admin_connections',
            'label': 'lang.connections',
            'comment': 'lang.connectionsDesc',
            'route': '/administration/connections/sso',
            'unit': 'supervision',
            'style': 'fas fa-plug',
            'hasParams': false
        },
        {
            'id': 'admin_attachments',
            'label': 'lang.attachments',
            'comment': 'lang.attachmentsDesc',
            'route': '/administration/attachments/types',
            'unit': 'supervision',
            'style': 'fas fa-paperclip',
            'hasParams': false
        },
        {
            'id': 'admin_multigest',
            'label': 'lang.multigest',
            'comment': 'lang.adminMultigestDesc',
            'route': '/administration/multigest',
            'unit': 'supervision',
            'style': 'multigest',
            'hasParams': false
        },
    ];

    private privileges: Privilege[] = [
        {
            'id': 'view_doc_history',
            'label': 'lang.viewDocHistory',
            'comment': 'lang.viewHistoryDesc',
            'unit': 'history'
        },
        {
            'id': 'view_full_history',
            'label': 'lang.viewFullHistory',
            'comment': 'lang.viewFullHistoryDesc',
            'unit': 'history'
        },
        {
            'id': 'edit_resource',
            'label': 'lang.editResource',
            'comment': 'lang.editResourceDesc',
            'unit': 'application'
        },
        {
            'id': 'add_links',
            'label': 'lang.addLinks',
            'comment': 'lang.addLinks',
            'unit': 'application'
        },
        {
            'id': 'manage_tags_application',
            'label': 'lang.manageTagsInApplication',
            'comment': 'lang.manageTagsInApplicationDesc',
            'unit': 'application'
        },
        {
            'id': 'create_contacts',
            'label': 'lang.manageCreateContacts',
            'comment': 'lang.manageCreateContactsDesc',
            'unit': 'application'
        },
        {
            'id': 'update_contacts',
            'label': 'lang.manageUpdateContacts',
            'comment': 'lang.manageUpdateContactsDesc',
            'unit': 'application'
        },
        {
            'id': 'update_diffusion_indexing',
            'label': 'lang.allRoles',
            'comment': 'lang.updateDiffusionWhileIndexing',
            'unit': 'diffusionList'
        },
        {
            'id': 'update_diffusion_except_recipient_indexing',
            'label': 'lang.rolesExceptAssignee',
            'comment': 'lang.updateDiffusionExceptRecipientWhileIndexing',
            'unit': 'diffusionList'
        },
        {
            'id': 'update_diffusion_process',
            'label': 'lang.allRoles',
            'comment': 'lang.updateDiffusionWhileProcess',
            'unit': 'diffusionList'
        },
        {
            'id': 'update_diffusion_except_recipient_process',
            'label': 'lang.rolesExceptAssignee',
            'comment': 'lang.updateDiffusionExceptRecipientWhileProcess',
            'unit': 'diffusionList'
        },
        {
            'id': 'update_diffusion_details',
            'label': 'lang.allRoles',
            'comment': 'lang.updateDiffusionWhileDetails',
            'unit': 'diffusionList'
        },
        {
            'id': 'update_diffusion_except_recipient_details',
            'label': 'lang.rolesExceptAssignee',
            'comment': 'lang.updateDiffusionExceptRecipientWhileDetails',
            'unit': 'diffusionList'
        },
        {
            'id': 'sendmail',
            'label': 'lang.sendmail',
            'comment': 'lang.sendmail',
            'unit': 'sendmail'
        },
        {
            'id': 'use_mail_services',
            'label': 'lang.useMailServices',
            'comment': 'lang.useMailServices',
            'unit': 'sendmail'
        },
        {
            'id': 'view_documents_with_notes',
            'label': 'lang.viewDocumentsWithNotes',
            'comment': 'lang.viewDocumentsWithNotesDesc',
            'unit': 'application'
        },
        {
            'id': 'view_technical_infos',
            'label': 'lang.showTechnicalInformations',
            'comment': 'lang.showTechnicalInformations',
            'unit': 'application'
        },
        {
            'id': 'config_avis_workflow',
            'label': 'lang.configAvisWorkflow',
            'comment': 'lang.configAvisWorkflowDesc',
            'unit': 'avis'
        },
        {
            'id': 'config_avis_workflow_in_detail',
            'label': 'lang.configAvisWorkflowInDetail',
            'comment': 'lang.configAvisWorkflowInDetailDesc',
            'unit': 'avis'
        },
        {
            'id': 'avis_documents',
            'label': 'lang.avisAnswer',
            'comment': 'lang.avisAnswerDesc',
            'unit': 'avis'
        },
        {
            'id': 'config_visa_workflow',
            'label': 'lang.configVisaWorkflow',
            'comment': 'lang.configVisaWorkflowDesc',
            'unit': 'visaWorkflow'
        },
        {
            'id': 'config_visa_workflow_in_detail',
            'label': 'lang.configVisaWorkflowInDetail',
            'comment': 'lang.configVisaWorkflowInDetailDesc',
            'unit': 'visaWorkflow'
        },
        {
            'id': 'visa_documents',
            'label': 'lang.visaAnswers',
            'comment': 'lang.visaAnswersDesc',
            'unit': 'visaWorkflow'
        },
        {
            'id': 'sign_document',
            'label': 'lang.signDocs',
            'comment': 'lang.signDocs',
            'unit': 'visaWorkflow'
        },
        {
            'id': 'modify_visa_in_signatureBook',
            'label': 'lang.modifyVisaInSignatureBook',
            'comment': 'lang.modifyVisaInSignatureBookDesc',
            'unit': 'visaWorkflow'
        },
        {
            'id': 'print_folder_doc',
            'label': 'lang.printFolderDoc',
            'comment': 'lang.printFolderDoc',
            'unit': 'application'
        },
        {
            'id': 'manage_attachments',
            'label': 'lang.manageAttachments',
            'comment': 'lang.manageAttachments',
            'unit': 'application'
        },
        {
            'id': 'view_personal_data',
            'label': 'lang.viewPersonalData',
            'comment': 'lang.viewPersonalData',
            'unit': 'confidentialityAndSecurity'
        },
        {
            'id': 'manage_personal_data',
            'label': 'lang.managePersonalData',
            'comment': 'lang.managePersonalData',
            'unit': 'confidentialityAndSecurity'
        },
        {
            'id': 'include_folders_and_followed_resources_perimeter',
            'label': 'lang.includeFolderPerimeter',
            'comment': 'lang.includeFolderPerimeter',
            'unit': 'application'
        },
        {
            'id': 'set_binding_document',
            'label': 'lang.setBindingDocument',
            'comment': 'lang.setBindingDocument',
            'unit': 'lifeCycle'
        },
        {
            'id': 'freeze_retention_rule',
            'label': 'lang.freezeRetentionRule',
            'comment': 'lang.freezeRetentionRule',
            'unit': 'lifeCycle'
        },
        {
            'id': 'add_correspondent_in_shared_groups_on_profile',
            'label': 'lang.addCorrespondentInSharedGroupsOnProfile',
            'comment': 'lang.addCorrespondentInSharedGroupsOnProfile',
            'unit': 'application'
        },
        {
            'id': 'create_public_indexing_models',
            'label': 'lang.createPublicIndexingModels',
            'comment': 'lang.createPublicIndexingModels',
            'unit': 'application'
        },
    ];

    private menus: Menu[] = [
        {
            'id': 'admin',
            'label': 'lang.administration',
            'comment': 'lang.administration',
            'route': '/administration',
            'style': 'fa fa-cogs',
            'unit': 'application',
            'shortcut': true
        },
        {
            'id': 'adv_search_mlb',
            'label': 'lang.search',
            'comment': 'lang.search',
            'route': '/search',
            'style': 'fa fa-search',
            'unit': 'application',
            'shortcut': true
        },
        {
            'id': 'entities_print_sep_mlb',
            'label': 'lang.entitiesSeparator',
            'comment': 'lang.entitiesSeparator',
            'route': '/separators/print',
            'style': 'fa fa-print',
            'unit': 'entities',
            'shortcut': false
        },
        {
            'id': 'manage_numeric_package',
            'label': 'lang.manageNumericPackage',
            'comment': 'lang.manageNumericPackage',
            'route': '/saveNumericPackage',
            'style': 'fa fa-file-archive',
            'unit': 'sendmail',
            'shortcut': false
        },
        {
            'id': 'registered_mail_receive_ar',
            'label': 'lang.arReception',
            'comment': 'lang.arReception',
            'route': '/registeredMail/acknowledgement',
            'style': 'fa fa-barcode',
            'unit': 'registeredMails',
            'shortcut': false
        },
        {
            'id': 'registered_mail_mass_import',
            'label': 'lang.importRegisteredMails',
            'comment': 'lang.importRegisteredMails',
            'route': 'RegisteredMailImportComponent__modal',
            'style': 'fas fa-dolly-flatbed',
            'unit': 'registeredMails',
            'shortcut': false
        },
        {
            'id': 'create_custom',
            'label': 'lang.installNewCustom',
            'comment': 'lang.installNewCustom',
            'route': '/install',
            'style': 'far fa-window-restore',
            'unit': 'application',
            'shortcut': false
        }
    ];

    constructor(
        public translate: TranslateService,
        public headerService: HeaderService
    ) { }

    getAllPrivileges(getLockedPrivilege: boolean, authMode: string = 'standard') {

        let priv: any[] = [];

        priv = priv.concat(this.privileges.map(elem => elem.id));
        priv = priv.concat(this.administrations.map(elem => elem.id));
        priv = priv.concat(this.menus.map(elem => elem.id));

        priv = priv.filter(elem => this.getExcludePrivilege(getLockedPrivilege, authMode).indexOf(elem) === -1);

        return priv;
    }

    getAdminMenu(ids: string[] = null) {
        let priv: any[] = [];

        priv = priv.concat(this.administrations);
        priv = priv.concat(this.menus);

        if (ids !== null) {
            return priv.filter(elem => ids.indexOf(elem.id) > -1);
        } else {
            return priv;
        }

    }

    getPrivileges(ids: string[] = null) {
        if (ids !== null) {
            return this.privileges.filter(elem => ids.indexOf(elem.id) > -1);
        } else {
            return this.privileges;
        }
    }

    getUnitsPrivileges(): Array<string> {
        return this.privileges.map(elem => elem.unit).filter((elem, pos, arr) => arr.indexOf(elem) === pos);
    }


    getPrivilegesByUnit(unit: string): Array<Privilege> {
        return this.privileges.filter(elem => elem.unit === unit);
    }

    getMenus(ids: string[] = null): Array<Menu> {
        if (ids !== null) {
            return this.menus.filter(elem => ids.indexOf(elem.id) > -1);
        } else {
            return this.menus;
        }
    }

    getCurrentUserMenus(ids: string[] = null) {
        let menus: Menu[];
        if (ids !== null) {
            menus = this.menus.filter(elem => ids.indexOf(elem.id) > -1 && this.headerService.user.privileges.indexOf(elem.id) > -1);
        } else {
            menus = this.menus.filter(elem => this.headerService.user.privileges.indexOf(elem.id) > -1);
        }

        if (this.headerService.user.groups.filter((group: any) => group.can_index === true).length > 0 && (ids === null || ids.indexOf('indexing') > -1)) {
            const indexingGroups: any[] = [];

            this.headerService.user.groups.filter((group: any) => group.can_index === true).forEach((group: any) => {
                indexingGroups.push({
                    id: group.id,
                    label: group.group_desc
                });
            });

            const indexingmenu: any = {
                'id': 'indexing',
                'label': 'lang.recordMail',
                'comment': 'lang.recordMail',
                'route': '/indexing/' + indexingGroups[0].id,
                'style': 'fa fa-file-medical',
                'unit': 'application',
                'shortcut': true,
                'groups': indexingGroups
            };
            menus.push(indexingmenu);
        }

        return menus;
    }

    getMenusByUnit(unit: string): Array<Menu> {
        return this.menus.filter(elem => elem.unit === unit);
    }

    getUnitsMenus(): Array<string> {
        return this.menus.map(elem => elem.unit).filter((elem, pos, arr) => arr.indexOf(elem) === pos);
    }

    resfreshUserShortcuts() {
        this.shortcuts = [
            {
                'id': 'followed',
                'label': 'lang.followedMail',
                'comment': 'lang.followedMail',
                'route': '/followed',
                'style': 'fas fa-star',
                'unit': 'application',
                'shortcut': true
            }
        ];

        this.shortcuts = this.shortcuts.concat(this.menus.filter(elem => elem.shortcut === true).filter(elem => this.headerService.user.privileges.indexOf(elem.id) > -1));

        if (this.headerService.user.groups.filter((group: any) => group.can_index === true).length > 0) {
            const indexingGroups: any[] = [];

            this.headerService.user.groups.filter((group: any) => group.can_index === true).forEach((group: any) => {
                indexingGroups.push({
                    id: group.id,
                    label: group.group_desc
                });
            });

            const indexingShortcut: any = {
                'id': 'indexing',
                'label': 'lang.recordMail',
                'comment': 'lang.recordMail',
                'route': '/indexing',
                'style': 'fa fa-file-medical',
                'unit': 'application',
                'shortcut': true,
                'groups': indexingGroups
            };
            this.shortcuts.push(indexingShortcut);
        }
    }

    getAdministrations(ids: string[] = null): Array<Administration> {
        if (ids !== null) {
            return this.administrations.filter(elem => ids.indexOf(elem.id) > -1);
        } else {
            return this.administrations;
        }
    }

    getCurrentUserAdministrationsByUnit(unit: string): Array<Administration> {
        if (this.hasCurrentUserPrivilege('view_history') && this.hasCurrentUserPrivilege('view_history_batch')) {
            return this.administrations.filter(elem => elem.unit === unit).filter(elem => this.headerService.user.privileges.indexOf(elem.id) > -1).filter(priv => priv.id !== 'view_history_batch');
        } else {
            return this.administrations.filter(elem => elem.unit === unit).filter(elem => this.headerService.user.privileges.indexOf(elem.id) > -1);
        }

    }

    hasCurrentUserPrivilege(privilegeId: string) {

        return this.headerService.user.privileges.indexOf(privilegeId) > -1;
    }

    private getExcludePrivilege(getLockedPrivilege: boolean, authMode: string) {
        let excludePriv = [];

        if (!getLockedPrivilege) {
            excludePriv = ['create_custom', 'admin_update_control'];
        }
        if (authMode !== 'standard') {
            excludePriv.push('admin_password_rules');
        }
        return excludePriv;
    }
}
