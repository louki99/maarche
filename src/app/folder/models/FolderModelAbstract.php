<?php
/**
* Copyright Maarch since 2008 under licence GPLv3.
* See LICENCE.txt file at the root folder for more details.
* This file is part of Maarch software.

* @brief   FolderModelAbstract
* @author  dev <dev@maarch.org>
* @ingroup core
*/

namespace Folder\models;

use SrcCore\models\ValidatorModel;
use SrcCore\models\DatabaseModel;

class FolderModelAbstract
{
    public static function get(array $aArgs)
    {
        $folders = DatabaseModel::select([
            'select'    => empty($aArgs['select']) ? ['*'] : $aArgs['select'],
            'table'     => ['folders'],
            'where'     => empty($aArgs['where']) ? [] : $aArgs['where'],
            'data'      => empty($aArgs['data']) ? [] : $aArgs['data'],
            'order_by'  => empty($aArgs['orderBy']) ? ['label'] : $aArgs['orderBy']
        ]);

        return $folders;
    }

    public static function getById(array $aArgs)
    {
        ValidatorModel::notEmpty($aArgs, ['id']);
        ValidatorModel::intVal($aArgs, ['id']);

        $folder = DatabaseModel::select([
            'select'    => empty($aArgs['select']) ? ['*'] : $aArgs['select'],
            'table'     => ['folders', 'entities_folders'],
            'left_join' => ['folders.id = entities_folders.folder_id'],
            'where'     => ['folders.id = ?'],
            'data'      => [$aArgs['id']]
        ]);

        if (empty($folder[0])) {
            return [];
        }

        return $folder[0];
    }

    public static function getFolderPath(array $aArgs)
    {
        ValidatorModel::notEmpty($aArgs, ['id']);
        ValidatorModel::intVal($aArgs, ['id']);

        if (empty($aArgs['folderPath'])) {
            $aArgs['folderPath'] = [];
        }

        $currentFolder = FolderModel::getById(['select' => ['parent_id', 'label'], 'id' => $aArgs['id']]);
        array_unshift($aArgs['folderPath'], $currentFolder['label']);
        if (!empty($currentFolder['parent_id'])) {
            return FolderModel::getFolderPath(['id' => $currentFolder['parent_id'], 'folderPath' => $aArgs['folderPath']]);
        }

        return $aArgs['folderPath'];
    }

    public static function getChild(array $aArgs)
    {
        ValidatorModel::notEmpty($aArgs, ['id']);
        ValidatorModel::intVal($aArgs, ['id']);

        $folders = DatabaseModel::select([
            'select'    => empty($aArgs['select']) ? ['*'] : $aArgs['select'],
            'table'     => ['folders'],
            'where'     => ['parent_id = ?'],
            'data'      => [$aArgs['id']]
        ]);

        return $folders;
    }

    public static function create(array $aArgs)
    {
        ValidatorModel::notEmpty($aArgs, ['user_id', 'label']);
        ValidatorModel::stringType($aArgs, ['label']);
        ValidatorModel::intVal($aArgs, ['user_id', 'parent_id', 'level']);
        ValidatorModel::boolType($aArgs, ['public']);

        $nextSequenceId = DatabaseModel::getNextSequenceValue(['sequenceId' => 'folders_id_seq']);

        DatabaseModel::insert([
            'table'     => 'folders',
            'columnsValues'     => [
                'id'        => $nextSequenceId,
                'label'     => $aArgs['label'],
                'public'    => empty($aArgs['public']) ? 'false' : 'true',
                'user_id'   => $aArgs['user_id'],
                'parent_id' => $aArgs['parent_id'],
                'level'     => $aArgs['level']
            ]
        ]);

        return $nextSequenceId;
    }

    public static function update(array $args)
    {
        ValidatorModel::notEmpty($args, ['where']);
        ValidatorModel::arrayType($args, ['set', 'where', 'data']);

        DatabaseModel::update([
            'table'     => 'folders',
            'set'       => empty($args['set']) ? [] : $args['set'],
            'where'     => $args['where'],
            'data'      => empty($args['data']) ? [] : $args['data']
        ]);

        return true;
    }

    public static function delete(array $args)
    {
        ValidatorModel::notEmpty($args, ['where', 'data']);
        ValidatorModel::arrayType($args, ['where', 'data']);

        DatabaseModel::delete([
            'table' => 'folders',
            'where' => $args['where'],
            'data'  => $args['data']
        ]);

        return true;
    }

    public static function getWithEntities(array $args = [])
    {
        ValidatorModel::arrayType($args, ['select', 'where', 'data', 'orderBy']);

        $folders = DatabaseModel::select([
            'select'    => empty($args['select']) ? ['*'] : $args['select'],
            'table'     => ['folders', 'entities_folders'],
            'left_join' => ['folders.id = entities_folders.folder_id'],
            'where'     => empty($args['where']) ? [] : $args['where'],
            'data'      => empty($args['data']) ? [] : $args['data'],
            'order_by'  => empty($args['orderBy']) ? [] : $args['orderBy']
        ]);

        return $folders;
    }

    public static function getWithEntitiesAndResources(array $args = [])
    {
        ValidatorModel::arrayType($args, ['select', 'where', 'data']);

        $folders = DatabaseModel::select([
            'select'    => empty($args['select']) ? ['*'] : $args['select'],
            'table'     => ['folders', 'entities_folders', 'resources_folders'],
            'left_join' => ['folders.id = entities_folders.folder_id', 'folders.id = resources_folders.folder_id'],
            'where'     => empty($args['where']) ? [] : $args['where'],
            'data'      => empty($args['data']) ? [] : $args['data'],
            'groupBy'   => empty($args['groupBy']) ? [] : $args['groupBy'],
            'order_by'  => empty($args['orderBy']) ? [] : $args['orderBy'],
            'limit'     => empty($args['limit']) ? 0 : $args['limit'],
        ]);

        return $folders;
    }

    public static function getWithResources(array $args = [])
    {
        ValidatorModel::arrayType($args, ['select', 'where', 'data']);

        $folders = DatabaseModel::select([
            'select'    => empty($args['select']) ? ['*'] : $args['select'],
            'table'     => ['folders', 'resources_folders'],
            'left_join' => ['folders.id = resources_folders.folder_id'],
            'where'     => empty($args['where']) ? [] : $args['where'],
            'data'      => empty($args['data']) ? [] : $args['data'],
            'groupBy'   => empty($args['groupBy']) ? [] : $args['groupBy']
        ]);

        return $folders;
    }
}
