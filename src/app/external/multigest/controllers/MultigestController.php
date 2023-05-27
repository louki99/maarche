<?php

/**
 * Copyright Maarch since 2008 under licence GPLv3.
 * See LICENCE.txt file at the root folder for more details.
 * This file is part of Maarch software.
 *
 */

/**
 * @brief   Multigest Controller
 * @author  dev@maarch.org
 */

namespace Multigest\controllers;

use Attachment\models\AttachmentModel;
use Attachment\models\AttachmentTypeModel;
use Configuration\models\ConfigurationModel;
use Contact\controllers\ContactCivilityController;
use Contact\controllers\ContactController;
use Contact\models\ContactModel;
use Convert\models\AdrModel;
use Docserver\models\DocserverModel;
use Doctype\models\DoctypeModel;
use Doctype\models\SecondLevelModel;
use Entity\models\EntityModel;
use Group\controllers\PrivilegeController;
use Priority\models\PriorityModel;
use Resource\models\ResModel;
use Resource\models\ResourceContactModel;
use Respect\Validation\Validator;
use Slim\Psr7\Request;
use SrcCore\http\Response;
use SrcCore\models\PasswordModel;
use SrcCore\models\ValidatorModel;
use SrcCore\models\CoreConfigModel;
use User\models\UserModel;

class MultigestController
{
    public function getConfiguration(Request $request, Response $response)
    {
        if (!PrivilegeController::hasPrivilege(['privilegeId' => 'admin_multigest', 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }

        $configuration = ConfigurationModel::getByPrivilege(['privilege' => 'admin_multigest']);
        if (empty($configuration)) {
            return $response->withJson(['configuration' => null]);
        }

        $configuration = json_decode($configuration['value'], true);

        return $response->withJson(['configuration' => $configuration]);
    }

    public function updateConfiguration(Request $request, Response $response)
    {
        if (!PrivilegeController::hasPrivilege(['privilegeId' => 'admin_multigest', 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }

        $body = $request->getParsedBody();

        if (!Validator::stringType()->notEmpty()->validate($body['uri'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body uri is empty or not a string']);
        }

        $value = json_encode([
            'uri' => trim($body['uri'])
        ]);

        $configuration = ConfigurationModel::getByPrivilege(['privilege' => 'admin_multigest']);
        if (empty($configuration)) {
            ConfigurationModel::create(['privilege' => 'admin_multigest', 'value' => $value]);
        } else {
            ConfigurationModel::update(['set' => ['value' => $value], 'where' => ['privilege = ?'], 'data' => ['admin_multigest']]);
        }

        return $response->withStatus(204);
    }

    public function getAccounts(Request $request, Response $response)
    {
        if (!PrivilegeController::hasPrivilege(['privilegeId' => 'admin_multigest', 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }

        $entities = EntityModel::get(['select' => ['external_id', 'short_label'], 'where' => ["external_id->>'multigest' is not null"]]);

        $accounts = [];
        $alreadyAdded = [];
        foreach ($entities as $entity) {
            $externalId = json_decode($entity['external_id'], true);
            if (!in_array($externalId['multigest']['id'], $alreadyAdded)) {
                $accounts[] = [
                    'id'            => $externalId['multigest']['id'],
                    'label'         => $externalId['multigest']['label'],
                    'login'         => $externalId['multigest']['login'],
                    'sasId'         => $externalId['multigest']['sasId'],
                    'entitiesLabel' => [$entity['short_label']]
                ];
                $alreadyAdded[] = $externalId['multigest']['id'];
            } else {
                foreach ($accounts as $key => $value) {
                    if ($value['id'] == $externalId['multigest']['id']) {
                        $accounts[$key]['entitiesLabel'][] = $entity['short_label'];
                    }
                }
            }
        }

        return $response->withJson(['accounts' => $accounts]);
    }

    public function getAvailableEntities(Request $request, Response $response)
    {
        if (!PrivilegeController::hasPrivilege(['privilegeId' => 'admin_multigest', 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }

        $entities = EntityModel::get(['select' => ['id'], 'where' => ["external_id->>'multigest' is null"]]);

        $availableEntities = array_column($entities, 'id');

        return $response->withJson(['availableEntities' => $availableEntities]);
    }

    public function createAccount(Request $request, Response $response)
    {
        if (!PrivilegeController::hasPrivilege(['privilegeId' => 'admin_multigest', 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }

        $body = $request->getParsedBody();

        if (!Validator::stringType()->notEmpty()->validate($body['label'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body label is empty or not a string']);
        } elseif (!Validator::stringType()->notEmpty()->validate($body['login'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body login is empty or not a string']);
        } elseif (!Validator::stringType()->notEmpty()->validate($body['password'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body password is empty or not a string']);
        } elseif (!Validator::stringType()->notEmpty()->validate($body['sasId'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body sasId is empty or not a string']);
        } elseif (!Validator::arrayType()->notEmpty()->validate($body['entities'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body entities is empty or not an array']);
        }

        foreach ($body['entities'] as $entity) {
            if (!Validator::notEmpty()->intVal()->validate($entity)) {
                return $response->withStatus(400)->withJson(['errors' => 'Body entities contains non integer values']);
            }
        }
        $entities = EntityModel::get(['select' => ['id'], 'where' => ['id in (?)'], 'data' => [$body['entities']]]);
        if (count($entities) != count($body['entities'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Some entities do not exist']);
        }

        $id = CoreConfigModel::uniqueId();
        $account = [
            'id'       => $id,
            'label'    => $body['label'],
            'login'    => $body['login'],
            'password' => PasswordModel::encrypt(['password' => $body['password']]),
            'sasId'    => $body['sasId']
        ];
        $account = json_encode($account);

        EntityModel::update([
            'postSet' => ['external_id' => "jsonb_set(coalesce(external_id, '{}'::jsonb), '{multigest}', '{$account}')"],
            'where'   => ['id in (?)'],
            'data'    => [$body['entities']]
        ]);

        return $response->withStatus(204);
    }

    public function getAccountById(Request $request, Response $response, array $args)
    {
        if (!PrivilegeController::hasPrivilege(['privilegeId' => 'admin_multigest', 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }

        $entities = EntityModel::get(['select' => ['external_id', 'id'], 'where' => ["external_id->'multigest'->>'id' = ?"], 'data' => [$args['id']]]);
        if (empty($entities[0])) {
            return $response->withStatus(400)->withJson(['errors' => 'Account not found']);
        }

        $externalId = json_decode($entities[0]['external_id'], true);
        $account = [
            'id'        => $externalId['multigest']['id'],
            'label'     => $externalId['multigest']['label'],
            'login'     => $externalId['multigest']['login'],
            'sasId'     => $externalId['multigest']['sasId'],
            'entities'  => array_column($entities, 'id')
        ];

        return $response->withJson($account);
    }

    public function updateAccount(Request $request, Response $response, array $args)
    {
        if (!PrivilegeController::hasPrivilege(['privilegeId' => 'admin_multigest', 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }

        $body = $request->getParsedBody();

        if (!Validator::stringType()->notEmpty()->validate($body['label'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body label is empty or not a string']);
        } elseif (!Validator::stringType()->notEmpty()->validate($body['login'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body login is empty or not a string']);
        } elseif (!Validator::stringType()->notEmpty()->validate($body['sasId'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body sasId is empty or not a string']);
        } elseif (!Validator::arrayType()->notEmpty()->validate($body['entities'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body entities is empty or not an array']);
        }

        $accounts = EntityModel::get(['select' => ['external_id', 'id'], 'where' => ["external_id->'multigest'->>'id' = ?"], 'data' => [$args['id']]]);
        if (empty($accounts[0])) {
            return $response->withStatus(400)->withJson(['errors' => 'Account not found']);
        }

        foreach ($body['entities'] as $entity) {
            if (!Validator::notEmpty()->intVal()->validate($entity)) {
                return $response->withStatus(400)->withJson(['errors' => 'Body entities contains non integer values']);
            }
        }
        $entities = EntityModel::get(['select' => ['id'], 'where' => ['id in (?)'], 'data' => [$body['entities']]]);
        if (count($entities) != count($body['entities'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Some entities do not exist']);
        }

        $externalId = json_decode($accounts[0]['external_id'], true);
        $account = [
            'id'        => $args['id'],
            'label'     => $body['label'],
            'login'     => $body['login'],
            'password'  => empty($body['password']) ? $externalId['multigest']['password'] : PasswordModel::encrypt(['password' => $body['password']]),
            'sasId'     => $body['sasId']
        ];
        $account = json_encode($account);

        EntityModel::update([
            'set'   => ['external_id' => "{}"],
            'where' => ['id in (?)', 'external_id = ?'],
            'data'  => [$body['entities'], 'null']
        ]);

        EntityModel::update([
            'postSet'   => ['external_id' => "jsonb_set(coalesce(external_id, '{}'::jsonb), '{multigest}', '{$account}')"],
            'where'     => ['id in (?)'],
            'data'      => [$body['entities']]
        ]);

        $previousEntities = array_column($accounts, 'id');
        $entitiesToRemove = array_diff($previousEntities, $body['entities']);
        if (!empty($entitiesToRemove)) {
            EntityModel::update([
                'postSet'   => ['external_id' => "external_id - 'multigest'"],
                'where'     => ['id in (?)'],
                'data'      => [$entitiesToRemove]
            ]);
        }

        return $response->withStatus(204);
    }

    public function deleteAccount(Request $request, Response $response, array $args)
    {
        if (!PrivilegeController::hasPrivilege(['privilegeId' => 'admin_multigest', 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }

        $accounts = EntityModel::get(['select' => ['external_id', 'id'], 'where' => ["external_id->'multigest'->>'id' = ?"], 'data' => [$args['id']]]);
        if (empty($accounts[0])) {
            return $response->withStatus(400)->withJson(['errors' => 'Account not found']);
        }

        $entitiesToRemove = array_column($accounts, 'id');
        EntityModel::update([
            'postSet'   => ['external_id' => "external_id - 'multigest'"],
            'where'     => ['id in (?)'],
            'data'      => [$entitiesToRemove]
        ]);

        return $response->withStatus(204);
    }

    public function checkAccount(Request $request, Response $response)
    {
        if (!PrivilegeController::hasPrivilege(['privilegeId' => 'admin_multigest', 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }

        $body = $request->getParsedBody();
        if (!Validator::stringType()->notEmpty()->validate($body['sasId'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body sasId is empty or not a string']);
        } elseif (!Validator::stringType()->notEmpty()->validate($body['login'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body login is empty or not a string']);
        } elseif (!empty($body['password']) && !Validator::stringType()->validate($body['password'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body password is not a string']);
        }

        $result = MultigestController::checkAccountWithCredentials([
            'sasId' => $body['sasId'],
            'login' => $body['login'],
            'password' => empty($body['password']) ? '' : PasswordModel::decrypt(['cryptedPassword' => $body['password']])
        ]);

        if (!empty($result['errors'])) {
            $return = ['errors' => $result['errors']];
            if (!empty($result['lang'])) {
                $return['lang'] = $result['lang'];
            }
            return $response->withStatus(400)->withJson($return);
        }

        return $response->withStatus(204);
    }

    public static function checkAccountWithCredentials(array $args)
    {
        ValidatorModel::stringType($args, ['sasId', 'login', 'password']);
        ValidatorModel::notEmpty($args, ['sasId', 'login']);

        $configuration = ConfigurationModel::getByPrivilege(['privilege' => 'admin_multigest']);
        if (empty($configuration)) {
            return ['errors' => 'Multigest configuration is not enabled', 'lang' => 'multigestIsNotEnabled'];
        }
        $configuration = json_decode($configuration['value'], true);
        if (empty($configuration['uri'])) {
            return ['errors' => 'Multigest configuration URI is empty', 'lang' => 'multigestUriIsEmpty'];
        }
        $multigestUri = rtrim($configuration['uri'], '/');

        try {
            $soapClient = new \SoapClient($multigestUri, [
                'login'          => $args['login'],
                'password'       => $args['password'] ?? '',
                'authentication' => SOAP_AUTHENTICATION_BASIC
            ]);
        } catch (\SoapFault $e) {
            return ['errors' => (string) $e, 'lang' => 'soapClientCreationError'];
        }

        $multigestParameters = CoreConfigModel::getJsonLoaded(['path' => 'config/multigest.json']);
        if (empty($multigestParameters)) {
            return ['errors' => 'Multigest mapping file does not exist', 'lang' => 'multigestMappingDoesNotExist'];
        }
        if (empty($multigestParameters['mapping']['document'])) {
            return ['errors' => 'Multigest mapping for document is empty', 'lang' => 'multigestMappingForDocumentIsEmpty'];
        }

        $keyMetadataField = key($multigestParameters['mapping']['document']);
        $result = $soapClient->GedChampReset();
        if (is_soap_fault($result)) {
            return ['errors' => 'MultiGest SoapFault: ' . $result];
        }
        $result = $soapClient->GedAddChampRecherche($keyMetadataField, 'FAKEDATA');
        if (is_soap_fault($result)) {
            return ['errors' => 'MultiGest SoapFault: ' . $result];
        }
        $result = $soapClient->GedDossierExist($args['sasId'], $args['login']);
        if (is_soap_fault($result)) {
            return ['errors' => 'MultiGest SoapFault: ' . $result];
        }
        $result = (int) $result;

        if ($result < 0 && $result != -7) { // -7 -> "Dossier inexistant"
            return ['errors' => 'MultiGest connection failed for user ' . $args['login'] . ' in sas ' . $args['sasId'], 'lang' => 'multigestAccountTestFailed'];
        }

        return true;
    }

    public static function sendResource(array $args)
    {
        ValidatorModel::notEmpty($args, ['resId', 'userId']);
        ValidatorModel::intVal($args, ['resId', 'userId']);

        $configuration = ConfigurationModel::getByPrivilege(['privilege' => 'admin_multigest']);
        if (empty($configuration)) {
            return ['errors' => 'Multigest configuration is not enabled', 'lang' => 'multigestIsNotEnabled'];
        }

        $configuration = json_decode($configuration['value'], true);
        if (empty($configuration['uri'])) {
            return ['errors' => 'Multigest configuration URI is empty', 'lang' => 'multigestUriIsEmpty'];
        }
        $multigestUri = rtrim($configuration['uri'], '/');

        $userPrimaryEntity = UserModel::getPrimaryEntityById([
            'id'     => $args['userId'],
            'select' => ['entities.id', 'entities.external_id']
        ]);

        if (empty($userPrimaryEntity)) {
            return ['errors' => 'User has no primary entity'];
        }
        $entityConfiguration = json_decode($userPrimaryEntity['external_id'], true);
        if (empty($entityConfiguration['multigest'])) {
            return ['errors' => 'Entity has no associated Multigest account', 'lang' => 'noMultigestAccount'];
        }
        $entityConfiguration = [
            'login' => $entityConfiguration['multigest']['login'] ?? '',
            'sasId' => $entityConfiguration['multigest']['sasId'] ?? ''
        ];

        $document = ResModel::getById([
            'select' => [
                'filename', 'subject', 'alt_identifier', 'external_id', 'type_id', 'priority', 'fingerprint', 'custom_fields', 'dest_user',
                'creation_date', 'modification_date', 'doc_date', 'admission_date',
                'destination', 'initiator', 'process_limit_date', 'closing_date', 'docserver_id', 'path', 'filename'
            ],
            'resId'  => $args['resId']
        ]);

        $convertedDocument = AdrModel::getDocuments([
            'select'    => ['docserver_id', 'path', 'filename', 'fingerprint'],
            'where'     => ['res_id = ?', 'type = ?', 'version = ?'],
            'data'      => [$args['resId'], 'SIGN', $document['version']],
            'orderBy'   => ['version DESC'],
            'limit'     => 1
        ]);
        if (!empty($convertedDocument[0])) {
            $document['docserver_id'] = $convertedDocument['docserver_id'];
            $document['path']         = $convertedDocument['path'];
            $document['filename']     = $convertedDocument['filename'];
            $document['fingerprint']  = $convertedDocument['fingerprint'];
        }

        if (empty($document)) {
            return ['errors' => 'Document does not exist', 'lang' => 'documentNotExist'];
        } elseif (empty($document['filename'])) {
            return ['errors' => 'Document has no file', 'lang' => 'documentHasNoFile'];
        } elseif (empty($document['alt_identifier'])) {
            return ['errors' => 'Document has no chrono', 'lang' => 'documentHasNoChrono'];
        }
        $document['subject'] = str_replace([':', '*', '\'', '"', '>', '<'], ' ', $document['subject']);

        $docserver = DocserverModel::getByDocserverId(['docserverId' => $document['docserver_id'], 'select' => ['path_template', 'docserver_type_id']]);
        if (empty($docserver['path_template']) || !file_exists($docserver['path_template'])) {
            return ['errors' => 'Docserver does not exist', 'lang' => 'docserverDoesNotExists'];
        }

        $pathToDocument = $docserver['path_template'] . str_replace('#', DIRECTORY_SEPARATOR, $document['path']) . $document['filename'];
        if (!is_file($pathToDocument)) {
            return ['errors' => 'Document not found on docserver', 'lang' => 'documentNotFoundOnDocserver'];
        }

        $fileContent = file_get_contents($pathToDocument);
        if ($fileContent === false) {
            return ['errors' => 'Could not read file from docserver', 'lang' => 'docserverDocumentNotReadable'];
        }

        $fileExtension = explode('.', $document['filename']);
        $fileExtension = array_pop($fileExtension);

        $multigestParameters = CoreConfigModel::getJsonLoaded(['path' => 'config/multigest.json']);
        if (empty($multigestParameters)) {
            return ['errors' => 'Multigest mapping file does not exist', 'lang' => 'multigestMappingDoesNotExist'];
        }
        if (empty($multigestParameters['mapping']['document'])) {
            return ['errors' => 'Multigest mapping for document is empty', 'lang' => 'multigestMappingForDocumentIsEmpty'];
        }

        $resourceContacts = ResourceContactModel::get([
            'where'     => ['res_id = ?', 'mode = ?'],
            'data'      => [$args['resId'], 'sender']
        ]);
        $rawContacts = [];
        foreach ($resourceContacts as $resourceContact) {
            if ($resourceContact['type'] == 'contact') {
                $rawContacts[] = ContactModel::getById([
                    'select'    => ['*'],
                    'id'        => $resourceContact['item_id']
                ]);
            }
        }

        $metadataFields = '';
        $metadataValues = '';
        $keyMetadataField = '';
        $keyMetadataValue = '';
        foreach ($multigestParameters['mapping']['document'] as $multigestField => $maarchField) {
            if (empty($multigestField) || empty($maarchField)) {
                continue;
            }

            $nextField = $multigestField;
            if ($maarchField == 'currentDate') {
                $nextValue = date('Y-m-d');
            } elseif (isset($document[$maarchField])) {
                if (strpos($maarchField, '_date') !== false) {
                    $date = new \DateTime($document[$maarchField]);
                    $document[$maarchField] = $date->format('Y-m-d');
                }
                $nextValue = $document[$maarchField];
            } else {
                $nextValue = MultigestController::getResourceField($document, $maarchField, $rawContacts);
            }

            $nextField = str_replace('|', '-', trim($nextField));
            $nextValue = str_replace('|', '-', trim($nextValue));

            if (empty($nextValue)) {
                continue;
            }

            if (empty($keyMetadataField)) {
                $keyMetadataField = $nextField;
            }
            if (empty($keyMetadataValue)) {
                $keyMetadataValue = $nextValue;
            }
            if (!empty($metadataFields)) {
                $metadataFields .= '|';
            }
            $metadataFields .= $nextField;
            if (!empty($metadataValues)) {
                $metadataValues .= '|';
            }
            $metadataValues .= $nextValue;
        }
        if (empty($metadataFields) || empty($metadataValues)) {
            return ['errors' => 'No valid metadata from Multigest mapping', 'lang' => 'multigestInvalidMapping'];
        }

        try {
            $soapClient = new \SoapClient($multigestUri, [
                'login'          => $configuration['login'],
                'password'       => empty($configuration['password']) ? '' : PasswordModel::decrypt(['cryptedPassword' => $configuration['password']]),
                'authentication' => SOAP_AUTHENTICATION_BASIC
            ]);
        } catch (\SoapFault $e) {
            return ['errors' => (string) $e, 'lang' => 'soapClientCreationError'];
        }

        $result = $soapClient->GedSetModeUid(1);
        if (is_soap_fault($result)) {
            return ['errors' => 'MultiGest SoapFault: ' . $result];
        }

        $result = $soapClient->GedChampReset();
        if (is_soap_fault($result)) {
            return ['errors' => 'MultiGest SoapFault: ' . $result];
        }

        $result = $soapClient->GedAddChampRecherche($keyMetadataField, $keyMetadataValue);
        if (is_soap_fault($result)) {
            return ['errors' => 'MultiGest SoapFault: ' . $result];
        }

        $result = $soapClient->GedDossierExist($entityConfiguration['sasId'], $entityConfiguration['login']);
        if (is_soap_fault($result)) {
            return ['errors' => 'MultiGest SoapFault: ' . $result];
        }

        $result = (int) $result;
        if ($result > 0) {
            return ['errors' => 'This resource is already in Multigest', 'lang' => 'documentAlreadyInMultigest'];
        } elseif ($result !== -7) { // -7 -> "Dossier inexistant"
            return ['errors' => 'Multigest error ' . $result . ' occurred while checking for folder preexistence'];
        }

        $result = $soapClient->GedChampReset();
        if (is_soap_fault($result)) {
            return ['errors' => 'MultiGest SoapFault: ' . $result];
        }

        $result = $soapClient->GedAddMultiChampRequete($metadataFields, $metadataValues);
        if (is_soap_fault($result)) {
            return ['errors' => 'MultiGest SoapFault: ' . $result];
        }

        $result = $soapClient->GedDossierCreate($entityConfiguration['sasId'], $entityConfiguration['login'], 0);
        if (is_soap_fault($result)) {
            return ['errors' => 'MultiGest SoapFault: ' . $result];
        }

        $result = (int) $result;
        if ($result != 0) {
            return ['errors' => 'Could not create Multigest folder', 'lang' => 'multigestFolderCreationError'];
        }

        $result = $soapClient->GedChampReset();
        if (is_soap_fault($result)) {
            return ['errors' => 'MultiGest SoapFault: ' . $result];
        }

        $result = $soapClient->GedAddChampRecherche($keyMetadataField, $keyMetadataValue);
        if (is_soap_fault($result)) {
            return ['errors' => 'MultiGest SoapFault: ' . $result];
        }

        $result = $soapClient->GedDossierExist($entityConfiguration['sasId'], $entityConfiguration['login']);
        if (is_soap_fault($result)) {
            return ['errors' => 'MultiGest SoapFault: ' . $result];
        }

        $result = (int) $result;
        if ($result <= 0) {
            return ['errors' => 'Multigest error ' . $result . ' occurred while accessing folder'];
        }

        $result = $soapClient->GedImporterDocumentStream(
            $entityConfiguration['sasId'],
            $entityConfiguration['login'],
            base64_encode($fileContent),
            '',
            '',
            '',
            $fileExtension,
            0,
            0,
            0,
            '',
            '',
            '',
            '',
            -1
        );
        if (is_soap_fault($result)) {
            return ['errors' => 'MultiGest SoapFault: ' . $result];
        }

        $result = (int) $result;
        if ($result <= 0) {
            return ['errors' => 'Multigest error ' . $result . ' occurred while importing main document'];
        }

        $externalId = json_decode($document['external_id'], true);
        $externalId['multigestId'] = $result;
        ResModel::update(['set' => ['external_id' => json_encode($externalId)], 'where' => ['res_id = ?'], 'data' => [$args['resId']]]);

        $multigestUIDs = ['document' => $result, 'attachments' => []];

        $attachments = AttachmentModel::get([
            'select'    => ['res_id', 'title', 'identifier', 'external_id', 'docserver_id', 'path', 'filename', 'format', 'attachment_type', 'relation'],
            'where'     => ['res_id_master = ?', 'attachment_type not in (?)', 'status not in (?)'],
            'data'      => [$args['resId'], ['signed_response'], ['DEL', 'OBS']],
            'orderBy'   => ['relation DESC']
        ]);

        // keep only last version
        $attachmentIdentifiers = [];
        foreach ($attachments as $key => $attachment) {
            if (in_array($attachment['identifier'], $attachmentIdentifiers)) {
                unset($attachments[$key]);
            } else {
                $attachmentIdentifiers[] = $attachment['identifier'];
            }
        }

        foreach ($attachments as $attachment) {
            $adrInfo = [
                'docserver_id'  => $attachment['docserver_id'],
                'path'          => $attachment['path'],
                'filename'      => $attachment['filename']
            ];
            if (empty($adrInfo['docserver_id'])) {
                continue;
            }
            $docserver = DocserverModel::getByDocserverId(['docserverId' => $adrInfo['docserver_id']]);
            if (empty($docserver['path_template'])) {
                continue;
            }
            $pathToDocument = $docserver['path_template'] . str_replace('#', DIRECTORY_SEPARATOR, $adrInfo['path']) . $adrInfo['filename'];
            if (!is_file($pathToDocument)) {
                continue;
            }
            $fileContent = file_get_contents($pathToDocument);
            if ($fileContent === false) {
                continue;
            }
            $fileExtension = explode('.', $attachment['filename']);
            $fileExtension = array_pop($fileExtension);

            $metadataFields = '';
            $metadataValues = '';
            $keyAttachmentMetadataField = '';
            $keyAttachmentMetadataValue = '';
            foreach ($multigestParameters['mapping']['attachments'] as $multigestField => $maarchField) {
                $metadataValue = '';
                if ($maarchField == 'typeLabel' && !empty($attachment['attachment_type'])) {
                    $attachmentType = AttachmentTypeModel::getByTypeId([
                        'typeId' => $attachment['attachment_type'],
                        'select' => ['label']
                    ]);
                    $metadataValue = $attachmentType['label'] ?? '';
                } elseif (!empty($attachment[$maarchField])) {
                    $metadataValue = $attachment[$maarchField];
                }
                if (empty($metadataValue)) {
                    continue;
                }
                if ($metadataFields !== '') {
                    $metadataFields .= '|';
                }
                $metadataFields .= str_replace('|', '-', $multigestField);
                if ($metadataValues !== '') {
                    $metadataValues .= '|';
                }
                $metadataValues .= str_replace('|', '-', $metadataValue);
                if (empty($keyAttachmentMetadataValue)) {
                    $keyAttachmentMetadataField = str_replace('|', '-', $multigestField);
                    $keyAttachmentMetadataValue = str_replace('|', '-', $metadataValue);
                }
            }
            $metadataFields .= '|'.$keyMetadataField;
            $metadataValues .= '|'.$keyMetadataValue;

            $metadataFields = $metadataFields;
            $metadataValues = $metadataValues;

            $result = $soapClient->GedChampReset();
            if (is_soap_fault($result)) {
                return ['errors' => 'MultiGest SoapFault: ' . $result];
            }

            $result = $soapClient->GedAddMultiChampRequete($metadataFields, $metadataValues);
            if (is_soap_fault($result)) {
                return ['errors' => 'MultiGest SoapFault: ' . $result];
            }

            $result = $soapClient->GedDossierCreate($entityConfiguration['sasId'], $entityConfiguration['login'], 0);
            if (is_soap_fault($result)) {
                return ['errors' => 'MultiGest SoapFault: ' . $result];
            }

            $result = (int) $result;
            if ($result != 0) {
                return ['errors' => 'Could not create Multigest folder', 'lang' => 'multigestFolderCreationError'];
            }

            $result = $soapClient->GedChampReset();
            if (is_soap_fault($result)) {
                return ['errors' => 'MultiGest SoapFault: ' . $result];
            }

            $result = $soapClient->GedAddChampRecherche($keyAttachmentMetadataField, $keyAttachmentMetadataValue);
            if (is_soap_fault($result)) {
                return ['errors' => 'MultiGest SoapFault: ' . $result];
            }

            $result = $soapClient->GedDossierExist($entityConfiguration['sasId'], $entityConfiguration['login']);
            if (is_soap_fault($result)) {
                return ['errors' => 'MultiGest SoapFault: ' . $result];
            }

            $result = (int) $result;
            if ($result <= 0) {
                return ['errors' => 'Multigest error ' . $result . ' occurred while accessing folder'];
            }

            $result = $soapClient->GedImporterDocumentStream(
                $entityConfiguration['sasId'],
                $entityConfiguration['login'],
                base64_encode($fileContent),
                '',
                '',
                '',
                $fileExtension,
                0,
                0,
                0,
                '',
                '',
                '',
                '',
                -1
            );
            if (is_soap_fault($result)) {
                return ['errors' => 'MultiGest SoapFault: ' . $result];
            }

            $result = (int) $result;
            if ($result <= 0) {
                return ['errors' => 'Multigest error ' . $result . ' occurred while importing attachment'];
            }

            $multigestUIDs['attachments'][] = $result;

            $externalId = json_decode($attachment['external_id'], true);
            $externalId['multigestId'] = $result;
            AttachmentModel::update(['set' => ['external_id' => json_encode($externalId)], 'where' => ['res_id = ?'], 'data' => [$attachment['res_id']]]);
        }

        return true;
    }

    public static function getResourceField(array $document, string $field, array $rawContacts) {
        if ($field == 'doctypeLabel' && !empty($document['type_id'])) {
            $doctype = DoctypeModel::getById(['select' => ['description'], 'id' => $document['type_id']]);
            if (!empty($doctype)) {
                return $doctype['description'];
            }
            return '';
        }
        if ($field == 'priorityLabel' && !empty($document['priority'])) {
            $priority = PriorityModel::getById(['select' => ['label'], 'id' => $document['priority']]);
            if (!empty($priority)) {
                return $priority['label'];
            }
            return '';
        }
        if ($field == 'destinationLabel' && !empty($document['destination'])) {
            $destination = EntityModel::getByEntityId(['entityId' => $document['destination'], 'select' => ['entity_label']]);
            if (!empty($destination)) {
                return $destination['entity_label'];
            }
            return '';
        }
        if ($field == 'initiatorLabel' && !empty($document['initiator'])) {
            $initiator = EntityModel::getByEntityId(['entityId' => $document['initiator'], 'select' => ['entity_label']]);
            if (!empty($initiator)) {
                return $initiator['entity_label'];
            }
            return '';
        }
        if ($field == 'destUserLabel' && !empty($document['dest_user'])) {
            return UserModel::getLabelledUserById(['id' => $document['dest_user']]);
        }
        if (strpos($field, 'senderCompany_') === 0) {
            return $rawContacts[MultigestController::fieldNameToIndex($field)]['company'] ?? '';
        }
        if (strpos($field, 'senderCivility_') === 0) {
            if (!empty($rawContacts[MultigestController::fieldNameToIndex($field)]['civility'])) {
                return ContactCivilityController::getLabelById(['id' => $rawContacts[MultigestController::fieldNameToIndex($field)]['civility']]);
            } else {
                return '';
            }
        }
        if (strpos($field, 'senderFirstname_') === 0) {
            return $rawContacts[MultigestController::fieldNameToIndex($field)]['firstname'] ?? '';
        }
        if (strpos($field, 'senderLastname_') === 0) {
            return $rawContacts[MultigestController::fieldNameToIndex($field)]['lastname'] ?? '';
        }
        if (strpos($field, 'senderFunction_') === 0) {
            return $rawContacts[MultigestController::fieldNameToIndex($field)]['function'] ?? '';
        }
        if (strpos($field, 'senderAddress_') === 0) {
            $contactIndex = MultigestController::fieldNameToIndex($field);
            if (!empty($rawContacts[$contactIndex])) {
                return ContactController::getFormattedContactWithAddress(['contact' => $rawContacts[$contactIndex]])['contact']['otherInfo'];
            } else {
                return '';
            }
        }
        if ($field == 'doctypeSecondLevelLabel' && !empty($document['type_id'])) {
            $doctype = DoctypeModel::getById(['select' => ['doctypes_second_level_id'], 'id' => $document['type_id']]);
            if (empty($doctype)) {
                return '';
            }
            $doctypeSecondLevel = SecondLevelModel::getById(['id' => $doctype['doctypes_second_level_id'], 'select' => ['doctypes_second_level_label']]);
            if (empty($doctypeSecondLevel)) {
                return '';
            }
            return $doctypeSecondLevel['doctypes_second_level_label'];
        }
        if (strpos($field, 'customField_') === 0) {
            $customFieldId = MultigestController::fieldNameToIndex($field);
            $customFieldsValues = json_decode($document['custom_fields'], true);
            if (!empty($customFieldsValues[$customFieldId]) && is_string($customFieldsValues[$customFieldId])) {
                return $customFieldsValues[$customFieldId];
            } else {
                return '';
            }
        }
        return '';
    }

    private static function fieldNameToIndex($field)
    {
        $fieldParts = explode($field, '_');
        if (count($fieldParts) > 1) {
            return (int) $fieldParts[1];
        }
        return 0;
    }
}
