<?php

/**
* Copyright Maarch since 2008 under licence GPLv3.
* See LICENCE.txt file at the root folder for more details.
* This file is part of Maarch software.
*
*/

/**
* @brief Notifications Emails Model
* @author dev@maarch.org
*/

namespace Notification\models;

use SrcCore\models\ValidatorModel;
use SrcCore\models\DatabaseModel;

class NotificationsEmailsModel
{
    public static function get(array $args = [])
    {
        ValidatorModel::arrayType($args, ['select', 'where', 'data', 'orderBy']);
        ValidatorModel::intType($args, ['limit']);

        $groups = DatabaseModel::select([
            'select'    => empty($args['select']) ? ['*'] : $args['select'],
            'table'     => ['notif_email_stack'],
            'where'     => $args['where'] ?? [],
            'data'      => $args['data'] ?? [],
            'order_by'  => $args['orderBy'] ?? [],
            'limit'     => $args['limit'] ?? 0
        ]);

        return $groups;
    }

    public static function create(array $aArgs)
    {
        ValidatorModel::notEmpty($aArgs, ['recipient', 'subject', 'html_body']);
        ValidatorModel::stringType($aArgs, ['recipient', 'subject', 'html_body']);

        $aReturn = DatabaseModel::insert([
            'table'         => 'notif_email_stack',
            'columnsValues' => $aArgs
        ]);

        return $aReturn;
    }

    public static function update(array $args)
    {
        ValidatorModel::notEmpty($args, ['where', 'data']);
        ValidatorModel::arrayType($args, ['where', 'data']);

        DatabaseModel::update([
            'table'   => 'notif_email_stack',
            'set'     => !empty($args['set']) ? $args['set'] : [],
            'where'   => $args['where'],
            'data'    => $args['data'],
        ]);

        return true;
    }
}
