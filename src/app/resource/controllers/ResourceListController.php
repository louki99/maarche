<?php

/**
* Copyright Maarch since 2008 under licence GPLv3.
* See LICENCE.txt file at the root folder for more details.
* This file is part of Maarch software.
*
*/

/**
* @brief Resource List Controller
* @author dev@maarch.org
*/

namespace Resource\controllers;

use Action\controllers\ActionController;
use Action\controllers\ActionMethodController;
use Action\models\ActionModel;
use Attachment\models\AttachmentModel;
use Basket\models\ActionGroupBasketModel;
use Basket\models\BasketModel;
use Basket\models\GroupBasketModel;
use Basket\models\RedirectBasketModel;
use Contact\controllers\ContactController;
use Convert\models\AdrModel;
use CustomField\models\CustomFieldModel;
use Docserver\models\DocserverModel;
use Docserver\models\DocserverTypeModel;
use Entity\models\EntityModel;
use Entity\models\ListInstanceModel;
use Folder\models\FolderModel;
use Group\models\GroupModel;
use Note\models\NoteModel;
use Email\models\EmailModel;
use Shipping\models\ShippingModel;
use MessageExchange\models\MessageExchangeModel;
use AcknowledgementReceipt\models\AcknowledgementReceiptModel;
use Priority\models\PriorityModel;
use RegisteredMail\models\IssuingSiteModel;
use RegisteredMail\models\RegisteredMailModel;
use Resource\models\ResModel;
use Resource\models\ResourceListModel;
use Resource\models\UserFollowedResourceModel;
use Respect\Validation\Validator;
use Slim\Psr7\Request;
use SrcCore\http\Response;
use SrcCore\controllers\PreparedClauseController;
use SrcCore\models\TextFormatModel;
use SrcCore\models\ValidatorModel;
use Status\models\StatusModel;
use User\models\UserModel;

class ResourceListController
{
    public function get(Request $request, Response $response, array $aArgs)
    {
        $errors = ResourceListController::listControl(['groupId' => $aArgs['groupId'], 'userId' => $aArgs['userId'], 'basketId' => $aArgs['basketId'], 'currentUserId' => $GLOBALS['id']]);
        if (!empty($errors['errors'])) {
            return $response->withStatus($errors['code'])->withJson(['errors' => $errors['errors']]);
        }

        $basket = BasketModel::getById(['id' => $aArgs['basketId'], 'select' => ['basket_clause', 'basket_res_order', 'basket_name', 'basket_id']]);
        $user = UserModel::getById(['id' => $aArgs['userId'], 'select' => ['user_id']]);
        $group = GroupModel::getById(['id' => $aArgs['groupId'], 'select' => ['group_id']]);

        $data = $request->getQueryParams();
        $data['offset'] = (empty($data['offset']) || !is_numeric($data['offset']) ? 0 : (int)$data['offset']);
        $data['limit'] = (empty($data['limit']) || !is_numeric($data['limit']) ? 10 : (int)$data['limit']);

        $allQueryData = ResourceListController::getResourcesListQueryData(['data' => $data, 'basketClause' => $basket['basket_clause'], 'login' => $user['user_id']]);
        if (!empty($allQueryData['order'])) {
            $data['order'] = $allQueryData['order'];
        }

        $rawResources = ResourceListModel::getOnView([
            'select'    => ['res_id'],
            'table'     => $allQueryData['table'],
            'leftJoin'  => $allQueryData['leftJoin'],
            'where'     => $allQueryData['where'],
            'data'      => $allQueryData['queryData'],
            'orderBy'   => empty($data['order']) ? [$basket['basket_res_order']] : [$data['order']]
        ]);
        $count = count($rawResources);

        $resIds = ResourceListController::getIdsWithOffsetAndLimit(['resources' => $rawResources, 'offset' => $data['offset'], 'limit' => $data['limit']]);

        $followedDocuments = UserFollowedResourceModel::get([
            'select'    => ['res_id'],
            'where'     => ['user_id = ?'],
            'data'      => [$GLOBALS['id']],
        ]);

        $trackedMails = array_column($followedDocuments, 'res_id');
        $allResources = array_column($rawResources, 'res_id');

        $formattedResources = [];
        $defaultAction      = [];
        $displayFolderTags  = false;
        $templateColumns    = 0;
        if (!empty($resIds)) {
            $excludeAttachmentTypes = ['signed_response', 'summary_sheet'];
            $attachments = AttachmentModel::get([
                'select'    => ['COUNT(res_id)', 'res_id_master'],
                'where'     => ['res_id_master in (?)', 'status not in (?)', 'attachment_type not in (?)', '((status = ? AND typist = ?) OR status != ?)'],
                'data'      => [$resIds, ['DEL', 'OBS'], $excludeAttachmentTypes, 'TMP', $GLOBALS['id'], 'TMP'],
                'groupBy'   => ['res_id_master']
            ]);

            $groupBasket     = GroupBasketModel::get(['select' => ['list_display', 'list_event', 'list_event_data'], 'where' => ['basket_id = ?', 'group_id = ?'], 'data' => [$basket['basket_id'], $group['group_id']]]);
            $listDisplay     = json_decode($groupBasket[0]['list_display'], true);
            $templateColumns = $listDisplay['templateColumns'];
            $listDisplay     = $listDisplay['subInfos'];

            $selectData = ResourceListController::getSelectData(['listDisplay' => $listDisplay]);

            $order = 'CASE res_letterbox.res_id ';
            foreach ($resIds as $key => $resId) {
                $order .= "WHEN {$resId} THEN {$key} ";
            }
            $order .= 'END';

            $resources = ResourceListModel::getOnResource([
                'select'    => $selectData['select'],
                'table'     => $selectData['tableFunction'],
                'leftJoin'  => $selectData['leftJoinFunction'],
                'where'     => ['res_letterbox.res_id in (?)'],
                'data'      => [$resIds],
                'orderBy'   => [$order]
            ]);

            $formattedResources = ResourceListController::getFormattedResources([
                'resources'     => $resources,
                'userId'        => $GLOBALS['id'],
                'attachments'   => $attachments,
                'checkLocked'   => true,
                'listDisplay'   => $listDisplay,
                'trackedMails'  => $trackedMails
            ]);

            $defaultAction['component'] = $groupBasket[0]['list_event'];
            $defaultAction['data'] = json_decode($groupBasket[0]['list_event_data'] ?? '{}', true);

            if (in_array('getFolders', array_column($listDisplay, 'value'))) {
                $displayFolderTags = true;
            }
        }

        return $response->withJson([
            'resources'         => $formattedResources,
            'count'             => $count,
            'basketLabel'       => $basket['basket_name'],
            'basket_id'         => $basket['basket_id'],
            'allResources'      => $allResources,
            'defaultAction'     => $defaultAction,
            'displayFolderTags' => $displayFolderTags,
            'templateColumns'   => $templateColumns
        ]);
    }

    public static function getSelectData(array $args)
    {
        $select = [
            'res_letterbox.res_id', 'res_letterbox.subject', 'res_letterbox.barcode', 'res_letterbox.alt_identifier',
            'status.label_status AS "status.label_status"', 'status.img_filename AS "status.img_filename"', 'priorities.color AS "priorities.color"',
            'res_letterbox.closing_date', 'res_letterbox.locker_user_id', 'res_letterbox.locker_time', 'res_letterbox.confidentiality',
            'res_letterbox.filename as res_filename', 'res_letterbox.integrations', 'res_letterbox.retention_frozen', 'res_letterbox.binding'
        ];
        $tableFunction    = ['status', 'priorities'];
        $leftJoinFunction = ['res_letterbox.status = status.id', 'res_letterbox.priority = priorities.id'];
        foreach ($args['listDisplay'] as $value) {
            $value = (array)$value;
            if ($value['value'] == 'getPriority') {
                $select[] = 'priorities.label AS "priorities.label"';
            } elseif ($value['value'] == 'getCategory') {
                $select[] = 'res_letterbox.category_id';
            } elseif ($value['value'] == 'getDoctype') {
                $select[] = 'doctypes.description AS "doctypes.description"';
                $tableFunction[] = 'doctypes';
                $leftJoinFunction[] = 'res_letterbox.type_id = doctypes.type_id';
            } elseif ($value['value'] == 'getCreationAndProcessLimitDates') {
                $select[] = 'res_letterbox.creation_date';
                $select[] = 'res_letterbox.process_limit_date';
            } elseif ($value['value'] == 'getCreationDate') {
                $select[] = 'res_letterbox.creation_date';
            } elseif ($value['value'] == 'getProcessLimitDate') {
                $select[] = 'res_letterbox.process_limit_date';
            } elseif ($value['value'] == 'getModificationDate') {
                $select[] = 'res_letterbox.modification_date';
            } elseif ($value['value'] == 'getOpinionLimitDate') {
                $select[] = 'res_letterbox.opinion_limit_date';
            } elseif (strpos($value['value'], 'indexingCustomField_') !== false && !in_array('res_letterbox.custom_fields', $select)) {
                $select[] = 'res_letterbox.custom_fields';
            }
        }

        return ['select' => $select, 'tableFunction' => $tableFunction, 'leftJoinFunction' => $leftJoinFunction];
    }

    public function getFilters(Request $request, Response $response, array $aArgs)
    {
        $errors = ResourceListController::listControl(['groupId' => $aArgs['groupId'], 'userId' => $aArgs['userId'], 'basketId' => $aArgs['basketId'], 'currentUserId' => $GLOBALS['id']]);
        if (!empty($errors['errors'])) {
            return $response->withStatus($errors['code'])->withJson(['errors' => $errors['errors']]);
        }

        $basket = BasketModel::getById(['id' => $aArgs['basketId'], 'select' => ['basket_clause']]);
        $whereClause = PreparedClauseController::getPreparedClause(['clause' => $basket['basket_clause'], 'userId' => $aArgs['userId']]);
        $where = [$whereClause];
        $queryData = [];

        $queryParams = $request->getQueryParams();

        $filters = ResourceListController::getFormattedFilters(['where' => $where, 'queryData' => $queryData, 'queryParams' => $queryParams]);

        return $response->withJson($filters);
    }

    public static function getResourcesListQueryData(array $args)
    {
        ValidatorModel::stringType($args, ['basketClause', 'login']);
        ValidatorModel::arrayType($args, ['data']);

        $table = [];
        $leftJoin = [];
        $where = [];
        if (!empty($args['basketClause'])) {
            $user = UserModel::getByLogin(['login' => $args['login'], 'select' => ['id']]);
            $whereClause = PreparedClauseController::getPreparedClause(['clause' => $args['basketClause'], 'userId' => $user['id']]);
            $where = [$whereClause];
        }
        $queryData = [];
        $order = null;

        if (!empty($args['data']['delayed']) && $args['data']['delayed'] == 'true') {
            $where[] = 'process_limit_date < CURRENT_TIMESTAMP';
        }
        if (!empty($args['data']['search']) && mb_strlen($args['data']['search']) >= 2) {
            if (preg_match('/^"([^"]+)"$/', $args['data']['search'], $cleanSearch)) {
                $where[] = '(alt_identifier like ? OR subject like ?)';
                $queryData[] = "{$cleanSearch[1]}";
                $queryData[] = "{$cleanSearch[1]}";
            } else {
                $where[] = "(replace(alt_identifier, ' ', '') ilike ? OR unaccent(subject) ilike unaccent(?::text))";
                $whiteStrippedChrono = str_replace(' ', '', $args['data']['search']);
                $queryData[] = "%{$whiteStrippedChrono}%";
                $queryData[] = "%{$args['data']['search']}%";
            }
        }
        if (isset($args['data']['priorities'])) {
            if (empty($args['data']['priorities'])) {
                $where[] = 'priority is null';
            } else {
                $replace = preg_replace('/(^,)|(,$)/', '', $args['data']['priorities']);
                $replace = preg_replace('/(,,)/', ',', $replace);
                if ($replace != $args['data']['priorities']) {
                    $where[] = '(priority is null OR priority in (?))';
                } else {
                    $where[] = 'priority in (?)';
                }
                $queryData[] = explode(',', $replace);
            }
        }
        if (isset($args['data']['categories'])) {
            if (empty($args['data']['categories'])) {
                $where[] = 'category_id is null';
            } else {
                $replace = preg_replace('/(^,)|(,$)/', '', $args['data']['categories']);
                $replace = preg_replace('/(,,)/', ',', $replace);
                if ($replace != $args['data']['categories']) {
                    $where[] = '(category_id is null OR category_id in (?))';
                } else {
                    $where[] = 'category_id in (?)';
                }
                $queryData[] = explode(',', $replace);
            }
        }
        if (!empty($args['data']['statuses'])) {
            $where[] = 'status in (?)';
            $queryData[] = explode(',', $args['data']['statuses']);
        }
        if (isset($args['data']['entities'])) {
            if (empty($args['data']['entities'])) {
                $where[] = 'destination is null';
            } else {
                $replace = preg_replace('/(^,)|(,$)/', '', $args['data']['entities']);
                $replace = preg_replace('/(,,)/', ',', $replace);
                if ($replace != $args['data']['entities']) {
                    $where[] = '(destination is null OR destination in (?))';
                } else {
                    $where[] = 'destination in (?)';
                }
                $queryData[] = explode(',', $replace);
            }
        }
        if (isset($args['data']['entitiesChildren'])) {
            if (empty($args['data']['entitiesChildren'])) {
                $where[] = 'destination is null';
            } else {
                $entities = explode(',', $args['data']['entitiesChildren']);
                $entitiesChildren = [];
                foreach ($entities as $entity) {
                    if (!empty($entity)) {
                        $children = EntityModel::getEntityChildren(['entityId' => $entity]);
                        $entitiesChildren = array_merge($entitiesChildren, $children);
                    }
                }
    
                $tmpWhere = [];
    
                $replace = preg_replace('/(^,)|(,$)/', '', $args['data']['entitiesChildren']);
                $replace = preg_replace('/(,,)/', ',', $replace);
                if ($replace != $args['data']['entitiesChildren']) {
                    $tmpWhere[] = 'destination is null';
                }
                if (!empty($entitiesChildren)) {
                    $tmpWhere[] = 'destination in (?)';
                    $queryData[] = $entitiesChildren;
                }
                if (!empty($tmpWhere)) {
                    $where[] = '(' . implode(' or ', $tmpWhere) . ')';
                }
            }
        }
        if (!empty($args['data']['doctypes'])) {
            $table[] = 'doctypes';
            $leftJoin[] = 'doctypes.description=res_view_letterbox.type_label';
            $where[] = 'doctypes.type_id in (?)';
            $queryData[] = explode(',', $args['data']['doctypes']);
        }
        if (!empty($args['data']['folders'])) {
            $resourcesInFolders = FolderModel::getWithResources([
                'select' => ['resources_folders.res_id'],
                'where'  => ['resources_folders.folder_id in (?)'],
                'data'   => [explode(',', $args['data']['folders'])]
            ]);
            $resourcesInFolders = array_column($resourcesInFolders, 'res_id');

            $where[] = 'res_id in (?)';
            $queryData[] = $resourcesInFolders;
        }

        if (!empty($args['data']['order']) && strpos($args['data']['order'], 'alt_identifier') !== false) {
            $order = 'order_alphanum(alt_identifier) ' . explode(' ', $args['data']['order'])[1];
        }
        if (!empty($args['data']['order']) && strpos($args['data']['order'], 'dest_user') !== false) {
            $order = '(us.lastname, us.firstname) ' . explode(' ', $args['data']['order'])[1];
            $table[] = '(SELECT firstname, lastname, id from users) AS us';
            $leftJoin[] = 'us.id = res_view_letterbox.dest_user';
        }
        if (!empty($args['data']['order']) && strpos($args['data']['order'], 'priority') !== false) {
            $order = 'priorities.order ' . explode(' ', $args['data']['order'])[1];
            $table[] = 'priorities';
            $leftJoin[] = 'res_view_letterbox.priority = priorities.id';
        }

        return ['table' => $table, 'leftJoin' => $leftJoin, 'where' => $where, 'queryData' => $queryData, 'order' => $order];
    }

    public function getActions(Request $request, Response $response, array $args)
    {
        $errors = ResourceListController::listControl(['groupId' => $args['groupId'], 'userId' => $args['userId'], 'basketId' => $args['basketId'], 'currentUserId' => $GLOBALS['id']]);
        if (!empty($errors['errors'])) {
            return $response->withStatus($errors['code'])->withJson(['errors' => $errors['errors']]);
        }

        $basket = BasketModel::getById(['id' => $args['basketId'], 'select' => ['basket_clause', 'basket_res_order', 'basket_name', 'basket_id']]);
        $group = GroupModel::getById(['id' => $args['groupId'], 'select' => ['group_id']]);

        $queryParams = $request->getQueryParams();

        if (!empty($queryParams['resId'])) {
            $usedIn = 'used_in_action_page';
        } else {
            $usedIn = 'used_in_basketlist';
        }

        $rawActions = ActionGroupBasketModel::get([
            'select'    => ['id_action', 'default_action_list', 'where_clause'],
            'where'     => ['basket_id = ?', 'group_id = ?', "{$usedIn} = ?"],
            'data'      => [$basket['basket_id'], $group['group_id'], 'Y']
        ]);

        $actions = [];
        $actionsClauses = [];
        $defaultAction = 0;
        foreach ($rawActions as $rawAction) {
            if ($rawAction['default_action_list'] == 'Y') {
                $defaultAction = $rawAction['id_action'];
            }
            $actions[] = $rawAction['id_action'];
            $actionsClauses[$rawAction['id_action']] = $rawAction['where_clause'];
        }

        if (!empty($actions)) {
            $actions = ActionModel::get(['select' => ['id', 'label_action', 'component'], 'where' => ['id in (?)'], 'data' => [$actions], 'orderBy' => ["id = {$defaultAction} DESC",'label_action']]);
            foreach ($actions as $key => $action) {
                if (!empty($queryParams['resId'])) {
                    if (!empty($actionsClauses[$action['id']])) {
                        $whereClause = PreparedClauseController::getPreparedClause(['clause' => $actionsClauses[$action['id']], 'userId' => $GLOBALS['id']]);
                        $ressource = ResModel::getOnView(['select' => [1], 'where' => ['res_id = ?', $whereClause], 'data' => [$queryParams['resId']]]);
                        if (empty($ressource)) {
                            unset($actions[$key]);
                            continue;
                        }
                    }
                    $categoriesList = ActionModel::getCategoriesById(['id' => $action['id']]);
                    if (!empty($categoriesList)) {
                        $actions[$key]['categories'] = array_column($categoriesList, 'category_id');
                    } else {
                        $categories = ResModel::getCategories();
                        $actions[$key]['categories'] = array_column($categories, 'id');
                    }
                }
                $actions[$key]['label'] = $action['label_action'];
                unset($actions[$key]['label_action']);
            }
        }

        return $response->withJson(['actions' => array_values($actions)]);
    }

    public function setAction(Request $request, Response $response, array $aArgs)
    {
        $body = $request->getParsedBody();
        if (!Validator::arrayType()->notEmpty()->validate($body['resources'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Data resources is empty or not an array']);
        }
        $body['resources'] = array_unique($body['resources']);
        $body['resources'] = array_slice($body['resources'], 0, 500);

        $errors = ResourceListController::listControl(['groupId' => $aArgs['groupId'], 'userId' => $aArgs['userId'], 'basketId' => $aArgs['basketId'], 'currentUserId' => $GLOBALS['id']]);
        if (!empty($errors['errors'])) {
            return $response->withStatus($errors['code'])->withJson(['errors' => $errors['errors']]);
        }

        $basket = BasketModel::getById(['id' => $aArgs['basketId'], 'select' => ['basket_clause', 'basket_id', 'basket_name']]);
        $group = GroupModel::getById(['id' => $aArgs['groupId'], 'select' => ['group_id']]);
        $actionGroupBasket = ActionGroupBasketModel::get([
            'select'    => [1],
            'where'     => ['basket_id = ?', 'group_id = ?', 'id_action = ?'],
            'data'      => [$basket['basket_id'], $group['group_id'], $aArgs['actionId']]
        ]);
        if (empty($actionGroupBasket)) {
            return $response->withStatus(400)->withJson(['errors' => 'Action is not linked to this group basket']);
        }

        $action = ActionModel::getById(['id' => $aArgs['actionId'], 'select' => ['id', 'component', 'parameters', 'label_action']]);
        if (empty($action['component'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Action component does not exist']);
        }
        if (!array_key_exists($action['component'], ActionMethodController::COMPONENTS_ACTIONS)) {
            return $response->withStatus(400)->withJson(['errors' => 'Action method does not exist']);
        }
        $action['parameters']   = json_decode($action['parameters'], true);
        $actionRequiredFields   = $action['parameters']['requiredFields'] ?? [];
        $fillRequiredFields     = $action['parameters']['fillRequiredFields'] ?? [];

        $whereClause = PreparedClauseController::getPreparedClause(['clause' => $basket['basket_clause'], 'userId' => $aArgs['userId']]);
        $resources = ResModel::getOnView([
            'select'    => ['res_id', 'locker_user_id', 'locker_time'],
            'where'     => [$whereClause, 'res_view_letterbox.res_id in (?)'],
            'data'      => [$body['resources']]
        ]);

        $resourcesInBasket = array_column($resources, 'res_id');

        if (!empty(array_diff($body['resources'], $resourcesInBasket))) {
            return $response->withStatus(403)->withJson(['errors' => 'Resources out of perimeter']);
        }

        $resourcesForAction = [];
        foreach ($resources as $resource) {
            $lock = true;
            if (empty($resource['locker_user_id']) || empty($resource['locker_time'])) {
                $lock = false;
            } elseif ($resource['locker_user_id'] == $GLOBALS['id']) {
                $lock = false;
            } elseif (strtotime($resource['locker_time']) < time()) {
                $lock = false;
            }
            if (!$lock) {
                $resourcesForAction[] = $resource['res_id'];
            }
        }

        if (empty($resourcesForAction)) {
            return $response->withJson(['success' => 'No resource to process']);
        }

        $body['data'] = empty($body['data']) ? [] : $body['data'];
        $body['note'] = empty($body['note']) ? [] : $body['note'];

        $method          = ActionMethodController::COMPONENTS_ACTIONS[$action['component']];
        $methodResponses = [];
        foreach ($resourcesForAction as $key => $resId) {
            if (!empty($actionRequiredFields)) {
                $requiredFieldsValid = ActionController::checkRequiredFields(['resId' => $resId, 'actionRequiredFields' => $actionRequiredFields]);
                if (!empty($requiredFieldsValid['errors'])) {
                    if (empty($methodResponses['errors'])) {
                        $methodResponses['errors'] = [];
                    }
                    $methodResponses['errors'] = array_merge($methodResponses['errors'], [$requiredFieldsValid['errors']]);
                    continue;
                }
            }
            if (!empty($fillRequiredFields)) {
                $replaceFieldsData = ActionController::replaceFieldsData(['resId' => $resId, 'fillRequiredFields' => $fillRequiredFields]);
                if (!empty($replaceFieldsData['errors'])) {
                    if (empty($methodResponses['errors'])) {
                        $methodResponses['errors'] = [];
                    }
                    $methodResponses['errors'] = array_merge($methodResponses['errors'], [$replaceFieldsData['errors']]);
                    continue;
                }
            }
            $control = ResourceListController::controlFingerprints(['resId' => $resId]);
            if (!$control) {
                if (empty($methodResponses['errors'])) {
                    $methodResponses['errors'] = [];
                }
                $methodResponses['errors'] = array_merge($methodResponses['errors'], ['Fingerprints do not match for resource ' . $resId]);
                continue;
            }

            if (!empty($method)) {
                $methodResponse = ActionMethodController::$method(['resId' => $resId, 'data' => $body['data'], 'note' => $body['note'], 'action' => $action, 'resources' => $resourcesForAction, 'userId' => $aArgs['userId']]);

                if (!empty($methodResponse['errors'])) {
                    if (empty($methodResponses['errors'])) {
                        $methodResponses['errors'] = [];
                    }
                    $methodResponses['errors'] = array_merge($methodResponses['errors'], $methodResponse['errors']);
                    unset($resourcesForAction[$key]);
                }
                if (!empty($methodResponse['data'])) {
                    if (empty($methodResponses['data'])) {
                        $methodResponses['data'] = [];
                    }
                    $methodResponses['data'] = array_merge($methodResponses['data'], $methodResponse['data']);
                }
            }
        }
        $historic = empty($methodResponse['history']) ? '' : $methodResponse['history'];
        if (!empty($resourcesForAction)) {
            ActionMethodController::terminateAction(['id' => $aArgs['actionId'], 'resources' => $resourcesForAction, 'basketName' => $basket['basket_name'], 'note' => $body['note'], 'history' => $historic, 'finishInScript' => !empty($methodResponse['postscript'])]);
        }

        if (!empty($methodResponse['postscript'])) {
            $base64Args = base64_encode(json_encode($methodResponse['args']));
            exec("php {$methodResponse['postscript']} --encodedData {$base64Args} > /dev/null &");
            unset($methodResponse['postscript']);
        }

        if (!empty($methodResponses)) {
            return $response->withJson($methodResponses);
        }

        return $response->withStatus(204);
    }

    public function lock(Request $request, Response $response, array $aArgs)
    {
        $body = $request->getParsedBody();
        if (!Validator::arrayType()->notEmpty()->validate($body['resources'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Data resources is empty or not an array']);
        }
        $body['resources'] = array_slice($body['resources'], 0, 500);

        $errors = ResourceListController::listControl(['groupId' => $aArgs['groupId'], 'userId' => $aArgs['userId'], 'basketId' => $aArgs['basketId'], 'currentUserId' => $GLOBALS['id']]);
        if (!empty($errors['errors'])) {
            return $response->withStatus($errors['code'])->withJson(['errors' => $errors['errors']]);
        }

        $basket = BasketModel::getById(['id' => $aArgs['basketId'], 'select' => ['basket_clause']]);

        $whereClause = PreparedClauseController::getPreparedClause(['clause' => $basket['basket_clause'], 'userId' => $aArgs['userId']]);
        $resources = ResModel::getOnView([
            'select'    => ['res_id', 'locker_user_id', 'locker_time'],
            'where'     => [$whereClause, 'res_view_letterbox.res_id in (?)'],
            'data'      => [$body['resources']]
        ]);

        $resourcesInBasket = array_column($resources, 'res_id');

        if (!empty(array_diff($body['resources'], $resourcesInBasket))) {
            return $response->withStatus(403)->withJson(['errors' => 'Resources out of perimeter']);
        }

        $resourcesToLock = [];
        $lockersId = [];
        foreach ($resources as $resource) {
            $lock = true;
            if (empty($resource['locker_user_id']) || empty($resource['locker_time'])) {
                $lock = false;
            } elseif ($resource['locker_user_id'] == $GLOBALS['id']) {
                $lock = false;
            } elseif (strtotime($resource['locker_time']) < time()) {
                $lock = false;
            }

            if (!$lock) {
                $resourcesToLock[] = $resource['res_id'];
            } else {
                $lockersId[] = $resource['locker_user_id'];
            }
        }

        if (!empty($resourcesToLock)) {
            ResModel::update([
                'set'   => ['locker_user_id' => $GLOBALS['id'], 'locker_time' => 'CURRENT_TIMESTAMP + interval \'1\' MINUTE'],
                'where' => ['res_id in (?)'],
                'data'  => [$resourcesToLock]
            ]);
        }

        return $response->withStatus(204);
    }

    public function unlock(Request $request, Response $response, array $aArgs)
    {
        $body = $request->getParsedBody();
        if (!Validator::arrayType()->notEmpty()->validate($body['resources'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Data resources is empty or not an array']);
        }
        $body['resources'] = array_slice($body['resources'], 0, 500);

        $errors = ResourceListController::listControl(['groupId' => $aArgs['groupId'], 'userId' => $aArgs['userId'], 'basketId' => $aArgs['basketId'], 'currentUserId' => $GLOBALS['id']]);
        if (!empty($errors['errors'])) {
            return $response->withStatus($errors['code'])->withJson(['errors' => $errors['errors']]);
        }

        $basket = BasketModel::getById(['id' => $aArgs['basketId'], 'select' => ['basket_clause']]);

        $whereClause = PreparedClauseController::getPreparedClause(['clause' => $basket['basket_clause'], 'userId' => $aArgs['userId']]);
        $resources = ResModel::getOnView([
            'select'    => ['res_id', 'locker_user_id', 'locker_time'],
            'where'     => [$whereClause, 'res_view_letterbox.res_id in (?)'],
            'data'      => [$body['resources']]
        ]);

        $resourcesInBasket = array_column($resources, 'res_id');

        if (!empty(array_diff($body['resources'], $resourcesInBasket))) {
            return $response->withStatus(403)->withJson(['errors' => 'Resources out of perimeter']);
        }

        $resourcesToUnlock = [];
        foreach ($resources as $resource) {
            if (!(!empty($resource['locker_user_id']) && $resource['locker_user_id'] != $GLOBALS['id'] && strtotime($resource['locker_time']) > time())) {
                $resourcesToUnlock[] = $resource['res_id'];
            }
        }

        if (!empty($resourcesToUnlock)) {
            ResModel::update([
                'set'   => ['locker_user_id' => null, 'locker_time' => null],
                'where' => ['res_id in (?)'],
                'data'  => [$resourcesToUnlock]
            ]);
        }

        return $response->withStatus(204);
    }

    public function areLocked(Request $request, Response $response, array $args)
    {
        $body = $request->getParsedBody();
        if (!Validator::arrayType()->notEmpty()->validate($body['resources'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Data resources is empty or not an array']);
        }
        $body['resources'] = array_slice($body['resources'], 0, 500);

        $errors = ResourceListController::listControl(['groupId' => $args['groupId'], 'userId' => $args['userId'], 'basketId' => $args['basketId'], 'currentUserId' => $GLOBALS['id']]);
        if (!empty($errors['errors'])) {
            return $response->withStatus($errors['code'])->withJson(['errors' => $errors['errors']]);
        }

        $basket = BasketModel::getById(['id' => $args['basketId'], 'select' => ['basket_clause']]);

        $whereClause = PreparedClauseController::getPreparedClause(['clause' => $basket['basket_clause'], 'userId' => $args['userId']]);
        $resources = ResModel::getOnView([
            'select'    => ['res_id', 'locker_user_id', 'locker_time'],
            'where'     => [$whereClause, 'res_view_letterbox.res_id in (?)'],
            'data'      => [$body['resources']]
        ]);

        $resourcesInBasket = array_column($resources, 'res_id');
        if (!empty(array_diff($body['resources'], $resourcesInBasket))) {
            return $response->withStatus(403)->withJson(['errors' => 'Resources out of perimeter']);
        }

        $locked = 0;
        $resourcesToLock = [];
        $lockersId = [];
        foreach ($resources as $resource) {
            $lock = true;
            if (empty($resource['locker_user_id']) || empty($resource['locker_time'])) {
                $lock = false;
            } elseif ($resource['locker_user_id'] == $GLOBALS['id']) {
                $lock = false;
            } elseif (strtotime($resource['locker_time']) < time()) {
                $lock = false;
            }

            if (!$lock) {
                $resourcesToLock[] = $resource['res_id'];
            } else {
                $lockersId[] = $resource['locker_user_id'];
                ++$locked;
            }
        }

        $lockers = [];
        if (!empty($lockersId)) {
            $lockersId = array_unique($lockersId);
            foreach ($lockersId as $lockerId) {
                $lockers[] = UserModel::getLabelledUserById(['id' => $lockerId]);
            }
        }

        return $response->withJson(['countLockedResources' => $locked, 'lockers' => $lockers, 'resourcesToProcess' => $resourcesToLock]);
    }

    public static function listControl(array $aArgs)
    {
        ValidatorModel::notEmpty($aArgs, ['groupId', 'userId', 'basketId', 'currentUserId']);
        ValidatorModel::intVal($aArgs, ['groupId', 'userId', 'basketId', 'currentUserId']);

        $group = GroupModel::getById(['id' => $aArgs['groupId'], 'select' => ['group_id']]);
        $basket = BasketModel::getById(['id' => $aArgs['basketId'], 'select' => ['basket_id', 'basket_clause', 'basket_res_order', 'basket_name']]);
        if (empty($group) || empty($basket)) {
            return ['errors' => 'Group or basket does not exist', 'code' => 403];
        }

        if ($aArgs['userId'] == $aArgs['currentUserId']) {
            $redirectedBasket = RedirectBasketModel::get([
                'select'    => [1],
                'where'     => ['owner_user_id = ?', 'basket_id = ?', 'group_id = ?'],
                'data'      => [$aArgs['userId'], $basket['basket_id'], $aArgs['groupId']]
            ]);
            if (!empty($redirectedBasket[0])) {
                return ['errors' => 'Basket out of perimeter (redirected)', 'code' => 403];
            }
        } else {
            $redirectedBasket = RedirectBasketModel::get([
                'select'    => ['actual_user_id'],
                'where'     => ['owner_user_id = ?', 'basket_id = ?', 'group_id = ?'],
                'data'      => [$aArgs['userId'], $basket['basket_id'], $aArgs['groupId']]
            ]);
            if (empty($redirectedBasket[0]) || $redirectedBasket[0]['actual_user_id'] != $aArgs['currentUserId']) {
                return ['errors' => 'Basket out of perimeter', 'code' => 403];
            }
        }

        $groups = UserModel::getGroupsById(['id' => $aArgs['userId']]);
        $groups = array_column($groups, 'id');
        if (!in_array($aArgs['groupId'], $groups)) {
            return ['errors' => 'Group is not linked to this user', 'code' => 403];
        }

        $isBasketLinked = GroupBasketModel::get(['select' => [1], 'where' => ['basket_id = ?', 'group_id = ?'], 'data' => [$basket['basket_id'], $group['group_id']]]);
        if (empty($isBasketLinked)) {
            return ['errors' => 'Group is not linked to this basket', 'code' => 403];
        }

        return ['success' => 'success'];
    }

    private static function getAssignee(array $args)
    {
        ValidatorModel::notEmpty($args, ['resId']);
        ValidatorModel::intVal($args, ['resId']);

        $res = ResModel::getById(['select' => ['destination'], 'resId' => $args['resId']]);
        $listInstances = ListInstanceModel::get([
            'select'    => ['item_id'],
            'where'     => ['difflist_type = ?', 'res_id = ?', 'item_mode = ?'],
            'data'      => ['entity_id', $args['resId'], 'dest']
        ]);

        $assignee = '';
        if (!empty($listInstances[0])) {
            $assignee .= UserModel::getLabelledUserById(['id' => $listInstances[0]['item_id']]);
        }
        if (!empty($res['destination'])) {
            $entityLabel = EntityModel::getByEntityId(['select' => ['entity_label'], 'entityId' => $res['destination']]);
            $assignee .= (empty($assignee) ? "({$entityLabel['entity_label']})" : " ({$entityLabel['entity_label']})");
        }

        return $assignee;
    }

    private static function getVisaWorkflow(array $args)
    {
        ValidatorModel::notEmpty($args, ['resId']);
        ValidatorModel::intVal($args, ['resId']);

        $listInstances = ListInstanceModel::get([
            'select'    => ['item_id', 'requested_signature', 'process_date'],
            'where'     => ['difflist_type = ?', 'res_id = ?'],
            'data'      => ['VISA_CIRCUIT', $args['resId']],
            'orderBy'   => ['listinstance_id']
        ]);

        $users = [];
        $currentFound = false;
        foreach ($listInstances as $listInstance) {
            $users[] = [
                'user'      => UserModel::getLabelledUserById(['id' => $listInstance['item_id']]),
                'mode'      => $listInstance['requested_signature'] ? 'sign' : 'visa',
                'date'      => TextFormatModel::formatDate($listInstance['process_date']),
                'current'   => empty($listInstance['process_date']) && !$currentFound
            ];
            if (empty($listInstance['process_date']) && !$currentFound) {
                $currentFound = true;
            }
        }

        return $users;
    }

    private static function getSignatories(array $args)
    {
        ValidatorModel::notEmpty($args, ['resId']);
        ValidatorModel::intVal($args, ['resId']);

        $listInstances = ListInstanceModel::get([
            'select'    => ['item_id', 'process_date'],
            'where'     => ['difflist_type = ?', 'res_id = ?' ,'requested_signature = ?'],
            'data'      => ['VISA_CIRCUIT', $args['resId'], true],
            'orderBy'   => ['listinstance_id']
        ]);

        $users = [];
        foreach ($listInstances as $listInstance) {
            $users[] = [
                'user'      => UserModel::getLabelledUserById(['id' => $listInstance['item_id']]),
                'date'      => TextFormatModel::formatDate($listInstance['process_date']),
            ];
        }

        return $users;
    }

    private static function getParallelOpinionsNumber(array $args)
    {
        ValidatorModel::notEmpty($args, ['resId']);
        ValidatorModel::intVal($args, ['resId']);

        $notes = NoteModel::get(['select' => ['count(1)'], 'where' => ['identifier = ?', 'note_text like ?'], 'data' => [$args['resId'], '[avis%']]);

        return $notes[0]['count'];
    }

    private static function getFolders(array $args)
    {
        ValidatorModel::notEmpty($args, ['resId', 'userId']);
        ValidatorModel::intVal($args, ['resId', 'userId']);

        $entities = UserModel::getEntitiesById(['id' => $args['userId'], 'select' => ['entities.id']]);
        $entities = array_column($entities, 'id');

        if (empty($entities)) {
            $entities = [0];
        }

        $folders = FolderModel::getWithEntitiesAndResources([
            'select'    => ['DISTINCT(folders.id)', 'folders.label'],
            'where'     => ['res_id = ?', '(user_id = ? OR entity_id in (?) OR keyword = ?)'],
            'data'      => [$args['resId'], $args['userId'], $entities, 'ALL_ENTITIES']
        ]);

        return $folders;
    }

    public static function getIdsWithOffsetAndLimit(array $args)
    {
        ValidatorModel::arrayType($args, ['resources']);
        ValidatorModel::intVal($args, ['offset', 'limit']);

        $ids = [];
        if (!empty($args['resources'][$args['offset']])) {
            $start = $args['offset'];
            $i = 0;
            while ($i < $args['limit'] && !empty($args['resources'][$start])) {
                $ids[] = $args['resources'][$start]['res_id'];
                ++$start;
                ++$i;
            }
        }

        return $ids;
    }

    public static function getFormattedResources(array $args)
    {
        ValidatorModel::notEmpty($args, ['resources', 'userId']);
        ValidatorModel::arrayType($args, ['resources', 'attachments', 'listDisplay']);
        ValidatorModel::intVal($args, ['userId']);
        ValidatorModel::boolType($args, ['checkLocked']);

        $formattedResources = [];

        $resources   = $args['resources'];
        $attachments = $args['attachments'];

        $customFields       = CustomFieldModel::get(['select' => ['id', 'type', 'label']]);
        $customFieldsLabels = array_column($customFields, 'label', 'id');
        $customFields       = array_column($customFields, 'type', 'id');

        foreach ($resources as $key => $resource) {
            $formattedResources[$key]['resId']              = $resource['res_id'];
            $formattedResources[$key]['chrono']             = $resource['alt_identifier'];
            $formattedResources[$key]['barcode']            = $resource['barcode'] ?? null;
            $formattedResources[$key]['subject']            = $resource['subject'];
            $formattedResources[$key]['confidentiality']    = $resource['confidentiality'] ?? null;
            $formattedResources[$key]['statusLabel']        = $resource['status.label_status'];
            $formattedResources[$key]['statusImage']        = $resource['status.img_filename'];
            $formattedResources[$key]['priorityColor']      = $resource['priorities.color'];
            $formattedResources[$key]['closing_date']       = $resource['closing_date'] ?? null;
            $formattedResources[$key]['countAttachments']   = 0;
            $formattedResources[$key]['hasDocument']        = $resource['res_filename'] != null;
            $formattedResources[$key]['mailTracking']       = in_array($resource['res_id'], $args['trackedMails']);
            $formattedResources[$key]['integrations']       = !empty($resource['integrations']) ? json_decode($resource['integrations'], true) : [];
            $formattedResources[$key]['retentionFrozen']    = $resource['retention_frozen'];
            $formattedResources[$key]['binding']            = $resource['binding'];
            foreach ($attachments as $attachment) {
                if ($attachment['res_id_master'] == $resource['res_id']) {
                    $formattedResources[$key]['countAttachments'] = $attachment['count'];
                    break;
                }
            }
            $formattedResources[$key]['countNotes'] = NoteModel::countByResId(['resId' => [$resource['res_id']], 'userId' => $args['userId']])[$resource['res_id']];
            $acknowledgementReceipts = count(AcknowledgementReceiptModel::get([
                'select' => [1],
                'where'  => ['res_id = ?'],
                'data'   => [$resource['res_id']]
            ]));
            $messagesExchange = count(MessageExchangeModel::get([
                'select' => [1],
                'where'  => ['res_id_master = ?', "(type = 'ArchiveTransfer' or reference like '%_ReplySent')"],
                'data'   => [$resource['res_id']]
            ]));
            $shippings = count(ShippingModel::get([
                'select' => [1],
                'where'  => ['document_id = ? and document_type = ?'],
                'data'   => [$resource['res_id'], 'resource']
            ]));
            $emails = count(EmailModel::get([
                'select' => [1],
                'where'  => ["document->>'id' = ?", "(status != 'DRAFT' or (status = 'DRAFT' and user_id = ?))"],
                'data'   => [$resource['res_id'], $args['userId']],
            ]));
            $formattedResources[$key]['countSentResources'] = $acknowledgementReceipts + $messagesExchange + $shippings + $emails;

            if (!empty($args['checkLocked'])) {
                $isLocked = true;
                if (empty($resource['locker_user_id']) || empty($resource['locker_time'])) {
                    $isLocked = false;
                } elseif ($resource['locker_user_id'] == $args['userId']) {
                    $isLocked = false;
                } elseif (strtotime($resource['locker_time']) < time()) {
                    $isLocked = false;
                }
                if ($isLocked) {
                    $formattedResources[$key]['locker'] = UserModel::getLabelledUserById(['id' => $resource['locker_user_id']]);
                }
                $formattedResources[$key]['isLocked'] = $isLocked;
            }

            if (isset($args['listDisplay'])) {
                $display = [];
                $listDisplayValues = array_column($args['listDisplay'], 'value');
                if (in_array('getRegisteredMailRecipient', $listDisplayValues) || in_array('getRegisteredMailReference', $listDisplayValues)
                    || in_array('getRegisteredMailIssuingSite', $listDisplayValues)) {
                    $registeredMail = RegisteredMailModel::getByResId(['resId' => $resource['res_id'], 'select' => ['issuing_site', 'recipient', 'reference']]);
                }
                if (!empty($args['listDisplay'])) {
                    if ($args['listDisplay'][0] !== 'folders') {
                        foreach ($args['listDisplay'] as $value) {
                            $value = (array)$value;
                            if ($value['value'] == 'getPriority') {
                                $value['displayValue'] = $resource['priorities.label'];
                                $display[] = $value;
                            } elseif ($value['value'] == 'getCategory') {
                                $value['displayValue'] = $resource['category_id'];
                                $display[] = $value;
                            } elseif ($value['value'] == 'getDoctype') {
                                $value['displayValue'] = $resource['doctypes.description'];
                                $display[] = $value;
                            } elseif ($value['value'] == 'getAssignee') {
                                $value['displayValue'] = ResourceListController::getAssignee(['resId' => $resource['res_id']]);
                                $display[] = $value;
                            } elseif ($value['value'] == 'getSenders') {
                                $value['displayValue'] = ContactController::getFormattedContacts(['resId' => $resource['res_id'], 'mode' => 'sender', 'onlyContact' => true]);
                                $display[] = $value;
                            } elseif ($value['value'] == 'getRecipients') {
                                $value['displayValue'] = ContactController::getFormattedContacts(['resId' => $resource['res_id'], 'mode' => 'recipient', 'onlyContact' => true]);
                                $display[] = $value;
                            } elseif ($value['value'] == 'getVisaWorkflow') {
                                $value['displayValue'] = ResourceListController::getVisaWorkflow(['resId' => $resource['res_id']]);
                                $display[] = $value;
                            } elseif ($value['value'] == 'getSignatories') {
                                $value['displayValue'] = ResourceListController::getSignatories(['resId' => $resource['res_id']]);
                                $display[] = $value;
                            } elseif ($value['value'] == 'getParallelOpinionsNumber') {
                                $value['displayValue'] = ResourceListController::getParallelOpinionsNumber(['resId' => $resource['res_id']]);
                                $display[] = $value;
                            } elseif ($value['value'] == 'getCreationAndProcessLimitDates') {
                                $value['displayValue'] = ['creationDate' => $resource['creation_date'], 'processLimitDate' => $resource['process_limit_date']];
                                $display[] = $value;
                            } elseif ($value['value'] == 'getCreationDate') {
                                $value['displayValue'] = $resource['creation_date'];
                                $display[] = $value;
                            } elseif ($value['value'] == 'getProcessLimitDate') {
                                $value['displayValue'] = $resource['process_limit_date'];
                                $display[] = $value;
                            } elseif ($value['value'] == 'getModificationDate') {
                                $value['displayValue'] = $resource['modification_date'];
                                $display[] = $value;
                            } elseif ($value['value'] == 'getOpinionLimitDate') {
                                $value['displayValue'] = $resource['opinion_limit_date'];
                                $display[] = $value;
                            } elseif ($value['value'] == 'getResId') {
                                $value['displayValue'] = $resource['res_id'];
                                $display[] = $value;
                            } elseif ($value['value'] == 'getBarcode') {
                                $value['displayValue'] = $resource['barcode'];
                                $display[] = $value;
                            } elseif ($value['value'] == 'getRegisteredMailRecipient') {
                                if (!empty($registeredMail)) {
                                    $recipient = json_decode($registeredMail['recipient'], true);
                                    if (!empty($recipient['company']) && (!empty($recipient['firstname']) || !empty($recipient['lastname']))) {
                                        $recipient = $recipient['firstname'] . ' ' . $recipient['lastname'] . ' (' . $recipient['company'] . ')';
                                    } elseif (empty($recipient['company']) && (!empty($recipient['firstname']) || !empty($recipient['lastname']))) {
                                        $recipient = $recipient['firstname'] . ' ' . $recipient['lastname'];
                                    } elseif (!empty($recipient['company']) && empty($recipient['firstname']) && empty($recipient['lastname'])) {
                                        $recipient = $recipient['company'];
                                    }
                                    $value['displayValue'] = $recipient;
                                } else {
                                    $value['displayValue'] = '';
                                }
                                $display[] = $value;
                            } elseif ($value['value'] == 'getRegisteredMailReference') {
                                $value['displayValue'] = !empty($registeredMail) ? $registeredMail['reference'] : '';
                                $display[] = $value;
                            } elseif ($value['value'] == 'getRegisteredMailIssuingSite') {
                                if (!empty($registeredMail)) {
                                    $site = IssuingSiteModel::getById(['id' => $registeredMail['issuing_site'], 'select' => ['label']]);
                                    $value['displayValue'] = $site['label'];
                                } else {
                                    $value['displayValue'] = '';
                                }
                                $display[] = $value;
                            } elseif (strpos($value['value'], 'indexingCustomField_') !== false) {
                                $customId = explode('_', $value['value'])[1];
                                $customValue = json_decode($resource['custom_fields'] ?? '{}', true);
    
                                $value['displayLabel'] = $customFieldsLabels[$customId] ?? '';
                                if ($customFields[$customId] == 'contact' && !empty($customValue[$customId])) {
                                    $value['displayValue'] = ContactController::getContactCustomField(['contacts' => $customValue[$customId], 'onlyContact' => true]);
                                } elseif ($customFields[$customId] == 'banAutocomplete' && !empty($customValue[$customId])) {
                                    $value['displayValue'] = $customValue[$customId][0]['addressNumber'] ?? '';
                                    $value['displayValue'] .= ' ';
                                    $value['displayValue'] .= $customValue[$customId][0]['addressStreet'] ?? '';
                                    $value['displayValue'] .= ' ';
                                    $value['displayValue'] .= $customValue[$customId][0]['addressTown'] ?? '';
                                } elseif ($customFields[$customId] == 'date' && !empty($customValue[$customId])) {
                                    $value['displayValue'] = TextFormatModel::formatDate($customValue[$customId], 'd-m-Y');
                                } elseif ($customFields[$customId] == 'checkbox' && !empty($customValue[$customId])) {
                                    $value['displayValue'] = implode(', ', $customValue[$customId]);
                                } else {
                                    $value['displayValue'] = $customValue[$customId] ?? '';
                                }
                                $display[] = $value;
                            }
                        }
                    }
                }
                $formattedResources[$key]['folders'] = ResourceListController::getFolders(['resId' => $resource['res_id'], 'userId' => $args['userId']]);
                $formattedResources[$key]['display'] = $display;
            }
        }

        return $formattedResources;
    }

    public static function getFormattedFilters(array $args)
    {
        ValidatorModel::notEmpty($args, ['where']);
        ValidatorModel::arrayType($args, ['where', 'queryData', 'queryParams']);

        $data = $args['queryParams'];
        $where = $args['where'];
        $queryData = $args['queryData'];

        if (!empty($data['delayed']) && $data['delayed'] == 'true') {
            $where[] = 'process_limit_date < CURRENT_TIMESTAMP';
        }
        if (!empty($data['search']) && mb_strlen($data['search']) >= 2) {
            $where[] = '(alt_identifier ilike ? OR unaccent(subject) ilike unaccent(?::text))';
            $queryData[] = "%{$data['search']}%";
            $queryData[] = "%{$data['search']}%";
        }

        $wherePriorities = $where;
        $whereCategories = $where;
        $whereStatuses   = $where;
        $whereEntities   = $where;
        $whereDocTypes   = $where;
        $whereFolders    = $where;
        $dataPriorities  = $queryData;
        $dataCategories  = $queryData;
        $dataStatuses    = $queryData;
        $dataEntities    = $queryData;
        $dataDocTypes    = $queryData;
        $dataFolders     = $queryData;

        if (isset($data['priorities'])) {
            if (empty($data['priorities'])) {
                $tmpWhere = 'priority is null';
            } else {
                $replace = preg_replace('/(^,)|(,$)/', '', $data['priorities']);
                $replace = preg_replace('/(,,)/', ',', $replace);
                if ($replace != $data['priorities']) {
                    $tmpWhere = '(priority is null OR priority in (?))';
                } else {
                    $tmpWhere = 'priority in (?)';
                }
                $dataCategories[] = explode(',', $replace);
                $dataStatuses[]   = explode(',', $replace);
                $dataEntities[]   = explode(',', $replace);
                $dataDocTypes[]   = explode(',', $replace);
                $dataFolders[]    = explode(',', $replace);
            }

            $whereCategories[] = $tmpWhere;
            $whereStatuses[]   = $tmpWhere;
            $whereEntities[]   = $tmpWhere;
            $whereDocTypes[]   = $tmpWhere;
            $whereFolders[]    = $tmpWhere;
        }
        if (isset($data['categories'])) {
            if (empty($data['categories'])) {
                $tmpWhere = 'category_id is null';
            } else {
                $replace = preg_replace('/(^,)|(,$)/', '', $data['categories']);
                $replace = preg_replace('/(,,)/', ',', $replace);
                if ($replace != $data['categories']) {
                    $tmpWhere = '(category_id is null OR category_id in (?))';
                } else {
                    $tmpWhere = 'category_id in (?)';
                }
                $dataPriorities[] = explode(',', $replace);
                $dataStatuses[]   = explode(',', $replace);
                $dataEntities[]   = explode(',', $replace);
                $dataDocTypes[]   = explode(',', $replace);
                $dataFolders[]    = explode(',', $replace);
            }

            $wherePriorities[] = $tmpWhere;
            $whereStatuses[]   = $tmpWhere;
            $whereEntities[]   = $tmpWhere;
            $whereDocTypes[]   = $tmpWhere;
            $whereFolders[]    = $tmpWhere;
        }
        if (!empty($data['statuses'])) {
            $wherePriorities[] = 'status in (?)';
            $dataPriorities[]  = explode(',', $data['statuses']);
            $whereCategories[] = 'status in (?)';
            $dataCategories[]  = explode(',', $data['statuses']);
            $whereEntities[]   = 'status in (?)';
            $dataEntities[]    = explode(',', $data['statuses']);
            $whereDocTypes[]   = 'status in (?)';
            $dataDocTypes[]    = explode(',', $data['statuses']);
            $whereFolders[]    = 'status in (?)';
            $dataFolders[]     = explode(',', $data['statuses']);
        }
        if (!empty($data['doctypes'])) {
            $wherePriorities[] = 'type_id in (?)';
            $dataPriorities[]  = explode(',', $data['doctypes']);
            $whereCategories[] = 'type_id in (?)';
            $dataCategories[]  = explode(',', $data['doctypes']);
            $whereEntities[]   = 'type_id in (?)';
            $dataEntities[]    = explode(',', $data['doctypes']);
            $whereStatuses[]   = 'type_id in (?)';
            $dataStatuses[]    = explode(',', $data['doctypes']);
            $whereFolders[]    = 'type_id in (?)';
            $dataFolders[]     = explode(',', $data['doctypes']);
        }
        if (isset($data['entities'])) {
            if (empty($data['entities'])) {
                $tmpWhere = 'destination is null';
            } else {
                $replace = preg_replace('/(^,)|(,$)/', '', $data['entities']);
                $replace = preg_replace('/(,,)/', ',', $replace);
                if ($replace != $data['entities']) {
                    $tmpWhere = '(destination is null OR destination in (?))';
                } else {
                    $tmpWhere = 'destination in (?)';
                }
                $dataPriorities[] = explode(',', $replace);
                $dataCategories[] = explode(',', $replace);
                $dataStatuses[] = explode(',', $replace);
                $dataDocTypes[] = explode(',', $replace);
                $dataFolders[]  = explode(',', $replace);
            }

            $wherePriorities[] = $tmpWhere;
            $whereCategories[] = $tmpWhere;
            $whereStatuses[]   = $tmpWhere;
            $whereDocTypes[]   = $tmpWhere;
            $whereFolders[]    = $tmpWhere;
        }
        if (!empty($data['entitiesChildren'])) {
            $entities = explode(',', $data['entitiesChildren']);
            $entitiesChildren = [];
            foreach ($entities as $entity) {
                $children = EntityModel::getEntityChildren(['entityId' => $entity]);
                $entitiesChildren = array_merge($entitiesChildren, $children);
            }
            if (!empty($entitiesChildren)) {
                $wherePriorities[] = 'destination in (?)';
                $dataPriorities[]  = $entitiesChildren;
                $whereCategories[] = 'destination in (?)';
                $dataCategories[]  = $entitiesChildren;
                $whereStatuses[]   = 'destination in (?)';
                $dataStatuses[]    = $entitiesChildren;
                $whereDocTypes[]   = 'destination in (?)';
                $dataDocTypes[]    = $entitiesChildren;
                $whereFolders[]    = 'destination in (?)';
                $dataFolders[]     = $entitiesChildren;
            }
        }

        if (!empty($data['folders'])) {
            $folders = explode(',', $data['folders']);
            $resIdsInFolders = [];
            foreach ($folders as $folderId) {
                $resources = FolderModel::getWithResources([
                    'select' => ['res_id'],
                    'where'  => ['folder_id in (?)'],
                    'data'   => [$folderId]
                ]);
                $resources = array_column($resources, 'res_id');
                $resIdsInFolders = array_merge($resIdsInFolders, $resources);
            }
            if (!empty($resIdsInFolders)) {
                $wherePriorities[] = 'res_id in (?)';
                $dataPriorities[]  = $resIdsInFolders;
                $whereCategories[] = 'res_id in (?)';
                $dataCategories[]  = $resIdsInFolders;
                $whereEntities[]   = 'res_id in (?)';
                $dataEntities[]    = $resIdsInFolders;
                $whereStatuses[]   = 'res_id in (?)';
                $dataStatuses[]    = $resIdsInFolders;
                $whereDocTypes[]   = 'res_id in (?)';
                $dataDocTypes[]    = $resIdsInFolders;
            }
        }

        $priorities = [];
        $rawPriorities = ResModel::getOnView([
            'select'    => ['count(res_id)', 'priority'],
            'where'     => $wherePriorities,
            'data'      => $dataPriorities,
            'groupBy'   => ['priority']
        ]);

        foreach ($rawPriorities as $value) {
            $priority = null;
            if (!empty($value['priority'])) {
                $priority = PriorityModel::getById(['select' => ['label'], 'id' => $value['priority']]);
            }
            $priorities[] = [
                'id'        => empty($value['priority']) ? null : $value['priority'],
                'label'     => empty($priority['label']) ? '_UNDEFINED' : $priority['label'],
                'count'     => $value['count']
            ];
        }

        $categories = [];
        $allCategories = ResModel::getCategories();
        $rawCategories = ResModel::getOnView([
            'select'    => ['count(res_id)', 'category_id'],
            'where'     => $whereCategories,
            'data'      => $dataCategories,
            'groupBy'   => ['category_id']
        ]);
        foreach ($rawCategories as $value) {
            $label = null;
            if (!empty($value['category_id'])) {
                foreach ($allCategories as $category) {
                    if ($value['category_id'] == $category['id']) {
                        $label = $category['label'];
                    }
                }
            }
            $categories[] = [
                'id'        => empty($value['category_id']) ? null : $value['category_id'],
                'label'     => empty($label) ? '_UNDEFINED' : $label,
                'count'     => $value['count']
            ];
        }

        $statuses = [];
        $rawStatuses = ResModel::getOnView([
            'select'    => ['count(res_id)', 'status'],
            'where'     => $whereStatuses,
            'data'      => $dataStatuses,
            'groupBy'   => ['status']
        ]);
        foreach ($rawStatuses as $value) {
            if (!empty($value['status'])) {
                $status = StatusModel::getById(['select' => ['label_status'], 'id' => $value['status']]);
                $statuses[] = [
                    'id'        => $value['status'],
                    'label'     => empty($status['label_status']) ? '_UNDEFINED' : $status['label_status'],
                    'count'     => $value['count']
                ];
            }
        }

        $entities = [];
        $rawEntities = ResModel::getOnView([
            'select'    => ['count(res_id)', 'destination'],
            'where'     => $whereEntities,
            'data'      => $dataEntities,
            'groupBy'   => ['destination']
        ]);
        foreach ($rawEntities as $value) {
            $entity = null;
            if (!empty($value['destination'])) {
                $entity = EntityModel::getByEntityId(['select' => ['entity_label'], 'entityId' => $value['destination']]);
            }
            $entities[] = [
                'entityId'  => empty($value['destination']) ? null : $value['destination'],
                'label'     => empty($entity['entity_label']) ? '_UNDEFINED' : $entity['entity_label'],
                'count'     => $value['count']
            ];
        }

        $docTypes = [];
        $rawDocType = ResModel::getOnView([
            'select'    => ['count(res_id)', 'type_id', 'type_label'],
            'where'     => $whereDocTypes,
            'data'      => $dataDocTypes,
            'groupBy'   => ['type_id', 'type_label']
        ]);
        foreach ($rawDocType as $value) {
            $docTypes[] = [
                'id'        => empty($value['type_id']) ? null : $value['type_id'],
                'label'     => empty($value['type_label']) ? '_UNDEFINED' : $value['type_label'],
                'count'     => $value['count']
            ];
        }

        $folders = [];

        $resIds = ResModel::getOnView([
            'select' => ['res_id'],
            'where'  => $whereFolders,
            'data'   => $dataFolders
        ]);

        if (!empty($resIds)) {
            $resIds = array_column($resIds, 'res_id');
    
            $userEntities = EntityModel::getWithUserEntities([
                'select' => ['entities.id'],
                'where'  => ['users_entities.user_id = ?'],
                'data'   => [$GLOBALS['id']]
            ]);
            $userEntities = array_column($userEntities, 'id');
    
            $rawFolders = FolderModel::getWithEntitiesAndResources([
                'select'  => ['folders.id', 'folders.label', 'count(resources_folders.res_id) as count'],
                'where'   => ['resources_folders.res_id in (?)', '(folders.user_id = ? OR entities_folders.entity_id in (?) or keyword = ?)'],
                'data'    => [$resIds, $GLOBALS['id'], $userEntities, 'ALL_ENTITIES'],
                'groupBy' => ['folders.id', 'folders.label']
            ]);
            foreach ($rawFolders as $value) {
                $folders[] = [
                    'id'    => empty($value['id']) ? null : $value['id'],
                    'label' => empty($value['label']) ? '_UNDEFINED' : $value['label'],
                    'count' => $value['count']
                ];
            }
        }

        $priorities = (count($priorities) >= 2) ? $priorities : [];
        $categories = (count($categories) >= 2) ? $categories : [];
        $statuses   = (count($statuses) >= 2) ? $statuses : [];
        $entities   = (count($entities) >= 2) ? $entities : [];
        $docTypes   = (count($docTypes) >= 2) ? $docTypes : [];
        $folders    = (count($folders) >= 2) ? $folders : [];

        $entitiesChildren = [];
        foreach ($entities as $entity) {
            if (!empty($entity['entityId'])) {
                $children = EntityModel::getEntityChildren(['entityId' => $entity['entityId']]);
                $count = 0;
                foreach ($entities as $value) {
                    if (in_array($value['entityId'], $children)) {
                        $count += $value['count'];
                    }
                }
            } else {
                $count = $entity['count'];
            }
            $entitiesChildren[] = [
                'entityId'  => $entity['entityId'],
                'label'     => $entity['label'],
                'count'     => $count
            ];
        }

        usort($entities, ['Resource\controllers\ResourceListController', 'compareSortOnLabel']);

        usort($priorities, ['Resource\controllers\ResourceListController', 'compareSortOnLabel']);

        usort($categories, ['Resource\controllers\ResourceListController', 'compareSortOnLabel']);

        usort($statuses, ['Resource\controllers\ResourceListController', 'compareSortOnLabel']);

        usort($entitiesChildren, ['Resource\controllers\ResourceListController', 'compareSortOnLabel']);

        usort($docTypes, ['Resource\controllers\ResourceListController', 'compareSortOnLabel']);

        usort($folders, ['Resource\controllers\ResourceListController', 'compareSortOnLabel']);


        return [
            'entities'         => $entities,
            'priorities'       => $priorities,
            'categories'       => $categories,
            'statuses'         => $statuses,
            'entitiesChildren' => $entitiesChildren,
            'doctypes'         => $docTypes,
            'folders'          => $folders
        ];
    }

    public static function compareSortOnLabel($a, $b)
    {
        if (strtolower($a['label']) < strtolower($b['label'])) {
            return -1;
        } elseif (strtolower($a['label']) > strtolower($b['label'])) {
            return 1;
        }
        return 0;
    }

    public static function controlFingerprints(array $args)
    {
        ValidatorModel::notEmpty($args, ['resId']);
        ValidatorModel::intVal($args, ['resId']);

        $convertedDocument = AdrModel::getDocuments([
            'select'    => ['id', 'docserver_id', 'path', 'filename', 'type', 'fingerprint'],
            'where'     => ['res_id = ?', 'type in (?)'],
            'data'      => [$args['resId'], ['PDF', 'SIGN']],
            'orderBy'   => ["type='SIGN' DESC", 'version DESC'],
            'limit'     => 1
        ]);

        $convertedDocument = $convertedDocument[0] ?? null;
        if (!empty($convertedDocument)) {
            $docserver = DocserverModel::getByDocserverId(['docserverId' => $convertedDocument['docserver_id'], 'select' => ['path_template', 'docserver_type_id']]);
            $pathToDocument = $docserver['path_template'] . str_replace('#', DIRECTORY_SEPARATOR, $convertedDocument['path']) . $convertedDocument['filename'];
            if (!is_file($pathToDocument)) {
                return false;
            }
            $docserverType = DocserverTypeModel::getById(['id' => $docserver['docserver_type_id'], 'select' => ['fingerprint_mode']]);
            $fingerprint = StoreController::getFingerPrint(['filePath' => $pathToDocument, 'mode' => $docserverType['fingerprint_mode']]);
            if (empty($convertedDocument['fingerprint'])) {
                AdrModel::updateDocumentAdr(['set' => ['fingerprint' => $fingerprint], 'where' => ['id = ?'], 'data' => [$convertedDocument['id']]]);
                $convertedDocument['fingerprint'] = $fingerprint;
            }
            if ($convertedDocument['fingerprint'] != $fingerprint) {
                return false;
            }
        }
        $allAttachments = AttachmentModel::get(['select' => ['res_id'], 'where' => ['res_id_master = ?', 'status != ?'], 'data' => [$args['resId'], 'DEL']]);
        $allAttachments = array_column($allAttachments, 'res_id');
        $convertedDocuments = [];
        if (!empty($allAttachments)) {
            $convertedDocuments = AdrModel::getAttachments([
                'select'    => ['id', 'docserver_id', 'path', 'filename', 'type', 'fingerprint'],
                'where'     => ['res_id in (?)', 'type = ?'],
                'data'      => [$allAttachments, 'PDF']
            ]);
        }

        foreach ($convertedDocuments as $convertedDocument) {
            $docserver = DocserverModel::getByDocserverId(['docserverId' => $convertedDocument['docserver_id'], 'select' => ['path_template', 'docserver_type_id']]);
            $pathToDocument = $docserver['path_template'] . str_replace('#', DIRECTORY_SEPARATOR, $convertedDocument['path']) . $convertedDocument['filename'];
            if (!is_file($pathToDocument)) {
                return false;
            }
            $docserverType = DocserverTypeModel::getById(['id' => $docserver['docserver_type_id'], 'select' => ['fingerprint_mode']]);
            $fingerprint = StoreController::getFingerPrint(['filePath' => $pathToDocument, 'mode' => $docserverType['fingerprint_mode']]);
            if (empty($convertedDocument['fingerprint'])) {
                AdrModel::updateAttachmentAdr(['set' => ['fingerprint' => $fingerprint], 'where' => ['id = ?'], 'data' => [$convertedDocument['id']]]);
                $convertedDocument['fingerprint'] = $fingerprint;
            }
            if ($convertedDocument['fingerprint'] != $fingerprint) {
                return false;
            }
        }

        return true;
    }
}
