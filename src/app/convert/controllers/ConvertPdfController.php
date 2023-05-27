<?php

/**
 * Copyright Maarch since 2008 under licence GPLv3.
 * See LICENCE.txt file at the root folder for more details.
 * This file is part of Maarch software.
 *
 */

/**
 * @brief Convert PDF Controller
 * @author dev@maarch.org
 */

namespace Convert\controllers;

use Attachment\models\AttachmentModel;
use ContentManagement\controllers\OnlyOfficeController;
use Convert\models\AdrModel;
use Docserver\controllers\DocserverController;
use Docserver\models\DocserverModel;
use Docserver\models\DocserverTypeModel;
use Resource\controllers\StoreController;
use Resource\models\ResModel;
use Respect\Validation\Validator;
use Slim\Psr7\Request;
use SrcCore\http\Response;
use SrcCore\controllers\LogsController;
use SrcCore\controllers\CoreController;
use SrcCore\controllers\UrlController;
use SrcCore\models\CoreConfigModel;
use SrcCore\models\ValidatorModel;

class ConvertPdfController
{
    public static function convertInPdf(array $aArgs)
    {
        $tmpPath = CoreConfigModel::getTmpPath();
        $extension = pathinfo($aArgs['fullFilename'], PATHINFO_EXTENSION);
        if (strtolower($extension) == 'html' || strtolower($extension) == 'htm') {
            $pdfFilepath = str_replace('.' . $extension, '', $aArgs['fullFilename']) . '.pdf';
            $command = "wkhtmltopdf -B 10mm -L 10mm -R 10mm -T 10mm --load-error-handling ignore --load-media-error-handling ignore --encoding utf-8 " . $aArgs['fullFilename'] . " " . $pdfFilepath;

            // Check if xvfb-run is installed, and run wkhtmltopdf with it
            // Necessary when running in a headless debian server
            // src : https://github.com/wkhtmltopdf/wkhtmltopdf/issues/2037
            exec('whereis xvfb-run', $outputWhereIs, $returnWk);
            $outputWhereIs = explode(':', $outputWhereIs[0]);
            $xvfb = !empty($outputWhereIs[1]);

            if ($xvfb) {
                $command = 'xvfb-run -a -e /dev/stderr ' . $command;
            } else {
                $command = 'export DISPLAY=:0 && ' . $command;
            }

            exec($command.' 2>&1', $output, $return);
        } elseif (strtolower($extension) != 'pdf') {
            $url = str_replace('rest/', '', UrlController::getCoreUrl());
            if (OnlyOfficeController::canConvert(['url' => $url, 'fullFilename' => $aArgs['fullFilename']])) {
                $converted = OnlyOfficeController::convert(['fullFilename' => $aArgs['fullFilename'], 'url' => $url, 'userId' => $GLOBALS['id']]);
                if (empty($converted['errors'])) {
                    LogsController::add([
                        'isTech'    => true,
                        'moduleId'  => 'convert',
                        'level'     => 'DEBUG',
                        'tableName' => '',
                        'recordId'  => '',
                        'eventType' => "Convert Pdf with Only Office success",
                        'eventId'   => "document : {$aArgs['fullFilename']}"
                    ]);
                    return ['output' => [], 'return' => 0];
                } else {
                    LogsController::add([
                        'isTech'    => true,
                        'moduleId'  => 'convert',
                        'level'     => 'ERROR',
                        'tableName' => '',
                        'recordId'  => '',
                        'eventType' => "Convert Pdf with Only Office failed",
                        'eventId'   => "{$converted['errors']}, document : {$aArgs['fullFilename']}"
                    ]);
                }
            }

            ConvertPdfController::addBom($aArgs['fullFilename']);
            $command = "timeout 30 unoconv -f pdf " . escapeshellarg($aArgs['fullFilename']);

            exec('export HOME=' . $tmpPath . ' && ' . $command . ' 2>&1', $output, $return);
        }

        return ['output' => $output, 'return' => $return];
    }
    public static function tmpConvert(array $aArgs)
    {
        ValidatorModel::notEmpty($aArgs, ['fullFilename']);

        if (!file_exists($aArgs['fullFilename'])) {
            return ['errors' => '[ConvertPdf] Document '.$aArgs['fullFilename'].' does not exist'];
        }

        $convertedFile = ConvertPdfController::convertInPdf(['fullFilename' => $aArgs['fullFilename']]);

        $docInfo = pathinfo($aArgs['fullFilename']);
        $tmpPath = CoreConfigModel::getTmpPath();
        if (!file_exists($tmpPath.$docInfo["filename"].'.pdf')) {
            return ['errors' => '[ConvertPdf]  Conversion failed ! '. implode(" ", $convertedFile['output'])];
        } else {
            return ['fullFilename' => $tmpPath.$docInfo["filename"].'.pdf'];
        }
    }

    public static function convert(array $aArgs)
    {
        ValidatorModel::notEmpty($aArgs, ['collId', 'resId']);
        ValidatorModel::stringType($aArgs, ['collId']);
        ValidatorModel::intVal($aArgs, ['resId', 'version']);

        if ($aArgs['collId'] == 'letterbox_coll') {
            $resource = ResModel::getById(['resId' => $aArgs['resId'], 'select' => ['docserver_id', 'path', 'filename', 'format']]);
        } else {
            $resource = AttachmentModel::getById(['id' => $aArgs['resId'], 'select' => ['docserver_id', 'path', 'filename', 'format']]);
        }

        if (empty($resource['docserver_id']) || empty($resource['path']) || empty($resource['filename'])) {
            return ['errors' => '[ConvertPdf] Resource does not exist'];
        }

        $docserver = DocserverModel::getByDocserverId(['docserverId' => $resource['docserver_id'], 'select' => ['path_template']]);
        if (empty($docserver['path_template']) || !file_exists($docserver['path_template'])) {
            return ['errors' => '[ConvertPdf] Docserver does not exist'];
        }

        $pathToDocument = $docserver['path_template'] . str_replace('#', DIRECTORY_SEPARATOR, $resource['path']) . $resource['filename'];

        if (!file_exists($pathToDocument)) {
            return ['errors' => '[ConvertPdf] Document does not exist on docserver'];
        }

        $docInfo = pathinfo($pathToDocument);
        if (empty($docInfo['extension'])) {
            $docInfo['extension'] = $resource['format'];
        }

        $canConvert = ConvertPdfController::canConvert(['extension' => $docInfo['extension']]);
        if (!$canConvert) {
            return ['docserver_id' => $resource['docserver_id'], 'path' => $resource['path'], 'filename' => $resource['filename']];
        }

        $tmpPath = CoreConfigModel::getTmpPath();
        $fileNameOnTmp = rand() . $docInfo["filename"];

        copy($pathToDocument, $tmpPath.$fileNameOnTmp .'.'. strtolower($docInfo["extension"]));

        if (strtolower($docInfo["extension"]) != 'pdf') {
            $convertedFile = ConvertPdfController::convertInPdf(['fullFilename' => $tmpPath.$fileNameOnTmp.'.'.$docInfo["extension"]]);

            if (!file_exists($tmpPath.$fileNameOnTmp.'.pdf')) {
                return ['errors' => '[ConvertPdf]  Conversion failed ! '. implode(" ", $convertedFile['output'])];
            }
        }

        $resource = file_get_contents("{$tmpPath}{$fileNameOnTmp}.pdf");
        $storeResult = DocserverController::storeResourceOnDocServer([
            'collId'            => $aArgs['collId'],
            'docserverTypeId'   => 'CONVERT',
            'encodedResource'   => base64_encode($resource),
            'format'            => 'pdf'
        ]);

        if (!empty($storeResult['errors'])) {
            return ['errors' => "[ConvertPdf] {$storeResult['errors']}"];
        }

        if ($aArgs['collId'] == 'letterbox_coll') {
            AdrModel::createDocumentAdr([
                'resId'         => $aArgs['resId'],
                'type'          => 'PDF',
                'docserverId'   => $storeResult['docserver_id'],
                'path'          => $storeResult['destination_dir'],
                'filename'      => $storeResult['file_destination_name'],
                'version'       => $aArgs['version'] ?? 1,
                'fingerprint'   => $storeResult['fingerPrint']
            ]);
        } else {
            AdrModel::createAttachAdr([
                'resId'         => $aArgs['resId'],
                'type'          => 'PDF',
                'docserverId'   => $storeResult['docserver_id'],
                'path'          => $storeResult['destination_dir'],
                'filename'      => $storeResult['file_destination_name'],
                'fingerprint'   => $storeResult['fingerPrint']
            ]);
        }

        return ['docserver_id' => $storeResult['docserver_id'], 'path' => $storeResult['destination_dir'], 'filename' => $storeResult['file_destination_name'], 'fingerprint' => $storeResult['fingerPrint']];
    }

    public static function convertFromEncodedResource(array $aArgs)
    {
        ValidatorModel::notEmpty($aArgs, ['encodedResource']);
        ValidatorModel::stringType($aArgs, ['encodedResource', 'context']);

        $tmpPath = CoreConfigModel::getTmpPath();
        $tmpFilename = 'converting' . rand() . '_' . rand();

        file_put_contents($tmpPath.$tmpFilename . '.' . $aArgs['extension'], base64_decode($aArgs['encodedResource']));
        $convertedFile = ConvertPdfController::convertInPdf(['fullFilename' => $tmpPath.$tmpFilename . '.' . $aArgs['extension']]);

        if (!file_exists($tmpPath.$tmpFilename.'.pdf')) {
            return ['errors' => '[ConvertPdf]  Conversion failed ! '. implode(" ", $convertedFile['output'])];
        }

        if (is_file("{$tmpPath}{$tmpFilename}.{$aArgs['extension']}")) {
            unlink("{$tmpPath}{$tmpFilename}.{$aArgs['extension']}");
        }

        $resource = file_get_contents("{$tmpPath}{$tmpFilename}.pdf");

        $aReturn = [];

        if (!empty($aArgs['context']) && $aArgs['context'] == 'scan') {
            $aReturn["tmpFilename"] = $tmpFilename.'.pdf';
        } else {
            $aReturn["encodedResource"] = base64_encode($resource);
            unlink("{$tmpPath}{$tmpFilename}.pdf");
        }
        return $aReturn;
    }

    public static function getConvertedPdfById(array $args)
    {
        ValidatorModel::notEmpty($args, ['resId', 'collId']);
        ValidatorModel::intVal($args, ['resId']);

        if ($args['collId'] == 'letterbox_coll') {
            $resource = ResModel::getById(['resId' => $args['resId'], 'select' => ['version']]);

            $convertedDocument = AdrModel::getDocuments([
                'select'    => ['id', 'docserver_id', 'path', 'filename', 'fingerprint'],
                'where'     => ['res_id = ?', 'type in (?)', 'version = ?'],
                'data'      => [$args['resId'], ['PDF', 'SIGN'], $resource['version']],
                'orderBy'   => ["type='SIGN' DESC"],
                'limit'     => 1
            ]);
            $convertedDocument = $convertedDocument[0] ?? null;
            if (!empty($convertedDocument) && empty($convertedDocument['fingerprint'])) {
                $docserver = DocserverModel::getByDocserverId(['docserverId' => $convertedDocument['docserver_id'], 'select' => ['path_template', 'docserver_type_id']]);
                $pathToDocument = $docserver['path_template'] . str_replace('#', DIRECTORY_SEPARATOR, $convertedDocument['path']) . $convertedDocument['filename'];
                if (is_file($pathToDocument)) {
                    $docserverType = DocserverTypeModel::getById(['id' => $docserver['docserver_type_id'], 'select' => ['fingerprint_mode']]);
                    $fingerprint = StoreController::getFingerPrint(['filePath' => $pathToDocument, 'mode' => $docserverType['fingerprint_mode']]);
                    AdrModel::updateDocumentAdr(['set' => ['fingerprint' => $fingerprint], 'where' => ['id = ?'], 'data' => [$convertedDocument['id']]]);
                    $convertedDocument['fingerprint'] = $fingerprint;
                }
            }
        } else {
            $convertedDocument = AdrModel::getConvertedDocumentById([
                'select'    => ['id', 'docserver_id','path', 'filename', 'fingerprint'],
                'resId'     => $args['resId'],
                'collId'    => 'attachment',
                'type'      => 'PDF'
            ]);
            if (!empty($convertedDocument) && empty($convertedDocument['fingerprint'])) {
                $docserver = DocserverModel::getByDocserverId(['docserverId' => $convertedDocument['docserver_id'], 'select' => ['path_template', 'docserver_type_id']]);
                $pathToDocument = $docserver['path_template'] . str_replace('#', DIRECTORY_SEPARATOR, $convertedDocument['path']) . $convertedDocument['filename'];
                if (is_file($pathToDocument)) {
                    $docserverType = DocserverTypeModel::getById(['id' => $docserver['docserver_type_id'], 'select' => ['fingerprint_mode']]);
                    $fingerprint = StoreController::getFingerPrint(['filePath' => $pathToDocument, 'mode' => $docserverType['fingerprint_mode']]);
                    AdrModel::updateAttachmentAdr(['set' => ['fingerprint' => $fingerprint], 'where' => ['id = ?'], 'data' => [$convertedDocument['id']]]);
                    $convertedDocument['fingerprint'] = $fingerprint;
                }
            }
        }

        if (empty($convertedDocument)) {
            $convertedDocument = ConvertPdfController::convert([
                'resId'     => $args['resId'],
                'collId'    => $args['collId'],
                'version'   => $resource['version'] ?? 1
            ]);
        }

        return $convertedDocument;
    }

    public static function canConvert(array $args)
    {
        ValidatorModel::notEmpty($args, ['extension']);
        ValidatorModel::stringType($args, ['extension']);

        $canConvert = false;
        $loadedXml = CoreConfigModel::getXmlLoaded(['path' => 'config/extensions.xml']);
        if ($loadedXml) {
            foreach ($loadedXml->FORMAT as $value) {
                if (strtoupper((string)$value->name) == strtoupper($args['extension']) && (string)$value->canConvert == 'true') {
                    $canConvert = true;
                    break;
                }
            }
        }

        return $canConvert;
    }

    public static function addBom($filePath)
    {
        $extension = pathinfo($filePath, PATHINFO_EXTENSION);
        if (strtolower($extension) == strtolower('txt')) {
            $content = file_get_contents($filePath);
            $bom = chr(239) . chr(187) . chr(191); # use BOM to be on safe side
            file_put_contents($filePath, $bom.$content);
        }
    }

    public function convertedFile(Request $request, Response $response)
    {
        $body = $request->getParsedBody();

        if (!Validator::notEmpty()->validate($body['name'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body name is empty']);
        }
        if (!Validator::notEmpty()->validate($body['base64'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body base64 is empty']);
        }

        $ext         = substr($body['name'], strrpos($body['name'], '.') + 1);
        $file        = base64_decode($body['base64']);
        $mimeAndSize = CoreController::getMimeTypeAndFileSize(['encodedFile' => $body['base64']]);
        if (!empty($mimeAndSize['errors'])) {
            return $response->withStatus(400)->withJson(['errors' => $mimeAndSize['errors']]);
        }
        $mimeType    = $mimeAndSize['mime'];
        $size        = $mimeAndSize['size'];

        if (strtolower($ext) == 'pdf' && strtolower($mimeType) == 'application/pdf') {
            if ($body['context'] == 'scan') {
                $tmpPath = CoreConfigModel::getTmpPath();
                $tmpFilename = 'scan_converting' . rand() . '.pdf';

                file_put_contents($tmpPath . $tmpFilename, $file);
                $return['tmpFilename'] = $tmpFilename;
            } else {
                $return['encodedResource'] = $body['base64'];
            }
            return $response->withJson($return);
        } else {
            $fileAccepted  = StoreController::isFileAllowed(['extension' => $ext, 'type' => $mimeType]);
            $maxFilesizeMo = ini_get('upload_max_filesize');
            $uploadMaxFilesize = StoreController::getBytesSizeFromPhpIni(['size' => $maxFilesizeMo]);
            $canConvert    = ConvertPdfController::canConvert(['extension' => $ext]);

            if (!$fileAccepted) {
                return $response->withStatus(400)->withJson(['errors' => 'File type not allowed. Extension : ' . $ext . '. Mime Type : ' . $mimeType . '.']);
            } elseif ($size > $uploadMaxFilesize) {
                $maximumSizeLabel = round($maxFilesizeMo / 1048576, 3) . ' Mo';
                return $response->withStatus(400)->withJson(['errors' => 'File maximum size is exceeded ('.$maximumSizeLabel.')']);
            } elseif (!$canConvert) {
                return $response->withStatus(400)->withJson(['errors' => 'File accepted but can not be converted in pdf']);
            }
            $convertion = ConvertPdfController::convertFromEncodedResource(['encodedResource' => $body['base64'], 'context' => $body['context'] ?? null, 'extension' => $ext]);
            if (empty($convertion['errors'])) {
                return $response->withJson($convertion);
            } else {
                return $response->withStatus(400)->withJson($convertion);
            }
        }
    }

    public function getConvertedFileByFilename(Request $request, Response $response, array $args)
    {
        $tmpPath = CoreConfigModel::getTmpPath();

        if (!file_exists("{$tmpPath}{$args['filename']}")) {
            return $response->withStatus(400)->withJson(['errors' => 'File does not exist']);
        }

        $resource = file_get_contents("{$tmpPath}{$args['filename']}");
        $extension = pathinfo("{$tmpPath}{$args['filename']}", PATHINFO_EXTENSION);
        $mimeType = mime_content_type("{$tmpPath}{$args['filename']}");

        unlink("{$tmpPath}{$args['filename']}");
        $encodedResource = base64_encode($resource);

        $encodedFiles = ['encodedResource' => $encodedResource];

        $encodedFiles['type'] = $mimeType;
        $encodedFiles['extension'] = $extension;

        $queryParams = $request->getQueryParams();
        if (!empty($queryParams['convert'])) {
            if (ConvertPdfController::canConvert(['extension' => $extension])) {
                $convertion = ConvertPdfController::convertFromEncodedResource(['encodedResource' => $encodedResource, 'extension' => $extension]);
                if (!empty($convertion['errors'])) {
                    $encodedFiles['convertedResourceErrors'] = $convertion['errors'];
                } else {
                    $encodedFiles['encodedConvertedResource'] = $convertion['encodedResource'];
                }
            }
        }

        return $response->withJson($encodedFiles);
    }

    public function getConvertedFileFromEncodedFile(Request $request, Response $response)
    {
        $body = $request->getParsedBody();

        if (!Validator::stringType()->notEmpty()->validate($body['format'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body format is empty or not a string']);
        } elseif (!Validator::stringType()->notEmpty()->validate($body['encodedFile'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body encodedFile is empty']);
        }

        if (!ConvertPdfController::canConvert(['extension' => $body['format']])) {
            return $response->withStatus(400)->withJson(['errors' => 'Format can not be converted']);
        }

        $convertion = ConvertPdfController::convertFromEncodedResource(['encodedResource' => $body['encodedFile'], 'extension' => $body['format']]);
        if (!empty($convertion['errors'])) {
            return $response->withStatus(400)->withJson(['errors' => $convertion['errors']]);
        }

        return $response->withJson($convertion);
    }
}
