<?php

/**
* Copyright Maarch since 2008 under licence GPLv3.
* See LICENCE.txt file at the root folder for more details.
* This file is part of Maarch software.

* @brief   ActionController
* @author  dev <dev@maarch.org>
* @ingroup core
*/

namespace Action\controllers;

use Basket\models\GroupBasketRedirectModel;
use CustomField\models\CustomFieldModel;
use Group\controllers\GroupController;
use Group\controllers\PrivilegeController;
use Group\models\GroupModel;
use History\controllers\HistoryController;
use IndexingModel\models\IndexingModelFieldModel;
use Resource\models\ResModel;
use Respect\Validation\Validator;
use Action\models\ActionModel;
use SrcCore\models\ValidatorModel;
use Status\models\StatusModel;
use Slim\Psr7\Request;
use SrcCore\http\Response;

class ActionController
{
    public function get(Request $request, Response $response)
    {
        if (!PrivilegeController::hasPrivilege(['privilegeId' => 'admin_actions', 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }

        $actions = ActionModel::get();

        foreach ($actions as $key => $action) {
            $actions[$key]['parameters'] = json_decode($action['parameters'], true);
        }

        return $response->withJson(['actions' => $actions]);
    }

    public function getById(Request $request, Response $response, array $aArgs)
    {
        if (!Validator::intVal()->validate($aArgs['id'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Route id is not an integer']);
        }

        $action['action'] = ActionModel::getById(['id' => $aArgs['id']]);
        if (empty($action['action'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Action does not exist']);
        }

        $categories = ActionModel::getCategoriesById(['id' => $aArgs['id']]);

        $action['action']['history'] = ($action['action']['history'] == 'Y');
        $action['action']['is_system'] = ($action['action']['is_system'] == 'Y');

        $action['action']['actionCategories'] = [];
        foreach ($categories as $category) {
            $action['action']['actionCategories'][] = $category['category_id'];
        }

        $action['categoriesList'] = ResModel::getCategories();
        if (empty($action['action']['actionCategories'])) {
            foreach ($action['categoriesList'] as $category) {
                $action['action']['actionCategories'][] = $category['id'];
            }
        }

        $action['statuses'] = StatusModel::get();
        array_unshift($action['statuses'], ['id' => '_NOSTATUS_', 'label_status' => _UNCHANGED]);
        $action['keywordsList'] = ActionModel::getKeywords();

        $action['action']['parameters'] = json_decode($action['action']['parameters'], true);

        return $response->withJson($action);
    }

    public function create(Request $request, Response $response)
    {
        if (!PrivilegeController::hasPrivilege(['privilegeId' => 'admin_actions', 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }

        $body = $request->getParsedBody();
        $body = $this->manageValue($body);
        
        $errors = $this->control($body, 'create');
        if (!empty($errors)) {
            return $response->withStatus(400)->withJson(['errors' => $errors]);
        }

        $requiredFields = [];
        $parameters = [];
        if (!empty($body['parameters'])) {
            $parameters = $body['parameters'];

            if (!empty($parameters['requiredFields'])) {
                if (!Validator::arrayType()->validate($parameters['requiredFields'])) {
                    return $response->withStatus(400)->withJson(['errors' => 'Data parameter requiredFields is not an array']);
                }
                $customFields = CustomFieldModel::get(['select' => ['id']]);
                $customFields = array_column($customFields, 'id');
                foreach ($parameters['requiredFields'] as $requiredField) {
                    if (strpos($requiredField, 'indexingCustomField_') !== false) {
                        $idCustom = explode("_", $requiredField)[1];
                        if (!in_array($idCustom, $customFields)) {
                            return $response->withStatus(400)->withJson(['errors' => 'Data custom field does not exist']);
                        }
                        $requiredFields[] = $requiredField;
                    }
                }
                $parameters['requiredFields'] = $requiredFields;
            }
            if (!empty($parameters['successStatus']) && is_string($parameters['successStatus'])) {
                $status = StatusModel::getById(['select' => [1], 'id' => $parameters['successStatus']]);
                if (empty($status)) {
                    unset($parameters['successStatus']);
                }
            }
            if (!empty($parameters['errorStatus']) && is_string($parameters['errorStatus'])) {
                $status = StatusModel::getById(['select' => [1], 'id' => $parameters['errorStatus']]);
                if (empty($status)) {
                    unset($parameters['errorStatus']);
                }
            }
        }

        $id = ActionModel::create([
            'history'      => $body['history'],
            'keyword'      => $body['keyword'],
            'id_status'    => $body['id_status'],
            'label_action' => $body['label_action'],
            'action_page'  => $body['action_page'],
            'component'    => $body['component'],
            'parameters'   => !empty($parameters) ? json_encode($parameters) : '{}'
        ]);
        if (!empty($body['actionCategories'])) {
            ActionModel::createCategories(['id' => $id, 'categories' => $body['actionCategories']]);
        }

        HistoryController::add([
            'tableName' => 'actions',
            'recordId'  => $id,
            'eventType' => 'ADD',
            'eventId'   => 'actionadd',
            'info'      => _ACTION_ADDED . ' : ' . $body['label_action']
        ]);

        return $response->withJson(['actionId' => $id]);
    }

    public function update(Request $request, Response $response, array $args)
    {
        if (!PrivilegeController::hasPrivilege(['privilegeId' => 'admin_actions', 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }

        $body       = $request->getParsedBody();
        $body['id'] = $args['id'];

        $body   = $this->manageValue($body);
        $errors = $this->control($body, 'update');
        if (!empty($errors)) {
            return $response->withStatus(500)->withJson(['errors' => $errors]);
        }

        $requiredFields = [];
        $parameters     = [];
        if (!empty($body['parameters'])) {
            $parameters = $body['parameters'];

            if (!empty($parameters['requiredFields'])) {
                if (!Validator::arrayType()->validate($parameters['requiredFields'])) {
                    return $response->withStatus(400)->withJson(['errors' => 'Data parameter requiredFields is not an array']);
                }
                $customFields = CustomFieldModel::get(['select' => ['id']]);
                $customFields = array_column($customFields, 'id');
                foreach ($parameters['requiredFields'] as $requiredField) {
                    if (strpos($requiredField, 'indexingCustomField_') !== false) {
                        $idCustom = explode("_", $requiredField)[1];
                        if (!in_array($idCustom, $customFields)) {
                            return $response->withStatus(400)->withJson(['errors' => 'Data custom field does not exist']);
                        }
                        $requiredFields[] = $requiredField;
                    }
                }
                $parameters['requiredFields'] = $requiredFields;
            }
        }

        ActionModel::update([
            'set'   => [
                'keyword'      => $body['keyword'],
                'label_action' => $body['label_action'],
                'id_status'    => $body['id_status'],
                'action_page'  => $body['action_page'],
                'component'    => $body['component'],
                'history'      => $body['history'],
                'parameters'   => !empty($parameters) ? json_encode($parameters) : '{}'
            ],
            'where' => ['id = ?'],
            'data'  => [$body['id']]
        ]);
        ActionModel::deleteCategories(['id' => $args['id']]);
        if (!empty($body['actionCategories'])) {
            ActionModel::createCategories(['id' => $args['id'], 'categories' => $body['actionCategories']]);
        }

        if (!in_array($body['component'], GroupController::INDEXING_ACTIONS)) {
            GroupModel::update([
                'postSet'   => ['indexation_parameters' => "jsonb_set(indexation_parameters, '{actions}', (indexation_parameters->'actions') - '{$args['id']}')"],
                'where'     => ["indexation_parameters->'actions' @> ?"],
                'data'      => ['"'.$args['id'].'"']
            ]);
        }

        HistoryController::add([
            'tableName' => 'actions',
            'recordId'  => $args['id'],
            'eventType' => 'UP',
            'eventId'   => 'actionup',
            'info'      => _ACTION_UPDATED. ' : ' . $body['label_action']
        ]);

        return $response->withJson(['success' => 'success']);
    }

    public function delete(Request $request, Response $response, array $args)
    {
        if (!PrivilegeController::hasPrivilege(['privilegeId' => 'admin_actions', 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }

        if (!Validator::intVal()->validate($args['id'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Route id is not an integer']);
        }
        $action = ActionModel::getById(['id' => $args['id'], 'select' => ['label_action']]);
        if (empty($action)) {
            return $response->withStatus(400)->withJson(['errors' => 'Action does not exist']);
        }

        ActionModel::delete(['id' => $args['id']]);
        ActionModel::deleteCategories(['id' => $args['id']]);
        GroupBasketRedirectModel::delete(['where' => ['action_id = ?'], 'data' => [$args['id']]]);

        GroupModel::update([
            'postSet'   => ['indexation_parameters' => "jsonb_set(indexation_parameters, '{actions}', (indexation_parameters->'actions') - '{$args['id']}')"],
            'where'     => ["indexation_parameters->'actions' @> ?"],
            'data'      => ['"'.$args['id'].'"']
        ]);

        HistoryController::add([
            'tableName' => 'actions',
            'recordId'  => $args['id'],
            'eventType' => 'DEL',
            'eventId'   => 'actiondel',
            'info'      => _ACTION_DELETED. ' : ' . $action['label_action']
        ]);

        return $response->withJson(['actions' => ActionModel::get()]);
    }

    protected function control($aArgs, $mode)
    {
        $errors = [];
      
        $objs = StatusModel::get();
        $status = array_column($objs, 'id');
        array_unshift($status, '_NOSTATUS_');

        if (!(in_array($aArgs['id_status'], $status))) {
            $errors[]= 'Invalid Status';
        }

        if ($mode == 'update') {
            if (!Validator::intVal()->validate($aArgs['id'])) {
                $errors[] = 'Id is not a numeric';
            } else {
                $obj = ActionModel::getById(['id' => $aArgs['id'], 'select' => [1]]);
            }
           
            if (empty($obj)) {
                $errors[] = 'Id ' .$aArgs['id']. ' does not exist';
            }
        }
           
        if (!Validator::notEmpty()->validate($aArgs['label_action']) ||
            !Validator::length(1, 255)->validate($aArgs['label_action'])) {
            $errors[] = 'Invalid label action';
        }
        if (!Validator::stringType()->notEmpty()->validate($aArgs['action_page'])) {
            $errors[] = 'Invalid page action';
        }

        if (!Validator::notEmpty()->validate($aArgs['id_status'])) {
            $errors[] = 'id_status is empty';
        }

        if (!Validator::notEmpty()->validate($aArgs['history']) || ($aArgs['history'] != 'Y' && $aArgs['history'] != 'N')) {
            $errors[]= 'Invalid history value';
        }

        return $errors;
    }

    public function initAction(Request $request, Response $response)
    {
        $obj['action']['history']          = true;
        $obj['action']['keyword']          = '';
        $obj['action']['actionPageId']     = 'confirm_status';
        $obj['action']['id_status']        = '_NOSTATUS_';
        $obj['categoriesList']             = ResModel::getCategories();

        $obj['action']['actionCategories'] = array_column($obj['categoriesList'], 'id');

        $obj['statuses'] = StatusModel::get();
        array_unshift($obj['statuses'], ['id'=>'_NOSTATUS_','label_status'=> _UNCHANGED]);
        $obj['keywordsList'] = ActionModel::getKeywords();
        
        return $response->withJson($obj);
    }

    protected function manageValue($request)
    {
        foreach ($request as $key => $value) {
            if (in_array($key, ['history'])) {
                if (empty($value)) {
                    $request[$key] = 'N';
                } else {
                    $request[$key] = 'Y';
                }
            }
        }
        return $request;
    }

    public static function checkRequiredFields(array $args)
    {
        ValidatorModel::notEmpty($args, ['resId', 'actionRequiredFields']);
        ValidatorModel::intVal($args, ['resId']);

        $resource = ResModel::getById(['resId' => $args['resId'], 'select' => ['model_id', 'custom_fields']]);
        $model = $resource['model_id'];
        $resourceCustomFields = json_decode($resource['custom_fields'], true);
        $modelFields = IndexingModelFieldModel::get([
            'select' => ['identifier'],
            'where'  => ['model_id = ?', "identifier LIKE 'indexingCustomField_%'"],
            'data'   => [$model]
        ]);
        $modelFields = array_column($modelFields, 'identifier');

        foreach ($args['actionRequiredFields'] as $actionRequiredField) {
            $idCustom = explode("_", $actionRequiredField)[1];
            if (in_array($actionRequiredField, $modelFields) && empty($resourceCustomFields[$idCustom])) {
                return ['errors' => 'Missing required custom field to do action'];
            }
        }
        return ['success' => true];
    }

    /**
     * @description Replace selected fields value
     * @param   array   $args
     * @return  bool    true
     */
    public static function replaceFieldsData(array $args)
    {
        ValidatorModel::notEmpty($args, ['resId']);
        ValidatorModel::intVal($args, ['resId']);
        ValidatorModel::arrayType($args, ['fillRequiredFields']);

        $set   = ['modification_date' => 'CURRENT_TIMESTAMP'];
        $where = ['res_id = ?'];

        if (!empty($args['fillRequiredFields'])) {
            $fillRequiredFields = $args['fillRequiredFields'];

            $resource = ResModel::getById(['resId' => $args['resId'], 'select' => ['custom_fields', 'model_id']]);
            $resourceCustomFields = json_decode($resource['custom_fields'], true);
            $modelFields = IndexingModelFieldModel::get([
                'select' => ['identifier'],
                'where'  => ['model_id = ?', "identifier LIKE 'indexingCustomField_%'"],
                'data'   => [$resource['model_id']]
            ]);
            $modelFields = array_column($modelFields, 'identifier');

            foreach($fillRequiredFields as $fillRequiredFieldItem) {
                $idCustom = explode("_", $fillRequiredFieldItem['id'])[1];
                $customFieldModel = CustomFieldModel::get(['select' => ['label'],'where' => ['id = ?'],'data' => [$idCustom]]);

                if (!empty($customFieldModel) && in_array($fillRequiredFieldItem['id'], $modelFields) && !empty($fillRequiredFieldItem['value'])) {
                    $resourceCustomFields[$idCustom] = $fillRequiredFieldItem['value'];
                    if ($fillRequiredFieldItem['value'] === '_TODAY') {
                        $resourceCustomFields[$idCustom] = date('Y-m-d');
                    }
                }
            }
            if (!empty($resourceCustomFields)) {
                $set['custom_fields'] = json_encode($resourceCustomFields);
                ResModel::update(['set' => $set, 'where' => $where, 'data' => [$args['resId']]]);
            }
        }
        return true;
    }
}
