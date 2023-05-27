<?php

/**
 * Copyright Maarch since 2008 under licence GPLv3.
 * See LICENCE.txt file at the root folder for more details.
 * This file is part of Maarch software.
 *
 */

/**
 * @brief Search Template Model
 * @author dev@maarch.org
 */

namespace Search\models;

use SrcCore\models\ValidatorModel;
use SrcCore\models\DatabaseModel;

class SearchTemplateModel
{
    public static function get(array $args = [])
    {
        ValidatorModel::arrayType($args, ['select', 'where', 'data', 'orderBy']);
        ValidatorModel::intType($args, ['limit']);

        $models = DatabaseModel::select([
            'select'    => empty($args['select']) ? ['*'] : $args['select'],
            'table'     => ['search_templates'],
            'where'     => empty($args['where']) ? [] : $args['where'],
            'data'      => empty($args['data']) ? [] : $args['data'],
            'order_by'  => empty($args['orderBy']) ? [] : $args['orderBy'],
            'limit'     => empty($args['limit']) ? 0 : $args['limit']
        ]);

        return $models;
    }

    public static function create(array $args)
    {
        ValidatorModel::notEmpty($args, ['user_id', 'label', 'query']);
        ValidatorModel::stringType($args, ['label', 'query']);
        ValidatorModel::intVal($args, ['user_id']);

        $nextSequenceId = DatabaseModel::getNextSequenceValue(['sequenceId' => 'search_templates_id_seq']);

        DatabaseModel::insert([
            'table'         => 'search_templates',
            'columnsValues' => [
                'id'            => $nextSequenceId,
                'user_id'       => $args['user_id'],
                'label'         => $args['label'],
                'creation_date' => 'CURRENT_TIMESTAMP',
                'query'         => $args['query']
            ]
        ]);

        return $nextSequenceId;
    }

    public static function delete(array $args)
    {
        ValidatorModel::notEmpty($args, ['where', 'data']);
        ValidatorModel::arrayType($args, ['where', 'data']);

        DatabaseModel::delete([
            'table' => 'search_templates',
            'where' => $args['where'],
            'data'  => $args['data']
        ]);

        return true;
    }
}
