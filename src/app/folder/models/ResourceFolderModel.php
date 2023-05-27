<?php
/**
* Copyright Maarch since 2008 under licence GPLv3.
* See LICENCE.txt file at the root folder for more details.
* This file is part of Maarch software.

* @brief   ResourceFolderModel
* @author  dev <dev@maarch.org>
* @ingroup core
*/

namespace Folder\models;

use SrcCore\models\ValidatorModel;
use SrcCore\models\DatabaseModel;

class ResourceFolderModel
{
    public static function get(array $aArgs)
    {
        ValidatorModel::arrayType($aArgs, ['select', 'where', 'data', 'orderBy']);
        ValidatorModel::intType($aArgs, ['limit']);

        $resourcesFolders = DatabaseModel::select([
            'select'    => empty($aArgs['select']) ? ['*'] : $aArgs['select'],
            'table'     => ['resources_folders'],
            'where'     => empty($aArgs['where']) ? [] : $aArgs['where'],
            'data'      => empty($aArgs['data']) ? [] : $aArgs['data'],
            'order_by'  => empty($aArgs['orderBy']) ? [] : $aArgs['orderBy'],
            'limit'     => empty($aArgs['limit']) ? 0 : $aArgs['limit']
        ]);

        return $resourcesFolders;
    }

    public static function create(array $args)
    {
        ValidatorModel::notEmpty($args, ['folder_id', 'res_id']);
        ValidatorModel::intVal($args, ['folder_id', 'res_id']);

        DatabaseModel::insert([
            'table'         => 'resources_folders',
            'columnsValues' => [
                'folder_id' => $args['folder_id'],
                'res_id'    => $args['res_id']
            ]
        ]);

        return true;
    }

    public static function delete(array $args)
    {
        ValidatorModel::notEmpty($args, ['where', 'data']);
        ValidatorModel::arrayType($args, ['where', 'data']);

        DatabaseModel::delete([
            'table' => 'resources_folders',
            'where' => $args['where'],
            'data'  => $args['data']
        ]);

        return true;
    }
}
