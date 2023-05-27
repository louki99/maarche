<?php

/**
* Copyright Maarch since 2008 under licence GPLv3.
* See LICENCE.txt file at the root folder for more details.
* This file is part of Maarch software.

* @brief   ParametersModelAbstract
* @author  dev <dev@maarch.org>
* @ingroup core
*/

/**
 * @brief Parameter Model Abstract
 * @author dev@maarch.org
 */

namespace Parameter\models;

use SrcCore\models\ValidatorModel;
use SrcCore\models\DatabaseModel;

abstract class ParameterModelAbstract
{
    public static function get(array $aArgs = [])
    {
        ValidatorModel::arrayType($aArgs, ['select']);

        $aReturn = DatabaseModel::select([
            'select'    => empty($aArgs['select']) ? ['*'] : $aArgs['select'],
            'table'     => ['parameters'],
            'where'     => empty($aArgs['where']) ? [] : $aArgs['where'],
            'data'      => empty($aArgs['data']) ? [] : $aArgs['data'],
        ]);

        return $aReturn;
    }

    public static function getById(array $aArgs)
    {
        ValidatorModel::notEmpty($aArgs, ['id']);
        ValidatorModel::stringType($aArgs, ['id']);
        ValidatorModel::arrayType($aArgs, ['select']);

        $parameter = DatabaseModel::select([
            'select'    => empty($aArgs['select']) ? ['*'] : $aArgs['select'],
            'table'     => ['parameters'],
            'where'     => ['id = ?'],
            'data'      => [$aArgs['id']]
        ]);

        if (empty($parameter[0])) {
            return [];
        }

        return $parameter[0];
    }

    public static function create(array $aArgs)
    {
        ValidatorModel::notEmpty($aArgs, ['id']);
        ValidatorModel::stringType($aArgs, ['id', 'description', 'param_value_string']);
        ValidatorModel::intVal($aArgs, ['param_value_int']);

        DatabaseModel::insert([
            'table'         => 'parameters',
            'columnsValues' => [
                'id'                    => $aArgs['id'],
                'description'           => $aArgs['description'] ?? '',
                'param_value_string'    => $aArgs['param_value_string'] ?? null,
                'param_value_int'       => $aArgs['param_value_int'] ?? null,
                'param_value_date'      => $aArgs['param_value_date'] ?? null
            ]
        ]);

        return true;
    }

    public static function update(array $aArgs)
    {
        ValidatorModel::notEmpty($aArgs, ['id']);
        ValidatorModel::stringType($aArgs, ['id', 'description', 'param_value_string']);
        ValidatorModel::intVal($aArgs, ['param_value_int']);

        DatabaseModel::update([
            'table'     => 'parameters',
            'set'       => [
                'description'           => $aArgs['description'] ?? '',
                'param_value_string'    => $aArgs['param_value_string'] ?? null,
                'param_value_int'       => $aArgs['param_value_int'] ?? null,
                'param_value_date'      => $aArgs['param_value_date'] ?? null
            ],
            'where'     => ['id = ?'],
            'data'      => [$aArgs['id']]
        ]);

        return true;
    }

    public static function delete(array $aArgs)
    {
        ValidatorModel::notEmpty($aArgs, ['id']);
        ValidatorModel::stringType($aArgs, ['id']);

        DatabaseModel::delete([
            'table' => 'parameters',
            'where' => ['id = ?'],
            'data'  => [$aArgs['id']]
        ]);

        return true;
    }
}
