<?php

/**
* Copyright Maarch since 2008 under licence GPLv3.
* See LICENCE.txt file at the root folder for more details.
* This file is part of Maarch software.
*
*/

/**
* @brief Resource Control Controller
* @author dev@maarch.org
*/

namespace Resource\controllers;

use Contact\models\ContactModel;
use Convert\controllers\ConvertPdfController;
use Convert\models\AdrModel;
use CustomField\models\CustomFieldModel;
use Doctype\models\DoctypeModel;
use Entity\models\EntityModel;
use Folder\controllers\FolderController;
use Group\controllers\PrivilegeController;
use IndexingModel\models\IndexingModelFieldModel;
use IndexingModel\models\IndexingModelModel;
use IndexingModel\controllers\IndexingModelController;
use Priority\models\PriorityModel;
use Resource\models\ResModel;
use Respect\Validation\Validator;
use SrcCore\controllers\CoreController;
use SrcCore\controllers\PreparedClauseController;
use Status\models\StatusModel;
use Tag\models\TagModel;
use User\models\UserGroupModel;
use User\models\UserModel;

class ResourceControlController
{
    protected static function controlResource(array $args)
    {
        $currentUser = UserModel::getById(['id' => $GLOBALS['id'], 'select' => ['mode']]);
        $isWebServiceUser = $currentUser['mode'] == 'rest';

        $body = $args['body'];

        if (empty($body)) {
            return ['errors' => 'Body is not set or empty'];
        } elseif (!empty($body['doctype']) && !Validator::intVal()->validate($body['doctype'])) {
            return ['errors' => 'Body doctype is not an integer'];
        } elseif (!Validator::notEmpty()->intVal()->validate($body['modelId'])) {
            return ['errors' => 'Body modelId is empty or not an integer'];
        } elseif ($isWebServiceUser && !Validator::stringType()->notEmpty()->validate($body['status'])) {
            return ['errors' => 'Body status is empty or not a string'];
        }

        if (!empty($body['doctype'])) {
            $doctype = DoctypeModel::getById(['id' => $body['doctype'], 'select' => [1]]);
            if (empty($doctype)) {
                return ['errors' => 'Body doctype does not exist'];
            }
        }

        $indexingModel = IndexingModelModel::getById(['id' => $body['modelId'], 'select' => ['master', 'enabled', 'mandatory_file']]);
        if (empty($indexingModel)) {
            return ['errors' => 'Body modelId does not exist'];
        } elseif (!$indexingModel['enabled']) {
            return ['errors' => 'Body modelId is disabled'];
        } elseif (!empty($indexingModel['master'])) {
            return ['errors' => 'Body modelId is not a master model'];
        } elseif (empty($body['encodedFile']) && $indexingModel['mandatory_file']) {
            return ['errors' => 'File is mandatory for this indexing model'];
        }

        $control = ResourceControlController::controlFileData(['body' => $body]);
        if (!empty($control['errors'])) {
            return ['errors' => $control['errors']];
        }

        $control = ResourceControlController::controlAdjacentData(['body' => $body]);
        if (!empty($control['errors'])) {
            return ['errors' => $control['errors']];
        }

        if (!$isWebServiceUser) {
            $control = ResourceControlController::controlIndexingModelFields(['body' => $body]);
            if (!empty($control['errors'])) {
                return ['errors' => $control['errors']];
            }

            if (!empty($body['initiator'])) {
                $userEntities = UserModel::getEntitiesById(['id' => $GLOBALS['id'], 'select' => ['entities.id']]);
                $userEntities = array_column($userEntities, 'id');
                if (!in_array($body['initiator'], $userEntities)) {
                    return ['errors' => "Body initiator does not belong to your entities"];
                }
            }
        }

        $control = ResourceControlController::controlDestination(['body' => $body]);
        if (!empty($control['errors'])) {
            return ['errors' => $control['errors']];
        }
        $control = ResourceControlController::controlDates(['body' => $body]);
        if (!empty($control['errors'])) {
            return ['errors' => $control['errors']];
        }

        if (!empty($body['status'])) {
            $status = StatusModel::getById(['id' => $body['status'], 'select' => [1]]);
            if (empty($status)) {
                return ['errors' => 'Body status does not exist'];
            }
        }

        if (!empty($body['linkedResources'])) {
            if (!ResController::hasRightByResId(['resId' => [$body['linkedResources']], 'userId' => $GLOBALS['id']])) {
                return ['errors' => 'Body linkedResources out of perimeter'];
            }
        }

        return true;
    }

    protected static function controlUpdateResource(array $args)
    {
        $body = $args['body'];

        if (empty($body)) {
            return ['errors' => 'Body is not set or empty'];
        }

        $resource = ResModel::getById(['resId' => $args['resId'], 'select' => ['type_id', 'status', 'model_id', 'format', 'initiator', 'external_id->>\'signatureBookId\' as signaturebookid', 'filename']]);
        if (empty($resource['status'])) {
            return ['errors' => 'Resource status is empty. It can not be modified'];
        }
        $status = StatusModel::getById(['id' => $resource['status'], 'select' => ['can_be_modified']]);
        if ($status['can_be_modified'] != 'Y') {
            return ['errors' => 'Resource can not be modified because of status'];
        }

        if (!empty($body['modelId']) && $resource['model_id'] != $body['modelId']) {
            if (!PrivilegeController::isResourceInProcess(['userId' => $GLOBALS['id'], 'resId' => $args['resId'], 'canUpdateData' => true, 'canUpdateModel' => true])) {
                return ['errors' => 'Model can not be modified'];
            }
            $indexingModel = IndexingModelModel::getById(['id' => $body['modelId'], 'select' => ['master', 'enabled', 'mandatory_file']]);
            if (empty($indexingModel)) {
                return ['errors' => 'Body modelId does not exist'];
            } elseif (!$indexingModel['enabled']) {
                return ['errors' => 'Body modelId is disabled'];
            } elseif (!empty($indexingModel['master'])) {
                return ['errors' => 'Body modelId is not a master model'];
            } elseif (empty($resource['filename']) && $indexingModel['mandatory_file']) {
                return ['errors' => 'File is mandatory for this indexing model'];
            }
        }

        if ($args['onlyDocument'] && empty($body['encodedFile'])) {
            return ['errors' => 'Body encodedFile is not set or empty'];
        } elseif (!empty($body['encodedFile'])) {
            if (!empty($resource['signaturebookid'])) {
                return ['errors' => 'Resource is in external signature book, file can not be modified'];
            } elseif (ResourceControlController::isSigned(['resId' => $args['resId']])) {
                return ['errors' => 'Resource is signed, file can not be modified'];
            } elseif (!empty($resource['format']) && !ConvertPdfController::canConvert(['extension' => $resource['format']])) {
                return ['errors' => 'Resource is not convertible, file can not be modified'];
            }
            $control = ResourceControlController::controlFileData(['body' => $body]);
            if (!empty($control['errors'])) {
                return ['errors' => $control['errors']];
            }
            if ($args['onlyDocument']) {
                return true;
            }
        }

        if (!Validator::intVal()->validate($body['doctype'])) {
            return ['errors' => 'Body doctype is not an integer'];
        }
        if (!empty($body['doctype'])) {
            $doctype = DoctypeModel::getById(['id' => $body['doctype'], 'select' => [1]]);
            if (empty($doctype)) {
                return ['errors' => 'Body doctype does not exist'];
            }
        }

        $control = ResourceControlController::controlAdjacentData(['body' => $body]);
        if (!empty($control['errors'])) {
            return ['errors' => $control['errors']];
        }

        if (empty($body['modelId'])) {
            $body['modelId'] = $resource['model_id'];
        }
        $control = ResourceControlController::controlIndexingModelFields(['body' => $body, 'oldDoctypeId' => $resource['type_id'], 'isUpdating' => true, 'resId' => $args['resId']]);
        if (!empty($control['errors'])) {
            return ['errors' => $control['errors']];
        }

        if (!empty($body['initiator'])) {
            $entity = EntityModel::getById(['id' => $body['initiator'], 'select' => ['entity_id']]);
            if (empty($entity)) {
                return ['errors' => "Body initiator does not exist"];
            }
            if ($resource['initiator'] != $entity['entity_id']) {
                $userEntities = UserModel::getEntitiesById(['id' => $GLOBALS['id'], 'select' => ['entities.id']]);
                $userEntities = array_column($userEntities, 'id');
                if (!in_array($body['initiator'], $userEntities)) {
                    return ['errors' => "Body initiator does not belong to your entities"];
                }
            }
        }

        $control = ResourceControlController::controlDestination(['body' => $body]);
        if (!empty($control['errors'])) {
            return ['errors' => $control['errors']];
        }
        $control = ResourceControlController::controlDates(['body' => $body, 'resId' => $args['resId']]);
        if (!empty($control['errors'])) {
            return ['errors' => $control['errors']];
        }

        return true;
    }

    private static function isSigned(array $args)
    {
        $signedDocument = AdrModel::getDocuments([
            'select'    => [1],
            'where'     => ['res_id = ?', 'type = ?'],
            'data'      => [$args['resId'], 'SIGN']
        ]);

        if (empty($signedDocument)) {
            return false;
        }

        return true;
    }

    public static function controlFileData(array $args)
    {
        $body = $args['body'];

        if (!empty($body['encodedFile'])) {
            if (!Validator::stringType()->notEmpty()->validate($body['format'])) {
                return ['errors' => 'Body format is empty or not a string'];
            }

            $mimeAndSize = CoreController::getMimeTypeAndFileSize(['encodedFile' => $body['encodedFile']]);
            if (!empty($mimeAndSize['errors'])) {
                return ['errors' => $mimeAndSize['errors']];
            }

            if (!StoreController::isFileAllowed(['extension' => $body['format'], 'type' => $mimeAndSize['mime']])) {
                return ['errors' => "Format with this mimeType is not allowed : {$body['format']} {$mimeAndSize['mime']}"];
            }

            $maximumSize = CoreController::getMaximumAllowedSizeFromPhpIni();
            if ($maximumSize > 0 && $mimeAndSize['size'] > $maximumSize) {
                return ['errors' => "Body encodedFile size is over limit"];
            }
        }

        return true;
    }

    private static function controlAdjacentData(array $args)
    {
        $body = $args['body'];

        if (!empty($body['customFields'])) {
            if (!Validator::arrayType()->notEmpty()->validate($body['customFields'])) {
                return ['errors' => 'Body customFields is not an array'];
            }
            $customFields = CustomFieldModel::get(['select' => ['count(1)'], 'where' => ['id in (?)'], 'data' => [array_keys($body['customFields'])]]);
            if (count($body['customFields']) != $customFields[0]['count']) {
                return ['errors' => 'Body customFields : One or more custom fields do not exist'];
            }
        }
        if (!empty($body['folders'])) {
            if (!Validator::arrayType()->notEmpty()->validate($body['folders'])) {
                return ['errors' => 'Body folders is not an array'];
            }
            if (!FolderController::hasFolders(['folders' => $body['folders'], 'userId' => $GLOBALS['id']])) {
                return ['errors' => 'Body folders : One or more folders do not exist or are out of perimeter'];
            }
        }
        if (!empty($body['tags'])) {
            if (!Validator::arrayType()->notEmpty()->validate($body['tags'])) {
                return ['errors' => 'Body tags is not an array'];
            }
            $tags = TagModel::get(['select' => ['count(1)'], 'where' => ['id in (?)'], 'data' => [$body['tags']]]);
            if (count($body['tags']) != $tags[0]['count']) {
                return ['errors' => 'Body tags : One or more tags do not exist'];
            }
        }
        if (!empty($body['senders'])) {
            if (!Validator::arrayType()->notEmpty()->validate($body['senders'])) {
                return ['errors' => 'Body senders is not an array'];
            }
            foreach ($body['senders'] as $key => $sender) {
                if (!Validator::arrayType()->notEmpty()->validate($sender)) {
                    return ['errors' => "Body senders[{$key}] is not an array"];
                } elseif (!Validator::notEmpty()->intVal()->validate($sender['id'])) {
                    return ['errors' => "Body senders[{$key}][id] is empty or not an integer"];
                }
                if ($sender['type'] == 'contact') {
                    $senderItem = ContactModel::getById(['id' => $sender['id'], 'select' => [1]]);
                } elseif ($sender['type'] == 'user') {
                    $senderItem = UserModel::getById(['id' => $sender['id'], 'select' => [1]]);
                } elseif ($sender['type'] == 'entity') {
                    $senderItem = EntityModel::getById(['id' => $sender['id'], 'select' => [1]]);
                } else {
                    return ['errors' => "Body senders[{$key}] type is not valid"];
                }
                if (empty($senderItem)) {
                    return ['errors' => "Body senders[{$key}] id does not exist"];
                }
            }
        }
        if (!empty($body['recipients'])) {
            if (!Validator::arrayType()->notEmpty()->validate($body['recipients'])) {
                return ['errors' => 'Body recipients is not an array'];
            }
            foreach ($body['recipients'] as $key => $recipient) {
                if (!Validator::arrayType()->notEmpty()->validate($recipient)) {
                    return ['errors' => "Body recipients[{$key}] is not an array"];
                } elseif (!Validator::notEmpty()->intVal()->validate($recipient['id'])) {
                    return ['errors' => "Body recipients[{$key}][id] is empty or not an integer"];
                }
                if ($recipient['type'] == 'contact') {
                    $recipientItem = ContactModel::getById(['id' => $recipient['id'], 'select' => [1]]);
                } elseif ($recipient['type'] == 'user') {
                    $recipientItem = UserModel::getById(['id' => $recipient['id'], 'select' => [1]]);
                } elseif ($recipient['type'] == 'entity') {
                    $recipientItem = EntityModel::getById(['id' => $recipient['id'], 'select' => [1]]);
                } else {
                    return ['errors' => "Body recipients[{$key}] type is not valid"];
                }
                if (empty($recipientItem)) {
                    return ['errors' => "Body recipients[{$key}] id does not exist"];
                }
            }
        }
        if (!empty($body['diffusionList'])) {
            if (!Validator::arrayType()->notEmpty()->validate($body['diffusionList'])) {
                return ['errors' => 'Body diffusionList is not an array'];
            }
            $destFound = false;
            foreach ($body['diffusionList'] as $key => $diffusion) {
                if (!Validator::arrayType()->notEmpty()->validate($diffusion)) {
                    return ['errors' => "Body diffusionList[{$key}] is not an array"];
                } elseif (!Validator::notEmpty()->intVal()->validate($diffusion['id'])) {
                    return ['errors' => "Body diffusionList[{$key}][id] is empty or not an integer"];
                }
                if ($diffusion['mode'] == 'dest') {
                    if ($destFound) {
                        return ['errors' => "Body diffusionList has multiple dest"];
                    }
                    $destFound = true;
                }
                if ($diffusion['type'] == 'user' || $diffusion['mode'] == 'dest') {
                    $item = UserModel::getById(['id' => $diffusion['id'], 'select' => [1]]);
                } else {
                    $item = EntityModel::getById(['id' => $diffusion['id'], 'select' => [1]]);
                }
                if (empty($item)) {
                    return ['errors' => "Body diffusionList[{$key}] id does not exist"];
                }
            }
            if (!$destFound) {
                return ['errors' => 'Body diffusion has no dest'];
            }
        }

        return true;
    }

    private static function controlIndexingModelFields(array $args)
    {
        $body = $args['body'];

        $indexingModelFields = IndexingModelFieldModel::get(['select' => ['identifier', 'mandatory', 'allowed_values'], 'where' => ['model_id = ?'], 'data' => [$body['modelId']]]);
        foreach ($indexingModelFields as $indexingModelField) {
            $indexingModelField['allowed_values'] = !empty($indexingModelField['allowed_values']) ? json_decode($indexingModelField['allowed_values'], true) : null;
            if ($indexingModelField['allowed_values'] == IndexingModelController::ALLOWED_VALUES_ALL_DOCTYPES) {
                $indexingModelField['allowed_values'] = null; // setting to null so it is ignored in the rest of this function
            }
            if (strpos($indexingModelField['identifier'], 'indexingCustomField_') !== false) {
                $customFieldId = explode('_', $indexingModelField['identifier'])[1];
                if ($indexingModelField['mandatory'] && empty($body['customFields'][$customFieldId]) && $body['customFields'][$customFieldId] !== 0) {
                    return ['errors' => "Body customFields[{$customFieldId}] is empty"];
                }
                if (!empty($body['customFields'][$customFieldId])) {
                    $customField = CustomFieldModel::getById(['id' => $customFieldId, 'select' => ['type', 'values']]);
                    $possibleValues = empty($customField['values']) ? [] : json_decode($customField['values'], true);
                    if (!empty($possibleValues['table'])) {
                        if (!empty($args['resId'])) {
                            $possibleValues['resId'] = $args['resId'];
                        }
                        $possibleValues = CustomFieldModel::getValuesSQL($possibleValues);
                        $possibleValues = array_column($possibleValues, 'key');
                    }
                    if (($customField['type'] == 'select' || $customField['type'] == 'radio') && !in_array($body['customFields'][$customFieldId], $possibleValues)) {
                        return ['errors' => "Body customFields[{$customFieldId}] has wrong value"];
                    } elseif ($customField['type'] == 'checkbox') {
                        if (!is_array($body['customFields'][$customFieldId])) {
                            return ['errors' => "Body customFields[{$customFieldId}] is not an array"];
                        }
                        foreach ($body['customFields'][$customFieldId] as $value) {
                            if (!in_array($value, $possibleValues)) {
                                return ['errors' => "Body customFields[{$customFieldId}] has wrong value"];
                            }
                        }
                    } elseif ($customField['type'] == 'banAutocomplete') {
                        if (empty($body['customFields'][$customFieldId][0]) || !is_array($body['customFields'][$customFieldId][0])) {
                            return ['errors' => "Body customFields[{$customFieldId}] is not an array"];
                        }
                        if (empty($body['customFields'][$customFieldId][0]['longitude'])) {
                            return ['errors' => "Body customFields[{$customFieldId}] longitude is empty"];
                        } elseif (empty($body['customFields'][$customFieldId][0]['latitude'])) {
                            return ['errors' => "Body customFields[{$customFieldId}] latitude is empty"];
                        } elseif (empty($body['customFields'][$customFieldId][0]['addressTown'])) {
                            return ['errors' => "Body customFields[{$customFieldId}] addressTown is empty"];
                        } elseif (empty($body['customFields'][$customFieldId][0]['addressPostcode'])) {
                            return ['errors' => "Body customFields[{$customFieldId}] addressPostcode is empty"];
                        }
                    } elseif ($customField['type'] == 'string' && !Validator::stringType()->notEmpty()->validate($body['customFields'][$customFieldId])) {
                        return ['errors' => "Body customFields[{$customFieldId}] is not a string"];
                    } elseif ($customField['type'] == 'integer' && !Validator::floatVal()->notEmpty()->validate($body['customFields'][$customFieldId])) {
                        return ['errors' => "Body customFields[{$customFieldId}] is not a number"];
                    } elseif ($customField['type'] == 'date' && !Validator::dateTime()->notEmpty()->validate($body['customFields'][$customFieldId])) {
                        return ['errors' => "Body customFields[{$customFieldId}] is not a date"];
                    } elseif (!empty($indexingModelField['allowed_values']) && !in_array($body['customFields'][$customFieldId], $indexingModelField['allowed_values'])) {
                        if(!empty($args['oldDoctypeId']) && $body['customFields'][$customFieldId] == $args['oldDoctypeId']) {
                            continue;
                        }
                        return ['errors' => "Body {$indexingModelField['identifier']} is not one of the allowed values"];
                    }
                }
            } elseif ($indexingModelField['identifier'] == 'destination' && !empty($args['isUpdating'])) {
                continue;
            } elseif ($indexingModelField['mandatory'] && !isset($body[$indexingModelField['identifier']])) {
                return ['errors' => "Body {$indexingModelField['identifier']} is not set"];
            } elseif (!empty($indexingModelField['allowed_values']) && !in_array($body[$indexingModelField['identifier']], $indexingModelField['allowed_values'])) {
                if(!empty($args['oldDoctypeId']) && $body[$indexingModelField['identifier']] == $args['oldDoctypeId']) {
                    continue;
                }
                return ['errors' => "Body {$indexingModelField['identifier']} is not one of the allowed values"];
            }
        }

        return true;
    }

    private static function controlDates(array $args)
    {
        $body = $args['body'];

        if (!empty($body['documentDate'])) {
            if (!Validator::dateTime()->notEmpty()->validate($body['documentDate'])) {
                return ['errors' => "Body documentDate is not a date"];
            }

            $model = IndexingModelModel::getById(['id' => $body['modelId'], 'select' => ['category']]);
            if ($model['category'] != 'outgoing') {
                $documentDate = new \DateTime($body['documentDate']);
                $tmr = new \DateTime('tomorrow');
                if ($documentDate > $tmr) {
                    return ['errors' => "Body documentDate cannot be a date in the future"];
                }
            }
        }
        if (!empty($body['arrivalDate'])) {
            if (!Validator::dateTime()->notEmpty()->validate($body['arrivalDate'])) {
                return ['errors' => "Body arrivalDate is not a date"];
            }

            $arrivalDate = new \DateTime($body['arrivalDate']);
            $tmr = new \DateTime('tomorrow');
            if ($arrivalDate > $tmr) {
                return ['errors' => "Body arrivalDate is not a valid date"];
            }
        }
        if (!empty($body['departureDate'])) {
            if (!Validator::dateTime()->notEmpty()->validate($body['departureDate'])) {
                return ['errors' => "Body departureDate is not a date"];
            }
            $departureDate = new \DateTime($body['departureDate']);
            if (!empty($documentDate) && $departureDate < $documentDate) {
                return ['errors' => "Body departureDate is not a valid date"];
            }
        }
        if (!empty($body['processLimitDate'])) {
            if (!Validator::dateTime()->notEmpty()->validate($body['processLimitDate'])) {
                return ['errors' => "Body processLimitDate is not a date"];
            }

            if (!empty($args['resId'])) {
                $resource = ResModel::getById(['resId' => $args['resId'], 'select' => ['process_limit_date']]);
                if (!empty($resource['process_limit_date'])) {
                    $originProcessLimitDate = new \DateTime($resource['process_limit_date']);
                }
            }
            $processLimitDate = new \DateTime($body['processLimitDate']);
            if (empty($originProcessLimitDate) || $originProcessLimitDate != $processLimitDate) {
                $today = new \DateTime();
                $today->setTime(00, 00, 00);
                if ($processLimitDate < $today) {
                    return ['errors' => "Body processLimitDate is not a valid date"];
                }
            }
        } elseif (!empty($body['priority'])) {
            if (!Validator::stringType()->validate($body['priority'])) {
                return ['errors' => "Body priority is not a string"];
            }

            $priority = PriorityModel::getById(['id' => $body['priority'], 'select' => [1]]);
            if (empty($priority)) {
                return ['errors' => "Body priority does not exist"];
            }
        }

        return true;
    }

    private static function controlDestination(array $args)
    {
        $body = $args['body'];

        if (!empty($body['destination'])) {
            $groups = UserGroupModel::getWithGroups([
                'select'    => ['usergroups.indexation_parameters'],
                'where'     => ['usergroup_content.user_id = ?', 'usergroups.can_index = ?'],
                'data'      => [$GLOBALS['id'], true]
            ]);

            $clauseToProcess = '';
            $allowedEntities = [];
            foreach ($groups as $group) {
                $group['indexation_parameters'] = json_decode($group['indexation_parameters'], true);
                foreach ($group['indexation_parameters']['keywords'] as $keywordValue) {
                    if (!empty($clauseToProcess)) {
                        $clauseToProcess .= ', ';
                    }
                    $clauseToProcess .= IndexingController::KEYWORDS[$keywordValue];
                }
                $allowedEntities = array_merge($allowedEntities, $group['indexation_parameters']['entities']);
                $allowedEntities = array_unique($allowedEntities);
            }

            if (!empty($clauseToProcess)) {
                $preparedClause = PreparedClauseController::getPreparedClause(['clause' => $clauseToProcess, 'userId' => $GLOBALS['id']]);
                $preparedEntities = EntityModel::get(['select' => ['id'], 'where' => ['enabled = ?', "entity_id in {$preparedClause}"], 'data' => ['Y']]);
                $preparedEntities = array_column($preparedEntities, 'id');
                $allowedEntities = array_merge($allowedEntities, $preparedEntities);
                $allowedEntities = array_unique($allowedEntities);
            }

            if (!in_array($body['destination'], $allowedEntities)) {
                return ['errors' => "Body destination is out of your indexing parameters"];
            }
        }

        return true;
    }
}
