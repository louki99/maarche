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
        'moduleId'  => $GLOBALS['batchName'],
        'level'     => $args['level'],
        'tableName' => '',
        'recordId'  => $GLOBALS['batchName'],
        'eventType' => $GLOBALS['batchName'],
        'eventId'   => $args['message']
    ]);
}

function Bt_getReply($args = [])
{
    $refEncode = str_replace('.', '%2E', urlencode($args['reference']));
    $curlResponse = \SrcCore\models\CurlModel::exec([
        'url'     => rtrim($GLOBALS['urlSAEService'], '/') . '/medona/message/reference?reference=' . $refEncode,
        'method'  => 'GET',
        'cookie'  => 'LAABS-AUTH=' . urlencode($GLOBALS['token']),
        'headers' => [
            'Accept: application/json',
            'Content-Type: application/json',
            'User-Agent: ' . $GLOBALS['userAgent']
        ]
    ]);

    if (!empty($curlResponse['errors'])) {
        return ['errors' => 'Error returned by the route /organization/organization/Search : ' . $curlResponse['errors']];
    } elseif ($curlResponse['code'] != 200) {
        return ['errors' => 'Error returned by the route /organization/organization/Search : ' . $curlResponse['response']['message']];
    }

    if (!in_array($curlResponse['response']['status'], ['rejected', 'processed'])) {
        return [];
    }

    $messageId = $curlResponse['response']['replyMessage']['messageId'];

    $curlResponse = \SrcCore\models\CurlModel::exec([
        'url'     => rtrim($GLOBALS['urlSAEService'], '/') . '/medona/message/'.urlencode($messageId).'/Export',
        'method'  => 'GET',
        'cookie'  => 'LAABS-AUTH=' . urlencode($GLOBALS['token']),
        'headers' => [
            'Accept: application/zip',
            'Content-Type: application/json',
            'User-Agent: ' . $GLOBALS['userAgent']
        ]
    ]);

    if (!empty($curlResponse['errors'])) {
        return ['errors' => 'Error returned by the route /medona/message/{messageId}/Export : ' . $curlResponse['errors']];
    } elseif ($curlResponse['code'] != 200) {
        return ['errors' => 'Error returned by the route /medona/message/{messageId}/Export : ' . $curlResponse['response']['message']];
    }

    $reply = \ExportSeda\controllers\ExportSEDATrait::getXmlFromZipMessage([
        'encodedZipDocument' => base64_encode($curlResponse['response']),
        'messageId'          => $messageId
    ]);
    if (!empty($reply['errors'])) {
        return ['errors' => 'Error during getXmlFromZipMessage process : ' . $reply['errors']];
    }

    return ['encodedReply' => $reply['encodedDocument'], 'xmlContent' => $reply['xmlContent']];
}

function Bt_purgeAll($args = [])
{
    if (!empty($args['resources'])) {
        $resources = \SrcCore\models\DatabaseModel::select([
            'select'    => ['d.path_template', 'r.path', 'r.filename'],
            'table'     => ['res_letterbox r', 'docservers d'],
            'left_join' => ['r.docserver_id = d.docserver_id'],
            'where'     => ['res_id in (?)', 'filename is not null'],
            'data'      => [$args['resources']]
        ]);
        foreach ($resources as $resource) {
            $pathToDocument = $resource['path_template'] . str_replace('#', DIRECTORY_SEPARATOR, $resource['path']) . $resource['filename'];
            if (is_file($pathToDocument)) {
                unlink($pathToDocument);
            }
        }
    
        $resources = \SrcCore\models\DatabaseModel::select([
            'select'    => ['d.path_template', 'r.path', 'r.filename', 'r.res_id'],
            'table'     => ['res_attachments r', 'docservers d'],
            'left_join' => ['r.docserver_id = d.docserver_id'],
            'where'     => ['res_id_master in (?)', 'filename is not null'],
            'data'      => [$args['resources']]
        ]);
        $attachmentIds = array_column($resources, 'res_id');
        foreach ($resources as $resource) {
            $pathToDocument = $resource['path_template'] . str_replace('#', DIRECTORY_SEPARATOR, $resource['path']) . $resource['filename'];
            if (is_file($pathToDocument)) {
                unlink($pathToDocument);
            }
        }
    
        $resources = \SrcCore\models\DatabaseModel::select([
            'select'    => ['d.path_template', 'adr.path', 'adr.filename'],
            'table'     => ['adr_letterbox adr', 'docservers d'],
            'left_join' => ['adr.docserver_id = d.docserver_id'],
            'where'     => ['res_id in (?)'],
            'data'      => [$args['resources']]
        ]);
        foreach ($resources as $resource) {
            $pathToDocument = $resource['path_template'] . str_replace('#', DIRECTORY_SEPARATOR, $resource['path']) . $resource['filename'];
            if (is_file($pathToDocument)) {
                unlink($pathToDocument);
            }
        }
    
        if (!empty($attachmentIds)) {
            $resources = \SrcCore\models\DatabaseModel::select([
                'select'    => ['d.path_template', 'adr.path', 'adr.filename'],
                'table'     => ['adr_attachments adr', 'docservers d'],
                'left_join' => ['adr.docserver_id = d.docserver_id'],
                'where'     => ['res_id in (?)'],
                'data'      => [$attachmentIds]
            ]);
            foreach ($resources as $resource) {
                $pathToDocument = $resource['path_template'] . str_replace('#', DIRECTORY_SEPARATOR, $resource['path']) . $resource['filename'];
                if (is_file($pathToDocument)) {
                    unlink($pathToDocument);
                }
            }
        }
    
        \SrcCore\models\DatabaseModel::delete([
            'table' => 'adr_letterbox',
            'where' => ['res_id in (?)'],
            'data'  => [$args['resources']]
        ]);
        \SrcCore\models\DatabaseModel::delete([
            'table' => 'acknowledgement_receipts',
            'where' => ['res_id in (?)'],
            'data'  => [$args['resources']]
        ]);
        \SrcCore\models\DatabaseModel::delete([
            'table' => 'listinstance',
            'where' => ['res_id in (?)'],
            'data'  => [$args['resources']]
        ]);
        \SrcCore\models\DatabaseModel::delete([
            'table' => 'listinstance_history',
            'where' => ['res_id in (?)'],
            'data'  => [$args['resources']]
        ]);
        \SrcCore\models\DatabaseModel::delete([
            'table' => 'listinstance_history_details',
            'where' => ['res_id in (?)'],
            'data'  => [$args['resources']]
        ]);
        \SrcCore\models\DatabaseModel::delete([
            'table' => 'registered_mail_resources',
            'where' => ['res_id in (?)'],
            'data'  => [$args['resources']]
        ]);
        \SrcCore\models\DatabaseModel::delete([
            'table' => 'res_letterbox',
            'where' => ['res_id in (?)'],
            'data'  => [$args['resources']]
        ]);
        \SrcCore\models\DatabaseModel::delete([
            'table' => 'res_mark_as_read',
            'where' => ['res_id in (?)'],
            'data'  => [$args['resources']]
        ]);
        \SrcCore\models\DatabaseModel::delete([
            'table' => 'resource_contacts',
            'where' => ['res_id in (?)'],
            'data'  => [$args['resources']]
        ]);
        \SrcCore\models\DatabaseModel::delete([
            'table' => 'resources_folders',
            'where' => ['res_id in (?)'],
            'data'  => [$args['resources']]
        ]);
        \SrcCore\models\DatabaseModel::delete([
            'table' => 'resources_tags',
            'where' => ['res_id in (?)'],
            'data'  => [$args['resources']]
        ]);
        \SrcCore\models\DatabaseModel::delete([
            'table' => 'unit_identifier',
            'where' => ['res_id in (?)'],
            'data'  => [$args['resources']]
        ]);
        \SrcCore\models\DatabaseModel::delete([
            'table' => 'users_followed_resources',
            'where' => ['res_id in (?)'],
            'data'  => [$args['resources']]
        ]);
        \SrcCore\models\DatabaseModel::delete([
            'table' => 'message_exchange',
            'where' => ['res_id_master in (?)'],
            'data'  => [$args['resources']]
        ]);
        \SrcCore\models\DatabaseModel::delete([
            'table' => 'res_attachments',
            'where' => ['res_id_master in (?)'],
            'data'  => [$args['resources']]
        ]);
        \SrcCore\models\DatabaseModel::delete([
            'table' => 'shippings',
            'where' => ['document_id in (?)', 'document_type = ?'],
            'data'  => [$args['resources'], 'resource']
        ]);
        \SrcCore\models\DatabaseModel::delete([
            'table' => 'shippings',
            'where' => ['document_id in (select res_id from res_attachments where res_id_master in (?))', 'document_type = ?'],
            'data'  => [$args['resources'], 'attachment']
        ]);
        \SrcCore\models\DatabaseModel::delete([
            'table' => 'notes',
            'where' => ['identifier in (?)'],
            'data'  => [$args['resources']]
        ]);
        \SrcCore\models\DatabaseModel::delete([
            'table' => 'note_entities',
            'where' => ['note_id in (select id from notes where identifier in (?))'],
            'data'  => [$args['resources']]
        ]);

        $linkedResources = implode("' - '", $args['resources']);
        \SrcCore\models\DatabaseModel::update([
            'table'     => 'res_letterbox',
            'postSet'   => ['linked_resources' => "linked_resources - '{$linkedResources}'"],
            'where'     => ['linked_resources != ?'],
            'data'      => ['[]']
        ]);

        \SrcCore\models\DatabaseModel::delete([
            'table' => 'adr_attachments',
            'where' => ['res_id in (select res_id from res_attachments where res_id_master in (?))'],
            'data'  => [$args['resources']]
        ]);
        \SrcCore\models\DatabaseModel::delete([
            'table' => 'emails',
            'where' => ['document->>\'id\' in (?)'],
            'data'  => [$args['resources']]
        ]);
    }
}
