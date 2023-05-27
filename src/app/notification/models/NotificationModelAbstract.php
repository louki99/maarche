<?php

/**
 * Copyright Maarch since 2008 under licence GPLv3.
 * See LICENCE.txt file at the root folder for more details.
 * This file is part of Maarch software.
 */

/**
 * @brief Notifications Model
 *
 * @author dev@maarch.org
 */

namespace Notification\models;

use SrcCore\models\ValidatorModel;
use Entity\models\EntityModel;
use Group\models\GroupModel;
use SrcCore\models\DatabaseModel;
use Status\models\StatusModel;
use SrcCore\models\CoreConfigModel;

abstract class NotificationModelAbstract
{
    public static function get(array $aArgs = [])
    {
        ValidatorModel::arrayType($aArgs, ['select', 'where', 'orderBy']);
        ValidatorModel::intType($aArgs, ['limit']);

        $aNotifications = DatabaseModel::select([
            'select'    => empty($aArgs['select']) ? ['*'] : $aArgs['select'],
            'table'     => ['notifications'],
            'where'     => empty($aArgs['where']) ? [] : $aArgs['where'],
            'data'      => empty($aArgs['data']) ? [] : $aArgs['data'],
            'order_by'  => empty($aArgs['orderBy']) ? [] : $aArgs['orderBy'],
            'limit'     => empty($aArgs['limit']) ? 0 : $aArgs['limit']
        ]);

        return $aNotifications;
    }

    public static function getById(array $aArgs)
    {
        ValidatorModel::notEmpty($aArgs, ['notification_sid']);

        $aNotification = DatabaseModel::select([
            'select' => empty($aArgs['select']) ? ['*'] : $aArgs['select'],
            'table'  => ['notifications'],
            'where'  => ['notification_sid = ?'],
            'data'   => [$aArgs['notification_sid']],
        ]);

        if (empty($aNotification[0])) {
            return [];
        }

        return $aNotification[0];
    }

    public static function getByNotificationId(array $aArgs)
    {
        ValidatorModel::notEmpty($aArgs, ['notificationId']);

        $aNotification = DatabaseModel::select([
            'select' => empty($aArgs['select']) ? ['*'] : $aArgs['select'],
            'table'  => ['notifications'],
            'where'  => ['notification_id = ?'],
            'data'   => [$aArgs['notificationId']],
        ]);

        if (empty($aNotification[0])) {
            return [];
        }

        return $aNotification[0];
    }

    public static function delete(array $aArgs)
    {
        ValidatorModel::notEmpty($aArgs, ['notification_sid']);
        ValidatorModel::intVal($aArgs, ['notification_sid']);

        DatabaseModel::delete([
            'table' => 'notifications',
            'where' => ['notification_sid = ?'],
            'data'  => [$aArgs['notification_sid']],
        ]);

        return true;
    }

    public static function create(array $aArgs)
    {
        ValidatorModel::notEmpty($aArgs, ['notification_id', 'description', 'is_enabled', 'event_id', 'notification_mode', 'template_id', 'diffusion_type']);
        ValidatorModel::intVal($aArgs, ['template_id']);
        ValidatorModel::stringType($aArgs, ['notification_id', 'description', 'is_enabled', 'notification_mode']);

        DatabaseModel::insert([
            'table' => 'notifications',
            'columnsValues' => [
                'notification_id'      => $aArgs['notification_id'],
                'description'          => $aArgs['description'],
                'is_enabled'           => $aArgs['is_enabled'],
                'event_id'             => $aArgs['event_id'],
                'notification_mode'    => $aArgs['notification_mode'],
                'template_id'          => $aArgs['template_id'],
                'diffusion_type'       => $aArgs['diffusion_type'],
                'diffusion_properties' => $aArgs['diffusion_properties'],
                'attachfor_type'       => $aArgs['attachfor_type'] ?? null,
                'attachfor_properties' => $aArgs['attachfor_properties'],
            ],
        ]);

        return true;
    }

    public static function update(array $aArgs)
    {
        ValidatorModel::notEmpty($aArgs, ['notification_sid']);
        ValidatorModel::intVal($aArgs, ['notification_sid']);

        $notificationSid = $aArgs['notification_sid'];
        unset($aArgs['data']);
        unset($aArgs['notification_sid']);

        $aReturn = DatabaseModel::update([
            'table' => 'notifications',
            'set'   => $aArgs,
            'where' => ['notification_sid = ?'],
            'data'  => [$notificationSid],
        ]);

        return $aReturn;
    }

    public static function getEvents()
    {
        $events = DatabaseModel::select([
            'select' => ['id, label_action'],
            'table'  => ['actions'],
        ]);
        foreach ($events as $key => $event) {
            $events[$key]['id'] = (string)$event['id'];
        }

        $loadedXml = CoreConfigModel::getXmlLoaded(['path' => 'config/event_type.xml']);
        if ($loadedXml) {
            foreach ($loadedXml->event_type as $eventType) {
                $events[] = [
                    'id'           => (string)$eventType->id,
                    'label_action' => (string)$eventType->label
                ];
            }
        }

        return $events;
    }

    public static function getTemplate()
    {
        $tabTemplate = DatabaseModel::select([
            'select' => ['template_id, template_label'],
            'table'  => ['templates'],
            'where'  => ['template_target = ?'],
            'data'   => ['notifications'],
        ]);

        return $tabTemplate;
    }

    public static function getDiffusionType()
    {
        $diffusionTypes = [];

        $diffusionTypes[] = array(
            'id'             => 'group',
            'label'          => 'Groupe',
            'add_attachment' => 'true',
            'function'       => 'getRecipientsByGroup'
        );
        $diffusionTypes[] = array(
            'id'             => 'entity',
            'label'          => 'Entité',
            'add_attachment' => 'true',
            'function'       => 'getRecipientsByEntity'
        );
        $diffusionTypes[] = array(
            'id'             => 'dest_entity',
            'label'          => 'Service de l\'utilisateur destinataire',
            'add_attachment' => 'false',
            'function'       => 'getRecipientsByDestEntity'
        );
        $diffusionTypes[] = array(
            'id'             => 'dest_user',
            'label'          => 'Liste de diffusion du document',
            'add_attachment' => 'false',
            'function'       => 'getRecipientsByDestUser'
        );
        $diffusionTypes[] = array(
            'id'             => 'dest_user_visa',
            'label'          => 'Viseur actuel du document',
            'add_attachment' => 'true',
            'function'       => 'getRecipientsByDestUserVisa'
        );
        $diffusionTypes[] = array(
            'id'             => 'dest_user_sign',
            'label'          => 'Signataire actuel du document',
            'add_attachment' => 'true',
            'function'       => 'getRecipientsByDestUserSign'
        );
        $diffusionTypes[] = array(
            'id'             => 'user',
            'label'          => 'Utilisateur désigné',
            'add_attachment' => 'true',
            'function'       => 'getRecipientsByUser'
        );

        $diffusionTypes[] = array(
            'id'             => 'copy_list',
            'label'          => 'Liste de diffusion du document',
            'add_attachment' => 'false',
            'function'       => 'getRecipientsByCopie'
        );

        $diffusionTypes[] = array(
            'id'             => 'contact',
            'label'          => 'Contact du document',
            'add_attachment' => 'true',
            'function'       => 'getRecipientsByContact'
        );

        return $diffusionTypes;
    }

    public static function getDiffusionTypeGroups()
    {
        $groups = GroupModel::get(['orderBy' => ['group_desc']]);

        return $groups;
    }

    public static function getDiffusionTypesUsers()
    {
        $users = DatabaseModel::select([
            'select' => ["id, concat(firstname,' ',lastname) as label"],
            'table'  => ['users'],
        ]);

        foreach ($users as $key => $user) {
            $users[$key]['id'] = (string)$user['id'];
        }

        return $users;
    }

    public static function getDiffusionTypeEntities()
    {
        $entities = EntityModel::get();

        return $entities;
    }

    public static function getDiffusionTypeStatus()
    {
        $status = StatusModel::get();

        return $status;
    }

    public static function getEnableNotifications(array $aArgs = [])
    {
        ValidatorModel::arrayType($aArgs, ['select']);

        $aReturn = DatabaseModel::select([
            'select' => empty($aArgs['select']) ? ['*'] : $aArgs['select'],
            'table'  => ['notifications'],
            'where'  => ['is_enabled = ?'],
            'data'   => ['Y'],
        ]);

        return $aReturn;
    }
}
