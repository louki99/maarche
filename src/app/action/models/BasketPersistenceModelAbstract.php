<?php

/**
 * Copyright Maarch since 2008 under licence GPLv3.
 * See LICENCE.txt file at the root folder for more details.
 * This file is part of Maarch software.
 *
 */

/**
* @brief Basket Persistence Model
* @author  dev@maarch.org
*/

namespace Action\models;

use SrcCore\models\ValidatorModel;
use SrcCore\models\DatabaseModel;

abstract class BasketPersistenceModelAbstract
{
    public static function create(array $aArgs)
    {
        DatabaseModel::insert([
            'table'         => 'basket_persistent_mode',
            'columnsValues' => $aArgs
        ]);

        return true;
    }

    public static function delete(array $aArgs)
    {
        ValidatorModel::notEmpty($aArgs, ['where', 'data']);
        ValidatorModel::arrayType($aArgs, ['where', 'data']);

        DatabaseModel::delete([
            'table' => 'basket_persistent_mode',
            'where' => $aArgs['where'],
            'data'  => $aArgs['data']
        ]);

        return true;
    }
}
