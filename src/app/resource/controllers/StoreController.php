<?php

/**
 * Copyright Maarch since 2008 under licence GPLv3.
 * See LICENCE.txt file at the root folder for more details.
 * This file is part of Maarch software.
 *
 */

/**
 * @brief Store Controller
 * @author dev@maarch.org
 * @ingroup core
 */

namespace Resource\controllers;

use Attachment\models\AttachmentModel;
use Attachment\models\AttachmentTypeModel;
use ContentManagement\controllers\MergeController;
use CustomField\models\CustomFieldModel;
use Docserver\controllers\DocserverController;
use Entity\models\EntityModel;
use IndexingModel\models\IndexingModelFieldModel;
use IndexingModel\models\IndexingModelModel;
use Resource\models\ChronoModel;
use Resource\models\ResModel;
use SrcCore\models\CoreConfigModel;
use SrcCore\models\DatabaseModel;
use SrcCore\models\ValidatorModel;
use User\models\UserModel;

class StoreController
{
    public static function storeResource(array $args)
    {
        try {
            if (empty($args['resId'])) {
                $resId = DatabaseModel::getNextSequenceValue(['sequenceId' => 'res_id_mlb_seq']);

                $data = $args;
                $data['resId'] = $resId;
                $data = StoreController::prepareResourceStorage($data);
            } else {
                $resId = $args['resId'];
                $data = StoreController::prepareUpdateResourceStorage($args);
            }

            if (!empty($args['encodedFile'])) {
                $fileContent = base64_decode(str_replace(['-', '_'], ['+', '/'], $args['encodedFile']));

                if (empty($args['resId']) && in_array($args['format'], MergeController::OFFICE_EXTENSIONS) && empty($args['integrations']['inMailing'])) {
                    $tmpPath     = CoreConfigModel::getTmpPath();
                    $uniqueId    = CoreConfigModel::uniqueId();
                    $tmpFilename = "storeTmp_{$GLOBALS['id']}_{$uniqueId}.{$args['format']}";
                    file_put_contents($tmpPath . $tmpFilename, $fileContent);
                    $fileContent = MergeController::mergeChronoDocument(['chrono' => $data['alt_identifier'], 'path' => $tmpPath . $tmpFilename, 'type' => 'resource', 'resIdMaster' => $resId, 'resId' => null, 'title' => $data['subject']]);
                    $fileContent = base64_decode($fileContent['encodedDocument']);
                    unlink($tmpPath . $tmpFilename);
                }

                $storeResult = DocserverController::storeResourceOnDocServer([
                    'collId'            => 'letterbox_coll',
                    'docserverTypeId'   => 'DOC',
                    'encodedResource'   => base64_encode($fileContent),
                    'format'            => strtolower($args['format'])
                ]);
                if (!empty($storeResult['errors'])) {
                    return ['errors' => '[storeResource] ' . $storeResult['errors']];
                }

                $data['docserver_id']   = $storeResult['docserver_id'];
                $data['filename']       = $storeResult['file_destination_name'];
                $data['filesize']       = $storeResult['fileSize'];
                $data['path']           = $storeResult['directory'];
                $data['fingerprint']    = $storeResult['fingerPrint'];
                $data['format']         = $args['format'];
            }

            if (empty($args['resId'])) {
                ResModel::create($data);
            } else {
                ResModel::update(['set' => $data, 'where' => ['res_id = ?'], 'data' => [$args['resId']]]);
            }

            return $resId;
        } catch (\Exception $e) {
            return ['errors' => '[storeResource] ' . $e->getMessage()];
        }
    }

    public static function storeAttachment(array $args)
    {
        try {
            if (empty($args['id'])) {
                $resId = DatabaseModel::getNextSequenceValue(['sequenceId' => 'res_attachment_res_id_seq']);
                $data = $args;
                $data['resId'] = $resId;
                $data = StoreController::prepareAttachmentStorage($data);
            } else {
                $resId = $args['id'];
                $data = StoreController::prepareUpdateAttachmentStorage($args);
            }

            if (!empty($args['encodedFile'])) {
                $fileContent = base64_decode(str_replace(['-', '_'], ['+', '/'], $args['encodedFile']));

                if (empty($args['id']) && in_array($args['format'], MergeController::OFFICE_EXTENSIONS) && $data['status'] != 'SEND_MASS') {
                    $tmpPath = CoreConfigModel::getTmpPath();
                    $uniqueId = CoreConfigModel::uniqueId();
                    $tmpFilename = "storeTmp_{$GLOBALS['id']}_{$uniqueId}.{$args['format']}";
                    file_put_contents($tmpPath . $tmpFilename, $fileContent);
                    $fileContent = MergeController::mergeChronoDocument(['chrono' => $data['identifier'], 'path' => $tmpPath . $tmpFilename, 'type' => 'attachment', 'resIdMaster' => $data['res_id_master'], 'resId' => $resId, 'title' => $data['title']]);
                    $fileContent = base64_decode($fileContent['encodedDocument']);
                    unlink($tmpPath . $tmpFilename);
                }

                $storeResult = DocserverController::storeResourceOnDocServer([
                    'collId'            => 'attachments_coll',
                    'docserverTypeId'   => 'DOC',
                    'encodedResource'   => base64_encode($fileContent),
                    'format'            => strtolower($args['format'])
                ]);
                if (!empty($storeResult['errors'])) {
                    return ['errors' => '[storeAttachment] ' . $storeResult['errors']];
                }

                $data['docserver_id']   = $storeResult['docserver_id'];
                $data['filename']       = $storeResult['file_destination_name'];
                $data['filesize']       = $storeResult['fileSize'];
                $data['path']           = $storeResult['directory'];
                $data['fingerprint']    = $storeResult['fingerPrint'];
                $data['format']         = $args['format'];
            }

            if (empty($args['id'])) {
                $signedByDefault = AttachmentTypeModel::getByTypeId(['typeId' => $data['attachment_type'], 'select' => ['signed_by_default']]);
                $signedByDefault = $signedByDefault['signed_by_default'] ?? false;
                if ($signedByDefault) {
                    $data['status'] = 'SIGN';
                }
                $id = AttachmentModel::create($data);
                if ($signedByDefault) {
                    $versionData = $data;
                    $versionData['res_id']          = DatabaseModel::getNextSequenceValue(['sequenceId' => 'res_attachment_res_id_seq']);
                    $versionData['status']          = 'TRA';
                    $versionData['attachment_type'] = 'signed_response';
                    $versionData['origin']          = "{$id},res_attachments";
                    AttachmentModel::create($versionData);
                }
            } else {
                AttachmentModel::update(['set' => $data, 'where' => ['res_id = ?'], 'data' => [$args['id']]]);
                $id = $args['id'];
            }

            return $id;
        } catch (\Exception $e) {
            return ['errors' => '[storeAttachment] ' . $e->getMessage()];
        }
    }

    public static function setDisabledAndEmptyMandatoryFields(array $args)
    {
        $fields = IndexingModelFieldModel::get([
            'select' => ['identifier', 'default_value', 'enabled', 'mandatory'],
            'where'  => ['model_id = ?', '(enabled = ? OR mandatory = ?)'],
            'data'   => [$args['modelId'], 'false', 'true']
        ]);
        foreach ($fields as $field) {
            $defaultValue = null;
            if (!empty($field['default_value'])) {
                $defaultValue = json_decode($field['default_value'], true);
            }

            if ($defaultValue == "_TODAY") {
                $defaultValue = date('d-m-Y');
            } elseif ($defaultValue == "#myPrimaryEntity") {
                $entity       = UserModel::getPrimaryEntityById(['id' => $GLOBALS['id'], 'select' => ['entities.id']]);
                $defaultValue = $entity['id'];
            }
            if (empty($field['enabled']) || (!empty($field['mandatory']) && !isset($args[$field['identifier']]) && $defaultValue !== null)) {
                if (strpos($field['identifier'], 'indexingCustomField_') !== false) {
                    $idCustom = explode("_", $field['identifier']);
                    $idCustom = $idCustom[1];
                    $args['customFields'][$idCustom] = $defaultValue;
                } elseif ($field['identifier'] != 'initiator') {
                    $args[$field['identifier']] = $defaultValue;
                }
            }
        }
        return $args;
    }

    public static function prepareResourceStorage(array $args)
    {
        ValidatorModel::notEmpty($args, ['resId', 'modelId']);
        ValidatorModel::intVal($args, ['resId', 'modelId']);

        $indexingModel = IndexingModelModel::getById(['id' => $args['modelId'], 'select' => ['category']]);

        if (empty($args['typist'])) {
            $args['typist'] = $GLOBALS['id'];
        }

        if (!empty($args['initiator'])) {
            $entity = EntityModel::getById(['id' => $args['initiator'], 'select' => ['entity_id']]);
            $args['initiator'] = $entity['entity_id'];
        }
        if (!empty($args['destination'])) {
            $entity = EntityModel::getById(['id' => $args['destination'], 'select' => ['entity_id']]);
            $args['destination'] = $entity['entity_id'];
        }
        $chrono = null;
        if (!empty($args['chrono'])) {
            $chrono = ChronoModel::getChrono(['id' => $indexingModel['category'], 'entityId' => $args['destination'] ?? null, 'typeId' => $args['doctype'], 'resId' => $args['resId']]);
        }

        if (!empty($args['processLimitDate']) && !empty($args['priority'])) {
            $args['priority'] = IndexingController::calculatePriorityWithProcessLimitDate(['processLimitDate' => $args['processLimitDate']]);
        }

        $externalId = '{}';
        if (!empty($args['externalId']) && is_array($args['externalId'])) {
            $externalId = json_encode($args['externalId']);
        }

        $integrations = ['inSignatureBook' => false, 'inShipping' => false];
        if (!empty($args['integrations']) && !empty($args['encodedFile'])) {
            $integrations['inSignatureBook'] = !empty($args['integrations']['inSignatureBook']);
            $integrations['inShipping'] = !empty($args['integrations']['inShipping']);
        }

        if (!empty($args['customFields'])) {
            foreach ($args['customFields'] as $key => $value) {
                $customField = CustomFieldModel::getById(['id' => $key, 'select' => ['type']]);
                if ($customField['type'] == 'date' && !empty($value)) {
                    $date = new \DateTime($value);
                    $value = $date->format('Y-m-d');
                    $args['customFields'][$key] = $value;
                } elseif (in_array($customField['type'], ['banAutocomplete', 'contact', 'date'])) {
                    $args['customFields'][$key] = $value;
                } elseif ($customField['type'] != 'integer' && !is_array($value)) {
                    $args['customFields'][$key] = (string)$value;
                } elseif ($customField['type'] != 'integer' && is_array($value)) {
                    foreach ($value as $iKey => $sValue) {
                        $value[$iKey] = (string)$sValue;
                    }
                    $args['customFields'][$key] = $value;
                }
            }
        }

        $preparedData = [
            'res_id'                => $args['resId'],
            'model_id'              => $args['modelId'],
            'category_id'           => $indexingModel['category'],
            'type_id'               => $args['doctype'],
            'subject'               => $args['subject'] ?? null,
            'alt_identifier'        => $chrono,
            'typist'                => $args['typist'],
            'status'                => $args['status'] ?? null,
            'destination'           => $args['destination'] ?? null,
            'initiator'             => $args['initiator'] ?? null,
            'confidentiality'       => empty($args['confidentiality']) ? 'N' : 'Y',
            'doc_date'              => $args['documentDate'] ?? null,
            'admission_date'        => $args['arrivalDate'] ?? null,
            'departure_date'        => $args['departureDate'] ?? null,
            'process_limit_date'    => $args['processLimitDate'] ?? null,
            'priority'              => $args['priority'] ?? null,
            'version'               => 1,
            'barcode'               => $args['barcode'] ?? null,
            'origin'                => $args['origin'] ?? null,
            'custom_fields'         => !empty($args['customFields']) ? json_encode($args['customFields']) : null,
            'integrations'          => json_encode($integrations),
            'linked_resources'      => !empty($args['linkedResources']) ? json_encode($args['linkedResources']) : '[]',
            'external_id'           => $externalId,
            'creation_date'         => 'CURRENT_TIMESTAMP'
        ];

        return $preparedData;
    }

    public static function prepareUpdateResourceStorage(array $args)
    {
        $definedVars = get_defined_vars();

        $preparedData = [
            'modification_date' => 'CURRENT_TIMESTAMP'
        ];

        $resource = ResModel::getById(['resId' => $args['resId'], 'select' => ['version', 'alt_identifier', 'external_id', 'category_id', 'type_id', 'destination', 'custom_fields']]);

        if (!empty($args['modelId'])) {
            $preparedData['model_id']    = $args['modelId'];
            $indexingModel               = IndexingModelModel::getById(['id' => $args['modelId'], 'select' => ['category']]);
            $preparedData['category_id'] = $indexingModel['category'];
            $resource['category_id']     = $indexingModel['category'];

            $indexingModelField = IndexingModelFieldModel::get(['select' => ['default_value'], 'where' => ['model_id = ?', 'identifier = ?'], 'data' => [$args['modelId'], 'destination']]);
            $newDestination     = json_decode($indexingModelField[0]['default_value']);
            if (empty($resource['destination']) && !empty($newDestination)) {
                if ($newDestination == "#myPrimaryEntity") {
                    $entity = UserModel::getPrimaryEntityById(['id' => $GLOBALS['id'], 'select' => ['entities.entity_id']]);
                    $preparedData['destination'] = $entity['entity_id'];
                } else {
                    $entity = EntityModel::getById(['id' => $newDestination, 'select' => ['entity_id']]);
                    $preparedData['destination'] = $entity['entity_id'];
                }
            }
        }
        if (empty($resource['alt_identifier'])) {
            $chrono = ChronoModel::getChrono(['id' => $resource['category_id'], 'entityId' => $resource['destination'], 'typeId' => $resource['type_id'], 'resId' => $args['resId']]);
            $preparedData['alt_identifier'] = $chrono;
        }
        if (!empty($args['doctype'])) {
            $preparedData['type_id'] = $args['doctype'];
        }
        if (isset($args['subject'])) {
            $preparedData['subject'] = $args['subject'];
        }
        if (isset($args['confidentiality'])) {
            $preparedData['confidentiality'] = empty($args['confidentiality']) ? 'N' : 'Y';
        }
        if (!empty($args['initiator'])) {
            $entity = EntityModel::getById(['id' => $args['initiator'], 'select' => ['entity_id']]);
            $preparedData['initiator'] = $entity['entity_id'];
        } elseif (array_key_exists('initiator', $definedVars['args'])) {
            $preparedData['initiator'] = null;
        }
        if (isset($args['documentDate'])) {
            $preparedData['doc_date'] = $args['documentDate'];
        } elseif (array_key_exists('documentDate', $definedVars['args'])) {
            $preparedData['doc_date'] = null;
        }
        if (isset($args['arrivalDate'])) {
            $preparedData['admission_date'] = $args['arrivalDate'];
        } elseif (array_key_exists('arrivalDate', $definedVars['args'])) {
            $preparedData['admission_date'] = null;
        }
        if (isset($args['departureDate'])) {
            $preparedData['departure_date'] = $args['departureDate'];
        } elseif (array_key_exists('departureDate', $definedVars['args'])) {
            $preparedData['departure_date'] = null;
        }
        if (isset($args['processLimitDate'])) {
            $preparedData['process_limit_date'] = $args['processLimitDate'];
        } elseif (array_key_exists('processLimitDate', $definedVars['args'])) {
            $preparedData['process_limit_date'] = null;
        }
        if (isset($args['priority'])) {
            $preparedData['priority'] = $args['priority'];
        } elseif (array_key_exists('priority', $definedVars['args'])) {
            $preparedData['priority'] = null;
        }
        if (!empty($args['processLimitDate']) && !empty($args['priority'])) {
            $preparedData['priority'] = IndexingController::calculatePriorityWithProcessLimitDate(['processLimitDate' => $args['processLimitDate']]);
        }
        if (!empty($args['encodedFile'])) {
            $preparedData['version'] = $resource['version'] + 1;
        }
        if (!empty($args['externalId']) && is_array($args['externalId'])) {
            $externalId = array_merge(json_decode($resource['external_id'], true), $args['externalId']);
            $externalId = json_encode($externalId);
            $preparedData['external_id'] = $externalId;
        }

        if (!empty($args['customFields'])) {
            foreach ($args['customFields'] as $key => $value) {
                $customField = CustomFieldModel::getById(['id' => $key, 'select' => ['type']]);
                if ($customField['type'] == 'date' && !empty($value)) {
                    $date = new \DateTime($value);
                    $value = $date->format('Y-m-d');
                    $args['customFields'][$key] = $value;
                } elseif (in_array($customField['type'], ['banAutocomplete', 'contact', 'date'])) {
                    $args['customFields'][$key] = $value;
                } elseif ($customField['type'] != 'integer' && !is_array($value)) {
                    $args['customFields'][$key] = (string)$value;
                } elseif ($customField['type'] != 'integer' && is_array($value)) {
                    foreach ($value as $iKey => $sValue) {
                        $value[$iKey] = (string)$sValue;
                    }
                    $args['customFields'][$key] = $value;
                }
            }
            $customFields = json_decode($resource['custom_fields'], true);
            $technicalCustoms = CustomFieldModel::get(['select' => ['id'], 'where' => ['mode in (?)'], 'data' => [['technical']]]);
            $technicalCustoms = array_column($technicalCustoms, 'id');
            foreach ($technicalCustoms as $technicalCustom) {
                if (!empty($customFields[$technicalCustom])) {
                    $args['customFields'][$technicalCustom] = $customFields[$technicalCustom];
                }
            }
            $preparedData['custom_fields'] = json_encode($args['customFields']);
        }

        return $preparedData;
    }

    public static function prepareAttachmentStorage(array $args)
    {
        $attachmentsTypes = AttachmentTypeModel::get(['select' => ['type_id', 'chrono', 'signable']]);
        $attachmentsTypes = array_column($attachmentsTypes, null, 'type_id');
        if ($attachmentsTypes[$args['type']]['chrono'] && empty($args['chrono'])) {
            $resource = ResModel::getById(['select' => ['destination', 'type_id'], 'resId' => $args['resIdMaster']]);
            $args['chrono'] = ChronoModel::getChrono(['id' => 'outgoing', 'entityId' => $resource['destination'], 'typeId' => $resource['type_id'], 'resId' => $args['resIdMaster']]);
        }
        $shouldBeInSignatureBook = $attachmentsTypes[$args['type']]['signable'];

        if ($args['type'] == 'signed_response') {
            $linkSign = "{$args['originId']},res_attachments";
            AttachmentModel::update(['set' => ['status' => 'SIGN'], 'where' => ['res_id = ?'], 'data' => [$args['originId']]]);
            unset($args['originId']);
        }

        $relation = 1;
        if (!empty($args['originId'])) {
            $relations = AttachmentModel::get(['select' => ['relation', 'in_signature_book'], 'where' => ['(origin_id = ? or res_id = ?)'], 'data' => [$args['originId'], $args['originId']], 'orderBy' => ['relation DESC'], 'limit' => 1]);
            $relation = $relations[0]['relation'] + 1;
            AttachmentModel::update(['set' => ['status' => 'OBS'], 'where' => ['(origin_id = ? OR res_id = ?)'], 'data' => [$args['originId'], $args['originId']]]);
            $shouldBeInSignatureBook = $relations[0]['in_signature_book'];
        }
        $typist = $GLOBALS['id'];
        if (!empty($args['typist']) && is_numeric($args['typist'])) {
            $typist = $args['typist'];
        }

        $externalId = '{}';
        if (!empty($args['externalId']) && is_array($args['externalId'])) {
            $externalId = json_encode($args['externalId']);
        }

        $inSignatureBook = isset($args['inSignatureBook']) ? $args['inSignatureBook'] : $shouldBeInSignatureBook;
        $preparedData = [
            'res_id'                   => $args['resId'] ?? null,
            'title'                    => $args['title'] ?? null,
            'identifier'               => $args['chrono'] ?? null,
            'typist'                   => $typist,
            'status'                   => $args['status'] ?? 'A_TRA',
            'relation'                 => $relation,
            'origin_id'                => $args['originId'] ?? null,
            'origin'                   => $linkSign ?? null,
            'res_id_master'            => $args['resIdMaster'],
            'attachment_type'          => $args['type'],
            'recipient_id'             => $args['recipientId'] ?? null,
            'recipient_type'           => !empty($args['recipientId']) ? $args['recipientType'] : null,
            'validation_date'          => $args['validationDate'] ?? null,
            'effective_date'           => $args['effectiveDate'] ?? null,
            'in_signature_book'        => $inSignatureBook ? 'true' : 'false',
            'external_id'              => $externalId,
            'creation_date'            => 'CURRENT_TIMESTAMP',
            'signatory_user_serial_id' => !empty($args['signatory_user_serial_id']) ? $args['signatory_user_serial_id'] : null
        ];

        return $preparedData;
    }

    public static function prepareUpdateAttachmentStorage(array $args)
    {
        $attachment = AttachmentModel::getById(['id' => $args['id'], 'select' => ['identifier', 'res_id_master']]);
        $attachmentsTypes = AttachmentTypeModel::get(['select' => ['type_id', 'chrono']]);
        $attachmentsTypes = array_column($attachmentsTypes, 'chrono', 'type_id');
        if ($attachmentsTypes[$args['type']] && empty($attachment['identifier'])) {
            $resource = ResModel::getById(['select' => ['destination', 'type_id'], 'resId' => $attachment['res_id_master']]);
            $chrono = ChronoModel::getChrono(['id' => 'outgoing', 'entityId' => $resource['destination'], 'typeId' => $resource['type_id'], 'resId' => $attachment['res_id_master']]);
        }

        $preparedData = [
            'title'                 => $args['title'] ?? null,
            'recipient_id'          => $args['recipientId'] ?? null,
            'recipient_type'        => $args['recipientType'] ?? null,
            'attachment_type'       => $args['type'],
            'validation_date'       => $args['validationDate'] ?? null,
            'effective_date'        => $args['effectiveDate'] ?? null,
            'modified_by'           => $GLOBALS['id'],
            'modification_date'     => 'CURRENT_TIMESTAMP'
        ];

        if (!empty($chrono)) {
            $preparedData['identifier'] = $chrono;
        }

        return $preparedData;
    }

    public static function getFingerPrint(array $args)
    {
        ValidatorModel::notEmpty($args, ['filePath']);
        ValidatorModel::stringType($args, ['filePath', 'mode']);

        if (empty($args['mode']) || $args['mode'] == 'NONE') {
            $args['mode'] = 'sha512';
        }

        return hash_file(strtolower($args['mode']), $args['filePath']);
    }

    public static function isFileAllowed(array $args)
    {
        ValidatorModel::notEmpty($args, ['extension']);
        ValidatorModel::stringType($args, ['extension', 'type']);

        $loadedXml = CoreConfigModel::getXmlLoaded(['path' => 'config/extensions.xml']);
        if ($loadedXml) {
            foreach ($loadedXml->FORMAT as $value) {
                if (strtolower((string)$value->name) == strtolower($args['extension']) && (strtolower((string)$value->mime) == strtolower($args['type']) || (empty((string)$value->mime) && empty($args['type'])))) {
                    return true;
                }
            }
        }

        return false;
    }

    public static function getAllowedFiles()
    {
        $allowedFiles = [];

        $loadedXml = CoreConfigModel::getXmlLoaded(['path' => 'config/extensions.xml']);
        if ($loadedXml) {
            foreach ($loadedXml->FORMAT as $value) {
                $allowedFiles[] = [
                    'extension'     => (string)$value->name,
                    'mimeType'      => (string)$value->mime,
                    'canConvert'    => filter_var((string)$value->canConvert, FILTER_VALIDATE_BOOLEAN)
                ];
            }
        }

        return $allowedFiles;
    }

    public static function getBytesSizeFromPhpIni(array $args)
    {
        if (strpos($args['size'], 'K') !== false) {
            return (int)$args['size'] * 1024;
        } elseif (strpos($args['size'], 'M') !== false) {
            return (int)$args['size'] * 1048576;
        } elseif (strpos($args['size'], 'G') !== false) {
            return (int)$args['size'] * 1073741824;
        }

        return (int)$args['size'];
    }

    public static function getFormattedSizeFromBytes(array $args)
    {
        if ($args['size'] / 1073741824 > 1) {
            return round($args['size'] / 1073741824) . ' Go';
        } elseif ($args['size'] / 1048576 > 1) {
            return round($args['size'] / 1048576) . ' Mo';
        } elseif ($args['size'] / 1024 > 1) {
            return round($args['size'] / 1024) . ' Ko';
        }

        return $args['size'] . ' o';
    }
}
