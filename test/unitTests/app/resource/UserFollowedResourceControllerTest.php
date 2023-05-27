<?php

/**
* Copyright Maarch since 2008 under licence GPLv3.
* See LICENCE.txt file at the root folder for more details.
* This file is part of Maarch software.
*
*/

namespace MaarchCourrier\Tests\app\resource;

use MaarchCourrier\Tests\CourrierTestCase;
use Resource\controllers\ResController;
use Resource\controllers\UserFollowedResourceController;
use Resource\models\ResModel;
use Resource\models\UserFollowedResourceModel;
use SrcCore\http\Response;
use User\models\UserModel;

class UserFollowedResourceControllerTest extends CourrierTestCase
{
    private static $idFirst = null;
    private static $idSecond = null;
    private static $idThird = null;

    public function testCreate()
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
            'subject'          => 'Breaking News : Superman is alive - PHP unit FOLLOW / UNFOLLOW',
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

        $argsMailATra = [
            'modelId'          => 1,
            'status'           => 'A_TRA',
            'encodedFile'      => $encodedFile,
            'format'           => 'txt',
            'confidentiality'  => false,
            'documentDate'     => '2019-01-01 17:18:47',
            'arrivalDate'      => '2019-01-01 17:18:47',
            'processLimitDate' => '2029-01-01',
            'doctype'          => 102,
            'destination'      => 15,
            'initiator'        => 15,
            'subject'          => 'Breaking News : Superman is alive - PHP unit FOLLOW / UNFOLLOW',
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
        self::$idFirst = $responseBody['resId'];
        $this->assertIsInt(self::$idFirst);

        $response     = $resController->create($fullRequest, new Response());
        $responseBody = json_decode((string)$response->getBody(), true);
        self::$idSecond = $responseBody['resId'];
        $this->assertIsInt(self::$idFirst);

        $fullRequest = $this->createRequestWithBody('POST', $argsMailATra);
        $response     = $resController->create($fullRequest, new Response());
        $responseBody = json_decode((string)$response->getBody(), true);
        self::$idThird = $responseBody['resId'];
        $this->assertIsInt(self::$idFirst);

        //  READ
        $res = ResModel::getById(['resId' => self::$idFirst, 'select' => ['*']]);

        $this->assertIsArray($res);

        $this->assertSame('Breaking News : Superman is alive - PHP unit FOLLOW / UNFOLLOW', $res['subject']);
        $this->assertSame(102, $res['type_id']);
        $this->assertSame('txt', $res['format']);
        $this->assertSame('NEW', $res['status']);
        $this->assertSame(19, $res['typist']);
        $this->assertNotNull($res['destination']);
        $this->assertNotNull($res['initiator']);

        $GLOBALS['login'] = 'superadmin';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];
    }

    public function testUnFollow()
    {
        $GLOBALS['login'] = 'cchaplin';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        $usersFollowedResourcesController = new UserFollowedResourceController();

        $args = [
            'resources' => [self::$idFirst, self::$idSecond, self::$idThird]
        ];
        $fullRequest = $this->createRequestWithBody('DELETE', $args);

        $response     = $usersFollowedResourcesController->unFollow($fullRequest, new Response());

        $this->assertSame(200, $response->getStatusCode());

        $responseBody = json_decode((string)$response->getBody(), true);

        $this->assertSame(3, $responseBody['unFollowed']);

        $GLOBALS['login'] = 'ccharles';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        $response     = $usersFollowedResourcesController->unFollow($fullRequest, new Response());
        $responseBody = json_decode((string)$response->getBody());

        $this->assertSame(0, $responseBody->unFollowed);

        $GLOBALS['login'] = 'superadmin';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];
    }

    public function testFollow()
    {
        $GLOBALS['login'] = 'aackermann';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        $usersFollowedResourcesController = new UserFollowedResourceController();

        $args = [
            'resources' => [self::$idFirst, self::$idSecond, self::$idThird]
        ];
        $fullRequest = $this->createRequestWithBody('POST', $args);

        $response     = $usersFollowedResourcesController->follow($fullRequest, new Response());

        $this->assertSame(204, $response->getStatusCode());

        $GLOBALS['login'] = 'ccharles';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        $response     = $usersFollowedResourcesController->follow($fullRequest, new Response());
        $responseBody = json_decode((string)$response->getBody());

        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame('Document out of perimeter', $responseBody->errors);

        $GLOBALS['login'] = 'superadmin';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];
    }

    public function testGetList()
    {
        $GLOBALS['login'] = 'aackermann';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        $userFollowedResourceController = new UserFollowedResourceController();

        //  GET
        $request = $this->createRequest('GET');

        $response     = $userFollowedResourceController->getFollowedResources($request, new Response());
        $responseBody = json_decode((string)$response->getBody());

        $this->assertIsInt($responseBody->countResources);
        $this->assertSame(3, $responseBody->countResources);
        $this->assertSame(3, count($responseBody->resources));

        $this->assertNotNull($responseBody->resources[0]->priorityColor);
        $this->assertNotNull($responseBody->resources[0]->statusImage);
        $this->assertNotNull($responseBody->resources[0]->statusLabel);
        $this->assertIsInt($responseBody->resources[0]->resId);
        $this->assertSame('Breaking News : Superman is alive - PHP unit FOLLOW / UNFOLLOW', $responseBody->resources[0]->subject);

        $GLOBALS['login'] = 'bblier';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        //  GET
        $request = $this->createRequest('GET');

        $response     = $userFollowedResourceController->getFollowedResources($request, new Response());
        $responseBody = json_decode((string)$response->getBody());

        $this->assertIsInt($responseBody->countResources);
        $this->assertSame(0, $responseBody->countResources);

        $GLOBALS['login'] = 'superadmin';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];
    }

    public function testGetFilters()
    {
        $GLOBALS['login'] = 'aackermann';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        $userFollowedResourceController = new UserFollowedResourceController();

        //  GET
        $request = $this->createRequest('GET');

        $response     = $userFollowedResourceController->getFilters($request, new Response());
        $this->assertSame(200, $response->getStatusCode());

        $responseBody = json_decode((string)$response->getBody(), true);

        $this->assertIsArray($responseBody['entities']);
        $this->assertEmpty($responseBody['entities']);
        $this->assertIsArray($responseBody['priorities']);
        $this->assertEmpty($responseBody['priorities']);
        $this->assertIsArray($responseBody['categories']);
        $this->assertEmpty($responseBody['categories']);

        $this->assertIsArray($responseBody['statuses']);

        $this->assertSame(2, count($responseBody['statuses']));

        $this->assertSame('NEW', $responseBody['statuses'][0]['id']);
        $this->assertSame('Nouveau courrier pour le service', $responseBody['statuses'][0]['label']);
        $this->assertSame(2, $responseBody['statuses'][0]['count']);

        $this->assertSame('A_TRA', $responseBody['statuses'][1]['id']);
        $this->assertSame('PJ à traiter', $responseBody['statuses'][1]['label']);
        $this->assertSame(1, $responseBody['statuses'][1]['count']);

        $this->assertIsArray($responseBody['entitiesChildren']);
        $this->assertEmpty($responseBody['entitiesChildren']);
        $this->assertIsArray($responseBody['entitiesChildren']);
        $this->assertEmpty($responseBody['entitiesChildren']);
        $this->assertIsArray($responseBody['doctypes']);
        $this->assertEmpty($responseBody['doctypes']);
        $this->assertIsArray($responseBody['folders']);
        $this->assertEmpty($responseBody['folders']);

        $GLOBALS['login'] = 'bblier';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        //  GET
        $request = $this->createRequest('GET');

        $response     = $userFollowedResourceController->getFilters($request, new Response());
        $this->assertSame(200, $response->getStatusCode());

        $responseBody = json_decode((string)$response->getBody(), true);

        $this->assertIsArray($responseBody['entities']);
        $this->assertEmpty($responseBody['entities']);
        $this->assertIsArray($responseBody['priorities']);
        $this->assertEmpty($responseBody['priorities']);
        $this->assertIsArray($responseBody['categories']);
        $this->assertEmpty($responseBody['categories']);
        $this->assertIsArray($responseBody['statuses']);
        $this->assertEmpty($responseBody['statuses']);
        $this->assertIsArray($responseBody['entitiesChildren']);
        $this->assertEmpty($responseBody['entitiesChildren']);
        $this->assertIsArray($responseBody['entitiesChildren']);
        $this->assertEmpty($responseBody['entitiesChildren']);
        $this->assertIsArray($responseBody['doctypes']);
        $this->assertEmpty($responseBody['doctypes']);
        $this->assertIsArray($responseBody['folders']);
        $this->assertEmpty($responseBody['folders']);

        $GLOBALS['login'] = 'superadmin';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];
    }

    public function testGetBaskets()
    {
        $GLOBALS['login'] = 'aackermann';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        $userFollowedResourceController = new UserFollowedResourceController();

        //  GET
        $request = $this->createRequest('GET');

        // Errors
        $response     = $userFollowedResourceController->getBaskets($request, new Response(), ['resId' => 'wrong format']);
        $this->assertNotEmpty(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Route resId is not an integer', $responseBody['errors']);


        // Success
        $response     = $userFollowedResourceController->getBaskets($request, new Response(), ['resId' => self::$idFirst]);
        $this->assertSame(200, $response->getStatusCode());

        $responseBody = json_decode((string)$response->getBody(), true);

        $this->assertIsArray($responseBody['groupsBaskets']);
        $this->assertNotEmpty($responseBody['groupsBaskets']);

        $this->assertSame(2, count($responseBody['groupsBaskets']));

        $this->assertSame(2, $responseBody['groupsBaskets'][0]['groupId']);
        $this->assertSame('Utilisateur', $responseBody['groupsBaskets'][0]['groupName']);
        $this->assertSame(6, $responseBody['groupsBaskets'][0]['basketId']);
        $this->assertSame('AR - A Envoyer', $responseBody['groupsBaskets'][0]['basketName']);

        $this->assertSame(2, $responseBody['groupsBaskets'][1]['groupId']);
        $this->assertSame('Utilisateur', $responseBody['groupsBaskets'][1]['groupName']);
        $this->assertSame(4, $responseBody['groupsBaskets'][1]['basketId']);
        $this->assertSame('Courriers à traiter', $responseBody['groupsBaskets'][1]['basketName']);

        $GLOBALS['login'] = 'bblier';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        $GLOBALS['login'] = 'superadmin';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];
    }

    public function testDelete()
    {
        $GLOBALS['login'] = 'aackermann';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        //  DELETE
        ResModel::delete([
            'where' => ['res_id in (?)'],
            'data' => [[self::$idFirst, self::$idSecond, self::$idThird]]
        ]);

        UserFollowedResourceModel::delete([
            'userId' => $GLOBALS['id'],
            'resId' => self::$idFirst
        ]);
        UserFollowedResourceModel::delete([
            'userId' => $GLOBALS['id'],
            'resId' => self::$idSecond
        ]);
        UserFollowedResourceModel::delete([
            'userId' => $GLOBALS['id'],
            'resId' => self::$idThird
        ]);

        $GLOBALS['login'] = 'superadmin';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        //  READ
        $res = ResModel::getById(['resId' => self::$idFirst, 'select' => ['*']]);
        $this->assertIsArray($res);
        $this->assertEmpty($res);

        $res = ResModel::getById(['resId' => self::$idSecond, 'select' => ['*']]);
        $this->assertIsArray($res);
        $this->assertEmpty($res);

        $res = ResModel::getById(['resId' => self::$idThird, 'select' => ['*']]);
        $this->assertIsArray($res);
        $this->assertEmpty($res);
    }
}
