<?php

/**
 * Copyright Maarch since 2008 under licence GPLv3.
 * See LICENCE.txt file at the root folder for more details.
 * This file is part of Maarch software.
 */

/**
 * @brief Merge Controller
 *
 * @author dev@maarch.org
 */

namespace ContentManagement\controllers;

use Attachment\models\AttachmentModel;
use Contact\controllers\ContactCivilityController;
use Contact\controllers\ContactController;
use Contact\models\ContactModel;
use Convert\controllers\ConvertPdfController;
use Convert\models\AdrModel;
use CustomField\models\CustomFieldModel;
use Docserver\controllers\DocserverController;
use Docserver\models\DocserverModel;
use Doctype\models\DoctypeModel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Entity\models\EntityModel;
use Entity\models\ListInstanceModel;
use IndexingModel\models\IndexingModelModel;
use Note\models\NoteModel;
use Parameter\models\ParameterModel;
use PiBarCode\PiBarCode;
use Resource\models\ResModel;
use Resource\models\ResourceContactModel;
use SrcCore\models\CoreConfigModel;
use SrcCore\models\TextFormatModel;
use SrcCore\models\ValidatorModel;
use Template\controllers\DatasourceController;
use Template\models\TemplateModel;
use User\models\UserModel;

include_once('vendor/tinybutstrong/opentbs/tbs_plugin_opentbs.php');
//include_once('vendor/rafikhaceb/pi-barcode/pi_barcode.php');


class MergeController
{
    public const OFFICE_EXTENSIONS = ['odt', 'ods', 'odp', 'xlsx', 'pptx', 'docx', 'odf'];

    public static function mergeDocument(array $args)
    {
        ValidatorModel::notEmpty($args, ['data']);
        ValidatorModel::arrayType($args, ['data']);
        ValidatorModel::stringType($args, ['path', 'content']);
        ValidatorModel::notEmpty($args['data'], ['userId']);
        ValidatorModel::intVal($args['data'], ['userId']);

        setlocale(LC_TIME, _DATE_LOCALE);

        $tbs = new \clsTinyButStrong();
        $tbs->NoErr = true;
        $tbs->Protect = false;

        if (!empty($args['path'])) {
            $pathInfo = pathinfo($args['path']);
            $extension = $pathInfo['extension'];
        } else {
            $tbs->Source = $args['content'];
            $extension = 'unknow';
            $args['path'] = null;
        }

        if (strtolower($extension) != 'html') {
            $tbs->PlugIn(TBS_INSTALL, OPENTBS_PLUGIN);
        }

        $dataToBeMerge = MergeController::getDataForMerge($args['data']);

        if (!empty($args['path'])) {
            if ($extension == 'odt') {
                $tbs->LoadTemplate($args['path'], OPENTBS_ALREADY_UTF8);
                if ($tbs->Plugin(OPENTBS_FILEEXISTS, 'styles.xml')) {
                    $tbs->LoadTemplate('#styles.xml', OPENTBS_ALREADY_UTF8);
                    foreach ($dataToBeMerge as $key => $value) {
                        $tbs->MergeField($key, $value);
                    }
                }
                $tbs->PlugIn(OPENTBS_SELECT_MAIN);
            } elseif ($extension == 'docx') {
                $tbs->LoadTemplate($args['path'], OPENTBS_ALREADY_UTF8);
                $templates = ['word/header1.xml', 'word/header2.xml', 'word/header3.xml', 'word/footer1.xml', 'word/footer2.xml', 'word/footer3.xml'];
                foreach ($templates as $template) {
                    if ($tbs->Plugin(OPENTBS_FILEEXISTS, $template)) {
                        $tbs->LoadTemplate("#{$template}", OPENTBS_ALREADY_UTF8);
                        foreach ($dataToBeMerge as $key => $value) {
                            $tbs->MergeField($key, $value);
                        }
                    }
                }
                $tbs->PlugIn(OPENTBS_SELECT_MAIN);
            } else {
                $tbs->LoadTemplate($args['path'], OPENTBS_ALREADY_UTF8);
            }
        }

        $pages = 1;
        if ($extension == 'xlsx') {
            $pages = $tbs->PlugIn(OPENTBS_COUNT_SHEETS);
        }

        for ($i = 0; $i < $pages; ++$i) {
            if ($extension == 'xlsx') {
                $tbs->PlugIn(OPENTBS_SELECT_SHEET, $i + 1);
            }
            foreach ($dataToBeMerge as $key => $value) {
                $tbs->MergeField($key, $value);
            }
        }

        if (in_array($extension, MergeController::OFFICE_EXTENSIONS)) {
            $tbs->Show(OPENTBS_STRING);
        } else {
            $tbs->Show(TBS_NOTHING);
        }

        return ['encodedDocument' => base64_encode($tbs->Source)];
    }

    private static function getDataForMerge(array $args)
    {
        ValidatorModel::notEmpty($args, ['userId']);
        ValidatorModel::intVal($args, ['resId', 'userId']);

        //Resource
        if (!empty($args['resId'])) {
            $resource = ResModel::getById(['select' => ['*'], 'resId' => $args['resId']]);

            if (!empty($args['senderId']) && !empty($args['senderType'])) {
                $senders = [['id' => $args['senderId'], 'type' => $args['senderType']]];
            } else {
                $senders = ResourceContactModel::get(['select' => ['item_id as id', 'type'], 'where' => ['res_id = ?', 'mode = ?'], 'data' => [$args['resId'], 'sender'], 'limit' => 1]);
            }

            if (!empty($args['recipientId']) && !empty($args['recipientType'])) {
                $recipients = [['id' => $args['recipientId'], 'type' => $args['recipientType']]];
            } else {
                $recipients = ResourceContactModel::get(['select' => ['item_id as id', 'type'], 'where' => ['res_id = ?', 'mode = ?'], 'data' => [$args['resId'], 'recipient'], 'limit' => 1]);
            }
        } else {
            if (!empty($args['modelId'])) {
                $indexingModel = IndexingModelModel::getById(['id' => $args['modelId'], 'select' => ['category']]);
            }
            if (!empty($args['initiator'])) {
                $entity = EntityModel::getById(['id' => $args['initiator'], 'select' => ['entity_id']]);
                $args['initiator'] = $entity['entity_id'];
            }
            if (!empty($args['destination'])) {
                $entity = EntityModel::getById(['id' => $args['destination'], 'select' => ['entity_id']]);
                $args['destination'] = $entity['entity_id'];
            }
            $resource = [
                'model_id'              => $args['modelId'] ?? null,
                'alt_identifier'        => '[res_letterbox.alt_identifier]',
                'category_id'           => $indexingModel['category'] ?? null,
                'type_id'               => $args['doctype'] ?? null,
                'subject'               => $args['subject'] ?? null,
                'destination'           => $args['destination'] ?? null,
                'initiator'             => $args['initiator'] ?? null,
                'doc_date'              => $args['documentDate'] ?? null,
                'admission_date'        => $args['arrivalDate'] ?? null,
                'departure_date'        => $args['departureDate'] ?? null,
                'process_limit_date'    => $args['processLimitDate'] ?? null,
                'barcode'               => $args['barcode'] ?? null,
                'origin'                => $args['origin'] ?? null
            ];
            $senders = $args['senders'] ?? [];
            $recipients = $args['recipients'] ?? [];
        }
        $allDates = ['doc_date', 'departure_date', 'admission_date', 'process_limit_date', 'opinion_limit_date', 'closing_date', 'creation_date'];
        foreach ($allDates as $date) {
            $resource[$date] = TextFormatModel::formatDate($resource[$date] ?? null, 'd-m-Y');
        }
        $resource['category_id'] = ResModel::getCategoryLabel(['categoryId' => $resource['category_id']]);

        if (!empty($resource['type_id'])) {
            $doctype = DoctypeModel::getById(['id' => $resource['type_id'], 'select' => ['process_delay', 'process_mode', 'description']]);
            $resource['type_label'] = $doctype['description'];
            $resource['process_delay'] = $doctype['process_delay'];
            $resource['process_mode'] = $doctype['process_mode'];
        }

        if (!empty($resource['initiator'])) {
            $initiator = EntityModel::getByEntityId(['entityId' => $resource['initiator'], 'select' => ['*']]);
            $initiator['path'] = EntityModel::getEntityPathByEntityId(['entityId' => $resource['initiator'], 'path' => '']);
            if (!empty($initiator['parent_entity_id'])) {
                $parentInitiator = EntityModel::getByEntityId(['entityId' => $initiator['parent_entity_id'], 'select' => ['*']]);
                $parentInitiator['path'] = EntityModel::getEntityPathByEntityId(['entityId' => $initiator['parent_entity_id'], 'path' => '']);
            }
        }
        if (!empty($resource['destination'])) {
            $destination = EntityModel::getByEntityId(['entityId' => $resource['destination'], 'select' => ['*']]);
            $destination['path'] = EntityModel::getEntityPathByEntityId(['entityId' => $resource['destination'], 'path' => '']);
            if (!empty($destination['parent_entity_id'])) {
                $parentDestination = EntityModel::getByEntityId(['entityId' => $destination['parent_entity_id'], 'select' => ['*']]);
                $parentDestination['path'] = EntityModel::getEntityPathByEntityId(['entityId' => $destination['parent_entity_id'], 'path' => '']);
            }
        }

        //Attachment
        $attachment = [
            'chrono'    => '[attachment.chrono]',
            'title'     => $args['attachment_title'] ?? null
        ];

        //Sender
        $sender = MergeController::formatPerson(['id' => $senders[0]['id'] ?? null, 'type' => $senders[0]['type'] ?? null]);
        //Recipient
        $recipient = MergeController::formatPerson(['id' => $recipients[0]['id'] ?? null, 'type' => $recipients[0]['type'] ?? null]);

        //User
        $currentUser = UserModel::getById(['id' => $args['userId'], 'select' => ['firstname', 'lastname', 'phone', 'mail', 'initials']]);
        $currentUserPrimaryEntity = UserModel::getPrimaryEntityById(['id' => $args['userId'], 'select' => ['entities.*', 'users_entities.user_role as role']]);
        if (!empty($currentUserPrimaryEntity)) {
            $currentUserPrimaryEntity['path'] = EntityModel::getEntityPathByEntityId(['entityId' => $currentUserPrimaryEntity['entity_id'], 'path' => '']);
        }

        //Visas - Visa
        $visas = '';
        $visa = [];
        if (!empty($args['resId'])) {
            $visaWorkflow = ListInstanceModel::get([
                'select'    => ['item_id', 'process_date', 'process_comment', 'requested_signature', 'delegate', 'signatory'],
                'where'     => ['difflist_type = ?', 'res_id = ?'],
                'data'      => ['VISA_CIRCUIT', $args['resId']],
                'orderBy'   => ['listinstance_id']
            ]);
            $visaCount = 0;
            $signCount = 0;
            foreach ($visaWorkflow as $value) {
                $userLabel = UserModel::getLabelledUserById(['id' => $value['item_id']]);
                $primaryEntity = UserModel::getPrimaryEntityById(['id' => $value['item_id'], 'select' => ['entities.entity_label', 'users_entities.user_role as role']]);
                $value['process_comment'] = $value['process_comment'] ?? '';

                if (!empty($value['process_date']) && strpos($value['process_comment'], _INTERRUPTED_WORKFLOW) === false) {
                    $modeLabel = ($value['signatory'] ? _SIGNATORY : _VISA_USER_MIN) . ', ' . TextFormatModel::formatDate($value['process_date']);
                    $mode = ($value['signatory'] ? 'sign' : 'visa');
                } else {
                    $modeLabel = ($value['requested_signature'] ? _SIGNATORY : _VISA_USER_MIN);
                    $mode = ($value['requested_signature'] ? 'sign' : 'visa');
                }

                $delegate = !empty($value['delegate']) ? UserModel::getLabelledUserById(['id' => $value['delegate']]) : '';
                if (!empty($delegate)) {
                    $userLabel = $delegate . ', ' . _INSTEAD_OF . ' ' . $userLabel;
                }
                $visas .= "{$userLabel} (" . (!empty($primaryEntity['role']) ? $primaryEntity['role'].', ' : '') . "{$primaryEntity['entity_label']}) - {$modeLabel}\n";

                if ($mode === 'sign') {
                    $signCount++;
                    $visa['nameSign'.$signCount]   = $userLabel;
                    $visa['roleSign'.$signCount]   = $primaryEntity['role'];
                    $visa['entitySign'.$signCount] = $primaryEntity['entity_label'];
                    $visa['dateSign'.$signCount]   = !empty($value['process_date']) ? TextFormatModel::formatDate($value['process_date']) : null;
                } else {
                    $visaCount++;
                    $visa['nameVisa'.$visaCount]   = $userLabel;
                    $visa['roleVisa'.$visaCount]   = $primaryEntity['role'];
                    $visa['entityVisa'.$visaCount] = $primaryEntity['entity_label'];
                    $visa['dateVisa'.$visaCount]   = !empty($value['process_date']) ? TextFormatModel::formatDate($value['process_date']) : null;
                }
            }
            if ($visaCount > 0) {
                $visa['nameVisaLast'] = $visa['nameVisa'.$visaCount];
                $visa['roleVisaLast'] = $visa['roleVisa'.$visaCount];
                $visa['entityVisaLast'] = $visa['entityVisa'.$visaCount];
                $visa['dateVisaLast'] = $visa['dateVisa'.$visaCount];
            }
            if ($signCount > 0) {
                $visa['nameSignLast'] = $visa['nameSign'.$signCount];
                $visa['roleSignLast'] = $visa['roleSign'.$signCount];
                $visa['entitySignLast'] = $visa['entitySign'.$signCount];
                $visa['dateSignLast'] = $visa['dateSign'.$signCount];
            }
            unset($visaCount);
            unset($signCount);
        }

        //Opinions - Opinion
        $opinions = '';
        $opinion = [];
        if (!empty($args['resId'])) {
            $opinionWorkflow = ListInstanceModel::get([
                'select'    => ['item_id', 'process_date', 'delegate'],
                'where'     => ['difflist_type = ?', 'res_id = ?'],
                'data'      => ['AVIS_CIRCUIT', $args['resId']],
                'orderBy'   => ['listinstance_id']
            ]);
            $visibleNotes = NoteModel::getByUserIdForResource(['select' => ['user_id', 'note_text'], 'resId' => $args['resId'], 'userId' => $GLOBALS['id']]);
            $visibleNotes = array_reverse($visibleNotes);
            $opinionCount = 1;
            foreach ($opinionWorkflow as $value) {
                $valueUserId = $value['delegate'] ?? $value['item_id'];
                $user = UserModel::getById(['id' => $valueUserId, 'select' => ['firstname', 'lastname']]);
                $primaryEntity = UserModel::getPrimaryEntityById(['id' => $valueUserId, 'select' => ['entities.entity_label', 'users_entities.user_role as role']]);
                $processDate = null;
                if (!empty($value['process_date'])) {
                    $processDate = ' - ' . TextFormatModel::formatDate($value['process_date']);
                }
                $opinions .= "{$user['firstname']} {$user['lastname']} ({$primaryEntity['entity_label']}) {$processDate}\n";
                $opinion['firstname'.$opinionCount] = $user['firstname'];
                $opinion['lastname'.$opinionCount] = $user['lastname'];
                $opinion['role'.$opinionCount] = $primaryEntity['role'];
                $opinion['entity'.$opinionCount] = $primaryEntity['entity_label'];
                $opinion['note'.$opinionCount] = [];
                foreach ($visibleNotes as $visibleNote) {
                    $visibleNote['note_text'] = $visibleNote['note_text'] ?? '';
                    if ($visibleNote['user_id'] === $valueUserId && strpos($visibleNote['note_text'], _AVIS_NOTE_PREFIX) === 0) {
                        $opinion['note'.$opinionCount][] = trim(str_replace(_AVIS_NOTE_PREFIX, '', $visibleNote['note_text']));
                    }
                }
                $opinion['note'.$opinionCount] = implode(' ; ', $opinion['note'.$opinionCount]);
                $opinionCount++;
            }
            unset($opinionCount);
            unset($visibleNotes);
        }

        //Copies
        $copies = '';
        if (!empty($args['resId'])) {
            $copyWorkflow = ListInstanceModel::get([
                'select'    => ['item_id', 'item_type'],
                'where'     => ['difflist_type = ?', 'res_id = ?', 'item_mode = ?'],
                'data'      => ['entity_id', $args['resId'], 'cc'],
                'orderBy'   => ['listinstance_id']
            ]);
            foreach ($copyWorkflow as $value) {
                if ($value['item_type'] == 'user_id') {
                    $user = UserModel::getById(['id' => $value['item_id'], 'select' => ['firstname', 'lastname']]);
                    $primaryentity = UserModel::getPrimaryEntityById(['id' => $value['item_id'], 'select' => ['entities.entity_label']]);
                    $label = "{$user['firstname']} {$user['lastname']} ({$primaryentity['entity_label']})";
                } else {
                    $entity = EntityModel::getById(['id' => $value['item_id'], 'select' => ['entity_label']]);
                    $label = $entity['entity_label'];
                }
                $copies .= "{$label}\n";
            }
        }

        //Notes
        $mergedNote = '';
        if (!empty($args['resId'])) {
            $notes = NoteModel::getByUserIdForResource(['select' => ['note_text', 'creation_date', 'user_id'], 'resId' => $args['resId'], 'userId' => $args['userId']]);
            foreach ($notes as $note) {
                $labelledUser = UserModel::getLabelledUserById(['id' => $note['user_id']]);
                $creationDate = TextFormatModel::formatDate($note['creation_date'], 'd/m/Y');
                $mergedNote .= "{$labelledUser} : {$creationDate} : {$note['note_text']}\n";
            }
        }

        //CustomFields
        if (!empty($args['resId'])) {
            $customs = !empty($resource['custom_fields']) ? json_decode($resource['custom_fields'], true) : [];
        } else {
            $customs = !empty($args['customFields']) ? $args['customFields'] : [];
        }

        $customFieldsIds = array_keys($customs);
        if (!empty($customFieldsIds)) {
            $customFields = CustomFieldModel::get([
                'select' => ['id', 'values', 'type'],
                'where'  => ['id in (?)'],
                'data'   => [$customFieldsIds]
            ]);
            if (!empty($args['resId'])) {
                $customFieldsValues = array_column($customFields, 'values', 'id');
            } else {
                $customFieldsValues = $customs;
            }
            $customFieldsTypes = array_column($customFields, 'type', 'id');

            foreach ($customs as $customId => $custom) {
                if (!empty($args['resId'])) {
                    $rawValues = json_decode($customFieldsValues[$customId], true);
                } else {
                    $rawValues = $customFieldsValues[$customId];
                }

                if (!empty($rawValues['table']) && in_array($customFieldsTypes[$customId], ['radio', 'select', 'checkbox'])) {
                    if (!empty($args['resId'])) {
                        $rawValues['resId'] = $args['resId'];
                    }
                    $rawValues = CustomFieldModel::getValuesSQL($rawValues);
                    $rawValues = array_column($rawValues, 'label', 'key');
                    if (is_array($custom)) {
                        foreach ($custom as $key => $value) {
                            $custom[$key] = $rawValues[$value];
                        }
                    } else {
                        $custom = $rawValues[$custom];
                    }
                }

                if (is_array($custom)) {
                    if ($customFieldsTypes[$customId] == 'banAutocomplete') {
                        $resource['customField_' . $customId] = "{$custom[0]['addressNumber']} {$custom[0]['addressStreet']} {$custom[0]['addressTown']} ({$custom[0]['addressPostcode']})";
                    } elseif ($customFieldsTypes[$customId] == 'contact') {
                        $customValues = ContactController::getContactCustomField(['contacts' => $custom]);
                        $resource['customField_' . $customId] = implode("\n", $customValues);
                    } else {
                        $resource['customField_' . $customId] = implode("\n", $custom);
                    }
                } else {
                    $resource['customField_' . $customId] = $custom;
                }
            }
        }

        //Transmissions
        $transmissions = [];
        if (!empty($args['recipientId']) && !empty($args['recipientType'])) {
            $currentTransmission = MergeController::formatPerson(['id' => $args['recipientId'], 'type' => $args['recipientType']]);
            $transmissions['currentContact_lastname'] = $currentTransmission['lastname'] ?? null;
            $transmissions['currentContact_firstname'] = $currentTransmission['firstname'] ?? null;
            $transmissions['currentContact_title'] = $currentTransmission['civility'] ?? null;
            $transmissions['currentContact_function'] = $currentTransmission['function'] ?? null;
        }

        $trKey = 1;
        while (!empty($args["transmissionRecipientId{$trKey}"])) {
            $recipientTransmission = MergeController::formatPerson(['id' => $args["transmissionRecipientId{$trKey}"], 'type' => $args["transmissionRecipientType{$trKey}"]]);
            $transmissions["lastname{$trKey}"] = $recipientTransmission['lastname'] ?? null;
            $transmissions["firstname{$trKey}"] = $recipientTransmission['firstname'] ?? null;
            $transmissions["title{$trKey}"] = $recipientTransmission['civility'] ?? null;
            $transmissions["function{$trKey}"] = $recipientTransmission['function'] ?? null;
            ++$trKey;
        }


        //Datetime
        $datetime = [
            'date'  => date('d-m-Y'),
            'time'  => date('H:i')
        ];

        $dataToBeMerge['res_letterbox']         = $resource;
        $dataToBeMerge['initiator']             = empty($initiator) ? [] : $initiator;
        $dataToBeMerge['parentInitiator']       = empty($parentInitiator) ? [] : $parentInitiator;
        $dataToBeMerge['destination']           = empty($destination) ? [] : $destination;
        $dataToBeMerge['parentDestination']     = empty($parentDestination) ? [] : $parentDestination;
        $dataToBeMerge['attachment']            = $attachment;
        $dataToBeMerge['sender']                = $sender;
        $dataToBeMerge['recipient']             = $recipient;
        $dataToBeMerge['user']                  = $currentUser;
        $dataToBeMerge['userPrimaryEntity']     = $currentUserPrimaryEntity;
        $dataToBeMerge['visas']                 = $visas;
        $dataToBeMerge['visa']                  = $visa;
        $dataToBeMerge['opinions']              = $opinions;
        $dataToBeMerge['opinion']               = $opinion;
        $dataToBeMerge['copies']                = $copies;
        $dataToBeMerge['contact']               = [];
        $dataToBeMerge['notes']                 = $mergedNote;
        $dataToBeMerge['datetime']              = $datetime;
        $dataToBeMerge['transmissions']         = $transmissions;
        if (empty($args['inMailing'])) {
            $dataToBeMerge['attachmentRecipient'] = MergeController::formatPerson(['id' => $args['recipientId'] ?? null, 'type' => $args['recipientType'] ?? null]);
        }

        return $dataToBeMerge;
    }

    public static function mergeChronoDocument(array $args)
    {
        ValidatorModel::stringType($args, ['path', 'content', 'chrono', 'type']);

        $tbs = new \clsTinyButStrong();
        $tbs->NoErr = true;
        $tbs->PlugIn(TBS_INSTALL, OPENTBS_PLUGIN);

        if (!empty($args['path'])) {
            $pathInfo = pathinfo($args['path']);
            $extension = $pathInfo['extension'];
        } else {
            $tbs->Source = $args['content'];
            $extension = 'unknow';
            $args['path'] = null;
        }

        $barcodeFile = CoreConfigModel::getTmpPath() . mt_rand() ."_{$GLOBALS['id']}_barcode.png";
        $generator = new PiBarCode();
        $generator->setCode($args['chrono']);
        $generator->setType('C128');
        $generator->setSize(30, 50);
        $generator->setText($args['chrono']);
        $generator->hideCodeType();
        $generator->setFiletype('PNG');
        $generator->writeBarcodeFile($barcodeFile);

        // Generate QR Code
        $qrcodeFile = CoreConfigModel::getTmpPath() . mt_rand() ."_{$GLOBALS['id']}_qrcode.png";
        $parameter = ParameterModel::getById(['select' => ['param_value_int'], 'id' => 'QrCodePrefix']);
        $prefix = '';
        if ($parameter['param_value_int'] == 1 && !empty($args['chrono'])) {
            $prefix = 'MAARCH_';
        }

        $data = [
            'chrono'      => $prefix . $args['chrono'],
            'resIdMaster' => $args['resIdMaster'],
            'resId'       => $args['resId'],
            'title'       => $args['title']
        ];
        $data = json_encode($data);
        $qrCode = new QrCode($data);
        $pngWriter = new PngWriter();
        $qrCodeResult = $pngWriter->write($qrCode);
        $qrCodeResult->saveToFile($qrcodeFile);

        if (!empty($args['path'])) {
            if ($extension == 'odt') {
                $tbs->LoadTemplate($args['path'], OPENTBS_ALREADY_UTF8);
                if ($tbs->Plugin(OPENTBS_FILEEXISTS, 'styles.xml')) {
                    $tbs->LoadTemplate('#styles.xml', OPENTBS_ALREADY_UTF8);
                    if ($args['type'] == 'resource') {
                        $tbs->MergeField('res_letterbox', ['alt_identifier' => $args['chrono']]);
                    } elseif ($args['type'] == 'attachment') {
                        $tbs->MergeField('attachment', ['chrono' => $args['chrono']]);
                    }
                    $tbs->MergeField('attachments', ['chronoBarCode' => $barcodeFile, 'chronoQrCode' => $qrcodeFile]);
                }
                $tbs->PlugIn(OPENTBS_SELECT_MAIN);
            } elseif ($extension == 'docx') {
                $tbs->LoadTemplate($args['path'], OPENTBS_ALREADY_UTF8);
                $templates = ['word/header1.xml', 'word/header2.xml', 'word/header3.xml', 'word/footer1.xml', 'word/footer2.xml', 'word/footer3.xml'];
                foreach ($templates as $template) {
                    if ($tbs->Plugin(OPENTBS_FILEEXISTS, $template)) {
                        $tbs->LoadTemplate("#{$template}", OPENTBS_ALREADY_UTF8);
                        if ($args['type'] == 'resource') {
                            $tbs->MergeField('res_letterbox', ['alt_identifier' => $args['chrono']]);
                        } elseif ($args['type'] == 'attachment') {
                            $tbs->MergeField('attachment', ['chrono' => $args['chrono']]);
                        }
                        $tbs->MergeField('attachments', ['chronoBarCode' => $barcodeFile, 'chronoQrCode' => $qrcodeFile]);
                    }
                }
                $tbs->PlugIn(OPENTBS_SELECT_MAIN);
            } else {
                $tbs->LoadTemplate($args['path'], OPENTBS_ALREADY_UTF8);
            }
        }

        if ($args['type'] == 'resource') {
            $tbs->MergeField('res_letterbox', ['alt_identifier' => $args['chrono']]);
        } elseif ($args['type'] == 'attachment') {
            $tbs->MergeField('attachment', ['chrono' => $args['chrono']]);
        }

        $tbs->MergeField('attachments', ['chronoBarCode' => $barcodeFile, 'chronoQrCode' => $qrcodeFile]);

        if (in_array($extension, MergeController::OFFICE_EXTENSIONS)) {
            $tbs->Show(OPENTBS_STRING);
        } else {
            $tbs->Show(TBS_NOTHING);
        }

        unlink($qrcodeFile);
        unlink($barcodeFile);

        return ['encodedDocument' => base64_encode($tbs->Source)];
    }

    public static function mergeNotification(array $args)
    {
        $templateInfo                     = TemplateModel::getById(['id' => $args['templateId']]);
        $templateInfo['template_content'] = str_replace('###', ';', $templateInfo['template_content']);
        $templateInfo['template_content'] = str_replace('___', '--', $templateInfo['template_content']);
        $tmpPath                          = CoreConfigModel::getTmpPath();
        $pathToTemplate                   = $tmpPath . 'tmp_template_' . rand() . '_' . rand() . '.html';

        $handle = fopen($pathToTemplate, 'w');
        if (fwrite($handle, $templateInfo['template_content']) === false) {
            return false;
        }
        fclose($handle);

        $datasourceObj = TemplateModel::getDatasourceById(['id' => $templateInfo['template_datasource']]);

        if ($datasourceObj['function']) {
            $function = $datasourceObj['function'];
            $datasources = DatasourceController::$function(['params' => $args['params']]);
        }

        $datasources['datetime'][0]['date'] = date('d-m-Y');
        $datasources['datetime'][0]['time'] = date('H:i:s.u');
        $datasources['datetime'][0]['timestamp'] = time();

        $TBS = new \clsTinyButStrong();
        $TBS->NoErr = true;
        $TBS->LoadTemplate($pathToTemplate);

        foreach ($datasources as $name => $datasource) {
            if (!is_array($datasource)) {
                $TBS->MergeField($name, $datasource);
            } else {
                $TBS->MergeBlock($name, 'array', $datasource);
            }
        }

        $TBS->Show(TBS_NOTHING);

        $myContent = $TBS->Source;
        return $myContent;
    }

    public static function mergeGlobalEmailSignature(array $args)
    {
        $tmpPath        = CoreConfigModel::getTmpPath();
        $pathToTemplate = $tmpPath . 'tmp_template_' . rand() . '_' . rand() . '.html';

        $handle = fopen($pathToTemplate, 'w');
        if (fwrite($handle, $args['content']) === false) {
            return false;
        }
        fclose($handle);

        $datasources['user'] = [UserModel::getById(['select' => ['firstname', 'lastname', 'mail', 'phone', 'initials'], 'id' => $GLOBALS['id']])];
        $datasources['userPrimaryEntity'] = [UserModel::getPrimaryEntityById(['id' => $GLOBALS['id'], 'select' => ['entities.*', 'users_entities.user_role as role']])];

        $TBS = new \clsTinyButStrong();
        $TBS->NoErr = true;
        $TBS->LoadTemplate($pathToTemplate);

        foreach ($datasources as $name => $datasource) {
            if (!is_array($datasource)) {
                $TBS->MergeField($name, $datasource);
            } else {
                $TBS->MergeBlock($name, 'array', $datasource);
            }
        }

        $TBS->Show(TBS_NOTHING);

        $myContent = $TBS->Source;
        return $myContent;
    }

    public static function mergeAction(array $args)
    {
        ValidatorModel::notEmpty($args, ['resId', 'type']);
        ValidatorModel::intVal($args, ['resId']);
        ValidatorModel::stringType($args, ['type']);

        setlocale(LC_TIME, _DATE_LOCALE);
        
        $mergeData = [
            'date'   => date('c'),
            'user'   => UserModel::getLabelledUserById(['id' => $GLOBALS['id']]),
            'entity' => UserModel::getPrimaryEntityById(['id' => $GLOBALS['id'], 'select' => ['*']])
        ];

        if ($args['type'] == 'attachment') {
            $document = AttachmentModel::get([
                'select' => ['res_id', 'docserver_id', 'path', 'filename', 'res_id_master', 'title', 'fingerprint', 'format', 'identifier', 'attachment_type'],
                'where'  => ['res_id = ?', 'status not in (?)'],
                'data'   => [$args['resId'], ['DEL']]
            ]);
            $document = $document[0];

            $docserver = DocserverModel::getByDocserverId(['docserverId' => $document['docserver_id'], 'select' => ['path_template', 'docserver_type_id']]);
            if (empty($docserver['path_template']) || !file_exists($docserver['path_template'])) {
                return ['errors' => 'Docserver does not exist'];
            }

            $pathToDocument = $docserver['path_template'] . str_replace('#', DIRECTORY_SEPARATOR, $document['path']) . $document['filename'];

            if (!file_exists($pathToDocument)) {
                return ['errors' => 'Document not found on docserver'];
            }
        } else {
            $document = ResModel::getById(['select' => ['docserver_id', 'path', 'filename', 'category_id', 'version', 'fingerprint', 'format', 'version'], 'resId' => $args['resId']]);
            if (empty($document['filename'])) {
                return ['errors' => 'Document does not exist'];
            }

            $convertedDocument = AdrModel::getDocuments([
                'select' => ['docserver_id', 'path', 'filename', 'fingerprint'],
                'where'  => ['res_id = ?', 'type = ?', 'version = ?'],
                'data'   => [$args['resId'], 'SIGN', $document['version']],
                'limit'  => 1
            ]);
            $document = $convertedDocument[0] ?? $document;

            $docserver = DocserverModel::getByDocserverId(['docserverId' => $document['docserver_id'], 'select' => ['path_template', 'docserver_type_id']]);
            if (empty($docserver['path_template']) || !file_exists($docserver['path_template'])) {
                return ['errors' => 'Docserver does not exist'];
            }

            $pathToDocument = $docserver['path_template'] . str_replace('#', DIRECTORY_SEPARATOR, $document['path']) . $document['filename'];
        }

        $tbs = new \clsTinyButStrong();
        $tbs->NoErr = true;
        $tbs->PlugIn(TBS_INSTALL, OPENTBS_PLUGIN);

        $pathInfo = pathinfo($pathToDocument);
        $extension = $pathInfo['extension'];
        $filename = $pathInfo['filename'];

        if ($extension == 'odt') {
            $tbs->LoadTemplate($pathToDocument, OPENTBS_ALREADY_UTF8);
        } elseif ($extension == 'docx') {
            $tbs->LoadTemplate($pathToDocument, OPENTBS_ALREADY_UTF8);
            $templates = ['word/header1.xml', 'word/header2.xml', 'word/header3.xml', 'word/footer1.xml', 'word/footer2.xml', 'word/footer3.xml'];
            foreach ($templates as $template) {
                if ($tbs->Plugin(OPENTBS_FILEEXISTS, $template)) {
                    $tbs->LoadTemplate("#{$template}", OPENTBS_ALREADY_UTF8);
                    $tbs->MergeField('signature', $mergeData);
                }
            }
            $tbs->PlugIn(OPENTBS_SELECT_MAIN);
        } else {
            $tbs->LoadTemplate($pathToDocument, OPENTBS_ALREADY_UTF8);
        }

        $tbs->MergeField('signature', $mergeData);

        if (in_array($extension, MergeController::OFFICE_EXTENSIONS)) {
            $tbs->Show(OPENTBS_STRING);
        } else {
            $tbs->Show(TBS_NOTHING);
        }

        if ($args['type'] == 'attachment') {
            $tmpPath = CoreConfigModel::getTmpPath();
            $fileNameOnTmp = rand() . $filename;
            file_put_contents($tmpPath . $fileNameOnTmp . '.' . $extension, $tbs->Source);

            ConvertPdfController::convertInPdf(['fullFilename' => $tmpPath.$fileNameOnTmp.'.'.$extension]);

            if (!file_exists($tmpPath.$fileNameOnTmp.'.pdf')) {
                return ['errors' => 'Merged document conversion failed'];
            }

            $content = file_get_contents($tmpPath.$fileNameOnTmp.'.pdf');

            $storeResult = DocserverController::storeResourceOnDocServer([
                'collId'          => 'attachments_coll',
                'docserverTypeId' => 'CONVERT',
                'encodedResource' => base64_encode($content),
                'format'          => 'pdf'
            ]);

            if (!empty($storeResult['errors'])) {
                return ['errors' => $storeResult['errors']];
            }

            unlink($tmpPath.$fileNameOnTmp.'.'.$extension);
            unlink($tmpPath.$fileNameOnTmp.'.pdf');

            AdrModel::createAttachAdr([
                'resId'       => $args['resId'],
                'type'        => 'TMP',
                'docserverId' => $storeResult['docserver_id'],
                'path'        => $storeResult['destination_dir'],
                'filename'    => $storeResult['file_destination_name'],
                'fingerprint' => $storeResult['fingerPrint']
            ]);
        } else {
            $tmpPath = CoreConfigModel::getTmpPath();
            $fileNameOnTmp = rand() . $document['filename'];

            file_put_contents($tmpPath . $fileNameOnTmp . '.' . $extension, $tbs->Source);

            ConvertPdfController::convertInPdf(['fullFilename' => $tmpPath.$fileNameOnTmp.'.'.$extension]);

            if (!file_exists($tmpPath.$fileNameOnTmp.'.pdf')) {
                return ['errors' => 'Merged document conversion failed'];
            }

            $content = file_get_contents($tmpPath.$fileNameOnTmp.'.pdf');

            $storeResult = DocserverController::storeResourceOnDocServer([
                'collId'          => 'letterbox_coll',
                'docserverTypeId' => 'CONVERT',
                'encodedResource' => base64_encode($content),
                'format'          => 'pdf'
            ]);

            if (!empty($storeResult['errors'])) {
                return ['errors' => $storeResult['errors']];
            }

            unlink($tmpPath.$fileNameOnTmp.'.'.$extension);
            unlink($tmpPath.$fileNameOnTmp.'.pdf');

            AdrModel::createDocumentAdr([
                'resId'       => $args['resId'],
                'type'        => 'TMP',
                'docserverId' => $storeResult['docserver_id'],
                'path'        => $storeResult['destination_dir'],
                'filename'    => $storeResult['file_destination_name'],
                'version'     => $document['version'] + 1,
                'fingerprint' => $storeResult['fingerPrint']
            ]);
        }

        return true;
    }

    private static function formatPerson(array $args)
    {
        $person = [];

        if (!empty($args['id']) && !empty($args['type'])) {
            if ($args['type'] == 'contact') {
                $person = ContactModel::getById([
                    'id' => $args['id'],
                    'select' => [
                        'civility', 'firstname', 'lastname', 'company', 'department', 'function', 'address_number', 'address_street', 'address_town',
                        'address_additional1', 'address_additional2', 'address_postcode', 'address_town', 'address_country', 'phone', 'email', 'custom_fields'
                    ]
                ]);
                $postalAddress = ContactController::getContactAfnor($person);
                unset($postalAddress[0]);
                foreach ($postalAddress as $key => $value) {
                    if (empty($value)) {
                        unset($postalAddress[$key]);
                    }
                }
                $postalAddress = array_values($postalAddress);
                $person['postal_address'] = implode("\n", $postalAddress);
                if (!empty($person['civility'])) {
                    $person['civility'] = ContactCivilityController::getLabelById(['id' => $person['civility']]);
                } else {
                    $person['civility'] = '';
                }
                $customFields = json_decode($person['custom_fields'] ?? '[]', true);
                unset($person['custom_fields']);
                if (!empty($customFields)) {
                    foreach ($customFields as $key => $customField) {
                        $person["customField_{$key}"] = is_array($customField) ? implode("\n", $customField) : $customField;
                    }
                }
            } elseif ($args['type'] == 'user') {
                $person = UserModel::getById(['id' => $args['id'], 'select' => ['firstname', 'lastname']]);
            } elseif ($args['type'] == 'entity') {
                $person = EntityModel::getById(['id' => $args['id'], 'select' => ['entity_label as lastname']]);
            }
        }

        return $person;
    }
}
