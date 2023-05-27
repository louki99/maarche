<?php

/**
 * Copyright Maarch since 2008 under licence GPLv3.
 * See LICENCE.txt file at the root folder for more details.
 * This file is part of Maarch software.
 *
 */

/**
 * @brief Folder Print Controller
 * @author dev@maarch.org
 */

namespace Resource\controllers;

use AcknowledgementReceipt\models\AcknowledgementReceiptModel;
use Attachment\models\AttachmentModel;
use Attachment\models\AttachmentTypeModel;
use Contact\controllers\ContactController;
use Contact\models\ContactModel;
use Convert\controllers\ConvertPdfController;
use Docserver\models\DocserverModel;
use Docserver\models\DocserverTypeModel;
use Doctype\models\DoctypeModel;
use Email\models\EmailModel;
use Entity\models\EntityModel;
use IndexingModel\models\IndexingModelFieldModel;
use Note\models\NoteEntityModel;
use Note\models\NoteModel;
use Resource\models\ResModel;
use Respect\Validation\Validator;
use setasign\Fpdi\Tcpdf\Fpdi;
use Slim\Psr7\Request;
use SrcCore\http\Response;
use SrcCore\controllers\LogsController;
use SrcCore\models\TextFormatModel;
use SrcCore\models\CoreConfigModel;
use SrcCore\models\ValidatorModel;
use Status\models\StatusModel;
use User\models\UserModel;

class FolderPrintController
{
    /**
     * generateFile returns one PDF per resource
     * if 1 resource, sends a single PDF
     * if several resources, sends one PDF per resource combined into a ZIP archive
     */
    public function generateFile(Request $request, Response $response)
    {
        $body = $request->getParsedBody();

        if (!Validator::notEmpty()->arrayType()->validate($body['resources'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body resources is empty']);
        }

        $defaultUnits = [
            [
                "unit" => "qrcode",
                "label" => ""
            ],
            [
                "unit" => "primaryInformations",
                "label" => _PRIMARY_INFORMATION
            ],
            [
                "unit" => "senderRecipientInformations",
                "label" => _DEST_INFORMATION
            ],
            [
                "unit" => "secondaryInformations",
                "label" => _SECONDARY_INFORMATION
            ],
            [
                "unit" => "diffusionList",
                "label" => _DIFFUSION_LIST
            ],
            [
                "unit" => "opinionWorkflow",
                "label" => _AVIS_WORKFLOW
            ],
            [
                "unit" => "visaWorkflow",
                "label" => _VISA_WORKFLOW
            ]
        ];

        // Array containing all paths to the pdf files to return
        $folderPrintPaths = [];
        $tmpDir = CoreConfigModel::getTmpPath();

        $withSeparators = !empty($body['withSeparator']);

        $unitsSummarySheet = [];
        if (!empty($body['summarySheet'])) {
            $unitsSummarySheet = $body['summarySheet'];
        } elseif (count($body['resources']) > 1) {
            $unitsSummarySheet = $defaultUnits;
        }

        $resIds = array_column($body['resources'], 'resId');

        if (!ResController::hasRightByResId(['resId' => $resIds, 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Document out of perimeter']);
        }
        foreach ($body['resources'] as $resource) {
            // Array containing all paths to the pdf files to merge for this resource
            $documentPaths = [];

            $withSummarySheet = !empty($unitsSummarySheet) || !empty($resource['summarySheet']);

            if ($withSummarySheet) {
                if (!empty($resource['summarySheet']) && is_array($resource['summarySheet'])) {
                    $units = $resource['summarySheet'];
                } else {
                    $units = $unitsSummarySheet;
                }

                $documentPaths[] = FolderPrintController::getSummarySheet(['units' => $units, 'resId' => $resource['resId']]);
            }

            if (!empty($resource['document'])) {
                $document = ResModel::getById([
                    'select' => ['res_id', 'docserver_id', 'path', 'filename', 'fingerprint', 'category_id', 'alt_identifier', 'subject'],
                    'resId'  => $resource['resId']
                ]);
                if (empty($document)) {
                    return $response->withStatus(400)->withJson(['errors' => 'Document does not exist']);
                }

                $resource['altIdentifier'] = $document['alt_identifier'];
                $resource['subject']       = $document['subject'];

                if (empty($document['filename'])) {
                    LogsController::add([
                        'isTech' => true,
                        'moduleId'  => 'folderPrint',
                        'level'     => 'DEBUG',
                        'tableName' => '',
                        'recordId'  => '',
                        'eventType' => 'Error: Document has no file, resId: ' . $document['res_id'],
                        'eventId'   => 'FolderPrint Error'
                    ]);
                } else {
                    $path = FolderPrintController::getDocumentFilePath(['document' => $document, 'collId' => 'letterbox_coll']);
                    if (!empty($path['errors'])) {
                        LogsController::add([
                            'isTech'    => true,
                            'moduleId'  => 'folderPrint',
                            'level'     => 'DEBUG',
                            'tableName' => '',
                            'recordId'  => '',
                            'eventType' => 'Error: ' . $path['errors'],
                            'eventId'   => 'FolderPrint Error'
                        ]);
                    } else {
                        $documentPaths[] = $path;
                    }
                }
            }

            if (!empty($resource['attachments'])) {
                if (is_array($resource['attachments'])) {
                    foreach ($resource['attachments'] as $attachment) {
                        if (!Validator::intVal()->validate($attachment)) {
                            return $response->withStatus(400)->withJson(['errors' => 'Attachment id is not an integer']);
                        }
                    }

                    $attachments = AttachmentModel::get([
                        'select'  => ['res_id', 'res_id_master', 'recipient_type', 'recipient_id', 'typist', 'status', 'attachment_type',
                                      'creation_date', 'identifier', 'title', 'format', 'docserver_id', 'origin'],
                        'where'   => ['res_id in (?)', 'status not in (?)'],
                        'data'    => [$resource['attachments'], ['DEL', 'OBS']],
                        'orderBy' => ['creation_date desc']
                    ]);

                    if (count($attachments) < count($resource['attachments'])) {
                        return $response->withStatus(400)->withJson(['errors' => 'Attachment(s) not found']);
                    }
                } else {
                    $attachments = AttachmentModel::get([
                        'select'  => ['res_id', 'res_id_master', 'recipient_type', 'recipient_id', 'typist', 'status', 'attachment_type',
                                      'creation_date', 'identifier', 'title', 'format', 'docserver_id', 'origin'],
                        'where'   => ['res_id_master = ?', 'status not in (?)'],
                        'data'    => [$resource['resId'], ['DEL', 'OBS']],
                        'orderBy' => ['creation_date desc']
                    ]);
                }

                if (!empty($attachments)) {
                    $chronoResource = ResModel::getById(['select' => ['alt_identifier'], 'resId' => $resource['resId']]);
                    $chronoResource = $chronoResource['alt_identifier'];

                    $attachmentsIds = array_column($attachments, 'res_id');

                    foreach ($attachments as $attachment) {
                        if ($attachment['res_id_master'] != $resource['resId']) {
                            return $response->withStatus(400)->withJson(['errors' => 'Attachment not linked to resource']);
                        }

                        $originAttachment = AttachmentModel::get([
                            'select' => [
                                'res_id', 'res_id_master', 'recipient_type', 'recipient_id', 'typist', 'status', 'attachment_type',
                                'creation_date', 'identifier', 'title', 'format', 'docserver_id', 'origin'
                            ],
                            'where'  => ['origin = ?'],
                            'data'   => [$attachment['res_id'] . ',res_attachments']
                        ]);

                        if (!empty($originAttachment[0])) {
                            $originAttachment = $originAttachment[0];
                            if (in_array($originAttachment['res_id'], $attachmentsIds)) {
                                continue;
                            }

                            $attachment = $originAttachment;
                        }

                        $path = FolderPrintController::getDocumentFilePath(['document' => $attachment, 'collId' => 'attachments_coll']);
                        if (!empty($path['errors'])) {
                            LogsController::add([
                                'isTech'    => true,
                                'moduleId'  => 'folderPrint',
                                'level'     => 'DEBUG',
                                'tableName' => '',
                                'recordId'  => '',
                                'eventType' => 'Error: ' . $path['errors'],
                                'eventId'   => 'FolderPrint Error'
                            ]);
                            continue;
                        }

                        if ($withSeparators) {
                            $documentPaths[] = FolderPrintController::getAttachmentSeparator([
                                'attachment'     => $attachment,
                                'chronoResource' => $chronoResource
                            ]);
                        }

                        $documentPaths[] = $path;
                    }
                }
            }

            if (!empty($resource['notes'])) {
                if (is_array($resource['notes'])) {
                    foreach ($resource['notes'] as $attachment) {
                        if (!Validator::intVal()->validate($attachment)) {
                            return $response->withStatus(400)->withJson(['errors' => 'Note id is not an integer']);
                        }
                    }

                    $allNotes = NoteModel::get([
                        'where'   => ['id in (?)'],
                        'data'    => [$resource['notes']],
                        'orderBy' => ['creation_date desc']
                    ]);

                    $userEntities = EntityModel::getByUserId(['userId' => $GLOBALS['id'], 'select' => ['entity_id']]);
                    $userEntities = array_column($userEntities, 'entity_id');

                    $notes = [];
                    foreach ($allNotes as $attachment) {
                        $allowed = false;

                        if ($attachment['user_id'] == $GLOBALS['id']) {
                            $allowed = true;
                        }

                        $noteEntities = NoteEntityModel::getWithEntityInfo(['select' => ['item_id', 'short_label'], 'where' => ['note_id = ?'], 'data' => [$attachment['id']]]);
                        if (!empty($noteEntities)) {
                            foreach ($noteEntities as $noteEntity) {
                                $attachment['entities_restriction'][] = ['short_label' => $noteEntity['short_label'], 'item_id' => [$noteEntity['item_id']]];

                                if (in_array($noteEntity['item_id'], $userEntities)) {
                                    $allowed = true;
                                }
                            }
                        } else {
                            $allowed = true;
                        }

                        if ($allowed) {
                            $notes[] = $attachment;
                        }
                    }

                    if (count($notes) < count($resource['notes'])) {
                        return $response->withStatus(400)->withJson(['errors' => 'Note(s) not found']);
                    }
                } else {
                    $notes = NoteModel::getByUserIdForResource([
                        'select'  => ['id', 'identifier', 'user_id', 'note_text', 'creation_date'],
                        'userId'  => $GLOBALS['id'],
                        'resId'   => $resource['resId']
                    ]);
                }

                if (!empty($notes)) {
                    $noteFilePath = FolderPrintController::getNotesFilePath(['notes' => $notes, 'resId' => $resource['resId']]);

                    if (!empty($noteFilePath['errors'])) {
                        return $response->withStatus($noteFilePath['code'])->withJson(['errors' => $noteFilePath['errors']]);
                    }

                    if (file_exists($noteFilePath)) {
                        $documentPaths[] = $noteFilePath;
                    } else {
                        return $response->withStatus(500)->withJson(['errors' => 'Notes file not created']);
                    }
                }
            }

            if (!empty($resource['acknowledgementReceipts'])) {
                if (is_array($resource['acknowledgementReceipts'])) {
                    foreach ($resource['acknowledgementReceipts'] as $acknowledgementReceipt) {
                        if (!Validator::intVal()->validate($acknowledgementReceipt)) {
                            return $response->withStatus(400)->withJson(['errors' => 'Acknowledgement Receipt id is not an integer']);
                        }
                    }

                    $acknowledgementReceipts = AcknowledgementReceiptModel::getByIds([
                        'select' => ['id', 'res_id', 'format', 'contact_id', 'user_id', 'creation_date', 'send_date', 'docserver_id', 'path',
                                     'filename', 'fingerprint'],
                        'ids'    => $resource['acknowledgementReceipts']
                    ]);

                    if (count($acknowledgementReceipts) < count($resource['acknowledgementReceipts'])) {
                        return $response->withStatus(400)->withJson(['errors' => 'Acknowledgement Receipt(s) not found']);
                    }
                } else {
                    $acknowledgementReceipts = AcknowledgementReceiptModel::get([
                        'select' => ['id', 'res_id', 'format', 'contact_id', 'user_id', 'creation_date', 'send_date', 'docserver_id', 'path',
                                     'filename', 'fingerprint'],
                        'where'  => ['res_id = ?'],
                        'data'   => [$resource['resId']]
                    ]);
                }

                if (!empty($acknowledgementReceipts)) {
                    foreach ($acknowledgementReceipts as $acknowledgementReceipt) {
                        if ($acknowledgementReceipt['res_id'] != $resource['resId']) {
                            return $response->withStatus(400)->withJson(['errors' => 'Acknowledgement Receipt not linked to resource']);
                        }

                        if ($withSeparators) {
                            $documentPaths[] = FolderPrintController::getAcknowledgementReceiptSeparator(['acknowledgementReceipt' => $acknowledgementReceipt]);
                        }
                        $path = FolderPrintController::getDocumentFilePath(['document' => $acknowledgementReceipt]);

                        if ($acknowledgementReceipt['format'] == 'html') {
                            $path = FolderPrintController::getPathConvertedAcknowledgementReceipt([
                                'acknowledgementReceipt' => $acknowledgementReceipt,
                                'pathHtml'               => $path
                            ]);
                        }

                        $documentPaths[] = $path;
                    }
                }
            }

            if (!empty($resource['emails'])) {
                if (is_array($resource['emails'])) {
                    foreach ($resource['emails'] as $email) {
                        if (!Validator::intVal()->validate($email)) {
                            return $response->withStatus(400)->withJson(['errors' => 'Email id is not an integer']);
                        }
                    }
                    $emails = EmailModel::get([
                        'select'  => ['id', 'user_id', 'sender', 'recipients', 'cc', 'cci', 'object', 'body', 'document', 'send_date', 'status'],
                        'where'   => ['id in (?)', "object NOT LIKE '[AR]%'"],
                        'data'    => [$resource['emails']],
                        'orderBy' => ['creation_date desc']
                    ]);
                    if (count($emails) < count($resource['emails'])) {
                        return $response->withStatus(400)->withJson(['errors' => 'Email(s) not found']);
                    }
                } else {
                    $emails = EmailModel::get([
                        'select'  => ['id', 'user_id', 'sender', 'recipients', 'cc', 'cci', 'object', 'body', 'document', 'send_date', 'status'],
                        'where'   => ["cast(document->>'id' as INT) = ? ", "(object NOT LIKE '[AR]%' OR object is null)"],
                        'data'    => [$resource['resId']],
                        'orderBy' => ['creation_date desc']
                    ]);
                }

                if (!empty($emails)) {
                    foreach ($emails as $email) {
                        $emailDocument = json_decode($email['document'], true);
                        if (!empty($emailDocument['id']) && $emailDocument['id'] != $resource['resId']) {
                            return $response->withStatus(400)->withJson(['errors' => 'Email not linked to resource']);
                        }
                        $emailFilePath = FolderPrintController::getEmailFilePath(['email' => $email, 'resId' => $resource['resId']]);

                        if (file_exists($emailFilePath)) {
                            $documentPaths[] = $emailFilePath;
                        } else {
                            return $response->withStatus(500)->withJson(['errors' => 'Email file not created']);
                        }
                    }
                }
            }

            $linkedAttachmentsPath = [];
            if (!empty($resource['linkedResourcesAttachments'])) {
                if (is_array($resource['linkedResourcesAttachments'])) {
                    foreach ($resource['linkedResourcesAttachments'] as $attachment) {
                        if (!Validator::intVal()->validate($attachment)) {
                            return $response->withStatus(400)->withJson(['errors' => 'LinkedResources attachment id is not an integer']);
                        }
                    }
                    $attachments = AttachmentModel::get([
                        'select'  => ['res_id', 'res_id_master', 'recipient_type', 'recipient_id', 'typist', 'status', 'attachment_type',
                            'creation_date', 'identifier', 'title', 'format', 'docserver_id', 'origin'],
                        'where'   => ['res_id in (?)', 'status not in (?)'],
                        'data'    => [$resource['linkedResourcesAttachments'], ['DEL', 'OBS']],
                        'orderBy' => ['creation_date desc']
                    ]);

                    if (count($attachments) < count($resource['linkedResourcesAttachments'])) {
                        return $response->withStatus(400)->withJson(['errors' => 'LinkedResources attachments not found']);
                    }

                    $linkedResources = array_column($attachments, 'res_id_master');
                    if (!ResController::hasRightByResId(['resId' => $linkedResources, 'userId' => $GLOBALS['id']])) {
                        return $response->withStatus(403)->withJson(['errors' => 'LinkedResources out of perimeter']);
                    }
                } else {
                    $oLinkedResources = ResModel::getById(['resId' => $resource['resId'], 'select' => ['linked_resources']]);
                    $linkedResources = json_decode($oLinkedResources['linked_resources'], true);
                    $attachments = [];
                    if (!empty($linkedResources)) {
                        $attachments = AttachmentModel::get([
                            'select'  => ['res_id', 'res_id_master', 'recipient_type', 'recipient_id', 'typist', 'status', 'attachment_type',
                                'creation_date', 'identifier', 'title', 'format', 'docserver_id', 'origin'],
                            'where'   => ['res_id_master in (?)', 'status not in (?)'],
                            'data'    => [$linkedResources, ['DEL', 'OBS']],
                            'orderBy' => ['creation_date desc']
                        ]);
                    }
                }

                $attachmentsIds = array_column($attachments, 'res_id');

                foreach ($attachments as $attachment) {
                    $resourceInfo = ResModel::getById(['resId' => $attachment['res_id_master'], 'select' => ['alt_identifier']]);
                    $chronoResource = $resourceInfo['alt_identifier'];

                    $originAttachment = AttachmentModel::get([
                        'select' => [
                            'res_id', 'res_id_master', 'recipient_type', 'recipient_id', 'typist', 'status', 'attachment_type',
                            'creation_date', 'identifier', 'title', 'format', 'docserver_id', 'origin'
                        ],
                        'where'  => ['origin = ?'],
                        'data'   => [$attachment['res_id'] . ',res_attachments']
                    ]);

                    if (!empty($originAttachment[0])) {
                        $originAttachment = $originAttachment[0];
                        if (in_array($originAttachment['res_id'], $attachmentsIds)) {
                            continue;
                        }

                        $attachment = $originAttachment;
                    }

                    if ($withSeparators) {
                        $linkedAttachmentsPath[$attachment['res_id_master']][] = FolderPrintController::getAttachmentSeparator([
                            'attachment'     => $attachment,
                            'chronoResource' => $chronoResource
                        ]);
                    }

                    $path = FolderPrintController::getDocumentFilePath(['document' => $attachment, 'collId' => 'attachments_coll']);
                    if (!empty($path['errors'])) {
                        LogsController::add([
                            'isTech'    => true,
                            'moduleId'  => 'folderPrint',
                            'level'     => 'DEBUG',
                            'tableName' => '',
                            'recordId'  => '',
                            'eventType' => 'Error: ' . $path['errors'],
                            'eventId'   => 'FolderPrint Error'
                        ]);
                    } else {
                        $linkedAttachmentsPath[$attachment['res_id_master']][] = $path;
                    }
                }
            }

            if (!empty($resource['linkedResources'])) {
                $controlResource = ResModel::getById(['resId' => $resource['resId'], 'select' => ['linked_resources']]);
                $controlResource['linked_resources'] = json_decode($controlResource['linked_resources'], true);
                if (!is_array($resource['linkedResources'])) {
                    $resource['linkedResources'] = $controlResource['linked_resources'];
                }
                if (!empty($resource['linkedResources']) && !ResController::hasRightByResId(['resId' => $resource['linkedResources'], 'userId' => $GLOBALS['id']])) {
                    return $response->withStatus(403)->withJson(['errors' => 'LinkedResources out of perimeter']);
                }
                foreach ($resource['linkedResources'] as $linkedResource) {
                    if (!Validator::intVal()->validate($linkedResource)) {
                        return $response->withStatus(400)->withJson(['errors' => 'LinkedResources resId is not an integer']);
                    }
                    if (!in_array($linkedResource, $controlResource['linked_resources'])) {
                        return $response->withStatus(400)->withJson(['errors' => 'LinkedResources resId is not linked to resource']);
                    }

                    $document = ResModel::getById([
                        'select' => ['res_id', 'docserver_id', 'path', 'filename', 'fingerprint', 'category_id', 'alt_identifier'],
                        'resId'  => $linkedResource
                    ]);
                    if (empty($document)) {
                        return $response->withStatus(400)->withJson(['errors' => 'LinkedResources Document does not exist']);
                    }

                    if (empty($document['filename'])) {
                        LogsController::add([
                            'isTech'    => true,
                            'moduleId'  => 'folderPrint',
                            'level'     => 'DEBUG',
                            'tableName' => '',
                            'recordId'  => '',
                            'eventType' => 'Error: LinkedResources document has no file, resId: ' . $document['res_id'],
                            'eventId'   => 'FolderPrint Error'
                        ]);
                        continue;
                    }

                    $path = FolderPrintController::getDocumentFilePath(['document' => $document, 'collId' => 'letterbox_coll']);
                    if (!empty($path['errors'])) {
                        LogsController::add([
                            'isTech'    => true,
                            'moduleId'  => 'folderPrint',
                            'level'     => 'DEBUG',
                            'tableName' => '',
                            'recordId'  => '',
                            'eventType' => 'Error: ' . $path['errors'],
                            'eventId'   => 'FolderPrint Error'
                        ]);
                        continue;
                    }

                    if ($withSummarySheet) {
                        $documentPaths[] = FolderPrintController::getSummarySheet(['units' => $units, 'resId' => $linkedResource]);
                    }

                    $documentPaths[] = $path;

                    if (!empty($linkedAttachmentsPath[$linkedResource])) {
                        $documentPaths = array_merge($documentPaths, $linkedAttachmentsPath[$linkedResource]);
                        unset($linkedAttachmentsPath[$linkedResource]);
                    }
                }
            }

            foreach ($linkedAttachmentsPath as $linkedAttachmentPath) {
                $documentPaths = array_merge($documentPaths, $linkedAttachmentPath);
            }


            if (!empty($documentPaths)) {
                if (empty($resource['altIdentifier'] . $resource['subject'])) {
                    $document = ResModel::getById([
                        'select' => ['alt_identifier', 'subject'],
                        'resId'  => $resource['resId']
                    ]);
                    $resource['altIdentifier'] = $document['alt_identifier'];
                    $resource['subject']       = $document['subject'];
                }
                if (empty($resource['altIdentifier'] . $resource['subject'])) {
                    $resource['altIdentifier'] = 'MAARCH';
                    $resource['subject']       = $resource['resId'];
                }
                $filePathOnTmp = trim($tmpDir . TextFormatModel::formatFilename([
                    'filename'  => $resource['altIdentifier'] . '_' . $resource['subject'],
                    'maxLength' => 100
                ])) . '.pdf';
                $filePathOnTmp = str_replace('//', '/', $filePathOnTmp);
                foreach ($documentPaths as $key => $documentPath) {
                    $documentPaths[$key] = TextFormatModel::formatFilename([
                        'filename'  => $documentPath,
                        'maxLength' => 100
                    ]);
                    $documentPaths[$key] = str_replace('//', '/', $documentPath);
                }
                if (file_exists($filePathOnTmp)) {
                    unlink($filePathOnTmp);
                }

                $command = "pdfunite '" . implode("' '", $documentPaths) . "' '" . $filePathOnTmp . "'";
                exec($command . ' 2>&1', $output, $return);

                if (!file_exists($filePathOnTmp)) {
                    return $response->withStatus(500)->withJson(['errors' => 'Merge PDF file not created']);
                }

                // delete all tmp email_*.pdf, attachment_*.pdf, summarySheet_*.pdf, convertedAr_*.pdf and listNotes_*.pdf after merged is complete
                foreach ($documentPaths as $documentPath) {
                    if (strpos($documentPath, "email_") !== false           || strpos($documentPath, "attachment_") !== false   || strpos($documentPath, "summarySheet_") !== false 
                        || strpos($documentPath, "convertedAr_") !== false  || strpos($documentPath, "listNotes_") !== false) {
                        unlink($documentPath);
                    }
                }
                
                $folderPrintPaths[] = $filePathOnTmp;
            }
        }
        if (count($folderPrintPaths) == 0) {
            return $response->withStatus(400)->withJson(['errors' => 'No document to merge']);
        }
        if (count($folderPrintPaths) == 1) {
            $finfo = new \finfo(FILEINFO_MIME_TYPE);

            $fileContent = file_get_contents($folderPrintPaths[0]);
            $mimeType = $finfo->buffer($fileContent);

            $response->write($fileContent);

            $response = $response->withAddedHeader('Content-Disposition', "inline; filename=maarch.pdf");
            return $response->withHeader('Content-Type', $mimeType);
        }

        $filePathOnTmp = str_replace('//', '/', $tmpDir) . 'folderPrint.zip';
        if (file_exists($filePathOnTmp)) {
            unlink($filePathOnTmp);
        }

        $zip = new \ZipArchive;
        if ($zip->open($filePathOnTmp, \ZipArchive::CREATE) !== TRUE) {
            return $response->withStatus(500)->withJson(['errors' => 'Merged ZIP file not created']);
        }
        foreach ($folderPrintPaths as $folderPrintPath) {
            $zip->addFile($folderPrintPath, basename($folderPrintPath));
        }
        $zip->close();

        if (!file_exists($filePathOnTmp)) {
            return $response->withStatus(500)->withJson(['errors' => 'Merged ZIP file not created']);
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $fileContent = file_get_contents($filePathOnTmp);
        $mimeType = $finfo->buffer($fileContent);

        $response->write($fileContent);

        // delete tmp files, partly to avoid filling an existing ZIP and sending more than was requested
        unlink($filePathOnTmp);
        foreach ($folderPrintPaths as $folderPrintPath) {
            unlink($folderPrintPath);
        }

        $response = $response->withAddedHeader('Content-Disposition', 'inline; filename=maarch.zip');
        return $response->withHeader('Content-Type', $mimeType);
    }

    private static function getDocumentFilePath(array $args)
    {
        ValidatorModel::notEmpty($args, ['document']);
        ValidatorModel::arrayType($args, ['document']);
        ValidatorModel::stringType($args, ['collId']);

        $resourceDocument = $args['document'];

        if (!empty($args['collId']) && in_array($args['collId'], ['letterbox_coll', 'attachments_coll'])) {
            $document = ConvertPdfController::getConvertedPdfById(['resId' => $resourceDocument['res_id'], 'collId' => $args['collId']]);
            if (!empty($document['errors'])) {
                return ['errors' => 'Conversion error : ' . $document['errors'], 'code' => 400];
            }

            if (strtolower(pathinfo($document['filename'], PATHINFO_EXTENSION)) != 'pdf') {
                return ['errors' => 'Document can not be converted', 'code' => 400];
            }
        } else {
            $document = $resourceDocument;
        }

        $docserver = DocserverModel::getByDocserverId([
            'docserverId' => $document['docserver_id'], 'select' => ['path_template', 'docserver_type_id']
        ]);
        if (empty($docserver['path_template']) || !file_exists($docserver['path_template'])) {
            return ['errors' => 'Docserver does not exist', 'code' => 400];
        }

        $pathToDocument = $docserver['path_template'] . str_replace('#', DIRECTORY_SEPARATOR, $document['path']) . $document['filename'];

        if (!file_exists($pathToDocument)) {
            return ['errors' => 'Document not found on docserver', 'code' => 404];
        }

        $docserverType = DocserverTypeModel::getById(['id' => $docserver['docserver_type_id'], 'select' => ['fingerprint_mode']]);
        $fingerprint = StoreController::getFingerPrint(['filePath' => $pathToDocument, 'mode' => $docserverType['fingerprint_mode']]);
        if ($document['fingerprint'] != $fingerprint) {
            return ['errors' => 'Fingerprints do not match', 'code' => 400];
        }

        return $pathToDocument;
    }

    private static function getNotesFilePath(array $args)
    {
        ValidatorModel::notEmpty($args, ['notes', 'resId']);
        ValidatorModel::arrayType($args, ['notes']);
        ValidatorModel::intVal($args, ['resId']);

        $notes = [];

        foreach ($args['notes'] as $note) {
            if ($note['identifier'] != $args['resId']) {
                return ['errors' => 'Note not linked to resource', 'code' => 400];
            }

            $user = UserModel::getById(['id' => $note['user_id'], 'select' => ['firstname', 'lastname']]);
            $userName = $user['firstname'] . ' ' . $user['lastname'];

            $noteText = str_replace('←', '<=', $note['note_text']);

            $date = explode('-', date('d-m-Y', strtotime($note['creation_date'])));
            $date = $date[0].'/'.$date[1].'/'.$date[2].' '.date('H:i', strtotime($note['creation_date']));

            $notes[] = ['user' => $userName, 'note' => $noteText, 'date' => $date];
        }

        $pdf = new Fpdi('P', 'pt');
        $pdf->setPrintHeader(false);
        $pdf->AddPage();

        $dimensions     = $pdf->getPageDimensions();
        $widthNoMargins = $dimensions['w'] - $dimensions['rm'] - $dimensions['lm'];
        $bottomHeight   = $dimensions['h'] - $dimensions['bm'];
        $widthNotes     = $widthNoMargins / 2;

        $pdf->SetY($pdf->GetY() + 40);
        if (($pdf->GetY() + 80) > $bottomHeight) {
            $pdf->AddPage();
        }

        $pdf->SetFont('', 'B', 11);
        $pdf->Cell(0, 15, _NOTES_COMMENT, 0, 2, 'L', false);

        $pdf->SetY($pdf->GetY() + 2);
        $pdf->SetFont('', '', 10);

        foreach ($notes as $note) {
            if (($pdf->GetY() + 65) > $bottomHeight) {
                $pdf->AddPage();
            }
            $pdf->SetFont('', 'B', 10);
            $pdf->Cell($widthNotes, 20, $note['user'], 1, 0, 'L', false);
            $pdf->SetFont('', '', 10);
            $pdf->Cell($widthNotes, 20, $note['date'], 1, 1, 'L', false);
            $pdf->MultiCell(0, 40, $note['note'], 1, 'L', false);
            $pdf->SetY($pdf->GetY() + 5);
        }

        $tmpDir = CoreConfigModel::getTmpPath();
        $filePathOnTmp = $tmpDir . 'listNotes_' . $GLOBALS['id'] . '.pdf';
        $pdf->Output($filePathOnTmp, 'F');

        return $filePathOnTmp;
    }

    private static function getAcknowledgementReceiptSeparator(array $args)
    {
        ValidatorModel::notEmpty($args, ['acknowledgementReceipt']);
        ValidatorModel::arrayType($args, ['acknowledgementReceipt']);

        $acknowledgementReceipt = $args['acknowledgementReceipt'];

        $contact = ContactModel::getById([
            'select' => ['id', 'firstname', 'lastname', 'email', 'address_number', 'address_street', 'address_postcode',
                         'address_town', 'address_country', 'company'],
            'id'     => $acknowledgementReceipt['contact_id']
        ]);
        if ($acknowledgementReceipt['format'] == 'html') {
            $displayContact = $contact['firstname'] . ' ' . $contact['lastname'] . ' (' . $contact['email'] . ')';
        } else {
            $displayContact = ContactController::getFormattedContactWithAddress([
                'contact' => $contact
            ]);
            $displayContact = $displayContact['contact']['otherInfo'];
        }

        $creator = UserModel::getById(['id' => $acknowledgementReceipt['user_id']]);

        $creationDate = new \DateTime($acknowledgementReceipt['creation_date']);
        $creationDate = $creationDate->format('d-m-Y H:i');

        if (!empty($acknowledgementReceipt['send_date'])) {
            $sendDate = new \DateTime($acknowledgementReceipt['send_date']);
            $sendDate = $sendDate->format('d-m-Y H:i');
        } else {
            $sendDate = _UNDEFINED;
        }

        $pdf = new Fpdi('P', 'pt');
        $pdf->setPrintHeader(false);
        $pdf->AddPage();

        $dimensions     = $pdf->getPageDimensions();
        $widthNoMargins = $dimensions['w'] - $dimensions['rm'] - $dimensions['lm'];
        $width          = $widthNoMargins / 2;

        $pdf->SetFont('', 'B', 32);
        $pdf->Cell($widthNoMargins, 40, _ACKNOWLEDGEMENT_RECEIPT, 0, 1, 'C', false);

        $pdf->SetY($pdf->GetY() + 40);

        $pdf->SetFont('', '', 10);
        $pdf->MultiCell($width, 30, '<b>' . _CREATED_BY . '</b>', 1, 'L', false, 0, '', '', true, 0, true);
        $pdf->MultiCell($width, 30, $creator['firstname'] . ' ' . $creator['lastname'], 1, 'L', false, 1, '', '', true, 0, true);

        $pdf->MultiCell($width, 30, '<b>' . _CREATED . '</b>', 1, 'L', false, 0, '', '', true, 0, true);
        $pdf->MultiCell($width, 30, $creationDate, 1, 'L', false, 1, '', '', true, 0, true);

        $pdf->MultiCell($width, 30, '<b>' . _SENT_DATE . '</b>', 1, 'L', false, 0, '', '', true, 0, true);
        $pdf->MultiCell($width, 30, $sendDate, 1, 'L', false, 1, '', '', true, 0, true);

        $pdf->MultiCell($width, 30, '<b>' . _FORMAT . '</b>', 1, 'L', false, 0, '', '', true, 0, true);
        $pdf->MultiCell($width, 30, $acknowledgementReceipt['format'], 1, 'L', false, 1, '', '', true, 0, true);

        $pdf->MultiCell($width, 30, '<b>' . _SENT_TO . '</b>', 1, 'L', false, 0, '', '', true, 0, true);
        $pdf->MultiCell($width, 30, $displayContact, 1, 'L', false, 1, '', '', true, 0, true);


        $tmpDir = CoreConfigModel::getTmpPath();
        $filePathOnTmp = $tmpDir . 'convertedAr_' . $acknowledgementReceipt['id'] . '_SEPARATOR_' . $GLOBALS['id'] . '.pdf';
        $pdf->Output($filePathOnTmp, 'F');

        return $filePathOnTmp;
    }

    private static function getPathConvertedAcknowledgementReceipt(array $args)
    {
        ValidatorModel::notEmpty($args, ['acknowledgementReceipt', 'pathHtml']);
        ValidatorModel::arrayType($args, ['acknowledgementReceipt']);
        ValidatorModel::stringType($args, ['pathHtml']);

        $acknowledgementReceipt = $args['acknowledgementReceipt'];

        $contentHtml = file_get_contents($args['pathHtml']);

        $pdf = new Fpdi('P', 'pt');
        $pdf->setPrintHeader(false);
        $pdf->AddPage();

        $pdf->writeHTML($contentHtml);

        $tmpDir = CoreConfigModel::getTmpPath();
        $filePathOnTmp = $tmpDir . 'convertedAr_' . $acknowledgementReceipt['id'] . '_' . $GLOBALS['id'] . '.pdf';
        $pdf->Output($filePathOnTmp, 'F');

        return $filePathOnTmp;
    }

    private static function getAttachmentSeparator(array $args)
    {
        ValidatorModel::notEmpty($args, ['attachment']);
        ValidatorModel::arrayType($args, ['attachment']);
        ValidatorModel::stringType($args, ['chronoResource']);

        $attachment = $args['attachment'];
        $chronoResource = $args['chronoResource'];

        if ($attachment['recipient_type'] == 'user') {
            $displayContact = UserModel::getLabelledUserById(['id' => $attachment['recipient_id']]);
        } elseif ($attachment['recipient_type'] == 'contact') {
            $contact = ContactModel::getById([
                'select' => ['id', 'firstname', 'lastname', 'email', 'address_number', 'address_street', 'address_postcode',
                             'address_town', 'address_country', 'company'],
                'id'     => $attachment['recipient_id']
            ]);
            $displayContact = ContactController::getFormattedContactWithAddress([
                'contact' => $contact
            ]);
            $displayContact = $displayContact['contact']['otherInfo'];
        }

        $creator = UserModel::getById(['id' => $attachment['typist'], 'select' => ['firstname', 'lastname']]);

        $status = StatusModel::getById(['id' => $attachment['status'], 'select' => ['label_status']]);
        $status = $status['label_status'];

        $attachmentTypes = AttachmentTypeModel::get(['select' => ['type_id', 'label']]);
        $attachmentTypes = array_column($attachmentTypes, 'label', 'type_id');
        $attachmentType = $attachmentTypes[$attachment['attachment_type']];

        $creationDate = new \DateTime($attachment['creation_date']);
        $creationDate = $creationDate->format('d-m-Y H:i');

        $pdf = new Fpdi('P', 'pt');
        $pdf->setPrintHeader(false);
        $pdf->AddPage();

        $dimensions     = $pdf->getPageDimensions();
        $widthNoMargins = $dimensions['w'] - $dimensions['rm'] - $dimensions['lm'];
        $width          = $widthNoMargins / 2;

        $pdf->SetFont('', 'B', 32);
        $pdf->Cell($widthNoMargins, 40, _ATTACHMENT, 0, 1, 'C', false);
        $pdf->SetFont('', 'B', 22);
        $pdf->Cell($widthNoMargins, 40, $attachment['identifier'], 0, 1, 'C', false);

        $pdf->SetY($pdf->GetY() + 40);
        $pdf->SetFont('', '', 10);

        $pdf->MultiCell($width, 30, '<b>' . _CHRONO_NUMBER_MASTER . '</b>', 1, 'L', false, 0, '', '', true, 0, true);
        $pdf->MultiCell($width, 30, $chronoResource, 1, 'L', false, 1, '', '', true, 0, true);

        $pdf->MultiCell($width, 30, '<b>' . _SUBJECT . '</b>', 1, 'L', false, 0, '', '', true, 0, true);
        $pdf->MultiCell($width, 30, $attachment['title'], 1, 'L', false, 1, '', '', true, 0, true);

        $pdf->MultiCell($width, 30, '<b>' . _CREATED_BY . '</b>', 1, 'L', false, 0, '', '', true, 0, true);
        $pdf->MultiCell($width, 30, $creator['firstname'] . ' ' . $creator['lastname'], 1, 'L', false, 1, '', '', true, 0, true);

        $pdf->MultiCell($width, 30, '<b>' . _CREATED . '</b>', 1, 'L', false, 0, '', '', true, 0, true);
        $pdf->MultiCell($width, 30, $creationDate, 1, 'L', false, 1, '', '', true, 0, true);

        $pdf->MultiCell($width, 30, '<b>' . _FORMAT . '</b>', 1, 'L', false, 0, '', '', true, 0, true);
        $pdf->MultiCell($width, 30, $attachment['format'], 1, 'L', false, 1, '', '', true, 0, true);

        $pdf->MultiCell($width, 30, '<b>' . _STATUS . '</b>', 1, 'L', false, 0, '', '', true, 0, true);
        $pdf->MultiCell($width, 30, $status, 1, 'L', false, 1, '', '', true, 0, true);

        $pdf->MultiCell($width, 30, '<b>' . _DOCTYPE . '</b>', 1, 'L', false, 0, '', '', true, 0, true);
        $pdf->MultiCell($width, 30, $attachmentType, 1, 'L', false, 1, '', '', true, 0, true);

        $pdf->MultiCell($width, 30, '<b>' . _CONTACT . '</b>', 1, 'L', false, 0, '', '', true, 0, true);
        $pdf->MultiCell($width, 30, $displayContact, 1, 'L', false, 1, '', '', true, 0, true);


        $tmpDir = CoreConfigModel::getTmpPath();
        $filePathOnTmp = $tmpDir . 'attachment_' . $attachment['res_id'] . '_SEPARATOR_' . $GLOBALS['id'] . '.pdf';
        $pdf->Output($filePathOnTmp, 'F');

        return $filePathOnTmp;
    }

    private static function getEmailFilePath(array $args)
    {
        ValidatorModel::notEmpty($args, ['email', 'resId']);
        ValidatorModel::arrayType($args, ['email']);
        ValidatorModel::intVal($args, ['resId']);

        $email = $args['email'];

        $date = new \DateTime($email['send_date']);
        $date = $date->format('d-m-Y H:i');

        $sentDate = _CREATED . ' ' . $date;

        $sentBy = UserModel::getLabelledUserById(['id' => $email['user_id']]);

        $sender = json_decode($email['sender'], true);
        $sender = $sender['email'] ?? _UNDEFINED;

        $sender = $sentBy . " ($sender)";

        $recipients = json_decode($email['recipients'], true);
        $recipients = implode(", ", $recipients);
        $recipients = !empty($recipients) ? $recipients : _UNDEFINED;

        $recipientsCopy = json_decode($email['cc'], true);
        $recipientsCopy = implode(", ", $recipientsCopy);
        $recipientsCopy = !empty($recipientsCopy) ? $recipientsCopy : _UNDEFINED;

        $recipientsCopyHidden = json_decode($email['cci'], true);
        $recipientsCopyHidden = implode(", ", $recipientsCopyHidden);
        $recipientsCopyHidden = !empty($recipientsCopyHidden) ? $recipientsCopyHidden : _UNDEFINED;

        $subject = !empty($email['object']) ? $email['object'] : "<i>" . _EMPTY_SUBJECT . "</i>";

        if ($email['status'] == 'SENT') {
            $status = _EMAIL_SENT;
        } elseif ($email['status'] == 'DRAFT') {
            $status = _EMAIL_DRAFT;
        } elseif ($email['status'] == 'WAITING') {
            $status = _EMAIL_SENDING;
        } else {
            $status = _EMAIL_ERROR_SENT;
        }

        $widthTable_px = "100%";
        $cellPaddingBottom_px = "30px";

        $emailMeta = "<table style=\"width: $widthTable_px;\" cellspacing=\"0\" cellpadding=\"5\">
        <tbody>
        <tr style=\"border-syle: none;\">
        <td style=\"\"><b>" . _EMAIL . "</b></td>
        <td style=\"text-align: right; \">$sentDate</td>
        </tr>
        </tbody>
        </table>
        <table style=\"width: $widthTable_px; border-style: solid; border-color: #000000;\" border=\"1\" cellspacing=\"0\" cellpadding=\"5\">
        <tbody>
        <tr>
        <td style=\"padding-bottom: $cellPaddingBottom_px;\"><b>" . _SENDER . "</b></td>
        <td style=\"padding-bottom: $cellPaddingBottom_px;\">$sender</td>
        </tr>
        <tr>
        <td style=\"padding-bottom: $cellPaddingBottom_px;\"><b>" . _RECIPIENTS . "</b></td>
        <td style=\"padding-bottom: $cellPaddingBottom_px;\">$recipients</td>
        </tr>
        <tr>
        <td style=\"padding-bottom: $cellPaddingBottom_px;\"><b>" . _TO_CC . "</b></td>
        <td style=\"padding-bottom: $cellPaddingBottom_px;\">$recipientsCopy</td>
        </tr>
        <tr>
        <td style=\"padding-bottom: $cellPaddingBottom_px;\"><b>" . _TO_CCI . "</b></td>
        <td style=\"padding-bottom: $cellPaddingBottom_px;\">$recipientsCopyHidden</td>
        </tr>
        <tr>
        <td style=\"padding-bottom: $cellPaddingBottom_px;\"><b>" . _SUBJECT . "</b></td>
        <td style=\"padding-bottom: $cellPaddingBottom_px;\">$subject</td>
        </tr>
        <tr>
        <td style=\"padding-bottom: $cellPaddingBottom_px;\"><b>" . _STATUS . "</b></td>
        <td style=\"padding-bottom: $cellPaddingBottom_px;\">$status</td>
        </tr>
        </tbody>
        </table><br>";
        $emailMeta_emailDataBody = $emailMeta.$email['body'];

        $tmpDir = CoreConfigModel::getTmpPath();
        $filePathInTmpNoExtension = $tmpDir . 'email_' . $email['id'] . '_' . $GLOBALS['id'];
        file_put_contents($filePathInTmpNoExtension . '.html', mb_convert_encoding($emailMeta_emailDataBody, 'HTML', 'UTF-8'));
        ConvertPdfController::convertInPdf(['fullFilename' => $filePathInTmpNoExtension . '.html']);

        if (file_exists($filePathInTmpNoExtension . '.html')) {
            unlink($filePathInTmpNoExtension . '.html');
        }

        return $filePathInTmpNoExtension . '.pdf';
    }
    
    private static function getSummarySheet(array $args)
    {
        ValidatorModel::notEmpty($args, ['units', 'resId']);
        ValidatorModel::arrayType($args, ['units']);
        ValidatorModel::intVal($args, ['resId']);

        $units = $args['units'];
        $resId = $args['resId'];

        $resource = ResModel::getById([
            'select' => ['res_id', 'alt_identifier', 'type_id', 'model_id', 'subject', 'admission_date', 'creation_date',
                         'doc_date', 'initiator', 'typist', 'category_id', 'status', 'priority', 'process_limit_date', 'destination'],
            'resId'  => $resId
        ]);

        if (!empty($resource['type_id'])) {
            $doctype = DoctypeModel::getById(['select' => ['description'], 'id' => $resource['type_id']]);
        }
        $resource['type_label'] = $doctype['description'] ?? '';

        $data = SummarySheetController::prepareData(['units' => $units, 'resourcesIds' => [$resId]]);

        $indexingFields = IndexingModelFieldModel::get([
            'select' => ['identifier', 'unit'],
            'where'  => ['model_id = ?'],
            'data'   => [$resource['model_id']]
        ]);
        $fieldsIdentifier = array_column($indexingFields, 'identifier');

        $pdf = new Fpdi('P', 'pt');
        $pdf->setPrintHeader(false);

        SummarySheetController::createSummarySheet($pdf, [
            'resource'         => $resource,
            'units'            => $units,
            'login'            => $GLOBALS['login'],
            'data'             => $data,
            'fieldsIdentifier' => $fieldsIdentifier
        ]);

        $tmpDir = CoreConfigModel::getTmpPath();
        $filePathOnTmp = $tmpDir . 'summarySheet_' . $resId . '_' . $GLOBALS['id'] . '.pdf';
        $pdf->Output($filePathOnTmp, 'F');

        return $filePathOnTmp;
    }
}
