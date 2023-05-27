<?php

/**
* Copyright Maarch since 2008 under licence GPLv3.
* See LICENCE.txt file at the root folder for more details.
* This file is part of Maarch software.
*
*/

/**
* @brief Text Format Model
* @author dev@maarch.org
*/

namespace SrcCore\models;

class TextFormatModel
{
    public static function normalize(array $aArgs)
    {
        ValidatorModel::notEmpty($aArgs, ['string']);
        ValidatorModel::stringType($aArgs, ['string']);

        $a = 'ÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèéêëìíîïðñòóôõöøùúûýýþÿŔŕ';
        $b = 'aaaaaaaceeeeiiiidnoooooouuuuybsaaaaaaaceeeeiiiidnoooooouuuyybyRr';

        $string = utf8_decode($aArgs['string']);
        $string = strtr($string, utf8_decode($a), $b);
        $string = strtolower($string);

        return utf8_encode($string);
    }

    public static function formatFilename(array $args)
    {
        if (!empty($args['maxLength'])) {
            $args['filename'] = mb_substr($args['filename'], 0, $args['maxLength']);
        }
        return preg_replace(utf8_decode('@[\\/:*?"\'<>|,]@'), '_', $args['filename']);
    }

    public static function formatDate($date, $format = null)
    {
        if (empty($date)) {
            return '';
        }

        $date = new \DateTime($date);

        if (!empty($format)) {
            return $date->format($format);
        }

        return $date->format('d-m-Y H:i');
    }

    public static function getEndDayDate(array $args)
    {
        ValidatorModel::notEmpty($args, ['date']);
        ValidatorModel::stringType($args, ['date']);

        $date = new \DateTime($args['date']);
        $date->setTime(23, 59, 59);

        return $date->format('d-m-Y H:i:s');
    }

    public static function removeAccent(array $aArgs)
    {
        ValidatorModel::notEmpty($aArgs, ['string']);
        ValidatorModel::stringType($aArgs, ['string', 'charset']);

        if (empty($aArgs['charset'])) {
            $aArgs['charset'] = 'utf-8';
        }

        $string = htmlentities($aArgs['string'], ENT_NOQUOTES, $aArgs['charset']);

        $string = preg_replace('#\&([A-za-z])(?:uml|circ|tilde|acute|grave|cedil|ring)\;#', '\1', $string);
        $string = preg_replace('#\&([A-za-z]{2})(?:lig)\;#', '\1', $string);
        $string = preg_replace('#\&[^;]+\;#', '', $string);

        return $string;
    }

    public static function htmlWasher($html)
    {
        $html = str_replace("<br/>", "\\n", $html);
        $html = str_replace("<br />", "\\n", $html);
        $html = str_replace("<br/>", "\\n", $html);
        $html = str_replace("&nbsp;", " ", $html);
        $html = str_replace("&eacute;", "\u00e9", $html);
        $html = str_replace("&egrave;", "\u00e8", $html);
        $html = str_replace("&ecirc;", "\00ea", $html);
        $html = str_replace("&agrave;", "\u00e0", $html);
        $html = str_replace("&acirc;", "\u00e2", $html);
        $html = str_replace("&icirc;", "\u00ee", $html);
        $html = str_replace("&ocirc;", "\u00f4", $html);
        $html = str_replace("&ucirc;", "\u00fb", $html);
        $html = str_replace("&acute;", "\u0027", $html);
        $html = str_replace("&deg;", "\u00b0", $html);
        $html = str_replace("&rsquo;", "\u2019", $html);

        return $html;
    }

    public static function cutString(array $aArgs)
    {
        ValidatorModel::notEmpty($aArgs, ['string']);
        ValidatorModel::stringType($aArgs, ['string']);
        ValidatorModel::intType($aArgs, ['max']);

        $string = $aArgs['string'];
        $max    = $aArgs['max'];
        if (strlen($string) >= $max) {
            $string = substr($string, 0, $max);
            $espace = strrpos($string, " ");
            $string = substr($string, 0, $espace)."...";
            return $string;
        } else {
            return $string;
        }
    }

    public static function snakeToCamel($subject) {
        $subject = lcfirst(ucwords($subject, '_'));
        $subject = str_replace('_', '', $subject);
        return $subject;
    }

    public static function camelToSnake($subject) {
        $snakeCaseSubject = '';
        foreach (str_split($subject) as $index => $character) {
            if ($index > 0 && strtoupper($character) == $character && strpos('0123456789_', $character) === false) {
                $character = strtolower($character);
                $character = '_' . $character;
            }
            $snakeCaseSubject .= $character;
        }
        return $snakeCaseSubject;
    }
}
