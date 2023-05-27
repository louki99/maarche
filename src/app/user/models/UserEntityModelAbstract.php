<?php

/**
 * Copyright Maarch since 2008 under licence GPLv3.
 * See LICENCE.txt file at the root folder for more details.
 * This file is part of Maarch software.
 *
 */

/**
 * @brief User Entity Model Abstract
 * @author dev@maarch.org
 */

namespace User\models;

use Entity\models\EntityModel;
use SrcCore\models\DatabaseModel;
use SrcCore\models\ValidatorModel;

abstract class UserEntityModelAbstract
{
    public static function get(array $aArgs)
    {
        ValidatorModel::notEmpty($aArgs, ['select', 'where', 'data']);
        ValidatorModel::arrayType($aArgs, ['select', 'where', 'data']);

        $users = DatabaseModel::select([
            'select'    => empty($aArgs['select']) ? ['*'] : $aArgs['select'],
            'table'     => ['users_entities'],
            'where'     => $aArgs['where'],
            'data'      => $aArgs['data']
        ]);

        return $users;
    }

    public static function update(array $aArgs)
    {
        ValidatorModel::notEmpty($aArgs, ['set', 'where', 'data']);
        ValidatorModel::arrayType($aArgs, ['set', 'where', 'data']);

        DatabaseModel::update([
            'table' => 'users_entities',
            'set'   => $aArgs['set'],
            'where' => $aArgs['where'],
            'data'  => $aArgs['data']
        ]);

        return true;
    }

    public static function delete(array $aArgs)
    {
        ValidatorModel::notEmpty($aArgs, ['where', 'data']);
        ValidatorModel::arrayType($aArgs, ['where', 'data']);

        DatabaseModel::delete([
            'table' => 'users_entities',
            'where' => $aArgs['where'],
            'data'  => $aArgs['data']
        ]);

        return true;
    }

    public static function getUsersWithoutEntities(array $aArgs)
    {
        ValidatorModel::arrayType($aArgs, ['select']);

        $aUsersEntities = DatabaseModel::select([
            'select'    => empty($aArgs['select']) ? ['*'] : $aArgs['select'],
            'table'     => ['users', 'users_entities'],
            'left_join' => ['users.id = users_entities.user_id'],
            'where'     => ['users_entities IS NULL', 'status != ?'],
            'data'      => ['DEL']
        ]);

        return $aUsersEntities;
    }

    public static function addUserEntity(array $args)
    {
        ValidatorModel::notEmpty($args, ['id', 'entityId', 'primaryEntity']);
        ValidatorModel::intVal($args, ['id']);
        ValidatorModel::stringType($args, ['entityId', 'role', 'primaryEntity']);

        DatabaseModel::insert([
            'table'         => 'users_entities',
            'columnsValues' => [
                'user_id'           => $args['id'],
                'entity_id'         => $args['entityId'],
                'user_role'         => $args['role'],
                'primary_entity'    => $args['primaryEntity']
            ]
        ]);

        return true;
    }

    public static function updateUserEntity(array $args)
    {
        ValidatorModel::notEmpty($args, ['id', 'entityId']);
        ValidatorModel::intVal($args, ['id']);
        ValidatorModel::stringType($args, ['entityId', 'role']);

        DatabaseModel::update([
            'table'     => 'users_entities',
            'set'       => [
                'user_role' => $args['role']
            ],
            'where'     => ['user_id = ?', 'entity_id = ?'],
            'data'      => [$args['id'], $args['entityId']]
        ]);

        return true;
    }

    public static function updateUserPrimaryEntity(array $aArgs)
    {
        ValidatorModel::notEmpty($aArgs, ['id', 'entityId']);
        ValidatorModel::intVal($aArgs, ['id']);
        ValidatorModel::stringType($aArgs, ['entityId']);

        $entities = EntityModel::getByUserId(['userId' => $aArgs['id']]);
        foreach ($entities as $entity) {
            if ($entity['primary_entity'] == 'Y') {
                DatabaseModel::update([
                    'table'     => 'users_entities',
                    'set'       => [
                        'primary_entity'    => 'N'
                    ],
                    'where'     => ['user_id = ?', 'entity_id = ?'],
                    'data'      => [$aArgs['id'], $entity['entity_id']]
                ]);
            }
        }

        DatabaseModel::update([
            'table'     => 'users_entities',
            'set'       => [
                'primary_entity'    => 'Y'
            ],
            'where'     => ['user_id = ?', 'entity_id = ?'],
            'data'      => [$aArgs['id'], $aArgs['entityId']]
        ]);

        return true;
    }

    public static function reassignUserPrimaryEntity(array $aArgs)
    {
        ValidatorModel::notEmpty($aArgs, ['userId']);
        ValidatorModel::intVal($aArgs, ['userId']);

        $entities = EntityModel::getByUserId(['userId' => $aArgs['userId'], 'select' => ['entity_id']]);
        if (!empty($entities[0])) {
            DatabaseModel::update([
                'table'     => 'users_entities',
                'set'       => [
                    'primary_entity'    => 'Y'
                ],
                'where'     => ['user_id = ?', 'entity_id = ?'],
                'data'      => [$aArgs['userId'], $entities[0]['entity_id']]
            ]);
        }

        return true;
    }

    public static function deleteUserEntity(array $aArgs)
    {
        ValidatorModel::notEmpty($aArgs, ['id', 'entityId']);
        ValidatorModel::intVal($aArgs, ['id']);
        ValidatorModel::stringType($aArgs, ['entityId']);

        DatabaseModel::delete([
            'table'     => 'users_entities',
            'where'     => ['entity_id = ?', 'user_id = ?'],
            'data'      => [$aArgs['entityId'], $aArgs['id']]
        ]);

        return true;
    }

    public static function getWithUsers(array $aArgs)
    {
        ValidatorModel::notEmpty($aArgs, ['select', 'where', 'data']);
        ValidatorModel::arrayType($aArgs, ['select', 'where', 'data', 'orderBy']);
        ValidatorModel::intType($aArgs, ['limit']);

        $users = DatabaseModel::select([
            'select'    => empty($aArgs['select']) ? ['*'] : $aArgs['select'],
            'table'     => ['users', 'users_entities'],
            'left_join' => ['users.id = users_entities.user_id'],
            'where'     => empty($aArgs['where']) ? [] : $aArgs['where'],
            'data'      => empty($aArgs['data']) ? [] : $aArgs['data'],
            'order_by'  => empty($aArgs['orderBy']) ? [] : $aArgs['orderBy'],
            'limit'     => empty($aArgs['limit']) ? 0 : $aArgs['limit']
        ]);

        return $users;
    }
}
