<?php

/**
* Copyright Maarch since 2008 under licence GPLv3.
* See LICENCE.txt file at the root folder for more details.
* This file is part of Maarch software.
*/

/**
 * @brief Issuing Site Controller
 * @author dev@maarch.org
 */

namespace RegisteredMail\controllers;

use Group\controllers\PrivilegeController;
use History\controllers\HistoryController;
use RegisteredMail\models\IssuingSiteEntitiesModel;
use RegisteredMail\models\IssuingSiteModel;
use RegisteredMail\models\RegisteredMailModel;
use Respect\Validation\Validator;
use Slim\Psr7\Request;
use SrcCore\http\Response;

class IssuingSiteController
{
    public function get(Request $request, Response $response)
    {
        $sites = IssuingSiteModel::get();

        foreach ($sites as $key => $site) {
            $sites[$key] = [
                'id'                 => $site['id'],
                'label'              => $site['label'],
                'postOfficeLabel'    => $site['post_office_label'],
                'accountNumber'      => $site['account_number'],
                'addressNumber'      => $site['address_number'],
                'addressStreet'      => $site['address_street'],
                'addressAdditional1' => $site['address_additional1'],
                'addressAdditional2' => $site['address_additional2'],
                'addressPostcode'    => $site['address_postcode'],
                'addressTown'        => $site['address_town'],
                'addressCountry'     => $site['address_country']
            ];

            $entities = IssuingSiteEntitiesModel::get([
                'select' => ['entity_id'],
                'where'  => ['site_id = ?'],
                'data'   => [$site['id']]
            ]);
    
            $entities = array_column($entities, 'entity_id');
            $sites[$key]['entities'] = $entities;
        }

        return $response->withJson(['sites' => $sites]);
    }

    public function getById(Request $request, Response $response, array $args)
    {
        $site = IssuingSiteModel::getById(['id' => $args['id']]);

        if (empty($site)) {
            return $response->withStatus(400)->withJson(['errors' => 'Issuing site not found']);
        }

        $site = [
            'id'                 => $site['id'],
            'label'              => $site['label'],
            'postOfficeLabel'    => $site['post_office_label'] ?? null,
            'accountNumber'      => $site['account_number'],
            'addressNumber'      => $site['address_number'],
            'addressStreet'      => $site['address_street'],
            'addressAdditional1' => $site['address_additional1'] ?? null,
            'addressAdditional2' => $site['address_additional2'] ?? null,
            'addressPostcode'    => $site['address_postcode'],
            'addressTown'        => $site['address_town'],
            'addressCountry'     => $site['address_country'] ?? null
        ];

        $entities = IssuingSiteEntitiesModel::get([
            'select' => ['entity_id'],
            'where'  => ['site_id = ?'],
            'data'   => [$args['id']]
        ]);

        $entities = array_column($entities, 'entity_id');

        $site['entities'] = $entities;

        return $response->withJson(['site' => $site]);
    }

    public function create(Request $request, Response $response)
    {
        if (!PrivilegeController::hasPrivilege(['privilegeId' => 'admin_registered_mail', 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }

        $body = $request->getParsedBody();

        if (!Validator::stringType()->notEmpty()->validate($body['label'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body label is empty or not a string']);
        }
        if (!Validator::notEmpty()->intVal()->length(1, 10)->validate($body['accountNumber'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body accountNumber is empty or not an integer with less than 11 digits']);
        }
        if (!Validator::stringType()->notEmpty()->validate($body['addressNumber'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body addressNumber is empty or not an integer']);
        }
        if (!Validator::stringType()->notEmpty()->validate($body['addressStreet'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body addressStreet is empty or not a string']);
        }
        if (!Validator::stringType()->notEmpty()->validate($body['addressPostcode'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body addressPostcode is empty or not a string']);
        }
        if (!Validator::stringType()->notEmpty()->validate($body['addressTown'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body addressTown is empty or not a string']);
        }

        $site = IssuingSiteModel::get([
            'select' => [1],
            'where'  => ['account_number = ?'],
            'data'   => [$body['accountNumber']],
            'limit ' => 1
        ]);
        if (!empty($site)) {
            return $response->withStatus(400)->withJson(['errors' => 'Body accountNumber is already used by another site', 'lang' => 'accountNumberAlreadyUsed']);
        }

        if (!empty($body['entities']) && !Validator::arrayType()->validate($body['entities'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body entities is not an array']);
        } elseif (!empty($body['entities']) && Validator::arrayType()->validate($body['entities'])) {
            foreach ($body['entities'] as $key => $entity) {
                if (!Validator::intVal()->validate($entity)) {
                    return $response->withStatus(400)->withJson(['errors' => "Body entities[$key] is not an integer"]);
                }
            }
        }

        $id = IssuingSiteModel::create([
            'label'              => $body['label'],
            'postOfficeLabel'    => $body['postOfficeLabel'] ?? null,
            'accountNumber'      => $body['accountNumber'],
            'addressNumber'      => $body['addressNumber'],
            'addressStreet'      => $body['addressStreet'],
            'addressAdditional1' => $body['addressAdditional1'] ?? null,
            'addressAdditional2' => $body['addressAdditional2'] ?? null,
            'addressPostcode'    => $body['addressPostcode'],
            'addressTown'        => $body['addressTown'],
            'addressCountry'     => $body['addressCountry'] ?? null
        ]);

        if (!empty($body['entities'])) {
            foreach ($body['entities'] as $entity) {
                IssuingSiteEntitiesModel::create(['siteId' => $id, 'entityId' => $entity]);
            }
        }

        HistoryController::add([
            'tableName' => 'issuing_sites',
            'recordId'  => $id,
            'eventType' => 'ADD',
            'info'      => _ISSUING_SITE_CREATED . " : {$id}",
            'moduleId'  => 'issuing_sites',
            'eventId'   => 'issuingSitesCreation',
        ]);

        return $response->withJson(['id' => $id]);
    }

    public function update(Request $request, Response $response, array $args)
    {
        if (!PrivilegeController::hasPrivilege(['privilegeId' => 'admin_registered_mail', 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }

        $site = IssuingSiteModel::getById(['id' => $args['id']]);
        if (empty($site)) {
            return $response->withStatus(400)->withJson(['errors' => 'Issuing site not found']);
        }

        $body = $request->getParsedBody();

        if (!Validator::stringType()->notEmpty()->validate($body['label'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body label is empty or not a string']);
        }
        if (!Validator::notEmpty()->intVal()->length(1, 10)->validate($body['accountNumber'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body accountNumber is empty or not an integer with less than 11 digits']);
        }
        if (!Validator::stringType()->notEmpty()->validate($body['addressNumber'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body addressNumber is empty or not an integer']);
        }
        if (!Validator::stringType()->notEmpty()->validate($body['addressStreet'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body addressStreet is empty or not a string']);
        }
        if (!Validator::stringType()->notEmpty()->validate($body['addressPostcode'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body addressPostcode is empty or not a string']);
        }
        if (!Validator::stringType()->notEmpty()->validate($body['addressTown'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body addressTown is empty or not a string']);
        }

        $site = IssuingSiteModel::get([
            'select' => [1],
            'where'  => ['account_number = ?', 'id != ?'],
            'data'   => [$body['accountNumber'], $args['id']],
            'limit ' => 1
        ]);
        if (!empty($site)) {
            return $response->withStatus(400)->withJson(['errors' => 'Body accountNumber is already used by another site', 'lang' => 'accountNumberAlreadyUsed']);
        }

        if (!empty($body['entities']) && !Validator::arrayType()->validate($body['entities'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body entities is not an array']);
        } elseif (!empty($body['entities']) && Validator::arrayType()->validate($body['entities'])) {
            foreach ($body['entities'] as $key => $entity) {
                if (!Validator::intVal()->validate($entity)) {
                    return $response->withStatus(400)->withJson(['errors' => "Body entities[$key] is not an integer"]);
                }
            }
        }

        IssuingSiteModel::update([
            'set'   => [
                'label'               => $body['label'],
                'post_office_label'   => $body['postOfficeLabel'] ?? null,
                'account_number'      => $body['accountNumber'],
                'address_number'      => $body['addressNumber'],
                'address_street'      => $body['addressStreet'],
                'address_additional1' => $body['addressAdditional1'] ?? null,
                'address_additional2' => $body['addressAdditional2'] ?? null,
                'address_postcode'    => $body['addressPostcode'],
                'address_town'        => $body['addressTown'],
                'address_country'     => $body['addressCountry'] ?? null
            ],
            'where' => ['id = ?'],
            'data'  => [$args['id']]
        ]);

        IssuingSiteEntitiesModel::delete([
            'where' => ['site_id = ?'],
            'data'  => [$args['id']]
        ]);

        if (!empty($body['entities'])) {
            foreach ($body['entities'] as $entity) {
                IssuingSiteEntitiesModel::create(['siteId' => $args['id'], 'entityId' => $entity]);
            }
        }

        HistoryController::add([
            'tableName' => 'issuing_sites',
            'recordId'  => $args['id'],
            'eventType' => 'UP',
            'info'      => _ISSUING_SITE_UPDATED . " : {$args['id']}",
            'moduleId'  => 'issuing_sites',
            'eventId'   => 'issuingSitesModification',
        ]);

        return $response->withStatus(204);
    }

    public function delete(Request $request, Response $response, array $args)
    {
        if (!PrivilegeController::hasPrivilege(['privilegeId' => 'admin_registered_mail', 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }

        $site = IssuingSiteModel::getById(['id' => $args['id']]);
        if (empty($site)) {
            return $response->withStatus(204);
        }

        $issuingSite = RegisteredMailModel::get([
            'select'    => [1],
            'where'     => ['issuing_site = ?'],
            'data'      => [$args['id']]
        ]);
        if (!empty($issuingSite)) {
            return $response->withStatus(400)->withJson(['errors' => 'Cannot delete site : site is already used by a registered mail', 'lang' => 'siteIsUsedByRegisteredMail']);
        }

        IssuingSiteEntitiesModel::delete([
            'where' => ['site_id = ?'],
            'data'  => [$args['id']]
        ]);

        IssuingSiteModel::delete([
            'where' => ['id = ?'],
            'data'  => [$args['id']]
        ]);

        HistoryController::add([
            'tableName' => 'issuing_sites',
            'recordId'  => $args['id'],
            'eventType' => 'DEL',
            'info'      => _ISSUING_SITE_DELETED . " : {$args['id']}",
            'moduleId'  => 'issuing_sites',
            'eventId'   => 'issuingSitesSuppression',
        ]);

        return $response->withStatus(204);
    }
}
