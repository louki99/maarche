<?php

/**
* Copyright Maarch since 2008 under licence GPLv3.
* See LICENCE.txt file at the root folder for more details.
* This file is part of Maarch software.
*
*/

/**
* @brief Export Controller
* @author dev@maarch.org
*/

namespace Resource\controllers;

use AcknowledgementReceipt\models\AcknowledgementReceiptModel;
use Attachment\models\AttachmentModel;
use Contact\controllers\ContactController;
use CustomField\models\CustomFieldModel;
use Entity\models\EntityModel;
use Entity\models\ListInstanceModel;
use Folder\controllers\FolderController;
use Folder\models\FolderModel;
use Resource\models\ExportTemplateModel;
use Resource\models\ResModel;
use Resource\models\ResourceListModel;
use Respect\Validation\Validator;
use setasign\Fpdi\Tcpdf\Fpdi;
use Slim\Psr7\Request;
use SrcCore\http\Response;
use SrcCore\controllers\UrlController;
use SrcCore\models\DatabaseModel;
use SrcCore\models\TextFormatModel;
use SrcCore\models\ValidatorModel;
use Tag\models\ResourceTagModel;
use Tag\models\TagModel;
use User\models\UserModel;
use BroadcastList\models\BroadcastListRoleModel;

class ExportController
{
    public function getExportTemplates(Request $request, Response $response)
    {
        $rawTemplates = ExportTemplateModel::getByUserId(['userId' => $GLOBALS['id']]);

        $templates = ['pdf' => ['data' => []], 'csv' => ['data' => []]];
        foreach ($rawTemplates as $rawTemplate) {
            if ($rawTemplate['format'] == 'pdf') {
                $templates['pdf'] = ['data' => json_decode($rawTemplate['data'], true)];
            } elseif ($rawTemplate['format'] == 'csv') {
                $templates['csv'] = ['delimiter' => $rawTemplate['delimiter'], 'data' => json_decode($rawTemplate['data'], true)];
            }
        }

        return $response->withJson(['templates' => $templates]);
    }

    public function updateExport(Request $request, Response $response)
    {
        set_time_limit(240);

        $body = $request->getParsedBody();

        if (!Validator::stringType()->notEmpty()->validate($body['format']) || !in_array($body['format'], ['pdf', 'csv'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Data format is empty or not a string between [\'pdf\', \'csv\']']);
        } elseif ($body['format'] == 'csv' && (!Validator::stringType()->notEmpty()->validate($body['delimiter']) || !in_array($body['delimiter'], [',', ';', 'TAB']))) {
            return $response->withStatus(400)->withJson(['errors' => 'Delimiter is empty or not a string between [\',\', \';\', \'TAB\']']);
        } elseif (!Validator::arrayType()->notEmpty()->validate($body['data'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Data data is empty or not an array']);
        } elseif (!Validator::arrayType()->notEmpty()->validate($body['resources'])) {
            return $response->withStatus(403)->withJson(['errors' => 'Data resources is empty or not an array']);
        } elseif (!ResController::hasRightByResId(['resId' => $body['resources'], 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Document out of perimeter']);
        }

        foreach ($body['data'] as $value) {
            if (!isset($value['value']) || !Validator::stringType()->notEmpty()->validate($value['label']) || !Validator::boolType()->validate($value['isFunction'])) {
                return $response->withStatus(400)->withJson(['errors' => 'One data is not set well']);
            }
        }

        $order = 'CASE res_view_letterbox.res_id ';
        foreach ($body['resources'] as $key => $resId) {
            $order .= "WHEN {$resId} THEN {$key} ";
        }
        $order .= 'END';

        $template = ExportTemplateModel::get(['select' => [1], 'where' => ['user_id = ?', 'format = ?'], 'data' => [$GLOBALS['id'], $body['format']]]);
        if (empty($template)) {
            ExportTemplateModel::create([
                'userId'    => $GLOBALS['id'],
                'format'    => $body['format'],
                'delimiter' => empty($body['delimiter']) ? null : $body['delimiter'],
                'data'      => json_encode($body['data'])
            ]);
        } else {
            ExportTemplateModel::update([
                'set'   => [
                    'delimiter' => empty($body['delimiter']) ? null : $body['delimiter'],
                    'data'      => json_encode($body['data'])
                ],
                'where' => ['user_id = ?', 'format = ?'],
                'data'  => [$GLOBALS['id'], $body['format']]
            ]);
        }

        $select           = ['res_view_letterbox.res_id'];
        $tableFunction    = [];
        $leftJoinFunction = [];
        $csvHead          = [];
        foreach ($body['data'] as $value) {
            $csvHead[] = $value['label'];
            if (empty($value['value'])) {
                continue;
            }
            if ($value['isFunction']) {
                if ($value['value'] == 'getStatus') {
                    $select[] = 'status.label_status AS "status.label_status"';
                    $tableFunction[] = 'status';
                    $leftJoinFunction[] = 'res_view_letterbox.status = status.id';
                } elseif ($value['value'] == 'getPriority') {
                    $select[] = 'priorities.label AS "priorities.label"';
                    $tableFunction[] = 'priorities';
                    $leftJoinFunction[] = 'res_view_letterbox.priority = priorities.id';
                } elseif ($value['value'] == 'getCategory') {
                    $select[] = 'res_view_letterbox.category_id';
                } elseif ($value['value'] == 'getRetentionFrozen') {
                    $select[] = 'res_view_letterbox.retention_frozen';
                } elseif ($value['value'] == 'getBinding') {
                    $select[] = 'res_view_letterbox.binding';
                } elseif ($value['value'] == 'getInitiatorEntity') {
                    $select[] = 'enone.short_label AS "enone.short_label"';
                    $tableFunction[] = 'entities enone';
                    $leftJoinFunction[] = 'res_view_letterbox.initiator = enone.entity_id';
                } elseif ($value['value'] == 'getDestinationEntity') {
                    $select[] = 'entwo.short_label AS "entwo.short_label"';
                    $tableFunction[] = 'entities entwo';
                    $leftJoinFunction[] = 'res_view_letterbox.destination = entwo.entity_id';
                } elseif ($value['value'] == 'getDestinationEntityType') {
                    $select[] = 'enthree.entity_type AS "enthree.entity_type"';
                    $tableFunction[] = 'entities enthree';
                    $leftJoinFunction[] = 'res_view_letterbox.destination = enthree.entity_id';
                } elseif ($value['value'] == 'getTypist') {
                    $select[] = 'res_view_letterbox.typist';
                } elseif ($value['value'] == 'getAssignee') {
                    $select[] = 'res_view_letterbox.dest_user';
                }
            } else {
                $select[] = "res_view_letterbox.{$value['value']}";
            }
        }

        $aChunkedResources = array_chunk($body['resources'], 10000);
        $resources = [];
        foreach ($aChunkedResources as $chunkedResource) {
            $resourcesTmp = ResourceListModel::getOnView([
                'select'    => $select,
                'table'     => $tableFunction,
                'leftJoin'  => $leftJoinFunction,
                'where'     => ['res_view_letterbox.res_id in (?)'],
                'data'      => [$chunkedResource],
                'orderBy'   => [$order]
            ]);
            $resources = array_merge($resources, $resourcesTmp);
        }

        if ($body['format'] == 'csv') {
            $file = ExportController::getCsv(['delimiter' => $body['delimiter'], 'data' => $body['data'], 'resources' => $resources, 'chunkedResIds' => $aChunkedResources]);
            $response->write(stream_get_contents($file));
            $response = $response->withAddedHeader('Content-Disposition', 'attachment; filename=export_maarch.csv');
            $contentType = 'application/vnd.ms-excel';
            fclose($file);
        } else {
            $pdf = ExportController::getPdf(['data' => $body['data'], 'resources' => $resources, 'chunkedResIds' => $aChunkedResources]);

            $fileContent    = $pdf->Output('', 'S');
            $finfo          = new \finfo(FILEINFO_MIME_TYPE);
            $contentType    = $finfo->buffer($fileContent);

            $response->write($fileContent);
            $response = $response->withAddedHeader('Content-Disposition', "inline; filename=maarch.pdf");
        }

        return $response->withHeader('Content-Type', $contentType);
    }

    public static function getCsv(array $aArgs)
    {
        ValidatorModel::notEmpty($aArgs, ['delimiter', 'data', 'resources', 'chunkedResIds']);
        ValidatorModel::stringType($aArgs, ['delimiter']);
        ValidatorModel::arrayType($aArgs, ['data', 'resources', 'chunkedResIds']);

        $file = fopen('php://temp', 'w');
        $delimiter = ($aArgs['delimiter'] == 'TAB' ? "\t" : $aArgs['delimiter']);

        $csvHead = [];
        foreach ($aArgs['data'] as $value) {
            $decoded = utf8_decode($value['label']);
            $csvHead[] = $decoded;
        }

        fputcsv($file, $csvHead, $delimiter);

        foreach ($aArgs['resources'] as $resource) {
            $csvContent = [];
            foreach ($aArgs['data'] as $value) {
                if (empty($value['value'])) {
                    $csvContent[] = '';
                    continue;
                }
                if ($value['isFunction']) {
                    if ($value['value'] == 'getStatus') {
                        $csvContent[] = $resource['status.label_status'];
                    } elseif ($value['value'] == 'getPriority') {
                        $csvContent[] = $resource['priorities.label'];
                    } elseif ($value['value'] == 'getCopies') {
                        $copies       = ExportController::getCopies(['chunkedResIds' => $aArgs['chunkedResIds']]);
                        $csvContent[] = empty($copies[$resource['res_id']]) ? '' : $copies[$resource['res_id']];
                    } elseif ($value['value'] == 'getDetailLink') {
                        $csvContent[] = trim(UrlController::getCoreUrl(), '/') . '/dist/index.html#/resources/'.$resource['res_id'];
                    } elseif ($value['value'] == 'getParentFolder') {
                        $csvContent[] = ExportController::getParentFolderLabel(['res_id' => $resource['res_id']]);
                    } elseif ($value['value'] == 'getFolder') {
                        $csvContent[] = ExportController::getFolderLabel(['res_id' => $resource['res_id']]);
                    } elseif ($value['value'] == 'getCategory') {
                        $csvContent[] = ResModel::getCategoryLabel(['categoryId' => $resource['category_id']]);
                    } elseif ($value['value'] == 'getRetentionFrozen') {
                        $csvContent[] = $resource['retention_frozen'] === true ? 'Y' : 'N';
                    } elseif ($value['value'] == 'getBinding') {
                        if ($resource['binding'] === true) {
                            $csvContent[] = 'Y';
                        } elseif ($resource['binding'] === false) {
                            $csvContent[] = 'N';
                        } else {
                            $csvContent[] = '';
                        }
                    } elseif ($value['value'] == 'getInitiatorEntity') {
                        $csvContent[] = $resource['enone.short_label'];
                    } elseif ($value['value'] == 'getDestinationEntity') {
                        $csvContent[] = $resource['entwo.short_label'];
                    } elseif ($value['value'] == 'getDestinationEntityType') {
                        $csvContent[] = $resource['enthree.entity_type'];
                    } elseif ($value['value'] == 'getSenders') {
                        $senders = ContactController::getFormattedContacts(['resId' => $resource['res_id'], 'mode' => 'sender']);
                        $csvContent[] = implode("\n", $senders);
                    } elseif ($value['value'] == 'getRecipients') {
                        $recipients = ContactController::getFormattedContacts(['resId' => $resource['res_id'], 'mode' => 'recipient']);
                        $csvContent[] = implode("\n", $recipients);
                    } elseif ($value['value'] == 'getTypist') {
                        $csvContent[] = UserModel::getLabelledUserById(['id' => $resource['typist']]);
                    } elseif ($value['value'] == 'getAssignee') {
                        $csvContent[] = UserModel::getLabelledUserById(['id' => $resource['dest_user']]);
                    } elseif ($value['value'] == 'getTags') {
                        $tags = ExportController::getTags(['chunkedResIds' => $aArgs['chunkedResIds']]);
                        $csvContent[] = empty($tags[$resource['res_id']]) ? '' : $tags[$resource['res_id']];
                    } elseif ($value['value'] == 'getSignatories') {
                        $signatories = ExportController::getSignatories(['chunkedResIds' => $aArgs['chunkedResIds']]);
                        $csvContent[] = empty($signatories[$resource['res_id']]) ? '' : $signatories[$resource['res_id']];
                    } elseif ($value['value'] == 'getSignatureDates') {
                        $signatureDates = ExportController::getSignatureDates(['chunkedResIds' => $aArgs['chunkedResIds']]);
                        $csvContent[] = empty($signatureDates[$resource['res_id']]) ? '' : $signatureDates[$resource['res_id']];
                    } elseif ($value['value'] == 'getDepartment') {
                        $department   = ExportController::getDepartment(['chunkedResIds' => $aArgs['chunkedResIds']]);
                        $csvContent[] = empty($department[$resource['res_id']]) ? '' : $department[$resource['res_id']];
                    } elseif ($value['value'] == 'getAcknowledgementSendDate') {
                        $acknwoledgementSendDate = ExportController::getAcknowledgementSendDate(['chunkedResIds' => $aArgs['chunkedResIds']]);
                        $csvContent[] = empty($acknwoledgementSendDate[$resource['res_id']]) ? '' : $acknwoledgementSendDate[$resource['res_id']];
                    } elseif (strpos($value['value'], 'custom_', 0) !== false) {
                        $csvContent[] = ExportController::getCustomFieldValue(['custom' => $value['value'], 'resId' => $resource['res_id']]);
                    } elseif ($value['value'] == 'getVisaCircuit') {
                        $csvContent[] = ExportController::getCircuit(['listType' => 'VISA_CIRCUIT', 'resId' => $resource['res_id']]);
                    } elseif ($value['value'] == 'getOpinionCircuit') {
                        $csvContent[] = ExportController::getCircuit(['listType' => 'AVIS_CIRCUIT', 'resId' => $resource['res_id']]);
                    }
                } else {
                    $allDates = ['doc_date', 'departure_date', 'admission_date', 'process_limit_date', 'opinion_limit_date', 'closing_date'];
                    if (in_array($value['value'], $allDates)) {
                        $csvContent[] = TextFormatModel::formatDate($resource[$value['value']]);
                    } elseif (in_array($value['value'], ['res_id', 'type_label', 'doctypes_first_level_label', 'doctypes_second_level_label', 'format', 'barcode', 'confidentiality', 'alt_identifier', 'subject'])) {
                        $csvContent[] = $resource[$value['value']];
                    }
                }
            }

            foreach ($csvContent as $key => $value) {
                $csvContent[$key] = utf8_decode($value);
            }
            fputcsv($file, $csvContent, $delimiter);
        }

        rewind($file);

        return $file;
    }

    private static function getPdf(array $aArgs)
    {
        ValidatorModel::notEmpty($aArgs, ['data', 'resources', 'chunkedResIds']);
        ValidatorModel::arrayType($aArgs, ['data', 'resources', 'chunkedResIds']);

        $columnsNumber = count($aArgs['data']);
        $orientation = 'P';
        if ($columnsNumber > 5) {
            $orientation = 'L';
        }

        $pdf = new Fpdi($orientation, 'pt');
        $pdf->setPrintHeader(false);

        $pdf->AddPage();
        $dimensions     = $pdf->getPageDimensions();
        $widthNoMargins = $dimensions['w'] - $dimensions['rm'] - $dimensions['lm'];
        $bottomHeight   = $dimensions['h'] - $dimensions['bm'];

        $labels = [];
        foreach ($aArgs['data'] as $value) {
            $labels[] = $value['label'];
        }

        $pdf->SetFont('', 'B', 12);
        $labelHeight = ExportController::getMaximumHeight($pdf, ['data' => $labels, 'width' => $widthNoMargins / $columnsNumber]);
        $pdf->SetFillColor(230, 230, 230);
        foreach ($aArgs['data'] as $value) {
            $pdf->MultiCell($widthNoMargins / $columnsNumber, $labelHeight, $value['label'], 1, 'L', true, 0);
        }

        $pdf->SetY($pdf->GetY() + $labelHeight);
        $pdf->SetFont('', '', 10);

        foreach ($aArgs['resources'] as $resource) {
            $content = [];
            foreach ($aArgs['data'] as $value) {
                if (empty($value['value'])) {
                    $content[] = '';
                    continue;
                }
                if ($value['isFunction']) {
                    if ($value['value'] == 'getStatus') {
                        $content[] = $resource['status.label_status'];
                    } elseif ($value['value'] == 'getPriority') {
                        $content[] = $resource['priorities.label'];
                    } elseif ($value['value'] == 'getCopies') {
                        $copies    = ExportController::getCopies(['chunkedResIds' => $aArgs['chunkedResIds']]);
                        $content[] = empty($copies[$resource['res_id']]) ? '' : $copies[$resource['res_id']];
                    } elseif ($value['value'] == 'getDetailLink') {
                        $content[] = trim(UrlController::getCoreUrl(), '/') . '/dist/index.html#/resources/'.$resource['res_id'];
                    } elseif ($value['value'] == 'getParentFolder') {
                        $content[] = ExportController::getParentFolderLabel(['res_id' => $resource['res_id']]);
                    } elseif ($value['value'] == 'getFolder') {
                        $content[] = ExportController::getFolderLabel(['res_id' => $resource['res_id']]);
                    } elseif ($value['value'] == 'getCategory') {
                        $content[] = ResModel::getCategoryLabel(['categoryId' => $resource['category_id']]);
                    } elseif ($value['value'] == 'getRetentionFrozen') {
                        $content[] = $resource['retention_frozen'] === true ? 'Y' : 'N';
                    } elseif ($value['value'] == 'getBinding') {
                        if ($resource['binding'] === true) {
                            $content[] = 'Y';
                        } elseif ($resource['binding'] === false) {
                            $content[] = 'N';
                        } else {
                            $content[] = '';
                        }
                    } elseif ($value['value'] == 'getInitiatorEntity') {
                        $content[] = $resource['enone.short_label'];
                    } elseif ($value['value'] == 'getDestinationEntity') {
                        $content[] = $resource['entwo.short_label'];
                    } elseif ($value['value'] == 'getDestinationEntityType') {
                        $content[] = $resource['enthree.entity_type'];
                    } elseif ($value['value'] == 'getSenders') {
                        $senders = ContactController::getFormattedContacts(['resId' => $resource['res_id'], 'mode' => 'sender']);
                        $content[] = implode("\n", $senders);
                    } elseif ($value['value'] == 'getRecipients') {
                        $recipients = ContactController::getFormattedContacts(['resId' => $resource['res_id'], 'mode' => 'recipient']);
                        $content[] = implode("\n", $recipients);
                    } elseif ($value['value'] == 'getTypist') {
                        $content[] = UserModel::getLabelledUserById(['id' => $resource['typist']]);
                    } elseif ($value['value'] == 'getAssignee') {
                        $content[] = UserModel::getLabelledUserById(['id' => $resource['dest_user']]);
                    } elseif ($value['value'] == 'getTags') {
                        $tags = ExportController::getTags(['chunkedResIds' => $aArgs['chunkedResIds']]);
                        $content[] = empty($tags[$resource['res_id']]) ? '' : $tags[$resource['res_id']];
                    } elseif ($value['value'] == 'getSignatories') {
                        $signatories = ExportController::getSignatories(['chunkedResIds' => $aArgs['chunkedResIds']]);
                        $content[] = empty($signatories[$resource['res_id']]) ? '' : $signatories[$resource['res_id']];
                    } elseif ($value['value'] == 'getSignatureDates') {
                        $signatureDates = ExportController::getSignatureDates(['chunkedResIds' => $aArgs['chunkedResIds']]);
                        $content[] = empty($signatureDates[$resource['res_id']]) ? '' : $signatureDates[$resource['res_id']];
                    } elseif ($value['value'] == 'getDepartment') {
                        $department = ExportController::getDepartment(['chunkedResIds' => $aArgs['chunkedResIds']]);
                        $content[]  = empty($department[$resource['res_id']]) ? '' : $department[$resource['res_id']];
                    } elseif ($value['value'] == 'getAcknowledgementSendDate') {
                        $acknwoledgementSendDate = ExportController::getAcknowledgementSendDate(['chunkedResIds' => $aArgs['chunkedResIds']]);
                        $content[] = empty($acknwoledgementSendDate[$resource['res_id']]) ? '' : $acknwoledgementSendDate[$resource['res_id']];
                    } elseif (strpos($value['value'], 'custom_', 0) !== false) {
                        $content[] = ExportController::getCustomFieldValue(['custom' => $value['value'], 'resId' => $resource['res_id']]);
                    } elseif ($value['value'] == 'getVisaCircuit') {
                        $content[] = ExportController::getCircuit(['listType' => 'VISA_CIRCUIT', 'resId' => $resource['res_id']]);
                    } elseif ($value['value'] == 'getOpinionCircuit') {
                        $content[] = ExportController::getCircuit(['listType' => 'AVIS_CIRCUIT', 'resId' => $resource['res_id']]);
                    }
                } else {
                    $allDates = ['doc_date', 'departure_date', 'admission_date', 'process_limit_date', 'opinion_limit_date', 'closing_date'];
                    if (in_array($value['value'], $allDates)) {
                        $content[] = TextFormatModel::formatDate($resource[$value['value']]);
                    } else {
                        $content[] = $resource[$value['value']];
                    }
                }
            }
            if (!empty($contentHeight)) {
                $pdf->SetY($pdf->GetY() + $contentHeight);
            }
            $contentHeight = ExportController::getMaximumHeight($pdf, ['data' => $content, 'width' => $widthNoMargins / $columnsNumber]);
            if (($pdf->GetY() + $contentHeight) > $bottomHeight) {
                $pdf->AddPage();
            }
            foreach ($content as $value) {
                $pdf->MultiCell($widthNoMargins / $columnsNumber, $contentHeight, $value, 1, 'L', false, 0);
            }
        }

        return $pdf;
    }

    private static function getCopies(array $args)
    {
        ValidatorModel::notEmpty($args, ['chunkedResIds']);
        ValidatorModel::arrayType($args, ['chunkedResIds']);

        static $aCopies = [];
        if (!empty($aCopies)) {
            return $aCopies;
        }

        foreach ($args['chunkedResIds'] as $resIds) {
            $listInstances = ListInstanceModel::get([
                'select'    => ['item_id', 'item_type', 'res_id'],
                'where'     => ['res_id in (?)', 'difflist_type = ?', 'item_mode = ?'],
                'data'      => [$resIds, 'entity_id', 'cc'],
                'order_by'  => ['res_id']
            ]);

            $resId = '';
            $copies = '';
            if (!empty($listInstances)) {
                foreach ($listInstances as $key => $listInstance) {
                    if ($key != 0 && $resId == $listInstance['res_id']) {
                        $copies .= "\n";
                    } elseif ($key != 0 && $resId != $listInstance['res_id']) {
                        $aCopies[$resId] = $copies;
                        $copies = '';
                    } else {
                        $copies = '';
                    }
                    if ($listInstance['item_type'] == 'user_id') {
                        $copies .= UserModel::getLabelledUserById(['id' => $listInstance['item_id']]);
                    } elseif ($listInstance['item_type'] == 'entity_id') {
                        $entity = EntityModel::getById(['id' => $listInstance['item_id'], 'select' => ['short_label']]);
                        $copies .= $entity['short_label'];
                    }
                    $resId = $listInstance['res_id'];
                }
                $aCopies[$resId] = $copies;
            }
        }

        if (empty($aCopies)) {
            $aCopies = ['empty'];
        }
        return $aCopies;
    }

    private static function getAcknowledgementSendDate(array $args)
    {
        ValidatorModel::notEmpty($args, ['chunkedResIds']);
        ValidatorModel::arrayType($args, ['chunkedResIds']);

        static $acknowledgementSendDate = [];
        if (!empty($acknowledgementSendDate)) {
            return $acknowledgementSendDate;
        }

        foreach ($args['chunkedResIds'] as $resIds) {
            $arSendDate = AcknowledgementReceiptModel::getByResIds([
                'select'  => ['res_id', 'min(send_date) as send_date'],
                'resIds'  => $resIds,
                'where'   => ['send_date IS NOT NULL', 'send_date != \'\''],
                'groupBy' => ['res_id']
            ]);

            $acknowledgementSendDate = [];
            foreach ($arSendDate as $date) {
                $acknowledgementSendDate[$date['res_id']] = TextFormatModel::formatDate($date['send_date']);
            }
        }

        return $acknowledgementSendDate;
    }

    private static function getDepartment(array $args)
    {
        ValidatorModel::notEmpty($args, ['chunkedResIds']);
        ValidatorModel::arrayType($args, ['chunkedResIds']);

        static $aDepartment = [];
        if (!empty($aDepartment)) {
            return $aDepartment;
        }

        foreach ($args['chunkedResIds'] as $resIds) {
            $contactsMatch = DatabaseModel::select([
                'select'    => ['res_id', 'address_postcode'],
                'table'     => ['resource_contacts', 'contacts'],
                'left_join' => ['resource_contacts.item_id = contacts.id'],
                'where'     => ["res_id in (?)", "type = 'contact'","mode = 'sender'", "(address_country ILIKE 'FRANCE' OR address_country = '' OR address_country IS NULL)"],
                'data'      => [$resIds]
            ]);

            $resId = '';
            $departmentName = '';
            if (!empty($contactsMatch)) {
                foreach ($contactsMatch as $key => $contact) {
                    if (empty($contact['address_postcode'])) {
                        continue;
                    }
                    if ($key != 0 && $resId == $contact['res_id']) {
                        $departmentName .= "\n";
                    } elseif ($key != 0 && $resId != $contact['res_id']) {
                        $aDepartment[$resId] = $departmentName;
                        $departmentName = '';
                    } else {
                        $departmentName = '';
                    }
                    $departmentId = substr($contact['address_postcode'], 0, 2);

                    if ((int) $departmentId >= 97 || $departmentId == '20') {
                        $departmentId = substr($contact['address_postcode'], 0, 3);
                        if ((int)$departmentId < 202) {
                            $departmentId = "2A";
                        } elseif ((int)$departmentId >= 202 && (int)$departmentId < 970) {
                            $departmentId = "2B";
                        }
                    }
                    $departmentName .= $departmentId . ' - ' . DepartmentController::getById(['id' => $departmentId]);
                    $resId = $contact['res_id'];
                }
                if (!empty($resId)) {
                    $aDepartment[$resId] = $departmentName;
                }
            }
        }

        if (empty($aDepartment)) {
            $aDepartment = ['empty'];
        }
        return $aDepartment;
    }

    private static function getTags(array $args)
    {
        ValidatorModel::notEmpty($args, ['chunkedResIds']);
        ValidatorModel::arrayType($args, ['chunkedResIds']);

        static $tags = [];
        if (!empty($tags)) {
            return $tags;
        }

        foreach ($args['chunkedResIds'] as $resIds) {
            $tagsRes = ResourceTagModel::get([
                'select'    => ['tag_id', 'res_id'],
                'where'     => ['res_id in (?)'],
                'data'      => [$resIds],
                'order_by'  => ['res_id']
            ]);

            foreach ($tagsRes as $value) {
                $tag = TagModel::getById(['id' => $value['tag_id'], 'select' => ['label']]);
                if (!empty($tags[$value['res_id']])) {
                    $tags[$value['res_id']] .= "\n";
                } else {
                    $tags[$value['res_id']] = '';
                }
                $tags[$value['res_id']] .= $tag['label'];
            }
        }

        return $tags;
    }

    private static function getSignatories(array $args)
    {
        ValidatorModel::notEmpty($args, ['chunkedResIds']);
        ValidatorModel::arrayType($args, ['chunkedResIds']);

        static $aSignatories = [];
        if (!empty($aSignatories)) {
            return $aSignatories;
        }

        foreach ($args['chunkedResIds'] as $resIds) {
            $listInstances = ListInstanceModel::get([
                'select'    => ['item_id', 'res_id'],
                'where'     => ['res_id in (?)', 'item_type = ?', 'signatory = ?'],
                'data'      => [$resIds, 'user_id', true],
                'order_by'  => ['res_id']
            ]);

            foreach ($listInstances as $listInstance) {
                if (!empty($listInstance['item_id'])) {
                    $user = UserModel::getById(['id' => $listInstance['item_id'], 'select' => ['firstname', 'lastname']]);
                    if (!empty($aSignatories[$listInstance['res_id']])) {
                        $aSignatories[$listInstance['res_id']] .= "\n";
                    } else {
                        $aSignatories[$listInstance['res_id']] = '';
                    }
                    $aSignatories[$listInstance['res_id']] .= "{$user['firstname']} {$user['lastname']}";
                } else {
                    $aSignatories[$listInstance['res_id']] .= _USER_DELETED;
                }
            }
        }

        return $aSignatories;
    }

    private static function getSignatureDates(array $args)
    {
        ValidatorModel::notEmpty($args, ['chunkedResIds']);
        ValidatorModel::arrayType($args, ['chunkedResIds']);

        static $aSignatureDates = [];
        if (!empty($aSignatureDates)) {
            return $aSignatureDates;
        }

        foreach ($args['chunkedResIds'] as $resIds) {
            $attachments = AttachmentModel::get([
                'select'    => ['creation_date', 'res_id_master'],
                'where'     => ['res_id_master in (?)', 'attachment_type = ?', 'status = ?'],
                'data'      => [$resIds, 'signed_response', 'TRA'],
                'order_by'  => ['res_id']
            ]);

            foreach ($attachments as $attachment) {
                if (!empty($aSignatureDates[$attachment['res_id']])) {
                    $aSignatureDates[$attachment['res_id_master']] .= "\n";
                } else {
                    $aSignatureDates[$attachment['res_id_master']] = '';
                }
                $aSignatureDates[$attachment['res_id_master']] .= TextFormatModel::formatDate($attachment['creation_date']);
            }
        }

        return $aSignatureDates;
    }

    private static function getMaximumHeight(Fpdi $pdf, array $args)
    {
        ValidatorModel::notEmpty($args, ['data', 'width']);
        ValidatorModel::arrayType($args, ['data']);

        $maxHeight = 1;
        if (!is_numeric($args['width'])) {
            return $maxHeight;
        }
        foreach ($args['data'] as $value) {
            $height = $pdf->getStringHeight($args['width'], $value);
            if ($height > $maxHeight) {
                $maxHeight = $height;
            }
        }

        return $maxHeight + 2;
    }

    private static function getFolderLabel(array $args)
    {
        $folders = FolderModel::getWithResources([
            'select'    => ['folders.id, folders.label'],
            'where'     => ['resources_folders.res_id = ?'],
            'data'      => [$args['res_id']]
        ]);
        if (empty($folders)) {
            return '';
        }

        $labels = [];
        foreach ($folders as $folder) {
            $hasFolder = FolderController::hasFolders([
                'userId' => $GLOBALS['id'],
                'folders' => [$folder['id']]
            ]);
            if ($hasFolder) {
                $labels[] = $folder['label'];
            }
        }

        return implode("\n", $labels);
    }

    private static function getParentFolderLabel(array $args)
    {
        $folders = FolderModel::getWithResources([
            'select'    => ['folders.parent_id'],
            'where'     => ['resources_folders.res_id = ?'],
            'data'      => [$args['res_id']]
        ]);
        if (empty($folders)) {
            return '';
        }

        $parentLabels = [];
        foreach ($folders as $folder) {
            $hasFolder = FolderController::hasFolders([
                'userId' => $GLOBALS['id'],
                'folders' => [$folder['parent_id']]
            ]);

            if (!$hasFolder) {
                continue;
            }
            $parentFolder = FolderModel::getById([
                'id' => $folder['parent_id']
            ]);
            $parentLabels[] = $parentFolder['label'];
        }

        return implode("\n", $parentLabels);
    }

    private static function getCustomFieldValue(array $args)
    {
        ValidatorModel::notEmpty($args, ['custom', 'resId']);
        ValidatorModel::stringType($args, ['custom']);
        ValidatorModel::intVal($args, ['resId']);

        $customField = explode('_', $args['custom']);
        // Custom fields must be in this format : 'custom_<id custom field>'
        // So if the explode returns an array with more or less than 2 elements, the format is wrong
        if (count($customField) != 2) {
            return null;
        }
        $customFieldId = $customField[1];
        $customField = ResModel::get(['select' => ["custom_fields->'{$customFieldId}' as csfield"], 'where' => ['res_id = ?'], 'data' => [$args['resId']]]);
        if (empty($customField[0]['csfield'])) {
            return null;
        }
        $customValues = json_decode($customField[0]['csfield'], true);

        if (!isset($customValues)) {
            return null;
        }

        $field = CustomFieldModel::getById(['select' => ['type', 'values'], 'id' => $customFieldId]);
        $values = json_decode($field['values'], true);

        if ($field['type'] == 'contact') {
            $customValues = ContactController::getContactCustomField(['contacts' => $customValues]);
            $customValues = implode("\n", $customValues);
        } elseif ($field['type'] == 'banAutocomplete') {
            $line = "{$customValues[0]['addressNumber']} {$customValues[0]['addressStreet']} {$customValues[0]['addressTown']} ({$customValues[0]['addressPostcode']})";
            if (!empty($customValues[0]['sector'])) {
                $line .= " - {$customValues[0]['sector']}";
            }
            $line .= "\n";
            $line .= "{$customValues[0]['latitude']},{$customValues[0]['longitude']}";
            $customValues = $line;
        } elseif (!empty($values['table']) && in_array($field['type'], ['radio', 'select', 'checkbox'])) {
            if (!empty($args['resId'])) {
                $values['resId'] = $args['resId'];
            }
            $values = CustomFieldModel::getValuesSQL($values);

            $values = array_column($values, 'label', 'key');
            if (is_array($customValues)) {
                foreach ($customValues as $key => $value) {
                    $customValues[$key] = $values[$value];
                }
                $customValues = implode("\n", $customValues);
            } else {
                $customValues = $values[$customValues];
            }
        } elseif (is_array($customValues)) {
            $customValues = implode("\n", $customValues);
        }

        return $customValues;
    }

    private static function getCircuit(array $args)
    {
        ValidatorModel::notEmpty($args, ['resId', 'listType']);
        ValidatorModel::intVal($args, ['resId']);
        ValidatorModel::stringType($args, ['listType']);

        $list = [];

        $roles = BroadcastListRoleModel::getRoles();
        $roles = array_column($roles, 'label', 'id');

        $listInstances = ListInstanceModel::get([
            'select'    => ['item_id', 'item_mode', 'delegate'],
            'where'     => ['res_id in (?)', 'item_type = ?', 'difflist_type = ?'],
            'data'      => [$args['resId'], 'user_id', $args['listType']],
            'order_by'  => ['sequence']
        ]);

        foreach ($listInstances as $listInstance) {
            if (!empty($listInstance['item_id'])) {
                $user = UserModel::getLabelledUserById(['id' => $listInstance['item_id']]);

                $delegate = null;
                if (!empty($listInstance['delegate'])) {
                    $delegate = UserModel::getLabelledUserById(['id' => $listInstance['delegate']]);
                }

                if ($args['listType'] == 'VISA_CIRCUIT') {
                    if ($listInstance['item_mode'] == 'cc') {
                        $listInstance['item_mode'] = 'copy';
                    }
                    $roleLabel = $roles[$listInstance['item_mode']];

                    if (!empty($delegate)) {
                        $label = "{$delegate} ({$roleLabel}, " . _INSTEAD_OF . " {$user})";
                    } else {
                        $label = "{$user} ({$roleLabel})";
                    }
                } else {
                    if (!empty($delegate)) {
                        $label = "{$delegate} (" . _INSTEAD_OF . " {$user})";
                    } else {
                        $label = "{$user}";
                    }
                }
                $list[] = $label;
            } else {
                $list[] = _USER_DELETED;
            }
        }

        return implode("\n", $list);
    }
}
