<?php
/**
* Copyright Maarch since 2008 under licence GPLv3.
* See LICENCE.txt file at the root folder for more details.
* This file is part of Maarch software.

*
* @brief   HomeControllerTest
*
* @author  dev <dev@maarch.org>
* @ingroup core
*/

namespace MaarchCourrier\Tests\app\home;

use Home\controllers\HomeController;
use SrcCore\http\Response;
use MaarchCourrier\Tests\CourrierTestCase;
use User\models\UserModel;

class HomeControllerTest extends CourrierTestCase
{
    public function testGet()
    {
        $GLOBALS['login'] = 'bblier';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        $homeController = new HomeController();

        $request = $this->createRequest('GET');

        $response = $homeController->get($request, new Response());
        $responseBody = json_decode((string) $response->getBody());
        
        $this->assertNotNull($responseBody->regroupedBaskets);
        $this->assertNotNull($responseBody->assignedBaskets);
        $this->assertNotEmpty($responseBody->homeMessage);

        $GLOBALS['login'] = 'superadmin';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];
    }

    public function testGetMaarchParapheurDocuments()
    {
        $GLOBALS['login'] = 'jjane';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        $homeController = new HomeController();

        $request = $this->createRequest('GET');

        $response = $homeController->getMaarchParapheurDocuments($request, new Response());
        $responseBody = json_decode((string) $response->getBody());
        
        $this->assertIsArray($responseBody->documents);
        foreach ($responseBody->documents as $document) {
            $this->assertIsInt($document->id);
            $this->assertNotEmpty($document->title);
            $this->assertNotEmpty($document->mode);
            $this->assertIsBool($document->owner);
        }

        $GLOBALS['login'] = 'superadmin';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        // ERROR
        $request = $this->createRequest('GET');

        $response = $homeController->getMaarchParapheurDocuments($request, new Response());
        $responseBody = json_decode((string) $response->getBody(), true);
        $this->assertSame('User is not linked to Maarch Parapheur', $responseBody['errors']);
    }
}
