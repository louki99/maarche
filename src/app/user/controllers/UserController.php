<?php

/**
* Copyright Maarch since 2008 under licence GPLv3.
* See LICENCE.txt file at the root folder for more details.
* This file is part of Maarch software.
*
*/

/**
* @brief User Controller
* @author dev@maarch.org
*/

namespace User\controllers;

use Basket\models\BasketModel;
use Basket\models\GroupBasketModel;
use Basket\models\RedirectBasketModel;
use Configuration\models\ConfigurationModel;
use Contact\models\ContactGroupListModel;
use Contact\models\ContactGroupModel;
use ContentManagement\controllers\DocumentEditorController;
use ContentManagement\controllers\MergeController;
use Docserver\controllers\DocserverController;
use Docserver\models\DocserverModel;
use Email\controllers\EmailController;
use Entity\models\EntityModel;
use Entity\models\ListInstanceModel;
use Entity\models\ListTemplateItemModel;
use Entity\models\ListTemplateModel;
use Firebase\JWT\JWT;
use Group\controllers\PrivilegeController;
use Group\models\GroupModel;
use History\controllers\HistoryController;
use History\models\HistoryModel;
use Notification\controllers\NotificationsEventsController;
use Parameter\models\ParameterModel;
use Resource\controllers\ResController;
use Resource\controllers\StoreController;
use Resource\models\ResModel;
use Resource\models\UserFollowedResourceModel;
use Respect\Validation\Validator;
use Slim\Psr7\Request;
use SrcCore\http\Response;
use SrcCore\controllers\AuthenticationController;
use SrcCore\controllers\PasswordController;
use SrcCore\controllers\UrlController;
use SrcCore\models\AuthenticationModel;
use SrcCore\models\CoreConfigModel;
use SrcCore\models\DatabaseModel;
use SrcCore\models\PasswordModel;
use SrcCore\models\ValidatorModel;
use Template\models\TemplateModel;
use User\models\UserBasketPreferenceModel;
use User\models\UserEmailSignatureModel;
use User\models\UserEntityModel;
use User\models\UserGroupModel;
use User\models\UserModel;
use User\models\UserSignatureModel;

class UserController
{
    const ALTERNATIVES_CONNECTIONS_METHODS = ['sso', 'cas', 'ldap', 'keycloak', 'shibboleth', 'azure_saml'];

    public function get(Request $request, Response $response)
    {
        if (!PrivilegeController::hasPrivilege(['privilegeId' => 'admin_users', 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }

        if (UserController::isRoot(['id' => $GLOBALS['id']])) {
            $users = UserModel::get([
                'select'    => ['id', 'user_id', 'firstname', 'lastname', 'status', 'mail', 'mode'],
                'where'     => ['status != ?'],
                'data'      => ['DEL']
            ]);
        } else {
            $entities = EntityModel::getAllEntitiesByUserId(['userId' => $GLOBALS['id']]);
            $users = [];
            if (!empty($entities)) {
                $users = UserEntityModel::getWithUsers([
                    'select'    => ['DISTINCT users.id', 'users.user_id', 'firstname', 'lastname', 'status', 'mail', 'mode'],
                    'where'     => ['users_entities.entity_id in (?)', 'status != ?'],
                    'data'      => [$entities, 'DEL']
                ]);
            }
            $usersNoEntities = UserEntityModel::getUsersWithoutEntities(['select' => ['id', 'users.user_id', 'firstname', 'lastname', 'status', 'mail', 'mode']]);
            $users = array_merge($users, $usersNoEntities);
        }

        $quota = [];
        $userQuota = ParameterModel::getById(['id' => 'user_quota', 'select' => ['param_value_int']]);
        if (!empty($userQuota['param_value_int'])) {
            $activeUser = UserModel::get(['select' => ['count(1)'], 'where' => ['status = ?', 'mode != ?'], 'data' => ['OK', 'root_invisible']]);
            $inactiveUser = UserModel::get(['select' => ['count(1)'], 'where' => ['status = ?', 'mode != ?'], 'data' => ['SPD', 'root_invisible']]);
            $quota = ['actives' => $activeUser[0]['count'], 'inactives' => $inactiveUser[0]['count'], 'userQuota' => $userQuota['param_value_int']];
        }

        return $response->withJson(['users' => $users, 'quota' => $quota]);
    }

    public function getById(Request $request, Response $response, array $args)
    {
        $user = UserModel::getById(['id' => $args['id'], 'select' => ['id', 'firstname', 'lastname', 'status', 'mail', 'phone']]);
        if (empty($user)) {
            return $response->withStatus(400)->withJson(['errors' => 'User does not exist']);
        }
        $user['enabled'] = $user['status'] != 'SPD';
        unset($user['status']);

        $primaryEntity = UserModel::getPrimaryEntityById(['id' => $args['id'], 'select' => ['entity_label']]);
        $user['department'] = $primaryEntity['entity_label'];

        if ($GLOBALS['id'] != $args['id'] && !PrivilegeController::hasPrivilege(['privilegeId' => 'view_personal_data', 'userId' => $GLOBALS['id']])) {
            unset($user['phone']);
        }

        return $response->withJson($user);
    }

    public function getDetailledById(Request $request, Response $response, array $args)
    {
        $error = $this->hasUsersRights(['id' => $args['id']]);
        if (!empty($error['error'])) {
            return $response->withStatus($error['status'])->withJson(['errors' => $error['error']]);
        }

        $user = UserModel::getById(['id' => $args['id'], 'select' => ['id', 'user_id', 'firstname', 'lastname', 'status', 'phone', 'mail', 'initials', 'mode', 'authorized_api', 'external_id', 'absence']]);
        $user['external_id']        = json_decode($user['external_id'], true);
        $user['authorizedApi']      = json_decode($user['authorized_api'], true);
        if (!empty($user['absence'])) {
            $user['absence'] = json_decode($user['absence'], true);
        }
        unset($user['authorized_api']);

        if ($GLOBALS['id'] == $args['id'] || PrivilegeController::hasPrivilege(['privilegeId' => 'view_personal_data', 'userId' => $GLOBALS['id']])) {
            $user['signatures'] = UserSignatureModel::getByUserSerialId(['userSerialid' => $args['id']]);
            $user['emailSignatures'] = UserEmailSignatureModel::getByUserId(['userId' => $user['id']]);
        } else {
            $user['signatures'] = [];
            $user['emailSignatures'] = [];
            unset($user['phone']);
        }

        $user['groups']             = UserModel::getGroupsById(['id' => $args['id']]);
        $user['allGroups']          = GroupModel::getAvailableGroupsByUserId(['userId' => $user['id'], 'administratorId' => $GLOBALS['id']]);
        $user['entities']           = UserModel::getEntitiesById(['id' => $args['id'], 'select' => ['entities.id', 'users_entities.entity_id', 'entities.entity_label', 'users_entities.user_role', 'users_entities.primary_entity'], 'orderBy' => ['users_entities.primary_entity DESC']]);
        $user['allEntities']        = EntityModel::getAvailableEntitiesForAdministratorByUserId(['userId' => $user['user_id'], 'administratorUserId' => $GLOBALS['login']]);
        $user['baskets']            = BasketModel::getBasketsByLogin(['login' => $user['user_id']]);
        $user['assignedBaskets']    = RedirectBasketModel::getAssignedBasketsByUserId(['userId' => $user['id']]);
        $user['redirectedBaskets']  = RedirectBasketModel::getRedirectedBasketsByUserId(['userId' => $user['id']]);
        $user['history']            = HistoryModel::get(['select' => ['record_id', 'event_date', 'info', 'remote_ip'], 'where' => ['user_id = ?'], 'data' => [$args['id']], 'orderBy' => ['event_date DESC'], 'limit' => 500]);
        $user['canModifyPassword']                      = false;
        $user['canSendActivationNotification']          = false;
        $user['canLinkToExternalSignatoryBook']         = false;

        if ($user['mode'] == 'rest') {
            $user['canModifyPassword'] = true;
        }
        $loggingMethod = CoreConfigModel::getLoggingMethod();
        if ($user['mode'] != 'rest' && $loggingMethod['id'] == 'standard') {
            $user['canSendActivationNotification'] = true;
        }

        $loadedXml = CoreConfigModel::getXmlLoaded(['path' => 'modules/visa/xml/remoteSignatoryBooks.xml']);
        if (!empty($loadedXml)) {
            $signatoryBookEnabled = (string)$loadedXml->signatoryBookEnabled ?? null;
            if ($user['mode'] != 'rest' && empty($user['external_id'][$signatoryBookEnabled]) && ($signatoryBookEnabled == 'maarchParapheur' || $signatoryBookEnabled == 'fastParapheur')) {
                if ($signatoryBookEnabled == 'fastParapheur') {
                    $fastParapheurBlock = $loadedXml->xpath('//signatoryBook[id=\'fastParapheur\']')[0] ?? null;
                    if (!empty($fastParapheurBlock)) {
                        $user['canLinkToExternalSignatoryBook'] = filter_var((string)$fastParapheurBlock->integratedWorkflow, FILTER_VALIDATE_BOOLEAN) ?? false;
                    }
                } else {
                    $user['canLinkToExternalSignatoryBook'] = true;
                }
            }
        }

        return $response->withJson($user);
    }

    public function create(Request $request, Response $response)
    {
        if (!PrivilegeController::hasPrivilege(['privilegeId' => 'admin_users', 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }

        $body = $request->getParsedBody();

        if (empty($body)) {
            return $response->withStatus(400)->withJson(['errors' => 'Body is empty']);
        } elseif (!Validator::stringType()->length(1, 128)->notEmpty()->validate($body['userId'] ?? null) || !preg_match("/^[\w.@-]*$/", $body['userId'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body userId is empty, not a string or not valid']);
        } elseif (!Validator::stringType()->length(1, 255)->notEmpty()->validate($body['firstname'] ?? null)) {
            return $response->withStatus(400)->withJson(['errors' => 'Body firstname is empty or not a string']);
        } elseif (!Validator::stringType()->length(1, 255)->notEmpty()->validate($body['lastname'] ?? null)) {
            return $response->withStatus(400)->withJson(['errors' => 'Body lastname is empty or not a string']);
        } elseif (!Validator::stringType()->length(0, 32)->validate($body['initials'] ?? '')) {
            return $response->withStatus(400)->withJson(['errors' => 'Body initials is too long']);
        } elseif (!Validator::stringType()->length(1, 255)->notEmpty()->validate($body['mail'] ?? null) || !filter_var($body['mail'], FILTER_VALIDATE_EMAIL)) {
            return $response->withStatus(400)->withJson(['errors' => 'Body mail is empty or not valid']);
        } elseif (PrivilegeController::hasPrivilege(['privilegeId' => 'manage_personal_data', 'userId' => $GLOBALS['id']]) && !empty($body['phone']) && (!preg_match("/\+?((|\ |\.|\(|\)|\-)?(\d)*)*\d$/", $body['phone']) || !Validator::stringType()->length(0, 32)->validate($body['phone'] ?? ''))) {
            return $response->withStatus(400)->withJson(['errors' => 'Body phone is not valid']);
        }

        $loggingMethod = CoreConfigModel::getLoggingMethod();
        $existingUser = UserModel::getByLowerLogin(['login' => $body['userId'], 'select' => ['id', 'status', 'mail']]);

        if (!empty($existingUser) && $existingUser['status'] == 'DEL') {
            UserModel::update([
                'set'   => [
                    'status'    => 'OK',
                    'password'  => AuthenticationModel::getPasswordHash(AuthenticationModel::generatePassword()),
                ],
                'where' => ['id = ?'],
                'data'  => [$existingUser['id']]
            ]);

            if ($loggingMethod['id'] == 'standard') {
                AuthenticationController::sendAccountActivationNotification(['userId' => $existingUser['id'], 'userEmail' => $existingUser['mail']]);
            }

            return $response->withJson(['id' => $existingUser['id']]);
        } elseif (!empty($existingUser)) {
            return $response->withStatus(400)->withJson(['errors' => _USER_ID_ALREADY_EXISTS]);
        }

        if (!PrivilegeController::hasPrivilege(['privilegeId' => 'manage_personal_data', 'userId' => $GLOBALS['id']])) {
            $body['phone'] = null;
        }

        $modes = ['standard', 'rest', 'root_visible', 'root_invisible'];
        if (empty($body['mode']) || !in_array($body['mode'], $modes)) {
            $body['mode'] = 'standard';
        }

        if (in_array($body['mode'], ['root_visible', 'root_invisible']) && !UserController::isRoot(['id' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }

        $preferences = ['documentEdition' => 'java'];
        $allowedMethods = DocumentEditorController::getAllowedMethods();
        if (in_array('onlyoffice', $allowedMethods)) {
            $preferences = ['documentEdition' => 'onlyoffice'];
        }
        $body['preferences'] = json_encode($preferences);

        $id = UserModel::create(['user' => $body]);

        $userQuota = ParameterModel::getById(['id' => 'user_quota', 'select' => ['param_value_int']]);
        if (!empty($userQuota['param_value_int'])) {
            $activeUser = UserModel::get(['select' => ['count(1)'], 'where' => ['status = ?', 'mode != ?'], 'data' => ['OK', 'root_invisible']]);
            if ($activeUser[0]['count'] > $userQuota['param_value_int']) {
                NotificationsEventsController::fillEventStack(['eventId' => 'user_quota', 'tableName' => 'users', 'recordId' => 'quota_exceed', 'userId' => $GLOBALS['id'], 'info' => _QUOTA_EXCEEDED]);
            }
        }

        if ($loggingMethod['id'] == 'standard') {
            AuthenticationController::sendAccountActivationNotification(['userId' => $id, 'userEmail' => $body['mail']]);
        }

        HistoryController::add([
            'tableName'    => 'users',
            'recordId'     => $GLOBALS['id'],
            'eventType'    => 'ADD',
            'eventId'      => 'userCreation',
            'info'         => _USER_CREATED . " {$body['userId']}"
        ]);

        return $response->withJson(['id' => $id]);
    }

    public function update(Request $request, Response $response, array $aArgs)
    {
        $error = $this->hasUsersRights(['id' => $aArgs['id']]);
        if (!empty($error['error'])) {
            return $response->withStatus($error['status'])->withJson(['errors' => $error['error']]);
        }

        $body = $request->getParsedBody();

        if (!Validator::stringType()->length(1, 255)->notEmpty()->validate($body['firstname'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body firstname is empty or not a string']);
        } elseif (!Validator::stringType()->length(1, 255)->notEmpty()->validate($body['lastname'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body lastname is empty or not a string']);
        } elseif (!Validator::stringType()->length(0, 32)->validate($body['initials'] ?? '')) {
            return $response->withStatus(400)->withJson(['errors' => 'Body initials is too long']);
        } elseif (!empty($body['mail']) && !filter_var($body['mail'], FILTER_VALIDATE_EMAIL) && Validator::stringType()->length(1, 255)->notEmpty()->validate($body['mail'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body mail is not correct']);
        } elseif (PrivilegeController::hasPrivilege(['privilegeId' => 'manage_personal_data', 'userId' => $GLOBALS['id']]) && !empty($body['phone']) && (!preg_match("/\+?((|\ |\.|\(|\)|\-)?(\d)*)*\d$/", $body['phone']) || !Validator::stringType()->length(0, 32)->validate($body['phone'] ?? ''))) {
            return $response->withStatus(400)->withJson(['errors' => 'Body phone is not correct']);
        }

        $user = UserModel::getById(['id' => $aArgs['id'], 'select' => ['status', 'mode']]);

        $set = [
            'firstname' => $body['firstname'],
            'lastname'  => $body['lastname'],
            'mail'      => $body['mail'],
            'initials'  => $body['initials'],
        ];

        if (PrivilegeController::hasPrivilege(['privilegeId' => 'manage_personal_data', 'userId' => $GLOBALS['id']])) {
            $set['phone'] = $body['phone'];
        }

        if (!empty($body['status']) && $body['status'] == 'OK') {
            $set['status'] = 'OK';
        }
        if (!empty($body['mode']) && $user['mode'] != $body['mode']) {
            if ((in_array($body['mode'], ['root_visible', 'root_invisible']) || in_array($user['mode'], ['root_visible', 'root_invisible'])) && !UserController::isRoot(['id' => $GLOBALS['id']])) {
                return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
            }
            $set['mode'] = $body['mode'];
        }

        if ($body['mode'] == 'rest' && isset($body['authorizedApi']) && is_array($body['authorizedApi'])) {
            foreach ($body['authorizedApi'] as $value) {
                if (strpos($value, 'GET/') !== 0 && strpos($value, 'POST/') !== 0 && strpos($value, 'PUT/') !== 0 && strpos($value, 'DELETE/') !== 0) {
                    return $response->withStatus(400)->withJson(['errors' => 'Body authorizedApi is not well formatted', 'lang' => 'authorizedRoutesNotWellFormatted']);
                }
            }
            $set['authorized_api'] = json_encode($body['authorizedApi']);
        }

        $userQuota = ParameterModel::getById(['id' => 'user_quota', 'select' => ['param_value_int']]);

        UserModel::update([
            'set'   => $set,
            'where' => ['id = ?'],
            'data'  => [$aArgs['id']]
        ]);

        if (!empty($userQuota['param_value_int'])) {
            if ($user['status'] == 'SPD' && $body['status'] == 'OK') {
                $activeUser = UserModel::get(['select' => ['count(1)'], 'where' => ['status = ?', 'mode != ?'], 'data' => ['OK', 'root_invisible']]);
                if ($activeUser[0]['count'] > $userQuota['param_value_int']) {
                    NotificationsEventsController::fillEventStack(['eventId' => 'user_quota', 'tableName' => 'users', 'recordId' => 'quota_exceed', 'userId' => $GLOBALS['id'], 'info' => _QUOTA_EXCEEDED]);
                }
            }
        }

        HistoryController::add([
            'tableName'    => 'users',
            'recordId'     => $GLOBALS['id'],
            'eventType'    => 'UP',
            'eventId'      => 'userModification',
            'info'         => _USER_UPDATED . " {$body['firstname']} {$body['lastname']}"
        ]);

        return $response->withStatus(204);
    }

    public function isDeletable(Request $request, Response $response, array $aArgs)
    {
        $error = $this->hasUsersRights(['id' => $aArgs['id'], 'delete' => true, 'himself' => true]);
        if (!empty($error['error'])) {
            return $response->withStatus($error['status'])->withJson(['errors' => $error['error']]);
        }

        $isListInstanceDeletable = true;
        $isListTemplateDeletable = true;

        $listInstanceEntities = [];
        $listInstanceResIds = [];
        $listInstances = ListInstanceModel::getWhenOpenMailsByUserId(['select' => ['listinstance.res_id', 'res_letterbox.destination'], 'userId' => $aArgs['id'], 'itemMode' => 'dest']);
        foreach ($listInstances as $listInstance) {
            if (!ResController::hasRightByResId(['resId' => [$listInstance['res_id']], 'userId' => $GLOBALS['id']])) {
                $isListInstanceDeletable = false;
            }
            $listInstanceResIds[] = $listInstance['res_id'];
            if (!empty($listInstance['destination'])) {
                $listInstanceEntities[] = $listInstance['destination'];
            }
        }

        $listTemplateEntities = [];
        $listTemplates = ListTemplateModel::getWithItems([
            'select'    => ['entity_id', 'title'],
            'where'     => ['item_id = ?', 'type = ?', 'item_mode = ?', 'item_type = ?', 'entity_id is not null'],
            'data'      => [$aArgs['id'], 'diffusionList', 'dest', 'user']
        ]);
        $allEntities = EntityModel::getAllEntitiesByUserId(['userId' => $GLOBALS['id']]);
        if (!empty($allEntities)) {
            $allEntities = EntityModel::get(['select' => ['id'], 'where' => ['entity_id in (?)'], 'data' => [$allEntities]]);
            $allEntities = array_column($allEntities, 'id');
        }
        foreach ($listTemplates as $listTemplate) {
            if (!in_array($listTemplate['entity_id'], $allEntities)) {
                $isListTemplateDeletable = false;
            }
            $listTemplateEntities[] = $listTemplate['entity_id'];
        }

        if (!$isListInstanceDeletable || !$isListTemplateDeletable) {
            $formattedLIEntities = [];
            $listInstanceEntities = array_unique($listInstanceEntities);
            foreach ($listInstanceEntities as $listInstanceEntity) {
                $entity = EntityModel::getByEntityId(['select' => ['short_label'], 'entityId' => $listInstanceEntity]);
                $formattedLIEntities[] = $entity['short_label'];
            }
            $formattedLTEntities = [];
            $listTemplateEntities = array_unique($listTemplateEntities);
            foreach ($listTemplateEntities as $listTemplateEntity) {
                $entity = EntityModel::getById(['select' => ['short_label'], 'id' => $listTemplateEntity]);
                $formattedLTEntities[] = $entity['short_label'];
            }

            return $response->withJson(['isDeletable' => false, 'listInstanceEntities' => $formattedLIEntities, 'listTemplateEntities' => $formattedLTEntities]);
        }

        $listInstances = [];
        foreach ($listInstanceResIds as $listInstanceResId) {
            $rawListInstances = ListInstanceModel::get([
                'select'    => ['*'],
                'where'     => ['res_id = ?', 'difflist_type = ?'],
                'data'      => [$listInstanceResId, 'entity_id'],
                'orderBy'   => ['listinstance_id']
            ]);
            $listInstances[] = [
                'resId'         => $listInstanceResId,
                'listInstances' => $rawListInstances
            ];
        }

        $rawWorkflowListInstances = ListInstanceModel::get([
            'select'    => ['res_id'],
            'where'     => ['item_id = ?', 'difflist_type = ?', 'process_date is null'],
            'data'      => [$aArgs['id'], 'VISA_CIRCUIT'],
            'groupBy'   => ['res_id']
        ]);
        $workflowListInstances = [];
        foreach ($rawWorkflowListInstances as $rawWorkflowListInstance) {
            $rawListInstances = ListInstanceModel::get([
                'select'    => ['*'],
                'where'     => ['res_id = ?', 'difflist_type = ?'],
                'data'      => [$rawWorkflowListInstance['res_id'], 'VISA_CIRCUIT'],
                'orderBy'   => ['listinstance_id']
            ]);
            $workflowListInstances[] = [
                'resId'         => $rawWorkflowListInstance['res_id'],
                'listInstances' => $rawListInstances
            ];
        }

        return $response->withJson(['isDeletable' => true, 'listTemplates' => $listTemplates, 'listInstances' => $listInstances, 'workflowListInstances' => $workflowListInstances]);
    }

    public function suspend(Request $request, Response $response, array $args)
    {
        $error = $this->hasUsersRights(['id' => $args['id'], 'delete' => true, 'himself' => true]);
        if (!empty($error['error'])) {
            return $response->withStatus($error['status'])->withJson(['errors' => $error['error']]);
        }

        $user = UserModel::getById(['id' => $args['id'], 'select' => ['firstname', 'lastname', 'user_id', 'mode']]);
        if (in_array($user['mode'], ['root_visible', 'root_invisible']) && !UserController::isRoot(['id' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }

        $listInstances = ListInstanceModel::getWhenOpenMailsByUserId(['select' => [1], 'userId' => $args['id'], 'itemMode' => 'dest']);
        if (!empty($listInstances)) {
            return $response->withStatus(403)->withJson(['errors' => 'User is still present in listInstances']);
        }

        $listTemplates = ListTemplateModel::getWithItems([
            'select'    => [1],
            'where'     => ['item_id = ?', 'type = ?', 'item_mode = ?', 'item_type = ?', 'entity_id is not null'],
            'data'      => [$args['id'], 'diffusionList', 'dest', 'user']
        ]);
        if (!empty($listTemplates)) {
            return $response->withStatus(403)->withJson(['errors' => 'User is still present in listTemplates']);
        }

        ListInstanceModel::delete([
            'where' => ['item_id = ?', 'difflist_type = ?', 'item_type = ?', 'item_mode != ?'],
            'data'  => [$args['id'], 'entity_id', 'user_id', 'dest']
        ]);
        RedirectBasketModel::delete([
            'where' => ['owner_user_id = ? OR actual_user_id = ?'],
            'data'  => [$args['id'], $args['id']]
        ]);
        ListTemplateItemModel::delete([
            'where' => ['item_id = ?', 'item_type = ?'],
            'data'  => [$args['id'], 'user']
        ]);
        ListTemplateModel::deleteNoItemsOnes();

        $contactGroupsToDelete = ContactGroupModel::get(['select' => ['id'], 'where' => ['owner = ?', 'entities = ?'], 'data' => [$args['id'], '{}']]);
        if (!empty($contactGroupsToDelete)) {
            $contactGroupsToDelete = array_column($contactGroupsToDelete, 'id');
            ContactGroupModel::delete(['where' => ['id in (?)'], 'data' => [$contactGroupsToDelete]]);
            ContactGroupListModel::delete(['where' => ['contacts_groups_id in (?)'], 'data' => [$contactGroupsToDelete]]);
        }
        ContactGroupListModel::delete(['where' => ['correspondent_id = ?', 'correspondent_type = ?'], 'data' => [$args['id'], 'user']]);

        UserModel::update([
            'set'   => [
                'status'   => 'SPD'
            ],
            'where' => ['id = ?'],
            'data'  => [$args['id']]
        ]);

        HistoryController::add([
            'tableName'    => 'users',
            'recordId'     => $GLOBALS['id'],
            'eventType'    => 'DEL',
            'eventId'      => 'userSuppression',
            'info'         => _USER_SUSPENDED . " {$user['firstname']} {$user['lastname']}"
        ]);

        return $response->withStatus(204);
    }

    public function delete(Request $request, Response $response, array $args)
    {
        $error = $this->hasUsersRights(['id' => $args['id'], 'delete' => true, 'himself' => true]);
        if (!empty($error['error'])) {
            return $response->withStatus($error['status'])->withJson(['errors' => $error['error']]);
        }

        $user = UserModel::getById(['id' => $args['id'], 'select' => ['firstname', 'lastname', 'user_id', 'mode']]);
        if (in_array($user['mode'], ['root_visible', 'root_invisible']) && !UserController::isRoot(['id' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }

        $listInstances = ListInstanceModel::getWhenOpenMailsByUserId(['select' => [1], 'userId' => $args['id'], 'itemMode' => 'dest']);
        if (!empty($listInstances)) {
            return $response->withStatus(403)->withJson(['errors' => 'User is still present in listInstances']);
        }

        $listTemplates = ListTemplateModel::getWithItems([
            'select'    => [1],
            'where'     => ['item_id = ?', 'type = ?', 'item_mode = ?', 'item_type = ?', 'entity_id is not null'],
            'data'      => [$args['id'], 'diffusionList', 'dest', 'user']
        ]);
        if (!empty($listTemplates)) {
            return $response->withStatus(403)->withJson(['errors' => 'User is still present in listTemplates']);
        }

        ListInstanceModel::delete([
            'where' => ['item_id = ?', 'difflist_type = ?', 'item_type = ?', 'item_mode != ?'],
            'data'  => [$args['id'], 'entity_id', 'user_id', 'dest']
        ]);
        ListTemplateItemModel::delete([
            'where' => ['item_id = ?', 'item_type = ?'],
            'data'  => [$args['id'], 'user']
        ]);
        ListTemplateModel::deleteNoItemsOnes();

        $contactGroupsToDelete = ContactGroupModel::get(['select' => ['id'], 'where' => ['owner = ?', 'entities = ?'], 'data' => [$args['id'], '{}']]);
        if (!empty($contactGroupsToDelete)) {
            $contactGroupsToDelete = array_column($contactGroupsToDelete, 'id');
            ContactGroupModel::delete(['where' => ['id in (?)'], 'data' => [$contactGroupsToDelete]]);
            ContactGroupListModel::delete(['where' => ['contacts_groups_id in (?)'], 'data' => [$contactGroupsToDelete]]);
        }
        ContactGroupListModel::delete(['where' => ['correspondent_id = ?', 'correspondent_type = ?'], 'data' => [$args['id'], 'user']]);

        RedirectBasketModel::delete([
            'where' => ['owner_user_id = ? OR actual_user_id = ?'],
            'data'  => [$args['id'], $args['id']]
        ]);

        // Delete from groups
        UserGroupModel::delete(['where' => ['user_id = ?'], 'data' => [$args['id']]]);
        UserBasketPreferenceModel::delete([
            'where' => ['user_serial_id = ?'],
            'data'  => [$args['id']]
        ]);
        RedirectBasketModel::delete([
            'where' => ['owner_user_id = ?'],
            'data'  => [$args['id']]
        ]);

        UserEntityModel::delete([
            'where' => ['user_id = ?'],
            'data'  => [$args['id']]
        ]);

        UserModel::delete(['id' => $args['id']]);

        HistoryController::add([
            'tableName'    => 'users',
            'recordId'     => $GLOBALS['id'],
            'eventType'    => 'DEL',
            'eventId'      => 'userSuppression',
            'info'         => _USER_DELETED . " {$user['firstname']} {$user['lastname']}"
        ]);

        return $response->withStatus(204);
    }

    public function getProfile(Request $request, Response $response)
    {
        $user = UserModel::getById(['id' => $GLOBALS['id'], 'select' => ['id', 'user_id', 'firstname', 'lastname', 'phone', 'mail', 'initials', 'preferences', 'external_id', 'status', 'mode', 'feature_tour', 'absence']]);
        $user['external_id']        = json_decode($user['external_id'], true);
        $user['preferences']        = json_decode($user['preferences'], true);
        unset($user['preferences']['outlookPassword']);
        $user['featureTour']        = json_decode($user['feature_tour'], true);
        unset($user['feature_tour']);
        $user['signatures']         = UserSignatureModel::getByUserSerialId(['userSerialid' => $user['id']]);
        $user['groups']             = UserModel::getGroupsById(['id' => $GLOBALS['id']]);
        $user['entities']           = UserModel::getEntitiesById(['id' => $GLOBALS['id'], 'select' => ['entities.id', 'users_entities.entity_id', 'entities.entity_label', 'users_entities.user_role', 'users_entities.primary_entity'], 'orderBy' => ['users_entities.primary_entity DESC']]);
        $user['baskets']            = BasketModel::getBasketsByLogin(['login' => $user['user_id']]);
        $user['assignedBaskets']    = RedirectBasketModel::getAssignedBasketsByUserId(['userId' => $user['id']]);
        $user['redirectedBaskets']  = RedirectBasketModel::getRedirectedBasketsByUserId(['userId' => $user['id']]);
        $user['regroupedBaskets']   = BasketModel::getRegroupedBasketsByUserId(['userId' => $user['user_id']]);
        $user['passwordRules']      = PasswordModel::getEnabledRules();
        $user['canModifyPassword']  = true;
        $user['privileges']         = PrivilegeController::getPrivilegesByUser(['userId' => $user['id']]);
        $user['lockAdvancedPrivileges'] = PrivilegeController::isAdvancedPrivilegesLocked();
        $userFollowed = UserFollowedResourceModel::get(['select' => ['count(1) as nb'], 'where' => ['user_id = ?'], 'data' => [$GLOBALS['id']]]);
        $user['nbFollowedResources'] = $userFollowed[0]['nb'];
        if (!empty($user['absence'])) {
            $user['absence'] = json_decode($user['absence'], true);
        }

        $loggingMethod = CoreConfigModel::getLoggingMethod();
        if (in_array($loggingMethod['id'], self::ALTERNATIVES_CONNECTIONS_METHODS)) {
            $user['canModifyPassword'] = false;
        }

        foreach ($user['baskets'] as $key => $basket) {
            if (!$basket['allowed']) {
                unset($user['baskets'][$key]);
            }
            unset($user['baskets'][$key]['basket_clause']);
        }
        $user['baskets'] = array_values($user['baskets']);
        foreach ($user['groups'] as $key => $group) {
            unset($user['groups'][$key]['where_clause']);
        }
        foreach ($user['assignedBaskets'] as $key => $basket) {
            unset($user['assignedBaskets'][$key]['basket_clause']);
        }

        return $response->withJson($user);
    }

    public function updateProfile(Request $request, Response $response)
    {
        $body = $request->getParsedBody();

        if (!Validator::stringType()->notEmpty()->validate($body['firstname'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body firstname is empty or not a string']);
        } elseif (!Validator::stringType()->notEmpty()->validate($body['lastname'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body lastname is empty or not a string']);
        } elseif (!Validator::stringType()->notEmpty()->validate($body['mail']) || !filter_var($body['mail'], FILTER_VALIDATE_EMAIL)) {
            return $response->withStatus(400)->withJson(['errors' => 'Body mail is empty or not a valid email']);
        } elseif (!empty($body['phone']) && !preg_match("/\+?((|\ |\.|\(|\)|\-)?(\d)*)*\d/", $body['phone'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body phone is not a valid phone number']);
        }

        UserModel::update([
            'set'   => [
                'firstname'     => $body['firstname'],
                'lastname'      => $body['lastname'],
                'mail'          => $body['mail'],
                'phone'         => $body['phone'],
                'initials'      => $body['initials']
            ],
            'where' => ['id = ?'],
            'data'  => [$GLOBALS['id']]
        ]);

        HistoryController::add([
            'tableName'    => 'users',
            'recordId'     => $GLOBALS['id'],
            'eventType'    => 'UP',
            'eventId'      => 'userModification',
            'info'         => _USER_UPDATED . " {$body['firstname']} {$body['lastname']}"
        ]);

        return $response->withStatus(204);
    }

    public function updateCurrentUserFeatureTour(Request $request, Response $response)
    {
        $body = $request->getParsedBody();

        if (!Validator::notEmpty()->validate($body['featureTour'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body featureTour is empty']);
        }

        UserModel::update([
            'set'   => [
                'feature_tour' => json_encode($body['featureTour'])
            ],
            'where' => ['id = ?'],
            'data'  => [$GLOBALS['id']]
        ]);

        $userData = UserModel::getLabelledUserById(['id' => $GLOBALS['id'], 'select' => ['firstname', 'lastname']]);
        HistoryController::add([
            'tableName'    => 'users',
            'recordId'     => $GLOBALS['id'],
            'eventType'    => 'UP',
            'eventId'      => 'userModification',
            'info'         => _USER_FEATURE_TOUR_UPDATED . " " . $userData
        ]);

        return $response->withStatus(204);
    }

    public function updateCurrentUserPreferences(Request $request, Response $response)
    {
        $body = $request->getParsedBody();

        $user = UserModel::getById(['id' => $GLOBALS['id'], 'select' => ['preferences', 'firstname', 'lastname']]);
        $preferences = json_decode($user['preferences'], true);

        if (!empty($body['documentEdition'])) {
            if (!in_array($body['documentEdition'], DocumentEditorController::DOCUMENT_EDITION_METHODS)) {
                return $response->withStatus(400)->withJson(['errors' => 'Body preferences[documentEdition] is not allowed']);
            }
            $preferences['documentEdition'] = $body['documentEdition'];
        }
        if (!empty($body['homeGroups'])) {
            $preferences['homeGroups'] = $body['homeGroups'];
        }

        UserModel::update([
            'set'   => [
                'preferences'   => json_encode($preferences)
            ],
            'where' => ['id = ?'],
            'data'  => [$GLOBALS['id']]
        ]);

        HistoryController::add([
            'tableName'    => 'users',
            'recordId'     => $GLOBALS['id'],
            'eventType'    => 'UP',
            'eventId'      => 'userModification',
            'info'         => _USER_PREFERENCE_UPDATED . " {$user['firstname']} {$user['lastname']}"
        ]);

        return $response->withStatus(204);
    }

    public function updatePassword(Request $request, Response $response, array $aArgs)
    {
        $error = $this->hasUsersRights(['id' => $aArgs['id'], 'himself' => true]);
        if (!empty($error['error'])) {
            return $response->withStatus($error['status'])->withJson(['errors' => $error['error']]);
        }

        $body = $request->getParsedBody();

        $check = Validator::stringType()->notEmpty()->validate($body['currentPassword']);
        $check = $check && Validator::stringType()->notEmpty()->validate($body['newPassword']);
        $check = $check && Validator::stringType()->notEmpty()->validate($body['reNewPassword']);
        if (!$check) {
            return $response->withStatus(400)->withJson(['errors' => 'Bad Request']);
        }

        $user = UserModel::getById(['id' => $aArgs['id'], 'select' => ['user_id', 'mode']]);
        if ($user['mode'] != 'rest' && $user['user_id'] != $GLOBALS['login']) {
            return $response->withStatus(403)->withJson(['errors' => 'Not allowed']);
        }

        if ($body['newPassword'] != $body['reNewPassword']) {
            return $response->withStatus(400)->withJson(['errors' => 'Bad Request']);
        } elseif (!AuthenticationModel::authentication(['login' => $user['user_id'], 'password' => $body['currentPassword']])) {
            return $response->withStatus(401)->withJson(['errors' => _WRONG_PSW]);
        } elseif (!PasswordController::isPasswordValid(['password' => $body['newPassword']])) {
            return $response->withStatus(400)->withJson(['errors' => 'Password does not match security criteria']);
        } elseif (!PasswordModel::isPasswordHistoryValid(['password' => $body['newPassword'], 'userSerialId' => $aArgs['id']])) {
            return $response->withStatus(400)->withJson(['errors' => _ALREADY_USED_PSW]);
        }

        UserModel::updatePassword(['id' => $aArgs['id'], 'password' => $body['newPassword']]);
        PasswordModel::setHistoryPassword(['userSerialId' => $aArgs['id'], 'password' => $body['newPassword']]);

        HistoryController::add([
            'tableName'    => 'users',
            'recordId'     => $aArgs['id'],
            'eventType'    => 'UP',
            'eventId'      => 'userModification',
            'info'         => _USER_PASSWORD_UPDATED
        ]);

        return $response->withJson(['success' => 'success']);
    }

    public function setRedirectedBaskets(Request $request, Response $response, array $args)
    {
        $error = $this->hasUsersRights(['id' => $args['id'], 'himself' => true]);
        if (!empty($error['error'])) {
            return $response->withStatus($error['status'])->withJson(['errors' => $error['error']]);
        }

        $body = $request->getParsedBody();

        $result = UserController::redirectBasket(['redirectedBaskets' => $body, 'userId' => $args['id'], 'login' => $GLOBALS['login']]);
        if (!empty($result['errors'])) {
            return $response->withStatus(400)->withJson(['errors' => $result['errors']]);
        }

        $user = UserModel::getById(['id' => $args['id'], 'select' => ['user_id']]);

        $userBaskets = BasketModel::getBasketsByLogin(['login' => $user['user_id']]);

        if ($GLOBALS['login'] == $user['user_id']) {
            foreach ($userBaskets as $key => $basket) {
                if (!$basket['allowed']) {
                    unset($userBaskets[$key]);
                }
            }
            $userBaskets = array_values($userBaskets);
        }

        return $response->withJson([
            'redirectedBaskets' => RedirectBasketModel::getRedirectedBasketsByUserId(['userId' => $args['id']]),
            'baskets'           => $userBaskets
        ]);
    }

    public function deleteRedirectedBasket(Request $request, Response $response, array $aArgs)
    {
        $error = $this->hasUsersRights(['id' => $aArgs['id'], 'himself' => true]);
        if (!empty($error['error'])) {
            return $response->withStatus($error['status'])->withJson(['errors' => $error['error']]);
        }

        $data = $request->getQueryParams();

        DatabaseModel::beginTransaction();

        if (!Validator::notEmpty()->arrayType()->validate($data['redirectedBasketIds'])) {
            DatabaseModel::rollbackTransaction();
            return $response->withStatus(400)->withJson(['errors' => 'RedirectedBasketIds is empty or not an array']);
        }

        $user = UserModel::getById(['id' => $aArgs['id'], 'select' => ['user_id']]);
        if (empty($user)) {
            DatabaseModel::rollbackTransaction();
            return $response->withStatus(400)->withJson(['errors' => 'User not found']);
        }

        foreach ($data['redirectedBasketIds'] as $redirectedBasketId) {
            $redirectedBasket = RedirectBasketModel::get(['select' => ['actual_user_id', 'owner_user_id', 'basket_id'], 'where' => ['id = ?'], 'data' => [$redirectedBasketId]]);
            if (empty($redirectedBasket[0]) || ($redirectedBasket[0]['actual_user_id'] != $aArgs['id'] && $redirectedBasket[0]['owner_user_id'] != $aArgs['id'])) {
                DatabaseModel::rollbackTransaction();
                return $response->withStatus(403)->withJson(['errors' => 'Redirected basket out of perimeter']);
            }

            RedirectBasketModel::delete(['where' => ['id = ?'], 'data' => [$redirectedBasketId]]);

            HistoryController::add([
                'tableName'    => 'redirected_baskets',
                'recordId'     => $GLOBALS['login'],
                'eventType'    => 'DEL',
                'eventId'      => 'basketRedirection',
                'info'         => _BASKET_REDIRECTION_SUPPRESSION . " {$user['user_id']} : " . $redirectedBasket[0]['basket_id']
            ]);
        }

        DatabaseModel::commitTransaction();

        $userBaskets = BasketModel::getBasketsByLogin(['login' => $user['user_id']]);

        if ($GLOBALS['login'] == $user['user_id']) {
            foreach ($userBaskets as $key => $basket) {
                if (!$basket['allowed']) {
                    unset($userBaskets[$key]);
                }
            }
            $userBaskets = array_values($userBaskets);
        }

        return $response->withJson([
            'baskets'   => $userBaskets
        ]);
    }

    public function getStatusByUserId(Request $request, Response $response, array $aArgs)
    {
        if (!PrivilegeController::hasPrivilege(['privilegeId' => 'admin_users', 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }
        
        $user = UserModel::getByLowerLogin(['login' => $aArgs['userId'], 'select' => ['status']]);

        if (empty($user)) {
            return $response->withJson(['status' => null]);
        }

        return $response->withJson(['status' => $user['status']]);
    }

    public function updateStatus(Request $request, Response $response, array $args)
    {
        $error = $this->hasUsersRights(['id' => $args['id'], 'himself' => true]);
        if (!empty($error['error'])) {
            return $response->withStatus($error['status'])->withJson(['errors' => $error['error']]);
        }

        $body = $request->getParsedBody();

        if (!Validator::stringType()->notEmpty()->validate($body['status'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body status is empty or not a string']);
        } elseif ($body['status'] != 'OK' && $body['status'] != 'ABS') {
            return $response->withStatus(400)->withJson(['errors' => 'Body status can only be OK or ABS']);
        }

        $user = UserModel::getById(['id' => $args['id'], 'select' => ['status']]);

        $set = ['status' => $body['status']];
        if ($user['status'] == 'ABS' && $body['status'] == 'OK') {
            $set['absence'] = null;
        }

        UserModel::update(['set' => $set, 'where' => ['id = ?'], 'data' => [$args['id']]]);

        $message = UserModel::getLabelledUserById(['id' => $args['id']]);
        $message .= ' ';
        $message .= ($body['status'] == 'ABS' ? _GO_ON_VACATION : _BACK_FROM_VACATION);

        HistoryController::add([
            'tableName'    => 'users',
            'recordId'     => $args['id'],
            'eventType'    => $body['status'] == 'ABS' ? 'ABS' : 'PRE',
            'eventId'      => 'userabs',
            'info'         => $message
        ]);

        return $response->withJson(['user' => UserModel::getById(['id' => $args['id'], 'select' => ['status']])]);
    }

    public function getImageContent(Request $request, Response $response, array $aArgs)
    {
        if (!Validator::intVal()->validate($aArgs['id']) || !Validator::intVal()->validate($aArgs['signatureId'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Bad Request']);
        }

        if (!PrivilegeController::hasPrivilege(['privilegeId' => 'view_personal_data', 'userId' => $GLOBALS['id']])
            && $aArgs['id'] != $GLOBALS['id']) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }

        $error = $this->hasUsersRights(['id' => $aArgs['id'], 'himself' => true]);
        if (!empty($error['error'])) {
            return $response->withStatus($error['status'])->withJson(['errors' => $error['error']]);
        }

        $signatures = UserSignatureModel::get([
            'select'    => ['signature_path', 'signature_file_name'],
            'where'     => ['user_serial_id = ?', 'id = ?'],
            'data'      => [$aArgs['id'], $aArgs['signatureId']]
        ]);
        if (empty($signatures[0])) {
            return $response->withStatus(400)->withJson(['errors' => 'Signature does not exist']);
        }

        $docserver = DocserverModel::getCurrentDocserver(['typeId' => 'TEMPLATES', 'collId' => 'templates', 'select' => ['path_template']]);
        if (empty($docserver['path_template']) || !file_exists($docserver['path_template'])) {
            return [];
        }

        $pathToSignature = $docserver['path_template'] . str_replace('#', '/', $signatures[0]['signature_path']) . $signatures[0]['signature_file_name'];
        $image = file_get_contents($pathToSignature);
        if ($image === false) {
            return $response->withStatus(404)->withJson(['errors' => 'Signature not found on docserver']);
        }

        $finfo    = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->buffer($image);

        $response->write($image);

        return $response->withHeader('Content-Type', $mimeType);
    }

    public function addSignature(Request $request, Response $response, array $aArgs)
    {
        if (!PrivilegeController::hasPrivilege(['privilegeId' => 'manage_personal_data', 'userId' => $GLOBALS['id']])
            && $aArgs['id'] != $GLOBALS['id']) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }

        $error = $this->hasUsersRights(['id' => $aArgs['id'], 'himself' => true]);
        if (!empty($error['error'])) {
            return $response->withStatus($error['status'])->withJson(['errors' => $error['error']]);
        }

        $data = $request->getParsedBody();

        if (!$this->checkNeededParameters(['data' => $data, 'needed' => ['base64', 'name', 'label']])) {
            return $response->withStatus(400)->withJson(['errors' => 'Bad Request']);
        }

        $file     = base64_decode($data['base64']);
        $tmpName  = "tmp_file_{$aArgs['id']}_" .rand(). "_{$data['name']}";

        $finfo    = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->buffer($file);
        $size     = strlen($file);
        $type     = explode('/', $mimeType);
        $ext      = strtoupper(substr($data['name'], strrpos($data['name'], '.') + 1));

        $fileAccepted  = StoreController::isFileAllowed(['extension' => $ext, 'type' => $mimeType]);

        if (!$fileAccepted || $type[0] != 'image') {
            return $response->withStatus(400)->withJson(['errors' => _WRONG_FILE_TYPE]);
        } elseif ($size > 2000000) {
            return $response->withStatus(400)->withJson(['errors' => _MAX_SIZE_UPLOAD_REACHED . ' (2 MB)']);
        }

        file_put_contents(CoreConfigModel::getTmpPath() . $tmpName, $file);

        $storeInfos = DocserverController::storeResourceOnDocServer([
            'collId'            => 'templates',
            'docserverTypeId'   => 'TEMPLATES',
            'encodedResource'   => base64_encode($file),
            'format'            => $ext
        ]);

        if (!file_exists($storeInfos['path_template']. str_replace('#', '/', $storeInfos['destination_dir']) .$storeInfos['file_destination_name'])) {
            return $response->withStatus(500)->withJson(['errors' => $storeInfos['error'] .' '. _PATH_OF_DOCSERVER_UNAPPROACHABLE]);
        }

        UserSignatureModel::create([
            'userSerialId'      => $aArgs['id'],
            'signatureLabel'    => $data['label'],
            'signaturePath'     => $storeInfos['destination_dir'],
            'signatureFileName' => $storeInfos['file_destination_name'],
        ]);

        return $response->withJson([
            'signatures' => UserSignatureModel::getByUserSerialId(['userSerialid' => $aArgs['id']])
        ]);
    }

    public function updateSignature(Request $request, Response $response, array $aArgs)
    {
        if (!PrivilegeController::hasPrivilege(['privilegeId' => 'manage_personal_data', 'userId' => $GLOBALS['id']])
            && $aArgs['id'] != $GLOBALS['id']) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }

        $error = $this->hasUsersRights(['id' => $aArgs['id'], 'himself' => true]);
        if (!empty($error['error'])) {
            return $response->withStatus($error['status'])->withJson(['errors' => $error['error']]);
        }

        $data = $request->getParsedBody();

        if (!$this->checkNeededParameters(['data' => $data, 'needed' => ['label']])) {
            return $response->withStatus(400)->withJson(['errors' => 'Bad Request']);
        }

        UserSignatureModel::update([
            'signatureId'   => $aArgs['signatureId'],
            'userSerialId'  => $aArgs['id'],
            'label'         => $data['label']
        ]);

        return $response->withJson([
            'signature' => UserSignatureModel::getById(['id' => $aArgs['signatureId'], 'select' => ['id', 'user_serial_id', 'signature_label']])
        ]);
    }

    public function deleteSignature(Request $request, Response $response, array $aArgs)
    {
        if (!PrivilegeController::hasPrivilege(['privilegeId' => 'manage_personal_data', 'userId' => $GLOBALS['id']])
            && $aArgs['id'] != $GLOBALS['id']) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }

        $error = $this->hasUsersRights(['id' => $aArgs['id'], 'himself' => true]);
        if (!empty($error['error'])) {
            return $response->withStatus($error['status'])->withJson(['errors' => $error['error']]);
        }

        UserSignatureModel::delete(['signatureId' => $aArgs['signatureId'], 'userSerialId' => $aArgs['id']]);

        return $response->withJson([
            'signatures' => UserSignatureModel::getByUserSerialId(['userSerialid' => $aArgs['id']])
        ]);
    }

    public function createCurrentUserEmailSignature(Request $request, Response $response)
    {
        $data = $request->getParsedBody();

        if (!$this->checkNeededParameters(['data' => $data, 'needed' => ['title', 'htmlBody']])) {
            return $response->withJson(['errors' => 'Bad Request']);
        }

        UserEmailSignatureModel::create([
            'userId'    => $GLOBALS['id'],
            'title'     => $data['title'],
            'htmlBody'  => $data['htmlBody']
        ]);

        return $response->withJson([
            'emailSignatures' => UserEmailSignatureModel::getByUserId(['userId' => $GLOBALS['id']])
        ]);
    }

    public function updateCurrentUserEmailSignature(Request $request, Response $response, array $aArgs)
    {
        $data = $request->getParsedBody();

        if (!$this->checkNeededParameters(['data' => $data, 'needed' => ['title', 'htmlBody']])) {
            return $response->withJson(['errors' => 'Bad Request']);
        }

        UserEmailSignatureModel::update([
            'id'        => $aArgs['id'],
            'userId'    => $GLOBALS['id'],
            'title'     => $data['title'],
            'htmlBody'  => $data['htmlBody']
        ]);

        return $response->withJson([
            'emailSignature' => UserEmailSignatureModel::getById(['id' => $aArgs['id']])
        ]);
    }

    public function deleteCurrentUserEmailSignature(Request $request, Response $response, array $aArgs)
    {
        UserEmailSignatureModel::delete([
            'id'        => $aArgs['id'],
            'userId'    => $GLOBALS['id']
        ]);

        return $response->withJson(['emailSignatures' => UserEmailSignatureModel::getByUserId(['userId' => $GLOBALS['id']])]);
    }

    public function addGroup(Request $request, Response $response, array $aArgs)
    {
        $error = $this->hasUsersRights(['id' => $aArgs['id']]);
        if (!empty($error['error'])) {
            return $response->withStatus($error['status'])->withJson(['errors' => $error['error']]);
        }

        $data = $request->getParsedBody();
        if (!$this->checkNeededParameters(['data' => $data, 'needed' => ['groupId']])) {
            return $response->withStatus(400)->withJson(['errors' => 'Bad Request']);
        }

        $group = GroupModel::getByGroupId(['select' => ['id'], 'groupId' => $data['groupId']]);

        if (empty($group)) {
            return $response->withStatus(400)->withJson(['errors' => 'Group not found']);
        } elseif (UserModel::hasGroup(['id' => $aArgs['id'], 'groupId' => $data['groupId']])) {
            return $response->withStatus(400)->withJson(['errors' => _USER_ALREADY_LINK_GROUP]);
        }
        if (!PrivilegeController::canAssignGroup(['userId' => $GLOBALS['id'], 'groupId' => $group['id']])) {
            return $response->withStatus(403)->withJson(['errors' => _CANNOT_ADD_USER_IN_THIS_GROUP]);
        }
        if (empty($data['role'])) {
            $data['role'] = null;
        }

        UserGroupModel::create(['user_id' => $aArgs['id'], 'group_id' => $group['id'], 'role' => $data['role']]);

        $baskets = GroupBasketModel::get(['select' => ['basket_id'], 'where' => ['group_id = ?'], 'data' => [$data['groupId']]]);
        foreach ($baskets as $basket) {
            UserBasketPreferenceModel::create([
                'userSerialId'  => $aArgs['id'],
                'groupSerialId' => $group['id'],
                'basketId'      => $basket['basket_id'],
                'display'       => 'true'
            ]);
        }

        $user = UserModel::getById(['id' => $aArgs['id'], 'select' => ['user_id']]);
        HistoryController::add([
            'tableName' => 'users',
            'recordId'  => $aArgs['id'],
            'eventType' => 'UP',
            'info'      => _USER_GROUP_CREATION . " : {$user['user_id']} {$data['groupId']}",
            'moduleId'  => 'user',
            'eventId'   => 'userModification',
        ]);

        return $response->withJson([
            'groups'    => UserModel::getGroupsById(['id' => $aArgs['id']]),
            'baskets'   => BasketModel::getBasketsByLogin(['login' => $user['user_id']])
        ]);
    }

    public function updateGroup(Request $request, Response $response, array $aArgs)
    {
        $error = $this->hasUsersRights(['id' => $aArgs['id']]);
        if (!empty($error['error'])) {
            return $response->withStatus($error['status'])->withJson(['errors' => $error['error']]);
        }

        $group = GroupModel::getByGroupId(['select' => ['id'], 'groupId' => $aArgs['groupId']]);
        if (empty($group)) {
            return $response->withStatus(400)->withJson(['errors' => 'Group not found']);
        }

        if (!PrivilegeController::canAssignGroup(['userId' => $GLOBALS['id'], 'groupId' => $group['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }

        $data = $request->getParsedBody();
        if (empty($data['role'])) {
            $data['role'] = '';
        }

        UserGroupModel::update(['set' => ['role' => $data['role']], 'where' => ['user_id = ?', 'group_id = ?'], 'data' => [$aArgs['id'], $group['id']]]);

        $user = UserModel::getById(['id' => $aArgs['id'], 'select' => ['user_id']]);
        HistoryController::add([
            'tableName' => 'users',
            'recordId'  => $aArgs['id'],
            'eventType' => 'UP',
            'info'      => _USER_GROUP_MODIFICATION . " : {$user['user_id']} {$aArgs['groupId']}",
            'moduleId'  => 'user',
            'eventId'   => 'userModification',
        ]);

        return $response->withJson(['success' => 'success']);
    }

    public function deleteGroup(Request $request, Response $response, array $aArgs)
    {
        $error = $this->hasUsersRights(['id' => $aArgs['id']]);
        if (!empty($error['error'])) {
            return $response->withStatus($error['status'])->withJson(['errors' => $error['error']]);
        }
        $group = GroupModel::getByGroupId(['select' => ['id'], 'groupId' => $aArgs['groupId']]);
        if (empty($group)) {
            return $response->withStatus(400)->withJson(['errors' => 'Group not found']);
        }

        if (!PrivilegeController::canAssignGroup(['userId' => $GLOBALS['id'], 'groupId' => $group['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }

        UserGroupModel::delete(['where' => ['user_id = ?', 'group_id = ?'], 'data' => [$aArgs['id'], $group['id']]]);

        UserBasketPreferenceModel::delete([
            'where' => ['user_serial_id = ?', 'group_serial_id = ?'],
            'data'  => [$aArgs['id'], $group['id']]
        ]);
        RedirectBasketModel::delete([
            'where' => ['owner_user_id = ?', 'group_id = ?'],
            'data'  => [$aArgs['id'], $group['id']]
        ]);

        $user = UserModel::getById(['id' => $aArgs['id'], 'select' => ['user_id']]);
        HistoryController::add([
            'tableName' => 'users',
            'recordId'  => $aArgs['id'],
            'eventType' => 'UP',
            'info'      => _USER_GROUP_SUPPRESSION . " : {$user['user_id']} {$aArgs['groupId']}",
            'moduleId'  => 'user',
            'eventId'   => 'userModification',
        ]);

        return $response->withJson([
            'groups'            => UserModel::getGroupsById(['id' => $aArgs['id']]),
            'baskets'           => BasketModel::getBasketsByLogin(['login' => $user['user_id']]),
            'redirectedBaskets' => RedirectBasketModel::getRedirectedBasketsByUserId(['userId' => $aArgs['id']]),
        ]);
    }

    public function getEntities(Request $request, Response $response, array $args)
    {
        $user = UserModel::getById(['id' => $args['id'], 'select' => ['user_id']]);
        if (empty($user)) {
            return $response->withStatus(400)->withJson(['errors' => 'User does not exist']);
        }

        $entities = UserModel::getEntitiesById(['id' => $args['id'], 'select' => ['entities.id', 'users_entities.entity_id', 'entities.entity_label', 'users_entities.user_role', 'users_entities.primary_entity'], 'orderBy' => ['users_entities.primary_entity DESC']]);

        return $response->withJson(['entities' => $entities]);
    }

    public function addEntity(Request $request, Response $response, array $aArgs)
    {
        $error = $this->hasUsersRights(['id' => $aArgs['id']]);
        if (!empty($error['error'])) {
            return $response->withStatus($error['status'])->withJson(['errors' => $error['error']]);
        }

        $data = $request->getParsedBody();
        if (!$this->checkNeededParameters(['data' => $data, 'needed' => ['entityId']])) {
            return $response->withStatus(400)->withJson(['errors' => 'Bad Request']);
        }
        if (empty(EntityModel::getByEntityId(['entityId' => $data['entityId']]))) {
            return $response->withStatus(400)->withJson(['errors' => 'Entity not found']);
        } elseif (UserModel::hasEntity(['id' => $aArgs['id'], 'entityId' => $data['entityId']])) {
            return $response->withStatus(400)->withJson(['errors' => _USER_ALREADY_LINK_ENTITY]);
        }
        if (empty($data['role'])) {
            $data['role'] = '';
        }
        $user = UserModel::getById(['id' => $aArgs['id'], 'select' => ['user_id']]);
        $primaryEntity = UserModel::getPrimaryEntityById(['id' => $aArgs['id'], 'select' => [1]]);
        $pEntity = 'N';
        if (empty($primaryEntity)) {
            $pEntity = 'Y';
        }

        UserEntityModel::addUserEntity(['id' => $aArgs['id'], 'entityId' => $data['entityId'], 'role' => $data['role'], 'primaryEntity' => $pEntity]);
        HistoryController::add([
            'tableName' => 'users',
            'recordId'  => $aArgs['id'],
            'eventType' => 'UP',
            'info'      => _USER_ENTITY_CREATION . " : {$user['user_id']} {$data['entityId']}",
            'moduleId'  => 'user',
            'eventId'   => 'userModification',
        ]);

        return $response->withJson([
            'entities'      => UserModel::getEntitiesById(['id' => $aArgs['id'], 'select' => ['entities.id', 'users_entities.entity_id', 'entities.entity_label', 'users_entities.user_role', 'users_entities.primary_entity'], 'orderBy' => ['users_entities.primary_entity DESC']]),
            'allEntities'   => EntityModel::getAvailableEntitiesForAdministratorByUserId(['userId' => $user['user_id'], 'administratorUserId' => $GLOBALS['login']])
        ]);
    }

    public function updateEntity(Request $request, Response $response, array $aArgs)
    {
        $error = $this->hasUsersRights(['id' => $aArgs['id']]);
        if (!empty($error['error'])) {
            return $response->withStatus($error['status'])->withJson(['errors' => $error['error']]);
        }
        if (empty(entitymodel::getByEntityId(['entityId' => $aArgs['entityId']]))) {
            return $response->withStatus(400)->withJson(['errors' => 'Entity not found']);
        }

        $data = $request->getParsedBody();
        if (empty($data['user_role'])) {
            $data['user_role'] = '';
        }

        UserEntityModel::updateUserEntity(['id' => $aArgs['id'], 'entityId' => $aArgs['entityId'], 'role' => $data['user_role']]);
        HistoryController::add([
            'tableName' => 'users',
            'recordId'  => $aArgs['id'],
            'eventType' => 'UP',
            'info'      => _USER_ENTITY_MODIFICATION . " : {$aArgs['id']} {$aArgs['entityId']}",
            'moduleId'  => 'user',
            'eventId'   => 'userModification',
        ]);

        return $response->withJson(['success' => 'success']);
    }

    public function updatePrimaryEntity(Request $request, Response $response, array $aArgs)
    {
        $error = $this->hasUsersRights(['id' => $aArgs['id']]);
        if (!empty($error['error'])) {
            return $response->withStatus($error['status'])->withJson(['errors' => $error['error']]);
        }
        if (empty(EntityModel::getByEntityId(['entityId' => $aArgs['entityId']]))) {
            return $response->withStatus(400)->withJson(['errors' => 'Entity not found']);
        }

        UserEntityModel::updateUserPrimaryEntity(['id' => $aArgs['id'], 'entityId' => $aArgs['entityId']]);

        return $response->withJson(['entities' => UserModel::getEntitiesById(['id' => $aArgs['id'], 'select' => ['entities.id', 'users_entities.entity_id', 'entities.entity_label', 'users_entities.user_role', 'users_entities.primary_entity'], 'orderBy' => ['users_entities.primary_entity DESC']])]);
    }

    public function deleteEntity(Request $request, Response $response, array $aArgs)
    {
        $error = $this->hasUsersRights(['id' => $aArgs['id']]);
        if (!empty($error['error'])) {
            return $response->withStatus($error['status'])->withJson(['errors' => $error['error']]);
        }
        $entityInfo = EntityModel::getByEntityId(['entityId' => $aArgs['entityId'], 'select' => ['id']]);
        if (empty($entityInfo)) {
            return $response->withStatus(400)->withJson(['errors' => 'Entity not found']);
        }

        $user = UserModel::getById(['id' => $aArgs['id'], 'select' => ['user_id']]);

        $data = $request->getParsedBody();
        if (!empty($data['mode'])) {
            $templateLists = ListTemplateModel::get(['select' => ['id'], 'where' => ['entity_id = ?'], 'data' => [$entityInfo['id']]]);
            if (!empty($templateLists)) {
                foreach ($templateLists as $templateList) {
                    ListTemplateItemModel::delete(['where' => ['list_template_id = ?'], 'data' => [$templateList['id']]]);
                }
            }

            if ($data['mode'] == 'reaffect') {
                $listInstances = ListInstanceModel::getWithConfidentiality(['select' => ['listinstance.res_id'], 'entityId' => $aArgs['entityId'], 'userId' => $aArgs['id']]);
                $resIdsToReplace = array_column($listInstances, 'res_id');
                if (!empty($resIdsToReplace)) {
                    ListInstanceModel::update([
                        'set'   => ['item_id' => $data['newUser']['serialId']],
                        'where' => ['res_id in (?)', 'item_id = ?', 'process_date is null'],
                        'data'  => [$resIdsToReplace, $aArgs['id']]
                    ]);
                }
            } else {
                $ressources = ResModel::get([
                    'select'    => ['res_id'],
                    'where'     => ['confidentiality = ?', 'destination = ?', 'closing_date is null'],
                    'data'      => ['Y', $aArgs['entityId']]
                ]);
                foreach ($ressources as $ressource) {
                    $listInstanceId = ListInstanceModel::get([
                        'select'    => ['listinstance_id'],
                        'where'     => ['res_id = ?', 'item_id = ?', 'item_type = ?', 'difflist_type = ?', 'item_mode = ?', 'process_date is null'],
                        'data'      => [$ressource['res_id'], $aArgs['id'], 'user_id', 'VISA_CIRCUIT', 'sign']
                    ]);

                    if (!empty($listInstanceId)) {
                        ListInstanceModel::update([
                            'set'   => ['process_date' => null],
                            'where' => ['res_id = ?', 'difflist_type = ?', 'listinstance_id = ?'],
                            'data'  => [$ressource['res_id'], 'VISA_CIRCUIT', $listInstanceId[0]['listinstance_id'] - 1]
                        ]);
                        $listInstanceMinus = ListInstanceModel::get([
                            'select'    => ['requested_signature'],
                            'where'     => ['listinstance_id = ?'],
                            'data'      => [$listInstanceId[0]['listinstance_id'] - 1]
                        ]);
                        if ($listInstanceMinus[0]['requested_signature']) {
                            ResModel::update(['set' => ['status' => 'ESIG'], 'where' => ['res_id = ?'], 'data' => [$ressource['res_id']]]);
                        } else {
                            ResModel::update(['set' => ['status' => 'EVIS'], 'where' => ['res_id = ?'], 'data' => [$ressource['res_id']]]);
                        }
                    }
                }

                $listInstances = ListInstanceModel::getWithConfidentiality(['select' => ['listinstance.res_id', 'listinstance.difflist_type'], 'entityId' => $aArgs['entityId'], 'userId' => $aArgs['id']]);
                $resIdsToReplace = [];
                foreach ($listInstances as $listInstance) {
                    $resIdsToReplace[] = $listInstance['res_id'];
                }
                if (!empty($resIdsToReplace)) {
                    ListInstanceModel::update([
                        'set'   => ['process_comment' => '[DEL] supprimé - changement d\'entité', 'process_date' => 'CURRENT_TIMESTAMP'],
                        'where' => ['res_id in (?)', 'item_id = ?'],
                        'data'  => [$resIdsToReplace, $aArgs['id']]
                    ]);
                }
            }
        }

        $primaryEntity = UserModel::getPrimaryEntityById(['id' => $aArgs['id'], 'select' => ['entities.entity_label', 'entities.entity_id']]);
        UserEntityModel::deleteUserEntity(['id' => $aArgs['id'], 'entityId' => $aArgs['entityId']]);

        if (!empty($primaryEntity['entity_id']) && $primaryEntity['entity_id'] == $aArgs['entityId']) {
            UserEntityModel::reassignUserPrimaryEntity(['userId' => $aArgs['id']]);
        }

        HistoryController::add([
            'tableName' => 'users',
            'recordId'  => $aArgs['id'],
            'eventType' => 'UP',
            'info'      => _USER_ENTITY_SUPPRESSION . " : {$user['user_id']} {$aArgs['entityId']}",
            'moduleId'  => 'user',
            'eventId'   => 'userModification',
        ]);

        return $response->withJson([
            'entities'      => UserModel::getEntitiesById(['id' => $aArgs['id'], 'select' => ['entities.id', 'users_entities.entity_id', 'entities.entity_label', 'users_entities.user_role', 'users_entities.primary_entity'], 'orderBy' => ['users_entities.primary_entity DESC']]),
            'allEntities'   => EntityModel::getAvailableEntitiesForAdministratorByUserId(['userId' => $user['user_id'], 'administratorUserId' => $GLOBALS['login']])
        ]);
    }

    public function isEntityDeletable(Request $request, Response $response, array $args)
    {
        $error = $this->hasUsersRights(['id' => $args['id']]);
        if (!empty($error['error'])) {
            return $response->withStatus($error['status'])->withJson(['errors' => $error['error']]);
        }
        $entity = EntityModel::getByEntityId(['entityId' => $args['entityId'], 'select' => ['id']]);
        if (empty($entity)) {
            return $response->withStatus(400)->withJson(['errors' => 'Entity does not exist']);
        }

        $listInstances = ListInstanceModel::getWithConfidentiality(['select' => [1], 'entityId' => $args['entityId'], 'userId' => $args['id']]);

        $listTemplates = ListTemplateModel::getWithItems(['select' => [1], 'where' => ['entity_id = ?', 'item_type = ?', 'item_id = ?'], 'data' => [$entity['id'], 'user', $args['id']]]);

        return $response->withJson(['hasConfidentialityInstances' => !empty($listInstances), 'hasListTemplates' => !empty($listTemplates)]);
    }

    public function updateBasketsDisplay(Request $request, Response $response, array $aArgs)
    {
        $error = $this->hasUsersRights(['id' => $aArgs['id']]);
        if (!empty($error['error'])) {
            return $response->withStatus($error['status'])->withJson(['errors' => $error['error']]);
        }

        $data = $request->getParsedBody();
        $check = Validator::arrayType()->notEmpty()->validate($data['baskets']);
        if (!$check) {
            return $response->withStatus(400)->withJson(['errors' => 'Bad Request']);
        }

        foreach ($data['baskets'] as $basketContainer) {
            $check = Validator::stringType()->notEmpty()->validate($basketContainer['basketId']);
            $check = $check && Validator::notEmpty()->intVal()->validate($basketContainer['groupSerialId']);
            $check = $check && Validator::boolType()->validate($basketContainer['allowed']);
            if (!$check) {
                return $response->withStatus(400)->withJson(['errors' => 'Element is missing']);
            }
        }

        foreach ($data['baskets'] as $basketContainer) {
            $group = GroupModel::getById(['id' => $basketContainer['groupSerialId'], 'select' => ['group_id']]);
            $basket = BasketModel::getByBasketId(['basketId' => $basketContainer['basketId'], 'select' => [1]]);
            if (empty($group) || empty($basket)) {
                return $response->withStatus(400)->withJson(['errors' => 'Group or basket does not exist']);
            }

            $groups = UserModel::getGroupsById(['id' => $aArgs['id']]);
            $groupFound = false;
            foreach ($groups as $value) {
                if ($value['id'] == $basketContainer['groupSerialId']) {
                    $groupFound = true;
                }
            }
            if (!$groupFound) {
                return $response->withStatus(400)->withJson(['errors' => 'Group is not linked to this user']);
            }
            $groups = GroupBasketModel::get(['where' => ['basket_id = ?'], 'data' => [$basketContainer['basketId']]]);
            $groupFound = false;
            foreach ($groups as $value) {
                if ($value['group_id'] == $group['group_id']) {
                    $groupFound = true;
                }
            }
            if (!$groupFound) {
                return $response->withStatus(400)->withJson(['errors' => 'Group is not linked to this basket']);
            }

            if ($basketContainer['allowed']) {
                $preference = UserBasketPreferenceModel::get([
                    'select'    => [1],
                    'where'     => ['user_serial_id = ?', 'group_serial_id = ?', 'basket_id = ?'],
                    'data'      => [$aArgs['id'], $basketContainer['groupSerialId'], $basketContainer['basketId']]
                ]);
                if (!empty($preference)) {
                    return $response->withStatus(400)->withJson(['errors' => 'Preference already exists']);
                }
                $basketContainer['userSerialId'] = $aArgs['id'];
                $basketContainer['display'] = 'true';
                UserBasketPreferenceModel::create($basketContainer);
            } else {
                UserBasketPreferenceModel::delete([
                    'where' => ['user_serial_id = ?', 'group_serial_id = ?', 'basket_id = ?'],
                    'data'  => [$aArgs['id'], $basketContainer['groupSerialId'], $basketContainer['basketId']]
                ]);
            }
        }

        return $response->withJson(['success' => 'success']);
    }

    public function getTemplates(Request $request, Response $response)
    {
        $queryParams = $request->getQueryParams();

        $entities = UserModel::getEntitiesById(['id' => $GLOBALS['id'], 'select' => ['users_entities.entity_id']]);
        $entities = array_column($entities, 'entity_id');
        if (empty($entities)) {
            $entities = [0];
        }

        $where = ['(templates_association.value_field in (?) OR templates_association.template_id IS NULL)'];
        $data = [$entities];
        if (!empty($queryParams['type'])) {
            $where[] = 'templates.template_type = ?';
            $data[] = strtoupper($queryParams['type']);
        }
        if (!empty($queryParams['target'])) {
            $where[] = 'templates.template_target = ?';
            $data[] = $queryParams['target'];
        }
        $templates = TemplateModel::getWithAssociation([
            'select'    => ['DISTINCT(templates.template_id)', 'templates.template_label', 'templates.template_file_name', 'templates.template_path', 'templates.template_target', 'templates.template_attachment_type'],
            'where'     => $where,
            'data'      => $data,
            'orderBy'   => ['templates.template_label']
        ]);

        $docserver = DocserverModel::getCurrentDocserver(['typeId' => 'TEMPLATES', 'collId' => 'templates', 'select' => ['path_template']]);
        foreach ($templates as $key => $template) {
            $explodeFile = explode('.', $template['template_file_name']);
            $ext = $explodeFile[count($explodeFile) - 1];
            $exists = is_file($docserver['path_template'] . str_replace('#', DIRECTORY_SEPARATOR, $template['template_path']) . $template['template_file_name']);

            $templates[$key] = [
                'id'                => $template['template_id'],
                'label'             => $template['template_label'],
                'extension'         => $ext,
                'exists'            => $exists,
                'target'            => $template['template_target'],
                'attachmentType'    => $template['template_attachment_type']
            ];
        }
        
        return $response->withJson(['templates' => $templates]);
    }

    public function updateCurrentUserBasketPreferences(Request $request, Response $response, array $aArgs)
    {
        $data = $request->getParsedBody();

        if (isset($data['color']) && $data['color'] == '') {
            UserBasketPreferenceModel::update([
                'set'   => ['color' => null],
                'where' => ['user_serial_id = ?', 'group_serial_id = ?', 'basket_id = ?'],
                'data'  => [$GLOBALS['id'], $aArgs['groupId'], $aArgs['basketId']]
            ]);
        } elseif (!empty($data['color'])) {
            UserBasketPreferenceModel::update([
                'set'   => ['color' => $data['color']],
                'where' => ['user_serial_id = ?', 'group_serial_id = ?', 'basket_id = ?'],
                'data'  => [$GLOBALS['id'], $aArgs['groupId'], $aArgs['basketId']]
            ]);
        }

        return $response->withJson([
            'userBaskets' => BasketModel::getRegroupedBasketsByUserId(['userId' => $GLOBALS['login']])
        ]);
    }

    public function sendAccountActivationNotification(Request $request, Response $response, array $args)
    {
        $control = $this->hasUsersRights(['id' => $args['id']]);
        if (!empty($control['error'])) {
            return $response->withStatus($control['status'])->withJson(['errors' => $control['error']]);
        }

        $loggingMethod = CoreConfigModel::getLoggingMethod();
        if ($loggingMethod['id'] != 'standard') {
            return $response->withStatus(403)->withJson(['errors' => 'Cannot send activation notification when not using standard connection']);
        }

        $user = UserModel::getById(['id' => $args['id'], 'select' => ['mail']]);

        AuthenticationController::sendAccountActivationNotification(['userId' => $args['id'], 'userEmail' => $user['mail']]);

        return $response->withStatus(204);
    }

    public function getExport(Request $request, Response $response)
    {
        if (!PrivilegeController::hasPrivilege(['privilegeId' => 'admin_users', 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }

        $allowedFields = [
            'id'                => 'id',
            'userId'            => 'user_id',
            'firstname'         => 'firstname',
            'lastname'          => 'lastname',
            'phone'             => 'phone',
            'mail'              => 'mail',
            'status'            => 'status',
            'accountType'       => 'mode',
            'groups'            => 'groups',
            'entities'          => 'entities',
            'baskets'           => 'baskets',
            'redirectedBaskets' => 'redirectedBaskets',
            'assignedBaskets'   => 'assignedBaskets'
        ];
        $personalFields = ['phone'];
        $defaultFields = ['id', 'userId', 'firstname', 'lastname', 'phone', 'mail'];

        $metaFieldQueries = [
            'groups' => "(
                SELECT string_agg(ug.group_desc, '\n')
                FROM usergroups ug, usergroup_content ugc
                WHERE ugc.group_id = ug.id AND ugc.user_id = users.id
                ) AS groups",

            'entities' => "(
                SELECT string_agg(trim(' ' FROM replace(replace(
                        e.entity_label
                        || ' [" . _ROLE . " : ' || ue.user_role || '] '
                        || (CASE WHEN ue.primary_entity = 'Y' THEN '(" . _PRIMARY_ENTITY . ")' ELSE '' END)
                        || ' ',
                    '[" . _ROLE . " : ]', ''), '  ', ' ')), '\n')
                FROM entities e, users_entities ue
                WHERE ue.entity_id = e.entity_id AND ue.user_id = users.id
                ) AS entities",

            'baskets' => "(
                SELECT string_agg(
                    b.basket_name || ' (' || ug.group_desc || ')',
                    '\n')
                FROM usergroups ug, usergroup_content ugc, groupbasket gb, baskets b, users_baskets_preferences ubp
                WHERE ugc.group_id = ug.id AND gb.group_id = ug.group_id AND gb.basket_id = b.basket_id
                    AND ubp.user_serial_id = users.id AND ubp.group_serial_id = ug.id AND ubp.basket_id = b.basket_id
                    AND b.basket_id NOT IN (SELECT rb.basket_id FROM redirected_baskets rb WHERE rb.group_id = ug.id AND rb.owner_user_id = users.id)
                    AND ugc.user_id = users.id
                ) AS baskets",

            'redirectedBaskets' => "(
                SELECT string_agg(
                    b.basket_name
                    || ' (' || ug.group_desc || ')'
                    || ' " . _REDIRECTED_TO . " ' || trim(' ' FROM u_actual.firstname || ' ' || u_actual.lastname),
                    '\n')
                FROM redirected_baskets rb, users u_actual, usergroups ug, usergroup_content ugc, baskets b
                WHERE rb.owner_user_id = users.id AND rb.actual_user_id = u_actual.id
                    AND rb.group_id = ug.id AND ug.id = ugc.group_id AND ugc.user_id = users.id AND rb.basket_id = b.basket_id
                ) as \"redirectedBaskets\"",

            'assignedBaskets' => "(
                SELECT string_agg(
                    b.basket_name
                    || ' (' || ug.group_desc || ')'
                    || ' " . _ASSIGNED_BY . " ' || trim(' ' FROM u_owner.firstname || ' ' || u_owner.lastname),
                    '\n')
                FROM redirected_baskets rb, users u_owner, usergroups ug, usergroup_content ugc, baskets b
                WHERE rb.actual_user_id = users.id AND rb.owner_user_id = u_owner.id
                    AND rb.group_id = ug.id AND ug.id = ugc.group_id AND ugc.user_id = u_owner.id AND rb.basket_id = b.basket_id
                ) as \"assignedBaskets\""
        ];

        $fields = [];
        
        $body = $request->getParsedBody();
        if (empty($body['data'])) {
            foreach ($defaultFields as $field) {
                $fields[] = ['label' => $allowedFields[$field], 'value' => $field];
            }
        } else {
            foreach ($body['data'] as $parameter) {
                if (!empty($parameter['label']) && is_string($parameter['label']) && !empty($parameter['value']) && is_string($parameter['value'])) {
                    if (!in_array($parameter['value'], array_keys($allowedFields))) {
                        continue;
                    }
                    $fields[] = [
                        'label' => $parameter['label'],
                        'value' => $parameter['value']
                    ];
                }
            }
        }
        $select = array_map(function ($field) use ($allowedFields) {
            return $allowedFields[$field['value']];
        }, $fields);
        foreach ($select as $key => $value) {
            if (array_key_exists($value, $metaFieldQueries)) {
                $select[$key] = $metaFieldQueries[$value];
            } else {
                $select[$key] = 'users.' . $value;
            }
        }
        if (empty($select)) {
            return $response->withStatus(400)->withJson(['errors' => 'no allowed field selected for users export']);
        }

        if (UserController::isRoot(['id' => $GLOBALS['id']])) {
            $users = UserModel::get([
                'select'  => $select,
                'where'   => ['status != ?'],
                'data'    => ['DEL'],
                'orderBy' => ['id ASC']
            ]);
        } else {
            $viewPersonaldata = false;
            if (PrivilegeController::hasPrivilege(['privilegeId' => 'view_personal_data', 'userId' => $GLOBALS['id']])) {
                $viewPersonaldata = true;
            }

            $entities = EntityModel::getAllEntitiesByUserId(['userId' => $GLOBALS['id']]);
            $users = [];
            if (!$viewPersonaldata) {
                foreach ($select as $selectKey => $selectValue) {
                    foreach ($personalFields as $personalField) {
                        if (strrpos($selectValue, 'users.' . $personalField) !== false) {
                            // TODO: replace this with str_ends_with in PHP8
                            unset($select[$selectKey]);
                        }
                    }
                }
            }
            if (!empty($entities)) {
                $users = UserEntityModel::getWithUsers([
                    'select'    => $select,
                    'where'     => ['users_entities.entity_id in (?)', 'status != ?'],
                    'data'      => [$entities, 'DEL']
                ]);
            }
            $usersNoEntities = UserEntityModel::getUsersWithoutEntities(['select' => $select]);
            $users = array_merge($users, $usersNoEntities);
        }

        $delimiter = ',';
        if (!empty($body['delimiter'])) {
            if (in_array($body['delimiter'], [',', ';', 'TAB'])) {
                $delimiter = ($body['delimiter'] == 'TAB' ? "\t" : $body['delimiter']);
            }
        }

        $file = fopen('php://temp', 'w');

        $csvHead = array_map(function ($field) { return $field; }, array_column($fields, 'label'));
        fputcsv($file, $csvHead, $delimiter);

        $userAccountTypes = [
            'standard'          => _STANDARD_,
            'rest'              => _REST_,
            'root_visible'      => _ROOT_VISIBLE_,
            'root_invisible'    => _ROOT_INVISIBLE_
        ];

        $userStatus = [
            'OK'    => _OK_,
            'SPD'   => _SPD_,
            'ABS'   => _ABS_
        ];

        foreach ($users as $user) {
            $csvContent = [];
            foreach ($fields as $field) {
                if ($field['value'] == 'accountType') {
                    $user[$allowedFields[$field['value']]] = $user[$allowedFields[$field['value']]] . ' (' . $userAccountTypes[$user[$allowedFields[$field['value']]]] . ')';
                }

                if ($field['value'] == 'status') {
                    $user[$allowedFields[$field['value']]] = $user[$allowedFields[$field['value']]] . ' (' . $userStatus[$user[$allowedFields[$field['value']]]] . ')';
                }

                if ($field['value'] == 'baskets' || $field['value'] == 'redirectedBaskets' || $field['value'] == 'assignedBaskets') {
                    $array = explode("\n", $user[$allowedFields[$field['value']]]);
                    sort($array);
                    $user[$allowedFields[$field['value']]] = implode("\n", $array);
                }

                if ($field['value'] == 'entities') {
                    $array = explode("\n", $user[$allowedFields[$field['value']]]);
                    $searchTerm = _PRIMARY_ENTITY;
                    $primaryEntity = '';
                    $index = false;
                    foreach ($array as $key => $value) {
                        if (strpos($value, $searchTerm) !== false) {
                            $primaryEntity  = $value;
                            $index = $key;
                            break;
                        }
                    }

                    if ($index !== false) {
                        unset($array[$index]);
                        array_unshift($array, $primaryEntity);
                    }
                    $user[$allowedFields[$field['value']]] = implode("\n", $array);
                }
                $csvContent[] = $user[$allowedFields[$field['value']]] ?? '';
            }
            fputcsv($file, $csvContent, $delimiter);
        }

        rewind($file);

        $response->write(stream_get_contents($file));
        $response = $response->withAddedHeader('Content-Disposition', 'attachment; filename=export_maarch.csv');
        $contentType = 'application/vnd.ms-excel';
        fclose($file);

        return $response->withHeader('Content-Type', $contentType);
    }

    public function setImport(Request $request, Response $response)
    {
        if (!PrivilegeController::hasPrivilege(['privilegeId' => 'admin_users', 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }

        $body = $request->getParsedBody();
        if (!Validator::arrayType()->validate($body['users'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body users is empty or not an array']);
        }

        $isRoot = UserController::isRoot(['id' => $GLOBALS['id']]);
        $allowedUsers = [];
        if (!$isRoot) {
            $entities = EntityModel::getAllEntitiesByUserId(['userId' => $GLOBALS['id']]);
            $allowedUsers = [];
            if (!empty($entities)) {
                $allowedUsers = UserEntityModel::getWithUsers([
                    'select'    => ['DISTINCT users.id'],
                    'where'     => ['users_entities.entity_id in (?)', 'status != ?'],
                    'data'      => [$entities, 'DEL']
                ]);
            }
            $usersNoEntities = UserEntityModel::getUsersWithoutEntities(['select' => ['id']]);
            $allowedUsers = array_merge($allowedUsers, $usersNoEntities);
            $allowedUsers = array_column($allowedUsers, 'id');
        }

        $warnings = [];
        $errors = [];
        foreach ($body['users'] as $key => $user) {
            if (!empty($user['firstname']) && (!Validator::stringType()->validate($user['firstname']) || !Validator::length(1, 255)->validate($user['firstname']))) {
                $errors[] = ['error' => "Argument firstname is not a string for user {$key}", 'index' => $key, 'lang' => 'argumentFirstnameNotString'];
                continue;
            } elseif (!empty($user['lastname']) && (!Validator::stringType()->validate($user['lastname']) || !Validator::length(1, 255)->validate($user['lastname']))) {
                $errors[] = ['error' => "Argument lastname is not a string for user {$key}", 'index' => $key, 'lang' => 'argumentLastnameNotString'];
                continue;
            } elseif (!empty($user['mail']) && (!filter_var($user['mail'], FILTER_VALIDATE_EMAIL) || !Validator::length(1, 255)->validate($user['mail']))) {
                $errors[] = ['error' => "Argument mail is not correct for user {$key}", 'index' => $key, 'lang' => 'argumentMailNotCorrect'];
                continue;
            } elseif (!empty($user['phone']) && (!preg_match("/\+?((|\ |\.|\(|\)|\-)?(\d)*)*\d$/", $user['phone']) || !Validator::length(1, 32)->validate($user['phone']))) {
                $errors[] = ['error' => "Argument phone is not correct for user {$key}", 'index' => $key, 'lang' => 'argumentPhoneNotCorrect'];
                continue;
            }
            if (empty($user['id'])) {
                if (empty($user['user_id'])) {
                    $errors[] = ['error' => "Argument user_id is empty for user {$key}", 'index' => $key, 'lang' => 'argumentUserIdEmpty'];
                    continue;
                } elseif (empty($user['firstname'])) {
                    $errors[] = ['error' => "Argument firstname is empty for user {$key}", 'index' => $key, 'lang' => 'argumentFirstnameEmpty'];
                    continue;
                } elseif (empty($user['lastname'])) {
                    $errors[] = ['error' => "Argument lastname is empty for user {$key}", 'index' => $key, 'lang' => 'argumentLastnameEmpty'];
                    continue;
                } elseif (empty($user['mail'])) {
                    $errors[] = ['error' => "Argument mail is empty for user {$key}", 'index' => $key, 'lang' => 'argumentMailEmpty'];
                    continue;
                }

                $existingUser = UserModel::getByLogin(['login' => strtolower($user['user_id']), 'select' => ['id', 'status']]);
                if (!empty($existingUser) && $existingUser['status'] != 'DEL') {
                    $errors[] = ['error' => "User already exists with login {$user['user_id']}", 'index' => $key, 'lang' => 'userLoginAlreadyExists'];
                    continue;
                } elseif (!empty($existingUser) && $existingUser['status'] == 'DEL') {
                    UserModel::update([
                        'set'   => [
                            'status'    => 'OK',
                            'mail'      => $user['mail'],
                            'password'  => AuthenticationModel::getPasswordHash(AuthenticationModel::generatePassword()),
                        ],
                        'where' => ['id = ?'],
                        'data'  => [$existingUser['id']]
                    ]);
                    $id = $existingUser['id'];
                    $warnings[] = ['warning' => "User {$user['user_id']} was deleted and is now reactivated", 'index' => $key, 'lang' => 'userDeletedNowActivated'];
                } else {
                    $userToCreate = [
                        'userId'        => $user['user_id'],
                        'firstname'     => $user['firstname'],
                        'lastname'      => $user['lastname'],
                        'mail'          => $user['mail'],
                        'preferences'   => json_encode(['documentEdition' => 'java'])
                    ];
                    if (!empty($user['phone']) && PrivilegeController::hasPrivilege(['privilegeId' => 'manage_personal_data', 'userId' => $GLOBALS['id']])) {
                        $userToCreate['phone'] = $user['phone'];
                    } elseif (!empty($user['phone']) && !PrivilegeController::hasPrivilege(['privilegeId' => 'manage_personal_data', 'userId' => $GLOBALS['id']])) {
                        $warnings[] = ['warning' => "Phone is not allowed to be modified", 'index' => $key, 'lang' => 'phoneModificationNotAllowed'];
                    }
                    $id = UserModel::create(['user' => $userToCreate]);
                }

                $loggingMethod = CoreConfigModel::getLoggingMethod();
                if ($loggingMethod['id'] == 'standard') {
                    AuthenticationController::sendAccountActivationNotification(['userId' => $id, 'userEmail' => $user['mail']]);
                }
            } else {
                if (!$isRoot && !in_array($user['id'], $allowedUsers)) {
                    $errors[] = ['error' => "User is not allowed to be modified {$user['user_id']}", 'index' => $key, 'lang' => 'userModificationNotAllowed'];
                    continue;
                }

                $set = [];
                if (!empty($user['firstname'])) {
                    $set['firstname'] = $user['firstname'];
                }
                if (!empty($user['lastname'])) {
                    $set['lastname'] = $user['lastname'];
                }
                if (!empty($user['mail'])) {
                    $set['mail'] = $user['mail'];
                }
                if (!empty($user['phone']) && PrivilegeController::hasPrivilege(['privilegeId' => 'manage_personal_data', 'userId' => $GLOBALS['id']])) {
                    $set['phone'] = $user['phone'];
                } elseif (!empty($user['phone']) && !PrivilegeController::hasPrivilege(['privilegeId' => 'manage_personal_data', 'userId' => $GLOBALS['id']])) {
                    $warnings[] = ['warning' => "Phone is not allowed to be modified", 'index' => $key, 'lang' => 'phoneModificationNotAllowed'];
                }

                if (!empty($set)) {
                    UserModel::update([
                        'set'   => $set,
                        'where' => ['id = ?'],
                        'data'  => [$user['id']]
                    ]);
                }
            }
        }

        $return = [
            'success'   => count($body['users']) - count($warnings) - count($errors),
            'warnings'  => [
                'count'     => count($warnings),
                'details'   => $warnings
            ],
            'errors'    => [
                'count'     => count($errors),
                'details'   => $errors
            ]
        ];

        return $response->withJson($return);
    }

    public function hasUsersRights(array $args)
    {
        if (!is_numeric($args['id'])) {
            return ['status' => 400, 'error' => 'id must be an integer'];
        }

        $user = UserModel::getById(['id' => $args['id'], 'select' => ['id']]);
        if (empty($user['id'])) {
            return ['status' => 400, 'error' => 'User not found'];
        }

        if (empty($args['himself']) || $GLOBALS['id'] != $user['id']) {
            if (!PrivilegeController::hasPrivilege(['privilegeId' => 'admin_users', 'userId' => $GLOBALS['id']])) {
                return ['status' => 403, 'error' => 'Service forbidden'];
            }
            $isRoot = UserController::isRoot(['id' => $GLOBALS['id']]);
            if (!$isRoot) {
                $users = [];
                $entities = EntityModel::getAllEntitiesByUserId(['userId' => $GLOBALS['id']]);
                if (!empty($entities)) {
                    $users = UserEntityModel::getWithUsers([
                        'select'    => ['users.id'],
                        'where'     => ['users_entities.entity_id in (?)', 'status != ?'],
                        'data'      => [$entities, 'DEL']
                    ]);
                }
                $usersNoEntities = UserEntityModel::getUsersWithoutEntities(['select' => ['id']]);
                $users = array_merge($users, $usersNoEntities);
                $allowed = false;
                foreach ($users as $value) {
                    if ($value['id'] == $args['id']) {
                        $allowed = true;
                    }
                }
                if (!$allowed) {
                    return ['status' => 403, 'error' => 'UserId out of perimeter'];
                }
            }
        } elseif (!empty($args['delete']) && $GLOBALS['id'] == $user['id']) {
            return ['status' => 403, 'error' => 'Can not delete yourself'];
        }

        return true;
    }

    private function checkNeededParameters(array $aArgs)
    {
        foreach ($aArgs['needed'] as $value) {
            if (empty($aArgs['data'][$value])) {
                return false;
            }
        }

        return true;
    }

    public function forgotPassword(Request $request, Response $response)
    {
        $body = $request->getParsedBody();

        if (!Validator::stringType()->notEmpty()->validate($body['login'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body login is empty']);
        }

        $user = UserModel::getByLogin(['select' => ['id', 'mail'], 'login' => strtolower($body['login'])]);
        if (empty($user)) {
            return $response->withStatus(204);
        }

        $GLOBALS['id'] = $user['id'];
        $GLOBALS['login'] = $body['login'];

        $resetToken = AuthenticationController::getResetJWT(['id' => $user['id'], 'expirationTime' => 3600]); // 1 hour
        UserModel::update(['set' => ['reset_token' => $resetToken], 'where' => ['id = ?'], 'data' => [$user['id']]]);

        $url = UrlController::getCoreUrl() . 'dist/index.html#/reset-password?token=' . $resetToken;
        $configuration = ConfigurationModel::getByPrivilege(['privilege' => 'admin_email_server', 'select' => ['value']]);
        $configuration = json_decode($configuration['value'], true);
        if (!empty($configuration['from'])) {
            $sender = $configuration['from'];
        } else {
            $sender = $user['mail'];
        }
        $email = EmailController::createEmail([
            'userId'    => $user['id'],
            'data'      => [
                'sender'        => ['email' => $sender],
                'recipients'    => [$user['mail']],
                'object'        => _NOTIFICATIONS_FORGOT_PASSWORD_SUBJECT,
                'body'          => _NOTIFICATIONS_FORGOT_PASSWORD_BODY . '<a href="' . $url . '">'._CLICK_HERE.'</a>' . _NOTIFICATIONS_FORGOT_PASSWORD_FOOTER,
                'isHtml'        => true,
                'status'        => 'WAITING'
            ]
        ]);

        if (!empty($email['errors'])) {
            $historyMessage = $email['errors'];
        } else {
            $historyMessage = _PASSWORD_REINIT_SENT;
        }
        HistoryController::add([
            'tableName'    => 'users',
            'recordId'     => $GLOBALS['id'],
            'eventType'    => 'RESETPSW',
            'eventId'      => 'userModification',
            'info'         => $historyMessage
        ]);

        return $response->withStatus(204);
    }

    public function passwordInitialization(Request $request, Response $response)
    {
        $body = $request->getParsedBody();

        $check = Validator::stringType()->notEmpty()->validate($body['token']);
        $check = $check && Validator::stringType()->notEmpty()->validate($body['password']);
        if (!$check) {
            return $response->withStatus(400)->withJson(['errors' => 'Body token or body password is empty']);
        }

        try {
            $jwt = JWT::decode($body['token'], CoreConfigModel::getEncryptKey(), ['HS256']);
        } catch (\Exception $e) {
            return $response->withStatus(403)->withJson(['errors' => 'Invalid token', 'lang' => 'invalidToken']);
        }

        $user = UserModel::getById(['id' => $jwt->user->id, 'select' => ['user_id', 'id', 'reset_token']]);
        if (empty($user)) {
            return $response->withStatus(400)->withJson(['errors' => 'User does not exist']);
        }

        if ($body['token'] != $user['reset_token']) {
            return $response->withStatus(403)->withJson(['errors' => 'Invalid token', 'lang' => 'invalidToken']);
        }

        if (!PasswordController::isPasswordValid(['password' => $body['password']])) {
            return $response->withStatus(400)->withJson(['errors' => 'Password does not match security criteria']);
        }

        UserModel::resetPassword(['password' => $body['password'], 'id'  => $user['id']]);

        $GLOBALS['id'] = $user['id'];
        $GLOBALS['login'] = $user['user_id'];

        HistoryController::add([
            'tableName'    => 'users',
            'recordId'     => $GLOBALS['id'],
            'eventType'    => 'UP',
            'eventId'      => 'userModification',
            'info'         => _PASSWORD_REINIT . " {$body['login']}"
        ]);

        return $response->withStatus(204);
    }

    public function getCurrentUserEmailSignatures(Request $request, Response $response)
    {
        $signatures = UserController::getSignatures(['withContent' => true]);

        return $response->withJson(['emailSignatures' => $signatures['signatures']]);
    }

    public function getCurrentUserEmailSignaturesList(Request $request, Response $response)
    {
        $signatures = UserController::getSignatures(['withContent' => false]);

        return $response->withJson(['emailSignatures' => $signatures['signatures']]);
    }

    public static function getSignatures($args = [])
    {
        $signatures = [];
        
        $signatureModels = UserEmailSignatureModel::getByUserId(['userId' => $GLOBALS['id']]);
        foreach ($signatureModels as $signature) {
            $signatureTmp = [
                'id'     => $signature['id'],
                'label'  => $signature['title'],
                'public' => false
            ];

            if ($args['withContent']) {
                $signatureTmp['content'] = $signature['html_body'];
            }

            $signatures[] = $signatureTmp;
        }

        $globalEmailSignatures = ConfigurationModel::getByPrivilege(['privilege' => 'admin_organization_email_signatures', 'select' => ['value']]);
        $value = json_decode($globalEmailSignatures['value'], true);
        if (!empty($value['signatures'])) {
            foreach ($value['signatures'] as $key => $globalEmailSignature) {
                $signatureTmp = [
                    'id'     => $key,
                    'label'  => $globalEmailSignature['label'],
                    'public' => true
                ];

                if ($args['withContent']) {
                    $signatureTmp['content'] = MergeController::mergeGlobalEmailSignature(['content' => $globalEmailSignature['content']]);
                }

                $signatures[] = $signatureTmp;
            }
        }

        return ['signatures' => $signatures];
    }

    public function getGlobalEmailSignatureById(Request $request, Response $response, array $args)
    {
        if (!Validator::intVal()->validate($args['id'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body param id is empty or not an integer']);
        }

        $globalEmailSignatures = ConfigurationModel::getByPrivilege(['privilege' => 'admin_organization_email_signatures', 'select' => ['value']]);
        $value = json_decode($globalEmailSignatures['value'], true);
        if (!empty($value['signatures'])) {
            $signature = [
                'id'      => $args['id'],
                'label'   => $value['signatures'][$args['id']]['label'],
                'content' => MergeController::mergeGlobalEmailSignature(['content' => $value['signatures'][$args['id']]['content']])
            ];
        }

        return $response->withJson(['emailSignature' => $signature]);
    }

    public function getCurrentUserEmailSignatureById(Request $request, Response $response, array $args)
    {
        if (!Validator::notEmpty()->intVal()->validate($args['id'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body param id is empty or not an integer']);
        }

        $signature = UserEmailSignatureModel::getById(['id' => $args['id']]);
        if (empty($signature) || $signature['user_id'] != $GLOBALS['id']) {
            return $response->withStatus(404)->withJson(['errors' => 'Signature not found']);
        }

        $signature = [
            'id'      => $signature['id'],
            'label'   => $signature['title'],
            'content' => $signature['html_body']
        ];

        return $response->withJson(['emailSignature' => $signature]);
    }

    public static function isRoot(array $args)
    {
        ValidatorModel::notEmpty($args, ['id']);
        ValidatorModel::intVal($args, ['id']);

        $user = UserModel::getById(['select' => ['mode'], 'id' => $args['id']]);

        $isRoot = ($user['mode'] == 'root_visible' || $user['mode'] == 'root_invisible');

        return $isRoot;
    }

    public function setAbsenceRange(Request $request, Response $response, array $args)
    {
        $error = $this->hasUsersRights(['id' => $args['id'], 'himself' => true]);
        if (!empty($error['error'])) {
            return $response->withStatus($error['status'])->withJson(['errors' => $error['error']]);
        }

        $body = $request->getParsedBody();

        $userAbsence = UserModel::getById(['id' => $args['id'], 'select' => ['absence']]);
        if (!empty($userAbsence['absence'])) {
            $userAbsence = json_decode($userAbsence['absence'], true);
            if (!empty($userAbsence['absenceDate']['startDate'])) {
                $absenceStartDate = new \DateTime($userAbsence['absenceDate']['startDate']);
                $today = new \DateTime();

                if ($today < $absenceStartDate && empty($body['absenceDate']['startDate']) && empty($body['absenceDate']['endDate']) && empty($body['redirectedBaskets'])) {
                    UserModel::update(['set' => ['absence' => null], 'where' => ['id = ?'], 'data' => [$args['id']]]);
                    return $response->withStatus(204);
                }
            }
        }


        if (!Validator::arrayType()->notEmpty()->validate($body['absenceDate'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body absenceDate is empty or not an array']);
        } elseif (!Validator::stringType()->notEmpty()->validate($body['absenceDate']['endDate'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body absence[endDate] is empty or not a string']);
        }

        foreach ($body['redirectedBaskets'] as $key => $value) {
            if (!Validator::intType()->notEmpty()->validate($value['actual_user_id'])) {
                return $response->withStatus(400)->withJson(['errors' => "Body redirectedBaskets[$key][actual_user_id] is empty or not an integer"]);
            } elseif (!Validator::intType()->notEmpty()->validate($value['group_id'])) {
                return $response->withStatus(400)->withJson(['errors' => "Body redirectedBaskets[$key][group_id] is empty or not an integer"]);
            } elseif (!Validator::stringType()->notEmpty()->validate($value['basket_id'])) {
                return $response->withStatus(400)->withJson(['errors' => "Body redirectedBaskets[$key][basket_id] is empty or not an integer"]);
            }

            $check = UserModel::getById(['id' => $value['actual_user_id'], 'select' => ['1']]);
            if (empty($check)) {
                return $response->withStatus(400)->withJson(['errors' => 'User not found']);
            }
        }

        if (empty($body['redirectedBaskets'])) {
            $body['redirectedBaskets'] = [];
        }
        $absence = ['absenceDate' => $body['absenceDate'], 'redirectedBaskets' => $body['redirectedBaskets']];
        UserModel::update(['set' => ['absence' => json_encode($absence)], 'where' => ['id = ?'], 'data' => [$args['id']]]);

        $absenceStartDate = new \DateTime($body['absenceDate']['startDate']);
        $today = new \DateTime();
        if ($absenceStartDate <= $today) {
            UserController::setAbsences();
        }

        return $response->withStatus(204);
    }

    public static function setAbsences()
    {
        $absentUsers = UserModel::get(['select' => ['id', 'absence', 'user_id'], 'where' => ['absence is not null', 'status = ?'], 'data' => ['ABS']]);
        foreach ($absentUsers as $absentUser) {
            $absentUser['absence'] = json_decode($absentUser['absence'], true);
            $absenceEndDate = new \DateTime($absentUser['absence']['absenceDate']['endDate']);
            $today = new \DateTime();
            if ($today > $absenceEndDate) {
                UserModel::update(['set' => ['absence' => null, 'status' => 'OK'], 'where' => ['id = ?'], 'data' => [$absentUser['id']]]);

                foreach ($absentUser['absence']['redirectedBaskets'] as $redirectedBasket) {
                    RedirectBasketModel::delete([
                        'where' => ['actual_user_id = ?', 'basket_id = ?', 'group_id = ?', 'owner_user_id = ?'],
                        'data'  => [$redirectedBasket['actual_user_id'], $redirectedBasket['basket_id'], $redirectedBasket['group_id'], $absentUser['id']]
                    ]);

                    HistoryController::add([
                        'tableName' => 'redirected_baskets',
                        'recordId'  => $absentUser['user_id'],
                        'eventType' => 'DEL',
                        'eventId'   => 'basketRedirection',
                        'info'      => _BASKET_REDIRECTION_SUPPRESSION . " {$absentUser['user_id']} : " . $redirectedBasket['basket_id'],
                        'userId'    => !empty($GLOBALS['id']) ? $GLOBALS['id'] : $absentUser['id']
                    ]);
                }
            }
        }

        $futureAbsentUsers = UserModel::get([
            'select' => ['id', 'absence', 'user_id'],
            'where'  => ['absence is not null', 'status = ?'],
            'data'   => ['OK']
        ]);
        foreach ($futureAbsentUsers as $absentUser) {
            $absentUser['absence'] = json_decode($absentUser['absence'], true);
            $absenceStartDate = new \DateTime($absentUser['absence']['absenceDate']['startDate']);
            $today = new \DateTime();
            if ($today > $absenceStartDate) {
                UserModel::update(['set' => ['status' => 'ABS'], 'where' => ['id = ?'], 'data' => [$absentUser['id']]]);
                RedirectBasketModel::delete([
                    'where' => ['owner_user_id = ?'],
                    'data'  => [$absentUser['id']]
                ]);

                if (!empty($absentUser['absence']['redirectedBaskets'])) {
                    UserController::redirectBasket([
                        'redirectedBaskets' => $absentUser['absence']['redirectedBaskets'],
                        'userId'            => $absentUser['id'],
                        'login'             => $absentUser['user_id']
                    ]);
                }
            }
        }
    }

    private static function redirectBasket(array $args)
    {
        ValidatorModel::notEmpty($args, ['redirectedBaskets', 'userId', 'login']);
        ValidatorModel::arrayType($args, ['redirectedBaskets']);
        ValidatorModel::intVal($args, ['userId']);
        ValidatorModel::stringType($args, ['login']);

        DatabaseModel::beginTransaction();
        foreach ($args['redirectedBaskets'] as $key => $value) {
            if (empty($value['actual_user_id']) || empty($value['basket_id']) || empty($value['group_id'])) {
                DatabaseModel::rollbackTransaction();
                return ['errors' => 'Some data are empty'];
            }

            $check = UserModel::getById(['id' => $value['actual_user_id'], 'select' => ['1']]);
            if (empty($check)) {
                DatabaseModel::rollbackTransaction();
                return ['errors' => 'User not found'];
            }

            if (empty($value['originalOwner'])) {
                $userBasketPreference = UserBasketPreferenceModel::get([
                    'select' => ['display'],
                    'where'  => ['basket_id =?', 'group_serial_id = ?', 'user_serial_id = ?'],
                    'data'   => [$value['basket_id'], $value['group_id'], $args['userId']]
                ]);
                if (empty($userBasketPreference[0]['display'])) {
                    unset($args['redirectedBaskets'][$key]);
                    continue;
                }
            }

            $check = RedirectBasketModel::get([
                'select' => [1],
                'where'  => ['actual_user_id = ?', 'owner_user_id = ?', 'basket_id = ?', 'group_id = ?'],
                'data'   => [$value['actual_user_id'], $args['userId'], $value['basket_id'], $value['group_id']]
            ]);
            if (!empty($check)) {
                DatabaseModel::rollbackTransaction();
                return ['errors' => 'Redirection already exist'];
            }

            if (!empty($value['originalOwner'])) {
                RedirectBasketModel::update([
                    'actual_user_id' => $value['actual_user_id'],
                    'basket_id'      => $value['basket_id'],
                    'group_id'       => $value['group_id'],
                    'owner_user_id'  => $value['originalOwner']
                ]);
                HistoryController::add([
                    'tableName' => 'redirected_baskets',
                    'recordId'  => $args['login'],
                    'eventType' => 'UP',
                    'eventId'   => 'basketRedirection',
                    'info'      => _BASKET_REDIRECTION . " {$value['basket_id']} {$value['actual_user_id']}",
                    'userId'    => !empty($GLOBALS['id']) ? $GLOBALS['id'] : $args['userId']
                ]);
                unset($args['redirectedBaskets'][$key]);
            }
        }

        if (!empty($args['redirectedBaskets'])) {
            foreach ($args['redirectedBaskets'] as $value) {
                RedirectBasketModel::delete([
                    'where' => ['basket_id = ?', 'group_id = ?', 'owner_user_id = ?'],
                    'data'  => [$value['basket_id'], $value['group_id'], $args['userId']]
                ]);
                RedirectBasketModel::create([
                    'actual_user_id' => $value['actual_user_id'],
                    'basket_id'      => $value['basket_id'],
                    'group_id'       => $value['group_id'],
                    'owner_user_id'  => $args['userId']
                ]);
                HistoryController::add([
                    'tableName' => 'redirected_baskets',
                    'recordId'  => $args['login'],
                    'eventType' => 'UP',
                    'eventId'   => 'basketRedirection',
                    'info'      => _BASKET_REDIRECTION . " {$value['basket_id']} {$args['userId']} => {$value['actual_user_id']}",
                    'userId'    => !empty($GLOBALS['id']) ? $GLOBALS['id'] : $args['userId']
                ]);
            }
        }

        DatabaseModel::commitTransaction();

        return true;
    }
}
