<?php
/**
* Copyright Maarch since 2008 under licence GPLv3.
* See LICENCE.txt file at the root folder for more details.
* This file is part of Maarch software.

* @brief   AdministrationControllerTest
* @author  dev <dev@maarch.org>
* @ingroup core
*/

namespace MaarchCourrier\Tests\app\administration;

use Administration\controllers\AdministrationController;
use SrcCore\http\Response;
use MaarchCourrier\Tests\CourrierTestCase;
use User\models\UserModel;

class AdministrationControllerTest extends CourrierTestCase
{
    public function testGetDetails()
    {
        $GLOBALS['login'] = 'bblier';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        $request = $this->createRequest('GET');

        $administrationController = new AdministrationController();
        $response         = $administrationController->getDetails($request, new Response());
        $responseBody     = json_decode((string)$response->getBody(), true);

        $this->assertNotNull($responseBody['count']);
        $this->assertIsInt($responseBody['count']['users']);
        $this->assertIsInt($responseBody['count']['groups']);
        $this->assertIsInt($responseBody['count']['entities']);

        $nbUsersNotRoot = $responseBody['count']['users'];

        $GLOBALS['login'] = 'superadmin';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        $administrationController = new AdministrationController();
        $response         = $administrationController->getDetails($request, new Response());
        $responseBody     = json_decode((string)$response->getBody(), true);

        $this->assertNotNull($responseBody['count']);
        $this->assertIsInt($responseBody['count']['users']);
        $this->assertIsInt($responseBody['count']['groups']);
        $this->assertIsInt($responseBody['count']['entities']);
        $this->assertTrue($nbUsersNotRoot < $responseBody['count']['users']);
    }
}
