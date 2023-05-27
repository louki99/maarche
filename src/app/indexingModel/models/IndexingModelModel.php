<?php

/**
 * Copyright Maarch since 2008 under licence GPLv3.
 * See LICENCE.txt file at the root folder for more details.
 * This file is part of Maarch software.
 *
 */

/**
 * @brief Indexing Model Model
 * @author dev@maarch.org
 */

namespace IndexingModel\models;

use SrcCore\models\ValidatorModel;
use SrcCore\models\DatabaseModel;

class IndexingModelModel
{
    public static function get(array $args = [])
    {
        ValidatorModel::arrayType($args, ['select', 'where', 'data', 'orderBy']);
        ValidatorModel::intType($args, ['limit']);

        $models = DatabaseModel::select([
            'select'    => empty($args['select']) ? ['*'] : $args['select'],
            'table'     => ['indexing_models'],
            'where'     => empty($args['where']) ? [] : $args['where'],
            'data'      => empty($args['data']) ? [] : $args['data'],
            'order_by'  => empty($args['orderBy']) ? [] : $args['orderBy'],
            'limit'     => empty($args['limit']) ? 0 : $args['limit']
        ]);

        return $models;
    }

    public static function getById(array $args)
    {
        ValidatorModel::notEmpty($args, ['id']);
        ValidatorModel::intVal($args, ['id']);
        ValidatorModel::arrayType($args, ['select']);

        $model = DatabaseModel::select([
            'select'    => empty($args['select']) ? ['*'] : $args['select'],
            'table'     => ['indexing_models'],
            'where'     => ['id = ?'],
            'data'      => [$args['id']],
        ]);

        if (empty($model[0])) {
            return [];
        }

        return $model[0];
    }

    public static function create(array $args)
    {
        ValidatorModel::notEmpty($args, ['label', 'category', 'default', 'owner', 'private', 'mandatoryFile']);
        ValidatorModel::stringType($args, ['label', 'category', 'default', 'private']);
        ValidatorModel::intVal($args, ['owner', 'master']);

        $nextSequenceId = DatabaseModel::getNextSequenceValue(['sequenceId' => 'indexing_models_id_seq']);

        DatabaseModel::insert([
            'table'         => 'indexing_models',
            'columnsValues' => [
                'id'             => $nextSequenceId,
                'label'          => $args['label'],
                'category'       => $args['category'],
                '"default"'      => $args['default'],
                'owner'          => $args['owner'],
                'private'        => $args['private'],
                'mandatory_file' => $args['mandatoryFile'],
                'master'         => $args['master']
            ]
        ]);

        return $nextSequenceId;
    }

    public static function update(array $args)
    {
        ValidatorModel::notEmpty($args, ['set', 'where', 'data']);
        ValidatorModel::arrayType($args, ['set', 'where', 'data']);

        DatabaseModel::update([
            'table' => 'indexing_models',
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
            'table' => 'indexing_models',
            'where' => $args['where'],
            'data'  => $args['data']
        ]);

        return true;
    }
}
