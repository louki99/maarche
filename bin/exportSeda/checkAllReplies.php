<?php

/**
* Copyright Maarch since 2008 under licence GPLv3.
* See LICENCE.txt file at the root folder for more details.
* This file is part of Maarch software.
*
*/

/**
 * @brief Check all replies in record management system
 * @author dev@maarch.org
 */

/**
* @brief  Class to include the file error
*
*/
class IncludeFileError extends Exception
{
    public function __construct($file)
    {
        $this->file = $file;
        parent :: __construct('Include File \'$file\' is missing!', 1);
    }
}

// Globals variables definition
$GLOBALS['batchName']    = 'checkRepliesFromArchivingSystem';
$GLOBALS['wb']           = '';
$totalProcessedResources = 0;

// Load tools
include('batch_tools.php');

$options = getopt("c:", ["config:"]);
if (empty($options['c']) && empty($options['config'])) {
    print("Configuration file missing\n");
    exit(101);
} elseif (!empty($options['c']) && empty($options['config'])) {
    $options['config'] = $options['c'];
    unset($options['c']);
}

$txt = '';
foreach (array_keys($options) as $key) {
    if (isset($options[$key]) && $options[$key] == false) {
        $txt .= $key . '=false,';
    } else {
        $txt .= $key . '=' . $options[$key] . ',';
    }
}
print($txt . "\n");
$GLOBALS['configFile'] = $options['config'];

print("Load json config file:" . $GLOBALS['configFile'] . "\n");
// Tests existence of config file
if (!file_exists($GLOBALS['configFile'])) {
    print(
        "Configuration file " . $GLOBALS['configFile']
        . " does not exist\n"
    );
    exit(102);
}

$file = file_get_contents($GLOBALS['configFile']);
$file = json_decode($file, true);

if (empty($file)) {
    print("Error on loading config file:" . $GLOBALS['configFile'] . "\n");
    exit(103);
}

// Load config
$config = $file['config'];
$GLOBALS['MaarchDirectory'] = $config['maarchDirectory'];
$GLOBALS['customId']        = $config['customID'];
$GLOBALS['batchDirectory']  = $GLOBALS['MaarchDirectory'] . 'bin/exportSeda';

chdir($GLOBALS['MaarchDirectory']);

set_include_path(get_include_path() . PATH_SEPARATOR . $GLOBALS['MaarchDirectory']);

try {
    Bt_myInclude($GLOBALS['MaarchDirectory'] . 'vendor/autoload.php');
} catch (IncludeFileError $e) {
    Bt_writeLog(['level' => 'ERROR', 'message' => 'Problem with the php include path:' .$e .' '. get_include_path()]);
    exit();
}

\SrcCore\models\DatabasePDO::reset();
new \SrcCore\models\DatabasePDO(['customId' => $GLOBALS['customId']]);

$configuration = \Configuration\models\ConfigurationModel::getByPrivilege(['privilege' => 'admin_export_seda']);
$config = !empty($configuration['value']) ? json_decode($configuration['value'], true) : [];
$GLOBALS['sae']                 = $config['sae'];
$GLOBALS['token']               = $config['token'];
$GLOBALS['userAgent']           = $config['userAgent'];
$GLOBALS['urlSAEService']       = $config['urlSAEService'];
$GLOBALS['certificateSSL']      = $config['certificateSSL'];
$GLOBALS['statusReplyReceived'] = $config['statusReplyReceived'];
$GLOBALS['statusReplyRejected'] = $config['statusReplyRejected'];

$GLOBALS['errorLckFile'] = $GLOBALS['batchDirectory'] . DIRECTORY_SEPARATOR . $GLOBALS['batchName'] .'_error.lck';
$GLOBALS['lckFile']      = $GLOBALS['batchDirectory'] . DIRECTORY_SEPARATOR . $GLOBALS['batchName'] . '.lck';

if (file_exists($GLOBALS['errorLckFile'])) {
    Bt_writeLog(['level' => 'ERROR', 'message' => 'Error persists, please solve this before launching a new batch']);
    exit(13);
}

Bt_getWorkBatch();

Bt_writeLog(['level' => 'INFO', 'message' => 'Retrieve mail sent to archiving system']);

$acknowledgements = \Attachment\models\AttachmentModel::get([
    'select' => ['res_id_master', 'typist'],
    'where'  => ['attachment_type = ?', 'status = ?'],
    'data'   => ['acknowledgement_record_management', 'TRA']
]);
$acknowledgementsTypist = array_column($acknowledgements, 'typist', 'res_id_master');
$acknowledgements       = array_column($acknowledgements, 'res_id_master');

$replies = \Attachment\models\AttachmentModel::get([
    'select' => ['res_id_master'],
    'where'  => ['attachment_type = ?', 'status = ?'],
    'data'   => ['reply_record_management', 'TRA']
]);
$replies = array_column($replies, 'res_id_master');
$pendingResources = array_diff($acknowledgements, $replies);

$unitIdentifiers = [];
$nbMailsRetrieved = 0;
foreach ($pendingResources as $resId) {
    $unitIdentifier = \MessageExchange\models\MessageExchangeModel::getUnitIdentifierByResId(['select' => ['message_id', 'res_id'], 'resId' => (string)$resId]);
    if (empty($unitIdentifier[0]['message_id'])) {
        continue;
    }
    $message = \MessageExchange\models\MessageExchangeModel::getMessageByIdentifier(['select' => ['reference'], 'messageId' => $unitIdentifier[0]['message_id']]);

    if (array_key_exists($message['reference'], $unitIdentifiers)) {
        $unitIdentifiers[$message['reference']] .= "," . $unitIdentifier[0]['res_id'];
    } else {
        $unitIdentifiers[$message['reference']] = $unitIdentifier[0]['res_id'];
    }
}

foreach ($unitIdentifiers as $reference => $value) {
    $messages = Bt_getReply(['reference' => $reference]);
    if (!empty($messages['errors'])) {
        Bt_writeLog(['level' => 'ERROR', 'message' => $messages['errors']]);
        continue;
    } elseif (empty($messages['encodedReply'])) {
        Bt_writeLog(['level' => 'INFO', 'message' => 'Le bordereau avec la référence ' . $reference . ' est toujours en cours de traitement dans le SAE Maarch RM.']);
        continue;
    }

    $resIds = explode(',', $value);

    foreach ($resIds as $resId) {
        $id = Resource\controllers\StoreController::storeAttachment([
            'encodedFile'   => $messages['encodedReply'],
            'type'          => 'reply_record_management',
            'resIdMaster'   => $resId,
            'title'         => 'Réponse au transfert',
            'format'        => 'xml',
            'status'        => 'TRA',
            'typist'        => $acknowledgementsTypist[$resId]
        ]);
        if (empty($id) || !empty($id['errors'])) {
            Bt_writeLog(['level' => 'ERROR', 'message' => '[storeAttachment] ' . $id['errors']]);
            continue;
        }
        \Convert\controllers\ConvertPdfController::convert([
            'resId'  => $id,
            'collId' => 'attachments_coll'
        ]);
        $status = (strpos((string)$messages['xmlContent']->ReplyCode, 'OOO')) === false ? $GLOBALS['statusReplyRejected'] : $GLOBALS['statusReplyReceived'];
        \Resource\models\ResModel::update([
            'set'   => ['status' => $status],
            'where' => ['res_id = ?'],
            'data'  => [$resId]
        ]);
        $nbMailsRetrieved++;
    }
}

Bt_writeLog(['level' => 'INFO', 'message' => 'End of process']);
Bt_writeLog(['level' => 'INFO', 'message' => $nbMailsRetrieved.' document(s) retrieved']);

Bt_logInDataBase($nbMailsRetrieved, 0, $nbMailsRetrieved.' replie(s) retrieved from archiving system');
Bt_updateWorkBatch();

exit(0);
