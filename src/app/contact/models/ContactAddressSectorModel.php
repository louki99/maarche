<?php

/**
* Copyright Maarch since 2008 under licence GPLv3.
* See LICENCE.txt file at the root folder for more details.
* This file is part of Maarch software.
*
*/

/**
* @brief Contact Address Sector Model
* @author dev@maarch.org
*/

namespace Contact\models;

use SrcCore\models\DatabaseModel;
use SrcCore\models\ValidatorModel;

class ContactAddressSectorModel
{
    public static function get(array $args)
    {
        ValidatorModel::notEmpty($args, ['select']);
        ValidatorModel::arrayType($args, ['select', 'where', 'data', 'orderBy']);
        ValidatorModel::intType($args, ['limit']);

        $sectors = DatabaseModel::select([
            'select'    => $args['select'],
            'table'     => ['address_sectors'],
            'where'     => empty($args['where']) ? [] : $args['where'],
            'data'      => empty($args['data']) ? [] : $args['data'],
            'order_by'  => empty($args['orderBy']) ? [] : $args['orderBy'],
            'offset'    => empty($args['offset']) ? 0 : $args['offset'],
            'limit'     => empty($args['limit']) ? 0 : $args['limit']
        ]);

        return $sectors;
    }

    public static function getById(array $args)
    {
        ValidatorModel::notEmpty($args, ['id', 'select']);
        ValidatorModel::intVal($args, ['id']);
        ValidatorModel::arrayType($args, ['select']);

        $sector = DatabaseModel::select([
            'select'    => $args['select'],
            'table'     => ['address_sectors'],
            'where'     => ['id = ?'],
            'data'      => [$args['id']]
        ]);

        if (empty($sector[0])) {
            return [];
        }

        return $sector[0];
    }

    public static function create(array $args)
    {
        ValidatorModel::notEmpty($args, ['label', 'abbreviation']);
        ValidatorModel::stringType($args, ['label', 'abbreviation']);

        $nextSequenceId = DatabaseModel::getNextSequenceValue(['sequenceId' => 'address_sectors_id_seq']);

        DatabaseModel::insert([
            'table'         => 'address_sectors',
            'columnsValues' => [
                'id'            => $nextSequenceId,
                'label'         => $args['label'],
                'abbreviation'  => $args['abbreviation']
            ]
        ]);

        return $nextSequenceId;
    }

    public static function update(array $args)
    {
        ValidatorModel::notEmpty($args, ['set' ,'where', 'data']);
        ValidatorModel::arrayType($args, ['set', 'where', 'data']);

        DatabaseModel::update([
            'table'     => 'address_sectors',
            'set'       => $args['set'],
            'where'     => $args['where'],
            'data'      => $args['data']
        ]);

        return true;
    }

    public static function delete(array $args)
    {
        ValidatorModel::notEmpty($args, ['where', 'data']);
        ValidatorModel::arrayType($args, ['where', 'data']);

        DatabaseModel::delete([
            'table' => 'address_sectors',
            'where' => $args['where'],
            'data'  => $args['data']
        ]);

        return true;
    }
}
