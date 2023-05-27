<?php

/**
 * Copyright Maarch since 2008 under licence GPLv3.
 * See LICENCE.txt file at the root folder for more details.
 * This file is part of Maarch software.
 *
 */

/**
 * @brief Convert Thumbnail Controller
 * @author dev@maarch.org
 */

namespace Convert\controllers;

use Attachment\models\AttachmentModel;
use Convert\models\AdrModel;
use Docserver\controllers\DocserverController;
use Docserver\models\DocserverModel;
use Docserver\models\DocserverTypeModel;
use Parameter\models\ParameterModel;
use Resource\controllers\StoreController;
use Resource\models\ResModel;
use SrcCore\models\CoreConfigModel;
use SrcCore\models\ValidatorModel;

class ConvertThumbnailController
{
    public static function convert(array $aArgs)
    {
        ValidatorModel::notEmpty($aArgs, ['resId', 'type']);
        ValidatorModel::stringType($aArgs, ['type']);
        ValidatorModel::intVal($aArgs, ['resId']);

        if ($aArgs['type'] == 'resource') {
            $resource = ResModel::getById(['resId' => $aArgs['resId'], 'select' => ['filename', 'version']]);
            if (empty($resource)) {
                return ['errors' => '[ConvertThumbnail] Resource does not exist'];
            }
            if (empty($resource['filename'])) {
                return true;
            }

            $convertedDocument = AdrModel::getDocuments([
                'select'    => ['id', 'docserver_id', 'path', 'filename', 'fingerprint'],
                'where'     => ['res_id = ?', 'type in (?)', 'version = ?'],
                'data'      => [$aArgs['resId'], ['PDF', 'SIGN'], $resource['version']],
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
            $resource = AttachmentModel::getById(['id' => $aArgs['resId'], 'select' => [1]]);
            if (empty($resource)) {
                return ['errors' => '[ConvertThumbnail] Resource does not exist'];
            }

            $convertedDocument = AdrModel::getConvertedDocumentById([
                'select'    => ['id', 'docserver_id','path', 'filename', 'fingerprint'],
                'resId'     => $aArgs['resId'],
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
            return true;
        }

        $docserver = DocserverModel::getByDocserverId(['docserverId' => $convertedDocument['docserver_id'], 'select' => ['path_template']]);
        $pathToDocument = $docserver['path_template'] . str_replace('#', DIRECTORY_SEPARATOR, $convertedDocument['path']) . $convertedDocument['filename'];
        if (!file_exists($pathToDocument)) {
            return ['errors' => '[ConvertThumbnail] Document does not exist on docserver'];
        }

        $ext           = pathinfo($pathToDocument, PATHINFO_EXTENSION);
        $filename      = pathinfo($pathToDocument, PATHINFO_FILENAME);
        $tmpPath       = CoreConfigModel::getTmpPath();
        $fileNameOnTmp = rand() . $filename;

        if (in_array($ext, ['maarch', 'html'])) {
            if ($ext == 'maarch') {
                copy($pathToDocument, "{$tmpPath}{$fileNameOnTmp}.html");
                $pathToDocument = "{$tmpPath}{$fileNameOnTmp}.html";
            }
            $command = "wkhtmltoimage --height 600 --width 400 --quality 100 --zoom 0.2 "
                . escapeshellarg($pathToDocument) . ' ' . escapeshellarg("{$tmpPath}{$fileNameOnTmp}.png");
        } else {
            $size = '750x900';
            $parameter = ParameterModel::getById(['id' => 'thumbnailsSize', 'select' => ['param_value_string']]);
            if (!empty($parameter) && preg_match('/^[0-9]{3,4}[x][0-9]{3,4}$/', $parameter['param_value_string'])) {
                $size = $parameter['param_value_string'];
            }
            $command = "convert -thumbnail {$size} -background white -alpha remove "
                . escapeshellarg($pathToDocument) . '[0] ' . escapeshellarg("{$tmpPath}{$fileNameOnTmp}.png");
        }
        exec($command.' 2>&1', $output, $return);

        if ($return !== 0) {
            return ['errors' => "[ConvertThumbnail] ".implode(" ", $output)];
        }

        $content = file_get_contents("{$tmpPath}{$fileNameOnTmp}.png");
        $storeResult = DocserverController::storeResourceOnDocServer([
            'collId'            => $aArgs['type'] == 'resource' ? 'letterbox_coll' : 'attachments_coll',
            'docserverTypeId'   => 'TNL',
            'encodedResource'   => base64_encode($content),
            'format'            => 'png'
        ]);

        if (!empty($storeResult['errors'])) {
            return ['errors' => "[ConvertThumbnail] {$storeResult['errors']}"];
        }

        if ($aArgs['type'] == 'resource') {
            AdrModel::createDocumentAdr([
                'resId'         => $aArgs['resId'],
                'type'          => 'TNL',
                'docserverId'   => $storeResult['docserver_id'],
                'path'          => $storeResult['destination_dir'],
                'filename'      => $storeResult['file_destination_name'],
                'version'       => $resource['version']
            ]);
        } else {
            AdrModel::createAttachAdr([
                'resId'         => $aArgs['resId'],
                'type'          => 'TNL',
                'docserverId'   => $storeResult['docserver_id'],
                'path'          => $storeResult['destination_dir'],
                'filename'      => $storeResult['file_destination_name']
            ]);
        }

        return true;
    }

    public static function convertOnePage(array $args)
    {
        ValidatorModel::notEmpty($args, ['resId', 'page', 'type']);
        ValidatorModel::intVal($args, ['resId', 'page']);
        ValidatorModel::stringType($args, ['type']);

        if ($args['type'] == 'resource') {
            $resource = ResModel::getById(['resId' => $args['resId'], 'select' => ['filename', 'version']]);
        } elseif ($args['type'] == 'attachment') {
            $resource = AttachmentModel::getById(['id' => $args['resId'], 'select' => ['filename']]);
        }

        if (empty($resource)) {
            return ['errors' => '[ConvertThumbnail] Resource does not exist'];
        }
        if (empty($resource['filename'])) {
            return true;
        }

        if ($args['type'] == 'resource') {
            $convertedDocument = AdrModel::getDocuments([
                'select'    => ['id', 'docserver_id', 'path', 'filename', 'fingerprint'],
                'where'     => ['res_id = ?', 'type in (?)', 'version = ?'],
                'data'      => [$args['resId'], ['PDF', 'SIGN'], $resource['version']],
                'orderBy'   => ["type='SIGN' DESC"],
                'limit'     => 1
            ]);
            $convertedDocument = $convertedDocument[0] ?? null;
        } elseif ($args['type'] == 'attachment') {
            $convertedDocument = AdrModel::getConvertedDocumentById([
                'select'    => ['id', 'docserver_id','path', 'filename', 'fingerprint'],
                'resId'     => $args['resId'],
                'collId'    => 'attachment',
                'type'      => 'PDF'
            ]);
        }

        $docserver = DocserverModel::getByDocserverId(['docserverId' => $convertedDocument['docserver_id'], 'select' => ['path_template', 'docserver_type_id']]);
        if (empty($docserver['path_template']) || !is_dir($docserver['path_template'])) {
            return ['errors' => 'Docserver does not exist'];
        }

        $pathToDocument = $docserver['path_template'] . $convertedDocument['path'] . $convertedDocument['filename'];
        if (!is_file($pathToDocument) || !is_readable($pathToDocument)) {
            return ['errors' => 'Document not found on docserver or not readable'];
        }

        $filename = pathinfo($pathToDocument, PATHINFO_FILENAME);
        $tmpPath = CoreConfigModel::getTmpPath();

        $img = new \Imagick();
        $img->pingImage($pathToDocument);
        $pageCount = $img->getNumberImages();
        if ($pageCount < $args['page']) {
            return ['errors' => 'Page does not exist'];
        }

        $fileNameOnTmp = rand() . $filename;

        $convertPage = $args['page'] - 1;
        $command = "convert -density 500x500 -quality 100 -resize 1000x -background white -alpha remove "
            . escapeshellarg($pathToDocument) . "[{$convertPage}] " . escapeshellarg("{$tmpPath}{$fileNameOnTmp}.png");
        exec($command.' 2>&1', $output, $return);

        if ($return !== 0) {
            return ['errors' => "[ConvertThumbnail] Convert command failed for page {$args['page']} : ".implode(" ", $output)];
        }

        $content = file_get_contents("{$tmpPath}{$fileNameOnTmp}.png");

        $storeResult = DocserverController::storeResourceOnDocServer([
            'collId'            => $args['type'] == 'resource' ? 'letterbox_coll' : 'attachments_coll',
            'docserverTypeId'   => 'TNL',
            'encodedResource'   => base64_encode($content),
            'format'            => 'png'
        ]);
        if (!empty($storeResult['errors'])) {
            return ['errors' => $storeResult['errors']];
        }

        unlink("{$tmpPath}{$fileNameOnTmp}.png");

        if ($args['type'] == 'resource') {
            AdrModel::deleteDocumentAdr([
                'where' => ['res_id = ?', 'type = ?', 'version = ?'],
                'data'  => [$args['resId'], 'TNL' . $args['page'], $resource['version']]
            ]);
            AdrModel::createDocumentAdr([
                'resId'         => $args['resId'],
                'type'          => 'TNL' . $args['page'],
                'docserverId'   => $storeResult['docserver_id'],
                'path'          => $storeResult['destination_dir'],
                'filename'      => $storeResult['file_destination_name'],
                'version'       => $resource['version']
            ]);
        } else {
            AdrModel::createAttachAdr([
                'resId'         => $args['resId'],
                'type'          => 'TNL' . $args['page'],
                'docserverId'   => $storeResult['docserver_id'],
                'path'          => $storeResult['destination_dir'],
                'filename'      => $storeResult['file_destination_name']
            ]);
        }

        return true;
    }
}
