<?php

/**
* Copyright Maarch since 2008 under licence GPLv3.
* See LICENCE.txt file at the root folder for more details.
* This file is part of Maarch software.
*
*/

/**
 * @brief Attachment Type Model
 * @author dev@maarch.org
 */

namespace Attachment\models;

use SrcCore\models\DatabaseModel;
use SrcCore\models\ValidatorModel;

class AttachmentTypeModel
{
    public static function get(array $args)
    {
        ValidatorModel::notEmpty($args, ['select']);
        ValidatorModel::arrayType($args, ['select', 'where', 'data', 'orderBy', 'groupBy']);
        ValidatorModel::intType($args, ['limit']);

        $types = DatabaseModel::select([
            'select'    => $args['select'],
            'table'     => ['attachment_types'],
            'where'     => empty($args['where']) ? [] : $args['where'],
            'data'      => empty($args['data']) ? [] : $args['data'],
            'order_by'  => empty($args['orderBy']) ? [] : $args['orderBy'],
            'groupBy'   => empty($args['groupBy']) ? [] : $args['groupBy'],
            'limit'     => empty($args['limit']) ? 0 : $args['limit']
        ]);

        return $types;
    }

    public static function getById(array $args)
    {
        ValidatorModel::notEmpty($args, ['id']);
        ValidatorModel::intVal($args, ['id']);
        ValidatorModel::arrayType($args, ['select']);

        $type = DatabaseModel::select([
            'select'    => empty($args['select']) ? ['*'] : $args['select'],
            'table'     => ['attachment_types'],
            'where'     => ['id = ?'],
            'data'      => [$args['id']],
        ]);

        if (empty($type[0])) {
            return [];
        }

        return $type[0];
    }

    public static function getByTypeId(array $args)
    {
        ValidatorModel::notEmpty($args, ['typeId']);
        ValidatorModel::stringType($args, ['typeId']);
        ValidatorModel::arrayType($args, ['select']);

        $type = DatabaseModel::select([
            'select'    => empty($args['select']) ? ['*'] : $args['select'],
            'table'     => ['attachment_types'],
            'where'     => ['type_id = ?'],
            'data'      => [$args['typeId']],
        ]);

        if (empty($type[0])) {
            return [];
        }

        return $type[0];
    }

    public static function create(array $args)
    {
        ValidatorModel::notEmpty($args, ['type_id', 'label', 'visible', 'email_link', 'signable', 'version_enabled', 'new_version_default', 'chrono', 'signed_by_default']);
        ValidatorModel::stringType($args, ['type_id', 'label', 'visible', 'email_link', 'signable', 'version_enabled', 'new_version_default', 'chrono', 'signed_by_default', 'icon']);

        $nextSequenceId = DatabaseModel::getNextSequenceValue(['sequenceId' => 'attachment_types_id_seq']);

        DatabaseModel::insert([
            'table'         => 'attachment_types',
            'columnsValues' => [
                'id'                    => $nextSequenceId,
                'type_id'               => $args['type_id'],
                'label'                 => $args['label'],
                'visible'               => $args['visible'],
                'email_link'            => $args['email_link'],
                'signable'              => $args['signable'],
                'icon'                  => $args['icon'],
                'chrono'                => $args['chrono'],
                'version_enabled'       => $args['version_enabled'],
                'new_version_default'   => $args['new_version_default'],
                'signed_by_default'     => $args['signed_by_default']
            ]
        ]);

        return $nextSequenceId;
    }

    public static function update(array $args)
    {
        ValidatorModel::notEmpty($args, ['set', 'where', 'data']);
        ValidatorModel::arrayType($args, ['set', 'where', 'data']);

        DatabaseModel::update([
            'table'   => 'attachment_types',
            'set'     => $args['set'],
            'where'   => $args['where'],
            'data'    => $args['data']
        ]);

        return true;
    }

    public static function delete(array $args)
    {
        ValidatorModel::notEmpty($args, ['where', 'data']);
        ValidatorModel::arrayType($args, ['where', 'data']);

        DatabaseModel::delete([
            'table' => 'attachment_types',
            'where'   => $args['where'],
            'data'    => $args['data']
        ]);

        return true;
    }
}
