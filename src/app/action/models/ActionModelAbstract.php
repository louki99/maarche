<?php

/**
 * Copyright Maarch since 2008 under licence GPLv3.
 * See LICENCE.txt file at the root folder for more details.
 * This file is part of Maarch software.
 *
 */

/**
* @brief   Action Model Abstract
* @author  dev@maarch.org
*/

namespace Action\models;

use SrcCore\models\ValidatorModel;
use SrcCore\models\CoreConfigModel;
use SrcCore\models\DatabaseModel;

abstract class ActionModelAbstract
{
    public static function get(array $aArgs = [])
    {
        ValidatorModel::arrayType($aArgs, ['select', 'where', 'data', 'orderBy']);
        ValidatorModel::intType($aArgs, ['limit']);

        $actions = DatabaseModel::select([
            'select'    => empty($aArgs['select']) ? ['*'] : $aArgs['select'],
            'table'     => ['actions'],
            'where'     => empty($aArgs['where']) ? [] : $aArgs['where'],
            'data'      => empty($aArgs['data']) ? [] : $aArgs['data'],
            'order_by'  => empty($aArgs['orderBy']) ? [] : $aArgs['orderBy'],
            'limit'     => empty($aArgs['limit']) ? 0 : $aArgs['limit']
        ]);

        return $actions;
    }

    public static function getById(array $aArgs)
    {
        ValidatorModel::notEmpty($aArgs, ['id']);
        ValidatorModel::intVal($aArgs, ['id']);
        ValidatorModel::arrayType($aArgs, ['select']);

        $action = DatabaseModel::select([
            'select' => empty($aArgs['select']) ? ['*'] : $aArgs['select'],
            'table'  => ['actions'],
            'where'  => ['id = ?'],
            'data'   => [$aArgs['id']]
        ]);

        if (empty($action[0])) {
            return [];
        }

        return $action[0];
    }

    public static function create(array $aArgs)
    {
        $nextSequenceId = DatabaseModel::getNextSequenceValue(['sequenceId' => 'actions_id_seq']);

        unset($aArgs['actionCategories']);
        $aArgs['id'] = $nextSequenceId;
        DatabaseModel::insert([
            'table'         => 'actions',
            'columnsValues' => $aArgs
        ]);

        return $nextSequenceId;
    }

    public static function update(array $args)
    {
        ValidatorModel::notEmpty($args, ['where', 'data']);
        ValidatorModel::arrayType($args, ['where', 'data']);

        DatabaseModel::update([
            'table'   => 'actions',
            'set'     => !empty($args['set']) ? $args['set'] : [],
            'postSet' => !empty($args['postSet']) ? $args['postSet'] : [],
            'where'   => $args['where'],
            'data'    => $args['data'],
        ]);

        return true;
    }

    public static function delete(array $aArgs)
    {
        ValidatorModel::notEmpty($aArgs, ['id']);
        ValidatorModel::intVal($aArgs, ['id']);

        DatabaseModel::delete([
            'table' => 'actions',
            'where' => ['id = ?'],
            'data'  => [$aArgs['id']]
        ]);
        DatabaseModel::delete([
            'table' => 'actions_groupbaskets',
            'where' => ['id_action = ?'],
            'data'  => [$aArgs['id']]
        ]);

        return true;
    }

    public static function getCategoriesById(array $aArgs)
    {
        ValidatorModel::notEmpty($aArgs, ['id']);
        ValidatorModel::intVal($aArgs, ['id']);

        $categories = DatabaseModel::select([
            'select' => ['category_id'],
            'table'  => ['actions_categories'],
            'where'  => ['action_id = ?'],
            'data'   => [$aArgs['id']]
        ]);

        return $categories;
    }

    public static function createCategories(array $aArgs)
    {
        ValidatorModel::notEmpty($aArgs, ['id', 'categories']);
        ValidatorModel::intVal($aArgs, ['id']);
        ValidatorModel::arrayType($aArgs, ['categories']);

        foreach ($aArgs['categories'] as $category) {
            DatabaseModel::insert([
                'table'         => 'actions_categories',
                'columnsValues' => [
                    'action_id' => $aArgs['id'],
                    'category_id' => $category,
                ]
            ]);
        }

        return true;
    }

    public static function deleteCategories(array $aArgs)
    {
        ValidatorModel::notEmpty($aArgs, ['id']);
        ValidatorModel::intVal($aArgs, ['id']);

        DatabaseModel::delete([
            'table' => 'actions_categories',
            'where' => ['action_id = ?'],
            'data'  => [$aArgs['id']]
        ]);

        return true;
    }

    public static function getKeywords()
    {
        $tabKeyword   = [];
        $tabKeyword[] = ['value' => '', 'label' => _NO_KEYWORD];
        $tabKeyword[] = ['value' => 'redirect', 'label' => _REDIRECTION, 'desc' => _KEYWORD_REDIRECT_DESC];

        return $tabKeyword;
    }

    public static function getActionPageById(array $aArgs)
    {
        ValidatorModel::notEmpty($aArgs, ['id']);
        ValidatorModel::intVal($aArgs, ['id']);

        $action = DatabaseModel::select([
            'select' => ['action_page'],
            'table'  => ['actions'],
            'where'  => ['id = ? AND enabled = ?'],
            'data'   => [$aArgs['id'], 'Y']
        ]);

        if (empty($action[0])) {
            return '';
        }

        return $action[0]['action_page'];
    }

    public static function getForBasketPage(array $aArgs)
    {
        ValidatorModel::notEmpty($aArgs, ['basketId', 'groupId']);
        ValidatorModel::stringType($aArgs, ['basketId', 'groupId']);

        $actions = DatabaseModel::select([
            'select'    => ['id_action', 'where_clause', 'default_action_list', 'actions.label_action'],
            'table'     => ['actions_groupbaskets, actions'],
            'where'     => ['basket_id = ?', 'group_id = ?', 'used_in_action_page = ?', 'actions_groupbaskets.id_action = actions.id'],
            'data'      => [$aArgs['basketId'], $aArgs['groupId'], 'Y'],
            'order_by'  => ['default_action_list DESC']
        ]);

        return $actions;
    }
}
