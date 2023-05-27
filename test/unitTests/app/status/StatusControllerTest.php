<?php

/**
* Copyright Maarch since 2008 under licence GPLv3.
* See LICENCE.txt file at the root folder for more details.
* This file is part of Maarch software.
*
*/

namespace MaarchCourrier\Tests\app\status;

use MaarchCourrier\Tests\CourrierTestCase;
use SrcCore\http\Response;
use Status\controllers\StatusController;

class StatusControllerTest extends CourrierTestCase
{
    private static $id = null;

    public function testCreate()
    {
        $status      = new StatusController();

        $args = [
            'id'               => 'TEST',
            'label_status'     => 'TEST',
            'img_filename'     => 'fm-letter-end',
            'can_be_searched'  => 'true',
            'can_be_modified'  => '',
        ];
        $fullRequest = $this->createRequestWithBody('POST', $args);

        $response     = $status->create($fullRequest, new Response());
        $responseBody = json_decode((string)$response->getBody());

        $this->assertIsInt($responseBody->status->identifier);
        self::$id = $responseBody->status->identifier;

        unset($responseBody->status->identifier);

        $compare = [
            'id'               => 'TEST',
            'label_status'     => 'TEST',
            'is_system'        => 'N',
            'img_filename'     => 'fm-letter-end',
            'maarch_module'    => 'apps',
            'can_be_searched'  => 'Y',
            'can_be_modified'  => 'N',
        ];

        $aCompare = json_decode(json_encode($compare), false);
        $this->assertEqualsCanonicalizing($aCompare, $responseBody->status);

        ########## CREATE FAIL ##########
        $args = [
            'id'               => 'TEST',
            'label_status'     => 'TEST',
            'img_filename'     => 'fm-letter-end',
        ];
        $fullRequest = $this->createRequestWithBody('POST', $args);

        $response     = $status->create($fullRequest, new Response());
        $responseBody = json_decode((string)$response->getBody());

        $this->assertSame(_ID . ' TEST ' . _ALREADY_EXISTS, $responseBody->errors[0]);

        ########## CREATE FAIL 2 ##########
        $args = [
            'id'               => 'papa',
            'label_status'     => '',
            'img_filename'     => 'fm-letter-end',
        ];
        $fullRequest = $this->createRequestWithBody('POST', $args);

        $response     = $status->create($fullRequest, new Response());
        $responseBody = json_decode((string)$response->getBody());

        $this->assertSame('Invalid label_status value', $responseBody->errors[0]);
    }

    public function testGetById()
    {
        $request = $this->createRequest('GET');
        $status = new StatusController();

        $response = $status->getById($request, new Response(), ['id' => 'TEST']);
        $responseBody = json_decode((string)$response->getBody());

        $this->assertNotEmpty($responseBody->status);
        $this->assertSame('TEST', $responseBody->status->id);

        // ERROR
        $response  = $status->getById($request, new Response(), ['id' => 'NOTFOUNDSTATUS']);
        $responseBody = json_decode((string)$response->getBody());

        $this->assertSame('id not found', $responseBody->errors);
    }

    public function testGetListUpdateDelete()
    {
        ########## GET LIST ##########
        $request = $this->createRequest('GET');
        $status = new StatusController();

        $response = $status->get($request, new Response());

        $responseBody = json_decode((string)$response->getBody());
        $this->assertNotNull($responseBody->statuses);

        foreach ($responseBody->statuses as $value) {
            $this->assertIsInt($value->identifier);
        }

        ########## GETBYIDENTIFIER ##########
        $response     = $status->getByIdentifier($request, new Response(), ['identifier' => self::$id]);
        $responseBody = json_decode((string)$response->getBody());

        $this->assertNotNull($responseBody->status);
        $this->assertNotNull($responseBody->statusImages);

        $compare = [
            'identifier'       => self::$id,
            'id'               => 'TEST',
            'label_status'     => 'TEST',
            'is_system'        => 'N',
            'img_filename'     => 'fm-letter-end',
            'maarch_module'    => 'apps',
            'can_be_searched'  => 'Y',
            'can_be_modified'  => 'N',
        ];

        $aCompare = json_decode(json_encode($compare), false);
        $this->assertEqualsCanonicalizing($aCompare, $responseBody->status[0]);

        ########## GETBYIDENTIFIER FAIL ##########
        $response     = $status->getByIdentifier($request, new Response(), ['identifier' => -1]);
        $responseBody = json_decode((string)$response->getBody());

        $this->assertSame('identifier not found', $responseBody->errors);


        ########## UPDATE ##########
        $args = [
            'id'           => 'TEST',
            'label_status' => 'TEST AFTER UP',
            'img_filename' => 'fm-letter-end',
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $args);

        $response = $status->update($fullRequest, new Response(), ['identifier' => self::$id]);

        $responseBody = json_decode((string)$response->getBody());

        $compare = [
            'identifier'       => self::$id,
            'id'               => 'TEST',
            'label_status'     => 'TEST AFTER UP',
            'is_system'        => 'N',
            'img_filename'     => 'fm-letter-end',
            'maarch_module'    => 'apps',
            'can_be_searched'  => 'Y',
            'can_be_modified'  => 'N',
        ];

        $aCompare = json_decode(json_encode($compare), false);

        $this->assertEqualsCanonicalizing($aCompare, $responseBody->status);

        ########## UPDATE FAIL ##########
        $args = [
            'id'           => 'PZOEIRUTY',
            'label_status' => 'TEST AFTER UP',
            'img_filename' => 'fm-letter-end',
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $args);

        $response = $status->update($fullRequest, new Response(), ['identifier' => -1]);

        $responseBody = json_decode((string)$response->getBody());
        $this->assertSame('-1 ' . _NOT_EXISTS, $responseBody->errors[0]);


        ########## DELETE ##########
        $request = $this->createRequest('DELETE');

        $response = $status->delete($request, new Response(), ['identifier'=> self::$id]);

        $this->assertMatchesRegularExpression('/statuses/', (string)$response->getBody());
    }

    public function testGetNewInformations()
    {
        $request = $this->createRequest('GET');
        $status      = new StatusController();

        $response = $status->getNewInformations($request, new Response());

        $responseBody = json_decode((string)$response->getBody());

        $this->assertNotNull($responseBody->statusImages);
    }
}
