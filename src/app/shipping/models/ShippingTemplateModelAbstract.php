<?php

/**
 * Copyright Maarch since 2008 under licence GPLv3.
 * See LICENCE.txt file at the root folder for more details.
 * This file is part of Maarch software.
 *
 */

/**
* @brief   Shipping Template Model Abstract
* @author  dev@maarch.org
*/

namespace Shipping\models;

use SrcCore\models\ValidatorModel;
use SrcCore\models\DatabaseModel;

abstract class ShippingTemplateModelAbstract
{
    public static function get(array $aArgs = [])
    {
        ValidatorModel::arrayType($aArgs, ['select', 'where', 'data', 'orderBy']);
        ValidatorModel::intType($aArgs, ['limit']);

        $shippings = DatabaseModel::select([
            'select'    => empty($aArgs['select']) ? ['*'] : $aArgs['select'],
            'table'     => ['shipping_templates'],
            'where'     => empty($aArgs['where']) ? [] : $aArgs['where'],
            'data'      => empty($aArgs['data']) ? [] : $aArgs['data'],
            'order_by'  => empty($aArgs['orderBy']) ? [] : $aArgs['orderBy'],
            'limit'     => empty($aArgs['limit']) ? 0 : $aArgs['limit']
        ]);

        return $shippings;
    }

    public static function getById(array $aArgs)
    {
        ValidatorModel::notEmpty($aArgs, ['id']);
        ValidatorModel::intVal($aArgs, ['id']);
        ValidatorModel::arrayType($aArgs, ['select']);

        $shipping = DatabaseModel::select([
            'select' => empty($aArgs['select']) ? ['*'] : $aArgs['select'],
            'table'  => ['shipping_templates'],
            'where'  => ['id = ?'],
            'data'   => [$aArgs['id']]
        ]);

        if (empty($shipping[0])) {
            return [];
        }

        return $shipping[0];
    }

    public static function getByEntities(array $aArgs)
    {
        ValidatorModel::notEmpty($aArgs, ['entities']);
        ValidatorModel::arrayType($aArgs, ['select', 'entities']);

        $shippings = DatabaseModel::select([
            'select' => empty($aArgs['select']) ? ['*'] : $aArgs['select'],
            'table'  => ['shipping_templates'],
            'where'  => ['entities @> ?'],
            'data'   => [json_encode($aArgs['entities'])]
        ]);

        return $shippings;
    }

    public static function create(array $aArgs)
    {
        ValidatorModel::notEmpty($aArgs, ['label', 'description']);
        ValidatorModel::stringType($aArgs, ['label', 'description', 'options', 'fee', 'entities', 'account']);

        $nextSequenceId = DatabaseModel::getNextSequenceValue(['sequenceId' => 'shipping_templates_id_seq']);

        DatabaseModel::insert([
            'table'         => 'shipping_templates',
            'columnsValues' => [
                'id'             => $nextSequenceId,
                'label'          => $aArgs['label'],
                'description'    => $aArgs['description'],
                'options'        => $aArgs['options'],
                'fee'            => $aArgs['fee'],
                'entities'       => $aArgs['entities'],
                'account'        => $aArgs['account']
            ]
        ]);

        return $nextSequenceId;
    }

    public static function update(array $args)
    {
        ValidatorModel::notEmpty($args, ['where', 'data']);
        ValidatorModel::arrayType($args, ['set', 'postSet', 'where', 'data']);

        DatabaseModel::update([
            'table'     => 'shipping_templates',
            'set'       => $args['set'] ?? null,
            'postSet'   => $args['postSet'] ?? null,
            'where'     => $args['where'],
            'data'      => $args['data']
        ]);

        return true;
    }

    public static function delete(array $aArgs)
    {
        ValidatorModel::notEmpty($aArgs, ['id']);
        ValidatorModel::intVal($aArgs, ['id']);

        DatabaseModel::delete([
            'table' => 'shipping_templates',
            'where' => ['id = ?'],
            'data'  => [$aArgs['id']]
        ]);

        return true;
    }
}
