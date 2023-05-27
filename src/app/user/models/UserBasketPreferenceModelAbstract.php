<?php

/**
 * Copyright Maarch since 2008 under licence GPLv3.
 * See LICENCE.txt file at the root folder for more details.
 * This file is part of Maarch software.
 *
 */

/**
 * @brief User Basket Preference Model Abstract
 * @author dev@maarch.org
 */

namespace User\models;

use SrcCore\models\ValidatorModel;
use SrcCore\models\DatabaseModel;

abstract class UserBasketPreferenceModelAbstract
{
    public static function get(array $aArgs = [])
    {
        ValidatorModel::arrayType($aArgs, ['select', 'where', 'data']);

        $aBasketPreferences = DatabaseModel::select([
            'select'    => empty($aArgs['select']) ? ['*'] : $aArgs['select'],
            'table'     => ['users_baskets_preferences'],
            'where'     => $aArgs['where'],
            'data'      => $aArgs['data']
        ]);

        return $aBasketPreferences;
    }

    public static function create(array $aArgs)
    {
        ValidatorModel::notEmpty($aArgs, ['userSerialId', 'groupSerialId', 'basketId', 'display']);
        ValidatorModel::stringType($aArgs, ['basketId', 'display', 'color']);
        ValidatorModel::intVal($aArgs, ['userSerialId', 'groupSerialId']);

        DatabaseModel::insert([
            'table'         => 'users_baskets_preferences',
            'columnsValues' => [
                'user_serial_id'    => $aArgs['userSerialId'],
                'group_serial_id'   => $aArgs['groupSerialId'],
                'basket_id'         => $aArgs['basketId'],
                'display'           => $aArgs['display'],
                'color'             => $aArgs['color']
            ]
        ]);

        return true;
    }

    public static function update(array $aArgs)
    {
        ValidatorModel::notEmpty($aArgs, ['set', 'where', 'data']);
        ValidatorModel::arrayType($aArgs, ['set', 'where', 'data']);
        ValidatorModel::stringType($aArgs['set'], ['display', 'color']);

        DatabaseModel::update([
            'table' => 'users_baskets_preferences',
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
            'table' => 'users_baskets_preferences',
            'where' => $aArgs['where'],
            'data'  => $aArgs['data']
        ]);

        return true;
    }
}
