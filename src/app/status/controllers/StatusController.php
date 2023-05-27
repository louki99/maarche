<?php

/**
* Copyright Maarch since 2008 under licence GPLv3.
* See LICENCE.txt file at the root folder for more details.
* This file is part of Maarch software.
*
*/

/**
* @brief Status Controller
* @author dev@maarch.org
*/

namespace Status\controllers;

use Group\controllers\PrivilegeController;
use History\controllers\HistoryController;
use Respect\Validation\Validator;
use Status\models\StatusModel;
use Status\models\StatusImagesModel;
use Slim\Psr7\Request;
use SrcCore\http\Response;

class StatusController
{
    public function get(Request $request, Response $response)
    {
        return $response->withJson(['statuses' => StatusModel::get()]);
    }

    public function getNewInformations(Request $request, Response $response)
    {
        if (!PrivilegeController::hasPrivilege(['privilegeId' => 'admin_status', 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }

        return $response->withJson([
            'statusImages' => StatusImagesModel::getStatusImages()
        ]);
    }

    public function getByIdentifier(Request $request, Response $response, $aArgs)
    {
        if (!PrivilegeController::hasPrivilege(['privilegeId' => 'admin_status', 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }

        if (!empty($aArgs['identifier']) && Validator::numericVal()->validate($aArgs['identifier'])) {
            $obj = StatusModel::getByIdentifier(['identifier' => $aArgs['identifier']]);

            if (empty($obj)) {
                return $response->withStatus(404)->withJson(['errors' => 'identifier not found']);
            }

            return $response->withJson([
                'status'       => $obj,
                'statusImages' => StatusImagesModel::getStatusImages(),
            ]);
        } else {
            return $response->withStatus(500)->withJson(['errors' => 'identifier not valid']);
        }
    }

    public function getById(Request $request, Response $response, array $aArgs)
    {
        if (!PrivilegeController::hasPrivilege(['privilegeId' => 'admin_status', 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }

        $status = StatusModel::getById(['id' => $aArgs['id']]);
        if (empty($status)) {
            return $response->withStatus(404)->withJson(['errors' => 'id not found']);
        }

        return $response->withJson(['status' => $status]);
    }

    public function create(Request $request, Response $response)
    {
        if (!PrivilegeController::hasPrivilege(['privilegeId' => 'admin_status', 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }

        $request = $request->getParsedBody();
        $aArgs   = StatusController::manageValue($request);
        $errors  = $this->control($aArgs, 'create');

        if (!empty($errors)) {
            return $response->withStatus(500)->withJson(['errors' => $errors]);
        }

        StatusModel::create($aArgs);

        $return['status'] = StatusModel::getById(['id' => $aArgs['id']]);

        HistoryController::add([
            'tableName' => 'status',
            'recordId'  => $return['status']['id'],
            'eventType' => 'ADD',
            'eventId'   => 'statusup',
            'info'       => _STATUS_ADDED . ' : ' . $return['status']['id']
        ]);

        return $response->withJson($return);
    }

    public function update(Request $request, Response $response, $aArgs)
    {
        if (!PrivilegeController::hasPrivilege(['privilegeId' => 'admin_status', 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }

        $request = $request->getParsedBody();
        $request = array_merge($request, $aArgs);

        $aArgs   = StatusController::manageValue($request);
        $errors  = $this->control($aArgs, 'update');

        if (!empty($errors)) {
            return $response->withStatus(500)->withJson(['errors' => $errors]);
        }

        StatusModel::update($aArgs);

        $return['status'] = StatusModel::getByIdentifier(['identifier' => $aArgs['identifier']])[0];

        HistoryController::add([
            'tableName' => 'status',
            'recordId'  => $return['status']['id'],
            'eventType' => 'UP',
            'eventId'   => 'statusup',
            'info'       => _MODIFY_STATUS . ' : ' . $return['status']['id']
        ]);

        return $response->withJson($return);
    }

    public function delete(Request $request, Response $response, $aArgs)
    {
        if (!PrivilegeController::hasPrivilege(['privilegeId' => 'admin_status', 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }

        $statusDeleted = StatusModel::getByIdentifier(['identifier' => $aArgs['identifier']]);

        if (Validator::notEmpty()->validate($aArgs['identifier']) && Validator::numericVal()->validate($aArgs['identifier']) && !empty($statusDeleted)) {
            StatusModel::delete(['identifier' => $aArgs['identifier']]);

            HistoryController::add([
                'tableName' => 'status',
                'recordId'  => $statusDeleted[0]['id'],
                'eventType' => 'DEL',
                'eventId'   => 'statusdel',
                'info'       => _STATUS_DELETED . ' : ' . $statusDeleted[0]['id']
            ]);
        } else {
            return $response->withStatus(500)->withJson(['errors' => 'identifier not valid']);
        }

        return $response->withJson(['statuses' => StatusModel::get()]);
    }

    protected static function manageValue($request)
    {
        foreach ($request as $key => $value) {
            if (in_array($key, ['is_system', 'can_be_searched', 'can_be_modified'])) {
                if (empty($value)) {
                    $request[$key] = 'N';
                } else {
                    $request[$key] = 'Y';
                }
            }
        }

        $request['is_system'] = 'N';

        return $request;
    }

    protected function control($request, $mode)
    {
        $errors = [];

        if (!Validator::notEmpty()->validate($request['id'])) {
            array_push($errors, _ID . ' ' . _EMPTY);
        } elseif ($mode == 'create') {
            $obj = StatusModel::getById(['id' => $request['id']]);

            if (!empty($obj)) {
                array_push(
                    $errors,
                    _ID . ' ' . $obj['id'] . ' ' . _ALREADY_EXISTS
                );
            }
        } elseif ($mode == 'update') {
            $obj = StatusModel::getByIdentifier(['identifier' => $request['identifier']]);
            
            if (empty($obj)) {
                array_push(
                    $errors,
                    $request['identifier'] . ' ' . _NOT_EXISTS
                );
            }
        }

        if (!Validator::regex('/^[\w.-]*$/')->validate($request['id']) ||
            !Validator::length(1, 10)->validate($request['id']) ||
            !Validator::notEmpty()->validate($request['id'])) {
            array_push($errors, 'Invalid id value');
        }

        if (!Validator::notEmpty()->validate($request['label_status']) ||
            !Validator::length(1, 50)->validate($request['label_status'])) {
            array_push($errors, 'Invalid label_status value');
        }

        if (Validator::notEmpty()->validate($request['is_system']) &&
            !Validator::contains('Y')->validate($request['is_system']) &&
            !Validator::contains('N')->validate($request['is_system'])
        ) {
            array_push($errors, 'Invalid is_system value');
        }

        if (!Validator::notEmpty()->validate($request['img_filename']) ||
            !Validator::length(1, 255)->validate($request['img_filename'])
        ) {
            array_push($errors, 'Invalid img_filename value');
        }

        if (Validator::notEmpty()->validate($request['maarch_module'] ?? null) &&
            !Validator::length(null, 255)->validate($request['maarch_module'])
        ) {
            array_push($errors, 'Invalid maarch_module value');
        }

        if (Validator::notEmpty()->validate($request['can_be_searched']) &&
            !Validator::contains('Y')->validate($request['can_be_searched']) &&
            !Validator::contains('N')->validate($request['can_be_searched'])
        ) {
            array_push($errors, 'Invalid can_be_searched value');
        }

        if (Validator::notEmpty()->validate($request['can_be_modified']) &&
            !Validator::contains('Y')->validate($request['can_be_modified']) &&
            !Validator::contains('N')->validate($request['can_be_modified'])
        ) {
            array_push($errors, 'Invalid can_be_modified value');
        }

        return $errors;
    }
}
