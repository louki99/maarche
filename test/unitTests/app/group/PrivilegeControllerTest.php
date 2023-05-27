<?php

/**
* Copyright Maarch since 2008 under licence GPLv3.
* See LICENCE.txt file at the root folder for more details.
* This file is part of Maarch software.
*
*/

namespace MaarchCourrier\Tests\app\group;

use Group\controllers\GroupController;
use Group\controllers\PrivilegeController;
use MaarchCourrier\Tests\CourrierTestCase;
use Resource\controllers\ResController;
use Resource\models\ResModel;
use SrcCore\http\Response;
use User\models\UserModel;

class PrivilegeControllerTest extends CourrierTestCase
{
    private static $id = null;
    private static $resId = null;

    public function testCreate()
    {
        $groupController = new GroupController();

        //  CREATE
        $args = [
            'group_id'      => 'TEST-JusticeLeague',
            'group_desc'    => 'Beyond the darkness',
            'security'      => [
                'where_clause'      => '1=2',
                'maarch_comment'    => 'commentateur du dimanche'
            ]
        ];
        $fullRequest = $this->createRequestWithBody('POST', $args);

        $response     = $groupController->create($fullRequest, new Response());
        $responseBody = json_decode((string)$response->getBody());

        self::$id = $responseBody->group;

        $this->assertIsInt($responseBody->group);
    }

    public function testAddPrivilege()
    {
        $privilegeController = new PrivilegeController();

        //  Add privilege
        $request = $this->createRequest('POST');

        $args = [
            'privilegeId'      => 'entities_print_sep_mlb',
            'id'    => self::$id
        ];

        $response     = $privilegeController->addPrivilege($request, new Response(), $args);
        $this->assertSame(204, $response->getStatusCode());

        // Add privilege again

        $response     = $privilegeController->addPrivilege($request, new Response(), $args);
        $this->assertSame(204, $response->getStatusCode());

        $args = [
            'privilegeId'      => 'admin_users',
            'id'    => self::$id
        ];

        $response     = $privilegeController->addPrivilege($request, new Response(), $args);
        $this->assertSame(204, $response->getStatusCode());

        // Error : group does not exist
        $args = [
            'privilegeId'      => 'entities_print_sep_mlb',
            'id'    => self::$id * 100
        ];

        $response     = $privilegeController->addPrivilege($request, new Response(), $args);
        $this->assertSame(400, $response->getStatusCode());

        $responseBody = json_decode((string)$response->getBody());
        $this->assertIsString($responseBody->errors);
        $this->assertSame('Group not found', $responseBody->errors);

        $args = [
            'privilegeId'      => 'entities_print_sep_mlb',
            'id'    => 'wrong format'
        ];

        $response     = $privilegeController->addPrivilege($request, new Response(), $args);
        $this->assertSame(400, $response->getStatusCode());

        $responseBody = json_decode((string)$response->getBody());
        $this->assertIsString($responseBody->errors);
        $this->assertSame('Route id is empty or not an integer', $responseBody->errors);

        $args = [
            'privilegeId'      => 1000,
            'id'    => self::$id
        ];

        $response     = $privilegeController->addPrivilege($request, new Response(), $args);
        $this->assertSame(400, $response->getStatusCode());

        $responseBody = json_decode((string)$response->getBody());
        $this->assertIsString($responseBody->errors);
        $this->assertSame('Route privilegeId is empty or not an integer', $responseBody->errors);

        $GLOBALS['login'] = 'bbain';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        $response     = $privilegeController->addPrivilege($request, new Response(), $args);
        $this->assertSame(403, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);

        $this->assertSame('Service forbidden', $responseBody['errors']);

        $GLOBALS['login'] = 'superadmin';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];
    }

    public function testUpdateParameters()
    {
        $privilegeController = new PrivilegeController();

        //  Remove privilege
        $args = [
            'privilegeId'      => 'entities_print_sep_mlb',
            'id'    => self::$id
        ];

        $body = [
            'parameters' => [
                'enabled' => true
            ]
        ];
        $fullRequest = $this->createRequestWithBody('POST', $body);

        $response     = $privilegeController->updateParameters($fullRequest, new Response(), $args);
        $this->assertSame(204, $response->getStatusCode());

        // Fails
        $body = [
            'parameters' => 'wrong format'
        ];
        $fullRequest = $this->createRequestWithBody('POST', $body);

        $response     = $privilegeController->updateParameters($fullRequest, new Response(), $args);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Body parameters is not an array', $responseBody['errors']);

        $args = [
            'privilegeId'      => 'entities_print_sep_mlb',
            'id'    => self::$id * 100
        ];

        $response     = $privilegeController->updateParameters($fullRequest, new Response(), $args);
        $this->assertSame(400, $response->getStatusCode());

        $responseBody = json_decode((string)$response->getBody());
        $this->assertIsString($responseBody->errors);
        $this->assertSame('Group not found', $responseBody->errors);

        $args = [
            'privilegeId'      => 'entities_print_sep_mlb',
            'id'    => 'wrong format'
        ];

        $response     = $privilegeController->updateParameters($fullRequest, new Response(), $args);
        $this->assertSame(400, $response->getStatusCode());

        $responseBody = json_decode((string)$response->getBody());
        $this->assertIsString($responseBody->errors);
        $this->assertSame('Route id is empty or not an integer', $responseBody->errors);

        $args = [
            'privilegeId'      => 1000,
            'id'    => self::$id
        ];

        $response     = $privilegeController->updateParameters($fullRequest, new Response(), $args);
        $this->assertSame(400, $response->getStatusCode());

        $responseBody = json_decode((string)$response->getBody());
        $this->assertIsString($responseBody->errors);
        $this->assertSame('Route privilegeId is empty or not an integer', $responseBody->errors);

        $GLOBALS['login'] = 'bbain';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        $response     = $privilegeController->updateParameters($fullRequest, new Response(), $args);
        $this->assertSame(403, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);

        $this->assertSame('Service forbidden', $responseBody['errors']);

        $GLOBALS['login'] = 'superadmin';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];
    }

    public function testGetParameters()
    {
        $privilegeController = new PrivilegeController();

        //  Remove privilege
        $request = $this->createRequest('POST');

        $args = [
            'privilegeId'      => 'entities_print_sep_mlb',
            'id'    => self::$id
        ];

        $response     = $privilegeController->getParameters($request, new Response(), $args);
        $this->assertSame(200, $response->getStatusCode());

        $responseBody = json_decode((string)$response->getBody(), true);

        $this->assertIsArray($responseBody);
        $this->assertIsBool($responseBody['enabled']);
        $this->assertSame(true, $responseBody['enabled']);


        $queryParams = ['parameter' => 'enabled'];
        $fullRequest = $request->withQueryParams($queryParams);
        $response     = $privilegeController->getParameters($fullRequest, new Response(), $args);
        $this->assertSame(200, $response->getStatusCode());

        $responseBody = json_decode((string)$response->getBody(), true);

        $this->assertIsBool($responseBody);
        $this->assertSame(true, $responseBody);

        // Fails
        $queryParams = ['parameter' => 'fake'];
        $fullRequest = $request->withQueryParams($queryParams);
        $response     = $privilegeController->getParameters($fullRequest, new Response(), $args);
        $this->assertSame(400, $response->getStatusCode());

        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Parameter not found', $responseBody['errors']);

        $args = [
            'privilegeId'      => 'entities_print_sep_mlb',
            'id'    => self::$id * 100
        ];

        $response     = $privilegeController->getParameters($request, new Response(), $args);
        $this->assertSame(400, $response->getStatusCode());

        $responseBody = json_decode((string)$response->getBody());
        $this->assertIsString($responseBody->errors);
        $this->assertSame('Group not found', $responseBody->errors);

        $args = [
            'privilegeId'      => 'entities_print_sep_mlb',
            'id'    => 'wrong format'
        ];

        $response     = $privilegeController->getParameters($request, new Response(), $args);
        $this->assertSame(400, $response->getStatusCode());

        $responseBody = json_decode((string)$response->getBody());
        $this->assertIsString($responseBody->errors);
        $this->assertSame('Route id is empty or not an integer', $responseBody->errors);

        $args = [
            'privilegeId'      => 1000,
            'id'    => self::$id
        ];

        $response     = $privilegeController->getParameters($request, new Response(), $args);
        $this->assertSame(400, $response->getStatusCode());

        $responseBody = json_decode((string)$response->getBody());
        $this->assertIsString($responseBody->errors);
        $this->assertSame('Route privilegeId is empty or not an integer', $responseBody->errors);
    }

    public function testGetPrivilegesByUser()
    {
        $privilegeController = new PrivilegeController();

        $response = $privilegeController::getPrivilegesByUser(['userId' => $GLOBALS['id']]);

        $this->assertIsArray($response);
        $this->assertSame(1, count($response));
        $this->assertSame('ALL_PRIVILEGES', $response[0]);

        $GLOBALS['login'] = 'bbain';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        $response = $privilegeController::getPrivilegesByUser(['userId' => $GLOBALS['id']]);

        $this->assertIsArray($response);
        $this->assertNotContains('ALL_PRIVILEGES', $response);

        $GLOBALS['login'] = 'superadmin';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];
    }

    public function testGetAssignableGroups()
    {
        $privilegeController = new PrivilegeController();

        $response = $privilegeController::getAssignableGroups(['userId' => $GLOBALS['id']]);

        $this->assertIsArray($response);
        $this->assertEmpty($response);

        $GLOBALS['login'] = 'bblier';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        $response = $privilegeController::getAssignableGroups(['userId' => $GLOBALS['id']]);

        $this->assertIsArray($response);
        $this->assertNotEmpty($response);

        $GLOBALS['login'] = 'superadmin';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];
    }

    public function testCanAssignGroup()
    {
        $privilegeController = new PrivilegeController();

        $response = $privilegeController::canAssignGroup(['userId' => $GLOBALS['id'], 'groupId' => self::$id]);

        $this->assertIsBool($response);
        $this->assertSame(true, $response);

        $GLOBALS['login'] = 'bblier';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        $response = $privilegeController::canAssignGroup(['userId' => $GLOBALS['id'], 'groupId' => self::$id]);

        $this->assertIsBool($response);
        $this->assertSame(false, $response);

        $GLOBALS['login'] = 'superadmin';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];
    }

    public function testIsResourceInProcess()
    {
        $GLOBALS['login'] = 'cchaplin';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        $resController = new ResController();

        //  CREATE test resource
        $fileContent = file_get_contents('test/unitTests/samples/test.txt');
        $encodedFile = base64_encode($fileContent);
        $argsMailNew = [
            'modelId'          => 1,
            'status'           => 'NEW',
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
            'encodedFile'      => $encodedFile,
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

        $privilegeController = new PrivilegeController();

        $response = $privilegeController::isResourceInProcess(['userId' => $GLOBALS['id'], 'resId' => self::$resId]);

        $this->assertIsBool($response);
        $this->assertSame(false, $response);

        $GLOBALS['login'] = 'aackermann';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        $response = $privilegeController::isResourceInProcess(['userId' => $GLOBALS['id'], 'resId' => self::$resId]);

        $this->assertIsBool($response);
        $this->assertSame(true, $response);

        $GLOBALS['login'] = 'superadmin';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        $response = $privilegeController::isResourceInProcess(['userId' => $GLOBALS['id'], 'resId' => self::$resId]);

        $this->assertIsBool($response);
        $this->assertSame(false, $response);
    }

    public function testCanUpdateResource()
    {
        $GLOBALS['login'] = 'cchaplin';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        $privilegeController = new PrivilegeController();

        $response = $privilegeController::canUpdateResource(['userId' => $GLOBALS['id'], 'resId' => self::$resId]);

        $this->assertIsBool($response);
        $this->assertSame(false, $response);

        $GLOBALS['login'] = 'aackermann';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        $response = $privilegeController::canUpdateResource(['userId' => $GLOBALS['id'], 'resId' => self::$resId]);

        $this->assertIsBool($response);
        $this->assertSame(true, $response);

        $GLOBALS['login'] = 'superadmin';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        $response = $privilegeController::canUpdateResource(['userId' => $GLOBALS['id'], 'resId' => self::$resId]);

        $this->assertIsBool($response);
        $this->assertSame(true, $response);

        ResModel::delete([
            'where' => ['res_id in (?)'],
            'data' => [[self::$resId]]
        ]);

        $res = ResModel::getById(['resId' => self::$resId, 'select' => ['*']]);
        $this->assertIsArray($res);
        $this->assertEmpty($res);
    }

    public function testRemovePrivilege()
    {
        $privilegeController = new PrivilegeController();

        //  Remove privilege
        $request = $this->createRequest('POST');

        $args = [
            'privilegeId' => 'entities_print_sep_mlb',
            'id'          => self::$id
        ];

        $response     = $privilegeController->removePrivilege($request, new Response(), $args);
        $this->assertSame(204, $response->getStatusCode());

        // Remove privilege again

        $response     = $privilegeController->removePrivilege($request, new Response(), $args);
        $this->assertSame(204, $response->getStatusCode());

        // Error : group does not exist
        $args = [
            'privilegeId'      => 'entities_print_sep_mlb',
            'id'    => self::$id * 100
        ];

        $response     = $privilegeController->removePrivilege($request, new Response(), $args);
        $this->assertSame(400, $response->getStatusCode());

        $responseBody = json_decode((string)$response->getBody());
        $this->assertIsString($responseBody->errors);
        $this->assertSame('Group not found', $responseBody->errors);

        $args = [
            'privilegeId'      => 'entities_print_sep_mlb',
            'id'    => 'wrong format'
        ];

        $response     = $privilegeController->removePrivilege($request, new Response(), $args);
        $this->assertSame(400, $response->getStatusCode());

        $responseBody = json_decode((string)$response->getBody());
        $this->assertIsString($responseBody->errors);
        $this->assertSame('Route id is empty or not an integer', $responseBody->errors);

        $args = [
            'privilegeId'      => 1000,
            'id'    => self::$id
        ];

        $response     = $privilegeController->removePrivilege($request, new Response(), $args);
        $this->assertSame(400, $response->getStatusCode());

        $responseBody = json_decode((string)$response->getBody());
        $this->assertIsString($responseBody->errors);
        $this->assertSame('Route privilegeId is empty or not an integer', $responseBody->errors);

        $GLOBALS['login'] = 'bbain';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        $response     = $privilegeController->removePrivilege($request, new Response(), $args);
        $this->assertSame(403, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);

        $this->assertSame('Service forbidden', $responseBody['errors']);

        $GLOBALS['login'] = 'superadmin';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];
    }

    public function testDelete()
    {
        $groupController = new GroupController();

        //  DELETE
        $request = $this->createRequest('DELETE');
        $response       = $groupController->delete($request, new Response(), ['id' => self::$id]);
        $responseBody   = json_decode((string)$response->getBody());

        $this->assertIsArray($responseBody->groups);
        $this->assertNotEmpty($responseBody->groups);

        //  READ
        $request = $this->createRequest('GET');
        $response       = $groupController->getDetailledById($request, new Response(), ['id' => self::$id]);
        $responseBody   = json_decode((string)$response->getBody());

        $this->assertSame('Group not found', $responseBody->errors);
    }
}
