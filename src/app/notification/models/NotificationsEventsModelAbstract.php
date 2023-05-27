<?php

/**
* Copyright Maarch since 2008 under licence GPLv3.
* See LICENCE.txt file at the root folder for more details.
* This file is part of Maarch software.
*
*/

/**
* @brief Notifications Events Model
* @author dev@maarch.org
*/

namespace Notification\models;

use SrcCore\models\ValidatorModel;
use SrcCore\models\DatabaseModel;

abstract class NotificationsEventsModelAbstract
{
    public static function get(array $args = [])
    {
        ValidatorModel::arrayType($args, ['select', 'where', 'data', 'orderBy']);
        ValidatorModel::intType($args, ['limit']);

        $groups = DatabaseModel::select([
            'select'    => empty($args['select']) ? ['*'] : $args['select'],
            'table'     => ['notif_event_stack'],
            'where'     => $args['where'] ?? [],
            'data'      => $args['data'] ?? [],
            'order_by'  => $args['orderBy'] ?? [],
            'limit'     => $args['limit'] ?? 0
        ]);

        return $groups;
    }

    public static function create(array $aArgs)
    {
        ValidatorModel::notEmpty($aArgs, ['notification_sid', 'table_name', 'record_id', 'user_id', 'event_info']);
        ValidatorModel::stringType($aArgs, ['table_name', 'event_info']);
        ValidatorModel::intval($aArgs, ['notification_sid', 'user_id']);

        $aArgs['event_date'] = 'CURRENT_TIMESTAMP';
        $aArgs['event_info'] = substr($aArgs['event_info'], 0, 255);

        $aReturn = DatabaseModel::insert([
            'table'         => 'notif_event_stack',
            'columnsValues' => $aArgs
        ]);

        return $aReturn;
    }

    public static function getById(array $aArgs)
    {
        ValidatorModel::notEmpty($aArgs, ['notificationSid']);

        $aNotification = DatabaseModel::select([
            'select' => empty($aArgs['select']) ? ['*'] : $aArgs['select'],
            'table'  => ['notif_event_stack'],
            'where'  => ['notification_sid = ?', 'exec_date is NULL'],
            'data'   => [$aArgs['notificationSid']],
        ]);

        if (empty($aNotification[0])) {
            return [];
        }

        return $aNotification[0];
    }

    public static function update(array $args)
    {
        ValidatorModel::notEmpty($args, ['where', 'data']);
        ValidatorModel::arrayType($args, ['where', 'data']);

        DatabaseModel::update([
            'table'   => 'notif_event_stack',
            'set'     => !empty($args['set']) ? $args['set'] : [],
            'postSet' => !empty($args['postSet']) ? $args['postSet'] : [],
            'where'   => $args['where'],
            'data'    => $args['data'],
        ]);

        return true;
    }
}
