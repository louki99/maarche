<?php

/**
* Copyright Maarch since 2008 under licence GPLv3.
* See LICENCE.txt file at the root folder for more details.
* This file is part of Maarch software.
*
*/

namespace MaarchCourrier\Tests\app\resource;

use Basket\models\GroupBasketModel;
use Convert\models\AdrModel;
use DateInterval;
use DateTime;
use Folder\models\FolderModel;
use IndexingModel\models\IndexingModelFieldModel;
use MaarchCourrier\Tests\CourrierTestCase;
use Resource\controllers\DepartmentController;
use Resource\controllers\ResController;
use Resource\controllers\StoreController;
use Resource\models\ResModel;
use SrcCore\http\Response;
use Tag\models\TagModel;
use User\models\UserModel;

class ResControllerTest extends CourrierTestCase
{
    private static $id = null;
    private static $id2 = null;
    private static $id3 = null;

    public function testGetDepartmentById()
    {
        $department = DepartmentController::getById(['id' => '75']);
        $this->assertSame('Paris', $department);

        $department = DepartmentController::getById(['id' => 'not a french department']);
        $this->assertIsString($department);
        $this->assertEmpty($department);
    }

    public function testCreate()
    {
        $GLOBALS['login'] = 'cchaplin';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        $resController = new ResController();

        //  CREATE
        $fileContent = file_get_contents('test/unitTests/samples/test.txt');
        $encodedFile = base64_encode($fileContent);

        $body = [
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
            'senders'          => [['type' => 'contact', 'id' => 1], ['type' => 'user', 'id' => 21], ['type' => 'entity', 'id' => 1]],
        ];
        $fullRequest = $this->createRequestWithBody('POST', $body);

        $response     = $resController->create($fullRequest, new Response());
        $responseBody = json_decode((string)$response->getBody());
        self::$id = $responseBody->resId;
        $this->assertIsInt(self::$id);

        $body = [
            'modelId'          => 2,
            'status'           => 'NEW',
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
            'senders'          => [['type' => 'contact', 'id' => 1], ['type' => 'user', 'id' => 21], ['type' => 'entity', 'id' => 1]],
        ];
        $fullRequest = $this->createRequestWithBody('POST', $body);

        $response     = $resController->create($fullRequest, new Response());
        $this->assertSame(200, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertIsInt($responseBody['resId']);
        self::$id2 = $responseBody['resId'];

        $fileContent = file_get_contents('modules/templates/templates/styles/AR_Masse_Simple.docx');
        $encodedFile = base64_encode($fileContent);

        $body = [
            'modelId'          => 2,
            'status'           => 'INIT',
            'confidentiality'  => false,
            'encodedFile'      => $encodedFile,
            'format'           => 'docx',
            'arrivalDate'      => '2019-01-01 17:18:47',
            'processLimitDate' => '2029-01-01',
            'doctype'          => 102,
            'destination'      => 15,
            'initiator'        => 15,
            'subject'          => 'Breaking News : Superman is alive (again) - PHP unit',
            'typist'           => 19,
            'priority'         => 'poiuytre1357nbvc',
            'senders'          => [['type' => 'contact', 'id' => 1], ['type' => 'user', 'id' => 21], ['type' => 'entity', 'id' => 1]],
        ];
        $fullRequest = $this->createRequestWithBody('POST', $body);

        $response     = $resController->create($fullRequest, new Response());
        $this->assertSame(200, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertIsInt($responseBody['resId']);
        self::$id3 = $responseBody['resId'];

        //  READ
        $res = ResModel::getById(['resId' => self::$id, 'select' => ['*']]);

        $this->assertIsArray($res);

        $this->assertSame('Breaking News : Superman is alive - PHP unit', $res['subject']);
        $this->assertSame(102, $res['type_id']);
        $this->assertSame('txt', $res['format']);
        $this->assertSame('NEW', $res['status']);
        $this->assertSame(19, $res['typist']);
        $this->assertNotNull($res['destination']);
        $this->assertNotNull($res['initiator']);

        //  ERROR
        $fileContent = file_get_contents('test/unitTests/samples/test.txt');
        $encodedFile = base64_encode($fileContent);

        $body = [
            'status'        => 'NEW',
            'encodedFile'   => $encodedFile,
            'format'        => 'txt',
            'confidentiality'   => false,
            'documentDate'  => '2019-01-01 17:18:47',
            'arrivalDate'   => '2019-01-01 17:18:47',
            'processLimitDate'  => '2029-01-01',
            'doctype'       => 102,
            'destination'   => 15,
            'initiator'     => 15,
            'subject'       => 'Breaking News : Superman is alive - PHP unit',
            'typist'        => 19,
            'priority'      => 'poiuytre1357nbvc',
            'tags'          => [1, 2],
            'folders'       => [1, 2],
        ];
        $fullRequest = $this->createRequestWithBody('POST', $body);

        $response     = $resController->create($fullRequest, new Response());
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Body modelId is empty or not an integer', $responseBody['errors']);

        // Errors from ResourceControlController::controlResource
        $body = [];
        $fullRequest = $this->createRequestWithBody('POST', $body);

        $response     = $resController->create($fullRequest, new Response());
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Body is not set or empty', $responseBody['errors']);

        $body = [
            'doctype' => 'wrong format'
        ];
        $fullRequest = $this->createRequestWithBody('POST', $body);
        $response     = $resController->create($fullRequest, new Response());
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Body doctype is not an integer', $responseBody['errors']);

        $body = [
            'doctype' => 102,
            'modelId' => 1,
            'status'  => 1 // wrong format
        ];
        $fullRequest = $this->createRequestWithBody('POST', $body);
        $response     = $resController->create($fullRequest, new Response());
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Body status is empty or not a string', $responseBody['errors']);

        $body = [
            'doctype' => 102000000,
            'modelId' => 1,
            'status'  => 'NEW'
        ];
        $fullRequest = $this->createRequestWithBody('POST', $body);
        $response     = $resController->create($fullRequest, new Response());
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Body doctype does not exist', $responseBody['errors']);


        $body = [
            'doctype' => 102,
            'modelId' => 1000,
            'status'  => 'NEW'
        ];
        $fullRequest = $this->createRequestWithBody('POST', $body);
        $response     = $resController->create($fullRequest, new Response());
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Body modelId does not exist', $responseBody['errors']);

        $body = [
            'doctype'     => 102,
            'modelId'     => 1,
            'status'      => 'NEW',
            'encodedFile' => $encodedFile,
            'format'      => 'docx'
        ];
        $fullRequest = $this->createRequestWithBody('POST', $body);
        $response     = $resController->create($fullRequest, new Response());
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Format with this mimeType is not allowed : docx text/plain', $responseBody['errors']);

        $body = [
            'doctype'      => 102,
            'modelId'      => 1,
            'status'       => 'NEW',
            'encodedFile' => $encodedFile,
            'format'        => 'txt',
            'customFields' => 'wrong format'
        ];
        $fullRequest = $this->createRequestWithBody('POST', $body);
        $response     = $resController->create($fullRequest, new Response());
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Body customFields is not an array', $responseBody['errors']);

        $body = [
            'doctype'      => 102,
            'modelId'      => 1,
            'status'       => 'NEW',
            'encodedFile' => $encodedFile,
            'format'        => 'txt',
            'customFields' => [1000]
        ];
        $fullRequest = $this->createRequestWithBody('POST', $body);
        $response     = $resController->create($fullRequest, new Response());
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Body customFields : One or more custom fields do not exist', $responseBody['errors']);

        $body = [
            'doctype' => 102,
            'modelId' => 1,
            'status'  => 'NEW',
            'encodedFile' => $encodedFile,
            'format'        => 'txt',
            'folders' => 'wrong format'
        ];
        $fullRequest = $this->createRequestWithBody('POST', $body);
        $response     = $resController->create($fullRequest, new Response());
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Body folders is not an array', $responseBody['errors']);

        $body = [
            'doctype' => 102,
            'modelId' => 1,
            'status'  => 'NEW',
            'encodedFile' => $encodedFile,
            'format'        => 'txt',
            'folders' => [100000]
        ];
        $fullRequest = $this->createRequestWithBody('POST', $body);
        $response     = $resController->create($fullRequest, new Response());
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Body folders : One or more folders do not exist or are out of perimeter', $responseBody['errors']);

        $body = [
            'doctype' => 102,
            'modelId' => 1,
            'status'  => 'NEW',
            'encodedFile' => $encodedFile,
            'format'        => 'txt',
            'tags'    => 'wrong format'
        ];
        $fullRequest = $this->createRequestWithBody('POST', $body);
        $response     = $resController->create($fullRequest, new Response());
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Body tags is not an array', $responseBody['errors']);

        $body = [
            'doctype' => 102,
            'modelId' => 1,
            'status'  => 'NEW',
            'encodedFile' => $encodedFile,
            'format'        => 'txt',
            'tags'    => [100000]
        ];
        $fullRequest = $this->createRequestWithBody('POST', $body);
        $response     = $resController->create($fullRequest, new Response());
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Body tags : One or more tags do not exist', $responseBody['errors']);

        $body = [
            'doctype' => 102,
            'modelId' => 1,
            'status'  => 'NEW',
            'encodedFile' => $encodedFile,
            'format'        => 'txt',
            'senders' => 'wrong format'
        ];
        $fullRequest = $this->createRequestWithBody('POST', $body);
        $response     = $resController->create($fullRequest, new Response());
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Body senders is not an array', $responseBody['errors']);

        $body = [
            'doctype' => 102,
            'modelId' => 1,
            'status'  => 'NEW',
            'encodedFile' => $encodedFile,
            'format'        => 'txt',
            'senders' => ['wrong format']
        ];
        $fullRequest = $this->createRequestWithBody('POST', $body);
        $response     = $resController->create($fullRequest, new Response());
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Body senders[0] is not an array', $responseBody['errors']);

        $body = [
            'doctype' => 102,
            'modelId' => 1,
            'status'  => 'NEW',
            'encodedFile' => $encodedFile,
            'format'        => 'txt',
            'senders' => [['type' => 'alien']]
        ];
        $fullRequest = $this->createRequestWithBody('POST', $body);
        $response     = $resController->create($fullRequest, new Response());
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Body senders[0][id] is empty or not an integer', $responseBody['errors']);

        $body = [
            'doctype' => 102,
            'modelId' => 1,
            'status'  => 'NEW',
            'encodedFile' => $encodedFile,
            'format'        => 'txt',
            'senders' => [['type' => 'user', 'id' => 1000]]
        ];
        $fullRequest = $this->createRequestWithBody('POST', $body);
        $response     = $resController->create($fullRequest, new Response());
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Body senders[0] id does not exist', $responseBody['errors']);

        $body = [
            'doctype' => 102,
            'modelId' => 1,
            'status'  => 'NEW',
            'encodedFile' => $encodedFile,
            'format'        => 'txt',
            'recipients' => 'wrong format'
        ];
        $fullRequest = $this->createRequestWithBody('POST', $body);
        $response     = $resController->create($fullRequest, new Response());
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Body recipients is not an array', $responseBody['errors']);

        $body = [
            'doctype' => 102,
            'modelId' => 1,
            'status'  => 'NEW',
            'encodedFile' => $encodedFile,
            'format'        => 'txt',
            'recipients' => ['wrong format']
        ];
        $fullRequest = $this->createRequestWithBody('POST', $body);
        $response     = $resController->create($fullRequest, new Response());
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Body recipients[0] is not an array', $responseBody['errors']);

        $body = [
            'doctype' => 102,
            'modelId' => 1,
            'status'  => 'NEW',
            'encodedFile' => $encodedFile,
            'format'        => 'txt',
            'recipients' => [['type' => 'alien']]
        ];
        $fullRequest = $this->createRequestWithBody('POST', $body);
        $response     = $resController->create($fullRequest, new Response());
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Body recipients[0][id] is empty or not an integer', $responseBody['errors']);

        $body = [
            'doctype' => 102,
            'modelId' => 1,
            'status'  => 'NEW',
            'encodedFile' => $encodedFile,
            'format'        => 'txt',
            'recipients' => [['type' => 'user', 'id' => 1000]]
        ];
        $fullRequest = $this->createRequestWithBody('POST', $body);
        $response     = $resController->create($fullRequest, new Response());
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Body recipients[0] id does not exist', $responseBody['errors']);

        $body = [
            'doctype'      => 102,
            'modelId'      => 1,
            'status'       => 'NEW',
            'encodedFile' => $encodedFile,
            'format'        => 'txt',
            'documentDate' => 'wrong format'
        ];
        $fullRequest = $this->createRequestWithBody('POST', $body);
        $response     = $resController->create($fullRequest, new Response());
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Body documentDate is not a date', $responseBody['errors']);

        $dateInTheFuture = new DateTime('tomorrow');
        $dateInTheFuture->add(new DateInterval('P10D'));
        $dateInTheFuture = $dateInTheFuture->format('d-m-Y');
        
        $body = [
            'doctype'      => 102,
            'modelId'      => 1,
            'status'       => 'NEW',
            'encodedFile' => $encodedFile,
            'format'        => 'txt',
            'documentDate' => $dateInTheFuture
        ];
        $fullRequest = $this->createRequestWithBody('POST', $body);
        $response     = $resController->create($fullRequest, new Response());
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Body documentDate cannot be a date in the future', $responseBody['errors']);

        $body = [
            'doctype'     => 102,
            'modelId'     => 1,
            'status'      => 'NEW',
            'encodedFile' => $encodedFile,
            'format'        => 'txt',
            'arrivalDate' => 'wrong format'
        ];
        $fullRequest = $this->createRequestWithBody('POST', $body);
        $response     = $resController->create($fullRequest, new Response());
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Body arrivalDate is not a date', $responseBody['errors']);

        $body = [
            'doctype'     => 102,
            'modelId'     => 1,
            'status'      => 'NEW',
            'encodedFile' => $encodedFile,
            'format'        => 'txt',
            'arrivalDate' => $dateInTheFuture
        ];
        $fullRequest = $this->createRequestWithBody('POST', $body);
        $response     = $resController->create($fullRequest, new Response());
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Body arrivalDate is not a valid date', $responseBody['errors']);

        $body = [
            'doctype'       => 102,
            'modelId'       => 1,
            'status'        => 'NEW',
            'encodedFile' => $encodedFile,
            'format'        => 'txt',
            'departureDate' => 'wrong format'
        ];
        $fullRequest = $this->createRequestWithBody('POST', $body);
        $response     = $resController->create($fullRequest, new Response());
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Body departureDate is not a date', $responseBody['errors']);

        $body = [
            'doctype'       => 102,
            'modelId'       => 1,
            'status'        => 'NEW',
            'documentDate'  => '2020-02-01',
            'encodedFile' => $encodedFile,
            'format'        => 'txt',
            'departureDate' => '2020-01-01'
        ];
        $fullRequest = $this->createRequestWithBody('POST', $body);
        $response     = $resController->create($fullRequest, new Response());
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Body departureDate is not a valid date', $responseBody['errors']);

        $GLOBALS['login'] = 'bbain';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        $body = [
            'modelId'       => 1,
            'status'        => 'NEW',
            'confidentiality'   => false,
            'documentDate'  => '2019-01-01 17:18:47',
            'arrivalDate'   => '2019-01-01 17:18:47',
            'processLimitDate'  => '2029-01-01',
            'doctype'       => 102,
            'destination'   => 15,
            'initiator'     => 15,
            'subject'       => 'Breaking News : Superman is alive - PHP unit',
            'typist'        => 19,
            'priority'      => 'poiuytre1357nbvc',
            'senders'       => [['type' => 'contact', 'id' => 1], ['type' => 'user', 'id' => 21], ['type' => 'entity', 'id' => 1]],
            'encodedFile'   => $encodedFile,
            'format'        => 'txt',
            'diffusionList' => [
                ['id' => 19, 'mode' => 'dest']
            ]
        ];
        $fullRequest = $this->createRequestWithBody('POST', $body);

        $response     = $resController->create($fullRequest, new Response());
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Body initiator does not belong to your entities', $responseBody['errors']);

        $GLOBALS['login'] = 'ddur';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        $response     = $resController->create($fullRequest, new Response());
        $this->assertSame(403, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody());
        $this->assertSame('Service forbidden', $responseBody->errors);

        $GLOBALS['login'] = 'superadmin';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];
    }

    public function testGetById()
    {
        $resController = new ResController();

        $request = $this->createRequest('GET');

        $response     = $resController->getById($request, new Response(), ['resId' => self::$id2]);
        $responseBody = json_decode((string)$response->getBody());

        $this->assertSame(self::$id2, $responseBody->resId);
        $this->assertSame(2, $responseBody->modelId);
        $this->assertSame('outgoing', $responseBody->categoryId);
        $this->assertEmpty($responseBody->chrono);
        $this->assertSame('NEW', $responseBody->status);
        $this->assertEmpty($responseBody->closingDate);
        $this->assertNotEmpty($responseBody->creationDate);
        $this->assertNotEmpty($responseBody->modificationDate);
        $this->assertIsBool($responseBody->integrations->inShipping);
        $this->assertIsBool($responseBody->integrations->inSignatureBook);
        $this->assertSame('Breaking News : Superman is alive - PHP unit', $responseBody->subject);
        $this->assertSame('2029-01-01 00:00:00', $responseBody->processLimitDate);
        $this->assertSame('poiuytre1357nbvc', $responseBody->priority);
        $this->assertSame(102, $responseBody->doctype);
        $this->assertSame(15, $responseBody->destination);
        $this->assertSame('2019-01-01 17:18:47', $responseBody->documentDate);
        $this->assertEmpty($responseBody->arrivalDate);
        $this->assertNotEmpty($responseBody->destinationLabel);
        $this->assertSame("Nouveau courrier pour le service", $responseBody->statusLabel);
        $this->assertIsBool($responseBody->statusAlterable);
        $this->assertSame('Normal', $responseBody->priorityLabel);
        $this->assertSame('#009dc5', $responseBody->priorityColor);
        $this->assertIsArray($responseBody->senders);
        $this->assertIsArray($responseBody->customFields);
        $this->assertIsArray($responseBody->folders);
        foreach ($responseBody->folders as $value) {
            $this->assertIsInt($value);
        }
        $this->assertIsArray($responseBody->tags);
        foreach ($responseBody->tags as $value) {
            $this->assertIsInt($value);
        }
        
        // ERROR
        $GLOBALS['login'] = 'cchaplin';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        $response     = $resController->getById($request, new Response(), ['resId' => 123748]);
        $responseBody = json_decode((string)$response->getBody());
        $this->assertSame('Document out of perimeter', $responseBody->errors);

        $GLOBALS['login'] = 'superadmin';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        // LIGHT
        $aArgs = [
            'light'  => true
        ];
        $fullRequest = $request->withQueryParams($aArgs);
        $response     = $resController->getById($fullRequest, new Response(), ['resId' => self::$id]);
        $responseBody = json_decode((string)$response->getBody());

        $this->assertSame(self::$id, $responseBody->resId);
        $this->assertSame(1, $responseBody->modelId);
        $this->assertSame('incoming', $responseBody->categoryId);
        $this->assertEmpty($responseBody->chrono);
        $this->assertSame('NEW', $responseBody->status);
        $this->assertEmpty($responseBody->closingDate);
        $this->assertNotEmpty($responseBody->creationDate);
        $this->assertNotEmpty($responseBody->modificationDate);
        $this->assertIsBool($responseBody->integrations->inShipping);
        $this->assertIsBool($responseBody->integrations->inSignatureBook);
        $this->assertSame('Breaking News : Superman is alive - PHP unit', $responseBody->subject);
        $this->assertSame('2029-01-01 00:00:00', $responseBody->processLimitDate);
        $this->assertSame('poiuytre1357nbvc', $responseBody->priority);
    }

    public function testUpdate()
    {
        $resController = new ResController();

        // UPDATE
        $fileContent = file_get_contents('test/unitTests/samples/test.txt');
        $encodedFile = base64_encode($fileContent);

        $tag = TagModel::get([
            'select' => ['id'],
            'limit' => 1
        ]);
        $tag = $tag[0]['id'];

        $folder = FolderModel::create([
            'label'     => 'FOLDER TEST',
            'public'    => false,
            'user_id'   => $GLOBALS['id'],
            'parent_id' => null,
            'level'     => 0
        ]);

        $args = [
            'status'           => 'NEW',
            'encodedFile'      => $encodedFile,
            'format'           => 'txt',
            'confidentiality'  => true,
            'documentDate'     => '2019-01-01 17:18:47',
            'arrivalDate'      => '2019-01-01 17:18:47',
            'processLimitDate' => '2030-01-01',
            'doctype'          => 102,
            'destination'      => 15,
            'initiator'        => 15,
            'subject'          => 'Breaking News : Superman is alive - PHP unit',
            'typist'           => 19,
            'priority'         => 'poiuytre1357nbvc',
            'senders'          => [['type' => 'contact', 'id' => 1], ['type' => 'user', 'id' => 21], ['type' => 'entity', 'id' => 1]],
            'recipients'       => [['type' => 'contact', 'id' => 1]],
            'tags'             => [$tag],
            'folders'          => [$folder],
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $args);

        $response     = $resController->update($fullRequest, new Response(), ['resId' => self::$id]);
        $this->assertSame(204, $response->getStatusCode());

        $args = [
            'status'           => 'NEW',
            'encodedFile'      => $encodedFile,
            'format'           => 'txt',
            'confidentiality'  => true,
            'documentDate'     => '2019-01-01 17:18:47',
            'arrivalDate'      => '2019-01-01 17:18:47',
            'processLimitDate' => '2030-01-01',
            'doctype'          => 102,
            'destination'      => 15,
            'initiator'        => 15,
            'subject'          => 'Breaking News : Superman is alive - PHP unit',
            'typist'           => 19,
            'priority'         => 'poiuytre1357nbvc',
            'senders'          => [['type' => 'contact', 'id' => 1], ['type' => 'user', 'id' => 21], ['type' => 'entity', 'id' => 1]]
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $args);

        $response     = $resController->update($fullRequest, new Response(), ['resId' => self::$id]);
        $this->assertSame(204, $response->getStatusCode());

        GroupBasketModel::update([
            'set'   => ['list_event_data' => '{"canUpdateData": true, "defaultTab": "info", "canUpdateModel": true}'],
            'where' => ['group_id = ?', 'basket_id = ?'],
            'data'  => ['COURRIER', 'QualificationBasket']
        ]);

        $GLOBALS['login'] = 'bblier';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        $args = [
            'modelId'          => 3,
            'status'           => 'NEW',
            'encodedFile'      => $encodedFile,
            'format'           => 'txt',
            'confidentiality'  => true,
            'documentDate'     => '2019-01-01 17:18:47',
            'arrivalDate'      => '2019-01-01 17:18:47',
            'processLimitDate' => '2030-01-01',
            'doctype'          => 102,
            'destination'      => 15,
            'initiator'        => 15,
            'subject'          => 'Breaking News : Superman is alive - PHP unit',
            'typist'           => 19,
            'priority'         => 'poiuytre1357nbvc',
            'senders'          => [['type' => 'contact', 'id' => 1], ['type' => 'user', 'id' => 21], ['type' => 'entity', 'id' => 1]]
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $args);

        $response     = $resController->update($fullRequest, new Response(), ['resId' => self::$id3]);
        $this->assertSame(204, $response->getStatusCode());

        $GLOBALS['login'] = 'superadmin';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        //  READ
        $res = ResModel::getById(['resId' => self::$id, 'select' => ['*']]);

        $this->assertIsArray($res);

        $this->assertSame('Breaking News : Superman is alive - PHP unit', $res['subject']);
        $this->assertSame(102, $res['type_id']);
        $this->assertSame('txt', $res['format']);
        $this->assertSame('NEW', $res['status']);
        $this->assertSame(19, $res['typist']);
        $this->assertNotNull($res['destination']);
        $this->assertNotNull($res['initiator']);
        $this->assertSame('Y', $res['confidentiality']);
        $this->assertSame('2030-01-01 00:00:00', $res['process_limit_date']);

        //  ERROR
        $args = [
            'status'           => 'NEW',
            'encodedFile'      => $encodedFile,
            'confidentiality'  => true,
            'documentDate'     => '2019-01-01 17:18:47',
            'arrivalDate'      => '2019-01-01 17:18:47',
            'processLimitDate' => '2030-01-01',
            'doctype'          => 102,
            'destination'      => 15,
            'initiator'        => 15,
            'subject'          => 'Breaking News : Superman is alive - PHP unit',
            'typist'           => 19,
            'priority'         => 'poiuytre1357nbvc',
            'senders'          => [['type' => 'contact', 'id' => 1], ['type' => 'user', 'id' => 21], ['type' => 'entity', 'id' => 1]],
        ];

        $fullRequest = $this->createRequestWithBody('PUT', $args);

        $response     = $resController->update($fullRequest, new Response(), ['resId' => self::$id]);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Body format is empty or not a string', $responseBody['errors']);

        $args = [
            'status'           => 'NEW',
            'doctype'          => 'wrong format',
            'encodedFile'      => $encodedFile,
            'format'           => 'txt',
            'confidentiality'  => false,
            'documentDate'     => '2019-01-01 17:18:47',
            'arrivalDate'      => '2019-01-01 17:18:47',
            'processLimitDate' => '2029-01-01',
            'destination'      => 15,
            'initiator'        => 15,
            'subject'          => 'Breaking News : Superman is alive - PHP unit',
            'typist'           => 19,
            'priority'         => 'poiuytre1357nbvc',
            'tags'             => [1, 2],
            'folders'          => [1, 2],
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $args);

        $response     = $resController->update($fullRequest, new Response(), ['resId' => self::$id]);
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Body doctype is not an integer', $responseBody['errors']);

        $response     = $resController->update($fullRequest, new Response(), ['resId' => 'wrong format']);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Route resId is not an integer', $responseBody['errors']);

        // Errors from ResourceControlControllers->controlUpdateResource
        $body = [];
        $fullRequest = $this->createRequestWithBody('PUT', $body);

        $response = $resController->update($fullRequest, new Response(), ['resId' => self::$id]);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Body is not set or empty', $responseBody['errors']);

        ResModel::update([
            'set'   => ['status' => ''],
            'where' => ['res_id = ?'],
            'data'  => [self::$id]
        ]);

        $body = [
            'doctype'  => 102
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $body);
        $response = $resController->update($fullRequest, new Response(), ['resId' => self::$id]);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Resource status is empty. It can not be modified', $responseBody['errors']);

        ResModel::update([
            'set'   => ['status' => 'TMP'],
            'where' => ['res_id = ?'],
            'data'  => [self::$id]
        ]);

        $body = [
            'doctype'  => 102
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $body);
        $response = $resController->update($fullRequest, new Response(), ['resId' => self::$id]);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Resource can not be modified because of status', $responseBody['errors']);

        ResModel::update([
            'set'   => ['status' => 'NEW'],
            'where' => ['res_id = ?'],
            'data'  => [self::$id]
        ]);

        $body = [
            'doctype'  => 102000
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $body);
        $response = $resController->update($fullRequest, new Response(), ['resId' => self::$id]);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Body doctype does not exist', $responseBody['errors']);

        $body = [
            'encodedFile' => ''
        ];
        $queryParams = ['onlyDocument' => true ];
        $fullRequest = $this->createRequestWithBody('PUT', $body);
        $fullRequest = $fullRequest->withQueryParams($queryParams);
        $response = $resController->update($fullRequest, new Response(), ['resId' => self::$id]);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Body encodedFile is not set or empty', $responseBody['errors']);

        $externalId = ['signatureBookId' => 42];
        ResModel::update([
            'set'   => ['external_id' => json_encode($externalId)],
            'where' => ['res_id = ?'],
            'data'  => [self::$id]
        ]);

        $body = [
            'encodedFile' => $encodedFile
        ];
        $queryParams = ['onlyDocument' => true ];
        $fullRequest = $this->createRequestWithBody('PUT', $body);
        $fullRequest = $fullRequest->withQueryParams($queryParams);
        $response = $resController->update($fullRequest, new Response(), ['resId' => self::$id]);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Resource is in external signature book, file can not be modified', $responseBody['errors']);


        $externalId = [];
        ResModel::update([
            'set'   => ['external_id' => json_encode($externalId)],
            'where' => ['res_id = ?'],
            'data'  => [self::$id]
        ]);
        AdrModel::createDocumentAdr([
            'resId'         => self::$id,
            'type'          => 'SIGN',
            'docserverId'   => 'docserver_id',
            'path'          => 'directory',
            'filename'      => 'file_destination_name',
            'version'       => 2,
            'fingerprint'   => '1'
        ]);

        $body = [
            'encodedFile' => $encodedFile
        ];
        $queryParams = ['onlyDocument' => true ];
        $fullRequest = $this->createRequestWithBody('PUT', $body);
        $fullRequest = $fullRequest->withQueryParams($queryParams);
        $response = $resController->update($fullRequest, new Response(), ['resId' => self::$id]);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Resource is signed, file can not be modified', $responseBody['errors']);

        AdrModel::deleteDocumentAdr([
            'where' => ['res_id = ?', 'type = ?'],
            'data' => [self::$id, 'SIGN']
        ]);

        ResModel::update([
            'set'   => ['format' => 'css'],
            'where' => ['res_id = ?'],
            'data'  => [self::$id]
        ]);

        $body = [
            'encodedFile' => $encodedFile
        ];
        $queryParams = ['onlyDocument' => true ];
        $fullRequest = $this->createRequestWithBody('PUT', $body);
        $fullRequest = $fullRequest->withQueryParams($queryParams);
        $response = $resController->update($fullRequest, new Response(), ['resId' => self::$id]);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Resource is not convertible, file can not be modified', $responseBody['errors']);

        ResModel::update([
            'set'   => ['format' => 'txt'],
            'where' => ['res_id = ?'],
            'data'  => [self::$id]
        ]);

        $body = [
            'doctype' => 102,
            'tags'    => 'wrong format'
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $body);
        $response = $resController->update($fullRequest, new Response(), ['resId' => self::$id]);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Body tags is not an array', $responseBody['errors']);

        $body = [
            'doctype'   => 102,
            'initiator' => 10000
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $body);
        $response = $resController->update($fullRequest, new Response(), ['resId' => self::$id]);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Body priority is not set', $responseBody['errors']);

        $body = [
            'doctype'          => 102,
            'initiator'        => 10000,
            'priority'         => 'poiuytre1357nbvc',
            'documentDate'     => 'wrong format',
            'arrivalDate'      => 'wrong format',
            'subject'          => 'Permit to expend Slaughter house in  Schrute Farms',
            'senders'          => [['type' => 'contact', 'id' => 1]],
            'destination'      => 15,
            'processLimitDate' => '2029-01-01'
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $body);
        $response = $resController->update($fullRequest, new Response(), ['resId' => self::$id]);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Body initiator does not exist', $responseBody['errors']);

        $body = [
            'doctype'          => 102,
            'initiator'        => 10,
            'priority'         => 'poiuytre1357nbvc',
            'documentDate'     => 'wrong format',
            'arrivalDate'      => 'wrong format',
            'subject'          => 'Permit to expend Slaughter house in  Schrute Farms',
            'senders'          => [['type' => 'contact', 'id' => 1]],
            'destination'      => 15,
            'processLimitDate' => '2029-01-01'
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $body);
        $response = $resController->update($fullRequest, new Response(), ['resId' => self::$id]);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Body initiator does not belong to your entities', $responseBody['errors']);

        $body = [
            'doctype'          => 102,
            'priority'         => 'poiuytre1357nbvc',
            'documentDate'     => 'wrong format',
            'arrivalDate'      => 'wrong format',
            'subject'          => 'Permit to expend Slaughter house in  Schrute Farms',
            'senders'          => [['type' => 'contact', 'id' => 1]],
            'destination'      => 15,
            'processLimitDate' => '2029-01-01'
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $body);
        $response = $resController->update($fullRequest, new Response(), ['resId' => self::$id]);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Body documentDate is not a date', $responseBody['errors']);

        // test control custom fields
        IndexingModelFieldModel::create([
            'model_id'   => 1,
            'identifier' => 'indexingCustomField_1',
            'mandatory'  => 'true',
            'enabled'    => 'true',
            'unit'       => 'mail'
        ]);
        IndexingModelFieldModel::create([
            'model_id'   => 1,
            'identifier' => 'indexingCustomField_2',
            'mandatory'  => 'false',
            'enabled'    => 'true',
            'unit'       => 'mail'
        ]);
        IndexingModelFieldModel::create([
            'model_id'   => 1,
            'identifier' => 'indexingCustomField_3',
            'mandatory'  => 'false',
            'enabled'    => 'true',
            'unit'       => 'mail'
        ]);
        IndexingModelFieldModel::create([
            'model_id'   => 1,
            'identifier' => 'indexingCustomField_4',
            'mandatory'  => 'false',
            'enabled'    => 'true',
            'unit'       => 'mail'
        ]);

        $body = [
            'doctype'          => 102,
            'priority'         => 'poiuytre1357nbvc',
            'documentDate'     => 'wrong format',
            'arrivalDate'      => 'wrong format',
            'subject'          => 'Permit to expend Slaughter house in  Schrute Farms',
            'senders'          => [['type' => 'contact', 'id' => 1]],
            'destination'      => 15,
            'processLimitDate' => '2029-01-01'
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $body);
        $response = $resController->update($fullRequest, new Response(), ['resId' => self::$id]);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Body customFields[1] is empty', $responseBody['errors']);

        $body = [
            'doctype'          => 102,
            'priority'         => 'poiuytre1357nbvc',
            'documentDate'     => 'wrong format',
            'arrivalDate'      => 'wrong format',
            'subject'          => 'Permit to expend Slaughter house in  Schrute Farms',
            'senders'          => [['type' => 'contact', 'id' => 1]],
            'destination'      => 15,
            'processLimitDate' => '2029-01-01',
            'customFields'     => [1 => 'wrong format']
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $body);
        $response = $resController->update($fullRequest, new Response(), ['resId' => self::$id]);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Body customFields[1] is not a date', $responseBody['errors']);

        $body = [
            'doctype'          => 102,
            'priority'         => 'poiuytre1357nbvc',
            'documentDate'     => 'wrong format',
            'arrivalDate'      => 'wrong format',
            'subject'          => 'Permit to expend Slaughter house in  Schrute Farms',
            'senders'          => [['type' => 'contact', 'id' => 1]],
            'destination'      => 15,
            'processLimitDate' => '2029-01-01',
            'customFields'     => [
                1 => '2029-01-01',
                3 => 'Mail printed with Paper form Dunder Mifflin Paper Company Inc.'
            ]
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $body);
        $response = $resController->update($fullRequest, new Response(), ['resId' => self::$id]);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Body customFields[3] has wrong value', $responseBody['errors']);

        $body = [
            'doctype'          => 102,
            'priority'         => 'poiuytre1357nbvc',
            'documentDate'     => 'wrong format',
            'arrivalDate'      => 'wrong format',
            'subject'          => 'Permit to expend Slaughter house in  Schrute Farms',
            'senders'          => [['type' => 'contact', 'id' => 1]],
            'destination'      => 15,
            'processLimitDate' => '2029-01-01',
            'customFields'     => [
                1 => '2029-01-01',
                4 => 42 // wrong format
            ]
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $body);
        $response = $resController->update($fullRequest, new Response(), ['resId' => self::$id]);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Body customFields[4] is not a string', $responseBody['errors']);

        $body = [
            'doctype'          => 102,
            'priority'         => 'poiuytre1357nbvc',
            'documentDate'     => 'wrong format',
            'arrivalDate'      => 'wrong format',
            'subject'          => 'Permit to expend Slaughter house in  Schrute Farms',
            'senders'          => [['type' => 'contact', 'id' => 1]],
            'destination'      => 15,
            'processLimitDate' => '2029-01-01',
            'customFields'     => [
                1 => '2029-01-01',
                2 => ['wrong format']
            ]
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $body);
        $response = $resController->update($fullRequest, new Response(), ['resId' => self::$id]);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Body customFields[2] is not an array', $responseBody['errors']);

        $body = [
            'doctype'          => 102,
            'priority'         => 'poiuytre1357nbvc',
            'documentDate'     => 'wrong format',
            'arrivalDate'      => 'wrong format',
            'subject'          => 'Permit to expend Slaughter house in  Schrute Farms',
            'senders'          => [['type' => 'contact', 'id' => 1]],
            'destination'      => 15,
            'processLimitDate' => '2029-01-01',
            'customFields'     => [
                1 => '2029-01-01',
                2 => [['address' => 'yes']]
            ]
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $body);
        $response = $resController->update($fullRequest, new Response(), ['resId' => self::$id]);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Body customFields[2] longitude is empty', $responseBody['errors']);

        $body = [
            'doctype'          => 102,
            'priority'         => 'poiuytre1357nbvc',
            'documentDate'     => 'wrong format',
            'arrivalDate'      => 'wrong format',
            'subject'          => 'Permit to expend Slaughter house in  Schrute Farms',
            'senders'          => [['type' => 'contact', 'id' => 1]],
            'destination'      => 15,
            'processLimitDate' => '2029-01-01',
            'customFields'     => [
                1 => '2029-01-01',
                2 => [
                    [
                        'longitude' => '1'
                    ]
                ]
            ]
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $body);
        $response = $resController->update($fullRequest, new Response(), ['resId' => self::$id]);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Body customFields[2] latitude is empty', $responseBody['errors']);

        $body = [
            'doctype'          => 102,
            'priority'         => 'poiuytre1357nbvc',
            'documentDate'     => 'wrong format',
            'arrivalDate'      => 'wrong format',
            'subject'          => 'Permit to expend Slaughter house in  Schrute Farms',
            'senders'          => [['type' => 'contact', 'id' => 1]],
            'destination'      => 15,
            'processLimitDate' => '2029-01-01',
            'customFields'     => [
                1 => '2029-01-01',
                2 => [
                    [
                        'longitude' => '1',
                        'latitude' => '1'
                    ]
                ]
            ]
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $body);
        $response = $resController->update($fullRequest, new Response(), ['resId' => self::$id]);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Body customFields[2] addressTown is empty', $responseBody['errors']);

        $body = [
            'doctype'          => 102,
            'priority'         => 'poiuytre1357nbvc',
            'documentDate'     => 'wrong format',
            'arrivalDate'      => 'wrong format',
            'subject'          => 'Permit to expend Slaughter house in  Schrute Farms',
            'senders'          => [['type' => 'contact', 'id' => 1]],
            'destination'      => 15,
            'processLimitDate' => '2029-01-01',
            'customFields'     => [
                1 => '2029-01-01',
                2 => [
                    [
                        'longitude'   => '1',
                        'latitude'    => '1',
                        'addressTown' => '1'
                    ]
                ]
            ]
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $body);
        $response = $resController->update($fullRequest, new Response(), ['resId' => self::$id]);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Body customFields[2] addressPostcode is empty', $responseBody['errors']);

        $GLOBALS['login'] = 'bblier';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        $body = [
            'modelId'          => 10000,
            'doctype'          => 102,
            'priority'         => 'poiuytre1357nbvc',
            'documentDate'     => 'wrong format',
            'arrivalDate'      => 'wrong format',
            'subject'          => 'Permit to expend Slaughter house in  Schrute Farms',
            'senders'          => [['type' => 'contact', 'id' => 1]],
            'destination'      => 15,
            'processLimitDate' => '2029-01-01'
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $body);
        $response = $resController->update($fullRequest, new Response(), ['resId' => self::$id3]);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Body modelId does not exist', $responseBody['errors']);

        $GLOBALS['login'] = 'superadmin';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        $body = [
            'modelId'          => 4,
            'doctype'          => 102,
            'priority'         => 'poiuytre1357nbvc',
            'documentDate'     => 'wrong format',
            'arrivalDate'      => 'wrong format',
            'subject'          => 'Permit to expend Slaughter house in  Schrute Farms',
            'senders'          => [['type' => 'contact', 'id' => 1]],
            'destination'      => 15,
            'processLimitDate' => '2029-01-01'
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $body);
        $response = $resController->update($fullRequest, new Response(), ['resId' => self::$id3]);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Model can not be modified', $responseBody['errors']);

        $GLOBALS['login'] = 'ddur';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        $response     = $resController->update($fullRequest, new Response(), ['resId' => self::$id]);
        $this->assertSame(403, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Service forbidden', $responseBody['errors']);

        $GLOBALS['login'] = 'superadmin';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        FolderModel::delete([
            'where' => ['id = ?'],
            'data'  => [$folder]
        ]);

        IndexingModelFieldModel::delete([
            'where' => ['identifier in (?)', 'model_id = ?'],
            'data'  => [['indexingCustomField_1', 'indexingCustomField_2', 'indexingCustomField_3', 'indexingCustomField_4'], 1]
        ]);
    }
      
    public function testGetOriginalContent()
    {
        $resController = new ResController();

        $request = $this->createRequest('GET');

        $response     = $resController->getOriginalFileContent($request, new Response(), ['resId' => self::$id]);
        $responseBody = json_decode((string)$response->getBody());

        $this->assertSame(null, $responseBody);

        // GET FILE CONTENT
        $response     = $resController->getFileContent($request, new Response(), ['resId' => self::$id]);
        $responseBody = json_decode((string)$response->getBody());

        $this->assertSame(null, $responseBody);

        // GET FILE CONTENT
        $aArgs = [
            'mode'  => 'base64'
        ];
        $fullRequest = $request->withQueryParams($aArgs);
        $response     = $resController->getFileContent($fullRequest, new Response(), ['resId' => self::$id]);
        $responseBody = json_decode((string)$response->getBody());

        $this->assertNotEmpty($responseBody->encodedDocument);
        $this->assertSame('txt', $responseBody->originalFormat);
        $this->assertNotEmpty($responseBody->originalCreatorId);

        $aArgs = [
            'mode'  => 'base64'
        ];
        $fullRequest = $request->withQueryParams($aArgs);
        $response     = $resController->getOriginalFileContent($fullRequest, new Response(), ['resId' => self::$id]);
        $responseBody = json_decode((string)$response->getBody(), true);

        $this->assertNotEmpty($responseBody['encodedDocument']);
        $this->assertIsString($responseBody['encodedDocument']);
        $this->assertSame('txt', $responseBody['extension']);
        $this->assertNotEmpty($responseBody['mimeType']);
        $this->assertIsString($responseBody['mimeType']);

        // ERROR
        $response     = $resController->getFileContent($request, new Response(), ['resId' => -2]);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Document does not exist', $responseBody['errors']);

        $response     = $resController->getFileContent($request, new Response(), ['resId' => self::$id2]);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Document has no file', $responseBody['errors']);

        $GLOBALS['login'] = 'ddur';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        $response     = $resController->getFileContent($fullRequest, new Response(), ['resId' => self::$id]);
        $this->assertSame(403, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Document out of perimeter', $responseBody['errors']);

        $GLOBALS['login'] = 'superadmin';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        $response     = $resController->getOriginalFileContent($request, new Response(), ['resId' => -2]);
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Document does not exist', $responseBody['errors']);

        $response     = $resController->getOriginalFileContent($request, new Response(), ['resId' => self::$id2]);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Document has no file', $responseBody['errors']);

        $GLOBALS['login'] = 'ddur';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        $response     = $resController->getOriginalFileContent($fullRequest, new Response(), ['resId' => self::$id]);
        $this->assertSame(403, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Document out of perimeter', $responseBody['errors']);

        $GLOBALS['login'] = 'superadmin';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];
    }

    public function testGetThumbnailContent()
    {
        $resController = new ResController();

        $request = $this->createRequest('GET');

        $response     = $resController->getThumbnailContent($request, new Response(), ['resId' => self::$id]);

        $this->assertSame(200, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);

        $this->assertEmpty($responseBody);

        $response     = $resController->getThumbnailContent($request, new Response(), ['resId' => -2]);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Document does not exist', $responseBody['errors']);

        $response     = $resController->getThumbnailContent($request, new Response(), ['resId' => 'wrong format']);
        $this->assertSame(403, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('resId param is not an integer', $responseBody['errors']);
    }

    public function testGetItems()
    {
        $resController = new ResController();

        $request = $this->createRequest('GET');

        // Errors
        $response     = $resController->getItems($request, new Response(), ['resId' => 'wrong format']);
        $this->assertSame(403, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Document out of perimeter', $responseBody['errors']);

        $response     = $resController->getItems($request, new Response(), ['resId' => -2]);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Document does not exist', $responseBody['errors']);

        // Success
        $response     = $resController->getItems($request, new Response(), ['resId' => self::$id]);
        $this->assertSame(200, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);

        $this->assertSame(0, $responseBody['linkedResources']);
        $this->assertSame(0, $responseBody['attachments']);
        $this->assertSame(0, $responseBody['diffusionList']);
        $this->assertSame(0, $responseBody['visaCircuit']);
        $this->assertSame(0, $responseBody['opinionCircuit']);
        $this->assertSame(0, $responseBody['notes']);
        $this->assertSame(0, $responseBody['emails']);
    }

    public function testGetField()
    {
        $resController = new ResController();

        $request = $this->createRequest('GET');

        // Errors
        $GLOBALS['login'] = 'ddur';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        $response     = $resController->getField($request, new Response(), ['resId' => self::$id]);
        $this->assertSame(403, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Document out of perimeter', $responseBody['errors']);

        $GLOBALS['login'] = 'superadmin';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        $response     = $resController->getField($request, new Response(), ['resId' => self::$id, 'fieldId' => 'initiator']);
        $this->assertSame(403, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Field out of perimeter', $responseBody['errors']);

        $response     = $resController->getField($request, new Response(), ['resId' => self::$id * 1000, 'fieldId' => 'destination']);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Document does not exist', $responseBody['errors']);

        // Success
        $response     = $resController->getField($request, new Response(), ['resId' => self::$id2, 'fieldId' => 'externalId']);
        $this->assertSame(200, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertIsArray($responseBody['field']);
        $this->assertEmpty($responseBody['field']);

        $fullRequest = $request->withQueryParams(['alt' => true]);
        $response     = $resController->getField($fullRequest, new Response(), ['resId' => self::$id2, 'fieldId' => 'destination']);
        $this->assertSame(200, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame(15, $responseBody['field']);
    }

    public function testGetEncodedDocument()
    {
        $resController = new ResController();

        $response = $resController::getEncodedDocument(['resId' => self::$id, 'original' => false]);

        $this->assertIsString($response['encodedDocument']);
        $this->assertNotEmpty($response['encodedDocument']);

        $this->assertSame('Breaking News _ Superman is al.pdf', $response['fileName']);
    }

    public function testGetCategories()
    {
        $resController = new ResController();

        $request = $this->createRequest('GET');

        $response     = $resController->getCategories($request, new Response());
        $responseBody = json_decode((string)$response->getBody());

        $this->assertNotEmpty($responseBody->categories);

        foreach ($responseBody->categories as $value) {
            $this->assertNotEmpty($value->id);
            $this->assertNotEmpty($value->label);
        }
    }

    public function testIsAllowedForCurrentUser()
    {
        $resController = new ResController();

        $request = $this->createRequest('GET');

        $response     = $resController->isAllowedForCurrentUser($request, new Response(), ['resId' => self::$id]);
        $responseBody = json_decode((string)$response->getBody());

        $this->assertSame(true, $responseBody->isAllowed);

        // NOT ALLOWED
        $GLOBALS['login'] = 'bbain';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        $response     = $resController->isAllowedForCurrentUser($request, new Response(), ['resId' => 123456]);
        $responseBody = json_decode((string)$response->getBody());

        $this->assertSame(false, $responseBody->isAllowed);

        $GLOBALS['login'] = 'superadmin';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];
    }

    public function testSetInIntegrations()
    {
        $resController = new ResController();

        $args = [
            'resources'   => [self::$id],
            'integrations' => ['inSignatureBook' => true, 'inShipping' => true]
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $args);
        $response     = $resController->setInIntegrations($fullRequest, new Response());

        $this->assertSame(204, $response->getStatusCode());

        // ERROR
        $GLOBALS['login'] = 'bbain';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        $args = [
            'resources'   => [12345],
            'integrations' => ['inSignatureBook' => true, 'inShipping' => true]
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $args);
        $response     = $resController->setInIntegrations($fullRequest, new Response());
        $responseBody = json_decode((string)$response->getBody());

        $this->assertSame('Document out of perimeter', $responseBody->errors);

        $GLOBALS['login'] = 'superadmin';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];
    }

    public function testUpdateStatus()
    {
        $resController = new ResController();

        //  UPDATE STATUS
        $body = [
            'resId'         => [self::$id],
            'status'        => 'EVIS'
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $body);

        $response     = $resController->updateStatus($fullRequest, new Response());
        $responseBody = json_decode((string)$response->getBody());
        $this->assertSame('success', $responseBody->success);

        //  READ
        $res = ResModel::getById(['resId' => self::$id, 'select' => ['*']]);
        $this->assertIsArray($res);
        $this->assertSame('EVIS', $res['status']);

        //  UPDATE WITHOUT STATUS
        $body = [
            'resId'         => [self::$id]
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $body);

        $response     = $resController->updateStatus($fullRequest, new Response());
        $responseBody = json_decode((string)$response->getBody());

        $this->assertSame('success', $responseBody->success);

        //  READ
        $res = ResModel::getById(['resId' => self::$id, 'select' => ['*']]);
        $this->assertIsArray($res);
        $this->assertSame('COU', $res['status']);

        $body = [
            'status' => 'STATUS_THAT_DOES_NOT_EXIST'
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $body);

        $response     = $resController->updateStatus($fullRequest, new Response());
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame(_STATUS_NOT_FOUND, $responseBody['errors']);

        $body = [
            'status' => 'EVIS',
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $body);

        $response     = $resController->updateStatus($fullRequest, new Response());
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Bad Request', $responseBody['errors']);

        $body = [
            'status' => 'EVIS',
            'resId'  => [self::$id * 1000]
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $body);

        $response     = $resController->updateStatus($fullRequest, new Response());
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame(_DOCUMENT_NOT_FOUND, $responseBody['errors']);

        $GLOBALS['login'] = 'ddur';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        $body = [
            'status' => 'EVIS',
            'resId'  => [self::$id]
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $body);

        $response     = $resController->updateStatus($fullRequest, new Response());
        $this->assertSame(403, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Service forbidden', $responseBody['errors']);

        $GLOBALS['login'] = 'superadmin';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];
    }

    public function testUpdateExternalInfos()
    {
        $resController = new ResController();

        //  UPDATE STATUS

        //ALL OK
        $args = [
                'externalInfos' => [
                    [
                        'res_id'        => self::$id,
                        'external_id'   => "BB981212IIYZ",
                        'external_link' => "https://publik.nancy.fr/res/BB981212BB65"
                    ]
                ],
                'status'        => "GRCSENT"
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $args);

        $response = $resController->updateExternalInfos($fullRequest, new Response());

        $responseBody = json_decode((string) $response->getBody());

        $this->assertSame('success', $responseBody->success);

        // EXTERNAL INFOS EMPTY AND RES ID IS NOT INTEGER
        $args = [
            'externalInfos' => [
                    [
                        'res_id'        => "res_id",
                        'external_id'   => "",
                        'external_link' => ""
                    ]
                ],
            'status'        => "GRCSENT"

        ];
        $fullRequest = $this->createRequestWithBody('PUT', $args);

        $response = $resController->updateExternalInfos($fullRequest, new Response());

        $responseBody = json_decode((string) $response->getBody());

        $this->assertSame('Bad Request: invalid res_id', $responseBody->errors);

        // DOCUMENT DOES NOT EXIST
        $args = [
            'externalInfos' => [
                        [
                            'res_id'        => 123456789,
                            'external_id'   => "BB981212IIYZ",
                            'external_link' => "https://publik.nancy.fr/res/BB981212BB65"
                        ]
                    ],
            'status'        => 'GRCSENT'
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $args);

        $response = $resController->updateExternalInfos($fullRequest, new Response());

        $responseBody = json_decode((string) $response->getBody());

        $this->assertSame(_DOCUMENT_NOT_FOUND, $responseBody->errors);

        //MISSING STATUS
        $args = [
                'externalInfos' => [
                    [
                        'res_id'        => self::$id,
                        'external_id'   => "BB981212IIYZ",
                        'external_link' => "https://publik.nancy.fr/res/BB981212BB65"
                    ]
                ],
                'status'        => null
        ];

        $fullRequest = $this->createRequestWithBody('PUT', $args);

        $response = $resController->updateExternalInfos($fullRequest, new Response());

        $responseBody = json_decode((string) $response->getBody());

        $this->assertSame('Bad Request : status is empty', $responseBody->errors);

        //MISSING EXTERNAL INFOS
        $args = [
            'externalInfos' => null,
            'status'        => "GRCSENT"
        ];

        $fullRequest = $this->createRequestWithBody('PUT', $args);

        $response = $resController->updateExternalInfos($fullRequest, new Response());

        $responseBody = json_decode((string) $response->getBody());

        $this->assertSame('Bad Request : externalInfos is empty', $responseBody->errors);
    }

    public function testGetList()
    {
        $resController = new ResController();

        $args = [
            'select'        => 'sve_start_date',
            'clause'        => '1=1',
            'withFile'      => true,
            'orderBy'       => ['res_id'],
            'limit'         => 1
        ];
        $fullRequest = $this->createRequestWithBody('POST', $args);

        $response     = $resController->getList($fullRequest, new Response());
        $responseBody = json_decode((string)$response->getBody());
        $arr_res = $responseBody->resources;
        $this->assertNotNull($arr_res[0]->fileBase64Content);
        $this->assertIsInt($arr_res[0]->res_id);

        $args = [
            'select'        => 'res_id',
            'clause'        => '1=1',
            'withFile'      => false,
            'orderBy'       => ['res_id'],
            'limit'         => 1
        ];
        $fullRequest = $this->createRequestWithBody('POST', $args);

        $response     = $resController->getList($fullRequest, new Response());
        $responseBody = json_decode((string)$response->getBody());
        $arr_res = $responseBody->resources;
        $this->assertSame(null, $arr_res[0]->fileBase64Content);
        $this->assertIsInt($arr_res[0]->res_id);

        // Errors
        $args = [
            'select'        => '',
            'clause'        => '1=1',
            'withFile'      => false,
            'orderBy'       => ['res_id'],
            'limit'         => 1
        ];
        $fullRequest = $this->createRequestWithBody('POST', $args);

        $response     = $resController->getList($fullRequest, new Response());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Bad Request: select is not valid', $responseBody['errors']);

        $args = [
            'select'        => 'res_id',
            'clause'        => '',
            'withFile'      => false,
            'orderBy'       => ['res_id'],
            'limit'         => 1
        ];
        $fullRequest = $this->createRequestWithBody('POST', $args);

        $response     = $resController->getList($fullRequest, new Response());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Bad Request: clause is not valid', $responseBody['errors']);

        $args = [
            'select'        => 'res_id',
            'clause'        => '1=1',
            'withFile'      => 'wrong format',
            'orderBy'       => ['res_id'],
            'limit'         => 1
        ];
        $fullRequest = $this->createRequestWithBody('POST', $args);

        $response     = $resController->getList($fullRequest, new Response());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Bad Request: withFile parameter is not a boolean', $responseBody['errors']);

        $args = [
            'select'        => 'res_id',
            'clause'        => '1=1',
            'withFile'      => false,
            'orderBy'       => 'wrong format',
            'limit'         => 1
        ];
        $fullRequest = $this->createRequestWithBody('POST', $args);

        $response     = $resController->getList($fullRequest, new Response());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Bad Request: orderBy parameter not valid', $responseBody['errors']);

        $args = [
            'select'        => 'res_id',
            'clause'        => '1=1',
            'withFile'      => false,
            'orderBy'       => ['res_id'],
            'limit'         => 'wrong format'
        ];
        $fullRequest = $this->createRequestWithBody('POST', $args);

        $response     = $resController->getList($fullRequest, new Response());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Bad Request: limit parameter not valid', $responseBody['errors']);

        $args = [
            'select'        => 'res_id',
            'clause'        => 'dundermifflin_clients.branch',
            'withFile'      => false,
            'orderBy'       => ['res_id'],
            'limit'         => 1
        ];
        $fullRequest = $this->createRequestWithBody('POST', $args);

        $response     = $resController->getList($fullRequest, new Response());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame(_INVALID_REQUEST, $responseBody['errors']);
    }

    public function testGetProcessingData()
    {
        $resController = new ResController();

        $request = $this->createRequest('GET');

        $response     = $resController->getProcessingData($request, new Response(), ['groupId' => 'wrong format']);
        $this->assertSame(403, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('groupId param is not an integer', $responseBody['errors']);

        $response     = $resController->getProcessingData($request, new Response(), ['groupId' => 2, 'userId' => 'wrong format']);
        $this->assertSame(403, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('userId param is not an integer', $responseBody['errors']);

        $response     = $resController->getProcessingData($request, new Response(), ['groupId' => 2, 'userId' => $GLOBALS['id'], 'basketId' => 'wrong format']);
        $this->assertSame(403, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('basketId param is not an integer', $responseBody['errors']);

        $response     = $resController->getProcessingData($request, new Response(), ['groupId' => 2, 'userId' => $GLOBALS['id'], 'basketId' => 2, 'resId' => 'wrong format']);
        $this->assertSame(403, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('resId param is not an integer', $responseBody['errors']);

        $response     = $resController->getProcessingData($request, new Response(), ['groupId' => 2, 'userId' => $GLOBALS['id'], 'basketId' => 2, 'resId' => self::$id]);
        $this->assertSame(403, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Group is not linked to this user', $responseBody['errors']);

        $GLOBALS['login'] = 'bbain';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        $response     = $resController->getProcessingData($request, new Response(), ['groupId' => 2, 'userId' => $GLOBALS['id'], 'basketId' => 2, 'resId' => self::$id]);
        $this->assertSame(403, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Group is not linked to this basket', $responseBody['errors']);

        // Success
        $response     = $resController->getProcessingData($request, new Response(), ['groupId' => 2, 'userId' => $GLOBALS['id'], 'basketId' => 4, 'resId' => self::$id]);
        $this->assertSame(200, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);

        $this->assertIsArray($responseBody['listEventData']);
        $this->assertNotEmpty($responseBody['listEventData']);
        $this->assertSame('dashboard', $responseBody['listEventData']['defaultTab']);
        $this->assertSame(false, $responseBody['listEventData']['canUpdateData']);
        $this->assertSame(false, $responseBody['listEventData']['canUpdateModel']);

        $GLOBALS['login'] = 'superadmin';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];
    }

    public function testGetResourceFileInformation()
    {
        $resController = new ResController();

        $request = $this->createRequest('GET');

        $GLOBALS['login'] = 'bbain';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        $response     = $resController->getResourceFileInformation($request, new Response(), ['resId' => self::$id]);
        $this->assertSame(403, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Document out of perimeter', $responseBody['errors']);

        $GLOBALS['login'] = 'superadmin';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        // Success
        $response     = $resController->getResourceFileInformation($request, new Response(), ['resId' => self::$id]);
        $this->assertSame(200, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);

        $this->assertIsArray($responseBody['information']);
        $this->assertNotEmpty($responseBody['information']);
        $this->assertSame('txt', $responseBody['information']['format']);
        $this->assertIsString($responseBody['information']['fingerprint']);
        $this->assertNotEmpty($responseBody['information']['fingerprint']);
        $this->assertIsString($responseBody['information']['typistLabel']);
        $this->assertNotEmpty($responseBody['information']['typistLabel']);
        $this->assertNotEmpty($responseBody['information']['typist']);
        $this->assertSame(46, $responseBody['information']['filesize']);
        $this->assertSame('SUCCESS', $responseBody['information']['fulltext_result']);
        $this->assertSame(true, $responseBody['information']['canConvert']);
    }

    public function testGetVersionsInformations()
    {
        $resController = new ResController();

        $request = $this->createRequest('GET');

        $GLOBALS['login'] = 'bbain';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        $response     = $resController->getVersionsInformations($request, new Response(), ['resId' => self::$id]);
        $this->assertSame(403, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Document out of perimeter', $responseBody['errors']);

        $GLOBALS['login'] = 'superadmin';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        // Success
        $response     = $resController->getVersionsInformations($request, new Response(), ['resId' => self::$id]);
        $this->assertSame(200, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);

        $this->assertIsArray($responseBody['DOC']);
        $this->assertNotEmpty($responseBody['DOC']);
        $this->assertSame(1, $responseBody['DOC'][0]);
        $this->assertSame(2, $responseBody['DOC'][1]);
        $this->assertSame(3, $responseBody['DOC'][2]);

        $this->assertIsArray($responseBody['PDF']);
        $this->assertNotEmpty($responseBody['PDF']);
        $this->assertSame(1, $responseBody['PDF'][0]);
        $this->assertSame(2, $responseBody['PDF'][1]);
        $this->assertSame(3, $responseBody['PDF'][2]);

        $this->assertIsArray($responseBody['SIGN']);
        $this->assertEmpty($responseBody['SIGN']);

        $this->assertIsArray($responseBody['NOTE']);
        $this->assertEmpty($responseBody['NOTE']);

        $this->assertSame(true, $responseBody['convert']);

        $response     = $resController->getVersionsInformations($request, new Response(), ['resId' => self::$id2]);
        $this->assertSame(200, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);

        $this->assertIsArray($responseBody['DOC']);
        $this->assertEmpty($responseBody['DOC']);
        $this->assertIsArray($responseBody['PDF']);
        $this->assertEmpty($responseBody['PDF']);
        $this->assertIsArray($responseBody['SIGN']);
        $this->assertEmpty($responseBody['SIGN']);
        $this->assertIsArray($responseBody['NOTE']);
        $this->assertEmpty($responseBody['NOTE']);
    }

    public function testGetVersionFileContent()
    {
        $resController = new ResController();

        $request = $this->createRequest('GET');

        $GLOBALS['login'] = 'bbain';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        $response     = $resController->getVersionFileContent($request, new Response(), ['resId' => self::$id]);
        $this->assertSame(403, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Document out of perimeter', $responseBody['errors']);

        $GLOBALS['login'] = 'superadmin';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        $response     = $resController->getVersionFileContent($request, new Response(), ['resId' => self::$id, 'version' => 1000]);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Incorrect version', $responseBody['errors']);

        $response     = $resController->getVersionFileContent($request, new Response(), ['resId' => self::$id2, 'version' => 1]);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Document has no file', $responseBody['errors']);

        // Success
        $response     = $resController->getVersionFileContent($request, new Response(), ['resId' => self::$id, 'version' => 1]);
        $this->assertSame(200, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);

        $this->assertIsString($responseBody['encodedDocument']);
        $this->assertNotEmpty($responseBody['encodedDocument']);
    }

    public function testDelete()
    {
        //  DELETE
        ResModel::update(['set' => ['status' => 'DEL'], 'where' => ['res_id = ?'], 'data' => [self::$id]]);

        //  READ
        $res = ResModel::getById(['resId' => self::$id, 'select' => ['*']]);
        $this->assertIsArray($res);
        $this->assertSame('DEL', $res['status']);

        ResModel::delete([
            'where' => ['res_id = ?'],
            'data'  => [self::$id2]
        ]);

        $res = ResModel::getById(['resId' => self::$id2, 'select' => ['*']]);
        $this->assertIsArray($res);
        $this->assertEmpty($res);

        ResModel::delete([
            'where' => ['res_id = ?'],
            'data'  => [self::$id3]
        ]);

        $res = ResModel::getById(['resId' => self::$id3, 'select' => ['*']]);
        $this->assertIsArray($res);
        $this->assertEmpty($res);
    }

    public function testCreateMultipleDocument()
    {
        $GLOBALS['login'] = 'cchaplin';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        $resController = new ResController();

        $aNewDocument = [
            1 => [
                102,
                'poiuytre1357nbvc',
                'NEW'
            ],
            2 => [
                103,
                'poiuytre1379nbvc',
                'COU'
            ],
            3 => [
                104,
                'poiuytre1391nbvc',
                'ENVDONE'
            ]
        ];

        $entity = \Entity\models\EntityModel::getByEntityId(['entityId' => 'PJS', 'select' => ['id']]);
        $this->assertIsInt($entity['id']);

        foreach ($aNewDocument as $key => $value) {
            $fileContent = file_get_contents('test/unitTests/samples/test.txt');
            $encodedFile = base64_encode($fileContent);
            $args = [
                'modelId'       => 1,
                'status'        => $value[2],
                'encodedFile'   => $encodedFile,
                'format'        => 'txt',
                'confidentiality'   => false,
                'documentDate'  => '2019-01-01 17:18:47',
                'arrivalDate'   => '2019-01-01 17:18:47',
                'doctype'       => $value[0],
                'destination'   => $entity['id'],
                'initiator'     => $entity['id'],
                'subject'       => $key .' Breaking News : 12345 Superman is alive - PHP unit',
                'typist'        => 19,
                'priority'      => $value[1],
                'diffusionList' => [['id' => 19, 'type' => 'user', 'mode' => 'dest'], ['id' => 20, 'type' => 'user', 'mode' => 'cc']],
                'senders'       => [['type' => 'contact', 'id' => 1], ['type' => 'user', 'id' => 21], ['type' => 'entity', 'id' => 1]],
                'recipients'    => [['type' => 'contact', 'id' => 5], ['type' => 'user', 'id' => 19], ['type' => 'entity', 'id' => 5]],
                'tags'          => [1, 2],
                'folders'       => [1, 2],
                'customFields'  => [4 => 'référence externe']
            ];

            $fullRequest = $this->createRequestWithBody('POST', $args);
            $response     = $resController->create($fullRequest, new Response());
            $responseBody = json_decode((string)$response->getBody());
            $newId = $responseBody->resId;
            $this->assertIsInt($newId);
            $GLOBALS['resources'][] = $newId;
        }

        $GLOBALS['login'] = 'superadmin';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];
    }

    public function testGetBytesSizeFromPhpIni()
    {
        $size = '1K';
        $byteSize = StoreController::getBytesSizeFromPhpIni(['size' => $size]);
        $this->assertSame(1024, $byteSize);

        $size = '1M';
        $byteSize = StoreController::getBytesSizeFromPhpIni(['size' => $size]);
        $this->assertSame(1048576, $byteSize);

        $size = '1G';
        $byteSize = StoreController::getBytesSizeFromPhpIni(['size' => $size]);
        $this->assertSame(1073741824, $byteSize);

        $size = 1;
        $byteSize = StoreController::getBytesSizeFromPhpIni(['size' => $size]);
        $this->assertSame(1, $byteSize);
    }

    public function testGetFormattedSizeFromBytes()
    {
        $size = 1073741824 + 1;
        $formatted = StoreController::getFormattedSizeFromBytes(['size' => $size]);
        $this->assertSame(round($size / 1073741824) . ' Go', $formatted);

        $size = 1048576 + 1;
        $formatted = StoreController::getFormattedSizeFromBytes(['size' => $size]);
        $this->assertSame(round($size / 1048576) . ' Mo', $formatted);

        $size = 1024 + 1;
        $formatted = StoreController::getFormattedSizeFromBytes(['size' => $size]);
        $this->assertSame(round($size / 1024) . ' Ko', $formatted);

        $size = 1;
        $formatted = StoreController::getFormattedSizeFromBytes(['size' => $size]);
        $this->assertSame('1 o', $formatted);
    }
}
