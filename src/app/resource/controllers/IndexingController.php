<?php

/**
* Copyright Maarch since 2008 under licence GPLv3.
* See LICENCE.txt file at the root folder for more details.
* This file is part of Maarch software.
*
*/

/**
* @brief Indexing Controller
* @author dev@maarch.org
*/

namespace Resource\controllers;

use Action\controllers\ActionController;
use Action\controllers\ActionMethodController;
use Action\models\ActionModel;
use Doctype\models\DoctypeModel;
use Entity\models\EntityModel;
use Group\models\GroupModel;
use Parameter\models\ParameterModel;
use Priority\models\PriorityModel;
use Resource\models\ResModel;
use Respect\Validation\Validator;
use Slim\Psr7\Request;
use SrcCore\http\Response;
use SrcCore\controllers\CoreController;
use SrcCore\controllers\PreparedClauseController;
use SrcCore\models\ValidatorModel;
use User\models\UserGroupModel;

class IndexingController
{
    const KEYWORDS = [
        'ALL_ENTITIES'          => '@all_entities',
        'ENTITIES_JUST_BELOW'   => '@immediate_children[@my_primary_entity]',
        'ENTITIES_BELOW'        => '@subentities[@my_entities]',
        'ALL_ENTITIES_BELOW'    => '@subentities[@my_primary_entity]',
        'ENTITIES_JUST_UP'      => '@parent_entity[@my_primary_entity]',
        'MY_ENTITIES'           => '@my_entities',
        'MY_PRIMARY_ENTITY'     => '@my_primary_entity',
        'SAME_LEVEL_ENTITIES'   => '@sisters_entities[@my_primary_entity]'
    ];

    const HOLLIDAYS = [
        '01-01',
        '01-05',
        '08-05',
        '14-07',
        '15-08',
        '01-11',
        '11-11',
        '25-12'
    ];

    public function setAction(Request $request, Response $response, array $args)
    {
        $body = $request->getParsedBody();

        if (!Validator::notEmpty()->intVal()->validate($body['resource'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body resource is empty or not an integer']);
        }

        $group = GroupModel::getById(['id' => $args['groupId'], 'select' => ['can_index', 'indexation_parameters']]);
        if (empty($group)) {
            return $response->withStatus(400)->withJson(['errors' => 'Route groupId does not exist']);
        }

        $isUserLinked = UserGroupModel::get(['select' => [1], 'where' => ['user_id = ?', 'group_id = ?'], 'data' => [$GLOBALS['id'], $args['groupId']]]);
        if (empty($isUserLinked)) {
            return $response->withStatus(400)->withJson(['errors' => 'Group is not linked to this user']);
        }

        $group['indexation_parameters'] = json_decode($group['indexation_parameters'], true);

        if (!in_array($args['actionId'], $group['indexation_parameters']['actions'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Action is not linked to this group']);
        }

        $action = ActionModel::getById(['id' => $args['actionId'], 'select' => ['component', 'parameters']]);
        if (empty($action['component'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Action component does not exist']);
        }
        if (!array_key_exists($action['component'], ActionMethodController::COMPONENTS_ACTIONS)) {
            return $response->withStatus(400)->withJson(['errors' => 'Action method does not exist']);
        }
        $parameters = json_decode($action['parameters'], true);
        $actionRequiredFields = $parameters['requiredFields'] ?? [];

        $resource = ResModel::getById(['resId' => $body['resource'], 'select' => ['status']]);
        if (empty($resource)) {
            return $response->withStatus(400)->withJson(['errors' => 'Resource does not exist']);
        } elseif (!empty($resource['status'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Resource out of perimeter']);
        }

        $body['data'] = empty($body['data']) ? [] : $body['data'];
        $body['note'] = empty($body['note']) ? [] : $body['note'];

        if (!empty($actionRequiredFields)) {
            $requiredFieldsValid = ActionController::checkRequiredFields(['resId' => $body['resource'], 'actionRequiredFields' => $actionRequiredFields]);
            if (!empty($requiredFieldsValid['errors'])) {
                return $response->withStatus(400)->withJson($requiredFieldsValid);
            }
        }

        $method = ActionMethodController::COMPONENTS_ACTIONS[$action['component']];
        if (!empty($method)) {
            $methodResponse = ActionMethodController::$method(['resId' => $body['resource'], 'data' => $body['data'], 'note' => $body['note'], 'parameters' => $parameters, 'actionId' => $args['actionId']]);
        }
        if (!empty($methodResponse['errors'])) {
            $return = ['errors' => $methodResponse['errors'][0]];
            if (!empty($methodResponse['lang'])) {
                $return['lang'] = $methodResponse['lang'];
            }
            return $response->withStatus(400)->withJson($return);
        }

        $historic = empty($methodResponse['history']) ? '' : $methodResponse['history'];
        ActionMethodController::terminateAction(['id' => $args['actionId'], 'resources' => [$body['resource']], 'note' => $body['note'], 'history' => $historic, 'finishInScript' => !empty($methodResponse['postscript'])]);

        if (!empty($methodResponse['postscript'])) {
            $base64Args = base64_encode(json_encode($methodResponse['args']));
            exec("php {$methodResponse['postscript']} --encodedData {$base64Args} > /dev/null &");
            unset($methodResponse['postscript']);
        }

        if (!empty($methodResponse['data'])) {
            return $response->withJson($methodResponse['data']);
        }

        return $response->withStatus(204);
    }

    public function getIndexingActions(Request $request, Response $response, array $args)
    {
        if (!Validator::notEmpty()->intVal()->validate($args['groupId'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Param groupId must be an integer val']);
        }

        $indexingParameters = IndexingController::getIndexingParameters(['userId' => $GLOBALS['id'], 'groupId' => $args['groupId']]);
        if (!empty($indexingParameters['errors'])) {
            return $response->withStatus(403)->withJson($indexingParameters);
        }

        $actions = [];
        $categories = ResModel::getCategories();

        foreach ($indexingParameters['indexingParameters']['actions'] as $value) {
            $action         = ActionModel::getById(['id' => $value, 'select' => ['id', 'label_action', 'component', 'id_status', 'parameters']]);
            $categoriesList = ActionModel::getCategoriesById(['id' => $value]);

            $action['label']   = $action['label_action'];

            $action['parameters'] = json_decode($action['parameters'], true);
            $action['enabled'] = !empty($action['parameters']['successStatus']) ? $action['parameters']['successStatus'] != '_NOSTATUS_' : !empty($action['id_status']) && $action['id_status'] != '_NOSTATUS_';
            unset($action['parameters']);

            if (!empty($categoriesList)) {
                $action['categories'] = array_column($categoriesList, 'category_id');
            } else {
                $action['categories'] = array_column($categories, 'id');
            }
            unset($action['label_action'], $action['id_status']);
            $actions[] = $action;
        }

        return $response->withJson(['actions' => $actions]);
    }

    public function getIndexingEntities(Request $request, Response $response, array $aArgs)
    {
        if (!Validator::notEmpty()->intVal()->validate($aArgs['groupId'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Param groupId must be an integer val']);
        }

        $indexingParameters = IndexingController::getIndexingParameters(['userId' => $GLOBALS['id'], 'groupId' => $aArgs['groupId']]);
        if (!empty($indexingParameters['errors'])) {
            return $response->withStatus(403)->withJson($indexingParameters);
        }

        $allowedEntities = [];
        $clauseToProcess = '';

        foreach ($indexingParameters['indexingParameters']['keywords'] as $keywordValue) {
            if (!empty($clauseToProcess)) {
                $clauseToProcess .= ', ';
            }
            $clauseToProcess .= IndexingController::KEYWORDS[$keywordValue];
        }

        if (!empty($clauseToProcess)) {
            $preparedClause = PreparedClauseController::getPreparedClause(['clause' => $clauseToProcess, 'userId' => $GLOBALS['id']]);
            $preparedEntities = EntityModel::get(['select' => ['id'], 'where' => ['enabled = ?', "entity_id in {$preparedClause}"], 'data' => ['Y']]);
            $allowedEntities = array_column($preparedEntities, 'id');
        }

        $allowedEntities = array_merge($indexingParameters['indexingParameters']['entities'], $allowedEntities);
        $allowedEntities = array_unique($allowedEntities);

        $entitiesTmp = EntityModel::get([
            'select'   => ['id', 'entity_label', 'entity_id'],
            'where'    => ['enabled = ?', '(parent_entity_id is null OR parent_entity_id = \'\')'],
            'data'     => ['Y'],
            'orderBy'  => ['entity_label']
        ]);
        if (!empty($entitiesTmp)) {
            foreach ($entitiesTmp as $key => $value) {
                $entitiesTmp[$key]['level'] = 0;
            }
            $entitiesId = array_column($entitiesTmp, 'entity_id');
            $entitiesChild = IndexingController::getEntitiesChildrenLevel(['entitiesId' => $entitiesId, 'level' => 1]);
            $entitiesTmp = array_merge([$entitiesTmp], $entitiesChild);
        }

        $entities = [];
        foreach ($entitiesTmp as $keyLevel => $levels) {
            foreach ($levels as $entity) {
                if (in_array($entity['id'], $allowedEntities)) {
                    $entity['enabled'] = true;
                } else {
                    $entity['enabled'] = false;
                }
                if ($keyLevel == 0) {
                    $entities[] = $entity;
                    continue;
                } else {
                    foreach ($entities as $key => $oEntity) {
                        if ($oEntity['entity_id'] == $entity['parent_entity_id']) {
                            array_splice($entities, $key+1, 0, [$entity]);
                            continue;
                        }
                    }
                }
            }
        }

        return $response->withJson(['entities' => $entities]);
    }

    public function getProcessLimitDate(Request $request, Response $response)
    {
        $queryParams = $request->getQueryParams();

        // if delay is 0, then the process limit date is today
        $delay = -1;
        if (!empty($queryParams['doctype'])) {
            $doctype = DoctypeModel::getById(['id' => $queryParams['doctype'], 'select' => ['process_delay']]);
            if (empty($doctype)) {
                return $response->withStatus(400)->withJson(['errors' => 'Doctype does not exists']);
            }
            $delay = $doctype['process_delay'];
        }
        if (!empty($queryParams['priority'])) {
            $priority = PriorityModel::getById(['id' => $queryParams['priority'], 'select' => ['delays']]);
            if (empty($priority)) {
                return $response->withStatus(400)->withJson(['errors' => 'Priority does not exists']);
            }
            $delay = $priority['delays'];
        }
        if (!empty($queryParams['today'])) {
            $queryParams['today'] = filter_var($queryParams['today'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if (!Validator::boolType()->validate($queryParams['today'])) {
                return $response->withStatus(400)->withJson(['errors' => 'today is not a boolean']);
            }
            if ($queryParams['today']) {
                $delay = 0;
            }
        }
        if ($delay == -1) {
            return $response->withJson(['processLimitDate' => null]);
        }
        if (!Validator::intVal()->validate($delay)) {
            return $response->withStatus(400)->withJson(['errors' => 'Delay is not a numeric value']);
        }

        $processLimitDate = IndexingController::calculateProcessDate(['date' => date('c'), 'delay' => $delay]);

        return $response->withJson(['processLimitDate' => $processLimitDate]);
    }

    public function getFileInformations(Request $request, Response $response)
    {
        $allowedFiles = StoreController::getAllowedFiles();

        $maximumSize = CoreController::getMaximumAllowedSizeFromPhpIni();
        $maximumSizeLabel = round($maximumSize / 1048576, 3) . ' Mo';

        return $response->withJson(['informations' => ['maximumSize' => $maximumSize, 'maximumSizeLabel' => $maximumSizeLabel, 'allowedFiles' => $allowedFiles]]);
    }

    public function getPriorityWithProcessLimitDate(Request $request, Response $response)
    {
        $queryParams = $request->getQueryParams();

        if (empty($queryParams['processLimitDate'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Query params processLimitDate is empty']);
        }

        $priorityId = IndexingController::calculatePriorityWithProcessLimitDate(['processLimitDate' => $queryParams['processLimitDate']]);

        return $response->withJson(['priority' => $priorityId]);
    }

    public static function calculatePriorityWithProcessLimitDate(array $args)
    {
        $processLimitDate = new \DateTime($args['processLimitDate']);
        $processLimitDate->setTime(23, 59, 59);
        $now = new \DateTime();

        $diff = $processLimitDate->diff($now);
        $diff = $diff->format("%a");

        $workingDays = ParameterModel::getById(['id' => 'workingDays', 'select' => ['param_value_int']]);
        if (!empty($workingDays['param_value_int'])) {
            $hollidays = IndexingController::HOLLIDAYS;
            if (function_exists('easter_date')) {
                $hollidays[] = date('d-m', easter_date() + 86400);
            }

            $diffUpdated = 0;
            for ($i = 1; $i <= $diff; $i++) {
                $tmpDate = new \DateTime();
                $tmpDate->add(new \DateInterval("P{$i}D"));
                if (in_array($tmpDate->format('N'), [6, 7]) || in_array($tmpDate->format('d-m'), $hollidays)) {
                    continue;
                }
                ++$diffUpdated;
            }

            $diff = $diffUpdated;
        }

        $priority = PriorityModel::get(['select' => ['id'], 'where' => ['delays >= ?'], 'data' => [$diff], 'orderBy' => ['delays'], 'limit' => 1]);
        if (empty($priority)) {
            $priority = PriorityModel::get(['select' => ['id'], 'orderBy' => ['delays DESC'], 'limit' => 1]);
        }

        return $priority[0]['id'];
    }

    public static function getEntitiesChildrenLevel($aArgs = [])
    {
        $entities = EntityModel::getEntityChildrenSubLevel([
            'entitiesId' => $aArgs['entitiesId'],
            'select'     => ['id', 'entity_label', 'entity_id', 'parent_entity_id'],
            'orderBy'    => ['entity_label desc']
        ]);
        if (!empty($entities)) {
            foreach ($entities as $key => $value) {
                $entities[$key]['level'] = $aArgs['level'];
            }
            $entitiesId = array_column($entities, 'entity_id');
            $entitiesChild = IndexingController::getEntitiesChildrenLevel(['entitiesId' => $entitiesId, 'level' => $aArgs['level']+1]);
            $entities = array_merge([$entities], $entitiesChild);
        }

        return $entities;
    }

    public static function getIndexingParameters($aArgs = [])
    {
        $group = GroupModel::getGroupWithUsersGroups(['userId' => $aArgs['userId'], 'groupId' => $aArgs['groupId'], 'select' => ['can_index', 'indexation_parameters']]);
        if (empty($group)) {
            return ['errors' => 'This user is not in this group'];
        }
        if (!$group[0]['can_index']) {
            return ['errors' => 'This group can not index document'];
        }

        $group[0]['indexation_parameters'] = json_decode($group[0]['indexation_parameters'], true);

        return ['indexingParameters' => $group[0]['indexation_parameters']];
    }

    public static function calculateProcessDate(array $args)
    {
        ValidatorModel::notEmpty($args, ['date']);
        ValidatorModel::intVal($args, ['delay']);

        $date = new \DateTime($args['date']);

        $workingDays = ParameterModel::getById(['id' => 'workingDays', 'select' => ['param_value_int']]);

        // Working Day
        if ($workingDays['param_value_int'] == 1 && !empty($args['delay'])) {
            $hollidays = IndexingController::HOLLIDAYS;
            if (function_exists('easter_date')) {
                $hollidays[] = date('d-m', easter_date() + 86400);
            }

            $processDelayUpdated = 1;
            for ($i = 1; $i <= $args['delay']; $i++) {
                $tmpDate = new \DateTime($args['date']);
                if (!empty($args['sub'])) {
                    $tmpDate->sub(new \DateInterval("P{$i}D"));
                } else {
                    $tmpDate->add(new \DateInterval("P{$i}D"));
                }
                if (in_array($tmpDate->format('N'), [6, 7]) || in_array($tmpDate->format('d-m'), $hollidays)) {
                    ++$args['delay'];
                }
                if ($i+1 <= $args['delay']) {
                    ++$processDelayUpdated;
                }
            }

            if (!empty($args['sub'])) {
                $date->sub(new \DateInterval("P{$processDelayUpdated}D"));
            } else {
                $date->add(new \DateInterval("P{$processDelayUpdated}D"));
            }
        } else {
            // Calendar or empty delay
            if (!empty($args['sub'])) {
                $date->sub(new \DateInterval("P{$args['delay']}D"));
            } else {
                $date->add(new \DateInterval("P{$args['delay']}D"));
            }
        }

        return $date->format('Y-m-d');
    }
}
