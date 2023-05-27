<?php
/**
* Copyright Maarch since 2008 under licence GPLv3.
* See LICENCE.txt file at the root folder for more details.
* This file is part of Maarch software.

* @brief   DoctypeController
* @author  dev <dev@maarch.org>
* @ingroup core
*/

namespace Doctype\controllers;

use Group\controllers\PrivilegeController;
use History\controllers\HistoryController;
use Respect\Validation\Validator;
use Doctype\models\SecondLevelModel;
use Doctype\models\DoctypeModel;
use Template\models\TemplateModel;
use Slim\Psr7\Request;
use SrcCore\http\Response;
use Resource\models\ResModel;

class DoctypeController
{
    public function get(Request $request, Response $response)
    {
        $doctypes = DoctypeModel::get([
            'where'    => ['enabled = ?'],
            'data'     => ['Y'],
            'order_by' => ['description asc']
        ]);

        return $response->withJson(['doctypes' => $doctypes]);
    }

    public function getById(Request $request, Response $response, array $aArgs)
    {
        if (!Validator::intVal()->validate($aArgs['id']) || !Validator::notEmpty()->validate($aArgs['id'])) {
            return $response->withStatus(500)->withJson(['errors' => 'wrong format for id']);
        }

        $obj['doctype'] = DoctypeModel::getById(['id' => $aArgs['id']]);

        if (!empty($obj['doctype'])) {
            if ($obj['doctype']['enabled'] == 'Y') {
                $obj['doctype']['enabled'] = true;
            } else {
                $obj['doctype']['enabled'] = false;
            }
        }

        $obj['secondLevel']  = SecondLevelModel::get([
            'select'    => ['doctypes_second_level_id', 'doctypes_second_level_label'],
            'where'     => ['enabled = ?'],
            'data'      => ['Y'],
            'order_by'  => ['doctypes_second_level_label asc']
        ]);
        $obj['models']       = TemplateModel::getByTarget(['select' => ['template_id', 'template_label', 'template_comment'], 'template_target' => 'doctypes']);

        return $response->withJson($obj);
    }

    public function create(Request $request, Response $response)
    {
        if (!PrivilegeController::hasPrivilege(['privilegeId' => 'admin_architecture', 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }

        $data = $request->getParsedBody();
        
        $errors = DoctypeController::control($data, 'create');
        if (!empty($errors)) {
            return $response->withStatus(500)->withJson(['errors' => $errors]);
        }
        if (empty($data['duration_current_use'])) {
            $data['duration_current_use'] = null;
        }

        $secondLevelInfo = SecondLevelModel::getById(['select' => ['doctypes_first_level_id'], 'id' => $data['doctypes_second_level_id']]);
        
        if (empty($secondLevelInfo)) {
            return $response->withStatus(500)->withJson(['errors' => 'doctypes_second_level_id does not exists']);
        }

        $data['doctypes_first_level_id'] = $secondLevelInfo['doctypes_first_level_id'];
    
        $doctypeId = DoctypeModel::create([
            'description'                   => $data['description'],
            'doctypes_first_level_id'       => $data['doctypes_first_level_id'],
            'doctypes_second_level_id'      => $data['doctypes_second_level_id'],
            'duration_current_use'          => $data['duration_current_use'],
            'action_current_use'            => $data['action_current_use'] ?? null,
            'retention_rule'                => $data['retention_rule'] ?? null,
            'retention_final_disposition'   => $data['retention_final_disposition'] ?? null,
            "process_delay"                 => $data['process_delay'],
            "delay1"                        => $data['delay1'],
            "delay2"                        => $data['delay2'],
            "process_mode"                  => $data['process_mode'],
        ]);

        HistoryController::add([
            'tableName' => 'doctypes',
            'recordId'  => $doctypeId,
            'eventType' => 'ADD',
            'eventId'   => 'typesadd',
            'info'      => _DOCTYPE_ADDED . ' : ' . $data['description']
        ]);

        return $response->withJson(
            [
            'doctypeId'   => $doctypeId,
            'doctypeTree' => FirstLevelController::getTreeFunction(),
            ]
        );
    }

    public function update(Request $request, Response $response, $aArgs)
    {
        if (!PrivilegeController::hasPrivilege(['privilegeId' => 'admin_architecture', 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }

        $data                            = $request->getParsedBody();
        $data['type_id']                 = $aArgs['id'];
        
        $errors = DoctypeController::control($data, 'update');
        if (!empty($errors)) {
            return $response->withStatus(500)->withJson(['errors' => $errors]);
        }
        if (empty($data['duration_current_use'])) {
            $data['duration_current_use'] = null;
        }

        $secondLevelInfo                 = SecondLevelModel::getById(['select' => ['doctypes_first_level_id'], 'id' => $data['doctypes_second_level_id']]);
        if (empty($secondLevelInfo)) {
            return $response->withStatus(500)->withJson(['errors' => 'doctypes_second_level_id does not exists']);
        }
        $data['doctypes_first_level_id'] = $secondLevelInfo['doctypes_first_level_id'];
    
        DoctypeModel::update([
            'type_id'                       => $data['type_id'],
            'description'                   => $data['description'],
            'doctypes_first_level_id'       => $data['doctypes_first_level_id'],
            'doctypes_second_level_id'      => $data['doctypes_second_level_id'],
            'duration_current_use'          => $data['duration_current_use'],
            'action_current_use'            => $data['action_current_use'],
            'retention_rule'                => $data['retention_rule'],
            'retention_final_disposition'   => empty($data['retention_final_disposition']) ? null : $data['retention_final_disposition'],
            "process_delay"                 => $data['process_delay'],
            "delay1"                        => $data['delay1'],
            "delay2"                        => $data['delay2'],
            "process_mode"                  => $data['process_mode']
        ]);

        HistoryController::add([
            'tableName' => 'doctypes',
            'recordId'  => $data['type_id'],
            'eventType' => 'UP',
            'eventId'   => 'typesadd',
            'info'      => _DOCTYPE_UPDATED . ' : ' . $data['description']
        ]);

        return $response->withJson(
            [
            'doctype'     => $data,
            'doctypeTree' => FirstLevelController::getTreeFunction(),
            ]
        );
    }

    public function delete(Request $request, Response $response, $aArgs)
    {
        if (!PrivilegeController::hasPrivilege(['privilegeId' => 'admin_architecture', 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }

        if (!Validator::intVal()->validate($aArgs['id'])) {
            return $response->withStatus(500)->withJson(['errors' => 'Id is not a numeric']);
        }

        $count = ResModel::get([
            'select' => ['count(1)'],
            'where'  => ['type_id = ?'],
            'data'   => [$aArgs['id']]
        ]);

        $doctypeTree = null;
        $doctypes = null;
        if ($count[0]['count'] == 0) {
            DoctypeController::deleteAllDoctypeData(['type_id' => $aArgs['id']]);
            $deleted     = 0;
            $doctypeTree = FirstLevelController::getTreeFunction();
        } else {
            $deleted  = $count[0]['count'];
            $doctypes = DoctypeModel::get([
                'where'    => ['enabled = ?'],
                'data'     => ['Y'],
                'order_by' => ['description asc']
            ]);
            foreach ($doctypes as $key => $value) {
                if ($value['type_id'] == $aArgs['id']) {
                    $doctypes[$key]['disabled'] = true;
                }
            }
        }

        return $response->withJson([
            'deleted'     => $deleted,
            'doctypeTree' => $doctypeTree,
            'doctypes'    => $doctypes
        ]);
    }

    public function deleteRedirect(Request $request, Response $response, $aArgs)
    {
        if (!PrivilegeController::hasPrivilege(['privilegeId' => 'admin_architecture', 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }

        $data            = $request->getParsedBody();
        $data['type_id'] = $aArgs['id'];

        if (!Validator::intVal()->validate($data['type_id'])) {
            return $response
                ->withStatus(500)
                ->withJson(['errors' => 'Id is not a numeric']);
        }

        if (!Validator::intVal()->validate($data['new_type_id']) || !Validator::notEmpty()->validate($data['new_type_id'])) {
            return $response
                ->withStatus(500)
                ->withJson(['errors' => 'wrong format for new_type_id']);
        }

        if (empty(DoctypeModel::getById(['id' => $data['new_type_id']]))) {
            return $response
                ->withStatus(500)
                ->withJson(['errors' => 'new_type_id does not exists']);
        }

        if ($data['type_id'] == $data['new_type_id']) {
            return $response
                ->withStatus(500)
                ->withJson(['errors' => 'new_type_id is the same as type_id']);
        }

        ResModel::update([
            'set'   => ['type_id' => $data['new_type_id']],
            'where' => ['type_id = ?'],
            'data'  => [$data['type_id']]
        ]);
        DoctypeController::deleteAllDoctypeData(['type_id' => $data['type_id']]);

        return $response->withJson(
            [
            'doctypeTree' => FirstLevelController::getTreeFunction()
            ]
        );
    }

    protected function deleteAllDoctypeData(array $aArgs = [])
    {
        $doctypeInfo = DoctypeModel::getById(['id' => $aArgs['type_id']]);
        if (!empty($doctypeInfo)) {
            DoctypeModel::delete(['type_id' => $aArgs['type_id']]);

            HistoryController::add([
                'tableName' => 'doctypes',
                'recordId'  => $doctypeInfo['type_id'],
                'eventType' => 'DEL',
                'eventId'   => 'typesdel',
                'info'      => _DOCTYPE_DELETED . ' : ' . $doctypeInfo['description']
            ]);
        }
    }

    protected static function control($aArgs, $mode)
    {
        $errors = [];

        if ($mode == 'update') {
            if (!Validator::intVal()->validate($aArgs['type_id'])) {
                $errors[] = 'type_id is not a numeric';
            } else {
                $obj = DoctypeModel::getById(['id' => $aArgs['type_id']]);
            }
           
            if (empty($obj)) {
                $errors[] = 'Id ' .$aArgs['type_id']. ' does not exists';
            }
        }
           
        if (!Validator::notEmpty()->validate($aArgs['description']) ||
            !Validator::length(1, 255)->validate($aArgs['description'])) {
            $errors[] = 'Invalid description';
        }

        if (!Validator::notEmpty()->validate($aArgs['doctypes_second_level_id']) ||
            !Validator::intVal()->validate($aArgs['doctypes_second_level_id'])) {
            $errors[]= 'Invalid doctypes_second_level_id value';
        }
        if (!Validator::notEmpty()->validate($aArgs['process_delay']) &&
            (!Validator::intVal()->validate($aArgs['process_delay']) || $aArgs['process_delay'] < 0)) {
            $errors[]= 'Invalid process_delay value';
        }
        if (!Validator::notEmpty()->validate($aArgs['delay1']) &&
            (!Validator::intVal()->validate($aArgs['delay1']) || $aArgs['delay1'] < 0)) {
            $errors[]= 'Invalid delay1 value';
        }
        if (!Validator::notEmpty()->validate($aArgs['delay2']) &&
            (!Validator::intVal()->validate($aArgs['delay2']) || $aArgs['delay2'] < 0)) {
            $errors[]= 'Invalid delay2 value';
        }
        if (Validator::notEmpty()->validate($aArgs['duration_current_use'] ?? null) &&
            (!Validator::intVal()->validate($aArgs['duration_current_use'] ?? null) ||
            ($aArgs['duration_current_use'] ?? 0) < 0)) {
            $errors[]= 'Invalid duration_current_use value';
        }

        return $errors;
    }
}
