<?php

/**
* Copyright Maarch since 2008 under licence GPLv3.
* See LICENCE.txt file at the root folder for more details.
* This file is part of Maarch software.
*
*/

/**
* @brief   Redirect Basket Model Abstract
* @author  dev@maarch.org
*/

namespace Basket\models;

use SrcCore\models\ValidatorModel;
use SrcCore\models\DatabaseModel;
use User\models\UserModel;

class RedirectBasketModel
{
    public static function get(array $aArgs = [])
    {
        ValidatorModel::arrayType($aArgs, ['select', 'where', 'data']);

        $redirectedBaskets = DatabaseModel::select([
            'select'    => empty($aArgs['select']) ? ['*'] : $aArgs['select'],
            'table'     => ['redirected_baskets'],
            'where'     => empty($aArgs['where']) ? [] : $aArgs['where'],
            'data'      => empty($aArgs['data']) ? [] : $aArgs['data'],
        ]);

        return $redirectedBaskets;
    }

    public static function create(array $aArgs)
    {
        ValidatorModel::notEmpty($aArgs, ['actual_user_id', 'owner_user_id', 'basket_id', 'group_id']);
        ValidatorModel::stringType($aArgs, ['basket_id']);
        ValidatorModel::intVal($aArgs, ['actual_user_id', 'owner_user_id', 'group_id']);

        DatabaseModel::insert([
            'table'         => 'redirected_baskets',
            'columnsValues' => [
                'actual_user_id'    => $aArgs['actual_user_id'],
                'owner_user_id'     => $aArgs['owner_user_id'],
                'basket_id'         => $aArgs['basket_id'],
                'group_id'          => $aArgs['group_id']
            ]
        ]);

        return true;
    }

    public static function update(array $aArgs)
    {
        ValidatorModel::notEmpty($aArgs, ['actual_user_id', 'owner_user_id', 'basket_id', 'group_id']);
        ValidatorModel::stringType($aArgs, ['basket_id']);
        ValidatorModel::intVal($aArgs, ['actual_user_id', 'owner_user_id', 'group_id']);

        DatabaseModel::update([
            'table'     => 'redirected_baskets',
            'set'       => [
                'actual_user_id'    => $aArgs['actual_user_id']
            ],
            'where'     => ['owner_user_id = ?', 'basket_id = ?', 'group_id = ?'],
            'data'      => [$aArgs['owner_user_id'], $aArgs['basket_id'], $aArgs['group_id']]
        ]);

        return true;
    }

    public static function delete(array $aArgs)
    {
        ValidatorModel::notEmpty($aArgs, ['where', 'data']);
        ValidatorModel::arrayType($aArgs, ['where', 'data']);

        DatabaseModel::delete([
            'table' => 'redirected_baskets',
            'where' => $aArgs['where'],
            'data'  => $aArgs['data']
        ]);

        return true;
    }

    public static function getAssignedBasketsByUserId(array $aArgs)
    {
        ValidatorModel::notEmpty($aArgs, ['userId']);
        ValidatorModel::intVal($aArgs, ['userId']);

        $aBaskets = DatabaseModel::select([
            'select'    => ['rb.id', 'ba.basket_id', 'ba.basket_name', 'ba.basket_clause', 'rb.owner_user_id', 'rb.group_id', 'usergroups.group_id as "oldGroupId"', 'usergroups.group_desc'],
            'table'     => ['baskets ba, redirected_baskets rb, usergroups'],
            'where'     => ['rb.actual_user_id = ?', 'rb.basket_id = ba.basket_id', 'usergroups.id = rb.group_id'],
            'data'      => [$aArgs['userId']],
            'order_by'  => ['ba.basket_order, ba.basket_name']
        ]);

        foreach ($aBaskets as $key => $value) {
            $aBaskets[$key]['userToDisplay'] = UserModel::getLabelledUserById(['id' => $value['owner_user_id']]);
        }

        return $aBaskets;
    }

    public static function getRedirectedBasketsByUserId(array $aArgs)
    {
        ValidatorModel::notEmpty($aArgs, ['userId']);
        ValidatorModel::intVal($aArgs, ['userId']);

        $aBaskets = DatabaseModel::select([
            'select'    => ['rb.id', 'ba.basket_id', 'ba.basket_name', 'rb.actual_user_id', 'rb.group_id', 'usergroups.group_desc'],
            'table'     => ['baskets ba, redirected_baskets rb, usergroups'],
            'where'     => ['rb.owner_user_id = ?', 'rb.basket_id = ba.basket_id', 'usergroups.id = rb.group_id'],
            'data'      => [$aArgs['userId']],
            'order_by'  => ['rb.id']
        ]);

        foreach ($aBaskets as $key => $value) {
            $aBaskets[$key]['userToDisplay'] = UserModel::getLabelledUserById(['id' => $value['actual_user_id']]);
        }

        return $aBaskets;
    }
}
