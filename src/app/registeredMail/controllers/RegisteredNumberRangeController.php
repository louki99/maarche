<?php

/**
* Copyright Maarch since 2008 under licence GPLv3.
* See LICENCE.txt file at the root folder for more details.
* This file is part of Maarch software.
*/

/**
 * @brief Registered Number Range Controller
 * @author dev@maarch.org
 */

namespace RegisteredMail\controllers;

use Group\controllers\PrivilegeController;
use History\controllers\HistoryController;
use RegisteredMail\models\RegisteredNumberRangeModel;
use Respect\Validation\Validator;
use Slim\Psr7\Request;
use SrcCore\http\Response;

class RegisteredNumberRangeController
{
    public function get(Request $request, Response $response)
    {
        $ranges = RegisteredNumberRangeModel::get();

        foreach ($ranges as $key => $range) {
            $fullness = $range['current_number'] - $range['range_start'];
            $rangeSize = $range['range_end'] - $range['range_start'] + 1;
            $fullness = ($fullness / $rangeSize) * 100;
            $fullness = $fullness < 0 ? 0 : $fullness;
            $fullness = round($fullness, 2);

            $ranges[$key] = [
                'id'                    => $range['id'],
                'registeredMailType'    => $range['type'],
                'trackerNumber'         => $range['tracking_account_number'],
                'rangeStart'            => $range['range_start'],
                'rangeEnd'              => $range['range_end'],
                'creator'               => $range['creator'],
                'creationDate'          => $range['creation_date'],
                'status'                => $range['status'],
                'currentNumber'         => $range['current_number'],
                'fullness'              => $fullness,
            ];
        }

        return $response->withJson(['ranges' => $ranges]);
    }

    public function getById(Request $request, Response $response, array $args)
    {
        if (!PrivilegeController::hasPrivilege(['privilegeId' => 'admin_registered_mail', 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }

        $range = RegisteredNumberRangeModel::getById(['id' => $args['id']]);

        if (empty($range)) {
            return $response->withStatus(400)->withJson(['errors' => 'Range not found']);
        }

        $fullness = $range['current_number'] - $range['range_start'];
        $rangeSize = $range['range_end'] - $range['range_start'] + 1;
        $fullness = ($fullness / $rangeSize) * 100;
        $fullness = $fullness < 0 ? 0 : $fullness;
        $fullness = round($fullness, 2);

        $range = [
            'id'                    => $range['id'],
            'registeredMailType'    => $range['type'],
            'trackerNumber'         => $range['tracking_account_number'],
            'rangeStart'            => $range['range_start'],
            'rangeEnd'              => $range['range_end'],
            'creator'               => $range['creator'],
            'creationDate'          => $range['creation_date'],
            'status'                => $range['status'],
            'currentNumber'         => $range['current_number'],
            'fullness'              => $fullness
        ];

        return $response->withJson(['range' => $range]);
    }

    public function create(Request $request, Response $response)
    {
        if (!PrivilegeController::hasPrivilege(['privilegeId' => 'admin_registered_mail', 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }

        $body = $request->getParsedBody();

        if (!Validator::stringType()->notEmpty()->validate($body['registeredMailType'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body registeredMailType is empty or not a string']);
        }
        if (!Validator::stringType()->notEmpty()->validate($body['trackerNumber'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body trackerNumber is empty or not a string']);
        }
        if (!Validator::notEmpty()->intVal()->validate($body['rangeStart'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body rangeStart is empty or not an integer']);
        }
        if (!Validator::notEmpty()->intVal()->validate($body['rangeEnd'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body rangeEnd is empty or not an integer']);
        }
        if ($body['rangeStart'] >= $body['rangeEnd']) {
            return $response->withStatus(400)->withJson(['errors' => 'Body rangeStart cannot be larger or equal than rangeEnd', 'lang' => 'rangeStartLargerThanRangeEnd']);
        }

        $ranges = RegisteredNumberRangeModel::get([
            'select' => [1],
            'where'  => ['tracking_account_number = ?'],
            'data'   => [$body['trackerNumber']]
        ]);
        if (!empty($ranges)) {
            return $response->withStatus(400)->withJson(['errors' => 'Body trackerNumber is already used by another range', 'lang' => 'trackingNumberAlreadyUsed']);
        }

        $ranges = RegisteredNumberRangeModel::get([
            'select'  => ['range_start', 'range_end'],
            'where'   => ['type = ?'],
            'data'    => [$body['registeredMailType']],
            'orderBy' => ['range_end desc']
        ]);

        foreach ($ranges as $range) {
            if ($body['rangeStart'] <= $range['range_start'] && $range['range_start'] <= $body['rangeEnd']
                || $body['rangeStart'] <= $range['range_end'] && $range['range_end'] <= $body['rangeEnd']) {
                return $response->withStatus(400)->withJson(['errors' => 'Range overlaps another range', 'lang' => 'rangeOverlaps']);
            }
        }

        $id = RegisteredNumberRangeModel::create([
            'type'                  => $body['registeredMailType'],
            'trackingAccountNumber' => $body['trackerNumber'],
            'rangeStart'            => $body['rangeStart'],
            'rangeEnd'              => $body['rangeEnd'],
            'creator'               => $GLOBALS['id'],
            'status'                => empty($body['status']) ? 'SPD' : $body['status'],
            'currentNumber'         => null
        ]);

        HistoryController::add([
            'tableName' => 'registered_number_range',
            'recordId'  => $id,
            'eventType' => 'ADD',
            'info'      => _REGISTERED_NUMBER_RANGE_CREATED . " : {$id}",
            'moduleId'  => 'registered_number_range',
            'eventId'   => 'registered_number_rangeCreation',
        ]);

        return $response->withJson(['id' => $id]);
    }

    public function update(Request $request, Response $response, array $args)
    {
        if (!PrivilegeController::hasPrivilege(['privilegeId' => 'admin_registered_mail', 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }

        $range = RegisteredNumberRangeModel::getById(['id' => $args['id']]);
        if (empty($range)) {
            return $response->withStatus(400)->withJson(['errors' => 'Range not found']);
        }

        $body = $request->getParsedBody();

        if (!Validator::stringType()->notEmpty()->validate($body['registeredMailType'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body registeredMailType is empty or not a string']);
        }
        if (!Validator::stringType()->notEmpty()->validate($body['trackerNumber'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body trackerNumber is empty or not a string']);
        }
        if (!Validator::notEmpty()->intVal()->validate($body['rangeStart'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body rangeStart is empty or not an integer']);
        }
        if (!Validator::notEmpty()->intVal()->validate($body['rangeEnd'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body rangeEnd is empty or not an integer']);
        }
        if ($body['rangeStart'] >= $body['rangeEnd']) {
            return $response->withStatus(400)->withJson(['errors' => 'Body rangeStart cannot be larger or equal  than rangeEnd', 'lang' => 'rangeStartLargerThanRangeEnd']);
        }

        $ranges = RegisteredNumberRangeModel::get([
            'select' => [1],
            'where'  => ['tracking_account_number = ?', 'id != ?'],
            'data'   => [$body['trackerNumber'], $args['id']]
        ]);
        if (!empty($ranges)) {
            return $response->withStatus(400)->withJson(['errors' => 'Body trackerNumber is already used by another range', 'lang' => 'trackingNumberAlreadyUsed']);
        }

        $ranges = RegisteredNumberRangeModel::get([
            'select'  => ['range_start', 'range_end'],
            'where'   => ['type = ?', 'id != ?'],
            'data'    => [$body['registeredMailType'], $args['id']],
            'orderBy' => ['range_end desc']
        ]);

        foreach ($ranges as $item) {
            if ($body['rangeStart'] <= $item['range_start'] && $item['range_start'] <= $body['rangeEnd']
                || $body['rangeStart'] <= $item['range_end'] && $item['range_end'] <= $body['rangeEnd']) {
                return $response->withStatus(400)->withJson(['errors' => 'Range overlaps another range', 'lang' => 'rangeOverlaps']);
            }
        }

        if ($body['status'] == 'OK' && $range['status'] != 'OK') {
            RegisteredNumberRangeModel::update([
                'set'   => [
                    'status' => 'END',
                    'current_number' => null
                ],
                'where' => ['type = ?', 'status = ?'],
                'data'  => [$body['registeredMailType'], 'OK']
            ]);
        }

        if ($range['status'] != 'SPD' && $body['status'] != $range['status']) {
            RegisteredNumberRangeModel::update([
                'set'   => [
                    'status' => $body['status']
                ],
                'where' => ['id = ?'],
                'data'  => [$args['id']]
            ]);
            return $response->withStatus(204);
        } elseif ($range['status'] != 'SPD' && $body['status'] == $range['status']) {
            return $response->withStatus(400)->withJson(['errors' => 'Range cannot be updated']);
        }

        $currentNumber = $range['current_number'];
        if ($body['status'] == 'OK' && $range['status'] != 'OK' && $currentNumber == null) {
            $currentNumber = $body['rangeStart'];
        } elseif ($body['status'] == 'END') {
            $currentNumber = null;
        }

        RegisteredNumberRangeModel::update([
            'set'   => [
                'type'                    => $body['registeredMailType'],
                'tracking_account_number' => $body['trackerNumber'],
                'range_start'             => $body['rangeStart'],
                'range_end'               => $body['rangeEnd'],
                'status'                  => $body['status'],
                'current_number'          => $currentNumber
            ],
            'where' => ['id = ?'],
            'data'  => [$args['id']]
        ]);

        HistoryController::add([
            'tableName' => 'registered_number_range',
            'recordId'  => $args['id'],
            'eventType' => 'UP',
            'info'      => _REGISTERED_NUMBER_RANGE_UPDATED . " : {$args['id']}",
            'moduleId'  => 'registered_number_range',
            'eventId'   => 'registered_number_rangeModification',
        ]);

        return $response->withStatus(204);
    }

    public function delete(Request $request, Response $response, array $args)
    {
        if (!PrivilegeController::hasPrivilege(['privilegeId' => 'admin_registered_mail', 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }

        $range = RegisteredNumberRangeModel::getById(['id' => $args['id']]);
        if (empty($range)) {
            return $response->withStatus(204);
        }

        if ($range['status'] == 'OK') {
            return $response->withStatus(400)->withJson(['errors' => 'Range cannot be deleted']);
        }

        RegisteredNumberRangeModel::delete([
            'where' => ['id = ?'],
            'data'  => [$args['id']]
        ]);

        HistoryController::add([
            'tableName' => 'registered_number_range',
            'recordId'  => $args['id'],
            'eventType' => 'DEL',
            'info'      => _REGISTERED_NUMBER_RANGE_DELETED . " : {$args['id']}",
            'moduleId'  => 'registered_number_range',
            'eventId'   => 'registeredNumberRangeSuppression',
        ]);

        return $response->withStatus(204);
    }

    public function getLastNumberByType(Request $request, Response $response, array $args)
    {
        if (!PrivilegeController::hasPrivilege(['privilegeId' => 'admin_registered_mail', 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }

        $range = RegisteredNumberRangeModel::get([
            'select'  => ['range_end'],
            'where'   => ['type = ?', 'status in (?)'],
            'data'    => [$args['type'], ['OK', 'SPD']],
            'orderBy' => ['range_end desc'],
            'limit'   => 1
        ]);

        if (empty($range)) {
            return $response->withJson(['lastNumber' => 1]);
        }

        $range = $range[0];

        return $response->withJson(['lastNumber' => $range['range_end']]);
    }
}
