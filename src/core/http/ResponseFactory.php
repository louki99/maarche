<?php

/**
 * Copyright Maarch since 2008 under licence GPLv3.
 * See LICENCE.txt file at the root folder for more details.
 * This file is part of Maarch software.
 *
 */

/**
 * @brief Custom HTTP ResponseFactory
 *        This creates a custom Response Object
 * @author dev@maarch.org
 * @ingroup core
 */

namespace SrcCore\http;

use Fig\Http\Message\StatusCodeInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;

class ResponseFactory implements ResponseFactoryInterface
{
    public function createResponse(int $code = StatusCodeInterface::STATUS_OK, string $reasonPhrase = ''): ResponseInterface
    {
        $response = new Response($code);

        if ($reasonPhrase !== '') {
            $response = $response->withStatus($code, $reasonPhrase);
        }

        return $response;
    }

}
