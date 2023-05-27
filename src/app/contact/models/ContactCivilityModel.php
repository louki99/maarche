<?php

/**
* Copyright Maarch since 2008 under licence GPLv3.
* See LICENCE.txt file at the root folder for more details.
* This file is part of Maarch software.
*
*/

/**
* @brief Contact Civility Model
* @author dev@maarch.org
*/

namespace Contact\models;

use SrcCore\models\DatabaseModel;
use SrcCore\models\ValidatorModel;

class ContactCivilityModel
{
    public static function get(array $args)
    {
        ValidatorModel::notEmpty($args, ['select']);
        ValidatorModel::arrayType($args, ['select', 'where', 'data', 'orderBy']);
        ValidatorModel::intType($args, ['limit']);

        $civilities = DatabaseModel::select([
            'select'    => $args['select'],
            'table'     => ['contacts_civilities'],
            'where'     => empty($args['where']) ? [] : $args['where'],
            'data'      => empty($args['data']) ? [] : $args['data'],
            'order_by'  => empty($args['orderBy']) ? [] : $args['orderBy'],
            'offset'    => empty($args['offset']) ? 0 : $args['offset'],
            'limit'     => empty($args['limit']) ? 0 : $args['limit']
        ]);

        return $civilities;
    }

    public static function getById(array $args)
    {
        ValidatorModel::notEmpty($args, ['id', 'select']);
        ValidatorModel::intVal($args, ['id']);
        ValidatorModel::arrayType($args, ['select']);

        $civility = DatabaseModel::select([
            'select'    => $args['select'],
            'table'     => ['contacts_civilities'],
            'where'     => ['id = ?'],
            'data'      => [$args['id']]
        ]);

        if (empty($civility[0])) {
            return [];
        }

        return $civility[0];
    }

    public static function create(array $args)
    {
        ValidatorModel::notEmpty($args, ['label', 'abbreviation']);
        ValidatorModel::stringType($args, ['label', 'abbreviation']);

        $nextSequenceId = DatabaseModel::getNextSequenceValue(['sequenceId' => 'contacts_civilities_id_seq']);

        DatabaseModel::insert([
            'table'         => 'contacts_civilities',
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
            'table'     => 'contacts_civilities',
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
            'table' => 'contacts_civilities',
            'where' => $args['where'],
            'data'  => $args['data']
        ]);

        return true;
    }
}
