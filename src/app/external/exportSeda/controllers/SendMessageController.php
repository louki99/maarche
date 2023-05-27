<?php

/**
* Copyright Maarch since 2008 under licence GPLv3.
* See LICENCE.txt file at the root folder for more details.
* This file is part of Maarch software.
*
*/

/**
* @brief User Controller
* @author dev@maarch.org
*/

namespace ExportSeda\controllers;

use SrcCore\models\CoreConfigModel;

class SendMessageController
{
    public static function send($messageObject, $messageId, $type)
    {
        $channel = $messageObject->ArchivalAgency->OrganizationDescriptiveMetadata->Communication[0]->Channel;

        $adapter = '';
        if ($channel == 'url') {
            $adapter = new AdapterWSController();
        } elseif ($channel == 'email') {
            $adapter = new AdapterEmailController();
        } else {
            return false;
        }

        $res = $adapter->send($messageObject, $messageId, $type);

        return $res;
    }


    public static function generateMessageFile($aArgs = [], $isForSeda = false)
    {
        $messageObject = $aArgs['messageObject'];
        $type          = $aArgs['type'];

        $DOMTemplate = new \DOMDocument();
        $DOMTemplate->load('src/app/external/exportSeda/resources/'.$type.'.xml');
        $DOMTemplateProcessor = new DOMTemplateProcessorController($DOMTemplate);
        $DOMTemplateProcessor->setSource($type, $messageObject);
        $DOMTemplateProcessor->merge();
        $DOMTemplateProcessor->removeEmptyNodes();

        $tmpPath = CoreConfigModel::getTmpPath();
        file_put_contents($tmpPath . $messageObject->MessageIdentifier->value . ".xml", $DOMTemplate->saveXML());

        if ($messageObject->DataObjectPackage && !$isForSeda) {
            foreach ($messageObject->DataObjectPackage->BinaryDataObject as $binaryDataObject) {
                $base64_decoded = base64_decode($binaryDataObject->Attachment->value);
                $file = fopen($tmpPath . $binaryDataObject->Attachment->filename, 'w');
                fwrite($file, $base64_decoded);
                fclose($file);
            }
        }
        $filename = self::generateZip($messageObject, $tmpPath);

        return $filename;
    }

    public static function generateSedaFile($aArgs = [])
    {
        $tmpPath = CoreConfigModel::getTmpPath();

        $messageObject = $aArgs['messageObject'];
        $type          = $aArgs['type'];

        $seda2Message = self::initMessage(new \stdClass);

        $seda2Message->MessageIdentifier->value = $messageObject->messageIdentifier;
        $seda2Message->ArchivalAgreement->value = $messageObject->archivalAgreement;

        $seda2Message->ArchivalAgency->Identifier->value = $messageObject->archivalAgency;
        $seda2Message->TransferringAgency->Identifier->value = $messageObject->transferringAgency;


        $seda2Message->DataObjectPackage->DescriptiveMetadata->ArchiveUnit[] = self::getArchiveUnit(
            "RecordGrp",
            $messageObject->dataObjectPackage,
            null,
            'group_1',
            null,
            null
        );

        foreach ($messageObject->dataObjectPackage->attachments as $attachment) {
            $seda2Message->DataObjectPackage->BinaryDataObject[] = self::getBinaryDataObject(
                $attachment->filePath,
                $attachment->id
            );

            $pathInfo = pathinfo($attachment->filePath);
            copy($attachment->filePath, $tmpPath . $pathInfo["basename"]);

            if ($attachment->type == "mainDocument") {
                $messageObject->dataObjectPackage->label = $attachment->label;
                $messageObject->dataObjectPackage->originatingSystemId = $attachment->id;
                
                $seda2Message->DataObjectPackage->DescriptiveMetadata->ArchiveUnit[0] = self::getArchiveUnit(
                    "File",
                    $messageObject->dataObjectPackage,
                    null,
                    $attachment->id,
                    "res_" . $attachment->id,
                    $messageObject->dataObjectPackage->links
                );
            } else {
                if (!isset($attachment->retentionRule)) {
                    $attachment->retentionRule = $messageObject->dataObjectPackage->retentionRule;
                    $attachment->retentionFinalDisposition = $messageObject->dataObjectPackage->retentionFinalDisposition;
                }

                $seda2Message->DataObjectPackage->DescriptiveMetadata->ArchiveUnit[0]->ArchiveUnit[] = self::getArchiveUnit(
                    $attachment->type,
                    $attachment,
                    null,
                    $attachment->id,
                    "res_" . $attachment->id,
                    null
                );
            }
        }

        $filename = self::generateMessageFile(["messageObject" => $seda2Message, "type" => $type], true);

        $arrayReturn = [
            "messageObject" => $seda2Message,
            "encodedFilePath" => $filename,
            "messageFilename" => $seda2Message->MessageIdentifier->value
        ];

        return $arrayReturn;
    }

    private static function generateZip($seda2Message, $tmpPath)
    {
        $zip = new \ZipArchive();
        $filename = $tmpPath.$seda2Message->MessageIdentifier->value. ".zip";

        $zip->open($filename, \ZipArchive::CREATE);

        $zip->addFile($tmpPath . $seda2Message->MessageIdentifier->value . ".xml", $seda2Message->MessageIdentifier->value . ".xml");

        if ($seda2Message->DataObjectPackage) {
            foreach ($seda2Message->DataObjectPackage->BinaryDataObject as $binaryDataObject) {
                $zip->addFile($tmpPath . $binaryDataObject->Attachment->filename, $binaryDataObject->Attachment->filename);
            }
        }

        return $filename;
    }

    private static function initMessage($messageObject)
    {
        $date = new \DateTime;
        $messageObject->Date = $date->format(\DateTime::ATOM);
        $messageObject->MessageIdentifier = new \stdClass();
        $messageObject->MessageIdentifier->value = "";

        $messageObject->TransferringAgency = new \stdClass();
        $messageObject->TransferringAgency->Identifier = new \stdClass();

        $messageObject->ArchivalAgency = new \stdClass();
        $messageObject->ArchivalAgency->Identifier = new \stdClass();

        $messageObject->ArchivalAgreement = new \stdClass();

        $messageObject->DataObjectPackage = new \stdClass();
        $messageObject->DataObjectPackage->BinaryDataObject = [];
        $messageObject->DataObjectPackage->DescriptiveMetadata = new \stdClass();
        $messageObject->DataObjectPackage->ManagementMetadata = new \stdClass();

        return $messageObject;
    }

    private static function getBinaryDataObject($filePath, $id)
    {
        $binaryDataObject = new \stdClass();

        $pathInfo = pathinfo($filePath);
        if ($filePath) {
            $filename = $pathInfo["basename"];
        }

        $binaryDataObject->id = "res_" . $id;
        $binaryDataObject->MessageDigest = new \stdClass();
        $binaryDataObject->MessageDigest->value = hash_file('sha256', $filePath);
        $binaryDataObject->MessageDigest->algorithm = "sha256";
        $binaryDataObject->Size = filesize($filePath);

        $binaryDataObject->Attachment = new \stdClass();
        $binaryDataObject->Attachment->filename = $filename;

        $binaryDataObject->FileInfo = new \stdClass();
        $binaryDataObject->FileInfo->Filename = $filename;

        $binaryDataObject->FormatIdentification = new \stdClass();
        $binaryDataObject->FormatIdentification->MimeType = mime_content_type($filePath);

        return $binaryDataObject;
    }

    private static function getArchiveUnit(
        $type,
        $object = null,
        $attachments = null,
        $archiveUnitId = null,
        $dataObjectReferenceId = null,
        $relatedObjectReference = null
    ) {
        $archiveUnit = new \stdClass();

        if ($archiveUnitId) {
            $archiveUnit->id = $archiveUnitId;
        } else {
            $archiveUnit->id = uniqid();
        }

        if (isset($object)) {
            if ($relatedObjectReference) {
                $archiveUnit->Content = self::getContent($type, $object, $relatedObjectReference);
            } else {
                $archiveUnit->Content = self::getContent($type, $object);
            }

            $archiveUnit->Management = self::getManagement($object);
        } else {
            $archiveUnit->Content = self::getContent($type);
            $archiveUnit->Management = self::getManagement();
        }


        if ($dataObjectReferenceId) {
            $archiveUnit->DataObjectReference = new \stdClass();
            $archiveUnit->DataObjectReference->DataObjectReferenceId = $dataObjectReferenceId;
        }

        $archiveUnit->ArchiveUnit = [];
        if ($attachments) {
            $i = 1;
            foreach ($attachments as $attachment) {
                if ($attachment->res_id_master == $object->res_id) {
                    if ($attachment->attachment_type != "signed_response") {
                        $archiveUnit->ArchiveUnit[] = self::getArchiveUnit(
                            "Item",
                            $attachment,
                            null,
                            $archiveUnitId. '_attachment_' . $i,
                            $attachment->res_id
                        );
                    }
                }
                $i++;
            }
        }

        if (count($archiveUnit->ArchiveUnit) == 0) {
            unset($archiveUnit->ArchiveUnit);
        }

        return $archiveUnit;
    }

    private static function getContent($type, $object = null, $relatedObjectReference = null)
    {
        $content = new \stdClass();

        switch ($type) {
            case 'RecordGrp':
                $content->DescriptionLevel = $type;
                $content->Title = [];
                if ($object) {
                    $content->Title[] = $object->label;
                    $content->DocumentType = 'Document Principal';
                } else {
                    $content->DocumentType = 'Dossier';
                }
                break;
            case 'File':
                $content->DescriptionLevel = $type;

                $sentDate = new \DateTime($object->modificationDate);
                $acquiredDate = new \DateTime($object->creationDate);
                if ($object->documentDate) {
                    $receivedDate = new \DateTime($object->documentDate);
                } else {
                    $receivedDate = new \DateTime($object->receivedDate);
                }
                $content->SentDate = $sentDate->format(\DateTime::ATOM);
                $content->ReceivedDate = $receivedDate->format(\DateTime::ATOM);
                $content->AcquiredDate = $acquiredDate->format(\DateTime::ATOM);

                $content->Addressee = [];
                $content->Sender = [];
                $content->Keyword = [];

                if ($object->contacts) {
                    foreach ($object->contacts as $contactType => $contacts) {
                        foreach ($contacts as $contact) {
                            if ($contactType == "senders") {
                                $content->Sender[] = self::getContactData($contact);
                            } elseif ($contactType == "recipients") {
                                $content->Addressee[] = self::getContactData($contact);
                            }
                        }
                    }
                }

                if ($object->folders) {
                    $content->FilePlanPosition = [];
                    $content->FilePlanPosition[] = new \stdClass;
                    $content->FilePlanPosition[0]->value="";
                    foreach ($object->folders as $folder) {
                        $content->FilePlanPosition[0]->value .= "/".$folder;
                    }
                }

                $content->DocumentType = 'Document Principal';
                $content->OriginatingAgencyArchiveUnitIdentifier = $object->chrono;
                $content->OriginatingSystemId = $object->originatingSystemId;

                $content->Title = [];
                $content->Title[] = $object->label;
                break;
            case 'Item':
            case 'attachment':
            case 'response':
            case 'note':
            case 'email':
            case 'summarySheet':
                $content->DescriptionLevel = "Item";
                $content->Title = [];
                $content->Title[] = $object->label;

                if ($type == "attachment") {
                    $content->DocumentType = "Pièce jointe";
                    $date = new \DateTime($object->creation_date);
                    $content->CreatedDate = $date->format('Y-m-d');
                } elseif ($type == "note") {
                    $content->DocumentType = "Note";
                    $date = new \DateTime($object->creation_date);
                    $content->CreatedDate = $date->format('Y-m-d');
                } elseif ($type == "email") {
                    $content->DocumentType = "Courriel";
                    $date = new \DateTime($object->creation_date);
                    $content->CreatedDate = $date->format('Y-m-d');
                } elseif ($type == "response") {
                    $content->DocumentType = "Réponse";
                    $date = new \DateTime($object->creation_date);
                    $content->CreatedDate = $date->format('Y-m-d');
                } elseif ($type == "summarySheet") {
                    $content->DocumentType = "Fiche de liaison";
                    $date = new \DateTime($object->creation_date);
                    $content->CreatedDate = $date->format('Y-m-d');
                }
                break;
        }

        if (isset($relatedObjectReference) && !empty((array) $relatedObjectReference)) {
            $content->RelatedObjectReference = new \stdClass();
            $content->RelatedObjectReference->References = [];

            foreach ($relatedObjectReference as $ref) {
                if ($ref) {
                    $reference = new \stdClass();
                    $reference->ExternalReference = $ref->chrono;
                    $content->RelatedObjectReference->References[] = $reference;
                }
            }
        }

        if (isset($object->originatorAgency)) {
            $content->OriginatingAgency = new \stdClass();
            $content->OriginatingAgency->Identifier = new \stdClass();
            $content->OriginatingAgency->Identifier->value = $object->originatorAgency->id;

            if (empty($content->OriginatingAgency->Identifier->value)) {
                unset($content->OriginatingAgency);
            }
        }

        if (isset($object->history)) {
            $content->CustodialHistory = new \stdClass();
            $content->CustodialHistory->CustodialHistoryItem = [];
            foreach ($object->history as $history) {
                $content->CustodialHistory->CustodialHistoryItem[] = self::getCustodialHistoryItem($history);
            }

            if (count($content->CustodialHistory->CustodialHistoryItem) == 0) {
                unset($content->CustodialHistory);
            }
        }

        return $content;
    }

    private static function getManagement($valueInData = null)
    {
        $management = new \stdClass();

        $management->AppraisalRule = new \stdClass();
        $management->AppraisalRule->Rule = new \stdClass();
        if ($valueInData->retentionRule) {
            $management->AppraisalRule->Rule->value = $valueInData->retentionRule;
            $management->AppraisalRule->StartDate = date("Y-m-d");
            if (isset($valueInData->retentionFinalDisposition) && $valueInData->retentionFinalDisposition == "Conservation") {
                $management->AppraisalRule->FinalAction = "Keep";
            } else {
                $management->AppraisalRule->FinalAction = "Destroy";
            }
        }
        
        if ($valueInData->accessRuleCode) {
            $management->AccessRule = new \stdClass();
            $management->AccessRule->Rule = new \stdClass();
            $management->AccessRule->Rule->value = $valueInData->accessRuleCode;
            $management->AccessRule->StartDate = date("Y-m-d");
        }

        return $management;
    }

    private static function getCustodialHistoryItem($history)
    {
        $date = new \DateTime($history->event_date);

        $custodialHistoryItem = new \stdClass();
        $custodialHistoryItem->value = $history->info;
        $custodialHistoryItem->when = $date->format('Y-m-d');

        return $custodialHistoryItem;
    }

    private static function getContactData($informations)
    {
        $contactData = new \stdClass();
        
        if ($informations->civility) {
            $contactData->Gender = $informations->civility->label;
        }
        if ($informations->firstname) {
            $contactData->FirstName = $informations->firstname;
        }
        if ($informations->lastname) {
            $contactData->BirthName = $informations->lastname;
        }
        return $contactData;
    }
}
