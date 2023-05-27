<?php

/**
 * Copyright Maarch since 2008 under licence GPLv3.
 * See LICENCE.txt file at the root folder for more details.
 * This file is part of Maarch software.
 *
 */

/**
 * @brief Convert Pdf Script
 * @author dev@maarch.org
 */

namespace Convert\scripts;

require 'vendor/autoload.php';

use Attachment\models\AttachmentModel;
use ContentManagement\controllers\OnlyOfficeController;
use Convert\controllers\ConvertPdfController;
use Convert\models\AdrModel;
use Docserver\controllers\DocserverController;
use Docserver\models\DocserverModel;
use Resource\models\ResModel;
use SrcCore\controllers\LogsController;
use SrcCore\models\CoreConfigModel;
use SrcCore\models\DatabasePDO;
use User\models\UserModel;

ConvertPdfScript::launchConvert($argv);

class ConvertPdfScript
{
    public static function initalize($args)
    {
        $customId = null;

        $cmd = array_search('--customId', $args);
        if ($cmd > 0) {
            $customId = $args[$cmd + 1];
        }
        $cmd = array_search('--resId', $args);
        if ($cmd > 0) {
            $resId = $args[$cmd + 1];
        }
        $cmd = array_search('--type', $args);
        if ($cmd > 0) {
            $type = $args[$cmd + 1];
        }
        $cmd = array_search('--userId', $args);
        if ($cmd > 0) {
            $userId = $args[$cmd+1];
        }
        $cmd = array_search('--coreUrl', $args);
        if ($cmd > 0) {
            $coreUrl = $args[$cmd+1];
        }

        if (empty($resId) || empty($type) || empty($userId)) {
            echo 'Missing arguments';
            exit();
        }

        return ['customId' => $customId, 'resId' => $resId, 'type' => $type, 'userId' => $userId, 'coreUrl' => $coreUrl];
    }

    public static function convert(array $args)
    {
        if ($args['type'] == 'resource') {
            $resource = ResModel::getById(['resId' => $args['resId'], 'select' => ['docserver_id', 'path', 'filename', 'format']]);
        } else {
            $resource = AttachmentModel::getById(['id' => $args['resId'], 'select' => ['docserver_id', 'path', 'filename', 'format']]);
        }

        if (empty($resource['docserver_id']) || empty($resource['path']) || empty($resource['filename'])) {
            return ['errors' => 'Resource does not exist'];
        }

        $docserver = DocserverModel::getByDocserverId(['docserverId' => $resource['docserver_id'], 'select' => ['path_template']]);
        if (empty($docserver['path_template']) || !is_dir($docserver['path_template'])) {
            return ['errors' => 'Docserver does not exist'];
        }

        $pathToDocument = $docserver['path_template'] . str_replace('#', DIRECTORY_SEPARATOR, $resource['path']) . $resource['filename'];
        if (!is_file($pathToDocument)) {
            return ['errors' => 'Document does not exist on docserver'];
        }

        $docInfo = pathinfo($pathToDocument);
        if (empty($docInfo['extension'])) {
            $docInfo['extension'] = $resource['format'];
        }

        $canConvert = ConvertPdfController::canConvert(['extension' => $docInfo['extension']]);
        if (!$canConvert) {
            return ['errors' => 'Document can not be converted'];
        }

        $tmpPath = CoreConfigModel::getTmpPath();
        $fileNameOnTmp = rand() . $docInfo["filename"];

        copy($pathToDocument, "{$tmpPath}{$fileNameOnTmp}.{$docInfo['extension']}");

        if (strtolower($docInfo['extension']) != 'pdf') {
            $fullFilename = "{$tmpPath}{$fileNameOnTmp}.{$docInfo['extension']}";
            $converted = false;
            $output = [];
            if (OnlyOfficeController::canConvert(['url' => $args['coreUrl'], 'fullFilename' => $fullFilename])) {
                $converted = OnlyOfficeController::convert(['fullFilename' => $fullFilename, 'url' => $args['coreUrl'], 'userId' => $args['userId']]);

                if (empty($converted['errors'])) {
                    LogsController::add([
                        'isTech'    => true,
                        'moduleId'  => 'convert',
                        'level'     => 'DEBUG',
                        'tableName' => '',
                        'recordId'  => '',
                        'eventType' => "Convert Pdf with Only Office success",
                        'eventId'   => "document : {$fullFilename}"
                    ]);
                } else {
                    LogsController::add([
                        'isTech'    => true,
                        'moduleId'  => 'convert',
                        'level'     => 'ERROR',
                        'tableName' => '',
                        'recordId'  => '',
                        'eventType' => "Convert Pdf with Only Office failed",
                        'eventId'   => "{$converted['errors']}, document : {$fullFilename}"
                    ]);
                }
                $converted = empty($converted['errors']);
            }
            if (!$converted) {
                ConvertPdfController::addBom($fullFilename);
                $command = "timeout 30 unoconv -f pdf " . escapeshellarg($fullFilename);
                exec('export HOME=' . $tmpPath . ' && '.$command, $output, $return);
            }

            if (!file_exists($tmpPath.$fileNameOnTmp.'.pdf')) {
                return ['errors' => 'Conversion failed ! '. implode(" ", $output)];
            }
        }

        $resource = file_get_contents("{$tmpPath}{$fileNameOnTmp}.pdf");
        $storeResult = DocserverController::storeResourceOnDocServer([
            'collId'            => $args['type'] == 'resource' ? 'letterbox_coll' : 'attachments_coll',
            'docserverTypeId'   => 'CONVERT',
            'encodedResource'   => base64_encode($resource),
            'format'            => 'pdf'
        ]);

        if (!empty($storeResult['errors'])) {
            return ['errors' => $storeResult['errors']];
        }

        if ($args['type'] == 'resource') {
            AdrModel::createDocumentAdr([
                'resId'         => $args['resId'],
                'type'          => 'PDF',
                'docserverId'   => $storeResult['docserver_id'],
                'path'          => $storeResult['destination_dir'],
                'filename'      => $storeResult['file_destination_name'],
                'version'       => $args['version'] ?? 1,
                'fingerprint'   => $storeResult['fingerPrint']
            ]);
        } else {
            AdrModel::createAttachAdr([
                'resId'         => $args['resId'],
                'type'          => 'PDF',
                'docserverId'   => $storeResult['docserver_id'],
                'path'          => $storeResult['destination_dir'],
                'filename'      => $storeResult['file_destination_name'],
                'fingerprint'   => $storeResult['fingerPrint']
            ]);
        }

        return true;
    }

    public static function launchConvert(array $args)
    {
        $args = ConvertPdfScript::initalize($args);

        DatabasePDO::reset();
        new DatabasePDO(['customId' => $args['customId']]);

        $GLOBALS['customId'] = $args['customId'];

        $isConverted = ConvertPdfScript::convert($args);

        if (!empty($isConverted['errors'])) {
            $currentUser = UserModel::getById(['id' => $args['userId'], 'select' => ['user_id']]);
            $GLOBALS['login'] = $currentUser['user_id'];

            LogsController::add([
                'isTech'    => true,
                'moduleId'  => 'convert',
                'level'     => 'ERROR',
                'tableName' => $args['type'],
                'recordId'  => $args['resId'],
                'eventType' => "Convert Pdf failed : {$isConverted['errors']}",
                'eventId'   => $args['resId']
            ]);
        }
    }
}
