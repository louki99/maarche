<?php

/**
 * Copyright Maarch since 2008 under licence GPLv3.
 * See LICENCE.txt file at the root folder for more details.
 * This file is part of Maarch software.
 *
 */

/**
 * @brief Tag Model
 * @author dev@maarch.org
 */

namespace Tag\models;

use SrcCore\models\ValidatorModel;
use SrcCore\models\DatabaseModel;

class TagModel
{
    public static function get(array $args = [])
    {
        ValidatorModel::arrayType($args, ['select', 'where', 'data', 'orderBy']);
        ValidatorModel::intType($args, ['limit']);

        $tags = DatabaseModel::select([
            'select'    => empty($args['select']) ? ['*'] : $args['select'],
            'table'     => ['tags'],
            'where'     => empty($args['where']) ? [] : $args['where'],
            'data'      => empty($args['data']) ? [] : $args['data'],
            'order_by'  => empty($args['orderBy']) ? [] : $args['orderBy'],
            'groupBy'   => empty($args['groupBy']) ? [] : $args['groupBy'],
            'limit'     => empty($args['limit']) ? 0 : $args['limit']
        ]);

        return $tags;
    }

    public static function getById(array $args)
    {
        ValidatorModel::notEmpty($args, ['id']);
        ValidatorModel::intVal($args, ['id']);
        ValidatorModel::arrayType($args, ['select']);

        $tag = DatabaseModel::select([
            'select'    => empty($args['select']) ? ['*'] : $args['select'],
            'table'     => ['tags'],
            'where'     => ['id = ?'],
            'data'      => [$args['id']],
        ]);

        if (empty($tag[0])) {
            return [];
        }

        return $tag[0];
    }

    public static function create(array $args)
    {
        ValidatorModel::notEmpty($args, ['label']);
        ValidatorModel::stringType($args, ['label']);

        $nextSequenceId = DatabaseModel::getNextSequenceValue(['sequenceId' => 'tags_id_seq']);

        DatabaseModel::insert([
            'table'         => 'tags',
            'columnsValues' => [
                'id'          => $nextSequenceId,
                'label'       => $args['label'],
                'description' => $args['description'] ?? null,
                'parent_id'   => $args['parentId'] ?? null,
                'links'       => $args['links'] ?? json_encode('[]'),
                'usage'       => $args['usage'] ?? null
            ]
        ]);

        return $nextSequenceId;
    }

    public static function delete(array $args)
    {
        ValidatorModel::notEmpty($args, ['where', 'data']);
        ValidatorModel::arrayType($args, ['where', 'data']);

        DatabaseModel::delete([
            'table' => 'tags',
            'where' => $args['where'],
            'data'  => $args['data']
        ]);

        return true;
    }

    public static function update(array $args)
    {
        ValidatorModel::notEmpty($args, ['where']);
        ValidatorModel::arrayType($args, ['set', 'where', 'data']);

        DatabaseModel::update([
            'table'     => 'tags',
            'set'       => empty($args['set']) ? [] : $args['set'],
            'postSet'   => empty($args['postSet']) ? [] : $args['postSet'],
            'where'     => $args['where'],
            'data'      => empty($args['data']) ? [] : $args['data']
        ]);

        return true;
    }

}
