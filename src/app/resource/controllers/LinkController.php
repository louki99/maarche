<?php

/**
* Copyright Maarch since 2008 under licence GPLv3.
* See LICENCE.txt file at the root folder for more details.
* This file is part of Maarch software.
*
*/

/**
* @brief Link Controller
* @author dev@maarch.org
*/

namespace Resource\controllers;

use Contact\controllers\ContactController;
use Contact\models\ContactModel;
use Convert\controllers\ConvertPdfController;
use Entity\models\EntityModel;
use Entity\models\ListInstanceModel;
use Group\controllers\PrivilegeController;
use History\controllers\HistoryController;
use Resource\models\ResModel;
use Resource\models\ResourceContactModel;
use Respect\Validation\Validator;
use Slim\Psr7\Request;
use SrcCore\http\Response;
use Status\models\StatusModel;
use User\models\UserModel;

class LinkController
{
    public function getLinkedResources(Request $request, Response $response, array $args)
    {
        if (!Validator::intVal()->validate($args['resId']) || !ResController::hasRightByResId(['resId' => [$args['resId']], 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Resource out of perimeter']);
        }

        $resource = ResModel::getById(['resId' => $args['resId'], 'select' => ['res_id', 'linked_resources']]);
        $linkedResourcesIds = json_decode($resource['linked_resources'], true);

        $linkedResources = [];
        if (!empty($linkedResourcesIds)) {
            $linkedResourcesIds = ResController::getAuthorizedResources(['resources' => $linkedResourcesIds, 'userId' => $GLOBALS['id']]);
            if (!empty($linkedResourcesIds)) {
                $linkedResources = ResModel::get([
                    'select' => ['res_id as "resId"', 'subject', 'doc_date as "documentDate"', 'status', 'dest_user as "destUser"', 'destination', 'alt_identifier as chrono', 'category_id as "categoryId"', 'filename', 'format', 'confidentiality'],
                    'where'  => ['res_id in (?)'],
                    'data'   => [$linkedResourcesIds]
                ]);
            }

            foreach ($linkedResources as $key => $value) {
                $linkedResources[$key]['hasDocument'] = !empty($value['filename']);
                $linkedResources[$key]['confidentiality'] = $value['confidentiality'] == 'Y';
                if (!empty($value['status'])) {
                    $status = StatusModel::getById(['id' => $value['status'], 'select' => ['label_status', 'img_filename']]);
                    $linkedResources[$key]['statusLabel'] = $status['label_status'];
                    $linkedResources[$key]['statusImage'] = $status['img_filename'];
                }

                if (!empty($value['destUser'])) {
                    $linkedResources[$key]['destUserLabel'] = UserModel::getLabelledUserById(['id' => $value['destUser']]);
                }
                if (!empty($value['destination'])) {
                    $linkedResources[$key]['destinationLabel'] = EntityModel::getByEntityId(['entityId' => $value['destination'], 'select' => ['short_label']])['short_label'];
                }

                $correspondents = ResourceContactModel::get([
                    'select'    => ['res_id', 'item_id', 'type', 'mode'],
                    'where'     => ['res_id = ?'],
                    'data'      => [$value['resId']]
                ]);

                $linkedResources[$key]['senders'] = [];
                $linkedResources[$key]['recipients'] = [];
                foreach ($correspondents as $correspondent) {
                    if ($correspondent['res_id'] == $resource['res_id']) {
                        if ($correspondent['type'] == 'contact') {
                            $contactRaw = ContactModel::getById(['select' => ['firstname', 'lastname', 'company'], 'id' => $correspondent['item_id']]);
                            $contactToDisplay = ContactController::getFormattedOnlyContact(['contact' => $contactRaw]);
                            $formattedCorrespondent = $contactToDisplay['contact']['otherInfo'];
                        } elseif ($correspondent['type'] == 'user') {
                            $formattedCorrespondent = UserModel::getLabelledUserById(['id' => $correspondent['item_id']]);
                        } else {
                            $entity = EntityModel::getById(['id' => $correspondent['item_id'], 'select' => ['entity_label']]);
                            $formattedCorrespondent = $entity['entity_label'];
                        }

                        $linkedResources[$key]["{$correspondent['mode']}s"][] = $formattedCorrespondent;
                    }
                }

                $linkedResources[$key]['visaCircuit'] = ListInstanceModel::get(['select' => ['item_id', 'item_mode'], 'where' => ['res_id = ?', 'difflist_type = ?'], 'data' => [$value['resId'], 'VISA_CIRCUIT']]);
                foreach ($linkedResources[$key]['visaCircuit'] as $keyCircuit => $valueCircuit) {
                    $linkedResources[$key]['visaCircuit'][$keyCircuit]['userLabel'] = UserModel::getLabelledUserById(['id' => $valueCircuit['item_id']]);
                }

                $linkedResources[$key]['canConvert'] = false;
                if (!empty($value['format'])) {
                    $linkedResources[$key]['canConvert'] = ConvertPdfController::canConvert(['extension' => $value['format']]);
                }
            }
        }

        return $response->withJson(['linkedResources' => $linkedResources]);
    }

    public function linkResources(Request $request, Response $response, array $args)
    {
        if (!PrivilegeController::hasPrivilege(['privilegeId' => 'add_links', 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }

        if (!Validator::intVal()->validate($args['resId']) || !ResController::hasRightByResId(['resId' => [$args['resId']], 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Resource out of perimeter']);
        }

        $body = $request->getParsedBody();

        if (!Validator::arrayType()->notEmpty()->validate($body['linkedResources'])) {
            return $response->withStatus(403)->withJson(['errors' => 'Body linkedResources is empty or not an array']);
        } elseif (!ResController::hasRightByResId(['resId' => $body['linkedResources'], 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Body linkedResources out of perimeter']);
        } elseif (in_array($args['resId'], $body['linkedResources'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body linkedResources contains resource']);
        }

        $resource = ResModel::getById(['resId' => $args['resId'], 'select' => ['linked_resources', 'alt_identifier']]);
        $linkedResources = json_decode($resource['linked_resources'], true);
        $linkedResources = array_merge($linkedResources, $body['linkedResources']);
        $linkedResources = array_unique($linkedResources);
        foreach ($linkedResources as $key => $value) {
            $linkedResources[$key] = (string)$value;
        }

        ResModel::update([
            'set'       => ['linked_resources' => json_encode($linkedResources)],
            'where'     => ['res_id = ?'],
            'data'      => [$args['resId']]
        ]);
        ResModel::update([
            'postSet'   => ['linked_resources' => "jsonb_insert(linked_resources, '{0}', '\"{$args['resId']}\"')"],
            'where'     => ['res_id in (?)', "(linked_resources @> ?) = false"],
            'data'      => [$body['linkedResources'], "\"{$args['resId']}\""]
        ]);

        $linkedResourcesInfo = ResModel::get([
            'select' => ['alt_identifier', 'res_id'],
            'where'  => ['res_id in (?)'],
            'data'   => [$body['linkedResources']]
        ]);
        $linkedResourcesAltIdentifier = array_column($linkedResourcesInfo, 'alt_identifier', 'res_id');

        foreach ($body['linkedResources'] as $value) {
            HistoryController::add([
                'tableName' => 'res_letterbox',
                'recordId'  => $args['resId'],
                'eventType' => 'UP',
                'info'      => _LINK_ADDED . " : {$linkedResourcesAltIdentifier[$value]}",
                'moduleId'  => 'resource',
                'eventId'   => 'resourceModification'
            ]);
            HistoryController::add([
                'tableName' => 'res_letterbox',
                'recordId'  => $value,
                'eventType' => 'UP',
                'info'      => _LINK_ADDED . " : {$resource['alt_identifier']}",
                'moduleId'  => 'resource',
                'eventId'   => 'resourceModification'
            ]);
        }

        return $response->withStatus(204);
    }

    public function unlinkResources(Request $request, Response $response, array $args)
    {
        if (!PrivilegeController::hasPrivilege(['privilegeId' => 'add_links', 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }

        if (!Validator::intVal()->validate($args['resId']) || !ResController::hasRightByResId(['resId' => [$args['resId']], 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Resource out of perimeter']);
        }

        if (!Validator::intVal()->validate($args['id']) || !ResController::hasRightByResId(['resId' => [$args['id']], 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Resource to unlink out of perimeter']);
        }

        ResModel::update([
            'postSet'   => ['linked_resources' => "linked_resources - '{$args['id']}'"],
            'where'     => ['res_id = ?'],
            'data'      => [$args['resId']]
        ]);
        ResModel::update([
            'postSet'   => ['linked_resources' => "linked_resources - '{$args['resId']}'"],
            'where'     => ['res_id = ?'],
            'data'      => [$args['id']]
        ]);

        $linkedResourcesInfo = ResModel::get([
            'select' => ['alt_identifier', 'res_id'],
            'where'  => ['res_id in (?)'],
            'data'   => [[$args['resId'], $args['id']]]
        ]);
        $linkedResourcesAltIdentifier = array_column($linkedResourcesInfo, 'alt_identifier', 'res_id');

        HistoryController::add([
            'tableName' => 'res_letterbox',
            'recordId'  => $args['resId'],
            'eventType' => 'UP',
            'info'      => _LINK_DELETED . " : {$linkedResourcesAltIdentifier[$args['id']]}",
            'moduleId'  => 'resource',
            'eventId'   => 'resourceModification'
        ]);
        HistoryController::add([
            'tableName' => 'res_letterbox',
            'recordId'  => $args['id'],
            'eventType' => 'UP',
            'info'      => _LINK_DELETED . " : {$linkedResourcesAltIdentifier[$args['resId']]}",
            'moduleId'  => 'resource',
            'eventId'   => 'resourceModification'
        ]);

        return $response->withStatus(204);
    }
}
