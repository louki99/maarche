<?php

/**
* Copyright Maarch since 2008 under licence GPLv3.
* See LICENCE.txt file at the root folder for more details.
* This file is part of Maarch software.
*
*/

/**
* @brief Logs Controller
* @author dev@maarch.org
* @ingroup core
*/

namespace SrcCore\controllers;

use SrcCore\models\ValidatorModel;
use SrcCore\models\CoreConfigModel;
use SrcCore\processors\LogProcessor;

// using Monolog version 2.6.0
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\FilterHandler;
use Monolog\Formatter\LineFormatter;
use Monolog\Processor\ProcessIdProcessor;
use Monolog\Processor\MemoryUsageProcessor;

class LogsController
{
    public const DEFAULT_LOG_FORMAT     = "[%datetime%] %level_name% [%extra.process_id%] [%channel%][%WHERE%][%ID%][%HOW%][%USER%][%WHAT%][%ID_MODULE%][%REMOTE_IP%]\n";
    public const DEFAULT_SQL_LOG_FORMAT = "[%datetime%] %level_name% [%extra.process_id%] [%channel%][%QUERY%][%DATA%][%EXCEPTION%]\n";

    /**
     * @param array $logConfig
     * @param array $loggerConfig
     * @return Logger|array $logger
     */
    public static function initMonologLogger(array $logConfig, array $loggerConfig)
    {
        if (empty($logConfig)) {
            return ['code' => 400, 'errors' => "Log config is empty !"];
        }

        $dateFormat = $logConfig['dateTimeFormat'] ?? null;
        if (empty($dateFormat)) {
            return ['code' => 400, 'errors' => "Log configuration 'dateTimeFormat' is empty"];
        }
        $output = $loggerConfig['lineFormat'] ?? null;
        if (empty($output)) {
            return ['code' => 400, 'errors' => "Log configuration 'lineFormat' is empty"];
        }
        if (empty($loggerConfig['file'])) {
            return ['code' => 400, 'errors' => "Logger configuration 'file' path is empty"];
        }
        if (empty($loggerConfig['level'])) {
            return ['code' => 400, 'errors' => "Logger configuration 'level' is empty"];
        }

        $formatter = new LineFormatter($output, $dateFormat);

        $streamHandler = new StreamHandler($loggerConfig['file']);
        $streamHandler->setFormatter($formatter);

        $customId = CoreConfigModel::getCustomId() ?: 'SCRIPT';
        $logger = new Logger($customId);
        $filterHandler = new FilterHandler($streamHandler, $logger->toMonologLevel($loggerConfig['level']));
        $logger->pushHandler($filterHandler);

        $logger->pushProcessor(new ProcessIdProcessor());
        $logger->pushProcessor(new MemoryUsageProcessor());

        return $logger;
    }

    /**
     * @description Get log config by type
     * @param   string  $logType    logFonctionnel | logTechnique | queries
     * @return  array
     */
    public static function getLogType(string $logType): array
    {
        $logConfig = LogsController::getLogConfig();

        if (empty($logConfig[$logType])) {
            return ['code' => 400, 'errors' => "Log config of type '$logType' does not exist"];
        }

        return $logConfig[$logType];
    }

    /**
     * @description Get log config
     * @return  array
     */
    public static function getLogConfig(): ?array
    {
        $logConfig = CoreConfigModel::getJsonLoaded(['path' => 'config/config.json']);

        if (empty($logConfig['log'])) {
            return null;
        }

        return $logConfig['log'];
    }

    /**
     * @description Add log line
     * @param array $args
     * @return  array|bool
     * @throws \Exception
     */
    public static function add(array $args)
    {
        $logConfig = LogsController::getLogConfig();
        if (empty($logConfig)) {
            return ['code' => 400, 'errors' => "Log config not found!"];
        }
        if (empty($logConfig['enable'])) {
            return ['code' => 400, 'errors' => "LogController disabled. Check log configuration -> enable."];
        }

        $loggerType = 'logTechnique';
        $defaultLogFormat = self::DEFAULT_LOG_FORMAT;
        if (empty($args['isTech']) && empty($args['isSql'])) {
            $loggerType = 'logFonctionnel';
        } elseif (!empty($args['isSql'])) {
            $defaultLogFormat = self::DEFAULT_SQL_LOG_FORMAT;
            $loggerType = 'queries';
        }

        $loggerConfig = LogsController::getLogType($loggerType);

        if (Logger::toMonologLevel($args['level']) < Logger::toMonologLevel($loggerConfig['level'])) {
            return false;
        }

        $maxSize = LogsController::calculateFileSizeToBytes($loggerConfig['maxFileSize']);
        LogsController::rotateLogFileBySize([
            'path'      => $loggerConfig['file'],
            'maxSize'   => $maxSize,
            'maxFiles'  => $loggerConfig['maxBackupFiles']
        ]);

        LogsController::logWithMonolog([
            'lineFormat'     => $loggerConfig['lineFormat'] ?? $defaultLogFormat,
            'dateTimeFormat' => $logConfig['dateTimeFormat'],
            'levelConfig'    => $loggerConfig['level'],
            'path'           => $loggerConfig['file'],
            'level'          => $args['level'],
            'logData'        => $args,
            'isSql'          => !empty($args['isSql'])
        ]);
        return true;
    }

    /**
     * @description     Write prepare log line with monolog
     * @param array $log
     * @return  void
     * @throws \Exception
     */
    private static function logWithMonolog(array $log)
    {
        ValidatorModel::notEmpty($log, ['lineFormat', 'dateTimeFormat', 'path', 'level']);
        ValidatorModel::stringType($log, ['lineFormat', 'dateTimeFormat', 'path']);

        $logger = LogsController::initMonologLogger(
            ['dateTimeFormat' => $log['dateTimeFormat']],
            ['lineFormat' => $log['lineFormat'], 'file' => $log['path'], 'level' => $log['levelConfig']]
        );

        $log['line'] = null;

        $logger->pushProcessor(new LogProcessor($log['logData'], !empty($log['isSql'])));

        switch ($log['level']) {
            case 'DEBUG':
                // Use for detailed debug information
                $logger->debug($log['line']);
                break;
            case 'INFO':
                // Use for user logs in, SQL logs
                $logger->info($log['line']);
                break;
            case 'NOTICE':
                // Use for uncommon events
                $logger->notice($log['line']);
                break;
            case 'WARNING':
                // Use for exceptional occurrences that are not errors. Examples: Use of deprecated APIs, poor use of an API, undesirable things that are not necessarily wrong.
                $logger->warning($log['line']);
                break;
            case 'ERROR':
                // Use for runtime errors
                $logger->error($log['line']);
                break;
            case 'CRITICAL':
                // Use for critical conditions. Example: Application component unavailable, unexpected exception.
                $logger->critical($log['line']);
                break;
            case 'ALERT':
                // Use for actions that must be taken immediately. Example: Entire website down, database unavailable, etc.
                $logger->alert($log['line']);
                break;
            case 'EMERGENCY':
                // Use for urgent alert.
                $logger->emergency($log['line']);
                break;
        }

        $logger->close();
    }

    /**
     * @description Create new log file based on size and number of files to keep, when file size is exceeded
     * @param array $file
     * @return  void
     * @throws \Exception
     */
    public static function rotateLogFileBySize(array $file)
    {
        ValidatorModel::notEmpty($file, ['path']);
        ValidatorModel::intVal($file, ['maxSize', 'maxFiles']);
        ValidatorModel::stringType($file, ['path']);

        if (file_exists($file['path']) && !empty($file['maxSize']) && $file['maxSize'] > 0 && filesize($file['path']) > $file['maxSize']) {
            $path_parts = pathinfo($file['path']);
            $pattern = $path_parts['dirname'] . '/' . $path_parts['filename'] . "-%d." . $path_parts['extension'];

            // delete last file
            $fn = sprintf($pattern, $file['maxFiles']);
            if (file_exists($fn)) { unlink($fn);}

            // shift file names (add '-%index' before the extension)
            if (!empty($file['maxFiles'])) {
                for ($i = $file['maxFiles'] - 1; $i > 0; $i--) {
                    $fn = sprintf($pattern, $i);
                    if(file_exists($fn)) { 
                        rename($fn, sprintf($pattern, $i + 1)); 
                    }
                }
            }
            rename($file['path'], sprintf($pattern, 1));
        }
    }

    /**
     * @description Convert File size to KB
     * @param string $value     The size + prefix (of 2 characters)
     * @return  int
     */
    public static function calculateFileSizeToBytes(string $value): ?int
    {
		$maxFileSize = null;
		$numpart = substr($value,0, strlen($value) -2);
		$suffix = strtoupper(substr($value, -2));

		switch($suffix) {
			case 'KB': $maxFileSize = (int)((int)$numpart * 1024); break;
			case 'MB': $maxFileSize = (int)((int)$numpart * 1024 * 1024); break;
			case 'GB': $maxFileSize = (int)((int)$numpart * 1024 * 1024 * 1024); break;
			default:
				if (is_numeric($value)) {
					$maxFileSize = (int)$value;
				}
		}
		return $maxFileSize;
	}
}
