<?php

/**
 * Copyright Maarch since 2008 under licence GPLv3.
 * See LICENCE.txt file at the root folder for more details.
 * This file is part of Maarch software.
 *
 */

/**
 * @brief Template Controller
 * @author dev@maarch.org
 */

namespace Template\controllers;

use Attachment\models\AttachmentTypeModel;
use ContentManagement\controllers\MergeController;
use Convert\controllers\ConvertPdfController;
use Docserver\controllers\DocserverController;
use Docserver\models\DocserverModel;
use Entity\models\EntityModel;
use Group\controllers\PrivilegeController;
use History\controllers\HistoryController;
use Resource\controllers\ResController;
use Resource\controllers\StoreController;
use Resource\models\ResModel;
use Respect\Validation\Validator;
use Slim\Psr7\Request;
use SrcCore\http\Response;
use SrcCore\models\ValidatorModel;
use Template\models\TemplateAssociationModel;
use Template\models\TemplateModel;
use User\models\UserModel;

class TemplateController
{
    const AUTHORIZED_MIMETYPES = [
        'application/zip',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-excel',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml‌.slideshow',
        'application/vnd.oasis.opendocument.text',
        'application/vnd.oasis.opendocument.presentation',
        'application/vnd.oasis.opendocument.spreadsheet',
        'application/octet-stream'
    ];

    public function get(Request $request, Response $response)
    {
        if (!PrivilegeController::hasPrivilege(['privilegeId' => 'admin_templates', 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }

        $templates = TemplateModel::get();

        return $response->withJson(['templates' => $templates]);
    }

    public function getDetailledById(Request $request, Response $response, array $aArgs)
    {
        if (!PrivilegeController::hasPrivilege(['privilegeId' => 'admin_templates', 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }

        $template = TemplateModel::getById(['id' => $aArgs['id']]);
        if (empty($template)) {
            return $response->withStatus(400)->withJson(['errors' => 'Template does not exist']);
        }

        if (!empty($template['options'])) {
            $template['options'] = json_decode($template['options'], true);
        }

        $rawLinkedEntities = TemplateAssociationModel::get(['select' => ['value_field'], 'where' => ['template_id = ?'], 'data' => [$template['template_id']]]);
        $linkedEntities = [];
        foreach ($rawLinkedEntities as $rawLinkedEntity) {
            $linkedEntities[] = $rawLinkedEntity['value_field'];
        }
        $entities = EntityModel::getAllowedEntitiesByUserId(['root' => true]);
        foreach ($entities as $key => $entity) {
            $entities[$key]['state']['selected'] = false;
            if (in_array($entity['id'], $linkedEntities)) {
                $entities[$key]['state']['selected'] = true;
            }
        }

        $attachmentModelsTmp = AttachmentTypeModel::get(['select' => ['type_id', 'label', 'visible']]);
        $attachmentTypes = [];
        foreach ($attachmentModelsTmp as $value) {
            if ($value['visible'] || $value['type_id'] == $template['template_attachment_type']) {
                $attachmentTypes[] = [
                    'label'   => $value['label'],
                    'id'      => $value['type_id'],
                    'visible' => $value['visible']
                ];
            }
        }

        return $response->withJson([
            'template'          => $template,
            'templatesModels'   => TemplateModel::getModels(),
            'attachmentTypes'   => $attachmentTypes,
            'datasources'       => TemplateModel::getDatasources(),
            'entities'          => $entities
        ]);
    }

    public function create(Request $request, Response $response)
    {
        if (!PrivilegeController::hasPrivilege(['privilegeId' => 'admin_templates', 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }

        $body = $request->getParsedBody();
        if (!TemplateController::controlCreateTemplate(['data' => $body])) {
            return $response->withStatus(400)->withJson(['errors' => 'Bad Request']);
        }

        if ($body['target'] == 'acknowledgementReceipt' && !empty($body['entities'])) {
            $checkEntities = TemplateModel::checkEntities(['data' => $body]);
            if (!empty($checkEntities)) {
                return $response->withJson(['checkEntities' => $checkEntities]);
            }
        }

        $template = [
            'template_label'            => $body['label'],
            'template_comment'          => $body['description'],
            'template_type'             => $body['type'],
            'template_style'            => $body['style'] ?? null,
            'template_datasource'       => $body['datasource'],
            'template_target'           => $body['target'],
            'template_attachment_type'  => $body['template_attachment_type']
        ];
        if (!empty($body['options'])) {
            if (!empty($body['options']['acknowledgementReceiptFrom']) && !in_array($body['options']['acknowledgementReceiptFrom'], ['manual', 'destination', 'mailServer', 'user' ])) {
                return $response->withStatus(400)->withJson(['errors' => 'Body options[acknowledgementReceiptFrom] is invalid']);
            }
            $options = ['acknowledgementReceiptFrom' => $body['options']['acknowledgementReceiptFrom']];
            if ($body['options']['acknowledgementReceiptFrom'] == 'manual') {
                if (!Validator::stringType()->notEmpty()->validate($body['options']['acknowledgementReceiptFromMail'])) {
                    return $response->withStatus(400)->withJson(['errors' => 'Body options[acknowledgementReceiptFromMail] is empty or not a string']);
                }
                $options['acknowledgementReceiptFromMail'] = $body['options']['acknowledgementReceiptFromMail'];
            }
            $template['options'] = json_encode($options);
        }
        if ($body['type'] == 'TXT' || $body['type'] == 'HTML' || ($body['type'] == 'OFFICE_HTML' && !empty($body['file']['electronic']['content']))) {
            $template['template_content'] = $body['type'] == 'OFFICE_HTML' ? $body['file']['electronic']['content'] : $body['file']['content'];
        }
        if ($body['type'] == 'OFFICE' || ($body['type'] == 'OFFICE_HTML' && !empty($body['file']['paper']['content']))) {
            $content = $body['type'] == 'OFFICE_HTML' ? $body['file']['paper']['content'] : $body['file']['content'];
            $format = $body['type'] == 'OFFICE_HTML' ? $body['file']['paper']['format'] : $body['file']['format'];

            $fileContent = base64_decode($content);
            $finfo    = new \finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->buffer($fileContent);
            if (!StoreController::isFileAllowed(['extension' => $format, 'type' => $mimeType]) || !in_array($mimeType, self::AUTHORIZED_MIMETYPES)) {
                return $response->withStatus(400)->withJson(['errors' => _WRONG_FILE_TYPE . ' : '.$mimeType]);
            }

            $storeResult = DocserverController::storeResourceOnDocServer([
                'collId'            => 'templates',
                'docserverTypeId'   => 'TEMPLATES',
                'encodedResource'   => $content,
                'format'            => $format
            ]);
            if (!empty($storeResult['errors'])) {
                return $response->withStatus(500)->withJson(['errors' => '[storeResource] ' . $storeResult['errors']]);
            }

            $template['template_path'] = $storeResult['destination_dir'];
            $template['template_file_name'] = $storeResult['file_destination_name'];
        }

        if (!empty($body['subject'])) {
            if (!Validator::stringType()->validate($body['subject']) && !Validator::length(1, 255)->validate($body['subject'])) {
                return $response->withStatus(400)->withJson(['errors' => 'Body subject is too long or not a string']);
            }
            $template['subject'] = $body['subject'];
        }

        $id = TemplateModel::create($template);
        if (!empty($body['entities']) && is_array($body['entities'])) {
            foreach ($body['entities'] as $entity) {
                TemplateAssociationModel::create(['templateId' => $id, 'entityId' => $entity]);
            }
        }

        HistoryController::add([
            'tableName' => 'templates',
            'recordId'  => $id,
            'eventType' => 'ADD',
            'info'      => _TEMPLATE_ADDED . " : {$body['label']}",
            'moduleId'  => 'template',
            'eventId'   => 'templateCreation',
        ]);

        return $response->withJson(['template' => $id]);
    }

    public function update(Request $request, Response $response, array $aArgs)
    {
        if (!PrivilegeController::hasPrivilege(['privilegeId' => 'admin_templates', 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }

        $template = TemplateModel::getById(['select' => ['template_type', 'template_target'], 'id' => $aArgs['id']]);
        if (empty($template)) {
            return $response->withStatus(400)->withJson(['errors' => 'Template does not exist']);
        }

        $body = $request->getParsedBody();
        $body['type'] = $template['template_type'];

        if (!TemplateController::controlUpdateTemplate(['data' => $body])) {
            return $response->withStatus(400)->withJson(['errors' => 'Bad Request']);
        }

        $body['template_id'] = $aArgs['id'];
        if ($template['template_target'] == 'acknowledgementReceipt' && !empty($body['entities'])) {
            $checkEntities = TemplateModel::checkEntities(['data' => $body]);
            if (!empty($checkEntities)) {
                return $response->withJson(['checkEntities' => $checkEntities]);
            }
        }

        $subject = null;
        if (!empty($body['subject'])) {
            if (!Validator::stringType()->validate($body['subject']) && !Validator::length(1, 255)->validate($body['subject'])) {
                return $response->withStatus(400)->withJson(['errors' => 'Body subject is too long or not a string']);
            }
            $subject = $body['subject'];
        }

        $template = [
            'template_label'            => $body['label'],
            'template_comment'          => $body['description'],
            'template_attachment_type'  => $body['template_attachment_type'],
            'subject'                   => $subject
        ];
        if (!empty($body['datasource'])) {
            $template['template_datasource'] = $body['datasource'];
        }
        if (!empty($body['options'])) {
            if (!empty($body['options']['acknowledgementReceiptFrom']) && !in_array($body['options']['acknowledgementReceiptFrom'], ['manual', 'destination', 'mailServer', 'user' ])) {
                return $response->withStatus(400)->withJson(['errors' => 'Body options[acknowledgementReceiptFrom] is invalid']);
            }
            $options = ['acknowledgementReceiptFrom' => $body['options']['acknowledgementReceiptFrom']];
            if ($body['options']['acknowledgementReceiptFrom'] == 'manual') {
                if (!Validator::stringType()->notEmpty()->validate($body['options']['acknowledgementReceiptFromMail'])) {
                    return $response->withStatus(400)->withJson(['errors' => 'Body options[acknowledgementReceiptFromMail] is empty or not a string']);
                }
                $options['acknowledgementReceiptFromMail'] = $body['options']['acknowledgementReceiptFromMail'];
            }
            $template['options'] = json_encode($options);
        }
        if ($body['type'] == 'TXT' || $body['type'] == 'HTML' || ($body['type'] == 'OFFICE_HTML' && !empty($body['file']['electronic']['content']))) {
            $template['template_content'] = $body['type'] == 'OFFICE_HTML' ? $body['file']['electronic']['content'] : $body['file']['content'];
        }
        if (($body['type'] == 'OFFICE' && !empty($body['file']['content'])) || ($body['type'] == 'OFFICE_HTML' && !empty($body['file']['paper']['content']))) {
            $content = $body['type'] == 'OFFICE_HTML' ? $body['file']['paper']['content'] : $body['file']['content'];
            $format = $body['type'] == 'OFFICE_HTML' ? $body['file']['paper']['format'] : $body['file']['format'];

            $fileContent = base64_decode($content);
            $finfo    = new \finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->buffer($fileContent);
            if (!StoreController::isFileAllowed(['extension' => $format, 'type' => $mimeType]) || !in_array($mimeType, self::AUTHORIZED_MIMETYPES)) {
                return $response->withStatus(400)->withJson(['errors' => _WRONG_FILE_TYPE . ' : '.$mimeType]);
            }

            $storeResult = DocserverController::storeResourceOnDocServer([
                'collId'            => 'templates',
                'docserverTypeId'   => 'TEMPLATES',
                'encodedResource'   => $content,
                'format'            => $format
            ]);
            if (!empty($storeResult['errors'])) {
                return $response->withStatus(500)->withJson(['errors' => '[storeResource] ' . $storeResult['errors']]);
            }

            $template['template_path'] = $storeResult['destination_dir'];
            $template['template_file_name'] = $storeResult['file_destination_name'];
        }

        TemplateAssociationModel::delete(['where' => ['template_id = ?'], 'data' => [$aArgs['id']]]);
        if (!empty($body['entities']) && is_array($body['entities'])) {
            foreach ($body['entities'] as $entity) {
                TemplateAssociationModel::create(['templateId' => $aArgs['id'], 'entityId' => $entity]);
            }
        }
        TemplateModel::update(['set' => $template, 'where' => ['template_id = ?'], 'data' => [$aArgs['id']]]);

        HistoryController::add([
            'tableName' => 'templates',
            'recordId'  => $aArgs['id'],
            'eventType' => 'UP',
            'info'      => _TEMPLATE_UPDATED . " : {$template['template_label']}",
            'moduleId'  => 'template',
            'eventId'   => 'templateModification',
        ]);

        return $response->withStatus(204);
    }

    public function delete(Request $request, Response $response, array $aArgs)
    {
        if (!PrivilegeController::hasPrivilege(['privilegeId' => 'admin_templates', 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }

        $template = TemplateModel::getById(['select' => ['template_label'], 'id' => $aArgs['id']]);
        if (empty($template)) {
            return $response->withStatus(400)->withJson(['errors' => 'Template does not exist']);
        }

        TemplateModel::delete(['where' => ['template_id = ?'], 'data' => [$aArgs['id']]]);
        TemplateAssociationModel::delete(['where' => ['template_id = ?'], 'data' => [$aArgs['id']]]);

        HistoryController::add([
            'tableName' => 'templates',
            'recordId'  => $aArgs['id'],
            'eventType' => 'DEL',
            'info'      => _TEMPLATE_DELETED . " : {$template['template_label']}",
            'moduleId'  => 'template',
            'eventId'   => 'templateSuppression',
        ]);

        return $response->withJson(['success' => 'success']);
    }

    public function getContentById(Request $request, Response $response, array $aArgs)
    {
        if (!Validator::intVal()->validate($aArgs['id']) || !PrivilegeController::hasPrivilege(['privilegeId' => 'admin_templates', 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }

        $template = TemplateModel::getById(['id' => $aArgs['id']]);
        if (empty($template)) {
            return $response->withStatus(400)->withJson(['errors' => 'Template does not exist']);
        }
        if (empty($template['template_path'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Template has no office content']);
        }

        $docserver = DocserverModel::getCurrentDocserver(['typeId' => 'TEMPLATES', 'collId' => 'templates', 'select' => ['path_template']]);
        $pathToTemplate = $docserver['path_template'] . str_replace('#', DIRECTORY_SEPARATOR, $template['template_path']) . $template['template_file_name'];
        $extension = pathinfo($pathToTemplate, PATHINFO_EXTENSION);

        if (!ConvertPdfController::canConvert(['extension' => $extension])) {
            return $response->withStatus(400)->withJson(['errors' => 'Template can not be converted']);
        }

        $resource =  file_get_contents($pathToTemplate);
        $convertion = ConvertPdfController::convertFromEncodedResource(['encodedResource' => base64_encode($resource), 'extension' => $extension]);
        if (!empty($convertion['errors'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Template convertion failed : ' . $convertion['errors']]);
        }

        return $response->withJson(['encodedDocument' => $convertion['encodedResource']]);
    }

    public function duplicate(Request $request, Response $response, array $aArgs)
    {
        if (!PrivilegeController::hasPrivilege(['privilegeId' => 'admin_templates', 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }

        $template = TemplateModel::getById(['id' => $aArgs['id']]);

        if (empty($template)) {
            return $response->withStatus(400)->withJson(['errors' => 'Template not found']);
        }

        if ($template['template_target'] == 'acknowledgementReceipt') {
            return $response->withStatus(400)->withJson(['errors' => 'Forbidden duplication']);
        }

        if ($template['template_type'] == 'OFFICE') {
            $docserver = DocserverModel::getCurrentDocserver(['typeId' => 'TEMPLATES', 'collId' => 'templates', 'select' => ['path_template']]);

            $pathOnDocserver = DocserverController::createPathOnDocServer(['path' => $docserver['path_template']]);
            $docinfo = DocserverController::getNextFileNameInDocServer(['pathOnDocserver' => $pathOnDocserver['pathToDocServer']]);
            $docinfo['fileDestinationName'] .=  '.' . explode('.', $template['template_file_name'])[1];

            $pathToDocumentToCopy = $docserver['path_template'] . str_replace('#', DIRECTORY_SEPARATOR, $template['template_path']) . $template['template_file_name'];
            $resource = file_get_contents($pathToDocumentToCopy);

            $copyResult = DocserverController::copyOnDocServer([
                'encodedResource'       => base64_encode($resource),
                'destinationDir'        => $docinfo['destinationDir'],
                'fileDestinationName'   => $docinfo['fileDestinationName']
            ]);
            if (!empty($copyResult['errors'])) {
                return $response->withStatus(500)->withJson(['errors' => 'Template duplication failed : ' . $copyResult['errors']]);
            }
            $template['template_path'] = str_replace($docserver['path_template'], '', $docinfo['destinationDir']);
            $template['template_file_name'] = $docinfo['fileDestinationName'];
        }

        $template['template_label'] = 'Copie de ' . $template['template_label'];

        $templateId = TemplateModel::create($template);

        return $response->withJson(['id' => $templateId]);
    }

    public function initTemplates(Request $request, Response $response)
    {
        if (!PrivilegeController::hasPrivilege(['privilegeId' => 'admin_templates', 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }

        $attachmentModelsTmp = AttachmentTypeModel::get(['select' => ['type_id', 'label', 'visible']]);
        $attachmentTypes = [];
        foreach ($attachmentModelsTmp as $value) {
            if ($value['visible']) {
                $attachmentTypes[] = [
                    'label'   => $value['label'],
                    'id'      => $value['type_id'],
                    'visible' => $value['visible']
                ];
            }
        }

        $entities = EntityModel::getAllowedEntitiesByUserId(['root' => true]);
        foreach ($entities as $key => $entity) {
            $entities[$key]['state']['selected'] = false;
        }

        return $response->withJson([
            'templatesModels' => TemplateModel::getModels(),
            'attachmentTypes' => $attachmentTypes,
            'datasources'     => TemplateModel::getDatasources(),
            'entities'        => $entities,
        ]);
    }

    public function getByResId(Request $request, Response $response, array $args)
    {
        if (!Validator::intVal()->validate($args['resId'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Route resId is not an integer']);
        }
        if (!ResController::hasRightByResId(['resId' => [$args['resId']], 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Document out of perimeter']);
        }

        $resource = ResModel::getById(['resId' => $args['resId'], 'select' => ['destination']]);
        if (!empty($resource['destination'])) {
            $entities = [$resource['destination']];
        } else {
            $entities = UserModel::getEntitiesById(['id' => $GLOBALS['id'], 'select' => ['users_entities.entity_id']]);
            $entities = array_column($entities, 'entity_id');
            if (empty($entities)) {
                $entities = [0];
            }
        }
        $where = ['(templates_association.value_field in (?) OR templates_association.template_id IS NULL)', 'templates.template_type = ?', 'templates.template_target = ?'];
        $data = [$entities, 'OFFICE', 'attachments'];

        $queryParams = $request->getQueryParams();

        if (!empty($queryParams['attachmentType'])) {
            $where[] = 'templates.template_attachment_type in (?)';
            $data[] = explode(',', $queryParams['attachmentType']);
        }
        
        $templates = TemplateModel::getWithAssociation([
            'select'    => ['DISTINCT(templates.template_id)', 'templates.template_label', 'templates.template_file_name', 'templates.template_path', 'templates.template_attachment_type'],
            'where'     => $where,
            'data'      => $data,
            'orderBy'   => ['templates.template_label']
        ]);

        $docserver = DocserverModel::getCurrentDocserver(['typeId' => 'TEMPLATES', 'collId' => 'templates', 'select' => ['path_template']]);
        foreach ($templates as $key => $template) {
            $explodeFile = explode('.', $template['template_file_name']);
            $ext = $explodeFile[count($explodeFile) - 1];
            $exists = is_file($docserver['path_template'] . str_replace('#', DIRECTORY_SEPARATOR, $template['template_path']) . $template['template_file_name']);

            $templates[$key] = [
                'id'                => $template['template_id'],
                'label'             => $template['template_label'],
                'extension'         => $ext,
                'exists'            => $exists,
                'attachmentType'    => $template['template_attachment_type']
            ];
        }

        return $response->withJson(['templates' => $templates]);
    }

    public function getEmailTemplatesByResId(Request $request, Response $response, array $args)
    {
        if (!Validator::intVal()->validate($args['resId']) || !ResController::hasRightByResId(['resId' => [$args['resId']], 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Document out of perimeter']);
        }

        $resource = ResModel::getById(['resId' => $args['resId'], 'select' => ['destination']]);
        if (!empty($resource['destination'])) {
            $entities = [$resource['destination']];
        } else {
            $entities = UserModel::getEntitiesById(['id' => $GLOBALS['id'], 'select' => ['users_entities.entity_id']]);
            $entities = array_column($entities, 'entity_id');
            if (empty($entities)) {
                $entities = [0];
            }
        }

        $where = ['templates_association.value_field in (?)', 'templates.template_type = ?', 'templates.template_target = ?'];
        $data = [$entities, 'HTML', 'sendmail'];

        $templates = TemplateModel::getWithAssociation([
            'select'    => ['DISTINCT(templates.template_id)', 'templates.template_label', 'templates.subject'],
            'where'     => $where,
            'data'      => $data,
            'orderBy'   => ['templates.template_label']
        ]);

        foreach ($templates as $key => $template) {
            $templates[$key] = [
                'id'      => $template['template_id'],
                'label'   => $template['template_label'],
                'subject' => $template['subject']
            ];
        }

        return $response->withJson(['templates' => $templates]);
    }

    public function mergeEmailTemplate(Request $request, Response $response, array $args)
    {
        if (!Validator::intVal()->validate($args['id'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Route param id is not an integer']);
        }

        $body = $request->getParsedBody();

        if (!Validator::intVal()->validate($body['data']['resId'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body param resId is missing']);
        }

        $resource = ResModel::getById(['resId' => $body['data']['resId'], 'select' => ['destination']]);
        if (!empty($resource['destination'])) {
            $entities = [$resource['destination']];
        } else {
            $entities = UserModel::getEntitiesById(['id' => $GLOBALS['id'], 'select' => ['users_entities.entity_id']]);
            $entities = array_column($entities, 'entity_id');
            if (empty($entities)) {
                $entities = [0];
            }
        }

        $templates = TemplateModel::getWithAssociation([
            'select'  => ['DISTINCT(templates.template_id)', 'templates.template_content', 'templates.subject'],
            'where'   => ['(templates_association.value_field in (?) OR templates_association.template_id IS NULL)', 'templates.template_type = ?', 'templates.template_target = ?', 'templates.template_id = ?'],
            'data'    => [$entities, 'HTML', 'sendmail', $args['id']],
            'orderBy' => ['templates.template_id']
        ]);

        if (empty($templates[0])) {
            return $response->withStatus(400)->withJson(['errors' => 'Template does not exist']);
        }
        $template = $templates[0];
        if (empty($template['template_content'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Template has no content']);
        }

        $dataToMerge = ['userId' => $GLOBALS['id']];
        if (!empty($body['data']) && is_array($body['data'])) {
            $dataToMerge = array_merge($dataToMerge, $body['data']);
        }
        $mergedDocument = MergeController::mergeDocument([
            'content' => $template['template_content'],
            'data'    => $dataToMerge
        ]);
        $mergedDocument = base64_decode($mergedDocument['encodedDocument']);
        $mergedSubject = null;
        if (!empty($template['subject'])) {
            $mergedSubject = MergeController::mergeDocument([
                'content' => $template['subject'],
                'data'    => $dataToMerge
            ]);
            $mergedSubject = base64_decode($mergedSubject['encodedDocument']);
        }

        return $response->withJson(['mergedDocument' => $mergedDocument, 'mergedSubject' => $mergedSubject]);
    }

    private static function controlCreateTemplate(array $aArgs)
    {
        ValidatorModel::notEmpty($aArgs, ['data']);
        ValidatorModel::arrayType($aArgs, ['data']);

        $availableTypes = ['HTML', 'TXT', 'OFFICE', 'OFFICE_HTML'];
        $data = $aArgs['data'];

        $check = Validator::stringType()->notEmpty()->validate($data['label']);
        $check = $check && Validator::stringType()->notEmpty()->validate($data['description']);
        $check = $check && Validator::stringType()->notEmpty()->validate($data['target']);
        $check = $check && Validator::stringType()->notEmpty()->validate($data['type']) && in_array($data['type'], $availableTypes);

        if ($data['type'] == 'HTML' || $data['type'] == 'TXT') {
            $check = $check && Validator::notEmpty()->validate($data['file']['content']);
        }

        if ($data['type'] == 'OFFICE_HTML') {
            $check = $check && (Validator::notEmpty()->validate($data['file']['paper']['content']) || Validator::notEmpty()->validate($data['file']['electronic']['content']));
            $check = $check && Validator::stringType()->notEmpty()->validate($data['template_attachment_type']);
        }

        if (!empty($data['entities'])) {
            $check = $check && Validator::arrayType()->validate($data['entities']);
        }

        return $check;
    }

    private static function controlUpdateTemplate(array $args)
    {
        ValidatorModel::notEmpty($args, ['data']);
        ValidatorModel::arrayType($args, ['data']);

        $data = $args['data'];

        $check = Validator::stringType()->notEmpty()->validate($data['label']);
        $check = $check && Validator::stringType()->notEmpty()->validate($data['description']);

        if ($data['type'] == 'HTML' || $data['type'] == 'TXT') {
            $check = $check && Validator::notEmpty()->validate($data['file']['content']);
        }

        if ($data['type'] == 'OFFICE_HTML') {
            $check = $check && Validator::stringType()->notEmpty()->validate($data['template_attachment_type']);
        }

        if (!empty($data['entities'])) {
            $check = $check && Validator::arrayType()->validate($data['entities']);
        }

        return $check;
    }
}
