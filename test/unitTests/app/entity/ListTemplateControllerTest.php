<?php

/**
* Copyright Maarch since 2008 under licence GPLv3.
* See LICENCE.txt file at the root folder for more details.
* This file is part of Maarch software.
*
*/

namespace MaarchCourrier\Tests\app\entity;

use Entity\controllers\ListTemplateController;
use Entity\models\ListTemplateModel;
use MaarchCourrier\Tests\CourrierTestCase;
use SrcCore\http\Response;
use User\models\UserModel;

class ListTemplateControllerTest extends CourrierTestCase
{
    private static $id = null;

    public function testCreate()
    {
        $listTemplateController = new ListTemplateController();

        //  CREATE
        $body = [
            'type'              => 'visaCircuit',
            'title'             => 'TEST-LISTTEMPLATE123-TITLE',
            'description'       => 'TEST LISTTEMPLATE123 DESCRIPTION',
            'items'             => [
                [
                    'id'   => 5,
                    'type' => 'user',
                    'mode' => 'visa'
                ],
                [
                    'id'   => 10,
                    'type' => 'user',
                    'mode' => 'visa'
                ],
                [
                    'id'   => 17,
                    'type' => 'user',
                    'mode' => 'sign'
                ]
            ],
        ];
        $fullRequest = $this->createRequestWithBody('POST', $body);

        $queryParams = [
            'admin'  => true
        ];
        $fullRequest = $fullRequest->withQueryParams($queryParams);

        $response     = $listTemplateController->create($fullRequest, new Response());
        $responseBody = json_decode((string)$response->getBody());

        $this->assertIsInt($responseBody->id);

        //  READ
        $request = $this->createRequest('GET');
        $response       = $listTemplateController->get($request, new Response());
        $responseBody   = json_decode((string)$response->getBody());

        foreach ($responseBody->listTemplates as $listTemplate) {
            if ($listTemplate->title == 'TEST-LISTTEMPLATE123-TITLE') {
                self::$id = $listTemplate->id;
                $this->assertSame('visaCircuit', $listTemplate->type);
                $this->assertSame('TEST-LISTTEMPLATE123-TITLE', $listTemplate->title);
                $this->assertSame('TEST LISTTEMPLATE123 DESCRIPTION', $listTemplate->description);
            }
        }

        $this->assertNotEmpty(self::$id);

        //  READ
        $response       = $listTemplateController->getById($request, new Response(), ['id' => self::$id]);
        $responseBody   = json_decode((string)$response->getBody());

        $this->assertSame('TEST-LISTTEMPLATE123-TITLE', $responseBody->listTemplate->title);
        $this->assertSame('TEST LISTTEMPLATE123 DESCRIPTION', $responseBody->listTemplate->description);
        $this->assertSame('visaCircuit', $responseBody->listTemplate->type);

        $this->assertSame(0, $responseBody->listTemplate->items[0]->sequence);
        $this->assertSame(5, $responseBody->listTemplate->items[0]->item_id);
        $this->assertSame('user', $responseBody->listTemplate->items[0]->item_type);
        $this->assertSame('visa', $responseBody->listTemplate->items[0]->item_mode);

        $this->assertSame(1, $responseBody->listTemplate->items[1]->sequence);
        $this->assertSame(10, $responseBody->listTemplate->items[1]->item_id);
        $this->assertSame('user', $responseBody->listTemplate->items[1]->item_type);
        $this->assertSame('visa', $responseBody->listTemplate->items[1]->item_mode);

        $this->assertSame(2, $responseBody->listTemplate->items[2]->sequence);
        $this->assertSame(17, $responseBody->listTemplate->items[2]->item_id);
        $this->assertSame('user', $responseBody->listTemplate->items[2]->item_type);
        $this->assertSame('sign', $responseBody->listTemplate->items[2]->item_mode);

        // Errors
        $body = [
            'type'        => 'raceCircuit',
            'title'       => 'TEST-LISTTEMPLATE123-TITLE',
            'description' => 'TEST LISTTEMPLATE123 DESCRIPTION',
            'items'       => [
                [
                    'id'   => 5,
                    'type' => 'user',
                    'mode' => 'visa'
                ],
                [
                    'id'   => 10,
                    'type' => 'user',
                    'mode' => 'visa'
                ],
                [
                    'id'   => 17,
                    'type' => 'user',
                    'mode' => 'sign'
                ]
            ]
        ];
        $fullRequest = $this->createRequestWithBody('POST', $body);

        $response     = $listTemplateController->create($fullRequest, new Response());
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Body type is empty or not an allowed types', $responseBody['errors']);

        $body = [
            'type'        => 'diffusionList',
            'title'       => 'TEST-LISTTEMPLATE123-TITLE',
            'description' => 'TEST LISTTEMPLATE123 DESCRIPTION',
            'items'       => [
                [
                    'id'   => 5,
                    'type' => 'user',
                    'mode' => 'visa'
                ],
                [
                    'id'   => 10,
                    'type' => 'user',
                    'mode' => 'visa'
                ],
                [
                    'id'   => 17,
                    'type' => 'user',
                    'mode' => 'sign'
                ]
            ],
            'entityId' => 6
        ];
        $fullRequest = $this->createRequestWithBody('POST', $body);
        $queryParams = [
            'admin'  => true
        ];
        $fullRequest = $fullRequest->withQueryParams($queryParams);

        $response     = $listTemplateController->create($fullRequest, new Response());

        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Entity is already linked to this type of template', $responseBody['errors']);

        $GLOBALS['login'] = 'bblier';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        $body = [
            'type'        => 'visaCircuit',
            'title'       => 'TEST-LISTTEMPLATE123-TITLE',
            'description' => 'TEST LISTTEMPLATE123 DESCRIPTION',
            'items'       => [
                [
                    'id'   => 5,
                    'type' => 'user',
                    'mode' => 'visa'
                ],
                [
                    'id'   => 10,
                    'type' => 'user',
                    'mode' => 'visa'
                ],
                [
                    'id'   => 17,
                    'type' => 'user',
                    'mode' => 'sign'
                ]
            ],
            'entityId' => 6
        ];
        $fullRequest = $this->createRequestWithBody('POST', $body);
        $queryParams = [
            'admin'  => true
        ];
        $fullRequest = $fullRequest->withQueryParams($queryParams);
        $response     = $listTemplateController->create($fullRequest, new Response());
        $this->assertSame(403, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Entity out of perimeter', $responseBody['errors']);

        $GLOBALS['login'] = 'superadmin';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        $body = [
            'type'        => 'visaCircuit',
            'title'       => 'TEST-LISTTEMPLATE123-TITLE',
            'description' => 'TEST LISTTEMPLATE123 DESCRIPTION',
            'items'       => [
                [
                    'id'   => 5,
                    'type' => 'user',
                    'mode' => 'visa'
                ],
                [
                    'id'   => 10,
                    'type' => 'user',
                    'mode' => 'visa'
                ],
                [
                    'id'   => 17,
                    'mode' => 'visa'
                ]
            ],
            'entityId' => 6
        ];
        $fullRequest = $this->createRequestWithBody('POST', $body);
        $queryParams = [
            'admin'  => true
        ];
        $fullRequest = $fullRequest->withQueryParams($queryParams);
        $response     = $listTemplateController->create($fullRequest, new Response());
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('type is empty', $responseBody['errors']);

        $GLOBALS['login'] = 'ddur';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        // Mode not admin
        $body = [
            'type'        => 'visaCircuit',
            'title'       => 'TEST-LISTTEMPLATE123-TITLE',
            'description' => 'TEST LISTTEMPLATE123 DESCRIPTION',
            'items'       => [
                [
                    'id'   => 5,
                    'type' => 'user',
                    'mode' => 'visa'
                ],
                [
                    'id'   => 10,
                    'type' => 'user',
                    'mode' => 'visa'
                ],
                [
                    'id'   => 17,
                    'type' => 'user',
                    'mode' => 'sign'
                ]
            ]
        ];
        $fullRequest = $this->createRequestWithBody('POST', $body);

        $response     = $listTemplateController->create($fullRequest, new Response());
        $this->assertSame(403, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Service forbidden', $responseBody['errors']);

        $body['type'] = 'diffusionList';
        $fullRequest = $this->createRequestWithBody('POST', $body);

        $response     = $listTemplateController->create($fullRequest, new Response());
        $this->assertSame(403, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Service forbidden', $responseBody['errors']);

        $body['type'] = 'opinionCircuit';
        $fullRequest = $this->createRequestWithBody('POST', $body);

        $response     = $listTemplateController->create($fullRequest, new Response());
        $this->assertSame(403, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Service forbidden', $responseBody['errors']);

        // Mode admin
        $body['entityId'] =  21;
        $fullRequest = $this->createRequestWithBody('POST', $body);
        $queryParams = [
            'admin'  => true
        ];
        $fullRequest = $fullRequest->withQueryParams($queryParams);

        $response     = $listTemplateController->create($fullRequest, new Response());
        $this->assertSame(403, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Service forbidden', $responseBody['errors']);

        unset($body['entityId']);
        $fullRequest = $this->createRequestWithBody('POST', $body);
        $queryParams = [
            'admin'  => true
        ];
        $fullRequest = $fullRequest->withQueryParams($queryParams);

        $response     = $listTemplateController->create($fullRequest, new Response());
        $this->assertSame(403, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Service forbidden', $responseBody['errors']);

        $GLOBALS['login'] = 'superadmin';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];
    }

    public function testUpdate()
    {
        $listTemplateController = new ListTemplateController();

        //  UPDATE
        $args = [
            'title'             => 'TEST-LISTTEMPLATE123-TITLE-UPDATED',
            'description'       => 'TEST LISTTEMPLATE123 DESCRIPTION UPDATED',
            'items'             => [
                [
                    'id'   => 10,
                    'type' => 'user',
                    'mode' => 'visa'
                ],
                [
                    'id'   => 17,
                    'type' => 'user',
                    'mode' => 'sign'
                ]
            ],
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $args);

        $response     = $listTemplateController->update($fullRequest, new Response(), ['id' => self::$id]);
        $this->assertSame(204, $response->getStatusCode());

        //  READ
        $request = $this->createRequest('GET');
        $response       = $listTemplateController->get($request, new Response());
        $responseBody   = json_decode((string)$response->getBody());

        self::$id = null;
        foreach ($responseBody->listTemplates as $listTemplate) {
            if ($listTemplate->title == 'TEST-LISTTEMPLATE123-TITLE-UPDATED') {
                self::$id = $listTemplate->id;
                $this->assertSame('visaCircuit', $listTemplate->type);
                $this->assertSame('TEST-LISTTEMPLATE123-TITLE-UPDATED', $listTemplate->title);
                $this->assertSame('TEST LISTTEMPLATE123 DESCRIPTION UPDATED', $listTemplate->description);
            }
        }
        $this->assertNotEmpty(self::$id);

        //  READ
        $request = $this->createRequest('GET');
        $response       = $listTemplateController->getById($request, new Response(), ['id' => self::$id]);
        $responseBody   = json_decode((string)$response->getBody());

        $this->assertSame('TEST-LISTTEMPLATE123-TITLE-UPDATED', $responseBody->listTemplate->title);
        $this->assertSame('TEST LISTTEMPLATE123 DESCRIPTION UPDATED', $responseBody->listTemplate->description);
        $this->assertSame('visaCircuit', $responseBody->listTemplate->type);

        $this->assertSame(0, $responseBody->listTemplate->items[0]->sequence);
        $this->assertSame(10, $responseBody->listTemplate->items[0]->item_id);
        $this->assertSame('user', $responseBody->listTemplate->items[0]->item_type);
        $this->assertSame('visa', $responseBody->listTemplate->items[0]->item_mode);

        $this->assertSame(1, $responseBody->listTemplate->items[1]->sequence);
        $this->assertSame(17, $responseBody->listTemplate->items[1]->item_id);
        $this->assertSame('user', $responseBody->listTemplate->items[1]->item_type);
        $this->assertSame('sign', $responseBody->listTemplate->items[1]->item_mode);

        $this->assertSame(null, $responseBody->listTemplate->items[2]);

        // Errors
        $args = [
            'title'             => '',
            'description'       => 'TEST LISTTEMPLATE123 DESCRIPTION UPDATED',
            'items'             => [
                [
                    'id'   => 10,
                    'type' => 'user',
                    'mode' => 'visa'
                ],
                [
                    'id'   => 17,
                    'type' => 'user',
                    'mode' => 'sign'
                ]
            ],
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $args);

        $response     = $listTemplateController->update($fullRequest, new Response(), ['id' => self::$id]);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Bad Request', $responseBody['errors']);

        $GLOBALS['login'] = 'ddur';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        $args = [
            'title'             => 'TEST',
            'description'       => 'TEST LISTTEMPLATE123 DESCRIPTION UPDATED',
            'items'             => [
                [
                    'id'   => 10,
                    'type' => 'user',
                    'mode' => 'visa'
                ],
                [
                    'id'   => 17,
                    'type' => 'user',
                    'mode' => 'sign'
                ]
            ],
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $args);

        $response     = $listTemplateController->update($fullRequest, new Response(), ['id' => self::$id]);
        $this->assertSame(403, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Service forbidden', $responseBody['errors']);

        ListTemplateModel::update([
            'set'   => ['entity_id' => 6],
            'where' => ['id = ?'],
            'data'  => [self::$id]
        ]);

        $response     = $listTemplateController->update($fullRequest, new Response(), ['id' => self::$id]);
        $this->assertSame(403, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Service forbidden', $responseBody['errors']);

        $GLOBALS['login'] = 'bblier';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        $response     = $listTemplateController->update($fullRequest, new Response(), ['id' => self::$id * 1000]);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('List template not found', $responseBody['errors']);

        $response     = $listTemplateController->update($fullRequest, new Response(), ['id' => self::$id]);
        $this->assertSame(403, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Entity out of perimeter', $responseBody['errors']);

        ListTemplateModel::update([
            'set'   => ['entity_id' => null],
            'where' => ['id = ?'],
            'data'  => [self::$id]
        ]);

        $GLOBALS['login'] = 'superadmin';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        // test control items
        $body = [
            'type'        => 'visaCircuit',
            'title'       => 'TEST-LISTTEMPLATE123-TITLE',
            'description' => 'TEST LISTTEMPLATE123 DESCRIPTION',
            'items'       => [
                [
                    'id'   => 5,
                    'type' => 'user',
                    'mode' => 'visa'
                ],
                [
                    'id'   => 10,
                    'type' => 'user',
                    'mode' => 'visa'
                ],
                [
                    'type' => 'user',
                    'mode' => 'visa'
                ]
            ],
            'entityId' => 6
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $body);

        $response     = $listTemplateController->update($fullRequest, new Response(), ['id' => self::$id]);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('id is empty', $responseBody['errors']);

        $body = [
            'type'        => 'visaCircuit',
            'title'       => 'TEST-LISTTEMPLATE123-TITLE',
            'description' => 'TEST LISTTEMPLATE123 DESCRIPTION',
            'items'       => [
                [
                    'id'   => 5,
                    'type' => 'user',
                ],
                [
                    'id'   => 10,
                    'type' => 'user',
                    'mode' => 'visa'
                ],
                [
                    'type' => 'user',
                    'mode' => 'visa'
                ]
            ],
            'entityId' => 6
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $body);

        $response     = $listTemplateController->update($fullRequest, new Response(), ['id' => self::$id]);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('mode is empty', $responseBody['errors']);

        $body = [
            'type'        => 'diffusionList',
            'title'       => 'TEST-LISTTEMPLATE123-TITLE',
            'description' => 'TEST LISTTEMPLATE123 DESCRIPTION',
            'items'       => [
                [
                    'id'   => 5,
                    'type' => 'user',
                    'mode' => 'dest'
                ],
                [
                    'id'   => 10,
                    'type' => 'user',
                    'mode' => 'dest'
                ],
                [
                    'id'   => 17,
                    'type' => 'user',
                    'mode' => 'sign'
                ]
            ]
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $body);

        $response     = $listTemplateController->update($fullRequest, new Response(), ['id' => self::$id]);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Dest user is not present in this entity', $responseBody['errors']);

        ListTemplateModel::update([
            'set'   => ['entity_id' => 4, 'type' => 'opinionCircuit'],
            'where' => ['id = ?'],
            'data'  => [self::$id]
        ]);

        $body = [
            'type'        => 'opinionCircuit',
            'title'       => 'TEST-LISTTEMPLATE123-TITLE',
            'description' => 'TEST LISTTEMPLATE123 DESCRIPTION',
            'items'       => [
                [
                    'id'   => 8,
                    'type' => 'user',
                    'mode' => 'dest'
                ],
                [
                    'id'   => 17,
                    'type' => 'user',
                    'mode' => 'dest'
                ]
            ]
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $body);

        $response     = $listTemplateController->update($fullRequest, new Response(), ['id' => self::$id]);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('item has not enough privileges', $responseBody['errors']);

        ListTemplateModel::update([
            'set'   => ['type' => 'visaCircuit'],
            'where' => ['id = ?'],
            'data'  => [self::$id]
        ]);

        $body = [
            'type'        => 'visaCircuit',
            'title'       => 'TEST-LISTTEMPLATE123-TITLE',
            'description' => 'TEST LISTTEMPLATE123 DESCRIPTION',
            'items'       => [
                [
                    'id'   => 8,
                    'type' => 'user',
                    'mode' => 'dest'
                ],
                [
                    'id'   => 17,
                    'type' => 'user',
                    'mode' => 'dest'
                ]
            ]
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $body);

        $response     = $listTemplateController->update($fullRequest, new Response(), ['id' => self::$id]);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('item has not enough privileges', $responseBody['errors']);

        ListTemplateModel::update([
            'set'   => ['entity_id' => 1],
            'where' => ['id = ?'],
            'data'  => [self::$id]
        ]);

        $body = [
            'type'        => 'diffusionList',
            'title'       => 'TEST-LISTTEMPLATE123-TITLE',
            'description' => 'TEST LISTTEMPLATE123 DESCRIPTION',
            'items'       => [
                [
                    'id'   => 10,
                    'type' => 'user',
                    'mode' => 'dest'
                ],
                [
                    'id'   => 10,
                    'type' => 'user',
                    'mode' => 'dest'
                ],
                [
                    'id'   => 10,
                    'type' => 'user',
                    'mode' => 'dest'
                ]
            ]
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $body);

        $response     = $listTemplateController->update($fullRequest, new Response(), ['id' => self::$id]);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('More than one dest not allowed', $responseBody['errors']);

        ListTemplateModel::update([
            'set'   => ['entity_id' => null],
            'where' => ['id = ?'],
            'data'  => [self::$id]
        ]);
    }

    public function testGetByEntityId()
    {
        $listTemplateController = new ListTemplateController();

        // Errors
        $request = $this->createRequest('GET');

        $response       = $listTemplateController->getByEntityId($request, new Response(), ['entityId' => 1000]);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody   = json_decode((string)$response->getBody(), true);
        $this->assertSame('Entity does not exist', $responseBody['errors']);

        $queryParams = [
            'type' => 'toto'
        ];
        $fullRequest = $request->withQueryParams($queryParams);
        $response = $listTemplateController->getByEntityId($fullRequest, new Response(), ['entityId' => 6]);
        $this->assertSame(200, $response->getStatusCode());
        $responseBody   = json_decode((string)$response->getBody(), true);

        $this->assertIsArray($responseBody['listTemplates']);
        $this->assertIsArray($responseBody['listTemplates'][0]);
        $this->assertSame(6, $responseBody['listTemplates'][0]['id']);
        $this->assertIsString($responseBody['listTemplates'][0]['title']);
        $this->assertIsString($responseBody['listTemplates'][0]['description']);
        $this->assertSame('diffusionList', $responseBody['listTemplates'][0]['type']);
        $this->assertSame(6, $responseBody['listTemplates'][0]['entity_id']);
        $this->assertSame(null, $responseBody['listTemplates'][0]['owner']);
        $this->assertIsArray($responseBody['listTemplates'][0]['items']);

        $this->assertSame(6, $responseBody['listTemplates'][0]['items'][0]['list_template_id']);
        $this->assertSame(19, $responseBody['listTemplates'][0]['items'][0]['item_id']);
        $this->assertSame('user', $responseBody['listTemplates'][0]['items'][0]['item_type']);
        $this->assertSame('dest', $responseBody['listTemplates'][0]['items'][0]['item_mode']);
        $this->assertSame(0, $responseBody['listTemplates'][0]['items'][0]['sequence']);
        $this->assertIsString($responseBody['listTemplates'][0]['items'][0]['labelToDisplay']);
        $this->assertIsString($responseBody['listTemplates'][0]['items'][0]['descriptionToDisplay']);
        $this->assertSame(true, $responseBody['listTemplates'][0]['items'][0]['hasPrivilege']);

        $this->assertSame(6, $responseBody['listTemplates'][0]['items'][1]['list_template_id']);
        $this->assertSame(1, $responseBody['listTemplates'][0]['items'][1]['item_id']);
        $this->assertSame('entity', $responseBody['listTemplates'][0]['items'][1]['item_type']);
        $this->assertSame('cc', $responseBody['listTemplates'][0]['items'][1]['item_mode']);
        $this->assertSame(1, $responseBody['listTemplates'][0]['items'][1]['sequence']);
        $this->assertIsString($responseBody['listTemplates'][0]['items'][1]['labelToDisplay']);
        $this->assertEmpty($responseBody['listTemplates'][0]['items'][1]['descriptionToDisplay']);

        $queryParams = [
            'type' => 'visaCircuit'
        ];
        $fullRequest = $request->withQueryParams($queryParams);
        $response = $listTemplateController->getByEntityId($fullRequest, new Response(), ['entityId' => 6]);
        $this->assertSame(200, $response->getStatusCode());
        $responseBody   = json_decode((string)$response->getBody(), true);

        $this->assertIsArray($responseBody['listTemplates']);
        $this->assertEmpty($responseBody['listTemplates']);
    }

    public function testUpdateByUserWithEntityDest()
    {
        $listTemplateController = new ListTemplateController();

        // Errors
        $request = $this->createRequest('PUT');

        $GLOBALS['login'] = 'ddur';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        $response = $listTemplateController->updateByUserWithEntityDest($request, new Response(), []);
        $this->assertSame(403, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Service forbidden', $responseBody['errors']);

        $GLOBALS['login'] = 'superadmin';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        $body = [
            'redirectListModels' => [
                [
                    'entity_id' => 6,
                    'redirectUserId' => 'mscott'
                ]
            ]
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $body);
        $response = $listTemplateController->updateByUserWithEntityDest($fullRequest, new Response(), ['item_id' => $GLOBALS['id']]);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('User not found or not active', $responseBody['errors']);

        // Success
        $body = [
            'redirectListModels' => [
                [
                    'entity_id' => 6,
                    'redirectUserId' => 'bbain'
                ]
            ]
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $body);
        $response = $listTemplateController->updateByUserWithEntityDest($fullRequest, new Response(), ['item_id' => $GLOBALS['id']]);
        $this->assertSame(204, $response->getStatusCode());
    }

    public function testUpdateTypeRoles()
    {
        $listTemplateController = new ListTemplateController();

        // Errors
        $request = $this->createRequest('PUT');

        $GLOBALS['login'] = 'ddur';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        $response = $listTemplateController->updateTypeRoles($request, new Response(), []);
        $this->assertSame(403, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Service forbidden', $responseBody['errors']);

        $GLOBALS['login'] = 'superadmin';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        $response = $listTemplateController->updateTypeRoles($request, new Response(), []);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Bad Request', $responseBody['errors']);

        // Success
        $body = [
            'roles' => [
                [
                    'available' => true,
                    'id' => 'dest'
                ],[
                    'available' => true,
                    'id' => 'copy'
                ],[
                    'available' => true,
                    'id' => 'avis'
                ],[
                    'available' => true,
                    'id' => 'avis_copy'
                ]
            ]
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $body);
        $response = $listTemplateController->updateTypeRoles($fullRequest, new Response(), ['typeId' => 'entity_id']);
        $this->assertSame(200, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('success', $responseBody['success']);
    }

    public function testGetRoles()
    {
        $listTemplateController = new ListTemplateController();

        $request = $this->createRequest('GET');

        $queryParams = [
            'context' => 'indexation'
        ];
        $fullRequest = $request->withQueryParams($queryParams);
        $response = $listTemplateController->getRoles($fullRequest, new Response());
        $this->assertSame(200, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);

        $this->assertIsArray($responseBody['roles']);

        $this->assertSame('dest', $responseBody['roles'][0]['id']);
        $this->assertSame('Attributaire', $responseBody['roles'][0]['label']);
        $this->assertSame(false, $responseBody['roles'][0]['keepInListInstance']);

        $this->assertSame('cc', $responseBody['roles'][1]['id']);
        $this->assertSame('En copie', $responseBody['roles'][1]['label']);
        $this->assertSame(true, $responseBody['roles'][1]['keepInListInstance']);

        $this->assertSame('avis', $responseBody['roles'][2]['id']);
        $this->assertSame('Pour avis', $responseBody['roles'][2]['label']);
        $this->assertSame(false, $responseBody['roles'][2]['keepInListInstance']);

        $this->assertSame('avis_copy', $responseBody['roles'][3]['id']);
        $this->assertSame('En copie (avis)', $responseBody['roles'][3]['label']);
        $this->assertSame(false, $responseBody['roles'][3]['keepInListInstance']);

        $queryParams = [
            'context' => 'process'
        ];
        $fullRequest = $request->withQueryParams($queryParams);
        $response = $listTemplateController->getRoles($fullRequest, new Response());
        $this->assertSame(200, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);

        $this->assertIsArray($responseBody['roles']);

        $this->assertSame('dest', $responseBody['roles'][0]['id']);
        $this->assertSame('Attributaire', $responseBody['roles'][0]['label']);
        $this->assertSame(false, $responseBody['roles'][0]['keepInListInstance']);
        $this->assertSame(true, $responseBody['roles'][0]['canUpdate']);

        $this->assertSame('cc', $responseBody['roles'][1]['id']);
        $this->assertSame('En copie', $responseBody['roles'][1]['label']);
        $this->assertSame(true, $responseBody['roles'][1]['keepInListInstance']);
        $this->assertSame(true, $responseBody['roles'][1]['canUpdate']);

        $this->assertSame('avis', $responseBody['roles'][2]['id']);
        $this->assertSame('Pour avis', $responseBody['roles'][2]['label']);
        $this->assertSame(false, $responseBody['roles'][2]['keepInListInstance']);
        $this->assertSame(true, $responseBody['roles'][2]['canUpdate']);

        $this->assertSame('avis_copy', $responseBody['roles'][3]['id']);
        $this->assertSame('En copie (avis)', $responseBody['roles'][3]['label']);
        $this->assertSame(false, $responseBody['roles'][3]['keepInListInstance']);
        $this->assertSame(true, $responseBody['roles'][3]['canUpdate']);

        $queryParams = [
            'context' => 'details'
        ];
        $fullRequest = $request->withQueryParams($queryParams);
        $response = $listTemplateController->getRoles($fullRequest, new Response());
        $this->assertSame(200, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);

        $this->assertIsArray($responseBody['roles']);

        $this->assertSame('dest', $responseBody['roles'][0]['id']);
        $this->assertSame('Attributaire', $responseBody['roles'][0]['label']);
        $this->assertSame(false, $responseBody['roles'][0]['keepInListInstance']);
        $this->assertSame(true, $responseBody['roles'][0]['canUpdate']);

        $this->assertSame('cc', $responseBody['roles'][1]['id']);
        $this->assertSame('En copie', $responseBody['roles'][1]['label']);
        $this->assertSame(true, $responseBody['roles'][1]['keepInListInstance']);
        $this->assertSame(true, $responseBody['roles'][1]['canUpdate']);

        $this->assertSame('avis', $responseBody['roles'][2]['id']);
        $this->assertSame('Pour avis', $responseBody['roles'][2]['label']);
        $this->assertSame(false, $responseBody['roles'][2]['keepInListInstance']);
        $this->assertSame(true, $responseBody['roles'][2]['canUpdate']);

        $this->assertSame('avis_copy', $responseBody['roles'][3]['id']);
        $this->assertSame('En copie (avis)', $responseBody['roles'][3]['label']);
        $this->assertSame(false, $responseBody['roles'][3]['keepInListInstance']);
        $this->assertSame(true, $responseBody['roles'][3]['canUpdate']);
    }

    public function testGetAvailableCircuits()
    {
        $listTemplateController = new ListTemplateController();

        $request = $this->createRequest('GET');

        $queryParams = [];
        $fullRequest = $request->withQueryParams($queryParams);
        $response = $listTemplateController->getAvailableCircuits($fullRequest, new Response());
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Query params circuit is empty', $responseBody['errors']);

        $queryParams = [
            'circuit' => 'visaCircuit'
        ];
        $fullRequest = $request->withQueryParams($queryParams);
        $response = $listTemplateController->getAvailableCircuits($fullRequest, new Response());
        $responseBody = json_decode((string)$response->getBody(), true);

        $this->assertIsArray($responseBody['circuits']);

        $this->assertSame('visaCircuit', $responseBody['circuits'][0]['type']);
        $this->assertEmpty($responseBody['circuits'][0]['entityId']);
        $this->assertSame('TEST-LISTTEMPLATE123-TITLE-UPDATED', $responseBody['circuits'][0]['title']);
        $this->assertSame('TEST LISTTEMPLATE123 DESCRIPTION UPDATED', $responseBody['circuits'][0]['description']);
        $this->assertSame(false, $responseBody['circuits'][0]['private']);
    }

    public function testGetDefaultCircuitByResId()
    {
        $listTemplateController = new ListTemplateController();

        $request = $this->createRequest('GET');

        $queryParams = [];
        $fullRequest = $request->withQueryParams($queryParams);
        $response = $listTemplateController->getDefaultCircuitByResId($fullRequest, new Response(), []);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Query params circuit is empty', $responseBody['errors']);

        $queryParams = [
            'circuit' => 'visa'
        ];
        $fullRequest = $request->withQueryParams($queryParams);
        $response = $listTemplateController->getDefaultCircuitByResId($fullRequest, new Response(), ['resId' => $GLOBALS['resources'][0]]);
        $this->assertSame(200, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame(null, $responseBody['circuit']);

        ListTemplateModel::update([
            'set'   => ['entity_id' => 6],
            'where' => ['id = ?'],
            'data'  => [self::$id]
        ]);

        $response = $listTemplateController->getDefaultCircuitByResId($fullRequest, new Response(), ['resId' => $GLOBALS['resources'][0]]);
        $this->assertSame(200, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);

        $this->assertIsArray($responseBody['circuit']);
        $this->assertSame(self::$id, $responseBody['circuit']['id']);
        $this->assertSame('visaCircuit', $responseBody['circuit']['type']);
        $this->assertSame(6, $responseBody['circuit']['entityId']);
        $this->assertSame('TEST-LISTTEMPLATE123-TITLE-UPDATED', $responseBody['circuit']['title']);
        $this->assertSame('TEST LISTTEMPLATE123 DESCRIPTION UPDATED', $responseBody['circuit']['description']);

        $this->assertIsArray($responseBody['circuit']['items']);

        $this->assertSame(self::$id, $responseBody['circuit']['items'][0]['list_template_id']);
        $this->assertSame(10, $responseBody['circuit']['items'][0]['item_id']);
        $this->assertSame('user', $responseBody['circuit']['items'][0]['item_type']);
        $this->assertSame('visa', $responseBody['circuit']['items'][0]['item_mode']);
        $this->assertSame(0, $responseBody['circuit']['items'][0]['sequence']);
        $this->assertIsString($responseBody['circuit']['items'][0]['labelToDisplay']);
        $this->assertIsString($responseBody['circuit']['items'][0]['descriptionToDisplay']);
        $this->assertSame(true, $responseBody['circuit']['items'][0]['hasPrivilege']);

        $this->assertSame(self::$id, $responseBody['circuit']['items'][1]['list_template_id']);
        $this->assertSame(17, $responseBody['circuit']['items'][1]['item_id']);
        $this->assertSame('user', $responseBody['circuit']['items'][1]['item_type']);
        $this->assertSame('sign', $responseBody['circuit']['items'][1]['item_mode']);
        $this->assertSame(1, $responseBody['circuit']['items'][1]['sequence']);
        $this->assertIsString($responseBody['circuit']['items'][1]['labelToDisplay']);
        $this->assertIsString($responseBody['circuit']['items'][1]['descriptionToDisplay']);
        $this->assertSame(true, $responseBody['circuit']['items'][1]['hasPrivilege']);
    }

    public function testDelete()
    {
        $listTemplateController = new ListTemplateController();

        // Errors
        $request = $this->createRequest('DELETE');

        $response       = $listTemplateController->delete($request, new Response(), ['id' => self::$id * 1000]);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody   = json_decode((string)$response->getBody(), true);
        $this->assertSame('List template not found', $responseBody['errors']);

        $GLOBALS['login'] = 'ddur';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        $response       = $listTemplateController->delete($request, new Response(), ['id' => self::$id]);
        $this->assertSame(403, $response->getStatusCode());
        $responseBody   = json_decode((string)$response->getBody(), true);
        $this->assertSame('Service forbidden', $responseBody['errors']);

        ListTemplateModel::update([
            'set'   => ['entity_id' => 6],
            'where' => ['id = ?'],
            'data'  => [self::$id]
        ]);

        $response       = $listTemplateController->delete($request, new Response(), ['id' => self::$id]);
        $this->assertSame(403, $response->getStatusCode());
        $responseBody   = json_decode((string)$response->getBody(), true);
        $this->assertSame('Service forbidden', $responseBody['errors']);

        $userInfo = UserModel::getByLogin(['login' => 'bblier', 'select' => ['id']]);

        ListTemplateModel::update([
            'set'   => ['owner' => $userInfo['id']],
            'where' => ['id = ?'],
            'data'  => [self::$id]
        ]);

        $response       = $listTemplateController->delete($request, new Response(), ['id' => self::$id]);
        $this->assertSame(403, $response->getStatusCode());
        $responseBody   = json_decode((string)$response->getBody(), true);
        $this->assertSame('Service forbidden', $responseBody['errors']);

        $response       = $listTemplateController->getById($request, new Response(), ['id' => self::$id]);
        $this->assertSame(403, $response->getStatusCode());
        $responseBody   = json_decode((string)$response->getBody(), true);
        $this->assertSame('Cannot access private model', $responseBody['errors']);

        $GLOBALS['login'] = 'bblier';
        $GLOBALS['id'] = $userInfo['id'];

        $response       = $listTemplateController->delete($request, new Response(), ['id' => self::$id]);
        $this->assertSame(403, $response->getStatusCode());
        $responseBody   = json_decode((string)$response->getBody(), true);
        $this->assertSame('Entity out of perimeter', $responseBody['errors']);

        ListTemplateModel::update([
            'set'   => ['entity_id' => null, 'owner' => null],
            'where' => ['id = ?'],
            'data'  => [self::$id]
        ]);

        $GLOBALS['login'] = 'superadmin';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        // Success
        //  DELETE
        $request = $this->createRequest('DELETE');
        $response       = $listTemplateController->delete($request, new Response(), ['id' => self::$id]);
        $this->assertSame(204, $response->getStatusCode());

        //  READ
        $request = $this->createRequest('GET');
        $response       = $listTemplateController->getById($request, new Response(), ['id' => self::$id]);
        $responseBody   = json_decode((string)$response->getBody());

        $this->assertSame('List template not found', $responseBody->errors);
    }

    public function testGetTypesRoles()
    {
        $listTemplateController = new ListTemplateController();

        $request = $this->createRequest('GET');
        $response       = $listTemplateController->getTypeRoles($request, new Response(), ['typeId' => 'entity_id']);
        $responseBody   = json_decode((string)$response->getBody());

        foreach ($responseBody->roles as $value) {
            $this->assertNotEmpty($value->id);
            $this->assertNotEmpty($value->label);
            $this->assertIsBool($value->available);
        }
    }
}
