<?php

/**
* Copyright Maarch since 2008 under licence GPLv3.
* See LICENCE.txt file at the root folder for more details.
* This file is part of Maarch software.
*
*/

/**
* @brief Batch History Controller
* @author dev@maarch.org
*/

namespace History\controllers;

use Group\controllers\PrivilegeController;
use History\models\BatchHistoryModel;
use Slim\Psr7\Request;
use SrcCore\http\Response;
use SrcCore\controllers\AutoCompleteController;
use Respect\Validation\Validator;

class BatchHistoryController
{
    public function get(Request $request, Response $response)
    {
        if (!PrivilegeController::hasPrivilege(['privilegeId' => 'view_history_batch', 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }

        $queryParams = $request->getQueryParams();

        $limit = 25;
        if (!empty($queryParams['limit']) && is_numeric($queryParams['limit'])) {
            $limit = (int)$queryParams['limit'];
        }
        $offset = 0;
        if (!empty($queryParams['offset']) && is_numeric($queryParams['offset'])) {
            $offset = (int)$queryParams['offset'];
        }

        $where = [];
        $data = [];

        if (!empty($queryParams['startDate'])) {
            $where[] = 'event_date > ?';
            $data[] = $queryParams['startDate'];
        }
        if (!empty($queryParams['endDate'])) {
            $where[] = 'event_date < ?';
            $data[] = $queryParams['endDate'];
        }
        if (!empty($queryParams['modules'])) {
            $where[] = 'module_name in (?)';
            $data[] = $queryParams['modules'];
        }
        if (!empty($queryParams['totalErrors'])) {
            $where[] = 'total_errors > 0';
        }

        if (!empty($queryParams['search'])) {
            $searchFields = ['info', 'module_name'];
            $fields = AutoCompleteController::getInsensitiveFieldsForRequest(['fields' => $searchFields]);

            $requestData = AutoCompleteController::getDataForRequest([
                'search'        => $queryParams['search'],
                'fields'        => $fields,
                'where'         => $where,
                'data'          => $data,
                'fieldsNumber'  => 2,
                'longField'     => true
            ]);

            $where = $requestData['where'];
            $data = $requestData['data'];
        }
        $order = '';
        if (!empty($queryParams['order']) && in_array($queryParams['order'], ['asc', 'desc'])) {
            $order = $queryParams['order'];
        }
        if (!empty($queryParams['orderBy'])) {
            $orderBy = !in_array($queryParams['orderBy'], ['event_date', 'module_name', 'total_processed', 'total_errors', 'info']) ? ['event_date DESC'] : ["{$queryParams['orderBy']} {$order}"];
        }

        $history = BatchHistoryModel::get([
            'select'    => ['event_date', 'module_name', 'total_processed', 'total_errors', 'info', 'count(1) OVER()'],
            'where'     => $where,
            'data'      => $data,
            'orderBy'   => $orderBy ?? null,
            'offset'    => $offset,
            'limit'     => $limit
        ]);

        $total = $history[0]['count'] ?? 0;
        foreach ($history as $key => $value) {
            unset($history[$key]['count']);
        }

        return $response->withJson(['history' => $history, 'count' => $total]);
    }

    public function getAvailableFilters(Request $request, Response $response)
    {
        if (!PrivilegeController::hasPrivilege(['privilegeId' => 'view_history_batch', 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }

        $modules = BatchHistoryModel::get([
            'select' => ['DISTINCT(module_name) as id', 'module_name as label']
        ]);

        return $response->withJson(['modules' => $modules]);
    }

    public function exportBatchHistory(Request $request, Response $response)
    {
        if (!PrivilegeController::hasPrivilege(['privilegeId' => 'admin_contacts', 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }

        $body = $request->getParsedBody();
        $queryParams = $request->getQueryParams();

        if (!Validator::stringType()->notEmpty()->validate($body['delimiter']) || !in_array($body['delimiter'], [',', ';', 'TAB'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Delimiter is empty or not a string between [\',\', \';\', \'TAB\']']);
        } elseif (!Validator::stringType()->notEmpty()->validate($body['format'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Format data is empty or not an array']);
        } elseif (!Validator::arrayType()->notEmpty()->validate($body['data'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Data data is empty or not an array']);
        } elseif (!Validator::arrayType()->notEmpty()->validate($body['parameters'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Parameters data is empty or not an array']);
        }

        $limit = 1000;
        if (!empty($queryParams['limit']) && is_numeric($queryParams['limit']) && (int)$queryParams['limit'] != $limit) {
            $limit = (int)$queryParams['limit'];
        }

        $where = [];
        $data = [];
        if (!empty($body['parameters']['startDate'])) {
            $where[] = 'event_date > ?';
            $data[]  = $body['parameters']['startDate'];
        }
        if (!empty($body['parameters']['endDate'])) {
            $where[] = 'event_date < ?';
            $data[]  = $body['parameters']['endDate'];
        }

        $moduleTypes = [];
        if (!empty($body['parameters']['filterUsed']['modules']) && is_array($body['parameters']['filterUsed']['modules'])) {
            foreach ($body['parameters']['filterUsed']['modules'] as $module) {
                $moduleTypes[] = $module['id'];
            }
        }
        if (!empty($moduleTypes)) {
            $where[] = 'module_name in (?)';
            $data[] = $moduleTypes;
        }

        $totalProcessed = [];
        if (!empty($body['parameters']['filterUsed']['totalProcessed']) && is_array($body['parameters']['filterUsed']['totalProcessed'])) {
            foreach ($body['parameters']['filterUsed']['totalProcessed'] as $processedElm) {
                $totalProcessed[] = $processedElm['id'];
            }
        }
        if (!empty($totalProcessed)) {
            $where[] = 'total_processed in (?)';
            $data[] = $totalProcessed;
        }

        $totalErrors = [];
        if (!empty($body['parameters']['filterUsed']['totalErrors']) && is_array($body['parameters']['filterUsed']['totalErrors'])) {
            foreach ($body['parameters']['filterUsed']['totalErrors'] as $errorElm) {
                $totalErrors[] = $errorElm['id'];
            }
        }
        if (!empty($totalErrors)) {
            $where[] = 'total_errors in (?)';
            $data[] = $totalErrors;
        }

        $orderBy = ['event_date DESC'];


        $fields = [];
        $csvHead = [];
        foreach ($body['data'] as $field) {
            $fields[] = $field['value'];
            $csvHead[] = $field['label'];
        }

        ini_set('memory_limit', -1);

        $file = fopen('php://temp', 'w');
        $delimiter = ($body['delimiter'] == 'TAB' ? "\t" : $body['delimiter']);

        fputcsv($file, $csvHead, $delimiter);

        $histories = BatchHistoryModel::get([
            'select'    => $fields,
            'where'     => $where,
            'data'      => $data,
            'orderBy'   => $orderBy,
            'limit'     => $limit
        ]);

        foreach ($histories as $history) {
            fputcsv($file, $history, $delimiter);
        }

        rewind($file);

        $response->write(stream_get_contents($file));
        $response = $response->withAddedHeader('Content-Disposition', 'attachment; filename=export_maarch.csv');
        $contentType = 'application/vnd.ms-excel';
        fclose($file);

        return $response->withHeader('Content-Type', $contentType);
    }
}
