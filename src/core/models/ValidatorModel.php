<?php

/**
* Copyright Maarch since 2008 under licence GPLv3.
* See LICENCE.txt file at the root folder for more details.
* This file is part of Maarch software.
*
*/

/**
* @brief Validator Model
* @author dev@maarch.org
*/

namespace SrcCore\models;

use Respect\Validation\Validator;

class ValidatorModel
{
    public static function notEmpty(array $args, $keys)
    {
        if (!Validator::arrayType()->notEmpty()->validate($args)) {
            throw new \Exception('First argument must be a non empty array');
        }
        foreach ($keys as $key) {        
            if (Validator::stringType()->validate($args[$key]) && trim($args[$key]) == '' && $args[$key] != '') {
                $args[$key] .= 'NOT_EMPTY';
            }
            if (Validator::stringType()->validate($args[$key]) && trim($args[$key]) == '0' && $args[$key] != '0') {
                $args[$key] .= 'NOT_EMPTY';
            }
            if (!Validator::notEmpty()->validate($args[$key])) {
                throw new \Exception("Argument {$key} is empty");
            }
        }
    }

    public static function intVal(array $aArgs, $aKeys)
    {
        foreach ($aKeys as $key) {
            if (!isset($aArgs[$key])) {
                continue;
            }
            if (!Validator::intVal()->validate($aArgs[$key])) {
                throw new \Exception("Argument {$key} is not an integer (value)");
            }
        }
    }

    public static function json(array $aArgs, $aKeys)
    {
        foreach ($aKeys as $key) {
            if (!isset($aArgs[$key])) {
                continue;
            }
            if (!Validator::json()->validate($aArgs[$key])) {
                throw new \Exception("Argument {$key} is not a json (value)");
            }
        }
    }

    public static function intType(array $aArgs, $aKeys)
    {
        foreach ($aKeys as $key) {
            if (!isset($aArgs[$key])) {
                continue;
            }
            if (!Validator::intType()->validate($aArgs[$key])) {
                throw new \Exception("Argument {$key} is not an integer (type)");
            }
        }
    }

    public static function stringType(array $aArgs, $aKeys)
    {
        foreach ($aKeys as $key) {
            if (!isset($aArgs[$key])) {
                continue;
            }
            if (!Validator::stringType()->validate($aArgs[$key])) {
                throw new \Exception("Argument {$key} is not a string (type)");
            }
        }
    }

    public static function arrayType(array $aArgs, $aKeys)
    {
        foreach ($aKeys as $key) {
            if (!isset($aArgs[$key])) {
                continue;
            }
            if (!Validator::arrayType()->validate($aArgs[$key])) {
                throw new \Exception("Argument {$key} is not an array (type)");
            }
        }
    }

    public static function boolType(array $aArgs, $aKeys)
    {
        foreach ($aKeys as $key) {
            if (!isset($aArgs[$key])) {
                continue;
            }
            if (!Validator::boolType()->validate($aArgs[$key])) {
                throw new \Exception("Argument {$key} is not a boolean (type)");
            }
        }
    }
}
