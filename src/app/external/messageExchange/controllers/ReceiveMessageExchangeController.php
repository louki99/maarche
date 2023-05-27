<?php

/**
* Copyright Maarch since 2008 under licence GPLv3.
* See LICENCE.txt file at the root folder for more details.
* This file is part of Maarch software.
*
*/

/**
* @brief Receive Message Exchange Controller
* @author dev@maarch.org
* @ingroup core
*/

namespace MessageExchange\controllers;

use Basket\models\BasketModel;
use Contact\models\ContactModel;
use Convert\controllers\ConvertPdfController;
use Entity\models\EntityModel;
use ExportSeda\controllers\SendMessageController;
use Group\controllers\PrivilegeController;
use History\controllers\HistoryController;
use MessageExchange\models\MessageExchangeModel;
use Note\models\NoteModel;
use Resource\controllers\StoreController;
use Resource\models\ResModel;
use Resource\models\ResourceContactModel;
use Slim\Psr7\Request;
use SrcCore\http\Response;
use SrcCore\controllers\CoreController;
use SrcCore\models\CoreConfigModel;
use User\models\UserModel;

require_once 'modules/export_seda/Controllers/ReceiveMessage.php';

class ReceiveMessageExchangeController
{
    private static $aComments = [];

    public function saveMessageExchange(Request $request, Response $response)
    {
        if (!PrivilegeController::hasPrivilege(['privilegeId' => 'manage_numeric_package', 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }

        $data = $request->getParsedBody();

        $this->addComment('['.date("d/m/Y H:i:s") . '] Réception du pli numérique');
        $tmpName = self::createFile(['base64' => $data['base64'], 'extension' => $data['extension'], 'size' => $data['size']]);
        if (!empty($tmpName['errors'])) {
            return $response->withStatus(400)->withJson($tmpName);
        }
        $this->addComment('['.date("d/m/Y H:i:s") . '] Pli numérique déposé sur le serveur');
        $this->addComment('['.date("d/m/Y H:i:s") . '] Validation du pli numérique');
        /********** EXTRACTION DU ZIP ET CONTROLE *******/
        $receiveMessage = new \ReceiveMessage();
        $tmpPath = CoreConfigModel::getTmpPath();
        $res = $receiveMessage->receive($tmpPath, $tmpName, 'ArchiveTransfer');

        if ($res['status'] == 1) {
            return $response->withStatus(400)->withJson(["errors" => 'Reception error : ' . $res['content']]);
        }
        self::$aComments[] = '['.date("d/m/Y H:i:s") . '] Pli numérique validé';

        $sDataObject = $res['content'];
        $sDataObject = json_decode($sDataObject);

        $acknowledgementReturn = self::sendAcknowledgement(["dataObject" => $sDataObject]);
        if (!empty($acknowledgementReturn['error'])) {
            return $response->withStatus(400)->withJson(["errors" => $acknowledgementReturn['error']]);
        }

        $aDefaultConfig = self::readXmlConfig();

        /*************** CONTACT **************/
        $this->addComment('['.date("d/m/Y H:i:s") . '] Selection ou création du contact');
        $contactReturn = self::saveContact(["dataObject" => $sDataObject, "defaultConfig" => $aDefaultConfig]);

        if ($contactReturn['returnCode'] <> 0) {
            return $response->withStatus(400)->withJson(["errors" => $contactReturn['errors']]);
        }
        self::$aComments[] = '['.date("d/m/Y H:i:s") . '] Contact sélectionné ou créé';

        /*************** RES LETTERBOX **************/
        $this->addComment('['.date("d/m/Y H:i:s") . '] Enregistrement du message');
        $resLetterboxReturn = self::saveResLetterbox(["dataObject" => $sDataObject, "defaultConfig" => $aDefaultConfig, "contact" => $contactReturn]);

        if (!empty($resLetterboxReturn['errors'])) {
            return $response->withStatus(400)->withJson(["errors" => $resLetterboxReturn['errors']]);
        }

        self::$aComments[] = '['.date("d/m/Y H:i:s") . '] Message enregistré';
        /************** NOTES *****************/
        $notesReturn = self::saveNotes(["dataObject" => $sDataObject, "resId" => $resLetterboxReturn, "userId" => $GLOBALS['id']]);
        if (!empty($notesReturn['errors'])) {
            return $response->withStatus(400)->withJson(["errors" => $notesReturn['errors']]);
        }
        /************** RES ATTACHMENT *****************/
        $resAttachmentReturn = self::saveResAttachment(["dataObject" => $sDataObject, "resId" => $resLetterboxReturn, "defaultConfig" => $aDefaultConfig]);

        if (!empty($resAttachmentReturn['errors'])) {
            return $response->withStatus(400)->withJson(["errors" => $resAttachmentReturn['errors']]);
        }

        HistoryController::add([
            'tableName' => 'res_letterbox',
            'recordId'  => $resLetterboxReturn,
            'eventType' => 'ADD',
            'eventId'   => 'resadd',
            'info'      => _NUMERIC_PACKAGE_IMPORTED
        ]);

        $basketRedirection = null;
        $userBaskets = BasketModel::getBasketsByLogin(['login' => $GLOBALS['login']]);
        if (!empty($userBaskets)) {
            foreach ($userBaskets as $value) {
                if ($value['basket_id'] == $aDefaultConfig['basketRedirection_afterUpload'][0]) {
                    $userGroups = UserModel::getGroupsById(['id' => $GLOBALS['id']]);
                    $basketRedirection = 'index.php#/basketList/users/'.$GLOBALS['id'].'/groups/'.$userGroups[0]['id'].'/baskets/'.$value['id'];
                    $resource = ResModel::getById(['id' => $resLetterboxReturn]);
                    if (!empty($resource['alt_identifier'])) {
                        $basketRedirection .= '?chrono='.$resource['alt_identifier'];
                    }
                    break;
                }
            }
        }

        if (empty($basketRedirection)) {
            $basketRedirection = 'index.php';
        }

        self::sendReply(['dataObject' => $sDataObject, 'Comment' => self::$aComments, 'replyCode' => '000 : OK', 'res_id_master' => $resLetterboxReturn, 'userId' => $GLOBALS['login']]);

        return $response->withJson([
            "resId"             => $resLetterboxReturn,
            'basketRedirection' => $basketRedirection
        ]);
    }

    public static function checkNeededParameters($aArgs = [])
    {
        foreach ($aArgs['needed'] as $value) {
            if (empty($aArgs['data'][$value])) {
                return false;
            }
        }

        return true;
    }

    public function createFile($aArgs = [])
    {
        if (!self::checkNeededParameters(['data' => $aArgs, 'needed' => ['base64', 'extension', 'size']])) {
            return ['errors' => 'Bad Request'];
        }

        $mimeAndSize = CoreController::getMimeTypeAndFileSize(['encodedFile' => $aArgs['base64']]);
        if (!empty($mimeAndSize['errors'])) {
            return ['errors' => $mimeAndSize['errors']];
        }
        $mimeType = $mimeAndSize['mime'];
        $ext      = $aArgs['extension'];
        $tmpName  = 'tmp_file_' .$GLOBALS['login']. '_ArchiveTransfer_' .rand(). '.' . $ext;

        if (!in_array(strtolower($ext), ['zip', 'tar'])) {
            return ["errors" => 'Only zip file is allowed'];
        }

        if ($mimeType != "application/x-tar" && $mimeType != "application/zip" && $mimeType != "application/tar" && $mimeType != "application/x-gzip") {
            return ['errors' => 'Filetype is not allowed'];
        }

        $file = base64_decode($aArgs['base64']);

        $tmpPath = CoreConfigModel::getTmpPath();
        file_put_contents($tmpPath . $tmpName, $file);

        return $tmpName;
    }

    public static function readXmlConfig()
    {
        $loadedXml = CoreConfigModel::getXmlLoaded(['path' => 'config/m2m_config.xml']);

        if (empty($loadedXml)) {
            return null;
        }
        $aDefaultConfig = [];
        if (!empty($loadedXml)) {
            foreach ($loadedXml as $key => $value) {
                $aDefaultConfig[$key] = (array)$value;
            }
        }

        $aDefaultConfig['m2m_communication'] = explode(",", $aDefaultConfig['m2m_communication'][0]);
        foreach ($aDefaultConfig['m2m_communication'] as $value) {
            if (filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $aDefaultConfig['m2m_communication_type']['email'] = $value;
            } elseif (filter_var($value, FILTER_VALIDATE_URL)) {
                $aDefaultConfig['m2m_communication_type']['url'] = $value;
            }
        }

        return $aDefaultConfig;
    }

    protected static function saveResLetterbox($aArgs = [])
    {
        $dataObject    = $aArgs['dataObject'];
        $defaultConfig = $aArgs['defaultConfig']['res_letterbox'];

        $DescriptiveMetadata = $dataObject->DataObjectPackage->DescriptiveMetadata->ArchiveUnit[0];

        $mainDocumentMetaData  = $DescriptiveMetadata->Content;
        $DataObjectReferenceId = $DescriptiveMetadata->ArchiveUnit[0]->DataObjectReference[0]->DataObjectReferenceId;

        $documentMetaData = self::getBinaryDataObjectInfo(['binaryDataObject' => $dataObject->DataObjectPackage->BinaryDataObject, 'binaryDataObjectId' => $DataObjectReferenceId]);

        $filename         = $documentMetaData->Attachment->filename;
        $fileFormat       = substr($filename, strrpos($filename, '.') + 1);

        $archivalAgency = $dataObject->ArchivalAgency;
        $destination    = EntityModel::getByBusinessId(['businessId' => $archivalAgency->Identifier->value]);
        $Communication  = $archivalAgency->OrganizationDescriptiveMetadata->Contact[0]->Communication;

        foreach ($Communication as $value) {
            if ($value->Channel == 'email') {
                $email = $value->value;
                break;
            }
        }

        if (!empty($email)) {
            $destUser = UserModel::getByEmail(['mail' => $email]);
        }

        $dataValue = [];
        $users = UserModel::get(['select' => ['id'], 'where' => ['mode in (?)'], 'data' => [['root_visible', 'root_invisible']], 'limit' => 1]);
        $entityId  = EntityModel::getByEntityId(['entityId' => $destination[0]['entity_id'], 'select' => ['id']]);
        $dataValue['typist']           = $users[0]['id'];
        $dataValue['doctype']          = $defaultConfig['type_id'];
        $dataValue['subject']          = str_replace("[CAPTUREM2M]", "", $mainDocumentMetaData->Title[0]);
        $dataValue['documentDate']     = $mainDocumentMetaData->CreatedDate;
        $dataValue['destination']      = $entityId['id'];
        $dataValue['initiator']        = $entityId['id'];
        $dataValue['diffusionList']    = ['id' => $destUser[0]['user_id'], 'type' => 'user', 'mode' => 'dest'];
        $dataValue['externalId']       = ['m2m' => $dataObject->MessageIdentifier->value];
        $dataValue['priority']         = $defaultConfig['priority'];
        $dataValue['confidentiality']  = false;
        $dataValue['chrono']           = true;
        $date = new \DateTime();
        $dataValue['arrivalDate']  = $date->format('d-m-Y H:i');
        $dataValue['encodedFile']  = $documentMetaData->Attachment->value;
        $dataValue['format']       = $fileFormat;
        $dataValue['status']       = $defaultConfig['status'];
        $dataValue['modelId']      = $defaultConfig['indexingModelId'];

        $storeResource = StoreController::storeResource($dataValue);
        if (empty($storeResource['errors'])) {
            if (!empty($dataValue['encodedFile'])) {
                ConvertPdfController::convert([
                    'resId'     => $storeResource,
                    'collId'    => 'letterbox_coll',
                    'version'   => 1
                ]);

                $customId = CoreConfigModel::getCustomId();
                $customId = empty($customId) ? 'null' : $customId;
                exec("php src/app/convert/scripts/FullTextScript.php --customId {$customId} --resId {$storeResource} --collId letterbox_coll --userId {$GLOBALS['id']} > /dev/null &");
            }
            ResourceContactModel::create(['res_id' => $storeResource, 'item_id' => $aArgs['contact']['id'], 'type' => 'contact', 'mode' => 'sender']);
        }

        return $storeResource;
    }

    protected static function saveContact($aArgs = [])
    {
        $dataObject                 = $aArgs['dataObject'];
        $transferringAgency         = $dataObject->TransferringAgency;
        $transferringAgencyMetadata = $transferringAgency->OrganizationDescriptiveMetadata;

        if (strrpos($transferringAgencyMetadata->Communication[0]->value, "/rest/") !== false) {
            $contactCommunicationValue = substr($transferringAgencyMetadata->Communication[0]->value, 0, strrpos($transferringAgencyMetadata->Communication[0]->value, "/rest/")+1);
        } else {
            $contactCommunicationValue = $transferringAgencyMetadata->Communication[0]->value;
        }

        if (filter_var($contactCommunicationValue, FILTER_VALIDATE_EMAIL)) {
            $aCommunicationMeans['email'] = $contactCommunicationValue;
            $whereAlreadyExist = "communication_means->>'email' = ?";
        } elseif (filter_var($contactCommunicationValue, FILTER_VALIDATE_URL)) {
            $aCommunicationMeans['url'] = $contactCommunicationValue;
            $whereAlreadyExist = "communication_means->>'url' = ?";
        }
        $dataAlreadyExist = $contactCommunicationValue;

        $contactAlreadyCreated = ContactModel::get([
            'select'    => ['id', 'communication_means'],
            'where'     => ["external_id->>'m2m' = ?", $whereAlreadyExist],
            'data'      => [$transferringAgency->Identifier->value, $dataAlreadyExist],
            'limit'     => 1
        ]);

        if (!empty($contactAlreadyCreated[0]['id'])) {
            $contact = [
                'id'         => $contactAlreadyCreated[0]['id'],
                'returnCode' => (int) 0
            ];
        } else {
            $aDataContact = [
                'company'             => $transferringAgencyMetadata->LegalClassification,
                'external_id'         => json_encode(['m2m' => $transferringAgency->Identifier->value]),
                'department'          => $transferringAgencyMetadata->Name,
                'communication_means' => json_encode($aCommunicationMeans),
                'creator'               => $GLOBALS['id']
            ];

            $contactId = ContactModel::create($aDataContact);
            if (empty($contactId)) {
                $contact = [
                    'returnCode'  => (int) -1,
                    'error'       => 'Contact creation error',
                ];
            } else {
                $contact = [
                    'id'         => $contactId,
                    'returnCode' => (int) 0
                ];
            }
        }

        return $contact;
    }

    protected static function saveNotes($aArgs = [])
    {
        $countNote = 0;
        foreach ($aArgs['dataObject']->Comment as $value) {
            if (!empty($value->value)) {
                NoteModel::create([
                    "resId" => $aArgs['resId'],
                    "user_id"    => $aArgs['userId'],
                    "note_text"  => $value->value
                ]);

                HistoryController::add([
                    'tableName' => 'notes',
                    'recordId'  => $aArgs['resId'],
                    'eventType' => 'ADD',
                    'eventId'   => 'noteadd',
                    'info'       => _NOTE_ADDED
                ]);

                $countNote++;
            }
        }
        self::$aComments[] = '['.date("d/m/Y H:i:s") . '] '.$countNote . ' note(s) enregistrée(s)';
        return true;
    }

    protected static function saveResAttachment($aArgs = [])
    {
        $dataObject        = $aArgs['dataObject'];
        $resIdMaster       = $aArgs['resId'];
        $defaultConfig     = $aArgs['defaultConfig']['res_attachments'];
        $dataObjectPackage = $dataObject->DataObjectPackage;

        $attachments = $dataObjectPackage->DescriptiveMetadata->ArchiveUnit[0]->ArchiveUnit;

        // First one is the main document. Already added
        unset($attachments[0]);
        $countAttachment = 0;
        if (!empty($attachments)) {
            foreach ($attachments as $value) {
                $attachmentContent      = $value->Content;
                $attachmentDataObjectId = $value->DataObjectReference[0]->DataObjectReferenceId;

                $BinaryDataObjectInfo = self::getBinaryDataObjectInfo(["binaryDataObject" => $dataObjectPackage->BinaryDataObject, "binaryDataObjectId" => $attachmentDataObjectId]);
                $filename             = $BinaryDataObjectInfo->Attachment->filename;
                $fileFormat           = substr($filename, strrpos($filename, '.') + 1);

                $users = UserModel::get(['select' => ['id'], 'where' => ['mode in (?)'], 'data' => [['root_visible', 'root_invisible']], 'limit' => 1]);

                $allDatas = [
                    'title'        => $attachmentContent->Title[0],
                    'encodedFile'  => $BinaryDataObjectInfo->Attachment->value,
                    'format'       => $fileFormat,
                    'typist'       => $users[0]['id'],
                    'resIdMaster'  => $resIdMaster,
                    'type'         => $defaultConfig['attachment_type']
                ];

                $resId = StoreController::storeAttachment($allDatas);
                ConvertPdfController::convert([
                    'resId'  => $resId,
                    'collId' => 'attachments_coll'
                ]);
                $countAttachment++;
            }
        }
        self::$aComments[] = '['.date("d/m/Y H:i:s") . '] '.$countAttachment . ' attachement(s) enregistré(s)';
        return $resId;
    }

    protected static function getBinaryDataObjectInfo($aArgs = [])
    {
        $dataObject   = $aArgs['binaryDataObject'];
        $dataObjectId = $aArgs['binaryDataObjectId'];

        foreach ($dataObject as $value) {
            if ($value->id == $dataObjectId) {
                return $value;
            }
        }
        return null;
    }

    protected function sendAcknowledgement($aArgs = [])
    {
        $dataObject = $aArgs['dataObject'];
        $date       = new \DateTime;

        $acknowledgementObject                                   = new \stdClass();
        $acknowledgementObject->Date                             = $date->format(\DateTime::ATOM);

        $acknowledgementObject->MessageIdentifier                = new \stdClass();
        $acknowledgementObject->MessageIdentifier->value         = $dataObject->MessageIdentifier->value . '_AckSent';

        $acknowledgementObject->MessageReceivedIdentifier        = new \stdClass();
        $acknowledgementObject->MessageReceivedIdentifier->value = $dataObject->MessageIdentifier->value;

        $acknowledgementObject->Sender                           = $dataObject->ArchivalAgency;
        $acknowledgementObject->Receiver                         = $dataObject->TransferringAgency;

        if ($acknowledgementObject->Receiver->OrganizationDescriptiveMetadata->Communication[0]->Channel == 'url') {
            $acknowledgementObject->Receiver->OrganizationDescriptiveMetadata->Communication[0]->value .= '/rest/saveMessageExchangeReturn';
        }

        $acknowledgementObject->MessageIdentifier->value          = $dataObject->MessageIdentifier->value . '_Ack';
        $filePath = SendMessageController::generateMessageFile(['messageObject' => $acknowledgementObject, 'type' => 'Acknowledgement']);
        $acknowledgementObject->ArchivalAgency = $acknowledgementObject->Receiver;
        $acknowledgementObject->TransferringAgency = $acknowledgementObject->Sender;

        $acknowledgementObject->TransferringAgency->OrganizationDescriptiveMetadata->UserIdentifier = $GLOBALS['login'];

        $acknowledgementObject->MessageIdentifier->value          = $dataObject->MessageIdentifier->value . '_AckSent';
        $messageExchangeSaved = SendMessageExchangeController::saveMessageExchange(['dataObject' => $acknowledgementObject, 'res_id_master' => 0, 'type' => 'Acknowledgement', 'file_path' => $filePath, 'userId' => $GLOBALS['login']]);

        $acknowledgementObject->DataObjectPackage = new \stdClass();
        $acknowledgementObject->DataObjectPackage->DescriptiveMetadata = new \stdClass();
        $acknowledgementObject->DataObjectPackage->DescriptiveMetadata->ArchiveUnit = array();
        $acknowledgementObject->DataObjectPackage->DescriptiveMetadata->ArchiveUnit[0] = new \stdClass();
        $acknowledgementObject->DataObjectPackage->DescriptiveMetadata->ArchiveUnit[0]->Content = new \stdClass();
        $acknowledgementObject->DataObjectPackage->DescriptiveMetadata->ArchiveUnit[0]->Content->Title[0] = '[CAPTUREM2M_ACK]'.date("Ymd_his");

        SendMessageController::send($acknowledgementObject, $messageExchangeSaved['messageId'], 'Acknowledgement');

        return $messageExchangeSaved;
    }

    protected function sendReply($aArgs = [])
    {
        $dataObject = $aArgs['dataObject'];
        $date       = new \DateTime;

        $replyObject                                    = new \stdClass();
        $replyObject->Comment                           = $aArgs['Comment'];
        $replyObject->Date                              = $date->format(\DateTime::ATOM);

        $replyObject->MessageIdentifier                 = new \stdClass();
        $replyObject->MessageIdentifier->value          = $dataObject->MessageIdentifier->value . '_ReplySent';

        $replyObject->ReplyCode                         = $aArgs['replyCode'];

        $replyObject->MessageRequestIdentifier        = new \stdClass();
        $replyObject->MessageRequestIdentifier->value = $dataObject->MessageIdentifier->value;

        $replyObject->TransferringAgency                = $dataObject->ArchivalAgency;
        $replyObject->TransferringAgency->OrganizationDescriptiveMetadata->UserIdentifier = $GLOBALS['login'];
        $replyObject->ArchivalAgency                    = $dataObject->TransferringAgency;

        $replyObject->MessageIdentifier->value          = $dataObject->MessageIdentifier->value . '_Reply';
        $filePath = SendMessageController::generateMessageFile(['messageObject' => $replyObject, 'type' => 'ArchiveTransferReply']);
        $replyObject->MessageIdentifier->value          = $dataObject->MessageIdentifier->value . '_ReplySent';
        $messageExchangeSaved = SendMessageExchangeController::saveMessageExchange(['dataObject' => $replyObject, 'res_id_master' => $aArgs['res_id_master'], 'type' => 'ArchiveTransferReply', 'file_path' => $filePath, 'userId' => $aArgs['userId']]);

        $replyObject->MessageIdentifier->value          = $dataObject->MessageIdentifier->value . '_Reply';

        $replyObject->DataObjectPackage = new \stdClass();
        $replyObject->DataObjectPackage->DescriptiveMetadata = new \stdClass();
        $replyObject->DataObjectPackage->DescriptiveMetadata->ArchiveUnit = array();
        $replyObject->DataObjectPackage->DescriptiveMetadata->ArchiveUnit[0] = new \stdClass();
        $replyObject->DataObjectPackage->DescriptiveMetadata->ArchiveUnit[0]->Content = new \stdClass();
        $replyObject->DataObjectPackage->DescriptiveMetadata->ArchiveUnit[0]->Content->OriginatingSystemId = $aArgs['res_id_master'];

        $replyObject->DataObjectPackage->DescriptiveMetadata->ArchiveUnit[0]->Content->Title[0] = '[CAPTUREM2M_REPLY]'.date("Ymd_his");

        SendMessageController::send($replyObject, $messageExchangeSaved['messageId'], 'ArchiveTransferReply');
    }

    public function saveMessageExchangeReturn(Request $request, Response $response)
    {
        if (empty($GLOBALS['login'])) {
            return $response->withStatus(401)->withJson(['errors' => 'User Not Connected']);
        }

        $data = $request->getParsedBody();

        if (!self::checkNeededParameters(['data' => $data, 'needed' => ['type']])) {
            return $response->withStatus(400)->withJson(['errors' => 'Bad Request']);
        }

        $tmpName = self::createFile(['base64' => $data['base64'], 'extension' => $data['extension'], 'size' => $data['size']]);

        $receiveMessage = new \ReceiveMessage();
        $tmpPath = CoreConfigModel::getTmpPath();
        $res = $receiveMessage->receive($tmpPath, $tmpName, $data['type']);

        $sDataObject = $res['content'];
        $dataObject = json_decode($sDataObject);

        if ($dataObject->type == 'Acknowledgement') {
            $messageExchange = MessageExchangeModel::getMessageByReference(['select' => ['message_id', 'res_id_master'], 'reference' => $dataObject->MessageReceivedIdentifier->value]);
            $dataObject->TransferringAgency = $dataObject->Sender;
            $dataObject->ArchivalAgency     = $dataObject->Receiver;
            MessageExchangeModel::updateReceptionDateMessage(['reception_date' => $dataObject->Date, 'message_id' => $messageExchange['message_id']]);
        } elseif ($dataObject->type == 'ArchiveTransferReply') {
            $messageExchange = MessageExchangeModel::getMessageByReference(['select' => ['message_id', 'res_id_master'], 'reference' => $dataObject->MessageRequestIdentifier->value]);
        }

        $messageExchangeSaved = SendMessageExchangeController::saveMessageExchange(['dataObject' => $dataObject, 'res_id_master' => $messageExchange['res_id_master'], 'type' => $data['type'], 'userId' => $GLOBALS['login']]);
        if (!empty($messageExchangeSaved['error'])) {
            return $response->withStatus(400)->withJson(['errors' => $messageExchangeSaved['error']]);
        }

        return $response->withJson([
            "messageId" => $messageExchangeSaved['messageId']
        ]);
    }

    protected function addComment($str)
    {
        $comment = new \stdClass();
        $comment->value = $str;

        self::$aComments[] = $comment;
    }
}
