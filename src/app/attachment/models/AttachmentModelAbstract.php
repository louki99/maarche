<?php

/**
* Copyright Maarch since 2008 under licence GPLv3.
* See LICENCE.txt file at the root folder for more details.
* This file is part of Maarch software.
*
*/

/**
 * @brief Attachment Model Abstract
 * @author dev@maarch.org
 */

namespace Attachment\models;

use SrcCore\models\DatabaseModel;
use SrcCore\models\ValidatorModel;

abstract class AttachmentModelAbstract
{
    public static function get(array $aArgs)
    {
        ValidatorModel::notEmpty($aArgs, ['select']);
        ValidatorModel::arrayType($aArgs, ['select', 'where', 'data', 'orderBy', 'groupBy']);
        ValidatorModel::intType($aArgs, ['limit']);

        $attachments = DatabaseModel::select([
            'select'    => $aArgs['select'],
            'table'     => ['res_attachments'],
            'where'     => empty($aArgs['where']) ? [] : $aArgs['where'],
            'data'      => empty($aArgs['data']) ? [] : $aArgs['data'],
            'order_by'  => empty($aArgs['orderBy']) ? [] : $aArgs['orderBy'],
            'groupBy'   => empty($aArgs['groupBy']) ? [] : $aArgs['groupBy'],
            'limit'     => empty($aArgs['limit']) ? 0 : $aArgs['limit']
        ]);

        return $attachments;
    }

    public static function getById(array $aArgs)
    {
        ValidatorModel::notEmpty($aArgs, ['id']);
        ValidatorModel::intVal($aArgs, ['id']);
        ValidatorModel::arrayType($aArgs, ['select']);

        $attachment = DatabaseModel::select([
            'select'    => empty($aArgs['select']) ? ['*'] : $aArgs['select'],
            'table'     => ['res_attachments'],
            'where'     => ['res_id = ?'],
            'data'      => [$aArgs['id']],
        ]);

        if (empty($attachment[0])) {
            return [];
        }

        return $attachment[0];
    }

    public static function create(array $args)
    {
        ValidatorModel::notEmpty($args, ['format', 'typist', 'creation_date', 'docserver_id', 'path', 'filename', 'fingerprint', 'filesize', 'status', 'relation']);
        ValidatorModel::stringType($args, ['format', 'creation_date', 'docserver_id', 'path', 'filename', 'fingerprint', 'status']);
        ValidatorModel::intVal($args, ['filesize', 'relation', 'typist']);

        if (empty($args['res_id'])) {
            $nextSequenceId = DatabaseModel::getNextSequenceValue(['sequenceId' => 'res_attachment_res_id_seq']);
            $args['res_id'] = $nextSequenceId;
        }

        DatabaseModel::insert([
            'table'         => 'res_attachments',
            'columnsValues' => $args
        ]);

        return $args['res_id'];
    }

    public static function update(array $args)
    {
        ValidatorModel::notEmpty($args, ['where', 'data']);
        ValidatorModel::arrayType($args, ['set', 'postSet', 'where', 'data']);

        DatabaseModel::update([
            'table'   => 'res_attachments',
            'set'     => $args['set'] ?? [],
            'postSet' => $args['postSet'] ?? [],
            'where'   => $args['where'],
            'data'    => $args['data']
        ]);

        return true;
    }

    public static function freezeAttachment(array $aArgs)
    {
        ValidatorModel::notEmpty($aArgs, ['resId', 'externalId']);
        ValidatorModel::intType($aArgs, ['resId']);

        $aAttachment = DatabaseModel::select([
            'select'    => ['external_id'],
            'table'     => ['res_attachments'],
            'where'     => ['res_id = ?'],
            'data'      => [$aArgs['resId']],
        ]);

        $externalId = json_decode($aAttachment[0]['external_id'], true);
        $externalId['signatureBookId'] = empty($aArgs['externalId']) ? null : $aArgs['externalId'];

        DatabaseModel::update([
            'table'     => 'res_attachments',
            'set'       => ['status' => 'FRZ', 'external_id' => json_encode($externalId)],
            'where'     => ['res_id = ?'],
            'data'      => [$aArgs['resId']]
        ]);

        return true;
    }

    public static function setInSignatureBook(array $aArgs)
    {
        ValidatorModel::notEmpty($aArgs, ['id']);
        ValidatorModel::intVal($aArgs, ['id']);
        ValidatorModel::boolType($aArgs, ['inSignatureBook']);

        if ($aArgs['inSignatureBook']) {
            $aArgs['inSignatureBook'] =  'true';
        } else {
            $aArgs['inSignatureBook'] =  'false';
        }

        DatabaseModel::update([
            'table'     => 'res_attachments',
            'set'       => [
                'in_signature_book'   => $aArgs['inSignatureBook']
            ],
            'where'     => ['res_id = ?'],
            'data'      => [$aArgs['id']],
        ]);

        return true;
    }

    public static function setInSendAttachment(array $aArgs)
    {
        ValidatorModel::notEmpty($aArgs, ['id']);
        ValidatorModel::intVal($aArgs, ['id']);
        ValidatorModel::boolType($aArgs, ['inSendAttachment']);

        if ($aArgs['inSendAttachment']) {
            $aArgs['inSendAttachment'] =  'true';
        } else {
            $aArgs['inSendAttachment'] =  'false';
        }

        DatabaseModel::update([
            'table'     => 'res_attachments',
            'set'       => [
                'in_send_attach'   => $aArgs['inSendAttachment']
            ],
            'where'     => ['res_id = ?'],
            'data'      => [$aArgs['id']],
        ]);

        return true;
    }

    public static function hasAttachmentsSignedByResId(array $args)
    {
        ValidatorModel::notEmpty($args, ['resId', 'userId']);
        ValidatorModel::intVal($args, ['resId', 'userId']);

        $attachments = DatabaseModel::select([
            'select'    => [1],
            'table'     => ['res_attachments'],
            'where'     => ['res_id_master = ?', 'signatory_user_serial_id = ?'],
            'data'      => [$args['resId'], $args['userId']],
        ]);

        if (empty($attachments)) {
            return false;
        }

        return true;
    }

    public static function delete(array $args)
    {
        ValidatorModel::notEmpty($args, ['where', 'data']);
        ValidatorModel::arrayType($args, ['where', 'data']);

        DatabaseModel::update([
            'table' => 'res_attachments',
            'set'   => [
                'status'    => 'DEL'
            ],
            'where' => $args['where'],
            'data'  => $args['data']
        ]);

        return true;
    }
}
