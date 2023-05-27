<?php
/**
* Copyright Maarch since 2008 under licence GPLv3.
* See LICENCE.txt file at the root folder for more details.
* This file is part of Maarch software.

* @brief   DoctypeControllerTest
* @author  dev <dev@maarch.org>
* @ingroup core
*/

namespace MaarchCourrier\Tests\app\doctype;

use Doctype\controllers\DoctypeController;
use Doctype\controllers\FirstLevelController;
use Doctype\controllers\SecondLevelController;
use Resource\controllers\ResController;
use Resource\models\ResModel;
use SrcCore\http\Response;
use SrcCore\models\DatabaseModel;
use MaarchCourrier\Tests\CourrierTestCase;
use User\models\UserModel;

class DoctypeControllerTest extends CourrierTestCase
{
    private static $firstLevelId  = null;
    private static $secondLevelId = null;
    private static $doctypeId     = null;

    public function testGet()
    {
        //  READ
        $request = $this->createRequest('GET');

        $doctypeController = new DoctypeController();
        $response          = $doctypeController->get($request, new Response());
        $responseBody      = json_decode((string)$response->getBody());

        $this->assertNotNull($responseBody->doctypes);
        foreach ($responseBody->doctypes as $value) {
            $this->assertNotNull($value->coll_id);
            $this->assertIsInt($value->type_id);
            $this->assertNotNull($value->description);
            $this->assertNotNull($value->enabled);
            $this->assertIsInt($value->doctypes_first_level_id);
            $this->assertIsInt($value->doctypes_second_level_id);
            $this->assertNotNull($value->retention_final_disposition);
            $this->assertNotNull($value->retention_rule);
            $this->assertIsInt($value->duration_current_use);
            $this->assertIsInt($value->process_delay);
            $this->assertIsInt($value->delay1);
            $this->assertIsInt($value->delay2);
            $this->assertNotNull($value->process_mode);
        }
    }

    public function testReadList()
    {
        //  READ LIST
        $request = $this->createRequest('GET');

        $firstLevelController = new FirstLevelController();
        $response          = $firstLevelController->getTree($request, new Response());
        $responseBody      = json_decode((string)$response->getBody());

        $this->assertNotNull($responseBody->structure);
        $this->assertNotNull($responseBody->structure[0]->id);
        $this->assertNotNull($responseBody->structure[0]->text);
        $this->assertNotNull($responseBody->structure[0]->parent);

        $this->assertIsInt($responseBody->structure[0]->doctypes_first_level_id);
        $this->assertNotNull($responseBody->structure[0]->doctypes_first_level_id);
        $this->assertNotNull($responseBody->structure[0]->doctypes_first_level_label);
        $this->assertNotNull($responseBody->structure[0]->enabled);
    }

    public function testinitDoctypes()
    {
        $request = $this->createRequest('GET');

        $firstLevelController = new FirstLevelController();
        $response          = $firstLevelController->initDoctypes($request, new Response());
        $responseBody      = json_decode((string)$response->getBody());

        $this->assertNotNull($responseBody->firstLevel);
        $this->assertNotNull($responseBody->firstLevel[0]->doctypes_first_level_id);
        $this->assertNotNull($responseBody->firstLevel[0]->doctypes_first_level_label);

        $this->assertNotNull($responseBody->secondLevel);
    }

    public function testCreateFirstLevel()
    {
        $firstLevelController = new FirstLevelController();

        //  CREATE
        $args = [
            'doctypes_first_level_label' => 'testTUfirstlevel',
            'css_style'                    => '#99999',
            'enabled'                    => 'Y',
        ];
        $fullRequest = $this->createRequestWithBody('POST', $args);

        $response     = $firstLevelController->create($fullRequest, new Response());
        $responseBody = json_decode((string)$response->getBody());

        self::$firstLevelId = $responseBody->firstLevelId;

        $this->assertIsInt(self::$firstLevelId);

        // CREATE FAIL
        $args = [
            'doctypes_first_level_label' => '',
            'css_style'                  => '#7777',
            'enabled'                    => 'gaz',
        ];
        $fullRequest = $this->createRequestWithBody('POST', $args);

        $response     = $firstLevelController->create($fullRequest, new Response());
        $responseBody = json_decode((string)$response->getBody());

        $this->assertSame('Invalid doctypes_first_level_label', $responseBody->errors[0]);
    }

    public function testCreateSecondLevel()
    {
        $secondLevelController = new SecondLevelController();

        //  CREATE
        $args = [
            'doctypes_second_level_label' => 'testTUsecondlevel',
            'doctypes_first_level_id'     => self::$firstLevelId,
            'css_style'                   => '#99999',
            'enabled'                     => 'Y',
        ];
        $fullRequest = $this->createRequestWithBody('POST', $args);

        $response     = $secondLevelController->create($fullRequest, new Response());
        $responseBody = json_decode((string)$response->getBody());

        self::$secondLevelId = $responseBody->secondLevelId;

        $this->assertIsInt(self::$secondLevelId);

        // CREATE FAIL
        $args = [
            'doctypes_second_level_label' => '',
            'doctypes_first_level_id'     => '',
            'css_style'                  => '#7777',
            'enabled'                    => 'gaz',
        ];
        $fullRequest = $this->createRequestWithBody('POST', $args);

        $response     = $secondLevelController->create($fullRequest, new Response());
        $responseBody = json_decode((string)$response->getBody());

        $this->assertSame('Invalid doctypes_second_level_label', $responseBody->errors[0]);
        $this->assertSame('Invalid doctypes_first_level_id', $responseBody->errors[1]);
    }

    public function testCreateDoctype()
    {
        $doctypeController = new DoctypeController();

        //  CREATE
        $args = [
            'description'                 => 'testUDoctype',
            'doctypes_second_level_id'    => self::$secondLevelId,
            'retention_final_disposition' => 'destruction',
            'retention_rule'              => 'compta_3_03',
            'duration_current_use'        => '10',
            'process_delay'               => '18',
            'delay1'                      => '10',
            'delay2'                      => '5',
            'process_mode'                => 'NORMAL',
            'template_id'                 => 0,
            'indexes' => [
                0 => [
                    "column"        => "custom_t1",
                    "label"         => "PO#",
                    "type"          => "string",
                    "img"           => "arrow-right",
                    "type_field"    => "input",
                    "default_value" => false
                ],
                1 => [
                    "column"        => "custom_t2",
                    "label"         => "Imput",
                    "type"          => "string",
                    "img"           => "arrow-right",
                    "type_field"    => "input",
                    "default_value" => false,
                    "use"           => true,
                    "mandatory"     => false
                ],
                2 => [
                    "column"        => "custom_f1",
                    "label"         => "Mnt",
                    "type"          => "float",
                    "img"           => "arrow-right",
                    "type_field"    => "input",
                    "default_value" => false
                ],
                3 => [
                    "column"        => "custom_t3",
                    "label"         => "Id/Matricule",
                    "type"          => "string",
                    "img"           => "arrow-right",
                    "type_field"    => "input",
                    "default_value" => false
                ]
            ]
        ];
        $fullRequest = $this->createRequestWithBody('POST', $args);

        $response     = $doctypeController->create($fullRequest, new Response());
        $responseBody = json_decode((string)$response->getBody());

        self::$doctypeId = $responseBody->doctypeId;

        $this->assertIsInt(self::$doctypeId);
        $this->assertNotNull($responseBody->doctypeTree);

        // CREATE FAIL
        $args = [
            'description'                 => '',
            'doctypes_second_level_id'    => '',
            'retention_final_disposition' => '',
            'retention_rule'              => 'compta_3_03',
            'duration_current_use'        => '3',
            'process_delay'               => '',
            'delay1'                      => '',
            'delay2'                      => '',
            'process_mode'                => '',
            'template_id'                 => 0,
        ];
        $fullRequest = $this->createRequestWithBody('POST', $args);

        $response     = $doctypeController->create($fullRequest, new Response());
        $responseBody = json_decode((string)$response->getBody());

        $this->assertSame('Invalid description', $responseBody->errors[0]);
        $this->assertSame('Invalid doctypes_second_level_id value', $responseBody->errors[1]);
        $this->assertSame('Invalid process_delay value', $responseBody->errors[2]);
        $this->assertSame('Invalid delay1 value', $responseBody->errors[3]);
        $this->assertSame('Invalid delay2 value', $responseBody->errors[4]);
    }

    public function testUpdateFirstLevel()
    {
        $firstLevelController = new FirstLevelController();

        //  UPDATE
        $args = [
            'doctypes_first_level_label' => 'testTUfirstlevelUPDATE',
            'css_style'                  => '#7777',
            'enabled'                    => 'Y',
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $args);

        $response     = $firstLevelController->update($fullRequest, new Response(), ["id" => self::$firstLevelId]);
        $responseBody = json_decode((string)$response->getBody());

        $this->assertSame(self::$firstLevelId, $responseBody->firstLevelId->doctypes_first_level_id);
        $this->assertSame('testTUfirstlevelUPDATE', $responseBody->firstLevelId->doctypes_first_level_label);
        $this->assertSame('#7777', $responseBody->firstLevelId->css_style);
        $this->assertSame('Y', $responseBody->firstLevelId->enabled);

        // UPDATE FAIL
        $args = [
            'doctypes_first_level_label' => '',
            'css_style'                  => '#7777',
            'enabled'                    => 'gaz',
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $args);

        $response     = $firstLevelController->update($fullRequest, new Response(), ["id" => 'gaz']);
        $responseBody = json_decode((string)$response->getBody());

        $this->assertSame('Id is not a numeric', $responseBody->errors[0]);
        $this->assertSame('Id gaz does not exists', $responseBody->errors[1]);
        $this->assertSame('Invalid doctypes_first_level_label', $responseBody->errors[2]);
    }

    public function testUpdateSecondLevel()
    {
        $secondLevelController = new SecondLevelController();

        //  UPDATE
        $args = [
            'doctypes_second_level_label' => 'testTUsecondlevelUPDATE',
            'doctypes_first_level_id'     => self::$firstLevelId,
            'css_style'                   => '#7777',
            'enabled'                     => 'Y',
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $args);

        $response     = $secondLevelController->update($fullRequest, new Response(), ["id" => self::$secondLevelId]);
        $responseBody = json_decode((string)$response->getBody());

        $this->assertSame(self::$secondLevelId, $responseBody->secondLevelId->doctypes_second_level_id);
        $this->assertSame('testTUsecondlevelUPDATE', $responseBody->secondLevelId->doctypes_second_level_label);
        $this->assertSame(self::$firstLevelId, $responseBody->secondLevelId->doctypes_first_level_id);
        $this->assertSame('#7777', $responseBody->secondLevelId->css_style);
        $this->assertSame('Y', $responseBody->secondLevelId->enabled);

        // UPDATE FAIL
        $args = [
            'doctypes_second_level_label' => '',
            'doctypes_first_level_id'     => '',
            'css_style'                  => '#7777',
            'enabled'                    => 'gaz',
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $args);

        $response     = $secondLevelController->update($fullRequest, new Response(), ["id" => 'gaz']);
        $responseBody = json_decode((string)$response->getBody());

        $this->assertSame('Id is not a numeric', $responseBody->errors[0]);
        $this->assertSame('Id gaz does not exists', $responseBody->errors[1]);
        $this->assertSame('Invalid doctypes_second_level_label', $responseBody->errors[2]);
        $this->assertSame('Invalid doctypes_first_level_id', $responseBody->errors[3]);
    }

    public function testUpdateDoctype()
    {
        $doctypeController = new DoctypeController();

        //  UPDATE
        $args = [
            'description'                 => 'testUDoctypeUPDATE',
            'doctypes_second_level_id'    => self::$secondLevelId,
            'retention_final_disposition' => 'destruction',
            'retention_rule'              => 'compta_3_03',
            'duration_current_use'        => '12',
            'process_delay'               => '17',
            'delay1'                      => '11',
            'delay2'                      => '6',
            'process_mode'                => 'SVR',
            'template_id'                 => 0,
            'indexes' => [
                0 => [
                    "column"        => "custom_t1",
                    "label"         => "PO#",
                    "type"          => "string",
                    "img"           => "arrow-right",
                    "type_field"    => "input",
                    "default_value" => false,
                    "use"           => true,
                    "mandatory"     => true
                ],
                1 => [
                    "column"        => "custom_t2",
                    "label"         => "Imput",
                    "type"          => "string",
                    "img"           => "arrow-right",
                    "type_field"    => "input",
                    "default_value" => false,
                    "use"           => false,
                    "mandatory"     => false
                ],
                2 => [
                    "column"        => "custom_f1",
                    "label"         => "Mnt",
                    "type"          => "float",
                    "img"           => "arrow-right",
                    "type_field"    => "input",
                    "default_value" => false
                ],
                3 => [
                    "column"        => "custom_t3",
                    "label"         => "Id/Matricule",
                    "type"          => "string",
                    "img"           => "arrow-right",
                    "type_field"    => "input",
                    "default_value" => false
                ]
            ]
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $args);

        $response     = $doctypeController->update($fullRequest, new Response(), ["id" => self::$doctypeId]);
        $responseBody = json_decode((string)$response->getBody());

        $this->assertSame(self::$doctypeId, $responseBody->doctype->type_id);
        $this->assertNotNull($responseBody->doctypeTree);

        // UPDATE FAIL
        $args = [
            'description'                 => '',
            'doctypes_second_level_id'    => '',
            'retention_final_disposition' => '',
            'retention_rule'              => 'compta_3_03',
            'duration_current_use'        => '3',
            'process_delay'               => '',
            'delay1'                      => '',
            'delay2'                      => '',
            'process_mode'                => '',
            'template_id'                 => 0,
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $args);

        $response     = $doctypeController->update($fullRequest, new Response(), ["id" => 'gaz']);
        $responseBody = json_decode((string)$response->getBody());

        $this->assertSame('type_id is not a numeric', $responseBody->errors[0]);
        $this->assertSame('Id gaz does not exists', $responseBody->errors[1]);
        $this->assertSame('Invalid description', $responseBody->errors[2]);
        $this->assertSame('Invalid doctypes_second_level_id value', $responseBody->errors[3]);
        $this->assertSame('Invalid process_delay value', $responseBody->errors[4]);
        $this->assertSame('Invalid delay1 value', $responseBody->errors[5]);
        $this->assertSame('Invalid delay2 value', $responseBody->errors[6]);
    }

    public function testRead()
    {
        //  READ FIRST LEVEL
        $request = $this->createRequest('GET');

        $firstLevelController = new FirstLevelController();
        $response          = $firstLevelController->getById($request, new Response(), ["id" => self::$firstLevelId]);
        $responseBody      = json_decode((string)$response->getBody());

        $this->assertSame(self::$firstLevelId, $responseBody->firstLevel->doctypes_first_level_id);
        $this->assertSame('testTUfirstlevelUPDATE', $responseBody->firstLevel->doctypes_first_level_label);
        $this->assertSame('#7777', $responseBody->firstLevel->css_style);
        $this->assertSame(true, $responseBody->firstLevel->enabled);

        // READ FIRST LEVEL FAIL
        $response          = $firstLevelController->getById($request, new Response(), ["id" => 'GAZ']);
        $responseBody      = json_decode((string)$response->getBody());

        $this->assertSame('wrong format for id', $responseBody->errors);

        //  READ SECOND LEVEL
        $secondLevelController = new SecondLevelController();
        $response     = $secondLevelController->getById($request, new Response(), ["id" => self::$secondLevelId]);
        $responseBody = json_decode((string)$response->getBody());

        $this->assertSame(self::$secondLevelId, $responseBody->secondLevel->doctypes_second_level_id);
        $this->assertSame('testTUsecondlevelUPDATE', $responseBody->secondLevel->doctypes_second_level_label);
        $this->assertSame(self::$firstLevelId, $responseBody->secondLevel->doctypes_first_level_id);
        $this->assertSame(true, $responseBody->secondLevel->enabled);

        // READ SECOND LEVEL FAIL
        $response          = $secondLevelController->getById($request, new Response(), ["id" => 'GAZ']);
        $responseBody      = json_decode((string)$response->getBody());

        $this->assertSame('wrong format for id', $responseBody->errors);

        // READ DOCTYPE
        $doctypeController = new DoctypeController();
        $response     = $doctypeController->getById($request, new Response(), ["id" => self::$doctypeId]);
        $responseBody = json_decode((string)$response->getBody());

        $this->assertSame(self::$doctypeId, $responseBody->doctype->type_id);
        $this->assertSame('testUDoctypeUPDATE', $responseBody->doctype->description);
        $this->assertSame(self::$firstLevelId, $responseBody->doctype->doctypes_first_level_id);
        $this->assertSame(self::$secondLevelId, $responseBody->doctype->doctypes_second_level_id);
        $this->assertSame('destruction', $responseBody->doctype->retention_final_disposition);
        $this->assertSame('compta_3_03', $responseBody->doctype->retention_rule);
        $this->assertSame(12, $responseBody->doctype->duration_current_use);
        $this->assertSame(17, $responseBody->doctype->process_delay);
        $this->assertSame(11, $responseBody->doctype->delay1);
        $this->assertSame(6, $responseBody->doctype->delay2);
        $this->assertSame('SVR', $responseBody->doctype->process_mode);
        $this->assertNotNull($responseBody->secondLevel);
        $this->assertSame(null, $responseBody->doctype->template_id);
        $this->assertNotNull($responseBody->models);

        // READ DOCTYPE FAIL
        $response          = $doctypeController->getById($request, new Response(), ["id" => 'GAZ']);
        $responseBody      = json_decode((string)$response->getBody());

        $this->assertSame('wrong format for id', $responseBody->errors);
    }

    public function testDeleteRedirectDoctype()
    {
        $doctypeController = new DoctypeController();

        //  CREATE
        $args = [
            'description'                 => 'testUDoctype',
            'doctypes_first_level_id'     => self::$firstLevelId,
            'doctypes_second_level_id'    => self::$secondLevelId,
            'retention_final_disposition' => 'destruction',
            'retention_rule'              => 'compta_3_03',
            'duration_current_use'        => '10',
            'process_delay'               => '18',
            'delay1'                      => '10',
            'delay2'                      => '5',
            'process_mode'                => 'NORMAL',
            'template_id'                 => 0,
            'indexes' => [
                0 => [
                    "column"        => "custom_t1",
                    "label"         => "PO#",
                    "type"          => "string",
                    "img"           => "arrow-right",
                    "type_field"    => "input",
                    "default_value" => false,
                    "use"           => false,
                    "mandatory"     => false
                ],
                1 => [
                    "column"        => "custom_t2",
                    "label"         => "Imput",
                    "type"          => "string",
                    "img"           => "arrow-right",
                    "type_field"    => "input",
                    "default_value" => false,
                    "use"           => true,
                    "mandatory"     => true
                ],
                2 => [
                    "column"        => "custom_f1",
                    "label"         => "Mnt",
                    "type"          => "float",
                    "img"           => "arrow-right",
                    "type_field"    => "input",
                    "default_value" => false
                ],
                3 => [
                    "column"        => "custom_t3",
                    "label"         => "Id/Matricule",
                    "type"          => "string",
                    "img"           => "arrow-right",
                    "type_field"    => "input",
                    "default_value" => false
                ]
            ]
        ];
        $fullRequest = $this->createRequestWithBody('POST', $args);

        $response     = $doctypeController->create($fullRequest, new Response());
        $responseBody = json_decode((string)$response->getBody());

        $doctypeId = $responseBody->doctypeId;

        $resController = new ResController();

        //  CREATE RESOURCE
        $GLOBALS['login'] = 'cchaplin';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        $fileContent = file_get_contents('test/unitTests/samples/test.txt');
        $encodedFile = base64_encode($fileContent);

        $args = [
            'modelId'       => 1,
            'status'        => 'NEW',
            'encodedFile'   => $encodedFile,
            'format'        => 'txt',
            'confidentiality'   => false,
            'documentDate'  => '2019-01-01 17:18:47',
            'arrivalDate'   => '2019-01-01 17:18:47',
            'processLimitDate'  => '2029-01-01',
            'doctype'       => $doctypeId,
            'destination'   => 15,
            'initiator'     => 15,
            'subject'       => 'Breaking News : Aquaman is a fish - PHP unit',
            'typist'        => 19,
            'priority'      => 'poiuytre1357nbvc',
        ];

        $fullRequest = $this->createRequestWithBody('POST', $args);
        $response     = $resController->create($fullRequest, new Response());

        $responseBody = json_decode((string)$response->getBody());

        $resId = $responseBody->resId;

        $GLOBALS['login'] = 'superadmin';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];


        //  CAN NOT DELETE
        $args = [];
        $fullRequest = $this->createRequestWithBody('DELETE', $args);

        $response     = $doctypeController->delete($fullRequest, new Response(), ["id" => $doctypeId]);
        $responseBody = json_decode((string)$response->getBody());

        $this->assertSame(1, $responseBody->deleted);
        $this->assertNull($responseBody->doctypeTree);
        $this->assertNotNull($responseBody->doctypes);

        $args = [
            "new_type_id" => self::$doctypeId
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $args);

        $response     = $doctypeController->deleteRedirect($fullRequest, new Response(), ["id" => $doctypeId]);
        $responseBody = json_decode((string)$response->getBody());

        $this->assertNotNull($responseBody->doctypeTree);

        $res = ResModel::getById(['resId' => $resId, 'select' => ['*']]);
        $this->assertSame(self::$doctypeId, $res['type_id']);

        DatabaseModel::delete([
            'table' => 'res_letterbox',
            'where' => ['type_id = ?'],
            'data'  => [self::$doctypeId]
        ]);

        // DELETE REDIRECT FAIL
        $args = [
            "new_type_id" => 'gaz'
        ];
        $fullRequest = $this->createRequestWithBody('DELETE', $args);
        $response     = $doctypeController->deleteRedirect($fullRequest, new Response(), ["id" => $doctypeId]);
        $responseBody = json_decode((string)$response->getBody());

        $this->assertSame('wrong format for new_type_id', $responseBody->errors);

        $GLOBALS['login'] = 'superadmin';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];
    }

    public function testDeleteDoctype()
    {
        $doctypeController = new DoctypeController();

        //  DELETE
        $args = [];
        $fullRequest = $this->createRequestWithBody('DELETE', $args);

        $response     = $doctypeController->delete($fullRequest, new Response(), ["id" => self::$doctypeId]);
        $responseBody = json_decode((string)$response->getBody());

        $this->assertSame(0, $responseBody->deleted);
        $this->assertNotNull($responseBody->doctypeTree);
        $this->assertNull($responseBody->doctypes);

        //  DELETE FAIL
        $response     = $doctypeController->delete($fullRequest, new Response(), ["id" => 'gaz']);
        $responseBody = json_decode((string)$response->getBody());

        $this->assertSame('Id is not a numeric', $responseBody->errors);
    }

    public function testDeleteSecondLevel()
    {
        $secondLevelController = new SecondLevelController();

        //  DELETE
        $args = [];
        $fullRequest = $this->createRequestWithBody('DELETE', $args);

        $response     = $secondLevelController->delete($fullRequest, new Response(), ["id" => self::$secondLevelId]);
        $responseBody = json_decode((string)$response->getBody());

        $this->assertSame(self::$secondLevelId, $responseBody->secondLevelDeleted->doctypes_second_level_id);
        $this->assertSame(self::$firstLevelId, $responseBody->secondLevelDeleted->doctypes_first_level_id);
        $this->assertSame('testTUsecondlevelUPDATE', $responseBody->secondLevelDeleted->doctypes_second_level_label);
        $this->assertSame('#7777', $responseBody->secondLevelDeleted->css_style);
        $this->assertSame('N', $responseBody->secondLevelDeleted->enabled);

        //  DELETE FAIL
        $response     = $secondLevelController->delete($fullRequest, new Response(), ["id" => 'gaz']);
        $responseBody = json_decode((string)$response->getBody());

        $this->assertSame('Id is not a numeric', $responseBody->errors);
    }

    public function testDeleteFirstLevel()
    {
        $firstLevelController = new FirstLevelController();

        //  DELETE
        $args = [];
        $fullRequest = $this->createRequestWithBody('DELETE', $args);

        $response     = $firstLevelController->delete($fullRequest, new Response(), ["id" => self::$firstLevelId]);
        $responseBody = json_decode((string)$response->getBody());

        $this->assertSame(self::$firstLevelId, $responseBody->firstLevelDeleted->doctypes_first_level_id);
        $this->assertSame('testTUfirstlevelUPDATE', $responseBody->firstLevelDeleted->doctypes_first_level_label);
        $this->assertSame('#7777', $responseBody->firstLevelDeleted->css_style);
        $this->assertSame('N', $responseBody->firstLevelDeleted->enabled);

        //  DELETE FAIL
        $response     = $firstLevelController->delete($fullRequest, new Response(), ["id" => 'gaz']);
        $responseBody = json_decode((string)$response->getBody());

        $this->assertSame('Id is not a numeric', $responseBody->errors);
    }

    public static function tearDownAfterClass(): void
    {
        DatabaseModel::delete([
            'table' => 'doctypes_first_level',
            'where' => ['doctypes_first_level_id = ?'],
            'data'  => [self::$firstLevelId]
        ]);
        DatabaseModel::delete([
            'table' => 'doctypes_second_level',
            'where' => ['doctypes_second_level_id = ?'],
            'data'  => [self::$secondLevelId]
        ]);
    }
}
