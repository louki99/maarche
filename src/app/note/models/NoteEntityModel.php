<?php

/**
 * Copyright Maarch since 2008 under licence GPLv3.
 * See LICENCE.txt file at the root folder for more details.
 * This file is part of Maarch software.
 *
 */

/**
 * @brief Note Entity Model
 * @author dev@maarch.org
 */

namespace Note\models;

use SrcCore\models\DatabaseModel;
use SrcCore\models\ValidatorModel;

class NoteEntityModel
{
    public static function get(array $aArgs = [])
    {
        ValidatorModel::arrayType($aArgs, ['select', 'where', 'data']);

        $noteEntities = DatabaseModel::select([
            'select'    => empty($aArgs['select']) ? ['*'] : $aArgs['select'],
            'table'     => ['note_entities'],
            'where'     => empty($aArgs['where']) ? [] : $aArgs['where'],
            'data'      => empty($aArgs['data']) ? [] : $aArgs['data']
        ]);

        return $noteEntities;
    }

    public static function create(array $aArgs)
    {
        ValidatorModel::notEmpty($aArgs, ['note_id', 'item_id']);
        ValidatorModel::intVal($aArgs, ['note_id']);
        ValidatorModel::stringType($aArgs, ['item_id']);

        DatabaseModel::insert([
            'table' => 'note_entities',
            'columnsValues' => [
                'note_id'   => $aArgs['note_id'],
                'item_id'   => $aArgs['item_id']
            ]
        ]);

        return true;
    }

    public static function delete(array $args)
    {
        ValidatorModel::notEmpty($args, ['where', 'data']);
        ValidatorModel::arrayType($args, ['where', 'data']);

        DatabaseModel::delete([
            'table' => 'note_entities',
            'where' => $args['where'],
            'data'  => $args['data']
        ]);

        return true;
    }

    public static function getWithEntityInfo(array $aArgs = [])
    {
        ValidatorModel::arrayType($aArgs, ['select', 'where', 'data']);

        $noteEntities = DatabaseModel::select([
            'select'    => empty($aArgs['select']) ? ['*'] : $aArgs['select'],
            'table'     => ['note_entities', 'entities'],
            'left_join' => ['note_entities.item_id = entities.entity_id'],
            'where'     => empty($aArgs['where']) ? [] : $aArgs['where'],
            'data'      => empty($aArgs['data']) ? [] : $aArgs['data']
        ]);

        return $noteEntities;
    }
}
