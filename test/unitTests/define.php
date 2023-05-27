<?php

/**
* Copyright Maarch since 2008 under licence GPLv3.
* See LICENCE.txt file at the root folder for more details.
* This file is part of Maarch software.
*
*/

require_once 'vendor/autoload.php';

$GLOBALS['login'] = 'superadmin';
$userInfo = \User\models\UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
$GLOBALS['id'] = $userInfo['id'];
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

date_default_timezone_set(\SrcCore\models\CoreConfigModel::getTimezone());

$language = \SrcCore\models\CoreConfigModel::getLanguage();
require_once("src/core/lang/lang-{$language}.php");

