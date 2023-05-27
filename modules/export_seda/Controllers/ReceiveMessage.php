<?php

/**
 * Copyright Maarch since 2008 under licence GPLv3.
 * See LICENCE.txt file at the root folder for more details.
 * This file is part of Maarch software.
 *
 */

/**
 * @brief Receive Message
 * @author dev@maarch.org
 * @ingroup export_seda
 */

class ReceiveMessage
{
    public function __construct()
    {
    }

    /**
     * @param $messageObject
     * @return bool|mixed
     */
    public function receive($tmpPath, $tmpName, $type)
    {
        $res['status']  = 0;
        $res['content'] = '';

        $zipPathParts     = pathinfo($tmpPath. DIRECTORY_SEPARATOR. $tmpName);
        $messageDirectory = $tmpPath . $zipPathParts['filename'];

        $zip = new ZipArchive();
        $zip->open($tmpPath. DIRECTORY_SEPARATOR. $tmpName);
        $zip->extractTo($messageDirectory);

        $messageFileName = '';

        foreach (glob($messageDirectory. DIRECTORY_SEPARATOR. '*.xml') as $filename) {
            $pathParts = pathinfo($filename);
            if (strpos($pathParts['filename'], 'ArchiveTransfer') === false) {
                break;
            } else {
                $messageFileName = $filename;
            }
        }

        if (!$messageFileName) {
            $res['status']  = 1;
            $res['content'] = _ERROR_MESSAGE_NOT_PRESENT;

            return $res;
        }

        libxml_use_internal_errors(true);

        $xml = new DOMDocument();
        $xml->load($messageFileName);

        // TEST ATTACHMENT
        $listFiles = scandir($messageDirectory);
        $dataObject = simplexml_load_file($messageFileName);
        if ($dataObject->DataObjectPackage) {
            foreach ($dataObject->DataObjectPackage->BinaryDataObject as $binaryDataObject) {
                $filename = '';
                // ATTACHMENT FILENAME
                $filename = $binaryDataObject->Attachment->attributes();
                if (!array_search($filename, $listFiles)) {
                    $res['status'] = 1;
                    $res['content'] = _ERROR_ATTACHMENT_FILE_MISSING . ' : ' . $filename;

                    return $res;
                }

                // ATTACHMENT BASE 64
                $data = file_get_contents($messageDirectory . DIRECTORY_SEPARATOR . $filename);
                $dataBase64 = base64_encode($data);

                if ($dataBase64 != $binaryDataObject->Attachment) {
                    $res['status'] = 1;
                    $res['content'] = _ERROR_ATTACHMENT_WRONG_BASE64 . ' : ' . $filename;

                    return $res;
                }
            }
        }

        // ARCHIVER AGENCY CONTACT
        if ($dataObject->ArchivalAgency) {
            $destination = \Entity\models\EntityModel::getByBusinessId(['businessId' => (string)$dataObject->ArchivalAgency->Identifier]);
            if (empty($destination)) {
                $res['status'] = 1;
                $res['content'] = _ERROR_CONTACT_UNKNOW . ' : ' . $dataObject->ArchivalAgency->Identifier;

                return $res;
            }
        }

        $res['content'] = json_encode($this->getMessageObject($dataObject, $type));

        return $res;
    }

    private function getMessageObject($dataObject, $type)
    {
        $messageObject = new stdClass();

        $listComment= array();
        $messageObject->Comment = new stdClass();
        foreach ($dataObject->Comment as $comment) {
            $tmpComment = new stdClass();
            $tmpComment->value = (string) $comment;

            $listComment[] = $tmpComment;
        }
        $messageObject->Comment = $listComment;

        $messageObject->MessageIdentifier = new stdClass();
        $messageObject->MessageIdentifier->value = (string) $dataObject->MessageIdentifier;

        if ($dataObject->MessageReceivedIdentifier) {
            $messageObject->MessageReceivedIdentifier = new stdClass();
            $messageObject->MessageReceivedIdentifier->value = (string) $dataObject->MessageReceivedIdentifier;
        }

        if ($dataObject->MessageRequestIdentifier) {
            $messageObject->MessageRequestIdentifier = new stdClass();
            $messageObject->MessageRequestIdentifier->value = (string) $dataObject->MessageRequestIdentifier;
        }

        $messageObject->Date = (string) $dataObject->Date;

        if ($dataObject->DataObjectPackage) {
            $messageObject->DataObjectPackage = $this->getDataObjectPackage($dataObject->DataObjectPackage);
        }

        if ($dataObject->ArchivalAgency) {
            $messageObject->ArchivalAgency = $this->getOrganization($dataObject->ArchivalAgency);
        }

        if ($dataObject->OriginatingAgency) {
            $messageObject->OriginatingAgency = $this->getOrganization($dataObject->OriginatingAgency);
        }

        if ($dataObject->TransferringAgency) {
            $messageObject->TransferringAgency = $this->getOrganization($dataObject->TransferringAgency);
        }

        if ($dataObject->Sender) {
            $messageObject->Sender = $this->getOrganization($dataObject->Sender);
        }

        if ($dataObject->Receiver) {
            $messageObject->Receiver = $this->getOrganization($dataObject->Receiver);
        }

        if ($dataObject->UnitIdentifier) {
            $messageObject->UnitIdentifier = new stdClass();
            $messageObject->UnitIdentifier->value = (string) $dataObject->UnitIdentifier;
        }

        if ($type) {
            $messageObject->type = $type;
        }

        return $messageObject;
    }

    private function getDataObjectPackage($dataObject)
    {
        $dataObjectPackage = new stdClass();
        $dataObjectPackage->BinaryDataObject = new stdClass();
        $dataObjectPackage->BinaryDataObject = $this->getBinaryDataObject($dataObject->BinaryDataObject);

        $dataObjectPackage->DescriptiveMetadata = new stdClass();
        $dataObjectPackage->DescriptiveMetadata->ArchiveUnit = new stdClass();
        $dataObjectPackage->DescriptiveMetadata->ArchiveUnit = $this->getArchiveUnit($dataObject->DescriptiveMetadata->ArchiveUnit);

        return $dataObjectPackage;
    }

    private function getBinaryDataObject($dataObject)
    {
        $listBinaryDataObject = array();
        $i = 0;
        foreach ($dataObject as $BinaryDataObject) {
            $tmpBinaryDataObject = new stdClass();
            $tmpBinaryDataObject->id = (string) $BinaryDataObject->attributes();

            $tmpBinaryDataObject->MessageDigest = new stdClass();
            $tmpBinaryDataObject->MessageDigest->value = (string) $BinaryDataObject->MessageDigest;
            $tmpBinaryDataObject->MessageDigest->algorithm = (string) $BinaryDataObject->MessageDigest->attributes();

            $tmpBinaryDataObject->Size = (string) $BinaryDataObject->Size;

            $tmpBinaryDataObject->Attachment = new stdClass();
            $tmpBinaryDataObject->Attachment->value = (string) $BinaryDataObject->Attachment;
            foreach ($BinaryDataObject->Attachment->attributes() as $key => $value) {
                if ($key == 'filename') {
                    $tmpBinaryDataObject->Attachment->filename = (string) $value;
                } elseif ($key == 'uri') {
                    $tmpBinaryDataObject->Attachment->uri = (string) $value;
                }
            }

            $tmpBinaryDataObject->FormatIdentification = new stdClass();
            $tmpBinaryDataObject->FormatIdentification->MimeType = (string) $BinaryDataObject->FormatIdentification->MimeType;

            $listBinaryDataObject[] = $tmpBinaryDataObject;
        }

        return $listBinaryDataObject;
    }
    
    private function getArchiveUnit($dataObject)
    {
        $listArchiveUnit = array();
        foreach ($dataObject as $ArchiveUnit) {
            $tmpArchiveUnit = new stdClass();
            $tmpArchiveUnit->id = (string) $ArchiveUnit->attributes();
            $tmpArchiveUnit->Content = new stdClass();
            $tmpArchiveUnit->Content->DescriptionLevel = (string) $ArchiveUnit->Content->DescriptionLevel;

            $tmpArchiveUnit->Content->Title = array();
            foreach ($ArchiveUnit->Content->Title as $title) {
                $tmpArchiveUnit->Content->Title[] = (string) $title;
            }

            $tmpArchiveUnit->Content->OriginatingSystemId                    = (string) $ArchiveUnit->Content->OriginatingSystemId;
            $tmpArchiveUnit->Content->OriginatingAgencyArchiveUnitIdentifier = (string) $ArchiveUnit->Content->OriginatingAgencyArchiveUnitIdentifier;
            $tmpArchiveUnit->Content->DocumentType                           = (string) $ArchiveUnit->Content->DocumentType;
            $tmpArchiveUnit->Content->Status                                 = (string) $ArchiveUnit->Content->Status;
            $tmpArchiveUnit->Content->CreatedDate                            = (string) $ArchiveUnit->Content->CreatedDate;

            if ($ArchiveUnit->Content->Writer) {
                $tmpArchiveUnit->Content->Writer = array();
                foreach ($ArchiveUnit->Content->Writer as $Writer) {
                    $tmpWriter = new stdClass();
                    $tmpWriter->FirstName = (string)$Writer->FirstName;
                    $tmpWriter->BirthName = (string)$Writer->BirthName;

                    $tmpArchiveUnit->Content->Writer = $tmpWriter;
                }
            }

            if ($ArchiveUnit->DataObjectReference) {
                $tmpArchiveUnit->DataObjectReference = array();
                foreach ($ArchiveUnit->DataObjectReference as $DataObjectReference) {
                    $tmpDataObjectReference = new stdClass();
                    $tmpDataObjectReference->DataObjectReferenceId = (string) $DataObjectReference->DataObjectReferenceId;

                    $tmpArchiveUnit->DataObjectReference[] = $tmpDataObjectReference;
                }
            }

            if ($ArchiveUnit->ArchiveUnit) {
                $tmpArchiveUnit->ArchiveUnit = $this->getArchiveUnit($ArchiveUnit->ArchiveUnit);
            }

            $listArchiveUnit[] = $tmpArchiveUnit;
        }
        return $listArchiveUnit;
    }

    private function getOrganization($dataObject)
    {
        $organization= new stdClass();

        $organization->Identifier = new stdClass();
        $organization->Identifier->value = (string) $dataObject->Identifier;

        $organization->OrganizationDescriptiveMetadata = new stdClass();

        if ($dataObject->OrganizationDescriptiveMetadata->LegalClassification) {
            $organization->OrganizationDescriptiveMetadata->LegalClassification = (string) $dataObject->OrganizationDescriptiveMetadata->LegalClassification;
        }

        if ($dataObject->OrganizationDescriptiveMetadata->Name) {
            $organization->OrganizationDescriptiveMetadata->Name = (string) $dataObject->OrganizationDescriptiveMetadata->Name;
        }

        if ($dataObject->OrganizationDescriptiveMetadata->Communication) {
            $organization->OrganizationDescriptiveMetadata->Communication = $this->getCommunication($dataObject->OrganizationDescriptiveMetadata->Communication);
        }

        if ($dataObject->OrganizationDescriptiveMetadata->Contact) {
            $organization->OrganizationDescriptiveMetadata->Contact = $this->getContact($dataObject->OrganizationDescriptiveMetadata->Contact);
        }

        return $organization;
    }

    private function getCommunication($dataObject)
    {
        $listCommunication = array();
        foreach ($dataObject as $Communication) {
            $tmpCommunication = new stdClass();
            $tmpCommunication->Channel = (string) $Communication->Channel;
            $tmpCommunication->value   = (string) $Communication->CompleteNumber;

            $listCommunication[] = $tmpCommunication;
        }

        return $listCommunication;
    }

    private function getAddress($dataObject)
    {
        $listAddress = array();
        foreach ($dataObject as $Address) {
            $tmpAddress = new stdClass();
            $tmpAddress->CityName      = (string) $Address->CityName;
            $tmpAddress->Country       = (string) $Address->Country;
            $tmpAddress->Postcode      = (string) $Address->Postcode;
            $tmpAddress->PostOfficeBox = (string) $Address->PostOfficeBox;
            $tmpAddress->StreetName    = (string) $Address->StreetName;

            $listAddress[] = $tmpAddress;
        }

        return $listAddress;
    }

    private function getContact($dataObject)
    {
        $listContact = array();
        foreach ($dataObject as $Contact) {
            $tmpContact = new stdClass();
            $tmpContact->DepartmentName = (string) $Contact->DepartmentName;
            $tmpContact->PersonName = (string) $Contact->PersonName;

            if ($Contact->Communication) {
                $tmpContact->Communication = $this->getCommunication($Contact->Communication);
            }

            if ($Contact->Address) {
                $tmpContact->Address = $this->getAddress($Contact->Address);
            }
            $listContact[] = $tmpContact;
        }

        return $listContact;
    }
}
