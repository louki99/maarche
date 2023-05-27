<?php

/**
* Copyright Maarch since 2008 under licence GPLv3.
* See LICENCE.txt file at the root folder for more details.
* This file is part of Maarch software.
*/

/**
 * @brief Registered Mail Model
 * @author dev@maarch.org
 */

namespace RegisteredMail\models;

use SrcCore\models\ValidatorModel;
use SrcCore\models\DatabaseModel;

class RegisteredMailModel
{
    public static function get(array $args = [])
    {
        ValidatorModel::arrayType($args, ['select']);

        return DatabaseModel::select([
            'select'   => empty($args['select']) ? ['*'] : $args['select'],
            'table'    => ['registered_mail_resources'],
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

        $item = DatabaseModel::select([
            'select' => empty($args['select']) ? ['*'] : $args['select'],
            'table'  => ['registered_mail_resources'],
            'where'  => ['id = ?'],
            'data'   => [$args['id']]
        ]);

        if (empty($item[0])) {
            return [];
        }

        return $item[0];
    }

    public static function getByResId(array $args)
    {
        ValidatorModel::notEmpty($args, ['resId']);
        ValidatorModel::intVal($args, ['resId']);
        ValidatorModel::arrayType($args, ['select']);

        $item = DatabaseModel::select([
            'select' => empty($args['select']) ? ['*'] : $args['select'],
            'table'  => ['registered_mail_resources'],
            'where'  => ['res_id = ?'],
            'data'   => [$args['resId']]
        ]);

        if (empty($item[0])) {
            return [];
        }

        return $item[0];
    }

    public static function create(array $args)
    {
        ValidatorModel::notEmpty($args, ['res_id', 'type', 'issuing_site', 'warranty', 'recipient', 'number']);

        DatabaseModel::insert([
            'table'         => 'registered_mail_resources',
            'columnsValues' => [
                'res_id'        => $args['res_id'],
                'type'          => $args['type'],
                'issuing_site'  => $args['issuing_site'],
                'warranty'      => $args['warranty'],
                'letter'        => $args['letter'] ?? 'false',
                'recipient'     => $args['recipient'],
                'number'        => $args['number'],
                'reference'     => $args['reference'] ?? null,
                'generated'     => $args['generated'] ?? 'false',
            ]
        ]);

        return true;
    }

    public static function update(array $args)
    {
        ValidatorModel::notEmpty($args, ['where', 'data']);
        ValidatorModel::arrayType($args, ['set', 'where', 'data']);

        DatabaseModel::update([
            'table'     => 'registered_mail_resources',
            'set'       => $args['set'],
            'where'     => $args['where'],
            'data'      => $args['data']
        ]);

        return true;
    }

    public static function getWithResources(array $args = [])
    {
        ValidatorModel::arrayType($args, ['select', 'where', 'data']);

        return DatabaseModel::select([
            'select'    => empty($args['select']) ? ['*'] : $args['select'],
            'table'     => ['registered_mail_resources', 'res_letterbox'],
            'left_join' => ['registered_mail_resources.res_id = res_letterbox.res_id'],
            'where'     => empty($args['where']) ? [] : $args['where'],
            'data'      => empty($args['data']) ? [] : $args['data'],
            'groupBy'   => empty($args['groupBy']) ? [] : $args['groupBy'],
            'order_by'  => empty($args['orderBy']) ? [] : $args['orderBy'],
        ]);
    }
}
