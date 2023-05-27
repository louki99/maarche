<?php

/**
 * Copyright Maarch since 2008 under licence GPLv3.
 * See LICENCE.txt file at the root folder for more details.
 * This file is part of Maarch software.
 *
 */

/**
 * @brief User Signature Model Abstract
 * @author dev@maarch.org
 */

namespace User\models;

use SrcCore\models\DatabaseModel;
use SrcCore\models\ValidatorModel;

abstract class UserSignatureModelAbstract
{
    public static function get(array $aArgs)
    {
        ValidatorModel::notEmpty($aArgs, ['select', 'where', 'data']);
        ValidatorModel::arrayType($aArgs, ['select', 'where', 'data']);

        $signatures = DatabaseModel::select([
            'select'    => empty($aArgs['select']) ? ['*'] : $aArgs['select'],
            'table'     => ['user_signatures'],
            'where'     => $aArgs['where'],
            'data'      => $aArgs['data']
        ]);

        return $signatures;
    }

    public static function getById(array $args)
    {
        ValidatorModel::notEmpty($args, ['id']);
        ValidatorModel::intVal($args, ['id']);
        ValidatorModel::arrayType($args, ['select']);

        $signature = DatabaseModel::select([
            'select'    => $args['select'] ?? ['*'],
            'table'     => ['user_signatures'],
            'where'     => ['id = ?'],
            'data'      => [$args['id']],
        ]);

        if (empty($signature[0])) {
            return [];
        }

        return $signature[0];
    }

    public static function getByUserSerialId(array $aArgs)
    {
        ValidatorModel::notEmpty($aArgs, ['userSerialid']);
        ValidatorModel::intVal($aArgs, ['userSerialid']);

        $signatures = DatabaseModel::select([
            'select'    => ['id', 'user_serial_id', 'signature_label'],
            'table'     => ['user_signatures'],
            'where'     => ['user_serial_id = ?'],
            'data'      => [$aArgs['userSerialid']],
            'order_by'  => ['id']
        ]);

        return $signatures;
    }

    public static function create(array $aArgs)
    {
        ValidatorModel::notEmpty($aArgs, ['userSerialId', 'signatureLabel', 'signaturePath', 'signatureFileName']);
        ValidatorModel::stringType($aArgs, ['signatureLabel', 'signaturePath', 'signatureFileName']);
        ValidatorModel::intVal($aArgs, ['userSerialId']);

        DatabaseModel::insert([
            'table'         => 'user_signatures',
            'columnsValues' => [
                'user_serial_id'        => $aArgs['userSerialId'],
                'signature_label'       => $aArgs['signatureLabel'],
                'signature_path'        => $aArgs['signaturePath'],
                'signature_file_name'   => $aArgs['signatureFileName']
            ]
        ]);

        return true;
    }

    public static function update(array $aArgs)
    {
        ValidatorModel::notEmpty($aArgs, ['signatureId', 'userSerialId', 'label']);
        ValidatorModel::stringType($aArgs, ['label']);
        ValidatorModel::intVal($aArgs, ['signatureId', 'userSerialId']);

        DatabaseModel::update([
            'table'     => 'user_signatures',
            'set'       => [
                'signature_label'   => $aArgs['label']
            ],
            'where'     => ['user_serial_id = ?', 'id = ?'],
            'data'      => [$aArgs['userSerialId'], $aArgs['signatureId']]
        ]);

        return true;
    }

    public static function delete(array $aArgs)
    {
        ValidatorModel::notEmpty($aArgs, ['signatureId', 'userSerialId']);
        ValidatorModel::intVal($aArgs, ['signatureId', 'userSerialId']);

        DatabaseModel::delete([
            'table'     => 'user_signatures',
            'where'     => ['user_serial_id = ?', 'id = ?'],
            'data'      => [$aArgs['userSerialId'], $aArgs['signatureId']],
        ]);

        return true;
    }
}
