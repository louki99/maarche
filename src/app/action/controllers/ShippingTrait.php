<?php

/**
* Copyright Maarch since 2008 under licence GPLv3.
* See LICENCE.txt file at the root folder for more details.
* This file is part of Maarch software.

* @brief   AcknowledgementReceiptTrait
* @author  dev <dev@maarch.org>
* @ingroup core
*/

namespace Action\controllers;

use Attachment\models\AttachmentModel;
use Contact\controllers\ContactController;
use Contact\models\ContactModel;
use Convert\controllers\ConvertPdfController;
use Convert\models\AdrModel;
use Docserver\models\DocserverModel;
use Docserver\models\DocserverTypeModel;
use Entity\models\EntityModel;
use Resource\controllers\StoreController;
use Resource\models\ResModel;
use Resource\models\ResourceContactModel;
use Shipping\controllers\ShippingTemplateController;
use Shipping\models\ShippingModel;
use Shipping\models\ShippingTemplateModel;
use SrcCore\models\CoreConfigModel;
use SrcCore\models\CurlModel;
use SrcCore\models\PasswordModel;
use SrcCore\models\ValidatorModel;
use User\models\UserModel;


trait ShippingTrait
{
    public static function createMailevaShippings(array $args)
    {
        ValidatorModel::notEmpty($args, ['resId', 'action']);
        ValidatorModel::intVal($args, ['resId']);
        ValidatorModel::arrayType($args, ['data', 'action']);
        ValidatorModel::notEmpty($args['action'], ['id']);
        ValidatorModel::intVal($args['action'], ['id']);

        $resource = ResModel::getById(['select' => ['destination', 'integrations', 'subject as title', 'external_id', 'res_id', 'version'], 'resId' => $args['resId']]);
        $integrations = json_decode($resource['integrations'], true);

        $recipientEntity = EntityModel::getByEntityId(['select' => ['id'], 'entityId' => $resource['destination']]);

        $mailevaConfig = CoreConfigModel::getMailevaConfiguration();
        if (empty($mailevaConfig)) {
            return ['errors' => ['Maileva configuration does not exist']];
        } elseif (!$mailevaConfig['enabled']) {
            return ['errors' => ['Maileva configuration is disabled']];
        }
        $shippingTemplate = ShippingTemplateModel::getById(['id' => $args['data']['shippingTemplateId']]);
        if (empty($shippingTemplate)) {
            return ['errors' => ['Shipping template does not exist']];
        }
        $shippingTemplate['options'] = json_decode($shippingTemplate['options'], true);
        $shippingTemplate['account'] = json_decode($shippingTemplate['account'], true);
        $shippingTemplate['fee'] = json_decode($shippingTemplate['fee'], true);

        $attachments = AttachmentModel::get([
            'select'    => ['res_id', 'title', 'recipient_id', 'recipient_type', 'external_id', 'status'],
            'where'     => ['res_id_master = ?', 'in_send_attach = ?', 'status not in (?)', 'attachment_type not in (?)'],
            'data'      => [$args['resId'], true, ['OBS', 'DEL', 'TMP', 'FRZ'], ['signed_response']]
        ]);

        if (empty($attachments) && empty($integrations['inShipping'])) {
            return true;
        }

        $resourcesList = [];

        $contacts = [];
        foreach ($attachments as $attachment) {
            $attachmentId = $attachment['res_id'];
            if ($attachment['status'] == 'SIGN') {
                $signedAttachment = AttachmentModel::get([
                    'select'    => ['res_id'],
                    'where'     => ['origin = ?', 'status not in (?)', 'attachment_type = ?'],
                    'data'      => ["{$args['resId']},res_attachments", ['OBS', 'DEL', 'TMP', 'FRZ'], 'signed_response']
                ]);
                if (!empty($signedAttachment[0])) {
                    $attachmentId = $signedAttachment[0]['res_id'];
                }
            }

            $convertedDocument = AdrModel::getConvertedDocumentById([
                'select'    => ['docserver_id','path', 'filename', 'fingerprint'],
                'resId'     => $attachmentId,
                'collId'    => 'attachments_coll',
                'type'      => 'PDF'
            ]);
            if (empty($convertedDocument)) {
                return ['errors' => ['No conversion for attachment']];
            }
            if (empty($attachment['recipient_id']) || $attachment['recipient_type'] != 'contact') {
                return ['errors' => ['Contact is empty for attachment']];
            }
            $contact = ContactModel::getById(['select' => ['*'], 'id' => $attachment['recipient_id']]);
            if (empty($contact)) {
                return ['errors' => ['Contact does not exist for attachment']];
            }
            if (!empty($contact['address_country']) && strtoupper(trim($contact['address_country'])) != 'FRANCE') {
                return ['errors' => ['Contact country is not France']];
            }
            $afnorAddress = ContactController::getContactAfnor($contact);
            if ((empty($afnorAddress[1]) && empty($afnorAddress[2])) || empty($afnorAddress[6]) || !preg_match("/^\d{5}\s/", $afnorAddress[6])) {
                return ['errors' => ['Contact is not fill enough for attachment']];
            }
            $contacts[] = $afnorAddress;

            $attachment['type'] = 'attachment';
            $resourcesList[] = $attachment;
        }

        if (!empty($integrations['inShipping'])) {
            $convertedDocument = AdrModel::getDocuments([
                'select'    => ['docserver_id', 'path', 'filename', 'fingerprint'],
                'where'     => ['res_id = ?', 'type in (?)', 'version = ?'],
                'data'      => [$args['resId'], ['PDF', 'SIGN'], $resource['version']],
                'orderBy'   => ['version', "type='SIGN' DESC"],
                'limit'     => 1
            ]);
            $convertedDocument = $convertedDocument[0] ?? null;
            if (empty($convertedDocument)) {
                return ['errors' => ['No conversion for resource']];
            }
            $resourceContacts = ResourceContactModel::get([
                'where' => ['res_id = ?', 'mode = ?', 'type = ?'],
                'data'  => [$args['resId'], 'recipient', 'contact']
            ]);
            if (empty($resourceContacts)) {
                return ['errors' => ['No contact found for resource']];
            }

            $contactsResource = [];
            foreach ($resourceContacts as $resourceContact) {
                $contact = ContactModel::getById(['select' => ['*'], 'id' => $resourceContact['item_id']]);
                if (empty($contact)) {
                    return ['errors' => ['Contact does not exist for resource']];
                }
                if (!empty($contact['address_country']) && strtoupper(trim($contact['address_country'])) != 'FRANCE') {
                    return ['errors' => ['Contact country is not France']];
                }
                $afnorAddress = ContactController::getContactAfnor($contact);
                if ((empty($afnorAddress[1]) && empty($afnorAddress[2])) || empty($afnorAddress[6]) || !preg_match("/^\d{5}\s/", $afnorAddress[6])) {
                    return ['errors' => ['Contact is not filled enough for resource']];
                }
                $contactsResource[] = $afnorAddress;
            }
            $contacts[] = $contactsResource;

            $resource['type'] = 'resource';
            $resourcesList[] = $resource;
        }

        $urlComplement = 'mail';
        $isRegisteredMail = false;
        if (strpos($shippingTemplate['options']['sendMode'], 'digital_registered_mail') !== false) {
            $urlComplement = 'registered_mail';
            $isRegisteredMail = true;
            $addressEntity = UserModel::getPrimaryEntityById([
                'id'        => $GLOBALS['id'],
                'select'    => [
                    'entities.entity_id', 'entities.short_label', 'entities.address_number', 'entities.address_street', 'entities.address_additional1', 'entities.address_additional2', 'entities.address_postcode', 'entities.address_town', 'entities.address_country'
                ]
            ]);
            $entityRoot = EntityModel::getEntityRootById(['entityId' => $addressEntity['entity_id']]);

            $addressEntity = ContactController::getContactAfnor([
                'company'               => $entityRoot['entity_label'],
                'civility'              => '',
                'firstname'             => $addressEntity['short_label'],
                'lastname'              => '',
                'address_number'        => $addressEntity['address_number'],
                'address_street'        => $addressEntity['address_street'],
                'address_additional1'   => $addressEntity['address_additional1'],
                'address_additional2'   => $addressEntity['address_additional2'],
                'address_postcode'      => $addressEntity['address_postcode'],
                'address_town'          => $addressEntity['address_town'],
                'address_country'       => $addressEntity['address_country']
            ]);
            if ((empty($addressEntity[1]) && empty($addressEntity[2])) || empty($addressEntity[6]) || !preg_match("/^\d{5}\s/", $addressEntity[6])) {
                return ['errors' => ['User primary entity address is not filled enough']];
            }
        }

        $curlAuth = CurlModel::exec([
            'url'           => $mailevaConfig['connectionUri'] . '/authentication/oauth2/token',
            'basicAuth'     => ['user' => $mailevaConfig['clientId'], 'password' => $mailevaConfig['clientSecret']],
            'headers'       => ['Content-Type: application/x-www-form-urlencoded'],
            'method'        => 'POST',
            'queryParams'   => [
                'grant_type'    => 'password',
                'username'      => $shippingTemplate['account']['id'],
                'password'      => PasswordModel::decrypt(['cryptedPassword' => $shippingTemplate['account']['password']])
            ]
        ]);
        if ($curlAuth['code'] != 200) {
            return ['errors' => ['Maileva authentication failed']];
        }
        $token = $curlAuth['response']['access_token'];

        $errors = [];
        foreach ($resourcesList as $key => $resource) {
            $sendingName = CoreConfigModel::uniqueId();
            $resId = $resource['res_id'];

            if ($isRegisteredMail) {
                $body = [
                    'name' => $sendingName,
                    'acknowledgement_of_receipt'    => $shippingTemplate['options']['sendMode'] == 'digital_registered_mail_with_AR',
                    "sender_address_line_1"         => $addressEntity[1] ?? null,
                    "sender_address_line_2"         => $addressEntity[2] ?? null,
                    "sender_address_line_3"         => $addressEntity[3] ?? null,
                    "sender_address_line_4"         => $addressEntity[4] ?? null,
                    "sender_address_line_5"         => $addressEntity[5] ?? null,
                    "sender_address_line_6"         => $addressEntity[6] ?? null,
                    "sender_country_code"           => "FR"
                ];
            } else {
                $body = [
                    'name' => $sendingName
                ];
            }
            $createSending = CurlModel::exec([
                'url'           => "{$mailevaConfig['uri']}/{$urlComplement}/v2/sendings",
                'bearerAuth'    => ['token' => $token],
                'headers'       => ['Content-Type: application/json'],
                'method'        => 'POST',
                'body'          => json_encode($body)
            ]);
            if ($createSending['code'] != 201) {
                $errors[] = "Maileva sending creation failed for attachment {$resId}";
                continue;
            }
            if (empty($createSending['response']['id'])) {
                $errors[] = "Maileva sending id not found for attachment {$resId}";
                continue;
            }
            $sendingId = $createSending['response']['id'];

            $resourceIdToFind = $resId;
            if ($resource['type'] == 'attachment' && $resource['status'] == 'SIGN') {
                $signedAttachment = AttachmentModel::get([
                    'select'    => ['res_id'],
                    'where'     => ['origin = ?', 'status not in (?)', 'attachment_type = ?'],
                    'data'      => ["{$args['resId']},res_attachments", ['OBS', 'DEL', 'TMP', 'FRZ'], 'signed_response']
                ]);
                if (!empty($signedAttachment[0])) {
                    $resourceIdToFind = $signedAttachment[0]['res_id'];
                }
            }
            $convertedDocument = ConvertPdfController::getConvertedPdfById(['resId' => $resourceIdToFind, 'collId' => ($resource['type'] == 'resource' ? 'letterbox_coll' : 'attachments_coll')]);
            $docserver = DocserverModel::getByDocserverId(['docserverId' => $convertedDocument['docserver_id'], 'select' => ['path_template', 'docserver_type_id']]);
            if (empty($docserver['path_template']) || !file_exists($docserver['path_template'])) {
                $errors[] = "Docserver does not exist for {$resource['type']} {$resId}";
                continue;
            }
            $pathToDocument = $docserver['path_template'] . str_replace('#', DIRECTORY_SEPARATOR, $convertedDocument['path']) . $convertedDocument['filename'];
            if (!file_exists($pathToDocument) || !is_file($pathToDocument)) {
                $errors[] = "Document not found on docserver for {$resource['type']} {$resId}";
                continue;
            }

            $docserverType = DocserverTypeModel::getById(['id' => $docserver['docserver_type_id'], 'select' => ['fingerprint_mode']]);
            $fingerprint = StoreController::getFingerPrint(['filePath' => $pathToDocument, 'mode' => $docserverType['fingerprint_mode']]);
            if ($convertedDocument['fingerprint'] != $fingerprint) {
                $errors[] = "Fingerprints do not match for {$resource['type']} {$resId}";
                continue;
            }

            $createDocument = CurlModel::exec([
                'url'           => "{$mailevaConfig['uri']}/{$urlComplement}/v2/sendings/{$sendingId}/documents",
                'bearerAuth'    => ['token' => $token],
                'method'        => 'POST',
                'multipartBody' => [
                    'document' => ['isFile' => true, 'filename' => $convertedDocument['filename'], 'content' => file_get_contents($pathToDocument)],
                    'metadata' => json_encode(['priority' => 0, 'name' => $resource['title']])
                ]
            ]);
            if ($createDocument['code'] != 201) {
                $errors[] = "Maileva document creation failed for resource {$resId}";
                continue;
            }

            $recipients = [];
            if ($resource['type'] == 'attachment') {
                $createRecipient = CurlModel::exec([
                    'url'           => "{$mailevaConfig['uri']}/{$urlComplement}/v2/sendings/{$sendingId}/recipients",
                    'bearerAuth'    => ['token' => $token],
                    'headers'       => ['Content-Type: application/json'],
                    'method'        => 'POST',
                    'body'          => json_encode([
                        "address_line_1"    => $contacts[$key][1],
                        "address_line_2"    => $contacts[$key][2],
                        "address_line_3"    => $contacts[$key][3],
                        "address_line_4"    => $contacts[$key][4],
                        "address_line_5"    => $contacts[$key][5],
                        "address_line_6"    => $contacts[$key][6],
                        "country_code"      => 'FR'
                    ]),
                ]);
                if ($createRecipient['code'] != 201 || empty($createRecipient['response']) || !is_array($createRecipient['response'])) {
                    $errors[] = "Maileva recipient creation failed for resource {$resId}";
                    continue;
                }
                $contact[$key]['recipientId'] = $createRecipient['response']['id'] ?? null;
                $contact[$key]['acknowledgement_of_receipt_url'] = $createRecipient['response']['acknowledgement_of_receipt_url'] ?? null;
                $recipients[] = $contacts[$key];
            } else {
                foreach ($contacts[$key] as $contact) {
                    $createRecipient = CurlModel::exec([
                        'url'           => "{$mailevaConfig['uri']}/{$urlComplement}/v2/sendings/{$sendingId}/recipients",
                        'bearerAuth'    => ['token' => $token],
                        'headers'       => ['Content-Type: application/json'],
                        'method'        => 'POST',
                        'body'          => json_encode([
                            "address_line_1"    => $contact[1],
                            "address_line_2"    => $contact[2],
                            "address_line_3"    => $contact[3],
                            "address_line_4"    => $contact[4],
                            "address_line_5"    => $contact[5],
                            "address_line_6"    => $contact[6],
                            "country_code"      => 'FR'
                        ]),
                    ]);
                    if ($createRecipient['code'] != 201) {
                        $errors[] = "Maileva recipient creation failed for resource {$resId}";
                        continue 2;
                    }
                    $contact['recipientId'] = $createRecipient['response']['id'];
                    $contact['acknowledgement_of_receipt_url'] = $createRecipient['response']['acknowledgement_of_receipt_url'];
                    $recipients[] = $contact;
                }
            }

            $body = [
                'color_printing'            => in_array('color', $shippingTemplate['options']['shapingOptions']),
                'duplex_printing'           => in_array('duplexPrinting', $shippingTemplate['options']['shapingOptions']),
                'optional_address_sheet'    => in_array('addressPage', $shippingTemplate['options']['shapingOptions'])
            ];
            if (!$isRegisteredMail) {
                $body['postage_type'] = strtoupper($shippingTemplate['options']['sendMode']);
            }
            $setOptions = CurlModel::exec([
                'url'           => "{$mailevaConfig['uri']}/{$urlComplement}/v2/sendings/{$sendingId}",
                'bearerAuth'    => ['token' => $token],
                'headers'       => ['Content-Type: application/json'],
                'method'        => 'PATCH',
                'body'          => json_encode($body),
            ]);
            if ($setOptions['code'] != 200) {
                $errors[] = "Maileva options modification failed for attachment {$resId}";
                continue;
            }

            $submit = CurlModel::exec([
                'url'           => "{$mailevaConfig['uri']}/{$urlComplement}/v2/sendings/{$sendingId}/submit",
                'bearerAuth'    => ['token' => $token],
                'headers'       => ['Content-Type: application/json'],
                'method'        => 'POST'
            ]);
            if ($submit['code'] != 200) {
                $errors[] = "Maileva submit failed for attachment {$resId}";
                continue;
            }

            $externalId = json_decode($resource['external_id'], true);
            $externalId['mailevaSendingId'] = $sendingId;
            if ($resource['type'] == 'attachment') {
                AttachmentModel::update(['set' => ['external_id' => json_encode($externalId)], 'where' => ['res_id = ?'], 'data' => [$resId]]);
            } else {
                ResModel::update(['set' => ['external_id' => json_encode($externalId)], 'where' => ['res_id = ?'], 'data' => [$resId]]);
            }

            $fee = ShippingTemplateController::calculShippingFee([
                'fee'       => $shippingTemplate['fee'],
                'resources' => [$resource]
            ]);

            ShippingModel::create([
                'userId'            => $GLOBALS['id'],
                'sendingId'         => $sendingId,
                'documentId'        => $resId,
                'documentType'      => $resource['type'],
                'options'           => !empty($shippingTemplate['options']) ? json_encode($shippingTemplate['options']) : '{}',
                'fee'               => $fee,
                'recipientEntityId' => $recipientEntity['id'],
                'accountId'         => $shippingTemplate['account']['id'],
                'recipients'        => !empty($recipients) ? json_encode($recipients) : '[]',
                'actionId'          => $args['action']['id']
            ]);
        }

        if (!empty($errors)) {
            return ['errors' => $errors];
        }

        return true;
    }
}
