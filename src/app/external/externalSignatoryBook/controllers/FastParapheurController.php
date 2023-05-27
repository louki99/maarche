<?php

/**
 * Copyright Maarch since 2008 under licence GPLv3.
 * See LICENCE.txt file at the root folder for more details.
 * This file is part of Maarch software.
 *
 */

/**
 * @brief fastParapheur Controller
 * @author nathan.cheval@edissyum.com
 * @author dev@maarch.org
 */

namespace ExternalSignatoryBook\controllers;

use Attachment\controllers\AttachmentTypeController;
use Attachment\models\AttachmentModel;
use Attachment\models\AttachmentTypeModel;
use Convert\controllers\ConvertPdfController;
use Docserver\models\DocserverModel;
use Docserver\models\DocserverTypeModel;
use Entity\models\ListInstanceModel;
use History\controllers\HistoryController;
use Resource\controllers\ResController;
use Resource\controllers\StoreController;
use Resource\models\ResModel;
use Respect\Validation\Validator;
use Slim\Psr7\Request;
use SrcCore\http\Response;
use SrcCore\controllers\LogsController;
use SrcCore\models\CoreConfigModel;
use SrcCore\models\CurlModel;
use SrcCore\models\DatabaseModel;
use SrcCore\models\TextFormatModel;
use SrcCore\models\ValidatorModel;
use User\controllers\UserController;
use Note\models\NoteModel;
use Entity\models\EntityModel;
use IndexingModel\models\IndexingModelFieldModel;
use Resource\controllers\SummarySheetController;
use setasign\Fpdi\Tcpdf\Fpdi;
use Convert\models\AdrModel;
use User\models\UserModel;
use Contact\controllers\ContactController;


/**
* @codeCoverageIgnore
*/
class FastParapheurController
{
    public function getWorkflowDetails(Request $request, Response $response)
    {
        $config = FastParapheurController::getConfig();
        if (!empty($config['errors'])) {
            return $response->withStatus($config['code'])->withJson(['errors' => $config['errors']]);
        }

        $signatureModes = FastParapheurController::getSignatureModes(['mapping' => true]);
        if (!empty($signatureModes['errors'])) {
            return $response->withStatus($signatureModes['code'])->withJson(['errors' => $signatureModes['errors']]);
        }

        return $response->withJson([
            'workflowTypes'  => $config['workflowTypes']['type'],
            'signatureModes' => $signatureModes['signatureModes']
        ]);
    }

    public function linkUserToFastParapheur(Request $request, Response $response, array $args)
    {
        $body = $request->getParsedBody();
        if (!Validator::notEmpty()->email()->validate($body['fastParapheurUserEmail'] ?? null)) {
            return $response->withStatus(400)->withJson(['errors' => 'body fastParapheurUserEmail is not a valid email address']);
        }
        if (!Validator::notEmpty()->intVal()->validate($args['id'])) {
            return $response->withStatus(400)->withJson(['errors' => 'args id is not an integer']);
        }

        $userController = new UserController();
        $error = $userController->hasUsersRights(['id' => $args['id']]);
        if (!empty($error['error'])) {
            return $response->withStatus($error['status'])->withJson(['errors' => $error['error']]);
        }

        $alreadyLinked = UserModel::get([
            'select' => [1],
            'where'  => ['external_id->>\'fastParapheur\' = ?'],
            'data'   => [$body['fastParapheurUserEmail']]
        ]);
        if (!empty($alreadyLinked)) {
            return $response->withStatus(403)->withJson(['errors' => 'FastParapheur user email can only be linked to a single MaarchCourrier user', 'lang' => 'fastParapheurUserAlreadyLinked']);
        }

        $check = FastParapheurController::checkUserExistanceInFastParapheur(['fastParapheurUserEmail' => $body['fastParapheurUserEmail']]);
        if (!empty($check['errors'])) {
            return $response->withStatus($check['code'])->withJson(['errors' => $check['errors']]);
        }

        $userInfo   = UserModel::getById(['select' => ['external_id', 'firstname', 'lastname'], 'id' => $args['id']]);
        $externalId = json_decode($userInfo['external_id'], true);
        $externalId['fastParapheur'] = $body['fastParapheurUserEmail'];

        UserModel::updateExternalId(['id' => $args['id'], 'externalId' => json_encode($externalId)]);

        HistoryController::add([
            'tableName'    => 'users',
            'recordId'     => $GLOBALS['id'],
            'eventType'    => 'UP',
            'eventId'      => 'userModification',
            'info'         => _USER_LINKED_TO_FASTPARAPHEUR . " : {$userInfo['firstname']} {$userInfo['lastname']}"
        ]);

        return $response->withStatus(204);
    }

    public function unlinkUserToFastParapheur(Request $request, Response $response, array $args)
    {
        if (!Validator::notEmpty()->intVal()->validate($args['id'])) {
            return $response->withStatus(400)->withJson(['errors' => 'args id is not an integer']);
        }

        $userController = new UserController();
        $error = $userController->hasUsersRights(['id' => $args['id']]);
        if (!empty($error['error'])) {
            return $response->withStatus($error['status'])->withJson(['errors' => $error['error']]);
        }

        $user = UserModel::getById(['id' => $args['id'], 'select' => ['firstname', 'lastname', 'external_id']]);
        $externalId = json_decode($user['external_id'], true);
        unset($externalId['fastParapheur']);

        UserModel::updateExternalId(['id' => $args['id'], 'externalId' => json_encode($externalId)]);

        HistoryController::add([
            'tableName'    => 'users',
            'recordId'     => $GLOBALS['id'],
            'eventType'    => 'UP',
            'eventId'      => 'userModification',
            'info'         => _USER_UNLINKED_TO_FASTPARAPHEUR . " : {$user['firstname']} {$user['lastname']}"
        ]);

        return $response->withStatus(204);
    }

    public function userStatusInFastParapheur(Request $request, Response $response, array $args)
    {
        $loadedXml = CoreConfigModel::getXmlLoaded(['path' => 'modules/visa/xml/remoteSignatoryBooks.xml']);
        if ($loadedXml->signatoryBookEnabled != 'fastParapheur') {
            return $response->withStatus(403)->withJson(['errors' => 'fastParapheur is not enabled']);
        }

        $user = UserModel::getById(['id' => $args['id'], 'select' => ['external_id->>\'fastParapheur\' as "fastParapheurId"']]);
        if (empty($user['fastParapheurId'])) {
            return $response->withStatus(403)->withJson(['errors' => 'user does not have a Fast Parapheur email']);
        }

        $check = FastParapheurController::checkUserExistanceInFastParapheur(['fastParapheurUserEmail' => $user['fastParapheurId']]);
        if (!empty($check['errors'])) {
            return $response->withStatus($check['code'])->withJson(['errors' => $check['errors']]);
        }

        return $response->withJson(['link' => $user['fastParapheurId']]);
    }

    public function getWorkflow(Request $request, Response $response, array $args)
    {
        $queryParams = $request->getQueryParams();

        if (!empty($queryParams['type']) && $queryParams['type'] == 'resource') {
            if (!ResController::hasRightByResId(['resId' => [$args['id']], 'userId' => $GLOBALS['id']])) {
                return $response->withStatus(400)->withJson(['errors' => 'Resource out of perimeter']);
            }
            $resource = ResModel::getById(['resId' => $args['id'], 'select' => ['external_id', 'external_state']]);
            if (empty($resource)) {
                return $response->withStatus(400)->withJson(['errors' => 'Resource does not exist']);
            }
            $resource['resourceType'] = 'letterbox_coll';
        } else {
            $resource = AttachmentModel::getById(['id' => $args['id'], 'select' => ['res_id_master', 'external_id', 'external_state']]);
            if (empty($resource)) {
                return $response->withStatus(400)->withJson(['errors' => 'Attachment does not exist']);
            }
            if (!ResController::hasRightByResId(['resId' => [$resource['res_id_master']], 'userId' => $GLOBALS['id']])) {
                return $response->withStatus(400)->withJson(['errors' => 'Resource does not exist']);
            }
            $resource['resourceType'] = 'attachments_coll';
        }

        $externalId = json_decode($resource['external_id'], true);
        if (empty($externalId['signatureBookId'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Resource is not linked to Fast Parapheur']);
        }

        $externalState = json_decode($resource['external_state'], true);
        $fetchDate = new \DateTimeImmutable($externalState['signatureBookWorkflow']['fetchDate']);
        $timeAgo = new \DateTimeImmutable('-30 minutes');
        
        if (!empty($externalState['signatureBookWorkflow']['fetchDate']) && $fetchDate->getTimestamp() >= $timeAgo->getTimestamp()) {
            return $response->withJson($externalState['signatureBookWorkflow']['data']);
        }

        $loadedXml = CoreConfigModel::getXmlLoaded(['path' => 'modules/visa/xml/remoteSignatoryBooks.xml']);
        if (empty($loadedXml)) {
            return $response->withStatus(400)->withJson(['errors' => 'SignatoryBooks configuration file missing']);
        }

        $fastParapheurBlock = $loadedXml->xpath('//signatoryBook[id=\'fastParapheur\']')[0] ?? null;
        if (empty($fastParapheurBlock)) {
            return $response->withStatus(500)->withJson(['errors' => 'invalid configuration for FastParapheur']);
        }
        $url = (string)$fastParapheurBlock->url;
        $certPath = (string)$fastParapheurBlock->certPath;
        $certPass = (string)$fastParapheurBlock->certPass;
        $certType = (string)$fastParapheurBlock->certType;
        $subscriberId = (string)$fastParapheurBlock->subscriberId;

        $curlReturn = CurlModel::exec([
            'url'           => $url . '/documents/v2/' . $externalId['signatureBookId'] . '/history',
            'method'        => 'GET',
            'options'       => [
                CURLOPT_SSLCERT       => $certPath,
                CURLOPT_SSLCERTPASSWD => $certPass,
                CURLOPT_SSLCERTTYPE   => $certType
            ]
        ]);

        if ($curlReturn['code'] != 200) {
            return $response->withStatus($curlReturn['code'])->withJson($curlReturn['errors']);
        }

        if (!empty($curlReturn)) {
            $fastParapheurUsers = FastParapheurController::getUsers(['config' => [
                'subscriberId' => $subscriberId,
                'url'          => $url,
                'certPath'     => $certPath,
                'certPass'     => $certPass,
                'certType'     => $certType
            ]]);
            $fastParapheurUsers = array_column($fastParapheurUsers, 'email', 'idToDisplay');
        }

        $externalWorkflow = [];
        $order = 0;
        $mode = null;
        foreach ($curlReturn['response'] as $step) {
            if (mb_stripos($step['stateName'], 'Préparé') === 0) {
                continue;
            }
            if (empty($step['userFullname'])) {
                $mode = mb_stripos($step['stateName'], 'visa') !== false ? 'visa' : 'sign';
                continue;
            }
            $order++;
            $user = UserModel::get([
                'select' => [
                    'id',
                    'concat(firstname, \' \', lastname) as name',
                ],
                'where'  => ['external_id->>\'fastParapheur\' = ?'],
                'data'   => [$fastParapheurUsers[$step['userFullname']]],
                'limit'  => 1
            ]);
            if (empty($user)) {
                $user = ['id' => null, 'name' => '-'];
            } else {
                $user = $user[0];
            }
            $processDate = new \DateTimeImmutable($step['date']);
            $externalWorkflow[] = [
                'userId'        => $user['id'],
                'userDisplay'   => $step['userFullname'] . ' (' . $user['name'] . ')',
                'mode'          => $mode,
                'order'         => $order,
                'process_date'  => $processDate->format('d-m-Y H:i')
            ];
        }

        $currentDate = new \DateTimeImmutable();
        $externalState['signatureBookWorkflow']['fetchDate'] = $currentDate->format('c');
        $externalState['signatureBookWorkflow']['data'] = $externalWorkflow;
        if ($resource['resourceType'] == 'letterbox_coll') {
            ResModel::update([
                'where'   => ['res_id = ?'],
                'data'    => [$args['id']],
                'postSet' => [
                    'external_state' => 'jsonb_set(external_state, \'{signatureBookWorkflow}\', \'' . json_encode($externalState['signatureBookWorkflow']) . '\'::jsonb)'
                ]
            ]);
        } else {
            AttachmentModel::update([
                'where'   => ['res_id = ?'],
                'data'    => [$args['id']],
                'postSet' => [
                    'external_state' => 'jsonb_set(external_state, \'{signatureBookWorkflow}\', \'' . json_encode($externalState['signatureBookWorkflow']) . '\'::jsonb)'
                ]
            ]);
        }

        return $response->withJson($externalWorkflow);
    }

    public static function retrieveSignedMails(array $args)
    {
        $version = $args['version'];

        $fastUsers = [];
        if (!empty($args['config']['data']['integratedWorkflow']) && $args['config']['data']['integratedWorkflow'] == 'true') {
            // Get all fast users, format them to have the email and name (as formatted in fast's /history route)
            $fastUsers = FastParapheurController::getUsers(['config' => $args['config']['data'], 'noFormat' => true]);
            $fastUsers = array_map(function ($user) {
                return [
                    'email' => $user['email'],
                    'name'  => $user['nom'] . ' ' . $user['prenom']
                ];
            }, $fastUsers);
            $fastUsers = array_column($fastUsers, 'name', 'email');
        }

        foreach ($args['idsToRetrieve'][$version] as $resId => $value) {
            if (empty($value['res_id_master'])) {
                LogsController::add([
                    'isTech'    => true,
                    'moduleId'  => $GLOBALS['moduleId'],
                    'level'     => 'INFO',
                    'tableName' => $GLOBALS['batchName'],
                    'eventType' => 'script',
                    'eventId'   => "Retrieve main document resId: $resId"
                ]);
            } else {
                LogsController::add([
                    'isTech'    => true,
                    'moduleId'  => $GLOBALS['moduleId'],
                    'level'     => 'INFO',
                    'tableName' => $GLOBALS['batchName'],
                    'eventType' => 'script',
                    'eventId'   => "Retrieve attachment resId: $resId"
                ]);
            }
            if (empty(trim($value['external_id']))) {
                $args['idsToRetrieve'][$version][$resId]['status'] = 'waiting';
                continue;
            }

            if (!empty($value['external_state_fetch_date'])) {
                $fetchDate = new \DateTimeImmutable($value['external_state_fetch_date']);
                $timeAgo = new \DateTimeImmutable('-30 minutes');

                if ($fetchDate->getTimestamp() >= $timeAgo->getTimestamp()) {
                    $newDate = $fetchDate->modify('+30 minutes');

                    LogsController::add([
                        'isTech'    => true,
                        'moduleId'  => $GLOBALS['moduleId'],
                        'level'     => 'INFO',
                        'tableName' => $GLOBALS['batchName'],
                        'eventType' => 'script',
                        'eventId'   => "Time limit reached ! Next retrieve time : {$newDate->format('d-m-Y H:i')}"
                    ]);

                    unset($args['idsToRetrieve'][$version][$resId]);
                    continue;
                }
            }

            $historyResponse = FastParapheurController::getDocumentHistory(['config' => $args['config'], 'documentId' => $value['external_id']]);

            // Update external_state_fetch_date event if $historyResponse return an error. To avoid spamming the API endpoint.
            $updateHistoryFetchDate = FastParapheurController::updateFetchHistoryDateByExternalId([
                'type' => ($version == 'resLetterbox' ? 'resource' : 'attachment'),
                'resId' => $value['res_id']
            ]);
            if (!empty($updateHistoryFetchDate['errors'])) {
                LogsController::add([
                    'isTech'    => true,
                    'moduleId'  => $GLOBALS['moduleId'],
                    'level'     => 'ERROR',
                    'tableName' => $GLOBALS['batchName'],
                    'eventType' => 'script',
                    'eventId'   => "{$updateHistoryFetchDate['errors']}"
                ]);
                unset($args['idsToRetrieve'][$version][$resId]);
                continue;
            }

            // Check for $historyResponse error
            if (!empty($historyResponse['errors'])) {
                LogsController::add([
                    'isTech'    => true,
                    'moduleId'  => $GLOBALS['moduleId'],
                    'level'     => 'ERROR',
                    'tableName' => $GLOBALS['batchName'],
                    'eventType' => 'script',
                    'eventId'   => "[fastParapheur api] {$historyResponse['errors']}"
                ]);
                unset($args['idsToRetrieve'][$version][$resId]);
                continue;
            }

            if (!empty($args['config']['data']['integratedWorkflow']) && $args['config']['data']['integratedWorkflow'] == 'true') {
                if (empty($value['res_id_master'])) {
                    $resource = ResModel::getById([
                        'select' => ['external_state'],
                        'resId'  => $resId
                    ]);
                } else {
                    $resource = AttachmentModel::getById([
                        'select' => ['external_state'],
                        'id'     => $resId
                    ]);
                }
                $externalState = json_decode($resource['external_state'] ?? '{}', true);
                $knownWorkflow = array_map(function ($step) use ($fastUsers) {
                    if($step['type'] == 'externalOTP') {
                        $step['name'] = $step['lastname'] . " " . $step['firstname'];
                    } else {
                        $step['name'] = $fastUsers[$step['id']];
                    }
                    return $step;
                }, $externalState['signatureBookWorkflow']['workflow'] ?? []);

                $lastFastWorkflowAction = FastParapheurController::getLastFastWorkflowAction($historyResponse['response'], $knownWorkflow, $args['config']['data']);
                if (empty($lastFastWorkflowAction)) {
                    $args['idsToRetrieve'][$version][$resId]['status'] = 'waiting';
                    continue;
                }
                $historyResponse['response'] = [
                    $lastFastWorkflowAction
                ];
            }

            $validatedState = $args['config']['data']['validatedState'] ?? null;
            $validatedVisaState = $args['config']['data']['validatedVisaState'] ?? null;
            $refusedState = $args['config']['data']['refusedState'] ?? null;
            $refusedVisaState = $args['config']['data']['refusedVisaState'] ?? null;
            foreach ($historyResponse['response'] as $valueResponse) {    // Loop on all steps of the documents (prepared, send to signature, signed etc...)
                if ($valueResponse['stateName'] == $validatedState || $valueResponse['stateName'] == $validatedVisaState) {
                    LogsController::add([
                        'isTech'    => true,
                        'moduleId'  => $GLOBALS['moduleId'],
                        'level'     => 'INFO',
                        'tableName' => $GLOBALS['batchName'],
                        'eventType' => 'script',
                        'eventId'   => "Circuit ended ! Retrieve file from fastParapheur"
                    ]);
                    $response = FastParapheurController::download(['config' => $args['config'], 'documentId' => $value['external_id']]);
                    $args['idsToRetrieve'][$version][$resId]['status'] = 'validated';
                    $args['idsToRetrieve'][$version][$resId]['format'] = 'pdf';
                    $args['idsToRetrieve'][$version][$resId]['encodedFile'] = $response['b64FileContent'];
                    $args['idsToRetrieve'][$version][$resId]['signatory_user_serial_id'] = null;

                    $proofDocument = FastParapheurController::makeHistoryProof([
                        'documentId'      => $value['external_id'],
                        'config'          => $args['config'],
                        'historyData'     => $historyResponse['response'],
                        'filename'        => ($args['idsToRetrieve'][$version][$resId]['title'] ?? $args['idsToRetrieve'][$version][$resId]['subject']) . '.pdf',
                        'signEncodedFile' => $response['b64FileContent']
                    ]);
                    if (!empty($proofDocument['errors'])) {
                        LogsController::add([
                            'isTech'    => true,
                            'moduleId'  => $GLOBALS['moduleId'],
                            'level'     => 'ERROR',
                            'tableName' => $GLOBALS['batchName'],
                            'eventType' => 'script',
                            'eventId'   => "{$proofDocument['errors']}"
                        ]);
                        continue;
                    } elseif (!empty($proofDocument['encodedProofDocument'])) {
                        LogsController::add([
                            'isTech'    => true,
                            'moduleId'  => $GLOBALS['moduleId'],
                            'level'     => 'INFO',
                            'tableName' => $GLOBALS['batchName'],
                            'eventType' => 'script',
                            'eventId'   => "Retrieve proof from fastParapheur"
                        ]);
                        $args['idsToRetrieve'][$version][$resId]['log']       = $proofDocument['encodedProofDocument'];
                        $args['idsToRetrieve'][$version][$resId]['logFormat'] = $proofDocument['format'];
                        $args['idsToRetrieve'][$version][$resId]['logTitle']  = '[Faisceau de preuve]';
                    }

                    if (empty($args['config']['data']['integratedWorkflow']) || $args['config']['data']['integratedWorkflow'] == 'false') {
                        $signatoryInfo = FastParapheurController::getSignatoryUserInfo([
                            'config'        => $args['config'],
                            'resId'         => $args['idsToRetrieve'][$version][$resId]['res_id_master'] ?? $args['idsToRetrieve'][$version][$resId]['res_id']]);
                        $args['idsToRetrieve'][$version][$resId]['signatory_user_serial_id'] = $signatoryInfo['id'];
                    } elseif (!empty($valueResponse['userFastId'])){
                        $maarchUser = UserModel::get([
                            'select' => ['id'],
                            'where'  => ['external_id->>\'fastParapheur\' = ?'],
                            'data'   => [$valueResponse['userFastId']]
                        ]);
                        $args['idsToRetrieve'][$version][$resId]['signatory_user_serial_id'] = $maarchUser[0]['id'] ?? null;
                        $args['idsToRetrieve'][$version][$resId]['typist'] = ($maarchUser[0]['id'] ?? $args['idsToRetrieve'][$version][$resId]['typist']) ?? null;
                    }
                    LogsController::add([
                        'isTech'    => true,
                        'moduleId'  => $GLOBALS['moduleId'],
                        'level'     => 'INFO',
                        'tableName' => $GLOBALS['batchName'],
                        'eventType' => 'script',
                        'eventId'   => "Done!"
                    ]);
                    break;
                } elseif ($valueResponse['stateName'] == $refusedState || $valueResponse['stateName'] == $refusedVisaState) {
                    $signatoryInfo = FastParapheurController::getSignatoryUserInfo([
                        'config'        => $args['config'],
                        'valueResponse' => $valueResponse,
                        'resId'         => $args['idsToRetrieve'][$version][$resId]['res_id_master'] ?? $args['idsToRetrieve'][$version][$resId]['res_id']]);
                    $response = FastParapheurController::getRefusalMessage([
                        'config'        => $args['config'],
                        'documentId'    => $value['external_id'],
                        'res_id'        => $resId,
                        'version'       => $version
                    ]);
                    $args['idsToRetrieve'][$version][$resId]['status'] = 'refused';
                    if (empty($args['config']['data']['integratedWorkflow']) || $args['config']['data']['integratedWorkflow'] == 'false') {
                        $args['idsToRetrieve'][$version][$resId]['notes'][] = ['content' => $signatoryInfo['lastname'] . ' ' . $signatoryInfo['firstname'] . ' : ' . $response];
                    } else {
                        $args['idsToRetrieve'][$version][$resId]['notes'][] = ['content' => $signatoryInfo['name'] . ' : ' . $response];
                    }
                    LogsController::add([
                        'isTech'    => true,
                        'moduleId'  => $GLOBALS['moduleId'],
                        'level'     => 'INFO',
                        'tableName' => $GLOBALS['batchName'],
                        'eventType' => 'script',
                        'eventId'   => "Done!"
                    ]);
                    break;
                } else {
                    $args['idsToRetrieve'][$version][$resId]['status'] = 'waiting';
                }
            }
        }
        
        return $args['idsToRetrieve'];
    }

    /**
     * Create proof from history data, get proof from fast (Fiche de Circulation)
     * 
     * @param   array   $args   documentId, config, historyData, filename, signEncodedFile
     */
    public static function makeHistoryProof(array $args)
    {
        if (!Validator::stringType()->notEmpty()->validate($args['documentId'])) {
            return ['errors' => 'documentId is not an array'];
        }
        if (!Validator::arrayType()->notEmpty()->validate($args['config'])) {
            return ['errors' => 'config is not an array'];
        }
        if (!Validator::arrayType()->notEmpty()->validate($args['historyData'])) {
            return ['errors' => 'historyData is not an array'];
        }
        if (!Validator::stringType()->notEmpty()->validate($args['filename'])) {
            return ['errors' => 'filename is not a string'];
        }
        if (!Validator::stringType()->notEmpty()->validate($args['signEncodedFile'])) {
            return ['errors' => 'signEncodedFile is not a string'];
        }

        $documentPathToZip  = [];
        $tmpPath            = CoreConfigModel::getTmpPath();
        $proof              = ['history' => $args['historyData']];

        $signDocumentPath   = $tmpPath . 'fastSignDoc' . "_" . rand() . '.pdf';
        file_put_contents($signDocumentPath, base64_decode($args['signEncodedFile']));

        $filename = TextFormatModel::formatFilename(['filename' => $args['filename']]);
        if (file_exists($signDocumentPath) && filesize($signDocumentPath) > 0) {
            $proof = [
                'signedDocument'    => [
                    'filename'      => $filename,
                    'filenameSize'  => filesize($signDocumentPath)
                ]
            ];
            $documentPathToZip[] = ['path' => $signDocumentPath, 'filename' => $filename];
        }

        
        $fdc = FastParapheurController::getProof(['documentId' => $args['documentId'], 'config' => $args['config']]);
        if (!empty($fdc['errors'])) {
            return ['errors' => $fdc['errors']];
        }
        $fdcPath = $tmpPath . 'ficheDeCirculation' . "_" . rand() . '.pdf';
        file_put_contents($fdcPath, $fdc['response']);

        $documentPathToZip[] = ['path' => $fdcPath, 'filename' => 'ficheDeCirculation.pdf'];
        $proof['proof'] = [
            'filename' => 'ficheDeCirculation.pdf',
            'filenameSize' => filesize($fdcPath)
        ];
        $proof['history'] = $args['historyData'];


        $proofJson = json_encode($proof, JSON_PRETTY_PRINT);
        $proofJsonPath = $tmpPath . 'maarchProof' . "_" . rand() . '.json';
        $proofCreation = file_put_contents($proofJsonPath, $proofJson);
        if (empty($proofCreation)) {
            return ['errors' => 'Cannot create proof json'];
        }
        $documentPathToZip[] = ['path' => $proofJsonPath, 'filename' => 'maarchProof.json'];


        $zipFileContent = null;
        $zip = new \ZipArchive();
        $zipFilename = $tmpPath . 'archivedProof' . '_' . rand() . '.zip';

        if ($zip->open($zipFilename, \ZipArchive::CREATE) === true) {
            foreach ($documentPathToZip as $document) {
                if(file_exists($document['path']) && filesize($document['path']) > 0) {
                    $zip->addFile($document['path'], $document['filename']);
                }
            }
            $zip->close();
            $zipFileContent = file_get_contents($zipFilename);
            $documentPathToZip[] = ['path' => $zipFilename];
        } else {
            return ['errors' => 'Cannot create archive zip'];
        }

        foreach ($documentPathToZip as $document) {
            if(file_exists($document['path']) && filesize($document['path']) > 0) {
                unlink($document['path']);
            }
        }

        return ['format' => 'zip','encodedProofDocument' => base64_encode($zipFileContent)];
    }

    public static function getProof(array $args)
    {
        ValidatorModel::notEmpty($args, ['documentId', 'config']);
        ValidatorModel::stringType($args, ['documentId']);
        ValidatorModel::arrayType($args, ['config']);

        $curlReturn = CurlModel::exec([
            'url'           => $args['config']['data']['url'] . '/documents/v2/' . $args['documentId'] . '/getFdc',
            'method'        => 'GET',
            'options'       => [
                CURLOPT_SSLCERT       => $args['config']['data']['certPath'],
                CURLOPT_SSLCERTPASSWD => $args['config']['data']['certPass'],
                CURLOPT_SSLCERTTYPE   => $args['config']['data']['certType']
            ],
            'fileResponse'  => true
        ]);

        if ($curlReturn['code'] == 404) {
            return ['code' => 400, 'errors' => "Erreur 404 : {$curlReturn['raw']}"];
        } elseif (!empty($curlReturn['response']['developerMessage'])) {
            return ['code' => 500, 'errors' => $curlReturn['response']['developerMessage']];
        }
        return ['response' => $curlReturn['response']];
    }

    public static function getSignatoryUserInfo(array $args = [])
    {
        ValidatorModel::notEmpty($args, ['resId', 'config']);

        $signatoryInfo = null;

        if (empty($args['config']['data']['integratedWorkflow']) || $args['config']['data']['integratedWorkflow'] == 'false') {
            $signatoryInfo = DatabaseModel::select([
                'select'    => ['firstname', 'lastname', 'users.id'],
                'table'     => ['listinstance', 'users'],
                'left_join' => ['listinstance.item_id = users.id'],
                'where'     => ['res_id = ?', 'process_date is null', 'difflist_type = ?'],
                'data'      => [$args['resId'], 'VISA_CIRCUIT']
            ])[0];
        } else {
            if (!empty($args['valueResponse']['userFullname'])) {
                $search = $args['valueResponse']['userFullname'];

                $fpUsers = FastParapheurController::getUsers([
                    'config' => [
                        'subscriberId' => $args['config']['data']['subscriberId'],
                        'url'          => $args['config']['data']['url'],
                        'certPath'     => $args['config']['data']['certPath'],
                        'certPass'     => $args['config']['data']['certPass'],
                        'certType'     => $args['config']['data']['certType']
                    ]
                ]);
                if (empty($fpUsers)) {
                    return null;
                }

                $fpUser = array_filter($fpUsers, function ($fpUser) use ($search) {
                    return mb_stripos($fpUser['email'], $search) > -1 || 
                        mb_stripos($fpUser['idToDisplay'], $search) > -1 ||
                        mb_stripos($fpUser['idToDisplay'], explode(' ', $search)[1] . ' ' . explode(' ', $search)[0]) > -1;
                });
                $fpUser = array_values($fpUser)[0];

                $alreadyLinkedUsers = UserModel::get([
                    'select' => [
                        'external_id->>\'fastParapheur\' as "fastParapheurEmail"',
                        'trim(concat(firstname, \' \', lastname)) as name'
                    ],
                    'where'  => ['external_id->>\'fastParapheur\' is not null']
                ]);

                foreach ($alreadyLinkedUsers as $alreadyLinkedUser) {
                    if ($fpUser['email'] == $alreadyLinkedUser['fastParapheurEmail']) {
                        $signatoryInfo['name'] = $alreadyLinkedUser['name'] . ' (' . $alreadyLinkedUser['fastParapheurEmail'] . ')';
                        break;
                    }
                }
            }
        }

        return $signatoryInfo;
    }

    public static function processVisaWorkflow(array $args = [])
    {
        $resIdMaster = $args['res_id_master'] ?? $args['res_id'];

        $attachments = AttachmentModel::get(['select' => ['count(1)'], 'where' => ['res_id_master = ?', 'status = ?'], 'data' => [$resIdMaster, 'FRZ']]);
        if ((count($attachments) < 2 && $args['processSignatory']) || !$args['processSignatory']) {
            $visaWorkflow = ListInstanceModel::get([
                'select'  => ['listinstance_id', 'requested_signature'],
                'where'   => ['res_id = ?', 'difflist_type = ?', 'process_date IS NULL'],
                'data'    => [$resIdMaster, 'VISA_CIRCUIT'],
                'orderBY' => ['ORDER BY listinstance_id ASC']
            ]);
    
            if (!empty($visaWorkflow)) {
                foreach ($visaWorkflow as $listInstance) {
                    if ($listInstance['requested_signature']) {
                        // Stop to the first signatory user
                        if ($args['processSignatory']) {
                            ListInstanceModel::update(['set' => ['signatory' => 'true', 'process_date' => 'CURRENT_TIMESTAMP'], 'where' => ['listinstance_id = ?'], 'data' => [$listInstance['listinstance_id']]]);
                        }
                        break;
                    }
                    ListInstanceModel::update(['set' => ['process_date' => 'CURRENT_TIMESTAMP'], 'where' => ['listinstance_id = ?'], 'data' => [$listInstance['listinstance_id']]]);
                }
            }
        }
    }

    public static function upload(array $args)
    {
        ValidatorModel::notEmpty($args, ['circuitId', 'label', 'businessId']);
        ValidatorModel::stringType($args, ['circuitId', 'label', 'businessId']);

        $circuitId    = $args['circuitId'];
        $label        = $args['label'];
        $subscriberId = $args['businessId'];

        // Retrieve the annexes of the attachemnt to sign (other attachment and the original document)
        $annexes = [];
        $annexes['letterbox'] = ResModel::get([
            'select' => ['res_id', 'path', 'filename', 'docserver_id', 'format', 'category_id', 'external_id', 'integrations'],
            'where'  => ['res_id = ?'],
            'data'   => [$args['resIdMaster']]
        ]);

        if (!empty($annexes['letterbox'][0]['docserver_id'])) {
            $adrMainInfo = ConvertPdfController::getConvertedPdfById(['resId' => $args['resIdMaster'], 'collId' => 'letterbox_coll']);
            $letterboxPath = DocserverModel::getByDocserverId(['docserverId' => $adrMainInfo['docserver_id'], 'select' => ['path_template']]);
            $annexes['letterbox'][0]['filePath'] = $letterboxPath['path_template'] . str_replace('#', '/', $adrMainInfo['path']) . $adrMainInfo['filename'];
        }

        $attachments = AttachmentModel::get([
            'select'    => [
                'res_id', 'docserver_id', 'path', 'filename', 'format', 'attachment_type', 'fingerprint'
            ],
            'where'     => ["res_id_master = ?", "attachment_type not in (?)", "status not in ('DEL', 'OBS', 'FRZ', 'TMP', 'SEND_MASS')", "in_signature_book = 'true'"],
            'data'      => [$args['resIdMaster'], ['signed_response']]
        ]);

        $attachmentTypes = AttachmentTypeModel::get(['select' => ['type_id', 'signable']]);
        $attachmentTypes = array_column($attachmentTypes, 'signable', 'type_id');
        foreach ($attachments as $key => $value) {
            if (!$attachmentTypes[$value['attachment_type']]) {
                $annexeAttachmentPath = DocserverModel::getByDocserverId(['docserverId' => $value['docserver_id'], 'select' => ['path_template', 'docserver_type_id']]);
                $value['filePath']    = $annexeAttachmentPath['path_template'] . str_replace('#', DIRECTORY_SEPARATOR, $value['path']) . $value['filename'];

                $docserverType = DocserverTypeModel::getById(['id' => $annexeAttachmentPath['docserver_type_id'], 'select' => ['fingerprint_mode']]);
                $fingerprint = StoreController::getFingerPrint(['filePath' => $value['filePath'], 'mode' => $docserverType['fingerprint_mode']]);
                if ($value['fingerprint'] != $fingerprint) {
                    return ['error' => 'Fingerprints do not match'];
                }

                unset($attachments[$key]);
                $annexes['attachments'][] = $value;
            }
        }
        // END annexes

        $attachmentToFreeze = [];
        foreach ($attachments as $attachment) {
            $resId  = $attachment['res_id'];
            $collId = 'attachments_coll';
            
            $response = FastParapheurController::uploadFile([
                'resId'        => $resId,
                'collId'       => $collId,
                'resIdMaster'  => $args['resIdMaster'],
                'annexes'      => $annexes,
                'circuitId'    => $circuitId,
                'label'        => $label,
                'subscriberId' => $subscriberId,
                'config'       => $args['config']
            ]);

            if (!empty($response['error'])) {
                return $response;
            } else {
                $attachmentToFreeze[$collId][$resId] = $response['success'];
            }
        }

        // Send main document if in signature book
        if (!empty($annexes['letterbox'][0])) {
            $mainDocumentIntegration = json_decode($annexes['letterbox'][0]['integrations'], true);
            $externalId              = json_decode($annexes['letterbox'][0]['external_id'], true);
            if ($mainDocumentIntegration['inSignatureBook'] && empty($externalId['signatureBookId'])) {
                $resId  = $annexes['letterbox'][0]['res_id'];
                $collId = 'letterbox_coll';
                unset($annexes['letterbox']);
    
                $response = FastParapheurController::uploadFile([
                    'resId'        => $resId,
                    'collId'       => $collId,
                    'resIdMaster'  => $args['resIdMaster'],
                    'annexes'      => $annexes,
                    'circuitId'    => $circuitId,
                    'label'        => $label,
                    'subscriberId' => $subscriberId,
                    'config'       => $args['config']
                ]);

                if (!empty($response['error'])) {
                    return $response;
                } else {
                    $attachmentToFreeze[$collId][$resId] = $response['success'];
                }
            }
        }

        return ['sended' => $attachmentToFreeze];
    }

    public static function uploadFile(array $args)
    {
        $adrInfo = ConvertPdfController::getConvertedPdfById(['resId' => $args['resId'], 'collId' => $args['collId']]);
        if (empty($adrInfo['docserver_id']) || strtolower(pathinfo($adrInfo['filename'], PATHINFO_EXTENSION)) != 'pdf') {
            return ['error' => 'Document ' . $args['resIdMaster'] . ' is not converted in pdf'];
        }
        $attachmentPath     =  DocserverModel::getByDocserverId(['docserverId' => $adrInfo['docserver_id'], 'select' => ['path_template']]);
        $attachmentFilePath = $attachmentPath['path_template'] . str_replace('#', '/', $adrInfo['path']) . $adrInfo['filename'];
        $attachmentFileName = 'projet_courrier_' . $args['resIdMaster'] . '_' . rand(0001, 9999) . '.pdf';

        $zip         = new \ZipArchive();
        $tmpPath     = CoreConfigModel::getTmpPath();
        $zipFilePath = $tmpPath . DIRECTORY_SEPARATOR
            . $attachmentFileName . '.zip';  // The zip file need to have the same name as the attachment we want to sign

        if ($zip->open($zipFilePath, \ZipArchive::CREATE)!==true) {
            return ['error' => "Can not open file : <$zipFilePath>\n"];
        }
        $zip->addFile($attachmentFilePath, $attachmentFileName);

        if (!empty($args['annexes']['letterbox'])) {
            $zip->addFile($args['annexes']['letterbox'][0]['filePath'], 'document_principal.' . $args['annexes']['letterbox'][0]['format']);
        }

        if (isset($args['annexes']['attachments'])) {
            for ($j = 0; $j < count($args['annexes']['attachments']); $j++) {
                $zip->addFile(
                    $args['annexes']['attachments'][$j]['filePath'],
                    'PJ_' . ($j + 1) . '.' . $args['annexes']['attachments'][$j]['format']
                );
            }
        }

        $zip->close();
        
        $result = FastParapheurController::uploadFileToFast([
            'config'        => $args['config'],
            'circuitId'     => str_replace('.', '-', $args['circuitId']),
            'fileName'      => $attachmentFileName . '.zip',
            'b64Attachment' => file_get_contents($zipFilePath),
            'label'         => $args['label']
        ]);
        if (!empty($result['error'])) {
            return ['error' => $result['error'], 'code' => $result['code']];
        }

        FastParapheurController::processVisaWorkflow(['res_id_master' => $args['resIdMaster'], 'processSignatory' => false]);
        $documentId = $result['response'];
        return ['success' => (string)$documentId];
    }

    /**
     * Function to send files to FastParapheur only
     * @param   array   $args:
     *                      - config
     *                      - circuitId
     *                      - fileName
     *                      - circuib64AttachmenttId
     *                      - label
     */
    public static function uploadFileToFast(array $args)
    {
        ValidatorModel::notEmpty($args, ['config', 'circuitId']);
        ValidatorModel::arrayType($args, ['config']);
        ValidatorModel::stringType($args, ['circuitId']);

        $curlReturn = CurlModel::exec([
            'url'           => $args['config']['data']['url'] . '/documents/v2/' . $args['config']['data']['subscriberId'] . '/' . $args['circuitId'] . '/upload',
            'method'        => 'POST',
            'options'       => [
                CURLOPT_SSLCERT       => $args['config']['data']['certPath'],
                CURLOPT_SSLCERTPASSWD => $args['config']['data']['certPass'],
                CURLOPT_SSLCERTTYPE   => $args['config']['data']['certType']
            ],
            'multipartBody' => [
                'content'   => ['isFile' => true, 'filename' => $args['fileName'], 'content' => $args['b64Attachment']],
                'label'     => $args['label'],
                'comment'   => ""
            ]
        ]);

        if ($curlReturn['code'] == 404) {
            return ['error' => 'Erreur 404 : ' . $curlReturn['raw'], 'code' => $curlReturn['code']];
        } elseif (!empty($curlReturn['errors'])) {
            return ['error' => $curlReturn['errors'], 'code' => $curlReturn['code']];
        } elseif (!empty($curlReturn['response']['developerMessage'])) {
            return ['error' => $curlReturn['response']['developerMessage'], 'code' => $curlReturn['code']];
        }

        return ['response' => $curlReturn['response']];
    }

    /**
     * upload to FastParapheur with integrated workflow steps
     *
     * @param array $args:
     *   - resIdMaster: identifier of the res_letterbox item to send
     *   - config: FastParapheur configuration
     *   - steps: an array of steps, each being an associative array with:
     *     - mode: 'visa' or 'signature'
     *     - type: 'maarchCourrierUserId' or 'fastParapheurUserEmail'
     *     - id: identifies the user, int for maarchCourrierUserId, string for fastParapheurUserEmail
     *
     * @return array links between MC and FP identifiers:
     *   [
     *     'sended' => [
     *       'letterbox_coll' => [
     *         $maarchCourrierResId => $fastParapheurDocumentId,
     *         ...
     *       ],
     *       'attachments_coll' => [
     *         $maarchCourrierAttachmentResId => $fastParapheurDocumentId,
     *         ...
     *       ]
     *     ]
     *   ]
     */
    public static function uploadWithSteps(array $args)
    {
        ValidatorModel::notEmpty($args, ['resIdMaster', 'steps', 'config', 'workflowType']);
        ValidatorModel::intType($args, ['resIdMaster']);
        ValidatorModel::arrayType($args, ['steps', 'config']);
        ValidatorModel::stringType($args, ['workflowType']);

        $subscriberId = $args['config']['subscriberId'] ?? null;
        if (empty($subscriberId)) {
            return ['error' => _NO_SUBSCRIBER_ID_FOUND_FAST_PARAPHEUR];
        }
        if (empty($args['workflowType'])) {
            return ['error' => _NO_WORKFLOW_TYPE_FOUND_FAST_PARAPHEUR];
        }
        
        $signatureModes = FastParapheurController::getSignatureModes(['mapping' => false]);
        if (!empty($signatureModes['errors'])) {
            return ['errors' => $signatureModes['errors']];
        }

        $signatureModes = array_column($signatureModes['signatureModes'], 'id');

        $circuit = [
            'type'  => $args['workflowType'],
            'steps' => []
        ];

        $otpInfo = [];
        foreach ($args['steps'] as $index => $step) {
            $stepMode = FastParapheurController::getSignatureModeById(['signatureModeId' => $step['mode']]);

            if (in_array($stepMode, $signatureModes) && !empty($step['type']) && !empty($step['id'])) {
                if ($step['type'] == 'maarchCourrierUserId') {
                    $user = UserModel::getById(['id' => $step['id'], 'select' => ['external_id->>\'fastParapheur\' as "fastParapheurEmail"']]);
                    if (empty($user['fastParapheurEmail'])) {
                        return ['errors' => 'no FastParapheurEmail for user ' . $step['id'], 'code' => 400];
                    }
                    $circuit['steps'][] = [
                        'step'    => $stepMode,
                        'members' => [$user['fastParapheurEmail']]
                    ];
                } elseif ($step['type'] == 'fastParapheurUserEmail') {
                    $circuit['steps'][] = [
                        'step'    => $stepMode,
                        'members' => [$step['id']]
                    ];
                }
            } elseif ($step['type'] == 'externalOTP'
                    && Validator::notEmpty()->phone()->validate($step['phone'])
                    && Validator::notEmpty()->email()->validate($step['email'])
                    && Validator::notEmpty()->stringType()->validate($step['firstname'])
                    && Validator::notEmpty()->stringType()->validate($step['lastname'])) {
                $circuit['steps'][] = [
                    'step'    => 'OTPSignature',
                    'members' => [$step['email']]
                ];
                $otpInfo['OTP_firstname_' . $index]   = $step['firstname'];
                $otpInfo['OTP_lastname_' . $index]    = $step['lastname'];
                $otpInfo['OTP_phonenumber_' . $index] = $step['phone'];
                $otpInfo['OTP_email_' . $index]       = $step['email'];
            } else {
                return ['error' => 'step number ' . ($index + 1) . ' is invalid', 'code' => 400];
            }
        }
        if (empty($circuit['steps'])) {
            return ['error' => 'steps are empty or invalid', 'code' => 400];
        }

        $otpInfoXML = null;
        if (!empty($otpInfo)) {
            $otpInfoXML = FastParapheurController::generateOtpXml([
                'prettyPrint'   => true,
                'otpInfo'       => $otpInfo
            ]);
            if (!empty($otpInfoXML['errors'])) {
                return ['error' => $$otpInfoXML['errors']];
            }
        }

        $resource = ResModel::getById([
            'resId'  => $args['resIdMaster'],
            'select' => ['res_id', 'subject', 'typist', 'integrations', 'docserver_id', 'path', 'filename', 'category_id', 'format', 'external_id', 'external_state']
        ]);
        if (empty($resource)) {
            return ['error' => 'resource does not exist', 'code' => 400];
        }
        $resource['external_id'] = json_decode($resource['external_id'], true);

        if ($resource['format'] != 'pdf' && !empty($resource['docserver_id'])) {
            $convertedDocument = ConvertPdfController::getConvertedPdfById(['collId' => 'letterbox_coll', 'resId' => $args['resIdMaster']]);
            if (!empty($convertedDocument['errors'])) {
                return ['error' => 'unable to convert main document'];
            }
            $resource['docserver_id'] = $convertedDocument['docserver_id'];
            $resource['path'] = $convertedDocument['path'];
            $resource['filename'] = $convertedDocument['filename'];
        }

        $sentAttachments = [];
        $sentMainDocument = [];
        $docservers = DocserverModel::get(['select' => ['docserver_id', 'path_template']]);
        $docservers = array_column($docservers, 'path_template', 'docserver_id');
        $attachmentTypeSignable = AttachmentTypeModel::get(['select' => ['type_id', 'signable']]);
        $attachmentTypeSignable = array_column($attachmentTypeSignable, 'signable', 'type_id');

        $mainDocumentSigned = AdrModel::getConvertedDocumentById([
            'select' => [1],
            'resId'  => $args['resIdMaster'],
            'collId' => 'letterbox_coll',
            'type'   => 'SIGN'
        ]);

        if (!empty($docservers[$resource['docserver_id']])) {
            $resource['integrations'] = json_decode($resource['integrations'], true);
            if (!empty($resource['integrations']['inSignatureBook'])) {
                $sentMainDocument = [
                    'comment'  => $resource['subject'],
                    'signable' => empty($mainDocumentSigned),
                    'path'     => $docservers[$resource['docserver_id']] . $resource['path'] . $resource['filename']
                ];
            }
        }

        $attachments = AttachmentModel::get([
            'select'    => [
                'res_id', 'title', 'docserver_id', 'path', 'filename', 'format', 'attachment_type', 'fingerprint', 'external_state'
            ],
            'where'     => ['res_id_master = ?', 'attachment_type not in (?)', 'status not in (\'DEL\', \'OBS\', \'FRZ\', \'TMP\', \'SEND_MASS\')', 'in_signature_book is true'],
            'data'      => [$args['resIdMaster'], AttachmentTypeController::UNLISTED_ATTACHMENT_TYPES]
        ]);
        foreach ($attachments as $attachment) {
            if ($attachment['format'] != 'pdf') {
                $convertedAttachment = ConvertPdfController::getConvertedPdfById(['collId' => 'attachments_coll', 'resId' => $attachment['res_id']]);
                if (!empty($convertedAttachment['errors'])) {
                    continue;
                }
                $attachment['docserver_id'] = $convertedAttachment['docserver_id'];
                $attachment['path']         = $convertedAttachment['path'];
                $attachment['filename']     = $convertedAttachment['filename'];
                $attachment['format']       = 'pdf';
            }
            $externalState = json_decode($attachment['external_state'] ?? '{}', true);
            $sentAttachments[] = [
                'comment'       => $attachment['title'],
                'signable'      => $attachmentTypeSignable[$attachment['attachment_type']] && $attachment['format'] == 'pdf',
                'path'          => $docservers[$attachment['docserver_id']] . $attachment['path'] . $attachment['filename'],
                'resId'         => $attachment['res_id'],
                'externalState' => $externalState
            ];
        }

        $uploads = [];
        $appendices = [];
        if (!empty($sentMainDocument) && is_file($sentMainDocument['path'])) {
            if ($sentMainDocument['signable']) {
                $uploads[] = [
                    'id' => [
                        'collId' => 'letterbox_coll',
                        'resId'  => $args['resIdMaster']
                    ],
                    'doc' => [
                        'path'     => $sentMainDocument['path'],
                        'filename' => TextFormatModel::formatFilename([
                            'filename'  => $sentMainDocument['comment'] . '.' . pathinfo($sentMainDocument['path'], PATHINFO_EXTENSION),
                            'maxLength' => 50
                        ])
                    ],
                    'comment' => $sentMainDocument['comment']
                ];

                $externalState = json_decode($resource['external_state'] ?? '{}', true);
                $externalState['signatureBookWorkflow']['workflow'] = $args['steps'];
                ResModel::update([
                    'where'   => ['res_id = ?'],
                    'data'    => [$args['resIdMaster']],
                    'postSet' => [
                        'external_state' => 'jsonb_set(external_state, \'{signatureBookWorkflow}\', \'' . json_encode($externalState['signatureBookWorkflow']) . '\'::jsonb)'
                    ]
                ]);
            } else {
                $appendices[] = [
                    'isFile'   => true,
                    'content'  => file_get_contents($sentMainDocument['path']),
                    'filename' => TextFormatModel::formatFilename([
                        'filename'  => $sentMainDocument['comment'] . '.' . pathinfo($sentMainDocument['path'], PATHINFO_EXTENSION),
                        'maxLength' => 50
                    ])
                ];
            }
        }
        foreach ($sentAttachments as $sentAttachment) {
            if (!is_file($sentAttachment['path'])) {
                continue;
            }
            if ($sentAttachment['signable']) {
                $uploads[] = [
                    'id' => [
                        'collId' => 'attachments_coll',
                        'resId'  => $sentAttachment['resId']
                    ],
                    'doc' => [
                        'path'     => $sentAttachment['path'],
                        'filename' => TextFormatModel::formatFilename([
                            'filename'  => $sentAttachment['comment'] . '.' . pathinfo($sentAttachment['path'], PATHINFO_EXTENSION),
                            'maxLength' => 50
                        ])
                    ],
                    'comment' => $sentAttachment['comment']
                ];
                $externalState = $sentAttachment['externalState'];
                $externalState['signatureBookWorkflow']['workflow'] = $args['steps'];
                AttachmentModel::update([
                    'where'   => ['res_id = ?'],
                    'data'    => [$sentAttachment['resId']],
                    'postSet' => [
                        'external_state' => 'jsonb_set(external_state, \'{signatureBookWorkflow}\', \'' . json_encode($externalState['signatureBookWorkflow']) . '\'::jsonb)'
                    ]
                ]);
            } else {
                $appendices[] = [
                    'isFile'   => true,
                    'content'  => file_get_contents($sentAttachment['path']),
                    'filename' => TextFormatModel::formatFilename([
                        'filename'  => $sentAttachment['comment'] . '.' . pathinfo($sentAttachment['path'], PATHINFO_EXTENSION),
                        'maxLength' => 50
                    ])
                ];
            }
        }

        $user = UserModel::getById(['id' => $GLOBALS['id'], 'select' => ['user_id']]);
        $summarySheetFilePath = FastParapheurController::getSummarySheetFile([
            'docResId' => $args['resIdMaster'],
            'login' => $user['user_id']
        ]);
        $appendices[] = [
            'isFile'   => true,
            'content'  => file_get_contents($summarySheetFilePath),
            'filename' => TextFormatModel::formatFilename([
                'filename'  => 'Fiche-De-Liaison.' . pathinfo($summarySheetFilePath, PATHINFO_EXTENSION),
                'maxLength' => 50
            ])
        ];
        unlink($summarySheetFilePath);

        if (empty($uploads)) {
            return ['error' => 'resource has nothing to sign', 'code' => 400];
        }

        $returnIds = ['sended' => ['letterbox_coll' => [], 'attachments_coll' => []]];

        foreach ($uploads as $upload) {

            $result = FastParapheurController::onDemandUploadFilesToFast([
                'config'     => $args['config'],
                'upload'     => $upload,
                'circuit'    => $circuit,
                'appendices' => $appendices
            ]);
            if (!empty($result['error'])) {
                return ['code' => $result['code'], 'error' => $result['error']];
            }

            if (!empty($otpInfoXML['content'])) {
                $curlReturn = CurlModel::exec([
                    'method'  => 'PUT',
                    'url'     => $args['config']['url'] . '/documents/v2/otp/' . $result['response'] . '/metadata/define',
                    'options' => [
                        CURLOPT_SSLCERT       => $args['config']['certPath'],
                        CURLOPT_SSLCERTPASSWD => $args['config']['certPass'],
                        CURLOPT_SSLCERTTYPE   => $args['config']['certType']
                    ],
                    'multipartBody' => [
                        'otpinformation' => ['isFile' => true, 'filename' => 'METAS_API.xml', 'content' => $otpInfoXML['content']]
                    ]
                ]);
                if ($curlReturn['code'] != 200) {
                    return ['error' => $curlReturn, 'code' => $curlReturn['code']];
                }
            }

            $returnIds['sended'][$upload['id']['collId']][$upload['id']['resId']] = (string)$result['response'];

        }

        return $returnIds;
    }

    public static function onDemandUploadFilesToFast(array $args)
    {
        ValidatorModel::notEmpty($args, ['config', 'upload', 'circuit']);
        ValidatorModel::arrayType($args, ['config', 'upload', 'circuit', 'appendices']);

        $curlReturn = CurlModel::exec([
            'method'  => 'POST',
            'url'     => $args['config']['url'] . '/documents/ondemand/' . $args['config']['subscriberId'] . '/upload',
            'options' => [
                CURLOPT_SSLCERT       => $args['config']['certPath'],
                CURLOPT_SSLCERTPASSWD => $args['config']['certPass'],
                CURLOPT_SSLCERTTYPE   => $args['config']['certType']
            ],
            'multipartBody' => [
                'comment' => $args['upload']['comment'],
                'doc'     => ['isFile' => true, 'filename' => $args['upload']['doc']['filename'], 'content' => file_get_contents($args['upload']['doc']['path'])],
                'annexes' => ['subvalues' => $args['appendices']],
                'circuit' => json_encode($args['circuit'])
            ]
        ]);

        if ($curlReturn['code'] != 200) {
            return ['code' => $curlReturn['code'], 'error' => $curlReturn['errors']];
        }
        if (!empty($curlReturn['response']['developerMessage'])) {
            return ['code' => $curlReturn['code'], 'error' => $curlReturn['response']['userFriendlyMessage']];
        }

        return ['response' => $curlReturn['response']];
    }

    public static function download(array $args)
    {
        $curlReturn = CurlModel::exec([
            'url'           => $args['config']['data']['url'] . '/documents/v2/' . $args['documentId'] . '/download',
            'method'        => 'GET',
            'options'       => [
                CURLOPT_SSLCERT       => $args['config']['data']['certPath'],
                CURLOPT_SSLCERTPASSWD => $args['config']['data']['certPass'],
                CURLOPT_SSLCERTTYPE   => $args['config']['data']['certType'],
            ],
            'fileResponse'  => true
        ]);

        if ($curlReturn['code'] == 404) {
            echo "Erreur 404 : {$curlReturn['raw']}";
            return false;
        } elseif (!empty($curlReturn['response']['developerMessage'])) {
            echo $curlReturn['response']['developerMessage'];
            return false;
        } else {
            return ['b64FileContent' => base64_encode($curlReturn['response'])];
        }
    }

    public static function sendDatas(array $args)
    {
        $config = $args['config'];

        if (!empty($config['data']['integratedWorkflow']) && $config['data']['integratedWorkflow'] == 'true') {
            $steps = [];
            // TODO rework steps mechanic, will not work for signaturePositions !
            if (empty($args['steps'])) {
                return ['error' => 'steps is empty'];
            }
            $resId = $args['steps'][0]['resId'] ?? null;
            if ($resId === null) {
                return ['error' => 'no resId found in steps'];
            }

            foreach ($args['steps'] as $step) {
                if ($step['resId'] !== $resId) {
                    continue;
                }
                if (!empty($step['externalInformations'])) {
                    $steps[$step['sequence']] = [
                        'mode' => $step['signatureMode'],
                        'type' => 'externalOTP',
                        'phone'     => $step['externalInformations']['phone'] ?? null,
                        'email'     => $step['externalInformations']['email'] ?? null,
                        'firstname' => $step['externalInformations']['firstname'] ?? null,
                        'lastname'  => $step['externalInformations']['lastname'] ?? null
                    ];
                } else {
                    $steps[$step['sequence']] = [
                        'mode' => $step['signatureMode'],
                        'type' => 'fastParapheurUserEmail',
                        'id'   => $step['externalId']
                    ];
                }
            }
            return FastParapheurController::uploadWithSteps([
                'config'      => $config['data'],
                'resIdMaster' => $args['resIdMaster'],
                'steps'       => $steps,
                'workflowType'=> $args['workflowType']
            ]);
        } else {
            // We need the SIRET field and the user_id of the signatory user's primary entity
            $signatory = DatabaseModel::select([
                'select'    => ['user_id', 'external_id', 'entities.entity_label'],
                'table'     => ['listinstance', 'users_entities', 'entities'],
                'left_join' => ['item_id = user_id', 'users_entities.entity_id = entities.entity_id'],
                'where'     => ['res_id = ?', 'item_mode = ?', 'process_date is null'],
                'data'      => [$args['resIdMaster'], 'sign']
            ])[0];
            $redactor = DatabaseModel::select([
                'select'    => ['short_label'],
                'table'     => ['res_view_letterbox', 'users_entities', 'entities'],
                'left_join' => ['dest_user = user_id', 'users_entities.entity_id = entities.entity_id'],
                'where'     => ['res_id = ?'],
                'data'      => [$args['resIdMaster']]
            ])[0];

            $signatory['business_id'] = json_decode($signatory['external_id'], true)['fastParapheurSubscriberId'];
            if (empty($signatory['business_id']) || substr($signatory['business_id'], 0, 3) == 'org') {
                $signatory['business_id'] = $config['data']['subscriberId'];
            }

            $user = [];
            if (!empty($signatory['user_id'])) {
                $user = UserModel::getById(['id' => $signatory['user_id'], 'select' => ['user_id']]);
            }

            if (empty($user['user_id'])) {
                return ['error' => _VISA_WORKFLOW_NOT_FOUND];
            }

            // check if circuidId is an email
            if (Validator::email()->notEmpty()->validate($user['user_id'])) {
                $user['user_id'] = explode("@", $user['user_id'])[0];
            }

            if (empty($signatory['business_id'])) {
                return ['error' => _NO_BUSINESS_ID];
            }

            if (empty($redactor['short_label'])) {
                return ['error' => _VISA_WORKFLOW_ENTITY_NOT_FOUND];
            }

            return FastParapheurController::upload([
                'config'        => $config,
                'resIdMaster'   => $args['resIdMaster'],
                'businessId'    => $signatory['business_id'],
                'circuitId'     => $user['user_id'],
                'label'         => $redactor['short_label']
            ]);
        }
    }

    /**
     * Recommandations minimales Fast à respecter
     * Espacement minimum de 30 minutes pour le même document
     * 
     * @param   $args:
     *  - documentId : 'externalid' of res_letterbox
     *  - config : FastParapheur configuration
     */
    public static function getDocumentHistory(array $args)
    {
        if (!Validator::notEmpty()->validate($args['documentId'])) {
            return ['errors' => 'documentId not found'];
        }
        if (!Validator::arrayType()->notEmpty()->validate($args['config'])) {
            return ['errors' => 'config is not an array'];
        }

        $curlReturn = CurlModel::exec([
            'url'           => $args['config']['data']['url'] . '/documents/v2/' . $args['documentId'] . '/history',
            'method'        => 'GET',
            'options'       => [
                CURLOPT_SSLCERT       => $args['config']['data']['certPath'],
                CURLOPT_SSLCERTPASSWD => $args['config']['data']['certPass'],
                CURLOPT_SSLCERTTYPE   => $args['config']['data']['certType']
            ]
        ]);

        if ($curlReturn['code'] == 404) {
            return ['code' => $curlReturn['code'], 'errors' => $curlReturn['raw']];
        } elseif (!empty($curlReturn['response']['developerMessage'])) {
            return ['code' => $curlReturn['code'], 'errors' => $curlReturn['response']['developerMessage']];
        }

        return ['response' => $curlReturn['response']];
    }

    public static function updateFetchHistoryDateByExternalId(array $args)
    {
        $tag = "[updateFetchHistoryDateByExternalId] ";
        if (!Validator::stringType()->notEmpty()->validate($args['type'])) {
            return ['errors' => $tag . 'type is not a string'];
        }
        if (!Validator::notEmpty()->validate($args['resId'])) {
            return ['errors' => $tag . 'resId is not found'];
        }

        if (!empty($args['type']) && $args['type'] == 'resource') {
            $resource = ResModel::get([
                'select' => ['res_id', 'external_id', 'external_state'],
                'where' => ["res_id = ?"],
                'data'  => [$args['resId']]
            ]);
            if (empty($resource)) {
                return ['errors' => $tag . 'Resource (' . $args['resId'] .') does not exist'];
            }
        } else {
            $resource = AttachmentModel::get([
                'select' => ['res_id', 'res_id_master', 'external_id', 'external_state'],
                'where' => ["res_id = ?"],
                'data'  => [$args['resId']]
            ]);
            if (empty($resource)) {
                return ['errors' => $tag . 'Attachment (' . $args['resId'] .') does not exist'];
            }
        }

        $resource = $resource[0];

        $externalId = json_decode($resource['external_id'], true);
        if (empty($externalId['signatureBookId'])) {
            return ['errors' => $tag . 'Resource is not linked to Fast Parapheur'];
        }

        $externalState = json_decode($resource['external_state'], true);

        $currentDate = new \DateTimeImmutable();
        $externalState['signatureBookWorkflow']['fetchDate'] = $currentDate->format('c');
        if (!empty($args['type']) && $args['type'] == 'resource') {
            ResModel::update([
                'where'   => ['res_id = ?'],
                'data'    => [$resource['res_id']],
                'postSet' => [
                    'external_state' => 'jsonb_set(external_state, \'{signatureBookWorkflow}\', \'' . json_encode($externalState['signatureBookWorkflow']) . '\'::jsonb)'
                ]
            ]);
        } else {
            AttachmentModel::update([
                'where'   => ['res_id = ?'],
                'data'    => [$resource['res_id']],
                'postSet' => [
                    'external_state' => 'jsonb_set(external_state, \'{signatureBookWorkflow}\', \'' . json_encode($externalState['signatureBookWorkflow']) . '\'::jsonb)'
                ]
            ]);
        }

        return true;
    }

    public static function getRefusalMessage(array $args)
    {
        $curlReturn = CurlModel::exec([
            'url'           => $args['config']['data']['url'] . '/documents/v2/' . $args['documentId'] . '/comments/refusal',
            'method'        => 'GET',
            'options'       => [
                CURLOPT_SSLCERT       => $args['config']['data']['certPath'],
                CURLOPT_SSLCERTPASSWD => $args['config']['data']['certPass'],
                CURLOPT_SSLCERTTYPE   => $args['config']['data']['certType']
            ]
        ]);

        $response = "";
        if (!empty($curlReturn['response']['developerMessage']) && $args['version'] == 'noVersion') {
            $attachmentName = AttachmentModel::getById(['select' => ['title'], 'id' => $args['res_id']]);
            $str = explode(':', $curlReturn['response']['developerMessage']);
            unset($str[0]);
            $response = _FOR_ATTACHMENT . " \"{$attachmentName['title']}\". " . implode('.', $str);

        } elseif (!empty($curlReturn['response']['developerMessage'])) {
            $str = explode(':', $curlReturn['response']['developerMessage']);
            unset($str[0]);
            $response = _FOR_MAIN_DOC . ". " . implode('.', $str);

        } elseif (!empty($curlReturn['response']['comment']) && $args['version'] == 'noVersion') {
            $attachmentName = AttachmentModel::getById(['select' => ['title'], 'id' => $args['res_id']]);
            $response = _FOR_ATTACHMENT . " \"{$attachmentName['title']}\". " . $curlReturn['response']['comment'];

        } elseif (!empty($curlReturn['response']['comment'])) {
            $response = _FOR_MAIN_DOC . ". " . $curlReturn['response']['comment'];
        }
        return $response;
    }

    public static function getUsers(array $args)
    {
        $subscriberId = $args['subscriberId'] ?? $args['config']['subscriberId'] ?? null;
        if (empty($subscriberId)) {
            return ['code' => 400, 'errors' => 'no subscriber id provided'];
        }
        $curlReturn = CurlModel::exec([
            'url'           => $args['config']['url'] . '/exportUsersData?siren=' . urlencode($subscriberId),
            'method'        => 'GET',
            'options'       => [
                CURLOPT_SSLCERT       => $args['config']['certPath'],
                CURLOPT_SSLCERTPASSWD => $args['config']['certPass'],
                CURLOPT_SSLCERTTYPE   => $args['config']['certType']
            ]
        ]);
        if (!empty($curlReturn['errors'])) {
            return ['code' => $curlReturn['code'], 'errors' =>  $curlReturn['errors']];
        } else if (empty($curlReturn['response']['users'])) {
            return [];
        }

        if (!empty($args['noFormat'])) {
            return $curlReturn['response']['users'];
        }

        $users = [];
        foreach ($curlReturn['response']['users'] as $user) {
            $users[] = [
                'idToDisplay' => trim($user['prenom'] . ' ' . $user['nom']),
                'email'       => trim($user['email'])
            ];
        }

        return $users;
    }

    public static function checkUserExistanceInFastParapheur(array $args)
    {
        ValidatorModel::notEmpty($args, ['fastParapheurUserEmail']);
        ValidatorModel::stringType($args, ['fastParapheurUserEmail']);

        $config = FastParapheurController::getConfig();
        if (!empty($config['errors'])) {
            return ['code' => $config['code'], 'errors' => $config['errors']];
        }

        $fpUsers = FastParapheurController::getUsers([
            'config' => [
                'subscriberId' => $config['subscriberId'],
                'url'          => $config['url'],
                'certPath'     => $config['certPath'],
                'certPass'     => $config['certPass'],
                'certType'     => $config['certType']
            ]
        ]);
        if (!empty($fpUsers['errors'])) {
            return ['code' => $fpUsers['code'], 'errors' => $fpUsers['errors']];
        } else if (empty($fpUsers)) {
            return ['code' => 400, 'errors' => "FastParapheur users not found!"];
        }
        $fpUsersEmails = array_values(array_unique(array_column($fpUsers, 'email')));

        if (!in_array($args['fastParapheurUserEmail'], $fpUsersEmails)) {
            return ['code' => 400, 'errors' => "FastParapheur user '{$args['fastParapheurUserEmail']}' not found!"];
        }

        return true;
    }

    public static function getConfig()
    {
        $loadedXml = CoreConfigModel::getXmlLoaded(['path' => 'modules/visa/xml/remoteSignatoryBooks.xml']);
        if (empty($loadedXml)) {
            return ['code' => 400, 'errors' => 'SignatoryBooks configuration file missing'];
        }
        
        $fastParapheurBlock = $loadedXml->xpath('//signatoryBook[id=\'fastParapheur\']')[0] ?? null;
        if (empty($fastParapheurBlock)) {
            return ['code' => 500, 'errors' => 'invalid configuration for FastParapheur'];
        }

        $fastParapheurBlock = json_decode(json_encode($fastParapheurBlock), true);
        if (empty($fastParapheurBlock)) {
            return ['code' => 500, 'errors' => 'invalid configuration for FastParapheur'];
        } elseif (!array_key_exists('workflowTypes', $fastParapheurBlock)) {
            return ['code' => 500, 'errors' => 'workflowTypes not found for FastParapheur'];
        } elseif (!array_key_exists('subscriberId', $fastParapheurBlock)) {
            return ['code' => 500, 'errors' => 'subscriberId not found for FastParapheur'];
        } elseif (!array_key_exists('url', $fastParapheurBlock)) {
            return ['code' => 500, 'errors' => 'url not found for FastParapheur'];
        } elseif (!array_key_exists('certPath', $fastParapheurBlock)) {
            return ['code' => 500, 'errors' => 'certPath not found for FastParapheur'];
        } elseif (!array_key_exists('certPass', $fastParapheurBlock)) {
            return ['code' => 500, 'errors' => 'certPass not found for FastParapheur'];
        } elseif (!array_key_exists('certType', $fastParapheurBlock)) {
            return ['code' => 500, 'errors' => 'certType not found for FastParapheur'];
        } elseif (!array_key_exists('validatedState', $fastParapheurBlock)) {
            return ['code' => 500, 'errors' => 'validatedState not found for FastParapheur'];
        } elseif (!array_key_exists('refusedState', $fastParapheurBlock)) {
            return ['code' => 500, 'errors' => 'refusedState not found for FastParapheur'];
        }

        if (!array_key_exists('integratedWorkflow', $fastParapheurBlock)) {
            $fastParapheurBlock['integratedWorkflow'] = 'false';
        }
        
        return $fastParapheurBlock;
    }

    public static function getSummarySheetFile(array $args)
    {
        ValidatorModel::notEmpty($args, ['docResId', 'login']);
        ValidatorModel::intVal($args, ['docResId']);

        $mainResource = ResModel::getOnView([
            'select' => ['*'],
            'where'  => ['res_id = ?'],
            'data'   => [$args['docResId']]
        ]);
        if (empty($mainResource)) {
            return ['error' => 'Mail does not exist'];
        }

        $units = [];
        $units[] = ['unit' => 'primaryInformations'];
        $units[] = ['unit' => 'secondaryInformations',       'label' => _SECONDARY_INFORMATION];
        $units[] = ['unit' => 'senderRecipientInformations', 'label' => _DEST_INFORMATION];
        $units[] = ['unit' => 'diffusionList',               'label' => _DIFFUSION_LIST];
        $units[] = ['unit' => 'visaWorkflow',                'label' => _VISA_WORKFLOW];
        $units[] = ['unit' => 'opinionWorkflow',             'label' => _AVIS_WORKFLOW];
        $units[] = ['unit' => 'notes',                       'label' => _NOTES_COMMENT];

        // Data for resources
        $tmpIds = [$mainResource[0]['res_id']];
        $data   = [];
        foreach ($units as $unit) {
            if ($unit['unit'] == 'notes') {
                $data['notes'] = NoteModel::get([
                    'select'   => ['id', 'note_text', 'user_id', 'creation_date', 'identifier'],
                    'where'    => ['identifier in (?)'],
                    'data'     => [$tmpIds],
                    'order_by' => ['identifier']]);

                $userEntities = EntityModel::getByUserId(['userId' => $GLOBALS['id'], 'select' => ['entity_id']]);
                $data['userEntities'] = [];
                foreach ($userEntities as $userEntity) {
                    $data['userEntities'][] = $userEntity['entity_id'];
                }
            } elseif ($unit['unit'] == 'opinionWorkflow') {
                $data['listInstancesOpinion'] = ListInstanceModel::get([
                    'select'    => ['item_id', 'process_date', 'res_id'],
                    'where'     => ['difflist_type = ?', 'res_id in (?)'],
                    'data'      => ['AVIS_CIRCUIT', $tmpIds],
                    'orderBy'   => ['listinstance_id']
                ]);
            } elseif ($unit['unit'] == 'visaWorkflow') {
                $data['listInstancesVisa'] = ListInstanceModel::get([
                    'select'    => ['item_id', 'requested_signature', 'process_date', 'res_id'],
                    'where'     => ['difflist_type = ?', 'res_id in (?)'],
                    'data'      => ['VISA_CIRCUIT', $tmpIds],
                    'orderBy'   => ['listinstance_id']
                ]);
            } elseif ($unit['unit'] == 'diffusionList') {
                $data['listInstances'] = ListInstanceModel::get([
                    'select'  => ['item_id', 'item_type', 'item_mode', 'res_id'],
                    'where'   => ['difflist_type = ?', 'res_id in (?)'],
                    'data'    => ['entity_id', $tmpIds],
                    'orderBy' => ['listinstance_id']
                ]);
            }
        }

        $modelId = ResModel::getById([
            'select' => ['model_id'],
            'resId'  => $mainResource[0]['res_id']
        ]);
        $indexingFields = IndexingModelFieldModel::get([
            'select' => ['identifier', 'unit'],
            'where'  => ['model_id = ?'],
            'data'   => [$modelId['model_id']]
        ]);
        $fieldsIdentifier = array_column($indexingFields, 'identifier');

        $pdf = new Fpdi('P', 'pt');
        $pdf->setPrintHeader(false);

        SummarySheetController::createSummarySheet($pdf, [
            'resource'         => $mainResource[0],
            'units'            => $units,
            'login'            => $args['login'],
            'data'             => $data,
            'fieldsIdentifier' => $fieldsIdentifier
        ]);

        $tmpPath = CoreConfigModel::getTmpPath();
        $summarySheetFilePath = $tmpPath . "summarySheet_" . $args['docResId'] . "_" . $args['login'] . "_" . rand() . ".pdf";
        $pdf->Output($summarySheetFilePath, 'F');

        return $summarySheetFilePath;
    }

    public static function getSignatureModes(array $args)
    {
        ValidatorModel::boolType($args, ['mapping']);

        $config = FastParapheurController::getConfig();
        if (!empty($config['errors'])) {
            return ['code' => $config['code'], 'errors' => $config['errors']];
        }

        if (empty($config['signatureModes']['mode'])) {
            return ['code' => 400, 'errors' => "signatureModes not found in config file"];
        }
        
        //map sign to signature or others
        $modes = $config['signatureModes']['mode'];
        if (!empty($args['mapping'])) {
            $modes = [];
            foreach ($config['signatureModes']['mode'] as $key => $value) {
                $value['id'] = FastParapheurController::getSignatureModeById(['signatureModeId' => $value['id']]);
                $modes[] = $value;
            }
        }

        return ['signatureModes' => $modes];
    }

    public static function getSignatureModeById(array $args)
    {
        ValidatorModel::notEmpty($args, ['signatureModeId']);
        ValidatorModel::stringType($args, ['signatureModeId']);

        $signatureModeId = null;
        switch ($args['signatureModeId']) {
            case 'sign':
                $signatureModeId = 'signature';
                break;

            case 'signature':
                $signatureModeId = 'sign';
                break;

            default:
                $signatureModeId = $args['signatureModeId'];
                break;
        }

        return $signatureModeId;
    }

/* STANDBY : We can't create tiles for FAST

    public static function getResourcesCount()
    {
        $resourcesInFastParapheur = ResModel::get([
            'select' => ['res_id', 'external_id->>\'signatureBookId\' as "signatureBookId"'],
            'where'  => ['external_id->>\'signatureBookId\' is not null']
        ]);

        $attachmentsInFastParapheur = AttachmentModel::get([
            'select' => ['res_id', 'external_id->>\'signatureBookId\' as "signatureBookId"'],
            'where'  => ['external_id->>\'signatureBookId\' is not null']
        ]);

        $documentsInDataBase = array_merge($resourcesInFastParapheur, $attachmentsInFastParapheur);
        $documentsInFastParapheur = FastParapheurController::getResources();
        if (!empty($documentsInFastParapheur['errors'])) {
            return ['code' => $documentsInFastParapheur['code'], 'errors' => $documentsInFastParapheur['errors']];
        }

        $resourcesNumber = 0;
        foreach ($documentsInDataBase as $document) {
            if (array_search($document['signatureBookId'], $documentsInFastParapheur['response'])) {
                $resourcesNumber++;
            }
        }

        return $resourcesNumber;
    }

    public static function getResourcesDetails() 
    {
        $loadedXml = CoreConfigModel::getXmlLoaded(['path' => 'modules/visa/xml/remoteSignatoryBooks.xml']);
        if (empty($loadedXml)) {
            return ['errors' => 'configuration file missing'];
        }

        $fastParapheurBlock = $loadedXml->xpath('//signatoryBook[id=\'fastParapheur\']')[0] ?? null;
        if (empty($fastParapheurBlock)) {
            return ['errors' => 'invalid configuration for FastParapheur'];
        }
        $fastParapheurUrl = (string)$fastParapheurBlock->url;
        $fastParapheurUrl = str_replace('/parapheur-ws/rest/v1', '', $fastParapheurUrl);

        $resourcesInFastParapheur = ResModel::get([
            'select' => [
                'external_id->>\'signatureBookId\' as "signatureBookId"',
                'subject', 'creation_date', 'res_id', 'category_id'
            ],
            'where'     => ['external_id->>\'signatureBookId\' is not null'],
            'orderBy'   => ['creation_date DESC']
        ]);

        $attachmentsInFastParapheur = AttachmentModel::get([
            'select' => [
                'external_id->>\'signatureBookId\' as "signatureBookId"',
                'title as subject', 'res_id', 'creation_date'
            ],
            'where'     => ['external_id->>\'signatureBookId\' is not null'],
            'orderBy'   => ['creation_date DESC']
        ]);
        $correspondents = null;
        $documentsInFastParapheur = FastParapheurController::getResources();
        if (!empty($documentsInFastParapheur['errors'])) {
            return ['code' => $documentsInFastParapheur['code'], 'errors' => $documentsInFastParapheur['errors']];
        }

        $documentsInDataBase = array_merge($resourcesInFastParapheur, $attachmentsInFastParapheur);
        foreach ($documentsInDataBase as $document) {
            if (!(array_search($document['signatureBookId'], $documentsInFastParapheur['response']))) {
                unset($documentsInDataBase[array_search($document, $documentsInDataBase)]);
            }
        }
        $documentsInDataBase = array_values(array_map(function ($doc) use ($fastParapheurUrl) {
            if ($doc['category_id'] == 'outgoing') {
                $correspondents = ContactController::getFormattedContacts(['resId' => $doc['res_id'], 'mode' => 'recipient', 'onlyContact' => true]);
            } else {
                $correspondents = ContactController::getFormattedContacts(['resId' => $doc['res_id'], 'mode' => 'sender', 'onlyContact' => true]);
            }
            return [
                'subject'           => $doc['subject'],
                'creationDate'      => $doc['creation_date'],
                'correspondents'    => $correspondents,
                'resId'             => (int)$doc['signatureBookId'],
                'url'               => $fastParapheurUrl . '/parapheur/showDoc.action?documentid=' . $doc['signatureBookId']
            ];
        }, $documentsInDataBase));

        return $documentsInDataBase;
    }

    public static function getResources()
    {
        $loadedXml = CoreConfigModel::getXmlLoaded(['path' => 'modules/visa/xml/remoteSignatoryBooks.xml']);
        if (empty($loadedXml)) {
            return ['code' => 400, 'errors' => 'SignatoryBooks configuration file missing'];
        }

        $fastParapheurBlock = $loadedXml->xpath('//signatoryBook[id=\'fastParapheur\']')[0] ?? null;
        if (empty($fastParapheurBlock)) {
            return ['code' => 500, 'errors' => 'invalid configuration for FastParapheur'];
        }
        $url = (string)$fastParapheurBlock->url;
        $certPath = (string)$fastParapheurBlock->certPath;
        $certPass = (string)$fastParapheurBlock->certPass;
        $certType = (string)$fastParapheurBlock->certType;
        $subscriberId = (string)$fastParapheurBlock->subscriberId;

        $curlReturn = CurlModel::exec([
            'url'       => $url . '/documents/search',
            'method'    => 'POST',
            'headers'   => [
                'Accept: application/json',
                'Content-Type: application/json'
            ],
            'options'   => [
                CURLOPT_SSLCERT       => $certPath,
                CURLOPT_SSLCERTPASSWD => $certPass,
                CURLOPT_SSLCERTTYPE   => $certType
            ],
            'body'      => json_encode([
                'siren'     => $subscriberId,
                'state'     => 'Prepared',
                'circuit'   => 'circuit-a-la-volee'
            ])
        ]);

        if ($curlReturn['code'] == 404) {
            return ['code' => 404, 'errors' => 'Erreur 404 : ' . $curlReturn['raw']];
        } elseif (!empty($curlReturn['errors'])) {
            return ['code' => $curlReturn['code'], 'errors' => $curlReturn['errors']];
        } elseif (!empty($curlReturn['response']['developerMessage'])) {
            return ['code' => $curlReturn['code'], 'errors' => $curlReturn['response']['developerMessage']];
        }

        return ['response' => $curlReturn['response']];
    }
*/

    public static function getLastFastWorkflowAction(array $documentHistory, array $knownWorkflow, array $config): array
    {
        ValidatorModel::notEmpty($config, ['validatedState', 'validatedVisaState', 'refusedState', 'refusedVisaState']);
        ValidatorModel::stringType($config, ['validatedState', 'validatedVisaState', 'refusedState', 'refusedVisaState']);

        if (empty($knownWorkflow) || empty($documentHistory)) {
            return [];
        }

        $totalStepsInWorkflow = count($knownWorkflow);
        $current = 0;
        $lastStep = [];

        foreach ($documentHistory as $historyStep) {
            if (!empty($knownWorkflow[$current]['id'])) {
                $historyStep['userFastId'] = $knownWorkflow[$current]['id'];
            }
            // If the document has been refused, then the workflow has ended and the last step is the refused step
            if (in_array($historyStep['stateName'], [$config['refusedState'], $config['refusedVisaState']])) {
                $lastStep = $historyStep;
                break;
            }

            // If the state is sign or an approved visa, the workflow is continuing
            if (in_array($historyStep['stateName'], [$config['validatedState'], $config['validatedVisaState']])) {
                $current++;

                // If we have as many steps in history as the workflow, then the workflow is over and the last step is the last sign/visa
                if ($current === $totalStepsInWorkflow) {
                    $lastStep = $historyStep;
                    break;
                }
            }
        }

        return $lastStep;
    }

    public static function generateOtpXml(array $args)
    {
        ValidatorModel::notEmpty($args, ['otpInfo']);
        ValidatorModel::arrayType($args, ['otpInfo']);
        ValidatorModel::boolType($args, ['prettyPrint']);

        $xmlData = null;
        try {
            $otpInfoXML = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8" standalone="yes"?> <meta-data-list></meta-data-list>');
            foreach ($args['otpInfo'] as $name => $value) {
                $metaDataElement = $otpInfoXML->addChild('meta-data');
                $metaDataElement->addAttribute('name', $name);
                $metaDataElement->addAttribute('value', $value);
            }

            $xmlData = $otpInfoXML->asXML();
            if (!empty($args['prettyPrint'])) {
                $dom = dom_import_simplexml($otpInfoXML)->ownerDocument;
                $dom->formatOutput = true;
                $xmlData = $dom->saveXML();
            }
        } catch (\Exception $e) {
            return ['errors' => '[FastParapheur][generateOtpXml] : ' . $e->getMessage()];
        }

        return ['content' => $xmlData];
    }
}
