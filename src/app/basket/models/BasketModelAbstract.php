<?php

/**
* Copyright Maarch since 2008 under licence GPLv3.
* See LICENCE.txt file at the root folder for more details.
* This file is part of Maarch software.
*
*/

/**
* @brief   Basket Model Abstract
* @author  dev@maarch.org
*/

namespace Basket\models;

use SrcCore\models\ValidatorModel;
use Resource\models\ResModel;
use SrcCore\controllers\PreparedClauseController;
use SrcCore\models\DatabaseModel;
use User\models\UserBasketPreferenceModel;
use User\models\UserModel;

abstract class BasketModelAbstract
{
    public static function get(array $args = [])
    {
        ValidatorModel::arrayType($args, ['select', 'where', 'data', 'orderBy']);
        ValidatorModel::intType($args, ['limit']);

        $baskets = DatabaseModel::select([
            'select'    => $args['select'] ?? ['*'],
            'table'     => ['baskets'],
            'where'     => $args['where'] ?? [],
            'data'      => $args['data'] ?? [],
            'order_by'  => $args['orderBy'] ?? [],
            'limit'     => $args['limit'] ?? 0
        ]);

        return $baskets;
    }

    public static function getById(array $aArgs)
    {
        ValidatorModel::notEmpty($aArgs, ['id']);
        ValidatorModel::intVal($aArgs, ['id']);
        ValidatorModel::arrayType($aArgs, ['select']);

        $aBasket = DatabaseModel::select([
            'select'    => empty($aArgs['select']) ? ['*'] : $aArgs['select'],
            'table'     => ['baskets'],
            'where'     => ['id = ?'],
            'data'      => [$aArgs['id']]
        ]);

        if (empty($aBasket[0])) {
            return [];
        }

        return $aBasket[0];
    }

    public static function getByBasketId(array $aArgs)
    {
        ValidatorModel::notEmpty($aArgs, ['basketId']);
        ValidatorModel::stringType($aArgs, ['basketId']);
        ValidatorModel::arrayType($aArgs, ['select']);

        $aBasket = DatabaseModel::select([
            'select'    => empty($aArgs['select']) ? ['*'] : $aArgs['select'],
            'table'     => ['baskets'],
            'where'     => ['basket_id = ?'],
            'data'      => [$aArgs['basketId']]
        ]);

        if (empty($aBasket[0])) {
            return [];
        }

        return $aBasket[0];
    }

    public static function create(array $aArgs)
    {
        ValidatorModel::notEmpty($aArgs, ['id', 'basket_name', 'basket_desc', 'clause', 'isVisible', 'flagNotif']);
        ValidatorModel::stringType($aArgs, ['id', 'basket_name', 'color', 'basket_desc', 'clause', 'isVisible', 'flagNotif', 'basket_res_order']);

        DatabaseModel::insert([
            'table'         => 'baskets',
            'columnsValues' => [
                'basket_id'         => $aArgs['id'],
                'basket_name'       => $aArgs['basket_name'],
                'basket_desc'       => $aArgs['basket_desc'],
                'basket_clause'     => $aArgs['clause'],
                'is_visible'        => $aArgs['isVisible'],
                'flag_notif'        => $aArgs['flagNotif'],
                'color'             => $aArgs['color'] ?? null,
                'coll_id'           => 'letterbox_coll',
                'basket_res_order'  => $aArgs['basket_res_order'],
            ]
        ]);

        return true;
    }

    public static function update(array $args)
    {
        ValidatorModel::notEmpty($args, ['where', 'data']);
        ValidatorModel::arrayType($args, ['set', 'postSet', 'where', 'data']);

        DatabaseModel::update([
            'table'     => 'baskets',
            'set'       => $args['set'],
            'postSet'   => $args['postSet'] ?? [],
            'where'     => $args['where'],
            'data'      => $args['data']
        ]);

        return true;
    }

    public static function updateOrder(array $aArgs)
    {
        ValidatorModel::notEmpty($aArgs, ['id']);
        ValidatorModel::stringType($aArgs, ['id']);
        ValidatorModel::intVal($aArgs, ['order']);

        DatabaseModel::update([
            'table'     => 'baskets',
            'set'       => [
                'basket_order'  => $aArgs['order']
            ],
            'where'     => ['basket_id = ?'],
            'data'      => [$aArgs['id']]
        ]);

        return true;
    }

    public static function delete(array $aArgs)
    {
        ValidatorModel::notEmpty($aArgs, ['id']);
        ValidatorModel::stringType($aArgs, ['id']);

        DatabaseModel::delete([
            'table' => 'baskets',
            'where' => ['basket_id = ?'],
            'data'  => [$aArgs['id']]
        ]);
        DatabaseModel::delete([
            'table' => 'groupbasket',
            'where' => ['basket_id = ?'],
            'data'  => [$aArgs['id']]
        ]);
        DatabaseModel::delete([
            'table' => 'groupbasket_redirect',
            'where' => ['basket_id = ?'],
            'data'  => [$aArgs['id']]
        ]);
        DatabaseModel::delete([
            'table' => 'actions_groupbaskets',
            'where' => ['basket_id = ?'],
            'data'  => [$aArgs['id']]
        ]);
        DatabaseModel::delete([
            'table' => 'users_baskets_preferences',
            'where' => ['basket_id = ?'],
            'data'  => [$aArgs['id']]
        ]);

        return true;
    }

    public static function hasGroup(array $aArgs)
    {
        ValidatorModel::notEmpty($aArgs, ['id', 'groupId']);
        ValidatorModel::stringType($aArgs, ['id', 'groupId']);

        return !empty(GroupBasketModel::get(['where' => ['basket_id = ?', 'group_id = ?'], 'data' => [$aArgs['id'], $aArgs['groupId']]]));
    }

    public static function getBasketsByLogin(array $aArgs)
    {
        ValidatorModel::notEmpty($aArgs, ['login']);
        ValidatorModel::stringType($aArgs, ['login']);
        ValidatorModel::arrayType($aArgs, ['unneededBasketId']);

        $user = UserModel::getByLogin(['login' => $aArgs['login'], 'select' => ['id']]);
        $userGroups = UserModel::getGroupsById(['id' => $user['id']]);
        $groupIds = array_column($userGroups, 'group_id');

        $aBaskets = [];
        if (!empty($groupIds)) {
            $where = ['groupbasket.group_id in (?)', 'groupbasket.basket_id = baskets.basket_id', 'groupbasket.group_id = usergroups.group_id'];
            $data = [$groupIds];
            if (!empty($aArgs['unneededBasketId'])) {
                $where[] = 'groupbasket.basket_id not in (?)';
                $data[]  = $aArgs['unneededBasketId'];
            }
            $aBaskets = DatabaseModel::select([
                    'select'    => ['usergroups.id as groupSerialId', 'groupbasket.basket_id', 'baskets.id', 'groupbasket.group_id', 'basket_name', 'basket_desc', 'basket_clause', 'usergroups.group_desc', 'baskets.is_visible'],
                    'table'     => ['groupbasket, baskets, usergroups'],
                    'where'     => $where,
                    'data'      => $data,
                    'order_by'  => ['groupbasket.group_id, basket_order, basket_name']
            ]);

            $userPrefs = UserBasketPreferenceModel::get([
                'select'    => ['group_serial_id', 'basket_id'],
                'where'     => ['user_serial_id = ?'],
                'data'      => [$user['id']]
            ]);

            foreach ($aBaskets as $key => $value) {
                unset($aBaskets[$key]['groupserialid']);
                $aBaskets[$key]['groupSerialId'] = $value['groupserialid'];
                $aBaskets[$key]['owner_user_id'] = $user['id'];
                $aBaskets[$key]['basketSearch']  = $aBaskets[$key]['is_visible'] == 'N';
                $redirectedBasket = RedirectBasketModel::get([
                    'select'    => ['actual_user_id'],
                    'where'     => ['owner_user_id = ?', 'basket_id = ?', 'group_id = ?'],
                    'data'      => [$user['id'], $value['basket_id'], $value['groupserialid']]
                ]);
                $aBaskets[$key]['userToDisplay'] = (empty($redirectedBasket[0]) ? null : UserModel::getLabelledUserById(['id' => $redirectedBasket[0]['actual_user_id']]));
                $aBaskets[$key]['enabled'] = true;
                $aBaskets[$key]['allowed'] = false;
                foreach ($userPrefs as $userPref) {
                    if ($userPref['group_serial_id'] == $value['groupserialid'] && $userPref['basket_id'] == $value['basket_id']) {
                        $aBaskets[$key]['allowed'] = true;
                    }
                }
            }
        }

        return $aBaskets;
    }

    public static function getRegroupedBasketsByUserId(array $aArgs)
    {
        ValidatorModel::notEmpty($aArgs, ['userId']);
        ValidatorModel::stringType($aArgs, ['userId']);

        $regroupedBaskets = [];

        $user = UserModel::getByLogin(['login' => $aArgs['userId'], 'select' => ['id']]);

        $groups = UserModel::getGroupsById(['id' => $user['id']]);
        foreach ($groups as $group) {
            $baskets = BasketModel::getAvailableBasketsByGroupUser([
                'select'        => ['baskets.basket_id', 'baskets.basket_name', 'baskets.basket_desc', 'baskets.color', 'users_baskets_preferences.color as pcolor'],
                'userSerialId'  => $user['id'],
                'groupId'       => $group['group_id'],
                'groupSerialId' => $group['id']
            ]);

            foreach ($baskets as $kBasket => $basket) {
                if (!empty($basket['pcolor'])) {
                    $baskets[$kBasket]['color'] = $basket['pcolor'];
                }
                if (empty($baskets[$kBasket]['color'])) {
                    $baskets[$kBasket]['color'] = '#666666';
                }
                unset($baskets[$kBasket]['pcolor']);
            }

            $regroupedBaskets[] = [
                'groupSerialId' => $group['id'],
                'groupId'       => $group['group_id'],
                'groupDesc'     => $group['group_desc'],
                'baskets'       => $baskets
            ];
        }

        return $regroupedBaskets;
    }

    public static function getAvailableBasketsByGroupUser(array $aArgs)
    {
        ValidatorModel::notEmpty($aArgs, ['userSerialId', 'groupId', 'groupSerialId', 'select']);
        ValidatorModel::intVal($aArgs, ['userSerialId', 'groupSerialId']);
        ValidatorModel::stringType($aArgs, ['groupId']);
        ValidatorModel::arrayType($aArgs, ['select']);

        $baskets = DatabaseModel::select([
            'select'    => $aArgs['select'],
            'table'     => ['groupbasket, baskets, users_baskets_preferences'],
            'where'     => [
                'groupbasket.basket_id = baskets.basket_id',
                'baskets.basket_id = users_baskets_preferences.basket_id',
                'groupbasket.group_id = ?',
                'users_baskets_preferences.group_serial_id = ?',
                'users_baskets_preferences.user_serial_id = ?',
                'baskets.is_visible = ?'
            ],
            'data'      => [$aArgs['groupId'], $aArgs['groupSerialId'], $aArgs['userSerialId'], 'Y'],
            'order_by'  => ['baskets.basket_order', 'baskets.basket_name']
        ]);

        return $baskets;
    }

    public static function getDefaultActionIdByBasketId(array $aArgs)
    {
        ValidatorModel::notEmpty($aArgs, ['basketId', 'groupId']);
        ValidatorModel::stringType($aArgs, ['basketId', 'groupId']);

        $aAction = DatabaseModel::select(
            [
            'select'    => ['id_action'],
            'table'     => ['actions_groupbaskets'],
            'where'     => ['basket_id = ?', 'group_id = ?', 'default_action_list = \'Y\''],
            'data'      => [$aArgs['basketId'], $aArgs['groupId']]
            ]
        );

        if (empty($aAction[0])) {
            return '';
        }

        return $aAction[0]['id_action'];
    }

    public static function getResourceNumberByClause(array $args)
    {
        ValidatorModel::notEmpty($args, ['userId', 'clause']);
        ValidatorModel::stringType($args, ['clause']);
        ValidatorModel::intVal($args, ['userId']);

        try {
            $count = ResModel::getOnView([
                'select'    => ['COUNT(1)'],
                'where'     => [PreparedClauseController::getPreparedClause(['userId' => $args['userId'], 'clause' => $args['clause']])]
            ]);
        } catch (\Exception $e) {
            return 0;
        }

        if (empty($count[0]['count'])) {
            return 0;
        }

        return $count[0]['count'];
    }

    public static function getWithPreferences(array $args)
    {
        ValidatorModel::arrayType($args, ['select', 'where', 'data', 'orderBy']);

        $where = ['(baskets.basket_id = users_baskets_preferences.basket_id AND users_baskets_preferences.group_serial_id = usergroups.id)'];
        if (!empty($args['where'])) {
            $where = array_merge($where, $args['where']);
        }

        $baskets = DatabaseModel::select([
            'select'    => empty($args['select']) ? ['*'] : $args['select'],
            'table'     => ['baskets, users_baskets_preferences, usergroups'],
            'where'     => $where,
            'data'      => empty($args['data']) ? [] : $args['data'],
            'order_by'  => $args['orderBy'] ?? [],
        ]);

        return $baskets;
    }
}
