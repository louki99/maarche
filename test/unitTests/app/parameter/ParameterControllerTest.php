<?php

/**
* Copyright Maarch since 2008 under licence GPLv3.
* See LICENCE.txt file at the root folder for more details.
* This file is part of Maarch software.
*
*/

namespace MaarchCourrier\Tests\app\parameter;

use MaarchCourrier\Tests\CourrierTestCase;
use Parameter\controllers\ParameterController;
use SrcCore\http\Response;

class ParameterControllerTest extends CourrierTestCase
{
    public function testCreate()
    {
        $parameterController = new ParameterController();

        //  CREATE
        $args = [
            'id'                    => 'TEST-PARAMETER123',
            'description'           => 'TEST PARAMETER123 DESCRIPTION',
            'param_value_string'    => '20.12'
        ];
        $fullRequest = $this->createRequestWithBody('POST', $args);

        $response     = $parameterController->create($fullRequest, new Response());
        $responseBody = json_decode((string)$response->getBody());

        $this->assertSame('success', $responseBody->success);

        //  READ
        $request = $this->createRequest('GET');
        $response       = $parameterController->getById($request, new Response(), ['id' => 'TEST-PARAMETER123']);
        $responseBody   = json_decode((string)$response->getBody());

        $this->assertSame('TEST-PARAMETER123', $responseBody->parameter->id);
        $this->assertSame('TEST PARAMETER123 DESCRIPTION', $responseBody->parameter->description);
        $this->assertSame('20.12', $responseBody->parameter->param_value_string);
    }

    public function testUpdate()
    {
        $parameterController = new ParameterController();

        //  UPDATE
        $args = [
            'description'           => 'TEST PARAMETER123 DESCRIPTION UPDATED',
            'param_value_string'    => '20.12.22'
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $args);

        $response     = $parameterController->update($fullRequest, new Response(), ['id' => 'TEST-PARAMETER123']);
        $this->assertSame(204, $response->getStatusCode());

        //  READ
        $request = $this->createRequest('GET');
        $response       = $parameterController->getById($request, new Response(), ['id' => 'TEST-PARAMETER123']);
        $responseBody   = json_decode((string)$response->getBody());

        $this->assertSame('TEST-PARAMETER123', $responseBody->parameter->id);
        $this->assertSame('TEST PARAMETER123 DESCRIPTION UPDATED', $responseBody->parameter->description);
        $this->assertSame('20.12.22', $responseBody->parameter->param_value_string);
    }

    public function testGet()
    {
        $parameterController = new ParameterController();

        //  GET
        $request = $this->createRequest('GET');
        $response       = $parameterController->get($request, new Response());
        $responseBody   = json_decode((string)$response->getBody());

        $this->assertIsArray($responseBody->parameters);
        $this->assertNotNull($responseBody->parameters);
    }

    public function testDelete()
    {
        $parameterController = new ParameterController();

        //  DELETE
        $request = $this->createRequest('DELETE');
        $response       = $parameterController->delete($request, new Response(), ['id' => 'TEST-PARAMETER123']);
        $responseBody   = json_decode((string)$response->getBody());

        $this->assertIsArray($responseBody->parameters);

        //  READ
        $request = $this->createRequest('GET');
        $response       = $parameterController->getById($request, new Response(), ['id' => 'TEST-PARAMETER123']);
        $responseBody   = json_decode((string)$response->getBody());

        $this->assertSame('Parameter not found', $responseBody->errors);
    }
}
