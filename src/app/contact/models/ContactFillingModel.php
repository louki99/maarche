<?php


/**
* Copyright Maarch since 2008 under licence GPLv3.
* See LICENCE.txt file at the root folder for more details.
* This file is part of Maarch software.
*
*/

/**
* @brief Contact Filling Model
* @author dev@maarch.org
*/

namespace Contact\models;

use SrcCore\models\DatabaseModel;
use SrcCore\models\ValidatorModel;

class ContactFillingModel
{
    public static function get(array $aArgs = [])
    {
        ValidatorModel::arrayType($aArgs, ['select']);

        $rule = DatabaseModel::select([
            'select'    => empty($aArgs['select']) ? ['*'] : $aArgs['select'],
            'table'     => ['contacts_filling']
        ]);

        return $rule[0];
    }

    public static function update(array $aArgs)
    {
        ValidatorModel::boolType($aArgs, ['enable']);
        ValidatorModel::intVal($aArgs, ['first_threshold', 'second_threshold']);

        $aArgs['enable'] = $aArgs['enable'] ? 'true' : 'false';

        DatabaseModel::update([
            'table'     => 'contacts_filling',
            'set'       => [
                'enable'            => $aArgs['enable'],
                'first_threshold'   => $aArgs['first_threshold'],
                'second_threshold'  => $aArgs['second_threshold']
            ],
            'where'     => ['1 = 1']
        ]);

        return true;
    }
}
