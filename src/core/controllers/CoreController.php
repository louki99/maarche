<?php

/**
 * Copyright Maarch since 2008 under licence GPLv3.
 * See LICENCE.txt file at the root folder for more details.
 * This file is part of Maarch software.
 */

/**
 * @brief Core Controller
 *
 * @author dev@maarch.org
 */

namespace SrcCore\controllers;

use Resource\controllers\StoreController;
use Respect\Validation\Validator;
use Slim\Psr7\Request;
use SrcCore\http\Response;
use SrcCore\models\CoreConfigModel;
use SrcCore\models\ValidatorModel;
use User\models\UserModel;

class CoreController
{
    public function getHeader(Request $request, Response $response)
    {
        $user             = UserModel::getById(['id' => $GLOBALS['id'], 'select' => ['id', 'user_id', 'firstname', 'lastname']]);
        $user['groups']   = UserModel::getGroupsById(['id' => $GLOBALS['id']]);
        $user['entities'] = UserModel::getEntitiesById(['id' => $GLOBALS['id'], 'select' => ['entities.id', 'users_entities.entity_id', 'entities.entity_label', 'users_entities.user_role', 'users_entities.primary_entity']]);

        return $response->withJson(['user' => $user]);
    }

    public function getGitCommitInformation(Request $request, Response $response)
    {
        $head = file_get_contents('.git/HEAD');

        if ($head === false) {
            return $response->withJson(['hash' => null]);
        }
        preg_match('#^ref:(.+)$#', $head, $matches);
        $currentHead = trim($matches[1] ?? '');

        if (empty($currentHead) && empty($head)) {
            return $response->withJson(['hash' => null]);
        }

        $hash = $head;
        if (!empty($currentHead)) {
            $hash = file_get_contents('.git/' . $currentHead);
            if ($hash === false) {
                return $response->withJson(['hash' => null]);
            }
        }

        $hash = explode("\n", $hash)[0];

        return $response->withJson(['hash' => $hash]);
    }

    public static function setGlobals(array $args)
    {
        ValidatorModel::notEmpty($args, ['userId']);
        ValidatorModel::intVal($args, ['userId']);

        $user             = UserModel::getById(['id' => $args['userId'], 'select' => ['user_id']]);
        $GLOBALS['login'] = $user['user_id'];
        $GLOBALS['id']    = $args['userId'];
    }

    public function externalConnectionsEnabled(Request $request, Response $response)
    {
        $connections = [];
        $loadedXml = CoreConfigModel::getXmlLoaded(['path' => 'modules/visa/xml/remoteSignatoryBooks.xml']);

        if (!empty($loadedXml->signatoryBookEnabled)) {
            $connections[(string)$loadedXml->signatoryBookEnabled] = true;
        }
        $mailevaConfig = CoreConfigModel::getMailevaConfiguration();
        if ($mailevaConfig['enabled']) {
            $connections['maileva'] = true;
        }

        return $response->withJson(['connection' => $connections]);
    }

    public function getImages(Request $request, Response $response)
    {
        $queryParams = $request->getQueryParams();

        $customId = CoreConfigModel::getCustomId();

        $assetPath = 'dist/assets';

        if ($queryParams['image'] == 'loginPage') {
            $filename = 'bodylogin.jpg';
            if (!empty($customId) && is_file("custom/{$customId}/img/{$filename}")) {
                $path = "custom/{$customId}/img/{$filename}";
            } else {
                $path = "{$assetPath}/{$filename}";
            }
        } elseif ($queryParams['image'] == 'logo') {
            $filename = 'logo.svg';
            if (!empty($customId) && is_file("custom/{$customId}/img/{$filename}")) {
                $path = "custom/{$customId}/img/{$filename}";
            } else {
                $path = "{$assetPath}/{$filename}";
            }
        } elseif ($queryParams['image'] == 'onlyLogo') {
            $filename = 'logo_only.svg';
            if (!empty($customId) && is_file("custom/{$customId}/img/{$filename}")) {
                $path = "custom/{$customId}/img/{$filename}";
            } else {
                $path = "{$assetPath}/{$filename}";
            }
        } else {
            return $response->withStatus(404)->withJson(['errors' => 'QueryParams image is empty or not valid']);
        }

        $fileContent = file_get_contents($path);
        if ($fileContent === false) {
            return $response->withStatus(400)->withJson(['errors' => 'Image not found']);
        }

        if (in_array($queryParams['image'], ['logo', 'onlyLogo'])) {
            $mimeType = 'image/svg+xml';
        } else {
            $mimeAndSize = CoreController::getMimeTypeAndFileSize(['path' => $path]);
            if (!empty($mimeAndSize['errors'])) {
                return $response->withStatus(400)->withJson(['errors' => $mimeAndSize['errors']]);
            }
            $mimeType = $mimeAndSize['mime'];
        }
        $pathInfo = pathinfo($path);

        $response->write($fileContent);
        $response = $response->withAddedHeader('Content-Disposition', "inline; filename=maarch.{$pathInfo['extension']}");

        return $response->withHeader('Content-Type', $mimeType);
    }

    public static function getMaximumAllowedSizeFromPhpIni()
    {
        $uploadMaxFilesize = ini_get('upload_max_filesize');
        $uploadMaxFilesize = StoreController::getBytesSizeFromPhpIni(['size' => $uploadMaxFilesize]);
        $postMaxSize       = ini_get('post_max_size');
        $postMaxSize       = $postMaxSize == 0 ? $uploadMaxFilesize : StoreController::getBytesSizeFromPhpIni(['size' => $postMaxSize]);
        $memoryLimit       = ini_get('memory_limit');
        $memoryLimit       = $memoryLimit < 1 ? $uploadMaxFilesize : StoreController::getBytesSizeFromPhpIni(['size' => $memoryLimit]);
        $maximumSize       = min($uploadMaxFilesize, $postMaxSize, $memoryLimit);

        return $maximumSize;
    }

    /**
     * getMimeTypeAndFileSize
     * 
     * @param array $args with either an 'encodedFile' (base64 string), or a 'path' (file path as string)
     * @return array with 'mime' and 'size' entries or array with 'errors' entry
     */
    public static function getMimeTypeAndFileSize(array $args) {
        ValidatorModel::stringType($args, ['encodedFile', 'path']);
        if (empty($args['encodedFile']) && empty($args['path'])) {
            return ['errors' => 'args needs one of encodedFile or path'];
        }

        $resource = null;
        $size = null;
        if (!empty($args['encodedFile'])) {
            $resource = fopen('php://temp', 'r+');
            $streamFilterBase64 = stream_filter_append($resource, 'convert.base64-decode', STREAM_FILTER_WRITE);
            stream_set_chunk_size($resource, 1024*1024);
            $size = fwrite($resource, $args['encodedFile']);
            stream_filter_remove($streamFilterBase64);
        } elseif (!empty($args['path'])) {
            if (!is_file($args['path']) || !is_readable($args['path'])) {
                return ['errors' => 'args filename does not refer to a regular file or said file is not readable'];
            }
            $resource = fopen($args['path'], 'r');
            $size = filesize($args['path']);
        }
        
        if (empty($resource)) {
            return ['errors' => 'could not decode encoded data, or open target file'];
        }

        rewind($resource);
        $mimeType = mime_content_type($resource);
        fclose($resource);

        if (empty($mimeType) || empty($size)) {
            return ['errors' => "could not compute mime type ($mimeType) or file size ($size)"];
        }

        return ['mime' => $mimeType, 'size' => $size];
    }

    
    /**
     * get MASK param erorr_reporting
     * 
     * 1       E_ERROR     
     * 2       E_WARNING   
     * 4       E_PARSE     
     * 8       E_NOTICE    
     * 16      E_CORE_ERROR    
     * 32      E_CORE_WARNING  
     * 64      E_COMPILE_ERROR     
     * 128     E_COMPILE_WARNING   
     * 256     E_USER_ERROR    
     * 512     E_USER_WARNING  
     * 1024    E_USER_NOTICE   
     * 2048    E_STRICT    
     * 4096    E_RECOVERABLE_ERROR     
     * 8192    E_DEPRECATED    
     * 16384   E_USER_DEPRECATED
     */
    public static function getErrorReportingFromPhpIni()
    {
        $bits = ini_get('error_reporting');

        $errorReporting = [];
        while ($bits > 0) {
            $end = 0;
            for ($i = 0, $n = 0; $i <= $bits; $i = 1 * pow(2, $n), $n++) {
                $end = $i;
            }
            $errorReporting[] = $end;
            $bits = $bits - $end;
        }

        return $errorReporting;
    }

    public function generateLang(Request $request, Response $response)
    {
        if (!is_file('dist/main-es5.js') && !is_file('dist/main-es2015.js') && !is_file('dist/main.js')) {
            return $response->withStatus(403)->withJson(['errors' => 'Route forbidden']);
        }

        $body = $request->getParsedBody();

        if (!Validator::stringType()->notEmpty()->validate($body['langId'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body langId is empty or not a string']);
        }

        $content = json_encode($body['jsonContent'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($fp = @fopen("src/lang/lang-{$body['langId']}.json", 'w')) {
            fwrite($fp, $content);
            fclose($fp);
            return $response->withStatus(204);
        } else {
            return $response->withStatus(400)->withJson(['errors' => "Cannot open file : src/lang/lang-{$body['langId']}.json"]);
        }
    }

    public function getAvailableCoreLanguages(Request $request, Response $response)
    {
        $files = array_diff(scandir('src/lang'), ['..', '.']);
        $arrLanguages = [];
        foreach ($files as $value) {
            $file        = str_replace('.json', '', $value) ;
            $langName    = explode('-', $file)[1];
            $path        = 'src/lang/' . $file . '.json';
            $fileContent = file_get_contents($path);
            $fileContent = json_decode($fileContent);
            $arrLanguages[$langName] = $fileContent;
        }
        return $response->withJson(['langs' => $arrLanguages]);
    }
}
