<?php

/**
* Copyright Maarch since 2008 under licence GPLv3.
* See LICENCE.txt file at the root folder for more details.
* This file is part of Maarch software.
*
*/

/**
* @brief Adapter Email Controller
* @author dev@maarch.org
*/

namespace ExportSeda\controllers;

use Configuration\models\ConfigurationModel;
use Email\controllers\EmailController;
use MessageExchange\models\MessageExchangeModel;
use User\models\UserModel;

class AdapterEmailController
{
    public function send($messageObject, $messageId)
    {
        $res['status'] = 0;
        $res['content'] = '';

        $configuration = ConfigurationModel::getByPrivilege(['privilege' => 'admin_export_seda']);
        $configuration = !empty($configuration['value']) ? json_decode($configuration['value'], true) : [];
        $gec    = strtolower($configuration['M2M']['gec'] ?? '');

        if ($gec == 'maarch_courrier') {
            $document = ['id' => $messageObject->DataObjectPackage->DescriptiveMetadata->ArchiveUnit[0]->Content->OriginatingSystemId, 'isLinked' => false, 'original' => false];
            $userInfo = UserModel::getByLogin(['login' => $messageObject->TransferringAgency->OrganizationDescriptiveMetadata->UserIdentifier, 'select' => ['id', 'mail']]);

            if (!empty($messageObject->TransferringAgency->OrganizationDescriptiveMetadata->Contact[0]->Communication[1]->value)) {
                $senderEmail = $messageObject->TransferringAgency->OrganizationDescriptiveMetadata->Contact[0]->Communication[1]->value;
            } else {
                $senderEmail = $userInfo['mail'];
            }

            EmailController::createEmail([
                'userId'    => $userInfo['id'],
                'data'      => [
                    'sender'        => ['email' => $senderEmail],
                    'recipients'    => [$messageObject->ArchivalAgency->OrganizationDescriptiveMetadata->Communication[0]->value],
                    'cc'            => '',
                    'cci'           => '',
                    'object'        => $messageObject->DataObjectPackage->DescriptiveMetadata->ArchiveUnit[0]->Content->Title[0],
                    'body'          => $messageObject->Comment[0]->value,
                    'document'      => $document,
                    'isHtml'        => true,
                    'status'        => 'TO_SEND',
                    'messageExchangeId' => $messageId
                ]
            ]);

            MessageExchangeModel::updateStatusMessage(['messageId' => $messageId, 'status' => 'I']);
        }

        return $res;
    }
}
