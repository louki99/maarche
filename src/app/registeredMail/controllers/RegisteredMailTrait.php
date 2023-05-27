<?php

/**
* Copyright Maarch since 2008 under licence GPLv3.
* See LICENCE.txt file at the root folder for more details.
* This file is part of Maarch software.

* @brief   AcknowledgementReceiptTrait
* @author  dev <dev@maarch.org>
* @ingroup core
*/

namespace RegisteredMail\controllers;

use Contact\models\ContactModel;
use Parameter\models\ParameterModel;
use RegisteredMail\models\IssuingSiteModel;
use RegisteredMail\models\RegisteredMailModel;
use RegisteredMail\models\RegisteredNumberRangeModel;
use Resource\models\ResModel;
use setasign\Fpdi\Tcpdf\Fpdi;
use SrcCore\models\CoreConfigModel;
use SrcCore\models\ValidatorModel;

trait RegisteredMailTrait
{
    public static function saveAndPrintRegisteredMail(array $args)
    {
        ValidatorModel::notEmpty($args, ['resId', 'data']);
        ValidatorModel::intVal($args, ['resId']);
        ValidatorModel::arrayType($args, ['data']);

        $resource = ResModel::getById(['select' => ['departure_date', 'category_id'], 'resId' => $args['resId']]);
        if ($resource['category_id'] != 'registeredMail') {
            return ['errors' => ['This resource is not a registered mail'], 'lang' => 'registeredMailNotFound'];
        } elseif (empty($resource['departure_date'])) {
            return ['errors' => ['Departure date is empty']];
        } elseif (!in_array($args['data']['type'], ['2D', '2C', 'RW'])) {
            return ['errors' => ['Type is not correct']];
        } elseif (!in_array($args['data']['warranty'], ['R1', 'R2', 'R3'])) {
            return ['errors' => ['warranty is not correct']];
        } elseif ($args['data']['type'] == 'RW' && $args['data']['warranty'] == 'R3') {
            return ['errors' => ['R3 warranty is not allowed for type RW']];
        } elseif (empty($args['data']['recipient']) || empty($args['data']['issuingSiteId'])) {
            return ['errors' => ['recipient or issuingSiteId is missing to print registered mail']];
        } elseif (empty($args['data']['recipient'][0]['id'])) {
            return ['errors' => ['recipient is empty']];
        }

        $args['data']['recipient'] = ContactModel::getById(
            [
            'select' => ['company', 'lastname', 'firstname', 'address_town as "addressTown"', 'address_number as "addressNumber"', 'address_street as "addressStreet"', 'address_country as "addressCountry"', 'address_postcode as "addressPostcode"', 'address_additional1 as addressAdditional1', 'address_additional2 as addressAdditional2', 'department'],
            'id' => $args['data']['recipient'][0]['id']]
        );
        
        if (empty($args['data']['recipient']['lastname']) && !empty($args['data']['recipient']['department'])) {
            $args['data']['recipient']['lastname'] = $args['data']['recipient']['department'];
        }
        unset($args['data']['recipient']['department']);

        if ((empty($args['data']['recipient']['company']) && (empty($args['data']['recipient']['lastname']) || empty($args['data']['recipient']['firstname']))) || empty($args['data']['recipient']['addressStreet']) || empty($args['data']['recipient']['addressPostcode']) || empty($args['data']['recipient']['addressTown']) || empty($args['data']['recipient']['addressCountry'])) {
            return ['errors' => ['company and firstname/lastname, or addressStreet, addressPostcode, addressTown or addressCountry is empty in Recipient'], 'lang' => 'argumentRegisteredMailRecipientEmpty'];
        }

        $issuingSite = IssuingSiteModel::getById([
            'id'        => $args['data']['issuingSiteId'],
            'select'    => ['label', 'address_number', 'address_street', 'address_additional1', 'address_additional2', 'address_postcode', 'address_town', 'address_country']
        ]);
        if (empty($issuingSite)) {
            return ['errors' => ['Issuing site does not exist'], 'lang' => 'argumentRegisteredMailIssuingSiteEmpty'];
        }

        $range = RegisteredNumberRangeModel::get([
            'select'    => ['id', 'range_end', 'current_number'],
            'where'     => ['type = ?', 'status = ?'],
            'data'      => [$args['data']['type'], 'OK']
        ]);
        if (empty($range)) {
            return ['errors' => ['No range found'], 'lang' => 'NoRangeAvailable'];
        }

        if ($range[0]['current_number'] + 1 > $range[0]['range_end']) {
            $status = 'END';
            $nextNumber = $range[0]['current_number'];
        } else {
            $status = 'OK';
            $nextNumber = $range[0]['current_number'] + 1;
        }
        RegisteredNumberRangeModel::update([
            'set'   => ['current_number' => $nextNumber, 'status' => $status],
            'where' => ['id = ?'],
            'data'  => [$range[0]['id']]
        ]);

        $date      = new \DateTime($resource['departure_date']);
        $date      = $date->format('d/m/Y');
        $reference = "{$date} - {$args['data']['reference']}";

        RegisteredMailModel::create([
            'res_id'        => $args['resId'],
            'type'          => $args['data']['type'],
            'issuing_site'  => $args['data']['issuingSiteId'],
            'warranty'      => $args['data']['warranty'],
            'letter'        => empty($args['data']['letter']) ? 'false' : 'true',
            'recipient'     => json_encode($args['data']['recipient']),
            'number'        => $range[0]['current_number'],
            'reference'     => $reference,
            'generated'     => empty($args['data']['generated']) ? 'false' : 'true',
        ]);

        $registeredMailNumber = RegisteredMailController::getRegisteredMailNumber(['type' => $args['data']['type'], 'rawNumber' => $range[0]['current_number'], 'countryCode' => 'FR']);
        ResModel::update([
            'set'   => ['alt_identifier' => $registeredMailNumber],
            'where' => ['res_id = ?'],
            'data'  => [$args['resId']]
        ]);

        RegisteredMailController::generateRegisteredMailPDf([
            'registeredMailNumber' => $registeredMailNumber,
            'type'                 => $args['data']['type'],
            'warranty'             => $args['data']['warranty'],
            'letter'               => $args['data']['letter'],
            'reference'            => $reference,
            'recipient'            => $args['data']['recipient'],
            'issuingSite'          => $issuingSite,
            'resId'                => $args['resId'],
            'savePdf'              => true
        ]);

        if (empty($args['data']['generated'])) {
            return true;
        } else {
            $registeredMailPDF = RegisteredMailController::generateRegisteredMailPDf([
                'registeredMailNumber' => $registeredMailNumber,
                'type'                 => $args['data']['type'],
                'warranty'             => $args['data']['warranty'],
                'letter'               => $args['data']['letter'],
                'reference'            => $reference,
                'recipient'            => $args['data']['recipient'],
                'issuingSite'          => $issuingSite,
                'resId'                => $args['resId'],
                'savePdf'              => false
            ]);
            return ['data' => ['fileContent' => base64_encode($registeredMailPDF['fileContent']), 'registeredMailNumber' => $registeredMailNumber]];
        }
    }

    public static function printRegisteredMail(array $args)
    {
        ValidatorModel::notEmpty($args, ['resId']);
        ValidatorModel::intVal($args, ['resId']);

        static $data;

        $registeredMail = RegisteredMailModel::getByResId(['select' => ['issuing_site', 'type', 'number', 'warranty', 'letter', 'recipient', 'reference'], 'resId' => $args['resId']]);
        $recipient = json_decode($registeredMail['recipient'], true);
        if (empty($registeredMail)) {
            return ['errors' => ['No registered mail for this resource'], 'lang' => 'registeredMailNotFound'];
        } elseif (empty($recipient) || empty($registeredMail['issuing_site']) || empty($registeredMail['type']) || empty($registeredMail['number']) || empty($registeredMail['warranty'])) {
            return ['errors' => ['recipient, issuing_site, type, number or warranty is missing to print registered mail']];
        } elseif ((empty($recipient['company']) && (empty($recipient['lastname']) || empty($recipient['firstname']))) || empty($recipient['addressStreet']) || empty($recipient['addressPostcode']) || empty($recipient['addressTown']) || empty($recipient['addressCountry'])) {
            return ['errors' => ['company and firstname/lastname, or addressStreet, addressPostcode, addressTown or addressCountry is empty in Recipient'], 'lang' => 'argumentRegisteredMailRecipientEmpty'];
        }

        $issuingSite = IssuingSiteModel::getById([
            'id'        => $registeredMail['issuing_site'],
            'select'    => ['label', 'address_number', 'address_street', 'address_additional1', 'address_additional2', 'address_postcode', 'address_town', 'address_country']
        ]);

        $resource = ResModel::getById(['select' => ['alt_identifier'], 'resId' => $args['resId']]);
        $registeredMailPDF = RegisteredMailController::generateRegisteredMailPDf([
            'registeredMailNumber' => $resource['alt_identifier'],
            'type'                 => $registeredMail['type'],
            'warranty'             => $registeredMail['warranty'],
            'letter'               => $registeredMail['letter'],
            'reference'            => $registeredMail['reference'],
            'recipient'            => $recipient,
            'issuingSite'          => $issuingSite,
            'resId'                => $args['resId'],
            'savePdf'              => false
        ]);

        if ($data === null) {
            $data = [
                '2D' => null,
                '2C' => null,
                'RW' => null
            ];
        }

        if (empty($data[$registeredMail['type']])) {
            $data[$registeredMail['type']] = base64_encode($registeredMailPDF['fileContent']);
        } else {
            $concatPdf = new Fpdi('P', 'pt');
            $concatPdf->setPrintHeader(false);
            $concatPdf->setPrintFooter(false);
            $tmpPath = CoreConfigModel::getTmpPath();

            $firstFile = $tmpPath . 'registeredMail_first_file' . rand() . '.pdf';
            file_put_contents($firstFile, base64_decode($data[$registeredMail['type']]));
            $pageCount = $concatPdf->setSourceFile($firstFile);
            for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                $pageId = $concatPdf->ImportPage($pageNo);
                $s = $concatPdf->getTemplatesize($pageId);
                $concatPdf->AddPage($s['orientation'], $s);
                $concatPdf->useImportedPage($pageId);
            }

            $secondFile = $tmpPath . 'registeredMail_second_file' . rand() . '.pdf';
            file_put_contents($secondFile, $registeredMailPDF['fileContent']);
            $concatPdf->setSourceFile($secondFile);
            $pageId = $concatPdf->ImportPage(1);
            $s = $concatPdf->getTemplatesize($pageId);
            $concatPdf->AddPage($s['orientation'], $s);
            $concatPdf->useImportedPage($pageId);

            $fileContent = $concatPdf->Output('', 'S');

            $data[$registeredMail['type']] = base64_encode($fileContent);
            unlink($firstFile);
            unlink($secondFile);
        }

        RegisteredMailModel::update([
            'set'   => ['generated' => 'true'],
            'where' => ['res_id = ?'],
            'data'  => [$args['resId']]
        ]);

        return ['data' => $data];
    }

    public static function printDepositList(array $args)
    {
        ValidatorModel::notEmpty($args, ['resId']);
        ValidatorModel::intVal($args, ['resId']);

        static $processedResources;
        static $filesByType;
        static $currentDepositId;
        static $registeredMailsIdsByType;
        static $processedTypesSites;

        if ($filesByType === null) {
            $filesByType = [
                '2D' => [],
                '2C' => [],
                'RW' => []
            ];
        }
        if ($registeredMailsIdsByType === null) {
            $registeredMailsIdsByType = [];
        }
        if ($processedResources === null) {
            $processedResources = [];
        }
        if ($processedTypesSites === null) {
            $processedTypesSites = [];
        }

        if (in_array($args['resId'], $processedResources)) {
            return [];
        }

        $registeredMail = RegisteredMailModel::getWithResources([
            'select' => ['issuing_site', 'type', 'number', 'warranty', 'recipient', 'generated', 'departure_date', 'deposit_id'],
            'where'  => ['res_letterbox.res_id = ?'],
            'data'   => [$args['resId']]
        ]);
        if (empty($registeredMail[0])) {
            return ['errors' => ['No registered mail for this resource']];
        }
        $registeredMail = $registeredMail[0];

        if (!$registeredMail['generated']) {
            return ['errors' => ['Registered mail not generated for this resource']];
        }

        $uniqueType = $registeredMail['type'] . '_' . $registeredMail['issuing_site'] . '_' . $registeredMail['warranty'] . '_' . $registeredMail['departure_date'];

        $site = IssuingSiteModel::getById(['id' => $registeredMail['issuing_site']]);

        $range = RegisteredNumberRangeModel::get([
            'where' => ['type = ?', 'range_start <= ?', 'range_end >= ?'],
            'data'  => [$registeredMail['type'], $registeredMail['number'], $registeredMail['number']]
        ]);
        if (empty($range[0])) {
            return ['errors' => ['No range found']];
        }
        $range = $range[0];

        if (empty($registeredMail['deposit_id'])) {
            $registeredMails = RegisteredMailModel::getWithResources([
                'select'  => ['number', 'warranty', 'reference', 'recipient', 'res_letterbox.res_id', 'alt_identifier'],
                'where'   => ['type = ?', 'issuing_site = ?', 'departure_date = ?', 'warranty = ?', 'generated = ?'],
                'data'    => [$registeredMail['type'], $registeredMail['issuing_site'], $registeredMail['departure_date'], $registeredMail['warranty'], true],
                'orderBy' => ['number']
            ]);

            if (empty($currentDepositId) || !in_array($uniqueType, $processedTypesSites)) {
                $lastDepositId = ParameterModel::getById(['id' => 'last_deposit_id', 'select' => ['param_value_int']]);
                $currentDepositId = $lastDepositId['param_value_int'] + 1;
                ParameterModel::update(['id' => 'last_deposit_id', 'param_value_int' => $currentDepositId]);
            }
        } else {
            $registeredMails = RegisteredMailModel::getWithResources([
                'select'  => ['number', 'warranty', 'reference', 'recipient', 'res_letterbox.res_id', 'alt_identifier'],
                'where'   => ['deposit_id = ?'],
                'data'    => [$registeredMail['deposit_id']],
                'orderBy' => ['number']
            ]);
        }

        $resultPDF = RegisteredMailController::getDepositListPdf([
            'site'            => [
                'label'              => $site['label'],
                'postOfficeLabel'    => $site['post_office_label'],
                'accountNumber'      => $site['account_number'],
                'addressNumber'      => $site['address_number'],
                'addressStreet'      => $site['address_street'],
                'addressAdditional1' => $site['address_additional1'],
                'addressAdditional2' => $site['address_additional2'],
                'addressPostcode'    => $site['address_postcode'],
                'addressTown'        => $site['address_town'],
                'addressCountry'     => $site['address_country'],
            ],
            'type'            => $registeredMail['type'],
            'trackingNumber'  => $range['tracking_account_number'],
            'departureDate'   => $registeredMail['departure_date'],
            'registeredMails' => $registeredMails
        ]);

        $resIds = array_column($registeredMails, 'res_id');
        $processedResources = array_merge($processedResources, $resIds);
        $registeredMailsIdsByType[$uniqueType] = $resIds;

        $filesByType[$registeredMail['type']][] = base64_encode($resultPDF['fileContent']);

        if (!empty($currentDepositId)) {
            foreach ($registeredMailsIdsByType as $type => $ids) {
                if (!empty($ids) && !in_array($type, $processedTypesSites)) {
                    RegisteredMailModel::update([
                        'set'   => ['deposit_id' => $currentDepositId],
                        'where' => ['res_id in (?)'],
                        'data'  => [$ids]
                    ]);
                }
            }
        }
        $processedTypesSites[] = $uniqueType;

        $finalFile = null;
        foreach ($filesByType as $type => $files) {
            if (empty($files)) {
                continue;
            }
            foreach ($files as $file) {
                if (empty($finalFile)) {
                    $finalFile = $file;
                    continue;
                }

                $concatPdf = new Fpdi('P', 'pt');
                $concatPdf->setPrintHeader(false);
                $concatPdf->setPrintFooter(false);
                $tmpPath = CoreConfigModel::getTmpPath();

                $firstFile = $tmpPath . 'depositList_first_file' . rand() . '.pdf';
                file_put_contents($firstFile, base64_decode($finalFile));
                $pageCount = $concatPdf->setSourceFile($firstFile);
                for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                    $pageId = $concatPdf->ImportPage($pageNo);
                    $s = $concatPdf->getTemplatesize($pageId);
                    $concatPdf->AddPage($s['orientation'], $s);
                    $concatPdf->useImportedPage($pageId);
                }

                $secondFile = $tmpPath . 'depositList_second_file' . rand() . '.pdf';
                file_put_contents($secondFile, base64_decode($file));
                $concatPdf->setSourceFile($secondFile);
                $pageId = $concatPdf->ImportPage(1);
                $s = $concatPdf->getTemplatesize($pageId);
                $concatPdf->AddPage($s['orientation'], $s);
                $concatPdf->useImportedPage($pageId);

                $fileContent = $concatPdf->Output('', 'S');

                $finalFile = base64_encode($fileContent);
                unlink($firstFile);
                unlink($secondFile);
            }
        }

        return ['data' => ['encodedFile' => $finalFile]];
    }
}
