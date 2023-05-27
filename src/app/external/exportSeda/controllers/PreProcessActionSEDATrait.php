<?php

/**
* Copyright Maarch since 2008 under licence GPLv3.
* See LICENCE.txt file at the root folder for more details.
* This file is part of Maarch software.

* @brief   PreProcessActionSEDATrait
* @author  dev <dev@maarch.org>
* @ingroup core
*/

namespace ExportSeda\controllers;

use Action\controllers\PreProcessActionController;
use Action\models\ActionModel;
use Attachment\models\AttachmentModel;
use Configuration\models\ConfigurationModel;
use Docserver\models\DocserverModel;
use Docserver\models\DocserverTypeModel;
use Doctype\models\DoctypeModel;
use Entity\models\EntityModel;
use MessageExchange\models\MessageExchangeModel;
use Parameter\models\ParameterModel;
use Resource\controllers\ResController;
use Resource\controllers\ResourceListController;
use Resource\controllers\StoreController;
use Resource\models\ResModel;
use Respect\Validation\Validator;
use Slim\Http\Request;
use Slim\Http\Response;
use SrcCore\models\CoreConfigModel;

trait PreProcessActionSEDATrait
{
    public function checkSendToRecordManagement(Request $request, Response $response, array $aArgs)
    {
        $body = $request->getParsedBody();

        if (!Validator::arrayType()->notEmpty()->validate($body['resources'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body resources is empty or not an array']);
        }

        $errors = ResourceListController::listControl(['groupId' => $aArgs['groupId'], 'userId' => $aArgs['userId'], 'basketId' => $aArgs['basketId'], 'currentUserId' => $GLOBALS['id']]);
        if (!empty($errors['errors'])) {
            return $response->withStatus($errors['code'])->withJson(['errors' => $errors['errors']]);
        }

        $body['resources'] = array_slice($body['resources'], 0, 500);
        if (!ResController::hasRightByResId(['resId' => $body['resources'], 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Document out of perimeter']);
        }

        $resourcesInformations = ['success' => [], 'errors' => []];
        $body['resources'] = PreProcessActionController::getNonLockedResources(['resources' => $body['resources'], 'userId' => $GLOBALS['id']]);

        // Common Data
        $resources = ResModel::get([
            'select' => ['res_id', 'destination', 'type_id', 'subject', 'linked_resources', 'retention_frozen', 'binding', 'creation_date', 'alt_identifier', 'docserver_id', 'path', 'filename', 'version', 'fingerprint'],
            'where'  => ['res_id in (?)'],
            'data'   => [$body['resources']]
        ]);
        $resources      = array_column($resources, null, 'res_id');
        $doctypesId     = array_column($resources, 'type_id');
        $destinationsId = array_column($resources, 'destination');

        $doctypes = DoctypeModel::get([
            'select' => ['type_id', 'description', 'duration_current_use', 'retention_rule', 'action_current_use', 'retention_final_disposition'],
            'where'  => ['type_id in (?)'],
            'data'   => [$doctypesId]
        ]);
        $doctypesData = array_column($doctypes, null, 'type_id');

        $attachments = AttachmentModel::get([
            'select' => ['res_id_master', 'res_id', 'title', 'creation_date', 'identifier', 'attachment_type'],
            'where'  => ['res_id_master in (?)', 'status in (?)'],
            'data'   => [$body['resources'], ['A_TRA', 'TRA']]
        ]);
        $resAcknowledgement = [];
        $resAttachments = [];
        foreach ($attachments as $attachment) {
            if (in_array($attachment['attachment_type'], ['acknowledgement_record_management', 'reply_record_management'])) {
                $resAcknowledgement[$attachment['res_id_master']] = $attachment['res_id'];
            } else {
                $resAttachments[$attachment['res_id_master']][] = $attachment;
            }
        }

        $entities = EntityModel::get([
            'select' => ['producer_service', 'entity_label', 'entity_id'],
            'where'  => ['entity_id in (?)'],
            'data'   => [$destinationsId]
        ]);
        $destinationsData = array_column($entities, null, 'entity_id');

        $bindingDocument    = ParameterModel::getById(['select' => ['param_value_string'], 'id' => 'bindingDocumentFinalAction']);
        $nonBindingDocument = ParameterModel::getById(['select' => ['param_value_string'], 'id' => 'nonBindingDocumentFinalAction']);

        $configuration = ConfigurationModel::getByPrivilege(['privilege' => 'admin_export_seda']);
        $config = [];
        $config['exportSeda'] = !empty($configuration['value']) ? json_decode($configuration['value'], true) : [];
        if (empty($config['exportSeda']['senderOrgRegNumber'])) {
            return $response->withStatus(400)->withJson(['errors' => 'No senderOrgRegNumber defined in parameters', 'lang' => 'noSenderOrgRegNumber']);
        }
        if (empty($config['exportSeda']['accessRuleCode'])) {
            return $response->withStatus(400)->withJson(['errors' => 'No accessRuleCode defined in parameters', 'lang' => 'noAccessRuleCode']);
        }

        $producerService = '';
        foreach ($destinationsData as $value) {
            if (!empty($value['producer_service'])) {
                if (!empty($producerService) && $producerService != $value['producer_service']) {
                    return $response->withStatus(400)->withJson(['errors' => 'All producer services are not the same', 'lang' => 'differentProducerServices']);
                }
                $producerService = $value['producer_service'];
            }
        }
        if (empty($producerService)) {
            return $response->withStatus(400)->withJson(['errors' => 'No accessRuleCode found in config.json', 'lang' => 'noProducerService']);
        }

        $archivalAgreements = SedaController::getArchivalAgreements([
            'config'              => $config,
            'senderArchiveEntity' => $config['exportSeda']['senderOrgRegNumber'],
            'producerService'     => $producerService
        ]);
        if (!empty($archivalAgreements['errors'])) {
            return $response->withStatus(400)->withJson($archivalAgreements);
        }
        $recipientArchiveEntities = SedaController::getRecipientArchiveEntities(['config' => $config, 'archivalAgreements' => $archivalAgreements['archivalAgreements']]);
        if (!empty($recipientArchiveEntities['errors'])) {
            return $response->withStatus(400)->withJson($recipientArchiveEntities);
        }

        $resourcesInformations['archivalAgreements']       = $archivalAgreements['archivalAgreements'];
        $resourcesInformations['recipientArchiveEntities'] = $recipientArchiveEntities['archiveEntities'];
        $resourcesInformations['senderArchiveEntity']      = $config['exportSeda']['senderOrgRegNumber'];

        $massAction = count($body['resources']) > 1;
        if ($massAction) {
            $action = ActionModel::getById(['id' => $aArgs['actionId'], 'select' => ['parameters']]);
            $actionParams = json_decode($action['parameters'], true);
            if (empty($actionParams['errorStatus']) || empty($actionParams['successStatus'])) {
                return $response->withStatus(403)->withJson(['errors' => 'errorStatus or successStatus is not set for this action', 'lang' => 'actionStatusNotSet']);
            }
        }

        $selectedResId = [];
        // End of Common Data

        foreach ($body['resources'] as $resId) {
            if (empty($resources[$resId]['destination'])) {
                $resourcesInformations['errors'][] = ['alt_identifier' => $resources[$resId]['alt_identifier'], 'res_id' => $resId, 'reason' => 'noDestination'];
                continue;
            } elseif ($resources[$resId]['retention_frozen'] === true) {
                $resourcesInformations['errors'][] = ['alt_identifier' => $resources[$resId]['alt_identifier'], 'res_id' => $resId, 'reason' => 'retentionRuleFrozen'];
                continue;
            }

            if (!empty($resAcknowledgement[$resId])) {
                $resourcesInformations['errors'][] = ['alt_identifier' => $resources[$resId]['alt_identifier'], 'res_id' => $resId, 'reason' => 'recordManagement_alreadySent'];
                continue;
            }

            $typeId = $resources[$resId]['type_id'];
            if (empty($doctypesData[$typeId]['retention_rule']) || empty($doctypesData[$typeId]['retention_final_disposition']) || empty($doctypesData[$typeId]['duration_current_use'])) {
                $resourcesInformations['errors'][] = ['alt_identifier' => $resources[$resId]['alt_identifier'], 'res_id' => $resId, 'reason' => 'noRetentionInfo'];
                continue;
            } else {
                if ($resources[$resId]['binding'] === null && !in_array($doctypesData[$typeId]['action_current_use'], ['transfer', 'copy'])) {
                    $resourcesInformations['errors'][] = ['alt_identifier' => $resources[$resId]['alt_identifier'], 'res_id' => $resId, 'reason' => 'noTransferCopyRecordManagement'];
                    continue;
                } elseif ($resources[$resId]['binding'] === true && !in_array($bindingDocument['param_value_string'], ['transfer', 'copy'])) {
                    $resourcesInformations['errors'][] = ['alt_identifier' => $resources[$resId]['alt_identifier'], 'res_id' => $resId, 'reason' => 'noTransferCopyBindingRecordManagement'];
                    continue;
                } elseif ($resources[$resId]['binding'] === false && !in_array($nonBindingDocument['param_value_string'], ['transfer', 'copy'])) {
                    $resourcesInformations['errors'][] = ['alt_identifier' => $resources[$resId]['alt_identifier'], 'res_id' => $resId, 'reason' => 'noTransferCopyNoBindingRecordManagement'];
                    continue;
                }
                $date = new \DateTime($resources[$resId]['creation_date']);
                $date->add(new \DateInterval("P{$doctypesData[$typeId]['duration_current_use']}D"));
                if (strtotime($date->format('Y-m-d')) >= time()) {
                    $resourcesInformations['errors'][] = ['alt_identifier' => $resources[$resId]['alt_identifier'], 'res_id' => $resId, 'reason' => 'durationCurrentUseNotExceeded'];
                    continue;
                }
            }

            $destinationId = $resources[$resId]['destination'];
            if (empty($destinationsData[$destinationId]['producer_service'])) {
                $resourcesInformations['errors'][] = ['alt_identifier' => $resources[$resId]['alt_identifier'], 'res_id' => $resId, 'reason' => 'noProducerService'];
                continue;
            }

            $archivalData = SedaController::initArchivalData([
                'resource'           => $resources[$resId],
                'attachments'        => $resAttachments[$resId] ?? [],
                'senderOrgRegNumber' => $config['exportSeda']['senderOrgRegNumber'],
                'entity'             => $destinationsData[$destinationId],
                'doctype'            => $doctypesData[$typeId],
                'massAction'         => $massAction
            ]);

            if (!empty($archivalData['errors'])) {
                $resourcesInformations['errors'][] = ['alt_identifier' => $resources[$resId]['alt_identifier'], 'res_id' => $resId, 'reason' => $archivalData['errors']];
                continue;
            } else {
                $archivalData['archivalData']['data']['metadata'] = ['subject' => $resources[$resId]['subject'], 'alt_identifier' => $resources[$resId]['alt_identifier']];
                $resourcesInformations['success'][$resId] = $archivalData['archivalData'];
            }

            $unitIdentifier = MessageExchangeModel::getUnitIdentifierByResId(['select' => ['message_id'], 'resId' => (string)$resId]);
            if (!empty($unitIdentifier[0]['message_id'])) {
                MessageExchangeModel::delete(['where' => ['message_id = ?'], 'data' => [$unitIdentifier[0]['message_id']]]);
            }
            $selectedResId[] = $resId;
        }

        if (!empty($selectedResId)) {
            MessageExchangeModel::deleteUnitIdentifier(['where' => ['res_id in (?)'], 'data' => [$selectedResId]]);
        }

        return $response->withJson($resourcesInformations);
    }

    public function checkAcknowledgementRecordManagement(Request $request, Response $response, array $args)
    {
        $body = $request->getParsedBody();

        if (!Validator::arrayType()->notEmpty()->validate($body['resources'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body resources is empty or not an array']);
        }

        $errors = ResourceListController::listControl(['groupId' => $args['groupId'], 'userId' => $args['userId'], 'basketId' => $args['basketId'], 'currentUserId' => $GLOBALS['id']]);
        if (!empty($errors['errors'])) {
            return $response->withStatus($errors['code'])->withJson(['errors' => $errors['errors']]);
        }

        $body['resources'] = array_slice($body['resources'], 0, 500);
        if (!ResController::hasRightByResId(['resId' => $body['resources'], 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Document out of perimeter']);
        }

        $resourcesInformations = ['success' => [], 'errors' => []];
        $body['resources'] = PreProcessActionController::getNonLockedResources(['resources' => $body['resources'], 'userId' => $GLOBALS['id']]);

        $attachments = AttachmentModel::get([
            'select' => ['res_id_master', 'path', 'filename', 'docserver_id', 'fingerprint'],
            'where'  => ['res_id_master in (?)', 'attachment_type = ?', 'status = ?'],
            'data'   => [$body['resources'], 'acknowledgement_record_management', 'TRA']
        ]);
        $resourcesAcknowledgement = array_column($attachments, null, 'res_id_master');
        $resIdAcknowledgement     = array_column($attachments, 'res_id_master');

        $resourceAltIdentifier = ResModel::get(['select' => ['alt_identifier', 'res_id'], 'where' => ['res_id in (?)'], 'data' => [$body['resources']]]);
        $altIdentifiers        = array_column($resourceAltIdentifier, 'alt_identifier', 'res_id');

        foreach ($body['resources'] as $resId) {
            if (!in_array($resId, $resIdAcknowledgement)) {
                $resourcesInformations['errors'][] = ['alt_identifier' => $altIdentifiers[$resId], 'res_id' => $resId, 'reason' => 'recordManagement_noAcknowledgement'];
                continue;
            }
            $acknowledgement = $resourcesAcknowledgement[$resId];

            $docserver = DocserverModel::getByDocserverId(['docserverId' => $acknowledgement['docserver_id'], 'select' => ['path_template', 'docserver_type_id']]);
            if (empty($docserver['path_template']) || !file_exists($docserver['path_template'])) {
                $resourcesInformations['errors'][] = ['alt_identifier' => $altIdentifiers[$resId], 'res_id' => $resId, 'reason' => 'docserverDoesNotExists'];
                continue;
            }

            $pathToDocument = $docserver['path_template'] . str_replace('#', DIRECTORY_SEPARATOR, $acknowledgement['path']) . $acknowledgement['filename'];
            if (!file_exists($pathToDocument)) {
                $resourcesInformations['errors'][] = ['alt_identifier' => $altIdentifiers[$resId], 'res_id' => $resId, 'reason' => 'fileDoesNotExists'];
                continue;
            }

            $docserverType = DocserverTypeModel::getById(['id' => $docserver['docserver_type_id'], 'select' => ['fingerprint_mode']]);
            $fingerprint = StoreController::getFingerPrint(['filePath' => $pathToDocument, 'mode' => $docserverType['fingerprint_mode']]);
            if (empty($acknowledgement['fingerprint'])) {
                AttachmentModel::update(['set' => ['fingerprint' => $fingerprint], 'where' => ['res_id = ?'], 'data' => [$args['resId']]]);
                $acknowledgement['fingerprint'] = $fingerprint;
            }
            if (!empty($acknowledgement['fingerprint']) && $acknowledgement['fingerprint'] != $fingerprint) {
                $resourcesInformations['errors'][] = ['alt_identifier' => $altIdentifiers[$resId], 'res_id' => $resId, 'reason' => 'fingerprintsDoNotMatch'];
                continue;
            }

            $acknowledgementXml = @simplexml_load_file($pathToDocument);
            if (empty($acknowledgementXml)) {
                $resourcesInformations['errors'][] = ['alt_identifier' => $altIdentifiers[$resId], 'res_id' => $resId, 'reason' => 'recordManagement_acknowledgementNotReadable'];
                continue;
            }

            $messageExchange = MessageExchangeModel::getMessageByReference(['select' => ['message_id'], 'reference' => (string)$acknowledgementXml->MessageReceivedIdentifier]);
            if (empty($messageExchange)) {
                $resourcesInformations['errors'][] = ['alt_identifier' => $altIdentifiers[$resId], 'res_id' => $resId, 'reason' => 'recordManagement_noAcknowledgementReference'];
                continue;
            }

            $unitIdentifier = MessageExchangeModel::getUnitIdentifierByResId(['select' => ['message_id'], 'resId' => $resId]);
            if ($unitIdentifier[0]['message_id'] != $messageExchange['message_id']) {
                $resourcesInformations['errors'][] = ['alt_identifier' => $altIdentifiers[$resId], 'res_id' => $resId, 'reason' => 'recordManagement_wrongAcknowledgement'];
                continue;
            }

            $resourcesInformations['success'][] = $resId;
        }

        return $response->withJson(['resourcesInformations' => $resourcesInformations]);
    }

    public function checkReplyRecordManagement(Request $request, Response $response, array $args)
    {
        $body = $request->getParsedBody();

        if (!Validator::arrayType()->notEmpty()->validate($body['resources'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body resources is empty or not an array']);
        }

        $errors = ResourceListController::listControl(['groupId' => $args['groupId'], 'userId' => $args['userId'], 'basketId' => $args['basketId'], 'currentUserId' => $GLOBALS['id']]);
        if (!empty($errors['errors'])) {
            return $response->withStatus($errors['code'])->withJson(['errors' => $errors['errors']]);
        }

        $body['resources'] = array_slice($body['resources'], 0, 500);
        if (!ResController::hasRightByResId(['resId' => $body['resources'], 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Document out of perimeter']);
        }

        $resourcesInformations = ['success' => [], 'errors' => []];
        $body['resources'] = PreProcessActionController::getNonLockedResources(['resources' => $body['resources'], 'userId' => $GLOBALS['id']]);

        $attachments = AttachmentModel::get([
            'select' => ['res_id_master', 'path', 'filename', 'docserver_id', 'fingerprint'],
            'where'  => ['res_id_master in (?)', 'attachment_type = ?', 'status = ?'],
            'data'   => [$body['resources'], 'reply_record_management', 'TRA']
        ]);
        $resourcesReply = array_column($attachments, null, 'res_id_master');
        $resIdReply     = array_column($attachments, 'res_id_master');

        $resourceAltIdentifier = ResModel::get(['select' => ['alt_identifier', 'res_id'], 'where' => ['res_id in (?)'], 'data' => [$body['resources']]]);
        $altIdentifiers        = array_column($resourceAltIdentifier, 'alt_identifier', 'res_id');

        foreach ($body['resources'] as $resId) {
            if (!in_array($resId, $resIdReply)) {
                $resourcesInformations['errors'][] = ['alt_identifier' => $altIdentifiers[$resId], 'res_id' => $resId, 'reason' => 'recordManagement_noReply'];
                continue;
            }
            $reply = $resourcesReply[$resId];

            $docserver = DocserverModel::getByDocserverId(['docserverId' => $reply['docserver_id'], 'select' => ['path_template', 'docserver_type_id']]);
            if (empty($docserver['path_template']) || !file_exists($docserver['path_template'])) {
                $resourcesInformations['errors'][] = ['alt_identifier' => $altIdentifiers[$resId], 'res_id' => $resId, 'reason' => 'docserverDoesNotExists'];
                continue;
            }

            $pathToDocument = $docserver['path_template'] . str_replace('#', DIRECTORY_SEPARATOR, $reply['path']) . $reply['filename'];
            if (!file_exists($pathToDocument)) {
                $resourcesInformations['errors'][] = ['alt_identifier' => $altIdentifiers[$resId], 'res_id' => $resId, 'reason' => 'fileDoesNotExists'];
                continue;
            }

            $docserverType = DocserverTypeModel::getById(['id' => $docserver['docserver_type_id'], 'select' => ['fingerprint_mode']]);
            $fingerprint = StoreController::getFingerPrint(['filePath' => $pathToDocument, 'mode' => $docserverType['fingerprint_mode']]);
            if (empty($reply['fingerprint'])) {
                AttachmentModel::update(['set' => ['fingerprint' => $fingerprint], 'where' => ['res_id = ?'], 'data' => [$args['resId']]]);
                $reply['fingerprint'] = $fingerprint;
            }
            if (!empty($reply['fingerprint']) && $reply['fingerprint'] != $fingerprint) {
                $resourcesInformations['errors'][] = ['alt_identifier' => $altIdentifiers[$resId], 'res_id' => $resId, 'reason' => 'fingerprintsDoNotMatch'];
                continue;
            }

            $replyXml = @simplexml_load_file($pathToDocument);
            if (empty($replyXml)) {
                $resourcesInformations['errors'][] = ['alt_identifier' => $altIdentifiers[$resId], 'res_id' => $resId, 'reason' => 'recordManagement_replyNotReadable'];
                continue;
            }

            $messageExchange = MessageExchangeModel::getMessageByReference(['select' => ['message_id'], 'reference' => (string)$replyXml->MessageRequestIdentifier]);
            if (empty($messageExchange)) {
                $resourcesInformations['errors'][] = ['alt_identifier' => $altIdentifiers[$resId], 'res_id' => $resId, 'reason' => 'recordManagement_noReplyReference'];
                continue;
            }

            $unitIdentifier = MessageExchangeModel::getUnitIdentifierByResId(['select' => ['message_id'], 'resId' => $resId]);
            if ($unitIdentifier[0]['message_id'] != $messageExchange['message_id']) {
                $resourcesInformations['errors'][] = ['alt_identifier' => $altIdentifiers[$resId], 'res_id' => $resId, 'reason' => 'recordManagement_wrongReply'];
                continue;
            }

            if ($body['resetAction'] && strpos((string)$replyXml->ReplyCode, '000') !== false) {
                $resourcesInformations['errors'][] = ['alt_identifier' => $altIdentifiers[$resId], 'res_id' => $resId, 'reason' => 'recordManagement_alreadyArchived'];
                continue;
            }
            if (!$body['resetAction'] && strpos((string)$replyXml->ReplyCode, '000') === false) {
                $resourcesInformations['errors'][] = ['alt_identifier' => $altIdentifiers[$resId], 'res_id' => $resId, 'reason' => 'recordManagement_rejectedReply'];
                continue;
            }

            $resourcesInformations['success'][] = $resId;
        }

        return $response->withJson(['resourcesInformations' => $resourcesInformations]);
    }
}
