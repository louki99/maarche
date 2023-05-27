<?php

/**
 * Copyright Maarch since 2008 under licence GPLv3.
 * See LICENCE.txt file at the root folder for more details.
 * This file is part of Maarch software.
 */

/**
 * @brief Only Office Controller
 *
 * @author dev@maarch.org
 */

namespace ContentManagement\controllers;

use Attachment\models\AttachmentModel;
use Configuration\models\ConfigurationModel;
use Docserver\models\DocserverModel;
use Docserver\models\DocserverTypeModel;
use Firebase\JWT\JWT;
use Resource\controllers\ResController;
use Resource\controllers\StoreController;
use Resource\models\ResModel;
use Respect\Validation\Validator;
use Slim\Psr7\Request;
use SrcCore\http\Response;
use SrcCore\controllers\UrlController;
use SrcCore\models\CoreConfigModel;
use SrcCore\models\CurlModel;
use SrcCore\models\ValidatorModel;
use Template\models\TemplateModel;

class OnlyOfficeController
{
    // List of format convertible by OnlyOffice https://api.onlyoffice.com/editors/conversionapi
    const CONVERTIBLE_EXTENSIONS = ['doc', 'docm', 'docx', 'dot', 'dotm', 'dotx', 'epub', 'fodt', 'html', 'mht', 'odt', 'ott', 'rtf', 'txt', 'xps',
        'csv', 'fods', 'ods', 'ots', 'xls', 'xlsm', 'xlsx', 'xlt', 'xltm', 'xltx',
        'fodp', 'odp', 'otp', 'pot', 'potm', 'potx', 'pps', 'ppsm', 'ppsx', 'ppt', 'pptm', 'pptx'];

    public function getConfiguration(Request $request, Response $response)
    {
        $configuration = ConfigurationModel::getByPrivilege(['privilege' => 'admin_document_editors', 'select' => ['value']]);
        $configuration = !empty($configuration['value']) ? json_decode($configuration['value'], true) : [];

        if (empty($configuration) || empty($configuration['onlyoffice']) || empty($configuration['onlyoffice']['uri'])) {
            return $response->withJson(['enabled' => false]);
        }

        $customConfig = CoreConfigModel::getJsonLoaded(['path' => 'config/config.json']);
        $appUrl = $customConfig['config']['maarchUrl'];
        if (empty($appUrl)) {
            $appUrl = str_replace('rest/', '', UrlController::getCoreUrl());
        }

        $configurations = [
            'enabled'    => true,
            'serverUri'  => $configuration['onlyoffice']['uri'],
            'serverPort' => (int)$configuration['onlyoffice']['port'],
            'serverSsl'  => $configuration['onlyoffice']['ssl'],
            'coreUrl'    => $appUrl
        ];

        return $response->withJson($configurations);
    }

    public function getToken(Request $request, Response $response)
    {
        $body = $request->getParsedBody();
        if (!Validator::notEmpty()->validate($body['config'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body params config is empty']);
        }

        $configuration = ConfigurationModel::getByPrivilege(['privilege' => 'admin_document_editors', 'select' => ['value']]);
        $configuration = !empty($configuration['value']) ? json_decode($configuration['value'], true) : [];

        if (empty($configuration) || empty($configuration['onlyoffice']) || empty($configuration['onlyoffice']['uri'])) {
            return $response->withStatus(400)->withJson(['errors' => 'OnlyOffice server is disabled']);
        }

        $jwt = null;
        $serverSecret = $configuration['onlyoffice']['token'];
        if (!empty($serverSecret)) {
            $header = [
                "alg" => "HS256",
                "typ" => "JWT"
            ];

            $jwt = JWT::encode($body['config'], $serverSecret, 'HS256', null, $header);
        }

        return $response->withJson($jwt);
    }

    public function saveMergedFile(Request $request, Response $response)
    {
        $body = $request->getParsedBody();

        if (!Validator::stringType()->notEmpty()->validate($body['onlyOfficeKey'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body params onlyOfficeKey is empty']);
        } elseif (!preg_match('/[A-Za-z0-9]/i', $body['onlyOfficeKey'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body params onlyOfficeKey is forbidden']);
        }

        if ($body['objectType'] == 'templateCreation') {
            $customId = CoreConfigModel::getCustomId();
            if (!empty($customId) && is_dir("custom/{$customId}/modules/templates/templates/styles/")) {
                $stylesPath = "custom/{$customId}/modules/templates/templates/styles/";
            } else {
                $stylesPath = 'modules/templates/templates/styles/';
            }
            if (strpos($body['objectId'], $stylesPath) !== 0 || substr_count($body['objectId'], '.') != 1) {
                return $response->withStatus(400)->withJson(['errors' => 'Template path is not valid']);
            }

            $path = $body['objectId'];
            
            $fileContent = @file_get_contents($path);
            if ($fileContent == false) {
                return $response->withStatus(400)->withJson(['errors' => 'No content found']);
            }
        } elseif ($body['objectType'] == 'templateModification') {
            $docserver = DocserverModel::getCurrentDocserver(['typeId' => 'TEMPLATES', 'collId' => 'templates', 'select' => ['path_template']]);
            $template = TemplateModel::getById(['id' => $body['objectId'], 'select' => ['template_path', 'template_file_name']]);
            if (empty($template)) {
                return $response->withStatus(400)->withJson(['errors' => 'Template does not exist']);
            }

            $path = $docserver['path_template'] . str_replace('#', DIRECTORY_SEPARATOR, $template['template_path']) . $template['template_file_name'];
            if (!is_file($path)) {
                return $response->withStatus(400)->withJson(['errors' => 'Template does not exist on docserver']);
            }
            $fileContent = file_get_contents($path);
        } elseif ($body['objectType'] == 'resourceCreation' || $body['objectType'] == 'attachmentCreation') {
            $docserver = DocserverModel::getCurrentDocserver(['typeId' => 'TEMPLATES', 'collId' => 'templates', 'select' => ['path_template']]);
            $template = TemplateModel::getById(['id' => $body['objectId'], 'select' => ['template_path', 'template_file_name']]);
            if (empty($template)) {
                return $response->withStatus(400)->withJson(['errors' => 'Template does not exist']);
            }

            $path = $docserver['path_template'] . str_replace('#', DIRECTORY_SEPARATOR, $template['template_path']) . $template['template_file_name'];

            $dataToMerge = ['userId' => $GLOBALS['id']];
            if (!empty($body['data']) && is_array($body['data'])) {
                $dataToMerge = array_merge($dataToMerge, $body['data']);
            }
            $mergedDocument = MergeController::mergeDocument([
                'path' => $path,
                'data' => $dataToMerge
            ]);
            $fileContent = base64_decode($mergedDocument['encodedDocument']);
        } elseif ($body['objectType'] == 'resourceModification') {
            if (!ResController::hasRightByResId(['resId' => [$body['objectId']], 'userId' => $GLOBALS['id']])) {
                return $response->withStatus(400)->withJson(['errors' => 'Resource out of perimeter']);
            }
            $resource = ResModel::getById(['resId' => $body['objectId'], 'select' => ['docserver_id', 'path', 'filename', 'fingerprint']]);
            if (empty($resource['filename'])) {
                return $response->withStatus(400)->withJson(['errors' => 'Resource has no file']);
            }

            $docserver  = DocserverModel::getByDocserverId(['docserverId' => $resource['docserver_id'], 'select' => ['path_template', 'docserver_type_id']]);

            $path = $docserver['path_template'] . str_replace('#', DIRECTORY_SEPARATOR, $resource['path']) . $resource['filename'];

            $docserverType = DocserverTypeModel::getById(['id' => $docserver['docserver_type_id'], 'select' => ['fingerprint_mode']]);
            $fingerprint = StoreController::getFingerPrint(['filePath' => $path, 'mode' => $docserverType['fingerprint_mode']]);
            if (empty($resource['fingerprint'])) {
                ResModel::update(['set' => ['fingerprint' => $fingerprint], 'where' => ['res_id = ?'], 'data' => [$body['objectId']]]);
                $resource['fingerprint'] = $fingerprint;
            }

            if ($resource['fingerprint'] != $fingerprint) {
                return $response->withStatus(400)->withJson(['errors' => 'Fingerprints do not match']);
            }

            $fileContent = file_get_contents($path);
        } elseif ($body['objectType'] == 'attachmentModification') {
            $attachment = AttachmentModel::getById(['id' => $body['objectId'], 'select' => ['docserver_id', 'path', 'filename', 'res_id_master', 'fingerprint']]);
            if (empty($attachment)) {
                return $response->withStatus(400)->withJson(['errors' => 'Attachment does not exist']);
            }
            if (!ResController::hasRightByResId(['resId' => [$attachment['res_id_master']], 'userId' => $GLOBALS['id']])) {
                return $response->withStatus(400)->withJson(['errors' => 'Attachment out of perimeter']);
            }

            $docserver  = DocserverModel::getByDocserverId(['docserverId' => $attachment['docserver_id'], 'select' => ['path_template', 'docserver_type_id']]);

            $path = $docserver['path_template'] . str_replace('#', DIRECTORY_SEPARATOR, $attachment['path']) . $attachment['filename'];

            $docserverType = DocserverTypeModel::getById(['id' => $docserver['docserver_type_id'], 'select' => ['fingerprint_mode']]);
            $fingerprint = StoreController::getFingerPrint(['filePath' => $path, 'mode' => $docserverType['fingerprint_mode']]);
            if (empty($attachment['fingerprint'])) {
                AttachmentModel::update(['set' => ['fingerprint' => $fingerprint], 'where' => ['res_id = ?'], 'data' => [$body['objectId']]]);
                $attachment['fingerprint'] = $fingerprint;
            }

            if ($attachment['fingerprint'] != $fingerprint) {
                return $response->withStatus(400)->withJson(['errors' => 'Fingerprints do not match']);
            }

            $fileContent = file_get_contents($path);
        } elseif ($body['objectType'] == 'encodedResource') {
            if (empty($body['format'])) {
                return $response->withStatus(400)->withJson(['errors' => 'Body format is empty']);
            }
            $path        = null;
            $fileContent = base64_decode($body['objectId']);
            $extension   = $body['format'];
        } else {
            return $response->withStatus(400)->withJson(['errors' => 'Query param objectType does not exist']);
        }

        if (empty($extension)) {
            $extension = pathinfo($path, PATHINFO_EXTENSION);
        }
        $tmpPath = CoreConfigModel::getTmpPath();
        $filename = "onlyOffice_{$GLOBALS['id']}_{$body['onlyOfficeKey']}.{$extension}";

        $put = file_put_contents($tmpPath . $filename, $fileContent);
        if ($put === false) {
            return $response->withStatus(400)->withJson(['errors' => 'File put contents failed']);
        }

        $halfFilename = substr($filename, 11);
        return $response->withJson(['filename' => $halfFilename]);
    }

    public function getMergedFile(Request $request, Response $response)
    {
        $queryParams = $request->getQueryParams();

        if (!Validator::stringType()->notEmpty()->validate($queryParams['filename'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Query params filename is empty']);
        } elseif (substr_count($queryParams['filename'], '\\') > 0 || substr_count($queryParams['filename'], '.') != 1) {
            return $response->withStatus(400)->withJson(['errors' => 'Query params filename forbidden']);
        }

        $tmpPath  = CoreConfigModel::getTmpPath();
        $filename = "onlyOffice_{$queryParams['filename']}";

        $fileContent = file_get_contents($tmpPath . $filename);
        if ($fileContent == false) {
            return $response->withStatus(400)->withJson(['errors' => 'No content found']);
        }

        $finfo     = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType  = $finfo->buffer($fileContent);
        $extension = pathinfo($tmpPath . $filename, PATHINFO_EXTENSION);
        unlink($tmpPath . $filename);

        $response->write($fileContent);
        $response = $response->withAddedHeader('Content-Disposition', "attachment; filename=maarch.{$extension}");

        return $response->withHeader('Content-Type', $mimeType);
    }

    public function getEncodedFileFromUrl(Request $request, Response $response)
    {
        $queryParams = $request->getQueryParams();

        if (!Validator::stringType()->notEmpty()->validate($queryParams['url'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Query params url is empty']);
        }

        $configuration = ConfigurationModel::getByPrivilege(['privilege' => 'admin_document_editors', 'select' => ['value']]);
        $configuration = !empty($configuration['value']) ? json_decode($configuration['value'], true) : [];

        if (empty($configuration) || empty($configuration['onlyoffice']) || empty($configuration['onlyoffice']['uri'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Onlyoffice is not enabled']);
        }

        $checkUrl   = str_replace('http://', '', $queryParams['url']);
        $checkUrl   = str_replace('https://', '', $checkUrl);
        $uri        = $configuration['onlyoffice']['uri'];
        $uriPaths   = explode('/', $uri, 2);
        $masterPath = $uriPaths[0];
        $lastPath   = !empty($uriPaths[1]) ? rtrim("/{$uriPaths[1]}", '/') : '';
        $port       = (int)$configuration['onlyoffice']['port'];

        if (strpos($checkUrl, "{$masterPath}:{$port}{$lastPath}/cache/files/") !== 0 && (($port != 80 && $port != 443) || strpos($checkUrl, "{$masterPath}{$lastPath}/cache/files/") !== 0)) {
            return $response->withStatus(400)->withJson(['errors' => 'Query params url is not allowed']);
        }

        $fileContent = file_get_contents($queryParams['url']);
        if ($fileContent == false) {
            return $response->withStatus(400)->withJson(['errors' => 'No content found']);
        }

        return $response->withJson(['encodedFile' => base64_encode($fileContent)]);
    }

    public function isAvailable(Request $request, Response $response)
    {
        $configuration = ConfigurationModel::getByPrivilege(['privilege' => 'admin_document_editors', 'select' => ['value']]);
        $configuration = !empty($configuration['value']) ? json_decode($configuration['value'], true) : [];

        if (empty($configuration) || empty($configuration['onlyoffice'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Onlyoffice is not enabled', 'lang' => 'onlyOfficeNotEnabled']);
        } elseif (empty($configuration['onlyoffice']['uri'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Onlyoffice server_uri is empty', 'lang' => 'uriIsEmpty']);
        } elseif (empty($configuration['onlyoffice']['port'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Onlyoffice server_port is empty', 'lang' => 'portIsEmpty']);
        }

        $uri  = $configuration['onlyoffice']['uri'];
        $port = (int)$configuration['onlyoffice']['port'];

        $isAvailable = DocumentEditorController::isAvailable(['uri' => $uri, 'port' => $port]);

        if (!empty($isAvailable['errors'])) {
            return $response->withStatus(400)->withJson($isAvailable);
        }

        return $response->withJson(['isAvailable' => $isAvailable]);
    }

    public static function canConvert(array $args)
    {
        ValidatorModel::notEmpty($args, ['url', 'fullFilename']);
        ValidatorModel::stringType($args, ['url', 'fullFilename']);

        $configuration = ConfigurationModel::getByPrivilege(['privilege' => 'admin_document_editors', 'select' => ['value']]);
        $configuration = !empty($configuration['value']) ? json_decode($configuration['value'], true) : [];

        if (empty($configuration) || empty($configuration['onlyoffice']) || empty($configuration['onlyoffice']['uri']) || empty($configuration['onlyoffice']['port'])) {
            return false;
        }

        $uri  = $configuration['onlyoffice']['uri'];
        $port = (int)$configuration['onlyoffice']['port'];

        $isAvailable = DocumentEditorController::isAvailable(['uri' => $uri, 'port' => $port]);

        if (!empty($isAvailable['errors'])) {
            return false;
        }

        if (!$isAvailable) {
            return false;
        }

        if (strpos($args['url'], 'localhost') !== false || strpos($args['url'], '127.0.0.1') !== false ) {
            return false;
        }

        $docInfo = pathinfo($args['fullFilename']);

        if (!in_array($docInfo['extension'], OnlyOfficeController::CONVERTIBLE_EXTENSIONS)) {
            return false;
        }

        return true;
    }

    public static function convert(array $args)
    {
        ValidatorModel::notEmpty($args, ['url', 'fullFilename', 'userId']);
        ValidatorModel::stringType($args, ['url', 'fullFilename']);
        ValidatorModel::intVal($args, ['userId']);

        $configuration = ConfigurationModel::getByPrivilege(['privilege' => 'admin_document_editors', 'select' => ['value']]);
        $configuration = !empty($configuration['value']) ? json_decode($configuration['value'], true) : [];

        if (empty($configuration) || empty($configuration['onlyoffice'])) {
            return ['errors' => 'Onlyoffice is not enabled', 'lang' => 'onlyOfficeNotEnabled'];
        } elseif (empty($configuration['onlyoffice']['uri'])) {
            return ['errors' => 'Onlyoffice server_uri is empty', 'lang' => 'uriIsEmpty'];
        } elseif (empty($configuration['onlyoffice']['port'])) {
            return ['errors' => 'Onlyoffice server_port is empty', 'lang' => 'portIsEmpty'];
        }

        $uri  = $configuration['onlyoffice']['uri'];
        $port = (string)$configuration['onlyoffice']['port'];

        $tmpPath = CoreConfigModel::getTmpPath();
        $docInfo = pathinfo($args['fullFilename']);

        $payload = [
            'userId'       => $args['userId'],
            'fullFilename' => $args['fullFilename']
        ];

        $jwt = JWT::encode($payload, CoreConfigModel::getEncryptKey());

        $docUrl = $args['url'] . 'rest/onlyOffice/content?token=' . $jwt;

        $body = [
            'async'      => false,
            'filetype'   => $docInfo['extension'],
            'key'        => CoreConfigModel::uniqueId(),
            'outputtype' => 'pdf',
            'title'      => $docInfo['filename'] . 'pdf',
            'url'        => $docUrl
        ];

        $serverSecret = $configuration['onlyoffice']['token'];
        $serverAuthorizationHeader = $configuration['onlyoffice']['authorizationHeader'];
        $serverSsl = $configuration['onlyoffice']['ssl'];

        $uri = explode("/", $uri);
        $domain = $uri[0];
        $path = array_slice($uri, 1);
        $path = implode("/", $path);

        if (!empty($serverSsl)) {
            $convertUrl = 'https://';
        } else {
            $convertUrl = 'http://';
        }

        $convertUrl .= $domain;

        if ($port != 80) {
            $convertUrl .= ":{$port}";
        }

        if (!empty($path)) {
            $convertUrl .= '/' . $path;
        }

        if (substr($convertUrl, -1) != '/') {
            $convertUrl .= '/';
        }
        $convertUrl .= 'ConvertService.ashx';

        $headers = [
            'Accept: application/json',
            'Content-Type: application/json'
        ];

        if (!empty($serverSecret)) {
            $header = [
                "alg" => "HS256",
                "typ" => "JWT"
            ];

            $tokenOnlyOffice = JWT::encode($body, $serverSecret, 'HS256', null, $header);

            $authorizationHeader = empty($serverAuthorizationHeader) ? 'Authorization' : $serverAuthorizationHeader;
            $authorizationHeader .= ': Bearer ' . $tokenOnlyOffice;

            $headers[] =  $authorizationHeader;
        }

        $response = CurlModel::exec([
            'url'     => $convertUrl,
            'headers' => $headers,
            'method'  => 'POST',
            'body'    => json_encode($body)
        ]);

        if ($response['code'] != 200) {
            return ['errors' => 'OnlyOffice conversion failed'];
        }
        if (!empty($response['response']['error'])) {
            return ['errors' => 'OnlyOffice conversion failed : ' . $response['response']['error']];
        }

        $convertedFile = file_get_contents($response['response']['fileUrl']);

        if ($convertedFile === false) {
            return ['errors' => 'Cannot get converted document'];
        }

        $filename = $tmpPath . $docInfo['filename'] . '.pdf';
        $saveTmp = file_put_contents($filename, $convertedFile);
        if ($saveTmp == false) {
            return ['errors' => 'Cannot save converted document'];
        }

        $tmpFilename =  $tmpPath . "tmp_{$GLOBALS['id']}_" . rand() . ".pdf";
        $command = "gs -dCompatibilityLevel=1.4 -q -sDEVICE=pdfwrite -dNOPAUSE -dQUIET -dBATCH -o {$tmpFilename} {$filename} 2>&1; mv {$tmpFilename} {$filename}";
        exec($command, $output, $return);
        if (!empty($output)) {
            return ['errors' => implode(",", $output)];
        }

        return true;
    }

    public function getTmpFile(Request $request, Response $response)
    {
        $queryParams = $request->getQueryParams();

        try {
            $jwt = JWT::decode($queryParams['token'], CoreConfigModel::getEncryptKey(), ['HS256']);
        } catch (\Exception $e) {
            return $response->withStatus(401)->withJson(['errors' => 'Token is invalid']);
        }

        if (!file_exists($jwt->fullFilename)) {
            return $response->withStatus(404)->withJson(['errors' => 'Document not found']);
        }

        $fileContent = file_get_contents($jwt->fullFilename);
        if ($fileContent === false) {
            return $response->withStatus(404)->withJson(['errors' => 'Document not found']);
        }

        $finfo    = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->buffer($fileContent);
        $pathInfo = pathinfo($jwt->fullFilename);

        $response->write($fileContent);
        $response = $response->withAddedHeader('Content-Disposition', "attachment; filename=maarch.{$pathInfo['extension']}");
        return $response->withHeader('Content-Type', $mimeType);
    }
}
