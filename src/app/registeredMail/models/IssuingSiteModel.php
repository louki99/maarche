<?php

/**
* Copyright Maarch since 2008 under licence GPLv3.
* See LICENCE.txt file at the root folder for more details.
* This file is part of Maarch software.
*/

/**
 * @brief Issuing Site Model
 * @author dev@maarch.org
 */

namespace RegisteredMail\models;

use SrcCore\models\ValidatorModel;
use SrcCore\models\DatabaseModel;

class IssuingSiteModel
{
    public static function get(array $args = [])
    {
        ValidatorModel::arrayType($args, ['select']);

        return DatabaseModel::select([
            'select'   => empty($args['select']) ? ['*'] : $args['select'],
            'table'    => ['registered_mail_issuing_sites'],
            'where'    => empty($args['where']) ? [] : $args['where'],
            'data'     => empty($args['data']) ? [] : $args['data'],
            'order_by' => empty($args['orderBy']) ? [] : $args['orderBy'],
            'limit'    => empty($args['limit']) ? 0 : $args['limit']
        ]);
    }

    public static function getById(array $args)
    {
        ValidatorModel::notEmpty($args, ['id']);
        ValidatorModel::intVal($args, ['id']);
        ValidatorModel::arrayType($args, ['select']);

        $site = DatabaseModel::select([
            'select' => empty($args['select']) ? ['*'] : $args['select'],
            'table'  => ['registered_mail_issuing_sites'],
            'where'  => ['id = ?'],
            'data'   => [$args['id']]
        ]);

        if (empty($site[0])) {
            return [];
        }

        return $site[0];
    }

    public static function create(array $args)
    {
        ValidatorModel::notEmpty($args, ['label']);
        ValidatorModel::stringType($args, ['label']);

        $nextSequenceId = DatabaseModel::getNextSequenceValue(['sequenceId' => 'registered_mail_issuing_sites_id_seq']);

        DatabaseModel::insert([
            'table'         => 'registered_mail_issuing_sites',
            'columnsValues' => [
                'id'                  => $nextSequenceId,
                'label'               => $args['label'],
                'post_office_label'   => $args['postOfficeLabel'] ?? null,
                'account_number'      => $args['accountNumber'] ?? null,
                'address_number'      => $args['addressNumber'] ?? null,
                'address_street'      => $args['addressStreet'] ?? null,
                'address_additional1' => $args['addressAdditional1'] ?? null,
                'address_additional2' => $args['addressAdditional2'] ?? null,
                'address_postcode'    => $args['addressPostcode'] ?? null,
                'address_town'        => $args['addressTown'] ?? null,
                'address_country'     => $args['addressCountry'] ?? null
            ]
        ]);

        return $nextSequenceId;
    }

    public static function update(array $args)
    {
        ValidatorModel::notEmpty($args, ['where']);
        ValidatorModel::arrayType($args, ['set', 'where', 'data']);

        DatabaseModel::update([
            'table' => 'registered_mail_issuing_sites',
            'set'   => empty($args['set']) ? [] : $args['set'],
            'where' => $args['where'],
            'data'  => empty($args['data']) ? [] : $args['data']
        ]);

        return true;
    }

    public static function delete(array $args)
    {
        ValidatorModel::notEmpty($args, ['where', 'data']);
        ValidatorModel::arrayType($args, ['where', 'data']);

        DatabaseModel::delete([
            'table' => 'registered_mail_issuing_sites',
            'where' => $args['where'],
            'data'  => $args['data']
        ]);

        return true;
    }
}
