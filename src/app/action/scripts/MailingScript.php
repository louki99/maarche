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

use Action\controllers\ExternalSignatoryBookTrait;
use Attachment\controllers\AttachmentController;
use Attachment\models\AttachmentModel;
use Resource\models\ResModel;
use SrcCore\controllers\LogsController;
use SrcCore\models\DatabasePDO;
use User\models\UserModel;

// ARGS
// --encodedData : All data encoded in base64

MailingScript::initialize($argv);

class MailingScript
{
    public static function initialize($args)
    {
        if (array_search('--encodedData', $args) > 0) {
            $cmd = array_search('--encodedData', $args);
            $data = json_decode(base64_decode($args[$cmd+1]), true);
        }

        if (!empty($data)) {
            DatabasePDO::reset();
            new DatabasePDO(['customId' => $data['customId']]);
            $GLOBALS['customId'] = $data['customId'];
            $language = \SrcCore\models\CoreConfigModel::getLanguage();

            if (file_exists("custom/{$GLOBALS['customId']}/src/core/lang/lang-{$language}.php")) {
                require_once("custom/{$GLOBALS['customId']}/src/core/lang/lang-{$language}.php");
            }
            require_once("src/core/lang/lang-{$language}.php");


            $currentUser = UserModel::getById(['id' => $data['userId'], 'select' => ['user_id']]);
            $GLOBALS['login'] = $currentUser['user_id'];
            $GLOBALS['id']    = $data['userId'];

            if ($data['action'] == 'sendExternalSignatoryBookAction') {
                MailingScript::sendExternalSignatoryBookAction($data);
            } elseif ($data['action'] == 'generateMailing') {
                MailingScript::generateMailing($data);
            }
        }
    }

    public static function sendExternalSignatoryBookAction(array $args)
    {
        foreach ($args['resources'] as $resource) {
            $result = ExternalSignatoryBookTrait::sendExternalSignatoryBookAction($resource);

            if (!empty($result['errors'])) {
                if ($args['errorStatus'] != '_NOSTATUS_') {
                    ResModel::update(['set' => ['status' => $args['errorStatus']], 'where' => ['res_id = ?'], 'data' => [$resource['resId']]]);
                }
                LogsController::add([
                    'isTech'    => true,
                    'moduleId'  => 'resource',
                    'level'     => 'ERROR',
                    'tableName' => 'letterbox_coll',
                    'recordId'  => $resource['resId'],
                    'eventType' => "Send to external Signature Book failed : {$result['errors'][0]}",
                    'eventId'   => "resId : {$resource['resId']}"
                ]);
            } else {
                if ($args['successStatus'] != '_NOSTATUS_') {
                    ResModel::update(['set' => ['status' => $args['successStatus']], 'where' => ['res_id = ?'], 'data' => [$resource['resId']]]);
                }
                if (!empty($result['history'])) {
                    LogsController::add([
                        'isTech'    => true,
                        'moduleId'  => 'resource',
                        'level'     => 'INFO',
                        'tableName' => 'letterbox_coll',
                        'recordId'  => $resource['resId'],
                        'eventType' => "Send to external Signature Book success : {$result['history']}",
                        'eventId'   => "resId : {$resource['resId']}"
                    ]);
                }
            }
        }
    }

    public static function generateMailing(array $args)
    {
        foreach ($args['resources'] as $resource) {
            $where = ['res_id_master = ?', 'status = ?'];
            $data = [$resource['resId'], 'SEND_MASS'];

            if (!empty($resource['inSignatureBook'])) {
                $where[] = 'in_signature_book = ?';
                $data[] = true;
            }

            $attachments = AttachmentModel::get([
                'select'  => ['res_id', 'status'],
                'where'   => $where,
                'data'    => $data
            ]);

            $mailingSuccess = true;

            foreach ($attachments as $attachment) {
                $result = AttachmentController::generateMailing(['id' => $attachment['res_id'], 'userId' => $GLOBALS['id']]);

                if (!empty($result['errors'])) {
                    $mailingSuccess = false;
                    LogsController::add([
                        'isTech'    => true,
                        'moduleId'  => 'resource',
                        'level'     => 'ERROR',
                        'recordId'  => $attachment['res_id'],
                        'eventType' => "Mailing generation failed : {$result['errors']}",
                        'eventId'   => "resId : {$attachment['res_id']}"
                    ]);
                } else {
                    LogsController::add([
                        'isTech'    => true,
                        'moduleId'  => 'resource',
                        'level'     => 'INFO',
                        'recordId'  => $attachment['res_id'],
                        'eventType' => "Mailing generation success",
                        'eventId'   => "resId : {$attachment['res_id']}"
                    ]);
                }
            }

            if ($mailingSuccess && $args['successStatus'] != '_NOSTATUS_') {
                ResModel::update(['set' => ['status' => $args['successStatus']], 'where' => ['res_id = ?'], 'data' => [$resource['resId']]]);
            } elseif (!$mailingSuccess && $args['errorStatus'] != '_NOSTATUS_') {
                ResModel::update(['set' => ['status' => $args['errorStatus']], 'where' => ['res_id = ?'], 'data' => [$resource['resId']]]);
            }
        }
    }
}
