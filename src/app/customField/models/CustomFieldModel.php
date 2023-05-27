<?php

/**
 * Copyright Maarch since 2008 under licence GPLv3.
 * See LICENCE.txt file at the root folder for more details.
 * This file is part of Maarch software.
 *
 */

/**
 * @brief Custom Field Model
 * @author dev@maarch.org
 */

namespace CustomField\models;

use SrcCore\models\ValidatorModel;
use SrcCore\models\DatabaseModel;

class CustomFieldModel
{
    public static function get(array $args = [])
    {
        ValidatorModel::arrayType($args, ['select', 'where', 'data', 'orderBy']);
        ValidatorModel::intType($args, ['limit']);

        $fields = DatabaseModel::select([
            'select'    => empty($args['select']) ? ['*'] : $args['select'],
            'table'     => ['custom_fields'],
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
            'table'     => ['custom_fields'],
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

        $nextSequenceId = DatabaseModel::getNextSequenceValue(['sequenceId' => 'custom_fields_id_seq']);

        DatabaseModel::insert([
            'table'         => 'custom_fields',
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
            'table' => 'custom_fields',
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
            'table' => 'custom_fields',
            'where' => $args['where'],
            'data'  => $args['data'] ?? []
        ]);

        return true;
    }

    public static function getValuesSQL(array $args)
    {
        ValidatorModel::notEmpty($args, ['key', 'label', 'table', 'clause']);
        ValidatorModel::stringType($args, ['key', 'table', 'clause']);
        ValidatorModel::arrayType($args, ['label']);
        ValidatorModel::intVal($args, ['resId']);

        if (preg_match('/@resId/', $args['clause'])) {
            if (empty($args['resId'])) {
                return [];
            }
            $args['clause'] = str_replace('@resId', $args['resId'], $args['clause']);
        }

        $select = array_column($args['label'], 'column');
        $select[] = $args['key'];
        $rawValues = DatabaseModel::select([
            'select'    => $select,
            'table'     => [$args['table']],
            'where'     => [$args['clause']],
        ]);

        $values = [];
        foreach ($rawValues as $rawValue) {
            $label = '';
            foreach ($args['label'] as $value) {
                $label .= $value['delimiterStart'];
                $label .= $rawValue[$value['column']];
                $label .= $value['delimiterEnd'];
            }
            $values[] = ['key' => $rawValue[$args['key']], 'label' => $label];
        }

        return $values;
    }
}
