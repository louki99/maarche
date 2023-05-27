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

use AcknowledgementReceipt\models\AcknowledgementReceiptModel;
use Configuration\models\ConfigurationModel;
use Contact\models\ContactModel;
use ContentManagement\controllers\MergeController;
use Convert\controllers\ConvertPdfController;
use Docserver\controllers\DocserverController;
use Docserver\models\DocserverModel;
use Doctype\models\DoctypeModel;
use Email\controllers\EmailController;
use Entity\models\EntityModel;
use Group\controllers\PrivilegeController;
use Resource\models\ResModel;
use Resource\models\ResourceContactModel;
use SrcCore\models\CoreConfigModel;
use SrcCore\models\DatabaseModel;
use SrcCore\models\ValidatorModel;
use Template\models\TemplateModel;
use User\models\UserModel;

trait AcknowledgementReceiptTrait
{
    public static function createAcknowledgementReceipts(array $args)
    {
        ValidatorModel::notEmpty($args, ['resId']);
        ValidatorModel::intVal($args, ['resId']);

        $resource = ResModel::getById(['select' => ['type_id', 'destination', 'subject', 'category_id'], 'resId' => $args['resId']]);
        if (empty($resource) || $resource['category_id'] != 'incoming') {
            return [];
        }

        $args['data']['manual'] = $args['data']['manual'] ?? null;

        if ($args['data']['manual'] != null && $args['action']['parameters']['mode'] == 'auto') {
            return [];
        } elseif ($args['data']['manual'] == null && $args['action']['parameters']['mode'] == 'manual') {
            return [];
        }

        $subjectResource = $resource['subject'] ?? '';

        if ($args['data']['manual'] != null) {
            $contentToSend = $args['data']['content'] ?? null;
            $subjectToSend = !empty($args['data']['subject']) ? $args['data']['subject'] : '[AR] ' . $subjectResource;
        } else {
            $contentToSend = null;
            $subjectToSend = '[AR] ' . $subjectResource;
        }

        $contactsToProcess = ResourceContactModel::get([
            'select' => ['item_id'],
            'where' => ['res_id = ?', 'type = ?', 'mode = ?'],
            'data' => [$args['resId'], 'contact', 'sender']
        ]);
        $contactsToProcess = array_column($contactsToProcess, 'item_id');

        foreach ($contactsToProcess as $contactToProcess) {
            if (empty($contactToProcess)) {
                return [];
            }
        }

        $doctype['process_mode'] = '';
        if (!empty($resource['type_id'])) {
            $doctype = DoctypeModel::getById(['id' => $resource['type_id'], 'select' => ['process_mode']]);
        }

        if ($doctype['process_mode'] == 'SVA') {
            $templateAttachmentType = 'sva';
        } elseif ($doctype['process_mode'] == 'SVR') {
            $templateAttachmentType = 'svr';
        } else {
            $templateAttachmentType = 'simple';
        }

        $template = TemplateModel::getWithAssociation([
            'select'    => ['template_content', 'template_path', 'template_file_name', 'options'],
            'where'     => ['template_target = ?', 'template_attachment_type = ?', 'value_field = ?'],
            'data'      => ['acknowledgementReceipt', $templateAttachmentType, $resource['destination']]
        ]);
        $acknowledgementOptions = !empty($template[0]) ? json_decode($template[0]['options'], true) : null;
        if ($args['data']['manual'] == null) {
            if (empty($template[0])) {
                return [];
            }
    
            $docserver = DocserverModel::getByDocserverId(['docserverId' => 'TEMPLATES', 'select' => ['path_template']]);
            $pathToDocument = $docserver['path_template'] . str_replace('#', DIRECTORY_SEPARATOR, $template[0]['template_path']) . $template[0]['template_file_name'];
        }

        $currentUser = UserModel::getById(['id' => $GLOBALS['id'], 'select' => ['id', 'mail']]);
        $ids          = [];
        $errors       = [];
        $emailsToSend = [];
        DatabaseModel::beginTransaction();
        foreach ($contactsToProcess as $contactToProcess) {
            $contact = ContactModel::getById(['select' => ['*'], 'id' => $contactToProcess]);

            if (empty($contact['email']) && (empty($contact['address_street']) || empty($contact['address_town']) || empty($contact['address_postcode']))) {
                DatabaseModel::rollbackTransaction();
                return [];
            }

            if (!empty($contact['email'])) {
                if (empty($template[0]['template_content']) && empty($contentToSend)) {
                    DatabaseModel::rollbackTransaction();
                    return [];
                }
                if (empty($contentToSend)) {
                    $mergedDocument = MergeController::mergeDocument([
                        'content' => $template[0]['template_content'],
                        'data'    => ['resId' => $args['resId'], 'senderId' => $contactToProcess, 'senderType' => 'contact', 'userId' => $GLOBALS['id']]
                    ]);
                } else {
                    $mergedDocument['encodedDocument'] = base64_encode($contentToSend);
                }
                $format = 'html';
            } else {
                if ($args['data']['manual'] == null) {
                    if (!file_exists($pathToDocument) || !is_file($pathToDocument)) {
                        DatabaseModel::rollbackTransaction();
                        return [];
                    }

                    $pathInfo = pathinfo($pathToDocument);
                    $extension = $pathInfo['extension'];
                    $tmpFile = CoreConfigModel::getTmpPath() . "tmp_file_{$GLOBALS['id']}_" .rand(). "." . $extension;
                    $fileContent = MergeController::mergeChronoDocument(['chrono' => '', 'path' => $pathToDocument, 'type' => 'acknowledgementReceipt', 'resIdMaster' => $args['resId'], 'resId' => null, 'title' => null]);
                    file_put_contents($tmpFile, base64_decode($fileContent['encodedDocument']));
                    $mergedDocument = MergeController::mergeDocument([
                        'path' => $tmpFile,
                        'data' => ['resId' => $args['resId'], 'senderId' => $contactToProcess, 'senderType' => 'contact', 'userId' => $GLOBALS['id']]
                    ]);
                    unlink($tmpFile);

                    $extension = pathinfo($pathToDocument, PATHINFO_EXTENSION);
                    $encodedDocument = ConvertPdfController::convertFromEncodedResource(['encodedResource' => $mergedDocument['encodedDocument'], 'extension' => $extension]);
                } else {
                    $encodedDocument = ConvertPdfController::convertFromEncodedResource(['encodedResource' => base64_encode($contentToSend), 'extension' => 'html']);
                }
                $mergedDocument['encodedDocument'] = $encodedDocument["encodedResource"];
                $format = 'pdf';

                if (!empty($mergedDocument['encodedDocument']['errors'])) {
                    DatabaseModel::rollbackTransaction();
                    return [];
                }
            }

            $storeResult = DocserverController::storeResourceOnDocServer([
                'collId'            => 'letterbox_coll',
                'docserverTypeId'   => 'ACKNOWLEDGEMENT_RECEIPTS',
                'encodedResource'   => $mergedDocument['encodedDocument'],
                'format'            => $format
            ]);
            if (!empty($storeResult['errors'])) {
                DatabaseModel::rollbackTransaction();
                $errors[] = $storeResult['errors'];
                return ['errors' => $errors];
            }

            $id = AcknowledgementReceiptModel::create([
                'resId'             => $args['resId'],
                'type'              => $templateAttachmentType,
                'format'            => $format,
                'userId'            => $currentUser['id'],
                'contactId'         => $contactToProcess,
                'docserverId'       => 'ACKNOWLEDGEMENT_RECEIPTS',
                'path'              => $storeResult['directory'],
                'filename'          => $storeResult['file_destination_name'],
                'fingerprint'       => $storeResult['fingerPrint'],
                'cc'                => !empty($args['data']['cc']) ? json_encode($args['data']['cc']) : '[]',
                'cci'               => !empty($args['data']['cci']) ? json_encode($args['data']['cci']) : '[]'
            ]);

            if (!empty($contact['email'])) {
                $emailsToSend[] = ['id' => $id, 'email' => $contact['email'], 'encodedHtml' => $mergedDocument['encodedDocument']];
            }
            if ($format == 'pdf') {
                $ids[] = $id;
            }
        }
        DatabaseModel::commitTransaction();

        if (!empty($emailsToSend) && !empty($resource['destination'])) {
            $entity = EntityModel::getByEntityId(['entityId' => $resource['destination'], 'select' => ['email', 'id']]);
        }

        $emailSender = [];
        if (!empty($args['data']['sender'])) {
            $emailSender = $args['data']['sender'];
        } elseif (!empty($acknowledgementOptions)) {
            if ($acknowledgementOptions['acknowledgementReceiptFrom'] == 'user') {
                $emailSender = ['email' => $currentUser['mail']];
            } elseif ($acknowledgementOptions['acknowledgementReceiptFrom'] == 'destination' && !empty($entity['email'])) {
                $emailSender = ['email' => $entity['email'], 'entityId' => $entity['id']];
            } elseif ($acknowledgementOptions['acknowledgementReceiptFrom'] == 'mailServer') {
                $configuration = ConfigurationModel::getByPrivilege(['privilege' => 'admin_email_server', 'select' => ['value']]);
                $configuration = json_decode($configuration['value'], true);
                $emailSender = ['email' => $configuration['from']];
            } elseif ($acknowledgementOptions['acknowledgementReceiptFrom'] == 'manual') {
                $emailSender = ['email' => $acknowledgementOptions['acknowledgementReceiptFromMail']];
            }
        } else {
            if (empty($entity['email']) || !PrivilegeController::hasPrivilege(['privilegeId' => 'use_mail_services', 'userId' => $currentUser['id']])) {
                $emailSender = ['email' => $currentUser['mail']];
            } else {
                $availableEmails = EmailController::getAvailableEmailsByUserId(['userId' => $currentUser['id']]);
                $entities = array_column($availableEmails, 'entityId');

                if (!in_array($entity['id'], $entities)) {
                    $emailSender = ['email' => $currentUser['mail']];
                } else {
                    $emailSender = ['email' => $entity['email'], 'entityId' => $entity['id']];
                }
            }
        }

        foreach ($emailsToSend as $email) {
            $isSent = EmailController::createEmail([
                'userId'    => $currentUser['id'],
                'data'      => [
                    'sender'        => $emailSender,
                    'recipients'    => [$email['email']],
                    'object'        => substr($subjectToSend, 0, 100),
                    'body'          => base64_decode($email['encodedHtml']),
                    'document'      => ['id' => $args['resId'], 'isLinked' => false, 'original' => true],
                    'isHtml'        => true,
                    'status'        => 'TO_SEND',
                    'cc'            => !empty($args['data']['cc']) ? array_column($args['data']['cc'], 'email') : '[]',
                    'cci'           => !empty($args['data']['cci']) ? array_column($args['data']['cci'], 'email') : '[]'
                ],
                'options'   => [
                    'acknowledgementReceiptId' => $email['id']
                ]
            ]);

            if (!empty($isSent['errors'])) {
                $errors[] = "Send Email error AR {$email['id']}: {$isSent['errors']}";
            }
        }

        return ['data' => $ids, 'errors' => $errors];
    }
}
