<?php

/**
 * Copyright Maarch since 2008 under licence GPLv3.
 * See LICENCE.txt file at the root folder for more details.
 * This file is part of Maarch software.
 *
 */

namespace MaarchCourrier\Tests\app\signatureBook;

use Attachment\controllers\AttachmentController;
use Basket\models\BasketModel;
use Convert\models\AdrModel;
use Entity\models\ListInstanceModel;
use MaarchCourrier\Tests\CourrierTestCase;
use Resource\controllers\ResController;
use Resource\models\ResModel;
use SignatureBook\controllers\SignatureBookController;
use SrcCore\http\Response;
use User\controllers\UserController;
use User\models\UserModel;

class SignatureBookControllerTest extends CourrierTestCase
{
    private static $resId = null;
    private static $attachmentIdIncoming = null;
    private static $attachmentId = null;
    private static $attachmentId2 = null;
    private static $signedAttachmentId = null;
    private static $signatureIdPetit = null;
    private static $signatureIdRenaud = null;

    public function testInit()
    {
        $resController = new ResController();

        //  CREATE
        $GLOBALS['login'] = 'cchaplin';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        $fileContent = file_get_contents('install/samples/templates/2021/03/0001/0001_742130848.docx');
        $encodedFile = base64_encode($fileContent);

        $argsMailNew = [
            'modelId'          => 1,
            'status'           => 'ESIG',
            'encodedFile'      => $encodedFile,
            'format'           => 'docx',
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
            ],
            'integrations' => json_encode(['inSignatureBook' => true, 'inShipping' => true])
        ];

        $fullRequest = $this->createRequestWithBody('POST', $argsMailNew);

        $response     = $resController->create($fullRequest, new Response());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertIsInt($responseBody['resId']);
        self::$resId = $responseBody['resId'];

        $integrations = ['inSignatureBook' => true, 'inShipping' => true];
        ResModel::update([
            'set'   => ['integrations' => json_encode($integrations)],
            'where' => ['res_id = ?'],
            'data'  => [self::$resId]
        ]);

        $attachmentController = new AttachmentController();

        $body = [
            'title'         => 'Nulle pierre ne peut être polie sans friction, nul homme ne peut parfaire son expérience sans épreuve.',
            'type'          => 'response_project',
            'chrono'        => 'MAARCH/2019D/14',
            'resIdMaster'   => self::$resId,
            'encodedFile'   => $encodedFile,
            'format'        => 'docx',
            'recipientId'   => 1,
            'recipientType' => 'contact'
        ];
        $fullRequest = $this->createRequestWithBody('POST', $body);

        $response     = $attachmentController->create($fullRequest, new Response());
        $responseBody = json_decode((string)$response->getBody());
        self::$attachmentId = $responseBody->id;
        $this->assertIsInt(self::$attachmentId);

        $GLOBALS['login'] = 'rrenaud';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        ListInstanceModel::create([
            'res_id'          => self::$resId,
            'sequence'        => 0,
            'item_id'         => $userInfo['id'],
            'item_type'       => 'user_id',
            'item_mode'       => 'sign',
            'added_by_user'   => $GLOBALS['id'],
            'viewed'          => 0,
            'difflist_type'   => 'VISA_CIRCUIT'
        ]);
    }

    public function testGetSignatureBook()
    {
        $signatureBookController = new SignatureBookController();

        //  CREATE
        $request = $this->createRequest('GET');

        // Errors
        $GLOBALS['login'] = 'bblier';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        $response = $signatureBookController->getSignatureBook($request, new Response(), ['resId' => $GLOBALS['resources'][0] * 1000]);
        $this->assertSame(403, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Document out of perimeter', $responseBody['errors']);

        $GLOBALS['login'] = 'superadmin';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        $response = $signatureBookController->getSignatureBook($request, new Response(), ['resId' => $GLOBALS['resources'][0] * 1000]);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('No Document Found', $responseBody['errors']);

        $GLOBALS['login'] = 'bblier';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        // Success
        $myBasket = BasketModel::getByBasketId(['basketId' => 'MyBasket', 'select' => ['id']]);

        $response = $signatureBookController->getSignatureBook($request, new Response(), ['resId' => $GLOBALS['resources'][0], 'basketId' => $myBasket['id'], 'userId' => $GLOBALS['id']]);
        $this->assertSame(200, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);

        $this->assertIsArray($responseBody['attachments']);
        $this->assertEmpty($responseBody['attachments']);
        $this->assertIsArray($responseBody['documents']);
        $this->assertNotEmpty($responseBody['documents']);

        $this->assertSame($GLOBALS['resources'][0], $responseBody['documents'][0]['res_id']);
        $this->assertEmpty($responseBody['documents'][0]['alt_id']);
        $this->assertSame('1 Breaking News : 12345 Superman is alive - PHP unit', $responseBody['documents'][0]['title']);
        $this->assertSame('incoming', $responseBody['documents'][0]['category_id']);
        $this->assertSame('../rest/resources/' . $GLOBALS['resources'][0] . '/content', $responseBody['documents'][0]['viewerLink']);
        $this->assertSame('../rest/resources/' . $GLOBALS['resources'][0] . '/thumbnail', $responseBody['documents'][0]['thumbnailLink']);
        $this->assertSame(false, $responseBody['documents'][0]['inSignatureBook']);

        $this->assertIsArray($responseBody['resList']);
        $this->assertEmpty($responseBody['resList']);

        $this->assertSame(1, $responseBody['nbNotes']);
        $this->assertSame(0, $responseBody['nbLinks']);

        $this->assertIsArray($responseBody['signatures']);
        $this->assertEmpty($responseBody['signatures']);
        $this->assertEmpty($responseBody['consigne']);
        $this->assertSame(false, $responseBody['hasWorkflow']);
        $this->assertIsArray($responseBody['listinstance']);
        $this->assertEmpty($responseBody['listinstance']);

        $this->assertSame(false, $responseBody['canSign']);
        $this->assertSame(false, $responseBody['isCurrentWorkflowUser']);

        $GLOBALS['login'] = 'superadmin';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];
    }

    public function testGetIncomingMailAndAttachmentsById()
    {
        $signatureBookController = new SignatureBookController();

        //  CREATE
        $request = $this->createRequest('GET');

        // Errors
        $GLOBALS['login'] = 'bblier';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        $response = $signatureBookController->getIncomingMailAndAttachmentsById($request, new Response(), ['resId' => $GLOBALS['resources'][0] * 1000]);
        $this->assertSame(403, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Document out of perimeter', $responseBody['errors']);

        // Success
        $myBasket = BasketModel::getByBasketId(['basketId' => 'MyBasket', 'select' => ['id']]);

        $response = $signatureBookController->getIncomingMailAndAttachmentsById($request, new Response(), ['resId' => $GLOBALS['resources'][0], 'basketId' => $myBasket['id'], 'userId' => $GLOBALS['id']]);
        $this->assertSame(200, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);

        $this->assertSame($GLOBALS['resources'][0], $responseBody[0]['res_id']);
        $this->assertEmpty($responseBody[0]['alt_id']);
        $this->assertSame('1 Breaking News : 12345 Superman is alive - PHP unit', $responseBody[0]['title']);
        $this->assertSame('incoming', $responseBody[0]['category_id']);
        $this->assertSame('../rest/resources/' . $GLOBALS['resources'][0] . '/content', $responseBody[0]['viewerLink']);
        $this->assertSame('../rest/resources/' . $GLOBALS['resources'][0] . '/thumbnail', $responseBody[0]['thumbnailLink']);
        $this->assertSame(false, $responseBody[0]['inSignatureBook']);

        $attachmentController = new AttachmentController();

        $fileContent = file_get_contents('test/unitTests/samples/test.txt');
        $encodedFile = base64_encode($fileContent);

        $body = [
            'title'           => 'Superman Certificate of aliveness',
            'type'            => 'incoming_mail_attachment',
            'chrono'          => 'MAARCH/2019D/14',
            'resIdMaster'     => $GLOBALS['resources'][0],
            'encodedFile'     => $encodedFile,
            'format'          => 'txt',
            'recipientId'     => 1,
            'recipientType'   => 'contact',
            'inSignatureBook' => true
        ];

        $fullRequest = $this->createRequestWithBody('POST', $body);

        $response     = $attachmentController->create($fullRequest, new Response());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertIsInt($responseBody['id']);
        self::$attachmentIdIncoming = $responseBody['id'];

        $request = $this->createRequest('GET');

        $response = $signatureBookController->getIncomingMailAndAttachmentsById($request, new Response(), ['resId' => $GLOBALS['resources'][0], 'basketId' => $myBasket['id'], 'userId' => $GLOBALS['id']]);
        $this->assertSame(200, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);

        $this->assertSame($GLOBALS['resources'][0], $responseBody[0]['res_id']);
        $this->assertEmpty($responseBody[0]['alt_id']);
        $this->assertSame('1 Breaking News : 12345 Superman is alive - PHP unit', $responseBody[0]['title']);
        $this->assertSame('incoming', $responseBody[0]['category_id']);
        $this->assertSame('../rest/resources/' . $GLOBALS['resources'][0] . '/content', $responseBody[0]['viewerLink']);
        $this->assertSame('../rest/resources/' . $GLOBALS['resources'][0] . '/thumbnail', $responseBody[0]['thumbnailLink']);
        $this->assertSame(false, $responseBody[0]['inSignatureBook']);

        $this->assertSame(self::$attachmentIdIncoming, $responseBody[1]['res_id']);
        $this->assertSame('Superman Certificate of aliveness', $responseBody[1]['title']);
        $this->assertSame('txt', $responseBody[1]['format']);
        $this->assertSame(true, $responseBody[1]['isConverted']);
        $this->assertSame('../rest/attachments/' . self::$attachmentIdIncoming . '/content', $responseBody[1]['viewerLink']);
        $this->assertSame('../rest/attachments/' . self::$attachmentIdIncoming . '/thumbnail', $responseBody[1]['thumbnailLink']);

        $GLOBALS['login'] = 'superadmin';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];
    }

    public function testGetResources()
    {
        $signatureBookController = new SignatureBookController();

        //  CREATE
        $request = $this->createRequest('GET');
        $myBasket = BasketModel::getByBasketId(['basketId' => 'MyBasket', 'select' => ['id']]);

        // Errors
        $GLOBALS['login'] = 'bbain';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        $response = $signatureBookController->getResources($request, new Response(), ['resId' => $GLOBALS['resources'][0], 'groupId' => 1000, 'basketId' => $myBasket['id'], 'userId' => $GLOBALS['id']]);
        $this->assertSame(403, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Group or basket does not exist', $responseBody['errors']);

        // Success
        $response = $signatureBookController->getResources($request, new Response(), ['resId' => $GLOBALS['resources'][0], 'groupId' => 2, 'basketId' => $myBasket['id'], 'userId' => $GLOBALS['id']]);
        $this->assertSame(200, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);

        $this->assertSame($GLOBALS['resources'][2], $responseBody['resources'][0]['res_id']);
        $this->assertEmpty($responseBody['resources'][0]['alt_id']);
        $this->assertSame('3 Breaking News : 12345 Superman is alive - PHP unit', $responseBody['resources'][0]['subject']);
        $this->assertNotEmpty($responseBody['resources'][0]['creation_date']);
        $this->assertEmpty($responseBody['resources'][0]['process_limit_date']);
        $this->assertSame(false, $responseBody['resources'][0]['allSigned']);
        $this->assertSame('#ff0000', $responseBody['resources'][0]['priorityColor']);
        $this->assertSame('Très urgent', $responseBody['resources'][0]['priorityLabel']);

        $this->assertSame($GLOBALS['resources'][1], $responseBody['resources'][1]['res_id']);
        $this->assertEmpty($responseBody['resources'][1]['alt_id']);
        $this->assertSame('2 Breaking News : 12345 Superman is alive - PHP unit', $responseBody['resources'][1]['subject']);
        $this->assertNotEmpty($responseBody['resources'][1]['creation_date']);
        $this->assertEmpty($responseBody['resources'][1]['process_limit_date']);
        $this->assertSame(false, $responseBody['resources'][1]['allSigned']);
        $this->assertSame('#ffa500', $responseBody['resources'][1]['priorityColor']);
        $this->assertSame('Urgent', $responseBody['resources'][1]['priorityLabel']);

        $this->assertSame($GLOBALS['resources'][0], $responseBody['resources'][2]['res_id']);
        $this->assertEmpty($responseBody['resources'][2]['alt_id']);
        $this->assertSame('1 Breaking News : 12345 Superman is alive - PHP unit', $responseBody['resources'][2]['subject']);
        $this->assertNotEmpty($responseBody['resources'][2]['creation_date']);
        $this->assertEmpty($responseBody['resources'][2]['process_limit_date']);
        $this->assertSame(false, $responseBody['resources'][2]['allSigned']);
        $this->assertSame('#009dc5', $responseBody['resources'][2]['priorityColor']);
        $this->assertSame('Normal', $responseBody['resources'][2]['priorityLabel']);

        $GLOBALS['login'] = 'superadmin';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];
    }

    public function testGetAttachmentsById()
    {
        $signatureBookController = new SignatureBookController();

        //  CREATE
        $request = $this->createRequest('GET');

        // Errors
        $GLOBALS['login'] = 'bblier';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        $response = $signatureBookController->getAttachmentsById($request, new Response(), ['resId' => $GLOBALS['resources'][0] * 1000]);
        $this->assertSame(403, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Document out of perimeter', $responseBody['errors']);

        // Success
        $myBasket = BasketModel::getByBasketId(['basketId' => 'MyBasket', 'select' => ['id']]);

        $attachmentController = new AttachmentController();

        $fileContent = file_get_contents('test/unitTests/samples/test.txt');
        $encodedFile = base64_encode($fileContent);

        $body = [
            'title'           => 'Dunder Mifflin order',
            'type'            => 'response_project',
            'chrono'          => 'MAARCH/2019D/14',
            'resIdMaster'     => $GLOBALS['resources'][0],
            'encodedFile'     => $encodedFile,
            'format'          => 'txt',
            'recipientId'     => 1,
            'recipientType'   => 'contact',
            'inSignatureBook' => true
        ];

        $fullRequest = $this->createRequestWithBody('POST', $body);

        $response     = $attachmentController->create($fullRequest, new Response());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertIsInt($responseBody['id']);
        self::$attachmentId2 = $responseBody['id'];

        $request = $this->createRequest('GET');

        $response = $signatureBookController->getAttachmentsById($request, new Response(), ['resId' => $GLOBALS['resources'][0], 'basketId' => $myBasket['id'], 'userId' => $GLOBALS['id']]);
        $this->assertSame(200, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);

        $this->assertSame(self::$attachmentId2, $responseBody[0]['res_id']);
        $this->assertSame('Dunder Mifflin order', $responseBody[0]['title']);
        $this->assertNotEmpty($responseBody[0]['identifier']);
        $this->assertSame(_RESPONSE_PROJECT, $responseBody[0]['attachment_type']);
        $this->assertSame('A_TRA', $responseBody[0]['status']);
        $this->assertEmpty($responseBody[0]['modified_by']);
        $this->assertNotEmpty($responseBody[0]['typist']);
        $this->assertNotEmpty($responseBody[0]['creation_date']);
        $this->assertEmpty($responseBody[0]['validation_date']);
        $this->assertSame('txt', $responseBody[0]['format']);
        $this->assertSame(1, $responseBody[0]['relation']);
        $this->assertSame(1, $responseBody[0]['recipient_id']);
        $this->assertSame('contact', $responseBody[0]['recipient_type']);
        $this->assertEmpty($responseBody[0]['origin']);
        $this->assertIsArray($responseBody[0]['recipient']['contact']);
        $this->assertNotEmpty($responseBody[0]['recipient']['contact']);

        $this->assertSame(true, $responseBody[0]['canModify']);
        $this->assertSame(true, $responseBody[0]['canDelete']);
        $this->assertSame(true, $responseBody[0]['isConverted']);
        $this->assertSame(self::$attachmentId2, $responseBody[0]['viewerNoSignId']);
        $this->assertSame('R', $responseBody[0]['icon']);
        $this->assertSame(true, $responseBody[0]['sign']);

        $viewerLink = explode('?', $responseBody[0]['viewerLink'])[0];

        $this->assertSame('../rest/attachments/' . self::$attachmentId2 . '/content', $viewerLink);
        $this->assertIsArray($responseBody[0]['obsAttachments']);
        $this->assertEmpty($responseBody[0]['obsAttachments']);

        $GLOBALS['login'] = 'superadmin';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];
    }

    public function testSignResource()
    {
        $signatureBookController = new SignatureBookController();

        //  CREATE
        $request = $this->createRequest('PUT');

        // Errors
        $GLOBALS['login'] = 'bbain';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        $response = $signatureBookController->signResource($request, new Response(), []);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Route resId is not an integer', $responseBody['errors']);

        $response = $signatureBookController->signResource($request, new Response(), ['resId' => $GLOBALS['resources'][0]]);
        $this->assertSame(403, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Document out of signatory book', $responseBody['errors']);

        $GLOBALS['login'] = 'rrenaud';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        ListInstanceModel::create([
            'res_id'          => $GLOBALS['resources'][0],
            'sequence'        => 0,
            'item_id'         => $userInfo['id'],
            'item_type'       => 'user_id',
            'item_mode'       => 'sign',
            'added_by_user'   => $GLOBALS['id'],
            'viewed'          => 0,
            'difflist_type'   => 'VISA_CIRCUIT'
        ]);

        $integrations = ['inSignatureBook' => true, 'inShipping' => false];
        ResModel::update([
            'set'   => ['integrations' => json_encode($integrations), 'status' => 'ESIG'],
            'where' => ['res_id = ?'],
            'data'  => [$GLOBALS['resources'][0]]
        ]);

        $response = $signatureBookController->signResource($request, new Response(), ['resId' => $GLOBALS['resources'][0]]);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Body signatureId is empty or not an integer', $responseBody['errors']);

        // group -> responsable, basket -> ParafBasket
        $body = [
            'signatureId' => 10000
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $body);
        $response = $signatureBookController->signResource($fullRequest, new Response(), ['resId' => $GLOBALS['resources'][0]]);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Signature does not exist', $responseBody['errors']);

        $fileContent = file_get_contents('src/frontend/assets/noThumbnail.png');
        $encodedFile = base64_encode($fileContent);

        $GLOBALS['login'] = 'ppetit';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        $body = [
            'name'   => 'signature1.png',
            'label'  => 'Signature1',
            'base64' => $encodedFile
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $body);

        $userController = new UserController();
        $response = $userController->addSignature($fullRequest, new Response(), ['id' => $GLOBALS['id']]);
        $this->assertSame(200, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertIsInt($responseBody['signatures'][0]['id']);
        self::$signatureIdPetit = $responseBody['signatures'][0]['id'];

        $GLOBALS['login'] = 'rrenaud';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        $body = [
            'signatureId' => self::$signatureIdPetit
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $body);
        $response = $signatureBookController->signResource($fullRequest, new Response(), ['resId' => $GLOBALS['resources'][0]]);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Signature out of perimeter', $responseBody['errors']);

        $body = [
            'name'   => 'signature1.png',
            'label'  => 'Signature1',
            'base64' => $encodedFile
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $body);

        $userController = new UserController();
        $response = $userController->addSignature($fullRequest, new Response(), ['id' => $GLOBALS['id']]);
        $this->assertSame(200, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertIsInt($responseBody['signatures'][0]['id']);
        self::$signatureIdRenaud = $responseBody['signatures'][0]['id'];

        AdrModel::createDocumentAdr([
            'resId'         => self::$resId,
            'type'          => 'SIGN',
            'docserverId'   => 'docserver_id',
            'path'          => 'directory',
            'filename'      => 'file_destination_name',
            'version'       => 2,
            'fingerprint'   => '1'
        ]);
        $body = [
            'signatureId' => self::$signatureIdRenaud
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $body);
        $response = $signatureBookController->signResource($fullRequest, new Response(), ['resId' => self::$resId]);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Document has already been signed', $responseBody['errors']);

        AdrModel::deleteDocumentAdr([
            'where' => ['res_id = ?', 'type = ?'],
            'data' => [self::$resId, 'SIGN']
        ]);

        // Success
        $body = [
            'signatureId' => self::$signatureIdRenaud
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $body);
        $response = $signatureBookController->signResource($fullRequest, new Response(), ['resId' => self::$resId]);
        $this->assertSame(204, $response->getStatusCode());

        $GLOBALS['login'] = 'superadmin';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];
    }

    public function testUnSignResource()
    {
        $signatureBookController = new SignatureBookController();

        //  CREATE
        $request = $this->createRequest('PUT');

        // Errors
        $GLOBALS['login'] = 'bbain';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        $response = $signatureBookController->unsignResource($request, new Response(), []);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Route resId is not an integer', $responseBody['errors']);

        $response = $signatureBookController->unsignResource($request, new Response(), ['resId' => $GLOBALS['resources'][0] * 1000]);
        $this->assertSame(403, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Document out of perimeter', $responseBody['errors']);

        $GLOBALS['login'] = 'bblier';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        $response = $signatureBookController->unsignResource($request, new Response(), ['resId' => $GLOBALS['resources'][0]]);
        $this->assertSame(403, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Privilege forbidden', $responseBody['errors']);

        $GLOBALS['login'] = 'rrenaud';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        // Success
        $response = $signatureBookController->unsignResource($request, new Response(), ['resId' => self::$resId]);
        $this->assertSame(204, $response->getStatusCode());

        $GLOBALS['login'] = 'superadmin';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];
    }

    public function testSignAttachment()
    {
        $signatureBookController = new SignatureBookController();

        //  CREATE
        $request = $this->createRequest('PUT');

        // Errors
        $GLOBALS['login'] = 'bbain';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        $response = $signatureBookController->signAttachment($request, new Response(), []);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Route id is not an integer', $responseBody['errors']);

        $response = $signatureBookController->signAttachment($request, new Response(), ['id' => self::$attachmentId * 1000]);
        $this->assertSame(403, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Attachment out of perimeter', $responseBody['errors']);

        $response = $signatureBookController->signAttachment($request, new Response(), ['id' => self::$attachmentIdIncoming]);
        $this->assertSame(403, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Document out of signatory book', $responseBody['errors']);

        $GLOBALS['login'] = 'rrenaud';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        $response = $signatureBookController->signAttachment($request, new Response(), ['id' =>self::$attachmentId]);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Body signatureId is empty or not an integer', $responseBody['errors']);

        $body = [
            'signatureId' => 10000
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $body);
        $response = $signatureBookController->signAttachment($fullRequest, new Response(), ['id' =>self::$attachmentId]);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Signature does not exist', $responseBody['errors']);

        $body = [
            'signatureId' => self::$signatureIdPetit
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $body);
        $response = $signatureBookController->signAttachment($fullRequest, new Response(), ['id' =>self::$attachmentId]);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Signature out of perimeter', $responseBody['errors']);

        // Success
        $body = [
            'signatureId' => self::$signatureIdRenaud
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $body);
        $response = $signatureBookController->signAttachment($fullRequest, new Response(), ['id' => self::$attachmentId]);
        $this->assertSame(200, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertIsInt($responseBody['id']);
        self::$signedAttachmentId = $responseBody['id'];

        // Test Get
        $attachmentController = new AttachmentController();
        $request = $this->createRequest('GET');

        $response = $attachmentController->getById($request, new Response(), ['id' => self::$attachmentId]);
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertIsArray($responseBody);

        $this->assertSame('Nulle pierre ne peut être polie sans friction, nul homme ne peut parfaire son expérience sans épreuve.', $responseBody['title']);
        $this->assertSame('response_project', $responseBody['type']);
        $this->assertSame('SIGN', $responseBody['status']);
        $this->assertSame(1, $responseBody['relation']);

        $response = $attachmentController->getById($request, new Response(), ['id' => self::$signedAttachmentId]);
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertIsArray($responseBody);

        $this->assertSame('Nulle pierre ne peut être polie sans friction, nul homme ne peut parfaire son expérience sans épreuve.', $responseBody['title']);
        $this->assertSame('signed_response', $responseBody['type']);
        $this->assertSame('TRA', $responseBody['status']);
        $this->assertSame(1, $responseBody['relation']);

        $response = $attachmentController->getByResId($request, new Response(), ['resId' => self::$resId]);
        $response = json_decode((string)$response->getBody(), true);

        $this->assertNotNull($response['attachments']);
        $this->assertIsArray($response['attachments']);

        $this->assertIsBool($response['mailevaEnabled']);

        $userLabel = UserModel::getLabelledUserById(['id' => $GLOBALS['id']]);
        foreach ($response['attachments'] as $value) {
            if ($value['resId'] == self::$signedAttachmentId) {
                $this->assertSame($userLabel, $value['signatory']);
                $this->assertNotEmpty($value['signDate']);
                break;
            }
        }

        $GLOBALS['login'] = 'superadmin';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];
    }

    public function testUnSignAttachment()
    {
        $signatureBookController = new SignatureBookController();

        //  CREATE
        $request = $this->createRequest('PUT');

        // Errors
        $GLOBALS['login'] = 'bbain';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        $response = $signatureBookController->unsignAttachment($request, new Response(), []);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Route id is not an integer', $responseBody['errors']);

        $response = $signatureBookController->unsignAttachment($request, new Response(), ['id' => self::$attachmentId * 1000]);
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Document out of perimeter', $responseBody['errors']);
        $this->assertSame(403, $response->getStatusCode());

        $GLOBALS['login'] = 'bblier';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        $response = $signatureBookController->unsignAttachment($request, new Response(), ['id' => self::$attachmentId]);
        $this->assertSame(403, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Privilege forbidden', $responseBody['errors']);

        $GLOBALS['login'] = 'rrenaud';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        // Success
        $response = $signatureBookController->unsignAttachment($request, new Response(), ['id' => self::$attachmentId]);
        $this->assertSame(204, $response->getStatusCode());

        $GLOBALS['login'] = 'superadmin';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];
    }

    // TODO change to tearDownAfterClass
    public function testClean()
    {
        $request = $this->createRequest('DELETE');

        $attachmentController = new AttachmentController();

        $response     = $attachmentController->delete($request, new Response(), ['id' => self::$attachmentId]);
        $this->assertSame(204, $response->getStatusCode());

        $response     = $attachmentController->delete($request, new Response(), ['id' => self::$attachmentId2]);
        $this->assertSame(204, $response->getStatusCode());

        $response     = $attachmentController->delete($request, new Response(), ['id' => self::$attachmentIdIncoming]);
        $this->assertSame(204, $response->getStatusCode());

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
