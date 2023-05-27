<?php

/**
* Copyright Maarch since 2008 under licence GPLv3.
* See LICENCE.txt file at the root folder for more details.
* This file is part of Maarch software.
*
*/

namespace MaarchCourrier\Tests\app\user;

use Entity\controllers\EntityController;
use Entity\models\EntityModel;
use Firebase\JWT\JWT;
use MaarchCourrier\Tests\CourrierTestCase;
use Parameter\controllers\ParameterController;
use SrcCore\controllers\AuthenticationController;
use SrcCore\http\Response;
use SrcCore\models\AuthenticationModel;
use SrcCore\models\CoreConfigModel;
use SrcCore\models\DatabaseModel;
use User\controllers\UserController;
use User\models\UserModel;

class UserControllerTest extends CourrierTestCase
{
    private static $id = null;
    private static $idEmailSignature = null;
    private static $redirectId = null;
    private static $signatureId = null;

    public function testGet()
    {
        $userController = new UserController();

        //  READ
        $request = $this->createRequest('GET');

        $response     = $userController->get($request, new Response());
        $this->assertSame(200, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);

        $this->assertIsArray($responseBody['users']);
        $this->assertNotEmpty($responseBody['users']);

        foreach ($responseBody['users'] as $value) {
            $this->assertNotNull($value['id']);
            $this->assertIsInt($value['id']);
            $this->assertNotNull($value['user_id']);
            $this->assertIsString($value['user_id']);
            $this->assertNotNull($value['firstname']);
            $this->assertIsString($value['firstname']);
            $this->assertNotNull($value['lastname']);
            $this->assertIsString($value['lastname']);
            $this->assertNotNull($value['status']);
            $this->assertIsString($value['status']);
            $this->assertNotNull($value['mail']);
            $this->assertIsString($value['mail']);
            $this->assertNotNull($value['mode']);
            $this->assertIsString($value['mode']);
        }

        $GLOBALS['login'] = 'bblier';
        $userInfo          = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id']     = $userInfo['id'];

        $response     = $userController->get($request, new Response());
        $this->assertSame(200, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);

        $this->assertIsArray($responseBody['users']);
        $this->assertNotEmpty($responseBody['users']);

        foreach ($responseBody['users'] as $value) {
            $this->assertNotNull($value['id']);
            $this->assertIsInt($value['id']);
            $this->assertNotNull($value['user_id']);
            $this->assertIsString($value['user_id']);
            $this->assertNotNull($value['firstname']);
            $this->assertIsString($value['firstname']);
            $this->assertNotNull($value['lastname']);
            $this->assertIsString($value['lastname']);
            $this->assertNotNull($value['status']);
            $this->assertIsString($value['status']);
            $this->assertNotNull($value['mail']);
            $this->assertIsString($value['mail']);
            $this->assertNotNull($value['mode']);
            $this->assertIsString($value['mode']);
        }

        // Fail
        $GLOBALS['login'] = 'bbain';
        $userInfo          = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id']     = $userInfo['id'];

        $response     = $userController->get($request, new Response());
        $this->assertSame(403, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);

        $this->assertSame('Service forbidden', $responseBody['errors']);

        $GLOBALS['login'] = 'superadmin';
        $userInfo          = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id']     = $userInfo['id'];
    }

    public function testCreate()
    {
        $userController = new UserController();

        //  CREATE
        $args = [
            'userId'    => 'test-ckent',
            'firstname' => 'TEST-CLARK',
            'lastname'  => 'TEST-KENT',
            'mail'      => 'clark@test.zh'
        ];
        $fullRequest = $this->createRequestWithBody('POST', $args);

        $response     = $userController->create($fullRequest, new Response());
        $responseBody = json_decode((string)$response->getBody());

        self::$id = $responseBody->id;

        $this->assertIsInt(self::$id);

        //  READ
        $request = $this->createRequest('GET');
        $response     = $userController->getDetailledById($request, new Response(), ['id' => self::$id]);
        $responseBody = json_decode((string)$response->getBody());

        $this->assertSame(self::$id, $responseBody->id);
        $this->assertSame('test-ckent', $responseBody->user_id);
        $this->assertSame('TEST-CLARK', $responseBody->firstname);
        $this->assertSame('TEST-KENT', $responseBody->lastname);
        $this->assertSame('OK', $responseBody->status);
        $this->assertSame(null, $responseBody->phone);
        $this->assertSame('clark@test.zh', $responseBody->mail);
        $this->assertSame(null, $responseBody->initials);

        // Delete user then reactivate it
        UserModel::update([
            'set'   => ['status' => 'DEL'],
            'where' => ['id = ?'],
            'data'  => [self::$id]
        ]);

        $args = [
            'userId'    => 'test-ckent',
            'firstname' => 'TEST-CLARK',
            'lastname'  => 'TEST-KENT',
            'mail'      => 'clark@test.zh'
        ];
        $fullRequest = $this->createRequestWithBody('POST', $args);

        $response     = $userController->create($fullRequest, new Response());
        $responseBody = json_decode((string)$response->getBody(), true);

        $this->assertSame(self::$id, $responseBody['id']);

        // Fail
        $body = [
            'userId'    => 'test-ckent',
            'firstname' => 'TEST-CLARK',
            'lastname'  => 'TEST-KENT',
            'mail'      => 'clark@test.zh'
        ];
        $fullRequest = $this->createRequestWithBody('POST', $body);

        $response     = $userController->create($fullRequest, new Response());
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);

        $this->assertSame(_USER_ID_ALREADY_EXISTS, $responseBody['errors']);

        $body = [
            'userId'    => 'test-ckent',
            'firstname' => 12, // wrong format
            'lastname'  => 'TEST-KENT',
            'mail'      => 'clark@test.zh'
        ];
        $fullRequest = $this->createRequestWithBody('POST', $body);

        $response     = $userController->create($fullRequest, new Response());
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);

        $this->assertSame('Body firstname is empty or not a string', $responseBody['errors']);


        $GLOBALS['login'] = 'bbain';
        $userInfo          = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id']     = $userInfo['id'];

        $response     = $userController->create($fullRequest, new Response());
        $this->assertSame(403, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);

        $this->assertSame('Service forbidden', $responseBody['errors']);

        $GLOBALS['login'] = 'superadmin';
        $userInfo          = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id']     = $userInfo['id'];
    }

    public function testGetById()
    {
        $userController = new UserController();

        //  READ
        $request = $this->createRequest('GET');

        $response     = $userController->getById($request, new Response(), ['id' => self::$id]);
        $this->assertSame(200, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);

        $this->assertIsArray($responseBody);
        $this->assertNotEmpty($responseBody);

        $this->assertSame(self::$id, $responseBody['id']);
        $this->assertSame('TEST-CLARK', $responseBody['firstname']);
        $this->assertSame('TEST-KENT', $responseBody['lastname']);

        // Fail
        $response     = $userController->getById($request, new Response(), ['id' => self::$id * 1000]);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);

        $this->assertSame('User does not exist', $responseBody['errors']);
    }

    public function testUpdate()
    {
        $userController = new UserController();

        //  UPDATE
        $args = [
            'user_id'    => 'test-ckent',
            'firstname' => 'TEST-CLARK2',
            'lastname'  => 'TEST-KENT2',
            'mail'      => 'ck@dailyP.com',
            'phone'     => '0122334455',
            'initials'  => 'CK',
            'status'    => 'OK'
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $args);

        $response     = $userController->update($fullRequest, new Response(), ['id' => self::$id]);
        $this->assertSame(204, $response->getStatusCode());


        //  READ
        $request = $this->createRequest('GET');
        $response     = $userController->getDetailledById($request, new Response(), ['id' => self::$id]);
        $responseBody = json_decode((string)$response->getBody());

        $this->assertSame(self::$id, $responseBody->id);
        $this->assertSame('test-ckent', $responseBody->user_id);
        $this->assertSame('TEST-CLARK2', $responseBody->firstname);
        $this->assertSame('TEST-KENT2', $responseBody->lastname);
        $this->assertSame('OK', $responseBody->status);
        $this->assertSame('0122334455', $responseBody->phone);
        $this->assertSame('ck@dailyP.com', $responseBody->mail);
        $this->assertSame('CK', $responseBody->initials);

        // Fail
        $body = [
            'user_id'    => 'test-ckent',
            'firstname' => 'TEST-CLARK2',
            'lastname'  => 'TEST-KENT2',
            'mail'      => 'ck@dailyP.com',
            'phone'     => '0122334455',
            'initials'  => 'CK'
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $body);

        $response     = $userController->update($fullRequest, new Response(), ['id' => 'wrong format']);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('id must be an integer', $responseBody['errors']);

        $body = [
            'user_id'    => 'test-ckent',
            'firstname' => 'TEST-CLARK2',
            'lastname'  => 'TEST-KENT2',
            'mail'      => 'ck@dailyP.com',
            'phone'     => 'wrong format',
            'initials'  => 'CK'
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $body);

        $response     = $userController->update($fullRequest, new Response(), ['id' => self::$id]);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Body phone is not correct', $responseBody['errors']);
    }

    public function testAddGroup()
    {
        $userController = new UserController();

        //  CREATE
        $body = [
            'groupId'   => 'AGENT',
            'role'      => 'Douche'
        ];
        $fullRequest = $this->createRequestWithBody('POST', $body);

        $response     = $userController->addGroup($fullRequest, new Response(), ['id' => self::$id]);
        $responseBody = json_decode((string)$response->getBody());

        $this->assertIsArray($responseBody->groups);
        $this->assertIsArray($responseBody->baskets);

        //  READ
        $request = $this->createRequest('GET');
        $response     = $userController->getDetailledById($request, new Response(), ['id' => self::$id]);
        $responseBody = json_decode((string)$response->getBody());

        $this->assertSame(self::$id, $responseBody->id);
        $this->assertIsArray($responseBody->groups);
        $this->assertSame('AGENT', $responseBody->groups[0]->group_id);
        $this->assertSame('Douche', $responseBody->groups[0]->role);

        // Fail
        $body = [
            'role'      => 'Douche'
        ];
        $fullRequest = $this->createRequestWithBody('POST', $body);

        $response     = $userController->addGroup($fullRequest, new Response(), ['id' => self::$id * 1000]);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('User not found', $responseBody['errors']);

        $response     = $userController->addGroup($fullRequest, new Response(), ['id' => self::$id]);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Bad Request', $responseBody['errors']);

        $body = [
            'groupId'   => 'SECRET_AGENT',
            'role'      => 'Douche'
        ];
        $fullRequest = $this->createRequestWithBody('POST', $body);

        $response     = $userController->addGroup($fullRequest, new Response(), ['id' => self::$id]);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Group not found', $responseBody['errors']);

        $body = [
            'groupId'   => 'AGENT',
            'role'      => 'Douche'
        ];
        $fullRequest = $this->createRequestWithBody('POST', $body);

        $response     = $userController->addGroup($fullRequest, new Response(), ['id' => self::$id]);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame(_USER_ALREADY_LINK_GROUP, $responseBody['errors']);

        $GLOBALS['login'] = 'bbain';
        $userInfo          = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id']     = $userInfo['id'];

        $body = [
            'groupId'   => 'COURRIER',
            'role'      => 'Douche'
        ];
        $fullRequest = $this->createRequestWithBody('POST', $body);

        $response     = $userController->addGroup($fullRequest, new Response(), ['id' => self::$id]);
        $this->assertSame(403, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Service forbidden', $responseBody['errors']);

        $GLOBALS['login'] = 'superadmin';
        $userInfo          = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id']     = $userInfo['id'];
    }

    public function testUpdateGroup()
    {
        $userController = new UserController();

        //  UPDATE
        $args = [
            'role'      => 'role updated'
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $args);

        $response     = $userController->updateGroup($fullRequest, new Response(), ['id' => self::$id, 'groupId' => 'AGENT']);
        $responseBody = json_decode((string)$response->getBody());

        $this->assertSame('success', $responseBody->success);

        //  READ
        $request = $this->createRequest('GET');
        $response     = $userController->getDetailledById($request, new Response(), ['id' => self::$id]);
        $responseBody = json_decode((string)$response->getBody());

        $this->assertSame(self::$id, $responseBody->id);
        $this->assertIsArray($responseBody->groups);
        $this->assertSame('AGENT', $responseBody->groups[0]->group_id);
        $this->assertSame('role updated', $responseBody->groups[0]->role);

        // Fail
        $response     = $userController->updateGroup($fullRequest, new Response(), ['id' => self::$id * 1000]);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('User not found', $responseBody['errors']);

        $response     = $userController->updateGroup($fullRequest, new Response(), ['id' => self::$id, 'groupId' => 'SECRET_AGENT']);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Group not found', $responseBody['errors']);
    }

    public function testDeleteGroup()
    {
        $userController = new UserController();

        //  DELETE
        $request = $this->createRequest('DELETE');
        $response     = $userController->deleteGroup($request, new Response(), ['id' => self::$id, 'groupId' => 'AGENT']);
        $responseBody = json_decode((string)$response->getBody());

        $this->assertIsArray($responseBody->groups);
        $this->assertEmpty($responseBody->groups);
        $this->assertIsArray($responseBody->baskets);

        //  READ
        $request = $this->createRequest('GET');
        $response     = $userController->getDetailledById($request, new Response(), ['id' => self::$id]);
        $responseBody = json_decode((string)$response->getBody());

        $this->assertSame(self::$id, $responseBody->id);
        $this->assertIsArray($responseBody->groups);
        $this->assertEmpty($responseBody->groups);

        // Fail
        $request = $this->createRequest('DELETE');

        $response     = $userController->deleteGroup($request, new Response(), ['id' => self::$id * 1000]);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('User not found', $responseBody['errors']);

        $response     = $userController->deleteGroup($request, new Response(), ['id' => self::$id, 'groupId' => 'SECRET_AGENT']);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Group not found', $responseBody['errors']);
    }

    public function testAddEntity()
    {
        $userController = new UserController();

        //  CREATE
        $body = [
            'entityId'  => 'DGS',
            'role'      => 'Warrior'
        ];
        $fullRequest = $this->createRequestWithBody('POST', $body);

        $response     = $userController->addEntity($fullRequest, new Response(), ['id' => self::$id]);
        $responseBody = json_decode((string)$response->getBody());

        $this->assertIsArray($responseBody->entities);
        $this->assertIsArray($responseBody->allEntities);

        //  CREATE
        $body = [
            'entityId'  => 'FIN',
            'role'      => 'Hunter'
        ];
        $fullRequest = $this->createRequestWithBody('POST', $body);

        $response     = $userController->addEntity($fullRequest, new Response(), ['id' => self::$id]);
        $responseBody = json_decode((string)$response->getBody());

        $this->assertIsArray($responseBody->entities);
        $this->assertIsArray($responseBody->allEntities);

        //  READ
        $request = $this->createRequest('GET');
        $response     = $userController->getDetailledById($request, new Response(), ['id' => self::$id]);
        $responseBody = json_decode((string)$response->getBody());

        $this->assertSame(self::$id, $responseBody->id);
        $this->assertIsArray($responseBody->entities);
        $this->assertSame('DGS', $responseBody->entities[0]->entity_id);
        $this->assertSame('Warrior', $responseBody->entities[0]->user_role);
        $this->assertSame('Y', $responseBody->entities[0]->primary_entity);
        $this->assertSame('FIN', $responseBody->entities[1]->entity_id);
        $this->assertSame('Hunter', $responseBody->entities[1]->user_role);
        $this->assertSame('N', $responseBody->entities[1]->primary_entity);

        // Fail
        $body = [
            'entityId'  => 'SECRET_SERVICE',
            'role'      => 'Hunter'
        ];
        $fullRequest = $this->createRequestWithBody('POST', $body);

        $response     = $userController->addEntity($fullRequest, new Response(), ['id' => self::$id * 1000]);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('User not found', $responseBody['errors']);

        $response     = $userController->addEntity($fullRequest, new Response(), ['id' => self::$id]);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Entity not found', $responseBody['errors']);

        $body = [
            'entityId'  => 'FIN',
            'role'      => 'Hunter'
        ];
        $fullRequest = $this->createRequestWithBody('POST', $body);

        $response     = $userController->addEntity($fullRequest, new Response(), ['id' => self::$id]);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame(_USER_ALREADY_LINK_ENTITY, $responseBody['errors']);

        $body = [
            'role'      => 'Hunter'
        ];
        $fullRequest = $this->createRequestWithBody('POST', $body);

        $response     = $userController->addEntity($fullRequest, new Response(), ['id' => self::$id]);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Bad Request', $responseBody['errors']);
    }

    public function testGetEntities()
    {
        $userController = new UserController();

        //  READ
        $request = $this->createRequest('GET');
        $response     = $userController->getEntities($request, new Response(), ['id' => self::$id]);
        $responseBody = json_decode((string)$response->getBody(), true);

        $this->assertIsArray($responseBody['entities']);
        $this->assertSame('DGS', $responseBody['entities'][0]['entity_id']);
        $this->assertSame('Warrior', $responseBody['entities'][0]['user_role']);
        $this->assertSame('Y', $responseBody['entities'][0]['primary_entity']);
        $this->assertSame('FIN', $responseBody['entities'][1]['entity_id']);
        $this->assertSame('Hunter', $responseBody['entities'][1]['user_role']);
        $this->assertSame('N', $responseBody['entities'][1]['primary_entity']);

        // Fail

        $response     = $userController->getEntities($request, new Response(), ['id' => self::$id * 1000]);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('User does not exist', $responseBody['errors']);
    }

    public function testUpdateEntity()
    {
        $userController = new UserController();

        //  UPDATE
        $args = [

        ];
        $fullRequest = $this->createRequestWithBody('PUT', $args);

        $response     = $userController->updateEntity($fullRequest, new Response(), ['id' => self::$id, 'entityId' => 'DGS']);
        $responseBody = json_decode((string)$response->getBody());

        $this->assertSame('success', $responseBody->success);

        $args = [
            'user_role'      => 'Rogue'
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $args);

        $response     = $userController->updateEntity($fullRequest, new Response(), ['id' => self::$id, 'entityId' => 'DGS']);
        $responseBody = json_decode((string)$response->getBody());

        $this->assertSame('success', $responseBody->success);

        //  READ
        $request = $this->createRequest('GET');
        $response     = $userController->getDetailledById($request, new Response(), ['id' => self::$id]);
        $responseBody = json_decode((string)$response->getBody());

        $this->assertSame(self::$id, $responseBody->id);
        $this->assertIsArray($responseBody->entities);
        $this->assertSame('DGS', $responseBody->entities[0]->entity_id);
        $this->assertSame('Rogue', $responseBody->entities[0]->user_role);
        $this->assertSame('Y', $responseBody->entities[0]->primary_entity);

        // Fail
        $body = [
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $body);

        $response     = $userController->updateEntity($fullRequest, new Response(), ['id' => self::$id * 1000, 'entityId' => 'DGS']);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('User not found', $responseBody['errors']);

        $response     = $userController->updateEntity($fullRequest, new Response(), ['id' => self::$id, 'entityId' => 'SECRET_SERVICE']);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Entity not found', $responseBody['errors']);
    }

    public function testGetUsersById()
    {
        $entityController = new EntityController();

        $request = $this->createRequest('GET');

        $entityInfo     = EntityModel::getByEntityId(['entityId' => 'DGS', 'select' => ['id']]);
        $response       = $entityController->getById($request, new Response(), ['id' => $entityInfo['id']]);
        $responseBody   = json_decode((string)$response->getBody());
        $entitySerialId = $responseBody->id;

        $response     = $entityController->getUsersById($request, new Response(), ['id' => $entitySerialId]);
        $responseBody = json_decode((string)$response->getBody());

        $this->assertNotNull($responseBody->users);

        $found = false;
        foreach ($responseBody->users as $value) {
            $this->assertNotNull($value->id);
            $this->assertIsInt($value->id);
            $this->assertNotNull($value->user_id);
            $this->assertNotNull($value->firstname);
            $this->assertNotNull($value->lastname);
            $this->assertNotNull($value->labelToDisplay);
            $this->assertNotNull($value->descriptionToDisplay);

            if ($value->id == self::$id) {
                $this->assertSame('test-ckent', $value->user_id);
                $this->assertSame('TEST-CLARK2', $value->firstname);
                $this->assertSame('TEST-KENT2', $value->lastname);
                $this->assertSame($value->firstname . ' ' . $value->lastname, $value->labelToDisplay);
                $found = true;
            }
        }

        $this->assertSame(true, $found);

        //ERROR
        $response     = $entityController->getUsersById($request, new Response(), ['id' => 99989]);
        $responseBody = json_decode((string)$response->getBody());
        $this->assertSame('Entity not found', $responseBody->errors);
    }

    public function testIsDeletable()
    {
        $userController = new UserController();

        //  GET
        $request = $this->createRequest('GET');

        $response     = $userController->isDeletable($request, new Response(), ['id' => self::$id]);
        $responseBody = json_decode((string)$response->getBody());

        $this->assertSame(true, $responseBody->isDeletable);
        $this->assertIsArray($responseBody->listTemplates);
        $this->assertEmpty($responseBody->listTemplates);
        $this->assertIsArray($responseBody->listInstances);
        $this->assertEmpty($responseBody->listInstances);

        $user = UserModel::getByLogin(['login' => 'ggrand', 'select' => ['id']]);

        $response     = $userController->isDeletable($request, new Response(), ['id' => $user['id']]);
        $this->assertSame(200, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame(true, $responseBody['isDeletable']);
        $this->assertIsArray($responseBody['listTemplates']);
        $this->assertNotEmpty($responseBody['listTemplates']);
        $this->assertIsArray($responseBody['listInstances']);
        $this->assertEmpty($responseBody['listInstances']);

        // Fail
        $response     = $userController->isDeletable($request, new Response(), ['id' => self::$id * 1000]);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('User not found', $responseBody['errors']);
    }

    public function testIsEntityDeletable()
    {
        $userController = new UserController();

        //  GET
        $request = $this->createRequest('GET');

        $response     = $userController->isEntityDeletable($request, new Response(), ['id' => self::$id, 'entityId' => 'DGS']);
        $responseBody = json_decode((string)$response->getBody());

        $this->assertSame(false, $responseBody->hasConfidentialityInstances);
        $this->assertSame(false, $responseBody->hasListTemplates);

        // Fail
        $response     = $userController->isEntityDeletable($request, new Response(), ['id' => self::$id * 1000, 'entityId' => 'DGS']);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('User not found', $responseBody['errors']);

        $response     = $userController->isEntityDeletable($request, new Response(), ['id' => self::$id, 'entityId' => 'SECRET_SERVICE']);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Entity does not exist', $responseBody['errors']);
    }

    public function testUpdatePrimaryEntity()
    {
        $userController = new UserController();

        //  UPDATE
        $request = $this->createRequest('PUT');

        $response     = $userController->updatePrimaryEntity($request, new Response(), ['id' => self::$id, 'entityId' => 'FIN']);
        $responseBody = json_decode((string)$response->getBody());

        $this->assertIsArray($responseBody->entities);

        //  READ
        $request = $this->createRequest('GET');
        $response     = $userController->getDetailledById($request, new Response(), ['id' => self::$id]);
        $responseBody = json_decode((string)$response->getBody());

        $this->assertSame(self::$id, $responseBody->id);
        $this->assertIsArray($responseBody->entities);
        $this->assertSame('FIN', $responseBody->entities[0]->entity_id);
        $this->assertSame('Hunter', $responseBody->entities[0]->user_role);
        $this->assertSame('Y', $responseBody->entities[0]->primary_entity);
        $this->assertSame('DGS', $responseBody->entities[1]->entity_id);
        $this->assertSame('Rogue', $responseBody->entities[1]->user_role);
        $this->assertSame('N', $responseBody->entities[1]->primary_entity);

        // Fail
        $response     = $userController->updatePrimaryEntity($request, new Response(), ['id' => self::$id * 1000, 'entityId' => 'DGS']);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('User not found', $responseBody['errors']);

        $response     = $userController->updatePrimaryEntity($request, new Response(), ['id' => self::$id, 'entityId' => 'SECRET_SERVICE']);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Entity not found', $responseBody['errors']);
    }

    public function testDeleteEntity()
    {
        $userController = new UserController();

        //  DELETE
        $body = [
            'mode' => 'anything_but_reaffect'
        ];
        $fullRequest = $this->createRequestWithBody('DELETE', $body);

        $response     = $userController->deleteEntity($fullRequest, new Response(), ['id' => self::$id, 'entityId' => 'FIN']);
        $responseBody = json_decode((string)$response->getBody());

        $this->assertIsArray($responseBody->entities);
        $this->assertIsArray($responseBody->allEntities);

        //  DELETE
        $body = [
            'mode' => 'reaffect'
        ];
        $fullRequest = $this->createRequestWithBody('DELETE', $body);

        $response     = $userController->deleteEntity($fullRequest, new Response(), ['id' => self::$id, 'entityId' => 'DGS']);
        $responseBody = json_decode((string)$response->getBody());

        $this->assertIsArray($responseBody->entities);
        $this->assertEmpty($responseBody->entities);
        $this->assertIsArray($responseBody->allEntities);

        //  READ
        $request = $this->createRequest('GET');
        $response     = $userController->getDetailledById($request, new Response(), ['id' => self::$id]);
        $responseBody = json_decode((string)$response->getBody());

        $this->assertSame(self::$id, $responseBody->id);
        $this->assertIsArray($responseBody->entities);
        $this->assertEmpty($responseBody->entities);

        // Fail
        $request = $this->createRequest('DELETE');
        $response     = $userController->deleteEntity($request, new Response(), ['id' => self::$id * 1000, 'entityId' => 'DGS']);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('User not found', $responseBody['errors']);

        $response     = $userController->deleteEntity($request, new Response(), ['id' => self::$id, 'entityId' => 'SECRET_ENTITY']);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Entity not found', $responseBody['errors']);
    }

    public function testGetStatusByUserId()
    {
        $userController = new UserController();

        $request = $this->createRequest('GET');
        $response     = $userController->getStatusByUserId($request, new Response(), ['userId' => 'test-ckent']);
        $this->assertSame(200, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);

        $this->assertSame('OK', $responseBody['status']);

        // Fail
        $response     = $userController->getStatusByUserId($request, new Response(), ['userId' => 'test-ckent1234']);
        $this->assertSame(200, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertNull($responseBody['status']);

        $GLOBALS['login'] = 'bbain';
        $userInfo          = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id']     = $userInfo['id'];

        $response     = $userController->getStatusByUserId($request, new Response(), ['userId' => 'test-ckent']);
        $this->assertSame(403, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Service forbidden', $responseBody['errors']);

        $GLOBALS['login'] = 'superadmin';
        $userInfo          = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id']     = $userInfo['id'];
    }

    public function testUpdateStatus()
    {
        $userController = new UserController();

        //  UPDATE
        $args = [
            'status'    => 'ABS'
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $args);

        $response     = $userController->updateStatus($fullRequest, new Response(), ['id' => self::$id]);
        $responseBody = json_decode((string)$response->getBody());

        $this->assertSame('ABS', $responseBody->user->status);

        //  READ
        $request = $this->createRequest('GET');
        $response     = $userController->getDetailledById($request, new Response(), ['id' => self::$id]);
        $responseBody = json_decode((string)$response->getBody());

        $this->assertSame(self::$id, $responseBody->id);
        $this->assertSame('ABS', $responseBody->status);

        // Fail
        $args = [
            'status'    => 42 // Wrong format
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $args);

        $response     = $userController->updateStatus($fullRequest, new Response(), ['id' => self::$id]);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Body status is empty or not a string', $responseBody['errors']);

        $GLOBALS['login'] = 'bbain';
        $userInfo          = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id']     = $userInfo['id'];

        $args = [
            'status'    => 'ABS'
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $args);

        $response     = $userController->updateStatus($fullRequest, new Response(), ['id' => self::$id]);
        $this->assertSame(403, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Service forbidden', $responseBody['errors']);

        $GLOBALS['login'] = 'superadmin';
        $userInfo          = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id']     = $userInfo['id'];
    }

    public function testGetStatusByUserIdAfterUpdate()
    {
        $userController = new UserController();

        $request = $this->createRequest('GET');
        $response     = $userController->getStatusByUserId($request, new Response(), ['userId' => 'test-ckent']);
        $responseBody = json_decode((string)$response->getBody());

        $this->assertSame('ABS', $responseBody->status);
    }

    public function testRead()
    {
        $userController = new UserController();
        $parameterController = new ParameterController();

        //  UPDATE
        $args = [
            'description'           => 'User quota',
            'param_value_int'       => 0
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $args);
        $parameterController->update($fullRequest, new Response(), ['id' => 'user_quota']);

        // READ in case of deactivated user_quota
        $request = $this->createRequest('GET');
        $response       = $userController->get($request, new Response());
        $responseBody   = json_decode((string)$response->getBody());

        $this->assertNotNull($responseBody->users);
        $this->assertNull($responseBody->quota->userQuota);

        //  UPDATE
        $args = [
            'description'           => 'User quota',
            'param_value_int'       => 20
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $args);
        $parameterController->update($fullRequest, new Response(), ['id' => 'user_quota']);

        // READ in case of enabled user_quotat
        $request = $this->createRequest('GET');
        $response       = $userController->get($request, new Response());
        $responseBody   = json_decode((string)$response->getBody());

        $this->assertNotNull($responseBody->users);
        $this->assertNotNull($responseBody->quota);
        $this->assertSame(20, $responseBody->quota->userQuota);
        $this->assertNotNull($responseBody->quota->actives);
        $this->assertIsInt($responseBody->quota->inactives);

        $args = [
            'description'           => 'User quota',
            'param_value_int'       => 0
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $args);
        $parameterController->update($fullRequest, new Response(), ['id' => 'user_quota']);
    }

    public function testCreateEmailSignature()
    {
        $userController = new UserController();

        $args = [
            'title'    => 'Titre email signature TU 12345',
            'htmlBody' => '<p>Body Email Signature</p>'
        ];
        $fullRequest = $this->createRequestWithBody('POST', $args);
        $response = $userController->createCurrentUserEmailSignature($fullRequest, new Response());
        $responseBody = json_decode((string)$response->getBody());
        $this->assertNotEmpty($responseBody->emailSignatures);

        $titleEmailSignature = '';
        $htmlBodyEmailSignature = '';
        foreach ($responseBody->emailSignatures as $value) {
            if ($value->title == 'Titre email signature TU 12345') {
                self::$idEmailSignature = $value->id;
                $titleEmailSignature    = $value->title;
                $htmlBodyEmailSignature = $value->html_body;
            }
        }
        $this->assertNotEmpty(self::$idEmailSignature);
        $this->assertIsInt(self::$idEmailSignature);
        $this->assertSame('Titre email signature TU 12345', $titleEmailSignature);
        $this->assertSame('<p>Body Email Signature</p>', $htmlBodyEmailSignature);

        // ERROR
        $args = [
            'title'    => '',
            'htmlBody' => ''
        ];
        $fullRequest = $this->createRequestWithBody('POST', $args);

        $response     = $userController->createCurrentUserEmailSignature($fullRequest, new Response());
        $responseBody = json_decode((string)$response->getBody());

        $this->assertSame('Bad Request', $responseBody->errors);
    }

    public function testUpdateEmailSignature()
    {
        $userController = new UserController();

        $args = [
            'title'    => 'Titre email signature TU 12345 UPDATE',
            'htmlBody' => '<p>Body Email Signature UPDATE</p>'
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $args);

        $response     = $userController->updateCurrentUserEmailSignature($fullRequest, new Response(), ['id' => self::$idEmailSignature]);
        $responseBody = json_decode((string)$response->getBody());

        $this->assertNotEmpty($responseBody->emailSignature);
        $this->assertNotEmpty($responseBody->emailSignature->id);
        $this->assertIsInt($responseBody->emailSignature->id);
        $this->assertSame('Titre email signature TU 12345 UPDATE', $responseBody->emailSignature->title);
        $this->assertSame('<p>Body Email Signature UPDATE</p>', $responseBody->emailSignature->html_body);

        // ERROR
        $args = [
            'title'    => '',
            'htmlBody' => ''
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $args);

        $response     = $userController->updateCurrentUserEmailSignature($fullRequest, new Response(), ['id' => self::$idEmailSignature]);
        $responseBody = json_decode((string)$response->getBody());

        $this->assertSame('Bad Request', $responseBody->errors);
    }

    public function testGetCurrentUserEmailSignatures()
    {
        $userController = new UserController();

        $request = $this->createRequest('GET');

        //  Success
        $response     = $userController->getCurrentUserEmailSignatures($request, new Response());
        $this->assertSame(200, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);

        $this->assertIsArray($responseBody['emailSignatures']);
        $this->assertSame(self::$idEmailSignature, $responseBody['emailSignatures'][0]['id']);
        $this->assertSame('Titre email signature TU 12345 UPDATE', $responseBody['emailSignatures'][0]['label']);
    }

    public function testGetCurrentUserEmailSignatureById()
    {
        $userController = new UserController();

        $request = $this->createRequest('GET');

        //  Success
        $response     = $userController->getCurrentUserEmailSignatureById($request, new Response(), ['id' => self::$idEmailSignature]);
        $this->assertSame(200, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);

        $this->assertIsArray($responseBody['emailSignature']);
        $this->assertSame(self::$idEmailSignature, $responseBody['emailSignature']['id']);
        $this->assertSame('Titre email signature TU 12345 UPDATE', $responseBody['emailSignature']['label']);

        // Fail
        $response     = $userController->getCurrentUserEmailSignatureById($request, new Response(), ['id' => 'wrong format']);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Body param id is empty or not an integer', $responseBody['errors']);

        $response     = $userController->getCurrentUserEmailSignatureById($request, new Response(), ['id' => self::$idEmailSignature * 1000]);
        $this->assertSame(404, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Signature not found', $responseBody['errors']);
    }

    public function testDeleteEmailSignature()
    {
        $userController = new UserController();

        //  DELETE
        $request = $this->createRequest('DELETE');
        $response       = $userController->deleteCurrentUserEmailSignature($request, new Response(), ['id' => self::$idEmailSignature]);
        $responseBody   = json_decode((string)$response->getBody());

        $this->assertNotNull($responseBody->emailSignatures);

        $titleEmailSignature = '';
        $htmlBodyEmailSignature = '';
        foreach ($responseBody->emailSignatures as $value) {
            if ($value->title == 'Titre email signature TU 12345 UPDATE') {
                // Check If Signature Really Deleted
                $titleEmailSignature    = $value->title;
                $htmlBodyEmailSignature = $value->html_body;
            }
        }
        $this->assertSame('', $titleEmailSignature);
        $this->assertSame('', $htmlBodyEmailSignature);
    }

    public function testSuspend()
    {
        $userController = new UserController();

        $request = $this->createRequest('PUT');

        //  Success
        $response     = $userController->suspend($request, new Response(), ['id' => self::$id]);
        $this->assertSame(204, $response->getStatusCode());

        // set status OK
        $body = [
            'status' => 'OK'
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $body);

        $response     = $userController->updateStatus($fullRequest, new Response(), ['id' => self::$id]);
        $responseBody = json_decode((string)$response->getBody(), true);

        $this->assertSame('OK', $responseBody['user']['status']);

        // Fail
        $response     = $userController->suspend($request, new Response(), ['id' => self::$id * 1000]);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('User not found', $responseBody['errors']);

        $user = UserModel::getByLogin(['login' => 'bbain', 'select' => ['id']]);

        $response     = $userController->suspend($request, new Response(), ['id' => $user['id']]);
        $this->assertSame(403, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('User is still present in listInstances', $responseBody['errors']);

        $response     = $userController->suspend($request, new Response(), ['id' => 15]);
        $this->assertSame(403, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('User is still present in listTemplates', $responseBody['errors']);
    }

    public function testUpdateCurrentUserPreferences()
    {
        $userController = new UserController();

        //  Success
        $body = [
            'documentEdition' => 'onlyoffice',
            'homeGroups'      => [2, 1]
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $body);

        $response     = $userController->updateCurrentUserPreferences($fullRequest, new Response());
        $this->assertSame(204, $response->getStatusCode());

        // Fail
        $body = [
            'documentEdition' => 'GoogleDocs'
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $body);

        $response     = $userController->updateCurrentUserPreferences($fullRequest, new Response());
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Body preferences[documentEdition] is not allowed', $responseBody['errors']);
    }

    public function testAddSignature()
    {
        $userController = new UserController();

        //  Success
        $fileContent = file_get_contents('src/frontend/assets/noThumbnail.png');
        $encodedFile = base64_encode($fileContent);

        $body = [
            'name'   => 'signature1.png',
            'label'  => 'Signature1',
            'base64' => $encodedFile
        ];
        $fullRequest = $this->createRequestWithBody('POST', $body);

        $response     = $userController->addSignature($fullRequest, new Response(), ['id' => self::$id]);
        $this->assertSame(200, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);

        $this->assertIsArray($responseBody['signatures']);
        $this->assertNotEmpty($responseBody['signatures']);
        $this->assertSame(1, count($responseBody['signatures']));
        $this->assertIsInt($responseBody['signatures'][0]['id']);

        self::$signatureId = $responseBody['signatures'][0]['id'];

        // Fail
        $body = [

        ];
        $fullRequest = $this->createRequestWithBody('POST', $body);

        $response     = $userController->addSignature($fullRequest, new Response(), ['id' => self::$id * 1000]);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('User not found', $responseBody['errors']);


        $response     = $userController->addSignature($fullRequest, new Response(), ['id' => self::$id]);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Bad Request', $responseBody['errors']);

        $fileContent = file_get_contents('test/unitTests/samples/test.txt');
        $encodedFile = base64_encode($fileContent);

        $body = [
            'name'   => 'signature1.png',
            'label'  => 'Signature1',
            'base64' => $encodedFile
        ];
        $fullRequest = $this->createRequestWithBody('POST', $body);

        $response     = $userController->addSignature($fullRequest, new Response(), ['id' => self::$id]);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame(_WRONG_FILE_TYPE, $responseBody['errors']);

        $GLOBALS['login'] = 'bbain';
        $userInfo          = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id']     = $userInfo['id'];

        $response     = $userController->addSignature($fullRequest, new Response(), ['id' => self::$id]);
        $this->assertSame(403, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Service forbidden', $responseBody['errors']);

        $GLOBALS['login'] = 'superadmin';
        $userInfo          = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id']     = $userInfo['id'];
    }

    public function testGetImageContent()
    {
        $userController = new UserController();

        $request = $this->createRequest('GET');

        //  Success
        $response     = $userController->getImageContent($request, new Response(), ['id' => self::$id, 'signatureId' => self::$signatureId]);
        $this->assertSame(200, $response->getStatusCode());
        $headers = $response->getHeaders();

        $this->assertSame('image/png', $headers['Content-Type'][0]);

        // Fail
        $response     = $userController->getImageContent($request, new Response(), ['id' => 'wrong format', 'signatureId' => 'wrong format']);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Bad Request', $responseBody['errors']);

        $response     = $userController->getImageContent($request, new Response(), ['id' => self::$id * 1000, 'signatureId' => self::$signatureId]);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('User not found', $responseBody['errors']);


        $response     = $userController->getImageContent($request, new Response(), ['id' => self::$id, 'signatureId' => self::$signatureId * 1000]);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Signature does not exist', $responseBody['errors']);

        $GLOBALS['login'] = 'bbain';
        $userInfo          = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id']     = $userInfo['id'];

        $response     = $userController->getImageContent($request, new Response(), ['id' => self::$id, 'signatureId' => self::$signatureId]);
        $this->assertSame(403, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Service forbidden', $responseBody['errors']);

        $GLOBALS['login'] = 'superadmin';
        $userInfo          = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id']     = $userInfo['id'];
    }

    public function testUpdateSignature()
    {
        $userController = new UserController();

        //  Success
        $body = [
            'label'  => 'Signature1 - UPDATED'
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $body);

        $response     = $userController->updateSignature($fullRequest, new Response(), ['id' => self::$id, 'signatureId' => self::$signatureId]);
        $this->assertSame(200, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);

        $this->assertIsArray($responseBody['signature']);
        $this->assertNotEmpty($responseBody['signature']);

        // Fail
        $body = [
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $body);

        $response     = $userController->updateSignature($fullRequest, new Response(), ['id' => self::$id * 1000, 'signatureId' => self::$signatureId]);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('User not found', $responseBody['errors']);


        $response     = $userController->updateSignature($fullRequest, new Response(), ['id' => self::$id, 'signatureId' => self::$signatureId * 1000]);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Bad Request', $responseBody['errors']);

        $GLOBALS['login'] = 'bbain';
        $userInfo          = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id']     = $userInfo['id'];

        $response     = $userController->updateSignature($fullRequest, new Response(), ['id' => self::$id, 'signatureId' => self::$signatureId]);
        $this->assertSame(403, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Service forbidden', $responseBody['errors']);

        $GLOBALS['login'] = 'superadmin';
        $userInfo          = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id']     = $userInfo['id'];
    }

    public function testDeleteSignature()
    {
        $userController = new UserController();

        $request = $this->createRequest('DELETE');

        //  Success
        $response     = $userController->deleteSignature($request, new Response(), ['id' => self::$id, 'signatureId' => self::$signatureId]);
        $this->assertSame(200, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);

        $this->assertIsArray($responseBody['signatures']);
        $this->assertEmpty($responseBody['signatures']);

        // Fail
        $response     = $userController->deleteSignature($request, new Response(), ['id' => self::$id * 1000, 'signatureId' => self::$signatureId]);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('User not found', $responseBody['errors']);

        $GLOBALS['login'] = 'bbain';
        $userInfo          = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id']     = $userInfo['id'];

        $response     = $userController->deleteSignature($request, new Response(), ['id' => self::$id, 'signatureId' => self::$signatureId]);
        $this->assertSame(403, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Service forbidden', $responseBody['errors']);

        $GLOBALS['login'] = 'superadmin';
        $userInfo          = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id']     = $userInfo['id'];
    }

    public function testSendAccountActivationNotification()
    {
        $userController = new UserController();

        $request = $this->createRequest('PUT');

        //  Success
        $response     = $userController->sendAccountActivationNotification($request, new Response(), ['id' => self::$id, 'signatureId' => self::$signatureId]);
        $this->assertSame(204, $response->getStatusCode());

        // Fail
        $response     = $userController->sendAccountActivationNotification($request, new Response(), ['id' => self::$id * 1000, 'signatureId' => self::$signatureId]);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('User not found', $responseBody['errors']);
    }

    public function testForgotPassword()
    {
        $userController = new UserController();

        //  Success
        // User does not exist
        $body = [
            'login' => 'mscott'
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $body);

        $response     = $userController->forgotPassword($fullRequest, new Response());
        $this->assertSame(204, $response->getStatusCode());

        // User exist
        $body = [
            'login' => 'bbain'
        ];
        $fullRequest = $this->createRequestWithBody('POST', $body);

        $response     = $userController->forgotPassword($fullRequest, new Response());
        $this->assertSame(204, $response->getStatusCode());

        // Fail
        $body = [

        ];
        $fullRequest = $this->createRequestWithBody('POST', $body);

        $response     = $userController->forgotPassword($fullRequest, new Response());
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Body login is empty', $responseBody['errors']);

        $GLOBALS['login'] = 'superadmin';
        $userInfo          = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id']     = $userInfo['id'];
    }

    public function testPasswordInitialization()
    {
        $userController = new UserController();

        //  Success
        $token = AuthenticationController::getJWT();
        UserModel::update([
            'set'   => ['reset_token' => $token],
            'where' => ['id = ?'],
            'data'  => [$GLOBALS['id']]
        ]);

        $body = [
            'token'    => $token,
            'password' => 'superadmin'
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $body);

        $response     = $userController->passwordInitialization($fullRequest, new Response());
        $this->assertSame(204, $response->getStatusCode());

        // Fail
        $body = [

        ];
        $fullRequest = $this->createRequestWithBody('PUT', $body);

        $response     = $userController->passwordInitialization($fullRequest, new Response());
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Body token or body password is empty', $responseBody['errors']);

        $body = [
            'token'    => 'wrong token format',
            'password' => 'maarch'
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $body);

        $response     = $userController->passwordInitialization($fullRequest, new Response());
        $this->assertSame(403, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Invalid token', $responseBody['errors']);

        $token = [
            'exp'  => time() + 60 * AuthenticationController::MAX_DURATION_TOKEN,
            'user' => ['id' => self::$id * 1000]
        ];
        $token = JWT::encode($token, CoreConfigModel::getEncryptKey());

        $body = [
            'token'    => $token,
            'password' => 'maarch'
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $body);

        $response     = $userController->passwordInitialization($fullRequest, new Response());
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('User does not exist', $responseBody['errors']);

        $token = AuthenticationController::getJWT();
        $body = [
            'token'    => $token,
            'password' => 'maarch'
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $body);

        $response     = $userController->passwordInitialization($fullRequest, new Response());
        $this->assertSame(403, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Invalid token', $responseBody['errors']);

        $GLOBALS['login'] = 'superadmin';
        $userInfo          = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id']     = $userInfo['id'];
    }

    public function testUpdateBasketsDisplay()
    {
        $userController = new UserController();

        //  Success
        $user = UserModel::getByLogin(['login' => 'bbain', 'select' => ['id']]);
        $body = [
            'baskets' => [
                [
                    'basketId'      => 'MyBasket',
                    'groupSerialId' => 2,
                    'allowed'       => false
                ]
            ]
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $body);

        $response     = $userController->updateBasketsDisplay($fullRequest, new Response(), ['id' => $user['id']]);
        $this->assertSame(200, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('success', $responseBody['success']);

        $body = [
            'baskets' => [
                [
                    'basketId'      => 'MyBasket',
                    'groupSerialId' => 2,
                    'allowed'       => true
                ]
            ]
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $body);

        $response     = $userController->updateBasketsDisplay($fullRequest, new Response(), ['id' => $user['id']]);
        $this->assertSame(200, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('success', $responseBody['success']);

        $response     = $userController->updateBasketsDisplay($fullRequest, new Response(), ['id' => $user['id']]);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Preference already exists', $responseBody['errors']);

        // Fail
        $body = [

        ];
        $fullRequest = $this->createRequestWithBody('PUT', $body);

        $response     = $userController->updateBasketsDisplay($fullRequest, new Response(), ['id' => self::$id * 1000]);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('User not found', $responseBody['errors']);

        $response     = $userController->updateBasketsDisplay($fullRequest, new Response(), ['id' => self::$id]);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Bad Request', $responseBody['errors']);

        $body = [
            'baskets' => [
                [
                    'basketId'      => 'MyBasket',
                    'groupSerialId' => 1,
                ]
            ]
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $body);

        $response     = $userController->updateBasketsDisplay($fullRequest, new Response(), ['id' => self::$id]);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Element is missing', $responseBody['errors']);

        $body = [
            'baskets' => [
                [
                    'basketId'      => 'MyBasket',
                    'groupSerialId' => 100000,
                    'allowed'       => true
                ]
            ]
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $body);

        $response     = $userController->updateBasketsDisplay($fullRequest, new Response(), ['id' => self::$id]);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Group or basket does not exist', $responseBody['errors']);

        $body = [
            'baskets' => [
                [
                    'basketId'      => 'MyBasket',
                    'groupSerialId' => 1,
                    'allowed'       => true
                ]
            ]
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $body);

        $response     = $userController->updateBasketsDisplay($fullRequest, new Response(), ['id' => self::$id]);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Group is not linked to this user', $responseBody['errors']);

        $body = [
            'baskets' => [
                [
                    'basketId'      => 'QualificationBasket',
                    'groupSerialId' => 2,
                    'allowed'       => true
                ]
            ]
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $body);

        $response     = $userController->updateBasketsDisplay($fullRequest, new Response(), ['id' => $user['id']]);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Group is not linked to this basket', $responseBody['errors']);
    }

    public function testGetTemplates()
    {
        $userController = new UserController();

        $request = $this->createRequest('GET');

        //  Success
        $query = [
            'target' => 'sendmail',
            'type'   => 'HTML'
        ];
        $fullRequest = $request->withQueryParams($query);

        $response     = $userController->getTemplates($fullRequest, new Response());
        $this->assertSame(200, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertIsArray($responseBody['templates']);
        $this->assertNotEmpty($responseBody['templates']);

        foreach ($responseBody['templates'] as $template) {
            $this->assertIsInt($template['id']);
            $this->assertIsString($template['label']);
            $this->assertEmpty($template['extension']);
            $this->assertEmpty($template['exists']);
            $this->assertIsString($template['target']);
            $this->assertIsString($template['attachmentType']);
        }
    }

    public function testUpdateCurrentUserBasketPreferences()
    {
        $userController = new UserController();

        //  Success
        $body = [
            'color' => 'red'
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $body);

        $response     = $userController->updateCurrentUserBasketPreferences($fullRequest, new Response(), ['basketId' => 'MyBasket', 'groupSerialId' => 1]);
        $this->assertSame(200, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertIsArray($responseBody['userBaskets']);
        $this->assertEmpty($responseBody['userBaskets']);

        $body = [
            'color' => ''
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $body);

        $response     = $userController->updateCurrentUserBasketPreferences($fullRequest, new Response(), ['basketId' => 'MyBasket', 'groupSerialId' => 1]);
        $this->assertSame(200, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertIsArray($responseBody['userBaskets']);
        $this->assertEmpty($responseBody['userBaskets']);
    }

    public function testGetDetailledById()
    {
        $userController = new UserController();

        $request = $this->createRequest('GET');

        $GLOBALS['login'] = 'bbain';
        $userInfo          = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id']     = $userInfo['id'];

        $response       = $userController->getDetailledById($request, new Response(), ['id' => self::$id]);
        $this->assertSame(403, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Service forbidden', $responseBody['errors']);

        $GLOBALS['login'] = 'bblier';
        $userInfo          = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id']     = $userInfo['id'];

        $response       = $userController->getDetailledById($request, new Response(), ['id' => self::$id]);
        $this->assertSame(200, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);

        $this->assertSame(self::$id, $responseBody['id']);
        $this->assertSame('test-ckent', $responseBody['user_id']);
        $this->assertSame('TEST-CLARK2', $responseBody['firstname']);
        $this->assertSame('TEST-KENT2', $responseBody['lastname']);
        $this->assertSame('OK', $responseBody['status']);
        $this->assertSame(null, $responseBody['phone']);
        $this->assertSame('ck@dailyP.com', $responseBody['mail']);
        $this->assertSame('CK', $responseBody['initials']);

        $GLOBALS['login'] = 'superadmin';
        $userInfo          = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id']     = $userInfo['id'];
    }

    public function testDelete()
    {
        $userController = new UserController();

        //  DELETE
        $request = $this->createRequest('DELETE');
        $response       = $userController->delete($request, new Response(), ['id' => self::$id]);
        $this->assertSame(204, $response->getStatusCode());

        //  READ
        $request = $this->createRequest('GET');
        $response       = $userController->getDetailledById($request, new Response(), ['id' => self::$id]);
        $responseBody   = json_decode((string)$response->getBody());

        $this->assertSame(self::$id, $responseBody->id);
        $this->assertSame('test-ckent', $responseBody->user_id);
        $this->assertSame('TEST-CLARK2', $responseBody->firstname);
        $this->assertSame('TEST-KENT2', $responseBody->lastname);
        $this->assertSame('DEL', $responseBody->status);
        $this->assertSame('0122334455', $responseBody->phone);
        $this->assertSame('ck@dailyP.com', $responseBody->mail);
        $this->assertSame('CK', $responseBody->initials);

        // Fail
        $request = $this->createRequest('DELETE');
        $response       = $userController->delete($request, new Response(), ['id' => $GLOBALS['id']]);
        $this->assertSame(403, $response->getStatusCode());
        $responseBody   = json_decode((string)$response->getBody(), true);
        $this->assertSame('Can not delete yourself', $responseBody['errors']);

        //  REAL DELETE
        DatabaseModel::delete([
            'table' => 'users',
            'where' => ['id = ?'],
            'data'  => [self::$id]
        ]);
    }

    public function testPasswordManagement()
    {
        $userController = new UserController();

        $user = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);

        //  UPDATE PASSWORD
        $args = [
            'currentPassword'   => 'superadmin',
            'newPassword'       => 'hcraam',
            'reNewPassword'     => 'hcraam'
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $args);

        $response     = $userController->updatePassword($fullRequest, new Response(), ['id' => $user['id']]);
        $responseBody = json_decode((string)$response->getBody());

        $this->assertSame('success', $responseBody->success);

        $checkPassword = AuthenticationModel::authentication(['login' => $GLOBALS['login'], 'password' => 'hcraam']);

        $this->assertSame(true, $checkPassword);

        // Fail
        $args = [
            'currentPassword'   => 'superadmin',
            'newPassword'       => 42, // wrong format
            'reNewPassword'     => 'hcraam'
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $args);

        $response     = $userController->updatePassword($fullRequest, new Response(), ['id' => $user['id']]);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Bad Request', $responseBody['errors']);

        $GLOBALS['login'] = 'bbain';
        $userInfo          = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id']     = $userInfo['id'];

        $response     = $userController->updatePassword($fullRequest, new Response(), ['id' => $user['id']]);
        $this->assertSame(403, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Service forbidden', $responseBody['errors']);

        $GLOBALS['login'] = 'bblier';
        $userInfo          = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id']     = $userInfo['id'];

        $user = UserModel::getByLogin(['login' => 'ggrand', 'select' => ['id']]);

        $args = [
            'currentPassword'   => 'superadmin',
            'newPassword'       => 'hcraam',
            'reNewPassword'     => 'hcraam2'
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $args);

        $response     = $userController->updatePassword($fullRequest, new Response(), ['id' => $user['id']]);
        $this->assertSame(403, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Not allowed', $responseBody['errors']);

        // Passwords not matching
        $args = [
            'currentPassword'   => 'superadmin',
            'newPassword'       => 'hcraam',
            'reNewPassword'     => 'hcraam2'
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $args);

        $response     = $userController->updatePassword($fullRequest, new Response(), ['id' => $GLOBALS['id']]);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Bad Request', $responseBody['errors']);

        // wrong current password
        $args = [
            'currentPassword'   => 'superadmin',
            'newPassword'       => 'hcraam',
            'reNewPassword'     => 'hcraam'
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $args);

        $response     = $userController->updatePassword($fullRequest, new Response(), ['id' => $GLOBALS['id']]);
        $this->assertSame(401, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame(_WRONG_PSW, $responseBody['errors']);

        $GLOBALS['login'] = 'superadmin';
        $userInfo          = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id']     = $userInfo['id'];

        //  UPDATE RESET PASSWORD
        $args = [
            'currentPassword'   => 'hcraam',
            'newPassword'       => 'superadmin',
            'reNewPassword'     => 'superadmin'
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $args);

        $response     = $userController->updatePassword($fullRequest, new Response(), ['id' => $GLOBALS['id']]);
        $responseBody = json_decode((string)$response->getBody());

        $this->assertSame('success', $responseBody->success);

        $checkPassword = AuthenticationModel::authentication(['login' => $GLOBALS['login'], 'password' => 'superadmin']);

        $this->assertSame(true, $checkPassword);
    }

    public function testUpdateProfile()
    {
        $userController = new UserController();

        //  UPDATE
        $args = [
            'firstname'     => 'Wonder',
            'lastname'      => 'User',
            'mail'          => 'dev@maarch.org',
            'initials'      => 'SU'
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $args);

        $response     = $userController->updateProfile($fullRequest, new Response());
        $this->assertSame(204, $response->getStatusCode());


        //  READ
        $request = $this->createRequest('GET');
        $response     = $userController->getProfile($request, new Response());
        $responseBody = json_decode((string)$response->getBody(), true);

        $this->assertSame('superadmin', $responseBody['user_id']);
        $this->assertSame('Wonder', $responseBody['firstname']);
        $this->assertSame('User', $responseBody['lastname']);
        $this->assertSame('dev@maarch.org', $responseBody['mail']);
        $this->assertSame('SU', $responseBody['initials']);
        $this->assertSame('onlyoffice', $responseBody['preferences']['documentEdition']);


        //  UPDATE
        $args = [
            'firstname'     => 'Super',
            'lastname'      => 'ADMIN',
            'mail'          => 'dev@maarch.org',
            'initials'      => 'SU'
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $args);

        $response     = $userController->updateProfile($fullRequest, new Response());
        $this->assertSame(204, $response->getStatusCode());


        //  READ
        $request = $this->createRequest('GET');
        $response     = $userController->getProfile($request, new Response());
        $responseBody = json_decode((string)$response->getBody());

        $this->assertSame('superadmin', $responseBody->user_id);
        $this->assertSame('Super', $responseBody->firstname);
        $this->assertSame('ADMIN', $responseBody->lastname);
        $this->assertSame('dev@maarch.org', $responseBody->mail);
        $this->assertSame('SU', $responseBody->initials);

        //  ERRORS
        $args = [
            'firstname'     => 'Super',
            'lastname'      => 'ADMIN',
            'initials'      => 'SU'
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $args);

        $response     = $userController->updateProfile($fullRequest, new Response());
        $this->assertSame(400, $response->getStatusCode());

        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Body mail is empty or not a valid email', $responseBody['errors']);

        $args = [
            'firstname' => '',
            'lastname'  => 'ADMIN',
            'initials'  => 'SU'
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $args);

        $response     = $userController->updateProfile($fullRequest, new Response());
        $this->assertSame(400, $response->getStatusCode());

        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Body firstname is empty or not a string', $responseBody['errors']);

        $args = [
            'firstname' => 'Super',
            'lastname'  => '',
            'initials'  => 'SU'
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $args);

        $response     = $userController->updateProfile($fullRequest, new Response());
        $this->assertSame(400, $response->getStatusCode());

        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Body lastname is empty or not a string', $responseBody['errors']);

        $args = [
            'firstname' => 'Super',
            'lastname'  => 'ADMIN',
            'initials'  => 'SU',
            'mail'      => 'dev@maarch.org',
            'phone'     => 'wrong format'
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $args);

        $response     = $userController->updateProfile($fullRequest, new Response());
        $this->assertSame(400, $response->getStatusCode());

        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Body phone is not a valid phone number', $responseBody['errors']);
    }

    public function testSetRedirectedBasket()
    {
        $userController = new UserController();

        $body = [
            [
                'actual_user_id'    =>  21,
                'basket_id'         =>  'MyBasket',
                'group_id'          =>  2
            ]
        ];

        $user_id = UserModel::getByLogin(['login' => 'bbain', 'select' => ['id']]);
        $fullRequest = $this->createRequestWithBody('POST', $body);
        $response     = $userController->setRedirectedBaskets($fullRequest, new Response(), ['id' => $user_id['id']]);
        $responseBody = json_decode((string)$response->getBody());
        
        $this->assertNotNull($responseBody->baskets);
        $this->assertNotNull($responseBody->redirectedBaskets);
        foreach ($responseBody->redirectedBaskets as $redirectedBasket) {
            if ($redirectedBasket->actual_user_id == 21 && $redirectedBasket->basket_id == 'MyBasket' && $redirectedBasket->group_id == 2) {
                self::$redirectId = $redirectedBasket->id;
            }
        }
        $this->assertNotNull(self::$redirectId);
        $this->assertIsInt(self::$redirectId);

        $body = [
            [
                'newUser'       =>  null,
                'basketId'      =>  'MyBasket',
                'basketOwner'   =>  'bbain',
                'virtual'       =>  'Y'
            ],
            [
                'newUser'       =>  'bblier',
                'basketId'      =>  'EenvBasket',
                'basketOwner'   =>  'bbain',
                'virtual'       =>  'Y'
            ]
        ];
        $fullRequest = $this->createRequestWithBody('POST', $body);
        $response     = $userController->setRedirectedBaskets($fullRequest, new Response(), ['id' => $user_id['id']]);
        $responseBody = json_decode((string)$response->getBody());

        $this->assertSame('Some data are empty', $responseBody->errors);

        $body = [
            [
                'actual_user_id'    =>  -1,
                'basket_id'         =>  'MyBasket',
                'group_id'          =>  2
            ]
        ];
        $fullRequest = $this->createRequestWithBody('POST', $body);
        $response     = $userController->setRedirectedBaskets($fullRequest, new Response(), ['id' => $user_id['id']]);
        $responseBody = json_decode((string)$response->getBody());

        $this->assertSame('User not found', $responseBody->errors);

        $body = [
            [
                'actual_user_id'    =>  -1,
                'basket_id'         =>  'MyBasket',
                'group_id'          =>  2
            ]
        ];
        $fullRequest = $this->createRequestWithBody('POST', $body);
        $response     = $userController->setRedirectedBaskets($fullRequest, new Response(), ['id' => $user_id['id'] * 1000]);
        $responseBody = json_decode((string)$response->getBody());

        $this->assertSame('User not found', $responseBody->errors);
    }

    public function testDeleteRedirectedBaskets()
    {
        $userController = new UserController();

        $request = $this->createRequest('DELETE');

        $user_id = UserModel::getByLogin(['login' => 'bbain', 'select' => ['id']]);
       
        //DELETE MANY WITH ONE ON ERROR
        $body = [
            'redirectedBasketIds' => [ self::$redirectId, -1 ]
        ];

        $fullRequest = $request->withQueryParams($body);

        $response     = $userController->deleteRedirectedBasket($fullRequest, new Response(), ['id' => $user_id['id']]);
        $responseBody = json_decode((string)$response->getBody());

        $this->assertSame('Redirected basket out of perimeter', $responseBody->errors);

        //DELETE OK
        $GLOBALS['login'] = 'bbain';
        $userInfo          = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id']     = $userInfo['id'];

        $body = [
            'redirectedBasketIds' => [ self::$redirectId ]
        ];

        $fullRequest = $request->withQueryParams($body);

        $response  = $userController->deleteRedirectedBasket($fullRequest, new Response(), ['id' => $user_id['id']]);
        $responseBody = json_decode((string)$response->getBody());

        $this->assertNotNull($responseBody->baskets);

        $GLOBALS['login'] = 'superadmin';
        $userInfo          = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id']     = $userInfo['id'];

        //DELETE NOT OK
        $body = [
            'redirectedBasketIds' => [ -1 ]
        ];

        $fullRequest = $request->withQueryParams($body);

        $response     = $userController->deleteRedirectedBasket($fullRequest, new Response(), ['id' => $user_id['id']]);
        $responseBody = json_decode((string)$response->getBody());

        $this->assertSame('Redirected basket out of perimeter', $responseBody->errors);

        $body = [
            'redirectedBasketIds' => [ -1 ]
        ];

        $fullRequest = $request->withQueryParams($body);

        $response     = $userController->deleteRedirectedBasket($fullRequest, new Response(), ['id' => $user_id['id'] * 1000]);
        $responseBody = json_decode((string)$response->getBody());

        $this->assertSame('User not found', $responseBody->errors);

        $body = [
            'redirectedBasketIds' => 'wrong format'
        ];

        $fullRequest = $request->withQueryParams($body);

        $response     = $userController->deleteRedirectedBasket($fullRequest, new Response(), ['id' => $user_id['id']]);
        $responseBody = json_decode((string)$response->getBody());

        $this->assertSame('RedirectedBasketIds is empty or not an array', $responseBody->errors);
    }
}
