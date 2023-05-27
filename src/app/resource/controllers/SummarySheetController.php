<?php

/**
* Copyright Maarch since 2008 under licence GPLv3.
* See LICENCE.txt file at the root folder for more details.
* This file is part of Maarch software.
*
*/

/**
* @brief Summary Sheet Controller
* @author dev@maarch.org
*/

namespace Resource\controllers;

use Attachment\models\AttachmentModel;
use Contact\controllers\ContactController;
use CustomField\models\CustomFieldModel;
use Docserver\models\DocserverModel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Entity\models\EntityModel;
use Entity\models\ListInstanceModel;
use ExternalSignatoryBook\controllers\MaarchParapheurController;
use Group\controllers\PrivilegeController;
use History\models\HistoryModel;
use IndexingModel\models\IndexingModelFieldModel;
use Note\models\NoteEntityModel;
use Note\models\NoteModel;
use Parameter\models\ParameterModel;
use Priority\models\PriorityModel;
use Resource\models\ResModel;
use Respect\Validation\Validator;
use setasign\Fpdi\Tcpdf\Fpdi;
use Slim\Psr7\Request;
use SrcCore\http\Response;
use SrcCore\models\CoreConfigModel;
use SrcCore\models\TextFormatModel;
use SrcCore\models\ValidatorModel;
use Status\models\StatusModel;
use User\models\UserModel;
use BroadcastList\models\BroadcastListRoleModel;

class SummarySheetController
{
    public function createList(Request $request, Response $response)
    {
        set_time_limit(240);

        $bodyData = $request->getParsedBody();

        if (!Validator::arrayType()->notEmpty()->validate($bodyData['resources'])) {
            return $response->withStatus(403)->withJson(['errors' => 'Resources is not set or empty']);
        } elseif (!ResController::hasRightByResId(['resId' => $bodyData['resources'], 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Document out of perimeter']);
        }

        $units = empty($bodyData['units']) ? [] : $bodyData['units'];
        $bodyData['resources'] = array_slice($bodyData['resources'], 0, 500);

        $order = '';
        foreach ($bodyData['resources'] as $key => $resId) {
            $order .= "WHEN {$resId} THEN {$key} ";
        }
        $order .= 'END';

        $orderTable = 'CASE res_id ' . $order;
        $resourcesByModelIds = ResModel::get([
            'select'  => ["string_agg(cast(res_id as text), ',' order by {$orderTable}) as res_ids", 'model_id'],
            'where'   => ['res_id in (?)'],
            'data'    => [$bodyData['resources']],
            'groupBy' => ['model_id']
        ]);

        $pdf = new Fpdi('P', 'pt');
        $pdf->setPrintHeader(false);

        $order = 'CASE res_view_letterbox.res_id ' . $order;

        foreach ($resourcesByModelIds as $resourcesByModelId) {
            $resourcesIdsByModel = $resourcesByModelId['res_ids'];
            $resourcesIdsByModel = explode(',', $resourcesIdsByModel);

            $indexingFields   = IndexingModelFieldModel::get([
                'select' => ['identifier', 'unit'],
                'where'  => ['model_id = ?'],
                'data'   => [$resourcesByModelId['model_id']]
            ]);
            $fieldsIdentifier = array_column($indexingFields, 'identifier');

            $select = ['res_id', 'subject', 'alt_identifier'];
            foreach ($units as $unit) {
                $unit = (array)$unit;
                if ($unit['unit'] == 'primaryInformations') {
                    $information = [
                        'documentDate' => 'doc_date',
                        'arrivalDate'  => 'admission_date',
                        'initiator'    => 'initiator'
                    ];
                    $select = array_merge($select, ['type_label', 'creation_date', 'typist']);

                    foreach ($information as $key => $item) {
                        if (in_array($key, $fieldsIdentifier)) {
                            $select[] = $item;
                        }
                    }
                } elseif ($unit['unit'] == 'secondaryInformations') {
                    $information = [
                        'priority'         => 'priority',
                        'processLimitDate' => 'process_limit_date',
                    ];
                    $select = array_merge($select, ['category_id', 'status', 'retention_frozen', 'binding']);

                    foreach ($information as $key => $item) {
                        if (in_array($key, $fieldsIdentifier)) {
                            $select[] = $item;
                        }
                    }
                } elseif ($unit['unit'] == 'systemTechnicalFields') {
                    $select = array_merge($select, ['format', 'fingerprint', 'filesize', 'creation_date', 'filename', 'docserver_id', 'path', 'typist']);
                } elseif ($unit['unit'] == 'diffusionList') {
                    if (in_array('destination', $fieldsIdentifier)) {
                        $select[] = 'destination';
                    }
                }
            }

            $resources = ResModel::getOnView([
                'select'  => $select,
                'where'   => ['res_view_letterbox.res_id in (?)'],
                'data'    => [$resourcesIdsByModel],
                'orderBy' => [$order]
            ]);

            $resourcesIds = array_column($resources, 'res_id');

            // Data for resources
            $data = SummarySheetController::prepareData(['units' => $units, 'resourcesIds' => $resourcesIds]);

            foreach ($resources as $resource) {
                SummarySheetController::createSummarySheet($pdf, [
                    'resource'         => $resource, 'units' => $units,
                    'login'            => $GLOBALS['login'],
                    'data'             => $data,
                    'fieldsIdentifier' => $fieldsIdentifier
                ]);
            }
        }

        $fileContent = $pdf->Output('', 'S');
        $finfo    = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->buffer($fileContent);

        $queryParams = $request->getQueryParams();
        if (!empty($queryParams['mode']) && $queryParams['mode'] == 'base64') {
            return $response->withJson(['encodedDocument' => base64_encode($fileContent), 'mimeType' => $mimeType]);
        }

        $response->write($fileContent);
        $response = $response->withAddedHeader('Content-Disposition', "inline; filename=maarch.pdf");

        return $response->withHeader('Content-Type', $mimeType);
    }

    public static function createSummarySheet(Fpdi $pdf, array $args)
    {
        ValidatorModel::notEmpty($args, ['resource', 'login']);
        ValidatorModel::arrayType($args, ['resource', 'units', 'data', 'fieldsIdentifier']);
        ValidatorModel::stringType($args, ['login']);

        $resource         = $args['resource'];
        $units            = $args['units'];
        $fieldsIdentifier = $args['fieldsIdentifier'];


        $pdf->AddPage();
        $dimensions     = $pdf->getPageDimensions();
        $widthNoMargins = $dimensions['w'] - $dimensions['rm'] - $dimensions['lm'];
        $bottomHeight   = $dimensions['h'] - $dimensions['bm'];

        $widthMultiCell = $widthNoMargins / 10 * 4.5;
        $widthCell      = $widthNoMargins / 10;
        $widthNotes     = $widthNoMargins / 2;
        $specialWidth   = $widthNoMargins / 4;
        $widthAssignee  = $widthNoMargins / 6;

        $appName = CoreConfigModel::getApplicationName();
        $pdf->SetFont('', '', 8);
        $pdf->Cell(0, 20, mb_strimwidth($appName, 0, 40, '...', 'utf8') . " / " . date('d-m-Y'), 0, 2, 'L', false);
        $pdf->SetY($pdf->GetY() - 20);

        $pdf->SetFont('', 'B', 12);
        $pdf->Cell(0, 20, _SUMMARY_SHEET, 0, 2, 'C', false);

        $pdf->SetFont('', '', 8);
        $pdf->Cell(0, 1, $resource['alt_identifier'], 0, 2, 'C', false);

        $subject = str_replace("\n", ' ', $resource['subject']);

        $hasQrcode = in_array('qrcode', array_column($units, 'unit'));
        if ($hasQrcode) {
            $pdf->SetY($pdf->GetY() + 30);
        }
        $pdf->SetY($pdf->GetY() + 15);
        $pdf->SetFont('', 'B', 16);
        $pdf->MultiCell(0, 1, $subject, 1, 'C', false);

        if ($hasQrcode) {
            $pdf->SetY($pdf->GetY() - 20);
        }

        foreach ($units as $key => $unit) {
            $units[$key] = (array)$unit;
            $unit        = (array)$unit;
            if ($unit['unit'] == 'qrcode') {
                $parameter = ParameterModel::getById(['select' => ['param_value_int'], 'id' => 'QrCodePrefix']);
                $prefix = '';
                if ($parameter['param_value_int'] == 1) {
                    $prefix = 'MAARCH_';
                }
                $qrCode = new QrCode($prefix . $resource['res_id']);
                $qrCode->setSize(400);
                $qrCode->setMargin(25);

                $pngWriter = new PngWriter();
                $qrCodeResult = $pngWriter->write($qrCode);
                $qrCodeResult = $qrCodeResult->getString();

                $pdf->Image('@' . $qrCodeResult, 485, 10, 90, 90);
            }
        }
        foreach ($units as $key => $unit) {
            if ($unit['unit'] == 'primaryInformations') {
                $admissionDate = null;
                if (in_array('arrivalDate', $fieldsIdentifier)) {
                    $admissionDate = TextFormatModel::formatDate($resource['admission_date'], 'd-m-Y');
                    $admissionDate = empty($admissionDate) ? '<i>' . _UNDEFINED . '</i>' : "<b>{$admissionDate}</b>";
                }

                $creationdate  = TextFormatModel::formatDate($resource['creation_date'], 'd-m-Y');
                $creationdate  = empty($creationdate) ? '<i>'._UNDEFINED.'</i>' : "<b>{$creationdate}</b>";

                $docDate = null;
                if (in_array('documentDate', $fieldsIdentifier)) {
                    $docDate = TextFormatModel::formatDate($resource['doc_date'], 'd-m-Y');
                    $docDate = empty($docDate) ? '<i>' . _UNDEFINED . '</i>' : "<b>{$docDate}</b>";
                }

                if (!empty($resource['initiator'])) {
                    $initiator = EntityModel::getByEntityId(['entityId' => $resource['initiator'], 'select' => ['short_label']]);
                }
                $initiatorEntity = empty($initiator) ? '' : "({$initiator['short_label']})";

                $typist          = UserModel::getLabelledUserById(['id' => $resource['typist']]);
                $doctype         = empty($resource['type_label']) ? '<i>'._UNDEFINED.'</i>' : "<b>{$resource['type_label']}</b>";

                $pdf->SetY($pdf->GetY() + 40);
                if (($pdf->GetY() + 77) > $bottomHeight) {
                    $pdf->AddPage();
                }
                if (!($key == 0 || ($key == 1 && $units[0]['unit'] == 'qrcode'))) {
                    $pdf->SetFont('', 'B', 11);
                    $pdf->Cell(0, 15, $unit['label'], 0, 2, 'L', false);
                    $pdf->SetY($pdf->GetY() + 2);
                }

                $pdf->SetFont('', '', 10);

                $pdf->MultiCell($widthMultiCell, 15, _CREATED . " : {$creationdate}", 0, 'L', false, 0, '', '', true, 0, true);

                if (isset($docDate)) {
                    $pdf->Cell($widthCell, 15, '', 0, 0, 'L', false);
                    $pdf->MultiCell($widthMultiCell, 15, _DOC_DATE . " : {$docDate}", 0, 'L', false, 1, '', '', true, 0, true);
                } else {
                    $pdf->Cell($widthCell, 15, '', 0, 1, 'L', false);
                }

                if (isset($admissionDate)) {
                    $pdf->MultiCell($widthMultiCell, 15, _ADMISSION_DATE . " : {$admissionDate}", 0, 'L', false, 1, '', '', true, 0, true);
                }

                $pdf->MultiCell($widthMultiCell * 2, 15, _TYPIST . " : <b>{$typist} {$initiatorEntity}</b>", 0, 'L', false, 1, '', '', true, 0, true);

                $pdf->MultiCell($widthMultiCell * 2, 15, _DOCTYPE . " : {$doctype}", 0, 'L', false, 0, '', '', true, 0, true);
                $pdf->Cell($widthCell, 15, '', 0, 0, 'L', false);
            } elseif ($unit['unit'] == 'secondaryInformations') {
                $category = ResModel::getCategoryLabel(['categoryId' => $resource['category_id']]);
                $category = empty($category) ? '<i>'._UNDEFINED.'</i>' : "<b>{$category}</b>";

                if (!empty($resource['status'])) {
                    $status = StatusModel::getById(['id' => $resource['status'], 'select' => ['label_status']]);
                }
                $status = empty($status['label_status']) ? '<i>' . _UNDEFINED . '</i>' : "<b>{$status['label_status']}</b>";

                $retentionRuleFrozen = empty($resource['retention_frozen']) ? '<b>' . _NO . '</b>' : '<b>' . _YES . '</b>';

                if (!isset($resource['binding'])) {
                    $binding = '<i>' . _UNDEFINED . '</i>';
                } else {
                    $binding = empty($resource['binding']) ? '<b>' . _NO . '</b>' : '<b>' . _YES . '</b>';
                }

                $priority = null;
                if (in_array('priority', $fieldsIdentifier)) {
                    $priority = '';
                    if (!empty($resource['priority'])) {
                        $priority = PriorityModel::getById(['id' => $resource['priority'], 'select' => ['label']]);
                    }
                    $priority = empty($priority['label']) ? '<i>' . _UNDEFINED . '</i>' : "<b>{$priority['label']}</b>";
                }

                $processLimitDate = null;
                if (in_array('processLimitDate', $fieldsIdentifier)) {
                    $processLimitDate = TextFormatModel::formatDate($resource['process_limit_date'], 'd-m-Y');
                    $processLimitDate = empty($processLimitDate) ? '<i>' . _UNDEFINED . '</i>' : "<b>{$processLimitDate}</b>";
                }

                // Custom fields
                $customFieldsValues = ResModel::get([
                    'select' => ['custom_fields'],
                    'where' => ['res_id = ?'],
                    'data' => [$resource['res_id']]
                ]);
                // Get all the ids of the custom fields in the model
                $customFieldsIds = [];
                foreach ($fieldsIdentifier as $item) {
                    if (strpos($item, 'indexingCustomField_') !== false) {
                        $customFieldsIds[] = explode('_', $item)[1];
                    }
                }

                if (!empty($customFieldsIds)) {
                    // get the label of the custom fields
                    $customFields = CustomFieldModel::get([
                        'select' => ['id', 'label', 'values', 'type'],
                        'where'  => ['id in (?)'],
                        'data'   => [$customFieldsIds]
                    ]);

                    $customFieldsRawValues = array_column($customFields, 'values', 'id');
                    $customFieldsRawTypes = array_column($customFields, 'type', 'id');
                    $customFields = array_column($customFields, 'label', 'id');

                    $customFieldsValues = $customFieldsValues[0]['custom_fields'] ?? null;
                    $customFieldsValues = json_decode($customFieldsValues, true);
                }

                $pdf->SetY($pdf->GetY() + 40);
                if (($pdf->GetY() + 57) > $bottomHeight) {
                    $pdf->AddPage();
                }
                $pdf->SetFont('', 'B', 11);
                $pdf->Cell(0, 15, $unit['label'], 0, 2, 'L', false);
                $pdf->SetY($pdf->GetY() + 2);

                $pdf->SetFont('', '', 10);
                $pdf->MultiCell($widthNotes, 30, _CATEGORY . " : {$category}", 1, 'L', false, 0, '', '', true, 0, true);

                $pdf->MultiCell($widthNotes, 30, _STATUS . " : {$status}", 1, 'L', false, 1, '', '', true, 0, true);

                $pdf->MultiCell($widthNotes, 30, _RETENTION_RULE_FROZEN . " : {$retentionRuleFrozen}", 1, 'L', false, 0, '', '', true, 0, true);
                $pdf->MultiCell($widthNotes, 30, _BINDING_DOCUMENT . " : {$binding}", 1, 'L', false, 1, '', '', true, 0, true);

                $nextLine = 1;
                if (isset($priority)) {
                    $nextLine = isset($processLimitDate) || !empty($customFieldsIds) ? 0 : 1;
                    $pdf->MultiCell($widthNotes, 30, _PRIORITY . " : {$priority}", 1, 'L', false, $nextLine, '', '', true, 0, true);
                }
                if (isset($processLimitDate)) {
                    $nextLine = !empty($customFieldsIds) && $nextLine == 0 ? 1 : 0;
                    $pdf->MultiCell($widthNotes, 30, _PROCESS_LIMIT_DATE . " : {$processLimitDate}", 1, 'L', false, $nextLine, '', '', true, 0, true);
                }

                if (!empty($customFieldsIds)) {
                    $fieldsType = CustomFieldModel::get(['select' => ['type', 'id'], 'where' => ['id in (?)'], 'data' => [$customFieldsIds]]);
                    $fieldsType = array_column($fieldsType, 'type', 'id');

                    foreach ($customFieldsIds as $customFieldsId) {
                        $label = $customFields[$customFieldsId];
                        $rawValues = json_decode($customFieldsRawValues[$customFieldsId], true);
                        if (!empty($rawValues['table']) && in_array($customFieldsRawTypes[$customFieldsId], ['radio', 'select', 'checkbox'])) {
                            if (!empty($resource['res_id'])) {
                                $rawValues['resId'] = $resource['res_id'];
                            }
                            $rawValues = CustomFieldModel::getValuesSQL($rawValues);

                            $rawValues = array_column($rawValues, 'label', 'key');
                            if (is_array($customFieldsValues[$customFieldsId])) {
                                foreach ($customFieldsValues[$customFieldsId] as $key => $value) {
                                    $customFieldsValues[$customFieldsId][$key] = $rawValues[$value];
                                }
                            } else {
                                $customFieldsValues[$customFieldsId] = $rawValues[$customFieldsValues[$customFieldsId]];
                            }
                        }
                        if (is_array($customFieldsValues[$customFieldsId])) {
                            $customValue = "";
                            if (!empty($customFieldsValues[$customFieldsId])) {
                                if ($fieldsType[$customFieldsId] == 'banAutocomplete') {
                                    $customValue = "{$customFieldsValues[$customFieldsId][0]['addressNumber']} {$customFieldsValues[$customFieldsId][0]['addressStreet']} {$customFieldsValues[$customFieldsId][0]['addressTown']} ({$customFieldsValues[$customFieldsId][0]['addressPostcode']})";
                                    if (!empty($customFieldsValues[$customFieldsId][0]['sector'])) {
                                        $customValue .= " - {$customFieldsValues[$customFieldsId][0]['sector']}";
                                    }
                                } elseif ($fieldsType[$customFieldsId] == 'contact') {
                                    $customValues = ContactController::getContactCustomField(['contacts' => $customFieldsValues[$customFieldsId]]);
                                    $customValue = count($customValues) > 2 ? count($customValues) . ' ' . _CONTACTS : implode(", ", $customValues);
                                    if (count($customValues) < 3) {
                                        $pdf->SetFont('', '', 8);
                                    }
                                } else {
                                    $customValue = implode(',', $customFieldsValues[$customFieldsId]);
                                }
                            }
                            $value = !empty($customValue) ? '<b>' . $customValue . '</b>' : '<i>' . _UNDEFINED . '</i>';
                        } else {
                            $value = $customFieldsValues[$customFieldsId] ? '<b>' . $customFieldsValues[$customFieldsId] . '</b>' : '<i>' . _UNDEFINED . '</i>';
                        }

                        $nextLine = ($nextLine + 1) % 2;
                        $pdf->MultiCell($widthNotes*2, 30, $label . " : {$value}", 1, 'L', false, 1, '', '', true, 0, true, true);
                        $pdf->SetFont('', '', 10);
                    }
                }
            } elseif ($unit['unit'] == 'systemTechnicalFields') {
                if (PrivilegeController::hasPrivilege(['privilegeId' => 'view_technical_infos', 'userId' => $GLOBALS['id']])) {
                    if (!empty($resource['docserver_id'])) {
                        $docserver = DocserverModel::getByDocserverId(['docserverId' => $resource['docserver_id'], 'select' => ['path_template']]);
                        $docserverPathFile = $docserver['path_template'] . $resource['path'];
                        $docserverPathFile = str_replace('//', '/', $docserverPathFile);
                        $docserverPathFile = str_replace('#', '/', $docserverPathFile);
                    }
            
                    $typistLabel  = UserModel::getLabelledUserById(['id' => $resource['typist']]);
                    $fulltextInfo = ResModel::getById(['select' => ['fulltext_result'], 'resId' => $resource['res_id']]);

                    $pdf->SetY($pdf->GetY() + 40);
                    if (($pdf->GetY() + 57) > $bottomHeight) {
                        $pdf->AddPage();
                    }
                    $pdf->SetFont('', 'B', 11);
                    $pdf->Cell(0, 15, $unit['label'], 0, 2, 'L', false);
                    $pdf->SetY($pdf->GetY() + 2);
    
                    $pdf->SetFont('', '', 10);
                    $pdf->MultiCell($widthNotes, 30, _TYPIST . " : {$typistLabel}", 1, 'L', false, 0, '', '', true, 0, true);

                    $creationDate = TextFormatModel::formatDate($resource['creation_date'], 'd-m-Y');
                    $pdf->MultiCell($widthNotes, 30, _CREATION_DATE . " : {$creationDate}", 1, 'L', false, 1, '', '', true, 0, true);
    
                    $nextLine = 1;
                    if (!empty($resource['filesize'])) {
                        $resource['filesize'] = StoreController::getFormattedSizeFromBytes(['size' => $resource['filesize']]);
                        $nextLine = ($nextLine + 1) % 2;
                        $pdf->MultiCell($widthNotes, 30, _SIZE . " : {$resource['filesize']}", 1, 'L', false, $nextLine, '', '', true, 0, true);
                    }
                    if (!empty($resource['format'])) {
                        $resource['format'] = strtoupper($resource['format']);
                        $nextLine = ($nextLine + 1) % 2;
                        $pdf->MultiCell($widthNotes, 30, _FORMAT . " : {$resource['format']}", 1, 'L', false, $nextLine, '', '', true, 0, true);
                    }
                    if (!empty($resource['filename'])) {
                        $nextLine = ($nextLine + 1) % 2;
                        $pdf->MultiCell($widthNotes, 30, _FILENAME . " : {$resource['filename']}", 1, 'L', false, $nextLine, '', '', true, 0, true);
                    }
                    if (!empty($docserverPathFile)) {
                        $nextLine = ($nextLine + 1) % 2;
                        $pdf->MultiCell($widthNotes, 30, _DOCSERVER_PATH_FILE . " : {$docserverPathFile}", 1, 'L', false, $nextLine, '', '', true, 0, true);
                    }
                    if (!empty($resource['fingerprint'])) {
                        $pdf->SetFont('', '', 8);
                        $nextLine = ($nextLine + 1) % 2;
                        $pdf->MultiCell($widthNotes, 30, _FINGERPRINT . " : {$resource['fingerprint']}", 1, 'L', false, $nextLine, '', '', true, 0, true);
                        $pdf->SetFont('', '', 10);
                    }
                    if (!empty($fulltextInfo['fulltext_result'])) {
                        $nextLine = ($nextLine + 1) % 2;
                        $fulltextResult = $fulltextInfo['fulltext_result'] == 'SUCCESS' ? _SUCCESS : _ERROR;
                        $pdf->MultiCell($widthNotes, 30, _FULLTEXT . " : {$fulltextResult}", 1, 'L', false, $nextLine, '', '', true, 0, true);
                    }
                }
            } elseif ($unit['unit'] == 'customTechnicalFields') {
                if (PrivilegeController::hasPrivilege(['privilegeId' => 'view_technical_infos', 'userId' => $GLOBALS['id']])) {
                    $customFieldsValues = ResModel::get([
                        'select' => ['custom_fields'],
                        'where'  => ['res_id = ?'],
                        'data'   => [$resource['res_id']]
                    ]);
                    // Get all the ids of technical custom fields
                    $customFields    = CustomFieldModel::get(['where' => ['mode = ?'], 'data' => ['technical'], 'orderBy' => ['label']]);
                    $customFieldsIds = array_column($customFields, 'id');
    
                    if (!empty($customFieldsIds)) {
                        $customFieldsRawValues = array_column($customFields, 'values', 'id');
                        $customFieldsRawTypes = array_column($customFields, 'type', 'id');
                        $customFields = array_column($customFields, 'label', 'id');
    
                        $customFieldsValues = $customFieldsValues[0]['custom_fields'] ?? null;
                        $customFieldsValues = json_decode($customFieldsValues, true);
                    }
    
                    $pdf->SetY($pdf->GetY() + 40);
                    if (($pdf->GetY() + 57) > $bottomHeight) {
                        $pdf->AddPage();
                    }
                    $pdf->SetFont('', 'B', 11);
                    $pdf->Cell(0, 15, $unit['label'], 0, 2, 'L', false);
                    $pdf->SetY($pdf->GetY() + 2);
                    $pdf->SetFont('', '', 10);
    
                    $nextLine = 1;
                    if (!empty($customFieldsIds)) {
                        $fieldsType = CustomFieldModel::get(['select' => ['type', 'id'], 'where' => ['id in (?)'], 'data' => [$customFieldsIds]]);
                        $fieldsType = array_column($fieldsType, 'type', 'id');
    
                        foreach ($customFieldsIds as $customFieldsId) {
                            $label = $customFields[$customFieldsId];
                            $rawValues = json_decode($customFieldsRawValues[$customFieldsId], true);
                            if (!empty($rawValues['table']) && in_array($customFieldsRawTypes[$customFieldsId], ['radio', 'select', 'checkbox'])) {
                                if (!empty($resource['res_id'])) {
                                    $rawValues['resId'] = $resource['res_id'];
                                }
                                $rawValues = CustomFieldModel::getValuesSQL($rawValues);
    
                                $rawValues = array_column($rawValues, 'label', 'key');
                                if (is_array($customFieldsValues[$customFieldsId])) {
                                    foreach ($customFieldsValues[$customFieldsId] as $key => $value) {
                                        $customFieldsValues[$customFieldsId][$key] = $rawValues[$value];
                                    }
                                } else {
                                    $customFieldsValues[$customFieldsId] = $rawValues[$customFieldsValues[$customFieldsId]];
                                }
                            }
                            if (is_array($customFieldsValues[$customFieldsId] ?? null)) {
                                $customValue = "";
                                if (!empty($customFieldsValues[$customFieldsId])) {
                                    if ($fieldsType[$customFieldsId] == 'banAutocomplete') {
                                        $customValue = "{$customFieldsValues[$customFieldsId][0]['addressNumber']} {$customFieldsValues[$customFieldsId][0]['addressStreet']} {$customFieldsValues[$customFieldsId][0]['addressTown']} ({$customFieldsValues[$customFieldsId][0]['addressPostcode']})";
                                        if (!empty($customFieldsValues[$customFieldsId][0]['sector'])) {
                                            $customValue .= " - {$customFieldsValues[$customFieldsId][0]['sector']}";
                                        }
                                    } elseif ($fieldsType[$customFieldsId] == 'contact') {
                                        $customValues = ContactController::getContactCustomField(['contacts' => $customFieldsValues[$customFieldsId]]);
                                        $customValue = count($customValues) > 2 ? count($customValues) . ' ' . _CONTACTS : implode(", ", $customValues);
                                        if (count($customValues) < 3) {
                                            $pdf->SetFont('', '', 8);
                                        }
                                    } else {
                                        $customValue = implode(',', $customFieldsValues[$customFieldsId]);
                                    }
                                }
                                $value = !empty($customValue) ? '<b>' . $customValue . '</b>' : '<i>' . _UNDEFINED . '</i>';
                            } else {
                                $value = isset($customFieldsValues[$customFieldsId]) ? '<b>' . $customFieldsValues[$customFieldsId] . '</b>' : '<i>' . _UNDEFINED . '</i>';
                            }
    
                            $nextLine = ($nextLine + 1) % 2;
                            $pdf->MultiCell($widthNotes, 30, $label . " : {$value}", 1, 'L', false, $nextLine, '', '', true, 0, true);
                            $pdf->SetFont('', '', 10);
                        }
                    }
                }
            } elseif ($unit['unit'] == 'senderRecipientInformations') {
                $senders = null;
                if (in_array('senders', $fieldsIdentifier)) {
                    $senders = ContactController::getFormattedContacts([
                        'resId' => $resource['res_id'],
                        'mode'  => 'sender'
                    ]);
                    if (!empty($senders) && count($senders) > 2) {
                        $nbSenders = count($senders);
                        $senders = [];
                        $senders[0] = $nbSenders . ' ' . _CONTACTS;
                    } elseif (empty($senders)) {
                        $senders = [''];
                    }
                }

                $recipients = null;
                if (in_array('recipients', $fieldsIdentifier)) {
                    $recipients = ContactController::getFormattedContacts([
                        'resId' => $resource['res_id'],
                        'mode'  => 'recipient'
                    ]);
                    if (!empty($recipients) && count($recipients) > 2) {
                        $nbRecipients = count($recipients);
                        $recipients = [];
                        $recipients[0] = $nbRecipients . ' ' . _CONTACTS;
                    } elseif (empty($recipients)) {
                        $recipients = [''];
                    }
                }

                // If senders and recipients are both null, they are not part of the model so we continue to the next unit
                if ($senders === null && $recipients === null) {
                    continue;
                }

                $pdf->SetY($pdf->GetY() + 40);
                if (($pdf->GetY() + 57) > $bottomHeight) {
                    $pdf->AddPage();
                }
                $pdf->SetFont('', 'B', 11);
                $pdf->Cell(0, 15, $unit['label'], 0, 2, 'L', false);
                $pdf->SetY($pdf->GetY() + 2);

                $pdf->SetFont('', '', 10);

                $correspondents = [];
                if ($senders !== null && $recipients !== null) {
                    if (empty($senders[0]) && empty($recipients[0])) {
                        $correspondents = [null, null];
                    } else {
                        for ($i = 0; !empty($senders[$i]) || !empty($recipients[$i]); $i++) {
                            $correspondents[] = $senders[$i] ?? null;
                            $correspondents[] = $recipients[$i] ?? null;
                        }
                    }

                    $pdf->Cell($widthMultiCell, 15, _SENDERS, 1, 0, 'C', false);
                    $pdf->Cell($widthCell, 15, '', 0, 0, 'C', false);
                    $pdf->Cell($widthMultiCell, 15, _RECIPIENTS, 1, 1, 'C', false);
                } elseif ($senders !== null && $recipients === null) {
                    $correspondents = $senders;

                    $pdf->Cell($widthMultiCell, 15, _SENDERS, 1, 1, 'C', false);
                } elseif ($senders === null && $recipients !== null) {
                    $correspondents = $recipients;

                    $pdf->Cell($widthMultiCell, 15, _RECIPIENTS, 1, 1, 'C', false);
                }

                // allow to skip an element in the senders or recipients column if we already printed UNDEFINED once
                $columnUndefined = [false, false];
                $nextLine = 1;
                foreach ($correspondents as $correspondent) {
                    // if senders and recipients are not null, nextLine alternate between 0 and 1, otherwise its always 1
                    if ($senders !== null && $recipients !== null) {
                        $nextLine = ($nextLine + 1) % 2;

                        if ($columnUndefined[$nextLine]) {
                            $pdf->MultiCell($widthMultiCell, 40, '', 0, 'L', false, 0, '', '', true, 0, true);
                            $pdf->MultiCell($widthCell, 40, '', 0, 'L', false, $nextLine, '', '', true, 0, true);
                            continue;
                        }
                    } else {
                        $nextLine = 1;
                    }

                    if (empty($correspondent)) {
                        $columnUndefined[$nextLine] = true;
                        $pdf->MultiCell($widthMultiCell, 40, _UNDEFINED, 1, 'L', false, $nextLine, '', '', true, 0, true);
                    } else {
                        $pdf->MultiCell($widthMultiCell, 40, empty($correspondent) ? '' : $correspondent, empty($correspondent) ? 0 : 1, 'L', false, $nextLine, '', '', true, 0, true);
                    }

                    if ($nextLine == 0) {
                        $pdf->MultiCell($widthCell, 40, '', 0, 'L', false, 0, '', '', true, 0, true);
                    }
                }
            } elseif ($unit['unit'] == 'diffusionList') {
                $assignee    = '';
                $destination = '';
                $found       = false;
                $roles       = BroadcastListRoleModel::getRoles();
                $rolesItems  = [];
                $nbItems     = 0;
                foreach ($args['data']['listInstances'] as $listKey => $listInstance) {
                    if ($found && $listInstance['res_id'] != $resource['res_id']) {
                        break;
                    } elseif ($listInstance['res_id'] == $resource['res_id']) {
                        $item = '';
                        if ($listInstance['item_type'] == 'user_id') {
                            $user   = UserModel::getById(['id' => $listInstance['item_id'], 'select' => ['id', 'firstname', 'lastname']]);
                            $entity = UserModel::getPrimaryEntityById(['id' => $user['id'], 'select' => ['entities.entity_label']]);

                            if ($listInstance['item_mode'] == 'dest') {
                                $item = $user['firstname'] . ' ' . $user['lastname'];
                            } else {
                                $item = "{$user['firstname']} {$user['lastname']} ({$entity['entity_label']})";
                            }
                        } elseif ($listInstance['item_type'] == 'entity_id') {
                            $item   = $listInstance['item_id'];
                            $entity = EntityModel::getById(['id' => $listInstance['item_id'], 'select' => ['short_label', 'entity_id']]);
                            if (!empty($entity)) {
                                $item = "{$entity['short_label']} ({$entity['entity_id']})";
                            }
                        }
                        if ($listInstance['item_mode'] == 'dest') {
                            $assignee = $item;
                        } else {
                            foreach ($roles as $role) {
                                if ($listInstance['item_mode'] == $role['id'] || ($listInstance['item_mode'] == 'cc' && $role['id'] == 'copy')) {
                                    $rolesItems[$role['id']]['item'][] = $item;
                                    $rolesItems[$role['id']]['label'] = $role['label'];
                                    $nbItems++;
                                    continue;
                                }
                            }
                        }
                        unset($args['data']['listInstances'][$listKey]);
                        $found = true;
                    }
                }

                // Sort keys to be in the same order defined in the roles database
                $rolesIDs = array_column($roles, 'id');
                $tmp      = [];
                foreach ($rolesIDs as $key) {
                    if (!empty($rolesItems[$key])) {
                        $tmp[$key] = $rolesItems[$key];
                    }
                }
                $rolesItems = $tmp;

                if (!empty($resource['destination'])) {
                    $destination = EntityModel::getByEntityId(['entityId' => $resource['destination'], 'select' => ['short_label']]);
                }
                $destinationEntity = empty($destination) ? '' : "({$destination['short_label']})";

                if (empty($assignee)) {
                    $assignee = _UNDEFINED;
                }
                $pdf->SetY($pdf->GetY() + 40);
                if (($pdf->GetY() + 37 + $nbItems * 20) > $bottomHeight) {
                    $pdf->AddPage();
                }
                $pdf->SetFont('', 'B', 11);
                $pdf->Cell(0, 15, $unit['label'], 0, 2, 'L', false);
                $pdf->SetY($pdf->GetY() + 2);

                $pdf->SetFont('', '', 10);
                $pdf->MultiCell($widthAssignee, 20, _ASSIGNEE, 1, 'C', false, 0, '', '', true, 0, false, true, 20, 'M');
                $pdf->SetFont('', 'B', 10);
                $pdf->Cell($widthAssignee * 5, 20, "- {$assignee} {$destinationEntity}", 1, 1, 'L', false);

                foreach ($rolesItems as $rolesItem) {
                    $pdf->SetFont('', '', 10);
                    $pdf->MultiCell($widthAssignee, count($rolesItem['item']) * 20, $rolesItem['label'], 1, 'C', false, 0, '', '', true, 0, false, true, count($rolesItem['item']) * 20, 'M');

                    $nbItems = count($rolesItem['item']);
                    $i = 0;
                    foreach ($rolesItem['item'] as $item) {
                        $nextLine = $i == ($nbItems - 1) ? 1 : 2;
                        $pdf->Cell($widthAssignee * 5, 20, "- {$item}", 1, $nextLine, 'L', false);
                        $i++;
                    }
                }
            } elseif ($unit['unit'] == 'visaWorkflow') {
                $users = [];
                $found = false;

                foreach ($args['data']['listInstancesVisa'] as $listKey => $listInstance) {
                    if ($found && $listInstance['res_id'] != $resource['res_id']) {
                        break;
                    } elseif ($listInstance['res_id'] == $resource['res_id']) {
                        if (!empty($listInstance['process_date']) && $listInstance['process_comment'] != _INTERRUPTED_WORKFLOW) {
                            $mode = ($listInstance['signatory'] ? _SIGNATORY : _VISA_USER_MIN);
                        } else {
                            $mode = ($listInstance['requested_signature'] ? _SIGNATORY : _VISA_USER_MIN);
                        }
                        $userLabel = UserModel::getLabelledUserById(['id' => $listInstance['item_id']]);

                        $delegate = !empty($listInstance['delegate']) ? UserModel::getLabelledUserById(['id' => $listInstance['delegate']]) : '';
                        if (!empty($delegate)) {
                            $mode .= ', ' . _INSTEAD_OF . ' ' . $userLabel;
                            $userLabel = $delegate . " ({$mode})";
                        } else {
                            $userLabel .= " ({$mode})";
                        }

                        if (!empty($listInstance['process_date'])) {
                            if (empty($listInstance['process_comment'])) {
                                $userLabel .= ',' .  ($listInstance['signatory'] ? _SIGNED : _VALIDATED);
                            } else {
                                $userLabel .= ', ' . $listInstance['process_comment'];
                            }
                        }

                        $users[] = [
                            'user'  => $userLabel,
                            'date'  => TextFormatModel::formatDate($listInstance['process_date']),
                        ];
                        unset($args['data']['listInstancesVisa'][$listKey]);
                        $found = true;
                    }
                }

                if (!empty($users)) {
                    $pdf->SetY($pdf->GetY() + 40);
                    if (($pdf->GetY() + 37 + count($users) * 20) > $bottomHeight) {
                        $pdf->AddPage();
                    }
                    $pdf->SetFont('', 'B', 11);
                    $pdf->Cell(0, 15, $unit['label'], 0, 2, 'L', false);
                    $pdf->SetY($pdf->GetY() + 2);

                    $pdf->SetFont('', '', 10);
                    $pdf->Cell($specialWidth * 3, 20, _USERS, 1, 0, 'L', false);
                    $pdf->Cell($specialWidth, 20, _ACTION_DATE, 1, 1, 'L', false);
                    foreach ($users as $keyUser => $user) {
                        $pdf->MultiCell($specialWidth * 3, 20, $keyUser + 1 . ". {$user['user']}", 1, 'L', false, 0, '', '', true, 0, false, true, 20, 'M', true);
                        $pdf->Cell($specialWidth, 20, $user['date'], 1, 1, 'L', false);
                    }
                }
            } elseif ($unit['unit'] == 'opinionWorkflow') {
                $users = [];
                $found = false;
                foreach ($args['data']['listInstancesOpinion'] as $listKey => $listInstance) {
                    if ($found && $listInstance['res_id'] != $resource['res_id']) {
                        break;
                    } elseif ($listInstance['res_id'] == $resource['res_id']) {
                        $user = UserModel::getLabelledUserById(['id' => $listInstance['item_id']]);
                        $entity = UserModel::getPrimaryEntityById(['id' => $listInstance['item_id'], 'select' => ['entities.entity_label']]);

                        $entityLabel = $entity['entity_label'];
                        $userLabel = $user;
                        $delegate = !empty($listInstance['delegate']) ? UserModel::getLabelledUserById(['id' => $listInstance['delegate']]) : '';

                        if (!empty($delegate)) {
                            $entityLabel .= ', ' .  _INSTEAD_OF . ' ' . $userLabel;
                            $userLabel = $delegate . " (" . $entityLabel . ")";
                        } else {
                            $userLabel .= " (" . $entityLabel . ")";
                        }

                        $users[] = [
                            'user'  => $userLabel,
                            'date'  => TextFormatModel::formatDate($listInstance['process_date'])
                        ];
                        unset($args['data']['listInstancesOpinion'][$listKey]);
                        $found = true;
                    }
                }

                if (!empty($users)) {
                    $pdf->SetY($pdf->GetY() + 40);
                    if (($pdf->GetY() + 37 + count($users) * 20) > $bottomHeight) {
                        $pdf->AddPage();
                    }
                    $pdf->SetFont('', 'B', 11);
                    $pdf->Cell(0, 15, $unit['label'], 0, 2, 'L', false);
                    $pdf->SetY($pdf->GetY() + 2);

                    $pdf->SetFont('', '', 10);
                    $pdf->Cell($specialWidth * 3, 20, _USERS, 1, 0, 'L', false);
                    $pdf->Cell($specialWidth, 20, _ACTION_DATE, 1, 1, 'L', false);
                    foreach ($users as $keyUser => $user) {
                        $pdf->Cell($specialWidth * 3, 20, $keyUser + 1 . ". {$user['user']}", 1, 0, 'L', false);
                        $pdf->Cell($specialWidth, 20, $user['date'], 1, 1, 'L', false);
                    }
                }
            } elseif ($unit['unit'] == 'notes') {
                $notes = [];
                $found = false;
                $user = UserModel::getByLogin(['select' => ['id'], 'login' => $args['login']]);
                foreach ($args['data']['notes'] as $noteKey => $rawNote) {
                    if ($found && $rawNote['identifier'] != $resource['res_id']) {
                        break;
                    } elseif ($rawNote['identifier'] == $resource['res_id']) {
                        $allowed = false;
                        if ($rawNote['user_id'] == $user['id']) {
                            $allowed = true;
                        } else {
                            $noteEntities = NoteEntityModel::get(['select' => ['item_id'], 'where' => ['note_id = ?'], 'data' => [$rawNote['id']]]);
                            if (!empty($noteEntities)) {
                                foreach ($noteEntities as $noteEntity) {
                                    if (in_array($noteEntity['item_id'], $args['data']['userEntities'])) {
                                        $allowed = true;
                                        break;
                                    }
                                }
                            } else {
                                $allowed = true;
                            }
                        }
                        if ($allowed) {
                            $notes[] = [
                                'user'  => UserModel::getLabelledUserById(['id' => $rawNote['user_id']]),
                                'date'  => TextFormatModel::formatDate($rawNote['creation_date']),
                                'note'  => str_replace('←', '<=', $rawNote['note_text'])
                            ];
                        }
                        unset($args['data']['notes'][$noteKey]);
                        $found = true;
                    }
                }

                if (!empty($notes)) {
                    $pdf->SetY($pdf->GetY() + 40);
                    if (($pdf->GetY() + 80) > $bottomHeight) {
                        $pdf->AddPage();
                    }

                    $pdf->SetFont('', 'B', 11);
                    $pdf->Cell(0, 15, $unit['label'], 0, 2, 'L', false);

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
                }
            } elseif ($unit['unit'] == 'freeField') {
                $pdf->SetY($pdf->GetY() + 40);
                if (($pdf->GetY() + 77) > $bottomHeight) {
                    $pdf->AddPage();
                }
                $pdf->SetFont('', 'B', 11);
                $pdf->Cell(0, 15, $unit['label'], 0, 2, 'L', false);

                $pdf->SetY($pdf->GetY() + 2);
                $pdf->Cell(0, 60, '', 1, 2, 'L', false);
            } elseif ($unit['unit'] == 'trafficRecords') {
                $pdf->SetY($pdf->GetY() + 30);

                $parameter = ParameterModel::getById(['select' => ['param_value_string'], 'id' => 'traffic_record_summary_sheet']);

                $pdf2 = new Fpdi('P', 'pt');
                $pdf2->setPrintHeader(false);
                $pdf2->AddPage();
                $pdf2->writeHTMLCell($widthNoMargins + $dimensions['lm'], 0, $widthNoMargins + $dimensions['lm'], 0, $parameter['param_value_string'], 0, 1, 0, true, 'C', true);
                $height = 10 - ($pdf2->GetY());
                if (($pdf->GetY() + abs($height)) > $bottomHeight) {
                    $pdf->AddPage();
                }
                unset($pdf2);

                $pdf->SetFont('', 'B', 11);
                $pdf->Cell(0, 15, $unit['label'], 0, 2, 'L', false);
                $pdf->SetFont('', '', 9);

                $pdf->writeHTMLCell($widthNoMargins + $dimensions['lm'], 0, $dimensions['lm'] - 2, $pdf->GetY(), $parameter['param_value_string']);
                $pdf->SetY($pdf->GetY() + abs($height));
            } elseif ($unit['unit'] == 'visaWorkflowMaarchParapheur') {
                $loadedXml = CoreConfigModel::getXmlLoaded(['path' => 'modules/visa/xml/remoteSignatoryBooks.xml']);
                if (empty($loadedXml)) {
                    continue;
                }

                $config = ['data' => []];
                foreach ($loadedXml->signatoryBook as $value) {
                    if ($value->id == "maarchParapheur") {
                        $config['data']['url'] = rtrim($value->url, '/');
                        $config['data']['userId'] = (string)$value->userId;
                        $config['data']['password'] = (string)$value->password;
                        break;
                    }
                }

                if (empty($config['data'])) {
                    continue;
                }

                $mainDocument = ResModel::getById([
                    'resId' => $resource['res_id'],
                    'select' => ["external_id->>'signatureBookId' as external_id", 'alt_identifier', 'subject']
                ]);

                $documents = [];
                if (!empty($mainDocument['external_id'])) {
                    $documents[] = [
                        'id'    => $mainDocument['external_id'],
                        'label' => _MAIN_DOCUMENT . ' - ' . $mainDocument['alt_identifier'] . ' - ' . $mainDocument['subject']
                    ];
                }

                $attachments = AttachmentModel::get([
                    'select' => ['res_id', "external_id->>'signatureBookId' as external_id", 'title', 'identifier'],
                    'where'  => ["external_id->>'signatureBookId' IS NOT NULL", "external_id->>'signatureBookId' != ''", 'res_id_master = ?'],
                    'data'   => [$resource['res_id']]
                ]);

                foreach ($attachments as $attachment) {
                    $documents[] = [
                        'id'    => $attachment['external_id'],
                        'label' => _ATTACHMENT . ' - ' . $attachment['identifier'] . ' - ' . $attachment['title']
                    ];
                }

                if (empty($documents)) {
                    continue;
                }

                $pdf->SetY($pdf->GetY() + 40);
                if (($pdf->GetY() + 37) > $bottomHeight) {
                    $pdf->AddPage();
                }
                $pdf->SetFont('', 'B', 11);
                $pdf->Cell(0, 15, $unit['label'], 0, 2, 'L', false);
                $pdf->SetY($pdf->GetY() + 2);

                foreach ($documents as $document) {
                    $workflow = MaarchParapheurController::getDocumentWorkflow(['config' => $config, 'documentId' => $document['id']]);

                    $users = [];
                    if (!empty($workflow)) {
                        foreach ($workflow as $item) {
                            $mode = '';
                            if ($item['mode'] == 'sign') {
                                switch ($item['signatureMode']) {
                                    case 'stamp': $mode = _STAMP; break;
                                    case 'eidas': $mode = _EIDAS; break;
                                    case 'inca_card': $mode = _INCA_CARD; break;
                                    case 'inca_card_eidas': $mode = _INCA_CARD_EIDAS; break;
                                    case 'rgs_2stars_timestamped': $mode = _RGS_2STARS_TIMESTAMPED; break;
                                    case 'rgs_2stars': $mode = _RGS_2STARS; break;
                                    case 'otp_sign_yousign': $mode = _OTP_SIGN_YOUSIGN; break;
                                    case 'otp_visa_yousign': $mode = _OTP_VISA_YOUSIGN; break;
                                }
                            } elseif ($item['mode'] == 'visa') {
                                $mode = _VISA_USER_MIN;
                            }
                            $label = $item['userDisplay'] . ' (' . $mode . ')';
                            if (!empty($item['status'])) {
                                if ($item['status'] == 'VAL') {
                                    $label .= ', ' . _MAARCH_PARAPHEUR_STATUS_VAL;
                                } elseif ($item['status'] == 'REF') {
                                    $label .= ', ' . _MAARCH_PARAPHEUR_STATUS_REF;
                                }
                            }
                            $users[] = ['user' => $label, 'date' => $item['processDate']];
                        }
                    }

                    if (!empty($users)) {
                        $pdf->SetY($pdf->GetY() + 2);
                        if (($pdf->GetY() + 37 + count($users) * 20) > $bottomHeight) {
                            $pdf->AddPage();
                        }
                        $pdf->SetFont('', 'B', 10);
                        $pdf->Cell(0, 15, $document['label'], 0, 2, 'L', false);
                        $pdf->SetY($pdf->GetY() + 2);

                        $pdf->SetFont('', '', 10);
                        $pdf->Cell($specialWidth * 3, 20, _USERS, 1, 0, 'L', false);
                        $pdf->Cell($specialWidth, 20, _ACTION_DATE, 1, 1, 'L', false);
                        foreach ($users as $keyUser => $user) {
                            $pdf->Cell($specialWidth * 3, 20, $keyUser + 1 . ". {$user['user']}", 1, 0, 'L', false);
                            $pdf->Cell($specialWidth, 20, $user['date'], 1, 1, 'L', false);
                        }
                    }
                }
            } elseif ($unit['unit'] == 'workflowHistory') {
                if (!PrivilegeController::hasPrivilege(['privilegeId' => 'view_doc_history', 'userId' => $GLOBALS['id']])
                    && !PrivilegeController::hasPrivilege(['privilegeId' => 'view_full_history', 'userId' => $GLOBALS['id']])) {
                    continue;
                }

                $historyList = HistoryModel::get([
                    'select'  => ['record_id', 'event_date', 'user_id', 'info', 'remote_ip', 'count(1) OVER()'],
                    'where'   => ['table_name in (?)', 'event_type like ?', 'record_id = ?'],
                    'data'    => [['res_letterbox', 'res_view_letterbox'], 'ACTION#%', $resource['res_id']],
                    'orderBy' => ['event_date']
                ]);

                if (!empty($historyList)) {
                    $pdf->SetY($pdf->GetY() + 40);
                    if (($pdf->GetY() + 37 + count($historyList) * 20) > $bottomHeight) {
                        $pdf->AddPage();
                    }
                    $pdf->SetFont('', 'B', 10);
                    $pdf->Cell(0, 15, $unit['label'], 0, 2, 'L', false);
                    $pdf->SetY($pdf->GetY() + 2);

                    $pdf->SetFont('', '', 10);
                    foreach ($historyList as $history) {
                        $date = new \DateTime($history['event_date']);
                        $date = $date->format('d/m/Y H:i:s');
                        $label = $date . " - " . UserModel::getLabelledUserById(['id' => $history['user_id']]) . "\n" . $history['info'];
                        $pdf->MultiCell(0, 40, $label, 1, 'L', false);
                    }
                }
            }
        }
    }

    public static function prepareData(array $args)
    {
        $units = $args['units'];
        $tmpIds = $args['resourcesIds'];

        $data = [];
        foreach ($units as $unit) {
            if ($unit['unit'] == 'notes') {
                $data['notes'] = NoteModel::get([
                    'select'   => ['id', 'note_text', 'user_id', 'creation_date', 'identifier'],
                    'where'    => ['identifier in (?)'],
                    'data'     => [$tmpIds],
                    'order_by' => ['identifier']]);

                $userEntities = EntityModel::getByUserId(['userId' => $GLOBALS['id'], 'select' => ['entity_id']]);
                $data['userEntities'] = [];
                foreach ($userEntities as $userEntity) {
                    $data['userEntities'][] = $userEntity['entity_id'];
                }
            } elseif ($unit['unit'] == 'opinionWorkflow') {
                $data['listInstancesOpinion'] = ListInstanceModel::get([
                    'select'    => ['item_id', 'process_date', 'res_id', 'delegate'],
                    'where'     => ['difflist_type = ?', 'res_id in (?)'],
                    'data'      => ['AVIS_CIRCUIT', $tmpIds],
                    'orderBy'   => ['listinstance_id']
                ]);
            } elseif ($unit['unit'] == 'visaWorkflow') {
                $data['listInstancesVisa'] = ListInstanceModel::get([
                    'select'    => ['item_id', 'requested_signature', 'process_date', 'res_id', 'delegate', 'item_mode', 'signatory', 'process_comment'],
                    'where'     => ['difflist_type = ?', 'res_id in (?)'],
                    'data'      => ['VISA_CIRCUIT', $tmpIds],
                    'orderBy'   => ['listinstance_id']
                ]);
            } elseif ($unit['unit'] == 'diffusionList') {
                $data['listInstances'] = ListInstanceModel::get([
                    'select' => ['item_id', 'item_type', 'item_mode', 'res_id'],
                    'where'  => ['difflist_type = ?', 'res_id in (?)'],
                    'data'   => ['entity_id', $tmpIds],
                    'orderBy' => ['listinstance_id']
                ]);
            }
        }

        return $data;
    }
}
