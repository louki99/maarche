<?php

/**
* Copyright Maarch since 2008 under licence GPLv3.
* See LICENCE.txt file at the root folder for more details.
* This file is part of Maarch software.
*
*/

namespace MaarchCourrier\Tests\app\customField;

use CustomField\controllers\CustomFieldController;
use SrcCore\http\Response;
use MaarchCourrier\Tests\CourrierTestCase;

class CustomFieldControllerTest extends CourrierTestCase
{
    private static $id = null;

    public function testCreate()
    {
        $customFieldController = new CustomFieldController();

        //  CREATE
        $args = [
            'label'     => 'mon custom',
            'type'      => 'select',
            'mode'      => 'form',
            'values'    => ['one', 'two']
        ];
        $fullRequest = $this->createRequestWithBody('POST', $args);

        $response     = $customFieldController->create($fullRequest, new Response());
        $this->assertSame(200, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody());

        $this->assertIsInt($responseBody->customFieldId);

        self::$id = $responseBody->customFieldId;

        //  Errors
        $response     = $customFieldController->create($fullRequest, new Response());
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody());

        $this->assertSame('Custom field with this label already exists', $responseBody->errors);
    }

    public function testReadList()
    {
        $request = $this->createRequest('GET');

        $customFieldController = new CustomFieldController();
        $response         = $customFieldController->get($request, new Response());
        $responseBody     = json_decode((string)$response->getBody());

        $this->assertNotNull($responseBody->customFields);
    }

    public function testUpdate()
    {
        $customFieldController = new CustomFieldController();

        //  UPDATE
        $args = [
            'label'     => 'mon custom22',
            'mode'      => 'form',
            'values'    => [['key' => 0, 'label' => 'one'], ['key' => 1, 'label' => 'two'], ['key' => 2, 'label' => 'trois']]
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $args);

        $response     = $customFieldController->update($fullRequest, new Response(), ['id' => self::$id]);
        $this->assertSame(200, $response->getStatusCode());

        //  Errors
        $args = [
            'label'     => 'mon custom22',
            'mode'      => 'form',
            'values'    => [['key' => 0, 'label' => 'one']]
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $args);

        $response     = $customFieldController->update($fullRequest, new Response(), ['id' => self::$id]);
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Not enough values sent', $responseBody['errors']);
        $this->assertSame(400, $response->getStatusCode());

        unset($args['label']);
        $fullRequest = $this->createRequestWithBody('PUT', $args);

        $response     = $customFieldController->update($fullRequest, new Response(), ['id' => self::$id]);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody());

        $this->assertSame('Body label is empty or not a string', $responseBody->errors);
    }

    public function testDelete()
    {
        $customFieldController = new CustomFieldController();

        //  UPDATE
        $request = $this->createRequest('DELETE');

        $response     = $customFieldController->delete($request, new Response(), ['id' => self::$id]);
        $this->assertSame(204, $response->getStatusCode());
    }
}
