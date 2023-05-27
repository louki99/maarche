<?php

/**
* Copyright Maarch since 2008 under licence GPLv3.
* See LICENCE.txt file at the root folder for more details.
* This file is part of Maarch software.
*
*/

namespace MaarchCourrier\Tests\app\acknowledgementReceipt;

use AcknowledgementReceipt\controllers\AcknowledgementReceiptController;
use AcknowledgementReceipt\models\AcknowledgementReceiptModel;
use Convert\controllers\ConvertPdfController;
use Docserver\controllers\DocserverController;
use MaarchCourrier\Tests\CourrierTestCase;
use Resource\controllers\ResController;
use Resource\models\ResModel;
use SrcCore\http\Response;
use User\models\UserModel;

class AcknowledgementReceiptControllerTest extends CourrierTestCase
{
    private static $id = null;
    private static $resId = null;

    // TODO change to setUpBeforeClass
    public function testInit()
    {
        $resController = new ResController();

        //  CREATE
        $GLOBALS['login'] = 'cchaplin';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

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
            'subject'          => 'Breaking News : Superman is dead again - PHP unit',
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
        $this->assertIsInt($responseBody['resId']);
        self::$resId = $responseBody['resId'];

        $encodedDocument = ConvertPdfController::convertFromEncodedResource(['encodedResource' => base64_encode($encodedFile), 'extension' => 'txt']);

        $storeResult = DocserverController::storeResourceOnDocServer([
            'collId'            => 'letterbox_coll',
            'docserverTypeId'   => 'ACKNOWLEDGEMENT_RECEIPTS',
            'encodedResource'   => $encodedDocument['encodedResource'],
            'format'            => 'pdf'
        ]);

        self::$id = AcknowledgementReceiptModel::create([
            'resId'             => self::$resId,
            'type'              => 'simple',
            'format'            => 'pdf',
            'userId'            => $GLOBALS['id'],
            'contactId'         => 9,
            'docserverId'       => 'ACKNOWLEDGEMENT_RECEIPTS',
            'path'              => $storeResult['directory'],
            'filename'          => $storeResult['file_destination_name'],
            'fingerprint'       => $storeResult['fingerPrint']
        ]);

        $GLOBALS['login'] = 'superadmin';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];
    }

    public function testCreatePaperAcknowledgement()
    {
        $acknowledgementReceiptController = new AcknowledgementReceiptController();

        // Fail
        $body = [

        ];
        $fullRequest = $this->createRequestWithBody('POST', $body);

        $response     = $acknowledgementReceiptController->createPaperAcknowledgement($fullRequest, new Response());
        $this->assertSame(403, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Resources is not set or empty', $responseBody['errors']);

        $body = [
            'resources' => [self::$id * 1000]
        ];

        $fullRequest = $this->createRequestWithBody('POST', $body);

        $response     = $acknowledgementReceiptController->createPaperAcknowledgement($fullRequest, new Response());
        $this->assertSame(403, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Documents out of perimeter', $responseBody['errors']);

        // Success
        $body = [
            'resources' => [self::$id]
        ];

        $fullRequest = $this->createRequestWithBody('POST', $body);

        $response     = $acknowledgementReceiptController->createPaperAcknowledgement($fullRequest, new Response());
        $headers = $response->getHeaders();

        $this->assertSame('inline; filename=maarch.pdf', $headers['Content-Disposition'][0]);
        $this->assertSame('application/pdf', $headers['Content-Type'][0]);
    }

    public function testGetByResId()
    {
        $acknowledgementReceiptController = new AcknowledgementReceiptController();

        $request = $this->createRequest('POST');

        // Fail
        $response     = $acknowledgementReceiptController->getByResId($request, new Response(), ['resId' => 'wrong format']);
        $this->assertSame(403, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Document out of perimeter', $responseBody['errors']);

        // Success
        $response     = $acknowledgementReceiptController->getByResId($request, new Response(), ['resId' => self::$resId]);
        $this->assertSame(200, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);

        $this->assertIsArray($responseBody);

        $userInfo = UserModel::getByLogin(['login' => 'cchaplin', 'select' => ['id']]);

        $this->assertSame(self::$id, $responseBody[0]['id']);
        $this->assertSame(self::$resId, $responseBody[0]['resId']);
        $this->assertSame('simple', $responseBody[0]['type']);
        $this->assertSame('pdf', $responseBody[0]['format']);
        $this->assertSame($userInfo['id'], $responseBody[0]['userId']);
        $this->assertIsString($responseBody[0]['userLabel']);
        $this->assertIsString($responseBody[0]['creationDate']);
        $this->assertEmpty($responseBody[0]['sendDate']);
        $this->assertIsArray($responseBody[0]['contact']);
    }

    public function testGetById()
    {
        $acknowledgementReceiptController = new AcknowledgementReceiptController();

        $request = $this->createRequest('GET');

        // Fail
        $response     = $acknowledgementReceiptController->getById($request, new Response(), ['id' => 'wrong format']);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Route param id is not an integer', $responseBody['errors']);

        $response     = $acknowledgementReceiptController->getById($request, new Response(), ['id' => self::$id * 1000]);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Acknowledgement receipt does not exist', $responseBody['errors']);

        $GLOBALS['login'] = 'bbain';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        $response     = $acknowledgementReceiptController->getById($request, new Response(), ['id' => self::$id]);
        $this->assertSame(403, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Document out of perimeter', $responseBody['errors']);

        $GLOBALS['login'] = 'superadmin';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        // Success
        $response     = $acknowledgementReceiptController->getById($request, new Response(), ['id' => self::$id]);
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame(200, $response->getStatusCode());

        $this->assertIsArray($responseBody['acknowledgementReceipt']);

        $userInfo = UserModel::getByLogin(['login' => 'cchaplin', 'select' => ['id']]);

        $this->assertSame(self::$id, $responseBody['acknowledgementReceipt']['id']);
        $this->assertSame(self::$resId, $responseBody['acknowledgementReceipt']['resId']);
        $this->assertSame('simple', $responseBody['acknowledgementReceipt']['type']);
        $this->assertSame('pdf', $responseBody['acknowledgementReceipt']['format']);
        $this->assertSame($userInfo['id'], $responseBody['acknowledgementReceipt']['userId']);
        $this->assertIsString($responseBody['acknowledgementReceipt']['userLabel']);
        $this->assertIsString($responseBody['acknowledgementReceipt']['creationDate']);
        $this->assertEmpty($responseBody['acknowledgementReceipt']['sendDate']);
        $this->assertIsArray($responseBody['acknowledgementReceipt']['contact']);
    }

    public function testGetAcknowledgementReceipt()
    {
        $acknowledgementReceiptController = new AcknowledgementReceiptController();

        $request = $this->createRequest('GET');

        // Fail
        $response     = $acknowledgementReceiptController->getAcknowledgementReceipt($request, new Response(), ['id' => self::$id * 1000]);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Acknowledgement receipt does not exist', $responseBody['errors']);

        $GLOBALS['login'] = 'bbain';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        $response     = $acknowledgementReceiptController->getAcknowledgementReceipt($request, new Response(), ['id' => self::$id]);
        $this->assertSame(403, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Document out of perimeter', $responseBody['errors']);

        $GLOBALS['login'] = 'superadmin';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        // Success
        $response     = $acknowledgementReceiptController->getAcknowledgementReceipt($request, new Response(), ['id' => self::$id]);
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame(200, $response->getStatusCode());

        $this->assertIsArray($responseBody);

        $this->assertSame('pdf', $responseBody['format']);
        $this->assertIsString($responseBody['encodedDocument']);
    }

    // TODO change to tearDownAfterClass
    public function testClean()
    {
        AcknowledgementReceiptModel::delete([
            'where' => ['id = ?'],
            'data'  => [self::$resId]
        ]);

        // Delete resource
        ResModel::delete([
            'where' => ['res_id = ?'],
            'data' => [self::$resId]
        ]);

        $res = ResModel::getById(['resId' => self::$resId, 'select' => ['*']]);
        $this->assertIsArray($res);
        $this->assertEmpty($res);
    }
}
