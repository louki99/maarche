<?php

/**
* Copyright Maarch since 2008 under licence GPLv3.
* See LICENCE.txt file at the root folder for more details.
* This file is part of Maarch software.

* @brief   ParametersController
* @author  dev <dev@maarch.org>
* @ingroup core
*/

/**
 * @brief Custom Field Controller
 * @author dev@maarch.org
 */

namespace CustomField\controllers;

use Action\models\ActionModel;
use CustomField\models\CustomFieldModel;
use Group\controllers\PrivilegeController;
use History\controllers\HistoryController;
use IndexingModel\models\IndexingModelFieldModel;
use Resource\models\ResModel;
use Respect\Validation\Validator;
use Slim\Psr7\Request;
use SrcCore\http\Response;
use SrcCore\models\CoreConfigModel;
use SrcCore\models\DatabaseModel;

class CustomFieldController
{
    const NUMERIC_TYPES = ['smallint', 'integer', 'bigint', 'decimal', 'numeric', 'real', 'double precision', 'serial', 'bigserial'];

    public function get(Request $request, Response $response)
    {
        $queryParams = $request->getQueryParams();

        $customFields = CustomFieldModel::get(['orderBy' => ['label']]);

        foreach ($customFields as $key => $customField) {
            $customFields[$key]['values'] = json_decode($customField['values'], true);
            $customFields[$key]['SQLMode'] = !empty($customFields[$key]['values']['table']);
            if (empty($queryParams['admin']) || !PrivilegeController::hasPrivilege(['privilegeId' => 'admin_custom_fields', 'userId' => $GLOBALS['id']])) {
                if (!empty($customFields[$key]['values']['table'])) {
                    if (!empty($queryParams['resId']) && is_numeric($queryParams['resId'])) {
                        $customFields[$key]['values']['resId'] = $queryParams['resId'];
                    }
                    $customFields[$key]['values'] = CustomFieldModel::getValuesSQL($customFields[$key]['values']);

                    if (empty($customFields[$key]['values'])) {
                        continue;
                    }

                    if (in_array($customField['type'], ['select', 'radio', 'checkbox'])) {
                        foreach ($customFields[$key]['values'] as $iKey => $sValue) {
                            $customFields[$key]['values'][$iKey]['key'] = (string)$sValue['key'];
                        }
                    } elseif ($customField['type'] == 'string') {
                        $customFields[$key]['values'][0]['key'] = (string)$customFields[$key]['values'][0]['key'];
                    } elseif ($customField['type'] == 'integer') {
                        $customFields[$key]['values'][0]['key'] = (int)$customFields[$key]['values'][0]['key'];
                    }
                } elseif (!empty($customFields[$key]['values'])) {
                    $values = $customFields[$key]['values'];
                    $customFields[$key]['values'] = [];
                    foreach ($values as $value) {
                        $customFields[$key]['values'][] = ['key' => $value, 'label' => $value];
                    }
                }
            } else {
                if (empty($customFields[$key]['values']['table']) && !empty($customFields[$key]['values'])) {
                    $values = $customFields[$key]['values'];
                    $customFields[$key]['values'] = [];
                    foreach ($values as $valueKey => $value) {
                        $customFields[$key]['values'][] = ['key' => $valueKey, 'label' => $value];
                    }
                }
            }
        }

        return $response->withJson(['customFields' => $customFields]);
    }

    public function create(Request $request, Response $response)
    {
        if (!PrivilegeController::hasPrivilege(['privilegeId' => 'admin_custom_fields', 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }

        $body = $request->getParsedBody();

        if (!Validator::stringType()->notEmpty()->validate($body['label'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body label is empty or not a string']);
        } elseif (!Validator::stringType()->notEmpty()->validate($body['type']) || !in_array($body['type'], ['string', 'integer', 'select', 'date', 'radio', 'checkbox', 'banAutocomplete', 'contact'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body type is empty, not a string or value is incorrect']);
        } elseif (!empty($body['values']) && !Validator::arrayType()->notEmpty()->validate($body['values'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body values is not an array']);
        } elseif (!Validator::stringType()->notEmpty()->validate($body['mode']) || !in_array($body['mode'], ['form', 'technical'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body mode is empty, not a string or value is incorrect']);
        }

        $fields = CustomFieldModel::get(['select' => [1], 'where' => ['label = ?'], 'data' => [$body['label']]]);
        if (!empty($fields)) {
            return $response->withStatus(400)->withJson(['errors' => 'Custom field with this label already exists']);
        }

        if (!empty($body['SQLMode'])) {
            $control = CustomFieldController::controlSQLMode(['body' => $body]);
            if (!empty($control['errors'])) {
                return $response->withStatus(400)->withJson($control);
            }
        } else {
            unset($body['values']['key'], $body['values']['label'], $body['values']['table'], $body['values']['clause']);
        }

        $id = CustomFieldModel::create([
            'label'         => $body['label'],
            'type'          => $body['type'],
            'mode'          => $body['mode'],
            'values'        => empty($body['values']) ? '[]' : json_encode($body['values'])
        ]);

        HistoryController::add([
            'tableName' => 'custom_fields',
            'recordId'  => $id,
            'eventType' => 'ADD',
            'info'      => _CUSTOMFIELDS_CREATION . " : {$body['label']}",
            'moduleId'  => 'customField',
            'eventId'   => 'customFieldCreation',
        ]);

        return $response->withJson(['customFieldId' => $id]);
    }

    public function update(Request $request, Response $response, array $args)
    {
        if (!PrivilegeController::hasPrivilege(['privilegeId' => 'admin_custom_fields', 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }

        if (!Validator::notEmpty()->intVal()->validate($args['id'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Param id is empty or not an integer']);
        }

        $body = $request->getParsedBody();

        if (!Validator::stringType()->notEmpty()->validate($body['label'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body label is empty or not a string']);
        } elseif (!empty($body['values']) && !Validator::arrayType()->notEmpty()->validate($body['values'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body values is not an array']);
        } elseif (!Validator::stringType()->notEmpty()->validate($body['mode']) || !in_array($body['mode'], ['form', 'technical'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body mode is empty, not a string or value is incorrect']);
        }

        $field = CustomFieldModel::getById(['select' => ['type', 'values', 'mode', 'id'], 'id' => $args['id']]);
        if (empty($field)) {
            return $response->withStatus(400)->withJson(['errors' => 'Custom field not found']);
        }

        $fields = CustomFieldModel::get(['select' => [1], 'where' => ['label = ?', 'id != ?'], 'data' => [$body['label'], $args['id']]]);
        if (!empty($fields)) {
            return $response->withStatus(400)->withJson(['errors' => 'Custom field with this label already exists']);
        }

        if (!empty($body['SQLMode'])) {
            $control = CustomFieldController::controlSQLMode(['body' => $body]);
            if (!empty($control['errors'])) {
                return $response->withStatus(400)->withJson($control);
            }
            if (in_array($body['type'], ['string', 'date', 'int'])) {
                $limitPos = stripos($body['values']['clause'], 'limit');
                if (!empty($limitPos)) {
                    $body['values']['clause'] = substr_replace($body['values']['clause'], 'LIMIT 1', $limitPos);
                } else {
                    $body['values']['clause'] .= ' LIMIT 1';
                }
            }
        } else {
            unset($body['values']['table'], $body['values']['clause']);
            $bodyValuesNoNulls = array_filter($body['values'], function ($value) {
                return $value['label'] != null;
            });
            $uniqueValues = array_column($bodyValuesNoNulls, 'label');

            if (count(array_unique($uniqueValues)) < count($bodyValuesNoNulls)) {
                return $response->withStatus(400)->withJson(['errors' => 'Some values have the same name']);
            }
        }

        $values = json_decode($field['values'], true);

        if (count($body['values']) < count($values) && $body['SQLMode'] == !empty($values['table'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Not enough values sent']);
        }

        $newValues = [];
        if (empty($body['SQLMode'])) {
            if (in_array($field['type'], ['select', 'radio', 'checkbox'])) {
                foreach ($body['values'] as $value) {
                    if ($value['label'] != null) {
                        $newValues[] = $value['label'];
                    }

                    if (empty($values['table']) && !empty($values[$value['key']]) && !empty($value['label']) && $value['label'] != $values[$value['key']]) {
                        ResModel::update([
                            'postSet' => ['custom_fields' => "jsonb_set(custom_fields, '{{$args['id']}}', '\"" . str_replace(["\\", "'", '"'], ["\\\\", "''", '\"'], $value['label']) . "\"')"],
                            'where'   => ["custom_fields->'{$args['id']}' @> ?"],
                            'data'    => ["\"" . str_replace(["\\", '"'], ["\\\\", '\"'], $values[$value['key']]) . "\""]
                        ]);
                    }
                }
            }
        } else {
            $newValues = $body['values'];
        }

        if ($field['mode'] == 'form' && $body['mode'] == 'technical') {
            IndexingModelFieldModel::delete(['where' => ['identifier = ?'], 'data' => ['indexingCustomField_' . $args['id']]]);
        }

        CustomFieldModel::update([
            'set'   => [
                'label'  => $body['label'],
                'mode'   => $body['mode'],
                'values' => empty($newValues) ? '[]' : json_encode($newValues)
            ],
            'where' => ['id = ?'],
            'data'  => [$args['id']]
        ]);

        if (empty($body['SQLMode'])) {
            $valuesTmp = $newValues;
            $newValues = [];
            foreach ($valuesTmp as $valueKey => $value) {
                $newValues[] = ['key' => $valueKey, 'label' => $value];
            }
        }

        $customField = [
            'id'      => $field['id'],
            'label'   => $body['label'],
            'type'    => $field['type'],
            'values'  => $newValues,
            'mode'    => $body['mode'],
            'SQLMode' => !empty($body['SQLMode'])
        ];

        HistoryController::add([
            'tableName' => 'custom_fields',
            'recordId'  => $args['id'],
            'eventType' => 'UP',
            'info'      => _CUSTOMFIELDS_MODIFICATION . " : {$body['label']}",
            'moduleId'  => 'customField',
            'eventId'   => 'customFieldModification',
        ]);

        return $response->withJson(['customField' => $customField]);
    }

    public function delete(Request $request, Response $response, array $args)
    {
        if (!PrivilegeController::hasPrivilege(['privilegeId' => 'admin_custom_fields', 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }

        if (!Validator::notEmpty()->intVal()->validate($args['id'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Param id is empty or not an integer']);
        }

        $field = CustomFieldModel::getById(['select' => ['label'], 'id' => $args['id']]);

        IndexingModelFieldModel::delete(['where' => ['identifier = ?'], 'data' => ['indexingCustomField_' . $args['id']]]);
        ResModel::update(['postSet' => ['custom_fields' => "custom_fields - '{$args['id']}'"], 'where' => ['1 = ?'], 'data' => [1]]);

        ActionModel::update([
            'postSet' => ['parameters' => "jsonb_set(parameters, '{requiredFields}', (parameters->'requiredFields') - 'indexingCustomField_{$args['id']}')"],
            'where'   => ["parameters->'requiredFields' @> ?"],
            'data'    => ['"indexingCustomField_'.$args['id'].'"']
        ]);

        $itemsPositionToRemove = DatabaseModel::select([
            'select'    => ['a.id as action_id', 'position'],
            'table'     => ["actions a, jsonb_array_elements( a.parameters->'fillRequiredFields') with ordinality arr(elem, position)"],
            'where'     => ["a.parameters->'fillRequiredFields' IS NOT NULL AND elem->>'id' = ?"],
            'data'      => ['indexingCustomField_'.$args['id']]
        ]);
        if (!empty($itemsPositionToRemove)) {
            foreach ($itemsPositionToRemove as $key => $item) {
                $item['position']--;
                ActionModel::update([
                    'postSet' => ['parameters' => "jsonb_set(parameters, '{fillRequiredFields}', (parameters->'fillRequiredFields') - {$item['position']}) "],
                    'where'   => ["parameters->'fillRequiredFields' IS NOT NULL AND id = ?"],
                    'data'    => [$item['action_id']]
                ]);
            }
        }

        CustomFieldModel::delete([
            'where' => ['id = ?'],
            'data'  => [$args['id']]
        ]);

        HistoryController::add([
            'tableName' => 'custom_fields',
            'recordId'  => $args['id'],
            'eventType' => 'DEL',
            'info'      => _CUSTOMFIELDS_SUPPRESSION . " : {$field['label']}",
            'moduleId'  => 'customField',
            'eventId'   => 'customFieldSuppression',
        ]);

        return $response->withStatus(204);
    }

    public function getWhiteList(Request $request, Response $response)
    {
        if (!PrivilegeController::hasPrivilege(['privilegeId' => 'admin_custom_fields', 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }

        $whiteList = CoreConfigModel::getJsonLoaded(['path' => 'config/customFieldsWhiteList.json']);
        $allowedTables = [];
        foreach ($whiteList as $table) {
            $columns = CoreConfigModel::getColumns(['table' => $table]);
            $columns = array_column($columns, 'column_name');
            foreach ($columns as $key => $column) {
                if (stripos($column, 'password') !== false || stripos($column, 'token') !== false) {
                    unset($columns[$key]);
                }
            }
            $allowedTables[] = [
                'name'      => $table,
                'columns'   => array_values($columns)
            ];
        }

        return $response->withJson(['allowedTables' => $allowedTables]);
    }

    public static function controlSQLMode(array $args)
    {
        $body = $args['body'];

        if (in_array($body['type'], ['banAutocomplete', 'contact'])) {
            return ['errors' => 'SQL is not allowed for type BAN'];
        }
        if (!Validator::stringType()->notEmpty()->validate($body['values']['key'])) {
            return ['errors' => 'Body values[key] is empty or not a string'];
        } elseif (!Validator::arrayType()->notEmpty()->validate($body['values']['label'])) {
            return ['errors' => 'Body values[label] is empty or not an array'];
        } elseif (!Validator::stringType()->notEmpty()->validate($body['values']['table'])) {
            return ['errors' => 'Body values[table] is empty or not a string'];
        } elseif (!Validator::stringType()->notEmpty()->validate($body['values']['clause'])) {
            return ['errors' => 'Body values[clause] is empty or not a string'];
        }
        if (stripos($body['values']['key'], 'password') !== false || stripos($body['values']['key'], 'token') !== false) {
            return ['errors' => 'Body values[key] is not allowed'];
        }
        $allowedTables = CoreConfigModel::getJsonLoaded(['path' => 'config/customFieldsWhiteList.json']);
        if (!in_array($body['values']['table'], $allowedTables)) {
            return ['errors' => 'Body values[table] is not allowed'];
        }

        if ($body['type'] == 'date' && count($body['values']['label']) !== 1) {
            return ['errors' => 'Body values[label] count is wrong for type date'];
        }
        $columns = CoreConfigModel::getColumns(['table' => $body['values']['table']]);
        $columns = array_column($columns, 'data_type', 'column_name');

        foreach ($body['values']['label'] as $value) {
            if (!Validator::stringType()->notEmpty()->validate($value['column'])) {
                return ['errors' => 'Body values[label] column is empty or not a string'];
            } elseif (empty($columns[$value['column']])) {
                return ['errors' => 'Body values[label] column is not valid'];
            } elseif (!isset($value['delimiterStart'])) {
                return ['errors' => 'Body values[label] delimiterStart is not set'];
            } elseif (!isset($value['delimiterEnd'])) {
                return ['errors' => 'Body values[label] delimiterEnd is not set'];
            } elseif (strpos($value['column'], 'password') !== false || strpos($value['column'], 'token') !== false) {
                return ['errors' => 'Body values[label] column is not allowed'];
            }
            if ($body['type'] == 'date' && stripos($columns[$value['column']], 'timestamp') === false) {
                return ['errors' => 'Body values[label] column is not a date', 'lang' => 'invalidColumnType'];
            } elseif ($body['type'] == 'integer' && !in_array($columns[$value['column']], self::NUMERIC_TYPES)) {
                return ['errors' => 'Body values[label] column is not an integer', 'lang' => 'invalidColumnType'];
            } elseif (in_array($body['type'], ['date', 'integer']) && (!empty($value['delimiterStart']) || !empty($value['delimiterEnd']))) {
                return ['errors' => 'Delimiters are forbidden for this type', 'lang' => 'forbiddenDelimiterType'];
            }
        }
        if ($body['type'] == 'date' && stripos($columns[$body['values']['key']], 'timestamp') === false) {
            return ['errors' => 'Body values[label] column is not a date', 'lang' => 'invalidColumnType'];
        }
        if ($body['type'] == 'integer' && !in_array($columns[$body['values']['key']], self::NUMERIC_TYPES)) {
            return ['errors' => 'Body values[label] column is not an integer', 'lang' => 'invalidColumnType'];
        }
        if (stripos($body['values']['clause'], 'select') !== false) {
            return ['errors' => 'Clause is not valid', 'lang' => 'invalidClause'];
        }

        try {
            $body['values']['resId'] = 100;
            CustomFieldModel::getValuesSQL($body['values']);
        } catch (\Exception $e) {
            return ['errors' => 'Clause is not valid', 'lang' => 'invalidClause'];
        }

        return true;
    }
}
