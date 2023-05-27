<?php
/**
* Copyright Maarch since 2008 under licence GPLv3.
* See LICENCE.txt file at the root folder for more details.
* This file is part of Maarch software.

* @brief   DoctypeModelAbstract
* @author  dev <dev@maarch.org>
* @ingroup core
*/

namespace Doctype\models;

use SrcCore\models\ValidatorModel;
use SrcCore\models\DatabaseModel;

class DoctypeModelAbstract
{
    public static function get(array $aArgs = [])
    {
        ValidatorModel::arrayType($aArgs, ['select', 'where', 'data', 'orderBy']);
        ValidatorModel::intType($aArgs, ['limit']);

        $doctypes = DatabaseModel::select([
            'select'    => empty($aArgs['select']) ? ['*'] : $aArgs['select'],
            'table'     => ['doctypes'],
            'where'     => empty($aArgs['where']) ? [] : $aArgs['where'],
            'data'      => empty($aArgs['data']) ? [] : $aArgs['data'],
            'order_by'  => empty($aArgs['orderBy']) ? [] : $aArgs['orderBy'],
            'limit'     => empty($aArgs['limit']) ? 0 : $aArgs['limit']
        ]);

        return $doctypes;
    }

    public static function getById(array $aArgs)
    {
        ValidatorModel::notEmpty($aArgs, ['id']);
        ValidatorModel::intVal($aArgs, ['id']);

        $aReturn = DatabaseModel::select([
            'select' => empty($aArgs['select']) ? ['*'] : $aArgs['select'],
            'table'  => ['doctypes'],
            'where'  => ['type_id = ?'],
            'data'   => [$aArgs['id']]
        ]);

        if (empty($aReturn[0])) {
            return [];
        }

        return $aReturn[0];
    }

    public static function create(array $aArgs)
    {
        ValidatorModel::notEmpty($aArgs, ['description', 'doctypes_first_level_id', 'doctypes_second_level_id']);
        ValidatorModel::intVal($aArgs, ['doctypes_first_level_id', 'doctypes_second_level_id']);

        $aArgs['type_id'] = DatabaseModel::getNextSequenceValue(['sequenceId' => 'doctypes_type_id_seq']);
        DatabaseModel::insert([
            'table'         => 'doctypes',
            'columnsValues' => $aArgs
        ]);

        return $aArgs['type_id'];
    }

    public static function update(array $aArgs)
    {
        ValidatorModel::notEmpty($aArgs, ['type_id']);
        ValidatorModel::intVal($aArgs, ['type_id']);
        
        DatabaseModel::update([
            'table'     => 'doctypes',
            'set'       => $aArgs,
            'where'     => ['type_id = ?'],
            'data'      => [$aArgs['type_id']]
        ]);

        return true;
    }

    public static function disabledFirstLevel(array $aArgs)
    {
        ValidatorModel::notEmpty($aArgs, ['doctypes_first_level_id']);
        ValidatorModel::intVal($aArgs, ['doctypes_first_level_id']);
        
        DatabaseModel::update([
            'table'     => 'doctypes',
            'set'       => $aArgs,
            'where'     => ['doctypes_first_level_id = ?'],
            'data'      => [$aArgs['doctypes_first_level_id']]
        ]);

        return true;
    }

    public static function disabledSecondLevel(array $aArgs)
    {
        ValidatorModel::notEmpty($aArgs, ['doctypes_second_level_id']);
        ValidatorModel::intVal($aArgs, ['doctypes_second_level_id']);
        
        DatabaseModel::update([
            'table'     => 'doctypes',
            'set'       => $aArgs,
            'where'     => ['doctypes_second_level_id = ?'],
            'data'      => [$aArgs['doctypes_second_level_id']]
        ]);

        return true;
    }

    public static function delete(array $aArgs)
    {
        ValidatorModel::notEmpty($aArgs, ['type_id']);
        ValidatorModel::intVal($aArgs, ['type_id']);

        DatabaseModel::delete([
            'table' => 'doctypes',
            'where' => ['type_id = ?'],
            'data'  => [$aArgs['type_id']]
        ]);

        return true;
    }
}
