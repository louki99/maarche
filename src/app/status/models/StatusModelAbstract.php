<?php

/**
* Copyright Maarch since 2008 under licence GPLv3.
* See LICENCE.txt file at the root folder for more details.
* This file is part of Maarch software.
*
*/

/**
* @brief Status Model
* @author dev@maarch.org
*/

namespace Status\models;

use SrcCore\models\ValidatorModel;
use SrcCore\models\DatabaseModel;

abstract class StatusModelAbstract
{
    public static function get(array $args = [])
    {
        ValidatorModel::arrayType($args, ['select', 'where', 'data']);

        $statuses = DatabaseModel::select([
            'select'    => empty($args['select']) ? ['*'] : $args['select'],
            'table'     => ['status'],
            'where'     => $args['where'] ?? [],
            'data'      => $args['data'] ?? [],
            'order_by'  => ['label_status']
        ]);

        return $statuses;
    }

    public static function getById(array $aArgs)
    {
        ValidatorModel::notEmpty($aArgs, ['id']);
        ValidatorModel::stringType($aArgs, ['id']);

        $aReturn = DatabaseModel::select([
            'select'    => empty($aArgs['select']) ? ['*'] : $aArgs['select'],
            'table'     => ['status'],
            'where'     => ['id = ?'],
            'data'      => [$aArgs['id']]
        ]);

        if (empty($aReturn[0])) {
            return [];
        }

        return $aReturn[0];
    }

    public static function getByIdentifier(array $aArgs)
    {
        ValidatorModel::notEmpty($aArgs, ['identifier']);
        ValidatorModel::intVal($aArgs, ['identifier']);

        $aReturn = DatabaseModel::select([
            'select'    => empty($aArgs['select']) ? ['*'] : $aArgs['select'],
            'table'     => ['status'],
            'where'     => ['identifier = ?'],
            'data'      => [$aArgs['identifier']]
        ]);

        return $aReturn;
    }

    public static function getByResId(array $args)
    {
        ValidatorModel::notEmpty($args, ['resId', 'collId']);
        ValidatorModel::intVal($args, ['resId']);
        ValidatorModel::stringType($args, ['collId']);

        $joinTable = $args['collId'] == 'attachments_coll' ? 'res_attachments' : 'res_letterbox';

        $select = empty($args['select']) ? ['*'] : $args['select'];
        foreach ($select as $key => $val) {
            $select[$key] = 'status.' . trim($val);
        }

        $status = DatabaseModel::select([
            'select'    => $select,
            'table'     => ['status', $joinTable . ' AS r'],
            'left_join' => ['status.id = r.status'],
            'where'     => ['r.res_id = ?'],
            'data'      => [$args['resId']]
        ]);

        if (empty($status[0])) {
            return [];
        }

        return $status[0];
    }

    public static function create(array $aArgs)
    {
        ValidatorModel::notEmpty($aArgs, ['id', 'label_status']);
        ValidatorModel::stringType($aArgs, ['id', 'label_status']);

        DatabaseModel::insert([
            'table'         => 'status',
            'columnsValues' => $aArgs
        ]);

        return true;
    }

    public static function update(array $aArgs)
    {
        ValidatorModel::notEmpty($aArgs, ['label_status', 'identifier']);
        ValidatorModel::intVal($aArgs, ['identifier']);

        $where['identifier'] = $aArgs['identifier'];
        unset($aArgs['id']);
        unset($aArgs['identifier']);

        DatabaseModel::update([
            'table' => 'status',
            'set'   => $aArgs,
            'where' => ['identifier = ?'],
            'data'  => [$where['identifier']]
        ]);

        return true;
    }

    public static function delete(array $aArgs)
    {
        ValidatorModel::notEmpty($aArgs, ['identifier']);
        ValidatorModel::intVal($aArgs, ['identifier']);

        DatabaseModel::delete([
            'table' => 'status',
            'where' => ['identifier = ?'],
            'data'  => [$aArgs['identifier']]
        ]);

        return true;
    }
}
