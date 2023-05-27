<?php

/**
 * Copyright Maarch since 2008 under licence GPLv3.
 * See LICENCE.txt file at the root folder for more details.
 * This file is part of Maarch software.
 */

/**
 * @brief Language Controller
 * @author dev@maarch.org
 */

namespace SrcCore\controllers;

use Slim\Psr7\Request;
use SrcCore\http\Response;
use SrcCore\models\CoreConfigModel;
use SrcCore\models\ValidatorModel;

class LanguageController
{
    public function getByLang(Request $request, Response $response, array $args)
    {
        $language = LanguageController::getLanguage(['language' => $args['lang']]);

        return $response->withJson($language);
    }

    public static function getLanguage(array $args)
    {
        ValidatorModel::notEmpty($args, ['language']);
        ValidatorModel::stringType($args, ['language']);

        $language = ['lang' => []];

        if (is_file("src/lang/lang-{$args['language']}.json")) {
            $file             = file_get_contents("src/lang/lang-{$args['language']}.json");
            $language['lang'] = json_decode($file, true);
        }

        $customId = CoreConfigModel::getCustomId();
        if (is_file("custom/{$customId}/lang/lang-{$args['language']}.json")) {
            $file               = file_get_contents("custom/{$customId}/lang/lang-{$args['language']}.json");
            $overloadedLanguage = json_decode($file, true) ?? [];
            $language['lang']   = array_merge($language['lang'], $overloadedLanguage);
        }

        if (empty($language['lang'])) {
            return ['lang' => []];
        }

        return $language;
    }
}
