<?php

/**
 * Copyright Maarch since 2008 under licence GPLv3.
 * See LICENCE.txt file at the root folder for more details.
 * This file is part of Maarch software.
 *
 */

/**
 * @brief DatabasePDO
 * @author dev@maarch.org
 */

namespace SrcCore\models;

use \SrcCore\controllers\LogsController;

class DatabasePDO
{
    private static $pdo             = null;
    private static $type            = null;
    private static $preparedQueries = [];

    public function __construct(array $args = [])
    {
        if (!empty(self::$pdo)) {
            return;
        }

        if (!empty($args['customId'])) {
            $customId = $args['customId'];
        } else {
            $customId = CoreConfigModel::getCustomId();
        }

        if (!empty($customId) && file_exists("custom/{$customId}/config/config.json")) {
            $path = "custom/{$customId}/config/config.json";
        } else {
            $path = 'config/config.json';
        }

        if (!file_exists($path)) {
            throw new \Exception('No configuration file found');
        }
        $jsonFile = file_get_contents($path);
        $jsonFile = json_decode($jsonFile, true);
        if (empty($jsonFile['database'])) {
            throw new \Exception('No database part found in configuration file');
        }

        foreach ($jsonFile['database'] as $key => $database) {
            $server     = $database['server'];
            $port       = $database['port'];
            $name       = $database['name'];
            $user       = $database['user'];
            $password   = $database['password'];
            self::$type = $database['type'];

            ValidatorModel::notEmpty(['server' => $server, 'port' => $port, 'name' => $name, 'user' => $user], ['server', 'port', 'name', 'user']);
            ValidatorModel::stringType(['server' => $server, 'name' => $name, 'user' => $user], ['server', 'name', 'user']);
            ValidatorModel::intVal(['port' => $port], ['port']);

            $formattedDriver = 'pgsql';
            if (self::$type == 'POSTGRESQL') {
                $formattedDriver = 'pgsql';
            } elseif (self::$type == 'MYSQL') {
                $formattedDriver = 'mysql';
            } elseif (self::$type == 'ORACLE') {
                $formattedDriver = 'oci';
            }

            $options = [
                \PDO::ATTR_PERSISTENT   => true,
                \PDO::ATTR_ERRMODE      => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_CASE         => \PDO::CASE_NATURAL
            ];

            $dsn = "{$formattedDriver}:host={$server};port={$port};dbname={$name}";
            try {
                self::$pdo = new \PDO($dsn, $user, $password, $options);
                break;
            } catch (\PDOException $PDOException) {
                try {
                    $options[\PDO::ATTR_PERSISTENT] = false;
                    self::$pdo = new \PDO($dsn, $user, $password, $options);
                    break;
                } catch (\PDOException $PDOException) {
                    if (!empty($jsonFile['database'][$key + 1])) {
                        continue;
                    } else {
                        LogsController::add([
                            'isSql'         => true,
                            'level'         => 'ERROR',
                            'sqlException'  => $PDOException->getMessage()
                        ]);
                        throw new \Exception($PDOException->getMessage());
                    }
                }
            }
        }
    }

    public function query($queryString, array $data = [])
    {
        LogsController::add([
            'isSql'         => true,
            'level'         => 'INFO',
            'sqlQuery'      => $queryString,
            'sqlData'       => $data,
        ]);

        if (self::$type == 'ORACLE') {
            $queryString = str_ireplace('CURRENT_TIMESTAMP', 'SYSDATE', $queryString);
        }

        if (!empty($data)) {
            $tmpData = [];
            $position = 0;
            foreach ($data as $value) {
                if (is_array($value)) {
                    $placeholders = implode(',', array_fill(0, count($value), '?'));
                    $position = strpos($queryString, '(?)', $position + 1);
                    $queryString = substr_replace($queryString, $placeholders, $position + 1, 1);

                    $tmpData = array_merge($tmpData, $value);
                } else {
                    $tmpData[] = $value;
                }
            }
            $data = $tmpData;
        }

        try {
            if (empty(self::$preparedQueries[$queryString])) {
                $query = self::$pdo->prepare($queryString);
                self::$preparedQueries[$queryString] = $query;
            } else {
                $query = self::$preparedQueries[$queryString];
            }
            $query->execute($data);
        } catch (\PDOException $PDOException) {
            if (strpos($PDOException->getMessage(), 'Admin shutdown: 7') !== false || strpos($PDOException->getMessage(), 'General error: 7') !== false) {
                DatabasePDO::reset();
                $db = new DatabasePDO();
                $query = $db->query($queryString, $data);
            } else {
                LogsController::add([
                    'isSql'         => true,
                    'level'         => 'ERROR',
                    'sqlQuery'      => $queryString . PHP_EOL,
                    'sqlData'       => $data,
                    'sqlException'  => $PDOException->getMessage()
                ]);

                throw new \Exception($PDOException->getMessage());
            }
        }

        return $query;
    }

    public function exec(string $query)
    {
        LogsController::add([
            'isSql'         => true,
            'level'         => 'INFO',
            'sqlQuery'      => $query
        ]);

        try {
            self::$pdo->exec($query);
        } catch (\PDOException $PDOException) {
            LogsController::add([
                'isSql'         => true,
                'level'         => 'ERROR',
                'sqlQuery'      => $query . PHP_EOL,
                'sqlException'  => $PDOException->getMessage()
            ]);

            throw new \Exception($PDOException->getMessage());
        }

        return true;
    }

    public function setLimit(array $args)
    {
        ValidatorModel::notEmpty($args, ['limit']);
        ValidatorModel::intVal($args, ['limit']);
        ValidatorModel::stringType($args, ['where']);

        if (self::$type == 'ORACLE') {
            if (empty($args['where'])) {
                $where = ' WHERE ROWNUM <= ' . $args['limit'];
            } else {
                $where = "{$args['where']} AND ROWNUM <= {$args['limit']}";
            }
            $limit = '';
        } else {
            $where = $args['where'];
            $limit = " LIMIT {$args['limit']}";
        }

        return ['where' => $where, 'limit' => $limit];
    }

    public static function reset()
    {
        self::$pdo = null;
        self::$preparedQueries = [];
    }

    public function beginTransaction()
    {
        return self::$pdo->beginTransaction();
    }

    public function commitTransaction()
    {
        return self::$pdo->commit();
    }

    public function rollbackTransaction()
    {
        return self::$pdo->rollBack();
    }
}
