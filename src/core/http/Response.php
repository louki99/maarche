<?php

/**
 * Copyright Maarch since 2008 under licence GPLv3.
 * See LICENCE.txt file at the root folder for more details.
 * This file is part of Maarch software.
 *
 */

/**
 * @brief Custom HTTP Response Object
 *        This overrides Slim 4 Response with the old withJson() method
 * @author dev@maarch.org
 * @ingroup core
 */

namespace SrcCore\http;

use RuntimeException;
use Slim\Psr7\Response as SlimResponse;
use Slim\Psr7\Stream;

class Response extends SlimResponse
{
    /**
     * Json.
     *
     * Note: This method is not part of the PSR-7 standard.
     *
     * This method prepares the response object to return an HTTP Json
     * response to the client.
     *
     * @param  mixed $data   The data
     * @param  int|null   $status The HTTP status code.
     * @param  int|null   $encodingOptions Json encoding options
     *
     * @return static
     *
     * @throws RuntimeException
     */
    public function withJson($data, ?int $status = null, ?int $encodingOptions = 0)
    {
        $response = $this->withBody(new Stream(fopen('php://temp', 'r+')));
        $response->body->write($json = json_encode($data, $encodingOptions));

        // Ensure that the json encoding passed successfully
        if ($json === false) {
            throw new RuntimeException(json_last_error_msg(), json_last_error());
        }

        $responseWithJson = $response->withHeader('Content-Type', 'application/json');
        if (isset($status)) {
            return $responseWithJson->withStatus($status);
        }
        return $responseWithJson;
    }

    /**
     * Write data to the response body.
     *
     * Note: This method is not part of the PSR-7 standard.
     *
     * Proxies to the underlying stream and writes the provided data to it.
     *
     * @param string $data
     *
     * @return static
     */
    public function write($data)
    {
        $this->getBody()->write($data);

        return $this;
    }

}
