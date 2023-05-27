<?php

/**
 * Copyright Maarch since 2008 under licence GPLv3.
 * See LICENCE.txt file at the root folder for more details.
 * This file is part of Maarch software.
 *
 */

/**
 * @brief Adr Model
 * @author dev@maarch.org
 */

namespace Convert\models;

use SrcCore\models\DatabaseModel;
use SrcCore\models\ValidatorModel;

class AdrModel
{
    public static function getDocuments(array $args)
    {
        ValidatorModel::notEmpty($args, ['select']);
        ValidatorModel::arrayType($args, ['select', 'where', 'data', 'orderBy']);
        ValidatorModel::intType($args, ['offset', 'limit']);

        $documents = DatabaseModel::select([
            'select'    => empty($args['select']) ? ['*'] : $args['select'],
            'table'     => ['adr_letterbox'],
            'where'     => empty($args['where']) ? [] : $args['where'],
            'data'      => empty($args['data']) ? [] : $args['data'],
            'order_by'  => empty($args['orderBy']) ? [] : $args['orderBy'],
            'offset'    => empty($args['offset']) ? 0 : $args['offset'],
            'limit'     => empty($args['limit']) ? 0 : $args['limit']
        ]);

        return $documents;
    }

    public static function getAttachments(array $args)
    {
        ValidatorModel::notEmpty($args, ['select']);
        ValidatorModel::arrayType($args, ['select', 'where', 'data', 'orderBy']);
        ValidatorModel::intType($args, ['offset', 'limit']);

        $attachments = DatabaseModel::select([
            'select'    => empty($args['select']) ? ['*'] : $args['select'],
            'table'     => ['adr_attachments'],
            'where'     => empty($args['where']) ? [] : $args['where'],
            'data'      => empty($args['data']) ? [] : $args['data'],
            'order_by'  => empty($args['orderBy']) ? [] : $args['orderBy'],
            'offset'    => empty($args['offset']) ? 0 : $args['offset'],
            'limit'     => empty($args['limit']) ? 0 : $args['limit']
        ]);

        return $attachments;
    }

    public static function getConvertedDocumentById(array $aArgs)
    {
        ValidatorModel::notEmpty($aArgs, ['resId', 'type', 'collId']);
        ValidatorModel::intVal($aArgs, ['resId']);
        ValidatorModel::arrayType($aArgs, ['select']);

        if ($aArgs['collId'] == 'letterbox_coll') {
            $table = "adr_letterbox";
        } else {
            $table = "adr_attachments";
        }

        $document = DatabaseModel::select([
            'select'    => empty($aArgs['select']) ? ['*'] : $aArgs['select'],
            'table'     => [$table],
            'where'     => ['res_id = ?', 'type = ?'],
            'data'      => [$aArgs['resId'], $aArgs['type']],
        ]);

        if (empty($document[0])) {
            return [];
        }

        return $document[0];
    }
    
    public static function getTypedAttachAdrByResId(array $aArgs)
    {
        ValidatorModel::notEmpty($aArgs, ['resId', 'type']);
        ValidatorModel::intVal($aArgs, ['resId']);
        ValidatorModel::stringType($aArgs, ['type']);
        ValidatorModel::arrayType($aArgs, ['select']);

        $adr = DatabaseModel::select([
            'select'    => empty($aArgs['select']) ? ['*'] : $aArgs['select'],
            'table'     => ['adr_attachments'],
            'where'     => ['res_id = ?', 'type = ?'],
            'data'      => [$aArgs['resId'], $aArgs['type']]
        ]);

        if (empty($adr[0])) {
            return [];
        }

        return $adr[0];
    }

    public static function createDocumentAdr(array $args)
    {
        ValidatorModel::notEmpty($args, ['resId', 'docserverId', 'path', 'filename', 'type', 'version']);
        ValidatorModel::stringType($args, ['docserverId', 'path', 'filename', 'type', 'fingerprint']);
        ValidatorModel::intVal($args, ['resId', 'version']);

        DatabaseModel::insert([
            'table'         => 'adr_letterbox',
            'columnsValues' => [
                'res_id'        => $args['resId'],
                'type'          => $args['type'],
                'docserver_id'  => $args['docserverId'],
                'path'          => $args['path'],
                'filename'      => $args['filename'],
                'version'       => $args['version'],
                'fingerprint'   => empty($args['fingerprint']) ? null : $args['fingerprint']
            ]
        ]);

        return true;
    }

    public static function createAttachAdr(array $aArgs)
    {
        ValidatorModel::notEmpty($aArgs, ['resId', 'docserverId', 'path', 'filename', 'type']);
        ValidatorModel::stringType($aArgs, ['docserverId', 'path', 'filename', 'type', 'fingerprint']);
        ValidatorModel::intVal($aArgs, ['resId']);

        DatabaseModel::insert([
            'table'         => 'adr_attachments',
            'columnsValues' => [
                'res_id'        => $aArgs['resId'],
                'type'          => $aArgs['type'],
                'docserver_id'  => $aArgs['docserverId'],
                'path'          => $aArgs['path'],
                'filename'      => $aArgs['filename'],
                'fingerprint'   => empty($aArgs['fingerprint']) ? null : $aArgs['fingerprint'],
            ]
        ]);
        return true;
    }

    public static function updateDocumentAdr(array $args)
    {
        ValidatorModel::notEmpty($args, ['set', 'where', 'data']);
        ValidatorModel::arrayType($args, ['set', 'where', 'data']);

        DatabaseModel::update([
            'table' => 'adr_letterbox',
            'set'   => $args['set'],
            'where' => $args['where'],
            'data'  => $args['data']
        ]);

        return true;
    }

    public static function updateAttachmentAdr(array $args)
    {
        ValidatorModel::notEmpty($args, ['set', 'where', 'data']);
        ValidatorModel::arrayType($args, ['set', 'where', 'data']);

        DatabaseModel::update([
            'table' => 'adr_attachments',
            'set'   => $args['set'],
            'where' => $args['where'],
            'data'  => $args['data']
        ]);

        return true;
    }

    public static function deleteDocumentAdr(array $args)
    {
        ValidatorModel::notEmpty($args, ['where', 'data']);
        ValidatorModel::arrayType($args, ['where', 'data']);

        DatabaseModel::delete([
            'table' => 'adr_letterbox',
            'where' => $args['where'],
            'data'  => $args['data']
        ]);

        return true;
    }

    public static function deleteAttachmentAdr(array $args)
    {
        ValidatorModel::notEmpty($args, ['where', 'data']);
        ValidatorModel::arrayType($args, ['where', 'data']);

        DatabaseModel::delete([
            'table' => 'adr_attachments',
            'where' => $args['where'],
            'data'  => $args['data']
        ]);

        return true;
    }
}
