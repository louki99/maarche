<?php

/**
* Copyright Maarch since 2008 under licence GPLv3.
* See LICENCE.txt file at the root folder for more details.
* This file is part of Maarch software.
*
*/

/**
* @brief Resource List Model
* @author dev@maarch.org
*/

namespace Resource\models;

use SrcCore\models\DatabaseModel;
use SrcCore\models\ValidatorModel;

class ResourceListModel
{
    public static function getOnResource(array $aArgs)
    {
        ValidatorModel::notEmpty($aArgs, ['select']);
        ValidatorModel::arrayType($aArgs, ['select', 'table', 'leftJoin', 'where', 'data', 'orderBy', 'groupBy']);
        ValidatorModel::intType($aArgs, ['limit', 'offset']);

        $aResources = DatabaseModel::select([
            'select'    => $aArgs['select'],
            'table'     => array_merge(['res_letterbox'], $aArgs['table']),
            'left_join' => empty($aArgs['leftJoin']) ? [] : $aArgs['leftJoin'],
            'where'     => empty($aArgs['where']) ? [] : $aArgs['where'],
            'data'      => empty($aArgs['data']) ? [] : $aArgs['data'],
            'order_by'  => empty($aArgs['orderBy']) ? [] : $aArgs['orderBy'],
            'groupBy'   => empty($aArgs['groupBy']) ? [] : $aArgs['groupBy'],
            'offset'    => empty($aArgs['offset']) ? 0 : $aArgs['offset'],
            'limit'     => empty($aArgs['limit']) ? 0 : $aArgs['limit']
        ]);

        return $aResources;
    }

    public static function getOnView(array $aArgs)
    {
        ValidatorModel::notEmpty($aArgs, ['select']);
        ValidatorModel::arrayType($aArgs, ['select', 'table', 'leftJoin', 'where', 'data', 'orderBy', 'groupBy']);
        ValidatorModel::intType($aArgs, ['limit', 'offset']);

        $aResources = DatabaseModel::select([
            'select'    => $aArgs['select'],
            'table'     => array_merge(['res_view_letterbox'], $aArgs['table']),
            'left_join' => empty($aArgs['leftJoin']) ? [] : $aArgs['leftJoin'],
            'where'     => empty($aArgs['where']) ? [] : $aArgs['where'],
            'data'      => empty($aArgs['data']) ? [] : $aArgs['data'],
            'order_by'  => empty($aArgs['orderBy']) ? [] : $aArgs['orderBy'],
            'groupBy'   => empty($aArgs['groupBy']) ? [] : $aArgs['groupBy'],
            'offset'    => empty($aArgs['offset']) ? 0 : $aArgs['offset'],
            'limit'     => empty($aArgs['limit']) ? 0 : $aArgs['limit']
        ]);

        return $aResources;
    }
}
