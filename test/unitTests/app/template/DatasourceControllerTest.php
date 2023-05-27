<?php

/**
* Copyright Maarch since 2008 under licence GPLv3.
* See LICENCE.txt file at the root folder for more details.
* This file is part of Maarch software.
*
*/

namespace MaarchCourrier\Tests\app\template;

use Entity\models\ListInstanceModel;
use MaarchCourrier\Tests\CourrierTestCase;
use Note\controllers\NoteController;
use Resource\controllers\ResController;
use Resource\models\ResModel;
use SrcCore\http\Response;
use Template\controllers\DatasourceController;
use User\models\UserModel;

class DatasourceControllerTest extends CourrierTestCase
{
    private static $noteId = null;
    private static $resId = null;

    public function testInit()
    {
        $resController = new ResController();

        //  CREATE
        $GLOBALS['login'] = 'cchaplin';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        $fileContent = file_get_contents('test/unitTests/samples/test.txt');
        $encodedFile = base64_encode($fileContent);

        $argsMailNew = [
            'modelId'          => 1,
            'status'           => 'NEW',
            'encodedFile'      => $encodedFile,
            'format'           => 'txt',
            'confidentiality'  => false,
            'documentDate'     => '2019-01-01 17:18:47',
            'arrivalDate'      => '2019-01-01 17:18:47',
            'processLimitDate' => '2029-01-01',
            'doctype'          => 102,
            'destination'      => 15,
            'initiator'        => 15,
            'subject'          => 'Breaking News : Superman is dead again - PHP unit',
            'typist'           => 19,
            'priority'         => 'poiuytre1357nbvc',
            'followed'         => true,
            'diffusionList'    => [
                [
                    'id'   => 11,
                    'type' => 'user',
                    'mode' => 'dest'
                ]
            ]
        ];

        $fullRequest = $this->createRequestWithBody('POST', $argsMailNew);

        $response     = $resController->create($fullRequest, new Response());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertIsInt($responseBody['resId']);
        self::$resId = $responseBody['resId'];

        $noteController = new NoteController();

        // CREATE Note
        $args = [
            'value'     => "NOTE TEST",
            'entities'  => [],
            'resId'     => self::$resId
        ];
        $fullRequest = $this->createRequestWithBody('POST', $args);

        $response     = $noteController->create($fullRequest, new Response());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertIsInt($responseBody['noteId']);

        self::$noteId = $responseBody['noteId'];
    }

    public function testNotifEvents()
    {
        $dataSourceController   = new DatasourceController();

        $args = [
            'params' => [
                'notification' => 'testNotification',
                'recipient'    => 'testRecipient',
                'events'       => ['event1', 'event2']
            ]
        ];

        $result = $dataSourceController::notifEvents($args);

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);


        $this->assertIsArray($result['notification']);
        $this->assertNotEmpty($result['notification']);
        $this->assertIsString($result['notification'][0]);
        $this->assertSame('testNotification', $result['notification'][0]);

        $this->assertIsArray($result['recipient']);
        $this->assertNotEmpty($result['recipient']);
        $this->assertIsString($result['recipient'][0]);
        $this->assertSame('testRecipient', $result['recipient'][0]);

        $this->assertIsArray($result['events']);
        $this->assertNotEmpty($result['events']);
        $this->assertIsString($result['events'][0]);
        $this->assertSame('event1', $result['events'][0]);
        $this->assertIsString($result['events'][1]);
        $this->assertSame('event2', $result['events'][1]);
    }

    public function testLetterboxEvents()
    {
        $dataSourceController   = new DatasourceController();

        $args = [
            'params' => [
                'notification' => 'testNotification',
                'recipient'    => [
                    'id' => 19
                ],
                'res_view'     => 'res_view_letterbox',
                'events'       => [
                    [
                        'table_name' => 'notes',
                        'record_id'  => self::$noteId
                    ]
                ],
                'maarchUrl'    => 'http://localhost/'
            ]
        ];

        $result = $dataSourceController::letterboxEvents($args);

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);

        $this->assertIsArray($result['sender']);
        $this->assertNotEmpty($result['sender']);

        $this->assertIsArray($result['recipient']);
        $this->assertNotEmpty($result['recipient']);
        $this->assertIsArray($result['recipient'][0]);
        $this->assertNotEmpty($result['recipient'][0]);
        $this->assertIsInt($result['recipient'][0]['id']);
        $this->assertSame(19, $result['recipient'][0]['id']);

        $this->assertIsArray($result['res_letterbox']);
        $this->assertNotEmpty($result['res_letterbox']);
        $this->assertIsArray($result['res_letterbox'][0]);
        $this->assertNotEmpty($result['res_letterbox'][0]);

        $this->assertSame(self::$resId, $result['res_letterbox'][0]['res_id']);
        $this->assertSame(102, $result['res_letterbox'][0]['type_id']);
        $this->assertEmpty($result['res_letterbox'][0]['policy_id']);
        $this->assertEmpty($result['res_letterbox'][0]['cycle_id']);

        $this->assertSame('Convocation', $result['res_letterbox'][0]['type_label']);
        $this->assertSame(1, $result['res_letterbox'][0]['doctypes_first_level_id']);
        $this->assertSame('COURRIERS', $result['res_letterbox'][0]['doctypes_first_level_label']);
        $this->assertSame('#000000', $result['res_letterbox'][0]['doctype_first_level_style']);
        $this->assertSame(1, $result['res_letterbox'][0]['doctypes_second_level_id']);
        $this->assertSame('01. Correspondances', $result['res_letterbox'][0]['doctypes_second_level_label']);
        $this->assertSame('#000000', $result['res_letterbox'][0]['doctype_second_level_style']);

        $this->assertSame('txt', $result['res_letterbox'][0]['format']);
        $this->assertSame(19, $result['res_letterbox'][0]['typist']);
        $this->assertNotEmpty($result['res_letterbox'][0]['creation_date']);
        $this->assertNotEmpty($result['res_letterbox'][0]['modification_date']);

        $this->assertSame('FASTHD_MAN', $result['res_letterbox'][0]['docserver_id']);
        $this->assertIsString($result['res_letterbox'][0]['path']);
        $this->assertIsString($result['res_letterbox'][0]['filename']);
        $this->assertIsString($result['res_letterbox'][0]['fingerprint']);
        $this->assertIsInt($result['res_letterbox'][0]['filesize']);

        $this->assertSame('NEW', $result['res_letterbox'][0]['status']);
        $this->assertEmpty($result['res_letterbox'][0]['work_batch']);
        $this->assertNotEmpty($result['res_letterbox'][0]['doc_date']);
        $this->assertSame('{}', $result['res_letterbox'][0]['external_id']);
        $this->assertEmpty($result['res_letterbox'][0]['departure_date']);
        $this->assertEmpty($result['res_letterbox'][0]['opinion_limit_date']);
        $this->assertEmpty($result['res_letterbox'][0]['barcode']);
        $this->assertSame('PSF', $result['res_letterbox'][0]['initiator']);
        $this->assertSame('PSF', $result['res_letterbox'][0]['destination']);
        $this->assertSame(11, $result['res_letterbox'][0]['dest_user']);
        $this->assertSame('N', $result['res_letterbox'][0]['confidentiality']);
        $this->assertSame('incoming', $result['res_letterbox'][0]['category_id']);
        $this->assertEmpty($result['res_letterbox'][0]['alt_identifier']);
        $this->assertIsString($result['res_letterbox'][0]['admission_date']);
        $this->assertIsString($result['res_letterbox'][0]['process_limit_date']);
        $this->assertEmpty($result['res_letterbox'][0]['closing_date']);
        $this->assertEmpty($result['res_letterbox'][0]['alarm1_date']);
        $this->assertEmpty($result['res_letterbox'][0]['alarm2_date']);
        $this->assertSame('N', $result['res_letterbox'][0]['flag_alarm1']);
        $this->assertSame('N', $result['res_letterbox'][0]['flag_alarm2']);
        $this->assertSame('Breaking News : Superman is dead again - PHP unit', $result['res_letterbox'][0]['subject']);
        $this->assertSame('poiuytre1357nbvc', $result['res_letterbox'][0]['priority']);
        $this->assertEmpty($result['res_letterbox'][0]['locker_user_id']);
        $this->assertEmpty($result['res_letterbox'][0]['locker_time']);
        $this->assertEmpty($result['res_letterbox'][0]['custom_fields']);
        $this->assertSame('Pôle des Services Fonctionnels', $result['res_letterbox'][0]['entity_label']);
        $this->assertSame('Service', $result['res_letterbox'][0]['entitytype']);
        $this->assertSame('http://localhost/dist/index.html#/resources/' . self::$resId . '/content', $result['res_letterbox'][0]['linktodoc']);
        $this->assertSame('http://localhost/dist/index.html#/resources/' . self::$resId, $result['res_letterbox'][0]['linktodetail']);
        $this->assertSame('http://localhost/dist/index.html#/process/users/19/groups/2/baskets/4/resId/' . self::$resId, $result['res_letterbox'][0]['linktoprocess']);

        // Test view res_letterbox table
        $args = [
            'params' => [
                'notification' => 'testNotification',
                'recipient'    => [
                    'id' => 19
                ],
                'res_view'     => 'res_view_letterbox',
                'events'       => [
                    [
                        'table_name' => 'res_letterbox',
                        'record_id'  => self::$resId
                    ]
                ],
                'maarchUrl'    => 'http://localhost/'
            ]
        ];

        $result = $dataSourceController::letterboxEvents($args);

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);

        $this->assertIsArray($result['sender']);
        $this->assertNotEmpty($result['sender']);

        $this->assertIsArray($result['recipient']);
        $this->assertNotEmpty($result['recipient']);
        $this->assertIsArray($result['recipient'][0]);
        $this->assertNotEmpty($result['recipient'][0]);
        $this->assertIsInt($result['recipient'][0]['id']);
        $this->assertSame(19, $result['recipient'][0]['id']);

        $this->assertIsArray($result['res_letterbox']);
        $this->assertNotEmpty($result['res_letterbox']);
        $this->assertIsArray($result['res_letterbox'][0]);
        $this->assertNotEmpty($result['res_letterbox'][0]);

        $this->assertSame(self::$resId, $result['res_letterbox'][0]['res_id']);
        $this->assertSame(102, $result['res_letterbox'][0]['type_id']);
        $this->assertEmpty($result['res_letterbox'][0]['policy_id']);
        $this->assertEmpty($result['res_letterbox'][0]['cycle_id']);

        $this->assertSame('NEW', $result['res_letterbox'][0]['status']);
        $this->assertSame('Breaking News : Superman is dead again - PHP unit', $result['res_letterbox'][0]['subject']);
        $this->assertSame('Pôle des Services Fonctionnels', $result['res_letterbox'][0]['entity_label']);
        $this->assertSame('Service', $result['res_letterbox'][0]['entitytype']);
        $this->assertSame('http://localhost/dist/index.html#/resources/' . self::$resId . '/content', $result['res_letterbox'][0]['linktodoc']);
        $this->assertSame('http://localhost/dist/index.html#/resources/' . self::$resId, $result['res_letterbox'][0]['linktodetail']);
        $this->assertSame('http://localhost/dist/index.html#/process/users/19/groups/2/baskets/4/resId/' . self::$resId, $result['res_letterbox'][0]['linktoprocess']);
    }

    public function testNoteEvents()
    {
        $dataSourceController   = new DatasourceController();

        $args = [
            'params' => [
                'notification' => 'testNotification',
                'recipient'    => [
                    'id'      => 19,
                    'user_id' => 19
                ],
                'res_view'     => 'res_view_letterbox',
                'events'       => [
                    [
                        'table_name' => 'notes',
                        'record_id'  => self::$noteId
                    ]
                ],
                'maarchUrl'    => 'http://localhost/'
            ]
        ];

        $result = $dataSourceController::noteEvents($args);

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);

        $this->assertIsArray($result['notes']);

        $this->assertIsArray($result['recipient']);
        $this->assertNotEmpty($result['recipient']);
        $this->assertIsArray($result['recipient'][0]);
        $this->assertNotEmpty($result['recipient'][0]);
        $this->assertIsInt($result['recipient'][0]['id']);
        $this->assertSame(19, $result['recipient'][0]['id']);

        $this->assertIsArray($result['res_letterbox']);
        $this->assertNotEmpty($result['res_letterbox']);
        $this->assertIsArray($result['res_letterbox'][0]);
        $this->assertNotEmpty($result['res_letterbox'][0]);

        $this->assertSame(self::$resId, $result['res_letterbox'][0]['res_id']);
        $this->assertSame(102, $result['res_letterbox'][0]['type_id']);

        $this->assertSame('txt', $result['res_letterbox'][0]['format']);
        $this->assertSame(19, $result['res_letterbox'][0]['typist']);
        $this->assertNotEmpty($result['res_letterbox'][0]['creation_date']);
        $this->assertNotEmpty($result['res_letterbox'][0]['modification_date']);

        $this->assertSame('FASTHD_MAN', $result['res_letterbox'][0]['docserver_id']);
        $this->assertIsString($result['res_letterbox'][0]['path']);
        $this->assertIsString($result['res_letterbox'][0]['filename']);
        $this->assertIsString($result['res_letterbox'][0]['fingerprint']);
        $this->assertIsInt($result['res_letterbox'][0]['filesize']);

        $this->assertSame('NEW', $result['res_letterbox'][0]['status']);
        $this->assertEmpty($result['res_letterbox'][0]['work_batch']);
        $this->assertNotEmpty($result['res_letterbox'][0]['doc_date']);
        $this->assertSame('{}', $result['res_letterbox'][0]['external_id']);
        $this->assertEmpty($result['res_letterbox'][0]['departure_date']);
        $this->assertEmpty($result['res_letterbox'][0]['opinion_limit_date']);
        $this->assertEmpty($result['res_letterbox'][0]['barcode']);
        $this->assertSame('PSF', $result['res_letterbox'][0]['initiator']);
        $this->assertSame('PSF', $result['res_letterbox'][0]['destination']);
        $this->assertSame(11, $result['res_letterbox'][0]['dest_user']);
        $this->assertSame('N', $result['res_letterbox'][0]['confidentiality']);
        $this->assertSame('incoming', $result['res_letterbox'][0]['category_id']);
        $this->assertEmpty($result['res_letterbox'][0]['alt_identifier']);
        $this->assertIsString($result['res_letterbox'][0]['admission_date']);
        $this->assertIsString($result['res_letterbox'][0]['process_limit_date']);
        $this->assertEmpty($result['res_letterbox'][0]['closing_date']);
        $this->assertEmpty($result['res_letterbox'][0]['alarm1_date']);
        $this->assertEmpty($result['res_letterbox'][0]['alarm2_date']);
        $this->assertSame('N', $result['res_letterbox'][0]['flag_alarm1']);
        $this->assertSame('N', $result['res_letterbox'][0]['flag_alarm2']);
        $this->assertSame('Breaking News : Superman is dead again - PHP unit', $result['res_letterbox'][0]['subject']);
        $this->assertSame('poiuytre1357nbvc', $result['res_letterbox'][0]['priority']);
        $this->assertEmpty($result['res_letterbox'][0]['custom_fields']);
        $this->assertSame('http://localhost/dist/index.html#/resources/' . self::$resId . '/content', $result['res_letterbox'][0]['linktodoc']);
        $this->assertSame('http://localhost/dist/index.html#/resources/' . self::$resId, $result['res_letterbox'][0]['linktodetail']);
        $this->assertSame('http://localhost/dist/index.html#/resources/' . self::$resId . '/content', $result['res_letterbox'][0]['linktoprocess']);

        // Test view res_letterbox table
        $args = [
            'params' => [
                'notification' => 'testNotification',
                'recipient'    => [
                    'id'      => 19,
                    'user_id' => 19
                ],
                'res_view'     => 'res_view_letterbox',
                'events'       => [
                    [
                        'table_name' => 'res_letterbox',
                        'record_id'  => self::$resId
                    ]
                ],
                'maarchUrl'    => 'http://localhost/'
            ]
        ];

        ListInstanceModel::create([
            'res_id'          => self::$resId,
            'sequence'        => 0,
            'item_id'         => 19, // args['params']['recipient']['id']
            'item_type'       => 'user_id',
            'item_mode'       => 'dest',
            'added_by_user'   => $GLOBALS['id'],
            'viewed'          => 0,
            'difflist_type'   => 'entity_id'
        ]);

        $result = $dataSourceController::noteEvents($args);

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);

        $this->assertIsArray($result['recipient']);
        $this->assertNotEmpty($result['recipient']);
        $this->assertIsArray($result['recipient'][0]);
        $this->assertNotEmpty($result['recipient'][0]);
        $this->assertIsInt($result['recipient'][0]['id']);
        $this->assertSame(19, $result['recipient'][0]['id']);

        // $this->assertIsArray($result['notes']);

        // $this->assertSame('http://localhost/dist/index.html#/resources/' . self::$resId . '/content', $result['notes']['linktodoc']);
        // $this->assertSame('http://localhost/dist/index.html#/resources/' . self::$resId, $result['notes']['linktodetail']);
    }

    public function testClean()
    {
        $GLOBALS['login'] = 'cchaplin';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        $request = $this->createRequest('DELETE');

        $noteController = new NoteController();
        $response         = $noteController->delete($request, new Response(), ['id' => self::$noteId]);

        $this->assertSame(204, $response->getStatusCode());

        // Delete resource
        ResModel::delete([
            'where' => ['res_id = ?'],
            'data' => [self::$resId]
        ]);

        $res = ResModel::getById(['resId' => self::$resId, 'select' => ['*']]);
        $this->assertIsArray($res);
        $this->assertEmpty($res);

        $GLOBALS['login'] = 'superadmin';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];
    }
}
