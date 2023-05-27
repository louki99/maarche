<?php
// WARNING: logs for this file are only enabled if config.json log level is set to INFO or DEBUG!

$options = getopt("c:n:", ["config:", "notif:"]);

controlOptions($options);

$txt = '';
foreach (array_keys($options) as $key) {
    if (isset($options[$key]) && $options[$key] == false) {
        $txt .= $key . '=false,';
    } else {
        $txt .= $key . '=' . $options[$key] . ',';
    }
}
printf("{$txt}\n");

$notificationId = $options['notif'];

if (!is_file($options['config'])) {
    printf("Configuration file does not exist\n");
    exit();
}

$file = file_get_contents($options['config']);
$file = json_decode($file, true);

$customID   = $file['config']['customID'] ?? null;
$maarchUrl  = $file['config']['maarchUrl'];

chdir($file['config']['maarchDirectory']);

require 'vendor/autoload.php';


\SrcCore\models\DatabasePDO::reset();
new \SrcCore\models\DatabasePDO(['customId' => $customID]);

setBatchNumber();

$language = \SrcCore\models\CoreConfigModel::getLanguage();

if (file_exists("custom/{$customID}/src/core/lang/lang-{$language}.php")) {
    require_once("custom/{$customID}/src/core/lang/lang-{$language}.php");
}
require_once("src/core/lang/lang-{$language}.php");

\User\controllers\UserController::setAbsences();

//=========================================================================================================================================
//FIRST STEP
writeLog(['message' => "Loading configuration for notification {$notificationId}", 'level' => 'INFO']);
$notification = \Notification\models\NotificationModel::getByNotificationId(['notificationId' => $notificationId, 'select' => ['*']]);
if (empty($notification)) {
    writeLog(['message' => "Notification {$notificationId} does not exist", 'level' => 'ERROR', 'history' => true]);
    exit();
}
if ($notification['is_enabled'] === 'N') {
    writeLog(['message' => "Notification {$notificationId} is disabled", 'level' => 'ERROR', 'history' => true]);
    exit();
}
if (!empty($notification['attachfor_type']) || $notification['attachfor_type'] != null) {
    $attachMode = true;
    writeLog(['message' => "Document will be attached for each recipient", 'level' => 'INFO']);
} else {
    $attachMode = false;
}


//=========================================================================================================================================
//SECOND STEP
$baskets = \Basket\models\BasketModel::get(['select' => ['basket_id', 'basket_clause'], 'where' => ['flag_notif = ?'], 'data' => ['Y']]);

foreach ($baskets as $basket) {
    writeLog(['message' => "Basket {$basket['basket_id']} in progress", 'level' => 'INFO']);

    $groups = \Basket\models\GroupBasketModel::get(['select' => ['group_id'], 'where' => ['basket_id = ?'], 'data' => [$basket['basket_id']]]);
    $nbGroups = count($groups);

    foreach ($groups as $group) {
        $diffusionParams = empty($notification['diffusion_properties']) ? [] : explode(",", $notification['diffusion_properties']);

        if ($notification['diffusion_type'] == 'group' && !in_array($group['group_id'], $diffusionParams)) {
            continue;
        }
        $groupInfo = \Group\models\GroupModel::getByGroupId(['groupId' => $group['group_id'], 'select' => ['id']]);
        $users = \Group\models\GroupModel::getUsersById(['select' => ['users.user_id', 'users.id'], 'id' => $groupInfo['id']]);

        if ($notification['diffusion_type'] == 'entity' && !empty($diffusionParams)) {

            $usersOfEntities = \Entity\models\EntityModel::getWithUserEntities([
                'select'    => ['user_id as id'],
                'where'     => ['entities.entity_id in (?)'],
                'data'      => [$diffusionParams]
            ]);
            $users = array_filter(array_unique($usersOfEntities, SORT_REGULAR), function ($userOfEntities) use ($users) {
                foreach ($users as $user) {
                    if ($user['id'] == $userOfEntities['id']) {
                        return $user;
                    }
                }
                return false;
            });
        }

        if ($notification['diffusion_type'] == 'dest_user') {

            $tmpUsersOfInstance = \Entity\models\ListInstanceModel::get([
                'select'    => ['distinct(item_id)', 'item_type'],
                'where'     => ['difflist_type = ?', 'item_mode = ?'],
                'data'      => ['entity_id', 'dest']
            ]);
            $usersTmp = $users;
            $users = array_filter($usersTmp, function ($userTmp) use ($tmpUsersOfInstance) {
                foreach($tmpUsersOfInstance as $usersOfInstance) {
                    if ($usersOfInstance['item_id'] == $userTmp['id']) {
                        return true;
                    }
                }
                return false;
            });
        }

        if ($notification['diffusion_type'] == 'copy_list') {
            if ($basket['basket_id'] != "CopyMailBasket") {
                continue;
            } else {

                $tmpUsersOrEntitiesOfInstance = \Entity\models\ListInstanceModel::get([
                    'select'    => ['distinct(item_id)', 'item_type'],
                    'where'     => ['difflist_type = ?', 'item_mode = ?'],
                    'data'      => ['entity_id', 'cc']
                ]);
                $usersTmp = $users;
                $users = [];
                foreach ($usersTmp as $userTmp) {
                    foreach ($tmpUsersOrEntitiesOfInstance as $userOrentity) {
                        if ($userOrentity['item_type'] == 'user_id' && $userOrentity['item_id'] == $userTmp['id']) {

                            if(!in_array($userTmp['user_id'], array_column($users, 'user_id'))) {
                                $users[] = ['user_id' => $userTmp['user_id'], 'id' => $userTmp['id']];
                                continue;
                            }
                        } else if ($userOrentity['item_type'] == 'entity_id') {

                            $usersOfEntity = \Entity\models\EntityModel::getWithUserEntities([
                                'select'    => ['user_id as id'],
                                'where'     => ['entities.id = ?'],
                                'data'      => [$userOrentity['item_id']]
                            ]);
                            if(!empty($usersOfEntity)) {
                                $usersFromEntity = \User\models\UserModel::get([
                                    'select'    => ['id', 'user_id'],
                                    'where'     => ['id IN (?)'],
                                    'data'      => [array_column($usersOfEntity, 'id')]
                                ]);
                                foreach ($usersFromEntity as $userFromEntity) {

                                    if(!in_array($userFromEntity['user_id'], array_column($users, 'user_id'))) {
                                        $users[] = ['user_id' => $userFromEntity['user_id'], 'id' => $userFromEntity['id']];
                                        continue;
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        $countUsersToNotify = count($users);
        writeLog(['message' => "Group {$group['group_id']} : {$countUsersToNotify} user(s) to notify", 'level' => 'INFO']);

        foreach ($users as $userToNotify) {

            if ($notification['diffusion_type'] == 'user' && !in_array($userToNotify['id'], $diffusionParams)) {
                continue;
            }

            $realUserId     = null;
            $userId         = $userToNotify['id'];
            $whereClause    = \SrcCore\controllers\PreparedClauseController::getPreparedClause(['clause' => $basket['basket_clause'], 'userId' => $userToNotify['id']]);
            $redirectedBasket = \Basket\models\RedirectBasketModel::get([
                'select' => ['actual_user_id'],
                'where'  => ['owner_user_id = ?', 'basket_id = ?', 'group_id = ?'],
                'data'   => [$userToNotify['id'], $basket['basket_id'], $groupInfo['id']]
            ]);
            if (!empty($redirectedBasket)) {
                $realUserId = $userToNotify['id'];
                $userId     = $redirectedBasket[0]['actual_user_id'];
            }

            $resourcesWhere = in_array($notification['diffusion_type'], ['dest_user', 'copy_list']) && !empty($diffusionParams) ? [$whereClause, 'status IN (?)'] : [$whereClause];
            $resourcesData  = in_array($notification['diffusion_type'], ['dest_user', 'copy_list']) && !empty($diffusionParams) ? [$diffusionParams] : [];
            $resources = \Resource\models\ResModel::getOnView([
                'select' => ['res_id'],
                'where'  => $resourcesWhere,
                'data'   => $resourcesData
            ]);

            if (!empty($resources)) {

                $resourcesNumber = count($resources);
                writeLog(['message' => "{$resourcesNumber} document(s) to process for {$userToNotify['user_id']}", 'level' => 'INFO']);

                $info = "Notification [{$basket['basket_id']}] pour {$userToNotify['user_id']}";
                if (!empty($realUserId)) {
                    $notificationEvents = \Notification\models\NotificationsEventsModel::get(['select' => ['record_id'], 'where' => ['event_info = ?', '(user_id = ? OR user_id = ?)'], 'data' => [$info, $userToNotify['id'], $userId]]);
                } else {
                    $notificationEvents = \Notification\models\NotificationsEventsModel::get(['select' => ['record_id'], 'where' => ['event_info = ?', 'user_id = ?'], 'data' => [$info, $userToNotify['id']]]);
                }

                $aRecordId = array_column($notificationEvents, 'record_id', 'record_id');
                $aValues   = [];
                foreach ($resources as $resource) {
                    if (empty($aRecordId[$resource['res_id']])) {
                        $aValues[] = [
                            'res_letterbox',
                            $notification['notification_sid'],
                            $resource['res_id'],
                            $userId,
                            $info,
                            'CURRENT_TIMESTAMP'
                        ];
                    }
                }
                if (!empty($aValues)) {
                    writeLog(['message' => $info, 'level' => 'DEBUG']);
                    \SrcCore\models\DatabaseModel::insertMultiple([
                        'table'   => 'notif_event_stack',
                        'columns' => ['table_name', 'notification_sid', 'record_id', 'user_id', 'event_info', 'event_date'],
                        'values'  => $aValues
                    ]);
                }
            }
        }
    }
}

writeLog(['message' => "Scanning events for notification {$notification['notification_sid']}", 'level' => 'INFO']);

$events = \Notification\models\NotificationsEventsModel::get(['select' => ['*'], 'where' => ['notification_sid = ?', 'exec_date is NULL'], 'data' => [$notification['notification_sid']]]);
$totalEventsToProcess = count($events);
$currentEvent         = 0;
if ($totalEventsToProcess === 0) {
    writeLog(['message' => "No event to process", 'level' => 'INFO', 'history' => true]);
    exit();
}
writeLog(['message' => "{$totalEventsToProcess} event(s) to scan", 'level' => 'INFO']);
$tmpNotifs = [];


//=========================================================================================================================================
//THIRD STEP
$usersId = array_column($events, 'user_id');
$usersInfo = \User\models\UserModel::get(['select' => ['*'], 'where' => ['id in (?)'], 'data' => [$usersId]]);
$usersInfo = array_column($usersInfo, null, 'id');
foreach ($events as $event) {
    preg_match_all('#\[(\w+)]#', $event['event_info'], $result);
    $basket_id = $result[1];

    if ($event['table_name'] == 'res_letterbox' || $event['table_name'] == 'res_view_letterbox') {
        $res_id = $event['record_id'];
    } else {
        continue;
    }

    $event['res_id'] = $res_id;
    $user_id         = $event['user_id'];

    $userInfo = $usersInfo[$user_id];
    if (!isset($tmpNotifs[$userInfo['user_id']])) {
        $tmpNotifs[$userInfo['user_id']]['recipient'] = $userInfo;
    }

    $tmpNotifs[$userInfo['user_id']]['baskets'][$basket_id[0]]['events'][] = $event;
}
$totalNotificationsToProcess = count($tmpNotifs);
writeLog(['message' => "{$totalNotificationsToProcess} notification(s) to process", 'level' => 'INFO']);


//=========================================================================================================================================
//FOURTH STEP
$i = 1;
foreach ($tmpNotifs as $login => $tmpNotif) {
    foreach ($tmpNotif['baskets'] as $basketId => $basket_list) {
        $baskets = \Basket\models\BasketModel::getByBasketId(['select' => ['basket_name'], 'basketId' => $basketId]);
        $subject = $baskets['basket_name'];

        writeLog(['message' => "Generate e-mail {$i}/{$totalNotificationsToProcess} (TEMPLATE => {$notification['template_id']}, SUBJECT => {$subject}, RECIPIENT => {$login}, DOCUMENT(S) => " . count($basket_list['events']), 'level' => 'INFO']);

        $params = [
            'recipient'    => $tmpNotif['recipient'],
            'events'       => $basket_list['events'],
            'notification' => $notification,
            'maarchUrl'    => $maarchUrl,
            'coll_id'      => 'letterbox_coll',
            'res_table'    => 'res_letterbox',
            'res_view'     => 'res_view_letterbox'
        ];
        $html = \ContentManagement\controllers\MergeController::mergeNotification(['templateId' => $notification['template_id'], 'params' => $params]);

        if (strlen($html) === 0) {
            foreach ($tmpNotif['events'] as $event) {
                \Notification\models\NotificationsEventsModel::update([
                    'set'   => ['exec_date' => 'CURRENT_TIMESTAMP', 'exec_result' => 'FAILED: Error when merging template'],
                    'where' => ['event_stack_sid = ?'],
                    'data'  => [$event['event_stack_sid']]
                ]);
            }
            writeLog(['message' => "Could not merge template with the data", 'level' => 'ERROR', 'history' => true]);
            exit();
        }

        $recipient_mail     = $tmpNotif['recipient']['mail'];
        if (!empty($recipient_mail)) {
            $html = str_replace("&#039;", "'", $html);
            $html = str_replace('&amp;', '&', $html);
            $html = str_replace('&', '#and#', $html);

            $attachments = [];
            if ($attachMode) {
                writeLog(['message' => "Adding attachments", 'level' => 'INFO']);

                foreach ($basket_list['events'] as $event) {
                    if ($event['res_id'] != '') {
                        $resourceToAttach = \Resource\models\ResModel::getById(['resId' => $event['res_id'], 'select' => ['path', 'filename', 'docserver_id']]);
                        if (!empty($resourceToAttach['docserver_id'])) {
                            $docserver        = \Docserver\models\DocserverModel::getByDocserverId(['docserverId' => $resourceToAttach['docserver_id'], 'select' => ['path_template']]);
                            $path             = $docserver['path_template'] . str_replace('#', DIRECTORY_SEPARATOR, $resourceToAttach['path']) . $resourceToAttach['filename'];
                            $path = str_replace('//', '/', $path);
                            $path = str_replace('\\', '/', $path);
                            $attachments[] = $path;
                        }
                    }
                }
                writeLog(['message' => count($attachments). " attachment(s) added", 'level' => 'INFO']);
            }

            $arrayPDO = [
                'recipient' => $recipient_mail,
                'subject'   => $subject,
                'html_body' => $html
            ];
            if (count($attachments) > 0) {
                $arrayPDO['attachments'] = implode(',', $attachments);
            }
            \Notification\models\NotificationsEmailsModel::create($arrayPDO);

            $notificationSuccess = array_column($basket_list['events'], 'event_stack_sid');
            if (!empty($notificationSuccess)) {
                \Notification\models\NotificationsEventsModel::update([
                    'set'   => ['exec_date' => 'CURRENT_TIMESTAMP', 'exec_result' => 'SUCCESS'],
                    'where' => ['event_stack_sid IN (?)'],
                    'data'  => [$notificationSuccess]
                ]);
            }
        }
    }
    ++$i;
}

writeLog(['message' => "End of process : {$totalNotificationsToProcess} notification(s) processed without error", 'level' => 'INFO', 'history' => true]);
updateBatchNumber();


function controlOptions(array &$options)
{
    if (empty($options['c']) && empty($options['config'])) {
        printf("Configuration file missing\n");
        exit();
    } elseif (!empty($options['c']) && empty($options['config'])) {
        $options['config'] = $options['c'];
        unset($options['c']);
    }
    if (empty($options['n']) && empty($options['notif'])) {
        printf("Notification id missing\n");
        exit();
    } elseif (!empty($options['n']) && empty($options['notif'])) {
        $options['notif'] = $options['n'];
        unset($options['n']);
    }
}

function setBatchNumber()
{
    $parameter = \Parameter\models\ParameterModel::getById(['select' => ['param_value_int'], 'id' => 'basket_event_stack_id']);
    if (!empty($parameter)) {
        $GLOBALS['wb'] = $parameter['param_value_int'] + 1;
    } else {
        \Parameter\models\ParameterModel::create(['id' => 'basket_event_stack_id', 'param_value_int' => 1]);
        $GLOBALS['wb'] = 1;
    }
}

function updateBatchNumber()
{
    \Parameter\models\ParameterModel::update(['id' => 'basket_event_stack_id', 'param_value_int' => $GLOBALS['wb']]);
}

function writeLog(array $args)
{
    \SrcCore\controllers\LogsController::add([
        'isTech'    => true,
        'moduleId'  => 'Notification',
        'level'     => $args['level'] ?? 'INFO',
        'tableName' => '',
        'recordId'  => 'basketEventStack',
        'eventType' => 'Notification',
        'eventId'   => $args['message']
    ]);

    if (!empty($args['history'])) {
        \History\models\BatchHistoryModel::create(['info' => $args['message'], 'module_name' => 'Notification']);
    }
}
