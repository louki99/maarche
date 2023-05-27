<?php

/**
 * Copyright Maarch since 2008 under licence GPLv3.
 * See LICENCE.txt file at the root folder for more details.
 * This file is part of Maarch software.
 *
 */

/**
 * @brief User Group Model
 * @author dev@maarch.org
 */

namespace User\models;

use SrcCore\models\ValidatorModel;
use SrcCore\models\DatabaseModel;

class UserGroupModel
{
    public static function get(array $args = [])
    {
        ValidatorModel::arrayType($args, ['select', 'where', 'data', 'orderBy']);
        ValidatorModel::intType($args, ['limit']);

        $usersGroups = DatabaseModel::select([
            'select'    => $args['select'] ?? ['*'],
            'table'     => ['usergroup_content'],
            'where'     => $args['where'] ?? [],
            'data'      => $args['data'] ?? [],
            'order_by'  => $args['orderBy'] ?? [],
            'limit'     => $args['limit'] ?? 0
        ]);

        return $usersGroups;
    }

    public static function create(array $args)
    {
        ValidatorModel::notEmpty($args, ['user_id', 'group_id']);
        ValidatorModel::intVal($args, ['user_id', 'group_id']);
        ValidatorModel::stringType($args, ['role']);

        DatabaseModel::insert([
            'table'         => 'usergroup_content',
            'columnsValues' => [
                'user_id'       => $args['user_id'],
                'group_id'      => $args['group_id'],
                'role'          => $args['role']
            ]
        ]);

        return true;
    }

    public static function update(array $args)
    {
        ValidatorModel::notEmpty($args, ['set', 'where', 'data']);
        ValidatorModel::arrayType($args, ['set', 'where', 'data']);

        DatabaseModel::update([
            'table' => 'usergroup_content',
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
            'table' => 'usergroup_content',
            'where' => $args['where'],
            'data'  => $args['data'] ?? []
        ]);

        return true;
    }

    public static function getWithGroups(array $args = [])
    {
        ValidatorModel::arrayType($args, ['select', 'where', 'data', 'orderBy']);
        ValidatorModel::intType($args, ['limit']);

        $where = ['usergroup_content.group_id = usergroups.id'];
        if (!empty($args['where'])) {
            $where = array_merge($where, $args['where']);
        }
        $usersGroups = DatabaseModel::select([
            'select'    => $args['select'] ?? ['*'],
            'table'     => ['usergroup_content, usergroups'],
            'where'     => $where,
            'data'      => $args['data'] ?? [],
            'order_by'  => $args['orderBy'] ?? [],
            'limit'     => $args['limit'] ?? 0
        ]);

        return $usersGroups;
    }
}
