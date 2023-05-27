<?php

/**
* Copyright Maarch since 2008 under licence GPLv3.
* See LICENCE.txt file at the root folder for more details.
* This file is part of Maarch software.
*
*/

/**
* @brief Auto Complete Controller
* @author dev@maarch.org
*/

namespace SrcCore\controllers;

use Contact\controllers\ContactController;
use Contact\models\ContactGroupModel;
use Contact\models\ContactModel;
use Contact\models\ContactParameterModel;
use Entity\models\EntityModel;
use Group\controllers\PrivilegeController;
use Group\models\GroupModel;
use Group\models\PrivilegeModel;
use Respect\Validation\Validator;
use Slim\Psr7\Request;
use SrcCore\http\Response;
use SrcCore\models\CoreConfigModel;
use SrcCore\models\CurlModel;
use SrcCore\models\DatabaseModel;
use SrcCore\models\TextFormatModel;
use SrcCore\models\ValidatorModel;
use Status\models\StatusModel;
use Tag\models\TagModel;
use User\controllers\UserController;
use User\models\UserModel;
use Folder\models\FolderModel;
use Folder\controllers\FolderController;
use MessageExchange\controllers\AnnuaryController;
use Parameter\models\ParameterModel;
use Contact\models\ContactAddressSectorModel;
use ExternalSignatoryBook\controllers\FastParapheurController;

class AutoCompleteController
{
    const LIMIT = 50;
    const TINY_LIMIT = 10;

    public static function getUsers(Request $request, Response $response)
    {
        $queryParams = $request->getQueryParams();
        $check = Validator::stringType()->notEmpty()->validate($queryParams['search']);
        if (!$check) {
            return $response->withStatus(400)->withJson(['errors' => 'Bad Request']);
        }

        $fields = ['firstname', 'lastname'];
        $fields = AutoCompleteController::getInsensitiveFieldsForRequest(['fields' => $fields]);

        $where = ['status not in (?)', 'mode not in (?)'];
        $data = [['DEL', 'SPD'], ['root_invisible', 'rest']];
        if (!empty($queryParams['inEntity'])) {
            if (is_numeric($queryParams['inEntity'])) {
                $entity = EntityModel::getById(['select' => ['entity_id'], 'id' => $queryParams['inEntity']]);
                $queryParams['inEntity'] = $entity['entity_id'];
            }
            $where[] = 'id in (SELECT user_id FROM users_entities WHERE entity_id = ?)';
            $data[] = $queryParams['inEntity'];
        }
        $requestData = AutoCompleteController::getDataForRequest([
            'search'        => $queryParams['search'],
            'fields'        => $fields,
            'where'         => $where,
            'data'          => $data,
            'fieldsNumber'  => 2,
        ]);

        $users = UserModel::get([
            'select'    => ['id', 'user_id', 'firstname', 'lastname'],
            'where'     => $requestData['where'],
            'data'      => $requestData['data'],
            'orderBy'   => ['lastname'],
            'limit'     => self::LIMIT
        ]);

        $data = [];
        foreach ($users as $value) {
            $primaryEntity = UserModel::getPrimaryEntityById(['id' => $value['id'], 'select' => ['entities.entity_label']]);
            $data[] = [
                'type'                  => 'user',
                'id'                    => empty($queryParams['serial']) ? $value['user_id'] : $value['id'],
                'serialId'              => $value['id'],
                'idToDisplay'           => "{$value['firstname']} {$value['lastname']}",
                'descriptionToDisplay'  => empty($primaryEntity) ? '' : $primaryEntity['entity_label'],
                'otherInfo'             => ''
            ];
        }

        return $response->withJson($data);
    }

    public static function getMaarchParapheurUsers(Request $request, Response $response)
    {
        $data = $request->getQueryParams();
        $check = Validator::stringType()->notEmpty()->validate($data['search']);
        if (!$check) {
            return $response->withStatus(400)->withJson(['errors' => 'search is empty']);
        }

        if (!empty($data['exludeAlreadyConnected'])) {
            $usersAlreadyConnected = UserModel::get([
                'select' => ['external_id->>\'maarchParapheur\' as external_id'],
                'where' => ['external_id->>\'maarchParapheur\' is not null']
            ]);
            $excludedUsers = array_column($usersAlreadyConnected, 'external_id');
        }

        $loadedXml = CoreConfigModel::getXmlLoaded(['path' => 'modules/visa/xml/remoteSignatoryBooks.xml']);

        if ($loadedXml->signatoryBookEnabled == 'maarchParapheur') {
            foreach ($loadedXml->signatoryBook as $value) {
                if ($value->id == "maarchParapheur") {
                    $url      = $value->url;
                    $userId   = $value->userId;
                    $password = $value->password;
                    break;
                }
            }
            if (empty($url) || empty($userId) || empty($password)) {
                return $response->withStatus(500)->withJson(['errors' => 'Maarch Parapheur is not fully configured']);
            }

            $curlResponse = CurlModel::exec([
                'url'           => rtrim($url, '/') . '/rest/autocomplete/users?search='.urlencode($data['search']),
                'basicAuth'     => ['user' => $userId, 'password' => $password],
                'headers'       => ['content-type:application/json'],
                'method'        => 'GET'
            ]);

            if ($curlResponse['code'] != '200') {
                if (!empty($curlResponse['response']['errors'])) {
                    $errors =  $curlResponse['response']['errors'];
                } else {
                    $errors =  $curlResponse['errors'];
                }
                if (empty($errors)) {
                    $errors = 'An error occured. Please check your configuration file.';
                }
                return $response->withStatus(400)->withJson(['errors' => $errors]);
            }

            foreach ($curlResponse['response'] ?? [] as $key => $value) {
                if (!empty($data['exludeAlreadyConnected']) && in_array($value['id'], $excludedUsers)) {
                    unset($curlResponse['response'][$key]);
                    continue;
                }
                $curlResponse['response'][$key]['idToDisplay'] = $value['firstname'] . ' ' . $value['lastname'];
                $curlResponse['response'][$key]['externalId']['maarchParapheur'] = $value['id'];

                // Remove external value in signatureModes
                $array = $curlResponse['response'][$key]['signatureModes'];
                $externalRoleIndex = array_search('external', $array);
                if ($externalRoleIndex !== false) {
                    unset($array[$externalRoleIndex]);
                }
                $array = array_values($array);
                $curlResponse['response'][$key]['signatureModes'] = $array;
            }
            return $response->withJson($curlResponse['response']);
        } else {
            return $response->withStatus(403)->withJson(['errors' => 'maarchParapheur is not enabled']);
        }
    }

    public function getFastParapheurUsers(Request $request, Response $response)
    {
        $queryParams = $request->getQueryParams();
        if (!Validator::notEmpty()->stringType()->length(2)->validate($queryParams['search'])) {
            return $response->withStatus(400)->withJson(['errors' => 'search is empty or too short']);
        }
        $search = $queryParams['search'];

        $config = FastParapheurController::getConfig();
        if (!empty($config['errors'])) {
            return $response->withStatus($config['code'])->withJson(['errors' => $config['errors']]);
        }

        $fpUsers = [];
        $excludedEmails = [];
        $alreadyConnectedUsers = UserModel::get([
            'select' => [
                'external_id->>\'fastParapheur\' as "fastParapheurEmail"',
                'trim(concat(firstname, \' \', lastname)) as name'
            ],
            'where'  => ['external_id->>\'fastParapheur\' is not null']
        ]);

        $subscriberIds = EntityModel::getWithUserEntities([
            'select' => ['entities.external_id->>\'fastParapheurSubscriberId\' as "fastParapheurSubscriberId"'],
            'where'  => ['users_entities.user_id = ?'],
            'data'   => [$GLOBALS['id']]
        ]);
        $subscriberIds = array_values(array_unique(array_column($subscriberIds, 'fastParapheurSubscriberId')));

        if (empty($subscriberIds)) {
            $fpUsers = FastParapheurController::getUsers(['config' => $config]);
            if (!empty($fpUsers['errors'])) {
                return $response->withStatus(400)->withJson(['errors' => $fpUsers['errors']]);
            }
        } else {
            foreach ($subscriberIds as $subscriberId) {
                $subscriberUsers = FastParapheurController::getUsers(['subscriberId' => $subscriberId, 'config' => $config]);
                if (!empty($subscriberUsers['errors'])) {
                    return $response->withStatus(400)->withJson(['errors' => $subscriberUsers['errors']]);
                }
                $fpUsers = array_merge($fpUsers, $subscriberUsers);
            }
        }
        $fpUsersEmails = array_values(array_unique(array_column($fpUsers, 'email')));
        foreach ($fpUsers as $fpUserKey => $fpUser) {
            $emailKey = array_search($fpUser['email'], $fpUsersEmails);
            if ($emailKey !== false) {
                unset($fpUsersEmails[$emailKey]);
            } else {
                unset($fpUsers[$fpUserKey]);
            }
        }
        if (!empty($queryParams['excludeAlreadyConnected'])) {
            $excludedEmails = array_column($alreadyConnectedUsers, 'fastParapheurEmail');
            $fpUsers = array_filter($fpUsers, function ($fpUser) use ($excludedEmails) {
                return !in_array($fpUser['email'], $excludedEmails);
            });
        } else {
            foreach ($alreadyConnectedUsers as $alreadyConnectedUser) {
                foreach ($fpUsers as $key => $fpUser) {
                    if ($fpUser['email'] == $alreadyConnectedUser['fastParapheurEmail']) {
                        $fpUsers[$key]['idToDisplay'] = $alreadyConnectedUser['name'] . ' (' . $fpUsers[$key]['idToDisplay'] . ')';
                    }
                }
            }
        }
        $fpUsers = array_filter($fpUsers, function ($fpUser) use ($search) {
            return mb_stripos($fpUser['email'], $search) > -1 || mb_stripos($fpUser['idToDisplay'], $search) > -1;
        });
        $fpUsers = array_values($fpUsers);

        return $response->withJson($fpUsers);
    }

    public static function getCorrespondents(Request $request, Response $response)
    {
        $queryParams = $request->getQueryParams();

        if (!Validator::stringType()->notEmpty()->validate($queryParams['search'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Query params search is empty']);
        }

        $limit = self::TINY_LIMIT;
        if (!empty($queryParams['limit']) && is_numeric($queryParams['limit'])) {
            $limit = (int)$queryParams['limit'];
        } elseif (!empty($queryParams['limit']) && $queryParams['limit'] == 'none') {
            $limit = 0;
        }

        $searchOnEmails = !empty($queryParams['searchEmails']);

        //Contacts
        $autocompleteContacts = [];
        if (empty($queryParams['noContacts'])) {
            $searchableParameters = ContactParameterModel::get(['select' => ['identifier'], 'where' => ['searchable = ?'], 'data' => [true]]);

            $fields = [];
            foreach ($searchableParameters as $searchableParameter) {
                if (strpos($searchableParameter['identifier'], 'contactCustomField_') !== false) {
                    $customFieldId = explode('_', $searchableParameter['identifier'])[1];
                    $fields[] = "custom_fields->>'{$customFieldId}'";
                } else {
                    $fields[] = ContactController::MAPPING_FIELDS[$searchableParameter['identifier']];
                }
            }

            if ($searchOnEmails && !in_array('email', $fields)) {
                $fields[] = 'email';
            }

            $fieldsNumber = count($fields);
            $fields = AutoCompleteController::getInsensitiveFieldsForRequest(['fields' => $fields]);

            $requestData = AutoCompleteController::getDataForRequest([
                'search'        => $queryParams['search'],
                'fields'        => $fields,
                'where'         => ['enabled = ?'],
                'data'          => [true],
                'fieldsNumber'  => $fieldsNumber
            ]);

            $contacts = ContactModel::get([
                'select'    => ['id', 'email'],
                'where'     => $requestData['where'],
                'data'      => $requestData['data'],
                'orderBy'   => ['company', 'lastname NULLS FIRST'],
                'limit'     => $limit
            ]);

            foreach ($contacts as $contact) {
                $autoContact = ContactController::getAutocompleteFormat(['id' => $contact['id']]);

                if ($searchOnEmails && empty($autoContact['email'])) {
                    $autoContact['email'] = $contact['email'];
                }

                $autocompleteContacts[] = $autoContact;
            }
        }

        //Users
        $autocompleteUsers = [];
        if (empty($queryParams['noUsers'])) {
            $fields = ['firstname', 'lastname'];

            if ($searchOnEmails) {
                $fields[] = 'mail';
            }

            $nbFields = count($fields);

            $fields = AutoCompleteController::getInsensitiveFieldsForRequest(['fields' => $fields]);
            $requestData = AutoCompleteController::getDataForRequest([
                'search'        => $queryParams['search'],
                'fields'        => $fields,
                'where'         => ['status not in (?)', 'mode not in (?)'],
                'data'          => [['DEL', 'SPD'], ['root_invisible', 'rest']],
                'fieldsNumber'  => $nbFields,
            ]);

            $users = UserModel::get([
                'select'    => ['id', 'firstname', 'lastname', 'mail'],
                'where'     => $requestData['where'],
                'data'      => $requestData['data'],
                'orderBy'   => ['lastname'],
                'limit'     => $limit
            ]);

            foreach ($users as $user) {
                $autoUser = [
                    'type'          => 'user',
                    'id'            => $user['id'],
                    'firstname'     => $user['firstname'],
                    'lastname'      => $user['lastname']
                ];

                if ($searchOnEmails) {
                    $autoUser['email'] = $user['mail'];
                }

                $autocompleteUsers[] = $autoUser;
            }
        }

        //Entities
        $autocompleteEntities = [];
        if (empty($queryParams['noEntities'])) {
            $fields = ['entity_label'];

            if ($searchOnEmails) {
                $fields[] = 'email';
            }

            $nbFields = count($fields);

            $fields = AutoCompleteController::getInsensitiveFieldsForRequest(['fields' => $fields]);
            $requestData = AutoCompleteController::getDataForRequest([
                'search'        => $queryParams['search'],
                'fields'        => $fields,
                'where'         => ['enabled = ?'],
                'data'          => ['Y'],
                'fieldsNumber'  => $nbFields,
            ]);

            $entities = EntityModel::get([
                'select'    => [
                    'id', 'entity_id', 'entity_label', 'short_label', 'email', 'address_number', 'address_street', 'address_additional1',
                    'address_additional2', 'address_postcode', 'address_town', 'address_country'
                ],
                'where'     => $requestData['where'],
                'data'      => $requestData['data'],
                'orderBy'   => ['entity_label'],
                'limit'     => $limit
            ]);

            foreach ($entities as $value) {
                $entity = [
                    'type'                  => 'entity',
                    'id'                    => $value['id'],
                    'lastname'              => $value['entity_label'],
                    'firstname'             => '',
                    'addressNumber'         => $value['address_number'],
                    'addressStreet'         => $value['address_street'],
                    'addressAdditional1'    => $value['address_additional1'],
                    'addressAdditional2'    => $value['address_additional2'],
                    'addressPostcode'       => $value['address_postcode'],
                    'addressTown'           => $value['address_town'],
                    'addressCountry'        => $value['address_country']
                ];

                if ($searchOnEmails) {
                    $entity['email'] = $value['email'];
                }

                $autocompleteEntities[] = $entity;
            }
        }

        //Contacts Groups
        $autocompleteContactsGroups = [];
        if (empty($queryParams['noContactsGroups'])) {
            $fields = ['label'];
            $fields = AutoCompleteController::getInsensitiveFieldsForRequest(['fields' => $fields]);
            $hasService = PrivilegeController::hasPrivilege(['privilegeId' => 'admin_contacts', 'userId' => $GLOBALS['id']]);

            $where = [];
            $data = [];
            if ($hasService) {
                $where[] = '1=1';
            } else {
                $userEntities = UserModel::getEntitiesById(['id' => $GLOBALS['id'], 'select' => ['entities.id']]);

                $entitiesId = array_column($userEntities, 'id');
                $where[] = '(owner = ? OR entities @> ?)';
                $data[] = $GLOBALS['id'];
                $data[] = json_encode($entitiesId);
            }

            $requestData = AutoCompleteController::getDataForRequest([
                'search'        => $queryParams['search'],
                'fields'        => $fields,
                'where'         => $where,
                'data'          => $data,
                'fieldsNumber'  => 1,
            ]);

            $contactsGroups = ContactGroupModel::get([
                'select'    => ['id', 'label'],
                'where'     => $requestData['where'],
                'data'      => $requestData['data'],
                'orderBy'   => ['label'],
                'limit'     => $limit
            ]);

            foreach ($contactsGroups as $value) {
                $autocompleteContactsGroups[] = [
                    'type'          => 'contactGroup',
                    'id'            => $value['id'],
                    'lastname'      => $value['label'],
                    'firstname'     => ''
                ];
            }
        }

        $total = count($autocompleteContacts) + count($autocompleteUsers) + count($autocompleteEntities) + count($autocompleteContactsGroups);
        if ($total > $limit) {
            $divider = $total / $limit;
            $autocompleteContacts       = array_slice($autocompleteContacts, 0, round(count($autocompleteContacts) / $divider));
            $autocompleteUsers          = array_slice($autocompleteUsers, 0, round(count($autocompleteUsers) / $divider));
            $autocompleteEntities       = array_slice($autocompleteEntities, 0, round(count($autocompleteEntities) / $divider));
            $autocompleteContactsGroups = array_slice($autocompleteContactsGroups, 0, round(count($autocompleteContactsGroups) / $divider));
        }
        $autocompleteData = array_merge($autocompleteContacts, $autocompleteUsers, $autocompleteEntities, $autocompleteContactsGroups);

        return $response->withJson($autocompleteData);
    }

    public static function getUsersForAdministration(Request $request, Response $response)
    {
        $data = $request->getQueryParams();
        $check = Validator::stringType()->notEmpty()->validate($data['search']);
        if (!$check) {
            return $response->withStatus(400)->withJson(['errors' => 'Bad Request']);
        }

        if (!UserController::isRoot(['id' => $GLOBALS['id']])) {
            $entities = EntityModel::getAllEntitiesByUserId(['userId' => $GLOBALS['id']]);

            $fields = ['users.firstname', 'users.lastname'];
            $fields = AutoCompleteController::getInsensitiveFieldsForRequest(['fields' => $fields]);

            $requestData = AutoCompleteController::getDataForRequest([
                'search'        => $data['search'],
                'fields'        => $fields,
                'where'         => [
                    'users.id = users_entities.user_id',
                    'users_entities.entity_id in (?)',
                    'users.status not in (?)'
                ],
                'data'          => [$entities, ['DEL', 'SPD']],
                'fieldsNumber'  => 2,
            ]);

            $users = DatabaseModel::select([
                'select'    => ['DISTINCT users.user_id', 'users.id', 'users.firstname', 'users.lastname'],
                'table'     => ['users, users_entities'],
                'where'     => $requestData['where'],
                'data'      => $requestData['data'],
                'limit'     => self::LIMIT
            ]);

            if (count($users) < self::LIMIT) {
                $fields = ['users.firstname', 'users.lastname'];
                $fields = AutoCompleteController::getInsensitiveFieldsForRequest(['fields' => $fields]);

                $requestData = AutoCompleteController::getDataForRequest([
                    'search'        => $data['search'],
                    'fields'        => $fields,
                    'where'         => [
                        'users_entities IS NULL',
                        'users.mode not in (?)',
                        'users.status not in (?)'
                    ],
                    'data'          => [['root_invisible'], ['DEL', 'SPD']],
                    'fieldsNumber'  => 2,
                ]);

                $usersNoEntities = DatabaseModel::select([
                    'select'    => ['users.id', 'users.user_id', 'users.firstname', 'users.lastname'],
                    'table'     => ['users', 'users_entities'],
                    'left_join' => ['users.id = users_entities.user_id'],
                    'where'     => $requestData['where'],
                    'data'      => $requestData['data'],
                    'limit'     => (self::LIMIT - count($users))
                ]);

                $users = array_merge($users, $usersNoEntities);
            }
        } else {
            $requestData = AutoCompleteController::getDataForRequest([
                'search'        => $data['search'],
                'fields'        => '(firstname ilike ? OR lastname ilike ?)',
                'where'         => ['status not in (?)', 'mode not in (?)'],
                'data'          => [['DEL', 'SPD'], ['root_invisible']],
                'fieldsNumber'  => 2,
            ]);

            $users = UserModel::get([
                'select'    => ['id', 'user_id', 'firstname', 'lastname'],
                'where'     => $requestData['where'],
                'data'      => $requestData['data'],
                'orderBy'   => ['lastname'],
                'limit'     => self::LIMIT
            ]);
        }

        $data = [];
        foreach ($users as $value) {
            $data[] = [
                'type'          => 'user',
                'id'            => $value['id'],
                'idToDisplay'   => "{$value['firstname']} {$value['lastname']}",
                'otherInfo'     => $value['user_id']
            ];
        }

        return $response->withJson($data);
    }

    public static function getUsersForCircuit(Request $request, Response $response)
    {
        $queryParams = $request->getQueryParams();

        $services = ['visa_documents', 'sign_document'];
        if (!empty($queryParams['circuit']) && $queryParams['circuit'] == 'opinion') {
            $services = ['avis_documents'];
        }

        $allowedGroups = [0];
        $groups = PrivilegeModel::get(['select' => ['DISTINCT group_id'], 'where' => ['service_id in (?)'], 'data' => [$services]]);
        if (!empty($groups)) {
            $groups = array_column($groups, 'group_id');
            $groups = GroupModel::get(['select' => ['id'], 'where' => ['group_id in (?)'], 'data' => [$groups]]);
            $allowedGroups = array_column($groups, 'id');
        }
        $requestData['where'] = [
            '(users.mode = ? OR (usergroup_content.user_id = users.id AND usergroup_content.group_id in (?)))',
            'users.mode not in (?)',
            'users.status not in (?)'
        ];
        $requestData['data'] = ['root_visible', $allowedGroups, ['root_invisible', 'rest'], ['DEL', 'SPD']];

        if (!empty($queryParams['search'])) {
            $fields = ['users.firstname', 'users.lastname'];
            $fields = AutoCompleteController::getInsensitiveFieldsForRequest(['fields' => $fields]);

            $requestData = AutoCompleteController::getDataForRequest([
                'search'        => $queryParams['search'],
                'fields'        => $fields,
                'where'         => $requestData['where'],
                'data'          => $requestData['data'],
                'fieldsNumber'  => 2,
            ]);
        }

        $users = DatabaseModel::select([
            'select'    => ['DISTINCT users.id', 'users.firstname', 'users.lastname'],
            'table'     => ['users, usergroup_content'],
            'where'     => $requestData['where'],
            'data'      => $requestData['data'],
            'order_by'  => ['users.lastname']
        ]);

        $data = [];
        foreach ($users as $value) {
            $entity = UserModel::getPrimaryEntityById(['id' => $value['id'], 'select' => ['entities.short_label']]);
            $data[] = [
                'type'          => 'user',
                'id'            => $value['id'],
                'idToDisplay'   => "{$value['firstname']} {$value['lastname']}",
                'otherInfo'     => $entity['short_label'] ?? null
            ];
        }

        return $response->withJson($data);
    }

    public static function getEntities(Request $request, Response $response)
    {
        $queryParams = $request->getQueryParams();
        if (!Validator::stringType()->notEmpty()->validate($queryParams['search'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Bad Request']);
        }

        $fields = ['entity_label'];
        $fields = AutoCompleteController::getInsensitiveFieldsForRequest(['fields' => $fields]);

        $requestData = AutoCompleteController::getDataForRequest([
            'search'        => $queryParams['search'],
            'fields'        => $fields,
            'where'         => ['enabled = ?'],
            'data'          => ['Y'],
            'fieldsNumber'  => 1,
        ]);

        $entities = EntityModel::get([
            'select'    => ['id', 'entity_id', 'entity_label', 'short_label'],
            'where'     => $requestData['where'],
            'data'      => $requestData['data'],
            'orderBy'   => ['entity_label'],
            'limit'     => self::LIMIT
        ]);

        $data = [];
        foreach ($entities as $value) {
            $data[] = [
                'type'          => 'entity',
                'id'            => empty($queryParams['serial']) ? $value['entity_id'] : $value['id'],
                'serialId'      => $value['id'],
                'idToDisplay'   => $value['entity_label'],
                'otherInfo'     => $value['short_label']
            ];
        }

        return $response->withJson($data);
    }

    public static function getStatuses(Request $request, Response $response)
    {
        $statuses = StatusModel::get(['select' => ['id', 'label_status', 'img_filename']]);

        $data = [];
        foreach ($statuses as $value) {
            $data[] = [
                'type'          => 'status',
                'id'            => $value['id'],
                'idToDisplay'   => $value['label_status'],
                'otherInfo'     => $value['img_filename']
            ];
        }

        return $response->withJson($data);
    }

    public static function getContacts(Request $request, Response $response)
    {
        $data = $request->getQueryParams();

        $check = Validator::stringType()->notEmpty()->validate($data['search']);
        if (!$check) {
            return $response->withStatus(400)->withJson(['errors' => 'Bad Request']);
        }

        $searchableParameters = ContactParameterModel::get(['select' => ['identifier'], 'where' => ['searchable = ?'], 'data' => [true]]);

        $fields = [];
        foreach ($searchableParameters as $searchableParameter) {
            if (strpos($searchableParameter['identifier'], 'contactCustomField_') !== false) {
                $customFieldId = explode('_', $searchableParameter['identifier'])[1];
                $fields[] = "custom_fields->>'{$customFieldId}'";
            } else {
                $fields[] = ContactController::MAPPING_FIELDS[$searchableParameter['identifier']];
            }
        }

        $fieldsNumber = count($fields);
        $fields = AutoCompleteController::getInsensitiveFieldsForRequest(['fields' => $fields]);

        $requestData = AutoCompleteController::getDataForRequest([
            'search'        => $data['search'],
            'fields'        => $fields,
            'where'         => ['enabled = ?'],
            'data'          => [true],
            'fieldsNumber'  => $fieldsNumber
        ]);

        $contacts = ContactModel::get([
            'select'    => ['id', 'firstname', 'lastname', 'company', 'address_number', 'address_street', 'address_town', 'address_postcode'],
            'where'     => $requestData['where'],
            'data'      => $requestData['data'],
            'orderBy'   => ['company', 'lastname NULLS FIRST'],
            'limit'     => 1000
        ]);

        $data = [];
        foreach ($contacts as $contact) {
            $data[] = ContactController::getFormattedContactWithAddress(['contact' => $contact])['contact'];
        }

        return $response->withJson($data);
    }

    public static function getContactsCompany(Request $request, Response $response)
    {
        $queryParams = $request->getQueryParams();

        if (!Validator::stringType()->notEmpty()->validate($queryParams['search'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Query params search is empty']);
        }

        $fields = AutoCompleteController::getInsensitiveFieldsForRequest(['fields' => ['company']]);
        $contacts = ContactModel::get([
            'select'    => [
                'id', 'company', 'address_number as "addressNumber"', 'address_street as "addressStreet"',
                'address_additional1 as "addressAdditional1"', 'address_additional2 as "addressAdditional2"', 'address_postcode as "addressPostcode"',
                'address_town as "addressTown"', 'address_country as "addressCountry"'
            ],
            'where'     => ['enabled = ?', $fields],
            'data'      => [true, $queryParams['search'] . '%'],
            'orderBy'   => ['company', 'lastname'],
            'limit'     => 1
        ]);

        return $response->withJson($contacts);
    }

    public static function getContactsByName(Request $request, Response $response)
    {
        $queryParams = $request->getQueryParams();

        if (!Validator::stringType()->notEmpty()->validate($queryParams['firstname'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Query params firstname is empty or not a string']);
        } elseif (!Validator::stringType()->notEmpty()->validate($queryParams['lastname'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Query params lastname is empty or not a string']);
        }

        $firstnameField = AutoCompleteController::getInsensitiveFieldsForRequest(['fields' => ['firstname']]);
        $lastnameField = AutoCompleteController::getInsensitiveFieldsForRequest(['fields' => ['lastname']]);
        $contacts = ContactModel::get([
            'select'    => [
                'id', 'company', 'firstname', 'lastname', 'address_number as "addressNumber"', 'address_street as "addressStreet"',
                'address_additional1 as "addressAdditional1"', 'address_additional2 as "addressAdditional2"', 'address_postcode as "addressPostcode"',
                'address_town as "addressTown"', 'address_country as "addressCountry"'
            ],
            'where'     => ['enabled = ?', $firstnameField, $lastnameField],
            'data'      => [true, $queryParams['firstname'] . '%', $queryParams['lastname'] . '%'],
            'orderBy'   => ['company', 'lastname'],
            'limit'     => AutoCompleteController::TINY_LIMIT
        ]);

        return $response->withJson($contacts);
    }

    public static function getBanAddresses(Request $request, Response $response)
    {
        $data = $request->getQueryParams();

        $check = Validator::stringType()->notEmpty()->validate($data['address'] ?? '');
        $check = $check && Validator::stringType()->notEmpty()->validate($data['department'] ?? '');
        if (!$check) {
            return $response->withStatus(400)->withJson(['errors' => 'Bad Request']);
        }

        $data['address'] = TextFormatModel::normalize(['string' => str_replace(['*', '~', '-', '\'', '"', '(', ')', ';', '/', '\\'], ' ', $data['address'])]);
        $addressWords = explode(' ', $data['address']);
        foreach ($addressWords as $key => $value) {
            if (mb_strlen($value) <= 2 && !is_numeric($value)) {
                unset($addressWords[$key]);
                continue;
            }
        }
        $data['address'] = implode(' ', $addressWords);
        if (empty($data['address'])) {
            return $response->withJson([]);
        }

        $useSectors = ParameterModel::getById(['id' => 'useSectorsForAddresses', 'select' => ['param_value_int']]);
        if (!empty($useSectors['param_value_int']) && $useSectors['param_value_int'] == 1) {
            $addressFieldNames = ['address_number', 'address_street', 'address_postcode', 'address_town'];
            $fields = AutoCompleteController::getInsensitiveFieldsForRequest([
                'fields' => $addressFieldNames
            ]);
            $requestData = AutoCompleteController::getDataForRequest([
                'search'        => $data['address'],
                'fields'        => $fields,
                'fieldsNumber'  => count($addressFieldNames),
                'where'         => [],
                'data'          => [],
                'itemMinLength' => 1
            ]);
            $hits = ContactAddressSectorModel::get([
                'select'  => ['address_number', 'address_street', 'address_postcode', 'address_town', 'label', 'ban_id'],
                'where'   => $requestData['where'],
                'data'    => $requestData['data'],
                'orderBy' => ['substring(address_number from \'^\d+\')::integer asc', 'length(replace(address_number, \' \', \'\')) asc', 'address_street asc'],
                'limit'   => 100
            ]);
            $addresses = [];
            foreach ($hits as $hit) {
                $addresses[] = [
                    'banId'      => $hit['ban_id'],
                    'lon'        => null,
                    'lat'        => null,
                    'number'     => $hit['address_number'],
                    'afnorName'  => $hit['address_street'],
                    'postalCode' => $hit['address_postcode'],
                    'city'       => $hit['address_town'],
                    'address'    => "{$hit['address_number']} {$hit['address_street']}, {$hit['address_town']} ({$hit['address_postcode']})",
                    'sector'     => $hit['label']
                ];
            }
            return $response->withJson($addresses);
        }

        $customId = CoreConfigModel::getCustomId();
        if (is_dir("custom/{$customId}/referential/ban/indexes/{$data['department']}")) {
            $path = "custom/{$customId}/referential/ban/indexes/{$data['department']}";
        } elseif (is_dir('referential/ban/indexes/' . $data['department'])) {
            $path = 'referential/ban/indexes/' . $data['department'];
        } else {
            return $response->withStatus(400)->withJson(['errors' => 'Department indexes do not exist']);
        }

        \Zend_Search_Lucene_Analysis_Analyzer::setDefault(new \Zend_Search_Lucene_Analysis_Analyzer_Common_Utf8Num_CaseInsensitive());
        \Zend_Search_Lucene_Search_QueryParser::setDefaultOperator(\Zend_Search_Lucene_Search_QueryParser::B_AND);
        \Zend_Search_Lucene_Search_QueryParser::setDefaultEncoding('utf-8');

        $index = \Zend_Search_Lucene::open($path);
        \Zend_Search_Lucene::setResultSetLimit(10);

        foreach ($addressWords as $key => $value) {
            if (mb_strlen($value) <= 2 && !is_numeric($value)) {
                unset($addressWords[$key]);
                continue;
            }
            if (mb_strlen($value) >= 3 && $value != 'rue' && $value != 'avenue' && $value != 'boulevard') {
                $addressWords[$key] .= '*';
            }
        }
        $data['address'] = implode(' ', $addressWords);
        if (empty($data['address'])) {
            return $response->withJson([]);
        }

        $hits = $index->find($data['address']);

        $addresses = [];
        foreach ($hits as $key => $hit) {
            $sector = ContactController::getAddressSector([
                'addressNumber'   => $hit->streetNumber,
                'addressStreet'   => $hit->afnorName,
                'addressPostcode' => $hit->postalCode,
                'addressTown'     => $hit->city
            ]);
            $addresses[] = [
                'banId'         => $hit->banId,
                'lon'           => $hit->lon,
                'lat'           => $hit->lat,
                'number'        => $hit->streetNumber,
                'afnorName'     => $hit->afnorName,
                'postalCode'    => $hit->postalCode,
                'city'          => $hit->city,
                'address'       => "{$hit->streetNumber} {$hit->afnorName}, {$hit->city} ({$hit->postalCode})",
                'sector'        => $sector['label'] ?? null
            ];
        }

        return $response->withJson($addresses);
    }

    public static function getOuM2MAnnuary(Request $request, Response $response)
    {
        $data = $request->getQueryParams();

        $check = Validator::stringType()->notEmpty()->validate($data['company']);
        if (!$check) {
            return $response->withStatus(400)->withJson(['errors' => 'Query company is empty']);
        }

        $control = AnnuaryController::getAnnuaries();
        if (!isset($control['annuaries'])) {
            if (isset($control['errors'])) {
                return $response->withStatus(400)->withJson(['errors' => $control['errors']]);
            } elseif (isset($control['success'])) {
                return $response->withJson([]);
            }
        }

        $unitOrganizations = [];
        if (!empty($control['annuaries'])) {
            foreach ($control['annuaries'] as $annuary) {
                $ldap = @ldap_connect($annuary['uri']);
                if ($ldap === false) {
                    continue;
                }
                ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION, 3);
                ldap_set_option($ldap, LDAP_OPT_REFERRALS, 0);
                ldap_set_option($ldap, LDAP_OPT_NETWORK_TIMEOUT, 5);

                $search = @ldap_search($ldap, $annuary['baseDN'], "(ou=*{$data['company']}*)", ['ou', 'postOfficeBox', 'destinationIndicator', 'labeledURI']);
                if ($search === false) {
                    continue;
                }
                $entries = ldap_get_entries($ldap, $search);

                foreach ($entries as $key => $value) {
                    if (!is_numeric($key)) {
                        continue;
                    }
                    if (!empty($value['postofficebox'])) {
                        $unitOrganizations[] = [
                            'communicationValue' => $value['postofficebox'][0],
                            'businessIdValue'    => $value['destinationindicator'][0],
                            'unitOrganization'   => "{$value['ou'][0]} ({$value['postofficebox'][0]})"
                        ];
                    }
                    if (!empty($value['labeleduri'])) {
                        $unitOrganizations[] = [
                            'communicationValue' => $value['labeleduri'][0],
                            'businessIdValue'    => $value['destinationindicator'][0],
                            'unitOrganization'   => "{$value['ou'][0]} ({$value['labeleduri'][0]})"
                        ];
                    }
                }

                break;
            }
        }

        return $response->withJson($unitOrganizations);
    }

    public static function getAvailableContactsForM2M(Request $request, Response $response)
    {
        $queryParams = $request->getQueryParams();
        if (!Validator::stringType()->notEmpty()->validate($queryParams['search'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Query params search is empty']);
        }

        $autocompleteData = [];
        $searchableParameters = ContactParameterModel::get(['select' => ['identifier'], 'where' => ['searchable = ?'], 'data' => [true]]);

        $fields = [];
        foreach ($searchableParameters as $searchableParameter) {
            if (strpos($searchableParameter['identifier'], 'contactCustomField_') !== false) {
                $customFieldId = explode('_', $searchableParameter['identifier'])[1];
                $fields[] = "custom_fields->>'{$customFieldId}'";
            } else {
                $fields[] = ContactController::MAPPING_FIELDS[$searchableParameter['identifier']];
            }
        }

        $fieldsNumber = count($fields);
        $fields = AutoCompleteController::getInsensitiveFieldsForRequest(['fields' => $fields]);

        $requestData = AutoCompleteController::getDataForRequest([
            'search'        => $queryParams['search'],
            'fields'        => $fields,
            'where'         => ['enabled = ?', "external_id->>'m2m' is not null", "external_id->>'m2m' != ''", "(communication_means->>'url' is not null OR communication_means->>'email' is not null)"],
            'data'          => [true],
            'fieldsNumber'  => $fieldsNumber
        ]);

        $contacts = ContactModel::get([
            'select'    => ['id', 'communication_means', 'external_id'],
            'where'     => $requestData['where'],
            'data'      => $requestData['data'],
            'orderBy'   => ['company', 'lastname NULLS FIRST'],
            'limit'     => self::TINY_LIMIT
        ]);


        foreach ($contacts as $contact) {
            $autoContact = ContactController::getAutocompleteFormat(['id' => $contact['id']]);

            $externalId = json_decode($contact['external_id'], true);
            $communicationMeans = json_decode($contact['communication_means'], true);
            unset($communicationMeans['password']);
            $autoContact['m2m'] = $externalId['m2m'];
            $autoContact['communicationMeans'] = $communicationMeans ?? null;
            $autocompleteData[] = $autoContact;
        }

        return $response->withJson($autocompleteData);
    }

    public static function getBusinessIdM2MAnnuary(Request $request, Response $response)
    {
        $data = $request->getQueryParams();

        $check = Validator::stringType()->notEmpty()->validate($data['communicationValue']);
        if (!$check) {
            return $response->withStatus(400)->withJson(['errors' => 'Query communicationValue is empty']);
        }

        $control = AnnuaryController::getAnnuaries();
        if (!isset($control['annuaries'])) {
            if (isset($control['errors'])) {
                return $response->withStatus(400)->withJson(['errors' => $control['errors']]);
            } elseif (isset($control['success'])) {
                return $response->withJson([]);
            }
        }

        $unitOrganizations = [];
        foreach ($control['annuaries'] as $annuary) {
            $ldap = @ldap_connect($annuary['uri']);
            if ($ldap === false) {
                $error = 'Ldap connect failed : uri is maybe wrong';
                continue;
            }
            ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION, 3);
            ldap_set_option($ldap, LDAP_OPT_REFERRALS, 0);
            ldap_set_option($ldap, LDAP_OPT_NETWORK_TIMEOUT, 5);

            if (filter_var($data['communicationValue'], FILTER_VALIDATE_EMAIL)) {
                $search = @ldap_search($ldap, $annuary['baseDN'], "(postofficebox={$data['communicationValue']})", ['destinationIndicator']);
            } else {
                $search = @ldap_search($ldap, $annuary['baseDN'], "(labeleduri={$data['communicationValue']})", ['destinationIndicator']);
            }
            if ($search === false) {
                $error = 'Ldap search failed : baseDN is maybe wrong => ' . ldap_error($ldap);
                continue;
            }
            $entriesOu = ldap_get_entries($ldap, $search);
            foreach ($entriesOu as $keyOu => $valueOu) {
                if (!is_numeric($keyOu)) {
                    continue;
                }
                $siret   = $valueOu['destinationindicator'][0];
                $search  = @ldap_search($ldap, $valueOu['dn'], "(cn=*)", ['cn', 'initials', 'entryUUID']);
                $entries = ldap_get_entries($ldap, $search);

                foreach ($entries as $key => $value) {
                    if (!is_numeric($key)) {
                        continue;
                    }
                    $unitOrganizations[] = [
                        'entryuuid'        => $value['entryuuid'][0],
                        'businessIdValue'  => $siret . '/' . $value['initials'][0],
                        'unitOrganization' => "{$value['cn'][0]} - {$siret}/{$value['initials'][0]}"
                    ];
                }
            }

        }
        return $response->withJson($unitOrganizations);
    }

    public static function getFolders(Request $request, Response $response)
    {
        $data = $request->getQueryParams();

        if (!Validator::stringType()->notEmpty()->validate($data['search'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Query params search is empty']);
        }

        $scopedFolders = FolderController::getScopeFolders(['login' => $GLOBALS['login']]);
        if (empty($scopedFolders)) {
            return $response->withJson([]);
        }

        $arrScopedFoldersIds = array_column($scopedFolders, 'id');

        $fields = AutoCompleteController::getInsensitiveFieldsForRequest(['fields' => ['label']]);

        $selectedFolders = FolderModel::get([
            'where'    => ["{$fields} AND id in (?)"],
            'data'     => [ '%'.$data['search'].'%', $arrScopedFoldersIds],
            'orderBy'  => ['label']
        ]);

        $data = [];
        foreach ($selectedFolders as $value) {
            $data[] = [
                'id'            => $value['id'],
                'idToDisplay'   => $value['label'],
                'isPublic'      => $value['public'],
                'otherInfo'     => ''
            ];
        }

        return $response->withJson($data);
    }

    public static function getTags(Request $request, Response $response)
    {
        $data = $request->getQueryParams();

        if (!Validator::stringType()->notEmpty()->validate($data['search'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Query params search is empty']);
        }

        $fields = ['label'];
        $fields = AutoCompleteController::getInsensitiveFieldsForRequest(['fields' => $fields]);

        $requestData = AutoCompleteController::getDataForRequest([
            'search'        => $data['search'],
            'fields'        => $fields,
            'where'         => ['1 = ?'],
            'data'          => ['1'],
            'fieldsNumber'  => 1,
        ]);

        $tags = TagModel::get([
            'select'    => ['id', 'label'],
            'where'     => $requestData['where'],
            'data'      => $requestData['data'],
            'orderBy'   => ['label'],
            'limit'     => self::LIMIT
        ]);

        $data = [];
        foreach ($tags as $value) {
            $data[] = [
                'id'            => $value['id'],
                'idToDisplay'   => $value['label']
            ];
        }

        return $response->withJson($data);
    }

    public function getPostcodes(Request $request, Response $response)
    {
        $queryParams = $request->getQueryParams();

        if (empty($queryParams['postcode']) && empty($queryParams['town'])) {
            return $response->withJson(['postcodes' => []]);
        }

        if (!empty($queryParams['postcode']) && !Validator::stringType()->validate($queryParams['postcode'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Query postcode is not a string']);
        }
        if (!empty($queryParams['town']) && !Validator::stringType()->validate($queryParams['town'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Query town is not a string']);
        }

        if (!is_file('referential/codes-postaux.json') || !is_readable('referential/codes-postaux.json')) {
            return $response->withStatus(400)->withJson(['errors' => 'Cannot read postcodes']);
        }

        $postcodesContent = file_get_contents('referential/codes-postaux.json');
        if ($postcodesContent === false) {
            return $response->withStatus(400)->withJson(['errors' => 'Cannot read postcodes']);
        }
        $postcodes = json_decode($postcodesContent, true);

        $postcodes = array_map(function ($postcode) {
            return [
                'town'     => $postcode['nomCommune'],
                'label'    => $postcode['libelleAcheminement'],
                'postcode' => $postcode['codePostal']
            ];
        }, $postcodes);

        $searchTowns = [];
        if (!empty($queryParams['town'])) {
            $searchTowns = strtoupper(TextFormatModel::normalize(['string' => $queryParams['town']]));
            $searchTowns = trim(str_replace('-', ' ', $searchTowns));
            $searchTowns = explode(' ', $searchTowns);
        }
        $searchPostcode = null;
        if (!empty($queryParams['postcode'])) {
            $searchPostcode = strtoupper(TextFormatModel::normalize(['string' => $queryParams['postcode']]));
        }
        $postcodes = array_values(array_filter($postcodes, function ($code) use ($searchPostcode, $searchTowns) {
            $townFound = !empty($searchTowns);
            foreach ($searchTowns as $searchTown) {
                if ($searchTown == 'ST' || $searchTown == 'SAINT') {
                    if (strpos($code['label'], 'ST') === false && strpos($code['label'], 'SAINT') === false) {
                        $townFound = false;
                        break;
                    }
                } elseif ($searchTown == 'STE' || $searchTown == 'SAINTE') {
                    if (strpos($code['label'], 'STE') === false && strpos($code['label'], 'SAINTE') === false) {
                        $townFound = false;
                        break;
                    }
                } elseif (strpos($code['label'], $searchTown) === false) {
                    $townFound = false;
                    break;
                }
            }
            return $townFound || (!empty($searchPostcode) && strpos($code['postcode'], $searchPostcode) === 0);
        }));

        $postcodes = array_slice($postcodes, 0, AutoCompleteController::LIMIT);

        return $response->withJson(['postcodes' => $postcodes]);
    }

    public static function getDataForRequest(array $args)
    {
        ValidatorModel::notEmpty($args, ['search', 'fields', 'fieldsNumber']);
        ValidatorModel::stringType($args, ['search', 'fields']);
        ValidatorModel::arrayType($args, ['where', 'data']);
        ValidatorModel::intType($args, ['fieldsNumber', 'itemMinLength']);
        ValidatorModel::boolType($args, ['longField']);

        $searchItems   = preg_split('/\s+/', $args['search']);
        $itemMinLength = $args['itemMinLength'] ?? 2;

        foreach ($searchItems as $keyItem => $item) {
            if (mb_strlen($item) >= $itemMinLength) {
                $args['where'][] = $args['fields'];

                $isIncluded = false;
                foreach ($searchItems as $key => $value) {
                    if ($keyItem == $key) {
                        continue;
                    }
                    if (strpos($value, $item) === 0) {
                        $isIncluded = true;
                    }
                }
                for ($i = 0; $i < $args['fieldsNumber']; $i++) {
                    if (!empty($args['longField'])) {
                        $args['data'][] = ($isIncluded ? "%{$item} %" : "%{$item}%");
                    } else {
                        $args['data'][] = ($isIncluded ? "%{$item}" : "%{$item}%");
                    }
                }
            }
        }

        return ['where' => $args['where'], 'data' => $args['data']];
    }

    public static function getInsensitiveFieldsForRequest(array $args)
    {
        ValidatorModel::notEmpty($args, ['fields']);
        ValidatorModel::arrayType($args, ['fields']);

        $fields = [];
        foreach ($args['fields'] as $key => $field) {
            $fields[$key] = "unaccent({$field}::text)";
            $fields[$key] .= " ilike unaccent(?::text)";
        }
        $fields = implode(' OR ', $fields);
        $fields = "({$fields})";

        return $fields;
    }
}
