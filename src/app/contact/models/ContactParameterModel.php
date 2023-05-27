<?php

/**
* Copyright Maarch since 2008 under licence GPLv3.
* See LICENCE.txt file at the root folder for more details.
* This file is part of Maarch software.
*
*/

/**
* @brief Contact Parameter Model
* @author dev@maarch.org
*/

namespace Contact\models;

use SrcCore\models\DatabaseModel;
use SrcCore\models\ValidatorModel;

class ContactParameterModel
{
    public static function get(array $args)
    {
        ValidatorModel::notEmpty($args, ['select']);
        ValidatorModel::arrayType($args, ['select', 'where', 'data', 'orderBy']);
        ValidatorModel::intType($args, ['limit']);

        $contacts = DatabaseModel::select([
            'select'    => $args['select'],
            'table'     => ['contacts_parameters'],
            'where'     => empty($args['where']) ? [] : $args['where'],
            'data'      => empty($args['data']) ? [] : $args['data'],
            'order_by'  => empty($args['orderBy']) ? [] : $args['orderBy'],
            'limit'     => empty($args['limit']) ? 0 : $args['limit']
        ]);

        return $contacts;
    }

    public static function getById(array $args)
    {
        ValidatorModel::notEmpty($args, ['id', 'select']);
        ValidatorModel::intVal($args, ['id']);
        ValidatorModel::arrayType($args, ['select']);

        $contact = DatabaseModel::select([
            'select'    => $args['select'],
            'table'     => ['contacts_parameters'],
            'where'     => ['id = ?'],
            'data'      => [$args['id']],
        ]);

        if (empty($contact[0])) {
            return [];
        }

        return $contact[0];
    }

    public static function create(array $args)
    {
        $nextSequenceId = DatabaseModel::getNextSequenceValue(['sequenceId' => 'contacts_parameters_id_seq']);
        $args['id'] = $nextSequenceId;

        DatabaseModel::insert([
            'table'         => 'contacts_parameters',
            'columnsValues' => $args
        ]);

        return $nextSequenceId;
    }

    public static function update(array $args)
    {
        ValidatorModel::notEmpty($args, ['set', 'where', 'data']);
        ValidatorModel::arrayType($args, ['set', 'where', 'data']);

        DatabaseModel::update([
            'table' => 'contacts_parameters',
            'set'   => $args['set'],
            'where' => $args['where'],
            'data'  => $args['data']
        ]);

        return true;
    }

    public static function delete(array $args)
    {
        ValidatorModel::notEmpty($args, ['where', 'data']);
        ValidatorModel::arrayType($args, ['where', 'data']);

        DatabaseModel::delete([
            'table' => 'contacts_parameters',
            'where' => $args['where'],
            'data'  => $args['data']
        ]);

        return true;
    }
}
