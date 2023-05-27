<?php
/**
* Copyright Maarch since 2008 under licence GPLv3.
* See LICENCE.txt file at the root folder for more details.
* This file is part of Maarch software.

*
* @brief   NotificationsScheduleControllerTest
*
* @author  dev <dev@maarch.org>
* @ingroup core
*/

namespace MaarchCourrier\Tests\app\notification;

use MaarchCourrier\Tests\CourrierTestCase;
use Notification\controllers\NotificationScheduleController;
use SrcCore\http\Response;

class NotificationScheduleControllerTest extends CourrierTestCase
{
    public function testCreateScript()
    {
        $NotificationScheduleController = new NotificationScheduleController();

        // CREATE FAIL
        $args = [
            'notification_sid' => 'gaz',
            'notification_id' => '',
        ];
        $fullRequest = $this->createRequestWithBody('POST', $args);

        $response = $NotificationScheduleController->createScriptNotification($fullRequest, new Response());
        $responseBody = json_decode((string) $response->getBody());

        $this->assertSame('notification_sid is not a numeric', $responseBody->errors[0]);
        $this->assertSame('one of arguments is empty', $responseBody->errors[1]);

        // CREATE
        $args = [
            'notification_sid'  => 1,
            'notification_id'   => 'USERS',
            'event_id'          => 'users'
        ];
        $fullRequest = $this->createRequestWithBody('POST', $args);

        $response = $NotificationScheduleController->createScriptNotification($fullRequest, new Response());
        $responseBody = json_decode((string) $response->getBody());

        $this->assertSame(true, $responseBody);
    }

    public function testSaveCrontab()
    {
        $NotificationScheduleController = new NotificationScheduleController();

        $request = $this->createRequest('GET');

        $response = $NotificationScheduleController->get($request, new Response());
        $responseBody = json_decode((string) $response->getBody(), true);

        // CREATE FAIL
        $args = $responseBody['crontab'];

        $corePath = dirname(__FILE__, 5).'/';
        $newCrontab = [
            'm' => 12,
            'h' => 23,
            'dom' => '',
            'mon' => '*',
            'dow' => '*',
            'cmd' => $corePath.'bin/notification/scripts/notification_testtu.sh',
            'state' => 'normal',
        ];
        array_push($args, $newCrontab);

        $fullRequest = $this->createRequestWithBody('POST', $args);

        $response = $NotificationScheduleController->create($fullRequest, new Response());
        $responseBodyFail = json_decode((string) $response->getBody());

        $this->assertSame('wrong format for dom', $responseBodyFail->errors[ count($responseBodyFail->errors) - 1 ]);

        // CREATE
        $args = $responseBody['crontab'];

        $corePath = dirname(__FILE__, 5).'/';
        $newCrontab = [
            'm' => 12,
            'h' => 23,
            'dom' => '*',
            'mon' => '*',
            'dow' => '*',
            'cmd' => $corePath.'bin/notification/scripts/notification_testtu.sh',
            'state' => 'normal',
        ];
        array_push($args, $newCrontab);

        $fullRequest = $this->createRequestWithBody('POST', $args);
        $response = $NotificationScheduleController->create($fullRequest, new Response());
        $responseBodyCreate = json_decode((string) $response->getBody());

        $this->assertSame(true, $responseBodyCreate);
    }

    public function testReadAll()
    {
        $request = $this->createRequest('GET');

        $NotificationScheduleController = new NotificationScheduleController();
        $response = $NotificationScheduleController->get($request, new Response());
        $responseBody = json_decode((string) $response->getBody());

        $this->assertIsArray($responseBody->crontab);
        $this->assertIsArray($responseBody->authorizedNotification);
        $this->assertNotNull($responseBody->authorizedNotification);
        $this->assertNotNull($responseBody->crontab);
    }

    public function testUpdateCrontab()
    {
        $NotificationScheduleController = new NotificationScheduleController();
        $request = $this->createRequest('GET');

        $response = $NotificationScheduleController->get($request, new Response());
        $responseBody = json_decode((string) $response->getBody());

        //  UPDATE
        $args = $responseBody->crontab;

        $corePath = dirname(__FILE__, 5).'/';

        $args[count($args) - 1] = [
            'm' => 35,
            'h' => 22,
            'dom' => '*',
            'mon' => '*',
            'dow' => '*',
            'cmd' => $corePath.'bin/notification/scripts/notification_testtu.sh',
            'state' => 'normal',
        ];
        $fullRequest = $this->createRequestWithBody('POST', $args);

        $response = $NotificationScheduleController->create($fullRequest, new Response());
        $responseBody = json_decode((string) $response->getBody());

        $this->assertSame(true, $responseBody);

        $request = $this->createRequest('GET');

        $response = $NotificationScheduleController->get($request, new Response());
        $responseBody = json_decode((string) $response->getBody());

        $this->assertSame('35', $responseBody->crontab[count($responseBody->crontab) - 1]->m);
        $this->assertSame('22', $responseBody->crontab[count($responseBody->crontab) - 1]->h);
    }

    public function testDelete()
    {
        // DELETE FAIL
        $NotificationScheduleController = new NotificationScheduleController();

        $request = $this->createRequest('GET');

        $response = $NotificationScheduleController->get($request, new Response());
        $responseBody = json_decode((string) $response->getBody(), true);

        $args = $responseBody['crontab'];

        foreach ($args as $id => $value) {
            if ($value['cmd'] == dirname(__FILE__, 5).'/'.'bin/notification/scripts/notification_testtu.sh') {
                $args[$id]['state'] = 'hidden';
            }
        }

        $fullRequest = $this->createRequestWithBody('POST', $args);

        $response = $NotificationScheduleController->create($fullRequest, new Response());
        $responseBodyFail = json_decode((string) $response->getBody());

        $this->assertSame('Problem with crontab', $responseBodyFail->errors);

        // DELETE
        $args = $responseBody['crontab'];

        foreach ($args as $id => $value) {
            if ($value['cmd'] == dirname(__FILE__, 5).'/'.'bin/notification/scripts/notification_testtu.sh') {
                $args[$id]['state'] = 'deleted';
            }
        }

        $fullRequest = $this->createRequestWithBody('POST', $args);

        $response = $NotificationScheduleController->create($fullRequest, new Response());
        $responseBody = json_decode((string) $response->getBody());

        $this->assertSame(true, $responseBody);

        unlink('bin/notification/scripts/notification_USERS.sh');
    }
}
