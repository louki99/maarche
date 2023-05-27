<?php

/**
 * Copyright Maarch since 2008 under licence GPLv3.
 * See LICENCE.txt file at the root folder for more details.
 * This file is part of Maarch software.
 *
 */

/**
 * @brief Export Seda Script
 * @author dev@maarch.org
 */

namespace ExportSeda\controllers;

require 'vendor/autoload.php';

use Attachment\models\AttachmentModel;
use Configuration\models\ConfigurationModel;
use Doctype\models\DoctypeModel;
use Entity\models\EntityModel;
use ExportSeda\controllers\ExportSEDATrait;
use Resource\models\ResModel;
use SrcCore\controllers\LogsController;
use SrcCore\models\DatabasePDO;
use User\models\UserModel;

// ARGS
// --encodedData : All data encoded in base64

ExportSedaScript::initialize($argv);

class ExportSedaScript
{
    public static function initialize($args)
    {
        if (array_search('--encodedData', $args) > 0) {
            $cmd = array_search('--encodedData', $args);
            $data = json_decode(base64_decode($args[$cmd+1]), true);
        }

        if (!empty($data)) {
            ExportSedaScript::send(['data' => $data]);
        }
    }

    public static function send(array $args)
    {
        DatabasePDO::reset();
        new DatabasePDO(['customId' => $args['data']['customId']]);
        $GLOBALS['customId'] = $args['data']['customId'];

        $currentUser = UserModel::getById(['id' => $args['data']['userId'], 'select' => ['user_id']]);
        $GLOBALS['login'] = $currentUser['user_id'];
        $GLOBALS['id']    = $args['data']['userId'];

        $configuration = ConfigurationModel::getByPrivilege(['privilege' => 'admin_export_seda']);
        $config = [];
        $config['exportSeda'] = !empty($configuration['value']) ? json_decode($configuration['value'], true) : [];

        $resources = ResModel::get([
            'select' => ['res_id', 'destination', 'type_id', 'subject', 'linked_resources', 'alt_identifier', 'admission_date', 'creation_date', 'modification_date', 'doc_date', 'retention_frozen', 'binding', 'docserver_id', 'path', 'filename', 'version', 'fingerprint'],
            'where'  => ['res_id in (?)'],
            'data'   => [$args['data']['resources']]
        ]);
        $resources = array_column($resources, null, 'res_id');

        $attachments = AttachmentModel::get([
            'select' => ['res_id_master', 'res_id', 'title', 'docserver_id', 'path', 'filename', 'res_id_master', 'fingerprint', 'creation_date', 'identifier', 'attachment_type'],
            'where'  => ['res_id_master in (?)', 'status in (?)'],
            'data'   => [$args['data']['resources'], ['A_TRA', 'TRA']]
        ]);

        $attachmentsData = [];
        foreach ($attachments as $attachment) {
            if (!in_array($attachment['attachment_type'], ['acknowledgement_record_management', 'reply_record_management'])) {
                $attachmentsData[$attachment['res_id_master']][] = $attachment;
            }
        }

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

        foreach ($args['data']['resources'] as $resId) {
            $resourceData = $resources[$resId];
            $entity       = $entities[$resourceData['destination']];
            $doctype      = $doctypes[$resourceData['type_id']];

            $sedaPackage = ExportSedaTrait::makeSedaPackage([
                'resource'               => $resourceData,
                'attachments'            => $attachmentsData[$resId] ?? [],
                'config'                 => $config,
                'entity'                 => $entity,
                'doctype'                => $doctype,
                'folder'                 => $args['data']['folder'],
                'archivalAgreement'      => $args['data']['archivalAgreement'],
                'entityArchiveRecipient' => $args['data']['entityArchiveRecipient']
            ]);

            $elementSend  = ExportSEDATrait::sendSedaPackage([
                'messageId'       => $sedaPackage['messageId'],
                'config'          => $config,
                'encodedFilePath' => $sedaPackage['encodedFilePath'],
                'messageFilename' => $sedaPackage['messageFilename'],
                'resId'           => $resId,
                'reference'       => $sedaPackage['reference']
            ]);
            unlink($sedaPackage['encodedFilePath']);
            if (!empty($elementSend['errors'])) {
                ResModel::update(['set' => ['status' => $args['data']['errorStatus']], 'where' => ['res_id = ?'], 'data' => [$resId]]);
                LogsController::add([
                    'isTech'    => true,
                    'moduleId'  => 'exportSeda',
                    'level'     => 'ERROR',
                    'tableName' => 'letterbox_coll',
                    'recordId'  => $resId,
                    'eventType' => "Export Seda failed : {$elementSend['errors']}",
                    'eventId'   => "resId : {$resId}"
                ]);
            } else {
                ResModel::update(['set' => ['status' => $args['data']['successStatus']], 'where' => ['res_id = ?'], 'data' => [$resId]]);
                LogsController::add([
                    'isTech'    => true,
                    'moduleId'  => 'exportSeda',
                    'level'     => 'INFO',
                    'tableName' => 'letterbox_coll',
                    'recordId'  => $resId,
                    'eventType' => "Export Seda success",
                    'eventId'   => "resId : {$resId}"
                ]);
            }
        }

        return true;
    }
}
