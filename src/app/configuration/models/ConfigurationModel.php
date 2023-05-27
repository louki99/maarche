<?php

/**
* Copyright Maarch since 2008 under licence GPLv3.
* See LICENCE.txt file at the root folder for more details.
* This file is part of Maarch software.
*
*/

/**
* @brief Configuration Model
* @author dev@maarch.org
*/

namespace Configuration\models;

use SrcCore\models\DatabaseModel;
use SrcCore\models\ValidatorModel;

class ConfigurationModel
{
    public static function getByPrivilege(array $args)
    {
        ValidatorModel::notEmpty($args, ['privilege']);
        ValidatorModel::stringType($args, ['privilege']);
        ValidatorModel::arrayType($args, ['select']);

        $configuration = DatabaseModel::select([
            'select'    => empty($args['select']) ? ['*'] : $args['select'],
            'table'     => ['configurations'],
            'where'     => ['privilege = ?'],
            'data'      => [$args['privilege']],
        ]);

        if (empty($configuration[0])) {
            return [];
        }

        return $configuration[0];
    }

    public static function create(array $args)
    {
        ValidatorModel::notEmpty($args, ['privilege', 'value']);
        ValidatorModel::stringType($args, ['privilege', 'value']);

        DatabaseModel::insert([
            'table'         => 'configurations',
            'columnsValues' => [
                'privilege' => $args['privilege'],
                'value'     => $args['value']
            ]
        ]);

        return true;
    }

    public static function update(array $args)
    {
        ValidatorModel::notEmpty($args, ['where', 'data']);
        ValidatorModel::arrayType($args, ['postSet', 'set', 'where', 'data']);

        DatabaseModel::update([
            'table'   => 'configurations',
            'set'     => empty($args['set']) ? [] : $args['set'],
            'postSet' => empty($args['postSet']) ? [] : $args['postSet'],
            'where'   => $args['where'],
            'data'    => $args['data']
        ]);

        return true;
    }
}
