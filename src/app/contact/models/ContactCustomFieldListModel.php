<?php

/**
 * Copyright Maarch since 2008 under licence GPLv3.
 * See LICENCE.txt file at the root folder for more details.
 * This file is part of Maarch software.
 *
 */

/**
 * @brief Contact Custom Field List Model
 * @author dev@maarch.org
 */

namespace Contact\models;

use SrcCore\models\ValidatorModel;
use SrcCore\models\DatabaseModel;

class ContactCustomFieldListModel
{
    public static function get(array $args = [])
    {
        ValidatorModel::arrayType($args, ['select', 'where', 'data', 'orderBy']);
        ValidatorModel::intType($args, ['limit']);

        $fields = DatabaseModel::select([
            'select'    => empty($args['select']) ? ['*'] : $args['select'],
            'table'     => ['contacts_custom_fields_list'],
            'where'     => empty($args['where']) ? [] : $args['where'],
            'data'      => empty($args['data']) ? [] : $args['data'],
            'order_by'  => empty($args['orderBy']) ? [] : $args['orderBy'],
            'limit'     => empty($args['limit']) ? 0 : $args['limit']
        ]);

        return $fields;
    }

    public static function getById(array $args)
    {
        ValidatorModel::notEmpty($args, ['id']);
        ValidatorModel::intVal($args, ['id']);
        ValidatorModel::arrayType($args, ['select']);

        $field = DatabaseModel::select([
            'select'    => empty($args['select']) ? ['*'] : $args['select'],
            'table'     => ['contacts_custom_fields_list'],
            'where'     => ['id = ?'],
            'data'      => [$args['id']],
        ]);

        if (empty($field[0])) {
            return [];
        }

        return $field[0];
    }

    public static function create(array $args)
    {
        ValidatorModel::notEmpty($args, ['label', 'type']);
        ValidatorModel::stringType($args, ['label', 'type', 'values']);

        $nextSequenceId = DatabaseModel::getNextSequenceValue(['sequenceId' => 'contacts_custom_fields_list_id_seq']);

        DatabaseModel::insert([
            'table'         => 'contacts_custom_fields_list',
            'columnsValues' => [
                'id'        => $nextSequenceId,
                'label'     => $args['label'],
                'type'      => $args['type'],
                'values'    => $args['values']
            ]
        ]);

        return $nextSequenceId;
    }

    public static function update(array $args)
    {
        ValidatorModel::notEmpty($args, ['set', 'where', 'data']);
        ValidatorModel::arrayType($args, ['set', 'where', 'data']);

        DatabaseModel::update([
            'table' => 'contacts_custom_fields_list',
            'set'   => $args['set'],
            'where' => $args['where'],
            'data'  => $args['data']
        ]);

        return true;
    }

    public static function delete(array $args)
    {
        ValidatorModel::notEmpty($args, ['where']);
        ValidatorModel::arrayType($args, ['where', 'data']);

        DatabaseModel::delete([
            'table' => 'contacts_custom_fields_list',
            'where' => $args['where'],
            'data'  => $args['data'] ?? []
        ]);

        return true;
    }
}
