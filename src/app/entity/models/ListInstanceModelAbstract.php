<?php

/**
 * Copyright Maarch since 2008 under licence GPLv3.
 * See LICENCE.txt file at the root folder for more details.
 * This file is part of Maarch software.
 *
 */

/**
 * @brief List Instance Model Abstract
 * @author dev@maarch.org
 */

namespace Entity\models;

use SrcCore\models\ValidatorModel;
use SrcCore\models\DatabaseModel;

abstract class ListInstanceModelAbstract
{
    public static function get(array $args)
    {
        ValidatorModel::notEmpty($args, ['select']);
        ValidatorModel::arrayType($args, ['select', 'where', 'data', 'orderBy', 'groupBy']);
        ValidatorModel::intType($args, ['limit']);

        $listInstances = DatabaseModel::select([
            'select'    => $args['select'],
            'table'     => ['listinstance'],
            'where'     => empty($args['where']) ? [] : $args['where'],
            'data'      => empty($args['data']) ? [] : $args['data'],
            'order_by'  => empty($args['orderBy']) ? [] : $args['orderBy'],
            'groupBy'   => empty($args['groupBy']) ? [] : $args['groupBy'],
            'limit'     => empty($args['limit']) ? 0 : $args['limit']
        ]);

        return $listInstances;
    }

    public static function create(array $args)
    {
        ValidatorModel::notEmpty($args, ['res_id', 'item_id', 'item_type', 'item_mode', 'added_by_user', 'difflist_type']);
        ValidatorModel::intVal($args, ['res_id', 'sequence', 'viewed', 'item_id', 'added_by_user']);
        ValidatorModel::stringType($args, ['item_type', 'item_mode', 'difflist_type', 'process_date', 'process_comment']);

        DatabaseModel::insert([
            'table'         => 'listinstance',
            'columnsValues' => [
                'res_id'                    => $args['res_id'],
                'sequence'                  => $args['sequence'],
                'item_id'                   => $args['item_id'],
                'item_type'                 => $args['item_type'],
                'item_mode'                 => $args['item_mode'],
                'added_by_user'             => $args['added_by_user'],
                'viewed'                    => $args['viewed'] ?? 0,
                'difflist_type'             => $args['difflist_type'],
                'process_date'              => $args['process_date'] ?? null,
                'process_comment'           => $args['process_comment'] ?? null,
                'requested_signature'       => empty($args['requested_signature']) ? 'false' : 'true',
                'delegate'                  => $args['delegate'] ?? null,
                'signatory'                 => empty($args['signatory']) ? 'false' : 'true'
            ]
        ]);

        return true;
    }

    public static function update(array $aArgs)
    {
        ValidatorModel::notEmpty($aArgs, ['where', 'data']);
        ValidatorModel::arrayType($aArgs, ['set', 'postSet', 'where', 'data']);

        DatabaseModel::update([
            'table'     => 'listinstance',
            'set'       => $aArgs['set'],
            'postSet'   => $aArgs['postSet'] ?? null,
            'where'     => $aArgs['where'],
            'data'      => $aArgs['data']
        ]);

        return true;
    }

    public static function delete(array $args)
    {
        ValidatorModel::notEmpty($args, ['where', 'data']);
        ValidatorModel::arrayType($args, ['where', 'data']);

        DatabaseModel::delete([
            'table' => 'listinstance',
            'where' => $args['where'],
            'data'  => $args['data']
        ]);

        return true;
    }

    public static function getVisaCircuitByResId(array $aArgs)
    {
        ValidatorModel::notEmpty($aArgs, ['id']);
        ValidatorModel::intVal($aArgs, ['id']);
        ValidatorModel::arrayType($aArgs, ['select']);

        $aListinstance = DatabaseModel::select([
            'select'    => empty($aArgs['select']) ? ['*'] : $aArgs['select'],
            'table'     => ['listinstance', 'users'],
            'left_join' => ['listinstance.item_id = users.id'],
            'where'     => ['res_id = ?', 'item_type = ?', 'difflist_type = ?'],
            'data'      => [$aArgs['id'], 'user_id', 'VISA_CIRCUIT'],
            'order_by'  => ['listinstance_id ASC'],
        ]);

        return $aListinstance;
    }

    public static function getAvisCircuitByResId(array $aArgs)
    {
        ValidatorModel::notEmpty($aArgs, ['id']);
        ValidatorModel::intVal($aArgs, ['id']);
        ValidatorModel::arrayType($aArgs, ['select']);

        $aListinstance = DatabaseModel::select([
            'select'    => empty($aArgs['select']) ? ['*'] : $aArgs['select'],
            'table'     => ['listinstance', 'users'],
            'left_join' => ['listinstance.item_id = users.id'],
            'where'     => ['res_id = ?', 'item_type = ?', 'difflist_type = ?'],
            'data'      => [$aArgs['id'], 'user_id', 'AVIS_CIRCUIT'],
            'order_by'  => ['listinstance_id ASC'],
        ]);

        return $aListinstance;
    }

    public static function getParallelOpinionByResId(array $aArgs)
    {
        ValidatorModel::notEmpty($aArgs, ['id']);
        ValidatorModel::intVal($aArgs, ['id']);
        ValidatorModel::arrayType($aArgs, ['select']);

        $aListinstance = DatabaseModel::select([
            'select'    => empty($aArgs['select']) ? ['*'] : $aArgs['select'],
            'table'     => ['listinstance', 'users', 'users_entities', 'entities'],
            'left_join' => ['listinstance.item_id = users.id', 'users_entities.user_id = users.id', 'entities.entity_id = users_entities.entity_id'],
            'where'     => ['res_id = ?', 'item_type = ?', 'difflist_type = ?', 'primary_entity = ?', 'item_mode in (?)'],
            'data'      => [$aArgs['id'], 'user_id', 'entity_id', 'Y', ['avis', 'avis_copy', 'avis_info']],
            'order_by'  => ['listinstance_id ASC'],
        ]);

        return $aListinstance;
    }

    public static function getCurrentStepByResId(array $aArgs)
    {
        ValidatorModel::notEmpty($aArgs, ['resId']);
        ValidatorModel::intVal($aArgs, ['resId']);
        ValidatorModel::arrayType($aArgs, ['select']);

        $aListinstance = DatabaseModel::select([
            'select'    => empty($aArgs['select']) ? ['*'] : $aArgs['select'],
            'table'     => ['listinstance'],
            'where'     => ['res_id = ?', 'difflist_type = ?', 'process_date is null'],
            'data'      => [$aArgs['resId'], 'VISA_CIRCUIT'],
            'order_by'  => ['listinstance_id ASC'],
            'limit'     => 1
        ]);

        if (empty($aListinstance[0])) {
            return [];
        }

        return $aListinstance[0];
    }

    public static function getWithConfidentiality(array $args)
    {
        ValidatorModel::notEmpty($args, ['entityId', 'userId']);
        ValidatorModel::stringType($args, ['entityId']);
        ValidatorModel::intVal($args, ['userId']);
        ValidatorModel::arrayType($args, ['select']);

        $aListInstances = DatabaseModel::select([
            'select'    => empty($args['select']) ? ['*'] : $args['select'],
            'table'     => ['listinstance, res_letterbox'],
            'where'     => ['listinstance.res_id = res_letterbox.res_id', 'confidentiality = ?', 'destination = ?', 'item_id = ?', 'closing_date is null'],
            'data'      => ['Y', $args['entityId'], $args['userId']]
        ]);

        return $aListInstances;
    }

    public static function getWhenOpenMailsByUserId(array $aArgs)
    {
        ValidatorModel::notEmpty($aArgs, ['userId', 'itemMode']);
        ValidatorModel::stringType($aArgs, ['itemMode']);
        ValidatorModel::intVal($aArgs, ['userId']);
        ValidatorModel::arrayType($aArgs, ['select']);

        $listInstances = DatabaseModel::select([
            'select'    => empty($aArgs['select']) ? ['*'] : $aArgs['select'],
            'table'     => ['listinstance', 'res_letterbox'],
            'left_join' => ['listinstance.res_id = res_letterbox.res_id'],
            'where'     => ['listinstance.item_id = ?', 'listinstance.difflist_type = ?', 'listinstance.item_type = ?', 'listinstance.item_mode = ?', 'res_letterbox.closing_date is null', 'res_letterbox.status != ?'],
            'data'      => [$aArgs['userId'], 'entity_id', 'user_id', $aArgs['itemMode'], 'DEL']
        ]);

        return $listInstances;
    }
}
