<?php

/**
* Copyright Maarch since 2008 under licence GPLv3.
* See LICENCE.txt file at the root folder for more details.
* This file is part of Maarch software.
*
*/

/**
* @brief Entity Controller
* @author dev@maarch.org
*/

namespace Entity\controllers;

use Basket\models\GroupBasketRedirectModel;
use Contact\models\ContactGroupListModel;
use Entity\models\EntityModel;
use Entity\models\ListInstanceModel;
use Entity\models\ListTemplateItemModel;
use Entity\models\ListTemplateModel;
use Group\controllers\PrivilegeController;
use Group\models\GroupModel;
use History\controllers\HistoryController;
use MessageExchange\controllers\AnnuaryController;
use Parameter\models\ParameterModel;
use Resource\models\ResModel;
use Resource\models\ResourceContactModel;
use Respect\Validation\Validator;
use Slim\Psr7\Request;
use SrcCore\http\Response;
use Template\models\TemplateAssociationModel;
use User\models\UserEntityModel;
use User\models\UserModel;
use Template\models\TemplateModel;
use SrcCore\models\TextFormatModel;
use BroadcastList\models\BroadcastListRoleModel;

class EntityController
{
    public function get(Request $request, Response $response)
    {
        return $response->withJson(['entities' => EntityModel::getAllowedEntitiesByUserId(['userId' => $GLOBALS['login']])]);
    }

    public function getById(Request $request, Response $response, array $args)
    {
        $entity = EntityModel::getById([
            'id' => $args['id'],
            'select' => ['*']
        ]);
        if (empty($entity)) {
            return $response->withStatus(400)->withJson(['errors' => 'Entity not found']);
        }
        $entity = [
            'id'                    => $entity['id'],
            'entity_label'          => $entity['entity_label'],
            'short_label'           => $entity['short_label'],
            'entity_full_name'      => $entity['entity_full_name'],
            'entity_type'           => $entity['entity_type'],
            'entity_id'             => $entity['entity_id'],
            'enabled'               => $entity['enabled'],
            'parent_entity_id'      => $entity['parent_entity_id'],
            'addressNumber'         => $entity['address_number'],
            'addressStreet'         => $entity['address_street'],
            'addressAdditional1'    => $entity['address_additional1'],
            'addressAdditional2'    => $entity['address_additional2'],
            'addressPostcode'       => $entity['address_postcode'],
            'addressTown'           => $entity['address_town'],
            'addressCountry'        => $entity['address_country'],
            'email'                 => $entity['email']
        ];

        return $response->withJson($entity);
    }

    public function getDetailledById(Request $request, Response $response, array $args)
    {
        if (!PrivilegeController::hasPrivilege(['privilegeId' => 'manage_entities', 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }

        $entity = EntityModel::getByEntityId(['entityId' => $args['id']]);
        if (empty($entity)) {
            return $response->withStatus(400)->withJson(['errors' => 'Entity not found']);
        }

        $entity = [
            'id'                    => $entity['id'],
            'entity_label'          => $entity['entity_label'],
            'short_label'           => $entity['short_label'],
            'entity_full_name'      => $entity['entity_full_name'],
            'entity_type'           => $entity['entity_type'],
            'entity_id'             => $entity['entity_id'],
            'enabled'               => $entity['enabled'],
            'parent_entity_id'      => $entity['parent_entity_id'],
            'addressNumber'         => $entity['address_number'],
            'addressStreet'         => $entity['address_street'],
            'addressAdditional1'    => $entity['address_additional1'],
            'addressAdditional2'    => $entity['address_additional2'],
            'addressPostcode'       => $entity['address_postcode'],
            'addressTown'           => $entity['address_town'],
            'addressCountry'        => $entity['address_country'],
            'email'                 => $entity['email'],
            'producerService'       => $entity['producer_service'],
            'business_id'           => $entity['business_id'],
            'external_id'           => $entity['external_id'],
            'fastParapheurSubscriberId' => json_decode($entity['external_id'], true)['fastParapheurSubscriberId'] ?? null,
        ];

        $aEntities = EntityModel::getAllowedEntitiesByUserId(['userId' => $GLOBALS['login']]);
        foreach ($aEntities as $aEntity) {
            if ($aEntity['entity_id'] == $args['id'] && $aEntity['allowed'] == false) {
                return $response->withStatus(403)->withJson(['errors' => 'Entity out of perimeter']);
            }
        }

        $entity['types'] = EntityModel::getTypes();
        $listTemplateTypes = ListTemplateModel::getTypes(['select' => ['difflist_type_roles'], 'where' => ['difflist_type_id = ?'], 'data' => ['entity_id']]);
        $rolesForService = empty($listTemplateTypes[0]['difflist_type_roles']) ? [] : explode(' ', $listTemplateTypes[0]['difflist_type_roles']);

        //List Templates
        $listTemplates = ListTemplateModel::get([
            'select'    => ['id', 'title', 'description', 'type'],
            'where'     => ['entity_id = ?'],
            'data'      => [$entity['id']]
        ]);

        $entity['listTemplate'] = [];
        foreach ($rolesForService as $role) {
            $role == 'copy' ? $entity['listTemplate']['cc'] = [] : $entity['listTemplate'][$role] = [];
        }
        $entity['visaCircuit'] = [];
        $entity['opinionCircuit'] = [];
        foreach ($listTemplates as $listTemplate) {
            $listTemplateItems = ListTemplateItemModel::get(['select' => ['*'], 'where' => ['list_template_id = ?'], 'data' => [$listTemplate['id']]]);

            if ($listTemplate['type'] == 'diffusionList') {
                $entity['listTemplate'] = $listTemplate;
                $entity['listTemplate']['items'] = [];
                foreach ($listTemplateItems as $listTemplateItem) {
                    if ($listTemplateItem['item_type'] == 'user') {
                        $entity['listTemplate']['items'][$listTemplateItem['item_mode']][] = [
                            'id'                    => $listTemplateItem['item_id'],
                            'type'                  => $listTemplateItem['item_type'],
                            'sequence'              => $listTemplateItem['sequence'],
                            'labelToDisplay'        => UserModel::getLabelledUserById(['id' => $listTemplateItem['item_id']]),
                            'descriptionToDisplay'  => UserModel::getPrimaryEntityById(['id' => $listTemplateItem['item_id'], 'select' => ['entities.entity_label']])['entity_label']
                        ];
                    } elseif ($listTemplateItem['item_type'] == 'entity') {
                        $entity['listTemplate']['items'][$listTemplateItem['item_mode']][] = [
                            'id'                    => $listTemplateItem['item_id'],
                            'type'                  => $listTemplateItem['item_type'],
                            'sequence'              => $listTemplateItem['sequence'],
                            'labelToDisplay'        => EntityModel::getById(['id' => $listTemplateItem['item_id'], 'select' => ['entity_label']])['entity_label'],
                            'descriptionToDisplay'  => ''
                        ];
                    }
                }
            } else {
                $entity[$listTemplate['type']] = $listTemplate;
                $entity[$listTemplate['type']]['items'] = [];
                foreach ($listTemplateItems as $listTemplateItem) {
                    $entity[$listTemplate['type']]['items'][] = [
                        'id'                    => $listTemplateItem['item_id'],
                        'type'                  => $listTemplateItem['item_type'],
                        'mode'                  => $listTemplateItem['item_mode'],
                        'sequence'              => $listTemplateItem['sequence'],
                        'idToDisplay'           => UserModel::getLabelledUserById(['id' => $listTemplateItem['item_id']]),
                        'descriptionToDisplay'  => UserModel::getPrimaryEntityById(['id' => $listTemplateItem['item_id'], 'select' => ['entities.entity_label']])['entity_label']
                    ];
                }
            }
        }

        $entity['templates'] = TemplateModel::getByEntity([
            'select'    => ['t.template_id', 't.template_label', 'template_comment', 't.template_target', 't.template_attachment_type'],
            'entities'  => [$args['id']]
        ]);

        $entity['users'] = EntityModel::getUsersById(['id' => $entity['entity_id'], 'select' => ['users.id','users.user_id', 'users.firstname', 'users.lastname', 'users.status']]);
        $children = EntityModel::get(['select' => [1], 'where' => ['parent_entity_id = ?'], 'data' => [$args['id']]]);
        $entity['contact'] = $this->getContactLinkCount($entity['id']);
        $entity['hasChildren'] = count($children) > 0;
        $documents = ResModel::get(['select' => [1], 'where' => ['destination = ?'], 'data' => [$args['id']]]);
        $entity['documents'] = count($documents);
        $instances = ListInstanceModel::get(['select' => [1], 'where' => ['item_id = ?', 'item_type = ?'], 'data' => [$entity['id'], 'entity_id']]);
        $entity['instances'] = count($instances);
        $redirects = GroupBasketRedirectModel::get(['select' => [1], 'where' => ['entity_id = ?'], 'data' => [$args['id']]]);
        $entity['redirects'] = count($redirects);
        $entity['canAdminUsers'] = PrivilegeController::hasPrivilege(['privilegeId' => 'admin_users', 'userId' => $GLOBALS['id']]);
        $entity['canAdminTemplates'] = PrivilegeController::hasPrivilege(['privilegeId' => 'admin_templates', 'userId' => $GLOBALS['id']]);
        $siret = ParameterModel::getById(['id' => 'siret', 'select' => ['param_value_string']]);
        $entity['canSynchronizeSiret'] = !empty($siret['param_value_string']);

        return $response->withJson(['entity' => $entity]);
    }

    public function getContactLinkCount(int $id)
    {
        $linkCount = count(ResourceContactModel::get(['select' => ['distinct res_id'], 'where' => ['item_id = ?', 'type = ?'], 'data' => [$id, 'entity']]));
        return $linkCount;
    }


    public function create(Request $request, Response $response)
    {
        if (!PrivilegeController::hasPrivilege(['privilegeId' => 'manage_entities', 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }

        $body = $request->getParsedBody();

        if (empty($body)) {
            return $response->withStatus(400)->withJson(['errors' => 'Body is empty']);
        } elseif (!Validator::stringType()->notEmpty()->validate($body['entity_id']) || !preg_match("/^[\w-]*$/", $body['entity_id']) || (strlen($body['entity_id']) > 32)) {
            return $response->withStatus(400)->withJson(['errors' => 'Body entity_id is empty, not a string or not valid']);
        } elseif (!Validator::stringType()->notEmpty()->validate($body['entity_label'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body entity_label is empty or not a string']);
        } elseif (!Validator::stringType()->notEmpty()->validate($body['short_label'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body short_label is empty or not a string']);
        } elseif (!Validator::stringType()->notEmpty()->validate($body['entity_type'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body entity_type is empty or not a string']);
        } elseif (!empty($body['email']) && !filter_var($body['email'], FILTER_VALIDATE_EMAIL)) {
            return $response->withStatus(400)->withJson(['errors' => 'Body email is not valid']);
        }

        $existingEntity = EntityModel::getByEntityId(['entityId' => $body['entity_id'], 'select' => [1]]);
        if (!empty($existingEntity)) {
            return $response->withStatus(400)->withJson(['errors' => _ENTITY_ID_ALREADY_EXISTS]);
        }

        $externalId = [];
        if (!empty($body['fastParapheurSubscriberId'])) {
            $externalId['fastParapheurSubscriberId'] = $body['fastParapheurSubscriberId'];
        }
        $id = EntityModel::create([
            'entity_id'             => $body['entity_id'],
            'entity_label'          => $body['entity_label'],
            'short_label'           => $body['short_label'],
            'address_number'        => $body['addressNumber'] ?? null,
            'address_street'        => $body['addressStreet'] ?? null,
            'address_additional1'   => $body['addressAdditional1'] ?? null,
            'address_additional2'   => $body['addressAdditional2'] ?? null,
            'address_postcode'      => $body['addressPostcode'] ?? null,
            'address_town'          => $body['addressTown'] ?? null,
            'address_country'       => $body['addressCountry'] ?? null,
            'email'                 => $body['email'] ?? null,
            'business_id'           => $body['business_id'] ?? null,
            'parent_entity_id'      => $body['parent_entity_id'],
            'entity_type'           => $body['entity_type'],
            'ldap_id'               => $body['ldap_id'] ?? null,
            'entity_full_name'      => $body['entity_full_name'] ?? null,
            'producer_service'      => $body['producerService'],
            'external_id'           => !empty($externalId) ? json_encode($externalId) : '{}',
        ]);
        HistoryController::add([
            'tableName' => 'entities',
            'recordId'  => $body['entity_id'],
            'eventType' => 'ADD',
            'info'      => _ENTITY_CREATION . " : {$body['entity_id']}",
            'moduleId'  => 'entity',
            'eventId'   => 'entityCreation',
        ]);

        if (empty($body['parent_entity_id'])) {
            $primaryEntity = UserModel::getPrimaryEntityById(['id' => $GLOBALS['id'], 'select' => [1]]);
            $pEntity = 'N';
            if (empty($primaryEntity)) {
                $pEntity = 'Y';
            }

            UserEntityModel::addUserEntity(['id' => $GLOBALS['id'], 'entityId' => $body['entity_id'], 'role' => '', 'primaryEntity' => $pEntity]);
            HistoryController::add([
                'tableName' => 'users',
                'recordId'  => $GLOBALS['id'],
                'eventType' => 'UP',
                'info'      => _USER_ENTITY_CREATION . " : {$GLOBALS['login']} {$body['entity_id']}",
                'moduleId'  => 'user',
                'eventId'   => 'userModification',
            ]);
        }

        return $response->withJson(['entities' => EntityModel::getAllowedEntitiesByUserId(['userId' => $GLOBALS['login']]), 'id' => $id]);
    }

    public function update(Request $request, Response $response, array $aArgs)
    {
        if (!PrivilegeController::hasPrivilege(['privilegeId' => 'manage_entities', 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }

        $entity = EntityModel::getByEntityId(['entityId' => $aArgs['id'], 'select' => ['id', 'external_id']]);
        if (empty($entity)) {
            return $response->withStatus(400)->withJson(['errors' => 'Entity not found']);
        }

        $aEntities = EntityModel::getAllowedEntitiesByUserId(['userId' => $GLOBALS['login']]);
        foreach ($aEntities as $aEntity) {
            if ($aEntity['entity_id'] == $aArgs['id'] && $aEntity['allowed'] == false) {
                return $response->withStatus(403)->withJson(['errors' => 'Entity out of perimeter']);
            }
        }

        $body = $request->getParsedBody();

        $check = Validator::stringType()->notEmpty()->validate($body['entity_label']);
        $check = $check && Validator::stringType()->notEmpty()->validate($body['short_label']);
        $check = $check && Validator::stringType()->notEmpty()->validate($body['entity_type']);
        if (!$check) {
            return $response->withStatus(400)->withJson(['errors' => 'Bad Request']);
        }

        $fatherAndSons = EntityModel::getEntityChildren(['entityId' => $aArgs['id']]);
        if (in_array($body['parent_entity_id'], $fatherAndSons)) {
            return $response->withStatus(400)->withJson(['errors' => _CAN_NOT_MOVE_IN_CHILD_ENTITY]);
        }

        if (!empty($body['producerService'])) {
            $body['producer_service'] = $body['producerService'];
        } else {
            $body['producer_service'] = $aArgs['id'];
        }

        $externalId = json_decode($entity['external_id'], true);
        if (!empty($body['fastParapheurSubscriberId'])) {
            $externalId['fastParapheurSubscriberId'] = $body['fastParapheurSubscriberId'];
        } else {
            unset($externalId['fastParapheurSubscriberId']);
        }
        EntityModel::update(['set' => [
                'entity_label'          => $body['entity_label'],
                'short_label'           => $body['short_label'],
                'address_number'        => $body['addressNumber'],
                'address_street'        => $body['addressStreet'],
                'address_additional1'   => $body['addressAdditional1'],
                'address_additional2'   => $body['addressAdditional2'],
                'address_postcode'      => $body['addressPostcode'],
                'address_town'          => $body['addressTown'],
                'address_country'       => $body['addressCountry'],
                'email'                 => $body['email'],
                'business_id'           => $body['business_id'],
                'parent_entity_id'      => $body['parent_entity_id'],
                'entity_type'           => $body['entity_type'],
                'ldap_id'               => $body['ldap_id'] ?? null,
                'entity_full_name'      => $body['entity_full_name'],
                'producer_service'      => $body['producerService'],
                'external_id'           => !empty($externalId) ? json_encode($externalId) : '{}',
            ],
            'where' => ['entity_id = ?'],
            'data'  => [$aArgs['id']]
        ]);
        HistoryController::add([
            'tableName' => 'entities',
            'recordId'  => $aArgs['id'],
            'eventType' => 'UP',
            'info'      => _ENTITY_MODIFICATION . " : {$aArgs['id']}",
            'moduleId'  => 'entity',
            'eventId'   => 'entityModification',
        ]);

        if (empty($body['parent_entity_id'])) {
            $hasEntity = UserEntityModel::get(['select' => [1], 'where' => ['user_id = ?', 'entity_id = ?'], 'data' => [$GLOBALS['id'], $aArgs['id']]]);
            if (empty($hasEntity)) {
                $primaryEntity = UserModel::getPrimaryEntityById(['id' => $GLOBALS['id'], 'select' => [1]]);
                $pEntity = 'N';
                if (empty($primaryEntity)) {
                    $pEntity = 'Y';
                }

                UserEntityModel::addUserEntity(['id' => $GLOBALS['id'], 'entityId' => $aArgs['id'], 'role' => '', 'primaryEntity' => $pEntity]);
                HistoryController::add([
                    'tableName' => 'users',
                    'recordId'  => $GLOBALS['id'],
                    'eventType' => 'UP',
                    'info'      => _USER_ENTITY_CREATION . " : {$GLOBALS['login']} {$aArgs['id']}",
                    'moduleId'  => 'user',
                    'eventId'   => 'userModification',
                ]);
            }
        }

        return $response->withJson(['entities' => EntityModel::getAllowedEntitiesByUserId(['userId' => $GLOBALS['login']])]);
    }

    public function delete(Request $request, Response $response, array $aArgs)
    {
        if (!PrivilegeController::hasPrivilege(['privilegeId' => 'manage_entities', 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }

        $entity = EntityModel::getByEntityId(['entityId' => $aArgs['id'], 'select' => ['id', 'business_id']]);
        if (empty($entity)) {
            return $response->withStatus(400)->withJson(['errors' => 'Entity not found']);
        }

        $aEntities = EntityModel::getAllowedEntitiesByUserId(['userId' => $GLOBALS['login']]);
        foreach ($aEntities as $aEntity) {
            if ($aEntity['entity_id'] == $aArgs['id'] && $aEntity['allowed'] == false) {
                return $response->withStatus(403)->withJson(['errors' => 'Entity out of perimeter']);
            }
        }

        $children  = EntityModel::get(['select' => [1], 'where' => ['parent_entity_id = ?'], 'data' => [$aArgs['id']]]);
        $documents = ResModel::get(['select' => [1], 'where' => ['destination = ?'], 'data' => [$aArgs['id']]]);
        $users     = EntityModel::getUsersById(['select' => [1], 'id' => $aArgs['id']]);
        $templates = TemplateAssociationModel::get(['select' => [1], 'where' => ['value_field = ?'], 'data' => [$aArgs['id']]]);
        $instances = ListInstanceModel::get(['select' => [1], 'where' => ['item_id = ?', 'item_type = ?'], 'data' => [$entity['id'], 'entity_id']]);
        $redirects = GroupBasketRedirectModel::get(['select' => [1], 'where' => ['entity_id = ?'], 'data' => [$aArgs['id']]]);

        $allowedCount = count($children) + count($documents) + count($users) + count($templates) + count($instances) + count($redirects);
        if ($allowedCount > 0) {
            return $response->withStatus(400)->withJson(['errors' => 'Entity is still used']);
        }

        $entities = [];
        if (!empty($entity['business_id'])) {
            $control = AnnuaryController::deleteEntityToOrganization(['entityId' => $aArgs['id']]);
            if (!empty($control['errors'])) {
                return $response->withStatus(400)->withJson(['errors' => $control['errors']]);
            }
            $entities['deleted'] = $control['deleted'];
        }

        $templateLists = ListTemplateModel::get(['select' => ['id'], 'where' => ['entity_id = ?'], 'data' => [$entity['id']]]);
        if (!empty($templateLists)) {
            foreach ($templateLists as $templateList) {
                ListTemplateModel::delete([
                    'where' => ['id = ?'],
                    'data'  => [$templateList['id']]
                ]);
                ListTemplateItemModel::delete(['where' => ['list_template_id = ?'], 'data' => [$templateList['id']]]);
            }
        }

        ContactGroupListModel::delete(['where' => ['correspondent_id = ?', 'correspondent_type = ?'], 'data' => [$entity['id'], 'entity']]);
        GroupModel::update([
            'postSet'   => ['indexation_parameters' => "jsonb_set(indexation_parameters, '{entities}', (indexation_parameters->'entities') - '{$entity['id']}')"],
            'where'     => ["indexation_parameters->'entities' @> ?"],
            'data'      => ['"'.$entity['id'].'"']
        ]);

        EntityModel::delete(['where' => ['entity_id = ?'], 'data' => [$aArgs['id']]]);

        HistoryController::add([
            'tableName' => 'entities',
            'recordId'  => $aArgs['id'],
            'eventType' => 'DEL',
            'info'      => _ENTITY_SUPPRESSION . " : {$aArgs['id']}",
            'moduleId'  => 'entity',
            'eventId'   => 'entitySuppression',
        ]);

        $entities['entities'] = EntityModel::getAllowedEntitiesByUserId(['userId' => $GLOBALS['login']]);
        return $response->withJson($entities);
    }

    public function reassignEntity(Request $request, Response $response, array $aArgs)
    {
        if (!PrivilegeController::hasPrivilege(['privilegeId' => 'manage_entities', 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }

        $dyingEntity = EntityModel::getByEntityId(['entityId' => $aArgs['id'], 'select' => ['id', 'parent_entity_id', 'business_id']]);
        $successorEntity = EntityModel::getByEntityId(['entityId' => $aArgs['newEntityId'], 'select' => ['id']]);
        if (empty($dyingEntity) || empty($successorEntity)) {
            return $response->withStatus(400)->withJson(['errors' => 'Entity does not exist']);
        }
        $entities = EntityModel::getAllowedEntitiesByUserId(['userId' => $GLOBALS['login']]);
        foreach ($entities as $entity) {
            if (($entity['entity_id'] == $aArgs['id'] && $entity['allowed'] == false) || ($entity['entity_id'] == $aArgs['newEntityId'] && $entity['allowed'] == false)) {
                return $response->withStatus(403)->withJson(['errors' => 'Entity out of perimeter']);
            }
        }

        $entities = [];
        if (!empty($dyingEntity['business_id'])) {
            $control = AnnuaryController::deleteEntityToOrganization(['entityId' => $aArgs['id']]);
            if (!empty($control['errors'])) {
                return $response->withStatus(400)->withJson(['errors' => $control['errors']]);
            }
            $entities['deleted'] = $control['deleted'];
        }

        //Documents
        ResModel::update(['set' => ['destination' => $aArgs['newEntityId']], 'where' => ['destination = ?', 'status != ?'], 'data' => [$aArgs['id'], 'DEL']]);

        //Users
        $users = UserEntityModel::get(['select' => ['user_id', 'entity_id', 'primary_entity'], 'where' => ['entity_id = ? OR entity_id = ?'], 'data' => [$aArgs['id'], $aArgs['newEntityId']]]);
        $tmpUsers = [];
        $doubleUsers = [];
        foreach ($users as $user) {
            if (in_array($user['user_id'], $tmpUsers)) {
                $doubleUsers[] = $user['user_id'];
            }
            $tmpUsers[] = $user['user_id'];
        }
        foreach ($users as $user) {
            if (in_array($user['user_id'], $doubleUsers)) {
                if ($user['entity_id'] == $aArgs['id'] && $user['primary_entity'] == 'N') {
                    UserEntityModel::delete(['where' => ['user_id = ?', 'entity_id = ?'], 'data' => [$user['user_id'], $aArgs['id']]]);
                } elseif ($user['entity_id'] == $aArgs['id'] && $user['primary_entity'] == 'Y') {
                    UserEntityModel::delete(['where' => ['user_id = ?', 'entity_id = ?'], 'data' => [$user['user_id'], $aArgs['newEntityId']]]);
                }
            }
        }
        UserEntityModel::update(['set' => ['entity_id' => $aArgs['newEntityId']], 'where' => ['entity_id = ?'], 'data' => [$aArgs['id']]]);

        //Entities
        $entities = EntityModel::get(['select' => ['entity_id', 'parent_entity_id'], 'where' => ['parent_entity_id = ?'], 'data' => [$aArgs['id']]]);
        foreach ($entities as $entity) {
            if ($entity['entity_id'] = $aArgs['newEntityId']) {
                EntityModel::update(['set' => ['parent_entity_id' => $dyingEntity['parent_entity_id']], 'where' => ['entity_id = ?'], 'data' => [$aArgs['newEntityId']]]);
            } else {
                EntityModel::update(['set' => ['parent_entity_id' => $aArgs['newEntityId']], 'where' => ['entity_id = ?'], 'data' => [$entity['entity_id']]]);
            }
        }

        //Baskets
        GroupBasketRedirectModel::update(['set' => ['entity_id' => $aArgs['newEntityId']], 'where' => ['entity_id = ?'], 'data' => [$aArgs['id']]]);
        //ListInstances
        ListInstanceModel::update(['set' => ['item_id' => $successorEntity['id']], 'where' => ['item_id = ?', 'item_type = ?'], 'data' => [$dyingEntity['id'], 'entity_id']]);
        //ListTemplates
        $templateLists = ListTemplateModel::get(['select' => ['id'], 'where' => ['entity_id = ?'], 'data' => [$dyingEntity['id']]]);
        if (!empty($templateLists)) {
            foreach ($templateLists as $templateList) {
                ListTemplateModel::delete([
                    'where' => ['id = ?'],
                    'data'  => [$templateList['id']]
                ]);
                ListTemplateItemModel::delete(['where' => ['list_template_id = ?'], 'data' => [$templateList['id']]]);
            }
        }
        //Templates
        TemplateAssociationModel::update(['set' => ['value_field' => $aArgs['newEntityId']], 'where' => ['value_field = ?'], 'data' => [$aArgs['id']]]);
        //GroupIndexing
        GroupModel::update([
            'postSet'   => ['indexation_parameters' => "jsonb_set(indexation_parameters, '{entities}', (indexation_parameters->'entities') - '{$dyingEntity['id']}')"],
            'where'     => ["indexation_parameters->'entities' @> ?"],
            'data'      => ['"'.$dyingEntity['id'].'"']
        ]);
        //ResourceContact
        $dyingConnection = ResourceContactModel::get(['select' => ['id', 'res_id', 'item_id', 'mode'], 'where' => ['type = ?', 'item_id = ?'], 'data' => ['entity', $dyingEntity['id']]]);
        $successorConnection = [];
        if(!empty($dyingConnection)) {
            $successorConnection = ResourceContactModel::get(['select' => ['id', 'res_id', 'item_id', 'mode'], 'where' => ['type = ?', 'item_id = ?', 'res_id in (?)'], 'data' => ['entity', $successorEntity['id'], array_unique(array_column($dyingConnection, 'res_id'))]]);
        }
        $dyingIds = array_column($dyingConnection, 'id');
        $idsToDelete = [];
        foreach ($dyingConnection as $dyingConn) {
            foreach ($successorConnection as $successorConn) {
                if ($dyingConn['mode'] == $successorConn['mode'] && $dyingConn['res_id'] == $successorConn['res_id']) {
                    $idsToDelete[] = $dyingConn['id'];
                }
            }
        }
        if(!empty($idsToDelete)) {
            ResourceContactModel::delete(['where' => ['id in (?)'], 'data' => [$idsToDelete]]);
        }
        if(!empty($dyingIds)) {
            ResourceContactModel::update(['set' => ['item_id' => $successorEntity['id']], 'where' => ['id in (?)'], 'data' => [$dyingIds]]);
        }
        EntityModel::delete(['where' => ['entity_id = ?'], 'data' => [$aArgs['id']]]);

        HistoryController::add([
            'tableName' => 'entities',
            'recordId'  => $aArgs['id'],
            'eventType' => 'DEL',
            'info'      => _ENTITY_SUPPRESSION . " : {$aArgs['id']}",
            'moduleId'  => 'entity',
            'eventId'   => 'entitySuppression',
        ]);

        $entities['entities'] = EntityModel::getAllowedEntitiesByUserId(['userId' => $GLOBALS['login']]);
        return $response->withJson($entities);
    }

    public function updateStatus(Request $request, Response $response, array $aArgs)
    {
        if (!PrivilegeController::hasPrivilege(['privilegeId' => 'manage_entities', 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }

        $entity = EntityModel::getByEntityId(['entityId' => $aArgs['id'], 'select' => [1]]);
        if (empty($entity)) {
            return $response->withStatus(400)->withJson(['errors' => 'Entity not found']);
        }

        $aEntities = EntityModel::getAllowedEntitiesByUserId(['userId' => $GLOBALS['login']]);
        foreach ($aEntities as $aEntity) {
            if ($aEntity['entity_id'] == $aArgs['id'] && $aEntity['allowed'] == false) {
                return $response->withStatus(403)->withJson(['errors' => 'Entity out of perimeter']);
            }
        }

        $data = $request->getParsedBody();
        $check = Validator::stringType()->notEmpty()->validate($data['method']);
        if (!$check) {
            return $response->withStatus(400)->withJson(['errors' => 'Bad Request']);
        }

        if ($data['method'] == 'disable') {
            $status = 'N';
        } else {
            $status = 'Y';
        }
        $fatherAndSons = EntityModel::getEntityChildren(['entityId' => $aArgs['id']]);

        EntityModel::update(['set' => ['enabled' => $status], 'where' => ['entity_id in (?)'], 'data' => [$fatherAndSons]]);
        HistoryController::add([
            'tableName' => 'entities',
            'recordId'  => $aArgs['id'],
            'eventType' => 'UP',
            'info'      => _ENTITY_MODIFICATION . " : {$aArgs['id']}",
            'moduleId'  => 'entity',
            'eventId'   => 'entityModification',
        ]);

        return $response->withJson(['success' => 'success']);
    }

    public function getUsersById(Request $request, Response $response, array $aArgs)
    {
        $entity = EntityModel::getById(['id' => $aArgs['id'], 'select' => ['entity_id']]);
        if (empty($entity)) {
            return $response->withStatus(400)->withJson(['errors' => 'Entity not found']);
        }

        $users = UserEntityModel::getWithUsers([
            'select'    => ['DISTINCT users.id', 'users.user_id', 'firstname', 'lastname'],
            'where'     => ['users_entities.entity_id = ?', 'status not in (?)'],
            'data'      => [$entity['entity_id'], ['DEL', 'ABS']],
            'orderBy'   => ['lastname', 'firstname']
        ]);

        foreach ($users as $key => $user) {
            $users[$key]['labelToDisplay'] = "{$user['firstname']} {$user['lastname']}";
            $users[$key]['descriptionToDisplay'] = UserModel::getPrimaryEntityById(['id' => $user['id'], 'select' => ['entities.entity_label']])['entity_label'];
        }

        return $response->withJson(['users' => $users]);
    }

    public function getTypes(Request $request, Response $response)
    {
        return $response->withJson(['types' => EntityModel::getTypes()]);
    }

    public function getParentAddress(Request $request, Response $response, array $args)
    {
        if (!PrivilegeController::hasPrivilege(['privilegeId' => 'manage_entities', 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }

        $entity = EntityModel::getById(['id' => $args['id'], 'select' => ['parent_entity_id']]);
        if (empty($entity)) {
            return $response->withStatus(400)->withJson(['errors' => 'Entity does not exist']);
        }

        while (!empty($entity['parent_entity_id'])) {
            $entity = EntityModel::getByEntityId([
                'entityId'  => $entity['parent_entity_id'],
                'select'    => ['parent_entity_id', 'address_number', 'address_street', 'address_additional1', 'address_additional2', 'address_postcode', 'address_town', 'address_country']
            ]);
            if (!empty($entity['address_street'])) {
                return $response->withJson([
                    'addressNumber'         => $entity['address_number'],
                    'addressStreet'         => $entity['address_street'],
                    'addressAdditional1'    => $entity['address_additional1'],
                    'addressAdditional2'    => $entity['address_additional2'],
                    'addressPostcode'       => $entity['address_postcode'],
                    'addressTown'           => $entity['address_town'],
                    'addressCountry'        => $entity['address_country']
                ]);
            }
        }

        return $response->withJson(null);
    }

    public function export(Request $request, Response $response)
    {
        if (!PrivilegeController::hasPrivilege(['privilegeId' => 'manage_entities', 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }

        $allowedFieldsCamelCase = [
            'id', 'entityId', 'entityLabel', 'shortLabel', 'entityFullName', 'enabled', 'addressNumber', 'addressStreet', 'addressAdditional1', 'addressAdditional2',
            'addressPostcode', 'addressTown', 'addressCountry', 'email', 'parentEntityId', 'entityType', 'businessId', 'folderImport', 'producerService',
            'diffusionList', 'visaCircuit', 'opinionCircuit',
            'users',
            'templates'
        ];
        $allowedFields = [];
        foreach ($allowedFieldsCamelCase as $camelCaseField) {
            if (in_array($camelCaseField, ['diffusionList', 'visaCircuit', 'opinionCircuit'])) {
                $allowedFields[$camelCaseField] = $camelCaseField;
            } else {
                $allowedFields[$camelCaseField] = TextFormatModel::camelToSnake($camelCaseField);
            }
        }
        unset($allowedFieldsCamelCase);

        $body = $request->getParsedBody();

        $delimiter = ';';
        if (!empty($body['delimiter'])) {
            if (in_array($body['delimiter'], [',', ';', 'TAB'])) {
                $delimiter = ($body['delimiter'] == 'TAB' ? "\t" : $body['delimiter']);
            }
        }

        $fields = [];
        foreach ($allowedFields as $camel => $snake) {
            $fields[] = ['label' => $snake, 'value' => $camel];
        }
        if (!empty($body['data'])) {
            $fields = [];
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
        if (empty($fields)) {
            return $response->withStatus(400)->withJson(['errors' => 'no allowed fields selected for entities export']);
        }

        ini_set('memory_limit', -1);

        $file = fopen('php://temp', 'w');
        $delimiter = ($delimiter == 'TAB' ? "\t" : $delimiter);

        $entities = EntityModel::getAllowedEntitiesByUserId(['userId' => $GLOBALS['login']]);
        $entities = array_filter($entities, function ($entity) {
            return $entity['allowed'] == true;
        });
        $entitiesIds = array_column($entities, 'serialId');

        $select = array_map(function ($field) use ($allowedFields) {
            return $allowedFields[$field['value']];
        }, $fields);
        $select = array_diff($select, ['diffusionList', 'visaCircuit', 'opinionCircuit', 'users', 'templates']);
        if (!in_array('id', $select)) {
            $select[] = 'id';
        }
        if (!in_array('entity_id', $select)) {
            $select[] = 'entity_id';
        }

        $entities = EntityModel::get([
            'select'  => $select,
            'where'   => ['id in (?)'],
            'data'    => [$entitiesIds],
            'orderBy' => ['parent_entity_id', 'entity_label']
        ]);

        $templateTypes = [];
        foreach ($fields as $key => $field) {
            if (in_array($field['value'], ['diffusionList', 'visaCircuit', 'opinionCircuit'])) {
                $templateTypes[] = $field['value'];
            }
        }
        $includeUsers     = in_array('users', array_column($fields, 'value'));
        $includeTemplates = in_array('templates', array_column($fields, 'value'));

        $roles = BroadcastListRoleModel::getRoles();
        $roles = array_column($roles, 'label', 'id');

        foreach ($entities as $key => $entity) {
            // list templates
            foreach ($templateTypes as $type) {
                $template = ListTemplateModel::get([
                    'select' => ['*'],
                    'where'  => ['entity_id = ?', 'type = ?'],
                    'data'   => [$entity['id'], $type]
                ]);

                $list = [];
                if (!empty($template)) {
                    $template = $template[0];
                    $templateItems = ListTemplateItemModel::get([
                        'select'  => ['*'],
                        'where'   => ['list_template_id = ?'],
                        'data'    => [$template['id']],
                        'orderBy' => ['sequence']
                    ]);
                    foreach ($templateItems as $templateItem) {
                        $item = [];
                        if ($templateItem['item_mode'] == 'cc') {
                            $templateItem['item_mode'] = 'copy';
                        }
                        $item[] = $roles[$templateItem['item_mode']];

                        if ($templateItem['item_type'] == 'user') {
                            $item[] = UserModel::getLabelledUserById(['id' => $templateItem['item_id']]);
                        } elseif ($templateItem['item_type'] == 'entity') {
                            $entityLabel = EntityModel::getById(['select' => ['entity_label'], 'id' => $templateItem['item_id']]);
                            $item[] = $entityLabel['entity_label'];
                        }

                        $list[] = implode(' ', $item);
                    }
                }
                $entities[$key][$type] = implode("\n", $list);
            }

            // Users in entity
            if ($includeUsers) {
                $users = UserEntityModel::getWithUsers([
                    'select'    => ['DISTINCT users.id', 'firstname', 'lastname'],
                    'where'     => ['users_entities.entity_id = ?'],
                    'data'      => [$entity['entity_id']]
                ]);
                $users = array_map(function ($user) {
                    return $user['firstname'] . ' ' . $user['lastname'];
                }, $users);
                $entities[$key]['users'] = implode("\n", $users);
            }

            // Document templates
            if ($includeTemplates) {
                $templates = TemplateModel::getByEntity([
                    'select'    => ['t.template_label', 't.template_target'],
                    'entities'  => [$entity['entity_id']]
                ]);
                $templates = array_map(function ($template) {
                    return $template['template_label'] . ' ' . $template['template_target'];
                }, $templates);
                $entities[$key]['templates'] = implode("\n", $templates);
            }
        }

        $csvHead = array_map(function ($field) { return $field; }, array_column($fields, 'label'));
        fputcsv($file, $csvHead, $delimiter);

        foreach ($entities as $entity) {
            $entityValues = [];
            foreach ($fields as $field) {
                $entityValues[] = $entity[$allowedFields[$field['value']]];
            }
            fputcsv($file, $entityValues, $delimiter);
        }

        rewind($file);

        $response->write(stream_get_contents($file));
        $response = $response->withAddedHeader('Content-Disposition', 'attachment; filename=export_maarch.csv');
        $contentType = 'application/vnd.ms-excel';
        fclose($file);

        return $response->withHeader('Content-Type', $contentType);
    }
}
