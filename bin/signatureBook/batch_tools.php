<?php

/**
* Copyright Maarch since 2008 under licence GPLv3.
* See LICENCE.txt file at the root folder for more details.
* This file is part of Maarch software.
*
*/

/**
 * @brief API to manage batchs
 *
 * @file
 * @author <dev@maarch.org>
 * @date $date$
 * @version $Revision$
 */

/**
 * Exit the batch with a return code, message in the log and
 * in the database if necessary
 *
 * @param int $returnCode code to exit (if > O error)
 * @param string $message message to the log and the DB
 * @return nothing exit the program
 */
function Bt_exitBatch($returnCode, $message='')
{
    if (file_exists($GLOBALS['lckFile'])) {
        unlink($GLOBALS['lckFile']);
    }
    if ($returnCode > 0) {
        $GLOBALS['totalProcessedResources']--;
        if ($GLOBALS['totalProcessedResources'] == -1) {
            $GLOBALS['totalProcessedResources'] = 0;
        }
        if ($returnCode < 100) {
            if (file_exists($GLOBALS['errorLckFile'])) {
                unlink($GLOBALS['errorLckFile']);
            }
            $semaphore = fopen($GLOBALS['errorLckFile'], "a");
            fwrite($semaphore, '1');
            fclose($semaphore);
        }
        Bt_writeLog(['level' => 'ERROR', 'message' => $message]);
        Bt_logInDataBase($GLOBALS['totalProcessedResources'], 1, $message.' (return code: '. $returnCode.')');
    } elseif ($message <> '') {
        Bt_writeLog(['level' => 'INFO', 'message' => $message]);
        Bt_logInDataBase($GLOBALS['totalProcessedResources'], 0, $message.' (return code: '. $returnCode.')');
    }
    Bt_updateWorkBatch();
    exit($returnCode);
}

/**
* Insert in the database the report of the batch
* @param long $totalProcessed total of resources processed in the batch
* @param long $totalErrors total of errors in the batch
* @param string $info message in db
*/
function Bt_logInDataBase($totalProcessed=0, $totalErrors=0, $info='')
{
    \History\models\BatchHistoryModel::create([
        'module_name'     => $GLOBALS['batchName'],
        'batch_id'        => $GLOBALS['wb'],
        'info'            => substr(str_replace('\\', '\\\\', str_replace("'", "`", $info)), 0, 999),
        'total_processed' => $totalProcessed,
        'total_errors'    => $totalErrors
    ]);
}


/**
* Insert in the database a line for history
*/
function Bt_history($aArgs = [])
{
    $user = \User\models\UserModel::get(['select' => ['id'], 'orderBy' => ["user_id='superadmin' desc"], 'limit' => 1]);
    \History\controllers\HistoryController::add([
        'tableName'    => $aArgs['table_name'],
        'recordId'     => $aArgs['record_id'],
        'eventType'    => $aArgs['event_type'],
        'eventId'      => $aArgs['event_id'],
        'userId'       => $user[0]['id'],
        'info'         => $aArgs['info']
    ]);
}

/**
 * Get the batch if of the batch
 *
 * @return nothing
 */
function Bt_getWorkBatch()
{
    $parameter = \Parameter\models\ParameterModel::getById(['select' => ['param_value_int'], 'id' => $GLOBALS['batchName']."_id"]);
    if (!empty($parameter)) {
        $GLOBALS['wb'] = $parameter['param_value_int'] + 1;
    } else {
        \Parameter\models\ParameterModel::create(['id' => $GLOBALS['batchName']."_id", 'param_value_int' => 1]);
        $GLOBALS['wb'] = 1;
    }
}

/**
 * Update the database with the new batch id of the batch
 *
 * @return nothing
 */
function Bt_updateWorkBatch()
{
    \Parameter\models\ParameterModel::update(['id' => $GLOBALS['batchName']."_id", 'param_value_int' => $GLOBALS['wb']]);
}

/**
 * Include the file requested if exists
 *
 * @param string $file path of the file to include
 * @return nothing
 */
function Bt_myInclude($file)
{
    if (file_exists($file)) {
        include_once($file);
    } else {
        throw new IncludeFileError($file);
    }
}

function Bt_writeLog($args = [])
{
    \SrcCore\controllers\LogsController::add([
        'isTech'    => true,
        'moduleId'  => $GLOBALS['moduleId'],
        'level'     => $args['level'],
        'tableName' => $GLOBALS['batchName'],
        'eventType' => 'script',
        'eventId'   => $args['message']
    ]);
}

function Bt_createNote($aArgs = [])
{
    if (!empty($aArgs['notes'])) {
        foreach ($aArgs['notes'] as $note) {
            if (!empty(trim($note['content']))) {
                $creatorName = '';
                if (!empty($note['creatorId'])) {
                    $creatorId = $note['creatorId'];
                } else {
                    $users = \User\models\UserModel::get(['select' => ['id'], 'orderBy' => ["user_id='superadmin' desc"], 'limit' => 1]);
                    $creatorId = $users[0]['id'];
                }
                if (!empty($note['creatorName'])) {
                    $creatorName = $note['creatorName'] . ' (Maarch Parapheur) : ';
                }
                \Note\models\NoteModel::create([
                    'resId'     => $aArgs['resId'],
                    'user_id'   => $creatorId,
                    'note_text' => $creatorName . $note['content'],
                ]);
            }
        }
    }
}

function Bt_createAttachment($args = [])
{
    $opts = [
        CURLOPT_URL => rtrim($GLOBALS['applicationUrl'], "/") . '/rest/attachments',
        CURLOPT_HTTPHEADER => [
            'accept:application/json',
            'content-type:application/json',
            'Authorization: Basic ' . base64_encode($GLOBALS['userWS']. ':' .$GLOBALS['passwordWS']),
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_POSTFIELDS => json_encode($args),
        CURLOPT_POST => true
    ];

    $curl = curl_init();
    curl_setopt_array($curl, $opts);
    $rawResponse = curl_exec($curl);
    $error       = curl_error($curl);
    
    $code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    if ($code == 404) {
        Bt_writeLog(['level' => 'ERROR', 'message' => 'Create attachment failed : maarchUrl is not correct']);
        return false;
    }

    if (!empty($error)) {
        Bt_writeLog(['level' => 'ERROR', 'message' => "Create attachment failed : {$error}"]);
        return false;
    }

    $return = json_decode($rawResponse, true);
    if (!empty($return['errors'])) {
        Bt_writeLog(['level' => 'ERROR', 'message' => "Create attachment failed : {$return['errors']}"]);
        return false;
    }

    return $return;
}

function Bt_validatedMail($aArgs = [])
{
    $attachments = \Attachment\models\AttachmentModel::get(['select' => ['count(1)'], 'where' => ['res_id_master = ?', 'status = ?'], 'data' => [$aArgs['resId'], 'FRZ']]);
    if ($attachments[0]['count'] == 0) {
        \Resource\models\ResModel::update([
            'set'     => ['status' => $aArgs['status']],
            'where'   => ['res_id = ?'],
            'data'    => [$aArgs['resId']]
        ]);
    }
}
