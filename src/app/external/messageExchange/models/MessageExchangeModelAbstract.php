<?php

/**
 * Copyright Maarch since 2008 under licence GPLv3.
 * See LICENCE.txt file at the root folder for more details.
 * This file is part of Maarch software.
 *
 */

/**
 * @brief User Model
 * @author dev@maarch.org
 */

namespace MessageExchange\models;

use SrcCore\models\DatabaseModel;
use SrcCore\models\ValidatorModel;
use Docserver\models\DocserverModel;
use Docserver\models\DocserverTypeModel;
use Resource\controllers\StoreController;
use Docserver\controllers\DocserverController;

abstract class MessageExchangeModelAbstract
{
    public static function get(array $args = [])
    {
        ValidatorModel::arrayType($args, ['select', 'where', 'data', 'orderBy']);
        ValidatorModel::intType($args, ['limit', 'offset']);

        $emails = DatabaseModel::select([
            'select'    => empty($args['select']) ? ['*'] : $args['select'],
            'table'     => ['message_exchange'],
            'where'     => empty($args['where']) ? [] : $args['where'],
            'data'      => empty($args['data']) ? [] : $args['data'],
            'order_by'  => empty($args['orderBy']) ? [] : $args['orderBy'],
            'offset'    => empty($args['offset']) ? 0 : $args['offset'],
            'limit'     => empty($args['limit']) ? 0 : $args['limit']
        ]);

        return $emails;
    }

    public static function getMessageByReference($aArgs = [])
    {
        ValidatorModel::notEmpty($aArgs, ['reference']);
        ValidatorModel::arrayType($aArgs, ['orderBy']);

        $aReturn = DatabaseModel::select([
            'select'   => empty($aArgs['select']) ? ['*'] : $aArgs['select'],
            'table'    => ['message_exchange'],
            'where'    => ['reference = ?'],
            'data'     => [$aArgs['reference']],
            'order_by' => empty($aArgs['orderBy']) ? [] : $aArgs['orderBy']
        ]);

        if (empty($aReturn[0])) {
            return [];
        }
       
        return $aReturn[0];
    }

    public static function getMessageByIdentifier($aArgs = [])
    {
        ValidatorModel::notEmpty($aArgs, ['messageId']);

        $aReturn = DatabaseModel::select(
            [
            'select' => empty($aArgs['select']) ? ['*'] : $aArgs['select'],
            'table'  => ['message_exchange'],
            'where'  => ['message_id = ?'],
            'data'   => [$aArgs['messageId']]
            ]
        );

        if (empty($aReturn[0])) {
            return [];
        }
       
        return $aReturn[0];
    }

    public static function updateStatusMessage(array $aArgs)
    {
        ValidatorModel::notEmpty($aArgs, ['status','messageId']);

        DatabaseModel::update([
            'table'     => 'message_exchange',
            'set'       => [
                'status'     => $aArgs['status']
            ],
            'where'     => ['message_id = ?'],
            'data'      => [$aArgs['messageId']]
        ]);

        return true;
    }

    public static function delete(array $args)
    {
        ValidatorModel::notEmpty($args, ['where', 'data']);
        ValidatorModel::arrayType($args, ['where', 'data']);

        DatabaseModel::delete([
            'table' => 'message_exchange',
            'where' => $args['where'],
            'data'  => $args['data']
        ]);

        return true;
    }

    public static function deleteUnitIdentifier(array $args)
    {
        ValidatorModel::notEmpty($args, ['where', 'data']);
        ValidatorModel::arrayType($args, ['where', 'data']);

        DatabaseModel::delete([
            'table' => 'unit_identifier',
            'where' => $args['where'],
            'data'  => $args['data']
        ]);

        return true;
    }

    public static function updateOperationDateMessage(array $aArgs)
    {
        ValidatorModel::notEmpty($aArgs, ['operation_date','message_id']);

        DatabaseModel::update([
            'table'     => 'message_exchange',
            'set'       => [
                'operation_date' => $aArgs['operation_date']
            ],
            'where'     => ['message_id = ?'],
            'data'      => [$aArgs['message_id']]
        ]);

        return true;
    }

    public static function updateReceptionDateMessage(array $aArgs)
    {
        ValidatorModel::notEmpty($aArgs, ['reception_date','message_id']);

        DatabaseModel::update([
            'table'     => 'message_exchange',
            'set'       => [
                'reception_date' => $aArgs['reception_date']
            ],
            'where'     => ['message_id = ?'],
            'data'      => [$aArgs['message_id']]
        ]);

        return true;
    }

    /*** Generates a local unique identifier
    @return string The unique id*/
    public static function generateUniqueId()
    {
        $parts = explode('.', microtime(true));
        $sec   = $parts[0];
        if (!isset($parts[1])) {
            $msec = 0;
        } else {
            $msec = $parts[1];
        }
        $uniqueId = str_pad(base_convert($sec, 10, 36), 6, '0', STR_PAD_LEFT) . str_pad(base_convert($msec, 10, 16), 4, '0', STR_PAD_LEFT);
        $uniqueId .= str_pad(base_convert(mt_rand(), 10, 36), 6, '0', STR_PAD_LEFT);

        return $uniqueId;
    }

    public static function insertMessage($args = [])
    {
        $messageObject = $args['data'];
        $type          = $args['type'];
        $aArgs         = $args['dataExtension'];
        $userId        = $args['userId'];

        if (empty($messageObject->messageId)) {
            $messageObject->messageId = self::generateUniqueId();
        }

        if (empty($aArgs['status'])) {
            $status = "sent";
        } else {
            $status = $aArgs['status'];
        }

        if (empty($aArgs['fullMessageObject'])) {
            $messageObjectToSave = $messageObject;
        } else {
            $messageObjectToSave = $aArgs['fullMessageObject'];
        }

        if (empty($aArgs['resIdMaster'])) {
            $resIdMaster = null;
        } else {
            $resIdMaster = $aArgs['resIdMaster'];
        }

        if (empty($aArgs['filePath'])) {
            $filePath = null;
        } else {
            $filePath = $aArgs['filePath'];
            $filesize = filesize($filePath);

            //Store resource on docserver
            $resource = file_get_contents($filePath);
            $pathInfo = pathinfo($filePath);
            $storeResult = DocserverController::storeResourceOnDocServer([
                'collId'            => 'archive_transfer_coll',
                'docserverTypeId'   => 'ARCHIVETRANSFER',
                'encodedResource'   => base64_encode($resource),
                'format'            => $pathInfo['extension']
            ]);

            if (!empty($storeResult['errors'])) {
                return ['error' => $storeResult['errors']];
            }
            $docserverId = $storeResult['docserver_id'];
            $filepath    = $storeResult['destination_dir'];
            $filename    = $storeResult['file_destination_name'];
            $docserver   = DocserverModel::getByDocserverId(['docserverId' => $docserverId]);

            $docserverType = DocserverTypeModel::getById([
                'id' => $docserver['docserver_type_id']
            ]);

            $fingerprint = StoreController::getFingerPrint([
                'filePath' => $filePath,
                'mode'     => $docserverType['fingerprint_mode'],
            ]);
        }

        try {
            DatabaseModel::insert([
                'table'         => 'message_exchange',
                'columnsValues' => [
                    'message_id'                   => $messageObject->messageId,
                    'schema'                       => "2.1",
                    'type'                         => $type,
                    'status'                       => $status,
                    'date'                         => $messageObject->date,
                    'reference'                    => $messageObject->MessageIdentifier->value,
                    'account_id'                   => $userId,
                    'sender_org_identifier'        => $messageObject->TransferringAgency->Identifier->value,
                    'sender_org_name'              => $aArgs['SenderOrgNAme'],
                    'recipient_org_identifier'     => $messageObject->ArchivalAgency->Identifier->value,
                    'recipient_org_name'           => $aArgs['RecipientOrgNAme'],
                    'archival_agreement_reference' => $messageObject->ArchivalAgreement->value,
                    'reply_code'                   => $messageObject->ReplyCode,
                    'size'                         => '0',
                    'data'                         => json_encode($messageObjectToSave),
                    'active'                       => 'true',
                    'archived'                     => 'false',
                    'res_id_master'                => $resIdMaster,
                    'docserver_id'                 => $docserverId,
                    'path'                         => $filepath,
                    'filename'                     => $filename,
                    'fingerprint'                  => $fingerprint,
                    'filesize'                     => $filesize
                ]
                ]);
        } catch (\Exception $e) {
            return ['error' => $e];
        }

        return ['messageId' => $messageObject->messageId];
    }

    public static function getUnitIdentifierByMessageId(array $args)
    {
        ValidatorModel::notEmpty($args, ['messageId']);
        ValidatorModel::stringType($args, ['messageId']);

        $messages = DatabaseModel::select([
                'select' => empty($args['select']) ? ['*'] : $args['select'],
                'table'  => ['unit_identifier'],
                'where'  => ['message_id = ?'],
                'data'   => [$args['messageId']]
            ]);

        return $messages;
    }

    public static function getUnitIdentifierByResId(array $args)
    {
        ValidatorModel::notEmpty($args, ['resId']);

        $messages = DatabaseModel::select([
                'select' => empty($args['select']) ? ['*'] : $args['select'],
                'table'  => ['unit_identifier'],
                'where'  => ['res_id = ?'],
                'data'   => [$args['resId']]
            ]);

        return $messages;
    }

    public static function insertUnitIdentifier(array $args)
    {
        ValidatorModel::notEmpty($args, ['messageId', 'tableName', 'resId']);
        ValidatorModel::stringType($args, ['messageId', 'tableName', 'disposition']);
        ValidatorModel::intVal($args, ['resId']);

        $messages = DatabaseModel::insert([
            'table'         => 'unit_identifier',
            'columnsValues' => [
                'message_id'   => $args['messageId'],
                'tablename'    => $args['tableName'],
                'res_id'       => $args['resId'],
                'disposition'  => $args['disposition']
            ]
        ]);

        return $messages;
    }
}
