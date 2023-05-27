<?php

/**
* Copyright Maarch since 2008 under licence GPLv3.
* See LICENCE.txt file at the root folder for more details.
* This file is part of Maarch software.

* @brief   ExportSEDATrait
* @author  dev <dev@maarch.org>
* @ingroup core
*/

namespace ExportSeda\controllers;

use Attachment\models\AttachmentModel;
use Configuration\models\ConfigurationModel;
use Contact\controllers\ContactController;
use Convert\controllers\ConvertPdfController;
use Docserver\models\DocserverModel;
use Docserver\models\DocserverTypeModel;
use Doctype\models\DoctypeModel;
use Entity\models\EntityModel;
use Entity\models\ListInstanceModel;
use Folder\models\FolderModel;
use History\models\HistoryModel;
use IndexingModel\models\IndexingModelFieldModel;
use MessageExchange\models\MessageExchangeModel;
use Note\controllers\NoteController;
use Parameter\models\ParameterModel;
use Resource\controllers\StoreController;
use Resource\controllers\SummarySheetController;
use Resource\models\ResModel;
use setasign\Fpdi\Tcpdf\Fpdi;
use SrcCore\models\CoreConfigModel;
use SrcCore\models\CurlModel;
use SrcCore\models\ValidatorModel;

trait ExportSEDATrait
{
    public static function sendToRecordManagement(array $args)
    {
        ValidatorModel::notEmpty($args, ['resId']);
        ValidatorModel::intVal($args, ['resId']);

        static $resAcknowledgement;
        static $attachmentsData;
        static $resources;
        static $doctypes;
        static $bindingDocument;
        static $nonBindingDocument;
        static $config;
        static $entities;
        static $zipFilename;

        if ($resources === null) {
            $resAcknowledgement = [];
            $attachments = AttachmentModel::get([
                'select' => ['res_id_master', 'res_id', 'title', 'docserver_id', 'path', 'filename', 'res_id_master', 'fingerprint', 'creation_date', 'identifier', 'attachment_type'],
                'where'  => ['res_id_master in (?)', 'status in (?)'],
                'data'   => [$args['resources'], ['A_TRA', 'TRA']]
            ]);
            $attachmentsData = [];
            foreach ($attachments as $attachment) {
                if (in_array($attachment['attachment_type'], ['acknowledgement_record_management', 'reply_record_management'])) {
                    $resAcknowledgement[$attachment['res_id_master']] = $attachment['res_id'];
                } else {
                    $attachmentsData[$attachment['res_id_master']][] = $attachment;
                }
            }

            $resources = ResModel::get([
                'select' => ['res_id', 'destination', 'type_id', 'subject', 'linked_resources', 'alt_identifier', 'admission_date', 'creation_date', 'modification_date', 'doc_date', 'retention_frozen', 'binding', 'docserver_id', 'path', 'filename', 'version', 'fingerprint'],
                'where'  => ['res_id in (?)'],
                'data'   => [$args['resources']]
            ]);
            $resources = array_column($resources, null, 'res_id');

            $doctypesId = array_column($resources, 'type_id');
            $doctypes = DoctypeModel::get([
                'select' => ['type_id', 'description', 'duration_current_use', 'action_current_use', 'retention_rule', 'retention_final_disposition'],
                'where'  => ['type_id in (?)'],
                'data'   => [$doctypesId]
            ]);
            $doctypes = array_column($doctypes, null, 'type_id');

            $entitiesId = array_column($resources, 'destination');
            $entities   = EntityModel::get(['select' => ['entity_id', 'producer_service', 'entity_label'], 'where' => ['entity_id in (?)'], 'data' => [$entitiesId]]);
            $entities   = array_column($entities, null, 'entity_id');

            $bindingDocument    = ParameterModel::getById(['select' => ['param_value_string'], 'id' => 'bindingDocumentFinalAction']);
            $nonBindingDocument = ParameterModel::getById(['select' => ['param_value_string'], 'id' => 'nonBindingDocumentFinalAction']);

            $configuration = ConfigurationModel::getByPrivilege(['privilege' => 'admin_export_seda']);
            $config = [];
            $config['exportSeda'] = !empty($configuration['value']) ? json_decode($configuration['value'], true) : [];

            if (count($args['resources']) > 1 && $args['data']['actionMode'] == 'download') {
                $tmpPath     = CoreConfigModel::getTmpPath();
                $zipFilename = $tmpPath . $GLOBALS['login'] . "_" . rand() . ".zip";
            }
        }

        if (in_array($args['resId'], $resAcknowledgement)) {
            return ['errors' => ['acknowledgement or reply already exists']];
        }

        $resource = $resources[$args['resId']];
        if (empty($resource)) {
            return ['errors' => ['resource does not exists']];
        } elseif (empty($resource['destination'])) {
            return ['errors' => ['resource has no destination']];
        } elseif ($resource['retention_frozen']) {
            return ['errors' => ['retention rule is frozen']];
        }

        $doctype = $doctypes[$resource['type_id']];
        if (empty($doctype['retention_rule']) || empty($doctype['retention_final_disposition'])) {
            return ['errors' => ['retention_rule or retention_final_disposition is empty for doctype']];
        } else {
            if ($resource['binding'] === null && !in_array($doctype['action_current_use'], ['transfer', 'copy'])) {
                return ['errors' => 'action_current_use is not transfer or copy'];
            } elseif ($resource['binding'] === true && !in_array($bindingDocument['param_value_string'], ['transfer', 'copy'])) {
                return ['errors' => 'binding document is not transfer or copy'];
            } elseif ($resource['binding'] === false && !in_array($nonBindingDocument['param_value_string'], ['transfer', 'copy'])) {
                return ['errors' => 'no binding document is not transfer or copy'];
            }
            $date = new \DateTime($resource['creation_date']);
            $date->add(new \DateInterval("P{$doctype['duration_current_use']}D"));
            if (strtotime($date->format('Y-m-d')) >= time()) {
                return ['errors' => 'duration current use is not exceeded'];
            }
        }

        $entity = $entities[$resource['destination']];
        if (empty($entity['producer_service'])) {
            return ['errors' => ['producer_service is empty for this entity']];
        }

        if (empty($config['exportSeda']['senderOrgRegNumber'])) {
            return ['errors' => ['No senderOrgRegNumber found in config.json']];
        }

        foreach (['archivalAgreement', 'entityArchiveRecipient'] as $value) {
            if (empty($args['data'][$value])) {
                return ['errors' => [$value . ' is empty']];
            }
        }

        if ($args['data']['actionMode'] == 'download') {
            $sedaPackage = ExportSedaTrait::makeSedaPackage([
                'resource'               => $resource,
                'attachments'            => $attachmentsData[$args['resId']] ?? [],
                'config'                 => $config,
                'entity'                 => $entity,
                'doctype'                => $doctype,
                'folder'                 => $args['data']['folder'],
                'archivalAgreement'      => $args['data']['archivalAgreement'],
                'entityArchiveRecipient' => $args['data']['entityArchiveRecipient']
            ]);

            if (count($args['resources']) > 1) {
                $zip = new \ZipArchive();
                if ($zip->open($zipFilename, \ZipArchive::CREATE) === true) {
                    $zip->addFile($sedaPackage['encodedFilePath'], 'sedaPackage' . $resource['res_id'] . '.zip');
                    $zip->close();

                    $zipPath = $zipFilename;
                } else {
                    return ['errors' => ['Cannot open zip file ' . $zipFilename]];
                }
            } else {
                $zipPath = $sedaPackage['encodedFilePath'];
            }
            $encodedContent = base64_encode(file_get_contents($zipPath));
            unlink($sedaPackage['encodedFilePath']);
            return ['data' => ['encodedFile' => $encodedContent]];
        } else {
            $customId = CoreConfigModel::getCustomId();

            static $massData;
            if ($massData === null) {
                $massData = [
                    'resources'              => [],
                    'successStatus'          => $args['action']['parameters']['successStatus'],
                    'errorStatus'            => $args['action']['parameters']['errorStatus'],
                    'userId'                 => $GLOBALS['id'],
                    'customId'               => $customId,
                    'archivalAgreement'      => $args['data']['archivalAgreement'],
                    'entityArchiveRecipient' => $args['data']['entityArchiveRecipient'],
                    'folder'                 => $args['data']['folder']
                ];
            }

            $massData['resources'][] = $resource['res_id'];

            return ['postscript' => 'src/app/external/exportSeda/scripts/ExportSedaScript.php', 'args' => $massData];
        }
    }

    public static function makeSedaPackage($args = [])
    {
        $initData = SedaController::initArchivalData([
            'resource'           => $args['resource'],
            'attachments'        => $args['attachments'],
            'senderOrgRegNumber' => $args['config']['exportSeda']['senderOrgRegNumber'],
            'entity'             => $args['entity'],
            'doctype'            => $args['doctype'],
            'getFile'            => true
        ]);

        if (!empty($initData['errors'])) {
            return ['errors' => $initData['errors']];
        } else {
            $initData = $initData['archivalData'];
        }

        $history = ExportSEDATrait::getHistory(['resId' => $args['resource']['res_id']]);
        $folder  = ExportSEDATrait::getFolderPath(['selectedFolder' => $args['folder'], 'folders' => $initData['additionalData']['folders']]);

        $dataObjectPackage = [
            'archiveId'                 => $initData['data']['slipInfo']['archiveId'],
            'chrono'                    => $args['resource']['alt_identifier'],
            'originatorAgency'          => [
                'id'    => $args['entity']['producer_service'],
                'label' => $args['entity']['entity_label']
            ],
            'receivedDate'              => $args['resource']['admission_date'],
            'documentDate'              => $args['resource']['doc_date'],
            'creationDate'              => $args['resource']['creation_date'],
            'modificationDate'          => $args['resource']['modification_date'],
            'retentionRule'             => $initData['data']['doctype']['retentionRule'],
            'retentionFinalDisposition' => $initData['data']['doctype']['retentionFinalDisposition'],
            'accessRuleCode'            => $args['config']['exportSeda']['accessRuleCode'],
            'history'                   => $history['history'],
            'contacts'                  => [
                'senders'    => ContactController::getParsedContacts(['resId' => $args['resource']['res_id'], 'mode' => 'sender']),
                'recipients' => ContactController::getParsedContacts(['resId' => $args['resource']['res_id'], 'mode' => 'recipient'])
            ],
            'attachments'               => $initData['archiveUnits'],
            'folders'                   => $folder['folderPath'],
            'links'                     => $initData['additionalData']['linkedResources']
        ];

        $data = [
            'type' => 'ArchiveTransfer',
            'messageObject' => [
                'messageIdentifier'  => $initData['data']['slipInfo']['slipId'],
                'archivalAgreement'  => $args['archivalAgreement'],
                'dataObjectPackage'  => $dataObjectPackage,
                'archivalAgency'     => $args['entityArchiveRecipient'],
                'transferringAgency' => $initData['data']['entity']['senderArchiveEntity']
            ]
        ];

        $sedaPackage = ExportSEDATrait::generateSEDAPackage(['data' => $data]);
        if (!empty($sedaPackage['errors'])) {
            return ['errors' => [$sedaPackage['errors']]];
        }

        $messageSaved = ExportSEDATrait::saveMessage(['messageObject' => $sedaPackage['messageObject']]);
        MessageExchangeModel::insertUnitIdentifier([
            'messageId' => $messageSaved['messageId'],
            'tableName' => 'res_letterbox',
            'resId'     => $args['resource']['res_id']
        ]);

        ExportSEDATrait::cleanTmpDocument(['archiveUnits' => $initData['archiveUnits']]);

        return [
            'messageId' => $messageSaved['messageId'], 'encodedFilePath' => $sedaPackage['encodedFilePath'],
            'messageFilename' => $sedaPackage['messageFilename'], 'reference' => $data['messageObject']['messageIdentifier']
        ];
    }

    public static function sendSedaPackage($args = [])
    {
        $bodyData = [
            'messageFile' => base64_encode(file_get_contents($args['encodedFilePath'])),
            'filename'    => $args['messageFilename'],
            'schema'      => 'seda2'
        ];
        $curlResponse = CurlModel::exec([
            'url'     => rtrim($args['config']['exportSeda']['urlSAEService'], '/') . '/medona/archiveTransfer',
            'method'  => 'POST',
            'cookie'  => 'LAABS-AUTH=' . urlencode($args['config']['exportSeda']['token']),
            'headers' => [
                'Accept: application/json',
                'Content-Type: application/json',
                'User-Agent: ' . $args['config']['exportSeda']['userAgent']
            ],
            'body'   => json_encode($bodyData)
        ]);

        if (!empty($curlResponse['errors'])) {
            return ['errors' => 'Error returned by the route /medona/create : ' . $curlResponse['errors']];
        } elseif ($curlResponse['code'] != 200) {
            return ['errors' => 'Error returned by the route /medona/create : ' . $curlResponse['response']['message']];
        }

        $acknowledgement = ExportSEDATrait::getAcknowledgement([
            'config'    => $args['config'],
            'reference' => $args['reference']
        ]);
        if (!empty($acknowledgement['errors'])) {
            return ['errors' => 'Error returned in getAcknowledgement process : ' . $acknowledgement['errors']];
        }

        $id = StoreController::storeAttachment([
            'encodedFile'   => $acknowledgement['encodedAcknowledgement'],
            'type'          => 'acknowledgement_record_management',
            'resIdMaster'   => $args['resId'],
            'title'         => 'Accusé de réception',
            'format'        => 'xml',
            'status'        => 'TRA'
        ]);
        if (empty($id) || !empty($id['errors'])) {
            return ['errors' => ['[storeAttachment] ' . $id['errors']]];
        }

        ConvertPdfController::convert(['resId' => $id, 'collId' => 'attachments_coll']);

        return [];
    }

    public static function saveMessage($args = [])
    {
        $data = new \stdClass();

        $data->messageId                             = $args['messageObject']->MessageIdentifier->value;
        $data->date                                  = $args['messageObject']->Date;

        $data->MessageIdentifier                     = new \stdClass();
        $data->MessageIdentifier->value              = $args['messageObject']->MessageIdentifier->value;

        $data->TransferringAgency                    = new \stdClass();
        $data->TransferringAgency->Identifier        = new \stdClass();
        $data->TransferringAgency->Identifier->value = $args['messageObject']->TransferringAgency->Identifier->value;

        $data->ArchivalAgency                        = new \stdClass();
        $data->ArchivalAgency->Identifier            = new \stdClass();
        $data->ArchivalAgency->Identifier->value     = $args['messageObject']->ArchivalAgency->Identifier->value;

        $data->ArchivalAgreement                     = new \stdClass();
        $data->ArchivalAgreement->value              = $args['messageObject']->ArchivalAgreement->value;

        $data->ReplyCode                             = $args['messageObject']->ReplyCode;

        $dataExtension                      = [];
        $dataExtension['fullMessageObject'] = $args['messageObject'];
        $dataExtension['SenderOrgNAme']     = '';
        $dataExtension['RecipientOrgNAme']  = '';

        $message = MessageExchangeModel::insertMessage([
            'data'          => $data,
            'type'          => 'ArchiveTransfer',
            'dataExtension' => $dataExtension,
            'userId'        => $GLOBALS['id']
        ]);

        return ['messageId' => $message['messageId']];
    }

    public static function getFolderPath($args = [])
    {
        $folderPath = null;
        if (!empty($args['selectedFolder'])) {
            foreach ($args['folders'] as $folder) {
                if ($folder['id'] == $args['selectedFolder']) {
                    $folderId   = explode("_", $folder['id'])[1];
                    $folderPath = FolderModel::getFolderPath(['id' => $folderId]);
                    break;
                }
            }
        } elseif (!empty($args['folders'])) {
            $folderId   = explode("_", $args['folders'][0]['id'])[1];
            $folderPath = FolderModel::getFolderPath(['id' => $folderId]);
        }

        return ['folderPath' => $folderPath];
    }

    public static function getHistory($args = [])
    {
        $history = HistoryModel::get([
            'select'  => ['event_date', 'info'],
            'where'   => ['table_name in (?)', 'record_id = ?', 'event_type like ?'],
            'data'    => [['res_letterbox', 'res_view_letterbox'], $args['resId'], 'ACTION#%'],
            'orderBy' => ['event_date DESC']
        ]);

        return ['history' => $history];
    }

    public static function getAttachmentFilePath($args = [])
    {
        $document['docserver_id'] = $args['data']['docserver_id'];
        $document['path']         = $args['data']['path'];
        $document['filename']     = $args['data']['filename'];
        $document['fingerprint']  = $args['data']['fingerprint'];

        $docserver = DocserverModel::getByDocserverId(['docserverId' => $document['docserver_id'], 'select' => ['path_template', 'docserver_type_id']]);
        if (empty($docserver['path_template']) || !file_exists($docserver['path_template'])) {
            return ['errors' => 'Docserver does not exist'];
        }

        $pathToDocument = $docserver['path_template'] . str_replace('#', DIRECTORY_SEPARATOR, $document['path']) . $document['filename'];

        if (!file_exists($pathToDocument)) {
            return ['errors' => 'Attachment not found on docserver'];
        }

        $docserverType = DocserverTypeModel::getById(['id' => $docserver['docserver_type_id'], 'select' => ['fingerprint_mode']]);
        $fingerprint   = StoreController::getFingerPrint(['filePath' => $pathToDocument, 'mode' => $docserverType['fingerprint_mode']]);
        if (empty($document['fingerprint'])) {
            AttachmentModel::update(['set' => ['fingerprint' => $fingerprint], 'where' => ['res_id = ?'], 'data' => [$args['resId']]]);
            $document['fingerprint'] = $fingerprint;
        }

        if (!empty($document['fingerprint']) && $document['fingerprint'] != $fingerprint) {
            return ['errors' => 'Fingerprints do not match'];
        }

        $fileContent = file_exists($pathToDocument);
        if ($fileContent === false) {
            return ['errors' => 'Document not found on docserver'];
        }

        return ['filePath' => $pathToDocument];
    }

    public static function getNoteFilePath($args = [])
    {
        $encodedDocument = NoteController::getEncodedPdfByIds(['ids' => [$args['id']]]);

        $tmpPath  = CoreConfigModel::getTmpPath();
        $filePath = $tmpPath . 'note_' . $args['id'] . '.pdf';
        file_put_contents($filePath, base64_decode($encodedDocument['encodedDocument']));

        return ['filePath' => $filePath];
    }

    public static function getEmailFilePath($args = [])
    {
        $body   = str_replace('###', ';', $args['data']['body']);
        $sender = json_decode($args['data']['sender'], true);
        $data   = "Courriel n°" . $args['data']['id'] . "\nDe : " . $sender['email'] . "\nPour : " . implode(", ", json_decode($args['data']['recipients'], true)) . "\nObjet : " . $args['data']['object'] . "\n\n" . strip_tags(html_entity_decode($body));

        $pdf = new Fpdi('P', 'pt');
        $pdf->setPrintHeader(false);
        $pdf->AddPage();
        $pdf->MultiCell(0, 10, $data, 0, 'L');

        $tmpPath  = CoreConfigModel::getTmpPath();
        $filePath = $tmpPath . 'email_' . $args['data']['id'] . '.pdf';
        $pdf->Output($filePath, "F");

        return ['filePath' => $filePath];
    }

    public static function getSummarySheetFilePath($args = [])
    {
        $units   = [];
        $units[] = ['unit' => 'primaryInformations'];
        $units[] = ['unit' => 'secondaryInformations',       'label' => _SECONDARY_INFORMATION];
        $units[] = ['unit' => 'senderRecipientInformations', 'label' => _DEST_INFORMATION];
        $units[] = ['unit' => 'diffusionList',               'label' => _DIFFUSION_LIST];
        $units[] = ['unit' => 'visaWorkflow',                'label' => _VISA_WORKFLOW];
        $units[] = ['unit' => 'opinionWorkflow',             'label' => _AVIS_WORKFLOW];

        $tmpIds = [$args['resId']];
        $data   = [];
        foreach ($units as $unit) {
            if ($unit['unit'] == 'opinionWorkflow') {
                $data['listInstancesOpinion'] = ListInstanceModel::get([
                    'select'    => ['item_id', 'process_date', 'res_id'],
                    'where'     => ['difflist_type = ?', 'res_id in (?)'],
                    'data'      => ['AVIS_CIRCUIT', $tmpIds],
                    'orderBy'   => ['listinstance_id']
                ]);
            } elseif ($unit['unit'] == 'visaWorkflow') {
                $data['listInstancesVisa'] = ListInstanceModel::get([
                    'select'    => ['item_id', 'requested_signature', 'process_date', 'res_id'],
                    'where'     => ['difflist_type = ?', 'res_id in (?)'],
                    'data'      => ['VISA_CIRCUIT', $tmpIds],
                    'orderBy'   => ['listinstance_id']
                ]);
            } elseif ($unit['unit'] == 'diffusionList') {
                $data['listInstances'] = ListInstanceModel::get([
                    'select'  => ['item_id', 'item_type', 'item_mode', 'res_id'],
                    'where'   => ['difflist_type = ?', 'res_id in (?)'],
                    'data'    => ['entity_id', $tmpIds],
                    'orderBy' => ['listinstance_id']
                ]);
            }
        }

        $mainResource = ResModel::getOnView([
            'select' => ['process_limit_date', 'status', 'alt_identifier', 'subject', 'priority', 'res_id', 'admission_date', 'creation_date', 'doc_date', 'initiator', 'typist', 'type_label', 'destination', 'filename'],
            'where'  => ['res_id = ?'],
            'data'   => [$args['resId']]
        ]);

        $modelId = ResModel::getById([
            'select' => ['model_id'],
            'resId'  => $args['resId']
        ]);
        $indexingFields = IndexingModelFieldModel::get([
            'select' => ['identifier', 'unit'],
            'where'  => ['model_id = ?'],
            'data'   => [$modelId['model_id']]
        ]);
        $fieldsIdentifier = array_column($indexingFields, 'identifier');

        $pdf = new Fpdi('P', 'pt');
        $pdf->setPrintHeader(false);

        SummarySheetController::createSummarySheet($pdf, [
            'resource'         => $mainResource[0],
            'units'            => $units,
            'login'            => $GLOBALS['login'],
            'data'             => $data,
            'fieldsIdentifier' => $fieldsIdentifier
        ]);

        $tmpPath = CoreConfigModel::getTmpPath();
        $summarySheetFilePath = $tmpPath . "summarySheet_".$args['resId'] . "_" . $GLOBALS['id'] . "_" . rand() . ".pdf";
        $pdf->Output($summarySheetFilePath, 'F');

        return ['filePath' => $summarySheetFilePath];
    }

    public static function cleanTmpDocument(array $args)
    {
        foreach ($args['archiveUnits'] as $archiveUnit) {
            if (in_array($archiveUnit['type'], ['note', 'email', 'summarySheet'])) {
                unlink($archiveUnit['filePath']);
            }
        }
    }

    public static function array2object($data)
    {
        if (!is_array($data)) {
            return $data;
        }
        $object = new \stdClass();
        foreach ($data as $name => $value) {
            if (isset($name)) {
                $object->{$name} = self::array2object($value);
            }
        }
        return $object;
    }

    public static function generateSEDAPackage(array $args)
    {
        $data = [];
        $data['messageObject'] = self::array2object($args["data"]["messageObject"]);
        $data['type'] = $args["data"]["type"];

        $informationsToSend = SendMessageController::generateSedaFile($data);
        return $informationsToSend;
    }

    public static function getAcknowledgement($args = [])
    {
        $curlResponse = CurlModel::exec([
            'url'     => rtrim($args['config']['exportSeda']['urlSAEService'], '/') . '/medona/message/reference?reference='.urlencode($args['reference']."_Ack"),
            'method'  => 'GET',
            'cookie'  => 'LAABS-AUTH=' . urlencode($args['config']['exportSeda']['token']),
            'headers' => [
                'Accept: application/json',
                'Content-Type: application/json',
                'User-Agent: ' . $args['config']['exportSeda']['userAgent']
            ]
        ]);

        if (!empty($curlResponse['errors'])) {
            return ['errors' => 'Error returned by the route /medona/message/reference : ' . $curlResponse['errors']];
        } elseif ($curlResponse['code'] != 200) {
            return ['errors' => 'Error returned by the route /medona/message/reference : ' . $curlResponse['response']['message']];
        }

        $messageId = $curlResponse['response']['messageId'];

        $curlResponse = CurlModel::exec([
            'url'     => rtrim($args['config']['exportSeda']['urlSAEService'], '/') . '/medona/message/'.urlencode($messageId).'/Export',
            'method'  => 'GET',
            'cookie'  => 'LAABS-AUTH=' . urlencode($args['config']['exportSeda']['token']),
            'headers' => [
                'Accept: application/zip',
                'Content-Type: application/json',
                'User-Agent: ' . $args['config']['exportSeda']['userAgent']
            ]
        ]);

        if (!empty($curlResponse['errors'])) {
            return ['errors' => 'Error returned by the route /medona/message/{messageId}/Export : ' . $curlResponse['errors']];
        } elseif ($curlResponse['code'] != 200) {
            return ['errors' => 'Error returned by the route /medona/message/{messageId}/Export : ' . $curlResponse['response']['message']];
        }

        $encodedAcknowledgement = ExportSEDATrait::getXmlFromZipMessage([
            'encodedZipDocument' => base64_encode($curlResponse['response']),
            'messageId'          => $messageId
        ]);
        if (!empty($encodedAcknowledgement['errors'])) {
            return ['errors' => 'Error during getXmlFromZipMessage process : ' . $encodedAcknowledgement['errors']];
        }

        return ['encodedAcknowledgement' => $encodedAcknowledgement['encodedDocument']];
    }

    public static function getXmlFromZipMessage(array $args)
    {
        $tmpPath = CoreConfigModel::getTmpPath();

        $zipDocumentOnTmp = $tmpPath . mt_rand() .'_' . $GLOBALS['id'] . '_acknowledgement.7z';
        file_put_contents($zipDocumentOnTmp, base64_decode($args['encodedZipDocument']));

        $path = $tmpPath. mt_rand() .'_' . $GLOBALS['id'];
        shell_exec("7z x $zipDocumentOnTmp -o$path");

        $fullFilePath = $path."/".$args['messageId'].".xml";
        if (!file_exists($fullFilePath)) {
            return ['errors' => "getDocumentFromEncodedZip : No document was found in Zip"];
        }

        $content = file_get_contents($fullFilePath);
        $xmlfile = simplexml_load_file($fullFilePath);
        unlink($zipDocumentOnTmp);
        unlink($fullFilePath);
        rmdir($path);

        return ['encodedDocument' => base64_encode($content), 'xmlContent' => $xmlfile];
    }

    public static function checkAcknowledgmentRecordManagement(array $args)
    {
        ValidatorModel::notEmpty($args, ['resId']);
        ValidatorModel::intVal($args, ['resId']);

        $acknowledgement = AttachmentModel::get([
            'select' => ['res_id_master', 'path', 'filename', 'docserver_id', 'fingerprint'],
            'where'  => ['res_id_master = ?', 'attachment_type = ?', 'status = ?'],
            'data'   => [$args['resId'], 'acknowledgement_record_management', 'TRA']
        ])[0];
        if (empty($acknowledgement)) {
            return ['errors' => ['No acknowledgement found']];
        }

        $docserver = DocserverModel::getByDocserverId(['docserverId' => $acknowledgement['docserver_id'], 'select' => ['path_template', 'docserver_type_id']]);
        if (empty($docserver['path_template']) || !file_exists($docserver['path_template'])) {
            return ['errors' => ['Docserver does not exists']];
        }

        $pathToDocument = $docserver['path_template'] . str_replace('#', DIRECTORY_SEPARATOR, $acknowledgement['path']) . $acknowledgement['filename'];
        if (!file_exists($pathToDocument)) {
            return ['errors' => ['File does not exists']];
        }

        $docserverType = DocserverTypeModel::getById(['id' => $docserver['docserver_type_id'], 'select' => ['fingerprint_mode']]);
        $fingerprint = StoreController::getFingerPrint(['filePath' => $pathToDocument, 'mode' => $docserverType['fingerprint_mode']]);
        if (!empty($acknowledgement['fingerprint']) && $acknowledgement['fingerprint'] != $fingerprint) {
            return ['errors' => ['Fingerprint does not match']];
        }

        $acknowledgementXml = @simplexml_load_file($pathToDocument);
        if (empty($acknowledgementXml)) {
            return ['errors' => ['Acknowledgement is not readable']];
        }

        $messageExchange = MessageExchangeModel::getMessageByReference(['select' => ['message_id'], 'reference' => (string)$acknowledgementXml->MessageReceivedIdentifier]);
        if (empty($messageExchange)) {
            return ['errors' => ['No acknowledgement found with this reference']];
        }

        $unitIdentifier = MessageExchangeModel::getUnitIdentifierByResId(['select' => ['message_id'], 'resId' => $args['resId']]);
        if ($unitIdentifier[0]['message_id'] != $messageExchange['message_id']) {
            return ['errors' => ['Wrong acknowledgement']];
        }

        return true;
    }

    public static function checkReplyRecordManagement(array $args)
    {
        ValidatorModel::notEmpty($args, ['resId']);
        ValidatorModel::intVal($args, ['resId']);

        $reply = AttachmentModel::get([
            'select' => ['res_id_master', 'path', 'filename', 'docserver_id', 'fingerprint'],
            'where'  => ['res_id_master = ?', 'attachment_type = ?', 'status = ?'],
            'data'   => [$args['resId'], 'reply_record_management', 'TRA']
        ])[0];
        if (empty($reply)) {
            return ['errors' => ['No reply found']];
        }

        $docserver = DocserverModel::getByDocserverId(['docserverId' => $reply['docserver_id'], 'select' => ['path_template', 'docserver_type_id']]);
        if (empty($docserver['path_template']) || !file_exists($docserver['path_template'])) {
            return ['errors' => ['Docserver does not exists']];
        }

        $pathToDocument = $docserver['path_template'] . str_replace('#', DIRECTORY_SEPARATOR, $reply['path']) . $reply['filename'];
        if (!file_exists($pathToDocument)) {
            return ['errors' => ['File does not exists']];
        }

        $docserverType = DocserverTypeModel::getById(['id' => $docserver['docserver_type_id'], 'select' => ['fingerprint_mode']]);
        $fingerprint = StoreController::getFingerPrint(['filePath' => $pathToDocument, 'mode' => $docserverType['fingerprint_mode']]);
        if (!empty($reply['fingerprint']) && $reply['fingerprint'] != $fingerprint) {
            return ['errors' => ['Fingerprint does not match']];
        }

        $replyXml = @simplexml_load_file($pathToDocument);
        if (empty($replyXml)) {
            return ['errors' => ['Reply is not readable']];
        }

        $messageExchange = MessageExchangeModel::getMessageByReference(['select' => ['message_id'], 'reference' => (string)$replyXml->MessageRequestIdentifier]);
        if (empty($messageExchange)) {
            return ['errors' => ['No reply found with this reference']];
        }

        $unitIdentifier = MessageExchangeModel::getUnitIdentifierByResId(['select' => ['message_id'], 'resId' => $args['resId']]);
        if ($unitIdentifier[0]['message_id'] != $messageExchange['message_id']) {
            return ['errors' => ['Wrong reply']];
        }

        if ($args['data']['resetAction']) {
            if (strpos((string)$replyXml->ReplyCode, '000') !== false) {
                return ['errors' => ['Mail already archived']];
            }

            AttachmentModel::update([
                'set'   => ['status' => 'DEL'],
                'where' => ['attachment_type in (?)', 'res_id_master = ?'],
                'data'  => [['acknowledgement_record_management', 'reply_record_management'], $args['resId']]
            ]);

            MessageExchangeModel::deleteUnitIdentifier(['where' => ['res_id = ?'], 'data' => [$args['resId']]]);
            MessageExchangeModel::delete(['where' => ['reference in (?)'], 'data' => [[(string) $replyXml->MessageRequestIdentifier, (string) $replyXml->MessageIdentifier]]]);
        } elseif (strpos((string)$replyXml->ReplyCode, '000') === false) {
            return ['errors' => ['Mail rejected from SAE']];
        }

        return true;
    }
}
