<?php

/**
 * Copyright Maarch since 2008 under licence GPLv3.
 * See LICENCE.txt file at the root folder for more details.
 * This file is part of Maarch software.
 *
 */

/**
 * @brief Template Model Abstract
 * @author dev@maarch.org
 */

namespace Template\models;

use SrcCore\models\CoreConfigModel;
use SrcCore\models\ValidatorModel;
use SrcCore\models\DatabaseModel;

abstract class TemplateModelAbstract
{
    public static function get(array $aArgs = [])
    {
        ValidatorModel::arrayType($aArgs, ['select', 'where', 'data', 'orderBy']);
        ValidatorModel::intType($aArgs, ['limit']);

        $aTemplates = DatabaseModel::select([
            'select'    => empty($aArgs['select']) ? ['*'] : $aArgs['select'],
            'table'     => ['templates'],
            'where'     => empty($aArgs['where']) ? [] : $aArgs['where'],
            'data'      => empty($aArgs['data']) ? [] : $aArgs['data'],
            'order_by'  => empty($aArgs['orderBy']) ? [] : $aArgs['orderBy'],
            'limit'     => empty($aArgs['limit']) ? 0 : $aArgs['limit']
        ]);

        return $aTemplates;
    }

    public static function getById(array $aArgs)
    {
        ValidatorModel::notEmpty($aArgs, ['id']);
        ValidatorModel::intVal($aArgs, ['id']);
        ValidatorModel::arrayType($aArgs, ['select']);

        $aTemplate = DatabaseModel::select([
            'select'    => empty($aArgs['select']) ? ['*'] : $aArgs['select'],
            'table'     => ['templates'],
            'where'     => ['template_id = ?'],
            'data'      => [$aArgs['id']],
        ]);

        if (empty($aTemplate[0])) {
            return [];
        }

        return $aTemplate[0];
    }

    public static function getByTarget(array $aArgs)
    {
        ValidatorModel::notEmpty($aArgs, ['template_target']);

        $aTemplate = DatabaseModel::select([
            'select'    => empty($aArgs['select']) ? ['*'] : $aArgs['select'],
            'table'     => ['templates'],
            'where'     => ['template_target = ?'],
            'data'      => [$aArgs['template_target']],
        ]);

        return $aTemplate;
    }

    public static function getByEntity(array $aArgs)
    {
        ValidatorModel::notEmpty($aArgs, ['entities']);
        ValidatorModel::arrayType($aArgs, ['select', 'entities']);

        $aTemplate = DatabaseModel::select([
            'select'    => empty($aArgs['select']) ? ['*'] : $aArgs['select'],
            'table'     => ['templates t, templates_association ta'],
            'where'     => ['t.template_id = ta.template_id', 'ta.value_field in (?)'],
            'data'      => [$aArgs['entities']],
        ]);

        return $aTemplate;
    }

    public static function create(array $args)
    {
        ValidatorModel::notEmpty($args, ['template_label']);
        ValidatorModel::stringType($args, ['template_label']);

        $nextSequenceId = DatabaseModel::getNextSequenceValue(['sequenceId' => 'templates_seq']);

        DatabaseModel::insert([
            'table'         => 'templates',
            'columnsValues' => [
                'template_id'              => $nextSequenceId,
                'template_label'           => $args['template_label'] ?? null,
                'template_comment'         => $args['template_comment'] ?? null,
                'template_content'         => $args['template_content'] ?? null,
                'template_type'            => $args['template_type'] ?? null,
                'template_style'           => $args['template_style'] ?? null,
                'template_datasource'      => $args['template_datasource'] ?? null,
                'template_target'          => $args['template_target'] ?? null,
                'template_attachment_type' => $args['template_attachment_type'] ?? null,
                'template_path'            => $args['template_path'] ?? null,
                'template_file_name'       => $args['template_file_name'] ?? null,
                'subject'                  => $args['subject'] ?? null,
                'options'                  => $args['options'] ?? null
            ]
        ]);

        return $nextSequenceId;
    }

    public static function update(array $args)
    {
        ValidatorModel::notEmpty($args, ['set', 'where', 'data']);
        ValidatorModel::arrayType($args, ['set', 'where', 'data']);

        DatabaseModel::update([
            'table' => 'templates',
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
            'table' => 'templates',
            'where' => $args['where'],
            'data'  => $args['data']
        ]);

        return true;
    }

    public static function getDatasources()
    {
        $datasources = [
            [
                'id'        => 'notif_events',
                'label'     => '[notification] Informations événements systèmes',
                'function'  => 'notifEvents',
                'target'    => 'notification',
            ],
            [
                'id'        => 'letterbox_events',
                'label'     => '[notification] Informations du courrier traité',
                'function'  => 'letterboxEvents',
                'target'    => 'notification',
            ],
            [
                'id'        => 'notes',
                'label'     => '[notification] Informations sur notes associées au courrier',
                'function'  => 'noteEvents',
                'target'    => 'notification',
            ]
        ];

        return $datasources;
    }

    public static function getDatasourceById(array $aArgs)
    {
        ValidatorModel::notEmpty($aArgs, ['id']);

        $datasources = TemplateModel::getDatasources();
        $datasource  = [];

        foreach ($datasources as $value) {
            if ($value['id'] == $aArgs['id']) {
                $datasource = [
                    'id'        => $value['id'],
                    'label'     => $value['label'],
                    'function'  => $value['function'],
                    'target'    => $value['target'],
                ];
                break;
            }
        }

        return $datasource;
    }

    public static function getModels()
    {
        $customId = CoreConfigModel::getCustomId();

        if (is_dir("custom/{$customId}/modules/templates/templates/styles/")) {
            $path = "custom/{$customId}/modules/templates/templates/styles/";
        } else {
            $path = 'modules/templates/templates/styles/';
        }

        $templateModels = scandir($path);
        $models = [];
        foreach ($templateModels as $value) {
            if ($value != '.' && $value != '..') {
                $file = implode('.', explode('.', $value, -1));
                $ext = explode('.', $value);
                $models[] = [
                    'fileName'  => $file,
                    'fileExt'   => strtoupper($ext[count($ext) - 1]),
                    'filePath'  => $path . $value,
                ];
            }
        }

        return $models;
    }

    public static function getWithAssociation(array $aArgs = [])
    {
        ValidatorModel::arrayType($aArgs, ['select', 'where', 'data', 'orderBy']);
        ValidatorModel::intType($aArgs, ['limit']);

        $aTemplates = DatabaseModel::select([
            'select'    => empty($aArgs['select']) ? ['*'] : $aArgs['select'],
            'table'     => ['templates', 'templates_association'],
            'left_join' => ['templates.template_id = templates_association.template_id'],
            'where'     => empty($aArgs['where']) ? [] : $aArgs['where'],
            'data'      => empty($aArgs['data']) ? [] : $aArgs['data'],
            'order_by'  => empty($aArgs['orderBy']) ? [] : $aArgs['orderBy'],
            'limit'     => empty($aArgs['limit']) ? 0 : $aArgs['limit']
        ]);

        return $aTemplates;
    }

    public static function checkEntities(array $aArgs)
    {
        ValidatorModel::notEmpty($aArgs, ['data']);
        ValidatorModel::arrayType($aArgs, ['data']);

        $data = $aArgs['data'];
        
        $listEntities = DatabaseModel::select([
        'select'    => ['ta.value_field', 'e.entity_label'],
        'table'     => ['templates t','templates_association ta', 'entities e'],
        'left_join' => ['ta.template_id = t.template_id', 'e.entity_id = ta.value_field'],
        'where'     => empty($data['template_id']) ? ['t.template_target = ?', 't.template_attachment_type = ?', 'value_field in (?)'] : ['t.template_target = ?', 't.template_attachment_type = ?', 'value_field in (?)', 't.template_id != ?' ],
        'data'      => empty($data['template_id']) ? [$data['target'], $data['template_attachment_type'], $data['entities']]   : [$data['target'], $data['template_attachment_type'], $data['entities'], $data['template_id']],
        'groupBy'   => ['ta.value_field', 'e.entity_label']
        ]);
        
        return $listEntities;
    }
}
