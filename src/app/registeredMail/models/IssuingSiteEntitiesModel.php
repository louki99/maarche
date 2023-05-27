<?php

/**
* Copyright Maarch since 2008 under licence GPLv3.
* See LICENCE.txt file at the root folder for more details.
* This file is part of Maarch software.
*/

/**
 * @brief Issuing Site Model
 * @author dev@maarch.org
 */

namespace RegisteredMail\models;

use SrcCore\models\ValidatorModel;
use SrcCore\models\DatabaseModel;

class IssuingSiteEntitiesModel
{
    public static function get(array $args = [])
    {
        ValidatorModel::notEmpty($args, ['select']);
        ValidatorModel::arrayType($args, ['select', 'where', 'data', 'orderBy', 'groupBy']);
        ValidatorModel::intType($args, ['limit']);

        return DatabaseModel::select([
            'select'    => $args['select'],
            'table'     => ['registered_mail_issuing_sites_entities'],
            'where'     => empty($args['where']) ? [] : $args['where'],
            'data'      => empty($args['data']) ? [] : $args['data'],
            'order_by'  => empty($args['orderBy']) ? [] : $args['orderBy'],
            'limit'     => empty($args['limit']) ? 0 : $args['limit'],
            'groupBy'   => empty($args['groupBy']) ? [] : $args['groupBy'],
        ]);
    }

    public static function create(array $args)
    {
        ValidatorModel::notEmpty($args, ['siteId', 'entityId']);
        ValidatorModel::intVal($args, ['siteId', 'entityId']);

        DatabaseModel::insert([
            'table'         => 'registered_mail_issuing_sites_entities',
            'columnsValues' => [
                'site_id'   => $args['siteId'],
                'entity_id' => $args['entityId'],
            ]
        ]);

        return true;
    }

    public static function delete(array $args)
    {
        ValidatorModel::notEmpty($args, ['where', 'data']);
        ValidatorModel::arrayType($args, ['where', 'data']);

        DatabaseModel::delete([
            'table' => 'registered_mail_issuing_sites_entities',
            'where' => $args['where'],
            'data'  => $args['data']
        ]);

        return true;
    }
}
