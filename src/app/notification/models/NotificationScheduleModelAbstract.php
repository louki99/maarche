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
use SrcCore\models\CoreConfigModel;
use History\controllers\HistoryController;

abstract class NotificationScheduleModelAbstract
{
    public static function saveCrontab(array $aArgs = [])
    {
        $aCrontab = NotificationScheduleModel::getCrontab(['setHiddenValue' => false]);

        $file = [];
        foreach ($aArgs['crontab'] as $id => $cronValue) {
            if ($cronValue['state'] == 'hidden') {
                $file[$id] = "{$aCrontab[$id]['m']}\t{$aCrontab[$id]['h']}\t{$aCrontab[$id]['dom']}\t{$aCrontab[$id]['mon']}\t{$aCrontab[$id]['dow']}\t{$aCrontab[$id]['cmd']}";
            } elseif ($cronValue['state'] != 'deleted') {
                $file[$id] = "{$cronValue['m']}\t{$cronValue['h']}\t{$cronValue['dom']}\t{$cronValue['mon']}\t{$cronValue['dow']}\t{$cronValue['cmd']}";
            }
        }

        $output = '';

        if (isset($file)) {
            foreach ($file as $l) {
                $output .= "$l\n";
            }
        }

        $output = preg_replace("!\n+$!", "\n", $output);
        file_put_contents('/tmp/crontab.plain', print_r($file, true));
        file_put_contents('/tmp/crontab.txt', $output);

        exec('crontab /tmp/crontab.txt');

        HistoryController::add([
            'tableName' => 'notifications',
            'recordId'  => $GLOBALS['login'],
            'eventType' => 'UP',
            'eventId'   => 'notificationadd',
            'info'      => _NOTIFICATION_SCHEDULE_UPDATED,
        ]);

        return true;
    }

    public static function getCrontab(array $aArgs = [])
    {
        if (!isset($aArgs['setHiddenValue'])) {
            $aArgs['setHiddenValue'] = true;
        }

        $crontab  = shell_exec('crontab -l');
        $lines    = explode("\n", $crontab);
        $data     = [];
        $customId = CoreConfigModel::getCustomId();
        $corePath = str_replace('custom/'.$customId.'/src/app/notification/models', '', __DIR__);
        $corePath = str_replace('src/app/notification/models', '', $corePath);

        $emptyLine = [
            'm'           => 1,
            'h'           => 1,
            'dom'         => 1,
            'mon'         => 1,
            'dow'         => 1,
            'cmd'         => 'empty',
            'description' => 'empty',
            'state'       => 'hidden',
        ];
        foreach ($lines as $cronLine) {
            $cronLine = preg_replace('![ \t]+!', ' ', trim($cronLine));
            if (empty($cronLine)) {
                continue;
            }
            if ($aArgs['setHiddenValue'] && ($cronLine[0] == '#' || ctype_alpha($cronLine[0]))) {
                $data[] = $emptyLine;
                continue;
            } elseif (!$aArgs['setHiddenValue'] && ($cronLine[0] == '#' || ctype_alpha($cronLine[0]))) {
                $data[] = [ 'm' => $cronLine];
                continue;
            }
            if ($cronLine[0] == '@') {
                $explodeCronLine = explode(' ', $cronLine, 2);
                $cmd = $explodeCronLine[1];
            } else {
                list($m, $h, $dom, $mon, $dow, $cmd) = explode(' ', $cronLine, 6);
            }

            if ($customId != '') {
                $pathToFolow = $corePath.'custom/'.$customId.'/';
            } else {
                $pathToFolow = $corePath;
            }

            $state = 'normal';
            if (strpos($cmd, $pathToFolow.'bin/notification/scripts/') !== 0 && $aArgs['setHiddenValue']) {
                $cmd = 'hidden';
                $state = 'hidden';
            }

            $filename = explode('/', $cmd);

            $data[] = [
                'm'           => $m,
                'h'           => $h,
                'dom'         => $dom,
                'mon'         => $mon,
                'dow'         => $dow,
                'cmd'         => $cmd,
                'description' => end($filename),
                'state'       => $state,
            ];
        }

        return $data;
    }

    public static function createScriptNotification(array $aArgs)
    {
        ValidatorModel::notEmpty($aArgs, ['notification_sid', 'notification_id', 'event_id']);
        ValidatorModel::intVal($aArgs, ['notification_sid']);

        $notification_id = $aArgs['notification_id'];
        $event_id = $aArgs['event_id'];

        //Creer le script sh pour les notifications
        $filename = 'notification';
        $customId = CoreConfigModel::getCustomId();
        if (isset($customId) && $customId != '') {
            $filename .= '_'.str_replace(' ', '', $customId);
        }
        $filename .= '_'.$notification_id.'.sh';

        $corePath = str_replace('custom/'.$customId.'/src/app/notification/models', '', __DIR__);
        $corePath = str_replace('src/app/notification/models', '', $corePath);

        $ConfigNotif = $corePath . CoreConfigModel::getConfigPath();

        if ($customId != '') {
            $pathToFolow = $corePath.'custom/'.$customId.'/';
            if (!file_exists($pathToFolow.'bin/notification/scripts/')) {
                mkdir($pathToFolow.'bin/notification/scripts/', 0777, true);
            }
            $file_open = fopen($pathToFolow.'bin/notification/scripts/'.$filename, 'w+');
        } else {
            $pathToFolow = $corePath;
            $file_open = fopen($pathToFolow.'bin/notification/scripts/'.$filename, 'w+');
        }

        fwrite($file_open, '#!/bin/sh');
        fwrite($file_open, "\n");
        fwrite($file_open, 'path=\''.$corePath.'bin/notification/\'');
        fwrite($file_open, "\n");
        fwrite($file_open, 'cd $path');
        fwrite($file_open, "\n");
        if ($event_id == 'baskets') {
            fwrite($file_open, 'php \'basket_event_stack.php\' -c '.$ConfigNotif.' -n '.$notification_id);
        } elseif ($notification_id == 'RELANCE1' || $notification_id == 'RELANCE2' || $event_id == 'alert1' || $event_id == 'alert2') {
            fwrite($file_open, 'php \'stack_letterbox_alerts.php\' -c '.$ConfigNotif);
            fwrite($file_open, "\n");
            fwrite($file_open, 'php \'process_event_stack.php\' -c '.$ConfigNotif.' -n '.$notification_id);
        } else {
            fwrite($file_open, 'php \'process_event_stack.php\' -c '.$ConfigNotif.' -n '.$notification_id);
        }
        fwrite($file_open, "\n");
        fwrite($file_open, 'cd $path');
        fwrite($file_open, "\n");
        fwrite($file_open, 'php \'process_email_stack.php\' -c '.$ConfigNotif);
        fwrite($file_open, "\n");
        fclose($file_open);
        shell_exec('chmod +x '.escapeshellarg($pathToFolow.'bin/notification/scripts/'.$filename));

        HistoryController::add([
            'tableName' => 'notifications',
            'recordId'  => $notification_id,
            'eventType' => 'ADD',
            'eventId'   => 'notificationadd',
            'info'      => _NOTIFICATION_SCRIPT_ADDED,
        ]);

        return true;
    }
}
