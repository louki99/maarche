<?php

/**
 * Copyright Maarch since 2008 under licence GPLv3.
 * See LICENCE.txt file at the root folder for more details.
 * This file is part of Maarch software.
 *
 */

/**
 * @brief List Template Model
 * @author dev@maarch.org
 */

namespace Entity\models;

use SrcCore\models\DatabaseModel;
use SrcCore\models\ValidatorModel;

class ListTemplateItemModel
{
    public static function get(array $args = [])
    {
        ValidatorModel::arrayType($args, ['select', 'where', 'data']);

        $items = DatabaseModel::select([
            'select'    => $args['select'] ?? [1],
            'table'     => ['list_templates_items'],
            'where'     => $args['where'] ?? [],
            'data'      => $args['data'] ?? [],
            'order_by'  => $args['orderBy'] ?? []
        ]);

        return $items;
    }

    public static function create(array $args)
    {
        ValidatorModel::notEmpty($args, ['list_template_id', 'item_id', 'item_type', 'item_mode']);
        ValidatorModel::stringType($args, ['item_type', 'item_mode']);
        ValidatorModel::intVal($args, ['list_template_id', 'item_id', 'sequence']);

        DatabaseModel::insert([
            'table'         => 'list_templates_items',
            'columnsValues' => [
                'list_template_id'  => $args['list_template_id'],
                'item_id'           => $args['item_id'],
                'item_type'         => $args['item_type'],
                'item_mode'         => $args['item_mode'],
                'sequence'          => $args['sequence']
            ]
        ]);

        return true;
    }

    public static function update(array $args)
    {
        ValidatorModel::notEmpty($args, ['set', 'where', 'data']);
        ValidatorModel::arrayType($args, ['set', 'where', 'data']);

        DatabaseModel::update([
            'table' => 'list_templates_items',
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
            'table' => 'list_templates_items',
            'where' => $args['where'],
            'data'  => $args['data']
        ]);

        return true;
    }
}
