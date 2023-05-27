<?php

/**
* Copyright Maarch since 2008 under licence GPLv3.
* See LICENCE.txt file at the root folder for more details.
* This file is part of Maarch software.
*
*/

/**
* @brief Configuration Model
* @author dev@maarch.org
*/

namespace Email\models;

use SrcCore\models\DatabaseModel;
use SrcCore\models\ValidatorModel;

class EmailModel
{
    public static function get(array $args = [])
    {
        ValidatorModel::arrayType($args, ['select', 'where', 'data', 'orderBy']);
        ValidatorModel::intType($args, ['limit', 'offset']);

        $emails = DatabaseModel::select([
            'select'    => empty($args['select']) ? ['*'] : $args['select'],
            'table'     => ['emails'],
            'where'     => empty($args['where']) ? [] : $args['where'],
            'data'      => empty($args['data']) ? [] : $args['data'],
            'order_by'  => empty($args['orderBy']) ? [] : $args['orderBy'],
            'offset'    => empty($args['offset']) ? 0 : $args['offset'],
            'limit'     => empty($args['limit']) ? 0 : $args['limit']
        ]);

        return $emails;
    }

    public static function getById(array $aArgs)
    {
        ValidatorModel::notEmpty($aArgs, ['id']);
        ValidatorModel::intVal($aArgs, ['id']);
        ValidatorModel::arrayType($aArgs, ['select']);

        $email = DatabaseModel::select([
            'select'    => empty($aArgs['select']) ? ['*'] : $aArgs['select'],
            'table'     => ['emails'],
            'where'     => ['id = ?'],
            'data'      => [$aArgs['id']],
        ]);

        if (empty($email[0])) {
            return [];
        }

        return $email[0];
    }

    public static function create(array $aArgs)
    {
        ValidatorModel::notEmpty($aArgs, ['userId', 'sender', 'recipients', 'cc', 'cci', 'isHtml', 'status']);
        ValidatorModel::intVal($aArgs, ['userId']);
        ValidatorModel::stringType($aArgs, ['sender', 'recipients', 'cc', 'cci', 'object', 'body', 'messageExchangeId', 'document', 'isHtml', 'status']);

        $nextSequenceId = DatabaseModel::getNextSequenceValue(['sequenceId' => 'emails_id_seq']);

        DatabaseModel::insert([
            'table'         => 'emails',
            'columnsValues' => [
                'id'                        => $nextSequenceId,
                'user_id'                   => $aArgs['userId'],
                'sender'                    => $aArgs['sender'],
                'recipients'                => $aArgs['recipients'],
                'cc'                        => $aArgs['cc'],
                'cci'                       => $aArgs['cci'],
                'object'                    => $aArgs['object'],
                'body'                      => $aArgs['body'],
                'document'                  => $aArgs['document'],
                'is_html'                   => $aArgs['isHtml'],
                'status'                    => $aArgs['status'],
                'message_exchange_id'       => $aArgs['messageExchangeId'],
                'creation_date'             => 'CURRENT_TIMESTAMP'
            ]
        ]);

        return $nextSequenceId;
    }

    public static function update(array $aArgs)
    {
        ValidatorModel::notEmpty($aArgs, ['set', 'where', 'data']);
        ValidatorModel::arrayType($aArgs, ['set', 'where', 'data']);

        DatabaseModel::update([
            'table' => 'emails',
            'set'   => $aArgs['set'],
            'where' => $aArgs['where'],
            'data'  => $aArgs['data']
        ]);

        return true;
    }

    public static function delete(array $aArgs)
    {
        ValidatorModel::notEmpty($aArgs, ['where', 'data']);
        ValidatorModel::arrayType($aArgs, ['where', 'data']);

        DatabaseModel::delete([
            'table' => 'emails',
            'where' => $aArgs['where'],
            'data'  => $aArgs['data']
        ]);

        return true;
    }
}
