<?php

/**
 * Copyright Maarch since 2008 under licence GPLv3.
 * See LICENCE.txt file at the root folder for more details.
 * This file is part of Maarch software.
 *
 */

/**
 * @brief Note Model
 * @author dev@maarch.org
 */

namespace Note\models;

use Entity\models\EntityModel;
use SrcCore\models\DatabaseModel;
use SrcCore\models\ValidatorModel;

class NoteModel
{
    public static function get(array $aArgs = [])
    {
        ValidatorModel::arrayType($aArgs, ['select', 'where', 'data', 'orderBy']);
        ValidatorModel::intType($aArgs, ['limit']);

        $notes = DatabaseModel::select([
            'select'    => empty($aArgs['select']) ? ['*'] : $aArgs['select'],
            'table'     => ['notes'],
            'where'     => empty($aArgs['where']) ? [] : $aArgs['where'],
            'data'      => empty($aArgs['data']) ? [] : $aArgs['data'],
            'order_by'  => empty($aArgs['orderBy']) ? [] : $aArgs['orderBy'],
            'limit'     => empty($aArgs['limit']) ? 0 : $aArgs['limit']
        ]);

        return $notes;
    }

    public static function getById(array $args)
    {
        ValidatorModel::notEmpty($args, ['id']);
        ValidatorModel::intVal($args, ['id']);
        ValidatorModel::arrayType($args, ['select']);

        $note = DatabaseModel::select([
            'select'    => empty($args['select']) ? ['*'] : $args['select'],
            'table'     => ['notes'],
            'where'     => ['id = ?'],
            'data'      => [$args['id']],
        ]);

        if (empty($note[0])) {
            return [];
        }

        return $note[0];
    }

    public static function create(array $args)
    {
        ValidatorModel::notEmpty($args, ['resId', 'note_text', 'user_id']);
        ValidatorModel::intVal($args, ['resId', 'user_id']);
        ValidatorModel::stringType($args, ['note_text']);

        $nextSequenceId = DatabaseModel::getNextSequenceValue(['sequenceId' => 'notes_id_seq']);

        DatabaseModel::insert([
            'table'         => 'notes',
            'columnsValues' => [
                'id'            => $nextSequenceId,
                'identifier'    => $args['resId'],
                'user_id'       => $args['user_id'],
                'creation_date' => 'CURRENT_TIMESTAMP',
                'note_text'     => $args['note_text']
            ]
        ]);

        return $nextSequenceId;
    }

    public static function update(array $args)
    {
        ValidatorModel::notEmpty($args, ['set', 'where', 'data']);
        ValidatorModel::arrayType($args, ['set', 'where', 'data']);

        DatabaseModel::update([
            'table' => 'notes',
            'set'   => $args['set'],
            'where' => $args['where'],
            'data'  => $args['data']
        ]);

        return true;
    }

    public static function delete(array $args)
    {
        ValidatorModel::notEmpty($args, ['where', 'data']);
        ValidatorModel::arrayType($args, ['where', 'data']);

        DatabaseModel::delete([
            'table' => 'notes',
            'where' => $args['where'],
            'data'  => $args['data']
        ]);

        return true;
    }

    public static function countByResId(array $aArgs)
    {
        ValidatorModel::notEmpty($aArgs, ['resId', 'userId']);
        ValidatorModel::intVal($aArgs, ['userId']);
        ValidatorModel::arrayType($aArgs, ['resId']);

        $nb = [];
        $countedNotes = [];

        $aEntities = DatabaseModel::select([
            'select'    => ['entity_id'],
            'table'     => ['users_entities'],
            'where'     => ['user_id = ?'],
            'data'      => [$aArgs['userId']]
        ]);

        $entities = array_column($aEntities, 'entity_id');

        $aNotes = DatabaseModel::select([
            'select'    => ['notes.id', 'user_id', 'item_id', 'identifier'],
            'table'     => ['notes', 'note_entities'],
            'left_join' => ['notes.id = note_entities.note_id'],
            'where'     => ['identifier in (?)'],
            'data'      => [$aArgs['resId']]
        ]);

        foreach ($aArgs['resId'] as $resId) {
            $nb[$resId] = 0;
            $countedNotes[$resId] = [];
            foreach ($aNotes as $key => $value) {
                if ($value['identifier'] == $resId && !in_array($value['id'], $countedNotes[$resId])) {
                    if (empty($value['item_id']) ||
                        (!empty($value['item_id']) && (($value['user_id'] == $aArgs['userId']) || (in_array($value['item_id'], $entities))))
                    ) {
                        ++$nb[$resId];
                        $countedNotes[$resId][] = $value['id'];
                        unset($aNotes[$key]);
                    }
                }
            }
        }

        return $nb;
    }

    public static function getByUserIdForResource(array $aArgs)
    {
        ValidatorModel::notEmpty($aArgs, ['userId', 'resId', 'select']);
        ValidatorModel::intVal($aArgs, ['userId', 'resId', 'limit']);
        ValidatorModel::arrayType($aArgs, ['select']);

        $rawUserEntities = EntityModel::getByUserId(['userId' => $aArgs['userId'], 'select' => ['entity_id']]);

        $userEntities = array_column($rawUserEntities, 'entity_id');

        if (!in_array('id', $aArgs['select'])) {
            $aArgs['select'][] = 'id';
        }

        $allNotes = NoteModel::get([
            'select'    => $aArgs['select'],
            'where'     => ['identifier = ?'],
            'data'      => [$aArgs['resId']],
            'orderBy'   => ['id desc'],
            'limit'     => empty($aArgs['limit']) ? null : $aArgs['limit']
        ]);

        $notes = [];
        foreach ($allNotes as $note) {
            $allowed = false;

            if ($note['user_id'] == $aArgs['userId']) {
                $allowed = true;
            }

            $noteEntities = NoteEntityModel::getWithEntityInfo(['select' => ['item_id', 'short_label'], 'where' => ['note_id = ?'], 'data' => [$note['id']]]);
            if (!empty($noteEntities)) {
                foreach ($noteEntities as $noteEntity) {
                    $note['entities_restriction'][] = ['short_label' => $noteEntity['short_label'], 'item_id' => [$noteEntity['item_id']]];

                    if (in_array($noteEntity['item_id'], $userEntities)) {
                        $allowed = true;
                    }
                }
            } else {
                $allowed = true;
            }

            if ($allowed) {
                $notes[] = $note;
            }
        }

        return $notes;
    }
}
