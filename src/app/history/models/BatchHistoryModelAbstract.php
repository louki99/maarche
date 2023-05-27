<?php

/**
* Copyright Maarch since 2008 under licence GPLv3.
* See LICENCE.txt file at the root folder for more details.
* This file is part of Maarch software.
*
*/

/**
* @brief Batch History Model Abstract
* @author dev@maarch.org
*/

namespace History\models;

use SrcCore\models\ValidatorModel;
use SrcCore\models\DatabaseModel;

abstract class BatchHistoryModelAbstract
{
    public static function get(array $args)
    {
        ValidatorModel::notEmpty($args, ['select']);
        ValidatorModel::arrayType($args, ['select', 'where', 'data', 'orderBy']);
        ValidatorModel::intVal($args, ['offset', 'limit']);

        $history = DatabaseModel::select([
            'select'    => $args['select'],
            'table'     => ['history_batch'],
            'where'     => $args['where'] ?? [],
            'data'      => $args['data'] ?? [],
            'order_by'  => $args['orderBy'] ?? [],
            'offset'    => $args['offset'] ?? 0,
            'limit'     => $args['limit'] ?? 0
        ]);

        return $history;
    }

    public static function create(array $args)
    {
        ValidatorModel::notEmpty($args, ['info', 'module_name']);
        ValidatorModel::stringType($args, ['info', 'module_name']);

        DatabaseModel::insert([
            'table'         => 'history_batch',
            'columnsValues' => [
                'module_name'       => $args['module_name'],
                'batch_id'          => $args['batch_id'] ?? null,
                'event_date'        => 'CURRENT_TIMESTAMP',
                'info'              => $args['info'],
                'total_processed'   => $args['total_processed'] ?? 0,
                'total_errors'      => $args['total_errors'] ?? 0,
            ]
        ]);

        return true;
    }
}
