<?php

/**
* Copyright Maarch since 2008 under licence GPLv3.
* See LICENCE.txt file at the root folder for more details.
* This file is part of Maarch software.
*
*/

/**
* @brief Docserver Controller
* @author dev@maarch.org
*/

namespace Docserver\controllers;

use Docserver\models\DocserverTypeModel;
use Group\controllers\PrivilegeController;
use History\controllers\HistoryController;
use Resource\controllers\StoreController;
use Respect\Validation\Validator;
use Slim\Psr7\Request;
use SrcCore\http\Response;
use SrcCore\models\ValidatorModel;
use Docserver\models\DocserverModel;

class DocserverController
{
    public function get(Request $request, Response $response)
    {
        if (!PrivilegeController::hasPrivilege(['privilegeId' => 'admin_docservers', 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }

        $sortedDocservers = [];
        $docservers = DocserverModel::get();
        foreach ($docservers as $docserver) {
            $sortedDocservers[$docserver['docserver_type_id']][] = DocserverController::getFormattedDocserver(['docserver' => $docserver]);
        }

        $docserversTypes = DocserverTypeModel::get(['select' => ['docserver_type_id', 'docserver_type_label'], 'orderBy' => ['docserver_type_label']]);

        return $response->withJson(['docservers' => $sortedDocservers, 'types' => $docserversTypes]);
    }

    public function getById(Request $request, Response $response, array $aArgs)
    {
        if (!PrivilegeController::hasPrivilege(['privilegeId' => 'admin_docservers', 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }

        $docserver = DocserverModel::getById(['id' => $aArgs['id']]);
        if (empty($docserver)) {
            return $response->withStatus(400)->withJson(['errors' => 'Docserver not found']);
        }

        return $response->withJson($docserver);
    }

    /**
     * Get Path of migration folder (shadow docserver, not available in docserver administration)
     */
    public static function getMigrationFolderPath()
    {
        $docserver = DocserverModel::getCurrentDocserver(['typeId' => 'DOC', 'collId' => 'letterbox_coll', 'select' => ['path_template']]);
        if (empty($docserver)) {
            return ['errors' => 'Docserver letterbox_coll  does not exist'];
        }
        $directoryPath = explode('/', rtrim($docserver['path_template'], '/'));
        array_pop($directoryPath);
        $directoryPath = implode('/', $directoryPath);

        if (!is_dir($directoryPath . '/migration')) {
            if (!is_writable($directoryPath)) {
                return ['errors' => 'Directory path is not writable : ' . $directoryPath];
            }
            mkdir($directoryPath . '/migration', 0755, true);
        } elseif (!is_writable($directoryPath . '/migration')) {
            return ['errors' => 'Directory path is not writable : ' . $directoryPath . '/migration'];
        }
        return ['path' => $directoryPath . '/migration'];
    }

    public function create(Request $request, Response $response)
    {
        if (!PrivilegeController::hasPrivilege(['privilegeId' => 'admin_docservers', 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }

        $data = $request->getParsedBody();

        $check = Validator::stringType()->notEmpty()->validate($data['docserver_id']) && preg_match("/^[\w-]*$/", $data['docserver_id']) && (strlen($data['docserver_id']) <= 32);
        $check = $check && Validator::stringType()->notEmpty()->validate($data['docserver_type_id']);
        $check = $check && Validator::stringType()->notEmpty()->validate($data['device_label']);
        $check = $check && Validator::notEmpty()->intVal()->validate($data['size_limit_number']);
        $check = $check && Validator::stringType()->notEmpty()->validate($data['path_template']);
        $check = $check && Validator::stringType()->notEmpty()->validate($data['coll_id']);
        if (!$check) {
            return $response->withStatus(400)->withJson(['errors' => 'Bad Request']);
        }

        $existingDocserver = DocserverModel::getByDocserverId(['docserverId' => $data['docserver_id'], 'select' => ['1']]);
        if (!empty($existingDocserver)) {
            return $response->withStatus(400)->withJson(['errors' => _ID. ' ' . _ALREADY_EXISTS]);
        }
        $existingDocserverType = DocserverTypeModel::get(['select' => ['1'], 'where' => ['docserver_type_id = ?'], 'data' => [$data['docserver_type_id']]]);
        if (empty($existingDocserverType)) {
            return $response->withStatus(400)->withJson(['errors' => 'Docserver type does not exist']);
        }
        if (!DocserverController::isPathAvailable(['path' => $data['path_template']])) {
            return $response->withStatus(400)->withJson(['errors' => _PATH_OF_DOCSERVER_UNAPPROACHABLE]);
        }

        $existingCurrentDocserver = DocserverModel::getCurrentDocserver([
            'select' => ['1'],
            'typeId' => $data['docserver_type_id'],
            'collId' => $data['coll_id']
        ]);
        $data['is_readonly'] = empty($existingCurrentDocserver) ? 'N' : 'Y';
        
        if (substr($data['path_template'], -1) != DIRECTORY_SEPARATOR) {
            $data['path_template'] .= "/";
        }

        $id = DocserverModel::create($data);
        HistoryController::add([
            'tableName' => 'docservers',
            'recordId'  => $data['docserver_id'],
            'eventType' => 'ADD',
            'info'      => _DOCSERVER_ADDED . " : {$data['docserver_id']}",
            'moduleId'  => 'docserver',
            'eventId'   => 'docserverCreation',
        ]);

        return $response->withJson(['docserver' => $id]);
    }

    public function update(Request $request, Response $response, array $aArgs)
    {
        if (!PrivilegeController::hasPrivilege(['privilegeId' => 'admin_docservers', 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }

        $data = $request->getParsedBody();

        $check = Validator::stringType()->notEmpty()->validate($data['device_label']);
        $check = $check && Validator::notEmpty()->intVal()->validate($data['size_limit_number']);
        $check = $check && Validator::stringType()->notEmpty()->validate($data['path_template']);
        $check = $check && Validator::boolType()->validate($data['is_readonly']);
        if (!$check) {
            return $response->withStatus(400)->withJson(['errors' => 'Bad Request']);
        }

        $docserver = DocserverModel::getById(['id' => $aArgs['id'], 'select' => ['docserver_type_id', 'coll_id']]);
        if (empty($docserver)) {
            return $response->withStatus(400)->withJson(['errors' => 'Docserver not found']);
        }
        if (!DocserverController::isPathAvailable(['path' => $data['path_template']])) {
            return $response->withStatus(400)->withJson(['errors' => _PATH_OF_DOCSERVER_UNAPPROACHABLE]);
        }
        if (!$data['is_readonly']) {
            $existingCurrentDocserver = DocserverModel::getCurrentDocserver([
                'select' => ['id'],
                'typeId' => $docserver['docserver_type_id'],
                'collId' => $docserver['coll_id']
            ]);
            if (!empty($existingCurrentDocserver) && $existingCurrentDocserver['id'] != $aArgs['id']) {
                return $response->withStatus(400)->withJson(['errors' => _DOCSERVER_ACTIVATED_EXISTS]);
            }
        }

        if (substr($data['path_template'], -1) != DIRECTORY_SEPARATOR) {
            $data['path_template'] .= "/";
        }

        DocserverModel::update([
            'set'   => [
                'device_label'          => $data['device_label'],
                'size_limit_number'     => $data['size_limit_number'],
                'path_template'         => $data['path_template'],
                'is_readonly'           => empty($data['is_readonly']) ? 'N' : 'Y'
            ],
            'where' => ['id = ?'],
            'data'  => [$aArgs['id']]
        ]);

        HistoryController::add([
            'tableName' => 'docservers',
            'recordId'  => $aArgs['id'],
            'eventType' => 'UP',
            'info'      => _DOCSERVER_UPDATED . " : {$data['device_label']}",
            'moduleId'  => 'docserver',
            'eventId'   => 'docserverModification',
        ]);

        $docserver = DocserverModel::getById(['id' => $aArgs['id']]);

        return $response->withJson(['docserver' => DocserverController::getFormattedDocserver(['docserver' => $docserver])]);
    }

    public function delete(Request $request, Response $response, array $aArgs)
    {
        if (!PrivilegeController::hasPrivilege(['privilegeId' => 'admin_docservers', 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }

        $docserver = DocserverModel::getById(['id' => $aArgs['id']]);
        if (empty($docserver)) {
            return $response->withStatus(400)->withJson(['errors' => 'Docserver does not exist']);
        }

        DocserverModel::delete(['id' => $aArgs['id']]);
        HistoryController::add([
            'tableName' => 'docservers',
            'recordId'  => $aArgs['id'],
            'eventType' => 'DEL',
            'info'      => _DOCSERVER_DELETED . " : {$aArgs['id']}",
            'moduleId'  => 'docserver',
            'eventId'   => 'docserverSuppression',
        ]);

        return $response->withJson(['success' => 'success']);
    }

    public static function storeResourceOnDocServer(array $aArgs)
    {
        ValidatorModel::notEmpty($aArgs, ['collId', 'docserverTypeId', 'encodedResource', 'format']);
        ValidatorModel::stringType($aArgs, ['collId', 'docserverTypeId', 'encodedResource', 'format']);

        $docserver = DocserverModel::getCurrentDocserver(['collId' => $aArgs['collId'], 'typeId' => $aArgs['docserverTypeId']]);
        if (empty($docserver)) {
            return ['errors' => '[storeRessourceOnDocserver] No available Docserver with type ' . $aArgs['docserverTypeId']];
        }

        $pathOnDocserver = DocserverController::createPathOnDocServer(['path' => $docserver['path_template']]);
        if (!empty($pathOnDocserver['errors'])) {
            return ['errors' => '[storeRessourceOnDocserver] ' . $pathOnDocserver['errors']];
        }

        $docinfo = DocserverController::getNextFileNameInDocServer(['pathOnDocserver' => $pathOnDocserver['pathToDocServer']]);
        if (!empty($docinfo['errors'])) {
            return ['errors' => '[storeRessourceOnDocserver] ' . $docinfo['errors']];
        }
        $docinfo['fileDestinationName'] .= '.' . strtolower($aArgs['format']);

        $docserverTypeObject = DocserverTypeModel::getById(['id' => $docserver['docserver_type_id']]);
        $copyResult = DocserverController::copyOnDocServer([
            'encodedResource'       => $aArgs['encodedResource'],
            'destinationDir'        => $docinfo['destinationDir'],
            'fileDestinationName'   => $docinfo['fileDestinationName'],
        ]);
        if (!empty($copyResult['errors'])) {
            return ['errors' => '[storeRessourceOnDocserver] ' . $copyResult['errors']];
        }

        $directory = substr($docinfo['destinationDir'], strlen($docserver['path_template']));

        DocserverModel::update([
            'set'   => [
                'actual_size_number' => $docserver['actual_size_number'] + $aArgs['fileInfos']['size']
            ],
            'where' => ['id = ?'],
            'data'  => [$docserver['id']]
        ]);

        return [
            'path_template'         => $docserver['path_template'],
            'destination_dir'       => $directory,
            'directory'             => $directory,
            'docserver_id'          => $docserver['docserver_id'],
            'file_destination_name' => $docinfo['fileDestinationName'],
            'fileSize'              => $copyResult['fileSize'],
            'fingerPrint'           => StoreController::getFingerPrint([
                'filePath'  => $docinfo['destinationDir'] . $docinfo['fileDestinationName'],
                'mode'      => $docserverTypeObject['fingerprint_mode']
            ])
        ];
    }

    public static function createPathOnDocServer(array $args)
    {
        ValidatorModel::notEmpty($args, ['path']);
        ValidatorModel::stringType($args, ['path']);

        if (!is_dir($args['path'])) {
            return ['errors' => '[createPathOnDocServer] Path does not exist : ' . $args['path']];
        } elseif (!is_readable($args['path']) || !is_writable($args['path'])) {
            return ['errors' => '[createPathOnDocServer] Path is not readable or writable : ' . $args['path']];
        }

        error_reporting(0);
        umask(0022);

        $yearPath = $args['path'] . date('Y') . '/';
        if (!is_dir($yearPath)) {
            mkdir($yearPath, 0770);
            if (DIRECTORY_SEPARATOR == '/' && !empty($GLOBALS['apacheUserAndGroup'])) {
                exec('chown ' . escapeshellarg($GLOBALS['apacheUserAndGroup']) . ' ' . escapeshellarg($yearPath));
            }
            umask(0022);
            chmod($yearPath, 0770);
        }

        $monthPath = $yearPath . date('m') . '/';
        if (!is_dir($monthPath)) {
            mkdir($monthPath, 0770);
            if (DIRECTORY_SEPARATOR == '/' && !empty($GLOBALS['apacheUserAndGroup'])) {
                exec('chown ' . escapeshellarg($GLOBALS['apacheUserAndGroup']) . ' ' . escapeshellarg($monthPath));
            }
            umask(0022);
            chmod($monthPath, 0770);
        }

        $pathToDS = $monthPath;
        if (!empty($GLOBALS['wb'])) {
            $pathToDS = "{$monthPath}BATCH/{$GLOBALS['wb']}/";
            if (!is_dir($pathToDS)) {
                mkdir($pathToDS, 0770, true);
                if (DIRECTORY_SEPARATOR == '/' && !empty($GLOBALS['apacheUserAndGroup'])) {
                    exec('chown ' . escapeshellarg($GLOBALS['apacheUserAndGroup']) . ' ' . escapeshellarg($monthPath));
                }
                umask(0022);
                chmod($monthPath, 0770);
            }
        }

        return ['pathToDocServer' => $pathToDS];
    }

    public static function getNextFileNameInDocServer(array $aArgs)
    {
        ValidatorModel::notEmpty($aArgs, ['pathOnDocserver']);
        ValidatorModel::stringType($aArgs, ['pathOnDocserver']);

        if (!is_dir($aArgs['pathOnDocserver'])) {
            return ['errors' => '[getNextFileNameInDocServer] PathOnDocserver does not exist'];
        }

        umask(0022);

        $aFiles = scandir($aArgs['pathOnDocserver']);
        array_shift($aFiles); // Remove . line
        array_shift($aFiles); // Remove .. line

        if (file_exists($aArgs['pathOnDocserver'] . '/package_information')) {
            unset($aFiles[array_search('package_information', $aFiles)]);
        }
        if (is_dir($aArgs['pathOnDocserver'] . '/BATCH')) {
            unset($aFiles[array_search('BATCH', $aFiles)]);
        }

        $filesNb = count($aFiles);
        if ($filesNb == 0) {
            $zeroOnePath = $aArgs['pathOnDocserver'] . '0001/';

            if (!mkdir($zeroOnePath, 0770)) {
                return ['errors' => '[getNextFileNameInDocServer] Directory creation failed: ' . $zeroOnePath];
            } else {
                if (DIRECTORY_SEPARATOR == '/' && !empty($GLOBALS['apacheUserAndGroup'])) {
                    exec('chown ' . escapeshellarg($GLOBALS['apacheUserAndGroup']) . ' ' . escapeshellarg($zeroOnePath));
                }
                umask(0022);
                chmod($zeroOnePath, 0770);

                return [
                    'destinationDir'        => $zeroOnePath,
                    'fileDestinationName'   => '0001_' . mt_rand(),
                ];
            }
        } else {
            $destinationDir = $aArgs['pathOnDocserver'] . str_pad(count($aFiles), 4, '0', STR_PAD_LEFT) . '/';
            $aFilesBis = scandir($aArgs['pathOnDocserver'] . strval(str_pad(count($aFiles), 4, '0', STR_PAD_LEFT)));
            array_shift($aFilesBis); // Remove . line
            array_shift($aFilesBis); // Remove .. line

            $filesNbBis = count($aFilesBis);
            if ($filesNbBis >= 1000) { //If number of files >= 1000 then creates a new subdirectory
                $zeroNumberPath = $aArgs['pathOnDocserver'] . str_pad($filesNb + 1, 4, '0', STR_PAD_LEFT) . '/';

                if (!mkdir($zeroNumberPath, 0770)) {
                    return ['errors' => '[getNextFileNameInDocServer] Directory creation failed: ' . $zeroNumberPath];
                } else {
                    if (DIRECTORY_SEPARATOR == '/' && !empty($GLOBALS['apacheUserAndGroup'])) {
                        exec('chown ' . escapeshellarg($GLOBALS['apacheUserAndGroup']) . ' ' . escapeshellarg($zeroNumberPath));
                    }
                    umask(0022);
                    chmod($zeroNumberPath, 0770);

                    return [
                        'destinationDir'        => $zeroNumberPath,
                        'fileDestinationName'   => '0001_' . mt_rand(),
                    ];
                }
            } else {
                $higher = $filesNbBis + 1;
                foreach ($aFilesBis as $value) {
                    $currentFileName = explode('.', $value);
                    if ($higher <= (int)$currentFileName[0]) {
                        $higher = (int)$currentFileName[0] + 1;
                    }
                }

                return [
                    'destinationDir'        => $destinationDir,
                    'fileDestinationName'   => str_pad($higher, 4, '0', STR_PAD_LEFT) . '_' . mt_rand(),
                ];
            }
        }
    }

    public static function copyOnDocServer(array $aArgs)
    {
        ValidatorModel::notEmpty($aArgs, ['destinationDir', 'fileDestinationName', 'encodedResource']);
        ValidatorModel::stringType($aArgs, ['destinationDir', 'fileDestinationName', 'encodedResource']);

        if (file_exists($aArgs['destinationDir'] . $aArgs['fileDestinationName'])) {
            return ['errors' => '[copyOnDocserver] File already exists: ' . $aArgs['destinationDir'] . $aArgs['fileDestinationName']];
        }

        error_reporting(0);

        if (!is_dir($aArgs['destinationDir'])) {
            mkdir($aArgs['destinationDir'], 0770, true);
            if (DIRECTORY_SEPARATOR == '/' && !empty($GLOBALS['apacheUserAndGroup'])) {
                exec('chown ' . escapeshellarg($GLOBALS['apacheUserAndGroup']) . ' ' . escapeshellarg($aArgs['destinationDir']));
            }
            umask(0022);
            chmod($aArgs['destinationDir'], 0770);
        }

        if (file_put_contents($aArgs['destinationDir'] . $aArgs['fileDestinationName'], base64_decode($aArgs['encodedResource'])) === false) {
            return ['errors' => '[copyOnDocserver] Copy on the docserver failed'];
        }
        if (DIRECTORY_SEPARATOR == '/' && !empty($GLOBALS['apacheUserAndGroup'])) {
            exec('chown ' . escapeshellarg($GLOBALS['apacheUserAndGroup']) . ' ' . escapeshellarg($aArgs['destinationDir'] . $aArgs['fileDestinationName']));
        }
        umask(0022);
        chmod($aArgs['destinationDir'] . $aArgs['fileDestinationName'], 0770);

        $aArgs['destinationDir'] = str_replace(DIRECTORY_SEPARATOR, '#', $aArgs['destinationDir']);

        return ['fileSize' => filesize(str_replace('#', '/', $aArgs['destinationDir']) . $aArgs['fileDestinationName'])];
    }

    private static function getFormattedDocserver(array $aArgs)
    {
        ValidatorModel::notEmpty($aArgs, ['docserver']);
        ValidatorModel::arrayType($aArgs, ['docserver']);

        $docserver = $aArgs['docserver'];

        $docserver['is_readonly'] = ($docserver['is_readonly'] == 'Y');
        $docserver['actual_size_number'] = DocserverController::getDocserverSize(['path' => $docserver['path_template']]);
        if ($docserver['actual_size_number'] > 1000000000) {
            $docserver['actualSizeFormatted'] = round($docserver['actual_size_number'] / 1000000000, 3) . ' Go';
        } else {
            $docserver['actualSizeFormatted'] = round($docserver['actual_size_number'] / 1000000, 3) . ' Mo';
        }
        $docserver['limitSizeFormatted'] = round($docserver['size_limit_number'] / 1000000000, 3); // Giga
        $docserver['percentage'] = round($docserver['actual_size_number'] / $docserver['size_limit_number'] * 100, 2);

        return $docserver;
    }

    private static function getDocserverSize(array $aArgs)
    {
        ValidatorModel::notEmpty($aArgs, ['path']);
        ValidatorModel::stringType($aArgs, ['path']);

        $size = 0;

        if (DocserverController::isPathAvailable(['path' => $aArgs['path']])) {
            $exec = shell_exec("du -s -b {$aArgs['path']}");
            $execPlode = explode("\t", $exec);
            if (isset($execPlode[0]) && is_numeric($execPlode[0])) {
                $size = $execPlode[0];
            }
        }

        return (int)$size;
    }

    private static function isPathAvailable(array $aArgs)
    {
        ValidatorModel::notEmpty($aArgs, ['path']);
        ValidatorModel::stringType($aArgs, ['path']);

        if (!is_dir($aArgs['path'])) {
            return false;
        }
        if (!is_readable($aArgs['path'])) {
            return false;
        }
        if (!is_writable($aArgs['path'])) {
            return false;
        }

        return true;
    }
}
