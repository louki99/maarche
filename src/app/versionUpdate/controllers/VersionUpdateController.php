<?php

/**
 * Copyright Maarch since 2008 under licence GPLv3.
 * See LICENCE.txt file at the root folder for more details.
 * This file is part of Maarch software.
 *
 */

/**
 * @brief Version Update Controller
 * @author dev@maarch.org
 */

namespace VersionUpdate\controllers;

use Docserver\controllers\DocserverController;
use Gitlab\Client;
use Group\controllers\PrivilegeController;
use Parameter\models\ParameterModel;
use Slim\Psr7\Request;
use SrcCore\http\Response;
use SrcCore\models\CoreConfigModel;
use SrcCore\models\DatabaseModel;
use SrcCore\models\ValidatorModel;
use History\controllers\HistoryController;

class VersionUpdateController
{
    public function get(Request $request, Response $response)
    {
        if (!PrivilegeController::hasPrivilege(['privilegeId' => 'admin_update_control', 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }

        $client = new Client();
        $client->setUrl('https://labs.maarch.org/api/v4/');
        try {
            $tags = $client->tags()->all('12');
        } catch (\Exception $e) {
            return $response->withJson(['errors' => $e->getMessage()]);
        }

        $applicationVersion = CoreConfigModel::getApplicationVersion();

        if (empty($applicationVersion)) {
            return $response->withStatus(400)->withJson(['errors' => "Can't load package.json"]);
        }

        $currentVersion = $applicationVersion;
        $versions = explode('.', $currentVersion);

        if (count($versions) < 3) {
            return $response->withStatus(400)->withJson(['errors' => "Bad tag format : {$applicationVersion}"]);
        } else if (strlen($versions[0]) !== 4) {
            return $response->withStatus(400)->withJson(['errors' => "Bad tag format : {$applicationVersion}"]);
        }

        $currentVersionBranch = $versions[0];
        $currentMinorVersionTag = $versions[2];

        $availableMinorVersions = [];
        $availableMajorVersions = [];

        foreach ($tags as $value) {
            if (!preg_match("/^\d{4}\.\d\.\d+$/", $value['name'])) {
                continue;
            }
            $explodedValue = explode('.', $value['name']);

            $branchVersionTag = $explodedValue[0];
            $minorVersionTag = $explodedValue[2];

            if ($branchVersionTag > $currentVersionBranch) {
                $availableMajorVersions[] = $value['name'];
            } else if ($branchVersionTag == $currentVersionBranch && $minorVersionTag > $currentMinorVersionTag) {
                $availableMinorVersions[] = $value['name'];
            }
        }

        natcasesort($availableMinorVersions);
        natcasesort($availableMajorVersions);

        if (empty($availableMinorVersions)) {
            $lastAvailableMinorVersion = null;
        } else {
            $lastAvailableMinorVersion = end($availableMinorVersions);
        }

        if (empty($availableMajorVersions)) {
            $lastAvailableMajorVersion = null;
        } else {
            $lastAvailableMajorVersion = end($availableMajorVersions);
        }

        $output = [];

        exec('git status --porcelain --untracked-files=no 2>&1', $output);

        return $response->withJson([
            'lastAvailableMinorVersion' => $lastAvailableMinorVersion,
            'lastAvailableMajorVersion' => $lastAvailableMajorVersion,
            'currentVersion'            => $currentVersion,
            'canUpdate'                 => empty($output),
            'diffOutput'                => $output
        ]);
    }

    /**
        * @codeCoverageIgnore
    */
    public function update(Request $request, Response $response)
    {
        if (!PrivilegeController::hasPrivilege(['privilegeId' => 'admin_update_control', 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }

        $body = $request->getParsedBody();
        $targetTag = $body['tag'];
        $targetTagVersions = explode('.', $targetTag);

        if (count($targetTagVersions) < 3) {
            return $response->withStatus(400)->withJson(['errors' => "Bad tag format : {$body['tag']}"]);
        }

        $targetVersionBranch = $targetTagVersions[0];
        $targetMinorVersionTag = $targetTagVersions[2];
        $targetMajorVersionTag = $targetTagVersions[1];

        $applicationVersion = CoreConfigModel::getApplicationVersion();
        if (empty($applicationVersion)) {
            return $response->withStatus(400)->withJson(['errors' => "Can't load package.json"]);
        }

        $currentVersion = $applicationVersion;

        $versions = explode('.', $currentVersion);
        $currentVersionBranch = $versions[0];
        $currentMinorVersionTag = $versions[2];
        $currentMajorVersionTag = $versions[1];

        if ($currentVersionBranch !== $targetVersionBranch) {
            return $response->withStatus(400)->withJson(['errors' => "Target branch version did not match with current branch"]);
        }

        if ($targetMajorVersionTag < $currentMajorVersionTag) {
            return $response->withStatus(400)->withJson(['errors' => "Can't update to previous / same major tag"]);
        } else if ($targetMajorVersionTag == $currentMajorVersionTag && $targetMinorVersionTag <= $currentMinorVersionTag) {
            return $response->withStatus(400)->withJson(['errors' => "Can't update to previous / same minor tag"]);
        }

        $output = [];
        exec('git status --porcelain --untracked-files=no 2>&1', $output);
        if (!empty($output)) {
            return $response->withStatus(400)->withJson(['errors' => 'Some files are modified. Can not update application', 'lang' => 'canNotUpdateApplication']);
        }

        $migrationFolder = DocserverController::getMigrationFolderPath();

        if (!empty($migrationFolder['errors'])) {
            return $response->withStatus(400)->withJson(['errors' => $migrationFolder['errors']]);
        }

        $actualTime = date("dmY-His");

        $output = [];
        exec('git fetch');
        exec("git checkout {$targetTag} 2>&1", $output, $returnCode);

        $log = "Application update from {$currentVersion} to {$targetTag}\nCheckout response {$returnCode} => " . implode(' ', $output) . "\n";
        file_put_contents("{$migrationFolder['path']}/updateVersion_{$actualTime}.log", $log, FILE_APPEND);

        if ($returnCode != 0) {
            return $response->withStatus(400)->withJson(['errors' => "Application update failed. Please check updateVersion.log at {$migrationFolder['path']}"]);
        }

        HistoryController::add([
            'tableName' => 'none',
            'recordId'  => $targetTag,
            'eventType' => 'UP',
            'userId'    => $GLOBALS['id'],
            'info'      => _APP_UPDATED_TO_TAG. ' : ' . $targetTag,
            'moduleId'  => null,
            'eventId'   => 'appUpdate',
        ]);

        return $response->withStatus(204);
    }

    private static function executeSQLUpdate(array $args)
    {
        ValidatorModel::arrayType($args, ['sqlFiles']);

        $migrationFolder = DocserverController::getMigrationFolderPath();
        
        if (!empty($migrationFolder['errors'])) {
            return ['errors' => $migrationFolder['errors']];
        }

        if (!empty($args['sqlFiles'])) {
            $config = CoreConfigModel::getJsonLoaded(['path' => 'config/config.json']);

            $actualTime = date("dmY-His");
            $tablesToSave = '';
            foreach ($args['sqlFiles'] as $sqlFile) {
                $fileContent = file_get_contents($sqlFile);
                $explodedFile = explode("\n", $fileContent);
                foreach ($explodedFile as $key => $line) {
                    if (strpos($line, '--DATABASE_BACKUP') !== false) {
                        $lineNb = $key;
                    }
                }
                if (isset($lineNb)) {
                    $explodedLine = explode('|', $explodedFile[$lineNb]);
                    array_shift($explodedLine);
                    foreach ($explodedLine as $table) {
                        if (!empty($table)) {
                            $tablesToSave .= ' -t ' . trim($table);
                        }
                    }
                }
            }

            $execReturn = exec("pg_dump --dbname=\"postgresql://{$config['database'][0]['user']}:{$config['database'][0]['password']}@{$config['database'][0]['server']}:{$config['database'][0]['port']}/{$config['database'][0]['name']}\" {$tablesToSave} -a > \"{$migrationFolder['path']}/backupDB_maarchcourrier_{$actualTime}.sql\"", $output, $intReturn);
            if (!empty($execReturn)) {
                return ['errors' => 'Pg dump failed : ' . $execReturn];
            }

            foreach ($args['sqlFiles'] as $sqlFile) {
                $fileContent = file_get_contents($sqlFile);
                DatabaseModel::exec($fileContent);
                $fileName = explode('/', $sqlFile)[1];
                HistoryController::add([
                    'tableName' => 'none',
                    'recordId'  => $fileName,
                    'eventType' => 'UP',
                    'userId'    => $GLOBALS['id'],
                    'info'      => _DB_UPDATED_WITH_FILE. ' : ' . $fileName,
                    'moduleId'  => null,
                    'eventId'   => 'databaseUpdate',
                ]);
            }
        }

        return ['directoryPath' => "{$migrationFolder['path']}"];
    }

    public function updateSQLVersion(Request $request, Response $response)
    {
        $parameter = ParameterModel::getById(['select' => ['param_value_string'], 'id' => 'database_version']);

        $parameter = explode('.', $parameter['param_value_string']);

        if (count($parameter) < 2) {
            return $response->withStatus(400)->withJson(['errors' => "Bad format database_version"]); 
        }

        $dbMinorVersion = (int)$parameter[2];

        $dbMajorVersion = (int)$parameter[1];

        $sqlFiles = array_diff(scandir('migration'), array('..', '.', '.gitkeep'));
        natcasesort($sqlFiles);
        $targetedSqlFiles = [];

        foreach ($sqlFiles as $key => $file) {
            $fileVersions = explode('.', $file);
            $fileMinorVersion = (int)$fileVersions[2];
            $fileMajorVersion = (int)$fileVersions[1];
            if ($fileMajorVersion > $dbMajorVersion || ($fileMajorVersion == $dbMajorVersion && $fileMinorVersion > $dbMinorVersion)) {
                if (!is_readable("migration/{$file}")) {
                    return $response->withStatus(400)->withJson(['errors' => "File migration/{$file} is not readable"]);
                }
                $targetedSqlFiles[] = "migration/{$file}"; 
            }
        }

        if (!empty($targetedSqlFiles)) {
            $control = VersionUpdateController::executeSQLUpdate(['sqlFiles' => $targetedSqlFiles]);
            if (!empty($control['errors'])) {
                return $response->withStatus(400)->withJson(['errors' => $control['errors']]);
            }
            return $response->withJson(['success' => 'Database has been updated']);
        }

        return $response->withStatus(204);
    }
}
