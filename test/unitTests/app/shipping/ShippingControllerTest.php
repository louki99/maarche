<?php

/**
* Copyright Maarch since 2008 under licence GPLv3.
* See LICENCE.txt file at the root folder for more details.
* This file is part of Maarch software.
*
*/

namespace MaarchCourrier\Tests\app\shipping;

use MaarchCourrier\Tests\CourrierTestCase;
use Resource\controllers\ResController;
use Resource\models\ResModel;
use Shipping\controllers\ShippingController;
use Shipping\controllers\ShippingTemplateController;
use Shipping\models\ShippingModel;
use SrcCore\http\Response;
use User\models\UserModel;

class ShippingControllerTest extends CourrierTestCase
{
    private static $id = null;
    private static $resId = null;

    public function testCreate()
    {
        $shipping    = new ShippingTemplateController();

        $args = [
            'label'           => 'TEST',
            'description'     => 'description du TEST',
            'options'         => [
                'shaping'    => ['color', 'duplexPrinting', 'addressPage'],
                'sendMode'   => 'fast'
            ],
            'fee'             => ['firstPagePrice' => 0, 'nextPagePrice' => 2, 'postagePrice' => 12],
            'entities'        => [1, 2],
            'account'         => ['id' => 'toto', 'password' => '1234']
        ];
        $fullRequest = $this->createRequestWithBody('POST', $args);

        $response     = $shipping->create($fullRequest, new Response());
        $this->assertSame(200, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody());

        $this->assertIsInt($responseBody->shippingId);
        self::$id = $responseBody->shippingId;

        ####### FAIL ##########
        $args = [
            'description' => 'description too long !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!',
            'options'     => [
                'shaping'  => ['color', 'duplexPrinting', 'addressPage'],
                'sendMode' => 'fast'
            ],
            'fee'         => ['firstPagePrice' => 1, 'nextPagePrice' => 2, 'postagePrice' => -12],
            'account'     => ['id' => 'toto', 'password' => ''],
            'entities'    => [99999]
        ];
        $fullRequest = $this->createRequestWithBody('POST', $args);

        $response     = $shipping->create($fullRequest, new Response());
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody());

        $this->assertSame('account id or password is empty', $responseBody->errors[0]);
        $this->assertSame('label is empty or too long', $responseBody->errors[1]);
        $this->assertSame('description is empty or too long', $responseBody->errors[2]);
        $this->assertSame('99999 does not exists', $responseBody->errors[3]);
        $this->assertSame('fee must be an array with positive values', $responseBody->errors[4]);

        $args = [
            'description' => 'description',
            'options'     => [
                'shaping'  => ['color', 'duplexPrinting', 'addressPage'],
                'sendMode' => 'fast'
            ],
            'fee'         => ['firstPagePrice' => 1, 'nextPagePrice' => 2, 'postagePrice' => 12],
            'account'     => ['id' => 'toto', 'password' => ''],
            'entities'    => 'wrong format'
        ];
        $fullRequest = $this->createRequestWithBody('POST', $args);

        $response     = $shipping->create($fullRequest, new Response());
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody());

        $this->assertSame('account id or password is empty', $responseBody->errors[0]);
        $this->assertSame('label is empty or too long', $responseBody->errors[1]);
        $this->assertSame('entities must be an array', $responseBody->errors[2]);

        $GLOBALS['login'] = 'bbain';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        $response  = $shipping->create($fullRequest, new Response());
        $this->assertSame(403, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody());
        $this->assertSame('Service forbidden', $responseBody->errors);

        $GLOBALS['login'] = 'superadmin';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];
    }

    public function testGetById()
    {
        $request = $this->createRequest('GET');
        $shipping    = new ShippingTemplateController();

        $response  = $shipping->getById($request, new Response(), ['id' => self::$id]);
        $this->assertSame(200, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody());

        $this->assertNotEmpty($responseBody);
        $this->assertSame('TEST', $responseBody->shipping->label);
        $this->assertSame('description du TEST', $responseBody->shipping->description);
        $this->assertSame('color', $responseBody->shipping->options->shaping[0]);
        $this->assertSame('duplexPrinting', $responseBody->shipping->options->shaping[1]);
        $this->assertSame('addressPage', $responseBody->shipping->options->shaping[2]);
        $this->assertSame('fast', $responseBody->shipping->options->sendMode);
        $this->assertSame(0, $responseBody->shipping->fee->firstPagePrice);
        $this->assertSame(2, $responseBody->shipping->fee->nextPagePrice);
        $this->assertSame(12, $responseBody->shipping->fee->postagePrice);
        $this->assertNotNull($responseBody->shipping->entities);
        $this->assertNotNull($responseBody->entities);

        ######## ERROR #############
        $response  = $shipping->getById($request, new Response(), ['id' => 999999999]);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody());
        $this->assertSame('Shipping does not exist', $responseBody->errors);

        $response  = $shipping->getById($request, new Response(), ['id' => 'wrong format']);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody());
        $this->assertSame('id is not an integer', $responseBody->errors);

        $GLOBALS['login'] = 'bbain';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        $response  = $shipping->getById($request, new Response(), ['id' => self::$id]);
        $this->assertSame(403, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody());
        $this->assertSame('Service forbidden', $responseBody->errors);

        $GLOBALS['login'] = 'superadmin';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];
    }

    public function testGetList()
    {
        $request = $this->createRequest('GET');
        $shipping    = new ShippingTemplateController();

        $response  = $shipping->get($request, new Response());
        $responseBody = json_decode((string)$response->getBody());

        $this->assertNotNull($responseBody->shippings);

        foreach ($responseBody->shippings as $value) {
            $this->assertIsInt($value->id);
        }

        // Fail
        $GLOBALS['login'] = 'bbain';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        $response  = $shipping->get($request, new Response());
        $this->assertSame(403, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody());
        $this->assertSame('Service forbidden', $responseBody->errors);

        $GLOBALS['login'] = 'superadmin';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];
    }

    public function testUpdate()
    {
        $args = [
            'label'           => 'TEST 2',
            'description'     => 'description du test 2',
            'options'         => [
                'shaping'    => ['color', 'address_page'],
                'sendMode'   => 'fast'
            ],
            'fee'        => ['firstPagePrice' => 10, 'nextPagePrice' => 20, 'postagePrice' => 12],
            'account'    => ['id' => 'toto', 'password' => '1234'],
            'subscribed' => false
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $args);

        $shipping    = new ShippingTemplateController();
        $response = $shipping->update($fullRequest, new Response(), ['id' => self::$id]);

        $responseBody = json_decode((string)$response->getBody());

        $this->assertSame('success', $responseBody->success);

        $request = $this->createRequest('GET');
        $shipping    = new ShippingTemplateController();

        $response  = $shipping->getById($request, new Response(), ['id' => self::$id]);
        $responseBody = json_decode((string)$response->getBody());

        $this->assertNotEmpty($responseBody);
        $this->assertSame('TEST 2', $responseBody->shipping->label);
        $this->assertSame('description du test 2', $responseBody->shipping->description);
        $this->assertSame('color', $responseBody->shipping->options->shaping[0]);
        $this->assertSame('address_page', $responseBody->shipping->options->shaping[1]);
        $this->assertSame('fast', $responseBody->shipping->options->sendMode);
        $this->assertSame(10, $responseBody->shipping->fee->firstPagePrice);
        $this->assertSame(20, $responseBody->shipping->fee->nextPagePrice);
        $this->assertSame(12, $responseBody->shipping->fee->postagePrice);
        $this->assertNotNull($responseBody->entities);

        $args = [
            'description' => 'description',
            'options'     => [
                'shaping'  => ['color', 'duplexPrinting', 'addressPage'],
                'sendMode' => 'fast'
            ],
            'fee'        => ['firstPagePrice' => 1, 'nextPagePrice' => 2, 'postagePrice' => 12],
            'account'    => ['id' => 'toto', 'password' => '1234'],
            'entities'   => 'wrong format',
            'subscribed' => false
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $args);

        $response     = $shipping->update($fullRequest, new Response(), ['id' => 'wrong format']);
        $this->assertSame(500, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody());

        $this->assertSame('id is not an integer', $responseBody->errors[0]);
        $this->assertSame('Shipping does not exist', $responseBody->errors[1]);
        $this->assertSame('label is empty or too long', $responseBody->errors[2]);
        $this->assertSame('entities must be an array', $responseBody->errors[3]);

        $args = [
            'label'       => 'TEST 2',
            'description' => 'description du test 2',
            'options'     => [
                'shaping'  => ['color', 'address_page'],
                'sendMode' => 'fast'
            ],
            'fee'        => ['firstPagePrice' => 10, 'nextPagePrice' => 20, 'postagePrice' => 12],
            'account'    => ['id' => 'toto'],
            'subscribed' => false
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $args);

        $shipping    = new ShippingTemplateController();
        $response = $shipping->update($fullRequest, new Response(), ['id' => self::$id]);

        $responseBody = json_decode((string)$response->getBody());

        $this->assertSame('success', $responseBody->success);

        $GLOBALS['login'] = 'bbain';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        $response  = $shipping->update($request, new Response(), ['id' => self::$id]);
        $this->assertSame(403, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody());
        $this->assertSame('Service forbidden', $responseBody->errors);

        $GLOBALS['login'] = 'superadmin';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];
    }

    public function testDelete()
    {
        $request = $this->createRequest('DELETE');
        $shipping    = new ShippingTemplateController();

        $response = $shipping->delete($request, new Response(), ['id' => self::$id]);
        $responseBody = json_decode((string)$response->getBody());

        $this->assertIsArray($responseBody->shippings);

        ##### FAIL ######
        $response = $shipping->delete($request, new Response(), ['id' => 'myid']);
        $responseBody = json_decode((string)$response->getBody());
        $this->assertSame('id is not an integer', $responseBody->errors);

        $response = $shipping->delete($request, new Response(), ['id' => self::$id * 1000]);
        $responseBody = json_decode((string)$response->getBody());
        $this->assertSame('Shipping does not exist', $responseBody->errors);

        $GLOBALS['login'] = 'bbain';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        $response  = $shipping->delete($request, new Response(), ['id' => self::$id]);
        $this->assertSame(403, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody());
        $this->assertSame('Service forbidden', $responseBody->errors);

        $GLOBALS['login'] = 'superadmin';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];
    }

    public function testInitShipping()
    {
        $request = $this->createRequest('GET');
        $shipping    = new ShippingTemplateController();

        $response  = $shipping->initShipping($request, new Response());
        $responseBody = json_decode((string)$response->getBody());

        $this->assertNotNull($responseBody->entities);

        foreach ($responseBody->entities as $value) {
            $this->assertNotNull($value->entity_id);
            $this->assertNotNull($value->entity_label);
        }

        // Fail
        $GLOBALS['login'] = 'bbain';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        $response  = $shipping->initShipping($request, new Response());
        $this->assertSame(403, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody());
        $this->assertSame('Service forbidden', $responseBody->errors);

        $GLOBALS['login'] = 'superadmin';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];
    }

    public function testGetByResId()
    {
        $GLOBALS['login'] = 'cchaplin';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        $resController = new ResController();

        //  CREATE
        $fileContent = file_get_contents('test/unitTests/samples/test.txt');
        $encodedFile = base64_encode($fileContent);

        $argsMailNew = [
            'modelId'          => 1,
            'status'           => 'NEW',
            'encodedFile'      => $encodedFile,
            'format'           => 'txt',
            'confidentiality'  => false,
            'documentDate'     => '2019-01-01 17:18:47',
            'arrivalDate'      => '2019-01-01 17:18:47',
            'processLimitDate' => '2029-01-01',
            'doctype'          => 102,
            'destination'      => 15,
            'initiator'        => 15,
            'subject'          => 'Breaking News : Superman is alive - PHP unit',
            'typist'           => 19,
            'priority'         => 'poiuytre1357nbvc',
            'followed'         => true,
            'diffusionList'    => [
                [
                    'id'   => 11,
                    'type' => 'user',
                    'mode' => 'dest'
                ]
            ]
        ];
        $fullRequest = $this->createRequestWithBody('POST', $argsMailNew);

        $response     = $resController->create($fullRequest, new Response());
        $responseBody = json_decode((string)$response->getBody(), true);
        self::$resId = $responseBody['resId'];
        $this->assertIsInt(self::$resId);

        ShippingModel::create([
            'userId'            => $GLOBALS['id'],
            'documentId'        => self::$resId,
            'documentType'      => 'resource',
            'options'           => json_encode([
                'shaping'    => ['color', 'duplexPrinting', 'addressPage'],
                'sendMode'   => 'fast'
            ]),
            'fee'               => 2,
            'recipientEntityId' => 13,
            'accountId'         => 'toto',
            'recipients'        => json_encode(['Recipient', 'contact']),
            'actionId'          => 1,
            'sendingId'         => 'sending-id'
        ]);

        $request = $this->createRequest('GET');

        // Fail
        $response  = ShippingController::getByResId($request, new Response(), ['resId' => self::$resId * 1000]);
        $this->assertSame(403, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Document out of perimeter', $responseBody['errors']);

        // Success
        $response  = ShippingController::getByResId($request, new Response(), ['resId' => self::$resId]);
        $this->assertSame(200, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);

        $this->assertSame(self::$resId, $responseBody[0]['documentId']);
        $this->assertSame('resource', $responseBody[0]['documentType']);
        $this->assertSame($GLOBALS['id'], $responseBody[0]['userId']);
        $this->assertSame('2', $responseBody[0]['fee']);
        $this->assertSame(13, $responseBody[0]['recipientEntityId']);

        ResModel::delete([
            'where' => ['res_id in (?)'],
            'data' => [[self::$resId]]
        ]);

        $res = ResModel::getById(['resId' => self::$resId, 'select' => ['*']]);
        $this->assertIsArray($res);
        $this->assertEmpty($res);

        $GLOBALS['login'] = 'superadmin';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];
    }
}
