<?php

/**
 * Copyright Maarch since 2008 under licence GPLv3.
 * See LICENCE.txt file at the root folder for more details.
 * This file is part of Maarch software.
 *
 */

/**
 * @brief Tile Model
 * @author dev@maarch.org
 */

namespace Home\models;

use SrcCore\models\ValidatorModel;
use SrcCore\models\DatabaseModel;

class TileModel
{
    public static function get(array $args = [])
    {
        ValidatorModel::arrayType($args, ['select', 'where', 'data', 'orderBy']);
        ValidatorModel::intType($args, ['limit']);

        $tiles = DatabaseModel::select([
            'select'    => empty($args['select']) ? ['*'] : $args['select'],
            'table'     => ['tiles'],
            'where'     => empty($args['where']) ? [] : $args['where'],
            'data'      => empty($args['data']) ? [] : $args['data'],
            'order_by'  => empty($args['orderBy']) ? [] : $args['orderBy'],
            'groupBy'   => empty($args['groupBy']) ? [] : $args['groupBy'],
            'limit'     => empty($args['limit']) ? 0 : $args['limit']
        ]);

        return $tiles;
    }

    public static function getById(array $args)
    {
        ValidatorModel::notEmpty($args, ['id']);
        ValidatorModel::intVal($args, ['id']);
        ValidatorModel::arrayType($args, ['select']);

        $tile = DatabaseModel::select([
            'select'    => empty($args['select']) ? ['*'] : $args['select'],
            'table'     => ['tiles'],
            'where'     => ['id = ?'],
            'data'      => [$args['id']],
        ]);

        if (empty($tile[0])) {
            return [];
        }

        return $tile[0];
    }

    public static function create(array $args)
    {
        ValidatorModel::notEmpty($args, ['user_id', 'type', 'view']);
        ValidatorModel::stringType($args, ['type', 'view', 'parameters', 'color']);
        ValidatorModel::intVal($args, ['user_id', 'position']);

        $nextSequenceId = DatabaseModel::getNextSequenceValue(['sequenceId' => 'tiles_id_seq']);

        DatabaseModel::insert([
            'table'         => 'tiles',
            'columnsValues' => [
                'id'            => $nextSequenceId,
                'user_id'       => $args['user_id'],
                'type'          => $args['type'],
                'view'          => $args['view'],
                'position'      => $args['position'],
                'color'         => $args['color'],
                'parameters'    => $args['parameters'],
            ]
        ]);

        return $nextSequenceId;
    }

    public static function update(array $args)
    {
        ValidatorModel::notEmpty($args, ['where']);
        ValidatorModel::arrayType($args, ['set', 'where', 'data']);

        DatabaseModel::update([
            'table'     => 'tiles',
            'set'       => empty($args['set']) ? [] : $args['set'],
            'where'     => $args['where'],
            'data'      => empty($args['data']) ? [] : $args['data']
        ]);

        return true;
    }

    public static function delete(array $args)
    {
        ValidatorModel::notEmpty($args, ['where', 'data']);
        ValidatorModel::arrayType($args, ['where', 'data']);

        DatabaseModel::delete([
            'table' => 'tiles',
            'where' => $args['where'],
            'data'  => $args['data']
        ]);

        return true;
    }
}
