<?php

/**
* Copyright Maarch since 2008 under licence GPLv3.
* See LICENCE.txt file at the root folder for more details.
* This file is part of Maarch software.
*
*/

namespace MaarchCourrier\Tests\app\docserver;

use Docserver\controllers\DocserverController;
use Docserver\controllers\DocserverTypeController;
use SrcCore\http\Response;
use MaarchCourrier\Tests\CourrierTestCase;

class DocserverControllerTest extends CourrierTestCase
{
    private static $id = null;
    private static $pathTemplate = '/tmp/unitTestMaarchCourrier/';

    public function testGet()
    {
        $docserverController = new DocserverController();

        $request = $this->createRequest('GET');

        $response     = $docserverController->get($request, new Response());
        $responseBody = json_decode((string)$response->getBody());

        $this->assertNotEmpty($responseBody->docservers);
        $this->assertNotEmpty($responseBody->types);
    }

    public function testCreate()
    {
        $docserverController = new DocserverController();

        //  CREATE
        if (!is_dir(self::$pathTemplate)) {
            mkdir(self::$pathTemplate);
        }

        $args = [
            'docserver_id'           =>  'NEW_DOCSERVER',
            'docserver_type_id'      =>  'DOC',
            'device_label'           =>  'new docserver',
            'size_limit_number'      =>  50000000000,
            'path_template'          =>  self::$pathTemplate,
            'coll_id'                =>  'letterbox_coll'
        ];
        $fullRequest = $this->createRequestWithBody('POST', $args);

        $response     = $docserverController->create($fullRequest, new Response());
        $responseBody = json_decode((string)$response->getBody());

        self::$id = $responseBody->docserver;
        $this->assertIsInt(self::$id);

        //  READ
        $request = $this->createRequest('GET');
        $response       = $docserverController->getById($request, new Response(), ['id' =>  self::$id]);
        $responseBody   = json_decode((string)$response->getBody());

        $this->assertSame('NEW_DOCSERVER', $responseBody->docserver_id);

        //  CREATE
        $args = [
            'docserver_id'           =>  'WRONG_PATH',
            'docserver_type_id'      =>  'DOC',
            'device_label'           =>  'new docserver',
            'size_limit_number'      =>  50000000000,
            'path_template'          =>  '/wrong/path/',
            'coll_id'                =>  'letterbox_coll'
        ];
        $fullRequest = $this->createRequestWithBody('POST', $args);

        $response     = $docserverController->create($fullRequest, new Response());
        $responseBody = json_decode((string)$response->getBody());
        
        $this->assertSame(_PATH_OF_DOCSERVER_UNAPPROACHABLE, $responseBody->errors);

        //  CREATE
        $args = [
            'docserver_id'           =>  'BAD_REQUEST',
            'docserver_type_id'      =>  'DOC',
            'device_label'           =>  'new docserver',
            'size_limit_number'      =>  50000000000,
            'path_template'          =>  null,
            'coll_id'                =>  'letterbox_coll'
        ];
        $fullRequest = $this->createRequestWithBody('POST', $args);
        $response     = $docserverController->create($fullRequest, new Response());
        $responseBody = json_decode((string)$response->getBody());
        
        $this->assertSame('Bad Request', $responseBody->errors);

        //  CREATE
        $args = [
            'docserver_id'           =>  'NEW_DOCSERVER',
            'docserver_type_id'      =>  'DOC',
            'device_label'           =>  'new docserver',
            'size_limit_number'      =>  50000000000,
            'path_template'          =>  '/var/docserversDEV/dev1804/archive_transfer/',
            'coll_id'                =>  'letterbox_coll'
        ];
        $fullRequest = $this->createRequestWithBody('POST', $args);
        $response     = $docserverController->create($fullRequest, new Response());
        $responseBody = json_decode((string)$response->getBody());
        
        $this->assertSame(_ID. ' ' . _ALREADY_EXISTS, $responseBody->errors);
    }

    public function testUpdate()
    {
        $docserverController = new DocserverController();

        //  UPDATE
        $args = [
            'docserver_type_id' =>  'DOC',
            'device_label'      =>  'updated docserver',
            'size_limit_number' =>  50000000000,
            'path_template'     =>  self::$pathTemplate,
            'is_readonly'       =>  true
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $args);
        $response     = $docserverController->update($fullRequest, new Response(), ['id' => self::$id]);
        $responseBody = json_decode((string)$response->getBody());

        $this->assertNotEmpty($responseBody->docserver);
        $this->assertSame('updated docserver', $responseBody->docserver->device_label);

        //  READ
        $request = $this->createRequest('GET');
        $response       = $docserverController->getById($request, new Response(), ['id' =>  self::$id]);
        $responseBody   = json_decode((string)$response->getBody());

        $this->assertSame('updated docserver', $responseBody->device_label);

        //  UPDATE
        $args = [
            'docserver_type_id'      =>  'DOC',
            'device_label'           =>  'updated docserver',
            'size_limit_number'      =>  50000000000,
            'path_template'          =>  '/wrong/path/',
            'is_readonly'       =>  true
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $args);
        $response     = $docserverController->update($fullRequest, new Response(), ['id' => self::$id]);
        $responseBody = json_decode((string)$response->getBody());

        $this->assertSame(_PATH_OF_DOCSERVER_UNAPPROACHABLE, $responseBody->errors);

        //  UPDATE
        $args = [
            'docserver_type_id'      =>  'DOC',
            'device_label'           =>  'updated docserver',
            'size_limit_number'      =>  50000000000,
            'path_template'          =>  self::$pathTemplate,
            'is_readonly'       =>  true
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $args);
        $response     = $docserverController->update($fullRequest, new Response(), ['id' => 12345]);
        $responseBody = json_decode((string)$response->getBody());

        $this->assertSame('Docserver not found', $responseBody->errors);
    }

    public function testDelete()
    {
        $docserverController = new DocserverController();

        //  DELETE
        $request = $this->createRequest('DELETE');
        $response       = $docserverController->delete($request, new Response(), ['id' => self::$id]);
        $responseBody   = json_decode((string)$response->getBody());

        $this->assertsame('success', $responseBody->success);

        //  READ
        $request = $this->createRequest('GET');
        $response       = $docserverController->getById($request, new Response(), ['id' =>  self::$id]);
        $responseBody   = json_decode((string)$response->getBody());

        $this->assertSame('Docserver not found', $responseBody->errors);

        //  DELETE
        $request = $this->createRequest('DELETE');
        $response       = $docserverController->delete($request, new Response(), ['id' => self::$id]);
        $responseBody   = json_decode((string)$response->getBody());

        $this->assertSame('Docserver does not exist', $responseBody->errors);

        rmdir(self::$pathTemplate);
    }

    public function testGetDocserverTypes()
    {
        $docserverTypeController = new DocserverTypeController();

        $request = $this->createRequest('GET');

        $response     = $docserverTypeController->get($request, new Response());
        $responseBody = json_decode((string)$response->getBody());

        $this->assertNotEmpty($responseBody->docserverTypes);
        foreach ($responseBody->docserverTypes as $docserverType) {
            $this->assertNotEmpty($docserverType->docserver_type_id);
            $this->assertNotEmpty($docserverType->docserver_type_label);
            $this->assertNotEmpty($docserverType->enabled);
        }
    }
}
