<?php

/**
 * Copyright Maarch since 2008 under licence GPLv3.
 * See LICENCE.txt file at the root folder for more details.
 * This file is part of Maarch software.
 *
 */

/**
 * @brief CourrierTestCase Base TestCase class for MaarchCourrier tests
 * @author dev@maarch.org
 */


namespace MaarchCourrier\Tests;

use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\StreamFactory;
use Slim\Psr7\Headers;
use Slim\Psr7\Request;
use Slim\Psr7\Uri;
use User\models\UserModel;

class CourrierTestCase extends TestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();

        // Force to log as superadmin at the end of every tests
        $this->connectAsUser('superadmin');
    }

    public function connectAsUser(string $login)
    {
        $GLOBALS['login'] = $login;
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];
    }

    public function createRequestWithBody(
        string $method,
        array $body = [],
        string $path = '',
        array $headers = ['HTTP_ACCEPT' => 'application/json'],
        array $cookies = [],
        array $serverParams = []
    ): Request {
        $request = $this->createRequest($method, $path, $headers, $cookies, $serverParams);

        return $this->addContentInBody($request, $body);
    }

    public function createRequest(
        string $method,
        string $path = '',
        array $headers = ['HTTP_ACCEPT' => 'application/json'],
        array $cookies = [],
        array $serverParams = []
    ): Request {
        $uri = new Uri('', '', 80, $path);
        $handle = fopen('php://temp', 'w+');
        $stream = (new StreamFactory())->createStreamFromResource($handle);

        $h = new Headers();
        foreach ($headers as $name => $value) {
            $h->addHeader($name, $value);
        }

        return new Request($method, $uri, $h, $cookies, $serverParams, $stream);
    }

    public static function addContentInBody(Request $request, array $body)
    {
        $request = $request->withParsedBody($body);

        return $request->withHeader('Content-Type', 'application/json');
    }
}
