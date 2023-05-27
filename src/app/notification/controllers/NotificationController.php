<?php

/**
 * Copyright Maarch since 2008 under licence GPLv3.
 * See LICENCE.txt file at the root folder for more details.
 * This file is part of Maarch software.
 */

/**
 * @brief Notifications Controller
 *
 * @author dev@maarch.org
 * @ingroup notifications
 */

namespace Notification\controllers;

use Group\controllers\PrivilegeController;
use History\controllers\HistoryController;
use Respect\Validation\Validator;
use Notification\models\NotificationModel;
use Notification\models\NotificationScheduleModel;
use Slim\Psr7\Request;
use SrcCore\http\Response;
use SrcCore\models\CoreConfigModel;

class NotificationController
{
    public function get(Request $request, Response $response)
    {
        if (!PrivilegeController::hasPrivilege(['privilegeId' => 'admin_notif', 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }

        return $response->withJson(['notifications' => NotificationModel::get()]);
    }

    public function getBySid(Request $request, Response $response, $aArgs)
    {
        if (!PrivilegeController::hasPrivilege(['privilegeId' => 'admin_notif', 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }

        if (!Validator::intVal()->validate($aArgs['id'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Id is not a numeric']);
        }

        $notification = NotificationModel::getById(['notification_sid' => $aArgs['id']]);
        if (empty($notification)) {
            return $response->withStatus(400)->withJson(['errors' => 'Notification not found']);
        }

        $notification['diffusion_properties'] = explode(',', $notification['diffusion_properties']);

        $notification['attachfor_properties'] = explode(',', $notification['attachfor_properties']);

        foreach ($notification['attachfor_properties'] as $key => $value) {
            $notification['attachfor_properties'][$value] = $value;
            unset($notification['attachfor_properties'][$key]);
        }

        $data = [];

        $data['event']         = NotificationModel::getEvents();
        $data['template']      = NotificationModel::getTemplate();
        $data['diffusionType'] = NotificationModel::getDiffusionType();
        $data['groups']        = NotificationModel::getDiffusionTypeGroups();
        $data['users']         = NotificationModel::getDiffusionTypesUsers();
        $data['entities']      = NotificationModel::getDiffusionTypeEntities();
        $data['status']        = NotificationModel::getDiffusionTypeStatus();

        $notification['event_id'] = (string)$notification['event_id'];
        $notification['data'] = $data;

        $filename = 'notification';
        $customId = CoreConfigModel::getCustomId();
        if ($customId != '') {
            $filename .= '_'.str_replace(' ', '', $customId);
        }
        $filename .= '_'.$notification['notification_id'].'.sh';

        $corePath = str_replace('custom/'.$customId.'/src/app/notification/controllers', '', __DIR__);
        $corePath = str_replace('src/app/notification/controllers', '', $corePath);
        if ($customId != '') {
            $pathToFolow = $corePath.'custom/'.$customId.'/';
        } else {
            $pathToFolow = $corePath;
        }

        $notification['scriptcreated'] = false;

        if (file_exists($pathToFolow.'bin/notification/scripts/'.$filename)) {
            $notification['scriptcreated'] = true;
        }

        return $response->withJson(['notification' => $notification]);
    }

    public function create(Request $request, Response $response)
    {
        if (!PrivilegeController::hasPrivilege(['privilegeId' => 'admin_notif', 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }

        $data = $request->getParsedBody();
        $data['notification_mode'] = 'EMAIL';
        
        $errors = $this->control($data, 'create');
        if (!empty($errors)) {
            return $response->withStatus(500)->withJson(['errors' => $errors]);
        }

        $notificationInDb = NotificationModel::getByNotificationId(['notificationId' => $data['notification_id'], 'select' => ['notification_sid']]);

        if (Validator::notEmpty()->validate($notificationInDb)) {
            return $response->withStatus(400)->withJson(['errors' => _NOTIFICATION_ALREADY_EXIST]);
        }

        

        if ($data['diffusion_properties']) {
            $data['diffusion_properties'] = implode(',', $data['diffusion_properties']);
        } else {
            $data['diffusion_properties'] = '';
        }

        if ($data['attachfor_properties']) {
            $data['attachfor_properties'] = implode(',', $data['attachfor_properties']);
        } else {
            $data['attachfor_properties'] = '';
        }

        if (NotificationModel::create($data)) {
            if (PHP_OS == 'Linux') {
                $notificationAdded = NotificationModel::getByNotificationId(['notificationId' => $data['notification_id'], 'select' => ['notification_sid']]);
                NotificationScheduleModel::createScriptNotification(['notification_sid' => $notificationAdded['notification_sid'], 'event_id' => $data['event_id'], 'notification_id' => $data['notification_id']]);
            }

            HistoryController::add([
                'tableName' => 'notifications',
                'recordId'  => $data['notification_id'],
                'eventType' => 'ADD',
                'eventId'   => 'notificationsadd',
                'info'      => _ADD_NOTIFICATIONS.' : '.$data['notification_id'],
            ]);

            return $response->withJson(NotificationModel::getByNotificationId(['notificationId' => $data['notification_id']]));
        } else {
            return $response->withStatus(400)->withJson(['errors' => 'Notification Create Error']);
        }
    }

    public function update(Request $request, Response $response, array $aArgs)
    {
        if (!PrivilegeController::hasPrivilege(['privilegeId' => 'admin_notif', 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }

        $data = $request->getParsedBody();
        $data['notification_sid'] = $aArgs['id'];
        unset($data['scriptcreated']);

        $errors = $this->control($data, 'update');

        if (!empty($errors)) {
            return $response->withStatus(500)->withJson(['errors' => $errors]);
        }

        $data['diffusion_properties'] = implode(',', $data['diffusion_properties']);
        $data['attachfor_properties'] = implode(',', $data['attachfor_properties']);

        NotificationModel::update($data);

        $notification = NotificationModel::getById(['notification_sid' => $data['notification_sid']]);

        if (PHP_OS == 'Linux') {
            NotificationScheduleModel::createScriptNotification(['notification_sid' => $data['notification_sid'], 'event_id' => $data['event_id'], 'notification_id' => $notification['notification_id']]);
        }

        HistoryController::add([
            'tableName' => 'notifications',
            'recordId'  => $data['notification_sid'],
            'eventType' => 'UP',
            'eventId'   => 'notificationsup',
            'info'      => _MODIFY_NOTIFICATIONS.' : '.$data['notification_sid'],
        ]);

        return $response->withJson(['notification' => $notification]);
    }

    public function delete(Request $request, Response $response, array $aArgs)
    {
        if (!PrivilegeController::hasPrivilege(['privilegeId' => 'admin_notif', 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }

        if (!Validator::intVal()->validate($aArgs['id'])) {
            return $response->withStatus(500)->withJson(['errors' => 'Id is not a numeric']);
        }

        $notification = NotificationModel::getById(['notification_sid' => $aArgs['id']]);

        NotificationModel::delete(['notification_sid' => $aArgs['id']]);

        HistoryController::add([
                'tableName' => 'notifications',
                'recordId'  => $aArgs['id'],
                'eventType' => 'DEL',
                'eventId'   => 'notificationsdel',
                'info'      => _DELETE_NOTIFICATIONS.' : '.$aArgs['id'],
            ]);

        if (PHP_OS == 'Linux') {
            // delete scheduled notification
            $filename = 'notification';

            $customId = CoreConfigModel::getCustomId();
            if ($customId != '') {
                $filename .= '_'.str_replace(' ', '', $customId);
            }
            $filename .= '_'.$notification['notification_id'].'.sh';

            $cronTab = NotificationScheduleModel::getCrontab();

            $flagCron = false;

            $corePath = str_replace('custom/'.$customId.'/src/app/notification/controllers', '', __DIR__);
            $corePath = str_replace('src/app/notification/controllers', '', $corePath);
            if ($customId != '') {
                $pathToFolow = $corePath.'custom/'.$customId.'/';
            } else {
                $pathToFolow = $corePath;
            }

            foreach ($cronTab as $key => $value) {
                if (in_array($value['cmd'], [$pathToFolow.'bin/notification/scripts/'.$filename])) {
                    $cronTab[$key]['state'] = 'deleted';
                    $flagCron = true;
                    break;
                }
            }

            if ($flagCron) {
                NotificationScheduleModel::saveCrontab($cronTab);
            }

            $filePath = $pathToFolow.'bin/notification/scripts/'.$filename;
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        }

        return $response->withJson([
            'notifications' => NotificationModel::get(),
        ]);
    }

    protected function control($aArgs, $mode)
    {
        $errors = [];

        if ($mode == 'update') {
            if (!Validator::intVal()->validate($aArgs['notification_sid'])) {
                $errors[] = 'notification_sid is not a numeric';
            } else {
                $obj = NotificationModel::getById(['notification_sid' => $aArgs['notification_sid']]);
            }

            if (empty($obj)) {
                $errors[] = 'notification does not exists';
            }
        }

        if (!Validator::notEmpty()->validate($aArgs['notification_id'])) {
            $errors[] = 'notification_id is empty';
        }
        if (!Validator::length(1, 254)->notEmpty()->validate($aArgs['description'])) {
            $errors[] = 'wrong format for description';
        }
        if (!Validator::length(0, 254)->validate($aArgs['event_id'])) {
            $errors[] = 'event_id is too long';
        }
        if (!Validator::length(0, 30)->validate($aArgs['notification_mode'])) {
            $errors[] = 'notification_mode is too long';
        }
        if (!Validator::intType()->notEmpty()->validate($aArgs['template_id'])) {
            $errors[] = 'wrong format for template_id';
        }
        if (!Validator::notEmpty()->validate($aArgs['is_enabled']) || ($aArgs['is_enabled'] != 'Y' && $aArgs['is_enabled'] != 'N')) {
            $errors[] = 'Invalid is_enabled value';
        }

        return $errors;
    }

    public function initNotification(Request $request, Response $response)
    {
        if (!PrivilegeController::hasPrivilege(['privilegeId' => 'admin_notif', 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }

        $notification = [];
        $notification['diffusion_properties'] = [];
        $notification['attachfor_properties'] = [];
        $data = [];

        $data['event']         = NotificationModel::getEvents();
        $data['template']      = NotificationModel::getTemplate();
        $data['diffusionType'] = NotificationModel::getDiffusionType();
        $data['groups']        = NotificationModel::getDiffusionTypeGroups();
        $data['users']         = NotificationModel::getDiffusionTypesUsers();
        $data['entities']      = NotificationModel::getDiffusionTypeEntities();
        $data['status']        = NotificationModel::getDiffusionTypeStatus();

        $notification['data'] = $data;

        return $response->withJson(['notification' => $notification]);
    }
}
