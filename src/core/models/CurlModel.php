<?php

/**
 * Copyright Maarch since 2008 under licence GPLv3.
 * See LICENCE.txt file at the root folder for more details.
 * This file is part of Maarch software.
 *
 */

/**
 * @brief Curl Model
 * @author dev@maarch.org
 */

namespace SrcCore\models;

use SrcCore\controllers\LogsController;

class CurlModel
{
    public static function execSOAP(array $aArgs)
    {
        ValidatorModel::notEmpty($aArgs, ['xmlPostString', 'url']);
        ValidatorModel::stringType($aArgs, ['xmlPostString', 'url', 'soapAction']);
        ValidatorModel::arrayType($aArgs, ['options']);

        $opts = [
            CURLOPT_URL             => $aArgs['url'],
            CURLOPT_RETURNTRANSFER  => true,
            CURLOPT_POST            => true,
            CURLOPT_POSTFIELDS      => $aArgs['xmlPostString'],
            CURLOPT_HTTPHEADER      => [
                'content-type:text/xml;charset="utf-8"',
                'accept:text/xml',
                'Cache-Control: no-cache',
                'Pragma: no-cache',
                'Content-length: ' . strlen($aArgs['xmlPostString']),
            ],
            CURLOPT_SSL_VERIFYHOST  => false,
            CURLOPT_SSL_VERIFYPEER  => false,
            CURLOPT_CONNECTTIMEOUT  => 10
        ];

        if (!empty($aArgs['soapAction'])) {
            $opts[CURLOPT_HTTPHEADER][] = "SOAPAction: \"{$aArgs['soapAction']}\"";
        }
        if (!empty($aArgs['Cookie'])) {
            $opts[CURLOPT_HTTPHEADER][] = "Cookie:{$aArgs['Cookie']}";
        }
        if (!empty($aArgs['options'])) {
            foreach ($aArgs['options'] as $key => $option) {
                $opts[$key] = $option;
            }
        }

        $curl = curl_init();
        curl_setopt_array($curl, $opts);
        $rawResponse = curl_exec($curl);
        $error = curl_error($curl);
        $infos = curl_getinfo($curl);

        $cookies = [];
        if (!empty($aArgs['options'][CURLOPT_HEADER])) {
            preg_match_all('/^Set-Cookie:\s*([^;]*)/mi', $rawResponse, $matches);
            foreach ($matches[1] as $item) {
                $cookie = explode("=", $item);
                $cookies = array_merge($cookies, [$cookie[0] => $cookie[1]]);
            }
            $rawResponse = substr($rawResponse, $infos['header_size']);
        } elseif (!empty($aArgs['delete_header'])) { // Delete header for iparapheur
            $body = strstr($rawResponse, '<?xml'); // put the header ahead
            if (empty($body)) {
                $body = explode(PHP_EOL, $rawResponse);
                // we remove the 4 starting item of the array (header)
                for ($i=0; $i < 5; $i++) {
                    array_shift($body);
                }
                // and the last item (footer)
                array_pop($body);
                $body = join('', $body);
            }
            $pattern = '/--uuid:[0-9a-f-]+--/';                  // And also the footer
            $rawResponse = preg_replace($pattern, '', $body);
        }

        LogsController::add([
            'isTech'    => true,
            'moduleId'  => 'curl',
            'level'     => 'DEBUG',
            'tableName' => '',
            'recordId'  => '',
            'eventType' => 'Exec Curl : ' . $aArgs['url'],
            'eventId'   => $rawResponse
        ]);

        if (!empty($error)) {
            LogsController::add([
                'isTech'    => true,
                'moduleId'  => 'curl',
                'level'     => 'ERROR',
                'tableName' => '',
                'recordId'  => '',
                'eventType' => 'Error Exec Curl : ' . $error,
                'eventId'   => $rawResponse
            ]);
        }

        return ['response' => simplexml_load_string($rawResponse), 'infos' => $infos, 'cookies' => $cookies, 'raw' => $rawResponse, 'error' => $error];
    }

    public static function exec(array $args)
    {
        ValidatorModel::notEmpty($args, ['url', 'method']);
        ValidatorModel::stringType($args, ['url', 'method', 'cookie']);
        ValidatorModel::arrayType($args, ['headers', 'queryParams', 'basicAuth', 'bearerAuth', 'options']);
        ValidatorModel::boolType($args, ['isXml', 'followRedirect']);

        $args['isXml'] = $args['isXml'] ?? false;

        $opts = [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_SSL_VERIFYPEER => false, CURLOPT_CONNECTTIMEOUT => 10];

        if (!empty($args['followRedirect'])) {
            $opts[CURLOPT_FOLLOWLOCATION] = true;
        }

        //Headers
        if (!empty($args['headers'])) {
            $opts[CURLOPT_HTTPHEADER] = $args['headers'];
        } else {
            $args['headers'] = [];
        }
        //Auth
        if (!empty($args['basicAuth'])) {
            $opts[CURLOPT_HTTPHEADER][] = 'Authorization: Basic ' . base64_encode($args['basicAuth']['user']. ':' .$args['basicAuth']['password']);
        }
        if (!empty($args['bearerAuth'])) {
            $opts[CURLOPT_HTTPHEADER][] = 'Authorization: Bearer ' . $args['bearerAuth']['token'];
        }
        //Cookie
        if (!empty($args['cookie'])) {
            $opts[CURLOPT_COOKIE] = $args['cookie'];
        }
        //Options
        if (!empty($args['options'])) {
            foreach ($args['options'] as $key => $option) {
                $opts[$key] = $option;
            }
        }

        //QueryParams
        if (!empty($args['queryParams'])) {
            $args['url'] .= '?';
            $i = 0;
            foreach ($args['queryParams'] as $queryKey => $queryParam) {
                if ($i > 0) {
                    $args['url'] .= '&';
                }
                $args['url'] .= "{$queryKey}={$queryParam}";
                ++$i;
            }
        }

        //Body
        if (!empty($args['body'])) {
            $opts[CURLOPT_POSTFIELDS] = $args['body'];
        }
        //MultipartBody
        if (!empty($args['multipartBody'])) {
            $boundary = uniqid();
            $postData = CurlModel::createMultipartFormData(['boundary' => $boundary, 'body' => $args['multipartBody']]);
            $opts[CURLOPT_HTTPHEADER][] = "Content-Type: multipart/form-data; boundary=-------------{$boundary}";
            $opts[CURLOPT_POSTFIELDS] = $postData;
        }
        //Method
        if ($args['method'] == 'POST') {
            $opts[CURLOPT_POST] = true;
        } elseif ($args['method'] == 'PUT' || $args['method'] == 'PATCH' || $args['method'] == 'DELETE') {
            $opts[CURLOPT_CUSTOMREQUEST] = $args['method'];
        } elseif (!empty($args['customRequest'])) {
            $opts[CURLOPT_CUSTOMREQUEST] = $args['customRequest'];
        }

        //Url
        $opts[CURLOPT_URL] = $args['url'];

        $curl = curl_init();
        curl_setopt_array($curl, $opts);
        $rawResponse = curl_exec($curl);
        $code        = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $headerSize  = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
        $errors      = curl_error($curl);
        curl_close($curl);

        $headers  = substr($rawResponse, 0, $headerSize);
        $headers  = explode("\r\n", $headers);
        $response = substr($rawResponse, $headerSize);

        $formatedResponse = $response;

        if ($args['isXml']) {
            $formatedResponse = simplexml_load_string($response);
        } elseif (in_array('Accept: application/zip', $args['headers'])) {
            $formatedResponse = trim($response);
        } elseif(empty($args['fileResponse'])) {
            $formatedResponse = json_decode($response, true);
            if (empty($code) && !empty($formatedResponse["code"])) {
                $code = $formatedResponse["code"];
            }
            if (empty($errors) && !empty($formatedResponse["error"])) {
                $errors = $args['url'] . " ". $formatedResponse["error"];
            }
        }

        $code = !empty($code) ? $code : 500;

        if (empty($args['noLogs'])) {
            $logResponse = '';

            if (in_array('Accept: application/zip', $args['headers'])) {
                $logResponse = 'Zip file content';
            } elseif (!empty($args['fileResponse'])) {
                $logResponse = 'File content';
            } else {
                $logResponse = json_encode($formatedResponse ?? '{}');
            }
            LogsController::add([
                'isTech'    => true,
                'moduleId'  => 'curl',
                'level'     => ($code >= 400 ? 'ERROR' : 'DEBUG'),
                'tableName' => 'curl',
                'recordId'  => 'curl_exec',
                'eventType' => "Url : {$args['url']} HttpCode : {$code} Errors : {$errors}",
                'eventId'   => "Response : {$logResponse}"
            ]);
        }

        return ['raw' => $rawResponse, 'code' => $code, 'headers' => $headers, 'response' => $formatedResponse, 'errors' => $errors];
    }

    private static function createMultipartFormData(array $args)
    {
        ValidatorModel::notEmpty($args, ['boundary', 'body']);
        ValidatorModel::stringType($args, ['boundary']);
        ValidatorModel::arrayType($args, ['body']);

        $delimiter = "-------------{$args['boundary']}";

        $postData = '';
        foreach ($args['body'] as $key => $value) {
            if (!empty($value['subvalues']) && is_array($value['subvalues'])) {
                foreach ($value['subvalues'] as $subvalue) {
                    $postData .= "--{$delimiter}\r\n";
                    if (is_array($subvalue) && !empty($subvalue['isFile']) && !empty($subvalue['filename']) && !empty($subvalue['content'])) {
                        $postData .= "Content-Disposition: form-data; name=\"{$key}\"; filename=\"{$subvalue['filename']}\"\r\n";
                        $postData .= "\r\n{$subvalue['content']}\r\n";
                    } else {
                        $postData .= "Content-Disposition: form-data; name=\"{$key}\"\r\n";
                        $postData .= "\r\n{$subvalue}\r\n";
                    }
                }
            } else {
                $postData .= "--{$delimiter}\r\n";
                if (is_array($value) && !empty($value['isFile']) && !empty($value['filename']) && !empty($value['content'])) {
                    $postData .= "Content-Disposition: form-data; name=\"{$key}\"; filename=\"{$value['filename']}\"\r\n";
                    $postData .= "\r\n{$value['content']}\r\n";
                } else {
                    $postData .= "Content-Disposition: form-data; name=\"{$key}\"\r\n";
                    $postData .= "\r\n{$value}\r\n";
                }
            }
        }
        $postData .= "--{$delimiter}--\r\n";

        return $postData;
    }

    public static function makeCurlFile(array $aArgs)
    {
        ValidatorModel::notEmpty($aArgs, ['path']);
        ValidatorModel::stringType($aArgs, ['path', 'name']);

        $mime = mime_content_type($aArgs['path']);
        $info = pathinfo($aArgs['path']);
        $name = $aArgs['name'] ?? $info['basename'];
        $output = new \CURLFile($aArgs['path'], $mime, $name);

        return $output;
    }
}
