<?php

/**
* Copyright Maarch since 2008 under licence GPLv3.
* See LICENCE.txt file at the root folder for more details.
* This file is part of Maarch software.
*
*/

namespace MaarchCourrier\Tests\app\entity;

use Entity\controllers\EntityController;
use Entity\models\EntityModel;
use Entity\models\ListTemplateItemModel;
use Entity\models\ListTemplateModel;
use MaarchCourrier\Tests\CourrierTestCase;
use SrcCore\http\Response;
use User\models\UserEntityModel;
use User\models\UserModel;

class EntityControllerTest extends CourrierTestCase
{
    private static $id = null;

    public function testCreate()
    {
        $GLOBALS['login'] = 'bblier';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        $entityController = new EntityController();

        //  CREATE
        $args = [
            'entity_id'         => 'TEST-ENTITY123',
            'entity_label'      => 'TEST-ENTITY123-LABEL',
            'short_label'       => 'TEST-ENTITY123-SHORTLABEL',
            'entity_type'       => 'Service',
            'email'             => 'paris@isMagic.fr',
            'addressNumber'    => '1',
            'addressStreet'    => 'rue du parc des princes',
            'addressPostcode'  => '75016',
            'addressTown'      => 'PARIS',
        ];
        $fullRequest = $this->createRequestWithBody('POST', $args);

        $response     = $entityController->create($fullRequest, new Response());
        $this->assertSame(200, $response->getStatusCode());

        $responseBody = json_decode((string)$response->getBody());

        $this->assertIsArray($responseBody->entities);

        $entityInfo = EntityModel::getByEntityId(['entityId' => 'TEST-ENTITY123', 'select' => ['id']]);
        self::$id = $entityInfo['id'];

        //  READ
        $request = $this->createRequest('GET');
        $response       = $entityController->getById($request, new Response(), ['id' => self::$id]);
        $responseBody   = json_decode((string)$response->getBody());

        $this->assertSame('TEST-ENTITY123', $responseBody->entity_id);
        $this->assertSame('TEST-ENTITY123-LABEL', $responseBody->entity_label);
        $this->assertSame('TEST-ENTITY123-SHORTLABEL', $responseBody->short_label);
        $this->assertSame('Service', $responseBody->entity_type);
        $this->assertSame('Y', $responseBody->enabled);
        $this->assertSame(null, $responseBody->parent_entity_id);

        // ERRORS

        $response     = $entityController->create($fullRequest, new Response());
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);

        $this->assertSame(_ENTITY_ID_ALREADY_EXISTS, $responseBody['errors']);

        unset($args['entity_label']);
        $fullRequest = $this->createRequestWithBody('POST', $args);
        $response     = $entityController->create($fullRequest, new Response());
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);

        $this->assertSame('Body entity_label is empty or not a string', $responseBody['errors']);

        unset($args['entity_id']);
        $fullRequest = $this->createRequestWithBody('POST', $args);
        $response     = $entityController->create($fullRequest, new Response());
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);

        $this->assertSame('Body entity_id is empty, not a string or not valid', $responseBody['errors']);

        $GLOBALS['login'] = 'ddur';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        $response = $entityController->create($fullRequest, new Response());
        $this->assertSame(403, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody());
        $this->assertSame('Service forbidden', $responseBody->errors);

        $GLOBALS['login'] = 'superadmin';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];
    }

    public function testGetUsersById()
    {
        $entityController = new EntityController();

        $request = $this->createRequest('GET');
        $response     = $entityController->getUsersById($request, new Response(), ['id' => self::$id]);
        $this->assertSame(200, $response->getStatusCode());

        $responseBody = json_decode((string)$response->getBody(), true);

        $this->assertIsArray($responseBody['users']);
        $this->assertNotEmpty($responseBody['users']);
        $this->assertSame('bblier', $responseBody['users'][0]['user_id']);

        $request = $this->createRequest('GET');
        $response     = $entityController->getUsersById($request, new Response(), ['id' => 99999999]);
        $this->assertSame(400, $response->getStatusCode());

        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Entity not found', $responseBody['errors']);
    }

    public function testUpdate()
    {
        $GLOBALS['login'] = 'bblier';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        $entityController = new EntityController();

        //  UPDATE
        $args = [
            'entity_label'      => 'TEST-ENTITY123-LABEL',
            'short_label'       => 'TEST-ENTITY123-SHORTLABEL-UP',
            'entity_type'       => 'Direction',
            'email'             => 'paris@isMagic2.fr',
            'addressNumber'    => '2',
            'addressStreet'    => 'rue du parc des princes',
            'addressPostcode'  => '75016',
            'addressTown'      => 'PARIS',
            'toto'              => 'toto',
            'parent_entity_id' => 'COU'
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $args);

        $response     = $entityController->update($fullRequest, new Response(), ['id' => 'TEST-ENTITY123']);
        $this->assertSame(200, $response->getStatusCode());

        $responseBody = json_decode((string)$response->getBody());

        $this->assertIsArray($responseBody->entities);

        //  READ
        $request = $this->createRequest('GET');
        $response       = $entityController->getById($request, new Response(), ['id' => self::$id]);
        $responseBody   = json_decode((string)$response->getBody());

        $this->assertSame('TEST-ENTITY123', $responseBody->entity_id);
        $this->assertSame('TEST-ENTITY123-LABEL', $responseBody->entity_label);
        $this->assertSame('TEST-ENTITY123-SHORTLABEL-UP', $responseBody->short_label);
        $this->assertSame('Direction', $responseBody->entity_type);
        $this->assertSame('Y', $responseBody->enabled);
        $this->assertSame('COU', $responseBody->parent_entity_id);

        // test setting entity as user's primary entity when user does not have any
        UserEntityModel::deleteUserEntity(['id' => $GLOBALS['id'], 'entityId' => 'TEST-ENTITY123']);
        UserEntityModel::update([
            'set'   => ['primary_entity' => 'N'],
            'where' => ['user_id = ?'],
            'data'  => [$GLOBALS['id']]
        ]);

        $args = [
            'entity_label'      => 'TEST-ENTITY123-LABEL',
            'short_label'       => 'TEST-ENTITY123-SHORTLABEL-UP',
            'entity_type'       => 'Direction',
            'email'             => 'paris@isMagic2.fr',
            'toto'              => 'toto',
            'parent_entity_id'  => null
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $args);

        // Errors
        $response     = $entityController->update($fullRequest, new Response(), ['id' => '12345678923456789']);
        $this->assertSame(400, $response->getStatusCode());

        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Entity not found', $responseBody['errors']);

        unset($args['entity_label']);
        $fullRequest = $this->createRequestWithBody('PUT', $args);
        $response     = $entityController->update($fullRequest, new Response(), ['id' => 'TEST-ENTITY123']);
        $this->assertSame(400, $response->getStatusCode());

        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Bad Request', $responseBody['errors']);

        $fullRequest = $this->createRequestWithBody('PUT', $args);
        $response     = $entityController->update($fullRequest, new Response(), ['id' => 'CAB']);
        $this->assertSame(403, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Entity out of perimeter', $responseBody['errors']);

        UserEntityModel::deleteUserEntity(['id' => $GLOBALS['id'], 'entityId' => 'TEST-ENTITY123']);

        UserEntityModel::update([
            'set'   => ['primary_entity' => 'Y'],
            'where' => ['user_id = ?', 'entity_id = ?'],
            'data'  => [$GLOBALS['id'], 'COU']
        ]);

        $GLOBALS['login'] = 'superadmin';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        $args = [
            'entity_label'     => 'TEST-ENTITY123-LABEL',
            'short_label'      => 'TEST-ENTITY123-SHORTLABEL-UP',
            'entity_type'      => 'Direction',
            'email'            => 'paris@isMagic2.fr',
            'toto'             => 'toto',
            'parent_entity_id' => 'SP'
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $args);

        $response     = $entityController->update($fullRequest, new Response(), ['id' => 'PJS']);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame(_CAN_NOT_MOVE_IN_CHILD_ENTITY, $responseBody['errors']);

        $GLOBALS['login'] = 'ddur';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        $response = $entityController->update($fullRequest, new Response(), ['id' => 'TEST-ENTITY123']);
        $this->assertSame(403, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody());
        $this->assertSame('Service forbidden', $responseBody->errors);

        $GLOBALS['login'] = 'superadmin';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];
    }

    public function testUpdateStatus()
    {
        $entityController = new EntityController();

        //  UPDATE
        $args = [
            'method'            => 'disable'
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $args);

        $response     = $entityController->updateStatus($fullRequest, new Response(), ['id' => 'TEST-ENTITY123']);
        $this->assertSame(200, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody());

        $this->assertSame('success', $responseBody->success);

        //  READ
        $request = $this->createRequest('GET');
        $response       = $entityController->getById($request, new Response(), ['id' => self::$id]);
        $this->assertSame(200, $response->getStatusCode());
        $responseBody   = json_decode((string)$response->getBody());

        $this->assertSame('TEST-ENTITY123', $responseBody->entity_id);
        $this->assertSame('N', $responseBody->enabled);

        //  UPDATE
        $args = [
            'method'            => 'enable'
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $args);

        $response     = $entityController->updateStatus($fullRequest, new Response(), ['id' => 'TEST-ENTITY123']);
        $responseBody = json_decode((string)$response->getBody());

        $this->assertSame('success', $responseBody->success);

        // Errors
        $fullRequest = $this->createRequestWithBody('PUT', $args);

        $response     = $entityController->updateStatus($fullRequest, new Response(), ['id' => 'TEST-9999999']);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody());

        $this->assertSame('Entity not found', $responseBody->errors);


        $fullRequest = $this->createRequestWithBody('PUT', []);

        $response     = $entityController->updateStatus($fullRequest, new Response(), ['id' => 'TEST-ENTITY123']);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Bad Request', $responseBody['errors']);

        $GLOBALS['login'] = 'bblier';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        $response     = $entityController->updateStatus($fullRequest, new Response(), ['id' => 'PJS']);
        $this->assertSame(403, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Entity out of perimeter', $responseBody['errors']);

        $GLOBALS['login'] = 'ddur';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        $response = $entityController->updateStatus($fullRequest, new Response(), ['id' => 'TEST-ENTITY123']);
        $this->assertSame(403, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody());
        $this->assertSame('Service forbidden', $responseBody->errors);

        $GLOBALS['login'] = 'superadmin';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];
    }

    public function testGet()
    {
        $entityController = new EntityController();

        //  READ
        $request = $this->createRequest('GET');
        $response       = $entityController->get($request, new Response());
        $this->assertSame(200, $response->getStatusCode());
        $responseBody   = json_decode((string)$response->getBody());

        $this->assertIsArray($responseBody->entities);
        $this->assertNotNull($responseBody->entities);
    }

    public function testGetDetailledById()
    {
        $entityController = new EntityController();

        $visaTemplateId = ListTemplateModel::create([
            'title'       => 'TEMPLATE TEST',
            'description' => 'TEMPLATE TEST will be deleted when entity is deleted',
            'type'        => 'visaCircuit',
            'entity_id'   => self::$id,
            'owner'       => $GLOBALS['id']
        ]);
        ListTemplateItemModel::create([
            'list_template_id' => $visaTemplateId,
            'item_id'          => $GLOBALS['id'],
            'item_type'        => 'user',
            'item_mode'        => 'sign',
            'sequence'         => 0,
        ]);
        $templateId = ListTemplateModel::create([
            'title'       => 'TEMPLATE TEST',
            'description' => 'TEMPLATE TEST will be deleted when entity is deleted',
            'type'        => 'diffusionList',
            'entity_id'   => self::$id,
            'owner'       => $GLOBALS['id']
        ]);
        ListTemplateItemModel::create([
            'list_template_id' => $templateId,
            'item_id'          => $GLOBALS['id'],
            'item_type'        => 'user',
            'item_mode'        => 'dest',
            'sequence'         => 0,
        ]);
        ListTemplateItemModel::create([
            'list_template_id' => $templateId,
            'item_id'          => 13,
            'item_type'        => 'entity',
            'item_mode'        => 'cc',
            'sequence'         => 1,
        ]);

        //  READ
        $request = $this->createRequest('GET');
        $response       = $entityController->getDetailledById($request, new Response(), ['id' => 'TEST-ENTITY123']);
        $this->assertSame(200, $response->getStatusCode());
        $responseBody   = json_decode((string)$response->getBody(), true);

        $this->assertSame('TEST-ENTITY123', $responseBody['entity']['entity_id']);
        $this->assertSame('TEST-ENTITY123-LABEL', $responseBody['entity']['entity_label']);
        $this->assertSame('TEST-ENTITY123-SHORTLABEL-UP', $responseBody['entity']['short_label']);
        $this->assertSame('Direction', $responseBody['entity']['entity_type']);
        $this->assertSame('Y', $responseBody['entity']['enabled']);
        $this->assertSame('paris@isMagic2.fr', $responseBody['entity']['email']);
        $this->assertSame('2', $responseBody['entity']['addressNumber']);
        $this->assertSame('rue du parc des princes', $responseBody['entity']['addressStreet']);
        $this->assertSame('75016', $responseBody['entity']['addressPostcode']);
        $this->assertSame('PARIS', $responseBody['entity']['addressTown']);
        $this->assertSame('COU', $responseBody['entity']['parent_entity_id']);
        $this->assertIsArray($responseBody['entity']['listTemplate']);
        $this->assertNotEmpty($responseBody['entity']['listTemplate']);

        $this->assertSame($templateId, $responseBody['entity']['listTemplate']['id']);
        $this->assertSame('TEMPLATE TEST', $responseBody['entity']['listTemplate']['title']);
        $this->assertSame('TEMPLATE TEST will be deleted when entity is deleted', $responseBody['entity']['listTemplate']['description']);
        $this->assertSame('diffusionList', $responseBody['entity']['listTemplate']['type']);
        $this->assertIsArray($responseBody['entity']['listTemplate']['items']);

        $this->assertIsArray($responseBody['entity']['listTemplate']['items']['dest'][0]);
        $this->assertSame($GLOBALS['id'], $responseBody['entity']['listTemplate']['items']['dest'][0]['id']);
        $this->assertSame('user', $responseBody['entity']['listTemplate']['items']['dest'][0]['type']);
        $this->assertSame(0, $responseBody['entity']['listTemplate']['items']['dest'][0]['sequence']);
        $this->assertIsString($responseBody['entity']['listTemplate']['items']['dest'][0]['labelToDisplay']);
        $this->assertNotEmpty($responseBody['entity']['listTemplate']['items']['dest'][0]['descriptionToDisplay']);

        $this->assertIsArray($responseBody['entity']['listTemplate']['items']['cc'][0]);
        $this->assertSame(13, $responseBody['entity']['listTemplate']['items']['cc'][0]['id']);
        $this->assertSame('entity', $responseBody['entity']['listTemplate']['items']['cc'][0]['type']);
        $this->assertSame(1, $responseBody['entity']['listTemplate']['items']['cc'][0]['sequence']);
        $this->assertIsString($responseBody['entity']['listTemplate']['items']['cc'][0]['labelToDisplay']);
        $this->assertEmpty($responseBody['entity']['listTemplate']['items']['cc'][0]['descriptionToDisplay']);

        $this->assertIsArray($responseBody['entity']['visaCircuit']);
        $this->assertNotEmpty($responseBody['entity']['visaCircuit']);

        $this->assertSame($visaTemplateId, $responseBody['entity']['visaCircuit']['id']);
        $this->assertSame('TEMPLATE TEST', $responseBody['entity']['visaCircuit']['title']);
        $this->assertSame('TEMPLATE TEST will be deleted when entity is deleted', $responseBody['entity']['visaCircuit']['description']);
        $this->assertSame('visaCircuit', $responseBody['entity']['visaCircuit']['type']);
        $this->assertIsArray($responseBody['entity']['visaCircuit']['items']);

        $this->assertIsArray($responseBody['entity']['visaCircuit']['items'][0]);
        $this->assertSame($GLOBALS['id'], $responseBody['entity']['visaCircuit']['items'][0]['id']);
        $this->assertSame('user', $responseBody['entity']['visaCircuit']['items'][0]['type']);
        $this->assertSame('sign', $responseBody['entity']['visaCircuit']['items'][0]['mode']);
        $this->assertSame(0, $responseBody['entity']['visaCircuit']['items'][0]['sequence']);
        $this->assertIsString($responseBody['entity']['visaCircuit']['items'][0]['idToDisplay']);
        $this->assertNotEmpty($responseBody['entity']['visaCircuit']['items'][0]['descriptionToDisplay']);

        $this->assertSame(false, $responseBody['entity']['hasChildren']);
        $this->assertSame(0, $responseBody['entity']['documents']);
        $this->assertIsArray($responseBody['entity']['users']);
        $this->assertIsArray($responseBody['entity']['templates']);
        $this->assertSame(0, $responseBody['entity']['instances']);
        $this->assertSame(0, $responseBody['entity']['redirects']);

        // Errors
        $response     = $entityController->getDetailledById($request, new Response(), ['id' => 'SECRET-SERVICE']);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Entity not found', $responseBody['errors']);


        $GLOBALS['login'] = 'bblier';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        $response     = $entityController->getDetailledById($request, new Response(), ['id' => 'PJS']);
        $this->assertSame(403, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Entity out of perimeter', $responseBody['errors']);

        $GLOBALS['login'] = 'ddur';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        $response = $entityController->getDetailledById($request, new Response(), ['id' => 'TEST-ENTITY123']);
        $this->assertSame(403, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody());
        $this->assertSame('Service forbidden', $responseBody->errors);

        $GLOBALS['login'] = 'superadmin';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];
    }

    public function testReassignEntity()
    {
        $entityController = new EntityController();

        //  CREATE
        $args = [
            'entity_id'         => 'R2-D2',
            'entity_label'      => 'TEST-ENTITY123-LABEL',
            'short_label'       => 'TEST-ENTITY123-SHORTLABEL',
            'entity_type'       => 'Service',
            'email'             => 'paris@isMagic.fr',
            'zipcode'           => '75016',
            'city'              => 'PARIS',
        ];
        $fullRequest = $this->createRequestWithBody('POST', $args);

        $response     = $entityController->create($fullRequest, new Response());
        $this->assertSame(200, $response->getStatusCode());

        UserEntityModel::deleteUserEntity(['id' => $GLOBALS['id'], 'entityId' => 'R2-D2']);

        $request = $this->createRequestWithBody('PUT', $args);
        $response       = $entityController->reassignEntity($request, new Response(), ['id' => 'R2-D2', 'newEntityId' => 'TEST-ENTITY123']);
        $this->assertSame(200, $response->getStatusCode());
        $responseBody   = json_decode((string)$response->getBody(), true);

        $this->assertIsArray($responseBody['entities']);

        // Errors
        $request = $this->createRequestWithBody('PUT', $args);
        $response       = $entityController->reassignEntity($request, new Response(), ['id' => 'R2-D29999999', 'newEntityId' => 'TEST-ENTITY123']);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody   = json_decode((string)$response->getBody(), true);

        $this->assertSame('Entity does not exist', $responseBody['errors']);

        $GLOBALS['login'] = 'bblier';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        $response     = $entityController->reassignEntity($request, new Response(), ['id' => 'PJS', 'newEntityId' => 'TEST-ENTITY123']);
        $this->assertSame(403, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Entity out of perimeter', $responseBody['errors']);

        $GLOBALS['login'] = 'ddur';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        $response = $entityController->reassignEntity($request, new Response(), ['id' => 'TEST-ENTITY123', 'newEntityId' => 'TEST-ENTITY123']);
        $this->assertSame(403, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody());
        $this->assertSame('Service forbidden', $responseBody->errors);

        $GLOBALS['login'] = 'superadmin';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];
    }

    public function testDelete()
    {
        $entityController = new EntityController();

        //  DELETE
        $request = $this->createRequest('DELETE');
        $response       = $entityController->delete($request, new Response(), ['id' => 'TEST-ENTITY123']);
        $this->assertSame(200, $response->getStatusCode());
        $responseBody   = json_decode((string)$response->getBody());

        $this->assertIsArray($responseBody->entities);

        //  READ
        $request = $this->createRequest('GET');
        $response       = $entityController->getById($request, new Response(), ['id' => self::$id]);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody   = json_decode((string)$response->getBody());

        $this->assertSame('Entity not found', $responseBody->errors);

        // Errors
        $response     = $entityController->delete($request, new Response(), ['id' => 'TEST-ENTITY123']);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Entity not found', $responseBody['errors']);

        $response     = $entityController->delete($request, new Response(), ['id' => 'PJS']);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Entity is still used', $responseBody['errors']);


        $GLOBALS['login'] = 'bblier';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        $response     = $entityController->delete($request, new Response(), ['id' => 'PJS']);
        $this->assertSame(403, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Entity out of perimeter', $responseBody['errors']);

        $GLOBALS['login'] = 'ddur';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        $response = $entityController->delete($request, new Response(), ['id' => 'TEST-ENTITY123']);
        $this->assertSame(403, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody());
        $this->assertSame('Service forbidden', $responseBody->errors);

        $GLOBALS['login'] = 'superadmin';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];
    }

    public function testGetTypes()
    {
        $entityController = new EntityController();

        //  DELETE
        $request = $this->createRequest('GET');
        $response       = $entityController->getTypes($request, new Response());
        $this->assertSame(200, $response->getStatusCode());

        $responseBody   = json_decode((string)$response->getBody(), true);

        $this->assertIsArray($responseBody['types']);
        $this->assertNotEmpty($responseBody['types']);
    }
}
