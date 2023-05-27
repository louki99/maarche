<?php

/**
* Copyright Maarch since 2008 under licence GPLv3.
* See LICENCE.txt file at the root folder for more details.
* This file is part of Maarch software.
*
*/

/**
* @brief Docserver Model
* @author dev@maarch.org
* @ingroup core
*/

namespace Docserver\models;

use SrcCore\models\DatabaseModel;
use SrcCore\models\ValidatorModel;

class DocserverModelAbstract
{
    public static function get(array $aArgs = [])
    {
        ValidatorModel::arrayType($aArgs, ['select', 'where', 'data', 'orderBy']);
        ValidatorModel::intType($aArgs, ['limit']);

        $aDocservers = DatabaseModel::select([
            'select'    => empty($aArgs['select']) ? ['*'] : $aArgs['select'],
            'table'     => ['docservers'],
            'where'     => empty($aArgs['where']) ? [] : $aArgs['where'],
            'data'      => empty($aArgs['data']) ? [] : $aArgs['data'],
            'order_by'  => empty($aArgs['orderBy']) ? [] : $aArgs['orderBy'],
            'limit'     => empty($aArgs['limit']) ? 0 : $aArgs['limit']
        ]);

        return $aDocservers;
    }

    public static function getById(array $aArgs)
    {
        ValidatorModel::notEmpty($aArgs, ['id']);
        ValidatorModel::intVal($aArgs, ['id']);
        ValidatorModel::arrayType($aArgs, ['select']);

        $aDocserver = DatabaseModel::select([
            'select'    => empty($aArgs['select']) ? ['*'] : $aArgs['select'],
            'table'     => ['docservers'],
            'where'     => ['id = ?'],
            'data'      => [$aArgs['id']]
        ]);

        if (empty($aDocserver[0])) {
            return [];
        }

        return $aDocserver[0];
    }

    public static function getByDocserverId(array $aArgs)
    {
        ValidatorModel::notEmpty($aArgs, ['docserverId']);
        ValidatorModel::stringType($aArgs, ['docserverId']);
        ValidatorModel::arrayType($aArgs, ['select']);

        $aDocserver = DatabaseModel::select([
            'select'    => empty($aArgs['select']) ? ['*'] : $aArgs['select'],
            'table'     => ['docservers'],
            'where'     => ['docserver_id = ?'],
            'data'      => [$aArgs['docserverId']]
        ]);

        if (empty($aDocserver[0])) {
            return [];
        }

        return $aDocserver[0];
    }

    public static function create(array $aArgs)
    {
        ValidatorModel::notEmpty($aArgs, ['docserver_id', 'docserver_type_id', 'device_label', 'path_template', 'coll_id', 'size_limit_number', 'is_readonly']);
        ValidatorModel::stringType($aArgs, ['docserver_id', 'docserver_type_id', 'device_label', 'path_template', 'coll_id', 'is_readonly']);
        ValidatorModel::intVal($aArgs, ['size_limit_number']);

        $nextSequenceId = DatabaseModel::getNextSequenceValue(['sequenceId' => 'docservers_id_seq']);

        DatabaseModel::insert([
            'table'         => 'docservers',
            'columnsValues' => [
                'id'                    => $nextSequenceId,
                'docserver_id'          => $aArgs['docserver_id'],
                'docserver_type_id'     => $aArgs['docserver_type_id'],
                'device_label'          => $aArgs['device_label'],
                'path_template'         => $aArgs['path_template'],
                'coll_id'               => $aArgs['coll_id'],
                'size_limit_number'     => $aArgs['size_limit_number'],
                'is_readonly'           => $aArgs['is_readonly'],
                'creation_date'         => 'CURRENT_TIMESTAMP'
            ]
        ]);

        return $nextSequenceId;
    }

    public static function update(array $aArgs)
    {
        ValidatorModel::notEmpty($aArgs, ['set', 'where', 'data']);
        ValidatorModel::arrayType($aArgs, ['set', 'where', 'data']);

        DatabaseModel::update([
            'table' => 'docservers',
            'set'   => $aArgs['set'],
            'where' => $aArgs['where'],
            'data'  => $aArgs['data']
        ]);

        return true;
    }

    public static function delete(array $aArgs)
    {
        ValidatorModel::notEmpty($aArgs, ['id']);
        ValidatorModel::intVal($aArgs, ['id']);

        DatabaseModel::delete([
            'table'     => 'docservers',
            'where'     => ['id = ?'],
            'data'      => [$aArgs['id']]
        ]);

        return true;
    }

    public static function getCurrentDocserver(array $aArgs)
    {
        ValidatorModel::notEmpty($aArgs, ['collId', 'typeId']);
        ValidatorModel::stringType($aArgs, ['collId', 'typeId']);

        $aDocserver = DatabaseModel::select([
            'select'    => ['*'],
            'table'     => ['docservers'],
            'where'     => ['is_readonly = ?', 'coll_id = ?', 'docserver_type_id = ?'],
            'data'      => ['N', $aArgs['collId'], $aArgs['typeId']],
            'limit'     => 1,
        ]);

        if (empty($aDocserver[0])) {
            return [];
        }

        return $aDocserver[0];
    }
}
