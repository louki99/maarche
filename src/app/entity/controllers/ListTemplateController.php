<?php

/**
 * Copyright Maarch since 2008 under licence GPLv3.
 * See LICENCE.txt file at the root folder for more details.
 * This file is part of Maarch software.
 *
 */

/**
 * @brief List Template Controller
 * @author dev@maarch.org
 */

namespace Entity\controllers;

use Entity\models\EntityModel;
use Entity\models\ListTemplateItemModel;
use Entity\models\ListTemplateModel;
use ExternalSignatoryBook\controllers\MaarchParapheurController;
use Group\controllers\PrivilegeController;
use History\controllers\HistoryController;
use Parameter\models\ParameterModel;
use Resource\models\ResModel;
use Respect\Validation\Validator;
use Slim\Psr7\Request;
use SrcCore\http\Response;
use SrcCore\models\DatabaseModel;
use SrcCore\models\ValidatorModel;
use User\models\UserModel;
use BroadcastList\models\BroadcastListRoleModel;

class ListTemplateController
{
    public function get(Request $request, Response $response)
    {
        $listTemplates = ListTemplateModel::get([
            'select' => ['id', 'type', 'entity_id as "entityId"', 'title', 'description'],
            'where'  => ['owner is null']
        ]);

        return $response->withJson(['listTemplates' => $listTemplates]);
    }

    public function getById(Request $request, Response $response, array $args)
    {
        $listTemplate = ListTemplateModel::getById(['id' => $args['id'], 'select' => ['title', 'description', 'type', 'entity_id', 'owner']]);
        if (empty($listTemplate)) {
            return $response->withStatus(400)->withJson(['errors' => 'List template not found']);
        }

        if (!empty($listTemplate['owner']) && $listTemplate['owner'] != $GLOBALS['id']) {
            return $response->withStatus(403)->withJson(['errors' => 'Cannot access private model']);
        }

        $listTemplateItems = ListTemplateItemModel::get(['select' => ['*'], 'where' => ['list_template_id = ?'], 'data' => [$args['id']]]);
        foreach ($listTemplateItems as $key => $value) {
            $listTemplateItems[$key]['isValid'] = true;
            if ($value['item_type'] == 'entity') {
                $listTemplateItems[$key]['idToDisplay'] = EntityModel::getById(['id' => $value['item_id'], 'select' => ['entity_label']])['entity_label'];
                $listTemplateItems[$key]['descriptionToDisplay'] = '';
            } else {
                $user = UserModel::getById(['id' => $value['item_id'], 'select' => ['firstname', 'lastname', 'status']]);
                if (empty($user) || in_array($user['status'], ['SPD', 'DEL'])) {
                    $listTemplateItems[$key]['isValid'] = false;
                }
                $listTemplateItems[$key]['idToDisplay'] = "{$user['firstname']} {$user['lastname']}";
                $listTemplateItems[$key]['descriptionToDisplay'] = UserModel::getPrimaryEntityById(['id' => $value['item_id'], 'select' => ['entity_label']])['entity_label'];
            }
            $listTemplateItems[$key]['hasPrivilege'] = true;
            if ($listTemplate['type'] == 'visaCircuit' && !PrivilegeController::hasPrivilege(['privilegeId' => 'visa_documents', 'userId' => $value['item_id']]) && !PrivilegeController::hasPrivilege(['privilegeId' => 'sign_document', 'userId' => $value['item_id']])) {
                $listTemplateItems[$key]['hasPrivilege'] = false;
            } elseif ($listTemplate['type'] == 'opinionCircuit' && !PrivilegeController::hasPrivilege(['privilegeId' => 'avis_documents', 'userId' => $value['item_id']])) {
                $listTemplateItems[$key]['hasPrivilege'] = false;
            }
        }

        $roles = BroadcastListRoleModel::getRoles();
        $difflistType = $listTemplate['type'] == 'diffusionList' ? 'entity_id' : ($listTemplate['type'] == 'visaCircuit' ? 'VISA_CIRCUIT' : 'AVIS_CIRCUIT');
        $listTemplateTypes = ListTemplateModel::getTypes(['select' => ['difflist_type_roles'], 'where' => ['difflist_type_id = ?'], 'data' => [$difflistType]]);
        $rolesForService = empty($listTemplateTypes[0]['difflist_type_roles']) ? [] : explode(' ', $listTemplateTypes[0]['difflist_type_roles']);
        foreach ($roles as $key => $role) {
            if (!in_array($role['id'], $rolesForService)) {
                unset($roles[$key]);
            } elseif ($role['id'] == 'copy') {
                $roles[$key]['id'] = 'cc';
            }
        }

        $listTemplate = [
            'title'         => $listTemplate['title'],
            'description'   => $listTemplate['description'],
            'type'          => $listTemplate['type'],
            'entityId'      => $listTemplate['entity_id'],
            'items'         => $listTemplateItems,
            'roles'         => array_values($roles)
        ];

        return $response->withJson(['listTemplate' => $listTemplate]);
    }

    public function create(Request $request, Response $response)
    {
        $body = $request->getParsedBody();

        $queryParams = $request->getQueryParams();

        if (!empty($queryParams['admin'])) {
            if (!PrivilegeController::hasPrivilege(['privilegeId' => 'manage_entities', 'userId' => $GLOBALS['id']]) && !empty($body['entityId'])) {
                return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
            }

            if (!PrivilegeController::hasPrivilege(['privilegeId' => 'admin_listmodels', 'userId' => $GLOBALS['id']]) && empty($body['entityId'])) {
                return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
            }

            $owner = null;
        } else {
            if (!empty($body['entityId']) || $body['type'] == 'diffusionList') {
                return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
            }

            if ($body['type'] == 'visaCircuit' && !PrivilegeController::hasPrivilege(['privilegeId' => 'config_visa_workflow', 'userId' => $GLOBALS['id']])) {
                return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
            }
            if ($body['type'] == 'opinionCircuit' && !PrivilegeController::hasPrivilege(['privilegeId' => 'config_avis_workflow', 'userId' => $GLOBALS['id']])) {
                return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
            }
            $owner = $GLOBALS['id'];
        }

        $allowedTypes = ['diffusionList', 'visaCircuit', 'opinionCircuit'];
        if (!Validator::stringType()->notEmpty()->validate($body['type']) || !in_array($body['type'], $allowedTypes)) {
            return $response->withStatus(400)->withJson(['errors' => 'Body type is empty or not an allowed types']);
        }
        if (!Validator::arrayType()->notEmpty()->validate($body['items'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body items is empty or not an array']);
        }
        if (!Validator::stringType()->notEmpty()->validate($body['title'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body title is empty or not a string', 'lang' => 'templateNameMandatory']);
        }

        if (!empty($body['entityId'])) {
            $listTemplate = ListTemplateModel::get(['select' => [1], 'where' => ['entity_id = ?', 'type = ?'], 'data' => [$body['entityId'], $body['type']]]);
            if (!empty($listTemplate)) {
                return $response->withStatus(400)->withJson(['errors' => 'Entity is already linked to this type of template']);
            }
            $entities = EntityModel::getAllowedEntitiesByUserId(['userId' => $GLOBALS['login']]);
            foreach ($entities as $entity) {
                if ($entity['serialId'] == $body['entityId'] && $entity['allowed'] == false) {
                    return $response->withStatus(403)->withJson(['errors' => 'Entity out of perimeter']);
                }
            }
        }

        $control = ListTemplateController::controlItems(['items' => $body['items'], 'type' => $body['type'], 'entityId' => $body['entityId'] ?? null]);
        if (!empty($control['errors'])) {
            return $response->withStatus(400)->withJson(['errors' => $control['errors'], 'lang' => $control['lang']]);
        }

        $listTemplateId = ListTemplateModel::create([
            'title'         => $body['title'] ?? $body['description'],
            'description'   => $body['description'] ?? null,
            'type'          => $body['type'],
            'entity_id'     => $body['entityId'] ?? null,
            'owner'         => $owner
        ]);

        foreach ($body['items'] as $key => $item) {
            ListTemplateItemModel::create([
                'list_template_id'  => $listTemplateId,
                'item_id'           => $item['id'],
                'item_type'         => $item['type'],
                'item_mode'         => $item['mode'],
                'sequence'          => $key,
            ]);
        }

        $description = $body['description'] ?? '';

        HistoryController::add([
            'tableName' => 'list_templates',
            'recordId'  => $listTemplateId,
            'eventType' => 'ADD',
            'info'      => _LIST_TEMPLATE_CREATION . " : {$body['title']} {$description}",
            'moduleId'  => 'listTemplate',
            'eventId'   => 'listTemplateCreation',
        ]);

        return $response->withJson(['id' => $listTemplateId]);
    }

    public function update(Request $request, Response $response, array $args)
    {
        $body = $request->getParsedBody();

        $check = Validator::arrayType()->notEmpty()->validate($body['items']);
        $check = $check && Validator::stringType()->notEmpty()->validate($body['title']);
        if (!$check) {
            return $response->withStatus(400)->withJson(['errors' => 'Bad Request']);
        }

        $listTemplate = ListTemplateModel::getById(['id' => $args['id'], 'select' => ['entity_id', 'type']]);
        if (!PrivilegeController::hasPrivilege(['privilegeId' => 'manage_entities', 'userId' => $GLOBALS['id']]) && !empty($listTemplate['entity_id'])) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }

        if (!PrivilegeController::hasPrivilege(['privilegeId' => 'admin_listmodels', 'userId' => $GLOBALS['id']]) && empty($listTemplate['entity_id'])) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }
        if (empty($listTemplate)) {
            return $response->withStatus(400)->withJson(['errors' => 'List template not found']);
        }

        if (!empty($listTemplate['entity_id'])) {
            $entities = EntityModel::getAllowedEntitiesByUserId(['userId' => $GLOBALS['login']]);
            foreach ($entities as $entity) {
                if ($entity['serialId'] == $listTemplate['entity_id'] && $entity['allowed'] == false) {
                    return $response->withStatus(403)->withJson(['errors' => 'Entity out of perimeter']);
                }
            }
        }

        $control = ListTemplateController::controlItems(['items' => $body['items'], 'type' => $listTemplate['type'], 'entityId' => $listTemplate['entity_id']]);
        if (!empty($control['errors'])) {
            return $response->withStatus(400)->withJson(['errors' => $control['errors'], 'lang' => $control['lang']]);
        }

        ListTemplateModel::update([
            'set'   => ['title' => $body['title'], 'description' => $body['description'] ?? null],
            'where' => ['id = ?'],
            'data'  => [$args['id']]
        ]);

        ListTemplateItemModel::delete(['where' => ['list_template_id = ?'], 'data' => [$args['id']]]);
        foreach ($body['items'] as $key => $item) {
            ListTemplateItemModel::create([
                'list_template_id'  => $args['id'],
                'item_id'           => $item['id'],
                'item_type'         => $item['type'],
                'item_mode'         => $item['mode'],
                'sequence'          => $key,
            ]);
        }

        HistoryController::add([
            'tableName' => 'list_templates',
            'recordId'  => $args['id'],
            'eventType' => 'UP',
            'info'      => _LIST_TEMPLATE_MODIFICATION . " : {$body['title']} {$body['description']}",
            'moduleId'  => 'listTemplate',
            'eventId'   => 'listTemplateModification',
        ]);

        return $response->withStatus(204);
    }

    public function delete(Request $request, Response $response, array $args)
    {
        $listTemplate = ListTemplateModel::getById(['id' => $args['id'], 'select' => ['entity_id', 'type', 'title', 'owner']]);

        if (empty($listTemplate)) {
            return $response->withStatus(400)->withJson(['errors' => 'List template not found']);
        }

        $listTemplate['entityId'] = $listTemplate['entity_id'];
        if (empty($listTemplate['owner'])) {
            if (!PrivilegeController::hasPrivilege(['privilegeId' => 'manage_entities', 'userId' => $GLOBALS['id']]) && !empty($listTemplate['entityId'])) {
                return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
            }

            if (!PrivilegeController::hasPrivilege(['privilegeId' => 'admin_listmodels', 'userId' => $GLOBALS['id']]) && empty($listTemplate['entityId'])) {
                return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
            }
        } else {
            if ($listTemplate['owner'] != $GLOBALS['id']) {
                return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
            }
        }

        if (!empty($listTemplate['entityId'])) {
            $entities = EntityModel::getAllowedEntitiesByUserId(['userId' => $GLOBALS['login']]);
            foreach ($entities as $entity) {
                if ($entity['serialId'] == $listTemplate['entityId'] && $entity['allowed'] == false) {
                    return $response->withStatus(403)->withJson(['errors' => 'Entity out of perimeter']);
                }
            }
        }

        ListTemplateModel::delete([
            'where' => ['id = ?'],
            'data'  => [$args['id']]
        ]);
        ListTemplateItemModel::delete(['where' => ['list_template_id = ?'], 'data' => [$args['id']]]);

        HistoryController::add([
            'tableName' => 'list_templates',
            'recordId'  => $args['id'],
            'eventType' => 'DEL',
            'info'      => _LIST_TEMPLATE_SUPPRESSION . " : {$listTemplate['title']}",
            'moduleId'  => 'listTemplate',
            'eventId'   => 'listTemplateSuppression',
        ]);

        return $response->withStatus(204);
    }

    public function getByEntityId(Request $request, Response $response, array $args)
    {
        $entity = EntityModel::getById(['select' => ['entity_id'], 'id' => $args['entityId']]);
        if (empty($entity)) {
            return $response->withStatus(400)->withJson(['errors' => 'Entity does not exist']);
        }

        $queryParams = $request->getQueryParams();

        $where = ['entity_id = ?'];
        $data = [$args['entityId']];
        if (!empty($queryParams['type'])) {
            if (in_array($queryParams['type'], ['visaCircuit', 'opinionCircuit'])) {
                $where[] = 'type = ?';
                $data[] = $queryParams['type'];
            } else {
                $where[] = 'type = ?';
                $data[] = 'diffusionList';
            }
        }

        $listTemplates = ListTemplateModel::get(['select' => ['*'], 'where' => $where, 'data' => $data]);
        foreach ($listTemplates as $key => $listTemplate) {
            $listTemplateItems = ListTemplateItemModel::get(['select' => ['*'], 'where' => ['list_template_id = ?'], 'data' => [$listTemplate['id']]]);
            foreach ($listTemplateItems as $itemKey => $value) {
                if ($value['item_type'] == 'entity') {
                    $listTemplateItems[$itemKey]['labelToDisplay'] = EntityModel::getById(['id' => $value['item_id'], 'select' => ['entity_label']])['entity_label'];
                    $listTemplateItems[$itemKey]['descriptionToDisplay'] = '';
                } else {
                    $user = UserModel::getById(['id' => $value['item_id'], 'select' => ['firstname', 'lastname', 'external_id', 'status']]);
                    $listTemplateItems[$itemKey]['isValid'] = true;
                    if (empty($user) || in_array($user['status'], ['SPD', 'DEL'])) {
                        if ($listTemplate['type'] == 'diffusionList') {
                            unset($listTemplateItems[$itemKey]);
                            continue;
                        }
                        $listTemplateItems[$itemKey]['isValid'] = false;
                    }

                    $listTemplateItems[$itemKey]['labelToDisplay'] = "{$user['firstname']} {$user['lastname']}";
                    if (empty($queryParams['maarchParapheur']) && empty($queryParams['fastParapheur'])) {
                        $listTemplateItems[$itemKey]['descriptionToDisplay'] = UserModel::getPrimaryEntityById(['id' => $value['item_id'], 'select' => ['entity_label']])['entity_label'];
                    } else {
                        $listTemplateItems[$itemKey]['descriptionToDisplay'] = '';
                    }

                    $listTemplateItems[$itemKey]['hasPrivilege'] = true;
                    if (empty($queryParams['maarchParapheur']) && empty($queryParams['fastParapheur'])) {
                        if ($listTemplate['type'] == 'visaCircuit' && !PrivilegeController::hasPrivilege(['privilegeId' => 'visa_documents', 'userId' => $value['item_id']]) && !PrivilegeController::hasPrivilege(['privilegeId' => 'sign_document', 'userId' => $value['item_id']])) {
                            $listTemplateItems[$itemKey]['hasPrivilege'] = false;
                        } elseif ($listTemplate['type'] == 'opinionCircuit' && !PrivilegeController::hasPrivilege(['privilegeId' => 'avis_documents', 'userId' => $value['item_id']])) {
                            $listTemplateItems[$itemKey]['hasPrivilege'] = false;
                        }
                    }

                    $externalId = json_decode($user['external_id'], true);
                    if (!empty($queryParams['maarchParapheur']) && !empty($externalId['maarchParapheur'])) {
                        $userExists = MaarchParapheurController::userExists(['userId' => $externalId['maarchParapheur']]);
                        if (!empty($userExists)) {
                            // Remove external value in signatureModes
                            $array = $userExists['signatureModes'];
                            $externalRoleIndex = array_search('external', $array);
                            if ($externalRoleIndex !== false) {
                                unset($array[$externalRoleIndex]);
                            }
                            $userExists['signatureModes'] = array_values($array);
                            $listTemplateItems[$itemKey]['externalId']['maarchParapheur'] = $externalId['maarchParapheur'];
                            $listTemplateItems[$itemKey]['descriptionToDisplay']          = $userExists['email'];
                            $listTemplateItems[$itemKey]['labelToDisplay']                = $userExists['firstname'] . ' ' . $userExists['lastname'];
                            $listTemplateItems[$itemKey]['availableRoles']                = array_merge(['visa'], $userExists['signatureModes']);
                            $listTemplateItems[$itemKey]['role']                          = end($userExists['signatureModes']);
                        }
                    } elseif (!empty($queryParams['fastParapheur']) && !empty($externalId['fastParapheur'])) {
                        $listTemplateItems[$itemKey] = [
                            'externalId'           => ['fastParapheur' => $externalId['fastParapheur']],
                            'descriptionToDisplay' => $externalId['fastParapheur'],
                            'labelToDisplay'       => trim($user['firstname'] . ' ' . $user['lastname']),
                            'availableRoles'       => ['visa', 'sign'],
                            'role'                 => $listTemplateItems[$itemKey]['item_mode']
                        ];
                    }
                }
            }
            $listTemplates[$key]['items'] = array_values($listTemplateItems);
        }

        return $response->withJson(['listTemplates' => $listTemplates]);
    }

    public function updateByUserWithEntityDest(Request $request, Response $response, array $args)
    {
        if (!PrivilegeController::hasPrivilege(['privilegeId' => 'admin_users', 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }
        
        $data = $request->getParsedBody();

        DatabaseModel::beginTransaction();

        $allEntityIds = array_column($data['redirectListModels'], 'entity_id');
        $templates = ListTemplateModel::get(['select' => ['id'], 'where' => ['type = ?', 'entity_id in (?)'], 'data' => ['diffusionList', $allEntityIds]]);
        $templates = array_column($templates, 'id');
        foreach ($data['redirectListModels'] as $listModel) {
            $redirectUser = UserModel::getByLogin(['login' => $listModel['redirectUserId'], 'select' => ['status', 'id']]);
            if (empty($redirectUser) || $redirectUser['status'] != "OK") {
                DatabaseModel::rollbackTransaction();
                return $response->withStatus(400)->withJson(['errors' => 'User not found or not active']);
            }

            $entities = UserModel::getEntitiesById(['id' => $redirectUser['id'], 'select' => ['entities.id']]);
            $entities = array_column($entities, 'id');
            if (!empty(array_diff($allEntityIds, $entities))) {
                return $response->withStatus(400)->withJson(['errors' => 'Dest user is not present in this entity']);
            }

            ListTemplateItemModel::update([
                'set'   => ['item_id' => $redirectUser['id']],
                'where' => ['item_id = ?', 'item_type = ?', 'item_mode = ?', 'list_template_id in (?)'],
                'data'  => [$args['itemId'], 'user', 'dest', $templates]
            ]);
        }

        ListTemplateModel::deleteNoItemsOnes();
        DatabaseModel::commitTransaction();

        return $response->withStatus(204);
    }

    public function getTypeRoles(Request $request, Response $response, array $aArgs)
    {
        $unneededRoles = [];
        if ($aArgs['typeId'] == 'entity_id') {
            $unneededRoles = ['visa', 'sign'];
        }
        $roles = BroadcastListRoleModel::getRoles();
        $listTemplateTypes = ListTemplateModel::getTypes(['select' => ['difflist_type_roles'], 'where' => ['difflist_type_id = ?'], 'data' => [$aArgs['typeId']]]);
        $rolesForType = empty($listTemplateTypes[0]['difflist_type_roles']) ? [] : explode(' ', $listTemplateTypes[0]['difflist_type_roles']);
        foreach ($roles as $key => $role) {
            if ($role['id'] == 'dest') {
                $roles[$key]['label'] = _ASSIGNEE;
            }
            if (in_array($role['id'], $unneededRoles)) {
                unset($roles[$key]);
                continue;
            }
            if (in_array($role['id'], $rolesForType)) {
                $roles[$key]['available'] = true;
            } else {
                $roles[$key]['available'] = false;
            }
            if ($role['id'] == 'copy') {
                $roles[$key]['id'] = 'cc';
            }

            $roles[$key]['usedIn'] = [];
            $type = $aArgs['typeId'] == 'entity_id' ? 'diffusionList' : ($aArgs['typeId'] == 'VISA_CIRCUIT' ? 'visaCircuit' : 'opinionCircuit');
            $listTemplates = ListTemplateModel::getWithItems(['select' => ['DISTINCT entity_id'], 'where' => ['type = ?', 'item_mode = ?', 'entity_id is not null'], 'data' => [$type, $roles[$key]['id']]]);
            foreach ($listTemplates as $listTemplate) {
                $entity = EntityModel::getById(['select' => ['short_label'], 'id' => $listTemplate['entity_id']]);
                $roles[$key]['usedIn'][] = $entity['short_label'];
            }
        }

        return $response->withJson(['roles' => array_values($roles)]);
    }

    public function updateTypeRoles(Request $request, Response $response, array $aArgs)
    {
        if (!PrivilegeController::hasPrivilege(['privilegeId' => 'manage_entities', 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }

        $data = $request->getParsedBody();

        $check = Validator::arrayType()->notEmpty()->validate($data['roles'] ?? null);
        if (!$check) {
            return $response->withStatus(400)->withJson(['errors' => 'Bad Request']);
        }

        $roles = '';
        foreach ($data['roles'] as $role) {
            if ($role['available'] === true) {
                if ($role['id'] == 'cc') {
                    $role['id'] = 'copy';
                }

                if (!empty($roles)) {
                    $roles .= ' ';
                }
                $roles .= $role['id'];
            }
        }

        ListTemplateModel::updateTypes([
            'set'   => ['difflist_type_roles' => $roles],
            'where' => ['difflist_type_id = ?'],
            'data'  => [$aArgs['typeId']]
        ]);

        $listTemplates = ListTemplateModel::get([
            'select'    => ['id'],
            'where'     => ['type = ?'],
            'data'      => ['diffusionList']
        ]);
        $listTemplates = array_column($listTemplates, 'id');

        if (empty($roles)) {
            if (!empty($listTemplates)) {
                ListTemplateModel::delete([
                    'where' => ['type = ?'],
                    'data'  => ['diffusionList']
                ]);
                ListTemplateItemModel::delete([
                    'where' => ['list_template_id in (?)'],
                    'data'  => [$listTemplates]
                ]);
            }
        } else {
            ListTemplateItemModel::delete([
                'where' => ['list_template_id in (?)', 'item_mode not in (?)'],
                'data'  => [$listTemplates, explode(' ', str_replace('copy', 'cc', $roles))]
            ]);
            ListTemplateModel::deleteNoItemsOnes();
        }

        return $response->withJson(['success' => 'success']);
    }

    public function getRoles(Request $request, Response $response)
    {
        $data = $request->getQueryParams();

        $canUpdateDiffusionRecipient = false;
        $canUpdateDiffusionRoles = false;
        $triggerContext = false;

        if (!empty($data['context'])) {
            if ($data['context'] == 'indexation') {
                $serviceRecipient = 'update_diffusion_indexing';
                $serviceRoles = 'update_diffusion_except_recipient_indexing';
                $triggerContext = true;
            } elseif ($data['context'] == 'process') {
                $serviceRecipient = 'update_diffusion_process';
                $serviceRoles = 'update_diffusion_except_recipient_process';
                $triggerContext = true;
            } elseif ($data['context'] == 'details') {
                $serviceRecipient = 'update_diffusion_details';
                $serviceRoles = 'update_diffusion_except_recipient_details';
                $triggerContext = true;
            }
    
            if ($data['context'] == 'redirect') {
                $triggerContext = true;
                $canUpdateDiffusionRecipient = true;
            } elseif ($triggerContext) {
                if (PrivilegeController::hasPrivilege(['privilegeId' => $serviceRecipient, 'userId' => $GLOBALS['id']])) {
                    $canUpdateDiffusionRecipient = true;
                }
                if (!$canUpdateDiffusionRecipient && PrivilegeController::hasPrivilege(['privilegeId' => $serviceRoles, 'userId' => $GLOBALS['id']])) {
                    $canUpdateDiffusionRoles = true;
                }
            }
        }

        $listTemplateTypes = ListTemplateModel::getTypes(['select' => ['difflist_type_roles'], 'where' => ['difflist_type_id = ?'], 'data' => ['entity_id']]);
        $availableRoles = empty($listTemplateTypes[0]['difflist_type_roles']) ? [] : explode(' ', $listTemplateTypes[0]['difflist_type_roles']);
        $roles = BroadcastListRoleModel::getRoles();
        foreach ($roles as $key => $role) {
            if (!in_array($role['id'], $availableRoles)) {
                unset($roles[$key]);
                continue;
            }
            if ($role['id'] == 'dest') {
                $roles[$key]['label'] = _ASSIGNEE;
                if ($triggerContext) {
                    $roles[$key]['canUpdate'] = $canUpdateDiffusionRecipient;
                }
            } else {
                if ($triggerContext) {
                    $roles[$key]['canUpdate'] = $canUpdateDiffusionRecipient || $canUpdateDiffusionRoles;
                }
            }
            if ($role['id'] == 'copy') {
                $roles[$key]['id'] = 'cc';
            }
        }

        $parameters = [];

        $parameter = ParameterModel::getById(['id' => 'keepDiffusionRoleInOutgoingIndexation', 'select' => ['param_value_int']]);
        $parameters['keepDiffusionRoleInOutgoingIndexation'] = !empty($parameter['param_value_int']);

        return $response->withJson(['roles' => array_values($roles), 'parameters' => $parameters]);
    }

    public function getAvailableCircuits(Request $request, Response $response)
    {
        $queryParams = $request->getQueryParams();

        if (!Validator::stringType()->notEmpty()->validate($queryParams['circuit'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Query params circuit is empty']);
        }

        $circuit = $queryParams['circuit'] == 'opinion' ? 'opinionCircuit' : 'visaCircuit';

        $circuits = ListTemplateModel::get([
            'select'  => ['id', 'type', 'entity_id as "entityId"', 'title', 'description', "case when owner is null then false else true end as private"],
            'where'   => ['type = ?', 'entity_id is null', '(owner is null or owner = ?)'],
            'data'    => [$circuit, $GLOBALS['id']],
            'orderBy' => ['title']
        ]);

        return $response->withJson(['circuits' => $circuits]);
    }

    public function getDefaultCircuitByResId(Request $request, Response $response, array $args)
    {
        $queryParams = $request->getQueryParams();

        if (!Validator::stringType()->notEmpty()->validate($queryParams['circuit'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Query params circuit is empty']);
        }

        $circuit = $queryParams['circuit'] == 'opinion' ? 'opinionCircuit' : 'visaCircuit';
        $resource = ResModel::getById(['resId' => $args['resId'], 'select' => ['destination']]);

        if (empty($resource['destination'])) {
            return $response->withJson(['circuit' => null]);
        }

        $entity = EntityModel::getByEntityId(['entityId' => $resource['destination'], 'select' => ['id']]);

        $circuit = ListTemplateModel::get([
            'select'  => ['id', 'type', 'entity_id as "entityId"', 'title', 'description'],
            'where'   => ['type = ?', 'entity_id = ?'],
            'data'    => [$circuit, $entity['id']]
        ]);

        if (empty($circuit[0])) {
            return $response->withJson(['circuit' => null]);
        }
        $circuit = $circuit[0];

        $listTemplateItems = ListTemplateItemModel::get(['select' => ['*'], 'where' => ['list_template_id = ?'], 'data' => [$circuit['id']]]);
        foreach ($listTemplateItems as $key => $value) {
            $user = UserModel::getById(['id' => $value['item_id'], 'select' => ['firstname', 'lastname', 'status']]);
            $listTemplateItems[$key]['isValid'] = true;
            if (empty($user) || in_array($user['status'], ['SPD', 'DEL'])) {
                $listTemplateItems[$key]['isValid'] = false;
            }

            $listTemplateItems[$key]['labelToDisplay'] = "{$user['firstname']} {$user['lastname']}";
            $listTemplateItems[$key]['descriptionToDisplay'] = UserModel::getPrimaryEntityById(['id' => $value['item_id'], 'select' => ['entity_label']])['entity_label'];

            $listTemplateItems[$key]['hasPrivilege'] = true;
            if ($circuit['type'] == 'visaCircuit' && !PrivilegeController::hasPrivilege(['privilegeId' => 'visa_documents', 'userId' => $value['item_id']]) && !PrivilegeController::hasPrivilege(['privilegeId' => 'sign_document', 'userId' => $value['item_id']])) {
                $listTemplateItems[$key]['hasPrivilege'] = false;
            } elseif ($circuit['type'] == 'opinionCircuit' && !PrivilegeController::hasPrivilege(['privilegeId' => 'avis_documents', 'userId' => $value['item_id']])) {
                $listTemplateItems[$key]['hasPrivilege'] = false;
            }
            if ($circuit['type'] == 'visaCircuit') {
                $listTemplateItems[$key]['currentRole'] = $value['item_mode'];
            }
        }
        $circuit['items'] = array_values($listTemplateItems);

        return $response->withJson(['circuit' => $circuit]);
    }

    private static function controlItems(array $args)
    {
        ValidatorModel::notEmpty($args, ['items', 'type']);
        ValidatorModel::arrayType($args, ['items']);
        ValidatorModel::stringType($args, ['type']);
        ValidatorModel::intVal($args, ['entityId']);

        $destFound = false;
        foreach ($args['items'] as $item) {
            if ($destFound && $item['mode'] == 'dest') {
                return ['errors' => 'More than one dest not allowed'];
            }
            if (empty($item['id'])) {
                return ['errors' => 'id is empty'];
            } elseif (empty($item['type'])) {
                return ['errors' => 'type is empty'];
            } elseif (empty($item['mode'])) {
                return ['errors' => 'mode is empty'];
            }
            if ($item['mode'] == 'dest') {
                $destFound = true;
                $entities = UserModel::getEntitiesById(['id' => $item['id'], 'select' => ['entities.id']]);
                $entities = array_column($entities, 'id');
                if (!in_array($args['entityId'], $entities)) {
                    return ['errors' => 'Dest user is not present in this entity'];
                }
            }
            if ($item['type'] == 'user') {
                $user = UserModel::getById(['id' => $item['id'], 'select' => ['status']]);
                if (empty($user) || $user['status'] == 'SPD' || $user['status'] == 'DEL') {
                    return ['errors' => 'Item user is not valid'];
                }
            }
            if ($args['type'] == 'visaCircuit' && !PrivilegeController::hasPrivilege(['privilegeId' => 'visa_documents', 'userId' => $item['id']]) && !PrivilegeController::hasPrivilege(['privilegeId' => 'sign_document', 'userId' => $item['id']])) {
                return ['errors' => 'item has not enough privileges'];
            } elseif ($args['type'] == 'opinionCircuit' && !PrivilegeController::hasPrivilege(['privilegeId' => 'avis_documents', 'userId' => $item['id']])) {
                return ['errors' => 'item has not enough privileges'];
            }
        }

        return true;
    }
}
