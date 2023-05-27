<?php

/**
 * Copyright Maarch since 2008 under licence GPLv3.
 * See LICENCE.txt file at the root folder for more details.
 * This file is part of Maarch software.
 *
 */

namespace MaarchCourrier\Tests\app\priority;

use MaarchCourrier\Tests\CourrierTestCase;
use Priority\controllers\PriorityController;
use SrcCore\http\Response;
use User\models\UserModel;

class PriorityControllerTest extends CourrierTestCase
{
    private static $id = null;
    private static ?array $priorities = null;

    public function testCreate()
    {
        $priorityController = new PriorityController();

        //  CREATE
        $body = [
            'label'             => 'TEST-OVER-URGENT',
            'color'             => '#ffffff',
            'delays'            => '72',
        ];
        $fullRequest = $this->createRequestWithBody('POST', $body);

        $response     = $priorityController->create($fullRequest, new Response());
        $responseBody = json_decode((string)$response->getBody());

        self::$id = $responseBody->priority;

        $this->assertIsString(self::$id);

        //  READ
        $request = $this->createRequest('GET');
        $response       = $priorityController->getById($request, new Response(), ['id' => self::$id]);
        $responseBody   = json_decode((string)$response->getBody());

        $this->assertSame(self::$id, $responseBody->priority->id);
        $this->assertSame('TEST-OVER-URGENT', $responseBody->priority->label);
        $this->assertSame('#ffffff', $responseBody->priority->color);
        $this->assertSame(72, $responseBody->priority->delays);

        // fail
        $body = [
        ];
        $fullRequest = $this->createRequestWithBody('POST', $body);

        $response     = $priorityController->create($fullRequest, new Response());
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);

        $this->assertSame('Body (label, color or delays) is empty or type is incorrect', $responseBody['errors']);

        $body = [
            'label'             => 'TEST-OVER-URGENT',
            'color'             => '#ffffff',
            'delays'            => '72',
        ];
        $fullRequest = $this->createRequestWithBody('POST', $body);

        $response     = $priorityController->create($fullRequest, new Response());
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);

        $this->assertSame(_PRIORITY_DELAY_ALREADY_SET, $responseBody['errors']);

        $GLOBALS['login'] = 'bbain';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        $body = [
        ];
        $fullRequest = $this->createRequestWithBody('POST', $body);

        $response     = $priorityController->create($fullRequest, new Response());
        $this->assertSame(403, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);

        $this->assertSame('Service forbidden', $responseBody['errors']);

        $GLOBALS['login'] = 'superadmin';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];
    }

    public function testGet()
    {
        $priorityController = new PriorityController();

        //  GET
        $request = $this->createRequest('GET');
        $response       = $priorityController->get($request, new Response());
        $responseBody   = json_decode((string)$response->getBody());

        $this->assertIsArray($responseBody->priorities);
        $this->assertNotNull($responseBody->priorities);
    }

    public function testUpdate()
    {
        $priorityController = new PriorityController();

        //  UPDATE
        $args = [
            'label'             => 'TEST-OVER-URGENT-UPDATED',
            'color'             => '#f2f2f2',
            'delays'            => '64',
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $args);

        $response     = $priorityController->update($fullRequest, new Response(), ['id' => self::$id]);
        $responseBody = json_decode((string)$response->getBody());

        $this->assertSame('success', $responseBody->success);

        //  READ
        $request = $this->createRequest('GET');
        $response       = $priorityController->getById($request, new Response(), ['id' => self::$id]);
        $responseBody   = json_decode((string)$response->getBody());

        $this->assertSame(self::$id, $responseBody->priority->id);
        $this->assertSame('TEST-OVER-URGENT-UPDATED', $responseBody->priority->label);
        $this->assertSame('#f2f2f2', $responseBody->priority->color);
        $this->assertSame(64, $responseBody->priority->delays);

        // fail
        $body = [
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $body);

        $response     = $priorityController->update($fullRequest, new Response(), ['id' => self::$id]);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);

        $this->assertSame('Body (label, color or delays) is empty or type is incorrect', $responseBody['errors']);

        $body = [
            'label'             => 'TEST-OVER-URGENT',
            'color'             => '#ffffff',
            'delays'            => '64',
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $body);

        $response     = $priorityController->update($fullRequest, new Response(), ['id' => ((int) self::$id) * 1000]);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);

        $this->assertSame(_PRIORITY_DELAY_ALREADY_SET, $responseBody['errors']);

        $GLOBALS['login'] = 'bbain';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        $body = [
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $body);

        $response     = $priorityController->update($fullRequest, new Response(), ['id' => self::$id]);
        $this->assertSame(403, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);

        $this->assertSame('Service forbidden', $responseBody['errors']);

        $GLOBALS['login'] = 'superadmin';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];
    }

    public function testDelete()
    {
        $priorityController = new PriorityController();

        //  DELETE
        $request = $this->createRequest('DELETE');
        $response       = $priorityController->delete($request, new Response(), ['id' => self::$id]);
        $responseBody   = json_decode((string)$response->getBody());

        $this->assertIsArray($responseBody->priorities);

        //  READ
        $request = $this->createRequest('GET');
        $response       = $priorityController->getById($request, new Response(), ['id' => self::$id]);
        $responseBody   = json_decode((string)$response->getBody());

        $this->assertSame('Priority not found', $responseBody->errors);

        // Fail
        $GLOBALS['login'] = 'bbain';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        $request = $this->createRequest('DELETE');

        $response     = $priorityController->delete($request, new Response(), ['id' => self::$id]);
        $this->assertSame(403, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);

        $this->assertSame('Service forbidden', $responseBody['errors']);

        $GLOBALS['login'] = 'superadmin';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];
    }

    public function testGetSorted()
    {
        $priorityController = new PriorityController();

        //  GET
        $request = $this->createRequest('GET');
        $response       = $priorityController->getSorted($request, new Response());
        $responseBody   = json_decode((string)$response->getBody(), true);

        $this->assertIsArray($responseBody['priorities']);
        $this->assertNotEmpty($responseBody['priorities']);
        
        foreach ($responseBody['priorities'] as $value) {
            $this->assertNotEmpty($value['id']);
            $this->assertNotEmpty($value['label']);
        }

        self::$priorities = $responseBody['priorities'];

        // Fail
        $GLOBALS['login'] = 'bbain';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        $request = $this->createRequest('GET');

        $response     = $priorityController->getSorted($request, new Response());
        $this->assertSame(403, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);

        $this->assertSame('Service forbidden', $responseBody['errors']);

        $GLOBALS['login'] = 'superadmin';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];
    }

    public function testUpdateSorted()
    {
        $priorityController = new PriorityController();

        //  PUT
        $priority2 = self::$priorities[1];
        self::$priorities[1] = self::$priorities[0];
        self::$priorities[0] = $priority2;

        $fullRequest = $this->createRequestWithBody('PUT', self::$priorities);

        $response       = $priorityController->updateSort($fullRequest, new Response());
        $responseBody   = json_decode((string)$response->getBody(), true);

        $this->assertIsArray($responseBody['priorities']);
        $this->assertNotEmpty($responseBody['priorities']);

        foreach ($responseBody['priorities'] as $value) {
            $this->assertNotEmpty($value['id']);
            $this->assertNotEmpty($value['label']);
        }

        // fail
        $GLOBALS['login'] = 'bbain';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        $body = [
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $body);

        $response     = $priorityController->updateSort($fullRequest, new Response());
        $this->assertSame(403, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);

        $this->assertSame('Service forbidden', $responseBody['errors']);

        $GLOBALS['login'] = 'superadmin';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];
    }
}
