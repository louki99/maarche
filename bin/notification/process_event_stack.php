<?php

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


//=========================================================================================================================================
//SECOND STEP
writeLog(['message' => "Loading events for notification {$notificationId}", 'level' => 'INFO']);

$events = \Notification\models\NotificationsEventsModel::get(['select' => ['*'], 'where' => ['notification_sid = ?', 'exec_date is NULL'], 'data' => [$notification['notification_sid']], 'orderBy' => ['event_date desc']]);
$totalEventsToProcess = count($events);
$currentEvent = 0;
if ($totalEventsToProcess === 0) {
    writeLog(['message' => "No event for notification {$notificationId}", 'level' => 'INFO']);
}
$tmpNotifs = [];

writeLog(['message' => "{$totalEventsToProcess} event(s) for notification {$notificationId}", 'level' => 'INFO']);


//=========================================================================================================================================
//THIRD STEP
foreach ($events as $event) {
    writeLog(['message' => "Getting recipients using diffusion type {$notification['diffusion_type']}", 'level' => 'INFO']);
    $recipients = \Notification\controllers\DiffusionTypesController::getItemsToNotify(['request' => 'recipients', 'notification' => $notification, 'event' => $event]);

    $res_id = false;
    if ($event['table_name'] == 'res_letterbox' || $event['table_name'] == 'res_view_letterbox') {
        $res_id = $event['record_id'];
    } else {
        $res_id = \Notification\controllers\DiffusionTypesController::getItemsToNotify(['request' => 'res_id', 'notification' => $notification, 'event' => $event]);
    }
    $event['res_id'] = $res_id;

    if (!empty($notification['attachfor_type']) || $notification['attachfor_type'] != null) {
        $attachMode = true;
        writeLog(['message' => "Document will be attached for each recipient", 'level' => 'INFO']);
    } else {
        $attachMode = false;
    }

    $nbRecipients = count($recipients);
    writeLog(['message' => "{$nbRecipients} recipients found", 'level' => 'INFO']);

    $parameter = \Parameter\models\ParameterModel::getById(['select' => ['param_value_int'], 'id' => 'user_quota']);
    if ($notification['diffusion_type'] === 'dest_entity') {
        foreach ($recipients as $key => $recipient) {
            $entity_id = $recipient['entity_id'];
            writeLog(['message' => "Recipient entity {$entity_id}", 'level' => 'INFO']);

            if (($recipient['enabled'] == 'N' && (empty($parameter) || $parameter['param_value_int'] == 0)) || $recipient['mail'] == '') {
                writeLog(['message' => "{$entity_id} is disabled or mail is invalid, this notification will not be send", 'level' => 'INFO']);
                unset($recipients[$key]);
                continue;
            }

            if (!isset($tmpNotifs[$entity_id])) {
                $tmpNotifs[$entity_id]['recipient'] = $recipient;
            }
            $tmpNotifs[$entity_id]['events'][] = $event;
        }
    } else {
        foreach ($recipients as $key => $recipient) {
            $user_id = $recipient['user_id'];
            writeLog(['message' => "Recipient {$user_id}", 'level' => 'INFO']);

            if (($recipient['status'] == 'SPD' && (empty($parameter) || $parameter['param_value_int'] == 0)) || $recipient['status'] == 'DEL') {
                writeLog(['message' => "{$user_id} is disabled or deleted, this notification will not be send", 'level' => 'INFO']);
                unset($recipients[$key]);
                continue;
            }

            if (!isset($tmpNotifs[$user_id])) {
                $tmpNotifs[$user_id]['recipient'] = $recipient;
            }
            $tmpNotifs[$user_id]['events'][] = $event;
        }
    }

    if (count($recipients) === 0) {
        writeLog(['message' => "No recipient found", 'level' => 'INFO']);
        \Notification\models\NotificationsEventsModel::update([
            'set'   => ['exec_date' => 'CURRENT_TIMESTAMP', 'exec_result' => 'INFO: no recipient found'],
            'where' => ['event_stack_sid = ?'],
            'data'  => [$event['event_stack_sid']]
        ]);
    }
}

$totalNotificationsToProcess = count($tmpNotifs);
writeLog(['message' => "{$totalNotificationsToProcess} notifications to process", 'level' => 'INFO']);


//=========================================================================================================================================
//FOURTH STEP
foreach ($tmpNotifs as $user_id => $tmpNotif) {
    writeLog(['message' => "Merging template {$notification['template_id']} for user {$user_id}", 'level' => 'INFO']);
    $params = [
        'recipient'    => $tmpNotif['recipient'],
        'events'       => $tmpNotif['events'],
        'notification' => $notification,
        'maarchUrl'    => $maarchUrl,
        'coll_id'      => 'letterbox_coll',
        'res_table'    => 'res_letterbox',
        'res_view'     => 'res_view_letterbox'
    ];
    $html = \ContentManagement\controllers\MergeController::mergeNotification(['templateId' => $notification['template_id'], 'params' => $params]);
    if (strlen($html) === 0) {
        $notificationError = array_column($tmpNotif['events'], 'event_stack_sid');
        if (!empty($notificationError)) {
            \Notification\models\NotificationsEventsModel::update([
                'set'   => ['exec_date' => 'CURRENT_TIMESTAMP', 'exec_result' => 'FAILED: Error when merging template'],
                'where' => ['event_stack_sid IN (?)'],
                'data'  => [$notificationError]
            ]);
        }
        writeLog(['message' => "Could not merge template with the data", 'level' => 'ERROR']);
        exit();
    }

    // Prepare e-mail for stack
    $recipient_mail = $tmpNotif['recipient']['mail'];
    $subject        = $notification['description'];

    if (!empty($recipient_mail)) {
        $html = str_replace("&#039;", "'", $html);
        $html = str_replace('&amp;', '&', $html);
        $html = str_replace('&', '#and#', $html);

        $recipient_mail = $tmpNotif['recipient']['mail'];

        // Attachments
        $attachments = [];
        if ($attachMode) {
            foreach ($tmpNotif['events'] as $event) {
                if ($event['res_id'] != '') {
                    $resourceToAttach = \Resource\models\ResModel::getById(['resId' => $event['res_id'], 'select' => ['path', 'filename', 'docserver_id']]);
                    if (!empty($resourceToAttach['docserver_id'])) {
                        $docserver     = \Docserver\models\DocserverModel::getByDocserverId(['docserverId' => $resourceToAttach['docserver_id'], 'select' => ['path_template']]);
                        $path          = $docserver['path_template'] . str_replace('#', DIRECTORY_SEPARATOR, $resourceToAttach['path']) . $resourceToAttach['filename'];
                        $path          = str_replace('//', '/', $path);
                        $path          = str_replace('\\', '/', $path);
                        $attachments[] = $path;
                    }
                }
            }
            $attachmentsCount = count($attachments);
            writeLog(['message' => "{$attachmentsCount} attachment(s) added", 'level' => 'INFO']);
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

        $notificationSuccess = array_column($tmpNotif['events'], 'event_stack_sid');
        if (!empty($notificationSuccess)) {
            \Notification\models\NotificationsEventsModel::update([
                'set'   => ['exec_date' => 'CURRENT_TIMESTAMP', 'exec_result' => 'SUCCESS'],
                'where' => ['event_stack_sid IN (?)'],
                'data'  => [$notificationSuccess]
            ]);
        }
    }
}


writeLog(['message' => "End of process", 'level' => 'INFO', 'history' => true]);
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
    $parameter = \Parameter\models\ParameterModel::getById(['select' => ['param_value_int'], 'id' => "process_event_stack_id"]);
    if (!empty($parameter)) {
        $GLOBALS['wb'] = $parameter['param_value_int'] + 1;
    } else {
        \Parameter\models\ParameterModel::create(['id' => 'process_event_stack_id', 'param_value_int' => 1]);
        $GLOBALS['wb'] = 1;
    }
}

function updateBatchNumber()
{
    \Parameter\models\ParameterModel::update(['id' => 'process_event_stack_id', 'param_value_int' => $GLOBALS['wb']]);
}

function writeLog(array $args)
{
    \SrcCore\controllers\LogsController::add([
        'isTech'    => true,
        'moduleId'  => 'Notification',
        'level'     => $args['level'] ?? 'INFO',
        'tableName' => '',
        'recordId'  => 'processEventStack',
        'eventType' => 'Notification',
        'eventId'   => $args['message']
    ]);

    if (!empty($args['history'])) {
        \History\models\BatchHistoryModel::create(['info' => $args['message'], 'module_name' => 'Notification']);
    }
}
