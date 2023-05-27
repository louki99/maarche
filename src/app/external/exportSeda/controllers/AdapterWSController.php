<?php

/**
* Copyright Maarch since 2008 under licence GPLv3.
* See LICENCE.txt file at the root folder for more details.
* This file is part of Maarch software.
*
*/

/**
* @briefAdapter WS Controller
* @author dev@maarch.org
*/

namespace ExportSeda\controllers;

use MessageExchange\models\MessageExchangeModel;

class AdapterWSController
{
    public function send($messageObject, $messageId, $type)
    {
        $res     = TransferController::transfer('maarchcourrier', $messageId, $type);

        if ($res['status'] == 1) {
            MessageExchangeModel::updateStatusMessage(['messageId' => $messageId, 'status' => 'E']);
            return $res;
        }

        MessageExchangeModel::updateStatusMessage(['messageId' => $messageId, 'status' => 'S']);
    }
}
