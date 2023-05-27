<?php

/**
* Copyright Maarch since 2008 under licence GPLv3.
* See LICENCE.txt file at the root folder for more details.
* This file is part of Maarch software.
*
*/

/**
* @brief   Group Basket Redirect Model Abstract
* @author  dev@maarch.org
*/

namespace Basket\models;

use SrcCore\models\ValidatorModel;
use SrcCore\models\DatabaseModel;

class GroupBasketRedirectModel
{
    public static function get(array $aArgs)
    {
        ValidatorModel::notEmpty($aArgs, ['select']);
        ValidatorModel::arrayType($aArgs, ['select', 'where', 'data']);

        $aRedirects = DatabaseModel::select([
            'select'    => $aArgs['select'],
            'table'     => ['groupbasket_redirect'],
            'where'     => $aArgs['where'],
            'data'      => $aArgs['data']
        ]);

        return $aRedirects;
    }

    public static function create(array $aArgs)
    {
        ValidatorModel::notEmpty($aArgs, ['id', 'groupId', 'actionId', 'redirectMode']);
        ValidatorModel::stringType($aArgs, ['id', 'groupId', 'entityId', 'keyword', 'redirectMode']);
        ValidatorModel::intVal($aArgs, ['actionId']);

        DatabaseModel::insert([
            'table'         => 'groupbasket_redirect',
            'columnsValues' => [
                'action_id'     => $aArgs['actionId'],
                'group_id'      => $aArgs['groupId'],
                'basket_id'     => $aArgs['id'],
                'entity_id'     => $aArgs['entityId'],
                'keyword'       => $aArgs['keyword'],
                'redirect_mode' => $aArgs['redirectMode']
            ]
        ]);

        return true;
    }

    public static function update(array $aArgs)
    {
        ValidatorModel::notEmpty($aArgs, ['set', 'where', 'data']);
        ValidatorModel::arrayType($aArgs, ['set', 'where', 'data']);

        DatabaseModel::update([
            'table' => 'groupbasket_redirect',
            'set'   => $aArgs['set'],
            'where' => $aArgs['where'],
            'data'  => $aArgs['data']
        ]);

        return true;
    }

    public static function delete(array $args)
    {
        ValidatorModel::notEmpty($args, ['where', 'data']);
        ValidatorModel::arrayType($args, ['where', 'data']);

        DatabaseModel::delete([
            'table' => 'groupbasket_redirect',
            'where' => $args['where'],
            'data'  => $args['data']
        ]);

        return true;
    }
}
