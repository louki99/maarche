<?php

/**
 * Copyright Maarch since 2008 under licence GPLv3.
 * See LICENCE.txt file at the root folder for more details.
 * This file is part of Maarch software.
 *
 */

/**
 * @brief Users Followed Resources Controller
 * @author dev@maarch.org
 */

namespace Resource\controllers;

use Attachment\models\AttachmentModel;
use Basket\models\BasketModel;
use Group\controllers\PrivilegeController;
use Resource\models\ResModel;
use Resource\models\ResourceListModel;
use Resource\models\UserFollowedResourceModel;
use Respect\Validation\Validator;
use Slim\Psr7\Request;
use SrcCore\http\Response;
use SrcCore\controllers\PreparedClauseController;

class UserFollowedResourceController
{
    public function follow(Request $request, Response $response)
    {
        $body = $request->getParsedBody();

        if (!ResController::hasRightByResId(['resId' => $body['resources'], 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Document out of perimeter']);
        }

        foreach ($body['resources'] as $resId) {
            $following = UserFollowedResourceModel::get([
                'where' => ['user_id = ?', 'res_id = ?'],
                'data'  => [$GLOBALS['id'], $resId]
            ]);

            if (!empty($following)) {
                continue;
            }

            UserFollowedResourceModel::create([
                'userId' => $GLOBALS['id'],
                'resId'  => $resId
            ]);
        }

        return $response->withStatus(204);
    }

    public function unFollow(Request $request, Response $response)
    {
        $body = $request->getParsedBody();

        $nbUnFollowed = 0;

        foreach ($body['resources'] as $resId) {
            $following = UserFollowedResourceModel::get([
                'where' => ['user_id = ?', 'res_id = ?'],
                'data' => [$GLOBALS['id'], $resId]
            ]);

            if (empty($following)) {
                continue;
            }

            UserFollowedResourceModel::delete([
                'userId' => $GLOBALS['id'],
                'resId' => $resId
            ]);

            $nbUnFollowed++;
        }


        return $response->withJson(['unFollowed' => $nbUnFollowed]);
    }

    public function getFollowedResources(Request $request, Response $response)
    {
        $followedResources = UserFollowedResourceModel::get(['select' => ['res_id'], 'where' => ['user_id = ?'], 'data' => [$GLOBALS['id']]]);
        $followedResources = array_column($followedResources, 'res_id');

        $formattedResources = [];
        $allResources = [];
        $count = 0;
        if (!empty($followedResources)) {
            $queryParams = $request->getQueryParams();
            $queryParams['offset'] = (empty($queryParams['offset']) || !is_numeric($queryParams['offset']) ? 0 : (int)$queryParams['offset']);
            $queryParams['limit'] = (empty($queryParams['limit']) || !is_numeric($queryParams['limit']) ? 10 : (int)$queryParams['limit']);

            $allQueryData = ResourceListController::getResourcesListQueryData(['data' => $queryParams]);
            if (!empty($allQueryData['order'])) {
                $data['order'] = $allQueryData['order'];
            }

            $rawResources = ResourceListModel::getOnView([
                'select'    => ['res_id'],
                'table'     => $allQueryData['table'],
                'leftJoin'  => $allQueryData['leftJoin'],
                'where'     => array_merge(['res_id in (?)'], $allQueryData['where']),
                'data'      => array_merge([$followedResources], $allQueryData['queryData']),
                'orderBy'   => empty($data['order']) ? ['creation_date'] : [$data['order']]
            ]);

            $resIds = ResourceListController::getIdsWithOffsetAndLimit(['resources' => $rawResources, 'offset' => $queryParams['offset'], 'limit' => $queryParams['limit']]);

            $allResources = array_column($rawResources, 'res_id');

            $formattedResources = [];
            if (!empty($resIds)) {
                $attachments = AttachmentModel::get([
                    'select'    => ['COUNT(res_id)', 'res_id_master'],
                    'where'     => ['res_id_master in (?)', 'status not in (?)', '((status = ? AND typist = ?) OR status != ?)', 'attachment_type <> ?'],
                    'data'      => [$resIds, ['DEL', 'OBS'], 'TMP', $GLOBALS['id'], 'TMP', 'summary_sheet'],
                    'groupBy'   => ['res_id_master']
                ]);

                $select = [
                    'res_letterbox.res_id', 'res_letterbox.subject', 'res_letterbox.barcode', 'res_letterbox.alt_identifier',
                    'status.label_status AS "status.label_status"', 'status.img_filename AS "status.img_filename"', 'priorities.color AS "priorities.color"',
                    'res_letterbox.filename as res_filename', 'res_letterbox.retention_frozen', 'res_letterbox.binding'
                ];
                $tableFunction = ['status', 'priorities'];
                $leftJoinFunction = ['res_letterbox.status = status.id', 'res_letterbox.priority = priorities.id'];

                $order = 'CASE res_letterbox.res_id ';
                foreach ($resIds as $key => $resId) {
                    $order .= "WHEN {$resId} THEN {$key} ";
                }
                $order .= 'END';

                $resources = ResourceListModel::getOnResource([
                    'select'    => $select,
                    'table'     => $tableFunction,
                    'leftJoin'  => $leftJoinFunction,
                    'where'     => ['res_letterbox.res_id in (?)'],
                    'data'      => [$resIds],
                    'orderBy'   => [$order]
                ]);

                $formattedResources = ResourceListController::getFormattedResources([
                    'resources'     => $resources,
                    'userId'        => $GLOBALS['id'],
                    'attachments'   => $attachments,
                    'checkLocked'   => false,
                    'trackedMails'  => $followedResources,
                    'listDisplay'   => ['folders']
                ]);
                
                $folderPrivilege = PrivilegeController::hasPrivilege(['privilegeId' => 'include_folders_and_followed_resources_perimeter', 'userId' => $GLOBALS['id']]);
                foreach ($formattedResources as $key => $formattedResource) {
                    if ($folderPrivilege) {
                        $formattedResources[$key]['allowed'] = true;
                    } else {
                        $formattedResources[$key]['allowed'] = ResController::hasRightByResId(['resId' => [$formattedResource['resId']], 'userId' => $GLOBALS['id']]);
                    }
                }
            }

            $count = count($rawResources);
        }

        return $response->withJson(['resources' => $formattedResources, 'countResources' => $count, 'allResources' => $allResources]);
    }

    public function getBaskets(Request $request, Response $response, array $args)
    {
        if (!Validator::numericVal()->notEmpty()->validate($args['resId'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Route resId is not an integer']);
        }

        $baskets = BasketModel::getWithPreferences([
            'select'    => ['baskets.id', 'baskets.basket_name', 'baskets.basket_clause', 'users_baskets_preferences.group_serial_id', 'usergroups.group_desc'],
            'where'     => ['users_baskets_preferences.user_serial_id = ?'],
            'data'      => [$GLOBALS['id']],
            'orderBy'   => ['baskets.basket_name']
        ]);
        $groupsBaskets = [];
        $inCheckedBaskets = [];
        $outCheckedBaskets = [];
        foreach ($baskets as $basket) {
            if (in_array($basket['id'], $outCheckedBaskets)) {
                continue;
            } else {
                if (!in_array($basket['id'], $inCheckedBaskets)) {
                    $preparedClause = PreparedClauseController::getPreparedClause(['clause' => $basket['basket_clause'], 'userId' => $GLOBALS['id']]);
                    $resource = ResModel::getOnView(['select' => [1], 'where' => ['res_id = ?', "({$preparedClause})"], 'data' => [$args['resId']]]);
                    if (empty($resource)) {
                        $outCheckedBaskets[] = $basket['id'];
                        continue;
                    }
                }
                $inCheckedBaskets[] = $basket['id'];
                $groupsBaskets[] = ['groupId' => $basket['group_serial_id'], 'groupName' => $basket['group_desc'], 'basketId' => $basket['id'], 'basketName' => $basket['basket_name']];
            }
        }

        return $response->withJson(['groupsBaskets' => $groupsBaskets]);
    }

    public function getFilters(Request $request, Response $response)
    {
        $followedResources = UserFollowedResourceModel::get(['select' => ['res_id'], 'where' => ['user_id = ?'], 'data' => [$GLOBALS['id']]]);
        $followedResources = array_column($followedResources, 'res_id');

        if (empty($followedResources)) {
            return $response->withJson([
                'entities'         => [],
                'priorities'       => [],
                'categories'       => [],
                'statuses'         => [],
                'entitiesChildren' => [],
                'doctypes'         => [],
                'folders'          => []
            ]);
        }

        $filters = ResourceListController::getFormattedFilters([
            'where'       => ['(res_id in (?))'],
            'queryData'   => [$followedResources],
            'queryParams' => $request->getQueryParams()
        ]);

        return $response->withJson($filters);
    }
}
