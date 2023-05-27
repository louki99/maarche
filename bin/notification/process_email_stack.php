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

$GLOBALS['lckFile'] = "{$file['config']['maarchDirectory']}/bin/notification/{$customID}process_email_stack.lck";

$lockFile = @fopen($GLOBALS['lckFile'], 'x');
if (!$lockFile) {
    writeLog(['message' => "An instance of process_email_stack is already in progress", 'level' => 'INFO']);
    exit();
}
fwrite($lockFile, '1');
fclose($lockFile);


setBatchNumber();

$language = \SrcCore\models\CoreConfigModel::getLanguage();

if (file_exists("custom/{$customID}/src/core/lang/lang-{$language}.php")) {
    require_once("custom/{$customID}/src/core/lang/lang-{$language}.php");
}
require_once("src/core/lang/lang-{$language}.php");

\User\controllers\UserController::setAbsences();

//=========================================================================================================================================
//FIRST STEP
$emails = \Notification\models\NotificationsEmailsModel::get(['select' => ['*'], 'where' => ['exec_date is NULL']]);
$totalEmailsToProcess = count($emails);
if ($totalEmailsToProcess === 0) {
    writeLog(['message' => "No notifications to send", 'level' => 'INFO']);
    unlink($GLOBALS['lckFile']);
    exit();
}
$emailsInError = 0;
writeLog(['message' => "{$totalEmailsToProcess} notification(s) to send", 'level' => 'INFO']);


//=========================================================================================================================================
//SECOND STEP
$configuration = \Configuration\models\ConfigurationModel::getByPrivilege(['privilege' => 'admin_email_server', 'select' => ['value']]);
$configuration = json_decode($configuration['value'], true);
foreach ($emails as $key => $email) {
    if (empty($configuration)) {
        writeLog(['message' => "Configuration admin_email_server is missing", 'level' => 'INFO', 'history' => true]);
        unlink($GLOBALS['lckFile']);
        exit();
    }

    $phpmailer = new \PHPMailer\PHPMailer\PHPMailer();
    $phpmailer->setFrom($configuration['from'], $configuration['from']);
    if (in_array($configuration['type'], ['smtp', 'mail'])) {
        if ($configuration['type'] == 'smtp') {
            $phpmailer->isSMTP();
        } elseif ($configuration['type'] == 'mail') {
            $phpmailer->isMail();
        }

        $phpmailer->Host = $configuration['host'];
        $phpmailer->Port = $configuration['port'];
        $phpmailer->SMTPAutoTLS = false;
        if (!empty($configuration['secure'])) {
            $phpmailer->SMTPSecure = $configuration['secure'];
        }
        $phpmailer->SMTPAuth = $configuration['auth'];
        if ($configuration['auth']) {
            $phpmailer->Username = $configuration['user'];
            if (!empty($configuration['password'])) {
                $phpmailer->Password = \SrcCore\models\PasswordModel::decrypt(['cryptedPassword' => $configuration['password']]);
            }
        }
    } elseif ($configuration['type'] == 'sendmail') {
        $phpmailer->isSendmail();
    } elseif ($configuration['type'] == 'qmail') {
        $phpmailer->isQmail();
    }

    $phpmailer->CharSet = $configuration['charset'];

    $phpmailer->addAddress($email['recipient']);

    $phpmailer->isHTML(true);

    $email['html_body'] = str_replace('#and#', '&', $email['html_body']);
    $email['html_body'] = str_replace("\''", "'", $email['html_body']);
    $email['html_body'] = str_replace("\'", "'", $email['html_body']);
    $email['html_body'] = str_replace("''", "'", $email['html_body']);

    $dom = new \DOMDocument();
    $internalErrors = libxml_use_internal_errors(true);
    $dom->loadHTML($email['html_body'], LIBXML_NOWARNING);
    libxml_use_internal_errors($internalErrors);
    $images = $dom->getElementsByTagName('img');

    foreach ($images as $imageKey => $image) {
        $originalSrc = $image->getAttribute('src');
        if (preg_match('/^data:image\/(\w+);base64,/', $originalSrc)) {
            $encodedImage = substr($originalSrc, strpos($originalSrc, ',') + 1);
            $imageFormat = substr($originalSrc, 11, strpos($originalSrc, ';') - 11);
            $phpmailer->addStringEmbeddedImage(base64_decode($encodedImage), "embeded{$key}", "embeded{$key}.{$imageFormat}");
            $email['html_body'] = str_replace($originalSrc, "cid:embeded{$key}", $email['html_body']);
        }
    }

    $phpmailer->Subject = $email['subject'];
    $phpmailer->Body = $email['html_body'];
    if (empty($email['html_body'])) {
        $phpmailer->AllowEmpty = true;
    }

    if (!empty($email['attachments'])) {
        $attachments = explode(',', $email['attachments']);
        foreach ($attachments as $num => $attachment) {
            if (is_file($attachment)) {
                $ext  = strrchr($attachment, '.');
                $name = str_pad(($num + 1), 4, '0', STR_PAD_LEFT) . $ext;
                $phpmailer->addStringAttachment(file_get_contents($attachment), $name);
            }
        }
    }

    $phpmailer->Timeout = 30;
    $phpmailer->SMTPDebug = 1;
    $phpmailer->Debugoutput = function ($str) {
        if (strpos($str, 'SMTP ERROR') !== false) {
            $user = \User\models\UserModel::get(['select' => ['id'], 'orderBy' => ["user_id='superadmin' desc"], 'limit' => 1]);
            \History\controllers\HistoryController::add([
                'tableName'    => 'emails',
                'recordId'     => 'email',
                'eventType'    => 'ERROR',
                'eventId'      => 'sendEmail',
                'userId'       => $user[0]['id'],
                'info'         => $str
            ]);
        }
    };

    $isSent = $phpmailer->send();
    if ($isSent) {
        writeLog(['message' => "Notification sent", 'level' => 'INFO']);
        $result = 'SENT';
    } else {
        writeLog(['message' => "SENDING EMAIL ERROR ! ({$phpmailer->ErrorInfo})", 'level' => 'ERROR', 'history' => true]);

        $emailsInError++;
        $errTxt = ' (Last Error : '.$phpmailer->ErrorInfo.')';
        $result = 'FAILED';
    }

    \Notification\models\NotificationsEmailsModel::update([
        'set'   => ['exec_date' => 'CURRENT_TIMESTAMP', 'exec_result' => $result],
        'where' => ['email_stack_sid = ?'],
        'data'  => [$email['email_stack_sid']]
    ]);
}

$emailsSent = $totalEmailsToProcess - $emailsInError;
if (!empty($emailsInError)) {
    writeLog(['message' => "{$emailsInError}/{$totalEmailsToProcess} notification(s) in error", 'level' => 'ERROR', 'history' => true]);
}
writeLog(['message' => "{$emailsSent} notification(s) sent, end of process", 'level' => 'INFO']);

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
    $parameter = \Parameter\models\ParameterModel::getById(['select' => ['param_value_int'], 'id' => "process_email_stack_id"]);
    if (!empty($parameter)) {
        $GLOBALS['wb'] = $parameter['param_value_int'] + 1;
    } else {
        \Parameter\models\ParameterModel::create(['id' => 'process_email_stack_id', 'param_value_int' => 1]);
        $GLOBALS['wb'] = 1;
    }
}

function updateBatchNumber()
{
    \Parameter\models\ParameterModel::update(['id' => 'process_email_stack_id', 'param_value_int' => $GLOBALS['wb']]);
}

function writeLog(array $args)
{
    \SrcCore\controllers\LogsController::add([
        'isTech'    => true,
        'moduleId'  => 'Notification',
        'level'     => $args['level'] ?? 'INFO',
        'tableName' => '',
        'recordId'  => 'processEmailStack',
        'eventType' => 'Notification',
        'eventId'   => $args['message']
    ]);

    if (!empty($args['history'])) {
        \History\models\BatchHistoryModel::create(['info' => $args['message'], 'module_name' => 'Notification']);
    }
}
