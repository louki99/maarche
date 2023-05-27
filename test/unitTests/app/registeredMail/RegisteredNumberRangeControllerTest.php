<?php

/**
 * Copyright Maarch since 2008 under licence GPLv3.
 * See LICENCE.txt file at the root folder for more details.
 * This file is part of Maarch software.
 *
 */

namespace MaarchCourrier\Tests\app\registeredMail;

use MaarchCourrier\Tests\CourrierTestCase;
use RegisteredMail\controllers\RegisteredNumberRangeController;
use RegisteredMail\models\RegisteredNumberRangeModel;
use SrcCore\http\Response;
use User\models\UserModel;

class RegisteredNumberRangeControllerTest extends CourrierTestCase
{
    private static $id = null;
    private static $id2 = null;

    public function testCreate()
    {
        $registeredNumberRangeController = new RegisteredNumberRangeController();

        //  CREATE
        $body = [
            'registeredMailType' => '2D',
            'trackerNumber'      => 'AZPOKF30KDZP',
            'rangeStart'         => 11,
            'rangeEnd'           => 1000,
        ];
        $fullRequest = $this->createRequestWithBody('POST', $body);

        $response = $registeredNumberRangeController->create($fullRequest, new Response());
        $this->assertSame(200, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertIsInt($responseBody['id']);

        self::$id = $responseBody['id'];

        $body = [
            'registeredMailType' => '2D',
            'trackerNumber'      => 'AZPOKF30KDZP2',
            'rangeStart'         => 1001,
            'rangeEnd'           => 2000,
            'status'             => 'OK'
        ];
        $fullRequest = $this->createRequestWithBody('POST', $body);

        $response = $registeredNumberRangeController->create($fullRequest, new Response());
        $this->assertSame(200, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertIsInt($responseBody['id']);

        self::$id2 = $responseBody['id'];

        //  READ
        $request = $this->createRequest('GET');
        $response = $registeredNumberRangeController->getById($request, new Response(), ['id' => self::$id]);
        $this->assertSame(200, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);

        $this->assertNotEmpty($responseBody['range']);
        $this->assertSame(self::$id, $responseBody['range']['id']);
        $this->assertSame('2D', $responseBody['range']['registeredMailType']);
        $this->assertSame('AZPOKF30KDZP', $responseBody['range']['trackerNumber']);
        $this->assertSame(11, $responseBody['range']['rangeStart']);
        $this->assertSame(1000, $responseBody['range']['rangeEnd']);
        $this->assertSame($GLOBALS['id'], $responseBody['range']['creator']);
        $this->assertNull($responseBody['range']['currentNumber']);
        $this->assertSame(0, $responseBody['range']['fullness']);

        // fail
        $body = [
        ];
        $fullRequest = $this->createRequestWithBody('POST', $body);

        $response = $registeredNumberRangeController->create($fullRequest, new Response());
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Body registeredMailType is empty or not a string', $responseBody['errors']);

        $body = [
            'registeredMailType' => '2D'
        ];
        $fullRequest = $this->createRequestWithBody('POST', $body);

        $response = $registeredNumberRangeController->create($fullRequest, new Response());
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Body trackerNumber is empty or not a string', $responseBody['errors']);

        $body = [
            'registeredMailType' => '2D',
            'trackerNumber'      => 'AZPOKF30KDZP',
        ];
        $fullRequest = $this->createRequestWithBody('POST', $body);

        $response = $registeredNumberRangeController->create($fullRequest, new Response());
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Body rangeStart is empty or not an integer', $responseBody['errors']);

        $body = [
            'registeredMailType' => '2D',
            'trackerNumber'      => 'AZPOKF30KDZP',
            'rangeStart'         => 1,
        ];
        $fullRequest = $this->createRequestWithBody('POST', $body);

        $response = $registeredNumberRangeController->create($fullRequest, new Response());
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Body rangeEnd is empty or not an integer', $responseBody['errors']);

        $body = [
            'registeredMailType' => '2D',
            'trackerNumber'      => 'AZPOKF30KDZP3',
            'rangeStart'         => 500,
            'rangeEnd'           => 1500
        ];
        $fullRequest = $this->createRequestWithBody('POST', $body);

        $response = $registeredNumberRangeController->create($fullRequest, new Response());
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Range overlaps another range', $responseBody['errors']);

        $body = [
            'registeredMailType' => '2D',
            'trackerNumber'      => 'AZPOKF30KDZP',
            'rangeStart'         => 500,
            'rangeEnd'           => 1500
        ];
        $fullRequest = $this->createRequestWithBody('POST', $body);

        $response = $registeredNumberRangeController->create($fullRequest, new Response());
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Body trackerNumber is already used by another range', $responseBody['errors']);

        $GLOBALS['login'] = 'bbain';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        $response = $registeredNumberRangeController->create($fullRequest, new Response());
        $this->assertSame(403, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);

        $this->assertSame('Service forbidden', $responseBody['errors']);

        $GLOBALS['login'] = 'superadmin';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];
    }

    public function testGet()
    {
        $registeredNumberRangeController = new RegisteredNumberRangeController();

        //  GET
        $request = $this->createRequest('GET');
        $response = $registeredNumberRangeController->get($request, new Response());
        $this->assertSame(200, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);

        $this->assertIsArray($responseBody['ranges']);
        $this->assertNotEmpty($responseBody['ranges']);

        $found = false;
        foreach ($responseBody['ranges'] as $range) {
            $this->assertIsArray($range);
            $this->assertNotEmpty($range);
            if ($range['id'] == self::$id) {
                $this->assertNotEmpty($range);
                $this->assertSame(self::$id, $range['id']);
                $this->assertSame('2D', $range['registeredMailType']);
                $this->assertSame('AZPOKF30KDZP', $range['trackerNumber']);
                $this->assertSame(11, $range['rangeStart']);
                $this->assertSame(1000, $range['rangeEnd']);
                $this->assertSame($GLOBALS['id'], $range['creator']);
                $this->assertNull($range['currentNumber']);
                $this->assertSame(0, $range['fullness']);
                $found = true;
            }
        }

        $this->assertSame(true, $found);

        $GLOBALS['login'] = 'superadmin';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];
    }

    public function testGetById()
    {
        $registeredNumberRangeController = new RegisteredNumberRangeController();

        //  GET
        $request = $this->createRequest('GET');
        $response = $registeredNumberRangeController->getById($request, new Response(), ['id' => self::$id]);
        $this->assertSame(200, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);

        $this->assertNotEmpty($responseBody['range']);
        $this->assertSame(self::$id, $responseBody['range']['id']);
        $this->assertSame('2D', $responseBody['range']['registeredMailType']);
        $this->assertSame('AZPOKF30KDZP', $responseBody['range']['trackerNumber']);
        $this->assertSame(11, $responseBody['range']['rangeStart']);
        $this->assertSame(1000, $responseBody['range']['rangeEnd']);
        $this->assertSame($GLOBALS['id'], $responseBody['range']['creator']);
        $this->assertNull($responseBody['range']['currentNumber']);
        $this->assertSame(0, $responseBody['range']['fullness']);

        $GLOBALS['login'] = 'bbain';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        $response = $registeredNumberRangeController->getById($request, new Response(), ['id' => self::$id]);
        $this->assertSame(403, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Service forbidden', $responseBody['errors']);

        $GLOBALS['login'] = 'superadmin';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];
    }

    public function testUpdate()
    {
        $registeredNumberRangeController = new RegisteredNumberRangeController();

        //  UPDATE
        $body = [
            'registeredMailType' => '2D',
            'trackerNumber'      => 'AZPOKF30KDZP',
            'rangeStart'         => 11,
            'rangeEnd'           => 900,
            'status'             => 'OK'
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $body);

        $response = $registeredNumberRangeController->update($fullRequest, new Response(), ['id' => self::$id]);
        $this->assertSame(204, $response->getStatusCode());

        //  READ
        $request = $this->createRequest('GET');
        $response = $registeredNumberRangeController->getById($request, new Response(), ['id' => self::$id]);
        $responseBody = json_decode((string)$response->getBody(), true);

        $this->assertNotEmpty($responseBody['range']);
        $this->assertSame(self::$id, $responseBody['range']['id']);
        $this->assertSame('2D', $responseBody['range']['registeredMailType']);
        $this->assertSame('AZPOKF30KDZP', $responseBody['range']['trackerNumber']);
        $this->assertSame(11, $responseBody['range']['rangeStart']);
        $this->assertSame(900, $responseBody['range']['rangeEnd']);
        $this->assertSame($GLOBALS['id'], $responseBody['range']['creator']);
        $this->assertSame(11, $responseBody['range']['currentNumber']);
        $this->assertSame(0, $responseBody['range']['fullness']);
        $this->assertSame('OK', $responseBody['range']['status']);

        RegisteredNumberRangeModel::update([
            'set'   => [
                'status' => 'SPD'
            ],
            'where' => ['id = ?'],
            'data'  => [self::$id2]
        ]);

        $body = [
            'registeredMailType' => '2D',
            'trackerNumber'      => 'AZPOKF30KDZP2',
            'rangeStart'         => 1001,
            'rangeEnd'           => 2000,
            'status'             => 'OK'
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $body);

        $response = $registeredNumberRangeController->update($fullRequest, new Response(), ['id' => self::$id2]);
        $this->assertSame(204, $response->getStatusCode());

        //  READ
        $request = $this->createRequest('GET');
        $response = $registeredNumberRangeController->getById($request, new Response(), ['id' => self::$id2]);
        $responseBody = json_decode((string)$response->getBody(), true);

        $this->assertNotEmpty($responseBody['range']);
        $this->assertSame(self::$id2, $responseBody['range']['id']);
        $this->assertSame('2D', $responseBody['range']['registeredMailType']);
        $this->assertSame('AZPOKF30KDZP2', $responseBody['range']['trackerNumber']);
        $this->assertSame(1001, $responseBody['range']['rangeStart']);
        $this->assertSame(2000, $responseBody['range']['rangeEnd']);
        $this->assertSame($GLOBALS['id'], $responseBody['range']['creator']);
        $this->assertSame(1001, $responseBody['range']['currentNumber']);
        $this->assertSame(0, $responseBody['range']['fullness']);
        $this->assertSame('OK', $responseBody['range']['status']);

        $response = $registeredNumberRangeController->getById($request, new Response(), ['id' => self::$id]);
        $this->assertSame(200, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('END', $responseBody['range']['status']);
        $this->assertNull($responseBody['range']['currentNumber']);

        RegisteredNumberRangeModel::update([
            'set'   => [
                'status' => 'SPD'
            ],
            'where' => ['id = ?'],
            'data'  => [self::$id]
        ]);

        $body = [
            'registeredMailType' => '2D',
            'trackerNumber'      => 'AZPOKF30KDZP',
            'rangeStart'         => 11,
            'rangeEnd'           => 900,
            'status'             => 'END'
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $body);

        $response = $registeredNumberRangeController->update($fullRequest, new Response(), ['id' => self::$id]);
        $this->assertSame(204, $response->getStatusCode());

        $response = $registeredNumberRangeController->getById($request, new Response(), ['id' => self::$id]);
        $this->assertSame(200, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('END', $responseBody['range']['status']);
        $this->assertNull($responseBody['range']['currentNumber']);

        $body = [
            'registeredMailType' => '2D',
            'trackerNumber'      => 'AZPOKF30KDZP2',
            'rangeStart'         => 1001,
            'rangeEnd'           => 2000,
            'status'             => 'SPD'
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $body);

        $response = $registeredNumberRangeController->update($fullRequest, new Response(), ['id' => self::$id2]);
        $this->assertSame(204, $response->getStatusCode());

        $body = [
            'registeredMailType' => '2D',
            'trackerNumber'      => 'AZPOKF30KDZP2',
            'rangeStart'         => 1001,
            'rangeEnd'           => 2000,
            'status'             => 'OK'
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $body);

        $response = $registeredNumberRangeController->update($fullRequest, new Response(), ['id' => self::$id2]);
        $this->assertSame(204, $response->getStatusCode());

        // fail
        $body = [
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $body);

        $response = $registeredNumberRangeController->update($fullRequest, new Response(), ['id' => self::$id]);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Body registeredMailType is empty or not a string', $responseBody['errors']);

        $body = [
            'registeredMailType' => '2D'
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $body);

        $response = $registeredNumberRangeController->update($fullRequest, new Response(), ['id' => self::$id]);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Body trackerNumber is empty or not a string', $responseBody['errors']);

        $body = [
            'registeredMailType' => '2D',
            'trackerNumber'      => 'AZPOKF30KDZP',
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $body);

        $response = $registeredNumberRangeController->update($fullRequest, new Response(), ['id' => self::$id]);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Body rangeStart is empty or not an integer', $responseBody['errors']);

        $body = [
            'registeredMailType' => '2D',
            'trackerNumber'      => 'AZPOKF30KDZP',
            'rangeStart'         => 1,
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $body);

        $response = $registeredNumberRangeController->update($fullRequest, new Response(), ['id' => self::$id]);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Body rangeEnd is empty or not an integer', $responseBody['errors']);

        $response = $registeredNumberRangeController->update($fullRequest, new Response(), ['id' => self::$id * 1000]);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Range not found', $responseBody['errors']);

        $body = [
            'registeredMailType' => '2D',
            'trackerNumber'      => 'AZPOKF30KDZP',
            'rangeStart'         => 500,
            'rangeEnd'           => 1500,
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $body);

        $response = $registeredNumberRangeController->update($fullRequest, new Response(), ['id' => self::$id]);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Range overlaps another range', $responseBody['errors']);

        $body = [
            'registeredMailType' => '2D',
            'trackerNumber'      => 'AZPOKF30KDZP2',
            'rangeStart'         => 1001,
            'rangeEnd'           => 2000,
            'status'             => 'OK'
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $body);

        $response = $registeredNumberRangeController->update($fullRequest, new Response(), ['id' => self::$id2]);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Range cannot be updated', $responseBody['errors']);

        $body = [
            'registeredMailType' => '2D',
            'trackerNumber'      => 'AZPOKF30KDZP',
            'rangeStart'         => 1001,
            'rangeEnd'           => 2000,
            'status'             => 'OK'
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $body);

        $response = $registeredNumberRangeController->update($fullRequest, new Response(), ['id' => self::$id2]);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Body trackerNumber is already used by another range', $responseBody['errors']);

        $GLOBALS['login'] = 'bbain';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        $body = [
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $body);

        $response = $registeredNumberRangeController->update($fullRequest, new Response(), ['id' => self::$id]);
        $this->assertSame(403, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);

        $this->assertSame('Service forbidden', $responseBody['errors']);

        $GLOBALS['login'] = 'superadmin';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];
    }

    public function testGetLastNumberByType()
    {
        $registeredNumberRangeController = new RegisteredNumberRangeController();

        //  GET
        $request = $this->createRequest('GET');

        $response = $registeredNumberRangeController->getLastNumberByType($request, new Response(), ['type' => '2D']);
        $this->assertSame(200, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame(2000, $responseBody['lastNumber']);

        $response = $registeredNumberRangeController->getLastNumberByType($request, new Response(), ['type' => '2C']);
        $this->assertSame(200, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame(10, $responseBody['lastNumber']);

        $GLOBALS['login'] = 'bbain';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        $response = $registeredNumberRangeController->getLastNumberByType($request, new Response(), ['type' => '2D']);
        $this->assertSame(403, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Service forbidden', $responseBody['errors']);

        $GLOBALS['login'] = 'superadmin';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];
    }

    public function testDelete()
    {
        $registeredNumberRangeController = new RegisteredNumberRangeController();

        //  DELETE
        $request = $this->createRequest('DELETE');

        $response = $registeredNumberRangeController->delete($request, new Response(), ['id' => self::$id]);
        $this->assertSame(204, $response->getStatusCode());

        $response = $registeredNumberRangeController->delete($request, new Response(), ['id' => self::$id]);
        $this->assertSame(204, $response->getStatusCode());

        //  READ
        $request = $this->createRequest('DELETE');
        $response = $registeredNumberRangeController->getById($request, new Response(), ['id' => self::$id]);
        $responseBody = json_decode((string)$response->getBody(), true);

        $this->assertSame('Range not found', $responseBody['errors']);

        // Fail
        $request = $this->createRequest('DELETE');

        $response = $registeredNumberRangeController->delete($request, new Response(), ['id' => self::$id2]);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Range cannot be deleted', $responseBody['errors']);

        RegisteredNumberRangeModel::update([
            'set'   => [
                'status' => 'SPD'
            ],
            'where' => ['id = ?'],
            'data'  => [self::$id2]
        ]);

        $response = $registeredNumberRangeController->delete($request, new Response(), ['id' => self::$id2]);
        $this->assertSame(204, $response->getStatusCode());

        $GLOBALS['login'] = 'bbain';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        $body = [
        ];
        $fullRequest = $this->createRequestWithBody('DELETE', $body);

        $response = $registeredNumberRangeController->delete($fullRequest, new Response(), ['id' => self::$id]);
        $this->assertSame(403, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);

        $this->assertSame('Service forbidden', $responseBody['errors']);

        $GLOBALS['login'] = 'superadmin';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];
    }
}
