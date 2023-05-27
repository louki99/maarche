<?php

/**
 * Copyright Maarch since 2008 under licence GPLv3.
 * See LICENCE.txt file at the root folder for more details.
 * This file is part of Maarch software.
 *
 */

/**
 * @brief Chrono Model
 * @author dev@maarch.org
 */

namespace Resource\models;

use Parameter\models\ParameterModel;
use SrcCore\models\ValidatorModel;
use SrcCore\models\CoreConfigModel;
use SrcCore\models\DatabaseModel;

class ChronoModel
{
    public static function getChrono(array $aArgs)
    {
        ValidatorModel::notEmpty($aArgs, ['id']);
        ValidatorModel::stringType($aArgs, ['id', 'entityId']);
        ValidatorModel::intVal($aArgs, ['typeId', 'resId']);

        $elements = [];
        $chronoNumber = [];

        $loadedXml = CoreConfigModel::getXmlLoaded(['path' => 'config/chrono.xml']);
        if ($loadedXml) {
            foreach ($loadedXml->CHRONO as $chrono) {
                if ($chrono->id == $aArgs['id']) {
                    foreach ($chrono->ELEMENT as $chronoElement) {
                        $elements[] = [
                            'type'  => (string)$chronoElement->type,
                            'value' => (string)$chronoElement->value
                        ];
                    }
                }
            }
        }

        foreach ($elements as $value) {
            if (!empty($value['type'])) {
                if ($value['type'] == 'date') {
                    if ($value['value'] == 'year') {
                        $value['value'] = date('Y');
                    } elseif ($value['value'] == 'month') {
                        $value['value'] = date('m');
                    } elseif ($value['value'] == 'day') {
                        $value['value'] = date('d');
                    } elseif ($value['value'] == 'full_date') {
                        $value['value'] = date('dmY');
                    }
                } elseif ($value['type'] == 'maarch_var') {
                    if ($value['value'] == 'entity_id') {
                        $value['value'] = $aArgs['entityId'];
                    } elseif ($value['value'] == 'type_id') {
                        $value['value'] = $aArgs['typeId'];
                    }
                } elseif ($value['type'] == 'maarch_functions') {
                    if ($value['value'] == 'chr_global') {
                        $value['value'] = ChronoModel::getChronoGlobal();
                    } elseif ($value['value'] == 'chr_by_entity') {
                        $value['value'] = ChronoModel::getChronoEntity($aArgs['entityId']);
                    } elseif ($value['value'] == 'chr_by_category') {
                        $value['value'] = ChronoModel::getChronoCategory($aArgs['id']);
                    } elseif ($value['value'] == 'category_char') {
                        $value['value'] = ChronoModel::getChronoCategoryChar($aArgs['id']);
                    } elseif ($value['value'] == 'chr_by_res_id') {
                        $value['value'] = $aArgs['resId'];
                    }
                }
            }
            $chronoNumber[] = $value['value'];
        }

        return implode('', $chronoNumber);
    }

    /**
     * @codeCoverageIgnore
     */
    public static function getChronoGlobal()
    {
        $chronoIdName  = 'chrono_global_' . date('Y');
        $chronoSeqName = $chronoIdName . "_seq";
        $chrono = DatabaseModel::createOrIncreaseChrono(['chronoIdName' => $chronoIdName, 'chronoSeqName' => $chronoSeqName]);
        return $chrono;
    }

    /**
     * @codeCoverageIgnore
     */
    public static function getChronoEntity($entityId)
    {
        $chronoIdName  = "chrono_{$entityId}_" . date('Y');
        $chronoSeqName = $chronoIdName . "_seq";
        $chrono = DatabaseModel::createOrIncreaseChrono(['chronoIdName' => $chronoIdName, 'chronoSeqName' => $chronoSeqName]);
        return $entityId . "/" . $chrono;
    }

    public static function getChronoCategory($categoryId)
    {
        $chronoIdName  = "chrono_{$categoryId}_" . date('Y');
        $chronoSeqName = $chronoIdName . "_seq";
        $chrono = DatabaseModel::createOrIncreaseChrono(['chronoIdName' => $chronoIdName, 'chronoSeqName' => $chronoSeqName]);
        return "/" . $chrono;
    }

    public static function getChronoCategoryChar($categoryId)
    {
        if ($categoryId == 'incoming') {
            return 'A';
        } elseif ($categoryId == 'outgoing') {
            return 'D';
        } else {
            return '';
        }
    }
}
