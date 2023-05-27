<?php

/**
 * Copyright Maarch since 2008 under licence GPLv3.
 * See LICENCE.txt file at the root folder for more details.
 * This file is part of Maarch software.
 *
 */

/**
 * @brief Indexing Model Field Model
 * @author dev@maarch.org
 */

namespace IndexingModel\models;

use SrcCore\models\ValidatorModel;
use SrcCore\models\DatabaseModel;

class IndexingModelFieldModel
{
    public static function get(array $args = [])
    {
        ValidatorModel::arrayType($args, ['select', 'where', 'data', 'orderBy']);
        ValidatorModel::intType($args, ['limit']);

        $fields = DatabaseModel::select([
            'select'    => empty($args['select']) ? ['*'] : $args['select'],
            'table'     => ['indexing_models_fields'],
            'where'     => empty($args['where']) ? [] : $args['where'],
            'data'      => empty($args['data']) ? [] : $args['data'],
            'order_by'  => empty($args['orderBy']) ? [] : $args['orderBy'],
            'limit'     => empty($args['limit']) ? 0 : $args['limit']
        ]);

        return $fields;
    }

    public static function create(array $args)
    {
        ValidatorModel::notEmpty($args, ['model_id', 'enabled', 'mandatory', 'identifier', 'unit']);
        ValidatorModel::stringType($args, ['enabled', 'mandatory', 'identifier', 'unit']);
        ValidatorModel::intVal($args, ['model_id']);

        DatabaseModel::insert([
            'table'         => 'indexing_models_fields',
            'columnsValues' => [
                'model_id'      => $args['model_id'],
                'identifier'    => $args['identifier'],
                'mandatory'     => $args['mandatory'],
                'enabled'       => $args['enabled'],
                'default_value' => $args['default_value'],
                'unit'          => $args['unit'],
                'allowed_values' => $args['allowed_values'],
            ]
        ]);

        return true;
    }

    public static function delete(array $args)
    {
        ValidatorModel::notEmpty($args, ['where', 'data']);
        ValidatorModel::arrayType($args, ['where', 'data']);

        DatabaseModel::delete([
            'table' => 'indexing_models_fields',
            'where' => $args['where'],
            'data'  => $args['data']
        ]);

        return true;
    }
}
