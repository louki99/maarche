<?php

/**
 * Copyright Maarch since 2008 under licence GPLv3.
 * See LICENCE.txt file at the root folder for more details.
 * This file is part of Maarch software.
 *
 */

/**
 * @brief Email Script
 * @author dev@maarch.org
 */

namespace Email\scripts;

require 'vendor/autoload.php';

use AcknowledgementReceipt\models\AcknowledgementReceiptModel;
use Email\controllers\EmailController;
use Email\models\EmailModel;
use SrcCore\models\DatabasePDO;
use User\models\UserModel;

//customId   = $argv[1];
//emailId    = $argv[2];
//userId     = $argv[3];
//encryptKey = $argv[4];
//options    = $argv[5];

$options = empty($argv[5]) ? null : unserialize($argv[5]);
EmailScript::send(['customId' => $argv[1], 'emailId' => $argv[2], 'userId' => $argv[3], 'encryptKey' => $argv[4], 'options' => $options]);

class EmailScript
{
    public static function send(array $args)
    {
        DatabasePDO::reset();
        new DatabasePDO(['customId' => $args['customId']]);

        $currentUser = UserModel::getById(['id' => $args['userId'], 'select' => ['user_id']]);
        $GLOBALS['login'] = $currentUser['user_id'];
        $GLOBALS['id']    = $args['userId'];
        $_SERVER['MAARCH_ENCRYPT_KEY'] = $args['encryptKey'];

        $isSent = EmailController::sendEmail(['emailId' => $args['emailId'], 'userId' => $args['userId']]);
        if (!empty($isSent['success'])) {
            EmailModel::update(['set' => ['status' => 'SENT', 'send_date' => 'CURRENT_TIMESTAMP'], 'where' => ['id = ?'], 'data' => [$args['emailId']]]);
        } else {
            EmailModel::update(['set' => ['status' => 'ERROR'], 'where' => ['id = ?'], 'data' => [$args['emailId']]]);
        }

        //Options
        if (!empty($args['options']['acknowledgementReceiptId']) && !empty($isSent['success'])) {
            AcknowledgementReceiptModel::update(['set' => ['send_date' => 'CURRENT_TIMESTAMP'], 'where' => ['id = ?'], 'data' => [$args['options']['acknowledgementReceiptId']]]);
        }

        return $isSent;
    }
}
