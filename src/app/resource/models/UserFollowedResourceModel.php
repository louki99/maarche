<?php

/**
* Copyright Maarch since 2008 under licence GPLv3.
* See LICENCE.txt file at the root folder for more details.
* This file is part of Maarch software.
*
*/

/**
* @brief Users Followed Resources Model
* @author dev@maarch.org
*/

namespace Resource\models;

use SrcCore\models\DatabaseModel;
use SrcCore\models\ValidatorModel;

class UserFollowedResourceModel
{
    public static function get(array $args = [])
    {
        ValidatorModel::arrayType($args, ['select', 'where', 'data', 'orderBy']);
        ValidatorModel::intType($args, ['limit']);

        $followedResources = DatabaseModel::select([
            'select'    => empty($args['select']) ? ['*'] : $args['select'],
            'table'     => ['users_followed_resources'],
            'where'     => empty($args['where']) ? [] : $args['where'],
            'data'      => empty($args['data']) ? [] : $args['data'],
            'order_by'  => empty($args['orderBy']) ? [] : $args['orderBy'],
            'limit'     => empty($args['limit']) ? 0 : $args['limit']
        ]);

        return $followedResources;
    }

    public static function create(array $args)
    {
        ValidatorModel::notEmpty($args, ['resId', 'userId']);
        ValidatorModel::intVal($args, ['resId', 'userId']);

        DatabaseModel::insert([
            'table'     => 'users_followed_resources',
            'columnsValues' => [
                'res_id'    => $args['resId'],
                'user_id'   => $args['userId'],
            ]
        ]);

        return true;
    }

    public static function delete(array $args)
    {
        ValidatorModel::notEmpty($args, ['resId', 'userId']);
        ValidatorModel::intVal($args, ['resId', 'userId']);

        DatabaseModel::delete([
            'table' => 'users_followed_resources',
            'where' => ['user_id = ?', 'res_id = ?'],
            'data'  => [$args['userId'], $args['resId']]
        ]);

        return true;
    }
}
