<?php

/**
* Copyright Maarch since 2008 under licence GPLv3.
* See LICENCE.txt file at the root folder for more details.
* This file is part of Maarch software.

* @brief   ShippingTemplateController
* @author  dev <dev@maarch.org>
* @ingroup core
*/

namespace Shipping\controllers;

use Convert\controllers\ConvertPdfController;
use Docserver\models\DocserverModel;
use Entity\models\EntityModel;
use Group\controllers\PrivilegeController;
use History\controllers\HistoryController;
use Respect\Validation\Validator;
use SrcCore\models\CurlModel;
use Resource\models\ResModel;
use Resource\controllers\StoreController;
use Attachment\models\AttachmentModel;
use Shipping\models\ShippingTemplateModel;
use Shipping\models\ShippingModel;
use User\models\UserModel;
use Action\models\ActionModel;
use Status\models\StatusModel;
use SrcCore\controllers\LogsController;
use SrcCore\models\CoreConfigModel;
use Slim\Psr7\Request;
use SrcCore\http\Response;
use SrcCore\models\PasswordModel;
use SrcCore\models\ValidatorModel;
use Firebase\JWT\JWT;

class ShippingTemplateController
{
    private const MAILEVA_EVENT_RESOURCES = [
        'ON_STATUS_ACCEPTED' => [
            'mail/v2/sendings',
            'registered_mail/v2/sendings',
            'simple_registered_mail/v1/sendings',
            'lel/v2/sendings',
            'lrc/v1/sendings'
        ],
        'ON_STATUS_REJECTED' => [
            'mail/v2/sendings',
            'registered_mail/v2/sendings',
            'simple_registered_mail/v1/sendings',
            'lel/v2/sendings',
            'lrc/v1/sending'
        ],
        'ON_STATUS_PROCESSED' => [
            'mail/v2/sendings',
            'registered_mail/v2/sendings',
            'simple_registered_mail/v1/sendings',
            'lel/v2/sendings',
            'lrc/v1/sending'
        ],
        'ON_DEPOSIT_PROOF_RECEIVED' => [
            'registered_mail/v2/sendings'
        ],
        'ON_STATUS_ARCHIVED' => [
            'mail/v2/sendings',
            'registered_mail/v2/sendings',
            'simple_registered_mail/v1/sendings',
            'lel/v2/sending'
        ]
    ];

    public function get(Request $request, Response $response)
    {
        if (!PrivilegeController::hasPrivilege(['privilegeId' => 'admin_shippings', 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }

        return $response->withJson(['shippings' => ShippingTemplateModel::get(['select' => ['id', 'label', 'description', 'options', 'fee', 'entities', "account->>'id' as accountid"]])]);
    }

    public function getById(Request $request, Response $response, array $args)
    {
        if (!PrivilegeController::hasPrivilege(['privilegeId' => 'admin_shippings', 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }

        if (!Validator::intVal()->validate($args['id'])) {
            return $response->withStatus(400)->withJson(['errors' => 'id is not an integer']);
        }

        $shippingInfo = ShippingTemplateModel::getById(['id' => $args['id']]);
        if (empty($shippingInfo)) {
            return $response->withStatus(400)->withJson(['errors' => 'Shipping does not exist']);
        }
        
        $shippingInfo['account'] = json_decode($shippingInfo['account'], true);
        $shippingInfo['account']['password'] = '';
        $shippingInfo['options']  = json_decode($shippingInfo['options'], true);
        $shippingInfo['fee']      = json_decode($shippingInfo['fee'], true);
        $shippingInfo['entities'] = !empty($shippingInfo['entities']) ? json_decode($shippingInfo['entities'], true) : [];

        $shippingInfo['subscriptions'] = !empty($shippingInfo['subscriptions']) ? json_decode($shippingInfo['subscriptions'], true) : [];
        $shippingInfo['subscribed'] = !empty($shippingInfo['subscriptions']) || (!empty($shippingInfo['account']['id']) && ShippingTemplateController::isSubscribed(['accountId' => $shippingInfo['account']['id']]));
        unset($shippingInfo['subscriptions']);

        $allEntities = EntityModel::get([
            'select'    => ['e1.id', 'e1.entity_id', 'e1.entity_label', 'e2.id as parent_id'],
            'table'     => ['entities e1', 'entities e2'],
            'left_join' => ['e1.parent_entity_id = e2.entity_id'],
            'where'     => ['e1.enabled = ?'],
            'data'      => ['Y']
        ]);

        foreach ($allEntities as $key => $value) {
            $allEntities[$key]['id'] = $value['id'];
            if (empty($value['parent_id'])) {
                $allEntities[$key]['parent'] = '#';
                $allEntities[$key]['icon']   = "fa fa-building";
            } else {
                $allEntities[$key]['parent'] = $value['parent_id'];
                $allEntities[$key]['icon']   = "fa fa-sitemap";
            }
            $allEntities[$key]['state']['opened'] = false;
            $allEntities[$key]['allowed']         = true;
            if (!empty($shippingInfo['entities']) && in_array($value['id'], $shippingInfo['entities'])) {
                $allEntities[$key]['state']['opened']   = true;
                $allEntities[$key]['state']['selected'] = true;
            }
            $allEntities[$key]['text'] = $value['entity_label'];
        }

        return $response->withJson(['shipping' => $shippingInfo, 'entities' => $allEntities]);
    }

    public function create(Request $request, Response $response)
    {
        if (!PrivilegeController::hasPrivilege(['privilegeId' => 'admin_shippings', 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }

        $body = $request->getParsedBody();
        
        $errors = ShippingTemplateController::checkData($body, 'create');
        if (!empty($errors)) {
            return $response->withStatus(400)->withJson(['errors' => $errors]);
        }

        if (!empty($body['account']['password'])) {
            $body['account']['password'] = PasswordModel::encrypt(['password' => $body['account']['password']]);
        }

        $body['options']  = !empty($body['options']) ? json_encode($body['options']) : '{}';
        $body['fee']      = !empty($body['fee']) ? json_encode($body['fee']) : '{}';
        foreach ($body['entities'] as $key => $entity) {
            $body['entities'][$key] = (string)$entity;
        }
        $body['entities'] = !empty($body['entities']) ? json_encode($body['entities']) : '[]';
        $account          = !empty($body['account']) ? json_encode($body['account']) : '[]';

        $id = ShippingTemplateModel::create([
            'label'       => $body['label'],
            'description' => $body['description'],
            'options'     => $body['options'],
            'fee'         => $body['fee'],
            'entities'    => $body['entities'],
            'account'     => $account
        ]);

        if (!empty($body['subscribed'])) {
            if (empty($body['account']['id']) || !Validator::stringType()->validate($body['account']['id'])) {
                return $response->withStatus(400)->withJson(['errors' => 'Could not subscribe to notifications: body[account][id] is empty or not a string']);
            }
            $alreadySubscribed = ShippingTemplateController::isSubscribed(['accountId' => $body['account']['id']]);
            if (!$alreadySubscribed) {
                $body['id'] = $id;
                $subscriptions = ShippingTemplateController::subscribeToNotifications($body);
                if (!empty($subscriptions['errors'])) {
                    return $response->withStatus(400)->withJson(['errors' => $subscriptions['errors']]);
                }
                $body['subscriptions'] = $subscriptions['subscriptions'];
                $tokenMinIat = date_create_from_format('U', $subscriptions['iat'] - 1); // apply -1 to allow the new notifications but not those before
                $tokenMinIat = date_format($tokenMinIat, 'c');
                ShippingTemplateModel::update([
                    'set'   => [
                        'subscriptions' => $subscriptions['subscriptions'],
                        'token_min_iat' => $tokenMinIat
                    ],
                    'where' => ['id = ?'],
                    'data'  => [$id]
                ]);
            }
        }

        HistoryController::add([
            'tableName' => 'shipping_templates',
            'recordId'  => $id,
            'eventType' => 'ADD',
            'eventId'   => 'shippingadd',
            'info'      => _MAILEVA_ADDED . ' : ' . $body['label']
        ]);

        return $response->withJson(['shippingId' => $id]);
    }

    public function update(Request $request, Response $response, array $args)
    {
        if (!PrivilegeController::hasPrivilege(['privilegeId' => 'admin_shippings', 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }

        $body = $request->getParsedBody();
        $body['id'] = $args['id'];

        $errors = ShippingTemplateController::checkData($body, 'update');
        if (!empty($errors)) {
            return $response->withStatus(500)->withJson(['errors' => $errors]);
        }

        if (!empty($body['account']['password'])) {
            $body['account']['password'] = PasswordModel::encrypt(['password' => $body['account']['password']]);
        } else {
            $shippingInfo = ShippingTemplateModel::getById(['id' => $args['id'], 'select' => ['account']]);
            $shippingInfo['account'] = json_decode($shippingInfo['account'], true);
            $body['account']['password'] = $shippingInfo['account']['password'];
        }
        $alreadySubscribed = false;
        if (!empty($body['account']['id']) && Validator::stringType()->validate($body['account']['id'])) {
            $alreadySubscribed = ShippingTemplateController::isSubscribed(['accountId' => $body['account']['id']]);
        }
        unset($shippingInfo);

        $body['options']  = !empty($body['options']) ? json_encode($body['options']) : '{}';
        $body['fee']      = !empty($body['fee']) ? json_encode($body['fee']) : '{}';
        $body['entities'] = $body['entities'] ?? [];
        $body['subscribed'] = !empty($body['subscribed']);
        $body['entities'] = array_map(function ($entity) {
            return (string)$entity;
        }, $body['entities']);
        if ($body['subscribed'] && !$alreadySubscribed) {
            $subscriptions = ShippingTemplateController::subscribeToNotifications($body);
            if (!empty($subscriptions['errors'])) {
                return $response->withStatus(400)->withJson(['errors' => $subscriptions['errors']]);
            }
            $body['subscriptions'] = $subscriptions['subscriptions'];
            $body['token_min_iat'] = date_create_from_format('U', $subscriptions['iat']-1);
            $body['token_min_iat'] = date_format($body['token_min_iat'], 'c');
        } elseif (!$body['subscribed'] && $alreadySubscribed) {
            $subscriptions = ShippingTemplateController::unsubscribeFromNotifications($body);
            if (!empty($subscriptions['errors'])) {
                return $response->withStatus(400)->withJson(['errors' => $subscriptions['errors']]);
            }
            $body['subscriptions'] = $subscriptions['subscriptions'];
            $body['token_min_iat'] = new \DateTime();
            $body['token_min_iat'] = $body['token_min_iat']->format('c');
        }
        unset($body['subscribed']);
        $body['subscriptions'] = !empty($body['subscriptions']) ? json_encode($body['subscriptions']) : '[]';
        $body['entities'] = !empty($body['entities']) ? json_encode($body['entities']) : '[]';
        $body['account']  = !empty($body['account']) ? json_encode($body['account']) : '[]';

        ShippingTemplateModel::update([
            'where' => ['id = ?'],
            'data'  => [$args['id']],
            'set'   => $body
        ]);

        HistoryController::add([
            'tableName' => 'shipping_templates',
            'recordId'  => $args['id'],
            'eventType' => 'UP',
            'eventId'   => 'shippingup',
            'info'      => _MAILEVA_UPDATED. ' : ' . $body['label']
        ]);

        return $response->withJson(['success' => 'success']);
    }

    public function delete(Request $request, Response $response, array $args)
    {
        if (!PrivilegeController::hasPrivilege(['privilegeId' => 'admin_shippings', 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }

        if (!Validator::intVal()->validate($args['id'])) {
            return $response->withStatus(400)->withJson(['errors' => 'id is not an integer']);
        }

        $shippingInfo = ShippingTemplateModel::getById(['id' => $args['id'], 'select' => ['label', 'subscriptions', 'account']]);
        if (empty($shippingInfo)) {
            return $response->withStatus(400)->withJson(['errors' => 'Shipping does not exist']);
        }

        $subscriptions = json_decode($shippingInfo['subscriptions'], true);
        $account = json_decode($shippingInfo['account'], true);
        if (!empty($account['id']) && Validator::stringType()->validate($account['id'])) {
            if (!empty($subscriptions)) {
                $result = ShippingTemplateController::unsubscribeFromNotifications([
                    'id'            => $args['id'],
                    'account'       => $account,
                    'subscriptions' => $subscriptions
                ]);
                if (!empty($result['errors'])) {
                    return $response->withStatus(400)->withJson(['errors' => $result['errors']]);
                }
            }
        }

        ShippingTemplateModel::delete(['id' => $args['id']]);

        HistoryController::add([
            'tableName' => 'shipping_templates',
            'recordId'  => $args['id'],
            'eventType' => 'DEL',
            'eventId'   => 'shippingdel',
            'info'      => _MAILEVA_DELETED. ' : ' . $shippingInfo['label']
        ]);

        $shippings = ShippingTemplateModel::get(['select' => ['id', 'label', 'description', 'options', 'fee', 'entities']]);
        return $response->withJson(['shippings' => $shippings]);
    }

    public function initShipping(Request $request, Response $response)
    {
        if (!PrivilegeController::hasPrivilege(['privilegeId' => 'admin_shippings', 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }

        $allEntities = EntityModel::get([
            'select'    => ['e1.id', 'e1.entity_id', 'e1.entity_label', 'e2.id as parent_id'],
            'table'     => ['entities e1', 'entities e2'],
            'left_join' => ['e1.parent_entity_id = e2.entity_id'],
            'where'     => ['e1.enabled = ?'],
            'data'      => ['Y']
        ]);

        foreach ($allEntities as $key => $value) {
            $allEntities[$key]['id'] = (string)$value['id'];
            if (empty($value['parent_id'])) {
                $allEntities[$key]['parent'] = '#';
                $allEntities[$key]['icon']   = "fa fa-building";
            } else {
                $allEntities[$key]['parent'] = (string)$value['parent_id'];
                $allEntities[$key]['icon']   = "fa fa-sitemap";
            }

            $allEntities[$key]['allowed']           = true;
            $allEntities[$key]['state']['opened']   = true;

            $allEntities[$key]['text'] = $value['entity_label'];
        }

        return $response->withJson([
            'entities' => $allEntities,
        ]);
    }

    public function receiveNotification(Request $request, Response $response, array $args)
    {
        $mailevaConfig = CoreConfigModel::getMailevaConfiguration();
        if (empty($mailevaConfig)) {
            return ShippingTemplateController::logAndReturnError($response, 400, 'Maileva configuration does not exist');
        } elseif (!$mailevaConfig['enabled']) {
            return ShippingTemplateController::logAndReturnError($response, 400, 'Maileva configuration is disabled');
        }
        $shippingApiDomainName = $mailevaConfig['uri'];
        $shippingApiDomainName = str_replace(['http://', 'https://'], '', $shippingApiDomainName);
        $shippingApiDomainName = rtrim($shippingApiDomainName, '/');

        if (empty($args['id']) || !Validator::intVal()->validate($args['id'])) {
            return ShippingTemplateController::logAndReturnError($response, 400, 'No shipping template id provided');
        }
        $shippingTemplate = ShippingTemplateModel::getById(['id' => $args['id']]);
        if (empty($shippingTemplate)) {
            return ShippingTemplateController::logAndReturnError($response, 400, 'No shipping template found with this id');
        }
        $shippingTemplateAccount = json_decode($shippingTemplate['account'], true);
        $minIAT = new \DateTime($shippingTemplate['token_min_iat']);
        $minIAT = $minIAT->format('U');

        $authToken = $request->getQueryParams();
        $authToken = $authToken['auth_token'] ?? null;
        $payload = ShippingTemplateController::checkToken([
            'token'                 => $authToken,
            'shippingApiDomainName' => $shippingApiDomainName,
            'shippingTemplateId'    => $shippingTemplate['id'],
            'minIAT'                => $minIAT
        ]);
        if (!empty($payload['errors'])) {
            return ShippingTemplateController::logAndReturnError($response, 403, $payload['errors']);
        }

        $body = $request->getParsedBody();
        $error = null;
        if (!Validator::equals($shippingApiDomainName)->validate($body['source'])) {
            $error = 'Body source is different from the saved one';
        } elseif (!Validator::stringType()->length(1, 256)->validate($body['user_id'])) {
            $error = 'Body user_id is empty, too long, or not a string';
        } elseif (!Validator::stringType()->equals($mailevaConfig['clientId'])->validate($body['client_id'])) {
            $error = 'Body client_id does not match ours';
        } elseif (!Validator::stringType()->in(array_keys(ShippingTemplateController::MAILEVA_EVENT_RESOURCES))->validate($body['event_type'])) {
            $error = 'Body event_type is not an allowed value';
        } elseif (!Validator::stringType()->in(ShippingTemplateController::MAILEVA_EVENT_RESOURCES[$body['event_type']])->validate($body['resource_type'])) {
            $error = 'Body resource_type is not an allowed value';
        } elseif (!Validator::dateTime()->validate($body['event_date'])) {
            $error = 'Body event_date is not a valid date';
        } elseif (!empty($body['event_location']) && !Validator::equals('FR')->validate($body['event_location'])) {
            $error = 'Body event_location is not FR';
        } elseif (!Validator::stringType()->length(1, 256)->validate($body['resource_id'])) {
            $error = 'Body resource_id is empty, too long, or not a string';
        } elseif (!Validator::url()->validate($body['resource_location'])) {
            $error = 'Body resource_location is not a valid url';
        }
        if (!empty($error)) {
            return ShippingTemplateController::logAndReturnError($response, 400, $error . ' => ' . json_encode($body));
        }

        LogsController::add([
            'isTech'    => true,
            'moduleId'  => 'shipping',
            'level'     => 'DEBUG',
            'tableName' => '',
            'recordId'  => '',
            'eventType' => 'Shipping webhook body: ' . json_encode($body),
            'eventId'   => 'Shipping webhook'
        ]);

        $body = [
            'source'           => $body['source'],
            'userId'           => $body['user_id'],
            'clientId'         => $body['client_id'],
            'eventType'        => $body['event_type'],
            'resourceType'     => $body['resource_type'],
            'resourceId'       => $body['resource_id'],
            'eventDate'        => $body['event_date'],
            'eventLocation'    => $body['event_location'],
            'resourceLocation' => $body['resource_location']
        ];

        if (in_array($body['eventType'], ['ON_DEPOSIT_PROOF_RECEIVED', 'ON_ACKNOWLEDGEMENT_OF_RECEIPT_RECEIVED'])) {
            LogsController::add([
                'isTech'    => true,
                'moduleId'  => 'shipping',
                'level'     => 'DEBUG',
                'tableName' => '',
                'recordId'  => '',
                'eventType' => 'Shipping webhook debug: Body event_type is ignored',
                'eventId'   => 'Shipping webhook debug'
            ]);
            return $response->withStatus(201)->withJson(['errors' => 'Body event_type is ignored']);
        }

        $shipping = ShippingModel::get([
            'select' => ['id', 'sending_id', 'document_id', 'document_type', 'history', 'recipients', 'attachments', 'action_id'],
            'where'  => ['sending_id = ?'],
            'data'   => [$body['resourceId']]
        ]);
        if (empty($shipping[0])) {
            return ShippingTemplateController::logAndReturnError($response, 400, 'Body resource_id does not match any shipping');
        }
        $shipping = $shipping[0];
        $shipping['recipients']  = json_decode($shipping['recipients'], true);
        $shipping['attachments'] = json_decode($shipping['attachments'], true);
        $shipping['history']     = json_decode($shipping['history'], true);

        $resId = $shipping['document_id'];
        if ($shipping['document_type'] == 'attachment') {
            $referencedAttachment = AttachmentModel::getById([
                'id'     => $shipping['document_id'],
                'select' => ['res_id', 'res_id_master']
            ]);
            if (empty($referencedAttachment)) {
                return ShippingTemplateController::logAndReturnError($response, 400, 'Body document_id does not match any attachment');
            }
            $resId = $referencedAttachment['res_id_master'];
        }

        $actions = ActionModel::get([
            'select' => ['parameters'],
            'where'  => ['component = ?', 'id = ?'],
            'data'   => ['sendShippingAction', $shipping['action_id']],
            'limit'  => 1
        ]);
        if (empty($actions)) {
            return ShippingTemplateController::logAndReturnError($response, 400, 'No Maileva action available');
        }
        $actionParameters = json_decode($actions[0]['parameters'], true);
        $actionParameters = [
            'intermediateStatus' => $actionParameters['intermediateStatus'] ?? null,
            'errorStatus'        => $actionParameters['errorStatus'] ?? null,
            'finalStatus'        => $actionParameters['finalStatus'] ?? null
        ];
        if (!Validator::each(Validator::arrayType())->validate($actionParameters)) {
            return ShippingTemplateController::logAndReturnError($response, 400, 'Maileva action parameters are not arrays');
        }
        /**
         * expected format
         * $actionParameters = [
         *   'intermediateStatus' => [
         *     'actionStatus' => 'COU', // a MaarchCourrier status
         *     'mailevaStatus' => ['ON_STATUS_ACCEPTED', 'ON_STATUS_PROCESSED'] // an array of Maileva statuses
         *   ],
         *   'errorStatus' => [
         *     ... same format as intermediateStatus ...
         *   ],
         *   'finalStatus' => [
         *     ... same format as intermediateStatus ...
         *   ]
         * ]
         */
        foreach ($actionParameters as $phaseParameters) {
            if (
                !Validator::each(Validator::in(array_keys(ShippingTemplateController::MAILEVA_EVENT_RESOURCES)))->validate($phaseParameters['mailevaStatus'])
                || !Validator::stringType()->length(1, 10)->validate($phaseParameters['actionStatus'])
                || (
                    $phaseParameters['actionStatus'] != '_NOSTATUS_'
                    && empty(StatusModel::getById(['id' => $phaseParameters['actionStatus'], 'select' => [1]]))
                )
            ) {
                return ShippingTemplateController::logAndReturnError($response, 400, 'Maileva action parameters are invalid');
            }
        }

        $actionStatus = null;
        foreach ($actionParameters as $phaseStatuses) {
            if (in_array($body['eventType'], $phaseStatuses['mailevaStatus'])) {
                if ($phaseStatuses['actionStatus'] == '_NOSTATUS_') {
                    break;
                }
                $actionStatus = $phaseStatuses['actionStatus'];
                break;
            }
        }

        $shipping['history'][] = [
            'eventType'    => $body['eventType'],
            'eventDate'    => $body['eventDate'],
            'resourceId'   => $body['resourceId'],
            'resourceType' => $body['resourceType'],
            'status'       => $actionStatus
        ];

        if ($body['eventType'] == 'ON_STATUS_ARCHIVED') {
            $authToken = ShippingTemplateController::getMailevaAuthToken($mailevaConfig, $shippingTemplateAccount);
            if (!empty($authToken['errors'])) {
                return ShippingTemplateController::logAndReturnError($response, 400, $authToken['errors']);
            }

            $typist = UserModel::get([
                'select' => ['id'],
                'where'  => ['mode = ?'],
                'data'   => ['rest'],
                'limit'  => 1
            ]);
            if (empty($typist[0]['id'])) {
                return ShippingTemplateController::logAndReturnError($response, 500, 'no rest user available');
            }
            $typist = $typist[0]['id'];

            $previousAttachments = [];
            if (!empty($shipping['attachments'])) {
                $previousAttachments = AttachmentModel::get([
                    'select' => ['res_id', 'attachment_type', 'external_id as "externalId"'],
                    'where'  => ['res_id in (?)'],
                    'data'   => [$shipping['attachments']]
                ]);
            }
            foreach ($previousAttachments as $key => $previousAttachment) {
                $previousAttachments[$key]['externalId'] = json_decode($previousAttachment['externalId'], true);
            }

            $attachmentErrors = [];

            // deposit proof
            if (!in_array('shipping_deposit_proof', array_column($previousAttachments, 'attachment_type'))) {
                $curlResponse = CurlModel::exec([
                    'method'       => 'GET',
                    'url'          => str_replace('\\', '', $body['resourceLocation']) . '/download_deposit_proof',
                    'bearerAuth'   => ['token' => $authToken],
                    'headers'      => ['Accept: */*'],
                    'fileResponse' => true
                ]);
                if ($curlResponse['code'] != 200) {
                    $attachmentErrors[] = 'shipping_deposit_proof:download: deposit proof failed to download for sending ' . json_encode(['maarchShippingId' => $shipping['id'], 'mailevaSendingId' => $body['resourceId'], 'curlResponse' => $curlResponse['response']]);
                } else {
                    $attachmentId = StoreController::storeAttachment([
                        'title'       => _SHIPPING_ATTACH_DEPOSIT_PROOF . '_' . (new \DateTime($body['eventDate']))->format('d-m-Y'),
                        'resIdMaster' => $resId,
                        'type'        => 'shipping_deposit_proof',
                        'status'      => 'TRA',
                        'encodedFile' => base64_encode($curlResponse['response']),
                        'format'      => 'zip',
                        'typist'      => $typist,
                        'externalId'  => [
                            'shippingResourceType' => $body['resourceType'],
                            'shippingResourceId'   => $body['resourceId'],
                            'shippingEventDate'    => $body['eventDate']
                        ]
                    ]);
                    if (!empty($attachmentId['errors'])) {
                        $attachmentErrors[] = 'shipping_deposit_proof:save: could not save deposit proof to docserver: ' . json_encode($attachmentId['errors']);
                    } else {
                        $shipping['attachments'][] = $attachmentId;
                        ShippingModel::update([
                            'set'   => ['attachments' => json_encode($shipping['attachments'])],
                            'where' => ['id = ?'],
                            'data'  => [$shipping['id']]
                        ]);
                    }
                }
            }

            if (!empty($attachmentErrors)) {
                return ShippingTemplateController::logAndReturnError($response, 500, "attachment errors:\n" . json_encode($attachmentErrors, JSON_PRETTY_PRINT));
            }
        }

        // Update history and status only if no error occurred
        ShippingModel::update([
            'set'   => ['history' => json_encode($shipping['history'])],
            'where' => ['id = ?'],
            'data'  => [$shipping['id']]
        ]);

        ResModel::update([
            'set'   => ['status' => $actionStatus],
            'where' => ['res_id = ?'],
            'data'  => [$resId]
        ]);

        return $response->withStatus(201);
    }

    private static function checkData($args, $mode)
    {
        $errors = [];

        if ($mode == 'update') {
            if (!Validator::boolType()->validate($args['subscribed'])) {
                $errors[] = '"subscribed" field is not a boolean';
            }
            if (!Validator::intVal()->validate($args['id'])) {
                $errors[] = 'id is not an integer';
            } else {
                $shippingInfo = ShippingTemplateModel::getById(['id' => $args['id']]);
            }
            if (empty($shippingInfo)) {
                $errors[] = 'Shipping does not exist';
            }
        } elseif (!empty($args['account']) && (
            !Validator::notEmpty()->validate($args['account']['id'])
            || !Validator::notEmpty()->validate($args['account']['password'])
        )) {
            $errors[] = 'account id or password is empty';
        }

        if (!Validator::notEmpty()->length(1, 64)->validate($args['label'] ?? '')) {
            $errors[] = 'label is empty or too long';
        }
        if (!Validator::notEmpty()->length(1, 255)->validate($args['description'])) {
            $errors[] = 'description is empty or too long';
        }

        if (!empty($args['entities'])) {
            if (!Validator::arrayType()->validate($args['entities'])) {
                $errors[] = 'entities must be an array';
            } else {
                foreach ($args['entities'] as $entity) {
                    $info = EntityModel::getById(['id' => $entity, 'select' => ['id']]);
                    if (empty($info)) {
                        $errors[] = $entity . ' does not exists';
                    }
                }
            }
        }

        if (!empty($args['fee']) && !Validator::each(Validator::floatVal()->greaterThan(-1))->validate($args['fee'])) {
            $errors[] = 'fee must be an array with positive values';
        }

        return $errors;
    }

    public static function calculShippingFee(array $args)
    {
        $fee = 0;
        foreach ($args['resources'] as $value) {
            $resourceId = $value['res_id'];

            $collId = $value['type'] == 'attachment' ? 'attachments_coll' : 'letterbox_coll';

            $convertedResource = ConvertPdfController::getConvertedPdfById(['resId' => $resourceId, 'collId' => $collId]);
            $docserver         = DocserverModel::getByDocserverId(['docserverId' => $convertedResource['docserver_id'], 'select' => ['path_template']]);
            $pathToDocument    = $docserver['path_template'] . str_replace('#', DIRECTORY_SEPARATOR, $convertedResource['path']) . $convertedResource['filename'];

            $img = new \Imagick();
            $img->pingImage($pathToDocument);
            $pageCount = $img->getNumberImages();

            $attachmentFee = ($pageCount > 1) ? ($pageCount - 1) * $args['fee']['nextPagePrice'] : 0 ;
            $fee = $fee + $attachmentFee + $args['fee']['firstPagePrice'] + $args['fee']['postagePrice'];
        }

        return $fee;
    }

    private static function generateToken($args)
    {
        ValidatorModel::notEmpty($args, ['mailevaUri', 'shippingTemplateId']);
        ValidatorModel::stringType($args, ['mailevaUri']);
        ValidatorModel::intVal($args, ['shippingTemplateId']);

        $shippingApiDomainName = $args['mailevaUri'];
        $shippingApiDomainName = str_replace(['http://', 'https://'], '', $shippingApiDomainName);
        $shippingApiDomainName = rtrim($shippingApiDomainName, '/');

        $now = time();

        $payload = [
            'iss' => 'MaarchCourrier',
            'sub' => 'maileva_notifications',
            'aud' => $shippingApiDomainName,
            'iat' => $now,
            'shippingTemplateId' => $args['shippingTemplateId'],
        ];

        $jwt = JWT::encode($payload, CoreConfigModel::getEncryptKey());

        return ['jwt' => $jwt, 'iat' => $now];
    }

    private static function checkToken($args)
    {
        ValidatorModel::notEmpty($args, ['shippingTemplateId', 'shippingApiDomainName', 'minIAT']);
        ValidatorModel::stringType($args, ['token', 'mailevaUri']);
        ValidatorModel::intVal($args, ['shippingTemplateId', 'minIAT']);

        try {
            $payload = JWT::decode($args['token'], CoreConfigModel::getEncryptKey(), ['HS256']);
            $payload = (array)$payload;
        } catch (\Exception $e) {
            return ['errors' => 'Authentication failed'];
        }

        $now = time();

        if (!Validator::notEmpty()->stringType()->equals('MaarchCourrier')->validate($payload['iss'])) {
            return ['errors' => 'Authentication failed'];
        } elseif (!Validator::notEmpty()->stringType()->equals('maileva_notifications')->validate($payload['sub'])) {
            return ['errors' => 'Authentication failed'];
        } elseif (!Validator::notEmpty()->stringType()->equals($args['shippingApiDomainName'])->validate($payload['aud'])) {
            return ['errors' => 'Authentication failed'];
        } elseif (!Validator::notEmpty()->intVal()->min($args['minIAT'])->max($now)->validate($payload['iat'])) {
            return ['errors' => 'Authentication failed'];
        } elseif (!Validator::notEmpty()->intVal()->equals($args['shippingTemplateId'])->validate($payload['shippingTemplateId'])) {
            return ['errors' => 'Authentication failed'];
        }

        $payload = [
            'iss' => $payload['iss'],
            'sub' => $payload['sub'],
            'aud' => $payload['aud'],
            'iat' => $payload['iat'],
            'shippingTemplateId' => $payload['shippingTemplateId']
        ];

        return $payload;
    }

    private static function isSubscribed(array $args)
    {
        ValidatorModel::notEmpty($args, ['accountId']);
        ValidatorModel::stringType($args, ['accountId']);

        $result = ShippingTemplateModel::get([
            'select' => [1],
            'where'  => ['account->>\'id\' = ?', 'jsonb_array_length(subscriptions) > 0'],
            'data'   => [$args['accountId']],
            'limit'  => 1
        ]);

        return !empty($result);
    }

    private static function subscribeToNotifications(array $shippingTemplate)
    {
        ValidatorModel::notEmpty($shippingTemplate, ['id', 'account']);
        ValidatorModel::intVal($shippingTemplate, ['id']);
        ValidatorModel::arrayType($shippingTemplate, ['account', 'subscriptions']);

        $mailevaConfig = CoreConfigModel::getMailevaConfiguration();
        if (empty($mailevaConfig)) {
            return ['errors' => 'Maileva configuration does not exist'];
        } elseif (!$mailevaConfig['enabled']) {
            return ['errors' => 'Maileva configuration is disabled'];
        }
        $configFile = CoreConfigModel::getJsonLoaded(['path' => 'config/config.json']);
        $maarchUrl = rtrim($configFile['config']['maarchUrl'], '/') ?? null;
        if (empty($maarchUrl)) {
            return ['errors' => 'maarchUrl is not configured'];
        }
        $jwtAndIAT = ShippingTemplateController::generateToken(['mailevaUri' => $mailevaConfig['uri'], 'shippingTemplateId' => $shippingTemplate['id']]);
        $jwt = $jwtAndIAT['jwt'];
        $iat = $jwtAndIAT['iat'];
        $authToken = ShippingTemplateController::getMailevaAuthToken($mailevaConfig, $shippingTemplate['account']);
        if (!empty($authToken['errors'])) {
            return ['errors' => $authToken['errors']];
        }
        $subscriptions = [];
        foreach (ShippingTemplateController::MAILEVA_EVENT_RESOURCES as $eventType => $resourceTypes) {
            foreach ($resourceTypes as $resourceType) {
                $curlResponse = CurlModel::exec([
                    'method'     => 'POST',
                    'url'        => $mailevaConfig['uri'] . '/notification_center/v2/subscriptions',
                    'bearerAuth' => ['token' => $authToken],
                    'headers'   => [
                        'Accept: application/json',
                        'Content-Type: application/json'
                    ],
                    'body'       => json_encode([
                        'event_type'    => $eventType,
                        'resource_type' => $resourceType,
                        'callback_url'  => $maarchUrl . '/rest/administration/shippings/' . $shippingTemplate['id'] . '/notifications?auth_token=' . $jwt
                    ])
                ]);
                if ($curlResponse['code'] != 201) {
                    return ['errors' => 'Maileva POST/subscriptions returned HTTP ' . $curlResponse['code'] . '; ' . json_encode($curlResponse['response'], true)];
                }

                $subscriptionId = $curlResponse['response']['id'] ?? null;
                if (!empty($subscriptionId)) {
                    $subscriptions[] = $subscriptionId;
                }
            }
        }
        $subscriptions = array_values(array_unique(array_merge(($shippingTemplate['subscriptions'] ?? []), $subscriptions))) ?? [];

        return ['subscriptions' => $subscriptions, 'jwt' => $jwt, 'iat' => $iat];
    }

    private static function unsubscribeFromNotifications(array $shippingTemplate)
    {
        ValidatorModel::notEmpty($shippingTemplate, ['id', 'account']);
        ValidatorModel::intVal($shippingTemplate, ['id']);
        ValidatorModel::arrayType($shippingTemplate, ['account', 'subscriptions']);

        if (empty($shippingTemplate)) {
            return ['errors' => 'shipping template is empty'];
        }
        $subscribedElsewhere = false;
        if (empty($shippingTemplate['subscriptions'])) {
            $shippingTemplate = ShippingTemplateModel::get([
                'select' => ['id', 'account', 'subscriptions'],
                'where'  => ['account->>\'id\' = ?', 'jsonb_array_length(subscriptions) > 0'],
                'data'   => [$shippingTemplate['account']['id']],
                'limit'  => 1
            ]);
            if (empty($shippingTemplate[0])) {
                return ['errors' => 'no subscribed shipping template with matching account id'];
            }
            $shippingTemplate = $shippingTemplate[0];
            $shippingTemplate['account'] = json_decode($shippingTemplate['account'], true);
            $shippingTemplate['subscriptions'] = json_decode($shippingTemplate['subscriptions'], true);
            $subscribedElsewhere = true;
        }
        $mailevaConfig = CoreConfigModel::getMailevaConfiguration();
        if (empty($mailevaConfig)) {
            return ['errors' => 'Maileva configuration does not exist'];
        } elseif (!$mailevaConfig['enabled']) {
            return ['errors' => 'Maileva configuration is disabled'];
        }
        $authToken = ShippingTemplateController::getMailevaAuthToken($mailevaConfig, $shippingTemplate['account']);
        if (!empty($authToken['errors'])) {
            return ['errors' => $authToken['errors']];
        }
        foreach ($shippingTemplate['subscriptions'] as $subscriptionId) {
            $curlResponse = CurlModel::exec([
                'method'     => 'DELETE',
                'url'        => $mailevaConfig['uri'] . '/notification_center/v2/subscriptions/' . $subscriptionId,
                'bearerAuth' => ['token' => $authToken],
                'headers'    => ['Accept: application/json']
            ]);
            if ($curlResponse['code'] != 204) {
                return ['errors' => $curlResponse['response'] ?? ('Maileva DELETE/subscriptions/' . $subscriptionId . ' returned HTTP ' . $curlResponse['code'])];
            }
        }
        if ($subscribedElsewhere) {
            $now = new \DateTime();
            ShippingTemplateModel::update([
                'where'   => ['id = ?'],
                'data'    => [$shippingTemplate['id']],
                'set' => [
                    'subscriptions' => '[]',
                    'token_min_iat' => $now->format('c')
                ]
            ]);
        }

        return ['subscriptions' => []];
    }

    private static function logAndReturnError(Response $response, int $httpStatusCode, string $error)
    {
        LogsController::add([
            'isTech'    => true,
            'moduleId'  => 'shipping',
            'level'     => 'ERROR',
            'tableName' => '',
            'recordId'  => '',
            'eventType' => 'Shipping webhook error: ' . $error,
            'eventId'   => 'Shipping webhook error'
        ]);
        return $response->withStatus($httpStatusCode)->withJson(['errors' => $error]);
    }

    private static function getMailevaAuthToken(array $mailevaConfig, array $shippingTemplateAccount) {
        $curlAuth = CurlModel::exec([
            'url'           => $mailevaConfig['connectionUri'] . '/authentication/oauth2/token',
            'basicAuth'     => ['user' => $mailevaConfig['clientId'], 'password' => $mailevaConfig['clientSecret']],
            'headers'       => ['Content-Type: application/x-www-form-urlencoded'],
            'method'        => 'POST',
            'queryParams'   => [
                'grant_type'    => 'password',
                'username'      => $shippingTemplateAccount['id'],
                'password'      => PasswordModel::decrypt(['cryptedPassword' => $shippingTemplateAccount['password']])
            ]
        ]);
        if ($curlAuth['code'] != 200) {
            return ['errors' => 'Maileva authentication failed'];
        }
        $token = $curlAuth['response']['access_token'];
        return $token;
    }
}
