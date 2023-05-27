<?php

/**
* Copyright Maarch since 2008 under licence GPLv3.
* See LICENCE.txt file at the root folder for more details.
* This file is part of Maarch software.
*
*/

/**
* @brief DocserverType Model
* @author dev@maarch.org
*/

namespace Docserver\models;

use SrcCore\models\DatabaseModel;
use SrcCore\models\ValidatorModel;

class DocserverTypeModelAbstract
{
    public static function get(array $aArgs = [])
    {
        ValidatorModel::arrayType($aArgs, ['select', 'where', 'orderBy']);
        ValidatorModel::intType($aArgs, ['limit']);

        $aDocserverTypes = DatabaseModel::select([
            'select'    => empty($aArgs['select']) ? ['*'] : $aArgs['select'],
            'table'     => ['docserver_types'],
            'where'     => empty($aArgs['where']) ? [] : $aArgs['where'],
            'data'      => empty($aArgs['data']) ? [] : $aArgs['data'],
            'order_by'  => empty($aArgs['orderBy']) ? [] : $aArgs['orderBy'],
            'limit'     => empty($aArgs['limit']) ? 0 : $aArgs['limit']
        ]);

        return $aDocserverTypes;
    }

    public static function getById(array $aArgs)
    {
        ValidatorModel::notEmpty($aArgs, ['id']);
        ValidatorModel::stringType($aArgs, ['id']);

        $aDocserverType = DatabaseModel::select([
            'select'    => empty($aArgs['select']) ? ['*'] : $aArgs['select'],
            'table'     => ['docserver_types'],
            'where'     => ['docserver_type_id = ?'],
            'data'      => [$aArgs['id']]
        ]);

        if (empty($aDocserverType[0])) {
            return [];
        }

        return $aDocserverType[0];
    }
}
