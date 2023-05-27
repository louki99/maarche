<?php
/**
* Copyright Maarch since 2008 under licence GPLv3.
* See LICENCE.txt file at the root folder for more details.
* This file is part of Maarch software.

* @brief   ActionsControllerTest
* @author  dev <dev@maarch.org>
* @ingroup core
*/

namespace MaarchCourrier\Tests\core;

use SrcCore\controllers\CoreController;
use SrcCore\http\Response;
use SrcCore\models\CoreConfigModel;
use MaarchCourrier\Tests\CourrierTestCase;

class CoreControllerTest extends CourrierTestCase
{
    public function testGetHeader()
    {
        $coreController = new CoreController();

        $request = $this->createRequest('GET');

        $response     = $coreController->getHeader($request, new Response());
        $responseBody = json_decode((string)$response->getBody());
        $this->assertNotEmpty($responseBody->user);
        $this->assertIsInt($responseBody->user->id);
        $this->assertSame("superadmin", $responseBody->user->user_id);
        $this->assertSame("Super", $responseBody->user->firstname);
        $this->assertSame("ADMIN", $responseBody->user->lastname);
        $this->assertIsArray($responseBody->user->groups);
        $this->assertIsArray($responseBody->user->entities);
    }

    public function testGetLanguage()
    {
        $availableLanguages = ['fr'];

        foreach ($availableLanguages as $value) {
            $this->assertFileExists("src/core/lang/lang-{$value}.php");
            $this->assertStringNotEqualsFile("src/core/lang/lang-{$value}.php", '');
        }
        $language = CoreConfigModel::getLanguage();
        $this->assertNotEmpty($language);
        require_once("src/core/lang/lang-{$language}.php");
        
        $this->assertFileDoesNotExist("src/core/lang/lang-en.php");
        $this->assertFileDoesNotExist("src/core/lang/lang-nl.php");
        $this->assertFileDoesNotExist("src/core/lang/lang-zh.php");
    }

    public function testGetExternalConnectionsEnabled()
    {
        $coreController = new CoreController();

        $request = $this->createRequest('GET');

        $response     = $coreController->externalConnectionsEnabled($request, new Response());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertIsBool($responseBody['connection']['maarchParapheur']);
        $this->assertSame(true, $responseBody['connection']['maarchParapheur']);
    }
}
