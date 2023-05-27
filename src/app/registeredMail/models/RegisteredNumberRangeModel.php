<?php

/**
* Copyright Maarch since 2008 under licence GPLv3.
* See LICENCE.txt file at the root folder for more details.
* This file is part of Maarch software.
*/

/**
 * @brief Registered Number Range Model
 * @author dev@maarch.org
 */

namespace RegisteredMail\models;

use SrcCore\models\ValidatorModel;
use SrcCore\models\DatabaseModel;

class RegisteredNumberRangeModel
{
    public static function get(array $args = [])
    {
        ValidatorModel::arrayType($args, ['select']);

        return DatabaseModel::select([
            'select'   => empty($args['select']) ? ['*'] : $args['select'],
            'table'    => ['registered_mail_number_range'],
            'where'    => empty($args['where']) ? [] : $args['where'],
            'data'     => empty($args['data']) ? [] : $args['data'],
            'order_by' => empty($args['orderBy']) ? [] : $args['orderBy'],
            'limit'    => empty($args['limit']) ? 0 : $args['limit']
        ]);
    }

    public static function getById(array $args)
    {
        ValidatorModel::notEmpty($args, ['id']);
        ValidatorModel::intVal($args, ['id']);
        ValidatorModel::arrayType($args, ['select']);

        $range = DatabaseModel::select([
            'select' => empty($args['select']) ? ['*'] : $args['select'],
            'table'  => ['registered_mail_number_range'],
            'where'  => ['id = ?'],
            'data'   => [$args['id']]
        ]);

        if (empty($range[0])) {
            return [];
        }

        return $range[0];
    }

    public static function create(array $args)
    {
        ValidatorModel::notEmpty($args, ['type', 'rangeStart', 'rangeEnd', 'status']);
        ValidatorModel::stringType($args, ['type', 'status']);
        ValidatorModel::intVal($args, ['rangeStart', 'rangeEnd', 'currentNumber']);

        $nextSequenceId = DatabaseModel::getNextSequenceValue(['sequenceId' => 'registered_mail_number_range_id_seq']);

        DatabaseModel::insert([
            'table'         => 'registered_mail_number_range',
            'columnsValues' => [
                'id'                      => $nextSequenceId,
                'type'                    => $args['type'],
                'tracking_account_number' => $args['trackingAccountNumber'],
                'range_start'             => $args['rangeStart'],
                'range_end'               => $args['rangeEnd'],
                'creator'                 => $args['creator'],
                'current_number'          => $args['currentNumber'],
                'status'                  => $args['status']
            ]
        ]);

        return $nextSequenceId;
    }

    public static function update(array $args)
    {
        ValidatorModel::notEmpty($args, ['where']);
        ValidatorModel::arrayType($args, ['set', 'where', 'data']);

        DatabaseModel::update([
            'table' => 'registered_mail_number_range',
            'set'   => empty($args['set']) ? [] : $args['set'],
            'where' => $args['where'],
            'data'  => empty($args['data']) ? [] : $args['data']
        ]);

        return true;
    }

    public static function delete(array $args)
    {
        ValidatorModel::notEmpty($args, ['where', 'data']);
        ValidatorModel::arrayType($args, ['where', 'data']);

        DatabaseModel::delete([
            'table' => 'registered_mail_number_range',
            'where' => $args['where'],
            'data'  => $args['data']
        ]);

        return true;
    }
}
