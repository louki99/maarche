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
use SrcCore\models\DatabaseModel;

class ActionGroupBasketModel
{
    public static function get(array $aArgs = [])
    {
        ValidatorModel::arrayType($aArgs, ['select', 'where', 'data', 'orderBy']);
        ValidatorModel::intType($aArgs, ['limit']);

        $actions = DatabaseModel::select([
            'select'    => empty($aArgs['select']) ? ['*'] : $aArgs['select'],
            'table'     => ['actions_groupbaskets'],
            'where'     => empty($aArgs['where']) ? [] : $aArgs['where'],
            'data'      => empty($aArgs['data']) ? [] : $aArgs['data'],
            'order_by'  => empty($aArgs['orderBy']) ? [] : $aArgs['orderBy'],
            'limit'     => empty($aArgs['limit']) ? 0 : $aArgs['limit']
        ]);

        return $actions;
    }

    public static function create(array $aArgs)
    {
        ValidatorModel::notEmpty($aArgs, ['id', 'groupId', 'actionId', 'usedInBasketlist', 'usedInActionPage', 'defaultActionList']);
        ValidatorModel::stringType($aArgs, ['id', 'groupId', 'whereClause', 'usedInBasketlist', 'usedInActionPage', 'defaultActionList']);
        ValidatorModel::intVal($aArgs, ['actionId']);

        DatabaseModel::insert([
            'table'         => 'actions_groupbaskets',
            'columnsValues' => [
                'id_action'             => $aArgs['actionId'],
                'where_clause'          => $aArgs['whereClause'],
                'group_id'              => $aArgs['groupId'],
                'basket_id'             => $aArgs['id'],
                'used_in_basketlist'    => $aArgs['usedInBasketlist'],
                'used_in_action_page'   => $aArgs['usedInActionPage'],
                'default_action_list'   => $aArgs['defaultActionList'],
            ]
        ]);

        return true;
    }
}
