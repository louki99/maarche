<?php

/**
* Copyright Maarch since 2008 under licence GPLv3.
* See LICENCE.txt file at the root folder for more details.
* This file is part of Maarch software.
*
*/

/**
* @brief Send Message Exchange Review Controller
* @author dev@maarch.org
*/

namespace MessageExchange\controllers;

use Attachment\models\AttachmentModel;
use Attachment\models\AttachmentTypeModel;
use Contact\models\ContactModel;
use Docserver\models\DocserverModel;
use Doctype\models\DoctypeModel;
use Entity\models\EntityModel;
use ExportSeda\controllers\SendMessageController;
use Group\controllers\PrivilegeController;
use History\controllers\HistoryController;
use MessageExchange\models\MessageExchangeModel;
use Note\models\NoteModel;
use Resource\controllers\ResController;
use Resource\models\ResModel;
use Respect\Validation\Validator;
use SrcCore\models\PasswordModel;
use SrcCore\models\TextFormatModel;
use Status\models\StatusModel;
use User\models\UserModel;
use Slim\Psr7\Request;
use SrcCore\http\Response;

class SendMessageExchangeController
{
    public function getInitialization(Request $request, Response $response)
    {
        $rawEntities = EntityModel::getWithUserEntities([
            'select' => ['entities.id', 'entities.entity_label', 'entities.business_id'],
            'where' => ['users_entities.user_id = ?', 'business_id is not null', 'business_id != ?'],
            'data'  => [$GLOBALS['id'], '']
        ]);

        $entities = [];
        foreach ($rawEntities as $entity) {
            $entities[] = [
                'id'    => $entity['id'],
                'label' => $entity['entity_label'],
                'm2m'   => $entity['business_id']
            ];
        }

        return $response->withJson(['entities' => $entities]);
    }

    public static function saveMessageExchange($aArgs = [])
    {
        $dataObject = $aArgs['dataObject'];
        $oData                                        = new \stdClass();
        $oData->messageId                             = MessageExchangeModel::generateUniqueId();
        $oData->date                                  = $dataObject->Date;

        $oData->MessageIdentifier                     = new \stdClass();
        $oData->MessageIdentifier->value              = $dataObject->MessageIdentifier->value;
        
        $oData->TransferringAgency                    = new \stdClass();
        $oData->TransferringAgency->Identifier        = new \stdClass();
        $oData->TransferringAgency->Identifier->value = $dataObject->TransferringAgency->Identifier->value;
        
        $oData->ArchivalAgency                        = new \stdClass();
        $oData->ArchivalAgency->Identifier            = new \stdClass();
        $oData->ArchivalAgency->Identifier->value     = $dataObject->ArchivalAgency->Identifier->value;
        
        $oData->archivalAgreement                     = new \stdClass();
        $oData->archivalAgreement->value              = "";
        
        $replyCode = "";
        if (!empty($dataObject->ReplyCode)) {
            $replyCode = $dataObject->ReplyCode;
        }

        $oData->replyCode                             = new \stdClass();
        $oData->replyCode                             = $replyCode;

        $dataObject = self::cleanBase64Value(['dataObject' => $dataObject]);

        $aDataExtension = [
            'status'            => 'W',
            'fullMessageObject' => $dataObject,
            'resIdMaster'       => $aArgs['res_id_master'],
            'SenderOrgNAme'     => $dataObject->TransferringAgency->OrganizationDescriptiveMetadata->Contact[0]->DepartmentName,
            'RecipientOrgNAme'  => $dataObject->ArchivalAgency->OrganizationDescriptiveMetadata->Name,
            'filePath'          => $aArgs['file_path'],
        ];

        $user = UserModel::getByLogin(['login' => $aArgs['userId'], 'select' => ['id']]);
        $messageId = MessageExchangeModel::insertMessage([
            "data"          => $oData,
            "type"          => $aArgs['type'],
            "dataExtension" => $aDataExtension,
            "userId"        => $user['id']
        ]);

        return $messageId;
    }

    protected static function cleanBase64Value($aArgs = [])
    {
        $dataObject = $aArgs['dataObject'];
        $aCleanDataObject = [];
        if (!empty($dataObject->DataObjectPackage->BinaryDataObject)) {
            foreach ($dataObject->DataObjectPackage->BinaryDataObject as $key => $value) {
                $value->Attachment->value = "";
                $aCleanDataObject[$key] = $value;
            }
            $dataObject->DataObjectPackage->BinaryDataObject = $aCleanDataObject;
        }
        return $dataObject;
    }


    public static function createMessageExchange(Request $request, Response $response, array $args)
    {
        if (!PrivilegeController::hasPrivilege(['privilegeId' => 'manage_numeric_package', 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }

        if (!Validator::intVal()->validate($args['resId']) || !ResController::hasRightByResId(['resId' => [$args['resId']], 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Document out of perimeter']);
        }

        $body = $request->getParsedBody();
        $errors = self::control($body);

        if (!empty($errors)) {
            return $response->withStatus(400)->withJson(['errors' => $errors]);
        }

        /***************** GET MAIL INFOS *****************/
        $AllUserEntities = EntityModel::getWithUserEntities(['where' => ['user_id = ?', 'business_id != \'\''], 'data' => [$GLOBALS['id']]]);

        foreach ($AllUserEntities as $value) {
            if ($value['id'] == $body['senderEmail']) {
                $TransferringAgencyInformations = $value;
                break;
            }
        }

        if (empty($TransferringAgencyInformations)) {
            return $response->withStatus(400)->withJson(['errors' => "no sender"]);
        }

        $AllInfoMainMail = ResModel::getById(['select' => ['*'], 'resId' => $args['resId']]);
        if (!empty($AllInfoMainMail['type_id'])) {
            $doctype = DoctypeModel::getById(['select' => ['description'], 'id' => $AllInfoMainMail['type_id']]);
        }

        $tmpMainExchangeDoc = explode("__", $body['mainExchangeDoc']);
        $MainExchangeDoc    = ['tablename' => $tmpMainExchangeDoc[0], 'res_id' => $tmpMainExchangeDoc[1]];

        $fileInfo = [];
        if (!empty($body['joinFile']) || $MainExchangeDoc['tablename'] == 'res_letterbox') {
            $AllInfoMainMail['Title']                                  = $AllInfoMainMail['subject'];
            $AllInfoMainMail['OriginatingAgencyArchiveUnitIdentifier'] = $AllInfoMainMail['alt_identifier'];
            $AllInfoMainMail['DocumentType']                           = $doctype['description'] ?? null;
            $AllInfoMainMail['tablenameExchangeMessage']               = 'res_letterbox';
            $fileInfo = [$AllInfoMainMail];
        }

        if ($MainExchangeDoc['tablename'] == 'res_attachments') {
            $body['joinAttachment'][] = $MainExchangeDoc['res_id'];
        }

        /**************** GET ATTACHMENTS INFOS ***************/
        $AttachmentsInfo = [];
        if (!empty($body['joinAttachment'])) {
            $AttachmentsInfo = AttachmentModel::get(['select' => ['*'], 'where' => ['res_id in (?)'], 'data' => [$body['joinAttachment']]]);
            $attachmentTypes = AttachmentTypeModel::get(['select' => ['type_id', 'label']]);
            $attachmentTypes = array_column($attachmentTypes, 'label', 'type_id');
            foreach ($AttachmentsInfo as $key => $value) {
                $AttachmentsInfo[$key]['Title']                                  = $value['title'];
                $AttachmentsInfo[$key]['OriginatingAgencyArchiveUnitIdentifier'] = $value['identifier'];
                $AttachmentsInfo[$key]['DocumentType']                           = $attachmentTypes[$value['attachment_type']];
                $AttachmentsInfo[$key]['tablenameExchangeMessage']               = 'res_attachments';
            }
        }
        $aAllAttachment = $AttachmentsInfo;

        /******************* GET NOTE INFOS **********************/
        $aComments = self::generateComments([
            'resId' => $args['resId'],
            'notes' => $body['notes'],
            'body'  => $body['content'],
            'TransferringAgencyInformations' => $TransferringAgencyInformations]);

        /*********** ORDER ATTACHMENTS IN MAIL ***************/
        if ($MainExchangeDoc['tablename'] == 'res_letterbox') {
            $mainDocument     = $fileInfo;
            $aMergeAttachment = array_merge($fileInfo, $aAllAttachment);
        } else {
            foreach ($aAllAttachment as $key => $value) {
                if ($value['res_id'] == $MainExchangeDoc['res_id'] && $MainExchangeDoc['tablename'] == $value['tablenameExchangeMessage']) {
                    if ($AllInfoMainMail['category_id'] == 'outgoing') {
                        $aOutgoingMailInfo                                           = $AllInfoMainMail;
                        $aOutgoingMailInfo['Title']                                  = $AllInfoMainMail['subject'];
                        $aOutgoingMailInfo['OriginatingAgencyArchiveUnitIdentifier'] = $AllInfoMainMail['alt_identifier'];
                        $aOutgoingMailInfo['DocumentType']                           = $AllInfoMainMail['type_label'];
                        $aOutgoingMailInfo['tablenameExchangeMessage']               = $AllInfoMainMail['tablenameExchangeMessage'];
                        $mainDocument = [$aOutgoingMailInfo];
                    } else {
                        $mainDocument = [$aAllAttachment[$key]];
                    }
                    $firstAttachment = [$aAllAttachment[$key]];
                    unset($aAllAttachment[$key]);
                }
            }
            if (!empty($fileInfo[0]['filename'])) {
                $aMergeAttachment = array_merge($firstAttachment, $fileInfo, $aAllAttachment);
            } else {
                $aMergeAttachment = array_merge($firstAttachment, $aAllAttachment);
            }
        }

        $mainDocument[0]['Title'] = '[CAPTUREM2M]'.$body['object'];

        foreach ($body['contacts'] as $contactId) {
            /******** GET ARCHIVAl INFORMATIONs **************/
            $communicationType   = ContactModel::getById(['select' => ['communication_means'], 'id' => $contactId]);
            $aArchivalAgencyCommunicationType = json_decode($communicationType['communication_means'], true);
            if (!empty($aArchivalAgencyCommunicationType)) {
                if (!empty($aArchivalAgencyCommunicationType['email'])) {
                    $ArchivalAgencyCommunicationType['type'] = 'email';
                    $ArchivalAgencyCommunicationType['value'] = $aArchivalAgencyCommunicationType['email'];
                } else {
                    $ArchivalAgencyCommunicationType['type'] = 'url';
                    $ArchivalAgencyCommunicationType['value'] = rtrim($aArchivalAgencyCommunicationType['url'], "/");
                    if (strrpos($ArchivalAgencyCommunicationType['value'], "http://") !== false) {
                        $prefix = "http://";
                    } elseif (strrpos($ArchivalAgencyCommunicationType['value'], "https://") !== false) {
                        $prefix = "https://";
                    } else {
                        return $response->withStatus(403)->withJson(['errors' => 'http or https missing']);
                    }
                    $url = str_replace($prefix, '', $ArchivalAgencyCommunicationType['value']);
                    $login = $aArchivalAgencyCommunicationType['login'] ?? '';
                    $password = !empty($aArchivalAgencyCommunicationType['password']) ? PasswordModel::decrypt(['cryptedPassword' => $aArchivalAgencyCommunicationType['password']]) : '';
                    $ArchivalAgencyCommunicationType['value'] = $prefix;
                    if (!empty($login) && !empty($password)) {
                        $ArchivalAgencyCommunicationType['value'] .= $login . ':' . $password . '@';
                    }
                    $ArchivalAgencyCommunicationType['value'] .= $url;
                }
            }
            $ArchivalAgencyContactInformations = ContactModel::getById(['select' => ['*'], 'id' => $contactId]);

            /******** GENERATE MESSAGE EXCHANGE OBJECT *********/
            $dataObject = self::generateMessageObject([
                'Comment' => $aComments,
                'ArchivalAgency' => [
                    'CommunicationType'   => $ArchivalAgencyCommunicationType,
                    'ContactInformations' => $ArchivalAgencyContactInformations
                ],
                'TransferringAgency' => [
                    'EntitiesInformations' => $TransferringAgencyInformations
                ],
                'attachment'            => $aMergeAttachment,
                'res'                   => $mainDocument,
                'mainExchangeDocument'  => $MainExchangeDoc
            ]);
            /******** GENERATION DU BORDEREAU */
            $filePath = SendMessageController::generateMessageFile(['messageObject' => $dataObject, 'type' => 'ArchiveTransfer']);

            /******** SAVE MESSAGE *********/
            $messageExchangeReturn = self::saveMessageExchange(['dataObject' => $dataObject, 'res_id_master' => $args['resId'], 'file_path' => $filePath, 'type' => 'ArchiveTransfer', 'userId' => $GLOBALS['login']]);
            if (!empty($messageExchangeReturn['error'])) {
                return $response->withStatus(400)->withJson(['errors' => $messageExchangeReturn['error']]);
            } else {
                $messageId = $messageExchangeReturn['messageId'];
            }
            self::saveUnitIdentifier(['attachment' => $aMergeAttachment, 'notes' => $body['notes'], 'messageId' => $messageId]);

            HistoryController::add([
                'tableName' => 'res_letterbox',
                'recordId'  => $args['resId'],
                'eventType' => 'UP',
                'eventId'   => 'resup',
                'info'       => _NUMERIC_PACKAGE_ADDED . _ON_DOC_NUM
                    . $args['resId'] . ' ('.$messageId.') : "' . TextFormatModel::cutString(['string' => $mainDocument[0]['Title'], 'max' => 254])
            ]);

            HistoryController::add([
                'tableName' => 'message_exchange',
                'recordId'  => $messageId,
                'eventType' => 'ADD',
                'eventId'   => 'messageexchangeadd',
                'info'       => _NUMERIC_PACKAGE_ADDED . ' (' . $messageId . ')'
            ]);

            /******** ENVOI *******/
            $res = SendMessageController::send($dataObject, $messageId, 'ArchiveTransfer');

            if ($res['status'] == 1) {
                $errors = [];
                array_push($errors, "L'envoi a échoué");
                array_push($errors, $res['content']);
                return $response->withStatus(400)->withJson(['errors' => $errors]);
            }
        }

        return $response->withStatus(200);
    }

    protected static function control($aArgs)
    {
        $errors = [];

        if (empty($aArgs['mainExchangeDoc'])) {
            array_push($errors, 'wrong format for mainExchangeDoc');
        }

        if (empty($aArgs['object'])) {
            array_push($errors, 'Body object is empty');
        }

        if (empty($aArgs['joinFile']) && empty($aArgs['joinAttachment']) && empty($aArgs['mainExchangeDoc'])) {
            array_push($errors, 'no attachment');
        }

        if (empty($aArgs['contacts']) || !is_array($aArgs['contacts'])) {
            $errors[] = 'Body contacts is empty or not an array';
        }
        foreach ($aArgs['contacts'] as $key => $contact) {
            if (empty($contact)) {
                $errors[] = "Body contacts[{$key}] is empty";
                break;
            }
        }

        if (empty($aArgs['senderEmail'])) {
            array_push($errors, 'Body senderEmail is empty');
        }

        return $errors;
    }

    protected static function generateComments($aArgs = [])
    {
        $aReturn    = [];

        $oBody = new \stdClass();
        if (!empty($aArgs['body'])) {
            $entityRoot = EntityModel::getEntityRootById(['entityId' => $aArgs['TransferringAgencyInformations']['entity_id']]);
            $userInfo = UserModel::getById(['id' => $GLOBALS['id'], 'select' => ['firstname', 'lastname', 'mail']]);
            $headerNote = $userInfo['firstname'] . ' ' . $userInfo['lastname'] . ' (' . $entityRoot['entity_label'] . ' - ' . $aArgs['TransferringAgencyInformations']['entity_label'] . ' - ' .$userInfo['mail'].') : ';
            $oBody->value = $headerNote . ' ' . $aArgs['body'];
        } else {
            $oBody->value = '';
        }
        array_push($aReturn, $oBody);

        if (!empty($aArgs['notes'])) {
            $notes = NoteModel::getByUserIdForResource([
                'select' => ['id', 'user_id', 'creation_date', 'note_text'],
                'resId'  => $aArgs['resId'],
                'userId' => $GLOBALS['id']
            ]);

            if (!empty($notes)) {
                foreach ($notes as $value) {
                    if (!in_array($value['id'], $aArgs['notes'])) {
                        continue;
                    }

                    $oComment        = new \stdClass();
                    $date            = new \DateTime($value['creation_date']);
                    $additionalUserInfos = '';
                    $userInfo = UserModel::getPrimaryEntityById([
                        'select' => ['users.firstname', 'users.lastname', 'entities.entity_id', 'entities.entity_label'],
                        'id'     => $GLOBALS['id']
                    ]);
                    if (!empty($userInfo['entity_id'])) {
                        $entityRoot          = EntityModel::getEntityRootById(['entityId' => $userInfo['entity_id']]);
                        $additionalUserInfos = ' ('.$entityRoot['entity_label'].' - '.$userInfo['entity_label'].')';
                    }
                    $oComment->value = $userInfo['firstname'].' '.$userInfo['lastname'].' - '.$date->format('d-m-Y H:i:s'). $additionalUserInfos . ' : '.$value['note_text'];
                    array_push($aReturn, $oComment);
                }
            }
        }
        return $aReturn;
    }

    public static function generateMessageObject($aArgs = [])
    {
        $date = new \DateTime;

        $messageObject          = new \stdClass();
        $messageObject->Comment = $aArgs['Comment'];
        $messageObject->Date    = $date->format(\DateTime::ATOM);

        $messageObject->MessageIdentifier = new \stdClass();
        $messageObject->MessageIdentifier->value = 'ArchiveTransfer_'.date("Ymd_His").'_'.$GLOBALS['login'];

        /********* BINARY DATA OBJECT PACKAGE *********/
        $messageObject->DataObjectPackage                   = new \stdClass();
        $messageObject->DataObjectPackage->BinaryDataObject = self::getBinaryDataObject($aArgs['attachment']);

        /********* DESCRIPTIVE META DATA *********/
        $messageObject->DataObjectPackage->DescriptiveMetadata = self::getDescriptiveMetaDataObject($aArgs);

        /********* ARCHIVAL AGENCY *********/
        $messageObject->ArchivalAgency = self::getArchivalAgencyObject(['ArchivalAgency' => $aArgs['ArchivalAgency']]);

        /********* TRANSFERRING AGENCY *********/
        $channelType = $messageObject->ArchivalAgency->OrganizationDescriptiveMetadata->Communication[0]->Channel;
        $messageObject->TransferringAgency = self::getTransferringAgencyObject(['TransferringAgency' => $aArgs['TransferringAgency'], 'ChannelType' => $channelType]);

        return $messageObject;
    }

    public static function getBinaryDataObject($aArgs = [])
    {
        $aReturn     = [];

        foreach ($aArgs as $key => $value) {
            if (!empty($value['filename'])) {
                if (!empty($value['tablenameExchangeMessage'])) {
                    $binaryDataObjectId = $value['tablenameExchangeMessage'] . "_" . $key . "_" . $value['res_id'];
                } else {
                    $binaryDataObjectId = $value['res_id'];
                }
    
                $binaryDataObject                           = new \stdClass();
                $binaryDataObject->id                       = $binaryDataObjectId;
    
                $binaryDataObject->MessageDigest            = new \stdClass();
                $binaryDataObject->MessageDigest->value     = $value['fingerprint'];
                $binaryDataObject->MessageDigest->algorithm = "sha256";
    
                $binaryDataObject->Size                     = $value['filesize'];
    
                $uri = str_replace("##", DIRECTORY_SEPARATOR, $value['path']);
                $uri = str_replace("#", DIRECTORY_SEPARATOR, $uri);
                
                $docServers = DocserverModel::getByDocserverId(['docserverId' => $value['docserver_id']]);
                $binaryDataObject->Attachment           = new \stdClass();
                $binaryDataObject->Attachment->uri      = '';
                $binaryDataObject->Attachment->filename = basename($value['filename']);
                $binaryDataObject->Attachment->value    = base64_encode(file_get_contents($docServers['path_template'] . $uri . '/'. $value['filename']));
    
                $binaryDataObject->FormatIdentification           = new \stdClass();
                $binaryDataObject->FormatIdentification->MimeType = mime_content_type($docServers['path_template'] . $uri . $value['filename']);
    
                array_push($aReturn, $binaryDataObject);
            }
        }

        return $aReturn;
    }

    public static function getDescriptiveMetaDataObject($aArgs = [])
    {
        $DescriptiveMetadataObject              = new \stdClass();
        $DescriptiveMetadataObject->ArchiveUnit = [];

        $documentArchiveUnit                    = new \stdClass();
        $documentArchiveUnit->id                = 'mail_1';

        $documentArchiveUnit->Content = self::getContent([
            'DescriptionLevel'                       => 'File',
            'Title'                                  => $aArgs['res'][0]['Title'],
            'OriginatingSystemId'                    => $aArgs['res'][0]['res_id'],
            'OriginatingAgencyArchiveUnitIdentifier' => $aArgs['res'][0]['OriginatingAgencyArchiveUnitIdentifier'],
            'DocumentType'                           => $aArgs['res'][0]['DocumentType'],
            'Status'                                 => $aArgs['res'][0]['status'],
            'Writer'                                 => $aArgs['res'][0]['typist'],
            'CreatedDate'                            => $aArgs['res'][0]['creation_date'],
        ]);

        $documentArchiveUnit->ArchiveUnit = [];
        foreach ($aArgs['attachment'] as $key => $value) {
            $attachmentArchiveUnit     = new \stdClass();
            $attachmentArchiveUnit->id = 'archiveUnit_'.$value['tablenameExchangeMessage'] . "_" . $key . "_" . $value['res_id'];
            $attachmentArchiveUnit->Content = self::getContent([
                'DescriptionLevel'                       => 'Item',
                'Title'                                  => $value['Title'],
                'OriginatingSystemId'                    => $value['res_id'],
                'OriginatingAgencyArchiveUnitIdentifier' => $value['OriginatingAgencyArchiveUnitIdentifier'],
                'DocumentType'                           => $value['DocumentType'],
                'Status'                                 => $value['status'],
                'Writer'                                 => $value['typist'],
                'CreatedDate'                            => $value['creation_date'],
            ]);
            $dataObjectReference                        = new \stdClass();
            $dataObjectReference->DataObjectReferenceId = $value['tablenameExchangeMessage'].'_'.$key.'_'.$value['res_id'];
            $attachmentArchiveUnit->DataObjectReference = [$dataObjectReference];

            array_push($documentArchiveUnit->ArchiveUnit, $attachmentArchiveUnit);
        }
        array_push($DescriptiveMetadataObject->ArchiveUnit, $documentArchiveUnit);

        return $DescriptiveMetadataObject;
    }

    public static function getContent($aArgs = [])
    {
        $contentObject                                         = new \stdClass();
        $contentObject->DescriptionLevel                       = $aArgs['DescriptionLevel'];
        $contentObject->Title                                  = [$aArgs['Title']];
        $contentObject->OriginatingSystemId                    = $aArgs['OriginatingSystemId'];
        $contentObject->OriginatingAgencyArchiveUnitIdentifier = $aArgs['OriginatingAgencyArchiveUnitIdentifier'];
        $contentObject->DocumentType                           = $aArgs['DocumentType'];
        $contentObject->Status                                 = StatusModel::getById(['id' => $aArgs['Status']])['label_status'];

        if (!empty($aArgs['Writer'])) {
            if (is_numeric($aArgs['Writer'])) {
                $userInfos = UserModel::getById(['id' => $aArgs['Writer'], 'select' => ['firstname', 'lastname']]);
            } else {
                $userInfos = UserModel::getByLogin(['login' => $aArgs['Writer'], 'select' => ['firstname', 'lastname']]);
            }
        }

        $writer                = new \stdClass();
        $writer->FirstName     = $userInfos['firstname'];
        $writer->BirthName     = $userInfos['lastname'];
        $contentObject->Writer = [$writer];

        $contentObject->CreatedDate = date("Y-m-d", strtotime($aArgs['CreatedDate']));

        return $contentObject;
    }

    public static function getArchivalAgencyObject($aArgs = [])
    {
        $archivalAgencyObject                    = new \stdClass();
        $archivalAgencyObject->Identifier        = new \stdClass();
        $externalId = json_decode($aArgs['ArchivalAgency']['ContactInformations']['external_id'], true);
        $archivalAgencyObject->Identifier->value = $externalId['m2m'];

        $archivalAgencyObject->OrganizationDescriptiveMetadata       = new \stdClass();
        $archivalAgencyObject->OrganizationDescriptiveMetadata->Name = trim($aArgs['ArchivalAgency']['ContactInformations']['company'] . ' ' . $aArgs['ArchivalAgency']['ContactInformations']['lastname'] . ' ' . $aArgs['ArchivalAgency']['ContactInformations']['firstname']);

        if (isset($aArgs['ArchivalAgency']['CommunicationType']['type'])) {
            $arcCommunicationObject          = new \stdClass();
            $arcCommunicationObject->Channel = $aArgs['ArchivalAgency']['CommunicationType']['type'];
            if ($aArgs['ArchivalAgency']['CommunicationType']['type'] == 'url') {
                $postUrl = '/rest/saveNumericPackage';
            }
            $arcCommunicationObject->value   = $aArgs['ArchivalAgency']['CommunicationType']['value'].$postUrl;

            $archivalAgencyObject->OrganizationDescriptiveMetadata->Communication = [$arcCommunicationObject];
        }

        $contactObject = new \stdClass();
        $contactObject->DepartmentName = $aArgs['ArchivalAgency']['ContactInformations']['department'];
        $contactObject->PersonName     = $aArgs['ArchivalAgency']['ContactInformations']['lastname'] . " " . $aArgs['ArchivalAgency']['ContactInformations']['firstname'];

        $addressObject = new \stdClass();
        $addressObject->CityName      = $aArgs['ArchivalAgency']['ContactInformations']['address_town'];
        $addressObject->Country       = $aArgs['ArchivalAgency']['ContactInformations']['address_country'];
        $addressObject->Postcode      = $aArgs['ArchivalAgency']['ContactInformations']['address_postcode'];
        $addressObject->PostOfficeBox = $aArgs['ArchivalAgency']['ContactInformations']['address_number'];
        $addressObject->StreetName    = $aArgs['ArchivalAgency']['ContactInformations']['address_street'];

        $contactObject->Address = [$addressObject];

        $communicationContactPhoneObject          = new \stdClass();
        $communicationContactPhoneObject->Channel = 'phone';
        $communicationContactPhoneObject->value   = $aArgs['ArchivalAgency']['ContactInformations']['phone'];

        $communicationContactEmailObject          = new \stdClass();
        $communicationContactEmailObject->Channel = 'email';
        $communicationContactEmailObject->value   = $aArgs['ArchivalAgency']['ContactInformations']['email'];

        $contactObject->Communication = [$communicationContactPhoneObject, $communicationContactEmailObject];

        $archivalAgencyObject->OrganizationDescriptiveMetadata->Contact = [$contactObject];

        return $archivalAgencyObject;
    }

    public static function getTransferringAgencyObject($aArgs = [])
    {
        $TransferringAgencyObject                    = new \stdClass();
        $TransferringAgencyObject->Identifier        = new \stdClass();
        $TransferringAgencyObject->Identifier->value = $aArgs['TransferringAgency']['EntitiesInformations']['business_id'];

        $TransferringAgencyObject->OrganizationDescriptiveMetadata = new \stdClass();

        $entityRoot = EntityModel::getEntityRootById(['entityId' => $aArgs['TransferringAgency']['EntitiesInformations']['entity_id']]);
        $TransferringAgencyObject->OrganizationDescriptiveMetadata->LegalClassification = $entityRoot['entity_label'];
        $TransferringAgencyObject->OrganizationDescriptiveMetadata->Name                = $aArgs['TransferringAgency']['EntitiesInformations']['entity_label'];
        $TransferringAgencyObject->OrganizationDescriptiveMetadata->UserIdentifier      = $GLOBALS['login'];

        $traCommunicationObject = new \stdClass();

        $aDefaultConfig = ReceiveMessageExchangeController::readXmlConfig();

        // If communication_type is an url, and there is a separate field for login and password, we recreate the url with the login and password
        if (filter_var($aDefaultConfig['m2m_communication_type'][$aArgs['ChannelType']], FILTER_VALIDATE_URL)) {
            if (!empty($aDefaultConfig['m2m_login']) && !empty($aDefaultConfig['m2m_password'])) {
                $prefix = '';
                if (strrpos($aDefaultConfig['m2m_communication_type'][$aArgs['ChannelType']], "http://") !== false) {
                    $prefix = "http://";
                } elseif (strrpos($aDefaultConfig['m2m_communication_type'][$aArgs['ChannelType']], "https://") !== false) {
                    $prefix = "https://";
                }
                $url = str_replace($prefix, '', $aDefaultConfig['m2m_communication_type'][$aArgs['ChannelType']]);
                $login = $aDefaultConfig['m2m_login'][0] ?? '';
                $password = $aDefaultConfig['m2m_password'][0] ?? '';
                $aDefaultConfig['m2m_communication_type'][$aArgs['ChannelType']] = $prefix . $login . ':' . $password . '@' . $url;
            }
        }

        $traCommunicationObject->Channel = $aArgs['ChannelType'];
        $traCommunicationObject->value   = rtrim($aDefaultConfig['m2m_communication_type'][$aArgs['ChannelType']], "/");

        $TransferringAgencyObject->OrganizationDescriptiveMetadata->Communication = [$traCommunicationObject];

        $userInfo = UserModel::getById(['id' => $GLOBALS['id'], 'select' => ['firstname', 'lastname', 'mail', 'phone']]);

        $contactUserObject                 = new \stdClass();
        $contactUserObject->DepartmentName = $aArgs['TransferringAgency']['EntitiesInformations']['entity_label'];
        $contactUserObject->PersonName     = $userInfo['firstname'] . " " . $userInfo['lastname'];

        $communicationUserPhoneObject          = new \stdClass();
        $communicationUserPhoneObject->Channel = 'phone';
        $communicationUserPhoneObject->value   = $userInfo['phone'];

        $communicationUserEmailObject          = new \stdClass();
        $communicationUserEmailObject->Channel = 'email';
        $communicationUserEmailObject->value   = $userInfo['mail'];

        $contactUserObject->Communication = [$communicationUserPhoneObject, $communicationUserEmailObject];

        $TransferringAgencyObject->OrganizationDescriptiveMetadata->Contact = [$contactUserObject];

        return $TransferringAgencyObject;
    }

    public static function saveUnitIdentifier($aArgs = [])
    {
        foreach ($aArgs['attachment'] as $key => $value) {
            $disposition = "attachment";
            if ($key == 0) {
                $disposition = "body";
            }

            MessageExchangeModel::insertUnitIdentifier([
                'messageId'   => $aArgs['messageId'],
                'tableName'   => $value['tablenameExchangeMessage'],
                'resId'       => $value['res_id'],
                'disposition' => $disposition
            ]);
        }

        if (!empty($aArgs['notes'])) {
            foreach ($aArgs['notes'] as $value) {
                MessageExchangeModel::insertUnitIdentifier([
                    'messageId'   => $aArgs['messageId'],
                    'tableName'   => "notes",
                    'resId'       => $value,
                    'disposition' => "note"
                ]);
            }
        }

        return true;
    }
}
