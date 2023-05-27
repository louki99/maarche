<?php

/**
 * Copyright Maarch since 2008 under licence GPLv3.
 * See LICENCE.txt file at the root folder for more details.
 * This file is part of Maarch software.
 *
 */

/**
 * @brief Note Controller
 * @author dev@maarch.org
 * @ingroup core
 */

namespace Note\controllers;

use Email\models\EmailModel;
use Note\models\NoteModel;
use Note\models\NoteEntityModel;
use Entity\models\EntityModel;
use Respect\Validation\Validator;
use setasign\Fpdi\Tcpdf\Fpdi;
use Slim\Psr7\Request;
use SrcCore\http\Response;
use History\controllers\HistoryController;
use Resource\controllers\ResController;
use SrcCore\models\ValidatorModel;
use User\models\UserModel;
use Template\models\TemplateModel;
use Resource\models\ResModel;

class NoteController
{
    public function getByResId(Request $request, Response $response, array $args)
    {
        if (!Validator::intVal()->validate($args['resId'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Route resId is not an integer']);
        }

        if (!ResController::hasRightByResId(['resId' => [$args['resId']], 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Document out of perimeter']);
        }

        $queryParams = $request->getQueryParams();
        if (!empty($queryParams['limit']) && !Validator::intVal()->validate($queryParams['limit'])) {
            return $response->withStatus(403)->withJson(['errors' => 'Query limit is not an int val']);
        }

        $limit = null;
        if (!empty($queryParams['limit'])) {
            $limit = (int)$queryParams['limit'];
        }

        $notes = NoteModel::getByUserIdForResource(['select' => ['*'], 'resId' => $args['resId'], 'userId' => $GLOBALS['id'], 'limit' => $limit]);
        
        foreach ($notes as $key => $note) {
            $user = UserModel::getById(['select' => ['firstname', 'lastname'], 'id' => $note['user_id']]);
            $primaryEntity = UserModel::getPrimaryEntityById(['id' => $note['user_id'], 'select' => ['entities.entity_label']]);
            $notes[$key]['firstname']    = $user['firstname'];
            $notes[$key]['lastname']     = $user['lastname'];
            $notes[$key]['entity_label'] = $primaryEntity['entity_label'] ?? null;

            $notes[$key]['value'] = $note['note_text'];
            unset($notes[$key]['note_text']);
        }

        return $response->withJson(['notes' => $notes]);
    }

    public function getById(Request $request, Response $response, array $args)
    {
        if (!NoteController::hasRightById(['id' => $args['id'], 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Note out of perimeter']);
        }
        $note = NoteModel::getById(['id' => $args['id']]);

        $entities = NoteEntityModel::get(['select' => ['item_id'], 'where' => ['note_id = ?'], 'data' => [$args['id']]]);
        $entities = array_column($entities, 'item_id');

        $note['value'] = $note['note_text'];
        $note['entities'] = $entities;

        unset($note['note_text']);

        return $response->withJson($note);
    }

    public function create(Request $request, Response $response)
    {
        $body = $request->getParsedBody();

        if (!Validator::stringType()->notEmpty()->validate($body['value'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body value is empty or not a string']);
        } elseif (!Validator::notEmpty()->intVal()->validate($body['resId'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body resId is empty or not an integer']);
        }

        if (!ResController::hasRightByResId(['resId' => [$body['resId']], 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Document out of perimeter']);
        }

        if (!empty($body['entities'])) {
            if (!Validator::arrayType()->validate($body['entities'])) {
                return $response->withStatus(400)->withJson(['errors' => 'Body entities is not an array']);
            }
            $entities = EntityModel::get(['select' => ['count(1)'], 'where' => ['entity_id in (?)'], 'data' => [$body['entities']]]);
            if ($entities[0]['count'] != count($body['entities'])) {
                return $response->withStatus(400)->withJson(['errors' => 'Body entities : one or more entities do not exist']);
            }
        }

        $noteId = NoteModel::create([
            'resId'     => $body['resId'],
            'user_id'   => $GLOBALS['id'],
            'note_text' => $body['value']
        ]);
    
        if (!empty($noteId) && !empty($body['entities'])) {
            foreach ($body['entities'] as $entity) {
                NoteEntityModel::create(['item_id' => $entity, 'note_id' => $noteId]);
            }
        }

        HistoryController::add([
            'tableName' => "notes",
            'recordId'  => $noteId,
            'eventType' => "ADD",
            'info'      => _NOTE_ADDED . " (" . $noteId . ")",
            'moduleId'  => 'notes',
            'eventId'   => 'noteadd'
        ]);

        HistoryController::add([
            'tableName' => 'res_letterbox',
            'recordId'  => $body['resId'],
            'eventType' => 'ADD',
            'info'      => _NOTE_ADDED,
            'moduleId'  => 'resource',
            'eventId'   => 'resourceModification'
        ]);

        return $response->withJson(['noteId' => $noteId]);
    }

    public function update(Request $request, Response $response, array $args)
    {
        if (!NoteController::hasRightById(['id' => $args['id'], 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Note out of perimeter']);
        }

        $note = NoteModel::getById(['select' => ['user_id', 'identifier'], 'id' => $args['id']]);
        if (empty($note) || $note['user_id'] != $GLOBALS['id']) {
            return $response->withStatus(403)->withJson(['errors' => 'Note out of perimeter']);
        }

        $body = $request->getParsedBody();

        if (!Validator::stringType()->notEmpty()->validate($body['value'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body value is empty or not a string']);
        }

        if (!empty($body['entities'])) {
            if (!Validator::arrayType()->validate($body['entities'])) {
                return $response->withStatus(400)->withJson(['errors' => 'Body entities is not an array']);
            }
            $entities = EntityModel::get(['select' => ['count(1)'], 'where' => ['entity_id in (?)'], 'data' => [$body['entities']]]);
            if ($entities[0]['count'] != count($body['entities'])) {
                return $response->withStatus(400)->withJson(['errors' => 'Body entities : one or more entities do not exist']);
            }
        }

        NoteModel::update([
            'set' => [
                'note_text' => $body['value']
            ],
            'where' => ['id = ?'],
            'data'  => [$args['id']]
        ]);

        NoteEntityModel::delete([
            'where' => ['note_id = ?'],
            'data'  => [$args['id']]
        ]);

        if (!empty($body['entities'])) {
            foreach ($body['entities'] as $entity) {
                NoteEntityModel::create(['item_id' => $entity, 'note_id' => $args['id']]);
            }
        }

        HistoryController::add([
            'tableName' => 'notes',
            'recordId'  => $args['id'],
            'eventType' => "UP",
            'info'      => _NOTE_UPDATED,
            'moduleId'  => 'notes',
            'eventId'   => 'noteModification'
        ]);

        HistoryController::add([
            'tableName' => 'res_letterbox',
            'recordId'  => $note['identifier'],
            'eventType' => 'UP',
            'info'      => _NOTE_UPDATED,
            'moduleId'  => 'resource',
            'eventId'   => 'resourceModification'
        ]);

        return $response->withStatus(204);
    }

    public function delete(Request $request, Response $response, array $args)
    {
        if (!NoteController::hasRightById(['id' => $args['id'], 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Note out of perimeter']);
        }

        $note = NoteModel::getById(['select' => ['user_id', 'identifier'], 'id' => $args['id']]);
        if (empty($note) || $note['user_id'] != $GLOBALS['id']) {
            return $response->withStatus(403)->withJson(['errors' => 'Note out of perimeter']);
        }

        NoteModel::delete([
            'where' => ['id = ?'],
            'data'  => [$args['id']]
        ]);
        NoteEntityModel::delete([
            'where' => ['note_id = ?'],
            'data'  => [$args['id']]
        ]);

        $emails = EmailModel::get([
            'select' => ['id', 'document'],
            'where'  => ["status = 'DRAFT'", "document->>'id' = ?::varchar"],
            'data'   => [$note['identifier']]
        ]);
        foreach ($emails as $key => $email) {
            $emails[$key]['document'] = json_decode($email['document'], true);
        }

        $emails = array_filter($emails, function($email) { return !empty($email['document']['notes']); });
        $emails = array_filter($emails, function ($email) use ($args) {
            $noteFound = false;
            foreach ($email['document']['notes'] as $id) {
                if ($id == $args['id']) {
                    $noteFound = true;
                }
            }
            return $noteFound;
        });

        foreach ($emails as $key => $email) {
            $emails[$key]['document']['notes'] = array_filter($emails[$key]['document']['notes'], function ($note) use ($args){
                return $note != $args['id'];
            });
            $emails[$key]['document']['notes'] = array_values($emails[$key]['document']['notes']);
            EmailModel::update([
                'set'   => ['document' => json_encode($emails[$key]['document'])],
                'where' => ['id = ?'],
                'data'  => [$emails[$key]['id']]
            ]);
        }

        HistoryController::add([
            'tableName' => 'notes',
            'recordId'  => $args['id'],
            'eventType' => "DEL",
            'info'      => _NOTE_DELETED,
            'moduleId'  => 'notes',
            'eventId'   => 'noteSuppression'
        ]);

        HistoryController::add([
            'tableName' => 'res_letterbox',
            'recordId'  => $note['identifier'],
            'eventType' => 'DEL',
            'info'      => _NOTE_DELETED,
            'moduleId'  => 'resource',
            'eventId'   => 'resourceModification'
        ]);

        return $response->withStatus(204);
    }

    public static function getTemplates(Request $request, Response $response)
    {
        $query = $request->getQueryParams();

        if (!empty($query['resId']) && is_numeric($query['resId'])) {
            if (!ResController::hasRightByResId(['resId' => [$query['resId']], 'userId' => $GLOBALS['id']])) {
                return $response->withStatus(403)->withJson(['errors' => 'Document out of perimeter']);
            }

            $resource = ResModel::getById(['resId' => $query['resId'], 'select' => ['destination']]);

            if (!empty($resource['destination'])) {
                $templates = TemplateModel::getWithAssociation([
                    'select'    => ['DISTINCT(templates.template_id), template_label', 'template_content'],
                    'where'     => ['template_target = ?', 'value_field = ?'],
                    'data'      => ['notes', $resource['destination']],
                    'orderBy'   => ['template_label']
                ]);
            } else {
                $templates = TemplateModel::getByTarget(['template_target' => 'notes', 'select' => ['template_label', 'template_content']]);
            }
        } else {
            $templates = TemplateModel::getByTarget(['template_target' => 'notes', 'select' => ['template_label', 'template_content']]);
        }

        return $response->withJson(['templates' => $templates]);
    }

    public static function getEncodedPdfByIds(array $aArgs)
    {
        ValidatorModel::notEmpty($aArgs, ['ids']);
        ValidatorModel::arrayType($aArgs, ['ids']);

        $pdf = new Fpdi('P', 'pt');
        $pdf->setPrintHeader(false);
        $pdf->AddPage();

        foreach ($aArgs['ids'] as $noteId) {
            $note = NoteModel::getById(['id' => $noteId, 'select' => ['note_text', 'creation_date', 'user_id']]);

            $user = UserModel::getById(['id' => $note['user_id'], 'select' => ['firstname', 'lastname']]);
            $date = new \DateTime($note['creation_date']);
            $date = $date->format('d-m-Y H:i');

            $pdf->Cell(0, 20, "{$user['firstname']} {$user['lastname']} : {$date}", 1, 2, 'C', false);
            $pdf->MultiCell(0, 20, $note['note_text'], 1, 'L', false);
            $pdf->SetY($pdf->GetY() + 40);
        }
        $fileContent = $pdf->Output('', 'S');

        return ['encodedDocument' => base64_encode($fileContent)];
    }

    public static function hasRightById(array $args)
    {
        ValidatorModel::notEmpty($args, ['id', 'userId']);
        ValidatorModel::intVal($args, ['id', 'userId']);

        $note = NoteModel::getById(['select' => ['user_id', 'identifier'], 'id' => $args['id']]);
        if (empty($note)) {
            return false;
        }
        if (!ResController::hasRightByResId(['resId' => [$note['identifier']], 'userId' => $args['userId']])) {
            return false;
        }
        if ($note['user_id'] == $args['userId']) {
            return true;
        }

        $noteEntities = NoteEntityModel::get(['select' => [1], 'where' => ['note_id = ?'], 'data' => [$args['id']]]);
        if (empty($noteEntities)) {
            return true;
        }

        $userEntities = EntityModel::getByUserId(['userId' => $args['userId'], 'select' => ['entity_id']]);
        $userEntities = array_column($userEntities, 'entity_id');
        if (empty($userEntities)) {
            return false;
        }

        $noteEntities = NoteEntityModel::get(['select' => [1], 'where' => ['note_id = ?', 'item_id in (?)'], 'data' => [$args['id'], $userEntities]]);
        if (empty($noteEntities)) {
            return false;
        }

        return true;
    }
}
