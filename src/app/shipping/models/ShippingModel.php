<?php

/**
 * Copyright Maarch since 2008 under licence GPLv3.
 * See LICENCE.txt file at the root folder for more details.
 * This file is part of Maarch software.
 *
 */

/**
* @brief   Shipping Model
* @author  dev@maarch.org
*/

namespace Shipping\models;

use SrcCore\models\ValidatorModel;
use SrcCore\models\DatabaseModel;

class ShippingModel
{
    public static function create(array $args)
    {
        ValidatorModel::notEmpty($args, ['userId', 'sendingId', 'documentId', 'documentType', 'accountId', 'recipients', 'actionId']);
        ValidatorModel::intVal($args, ['userId', 'documentId', 'recipientEntityId', 'actionId']);
        ValidatorModel::stringType($args, ['sendingId', 'accountId', 'documentType', 'recipients']);

        DatabaseModel::insert([
            'table'         => 'shippings',
            'columnsValues' => [
                'user_id'               => $args['userId'],
                'sending_id'            => $args['sendingId'],
                'document_id'           => $args['documentId'],
                'document_type'         => $args['documentType'],
                'options'               => $args['options'],
                'fee'                   => $args['fee'],
                'recipient_entity_id'   => $args['recipientEntityId'],
                'recipients'            => $args['recipients'],
                'account_id'            => $args['accountId'],
                'creation_date'         => 'CURRENT_TIMESTAMP',
                'action_id'             => $args['actionId']
            ]
        ]);

        return true;
    }

    public static function get(array $args)
    {
        ValidatorModel::notEmpty($args, ['select']);
        ValidatorModel::arrayType($args, ['select', 'where', 'data', 'orderBy']);
        ValidatorModel::intType($args, ['limit']);

        $shippings = DatabaseModel::select([
            'select'    => $args['select'],
            'table'     => ['shippings'],
            'where'     => empty($args['where']) ? [] : $args['where'],
            'data'      => empty($args['data']) ? [] : $args['data'],
            'order_by'  => empty($args['orderBy']) ? [] : $args['orderBy'],
            'offset'    => empty($args['offset']) ? 0 : $args['offset'],
            'limit'     => empty($args['limit']) ? 0 : $args['limit']
        ]);

        return $shippings;
    }

    public static function getByRecipientId(array $args)
    {
        ValidatorModel::notEmpty($args, ['select', 'recipientId']);
        ValidatorModel::arrayType($args, ['select', 'orderBy']);
        ValidatorModel::intType($args, ['limit']);
        ValidatorModel::stringType($args, ['recipientId']);

        // jsonb @@ jsonpath -> boolean
        // @@ executes a check on a json value
        // check on recipients that AT LEAST ONE of them has a recipientId equal to $args['recipientId']
        // see PostgreSQL doc on json functions and operators
        // https://www.postgresql.org/docs/14/functions-json.html
        $args['where'] = [
            'recipients @@ \'$[*].recipientId == "' . $args['recipientId'] . '"\''
        ];

        return ShippingModel::get([
            'select'  => $args['select'],
            'where'   => $args['where'],
            'orderBy' => $args['orderBy'],
            'limit'   => $args['limit']
        ]);
    }

    public static function update(array $args)
    {
        ValidatorModel::notEmpty($args, ['where', 'data']);
        ValidatorModel::arrayType($args, ['set', 'postSet', 'where', 'data']);

        DatabaseModel::update([
            'table'     => 'shippings',
            'set'       => $args['set'] ?? null,
            'postSet'   => $args['postSet'] ?? null,
            'where'     => $args['where'],
            'data'      => $args['data']
        ]);

        return true;
    }
}
