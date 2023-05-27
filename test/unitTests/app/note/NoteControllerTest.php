<?php

/**
* Copyright Maarch since 2008 under licence GPLv3.
* See LICENCE.txt file at the root folder for more details.
* This file is part of Maarch software.
*
*/

namespace MaarchCourrier\Tests\app\note;

use MaarchCourrier\Tests\CourrierTestCase;
use Note\controllers\NoteController;
use SrcCore\http\Response;
use SrcCore\models\DatabaseModel;
use User\models\UserModel;

class NoteControllerTest extends CourrierTestCase
{
    private static $noteId = null;
    private static $noteId2 = null;
    private static $resId = null;

    public function testCreate()
    {
        // GET LAST MAIL
        $getResId = DatabaseModel::select([
            'select'    => ['res_id'],
            'table'     => ['res_letterbox'],
            'order_by'  => ['res_id DESC'],
            'limit'     => 1
        ]);

        self::$resId = $getResId[0]['res_id'];

        $this->assertIsInt(self::$resId);

        $noteController = new NoteController();

        // CREATE WITH ALL DATA -> OK

        $args = [
            'value'     => "Test d'ajout d'une note par php unit",
            'entities'  => ['COU', 'CAB'],
            'resId'     => self::$resId
        ];
        $fullRequest = $this->createRequestWithBody('POST', $args);

        $response     = $noteController->create($fullRequest, new Response());
        $responseBody = json_decode((string)$response->getBody());

        self::$noteId = $responseBody->noteId;

        $this->assertIsInt(self::$noteId);

        // CREATE WITHOUT ENTITIES -> OK
        $args = [
            'value'     => "Test d'ajout d'une note par php unit",
            'resId'     => self::$resId
        ];
        $fullRequest = $this->createRequestWithBody('POST', $args);

        $response     = $noteController->create($fullRequest, new Response());
        $responseBody = json_decode((string)$response->getBody());

        self::$noteId2 = $responseBody->noteId;

        $this->assertIsInt(self::$noteId);

        // CREATE WITH NOTE_TEXT MISSING -> NOT OK
        $body = [
            'entities'  => ["COU", "CAB"],
            'resId'     => self::$resId
        ];
        $fullRequest = $this->createRequestWithBody('POST', $body);

        $response     = $noteController->create($fullRequest, new Response());
        $responseBody = json_decode((string)$response->getBody());

        $this->assertSame('Body value is empty or not a string', $responseBody->errors);
    }

    public function testUpdate()
    {
        $noteController = new NoteController();

        //  Update working
        $args = [
            'value'      => "Test modification d'une note par php unit",
            'entities'   => ['COU', 'DGS'],
            'resId'     => self::$resId
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $args);

        $response     = $noteController->update($fullRequest, new Response(), ['id' => self::$noteId]);

        $this->assertSame(204, $response->getStatusCode());

        // Update fail
        $args = [
            'value' => '',
            'resId' => self::$resId
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $args);

        $response     = $noteController->update($fullRequest, new Response(), ['id' => self::$noteId]);

        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody());

        $this->assertIsString($responseBody->errors);
        $this->assertSame('Body value is empty or not a string', $responseBody->errors);
    }

    public function testGetById()
    {
        $GLOBALS['login'] = 'bblier';
        $userInfo          = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id']     = $userInfo['id'];

        $noteController = new NoteController();

        //  READ
        $request = $this->createRequest('GET');
        $response     = $noteController->getById($request, new Response(), ['id' => self::$noteId]);

        $this->assertSame(200, $response->getStatusCode());

        $responseBody = json_decode((string)$response->getBody());

        $this->assertIsString($responseBody->value);
        $this->assertSame("Test modification d'une note par php unit", $responseBody->value);
        $this->assertIsArray($responseBody->entities);

        $response = $noteController->getById($request, new Response(), ['id' => 999999999]);

        $this->assertSame(403, $response->getStatusCode());

        $responseBody = json_decode((string)$response->getBody());

        $this->assertSame('Note out of perimeter', $responseBody->errors);

        $GLOBALS['login'] = 'superadmin';
        $userInfo          = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id']     = $userInfo['id'];
    }

    public function testGetByResId()
    {
        $GLOBALS['login'] = 'bblier';
        $userInfo          = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id']     = $userInfo['id'];

        $noteController = new NoteController();

        //  READ
        $request = $this->createRequest('GET');
        $response    = $noteController->getByResId($request, new Response(), ['resId' => self::$resId]);

        $this->assertSame(200, $response->getStatusCode());

        $responseBody = json_decode((string)$response->getBody());

        $this->assertIsArray($responseBody->notes);
        $this->assertNotEmpty($responseBody->notes);

        foreach ($responseBody->notes as $value) {
            $this->assertIsInt($value->id);
            $this->assertIsInt($value->identifier);
            $this->assertIsString($value->value);
            $this->assertNotEmpty($value->value);
            $this->assertIsInt($value->user_id);
            $this->assertIsString($value->firstname);
            $this->assertNotEmpty($value->firstname);
            $this->assertIsString($value->lastname);
            $this->assertNotEmpty($value->lastname);
        }

        // ERROR
        $response    = $noteController->getByResId($request, new Response(), ['resId' => 1234859]);
        $responseBody = json_decode((string)$response->getBody());
        $this->assertSame('Document out of perimeter', $responseBody->errors);

        $GLOBALS['login'] = 'superadmin';
        $userInfo          = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id']     = $userInfo['id'];
    }

    public function testGetTemplates()
    {
        $GLOBALS['login'] = 'bblier';
        $userInfo          = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id']     = $userInfo['id'];

        $noteController = new NoteController();

        //  GET
        $request = $this->createRequest('GET');

        $aArgs = [
            "resId" => self::$resId
        ];
        $fullRequest = $request->withQueryParams($aArgs);
        $response    = $noteController->getTemplates($fullRequest, new Response());
        $this->assertSame(200, $response->getStatusCode());

        $responseBody = json_decode((string)$response->getBody());

        $this->assertIsArray($responseBody->templates);

        foreach ($responseBody->templates as $value) {
            $this->assertNotEmpty($value->template_label);
            $this->assertNotEmpty($value->template_content);
        }

        // GET
        $response = $noteController->getTemplates($request, new Response());
        $this->assertSame(200, $response->getStatusCode());

        $responseBody = json_decode((string)$response->getBody());

        $this->assertIsArray($responseBody->templates);

        foreach ($responseBody->templates as $value) {
            $this->assertNotEmpty($value->template_label);
            $this->assertNotEmpty($value->template_content);
        }

        //  ERROR
        $aArgs = [
            "resId" => 19287
        ];
        $fullRequest = $request->withQueryParams($aArgs);
        $response     = $noteController->getTemplates($fullRequest, new Response());
        $responseBody = json_decode((string)$response->getBody());

        $this->assertSame('Document out of perimeter', $responseBody->errors);

        $GLOBALS['login'] = 'superadmin';
        $userInfo          = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id']     = $userInfo['id'];
    }

    public function testDelete()
    {
        //  DELETE
        $request = $this->createRequest('DELETE');

        $noteController = new NoteController();
        $response         = $noteController->delete($request, new Response(), ['id' => self::$noteId]);

        $this->assertSame(204, $response->getStatusCode());

        //  READ
        $request = $this->createRequest('GET');
        $response     = $noteController->getById($request, new Response(), ['id' => self::$noteId]);

        $this->assertSame(403, $response->getStatusCode());

        $responseBody = json_decode((string)$response->getBody());

        $this->assertIsString($responseBody->errors);
        $this->assertSame('Note out of perimeter', $responseBody->errors);

        // FAIL DELETE
        $noteController = new NoteController();
        $response         = $noteController->delete($request, new Response(), ['id' => self::$noteId]);
        $responseBody     = json_decode((string)$response->getBody());

        $this->assertSame('Note out of perimeter', $responseBody->errors);
        $this->assertSame(403, $response->getStatusCode());

        $noteController->delete($request, new Response(), ['id' => self::$noteId2]);
    }
}
