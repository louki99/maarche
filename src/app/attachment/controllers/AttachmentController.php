<?php

/**
* Copyright Maarch since 2008 under licence GPLv3.
* See LICENCE.txt file at the root folder for more details.
* This file is part of Maarch software.
*
*/

/**
* @brief Attachment Controller
* @author dev@maarch.org
*/

namespace Attachment\controllers;

use Attachment\models\AttachmentModel;
use Attachment\models\AttachmentTypeModel;
use Contact\models\ContactModel;
use ContentManagement\controllers\MergeController;
use Convert\controllers\ConvertPdfController;
use Convert\controllers\ConvertThumbnailController;
use Convert\models\AdrModel;
use Docserver\models\DocserverModel;
use Docserver\models\DocserverTypeModel;
use Email\models\EmailModel;
use Group\controllers\PrivilegeController;
use History\controllers\HistoryController;
use Resource\controllers\ResController;
use Resource\controllers\ResourceControlController;
use Resource\controllers\StoreController;
use Resource\controllers\WatermarkController;
use Resource\models\ResModel;
use Resource\models\ResourceContactModel;
use Respect\Validation\Validator;
use setasign\Fpdi\Tcpdf\Fpdi;
use SignatureBook\controllers\SignatureBookController;
use Slim\Psr7\Request;
use SrcCore\controllers\CoreController;
use SrcCore\controllers\LogsController;
use SrcCore\http\Response;
use SrcCore\models\CoreConfigModel;
use SrcCore\models\TextFormatModel;
use SrcCore\models\ValidatorModel;
use User\models\UserModel;

class AttachmentController
{
    public function create(Request $request, Response $response)
    {
        $body = $request->getParsedBody();

        $control = AttachmentController::controlAttachment(['body' => $body]);
        if (!empty($control['errors'])) {
            return $response->withStatus(400)->withJson(['errors' => $control['errors']]);
        }

        $id = StoreController::storeAttachment($body);
        if (empty($id) || !empty($id['errors'])) {
            return $response->withStatus(500)->withJson(['errors' => '[AttachmentController create] ' . $id['errors']]);
        }

        ConvertPdfController::convert([
            'resId'     => $id,
            'collId'    => 'attachments_coll'
        ]);

        $customId = CoreConfigModel::getCustomId();
        $customId = empty($customId) ? 'null' : $customId;
        exec("php src/app/convert/scripts/FullTextScript.php --customId {$customId} --resId {$id} --collId attachments_coll --userId {$GLOBALS['id']} > /dev/null &");

        HistoryController::add([
            'tableName' => 'res_attachments',
            'recordId'  => $id,
            'eventType' => 'ADD',
            'info'      => _ATTACHMENT_ADDED,
            'moduleId'  => 'attachment',
            'eventId'   => 'attachmentAdd'
        ]);

        HistoryController::add([
            'tableName' => 'res_letterbox',
            'recordId'  => $body['resIdMaster'],
            'eventType' => 'ADD',
            'info'      => _ATTACHMENT_ADDED . " : {$body['title']}",
            'moduleId'  => 'attachment',
            'eventId'   => 'attachmentAdd'
        ]);

        return $response->withJson(['id' => $id]);
    }

    public function getById(Request $request, Response $response, array $args)
    {
        $attachment = AttachmentModel::getById([
            'id'        => $args['id'],
            'select'    => [
                'res_id as "resId"', 'res_id_master as "resIdMaster"', 'status', 'title', 'identifier as chrono', 'typist', 'modified_by as "modifiedBy"', 'relation', 'attachment_type as type',
                'recipient_id as "recipientId"', 'recipient_type as "recipientType"', 'origin_id as "originId"', 'creation_date as "creationDate"', 'modification_date as "modificationDate"',
                'validation_date as "validationDate"', 'format', 'fulltext_result as "fulltextResult"', 'in_signature_book as "inSignatureBook"', 'in_send_attach as "inSendAttach"'
            ]
        ]);
        if (empty($attachment) || in_array($attachment['status'], ['DEL', 'OBS'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Attachment does not exist']);
        }

        if (!ResController::hasRightByResId(['resId' => [$attachment['resIdMaster']], 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(400)->withJson(['errors' => 'Attachment out of perimeter']);
        }

        if ($attachment['modificationDate'] == $attachment['creationDate']) {
            $attachment['modificationDate'] = null;
        }
        $typist = UserModel::getById(['id' => $attachment['typist'], 'select' => ['firstname', 'lastname']]);
        $attachment['typistLabel'] = $typist['firstname']. ' ' .$typist['lastname'];
        $attachment['modifiedBy'] = UserModel::getLabelledUserById(['id' => $attachment['modifiedBy']]);

        $attachmentsTypes = AttachmentTypeModel::get(['select' => ['type_id', 'label']]);
        $attachmentsTypes = array_column($attachmentsTypes, 'label', 'type_id');
        if (!empty($attachmentsTypes[$attachment['type']])) {
            $attachment['typeLabel'] = $attachmentsTypes[$attachment['type']];
        }

        $oldVersions = [];
        if (!empty($attachment['originId'])) {
            $oldVersions = AttachmentModel::get([
                'select'    => ['res_id as "resId"', 'relation'],
                'where'     => ['(origin_id = ? OR res_id = ?)', 'res_id != ?', 'status not in (?)'],
                'data'      => [$attachment['originId'], $attachment['originId'], $args['id'], ['DEL']],
                'orderBy'   => ['relation DESC']
            ]);
        }
        $attachment['versions'] = $oldVersions;

        if ($attachment['status'] == 'SIGN') {
            $signedResponse = AttachmentModel::get([
                'select'    => ['res_id', 'creation_date', 'typist', 'signatory_user_serial_id'],
                'where'     => ['origin = ?', 'status not in (?)'],
                'data'      => ["{$args['id']},res_attachments", ['DEL']]
            ]);
            if (!empty($signedResponse[0])) {
                $attachment['signedResponse'] = $signedResponse[0]['res_id'];
                if (!empty($signedResponse[0]['signatory_user_serial_id'])) {
                    $attachment['signatory']   = UserModel::getLabelledUserById(['id' => $signedResponse[0]['signatory_user_serial_id']]);
                    $attachment['signatoryId'] = $signedResponse[0]['signatory_user_serial_id'];
                } else {
                    $attachment['signatory']   = UserModel::getLabelledUserById(['id' => $signedResponse[0]['typist']]);
                    $attachment['signatoryId'] = $signedResponse[0]['typist'];
                }
                $attachment['signDate'] = $signedResponse[0]['creation_date'];
            }
        }

        return $response->withJson($attachment);
    }

    public function update(Request $request, Response $response, array $args)
    {
        $attachment = AttachmentModel::getById(['id' => $args['id'], 'select' => ['res_id_master', 'status', 'typist', 'attachment_type']]);
        if (empty($attachment) || !in_array($attachment['status'], ['A_TRA', 'TRA', 'SEND_MASS'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Attachment does not exist']);
        }
        if (!ResController::hasRightByResId(['resId' => [$attachment['res_id_master']], 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(400)->withJson(['errors' => 'Attachment out of perimeter']);
        }
        if ($GLOBALS['id'] != $attachment['typist'] && !PrivilegeController::hasPrivilege(['privilegeId' => 'manage_attachments', 'userId' => $GLOBALS['id']]) && !SignatureBookController::isResourceInSignatureBook(['resId' => $attachment['res_id_master'], 'userId' => $GLOBALS['id'], 'canUpdateDocuments' => true])) {
            return $response->withStatus(403)->withJson(['errors' => 'Attachment out of perimeter']);
        }

        $body = $request->getParsedBody();

        if (empty($body)) {
            return $response->withStatus(400)->withJson(['errors' => 'Body is not set or empty']);
        } elseif (!Validator::stringType()->notEmpty()->validate($body['type'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body type is empty or not a string']);
        }

        if (in_array($attachment['attachment_type'], ['acknowledgement_record_management', 'reply_record_management'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Can not update attachment use for record_management']);
        }

        $attachmentsTypes = AttachmentTypeModel::get(['select' => ['type_id']]);
        $attachmentsTypes = array_column($attachmentsTypes, null, 'type_id');
        if (empty($attachmentsTypes[$body['type']])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body type does not exist']);
        }

        $control = ResourceControlController::controlFileData(['body' => $body]);
        if (!empty($control['errors'])) {
            return $response->withStatus(400)->withJson(['errors' => $control['errors']]);
        }
        $control = AttachmentController::controlRecipient(['body' => $body]);
        if (!empty($control['errors'])) {
            return $response->withStatus(400)->withJson(['errors' => $control['errors']]);
        }
        $control = AttachmentController::controlDates(['body' => $body]);
        if (!empty($control['errors'])) {
            return $response->withStatus(400)->withJson(['errors' => $control['errors']]);
        }

        $body['id'] = $args['id'];
        $isStored = StoreController::storeAttachment($body);
        if (empty($isStored) || !empty($isStored['errors'])) {
            return $response->withStatus(500)->withJson(['errors' => '[AttachmentController update] ' . $isStored['errors']]);
        }

        if (!empty($body['encodedFile'])) {
            AdrModel::deleteAttachmentAdr(['where' => ['res_id = ?'], 'data' => [$args['id']]]);
            ConvertPdfController::convert([
                'resId'     => $args['id'],
                'collId'    => 'attachments_coll'
            ]);

            $customId = CoreConfigModel::getCustomId();
            $customId = empty($customId) ? 'null' : $customId;
            exec("php src/app/convert/scripts/FullTextScript.php --customId {$customId} --resId {$args['id']} --collId attachments_coll --userId {$GLOBALS['id']} > /dev/null &");
        }

        HistoryController::add([
            'tableName' => 'res_attachments',
            'recordId'  => $args['id'],
            'eventType' => 'UP',
            'info'      => _ATTACHMENT_UPDATED,
            'moduleId'  => 'attachment',
            'eventId'   => 'attachmentModification'
        ]);
        HistoryController::add([
            'tableName' => 'res_letterbox',
            'recordId'  => $attachment['res_id_master'],
            'eventType' => 'UP',
            'info'      => _ATTACHMENT_UPDATED . " : {$body['title']}",
            'moduleId'  => 'attachment',
            'eventId'   => 'attachmentModification'
        ]);

        return $response->withStatus(204);
    }

    public function delete(Request $request, Response $response, array $args)
    {
        if (!Validator::notEmpty()->intVal()->validate($args['id'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Route id must be an integer val']);
        }

        $attachment = AttachmentModel::getById(['id' => $args['id'], 'select' => ['origin_id', 'res_id_master', 'attachment_type', 'res_id', 'title', 'typist', 'status']]);
        if (empty($attachment) || $attachment['status'] == 'DEL') {
            return $response->withStatus(400)->withJson(['errors' => 'Attachment does not exist']);
        }
        if (in_array($attachment['attachment_type'], ['acknowledgement_record_management', 'reply_record_management'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Can not delete attachment use for record_management']);
        }
        if (!ResController::hasRightByResId(['resId' => [$attachment['res_id_master']], 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Document out of perimeter']);
        }
        if ($GLOBALS['id'] != $attachment['typist'] && !PrivilegeController::hasPrivilege(['privilegeId' => 'manage_attachments', 'userId' => $GLOBALS['id']]) && !SignatureBookController::isResourceInSignatureBook(['resId' => $attachment['res_id_master'], 'userId' => $GLOBALS['id'], 'canUpdateDocuments' => true])) {
            return $response->withStatus(403)->withJson(['errors' => 'Attachment out of perimeter']);
        }

        if (empty($attachment['origin_id'])) {
            $idToDelete = $attachment['res_id'];
        } else {
            $idToDelete = $attachment['origin_id'];
        }
        AttachmentModel::delete([
            'where' => ['res_id = ? or origin_id = ?'],
            'data'  => [$idToDelete, $idToDelete]
        ]);

        $emails = EmailModel::get([
            'select' => ['id', 'document'],
            'where'  => ["status = 'DRAFT'", "document->>'id' = ?::varchar"],
            'data'   => [$attachment['res_id_master']]
        ]);
        foreach ($emails as $key => $email) {
            $emails[$key]['document'] = json_decode($email['document'], true);
        }

        $emails = array_filter($emails, function ($email) {
            return !empty($email['document']['attachments']);
        });
        $emails = array_filter($emails, function ($email) use ($attachment) {
            $attachmentFound = false;
            foreach ($email['document']['attachments'] as $value) {
                if ($value['id'] == $attachment['res_id'] || $value['id'] == $attachment['origin_id']) {
                    $attachmentFound = true;
                }
            }
            return $attachmentFound;
        });

        foreach ($emails as $key => $email) {
            $emails[$key]['document']['attachments'] = array_filter($emails[$key]['document']['attachments'], function ($element) use ($attachment) {
                return $element['id'] != $attachment['res_id'] && $element['id'] != $attachment['origin_id'];
            });
            $emails[$key]['document']['attachments'] = array_values($emails[$key]['document']['attachments']);
            EmailModel::update([
                'set'   => ['document' => json_encode($emails[$key]['document'])],
                'where' => ['id = ?'],
                'data'  => [$emails[$key]['id']]
            ]);
        }


        HistoryController::add([
            'tableName' => 'res_attachments',
            'recordId'  => $args['id'],
            'eventType' => 'DEL',
            'info'      =>  _ATTACHMENT_DELETED . " : {$attachment['title']}",
            'eventId'   => 'attachmentSuppression',
        ]);

        HistoryController::add([
            'tableName' => 'res_letterbox',
            'recordId'  => $attachment['res_id_master'],
            'eventType' => 'DEL',
            'info'      => _ATTACHMENT_DELETED . " : {$attachment['title']}",
            'moduleId'  => 'attachment',
            'eventId'   => 'attachmentAdd'
        ]);

        return $response->withStatus(204);
    }

    public function getByResId(Request $request, Response $response, array $args)
    {
        if (!Validator::intVal()->validate($args['resId']) || !ResController::hasRightByResId(['resId' => [$args['resId']], 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Document out of perimeter']);
        }

        $queryParams = $request->getQueryParams();
        if (!empty($queryParams['limit']) && !Validator::intVal()->validate($queryParams['limit'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Query limit is not an integer']);
        }

        $excludeAttachmentTypes = ['signed_response', 'summary_sheet'];

        $limit = null;
        if (!empty($queryParams['limit'])) {
            $limit = (int)$queryParams['limit'];
        }

        $attachments = AttachmentModel::get([
            'select'    => [
                'res_id as "resId"', 'identifier as chrono', 'title', 'typist', 'modified_by as "modifiedBy"', 'creation_date as "creationDate"', 'modification_date as "modificationDate"',
                'relation', 'status', 'attachment_type as type', 'in_signature_book as "inSignatureBook"', 'in_send_attach as "inSendAttach"', 'format'
            ],
            'where'     => ['res_id_master = ?', 'status not in (?)', 'attachment_type not in (?)'],
            'data'      => [$args['resId'], ['DEL', 'OBS'], $excludeAttachmentTypes],
            'orderBy'   => ['modification_date DESC'],
            'limit'     => $limit
        ]);

        $attachmentsTypes = AttachmentTypeModel::get(['select' => ['type_id', 'label']]);
        $attachmentsTypes = array_column($attachmentsTypes, 'label', 'type_id');
        foreach ($attachments as $key => $attachment) {
            if ($attachment['modificationDate'] == $attachment['creationDate']) {
                $attachments[$key]['modificationDate'] = null;
            }
            $attachments[$key]['typistLabel'] = '';
            if (!empty($attachment['typist'])) {
                $typist = UserModel::getById(['id' => $attachment['typist'], 'select' => ['firstname', 'lastname']]);
                $attachments[$key]['typistLabel'] = $typist['firstname']. ' ' .$typist['lastname'];
            }
            $attachments[$key]['modifiedBy'] = UserModel::getLabelledUserById(['id' => $attachment['modifiedBy']]);

            if (!empty($attachmentsTypes[$attachment['type']])) {
                $attachments[$key]['typeLabel'] = $attachmentsTypes[$attachment['type']];
            }

            if ($attachment['status'] == 'SIGN') {
                $signedResponse = AttachmentModel::get([
                    'select'    => ['creation_date', 'typist', 'signatory_user_serial_id'],
                    'where'     => ['origin = ?', 'status not in (?)'],
                    'data'      => ["{$attachment['resId']},res_attachments", ['DEL']]
                ]);
                if (!empty($signedResponse[0])) {
                    if (!empty($signedResponse[0]['signatory_user_serial_id'])) {
                        $attachments[$key]['signatory'] = UserModel::getLabelledUserById(['id' => $signedResponse[0]['signatory_user_serial_id']]);
                    } else {
                        $attachments[$key]['signatory'] = UserModel::getLabelledUserById(['id' => $signedResponse[0]['typist']]);
                    }
                    $attachments[$key]['signDate'] = $signedResponse[0]['creation_date'];
                }
            }

            $attachments[$key]['canConvert'] = ConvertPdfController::canConvert(['extension' => $attachments[$key]['format']]);
            unset($attachments[$key]['format']);
        }

        $mailevaConfig = CoreConfigModel::getMailevaConfiguration();
        $mailevaEnabled = false;
        if (!empty($mailevaConfig) && $mailevaConfig['enabled']) {
            $mailevaEnabled = true;
        }

        return $response->withJson(['attachments' => $attachments, 'mailevaEnabled' => $mailevaEnabled]);
    }

    public function setInSignatureBook(Request $request, Response $response, array $aArgs)
    {
        $attachment = AttachmentModel::getById(['id' => $aArgs['id'], 'select' => ['in_signature_book', 'res_id_master', 'title']]);
        if (empty($attachment)) {
            return $response->withStatus(400)->withJson(['errors' => 'Attachment not found']);
        }

        if (!ResController::hasRightByResId(['resId' => [$attachment['res_id_master']], 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Document out of perimeter']);
        }

        AttachmentModel::setInSignatureBook(['id' => $aArgs['id'], 'inSignatureBook' => !$attachment['in_signature_book']]);

        $info = $attachment['in_signature_book'] ? _ATTACH_REMOVE_FROM_SIGNATORY_BOOK : _ATTACH_ADD_TO_SIGNATORY_BOOK;
        HistoryController::add([
            'tableName' => 'res_attachments',
            'recordId'  => $aArgs['id'],
            'eventType' => 'UP',
            'info'      => $info . " : {$attachment['title']}",
            'moduleId'  => 'attachment',
            'eventId'   => 'attachmentModification',
        ]);
        HistoryController::add([
            'tableName' => 'res_letterbox',
            'recordId'  => $attachment['res_id_master'],
            'eventType' => 'UP',
            'info'      => $info . " : " . $attachment['title'],
            'moduleId'  => 'resource',
            'eventId'   => 'resourceModification',
        ]);

        return $response->withJson(['success' => 'success']);
    }

    public function setInSendAttachment(Request $request, Response $response, array $aArgs)
    {
        $attachment = AttachmentModel::getById(['id' => $aArgs['id'], 'select' => ['in_send_attach', 'res_id_master', 'title']]);
        if (empty($attachment)) {
            return $response->withStatus(400)->withJson(['errors' => 'Attachment not found']);
        }

        if (!ResController::hasRightByResId(['resId' => [$attachment['res_id_master']], 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Document out of perimeter']);
        }

        AttachmentModel::setInSendAttachment(['id' => $aArgs['id'], 'inSendAttachment' => !$attachment['in_send_attach']]);

        $info = $attachment['in_send_attach'] ? _ATTACH_REMOVE_FROM_SHIPPING : _ATTACH_ADD_TO_SHIPPING;
        HistoryController::add([
            'tableName' => 'res_attachments',
            'recordId'  => $aArgs['id'],
            'eventType' => 'UP',
            'info'      => $info . " : {$attachment['title']}",
            'moduleId'  => 'attachment',
            'eventId'   => 'attachmentModification',
        ]);
        HistoryController::add([
            'tableName' => 'res_letterbox',
            'recordId'  => $attachment['res_id_master'],
            'eventType' => 'UP',
            'info'      => $info . " : " . $attachment['title'],
            'moduleId'  => 'resource',
            'eventId'   => 'resourceModification',
        ]);

        return $response->withJson(['success' => 'success']);
    }

    public function getThumbnailContent(Request $request, Response $response, array $args)
    {
        if (!Validator::intVal()->validate($args['id'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Route id is not an integer']);
        }

        $attachment = AttachmentModel::get([
            'select'    => ['res_id', 'docserver_id', 'path', 'filename', 'res_id_master'],
            'where'     => ['res_id = ?', 'status not in (?)'],
            'data'      => [$args['id'], ['DEL', 'OBS']],
            'limit'     => 1
        ]);
        if (empty($attachment[0])) {
            return $response->withStatus(400)->withJson(['errors' => 'Attachment not found']);
        }

        if (!ResController::hasRightByResId(['resId' => [$attachment[0]['res_id_master']], 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Document out of perimeter']);
        }

        $pathToThumbnail = 'dist/assets/noThumbnail.png';

        $tnlAdr = AdrModel::getTypedAttachAdrByResId([
            'select'    => ['docserver_id', 'path', 'filename'],
            'resId'     => $args['id'],
            'type'      => 'TNL'
        ]);

        if (empty($tnlAdr)) {
            ConvertThumbnailController::convert(['type' => 'attachment', 'resId' => $args['id']]);
            
            $tnlAdr = AdrModel::getTypedAttachAdrByResId([
                'select'    => ['docserver_id', 'path', 'filename'],
                'resId'     => $args['id'],
                'type'      => 'TNL'
            ]);
        }

        if (!empty($tnlAdr)) {
            $docserver = DocserverModel::getByDocserverId(['docserverId' => $tnlAdr['docserver_id'], 'select' => ['path_template']]);
            if (empty($docserver['path_template']) || !file_exists($docserver['path_template'])) {
                return $response->withStatus(400)->withJson(['errors' => 'Docserver does not exist']);
            }

            $pathToThumbnail = $docserver['path_template'] . str_replace('#', DIRECTORY_SEPARATOR, $tnlAdr['path']) . $tnlAdr['filename'];
        }

        $fileContent = file_get_contents($pathToThumbnail);
        if ($fileContent === false) {
            return $response->withStatus(404)->withJson(['errors' => 'Thumbnail not found on docserver']);
        }

        $mimeAndSize = CoreController::getMimeTypeAndFileSize(['path' => $pathToThumbnail]);
        if (!empty($mimeAndSize['errors'])) {
            return $response->withStatus(400)->withJson(['errors' => $mimeAndSize['errors']]);
        }
        $mimeType = $mimeAndSize['mime'];
        $pathInfo = pathinfo($pathToThumbnail);

        $response->write($fileContent);
        $response = $response->withAddedHeader('Content-Disposition', "inline; filename=maarch.{$pathInfo['extension']}");

        return $response->withHeader('Content-Type', $mimeType);
    }

    public function getThumbnailContentByPage(Request $request, Response $response, array $args)
    {
        if (!Validator::intVal()->validate($args['id'])) {
            return $response->withStatus(400)->withJson(['errors' => 'id param is not an integer']);
        }

        $document = AttachmentModel::getById(['select' => ['res_id_master'], 'id' => $args['id']]);
        if (empty($document)) {
            return $response->withStatus(400)->withJson(['errors' => 'Document does not exist']);
        }

        if (!ResController::hasRightByResId(['resId' => [$document['res_id_master']], 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Document out of perimeter']);
        }

        $childAttachment = AttachmentModel::get([
            'select' => ['res_id'],
            'where'  => ['origin = ?', 'status not in (?)'],
            'data'   => [$args['id'] . ',res_attachments', ['DEL', 'OBS']],
            'limit'  => 1
        ]);
        if (!empty($childAttachment[0])) {
            $args['id'] = $childAttachment[0]['res_id'];
        }

        $docserver = DocserverModel::getByDocserverId(['docserverId' => 'TNL_ATTACH', 'select' => ['path_template']]);
        if (empty($docserver['path_template']) || !file_exists($docserver['path_template'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Docserver does not exist']);
        }

        $adr = AdrModel::getAttachments([
            'select'  => ['path', 'filename'],
            'where'   => ['res_id = ?', 'type = ?'],
            'data'    => [$args['id'], 'TNL' . $args['page']]
        ]);

        $pathToThumbnail = '';
        if (!empty($adr[0])) {
            $pathToThumbnail = $docserver['path_template'] . $adr[0]['path'] . $adr[0]['filename'];
        }
        if (!is_file($pathToThumbnail) || !is_readable($pathToThumbnail)) {
            $control = ConvertThumbnailController::convertOnePage(['type' => 'attachment', 'resId' => $args['id'], 'page' => $args['page']]);
            if (!empty($control['errors'])) {
                return $response->withStatus(400)->withJson(['errors' => $control['errors']]);
            }
            $adr = AdrModel::getAttachments([
                'select'  => ['path', 'filename'],
                'where'   => ['res_id = ?', 'type = ?'],
                'data'    => [$args['id'], 'TNL' . $args['page']]
            ]);
            $pathToThumbnail = $docserver['path_template'] . $adr[0]['path'] . $adr[0]['filename'];
            if (!is_file($pathToThumbnail) || !is_readable($pathToThumbnail)) {
                return $response->withStatus(400)->withJson(['errors' => 'Thumbnail not found on docserver or not readable', 'lang' => 'thumbnailNotFound']);
            }
        }
        $pathToThumbnail = str_replace('#', '/', $pathToThumbnail);

        $fileContent = file_get_contents($pathToThumbnail);
        if ($fileContent === false) {
            return $response->withStatus(404)->withJson(['errors' => 'Page not found on docserver']);
        }

        $base64Content = base64_encode($fileContent);

        $adrPdf = AdrModel::getAttachments([
            'select'  => ['path', 'filename', 'docserver_id'],
            'where'   => ['res_id = ?', 'type = ?'],
            'data'    => [$args['id'], 'PDF']
        ]);

        $docserver = DocserverModel::getByDocserverId(['docserverId' => $adrPdf[0]['docserver_id'], 'select' => ['path_template']]);
        if (empty($docserver['path_template']) || !file_exists($docserver['path_template'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Docserver does not exist']);
        }
        $pathToPdf = $docserver['path_template'] . $adrPdf[0]['path'] . $adrPdf[0]['filename'];
        $pathToPdf = str_replace('#', '/', $pathToPdf);

        $libPath = CoreConfigModel::getSetaSignFormFillerLibrary();
        if (!empty($libPath)) {
            require_once($libPath);
            $document = \SetaPDF_Core_Document::loadByFilename($pathToPdf);
            $pages = $document->getCatalog()->getPages();
            $pageCount = count($pages);
        } else {
            try {
                $pdf = new Fpdi('P', 'pt');
                $pageCount = $pdf->setSourceFile($pathToPdf);
            } catch (\Exception $e) {
                LogsController::add([
                    'isTech'    => true,
                    'moduleId'  => 'attachments',
                    'level'     => 'ERROR',
                    'tableName' => 'res_attachments',
                    'recordId'  => $args['id'],
                    'eventType' => 'thumbnail',
                    'eventId'   => $e->getMessage()
                ]);
                return $response->withStatus(400)->withJson(['errors' => $e->getMessage()]);
            }
            
        }

        return $response->withJson(['fileContent' => $base64Content, 'pageCount' => $pageCount]);
    }
    
    public function getFileContent(Request $request, Response $response, array $args)
    {
        if (!Validator::intVal()->validate($args['id'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Route id is not an integer']);
        }

        $attachment = AttachmentModel::get([
            'select'    => ['res_id', 'docserver_id', 'res_id_master', 'format', 'title', 'signatory_user_serial_id', 'typist', 'attachment_type'],
            'where'     => ['res_id = ?', 'status not in (?)'],
            'data'      => [$args['id'], ['DEL']],
            'limit'     => 1
        ]);
        if (empty($attachment[0])) {
            return $response->withStatus(400)->withJson(['errors' => 'Attachment not found']);
        }
        $attachment = $attachment[0];
        if (!ResController::hasRightByResId(['resId' => [$attachment['res_id_master']], 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Document out of perimeter']);
        }

        $document = ConvertPdfController::getConvertedPdfById(['resId' => $attachment['res_id'], 'collId' => 'attachments_coll']);
        if (!empty($document['errors'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Conversion error : ' . $document['errors']]);
        } elseif ($document['docserver_id'] == $attachment['docserver_id']) {
            return $response->withStatus(400)->withJson(['errors' => 'Document can not be converted']);
        }

        $docserver = DocserverModel::getByDocserverId(['docserverId' => $document['docserver_id'], 'select' => ['path_template', 'docserver_type_id']]);
        if (empty($docserver['path_template']) || !file_exists($docserver['path_template'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Docserver does not exist']);
        }

        $pathToDocument = $docserver['path_template'] . str_replace('#', DIRECTORY_SEPARATOR, $document['path']) . $document['filename'];
        if (!file_exists($pathToDocument)) {
            return $response->withStatus(404)->withJson(['errors' => 'Attachment not found on docserver']);
        }

        $docserverType = DocserverTypeModel::getById(['id' => $docserver['docserver_type_id'], 'select' => ['fingerprint_mode']]);
        $fingerprint   = StoreController::getFingerPrint(['filePath' => $pathToDocument, 'mode' => $docserverType['fingerprint_mode']]);
        if ($document['fingerprint'] != $fingerprint) {
            return $response->withStatus(400)->withJson(['errors' => 'Fingerprints do not match']);
        }

        $fileContent = WatermarkController::watermarkAttachment(['attachmentId' => $args['id'], 'path' => $pathToDocument]);
        if (empty($fileContent)) {
            $fileContent = file_get_contents($pathToDocument);
        }
        if ($fileContent === false) {
            return $response->withStatus(400)->withJson(['errors' => 'Document not found on docserver']);
        }

        HistoryController::add([
            'tableName' => 'res_attachments',
            'recordId'  => $args['id'],
            'eventType' => 'VIEW',
            'info'      => _ATTACH_DISPLAYING . " : {$args['id']}",
            'moduleId'  => 'attachment',
            'eventId'   => 'resview',
        ]);

        HistoryController::add([
            'tableName' => 'res_letterbox',
            'recordId'  => $attachment['res_id_master'],
            'eventType' => 'VIEW',
            'info'      => _ATTACH_DISPLAYING . " : {$attachment['title']}",
            'moduleId'  => 'attachment',
            'eventId'   => 'resview'
        ]);

        $data = $request->getQueryParams();

        $mimeAndSize = CoreController::getMimeTypeAndFileSize(['path' => $pathToDocument]);
        if (!empty($mimeAndSize['errors'])) {
            return $response->withStatus(400)->withJson(['errors' => $mimeAndSize['errors']]);
        }
        $mimeType = $mimeAndSize['mime'];
        $filename = TextFormatModel::formatFilename(['filename' => $attachment['title'], 'maxLength' => 250]);

        if ($data['mode'] == 'base64') {
            $signatoryId = null;
            if ($attachment['attachment_type'] == 'signed_response') {
                if (!empty($attachment['signatory_user_serial_id'])) {
                    $signatoryId = $attachment['signatory_user_serial_id'];
                } else {
                    $signatoryId = $attachment['typist'];
                }
            }

            return $response->withJson([
                'encodedDocument' => base64_encode($fileContent),
                'originalFormat'  => $attachment['format'],
                'filename'        => $filename . '.' . $attachment['format'],
                'mimeType'        => $mimeType,
                'signatoryId'     => $signatoryId
            ]);
        } else {
            $pathInfo = pathinfo($pathToDocument);

            $response->write($fileContent);
            $response = $response->withAddedHeader('Content-Disposition', "inline; filename={$filename}.{$pathInfo['extension']}");
            return $response->withHeader('Content-Type', $mimeType);
        }
    }

    public function getOriginalFileContent(Request $request, Response $response, array $args)
    {
        if (!Validator::intVal()->validate($args['id'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Route id is not an integer']);
        }

        $attachment = AttachmentModel::get([
            'select' => ['res_id', 'docserver_id', 'path', 'filename', 'res_id_master', 'title', 'fingerprint', 'relation'],
            'where'  => ['res_id = ?', 'status not in (?)'],
            'data'   => [$args['id'], ['DEL']],
            'limit'  => 1
        ]);
        if (empty($attachment[0])) {
            return $response->withStatus(400)->withJson(['errors' => 'Attachment not found']);
        }

        if (!ResController::hasRightByResId(['resId' => [$attachment[0]['res_id_master']], 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Document out of perimeter']);
        }

        $attachmentTodisplay = $attachment[0];
        $id = $attachmentTodisplay['res_id'];

        $document['docserver_id'] = $attachmentTodisplay['docserver_id'];
        $document['path']         = $attachmentTodisplay['path'];
        $document['filename']     = $attachmentTodisplay['filename'];
        $document['fingerprint']  = $attachmentTodisplay['fingerprint'];

        $docserver = DocserverModel::getByDocserverId(['docserverId' => $document['docserver_id'], 'select' => ['path_template', 'docserver_type_id']]);
        if (empty($docserver['path_template']) || !file_exists($docserver['path_template'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Docserver does not exist']);
        }

        $pathToDocument = $docserver['path_template'] . str_replace('#', DIRECTORY_SEPARATOR, $document['path']) . $document['filename'];

        if (!file_exists($pathToDocument)) {
            return $response->withStatus(404)->withJson(['errors' => 'Attachment not found on docserver']);
        }

        $docserverType = DocserverTypeModel::getById(['id' => $docserver['docserver_type_id'], 'select' => ['fingerprint_mode']]);
        $fingerprint = StoreController::getFingerPrint(['filePath' => $pathToDocument, 'mode' => $docserverType['fingerprint_mode']]);
        if (empty($document['fingerprint'])) {
            AttachmentModel::update(['set' => ['fingerprint' => $fingerprint], 'where' => ['res_id = ?'], 'data' => [$args['id']]]);
            $document['fingerprint'] = $fingerprint;
        }

        if (!empty($document['fingerprint']) && $document['fingerprint'] != $fingerprint) {
            return $response->withStatus(400)->withJson(['errors' => 'Fingerprints do not match']);
        }

        $fileContent = file_get_contents($pathToDocument);

        if ($fileContent === false) {
            return $response->withStatus(400)->withJson(['errors' => 'Document not found on docserver']);
        }

        $mimeAndSize = CoreController::getMimeTypeAndFileSize(['path' => $pathToDocument]);
        if (!empty($mimeAndSize['errors'])) {
            return $response->withStatus(400)->withJson(['errors' => $mimeAndSize['errors']]);
        }
        $mimeType = $mimeAndSize['mime'];
        $pathInfo = pathinfo($pathToDocument);
        $data     = $request->getQueryParams();
        $filename = TextFormatModel::formatFilename(['filename' => $attachmentTodisplay['title'], 'maxLength' => 250]);
        if ($attachmentTodisplay['relation'] > 1) {
            $filename .= '_V' . $attachmentTodisplay['relation'];
        } else {
            $attachmentVersion = AttachmentModel::get([
                'select'    => [1],
                'where'     => ['origin_id = ?', 'status not in (?)'],
                'data'      => [$args['id'], ['DEL']]
            ]);
            if (!empty($attachmentVersion)) {
                $filename .= '_V1';
            }
        }
        
        HistoryController::add([
            'tableName' => 'res_attachments',
            'recordId'  => $args['id'],
            'eventType' => 'VIEW',
            'info'      => _ATTACH_DISPLAYING . " : {$id}",
            'moduleId'  => 'attachment',
            'eventId'   => 'resview',
        ]);

        HistoryController::add([
            'tableName' => 'res_letterbox',
            'recordId'  => $attachmentTodisplay['res_id_master'],
            'eventType' => 'VIEW',
            'info'      => _ATTACH_DISPLAYING . " : {$attachmentTodisplay['title']}",
            'moduleId'  => 'attachment',
            'eventId'   => 'resview'
        ]);

        if ($data['mode'] == 'base64') {
            return $response->withJson(['encodedDocument' => base64_encode($fileContent), 'extension' => $pathInfo['extension'], 'mimeType' => $mimeType, 'filename' => $filename.'.'.$pathInfo['extension']]);
        } else {
            $response->write($fileContent);
            $response = $response->withAddedHeader('Content-Disposition', "attachment; filename={$filename}.{$pathInfo['extension']}");
            return $response->withHeader('Content-Type', $mimeType);
        }
    }

    public function getByChrono(Request $request, Response $response)
    {
        $queryParams = $request->getQueryParams();
        if (empty($queryParams['chrono'])) {
            return $response->withStatus(403)->withJson(['errors' => 'Query chrono is not set']);
        }

        $attachment = AttachmentModel::get([
            'select'    => ['res_id as "resId"', 'res_id_master as "resIdMaster"', 'status', 'title'],
            'where'     => ['identifier = ?', 'status not in (?)'],
            'data'      => [$queryParams['chrono'], ['DEL', 'OBS']]
        ]);
        if (empty($attachment)) {
            return $response->withStatus(400)->withJson(['errors' => 'Attachment does not exist']);
        }
        $attachment = $attachment[0];

        if (!ResController::hasRightByResId(['resId' => [$attachment['resIdMaster']], 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Attachment out of perimeter']);
        }

        return $response->withJson($attachment);
    }

    public static function getEncodedDocument(array $args)
    {
        ValidatorModel::notEmpty($args, ['id']);
        ValidatorModel::intVal($args, ['id']);
        ValidatorModel::boolType($args, ['original']);

        $document = AttachmentModel::getById(['select' => ['docserver_id', 'path', 'filename', 'title', 'status', 'fingerprint'], 'id' => $args['id']]);

        if (empty($args['original'])) {
            if ($document['status'] == 'SIGN') {
                $signedAttachment = AttachmentModel::get([
                    'select'    => ['res_id'],
                    'where'     => ['origin = ?', 'status not in (?)', 'attachment_type = ?'],
                    'data'      => ["{$args['id']},res_attachments", ['OBS', 'DEL', 'TMP', 'FRZ'], 'signed_response']
                ]);
                if (!empty($signedAttachment[0])) {
                    $args['id'] = $signedAttachment[0]['res_id'];
                }
            }
            $convertedDocument = ConvertPdfController::getConvertedPdfById(['resId' => $args['id'], 'collId' => 'attachments_coll']);

            if (empty($convertedDocument['errors'])) {
                $document['docserver_id'] = $convertedDocument['docserver_id'];
                $document['path'] = $convertedDocument['path'];
                $document['filename'] = $convertedDocument['filename'];
                $document['fingerprint'] = $convertedDocument['fingerprint'];
            }
        }

        $docserver = DocserverModel::getByDocserverId(['docserverId' => $document['docserver_id'], 'select' => ['path_template', 'docserver_type_id']]);
        if (empty($docserver['path_template']) || !file_exists($docserver['path_template'])) {
            return ['errors' => 'Docserver does not exist'];
        }

        $pathToDocument = $docserver['path_template'] . str_replace('#', DIRECTORY_SEPARATOR, $document['path']) . $document['filename'];
        if (!file_exists($pathToDocument)) {
            return ['errors' => 'Document not found on docserver'];
        }

        $docserverType = DocserverTypeModel::getById(['id' => $docserver['docserver_type_id'], 'select' => ['fingerprint_mode']]);
        $fingerprint = StoreController::getFingerPrint(['filePath' => $pathToDocument, 'mode' => $docserverType['fingerprint_mode']]);
        if (empty($convertedDocument) && empty($document['fingerprint'])) {
            AttachmentModel::update(['set' => ['fingerprint' => $fingerprint], 'where' => ['res_id = ?'], 'data' => [$args['id']]]);
            $document['fingerprint'] = $fingerprint;
        }
        if ($document['fingerprint'] != $fingerprint) {
            return ['errors' => 'Fingerprints do not match'];
        }

        $fileContent = file_get_contents($pathToDocument);
        if ($fileContent === false) {
            return ['errors' => 'Document not found on docserver'];
        }

        $encodedDocument = base64_encode($fileContent);

        if (!empty($document['title'])) {
            $document['title'] = TextFormatModel::formatFilename(['filename' => $document['title'], 'maxLength' => 30]);
        }

        $pathInfo = pathinfo($pathToDocument);
        $fileName = (empty($document['title']) ? 'document' : $document['title']) . ".{$pathInfo['extension']}";

        return ['encodedDocument' => $encodedDocument, 'fileName' => $fileName];
    }

    public function getMailingById(Request $request, Response $response, array $args)
    {
        if (!Validator::intVal()->validate($args['id'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Route id is not an integer']);
        }

        $attachment = AttachmentModel::getById([
            'select'    => ['status', 'res_id_master'],
            'id'        => $args['id']
        ]);
        if (empty($attachment)) {
            return $response->withStatus(403)->withJson(['errors' => 'Attachment does not exist']);
        } elseif (!ResController::hasRightByResId(['resId' => [$attachment['res_id_master']], 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Document out of perimeter']);
        } elseif ($attachment['status'] != 'SEND_MASS') {
            return $response->withStatus(403)->withJson(['errors' => 'Attachment is not candidate to mailing']);
        }

        $generated = AttachmentController::generateMailing(['id' => $args['id'], 'userId' => $GLOBALS['id']]);
        if (!empty($generated['errors'])) {
            return $response->withStatus(400)->withJson(['errors' => $generated['errors']]);
        }

        return $response->withStatus(204);
    }

    public static function generateMailing(array $args)
    {
        ValidatorModel::notEmpty($args, ['id', 'userId']);
        ValidatorModel::intVal($args, ['id', 'userId']);

        $attachment = AttachmentModel::getById([
            'select'    => ['res_id_master', 'title', 'identifier', 'docserver_id', 'path', 'filename', 'format', 'attachment_type'],
            'id'        => $args['id']
        ]);

        $resource = ResModel::getById(['resId' => $attachment['res_id_master'], 'select' => ['category_id']]);

        $mode = $resource['category_id'] == 'incoming' ? 'sender' : 'recipient';
        $recipients = ResourceContactModel::get([
            'select'    => ['item_id'],
            'where'     => ['res_id = ?', 'type = ?', 'mode = ?'],
            'data'      => [$attachment['res_id_master'], 'contact', $mode]
        ]);

        $docserver = DocserverModel::getByDocserverId(['docserverId' => $attachment['docserver_id'], 'select' => ['path_template']]);
        if (empty($docserver['path_template']) || !is_dir($docserver['path_template'])) {
            return ['errors' => 'Docserver does not exist'];
        }
        $pathToAttachment = $docserver['path_template'] . str_replace('#', DIRECTORY_SEPARATOR, $attachment['path']) . $attachment['filename'];
        if (!is_file($pathToAttachment)) {
            return ['errors' => 'Attachment not found on docserver'];
        }

        if (empty($recipients)) {
            $mergedDocument = MergeController::mergeDocument([
                'path'  => $pathToAttachment,
                'data'  => ['userId' => $args['userId']]
            ]);

            $data = [
                'title'             => $attachment['title'],
                'encodedFile'       => $mergedDocument['encodedDocument'],
                'format'            => $attachment['format'],
                'resIdMaster'       => $attachment['res_id_master'],
                'chrono'            => $attachment['identifier'],
                'type'              => $attachment['attachment_type'],
                'inSignatureBook'   => true
            ];

            $id = StoreController::storeAttachment($data);
            if (!empty($id['errors'])) {
                return ['errors' => $id['errors']];
            }
            ConvertPdfController::convert([
                'resId'     => $id,
                'collId'    => 'attachments_coll'
            ]);
        } else {
            foreach ($recipients as $key => $recipient) {
                $mergedDocument = MergeController::mergeDocument([
                    'path'  => $pathToAttachment,
                    'data'  => ['userId' => $args['userId'], 'recipientId' => $recipient['item_id'], 'recipientType' => 'contact']
                ]);
    
                $data = [
                    'title'             => $attachment['title'],
                    'encodedFile'       => $mergedDocument['encodedDocument'],
                    'format'            => $attachment['format'],
                    'resIdMaster'       => $attachment['res_id_master'],
                    'chrono'            => $attachment['identifier'] . '-' . ($key+1),
                    'type'              => $attachment['attachment_type'],
                    'recipientId'       => $recipient['item_id'],
                    'recipientType'     => 'contact',
                    'inSignatureBook'   => true
                ];

                $id = StoreController::storeAttachment($data);
                if (!empty($id['errors'])) {
                    return ['errors' => $id['errors']];
                }
            }
        }

        AttachmentModel::update([
            'set'       => [
                'status'  => 'DEL',
            ],
            'where'     => ['res_id = ?'],
            'data'      => [$args['id']]
        ]);

        return true;
    }

    private static function controlAttachment(array $args)
    {
        $body = $args['body'];

        if (empty($body)) {
            return ['errors' => 'Body is not set or empty'];
        } elseif (!Validator::notEmpty()->validate($body['encodedFile'])) {
            return ['errors' => 'Body encodedFile is empty'];
        } elseif (!Validator::stringType()->notEmpty()->validate($body['format'])) {
            return ['errors' => 'Body format is empty or not a string'];
        } elseif (!Validator::notEmpty()->intVal()->validate($body['resIdMaster'])) {
            return ['errors' => 'Body resIdMaster is empty or not an integer'];
        } elseif (!Validator::stringType()->notEmpty()->validate($body['type'])) {
            return ['errors' => 'Body type is empty or not a string'];
        } elseif (!empty($body['title']) && !Validator::length(1, 255)->validate($body['title'])) {
            return ['errors' => 'Body title number of characters must be between 1 and 255 characters)'];
        } elseif (isset($body['status']) && !in_array($body['status'], ['A_TRA', 'TRA', 'SEND_MASS'])) {
            return ['errors' => 'Body status can only be A_TRA, TRA or SEND_MASS'];
        }

        if (!ResController::hasRightByResId(['resId' => [$body['resIdMaster']], 'userId' => $GLOBALS['id']])) {
            return ['errors' => 'Body resIdMaster is out of perimeter'];
        }

        $attachmentsTypes = AttachmentTypeModel::get(['select' => ['type_id']]);
        $attachmentsTypes = array_column($attachmentsTypes, 'type_id', 'type_id');
        if (empty($attachmentsTypes[$body['type']])) {
            return ['errors' => 'Body type does not exist'];
        }

        $control = ResourceControlController::controlFileData(['body' => $body]);
        if (!empty($control['errors'])) {
            return ['errors' => $control['errors']];
        }

        $control = AttachmentController::controlOrigin(['body' => $body]);
        if (!empty($control['errors'])) {
            return ['errors' => $control['errors']];
        }

        $control = AttachmentController::controlRecipient(['body' => $body]);
        if (!empty($control['errors'])) {
            return ['errors' => $control['errors']];
        }

        $control = AttachmentController::controlDates(['body' => $body]);
        if (!empty($control['errors'])) {
            return ['errors' => $control['errors']];
        }

        return true;
    }

    private static function controlOrigin(array $args)
    {
        $body = $args['body'];

        if ($body['type'] == 'signed_response' && empty($body['originId'])) {
            return ['errors' => 'Body type is signed_response and body originId is empty'];
        }
        if (!empty($body['originId'])) {
            if (!Validator::notEmpty()->intVal()->validate($body['originId'])) {
                return ['errors' => 'Body originId is not an integer'];
            }
            $origin = AttachmentModel::getById(['id' => $body['originId'], 'select' => ['res_id_master', 'origin_id', 'status']]);
            if (empty($origin)) {
                return ['errors' => 'Body originId does not exist'];
            } elseif ($origin['res_id_master'] != $body['resIdMaster']) {
                return ['errors' => 'Body resIdMaster is different from origin'];
            }
            if ($body['type'] == 'signed_response') {
                if (!in_array($origin['status'], ['A_TRA', 'TRA', 'SIGN', 'FRZ'])) {
                    return ['errors' => 'Body originId has not an authorized status'];
                }
            } else {
                if (!empty($origin['origin_id'])) {
                    return ['errors' => 'Body originId can not be a version, it must be the original version'];
                }
            }
        }

        return true;
    }

    private static function controlRecipient(array $args)
    {
        $body = $args['body'];

        if (!empty($body['recipientId'])) {
            if (!Validator::notEmpty()->intVal()->validate($body['recipientId'])) {
                return ['errors' => 'Body recipientId is not an integer'];
            }
            if (empty($body['recipientType']) || !in_array($body['recipientType'], ['user', 'contact'])) {
                return ['errors' => 'Body recipientType is empty or not in [user, contact]'];
            }
            if ($body['recipientType'] == 'user') {
                $recipient = UserModel::getById(['id' => $body['recipientId'], 'select' => [1], 'noDeleted' => true]);
            } elseif ($body['recipientType'] == 'contact') {
                $recipient = ContactModel::getById(['id' => $body['recipientId'], 'select' => [1]]);
            }
            if (empty($recipient)) {
                return ['errors' => 'Body recipientId does not exist'];
            }
        }

        return true;
    }

    private static function controlDates(array $args)
    {
        $body = $args['body'];

        if (!empty($body['validationDate'])) {
            if (!Validator::dateTime()->notEmpty()->validate($body['validationDate'])) {
                return ['errors' => "Body validationDate is not a date"];
            }
        }

        if (!empty($body['effectiveDate'])) {
            if (!Validator::dateTime()->notEmpty()->validate($body['effectiveDate'])) {
                return ['errors' => "Body effectiveDate is not a date"];
            }
        }

        return true;
    }
}
