<?php

/**
* Copyright Maarch since 2008 under licence GPLv3.
* See LICENCE.txt file at the root folder for more details.
* This file is part of Maarch software.
*
*/

namespace MaarchCourrier\Tests\core;

use SrcCore\controllers\PasswordController;
use SrcCore\http\Response;
use MaarchCourrier\Tests\CourrierTestCase;

class PasswordControllerTest extends CourrierTestCase
{
    public function testGetRules()
    {
        $passwordController = new PasswordController();

        $request = $this->createRequest('GET');

        $response     = $passwordController->getRules($request, new Response());
        $responseBody = json_decode((string)$response->getBody());

        $this->assertIsArray($responseBody->rules);
        $this->assertNotEmpty($responseBody->rules);
    }

    public function testUpdateRules()
    {
        $passwordController = new PasswordController();

        $request = $this->createRequest('GET');

        $response     = $passwordController->getRules($request, new Response());
        $responseBody = json_decode((string)$response->getBody());

        // reset
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
        }

        $fullRequest = $this->createRequestWithBody('PUT', ['rules' => $rules]);
        $response       = $passwordController->updateRules($fullRequest, new Response());
        $responseBody   = json_decode((string)$response->getBody());

        $this->assertSame($responseBody->success, 'success');

        $isPasswordValid = $passwordController->isPasswordValid(['password' => 'maarch']);
        $this->assertSame($isPasswordValid, true);

        // minLength
        foreach ($rules as $key => $rule) {
            if ($rule['label'] == 'minLength') {
                $rules[$key]['value'] = 7;
                $rules[$key]['enabled'] = true;
            }
        }

        $fullRequest = $this->createRequestWithBody('PUT', ['rules' => $rules]);
        $response       = $passwordController->updateRules($fullRequest, new Response());
        $responseBody   = json_decode((string)$response->getBody());

        $this->assertSame($responseBody->success, 'success');

        $isPasswordValid = $passwordController->isPasswordValid(['password' => 'maarch']);
        $this->assertSame($isPasswordValid, false);
        $isPasswordValid = $passwordController->isPasswordValid(['password' => 'maaarch']);
        $this->assertSame($isPasswordValid, true);

        // complexityUpper
        foreach ($rules as $key => $rule) {
            if ($rule['label'] == 'complexityUpper') {
                $rules[$key]['enabled'] = true;
            }
        }

        $fullRequest = $this->createRequestWithBody('PUT', ['rules' => $rules]);
        $response       = $passwordController->updateRules($fullRequest, new Response());
        $responseBody   = json_decode((string)$response->getBody());

        $this->assertSame($responseBody->success, 'success');

        $isPasswordValid = $passwordController->isPasswordValid(['password' => 'maaarch']);
        $this->assertSame($isPasswordValid, false);
        $isPasswordValid = $passwordController->isPasswordValid(['password' => 'Maaarch']);
        $this->assertSame($isPasswordValid, true);

        // complexityNumber
        foreach ($rules as $key => $rule) {
            if ($rule['label'] == 'complexityNumber') {
                $rules[$key]['enabled'] = true;
            }
        }

        $fullRequest = $this->createRequestWithBody('PUT', ['rules' => $rules]);
        $response       = $passwordController->updateRules($fullRequest, new Response());
        $responseBody   = json_decode((string)$response->getBody());

        $this->assertSame($responseBody->success, 'success');

        $isPasswordValid = $passwordController->isPasswordValid(['password' => 'Maaarch']);
        $this->assertSame($isPasswordValid, false);
        $isPasswordValid = $passwordController->isPasswordValid(['password' => 'Maaarch1']);
        $this->assertSame($isPasswordValid, true);

        // complexitySpecial
        foreach ($rules as $key => $rule) {
            if ($rule['label'] == 'complexitySpecial') {
                $rules[$key]['enabled'] = true;
            }
        }

        $fullRequest = $this->createRequestWithBody('PUT', ['rules' => $rules]);
        $response       = $passwordController->updateRules($fullRequest, new Response());
        $responseBody   = json_decode((string)$response->getBody());

        $this->assertSame($responseBody->success, 'success');

        $isPasswordValid = $passwordController->isPasswordValid(['password' => 'Maaarch1']);
        $this->assertSame($isPasswordValid, false);
        $isPasswordValid = $passwordController->isPasswordValid(['password' => 'Maaarch1!']);
        $this->assertSame($isPasswordValid, true);

        // reset
        foreach ($rules as $key => $rule) {
            if ($rule['label'] == 'complexitySpecial' || $rule['label'] == 'complexityNumber' || $rule['label'] == 'complexityUpper') {
                $rules[$key]['enabled'] = false;
            }
            if ($rule['label'] == 'minLength') {
                $rules[$key]['value'] = 6;
                $rules[$key]['enabled'] = true;
            }
        }

        $fullRequest = $this->createRequestWithBody('PUT', ['rules' => $rules]);
        $response       = $passwordController->updateRules($fullRequest, new Response());
        $responseBody   = json_decode((string)$response->getBody());

        $this->assertSame($responseBody->success, 'success');

        $isPasswordValid = $passwordController->isPasswordValid(['password' => 'maarch']);
        $this->assertSame($isPasswordValid, true);
    }
}
