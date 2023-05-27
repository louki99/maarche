<?php

/**
 * Copyright Maarch since 2008 under licence GPLv3.
 * See LICENCE.txt file at the root folder for more details.
 * This file is part of Maarch software.
 */

/**
 * @brief Url Controller
 * @author dev@maarch.org
 */

namespace SrcCore\controllers;

class UrlController
{
    private static function getPath()
    {

        if (!empty($_SERVER['HTTP_X_FORWARDED_SCRIPT_NAME'])) {
            $baseUri = $_SERVER['HTTP_X_FORWARDED_SCRIPT_NAME'];
        } else {
            $baseUri = $_SERVER['SCRIPT_NAME'];
        }
        $baseUri = trim(dirname($baseUri), '/');
        $baseUri = str_replace('rest', '', $baseUri);

        return '/'.$baseUri;
    }

    private static function getHost()
    {
        if (!empty($_SERVER['HTTP_X_FORWARDED_HOST'])) {
            return $_SERVER['HTTP_X_FORWARDED_HOST'];
        }

        $rawHost = explode(':',$_SERVER['HTTP_HOST']);

        return $rawHost[0];
    }

    private static function getPort()
    {
        if (!empty($_SERVER['HTTP_X_FORWARDED_PORT'])) {
            return $_SERVER['HTTP_X_FORWARDED_PORT'];
        }

        return $_SERVER['SERVER_PORT'];
    }

    private static function getProto()
    {
        if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
            return $_SERVER['HTTP_X_FORWARDED_PROTO'];
        }

        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') {
            return 'https';
        }

        return 'http';
    }

    public static function getCoreUrl()
    {
        $url = UrlController::getProto();
        $url .= '://';
        $url .= UrlController::getHost();

        $port = UrlController::getPort();
        if ($port != 80 && $port != 443) {
            $url .= ':';
            $url .= $port;
        }

        $url .= UrlController::getPath();

        return $url;
    }
}
