<?php

/**
* Copyright Maarch since 2008 under licence GPLv3.
* See LICENCE.txt file at the root folder for more details.
* This file is part of Maarch software.
*
*/

namespace MaarchCourrier\Tests\app\indexingModel;

use IndexingModel\controllers\IndexingModelController;
use MaarchCourrier\Tests\CourrierTestCase;
use SrcCore\http\Response;

class IndexingModelControllerTest extends CourrierTestCase
{
    private static $masterId = null;
    private static $childId = null;
    private static $childId2 = null;

    public function testCreate()
    {
        $indexingModelController = new IndexingModelController();

        //  CREATE
        $args = [
            'label'     => 'mon model d indexation',
            'category'  => 'incoming',
            'private'   => false,
            'fields'    => [
                [
                    'identifier'    => 'subject',
                    'mandatory'     => true,
                    'enabled'       => true,
                    'default_value' => 'tika',
                    'unit'          => 'mail'
                ], [
                    'identifier'    => 'doctype',
                    'mandatory'     => true,
                    'enabled'       => false,
                    'default_value' => 'type_test',
                    'unit'          => 'mail'
                ],
                [
                    'identifier'    => 'name',
                    'mandatory'     => true,
                    'enabled'       => true,
                    'default_value' => 'massala',
                    'unit'          => 'contact'
                ]
            ],
            'mandatoryFile' => false
        ];
        $fullRequest = $this->createRequestWithBody('POST', $args);

        $response     = $indexingModelController->create($fullRequest, new Response());
        $responseBody = json_decode((string)$response->getBody());

        $this->assertSame(200, $response->getStatusCode());

        self::$masterId = $responseBody->id;

        // GET BY ID
        $request = $this->createRequest('GET');

        $response     = $indexingModelController->getById($request, new Response(), ['id' => self::$masterId]);
        $this->assertSame(200, $response->getStatusCode());

        $responseBody = json_decode((string)$response->getBody());

        $this->assertSame('mon model d indexation', $responseBody->indexingModel->label);
        $this->assertSame(false, $responseBody->indexingModel->default);
        $this->assertSame(false, $responseBody->indexingModel->private);
        $this->assertSame(false, $responseBody->indexingModel->mandatoryFile);
        $this->assertSame('subject', $responseBody->indexingModel->fields[0]->identifier);
        $this->assertSame(true, $responseBody->indexingModel->fields[0]->mandatory);
        $this->assertSame(true, $responseBody->indexingModel->fields[0]->enabled);
        $this->assertSame('tika', $responseBody->indexingModel->fields[0]->default_value);
        $this->assertSame('doctype', $responseBody->indexingModel->fields[1]->identifier);
        $this->assertSame(true, $responseBody->indexingModel->fields[1]->mandatory);
        $this->assertSame(false, $responseBody->indexingModel->fields[1]->enabled);
        $this->assertSame('type_test', $responseBody->indexingModel->fields[1]->default_value);
        $this->assertSame('name', $responseBody->indexingModel->fields[2]->identifier);
        $this->assertSame(true, $responseBody->indexingModel->fields[2]->mandatory);
        $this->assertSame(true, $responseBody->indexingModel->fields[2]->enabled);
        $this->assertSame('massala', $responseBody->indexingModel->fields[2]->default_value);


        //  Errors label
        unset($args['label']);
        $fullRequest = $this->createRequestWithBody('POST', $args);

        $response     = $indexingModelController->create($fullRequest, new Response());
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody());

        $this->assertSame('Body label is empty or not a string or more than 256 characters', $responseBody->errors);

        //  Errors category
        $args['label'] = 'mon model d indexation';
        unset($args['category']);
        $fullRequest = $this->createRequestWithBody('POST', $args);

        $response     = $indexingModelController->create($fullRequest, new Response());
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody());

        $this->assertSame('Body category is empty, not a string or not a valid category', $responseBody->errors);

        $args['category'] = 'invalid_category';
        $fullRequest = $this->createRequestWithBody('POST', $args);

        $response     = $indexingModelController->create($fullRequest, new Response());
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody());

        $this->assertSame('Body category is empty, not a string or not a valid category', $responseBody->errors);

        // Errors fields
        $args['category'] = 'incoming';
        unset($args['fields']);
        $fullRequest = $this->createRequestWithBody('POST', $args);

        $response     = $indexingModelController->create($fullRequest, new Response());
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody());

        $this->assertSame('Body fields is empty or not an array', $responseBody->errors);

        $args['fields'] = [
            [
                'identifier'    => 'name',
                'mandatory'     => true,
                'default_value' => 'massala',
                'unit'          => 'contact'
            ]
        ];
        $fullRequest = $this->createRequestWithBody('POST', $args);

        $response     = $indexingModelController->create($fullRequest, new Response());
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody());

        $this->assertSame('Mandatory \'doctype\' field is missing', $responseBody->errors);

        $args['fields'][] = [
            'identifier'    => 'doctype',
            'mandatory'     => true,
            'default_value' => 'type_test',
            'unit'          => 'mail'
        ];

        $fullRequest = $this->createRequestWithBody('POST', $args);

        $response     = $indexingModelController->create($fullRequest, new Response());
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody());

        $this->assertSame('Mandatory \'subject\' field is missing', $responseBody->errors);


        // Create private model from master model
        // Fail
        $args = [
            'label'     => 'mon sous model d indexation',
            'category'  => 'incoming',
            'private'   => true,
            'master'    => -1,
            'fields'    => [
                [
                    'identifier'    => 'subject',
                    'mandatory'     => true,
                    'default_value' => 'tika',
                    'unit'          => 'mail'
                ], [
                    'identifier'    => 'doctype',
                    'mandatory'     => true,
                    'default_value' => 'type_test',
                    'unit'          => 'mail'
                ]
            ]
        ];
        $fullRequest = $this->createRequestWithBody('POST', $args);

        $response     = $indexingModelController->create($fullRequest, new Response());
        $responseBody = json_decode((string)$response->getBody());

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame('Master model not found', $responseBody->errors);

        $args['master'] = self::$masterId;
        $fullRequest = $this->createRequestWithBody('POST', $args);

        $response     = $indexingModelController->create($fullRequest, new Response());
        $responseBody = json_decode((string)$response->getBody());

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame('Field \'name\' from master model is missing', $responseBody->errors);

        // Success
        $args['fields'][] = [
            'identifier'    => 'name',
            'mandatory'     => true,
            'enabled'       => true,
            'default_value' => 'massala',
            'unit'          => 'contact'
        ];
        $fullRequest = $this->createRequestWithBody('POST', $args);

        $response     = $indexingModelController->create($fullRequest, new Response());
        $responseBody = json_decode((string)$response->getBody());

        $this->assertSame(200, $response->getStatusCode());

        $this->assertIsInt($responseBody->id);
        self::$childId = $responseBody->id;

        $response     = $indexingModelController->create($fullRequest, new Response());
        $responseBody = json_decode((string)$response->getBody());

        $this->assertSame(200, $response->getStatusCode());

        $this->assertIsInt($responseBody->id);
        self::$childId2 = $responseBody->id;
    }

    public function testUpdate()
    {
        $indexingModelController = new IndexingModelController();

        //  UPDATE
        $args = [
            'label'     => 'mon model d indexation modifié',
            'category'  => 'incoming',
            'default'   => false,
            'fields'    => [
                [
                    'identifier'    => 'subject',
                    'mandatory'     => true,
                    'enabled'       => true,
                    'default_value' => 'butter',
                    'unit'          => 'mail'
                ],
                [
                    'identifier'    => 'doctype',
                    'mandatory'     => true,
                    'enabled'       => true,
                    'default_value' => 'type_test2',
                    'unit'          => 'mail'
                ],
                [
                    'identifier'    => 'siret',
                    'mandatory'     => false,
                    'enabled'       => true,
                    'default_value' => 'chicken',
                    'unit'          => 'classement'
                ]
            ],
            'mandatoryFile' => false
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $args);

        $response     = $indexingModelController->update($fullRequest, new Response(), ['id' => self::$masterId]);
        $this->assertSame(204, $response->getStatusCode());

        // GET BY ID
        $request = $this->createRequest('GET');

        $response     = $indexingModelController->getById($request, new Response(), ['id' => self::$masterId]);
        $this->assertSame(200, $response->getStatusCode());

        $responseBody = json_decode((string)$response->getBody());

        $this->assertSame('mon model d indexation modifié', $responseBody->indexingModel->label);
        $this->assertSame(false, $responseBody->indexingModel->default);
        $this->assertSame(false, $responseBody->indexingModel->private);
        $this->assertSame(false, $responseBody->indexingModel->mandatoryFile);
        $this->assertSame('subject', $responseBody->indexingModel->fields[0]->identifier);
        $this->assertSame(true, $responseBody->indexingModel->fields[0]->mandatory);
        $this->assertSame(true, $responseBody->indexingModel->fields[0]->enabled);
        $this->assertSame('butter', $responseBody->indexingModel->fields[0]->default_value);
        $this->assertSame('mail', $responseBody->indexingModel->fields[0]->unit);

        $this->assertSame('doctype', $responseBody->indexingModel->fields[1]->identifier);
        $this->assertSame(true, $responseBody->indexingModel->fields[1]->mandatory);
        $this->assertSame(true, $responseBody->indexingModel->fields[1]->enabled);
        $this->assertSame('type_test2', $responseBody->indexingModel->fields[1]->default_value);
        $this->assertSame('mail', $responseBody->indexingModel->fields[1]->unit);

        $this->assertSame('siret', $responseBody->indexingModel->fields[2]->identifier);
        $this->assertSame(false, $responseBody->indexingModel->fields[2]->mandatory);
        $this->assertSame(true, $responseBody->indexingModel->fields[2]->enabled);
        $this->assertSame('chicken', $responseBody->indexingModel->fields[2]->default_value);
        $this->assertSame('classement', $responseBody->indexingModel->fields[2]->unit);

        // Read child
        $response     = $indexingModelController->getById($request, new Response(), ['id' => self::$childId]);
        $this->assertSame(200, $response->getStatusCode());
        $responseBodyChild = json_decode((string)$response->getBody());

        // check fields of child

        $this->assertSame(3, count($responseBodyChild->indexingModel->fields));

        $foundDoctype = false;
        $foundSubject = false;
        $foundSiret = false;
        foreach ($responseBodyChild->indexingModel->fields as $field) {
            if ($field->identifier == 'subject') {
                $foundSubject = true;

                $this->assertSame(true, $field->mandatory);
                $this->assertSame(true, $field->enabled);
                $this->assertSame('tika', $field->default_value);
                $this->assertSame('mail', $field->unit);
            } elseif ($field->identifier == 'doctype') {
                $foundDoctype = true;

                $this->assertSame(true, $field->mandatory);
                $this->assertSame(true, $field->enabled);
                $this->assertSame('type_test2', $field->default_value);
                $this->assertSame('mail', $field->unit);
            } elseif ($field->identifier == 'siret') {
                $foundSiret = true;

                $this->assertSame(false, $field->mandatory);
                $this->assertSame(true, $field->enabled);
                $this->assertSame('chicken', $field->default_value);
                $this->assertSame('classement', $field->unit);
            }
        }

        $this->assertSame(true, $foundSubject);
        $this->assertSame(true, $foundDoctype);
        $this->assertSame(true, $foundSiret);

        //  Errors
        unset($args['label']);
        $fullRequest = $this->createRequestWithBody('PUT', $args);

        $response     = $indexingModelController->update($fullRequest, new Response(), ['id' => self::$masterId]);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody());

        $this->assertSame('Body label is empty or not a string or more than 256 characters', $responseBody->errors);
    }

    public function testGet()
    {
        $indexingModelController = new IndexingModelController();

        //  GET
        $request = $this->createRequest('GET');

        $response     = $indexingModelController->get($request, new Response());
        $this->assertSame(200, $response->getStatusCode());

        $responseBody = json_decode((string)$response->getBody());

        $this->assertNotEmpty($responseBody->indexingModels);
    }

    public function testGetEntities()
    {
        $indexingModelController = new IndexingModelController();

        //  GET
        $request = $this->createRequest('GET');

        $response     = $indexingModelController->getEntities($request, new Response());
        $responseBody = json_decode((string)$response->getBody());

        $this->assertNotEmpty($responseBody->entities);
        foreach ($responseBody->entities as $value) {
            $this->assertIsInt($value->id);
            $this->assertNotEmpty($value->entity_label);
            $this->assertNotEmpty($value->entity_id);
        }
    }

    public function testEnabled()
    {
        $indexingModelController = new IndexingModelController();

        // GET BY ID
        $request = $this->createRequest('GET');

        $response     = $indexingModelController->getById($request, new Response(), ['id' => self::$masterId]);
        $this->assertSame(200, $response->getStatusCode());

        $responseBody = json_decode((string)$response->getBody());

        $this->assertSame(true, $responseBody->indexingModel->enabled);

        $response     = $indexingModelController->getById($request, new Response(), ['id' => self::$childId]);
        $this->assertSame(200, $response->getStatusCode());

        $responseBody = json_decode((string)$response->getBody());

        $this->assertSame(true, $responseBody->indexingModel->enabled);

        $response     = $indexingModelController->getById($request, new Response(), ['id' => self::$childId2]);
        $this->assertSame(200, $response->getStatusCode());

        $responseBody = json_decode((string)$response->getBody());

        $this->assertSame(true, $responseBody->indexingModel->enabled);

        //  Disable
        $request = $this->createRequest('PUT');
        $response     = $indexingModelController->disable($request, new Response(), ['id' => self::$masterId]);
        $this->assertSame(204, $response->getStatusCode());

        // GET BY ID
        $request = $this->createRequest('GET');

        $response     = $indexingModelController->getById($request, new Response(), ['id' => self::$masterId]);
        $this->assertSame(200, $response->getStatusCode());

        $responseBody = json_decode((string)$response->getBody());

        $this->assertSame(false, $responseBody->indexingModel->enabled);

        $response     = $indexingModelController->getById($request, new Response(), ['id' => self::$childId]);
        $this->assertSame(200, $response->getStatusCode());

        $responseBody = json_decode((string)$response->getBody());

        $this->assertSame(false, $responseBody->indexingModel->enabled);

        $response     = $indexingModelController->getById($request, new Response(), ['id' => self::$childId2]);
        $this->assertSame(200, $response->getStatusCode());

        $responseBody = json_decode((string)$response->getBody());

        $this->assertSame(false, $responseBody->indexingModel->enabled);

        //  Enable
        $request = $this->createRequest('PUT');
        $response     = $indexingModelController->enable($request, new Response(), ['id' => self::$masterId]);
        $this->assertSame(204, $response->getStatusCode());

        // GET BY ID
        $request = $this->createRequest('GET');

        $response     = $indexingModelController->getById($request, new Response(), ['id' => self::$masterId]);
        $this->assertSame(200, $response->getStatusCode());

        $responseBody = json_decode((string)$response->getBody());

        $this->assertSame(true, $responseBody->indexingModel->enabled);

        $response     = $indexingModelController->getById($request, new Response(), ['id' => self::$childId]);
        $this->assertSame(200, $response->getStatusCode());

        $responseBody = json_decode((string)$response->getBody());

        $this->assertSame(true, $responseBody->indexingModel->enabled);

        $response     = $indexingModelController->getById($request, new Response(), ['id' => self::$childId2]);
        $this->assertSame(200, $response->getStatusCode());

        $responseBody = json_decode((string)$response->getBody());

        $this->assertSame(true, $responseBody->indexingModel->enabled);
    }

    public function testDelete()
    {
        $indexingModelController = new IndexingModelController();

        //  DELETE 1 child model
        $request = $this->createRequest('DELETE');

        $response     = $indexingModelController->delete($request, new Response(), ['id' => self::$childId2]);
        $this->assertSame(204, $response->getStatusCode());

        //  GET
        $request = $this->createRequest('GET');

        $response     = $indexingModelController->getById($request, new Response(), ['id' => self::$childId2]);
        $responseBody = json_decode((string)$response->getBody());

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame('Model not found', $responseBody->errors);

        //  DELETE master + child
        $request = $this->createRequest('DELETE');

        $response     = $indexingModelController->delete($request, new Response(), ['id' => self::$masterId]);
        $this->assertSame(204, $response->getStatusCode());

        //  GET
        $request = $this->createRequest('GET');

        $response     = $indexingModelController->getById($request, new Response(), ['id' => self::$masterId]);
        $responseBody = json_decode((string)$response->getBody());

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame('Model not found', $responseBody->errors);

        $response     = $indexingModelController->getById($request, new Response(), ['id' => self::$childId]);
        $responseBody = json_decode((string)$response->getBody());

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame('Model not found', $responseBody->errors);

        //  Errors
        $response     = $indexingModelController->delete($request, new Response(), ['id' => self::$masterId]);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody());

        $this->assertSame('Model not found', $responseBody->errors);
    }
}
