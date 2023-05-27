<?php

/**
 * Copyright Maarch since 2008 under licence GPLv3.
 * See LICENCE.txt file at the root folder for more details.
 * This file is part of Maarch software.
 *
 */

/**
 * @brief Contact Group Model
 * @author dev@maarch.org
 */

namespace Contact\models;

use SrcCore\models\DatabaseModel;
use SrcCore\models\ValidatorModel;

class ContactGroupModel
{
    public static function get(array $args = [])
    {
        ValidatorModel::arrayType($args, ['select', 'where', 'data', 'orderBy']);
        ValidatorModel::intType($args, ['limit']);

        $contactGroups = DatabaseModel::select([
            'select'    => empty($args['select']) ? ['*'] : $args['select'],
            'table'     => ['contacts_groups'],
            'where'     => empty($args['where']) ? [] : $args['where'],
            'data'      => empty($args['data']) ? [] : $args['data'],
            'order_by'  => empty($args['orderBy']) ? [] : $args['orderBy'],
            'limit'     => empty($args['limit']) ? 0 : $args['limit']
        ]);

        return $contactGroups;
    }

    public static function getById(array $args)
    {
        ValidatorModel::notEmpty($args, ['id']);
        ValidatorModel::intVal($args, ['id']);
        ValidatorModel::arrayType($args, ['select']);

        $contactGroup = DatabaseModel::select([
            'select'    => empty($args['select']) ? ['*'] : $args['select'],
            'table'     => ['contacts_groups'],
            'where'     => ['id = ?'],
            'data'      => [$args['id']]
        ]);

        if (empty($contactGroup)) {
            return [];
        }
        return $contactGroup[0];
    }

    public static function create(array $args)
    {
        ValidatorModel::notEmpty($args, ['label', 'description', 'owner']);
        ValidatorModel::stringType($args, ['label', 'description', 'entities']);
        ValidatorModel::intVal($args, ['owner']);

        $nextSequenceId = DatabaseModel::getNextSequenceValue(['sequenceId' => 'contacts_groups_id_seq']);
        DatabaseModel::insert([
            'table'         => 'contacts_groups',
            'columnsValues' => [
                'id'            => $nextSequenceId,
                'label'         => $args['label'],
                'description'   => $args['description'],
                'entities'      => $args['entities'],
                'owner'         => $args['owner']
            ]
        ]);

        return $nextSequenceId;
    }

    public static function update(array $args)
    {
        ValidatorModel::notEmpty($args, ['set']);
        ValidatorModel::arrayType($args, ['set']);

        DatabaseModel::update([
            'table'     => 'contacts_groups',
            'set'       => $args['set'],
            'where'     => $args['where'],
            'data'      => $args['data']
        ]);

        return true;
    }

    public static function delete(array $args)
    {
        ValidatorModel::arrayType($args, ['where', 'data']);

        DatabaseModel::delete([
            'table' => 'contacts_groups',
            'where' => $args['where'],
            'data'  => $args['data']
        ]);

        return true;
    }

    public static function getWithList(array $args = [])
    {
        ValidatorModel::arrayType($args, ['select', 'where', 'data', 'orderBy']);
        ValidatorModel::intType($args, ['limit']);

        $contactGroups = DatabaseModel::select([
            'select'    => empty($args['select']) ? ['*'] : $args['select'],
            'table'     => ['contacts_groups', 'contacts_groups_lists'],
            'left_join' => ['contacts_groups.id = contacts_groups_lists.contacts_groups_id'],
            'where'     => empty($args['where']) ? [] : $args['where'],
            'data'      => empty($args['data']) ? [] : $args['data'],
            'order_by'  => empty($args['orderBy']) ? [] : $args['orderBy'],
            'limit'     => empty($args['limit']) ? 0 : $args['limit'],
            'offset'    => empty($args['offset']) ? 0 : $args['offset']
        ]);

        return $contactGroups;
    }
}
