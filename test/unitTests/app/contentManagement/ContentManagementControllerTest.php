<?php
/**
* Copyright Maarch since 2008 under licence GPLv3.
* See LICENCE.txt file at the root folder for more details.
* This file is part of Maarch software.

* @brief   ContentManagementControllerTest
* @author  dev <dev@maarch.org>
* @ingroup core
*/

namespace MaarchCourrier\Tests\app\contentManagement;

use ContentManagement\controllers\DocumentEditorController;
use ContentManagement\controllers\JnlpController;
use SrcCore\http\Response;
use MaarchCourrier\Tests\CourrierTestCase;

class ContentManagementControllerTest extends CourrierTestCase
{
    private static $uniqueId = null;

    public function testRenderJnlp()
    {
        $contentManagementController = new JnlpController();

        $request = $this->createRequest('GET');

        $response     = $contentManagementController->renderJnlp($request, new Response(), ['jnlpUniqueId' => 'superadmin_maarchCM_12345.js']);
        $responseBody = json_decode((string)$response->getBody());
        $this->assertSame('File extension forbidden', $responseBody->errors);
    }

    public function testGenerateJnlp()
    {
        $contentManagementController = new JnlpController();

        $request = $this->createRequest('GET');

        $response     = $contentManagementController->generateJnlp($request, new Response());
        $responseBody = json_decode((string)$response->getBody());
        $this->assertNotNull($responseBody->generatedJnlp);
        $this->assertNotNull($responseBody->jnlpUniqueId);

        self::$uniqueId = $responseBody->jnlpUniqueId;
    }

    public function testIsLockFileExisting()
    {
        $contentManagementController = new JnlpController();

        $request = $this->createRequest('GET');

        $response     = $contentManagementController->isLockFileExisting($request, new Response(), ['jnlpUniqueId' => self::$uniqueId]);
        $responseBody = json_decode((string)$response->getBody());
        $this->assertNotNull($responseBody->lockFileFound);
        $this->assertIsBool($responseBody->lockFileFound);
        $this->assertSame(true, $responseBody->lockFileFound);
        $this->assertNotNull($responseBody->fileTrunk);
        $this->assertSame("tmp_file_".$GLOBALS['id']."_".self::$uniqueId, $responseBody->fileTrunk);
    }

    public function testGetDocumentEditorConfig()
    {
        $documentEditorController = new DocumentEditorController();

        $request = $this->createRequest('GET');

        $response     = $documentEditorController->get($request, new Response());
        $responseBody = json_decode((string)$response->getBody());
        
        $this->assertIsArray($responseBody);
        foreach ($responseBody as $value) {
            $this->assertContains($value, ['java', 'onlyoffice']);
        }
    }
}
