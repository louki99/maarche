<?php

/**
* Copyright Maarch since 2008 under licence GPLv3.
* See LICENCE.txt file at the root folder for more details.
* This file is part of Maarch software.
*
*/

/**
* @brief Search Template Controller
* @author dev@maarch.org
*/

namespace Search\controllers;

use History\controllers\HistoryController;
use Search\models\SearchTemplateModel;
use Slim\Psr7\Request;
use SrcCore\http\Response;
use Respect\Validation\Validator;

class SearchTemplateController
{
    public function get(Request $request, Response $response)
    {
        $searchTemplates = SearchTemplateModel::get(['select' => ['id', 'label', 'query'], 'where' => ['user_id = ?'], 'data' => [$GLOBALS['id']], 'orderBy' => ['label']]);
        foreach ($searchTemplates as $key => $searchTemplate) {
            $searchTemplates[$key]['query'] = json_decode($searchTemplate['query'], true);
        }

        return $response->withJson(['searchTemplates' => $searchTemplates]);
    }

    public function create(Request $request, Response $response)
    {
        $body = $request->getParsedBody();
        if (!Validator::notEmpty()->arrayType()->validate($body['query'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body query is empty or not an array']);
        }
        if (!Validator::notEmpty()->stringType()->length(1, 255)->validate($body['label'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body label is empty or not a string']);
        }

        $templateId = SearchTemplateModel::create([
            'user_id'       => $GLOBALS['id'],
            'label'         => $body['label'],
            'query'         => json_encode($body['query'])
        ]);

        HistoryController::add([
            'tableName' => 'search_templates',
            'recordId'  => $templateId,
            'eventType' => 'ADD',
            'info'      => 'Modèle de recherche créé',
            'moduleId'  => 'searchTemplate',
            'eventId'   => 'searchTemplateCreation',
        ]);

        return $response->withJson(['id' => $templateId]);
    }

    public function delete(Request $request, Response $response, $args = [])
    {
        if (!Validator::notEmpty()->intVal()->validate($args['id'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Query id is empty or not a numeric']);
        }

        $searchTemplate = SearchTemplateModel::get(['select' => [1], 'where' => ['user_id = ?', 'id = ?'], 'data' => [$GLOBALS['id'], $args['id']]]);
        if (empty($searchTemplate)) {
            return $response->withStatus(400)->withJson(['errors' => 'This template does not exists or it is not your template']);
        }

        SearchTemplateModel::delete(['where' => ['id = ?'], 'data' => [$args['id']]]);

        HistoryController::add([
            'tableName' => 'search_templates',
            'recordId'  => $args['id'],
            'eventType' => 'DEL',
            'info'      => 'Modèle de recherche supprimé',
            'moduleId'  => 'searchTemplate',
            'eventId'   => 'searchTemplateSuppression',
        ]);

        return $response->withStatus(204);
    }
}
