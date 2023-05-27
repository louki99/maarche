<?php

/**
 * Copyright Maarch since 2008 under licence GPLv3.
 * See LICENCE.txt file at the root folder for more details.
 * This file is part of Maarch software.
 */

/**
 * @brief Collabora Online Controller
 *
 * @author dev@maarch.org
 */

namespace ContentManagement\controllers;

use Attachment\models\AttachmentModel;
use Configuration\models\ConfigurationModel;
use Convert\models\AdrModel;
use Docserver\models\DocserverModel;
use Docserver\models\DocserverTypeModel;
use Firebase\JWT\JWT;
use Group\controllers\PrivilegeController;
use Resource\controllers\ResController;
use Resource\controllers\StoreController;
use Resource\models\ResModel;
use Respect\Validation\Validator;
use Slim\Psr7\Request;
use SrcCore\http\Response;
use SrcCore\controllers\CoreController;
use SrcCore\controllers\UrlController;
use SrcCore\models\CoreConfigModel;
use SrcCore\models\CurlModel;
use SrcCore\models\ValidatorModel;
use Template\controllers\TemplateController;
use Template\models\TemplateAssociationModel;
use Template\models\TemplateModel;
use User\models\UserEntityModel;
use User\models\UserModel;

class CollaboraOnlineController
{
    public function getFileContent(Request $request, Response $response, array $args)
    {
        $queryParams = $request->getQueryParams();

        if (!Validator::stringType()->notEmpty()->validate($queryParams['access_token'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Query access_token is empty or not a string']);
        }

        $tokenCheckResult = CollaboraOnlineController::checkToken(['token' => $queryParams['access_token'], 'id' => $args['id']]);
        if (!empty($tokenCheckResult['errors'])) {
            return $response->withStatus($tokenCheckResult['code'])->withJson(['errors' => $tokenCheckResult['errors']]);
        }

        $document = CollaboraOnlineController::getDocument([
            'id'     => $args['id'],
            'type'   => $tokenCheckResult['type'],
            'format' => $tokenCheckResult['format']
        ]);

        if (!empty($document['errors'])) {
            return $response->withStatus($document['code'])->withJson(['errors' => $document['errors']]);
        }

        if (!empty($document['docserver_id'])) {
            $docserver = DocserverModel::getByDocserverId(['docserverId' => $document['docserver_id'], 'select' => ['path_template', 'docserver_type_id']]);
            if (empty($docserver['path_template']) || !file_exists($docserver['path_template'])) {
                return $response->withStatus(400)->withJson(['errors' => 'Docserver does not exist']);
            }
        } else {
            $docserver['path_template'] = '';
        }

        $pathToDocument = $docserver['path_template'] . str_replace('#', DIRECTORY_SEPARATOR, $document['path']) . $document['filename'];
        if (!file_exists($pathToDocument)) {
            return $response->withStatus(404)->withJson(['errors' => 'Document not found on docserver']);
        }

        if ($tokenCheckResult['type'] == 'resourceModification' || $tokenCheckResult['type'] == 'attachmentModification') {
            $docserverType = DocserverTypeModel::getById(['id' => $docserver['docserver_type_id'], 'select' => ['fingerprint_mode']]);
            $fingerprint = StoreController::getFingerPrint(['filePath' => $pathToDocument, 'mode' => $docserverType['fingerprint_mode']]);

            if (empty($document['fingerprint']) && $tokenCheckResult['type'] == 'resourceModification') {
                ResModel::update(['set' => ['fingerprint' => $fingerprint], 'where' => ['res_id = ?'], 'data' => [$args['id']]]);
                $document['fingerprint'] = $fingerprint;
            } elseif (empty($document['fingerprint']) && $tokenCheckResult['type'] == 'attachmentModification') {
                AttachmentModel::update(['set' => ['fingerprint' => $fingerprint], 'where' => ['res_id = ?'], 'data' => [$args['id']]]);
                $document['fingerprint'] = $fingerprint;
            }

            if ($document['fingerprint'] != $fingerprint) {
                return $response->withStatus(400)->withJson(['errors' => 'Fingerprints do not match']);
            }
        }

        if ($tokenCheckResult['type'] == 'resourceCreation' || $tokenCheckResult['type'] == 'attachmentCreation') {
            $dataToMerge = ['userId' => $GLOBALS['id']];
            if (!empty($tokenCheckResult['data']) && is_array($tokenCheckResult['data'])) {
                $dataToMerge = array_merge($dataToMerge, $tokenCheckResult['data']);
            }

            $mergedDocument = MergeController::mergeDocument([
                'path' => $pathToDocument,
                'data' => $dataToMerge
            ]);
            $content = $mergedDocument['encodedDocument'];
            $fileContent = base64_decode($content);
        } else {
            $fileContent = file_get_contents($pathToDocument);
            if ($fileContent === false) {
                return $response->withStatus(404)->withJson(['errors' => 'Document not found']);
            }

            $content = base64_encode($fileContent);
            $fileContent = base64_decode($content);
        }

        $finfo    = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->buffer($fileContent);
        $pathInfo = pathinfo($pathToDocument);

        if ($tokenCheckResult['type'] == 'templateEncoded') {
            unlink($document['path'] . $document['filename']);
        }

        $response->write($fileContent);
        $response = $response->withAddedHeader('Content-Disposition', "attachment; filename=maarch.{$pathInfo['extension']}");
        return $response->withHeader('Content-Type', $mimeType);
    }

    public function getCheckFileInfo(Request $request, Response $response, array $args)
    {
        $queryParams = $request->getQueryParams();

        if (!Validator::stringType()->notEmpty()->validate($queryParams['access_token'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Query access_token is empty or not a string']);
        }

        $tokenCheckResult = CollaboraOnlineController::checkToken(['token' => $queryParams['access_token'], 'id' => $args['id']]);
        if (!empty($tokenCheckResult['errors'])) {
            return $response->withStatus($tokenCheckResult['code'])->withJson(['errors' => $tokenCheckResult['errors']]);
        }

        $document = CollaboraOnlineController::getDocument([
            'id'     => $args['id'],
            'type'   => $tokenCheckResult['type'],
            'format' => $tokenCheckResult['format']
        ]);

        if (!empty($document['errors'])) {
            return $response->withStatus($document['code'])->withJson(['errors' => $document['errors']]);
        }

        $modificationDate = new \DateTime($document['modification_date']);
        $modificationDate->setTimezone(new \DateTimeZone('UTC'));
        $modificationDate = $modificationDate->format(\DateTime::ISO8601);

        return $response->withJson([
            'BaseFileName'            => $document['filename'],
            'Size'                    => $document['filesize'],
            'UserCanNotWriteRelative' => true,
            'UserCanWrite'            => true,
            'DisablePrint'            => true,
            'HideSaveOption'          => true,
            'UserFriendlyName'        => UserModel::getLabelledUserById(['id' => $GLOBALS['id']]),
            'OwnerId'                 => $GLOBALS['id'],
            'UserId'                  => $GLOBALS['id'],
            'LastModifiedTime'        => $modificationDate
        ]);
    }

    public function saveFile(Request $request, Response $response, array $args)
    {
        $queryParams = $request->getQueryParams();

        if (!Validator::stringType()->notEmpty()->validate($queryParams['access_token'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Query access_token is empty or not a string']);
        }

        $tokenCheckResult = CollaboraOnlineController::checkToken(['token' => $queryParams['access_token'], 'id' => $args['id']]);
        if (!empty($tokenCheckResult['errors'])) {
            return $response->withStatus($tokenCheckResult['code'])->withJson(['errors' => $tokenCheckResult['errors']]);
        }

        $document = CollaboraOnlineController::getDocument([
            'id'     => $args['id'],
            'type'   => $tokenCheckResult['type'],
            'format' => $tokenCheckResult['format']
        ]);

        if (!empty($document['errors'])) {
            return $response->withStatus($document['code'])->withJson(['errors' => $document['errors']]);
        }

        $fileContent = $request->getBody()->getContents();

        $extension = pathinfo($document['filename'], PATHINFO_EXTENSION);
        $tmpPath = CoreConfigModel::getTmpPath();
        $filename = "collabora_{$GLOBALS['id']}_{$tokenCheckResult['type']}_{$args['id']}.{$extension}";

        $put = file_put_contents($tmpPath . $filename, $fileContent);
        if ($put === false) {
            return $response->withStatus(400)->withJson(['errors' => 'File put contents failed']);
        }

        return $response->withStatus(200);
    }

    public function getTmpFile(Request $request, Response $response)
    {
        $body = $request->getParsedBody();

        if (!Validator::stringType()->notEmpty()->validate($body['token'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Query token is empty or not a string']);
        }

        $tokenCheckResult = CollaboraOnlineController::checkToken(['token' => $body['token']]);
        if (!empty($tokenCheckResult['errors'])) {
            return $response->withStatus($tokenCheckResult['code'])->withJson(['errors' => $tokenCheckResult['errors']]);
        }

        $document = CollaboraOnlineController::getDocument([
            'id'     => $tokenCheckResult['resId'],
            'type'   => $tokenCheckResult['type'],
            'format' => $tokenCheckResult['format']
        ]);
        if (!empty($document['errors'])) {
            return $response->withStatus($document['code'])->withJson(['errors' => $document['errors']]);
        }

        $extension = pathinfo($document['filename'], PATHINFO_EXTENSION);
        $filename = "collabora_{$GLOBALS['id']}_{$tokenCheckResult['type']}_{$tokenCheckResult['resId']}.{$extension}";
        $tmpPath = CoreConfigModel::getTmpPath();
        $pathToDocument = $tmpPath . $filename;

        if (!file_exists($pathToDocument)) {
            return $response->withStatus(404)->withJson(['errors' => 'Document not found']);
        }

        $fileContent = file_get_contents($pathToDocument);
        if ($fileContent === false) {
            return $response->withStatus(404)->withJson(['errors' => 'Document not found']);
        }

        $content = base64_encode($fileContent);

        return $response->withJson(['content' => $content, 'format' => $extension]);
    }

    public function deleteTmpFile(Request $request, Response $response)
    {
        $queryParams = $request->getQueryParams();

        if (!Validator::stringType()->notEmpty()->validate($queryParams['token'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Query token is empty or not a string']);
        }

        $tokenCheckResult = CollaboraOnlineController::checkToken(['token' => $queryParams['token']]);
        if (!empty($tokenCheckResult['errors'])) {
            return $response->withStatus($tokenCheckResult['code'])->withJson(['errors' => $tokenCheckResult['errors']]);
        }

        $document = CollaboraOnlineController::getDocument([
            'id'     => $tokenCheckResult['resId'],
            'type'   => $tokenCheckResult['type'],
            'format' => $tokenCheckResult['format']
        ]);
        if (!empty($document['errors'])) {
            return $response->withStatus($document['code'])->withJson(['errors' => $document['errors']]);
        }

        $extension = pathinfo($document['filename'], PATHINFO_EXTENSION);
        $filename = "collabora_{$GLOBALS['id']}_{$tokenCheckResult['type']}_{$tokenCheckResult['resId']}.{$extension}";
        $tmpPath = CoreConfigModel::getTmpPath();
        $pathToDocument = $tmpPath . $filename;

        if (file_exists($pathToDocument)) {
            unlink($pathToDocument);
        }

        return $response->withStatus(204);
    }

    public static function isAvailable(Request $request, Response $response)
    {
        $configuration = ConfigurationModel::getByPrivilege(['privilege' => 'admin_document_editors', 'select' => ['value']]);
        $configuration = !empty($configuration['value']) ? json_decode($configuration['value'], true) : [];

        if (empty($configuration) || empty($configuration['collaboraonline'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Collabora Online is not enabled', 'lang' => 'collaboraOnlineNotEnabled']);
        } elseif (empty($configuration['collaboraonline']['uri'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Collabora Online server_uri is empty', 'lang' => 'uriIsEmpty']);
        } elseif (empty($configuration['collaboraonline']['port'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Collabora Online server_port is empty', 'lang' => 'portIsEmpty']);
        }

        $uri  = $configuration['collaboraonline']['uri'];
        $port = (int)$configuration['collaboraonline']['port'];

        $isAvailable = DocumentEditorController::isAvailable(['uri' => $uri, 'port' => $port]);

        if (!empty($isAvailable['errors'])) {
            return $response->withStatus(400)->withJson($isAvailable);
        }

        return $response->withJson(['isAvailable' => $isAvailable]);
    }

    public static function getConfiguration(Request $request, Response $response)
    {
        $configuration = ConfigurationModel::getByPrivilege(['privilege' => 'admin_document_editors', 'select' => ['value']]);
        $configuration = !empty($configuration['value']) ? json_decode($configuration['value'], true) : [];

        if (empty($configuration) || empty($configuration['collaboraonline']) || empty($configuration['collaboraonline']['uri'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Collabora Online is not enabled', 'lang' => 'collaboraOnlineNotEnabled']);
        }

        $body = $request->getParsedBody();
        if (!Validator::notEmpty()->intVal()->validate($body['resId'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body resId is empty or not an integer']);
        }
        if (!Validator::stringType()->notEmpty()->validate($body['type'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body type is empty or not a string']);
        }
        if (!empty($body['format']) && !Validator::stringType()->validate($body['format'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body format is not a string']);
        }
        if (!empty($body['path']) && !Validator::stringType()->validate($body['path'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body path is not a string']);
        }
        if (!empty($body['data']) && !Validator::arrayType()->validate($body['data'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body data is not a string']);
        }
        if (!empty($body['lang']) && !Validator::stringType()->validate($body['lang'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body lang is not a string']);
        }

        $document = CollaboraOnlineController::getDocument([
            'id'     => $body['resId'],
            'type'   => $body['type'],
            'format' => $body['format'],
            'path'   => $body['path'] ?? ''
        ]);

        if (!empty($document['errors'])) {
            return $response->withStatus($document['code'])->withJson(['errors' => $document['errors']]);
        }

        $extension = pathinfo($document['filename'], PATHINFO_EXTENSION);

        $url = $configuration['collaboraonline']['uri'] . ':' . $configuration['collaboraonline']['port'];

        $coreUrl   = str_replace('rest/', '', UrlController::getCoreUrl());
        $serverSsl = $configuration['collaboraonline']['ssl'];
        if (!empty($serverSsl)) {
            if (strpos($coreUrl, 'https') === false) {
                return $response->withStatus(400)->withJson(['errors' => 'Collabora Online cannot be configured to use SSL if Maarch Courrier is not using SSL']);
            }
            $url = 'https://' . $url;
        } else {
            if (strpos($coreUrl, 'https') !== false) {
                return $response->withStatus(400)->withJson(['errors' => 'Collabora Online has to be configured to use SSL if Maarch Courrier is using SSL']);
            }
        }

        $discovery = CurlModel::exec([
            'url'    => $url . '/hosting/discovery',
            'method' => 'GET',
            'isXml'  => true
        ]);

        if ($discovery['code'] != 200) {
            return $response->withStatus(400)->withJson(['errors' => 'Collabora discovery failed']);
        }

        $version4 = false;
        foreach ($discovery['headers'] as $header) {
            if (strpos($header, 'UserAgent') !== false) {
                $version4 = strpos($header, 'LOOLWSD WOPI Agent 4') !== false;
            }
        }

        $urlSrc = null;
        if ($version4) {
            foreach ($discovery['response']->{'net-zone'}->app as $app) {
                if ($app->action['ext'] == $extension) {
                    $urlSrc = (string) $app->action['urlsrc'];
                    break;
                }
            }
        } else {
            foreach ($discovery['response']->{'net-zone'}->app as $app) {
                foreach ($app->action as $action) {
                    if ($action['ext'] == $extension) {
                        $urlSrc = (string) $action['urlsrc'];
                        break;
                    }
                }
            }
        }

        if (empty($urlSrc)) {
            return $response->withStatus(400)->withJson(['errors' => 'File cannot be edited with Collabora Online', 'lang' => 'collaboraOnlineEditDenied']);
        }

        $dataToMerge = ['userId' => $GLOBALS['id']];
        if (!empty($body['data']) && is_array($body['data'])) {
            $dataToMerge = array_merge($dataToMerge, $body['data']);
        }

        $payload = [
            'userId' => $GLOBALS['id'],
            'resId'  => $body['resId'],
            'type'   => $body['type'],
            'format' => $extension,
            'data'   => json_encode($dataToMerge)
        ];

        $jwt = JWT::encode($payload, CoreConfigModel::getEncryptKey());

        $urlIFrame = $urlSrc . 'WOPISrc=' . $coreUrl . 'rest/wopi/files/' . $body['resId'] . '&access_token=' . $jwt . '&NotWOPIButIframe=true';

        if (!empty($body['lang'])) {
            $urlIFrame .= '&lang=' . $body['lang'];
        }

        return $response->withJson(['url' => $urlIFrame, 'token' => $jwt, 'coreUrl' => $coreUrl]);
    }

    public function saveTmpEncodedDocument(Request $request, Response $response)
    {
        $body = $request->getParsedBody();

        if (!PrivilegeController::hasPrivilege(['privilegeId' => 'admin_templates', 'userId' => $GLOBALS['id']])) {
            return ['code' => 403, 'errors' => 'Service forbidden'];
        }

        if (!Validator::stringType()->notEmpty()->validate($body['content'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body content is empty or not a string']);
        }
        if (!Validator::stringType()->notEmpty()->validate($body['format'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body format is empty or not a string']);
        }
        if (!Validator::notEmpty()->intVal()->validate($body['key'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body key is empty or not an integer']);
        }

        $fileContent = base64_decode($body['content']);
        $finfo    = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->buffer($fileContent);
        if (!StoreController::isFileAllowed(['extension' => $body['format'], 'type' => $mimeType]) || !in_array($mimeType, TemplateController::AUTHORIZED_MIMETYPES)) {
            return $response->withStatus(400)->withJson(['errors' => _WRONG_FILE_TYPE . ' : '.$mimeType]);
        }

        $tmpPath = CoreConfigModel::getTmpPath();
        $filename = "collabora_encoded_{$GLOBALS['id']}_{$body['key']}.{$body['format']}";
        $fileContent = base64_decode($body['content']);

        $put = file_put_contents($tmpPath . $filename, $fileContent);
        if ($put === false) {
            return $response->withStatus(400)->withJson(['errors' => 'File put contents failed']);
        }

        return $response->withStatus(204);
    }

    private static function checkToken(array $args)
    {
        ValidatorModel::notEmpty($args, ['token']);
        ValidatorModel::stringType($args, ['token']);
        ValidatorModel::intVal($args, ['id']);

        try {
            $jwt = JWT::decode($args['token'], CoreConfigModel::getEncryptKey(), ['HS256']);
        } catch (\Exception $e) {
            return ['code' => 401, 'errors' => 'Collabora Online access token is invalid'];
        }

        if (empty($jwt->resId) || empty($jwt->userId) || empty($jwt->type)) {
            return ['code' => 401, 'errors' => 'Collabora Online access token is invalid'];
        }

        if (!empty($args['id']) && $jwt->resId != $args['id']) {
            return ['code' => 401, 'errors' => 'Collabora Online access token is invalid'];
        }

        CoreController::setGlobals(['userId' => $jwt->userId]);

        return [
            'type'   => $jwt->type,
            'resId'  => $jwt->resId,
            'format' => $jwt->format,
            'data'   => json_decode($jwt->data, true)
        ];
    }

    public static function getDocument(array $args)
    {
        ValidatorModel::notEmpty($args, ['id', 'type']);
        ValidatorModel::stringType($args, ['type', 'format', 'path']);
        ValidatorModel::intVal($args, ['id']);

        if ($args['type'] == 'resourceCreation' || $args['type'] == 'attachmentCreation') {
            $document = TemplateModel::getById(['select' => ['template_file_name', 'template_target', 'template_path', 'template_file_name'], 'id' => $args['id']]);
            if (empty($document)) {
                return ['code' => 400, 'errors' => 'Document does not exist'];
            }

            $templateAssociation = TemplateAssociationModel::get([
                'select' => ['value_field'],
                'where'  => ['template_id = ?'],
                'data'   => [$args['id']]
            ]);
            $templateAssociation = array_column($templateAssociation, 'value_field');

            $userEntities = UserEntityModel::get([
                'select' => ['entity_id'],
                'where'  => ['user_id = ?'],
                'data'   => [$GLOBALS['id']]
            ]);
            $userEntities = array_column($userEntities, 'entity_id');

            $inPerimeter = false;
            foreach ($userEntities as $userEntity) {
                if (in_array($userEntity, $templateAssociation)) {
                    $inPerimeter = true;
                    break;
                }
            }

            if (!$inPerimeter) {
                return ['code' => 400, 'errors' => 'Template is out of perimeter'];
            }

            $templateTarget = $args['type'] == 'resourceCreation' ? 'indexingFile' : 'attachments';
            if ($document['template_target'] != $templateTarget) {
                return ['code' => 400, 'errors' => 'Template is not for resource creation'];
            }
            $document['filename'] = $document['template_file_name'];
            $document['docserver_id'] = 'TEMPLATES';
            $document['path'] = $document['template_path'];

            $document['modification_date'] = new \DateTime('now');
            $document['modification_date'] = $document['modification_date']->format(\DateTime::ISO8601);
        } elseif ($args['type'] == 'resourceModification') {
            if (!ResController::hasRightByResId(['resId' => [$args['id']], 'userId' => $GLOBALS['id']])) {
                return ['code' => 403, 'errors' => 'Document out of perimeter'];
            }

            $document = ResModel::getById(['select' => ['docserver_id', 'path', 'filename', 'version', 'fingerprint', 'modification_date'], 'resId' => $args['id']]);

            // If the document has a signed version, it cannot be edited
            $convertedDocument = AdrModel::getDocuments([
                'select' => ['docserver_id', 'path', 'filename', 'fingerprint'],
                'where'  => ['res_id = ?', 'type = ?', 'version = ?'],
                'data'   => [$args['id'], 'SIGN', $document['version']],
                'limit'  => 1
            ]);
            if (!empty($convertedDocument[0])) {
                return ['code' => 400, 'errors' => 'Document was signed : it cannot be edited'];
            }
        } elseif ($args['type'] == 'attachmentModification') {
            $document = AttachmentModel::getById([
                'select' => ['res_id_master', 'filename', 'filesize', 'modification_date', 'docserver_id', 'path', 'fingerprint', 'status'],
                'id' => $args['id']
            ]);
            if (empty($document) || in_array($document['status'], ['DEL', 'OBS'])) {
                return ['code' => 400, 'errors' => 'Document does not exist'];
            }

            if (!ResController::hasRightByResId(['resId' => [$document['res_id_master']], 'userId' => $GLOBALS['id']])) {
                return ['code' => 403, 'errors' => 'Document out of perimeter'];
            }

            if ($document['status'] == 'SIGN') {
                return ['code' => 400, 'errors' => 'Document was signed : it cannot be edited'];
            }
        } elseif ($args['type'] == 'templateModification') {
            if (!PrivilegeController::hasPrivilege(['privilegeId' => 'admin_templates', 'userId' => $GLOBALS['id']])) {
                return ['code' => 403, 'errors' => 'Service forbidden'];
            }

            $document = TemplateModel::getById(['select' => ['template_file_name', 'template_target', 'template_path', 'template_file_name'], 'id' => $args['id']]);
            if (empty($document)) {
                return ['code' => 400, 'errors' => 'Document does not exist'];
            }

            $document['filename'] = $document['template_file_name'];
            $document['docserver_id'] = 'TEMPLATES';
            $document['path'] = $document['template_path'];

            $document['modification_date'] = new \DateTime('now');
            $document['modification_date'] = $document['modification_date']->format(\DateTime::ISO8601);
        } elseif ($args['type'] == 'templateEncoded') {
            if (!PrivilegeController::hasPrivilege(['privilegeId' => 'admin_templates', 'userId' => $GLOBALS['id']])) {
                return ['code' => 403, 'errors' => 'Service forbidden'];
            }

            $document['filename'] = "collabora_encoded_{$GLOBALS['id']}_{$args['id']}.{$args['format']}";
            $document['docserver_id'] = '';
            $document['path'] = CoreConfigModel::getTmpPath();

            $document['modification_date'] = new \DateTime('now');
            $document['modification_date'] = $document['modification_date']->format(\DateTime::ISO8601);
        } elseif ($args['type'] == 'templateCreation') {
            if (!PrivilegeController::hasPrivilege(['privilegeId' => 'admin_templates', 'userId' => $GLOBALS['id']])) {
                return ['code' => 403, 'errors' => 'Service forbidden'];
            }

            $document['filename'] = "collabora_template_{$GLOBALS['id']}_{$args['id']}.{$args['format']}";
            $document['docserver_id'] = '';
            $document['path'] = CoreConfigModel::getTmpPath();

            if (!file_exists($document['path'] . $document['filename'])) {
                if (empty($args['path'])) {
                    return ['code' => 400, 'errors' => 'Argument path is missing'];
                }

                $customId = CoreConfigModel::getCustomId();
                if (!empty($customId) && is_dir("custom/{$customId}/modules/templates/templates/styles/")) {
                    $stylesPath = "custom/{$customId}/modules/templates/templates/styles/";
                } else {
                    $stylesPath = 'modules/templates/templates/styles/';
                }
                if (strpos($args['path'], $stylesPath) !== 0 || substr_count($args['path'], '.') != 1) {
                    return ['code' => 400, 'errors' => 'Template path is not valid'];
                }

                if (!file_exists($args['path'])) {
                    return ['code' => 400, 'errors' => 'Document does not exists'];
                }

                $fileContent = file_get_contents($args['path']);
                if ($fileContent === false) {
                    return ['code' => 400, 'errors' => 'Document does not exists'];
                }

                $result = file_put_contents($document['path'] . $document['filename'], $fileContent);
                if ($result === false) {
                    return ['code' => 400, 'errors' => 'Document does not exists'];
                }
            }

            $document['modification_date'] = new \DateTime('now');
            $document['modification_date'] = $document['modification_date']->format(\DateTime::ISO8601);
        } else {
            return ['code' => 400, 'errors' => 'Not a valid document type'];
        }

        if (empty($document['filename'])) {
            return ['code' => 400, 'errors' => 'Document has no file'];
        }

        return $document;
    }
}
