<?php

/**
 * Copyright Maarch since 2008 under licence GPLv3.
 * See LICENCE.txt file at the root folder for more details.
 * This file is part of Maarch software.
 */

/**
 * @brief Jnlp Controller
 *
 * @author dev@maarch.org
 */

namespace ContentManagement\controllers;

use Attachment\models\AttachmentModel;
use Docserver\models\DocserverModel;
use Docserver\models\DocserverTypeModel;
use Resource\controllers\ResController;
use Resource\controllers\StoreController;
use Resource\models\ResModel;
use Slim\Psr7\Request;
use SrcCore\http\Response;
use SrcCore\controllers\UrlController;
use SrcCore\models\CoreConfigModel;
use SrcCore\models\ValidatorModel;
use Template\models\TemplateModel;

class JnlpController
{
    public function generateJnlp(Request $request, Response $response)
    {
        $body = $request->getParsedBody();

        $coreUrl         = str_replace('rest/', '', UrlController::getCoreUrl());
        $tmpPath         = CoreConfigModel::getTmpPath();
        $jnlpUniqueId    = CoreConfigModel::uniqueId();
        $jnlpFileName    = $GLOBALS['id'] . '_maarchCM_' . $jnlpUniqueId;
        $jnlpFileNameExt = $jnlpFileName . '.jnlp';

        $allCookies = '';
        foreach ($_COOKIE as $key => $value) {
            if (!empty($allCookies)) {
                $allCookies .= '; ';
            }
            $allCookies .= $key . '=' . str_replace(' ', '+', $value);
        }
        if (!empty($body['cookies'])) {
            if (!empty($allCookies)) {
                $allCookies .= '; ';
            }
            $allCookies .= $body['cookies'];
        }
        if (empty($allCookies)) {
            $allCookies = 'noCookie=noCookie';
        }

        $loadedXml = CoreConfigModel::getXmlLoaded(['path' => 'modules/content_management/xml/config.xml']);
        $jarPath = $coreUrl;
        if ($loadedXml && !empty((string)$loadedXml->CONFIG[0]->jar_path)) {
            $jarPath = (string)$loadedXml->CONFIG[0]->jar_path;
        }

        $jnlpDocument = new \DomDocument('1.0', 'UTF-8');

        $tagJnlp = $jnlpDocument->createElement('jnlp');

        $newAttribute = $jnlpDocument->createAttribute('spec');
        $newAttribute->value = '6.0+';
        $tagJnlp->appendChild($newAttribute);

        $newAttribute = $jnlpDocument->createAttribute('codebase');
        $newAttribute->value = $coreUrl . 'rest/jnlp/';
        $tagJnlp->appendChild($newAttribute);

        $newAttribute = $jnlpDocument->createAttribute('href');
        $newAttribute->value = htmlentities($jnlpFileNameExt);
        $tagJnlp->appendChild($newAttribute);

        $tagInformation = $jnlpDocument->createElement('information');
        $tagTitle       = $jnlpDocument->createElement('title', 'Editeur de modèle de document');
        $tagVendor      = $jnlpDocument->createElement('vendor', 'MAARCH');
        $tagOffline     = $jnlpDocument->createElement('offline-allowed');
        $tagSecurity    = $jnlpDocument->createElement('security');
        $tagPermissions = $jnlpDocument->createElement('all-permissions');
        $tagResources   = $jnlpDocument->createElement('resources');
        $tagJ2se        = $jnlpDocument->createElement('j2se');

        $newAttribute = $jnlpDocument->createAttribute('version');
        $newAttribute->value = '1.6+';
        $tagJ2se->appendChild($newAttribute);

        $tagJar1 = $jnlpDocument->createElement('jar');
        $newAttribute = $jnlpDocument->createAttribute('href');
        $newAttribute->value = $coreUrl . '/modules/content_management/dist/maarchCM.jar';
        $tagJar1->appendChild($newAttribute);
        $newAttribute = $jnlpDocument->createAttribute('main');
        $newAttribute->value = 'true';
        $tagJar1->appendChild($newAttribute);

        $tagJar2 = $jnlpDocument->createElement('jar');
        $newAttribute = $jnlpDocument->createAttribute('href');
        $newAttribute->value = $jarPath . '/modules/content_management/dist/lib/httpclient-4.5.2.jar';
        $tagJar2->appendChild($newAttribute);

        $tagJar3 = $jnlpDocument->createElement('jar');
        $newAttribute = $jnlpDocument->createAttribute('href');
        $newAttribute->value = $jarPath . '/modules/content_management/dist/lib/httpclient-cache-4.5.2.jar';
        $tagJar3->appendChild($newAttribute);

        $tagJar4 = $jnlpDocument->createElement('jar');
        $newAttribute = $jnlpDocument->createAttribute('href');
        $newAttribute->value = $jarPath . '/modules/content_management/dist/lib/httpclient-win-4.5.2.jar';
        $tagJar4->appendChild($newAttribute);

        $tagJar5 = $jnlpDocument->createElement('jar');
        $newAttribute = $jnlpDocument->createAttribute('href');
        $newAttribute->value = $jarPath . '/modules/content_management/dist/lib/httpcore-4.4.4.jar';
        $tagJar5->appendChild($newAttribute);

        $tagJar6 = $jnlpDocument->createElement('jar');
        $newAttribute = $jnlpDocument->createAttribute('href');
        $newAttribute->value = $jarPath . '/modules/content_management/dist/lib/plugin.jar';
        $tagJar6->appendChild($newAttribute);

        $tagJar7 = $jnlpDocument->createElement('jar');
        $newAttribute = $jnlpDocument->createAttribute('href');
        $newAttribute->value = $jarPath . '/modules/content_management/dist/lib/commons-logging-1.2.jar';
        $tagJar7->appendChild($newAttribute);


        $tagApplication = $jnlpDocument->createElement('application-desc');
        $newAttribute = $jnlpDocument->createAttribute('main-class');
        $newAttribute->value = 'com.maarch.MaarchCM';
        $tagApplication->appendChild($newAttribute);

        $cookie = $_COOKIE['maarchCourrierAuth'] ?? '';
        $bodyData = $body['data'] ?? null;
        $tagArg1 = $jnlpDocument->createElement('argument', $coreUrl . 'rest/jnlp/' . $jnlpUniqueId); //ProcessJnlp
        $tagArg2 = $jnlpDocument->createElement('argument', $body['objectType']); //Type
        $tagArg3 = $jnlpDocument->createElement('argument', base64_encode(json_encode($bodyData)));
        $tagArg4 = $jnlpDocument->createElement('argument', $body['objectId']); //ObjectId
        $tagArg5 = $jnlpDocument->createElement('argument', 0); //Useless
        $tagArg6 = $jnlpDocument->createElement('argument', "maarchCourrierAuth={$cookie}"); //MaarchCookie
        $tagArg7 = $jnlpDocument->createElement('argument', htmlentities($allCookies)); //AllCookies
        $tagArg8 = $jnlpDocument->createElement('argument', $jnlpFileName); //JnlpFileName
        $tagArg9 = $jnlpDocument->createElement('argument', $GLOBALS['id']); //CurrentUser //Useless
        $tagArg10 = $jnlpDocument->createElement('argument', 'false'); //ConvertPdf //Useless
        $tagArg11 = $jnlpDocument->createElement('argument', 'false'); //OnlyConvert //Useless
        $tagArg12 = $jnlpDocument->createElement('argument', 0); //HashFile //Useless
        $tagArg13 = $jnlpDocument->createElement('argument', $body['authToken']); //Token authentication


        $tagJnlp->appendChild($tagInformation);
        $tagInformation->appendChild($tagTitle);
        $tagInformation->appendChild($tagVendor);
        $tagInformation->appendChild($tagOffline);

        $tagJnlp->appendChild($tagSecurity);
        $tagSecurity->appendChild($tagPermissions);

        $tagJnlp->appendChild($tagResources);
        $tagResources->appendChild($tagJ2se);
        $tagResources->appendChild($tagJar1);
        $tagResources->appendChild($tagJar2);
        $tagResources->appendChild($tagJar3);
        $tagResources->appendChild($tagJar4);
        $tagResources->appendChild($tagJar5);
        $tagResources->appendChild($tagJar6);
        $tagResources->appendChild($tagJar7);

        $tagJnlp->appendChild($tagApplication);
        $tagApplication->appendChild($tagArg1);
        $tagApplication->appendChild($tagArg2);
        $tagApplication->appendChild($tagArg3);
        $tagApplication->appendChild($tagArg4);
        $tagApplication->appendChild($tagArg5);
        $tagApplication->appendChild($tagArg6);
        $tagApplication->appendChild($tagArg7);
        $tagApplication->appendChild($tagArg8);
        $tagApplication->appendChild($tagArg9);
        $tagApplication->appendChild($tagArg10);
        $tagApplication->appendChild($tagArg11);
        $tagApplication->appendChild($tagArg12);
        $tagApplication->appendChild($tagArg13);

        $jnlpDocument->appendChild($tagJnlp);

        $jnlpDocument->save($tmpPath . $jnlpFileNameExt);

        fopen($tmpPath . $jnlpFileName . '.lck', 'w+');

        return $response->withJson(['generatedJnlp' => $jnlpFileNameExt, 'jnlpUniqueId' => $jnlpUniqueId]);
    }

    public function renderJnlp(Request $request, Response $response, array $aArgs)
    {
        if (strtoupper(pathinfo($aArgs['jnlpUniqueId'], PATHINFO_EXTENSION)) != 'JNLP') {
            return $response->withStatus(403)->withJson(['errors' => 'File extension forbidden']);
        }

        $tmpPath = CoreConfigModel::getTmpPath();
        $jnlp = file_get_contents($tmpPath . $aArgs['jnlpUniqueId']);
        if ($jnlp === false) {
            return $response->withStatus(404)->withJson(['errors' => 'Jnlp file not found on ' . $tmpPath]);
        }

        $response->write($jnlp);

        return $response->withHeader('Content-Type', 'application/x-java-jnlp-file');
    }

    public function processJnlp(Request $request, Response $response, array $args)
    {
        $queryParams = $request->getQueryParams();
        $body = $request->getParsedBody();

        $tmpPath = CoreConfigModel::getTmpPath();

        if ($queryParams['action'] == 'editObject') {
            if ($queryParams['objectType'] == 'templateCreation') {
                $explodeFile = explode('.', $queryParams['objectId']);
                $extension = $explodeFile[count($explodeFile) - 1];
                $newFileOnTmp = "tmp_file_{$GLOBALS['id']}_{$args['jnlpUniqueId']}.{$extension}";

                $customId = CoreConfigModel::getCustomId();
                if (!empty($customId) && is_dir("custom/{$customId}/modules/templates/templates/styles/")) {
                    $stylesPath = "custom/{$customId}/modules/templates/templates/styles/";
                } else {
                    $stylesPath = 'modules/templates/templates/styles/';
                }
                if (strpos($queryParams['objectId'], $stylesPath) !== 0 || substr_count($queryParams['objectId'], '.') != 1) {
                    return $response->withStatus(400)->withJson(['errors' => 'Template path is not valid']);
                }

                $pathToCopy = $queryParams['objectId'];
            } elseif ($queryParams['objectType'] == 'templateModification') {
                $docserver = DocserverModel::getCurrentDocserver(['typeId' => 'TEMPLATES', 'collId' => 'templates', 'select' => ['path_template']]);
                $template = TemplateModel::getById(['id' => $queryParams['objectId'], 'select' => ['template_path', 'template_file_name']]);
                if (empty($template)) {
                    $xmlResponse = JnlpController::generateResponse(['type' => 'ERROR', 'data' => ['ERROR' => "Template does not exist"]]);
                    $response->write($xmlResponse);
                    return $response->withHeader('Content-Type', 'application/xml');
                }

                $pathToCopy = $docserver['path_template'] . str_replace('#', DIRECTORY_SEPARATOR, $template['template_path']) . $template['template_file_name'];
                $extension  = pathinfo($pathToCopy, PATHINFO_EXTENSION);
                $newFileOnTmp = "tmp_file_{$GLOBALS['id']}_{$args['jnlpUniqueId']}.{$extension}";
            } elseif ($queryParams['objectType'] == 'resourceCreation' || $queryParams['objectType'] == 'attachmentCreation') {
                $docserver = DocserverModel::getCurrentDocserver(['typeId' => 'TEMPLATES', 'collId' => 'templates', 'select' => ['path_template']]);
                $template = TemplateModel::getById(['id' => $queryParams['objectId'], 'select' => ['template_path', 'template_file_name']]);
                if (empty($template)) {
                    $xmlResponse = JnlpController::generateResponse(['type' => 'ERROR', 'data' => ['ERROR' => "Template does not exist"]]);
                    $response->write($xmlResponse);
                    return $response->withHeader('Content-Type', 'application/xml');
                }

                $pathToCopy = $docserver['path_template'] . str_replace('#', DIRECTORY_SEPARATOR, $template['template_path']) . $template['template_file_name'];
                $extension  = pathinfo($pathToCopy, PATHINFO_EXTENSION);
                $newFileOnTmp = "tmp_file_{$GLOBALS['id']}_{$args['jnlpUniqueId']}.{$extension}";

                $dataToMerge = ['userId' => $GLOBALS['id']];
                if (!empty($queryParams['objectTable'])) {
                    $decodedData = json_decode(base64_decode(urldecode($queryParams['objectTable'])), true);
                    if (!empty($decodedData)) {
                        $dataToMerge = array_merge($dataToMerge, $decodedData);
                    }
                }
                $mergedDocument = MergeController::mergeDocument([
                    'path' => $pathToCopy,
                    'data' => $dataToMerge
                ]);

                file_put_contents($tmpPath . $newFileOnTmp, base64_decode($mergedDocument['encodedDocument']));
                $pathToCopy = $tmpPath . $newFileOnTmp;
            } elseif ($queryParams['objectType'] == 'resourceModification') {
                if (!ResController::hasRightByResId(['resId' => [$queryParams['objectId']], 'userId' => $GLOBALS['id']])) {
                    $xmlResponse = JnlpController::generateResponse(['type' => 'ERROR', 'data' => ['ERROR' => "Resource out of perimeter"]]);
                    $response->write($xmlResponse);
                    return $response->withHeader('Content-Type', 'application/xml');
                }
                $resource = ResModel::getById(['resId' => $queryParams['objectId'], 'select' => ['docserver_id', 'path', 'filename', 'fingerprint']]);
                if (empty($resource['filename'])) {
                    $xmlResponse = JnlpController::generateResponse(['type' => 'ERROR', 'data' => ['ERROR' => "Resource has no file"]]);
                    $response->write($xmlResponse);
                    return $response->withHeader('Content-Type', 'application/xml');
                }

                $docserver  = DocserverModel::getByDocserverId(['docserverId' => $resource['docserver_id'], 'select' => ['path_template', 'docserver_type_id']]);
                $pathToCopy = $docserver['path_template'] . str_replace('#', DIRECTORY_SEPARATOR, $resource['path']) . $resource['filename'];

                $docserverType = DocserverTypeModel::getById(['id' => $docserver['docserver_type_id'], 'select' => ['fingerprint_mode']]);
                $fingerprint = StoreController::getFingerPrint(['filePath' => $pathToCopy, 'mode' => $docserverType['fingerprint_mode']]);
                if (empty($resource['fingerprint'])) {
                    ResModel::update(['set' => ['fingerprint' => $fingerprint], 'where' => ['res_id = ?'], 'data' => [$queryParams['objectId']]]);
                    $resource['fingerprint'] = $fingerprint;
                }

                if ($resource['fingerprint'] != $fingerprint) {
                    $xmlResponse = JnlpController::generateResponse(['type' => 'ERROR', 'data' => ['ERROR' => "Fingerprints do not match"]]);
                    $response->write($xmlResponse);
                    return $response->withHeader('Content-Type', 'application/xml');
                }

                $extension  = pathinfo($pathToCopy, PATHINFO_EXTENSION);
                $newFileOnTmp = "tmp_file_{$GLOBALS['id']}_{$args['jnlpUniqueId']}.{$extension}";
            } elseif ($queryParams['objectType'] == 'attachmentModification') {
                $attachment = AttachmentModel::getById(['id' => $queryParams['objectId'], 'select' => ['docserver_id', 'path', 'filename', 'res_id_master', 'fingerprint']]);
                if (empty($attachment)) {
                    $xmlResponse = JnlpController::generateResponse(['type' => 'ERROR', 'data' => ['ERROR' => "Attachment does not exist"]]);
                    $response->write($xmlResponse);
                    return $response->withHeader('Content-Type', 'application/xml');
                }
                if (!ResController::hasRightByResId(['resId' => [$attachment['res_id_master']], 'userId' => $GLOBALS['id']])) {
                    $xmlResponse = JnlpController::generateResponse(['type' => 'ERROR', 'data' => ['ERROR' => "Attachment out of perimeter"]]);
                    $response->write($xmlResponse);
                    return $response->withHeader('Content-Type', 'application/xml');
                }

                $docserver  = DocserverModel::getByDocserverId(['docserverId' => $attachment['docserver_id'], 'select' => ['path_template', 'docserver_type_id']]);
                $pathToCopy = $docserver['path_template'] . str_replace('#', DIRECTORY_SEPARATOR, $attachment['path']) . $attachment['filename'];

                $docserverType = DocserverTypeModel::getById(['id' => $docserver['docserver_type_id'], 'select' => ['fingerprint_mode']]);
                $fingerprint = StoreController::getFingerPrint(['filePath' => $pathToCopy, 'mode' => $docserverType['fingerprint_mode']]);
                if (empty($attachment['fingerprint'])) {
                    AttachmentModel::update(['set' => ['fingerprint' => $fingerprint], 'where' => ['res_id = ?'], 'data' => [$queryParams['objectId']]]);
                    $attachment['fingerprint'] = $fingerprint;
                }

                if ($attachment['fingerprint'] != $fingerprint) {
                    $xmlResponse = JnlpController::generateResponse(['type' => 'ERROR', 'data' => ['ERROR' => "Fingerprints do not match"]]);
                    $response->write($xmlResponse);
                    return $response->withHeader('Content-Type', 'application/xml');
                }

                $extension  = pathinfo($pathToCopy, PATHINFO_EXTENSION);
                $newFileOnTmp = "tmp_file_{$GLOBALS['id']}_{$args['jnlpUniqueId']}.{$extension}";
            } else {
                $xmlResponse = JnlpController::generateResponse(['type' => 'ERROR', 'data' => ['ERROR' => 'Wrong objectType']]);
                $response->write($xmlResponse);
                return $response->withHeader('Content-Type', 'application/xml');
            }

            if (($pathToCopy != $tmpPath . $newFileOnTmp) && (!file_exists($pathToCopy) || !copy($pathToCopy, $tmpPath . $newFileOnTmp))) {
                $xmlResponse = JnlpController::generateResponse(['type' => 'ERROR', 'data' => ['ERROR' => "Failed to copy on {$tmpPath} : {$pathToCopy}"]]);
                $response->write($xmlResponse);
                return $response->withHeader('Content-Type', 'application/xml');
            }

            $fileContent = file_get_contents($tmpPath . $newFileOnTmp);

            $result = [
                'STATUS'            => 'ok',
                'OBJECT_TYPE'       => $queryParams['objectType'],
                'OBJECT_TABLE'      => $queryParams['objectTable'],
                'OBJECT_ID'         => $queryParams['objectId'],
                'UNIQUE_ID'         => $queryParams['uniqueId'],
                'APP_PATH'          => 'start',
                'FILE_CONTENT'      => base64_encode($fileContent),
                'FILE_EXTENSION'    => $extension,
                'ERROR'             => '',
                'END_MESSAGE'       => ''
            ];
            $xmlResponse = JnlpController::generateResponse(['type' => 'SUCCESS', 'data' => $result]);
        } elseif ($queryParams['action'] == 'saveObject') {
            if (empty($body['fileContent']) || empty($body['fileExtension'])) {
                $xmlResponse = JnlpController::generateResponse(['type' => 'ERROR', 'data' => ['ERROR' => 'File content or file extension empty']]);
                $response->write($xmlResponse);
                return $response->withHeader('Content-Type', 'application/xml');
            }

            $encodedFileContent = str_replace(' ', '+', $body['fileContent']);
            $extension = str_replace(["\\", "/", '..'], '', $body['fileExtension']);
            $fileContent = base64_decode($encodedFileContent);
            $fileOnTmp = "tmp_file_{$GLOBALS['id']}_{$args['jnlpUniqueId']}.{$extension}";

            $file = fopen($tmpPath . $fileOnTmp, 'w');
            fwrite($file, $fileContent);
            fclose($file);

            if (!empty($queryParams['step']) && $queryParams['step'] == 'end') {
                if (file_exists("{$tmpPath}{$GLOBALS['id']}_maarchCM_{$args['jnlpUniqueId']}.lck")) {
                    unlink("{$tmpPath}{$GLOBALS['id']}_maarchCM_{$args['jnlpUniqueId']}.lck");
                }
                if (file_exists("{$tmpPath}{$GLOBALS['id']}_maarchCM_{$args['jnlpUniqueId']}.jnlp")) {
                    unlink("{$tmpPath}{$GLOBALS['id']}_maarchCM_{$args['jnlpUniqueId']}.jnlp");
                }
            }

            $xmlResponse = JnlpController::generateResponse(['type' => 'SUCCESS', 'data' => ['END_MESSAGE' => 'Update ok']]);
        } elseif ($queryParams['action'] == 'terminate') {
            if (file_exists("{$tmpPath}{$GLOBALS['id']}_maarchCM_{$args['jnlpUniqueId']}.lck")) {
                unlink("{$tmpPath}{$GLOBALS['id']}_maarchCM_{$args['jnlpUniqueId']}.lck");
            }
            if (file_exists("{$tmpPath}{$GLOBALS['id']}_maarchCM_{$args['jnlpUniqueId']}.jnlp")) {
                unlink("{$tmpPath}{$GLOBALS['id']}_maarchCM_{$args['jnlpUniqueId']}.jnlp");
            }

            $xmlResponse = JnlpController::generateResponse(['type' => 'SUCCESS', 'data' => ['END_MESSAGE' => 'Terminate ok']]);
        } else {
            $result = [
                'STATUS' => 'ko',
                'OBJECT_TYPE'       => $queryParams['objectType'],
                'OBJECT_TABLE'      => $queryParams['objectTable'],
                'OBJECT_ID'         => $queryParams['objectId'],
                'UNIQUE_ID'         => $queryParams['uniqueId'],
                'APP_PATH'          => 'start',
                'FILE_CONTENT'      => '',
                'FILE_EXTENSION'    => '',
                'ERROR'             => 'Missing parameters',
                'END_MESSAGE'       => ''
            ];
            $xmlResponse = JnlpController::generateResponse(['type' => 'ERROR', 'data' => $result]);
        }

        $response->write($xmlResponse);

        return $response->withHeader('Content-Type', 'application/xml');
    }

    public function isLockFileExisting(Request $request, Response $response, array $aArgs)
    {
        $tmpPath = CoreConfigModel::getTmpPath();
        $fileTrunk = "tmp_file_{$GLOBALS['id']}_{$aArgs['jnlpUniqueId']}";
        $lockFileName = "{$GLOBALS['id']}_maarchCM_{$aArgs['jnlpUniqueId']}.lck";

        $fileFound = false;
        if (file_exists($tmpPath . $lockFileName)) {
            $fileFound = true;
        }

        return $response->withJson(['lockFileFound' => $fileFound, 'fileTrunk' => $fileTrunk]);
    }

    private static function generateResponse(array $aArgs)
    {
        ValidatorModel::notEmpty($aArgs, ['type', 'data']);
        ValidatorModel::stringType($aArgs, ['type']);
        ValidatorModel::arrayType($aArgs, ['data']);

        $response = new \DomDocument('1.0', 'UTF-8');

        $tagRoot = $response->createElement($aArgs['type']);
        $response->appendChild($tagRoot);

        foreach ($aArgs['data'] as $key => $value) {
            $tag = $response->createElement($key, $value);
            $tagRoot->appendChild($tag);
        }

        return $response->saveXML();
    }
}
