------------
-- DATA SAMPLE 2301
-- (Launch the application to update data to the last tag)
------------

INSERT INTO actions (id, keyword, label_action, id_status, is_system, action_page, component, history, parameters) VALUES (1, 'redirect', 'Rediriger', 'NEW', 'Y', 'redirect', 'redirectAction', 'Y', '{}');
INSERT INTO actions (id, keyword, label_action, id_status, is_system, action_page, component, history, parameters) VALUES (3, '', 'Retourner au service Courrier', 'RET', 'N', 'confirm_status', 'confirmAction', 'Y', '{}');
INSERT INTO actions (id, keyword, label_action, id_status, is_system, action_page, component, history, parameters) VALUES (5, '', 'Remettre en traitement', 'COU', 'N', 'confirm_status', 'confirmAction', 'Y', '{}');
INSERT INTO actions (id, keyword, label_action, id_status, is_system, action_page, component, history, parameters) VALUES (6, '', 'Supprimer le courrier', 'DEL', 'N', 'confirm_status', 'confirmAction', 'Y', '{}');
INSERT INTO actions (id, keyword, label_action, id_status, is_system, action_page, component, history, parameters) VALUES (18, 'redirect', 'Qualifier le courrier', 'NEW', 'N', 'redirect', 'redirectAction', 'Y', '{}');
INSERT INTO actions (id, keyword, label_action, id_status, is_system, action_page, component, history, parameters) VALUES (19, '', 'Traiter courrier', 'COU', 'N', 'confirm_status', 'confirmAction', 'N', '{}');
INSERT INTO actions (id, keyword, label_action, id_status, is_system, action_page, component, history, parameters) VALUES (20, '', 'Cloturer', 'END', 'N', 'close_mail', 'closeMailAction', 'Y', '{"requiredFields": []}');
INSERT INTO actions (id, keyword, label_action, id_status, is_system, action_page, component, history, parameters) VALUES (21, '', 'Envoyer le courrier en validation', 'VAL', 'N', NULL, 'confirmAction', 'Y', '{}');
INSERT INTO actions (id, keyword, label_action, id_status, is_system, action_page, component, history, parameters) VALUES (22, '', 'Attribuer au service', 'NEW', 'N', 'confirm_status', 'confirmAction', 'Y', '{}');
INSERT INTO actions (id, keyword, label_action, id_status, is_system, action_page, component, history, parameters) VALUES (24, 'indexing', 'Remettre en validation', 'VAL', 'N', 'confirm_status', 'confirmAction', 'Y', '{}');
INSERT INTO actions (id, keyword, label_action, id_status, is_system, action_page, component, history, parameters) VALUES (36, '', 'Envoyer pour avis', 'EAVIS', 'N', 'send_docs_to_recommendation', 'sendToParallelOpinion', 'Y', '{}');
INSERT INTO actions (id, keyword, label_action, id_status, is_system, action_page, component, history, parameters) VALUES (37, '', 'Donner un avis', '_NOSTATUS_', 'N', 'avis_workflow_simple', 'giveOpinionParallelAction', 'Y', '{}');
INSERT INTO actions (id, keyword, label_action, id_status, is_system, action_page, component, history, parameters) VALUES (114, '', 'Marquer comme lu', '', 'N', 'mark_as_read', 'resMarkAsReadAction', 'N', '{}');
INSERT INTO actions (id, keyword, label_action, id_status, is_system, action_page, component, history, parameters) VALUES (400, '', 'Envoyer un AR', '_NOSTATUS_', 'N', 'send_attachments_to_contact', 'createAcknowledgementReceiptsAction', 'Y', '{"mode": "manual"}');
INSERT INTO actions (id, keyword, label_action, id_status, is_system, action_page, component, history, parameters) VALUES (405, '', 'Viser le courrier', '_NOSTATUS_', 'N', 'confirm_status', 'confirmAction', 'Y', '{}');
INSERT INTO actions (id, keyword, label_action, id_status, is_system, action_page, component, history, parameters) VALUES (407, '', 'Renvoyer pour traitement', 'COU', 'N', 'confirm_status', 'confirmAction', 'Y', '{}');
INSERT INTO actions (id, keyword, label_action, id_status, is_system, action_page, component, history, parameters) VALUES (408, '', 'Refuser le visa et remonter le circuit', '_NOSTATUS_', 'N', 'rejection_visa_previous', 'rejectVisaBackToPreviousAction', 'N', '{}');
INSERT INTO actions (id, keyword, label_action, id_status, is_system, action_page, component, history, parameters) VALUES (410, '', 'Transmettre la réponse signée', 'EENV', 'N', 'interrupt_status', 'continueVisaCircuitAction', 'Y', '{}');
INSERT INTO actions (id, keyword, label_action, id_status, is_system, action_page, component, history, parameters) VALUES (416, '', 'Valider et poursuivre le circuit', '_NOSTATUS_', 'N', 'visa_workflow', 'continueVisaCircuitAction', 'Y', '{"errorStatus": "END", "successStatus": "_NOSTATUS_"}');
INSERT INTO actions (id, keyword, label_action, id_status, is_system, action_page, component, history, parameters) VALUES (420, '', 'Classer sans suite', 'SSUITE', 'N', 'confirm_status', 'confirmAction', 'Y', '{}');
INSERT INTO actions (id, keyword, label_action, id_status, is_system, action_page, component, history, parameters) VALUES (421, '', 'Retourner au Service Courrier', 'RET', 'N', 'confirm_status', 'confirmAction', 'Y', '{}');
INSERT INTO actions (id, keyword, label_action, id_status, is_system, action_page, component, history, parameters) VALUES (431, '', 'Envoyer en GRC', 'GRC', 'N', 'confirm_status', 'confirmAction', 'Y', '{}');
INSERT INTO actions (id, keyword, label_action, id_status, is_system, action_page, component, history, parameters) VALUES (500, '', 'Transférer au système d''archivage', 'SEND_SEDA', 'N', 'export_seda', 'sendToRecordManagementAction', 'Y', '{"errorStatus": "END", "successStatus": "SEND_SEDA"}');
INSERT INTO actions (id, keyword, label_action, id_status, is_system, action_page, component, history, parameters) VALUES (501, '', 'Valider la réception du courrier par le système d''archivage', 'ACK_SEDA', 'N', 'check_acknowledgment', 'checkAcknowledgmentRecordManagementAction', 'Y', '{}');
INSERT INTO actions (id, keyword, label_action, id_status, is_system, action_page, component, history, parameters) VALUES (502, '', 'Valider l''archivage du courrier', 'REPLY_SEDA', 'N', 'check_reply', 'checkReplyRecordManagementAction', 'Y', '{}');
INSERT INTO actions (id, keyword, label_action, id_status, is_system, action_page, component, history, parameters) VALUES (503, '', 'Purger le courrier', 'DEL', 'N', 'purge_letter', 'confirmAction', 'Y', '{}');
INSERT INTO actions (id, keyword, label_action, id_status, is_system, action_page, component, history, parameters) VALUES (504, '', 'Remise à zero du courrier', 'END', 'N', 'reset_letter', 'resetRecordManagementAction', 'Y', '{}');
INSERT INTO actions (id, keyword, label_action, id_status, is_system, action_page, component, history, parameters) VALUES (505, '', 'Clôturer avec suivi', 'STDBY', 'N', 'close_mail', 'closeMailAction', 'Y', '{"requiredFields": []}');
INSERT INTO actions (id, keyword, label_action, id_status, is_system, action_page, component, history, parameters) VALUES (506, '', 'Terminer le suivi', 'END', 'N', 'confirm_status', 'confirmAction', 'Y', '{}');
INSERT INTO actions (id, keyword, label_action, id_status, is_system, action_page, component, history, parameters) VALUES (507, '', 'Acter l’envoi', 'ENVDONE', 'N', 'confirm_status', 'confirmAction', 'Y', '{}');
INSERT INTO actions (id, keyword, label_action, id_status, is_system, action_page, component, history, parameters) VALUES (524, '', 'Activer la persistance', '_NOSTATUS_', 'N', 'set_persistent_mode_on', 'enabledBasketPersistenceAction', 'N', '{}');
INSERT INTO actions (id, keyword, label_action, id_status, is_system, action_page, component, history, parameters) VALUES (525, '', 'Désactiver la persistance', '_NOSTATUS_', 'N', 'set_persistent_mode_off', 'disabledBasketPersistenceAction', 'N', '{}');
INSERT INTO actions (id, keyword, label_action, id_status, is_system, action_page, component, history, parameters) VALUES (527, '', 'Envoyer sur la tablette (Maarch Parapheur)', 'ATT_MP', 'N', 'sendToExternalSignatureBook', 'sendExternalSignatoryBookAction', 'Y', '{"errorStatus": "END", "successStatus": "ATT_MP"}');
INSERT INTO actions (id, keyword, label_action, id_status, is_system, action_page, component, history, parameters) VALUES (528, '', 'Générer les accusés de réception', '_NOSTATUS_', 'N', 'create_acknowledgement_receipt', 'createAcknowledgementReceiptsAction', 'Y', '{"mode": "both"}');
INSERT INTO actions (id, keyword, label_action, id_status, is_system, action_page, component, history, parameters) VALUES (529, '', 'Envoyer un pli postal Maileva', '_NOSTATUS_', 'N', 'send_shipping', 'sendShippingAction', 'Y', '{}');
INSERT INTO actions (id, keyword, label_action, id_status, is_system, action_page, component, history, parameters) VALUES (531, '', 'Envoyer pour annotation sur la tablette (Maarch Parapheur)', 'ATT_MP', 'N', 'sendToExternalSignatureBook', 'sendExternalSignatoryBookAction', 'Y', '{}');
INSERT INTO actions (id, keyword, label_action, id_status, is_system, action_page, component, history, parameters) VALUES (532, '', 'Enregistrer et imprimer le recommandé', 'NEW', 'N', 'saveAndPrintRegisteredMail', 'saveAndPrintRegisteredMailAction', 'Y', '{}');
INSERT INTO actions (id, keyword, label_action, id_status, is_system, action_page, component, history, parameters) VALUES (533, '', 'Enregistrer le recommandé et rester sur la page d''indexation', 'NEW', 'N', 'saveAndIndexRegisteredMail', 'saveAndIndexRegisteredMailAction', 'Y', '{}');
INSERT INTO actions (id, keyword, label_action, id_status, is_system, action_page, component, history, parameters) VALUES (534, '', 'Imprimer le recommandé', '_NOSTATUS_', 'N', 'printRegisteredMail', 'printRegisteredMailAction', 'Y', '{}');
INSERT INTO actions (id, keyword, label_action, id_status, is_system, action_page, component, history, parameters) VALUES (535, '', 'Imprimer le descriptif de pli', '_NOSTATUS_', 'N', 'printDepositList', 'printDepositListAction', 'Y', '{}');
INSERT INTO actions (id, keyword, label_action, id_status, is_system, action_page, component, history, parameters) VALUES (536, '', 'Enregistrer le recommandé', 'NEW', 'N', 'saveRegisteredMail', 'saveRegisteredMailAction', 'Y', '{}');
INSERT INTO actions (id, keyword, label_action, id_status, is_system, action_page, component, history, parameters) VALUES (537, '', 'Quitter le traitement', '_NOSTATUS_', 'N', 'no_confirm_status', 'noConfirmAction', 'N', '{}');
INSERT INTO actions (id, keyword, label_action, id_status, is_system, action_page, component, history, parameters) VALUES (530, '', 'Générer à nouveau les accusés de réception pour impression', '_NOSTATUS_', 'N', 'create_acknowledgement_receipt', 'createAcknowledgementReceiptsAction', 'Y', '{"mode": "both"}');
INSERT INTO actions (id, keyword, label_action, id_status, is_system, action_page, component, history, parameters) VALUES (4, '', 'Enregistrer les modifications', '_NOSTATUS_', 'N', 'no_confirm_status', 'noConfirmAction', 'N', '{}');
INSERT INTO actions (id, keyword, label_action, id_status, is_system, action_page, component, history, parameters) VALUES (414, '', 'Envoyer au parapheur interne', '_NOSTATUS_', 'N', 'send_to_visa', 'sendSignatureBookAction', 'Y', '{"errorStatus": "_NOSTATUS_", "successStatus": "EVIS"}');

INSERT INTO actions_categories (action_id, category_id) VALUES (20, 'incoming');
INSERT INTO actions_categories (action_id, category_id) VALUES (20, 'outgoing');
INSERT INTO actions_categories (action_id, category_id) VALUES (20, 'internal');
INSERT INTO actions_categories (action_id, category_id) VALUES (20, 'ged_doc');
INSERT INTO actions_categories (action_id, category_id) VALUES (22, 'incoming');
INSERT INTO actions_categories (action_id, category_id) VALUES (22, 'outgoing');
INSERT INTO actions_categories (action_id, category_id) VALUES (22, 'internal');
INSERT INTO actions_categories (action_id, category_id) VALUES (22, 'ged_doc');
INSERT INTO actions_categories (action_id, category_id) VALUES (532, 'registeredMail');
INSERT INTO actions_categories (action_id, category_id) VALUES (533, 'registeredMail');
INSERT INTO actions_categories (action_id, category_id) VALUES (534, 'registeredMail');
INSERT INTO actions_categories (action_id, category_id) VALUES (535, 'registeredMail');
INSERT INTO actions_categories (action_id, category_id) VALUES (536, 'registeredMail');
INSERT INTO actions_categories (action_id, category_id) VALUES (537, 'incoming');
INSERT INTO actions_categories (action_id, category_id) VALUES (537, 'outgoing');
INSERT INTO actions_categories (action_id, category_id) VALUES (537, 'internal');
INSERT INTO actions_categories (action_id, category_id) VALUES (537, 'ged_doc');
INSERT INTO actions_categories (action_id, category_id) VALUES (537, 'registeredMail');
INSERT INTO actions_categories (action_id, category_id) VALUES (530, 'incoming');
INSERT INTO actions_categories (action_id, category_id) VALUES (530, 'outgoing');
INSERT INTO actions_categories (action_id, category_id) VALUES (530, 'internal');
INSERT INTO actions_categories (action_id, category_id) VALUES (530, 'ged_doc');
INSERT INTO actions_categories (action_id, category_id) VALUES (530, 'registeredMail');
INSERT INTO actions_categories (action_id, category_id) VALUES (4, 'incoming');
INSERT INTO actions_categories (action_id, category_id) VALUES (4, 'outgoing');
INSERT INTO actions_categories (action_id, category_id) VALUES (4, 'internal');
INSERT INTO actions_categories (action_id, category_id) VALUES (4, 'ged_doc');
INSERT INTO actions_categories (action_id, category_id) VALUES (4, 'registeredMail');
INSERT INTO actions_categories (action_id, category_id) VALUES (414, 'ged_doc');
INSERT INTO actions_categories (action_id, category_id) VALUES (414, 'incoming');
INSERT INTO actions_categories (action_id, category_id) VALUES (414, 'internal');
INSERT INTO actions_categories (action_id, category_id) VALUES (414, 'outgoing');

INSERT INTO actions_groupbaskets (id_action, where_clause, group_id, basket_id, used_in_basketlist, used_in_action_page, default_action_list) VALUES (24, '', 'COURRIER', 'RetourCourrier', 'N', 'Y', 'Y');
INSERT INTO actions_groupbaskets (id_action, where_clause, group_id, basket_id, used_in_basketlist, used_in_action_page, default_action_list) VALUES (22, '', 'COURRIER', 'RetourCourrier', 'N', 'Y', 'N');
INSERT INTO actions_groupbaskets (id_action, where_clause, group_id, basket_id, used_in_basketlist, used_in_action_page, default_action_list) VALUES (531, '', 'COURRIER', 'RetourCourrier', 'N', 'Y', 'N');
INSERT INTO actions_groupbaskets (id_action, where_clause, group_id, basket_id, used_in_basketlist, used_in_action_page, default_action_list) VALUES (18, '', 'COURRIER', 'QualificationBasket', 'N', 'Y', 'Y');
INSERT INTO actions_groupbaskets (id_action, where_clause, group_id, basket_id, used_in_basketlist, used_in_action_page, default_action_list) VALUES (18, '', 'COURRIER', 'NumericBasket', 'N', 'Y', 'Y');
INSERT INTO actions_groupbaskets (id_action, where_clause, group_id, basket_id, used_in_basketlist, used_in_action_page, default_action_list) VALUES (19, '', 'AGENT', 'CopyMailBasket', 'N', 'Y', 'Y');
INSERT INTO actions_groupbaskets (id_action, where_clause, group_id, basket_id, used_in_basketlist, used_in_action_page, default_action_list) VALUES (114, '', 'AGENT', 'CopyMailBasket', 'Y', 'N', 'N');
INSERT INTO actions_groupbaskets (id_action, where_clause, group_id, basket_id, used_in_basketlist, used_in_action_page, default_action_list) VALUES (37, '', 'ELU', 'DdeAvisBasket', 'N', 'Y', 'Y');
INSERT INTO actions_groupbaskets (id_action, where_clause, group_id, basket_id, used_in_basketlist, used_in_action_page, default_action_list) VALUES (4, '', 'ELU', 'DdeAvisBasket', 'N', 'Y', 'N');
INSERT INTO actions_groupbaskets (id_action, where_clause, group_id, basket_id, used_in_basketlist, used_in_action_page, default_action_list) VALUES (19, '', 'AGENT', 'DepartmentBasket', 'N', 'Y', 'Y');
INSERT INTO actions_groupbaskets (id_action, where_clause, group_id, basket_id, used_in_basketlist, used_in_action_page, default_action_list) VALUES (20, '', 'AGENT', 'DepartmentBasket', 'Y', 'N', 'N');
INSERT INTO actions_groupbaskets (id_action, where_clause, group_id, basket_id, used_in_basketlist, used_in_action_page, default_action_list) VALUES (3, '', 'AGENT', 'DepartmentBasket', 'Y', 'N', 'N');
INSERT INTO actions_groupbaskets (id_action, where_clause, group_id, basket_id, used_in_basketlist, used_in_action_page, default_action_list) VALUES (1, '', 'AGENT', 'DepartmentBasket', 'Y', 'N', 'N');
INSERT INTO actions_groupbaskets (id_action, where_clause, group_id, basket_id, used_in_basketlist, used_in_action_page, default_action_list) VALUES (4, '', 'AGENT', 'RetAvisBasket', 'N', 'Y', 'Y');
INSERT INTO actions_groupbaskets (id_action, where_clause, group_id, basket_id, used_in_basketlist, used_in_action_page, default_action_list) VALUES (5, '', 'AGENT', 'RetAvisBasket', 'Y', 'Y', 'N');
INSERT INTO actions_groupbaskets (id_action, where_clause, group_id, basket_id, used_in_basketlist, used_in_action_page, default_action_list) VALUES (37, '', 'AGENT', 'DdeAvisBasket', 'N', 'Y', 'N');
INSERT INTO actions_groupbaskets (id_action, where_clause, group_id, basket_id, used_in_basketlist, used_in_action_page, default_action_list) VALUES (4, '', 'AGENT', 'DdeAvisBasket', 'N', 'Y', 'Y');
INSERT INTO actions_groupbaskets (id_action, where_clause, group_id, basket_id, used_in_basketlist, used_in_action_page, default_action_list) VALUES (4, '', 'AGENT', 'SupAvisBasket', 'N', 'Y', 'Y');
INSERT INTO actions_groupbaskets (id_action, where_clause, group_id, basket_id, used_in_basketlist, used_in_action_page, default_action_list) VALUES (5, '', 'AGENT', 'SupAvisBasket', 'Y', 'Y', 'N');
INSERT INTO actions_groupbaskets (id_action, where_clause, group_id, basket_id, used_in_basketlist, used_in_action_page, default_action_list) VALUES (19, '', 'AGENT', 'SuiviParafBasket', 'N', 'Y', 'Y');
INSERT INTO actions_groupbaskets (id_action, where_clause, group_id, basket_id, used_in_basketlist, used_in_action_page, default_action_list) VALUES (19, '', 'RESPONSABLE', 'MyBasket', 'N', 'Y', 'Y');
INSERT INTO actions_groupbaskets (id_action, where_clause, group_id, basket_id, used_in_basketlist, used_in_action_page, default_action_list) VALUES (1, '', 'RESPONSABLE', 'MyBasket', 'N', 'Y', 'N');
INSERT INTO actions_groupbaskets (id_action, where_clause, group_id, basket_id, used_in_basketlist, used_in_action_page, default_action_list) VALUES (414, '', 'RESPONSABLE', 'MyBasket', 'N', 'Y', 'N');
INSERT INTO actions_groupbaskets (id_action, where_clause, group_id, basket_id, used_in_basketlist, used_in_action_page, default_action_list) VALUES (36, '', 'RESPONSABLE', 'MyBasket', 'N', 'Y', 'N');
INSERT INTO actions_groupbaskets (id_action, where_clause, group_id, basket_id, used_in_basketlist, used_in_action_page, default_action_list) VALUES (3, '', 'RESPONSABLE', 'MyBasket', 'N', 'Y', 'N');
INSERT INTO actions_groupbaskets (id_action, where_clause, group_id, basket_id, used_in_basketlist, used_in_action_page, default_action_list) VALUES (20, 'closing_date IS NULL', 'RESPONSABLE', 'MyBasket', 'N', 'Y', 'N');
INSERT INTO actions_groupbaskets (id_action, where_clause, group_id, basket_id, used_in_basketlist, used_in_action_page, default_action_list) VALUES (506, 'closing_date IS NOT NULL', 'RESPONSABLE', 'MyBasket', 'N', 'Y', 'N');
INSERT INTO actions_groupbaskets (id_action, where_clause, group_id, basket_id, used_in_basketlist, used_in_action_page, default_action_list) VALUES (400, '', 'RESPONSABLE', 'MyBasket', 'N', 'Y', 'N');
INSERT INTO actions_groupbaskets (id_action, where_clause, group_id, basket_id, used_in_basketlist, used_in_action_page, default_action_list) VALUES (527, '', 'RESPONSABLE', 'MyBasket', 'Y', 'Y', 'N');
INSERT INTO actions_groupbaskets (id_action, where_clause, group_id, basket_id, used_in_basketlist, used_in_action_page, default_action_list) VALUES (19, '', 'RESPONSABLE', 'CopyMailBasket', 'N', 'Y', 'Y');
INSERT INTO actions_groupbaskets (id_action, where_clause, group_id, basket_id, used_in_basketlist, used_in_action_page, default_action_list) VALUES (114, '', 'RESPONSABLE', 'CopyMailBasket', 'Y', 'N', 'N');
INSERT INTO actions_groupbaskets (id_action, where_clause, group_id, basket_id, used_in_basketlist, used_in_action_page, default_action_list) VALUES (19, '', 'RESPONSABLE', 'ValidAnswerBasket', 'N', 'Y', 'Y');
INSERT INTO actions_groupbaskets (id_action, where_clause, group_id, basket_id, used_in_basketlist, used_in_action_page, default_action_list) VALUES (19, '', 'RESPONSABLE', 'DepartmentBasket', 'N', 'Y', 'Y');
INSERT INTO actions_groupbaskets (id_action, where_clause, group_id, basket_id, used_in_basketlist, used_in_action_page, default_action_list) VALUES (20, '', 'RESPONSABLE', 'DepartmentBasket', 'Y', 'N', 'N');
INSERT INTO actions_groupbaskets (id_action, where_clause, group_id, basket_id, used_in_basketlist, used_in_action_page, default_action_list) VALUES (3, '', 'RESPONSABLE', 'DepartmentBasket', 'Y', 'N', 'N');
INSERT INTO actions_groupbaskets (id_action, where_clause, group_id, basket_id, used_in_basketlist, used_in_action_page, default_action_list) VALUES (1, '', 'RESPONSABLE', 'DepartmentBasket', 'Y', 'N', 'N');
INSERT INTO actions_groupbaskets (id_action, where_clause, group_id, basket_id, used_in_basketlist, used_in_action_page, default_action_list) VALUES (37, '', 'RESPONSABLE', 'DdeAvisBasket', 'N', 'Y', 'N');
INSERT INTO actions_groupbaskets (id_action, where_clause, group_id, basket_id, used_in_basketlist, used_in_action_page, default_action_list) VALUES (4, '', 'RESPONSABLE', 'DdeAvisBasket', 'N', 'Y', 'Y');
INSERT INTO actions_groupbaskets (id_action, where_clause, group_id, basket_id, used_in_basketlist, used_in_action_page, default_action_list) VALUES (4, '', 'RESPONSABLE', 'SupAvisBasket', 'N', 'Y', 'Y');
INSERT INTO actions_groupbaskets (id_action, where_clause, group_id, basket_id, used_in_basketlist, used_in_action_page, default_action_list) VALUES (5, '', 'RESPONSABLE', 'SupAvisBasket', 'Y', 'Y', 'N');
INSERT INTO actions_groupbaskets (id_action, where_clause, group_id, basket_id, used_in_basketlist, used_in_action_page, default_action_list) VALUES (4, '', 'RESPONSABLE', 'RetAvisBasket', 'N', 'Y', 'Y');
INSERT INTO actions_groupbaskets (id_action, where_clause, group_id, basket_id, used_in_basketlist, used_in_action_page, default_action_list) VALUES (5, '', 'RESPONSABLE', 'RetAvisBasket', 'Y', 'Y', 'N');
INSERT INTO actions_groupbaskets (id_action, where_clause, group_id, basket_id, used_in_basketlist, used_in_action_page, default_action_list) VALUES (405, '', 'RESPONSABLE', 'ParafBasket', 'N', 'Y', 'Y');
INSERT INTO actions_groupbaskets (id_action, where_clause, group_id, basket_id, used_in_basketlist, used_in_action_page, default_action_list) VALUES (416, '', 'RESPONSABLE', 'ParafBasket', 'N', 'Y', 'N');
INSERT INTO actions_groupbaskets (id_action, where_clause, group_id, basket_id, used_in_basketlist, used_in_action_page, default_action_list) VALUES (407, '', 'RESPONSABLE', 'ParafBasket', 'N', 'Y', 'N');
INSERT INTO actions_groupbaskets (id_action, where_clause, group_id, basket_id, used_in_basketlist, used_in_action_page, default_action_list) VALUES (408, '', 'RESPONSABLE', 'ParafBasket', 'N', 'Y', 'N');
INSERT INTO actions_groupbaskets (id_action, where_clause, group_id, basket_id, used_in_basketlist, used_in_action_page, default_action_list) VALUES (410, '', 'RESPONSABLE', 'ParafBasket', 'N', 'Y', 'N');
INSERT INTO actions_groupbaskets (id_action, where_clause, group_id, basket_id, used_in_basketlist, used_in_action_page, default_action_list) VALUES (19, '', 'RESPONSABLE', 'SuiviParafBasket', 'N', 'Y', 'Y');
INSERT INTO actions_groupbaskets (id_action, where_clause, group_id, basket_id, used_in_basketlist, used_in_action_page, default_action_list) VALUES (19, '', 'AGENT', 'SendToSignatoryBook', 'N', 'Y', 'Y');
INSERT INTO actions_groupbaskets (id_action, where_clause, group_id, basket_id, used_in_basketlist, used_in_action_page, default_action_list) VALUES (5, '', 'AGENT', 'SendToSignatoryBook', 'Y', 'N', 'N');
INSERT INTO actions_groupbaskets (id_action, where_clause, group_id, basket_id, used_in_basketlist, used_in_action_page, default_action_list) VALUES (19, '', 'RESPONSABLE', 'SendToSignatoryBook', 'N', 'Y', 'Y');
INSERT INTO actions_groupbaskets (id_action, where_clause, group_id, basket_id, used_in_basketlist, used_in_action_page, default_action_list) VALUES (5, '', 'RESPONSABLE', 'SendToSignatoryBook', 'Y', 'N', 'N');
INSERT INTO actions_groupbaskets (id_action, where_clause, group_id, basket_id, used_in_basketlist, used_in_action_page, default_action_list) VALUES (19, '', 'ELU', 'MyBasket', 'N', 'Y', 'Y');
INSERT INTO actions_groupbaskets (id_action, where_clause, group_id, basket_id, used_in_basketlist, used_in_action_page, default_action_list) VALUES (19, '', 'ARCHIVISTE', 'ToArcBasket', 'N', 'Y', 'Y');
INSERT INTO actions_groupbaskets (id_action, where_clause, group_id, basket_id, used_in_basketlist, used_in_action_page, default_action_list) VALUES (500, '', 'ARCHIVISTE', 'ToArcBasket', 'Y', 'N', 'N');
INSERT INTO actions_groupbaskets (id_action, where_clause, group_id, basket_id, used_in_basketlist, used_in_action_page, default_action_list) VALUES (501, '', 'ARCHIVISTE', 'ToArcBasket', 'Y', 'N', 'N');
INSERT INTO actions_groupbaskets (id_action, where_clause, group_id, basket_id, used_in_basketlist, used_in_action_page, default_action_list) VALUES (502, '', 'ARCHIVISTE', 'SentArcBasket', 'Y', 'N', 'N');
INSERT INTO actions_groupbaskets (id_action, where_clause, group_id, basket_id, used_in_basketlist, used_in_action_page, default_action_list) VALUES (19, '', 'ARCHIVISTE', 'SentArcBasket', 'N', 'Y', 'Y');
INSERT INTO actions_groupbaskets (id_action, where_clause, group_id, basket_id, used_in_basketlist, used_in_action_page, default_action_list) VALUES (19, '', 'ARCHIVISTE', 'AckArcBasket', 'N', 'Y', 'Y');
INSERT INTO actions_groupbaskets (id_action, where_clause, group_id, basket_id, used_in_basketlist, used_in_action_page, default_action_list) VALUES (503, '', 'ARCHIVISTE', 'AckArcBasket', 'Y', 'N', 'N');
INSERT INTO actions_groupbaskets (id_action, where_clause, group_id, basket_id, used_in_basketlist, used_in_action_page, default_action_list) VALUES (504, '', 'ARCHIVISTE', 'AckArcBasket', 'Y', 'N', 'N');
INSERT INTO actions_groupbaskets (id_action, where_clause, group_id, basket_id, used_in_basketlist, used_in_action_page, default_action_list) VALUES (19, '', 'CABINET', 'SuiviBasket', 'N', 'Y', 'Y');
INSERT INTO actions_groupbaskets (id_action, where_clause, group_id, basket_id, used_in_basketlist, used_in_action_page, default_action_list) VALUES (524, '', 'CABINET', 'SuiviBasket', 'Y', 'N', 'N');
INSERT INTO actions_groupbaskets (id_action, where_clause, group_id, basket_id, used_in_basketlist, used_in_action_page, default_action_list) VALUES (525, '', 'CABINET', 'SuiviBasket', 'Y', 'N', 'N');
INSERT INTO actions_groupbaskets (id_action, where_clause, group_id, basket_id, used_in_basketlist, used_in_action_page, default_action_list) VALUES (19, '', 'SERVICE', 'ValidationBasket', 'N', 'Y', 'Y');
INSERT INTO actions_groupbaskets (id_action, where_clause, group_id, basket_id, used_in_basketlist, used_in_action_page, default_action_list) VALUES (19, '', 'AGENT', 'Maileva_Sended', 'N', 'Y', 'Y');
INSERT INTO actions_groupbaskets (id_action, where_clause, group_id, basket_id, used_in_basketlist, used_in_action_page, default_action_list) VALUES (22, '', 'RESP_COURRIER', 'ValidationBasket', 'Y', 'Y', 'Y');
INSERT INTO actions_groupbaskets (id_action, where_clause, group_id, basket_id, used_in_basketlist, used_in_action_page, default_action_list) VALUES (420, '', 'RESP_COURRIER', 'ValidationBasket', 'Y', 'Y', 'N');
INSERT INTO actions_groupbaskets (id_action, where_clause, group_id, basket_id, used_in_basketlist, used_in_action_page, default_action_list) VALUES (3, '', 'RESP_COURRIER', 'ValidationBasket', 'Y', 'Y', 'N');
INSERT INTO actions_groupbaskets (id_action, where_clause, group_id, basket_id, used_in_basketlist, used_in_action_page, default_action_list) VALUES (528, '', 'AGENT', 'AR_Create', 'Y', 'N', 'N');
INSERT INTO actions_groupbaskets (id_action, where_clause, group_id, basket_id, used_in_basketlist, used_in_action_page, default_action_list) VALUES (537, '', 'AGENT', 'AR_Create', 'N', 'Y', 'Y');
INSERT INTO actions_groupbaskets (id_action, where_clause, group_id, basket_id, used_in_basketlist, used_in_action_page, default_action_list) VALUES (507, '', 'AGENT', 'EenvBasket', 'Y', 'Y', 'N');
INSERT INTO actions_groupbaskets (id_action, where_clause, group_id, basket_id, used_in_basketlist, used_in_action_page, default_action_list) VALUES (20, '', 'AGENT', 'EenvBasket', 'Y', 'Y', 'N');
INSERT INTO actions_groupbaskets (id_action, where_clause, group_id, basket_id, used_in_basketlist, used_in_action_page, default_action_list) VALUES (19, '', 'AGENT', 'EenvBasket', 'N', 'Y', 'Y');
INSERT INTO actions_groupbaskets (id_action, where_clause, group_id, basket_id, used_in_basketlist, used_in_action_page, default_action_list) VALUES (530, '', 'AGENT', 'AR_AlreadySend', 'Y', 'N', 'N');
INSERT INTO actions_groupbaskets (id_action, where_clause, group_id, basket_id, used_in_basketlist, used_in_action_page, default_action_list) VALUES (537, '', 'AGENT', 'AR_AlreadySend', 'N', 'Y', 'Y');
INSERT INTO actions_groupbaskets (id_action, where_clause, group_id, basket_id, used_in_basketlist, used_in_action_page, default_action_list) VALUES (507, '', 'RESPONSABLE', 'EenvBasket', 'Y', 'Y', 'N');
INSERT INTO actions_groupbaskets (id_action, where_clause, group_id, basket_id, used_in_basketlist, used_in_action_page, default_action_list) VALUES (20, '', 'RESPONSABLE', 'EenvBasket', 'Y', 'Y', 'N');
INSERT INTO actions_groupbaskets (id_action, where_clause, group_id, basket_id, used_in_basketlist, used_in_action_page, default_action_list) VALUES (19, '', 'RESPONSABLE', 'EenvBasket', 'N', 'Y', 'Y');
INSERT INTO actions_groupbaskets (id_action, where_clause, group_id, basket_id, used_in_basketlist, used_in_action_page, default_action_list) VALUES (20, '', 'AGENT', 'MyBasket', 'N', 'Y', 'N');
INSERT INTO actions_groupbaskets (id_action, where_clause, group_id, basket_id, used_in_basketlist, used_in_action_page, default_action_list) VALUES (505, '', 'AGENT', 'MyBasket', 'N', 'Y', 'N');
INSERT INTO actions_groupbaskets (id_action, where_clause, group_id, basket_id, used_in_basketlist, used_in_action_page, default_action_list) VALUES (414, '', 'AGENT', 'MyBasket', 'N', 'Y', 'N');
INSERT INTO actions_groupbaskets (id_action, where_clause, group_id, basket_id, used_in_basketlist, used_in_action_page, default_action_list) VALUES (36, '', 'AGENT', 'MyBasket', 'N', 'Y', 'N');
INSERT INTO actions_groupbaskets (id_action, where_clause, group_id, basket_id, used_in_basketlist, used_in_action_page, default_action_list) VALUES (1, '', 'AGENT', 'MyBasket', 'N', 'Y', 'N');
INSERT INTO actions_groupbaskets (id_action, where_clause, group_id, basket_id, used_in_basketlist, used_in_action_page, default_action_list) VALUES (19, '', 'AGENT', 'MyBasket', 'N', 'Y', 'Y');
INSERT INTO actions_groupbaskets (id_action, where_clause, group_id, basket_id, used_in_basketlist, used_in_action_page, default_action_list) VALUES (18, '', 'AGENT', 'outlook_mails', 'N', 'Y', 'Y');
INSERT INTO actions_groupbaskets (id_action, where_clause, group_id, basket_id, used_in_basketlist, used_in_action_page, default_action_list) VALUES (18, '', 'DIRECTEUR', 'outlook_mails', 'N', 'Y', 'Y');
INSERT INTO actions_groupbaskets (id_action, where_clause, group_id, basket_id, used_in_basketlist, used_in_action_page, default_action_list) VALUES (18, '', 'RESPONSABLE', 'outlook_mails', 'N', 'Y', 'Y');
INSERT INTO actions_groupbaskets (id_action, where_clause, group_id, basket_id, used_in_basketlist, used_in_action_page, default_action_list) VALUES (18, '', 'COURRIER', 'outlook_mails', 'N', 'Y', 'Y');
INSERT INTO actions_groupbaskets (id_action, where_clause, group_id, basket_id, used_in_basketlist, used_in_action_page, default_action_list) VALUES (18, '', 'RESP_COURRIER', 'outlook_mails', 'N', 'Y', 'Y');

INSERT INTO attachment_types (id, type_id, label, visible, email_link, signable, signed_by_default, icon, chrono, version_enabled, new_version_default) VALUES (2, 'response_project', 'Projet de réponse', true, true, true, false, 'R', true, true, true);
INSERT INTO attachment_types (id, type_id, label, visible, email_link, signable, signed_by_default, icon, chrono, version_enabled, new_version_default) VALUES (3, 'signed_response', 'Réponse signée', false, true, false, false, '', true, true, true);
INSERT INTO attachment_types (id, type_id, label, visible, email_link, signable, signed_by_default, icon, chrono, version_enabled, new_version_default) VALUES (4, 'simple_attachment', 'Pièce jointe', true, false, false, false, 'PJ', false, true, true);
INSERT INTO attachment_types (id, type_id, label, visible, email_link, signable, signed_by_default, icon, chrono, version_enabled, new_version_default) VALUES (5, 'incoming_mail_attachment', 'Pièce jointe capturée', true, false, false, false, '', false, true, true);
INSERT INTO attachment_types (id, type_id, label, visible, email_link, signable, signed_by_default, icon, chrono, version_enabled, new_version_default) VALUES (6, 'outgoing_mail', 'Courrier départ spontané', true, false, true, false, 'DS', true, true, true);
INSERT INTO attachment_types (id, type_id, label, visible, email_link, signable, signed_by_default, icon, chrono, version_enabled, new_version_default) VALUES (7, 'summary_sheet', 'Fiche de liaison', false, false, false, false, '', true, true, true);
INSERT INTO attachment_types (id, type_id, label, visible, email_link, signable, signed_by_default, icon, chrono, version_enabled, new_version_default) VALUES (8, 'acknowledgement_record_management', 'Accusé de réception (Archivage)', false, false, false, false, '', true, true, true);
INSERT INTO attachment_types (id, type_id, label, visible, email_link, signable, signed_by_default, icon, chrono, version_enabled, new_version_default) VALUES (9, 'reply_record_management', 'Réponse au transfert (Archivage)', false, false, false, false, '', true, true, true);
INSERT INTO attachment_types (id, type_id, label, visible, email_link, signable, signed_by_default, icon, chrono, version_enabled, new_version_default) VALUES (10, 'shipping_deposit_proof', 'Preuve de dépôt Maileva', false, false, false, false, 'M', false, false, false);
INSERT INTO attachment_types (id, type_id, label, visible, email_link, signable, signed_by_default, icon, chrono, version_enabled, new_version_default) VALUES (11, 'shipping_acknowledgement_of_receipt', 'Accusé de réception Maileva', false, false, false, false, 'M', false, false, false);

INSERT INTO baskets (id, coll_id, basket_id, basket_name, basket_desc, basket_clause, is_visible, enabled, basket_order, color, basket_res_order, flag_notif) VALUES (5, 'letterbox_coll', 'CopyMailBasket', 'Courriers en copie', 'Courriers en copie non clos ou sans suite', '(res_id in (select res_id from listinstance WHERE item_type = ''user_id'' and item_id = @user_id and item_mode = ''cc'') or res_id in (select res_id from listinstance WHERE item_type = ''entity_id'' and item_mode = ''cc'' and item_id in (@my_entities_id))) and status not in ( ''DEL'', ''END'', ''SSUITE'') and res_id not in (select res_id from res_mark_as_read WHERE user_id = @user_id)', 'Y', 'Y', 7, NULL, 'res_id desc', 'N');
INSERT INTO baskets (id, coll_id, basket_id, basket_name, basket_desc, basket_clause, is_visible, enabled, basket_order, color, basket_res_order, flag_notif) VALUES (9, 'letterbox_coll', 'DdeAvisBasket', 'Avis : Avis à émettre', 'Courriers nécessitant un avis', 'status = ''EAVIS'' AND res_id IN (SELECT res_id FROM listinstance WHERE item_type = ''user_id'' AND item_id = @user_id AND item_mode = ''avis'' and process_date is NULL)', 'Y', 'Y', 8, NULL, 'res_id desc', 'N');
INSERT INTO baskets (id, coll_id, basket_id, basket_name, basket_desc, basket_clause, is_visible, enabled, basket_order, color, basket_res_order, flag_notif) VALUES (8, 'letterbox_coll', 'RetourCourrier', 'Retours Courrier', 'Courriers retournés au service Courrier', 'STATUS=''RET''', 'Y', 'Y', 4, NULL, 'res_id desc', 'N');
INSERT INTO baskets (id, coll_id, basket_id, basket_name, basket_desc, basket_clause, is_visible, enabled, basket_order, color, basket_res_order, flag_notif) VALUES (6, 'letterbox_coll', 'AR_Create', 'AR - A Envoyer', 'AR non envoyés', 'dest_user = @user_id AND res_id NOT IN(select distinct res_id from acknowledgement_receipts) and status not in (''END'') and category_id = ''incoming''', 'Y', 'Y', 5, NULL, 'res_id desc', 'N');
INSERT INTO baskets (id, coll_id, basket_id, basket_name, basket_desc, basket_clause, is_visible, enabled, basket_order, color, basket_res_order, flag_notif) VALUES (11, 'letterbox_coll', 'RetAvisBasket', 'Avis : Retours partiels', 'Courriers avec avis reçus', 'status=''EAVIS'' and ((dest_user = @user_id) OR (DEST_USER IN (select user_id from users_entities WHERE entity_id IN( @my_entities)) or DESTINATION in (@subentities[@my_entities]))) and res_id IN (SELECT res_id FROM listinstance WHERE item_mode = ''avis'' and difflist_type = ''entity_id'' and process_date is not NULL and res_view_letterbox.res_id = res_id group by res_id)', 'Y', 'Y', 10, NULL, 'res_id desc', 'N');
INSERT INTO baskets (id, coll_id, basket_id, basket_name, basket_desc, basket_clause, is_visible, enabled, basket_order, color, basket_res_order, flag_notif) VALUES (12, 'letterbox_coll', 'ValidationBasket', 'Attributions à vérifier', 'Courriers signalés en attente d''instruction pour les services', 'status=''VAL''', 'Y', 'Y', 11, NULL, 'res_id desc', 'N');
INSERT INTO baskets (id, coll_id, basket_id, basket_name, basket_desc, basket_clause, is_visible, enabled, basket_order, color, basket_res_order, flag_notif) VALUES (13, 'letterbox_coll', 'InValidationBasket', 'Courriers signalés en attente d''instruction', 'Courriers signalés en attente d''instruction par le responsable', 'destination in (@my_entities, @subentities[@my_entities]) and status=''VAL''', 'Y', 'Y', 12, NULL, 'res_id desc', 'N');
INSERT INTO baskets (id, coll_id, basket_id, basket_name, basket_desc, basket_clause, is_visible, enabled, basket_order, color, basket_res_order, flag_notif) VALUES (14, 'letterbox_coll', 'LateMailBasket', 'Courriers en retard', 'Courriers en retard', 'destination in (@my_entities, @subentities[@my_primary_entity]) and (status <> ''DEL'' AND status <> ''REP'') and (now() > process_limit_date)', 'Y', 'Y', 13, NULL, 'res_id desc', 'N');
INSERT INTO baskets (id, coll_id, basket_id, basket_name, basket_desc, basket_clause, is_visible, enabled, basket_order, color, basket_res_order, flag_notif) VALUES (15, 'letterbox_coll', 'DepartmentBasket', 'Courriers de ma direction', 'Bannette de supervision', 'destination in (@my_entities, @subentities[@my_primary_entity]) and (status <> ''DEL'' AND status <> ''REP'' and status <> ''VAL'')', 'Y', 'Y', 14, NULL, 'res_id desc', 'N');
INSERT INTO baskets (id, coll_id, basket_id, basket_name, basket_desc, basket_clause, is_visible, enabled, basket_order, color, basket_res_order, flag_notif) VALUES (16, 'letterbox_coll', 'ParafBasket', 'Parapheur électronique', 'Courriers à viser ou signer dans mon parapheur', 'status in (''ESIG'', ''EVIS'') AND ((res_id, @user_id) IN (SELECT res_id, item_id FROM listinstance WHERE difflist_type = ''VISA_CIRCUIT'' and process_date ISNULL and res_view_letterbox.res_id = res_id order by listinstance_id asc limit 1))', 'Y', 'Y', 15, NULL, 'res_id desc', 'N');
INSERT INTO baskets (id, coll_id, basket_id, basket_name, basket_desc, basket_clause, is_visible, enabled, basket_order, color, basket_res_order, flag_notif) VALUES (17, 'letterbox_coll', 'SuiviParafBasket', 'Courriers en circuit de visa/signature', 'Courriers en circulation dans les parapheurs électroniques', 'status in (''ESIG'', ''EVIS'') AND dest_user = @user_id', 'Y', 'Y', 16, NULL, 'res_id desc', 'N');
INSERT INTO baskets (id, coll_id, basket_id, basket_name, basket_desc, basket_clause, is_visible, enabled, basket_order, color, basket_res_order, flag_notif) VALUES (18, 'letterbox_coll', 'SendToSignatoryBook', 'Courriers envoyés au parapheur Maarch en attente ou rejetés', 'Courriers envoyés au parapheur Maarch en attente ou rejetés', '(status = ''ATT_MP'' or status = ''REJ_SIGN'') AND dest_user = @user_id', 'Y', 'Y', 17, NULL, 'res_id desc', 'Y');
INSERT INTO baskets (id, coll_id, basket_id, basket_name, basket_desc, basket_clause, is_visible, enabled, basket_order, color, basket_res_order, flag_notif) VALUES (19, 'letterbox_coll', 'Maileva_Sended', 'Courriers transmis via Maileva', 'Courriers transmis via Maileva', 'dest_user = @user_id AND res_id IN(SELECT distinct r.res_id_master from res_attachments r inner join shippings s on s.document_id = r.res_id) and status not in (''END'')', 'Y', 'Y', 18, NULL, 'res_id desc', 'N');
INSERT INTO baskets (id, coll_id, basket_id, basket_name, basket_desc, basket_clause, is_visible, enabled, basket_order, color, basket_res_order, flag_notif) VALUES (20, 'letterbox_coll', 'ToArcBasket', 'Courriers à archiver', 'Courriers arrivés en fin de DUC à envoyer en archive intermédiaire', 'status = ''EXP_SEDA'' OR status = ''END'' OR status = ''SEND_SEDA''', 'Y', 'Y', 19, NULL, 'res_id desc', 'N');
INSERT INTO baskets (id, coll_id, basket_id, basket_name, basket_desc, basket_clause, is_visible, enabled, basket_order, color, basket_res_order, flag_notif) VALUES (21, 'letterbox_coll', 'SentArcBasket', 'Courriers en cours d''archivage', 'Courriers envoyés au SAE, en attente de réponse de transfert', 'status=''ACK_SEDA''', 'Y', 'Y', 20, NULL, 'res_id desc', 'N');
INSERT INTO baskets (id, coll_id, basket_id, basket_name, basket_desc, basket_clause, is_visible, enabled, basket_order, color, basket_res_order, flag_notif) VALUES (23, 'letterbox_coll', 'GedSampleBasket', 'Contrats arrivant à expiration (date fin contrat < 3mois)', 'Contrats arrivant à expiration (date fin contrat < 3mois)', 'custom_fields->>''1'' is not null and custom_fields->>''1'' <> '''' and date(custom_fields->>''1'') < now()+ interval ''3 months''', 'Y', 'Y', 22, NULL, 'res_id desc', 'Y');
INSERT INTO baskets (id, coll_id, basket_id, basket_name, basket_desc, basket_clause, is_visible, enabled, basket_order, color, basket_res_order, flag_notif) VALUES (7, 'letterbox_coll', 'AR_AlreadySend', 'AR transmis', 'AR en masse : transmis', 'dest_user = @user_id AND ((res_id IN(SELECT distinct res_id FROM acknowledgement_receipts WHERE creation_date is not null AND send_date is not null) and status not in (''END'')) OR res_id IN (SELECT distinct res_id FROM acknowledgement_receipts WHERE creation_date is not null AND send_date is null ))', 'Y', 'Y', 6, NULL, 'res_id desc', 'N');
INSERT INTO baskets (id, coll_id, basket_id, basket_name, basket_desc, basket_clause, is_visible, enabled, basket_order, color, basket_res_order, flag_notif) VALUES (24, 'letterbox_coll', 'IntervBasket', 'Demandes d''''intervention voirie à traiter', 'Demandes d''''intervention voirie à traiter', 'status in (''NEW'', ''COU'', ''STDBY'', ''ENVDONE'') and dest_user = @user_id and type_id = 1202', 'Y', 'Y', 23, NULL, 'res_id desc', 'Y');
INSERT INTO baskets (id, coll_id, basket_id, basket_name, basket_desc, basket_clause, is_visible, enabled, basket_order, color, basket_res_order, flag_notif) VALUES (1, 'letterbox_coll', 'QualificationBasket', 'Courriers à qualifier', 'Bannette de qualification', 'status=''INIT''', 'Y', 'Y', 0, NULL, 'res_id desc', 'N');
INSERT INTO baskets (id, coll_id, basket_id, basket_name, basket_desc, basket_clause, is_visible, enabled, basket_order, color, basket_res_order, flag_notif) VALUES (2, 'letterbox_coll', 'NumericBasket', 'Plis numériques à qualifier', 'Plis numériques à qualifier', 'status = ''NUMQUAL''', 'Y', 'Y', 1, NULL, 'res_id desc', 'N');
INSERT INTO baskets (id, coll_id, basket_id, basket_name, basket_desc, basket_clause, is_visible, enabled, basket_order, color, basket_res_order, flag_notif) VALUES (3, 'letterbox_coll', 'EenvBasket', 'Courriers à envoyer', 'Courriers visés/signés prêts à être envoyés', 'status=''EENV'' and dest_user = @user_id', 'Y', 'Y', 2, NULL, 'res_id desc', 'N');
INSERT INTO baskets (id, coll_id, basket_id, basket_name, basket_desc, basket_clause, is_visible, enabled, basket_order, color, basket_res_order, flag_notif) VALUES (22, 'letterbox_coll', 'AckArcBasket', 'Courriers archivés', 'Courriers archivés et acceptés dans le SAE', 'status=''REPLY_SEDA''', 'Y', 'Y', 21, NULL, 'res_id desc', 'N');
INSERT INTO baskets (id, coll_id, basket_id, basket_name, basket_desc, basket_clause, is_visible, enabled, basket_order, color, basket_res_order, flag_notif) VALUES (4, 'letterbox_coll', 'MyBasket', 'Courriers à traiter', 'Bannette de traitement', 'status in (''NEW'', ''COU'', ''STDBY'', ''ENVDONE'') and dest_user = @user_id', 'Y', 'Y', 3, NULL, 'res_id desc', 'Y');
INSERT INTO baskets (id, coll_id, basket_id, basket_name, basket_desc, basket_clause, is_visible, enabled, basket_order, color, basket_res_order, flag_notif) VALUES (10, 'letterbox_coll', 'SupAvisBasket', 'Avis : En attente de réponse', 'Courriers en attente d''avis', 'status=''EAVIS'' and ((dest_user = @user_id) OR (DEST_USER IN (select user_id from users_entities WHERE entity_id IN( @my_entities)) or DESTINATION in (@subentities[@my_entities]))) and res_id NOT IN (SELECT res_id FROM listinstance WHERE item_mode = ''avis'' and difflist_type = ''entity_id'' and process_date is not NULL and res_view_letterbox.res_id = res_id group by res_id) AND res_id IN (SELECT res_id FROM listinstance WHERE item_mode = ''avis'' and difflist_type = ''entity_id'' and process_date is NULL and res_view_letterbox.res_id = res_id group by res_id)', 'Y', 'Y', 9, NULL, 'res_id desc', 'N');
INSERT INTO baskets (id, coll_id, basket_id, basket_name, basket_desc, basket_clause, is_visible, enabled, basket_order, color, basket_res_order, flag_notif) VALUES (25, 'letterbox_coll', 'outlook_mails', 'Courriels importés', 'Bannette des courriels importés de MS Outlook', 'status in (''OUT'') and typist = @user_id', 'Y', 'Y', 1, NULL, 'res_id desc', 'N');

INSERT INTO configurations (id, privilege, value) VALUES (1, 'admin_email_server', '{"auth": true, "from": "test.maarch.courrier@maarch.org", "host": "smtp.globalsp.com", "port": 587, "type": "smtp", "user": "", "online": false, "secure": "tls", "charset": "utf-8", "password": ""}');
INSERT INTO configurations (id, privilege, value) VALUES (2, 'admin_search', '{"listEvent": {"defaultTab": "dashboard"}, "listDisplay": {"subInfos": [{"icon": "fa-traffic-light", "value": "getPriority", "cssClasses": ["align_leftData"]}, {"icon": "fa-calendar", "value": "getCreationAndProcessLimitDates", "cssClasses": ["align_leftData"]}, {"icon": "fa-sitemap", "value": "getAssignee", "cssClasses": ["align_leftData"]}, {"icon": "fa-suitcase", "value": "getDoctype", "cssClasses": ["align_leftData"]}, {"icon": "fa-user", "value": "getRecipients", "cssClasses": ["align_leftData"]}, {"icon": "fa-book", "value": "getSenders", "cssClasses": ["align_leftData"]}], "templateColumns": 6}}');
INSERT INTO configurations (id, privilege, value) VALUES (3, 'admin_sso', '{"url": "", "mapping": [{"ssoId": "", "maarchId": "login"}]}');
INSERT INTO configurations (id, privilege, value) VALUES (4, 'admin_document_editors', '{"java": [], "onlyoffice": {"ssl": true, "uri": "onlyoffice7.maarchcourrier.com", "port": "443", "token": "", "authorizationHeader": "Authorization"}}');
INSERT INTO configurations (id, privilege, value) VALUES (5, 'admin_parameters_watermark', '{"font": "helvetica", "posX": 30, "posY": 35, "size": 10, "text": "Copie conforme de [alt_identifier] le [date_now] [hour_now]", "angle": 0, "color": [20, 192, 30], "enabled": true, "opacity": 0.5}');
INSERT INTO configurations (id, privilege, value) VALUES (6, 'admin_shippings', '{"uri": "", "authUri": "", "enabled": false}');
INSERT INTO configurations (id, privilege, value) VALUES (7, 'admin_addin_outlook', '{"typeId": 1203, "statusId": 42, "indexingModelId": 8, "attachmentTypeId": 5}');
INSERT INTO configurations (id, privilege, value) VALUES (8, 'admin_organization_email_signatures', '{"signatures": [{"label": "Signature Organisation", "content": "<div><span><span><strong>[user.firstname] </strong></span></span><span>[user.lastname]</span></div>\\n<div><span><span><span><span><strong>[userPrimaryEntity.entity_label]</strong></span></span></span></span></div>\\n<div><span>[user.phone]</span></div>\\n<div><span><span>[userPrimaryEntity.address_number] [userPrimaryEntity.address_street], [userPrimaryEntity.address_postcode] [userPrimaryEntity.address_town]</span></span></div>\\n<div>&nbsp;</div>"}]}');
INSERT INTO configurations (id, privilege, value) VALUES (9, 'admin_export_seda', '{}');

INSERT INTO contacts (id, civility, firstname, lastname, company, department, function, address_number, address_street, address_additional1, address_additional2, address_postcode, address_town, address_country, email, phone, communication_means, notes, creator, creation_date, modification_date, enabled, custom_fields, external_id) VALUES (1, 1, 'Jean-Louis', 'ERCOLANI', 'MAARCH', '', 'Directeur Général', '11', 'Boulevard du Sud-Est', '', '', '99000', 'MAARCH LES BAINS', 'France', 'dev.maarch@maarch.org', '', NULL, 'Editeur du logiciel libre Maarch', 21, '2015-04-24 12:43:54.97424', '2016-07-25 16:28:38.498185', true, '{}', '{}');
INSERT INTO contacts (id, civility, firstname, lastname, company, department, function, address_number, address_street, address_additional1, address_additional2, address_postcode, address_town, address_country, email, phone, communication_means, notes, creator, creation_date, modification_date, enabled, custom_fields, external_id) VALUES (4, 1, 'Nicolas', 'MARTIN', 'Préfecture de Maarch Les Bains', NULL, NULL, '13', 'RUE LA PREFECTURE', NULL, NULL, '99000', 'MAARCH LES BAINS', NULL, NULL, NULL, '{"url": "https://cchaplin:maarch@demo.maarchcourrier.com"}', NULL, 21, '2018-04-18 12:43:54.97424', '2020-03-24 15:06:58.16582', true, NULL, '{"m2m": "45239273100025/COU"}');
INSERT INTO contacts (id, civility, firstname, lastname, company, department, function, address_number, address_street, address_additional1, address_additional2, address_postcode, address_town, address_country, email, phone, communication_means, notes, creator, creation_date, modification_date, enabled, custom_fields, external_id) VALUES (5, 2, 'Brigitte', 'BERGER', 'ACME', '', 'Directrice Générale', '25', 'PLACE DES MIMOSAS', NULL, '', '99000', 'MAARCH LES BAINS', 'FRANCE', 'dev.maarch@maarch.org', '', NULL, 'Archivage et Conservation des Mémoires Electroniques', 21, '2015-04-24 12:43:54.97424', '2016-07-25 16:28:38.498185', true, '{}', '{}');
INSERT INTO contacts (id, civility, firstname, lastname, company, department, function, address_number, address_street, address_additional1, address_additional2, address_postcode, address_town, address_country, email, phone, communication_means, notes, creator, creation_date, modification_date, enabled, custom_fields, external_id) VALUES (6, 1, 'Bernard', 'PASCONTENT', '', '', '', '25', 'route de Pampelone', NULL, '', '99000', 'MAARCH-LES-BAINS', '', 'bernard.pascontent@gmail.com', '06 08 09 07 55', NULL, '', 21, '2019-03-20 13:59:09.23436', NULL, true, '{}', '{}');
INSERT INTO contacts (id, civility, firstname, lastname, company, department, function, address_number, address_street, address_additional1, address_additional2, address_postcode, address_town, address_country, email, phone, communication_means, notes, creator, creation_date, modification_date, enabled, custom_fields, external_id) VALUES (7, 1, 'Jacques', 'DUPONT', '', '', '', '1', 'rue du Peuplier', NULL, '', '92000', 'NANTERRE', '', '', '', NULL, '', 21, '2019-03-20 13:59:09.23436', NULL, true, '{}', '{}');
INSERT INTO contacts (id, civility, firstname, lastname, company, department, function, address_number, address_street, address_additional1, address_additional2, address_postcode, address_town, address_country, email, phone, communication_means, notes, creator, creation_date, modification_date, enabled, custom_fields, external_id) VALUES (8, 1, 'Pierre', 'BRUNEL', '', '', '', '5', 'allée des Pommiers', NULL, '', '99000', 'MAARCH-LES-BAINS', '', 'dev.maarch@maarch.org', '06 08 09 07 55', NULL, '', 21, '2019-03-20 13:59:09.23436', NULL, true, '{}', '{}');
INSERT INTO contacts (id, civility, firstname, lastname, company, department, function, address_number, address_street, address_additional1, address_additional2, address_postcode, address_town, address_country, email, phone, communication_means, notes, creator, creation_date, modification_date, enabled, custom_fields, external_id) VALUES (9, 1, 'Eric', 'MACKIN', '', '', '', '13', 'rue du Square Carré', NULL, '', '99000', 'MAARCH-LES-BAINS', '', '', '06 11 12 13 14', NULL, '', 21, '2019-03-20 13:59:09.23436', NULL, true, '{}', '{}');
INSERT INTO contacts (id, civility, firstname, lastname, company, department, function, address_number, address_street, address_additional1, address_additional2, address_postcode, address_town, address_country, email, phone, communication_means, notes, creator, creation_date, modification_date, enabled, custom_fields, external_id) VALUES (10, 2, 'Carole', 'COTIN', 'MAARCH', '', 'Directrice Administrative et Qualité', '11', 'Boulevard du Sud-Est', NULL, '', '99000', 'MAARCH LES BAINS', 'FRANCE', 'dev.maarch@maarch.org', '', NULL, 'Editeur du logiciel libre Maarch', 21, '2015-04-24 12:43:54.97424', '2016-07-25 16:28:38.498185', true, '{}', '{}');
INSERT INTO contacts (id, civility, firstname, lastname, company, department, function, address_number, address_street, address_additional1, address_additional2, address_postcode, address_town, address_country, email, phone, communication_means, notes, creator, creation_date, modification_date, enabled, custom_fields, external_id) VALUES (11, 1, 'Martin Donald', 'PELLE', '', '', '', '17', 'rue de la Demande', NULL, '', '99000', 'MAARCH-LES-BAINS', '', 'dev.maarch@maarch.org', '01 23 24 21 22', NULL, '', 21, '2019-03-20 13:59:09.23436', NULL, true, '{}', '{}');

INSERT INTO contacts_civilities (id, label, abbreviation) VALUES (1, 'Monsieur', 'M.');
INSERT INTO contacts_civilities (id, label, abbreviation) VALUES (2, 'Madame', 'Mme');
INSERT INTO contacts_civilities (id, label, abbreviation) VALUES (3, 'Mademoiselle', 'Mlle');
INSERT INTO contacts_civilities (id, label, abbreviation) VALUES (4, 'Messieurs', 'MM.');
INSERT INTO contacts_civilities (id, label, abbreviation) VALUES (5, 'Mesdames', 'Mmes');
INSERT INTO contacts_civilities (id, label, abbreviation) VALUES (6, 'Mesdemoiselles', 'Mlles');

INSERT INTO contacts_filling (id, enable, first_threshold, second_threshold) VALUES (1, true, 33, 66);

INSERT INTO contacts_parameters (id, identifier, mandatory, filling, searchable, displayable) VALUES (1, 'civility', false, false, false, false);
INSERT INTO contacts_parameters (id, identifier, mandatory, filling, searchable, displayable) VALUES (2, 'firstname', false, true, true, true);
INSERT INTO contacts_parameters (id, identifier, mandatory, filling, searchable, displayable) VALUES (3, 'lastname', true, true, true, true);
INSERT INTO contacts_parameters (id, identifier, mandatory, filling, searchable, displayable) VALUES (4, 'company', true, false, true, true);
INSERT INTO contacts_parameters (id, identifier, mandatory, filling, searchable, displayable) VALUES (5, 'department', false, false, false, false);
INSERT INTO contacts_parameters (id, identifier, mandatory, filling, searchable, displayable) VALUES (6, 'function', false, false, false, false);
INSERT INTO contacts_parameters (id, identifier, mandatory, filling, searchable, displayable) VALUES (7, 'addressNumber', false, false, true, true);
INSERT INTO contacts_parameters (id, identifier, mandatory, filling, searchable, displayable) VALUES (8, 'addressStreet', false, true, true, true);
INSERT INTO contacts_parameters (id, identifier, mandatory, filling, searchable, displayable) VALUES (9, 'addressAdditional1', false, false, false, false);
INSERT INTO contacts_parameters (id, identifier, mandatory, filling, searchable, displayable) VALUES (10, 'addressAdditional2', false, false, false, false);
INSERT INTO contacts_parameters (id, identifier, mandatory, filling, searchable, displayable) VALUES (11, 'addressPostcode', false, true, true, true);
INSERT INTO contacts_parameters (id, identifier, mandatory, filling, searchable, displayable) VALUES (12, 'addressTown', false, true, true, true);
INSERT INTO contacts_parameters (id, identifier, mandatory, filling, searchable, displayable) VALUES (13, 'addressCountry', false, false, false, false);
INSERT INTO contacts_parameters (id, identifier, mandatory, filling, searchable, displayable) VALUES (14, 'email', false, true, false, false);
INSERT INTO contacts_parameters (id, identifier, mandatory, filling, searchable, displayable) VALUES (15, 'phone', false, true, false, false);
INSERT INTO contacts_parameters (id, identifier, mandatory, filling, searchable, displayable) VALUES (16, 'notes', false, false, false, false);
INSERT INTO contacts_parameters (id, identifier, mandatory, filling, searchable, displayable) VALUES (17, 'sector', false, false, false, false);

INSERT INTO custom_fields (id, label, type, mode, "values") VALUES (1, 'Date de fin de contrat', 'date', 'form', '[]');
INSERT INTO custom_fields (id, label, type, mode, "values") VALUES (2, 'Adresse d''intervention', 'banAutocomplete', 'form', '[]');
INSERT INTO custom_fields (id, label, type, mode, "values") VALUES (3, 'Nature', 'select', 'form', '["Courrier simple", "Courriel", "Courrier suivi", "Courrier avec AR", "Fax", "Chronopost", "Fedex", "Courrier AR", "Coursier", "Pli numérique", "Autre"]');
INSERT INTO custom_fields (id, label, type, mode, "values") VALUES (4, 'Référence courrier expéditeur', 'string', 'form', '[]');
INSERT INTO custom_fields (id, label, type, mode, "values") VALUES (5, 'Num recommandé', 'string', 'form', '[]');

INSERT INTO difflist_types (difflist_type_id, difflist_type_label, difflist_type_roles, allow_entities, is_system) VALUES ('entity_id', 'Diffusion aux services', 'dest copy avis', 'Y', 'Y');
INSERT INTO difflist_types (difflist_type_id, difflist_type_label, difflist_type_roles, allow_entities, is_system) VALUES ('type_id', 'Diffusion selon le type de document', 'dest copy', 'Y', 'Y');
INSERT INTO difflist_types (difflist_type_id, difflist_type_label, difflist_type_roles, allow_entities, is_system) VALUES ('VISA_CIRCUIT', 'Circuit de visa', 'visa sign ', 'N', 'Y');
INSERT INTO difflist_types (difflist_type_id, difflist_type_label, difflist_type_roles, allow_entities, is_system) VALUES ('AVIS_CIRCUIT', 'Circuit d''avis', 'avis ', 'N', 'Y');

INSERT INTO docserver_types (docserver_type_id, docserver_type_label, enabled, fingerprint_mode) VALUES ('DOC', 'Documents numériques', 'Y', 'SHA512');
INSERT INTO docserver_types (docserver_type_id, docserver_type_label, enabled, fingerprint_mode) VALUES ('CONVERT', 'Conversions de formats', 'Y', 'SHA256');
INSERT INTO docserver_types (docserver_type_id, docserver_type_label, enabled, fingerprint_mode) VALUES ('FULLTEXT', 'Plein texte', 'Y', 'SHA256');
INSERT INTO docserver_types (docserver_type_id, docserver_type_label, enabled, fingerprint_mode) VALUES ('TNL', 'Miniatures', 'Y', 'NONE');
INSERT INTO docserver_types (docserver_type_id, docserver_type_label, enabled, fingerprint_mode) VALUES ('TEMPLATES', 'Modèles de documents', 'Y', 'NONE');
INSERT INTO docserver_types (docserver_type_id, docserver_type_label, enabled, fingerprint_mode) VALUES ('ARCHIVETRANSFER', 'Archives numériques', 'Y', 'SHA256');
INSERT INTO docserver_types (docserver_type_id, docserver_type_label, enabled, fingerprint_mode) VALUES ('ACKNOWLEDGEMENT_RECEIPTS', 'Accusés de réception', 'Y', NULL);

INSERT INTO docservers (id, docserver_id, docserver_type_id, device_label, is_readonly, size_limit_number, actual_size_number, path_template, creation_date, coll_id) VALUES (1, 'FASTHD_AI', 'DOC', 'Dépôt documentaire issue d''imports de masse', 'Y', 50000000000, 1, '/opt/maarch/docservers/ai/', '2011-01-07 13:43:48.696644', 'letterbox_coll');
INSERT INTO docservers (id, docserver_id, docserver_type_id, device_label, is_readonly, size_limit_number, actual_size_number, path_template, creation_date, coll_id) VALUES (8, 'FULLTEXT_MLB', 'FULLTEXT', 'Dépôt de l''extraction plein texte des documents numérisés', 'N', 50000000000, 0, '/opt/maarch/docservers/fulltext_resources/', '2015-03-16 14:47:49.197164', 'letterbox_coll');
INSERT INTO docservers (id, docserver_id, docserver_type_id, device_label, is_readonly, size_limit_number, actual_size_number, path_template, creation_date, coll_id) VALUES (9, 'FULLTEXT_ATTACH', 'FULLTEXT', 'Dépôt de l''extraction plein texte des pièces jointes', 'N', 50000000000, 0, '/opt/maarch/docservers/fulltext_attachments/', '2015-03-16 14:47:49.197164', 'attachments_coll');
INSERT INTO docservers (id, docserver_id, docserver_type_id, device_label, is_readonly, size_limit_number, actual_size_number, path_template, creation_date, coll_id) VALUES (6, 'TNL_MLB', 'TNL', 'Dépôt des maniatures des documents numérisés', 'N', 50000000000, 0, '/opt/maarch/docservers/thumbnails_resources/', '2015-03-16 14:47:49.197164', 'letterbox_coll');
INSERT INTO docservers (id, docserver_id, docserver_type_id, device_label, is_readonly, size_limit_number, actual_size_number, path_template, creation_date, coll_id) VALUES (11, 'ARCHIVETRANSFER', 'ARCHIVETRANSFER', 'Dépôt des archives numériques', 'N', 50000000000, 1, '/opt/maarch/docservers/archive_transfer/', '2017-01-13 14:47:49.197164', 'archive_transfer_coll');
INSERT INTO docservers (id, docserver_id, docserver_type_id, device_label, is_readonly, size_limit_number, actual_size_number, path_template, creation_date, coll_id) VALUES (7, 'TNL_ATTACH', 'TNL', 'Dépôt des maniatures des pièces jointes', 'N', 50000000000, 0, '/opt/maarch/docservers/thumbnails_attachments/', '2015-03-16 14:47:49.197164', 'attachments_coll');
INSERT INTO docservers (id, docserver_id, docserver_type_id, device_label, is_readonly, size_limit_number, actual_size_number, path_template, creation_date, coll_id) VALUES (10, 'TEMPLATES', 'TEMPLATES', 'Dépôt des modèles de documents', 'N', 50000000000, 71511, '/opt/maarch/docservers/templates/', '2012-04-01 14:49:05.095119', 'templates');
INSERT INTO docservers (id, docserver_id, docserver_type_id, device_label, is_readonly, size_limit_number, actual_size_number, path_template, creation_date, coll_id) VALUES (3, 'FASTHD_ATTACH', 'DOC', 'Dépôt des pièces jointes', 'N', 50000000000, 1, '/opt/maarch/docservers/attachments/', '2011-01-13 14:47:49.197164', 'attachments_coll');
INSERT INTO docservers (id, docserver_id, docserver_type_id, device_label, is_readonly, size_limit_number, actual_size_number, path_template, creation_date, coll_id) VALUES (5, 'CONVERT_ATTACH', 'CONVERT', 'Dépôt des formats des pièces jointes', 'N', 50000000000, 0, '/opt/maarch/docservers/convert_attachments/', '2015-03-16 14:47:49.197164', 'attachments_coll');
INSERT INTO docservers (id, docserver_id, docserver_type_id, device_label, is_readonly, size_limit_number, actual_size_number, path_template, creation_date, coll_id) VALUES (2, 'FASTHD_MAN', 'DOC', 'Dépôt documentaire de numérisation manuelle', 'N', 50000000000, 1290730, '/opt/maarch/docservers/resources/', '2011-01-13 14:47:49.197164', 'letterbox_coll');
INSERT INTO docservers (id, docserver_id, docserver_type_id, device_label, is_readonly, size_limit_number, actual_size_number, path_template, creation_date, coll_id) VALUES (4, 'CONVERT_MLB', 'CONVERT', 'Dépôt des formats des documents numérisés', 'N', 50000000000, 0, '/opt/maarch/docservers/convert_resources/', '2015-03-16 14:47:49.197164', 'letterbox_coll');
INSERT INTO docservers (id, docserver_id, docserver_type_id, device_label, is_readonly, size_limit_number, actual_size_number, path_template, creation_date, coll_id) VALUES (12, 'ACKNOWLEDGEMENT_RECEIPTS', 'ACKNOWLEDGEMENT_RECEIPTS', 'Dépôt des AR', 'N', 50000000000, 0, '/opt/maarch/docservers/acknowledgement_receipts/', '2019-04-19 22:22:22.201904', 'letterbox_coll');

INSERT INTO doctypes (coll_id, type_id, description, enabled, doctypes_first_level_id, doctypes_second_level_id, retention_final_disposition, retention_rule, action_current_use, duration_current_use, process_delay, delay1, delay2, process_mode) VALUES ('', 101, 'Abonnements – documentation – archives', 'Y', 1, 1, 'destruction', 'compta_3_03', NULL, 365, 30, 14, 1, 'NORMAL');
INSERT INTO doctypes (coll_id, type_id, description, enabled, doctypes_first_level_id, doctypes_second_level_id, retention_final_disposition, retention_rule, action_current_use, duration_current_use, process_delay, delay1, delay2, process_mode) VALUES ('', 102, 'Convocation', 'Y', 1, 1, 'destruction', 'compta_3_03', NULL, 365, 60, 14, 1, 'NORMAL');
INSERT INTO doctypes (coll_id, type_id, description, enabled, doctypes_first_level_id, doctypes_second_level_id, retention_final_disposition, retention_rule, action_current_use, duration_current_use, process_delay, delay1, delay2, process_mode) VALUES ('', 103, 'Demande de documents', 'Y', 1, 1, 'destruction', 'compta_3_03', NULL, 365, 30, 14, 1, 'NORMAL');
INSERT INTO doctypes (coll_id, type_id, description, enabled, doctypes_first_level_id, doctypes_second_level_id, retention_final_disposition, retention_rule, action_current_use, duration_current_use, process_delay, delay1, delay2, process_mode) VALUES ('', 104, 'Demande de fournitures et matériels', 'Y', 1, 1, 'destruction', 'compta_3_03', NULL, 365, 60, 14, 1, 'NORMAL');
INSERT INTO doctypes (coll_id, type_id, description, enabled, doctypes_first_level_id, doctypes_second_level_id, retention_final_disposition, retention_rule, action_current_use, duration_current_use, process_delay, delay1, delay2, process_mode) VALUES ('', 105, 'Demande de RDV', 'Y', 1, 1, 'destruction', 'compta_3_03', NULL, 365, 60, 14, 1, 'NORMAL');
INSERT INTO doctypes (coll_id, type_id, description, enabled, doctypes_first_level_id, doctypes_second_level_id, retention_final_disposition, retention_rule, action_current_use, duration_current_use, process_delay, delay1, delay2, process_mode) VALUES ('', 106, 'Demande de renseignements', 'Y', 1, 1, 'destruction', 'compta_3_03', NULL, 365, 60, 14, 1, 'NORMAL');
INSERT INTO doctypes (coll_id, type_id, description, enabled, doctypes_first_level_id, doctypes_second_level_id, retention_final_disposition, retention_rule, action_current_use, duration_current_use, process_delay, delay1, delay2, process_mode) VALUES ('', 107, 'Demande mise à jour de fichiers', 'Y', 1, 1, 'destruction', 'compta_3_03', NULL, 365, 60, 14, 1, 'NORMAL');
INSERT INTO doctypes (coll_id, type_id, description, enabled, doctypes_first_level_id, doctypes_second_level_id, retention_final_disposition, retention_rule, action_current_use, duration_current_use, process_delay, delay1, delay2, process_mode) VALUES ('', 108, 'Demande Multi-Objet', 'Y', 1, 1, 'destruction', 'compta_3_03', NULL, 365, 60, 14, 1, 'NORMAL');
INSERT INTO doctypes (coll_id, type_id, description, enabled, doctypes_first_level_id, doctypes_second_level_id, retention_final_disposition, retention_rule, action_current_use, duration_current_use, process_delay, delay1, delay2, process_mode) VALUES ('', 109, 'Installation provisoire dans un équipement ville', 'Y', 1, 1, 'destruction', 'compta_3_03', NULL, 365, 60, 14, 1, 'NORMAL');
INSERT INTO doctypes (coll_id, type_id, description, enabled, doctypes_first_level_id, doctypes_second_level_id, retention_final_disposition, retention_rule, action_current_use, duration_current_use, process_delay, delay1, delay2, process_mode) VALUES ('', 110, 'Invitation', 'Y', 1, 1, 'destruction', 'compta_3_03', NULL, 365, 60, 14, 1, 'NORMAL');
INSERT INTO doctypes (coll_id, type_id, description, enabled, doctypes_first_level_id, doctypes_second_level_id, retention_final_disposition, retention_rule, action_current_use, duration_current_use, process_delay, delay1, delay2, process_mode) VALUES ('', 111, 'Rapport – Compte-rendu – Bilan', 'Y', 1, 1, 'destruction', 'compta_3_03', NULL, 365, 60, 14, 1, 'NORMAL');
INSERT INTO doctypes (coll_id, type_id, description, enabled, doctypes_first_level_id, doctypes_second_level_id, retention_final_disposition, retention_rule, action_current_use, duration_current_use, process_delay, delay1, delay2, process_mode) VALUES ('', 112, 'Réservation d''un local communal et scolaire', 'Y', 1, 1, 'destruction', 'compta_3_03', NULL, 365, 60, 14, 1, 'NORMAL');
INSERT INTO doctypes (coll_id, type_id, description, enabled, doctypes_first_level_id, doctypes_second_level_id, retention_final_disposition, retention_rule, action_current_use, duration_current_use, process_delay, delay1, delay2, process_mode) VALUES ('', 201, 'Pétition', 'Y', 1, 2, 'destruction', 'compta_3_03', NULL, 365, 15, 14, 1, 'NORMAL');
INSERT INTO doctypes (coll_id, type_id, description, enabled, doctypes_first_level_id, doctypes_second_level_id, retention_final_disposition, retention_rule, action_current_use, duration_current_use, process_delay, delay1, delay2, process_mode) VALUES ('', 202, 'Communication', 'Y', 1, 2, 'destruction', 'compta_3_03', NULL, 365, 60, 14, 1, 'NORMAL');
INSERT INTO doctypes (coll_id, type_id, description, enabled, doctypes_first_level_id, doctypes_second_level_id, retention_final_disposition, retention_rule, action_current_use, duration_current_use, process_delay, delay1, delay2, process_mode) VALUES ('', 203, 'Politique', 'Y', 1, 2, 'destruction', 'compta_3_03', NULL, 365, 60, 14, 1, 'NORMAL');
INSERT INTO doctypes (coll_id, type_id, description, enabled, doctypes_first_level_id, doctypes_second_level_id, retention_final_disposition, retention_rule, action_current_use, duration_current_use, process_delay, delay1, delay2, process_mode) VALUES ('', 204, 'Relations et solidarité internationales ', 'Y', 1, 2, 'destruction', 'compta_3_03', NULL, 365, 60, 14, 1, 'NORMAL');
INSERT INTO doctypes (coll_id, type_id, description, enabled, doctypes_first_level_id, doctypes_second_level_id, retention_final_disposition, retention_rule, action_current_use, duration_current_use, process_delay, delay1, delay2, process_mode) VALUES ('', 205, 'Remerciements et félicitations', 'Y', 1, 2, 'destruction', 'compta_3_03', NULL, 365, 60, 14, 1, 'NORMAL');
INSERT INTO doctypes (coll_id, type_id, description, enabled, doctypes_first_level_id, doctypes_second_level_id, retention_final_disposition, retention_rule, action_current_use, duration_current_use, process_delay, delay1, delay2, process_mode) VALUES ('', 206, 'Sécurité', 'Y', 1, 2, 'destruction', 'compta_3_03', NULL, 365, 60, 14, 1, 'NORMAL');
INSERT INTO doctypes (coll_id, type_id, description, enabled, doctypes_first_level_id, doctypes_second_level_id, retention_final_disposition, retention_rule, action_current_use, duration_current_use, process_delay, delay1, delay2, process_mode) VALUES ('', 207, 'Suggestion', 'Y', 1, 2, 'destruction', 'compta_3_03', NULL, 365, 60, 14, 1, 'NORMAL');
INSERT INTO doctypes (coll_id, type_id, description, enabled, doctypes_first_level_id, doctypes_second_level_id, retention_final_disposition, retention_rule, action_current_use, duration_current_use, process_delay, delay1, delay2, process_mode) VALUES ('', 301, 'Culture', 'Y', 1, 3, 'destruction', 'compta_3_03', NULL, 365, 60, 14, 1, 'NORMAL');
INSERT INTO doctypes (coll_id, type_id, description, enabled, doctypes_first_level_id, doctypes_second_level_id, retention_final_disposition, retention_rule, action_current_use, duration_current_use, process_delay, delay1, delay2, process_mode) VALUES ('', 302, 'Demande scolaire hors inscription et dérogation', 'Y', 1, 3, 'destruction', 'compta_3_03', NULL, 365, 60, 14, 1, 'SVR');
INSERT INTO doctypes (coll_id, type_id, description, enabled, doctypes_first_level_id, doctypes_second_level_id, retention_final_disposition, retention_rule, action_current_use, duration_current_use, process_delay, delay1, delay2, process_mode) VALUES ('', 303, 'Éducation nationale', 'Y', 1, 3, 'destruction', 'compta_3_03', NULL, 365, 60, 14, 1, 'NORMAL');
INSERT INTO doctypes (coll_id, type_id, description, enabled, doctypes_first_level_id, doctypes_second_level_id, retention_final_disposition, retention_rule, action_current_use, duration_current_use, process_delay, delay1, delay2, process_mode) VALUES ('', 304, 'Jeunesse', 'Y', 1, 3, 'destruction', 'compta_3_03', NULL, 365, 60, 14, 1, 'NORMAL');
INSERT INTO doctypes (coll_id, type_id, description, enabled, doctypes_first_level_id, doctypes_second_level_id, retention_final_disposition, retention_rule, action_current_use, duration_current_use, process_delay, delay1, delay2, process_mode) VALUES ('', 305, 'Lycées et collèges', 'Y', 1, 3, 'destruction', 'compta_3_03', NULL, 365, 60, 14, 1, 'NORMAL');
INSERT INTO doctypes (coll_id, type_id, description, enabled, doctypes_first_level_id, doctypes_second_level_id, retention_final_disposition, retention_rule, action_current_use, duration_current_use, process_delay, delay1, delay2, process_mode) VALUES ('', 306, 'Parentalité', 'Y', 1, 3, 'destruction', 'compta_3_03', NULL, 365, 60, 14, 1, 'NORMAL');
INSERT INTO doctypes (coll_id, type_id, description, enabled, doctypes_first_level_id, doctypes_second_level_id, retention_final_disposition, retention_rule, action_current_use, duration_current_use, process_delay, delay1, delay2, process_mode) VALUES ('', 307, 'Petite Enfance', 'Y', 1, 3, 'destruction', 'compta_3_03', NULL, 365, 60, 14, 1, 'NORMAL');
INSERT INTO doctypes (coll_id, type_id, description, enabled, doctypes_first_level_id, doctypes_second_level_id, retention_final_disposition, retention_rule, action_current_use, duration_current_use, process_delay, delay1, delay2, process_mode) VALUES ('', 308, 'Sport', 'Y', 1, 3, 'destruction', 'compta_3_03', NULL, 365, 60, 14, 1, 'NORMAL');
INSERT INTO doctypes (coll_id, type_id, description, enabled, doctypes_first_level_id, doctypes_second_level_id, retention_final_disposition, retention_rule, action_current_use, duration_current_use, process_delay, delay1, delay2, process_mode) VALUES ('', 401, 'Contestation financière', 'Y', 1, 4, 'destruction', 'compta_3_03', NULL, 365, 60, 14, 1, 'NORMAL');
INSERT INTO doctypes (coll_id, type_id, description, enabled, doctypes_first_level_id, doctypes_second_level_id, retention_final_disposition, retention_rule, action_current_use, duration_current_use, process_delay, delay1, delay2, process_mode) VALUES ('', 402, 'Contrat de prêt', 'Y', 1, 4, 'destruction', 'compta_3_03', NULL, 365, 60, 14, 1, 'NORMAL');
INSERT INTO doctypes (coll_id, type_id, description, enabled, doctypes_first_level_id, doctypes_second_level_id, retention_final_disposition, retention_rule, action_current_use, duration_current_use, process_delay, delay1, delay2, process_mode) VALUES ('', 403, 'Garantie d''emprunt', 'Y', 1, 4, 'destruction', 'compta_3_03', NULL, 365, 60, 14, 1, 'NORMAL');
INSERT INTO doctypes (coll_id, type_id, description, enabled, doctypes_first_level_id, doctypes_second_level_id, retention_final_disposition, retention_rule, action_current_use, duration_current_use, process_delay, delay1, delay2, process_mode) VALUES ('', 404, 'Paiement', 'Y', 1, 4, 'destruction', 'compta_3_03', NULL, 365, 60, 14, 1, 'NORMAL');
INSERT INTO doctypes (coll_id, type_id, description, enabled, doctypes_first_level_id, doctypes_second_level_id, retention_final_disposition, retention_rule, action_current_use, duration_current_use, process_delay, delay1, delay2, process_mode) VALUES ('', 405, 'Quotient familial', 'Y', 1, 4, 'destruction', 'compta_3_03', NULL, 365, 60, 14, 1, 'NORMAL');
INSERT INTO doctypes (coll_id, type_id, description, enabled, doctypes_first_level_id, doctypes_second_level_id, retention_final_disposition, retention_rule, action_current_use, duration_current_use, process_delay, delay1, delay2, process_mode) VALUES ('', 406, 'Subvention', 'Y', 1, 4, 'destruction', 'compta_3_03', NULL, 365, 60, 14, 1, 'NORMAL');
INSERT INTO doctypes (coll_id, type_id, description, enabled, doctypes_first_level_id, doctypes_second_level_id, retention_final_disposition, retention_rule, action_current_use, duration_current_use, process_delay, delay1, delay2, process_mode) VALUES ('', 407, 'Facture ou avoir', 'Y', 1, 4, 'destruction', 'compta_3_03', NULL, 365, 60, 14, 1, 'NORMAL');
INSERT INTO doctypes (coll_id, type_id, description, enabled, doctypes_first_level_id, doctypes_second_level_id, retention_final_disposition, retention_rule, action_current_use, duration_current_use, process_delay, delay1, delay2, process_mode) VALUES ('', 408, 'Proposition financière', 'Y', 1, 4, 'destruction', 'compta_3_03', NULL, 365, 60, 14, 1, 'NORMAL');
INSERT INTO doctypes (coll_id, type_id, description, enabled, doctypes_first_level_id, doctypes_second_level_id, retention_final_disposition, retention_rule, action_current_use, duration_current_use, process_delay, delay1, delay2, process_mode) VALUES ('', 501, 'Hospitalisation d''office', 'Y', 1, 5, 'destruction', 'compta_3_03', NULL, 365, 2, 14, 1, 'NORMAL');
INSERT INTO doctypes (coll_id, type_id, description, enabled, doctypes_first_level_id, doctypes_second_level_id, retention_final_disposition, retention_rule, action_current_use, duration_current_use, process_delay, delay1, delay2, process_mode) VALUES ('', 502, 'Mise en demeure', 'Y', 1, 5, 'destruction', 'compta_3_03', NULL, 365, 60, 14, 1, 'NORMAL');
INSERT INTO doctypes (coll_id, type_id, description, enabled, doctypes_first_level_id, doctypes_second_level_id, retention_final_disposition, retention_rule, action_current_use, duration_current_use, process_delay, delay1, delay2, process_mode) VALUES ('', 503, 'Plainte', 'Y', 1, 5, 'destruction', 'compta_3_03', NULL, 365, 60, 14, 1, 'NORMAL');
INSERT INTO doctypes (coll_id, type_id, description, enabled, doctypes_first_level_id, doctypes_second_level_id, retention_final_disposition, retention_rule, action_current_use, duration_current_use, process_delay, delay1, delay2, process_mode) VALUES ('', 504, 'Recours contentieux', 'Y', 1, 5, 'destruction', 'compta_3_03', NULL, 365, 60, 14, 1, 'NORMAL');
INSERT INTO doctypes (coll_id, type_id, description, enabled, doctypes_first_level_id, doctypes_second_level_id, retention_final_disposition, retention_rule, action_current_use, duration_current_use, process_delay, delay1, delay2, process_mode) VALUES ('', 505, 'Recours gracieux et réclamations', 'Y', 1, 5, 'destruction', 'compta_3_03', NULL, 365, 60, 14, 1, 'NORMAL');
INSERT INTO doctypes (coll_id, type_id, description, enabled, doctypes_first_level_id, doctypes_second_level_id, retention_final_disposition, retention_rule, action_current_use, duration_current_use, process_delay, delay1, delay2, process_mode) VALUES ('', 601, 'Débits de boisson', 'Y', 1, 6, 'destruction', 'compta_3_03', NULL, 365, 60, 14, 1, 'SVR');
INSERT INTO doctypes (coll_id, type_id, description, enabled, doctypes_first_level_id, doctypes_second_level_id, retention_final_disposition, retention_rule, action_current_use, duration_current_use, process_delay, delay1, delay2, process_mode) VALUES ('', 602, 'Demande d’État Civil', 'Y', 1, 6, 'destruction', 'compta_3_03', NULL, 365, 60, 14, 1, 'NORMAL');
INSERT INTO doctypes (coll_id, type_id, description, enabled, doctypes_first_level_id, doctypes_second_level_id, retention_final_disposition, retention_rule, action_current_use, duration_current_use, process_delay, delay1, delay2, process_mode) VALUES ('', 603, 'Élections', 'Y', 1, 6, 'destruction', 'compta_3_03', NULL, 365, 60, 14, 1, 'NORMAL');
INSERT INTO doctypes (coll_id, type_id, description, enabled, doctypes_first_level_id, doctypes_second_level_id, retention_final_disposition, retention_rule, action_current_use, duration_current_use, process_delay, delay1, delay2, process_mode) VALUES ('', 604, 'Étrangers', 'Y', 1, 6, 'destruction', 'compta_3_03', NULL, 365, 60, 14, 1, 'NORMAL');
INSERT INTO doctypes (coll_id, type_id, description, enabled, doctypes_first_level_id, doctypes_second_level_id, retention_final_disposition, retention_rule, action_current_use, duration_current_use, process_delay, delay1, delay2, process_mode) VALUES ('', 605, 'Marché', 'Y', 1, 6, 'destruction', 'compta_3_03', NULL, 365, 60, 14, 1, 'NORMAL');
INSERT INTO doctypes (coll_id, type_id, description, enabled, doctypes_first_level_id, doctypes_second_level_id, retention_final_disposition, retention_rule, action_current_use, duration_current_use, process_delay, delay1, delay2, process_mode) VALUES ('', 606, 'Médaille du travail', 'Y', 1, 6, 'destruction', 'compta_3_03', NULL, 365, 60, 14, 1, 'NORMAL');
INSERT INTO doctypes (coll_id, type_id, description, enabled, doctypes_first_level_id, doctypes_second_level_id, retention_final_disposition, retention_rule, action_current_use, duration_current_use, process_delay, delay1, delay2, process_mode) VALUES ('', 607, 'Stationnement taxi', 'Y', 1, 6, 'destruction', 'compta_3_03', NULL, 365, 60, 14, 1, 'NORMAL');
INSERT INTO doctypes (coll_id, type_id, description, enabled, doctypes_first_level_id, doctypes_second_level_id, retention_final_disposition, retention_rule, action_current_use, duration_current_use, process_delay, delay1, delay2, process_mode) VALUES ('', 608, 'Vente au déballage', 'Y', 1, 6, 'destruction', 'compta_3_03', NULL, 365, 60, 14, 1, 'NORMAL');
INSERT INTO doctypes (coll_id, type_id, description, enabled, doctypes_first_level_id, doctypes_second_level_id, retention_final_disposition, retention_rule, action_current_use, duration_current_use, process_delay, delay1, delay2, process_mode) VALUES ('', 701, 'Arrêts de travail et maladie', 'Y', 1, 7, 'destruction', 'compta_3_03', NULL, 365, 60, 14, 1, 'NORMAL');
INSERT INTO doctypes (coll_id, type_id, description, enabled, doctypes_first_level_id, doctypes_second_level_id, retention_final_disposition, retention_rule, action_current_use, duration_current_use, process_delay, delay1, delay2, process_mode) VALUES ('', 702, 'Assurance du personnel', 'Y', 1, 7, 'destruction', 'compta_3_03', NULL, 365, 60, 14, 1, 'NORMAL');
INSERT INTO doctypes (coll_id, type_id, description, enabled, doctypes_first_level_id, doctypes_second_level_id, retention_final_disposition, retention_rule, action_current_use, duration_current_use, process_delay, delay1, delay2, process_mode) VALUES ('', 703, 'Candidature', 'Y', 1, 7, 'destruction', 'compta_3_03', NULL, 365, 60, 14, 1, 'NORMAL');
INSERT INTO doctypes (coll_id, type_id, description, enabled, doctypes_first_level_id, doctypes_second_level_id, retention_final_disposition, retention_rule, action_current_use, duration_current_use, process_delay, delay1, delay2, process_mode) VALUES ('', 704, 'Carrière', 'Y', 1, 7, 'destruction', 'compta_3_03', NULL, 365, 60, 14, 1, 'NORMAL');
INSERT INTO doctypes (coll_id, type_id, description, enabled, doctypes_first_level_id, doctypes_second_level_id, retention_final_disposition, retention_rule, action_current_use, duration_current_use, process_delay, delay1, delay2, process_mode) VALUES ('', 705, 'Conditions de travail santé', 'Y', 1, 7, 'destruction', 'compta_3_03', NULL, 365, 60, 14, 1, 'NORMAL');
INSERT INTO doctypes (coll_id, type_id, description, enabled, doctypes_first_level_id, doctypes_second_level_id, retention_final_disposition, retention_rule, action_current_use, duration_current_use, process_delay, delay1, delay2, process_mode) VALUES ('', 706, 'Congés exceptionnels et concours', 'Y', 1, 7, 'destruction', 'compta_3_03', NULL, 365, 60, 14, 1, 'NORMAL');
INSERT INTO doctypes (coll_id, type_id, description, enabled, doctypes_first_level_id, doctypes_second_level_id, retention_final_disposition, retention_rule, action_current_use, duration_current_use, process_delay, delay1, delay2, process_mode) VALUES ('', 707, 'Formation', 'Y', 1, 7, 'destruction', 'compta_3_03', NULL, 365, 60, 14, 1, 'NORMAL');
INSERT INTO doctypes (coll_id, type_id, description, enabled, doctypes_first_level_id, doctypes_second_level_id, retention_final_disposition, retention_rule, action_current_use, duration_current_use, process_delay, delay1, delay2, process_mode) VALUES ('', 708, 'Instances RH', 'Y', 1, 7, 'destruction', 'compta_3_03', NULL, 365, 60, 14, 1, 'NORMAL');
INSERT INTO doctypes (coll_id, type_id, description, enabled, doctypes_first_level_id, doctypes_second_level_id, retention_final_disposition, retention_rule, action_current_use, duration_current_use, process_delay, delay1, delay2, process_mode) VALUES ('', 709, 'Retraite', 'Y', 1, 7, 'destruction', 'compta_3_03', NULL, 365, 60, 14, 1, 'NORMAL');
INSERT INTO doctypes (coll_id, type_id, description, enabled, doctypes_first_level_id, doctypes_second_level_id, retention_final_disposition, retention_rule, action_current_use, duration_current_use, process_delay, delay1, delay2, process_mode) VALUES ('', 710, 'Stage', 'Y', 1, 7, 'destruction', 'compta_3_03', NULL, 365, 60, 14, 1, 'NORMAL');
INSERT INTO doctypes (coll_id, type_id, description, enabled, doctypes_first_level_id, doctypes_second_level_id, retention_final_disposition, retention_rule, action_current_use, duration_current_use, process_delay, delay1, delay2, process_mode) VALUES ('', 711, 'Syndicats', 'Y', 1, 7, 'destruction', 'compta_3_03', NULL, 365, 60, 14, 1, 'NORMAL');
INSERT INTO doctypes (coll_id, type_id, description, enabled, doctypes_first_level_id, doctypes_second_level_id, retention_final_disposition, retention_rule, action_current_use, duration_current_use, process_delay, delay1, delay2, process_mode) VALUES ('', 801, 'Aide à domicile', 'Y', 1, 8, 'destruction', 'compta_3_03', NULL, 365, 60, 14, 1, 'NORMAL');
INSERT INTO doctypes (coll_id, type_id, description, enabled, doctypes_first_level_id, doctypes_second_level_id, retention_final_disposition, retention_rule, action_current_use, duration_current_use, process_delay, delay1, delay2, process_mode) VALUES ('', 802, 'Aide Financière', 'Y', 1, 8, 'destruction', 'compta_3_03', NULL, 365, 60, 14, 1, 'NORMAL');
INSERT INTO doctypes (coll_id, type_id, description, enabled, doctypes_first_level_id, doctypes_second_level_id, retention_final_disposition, retention_rule, action_current_use, duration_current_use, process_delay, delay1, delay2, process_mode) VALUES ('', 803, 'Animations retraités', 'Y', 1, 8, 'destruction', 'compta_3_03', NULL, 365, 60, 14, 1, 'NORMAL');
INSERT INTO doctypes (coll_id, type_id, description, enabled, doctypes_first_level_id, doctypes_second_level_id, retention_final_disposition, retention_rule, action_current_use, duration_current_use, process_delay, delay1, delay2, process_mode) VALUES ('', 804, 'Domiciliation', 'Y', 1, 8, 'destruction', 'compta_3_03', NULL, 365, 60, 14, 1, 'NORMAL');
INSERT INTO doctypes (coll_id, type_id, description, enabled, doctypes_first_level_id, doctypes_second_level_id, retention_final_disposition, retention_rule, action_current_use, duration_current_use, process_delay, delay1, delay2, process_mode) VALUES ('', 805, 'Dossier de logement', 'Y', 1, 8, 'destruction', 'compta_3_03', NULL, 365, 60, 14, 1, 'NORMAL');
INSERT INTO doctypes (coll_id, type_id, description, enabled, doctypes_first_level_id, doctypes_second_level_id, retention_final_disposition, retention_rule, action_current_use, duration_current_use, process_delay, delay1, delay2, process_mode) VALUES ('', 806, 'Expulsion', 'Y', 1, 8, 'destruction', 'compta_3_03', NULL, 365, 60, 14, 1, 'NORMAL');
INSERT INTO doctypes (coll_id, type_id, description, enabled, doctypes_first_level_id, doctypes_second_level_id, retention_final_disposition, retention_rule, action_current_use, duration_current_use, process_delay, delay1, delay2, process_mode) VALUES ('', 807, 'Foyer', 'Y', 1, 8, 'destruction', 'compta_3_03', NULL, 365, 60, 14, 1, 'NORMAL');
INSERT INTO doctypes (coll_id, type_id, description, enabled, doctypes_first_level_id, doctypes_second_level_id, retention_final_disposition, retention_rule, action_current_use, duration_current_use, process_delay, delay1, delay2, process_mode) VALUES ('', 808, 'Obligation alimentaire', 'Y', 1, 8, 'destruction', 'compta_3_03', NULL, 365, 60, 14, 1, 'NORMAL');
INSERT INTO doctypes (coll_id, type_id, description, enabled, doctypes_first_level_id, doctypes_second_level_id, retention_final_disposition, retention_rule, action_current_use, duration_current_use, process_delay, delay1, delay2, process_mode) VALUES ('', 809, 'RSA', 'Y', 1, 8, 'destruction', 'compta_3_03', NULL, 365, 60, 14, 1, 'NORMAL');
INSERT INTO doctypes (coll_id, type_id, description, enabled, doctypes_first_level_id, doctypes_second_level_id, retention_final_disposition, retention_rule, action_current_use, duration_current_use, process_delay, delay1, delay2, process_mode) VALUES ('', 810, 'Scolarisation à domicile', 'Y', 1, 8, 'destruction', 'compta_3_03', NULL, 365, 60, 14, 1, 'NORMAL');
INSERT INTO doctypes (coll_id, type_id, description, enabled, doctypes_first_level_id, doctypes_second_level_id, retention_final_disposition, retention_rule, action_current_use, duration_current_use, process_delay, delay1, delay2, process_mode) VALUES ('', 901, 'Aire d''accueil des gens du voyage', 'Y', 1, 9, 'destruction', 'compta_3_03', NULL, 365, 60, 14, 1, 'NORMAL');
INSERT INTO doctypes (coll_id, type_id, description, enabled, doctypes_first_level_id, doctypes_second_level_id, retention_final_disposition, retention_rule, action_current_use, duration_current_use, process_delay, delay1, delay2, process_mode) VALUES ('', 902, 'Assainissement', 'Y', 1, 9, 'destruction', 'compta_3_03', NULL, 365, 60, 14, 1, 'NORMAL');
INSERT INTO doctypes (coll_id, type_id, description, enabled, doctypes_first_level_id, doctypes_second_level_id, retention_final_disposition, retention_rule, action_current_use, duration_current_use, process_delay, delay1, delay2, process_mode) VALUES ('', 903, 'Assurance et sinistre', 'Y', 1, 9, 'destruction', 'compta_3_03', NULL, 365, 60, 14, 1, 'NORMAL');
INSERT INTO doctypes (coll_id, type_id, description, enabled, doctypes_first_level_id, doctypes_second_level_id, retention_final_disposition, retention_rule, action_current_use, duration_current_use, process_delay, delay1, delay2, process_mode) VALUES ('', 904, 'Autorisation d''occupation du domaine public', 'Y', 1, 9, 'destruction', 'compta_3_03', NULL, 365, 60, 14, 1, 'SVR');
INSERT INTO doctypes (coll_id, type_id, description, enabled, doctypes_first_level_id, doctypes_second_level_id, retention_final_disposition, retention_rule, action_current_use, duration_current_use, process_delay, delay1, delay2, process_mode) VALUES ('', 905, 'Contrat et convention hors marchés publics', 'Y', 1, 9, 'destruction', 'compta_3_03', NULL, 365, 60, 14, 1, 'NORMAL');
INSERT INTO doctypes (coll_id, type_id, description, enabled, doctypes_first_level_id, doctypes_second_level_id, retention_final_disposition, retention_rule, action_current_use, duration_current_use, process_delay, delay1, delay2, process_mode) VALUES ('', 906, 'Détention de chiens dangereux', 'Y', 1, 9, 'destruction', 'compta_3_03', NULL, 365, 60, 14, 1, 'SVR');
INSERT INTO doctypes (coll_id, type_id, description, enabled, doctypes_first_level_id, doctypes_second_level_id, retention_final_disposition, retention_rule, action_current_use, duration_current_use, process_delay, delay1, delay2, process_mode) VALUES ('', 907, 'Espaces verts – Environnement – Développement durable', 'Y', 1, 9, 'destruction', 'compta_3_03', NULL, 365, 60, 14, 1, 'NORMAL');
INSERT INTO doctypes (coll_id, type_id, description, enabled, doctypes_first_level_id, doctypes_second_level_id, retention_final_disposition, retention_rule, action_current_use, duration_current_use, process_delay, delay1, delay2, process_mode) VALUES ('', 908, 'Hygiène et Salubrité', 'Y', 1, 9, 'destruction', 'compta_3_03', NULL, 365, 60, 14, 1, 'NORMAL');
INSERT INTO doctypes (coll_id, type_id, description, enabled, doctypes_first_level_id, doctypes_second_level_id, retention_final_disposition, retention_rule, action_current_use, duration_current_use, process_delay, delay1, delay2, process_mode) VALUES ('', 909, 'Marchés Publics', 'Y', 1, 9, 'destruction', 'compta_3_03', NULL, 365, 60, 14, 1, 'NORMAL');
INSERT INTO doctypes (coll_id, type_id, description, enabled, doctypes_first_level_id, doctypes_second_level_id, retention_final_disposition, retention_rule, action_current_use, duration_current_use, process_delay, delay1, delay2, process_mode) VALUES ('', 910, 'Mobiliers urbains', 'Y', 1, 9, 'destruction', 'compta_3_03', NULL, 365, 60, 14, 1, 'NORMAL');
INSERT INTO doctypes (coll_id, type_id, description, enabled, doctypes_first_level_id, doctypes_second_level_id, retention_final_disposition, retention_rule, action_current_use, duration_current_use, process_delay, delay1, delay2, process_mode) VALUES ('', 911, 'NTIC', 'Y', 1, 9, 'destruction', 'compta_3_03', NULL, 365, 60, 14, 1, 'NORMAL');
INSERT INTO doctypes (coll_id, type_id, description, enabled, doctypes_first_level_id, doctypes_second_level_id, retention_final_disposition, retention_rule, action_current_use, duration_current_use, process_delay, delay1, delay2, process_mode) VALUES ('', 912, 'Opération d''aménagement', 'Y', 1, 9, 'destruction', 'compta_3_03', NULL, 365, 60, 14, 1, 'NORMAL');
INSERT INTO doctypes (coll_id, type_id, description, enabled, doctypes_first_level_id, doctypes_second_level_id, retention_final_disposition, retention_rule, action_current_use, duration_current_use, process_delay, delay1, delay2, process_mode) VALUES ('', 913, 'Patrimoine', 'Y', 1, 9, 'destruction', 'compta_3_03', NULL, 365, 60, 14, 1, 'NORMAL');
INSERT INTO doctypes (coll_id, type_id, description, enabled, doctypes_first_level_id, doctypes_second_level_id, retention_final_disposition, retention_rule, action_current_use, duration_current_use, process_delay, delay1, delay2, process_mode) VALUES ('', 914, 'Problème de voisinage', 'Y', 1, 9, 'destruction', 'compta_3_03', NULL, 365, 60, 14, 1, 'NORMAL');
INSERT INTO doctypes (coll_id, type_id, description, enabled, doctypes_first_level_id, doctypes_second_level_id, retention_final_disposition, retention_rule, action_current_use, duration_current_use, process_delay, delay1, delay2, process_mode) VALUES ('', 915, 'Propreté', 'Y', 1, 9, 'destruction', 'compta_3_03', NULL, 365, 60, 14, 1, 'NORMAL');
INSERT INTO doctypes (coll_id, type_id, description, enabled, doctypes_first_level_id, doctypes_second_level_id, retention_final_disposition, retention_rule, action_current_use, duration_current_use, process_delay, delay1, delay2, process_mode) VALUES ('', 916, 'Stationnement et circulation', 'Y', 1, 9, 'destruction', 'compta_3_03', NULL, 365, 60, 14, 1, 'NORMAL');
INSERT INTO doctypes (coll_id, type_id, description, enabled, doctypes_first_level_id, doctypes_second_level_id, retention_final_disposition, retention_rule, action_current_use, duration_current_use, process_delay, delay1, delay2, process_mode) VALUES ('', 917, 'Transports', 'Y', 1, 9, 'destruction', 'compta_3_03', NULL, 365, 60, 14, 1, 'NORMAL');
INSERT INTO doctypes (coll_id, type_id, description, enabled, doctypes_first_level_id, doctypes_second_level_id, retention_final_disposition, retention_rule, action_current_use, duration_current_use, process_delay, delay1, delay2, process_mode) VALUES ('', 918, 'Travaux', 'Y', 1, 9, 'destruction', 'compta_3_03', NULL, 365, 60, 14, 1, 'NORMAL');
INSERT INTO doctypes (coll_id, type_id, description, enabled, doctypes_first_level_id, doctypes_second_level_id, retention_final_disposition, retention_rule, action_current_use, duration_current_use, process_delay, delay1, delay2, process_mode) VALUES ('', 1001, 'Alignement', 'Y', 1, 10, 'destruction', 'compta_3_03', NULL, 365, 60, 14, 1, 'NORMAL');
INSERT INTO doctypes (coll_id, type_id, description, enabled, doctypes_first_level_id, doctypes_second_level_id, retention_final_disposition, retention_rule, action_current_use, duration_current_use, process_delay, delay1, delay2, process_mode) VALUES ('', 1002, 'Avis d''urbanisme', 'Y', 1, 10, 'destruction', 'compta_3_03', NULL, 365, 60, 14, 1, 'NORMAL');
INSERT INTO doctypes (coll_id, type_id, description, enabled, doctypes_first_level_id, doctypes_second_level_id, retention_final_disposition, retention_rule, action_current_use, duration_current_use, process_delay, delay1, delay2, process_mode) VALUES ('', 1003, 'Commerces', 'Y', 1, 10, 'destruction', 'compta_3_03', NULL, 365, 60, 14, 1, 'NORMAL');
INSERT INTO doctypes (coll_id, type_id, description, enabled, doctypes_first_level_id, doctypes_second_level_id, retention_final_disposition, retention_rule, action_current_use, duration_current_use, process_delay, delay1, delay2, process_mode) VALUES ('', 1004, 'Numérotation', 'Y', 1, 10, 'destruction', 'compta_3_03', NULL, 365, 60, 14, 1, 'NORMAL');
INSERT INTO doctypes (coll_id, type_id, description, enabled, doctypes_first_level_id, doctypes_second_level_id, retention_final_disposition, retention_rule, action_current_use, duration_current_use, process_delay, delay1, delay2, process_mode) VALUES ('', 1101, 'Autorisation de buvette', 'Y', 1, 11, 'destruction', 'compta_3_03', NULL, 365, 60, 14, 1, 'SVA');
INSERT INTO doctypes (coll_id, type_id, description, enabled, doctypes_first_level_id, doctypes_second_level_id, retention_final_disposition, retention_rule, action_current_use, duration_current_use, process_delay, delay1, delay2, process_mode) VALUES ('', 1102, 'Cimetière', 'Y', 1, 11, 'destruction', 'compta_3_03', NULL, 365, 60, 14, 1, 'SVA');
INSERT INTO doctypes (coll_id, type_id, description, enabled, doctypes_first_level_id, doctypes_second_level_id, retention_final_disposition, retention_rule, action_current_use, duration_current_use, process_delay, delay1, delay2, process_mode) VALUES ('', 1103, 'Demande de dérogation scolaire', 'Y', 1, 11, 'destruction', 'compta_3_03', NULL, 365, 60, 14, 1, 'SVA');
INSERT INTO doctypes (coll_id, type_id, description, enabled, doctypes_first_level_id, doctypes_second_level_id, retention_final_disposition, retention_rule, action_current_use, duration_current_use, process_delay, delay1, delay2, process_mode) VALUES ('', 1104, 'Inscription à la cantine et activités périscolaires ', 'Y', 1, 11, 'destruction', 'compta_3_03', NULL, 365, 60, 14, 1, 'SVA');
INSERT INTO doctypes (coll_id, type_id, description, enabled, doctypes_first_level_id, doctypes_second_level_id, retention_final_disposition, retention_rule, action_current_use, duration_current_use, process_delay, delay1, delay2, process_mode) VALUES ('', 1105, 'Inscription toutes petites sections', 'Y', 1, 11, 'destruction', 'compta_3_03', NULL, 365, 90, 14, 1, 'SVA');
INSERT INTO doctypes (coll_id, type_id, description, enabled, doctypes_first_level_id, doctypes_second_level_id, retention_final_disposition, retention_rule, action_current_use, duration_current_use, process_delay, delay1, delay2, process_mode) VALUES ('', 1106, 'Travaux ERP', 'Y', 1, 11, 'destruction', 'compta_3_03', NULL, 365, 60, 14, 1, 'SVA');
INSERT INTO doctypes (coll_id, type_id, description, enabled, doctypes_first_level_id, doctypes_second_level_id, retention_final_disposition, retention_rule, action_current_use, duration_current_use, process_delay, delay1, delay2, process_mode) VALUES ('', 1201, 'Appel téléphonique', 'Y', 1, 12, 'destruction', 'compta_3_03', NULL, 365, 21, 14, 1, 'NORMAL');
INSERT INTO doctypes (coll_id, type_id, description, enabled, doctypes_first_level_id, doctypes_second_level_id, retention_final_disposition, retention_rule, action_current_use, duration_current_use, process_delay, delay1, delay2, process_mode) VALUES ('', 1202, 'Demande intervention voirie', 'Y', 1, 12, 'destruction', 'compta_3_03', NULL, 365, 21, 14, 1, 'NORMAL');
INSERT INTO doctypes (coll_id, type_id, description, enabled, doctypes_first_level_id, doctypes_second_level_id, retention_final_disposition, retention_rule, action_current_use, duration_current_use, process_delay, delay1, delay2, process_mode) VALUES ('', 1203, 'Courriel importé', 'Y', 1, 12, 'destruction', 'compta_3_03', NULL, 365, 21, 14, 1, 'NORMAL');

INSERT INTO doctypes_first_level (doctypes_first_level_id, doctypes_first_level_label, css_style, enabled) VALUES (1, 'COURRIERS', '#000000', 'Y');

INSERT INTO doctypes_second_level (doctypes_second_level_id, doctypes_second_level_label, doctypes_first_level_id, css_style, enabled) VALUES (1, '01. Correspondances', 1, '#000000', 'Y');
INSERT INTO doctypes_second_level (doctypes_second_level_id, doctypes_second_level_label, doctypes_first_level_id, css_style, enabled) VALUES (2, '02. Cabinet', 1, '#000000', 'Y');
INSERT INTO doctypes_second_level (doctypes_second_level_id, doctypes_second_level_label, doctypes_first_level_id, css_style, enabled) VALUES (3, '03. Éducation', 1, '#000000', 'Y');
INSERT INTO doctypes_second_level (doctypes_second_level_id, doctypes_second_level_label, doctypes_first_level_id, css_style, enabled) VALUES (4, '04. Finances', 1, '#000000', 'Y');
INSERT INTO doctypes_second_level (doctypes_second_level_id, doctypes_second_level_label, doctypes_first_level_id, css_style, enabled) VALUES (5, '05. Juridique', 1, '#000000', 'Y');
INSERT INTO doctypes_second_level (doctypes_second_level_id, doctypes_second_level_label, doctypes_first_level_id, css_style, enabled) VALUES (6, '06. Population ', 1, '#000000', 'Y');
INSERT INTO doctypes_second_level (doctypes_second_level_id, doctypes_second_level_label, doctypes_first_level_id, css_style, enabled) VALUES (7, '07. Ressources Humaines', 1, '#000000', 'Y');
INSERT INTO doctypes_second_level (doctypes_second_level_id, doctypes_second_level_label, doctypes_first_level_id, css_style, enabled) VALUES (8, '08. Social', 1, '#000000', 'Y');
INSERT INTO doctypes_second_level (doctypes_second_level_id, doctypes_second_level_label, doctypes_first_level_id, css_style, enabled) VALUES (9, '09. Technique', 1, '#000000', 'Y');
INSERT INTO doctypes_second_level (doctypes_second_level_id, doctypes_second_level_label, doctypes_first_level_id, css_style, enabled) VALUES (10, '10. Urbanisme', 1, '#000000', 'Y');
INSERT INTO doctypes_second_level (doctypes_second_level_id, doctypes_second_level_label, doctypes_first_level_id, css_style, enabled) VALUES (11, '11. Silence vaut acceptation', 1, '#000000', 'Y');
INSERT INTO doctypes_second_level (doctypes_second_level_id, doctypes_second_level_label, doctypes_first_level_id, css_style, enabled) VALUES (12, '12. Formulaires', 1, '#000000', 'Y');

INSERT INTO entities (id, entity_id, entity_label, short_label, entity_full_name, enabled, address_number, address_street, address_additional1, address_additional2, address_postcode, address_town, address_country, email, business_id, parent_entity_id, entity_type, ldap_id, producer_service, folder_import, external_id) VALUES (1, 'VILLE', 'Ville de Maarch-les-Bains', 'Ville de Maarch-les-Bains', NULL, 'Y', '', 'Place de la liberté', 'Hôtel de Ville', NULL, '99000', 'Maarch-les-Bains', 'FRANCE', 'mairie@maarchlesbains.fr', '45239273100025/VILLE', '', 'Direction', NULL, NULL, NULL, '{}');
INSERT INTO entities (id, entity_id, entity_label, short_label, entity_full_name, enabled, address_number, address_street, address_additional1, address_additional2, address_postcode, address_town, address_country, email, business_id, parent_entity_id, entity_type, ldap_id, producer_service, folder_import, external_id) VALUES (2, 'CAB', 'Cabinet du Maire', 'Cabinet du Maire', NULL, 'Y', '', 'Place de la liberté', 'Hôtel de Ville', NULL, '99000', 'Maarch-les-Bains', 'FRANCE', 'mairie@maarchlesbains.fr', '45239273100025/CAB', 'VILLE', 'Direction', NULL, NULL, NULL, '{}');
INSERT INTO entities (id, entity_id, entity_label, short_label, entity_full_name, enabled, address_number, address_street, address_additional1, address_additional2, address_postcode, address_town, address_country, email, business_id, parent_entity_id, entity_type, ldap_id, producer_service, folder_import, external_id) VALUES (4, 'DGA', 'Direction Générale Adjointe', 'Direction Générale Adjointe', NULL, 'Y', '', 'Place de la liberté', 'Hôtel de Ville', NULL, '99000', 'Maarch-les-Bains', 'FRANCE', 'mairie@maarchlesbains.fr', '45239273100025/DGA', 'DGS', 'Bureau', NULL, NULL, NULL, '{}');
INSERT INTO entities (id, entity_id, entity_label, short_label, entity_full_name, enabled, address_number, address_street, address_additional1, address_additional2, address_postcode, address_town, address_country, email, business_id, parent_entity_id, entity_type, ldap_id, producer_service, folder_import, external_id) VALUES (3, 'DGS', 'Direction Générale des Services', 'Direction Générale des Services', NULL, 'Y', '', 'Place de la liberté', 'Hôtel de Ville', NULL, '99000', 'Maarch-les-Bains', 'FRANCE', 'mairie@maarchlesbains.fr', '45239273100025/DGS', 'VILLE', 'Direction', NULL, NULL, NULL, '{}');
INSERT INTO entities (id, entity_id, entity_label, short_label, entity_full_name, enabled, address_number, address_street, address_additional1, address_additional2, address_postcode, address_town, address_country, email, business_id, parent_entity_id, entity_type, ldap_id, producer_service, folder_import, external_id) VALUES (5, 'PCU', 'Pôle Culturel', 'Pôle Culturel', NULL, 'Y', '', 'Place de la liberté', 'Hôtel de Ville', NULL, '99000', 'Maarch-les-Bains', 'FRANCE', 'mairie@maarchlesbains.fr', '45239273100025/PCU', 'DGA', 'Service', NULL, NULL, NULL, '{}');
INSERT INTO entities (id, entity_id, entity_label, short_label, entity_full_name, enabled, address_number, address_street, address_additional1, address_additional2, address_postcode, address_town, address_country, email, business_id, parent_entity_id, entity_type, ldap_id, producer_service, folder_import, external_id) VALUES (6, 'PJS', 'Pôle Jeunesse et Sport', 'Pôle Jeunesse et Sport', NULL, 'Y', '', 'Place de la liberté', 'Hôtel de Ville', NULL, '99000', 'Maarch-les-Bains', 'FRANCE', 'mairie@maarchlesbains.fr', '45239273100025/PJS', 'DGA', 'Service', NULL, NULL, NULL, '{}');
INSERT INTO entities (id, entity_id, entity_label, short_label, entity_full_name, enabled, address_number, address_street, address_additional1, address_additional2, address_postcode, address_town, address_country, email, business_id, parent_entity_id, entity_type, ldap_id, producer_service, folder_import, external_id) VALUES (7, 'PE', 'Petite enfance', 'Petite enfance', NULL, 'Y', '', 'Place de la liberté', 'Hôtel de Ville', NULL, '99000', 'Maarch-les-Bains', 'FRANCE', 'mairie@maarchlesbains.fr', '45239273100025/PE', 'PJS', 'Service', NULL, NULL, NULL, '{}');
INSERT INTO entities (id, entity_id, entity_label, short_label, entity_full_name, enabled, address_number, address_street, address_additional1, address_additional2, address_postcode, address_town, address_country, email, business_id, parent_entity_id, entity_type, ldap_id, producer_service, folder_import, external_id) VALUES (8, 'SP', 'Sport', 'Sport', NULL, 'Y', '', 'Place de la liberté', 'Hôtel de Ville', NULL, '99000', 'Maarch-les-Bains', 'FRANCE', 'mairie@maarchlesbains.fr', '45239273100025/SP', 'PJS', 'Service', NULL, NULL, NULL, '{}');
INSERT INTO entities (id, entity_id, entity_label, short_label, entity_full_name, enabled, address_number, address_street, address_additional1, address_additional2, address_postcode, address_town, address_country, email, business_id, parent_entity_id, entity_type, ldap_id, producer_service, folder_import, external_id) VALUES (9, 'PSO', 'Pôle Social', 'Pôle Social', NULL, 'Y', '', 'Place de la liberté', 'Hôtel de Ville', NULL, '99000', 'Maarch-les-Bains', 'FRANCE', 'mairie@maarchlesbains.fr', '45239273100025/PSO', 'DGA', 'Service', NULL, NULL, NULL, '{}');
INSERT INTO entities (id, entity_id, entity_label, short_label, entity_full_name, enabled, address_number, address_street, address_additional1, address_additional2, address_postcode, address_town, address_country, email, business_id, parent_entity_id, entity_type, ldap_id, producer_service, folder_import, external_id) VALUES (10, 'PTE', 'Pôle Technique', 'Pôle Technique', NULL, 'Y', '', 'Place de la liberté', 'Hôtel de Ville', NULL, '99000', 'Maarch-les-Bains', 'FRANCE', 'mairie@maarchlesbains.fr', '45239273100025/PTE', 'DGA', 'Service', NULL, NULL, NULL, '{}');
INSERT INTO entities (id, entity_id, entity_label, short_label, entity_full_name, enabled, address_number, address_street, address_additional1, address_additional2, address_postcode, address_town, address_country, email, business_id, parent_entity_id, entity_type, ldap_id, producer_service, folder_import, external_id) VALUES (11, 'DRH', 'Direction des Ressources Humaines', 'Direction des Ressources Humaines', NULL, 'Y', '', 'Place de la liberté', 'Hôtel de Ville', NULL, '99000', 'Maarch-les-Bains', 'FRANCE', 'mairie@maarchlesbains.fr', '45239273100025/DRH', 'DGS', 'Service', NULL, NULL, NULL, '{}');
INSERT INTO entities (id, entity_id, entity_label, short_label, entity_full_name, enabled, address_number, address_street, address_additional1, address_additional2, address_postcode, address_town, address_country, email, business_id, parent_entity_id, entity_type, ldap_id, producer_service, folder_import, external_id) VALUES (12, 'DSG', 'Secrétariat Général', 'Secrétariat Général', NULL, 'Y', '', 'Place de la liberté', 'Hôtel de Ville', NULL, '99000', 'Maarch-les-Bains', 'FRANCE', 'mairie@maarchlesbains.fr', '45239273100025/DSG', 'DGS', 'Direction', NULL, NULL, NULL, '{}');
INSERT INTO entities (id, entity_id, entity_label, short_label, entity_full_name, enabled, address_number, address_street, address_additional1, address_additional2, address_postcode, address_town, address_country, email, business_id, parent_entity_id, entity_type, ldap_id, producer_service, folder_import, external_id) VALUES (15, 'PSF', 'Pôle des Services Fonctionnels', 'Services Fonctionnels', NULL, 'Y', '', 'Place de la liberté', 'Hôtel de Ville', NULL, '99000', 'Maarch-les-Bains', 'FRANCE', 'mairie@maarchlesbains.fr', '45239273100025/PSF', 'DSG', 'Service', NULL, NULL, NULL, '{}');
INSERT INTO entities (id, entity_id, entity_label, short_label, entity_full_name, enabled, address_number, address_street, address_additional1, address_additional2, address_postcode, address_town, address_country, email, business_id, parent_entity_id, entity_type, ldap_id, producer_service, folder_import, external_id) VALUES (13, 'COU', 'Service Courrier', 'Service Courrier', NULL, 'Y', '', 'Place de la liberté', 'Hôtel de Ville', NULL, '99000', 'Maarch-les-Bains', 'FRANCE', 'mairie@maarchlesbains.fr', '45239273100025/COU', 'DSG', 'Service', NULL, NULL, NULL, '{}');
INSERT INTO entities (id, entity_id, entity_label, short_label, entity_full_name, enabled, address_number, address_street, address_additional1, address_additional2, address_postcode, address_town, address_country, email, business_id, parent_entity_id, entity_type, ldap_id, producer_service, folder_import, external_id) VALUES (14, 'COR', 'Correspondants Archive', 'Correspondants Archive', NULL, 'Y', '', 'Place de la liberté', 'Hôtel de Ville', NULL, '99000', 'Maarch-les-Bains', 'FRANCE', 'mairie@maarchlesbains.fr', '45239273100025/COR', 'COU', 'Service', NULL, NULL, NULL, '{}');
INSERT INTO entities (id, entity_id, entity_label, short_label, entity_full_name, enabled, address_number, address_street, address_additional1, address_additional2, address_postcode, address_town, address_country, email, business_id, parent_entity_id, entity_type, ldap_id, producer_service, folder_import, external_id) VALUES (17, 'FIN', 'Direction des Finances', 'Direction des Finances', NULL, 'Y', '', 'Place de la liberté', 'Hôtel de Ville', NULL, '99000', 'Maarch-les-Bains', 'FRANCE', 'mairie@maarchlesbains.fr', '45239273100025/FIN', 'DGS', 'Service', NULL, NULL, NULL, '{}');
INSERT INTO entities (id, entity_id, entity_label, short_label, entity_full_name, enabled, address_number, address_street, address_additional1, address_additional2, address_postcode, address_town, address_country, email, business_id, parent_entity_id, entity_type, ldap_id, producer_service, folder_import, external_id) VALUES (18, 'PJU', 'Pôle Juridique', 'Pôle Juridique', NULL, 'Y', '', 'Place de la liberté', 'Hôtel de Ville', NULL, '99000', 'Maarch-les-Bains', 'FRANCE', 'mairie@maarchlesbains.fr', '45239273100025/PJU', 'FIN', 'Service', NULL, NULL, NULL, '{}');
INSERT INTO entities (id, entity_id, entity_label, short_label, entity_full_name, enabled, address_number, address_street, address_additional1, address_additional2, address_postcode, address_town, address_country, email, business_id, parent_entity_id, entity_type, ldap_id, producer_service, folder_import, external_id) VALUES (16, 'DSI', 'Direction des Systèmes d''Information', 'Direction des Systèmes d''Information', NULL, 'Y', '', 'Place de la liberté', 'Hôtel de Ville', NULL, '99000', 'Maarch-les-Bains', 'FRANCE', 'mairie@maarchlesbains.fr', '45239273100025/DSI', 'DGS', 'Service', NULL, NULL, NULL, '{}');
INSERT INTO entities (id, entity_id, entity_label, short_label, entity_full_name, enabled, address_number, address_street, address_additional1, address_additional2, address_postcode, address_town, address_country, email, business_id, parent_entity_id, entity_type, ldap_id, producer_service, folder_import, external_id) VALUES (19, 'ELUS', 'Ensemble des élus', 'ELUS:Ensemble des élus', NULL, 'Y', '', 'Place de la liberté', 'Hôtel de Ville', NULL, '99000', 'Maarch-les-Bains', 'FRANCE', 'mairie@maarchlesbains.fr', '45239273100025/ELUS', 'VILLE', 'Direction', NULL, NULL, NULL, '{}');
INSERT INTO entities (id, entity_id, entity_label, short_label, entity_full_name, enabled, address_number, address_street, address_additional1, address_additional2, address_postcode, address_town, address_country, email, business_id, parent_entity_id, entity_type, ldap_id, producer_service, folder_import, external_id) VALUES (20, 'CCAS', 'Centre Communal d''Action Sociale', 'Centre Communal d''Action Sociale', NULL, 'Y', '', 'Place de la liberté', 'Hôtel de Ville', NULL, '99000', 'Maarch-les-Bains', 'France', 'mairie@maarchlesbains.fr', '45239273100025/CCAS', '', 'Direction', NULL, NULL, NULL, '{}');

INSERT INTO exports_templates (id, user_id, delimiter, format, data) VALUES (2, 4, ';', 'csv', '[{"value":"doc_date","label":"Date du courrier","isFunction":false},{"value":"getAssignee","label":"Attributaire","isFunction":true},{"value":"getDestinationEntity","label":"Libell\u00e9 de l''entit\u00e9 traitante","isFunction":true},{"value":"subject","label":"Objet","isFunction":false},{"value":"process_limit_date","label":"Date limite de traitement","isFunction":false}]');
INSERT INTO exports_templates (id, user_id, delimiter, format, data) VALUES (1, 4, ';', 'pdf', '[{"value":"doc_date","label":"Date du courrier","isFunction":false},{"value":"type_label","label":"Type de courrier","isFunction":false},{"value":"getAssignee","label":"Attributaire","isFunction":true},{"value":"subject","label":"Objet","isFunction":false},{"value":"process_limit_date","label":"Date limite de traitement","isFunction":false}]');

INSERT INTO folders (id, label, public, user_id, parent_id, level) VALUES (1, 'Compétences fonctionnelles', true, 21, NULL, 0);
INSERT INTO folders (id, label, public, user_id, parent_id, level) VALUES (2, 'Vie politique', true, 21, 1, 1);
INSERT INTO folders (id, label, public, user_id, parent_id, level) VALUES (3, 'Vie citoyenne', true, 21, 1, 1);
INSERT INTO folders (id, label, public, user_id, parent_id, level) VALUES (4, 'Administration municipale', true, 21, 1, 1);
INSERT INTO folders (id, label, public, user_id, parent_id, level) VALUES (5, 'Ressources humaines', true, 21, 1, 1);
INSERT INTO folders (id, label, public, user_id, parent_id, level) VALUES (6, 'Candidatures sur postes ouverts', true, 21, 5, 2);
INSERT INTO folders (id, label, public, user_id, parent_id, level) VALUES (7, 'Candidatures spontanées', true, 21, 5, 2);
INSERT INTO folders (id, label, public, user_id, parent_id, level) VALUES (8, 'Affaires juridiques', true, 21, 1, 1);
INSERT INTO folders (id, label, public, user_id, parent_id, level) VALUES (9, 'Finances', true, 21, 1, 1);
INSERT INTO folders (id, label, public, user_id, parent_id, level) VALUES (10, 'Marchés publics', true, 21, 1, 1);
INSERT INTO folders (id, label, public, user_id, parent_id, level) VALUES (11, 'Informatique', true, 21, 1, 1);
INSERT INTO folders (id, label, public, user_id, parent_id, level) VALUES (12, 'Communication', true, 21, 1, 1);
INSERT INTO folders (id, label, public, user_id, parent_id, level) VALUES (13, 'Événements', true, 21, 1, 1);
INSERT INTO folders (id, label, public, user_id, parent_id, level) VALUES (14, 'Moyens généraux (matériels et logistiques)', true, 21, 1, 1);
INSERT INTO folders (id, label, public, user_id, parent_id, level) VALUES (15, 'Archives', true, 21, 1, 1);
INSERT INTO folders (id, label, public, user_id, parent_id, level) VALUES (16, 'Compétences techniques', true, 21, NULL, 0);
INSERT INTO folders (id, label, public, user_id, parent_id, level) VALUES (17, 'Population', true, 21, 16, 1);
INSERT INTO folders (id, label, public, user_id, parent_id, level) VALUES (18, 'Police - ordre public', true, 21, 16, 1);
INSERT INTO folders (id, label, public, user_id, parent_id, level) VALUES (19, 'Stationnement', true, 21, 18, 2);
INSERT INTO folders (id, label, public, user_id, parent_id, level) VALUES (20, 'Politique de la ville', true, 21, 16, 1);
INSERT INTO folders (id, label, public, user_id, parent_id, level) VALUES (21, 'Urbanisme opérationnel', true, 21, 16, 1);
INSERT INTO folders (id, label, public, user_id, parent_id, level) VALUES (22, 'Urbanisme réglementaire', true, 21, 16, 1);
INSERT INTO folders (id, label, public, user_id, parent_id, level) VALUES (23, 'Affaires foncières ', true, 21, 16, 1);
INSERT INTO folders (id, label, public, user_id, parent_id, level) VALUES (24, 'Développement du territoire ', true, 21, 16, 1);
INSERT INTO folders (id, label, public, user_id, parent_id, level) VALUES (25, 'Habitat', true, 21, 16, 1);
INSERT INTO folders (id, label, public, user_id, parent_id, level) VALUES (26, 'Biens communaux (domaine privé)', true, 21, 16, 1);
INSERT INTO folders (id, label, public, user_id, parent_id, level) VALUES (27, 'Espaces publics urbains (domaine public - voiries -réseaux)', true, 21, 16, 1);
INSERT INTO folders (id, label, public, user_id, parent_id, level) VALUES (28, 'Éclairage public', true, 21, 27, 2);
INSERT INTO folders (id, label, public, user_id, parent_id, level) VALUES (29, 'Ouvrages d''art', true, 21, 27, 2);
INSERT INTO folders (id, label, public, user_id, parent_id, level) VALUES (30, 'Hygiène', true, 21, 16, 1);
INSERT INTO folders (id, label, public, user_id, parent_id, level) VALUES (31, 'Santé publique', true, 21, 16, 1);
INSERT INTO folders (id, label, public, user_id, parent_id, level) VALUES (32, 'Enseignement', true, 21, 16, 1);
INSERT INTO folders (id, label, public, user_id, parent_id, level) VALUES (33, 'Sports', true, 21, 16, 1);
INSERT INTO folders (id, label, public, user_id, parent_id, level) VALUES (34, 'Centre de loisirs nautiques', true, 21, 33, 2);
INSERT INTO folders (id, label, public, user_id, parent_id, level) VALUES (35, 'Jeunesse', true, 21, 16, 1);
INSERT INTO folders (id, label, public, user_id, parent_id, level) VALUES (36, 'Culture', true, 21, 16, 1);
INSERT INTO folders (id, label, public, user_id, parent_id, level) VALUES (37, 'Actions sociales', true, 21, 16, 1);
INSERT INTO folders (id, label, public, user_id, parent_id, level) VALUES (38, 'Cohésion sociale', true, 21, 16, 1);

INSERT INTO groupbasket (id, group_id, basket_id, list_display, list_event, list_event_data) VALUES (1, 'COURRIER', 'QualificationBasket', '{"templateColumns":7,"subInfos":[{"value":"getPriority","cssClasses":[],"icon":"fa-traffic-light"},{"value":"getCategory","cssClasses":[],"icon":"fa-exchange-alt"},{"value":"getDoctype","cssClasses":[],"icon":"fa-suitcase"},{"value":"getAssignee","cssClasses":[],"icon":"fa-sitemap"},{"value":"getRecipients","cssClasses":[],"icon":"fa-user"},{"value":"getSenders","cssClasses":[],"icon":"fa-book"},{"value":"getCreationAndProcessLimitDates","cssClasses":["align_rightData"],"icon":"fa-calendar"}]}', 'processDocument', '{"defaultTab": "info", "canUpdateData": true}');
INSERT INTO groupbasket (id, group_id, basket_id, list_display, list_event, list_event_data) VALUES (2, 'AGENT', 'CopyMailBasket', '{"templateColumns":7,"subInfos":[{"value":"getPriority","cssClasses":[],"icon":"fa-traffic-light"},{"value":"getCategory","cssClasses":[],"icon":"fa-exchange-alt"},{"value":"getDoctype","cssClasses":[],"icon":"fa-suitcase"},{"value":"getAssignee","cssClasses":[],"icon":"fa-sitemap"},{"value":"getRecipients","cssClasses":[],"icon":"fa-user"},{"value":"getSenders","cssClasses":[],"icon":"fa-book"},{"value":"getCreationAndProcessLimitDates","cssClasses":["align_rightData"],"icon":"fa-calendar"}]}', 'documentDetails', '[]');
INSERT INTO groupbasket (id, group_id, basket_id, list_display, list_event, list_event_data) VALUES (3, 'RESPONSABLE', 'CopyMailBasket', '{"templateColumns":7,"subInfos":[{"value":"getPriority","cssClasses":[],"icon":"fa-traffic-light"},{"value":"getCategory","cssClasses":[],"icon":"fa-exchange-alt"},{"value":"getDoctype","cssClasses":[],"icon":"fa-suitcase"},{"value":"getAssignee","cssClasses":[],"icon":"fa-sitemap"},{"value":"getRecipients","cssClasses":[],"icon":"fa-user"},{"value":"getSenders","cssClasses":[],"icon":"fa-book"},{"value":"getCreationAndProcessLimitDates","cssClasses":["align_rightData"],"icon":"fa-calendar"}]}', 'documentDetails', '[]');
INSERT INTO groupbasket (id, group_id, basket_id, list_display, list_event, list_event_data) VALUES (4, 'COURRIER', 'RetourCourrier', '{"templateColumns":7,"subInfos":[{"value":"getPriority","cssClasses":[],"icon":"fa-traffic-light"},{"value":"getCategory","cssClasses":[],"icon":"fa-exchange-alt"},{"value":"getDoctype","cssClasses":[],"icon":"fa-suitcase"},{"value":"getAssignee","cssClasses":[],"icon":"fa-sitemap"},{"value":"getRecipients","cssClasses":[],"icon":"fa-user"},{"value":"getSenders","cssClasses":[],"icon":"fa-book"},{"value":"getCreationAndProcessLimitDates","cssClasses":["align_rightData"],"icon":"fa-calendar"}]}', 'processDocument', '{"defaultTab": "info", "canUpdateData": true}');
INSERT INTO groupbasket (id, group_id, basket_id, list_display, list_event, list_event_data) VALUES (5, 'AGENT', 'DdeAvisBasket', '{"templateColumns":5,"subInfos":[{"value":"getPriority","cssClasses":[],"icon":"fa-traffic-light"},{"value":"getCategory","cssClasses":[],"icon":"fa-exchange-alt"},{"value":"getDoctype","cssClasses":[],"icon":"fa-suitcase"},{"value":"getParallelOpinionsNumber","cssClasses":["align_rightData"],"icon":"fa-comment-alt"},{"value":"getOpinionLimitDate","cssClasses":["align_rightData"],"icon":"fa-stopwatch"}]}', 'processDocument', '{"defaultTab": "dashboard"}');
INSERT INTO groupbasket (id, group_id, basket_id, list_display, list_event, list_event_data) VALUES (6, 'RESPONSABLE', 'DdeAvisBasket', '{"templateColumns":5,"subInfos":[{"value":"getPriority","cssClasses":[],"icon":"fa-traffic-light"},{"value":"getCategory","cssClasses":[],"icon":"fa-exchange-alt"},{"value":"getDoctype","cssClasses":[],"icon":"fa-suitcase"},{"value":"getParallelOpinionsNumber","cssClasses":["align_rightData"],"icon":"fa-comment-alt"},{"value":"getOpinionLimitDate","cssClasses":["align_rightData"],"icon":"fa-stopwatch"}]}', 'processDocument', '{"defaultTab": "dashboard"}');
INSERT INTO groupbasket (id, group_id, basket_id, list_display, list_event, list_event_data) VALUES (9, 'RESPONSABLE', 'SupAvisBasket', '{"templateColumns":5,"subInfos":[{"value":"getPriority","cssClasses":[],"icon":"fa-traffic-light"},{"value":"getCategory","cssClasses":[],"icon":"fa-exchange-alt"},{"value":"getDoctype","cssClasses":[],"icon":"fa-suitcase"},{"value":"getParallelOpinionsNumber","cssClasses":["align_rightData"],"icon":"fa-comment-alt"},{"value":"getOpinionLimitDate","cssClasses":["align_rightData"],"icon":"fa-stopwatch"}]}', 'processDocument', '{"defaultTab": "opinionCircuit"}');
INSERT INTO groupbasket (id, group_id, basket_id, list_display, list_event, list_event_data) VALUES (10, 'AGENT', 'RetAvisBasket', '{"templateColumns":5,"subInfos":[{"value":"getPriority","cssClasses":[],"icon":"fa-traffic-light"},{"value":"getCategory","cssClasses":[],"icon":"fa-exchange-alt"},{"value":"getDoctype","cssClasses":[],"icon":"fa-suitcase"},{"value":"getParallelOpinionsNumber","cssClasses":["align_rightData"],"icon":"fa-comment-alt"},{"value":"getOpinionLimitDate","cssClasses":["align_rightData"],"icon":"fa-stopwatch"}]}', 'processDocument', '{"defaultTab": "notes"}');
INSERT INTO groupbasket (id, group_id, basket_id, list_display, list_event, list_event_data) VALUES (11, 'RESPONSABLE', 'RetAvisBasket', '{"templateColumns":5,"subInfos":[{"value":"getPriority","cssClasses":[],"icon":"fa-traffic-light"},{"value":"getCategory","cssClasses":[],"icon":"fa-exchange-alt"},{"value":"getDoctype","cssClasses":[],"icon":"fa-suitcase"},{"value":"getParallelOpinionsNumber","cssClasses":["align_rightData"],"icon":"fa-comment-alt"},{"value":"getOpinionLimitDate","cssClasses":["align_rightData"],"icon":"fa-stopwatch"}]}', 'processDocument', '{"defaultTab": "notes"}');
INSERT INTO groupbasket (id, group_id, basket_id, list_display, list_event, list_event_data) VALUES (12, 'RESP_COURRIER', 'ValidationBasket', '{"templateColumns":7,"subInfos":[{"value":"getPriority","cssClasses":[],"icon":"fa-traffic-light"},{"value":"getCategory","cssClasses":[],"icon":"fa-exchange-alt"},{"value":"getDoctype","cssClasses":[],"icon":"fa-suitcase"},{"value":"getAssignee","cssClasses":[],"icon":"fa-sitemap"},{"value":"getRecipients","cssClasses":[],"icon":"fa-user"},{"value":"getSenders","cssClasses":[],"icon":"fa-book"},{"value":"getCreationAndProcessLimitDates","cssClasses":["align_rightData"],"icon":"fa-calendar"}]}', 'processDocument', '{"defaultTab": "diffusionList", "canUpdateData": true}');
INSERT INTO groupbasket (id, group_id, basket_id, list_display, list_event, list_event_data) VALUES (8, 'AGENT', 'SupAvisBasket', '{"templateColumns":5,"subInfos":[{"value":"getPriority","label":"Priorit\u00e9","sample":"Urgent","cssClasses":[],"icon":"fa-traffic-light"},{"value":"getCategory","label":"Cat\u00e9gorie","sample":"Courrier arriv\u00e9e","cssClasses":[],"icon":"fa-exchange-alt"},{"value":"getDoctype","label":"Type de courrier","sample":"R\u00e9clamation","cssClasses":[],"icon":"fa-suitcase"},{"value":"getParallelOpinionsNumber","label":"Nombre d''avis donn\u00e9s","sample":"<b>3<\/b> avis donn\u00e9(s)","cssClasses":["align_rightData"],"icon":"fa-comment-alt"},{"value":"getOpinionLimitDate","label":"Date limite d''envoi des avis","sample":"01-01-2019","cssClasses":["align_rightData"],"icon":"fa-stopwatch"}]}', 'processDocument', '{"defaultTab": "dashboard", "canUpdateData": true, "canUpdateModel": false}');
INSERT INTO groupbasket (id, group_id, basket_id, list_display, list_event, list_event_data) VALUES (15, 'ELU', 'MyBasket', '{"templateColumns":7,"subInfos":[{"value":"getPriority","cssClasses":[],"icon":"fa-traffic-light"},{"value":"getCategory","cssClasses":[],"icon":"fa-exchange-alt"},{"value":"getDoctype","cssClasses":[],"icon":"fa-suitcase"},{"value":"getAssignee","cssClasses":[],"icon":"fa-sitemap"},{"value":"getRecipients","cssClasses":[],"icon":"fa-user"},{"value":"getSenders","cssClasses":[],"icon":"fa-book"},{"value":"getCreationAndProcessLimitDates","cssClasses":["align_rightData"],"icon":"fa-calendar"}]}', 'processDocument', '{"defaultTab": "dashboard"}');
INSERT INTO groupbasket (id, group_id, basket_id, list_display, list_event, list_event_data) VALUES (16, 'AGENT', 'LateMailBasket', '{"templateColumns":7,"subInfos":[{"value":"getPriority","cssClasses":[],"icon":"fa-traffic-light"},{"value":"getCategory","cssClasses":[],"icon":"fa-exchange-alt"},{"value":"getDoctype","cssClasses":[],"icon":"fa-suitcase"},{"value":"getAssignee","cssClasses":[],"icon":"fa-sitemap"},{"value":"getRecipients","cssClasses":[],"icon":"fa-user"},{"value":"getSenders","cssClasses":[],"icon":"fa-book"},{"value":"getCreationAndProcessLimitDates","cssClasses":["align_rightData"],"icon":"fa-calendar"}]}', 'processDocument', '{"defaultTab": "dashboard"}');
INSERT INTO groupbasket (id, group_id, basket_id, list_display, list_event, list_event_data) VALUES (17, 'RESPONSABLE', 'DepartmentBasket', '{"templateColumns":7,"subInfos":[{"value":"getPriority","cssClasses":[],"icon":"fa-traffic-light"},{"value":"getCategory","cssClasses":[],"icon":"fa-exchange-alt"},{"value":"getDoctype","cssClasses":[],"icon":"fa-suitcase"},{"value":"getAssignee","cssClasses":[],"icon":"fa-sitemap"},{"value":"getRecipients","cssClasses":[],"icon":"fa-user"},{"value":"getSenders","cssClasses":[],"icon":"fa-book"},{"value":"getCreationAndProcessLimitDates","cssClasses":["align_rightData"],"icon":"fa-calendar"}]}', 'documentDetails', '[]');
INSERT INTO groupbasket (id, group_id, basket_id, list_display, list_event, list_event_data) VALUES (19, 'AGENT', 'SuiviParafBasket', '{"templateColumns":7,"subInfos":[{"value":"getPriority","cssClasses":[],"icon":"fa-traffic-light"},{"value":"getCategory","cssClasses":[],"icon":"fa-exchange-alt"},{"value":"getDoctype","cssClasses":[],"icon":"fa-suitcase"},{"value":"getAssignee","cssClasses":[],"icon":"fa-sitemap"},{"value":"getRecipients","cssClasses":[],"icon":"fa-user"},{"value":"getSenders","cssClasses":[],"icon":"fa-book"},{"value":"getCreationAndProcessLimitDates","cssClasses":["align_rightData"],"icon":"fa-calendar"}]}', 'documentDetails', '[]');
INSERT INTO groupbasket (id, group_id, basket_id, list_display, list_event, list_event_data) VALUES (20, 'RESPONSABLE', 'SuiviParafBasket', '{"templateColumns":7,"subInfos":[{"value":"getPriority","cssClasses":[],"icon":"fa-traffic-light"},{"value":"getCategory","cssClasses":[],"icon":"fa-exchange-alt"},{"value":"getDoctype","cssClasses":[],"icon":"fa-suitcase"},{"value":"getAssignee","cssClasses":[],"icon":"fa-sitemap"},{"value":"getRecipients","cssClasses":[],"icon":"fa-user"},{"value":"getSenders","cssClasses":[],"icon":"fa-book"},{"value":"getCreationAndProcessLimitDates","cssClasses":["align_rightData"],"icon":"fa-calendar"}]}', 'documentDetails', '[]');
INSERT INTO groupbasket (id, group_id, basket_id, list_display, list_event, list_event_data) VALUES (21, 'AGENT', 'EenvBasket', '{"templateColumns":7,"subInfos":[{"value":"getPriority","cssClasses":[],"icon":"fa-traffic-light"},{"value":"getCategory","cssClasses":[],"icon":"fa-exchange-alt"},{"value":"getDoctype","cssClasses":[],"icon":"fa-suitcase"},{"value":"getAssignee","cssClasses":[],"icon":"fa-sitemap"},{"value":"getRecipients","cssClasses":[],"icon":"fa-user"},{"value":"getSenders","cssClasses":[],"icon":"fa-book"},{"value":"getCreationAndProcessLimitDates","cssClasses":["align_rightData"],"icon":"fa-calendar"}]}', 'processDocument', '{"defaultTab": "dashboard"}');
INSERT INTO groupbasket (id, group_id, basket_id, list_display, list_event, list_event_data) VALUES (22, 'RESPONSABLE', 'EenvBasket', '{"templateColumns":7,"subInfos":[{"value":"getPriority","cssClasses":[],"icon":"fa-traffic-light"},{"value":"getCategory","cssClasses":[],"icon":"fa-exchange-alt"},{"value":"getDoctype","cssClasses":[],"icon":"fa-suitcase"},{"value":"getAssignee","cssClasses":[],"icon":"fa-sitemap"},{"value":"getRecipients","cssClasses":[],"icon":"fa-user"},{"value":"getSenders","cssClasses":[],"icon":"fa-book"},{"value":"getCreationAndProcessLimitDates","cssClasses":["align_rightData"],"icon":"fa-calendar"}]}', 'processDocument', '{"defaultTab": "dashboard"}');
INSERT INTO groupbasket (id, group_id, basket_id, list_display, list_event, list_event_data) VALUES (23, 'ARCHIVISTE', 'ToArcBasket', '{"templateColumns":7,"subInfos":[{"value":"getPriority","cssClasses":[],"icon":"fa-traffic-light"},{"value":"getCategory","cssClasses":[],"icon":"fa-exchange-alt"},{"value":"getDoctype","cssClasses":[],"icon":"fa-suitcase"},{"value":"getAssignee","cssClasses":[],"icon":"fa-sitemap"},{"value":"getRecipients","cssClasses":[],"icon":"fa-user"},{"value":"getSenders","cssClasses":[],"icon":"fa-book"},{"value":"getCreationAndProcessLimitDates","cssClasses":["align_rightData"],"icon":"fa-calendar"}]}', 'processDocument', '{"defaultTab": "dashboard"}');
INSERT INTO groupbasket (id, group_id, basket_id, list_display, list_event, list_event_data) VALUES (24, 'ARCHIVISTE', 'SentArcBasket', '{"templateColumns":7,"subInfos":[{"value":"getPriority","cssClasses":[],"icon":"fa-traffic-light"},{"value":"getCategory","cssClasses":[],"icon":"fa-exchange-alt"},{"value":"getDoctype","cssClasses":[],"icon":"fa-suitcase"},{"value":"getAssignee","cssClasses":[],"icon":"fa-sitemap"},{"value":"getRecipients","cssClasses":[],"icon":"fa-user"},{"value":"getSenders","cssClasses":[],"icon":"fa-book"},{"value":"getCreationAndProcessLimitDates","cssClasses":["align_rightData"],"icon":"fa-calendar"}]}', 'processDocument', '{"defaultTab": "dashboard"}');
INSERT INTO groupbasket (id, group_id, basket_id, list_display, list_event, list_event_data) VALUES (25, 'ARCHIVISTE', 'AckArcBasket', '{"templateColumns":7,"subInfos":[{"value":"getPriority","cssClasses":[],"icon":"fa-traffic-light"},{"value":"getCategory","cssClasses":[],"icon":"fa-exchange-alt"},{"value":"getDoctype","cssClasses":[],"icon":"fa-suitcase"},{"value":"getAssignee","cssClasses":[],"icon":"fa-sitemap"},{"value":"getRecipients","cssClasses":[],"icon":"fa-user"},{"value":"getSenders","cssClasses":[],"icon":"fa-book"},{"value":"getCreationAndProcessLimitDates","cssClasses":["align_rightData"],"icon":"fa-calendar"}]}', 'processDocument', '{"defaultTab": "dashboard"}');
INSERT INTO groupbasket (id, group_id, basket_id, list_display, list_event, list_event_data) VALUES (26, 'COURRIER', 'NumericBasket', '{"templateColumns":7,"subInfos":[{"value":"getPriority","cssClasses":[],"icon":"fa-traffic-light"},{"value":"getCategory","cssClasses":[],"icon":"fa-exchange-alt"},{"value":"getDoctype","cssClasses":[],"icon":"fa-suitcase"},{"value":"getAssignee","cssClasses":[],"icon":"fa-sitemap"},{"value":"getRecipients","cssClasses":[],"icon":"fa-user"},{"value":"getSenders","cssClasses":[],"icon":"fa-book"},{"value":"getCreationAndProcessLimitDates","cssClasses":["align_rightData"],"icon":"fa-calendar"}]}', 'processDocument', '{"defaultTab": "info", "canUpdateData": true}');
INSERT INTO groupbasket (id, group_id, basket_id, list_display, list_event, list_event_data) VALUES (27, 'AGENT', 'SendToSignatoryBook', '{"templateColumns":7,"subInfos":[{"value":"getPriority","cssClasses":[],"icon":"fa-traffic-light"},{"value":"getCategory","cssClasses":[],"icon":"fa-exchange-alt"},{"value":"getDoctype","cssClasses":[],"icon":"fa-suitcase"},{"value":"getAssignee","cssClasses":[],"icon":"fa-sitemap"},{"value":"getRecipients","cssClasses":[],"icon":"fa-user"},{"value":"getSenders","cssClasses":[],"icon":"fa-book"},{"value":"getCreationAndProcessLimitDates","cssClasses":["align_rightData"],"icon":"fa-calendar"}]}', 'documentDetails', '[]');
INSERT INTO groupbasket (id, group_id, basket_id, list_display, list_event, list_event_data) VALUES (28, 'RESPONSABLE', 'SendToSignatoryBook', '{"templateColumns":7,"subInfos":[{"value":"getPriority","cssClasses":[],"icon":"fa-traffic-light"},{"value":"getCategory","cssClasses":[],"icon":"fa-exchange-alt"},{"value":"getDoctype","cssClasses":[],"icon":"fa-suitcase"},{"value":"getAssignee","cssClasses":[],"icon":"fa-sitemap"},{"value":"getRecipients","cssClasses":[],"icon":"fa-user"},{"value":"getSenders","cssClasses":[],"icon":"fa-book"},{"value":"getCreationAndProcessLimitDates","cssClasses":["align_rightData"],"icon":"fa-calendar"}]}', 'documentDetails', '[]');
INSERT INTO groupbasket (id, group_id, basket_id, list_display, list_event, list_event_data) VALUES (31, 'AGENT', 'Maileva_Sended', '{"templateColumns":7,"subInfos":[{"value":"getPriority","cssClasses":[],"icon":"fa-traffic-light"},{"value":"getCategory","cssClasses":[],"icon":"fa-exchange-alt"},{"value":"getDoctype","cssClasses":[],"icon":"fa-suitcase"},{"value":"getAssignee","cssClasses":[],"icon":"fa-sitemap"},{"value":"getRecipients","cssClasses":[],"icon":"fa-user"},{"value":"getSenders","cssClasses":[],"icon":"fa-book"},{"value":"getCreationAndProcessLimitDates","cssClasses":["align_rightData"],"icon":"fa-calendar"}]}', 'processDocument', '{"defaultTab": "dashboard"}');
INSERT INTO groupbasket (id, group_id, basket_id, list_display, list_event, list_event_data) VALUES (13, 'AGENT', 'MyBasket', '{"templateColumns":7,"subInfos":[{"value":"getPriority","label":"Priorit\u00e9","sample":"Urgent","cssClasses":[],"icon":"fa-traffic-light"},{"value":"getCategory","label":"Cat\u00e9gorie","sample":"Courrier arriv\u00e9e","cssClasses":[],"icon":"fa-exchange-alt"},{"value":"getDoctype","label":"Type de courrier","sample":"R\u00e9clamation","cssClasses":[],"icon":"fa-suitcase"},{"value":"getAssignee","label":"Attributaire (entit\u00e9 traitante)","sample":"Barbara BAIN (P\u00f4le Jeunesse et Sport)","cssClasses":[],"icon":"fa-sitemap"},{"value":"getRecipients","label":"Destinataire","sample":"Patricia PETIT","cssClasses":[],"icon":"fa-user"},{"value":"getSenders","label":"Exp\u00e9diteur","sample":"Alain DUBOIS (MAARCH)","cssClasses":[],"icon":"fa-book"},{"value":"getCreationAndProcessLimitDates","cssClasses":["align_rightData"],"icon":"fa-calendar"},{"value":"getFolders","label":"Dossiers (emplacement fixe)","sample":"Litiges","cssClasses":["align_leftData"],"icon":"fa-folder"}]}', 'processDocument', '{"defaultTab": "dashboard", "canUpdateData": true, "canUpdateModel": false}');
INSERT INTO groupbasket (id, group_id, basket_id, list_display, list_event, list_event_data) VALUES (29, 'AGENT', 'AR_Create', '{"templateColumns":7,"subInfos":[{"value":"getPriority","label":"Priorit\u00e9","sample":"Urgent","cssClasses":[],"icon":"fa-traffic-light"},{"value":"getCategory","label":"Cat\u00e9gorie","sample":"Courrier arriv\u00e9e","cssClasses":[],"icon":"fa-exchange-alt"},{"value":"getDoctype","label":"Type de courrier","sample":"R\u00e9clamation","cssClasses":[],"icon":"fa-suitcase"},{"value":"getAssignee","label":"Attributaire (entit\u00e9 traitante)","sample":"Barbara BAIN (P\u00f4le Jeunesse et Sport)","cssClasses":[],"icon":"fa-sitemap"},{"value":"getRecipients","label":"Destinataire","sample":"Patricia PETIT","cssClasses":[],"icon":"fa-user"},{"value":"getSenders","label":"Exp\u00e9diteur","sample":"Alain DUBOIS (MAARCH)","cssClasses":[],"icon":"fa-book"},{"value":"getCreationAndProcessLimitDates","cssClasses":["align_rightData"],"icon":"fa-calendar"}]}', 'processDocument', '{"defaultTab": "emails", "canUpdateData": true, "canUpdateModel": false}');
INSERT INTO groupbasket (id, group_id, basket_id, list_display, list_event, list_event_data) VALUES (30, 'AGENT', 'AR_AlreadySend', '{"templateColumns":7,"subInfos":[{"value":"getPriority","label":"Priorit\u00e9","sample":"Urgent","cssClasses":[],"icon":"fa-traffic-light"},{"value":"getCategory","label":"Cat\u00e9gorie","sample":"Courrier arriv\u00e9e","cssClasses":[],"icon":"fa-exchange-alt"},{"value":"getDoctype","label":"Type de courrier","sample":"R\u00e9clamation","cssClasses":[],"icon":"fa-suitcase"},{"value":"getAssignee","label":"Attributaire (entit\u00e9 traitante)","sample":"Barbara BAIN (P\u00f4le Jeunesse et Sport)","cssClasses":[],"icon":"fa-sitemap"},{"value":"getRecipients","label":"Destinataire","sample":"Patricia PETIT","cssClasses":[],"icon":"fa-user"},{"value":"getSenders","label":"Exp\u00e9diteur","sample":"Alain DUBOIS (MAARCH)","cssClasses":[],"icon":"fa-book"},{"value":"getCreationAndProcessLimitDates","cssClasses":["align_rightData"],"icon":"fa-calendar"}]}', 'documentDetails', NULL);
INSERT INTO groupbasket (id, group_id, basket_id, list_display, list_event, list_event_data) VALUES (7, 'ELU', 'DdeAvisBasket', '{"templateColumns":5,"subInfos":[{"value":"getPriority","label":"Priorit\u00e9","sample":"Urgent","cssClasses":[],"icon":"fa-traffic-light"},{"value":"getCategory","label":"Cat\u00e9gorie","sample":"Courrier arriv\u00e9e","cssClasses":[],"icon":"fa-exchange-alt"},{"value":"getDoctype","label":"Type de courrier","sample":"R\u00e9clamation","cssClasses":[],"icon":"fa-suitcase"},{"value":"getParallelOpinionsNumber","label":"Nombre d''avis donn\u00e9s","sample":"<b>3<\/b> avis donn\u00e9(s)","cssClasses":["align_rightData"],"icon":"fa-comment-alt"},{"value":"getOpinionLimitDate","label":"Date limite d''envoi des avis","sample":"01-01-2019","cssClasses":["align_rightData"],"icon":"fa-stopwatch"}]}', 'processDocument', '{"defaultTab": "notes", "canUpdateData": false, "canUpdateModel": false}');
INSERT INTO groupbasket (id, group_id, basket_id, list_display, list_event, list_event_data) VALUES (14, 'RESPONSABLE', 'MyBasket', '{"templateColumns":7,"subInfos":[{"value":"getPriority","label":"Priorit\u00e9","sample":"Urgent","cssClasses":[],"icon":"fa-traffic-light"},{"value":"getCategory","label":"Cat\u00e9gorie","sample":"Courrier arriv\u00e9e","cssClasses":[],"icon":"fa-exchange-alt"},{"value":"getDoctype","label":"Type de courrier","sample":"R\u00e9clamation","cssClasses":[],"icon":"fa-suitcase"},{"value":"getAssignee","label":"Attributaire (entit\u00e9 traitante)","sample":"Barbara BAIN (P\u00f4le Jeunesse et Sport)","cssClasses":[],"icon":"fa-sitemap"},{"value":"getRecipients","label":"Destinataire","sample":"Patricia PETIT","cssClasses":[],"icon":"fa-user"},{"value":"getSenders","label":"Exp\u00e9diteur","sample":"Alain DUBOIS (MAARCH)","cssClasses":[],"icon":"fa-book"},{"value":"getCreationAndProcessLimitDates","cssClasses":["align_rightData"],"icon":"fa-calendar"},{"value":"getFolders","label":"Dossiers (emplacement fixe)","sample":"Litiges","cssClasses":["align_leftData"],"icon":"fa-folder"}]}', 'processDocument', '{"defaultTab": "dashboard", "canUpdateData": false, "canUpdateModel": false}');
INSERT INTO groupbasket (id, group_id, basket_id, list_display, list_event, list_event_data) VALUES (18, 'RESPONSABLE', 'ParafBasket', '{"templateColumns":7,"subInfos":[{"value":"getPriority","label":"Priorit\u00e9","sample":"Urgent","cssClasses":[],"icon":"fa-traffic-light"},{"value":"getCategory","label":"Cat\u00e9gorie","sample":"Courrier arriv\u00e9e","cssClasses":[],"icon":"fa-exchange-alt"},{"value":"getDoctype","label":"Type de courrier","sample":"R\u00e9clamation","cssClasses":[],"icon":"fa-suitcase"},{"value":"getAssignee","label":"Attributaire (entit\u00e9 traitante)","sample":"Barbara BAIN (P\u00f4le Jeunesse et Sport)","cssClasses":[],"icon":"fa-sitemap"},{"value":"getRecipients","label":"Destinataire","sample":"Patricia PETIT","cssClasses":[],"icon":"fa-user"},{"value":"getSenders","label":"Exp\u00e9diteur","sample":"Alain DUBOIS (MAARCH)","cssClasses":[],"icon":"fa-book"},{"value":"getCreationAndProcessLimitDates","cssClasses":["align_rightData"],"icon":"fa-calendar"},{"value":"getFolders","label":"Dossiers (emplacement fixe)","sample":"Litiges","cssClasses":["align_leftData"],"icon":"fa-folder"}]}', 'signatureBookAction', '{"canUpdateDocuments": true}');
INSERT INTO groupbasket (id, group_id, basket_id, list_display, list_event, list_event_data) VALUES (33, 'AGENT', 'outlook_mails', '{"templateColumns":0,"subInfos":[]}', 'processDocument', '{"defaultTab": "info", "canUpdateData": true, "canUpdateModel": true}');
INSERT INTO groupbasket (id, group_id, basket_id, list_display, list_event, list_event_data) VALUES (34, 'DIRECTEUR', 'outlook_mails', '{"templateColumns":0,"subInfos":[]}', 'processDocument', '{"defaultTab": "info", "canUpdateData": true, "canUpdateModel": true}');
INSERT INTO groupbasket (id, group_id, basket_id, list_display, list_event, list_event_data) VALUES (35, 'RESPONSABLE', 'outlook_mails', '{"templateColumns":0,"subInfos":[]}', 'processDocument', '{"defaultTab": "info", "canUpdateData": true, "canUpdateModel": true}');
INSERT INTO groupbasket (id, group_id, basket_id, list_display, list_event, list_event_data) VALUES (36, 'COURRIER', 'outlook_mails', '{"templateColumns":0,"subInfos":[]}', 'processDocument', '{"defaultTab": "info", "canUpdateData": true, "canUpdateModel": true}');
INSERT INTO groupbasket (id, group_id, basket_id, list_display, list_event, list_event_data) VALUES (37, 'RESP_COURRIER', 'outlook_mails', '{"templateColumns":0,"subInfos":[]}', 'processDocument', '{"defaultTab": "info", "canUpdateData": true, "canUpdateModel": true}');


INSERT INTO groupbasket_redirect (system_id, group_id, basket_id, action_id, entity_id, keyword, redirect_mode) VALUES (600, 'COURRIER', 'QualificationBasket', 18, '', 'ALL_ENTITIES', 'ENTITY');
INSERT INTO groupbasket_redirect (system_id, group_id, basket_id, action_id, entity_id, keyword, redirect_mode) VALUES (601, 'COURRIER', 'NumericBasket', 18, '', 'ALL_ENTITIES', 'ENTITY');
INSERT INTO groupbasket_redirect (system_id, group_id, basket_id, action_id, entity_id, keyword, redirect_mode) VALUES (607, 'RESPONSABLE', 'MyBasket', 1, '', 'MY_ENTITIES', 'ENTITY');
INSERT INTO groupbasket_redirect (system_id, group_id, basket_id, action_id, entity_id, keyword, redirect_mode) VALUES (608, 'RESPONSABLE', 'MyBasket', 1, '', 'ENTITIES_BELOW', 'ENTITY');
INSERT INTO groupbasket_redirect (system_id, group_id, basket_id, action_id, entity_id, keyword, redirect_mode) VALUES (609, 'RESPONSABLE', 'MyBasket', 1, '', 'ENTITIES_JUST_UP', 'ENTITY');
INSERT INTO groupbasket_redirect (system_id, group_id, basket_id, action_id, entity_id, keyword, redirect_mode) VALUES (610, 'RESPONSABLE', 'MyBasket', 1, '', 'SAME_LEVEL_ENTITIES', 'ENTITY');
INSERT INTO groupbasket_redirect (system_id, group_id, basket_id, action_id, entity_id, keyword, redirect_mode) VALUES (611, 'RESPONSABLE', 'MyBasket', 1, '', 'MY_ENTITIES', 'USERS');
INSERT INTO groupbasket_redirect (system_id, group_id, basket_id, action_id, entity_id, keyword, redirect_mode) VALUES (612, 'RESPONSABLE', 'DepartmentBasket', 1, '', 'MY_ENTITIES', 'ENTITY');
INSERT INTO groupbasket_redirect (system_id, group_id, basket_id, action_id, entity_id, keyword, redirect_mode) VALUES (613, 'RESPONSABLE', 'DepartmentBasket', 1, '', 'ENTITIES_BELOW', 'ENTITY');
INSERT INTO groupbasket_redirect (system_id, group_id, basket_id, action_id, entity_id, keyword, redirect_mode) VALUES (614, 'RESPONSABLE', 'DepartmentBasket', 1, '', 'ENTITIES_JUST_UP', 'ENTITY');
INSERT INTO groupbasket_redirect (system_id, group_id, basket_id, action_id, entity_id, keyword, redirect_mode) VALUES (615, 'RESPONSABLE', 'DepartmentBasket', 1, '', 'SAME_LEVEL_ENTITIES', 'ENTITY');
INSERT INTO groupbasket_redirect (system_id, group_id, basket_id, action_id, entity_id, keyword, redirect_mode) VALUES (616, 'RESPONSABLE', 'DepartmentBasket', 1, '', 'MY_ENTITIES', 'USERS');
INSERT INTO groupbasket_redirect (system_id, group_id, basket_id, action_id, entity_id, keyword, redirect_mode) VALUES (617, 'ELU', 'MyBasket', 1, '', 'ALL_ENTITIES', 'ENTITY');
INSERT INTO groupbasket_redirect (system_id, group_id, basket_id, action_id, entity_id, keyword, redirect_mode) VALUES (619, 'AGENT', 'DepartmentBasket', 1, '', 'ALL_ENTITIES', 'ENTITY');
INSERT INTO groupbasket_redirect (system_id, group_id, basket_id, action_id, entity_id, keyword, redirect_mode) VALUES (620, 'RESPONSABLE', 'MyBasket', 1, '', 'ALL_ENTITIES', 'ENTITY');
INSERT INTO groupbasket_redirect (system_id, group_id, basket_id, action_id, entity_id, keyword, redirect_mode) VALUES (621, 'RESPONSABLE', 'DepartmentBasket', 1, '', 'ALL_ENTITIES', 'ENTITY');
INSERT INTO groupbasket_redirect (system_id, group_id, basket_id, action_id, entity_id, keyword, redirect_mode) VALUES (678, 'AGENT', 'MyBasket', 1, '', 'MY_ENTITIES', 'ENTITY');
INSERT INTO groupbasket_redirect (system_id, group_id, basket_id, action_id, entity_id, keyword, redirect_mode) VALUES (679, 'AGENT', 'MyBasket', 1, '', 'SAME_LEVEL_ENTITIES', 'ENTITY');
INSERT INTO groupbasket_redirect (system_id, group_id, basket_id, action_id, entity_id, keyword, redirect_mode) VALUES (680, 'AGENT', 'MyBasket', 1, '', 'MY_PRIMARY_ENTITY', 'ENTITY');
INSERT INTO groupbasket_redirect (system_id, group_id, basket_id, action_id, entity_id, keyword, redirect_mode) VALUES (681, 'AGENT', 'MyBasket', 1, '', 'ENTITIES_JUST_UP', 'ENTITY');
INSERT INTO groupbasket_redirect (system_id, group_id, basket_id, action_id, entity_id, keyword, redirect_mode) VALUES (726, 'DIRECTEUR', 'outlook_mails', 18, '', 'ALL_ENTITIES', 'ENTITY');
INSERT INTO groupbasket_redirect (system_id, group_id, basket_id, action_id, entity_id, keyword, redirect_mode) VALUES (727, 'DIRECTEUR', 'outlook_mails', 18, '', 'ALL_ENTITIES', 'USERS');
INSERT INTO groupbasket_redirect (system_id, group_id, basket_id, action_id, entity_id, keyword, redirect_mode) VALUES (728, 'RESPONSABLE', 'outlook_mails', 18, '', 'ALL_ENTITIES', 'ENTITY');
INSERT INTO groupbasket_redirect (system_id, group_id, basket_id, action_id, entity_id, keyword, redirect_mode) VALUES (729, 'RESPONSABLE', 'outlook_mails', 18, '', 'ALL_ENTITIES', 'USERS');
INSERT INTO groupbasket_redirect (system_id, group_id, basket_id, action_id, entity_id, keyword, redirect_mode) VALUES (730, 'COURRIER', 'outlook_mails', 18, '', 'ALL_ENTITIES', 'ENTITY');
INSERT INTO groupbasket_redirect (system_id, group_id, basket_id, action_id, entity_id, keyword, redirect_mode) VALUES (731, 'COURRIER', 'outlook_mails', 18, '', 'ALL_ENTITIES', 'USERS');
INSERT INTO groupbasket_redirect (system_id, group_id, basket_id, action_id, entity_id, keyword, redirect_mode) VALUES (732, 'RESP_COURRIER', 'outlook_mails', 18, '', 'ALL_ENTITIES', 'ENTITY');
INSERT INTO groupbasket_redirect (system_id, group_id, basket_id, action_id, entity_id, keyword, redirect_mode) VALUES (733, 'RESP_COURRIER', 'outlook_mails', 18, '', 'ALL_ENTITIES', 'USERS');
INSERT INTO groupbasket_redirect (system_id, group_id, basket_id, action_id, entity_id, keyword, redirect_mode) VALUES (724, 'AGENT', 'outlook_mails', 18, '', 'ALL_ENTITIES', 'ENTITY');
INSERT INTO groupbasket_redirect (system_id, group_id, basket_id, action_id, entity_id, keyword, redirect_mode) VALUES (725, 'AGENT', 'outlook_mails', 18, '', 'ALL_ENTITIES', 'USERS');

INSERT INTO indexing_models (id, label, category, "default", owner, private, master, enabled, mandatory_file) VALUES (2, 'Courrier Départ', 'outgoing', false, 23, false, NULL, true, false);
INSERT INTO indexing_models (id, label, category, "default", owner, private, master, enabled, mandatory_file) VALUES (3, 'Note Interne', 'internal', false, 23, false, NULL, true, false);
INSERT INTO indexing_models (id, label, category, "default", owner, private, master, enabled, mandatory_file) VALUES (4, 'Document GED', 'ged_doc', false, 23, false, NULL, true, false);
INSERT INTO indexing_models (id, label, category, "default", owner, private, master, enabled, mandatory_file) VALUES (1, 'Courrier Arrivée', 'incoming', true, 23, false, NULL, true, true);
INSERT INTO indexing_models (id, label, category, "default", owner, private, master, enabled, mandatory_file) VALUES (5, 'Exemple de données pré-enregistrées', 'incoming', false, 21, true, 1, true, true);
INSERT INTO indexing_models (id, label, category, "default", owner, private, master, enabled, mandatory_file) VALUES (7, 'Demande de documents', 'outgoing', false, 16, true, 2, true, false);
INSERT INTO indexing_models (id, label, category, "default", owner, private, master, enabled, mandatory_file) VALUES (8, 'Courriels importés', 'incoming', false, 23, false, NULL, true, false);

INSERT INTO indexing_models_fields (id, model_id, identifier, mandatory, enabled, default_value, unit) VALUES (9, 2, 'doctype', true, true, NULL, 'mail');
INSERT INTO indexing_models_fields (id, model_id, identifier, mandatory, enabled, default_value, unit) VALUES (10, 2, 'priority', true, true, NULL, 'mail');
INSERT INTO indexing_models_fields (id, model_id, identifier, mandatory, enabled, default_value, unit) VALUES (11, 2, 'confidentiality', true, true, NULL, 'mail');
INSERT INTO indexing_models_fields (id, model_id, identifier, mandatory, enabled, default_value, unit) VALUES (12, 2, 'documentDate', true, true, NULL, 'mail');
INSERT INTO indexing_models_fields (id, model_id, identifier, mandatory, enabled, default_value, unit) VALUES (13, 2, 'departureDate', true, true, NULL, 'mail');
INSERT INTO indexing_models_fields (id, model_id, identifier, mandatory, enabled, default_value, unit) VALUES (14, 2, 'subject', true, true, NULL, 'mail');
INSERT INTO indexing_models_fields (id, model_id, identifier, mandatory, enabled, default_value, unit) VALUES (15, 2, 'senders', false, true, NULL, 'contact');
INSERT INTO indexing_models_fields (id, model_id, identifier, mandatory, enabled, default_value, unit) VALUES (16, 2, 'recipients', true, true, NULL, 'contact');
INSERT INTO indexing_models_fields (id, model_id, identifier, mandatory, enabled, default_value, unit) VALUES (17, 2, 'initiator', true, true, NULL, 'process');
INSERT INTO indexing_models_fields (id, model_id, identifier, mandatory, enabled, default_value, unit) VALUES (18, 2, 'destination', true, true, NULL, 'process');
INSERT INTO indexing_models_fields (id, model_id, identifier, mandatory, enabled, default_value, unit) VALUES (19, 2, 'processLimitDate', true, true, NULL, 'process');
INSERT INTO indexing_models_fields (id, model_id, identifier, mandatory, enabled, default_value, unit) VALUES (20, 2, 'folders', false, true, NULL, 'classifying');
INSERT INTO indexing_models_fields (id, model_id, identifier, mandatory, enabled, default_value, unit) VALUES (21, 2, 'tags', false, true, NULL, 'classifying');
INSERT INTO indexing_models_fields (id, model_id, identifier, mandatory, enabled, default_value, unit) VALUES (22, 3, 'doctype', true, true, NULL, 'mail');
INSERT INTO indexing_models_fields (id, model_id, identifier, mandatory, enabled, default_value, unit) VALUES (23, 3, 'priority', true, true, NULL, 'mail');
INSERT INTO indexing_models_fields (id, model_id, identifier, mandatory, enabled, default_value, unit) VALUES (24, 3, 'confidentiality', true, true, NULL, 'mail');
INSERT INTO indexing_models_fields (id, model_id, identifier, mandatory, enabled, default_value, unit) VALUES (25, 3, 'documentDate', true, true, '"_TODAY"', 'mail');
INSERT INTO indexing_models_fields (id, model_id, identifier, mandatory, enabled, default_value, unit) VALUES (26, 3, 'subject', true, true, NULL, 'mail');
INSERT INTO indexing_models_fields (id, model_id, identifier, mandatory, enabled, default_value, unit) VALUES (27, 3, 'senders', false, true, '[]', 'contact');
INSERT INTO indexing_models_fields (id, model_id, identifier, mandatory, enabled, default_value, unit) VALUES (28, 3, 'initiator', true, true, NULL, 'process');
INSERT INTO indexing_models_fields (id, model_id, identifier, mandatory, enabled, default_value, unit) VALUES (29, 3, 'destination', true, true, NULL, 'process');
INSERT INTO indexing_models_fields (id, model_id, identifier, mandatory, enabled, default_value, unit) VALUES (30, 3, 'processLimitDate', true, true, NULL, 'process');
INSERT INTO indexing_models_fields (id, model_id, identifier, mandatory, enabled, default_value, unit) VALUES (31, 3, 'folders', false, true, NULL, 'classifying');
INSERT INTO indexing_models_fields (id, model_id, identifier, mandatory, enabled, default_value, unit) VALUES (32, 3, 'tags', false, true, NULL, 'classifying');
INSERT INTO indexing_models_fields (id, model_id, identifier, mandatory, enabled, default_value, unit) VALUES (33, 4, 'doctype', true, true, NULL, 'mail');
INSERT INTO indexing_models_fields (id, model_id, identifier, mandatory, enabled, default_value, unit) VALUES (34, 4, 'documentDate', true, true, NULL, 'mail');
INSERT INTO indexing_models_fields (id, model_id, identifier, mandatory, enabled, default_value, unit) VALUES (35, 4, 'subject', true, true, NULL, 'mail');
INSERT INTO indexing_models_fields (id, model_id, identifier, mandatory, enabled, default_value, unit) VALUES (36, 4, 'senders', false, true, NULL, 'contact');
INSERT INTO indexing_models_fields (id, model_id, identifier, mandatory, enabled, default_value, unit) VALUES (37, 4, 'destination', true, true, NULL, 'process');
INSERT INTO indexing_models_fields (id, model_id, identifier, mandatory, enabled, default_value, unit) VALUES (38, 4, 'indexingCustomField_1', false, true, NULL, 'process');
INSERT INTO indexing_models_fields (id, model_id, identifier, mandatory, enabled, default_value, unit) VALUES (39, 4, 'folders', false, true, NULL, 'classifying');
INSERT INTO indexing_models_fields (id, model_id, identifier, mandatory, enabled, default_value, unit) VALUES (40, 4, 'tags', false, true, NULL, 'classifying');
INSERT INTO indexing_models_fields (id, model_id, identifier, mandatory, enabled, default_value, unit) VALUES (41, 1, 'doctype', true, true, NULL, 'mail');
INSERT INTO indexing_models_fields (id, model_id, identifier, mandatory, enabled, default_value, unit) VALUES (42, 1, 'priority', true, true, NULL, 'mail');
INSERT INTO indexing_models_fields (id, model_id, identifier, mandatory, enabled, default_value, unit) VALUES (43, 1, 'documentDate', true, true, NULL, 'mail');
INSERT INTO indexing_models_fields (id, model_id, identifier, mandatory, enabled, default_value, unit) VALUES (44, 1, 'arrivalDate', true, true, '"_TODAY"', 'mail');
INSERT INTO indexing_models_fields (id, model_id, identifier, mandatory, enabled, default_value, unit) VALUES (45, 1, 'subject', true, true, NULL, 'mail');
INSERT INTO indexing_models_fields (id, model_id, identifier, mandatory, enabled, default_value, unit) VALUES (46, 1, 'senders', true, true, NULL, 'contact');
INSERT INTO indexing_models_fields (id, model_id, identifier, mandatory, enabled, default_value, unit) VALUES (47, 1, 'destination', true, true, NULL, 'process');
INSERT INTO indexing_models_fields (id, model_id, identifier, mandatory, enabled, default_value, unit) VALUES (48, 1, 'processLimitDate', true, true, NULL, 'process');
INSERT INTO indexing_models_fields (id, model_id, identifier, mandatory, enabled, default_value, unit) VALUES (49, 5, 'doctype', true, true, '1202', 'mail');
INSERT INTO indexing_models_fields (id, model_id, identifier, mandatory, enabled, default_value, unit) VALUES (50, 5, 'priority', true, true, '"poiuytre1391nbvc"', 'mail');
INSERT INTO indexing_models_fields (id, model_id, identifier, mandatory, enabled, default_value, unit) VALUES (51, 5, 'documentDate', true, true, '"2021-03-24"', 'mail');
INSERT INTO indexing_models_fields (id, model_id, identifier, mandatory, enabled, default_value, unit) VALUES (52, 5, 'arrivalDate', true, true, '"2021-03-24"', 'mail');
INSERT INTO indexing_models_fields (id, model_id, identifier, mandatory, enabled, default_value, unit) VALUES (53, 5, 'subject', true, true, '"Demande d''interventions"', 'mail');
INSERT INTO indexing_models_fields (id, model_id, identifier, mandatory, enabled, default_value, unit) VALUES (54, 5, 'senders', true, true, '[{"type":"contact","id":6,"label":"Bernard PASCONTENT"}]', 'contact');
INSERT INTO indexing_models_fields (id, model_id, identifier, mandatory, enabled, default_value, unit) VALUES (55, 5, 'destination', true, true, '10', 'process');
INSERT INTO indexing_models_fields (id, model_id, identifier, mandatory, enabled, default_value, unit) VALUES (56, 5, 'diffusionList', false, true, '[{"id":16,"mode":"dest","type":"user"},{"id":12,"mode":"cc","type":"entity"},{"id":20,"mode":"cc","type":"entity"}]', 'process');
INSERT INTO indexing_models_fields (id, model_id, identifier, mandatory, enabled, default_value, unit) VALUES (57, 5, 'processLimitDate', true, true, '"2021-03-30"', 'process');
INSERT INTO indexing_models_fields (id, model_id, identifier, mandatory, enabled, default_value, unit) VALUES (72, 7, 'doctype', true, true, '106', 'mail');
INSERT INTO indexing_models_fields (id, model_id, identifier, mandatory, enabled, default_value, unit) VALUES (73, 7, 'priority', true, true, '"poiuytre1357nbvc"', 'mail');
INSERT INTO indexing_models_fields (id, model_id, identifier, mandatory, enabled, default_value, unit) VALUES (74, 7, 'confidentiality', true, true, 'false', 'mail');
INSERT INTO indexing_models_fields (id, model_id, identifier, mandatory, enabled, default_value, unit) VALUES (75, 7, 'documentDate', true, true, '"2021-03-25"', 'mail');
INSERT INTO indexing_models_fields (id, model_id, identifier, mandatory, enabled, default_value, unit) VALUES (76, 7, 'departureDate', true, true, '"2021-03-30"', 'mail');
INSERT INTO indexing_models_fields (id, model_id, identifier, mandatory, enabled, default_value, unit) VALUES (77, 7, 'subject', true, true, '"Demande de Kbis"', 'mail');
INSERT INTO indexing_models_fields (id, model_id, identifier, mandatory, enabled, default_value, unit) VALUES (78, 7, 'senders', false, true, '[{"type":"entity","id":10,"label":"P\u00f4le Technique"}]', 'contact');
INSERT INTO indexing_models_fields (id, model_id, identifier, mandatory, enabled, default_value, unit) VALUES (79, 7, 'recipients', true, true, '[{"type":"contact","id":10,"label":"Carole COTIN (MAARCH)"}]', 'contact');
INSERT INTO indexing_models_fields (id, model_id, identifier, mandatory, enabled, default_value, unit) VALUES (80, 7, 'initiator', true, true, '10', 'process');
INSERT INTO indexing_models_fields (id, model_id, identifier, mandatory, enabled, default_value, unit) VALUES (81, 7, 'destination', true, true, '10', 'process');
INSERT INTO indexing_models_fields (id, model_id, identifier, mandatory, enabled, default_value, unit) VALUES (82, 7, 'diffusionList', false, true, '[{"id":16,"mode":"dest","type":"user"},{"id":12,"mode":"cc","type":"entity"},{"id":20,"mode":"cc","type":"entity"}]', 'process');
INSERT INTO indexing_models_fields (id, model_id, identifier, mandatory, enabled, default_value, unit) VALUES (83, 7, 'processLimitDate', true, true, '"2021-06-18"', 'process');
INSERT INTO indexing_models_fields (id, model_id, identifier, mandatory, enabled, default_value, unit) VALUES (84, 7, 'folders', false, true, '[16]', 'classifying');
INSERT INTO indexing_models_fields (id, model_id, identifier, mandatory, enabled, default_value, unit) VALUES (85, 7, 'tags', false, true, '[4]', 'classifying');
INSERT INTO indexing_models_fields (id, model_id, identifier, mandatory, enabled, default_value, unit) VALUES (86, 8, 'doctype', true, true, NULL, 'mail');
INSERT INTO indexing_models_fields (id, model_id, identifier, mandatory, enabled, default_value, unit) VALUES (87, 8, 'documentDate', false, true, NULL, 'mail');
INSERT INTO indexing_models_fields (id, model_id, identifier, mandatory, enabled, default_value, unit) VALUES (88, 8, 'priority', false, true, '"poiuytre1357nbvc"', 'mail');
INSERT INTO indexing_models_fields (id, model_id, identifier, mandatory, enabled, default_value, unit) VALUES (89, 8, 'subject', true, true, NULL, 'mail');
INSERT INTO indexing_models_fields (id, model_id, identifier, mandatory, enabled, default_value, unit) VALUES (90, 8, 'senders', false, true, NULL, 'contact');
INSERT INTO indexing_models_fields (id, model_id, identifier, mandatory, enabled, default_value, unit) VALUES (91, 8, 'destination', false, true, '"#myPrimaryEntity"', 'process');
INSERT INTO indexing_models_fields (id, model_id, identifier, mandatory, enabled, default_value, unit) VALUES (92, 8, 'processLimitDate', false, true, '"2021-05-13"', 'process');

INSERT INTO list_templates (id, title, description, type, entity_id, owner) VALUES (1, 'Ville de Maarch-les-bains', 'Ville de Maarch-les-bains', 'diffusionList', 1, NULL);
INSERT INTO list_templates (id, title, description, type, entity_id, owner) VALUES (2, 'Cabinet du Maire', 'Cabinet du Maire', 'diffusionList', 2, NULL);
INSERT INTO list_templates (id, title, description, type, entity_id, owner) VALUES (3, 'Direction Générale des Services', 'Direction Générale des Services', 'diffusionList', 3, NULL);
INSERT INTO list_templates (id, title, description, type, entity_id, owner) VALUES (4, 'Direction Générale Adjointe', 'Direction Générale Adjointe', 'diffusionList', 4, NULL);
INSERT INTO list_templates (id, title, description, type, entity_id, owner) VALUES (5, 'Pôle Culturel', 'Pôle Culturel', 'diffusionList', 5, NULL);
INSERT INTO list_templates (id, title, description, type, entity_id, owner) VALUES (6, 'Pôle Jeunesse et Sport', 'Pôle Jeunesse et Sport', 'diffusionList', 6, NULL);
INSERT INTO list_templates (id, title, description, type, entity_id, owner) VALUES (7, 'Petite enfance', 'Petite enfance', 'diffusionList', 7, NULL);
INSERT INTO list_templates (id, title, description, type, entity_id, owner) VALUES (8, 'Sport', 'Sport', 'diffusionList', 8, NULL);
INSERT INTO list_templates (id, title, description, type, entity_id, owner) VALUES (9, 'Pôle Social', 'Pôle Social', 'diffusionList', 9, NULL);
INSERT INTO list_templates (id, title, description, type, entity_id, owner) VALUES (1009, 'visa Pôle Social', 'visa Pôle Social', 'visaCircuit', 9, NULL);
INSERT INTO list_templates (id, title, description, type, entity_id, owner) VALUES (10, 'Pôle Technique', 'Pôle Technique', 'diffusionList', 10, NULL);
INSERT INTO list_templates (id, title, description, type, entity_id, owner) VALUES (1010, 'visa Pôle Technique', 'visa Pôle Technique', 'visaCircuit', 10, NULL);
INSERT INTO list_templates (id, title, description, type, entity_id, owner) VALUES (11, 'Direction des Ressources Humaines', 'Direction des Ressources Humaines', 'diffusionList', 11, NULL);
INSERT INTO list_templates (id, title, description, type, entity_id, owner) VALUES (12, 'Secrétariat Général', 'Secrétariat Général', 'diffusionList', 12, NULL);
INSERT INTO list_templates (id, title, description, type, entity_id, owner) VALUES (13, 'Service Courrier', 'Service Courrier', 'diffusionList', 13, NULL);
INSERT INTO list_templates (id, title, description, type, entity_id, owner) VALUES (14, 'Correspondants Archive', 'Correspondants Archive', 'diffusionList', 14, NULL);
INSERT INTO list_templates (id, title, description, type, entity_id, owner) VALUES (15, 'Services Fonctionnels', 'Pôle des Services Fonctionnels', 'diffusionList', 15, NULL);
INSERT INTO list_templates (id, title, description, type, entity_id, owner) VALUES (16, 'Direction des Systèmes d''Information', 'Direction des Systèmes d''Information', 'diffusionList', 16, NULL);
INSERT INTO list_templates (id, title, description, type, entity_id, owner) VALUES (17, 'Direction des Finances', 'Direction des Finances', 'diffusionList', 17, NULL);
INSERT INTO list_templates (id, title, description, type, entity_id, owner) VALUES (18, 'Pôle Juridique', 'Pôle Juridique', 'diffusionList', 18, NULL);

INSERT INTO list_templates_items (id, list_template_id, item_id, item_type, item_mode, sequence) VALUES (1, 1, 15, 'user', 'dest', 0);
INSERT INTO list_templates_items (id, list_template_id, item_id, item_type, item_mode, sequence) VALUES (2, 2, 7, 'user', 'dest', 0);
INSERT INTO list_templates_items (id, list_template_id, item_id, item_type, item_mode, sequence) VALUES (3, 2, 12, 'entity', 'cc', 1);
INSERT INTO list_templates_items (id, list_template_id, item_id, item_type, item_mode, sequence) VALUES (4, 2, 10, 'user', 'cc', 2);
INSERT INTO list_templates_items (id, list_template_id, item_id, item_type, item_mode, sequence) VALUES (5, 2, 3, 'user', 'cc', 3);
INSERT INTO list_templates_items (id, list_template_id, item_id, item_type, item_mode, sequence) VALUES (6, 3, 1, 'user', 'dest', 0);
INSERT INTO list_templates_items (id, list_template_id, item_id, item_type, item_mode, sequence) VALUES (7, 3, 10, 'user', 'cc', 1);
INSERT INTO list_templates_items (id, list_template_id, item_id, item_type, item_mode, sequence) VALUES (8, 4, 17, 'user', 'dest', 0);
INSERT INTO list_templates_items (id, list_template_id, item_id, item_type, item_mode, sequence) VALUES (9, 4, 12, 'entity', 'cc', 1);
INSERT INTO list_templates_items (id, list_template_id, item_id, item_type, item_mode, sequence) VALUES (10, 4, 8, 'user', 'cc', 2);
INSERT INTO list_templates_items (id, list_template_id, item_id, item_type, item_mode, sequence) VALUES (11, 5, 9, 'user', 'dest', 0);
INSERT INTO list_templates_items (id, list_template_id, item_id, item_type, item_mode, sequence) VALUES (12, 5, 12, 'entity', 'cc', 1);
INSERT INTO list_templates_items (id, list_template_id, item_id, item_type, item_mode, sequence) VALUES (13, 6, 19, 'user', 'dest', 0);
INSERT INTO list_templates_items (id, list_template_id, item_id, item_type, item_mode, sequence) VALUES (14, 6, 1, 'entity', 'cc', 1);
INSERT INTO list_templates_items (id, list_template_id, item_id, item_type, item_mode, sequence) VALUES (15, 7, 15, 'user', 'dest', 0);
INSERT INTO list_templates_items (id, list_template_id, item_id, item_type, item_mode, sequence) VALUES (16, 7, 12, 'entity', 'cc', 1);
INSERT INTO list_templates_items (id, list_template_id, item_id, item_type, item_mode, sequence) VALUES (17, 8, 13, 'user', 'dest', 0);
INSERT INTO list_templates_items (id, list_template_id, item_id, item_type, item_mode, sequence) VALUES (18, 8, 12, 'entity', 'cc', 1);
INSERT INTO list_templates_items (id, list_template_id, item_id, item_type, item_mode, sequence) VALUES (19, 9, 4, 'user', 'dest', 0);
INSERT INTO list_templates_items (id, list_template_id, item_id, item_type, item_mode, sequence) VALUES (20, 9, 12, 'entity', 'cc', 1);
INSERT INTO list_templates_items (id, list_template_id, item_id, item_type, item_mode, sequence) VALUES (21, 1009, 17, 'user', 'visa', 0);
INSERT INTO list_templates_items (id, list_template_id, item_id, item_type, item_mode, sequence) VALUES (22, 1009, 10, 'user', 'sign', 1);
INSERT INTO list_templates_items (id, list_template_id, item_id, item_type, item_mode, sequence) VALUES (23, 10, 16, 'user', 'dest', 0);
INSERT INTO list_templates_items (id, list_template_id, item_id, item_type, item_mode, sequence) VALUES (24, 10, 12, 'entity', 'cc', 1);
INSERT INTO list_templates_items (id, list_template_id, item_id, item_type, item_mode, sequence) VALUES (25, 10, 20, 'entity', 'cc', 2);
INSERT INTO list_templates_items (id, list_template_id, item_id, item_type, item_mode, sequence) VALUES (26, 1010, 17, 'user', 'visa', 0);
INSERT INTO list_templates_items (id, list_template_id, item_id, item_type, item_mode, sequence) VALUES (27, 1010, 10, 'user', 'sign', 1);
INSERT INTO list_templates_items (id, list_template_id, item_id, item_type, item_mode, sequence) VALUES (28, 11, 12, 'user', 'dest', 0);
INSERT INTO list_templates_items (id, list_template_id, item_id, item_type, item_mode, sequence) VALUES (29, 11, 12, 'entity', 'cc', 1);
INSERT INTO list_templates_items (id, list_template_id, item_id, item_type, item_mode, sequence) VALUES (30, 12, 18, 'user', 'dest', 0);
INSERT INTO list_templates_items (id, list_template_id, item_id, item_type, item_mode, sequence) VALUES (31, 13, 21, 'user', 'dest', 0);
INSERT INTO list_templates_items (id, list_template_id, item_id, item_type, item_mode, sequence) VALUES (32, 13, 12, 'entity', 'cc', 1);
INSERT INTO list_templates_items (id, list_template_id, item_id, item_type, item_mode, sequence) VALUES (33, 14, 22, 'user', 'dest', 0);
INSERT INTO list_templates_items (id, list_template_id, item_id, item_type, item_mode, sequence) VALUES (34, 14, 14, 'user', 'cc', 1);
INSERT INTO list_templates_items (id, list_template_id, item_id, item_type, item_mode, sequence) VALUES (35, 15, 11, 'user', 'dest', 0);
INSERT INTO list_templates_items (id, list_template_id, item_id, item_type, item_mode, sequence) VALUES (36, 15, 12, 'entity', 'cc', 1);
INSERT INTO list_templates_items (id, list_template_id, item_id, item_type, item_mode, sequence) VALUES (37, 16, 3, 'user', 'dest', 0);
INSERT INTO list_templates_items (id, list_template_id, item_id, item_type, item_mode, sequence) VALUES (38, 16, 12, 'entity', 'cc', 1);
INSERT INTO list_templates_items (id, list_template_id, item_id, item_type, item_mode, sequence) VALUES (39, 16, 2, 'user', 'cc', 2);
INSERT INTO list_templates_items (id, list_template_id, item_id, item_type, item_mode, sequence) VALUES (40, 17, 14, 'user', 'dest', 0);
INSERT INTO list_templates_items (id, list_template_id, item_id, item_type, item_mode, sequence) VALUES (41, 17, 12, 'entity', 'cc', 1);
INSERT INTO list_templates_items (id, list_template_id, item_id, item_type, item_mode, sequence) VALUES (42, 17, 6, 'user', 'cc', 2);
INSERT INTO list_templates_items (id, list_template_id, item_id, item_type, item_mode, sequence) VALUES (43, 18, 20, 'user', 'dest', 0);
INSERT INTO list_templates_items (id, list_template_id, item_id, item_type, item_mode, sequence) VALUES (44, 18, 12, 'entity', 'cc', 1);

INSERT INTO notifications (notification_sid, notification_id, description, is_enabled, event_id, notification_mode, template_id, diffusion_type, diffusion_properties, attachfor_type, attachfor_properties) VALUES (1, 'USERS', '[administration] Actions sur les utilisateurs de l''application', 'Y', 'users%', 'EMAIL', 2, 'user', 'superadmin', '', '');
INSERT INTO notifications (notification_sid, notification_id, description, is_enabled, event_id, notification_mode, template_id, diffusion_type, diffusion_properties, attachfor_type, attachfor_properties) VALUES (2, 'RET2', 'Courriers en retard de traitement', 'Y', 'alert2', 'EMAIL', 5, 'dest_user', '', '', '');
INSERT INTO notifications (notification_sid, notification_id, description, is_enabled, event_id, notification_mode, template_id, diffusion_type, diffusion_properties, attachfor_type, attachfor_properties) VALUES (3, 'RET1', 'Courriers arrivant à échéance', 'Y', 'alert1', 'EMAIL', 6, 'dest_user', '', '', '');
INSERT INTO notifications (notification_sid, notification_id, description, is_enabled, event_id, notification_mode, template_id, diffusion_type, diffusion_properties, attachfor_type, attachfor_properties) VALUES (4, 'BASKETS', 'Notification de bannettes', 'Y', 'baskets', 'EMAIL', 7, 'dest_user', '', '', '');
INSERT INTO notifications (notification_sid, notification_id, description, is_enabled, event_id, notification_mode, template_id, diffusion_type, diffusion_properties, attachfor_type, attachfor_properties) VALUES (5, 'ANC', 'Nouvelle annotation sur courrier en copie', 'Y', 'noteadd', 'EMAIL', 8, 'copy_list', '', '', '');
INSERT INTO notifications (notification_sid, notification_id, description, is_enabled, event_id, notification_mode, template_id, diffusion_type, diffusion_properties, attachfor_type, attachfor_properties) VALUES (6, 'AND', 'Nouvelle annotation sur courrier destinataire', 'Y', 'noteadd', 'EMAIL', 8, 'dest_user', '', '', '');
INSERT INTO notifications (notification_sid, notification_id, description, is_enabled, event_id, notification_mode, template_id, diffusion_type, diffusion_properties, attachfor_type, attachfor_properties) VALUES (7, 'RED', 'Redirection de courrier', 'Y', '1', 'EMAIL', 7, 'dest_user', '', '', '');
INSERT INTO notifications (notification_sid, notification_id, description, is_enabled, event_id, notification_mode, template_id, diffusion_type, diffusion_properties, attachfor_type, attachfor_properties) VALUES (100, 'QUOTA', 'Alerte lorsque le quota est dépassé', 'Y', 'user_quota', 'EMAIL', 110, 'user', 'superadmin', NULL, NULL);

INSERT INTO parameters (id, description, param_value_string, param_value_int, param_value_date) VALUES ('work_batch_autoimport_id', NULL, NULL, 1, NULL);
INSERT INTO parameters (id, description, param_value_string, param_value_int, param_value_date) VALUES ('database_version', NULL, '2301.0.0', NULL, NULL);
INSERT INTO parameters (id, description, param_value_string, param_value_int, param_value_date) VALUES ('user_quota', NULL, '', 0, NULL);
INSERT INTO parameters (id, description, param_value_string, param_value_int, param_value_date) VALUES ('defaultDepartment', 'Département par défaut sélectionné dans les autocomplétions de la Base Adresse Nationale', '75', NULL, NULL);
INSERT INTO parameters (id, description, param_value_string, param_value_int, param_value_date) VALUES ('thumbnailsSize', 'Résolution des imagettes', '750x900', NULL, NULL);
INSERT INTO parameters (id, description, param_value_string, param_value_int, param_value_date) VALUES ('keepDestForRedirection', 'Si activé (1), met le destinataire en copie de la liste de diffusion lors d''une action de redirection', NULL, 0, NULL);
INSERT INTO parameters (id, description, param_value_string, param_value_int, param_value_date) VALUES ('QrCodePrefix', 'Si activé (1), ajoute "Maarch_" dans le contenu des QrCode générés. (Utilisable avec MaarchCapture >= 1.4)', NULL, 0, NULL);
INSERT INTO parameters (id, description, param_value_string, param_value_int, param_value_date) VALUES ('workingDays', 'Si activé (1), les délais de traitement sont calculés en jours ouvrés (Lundi à Vendredi). Sinon, en jours calendaire', NULL, 1, NULL);
INSERT INTO parameters (id, description, param_value_string, param_value_int, param_value_date) VALUES ('last_deposit_id', NULL, NULL, 0, NULL);
INSERT INTO parameters (id, description, param_value_string, param_value_int, param_value_date) VALUES ('registeredMailNotDistributedStatus', NULL, 'PND', NULL, NULL);
INSERT INTO parameters (id, description, param_value_string, param_value_int, param_value_date) VALUES ('registeredMailDistributedStatus', NULL, 'DSTRIBUTED', NULL, NULL);
INSERT INTO parameters (id, description, param_value_string, param_value_int, param_value_date) VALUES ('registeredMailImportedStatus', NULL, 'NEW', NULL, NULL);
INSERT INTO parameters (id, description, param_value_string, param_value_int, param_value_date) VALUES ('keepDiffusionRoleInOutgoingIndexation', 'Si activé (1), prend en compte les roles du modèle de diffusion de l''entité.', NULL, 1, NULL);
INSERT INTO parameters (id, description, param_value_string, param_value_int, param_value_date) VALUES ('bindingDocumentFinalAction', NULL, 'copy', NULL, NULL);
INSERT INTO parameters (id, description, param_value_string, param_value_int, param_value_date) VALUES ('nonBindingDocumentFinalAction', NULL, 'delete', NULL, NULL);
INSERT INTO parameters (id, description, param_value_string, param_value_int, param_value_date) VALUES ('minimumVisaRole', NULL, NULL, 0, NULL);
INSERT INTO parameters (id, description, param_value_string, param_value_int, param_value_date) VALUES ('maximumSignRole', NULL, NULL, 0, NULL);
INSERT INTO parameters (id, description, param_value_string, param_value_int, param_value_date) VALUES ('workflowSignatoryRole', 'Rôle de signataire dans le circuit', 'mandatory', NULL, NULL);
INSERT INTO parameters (id, description, param_value_string, param_value_int, param_value_date) VALUES ('siret', 'Numéro SIRET de l''entreprise', '45239273100025', NULL, NULL);
INSERT INTO parameters (id, description, param_value_string, param_value_int, param_value_date) VALUES ('homepage_message', '', '<p><span style="font-size: 14pt;">Bienvenue sur <strong>Maarch Courrier 2301</strong> </span><br /><span style="font-size: 14pt;">Suivez le <a title="notre guide de visite" href="https://docs.maarch.org/" target="_blank" rel="noopener"><span style="color: #f99830;"><strong>guide de visite en ligne</strong></span></a></span></p>', NULL, NULL);
INSERT INTO parameters (id, description, param_value_string, param_value_int, param_value_date) VALUES ('loginpage_message', '', '<p><span style="font-size: 14pt; color: #ecf0f1;"><span style="color: #000000;"><strong>Acc&eacute;der au</strong> </span><a style="color: ##3598db;" title="le guide de visite" href="https://docs.maarch.org/gitbook/html/MaarchCourrier/2301/guu/home.html" target="_blank" rel="noopener"><strong>guide de visite en ligne</strong></a></span></p>', NULL, NULL);
INSERT INTO parameters (id, description, param_value_string, param_value_int, param_value_date) VALUES ('traffic_record_summary_sheet', '', '', NULL, NULL);
INSERT INTO parameters (id, description, param_value_string, param_value_int, param_value_date) VALUES ('chrono_outgoing_2021', '', NULL, 3, NULL);
INSERT INTO parameters (id, description, param_value_string, param_value_int, param_value_date) VALUES ('chrono_incoming_2021', '', NULL, 4, NULL);
INSERT INTO parameters (id, description, param_value_string, param_value_int, param_value_date) VALUES ('suggest_links_n_days_ago', 'Le nombre de jours sur lequel sont cherchés les courriers à lier', NULL, 0, NULL);
INSERT INTO parameters (id, description, param_value_string, param_value_int, param_value_date) VALUES ('useSectorsForAddresses', 'Utilisation de la table address_sectors pour autocomplétion des adresses ; la BAN est ignorée (valeur = 1)', NULL, 0, NULL);

INSERT INTO password_rules (id, label, value, enabled) VALUES (1, 'minLength', 6, true);
INSERT INTO password_rules (id, label, value, enabled) VALUES (2, 'complexityUpper', 0, false);
INSERT INTO password_rules (id, label, value, enabled) VALUES (3, 'complexityNumber', 0, false);
INSERT INTO password_rules (id, label, value, enabled) VALUES (4, 'complexitySpecial', 0, false);
INSERT INTO password_rules (id, label, value, enabled) VALUES (5, 'lockAttempts', 3, false);
INSERT INTO password_rules (id, label, value, enabled) VALUES (6, 'lockTime', 5, false);
INSERT INTO password_rules (id, label, value, enabled) VALUES (7, 'historyLastUse', 2, false);
INSERT INTO password_rules (id, label, value, enabled) VALUES (8, 'renewal', 90, false);

INSERT INTO priorities (id, label, color, delays, "order") VALUES ('poiuytre1357nbvc', 'Normal', '#009dc5', 30, 1);
INSERT INTO priorities (id, label, color, delays, "order") VALUES ('poiuytre1379nbvc', 'Urgent', '#ffa500', 8, 2);
INSERT INTO priorities (id, label, color, delays, "order") VALUES ('poiuytre1391nbvc', 'Très urgent', '#ff0000', 4, 3);

INSERT INTO registered_mail_issuing_sites (id, label, post_office_label, account_number, address_number, address_street, address_additional1, address_additional2, address_postcode, address_town, address_country) VALUES (1, 'MAARCH - Nanterre', 'La poste Nanterre', 1234567, '10', 'AVENUE DE LA GRANDE ARMEE', '', '', '75017', 'PARIS', 'FRANCE');

INSERT INTO registered_mail_issuing_sites_entities (id, site_id, entity_id) VALUES (1, 1, 6);
INSERT INTO registered_mail_issuing_sites_entities (id, site_id, entity_id) VALUES (2, 1, 13);

INSERT INTO registered_mail_number_range (id, type, tracking_account_number, range_start, range_end, creator, creation_date, status, current_number) VALUES (1, '2C', 'SuiviNumber', 1, 10, 23, '2020-09-14 14:38:09.008644', 'OK', 1);
INSERT INTO registered_mail_number_range (id, type, tracking_account_number, range_start, range_end, creator, creation_date, status, current_number) VALUES (2, 'RW', 'SuiviNumberInternational', 1, 10, 23, '2020-09-14 14:39:32.972626', 'OK', 1);
INSERT INTO registered_mail_number_range (id, type, tracking_account_number, range_start, range_end, creator, creation_date, status, current_number) VALUES (3, '2D', 'suiviNumber', 1, 10, 23, '2020-09-14 14:39:16.779322', 'OK', 1);

INSERT INTO difflist_roles (id, role_id, label, keep_in_list_instance) VALUES (1, 'dest', 'Destinataire', false);
INSERT INTO difflist_roles (id, role_id, label, keep_in_list_instance) VALUES (2, 'copy', 'En copie', true);
INSERT INTO difflist_roles (id, role_id, label, keep_in_list_instance) VALUES (3, 'visa', 'Pour visa', false);
INSERT INTO difflist_roles (id, role_id, label, keep_in_list_instance) VALUES (4, 'sign', 'Pour signature', false);
INSERT INTO difflist_roles (id, role_id, label, keep_in_list_instance) VALUES (5, 'avis', 'Pour avis', false);
INSERT INTO difflist_roles (id, role_id, label, keep_in_list_instance) VALUES (6, 'avis_copy', 'En copie (avis)', false);
INSERT INTO difflist_roles (id, role_id, label, keep_in_list_instance) VALUES (7, 'avis_info', 'Pour information (avis)', false);

INSERT INTO search_templates (id, user_id, label, creation_date, query) VALUES (1, 23, 'Tous les courriers', '2021-03-25 11:54:30.273871', '[{"identifier":"category","values":""},{"identifier":"meta"}]');
INSERT INTO search_templates (id, user_id, label, creation_date, query) VALUES (2, 18, 'Courriers arrivés', '2021-03-25 11:59:29.500487', '[{"identifier":"category","values":[{"id":"incoming","label":"Courrier Arriv\u00e9e"}]},{"identifier":"meta"}]');

INSERT INTO security (security_id, group_id, coll_id, where_clause, maarch_comment) VALUES (600, 'COURRIER', 'letterbox_coll', '1=1', 'Tous les courriers');
INSERT INTO security (security_id, group_id, coll_id, where_clause, maarch_comment) VALUES (601, 'AGENT', 'letterbox_coll', 'destination in (@my_entities, @subentities[@my_primary_entity])', 'Les courriers de mes services et sous-services');
INSERT INTO security (security_id, group_id, coll_id, where_clause, maarch_comment) VALUES (602, 'RESP_COURRIER', 'letterbox_coll', '1=1', 'Tous les courriers');
INSERT INTO security (security_id, group_id, coll_id, where_clause, maarch_comment) VALUES (603, 'RESPONSABLE', 'letterbox_coll', 'destination in (@my_entities, @subentities[@my_primary_entity])', 'Les courriers de mes services et sous-services');
INSERT INTO security (security_id, group_id, coll_id, where_clause, maarch_comment) VALUES (604, 'ADMINISTRATEUR_N1', 'letterbox_coll', '1=1', 'Tous les courriers');
INSERT INTO security (security_id, group_id, coll_id, where_clause, maarch_comment) VALUES (605, 'ADMINISTRATEUR_N2', 'letterbox_coll', '1=0', 'Aucun courrier');
INSERT INTO security (security_id, group_id, coll_id, where_clause, maarch_comment) VALUES (606, 'DIRECTEUR', 'letterbox_coll', '1=0', 'Aucun courrier');
INSERT INTO security (security_id, group_id, coll_id, where_clause, maarch_comment) VALUES (607, 'ELU', 'letterbox_coll', '1=0', 'Aucun courrier');
INSERT INTO security (security_id, group_id, coll_id, where_clause, maarch_comment) VALUES (608, 'CABINET', 'letterbox_coll', '1=0', 'Aucun courrier');
INSERT INTO security (security_id, group_id, coll_id, where_clause, maarch_comment) VALUES (609, 'ARCHIVISTE', 'letterbox_coll', '1=1', 'Tous les courriers');
INSERT INTO security (security_id, group_id, coll_id, where_clause, maarch_comment) VALUES (610, 'MAARCHTOGEC', 'letterbox_coll', '1=0', 'Aucun courrier');
INSERT INTO security (security_id, group_id, coll_id, where_clause, maarch_comment) VALUES (611, 'SERVICE', 'letterbox_coll', '1=0', 'Aucun courrier');
INSERT INTO security (security_id, group_id, coll_id, where_clause, maarch_comment) VALUES (612, 'WEBSERVICE', 'letterbox_coll', '1=0', 'Tous les courriers');

INSERT INTO shipping_templates (id, label, description, options, fee, entities, account) VALUES (1, 'Modèle d''exemple d''envoi postal', 'Modèle d''exemple d''envoi postal', '{"shapingOptions":[],"sendMode":"fast"}', '{"firstPagePrice":0.4,"nextPagePrice":0.5,"postagePrice":0.9}', '["1", "2", "3", "4", "5", "6", "7", "8", "9", "10", "11", "12", "13", "14", "15", "17", "18", "16", "19", "20"]', '{"id":"sandbox.562","password":"VPh5AY6i::82f88fe97cead428e0885084f93a684c"}');

INSERT INTO status (identifier, id, label_status, is_system, img_filename, maarch_module, can_be_searched, can_be_modified) VALUES (1, 'ATT', 'En attente', 'Y', 'fm-letter-status-attr', 'apps', 'Y', 'Y');
INSERT INTO status (identifier, id, label_status, is_system, img_filename, maarch_module, can_be_searched, can_be_modified) VALUES (2, 'COU', 'En cours', 'Y', 'fm-letter-status-inprogress', 'apps', 'Y', 'Y');
INSERT INTO status (identifier, id, label_status, is_system, img_filename, maarch_module, can_be_searched, can_be_modified) VALUES (3, 'DEL', 'Supprimé', 'Y', 'fm-letter-del', 'apps', 'N', 'Y');
INSERT INTO status (identifier, id, label_status, is_system, img_filename, maarch_module, can_be_searched, can_be_modified) VALUES (4, 'END', 'Clos / fin du workflow', 'Y', 'fm-letter-status-end', 'apps', 'Y', 'Y');
INSERT INTO status (identifier, id, label_status, is_system, img_filename, maarch_module, can_be_searched, can_be_modified) VALUES (5, 'NEW', 'Nouveau courrier pour le service', 'Y', 'fm-letter-status-new', 'apps', 'Y', 'Y');
INSERT INTO status (identifier, id, label_status, is_system, img_filename, maarch_module, can_be_searched, can_be_modified) VALUES (6, 'RET', 'Retour courrier ou document en qualification', 'N', 'fm-letter-status-rejected', 'apps', 'Y', 'Y');
INSERT INTO status (identifier, id, label_status, is_system, img_filename, maarch_module, can_be_searched, can_be_modified) VALUES (7, 'VAL', 'Courrier signalé', 'Y', 'fm-letter-status-aval', 'apps', 'Y', 'Y');
INSERT INTO status (identifier, id, label_status, is_system, img_filename, maarch_module, can_be_searched, can_be_modified) VALUES (8, 'INIT', 'Nouveau courrier ou document non qualifié', 'Y', 'fm-letter-status-attr', 'apps', 'Y', 'Y');
INSERT INTO status (identifier, id, label_status, is_system, img_filename, maarch_module, can_be_searched, can_be_modified) VALUES (9, 'VALSG', 'Nouveau courrier ou document en validation SG', 'Y', 'fm-letter-status-attr', 'apps', 'Y', 'Y');
INSERT INTO status (identifier, id, label_status, is_system, img_filename, maarch_module, can_be_searched, can_be_modified) VALUES (10, 'ATT_MP', 'En attente tablette (MP)', 'Y', 'fm-letter-status-wait', 'apps', 'Y', 'Y');
INSERT INTO status (identifier, id, label_status, is_system, img_filename, maarch_module, can_be_searched, can_be_modified) VALUES (11, 'EAVIS', 'Avis demandé', 'N', 'fa-lightbulb', 'apps', 'Y', 'Y');
INSERT INTO status (identifier, id, label_status, is_system, img_filename, maarch_module, can_be_searched, can_be_modified) VALUES (12, 'EENV', 'A e-envoyer', 'N', 'fm-letter-status-aenv', 'apps', 'Y', 'Y');
INSERT INTO status (identifier, id, label_status, is_system, img_filename, maarch_module, can_be_searched, can_be_modified) VALUES (13, 'ESIG', 'A e-signer', 'N', 'fm-file-fingerprint', 'apps', 'Y', 'Y');
INSERT INTO status (identifier, id, label_status, is_system, img_filename, maarch_module, can_be_searched, can_be_modified) VALUES (14, 'EVIS', 'A e-viser', 'N', 'fm-letter-status-aval', 'apps', 'Y', 'Y');
INSERT INTO status (identifier, id, label_status, is_system, img_filename, maarch_module, can_be_searched, can_be_modified) VALUES (15, 'ESIGAR', 'AR à e-signer', 'N', 'fm-file-fingerprint', 'apps', 'Y', 'Y');
INSERT INTO status (identifier, id, label_status, is_system, img_filename, maarch_module, can_be_searched, can_be_modified) VALUES (16, 'EENVAR', 'AR à e-envoyer', 'N', 'fm-letter-status-aenv', 'apps', 'Y', 'Y');
INSERT INTO status (identifier, id, label_status, is_system, img_filename, maarch_module, can_be_searched, can_be_modified) VALUES (17, 'SVX', 'En attente  de traitement SVE', 'N', 'fm-letter-status-wait', 'apps', 'Y', 'Y');
INSERT INTO status (identifier, id, label_status, is_system, img_filename, maarch_module, can_be_searched, can_be_modified) VALUES (18, 'SSUITE', 'Sans suite', 'Y', 'fm-letter-del', 'apps', 'Y', 'Y');
INSERT INTO status (identifier, id, label_status, is_system, img_filename, maarch_module, can_be_searched, can_be_modified) VALUES (19, 'A_TRA', 'PJ à traiter', 'Y', 'fa-question', 'apps', 'Y', 'Y');
INSERT INTO status (identifier, id, label_status, is_system, img_filename, maarch_module, can_be_searched, can_be_modified) VALUES (20, 'FRZ', 'PJ gelée', 'Y', 'fa-pause', 'apps', 'Y', 'Y');
INSERT INTO status (identifier, id, label_status, is_system, img_filename, maarch_module, can_be_searched, can_be_modified) VALUES (21, 'TRA', 'PJ traitée', 'Y', 'fa-check', 'apps', 'Y', 'Y');
INSERT INTO status (identifier, id, label_status, is_system, img_filename, maarch_module, can_be_searched, can_be_modified) VALUES (22, 'OBS', 'PJ obsolète', 'Y', 'fa-pause', 'apps', 'Y', 'Y');
INSERT INTO status (identifier, id, label_status, is_system, img_filename, maarch_module, can_be_searched, can_be_modified) VALUES (23, 'TMP', 'PJ brouillon', 'Y', 'fm-letter-status-inprogress', 'apps', 'N', 'N');
INSERT INTO status (identifier, id, label_status, is_system, img_filename, maarch_module, can_be_searched, can_be_modified) VALUES (24, 'EXP_SEDA', 'A archiver', 'Y', 'fm-letter-status-acla', 'apps', 'Y', 'Y');
INSERT INTO status (identifier, id, label_status, is_system, img_filename, maarch_module, can_be_searched, can_be_modified) VALUES (25, 'SEND_SEDA', 'Courrier envoyé au système d''archivage', 'Y', 'fm-letter-status-inprogress', 'apps', 'Y', 'Y');
INSERT INTO status (identifier, id, label_status, is_system, img_filename, maarch_module, can_be_searched, can_be_modified) VALUES (26, 'ACK_SEDA', 'Accusé de réception reçu', 'Y', 'fm-letter-status-acla', 'apps', 'Y', 'Y');
INSERT INTO status (identifier, id, label_status, is_system, img_filename, maarch_module, can_be_searched, can_be_modified) VALUES (27, 'REPLY_SEDA', 'Courrier archivé', 'Y', 'fm-letter-status-acla', 'apps', 'Y', 'Y');
INSERT INTO status (identifier, id, label_status, is_system, img_filename, maarch_module, can_be_searched, can_be_modified) VALUES (28, 'GRC', 'Envoyé en GRC', 'N', 'fm-letter-status-inprogress', 'apps', 'Y', 'Y');
INSERT INTO status (identifier, id, label_status, is_system, img_filename, maarch_module, can_be_searched, can_be_modified) VALUES (29, 'GRC_TRT', 'En traitement GRC', 'N', 'fm-letter-status-inprogress', 'apps', 'Y', 'Y');
INSERT INTO status (identifier, id, label_status, is_system, img_filename, maarch_module, can_be_searched, can_be_modified) VALUES (30, 'GRC_ALERT', 'Retourné par la GRC', 'N', 'fm-letter-status-inprogress', 'apps', 'Y', 'Y');
INSERT INTO status (identifier, id, label_status, is_system, img_filename, maarch_module, can_be_searched, can_be_modified) VALUES (31, 'RETRN', 'Retourné', 'Y', 'fm-letter-outgoing', 'apps', 'N', 'N');
INSERT INTO status (identifier, id, label_status, is_system, img_filename, maarch_module, can_be_searched, can_be_modified) VALUES (32, 'NO_RETRN', 'Pas de retour', 'Y', 'fm-letter-status-rejected', 'apps', 'N', 'N');
INSERT INTO status (identifier, id, label_status, is_system, img_filename, maarch_module, can_be_searched, can_be_modified) VALUES (33, 'PJQUAL', 'PJ à réconcilier', 'Y', 'fm-letter-status-attr', 'apps', 'Y', 'Y');
INSERT INTO status (identifier, id, label_status, is_system, img_filename, maarch_module, can_be_searched, can_be_modified) VALUES (34, 'NUMQUAL', 'Plis à qualifier', 'Y', 'fm-letter-status-attr', 'apps', 'Y', 'Y');
INSERT INTO status (identifier, id, label_status, is_system, img_filename, maarch_module, can_be_searched, can_be_modified) VALUES (35, 'SEND_MASS', 'Pour publipostage', 'Y', 'fa-mail-bulk', 'apps', 'Y', 'Y');
INSERT INTO status (identifier, id, label_status, is_system, img_filename, maarch_module, can_be_searched, can_be_modified) VALUES (36, 'SIGN', 'PJ signée', 'Y', 'fa-check', 'apps', 'Y', 'Y');
INSERT INTO status (identifier, id, label_status, is_system, img_filename, maarch_module, can_be_searched, can_be_modified) VALUES (37, 'STDBY', 'Clôturé avec suivi', 'Y', 'fm-letter-status-wait', 'apps', 'Y', 'Y');
INSERT INTO status (identifier, id, label_status, is_system, img_filename, maarch_module, can_be_searched, can_be_modified) VALUES (38, 'ENVDONE', 'Courrier envoyé', 'Y', 'fm-letter-status-aenv', 'apps', 'Y', 'Y');
INSERT INTO status (identifier, id, label_status, is_system, img_filename, maarch_module, can_be_searched, can_be_modified) VALUES (39, 'REJ_SIGN', 'Signature refusée sur la tablette (MP)', 'Y', 'fm-letter-status-rejected', 'apps', 'Y', 'Y');
INSERT INTO status (identifier, id, label_status, is_system, img_filename, maarch_module, can_be_searched, can_be_modified) VALUES (40, 'PND', 'AR Non distribué', 'Y', 'fm-letter-status-rejected', 'apps', 'Y', 'Y');
INSERT INTO status (identifier, id, label_status, is_system, img_filename, maarch_module, can_be_searched, can_be_modified) VALUES (41, 'DSTRIBUTED', 'AR distribué', 'Y', 'fa-check', 'apps', 'Y', 'Y');
INSERT INTO status (identifier, id, label_status, is_system, img_filename, maarch_module, can_be_searched, can_be_modified) VALUES (42, 'OUT', 'Courriels importés à qualifier', 'N', 'fm-letter-incoming', 'apps', 'Y', 'Y');

INSERT INTO status_images (id, image_name) VALUES (1, 'fm-letter-status-new');
INSERT INTO status_images (id, image_name) VALUES (2, 'fm-letter-status-inprogress');
INSERT INTO status_images (id, image_name) VALUES (3, 'fm-letter-status-info');
INSERT INTO status_images (id, image_name) VALUES (4, 'fm-letter-status-wait');
INSERT INTO status_images (id, image_name) VALUES (5, 'fm-letter-status-validated');
INSERT INTO status_images (id, image_name) VALUES (6, 'fm-letter-status-rejected');
INSERT INTO status_images (id, image_name) VALUES (7, 'fm-letter-status-end');
INSERT INTO status_images (id, image_name) VALUES (8, 'fm-letter-status-newmail');
INSERT INTO status_images (id, image_name) VALUES (9, 'fm-letter-status-attr');
INSERT INTO status_images (id, image_name) VALUES (10, 'fm-letter-status-arev');
INSERT INTO status_images (id, image_name) VALUES (11, 'fm-letter-status-aval');
INSERT INTO status_images (id, image_name) VALUES (12, 'fm-letter-status-aimp');
INSERT INTO status_images (id, image_name) VALUES (13, 'fm-letter-status-imp');
INSERT INTO status_images (id, image_name) VALUES (14, 'fm-letter-status-aenv');
INSERT INTO status_images (id, image_name) VALUES (15, 'fm-letter-status-acla');
INSERT INTO status_images (id, image_name) VALUES (16, 'fm-letter-status-aarch');
INSERT INTO status_images (id, image_name) VALUES (17, 'fm-letter');
INSERT INTO status_images (id, image_name) VALUES (18, 'fm-letter-add');
INSERT INTO status_images (id, image_name) VALUES (19, 'fm-letter-search');
INSERT INTO status_images (id, image_name) VALUES (20, 'fm-letter-del');
INSERT INTO status_images (id, image_name) VALUES (21, 'fm-letter-incoming');
INSERT INTO status_images (id, image_name) VALUES (22, 'fm-letter-outgoing');
INSERT INTO status_images (id, image_name) VALUES (23, 'fm-letter-internal');
INSERT INTO status_images (id, image_name) VALUES (24, 'fm-file-fingerprint');
INSERT INTO status_images (id, image_name) VALUES (25, 'fm-classification-plan-l1');
INSERT INTO status_images (id, image_name) VALUES (26, 'fa-question');
INSERT INTO status_images (id, image_name) VALUES (27, 'fa-check');
INSERT INTO status_images (id, image_name) VALUES (28, 'fa-pause');
INSERT INTO status_images (id, image_name) VALUES (29, 'fa-mail-bulk');
INSERT INTO status_images (id, image_name) VALUES (30, 'fa-lightbulb');

INSERT INTO tags (id, label, description, parent_id, creation_date, links, usage) VALUES (1, 'SEMINAIRE', NULL, NULL, '2021-03-24 10:17:02.66594', '[]', NULL);
INSERT INTO tags (id, label, description, parent_id, creation_date, links, usage) VALUES (2, 'INNOVATION', NULL, NULL, '2021-03-24 10:17:02.66594', '[]', NULL);
INSERT INTO tags (id, label, description, parent_id, creation_date, links, usage) VALUES (3, 'MAARCH', NULL, NULL, '2021-03-24 10:17:02.66594', '[]', NULL);
INSERT INTO tags (id, label, description, parent_id, creation_date, links, usage) VALUES (4, 'ENVIRONNEMENT', NULL, NULL, '2021-03-24 10:17:02.66594', '[]', NULL);
INSERT INTO tags (id, label, description, parent_id, creation_date, links, usage) VALUES (5, 'PARTENARIAT', NULL, NULL, '2021-03-24 10:17:02.66594', '[]', NULL);
INSERT INTO tags (id, label, description, parent_id, creation_date, links, usage) VALUES (6, 'JUMELAGE', NULL, NULL, '2021-03-24 10:17:02.66594', '[]', NULL);
INSERT INTO tags (id, label, description, parent_id, creation_date, links, usage) VALUES (7, 'ECONOMIE', NULL, NULL, '2021-03-24 10:17:02.66594', '[]', NULL);
INSERT INTO tags (id, label, description, parent_id, creation_date, links, usage) VALUES (8, 'ASSOCIATIONS', NULL, NULL, '2021-03-24 10:17:02.66594', '[]', NULL);
INSERT INTO tags (id, label, description, parent_id, creation_date, links, usage) VALUES (9, 'RH', NULL, NULL, '2021-03-24 10:17:02.66594', '[]', NULL);
INSERT INTO tags (id, label, description, parent_id, creation_date, links, usage) VALUES (10, 'BUDGET', NULL, NULL, '2021-03-24 10:17:02.66594', '[]', NULL);
INSERT INTO tags (id, label, description, parent_id, creation_date, links, usage) VALUES (11, 'QUARTIERS', NULL, NULL, '2021-03-24 10:17:02.66594', '[]', NULL);
INSERT INTO tags (id, label, description, parent_id, creation_date, links, usage) VALUES (12, 'LITTORAL', NULL, NULL, '2021-03-24 10:17:02.66594', '[]', NULL);
INSERT INTO tags (id, label, description, parent_id, creation_date, links, usage) VALUES (13, 'SPORT', NULL, NULL, '2021-03-24 10:17:02.66594', '[]', NULL);

INSERT INTO templates (template_id, template_label, template_comment, template_content, template_type, template_path, template_file_name, template_style, template_datasource, template_target, template_attachment_type, subject) VALUES (2, '[notification] Notifications événement', 'Notifications des événements système', '<p><font face="verdana,geneva" size="1">Bonjour [recipient.firstname] [recipient.lastname],</font></p>
<p><font face="verdana,geneva" size="1"> </font></p>
<p><font face="verdana,geneva" size="1">Voici la liste des &eacute;v&eacute;nements de l''application qui vous sont notifi&eacute;s ([notification.description]) :</font></p>
<table style="width: 800px; height: 36px;" border="0" cellspacing="1" cellpadding="1">
<tbody>
<tr>
<td style="width: 150px; background-color: #0099ff;"><font face="verdana,geneva" size="1"><strong><font color="#FFFFFF">Date</font></strong></font></td>
<td style="width: 150px; background-color: #0099ff;"><font face="verdana,geneva" size="1"><strong><font color="#FFFFFF">Utilisateur </font></strong></font><font face="verdana,geneva" size="1"><strong></strong></font></td>
<td style="width: 500px; background-color: #0099ff;"><font face="verdana,geneva" size="1"><strong><font color="#FFFFFF">Description</font></strong></font></td>
</tr>
<tr>
<td><font face="verdana,geneva" size="1">[events.event_date;block=tr;frm=dd/mm/yyyy hh:nn:ss]</font></td>
<td><font face="verdana,geneva" size="1">[events.user_id]</font></td>
<td><font face="verdana,geneva" size="1">[events.event_info]</font></td>
</tr>
</tbody>
</table>', 'HTML', NULL, NULL, '', 'notif_events', 'notifications', NULL, NULL);
INSERT INTO templates (template_id, template_label, template_comment, template_content, template_type, template_path, template_file_name, template_style, template_datasource, template_target, template_attachment_type, subject) VALUES (5, '[notification courrier] Alerte 2', '[notification] Alerte 2', '<p><font face="arial,helvetica,sans-serif" size="2">Bonjour [recipient.firstname] [recipient.lastname],</font></p>
<p> </p>
<p><font face="arial,helvetica,sans-serif" size="2">Voici la liste des courriers dont la date limite de traitement est dépassée :n</font></p>
<table style="border: 1pt solid #000000; width: 1582px; height: 77px;" border="1" cellspacing="1" cellpadding="5" frame="box">
<tbody>
<tr>
<td><font face="arial,helvetica,sans-serif"><strong><font size="2">Référence</font></strong></font></td>
<td><font face="arial,helvetica,sans-serif"><strong><font size="2">Origine</font></strong></font></td>
<td><font face="arial,helvetica,sans-serif"><strong><font size="2">Emetteur</font></strong></font></td>
<td><font face="arial,helvetica,sans-serif" size="2" color="#000000"><strong>Date</strong></font></td>
<td><font face="arial,helvetica,sans-serif" size="2" color="#000000"><strong>Objet</strong></font></td>
<td><font face="arial,helvetica,sans-serif" size="2" color="#000000"><strong>Type</strong></font></td>
<td><font face="arial,helvetica,sans-serif" size="2" color="#FFFFFF"><strong>Liens</strong></font></td>
</tr>
<tr>
<td><font face="arial,helvetica,sans-serif" size="2">[res_letterbox.res_id]</font></td>
<td><font face="arial,helvetica,sans-serif" size="2">[res_letterbox.typist_label]</font></td>
<td>
<p><font face="arial,helvetica,sans-serif" size="2">[sender.company;block=tr] [sender.firstname] [sender.lastname] [sender.function] [sender.address_number] [sender.address_street] [sender.address_postcode] [sender.address_town]</font></p>
</td>
<td><font face="arial,helvetica,sans-serif" size="2">[res_letterbox.doc_date;block=tr;frm=dd/mm/yyyy]</font></td>
<td><font face="arial,helvetica,sans-serif" color="#FF0000"><strong><font size="2">[res_letterbox.subject]</font></strong></font></td>
<td><font face="arial,helvetica,sans-serif" size="2">[res_letterbox.type_label]</font></td>
<td><font face="arial,helvetica,sans-serif"><a href="[res_letterbox.linktoprocess]" name="traiter">traiter</a> <a href="[res_letterbox.linktodoc]" name="doc">Afficher</a></font></td>
</tr>
</tbody>
</table>', 'HTML', NULL, NULL, 'ODP: open_office_presentation', 'letterbox_events', 'notifications', NULL, NULL);
INSERT INTO templates (template_id, template_label, template_comment, template_content, template_type, template_path, template_file_name, template_style, template_datasource, template_target, template_attachment_type, subject) VALUES (6, '[notification courrier] Alerte 1', '[notification] Alerte 1', '<p><font face="arial,helvetica,sans-serif" size="2">Bonjour [recipient.firstname] [recipient.lastname],</font></p>
<p> </p>
<p><font face="arial,helvetica,sans-serif" size="2"> </font></p>
<p> </p>
<p><font face="arial,helvetica,sans-serif" size="2">Voici la liste des courriers toujours en attente de traitement :</font></p>
<p> </p>
<table style="border: 1pt solid #000000; width: 1582px; height: 77px;" border="1" cellspacing="1" cellpadding="5" frame="box">
<tbody>
<tr>
<td><font face="arial,helvetica,sans-serif"><strong><font size="2">Référence</font></strong></font></td>
<td><font face="arial,helvetica,sans-serif"><strong><font size="2">Origine</font></strong></font></td>
<td><font face="arial,helvetica,sans-serif"><strong><font size="2">Emetteur</font></strong></font></td>
<td><font face="arial,helvetica,sans-serif" size="2" color="#000000"><strong>Date</strong></font></td>
<td><font face="arial,helvetica,sans-serif" size="2" color="#000000"><strong>Objet</strong></font></td>
<td><font face="arial,helvetica,sans-serif" size="2" color="#000000"><strong>Type</strong></font></td>
<td><font face="arial,helvetica,sans-serif" size="2" color="#FFFFFF"><strong>Liens</strong></font></td>
</tr>
<tr>
<td><font face="arial,helvetica,sans-serif" size="2">[res_letterbox.res_id]</font></td>
<td><font face="arial,helvetica,sans-serif" size="2">[res_letterbox.typist_label]</font></td>
<td>
<p><font face="arial,helvetica,sans-serif" size="2">[sender.company;block=tr] [sender.firstname] [sender.lastname] [sender.function] [sender.address_number] [sender.address_street] [sender.address_postcode] [sender.address_town]</font></p>
</td>
<td><font face="arial,helvetica,sans-serif" size="2">[res_letterbox.doc_date;block=tr;frm=dd/mm/yyyy]</font></td>
<td><font face="arial,helvetica,sans-serif" color="#FF0000"><strong><font size="2">[res_letterbox.subject]</font></strong></font></td>
<td><font face="arial,helvetica,sans-serif" size="2">[res_letterbox.type_label]</font></td>
<td><font face="arial,helvetica,sans-serif"><a href="[res_letterbox.linktoprocess]" name="traiter">traiter</a> <a href="[res_letterbox.linktodoc]" name="doc">Afficher</a></font></td>
</tr>
</tbody>
</table>', 'HTML', NULL, NULL, 'ODP: open_office_presentation', 'letterbox_events', 'notifications', NULL, NULL);
INSERT INTO templates (template_id, template_label, template_comment, template_content, template_type, template_path, template_file_name, template_style, template_datasource, template_target, template_attachment_type, subject) VALUES (7, '[notification courrier] Diffusion de courrier', 'Alerte de courriers présents dans les bannettes', '<p style="font-family: Trebuchet MS, Arial, Helvetica, sans-serif;">Bonjour <strong>[recipient.firstname] [recipient.lastname]</strong>,</p>
<p>&nbsp;</p>
<p style="font-family: Trebuchet MS, Arial, Helvetica, sans-serif;">Voici la liste des nouveaux courriers pr&eacute;sents dans cette bannette :</p>
<table style="font-family: Trebuchet MS, Arial, Helvetica, sans-serif; border-collapse: collapse; width: 100%;">
<tbody>
<tr>
<th style="border: 1px solid #ddd; padding: 8px; padding-top: 12px; padding-bottom: 12px; text-align: left; background-color: #135f7f; color: white;">R&eacute;f&eacute;rence</th>
<th style="border: 1px solid #ddd; padding: 8px; padding-top: 12px; padding-bottom: 12px; text-align: left; background-color: #135f7f; color: white;">Origine</th>
<th style="border: 1px solid #ddd; padding: 8px; padding-top: 12px; padding-bottom: 12px; text-align: left; background-color: #135f7f; color: white;">Emetteur</th>
<th style="border: 1px solid #ddd; padding: 8px; padding-top: 12px; padding-bottom: 12px; text-align: left; background-color: #135f7f; color: white;">Date</th>
<th style="border: 1px solid #ddd; padding: 8px; padding-top: 12px; padding-bottom: 12px; text-align: left; background-color: #135f7f; color: white;">Objet</th>
<th style="border: 1px solid #ddd; padding: 8px; padding-top: 12px; padding-bottom: 12px; text-align: left; background-color: #135f7f; color: white;">Type</th>
<th style="border: 1px solid #ddd; padding: 8px; padding-top: 12px; padding-bottom: 12px; text-align: left; background-color: #135f7f; color: white;">&nbsp;</th>
</tr>
<tr>
<td style="border: 1px solid #ddd; padding: 8px;">[res_letterbox.res_id]</td>
<td style="border: 1px solid #ddd; padding: 8px;">[res_letterbox.typist_label]</td>
<td style="border: 1px solid #ddd; padding: 8px;">[sender.company;block=tr] [sender.firstname] [sender.lastname][sender.function][sender.address_number][sender.address_street][sender.address_postcode][sender.address_town]</td>
<td style="border: 1px solid #ddd; padding: 8px;">[res_letterbox.doc_date;block=tr;frm=dd/mm/yyyy]</td>
<td style="border: 1px solid #ddd; padding: 8px;">[res_letterbox.subject]</td>
<td style="border: 1px solid #ddd; padding: 8px;">[res_letterbox.type_label]</td>
<td style="border: 1px solid #ddd; padding: 8px; text-align: right;"><a style="text-decoration: none; background: #135f7f; padding: 5px; color: white; -webkit-box-shadow: 6px 4px 5px 0px rgba(0,0,0,0.75); -moz-box-shadow: 6px 4px 5px 0px rgba(0,0,0,0.75); box-shadow: 6px 4px 5px 0px rgba(0,0,0,0.75);" href="[res_letterbox.linktodetail]" name="detail">D&eacute;tail</a> <a style="text-decoration: none; background: #135f7f; padding: 5px; color: white; -webkit-box-shadow: 6px 4px 5px 0px rgba(0,0,0,0.75); -moz-box-shadow: 6px 4px 5px 0px rgba(0,0,0,0.75); box-shadow: 6px 4px 5px 0px rgba(0,0,0,0.75);" href="[res_letterbox.linktodoc]" name="doc">Afficher</a></td>
</tr>
</tbody>
</table>
<p>&nbsp;</p>
<p style="font-family: Trebuchet MS, Arial, Helvetica, sans-serif; width: 100%; text-align: center; font-size: 9px; font-style: italic; opacity: 0.5;">Message g&eacute;n&eacute;r&eacute; via l''application MaarchCourrier</p>', 'HTML', NULL, NULL, 'ODP: open_office_presentation', 'letterbox_events', 'notifications', NULL, NULL);
INSERT INTO templates (template_id, template_label, template_comment, template_content, template_type, template_path, template_file_name, template_style, template_datasource, template_target, template_attachment_type, subject) VALUES (8, '[notification courrier] Nouvelle annotation', '[notification] Nouvelle annotation', '<p style="font-family: Trebuchet MS, Arial, Helvetica, sans-serif;">Bonjour <strong>[recipient.firstname] [recipient.lastname]</strong>,</p>
<p>&nbsp;</p>
<p style="font-family: Trebuchet MS, Arial, Helvetica, sans-serif;">Voici les nouvelles annotations sur les courriers suivants :</p>
<table style="font-family: Trebuchet MS, Arial, Helvetica, sans-serif; border-collapse: collapse; width: 100%;">
<tbody>
<tr>
<th style="border: 1px solid #ddd; padding: 8px; padding-top: 12px; padding-bottom: 12px; text-align: left; background-color: #135f7f; color: white;">R&eacute;f&eacute;rence</th>
<th style="border: 1px solid #ddd; padding: 8px; padding-top: 12px; padding-bottom: 12px; text-align: left; background-color: #135f7f; color: white;">Num</th>
<th style="border: 1px solid #ddd; padding: 8px; padding-top: 12px; padding-bottom: 12px; text-align: left; background-color: #135f7f; color: white;">Date</th>
<th style="border: 1px solid #ddd; padding: 8px; padding-top: 12px; padding-bottom: 12px; text-align: left; background-color: #135f7f; color: white;">Objet</th>
<th style="border: 1px solid #ddd; padding: 8px; padding-top: 12px; padding-bottom: 12px; text-align: left; background-color: #135f7f; color: white;">Note</th>
<th style="border: 1px solid #ddd; padding: 8px; padding-top: 12px; padding-bottom: 12px; text-align: left; background-color: #135f7f; color: white;">Contact</th>
<th style="border: 1px solid #ddd; padding: 8px; padding-top: 12px; padding-bottom: 12px; text-align: left; background-color: #135f7f; color: white;">&nbsp;</th>
</tr>
<tr>
<td style="border: 1px solid #ddd; padding: 8px;">[res_letterbox.res_id]</td>
<td style="border: 1px solid #ddd; padding: 8px;">[res_letterbox.# ;frm=0000]</td>
<td style="border: 1px solid #ddd; padding: 8px;">[res_letterbox.doc_date;block=tr;frm=dd/mm/yyyy]</td>
<td style="border: 1px solid #ddd; padding: 8px;">[res_letterbox.subject]</td>
<td style="border: 1px solid #ddd; padding: 8px;">[notes.content;block=tr]</td>
<td style="border: 1px solid #ddd; padding: 8px;">[sender.company;block=tr] [sender.firstname] [sender.lastname]</td>
<td style="border: 1px solid #ddd; padding: 8px; text-align: right;"><a style="text-decoration: none; background: #135f7f; padding: 5px; color: white; -webkit-box-shadow: 6px 4px 5px 0px rgba(0,0,0,0.75); -moz-box-shadow: 6px 4px 5px 0px rgba(0,0,0,0.75); box-shadow: 6px 4px 5px 0px rgba(0,0,0,0.75);" href="[res_letterbox.linktodetail]" name="detail">D&eacute;tail</a> <a style="text-decoration: none; background: #135f7f; padding: 5px; color: white; -webkit-box-shadow: 6px 4px 5px 0px rgba(0,0,0,0.75); -moz-box-shadow: 6px 4px 5px 0px rgba(0,0,0,0.75); box-shadow: 6px 4px 5px 0px rgba(0,0,0,0.75);" href="[res_letterbox.linktodoc]" name="doc">Afficher</a></td>
</tr>
</tbody>
</table>
<p>&nbsp;</p>
<p style="font-family: Trebuchet MS, Arial, Helvetica, sans-serif; width: 100%; text-align: center; font-size: 9px; font-style: italic; opacity: 0.5;">Message g&eacute;n&eacute;r&eacute; via l''application MaarchCourrier</p>', 'HTML', NULL, NULL, 'ODP: open_office_presentation', 'notes', 'notifications', NULL, NULL);
INSERT INTO templates (template_id, template_label, template_comment, template_content, template_type, template_path, template_file_name, template_style, template_datasource, template_target, template_attachment_type, subject) VALUES (900, '[TRT] Passer me voir', 'Passer me voir', 'Passer me voir à mon bureau, merci.', 'TXT', NULL, NULL, 'XLSX: demo_spreadsheet_msoffice', '', 'notes', 'all', NULL);
INSERT INTO templates (template_id, template_label, template_comment, template_content, template_type, template_path, template_file_name, template_style, template_datasource, template_target, template_attachment_type, subject) VALUES (901, '[TRT] Compléter', 'Compléter', 'Le projet de réponse doit être complété/révisé sur les points suivants : 

- ', 'TXT', NULL, NULL, 'XLSX: demo_spreadsheet_msoffice', '', 'notes', 'all', NULL);
INSERT INTO templates (template_id, template_label, template_comment, template_content, template_type, template_path, template_file_name, template_style, template_datasource, template_target, template_attachment_type, subject) VALUES (902, '[AVIS] Demande avis', 'Demande avis', 'Merci de me fournir les éléments de langage pour répondre à ce courrier.', 'TXT', NULL, NULL, 'XLSX: demo_spreadsheet_msoffice', '', 'notes', 'all', NULL);
INSERT INTO templates (template_id, template_label, template_comment, template_content, template_type, template_path, template_file_name, template_style, template_datasource, template_target, template_attachment_type, subject) VALUES (904, '[AVIS] Avis favorable', 'Avis favorable', 'Merci de répondre favorablement à la demande inscrite dans ce courrier', 'TXT', NULL, NULL, 'XLSX: demo_spreadsheet_msoffice', '', 'notes', 'all', NULL);
INSERT INTO templates (template_id, template_label, template_comment, template_content, template_type, template_path, template_file_name, template_style, template_datasource, template_target, template_attachment_type, subject) VALUES (905, '[CLOTURE] Clôture pour REJET', 'Clôture pour REJET', 'Clôture pour REJET', 'TXT', NULL, NULL, 'XLSX: demo_spreadsheet_msoffice', '', 'notes', 'all', NULL);
INSERT INTO templates (template_id, template_label, template_comment, template_content, template_type, template_path, template_file_name, template_style, template_datasource, template_target, template_attachment_type, subject) VALUES (906, '[CLOTURE] Clôture pour ABANDON', 'Clôture pour ABANDON', 'Clôture pour ABANDON', 'TXT', NULL, NULL, 'XLSX: demo_spreadsheet_msoffice', '', 'notes', 'all', NULL);
INSERT INTO templates (template_id, template_label, template_comment, template_content, template_type, template_path, template_file_name, template_style, template_datasource, template_target, template_attachment_type, subject) VALUES (907, '[CLOTURE] Clôture RAS', 'Clôture RAS', 'Clôture NORMALE', 'TXT', NULL, NULL, 'XLSX: demo_spreadsheet_msoffice', '', 'notes', 'all', NULL);
INSERT INTO templates (template_id, template_label, template_comment, template_content, template_type, template_path, template_file_name, template_style, template_datasource, template_target, template_attachment_type, subject) VALUES (908, '[CLOTURE] Clôture AUTRE', 'Clôture AUTRE', 'Clôture pour ce motif : ', 'TXT', NULL, NULL, 'XLSX: demo_spreadsheet_msoffice', '', 'notes', 'all', NULL);
INSERT INTO templates (template_id, template_label, template_comment, template_content, template_type, template_path, template_file_name, template_style, template_datasource, template_target, template_attachment_type, subject) VALUES (909, '[REJET] Erreur affectation', 'Erreur affectation', 'Ce courrier ne semble pas concerner mon service', 'TXT', NULL, NULL, 'XLSX: demo_spreadsheet_msoffice', '', 'notes', 'all', NULL);
INSERT INTO templates (template_id, template_label, template_comment, template_content, template_type, template_path, template_file_name, template_style, template_datasource, template_target, template_attachment_type, subject) VALUES (910, '[REJET] Anomalie de numérisation', 'Anomalie de numérisation', 'Le courrier présente des anomalies de numérisation', 'TXT', NULL, NULL, 'XLSX: demo_spreadsheet_msoffice', '', 'notes', 'all', NULL);
INSERT INTO templates (template_id, template_label, template_comment, template_content, template_type, template_path, template_file_name, template_style, template_datasource, template_target, template_attachment_type, subject, options) VALUES (1033, 'AR EN MASSE TYPE SIMPLE', 'Cas d’une demande n’impliquant pas de décision implicite de l’administration', '<div id="write" class="is-node"><br /><hr /><span style="color: #236fa1;">H&ocirc;tel de ville</span><br /><span style="color: #236fa1;">Place de la Libert&eacute;</span><br /><span style="color: #236fa1;">99000 Maarch-les-bains</span>
<p>&nbsp;</p>
<p><span style="color: #236fa1;"><strong>Accus&eacute; de r&eacute;ception</strong></span></p>
<p>Service instructeur : <strong>[userPrimaryEntity.entity_label]</strong> <br />Courriel : [userPrimaryEntity.email]</p>
<p>[userPrimaryEntity.address_town], le [datetime.date;frm=dddd dd mmmm yyyy (locale)]</p>
<hr />
<p>Bonjour,</p>
<p>Votre demande concernant :</p>
<p><strong>[res_letterbox.subject]</strong></p>
<p>&agrave; bien &eacute;t&eacute; r&eacute;ceptionn&eacute;e par nos services le [res_letterbox.admission_date].</p>
<p><br />La r&eacute;f&eacute;rence de votre dossier est : <strong>[res_letterbox.alt_identifier]</strong></p>
<p>Le pr&eacute;sent accus&eacute; de r&eacute;ception atteste de la r&eacute;ception de votre demande. Il ne pr&eacute;juge pas de la conformit&eacute; de son contenu qui d&eacute;pend entre autres de l''&eacute;tude des pi&egrave;ces fournies.</p>
<p>Si l''instruction de votre demande n&eacute;cessite des informations ou des pi&egrave;ces compl&eacute;mentaires, nos services vous en ferons la demande</p>
<p>&nbsp;</p>
<p>Nous vous conseillons de conserver ce message jusqu''&agrave; la fin du traitement de votre dossier.</p>
<p>&nbsp;</p>
<p>[userPrimaryEntity.entity_label]</p>
<p>Ville de Maarch-les-Bains</p>
<p>&nbsp;</p>
</div>', 'OFFICE_HTML', '2021/03/0001/', '0011_1443263267.docx', '', 'letterbox_attachment', 'acknowledgementReceipt', 'simple', NULL, '{"acknowledgementReceiptFrom": "destination"}');
INSERT INTO templates (template_id, template_label, template_comment, template_content, template_type, template_path, template_file_name, template_style, template_datasource, template_target, template_attachment_type, subject, options) VALUES (1034, 'AR EN MASSE TYPE SVA', 'Cas d’une demande impliquant une décision implicite d''acceptation de l’administration', '<div id="write" class="is-node"><br /><hr /><span style="color: #236fa1;">H&ocirc;tel de ville</span><br /><span style="color: #236fa1;">Place de la Libert&eacute;</span><br /><span style="color: #236fa1;">99000 Maarch-les-bains</span>
<p>&nbsp;</p>
<p><span style="color: #236fa1;"><strong>Accus&eacute; de r&eacute;ception de votre demande intervenant<br />dans le cadre d''une d&eacute;cision implicite d''acceptation<br /></strong></span></p>
<p>Num&eacute;ro d''enregistrement :<strong> [res_letterbox.alt_identifier]</strong></p>
<p>Service instructeur : <strong>[userPrimaryEntity.entity_label]</strong> <br />Courriel : [userPrimaryEntity.email]</p>
<p>[userPrimaryEntity.address_town], le [datetime.date;frm=dddd dd mmmm yyyy (locale)]</p>
<hr />
<p>Bonjour,</p>
<p>Votre demande concernant :</p>
<p><strong>[res_letterbox.subject]</strong></p>
<p>&agrave; bien &eacute;t&eacute; r&eacute;ceptionn&eacute;e par nos services le [res_letterbox.admission_date].</p>
<p><br />La r&eacute;f&eacute;rence de votre dossier est : <strong>[res_letterbox.alt_identifier]</strong></p>
Le pr&eacute;sent accus&eacute; de r&eacute;ception atteste de la r&eacute;ception de votre demande. il ne pr&eacute;juge pas de la conformit&eacute; de son contenu qui d&eacute;pend entre autres de l''''&eacute;tude des pi&egrave;ces fournies.<br /><br />Votre demande est susceptible de faire l''objet d''''une d&eacute;cision implicite d''''acceptation en l''absence de r&eacute;ponse dans les jours suivant sa r&eacute;ception, soit le <strong>[res_letterbox.process_limit_date]</strong>.<br /><br />Si l''instruction de votre demande n&eacute;cessite des informations ou pi&egrave;ces compl&eacute;mentaires, la Ville vous contactera afin de les fournir, dans un d&eacute;lai de production qui sera fix&eacute;.<br /><br />Le cas &eacute;ch&eacute;ant, le d&eacute;lai de d&eacute;cision implicite d''acceptation ne d&eacute;butera qu''''apr&egrave;s la production des pi&egrave;ces demand&eacute;es.<br /><br />En cas de d&eacute;cision implicite d''''acceptation vous avez la possibilit&eacute; de demander au service charg&eacute; du dossier une attestation conform&eacute;ment aux dispositions de l''article 22 de la loi n&deg; 2000-321 du 12 avril 2000 relative aux droits des citoyens dans leurs relations avec les administrations modifi&eacute;e.
<p>Nous vous conseillons de conserver ce message jusqu''&agrave; la fin du traitement de votre dossier.</p>
<p>&nbsp;</p>
<p><span style="color: #236fa1;">Ville de Maarch-les-Bains</span><br />[userPrimaryEntity.entity_label]</p>
<p>Courriel : [userPrimaryEntity.email]<br />T&eacute;l&eacute;phone : [user.phone]</p>
</div>', 'OFFICE_HTML', NULL, NULL, 'DOCX: AR_Masse_SVA', 'letterbox_attachment', 'acknowledgementReceipt', 'sva', NULL, '{"acknowledgementReceiptFrom": "destination"}');
INSERT INTO templates (template_id, template_label, template_comment, template_content, template_type, template_path, template_file_name, template_style, template_datasource, template_target, template_attachment_type, subject) VALUES (1045, 'AR TYPE SVR - Courriel Manuel', 'A utiliser avec l''action "Générer les AR"', '<div id="write" class="is-node"><br /><hr /><span style="color: #236fa1;">H&ocirc;tel de ville</span><br /><span style="color: #236fa1;">Place de la Libert&eacute;</span><br /><span style="color: #236fa1;">99000 Maarch-les-bains</span>
<p>&nbsp;</p>
<p><span style="color: #236fa1;"><strong>Accus&eacute; de r&eacute;ception de votre demande intervenant<br />dans le cadre d''une d&eacute;cision implicite de rejet<br /></strong></span></p>
<p>Num&eacute;ro d''enregistrement :<strong> [res_letterbox.alt_identifier]</strong></p>
<p>Service instructeur : <strong>[userPrimaryEntity.entity_label]</strong> <br />Courriel : [userPrimaryEntity.email]</p>
<p>[userPrimaryEntity.address_town], le [datetime.date;frm=dddd dd mmmm yyyy (locale)]</p>
<hr />
<p>Bonjour,</p>
<p>Votre demande concernant :</p>
<p><strong>[res_letterbox.subject]</strong></p>
<p>&agrave; bien &eacute;t&eacute; r&eacute;ceptionn&eacute;e par nos services le [res_letterbox.admission_date].</p>
<p><br />La r&eacute;f&eacute;rence de votre dossier est : <strong>[res_letterbox.alt_identifier]</strong></p>
Le pr&eacute;sent accus&eacute; de r&eacute;ception atteste de la r&eacute;ception de votre demande. il ne pr&eacute;juge pas de la conformit&eacute; de son contenu qui d&eacute;pend entre autres de l''''&eacute;tude des pi&egrave;ces fournies.<br /><br />Votre demande est susceptible de faire l''objet d''une d&eacute;cision implicite de rejet en l''absence de r&eacute;ponse dans les jours suivant sa r&eacute;ception, soit le <strong>[res_letterbox.process_limit_date]</strong>.<br /><br />Si l''instruction de votre demande n&eacute;cessite des informations ou pi&egrave;ces compl&eacute;mentaires, la Ville vous contactera afin de les fournir, dans un d&eacute;lai de production qui sera fix&eacute;.<br /><br />Dans ce cas, le d&eacute;lai de d&eacute;cision implicite de rejet serait alors suspendu le temps de produire les pi&egrave;ces demand&eacute;es.<br /><br />Si vous estimez que la d&eacute;cision qui sera prise par l''administration est contestable, vous pourrez formuler :<br /><br />- Soit un recours gracieux devant l''auteur de la d&eacute;cision<br />- Soit un recours hi&eacute;rarchique devant le Maire<br />- Soit un recours contentieux devant le Tribunal Administratif territorialement comp&eacute;tent.<br /><br />Le recours gracieux ou le recours hi&eacute;rarchique peuvent &ecirc;tre faits sans condition de d&eacute;lais.<br /><br />Le recours contentieux doit intervenir dans un d&eacute;lai de deux mois &agrave; compter de la notification de la d&eacute;cision.<br /><br />Toutefois, si vous souhaitez en cas de rejet du recours gracieux ou du recours hi&eacute;rarchique former un recours contentieux, ce recours gracieux ou hi&eacute;rarchique devra avoir &eacute;t&eacute; introduit dans le d&eacute;lai sus-indiqu&eacute; du recours contentieux.<br /><br />Vous conserverez ainsi la possibilit&eacute; de former un recours contentieux, dans un d&eacute;lai de deux mois &agrave; compter de la d&eacute;cision intervenue sur ledit recours gracieux ou hi&eacute;rarchique.<br />
<p>Nous vous conseillons de conserver ce message jusqu''&agrave; la fin du traitement de votre dossier.</p>
<p>&nbsp;</p>
<p><span style="color: #236fa1;">Ville de Maarch-les-Bains</span><br />[userPrimaryEntity.entity_label]</p>
<p>Courriel : [userPrimaryEntity.email]<br />T&eacute;l&eacute;phone : [user.phone]</p>
</div>', 'HTML', NULL, NULL, NULL, 'letterbox_attachment', 'sendmail', 'all', NULL);
INSERT INTO templates (template_id, template_label, template_comment, template_content, template_type, template_path, template_file_name, template_style, template_datasource, template_target, template_attachment_type, subject, options) VALUES (1035, 'AR EN MASSE TYPE SVR', 'Cas d’une demande impliquant une décision implicite de rejet de l’administration', '<div id="write" class="is-node"><br /><hr /><span style="color: #236fa1;">H&ocirc;tel de ville</span><br /><span style="color: #236fa1;">Place de la Libert&eacute;</span><br /><span style="color: #236fa1;">99000 Maarch-les-bains</span>
<p>&nbsp;</p>
<p><span style="color: #236fa1;"><strong>Accus&eacute; de r&eacute;ception de votre demande intervenant<br />dans le cadre d''une d&eacute;cision implicite de rejet<br /></strong></span></p>
<p>Num&eacute;ro d''enregistrement :<strong> [res_letterbox.alt_identifier]</strong></p>
<p>Service instructeur : <strong>[userPrimaryEntity.entity_label]</strong> <br />Courriel : [userPrimaryEntity.email]</p>
<p>[userPrimaryEntity.address_town], le [datetime.date;frm=dddd dd mmmm yyyy (locale)]</p>
<hr />
<p>Bonjour,</p>
<p>Votre demande concernant :</p>
<p><strong>[res_letterbox.subject]</strong></p>
<p>&agrave; bien &eacute;t&eacute; r&eacute;ceptionn&eacute;e par nos services le [res_letterbox.admission_date].</p>
<p><br />La r&eacute;f&eacute;rence de votre dossier est : <strong>[res_letterbox.alt_identifier]</strong></p>
Le pr&eacute;sent accus&eacute; de r&eacute;ception atteste de la r&eacute;ception de votre demande. il ne pr&eacute;juge pas de la conformit&eacute; de son contenu qui d&eacute;pend entre autres de l''''&eacute;tude des pi&egrave;ces fournies.<br /><br />Votre demande est susceptible de faire l''objet d''une d&eacute;cision implicite de rejet en l''absence de r&eacute;ponse dans les jours suivant sa r&eacute;ception, soit le <strong>[res_letterbox.process_limit_date]</strong>.<br /><br />Si l''instruction de votre demande n&eacute;cessite des informations ou pi&egrave;ces compl&eacute;mentaires, la Ville vous contactera afin de les fournir, dans un d&eacute;lai de production qui sera fix&eacute;.<br /><br />Dans ce cas, le d&eacute;lai de d&eacute;cision implicite de rejet serait alors suspendu le temps de produire les pi&egrave;ces demand&eacute;es.<br /><br />Si vous estimez que la d&eacute;cision qui sera prise par l''administration est contestable, vous pourrez formuler :<br /><br />- Soit un recours gracieux devant l''auteur de la d&eacute;cision<br />- Soit un recours hi&eacute;rarchique devant le Maire<br />- Soit un recours contentieux devant le Tribunal Administratif territorialement comp&eacute;tent.<br /><br />Le recours gracieux ou le recours hi&eacute;rarchique peuvent &ecirc;tre faits sans condition de d&eacute;lais.<br /><br />Le recours contentieux doit intervenir dans un d&eacute;lai de deux mois &agrave; compter de la notification de la d&eacute;cision.<br /><br />Toutefois, si vous souhaitez en cas de rejet du recours gracieux ou du recours hi&eacute;rarchique former un recours contentieux, ce recours gracieux ou hi&eacute;rarchique devra avoir &eacute;t&eacute; introduit dans le d&eacute;lai sus-indiqu&eacute; du recours contentieux.<br /><br />Vous conserverez ainsi la possibilit&eacute; de former un recours contentieux, dans un d&eacute;lai de deux mois &agrave; compter de la d&eacute;cision intervenue sur ledit recours gracieux ou hi&eacute;rarchique.<br />
<p>Nous vous conseillons de conserver ce message jusqu''&agrave; la fin du traitement de votre dossier.</p>
<p>&nbsp;</p>
<p><span style="color: #236fa1;">Ville de Maarch-les-Bains</span><br />[userPrimaryEntity.entity_label]</p>
<p>Courriel : [userPrimaryEntity.email]<br />T&eacute;l&eacute;phone : [user.phone]</p>
</div>', 'OFFICE_HTML', NULL, NULL, 'DOCX: AR_Masse_SVR', 'letterbox_attachment', 'acknowledgementReceipt', 'svr', NULL, '{"acknowledgementReceiptFrom": "destination"}');
INSERT INTO templates (template_id, template_label, template_comment, template_content, template_type, template_path, template_file_name, template_style, template_datasource, template_target, template_attachment_type, subject) VALUES (1036, 'SVE - Courriel de réorientation', 'Modèle de courriel de réorientation d''une saisine SVE', '<div id="write" class="is-node"><br /><hr /><span style="color: #236fa1;">H&ocirc;tel de ville</span><br /><span style="color: #236fa1;">Place de la Libert&eacute;</span><br /><span style="color: #236fa1;">99000 Maarch-les-bains</span>
<p>[destination.entity_label]<br /><br />T&eacute;l&eacute;phone : &nbsp;&nbsp; &nbsp;[user.phone]<br />Courriel : &nbsp;&nbsp;&nbsp; [destination.email]</p>
<p>[destination.address_town], le [datetime.date;frm=dddd dd mmmm yyyy (locale)]</p>
<hr />
<p>Bonjour,</p>
Le [res_letterbox.doc_date], vous avez transmis par voie &eacute;lectronique &agrave; la Ville une demande qui ne rel&egrave;ve pas de sa comp&eacute;tence.<br /><br />Votre demande cit&eacute;e en objet de ce courriel a &eacute;t&eacute; transmise &agrave;</div>
<div class="is-node">&nbsp;</div>
<div class="is-node">(veuillez renseigner le nom de l''AUTORITE COMPETENTE).<br />
<p><br /><br /></p>
<p>&nbsp;</p>
<p>&nbsp;</p>
</div>', 'HTML', NULL, NULL, 'DOCX: AR_Masse_SVA', 'letterbox_attachment', 'sendmail', 'all', NULL);
INSERT INTO templates (template_id, template_label, template_comment, template_content, template_type, template_path, template_file_name, template_style, template_datasource, template_target, template_attachment_type, subject) VALUES (1043, 'AR TYPE SIMPLE- Courriel Manuel', 'A utiliser avec l''action "Générer les AR"', '<div id="write" class="is-node"><br /><hr /><span style="color: #236fa1;">H&ocirc;tel de ville</span><br /><span style="color: #236fa1;">Place de la Libert&eacute;</span><br /><span style="color: #236fa1;">99000 Maarch-les-bains</span>
<p>&nbsp;</p>
<p><span style="color: #236fa1;"><strong>Accus&eacute; de r&eacute;ception</strong></span></p>
<p>Service instructeur : <strong>[userPrimaryEntity.entity_label]</strong> <br />Courriel : [userPrimaryEntity.email]</p>
<p>[userPrimaryEntity.address_town], le [datetime.date;frm=dddd dd mmmm yyyy (locale)]</p>
<hr />
<p>Bonjour,</p>
<p>Votre demande concernant :</p>
<p><strong>[res_letterbox.subject]</strong></p>
<p>&agrave; bien &eacute;t&eacute; r&eacute;ceptionn&eacute;e par nos services le [res_letterbox.admission_date].</p>
<p><br />La r&eacute;f&eacute;rence de votre dossier est : <strong>[res_letterbox.alt_identifier]</strong></p>
<p>Le pr&eacute;sent accus&eacute; de r&eacute;ception atteste de la r&eacute;ception de votre demande. Il ne pr&eacute;juge pas de la conformit&eacute; de son contenu qui d&eacute;pend entre autres de l''&eacute;tude des pi&egrave;ces fournies.</p>
<p>Si l''instruction de votre demande n&eacute;cessite des informations ou des pi&egrave;ces compl&eacute;mentaires, nos services vous en ferons la demande</p>
<p>&nbsp;</p>
<p>Nous vous conseillons de conserver ce message jusqu''&agrave; la fin du traitement de votre dossier.</p>
<p>&nbsp;</p>
<p>[userPrimaryEntity.entity_label]</p>
<p>Ville de Maarch-les-Bains</p>
<p>&nbsp;</p>
</div>', 'HTML', NULL, NULL, NULL, 'letterbox_attachment', 'sendmail', 'all', NULL);
INSERT INTO templates (template_id, template_label, template_comment, template_content, template_type, template_path, template_file_name, template_style, template_datasource, template_target, template_attachment_type, subject) VALUES (1044, 'AR TYPE SVA - Courriel Manuel', 'A utiliser avec l''action "Générer les AR"', '<div id="write" class="is-node"><br /><hr /><span style="color: #236fa1;">H&ocirc;tel de ville</span><br /><span style="color: #236fa1;">Place de la Libert&eacute;</span><br /><span style="color: #236fa1;">99000 Maarch-les-bains</span>
<p>&nbsp;</p>
<p><span style="color: #236fa1;"><strong>Accus&eacute; de r&eacute;ception de votre demande intervenant<br />dans le cadre d''une d&eacute;cision implicite d''acceptation<br /></strong></span></p>
<p>Num&eacute;ro d''enregistrement :<strong> [res_letterbox.alt_identifier]</strong></p>
<p>Service instructeur : <strong>[userPrimaryEntity.entity_label]</strong> <br />Courriel : [userPrimaryEntity.email]</p>
<p>[userPrimaryEntity.address_town], le [datetime.date;frm=dddd dd mmmm yyyy (locale)]</p>
<hr />
<p>Bonjour,</p>
<p>Votre demande concernant :</p>
<p><strong>[res_letterbox.subject]</strong></p>
<p>&agrave; bien &eacute;t&eacute; r&eacute;ceptionn&eacute;e par nos services le [res_letterbox.admission_date].</p>
<p><br />La r&eacute;f&eacute;rence de votre dossier est : <strong>[res_letterbox.alt_identifier]</strong></p>
Le pr&eacute;sent accus&eacute; de r&eacute;ception atteste de la r&eacute;ception de votre demande. il ne pr&eacute;juge pas de la conformit&eacute; de son contenu qui d&eacute;pend entre autres de l''''&eacute;tude des pi&egrave;ces fournies.<br /><br />Votre demande est susceptible de faire l''objet d''''une d&eacute;cision implicite d''''acceptation en l''absence de r&eacute;ponse dans les jours suivant sa r&eacute;ception, soit le <strong>[res_letterbox.process_limit_date]</strong>.<br /><br />Si l''instruction de votre demande n&eacute;cessite des informations ou pi&egrave;ces compl&eacute;mentaires, la Ville vous contactera afin de les fournir, dans un d&eacute;lai de production qui sera fix&eacute;.<br /><br />Le cas &eacute;ch&eacute;ant, le d&eacute;lai de d&eacute;cision implicite d''acceptation ne d&eacute;butera qu''''apr&egrave;s la production des pi&egrave;ces demand&eacute;es.<br /><br />En cas de d&eacute;cision implicite d''''acceptation vous avez la possibilit&eacute; de demander au service charg&eacute; du dossier une attestation conform&eacute;ment aux dispositions de l''article 22 de la loi n&deg; 2000-321 du 12 avril 2000 relative aux droits des citoyens dans leurs relations avec les administrations modifi&eacute;e.
<p>Nous vous conseillons de conserver ce message jusqu''&agrave; la fin du traitement de votre dossier.</p>
<p>&nbsp;</p>
<p><span style="color: #236fa1;">Ville de Maarch-les-Bains</span><br />[userPrimaryEntity.entity_label]</p>
<p>Courriel : [userPrimaryEntity.email]<br />T&eacute;l&eacute;phone : [user.phone]</p>
</div>', 'HTML', NULL, NULL, NULL, 'letterbox_attachment', 'sendmail', 'all', NULL);
INSERT INTO templates (template_id, template_label, template_comment, template_content, template_type, template_path, template_file_name, template_style, template_datasource, template_target, template_attachment_type, subject) VALUES (1041, 'PR - Invitation (Visa interne)', 'Projet de réponse invitation pour visa interne', NULL, 'OFFICE', '2021/03/0001/', '0001_742130848.docx', 'DOCX: PR02_INVITATION', 'letterbox_attachment', 'attachments', 'response_project', NULL);
INSERT INTO templates (template_id, template_label, template_comment, template_content, template_type, template_path, template_file_name, template_style, template_datasource, template_target, template_attachment_type, subject) VALUES (1047, 'EC - Générique (Visa externe)', 'Enregistrement de courrier générique', NULL, 'OFFICE', '2021/03/0001/', '0005_1707546937.docx', 'DOCX: EC01_GENERIC', 'letterbox_attachment', 'indexingFile', 'all', NULL);
INSERT INTO templates (template_id, template_label, template_comment, template_content, template_type, template_path, template_file_name, template_style, template_datasource, template_target, template_attachment_type, subject) VALUES (20, 'Courriel d''accompagnement', 'Modèle de courriel d''accompagnement', '<div id="write" class="is-node"><br /><hr /><span style="color: #236fa1;">H&ocirc;tel de ville</span><br /><span style="color: #236fa1;">Place de la Libert&eacute;</span><br /><span style="color: #236fa1;">99000 Maarch-les-bains</span>
<p>[user.firstname] [user.lastname]<br />[userPrimaryEntity.role]<br />[userPrimaryEntity.entity_label]<br /><br />T&eacute;l&eacute;phone : &nbsp;&nbsp; &nbsp;[user.phone]<br />Courriel : &nbsp;&nbsp; &nbsp;[user.mail]</p>
<p>[userPrimaryEntity.address_town], le [datetime.date;frm=dddd dd mmmm yyyy (locale)]</p>
<hr />
<p>Bonjour,</p>
<p>Veuillez trouver en pi&egrave;ce jointe &agrave; ce courriel notre r&eacute;ponse &agrave; votre demande du [res_letterbox.admission_date].</p>
<p>Bien cordialement.</p>
<p>[user.firstname] [user.lastname]<br />[userPrimaryEntity.role]<br />[userPrimaryEntity.entity_label]<br /><br /></p>
<p>&nbsp;</p>
<p>&nbsp;</p>
</div>', 'HTML', NULL, NULL, 'DOCX: standard_nosign', 'letterbox_attachment', 'sendmail', 'all', NULL);
INSERT INTO templates (template_id, template_label, template_comment, template_content, template_type, template_path, template_file_name, template_style, template_datasource, template_target, template_attachment_type, subject) VALUES (1048, 'PR - Générique (Visa externe)', 'Projet de réponse générique', NULL, 'OFFICE', '2021/03/0001/', '0008_1397704541.docx', 'DOCX: PR01_GENERIC', 'letterbox_attachment', 'attachments', 'response_project', NULL);
INSERT INTO templates (template_id, template_label, template_comment, template_content, template_type, template_path, template_file_name, template_style, template_datasource, template_target, template_attachment_type, subject) VALUES (1038, 'EC - Générique (Visa interne)', 'Enregistrement de courrier générique', NULL, 'OFFICE', '2021/03/0001/', '0003_320653448.docx', 'DOCX: EC01_GENERIC', 'letterbox_attachment', 'indexingFile', 'all', NULL);
INSERT INTO templates (template_id, template_label, template_comment, template_content, template_type, template_path, template_file_name, template_style, template_datasource, template_target, template_attachment_type, subject) VALUES (1040, 'PR - Générique (Visa interne)', 'Projet de réponse générique', NULL, 'OFFICE', '2021/03/0001/', '0006_1786637551.docx', 'DOCX: PR01_GENERIC', 'letterbox_attachment', 'attachments', 'response_project', NULL);
INSERT INTO templates (template_id, template_label, template_comment, template_content, template_type, template_path, template_file_name, template_style, template_datasource, template_target, template_attachment_type, subject) VALUES (1046, 'PR - Invitation (Visa externe)', 'Modèle invitation pour visa externe', NULL, 'OFFICE', '2021/03/0001/', '0002_705367294.docx', NULL, 'letterbox_attachment', 'attachments', 'response_project', NULL);

INSERT INTO templates_association (id, template_id, value_field) VALUES (1, 900, 'VILLE');
INSERT INTO templates_association (id, template_id, value_field) VALUES (2, 901, 'VILLE');
INSERT INTO templates_association (id, template_id, value_field) VALUES (3, 902, 'VILLE');
INSERT INTO templates_association (id, template_id, value_field) VALUES (4, 904, 'VILLE');
INSERT INTO templates_association (id, template_id, value_field) VALUES (5, 905, 'VILLE');
INSERT INTO templates_association (id, template_id, value_field) VALUES (6, 906, 'VILLE');
INSERT INTO templates_association (id, template_id, value_field) VALUES (7, 907, 'VILLE');
INSERT INTO templates_association (id, template_id, value_field) VALUES (8, 908, 'VILLE');
INSERT INTO templates_association (id, template_id, value_field) VALUES (9, 909, 'VILLE');
INSERT INTO templates_association (id, template_id, value_field) VALUES (10, 910, 'VILLE');
INSERT INTO templates_association (id, template_id, value_field) VALUES (11, 1033, 'VILLE');
INSERT INTO templates_association (id, template_id, value_field) VALUES (12, 1034, 'VILLE');
INSERT INTO templates_association (id, template_id, value_field) VALUES (572, 1046, 'CAB');
INSERT INTO templates_association (id, template_id, value_field) VALUES (573, 1046, 'COR');
INSERT INTO templates_association (id, template_id, value_field) VALUES (15, 1035, 'VILLE');
INSERT INTO templates_association (id, template_id, value_field) VALUES (574, 1046, 'FIN');
INSERT INTO templates_association (id, template_id, value_field) VALUES (575, 1046, 'DRH');
INSERT INTO templates_association (id, template_id, value_field) VALUES (576, 1046, 'DSI');
INSERT INTO templates_association (id, template_id, value_field) VALUES (577, 1046, 'DGA');
INSERT INTO templates_association (id, template_id, value_field) VALUES (20, 900, 'CAB');
INSERT INTO templates_association (id, template_id, value_field) VALUES (21, 901, 'CAB');
INSERT INTO templates_association (id, template_id, value_field) VALUES (22, 902, 'CAB');
INSERT INTO templates_association (id, template_id, value_field) VALUES (23, 904, 'CAB');
INSERT INTO templates_association (id, template_id, value_field) VALUES (24, 905, 'CAB');
INSERT INTO templates_association (id, template_id, value_field) VALUES (25, 906, 'CAB');
INSERT INTO templates_association (id, template_id, value_field) VALUES (26, 907, 'CAB');
INSERT INTO templates_association (id, template_id, value_field) VALUES (27, 908, 'CAB');
INSERT INTO templates_association (id, template_id, value_field) VALUES (28, 909, 'CAB');
INSERT INTO templates_association (id, template_id, value_field) VALUES (29, 910, 'CAB');
INSERT INTO templates_association (id, template_id, value_field) VALUES (30, 1033, 'CAB');
INSERT INTO templates_association (id, template_id, value_field) VALUES (31, 1034, 'CAB');
INSERT INTO templates_association (id, template_id, value_field) VALUES (578, 1046, 'DGS');
INSERT INTO templates_association (id, template_id, value_field) VALUES (579, 1046, 'PE');
INSERT INTO templates_association (id, template_id, value_field) VALUES (34, 1035, 'CAB');
INSERT INTO templates_association (id, template_id, value_field) VALUES (580, 1046, 'PCU');
INSERT INTO templates_association (id, template_id, value_field) VALUES (581, 1046, 'PSF');
INSERT INTO templates_association (id, template_id, value_field) VALUES (582, 1046, 'PJS');
INSERT INTO templates_association (id, template_id, value_field) VALUES (583, 1046, 'PJU');
INSERT INTO templates_association (id, template_id, value_field) VALUES (39, 900, 'DGS');
INSERT INTO templates_association (id, template_id, value_field) VALUES (40, 901, 'DGS');
INSERT INTO templates_association (id, template_id, value_field) VALUES (41, 902, 'DGS');
INSERT INTO templates_association (id, template_id, value_field) VALUES (42, 904, 'DGS');
INSERT INTO templates_association (id, template_id, value_field) VALUES (43, 905, 'DGS');
INSERT INTO templates_association (id, template_id, value_field) VALUES (44, 906, 'DGS');
INSERT INTO templates_association (id, template_id, value_field) VALUES (45, 907, 'DGS');
INSERT INTO templates_association (id, template_id, value_field) VALUES (46, 908, 'DGS');
INSERT INTO templates_association (id, template_id, value_field) VALUES (47, 909, 'DGS');
INSERT INTO templates_association (id, template_id, value_field) VALUES (48, 910, 'DGS');
INSERT INTO templates_association (id, template_id, value_field) VALUES (49, 1033, 'DGS');
INSERT INTO templates_association (id, template_id, value_field) VALUES (50, 1034, 'DGS');
INSERT INTO templates_association (id, template_id, value_field) VALUES (584, 1046, 'PSO');
INSERT INTO templates_association (id, template_id, value_field) VALUES (585, 1046, 'PTE');
INSERT INTO templates_association (id, template_id, value_field) VALUES (53, 1035, 'DGS');
INSERT INTO templates_association (id, template_id, value_field) VALUES (586, 1046, 'DSG');
INSERT INTO templates_association (id, template_id, value_field) VALUES (587, 1046, 'COU');
INSERT INTO templates_association (id, template_id, value_field) VALUES (588, 1046, 'SP');
INSERT INTO templates_association (id, template_id, value_field) VALUES (589, 1046, 'VILLE');
INSERT INTO templates_association (id, template_id, value_field) VALUES (58, 900, 'DGA');
INSERT INTO templates_association (id, template_id, value_field) VALUES (59, 901, 'DGA');
INSERT INTO templates_association (id, template_id, value_field) VALUES (60, 902, 'DGA');
INSERT INTO templates_association (id, template_id, value_field) VALUES (61, 904, 'DGA');
INSERT INTO templates_association (id, template_id, value_field) VALUES (62, 905, 'DGA');
INSERT INTO templates_association (id, template_id, value_field) VALUES (63, 906, 'DGA');
INSERT INTO templates_association (id, template_id, value_field) VALUES (64, 907, 'DGA');
INSERT INTO templates_association (id, template_id, value_field) VALUES (65, 908, 'DGA');
INSERT INTO templates_association (id, template_id, value_field) VALUES (66, 909, 'DGA');
INSERT INTO templates_association (id, template_id, value_field) VALUES (67, 910, 'DGA');
INSERT INTO templates_association (id, template_id, value_field) VALUES (68, 1033, 'DGA');
INSERT INTO templates_association (id, template_id, value_field) VALUES (69, 1034, 'DGA');
INSERT INTO templates_association (id, template_id, value_field) VALUES (72, 1035, 'DGA');
INSERT INTO templates_association (id, template_id, value_field) VALUES (77, 900, 'PCU');
INSERT INTO templates_association (id, template_id, value_field) VALUES (78, 901, 'PCU');
INSERT INTO templates_association (id, template_id, value_field) VALUES (79, 902, 'PCU');
INSERT INTO templates_association (id, template_id, value_field) VALUES (80, 904, 'PCU');
INSERT INTO templates_association (id, template_id, value_field) VALUES (81, 905, 'PCU');
INSERT INTO templates_association (id, template_id, value_field) VALUES (82, 906, 'PCU');
INSERT INTO templates_association (id, template_id, value_field) VALUES (83, 907, 'PCU');
INSERT INTO templates_association (id, template_id, value_field) VALUES (84, 908, 'PCU');
INSERT INTO templates_association (id, template_id, value_field) VALUES (85, 909, 'PCU');
INSERT INTO templates_association (id, template_id, value_field) VALUES (86, 910, 'PCU');
INSERT INTO templates_association (id, template_id, value_field) VALUES (87, 1033, 'PCU');
INSERT INTO templates_association (id, template_id, value_field) VALUES (88, 1034, 'PCU');
INSERT INTO templates_association (id, template_id, value_field) VALUES (91, 1035, 'PCU');
INSERT INTO templates_association (id, template_id, value_field) VALUES (96, 900, 'PJS');
INSERT INTO templates_association (id, template_id, value_field) VALUES (97, 901, 'PJS');
INSERT INTO templates_association (id, template_id, value_field) VALUES (98, 902, 'PJS');
INSERT INTO templates_association (id, template_id, value_field) VALUES (99, 904, 'PJS');
INSERT INTO templates_association (id, template_id, value_field) VALUES (100, 905, 'PJS');
INSERT INTO templates_association (id, template_id, value_field) VALUES (101, 906, 'PJS');
INSERT INTO templates_association (id, template_id, value_field) VALUES (102, 907, 'PJS');
INSERT INTO templates_association (id, template_id, value_field) VALUES (103, 908, 'PJS');
INSERT INTO templates_association (id, template_id, value_field) VALUES (104, 909, 'PJS');
INSERT INTO templates_association (id, template_id, value_field) VALUES (105, 910, 'PJS');
INSERT INTO templates_association (id, template_id, value_field) VALUES (106, 1033, 'PJS');
INSERT INTO templates_association (id, template_id, value_field) VALUES (107, 1034, 'PJS');
INSERT INTO templates_association (id, template_id, value_field) VALUES (110, 1035, 'PJS');
INSERT INTO templates_association (id, template_id, value_field) VALUES (115, 900, 'PE');
INSERT INTO templates_association (id, template_id, value_field) VALUES (116, 901, 'PE');
INSERT INTO templates_association (id, template_id, value_field) VALUES (117, 902, 'PE');
INSERT INTO templates_association (id, template_id, value_field) VALUES (118, 904, 'PE');
INSERT INTO templates_association (id, template_id, value_field) VALUES (119, 905, 'PE');
INSERT INTO templates_association (id, template_id, value_field) VALUES (120, 906, 'PE');
INSERT INTO templates_association (id, template_id, value_field) VALUES (121, 907, 'PE');
INSERT INTO templates_association (id, template_id, value_field) VALUES (122, 908, 'PE');
INSERT INTO templates_association (id, template_id, value_field) VALUES (123, 909, 'PE');
INSERT INTO templates_association (id, template_id, value_field) VALUES (124, 910, 'PE');
INSERT INTO templates_association (id, template_id, value_field) VALUES (125, 1033, 'PE');
INSERT INTO templates_association (id, template_id, value_field) VALUES (126, 1034, 'PE');
INSERT INTO templates_association (id, template_id, value_field) VALUES (129, 1035, 'PE');
INSERT INTO templates_association (id, template_id, value_field) VALUES (134, 900, 'SP');
INSERT INTO templates_association (id, template_id, value_field) VALUES (135, 901, 'SP');
INSERT INTO templates_association (id, template_id, value_field) VALUES (136, 902, 'SP');
INSERT INTO templates_association (id, template_id, value_field) VALUES (137, 904, 'SP');
INSERT INTO templates_association (id, template_id, value_field) VALUES (138, 905, 'SP');
INSERT INTO templates_association (id, template_id, value_field) VALUES (139, 906, 'SP');
INSERT INTO templates_association (id, template_id, value_field) VALUES (140, 907, 'SP');
INSERT INTO templates_association (id, template_id, value_field) VALUES (141, 908, 'SP');
INSERT INTO templates_association (id, template_id, value_field) VALUES (142, 909, 'SP');
INSERT INTO templates_association (id, template_id, value_field) VALUES (143, 910, 'SP');
INSERT INTO templates_association (id, template_id, value_field) VALUES (144, 1033, 'SP');
INSERT INTO templates_association (id, template_id, value_field) VALUES (145, 1034, 'SP');
INSERT INTO templates_association (id, template_id, value_field) VALUES (148, 1035, 'SP');
INSERT INTO templates_association (id, template_id, value_field) VALUES (153, 900, 'PSO');
INSERT INTO templates_association (id, template_id, value_field) VALUES (154, 901, 'PSO');
INSERT INTO templates_association (id, template_id, value_field) VALUES (155, 902, 'PSO');
INSERT INTO templates_association (id, template_id, value_field) VALUES (156, 904, 'PSO');
INSERT INTO templates_association (id, template_id, value_field) VALUES (157, 905, 'PSO');
INSERT INTO templates_association (id, template_id, value_field) VALUES (158, 906, 'PSO');
INSERT INTO templates_association (id, template_id, value_field) VALUES (159, 907, 'PSO');
INSERT INTO templates_association (id, template_id, value_field) VALUES (160, 908, 'PSO');
INSERT INTO templates_association (id, template_id, value_field) VALUES (161, 909, 'PSO');
INSERT INTO templates_association (id, template_id, value_field) VALUES (162, 910, 'PSO');
INSERT INTO templates_association (id, template_id, value_field) VALUES (163, 1033, 'PSO');
INSERT INTO templates_association (id, template_id, value_field) VALUES (164, 1034, 'PSO');
INSERT INTO templates_association (id, template_id, value_field) VALUES (590, 1041, 'CAB');
INSERT INTO templates_association (id, template_id, value_field) VALUES (591, 1041, 'COR');
INSERT INTO templates_association (id, template_id, value_field) VALUES (167, 1035, 'PSO');
INSERT INTO templates_association (id, template_id, value_field) VALUES (592, 1041, 'FIN');
INSERT INTO templates_association (id, template_id, value_field) VALUES (593, 1041, 'DRH');
INSERT INTO templates_association (id, template_id, value_field) VALUES (594, 1041, 'DSI');
INSERT INTO templates_association (id, template_id, value_field) VALUES (595, 1041, 'DGA');
INSERT INTO templates_association (id, template_id, value_field) VALUES (172, 900, 'PTE');
INSERT INTO templates_association (id, template_id, value_field) VALUES (173, 901, 'PTE');
INSERT INTO templates_association (id, template_id, value_field) VALUES (174, 902, 'PTE');
INSERT INTO templates_association (id, template_id, value_field) VALUES (175, 904, 'PTE');
INSERT INTO templates_association (id, template_id, value_field) VALUES (176, 905, 'PTE');
INSERT INTO templates_association (id, template_id, value_field) VALUES (177, 906, 'PTE');
INSERT INTO templates_association (id, template_id, value_field) VALUES (178, 907, 'PTE');
INSERT INTO templates_association (id, template_id, value_field) VALUES (179, 908, 'PTE');
INSERT INTO templates_association (id, template_id, value_field) VALUES (180, 909, 'PTE');
INSERT INTO templates_association (id, template_id, value_field) VALUES (181, 910, 'PTE');
INSERT INTO templates_association (id, template_id, value_field) VALUES (182, 1033, 'PTE');
INSERT INTO templates_association (id, template_id, value_field) VALUES (183, 1034, 'PTE');
INSERT INTO templates_association (id, template_id, value_field) VALUES (596, 1041, 'DGS');
INSERT INTO templates_association (id, template_id, value_field) VALUES (597, 1041, 'PE');
INSERT INTO templates_association (id, template_id, value_field) VALUES (186, 1035, 'PTE');
INSERT INTO templates_association (id, template_id, value_field) VALUES (598, 1041, 'PCU');
INSERT INTO templates_association (id, template_id, value_field) VALUES (599, 1041, 'PSF');
INSERT INTO templates_association (id, template_id, value_field) VALUES (600, 1041, 'PJS');
INSERT INTO templates_association (id, template_id, value_field) VALUES (601, 1041, 'PJU');
INSERT INTO templates_association (id, template_id, value_field) VALUES (191, 900, 'DRH');
INSERT INTO templates_association (id, template_id, value_field) VALUES (192, 901, 'DRH');
INSERT INTO templates_association (id, template_id, value_field) VALUES (193, 902, 'DRH');
INSERT INTO templates_association (id, template_id, value_field) VALUES (194, 904, 'DRH');
INSERT INTO templates_association (id, template_id, value_field) VALUES (195, 905, 'DRH');
INSERT INTO templates_association (id, template_id, value_field) VALUES (196, 906, 'DRH');
INSERT INTO templates_association (id, template_id, value_field) VALUES (197, 907, 'DRH');
INSERT INTO templates_association (id, template_id, value_field) VALUES (198, 908, 'DRH');
INSERT INTO templates_association (id, template_id, value_field) VALUES (199, 909, 'DRH');
INSERT INTO templates_association (id, template_id, value_field) VALUES (200, 910, 'DRH');
INSERT INTO templates_association (id, template_id, value_field) VALUES (201, 1033, 'DRH');
INSERT INTO templates_association (id, template_id, value_field) VALUES (202, 1034, 'DRH');
INSERT INTO templates_association (id, template_id, value_field) VALUES (602, 1041, 'PSO');
INSERT INTO templates_association (id, template_id, value_field) VALUES (603, 1041, 'PTE');
INSERT INTO templates_association (id, template_id, value_field) VALUES (205, 1035, 'DRH');
INSERT INTO templates_association (id, template_id, value_field) VALUES (604, 1041, 'DSG');
INSERT INTO templates_association (id, template_id, value_field) VALUES (605, 1041, 'COU');
INSERT INTO templates_association (id, template_id, value_field) VALUES (606, 1041, 'SP');
INSERT INTO templates_association (id, template_id, value_field) VALUES (607, 1041, 'VILLE');
INSERT INTO templates_association (id, template_id, value_field) VALUES (210, 900, 'DSG');
INSERT INTO templates_association (id, template_id, value_field) VALUES (211, 901, 'DSG');
INSERT INTO templates_association (id, template_id, value_field) VALUES (212, 902, 'DSG');
INSERT INTO templates_association (id, template_id, value_field) VALUES (213, 904, 'DSG');
INSERT INTO templates_association (id, template_id, value_field) VALUES (214, 905, 'DSG');
INSERT INTO templates_association (id, template_id, value_field) VALUES (215, 906, 'DSG');
INSERT INTO templates_association (id, template_id, value_field) VALUES (216, 907, 'DSG');
INSERT INTO templates_association (id, template_id, value_field) VALUES (217, 908, 'DSG');
INSERT INTO templates_association (id, template_id, value_field) VALUES (218, 909, 'DSG');
INSERT INTO templates_association (id, template_id, value_field) VALUES (219, 910, 'DSG');
INSERT INTO templates_association (id, template_id, value_field) VALUES (220, 1033, 'DSG');
INSERT INTO templates_association (id, template_id, value_field) VALUES (221, 1034, 'DSG');
INSERT INTO templates_association (id, template_id, value_field) VALUES (224, 1035, 'DSG');
INSERT INTO templates_association (id, template_id, value_field) VALUES (229, 900, 'COU');
INSERT INTO templates_association (id, template_id, value_field) VALUES (230, 901, 'COU');
INSERT INTO templates_association (id, template_id, value_field) VALUES (231, 902, 'COU');
INSERT INTO templates_association (id, template_id, value_field) VALUES (232, 904, 'COU');
INSERT INTO templates_association (id, template_id, value_field) VALUES (233, 905, 'COU');
INSERT INTO templates_association (id, template_id, value_field) VALUES (234, 906, 'COU');
INSERT INTO templates_association (id, template_id, value_field) VALUES (235, 907, 'COU');
INSERT INTO templates_association (id, template_id, value_field) VALUES (236, 908, 'COU');
INSERT INTO templates_association (id, template_id, value_field) VALUES (237, 909, 'COU');
INSERT INTO templates_association (id, template_id, value_field) VALUES (238, 910, 'COU');
INSERT INTO templates_association (id, template_id, value_field) VALUES (239, 1033, 'COU');
INSERT INTO templates_association (id, template_id, value_field) VALUES (240, 1034, 'COU');
INSERT INTO templates_association (id, template_id, value_field) VALUES (243, 1035, 'COU');
INSERT INTO templates_association (id, template_id, value_field) VALUES (248, 900, 'COR');
INSERT INTO templates_association (id, template_id, value_field) VALUES (249, 901, 'COR');
INSERT INTO templates_association (id, template_id, value_field) VALUES (250, 902, 'COR');
INSERT INTO templates_association (id, template_id, value_field) VALUES (251, 904, 'COR');
INSERT INTO templates_association (id, template_id, value_field) VALUES (252, 905, 'COR');
INSERT INTO templates_association (id, template_id, value_field) VALUES (253, 906, 'COR');
INSERT INTO templates_association (id, template_id, value_field) VALUES (254, 907, 'COR');
INSERT INTO templates_association (id, template_id, value_field) VALUES (255, 908, 'COR');
INSERT INTO templates_association (id, template_id, value_field) VALUES (256, 909, 'COR');
INSERT INTO templates_association (id, template_id, value_field) VALUES (257, 910, 'COR');
INSERT INTO templates_association (id, template_id, value_field) VALUES (258, 1033, 'COR');
INSERT INTO templates_association (id, template_id, value_field) VALUES (259, 1034, 'COR');
INSERT INTO templates_association (id, template_id, value_field) VALUES (262, 1035, 'COR');
INSERT INTO templates_association (id, template_id, value_field) VALUES (267, 900, 'PSF');
INSERT INTO templates_association (id, template_id, value_field) VALUES (268, 901, 'PSF');
INSERT INTO templates_association (id, template_id, value_field) VALUES (269, 902, 'PSF');
INSERT INTO templates_association (id, template_id, value_field) VALUES (270, 904, 'PSF');
INSERT INTO templates_association (id, template_id, value_field) VALUES (271, 905, 'PSF');
INSERT INTO templates_association (id, template_id, value_field) VALUES (272, 906, 'PSF');
INSERT INTO templates_association (id, template_id, value_field) VALUES (273, 907, 'PSF');
INSERT INTO templates_association (id, template_id, value_field) VALUES (274, 908, 'PSF');
INSERT INTO templates_association (id, template_id, value_field) VALUES (275, 909, 'PSF');
INSERT INTO templates_association (id, template_id, value_field) VALUES (276, 910, 'PSF');
INSERT INTO templates_association (id, template_id, value_field) VALUES (277, 1033, 'PSF');
INSERT INTO templates_association (id, template_id, value_field) VALUES (278, 1034, 'PSF');
INSERT INTO templates_association (id, template_id, value_field) VALUES (281, 1035, 'PSF');
INSERT INTO templates_association (id, template_id, value_field) VALUES (286, 900, 'DSI');
INSERT INTO templates_association (id, template_id, value_field) VALUES (287, 901, 'DSI');
INSERT INTO templates_association (id, template_id, value_field) VALUES (288, 902, 'DSI');
INSERT INTO templates_association (id, template_id, value_field) VALUES (289, 904, 'DSI');
INSERT INTO templates_association (id, template_id, value_field) VALUES (290, 905, 'DSI');
INSERT INTO templates_association (id, template_id, value_field) VALUES (291, 906, 'DSI');
INSERT INTO templates_association (id, template_id, value_field) VALUES (292, 907, 'DSI');
INSERT INTO templates_association (id, template_id, value_field) VALUES (293, 908, 'DSI');
INSERT INTO templates_association (id, template_id, value_field) VALUES (294, 909, 'DSI');
INSERT INTO templates_association (id, template_id, value_field) VALUES (295, 910, 'DSI');
INSERT INTO templates_association (id, template_id, value_field) VALUES (296, 1033, 'DSI');
INSERT INTO templates_association (id, template_id, value_field) VALUES (297, 1034, 'DSI');
INSERT INTO templates_association (id, template_id, value_field) VALUES (300, 1035, 'DSI');
INSERT INTO templates_association (id, template_id, value_field) VALUES (305, 900, 'FIN');
INSERT INTO templates_association (id, template_id, value_field) VALUES (306, 901, 'FIN');
INSERT INTO templates_association (id, template_id, value_field) VALUES (307, 902, 'FIN');
INSERT INTO templates_association (id, template_id, value_field) VALUES (308, 904, 'FIN');
INSERT INTO templates_association (id, template_id, value_field) VALUES (309, 905, 'FIN');
INSERT INTO templates_association (id, template_id, value_field) VALUES (310, 906, 'FIN');
INSERT INTO templates_association (id, template_id, value_field) VALUES (311, 907, 'FIN');
INSERT INTO templates_association (id, template_id, value_field) VALUES (312, 908, 'FIN');
INSERT INTO templates_association (id, template_id, value_field) VALUES (313, 909, 'FIN');
INSERT INTO templates_association (id, template_id, value_field) VALUES (314, 910, 'FIN');
INSERT INTO templates_association (id, template_id, value_field) VALUES (315, 1033, 'FIN');
INSERT INTO templates_association (id, template_id, value_field) VALUES (316, 1034, 'FIN');
INSERT INTO templates_association (id, template_id, value_field) VALUES (608, 20, 'CAB');
INSERT INTO templates_association (id, template_id, value_field) VALUES (609, 20, 'COR');
INSERT INTO templates_association (id, template_id, value_field) VALUES (319, 1035, 'FIN');
INSERT INTO templates_association (id, template_id, value_field) VALUES (610, 20, 'FIN');
INSERT INTO templates_association (id, template_id, value_field) VALUES (611, 20, 'DRH');
INSERT INTO templates_association (id, template_id, value_field) VALUES (612, 20, 'DSI');
INSERT INTO templates_association (id, template_id, value_field) VALUES (613, 20, 'DGA');
INSERT INTO templates_association (id, template_id, value_field) VALUES (324, 900, 'PJU');
INSERT INTO templates_association (id, template_id, value_field) VALUES (325, 901, 'PJU');
INSERT INTO templates_association (id, template_id, value_field) VALUES (326, 902, 'PJU');
INSERT INTO templates_association (id, template_id, value_field) VALUES (327, 904, 'PJU');
INSERT INTO templates_association (id, template_id, value_field) VALUES (328, 905, 'PJU');
INSERT INTO templates_association (id, template_id, value_field) VALUES (329, 906, 'PJU');
INSERT INTO templates_association (id, template_id, value_field) VALUES (330, 907, 'PJU');
INSERT INTO templates_association (id, template_id, value_field) VALUES (331, 908, 'PJU');
INSERT INTO templates_association (id, template_id, value_field) VALUES (332, 909, 'PJU');
INSERT INTO templates_association (id, template_id, value_field) VALUES (333, 910, 'PJU');
INSERT INTO templates_association (id, template_id, value_field) VALUES (334, 1033, 'PJU');
INSERT INTO templates_association (id, template_id, value_field) VALUES (335, 1034, 'PJU');
INSERT INTO templates_association (id, template_id, value_field) VALUES (614, 20, 'DGS');
INSERT INTO templates_association (id, template_id, value_field) VALUES (615, 20, 'ELUS');
INSERT INTO templates_association (id, template_id, value_field) VALUES (338, 1035, 'PJU');
INSERT INTO templates_association (id, template_id, value_field) VALUES (616, 20, 'PE');
INSERT INTO templates_association (id, template_id, value_field) VALUES (617, 20, 'PCU');
INSERT INTO templates_association (id, template_id, value_field) VALUES (618, 20, 'PSF');
INSERT INTO templates_association (id, template_id, value_field) VALUES (619, 20, 'PJS');
INSERT INTO templates_association (id, template_id, value_field) VALUES (343, 900, 'ELUS');
INSERT INTO templates_association (id, template_id, value_field) VALUES (344, 901, 'ELUS');
INSERT INTO templates_association (id, template_id, value_field) VALUES (345, 902, 'ELUS');
INSERT INTO templates_association (id, template_id, value_field) VALUES (346, 904, 'ELUS');
INSERT INTO templates_association (id, template_id, value_field) VALUES (347, 905, 'ELUS');
INSERT INTO templates_association (id, template_id, value_field) VALUES (348, 906, 'ELUS');
INSERT INTO templates_association (id, template_id, value_field) VALUES (349, 907, 'ELUS');
INSERT INTO templates_association (id, template_id, value_field) VALUES (350, 908, 'ELUS');
INSERT INTO templates_association (id, template_id, value_field) VALUES (351, 909, 'ELUS');
INSERT INTO templates_association (id, template_id, value_field) VALUES (352, 910, 'ELUS');
INSERT INTO templates_association (id, template_id, value_field) VALUES (353, 1033, 'ELUS');
INSERT INTO templates_association (id, template_id, value_field) VALUES (354, 1034, 'ELUS');
INSERT INTO templates_association (id, template_id, value_field) VALUES (620, 20, 'PJU');
INSERT INTO templates_association (id, template_id, value_field) VALUES (621, 20, 'PSO');
INSERT INTO templates_association (id, template_id, value_field) VALUES (357, 1035, 'ELUS');
INSERT INTO templates_association (id, template_id, value_field) VALUES (622, 20, 'PTE');
INSERT INTO templates_association (id, template_id, value_field) VALUES (623, 20, 'DSG');
INSERT INTO templates_association (id, template_id, value_field) VALUES (624, 20, 'COU');
INSERT INTO templates_association (id, template_id, value_field) VALUES (625, 20, 'SP');
INSERT INTO templates_association (id, template_id, value_field) VALUES (362, 900, 'CCAS');
INSERT INTO templates_association (id, template_id, value_field) VALUES (363, 901, 'CCAS');
INSERT INTO templates_association (id, template_id, value_field) VALUES (364, 902, 'CCAS');
INSERT INTO templates_association (id, template_id, value_field) VALUES (365, 904, 'CCAS');
INSERT INTO templates_association (id, template_id, value_field) VALUES (366, 905, 'CCAS');
INSERT INTO templates_association (id, template_id, value_field) VALUES (367, 906, 'CCAS');
INSERT INTO templates_association (id, template_id, value_field) VALUES (368, 907, 'CCAS');
INSERT INTO templates_association (id, template_id, value_field) VALUES (369, 908, 'CCAS');
INSERT INTO templates_association (id, template_id, value_field) VALUES (370, 909, 'CCAS');
INSERT INTO templates_association (id, template_id, value_field) VALUES (371, 910, 'CCAS');
INSERT INTO templates_association (id, template_id, value_field) VALUES (372, 1033, 'CCAS');
INSERT INTO templates_association (id, template_id, value_field) VALUES (373, 1034, 'CCAS');
INSERT INTO templates_association (id, template_id, value_field) VALUES (626, 20, 'VILLE');
INSERT INTO templates_association (id, template_id, value_field) VALUES (376, 1035, 'CCAS');
INSERT INTO templates_association (id, template_id, value_field) VALUES (499, 1048, 'CAB');
INSERT INTO templates_association (id, template_id, value_field) VALUES (500, 1048, 'COR');
INSERT INTO templates_association (id, template_id, value_field) VALUES (501, 1048, 'FIN');
INSERT INTO templates_association (id, template_id, value_field) VALUES (502, 1048, 'DRH');
INSERT INTO templates_association (id, template_id, value_field) VALUES (503, 1048, 'DSI');
INSERT INTO templates_association (id, template_id, value_field) VALUES (504, 1048, 'DGA');
INSERT INTO templates_association (id, template_id, value_field) VALUES (505, 1048, 'DGS');
INSERT INTO templates_association (id, template_id, value_field) VALUES (506, 1048, 'ELUS');
INSERT INTO templates_association (id, template_id, value_field) VALUES (507, 1048, 'PE');
INSERT INTO templates_association (id, template_id, value_field) VALUES (508, 1048, 'PCU');
INSERT INTO templates_association (id, template_id, value_field) VALUES (509, 1048, 'PSF');
INSERT INTO templates_association (id, template_id, value_field) VALUES (510, 1048, 'PJS');
INSERT INTO templates_association (id, template_id, value_field) VALUES (511, 1048, 'PJU');
INSERT INTO templates_association (id, template_id, value_field) VALUES (512, 1048, 'PSO');
INSERT INTO templates_association (id, template_id, value_field) VALUES (513, 1048, 'PTE');
INSERT INTO templates_association (id, template_id, value_field) VALUES (514, 1048, 'DSG');
INSERT INTO templates_association (id, template_id, value_field) VALUES (515, 1048, 'COU');
INSERT INTO templates_association (id, template_id, value_field) VALUES (516, 1048, 'SP');
INSERT INTO templates_association (id, template_id, value_field) VALUES (517, 1048, 'VILLE');
INSERT INTO templates_association (id, template_id, value_field) VALUES (518, 1038, 'CAB');
INSERT INTO templates_association (id, template_id, value_field) VALUES (519, 1038, 'COR');
INSERT INTO templates_association (id, template_id, value_field) VALUES (520, 1038, 'FIN');
INSERT INTO templates_association (id, template_id, value_field) VALUES (521, 1038, 'DRH');
INSERT INTO templates_association (id, template_id, value_field) VALUES (522, 1038, 'DSI');
INSERT INTO templates_association (id, template_id, value_field) VALUES (523, 1038, 'DGA');
INSERT INTO templates_association (id, template_id, value_field) VALUES (524, 1038, 'DGS');
INSERT INTO templates_association (id, template_id, value_field) VALUES (525, 1038, 'PE');
INSERT INTO templates_association (id, template_id, value_field) VALUES (526, 1038, 'PCU');
INSERT INTO templates_association (id, template_id, value_field) VALUES (527, 1038, 'PSF');
INSERT INTO templates_association (id, template_id, value_field) VALUES (528, 1038, 'PJS');
INSERT INTO templates_association (id, template_id, value_field) VALUES (529, 1038, 'PJU');
INSERT INTO templates_association (id, template_id, value_field) VALUES (530, 1038, 'PSO');
INSERT INTO templates_association (id, template_id, value_field) VALUES (531, 1038, 'PTE');
INSERT INTO templates_association (id, template_id, value_field) VALUES (532, 1038, 'DSG');
INSERT INTO templates_association (id, template_id, value_field) VALUES (533, 1038, 'COU');
INSERT INTO templates_association (id, template_id, value_field) VALUES (534, 1038, 'SP');
INSERT INTO templates_association (id, template_id, value_field) VALUES (535, 1038, 'VILLE');
INSERT INTO templates_association (id, template_id, value_field) VALUES (536, 1047, 'CAB');
INSERT INTO templates_association (id, template_id, value_field) VALUES (537, 1047, 'COR');
INSERT INTO templates_association (id, template_id, value_field) VALUES (538, 1047, 'FIN');
INSERT INTO templates_association (id, template_id, value_field) VALUES (539, 1047, 'DRH');
INSERT INTO templates_association (id, template_id, value_field) VALUES (540, 1047, 'DSI');
INSERT INTO templates_association (id, template_id, value_field) VALUES (541, 1047, 'DGA');
INSERT INTO templates_association (id, template_id, value_field) VALUES (542, 1047, 'DGS');
INSERT INTO templates_association (id, template_id, value_field) VALUES (543, 1047, 'PE');
INSERT INTO templates_association (id, template_id, value_field) VALUES (544, 1047, 'PCU');
INSERT INTO templates_association (id, template_id, value_field) VALUES (545, 1047, 'PSF');
INSERT INTO templates_association (id, template_id, value_field) VALUES (546, 1047, 'PJS');
INSERT INTO templates_association (id, template_id, value_field) VALUES (547, 1047, 'PJU');
INSERT INTO templates_association (id, template_id, value_field) VALUES (548, 1047, 'PSO');
INSERT INTO templates_association (id, template_id, value_field) VALUES (549, 1047, 'PTE');
INSERT INTO templates_association (id, template_id, value_field) VALUES (550, 1047, 'DSG');
INSERT INTO templates_association (id, template_id, value_field) VALUES (551, 1047, 'COU');
INSERT INTO templates_association (id, template_id, value_field) VALUES (552, 1047, 'SP');
INSERT INTO templates_association (id, template_id, value_field) VALUES (553, 1047, 'VILLE');
INSERT INTO templates_association (id, template_id, value_field) VALUES (554, 1040, 'CAB');
INSERT INTO templates_association (id, template_id, value_field) VALUES (555, 1040, 'COR');
INSERT INTO templates_association (id, template_id, value_field) VALUES (556, 1040, 'FIN');
INSERT INTO templates_association (id, template_id, value_field) VALUES (557, 1040, 'DRH');
INSERT INTO templates_association (id, template_id, value_field) VALUES (558, 1040, 'DSI');
INSERT INTO templates_association (id, template_id, value_field) VALUES (559, 1040, 'DGA');
INSERT INTO templates_association (id, template_id, value_field) VALUES (560, 1040, 'DGS');
INSERT INTO templates_association (id, template_id, value_field) VALUES (561, 1040, 'PE');
INSERT INTO templates_association (id, template_id, value_field) VALUES (562, 1040, 'PCU');
INSERT INTO templates_association (id, template_id, value_field) VALUES (563, 1040, 'PSF');
INSERT INTO templates_association (id, template_id, value_field) VALUES (564, 1040, 'PJS');
INSERT INTO templates_association (id, template_id, value_field) VALUES (565, 1040, 'PJU');
INSERT INTO templates_association (id, template_id, value_field) VALUES (566, 1040, 'PSO');
INSERT INTO templates_association (id, template_id, value_field) VALUES (567, 1040, 'PTE');
INSERT INTO templates_association (id, template_id, value_field) VALUES (568, 1040, 'DSG');
INSERT INTO templates_association (id, template_id, value_field) VALUES (569, 1040, 'COU');
INSERT INTO templates_association (id, template_id, value_field) VALUES (570, 1040, 'SP');
INSERT INTO templates_association (id, template_id, value_field) VALUES (571, 1040, 'VILLE');

INSERT INTO tiles (id, user_id, type, view, "position", color, parameters) VALUES (2, 21, 'myLastResources', 'list', 0, '#80cbc4', '{}');
INSERT INTO tiles (id, user_id, type, view, "position", color, parameters) VALUES (3, 21, 'shortcut', 'summary', 1, '#9fa8da', '{"privilegeId": "admin_tag"}');
INSERT INTO tiles (id, user_id, type, view, "position", color, parameters) VALUES (1, 21, 'basket', 'summary', 3, '#90caf9', '{"groupId": 1, "basketId": 1}');
INSERT INTO tiles (id, user_id, type, view, "position", color, parameters) VALUES (5, 16, 'myLastResources', 'list', 0, '#90caf9', '{"groupId": 2, "basketId": 3}');
INSERT INTO tiles (id, user_id, type, view, "position", color, parameters) VALUES (8, 23, 'searchTemplate', 'chart', 1, '#bcaaa4', '{"chartMode": "creationDate", "chartType": "line", "searchTemplateId": 1}');
INSERT INTO tiles (id, user_id, type, view, "position", color, parameters) VALUES (7, 23, 'searchTemplate', 'chart', 0, '#b0bec5', '{"chartMode": "status", "chartType": "vertical-bar", "searchTemplateId": 1}');
INSERT INTO tiles (id, user_id, type, view, "position", color, parameters) VALUES (9, 18, 'searchTemplate', 'chart', 1, '#ce93d8', '{"chartMode": "destination", "chartType": "pie", "searchTemplateId": 2}');
INSERT INTO tiles (id, user_id, type, view, "position", color, parameters) VALUES (11, 18, 'basket', 'list', 0, '#ef9a9a', '{"groupId": 3, "basketId": 12}');
INSERT INTO tiles (id, user_id, type, view, "position", color, parameters) VALUES (12, 5, 'basket', 'list', 0, '#90caf9', '{"groupId": 8, "basketId": 9}');
INSERT INTO tiles (id, user_id, type, view, "position", color, parameters) VALUES (13, 4, 'myLastResources', 'list', 0, '#90caf9', '{"groupId": 2, "basketId": 3}');
INSERT INTO tiles (id, user_id, type, view, "position", color, parameters) VALUES (14, 4, 'basket', 'summary', 1, '#ffcc80', '{"groupId": 2, "basketId": 17}');
INSERT INTO tiles (id, user_id, type, view, "position", color, parameters) VALUES (16, 17, 'basket', 'chart', 1, '#90caf9', '{"groupId": 4, "basketId": 15, "chartMode": "doctype", "chartType": "pie"}');
INSERT INTO tiles (id, user_id, type, view, "position", color, parameters) VALUES (15, 17, 'basket', 'summary', 0, '#ef9a9a', '{"groupId": 4, "basketId": 16}');
INSERT INTO tiles (id, user_id, type, view, "position", color, parameters) VALUES (17, 10, 'basket', 'summary', 0, '#ef9a9a', '{"groupId": 4, "basketId": 16}');
INSERT INTO tiles (id, user_id, type, view, "position", color, parameters) VALUES (18, 10, 'basket', 'chart', 1, '#b39ddb', '{"groupId": 4, "basketId": 15, "chartMode": "destination", "chartType": "pie"}');
INSERT INTO tiles (id, user_id, type, view, "position", color, parameters) VALUES (19, 10, 'basket', 'chart', 2, '#bcaaa4', '{"groupId": 4, "basketId": 15, "chartMode": "doctype", "chartType": "vertical-bar"}');

INSERT INTO usergroup_content (user_id, group_id, role) VALUES (1, 4, '');
INSERT INTO usergroup_content (user_id, group_id, role) VALUES (1, 7, '');
INSERT INTO usergroup_content (user_id, group_id, role) VALUES (2, 2, '');
INSERT INTO usergroup_content (user_id, group_id, role) VALUES (3, 4, '');
INSERT INTO usergroup_content (user_id, group_id, role) VALUES (4, 2, '');
INSERT INTO usergroup_content (user_id, group_id, role) VALUES (5, 8, '');
INSERT INTO usergroup_content (user_id, group_id, role) VALUES (6, 4, '');
INSERT INTO usergroup_content (user_id, group_id, role) VALUES (7, 4, '');
INSERT INTO usergroup_content (user_id, group_id, role) VALUES (7, 7, '');
INSERT INTO usergroup_content (user_id, group_id, role) VALUES (8, 2, '');
INSERT INTO usergroup_content (user_id, group_id, role) VALUES (9, 2, '');
INSERT INTO usergroup_content (user_id, group_id, role) VALUES (10, 4, '');
INSERT INTO usergroup_content (user_id, group_id, role) VALUES (11, 2, '');
INSERT INTO usergroup_content (user_id, group_id, role) VALUES (12, 2, '');
INSERT INTO usergroup_content (user_id, group_id, role) VALUES (13, 2, '');
INSERT INTO usergroup_content (user_id, group_id, role) VALUES (14, 4, '');
INSERT INTO usergroup_content (user_id, group_id, role) VALUES (15, 2, '');
INSERT INTO usergroup_content (user_id, group_id, role) VALUES (16, 2, '');
INSERT INTO usergroup_content (user_id, group_id, role) VALUES (17, 4, '');
INSERT INTO usergroup_content (user_id, group_id, role) VALUES (18, 1, '');
INSERT INTO usergroup_content (user_id, group_id, role) VALUES (18, 3, '');
INSERT INTO usergroup_content (user_id, group_id, role) VALUES (19, 2, '');
INSERT INTO usergroup_content (user_id, group_id, role) VALUES (20, 2, '');
INSERT INTO usergroup_content (user_id, group_id, role) VALUES (21, 1, '');
INSERT INTO usergroup_content (user_id, group_id, role) VALUES (21, 5, '');
INSERT INTO usergroup_content (user_id, group_id, role) VALUES (22, 10, '');
INSERT INTO usergroup_content (user_id, group_id, role) VALUES (24, 11, '');
INSERT INTO usergroup_content (user_id, group_id, role) VALUES (24, 13, '');

INSERT INTO usergroups (id, group_id, group_desc, can_index, indexation_parameters) VALUES (4, 'RESPONSABLE', 'Manager', true, '{"actions": ["22", "20"], "entities": [], "keywords": ["ALL_ENTITIES"]}');
INSERT INTO usergroups (id, group_id, group_desc, can_index, indexation_parameters) VALUES (5, 'ADMINISTRATEUR_N1', 'Admin. Fonctionnel N1', false, '{"actions": [], "entities": [], "keywords": []}');
INSERT INTO usergroups (id, group_id, group_desc, can_index, indexation_parameters) VALUES (6, 'ADMINISTRATEUR_N2', 'Admin. Fonctionnel N2', false, '{"actions": [], "entities": [], "keywords": []}');
INSERT INTO usergroups (id, group_id, group_desc, can_index, indexation_parameters) VALUES (7, 'DIRECTEUR', 'Directeur', false, '{"actions": [], "entities": [], "keywords": []}');
INSERT INTO usergroups (id, group_id, group_desc, can_index, indexation_parameters) VALUES (8, 'ELU', 'Elu', false, '{"actions": [], "entities": [], "keywords": []}');
INSERT INTO usergroups (id, group_id, group_desc, can_index, indexation_parameters) VALUES (9, 'CABINET', 'Cabinet', false, '{"actions": [], "entities": [], "keywords": []}');
INSERT INTO usergroups (id, group_id, group_desc, can_index, indexation_parameters) VALUES (10, 'ARCHIVISTE', 'Archiviste', false, '{"actions": [], "entities": [], "keywords": []}');
INSERT INTO usergroups (id, group_id, group_desc, can_index, indexation_parameters) VALUES (11, 'MAARCHTOGEC', 'Envoi dématérialisé', false, '{"actions": [], "entities": [], "keywords": []}');
INSERT INTO usergroups (id, group_id, group_desc, can_index, indexation_parameters) VALUES (12, 'SERVICE', 'Service', false, '{"actions": [], "entities": [], "keywords": []}');
INSERT INTO usergroups (id, group_id, group_desc, can_index, indexation_parameters) VALUES (13, 'WEBSERVICE', 'Utilisateurs de WebService', true, '{"actions": ["22", "20"], "entities": [], "keywords": ["ALL_ENTITIES"]}');
INSERT INTO usergroups (id, group_id, group_desc, can_index, indexation_parameters) VALUES (1, 'COURRIER', 'Opérateur de numérisation', true, '{"actions": ["21", "22"], "entities": [], "keywords": ["ALL_ENTITIES"]}');
INSERT INTO usergroups (id, group_id, group_desc, can_index, indexation_parameters) VALUES (3, 'RESP_COURRIER', 'Superviseur Courrier', true, '{"actions": ["21", "22"], "entities": [], "keywords": ["ALL_ENTITIES"]}');
INSERT INTO usergroups (id, group_id, group_desc, can_index, indexation_parameters) VALUES (2, 'AGENT', 'Utilisateur', true, '{"actions": ["22", "414", "20"], "entities": [], "keywords": ["ALL_ENTITIES"]}');

INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('COURRIER', 'admin', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('COURRIER', 'adv_search_mlb', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('COURRIER', 'create_contacts', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('COURRIER', 'update_contacts', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('COURRIER', 'update_status_mail', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('COURRIER', 'view_technical_infos', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('COURRIER', 'view_doc_history', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('COURRIER', 'view_full_history', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('COURRIER', 'add_links', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('COURRIER', 'edit_resource', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('COURRIER', 'update_diffusion_indexing', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('COURRIER', 'update_diffusion_details', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('COURRIER', 'entities_print_sep_mlb', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('COURRIER', 'sendmail', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('COURRIER', 'use_mail_services', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('COURRIER', 'admin_registered_mail', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('COURRIER', 'include_folders_and_followed_resources_perimeter', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('COURRIER', 'manage_attachments', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('COURRIER', 'view_documents_with_notes', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('COURRIER', 'add_new_version', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('COURRIER', 'view_version_letterbox', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('COURRIER', 'print_folder_doc', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('COURRIER', 'manage_tags_application', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('COURRIER', 'private_tag', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('COURRIER', '_print_sep', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('COURRIER', 'physical_archive_print_sep_mlb', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('COURRIER', 'manage_numeric_package', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('AGENT', 'adv_search_mlb', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('AGENT', 'create_contacts', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('AGENT', 'update_contacts', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('AGENT', 'view_doc_history', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('AGENT', 'add_links', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('AGENT', 'update_diffusion_indexing', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('AGENT', 'update_diffusion_details', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('AGENT', 'sendmail', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('AGENT', 'use_mail_services', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('AGENT', 'include_folders_and_followed_resources_perimeter', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('AGENT', 'manage_attachments', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('AGENT', 'view_documents_with_notes', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('AGENT', 'add_new_version', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('AGENT', 'view_version_letterbox', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('AGENT', 'config_visa_workflow', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('AGENT', 'config_visa_workflow_in_detail', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('AGENT', 'print_folder_doc', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('AGENT', 'config_avis_workflow', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('AGENT', 'config_avis_workflow_in_detail', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('AGENT', 'private_tag', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('AGENT', 'manage_numeric_package', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('AGENT', 'add_correspondent_in_shared_groups_on_profile', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('RESP_COURRIER', 'adv_search_mlb', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('RESP_COURRIER', 'create_contacts', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('RESP_COURRIER', 'update_contacts', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('RESP_COURRIER', 'view_doc_history', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('RESP_COURRIER', 'view_full_history', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('RESP_COURRIER', 'add_links', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('RESP_COURRIER', 'edit_resource', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('RESP_COURRIER', 'update_diffusion_indexing', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('RESP_COURRIER', 'update_diffusion_details', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('RESP_COURRIER', 'update_diffusion_process', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('RESP_COURRIER', 'sendmail', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('RESP_COURRIER', 'use_mail_services', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('RESP_COURRIER', 'admin_registered_mail', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('RESP_COURRIER', 'include_folders_and_followed_resources_perimeter', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('RESP_COURRIER', 'view_documents_with_notes', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('RESP_COURRIER', 'add_new_version', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('RESP_COURRIER', 'view_version_letterbox', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('RESP_COURRIER', 'sign_document', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('RESP_COURRIER', 'visa_documents', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('RESP_COURRIER', 'print_folder_doc', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('RESP_COURRIER', 'private_tag', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('RESP_COURRIER', 'manage_numeric_package', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('RESPONSABLE', 'adv_search_mlb', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('RESPONSABLE', 'create_contacts', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('RESPONSABLE', 'update_contacts', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('RESPONSABLE', 'view_doc_history', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('RESPONSABLE', 'add_links', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('RESPONSABLE', 'update_diffusion_indexing', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('RESPONSABLE', 'update_diffusion_details', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('RESPONSABLE', 'sendmail', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('RESPONSABLE', 'use_mail_services', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('RESPONSABLE', 'include_folders_and_followed_resources_perimeter', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('RESPONSABLE', 'manage_attachments', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('RESPONSABLE', 'view_documents_with_notes', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('RESPONSABLE', 'add_new_version', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('RESPONSABLE', 'view_version_letterbox', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('RESPONSABLE', 'config_visa_workflow', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('RESPONSABLE', 'config_visa_workflow_in_detail', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('RESPONSABLE', 'sign_document', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('RESPONSABLE', 'visa_documents', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('RESPONSABLE', 'modify_visa_in_signatureBook', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('RESPONSABLE', 'print_folder_doc', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('RESPONSABLE', 'config_avis_workflow', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('RESPONSABLE', 'config_avis_workflow_in_detail', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('RESPONSABLE', 'avis_documents', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('RESPONSABLE', 'private_tag', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('RESPONSABLE', 'manage_numeric_package', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('RESPONSABLE', 'add_correspondent_in_shared_groups_on_profile', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('ADMINISTRATEUR_N1', 'admin', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('ADMINISTRATEUR_N1', 'adv_search_mlb', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('ADMINISTRATEUR_N1', 'admin_groups', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('ADMINISTRATEUR_N1', 'admin_architecture', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('ADMINISTRATEUR_N1', 'view_history', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('ADMINISTRATEUR_N1', 'view_history_batch', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('ADMINISTRATEUR_N1', 'admin_status', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('ADMINISTRATEUR_N1', 'admin_actions', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('ADMINISTRATEUR_N1', 'admin_contacts', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('ADMINISTRATEUR_N1', 'admin_indexing_models', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('ADMINISTRATEUR_N1', 'admin_custom_fields', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('ADMINISTRATEUR_N1', 'create_contacts', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('ADMINISTRATEUR_N1', 'update_contacts', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('ADMINISTRATEUR_N1', 'update_status_mail', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('ADMINISTRATEUR_N1', 'view_technical_infos', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('ADMINISTRATEUR_N1', 'view_doc_history', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('ADMINISTRATEUR_N1', 'view_full_history', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('ADMINISTRATEUR_N1', 'add_links', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('ADMINISTRATEUR_N1', 'admin_parameters', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('ADMINISTRATEUR_N1', 'admin_priorities', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('ADMINISTRATEUR_N1', 'edit_resource', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('ADMINISTRATEUR_N1', 'admin_email_server', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('ADMINISTRATEUR_N1', 'admin_shippings', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('ADMINISTRATEUR_N1', 'admin_baskets', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('ADMINISTRATEUR_N1', 'manage_entities', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('ADMINISTRATEUR_N1', 'admin_difflist_types', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('ADMINISTRATEUR_N1', 'admin_listmodels', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('ADMINISTRATEUR_N1', 'update_diffusion_indexing', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('ADMINISTRATEUR_N1', 'update_diffusion_details', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('ADMINISTRATEUR_N1', 'update_diffusion_process', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('ADMINISTRATEUR_N1', 'entities_print_sep_mlb', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('ADMINISTRATEUR_N1', 'sendmail', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('ADMINISTRATEUR_N1', 'use_mail_services', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('ADMINISTRATEUR_N1', 'admin_registered_mail', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('ADMINISTRATEUR_N1', 'include_folders_and_followed_resources_perimeter', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('ADMINISTRATEUR_N1', 'admin_alfresco', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('ADMINISTRATEUR_N1', 'admin_search', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('ADMINISTRATEUR_N1', 'manage_attachments', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('ADMINISTRATEUR_N1', 'view_documents_with_notes', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('ADMINISTRATEUR_N1', 'add_new_version', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('ADMINISTRATEUR_N1', 'view_version_letterbox', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('ADMINISTRATEUR_N1', 'config_visa_workflow', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('ADMINISTRATEUR_N1', 'config_visa_workflow_in_detail', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('ADMINISTRATEUR_N1', 'print_folder_doc', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('ADMINISTRATEUR_N1', 'config_avis_workflow', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('ADMINISTRATEUR_N1', 'admin_templates', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('ADMINISTRATEUR_N1', 'admin_tag', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('ADMINISTRATEUR_N1', 'manage_tags_application', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('ADMINISTRATEUR_N1', 'private_tag', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('ADMINISTRATEUR_N1', 'admin_notif', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('ADMINISTRATEUR_N1', '_print_sep', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('ADMINISTRATEUR_N1', 'physical_archive_print_sep_mlb', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('ADMINISTRATEUR_N1', 'physical_archive_batch_manage', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('ADMINISTRATEUR_N1', 'admin_life_cycle', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('ADMINISTRATEUR_N1', 'add_correspondent_in_shared_groups_on_profile', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('ADMINISTRATEUR_N2', 'admin', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('ADMINISTRATEUR_N2', 'view_doc_history', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('ADMINISTRATEUR_N2', 'view_full_history', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('ADMINISTRATEUR_N2', 'edit_resource', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('ADMINISTRATEUR_N2', 'include_folders_and_followed_resources_perimeter', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('ADMINISTRATEUR_N2', 'admin_templates', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('ADMINISTRATEUR_N2', 'admin_tag', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('ELU', 'include_folders_and_followed_resources_perimeter', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('ELU', 'sign_document', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('ELU', 'visa_documents', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('ELU', 'avis_documents', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('ARCHIVISTE', 'adv_search_mlb', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('ARCHIVISTE', 'create_contacts', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('ARCHIVISTE', 'update_contacts', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('ARCHIVISTE', 'view_technical_infos', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('ARCHIVISTE', 'view_doc_history', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('ARCHIVISTE', 'view_full_history', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('ARCHIVISTE', 'sendmail', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('ARCHIVISTE', 'include_folders_and_followed_resources_perimeter', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('ARCHIVISTE', 'avis_documents', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('ARCHIVISTE', 'export_seda_view', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('MAARCHTOGEC', 'include_folders_and_followed_resources_perimeter', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('MAARCHTOGEC', 'manage_numeric_package', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('WEBSERVICE', 'include_folders_and_followed_resources_perimeter', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('ADMINISTRATEUR_N1', 'admin_users', '{"groups": [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13]}');
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('ADMINISTRATEUR_N1', 'manage_personal_data', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('AGENT', 'edit_resource', NULL);
INSERT INTO usergroups_services (group_id, service_id, parameters) VALUES ('RESPONSABLE', 'update_diffusion_process', NULL);

INSERT INTO users (id, user_id, password, firstname, lastname, phone, mail, initials, preferences, status, password_modification_date, mode, refresh_token, reset_token, failed_authentication, locked_until, authorized_api, external_id, feature_tour) VALUES (5, 'ddur', '$2y$10$C.QSslBKD3yNMfRPuZfcaubFwPKiCkqqOUyAdOr5FSGKPaePwuEjG', 'Dominique', 'DUR', NULL, 'yourEmail@domain.com', NULL, '{"documentEdition": "onlyoffice"}', 'OK', '2021-03-24 10:17:02.66594', 'standard', '["eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJleHAiOjE2MTcyOTMxNjMsInVzZXIiOnsiaWQiOjV9fQ.-xHs1HNXaF04o5inFZeCOSOuodW8vrsPcVqsaiPnNww", "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJleHAiOjE2MTcyOTMzMzgsInVzZXIiOnsiaWQiOjV9fQ.jY7jDCHyojZScH2FEDst825Rk3-M1ZspQXg0P5ZwQfQ"]', NULL, 0, NULL, '[]', '{}', '[]');
INSERT INTO users (id, user_id, password, firstname, lastname, phone, mail, initials, preferences, status, password_modification_date, mode, refresh_token, reset_token, failed_authentication, locked_until, authorized_api, external_id, feature_tour) VALUES (1, 'rrenaud', '$2y$10$C.QSslBKD3yNMfRPuZfcaubFwPKiCkqqOUyAdOr5FSGKPaePwuEjG', 'Robert', 'RENAUD', NULL, 'yourEmail@domain.com', NULL, '{"documentEdition": "onlyoffice"}', 'OK', '2021-03-24 10:17:02.66594', 'standard', '[]', NULL, 0, NULL, '[]', '{}', '[]');
INSERT INTO users (id, user_id, password, firstname, lastname, phone, mail, initials, preferences, status, password_modification_date, mode, refresh_token, reset_token, failed_authentication, locked_until, authorized_api, external_id, feature_tour) VALUES (2, 'ccordy', '$2y$10$C.QSslBKD3yNMfRPuZfcaubFwPKiCkqqOUyAdOr5FSGKPaePwuEjG', 'Chloé', 'CORDY', NULL, 'yourEmail@domain.com', NULL, '{"documentEdition": "onlyoffice"}', 'OK', '2021-03-24 10:17:02.66594', 'standard', '[]', NULL, 0, NULL, '[]', '{}', '[]');
INSERT INTO users (id, user_id, password, firstname, lastname, phone, mail, initials, preferences, status, password_modification_date, mode, refresh_token, reset_token, failed_authentication, locked_until, authorized_api, external_id, feature_tour) VALUES (3, 'ssissoko', '$2y$10$C.QSslBKD3yNMfRPuZfcaubFwPKiCkqqOUyAdOr5FSGKPaePwuEjG', 'Sylvain', 'SISSOKO', NULL, 'yourEmail@domain.com', NULL, '{"documentEdition": "onlyoffice"}', 'OK', '2021-03-24 10:17:02.66594', 'standard', '[]', NULL, 0, NULL, '[]', '{}', '[]');
INSERT INTO users (id, user_id, password, firstname, lastname, phone, mail, initials, preferences, status, password_modification_date, mode, refresh_token, reset_token, failed_authentication, locked_until, authorized_api, external_id, feature_tour) VALUES (7, 'eerina', '$2y$10$C.QSslBKD3yNMfRPuZfcaubFwPKiCkqqOUyAdOr5FSGKPaePwuEjG', 'Edith', 'ERINA', NULL, 'yourEmail@domain.com', NULL, '{"documentEdition": "onlyoffice"}', 'OK', '2021-03-24 10:17:02.66594', 'standard', '[]', NULL, 0, NULL, '[]', '{}', '[]');
INSERT INTO users (id, user_id, password, firstname, lastname, phone, mail, initials, preferences, status, password_modification_date, mode, refresh_token, reset_token, failed_authentication, locked_until, authorized_api, external_id, feature_tour) VALUES (8, 'kkaar', '$2y$10$C.QSslBKD3yNMfRPuZfcaubFwPKiCkqqOUyAdOr5FSGKPaePwuEjG', 'Katy', 'KAAR', NULL, 'yourEmail@domain.com', NULL, '{"documentEdition": "onlyoffice"}', 'OK', '2021-03-24 10:17:02.66594', 'standard', '[]', NULL, 0, NULL, '[]', '{}', '[]');
INSERT INTO users (id, user_id, password, firstname, lastname, phone, mail, initials, preferences, status, password_modification_date, mode, refresh_token, reset_token, failed_authentication, locked_until, authorized_api, external_id, feature_tour) VALUES (9, 'bboule', '$2y$10$C.QSslBKD3yNMfRPuZfcaubFwPKiCkqqOUyAdOr5FSGKPaePwuEjG', 'Bruno', 'BOULE', NULL, 'yourEmail@domain.com', NULL, '{"documentEdition": "onlyoffice"}', 'OK', '2021-03-24 10:17:02.66594', 'standard', '[]', NULL, 0, NULL, '[]', '{}', '[]');
INSERT INTO users (id, user_id, password, firstname, lastname, phone, mail, initials, preferences, status, password_modification_date, mode, refresh_token, reset_token, failed_authentication, locked_until, authorized_api, external_id, feature_tour) VALUES (11, 'aackermann', '$2y$10$C.QSslBKD3yNMfRPuZfcaubFwPKiCkqqOUyAdOr5FSGKPaePwuEjG', 'Amanda', 'ACKERMANN', NULL, 'yourEmail@domain.com', NULL, '{"documentEdition": "onlyoffice"}', 'OK', '2021-03-24 10:17:02.66594', 'standard', '[]', NULL, 0, NULL, '[]', '{}', '[]');
INSERT INTO users (id, user_id, password, firstname, lastname, phone, mail, initials, preferences, status, password_modification_date, mode, refresh_token, reset_token, failed_authentication, locked_until, authorized_api, external_id, feature_tour) VALUES (12, 'ppruvost', '$2y$10$C.QSslBKD3yNMfRPuZfcaubFwPKiCkqqOUyAdOr5FSGKPaePwuEjG', 'Pierre', 'PRUVOST', NULL, 'yourEmail@domain.com', NULL, '{"documentEdition": "onlyoffice"}', 'OK', '2021-03-24 10:17:02.66594', 'standard', '[]', NULL, 0, NULL, '[]', '{}', '[]');
INSERT INTO users (id, user_id, password, firstname, lastname, phone, mail, initials, preferences, status, password_modification_date, mode, refresh_token, reset_token, failed_authentication, locked_until, authorized_api, external_id, feature_tour) VALUES (13, 'ttong', '$2y$10$C.QSslBKD3yNMfRPuZfcaubFwPKiCkqqOUyAdOr5FSGKPaePwuEjG', 'Tony', 'TONG', NULL, 'yourEmail@domain.com', NULL, '{"documentEdition": "onlyoffice"}', 'OK', '2021-03-24 10:17:02.66594', 'standard', '[]', NULL, 0, NULL, '[]', '{}', '[]');
INSERT INTO users (id, user_id, password, firstname, lastname, phone, mail, initials, preferences, status, password_modification_date, mode, refresh_token, reset_token, failed_authentication, locked_until, authorized_api, external_id, feature_tour) VALUES (14, 'sstar', '$2y$10$C.QSslBKD3yNMfRPuZfcaubFwPKiCkqqOUyAdOr5FSGKPaePwuEjG', 'Suzanne', 'STAR', NULL, 'yourEmail@domain.com', NULL, '{"documentEdition": "onlyoffice"}', 'OK', '2021-03-24 10:17:02.66594', 'standard', '[]', NULL, 0, NULL, '[]', '{}', '[]');
INSERT INTO users (id, user_id, password, firstname, lastname, phone, mail, initials, preferences, status, password_modification_date, mode, refresh_token, reset_token, failed_authentication, locked_until, authorized_api, external_id, feature_tour) VALUES (15, 'ssaporta', '$2y$10$C.QSslBKD3yNMfRPuZfcaubFwPKiCkqqOUyAdOr5FSGKPaePwuEjG', 'Sabrina', 'SAPORTA', NULL, 'yourEmail@domain.com', NULL, '{"documentEdition": "onlyoffice"}', 'OK', '2021-03-24 10:17:02.66594', 'standard', '[]', NULL, 0, NULL, '[]', '{}', '[]');
INSERT INTO users (id, user_id, password, firstname, lastname, phone, mail, initials, preferences, status, password_modification_date, mode, refresh_token, reset_token, failed_authentication, locked_until, authorized_api, external_id, feature_tour) VALUES (10, 'ppetit', '$2y$10$C.QSslBKD3yNMfRPuZfcaubFwPKiCkqqOUyAdOr5FSGKPaePwuEjG', 'Patricia', 'PETIT', NULL, 'yourEmail@domain.com', NULL, '{"documentEdition": "onlyoffice"}', 'OK', '2021-03-24 10:17:02.66594', 'standard', '["eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJleHAiOjE2MTczNzI2MTksInVzZXIiOnsiaWQiOjEwfX0.Ae49KoDeVxlFwVo4ET3nhuVt5syqsZT_f00-M1pEfFI", "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJleHAiOjE2MTczODM0NDMsInVzZXIiOnsiaWQiOjEwfX0.G8Mw5nN-TkOZcRbWkmbhPqKCAPb3IHEJUkvLgcUMelE"]', NULL, 0, NULL, '[]', '{"maarchParapheur": 10}', '[]');
INSERT INTO users (id, user_id, password, firstname, lastname, phone, mail, initials, preferences, status, password_modification_date, mode, refresh_token, reset_token, failed_authentication, locked_until, authorized_api, external_id, feature_tour) VALUES (20, 'jjonasz', '$2y$10$C.QSslBKD3yNMfRPuZfcaubFwPKiCkqqOUyAdOr5FSGKPaePwuEjG', 'Jean', 'JONASZ', NULL, 'yourEmail@domain.com', NULL, '{"documentEdition": "onlyoffice"}', 'OK', '2021-03-24 10:17:02.66594', 'standard', '[]', NULL, 0, NULL, '[]', '{}', '[]');
INSERT INTO users (id, user_id, password, firstname, lastname, phone, mail, initials, preferences, status, password_modification_date, mode, refresh_token, reset_token, failed_authentication, locked_until, authorized_api, external_id, feature_tour) VALUES (22, 'ggrand', '$2y$10$C.QSslBKD3yNMfRPuZfcaubFwPKiCkqqOUyAdOr5FSGKPaePwuEjG', 'Georges', 'GRAND', NULL, 'yourEmail@domain.com', NULL, '{"documentEdition": "onlyoffice"}', 'OK', '2021-03-24 10:17:02.66594', 'standard', '[]', NULL, 0, NULL, '[]', '{}', '[]');
INSERT INTO users (id, user_id, password, firstname, lastname, phone, mail, initials, preferences, status, password_modification_date, mode, refresh_token, reset_token, failed_authentication, locked_until, authorized_api, external_id, feature_tour) VALUES (6, 'jjane', '$2y$10$C.QSslBKD3yNMfRPuZfcaubFwPKiCkqqOUyAdOr5FSGKPaePwuEjG', 'Jenny', 'JANE', NULL, 'yourEmail@domain.com', NULL, '{"documentEdition": "onlyoffice"}', 'OK', '2021-03-24 10:17:02.66594', 'standard', '[]', NULL, 0, NULL, '[]', '{"maarchParapheur": 13}', '[]');
INSERT INTO users (id, user_id, password, firstname, lastname, phone, mail, initials, preferences, status, password_modification_date, mode, refresh_token, reset_token, failed_authentication, locked_until, authorized_api, external_id, feature_tour) VALUES (17, 'mmanfred', '$2y$10$C.QSslBKD3yNMfRPuZfcaubFwPKiCkqqOUyAdOr5FSGKPaePwuEjG', 'Martin', 'MANFRED', NULL, 'yourEmail@domain.com', NULL, '{"documentEdition": "onlyoffice"}', 'OK', '2021-03-24 10:17:02.66594', 'standard', '["eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJleHAiOjE2MTczNzE1NTMsInVzZXIiOnsiaWQiOjE3fX0.eEecd_WTB_6vi8VbaVa2K7fpezUcfZ3ERRrGPw-_u8E", "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJleHAiOjE2MTczNzIwMzgsInVzZXIiOnsiaWQiOjE3fX0.C0onpkSOTdb0wBooyiGv3k3rHMgGPK6XDZ9DhiZAtfk", "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJleHAiOjE2MTczODI1MDgsInVzZXIiOnsiaWQiOjE3fX0.pnx3TTituAWYRfDrOBRe1w1HHnVFRi_qpM5WB4px13U"]', NULL, 0, NULL, '[]', '{"maarchParapheur": 12}', '[]');
INSERT INTO users (id, user_id, password, firstname, lastname, phone, mail, initials, preferences, status, password_modification_date, mode, refresh_token, reset_token, failed_authentication, locked_until, authorized_api, external_id, feature_tour) VALUES (24, 'cchaplin', '$2y$10$C.QSslBKD3yNMfRPuZfcaubFwPKiCkqqOUyAdOr5FSGKPaePwuEjG', 'Charlie', 'CHAPLIN', NULL, 'yourEmail@domain.com', NULL, '{"documentEdition": "onlyoffice"}', 'OK', '2021-03-24 10:17:02.66594', 'rest', '[]', NULL, 0, NULL, '[]', '{}', '[]');
INSERT INTO users (id, user_id, password, firstname, lastname, phone, mail, initials, preferences, status, password_modification_date, mode, refresh_token, reset_token, failed_authentication, locked_until, authorized_api, external_id, feature_tour) VALUES (4, 'nnataly', '$2y$10$C.QSslBKD3yNMfRPuZfcaubFwPKiCkqqOUyAdOr5FSGKPaePwuEjG', 'Nancy', 'NATALY', '01 47 24 51 59', 'yourEmail@domain.com', 'NNA', '{"documentEdition": "onlyoffice"}', 'OK', '2021-03-24 10:17:02.66594', 'standard', '["eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJleHAiOjE2MTczNzgwMjgsInVzZXIiOnsiaWQiOjR9fQ.I35ctVjGU98nEVZF58YfjF6O0rJ-9kBKuxfaQB33500", "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJleHAiOjE2MTczNzg2OTYsInVzZXIiOnsiaWQiOjR9fQ.MFPGLYw0movM2-9u7OU5Ps1v3ydT78il2p5wOspD4_E", "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJleHAiOjE2MTczNzg3NzcsInVzZXIiOnsiaWQiOjR9fQ.2GXoECa6AGRzFIrjx724Ie2ClpOmcVN5hcM8q6Wmhxk", "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJleHAiOjE2MTczNzkzNTcsInVzZXIiOnsiaWQiOjR9fQ.pyi9SB-2XVjN6NwF2Ogf-C3s76bXsZxpfALfTlAkHZY", "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJleHAiOjE2MTczODEwOTcsInVzZXIiOnsiaWQiOjR9fQ.UKS-X2axMLc383ox6V8iSkeTdU-iLFRqxUMjkr6yGzE", "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJleHAiOjE2MTczODExOTUsInVzZXIiOnsiaWQiOjR9fQ.pPum2U9YxvyNpHbX8gql_5WVqBsjjuFsJyUSY2G_jqk", "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJleHAiOjE2MTczODE0NzksInVzZXIiOnsiaWQiOjR9fQ.aFWso_KzJI_KM4gQ-o0TcR9gyYkyGLPRvpVSgIMvBq0", "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJleHAiOjE2MTczODE3ODksInVzZXIiOnsiaWQiOjR9fQ.JV1dS4l_XQ_lysG6fiifDbz9_F5Z8FCoEfkwQk33G44", "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJleHAiOjE2MTczODIxMTQsInVzZXIiOnsiaWQiOjR9fQ.wLG4aGupayZ2zaNwXd_jhJ-mANae_fc0Bx5tUguiOlI", "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJleHAiOjE2MTczODIyNTYsInVzZXIiOnsiaWQiOjR9fQ.FYE8o4kg4EBiIx08_X514TQ0gGBRs8YPWWh0QPIJ3bg", "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJleHAiOjE2MTczODM0MjMsInVzZXIiOnsiaWQiOjR9fQ.G0Uj3cJd8DOcqi1vtV63S9nBYTsT0sgjXiv49DmIm8Q"]', NULL, 0, NULL, '[]', '{}', '[]');
INSERT INTO users (id, user_id, password, firstname, lastname, phone, mail, initials, preferences, status, password_modification_date, mode, refresh_token, reset_token, failed_authentication, locked_until, authorized_api, external_id, feature_tour) VALUES (19, 'bbain', '$2y$10$C.QSslBKD3yNMfRPuZfcaubFwPKiCkqqOUyAdOr5FSGKPaePwuEjG', 'Barbara', 'BAIN', NULL, 'yourEmail@domain.com', NULL, '{"documentEdition": "onlyoffice"}', 'OK', '2021-03-24 10:17:02.66594', 'standard', '["eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJleHAiOjE2MTcxOTc1MTMsInVzZXIiOnsiaWQiOjE5fX0.3JD_K31gj_Cpzg2fDPv3ikwY8bEXHXHAgiPxBNP7Lks", "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJleHAiOjE2MTcxOTgxOTMsInVzZXIiOnsiaWQiOjE5fX0.4IazyqfSoU_-kgOijesVIgwlfW6Zr1yofv2aOE9gZNM", "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJleHAiOjE2MTcyMDE2NjUsInVzZXIiOnsiaWQiOjE5fX0.2noUw3WeSigdUK_y0a8O8edoOI97lPLh0VLs2zZ4zqA", "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJleHAiOjE2MTcyNzAxOTksInVzZXIiOnsiaWQiOjE5fX0.reqaQ_wBiJ9GEMMqqeSHyPRXoHGemU8pWh2QOygo4ic", "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJleHAiOjE2MTcyNzE3NzUsInVzZXIiOnsiaWQiOjE5fX0.bIG_XRegpDHdvT1Bqgkmw6yBB_KoNAk6an-Bmo4Sey0"]', NULL, 0, NULL, '[]', '{}', '[]');
INSERT INTO users (id, user_id, password, firstname, lastname, phone, mail, initials, preferences, status, password_modification_date, mode, refresh_token, reset_token, failed_authentication, locked_until, authorized_api, external_id, feature_tour) VALUES (18, 'ddaull', '$2y$10$C.QSslBKD3yNMfRPuZfcaubFwPKiCkqqOUyAdOr5FSGKPaePwuEjG', 'Denis', 'DAULL', NULL, 'yourEmail@domain.com', 'DDE', '{"documentEdition": "onlyoffice"}', 'OK', '2021-03-24 10:17:02.66594', 'standard', '["eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJleHAiOjE2MTcyNzQyNzgsInVzZXIiOnsiaWQiOjE4fX0.jjGCRbta3QIJekhRITCShxGSM_iXTSG9N3kijeZhpcE", "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJleHAiOjE2MTcyNzQ3MjksInVzZXIiOnsiaWQiOjE4fX0.NM69b97AA4Q3fNhKdliSl2ZDw8N8JJXqmsh7I3wfhrE", "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJleHAiOjE2MTcyNzUzODUsInVzZXIiOnsiaWQiOjE4fX0.lF9yhGCoC9W-Hafdp0Ll6wrC1fQEJ7qw6h1y92U0gik", "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJleHAiOjE2MTcyODIyOTksInVzZXIiOnsiaWQiOjE4fX0.y7dTpSBDHYKaX0y_mWd4zY5xNuoj0cMYLcH2Nr8g6ds"]', NULL, 0, NULL, '[]', '{}', '[]');
INSERT INTO users (id, user_id, password, firstname, lastname, phone, mail, initials, preferences, status, password_modification_date, mode, refresh_token, reset_token, failed_authentication, locked_until, authorized_api, external_id, feature_tour) VALUES (21, 'bblier', '$2y$10$C.QSslBKD3yNMfRPuZfcaubFwPKiCkqqOUyAdOr5FSGKPaePwuEjG', 'Bernard', 'BLIER', NULL, 'yourEmail@domain.com', NULL, '{"documentEdition": "onlyoffice"}', 'OK', '2021-03-24 10:17:02.66594', 'standard', '["eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJleHAiOjE2MTcxOTc1MjgsInVzZXIiOnsiaWQiOjIxfX0.plGMlETEhHn_OySZhYOsDhbiXbn6CG9yv8teMXURDz0", "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJleHAiOjE2MTcxOTc2MzgsInVzZXIiOnsiaWQiOjIxfX0.8fxQFdyselx4IFRDaBBDFr3k3PlG66IMAaX5HrnQYfA", "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJleHAiOjE2MTcxOTgyMDksInVzZXIiOnsiaWQiOjIxfX0.Q06K2dqzzSRUHapFcPGMud-FwZpwKC0B8zLsMZ0VFEM", "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJleHAiOjE2MTcxOTgyODgsInVzZXIiOnsiaWQiOjIxfX0.zIdDXtyl0rYZEuSbNwFbo6yw5NtqNnWiNKGX5dGhZY0", "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJleHAiOjE2MTcxOTgzODQsInVzZXIiOnsiaWQiOjIxfX0.e0bKwQY8KJtfpKWZA92gdqrGAeRpdIRjUu0YoONpu4k", "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJleHAiOjE2MTcyMDE2ODEsInVzZXIiOnsiaWQiOjIxfX0.GCPm6k81zdjHwSk_SdivRduSyQEBzEM3R_zGlrZNtdg", "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJleHAiOjE2MTczODI3NzksInVzZXIiOnsiaWQiOjIxfX0.zc1Ua1yDsIn_mAFTBFKNA0fqp2B2yPBqg-jkkdy54cw", "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJleHAiOjE2MTczODI4NzksInVzZXIiOnsiaWQiOjIxfX0.3p1rQntbBkj0QQol_7c1s6Yt6UOs3iXD8XMgZ9wU7AE", "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJleHAiOjE2MTczODMyOTMsInVzZXIiOnsiaWQiOjIxfX0.3AQOob8RWZN26xvb7cKZoBK5UUECcgqFhAw_kedDBoM", "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJleHAiOjE2MTczODQ2OTgsInVzZXIiOnsiaWQiOjIxfX0.NrjZGTE3CnItgkeKA9VpZv-egRMhfHEKHkTVRe_kR8E", "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJleHAiOjE2MTczODQ5NTksInVzZXIiOnsiaWQiOjIxfX0.3W8l3CxftN-nZft758ovsTEXfK3CCZTY_4UxpqzD-IE"]', NULL, 0, NULL, '[]', '{}', '[]');
INSERT INTO users (id, user_id, password, firstname, lastname, phone, mail, initials, preferences, status, password_modification_date, mode, refresh_token, reset_token, failed_authentication, locked_until, authorized_api, external_id, feature_tour) VALUES (23, 'superadmin', '$2y$10$Vq244c5s2zmldjblmMXEN./Q2qZrqtGVgrbz/l1WfsUJbLco4E.e.', 'Super', 'ADMIN', '0147245159', 'yourEmail@domain.com', NULL, '{"documentEdition": "onlyoffice"}', 'OK', '2021-03-24 10:17:02.66594', 'root_invisible', '["eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJleHAiOjE2MTczODEwMzAsInVzZXIiOnsiaWQiOjIzfX0.UGeJdAGK2JitQExrK8jLDxJHnsRW6XyAqEM2Z_J9h4s", "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJleHAiOjE2MTczODExNjIsInVzZXIiOnsiaWQiOjIzfX0.JP6VaQfSz4UE2O-jt6LtUV8rYUJoDtAR2QErDVk9WNU", "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJleHAiOjE2MTczODE0MjcsInVzZXIiOnsiaWQiOjIzfX0.3tGHE94biLKFaCw2RTv8BbevYIEAcyMkwX3DC-6dD2o", "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJleHAiOjE2MTczODE3MTUsInVzZXIiOnsiaWQiOjIzfX0.gTSVd0W7Awo7r7BcrJx2Nv6QMua0mhRY6eiDV0Akc3A", "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJleHAiOjE2MTczODIwMDgsInVzZXIiOnsiaWQiOjIzfX0._obgnYje053-ulJ1YgLC9bRYjGJRebmMzUzHB7yAsCc", "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJleHAiOjE2MTczODIxNjQsInVzZXIiOnsiaWQiOjIzfX0.IvZdVDqsndzee3P4bUSanPQh7PIMs97JhNHQ6ciK7Gw", "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJleHAiOjE2MTczODIyODgsInVzZXIiOnsiaWQiOjIzfX0.zGZVL_oyxX9Q8IXLom3RebJK58taPaMLXfIaZyJonW4", "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJleHAiOjE2MTczODI2MjcsInVzZXIiOnsiaWQiOjIzfX0.8ar9hfUbzbt04hOq_Ms_45L-SbyhzNEJ9YlMve7rxbY", "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJleHAiOjE2MTczODI2NzksInVzZXIiOnsiaWQiOjIzfX0.XydXfRUonUrrurE7UVUD_e7slUVPPCIGbHwRkVAe4jE", "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJleHAiOjE2MTczODI4NTIsInVzZXIiOnsiaWQiOjIzfX0.K9cEBVjVf80yQRnD3TSGtArig68WThM_cAILxhDUo1U", "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJleHAiOjE2MTczODMyNjIsInVzZXIiOnsiaWQiOjIzfX0.71s6sivewtg1PLvm606YGjA3sUUKRH0wqlH65PFX0BA"]', NULL, 0, NULL, '[]', '{}', '["welcome", "email", "notification"]');
INSERT INTO users (id, user_id, password, firstname, lastname, phone, mail, initials, preferences, status, password_modification_date, mode, refresh_token, reset_token, failed_authentication, locked_until, authorized_api, external_id, feature_tour) VALUES (16, 'ccharles', '$2y$10$C.QSslBKD3yNMfRPuZfcaubFwPKiCkqqOUyAdOr5FSGKPaePwuEjG', 'Charlotte', 'CHARLES', '01 47 24 51 59', 'yourEmail@domain.com', 'CCH', '{"documentEdition": "onlyoffice"}', 'OK', '2021-03-24 10:17:02.66594', 'standard', '["eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJleHAiOjE2MTcyOTMwNTMsInVzZXIiOnsiaWQiOjE2fX0._rHmsXcO-0vRCsW3G3XL71w4VDjIMebe4aSphm0vOcA", "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJleHAiOjE2MTcyOTM5MjksInVzZXIiOnsiaWQiOjE2fX0.eiW-ayIAUkqa-UIbc6nTNGJgxqRUqC3df8P5QXNpPoY", "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJleHAiOjE2MTczNTMxNDYsInVzZXIiOnsiaWQiOjE2fX0.srfJhsaLYx3dKhdqxXKtpCtPbN39L4HvdmNZ7oFMhZk", "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJleHAiOjE2MTczNTMxNjYsInVzZXIiOnsiaWQiOjE2fX0.IHXvQhJ55loCeOVbnAQYEAdijsL27ldalGje2XQLOD8", "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJleHAiOjE2MTczNTYyNDYsInVzZXIiOnsiaWQiOjE2fX0.5sNOhAqGGve7Yw9z2h3rH8Gfrnr1TQhgXmBYmaVbRSw", "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJleHAiOjE2MTczNTcyMTUsInVzZXIiOnsiaWQiOjE2fX0.oxVhj7NNXf0DJq53cKKpXuSqxwop9jYJRkWH9UD5HlM", "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJleHAiOjE2MTczNTc0MTAsInVzZXIiOnsiaWQiOjE2fX0.XWeCumwk3I4sQOIhsrGiIxU6LaZ7EIUWMwYg95Y5qjU", "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJleHAiOjE2MTczNTc2MDIsInVzZXIiOnsiaWQiOjE2fX0.uPLIIIQqZoVc7UlNhj-DWKsbHzO_bJmsNPSPLlf3CQA", "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJleHAiOjE2MTczNTc2OTksInVzZXIiOnsiaWQiOjE2fX0.rFsF0YNxAZDR7ATQ0F-irDVwWi10TL-mO520jKl5siM", "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJleHAiOjE2MTczNzg3NDMsInVzZXIiOnsiaWQiOjE2fX0.U9Jv6X7ol87FyshBaGszRCpYVKSLWAID9YXX5CV15NU", "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJleHAiOjE2MTczODM0MDUsInVzZXIiOnsiaWQiOjE2fX0.-kGf7-jXxJ47Q-CKq9ihFxZjZdhhMBmCChSdQh7-3YI"]', NULL, 0, NULL, '[]', '{}', '[]');

INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (1, 1, 4, 'SendToSignatoryBook', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (2, 1, 4, 'EenvBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (3, 1, 4, 'SuiviParafBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (4, 1, 4, 'ParafBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (5, 1, 4, 'DepartmentBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (6, 1, 4, 'MyBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (7, 1, 4, 'RetAvisBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (8, 1, 4, 'SupAvisBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (9, 1, 4, 'DdeAvisBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (10, 1, 4, 'CopyMailBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (13, 2, 2, 'Maileva_Sended', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (14, 2, 2, 'AR_AlreadySend', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (15, 2, 2, 'AR_Create', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (16, 2, 2, 'SendToSignatoryBook', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (17, 2, 2, 'EenvBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (18, 2, 2, 'SuiviParafBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (19, 2, 2, 'LateMailBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (20, 2, 2, 'MyBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (21, 2, 2, 'RetAvisBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (22, 2, 2, 'SupAvisBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (23, 2, 2, 'DdeAvisBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (24, 2, 2, 'CopyMailBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (25, 3, 4, 'SendToSignatoryBook', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (26, 3, 4, 'EenvBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (27, 3, 4, 'SuiviParafBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (28, 3, 4, 'ParafBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (29, 3, 4, 'DepartmentBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (30, 3, 4, 'MyBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (31, 3, 4, 'RetAvisBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (32, 3, 4, 'SupAvisBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (33, 3, 4, 'DdeAvisBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (34, 3, 4, 'CopyMailBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (38, 4, 2, 'AR_AlreadySend', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (39, 4, 2, 'AR_Create', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (41, 4, 2, 'EenvBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (42, 4, 2, 'SuiviParafBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (43, 4, 2, 'LateMailBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (44, 4, 2, 'MyBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (45, 4, 2, 'RetAvisBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (46, 4, 2, 'SupAvisBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (47, 4, 2, 'DdeAvisBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (48, 4, 2, 'CopyMailBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (49, 5, 8, 'MyBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (50, 5, 8, 'DdeAvisBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (51, 6, 4, 'SendToSignatoryBook', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (52, 6, 4, 'EenvBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (53, 6, 4, 'SuiviParafBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (54, 6, 4, 'ParafBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (55, 6, 4, 'DepartmentBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (56, 6, 4, 'MyBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (57, 6, 4, 'RetAvisBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (58, 6, 4, 'SupAvisBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (59, 6, 4, 'DdeAvisBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (60, 6, 4, 'CopyMailBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (61, 7, 4, 'SendToSignatoryBook', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (62, 7, 4, 'EenvBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (63, 7, 4, 'SuiviParafBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (64, 7, 4, 'ParafBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (65, 7, 4, 'DepartmentBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (66, 7, 4, 'MyBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (67, 7, 4, 'RetAvisBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (68, 7, 4, 'SupAvisBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (69, 7, 4, 'DdeAvisBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (70, 7, 4, 'CopyMailBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (73, 8, 2, 'Maileva_Sended', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (74, 8, 2, 'AR_AlreadySend', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (75, 8, 2, 'AR_Create', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (76, 8, 2, 'SendToSignatoryBook', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (77, 8, 2, 'EenvBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (78, 8, 2, 'SuiviParafBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (79, 8, 2, 'LateMailBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (80, 8, 2, 'MyBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (81, 8, 2, 'RetAvisBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (82, 8, 2, 'SupAvisBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (83, 8, 2, 'DdeAvisBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (84, 8, 2, 'CopyMailBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (87, 9, 2, 'Maileva_Sended', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (88, 9, 2, 'AR_AlreadySend', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (89, 9, 2, 'AR_Create', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (90, 9, 2, 'SendToSignatoryBook', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (91, 9, 2, 'EenvBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (92, 9, 2, 'SuiviParafBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (93, 9, 2, 'LateMailBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (94, 9, 2, 'MyBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (95, 9, 2, 'RetAvisBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (96, 9, 2, 'SupAvisBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (97, 9, 2, 'DdeAvisBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (98, 9, 2, 'CopyMailBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (101, 10, 4, 'SuiviParafBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (102, 10, 4, 'ParafBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (103, 10, 4, 'DepartmentBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (105, 10, 4, 'RetAvisBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (106, 10, 4, 'SupAvisBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (107, 10, 4, 'DdeAvisBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (108, 10, 4, 'CopyMailBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (111, 11, 2, 'Maileva_Sended', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (112, 11, 2, 'AR_AlreadySend', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (113, 11, 2, 'AR_Create', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (114, 11, 2, 'SendToSignatoryBook', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (115, 11, 2, 'EenvBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (116, 11, 2, 'SuiviParafBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (117, 11, 2, 'LateMailBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (118, 11, 2, 'MyBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (119, 11, 2, 'RetAvisBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (120, 11, 2, 'SupAvisBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (121, 11, 2, 'DdeAvisBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (122, 11, 2, 'CopyMailBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (125, 12, 2, 'Maileva_Sended', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (126, 12, 2, 'AR_AlreadySend', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (127, 12, 2, 'AR_Create', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (128, 12, 2, 'SendToSignatoryBook', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (129, 12, 2, 'EenvBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (130, 12, 2, 'SuiviParafBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (131, 12, 2, 'LateMailBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (132, 12, 2, 'MyBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (133, 12, 2, 'RetAvisBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (134, 12, 2, 'SupAvisBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (135, 12, 2, 'DdeAvisBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (136, 12, 2, 'CopyMailBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (139, 13, 2, 'Maileva_Sended', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (140, 13, 2, 'AR_AlreadySend', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (141, 13, 2, 'AR_Create', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (142, 13, 2, 'SendToSignatoryBook', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (143, 13, 2, 'EenvBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (144, 13, 2, 'SuiviParafBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (145, 13, 2, 'LateMailBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (146, 13, 2, 'MyBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (147, 13, 2, 'RetAvisBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (148, 13, 2, 'SupAvisBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (149, 13, 2, 'DdeAvisBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (150, 13, 2, 'CopyMailBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (151, 14, 4, 'SendToSignatoryBook', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (152, 14, 4, 'EenvBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (153, 14, 4, 'SuiviParafBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (154, 14, 4, 'ParafBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (155, 14, 4, 'DepartmentBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (156, 14, 4, 'MyBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (157, 14, 4, 'RetAvisBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (158, 14, 4, 'SupAvisBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (159, 14, 4, 'DdeAvisBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (160, 14, 4, 'CopyMailBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (163, 15, 2, 'Maileva_Sended', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (164, 15, 2, 'AR_AlreadySend', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (165, 15, 2, 'AR_Create', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (166, 15, 2, 'SendToSignatoryBook', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (167, 15, 2, 'EenvBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (168, 15, 2, 'SuiviParafBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (169, 15, 2, 'LateMailBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (170, 15, 2, 'MyBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (171, 15, 2, 'RetAvisBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (172, 15, 2, 'SupAvisBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (173, 15, 2, 'DdeAvisBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (174, 15, 2, 'CopyMailBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (177, 16, 2, 'Maileva_Sended', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (178, 16, 2, 'AR_AlreadySend', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (179, 16, 2, 'AR_Create', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (180, 16, 2, 'SendToSignatoryBook', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (181, 16, 2, 'EenvBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (182, 16, 2, 'SuiviParafBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (183, 16, 2, 'LateMailBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (184, 16, 2, 'MyBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (185, 16, 2, 'RetAvisBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (186, 16, 2, 'SupAvisBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (187, 16, 2, 'DdeAvisBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (188, 16, 2, 'CopyMailBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (189, 17, 4, 'SendToSignatoryBook', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (190, 17, 4, 'EenvBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (191, 17, 4, 'SuiviParafBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (192, 17, 4, 'ParafBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (193, 17, 4, 'DepartmentBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (194, 17, 4, 'MyBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (195, 17, 4, 'RetAvisBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (196, 17, 4, 'SupAvisBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (197, 17, 4, 'DdeAvisBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (198, 17, 4, 'CopyMailBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (199, 18, 1, 'NumericBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (200, 18, 1, 'RetourCourrier', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (201, 18, 1, 'QualificationBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (202, 18, 3, 'ValidationBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (205, 19, 2, 'Maileva_Sended', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (206, 19, 2, 'AR_AlreadySend', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (207, 19, 2, 'AR_Create', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (208, 19, 2, 'SendToSignatoryBook', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (209, 19, 2, 'EenvBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (210, 19, 2, 'SuiviParafBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (211, 19, 2, 'LateMailBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (212, 19, 2, 'MyBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (213, 19, 2, 'RetAvisBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (214, 19, 2, 'SupAvisBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (215, 19, 2, 'DdeAvisBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (216, 19, 2, 'CopyMailBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (219, 20, 2, 'Maileva_Sended', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (220, 20, 2, 'AR_AlreadySend', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (221, 20, 2, 'AR_Create', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (222, 20, 2, 'SendToSignatoryBook', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (223, 20, 2, 'EenvBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (224, 20, 2, 'SuiviParafBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (225, 20, 2, 'LateMailBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (226, 20, 2, 'MyBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (227, 20, 2, 'RetAvisBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (228, 20, 2, 'SupAvisBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (229, 20, 2, 'DdeAvisBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (230, 20, 2, 'CopyMailBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (231, 21, 1, 'NumericBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (232, 21, 1, 'RetourCourrier', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (233, 21, 1, 'QualificationBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (234, 22, 10, 'AckArcBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (235, 22, 10, 'SentArcBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (236, 22, 10, 'ToArcBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (237, 10, 4, 'EenvBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (238, 10, 4, 'MyBasket', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (251, 2, 2, 'outlook_mails', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (252, 4, 2, 'outlook_mails', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (253, 8, 2, 'outlook_mails', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (254, 9, 2, 'outlook_mails', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (255, 11, 2, 'outlook_mails', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (256, 12, 2, 'outlook_mails', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (257, 13, 2, 'outlook_mails', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (258, 15, 2, 'outlook_mails', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (259, 16, 2, 'outlook_mails', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (260, 19, 2, 'outlook_mails', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (261, 20, 2, 'outlook_mails', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (262, 1, 7, 'outlook_mails', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (263, 7, 7, 'outlook_mails', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (264, 1, 4, 'outlook_mails', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (265, 3, 4, 'outlook_mails', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (266, 6, 4, 'outlook_mails', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (267, 7, 4, 'outlook_mails', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (268, 10, 4, 'outlook_mails', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (269, 14, 4, 'outlook_mails', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (270, 17, 4, 'outlook_mails', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (271, 18, 1, 'outlook_mails', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (272, 21, 1, 'outlook_mails', true, NULL);
INSERT INTO users_baskets_preferences (id, user_serial_id, group_serial_id, basket_id, display, color) VALUES (273, 18, 3, 'outlook_mails', true, NULL);

INSERT INTO users_entities (user_id, entity_id, user_role, primary_entity) VALUES (1, 'DGS', '', 'Y');
INSERT INTO users_entities (user_id, entity_id, user_role, primary_entity) VALUES (2, 'DSI', '', 'Y');
INSERT INTO users_entities (user_id, entity_id, user_role, primary_entity) VALUES (3, 'DSI', '', 'Y');
INSERT INTO users_entities (user_id, entity_id, user_role, primary_entity) VALUES (5, 'ELUS', '', 'Y');
INSERT INTO users_entities (user_id, entity_id, user_role, primary_entity) VALUES (6, 'CCAS', '', 'Y');
INSERT INTO users_entities (user_id, entity_id, user_role, primary_entity) VALUES (7, 'CAB', '', 'Y');
INSERT INTO users_entities (user_id, entity_id, user_role, primary_entity) VALUES (8, 'DGA', '', 'Y');
INSERT INTO users_entities (user_id, entity_id, user_role, primary_entity) VALUES (9, 'PCU', '', 'Y');
INSERT INTO users_entities (user_id, entity_id, user_role, primary_entity) VALUES (10, 'VILLE', '', 'Y');
INSERT INTO users_entities (user_id, entity_id, user_role, primary_entity) VALUES (11, 'PSF', '', 'Y');
INSERT INTO users_entities (user_id, entity_id, user_role, primary_entity) VALUES (12, 'DRH', '', 'Y');
INSERT INTO users_entities (user_id, entity_id, user_role, primary_entity) VALUES (13, 'SP', '', 'Y');
INSERT INTO users_entities (user_id, entity_id, user_role, primary_entity) VALUES (14, 'FIN', '', 'Y');
INSERT INTO users_entities (user_id, entity_id, user_role, primary_entity) VALUES (15, 'PE', '', 'Y');
INSERT INTO users_entities (user_id, entity_id, user_role, primary_entity) VALUES (17, 'DGA', '', 'Y');
INSERT INTO users_entities (user_id, entity_id, user_role, primary_entity) VALUES (19, 'PJS', '', 'Y');
INSERT INTO users_entities (user_id, entity_id, user_role, primary_entity) VALUES (20, 'PJU', '', 'Y');
INSERT INTO users_entities (user_id, entity_id, user_role, primary_entity) VALUES (22, 'COR', '', 'Y');
INSERT INTO users_entities (user_id, entity_id, user_role, primary_entity) VALUES (24, 'VILLE', '', 'Y');
INSERT INTO users_entities (user_id, entity_id, user_role, primary_entity) VALUES (23, 'VILLE', '', 'Y');
INSERT INTO users_entities (user_id, entity_id, user_role, primary_entity) VALUES (23, 'CCAS', '', 'N');
INSERT INTO users_entities (user_id, entity_id, user_role, primary_entity) VALUES (21, 'COU', 'Agent service courrier', 'Y');
INSERT INTO users_entities (user_id, entity_id, user_role, primary_entity) VALUES (16, 'PTE', 'Responsable de pôle', 'Y');
INSERT INTO users_entities (user_id, entity_id, user_role, primary_entity) VALUES (18, 'DSG', 'Superviseur courrier', 'Y');
INSERT INTO users_entities (user_id, entity_id, user_role, primary_entity) VALUES (4, 'PSO', 'Responsable Pole Social', 'Y');

------------
-- ENTITIES_FOLDERS
------------
TRUNCATE TABLE entities_folders;
INSERT INTO entities_folders (folder_id, entity_id, edition)
SELECT folders.id, entities.id, false
FROM folders, entities
WHERE 1=1; 


SELECT setval('acknowledgement_receipts_id_seq', (SELECT max(id)+1 FROM acknowledgement_receipts), false);
SELECT setval('actions_id_seq', (SELECT max(id)+1 FROM actions), false);
SELECT setval('address_sectors_id_seq', (SELECT max(id)+1 FROM address_sectors), false);
SELECT setval('adr_attachments_id_seq', (SELECT max(id)+1 FROM adr_attachments), false);
SELECT setval('adr_letterbox_id_seq', (SELECT max(id)+1 FROM adr_letterbox), false);
SELECT setval('attachment_types_id_seq', (SELECT max(id)+1 FROM attachment_types), false);
SELECT setval('baskets_id_seq', (SELECT max(id)+1 FROM baskets), false);
SELECT setval('blacklist_id_seq', (SELECT max(id)+1 FROM blacklist), false);
SELECT setval('configurations_id_seq', (SELECT max(id)+1 FROM configurations), false);
SELECT setval('contacts_civilities_id_seq', (SELECT max(id)+1 FROM contacts_civilities), false);
SELECT setval('contacts_custom_fields_list_id_seq', (SELECT max(id)+1 FROM contacts_custom_fields_list), false);
SELECT setval('contacts_filling_id_seq', (SELECT max(id)+1 FROM contacts_filling), false);
SELECT setval('contacts_groups_id_seq', (SELECT max(id)+1 FROM contacts_groups), false);
SELECT setval('contacts_groups_lists_id_seq', (SELECT max(id)+1 FROM contacts_groups_lists), false);
SELECT setval('contacts_id_seq', (SELECT max(id)+1 FROM contacts), false);
SELECT setval('contacts_parameters_id_seq', (SELECT max(id)+1 FROM contacts_parameters), false);
SELECT setval('custom_fields_id_seq', (SELECT max(id)+1 FROM custom_fields), false);
SELECT setval('docservers_id_seq', (SELECT max(id)+1 FROM docservers), false);
SELECT setval('doctypes_first_level_id_seq', (SELECT max(doctypes_first_level_id)+1 FROM doctypes_first_level), false);
SELECT setval('doctypes_second_level_id_seq', (SELECT max(doctypes_second_level_id)+1 FROM doctypes_second_level), false);
SELECT setval('doctypes_type_id_seq', (SELECT max(type_id)+1 FROM doctypes), false);
SELECT setval('emails_id_seq', (SELECT max(id)+1 FROM emails), false);
SELECT setval('entities_folders_id_seq', (SELECT max(id)+1 FROM entities_folders), false);
SELECT setval('entities_id_seq', (SELECT max(id)+1 FROM entities), false);
SELECT setval('exports_templates_id_seq', (SELECT max(id)+1 FROM exports_templates), false);
SELECT setval('folders_id_seq', (SELECT max(id)+1 FROM folders), false);
SELECT setval('groupbasket_id_seq', (SELECT max(id)+1 FROM groupbasket), false);
SELECT setval('groupbasket_redirect_system_id_seq', (SELECT max(system_id)+1 FROM groupbasket_redirect), false);
SELECT setval('history_batch_id_seq', (SELECT max(batch_id)+1 FROM history_batch), false);
SELECT setval('history_id_seq', (SELECT max(id)+1 FROM history), false);
SELECT setval('indexing_models_fields_id_seq', (SELECT max(id)+1 FROM indexing_models_fields), false);
SELECT setval('indexing_models_id_seq', (SELECT max(id)+1 FROM indexing_models), false);
SELECT setval('list_templates_id_seq', (SELECT max(id)+1 FROM list_templates), false);
SELECT setval('list_templates_items_id_seq', (SELECT max(id)+1 FROM list_templates_items), false);
SELECT setval('listinstance_history_details_id_seq', (SELECT max(listinstance_history_details_id)+1 FROM listinstance_history_details), false);
SELECT setval('listinstance_history_id_seq', (SELECT max(listinstance_history_id)+1 FROM listinstance_history), false);
SELECT setval('listinstance_id_seq', (SELECT max(listinstance_id)+1 FROM listinstance), false);
SELECT setval('notes_entities_id_seq', (SELECT max(id)+1 FROM note_entities), false);
SELECT setval('notes_id_seq', (SELECT max(id)+1 FROM notes), false);
SELECT setval('notif_email_stack_seq', (SELECT max(email_stack_sid)+1 FROM notif_email_stack), false);
SELECT setval('notif_event_stack_seq', (SELECT max(event_stack_sid)+1 FROM notif_event_stack), false);
SELECT setval('notifications_seq', (SELECT max(notification_sid)+1 FROM notifications), false);
SELECT setval('password_history_id_seq', (SELECT max(id)+1 FROM password_history), false);
SELECT setval('password_rules_id_seq', (SELECT max(id)+1 FROM password_rules), false);
SELECT setval('redirected_baskets_id_seq', (SELECT max(id)+1 FROM redirected_baskets), false);
SELECT setval('registered_mail_issuing_sites_entities_id_seq', (SELECT max(id)+1 FROM registered_mail_issuing_sites_entities), false);
SELECT setval('registered_mail_issuing_sites_id_seq', (SELECT max(id)+1 FROM registered_mail_issuing_sites), false);
SELECT setval('registered_mail_number_range_id_seq', (SELECT max(id)+1 FROM registered_mail_number_range), false);
SELECT setval('registered_mail_resources_id_seq', (SELECT max(id)+1 FROM registered_mail_resources), false);
SELECT setval('res_attachment_res_id_seq', (SELECT max(res_id)+1 FROM res_attachments), false);
SELECT setval('res_id_mlb_seq', (SELECT max(res_id)+1 FROM res_letterbox), false);
SELECT setval('resource_contacts_id_seq', (SELECT max(id)+1 FROM resource_contacts), false);
SELECT setval('resources_folders_id_seq', (SELECT max(id)+1 FROM resources_folders), false);
SELECT setval('resources_tags_id_seq', (SELECT max(id)+1 FROM resources_tags), false);
SELECT setval('search_templates_id_seq', (SELECT max(id)+1 FROM search_templates), false);
SELECT setval('security_security_id_seq', (SELECT max(security_id)+1 FROM security), false);
SELECT setval('shipping_templates_id_seq', (SELECT max(id)+1 FROM shipping_templates), false);
SELECT setval('shippings_id_seq', (SELECT max(id)+1 FROM shippings), false);
SELECT setval('status_identifier_seq', (SELECT max(identifier)+1 FROM status), false);
SELECT setval('status_images_id_seq', (SELECT max(id)+1 FROM status_images), false);
SELECT setval('tags_id_seq', (SELECT max(id)+1 FROM tags), false);
SELECT setval('templates_association_id_seq', (SELECT max(id)+1 FROM templates_association), false);
SELECT setval('templates_seq', (SELECT max(template_id)+1 FROM templates), false);
SELECT setval('tiles_id_seq', (SELECT max(id)+1 FROM tiles), false);
SELECT setval('user_signatures_id_seq', (SELECT max(id)+1 FROM user_signatures), false);
SELECT setval('usergroups_id_seq', (SELECT max(id)+1 FROM usergroups), false);
SELECT setval('users_baskets_preferences_id_seq', (SELECT max(id)+1 FROM users_baskets_preferences), false);
SELECT setval('users_email_signatures_id_seq', (SELECT max(id)+1 FROM users_email_signatures), false);
SELECT setval('users_followed_resources_id_seq', (SELECT max(id)+1 FROM users_followed_resources), false);
SELECT setval('users_id_seq', (SELECT max(id)+1 FROM users), false);
SELECT setval('users_pinned_folders_id_seq', (SELECT max(id)+1 FROM users_pinned_folders), false);
