<?php

/**
 * Copyright Maarch since 2008 under licence GPLv3.
 * See LICENCE.txt file at the root folder for more details.
 * This file is part of Maarch software.
 *
 */

namespace MaarchCourrier\Tests\app\versionUpdate;

use MaarchCourrier\Tests\CourrierTestCase;
use SrcCore\http\Response;
use VersionUpdate\controllers\VersionUpdateController;

class VersionUpdateControllerTest extends CourrierTestCase
{
    public function testGet()
    {
        $versionUpdateController = new VersionUpdateController();

        //  GET
        $request = $this->createRequest('GET');
        $response       = $versionUpdateController->get($request, new Response());
        $responseBody   = json_decode((string)$response->getBody());
        $this->assertIsString($responseBody->currentVersion);
        $this->assertNotNull($responseBody->currentVersion);
        $this->assertMatchesRegularExpression('/^\d{4}\.\d\.\d+$/', $responseBody->currentVersion, 'Invalid current version');

        if ($responseBody->lastAvailableMinorVersion != null) {
            $this->assertIsString($responseBody->lastAvailableMinorVersion);
            $this->assertMatchesRegularExpression('/^\d{4}\.\d\.\d+$/', $responseBody->lastAvailableMinorVersion, 'Invalid available minor version');
        }

        if ($responseBody->lastAvailableMajorVersion != null) {
            $this->assertIsString($responseBody->lastAvailableMajorVersion);
            $this->assertMatchesRegularExpression('/^\d{4}\.\d\.\d+$/', $responseBody->lastAvailableMajorVersion, 'Invalid available major version');
        }
    }
}
