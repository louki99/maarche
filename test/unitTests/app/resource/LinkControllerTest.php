<?php

/**
* Copyright Maarch since 2008 under licence GPLv3.
* See LICENCE.txt file at the root folder for more details.
* This file is part of Maarch software.
*
*/

namespace MaarchCourrier\Tests\app\resource;

use MaarchCourrier\Tests\CourrierTestCase;
use Resource\controllers\LinkController;
use Resource\controllers\ResController;
use SrcCore\http\Response;
use SrcCore\models\DatabaseModel;
use User\models\UserModel;

class LinkControllerTest extends CourrierTestCase
{
    private static $firstResourceId = null;
    private static $secondResourceId = null;

    public function testLinkResources()
    {
        $GLOBALS['login'] = 'cchaplin';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        $resController = new ResController();

        //  CREATE
        $fileContent = file_get_contents('test/unitTests/samples/test.txt');
        $encodedFile = base64_encode($fileContent);
        $args = [
            'modelId'           => 1,
            'encodedFile'       => $encodedFile,
            'format'            => 'txt',
            'status'            => 'NEW',
            'confidentiality'   => false,
            'documentDate'      => '2019-01-01 17:18:47',
            'arrivalDate'       => '2019-01-01 17:18:47',
            'processLimitDate'  => '2029-01-01',
            'doctype'           => 102,
            'destination'       => 15,
            'initiator'         => 15,
            'subject'           => 'Lorsque l\'on se cogne la tête contre un pot et que cela sonne creux, ça n\'est pas forcément le pot qui est vide.',
            'typist'            => 19,
            'priority'          => 'poiuytre1357nbvc',
            'senders'           => [['type' => 'contact', 'id' => 1], ['type' => 'user', 'id' => 21], ['type' => 'entity', 'id' => 1]],
        ];
        $fullRequest = $this->createRequestWithBody('POST', $args);

        $response     = $resController->create($fullRequest, new Response());
        $this->assertSame(200, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody());
        self::$firstResourceId = $responseBody->resId;
        $this->assertIsInt(self::$firstResourceId);

        $response     = $resController->create($fullRequest, new Response());
        $this->assertSame(200, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody());
        self::$secondResourceId = $responseBody->resId;
        $this->assertIsInt(self::$secondResourceId);

        $GLOBALS['login'] = 'superadmin';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];


        $linkController = new LinkController();

        $args = [
            'linkedResources' => [self::$secondResourceId]
        ];
        $fullRequest = $this->createRequestWithBody('POST', $args);
        $response     = $linkController->linkResources($fullRequest, new Response(), ['resId' => self::$firstResourceId]);
        $this->assertSame(204, $response->getStatusCode());


        // ERRORS
        $args['linkedResources'][] = self::$firstResourceId;
        $fullRequest = $this->createRequestWithBody('POST', $args);
        $response     = $linkController->linkResources($fullRequest, new Response(), ['resId' => self::$firstResourceId]);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Body linkedResources contains resource', $responseBody['errors']);

        $GLOBALS['login'] = 'cchaplin';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        $args['linkedResources'] = [9999999];
        $fullRequest = $this->createRequestWithBody('POST', $args);
        $response     = $linkController->linkResources($fullRequest, new Response(), ['resId' => self::$firstResourceId]);
        $this->assertSame(403, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Service forbidden', $responseBody['errors']);

        $GLOBALS['login'] = 'superadmin';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        $args['linkedResources'] = [];
        $fullRequest = $this->createRequestWithBody('POST', $args);
        $response     = $linkController->linkResources($fullRequest, new Response(), ['resId' => self::$firstResourceId]);
        $this->assertSame(403, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Body linkedResources is empty or not an array', $responseBody['errors']);
    }

    public function testGetLinkedResources()
    {
        $linkController = new LinkController();

        //  GET
        $request = $this->createRequest('GET');

        $response     = $linkController->getLinkedResources($request, new Response(), ['resId' => self::$firstResourceId]);
        $this->assertSame(200, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);

        $this->assertNotEmpty($responseBody['linkedResources']);
        $this->assertSame(self::$secondResourceId, $responseBody['linkedResources'][0]['resId']);
        $this->assertSame('Lorsque l\'on se cogne la tête contre un pot et que cela sonne creux, ça n\'est pas forcément le pot qui est vide.', $responseBody['linkedResources'][0]['subject']);
        $this->assertNotEmpty($responseBody['linkedResources'][0]['status']);
        $this->assertNotEmpty($responseBody['linkedResources'][0]['destination']);
        $this->assertNotEmpty($responseBody['linkedResources'][0]['destinationLabel']);
        $this->assertIsBool($responseBody['linkedResources'][0]['canConvert']);

        $response     = $linkController->getLinkedResources($request, new Response(), ['resId' => self::$secondResourceId]);
        $this->assertSame(200, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);

        $this->assertNotEmpty($responseBody['linkedResources']);
        $this->assertSame(self::$firstResourceId, $responseBody['linkedResources'][0]['resId']);
        $this->assertSame('Lorsque l\'on se cogne la tête contre un pot et que cela sonne creux, ça n\'est pas forcément le pot qui est vide.', $responseBody['linkedResources'][0]['subject']);
        $this->assertNotEmpty($responseBody['linkedResources'][0]['status']);
        $this->assertNotEmpty($responseBody['linkedResources'][0]['destination']);
        $this->assertNotEmpty($responseBody['linkedResources'][0]['destinationLabel']);
        $this->assertIsBool($responseBody['linkedResources'][0]['canConvert']);
    }

    public function testUnlinkResources()
    {
        $linkController = new LinkController();

        //  DELETE
        $request = $this->createRequest('DELETE');

        $response     = $linkController->unlinkResources($request, new Response(), ['resId' => self::$firstResourceId, 'id' => self::$secondResourceId]);
        $this->assertSame(204, $response->getStatusCode());

        $response     = $linkController->getLinkedResources($request, new Response(), ['resId' => self::$firstResourceId]);
        $this->assertSame(200, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);

        $this->assertEmpty($responseBody['linkedResources']);

        $response     = $linkController->getLinkedResources($request, new Response(), ['resId' => self::$secondResourceId]);
        $this->assertSame(200, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);

        $this->assertEmpty($responseBody['linkedResources']);

        DatabaseModel::delete([
            'table' => 'res_letterbox',
            'where' => ['res_id in (?)'],
            'data'  => [[self::$firstResourceId, self::$secondResourceId]]
        ]);
    }
}
