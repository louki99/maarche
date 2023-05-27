<?php

/**
* Copyright Maarch since 2008 under licence GPLv3.
* See LICENCE.txt file at the root folder for more details.
* This file is part of Maarch software.
*
*/

/**
* @brief Adapter Maarch Courrier Controller
* @author dev@maarch.org
*/

namespace ExportSeda\controllers;

use Docserver\models\DocserverModel;
use Docserver\models\DocserverTypeModel;
use Resource\controllers\StoreController;
use MessageExchange\models\MessageExchangeModel;

class AdapterMaarchCourrierController
{
    public function getInformations($messageId, $type)
    {
        $res = []; // [0] = url, [1] = header, [2] = cookie, [3] = data

        $message = MessageExchangeModel::getMessageByIdentifier(['messageId' => $messageId]);
        $messageObject = json_decode($message['data']);

        $docserver     = DocserverModel::getByDocserverId(['docserverId' => $message['docserver_id']]);
        $docserverType = DocserverTypeModel::getById(
            ['id' => $docserver['docserver_type_id']]
        );

        $pathDirectory = str_replace('#', DIRECTORY_SEPARATOR, $message['path']);
        $filePath      = $docserver['path_template'] . $pathDirectory . $message['filename'];
        $fingerprint   = StoreController::getFingerPrint([
            'filePath' => $filePath,
            'mode'     => $docserverType['fingerprint_mode'],
        ]);

        if ($fingerprint != $message['fingerprint']) {
            echo _PB_WITH_FINGERPRINT_OF_DOCUMENT;
            exit;
        }

        $pathParts = pathinfo($filePath);
        $res[0] =  $messageObject->ArchivalAgency->OrganizationDescriptiveMetadata->Communication[0]->value
            . '?extension='. $pathParts['extension']
            . '&size='. filesize($filePath)
            . '&type='. $type;

        $res[1] = [
            'accept:application/json',
            'content-type:application/json'
        ];

        $res[2] = '';

        $postData = new \stdClass();
        $postData->base64 = base64_encode(file_get_contents($filePath));

        $res[3] = json_encode($postData);

        return $res;
    }
}
