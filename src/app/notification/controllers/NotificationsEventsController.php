<?php

/**
* Copyright Maarch since 2008 under licence GPLv3.
* See LICENCE.txt file at the root folder for more details.
* This file is part of Maarch software.
*
*/

/**
* @brief Notifications Events Controller
* @author dev@maarch.org
*/

namespace Notification\controllers;

use Notification\models\NotificationsEventsModel;
use Notification\models\NotificationModel;

class NotificationsEventsController
{
    public static function fillEventStack(array $aArgs)
    {
        if ($aArgs['recordId'] == '') {
            return;
        }
        
        $aNotifications = NotificationModel::getEnableNotifications();
        if (empty($aNotifications)) {
            return;
        }

        foreach ($aNotifications as $notification) {
            $event_ids = explode(',', $notification['event_id']);

            if ($aArgs['eventId'] == $notification['event_id']
                || NotificationsEventsController::wildcardMatch(["pattern" => $notification['event_id'], "str" => $aArgs['eventId']])
                || in_array($aArgs['eventId'], $event_ids)) {
                NotificationsEventsModel::create([
                    'notification_sid' => $notification['notification_sid'],
                    'table_name'       => $aArgs['tableName'],
                    'record_id'        => $aArgs['recordId'],
                    'user_id'          => $aArgs['userId'],
                    'event_info'       => $aArgs['info']
                ]);
            }
        }
    }

    public static function wildcardMatch(array $aArgs)
    {
        $pattern = '/^' . str_replace(array('%', '\*', '\?', '\[', '\]'), array('.*', '.*', '.', '[', ']+'), preg_quote($aArgs['pattern'])) . '$/is';
        $result = preg_match($pattern, $aArgs['str']);
        return $result;
    }
}
