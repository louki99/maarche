<?php

/**
* Copyright Maarch since 2008 under licence GPLv3.
* See LICENCE.txt file at the root folder for more details.
* This file is part of Maarch software.
*
*/

namespace MaarchCourrier\Tests\app\configuration;

use Configuration\controllers\ConfigurationController;
use MaarchCourrier\Tests\CourrierTestCase;
use SrcCore\http\Response;

class ConfigurationControllerTest extends CourrierTestCase
{
    public function testUpdate()
    {
        $configurationController = new ConfigurationController();

        //  UPDATE
        $args = [
            'type'       => 'smtp',
            'host'       => 'smtp.outlook.com',
            'port'       => '45',
            'auth'       => true,
            'user'       => 'user@test.com',
            'password'   => '12345',
            'secure'     => 'ssl',
            'from'       => 'dev.maarch@maarch.org',
            'charset'    => 'utf-8',
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $args);

        $response     = $configurationController->update($fullRequest, new Response(), ['privilege' => 'admin_email_server']);
        $responseBody = json_decode((string)$response->getBody());

        $this->assertSame('success', $responseBody->success);

        //  READ
        $request = $this->createRequest('GET');
        $response       = $configurationController->getByPrivilege($request, new Response(), ['privilege' => 'admin_email_server']);
        $responseBody   = json_decode((string)$response->getBody());

        $this->assertNotNull($responseBody->configuration);
        $this->assertIsInt($responseBody->configuration->id);
        $this->assertSame('admin_email_server', $responseBody->configuration->privilege);
        $this->assertNotNull($responseBody->configuration->value);

        $jsonTest = json_encode(
            [
                'type'       => 'smtp',
                'host'       => 'smtp.outlook.com',
                'port'       => '45',
                'auth'       => true,
                'user'       => 'user@test.com',
                'password'       => '',
                'secure'     => 'ssl',
                'from'       => 'dev.maarch@maarch.org',
                'charset'    => 'utf-8',
                'passwordAlreadyExists' => true
            ]
        );

        $this->assertJsonStringEqualsJsonString($jsonTest, json_encode($responseBody->configuration->value));

        //  UPDATE auth false
        $args = [
            'type'       => 'smtp',
            'host'       => 'smtp.outlook.com',
            'port'       => '231',
            'auth'       => false,
            'user'       => '',
            'password'   => '',
            'secure'     => 'tls',
            'from'       => 'dev.maarch@maarch.org',
            'charset'    => 'utf-8',
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $args);

        $response     = $configurationController->update($fullRequest, new Response(), ['privilege' => 'admin_email_server']);
        $responseBody = json_decode((string)$response->getBody());

        $this->assertSame('success', $responseBody->success);

        $request = $this->createRequest('GET');
        $response       = $configurationController->getByPrivilege($request, new Response(), ['privilege' => 'admin_email_server']);
        $responseBody   = json_decode((string)$response->getBody());

        $this->assertNotNull($responseBody->configuration);
        $this->assertIsInt($responseBody->configuration->id);
        $this->assertSame('admin_email_server', $responseBody->configuration->privilege);
        $this->assertNotNull($responseBody->configuration->value);

        $jsonTest = json_encode(
            [
                'type'       => 'smtp',
                'host'       => 'smtp.outlook.com',
                'port'       => '231',
                'auth'       => false,
                'user'       => '',
                'password'       => '',
                'secure'     => 'tls',
                'from'       => 'dev.maarch@maarch.org',
                'charset'    => 'utf-8',
                'passwordAlreadyExists' => false
            ]
        );

        $this->assertJsonStringEqualsJsonString($jsonTest, json_encode($responseBody->configuration->value));


        //  UPDATE SENDMAIL
        $args = [
            'type' => 'sendmail',
            'from' => 'notifications@maarch.org'
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $args);

        $response     = $configurationController->update($fullRequest, new Response(), ['privilege' => 'admin_email_server']);
        $responseBody = json_decode((string)$response->getBody());

        $this->assertSame('success', $responseBody->success);

        $request = $this->createRequest('GET');
        $response       = $configurationController->getByPrivilege($request, new Response(), ['privilege' => 'admin_email_server']);
        $responseBody   = json_decode((string)$response->getBody());

        $this->assertNotNull($responseBody->configuration);
        $this->assertIsInt($responseBody->configuration->id);
        $this->assertSame('admin_email_server', $responseBody->configuration->privilege);
        $this->assertNotNull($responseBody->configuration->value);

        $jsonTest = json_encode(
            [
                'type'                  => 'sendmail',
                'from'                  => 'notifications@maarch.org',
                'passwordAlreadyExists' => false,
                'charset'               => 'utf-8'
            ]
        );

        $this->assertJsonStringEqualsJsonString($jsonTest, json_encode($responseBody->configuration->value));

        //  UPDATE ERROR
        $args = [
            'type' => 'sendmail'
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $args);

        $response     = $configurationController->update($fullRequest, new Response(), ['privilege' => 'admin_email_server_fail']);
        $responseBody = json_decode((string)$response->getBody());

        $this->assertSame('Unknown privilege', $responseBody->errors);

        //  UPDATE ERROR
        $args = [
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $args);

        $response     = $configurationController->update($fullRequest, new Response(), ['privilege' => 'admin_email_server']);
        $responseBody = json_decode((string)$response->getBody());

        $this->assertSame('Configuration type is missing', $responseBody->errors);

        //  UPDATE ERROR
        $args = [
            'type'       => 'smtp',
            'port'       => '231',
            'auth'       => 'aze',
            'user'       => '',
            'password'   => '',
            'secure'     => 'tls',
            'from'       => 'dev.maarch@maarch.org',
            'charset'    => 'utf-8',
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $args);

        $response     = $configurationController->update($fullRequest, new Response(), ['privilege' => 'admin_email_server']);
        $responseBody = json_decode((string)$response->getBody());

        $this->assertSame('Configuration data is missing or not well formatted', $responseBody->errors);
    }

    public function testReset()
    {
        $configurationController = new ConfigurationController();

        //  UPDATE
        $args = [
            'type'       => 'smtp',
            'host'       => 'smtp.gmail.com',
            'port'       => '465',
            'auth'       => true,
            'user'       => 'name@maarch.org',
            'password'   => '12345',
            'secure'     => 'ssl',
            'from'       => 'notifications@maarch.org',
            'charset'    => 'utf-8',
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $args);

        $response     = $configurationController->update($fullRequest, new Response(), ['privilege' => 'admin_email_server']);
        $responseBody = json_decode((string)$response->getBody());

        $this->assertSame('success', $responseBody->success);

        //  READ
        $request = $this->createRequest('GET');
        $response       = $configurationController->getByPrivilege($request, new Response(), ['privilege' => 'admin_email_server']);
        $responseBody   = json_decode((string)$response->getBody());

        $this->assertNotNull($responseBody->configuration);
        $this->assertIsInt($responseBody->configuration->id);
        $this->assertSame('admin_email_server', $responseBody->configuration->privilege);
        $this->assertNotNull($responseBody->configuration->value);

        $jsonTest = json_encode(
            [
                'type'       => 'smtp',
                'host'       => 'smtp.gmail.com',
                'port'       => '465',
                'auth'       => true,
                'user'       => 'name@maarch.org',
                'password'       => '',
                'secure'     => 'ssl',
                'from'       => 'notifications@maarch.org',
                'charset'    => 'utf-8',
                'passwordAlreadyExists' => true
            ]
        );

        $this->assertJsonStringEqualsJsonString($jsonTest, json_encode($responseBody->configuration->value));

        //  UPDATE TEST REST WITHOUT PASSWORD
        $args = [
            'type'       => 'smtp',
            'host'       => 'smtp.gmail.com',
            'port'       => '465',
            'auth'       => true,
            'user'       => 'name@maarch.org',
            'password'   => '',
            'secure'     => 'ssl',
            'from'       => 'notifications@maarch.org',
            'charset'    => 'utf-8',
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $args);

        $response     = $configurationController->update($fullRequest, new Response(), ['privilege' => 'admin_email_server']);
        $responseBody = json_decode((string)$response->getBody());

        $this->assertSame('success', $responseBody->success);

        //  READ
        $request = $this->createRequest('GET');
        $response       = $configurationController->getByPrivilege($request, new Response(), ['privilege' => 'admin_email_server']);
        $responseBody   = json_decode((string)$response->getBody());

        $this->assertNotNull($responseBody->configuration);
        $this->assertIsInt($responseBody->configuration->id);
        $this->assertSame('admin_email_server', $responseBody->configuration->privilege);
        $this->assertNotNull($responseBody->configuration->value);

        $jsonTest = json_encode(
            [
                'type'       => 'smtp',
                'host'       => 'smtp.gmail.com',
                'port'       => '465',
                'auth'       => true,
                'user'       => 'name@maarch.org',
                'password'       => '',
                'secure'     => 'ssl',
                'from'       => 'notifications@maarch.org',
                'charset'    => 'utf-8',
                'passwordAlreadyExists' => true
            ]
        );

        $this->assertJsonStringEqualsJsonString($jsonTest, json_encode($responseBody->configuration->value));
    }
}
