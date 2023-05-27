<?php
/**
* Copyright Maarch since 2008 under licence GPLv3.
* See LICENCE.txt file at the root folder for more details.
* This file is part of Maarch software.

* @brief   ActionsControllerTest
* @author  dev <dev@maarch.org>
* @ingroup core
*/

namespace MaarchCourrier\Tests\app\action;

use Action\controllers\ActionController;
use SrcCore\http\Response;
use MaarchCourrier\Tests\CourrierTestCase;

class ActionControllerTest extends CourrierTestCase
{
    private static $id = null;

    public function testCreate()
    {
        $actionController = new ActionController();

        //  CREATE
        $args = [
            'keyword'       => 'indexing',
            'label_action'  => 'TEST-LABEL',
            'id_status'     => '_NOSTATUS_',
            'action_page'   => 'close_mail',
            'component'     => 'closeMailAction',
            'history'       => true
        ];
        $fullRequest = $this->createRequestWithBody('POST', $args);

        $response     = $actionController->create($fullRequest, new Response());
        $responseBody = json_decode((string)$response->getBody());

        self::$id = $responseBody->actionId;

        $this->assertIsInt(self::$id);

        // FAIL CREATE
        $args = [
            'keyword'       => 'indexing',
            'label_action'  => '',
            'id_status'     => '',
            'action_page'   => 'close_mail',
            'component'     => 'closeMailAction',
            'history'       => true
        ];
        $fullRequest = $this->createRequestWithBody('POST', $args);

        $response     = $actionController->create($fullRequest, new Response());
        $responseBody = json_decode((string)$response->getBody());
        
        $this->assertSame('Invalid Status', $responseBody->errors[0]);
        $this->assertSame('Invalid label action', $responseBody->errors[1]);
        $this->assertSame('id_status is empty', $responseBody->errors[2]);
    }

    public function testRead()
    {
        //  READ
        $request = $this->createRequest('GET');

        $actionController = new ActionController();
        $response         = $actionController->getById($request, new Response(), ['id' => self::$id]);
        $responseBody     = json_decode((string)$response->getBody());

        $this->assertIsInt(self::$id);
        $this->assertSame(self::$id, $responseBody->action->id);
        $this->assertSame('indexing', $responseBody->action->keyword);
        $this->assertSame('TEST-LABEL', $responseBody->action->label_action);
        $this->assertSame('_NOSTATUS_', $responseBody->action->id_status);
        $this->assertSame(false, $responseBody->action->is_system);
        $this->assertSame('close_mail', $responseBody->action->action_page);
        $this->assertSame(true, $responseBody->action->history);

        // FAIL READ
        $actionController = new ActionController();
        $response         = $actionController->getById($request, new Response(), ['id' => 'gaz']);
        $responseBody     = json_decode((string)$response->getBody());
        $this->assertSame('Route id is not an integer', $responseBody->errors);
    }

    public function testReadList()
    {
        $request = $this->createRequest('GET');

        $actionController = new ActionController();
        $response         = $actionController->get($request, new Response());
        $responseBody     = json_decode((string)$response->getBody());

        $this->assertNotNull($responseBody->actions);
    }

    public function testUpdate()
    {
        //  UPDATE
        $args = [
            'keyword'      => '',
            'label_action' => 'TEST-LABEL_UPDATED',
            'id_status'    => 'COU',
            'action_page'  => 'close_mail',
            'component'    => 'closeMailAction',
            'history'      => false
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $args);

        $actionController = new ActionController();
        $response         = $actionController->update($fullRequest, new Response(), ['id' => self::$id]);
        $responseBody     = json_decode((string)$response->getBody());

        $this->assertSame(200, $response->getStatusCode());

        // UPDATE FAIL
        $args = [
            'keyword'      => '',
            'label_action' => 'TEST-LABEL_UPDATED',
            'id_status'    => 'COU',
            'action_page'  => 'close_mail',
            'component'    => 'closeMailAction',
            'history'      => false
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $args);

        $actionController = new ActionController();
        $response         = $actionController->update($fullRequest, new Response(), ['id' => 'gaz']);
        $responseBody     = json_decode((string)$response->getBody());
        $this->assertSame('Id is not a numeric', $responseBody->errors[0]);
    }

    public function testDelete()
    {
        //  DELETE
        $request = $this->createRequest('DELETE');

        $actionController = new ActionController();
        $response         = $actionController->delete($request, new Response(), ['id' => self::$id]);
        $responseBody     = json_decode((string)$response->getBody());

        $this->assertNotNull($responseBody->actions);

        $request = $this->createRequest('GET');
        $actionController = new ActionController();
        $response     = $actionController->getById($request, new Response(), ['id' => self::$id]);
        $responseBody = json_decode((string)$response->getBody());

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame('Action does not exist', $responseBody->errors);

        // FAIL DELETE
        $request = $this->createRequest('DELETE');

        $actionController = new ActionController();
        $response         = $actionController->delete($request, new Response(), ['id' => 'gaz']);
        $responseBody     = json_decode((string)$response->getBody());

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame('Route id is not an integer', $responseBody->errors);
    }

    public function testGetInitAction()
    {
        // InitAction
        $request = $this->createRequest('GET');

        $actionController = new ActionController();
        $response         = $actionController->initAction($request, new Response());
        $responseBody     = json_decode((string)$response->getBody());

        $this->assertNotNull($responseBody->action);
        $this->assertNotNull($responseBody->categoriesList);
        $this->assertNotNull($responseBody->statuses);
        $this->assertNotNull($responseBody->keywordsList);
    }
}
