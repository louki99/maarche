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

$GLOBALS['lckFile'] = "{$file['config']['maarchDirectory']}/bin/notification/{$customID}process_letterbox_alerts.lck";

if (is_file($GLOBALS['lckFile'])) {
    writeLog(['message' => "An instance of process_letterbox_alerts is already in progress", 'level' => 'INFO']);
    exit();
}
$lockFile = fopen($GLOBALS['lckFile'], 'a');
fwrite($lockFile, '1');
fclose($lockFile);

setBatchNumber();

//=========================================================================================================================================
//FIRST STEP
$alertRecordset = \Notification\models\NotificationModel::get(['select' => ['notification_sid', 'event_id'], 'where' => ['event_id in (?)'], 'data' => [['alert1', 'alert2']]]);
if (empty($alertRecordset)) {
    writeLog(['message' => "No alert set", 'level' => 'INFO']);
    unlink($GLOBALS['lckFile']);
    exit();
}
writeLog(['message' => count($alertRecordset) . " notifications set for mail alerts", 'level' => 'INFO']);

$alertNotifs = [];
foreach ($alertRecordset as $value) {
    $alertNotifs[$value['event_id']][] = $value['notification_sid'];
}


//=========================================================================================================================================
//SECOND STEP
$doctypes = \Doctype\models\DoctypeModel::get();
$doctypes = array_column($doctypes, null, 'type_id');
writeLog(['message' => count($doctypes) . " document types set", 'level' => 'INFO']);


//=========================================================================================================================================
//THIRD STEP
$resources = \Resource\models\ResModel::get([
    'select'    => ['res_id', 'type_id', 'process_limit_date', 'flag_alarm1', 'flag_alarm2'],
    'where'     => ['closing_date IS null', 'status NOT IN (?)', '(flag_alarm1 = \'N\' OR flag_alarm2 = \'N\')', 'process_limit_date IS NOT NULL'],
    'data'      => [['CLO', 'DEL', 'END']]
]);
if (empty($resources)) {
    writeLog(['message' => "No Resource to process", 'level' => 'INFO']);
    unlink($GLOBALS['lckFile']);
    exit();
}
$totalDocsToProcess = count($resources);
writeLog(['message' => "{$totalDocsToProcess} resource(s) to process", 'level' => 'INFO']);


//=========================================================================================================================================
//FOURTH STEP
foreach ($resources as $myDoc) {
    $myDoctype = $doctypes[$myDoc['type_id']];

    writeLog(['message' => "Processing resource {$myDoc['res_id']} with doctype {$myDoc['type_id']}", 'level' => 'INFO']);

    $users = \User\models\UserModel::get(['select' => ['id'], 'orderBy' => ["user_id='superadmin' desc"], 'limit' => 1]);
    $user = $users[0];

    if ($myDoc['flag_alarm1'] != 'Y' && $myDoc['flag_alarm2'] != 'Y' && $myDoctype['delay1'] > 0) {
        $processDate = \Resource\controllers\IndexingController::calculateProcessDate(['date' => $myDoc['process_limit_date'], 'delay' => $myDoctype['delay1'], 'sub' => true]);
        if (strtotime($processDate) <= time()) {
            writeLog(['message' => "Alarm 1 is going to be sent", 'level' => 'INFO']);

            $info = 'Relance 1 pour traitement du document No' . $myDoc['res_id'] . ' avant date limite.';
            if (count($alertNotifs['alert1']) > 0) {
                foreach ($alertNotifs['alert1'] as $notification_sid) {
                    \Notification\models\NotificationsEventsModel::create([
                        'notification_sid' => $notification_sid,
                        'table_name'       => 'res_view_letterbox',
                        'record_id'        => $myDoc['res_id'],
                        'user_id'          => $user['id'],
                        'event_info'       => $info
                    ]);
                }
            }
            \Resource\models\ResModel::update(['set' => ['flag_alarm1' => 'Y', 'alarm1_date' => 'CURRENT_TIMESTAMP'], 'where' => ['res_id = ?'], 'data' => [$myDoc['res_id']]]);
        }
    }

    if ($myDoc['flag_alarm2'] != 'Y' && $myDoctype['delay2'] > 0) {
        $processDate = \Resource\controllers\IndexingController::calculateProcessDate(['date' => $myDoc['process_limit_date'], 'delay' => $myDoctype['delay2']]);
        if (strtotime($processDate) <= time()) {
            writeLog(['message' => "Alarm 2 is going to be sent", 'level' => 'INFO']);

            $info = 'Relance 2 pour traitement du document No' . $myDoc['res_id'] . ' apres date limite.';
            if (count($alertNotifs['alert2']) > 0) {
                foreach ($alertNotifs['alert2'] as $notification_sid) {
                    \Notification\models\NotificationsEventsModel::create([
                        'notification_sid' => $notification_sid,
                        'table_name'       => 'res_view_letterbox',
                        'record_id'        => $myDoc['res_id'],
                        'user_id'          => $user['id'],
                        'event_info'       => $info
                    ]);
                }
            }
            \Resource\models\ResModel::update(['set' => ['flag_alarm1' => 'Y', 'flag_alarm2' => 'Y', 'alarm2_date' => 'CURRENT_TIMESTAMP'], 'where' => ['res_id = ?'], 'data' => [$myDoc['res_id']]]);
        }
    }
}


writeLog(['message' => "End of process : {$totalDocsToProcess} process without error", 'level' => 'INFO', 'history' => true]);
unlink($GLOBALS['lckFile']);
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
}

function setBatchNumber()
{
    $parameter = \Parameter\models\ParameterModel::getById(['select' => ['param_value_int'], 'id' => "process_letterbox_alerts_id"]);
    if (!empty($parameter)) {
        $GLOBALS['wb'] = $parameter['param_value_int'] + 1;
    } else {
        \Parameter\models\ParameterModel::create(['id' => 'process_letterbox_alerts_id', 'param_value_int' => 1]);
        $GLOBALS['wb'] = 1;
    }
}

function updateBatchNumber()
{
    \Parameter\models\ParameterModel::update(['id' => 'process_letterbox_alerts_id', 'param_value_int' => $GLOBALS['wb']]);
}

function writeLog(array $args)
{
    \SrcCore\controllers\LogsController::add([
        'isTech'    => true,
        'moduleId'  => 'Notification',
        'level'     => $args['level'] ?? 'INFO',
        'tableName' => '',
        'recordId'  => 'processLetterboxAlerts',
        'eventType' => 'Notification',
        'eventId'   => $args['message']
    ]);

    if (!empty($args['history'])) {
        \History\models\BatchHistoryModel::create(['info' => $args['message'], 'module_name' => 'Notification']);
    }
}
