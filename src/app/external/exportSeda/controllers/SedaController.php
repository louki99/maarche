<?php

/**
* Copyright Maarch since 2008 under licence GPLv3.
* See LICENCE.txt file at the root folder for more details.
* This file is part of Maarch software.
*
*/

/**
* @brief Seda Controller
* @author dev@maarch.org
*/

namespace ExportSeda\controllers;

use Configuration\models\ConfigurationModel;
use Convert\models\AdrModel;
use Docserver\models\DocserverModel;
use Docserver\models\DocserverTypeModel;
use Email\models\EmailModel;
use Folder\models\FolderModel;
use Group\controllers\PrivilegeController;
use History\controllers\HistoryController;
use Note\models\NoteModel;
use Resource\controllers\ResController;
use Resource\controllers\StoreController;
use Resource\models\ResModel;
use Respect\Validation\Validator;
use Slim\Psr7\Request;
use SrcCore\http\Response;
use SrcCore\models\CurlModel;
use User\models\UserModel;

class SedaController
{
    public static function initArchivalData($args = [])
    {
        $date = new \DateTime();

        $return = [
            'data' => [
                'entity' => [
                    'label'               => $args['entity']['entity_label'],
                    'producerService'     => $args['entity']['producer_service'],
                    'senderArchiveEntity' => $args['senderOrgRegNumber'],
                ],
                'doctype' => [
                    'label'                     => $args['doctype']['description'],
                    'retentionRule'             => $args['doctype']['retention_rule'],
                    'retentionFinalDisposition' => $args['doctype']['retention_final_disposition']
                ],
                'slipInfo' => [
                    'slipId'    => $GLOBALS['login'] . '-' . $date->format('Ymd-Hisu') . '-' . $args['resource']['res_id'],
                    'archiveId' => 'archive_' . $args['resource']['res_id']
                ]
            ],
            'archiveUnits'   => [],
            'additionalData' => ['folders' => [], 'linkedResources' => []]
        ];

        $document = $args['resource'];
        if (!empty($document['docserver_id']) && !empty($document['filename'])) {
            $convertedDocument = AdrModel::getDocuments([
                'select'    => ['docserver_id', 'path', 'filename', 'fingerprint'],
                'where'     => ['res_id = ?', 'type = ?', 'version = ?'],
                'data'      => [$args['resource']['res_id'], 'SIGN', $document['version']],
                'limit'     => 1
            ]);
            $document = $convertedDocument[0] ?? $document;

            $docserver = DocserverModel::getByDocserverId(['docserverId' => $document['docserver_id'], 'select' => ['path_template', 'docserver_type_id']]);
            if (empty($docserver['path_template']) || !file_exists($docserver['path_template'])) {
                return ['errors' => 'Docserver does not exist'];
            }

            $pathToDocument = $docserver['path_template'] . str_replace('#', DIRECTORY_SEPARATOR, $document['path']) . $document['filename'];
            if (!file_exists($pathToDocument)) {
                return ['errors' => 'Document not found on docserver'];
            }

            $docserverType = DocserverTypeModel::getById(['id' => $docserver['docserver_type_id'], 'select' => ['fingerprint_mode']]);
            $fingerprint = StoreController::getFingerPrint(['filePath' => $pathToDocument, 'mode' => $docserverType['fingerprint_mode']]);
            if (empty($convertedDocument) && empty($document['fingerprint'])) {
                ResModel::update(['set' => ['fingerprint' => $fingerprint], 'where' => ['res_id = ?'], 'data' => [$args['resource']['res_id']]]);
                $document['fingerprint'] = $fingerprint;
            }

            if ($document['fingerprint'] != $fingerprint) {
                return ['errors' => 'Fingerprints do not match'];
            }

            $fileContent = file_exists($pathToDocument);
            if ($fileContent === false) {
                return ['errors' => 'Document not found on docserver'];
            }

            $return['archiveUnits'][0] = [
                'id'               => 'letterbox_' . $args['resource']['res_id'],
                'label'            => $args['resource']['subject'],
                'type'             => 'mainDocument',
                'descriptionLevel' => 'File',
                'otherInfo'        => $args['resource']['alt_identifier']
            ];

            if ($args['getFile']) {
                $return['archiveUnits'][0]['filePath'] = $pathToDocument;
            }
        }

        $attachments = $args['attachments'];
        foreach ($attachments as $attachment) {
            $tmpAttachment = [
                'id'               => 'attachment_' . $attachment['res_id'],
                'label'            => $attachment['title'],
                'type'             => 'attachment',
                'descriptionLevel' => 'Item',
                'creationDate'     => $attachment['creation_date'],
                'otherInfo'        => $attachment['identifier']
            ];
            if ($args['getFile']) {
                $attachment = ExportSEDATrait::getAttachmentFilePath(['data' => $attachment]);
                $tmpAttachment['filePath'] = $attachment['filePath'];
            }
            $return['archiveUnits'][] = $tmpAttachment;
        }

        $notes = NoteModel::get(['select' => ['note_text', 'id', 'creation_date'], 'where' => ['identifier = ?'], 'data' => [$args['resource']['res_id']]]);
        foreach ($notes as $note) {
            $tmpNote = [
                'id'               => 'note_' . $note['id'],
                'label'            => $note['note_text'],
                'type'             => 'note',
                'descriptionLevel' => 'Item',
                'creationDate'     => $note['creation_date'],
                'otherInfo'        => null
            ];
            if ($args['getFile']) {
                $note = ExportSEDATrait::getNoteFilePath(['id' => $note['id']]);
                $tmpNote['filePath'] = $note['filePath'];
            }
            $return['archiveUnits'][] = $tmpNote;
        }

        $emails = EmailModel::get([
            'select'  => ['object', 'id', 'body', 'sender', 'recipients', 'creation_date'],
            'where'   => ['document->>\'id\' = ?', 'status = ?'],
            'data'    => [$args['resource']['res_id'], 'SENT'],
            'orderBy' => ['send_date desc']
        ]);
        foreach ($emails as $email) {
            $tmpEmail = [
                'id'               => 'email_' . $email['id'],
                'label'            => $email['object'],
                'type'             => 'email',
                'descriptionLevel' => 'Item',
                'creationDate'     => $email['creation_date'],
                'otherInfo'        => null
            ];
            if ($args['getFile']) {
                $email = ExportSEDATrait::getEmailFilePath(['data' => $email]);
                $tmpEmail['filePath'] = $email['filePath'];
            }
            $return['archiveUnits'][] = $tmpEmail;
        }

        $tmpSummarySheet = [
            'id'               => 'summarySheet_' . $args['resource']['res_id'],
            'label'            => 'Fiche de liaison',
            'type'             => 'summarySheet',
            'descriptionLevel' => 'Item',
            'creationDate'     => $date->format('Y-m-d H:i:s'),
            'otherInfo'        => null
        ];
        if ($args['getFile']) {
            $summarySheet = ExportSEDATrait::getSummarySheetFilePath(['resId' => $args['resource']['res_id']]);
            $tmpSummarySheet['filePath'] = $summarySheet['filePath'];
        }
        $return['archiveUnits'][] = $tmpSummarySheet;

        $linkedResourcesIds = json_decode($args['resource']['linked_resources'], true);
        if (!empty($linkedResourcesIds)) {
            $linkedResources = [];
            $linkedResources = ResModel::get([
                'select' => ['subject as object', 'alt_identifier as chrono'],
                'where'  => ['res_id in (?)'],
                'data'   => [$linkedResourcesIds]
            ]);
            $return['additionalData']['linkedResources'] = $linkedResources;
        }

        $entities = UserModel::getEntitiesById(['id' => $GLOBALS['id'], 'select' => ['entities.id']]);
        $entities = array_column($entities, 'id');

        if (empty($entities)) {
            $entities = [0];
        }

        $folderLimit = $args['massAction'] ? 1 : 0;
        $folders = FolderModel::getWithEntitiesAndResources([
            'select'  => ['DISTINCT(folders.id)', 'folders.label'],
            'where'   => ['res_id = ?', '(entity_id in (?) OR keyword = ?)', 'folders.public = TRUE'],
            'data'    => [$args['resource']['res_id'], $entities, 'ALL_ENTITIES'],
            'orderBy' => ['folders.label'],
            'limit'   => $folderLimit
        ]);
        foreach ($folders as $folder) {
            $return['additionalData']['folders'][] = [
                'id'    => 'folder_' . $folder['id'],
                'label' => $folder['label']
            ];
        }

        return ['archivalData' => $return];
    }

    public static function getRecipientArchiveEntities($args = [])
    {
        $archiveEntities = [];
        if (strtolower($args['config']['exportSeda']['sae']) == 'maarchrm') {
            $curlResponse = CurlModel::exec([
                'url'     => rtrim($args['config']['exportSeda']['urlSAEService'], '/') . '/organization/organization/Byrole/archiver',
                'method'  => 'GET',
                'cookie'  => 'LAABS-AUTH=' . urlencode($args['config']['exportSeda']['token']),
                'headers' => [
                    'Accept: application/json',
                    'Content-Type: application/json',
                    'User-Agent: ' . $args['config']['exportSeda']['userAgent']
                ]
            ]);

            if (!empty($curlResponse['errors'])) {
                return ['errors' => 'Error returned by the route /organization/organization/Byrole/archiver : ' . $curlResponse['errors']];
            } elseif ($curlResponse['code'] != 200) {
                return ['errors' => 'Error returned by the route /organization/organization/Byrole/archiver : ' . $curlResponse['response']['message']];
            }

            $archiveEntitiesAllowed = array_column($args['archivalAgreements'], 'archiveEntityRegNumber');

            $archiveEntities[] = [
                'id'    => "",
                'label' => null
            ];
            foreach ($curlResponse['response'] as $retentionRule) {
                if (in_array($retentionRule['registrationNumber'], $archiveEntitiesAllowed)) {
                    $archiveEntities[] = [
                        'id'    => $retentionRule['registrationNumber'],
                        'label' => $retentionRule['displayName']
                    ];
                }
            }
        } else {
            if (is_array($args['config']['exportSeda']['externalSAE']['archiveEntities'])) {
                foreach ($args['config']['exportSeda']['externalSAE']['archiveEntities'] as $archiveEntity) {
                    $archiveEntities[] = [
                            'id'    => $archiveEntity['id'],
                            'label' => $archiveEntity['label']
                        ];
                }
            }
        }

        return ['archiveEntities' => $archiveEntities];
    }

    public static function getArchivalAgreements($args = [])
    {
        $archivalAgreements = [];
        if (strtolower($args['config']['exportSeda']['sae']) == 'maarchrm') {
            $curlResponse = CurlModel::exec([
                'url'     => rtrim($args['config']['exportSeda']['urlSAEService'], '/') . '/medona/archivalAgreement/Index',
                'method'  => 'GET',
                'cookie'  => 'LAABS-AUTH=' . urlencode($args['config']['exportSeda']['token']),
                'headers' => [
                    'Accept: application/json',
                    'Content-Type: application/json',
                    'User-Agent: ' . $args['config']['exportSeda']['userAgent']
                ]
            ]);

            if (!empty($curlResponse['errors'])) {
                return ['errors' => 'Error returned by the route /medona/archivalAgreement/Index : ' . $curlResponse['errors']];
            } elseif ($curlResponse['code'] != 200) {
                return ['errors' => 'Error returned by the route /medona/archivalAgreement/Index : ' . $curlResponse['response']['message']];
            }

            $producerService = SedaController::getProducerServiceInfo(['config' => $args['config'], 'producerServiceName' => $args['producerService']]);
            if (!empty($producerService['errors'])) {
                return ['errors' => $curlResponse['errors']];
            } elseif (empty($producerService['producerServiceInfo'])) {
                return ['errors' => 'ProducerService does not exists in MaarchRM', 'lang' => 'producerServiceDoesNotExists'];
            }

            $archivalAgreements[] = [
                'id'    => "",
                'label' => null
            ];
            foreach ($curlResponse['response'] as $retentionRule) {
                if ($retentionRule['depositorOrgRegNumber'] == $args['senderArchiveEntity'] && in_array($producerService['producerServiceInfo']['orgId'], $retentionRule['originatorOrgIds'])) {
                    $archivalAgreements[] = [
                        'id'            => $retentionRule['reference'],
                        'label'         => $retentionRule['name'],
                        'archiveEntityRegNumber' => $retentionRule['archiverOrgRegNumber']
                    ];
                }
            }
        } else {
            if (is_array($args['config']['exportSeda']['externalSAE']['archivalAgreements'])) {
                foreach ($args['config']['exportSeda']['externalSAE']['archivalAgreements'] as $archivalAgreement) {
                    $archivalAgreements[] = [
                        'id'    => $archivalAgreement['id'],
                        'label' => $archivalAgreement['label']
                    ];
                }
            }
        }

        return ['archivalAgreements' => $archivalAgreements];
    }

    public static function getProducerServiceInfo($args = [])
    {
        $curlResponse = CurlModel::exec([
            'url'     => rtrim($args['config']['exportSeda']['urlSAEService'], '/') . '/organization/organization/Search?term=' . $args['producerServiceName'],
            'method'  => 'GET',
            'cookie'  => 'LAABS-AUTH=' . urlencode($args['config']['exportSeda']['token']),
            'headers' => [
                'Accept: application/json',
                'Content-Type: application/json',
                'User-Agent: ' . $args['config']['exportSeda']['userAgent']
            ]
        ]);

        if (!empty($curlResponse['errors'])) {
            return ['errors' => 'Error returned by the route /organization/organization/Search : ' . $curlResponse['errors']];
        } elseif ($curlResponse['code'] != 200) {
            return ['errors' => 'Error returned by the route /organization/organization/Search : ' . $curlResponse['response']['message']];
        }

        foreach ($curlResponse['response'] as $organization) {
            if ($organization['registrationNumber'] == $args['producerServiceName']) {
                return ['producerServiceInfo' => $organization];
            }
        }

        return ['producerServiceInfo' => null];
    }

    public function getRetentionRules(Request $request, Response $response)
    {
        if (!PrivilegeController::hasPrivilege(['privilegeId' => 'admin_architecture', 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }

        $retentionRules = [];

        $config = ConfigurationModel::getByPrivilege(['privilege' => 'admin_export_seda']);
        $config = !empty($config['value']) ? json_decode($config['value'], true) : [];
        if (empty($config['sae'])) {
            return $response->withJson(['retentionRules' => $retentionRules]);
        }

        if (strtolower($config['sae']) == 'maarchrm') {
            $curlResponse = CurlModel::exec([
                'url'     => rtrim($config['urlSAEService'], '/') . '/recordsManagement/retentionRule/Index',
                'method'  => 'GET',
                'cookie'  => 'LAABS-AUTH=' . urlencode($config['token']),
                'headers' => [
                    'Accept: application/json',
                    'Content-Type: application/json',
                    'User-Agent: ' . $config['userAgent']
                ]
            ]);

            if (!empty($curlResponse['errors'])) {
                return $response->withStatus(400)->withJson(['errors' => 'Error returned by the route /recordsManagement/retentionRule/Index : ' . $curlResponse['errors']]);
            } elseif ($curlResponse['code'] != 200) {
                return $response->withStatus(400)->withJson(['errors' => 'Error returned by the route /recordsManagement/retentionRule/Index : ' . $curlResponse['response']['message']]);
            }

            $retentionRules[] = [
                'id'    => "",
                'label' => null
            ];
            foreach ($curlResponse['response'] as $retentionRule) {
                $retentionRules[] = [
                    'id'    => $retentionRule['code'],
                    'label' => $retentionRule['label']
                ];
            }
        } else {
            if (is_array($config['externalSAE']['retentionRules'])) {
                foreach ($config['externalSAE']['retentionRules'] as $rule) {
                    $retentionRules[] = [
                        'id'    => $rule['id'],
                        'label' => $rule['label']
                    ];
                }
            }
        }

        return $response->withJson(['retentionRules' => $retentionRules]);
    }

    public function setBindingDocument(Request $request, Response $response)
    {
        if (!PrivilegeController::hasPrivilege(['privilegeId' => 'set_binding_document', 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }

        $body = $request->getParsedBody();

        if (!Validator::arrayType()->notEmpty()->validate($body['resources'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body resources is empty or not an array']);
        }
        if ($body['binding'] !== null && !Validator::boolType()->validate($body['binding'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body binding is not a boolean']);
        }

        $body['resources'] = array_slice($body['resources'], 0, 500);
        if (!ResController::hasRightByResId(['resId' => $body['resources'], 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Document out of perimeter']);
        }

        $documents = ResModel::get([
            'select' => ['alt_identifier', 'res_id'],
            'where'  => ['res_id in (?)'],
            'data'   => [$body['resources']]
        ]);
        $documents = array_column($documents, 'alt_identifier', 'res_id');

        if ($body['binding'] === null) {
            $binding = null;
            $info    = _RESET_BINDING_DOCUMENT;
        } else {
            $binding = $body['binding'] ? 'true' : 'false';
            $info    = $body['binding'] ? _SET_BINDING_DOCUMENT : _SET_NON_BINDING_DOCUMENT;
        }

        ResModel::update([
            'set'   => [
                'binding' => $binding,
            ],
            'where' => ['res_id in (?)'],
            'data'  => [$body['resources']]
        ]);

        foreach ($body['resources'] as $resId) {
            HistoryController::add([
                'tableName' => 'res_letterbox',
                'recordId'  => $resId,
                'eventType' => 'UP',
                'info'      => $info . " : " . $documents[$resId],
                'moduleId'  => 'resource',
                'eventId'   => 'resourceModification',
            ]);
        }

        return $response->withStatus(204);
    }

    public function freezeRetentionRule(Request $request, Response $response)
    {
        if (!PrivilegeController::hasPrivilege(['privilegeId' => 'freeze_retention_rule', 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }

        $body = $request->getParsedBody();

        if (!Validator::arrayType()->notEmpty()->validate($body['resources'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body resources is empty or not an array']);
        }
        if (!Validator::boolType()->validate($body['freeze'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body freeze is not a boolean']);
        }

        $body['resources'] = array_slice($body['resources'], 0, 500);
        if (!ResController::hasRightByResId(['resId' => $body['resources'], 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Document out of perimeter']);
        }

        $documents = ResModel::get([
            'select' => ['alt_identifier', 'res_id'],
            'where'  => ['res_id in (?)'],
            'data'   => [$body['resources']]
        ]);
        $documents = array_column($documents, 'alt_identifier', 'res_id');

        $freeze = $body['freeze'] ? 'true' : 'false';
        $info   = $body['freeze'] ? _FREEZE_RETENTION_RULE : _UNFREEZE_RETENTION_RULE;

        ResModel::update([
            'set'   => [
                'retention_frozen' => $freeze,
            ],
            'where' => ['res_id in (?)'],
            'data'  => [$body['resources']]
        ]);

        foreach ($body['resources'] as $resId) {
            HistoryController::add([
                'tableName' => 'res_letterbox',
                'recordId'  => $resId,
                'eventType' => 'UP',
                'info'      => $info . " : " . $documents[$resId],
                'moduleId'  => 'resource',
                'eventId'   => 'resourceModification',
            ]);
        }

        return $response->withStatus(204);
    }
}
