<?php

/**
 * Copyright Maarch since 2008 under licence GPLv3.
 * See LICENCE.txt file at the root folder for more details.
 * This file is part of Maarch software.
 *
 */

/**
 * @brief List Instance Controller
 * @author dev@maarch.org
 */

namespace Entity\controllers;

use Entity\models\EntityModel;
use Entity\models\ListInstanceHistoryDetailModel;
use Entity\models\ListInstanceHistoryModel;
use Entity\models\ListInstanceModel;
use Group\controllers\PrivilegeController;
use History\controllers\HistoryController;
use Parameter\models\ParameterModel;
use Resource\controllers\ResController;
use Resource\models\ResModel;
use Respect\Validation\Validator;
use Slim\Psr7\Request;
use SrcCore\http\Response;
use SrcCore\models\DatabaseModel;
use SrcCore\models\ValidatorModel;
use User\models\UserEntityModel;
use User\models\UserModel;
use SignatureBook\controllers\SignatureBookController;

class ListInstanceController
{
    const MAPPING_TYPES = [
            'visaCircuit'       => 'VISA_CIRCUIT',
            'opinionCircuit'    => 'AVIS_CIRCUIT'
    ];

    public function getByResId(Request $request, Response $response, array $args)
    {
        if (!Validator::intVal()->validate($args['resId']) || !ResController::hasRightByResId(['resId' => [$args['resId']], 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Document out of perimeter']);
        }

        $listInstances = ListInstanceModel::get(['select' => ['*'], 'where' => ['res_id = ?', 'difflist_type = ?'], 'data' => [$args['resId'], 'entity_id']]);
        foreach ($listInstances as $key => $value) {
            if ($value['item_type'] == 'entity_id') {
                $entity = EntityModel::getById(['id' => $value['item_id'], 'select' => ['entity_label', 'entity_id']]);
                $listInstances[$key]['item_id'] = $entity['entity_id'];
                $listInstances[$key]['itemSerialId'] = $value['item_id'];
                $listInstances[$key]['labelToDisplay'] = $entity['entity_label'];
                $listInstances[$key]['descriptionToDisplay'] = '';
            } else {
                $user = UserModel::getById(['id' => $value['item_id'], 'select' => ['user_id']]);
                $listInstances[$key]['item_id'] = $user['user_id'];
                $listInstances[$key]['itemSerialId'] = $value['item_id'];
                $listInstances[$key]['labelToDisplay'] = UserModel::getLabelledUserById(['id' => $value['item_id']]);
                $listInstances[$key]['descriptionToDisplay'] = UserModel::getPrimaryEntityById(['id' => $value['item_id'], 'select' => ['entities.entity_label']])['entity_label'];
            }
        }
        $hasHistory = ListInstanceHistoryDetailModel::get([
            'select'    => [1],
            'where'     => ['difflist_type = ?', 'res_id = ?'],
            'data'      => ['entity_id', $args['resId']]
        ]);

        return $response->withJson(['listInstance' => $listInstances, 'hasHistory' => !empty($hasHistory)]);
    }

    public function getVisaCircuitByResId(Request $request, Response $response, array $aArgs)
    {
        if (!Validator::intVal()->validate($aArgs['resId']) || !ResController::hasRightByResId(['resId' => [$aArgs['resId']], 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Document out of perimeter']);
        }

        $listInstances = ListInstanceModel::getVisaCircuitByResId(['select' => ['listinstance_id', 'sequence', 'item_id', 'item_type', 'firstname as item_firstname', 'lastname as item_lastname', 'viewed', 'process_date', 'process_comment', 'signatory', 'requested_signature', 'delegate'], 'id' => $aArgs['resId']]);
        foreach ($listInstances as $key => $value) {
            $primaryEntity = UserModel::getPrimaryEntityById(['select' => ['entity_label'], 'id' => $value['item_id']]);
            $listInstances[$key]['item_entity'] = $primaryEntity['entity_label'] ?? '';
            $user = UserModel::getById(['id' => $value['item_id'], 'select' => ['status']]);
            $listInstances[$key]['isValid'] = !empty($user) && !in_array($user['status'], ['SPD', 'DEL']);

            $listInstances[$key]['item_type'] = 'user';
            $itemLabel = $listInstances[$key]['item_firstname'].' '.$listInstances[$key]['item_lastname'];

            $listInstances[$key]['labelToDisplay'] = $itemLabel;
            $listInstances[$key]['delegatedBy'] = null;
            if (!empty($listInstances[$key]['delegate'])) {
                $listInstances[$key]['labelToDisplay'] = UserModel::getLabelledUserById(['id' => $listInstances[$key]['delegate']]);
                $listInstances[$key]['delegatedBy'] = $itemLabel;
            }

            $listInstances[$key]['hasPrivilege'] = true;
            if (empty($value['process_date']) && !PrivilegeController::hasPrivilege(['privilegeId' => 'visa_documents', 'userId' => $value['item_id']]) && !PrivilegeController::hasPrivilege(['privilegeId' => 'sign_document', 'userId' => $value['item_id']])) {
                $listInstances[$key]['hasPrivilege'] = false;
            }
        }
        $hasHistory = ListInstanceHistoryDetailModel::get([
            'select'    => [1],
            'where'     => ['difflist_type = ?', 'res_id = ?'],
            'data'      => ['VISA_CIRCUIT', $aArgs['resId']],
            'groupBy'   => ['listinstance_history_id']
        ]);

        return $response->withJson(['circuit' => $listInstances, 'hasHistory' => count($hasHistory) > 1]);
    }

    public function getOpinionCircuitByResId(Request $request, Response $response, array $aArgs)
    {
        if (!Validator::intVal()->validate($aArgs['resId']) || !ResController::hasRightByResId(['resId' => [$aArgs['resId']], 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Document out of perimeter']);
        }

        $listInstances = ListInstanceModel::getAvisCircuitByResId(['select' => ['listinstance_id', 'sequence', 'item_id', 'item_type', 'firstname as item_firstname', 'lastname as item_lastname', 'viewed', 'process_date', 'process_comment', 'delegate'], 'id' => $aArgs['resId']]);
        foreach ($listInstances as $key => $value) {
            $primaryEntity = UserModel::getPrimaryEntityById(['select' => ['entity_label'], 'id' => $value['item_id']]);
            $listInstances[$key]['item_entity'] = $primaryEntity['entity_label'] ?? '';
            $user = UserModel::getById(['id' => $value['item_id'], 'select' => ['status']]);
            $listInstances[$key]['isValid'] = !empty($user) && !in_array($user['status'], ['SPD', 'DEL']);

            $listInstances[$key]['item_type'] = 'user';
            $itemLabel = $listInstances[$key]['item_firstname'].' '.$listInstances[$key]['item_lastname'];

            $listInstances[$key]['labelToDisplay'] = $itemLabel;
            $listInstances[$key]['delegatedBy'] = null;
            if (!empty($listInstances[$key]['delegate'])) {
                $listInstances[$key]['labelToDisplay'] = UserModel::getLabelledUserById(['id' => $listInstances[$key]['delegate']]);
                $listInstances[$key]['delegatedBy'] = $itemLabel;
            }

            $listInstances[$key]['hasPrivilege'] = true;
            if (empty($value['process_date']) && !PrivilegeController::hasPrivilege(['privilegeId' => 'avis_documents', 'userId' => $value['item_id']])) {
                $listInstances[$key]['hasPrivilege'] = false;
            }
        }

        return $response->withJson(['circuit' => $listInstances]);
    }

    public function getParallelOpinionByResId(Request $request, Response $response, array $aArgs)
    {
        if (!Validator::intVal()->validate($aArgs['resId']) || !ResController::hasRightByResId(['resId' => [$aArgs['resId']], 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Document out of perimeter']);
        }

        $listInstances = ListInstanceModel::getParallelOpinionByResId(['select' => ['listinstance_id', 'sequence', 'item_mode', 'item_id', 'item_type', 'firstname as item_firstname', 'lastname as item_lastname', 'entity_label as item_entity', 'viewed', 'process_date', 'process_comment', 'delegate'], 'id' => $aArgs['resId']]);
        foreach ($listInstances as $key => $value) {
            $primaryEntity = UserModel::getPrimaryEntityById(['select' => ['entity_label'], 'id' => $value['item_id']]);
            $listInstances[$key]['item_entity'] = $primaryEntity['entity_label'] ?? '';
            $user = UserModel::getById(['id' => $value['item_id'], 'select' => ['status']]);
            $listInstances[$key]['isValid'] = !empty($user) && !in_array($user['status'], ['SPD', 'DEL']);

            $listInstances[$key]['item_type'] = 'user';
            $itemLabel = $listInstances[$key]['item_firstname'].' '.$listInstances[$key]['item_lastname'];

            $listInstances[$key]['labelToDisplay'] = $itemLabel;
            $listInstances[$key]['delegatedBy'] = null;
            if (!empty($listInstances[$key]['delegate'])) {
                $listInstances[$key]['labelToDisplay'] = UserModel::getLabelledUserById(['id' => $listInstances[$key]['delegate']]);
                $listInstances[$key]['delegatedBy'] = $itemLabel;
            }

            $listInstances[$key]['hasPrivilege'] = true;
            if (empty($value['process_date']) && !PrivilegeController::hasPrivilege(['privilegeId' => 'avis_documents', 'userId' => $value['item_id']])) {
                $listInstances[$key]['hasPrivilege'] = false;
            }
        }

        return $response->withJson($listInstances);
    }

    public function update(Request $request, Response $response)
    {
        $fullRight = false;

        if (PrivilegeController::hasPrivilege(['privilegeId' => 'admin_users', 'userId' => $GLOBALS['id']]) || PrivilegeController::hasPrivilege(['privilegeId' => 'update_diffusion_details', 'userId' => $GLOBALS['id']])) {
            $fullRight = true;
        } else {
            if (!PrivilegeController::hasPrivilege(['privilegeId' => 'update_diffusion_except_recipient_details', 'userId' => $GLOBALS['id']])
                && !PrivilegeController::hasPrivilege(['privilegeId' => 'update_diffusion_process', 'userId' => $GLOBALS['id']])
                && !PrivilegeController::hasPrivilege(['privilegeId' => 'update_diffusion_except_recipient_process', 'userId' => $GLOBALS['id']])) {
                return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
            }
        }

        $body = $request->getParsedBody();
        if (!Validator::arrayType()->notEmpty()->validate($body)) {
            return $response->withStatus(400)->withJson(['errors' => 'Body is not set or not an array']);
        }

        $controller = ListInstanceController::updateListInstance(['data' => $body, 'userId' => $GLOBALS['id'], 'fullRight' => $fullRight]);
        if (!empty($controller['errors'])) {
            return $response->withStatus($controller['code'])->withJson(['errors' => $controller['errors']]);
        }

        $resIds = array_column($body, 'resId');
        $resIds = array_unique($resIds);
        foreach ($resIds as $resId) {
            HistoryController::add([
                'tableName' => 'res_letterbox',
                'recordId'  => $resId,
                'eventType' => 'UP',
                'info'      => _UPDATE_LISTINSTANCE,
                'moduleId'  => 'listinstance',
                'eventId'   => 'listinstanceCreation',
            ]);
        }

        return $response->withStatus(204);
    }

    public static function updateListInstance(array $args)
    {
        ValidatorModel::notEmpty($args, ['data', 'userId']);
        ValidatorModel::arrayType($args, ['data']);
        ValidatorModel::intVal($args, ['userId']);

        DatabaseModel::beginTransaction();

        foreach ($args['data'] as $listInstanceByRes) {
            if (empty($listInstanceByRes['resId'])) {
                DatabaseModel::rollbackTransaction();
                return ['errors' => 'resId is empty', 'code' => 400];
            }

            if (!Validator::intVal()->validate($listInstanceByRes['resId']) || !ResController::hasRightByResId(['resId' => [$listInstanceByRes['resId']], 'userId' => $args['userId']])) {
                DatabaseModel::rollbackTransaction();
                return ['errors' => 'Document out of perimeter', 'code' => 403];
            }

            if (empty($listInstanceByRes['listInstances'])) {
                continue;
            }

            $listInstances = ListInstanceModel::get([
                'select'    => ['*'],
                'where'     => ['res_id = ?', 'difflist_type = ?'],
                'data'      => [$listInstanceByRes['resId'], 'entity_id']
            ]);

            $recipientFound = false;
            foreach ($listInstanceByRes['listInstances'] as $instance) {
                if (!empty($instance['item_mode']) && $instance['item_mode'] == 'dest') {
                    $recipientFound = true;
                }
            }
            if (!$recipientFound) {
                DatabaseModel::rollbackTransaction();
                return ['errors' => 'Dest is missing', 'code' => 400];
            }

            ListInstanceModel::delete([
                'where' => ['res_id = ?', 'difflist_type = ?'],
                'data'  => [$listInstanceByRes['resId'], 'entity_id']
            ]);

            $hasCopy = false;
            foreach ($listInstanceByRes['listInstances'] as $key => $instance) {
                $listControl = ['item_id', 'item_type', 'item_mode'];
                foreach ($listControl as $itemControl) {
                    if (empty($instance[$itemControl])) {
                        return ['errors' => "ListInstance {$itemControl} is not set or empty", 'code' => 400];
                    }
                }

                if (in_array($instance['item_type'], ['user_id', 'user'])) {
                    if (!is_numeric($instance['item_id'])) {
                        $user = UserModel::getByLogin(['login' => $instance['item_id'], 'select' => ['id']]);
                        $instance['item_id'] = $user['id'] ?? null;
                    } else {
                        $user = UserModel::getById(['id' => $instance['item_id'], 'select' => [1]]);
                    }
                    $instance['item_type'] = 'user_id';
                    if (empty($user)) {
                        DatabaseModel::rollbackTransaction();
                        return ['errors' => 'User not found', 'code' => 400];
                    }
                } elseif (in_array($instance['item_type'], ['entity_id', 'entity'])) {
                    if (!is_numeric($instance['item_id'])) {
                        $entity = EntityModel::getByEntityId(['entityId' => $instance['item_id'], 'select' => ['id', 'enabled']]);
                        $instance['item_id'] = $entity['id'];
                    } else {
                        $entity = EntityModel::getById(['id' => $instance['item_id'], 'select' => ['enabled']]);
                    }

                    $instance['item_type'] = 'entity_id';
                    if (empty($entity) || $entity['enabled'] != 'Y') {
                        DatabaseModel::rollbackTransaction();
                        return ['errors' => 'Entity not found or not active', 'code' => 400];
                    }
                } else {
                    DatabaseModel::rollbackTransaction();
                    return ['errors' => 'item_type does not exist', 'code' => 400];
                }

                if ($instance['item_mode'] == 'dest' && !$args['fullRight']) {
                    foreach ($listInstances as $listInstance) {
                        if ($listInstance['item_mode'] == 'dest') {
                            if ($listInstance['item_type'] != $instance['item_type'] || $listInstance['item_id'] != $instance['item_id']) {
                                if (!PrivilegeController::hasPrivilege(['privilegeId' => 'update_diffusion_process', 'userId' => $args['userId']])) {
                                    DatabaseModel::rollbackTransaction();
                                    return ['errors' => 'Privilege forbidden : update assignee', 'code' => 403];
                                } elseif (!PrivilegeController::isResourceInProcess(['userId' => $args['userId'], 'resId' => $listInstanceByRes['resId']])) {
                                    DatabaseModel::rollbackTransaction();
                                    return ['errors' => 'Privilege forbidden : update assignee', 'code' => 403];
                                }
                            }
                        }
                    }
                }

                if ($instance['item_mode'] == 'cc') {
                    $hasCopy = true;
                }

                ListInstanceModel::create([
                    'res_id'                => $listInstanceByRes['resId'],
                    'sequence'              => $key,
                    'item_id'               => $instance['item_id'],
                    'item_type'             => $instance['item_type'],
                    'item_mode'             => $instance['item_mode'],
                    'added_by_user'         => $args['userId'],
                    'difflist_type'         => 'entity_id',
                    'process_date'          => null,
                    'process_comment'       => null,
                    'requested_signature'   => false,
                    'viewed'                => empty($instance['viewed']) ? 0 : $instance['viewed'],
                    'delegate'              => $instance['delegate'] ?? null
                ]);

                if ($instance['item_mode'] == 'dest') {
                    $set          = ['dest_user' => $instance['item_id']];
                    $entities     = UserEntityModel::get(['select' => ['entity_id', 'primary_entity'], 'where' => ['user_id = ?'], 'data' => [$instance['item_id']]]);
                    $entitiesId   = array_column($entities, 'entity_id');
                    $userEntities = [];
                    if (!empty($entitiesId)) {
                        $userEntities = EntityModel::get(['select' => ['id', 'entity_id'], 'where' => ['entity_id in (?)'], 'data' => [$entitiesId]]);
                    }
                    $userEntities = array_column($userEntities, 'entity_id', 'id');
                    if (!empty($userEntities[$listInstanceByRes['destination']])) {
                        $set['destination'] = $userEntities[$listInstanceByRes['destination']];
                    } else {
                        $changeDestination = true;
                        $resource          = ResModel::getById(['select' => ['destination'], 'resId' => $listInstanceByRes['resId']]);
                        foreach ($entities as $entity) {
                            if ($entity['entity_id'] == $resource['destination']) {
                                $changeDestination = false;
                            }
                            if ($entity['primary_entity'] == 'Y') {
                                $destPrimaryEntity = $entity['entity_id'];
                            }
                        }
                        if ($changeDestination && !empty($destPrimaryEntity)) {
                            $set['destination'] = $destPrimaryEntity;
                        }
                    }

                    $resource = ResModel::getById(['select' => ['dest_user'], 'resId' => $listInstanceByRes['resId']]);
                    if ($resource['dest_user'] != $instance['item_id']) {
                        HistoryController::add([
                            'tableName' => 'res_letterbox',
                            'recordId'  => $listInstanceByRes['resId'],
                            'eventType' => 'UP',
                            'info'      => _UPDATE_LISTINSTANCE_DEST,
                            'moduleId'  => 'listinstance',
                            'eventId'   => 'diffdestuser',
                        ]);
                    }

                    ResModel::update([
                        'set'   => $set,
                        'where' => ['res_id = ?'],
                        'data'  => [$listInstanceByRes['resId']]
                    ]);
                }
            }
            if ($hasCopy) {
                HistoryController::add([
                    'tableName' => 'res_letterbox',
                    'recordId'  => $listInstanceByRes['resId'],
                    'eventType' => 'UP',
                    'info'      => _UPDATE_LISTINSTANCE,
                    'moduleId'  => 'listinstance',
                    'eventId'   => 'diffcopy',
                ]);
            }

            $listInstanceHistoryId = ListInstanceHistoryModel::create(['resId' => $listInstanceByRes['resId'], 'userId' => $args['userId']]);
            foreach ($listInstances as $listInstance) {
                ListInstanceHistoryDetailModel::create([
                    'listinstance_history_id'   => $listInstanceHistoryId,
                    'res_id'                    => $listInstance['res_id'],
                    'sequence'                  => $listInstance['sequence'],
                    'item_id'                   => $listInstance['item_id'],
                    'item_type'                 => $listInstance['item_type'],
                    'item_mode'                 => $listInstance['item_mode'],
                    'added_by_user'             => $listInstance['added_by_user'],
                    'difflist_type'             => 'entity_id',
                    'process_date'              => null,
                    'process_comment'           => null
                ]);
            }
        }

        DatabaseModel::commitTransaction();

        return ['success' => 'success'];
    }

    public function updateCircuits(Request $request, Response $response, array $args)
    {
        $body = $request->getParsedBody();
        if (!Validator::arrayType()->notEmpty()->validate($body)) {
            return $response->withStatus(400)->withJson(['errors' => 'Body is not set or not an array']);
        } elseif (!Validator::stringType()->validate($args['type']) || !in_array($args['type'], ['visaCircuit', 'opinionCircuit'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Route params type is empty or not valid']);
        }

        if ($args['type'] == 'visaCircuit') {
            $minimumVisaRole = ParameterModel::getById(['select' => ['param_value_int'], 'id' => 'minimumVisaRole']);
            $maximumSignRole = ParameterModel::getById(['select' => ['param_value_int'], 'id' => 'maximumSignRole']);
            $workflowSignatoryRole = ParameterModel::getById(['select' => ['param_value_string'], 'id' => 'workflowSignatoryRole']);

            $minimumVisaRole = !empty($minimumVisaRole['param_value_int']) ? $minimumVisaRole['param_value_int'] : 0;
            $maximumSignRole = !empty($maximumSignRole['param_value_int']) ? $maximumSignRole['param_value_int'] : 0;
            $workflowSignatoryRole = $workflowSignatoryRole['param_value_string'];
            if (!in_array($workflowSignatoryRole, SignatureBookController::SIGNATORY_ROLES)) {
                $workflowSignatoryRole = SignatureBookController::SIGNATORY_ROLE_DEFAULT;
            }
        }

        DatabaseModel::beginTransaction();

        foreach ($body['resources'] as $resourceKey => $resource) {
            if (empty($resource['resId'])) {
                DatabaseModel::rollbackTransaction();
                return $response->withStatus(400)->withJson(['errors' => "Body resources[{$resourceKey}] resId is empty"]);
            } elseif (!Validator::intVal()->validate($resource['resId']) || !ResController::hasRightByResId(['resId' => [$resource['resId']], 'userId' => $GLOBALS['id']])) {
                DatabaseModel::rollbackTransaction();
                return $response->withStatus(403)->withJson(['errors' => 'Resource out of perimeter']);
            } elseif (!Validator::arrayType()->notEmpty()->validate($resource['listInstances'])) {
                DatabaseModel::rollbackTransaction();
                return $response->withStatus(400)->withJson(['errors' => "Body resources[{$resourceKey}] listInstances is empty"]);
            }

            if ($args['type'] == 'visaCircuit' && $workflowSignatoryRole == SignatureBookController::SIGNATORY_ROLE_MANDATORY_FINAL) {
                $last = count($resource['listInstances']) -1;
                if (empty($resource['listInstances'][$last]['process_date']) && $resource['listInstances'][$last]['requested_signature'] == false) {
                    DatabaseModel::rollbackTransaction();
                    return $response->withStatus(400)->withJson(['errors' => "Body resources[{$resourceKey}] listInstances last user is not a signatory", 'lang' => 'lastNotSignatory']);
                } elseif (!empty($resource['listInstances'][$last]['process_date']) && $resource['listInstances'][$last]['signatory'] == false) {
                    DatabaseModel::rollbackTransaction();
                    return $response->withStatus(400)->withJson(['errors' => "Body resources[{$resourceKey}] listInstances last user is not a signatory", 'lang' => 'lastNotSignatory']);
                }
            }

            $listInstances = ListInstanceModel::get([
                'select'  => ['*'],
                'where'   => ['res_id = ?', 'difflist_type = ?'],
                'data'    => [$resource['resId'], self::MAPPING_TYPES[$args['type']]],
                'orderBy' => ['sequence']
            ]);
            $originalListInstances = $listInstances;
            $newListSequenceOrdered = array_column($resource['listInstances'], null, 'sequence');

            ListInstanceModel::delete([
                'where' => ['res_id = ?', 'difflist_type = ?'],
                'data'  => [$resource['resId'], self::MAPPING_TYPES[$args['type']]]
            ]);

            $minSequenceNoProcessDate = -1;
            $hasVisa = false;
            $hasSign = false;
            foreach ($listInstances as $listInstanceKey => $listInstance) {
                if (empty($listInstance['process_date'])) {
                    unset($listInstances[$listInstanceKey]);
                    continue;
                }
                if ($listInstance['sequence'] > $minSequenceNoProcessDate) {
                    $minSequenceNoProcessDate = $listInstance['sequence'];
                }
                if ($listInstance['signatory']) {
                    $hasSign = true;
                } else {
                    $hasVisa = true;
                }
            }
            $listInstances = array_values($listInstances);

            foreach ($resource['listInstances'] as $key => $listInstance) {
                if (!empty($listInstance['process_date'])) {
                    continue;
                } elseif (empty($listInstance['item_id'])) {
                    DatabaseModel::rollbackTransaction();
                    return $response->withStatus(400)->withJson(['errors' => "Body resources[{$resourceKey}] listInstances[{$key}] item_id is empty"]);
                } elseif (!empty($listInstance['process_comment']) && !Validator::stringType()->length(1, 255)->validate($listInstance['process_comment'])) {
                    DatabaseModel::rollbackTransaction();
                    return $response->withStatus(400)->withJson(['errors' => "Body resources[{$resourceKey}] listInstances[{$key}] process_comment is too long"]);
                }

                if (!empty($newListSequenceOrdered['sequence']) && $listInstance['sequence'] < $minSequenceNoProcessDate) {
                    DatabaseModel::rollbackTransaction();
                    return $response->withStatus(400)->withJson(['errors' => "Body resources[{$resourceKey}] listInstances[{$key}] sequence is before already processed users"]);
                }

                if (!is_numeric($listInstance['item_id'])) {
                    $user = UserModel::getByLogin(['login' => $listInstance['item_id'], 'select' => ['id'], 'noDeleted' => true]);
                    $listInstance['item_id'] = $user['id'] ?? null;
                } else {
                    $user = UserModel::getById(['id' => $listInstance['item_id'], 'select' => ['id'], 'noDeleted' => true]);
                }
                $listInstance['item_type'] = 'user_id';
                if (empty($user)) {
                    DatabaseModel::rollbackTransaction();
                    return $response->withStatus(400)->withJson(['errors' => "Body resources[{$resourceKey}] listInstances[{$key}] item_id does not exist"]);
                }
                if ($args['type'] == 'visaCircuit') {
                    if (!PrivilegeController::hasPrivilege(['privilegeId' => 'visa_documents', 'userId' => $user['id']]) && !PrivilegeController::hasPrivilege(['privilegeId' => 'sign_document', 'userId' => $user['id']])) {
                        $rawOriginalListInstances = array_column($originalListInstances, 'item_id');
                        if (!in_array($user['id'], $rawOriginalListInstances)) {
                            DatabaseModel::rollbackTransaction();
                            return $response->withStatus(400)->withJson(['errors' => "Body resources[{$resourceKey}] listInstances[{$key}] item_id has not enough privileges"]);
                        }
                    }
                    $listInstance['item_mode'] = $listInstance['requested_signature'] ? 'sign' : 'visa';
                } else {
                    if (!PrivilegeController::hasPrivilege(['privilegeId' => 'avis_documents', 'userId' => $user['id']])) {
                        DatabaseModel::rollbackTransaction();
                        return $response->withStatus(400)->withJson(['errors' => "Body resources[{$resourceKey}] listInstances[{$key}] item_id has not enough privileges"]);
                    }
                    $listInstance['item_mode'] = 'avis';
                }

                $listInstances[] = [
                    'item_id'               => $listInstance['item_id'],
                    'item_type'             => $listInstance['item_type'],
                    'item_mode'             => $listInstance['item_mode'],
                    'process_date'          => null,
                    'process_comment'       => $listInstance['process_comment'] ?? null,
                    'requested_signature'   => $listInstance['requested_signature'] ?? false,
                    'signatory'             => $listInstance['signatory'] ?? false,
                    'delegate'              => $listInstance['delegate'] ?? null
                ];

                if ($args['type'] == 'visaCircuit') {
                    if ($listInstance['requested_signature']) {
                        $hasSign = true;
                    } else {
                        $hasVisa = true;
                    }
                }
            }

            if ($args['type'] == 'visaCircuit' && $workflowSignatoryRole == SignatureBookController::SIGNATORY_ROLE_MANDATORY && !$hasSign) {
                DatabaseModel::rollbackTransaction();
                return $response->withStatus(400)->withJson(['errors' => "Body resources[{$resourceKey}] listInstances requires at least one sign user", 'lang' => 'signUserRequired']);
            }

            if ($args['type'] == 'visaCircuit' && (!empty($minimumVisaRole) || !empty($maximumSignRole))) {
                $nbVisaRole = 0;
                $nbSignRole = 0;
                foreach ($listInstances as $listInstance) {
                    $isSign = $listInstance['signatory'] || ($listInstance['requested_signature'] && $listInstance['process_date'] == null);
                    if ($isSign) {
                        $nbSignRole++;
                    } else {
                        $nbVisaRole++;
                    }
                }
                if ($minimumVisaRole != 0 && $nbVisaRole < $minimumVisaRole) {
                    DatabaseModel::rollbackTransaction();
                    return $response->withStatus(400)->withJson(['errors' => "Body resources[{$resourceKey}] listInstances does not have enough visa users", 'lang' => 'notEnoughVisaUser']);
                }
                if ($maximumSignRole != 0 && $nbSignRole > $maximumSignRole) {
                    DatabaseModel::rollbackTransaction();
                    return $response->withStatus(400)->withJson(['errors' => "Body resources[{$resourceKey}] listInstances has too many sign users", 'lang' => 'tooManySignUser']);
                }
            }

            if ($hasVisa) {
                HistoryController::add([
                    'tableName' => 'res_letterbox',
                    'recordId'  => $resource['resId'],
                    'eventType' => 'UP',
                    'info'      => _UPDATE_VISA_CIRCUIT,
                    'moduleId'  => 'listinstance',
                    'eventId'   => 'diffvisauser',
                ]);
            }
            if ($hasSign) {
                HistoryController::add([
                    'tableName' => 'res_letterbox',
                    'recordId'  => $resource['resId'],
                    'eventType' => 'UP',
                    'info'      => _UPDATE_VISA_CIRCUIT,
                    'moduleId'  => 'listinstance',
                    'eventId'   => 'diffsignuser',
                ]);
            }

            $listInstanceHistoryId = ListInstanceHistoryModel::create(['resId' => $resource['resId'], 'userId' => $GLOBALS['id']]);
            foreach ($listInstances as $key => $listInstance) {
                ListInstanceModel::create([
                    'res_id'                => $resource['resId'],
                    'sequence'              => $key,
                    'item_id'               => $listInstance['item_id'],
                    'item_type'             => $listInstance['item_type'],
                    'item_mode'             => $listInstance['item_mode'],
                    'added_by_user'         => $GLOBALS['id'],
                    'difflist_type'         => $args['type'] == 'visaCircuit' ? 'VISA_CIRCUIT' : 'AVIS_CIRCUIT',
                    'process_date'          => $listInstance['process_date'],
                    'process_comment'       => $listInstance['process_comment'],
                    'requested_signature'   => $listInstance['requested_signature'],
                    'signatory'             => $listInstance['signatory'],
                    'delegate'              => $listInstance['delegate']
                ]);
                ListInstanceHistoryDetailModel::create([
                    'listinstance_history_id'   => $listInstanceHistoryId,
                    'res_id'                    => $resource['resId'],
                    'sequence'                  => $key,
                    'item_id'                   => $listInstance['item_id'],
                    'item_type'                 => $listInstance['item_type'],
                    'item_mode'                 => $listInstance['item_mode'],
                    'added_by_user'             => $GLOBALS['id'],
                    'difflist_type'             => $args['type'] == 'visaCircuit' ? 'VISA_CIRCUIT' : 'AVIS_CIRCUIT',
                    'process_date'              => $listInstance['process_date'],
                    'process_comment'           => $listInstance['process_comment'],
                    'requested_signature'       => $listInstance['requested_signature'],
                    'signatory'                 => $listInstance['signatory']
                ]);
            }
        }

        $resIds = array_column($body['resources'], 'resId');
        $resIds = array_unique($resIds);

        if ($args['type'] == 'visaCircuit') {
            $info = _UPDATE_VISA_CIRCUIT;
        } else {
            $info = _UPDATE_AVIS_CIRCUIT;
        }

        foreach ($resIds as $resId) {
            HistoryController::add([
                'tableName' => 'res_letterbox',
                'recordId'  => $resId,
                'eventType' => 'UP',
                'info'      => $info,
                'moduleId'  => 'listinstance',
                'eventId'   => 'listinstanceCreation',
            ]);
        }

        DatabaseModel::commitTransaction();

        return $response->withStatus(204);
    }

    public function deleteCircuit(Request $request, Response $response, array $args)
    {
        if (!Validator::intVal()->validate($args['resId']) || !ResController::hasRightByResId(['resId' => [$args['resId']], 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Resource out of perimeter']);
        } elseif (!Validator::stringType()->validate($args['type']) || !in_array($args['type'], ['visaCircuit', 'opinionCircuit'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Route params type is empty or not valid']);
        } elseif ($args['type'] == 'visaCircuit' && !PrivilegeController::hasPrivilege(['privilegeId' => 'config_visa_workflow', 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        } elseif ($args['type'] == 'opinionCircuit' && !PrivilegeController::hasPrivilege(['privilegeId' => 'config_avis_workflow', 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }

        $circuit = ListInstanceModel::get(['select' => [1], 'where' => ['res_id = ?', 'difflist_type = ?', 'process_date is not null'], 'data' => [$args['resId'], self::MAPPING_TYPES[$args['type']]]]);
        if (!empty($circuit)) {
            return $response->withStatus(403)->withJson(['errors' => 'Circuit has already begun']);
        }

        ListInstanceModel::delete([
            'where' => ['res_id = ?', 'difflist_type = ?'],
            'data'  => [$args['resId'], self::MAPPING_TYPES[$args['type']]]
        ]);

        if ($args['type'] == 'visaCircuit') {
            $info = _VISA_CIRCUIT_DELETED;
        } else {
            $info = _AVIS_CIRCUIT_DELETED;
        }

        HistoryController::add([
            'tableName' => 'res_letterbox',
            'recordId'  => $args['resId'],
            'eventType' => 'DEL',
            'info'      => $info,
            'moduleId'  => 'listinstance',
            'eventId'   => 'listinstanceCreation',
        ]);

        return $response->withStatus(204);
    }
}
