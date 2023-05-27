<?php

/**
 * Copyright Maarch since 2008 under licence GPLv3.
 * See LICENCE.txt file at the root folder for more details.
 * This file is part of Maarch software.
 */

/**
 * @brief Contact Custom Field Controller
 * @author dev@maarch.org
 */

namespace Contact\controllers;

use Contact\models\ContactCustomFieldListModel;
use Contact\models\ContactModel;
use Contact\models\ContactParameterModel;
use Group\controllers\PrivilegeController;
use History\controllers\HistoryController;
use Respect\Validation\Validator;
use Slim\Psr7\Request;
use SrcCore\http\Response;

class ContactCustomFieldController
{
    public function get(Request $request, Response $response)
    {
        $customFields = ContactCustomFieldListModel::get(['orderBy' => ['label']]);

        foreach ($customFields as $key => $customField) {
            $customFields[$key]['values'] = json_decode($customField['values'], true);
        }

        return $response->withJson(['customFields' => $customFields]);
    }

    public function create(Request $request, Response $response)
    {
        if (!PrivilegeController::hasPrivilege(['privilegeId' => 'admin_contacts', 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }

        $body = $request->getParsedBody();

        if (!Validator::stringType()->notEmpty()->validate($body['label'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body label is empty or not a string']);
        } elseif (!Validator::stringType()->notEmpty()->validate($body['type'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body type is empty or not a string']);
        } elseif (!empty($body['values']) && !Validator::arrayType()->notEmpty()->validate($body['values'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body values is not an array']);
        }

        $fields = ContactCustomFieldListModel::get(['select' => [1], 'where' => ['label = ?'], 'data' => [$body['label']]]);
        if (!empty($fields)) {
            return $response->withStatus(400)->withJson(['errors' => 'Custom field with this label already exists']);
        }

        $id = ContactCustomFieldListModel::create([
            'label'         => $body['label'],
            'type'          => $body['type'],
            'values'        => empty($body['values']) ? '[]' : json_encode($body['values'])
        ]);

        ContactParameterModel::create(['identifier' => 'contactCustomField_' . $id]);

        HistoryController::add([
            'tableName' => 'contacts_custom_fields_list',
            'recordId'  => $id,
            'eventType' => 'ADD',
            'info'      => _CONTACT_CUSTOMFIELDS_CREATION . " : {$body['label']}",
            'moduleId'  => 'contactCustomFieldList',
            'eventId'   => 'contactCustomFieldListCreation',
        ]);

        return $response->withJson(['id' => $id]);
    }

    public function update(Request $request, Response $response, array $args)
    {
        if (!PrivilegeController::hasPrivilege(['privilegeId' => 'admin_contacts', 'userId' => $GLOBALS['id']])) {
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
        }

        if (count(array_unique($body['values'])) < count($body['values'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Some values have the same name']);
        }

        $field = ContactCustomFieldListModel::getById(['select' => ['type', 'values'], 'id' => $args['id']]);
        if (empty($field)) {
            return $response->withStatus(400)->withJson(['errors' => 'Custom field not found']);
        }

        $fields = ContactCustomFieldListModel::get(['select' => [1], 'where' => ['label = ?', 'id != ?'], 'data' => [$body['label'], $args['id']]]);
        if (!empty($fields)) {
            return $response->withStatus(400)->withJson(['errors' => 'Custom field with this label already exists']);
        }

        if (in_array($field['type'], ['select', 'checkbox', 'radio'])) {
            $values = json_decode($field['values'], true);
            foreach ($values as $key => $value) {
                if (!empty($body['values'][$key]) && !in_array($value, $body['values'])) {
                    if ($field['type'] == 'checkbox') {
                        ContactModel::update([
                            'postSet'   => ['custom_fields' => "jsonb_insert(custom_fields, '{{$args['id']}, 0}', '\"".str_replace(["\\", "'", '"'], ["\\\\", "''", '\"'], $body['values'][$key])."\"')"],
                            'where'     => ["custom_fields->'{$args['id']}' @> ?"],
                            'data'      => ["\"".str_replace(["\\", '"'], ["\\\\", '\"'], $value)."\""]
                        ]);
                        ContactModel::update([
                            'postSet'   => ['custom_fields' => "jsonb_set(custom_fields, '{{$args['id']}}', (custom_fields->'{$args['id']}') - '".str_replace(["\\", "'", '"'], ["\\\\", "''", '\"'], $value)."')"],
                            'where'     => ["custom_fields->'{$args['id']}' @> ?"],
                            'data'      => ["\"".str_replace(["\\", '"'], ["\\\\", '\"'], $value)."\""]
                        ]);
                    } else {
                        ContactModel::update([
                            'postSet'   => ['custom_fields' => "jsonb_set(custom_fields, '{{$args['id']}}', '\"".str_replace(["\\", "'", '"'], ["\\\\", "''", '\"'], $body['values'][$key])."\"')"],
                            'where'     => ["custom_fields->'{$args['id']}' @> ?"],
                            'data'      => ["\"".str_replace(["\\", '"'], ["\\\\", '\"'], $value)."\""]
                        ]);
                    }
                }
            }
        }

        ContactCustomFieldListModel::update([
            'set'   => [
                'label'  => $body['label'],
                'values' => empty($body['values']) ? '[]' : json_encode($body['values'])
            ],
            'where' => ['id = ?'],
            'data'  => [$args['id']]
        ]);

        HistoryController::add([
            'tableName' => 'contacts_custom_fields_list',
            'recordId'  => $args['id'],
            'eventType' => 'UP',
            'info'      => _CONTACT_CUSTOMFIELDS_MODIFICATION . " : {$body['label']}",
            'moduleId'  => 'contactCustomFieldList',
            'eventId'   => 'contactCustomFieldListModification',
        ]);

        return $response->withStatus(204);
    }

    public function delete(Request $request, Response $response, array $args)
    {
        if (!PrivilegeController::hasPrivilege(['privilegeId' => 'admin_contacts', 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }

        if (!Validator::notEmpty()->intVal()->validate($args['id'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Param id is empty or not an integer']);
        }

        $field = ContactCustomFieldListModel::getById(['select' => ['label'], 'id' => $args['id']]);

        ContactModel::update(['postSet' => ['custom_fields' => "custom_fields - '{$args['id']}'"], 'where' => ['custom_fields != ?'], 'data' => [null]]);
        ContactParameterModel::delete(['where' => ['identifier = ?'], 'data' => ['contactCustomField_' . $args['id']]]);

        ContactCustomFieldListModel::delete([
            'where' => ['id = ?'],
            'data'  => [$args['id']]
        ]);

        HistoryController::add([
            'tableName' => 'contacts_custom_fields_list',
            'recordId'  => $args['id'],
            'eventType' => 'DEL',
            'info'      => _CONTACT_CUSTOMFIELDS_SUPPRESSION . " : {$field['label']}",
            'moduleId'  => 'contactCustomFieldList',
            'eventId'   => 'contactCustomFieldListSuppression',
        ]);

        return $response->withStatus(204);
    }
}
