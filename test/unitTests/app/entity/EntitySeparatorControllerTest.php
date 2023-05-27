<?php

/**
* Copyright Maarch since 2008 under licence GPLv3.
* See LICENCE.txt file at the root folder for more details.
* This file is part of Maarch software.
*
*/

namespace MaarchCourrier\Tests\app\entity;

use Entity\controllers\EntitySeparatorController;
use MaarchCourrier\Tests\CourrierTestCase;
use SrcCore\http\Response;

class EntitySeparatorControllerTest extends CourrierTestCase
{
    public function testCreate()
    {
        $entityController = new EntitySeparatorController();

        //  CREATE
        $args = [
            'type'      => 'qrcode',
            'entities'  => ['PJS']
        ];
        $fullRequest = $this->createRequestWithBody('POST', $args);

        $response     = $entityController->create($fullRequest, new Response());
        $this->assertSame(200, $response->getStatusCode());

        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertNotEmpty($responseBody);

        $args = [
            'type'      => 'barcode',
            'target'    => 'generic'
        ];
        $fullRequest = $this->createRequestWithBody('POST', $args);

        $response     = $entityController->create($fullRequest, new Response());
        $this->assertSame(200, $response->getStatusCode());

        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertNotEmpty($responseBody);

        // ERRORS
        $args = [
            'type'      => 'barcode',
        ];
        $fullRequest = $this->createRequestWithBody('POST', $args);

        $response     = $entityController->create($fullRequest, new Response());
        $this->assertSame(400, $response->getStatusCode());

        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Body entities is not set or empty', $responseBody['errors']);

        $args = [
            'type'      => 'code',
        ];
        $fullRequest = $this->createRequestWithBody('POST', $args);

        $response     = $entityController->create($fullRequest, new Response());
        $this->assertSame(400, $response->getStatusCode());

        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Body type value must be qrcode or barcode', $responseBody['errors']);

        $fullRequest = $this->createRequestWithBody('POST', []);

        $response     = $entityController->create($fullRequest, new Response());
        $this->assertSame(400, $response->getStatusCode());

        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Body type is not set or empty', $responseBody['errors']);


        $GLOBALS['login'] = 'sstar';
        $userInfo = \User\models\UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        $fullRequest = $this->createRequestWithBody('POST', $args);

        $response     = $entityController->create($fullRequest, new Response());
        $this->assertSame(403, $response->getStatusCode());

        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Service forbidden', $responseBody['errors']);

        $GLOBALS['login'] = 'superadmin';
        $userInfo = \User\models\UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];
    }
}
