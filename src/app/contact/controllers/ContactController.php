<?php

/**
 * Copyright Maarch since 2008 under licence GPLv3.
 * See LICENCE.txt file at the root folder for more details.
 * This file is part of Maarch software.
 */

/**
 * @brief Contact Controller
 * @author dev@maarch.org
 */

namespace Contact\controllers;

use AcknowledgementReceipt\models\AcknowledgementReceiptModel;
use Attachment\models\AttachmentModel;
use Contact\models\ContactAddressSectorModel;
use Contact\models\ContactCivilityModel;
use Contact\models\ContactCustomFieldListModel;
use Contact\models\ContactFillingModel;
use Contact\models\ContactGroupListModel;
use Contact\models\ContactModel;
use Contact\models\ContactParameterModel;
use Entity\models\EntityModel;
use Group\controllers\PrivilegeController;
use History\controllers\HistoryController;
use MessageExchange\controllers\AnnuaryController;
use Parameter\models\ParameterModel;
use Resource\controllers\ResController;
use Resource\models\ResModel;
use Resource\models\ResourceContactModel;
use Respect\Validation\Validator;
use Slim\Psr7\Request;
use SrcCore\http\Response;
use SrcCore\controllers\AutoCompleteController;
use SrcCore\models\CoreConfigModel;
use SrcCore\models\DatabaseModel;
use SrcCore\models\PasswordModel;
use SrcCore\models\TextFormatModel;
use SrcCore\models\ValidatorModel;
use User\models\UserModel;

class ContactController
{
    const MAPPING_FIELDS = [
        'civility'              => 'civility',
        'firstname'             => 'firstname',
        'lastname'              => 'lastname',
        'company'               => 'company',
        'department'            => 'department',
        'function'              => 'function',
        'addressNumber'         => 'address_number',
        'addressStreet'         => 'address_street',
        'addressAdditional1'    => 'address_additional1',
        'addressAdditional2'    => 'address_additional2',
        'addressPostcode'       => 'address_postcode',
        'addressTown'           => 'address_town',
        'addressCountry'        => 'address_country',
        'email'                 => 'email',
        'phone'                 => 'phone',
        'notes'                 => 'notes',
        'sector'                => 'sector'
    ];

    public function get(Request $request, Response $response)
    {
        if (!PrivilegeController::hasPrivilege(['privilegeId' => 'admin_contacts', 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }

        $queryParams = $request->getQueryParams();

        $queryParams['offset'] = (empty($queryParams['offset']) || !is_numeric($queryParams['offset']) ? 0 : (int)$queryParams['offset']);
        $queryParams['limit'] = (empty($queryParams['limit']) || !is_numeric($queryParams['limit']) ? 25 : (int)$queryParams['limit']);
        $order = !in_array($queryParams['order'], ['asc', 'desc']) ? '' : $queryParams['order'];
        $orderBy = !in_array($queryParams['orderBy'], ['firstname', 'lastname', 'company']) ? ['id'] : ["{$queryParams['orderBy']} {$order}", 'id'];

        if (!empty($queryParams['search'])) {
            $fields = ['firstname', 'lastname', 'company', 'address_number', 'address_street', 'address_additional1', 'address_additional2', 'address_postcode', 'address_town', 'address_country'];
            $fieldsNumber = count($fields);
            $fields = AutoCompleteController::getInsensitiveFieldsForRequest(['fields' => $fields]);

            $requestData = AutoCompleteController::getDataForRequest([
                'search'        => $queryParams['search'],
                'fields'        => $fields,
                'where'         => [],
                'data'          => [],
                'fieldsNumber'  => $fieldsNumber
            ]);
        }

        $contacts = ContactModel::get([
            'select'    => [
                'id', 'firstname', 'lastname', 'company', 'address_number as "addressNumber"', 'address_street as "addressStreet"',
                'address_additional1 as "addressAdditional1"', 'address_additional2 as "addressAdditional2"', 'address_postcode as "addressPostcode"',
                'address_town as "addressTown"', 'address_country as "addressCountry"', 'enabled', 'sector', 'count(1) OVER()'
            ],
            'where'     => $requestData['where'] ?? null,
            'data'      => $requestData['data'] ?? null,
            'orderBy'   => $orderBy,
            'offset'    => $queryParams['offset'],
            'limit'     => $queryParams['limit']
        ]);
        $count = $contacts[0]['count'] ?? 0;
        if (empty($contacts)) {
            return $response->withJson(['contacts' => $contacts, 'count' => $count]);
        }

        $contactIds   = array_column($contacts, 'id');
        $contactsUsed = ContactController::isContactUsed(['ids' => $contactIds]);

        foreach ($contacts as $key => $contact) {
            unset($contacts[$key]['count']);
            $filling = ContactController::getFillingRate(['contactId' => $contact['id']]);

            $contacts[$key]['isUsed'] = $contactsUsed[$contact['id']];

            $contacts[$key]['filling'] = $filling;
        }
        if ($queryParams['orderBy'] == 'filling') {
            usort($contacts, function ($a, $b) {
                return $a['filling']['rate'] <=> $b['filling']['rate'];
            });
            if ($queryParams['order'] == 'desc') {
                $contacts = array_reverse($contacts);
            }
        }

        return $response->withJson(['contacts' => $contacts, 'count' => $count]);
    }

    public function create(Request $request, Response $response)
    {
        if (!PrivilegeController::hasPrivilege(['privilegeId' => 'create_contacts', 'userId' => $GLOBALS['id']])
            && !PrivilegeController::hasPrivilege(['privilegeId' => 'admin_contacts', 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }

        $body = $request->getParsedBody();

        $control = ContactController::controlContact(['body' => $body]);
        if (!empty($control['errors'])) {
            return $response->withStatus(400)->withJson(['errors' => $control['errors']]);
        }

        $currentUser = UserModel::getById(['id' => $GLOBALS['id'], 'select' => ['mode']]);
        if (!empty($body['email']) && $currentUser['mode'] == 'rest') {
            $contact = ContactModel::get(['select' => ['id'], 'where' => ['email = ?'], 'data' => [$body['email']]]);
            if (!empty($contact[0]['id'])) {
                return $response->withJson(['id' => $contact[0]['id']]);
            }
        }

        if (!empty($body['communicationMeans'])) {
            if (!empty($body['communicationMeans']['email']) && filter_var($body['communicationMeans']['email'], FILTER_VALIDATE_EMAIL)) {
                $contactBody['email'] = $body['communicationMeans']['email'];
            } elseif (!empty($body['communicationMeans']['url']) && filter_var($body['communicationMeans']['url'], FILTER_VALIDATE_URL)) {
                $contactBody['url'] = $body['communicationMeans']['url'];
            } else {
                return $response->withStatus(400)->withJson(['errors' => _COMMUNICATION_MEANS_VALIDATOR]);
            }
            if (!empty($body['communicationMeans']['login'])) {
                $contactBody['login'] = $body['communicationMeans']['login'];
            }
            if (!empty($body['communicationMeans']['password'])) {
                $contactBody['password'] = PasswordModel::encrypt(['password' => $body['communicationMeans']['password']]);                
            }
        }

        $annuaryReturn = ContactController::addContactToM2MAnnuary(['body' => $body]);
        $body = $annuaryReturn['body'];

        if (!empty($body['externalId']) && is_array($body['externalId'])) {
            $externalId = json_encode($body['externalId']);
        } else {
            $externalId = '{}';
        }

        $sector = ContactController::getAddressSector($body);

        $id = ContactModel::create([
            'civility'              => $body['civility'] ?? null,
            'firstname'             => $body['firstname'] ?? null,
            'lastname'              => $body['lastname'] ?? null,
            'company'               => $body['company'] ?? null,
            'department'            => $body['department'] ?? null,
            'function'              => $body['function'] ?? null,
            'address_number'        => $body['addressNumber'] ?? null,
            'address_street'        => $body['addressStreet'] ?? null,
            'address_additional1'   => $body['addressAdditional1'] ?? null,
            'address_additional2'   => $body['addressAdditional2'] ?? null,
            'address_postcode'      => $body['addressPostcode'] ?? null,
            'address_town'          => $body['addressTown'] ?? null,
            'address_country'       => $body['addressCountry'] ?? null,
            'email'                 => $body['email'] ?? null,
            'phone'                 => $body['phone'] ?? null,
            'communication_means'   => !empty($contactBody) ? json_encode($contactBody) : null,
            'notes'                 => $body['notes'] ?? null,
            'creator'               => $GLOBALS['id'],
            'enabled'               => 'true',
            'custom_fields'         => !empty($body['customFields']) ? json_encode($body['customFields']) : '{}',
            'external_id'           => $externalId,
            'sector'                => $sector['label'] ?? null
        ]);

        $historyInfoContact = '';
        if (!empty($body['firstname']) || !empty($body['lastname'])) {
            $historyInfoContact .= $body['firstname'] . ' ' . $body['lastname'];
        }
        if (!empty($historyInfoContact) && !empty($body['company'])) {
            $historyInfoContact .= ' (' . $body['company'] . ')';
        } else if (!empty($body['company'])) {
            $historyInfoContact .= $body['company'];
        }

        HistoryController::add([
            'tableName' => 'contacts',
            'recordId'  => $id,
            'eventType' => 'ADD',
            'info'      => _CONTACT_CREATION . " : " . trim($historyInfoContact),
            'moduleId'  => 'contact',
            'eventId'   => 'contactCreation',
        ]);

        return $response->withJson(['id' => $id, 'warning' => $annuaryReturn['warning']]);
    }

    public function getById(Request $request, Response $response, array $args)
    {
        if (!Validator::intVal()->validate($args['id'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Route id is not an integer']);
        }

        $rawContact = ContactModel::getById(['id' => $args['id'], 'select' => ['*']]);
        if (empty($rawContact)) {
            return $response->withStatus(400)->withJson(['errors' => 'Contact does not exist']);
        }

        $queryParams = $request->getQueryParams();

        $contact = [
            'id'                    => $rawContact['id'],
            'civility'              => null,
            'firstname'             => $rawContact['firstname'],
            'lastname'              => $rawContact['lastname'],
            'company'               => $rawContact['company'],
            'department'            => $rawContact['department'],
            'function'              => $rawContact['function'],
            'addressNumber'         => $rawContact['address_number'],
            'addressStreet'         => $rawContact['address_street'],
            'addressAdditional1'    => $rawContact['address_additional1'],
            'addressAdditional2'    => $rawContact['address_additional2'],
            'addressPostcode'       => $rawContact['address_postcode'],
            'addressTown'           => $rawContact['address_town'],
            'addressCountry'        => $rawContact['address_country'],
            'email'                 => $rawContact['email'],
            'phone'                 => $rawContact['phone'],
            'communicationMeans'    => null,
            'notes'                 => $rawContact['notes'],
            'creator'               => $rawContact['creator'],
            'creatorLabel'          => UserModel::getLabelledUserById(['id' => $rawContact['creator']]),
            'enabled'               => $rawContact['enabled'],
            'creationDate'          => $rawContact['creation_date'],
            'modificationDate'      => $rawContact['modification_date'],
            'customFields'          => !empty($rawContact['custom_fields']) ? json_decode($rawContact['custom_fields'], true) : null,
            'externalId'            => json_decode($rawContact['external_id'], true),
            'sector'                => $rawContact['sector']
        ];

        if (!empty($rawContact['civility'])) {
            $civilities = ContactCivilityModel::get(['select' => ['*']]);
            $civilities = array_column($civilities, null, 'id');

            $contact['civility'] = [
                'id'           => $rawContact['civility'],
                'label'        => $civilities[$rawContact['civility']]['label'] ?? null,
                'abbreviation' => $civilities[$rawContact['civility']]['abbreviation'] ?? null
            ];
        }
        if (!empty($rawContact['communication_means'])) {
            $communicationMeans = json_decode($rawContact['communication_means'], true);
            if(!empty($communicationMeans['url'])) {
                $contact['communicationMeans']['url'] = $communicationMeans['url'];
            } elseif (!empty($communicationMeans['email'])) {
                $contact['communicationMeans']['email'] = $communicationMeans['email']; 
            }
            $contact['communicationMeans']['login'] = $communicationMeans['login'] ?? null;
        }

        $filling = ContactController::getFillingRate(['contactId' => $rawContact['id']]);
        $contact['fillingRate'] = empty($filling) ? null : $filling;

        if (!empty($queryParams['resourcesCount'])) {
            $inResources = ResourceContactModel::get([
                'select' => ['item_id'],
                'where'  => ['item_id = ?', 'type = ?'],
                'data'   => [$args['id'], 'contact']
            ]);

            $inAcknowledgementReceipts = AcknowledgementReceiptModel::get([
                'select' => ['contact_id'],
                'where'  => ['contact_id = ?'],
                'data'   => [$args['id']]
            ]);

            $inAttachments = AttachmentModel::get([
                'select' => ['recipient_id'],
                'where'  => ['recipient_id = ?', 'recipient_type = ?'],
                'data'   => [$args['id'], 'contact']
            ]);

            $contact['resourcesCount'] = count($inResources) + count($inAcknowledgementReceipts) + count($inAttachments);
        }

        return $response->withJson($contact);
    }

    public function update(Request $request, Response $response, array $args)
    {
        if (!PrivilegeController::hasPrivilege(['privilegeId' => 'update_contacts', 'userId' => $GLOBALS['id']])
            && !PrivilegeController::hasPrivilege(['privilegeId' => 'admin_contacts', 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }

        if (!Validator::intVal()->validate($args['id'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Route id is not an integer']);
        }

        $body = $request->getParsedBody();

        $control = ContactController::controlContact(['body' => $body]);
        if (!empty($control['errors'])) {
            return $response->withStatus(400)->withJson(['errors' => $control['errors']]);
        }

        $contact = ContactModel::getById(['id' => $args['id'], 'select' => ['communication_means']]);
        if (empty($contact)) {
            return $response->withStatus(400)->withJson(['errors' => 'Contact does not exist']);
        }

        $contact['communication_means'] = json_decode($contact['communication_means'] ?? '{}', true);
        $contactBody = [];
        if (!empty($contact['communication_means']['password'])) {
            $contactBody['password'] = $contact['communication_means']['password'];
        }
        if (!empty($body['communicationMeans'])) {
            if (filter_var($body['communicationMeans']['email'], FILTER_VALIDATE_EMAIL)) {
                $contactBody['email'] = $body['communicationMeans']['email'];
            } elseif (filter_var($body['communicationMeans']['url'], FILTER_VALIDATE_URL)) {
                $contactBody['url'] = $body['communicationMeans']['url'];
            } else {
                unset($contactBody);
            }
            if (!empty($body['communicationMeans']['login'])) {
                $contactBody['login'] = $body['communicationMeans']['login'];
            }
            if (!empty($body['communicationMeans']['password'])) {
                $contactBody['password'] = PasswordModel::encrypt(['password' => $body['communicationMeans']['password']]);                
            }
        }

        $annuaryReturn = ContactController::addContactToM2MAnnuary(['body' => $body]);
        $body = $annuaryReturn['body'];

        if (!empty($body['externalId']) && is_array($body['externalId'])) {
            $externalId = json_encode($body['externalId']);
        } else {
            $externalId = '{}';
        }

        $sector = ContactController::getAddressSector($body);

        ContactModel::update([
            'set'   => [
                    'civility'              => $body['civility'] ?? null,
                    'firstname'             => $body['firstname'] ?? null,
                    'lastname'              => $body['lastname'] ?? null,
                    'company'               => $body['company'] ?? null,
                    'department'            => $body['department'] ?? null,
                    'function'              => $body['function'] ?? null,
                    'address_number'        => $body['addressNumber'] ?? null,
                    'address_street'        => $body['addressStreet'] ?? null,
                    'address_additional1'   => $body['addressAdditional1'] ?? null,
                    'address_additional2'   => $body['addressAdditional2'] ?? null,
                    'address_postcode'      => $body['addressPostcode'] ?? null,
                    'address_town'          => $body['addressTown'] ?? null,
                    'address_country'       => $body['addressCountry'] ?? null,
                    'email'                 => $body['email'] ?? null,
                    'phone'                 => $body['phone'] ?? null,
                    'communication_means'   => !empty($contactBody) ? json_encode($contactBody) : null,
                    'notes'                 => $body['notes'] ?? null,
                    'modification_date'     => 'CURRENT_TIMESTAMP',
                    'custom_fields'         => !empty($body['customFields']) ? json_encode($body['customFields']) : null,
                    'external_id'           => $externalId,
                    'sector'                => $sector['label'] ?? null
                ],
            'where' => ['id = ?'],
            'data'  => [$args['id']]
        ]);

        $historyInfoContact = '';
        if (!empty($body['firstname']) || !empty($body['lastname'])) {
            $historyInfoContact .= $body['firstname'] . ' ' . $body['lastname'];
        }
        if (!empty($historyInfoContact) && !empty($body['company'])) {
            $historyInfoContact .= ' (' . $body['company'] . ')';
        } else {
            $historyInfoContact .= $body['company'];
        }

        HistoryController::add([
            'tableName' => 'contacts',
            'recordId'  => $args['id'],
            'eventType' => 'UP',
            'info'      => _CONTACT_MODIFICATION . " : " . trim($historyInfoContact),
            'moduleId'  => 'contact',
            'eventId'   => 'contactModification',
        ]);

        if (!empty($annuaryReturn['warning'])) {
            return $response->withJson(['warning' => $annuaryReturn['warning']]);
        }

        return $response->withStatus(204);
    }

    public function addContactToM2MAnnuary($args = [])
    {
        $warning = '';
        $body = $args['body'];
        if (!empty($body['externalId']['m2m']) && !empty($body['company']) && empty($body['externalId']['m2m_annuary_id'])) {
            if (empty($body['company']) || (empty($body['communicationMeans']['email']) && empty($body['communicationMeans']['url'])) || empty($body['department'])) {
                $control = AnnuaryController::getAnnuaries();
                if (!empty($control['annuaries'])) {
                    $warning = _CANNOT_SYNCHRONIZE_M2M_ANNUARY;
                }
            } else {
                $annuaryInfo = AnnuaryController::addContact([
                    'ouName'             => $body['company'],
                    'communicationValue' => $body['communicationMeans']['email'] ?? $body['communicationMeans']['url'],
                    'serviceName'        => $body['department'],
                    'm2mId'              => $body['externalId']['m2m']
                ]);
                if (!empty($annuaryInfo['errors'])) {
                    $warning = $annuaryInfo['errors'];
                } else {
                    $body['externalId']['m2m_annuary_id'] = $annuaryInfo['entryUUID'];
                }
            }
        }

        return ['body' => $body, 'warning' => $warning];
    }

    public function updateActivation(Request $request, Response $response, array $args)
    {
        if (!PrivilegeController::hasPrivilege(['privilegeId' => 'admin_contacts', 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }

        if (!Validator::intVal()->validate($args['id'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Route id is not an integer']);
        }

        $contact = ContactModel::getById(['id' => $args['id'], 'select' => [1]]);
        if (empty($contact)) {
            return $response->withStatus(400)->withJson(['errors' => 'Contact does not exist']);
        }

        $body = $request->getParsedBody();

        ContactModel::update([
            'set'   => ['enabled' => empty($body['enabled']) ? 'false' : 'true'],
            'where' => ['id = ?'],
            'data'  => [$args['id']]
        ]);

        return $response->withStatus(204);
    }

    public function delete(Request $request, Response $response, array $args)
    {
        if (!PrivilegeController::hasPrivilege(['privilegeId' => 'admin_contacts', 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }

        if (!Validator::intVal()->validate($args['id'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Route id is not an integer']);
        }

        $contact = ContactModel::getById(['id' => $args['id'], 'select' => ['lastname', 'firstname', 'company']]);
        if (empty($contact)) {
            return $response->withStatus(400)->withJson(['errors' => 'Contact does not exist']);
        }

        $queryParams = $request->getQueryParams();

        if (!empty($queryParams['redirect'])) {
            if (!Validator::intVal()->validate($queryParams['redirect'])) {
                return $response->withStatus(400)->withJson(['errors' => 'Query param redirect is not an integer']);
            } elseif ($queryParams['redirect'] == $args['id']) {
                return $response->withStatus(400)->withJson(['errors' => 'Cannot redirect to contact you are deleting']);
            }

            $contactRedirect = ContactModel::getById(['id' => $queryParams['redirect'], 'select' => [1]]);
            if (empty($contactRedirect)) {
                return $response->withStatus(400)->withJson(['errors' => 'Contact does not exist']);
            }

            $resourcesContacts = ResourceContactModel::get([
                'select' => ['res_id', 'mode'],
                'where'  => ['item_id = ?', "type = 'contact'"],
                'data'   => [$args['id']]
            ]);

            ResourceContactModel::update([
                'set'   => ['item_id' => $queryParams['redirect']],
                'where' => ['item_id = ?', 'type = ?'],
                'data'  => [$args['id'], 'contact']
            ]);

            // Delete duplicates if needed
            $toDelete = [];
            foreach ($resourcesContacts as $resourcesContact) {
                $resContact = ResourceContactModel::get([
                    'select'  => ['id'],
                    'where'   => ['res_id = ?', 'item_id = ?', 'mode = ?', "type = 'contact'"],
                    'data'    => [$resourcesContact['res_id'], $queryParams['redirect'], $resourcesContact['mode']],
                    'orderBy' => ['id desc']
                ]);
                if (count($resContact) > 1) {
                    $toDelete[] = $resContact[0]['id'];
                }
            }
            if (!empty($toDelete)) {
                ResourceContactModel::delete([
                    'where' => ['id in (?)'],
                    'data' => [$toDelete]
                ]);
            }

            AcknowledgementReceiptModel::update([
                'set'   => ['contact_id' => $queryParams['redirect']],
                'where' => ['contact_id = ?'],
                'data'  => [$args['id']]
            ]);

            AttachmentModel::update([
                'set'   => ['recipient_id' => $queryParams['redirect']],
                'where' => ['recipient_id = ?', "recipient_type = 'contact'"],
                'data'  => [$args['id']]
            ]);
        }

        AttachmentModel::update([
            'set'   => ['recipient_id' => null, 'recipient_type' => null],
            'where' => ['recipient_id = ?', "recipient_type = 'contact'"],
            'data'  => [$args['id']]
        ]);

        ResourceContactModel::delete([
            'where' => ['item_id = ?', "type = 'contact'"],
            'data'  => [$args['id']]
        ]);

        ContactModel::delete([
            'where' => ['id = ?'],
            'data'  => [$args['id']]
        ]);

        ContactGroupListModel::delete(['where' => ['correspondent_id = ?', 'correspondent_type = ?'], 'data' => [$args['id'], 'contact']]);

        $historyInfoContact = '';
        if (!empty($contact['firstname']) || !empty($contact['lastname'])) {
            $historyInfoContact .= $contact['firstname'] . ' ' . $contact['lastname'];
        }
        if (!empty($historyInfoContact) && !empty($contact['company'])) {
            $historyInfoContact .= ' (' . $contact['company'] . ')';
        } else {
            $historyInfoContact .= $contact['company'];
        }

        HistoryController::add([
            'tableName' => 'contacts',
            'recordId'  => $args['id'],
            'eventType' => 'DEL',
            'info'      => _CONTACT_SUPPRESSION . " : " . trim($historyInfoContact),
            'moduleId'  => 'contact',
            'eventId'   => 'contactSuppression',
        ]);

        return $response->withStatus(204);
    }

    public function getContactsParameters(Request $request, Response $response)
    {
        $contactsFilling = ContactFillingModel::get();
        $contactParameters = ContactParameterModel::get([
            'select' => ['*'],
            'orderBy' => ['identifier=\'civility\' desc, identifier=\'firstname\' desc, identifier=\'lastname\' desc,identifier=\'function\' desc, identifier=\'company\' desc, identifier=\'department\' desc,
            identifier=\'email\' desc,  identifier=\'phone\' desc, identifier=\'addressAdditional1\' desc, identifier=\'addressNumber\' desc, identifier=\'addressStreet\' desc,
            identifier=\'addressAdditional2\' desc, identifier=\'addressPostcode\' desc, identifier=\'addressTown\' desc, identifier=\'addressCountry\' desc, identifier=\'notes\' desc']
        ]);
        foreach ($contactParameters as $key => $parameter) {
            if (strpos($parameter['identifier'], 'contactCustomField_') !== false) {
                $contactCustomId = str_replace("contactCustomField_", "", $parameter['identifier']);
                $customField = ContactCustomFieldListModel::getById(['select' => ['label'], 'id' => $contactCustomId]);
                $contactParameters[$key]['label'] = $customField['label'] ?? null;
            } else {
                $contactParameters[$key]['label'] = null;
            }
        }

        $loadedXml = CoreConfigModel::getXmlLoaded(['path' => 'config/m2m_config.xml']);

        $annuaryEnabled = true;
        if (!$loadedXml) {
            $annuaryEnabled = false;
        }
        if (empty($loadedXml->annuaries) || $loadedXml->annuaries->enabled == 'false') {
            $annuaryEnabled = false;
        }

        return $response->withJson(['contactsFilling' => $contactsFilling, 'contactsParameters' => $contactParameters, 'annuaryEnabled' => $annuaryEnabled]);
    }

    public function updateContactsParameters(Request $request, Response $response)
    {
        if (!PrivilegeController::hasPrivilege(['privilegeId' => 'admin_contacts', 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }

        $data = $request->getParsedBody();

        $check = Validator::arrayType()->validate($data['contactsParameters']);
        $check = $check && Validator::arrayType()->validate($data['contactsFilling']);
        $check = $check && Validator::boolType()->validate($data['contactsFilling']['enable']);
        $check = $check && Validator::notEmpty()->intVal()->validate($data['contactsFilling']['first_threshold']) && $data['contactsFilling']['first_threshold'] > 0 && $data['contactsFilling']['first_threshold'] < 99;
        $check = $check && Validator::notEmpty()->intVal()->validate($data['contactsFilling']['second_threshold']) && $data['contactsFilling']['second_threshold'] > 1 && $data['contactsFilling']['second_threshold'] < 100;
        $check = $check && $data['contactsFilling']['first_threshold'] < $data['contactsFilling']['second_threshold'];
        if (!$check) {
            return $response->withStatus(400)->withJson(['errors' => 'Bad Request']);
        }

        foreach ($data['contactsParameters'] as $key => $contactParameter) {

            if ($key == "label") {
                unset($contactParameter['label']);
            }
            ContactParameterModel::update([
                'set'   => [
                    'mandatory'   => empty($contactParameter['mandatory']) ? 'false' : 'true',
                    'filling'     => empty($contactParameter['filling']) ? 'false' : 'true',
                    'searchable'  => empty($contactParameter['searchable']) ? 'false' : 'true',
                    'displayable' => empty($contactParameter['displayable']) ? 'false' : 'true',
                ],
                'where' => ['id = ?'],
                'data'  => [$contactParameter['id']]
            ]);
        }

        ContactFillingModel::update($data['contactsFilling']);

        return $response->withJson(['success' => 'success']);
    }

    public function getByResId(Request $request, Response $response, array $args)
    {
        if (!Validator::intVal()->validate($args['resId']) || !ResController::hasRightByResId(['resId' => [$args['resId']], 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Document out of perimeter']);
        }

        $resource = ResModel::getById(['select' => ['res_id'], 'resId' => $args['resId']]);

        if (empty($resource)) {
            return $response->withStatus(404)->withJson(['errors' => 'Document does not exist']);
        }

        $queryParams = $request->getQueryParams();

        $contacts = [];
        if ($queryParams['type'] == 'senders') {
            $contacts = ContactController::getParsedContacts(['resId' => $resource['res_id'], 'mode' => 'sender']);
        } elseif ($queryParams['type'] == 'recipients') {
            $contacts = ContactController::getParsedContacts(['resId' => $resource['res_id'], 'mode' => 'recipient']);
        }

        return $response->withJson(['contacts' => $contacts]);
    }

    public function getLightFormattedContact(Request $request, Response $response, array $args)
    {
        if (!Validator::notEmpty()->intVal()->validate($args['id'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Query params id is not an integer']);
        }

        if ($args['type'] == 'contact') {
            $contact = ContactModel::getById([
                'select'    => [
                    'firstname', 'lastname', 'company', 'address_number as "addressNumber"', 'address_street as "addressStreet"',
                    'address_postcode as "addressPostcode"', 'address_town as "addressTown"', 'address_country as "addressCountry"'],
                'id'        => $args['id']
            ]);
        } elseif ($args['type'] == 'user') {
            $contact = UserModel::getById(['id' => $args['id'], 'select' => ['firstname', 'lastname']]);
        } elseif ($args['type'] == 'entity') {
            $contact = EntityModel::getById(['id' => $args['id'], 'select' => ['entity_label as label']]);
        }

        if (empty($contact)) {
            return $response->withStatus(400)->withJson(['errors' => 'Contact does not exist']);
        }

        return $response->withJson(['contact' => $contact]);
    }

    public static function getFillingRate(array $aArgs)
    {
        ValidatorModel::notEmpty($aArgs, ['contactId']);
        ValidatorModel::intVal($aArgs, ['contactId']);

        $contactsFilling = ContactFillingModel::get();
        $contactsParameters = ContactParameterModel::get(['select' => ['identifier'], 'where' => ['filling = ?'], 'data' => ['true']]);

        if ($contactsFilling['enable'] && !empty($contactsParameters)) {
            $contactRaw = ContactModel::getById([
                'select'    => [
                    'civility', 'firstname', 'lastname', 'company', 'department', 'function', 'address_number as "addressNumber"', 'address_street as "addressStreet"',
                    'address_additional1 as "addressAdditional1"', 'address_additional2 as "addressAdditional2"', 'address_postcode as "addressPostcode"',
                    'address_town as "addressTown"', 'address_country as "addressCountry"', 'email', 'phone', 'notes', 'custom_fields'
                ],
                'id'        => $aArgs['contactId']
            ]);
            $customFields = json_decode($contactRaw['custom_fields'] ?? '{}', true);

            $percent = 0;
            foreach ($contactsParameters as $ratingColumn) {
                if (strpos($ratingColumn['identifier'], 'contactCustomField_') !== false && !empty($customFields[str_replace("contactCustomField_", "", $ratingColumn['identifier'])])) {
                    $percent++;
                } elseif (!empty($contactRaw[$ratingColumn['identifier']])) {
                    $percent++;
                }
            }
            $percent = $percent * 100 / count($contactsParameters);
            if ($percent <= $contactsFilling['first_threshold']) {
                $thresholdLevel = 'first';
            } elseif ($percent <= $contactsFilling['second_threshold']) {
                $thresholdLevel = 'second';
            } else {
                $thresholdLevel = 'third';
            }

            return ['rate' => round($percent, 2), 'thresholdLevel' => $thresholdLevel];
        }

        return [];
    }

    public static function getContactAfnor(array $args)
    {
        $afnorAddress = [
            'Afnor',
            '',
            '',
            '',
            '',
            '',
            '',
            ''
        ];

        if (!empty($args['company'])) {
            // Ligne 1
            $afnorAddress[1] = trim(substr($args['company'], 0, 38));
        }

        // Ligne 2
        if (!empty($args['civility']) || !empty($args['firstname']) || !empty($args['lastname'])) {
            $afnorAddress[2] = ContactController::controlLengthNameAfnor([
                'civility'      => $args['civility'],
                'fullName'      => $args['firstname'].' '.$args['lastname'],
                'strMaxLength'  => 38
            ]);
            $afnorAddress[2] = trim($afnorAddress[2]);
        }

        // Ligne 3
        if (!empty($args['address_additional1'])) {
            $afnorAddress[3] = trim(substr($args['address_additional1'], 0, 38));
        }

        // Ligne 4
        if (!empty($args['address_number'])) {
            $args['address_number'] = TextFormatModel::normalize(['string' => $args['address_number']]);
            $args['address_number'] = preg_replace('/[^\w]/s', ' ', $args['address_number']);
            $args['address_number'] = strtoupper($args['address_number']);
        }
        if (!empty($args['address_street'])) {
            $args['address_street'] = TextFormatModel::normalize(['string' => $args['address_street']]);
            $args['address_street'] = preg_replace('/[^\w]/s', ' ', $args['address_street']);
            $args['address_street'] = strtoupper($args['address_street']);
        }
        $afnorAddress[4] = trim(substr($args['address_number'].' '.$args['address_street'], 0, 38));

        // Ligne 5
        if (!empty($args['address_additional2'])) {
            $afnorAddress[5] = trim(substr($args['address_additional2'], 0, 38));
        }

        // Ligne 6
        $args['address_postcode'] = strtoupper($args['address_postcode'] ?? '');
        $args['address_town'] = strtoupper($args['address_town'] ?? '');
        $afnorAddress[6] = trim(substr($args['address_postcode'].' '.$args['address_town'], 0, 38));

        // Ligne 7
        if (!empty($args['address_country'])) {
            $afnorAddress[7] = trim(substr($args['address_country'], 0, 38));
        }

        return $afnorAddress;
    }

    public static function controlLengthNameAfnor(array $args)
    {
        $civilities = ContactCivilityModel::get(['select' => ['*']]);
        $civilities = array_column($civilities, null, 'id');

        if (strlen(($civilities[$args['civility']]['label'] ?? '').' '.$args['fullName']) > $args['strMaxLength']) {
            $args['civility'] = $civilities[$args['civility']]['abbreviation'];
        } else {
            $args['civility'] = $civilities[$args['civility']]['label'] ?? null;
        }

        return substr($args['civility'].' '.$args['fullName'], 0, $args['strMaxLength']);
    }

    public function getAvailableDepartments(Request $request, Response $response)
    {
        $customId = CoreConfigModel::getCustomId();

        $referentialDirectory = 'referential/ban/indexes';
        if (is_dir("custom/{$customId}/".$referentialDirectory)) {
            $customFilesDepartments = scandir("custom/{$customId}/".$referentialDirectory);
        }
        if (is_dir($referentialDirectory)) {
            $filesDepartments = scandir($referentialDirectory);
        }

        $departments = [];
        if (!empty($customFilesDepartments)) {
            foreach ($customFilesDepartments as $value) {
                if ($value != '.' && $value != '..' && is_writable("custom/{$customId}/".$referentialDirectory.'/'.$value)) {
                    $departments[] = $value;
                }
            }
        }
        if (!empty($filesDepartments)) {
            foreach ($filesDepartments as $value) {
                if ($value != '.' && $value != '..' && !in_array($value, $departments) && is_writable($referentialDirectory.'/'.$value)) {
                    $departments[] = $value;
                }
            }
        }

        if (empty($departments)) {
            return $response->withJson(['departments' => []]);
        }

        sort($departments, SORT_NUMERIC);

        $defaultDepartment = ParameterModel::getById(['id' => 'defaultDepartment', 'select' => ['param_value_string']]);

        return $response->withJson(['departments' => $departments, 'default' => empty($defaultDepartment['param_value_string']) ? null : $defaultDepartment['param_value_string']]);
    }

    public function getDuplicatedContacts(Request $request, Response $response)
    {
        if (!PrivilegeController::hasPrivilege(['privilegeId' => 'admin_contacts', 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }

        $queryParams = $request->getQueryParams();

        // [fieldNameInFront] => field_name_in_db
        $allowedFields = [
            'civility'           => 'civility',
            'firstname'          => 'firstname',
            'lastname'           => 'lastname',
            'company'            => 'company',
            'addressNumber'      => 'address_number',
            'addressStreet'      => 'address_street',
            'addressAdditional1' => 'address_additional1',
            'addressAdditional2' => 'address_additional2',
            'addressPostcode'    => 'address_postcode',
            'addressTown'        => 'address_town',
            'addressCountry'     => 'address_country',
            'department'         => 'department',
            'function'           => 'function',
            'email'              => 'email',
            'phone'              => 'phone',
            'notes'              => 'notes'
        ];

        if (!Validator::arrayType()->notEmpty()->validate($queryParams['criteria'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Query criteria is empty or not an array']);
        }

        $contactCustoms = ContactCustomFieldListModel::get(['select' => ['id']]);
        $contactCustoms = array_column($contactCustoms, 'id');

        $allowedFieldsKeys = array_keys($allowedFields);
        foreach ($queryParams['criteria'] as $criterion) {
            if (strpos($criterion, 'contactCustomField_') !== false) {
                $customId = explode('_', $criterion)[1];
                if (!in_array($customId, $contactCustoms)) {
                    return $response->withStatus(400)->withJson(['errors' => 'Custom criteria does not exist']);
                }
            } else {
                if (!in_array($criterion, $allowedFieldsKeys)) {
                    return $response->withStatus(400)->withJson(['errors' => 'criteria does not exist']);
                }
            }
        }

        // Construct the query to get all duplicates on criteria
        $criteria = [];
        $order = [];
        foreach ($queryParams['criteria'] as $criterion) {
            if (strpos($criterion, 'contactCustomField_') !== false) {
                if (!in_array('custom_fields', $order)) {
                    $order[] = 'custom_fields';
                }
                $customId = explode('_', $criterion)[1];
                $criteria[] = "replace(lower(unaccent(custom_fields->>'" . $customId . "') ), ' ', '')";
            } else {
                $order[] = $allowedFields[$criterion];
                $criteria[] = "replace(lower(unaccent(" . $allowedFields[$criterion] . ") ), ' ', '')";
            }
        }

        $fields = ['distinct(id)', 'enabled', 'dense_rank() over (order by ' . implode(',', $criteria) . ') duplicate_id', 'custom_fields'];
        foreach ($allowedFields as $field) {
            $fields[] = $field;
        }

        $where = [];

        foreach ($criteria as $criterion) {
            $subQuery = "SELECT " . $criterion . ' as field FROM contacts c GROUP BY field HAVING count(*) > 1';

            $where[] = $criterion . " in (" . $subQuery . ") AND " . $criterion . " != '' AND " . $criterion . " is not null";
        }

        $duplicatesQuery = "SELECT " . implode(', ', $fields) . ' FROM contacts WHERE ' . implode(' AND ', $where);

        // Create a query that will have the number of duplicates for each duplicate group
        // this is needed to avoid getting result that only appears once in the result list (and the function dense_rank cannot be used in group by)
        $duplicatesCountQuery = 'SELECT duplicate_id, count(*) as duplicate_count FROM (' . $duplicatesQuery . ') as duplicates_id group by duplicate_id';

        $fields = ['distinct(id)', 'count(*) over () as total', 'duplicates_info.duplicate_id', 'enabled', 'custom_fields'];
        foreach ($allowedFields as $field) {
            $fields[] = $field;
        }

        // Get all the duplicates
        $duplicates = DatabaseModel::select([
            'select'   => $fields,
            'table'    => ['( ' . $duplicatesQuery . ') as duplicates_info, (' . $duplicatesCountQuery . ') as duplicates_ids'],
            'where'    => ['duplicates_ids.duplicate_id = duplicates_info.duplicate_id', 'duplicate_count > 1'],
            'order_by' => $order,
            'limit'    => 500
        ]);

        if (empty($duplicates)) {
            return $response->withJson(['returnedCount' => 0, 'realCount' => 0, 'contacts' => []]);
        }

        $contactIds   = array_column($duplicates, 'id');
        $contactsUsed = ContactController::isContactUsed(['ids' => $contactIds]);


        $civilities = ContactCivilityModel::get(['select' => ['*']]);
        $civilities = array_column($civilities, null, 'id');

        $contacts = [];
        foreach ($duplicates as $key => $contact) {
            unset($duplicates[$key]['count']);
            $filling = ContactController::getFillingRate(['contactId' => $contact['id']]);

            $contacts[] = [
                'duplicateId'        => $contact['duplicate_id'],
                'id'                 => $contact['id'],
                'firstname'          => $contact['firstname'],
                'lastname'           => $contact['lastname'],
                'company'            => $contact['company'],
                'addressNumber'      => $contact['address_number'],
                'addressStreet'      => $contact['address_street'],
                'addressAdditional1' => $contact['address_additional1'],
                'addressAdditional2' => $contact['address_additional2'],
                'addressPostcode'    => $contact['address_postcode'],
                'addressTown'        => $contact['address_town'],
                'addressCountry'     => $contact['address_country'],
                'enabled'            => $contact['enabled'],
                'function'           => $contact['function'],
                'department'         => $contact['department'],
                'email'              => $contact['email'],
                'phone'              => $contact['phone'],
                'notes'              => $contact['notes'],
                'isUsed'             => $contactsUsed[$contact['id']],
                'filling'            => $filling,
                'customFields'       => !empty($contact['custom_fields']) ? json_decode($contact['custom_fields'], true) : null,
                'civility'           => !empty($contact['civility']) ? $civilities[$contact['civility']]['label'] : null
            ];
        }
        $count = $duplicates[0]['total'];

        return $response->withJson(['returnedCount' => count($contacts), 'realCount' => $count, 'contacts' => $contacts]);
    }

    public function mergeContacts(Request $request, Response $response, array $args)
    {
        if (!PrivilegeController::hasPrivilege(['privilegeId' => 'admin_contacts', 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }

        if (!Validator::intVal()->validate($args['id'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Route id is not an integer']);
        }

        $body = $request->getParsedBody();
        if (!Validator::arrayType()->notEmpty()->validate($body['duplicates'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body duplicates is empty or not an array']);
        }

        $fields = ['civility', 'firstname', 'lastname', 'company', 'address_number', 'address_street', 'address_additional1', 'address_additional2',
                   'address_postcode', 'address_town', 'address_country', 'department', 'function', 'email', 'phone', 'custom_fields', 'external_id', 'notes'];

        $master = ContactModel::getById([
            'select' => $fields,
            'id'     => $args['id']
        ]);

        if (empty($master)) {
            return $response->withStatus(400)->withJson(['errors' => 'master does not exist']);
        }

        $duplicates = ContactModel::get([
            'select' => $fields,
            'where'  => ['id in (?)'],
            'data'   => [$body['duplicates']]
        ]);

        if (count($duplicates) != count($body['duplicates'])) {
            return $response->withStatus(400)->withJson(['errors' => 'duplicates do not exist']);
        }

        $set = [];
        foreach ($fields as $field) {
            if (($field == 'custom_fields' || $field == 'external_id') && !empty($master[$field])) {
                $master[$field] = json_decode($master[$field], true);
                $masterCustomsKeys = array_keys($master[$field]);
                $set[$field] = $master[$field];

                foreach ($duplicates as $duplicate) {
                    $duplicateCustoms = json_decode($duplicate[$field], true);
                    foreach ($duplicateCustoms as $key => $duplicateCustom) {
                        if (!in_array($key, $masterCustomsKeys)) {
                            $set[$field][$key] = $duplicateCustom;
                        }
                    }
                }
                $set[$field] = json_encode($set[$field]);
            } elseif (empty($master[$field])) {
                foreach ($duplicates as $duplicate) {
                    if (!empty($duplicate[$field])) {
                        $set[$field] = $duplicate[$field];
                        break;
                    }
                }
            }
        }

        if (!empty($set)) {
            ContactModel::update([
                'set'   => $set,
                'where' => ['id = ?'],
                'data'  => [$args['id']]
            ]);
        }

        ResourceContactModel::update([
            'set'   => ['item_id' => $args['id']],
            'where' => ['item_id in (?)', "type = 'contact'"],
            'data'  => [$body['duplicates']]
        ]);

        AcknowledgementReceiptModel::update([
            'set'   => ['contact_id' => $args['id']],
            'where' => ['contact_id in (?)'],
            'data'  => [$body['duplicates']]
        ]);

        AttachmentModel::update([
            'set'   => ['recipient_id' => $args['id']],
            'where' => ['recipient_id in (?)', "recipient_type = ?"],
            'data'  => [$body['duplicates'], 'contact']
        ]);

        $contactsgroupsList = ContactGroupListModel::get(['select' => ['contacts_groups_id'], 'where' => ['correspondent_id in (?)', 'correspondent_type = ?'], 'data' => [$body['duplicates'], 'contact']]);
        ContactGroupListModel::delete(['where' => ['correspondent_id in (?)', 'correspondent_type = ?'], 'data' => [$body['duplicates'], 'contact']]);

        $contactsgroupsWhereContactIs = ContactGroupListModel::get(['select' => ['contacts_groups_id'], 'where' => ['correspondent_id = ?', 'correspondent_type = ?'], 'data' => [$args['id'], 'contact']]);
        $contactsgroupsWhereContactIs = array_column($contactsgroupsWhereContactIs, 'contacts_groups_id');

        foreach ($contactsgroupsList as $value) {
            if (!in_array($value['contacts_groups_id'], $contactsgroupsWhereContactIs)) {
                ContactGroupListModel::create([
                    'contacts_groups_id'    => $value['contacts_groups_id'],
                    'correspondent_id'      => $args['id'],
                    'correspondent_type'    => 'contact'
                ]);
            }
        }

        ContactModel::delete([
            'where' => ['id in (?)'],
            'data'  => [$body['duplicates']]
        ]);

        return $response->withStatus(204);
    }

    public function exportContacts(Request $request, Response $response)
    {
        if (!PrivilegeController::hasPrivilege(['privilegeId' => 'admin_contacts', 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }

        $body = $request->getParsedBody();

        if (!Validator::stringType()->notEmpty()->validate($body['delimiter']) || !in_array($body['delimiter'], [',', ';', 'TAB'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Delimiter is empty or not a string between [\',\', \';\', \'TAB\']']);
        } elseif (!Validator::arrayType()->notEmpty()->validate($body['data'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Data data is empty or not an array']);
        }

        $existingFields = [
            'id'                 => 'id',
            'civility'           => 'civility',
            'firstname'          => 'firstname',
            'lastname'           => 'lastname',
            'company'            => 'company',
            'department'         => 'department',
            'function'           => 'function',
            'addressNumber'      => 'address_number',
            'addressStreet'      => 'address_street',
            'addressAdditional1' => 'address_additional1',
            'addressAdditional2' => 'address_additional2',
            'addressPostcode'    => 'address_postcode',
            'addressTown'        => 'address_town',
            'addressCountry'     => 'address_country',
            'email'              => 'email',
            'phone'              => 'phone',
            'communicationMeans' => 'communication_means',
            'notes'              => 'notes',
            'creator'            => 'creator',
            'creationDate'       => 'creation_date',
            'modificationDate'   => 'modification_date',
            'enabled'            => 'enabled',
            'customFields'       => 'custom_fields',
            'externalId'         => 'external_id',
            'sector'             => 'sector'
        ];

        $contactCustoms = ContactCustomFieldListModel::get(['select' => ['id']]);
        $contactCustoms = array_column($contactCustoms, 'id');

        $existingFieldsKeys = array_keys($existingFields);
        foreach ($body['data'] as $field) {
            if (!Validator::stringType()->notEmpty()->validate($field['value'])) {
                return $response->withStatus(400)->withJson(['errors' => 'field value is empty or not a string']);
            }
            if (!Validator::stringType()->notEmpty()->validate($field['label'])) {
                return $response->withStatus(400)->withJson(['errors' => 'field label is empty or not a string']);
            }
            if (strpos($field['value'], 'contactCustomField_') !== false) {
                $customId = explode('_', $field['value'])[1];
                if (!in_array($customId, $contactCustoms)) {
                    return $response->withStatus(400)->withJson(['errors' => 'Custom field does not exist']);
                }
            } else {
                if (!in_array($field['value'], $existingFieldsKeys) && $field['value'] != 'creatorLabel') {
                    return $response->withStatus(400)->withJson(['errors' => 'field does not exist']);
                }
            }
        }

        $fields = [];
        $csvHead = [];
        foreach ($body['data'] as $field) {
            if (strpos($field['value'], 'contactCustomField_') !== false) {
                $customId = explode('_', $field['value'])[1];
                $fields[] = "custom_fields->>'" . $customId . "' as contact_custom_field_" . $customId;
            } elseif ($field['value'] == 'creatorLabel') {
                $fields[] = "creator as creator_label";
            } else {
                $fields[] = $existingFields[$field['value']];
            }
            $csvHead[] = $field['label'];
        }

        ini_set('memory_limit', -1);

        $file = fopen('php://temp', 'w');
        $delimiter = ($body['delimiter'] == 'TAB' ? "\t" : $body['delimiter']);

        fputcsv($file, $csvHead, $delimiter);

        $contacts = ContactModel::get(['select' => $fields]);

        $civilities = ContactCivilityModel::get(['select' => ['*']]);
        $civilities = array_column($civilities, null, 'id');

        foreach ($contacts as $contact) {
            foreach ($contact as $field => $value) {
                if (strpos($field, 'contact_custom_field_') !== false) {
                    $decoded = json_decode($value, true);
                    if (is_array($decoded)) {
                        $contact[$field] = implode("\n", $decoded);
                    }
                }
                if (Validator::boolType()->validate($value)) {
                    $contact[$field] = $value ? 'TRUE' : 'FALSE';
                }
            }
            if (!empty($contact['creator_label'])) {
                $contact['creator_label'] = UserModel::getLabelledUserById(['id' => $contact['creator_label']]);
            }
            if (!empty($contact['civility'])) {
                $contact['civility'] = $civilities[$contact['civility']]['label'];
            }
            fputcsv($file, $contact, $delimiter);
        }

        rewind($file);

        $response->write(stream_get_contents($file));
        $response = $response->withAddedHeader('Content-Disposition', 'attachment; filename=export_maarch.csv');
        $contentType = 'application/vnd.ms-excel';
        fclose($file);

        return $response->withHeader('Content-Type', $contentType);
    }

    public function importContacts(Request $request, Response $response)
    {
        if (!PrivilegeController::hasPrivilege(['privilegeId' => 'admin_contacts', 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }

        ini_set('memory_limit', -1);

        $body = $request->getParsedBody();
        if (!Validator::arrayType()->validate($body['contacts'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body contacts is empty or not an array']);
        }

        $contactCustoms = ContactCustomFieldListModel::get(['select' => ['id', 'type', 'label']]);
        $customTypes    = array_column($contactCustoms, 'type', 'id');
        $customLabels   = array_column($contactCustoms, 'label', 'id');

        $contactCustoms = array_column($contactCustoms, 'id');

        foreach ($contactCustoms as $contactCustom) {
            $contactCustoms['contactCustomField_' . $contactCustom] = (string)$contactCustom;
        }


        $contactFields = ContactController::MAPPING_FIELDS;
        $contactFields = array_merge($contactFields, $contactCustoms);

        $errors = [];

        $contactIds = array_column($body['contacts'], 'id');
        $contactIds = array_filter($contactIds, function ($id) {
            return !empty($id);
        });
        $oldContact = [];
        $oldContactCustom = [];
        if (!empty($contactIds)) {
            $rawContact = ContactModel::get(['select' => ['custom_fields', 'id'], 'where' => ['id in (?)'], 'data' => [$contactIds]]);
            $oldContact = array_column($rawContact, 'id');
            $oldContactCustom = array_column($rawContact, 'custom_fields', 'id');
        }

        foreach ($body['contacts'] as $key => $contact) {
            if (!empty($contact['email']) && (!filter_var($contact['email'], FILTER_VALIDATE_EMAIL) || !Validator::length(1, 255)->validate($contact['email']))) {
                $errors[] = ['error' => "Argument email is not correct for contact {$key}", 'index' => $key, 'lang' => 'argumentMailNotCorrect'];
                continue;
            } elseif (!empty($contact['phone']) && (!preg_match("/\+?((|\ |\.|\(|\)|\-)?(\d)*)*\d$/", $contact['phone']) || !Validator::length(1, 32)->validate($contact['phone']))) {
                $errors[] = ['error' => "Argument phone is not correct for contact {$key}", 'index' => $key, 'lang' => 'argumentPhoneNotCorrect'];
                continue;
            }

            // Check format of fields
            foreach ($contactFields as $frontField => $backField) {
                if (!empty($contact[$frontField]) && $contact[$frontField] !== false) {
                    if (strpos($frontField, 'contactCustomField_') !== false) {
                        $customId = explode('_', $frontField)[1];
                        // Check custom field format
                        $type = $customTypes[(string)$customId];
                        if ($type == 'checkbox' && !Validator::arrayType()->validate($contact[$frontField])) {
                            $errors[] = ['error' => "Argument {$frontField} is not an array for contact {$key}", 'index' => $key, 'lang' => 'argumentNotArray', 'langParam' => $customLabels[$customId]];
                            continue 2;
                        } elseif ($type == 'integer' && !Validator::floatVal()->validate($contact[$frontField])) {
                            $errors[] = ['error' => "Argument {$frontField} is not an integer for contact {$key}", 'index' => $key, 'lang' => 'argumentNotInteger', 'langParam' => $customLabels[$customId]];
                            continue 2;
                        } elseif ($type == 'date' && !Validator::dateTime()->validate($contact[$frontField])) {
                            $errors[] = ['error' => "Argument {$frontField} is not a date for contact {$key}", 'index' => $key, 'lang' => 'argumentNotDate', 'langParam' => $customLabels[$customId]];
                            continue 2;
                        } elseif (in_array($type, ['string', 'select', 'radio']) && !Validator::stringType()->validate($contact[$frontField]) || !Validator::length(1, 255)->validate($contact[$frontField])) {
                            $errors[] = ['error' => "Argument {$frontField} is not a string for contact {$key}", 'index' => $key, 'lang' => 'argumentNotString', 'langParam' => $customLabels[$customId]];
                            continue 2;
                        }
                    } else {
                        if (!Validator::stringType()->validate($contact[$frontField]) || !Validator::length(1, 255)->validate($contact[$frontField])) {
                            $errors[] = ['error' => "Argument {$frontField} is not a string for contact {$key}", 'index' => $key, 'lang' => 'argumentNotString', 'langParam' => $frontField];
                            continue 2;
                        }
                    }
                }
            }

            // Replace civility label by civility id
            if (!empty($contact['civility'])) {
                $civility = ContactCivilityController::getIdByLabel(['label' => $contact['civility']]);
                if (empty($civility)) {
                    $errors[] = ['error' => "Argument civility is not a valid civility for contact {$key}", 'index' => $key, 'lang' => 'argumentCivilityNotCorrect'];
                    continue;
                }
                $contact['civility'] = $civility;
            }

            // If no id, then we create a new contact
            if (empty($contact['id'])) {
                if (empty($contact['lastname']) && empty($contact['company'])) {
                    $errors[] = ['error' => "Argument lastname and company are empty for contact {$key}", 'index' => $key, 'lang' => 'argumentLastnameAndCompanyEmpty'];
                    continue;
                }

                $mandatoryParameters = ContactParameterModel::get(['select' => ['identifier'], 'where' => ['mandatory = ?', 'identifier not in (?)'], 'data' => [true, ['lastname', 'company']]]);
                foreach ($mandatoryParameters as $mandatoryParameter) {
                    if (empty($contact[$mandatoryParameter['identifier']])) {
                        $errors[] = ['error' => "Argument {$mandatoryParameter['identifier']} is empty for contact {$key}", 'index' => $key, 'lang' => 'argumentMandatoryEmpty', 'langParam' => $mandatoryParameter['identifier']];
                        continue 2;
                    }
                }

                $contactToCreate = ['creator' => $GLOBALS['id'], 'custom_fields' => []];
                foreach ($contactFields as $frontField => $backField) {
                    if (!empty($contact[$frontField])) {
                        if (strpos($frontField, 'contactCustomField_') !== false) {
                            $contactToCreate['custom_fields'][$backField] = $contact[$frontField];
                        } else {
                            $contactToCreate[$backField] = $contact[$frontField];
                        }
                    }
                }
                $contactToCreate['custom_fields'] = !empty($contactToCreate['custom_fields']) ? json_encode($contactToCreate['custom_fields']) : '{}';

                ContactModel::create($contactToCreate);
            } else {
                if (!in_array($contact['id'], $oldContact)) {
                    $errors[] = ['error' => "Contact does not exists {$contact['id']}", 'index' => $key, 'lang' => 'contactDoesNotExists'];
                    continue;
                }

                // If id, then we update the contact
                $set = ['modification_date' => 'CURRENT_TIMESTAMP', 'custom_fields' => []];
                $customsToRemove = [];
                foreach ($contactFields as $frontField => $backField) {
                    if (!empty($contact[$frontField])) {
                        if (strpos($frontField, 'contactCustomField_') !== false) {
                            $set['custom_fields'][$backField] = $contact[$frontField];
                        } else {
                            $set[$backField] = $contact[$frontField];
                        }
                    } elseif ($contact[$frontField] === false && strpos($frontField, 'contactCustomField_') !== false) {
                        $customsToRemove[] = $backField;
                    } elseif ($contact[$frontField] === false && strpos($frontField, 'contactCustomField_') === false) {
                        $set[$backField] = null;
                    }
                }

                $oldContactCustom[$contact['id']] = json_decode($oldContactCustom[$contact['id']], true);
                if (!empty($oldContactCustom[$contact['id']])) {
                    $set['custom_fields'] = $set['custom_fields'] + $oldContactCustom[$contact['id']];
                }
                if (!empty($customsToRemove)) {
                    foreach ($customsToRemove as $item) {
                        unset($set['custom_fields'][$item]);
                    }
                }
                $set['custom_fields'] = !empty($set['custom_fields']) ? json_encode($set['custom_fields']) : '{}';

                if (!empty($set)) {
                    ContactModel::update([
                        'set'   => $set,
                        'where' => ['id = ?'],
                        'data'  => [$contact['id']]
                    ]);
                }
            }
        }

        $return = [
            'success'   => count($body['contacts']) - count($errors),
            'errors'    => [
                'count'     => count($errors),
                'details'   => $errors
            ]
        ];

        return $response->withJson($return);
    }

    public function getSectorFromAddress(Request $request, Response $response)
    {
        $queryParams = $request->getQueryParams();

        if (empty($queryParams)) {
            return $response->withStatus(400)->withJson(['errors' => 'Query is not set or empty']);
        } elseif (!empty($queryParams['addressNumber']) && !Validator::stringType()->validate($queryParams['addressNumber'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Query addressNumber is not a string']);
        } elseif (!empty($queryParams['addressStreet']) && !Validator::stringType()->notEmpty()->validate($queryParams['addressStreet'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Query addressStreet is not a string']);
        } elseif (!empty($queryParams['addressPostcode']) && !Validator::stringType()->notEmpty()->validate($queryParams['addressPostcode'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Query addressPostcode is not a string']);
        } elseif (!empty($queryParams['addressTown']) && !Validator::stringType()->notEmpty()->validate($queryParams['addressTown'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Query addressTown is not a string']);
        }

        $sector = ContactController::getAddressSector($queryParams);

        return $response->withJson(['sector' => $sector]);
    }

    public static function getParsedContacts(array $args)
    {
        ValidatorModel::notEmpty($args, ['resId', 'mode']);
        ValidatorModel::intVal($args, ['resId']);
        ValidatorModel::stringType($args, ['mode']);

        $contacts = [];

        $resourceContacts = ResourceContactModel::get([
            'where'     => ['res_id = ?', 'mode = ?'],
            'data'      => [$args['resId'], $args['mode']]
        ]);

        foreach ($resourceContacts as $resourceContact) {
            $contact = [];
            if ($resourceContact['type'] == 'contact') {
                $contactRaw = ContactModel::getById([
                    'select'    => ['*'],
                    'id'        => $resourceContact['item_id']
                ]);

                $civilities = ContactCivilityModel::get(['select' => ['*']]);
                $civilities = array_column($civilities, null, 'id');

                $xmlCivility = $civilities[$contactRaw['civility']] ?? null;
                $civility = [
                    'id'           => $contactRaw['civility'] ?? null,
                    'label'        => $xmlCivility['label'] ?? null,
                    'abbreviation' => $xmlCivility['abbreviation'] ?? null
                ];

                $contact = [
                    'id'                    => $resourceContact['item_id'],
                    'type'                  => 'contact',
                    'civility'              => $civility,
                    'firstname'             => $contactRaw['firstname'],
                    'lastname'              => $contactRaw['lastname'],
                    'company'               => $contactRaw['company'],
                    'department'            => $contactRaw['department'],
                    'function'              => $contactRaw['function'],
                    'addressNumber'         => $contactRaw['address_number'],
                    'addressStreet'         => $contactRaw['address_street'],
                    'addressAdditional1'    => $contactRaw['address_additional1'],
                    'addressAdditional2'    => $contactRaw['address_additional2'],
                    'addressPostcode'       => $contactRaw['address_postcode'],
                    'addressTown'           => $contactRaw['address_town'],
                    'addressCountry'        => $contactRaw['address_country'],
                    'email'                 => $contactRaw['email'],
                    'phone'                 => $contactRaw['phone'],
                    'communicationMeans'    => null,
                    'notes'                 => $contactRaw['notes'],
                    'creator'               => $contactRaw['creator'],
                    'creatorLabel'          => UserModel::getLabelledUserById(['id' => $contactRaw['creator']]),
                    'enabled'               => $contactRaw['enabled'],
                    'creationDate'          => $contactRaw['creation_date'],
                    'modificationDate'      => $contactRaw['modification_date'],
                    'customFields'          => !empty($contactRaw['custom_fields']) ? json_decode($contactRaw['custom_fields'], true) : null,
                    'externalId'            => json_decode($contactRaw['external_id'], true),
                    'sector'                => $contactRaw['sector']
                ];

                if (!empty($contactRaw['communication_means'])) {
                    $communicationMeans = json_decode($contactRaw['communication_means'], true);
                    unset($communicationMeans['password']);
                    $contact['communicationMeans'] = !empty($communicationMeans) ? $communicationMeans : null;
                }

                $filling = ContactController::getFillingRate(['contactId' => $resourceContact['item_id']]);

                $contact['fillingRate'] = $filling;
            } elseif ($resourceContact['type'] == 'user') {
                $user = UserModel::getById(['id' => $resourceContact['item_id']]);

                $phone = null;
                if ($user['id'] == $GLOBALS['id'] || PrivilegeController::hasPrivilege(['privilegeId' => 'view_personal_data', 'userId' => $GLOBALS['id']])) {
                    $phone = $user['phone'];
                }

                $primaryEntity = UserModel::getPrimaryEntityById(['select' => ['entity_label'], 'id' => $user['id']]);

                $userEntities = UserModel::getNonPrimaryEntitiesById(['id' => $user['id']]);
                $userEntities = array_column($userEntities, 'entity_label');

                $nonPrimaryEntities = implode(', ', $userEntities);

                $contact = [
                    'id'                    => $resourceContact['item_id'],
                    'type'                  => 'user',
                    'firstname'             => $user['firstname'],
                    'lastname'              => $user['lastname'],
                    'company'               => null,
                    'department'            => $primaryEntity['entity_label'],
                    'function'              => null,
                    'addressNumber'         => null,
                    'addressStreet'         => null,
                    'addressAdditional1'    => $nonPrimaryEntities,
                    'addressAdditional2'    => null,
                    'addressPostcode'       => null,
                    'addressTown'           => null,
                    'addressCountry'        => null,
                    'email'                 => $user['mail'],
                    'phone'                 => $phone,
                    'communicationMeans'    => null,
                    'notes'                 => null,
                    'creator'               => null,
                    'creatorLabel'          => null,
                    'enabled'               => $user['status'] != 'SPD',
                    'creationDate'          => null,
                    'modificationDate'      => null,
                    'customFields'          => null,
                    'externalId'            => null
                ];
            } elseif ($resourceContact['type'] == 'entity') {
                $entity = EntityModel::getById([
                    'id' => $resourceContact['item_id'],
                    'select' => [
                        'entity_label', 'email', 'enabled', 'address_number', 'address_street', 'address_additional1', 'address_additional2',
                        'address_postcode', 'address_town', 'address_country'
                    ]
                ]);

                $contact = [
                    'id'                    => $resourceContact['item_id'],
                    'type'                  => 'entity',
                    'firstname'             => null,
                    'lastname'              => $entity['entity_label'],
                    'company'               => null,
                    'department'            => null,
                    'function'              => null,
                    'addressNumber'         => $entity['address_number'],
                    'addressStreet'         => $entity['address_street'],
                    'addressAdditional1'    => $entity['address_additional1'],
                    'addressAdditional2'    => $entity['address_additional2'],
                    'addressPostcode'       => $entity['address_postcode'],
                    'addressTown'           => $entity['address_town'],
                    'addressCountry'        => $entity['address_country'],
                    'email'                 => $entity['email'],
                    'phone'                 => null,
                    'communicationMeans'    => null,
                    'notes'                 => null,
                    'creator'               => null,
                    'creatorLabel'          => null,
                    'enabled'               => $entity['enabled'] == 'Y',
                    'creationDate'          => null,
                    'modificationDate'      => null,
                    'customFields'          => null,
                    'externalId'            => null
                ];
            }

            $contacts[] = $contact;
        }

        return $contacts;
    }

    public static function getFormattedContacts(array $args)
    {
        ValidatorModel::notEmpty($args, ['resId', 'mode']);
        ValidatorModel::intVal($args, ['resId']);
        ValidatorModel::stringType($args, ['mode']);
        ValidatorModel::boolType($args, ['onlyContact']);

        $contacts = [];

        $resourceContacts = ResourceContactModel::get([
            'where'     => ['res_id = ?', 'mode = ?'],
            'data'      => [$args['resId'], $args['mode']]
        ]);

        foreach ($resourceContacts as $resourceContact) {
            $contact = '';
            if ($resourceContact['type'] == 'contact') {
                $contactRaw = ContactModel::getById([
                    'select'    => ['*'],
                    'id'        => $resourceContact['item_id']
                ]);

                if (isset($args['onlyContact']) && $args['onlyContact']) {
                    $contactToDisplay = ContactController::getFormattedOnlyContact(['contact' => $contactRaw]);
                } else {
                    $contactToDisplay = ContactController::getFormattedContactWithAddress(['contact' => $contactRaw]);
                }

                $contact = $contactToDisplay['contact']['otherInfo'];
            } elseif ($resourceContact['type'] == 'user') {
                $contact = UserModel::getLabelledUserById(['id' => $resourceContact['item_id']]);
            } elseif ($resourceContact['type'] == 'entity') {
                $entity = EntityModel::getById(['id' => $resourceContact['item_id'], 'select' => ['entity_label']]);
                $contact = $entity['entity_label'];
            }

            $contacts[] = $contact;
        }

        return $contacts;
    }

    public static function getContactCustomField(array $args)
    {
        $contacts = [];
        foreach ($args['contacts'] as $savedContact) {
            $contact = '';
            if ($savedContact['type'] == 'contact') {
                $contactRaw = ContactModel::getById([
                    'select'    => ['*'],
                    'id'        => $savedContact['id']
                ]);

                if (isset($args['onlyContact']) && $args['onlyContact']) {
                    $contactToDisplay = ContactController::getFormattedOnlyContact(['contact' => $contactRaw]);
                } else {
                    $contactToDisplay = ContactController::getFormattedContactWithAddress(['contact' => $contactRaw]);
                }

                $contact = $contactToDisplay['contact']['otherInfo'];
            } elseif ($savedContact['type'] == 'user') {
                $contact = UserModel::getLabelledUserById(['id' => $savedContact['id']]);
            } elseif ($savedContact['type'] == 'entity') {
                $entity = EntityModel::getById(['id' => $savedContact['id'], 'select' => ['entity_label']]);
                $contact = $entity['entity_label'];
            }

            $contacts[] = $contact;
        }

        return $contacts;
    }

    private static function controlContact(array $args)
    {
        $body = $args['body'];

        if (empty($body)) {
            return ['errors' => 'Body is not set or empty'];
        } elseif (!Validator::stringType()->notEmpty()->validate($body['lastname'] ?? null) && !Validator::stringType()->notEmpty()->validate($body['company'] ?? null)) {
            return ['errors' => 'Body lastname or company is mandatory'];
        } elseif (!empty($body['email']) && !filter_var($body['email'], FILTER_VALIDATE_EMAIL)) {
            return ['errors' => 'Body email is not valid'];
        } elseif (!empty($body['phone']) && !preg_match("/\+?((|\ |\.|\(|\)|\-)?(\d)*)*\d$/", $body['phone'])) {
            return ['errors' => 'Body phone is not valid'];
        }

        $lengthFields = [
            'firstname',
            'lastname',
            'company',
            'department',
            'function',
            'addressNumber',
            'addressStreet',
            'addressPostcode',
            'addressTown',
            'addressCountry',
            'email',
            'phone'
        ];

        foreach ($lengthFields as $field) {
            if (!empty($body[$field]) && !Validator::stringType()->length(1, 256)->validate($body[$field])) {
                return ['errors' => "Body {$field} length is not valid (1..256)"];
            }
        }

        if (!empty($body['customFields'])) {
            if (!Validator::arrayType()->notEmpty()->validate($body['customFields'])) {
                return ['errors' => 'Body customFields is not an array'];
            }
            $customFields = ContactCustomFieldListModel::get(['select' => ['count(1)'], 'where' => ['id in (?)'], 'data' => [array_keys($body['customFields'])]]);
            if (count($body['customFields']) != $customFields[0]['count']) {
                return ['errors' => 'Body customFields : One or more custom fields do not exist'];
            }
        }

        $mandatoryParameters = ContactParameterModel::get(['select' => ['identifier'], 'where' => ['mandatory = ?', 'identifier not in (?)'], 'data' => [true, ['lastname', 'company']]]);
        foreach ($mandatoryParameters as $mandatoryParameter) {
            if (strpos($mandatoryParameter['identifier'], 'contactCustomField_') !== false) {
                $customId = explode('_', $mandatoryParameter['identifier'])[1];
                if (empty($body['customFields'][$customId])) {
                    return ['errors' => "Body customFields[{$customId}] is mandatory"];
                }
            } else {
                if (empty($body[$mandatoryParameter['identifier']])) {
                    return ['errors' => "Body {$mandatoryParameter['identifier']} is mandatory"];
                }
            }
        }

        if (!empty($body['externalId']['m2m'])) {
            $businessId = explode("/", $body['externalId']['m2m']);
            if (!AnnuaryController::isSiretNumber(['siret' => $businessId[0]])) {
                return ['errors' => _EXTERNALID_M2M_VALIDATOR];
            }
        }

        return true;
    }

    public static function getFormattedOnlyContact(array $args)
    {
        ValidatorModel::notEmpty($args, ['contact']);
        ValidatorModel::arrayType($args, ['contact']);

        $contactName = '';
        if (!empty($args['contact']['firstname'])) {
            $contactName .= $args['contact']['firstname'] . ' ';
        }
        if (!empty($args['contact']['lastname'])) {
            $contactName .= $args['contact']['lastname'] . ' ';
        }

        $company = '';
        if (!empty($args['contact']['company'])) {
            $company = $args['contact']['company'];

            if (!empty($contactName)) {
                $company = '(' . $company . ') ';
            }
        }

        $contactToDisplay = $contactName . $company;

        $contact = [
            'type'          => 'onlyContact',
            'id'            => $args['contact']['id'],
            'idToDisplay'   => $contactToDisplay,
            'otherInfo'     => trim($contactToDisplay),
            'rateColor'     => ''
        ];

        return ['contact' => $contact];
    }

    public static function getFormattedContactWithAddress(array $args)
    {
        ValidatorModel::notEmpty($args, ['contact']);
        ValidatorModel::arrayType($args, ['contact']);
        ValidatorModel::boolType($args, ['color']);

        if (!empty($args['color'])) {
            $rate = ContactController::getFillingRate(['contactId' => $args['contact']['id']]);
        }
        $thresholdLevel = empty($rate['thresholdLevel']) ? '' : $rate['thresholdLevel'];

        $address = '';

        if (!empty($args['contact']['address_number'])) {
            $address.= $args['contact']['address_number'] . ' ';
        }
        if (!empty($args['contact']['address_street'])) {
            $address.= $args['contact']['address_street'] . ' ';
        }
        if (!empty($args['contact']['address_postcode'])) {
            $address.= $args['contact']['address_postcode'] . ' ';
        }
        if (!empty($args['contact']['address_town'])) {
            $address.= $args['contact']['address_town'] . ' ';
        }
        if (!empty($args['contact']['address_country'])) {
            $address.= $args['contact']['address_country'];
        }

        $contactName = '';
        if (!empty($args['contact']['firstname'])) {
            $contactName .= $args['contact']['firstname'] . ' ';
        }
        if (!empty($args['contact']['lastname'])) {
            $contactName .= $args['contact']['lastname'] . ' ';
        }

        $company = '';
        if (!empty($args['contact']['company'])) {
            $company = $args['contact']['company'];

            if (!empty($contactName)) {
                $company = '(' . $company . ')';
            }
        }

        $contactToDisplay = trim($contactName . $company);

        $sector = $args['contact']['sector'] ?? '';
        $email = $args['contact']['email'] ?? '';
        $otherInfo = empty($address) ? "{$contactToDisplay}" : "{$contactToDisplay} - {$address}" . (!empty($sector) ? " - {$sector}" : '');
        $contact = [
            'type'           => 'contact',
            'id'             => $args['contact']['id'],
            'contact'        => $contactToDisplay,
            'address'        => $address,
            'sector'         => $sector,
            'idToDisplay'    => "{$contactToDisplay}<br/>{$address}",
            'otherInfo'      => $otherInfo,
            'thresholdLevel' => $thresholdLevel,
            'email'           => $email
        ];

        return ['contact' => $contact];
    }

    public static function getAutocompleteFormat(array $args)
    {
        ValidatorModel::notEmpty($args, ['id']);
        ValidatorModel::intVal($args, ['id']);

        $displayableParameters = ContactParameterModel::get(['select' => ['identifier'], 'where' => ['displayable = ?'], 'data' => [true]]);

        $displayableStdParameters = [];
        $displayableCstParameters = [];
        foreach ($displayableParameters as $displayableParameter) {
            if (strpos($displayableParameter['identifier'], 'contactCustomField_') !== false) {
                $displayableCstParameters[] = explode('_', $displayableParameter['identifier'])[1];
            } else {
                $displayableStdParameters[] = ContactController::MAPPING_FIELDS[$displayableParameter['identifier']];
            }
        }

        if (!empty($displayableCstParameters)) {
            $displayableStdParameters[] = 'custom_fields';
        }

        $rawContact = ContactModel::getById(['id' => $args['id'], 'select' => $displayableStdParameters]);
        $contact = ['type' => 'contact', 'id' => $args['id'], 'lastname' => $rawContact['lastname'], 'company' => $rawContact['company']];

        if (in_array('civility', $displayableStdParameters)) {
            $contact['civility'] = null;

            if (!empty($rawContact['civility'])) {
                $civilities = ContactCivilityModel::get(['select' => ['*']]);
                $civilities = array_column($civilities, null, 'id');

                $contact['civility'] = [
                    'id'           => $rawContact['civility'],
                    'label'        => $civilities[$rawContact['civility']]['label'],
                    'abbreviation' => $civilities[$rawContact['civility']]['abbreviation']
                ];
            }
        }
        if (in_array('firstname', $displayableStdParameters)) {
            $contact['firstname'] = $rawContact['firstname'];
        } else {
            $contact['firstname'] = '';
        }
        if (in_array('department', $displayableStdParameters)) {
            $contact['department'] = $rawContact['department'];
        }
        if (in_array('function', $displayableStdParameters)) {
            $contact['function'] = $rawContact['function'];
        }
        if (in_array('address_number', $displayableStdParameters)) {
            $contact['addressNumber'] = $rawContact['address_number'];
        }
        if (in_array('address_street', $displayableStdParameters)) {
            $contact['addressStreet'] = $rawContact['address_street'];
        }
        if (in_array('address_additional1', $displayableStdParameters)) {
            $contact['addressAdditional1'] = $rawContact['address_additional1'];
        }
        if (in_array('address_additional2', $displayableStdParameters)) {
            $contact['addressAdditional2'] = $rawContact['address_additional2'];
        }
        if (in_array('address_postcode', $displayableStdParameters)) {
            $contact['addressPostcode'] = $rawContact['address_postcode'];
        }
        if (in_array('address_town', $displayableStdParameters)) {
            $contact['addressTown'] = $rawContact['address_town'];
        }
        if (in_array('address_country', $displayableStdParameters)) {
            $contact['addressCountry'] = $rawContact['address_country'];
        }
        if (in_array('email', $displayableStdParameters)) {
            $contact['email'] = $rawContact['email'];
        }
        if (in_array('phone', $displayableStdParameters)) {
            $contact['phone'] = $rawContact['phone'];
        }
        if (in_array('notes', $displayableStdParameters)) {
            $contact['notes'] = $rawContact['notes'];
        }
        if (in_array('sector', $displayableStdParameters)) {
            $contact['sector'] = $rawContact['sector'];
        }

        if (!empty($displayableCstParameters)) {
            $contact['customFields'] = [];

            $customFields = json_decode($rawContact['custom_fields'] ?? '{}', true);
            foreach ($displayableCstParameters as $value) {
                $contact['customFields'][$value] = $customFields[$value] ?? null;
            }
        }

        $fillingRate = ContactController::getFillingRate(['contactId' => $args['id']]);
        $contact['fillingRate'] = empty($fillingRate) ? null : $fillingRate;

        return $contact;
    }

    private static function isContactUsed(array $args)
    {
        ValidatorModel::notEmpty($args, ['ids']);
        ValidatorModel::arrayType($args, ['ids']);

        $contactsUsed = array_fill_keys($args['ids'], false);

        $inResources = ResourceContactModel::get([
            'select' => ['item_id'],
            'where'  => ['item_id in (?)', 'type = ?'],
            'data'   => [$args['ids'], 'contact']
        ]);
        $inResources = array_column($inResources, 'item_id');

        $inAcknowledgementReceipts = AcknowledgementReceiptModel::get([
            'select' => ['contact_id'],
            'where'  => ['contact_id in (?)'],
            'data'   => [$args['ids']]
        ]);
        $inAcknowledgementReceipts = array_column($inAcknowledgementReceipts, 'contact_id');

        $inAttachments = AttachmentModel::get([
            'select' => ['recipient_id'],
            'where'  => ['recipient_id in (?)', 'recipient_type = ?'],
            'data'   => [$args['ids'], 'contact']
        ]);
        $inAttachments = array_column($inAttachments, 'recipient_id');

        foreach ($contactsUsed as $id => $item) {
            $contactsUsed[$id] = in_array($id, $inResources) || in_array($id, $inAcknowledgementReceipts) || in_array($id, $inAttachments);
        }

        return $contactsUsed;
    }

    public static function getAddressSector(array $args)
    {
        ValidatorModel::stringType($args, ['addressNumber', 'addressStreet', 'addressPostcode', 'addressTown']);

        $where = [];
        $data = [];

        if (!empty($args['addressNumber'])) {
            $where[] = 'unaccent(address_number) ilike unaccent(?)';
            $data[] = $args['addressNumber'];
        } else {
            $where[] = 'address_number is null';
        }

        if (!empty($args['addressStreet'])) {
            $where[] = 'unaccent(address_street) ilike unaccent(?)';
            $data[] = $args['addressStreet'];
        } else {
            $where[] = 'address_street is null';
        }

        if (!empty($args['addressPostcode'])) {
            $where[] = 'unaccent(address_postcode) ilike unaccent(?)';
            $data[] = $args['addressPostcode'];
        } else {
            $where[] = 'address_postcode is null';
        }

        if (!empty($args['addressTown'])) {
            $where[] = 'unaccent(address_town) ilike unaccent(?)';
            $data[] = $args['addressTown'];
        } else {
            $where[] = 'address_town is null';
        }

        $sector = ContactAddressSectorModel::get([
            'select' => ['*'],
            'where'  => $where,
            'data'   => $data
        ]);

        if (empty($sector[0])) {
            return null;
        }

        return $sector[0];
    }
}
