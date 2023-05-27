<?php

/**
 * Copyright Maarch since 2008 under licence GPLv3.
 * See LICENCE.txt file at the root folder for more details.
 * This file is part of Maarch software.
 *
 */

namespace MaarchCourrier\Tests\app\contact;

use Contact\controllers\ContactGroupController;
use Contact\models\ContactModel;
use SrcCore\http\Response;
use MaarchCourrier\Tests\CourrierTestCase;
use User\models\UserModel;

class ContactGroupControllerTest extends CourrierTestCase
{
    private static $id = null;
    private static $id2 = null;


    public function testCreate()
    {
        $contactGroupController = new ContactGroupController();

        //  CREATE
        $body = [
            'label'             => 'Groupe petition',
            'description'       => 'Groupe de petition'
        ];
        $fullRequest = $this->createRequestWithBody('POST', $body);

        $response     = $contactGroupController->create($fullRequest, new Response());
        $this->assertSame(200, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);

        $this->assertIsInt($responseBody['id']);
        self::$id = $responseBody['id'];

        $GLOBALS['login'] = 'bbain';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        $body = [
            'label'             => 'Groupe petition 2',
            'description'       => 'Groupe de petition'
        ];
        $fullRequest = $this->createRequestWithBody('POST', $body);

        $response     = $contactGroupController->create($fullRequest, new Response());
        $this->assertSame(200, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);

        $this->assertIsInt($responseBody['id']);
        self::$id2 = $responseBody['id'];


        //  READ
        $request = $this->createRequest('GET');
        $response       = $contactGroupController->getById($request, new Response(), ['id' => self::$id]);
        $this->assertSame(403, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Contacts group out of perimeter', $responseBody['errors']);

        $GLOBALS['login'] = 'superadmin';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        $response       = $contactGroupController->getById($request, new Response(), ['id' => self::$id]);
        $responseBody   = json_decode((string)$response->getBody());

        $user = UserModel::getByLogin(['select' => ['id'], 'login' => 'superadmin']);
        $this->assertSame(self::$id, $responseBody->contactsGroup->id);
        $this->assertSame('Groupe petition', $responseBody->contactsGroup->label);
        $this->assertSame('Groupe de petition', $responseBody->contactsGroup->description);
        $this->assertSame($user['id'], $responseBody->contactsGroup->owner);
        $this->assertIsString($responseBody->contactsGroup->labelledOwner);

        // Fail
        $body = [
            'label'             => 'Groupe petition',
        ];
        $fullRequest = $this->createRequestWithBody('POST', $body);

        $response     = $contactGroupController->create($fullRequest, new Response());
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Body description is empty or not a string', $responseBody['errors']);
    }

    public function testGet()
    {
        $GLOBALS['login'] = 'bbain';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        $contactGroupController = new ContactGroupController();

        //  GET
        $request = $this->createRequest('GET');
        $response       = $contactGroupController->get($request, new Response());
        $responseBody   = json_decode((string)$response->getBody());

        $this->assertIsArray($responseBody->contactsGroups);
        $this->assertNotNull($responseBody->contactsGroups);

        $GLOBALS['login'] = 'superadmin';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];
    }

    public function testUpdate()
    {
        $contactGroupController = new ContactGroupController();

        //  UPDATE
        $body = [
            'label'             => 'Groupe petition updated',
            'description'       => 'Groupe de petition updated',
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $body);

        $response     = $contactGroupController->update($fullRequest, new Response(), ['id' => self::$id]);
        $this->assertSame(204, $response->getStatusCode());

        //  READ
        $request = $this->createRequest('GET');
        $response       = $contactGroupController->getById($request, new Response(), ['id' => self::$id]);
        $responseBody   = json_decode((string)$response->getBody());

        $this->assertSame(self::$id, $responseBody->contactsGroup->id);
        $this->assertSame('Groupe petition updated', $responseBody->contactsGroup->label);
        $this->assertSame('Groupe de petition updated', $responseBody->contactsGroup->description);
        $this->assertIsString($responseBody->contactsGroup->labelledOwner);

        // Fail
        $body = [
            'label'             => 'Groupe petition updated',
            'description'       => 'Groupe de petition updated',
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $body);

        $response     = $contactGroupController->update($fullRequest, new Response(), ['id' => self::$id * 1000]);
        $this->assertSame(403, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Contacts group out of perimeter', $responseBody['errors']);

        $GLOBALS['login'] = 'bbain';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        $response     = $contactGroupController->update($fullRequest, new Response(), ['id' => self::$id]);
        $this->assertSame(403, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Contacts group out of perimeter', $responseBody['errors']);

        $GLOBALS['login'] = 'superadmin';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        $body = [
            'label'             => 'Groupe petition updated'
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $body);

        $response     = $contactGroupController->update($fullRequest, new Response(), ['id' => self::$id]);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Body description is empty or not a string', $responseBody['errors']);
    }

    public function testAddCorrespondents()
    {
        $contactGroupController = new ContactGroupController();

        $contacts = ContactModel::get([
            'select'    => ['id'],
            'limit'     => 1
        ]);

        if (!empty($contacts[0])) {
            //  UPDATE

            $body = [
                'correspondents'    => ['id' => $contacts[0]['id'], 'type' => 'contact']
            ];
            $fullRequest = $this->createRequestWithBody('PUT', $body);

            $response     = $contactGroupController->addCorrespondents($fullRequest, new Response(), ['id' => self::$id]);
            $this->assertSame(204, $response->getStatusCode());
        }

        $body = [

        ];
        $fullRequest = $this->createRequestWithBody('PUT', $body);

        $response     = $contactGroupController->addCorrespondents($fullRequest, new Response(), ['id' => self::$id * 1000]);
        $this->assertSame(403, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Contacts group out of perimeter', $responseBody['errors']);

        $GLOBALS['login'] = 'bbain';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        $response     = $contactGroupController->addCorrespondents($fullRequest, new Response(), ['id' => self::$id]);
        $this->assertSame(403, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Contacts group out of perimeter', $responseBody['errors']);

        $GLOBALS['login'] = 'superadmin';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        $response     = $contactGroupController->addCorrespondents($fullRequest, new Response(), ['id' => self::$id]);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Body correspondents is empty or not an array', $responseBody['errors']);
    }

    public function testDeleteCorrespondents()
    {
        $contactGroupController = new ContactGroupController();

        $contacts = ContactModel::get([
            'select'    => ['id'],
            'limit'     => 1
        ]);

        if (!empty($contacts[0])) {
            //  DELETE
            $body = [
                'correspondents'    => ['id' => $contacts[0]['id'], 'type' => 'contact']
            ];
            $fullRequest = $this->createRequestWithBody('DELETE', $body);

            $response     = $contactGroupController->deleteCorrespondents($fullRequest, new Response(), ['id' => self::$id]);
            $this->assertSame(204, $response->getStatusCode());
        }

        //  READ
        $request = $this->createRequest('GET');
        $response       = $contactGroupController->getById($request, new Response(), ['id' => self::$id]);
        $responseBody   = json_decode((string)$response->getBody());

        $user = UserModel::getByLogin(['select' => ['id'], 'login' => 'superadmin']);
        $this->assertSame(self::$id, $responseBody->contactsGroup->id);
        $this->assertSame($user['id'], $responseBody->contactsGroup->owner);
        $this->assertIsString($responseBody->contactsGroup->labelledOwner);

        $request = $this->createRequest('DELETE');

        $response     = $contactGroupController->deleteCorrespondents($request, new Response(), ['id' => self::$id * 1000]);
        $this->assertSame(403, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Contacts group out of perimeter', $responseBody['errors']);

        $GLOBALS['login'] = 'bbain';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        $response     = $contactGroupController->deleteCorrespondents($request, new Response(), ['id' => self::$id]);
        $this->assertSame(403, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Contacts group out of perimeter', $responseBody['errors']);

        $GLOBALS['login'] = 'superadmin';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];
    }

    public function testDelete()
    {
        $contactGroupController = new ContactGroupController();

        //  DELETE
        $request = $this->createRequest('DELETE');

        // Fail
        $response     = $contactGroupController->delete($request, new Response(), ['id' => self::$id * 1000]);
        $this->assertSame(403, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Contacts group out of perimeter', $responseBody['errors']);

        $GLOBALS['login'] = 'bbain';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        $response     = $contactGroupController->delete($request, new Response(), ['id' => self::$id]);
        $this->assertSame(403, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Contacts group out of perimeter', $responseBody['errors']);

        $GLOBALS['login'] = 'superadmin';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        // Sucess
        $response       = $contactGroupController->delete($request, new Response(), ['id' => self::$id]);
        $this->assertSame(204, $response->getStatusCode());

        $response       = $contactGroupController->delete($request, new Response(), ['id' => self::$id2]);
        $this->assertSame(204, $response->getStatusCode());

        //  READ
        $request = $this->createRequest('GET');
        $response       = $contactGroupController->getById($request, new Response(), ['id' => self::$id]);
        $responseBody   = json_decode((string)$response->getBody());

        $this->assertSame('Contacts group out of perimeter', $responseBody->errors);
    }
}
