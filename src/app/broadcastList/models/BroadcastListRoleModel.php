<?php

/**
 * Copyright Maarch since 2008 under licence GPLv3.
 * See LICENCE.txt file at the root folder for more details.
 * This file is part of Maarch software.
 *
 */

/**
 * @brief Broadcast List Model
 * @author dev@maarch.org
 */

namespace BroadcastList\models;

use SrcCore\models\ValidatorModel;
use SrcCore\models\DatabaseModel;

class BroadcastListRoleModel
{
    public static function getRoles()
    {
        $roles = [];
        $tmpRoles = DatabaseModel::select([
            'select'    => ['role_id', 'label', 'keep_in_list_instance'],
            'table'     => ['difflist_roles']
        ]);

        foreach ($tmpRoles as $tmpValue) {
            $roles[] = [
                'id'                    => $tmpValue['role_id'],
                'label'                 => $tmpValue['label'],
                'keepInListInstance'    => $tmpValue['keep_in_list_instance']
            ];
        }

        return $roles;
    }

    public static function getRoleByRoleId(array $args)
    {
        ValidatorModel::notEmpty($args, ['id']);
        ValidatorModel::intVal($args, ['id']);

        $role = DatabaseModel::select([
            'select'    => ['role_id', 'label', 'keep_in_list_instance'],
            'table'     => ['difflist_roles'],
            'where'     => ['role_id = ?'],
            'data'      => [$args['id']]
        ]);

        return [
            'id'                    => $role[0]['role_id'],
            'label'                 => $role[0]['label'],
            'keepInListInstance'    => $role[0]['keep_in_list_instance']
        ];
    }
}