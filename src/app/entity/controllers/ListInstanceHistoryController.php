<?php

/**
 * Copyright Maarch since 2008 under licence GPLv3.
 * See LICENCE.txt file at the root folder for more details.
 * This file is part of Maarch software.
 *
 */

/**
 * @brief List Instance History Controller
 * @author dev@maarch.org
 */

namespace Entity\controllers;

use Entity\models\EntityModel;
use Entity\models\ListInstanceHistoryDetailModel;
use Entity\models\ListInstanceHistoryModel;
use Resource\controllers\ResController;
use Resource\models\ResModel;
use Respect\Validation\Validator;
use Slim\Psr7\Request;
use SrcCore\http\Response;
use User\models\UserModel;

class ListInstanceHistoryController
{
    public function getDiffusionListByResId(Request $request, Response $response, array $args)
    {
        if (!Validator::intVal()->validate($args['resId']) || !ResController::hasRightByResId(['resId' => [$args['resId']], 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Document out of perimeter']);
        }

        $resource = ResModel::getById(['select' => ['creation_date', 'typist'], 'resId' => $args['resId']]);

        $listInstancesModification = ListInstanceHistoryModel::get([
            'select'    => ['listinstance_history_id', 'updated_date', 'user_id'],
            'where'     => ['res_id = ?'],
            'data'      => [$args['resId']],
            'orderBy'   => ['updated_date']
        ]);

        $formattedHistory = [];
        foreach ($listInstancesModification as $limKey => $value) {
            $listInstancesDetails = ListInstanceHistoryDetailModel::get([
                'select'    => ['*'],
                'where'     => ['listinstance_history_id = ?', 'difflist_type = ?'],
                'data'      => [$value['listinstance_history_id'], 'entity_id']
            ]);
            $formattedDetails = [];
            foreach ($listInstancesDetails as $listInstancesDetail) {
                if (empty($formattedDetails[$listInstancesDetail['item_mode']])) {
                    $formattedDetails[$listInstancesDetail['item_mode']] = ['items' => []];
                }
                if ($listInstancesDetail['item_type'] == 'entity_id') {
                    $entity = EntityModel::getById(['id' => $listInstancesDetail['item_id'], 'select' => ['entity_label', 'entity_id']]);
                    $listInstancesDetail['item_id'] = $entity['entity_id'];
                    $listInstancesDetail['itemSerialId'] = $listInstancesDetail['item_id'];
                    $listInstancesDetail['itemLabel'] = $entity['entity_label'];
                    $listInstancesDetail['itemSubLabel'] = '';
                } else {
                    $listInstancesDetail['itemSerialId'] = $listInstancesDetail['item_id'];
                    $listInstancesDetail['itemLabel'] = UserModel::getLabelledUserById(['id' => $listInstancesDetail['item_id']]);
                    $listInstancesDetail['itemSubLabel'] = UserModel::getPrimaryEntityById(['id' => $listInstancesDetail['item_id'], 'select' => ['entities.entity_label']])['entity_label'];
                }
                $formattedDetails[$listInstancesDetail['item_mode']]['items'][] = $listInstancesDetail;
            }
            if (!empty($listInstancesDetails)) {
                if ($limKey == 0) {
                    $formattedHistory[] = [
                        'userId'            => $resource['typist'],
                        'user'              => UserModel::getLabelledUserById(['id' => $resource['typist']]),
                        'creationDate'      => $resource['creation_date'],
                        'details'           => $formattedDetails
                    ];
                } else {
                    $formattedHistory[] = [
                        'userId'            => $listInstancesModification[$limKey - 1]['user_id'],
                        'user'              => UserModel::getLabelledUserById(['id' => $listInstancesModification[$limKey - 1]['user_id']]),
                        'creationDate'      => $listInstancesModification[$limKey - 1]['updated_date'],
                        'details'           => $formattedDetails
                    ];
                }
            }
        }
        $formattedHistory = array_reverse($formattedHistory);

        return $response->withJson(['listInstanceHistory' => $formattedHistory]);
    }

    public function getCircuitByResId(Request $request, Response $response, array $args)
    {
        if (!Validator::intVal()->validate($args['resId']) || !ResController::hasRightByResId(['resId' => [$args['resId']], 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Document out of perimeter']);
        }

        $queryParams = $request->getQueryParams();

        $listInstancesModification = ListInstanceHistoryModel::get([
            'select'    => ['listinstance_history_id', 'updated_date', 'user_id'],
            'where'     => ['res_id = ?'],
            'data'      => [$args['resId']],
            'orderBy'   => ['updated_date']
        ]);

        $formattedHistory = [];
        foreach ($listInstancesModification as $value) {
            $where = ['listinstance_history_id = ?'];
            $data = [$value['listinstance_history_id']];
            if (!empty($queryParams['type']) && in_array($queryParams['type'], ['visaCircuit', 'opinionCircuit'])) {
                $where[] = 'difflist_type = ?';
                $data[] = str_replace(['visaCircuit', 'opinionCircuit'], ['VISA_CIRCUIT', 'AVIS_CIRCUIT'], $queryParams['type']);
            } else {
                $where[] = 'difflist_type = ?';
                $data[] = 'VISA_CIRCUIT';
            }
            $listInstancesDetails = ListInstanceHistoryDetailModel::get([
                'select'    => ['*'],
                'where'     => $where,
                'data'      => $data
            ]);
            foreach ($listInstancesDetails as $key => $listInstancesDetail) {
                $listInstancesDetails[$key]['itemSerialId'] = $listInstancesDetail['item_id'];
                $listInstancesDetails[$key]['itemLabel'] = UserModel::getLabelledUserById(['id' => $listInstancesDetail['item_id']]);
                $listInstancesDetails[$key]['itemSubLabel'] = UserModel::getPrimaryEntityById(['id' => $listInstancesDetail['item_id'], 'select' => ['entities.entity_label']])['entity_label'];
            }
            if (!empty($listInstancesDetails)) {
                $formattedHistory[] = [
                    'userId'            => $value['user_id'],
                    'user'              => UserModel::getLabelledUserById(['id' => $value['user_id']]),
                    'creationDate'      => $value['updated_date'],
                    'details'           => $listInstancesDetails
                ];
            }
        }
        array_pop($formattedHistory);
        $formattedHistory = array_reverse($formattedHistory);

        return $response->withJson(['listInstanceHistory' => $formattedHistory]);
    }
}
