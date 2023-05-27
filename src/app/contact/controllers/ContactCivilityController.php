<?php

/**
 * Copyright Maarch since 2008 under licence GPLv3.
 * See LICENCE.txt file at the root folder for more details.
 * This file is part of Maarch software.
 */

/**
 * @brief Contact Civility Controller
 * @author dev@maarch.org
 */

namespace Contact\controllers;

use Contact\models\ContactCivilityModel;
use Contact\models\ContactModel;
use Group\controllers\PrivilegeController;
use Respect\Validation\Validator;
use Slim\Psr7\Request;
use SrcCore\http\Response;
use SrcCore\models\ValidatorModel;

class ContactCivilityController
{
    public function get(Request $request, Response $response)
    {
        $civilities = ContactCivilityModel::get(['select' => ['*']]);

        return $response->withJson(['civilities' => $civilities]);
    }

    public function create(Request $request, Response $response)
    {
        if (!PrivilegeController::hasPrivilege(['privilegeId' => 'admin_contacts', 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }

        $body = $request->getParsedBody();

        if (empty($body)) {
            return $response->withStatus(400)->withJson(['errors' => 'Body is not set or empty']);
        } elseif (!Validator::stringType()->notEmpty()->validate($body['label'] ?? null)) {
            return $response->withStatus(400)->withJson(['errors' => 'Body label is empty or not a string']);
        } elseif (!Validator::stringType()->length(1, 16)->notEmpty()->validate($body['abbreviation'] ?? null)) {
            return $response->withStatus(400)->withJson(['errors' => 'Body abbreviation is empty or not a string']);
        }


        $id = ContactCivilityModel::create([
            'label'         => $body['label'],
            'abbreviation'  => $body['abbreviation']
        ]);

        return $response->withJson(['id' => $id]);
    }

    public function getById(Request $request, Response $response, array $args)
    {
        if (!Validator::intVal()->validate($args['id'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Route id is not an integer']);
        }

        $civility = ContactCivilityModel::getById(['id' => $args['id'], 'select' => ['*']]);
        if (empty($civility)) {
            return $response->withStatus(400)->withJson(['errors' => 'Civility does not exist']);
        }

        return $response->withJson($civility);
    }

    public function update(Request $request, Response $response, array $args)
    {
        if (!Validator::intVal()->validate($args['id'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Route id is not an integer']);
        }

        if (!PrivilegeController::hasPrivilege(['privilegeId' => 'admin_contacts', 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }

        $body = $request->getParsedBody();

        if (empty($body)) {
            return $response->withStatus(400)->withJson(['errors' => 'Body is not set or empty']);
        } elseif (!Validator::stringType()->notEmpty()->validate($body['label'] ?? null)) {
            return $response->withStatus(400)->withJson(['errors' => 'Body label is empty or not a string']);
        } elseif (!Validator::stringType()->length(1, 16)->notEmpty()->validate($body['abbreviation'] ?? null)) {
            return $response->withStatus(400)->withJson(['errors' => 'Body abbreviation is empty or not a string']);
        }

        ContactCivilityModel::update([
            'set'   => [
                'label'         => $body['label'],
                'abbreviation'  => $body['abbreviation']
            ],
            'where' => ['id = ?'],
            'data'  => [$args['id']]
        ]);

        return $response->withStatus(204);
    }

    public function delete(Request $request, Response $response, array $args)
    {
        if (!Validator::intVal()->validate($args['id'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Route id is not an integer']);
        }

        if (!PrivilegeController::hasPrivilege(['privilegeId' => 'admin_contacts', 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }

        ContactModel::update([
            'set'   => ['civility' => null],
            'where' => ['civility = ?'],
            'data'  => [$args['id']]
        ]);
        ContactCivilityModel::delete([
            'where' => ['id = ?'],
            'data'  => [$args['id']]
        ]);

        return $response->withStatus(204);
    }

    public static function getLabelById(array $args)
    {
        ValidatorModel::notEmpty($args, ['id']);
        ValidatorModel::intVal($args, ['id']);

        $civility = ContactCivilityModel::getById(['id' => $args['id'], 'select' => ['label']]);

        if (empty($civility)) {
            return '';
        }

        return $civility['label'];
    }

    public static function getIdByLabel(array $args)
    {
        ValidatorModel::notEmpty($args, ['label']);
        ValidatorModel::stringType($args, ['label']);

        $civility = ContactCivilityModel::get([
            'select' => ['id'],
            'where'  => ['label ilike ?'],
            'data'   => [$args['label']],
            'limit'  => 1
        ]);

        if (empty($civility)) {
            return '';
        }

        return $civility[0]['id'];
    }
}
