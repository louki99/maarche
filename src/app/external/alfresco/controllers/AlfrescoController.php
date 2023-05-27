<?php

/**
 * Copyright Maarch since 2008 under licence GPLv3.
 * See LICENCE.txt file at the root folder for more details.
 * This file is part of Maarch software.
 *
 */

/**
 * @brief   Alfresco Controller
 * @author  dev@maarch.org
 */

namespace Alfresco\controllers;

use Attachment\models\AttachmentModel;
use Attachment\models\AttachmentTypeModel;
use Configuration\models\ConfigurationModel;
use Contact\controllers\ContactCivilityController;
use Contact\controllers\ContactController;
use Contact\models\ContactModel;
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
use SrcCore\models\CoreConfigModel;
use SrcCore\models\CurlModel;
use SrcCore\models\PasswordModel;
use SrcCore\models\ValidatorModel;
use User\models\UserModel;

class AlfrescoController
{
    public function getConfiguration(Request $request, Response $response)
    {
        if (!PrivilegeController::hasPrivilege(['privilegeId' => 'admin_alfresco', 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }

        $configuration = ConfigurationModel::getByPrivilege(['privilege' => 'admin_alfresco']);
        if (empty($configuration)) {
            return $response->withJson(['configuration' => null]);
        }

        $configuration['value'] = json_decode($configuration['value'], true);

        return $response->withJson(['configuration' => ['uri' => $configuration['value']['uri']]]);
    }

    public function updateConfiguration(Request $request, Response $response)
    {
        if (!PrivilegeController::hasPrivilege(['privilegeId' => 'admin_alfresco', 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }

        $body = $request->getParsedBody();

        if (!Validator::stringType()->validate($body['uri'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body uri is empty or not a string']);
        }

        $value = json_encode(['uri' => trim($body['uri'])]);

        $configuration = ConfigurationModel::getByPrivilege(['privilege' => 'admin_alfresco']);
        if (empty($configuration)) {
            ConfigurationModel::create(['privilege' => 'admin_alfresco', 'value' => $value]);
        } else {
            ConfigurationModel::update(['set' => ['value' => $value], 'where' => ['privilege = ?'], 'data' => ['admin_alfresco']]);
        }

        return $response->withStatus(204);
    }

    public function getAvailableEntities(Request $request, Response $response)
    {
        if (!PrivilegeController::hasPrivilege(['privilegeId' => 'admin_alfresco', 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }

        $entities = EntityModel::get(['select' => ['id'], 'where' => ["external_id->>'alfresco' is null"]]);

        $availableEntities = array_column($entities, 'id');

        return $response->withJson(['availableEntities' => $availableEntities]);
    }

    public function getAccounts(Request $request, Response $response)
    {
        if (!PrivilegeController::hasPrivilege(['privilegeId' => 'admin_alfresco', 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }

        $entities = EntityModel::get(['select' => ['external_id', 'short_label'], 'where' => ["external_id->>'alfresco' is not null"]]);

        $accounts = [];
        $alreadyAdded = [];
        foreach ($entities as $entity) {
            $alfresco = json_decode($entity['external_id'], true);
            if (!in_array($alfresco['alfresco']['id'], $alreadyAdded)) {
                $accounts[] = [
                    'id'            => $alfresco['alfresco']['id'],
                    'label'         => $alfresco['alfresco']['label'],
                    'login'         => $alfresco['alfresco']['login'],
                    'entitiesLabel' => [$entity['short_label']]
                ];
                $alreadyAdded[] = $alfresco['alfresco']['id'];
            } else {
                foreach ($accounts as $key => $value) {
                    if ($value['id'] == $alfresco['alfresco']['id']) {
                        $accounts[$key]['entitiesLabel'][] = $entity['short_label'];
                    }
                }
            }
        }

        return $response->withJson(['accounts' => $accounts]);
    }

    public function getAccountById(Request $request, Response $response, array $args)
    {
        if (!PrivilegeController::hasPrivilege(['privilegeId' => 'admin_alfresco', 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }

        $entities = EntityModel::get(['select' => ['external_id', 'id'], 'where' => ["external_id->'alfresco'->>'id' = ?"], 'data' => [$args['id']]]);
        if (empty($entities[0])) {
            return $response->withStatus(400)->withJson(['errors' => 'Account not found']);
        }

        $alfresco = json_decode($entities[0]['external_id'], true);
        $account = [
            'id'        => $alfresco['alfresco']['id'],
            'label'     => $alfresco['alfresco']['label'],
            'login'     => $alfresco['alfresco']['login'],
            'nodeId'    => $alfresco['alfresco']['nodeId'],
            'entities'  => []
        ];

        foreach ($entities as $entity) {
            $account['entities'][] = $entity['id'];
        }

        return $response->withJson($account);
    }

    public function createAccount(Request $request, Response $response)
    {
        if (!PrivilegeController::hasPrivilege(['privilegeId' => 'admin_alfresco', 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }

        $body = $request->getParsedBody();

        if (!Validator::stringType()->notEmpty()->validate($body['label'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body label is empty or not a string']);
        } elseif (!Validator::stringType()->notEmpty()->validate($body['login'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body login is empty or not a string']);
        } elseif (!Validator::stringType()->notEmpty()->validate($body['password'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body password is empty or not a string']);
        } elseif (!Validator::stringType()->notEmpty()->validate($body['nodeId'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body nodeId is empty or not a string']);
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
            'id'        => $id,
            'label'     => $body['label'],
            'login'     => $body['login'],
            'password'  => PasswordModel::encrypt(['password' => $body['password']]),
            'nodeId'    => $body['nodeId']
        ];
        $account = json_encode($account);

        EntityModel::update([
            'postSet'   => ['external_id' => "jsonb_set(coalesce(external_id, '{}'::jsonb), '{alfresco}', '{$account}')"],
            'where'     => ['id in (?)'],
            'data'      => [$body['entities']]
        ]);

        return $response->withStatus(204);
    }

    public function updateAccount(Request $request, Response $response, array $args)
    {
        if (!PrivilegeController::hasPrivilege(['privilegeId' => 'admin_alfresco', 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }

        $body = $request->getParsedBody();

        if (!Validator::stringType()->notEmpty()->validate($body['label'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body label is empty or not a string']);
        } elseif (!Validator::stringType()->notEmpty()->validate($body['login'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body login is empty or not a string']);
        } elseif (!Validator::stringType()->notEmpty()->validate($body['nodeId'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body nodeId is empty or not a string']);
        } elseif (!Validator::arrayType()->notEmpty()->validate($body['entities'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body entities is empty or not an array']);
        }

        $accounts = EntityModel::get(['select' => ['external_id', 'id'], 'where' => ["external_id->'alfresco'->>'id' = ?"], 'data' => [$args['id']]]);
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

        $alfresco = json_decode($accounts[0]['external_id'], true);
        $account = [
            'id'        => $args['id'],
            'label'     => $body['label'],
            'login'     => $body['login'],
            'password'  => empty($body['password']) ? $alfresco['alfresco']['password'] : PasswordModel::encrypt(['password' => $body['password']]),
            'nodeId'    => $body['nodeId']
        ];
        $account = json_encode($account);

        EntityModel::update([
            'set'   => ['external_id' => "{}"],
            'where' => ['id in (?)', 'external_id = ?'],
            'data'  => [$body['entities'], 'null']
        ]);

        EntityModel::update([
            'postSet'   => ['external_id' => "jsonb_set(coalesce(external_id, '{}'::jsonb), '{alfresco}', '{$account}')"],
            'where'     => ['id in (?)'],
            'data'      => [$body['entities']]
        ]);

        $previousEntities = array_column($accounts, 'id');
        $entitiesToRemove = array_diff($previousEntities, $body['entities']);
        if (!empty($entitiesToRemove)) {
            EntityModel::update([
                'postSet'   => ['external_id' => "external_id - 'alfresco'"],
                'where'     => ['id in (?)'],
                'data'      => [$entitiesToRemove]
            ]);
        }

        return $response->withStatus(204);
    }

    public function deleteAccount(Request $request, Response $response, array $args)
    {
        if (!PrivilegeController::hasPrivilege(['privilegeId' => 'admin_alfresco', 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }

        $accounts = EntityModel::get(['select' => ['external_id', 'id'], 'where' => ["external_id->'alfresco'->>'id' = ?"], 'data' => [$args['id']]]);
        if (empty($accounts[0])) {
            return $response->withStatus(400)->withJson(['errors' => 'Account not found']);
        }

        $entitiesToRemove = array_column($accounts, 'id');
        EntityModel::update([
            'postSet'   => ['external_id' => "external_id - 'alfresco'"],
            'where'     => ['id in (?)'],
            'data'      => [$entitiesToRemove]
        ]);

        return $response->withStatus(204);
    }

    public function checkAccount(Request $request, Response $response)
    {
        if (!PrivilegeController::hasPrivilege(['privilegeId' => 'admin_alfresco', 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }

        $body = $request->getParsedBody();

        if (!Validator::stringType()->notEmpty()->validate($body['login'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body login is empty or not a string']);
        } elseif (!Validator::stringType()->notEmpty()->validate($body['password'] ?? '') && !Validator::stringType()->notEmpty()->validate($body['accountId'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body password is empty or not a string']);
        }

        if (empty($body['password'])) {
            $account = EntityModel::get(['select' => ['external_id'], 'where' => ["external_id->'alfresco'->>'id' = ?"], 'data' => [$body['accountId']], 'limit' => 1]);
            if (empty($account[0])) {
                return $response->withStatus(400)->withJson(['errors' => 'Account not found']);
            }
            $alfresco = json_decode($account[0]['external_id'], true);
            if (empty($alfresco['alfresco']['password'])) {
                return $response->withStatus(400)->withJson(['errors' => 'Account has no password']);
            }
            $body['password'] = PasswordModel::decrypt(['cryptedPassword' => $alfresco['alfresco']['password']]);
        }

        $configuration = ConfigurationModel::getByPrivilege(['privilege' => 'admin_alfresco']);
        if (empty($configuration)) {
            return $response->withStatus(400)->withJson(['errors' => 'Alfresco configuration is not enabled']);
        }
        $configuration = json_decode($configuration['value'], true);
        if (empty($configuration['uri'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Alfresco configuration URI is empty']);
        }
        $alfrescoUri = rtrim($configuration['uri'], '/');

        if (empty($body['nodeId'])) {
            $requestBody = [
                'query' => [
                    'query'     => "select * from cmis:folder",
                    'language'  => 'cmis',
                ],
                "paging" => [
                    'maxItems' => '1'
                ],
                'fields' => ['id', 'name']
            ];
            $curlResponse = CurlModel::exec([
                'url'           => "{$alfrescoUri}/search/versions/1/search",
                'basicAuth'     => ['user' => $body['login'], 'password' => $body['password']],
                'headers'       => ['content-type:application/json', 'Accept: application/json'],
                'method'        => 'POST',
                'body'          => json_encode($requestBody)
            ]);

        } else {
            $curlResponse = CurlModel::exec([
                'url'           => "{$alfrescoUri}/alfresco/versions/1/nodes/{$body['nodeId']}/children",
                'basicAuth'     => ['user' => $body['login'], 'password' => $body['password']],
                'headers'       => ['content-type:application/json'],
                'method'        => 'GET',
                'queryParams'   => ['where' => '(isFolder=true)']
            ]);
        }

        if ($curlResponse['code'] != 200) {
            if (!empty($curlResponse['response']['error']['briefSummary'])) {
                return $response->withStatus(400)->withJson(['errors' => $curlResponse['response']['error']['briefSummary']]);
            } elseif ($curlResponse['code'] == 404) {
                return $response->withStatus(400)->withJson(['errors' => 'Page not found', 'lang' => 'pageNotFound']);
            } elseif (!empty($curlResponse['response'])) {
                return $response->withStatus(400)->withJson(['errors' => json_encode($curlResponse['response'])]);
            } else {
                return $response->withStatus(400)->withJson(['errors' => $curlResponse['errors']]);
            }
        }

        return $response->withStatus(204);
    }

    public function getRootFolders(Request $request, Response $response)
    {
        $configuration = ConfigurationModel::getByPrivilege(['privilege' => 'admin_alfresco']);
        if (empty($configuration)) {
            return $response->withStatus(400)->withJson(['errors' => 'Alfresco configuration is not enabled']);
        }

        $configuration = json_decode($configuration['value'], true);
        if (empty($configuration['uri'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Alfresco configuration URI is empty']);
        }
        $alfrescoUri = rtrim($configuration['uri'], '/');

        $entity = UserModel::getPrimaryEntityById(['id' => $GLOBALS['id'], 'select' => ['entities.external_id']]);
        if (empty($entity)) {
            return $response->withStatus(400)->withJson(['errors' => 'User has no primary entity']);
        }
        $entityInformations = json_decode($entity['external_id'], true);
        if (empty($entityInformations['alfresco'])) {
            return $response->withStatus(400)->withJson(['errors' => 'User primary entity has not enough alfresco informations']);
        }
        $entityInformations['alfresco']['password'] = PasswordModel::decrypt(['cryptedPassword' => $entityInformations['alfresco']['password']]);

        $curlResponse = CurlModel::exec([
            'url'           => "{$alfrescoUri}/alfresco/versions/1/nodes/{$entityInformations['alfresco']['nodeId']}/children",
            'basicAuth'     => ['user' => $entityInformations['alfresco']['login'], 'password' => $entityInformations['alfresco']['password']],
            'headers'       => ['content-type:application/json'],
            'method'        => 'GET',
            'queryParams'   => ['where' => '(isFolder=true)']
        ]);
        if ($curlResponse['code'] != 200) {
            return $response->withStatus(400)->withJson(['errors' => json_encode($curlResponse['response'])]);
        }

        $folders = [];
        if (!empty($curlResponse['response']['list']['entries'])) {
            foreach ($curlResponse['response']['list']['entries'] as $value) {
                $folders[] = [
                    'id'        => $value['entry']['id'],
                    'icon'      => 'fa fa-folder',
                    'text'      => $value['entry']['name'],
                    'parent'    => '#',
                    'children'  => true
                ];
            }
        }

        return $response->withJson($folders);
    }

    public function getChildrenFoldersById(Request $request, Response $response, array $args)
    {
        $configuration = ConfigurationModel::getByPrivilege(['privilege' => 'admin_alfresco']);
        if (empty($configuration)) {
            return $response->withStatus(400)->withJson(['errors' => 'Alfresco configuration is not enabled']);
        }

        $configuration = json_decode($configuration['value'], true);
        if (empty($configuration['uri'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Alfresco configuration URI is empty']);
        }
        $alfrescoUri = rtrim($configuration['uri'], '/');

        $entity = UserModel::getPrimaryEntityById(['id' => $GLOBALS['id'], 'select' => ['entities.external_id']]);
        if (empty($entity)) {
            return $response->withStatus(400)->withJson(['errors' => 'User has no primary entity']);
        }
        $entityInformations = json_decode($entity['external_id'], true);
        if (empty($entityInformations['alfresco'])) {
            return $response->withStatus(400)->withJson(['errors' => 'User primary entity has not enough alfresco informations']);
        }
        $entityInformations['alfresco']['password'] = PasswordModel::decrypt(['cryptedPassword' => $entityInformations['alfresco']['password']]);

        $curlResponse = CurlModel::exec([
            'url'           => "{$alfrescoUri}/alfresco/versions/1/nodes/{$args['id']}/children",
            'basicAuth'     => ['user' => $entityInformations['alfresco']['login'], 'password' => $entityInformations['alfresco']['password']],
            'headers'       => ['content-type:application/json'],
            'method'        => 'GET',
            'queryParams'   => ['where' => '(isFolder=true)']
        ]);
        if ($curlResponse['code'] != 200) {
            return $response->withStatus(400)->withJson(['errors' => json_encode($curlResponse['response'])]);
        }

        $folders = [];
        if (!empty($curlResponse['response']['list']['entries'])) {
            foreach ($curlResponse['response']['list']['entries'] as $value) {
                $folders[] = [
                    'id'        => $value['entry']['id'],
                    'icon'      => 'fa fa-folder',
                    'text'      => $value['entry']['name'],
                    'parent'    => $args['id'],
                    'children'  => true
                ];
            }
        }

        return $response->withJson($folders);
    }

    public function getFolders(Request $request, Response $response)
    {
        $queryParams = $request->getQueryParams();
        if (!Validator::stringType()->notEmpty()->validate($queryParams['search'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Query params search is empty']);
        } elseif (strlen($queryParams['search']) < 3) {
            return $response->withStatus(400)->withJson(['errors' => 'Query params search is too short']);
        }

        $configuration = ConfigurationModel::getByPrivilege(['privilege' => 'admin_alfresco']);
        if (empty($configuration)) {
            return $response->withStatus(400)->withJson(['errors' => 'Alfresco configuration is not enabled']);
        }

        $configuration = json_decode($configuration['value'], true);
        if (empty($configuration['uri'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Alfresco configuration URI is empty']);
        }
        $alfrescoUri = rtrim($configuration['uri'], '/');

        $entity = UserModel::getPrimaryEntityById(['id' => $GLOBALS['id'], 'select' => ['entities.external_id']]);
        if (empty($entity)) {
            return $response->withStatus(400)->withJson(['errors' => 'User has no primary entity']);
        }
        $entityInformations = json_decode($entity['external_id'], true);
        if (empty($entityInformations['alfresco'])) {
            return $response->withStatus(400)->withJson(['errors' => 'User primary entity has not enough alfresco informations']);
        }
        $entityInformations['alfresco']['password'] = PasswordModel::decrypt(['cryptedPassword' => $entityInformations['alfresco']['password']]);

        $search = addslashes($queryParams['search']);
        $body = [
            'query' => [
                'query'     => "select * from cmis:folder where CONTAINS ('cmis:name:*{$search}*') and IN_TREE('{$entityInformations['alfresco']['nodeId']}')",
                'language'  => 'cmis',
            ],
            'fields' => ['id', 'name']
        ];
        $curlResponse = CurlModel::exec([
            'url'           => "{$alfrescoUri}/search/versions/1/search",
            'basicAuth'     => ['user' => $entityInformations['alfresco']['login'], 'password' => $entityInformations['alfresco']['password']],
            'headers'       => ['content-type:application/json', 'Accept: application/json'],
            'method'        => 'POST',
            'body'          => json_encode($body)
        ]);
        if ($curlResponse['code'] != 200) {
            return $response->withStatus(400)->withJson(['errors' => json_encode($curlResponse['response'])]);
        }

        $folders = [];
        if (!empty($curlResponse['response']['list']['entries'])) {
            foreach ($curlResponse['response']['list']['entries'] as $value) {
                $folders[] = [
                    'id'        => $value['entry']['id'],
                    'icon'      => 'fa fa-folder',
                    'text'      => $value['entry']['name'],
                    'parent'    => '#',
                    'children'  => true
                ];
            }
        }

        return $response->withJson($folders);
    }

    public static function sendResource(array $args)
    {
        ValidatorModel::notEmpty($args, ['resId', 'folderId', 'userId']);
        ValidatorModel::intVal($args, ['resId', 'userId']);
        ValidatorModel::stringType($args, ['folderId', 'folderName']);

        $configuration = ConfigurationModel::getByPrivilege(['privilege' => 'admin_alfresco']);
        if (empty($configuration)) {
            return ['errors' => 'Alfresco configuration is not enabled'];
        }

        $configuration = json_decode($configuration['value'], true);
        if (empty($configuration['uri'])) {
            return ['errors' => 'Alfresco configuration URI is empty'];
        }
        $alfrescoUri = rtrim($configuration['uri'], '/');

        $entity = UserModel::getPrimaryEntityById(['id' => $args['userId'], 'select' => ['entities.external_id']]);
        if (empty($entity)) {
            return ['errors' => 'User has no primary entity'];
        }
        $entityInformations = json_decode($entity['external_id'], true);
        if (empty($entityInformations['alfresco'])) {
            return ['errors' => 'User primary entity has not enough alfresco informations'];
        }
        $entityInformations['alfresco']['password'] = PasswordModel::decrypt(['cryptedPassword' => $entityInformations['alfresco']['password']]);

        $document = ResModel::getById([
            'select'    => [
                'filename', 'subject', 'alt_identifier', 'external_id', 'type_id', 'priority', 'fingerprint', 'custom_fields', 'dest_user',
                'creation_date', 'modification_date', 'doc_date', 'destination', 'initiator', 'process_limit_date', 'closing_date', 'docserver_id', 'path', 'filename'
            ],
            'resId'     => $args['resId']
        ]);
        if (empty($document)) {
            return ['errors' => 'Document does not exist'];
        } elseif (empty($document['filename'])) {
            return ['errors' => 'Document has no file'];
        } elseif (empty($document['alt_identifier'])) {
            return ['errors' => 'Document has no chrono'];
        }

        $docserver = DocserverModel::getByDocserverId(['docserverId' => $document['docserver_id'], 'select' => ['path_template', 'docserver_type_id']]);
        if (empty($docserver['path_template']) || !file_exists($docserver['path_template'])) {
            return ['errors' => 'Docserver does not exist'];
        }

        $pathToDocument = $docserver['path_template'] . str_replace('#', DIRECTORY_SEPARATOR, $document['path']) . $document['filename'];
        if (!is_file($pathToDocument)) {
            return ['errors' => 'Document not found on docserver'];
        }

        $fileContent = file_get_contents($pathToDocument);
        if ($fileContent === false) {
            return ['errors' => 'Document not found on docserver'];
        }
        $alfrescoParameters = CoreConfigModel::getJsonLoaded(['path' => 'config/alfresco.json']);
        if (empty($alfrescoParameters)) {
            return ['errors' => 'Alfresco mapping file does not exist'];
        }

        $body = ['name' => str_replace('/', '_', $document['alt_identifier']), 'nodeType' => 'cm:folder'];
        if (!empty($alfrescoParameters['mapping']['folderCreation'])) {
            $body['properties'] = $alfrescoParameters['mapping']['folderCreation'];
        }
        $curlResponse = CurlModel::exec([
            'url'           => "{$alfrescoUri}/alfresco/versions/1/nodes/{$args['folderId']}/children",
            'basicAuth'     => ['user' => $entityInformations['alfresco']['login'], 'password' => $entityInformations['alfresco']['password']],
            'headers'       => ['content-type:application/json', 'Accept: application/json'],
            'method'        => 'POST',
            'body'          => json_encode($body)
        ]);
        if ($curlResponse['code'] != 201) {
            return ['errors' => "Create folder {$document['alt_identifier']} failed : " . json_encode($curlResponse['response'])];
        }
        $resourceFolderId = $curlResponse['response']['entry']['id'];

        // regex matching INVALID folder or document name, used in Alfresco:
        // (.*[\"\*\\\>\<\?\/\:\|]+.*)|(.*[\.]?.*[\.]+$)|(.*[ ]+$)
        $alfrescoCharRefused = str_split('"*\\><?/:|');
        $alfrescoCharToTrim  = '. ';
        $document['subject'] = str_replace($alfrescoCharRefused, ' ', $document['subject']); // replace refused characters with a blank space
        $document['subject'] = preg_replace('/\s+/u', ' ', $document['subject']); // squeeze spaces including unicode ones
        $document['subject'] = trim($document['subject'], $alfrescoCharToTrim); // trim start and end of string
        $multipartBody = [
            'filedata' => ['isFile' => true, 'filename' => $document['subject'], 'content' => $fileContent],
        ];
        $curlResponse = CurlModel::exec([
            'url'           => "{$alfrescoUri}/alfresco/versions/1/nodes/{$resourceFolderId}/children",
            'basicAuth'     => ['user' => $entityInformations['alfresco']['login'], 'password' => $entityInformations['alfresco']['password']],
            'method'        => 'POST',
            'multipartBody' => $multipartBody
        ]);
        if ($curlResponse['code'] != 201) {
            return ['errors' => "Send resource {$args['resId']} failed : " . json_encode($curlResponse['response'])];
        }
        $documentId = $curlResponse['response']['entry']['id'];

        $properties = [];
        if (!empty($alfrescoParameters['mapping']['document'])) {
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

            foreach ($alfrescoParameters['mapping']['document'] as $key => $alfrescoParameter) {
                if ($alfrescoParameter == 'alfrescoLogin') {
                    $properties[$key] = $entityInformations['alfresco']['login'];
                } elseif ($alfrescoParameter == 'doctypeLabel') {
                    if (!empty($document['type_id'])) {
                        $doctype = DoctypeModel::getById(['select' => ['description'], 'id' => $document['type_id']]);
                    }
                    $properties[$key] = $doctype['description'] ?? '';
                } elseif ($alfrescoParameter == 'priorityLabel') {
                    if (!empty($document['priority'])) {
                        $priority = PriorityModel::getById(['select' => ['label'], 'id' => $document['priority']]);
                        $properties[$key] = $priority['label'];
                    }
                } elseif ($alfrescoParameter == 'destinationLabel') {
                    if (!empty($document['destination'])) {
                        $destination = EntityModel::getByEntityId(['entityId' => $document['destination'], 'select' => ['entity_label']]);
                        $properties[$key] = $destination['entity_label'];
                    }
                } elseif ($alfrescoParameter == 'initiatorLabel') {
                    if (!empty($document['initiator'])) {
                        $initiator = EntityModel::getByEntityId(['entityId' => $document['initiator'], 'select' => ['entity_label']]);
                        $properties[$key] = $initiator['entity_label'];
                    }
                } elseif ($alfrescoParameter == 'destUserLabel') {
                    if (!empty($document['dest_user'])) {
                        $properties[$key] = UserModel::getLabelledUserById(['id' => $document['dest_user']]);
                    }
                } elseif (strpos($alfrescoParameter, 'senderCompany_') !== false) {
                    $contactNb = explode('_', $alfrescoParameter)[1];
                    $properties[$key] = $rawContacts[$contactNb]['company'] ?? '';
                } elseif (strpos($alfrescoParameter, 'senderCivility_') !== false) {
                    $contactNb = explode('_', $alfrescoParameter)[1];
                    $civility = null;
                    if (!empty($rawContacts[$contactNb]['civility'])) {
                        $civility = ContactCivilityController::getLabelById(['id' => $rawContacts[$contactNb]['civility']]);
                    }
                    $properties[$key] = $civility ?? '';
                } elseif (strpos($alfrescoParameter, 'senderFirstname_') !== false) {
                    $contactNb = explode('_', $alfrescoParameter)[1];
                    $properties[$key] = $rawContacts[$contactNb]['firstname'] ?? '';
                } elseif (strpos($alfrescoParameter, 'senderLastname_') !== false) {
                    $contactNb = explode('_', $alfrescoParameter)[1];
                    $properties[$key] = $rawContacts[$contactNb]['lastname'] ?? '';
                } elseif (strpos($alfrescoParameter, 'senderFunction_') !== false) {
                    $contactNb = explode('_', $alfrescoParameter)[1];
                    $properties[$key] = $rawContacts[$contactNb]['function'] ?? '';
                } elseif (strpos($alfrescoParameter, 'senderAddress_') !== false) {
                    $contactNb = explode('_', $alfrescoParameter)[1];
                    if (!empty($rawContacts[$contactNb])) {
                        $contactToDisplay = ContactController::getFormattedContactWithAddress(['contact' => $rawContacts[$contactNb]]);
                    }
                    $properties[$key] = $contactToDisplay['contact']['address'] ?? '';
                } elseif ($alfrescoParameter == 'doctypeSecondLevelLabel') {
                    if (!empty($document['type_id'])) {
                        $doctype = DoctypeModel::getById(['select' => ['doctypes_second_level_id'], 'id' => $document['type_id']]);
                        $doctypeSecondLevel = SecondLevelModel::getById(['id' => $doctype['doctypes_second_level_id'], 'select' => ['doctypes_second_level_label']]);
                    }
                    $properties[$key] = $doctypeSecondLevel['doctypes_second_level_label'] ?? '';
                } elseif (strpos($alfrescoParameter, 'customField_') !== false) {
                    $customId = explode('_', $alfrescoParameter)[1];
                    $customValue = json_decode($document['custom_fields'], true);
                    $properties[$key] = (!empty($customValue[$customId]) && is_string($customValue[$customId])) ? $customValue[$customId] : '';
                } elseif ($alfrescoParameter == 'currentDate') {
                    $date = new \DateTime();
                    $properties[$key] = $date->format('d-m-Y H:i');
                } else {
                    $properties[$key] = $document[$alfrescoParameter];
                }
            }
        }

        $body = [
            'properties' => $properties,
        ];
        $curlResponse = CurlModel::exec([
            'url'           => "{$alfrescoUri}/alfresco/versions/1/nodes/{$documentId}",
            'basicAuth'     => ['user' => $entityInformations['alfresco']['login'], 'password' => $entityInformations['alfresco']['password']],
            'headers'       => ['content-type:application/json', 'Accept: application/json'],
            'method'        => 'PUT',
            'body'          => json_encode($body)
        ]);
        if ($curlResponse['code'] != 200) {
            return ['errors' => "Update resource {$args['resId']} failed : " . json_encode($curlResponse['response'])];
        }

        $externalId = json_decode($document['external_id'], true);
        $externalId['alfrescoId'] = $documentId;
        ResModel::update(['set' => ['external_id' => json_encode($externalId)], 'where' => ['res_id = ?'], 'data' => [$args['resId']]]);

        $attachments = AttachmentModel::get([
            'select'    => ['res_id', 'title', 'identifier', 'external_id', 'docserver_id', 'path', 'filename', 'format', 'attachment_type'],
            'where'     => ['res_id_master = ?', 'attachment_type not in (?)', 'status not in (?)'],
            'data'      => [$args['resId'], ['signed_response'], ['DEL', 'OBS']]
        ]);
        $firstAttachment = true;
        $attachmentsTitlesSent = [];
        foreach ($attachments as $attachment) {
            $attachment['title'] = str_replace($alfrescoCharRefused, ' ', $attachment['title']);
            $attachment['title'] = preg_replace('/\s+/u', ' ', $attachment['title']);
            $attachment['title'] = trim($attachment['title'], $alfrescoCharToTrim);
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

            if ($firstAttachment) {
                $curlResponse = CurlModel::exec([
                    'url'           => "{$alfrescoUri}/alfresco/versions/1/nodes/{$resourceFolderId}/children",
                    'basicAuth'     => ['user' => $entityInformations['alfresco']['login'], 'password' => $entityInformations['alfresco']['password']],
                    'headers'       => ['content-type:application/json', 'Accept: application/json'],
                    'method'        => 'POST',
                    'body'          => json_encode(['name' => 'Pièces jointes', 'nodeType' => 'cm:folder'])
                ]);
                if ($curlResponse['code'] != 201) {
                    return ['errors' => "Create folder 'Pièces jointes' failed : " . json_encode($curlResponse['response'])];
                }
                $attachmentsFolderId = $curlResponse['response']['entry']['id'];
            }

            if (empty($attachmentsFolderId)) {
                continue;
            }
            $firstAttachment = false;
            if (in_array($attachment['title'], $attachmentsTitlesSent)) {
                $i = 1;
                $newTitle = "{$attachment['title']}_{$i}";
                while (in_array($newTitle, $attachmentsTitlesSent)) {
                    $newTitle = "{$attachment['title']}_{$i}";
                    ++$i;
                }
                $attachment['title'] = $newTitle;
            }
            $multipartBody = [
                'filedata' => ['isFile' => true, 'filename' => $attachment['title'], 'content' => $fileContent],
            ];
            $curlResponse = CurlModel::exec([
                'url'           => "{$alfrescoUri}/alfresco/versions/1/nodes/{$attachmentsFolderId}/children",
                'basicAuth'     => ['user' => $entityInformations['alfresco']['login'], 'password' => $entityInformations['alfresco']['password']],
                'method'        => 'POST',
                'multipartBody' => $multipartBody
            ]);
            if ($curlResponse['code'] != 201) {
                return ['errors' => "Send attachment {$attachment['res_id']} failed : " . json_encode($curlResponse['response'])];
            }

            $attachmentId = $curlResponse['response']['entry']['id'];

            $properties = [];
            if (!empty($alfrescoParameters['mapping']['attachment'])) {
                foreach ($alfrescoParameters['mapping']['attachment'] as $key => $alfrescoParameter) {
                    if ($alfrescoParameter == 'typeLabel') {
                        $attachmentType = AttachmentTypeModel::getByTypeId(['select' => ['label'], 'typeId' => $attachment['attachment_type']]);
                        $properties[$key] = $attachmentType['label'] ?? '';
                    } else {
                        $properties[$key] = $attachment[$alfrescoParameter];
                    }
                }
            }

            $body = [
                'properties' => $properties,
            ];
            $curlResponse = CurlModel::exec([
                'url'           => "{$alfrescoUri}/alfresco/versions/1/nodes/{$attachmentId}",
                'basicAuth'     => ['user' => $entityInformations['alfresco']['login'], 'password' => $entityInformations['alfresco']['password']],
                'headers'       => ['content-type:application/json', 'Accept: application/json'],
                'method'        => 'PUT',
                'body'          => json_encode($body)
            ]);
            if ($curlResponse['code'] != 200) {
                return ['errors' => "Update attachment {$attachment['res_id']} failed : " . json_encode($curlResponse['response'])];
            }

            $attachmentsTitlesSent[] = $attachment['title'];

            $externalId = json_decode($attachment['external_id'], true);
            $externalId['alfrescoId'] = $attachmentId;
            AttachmentModel::update(['set' => ['external_id' => json_encode($externalId)], 'where' => ['res_id = ?'], 'data' => [$attachment['res_id']]]);
        }

        if (!empty($alfrescoParameters['mapping']['folderModification'])) {
            $body = [
                'properties' => $alfrescoParameters['mapping']['folderModification'],
            ];
            $curlResponse = CurlModel::exec([
                'url'           => "{$alfrescoUri}/alfresco/versions/1/nodes/{$resourceFolderId}",
                'basicAuth'     => ['user' => $entityInformations['alfresco']['login'], 'password' => $entityInformations['alfresco']['password']],
                'headers'       => ['content-type:application/json', 'Accept: application/json'],
                'method'        => 'PUT',
                'body'          => json_encode($body)
            ]);
            if ($curlResponse['code'] != 200) {
                return ['errors' => "Update alfresco folder {$resourceFolderId} failed : " . json_encode($curlResponse['response'])];
            }
        }

        $message = empty($args['folderName']) ? " (envoyé au dossier {$args['folderId']})" : " (envoyé au dossier {$args['folderName']})";
        return ['history' => $message];
    }
}
