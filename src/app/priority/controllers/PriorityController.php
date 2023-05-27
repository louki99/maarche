<?php

/**
 * Copyright Maarch since 2008 under licence GPLv3.
 * See LICENCE.txt file at the root folder for more details.
 * This file is part of Maarch software.
 *
 */

/**
 * @brief Priority Controller
 * @author dev@maarch.org
 */

namespace Priority\controllers;

use Group\controllers\PrivilegeController;
use History\controllers\HistoryController;
use Priority\models\PriorityModel;
use Respect\Validation\Validator;
use Slim\Psr7\Request;
use SrcCore\http\Response;

class PriorityController
{
    public function get(Request $request, Response $response)
    {
        return $response->withJson(['priorities' => PriorityModel::get(['orderBy' => ['"order"']])]);
    }

    public function getById(Request $request, Response $response, array $aArgs)
    {
        $priotity = PriorityModel::getById(['id' => $aArgs['id']]);

        if (empty($priotity)) {
            return $response->withStatus(400)->withJson(['errors' => 'Priority not found']);
        }

        return $response->withJson(['priority'  => $priotity]);
    }

    public function create(Request $request, Response $response)
    {
        if (!PrivilegeController::hasPrivilege(['privilegeId' => 'admin_priorities', 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }

        $data = $request->getParsedBody();
        $check = Validator::stringType()->notEmpty()->validate($data['label']);
        $check = $check && Validator::stringType()->notEmpty()->validate($data['color']);
        $check = $check && (Validator::notEmpty()->intVal()->validate($data['delays']) || $data['delays'] == 0);

        if (!$check) {
            return $response->withStatus(400)->withJson(['errors' => 'Body (label, color or delays) is empty or type is incorrect']);
        }

        $delayAlreadySet = PriorityModel::get(['select' => [1], 'where' => ['delays = ?'], 'data' => [(int)$data['delays']]]);
        if (!empty($delayAlreadySet)) {
            return $response->withStatus(400)->withJson(['errors' => _PRIORITY_DELAY_ALREADY_SET]);
        }

        $id = PriorityModel::create($data);
        HistoryController::add([
            'tableName' => 'priorities',
            'recordId'  => $id,
            'eventType' => 'ADD',
            'info'      => _PRIORITY_CREATION . " : {$data['label']}",
            'moduleId'  => 'priority',
            'eventId'   => 'priorityCreation',
        ]);

        return $response->withJson(['priority'  => $id]);
    }

    public function update(Request $request, Response $response, array $args)
    {
        if (!PrivilegeController::hasPrivilege(['privilegeId' => 'admin_priorities', 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }

        $data = $request->getParsedBody();
        $check = Validator::stringType()->notEmpty()->validate($data['label']);
        $check = $check && Validator::stringType()->notEmpty()->validate($data['color']);
        $check = $check && (Validator::notEmpty()->intVal()->validate($data['delays']) || $data['delays'] == 0);

        if (!$check) {
            return $response->withStatus(400)->withJson(['errors' => 'Body (label, color or delays) is empty or type is incorrect']);
        }

        $delayAlreadySet = PriorityModel::get([
            'select' => [1],
            'where'  => ['delays = ?', 'id != ?'],
            'data'   => [$data['delays'], $args['id']]
        ]);
        if (!empty($delayAlreadySet)) {
            return $response->withStatus(400)->withJson(['errors' => _PRIORITY_DELAY_ALREADY_SET]);
        }

        $data['id'] = $args['id'];

        PriorityModel::update($data);
        HistoryController::add([
            'tableName' => 'priorities',
            'recordId'  => $args['id'],
            'eventType' => 'UP',
            'info'      => _PRIORITY_MODIFICATION . " : {$data['label']}",
            'moduleId'  => 'priority',
            'eventId'   => 'priorityModification',
        ]);

        return $response->withJson(['success' => 'success']);
    }

    public function delete(Request $request, Response $response, array $aArgs)
    {
        if (!PrivilegeController::hasPrivilege(['privilegeId' => 'admin_priorities', 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }

        PriorityModel::delete(['id' => $aArgs['id']]);
        HistoryController::add([
            'tableName' => 'priorities',
            'recordId'  => $aArgs['id'],
            'eventType' => 'DEL',
            'info'      => _PRIORITY_SUPPRESSION . " : {$aArgs['id']}",
            'moduleId'  => 'priority',
            'eventId'   => 'prioritySuppression',
        ]);

        return $response->withJson(['priorities' => PriorityModel::get()]);
    }

    public function getSorted(Request $request, Response $response)
    {
        if (!PrivilegeController::hasPrivilege(['privilegeId' => 'admin_priorities', 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }

        $priorities = PriorityModel::get([
            'select'    => ['id', 'label', '"order"'],
            'orderBy'   => ['"order" NULLS LAST']
        ]);

        return $response->withJson(['priorities' => $priorities]);
    }

    public function updateSort(Request $request, Response $response)
    {
        if (!PrivilegeController::hasPrivilege(['privilegeId' => 'admin_priorities', 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }

        $data = $request->getParsedBody();

        foreach ($data as $key => $priorityToUpdate) {
            if ($key != $priorityToUpdate['order']) {
                PriorityModel::updateOrder(['id' => $priorityToUpdate['id'], 'order' => $key]);
            }
        }

        HistoryController::add([
            'tableName' => 'priorities',
            'recordId'  => $GLOBALS['login'],
            'eventType' => 'UP',
            'info'      => _PRIORITY_SORT_MODIFICATION,
            'moduleId'  => 'priority',
            'eventId'   => 'priorityModification',
        ]);

        $priorities = PriorityModel::get([
            'select'    => ['id', 'label', '"order"'],
            'orderBy'   => ['"order" NULLS LAST']
        ]);

        return $response->withJson(['priorities' => $priorities]);
    }
}
