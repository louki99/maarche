<?php
/**
* Copyright Maarch since 2008 under licence GPLv3.
* See LICENCE.txt file at the root folder for more details.
* This file is part of Maarch software.

* @brief   AuthenticationControllerTest
* @author  dev <dev@maarch.org>
* @ingroup core
*/

namespace MaarchCourrier\Tests\core;

use SrcCore\controllers\AuthenticationController;
use SrcCore\controllers\PasswordController;
use SrcCore\http\Response;
use MaarchCourrier\Tests\CourrierTestCase;

class AuthenticationControllerTest extends CourrierTestCase
{
    public function testAuthentication()
    {
        $_SERVER['PHP_AUTH_USER'] = 'superadmin';
        $_SERVER['PHP_AUTH_PW'] = 'superadmin';
        $response = AuthenticationController::authentication();

        $this->assertNotEmpty($response);
        $this->assertSame(23, $response);
    }

    public function testAuthenticate()
    {
        $authenticationController = new AuthenticationController();

        $args = [
            'login'     => 'bbain',
            'password'  => 'maarch'
        ];
        $fullRequest = $this->createRequestWithBody('POST', $args);

        $response     = $authenticationController->authenticate($fullRequest, new Response());
        $this->assertSame(204, $response->getStatusCode());
        $headers = $response->getHeaders();
        $this->assertArrayHasKey('Token', $headers);
        $this->assertArrayHasKey('Refresh-Token', $headers);

        //  ERRORS
        $args = [
            'login'     => 'bbain',
            'password'  => 'maarche'
        ];
        $fullRequest = $this->createRequestWithBody('POST', $args);
        $response     = $authenticationController->authenticate($fullRequest, new Response());
        $this->assertSame(401, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody());

        $this->assertSame('Authentication Failed', $responseBody->errors);

        $args = [
            'logi'     => 'bbain',
            'password'  => 'maarche'
        ];

        $fullRequest = $this->createRequestWithBody('POST', $args);
        $response     = $authenticationController->authenticate($fullRequest, new Response());
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody());

        $this->assertSame('Bad Request', $responseBody->errors);

        // MUST CONNECT WITH SUPERADMIN
        $args = [
            'login'     => 'superadmin',
            'password'  => 'superadmin'
        ];
        $fullRequest = $this->createRequestWithBody('POST', $args);
        $response     = $authenticationController->authenticate($fullRequest, new Response());
        $this->assertSame(204, $response->getStatusCode());
    }

    public function testIsRouteAvailable()
    {
        $response = AuthenticationController::isRouteAvailable(['userId' => 23, 'currentRoute' => '/actions', 'currentMethod' => 'POST']);
        $this->assertSame(true, $response['isRouteAvailable']);
    }

    public function testHandleFailedAuthentication()
    {
        $passwordController = new PasswordController();

        $request = $this->createRequest('GET');

        $response     = $passwordController->getRules($request, new Response());
        $responseBody = json_decode((string)$response->getBody());

        // reset rules
        $rules = (array)$responseBody->rules;
        foreach ($rules as $key => $rule) {
            $rules[$key] = (array)$rule;
            $rule = (array)$rule;
            if ($rule['label'] == 'complexitySpecial' || $rule['label'] == 'complexityNumber' || $rule['label'] == 'complexityUpper') {
                $rules[$key]['enabled'] = false;
            }
            if ($rule['label'] == 'minLength') {
                $rules[$key]['value'] = 6;
                $rules[$key]['enabled'] = true;
            }
            if ($rule['label'] == 'lockAttempts') {
                $lockAttempts = $rule['value'];
                $rules[$key]['enabled'] = true;
            }
            if ($rule['label'] == 'lockTime') {
                $lockTime = $rule['value'];
                $rules[$key]['enabled'] = true;
            }
        }

        if (!empty($lockAttempts) && !empty($lockTime)) {
            $fullRequest = $this->createRequestWithBody('PUT', ['rules' => $rules]);
            $passwordController->updateRules($fullRequest, new Response());

            \User\models\UserModel::update([
                'set'   => ['failed_authentication' => 0, 'locked_until' => null],
                'where' => ['user_id = ?'],
                'data'  => ['superadmin']
            ]);

            for ($i = 1; $i < $lockAttempts; $i++) {
                $response = AuthenticationController::handleFailedAuthentication(['userId' => $GLOBALS['id']]);
                $this->assertSame(true, $response);
            }
            $response = AuthenticationController::handleFailedAuthentication(['userId' => $GLOBALS['id']]);
            $this->assertSame(true, $response['accountLocked']);
            $response = AuthenticationController::handleFailedAuthentication(['userId' => $GLOBALS['id']]);
            $this->assertSame(true, $response['accountLocked']);
            $this->assertNotNull($response['lockedDate']);

            \User\models\UserModel::update([
                'set'   => ['failed_authentication' => 0, 'locked_until' => null],
                'where' => ['user_id = ?'],
                'data'  => ['superadmin']
            ]);
        }
    }
}
