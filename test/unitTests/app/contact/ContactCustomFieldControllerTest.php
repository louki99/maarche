<?php

/**
* Copyright Maarch since 2008 under licence GPLv3.
* See LICENCE.txt file at the root folder for more details.
* This file is part of Maarch software.
*
*/

namespace MaarchCourrier\Tests\app\contact;

use Contact\controllers\ContactCustomFieldController;
use SrcCore\http\Response;
use MaarchCourrier\Tests\CourrierTestCase;

class ContactCustomFieldControllerTest extends CourrierTestCase
{
    private static $id = null;
    private static $id2 = null;

    public function testCreate()
    {
        $contactCustomFieldController = new ContactCustomFieldController();

        //  CREATE
        $args = [
            'label'     => 'mon custom',
            'type'      => 'select',
            'values'    => ['one', 'two']
        ];
        $fullRequest = $this->createRequestWithBody('POST', $args);

        $response     = $contactCustomFieldController->create($fullRequest, new Response());
        $this->assertSame(200, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);

        $this->assertIsInt($responseBody['id']);

        self::$id = $responseBody['id'];

        $args = [
            'label'  => 'my second custom',
            'type'   => 'select',
            'values' => ['one', 'two']
        ];
        $fullRequest = $this->createRequestWithBody('POST', $args);

        $response     = $contactCustomFieldController->create($fullRequest, new Response());
        $this->assertSame(200, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);

        $this->assertIsInt($responseBody['id']);

        self::$id2 = $responseBody['id'];

        //  Errors
        $args = [
            'label'  => 'mon custom',
            'type'   => 'select',
            'values' => ['one', 'two']
        ];
        $fullRequest = $this->createRequestWithBody('POST', $args);

        $response     = $contactCustomFieldController->create($fullRequest, new Response());
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);

        $this->assertSame('Custom field with this label already exists', $responseBody['errors']);

        $args = [

        ];
        $fullRequest = $this->createRequestWithBody('POST', $args);

        $response     = $contactCustomFieldController->create($fullRequest, new Response());
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Body label is empty or not a string', $responseBody['errors']);

        $args = [
            'label' => 'mon custom'
        ];
        $fullRequest = $this->createRequestWithBody('POST', $args);

        $response     = $contactCustomFieldController->create($fullRequest, new Response());
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Body type is empty or not a string', $responseBody['errors']);

        $args = [
            'label'  => 'mon custom',
            'type'   => 'select',
            'values' => 'wrong format'
        ];
        $fullRequest = $this->createRequestWithBody('POST', $args);

        $response     = $contactCustomFieldController->create($fullRequest, new Response());
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Body values is not an array', $responseBody['errors']);

        $GLOBALS['login'] = 'bbain';
        $userInfo = \User\models\UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        $response     = $contactCustomFieldController->create($fullRequest, new Response());
        $this->assertSame(403, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Service forbidden', $responseBody['errors']);

        $GLOBALS['login'] = 'superadmin';
        $userInfo = \User\models\UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];
    }

    public function testReadList()
    {
        $contactCustomFieldController = new ContactCustomFieldController();

        $request = $this->createRequest('GET');

        $response         = $contactCustomFieldController->get($request, new Response());
        $responseBody     = json_decode((string)$response->getBody(), true);

        $this->assertNotNull($responseBody['customFields']);
    }

    public function testUpdate()
    {
        $contactCustomFieldController = new ContactCustomFieldController();

        //  UPDATE
        $args = [
            'label'     => 'mon custom22',
            'values'    => ['un', 'deux', 'trois']
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $args);

        $response     = $contactCustomFieldController->update($fullRequest, new Response(), ['id' => self::$id]);
        $this->assertSame(204, $response->getStatusCode());

        //  Errors
        unset($args['label']);
        $fullRequest = $this->createRequestWithBody('PUT', $args);

        $response     = $contactCustomFieldController->update($fullRequest, new Response(), ['id' => self::$id]);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);

        $this->assertSame('Body label is empty or not a string', $responseBody['errors']);

        // Fail
        $response     = $contactCustomFieldController->update($fullRequest, new Response(), ['id' => 'wrong format']);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Param id is empty or not an integer', $responseBody['errors']);

        $args = [
            'label'  => 'mon custom',
            'type'   => 'select',
            'values' => 'wrong format'
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $args);

        $response     = $contactCustomFieldController->update($fullRequest, new Response(), ['id' => self::$id]);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Body values is not an array', $responseBody['errors']);

        $args = [
            'label'  => 'mon custom',
            'type'   => 'select',
            'values' => ['one', 'one', 'two']
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $args);

        $response     = $contactCustomFieldController->update($fullRequest, new Response(), ['id' => self::$id]);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Some values have the same name', $responseBody['errors']);

        $args = [
            'label'  => 'mon custom',
            'type'   => 'select',
            'values' => ['one', 'two']
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $args);

        $response     = $contactCustomFieldController->update($fullRequest, new Response(), ['id' => self::$id * 1000]);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Custom field not found', $responseBody['errors']);

        $args = [
            'label'  => 'my second custom',
            'type'   => 'select',
            'values' => ['one', 'two']
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $args);

        $response     = $contactCustomFieldController->update($fullRequest, new Response(), ['id' => self::$id]);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Custom field with this label already exists', $responseBody['errors']);

        $GLOBALS['login'] = 'bbain';
        $userInfo = \User\models\UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        $response     = $contactCustomFieldController->update($fullRequest, new Response(), ['id' => self::$id]);
        $this->assertSame(403, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Service forbidden', $responseBody['errors']);

        $GLOBALS['login'] = 'superadmin';
        $userInfo = \User\models\UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];;
    }

    public function testDelete()
    {
        $contactCustomFieldController = new ContactCustomFieldController();

        //  UPDATE
        $request = $this->createRequest('DELETE');

        $response     = $contactCustomFieldController->delete($request, new Response(), ['id' => self::$id]);
        $this->assertSame(204, $response->getStatusCode());

        $response     = $contactCustomFieldController->delete($request, new Response(), ['id' => self::$id2]);
        $this->assertSame(204, $response->getStatusCode());

        // Fail
        $response     = $contactCustomFieldController->delete($request, new Response(), ['id' => 'wrong format']);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Param id is empty or not an integer', $responseBody['errors']);

        $GLOBALS['login'] = 'bbain';
        $userInfo = \User\models\UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        $response     = $contactCustomFieldController->delete($request, new Response(), ['id' => self::$id]);
        $this->assertSame(403, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Service forbidden', $responseBody['errors']);

        $GLOBALS['login'] = 'superadmin';
        $userInfo = \User\models\UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];;
    }
}
