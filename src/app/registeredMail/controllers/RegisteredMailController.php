<?php

/**
* Copyright Maarch since 2008 under licence GPLv3.
* See LICENCE.txt file at the root folder for more details.
* This file is part of Maarch software.
*/

/**
 * @brief Registered Mail Controller
 * @author dev@maarch.org
 */

namespace RegisteredMail\controllers;

use Com\Tecnick\Barcode\Barcode;
use Contact\controllers\ContactCivilityController;
use Contact\controllers\ContactController;
use Convert\models\AdrModel;
use Docserver\controllers\DocserverController;
use Group\controllers\PrivilegeController;
use History\controllers\HistoryController;
use IndexingModel\models\IndexingModelFieldModel;
use IndexingModel\models\IndexingModelModel;
use Parameter\models\ParameterModel;
use RegisteredMail\models\IssuingSiteModel;
use RegisteredMail\models\RegisteredMailModel;
use RegisteredMail\models\RegisteredNumberRangeModel;
use Resource\controllers\ResController;
use Resource\controllers\StoreController;
use Resource\models\ResModel;
use Respect\Validation\Validator;
use setasign\Fpdi\Tcpdf\Fpdi;
use Slim\Psr7\Request;
use SrcCore\http\Response;
use SrcCore\models\DatabaseModel;
use SrcCore\models\ValidatorModel;
use User\models\UserModel;

class RegisteredMailController
{
    public function update(Request $request, Response $response, array $args)
    {
        if (!ResController::hasRightByResId(['resId' => [$args['resId']], 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(400)->withJson(['errors' => 'Resource out of perimeter']);
        }

        $registeredMail = RegisteredMailModel::getByResId(['select' => ['issuing_site', 'type', 'deposit_id'], 'resId' => $args['resId']]);
        if (empty($registeredMail)) {
            return $response->withStatus(400)->withJson(['errors' => 'No registered mail for this resource']);
        } elseif (!empty($registeredMail['deposit_id'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Registered mail can not be modified (deposit list already generated)']);
        }

        $body = $request->getParsedBody();

        if (!Validator::stringType()->notEmpty()->validate($body['type'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body type is empty or not a string']);
        } elseif (!Validator::stringType()->notEmpty()->validate($body['warranty'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body warranty is empty or not a string']);
        } elseif (!in_array($body['type'], ['2D', '2C', 'RW'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body type is not correct']);
        } elseif (!in_array($body['warranty'], ['R1', 'R2', 'R3'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body warranty is not correct']);
        } elseif ($body['type'] == 'RW' && $body['warranty'] == 'R3') {
            return $response->withStatus(400)->withJson(['errors' => 'Body warranty R3 is not allowed for type RW']);
        } elseif (!Validator::notEmpty()->validate($body['recipient'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body recipient is empty']);
        }

        $issuingSite = IssuingSiteModel::getById([
            'id'        => $registeredMail['issuing_site'],
            'select'    => ['label', 'address_number', 'address_street', 'address_additional1', 'address_additional2', 'address_postcode', 'address_town', 'address_country']
        ]);
        if (empty($issuingSite)) {
            return $response->withStatus(400)->withJson(['errors' => 'Issuing site does not exist']);
        }

        $resource = ResModel::getById(['select' => ['departure_date', 'alt_identifier'], 'resId' => $args['resId']]);
        $date     = new \DateTime($resource['departure_date']);
        $date     = $date->format('d/m/Y');

        $refPos = strpos($body['reference'], '-');
        if ($refPos !== false) {
            $body['reference'] = substr_replace($body['reference'], "{$date} ", 0, $refPos);
        } else {
            $body['reference'] = "{$date} - {$body['reference']}";
        }
        $set = [
            'type'      => $body['type'],
            'warranty'  => $body['warranty'],
            'reference' => $body['reference'],
            'letter'    => empty($body['letter']) ? 'false' : 'true',
            'recipient' => json_encode($body['recipient']),
        ];

        if ($registeredMail['type'] != $body['type']) {
            $range = RegisteredNumberRangeModel::get([
                'select' => ['id', 'range_end', 'current_number'],
                'where'  => ['type = ?', 'status = ?'],
                'data'   => [$body['type'], 'OK']
            ]);
            if (empty($range)) {
                return $response->withStatus(400)->withJson(['errors' => 'No range found']);
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

            $set['number'] = $range[0]['current_number'];

            $resource['alt_identifier'] = RegisteredMailController::getRegisteredMailNumber(['type' => $body['type'], 'rawNumber' => $range[0]['current_number'], 'countryCode' => 'FR']);
            ResModel::update([
                'set'   => ['alt_identifier' => $resource['alt_identifier']],
                'where' => ['res_id = ?'],
                'data'  => [$args['resId']]
            ]);
        }

        RegisteredMailModel::update([
            'set'   => $set,
            'where' => ['res_id = ?'],
            'data'  => [$args['resId']]
        ]);

        RegisteredMailController::generateRegisteredMailPDF([
            'registeredMailNumber' => $resource['alt_identifier'],
            'type'                 => $body['type'],
            'warranty'             => $body['warranty'],
            'letter'               => $body['letter'],
            'reference'            => $body['reference'],
            'recipient'            => $body['recipient'],
            'issuingSite'          => $issuingSite,
            'resId'                => $args['resId'],
            'savePdf'              => true
        ]);

        return $response->withStatus(204);
    }

    public function getCountries(Request $request, Response $response)
    {
        $countries = [];
        if (($handle = fopen("referential/liste-197-etats.csv", "r")) !== false) {
            fgetcsv($handle, 0, ';');
            while (($data = fgetcsv($handle, 0, ';')) !== false) {
                $countries[] = utf8_encode($data[0]);
            }
            fclose($handle);
        }
        return $response->withJson(['countries' => $countries]);
    }

    public function receiveAcknowledgement(Request $request, Response $response)
    {
        if (!PrivilegeController::hasPrivilege(['privilegeId' => 'registered_mail_receive_ar', 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }

        $body = $request->getParsedBody();

        if (!Validator::stringType()->notEmpty()->validate($body['type']) && !in_array($body['type'], ['distributed', 'notDistributed'])) {
            return $response->withStatus(400)->withJson(['errors' => "Body type is empty or is not 'distributed' or 'notDistributed'"]);
        } elseif (!Validator::stringType()->notEmpty()->validate($body['number'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body number is empty or not a string']);
        } elseif (!preg_match("/((2C|2D)( ?[0-9]){11})|(RW( ?[0-9]){9} ?[A-Z]{2})/", $body['number'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body number is not valid']);
        }

        $number = RegisteredMailController::getFormattedRegisteredNumber(['number' => $body['number']]);
        $registeredMail = RegisteredMailModel::getWithResources([
            'select' => ['id', 'registered_mail_resources.res_id', 'received_date', 'deposit_id', 'status'],
            'where'  => ['alt_identifier = ?'],
            'data'   => [$number]
        ]);
        if (empty($registeredMail)) {
            return $response->withStatus(400)->withJson(['errors' => 'Registered mail number not found', 'lang' => 'registeredMailNotFound']);
        }
        $registeredMail = $registeredMail[0];
        if (empty($registeredMail['deposit_id'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Registered mail is not in a deposit list', 'lang' => 'registeredMailNotInDepositList']);
        }

        $statusDistributed = ParameterModel::getById(['select' => ['param_value_string'], 'id' => 'registeredMailDistributedStatus']);
        $statusDistributed = $statusDistributed['param_value_string'];

        $statusNotDistributed = ParameterModel::getById(['select' => ['param_value_string'], 'id' => 'registeredMailNotDistributedStatus']);
        $statusNotDistributed = $statusNotDistributed['param_value_string'];

        if (!empty($registeredMail['received_date'])) {
            if ($registeredMail['status'] == $statusNotDistributed && $body['type'] == 'distributed') {
                return $response->withJson(['previousStatus' => $registeredMail['status'], 'canRescan' => true]);
            } elseif ($registeredMail['status'] == $statusDistributed && $body['type'] == 'notDistributed') {
                return $response->withJson(['previousStatus' => $registeredMail['status'], 'canRescan' => true]);
            }
            return $response->withStatus(400)->withJson(['errors' => 'Registered mail was already received', 'lang' => 'arAlreadyReceived']);
        }

        if ($body['type'] == 'distributed') {
            $set = ['received_date' => 'CURRENT_TIMESTAMP'];
            $status = $statusDistributed;
            $info = _REGISTERED_MAIL_DISTRIBUTED;
        } else {
            if (!Validator::stringType()->notEmpty()->validate($body['returnReason'])) {
                return $response->withStatus(400)->withJson(['errors' => 'Body returnReason is empty or not a string']);
            } elseif (!Validator::dateTime()->notEmpty()->validate($body['receivedDate'])) {
                return $response->withStatus(400)->withJson(['errors' => 'Body receivedDate is empty or not a date']);
            }
            $receivedDate = new \DateTime($body['receivedDate']);
            $today = new \DateTime();
            $today->setTime(00, 00, 00);
            if ($receivedDate > $today) {
                return ['errors' => "Body receivedDate is not a valid date"];
            }

            $set = ['received_date' => $body['receivedDate'], 'return_reason' => $body['returnReason']];
            $status = $statusNotDistributed;
            $info = _REGISTERED_MAIL_NOT_DISTRIBUTED;
        }

        RegisteredMailModel::update([
            'set'   => $set,
            'where' => ['id = ?'],
            'data'  => [$registeredMail['id']]
        ]);
        if (!empty($status)) {
            ResModel::update([
                'set'   => ['status' => $status],
                'where' => ['res_id = ?'],
                'data'  => [$registeredMail['res_id']]
            ]);
        }

        HistoryController::add([
            'tableName' => 'res_letterbox',
            'recordId'  => $registeredMail['res_id'],
            'eventType' => 'ACTION#0',
            'info'      => $info,
            'moduleId'  => 'resource',
            'eventId'   => 'registeredMailReceived'
        ]);

        return $response->withJson(['previousStatus' => $registeredMail['status'], 'canRescan' => false]);
    }

    public function rollbackAcknowledgementReception(Request $request, Response $response)
    {
        if (!PrivilegeController::hasPrivilege(['privilegeId' => 'registered_mail_receive_ar', 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }

        $body = $request->getParsedBody();

        if (!Validator::stringType()->notEmpty()->validate($body['type']) && !in_array($body['type'], ['distributed', 'notDistributed'])) {
            return $response->withStatus(400)->withJson(['errors' => "Body type is empty or is not 'distributed' or 'notDistributed'"]);
        } elseif (!Validator::stringType()->notEmpty()->validate($body['number'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body number is empty or not a string']);
        } elseif (!preg_match("/((2C|2D)( ?[0-9]){11})|(RW( ?[0-9]){9} ?[A-Z]{2})/", $body['number'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body number is not valid']);
        }

        $body['number'] = RegisteredMailController::getFormattedRegisteredNumber(['number' => $body['number']]);
        $registeredMail = RegisteredMailModel::getWithResources([
            'select' => ['id', 'registered_mail_resources.res_id', 'received_date', 'deposit_id'],
            'where'  => ['alt_identifier = ?'],
            'data'   => [$body['number']]
        ]);
        if (empty($registeredMail)) {
            return $response->withStatus(400)->withJson(['errors' => 'Registered mail number not found', 'lang' => 'registeredMailNotFound']);
        }
        $registeredMail = $registeredMail[0];
        if (empty($registeredMail['received_date'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Registered mail is not received', 'lang' => 'arAlreadyReceived']);
        }

        RegisteredMailModel::update([
            'set'   => ['received_date' => null, 'return_reason' => null],
            'where' => ['id = ?'],
            'data'  => [$registeredMail['id']]
        ]);
        if (!empty($body['status'])) {
            ResModel::update([
                'set'   => ['status' => $body['status']],
                'where' => ['res_id = ?'],
                'data'  => [$registeredMail['res_id']]
            ]);
        }

        return $response->withStatus(204);
    }

    public function setImport(Request $request, Response $response)
    {
        if (!PrivilegeController::hasPrivilege(['privilegeId' => 'registered_mail_mass_import', 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }

        $body = $request->getParsedBody();
        if (!Validator::arrayType()->validate($body['registeredMails'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body registeredMails is empty or not an array']);
        }

        $warnings = [];
        $errors = [];
        foreach ($body['registeredMails'] as $key => $registeredMail) {
            if (!Validator::notEmpty()->intVal()->validate($registeredMail['modelId'])) {
                $errors[] = ['error' => "Argument modelId is empty or not an integer for registered mail {$key}", 'index' => $key, 'lang' => 'argumentModelIdEmpty'];
                continue;
            } elseif (!Validator::dateTime()->notEmpty()->validate($registeredMail['departureDate'])) {
                $errors[] = ['error' => "Argument departureDate is empty or not a date for registered mail {$key}", 'index' => $key, 'lang' => 'argumentDepartureDateEmpty'];
                continue;
            } elseif (!Validator::stringType()->notEmpty()->validate($registeredMail['registeredMail_type']) || !in_array($registeredMail['registeredMail_type'], ['2D', '2C', 'RW'])) {
                $errors[] = ['error' => "Argument registeredMail_type is empty or not valid for registered mail {$key}", 'index' => $key, 'lang' => 'argumentRegisteredMailTypeEmpty'];
                continue;
            } elseif (!Validator::stringType()->notEmpty()->validate($registeredMail['registeredMail_warranty']) || !in_array($registeredMail['registeredMail_warranty'], ['R1', 'R2', 'R3'])) {
                $errors[] = ['error' => "Argument registeredMail_warranty is empty or not valid for registered mail {$key}", 'index' => $key, 'lang' => 'argumentRegisteredMailWarrantyEmpty'];
                continue;
            } elseif ($registeredMail['registeredMail_type'] == 'RW' && $registeredMail['registeredMail_warranty'] == 'R3') {
                $errors[] = ['error' => "Argument registeredMail_warranty is not allowed for type RW for registered mail {$key}", 'index' => $key, 'lang' => 'argumentRegisteredMailWarrantyNotAllowed'];
                continue;
            } elseif (!Validator::notEmpty()->intVal()->validate($registeredMail['registeredMail_issuingSite'])) {
                $errors[] = ['error' => "Argument registeredMail_issuingSite is empty or not an integer for registered mail {$key}", 'index' => $key, 'lang' => 'argumentRegisteredMailIssuingSiteEmpty'];
                continue;
            } elseif ((empty($registeredMail['company']) && (empty($registeredMail['lastname']) || empty($registeredMail['firstname']))) || empty($registeredMail['addressStreet']) || empty($registeredMail['addressPostcode']) || empty($registeredMail['addressTown'])) {
                $errors[] = ['error' => "Argument company and firstname/lastname, or addressStreet, addressPostcode, addressTown is empty for registered mail {$key}", 'index' => $key, 'lang' => 'argumentRegisteredMailRecipientEmpty'];
                continue;
            }

            $indexingModel = IndexingModelModel::getById(['id' => $registeredMail['modelId'], 'select' => ['category']]);
            if (empty($indexingModel)) {
                $errors[] = ['error' => "Argument modelId does not exist for registered mail {$key}", 'index' => $key, 'lang' => 'argumentModelIdEmpty'];
                continue;
            } elseif ($indexingModel['category'] != 'registeredMail') {
                $errors[] = ['error' => "Argument modelId category is not valid for registered mail {$key}", 'index' => $key, 'lang' => 'argumentCategoryNotValid'];
                continue;
            }
            $indexingModelField = IndexingModelFieldModel::get([
                'select'    => ['default_value'],
                'where'     => ['model_id = ?', 'identifier in (?)'],
                'data'      => [$registeredMail['modelId'], ['doctype', 'subject']],
                'orderBy'   => ['identifier']
            ]);
            if (empty($indexingModelField[0]['default_value'])) {
                $errors[] = ['error' => "Argument modelId doctype default value is not valid for registered mail {$key}", 'index' => $key, 'lang' => 'argumentDoctypeEmpty'];
                continue;
            }

            $issuingSite = IssuingSiteModel::getById([
                'id'        => $registeredMail['registeredMail_issuingSite'],
                'select'    => ['label', 'address_number', 'address_street', 'address_additional1', 'address_additional2', 'address_postcode', 'address_town', 'address_country']
            ]);
            if (empty($issuingSite)) {
                $errors[] = ['error' => "Argument issuingSite does not exist for registered mail {$key}", 'index' => $key, 'lang' => 'argumentRegisteredMailIssuingSiteEmpty'];
                continue;
            }

            $range = RegisteredNumberRangeModel::get([
                'select'    => ['id', 'range_end', 'current_number'],
                'where'     => ['type = ?', 'status = ?'],
                'data'      => [$registeredMail['registeredMail_type'], 'OK']
            ]);
            if (empty($range)) {
                $errors[] = ['error' => "No range available for registered mail {$key}", 'index' => $key, 'lang' => 'NoRangeAvailable'];
                continue;
            }
            $status = ParameterModel::getById(['select' => ['param_value_string'], 'id' => 'registeredMailImportedStatus']);
            if (empty($status['param_value_string'])) {
                $errors[] = ['error' => "No status found in parameters", 'index' => $key, 'lang' => 'NoRegisteredMailImportedStatus'];
                continue;
            }

            $resId = DatabaseModel::getNextSequenceValue(['sequenceId' => 'res_id_mlb_seq']);
            $registeredMailNumber = RegisteredMailController::getRegisteredMailNumber(['type' => $registeredMail['registeredMail_type'], 'rawNumber' => $range[0]['current_number'], 'countryCode' => 'FR']);
            $data = StoreController::prepareResourceStorage([
                'resId'         => $resId,
                'subject'       => json_decode($indexingModelField[1]['default_value']),
                'modelId'       => $registeredMail['modelId'],
                'doctype'       => $indexingModelField[0]['default_value'],
                'status'        => $status['param_value_string'],
                'departureDate' => $registeredMail['departureDate']
            ]);
            $data['alt_identifier'] = $registeredMailNumber;
            ResModel::create($data);

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

            $date      = new \DateTime($registeredMail['departureDate']);
            $date      = $date->format('d/m/Y');
            $reference = "{$date} - {$registeredMail['registeredMail_reference']}";

            $recipient = [
                'company'               => $registeredMail['company'],
                'civility'              => $registeredMail['civility'],
                'firstname'             => $registeredMail['firstname'],
                'lastname'              => $registeredMail['lastname'],
                'addressNumber'         => $registeredMail['addressNumber'],
                'addressStreet'         => $registeredMail['addressStreet'],
                'addressAdditional1'    => $registeredMail['addressAdditional1'],
                'addressAdditional2'    => $registeredMail['addressAdditional2'],
                'addressPostcode'       => $registeredMail['addressPostcode'],
                'addressTown'           => $registeredMail['addressTown'],
                'addressCountry'        => 'FRANCE'
            ];

            RegisteredMailModel::create([
                'res_id'        => $resId,
                'type'          => $registeredMail['registeredMail_type'],
                'issuing_site'  => $registeredMail['registeredMail_issuingSite'],
                'warranty'      => $registeredMail['registeredMail_warranty'],
                'letter'        => empty($registeredMail['registeredMail_letter']) ? 'false' : 'true',
                'recipient'     => json_encode($recipient),
                'number'        => $range[0]['current_number'],
                'reference'     => $reference,
                'generated'     => 'false',
            ]);

            RegisteredMailController::generateRegisteredMailPDF([
                'registeredMailNumber' => $registeredMailNumber,
                'type'                 => $registeredMail['registeredMail_type'],
                'warranty'             => $registeredMail['registeredMail_warranty'],
                'letter'               => !empty($registeredMail['registeredMail_letter']),
                'reference'            => $reference,
                'recipient'            => $recipient,
                'issuingSite'          => $issuingSite,
                'resId'                => $resId,
                'savePdf'              => true
            ]);
        }

        $return = [
            'success'   => count($body['registeredMails']) - count($warnings) - count($errors),
            'warnings'  => [
                'count'     => count($warnings),
                'details'   => $warnings
            ],
            'errors'    => [
                'count'     => count($errors),
                'details'   => $errors
            ]
        ];

        return $response->withJson($return);
    }

    public static function getRegisteredMailNumber(array $args)
    {
        $numberLength = $args['type'] == 'RW' ? 8 : 10;
        $number = str_split(str_pad($args['rawNumber'], $numberLength, "0", STR_PAD_LEFT));
        $registeredMailNumber = "{$args['type']} {$number[0]}{$number[1]}{$number[2]} {$number[3]}{$number[4]}{$number[5]} {$number[6]}{$number[7]}";
        if ($args['type'] == 'RW') {
            // International Registered Mail Number
            // source: Universal Postal Union S10 standard
            // https://www.upu.int/UPU/media/upu/files/postalSolutions/programmesAndServices/standards/S10-12.pdf
            $weights = [8, 6, 4, 2, 3, 5, 9, 7];

            $checkDigit = 0;
            foreach ($number as $index => $digit) {
                $checkDigit += $weights[$index] * $digit;
            }
            $checkDigit = 11 - ($checkDigit % 11);
            if ($checkDigit == 10) {
                $checkDigit = 0;
            } elseif ($checkDigit == 11) {
                $checkDigit = 5;
            }

            $registeredMailNumber .= " {$checkDigit} {$args['countryCode']}";
        } else {
            // La Poste FRANCE uses EAN13
            $s1 = $number[1] + $number[3] + $number[5] + $number[7] + $number[9];
            $s2 = $number[0] + $number[2] + $number[4] + $number[6] + $number[8];
            $s3 = $s1 * 3 + $s2;

            $checkDigit = $s3 % 10;
            if ($checkDigit != 0) {
                $checkDigit = 10 - $checkDigit;
            }

            $registeredMailNumber .= "{$number[8]}{$number[9]} {$checkDigit}";
        }

        return $registeredMailNumber;
    }

    public static function generateRegisteredMailPDF(array $args)
    {
        $resource = ResModel::getById(['select' => ['typist'], 'resId' => $args['resId']]);
        $primaryEntity = UserModel::getPrimaryEntityById(['select' => ['short_label'], 'id' => $resource['typist']]);

        $sender = ContactController::getContactAfnor([
            'company'               => $args['issuingSite']['label'],
            'firstname'             => $primaryEntity['short_label'],
            'address_number'        => $args['issuingSite']['address_number'],
            'address_street'        => $args['issuingSite']['address_street'],
            'address_additional1'   => $args['issuingSite']['address_additional1'],
            'address_additional2'   => $args['issuingSite']['address_additional2'],
            'address_postcode'      => $args['issuingSite']['address_postcode'],
            'address_town'          => $args['issuingSite']['address_town'],
            'address_country'       => $args['issuingSite']['address_country']
        ]);

        $recipient = ContactController::getContactAfnor([
            'company'               => $args['recipient']['company'],
            'civility'              => !empty($args['recipient']['civility']) ? ContactCivilityController::getIdByLabel(['label' => $args['recipient']['civility']]) : '',
            'firstname'             => $args['recipient']['firstname'],
            'lastname'              => $args['recipient']['lastname'],
            'address_number'        => $args['recipient']['addressNumber'],
            'address_street'        => $args['recipient']['addressStreet'],
            'address_additional1'   => $args['recipient']['addressAdditional1'],
            'address_additional2'   => $args['recipient']['addressAdditional2'],
            'address_postcode'      => $args['recipient']['addressPostcode'],
            'address_town'          => $args['recipient']['addressTown'],
            'address_country'       => $args['recipient']['addressCountry']
        ]);

        $registeredMailPDF = RegisteredMailController::getRegisteredMailPDF([
            'registeredMailNumber' => $args['registeredMailNumber'],
            'type'                 => $args['type'],
            'warranty'             => $args['warranty'],
            'letter'               => $args['letter'],
            'reference'            => $args['reference'],
            'recipient'            => $recipient,
            'sender'               => $sender,
            'resId'                => $args['resId'],
            'savePdf'              => $args['savePdf']
        ]);
        return $registeredMailPDF;
    }

    public static function getRegisteredMailPDF(array $args)
    {
        $registeredMailNumber = $args['registeredMailNumber'];

        $pdf = new Fpdi();
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetAutoPagebreak(false);
        if (!$args['savePdf']) {
            $pdf->addPage();
        }
        $pdf->SetFont('times', '', 11);

        $barcode = new Barcode();

        if ($args['savePdf']) {
            if ($args['type'] == '2C') {
                $pdf->setSourceFile(__DIR__ . '/../sample/registeredMail_ar.pdf');
            } elseif ($args['type'] == '2D') {
                $pdf->setSourceFile(__DIR__ . '/../sample/registeredMail.pdf');
            } else {
                $pdf->setSourceFile(__DIR__ . '/../sample/registeredMail_international.pdf');
            }
            $pageId = $pdf->ImportPage(1);
            $pageInfo = $pdf->getTemplatesize($pageId);
            $pdf->AddPage($pageInfo['orientation'], $pageInfo);
            $pdf->useImportedPage($pageId);
        }
        if ($args['type'] != 'RW') {

            // FEUILLE 1 : GAUCHE
            $pdf->SetXY(50, 15);
            $pdf->cell(0, 0, $registeredMailNumber);

            if ($args['warranty'] == 'R1') {
                $pdf->SetXY(85, 27);
                $pdf->cell(0, 0, 'X');
            } elseif ($args['warranty'] == 'R2') {
                $pdf->SetXY(98, 27);
                $pdf->cell(0, 0, 'X');
            } else {
                $pdf->SetXY(111, 27);
                $pdf->cell(0, 0, 'X');
            }
            if ($args['letter'] === true) {
                $pdf->SetXY(85, 32);
                $pdf->cell(0, 0, 'X');
            }
            $y = 40;
            $pdf->SetXY(36, $y);
            $pdf->cell(0, 0, $args['recipient'][1]);

            $y += 4;
            $pdf->SetXY(36, $y);
            $pdf->cell(0, 0, $args['recipient'][2]);

            $y += 4;
            $pdf->SetXY(36, $y);
            $pdf->cell(0, 0, $args['recipient'][3]);

            $y += 4;
            $pdf->SetXY(36, $y);
            $pdf->cell(0, 0, $args['recipient'][4]);

            $y += 4;
            $pdf->SetXY(36, $y);
            $pdf->cell(0, 0, $args['recipient'][5]);

            $y += 4;
            $pdf->SetXY(36, $y);
            $pdf->cell(0, 0, $args['recipient'][6]);


            // FEUILLE 1 : DROITE
            $y = 40;
            $pdf->SetXY(130, $y);
            $pdf->cell(0, 0, $args['recipient'][1]);

            $y += 4;
            $pdf->SetXY(130, $y);
            $pdf->cell(0, 0, $args['recipient'][2]);

            $y += 4;
            $pdf->SetXY(130, $y);
            $pdf->cell(0, 0, $args['recipient'][3]);

            $y += 4;
            $pdf->SetXY(130, $y);
            $pdf->cell(0, 0, $args['recipient'][4]);

            $y += 4;
            $pdf->SetXY(130, $y);
            $pdf->cell(0, 0, $args['recipient'][5]);

            $y += 4;
            $pdf->SetXY(130, $y);
            $pdf->cell(0, 0, $args['recipient'][6]);

            $pdf->SetXY(140, 70);
            $pdf->cell(0, 0, $registeredMailNumber);
            $barcodeObj = $barcode->getBarcodeObj('C128', $registeredMailNumber, -4, -100);
            $pdf->Image('@'.$barcodeObj->getPngData(), 140, 75, 60, 12, '', '', '', false, 300);


            // 2eme feuille
            $pdf->SetXY(63, 100);
            $pdf->cell(0, 0, $registeredMailNumber);
            $barcodeObj = $barcode->getBarcodeObj('C128', $registeredMailNumber, -4, -100);
            $pdf->Image('@'.$barcodeObj->getPngData(), 63, 105, 60, 12, '', '', '', false, 300);


            if ($args['warranty'] == 'R1') {
                $pdf->SetXY(98, 127);
                $pdf->cell(0, 0, 'X');
            } elseif ($args['warranty'] == 'R2') {
                $pdf->SetXY(111, 127);
                $pdf->cell(0, 0, 'X');
            } else {
                $pdf->SetXY(124, 127);
                $pdf->cell(0, 0, 'X');
            }
            if ($args['letter'] === true) {
                $pdf->SetXY(98, 133);
                $pdf->cell(0, 0, 'X');
            }

            $y = 140;
            $pdf->SetXY(57, $y);
            $pdf->cell(0, 0, $args['recipient'][1]);

            $y += 4;
            $pdf->SetXY(57, $y);
            $pdf->cell(0, 0, $args['recipient'][2]);

            $y += 4;
            $pdf->SetXY(57, $y);
            $pdf->cell(0, 0, $args['recipient'][3]);

            $y += 4;
            $pdf->SetXY(57, $y);
            $pdf->cell(0, 0, $args['recipient'][4]);

            $y += 4;
            $pdf->SetXY(57, $y);
            $pdf->cell(0, 0, $args['recipient'][5]);

            $y += 4;
            $pdf->SetXY(57, $y);
            $pdf->cell(0, 0, $args['recipient'][6]);

            $y = 170;
            $pdf->SetXY(57, $y);
            $pdf->cell(0, 0, $args['sender'][1]);

            $y += 4;
            $pdf->SetXY(57, $y);
            $pdf->cell(0, 0, $args['sender'][2]);

            $y += 4;
            $pdf->SetXY(57, $y);
            $pdf->cell(0, 0, $args['sender'][3]);

            $y += 4;
            $pdf->SetXY(57, $y);
            $pdf->cell(0, 0, $args['sender'][4]);

            $y += 4;
            $pdf->SetXY(57, $y);
            $pdf->cell(0, 0, $args['sender'][5]);

            $y += 4;
            $pdf->SetXY(57, $y);
            $pdf->cell(0, 0, $args['sender'][6]);


            // 3eme feuille
            if ($args['type'] == '2C') {
                $pdf->SetXY(37, 205);
                $pdf->cell(0, 0, $registeredMailNumber);
                $barcodeObj = $barcode->getBarcodeObj('C128', $registeredMailNumber, -4, -100);
                $pdf->Image('@'.$barcodeObj->getPngData(), 37, 212, 60, 12, '', '', '', false, 300);

                $y = 230;
                $pdf->SetXY(57, $y);
                $pdf->cell(0, 0, $args['recipient'][1]);

                $y += 4;
                $pdf->SetXY(57, $y);
                $pdf->cell(0, 0, $args['recipient'][2]);

                $y += 4;
                $pdf->SetXY(57, $y);
                $pdf->cell(0, 0, $args['recipient'][3]);

                $y += 4;
                $pdf->SetXY(57, $y);
                $pdf->cell(0, 0, $args['recipient'][4]);

                $y += 4;
                $pdf->SetXY(57, $y);
                $pdf->cell(0, 0, $args['recipient'][5]);

                $y += 4;
                $pdf->SetXY(57, $y);
                $pdf->cell(0, 0, $args['recipient'][6]);
            }

            $y = 260;
            $pdf->SetXY(57, $y);
            $pdf->cell(0, 0, $args['sender'][1]);

            $y += 4;
            $pdf->SetXY(57, $y);
            $pdf->cell(0, 0, $args['sender'][2]);

            $y += 4;
            $pdf->SetXY(57, $y);
            $pdf->cell(0, 0, $args['sender'][3]);

            $y += 4;
            $pdf->SetXY(57, $y);
            $pdf->cell(0, 0, $args['sender'][4]);

            $y += 4;
            $pdf->SetXY(57, $y);
            $pdf->cell(0, 0, $args['sender'][5]);

            $y += 4;
            $pdf->SetXY(57, $y);
            $pdf->cell(0, 0, $args['sender'][6]);

            $pdf->SetXY(5, 275);
            $pdf->Multicell(40, 5, $args['reference']);
        } else {
            $pdf->setFont('times', '', '8');

            $y = 27;
            $pdf->SetXY(127, $y);
            $pdf->cell(0, 0, $args['recipient'][1]);

            $y += 4;
            $pdf->SetXY(127, $y);
            $pdf->cell(0, 0, $args['recipient'][2]);

            $y += 4;
            $pdf->SetXY(127, $y);
            $pdf->cell(0, 0, $args['recipient'][3]);

            $y += 4;
            $pdf->SetXY(127, $y);
            $pdf->cell(0, 0, $args['recipient'][4]);

            $y += 4;
            $pdf->SetXY(127, $y);
            $pdf->cell(0, 0, $args['recipient'][5]);

            $y += 4;
            $pdf->SetXY(127, $y);
            $pdf->cell(0, 0, $args['recipient'][6]);

            $y += 4;
            $pdf->SetXY(127, $y);
            $pdf->cell(0, 0, $args['recipient'][7]);

            $y = 2;
            $pdf->SetXY(26, $y);
            $pdf->cell(0, 0, $args['sender'][1]);

            $y += 3;
            $pdf->SetXY(26, $y);
            $pdf->cell(0, 0, $args['sender'][2]);

            $y += 3;
            $pdf->SetXY(26, $y);
            $pdf->cell(0, 0, $args['sender'][3]);

            $y += 3;
            $pdf->SetXY(26, $y);
            $pdf->cell(0, 0, $args['sender'][4]);

            $y += 3;
            $pdf->SetXY(26, $y);
            $pdf->cell(0, 0, $args['sender'][5]);

            $y += 3;
            $pdf->SetXY(26, $y);
            $pdf->cell(0, 0, "{$args['sender'][6]}, {$args['sender'][7]}");

            $pdf->SetXY(37.5, 22);
            $pdf->cell(0, 0, $args['sender'][7]);

            $pdf->SetFont('times', '', 11);

            if ($args['warranty'] == 'R1') {
                $pdf->SetXY(70.2, 24.4);
                $pdf->cell(0, 0, 'X');
            } elseif ($args['warranty'] == 'R2') {
                $pdf->SetXY(77.2, 24.4);
                $pdf->cell(0, 0, 'X');
            }

            $pdf->SetXY(52, 27.5);
            $pdf->cell(0, 0, $registeredMailNumber);

            $pdf->SetXY(52, 36.5);
            $pdf->cell(0, 0, $registeredMailNumber);
            $barcodeObj = $barcode->getBarcodeObj('C128', $registeredMailNumber, -4, -100);
            $pdf->Image('@'.$barcodeObj->getPngData(), 38, 41, 60, 10, '', '', '', false, 300);

            $pdf->SetXY(52, 57);
            $pdf->cell(0, 0, $registeredMailNumber);
            $barcodeObj = $barcode->getBarcodeObj('C128', $registeredMailNumber, -4, -100);
            $pdf->Image('@'.$barcodeObj->getPngData(), 38, 62, 60, 10, '', '', '', false, 300);
            $pdf->SetXY(52, 72);
            $pdf->cell(0, 0, $registeredMailNumber);

            $pdf->setFont('times', '', '8');

            $y = 236;
            $pdf->SetXY(103, $y);
            $pdf->cell(0, 0, $args['sender'][1]);

            $y += 3;
            $pdf->SetXY(103, $y);
            $pdf->cell(0, 0, $args['sender'][2]);

            $y += 3;
            $pdf->SetXY(103, $y);
            $pdf->cell(0, 0, $args['sender'][3]);

            $y += 3;
            $pdf->SetXY(103, $y);
            $pdf->cell(0, 0, $args['sender'][4]);

            $y += 3;
            $pdf->SetXY(103, $y);
            $pdf->cell(0, 0, $args['sender'][5]);

            $y += 3;
            $pdf->SetXY(103, $y);
            $pdf->cell(0, 0, $args['sender'][6]);

            $y += 3;
            $pdf->SetXY(103, $y);
            $pdf->cell(0, 0, $args['sender'][7]);

            $pdf->SetXY(120, 209);
            $pdf->cell(0, 0, $registeredMailNumber);

            $pdf->setFont('times', '', '10');
            $pdf->SetXY(95, 219);
            $pdf->Multicell(70, 5, $args['reference']);
            $pdf->setFont('times', '', '8');

            $y = 208;
            $pdf->SetXY(20, $y);
            $pdf->cell(0, 0, $args['recipient'][1]);

            $y += 4;
            $pdf->SetXY(20, $y);
            $pdf->cell(0, 0, $args['recipient'][2]);

            $y += 4;
            $pdf->SetXY(20, $y);
            $pdf->cell(0, 0, $args['recipient'][3]);

            $y += 4;
            $pdf->SetXY(20, $y);
            $pdf->cell(0, 0, $args['recipient'][4]);

            $y += 4;
            $pdf->SetXY(20, $y);
            $pdf->cell(0, 0, $args['recipient'][5]);

            $y += 4;
            $pdf->SetXY(20, $y);
            $pdf->cell(0, 0, $args['recipient'][6]);

            $y += 4;
            $pdf->SetXY(20, $y);
            $pdf->cell(0, 0, $args['recipient'][7]);
        }

        $fileContent = $pdf->Output('', 'S');

        if ($args['savePdf']) {
            AdrModel::deleteDocumentAdr(['where' => ['res_id = ?'], 'data' => [$args['resId']]]);
            $storeResult = DocserverController::storeResourceOnDocServer([
                'collId'            => 'letterbox_coll',
                'docserverTypeId'   => 'DOC',
                'encodedResource'   => base64_encode($fileContent),
                'format'            => 'pdf'
            ]);
            if (!empty($storeResult['errors'])) {
                return ['errors' => '[storeResource] ' . $storeResult['errors']];
            }
    
            $data['docserver_id']   = $storeResult['docserver_id'];
            $data['filename']       = $storeResult['file_destination_name'];
            $data['filesize']       = $storeResult['fileSize'];
            $data['path']           = $storeResult['directory'];
            $data['fingerprint']    = $storeResult['fingerPrint'];
            $data['format']         = 'pdf';
    
            ResModel::update(['set' => $data, 'where' => ['res_id = ?'], 'data' => [$args['resId']]]);
        }

        return ['fileContent' => $fileContent];
    }

    public static function getDepositListPdf(array $args)
    {
        $pdf = new Fpdi();
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetAutoPagebreak(false);
        $pdf->addPage();
        $pdf->SetFont('times', '', 11);

        $nb = 0;
        $page = 1;

        $pdf->setFont('times', 'B', 11);
        $pdf->SetXY(10, 10);
        if ($args['type'] == '2D') {
            $pdf->MultiCell(0, 15, "DESCRIPTIF DE PLI - LETTRE RECOMMANDEE SANS AR", 'LRTB', 'C', 0);
        } elseif ($args['type'] == '2C') {
            $pdf->MultiCell(0, 15, "DESCRIPTIF DE PLI - LETTRE RECOMMANDEE AVEC AR", 'LRTB', 'C', 0);
        } else {
            $pdf->MultiCell(0, 15, "DESCRIPTIF DE PLI - LETTRE RECOMMANDEE INTERNATIONALE AVEC AR", 'LRTB', 'C', 0);
        }

        $pdf->SetXY(10, 20);
        $pdf->setFont('times', '', 10);
        $pdf->MultiCell(0, 15, "(Descriptif de pli faisant office de preuve de dépôt après validation de La Poste)", '', 'C', 0);

        $pdf->SetXY(10, 30);
        $pdf->setFont('times', 'B', 11);
        $pdf->Cell(30, 10, "Raison Sociale", 1);
        $pdf->setFont('times', '', 11);
        $pdf->Cell(85, 10, $args['site']['label'], 1);
        $pdf->Ln();
        $pdf->setFont('times', 'B', 11);
        $pdf->Cell(30, 10, "Adresse", 1);
        $pdf->setFont('times', '', 11);
        $pdf->Cell(85, 10, $args['site']['addressNumber'] . ' ' . $args['site']['addressStreet'], 1);
        $pdf->Ln();
        $pdf->setFont('times', 'B', 11);
        $pdf->Cell(30, 10, "Code postal", 1);
        $pdf->setFont('times', '', 11);
        $pdf->Cell(15, 10, $args['site']['addressPostcode'], 1);
        $pdf->setFont('times', 'B', 11);
        $pdf->Cell(15, 10, "Ville", 1);
        $pdf->setFont('times', '', 11);
        $pdf->Cell(55, 10, $args['site']['addressTown'], 1);
        $pdf->Ln();

        $pdf->SetXY(145, 30);
        $pdf->setFont('times', 'B', 11);
        $pdf->Cell(55, 10, "N° de Client (Coclico)", 1, 0, 'C');
        $pdf->Ln();
        $pdf->SetXY(145, 40);
        $pdf->setFont('times', '', 11);
        $pdf->Cell(55, 10, $args['site']['accountNumber'], 1, 0, 'C');
        $pdf->Ln();

        $pdf->SetXY(145, 50);
        $pdf->setFont('times', 'B', 11);
        $pdf->Cell(55, 10, "N° de Compte de suivi", 1, 0, 'C');
        $pdf->Ln();

        $pdf->SetXY(145, 60);
        $pdf->setFont('times', '', 11);
        $pdf->Cell(55, 10, $args['trackingNumber'], 1, 0, 'C');
        $pdf->Ln();

        $pdf->SetXY(10, 71);
        $pdf->setFont('times', 'B', 11);
        $pdf->Cell(30, 10, "Site de dépôt", 0);
        $pdf->SetXY(10, 80);
        $pdf->Cell(30, 10, "Lieu", 1);
        $pdf->setFont('times', '', 11);
        $pdf->Cell(100, 10, $args['site']['postOfficeLabel'], 1);
        $pdf->setFont('times', 'B', 11);
        $pdf->Cell(20, 10, "Date", 1);
        $pdf->setFont('times', '', 11);

        $date = new \DateTime($args['departureDate']);

        $pdf->Cell(40, 10, $date->format('d/m/Y'), 1);
        $pdf->SetXY(10, 100);
        $pdf->Cell(10, 10, "", 1);
        $pdf->setFont('times', 'B', 11);
        $pdf->Cell(30, 10, "ID du pli", 1, 0, 'C');
        $pdf->Cell(10, 10, "NG*", 1, 0, 'C');
        $pdf->Cell(15, 10, "CRBT", 1, 0, 'C');
        $pdf->Cell(30, 10, "Référence", 1, 0, 'C');
        $pdf->Cell(95, 10, "Destinataire", 1, 0, 'C');
        $pdf->Ln();

        // List
        foreach ($args['registeredMails'] as $position => $registeredMail) {
            if ($position % 9 == 0) {
                $nb++;
            }

            $referenceInfo = json_decode($registeredMail['recipient'], true);
            $recipient = ContactController::getContactAfnor([
                'company'               => $referenceInfo['company'],
                'civility'              => !empty($referenceInfo['civility']) ? ContactCivilityController::getIdByLabel(['label' => $referenceInfo['civility']]) : '',
                'firstname'             => $referenceInfo['firstname'],
                'lastname'              => $referenceInfo['lastname'],
                'address_number'        => $referenceInfo['addressNumber'],
                'address_street'        => $referenceInfo['addressStreet'],
                'address_additional1'   => $referenceInfo['addressAdditional1'],
                'address_additional2'   => $referenceInfo['addressAdditional2'],
                'address_postcode'      => $referenceInfo['addressPostcode'],
                'address_town'          => $referenceInfo['addressTown'],
                'address_country'       => $referenceInfo['addressCountry']
            ]);

            $pdf->setFont('times', '', 9);
            $pdf->Cell(10, 10, $position + 1, 1, 0, 'C');
            $pdf->setFont('times', '', 9);
            $pdf->Cell(30, 10, $registeredMail['alt_identifier'], 1, 0, 'C');
            $pdf->Cell(10, 10, $registeredMail['warranty'], 1, 0, 'C');
            $pdf->Cell(15, 10, "", 1);
            $pdf->Cell(30, 10, mb_strimwidth($registeredMail['reference'], 0, 22, "...", "UTF-8"), 1, 0, 'C');

            $pdf->setFont('times', '', 6);
            $recipientLabel = $recipient[2] ?? '';
            if (empty($recipientLabel)) {
                $recipientLabel = $recipient[1];
            } elseif (!empty($recipient[1])) {
                $recipientLabel .= ' (' . $recipient[1] . ')';
            }
            $recipientLabel = trim($recipientLabel);
            if (strlen($recipientLabel . " " . $recipient[4] . " " . $recipient[6] . " " . $recipient[7]) > 60) {
                $pdf->Cell(95, 10, $recipientLabel, 1);
                $pdf->SetXY($pdf->GetX() - 95, $pdf->GetY() + 3);
                $pdf->Cell(95, 10, $recipient[4] . " " . $recipient[6] . " " . $recipient[7], 0);
                $pdf->SetXY($pdf->GetX() + 95, $pdf->GetY() - 3);
            } else {
                $pdf->Cell(95, 10, $recipientLabel . " " . $recipient[4] . " " . $recipient[6] . " " . $recipient[7], 1);
            }

            $pdf->Ln();
            //contrôle du nb de reco présent sur la page. Si 16 lignes, changement de page et affichage du footer
            if ($position % 12 >= 11) {
                $pdf->SetXY(10, 276);
                $pdf->setFont('times', 'I', 8);
                $pdf->Cell(0, 0, "*Niveau de garantie (R1 pour tous ou R2, R3");
                $pdf->SetXY(-30, 276);
                $pdf->setFont('times', 'I', 8);
                $pdf->Cell(0, 0, $page . '/' . $nb);
                $pdf->addPage();
                $page++;
            }
        }

        //contrôle du nb de reco présent sur la page. Si trop, saut de page pour la partie réservé à la poste
        if ($position % 10 >= 9) {
            $pdf->SetXY(10, 276);
            $pdf->setFont('times', 'I', 8);
            $pdf->Cell(0, 0, "*Niveau de garantie (R1 pour tous ou R2, R3");
            $pdf->SetXY(-30, 276);
            $pdf->setFont('times', 'I', 8);
            $pdf->Cell(0, 0, $page . '/' . $nb);
            $pdf->addPage();
            $page++;
        }
        $pdf->setFont('times', 'B', 9);
        $pdf->SetXY(10, 228);
        $pdf->Cell(0, 0, 'Partie réservée au contrôle postal');
        $pdf->SetXY(110, 238);
        $pdf->setFont('times', '', 11);
        $pdf->SetXY(10, 233);
        $pdf->Cell(90, 40, '', 1);
        $pdf->Cell(50, 40, '', 1);
        $pdf->Cell(11, 10, "Total", 1);
        $pdf->setFont('times', '', 9);
        $position = $position + 1;
        $pdf->Cell(0, 10, $position . " recommandé(s)", 1);
        $pdf->SetXY(10, 234);
        $pdf->Cell(0, 0, 'Commentaire :');
        $pdf->SetXY(110, 234);
        $pdf->Cell(0, 0, 'Timbre à date :');
        $pdf->setFont('times', 'I', 8);
        $pdf->SetXY(100, 268);
        $pdf->Cell(0, 0, 'Visa après contrôle des quantités.');

        $pdf->SetXY(10, 276);
        $pdf->setFont('times', 'I', 8);
        $pdf->Cell(0, 0, "*Niveau de garantie (R1 pour tous ou R2, R3)");
        $pdf->SetXY(-30, 276);
        $pdf->setFont('times', 'I', 8);
        $pdf->Cell(0, 0, $page . '/' . $nb);

        $fileContent = $pdf->Output('', 'S');
        return ['fileContent' => $fileContent];
    }

    public static function getFormattedRegisteredMail(array $args)
    {
        ValidatorModel::notEmpty($args, ['resId']);
        ValidatorModel::intVal($args, ['resId']);

        $registeredMail = RegisteredMailModel::getWithResources([
            'select' => ['issuing_site', 'type', 'deposit_id', 'warranty', 'letter', 'recipient', 'reference', 'generated', 'number', 'alt_identifier'],
            'where'  => ['res_letterbox.res_id = ?'],
            'data'   => [$args['resId']]
        ]);

        if (!empty($registeredMail)) {
            $registeredMail[0]['recipient']   = json_decode($registeredMail[0]['recipient'], true);
            $registeredMail[0]['number']      = $registeredMail[0]['alt_identifier'];
            $registeredMail[0]['issuingSite'] = $registeredMail[0]['issuing_site'];
            unset($registeredMail[0]['issuing_site']);

            return $registeredMail[0];
        }

        return [];
    }

    private static function getFormattedRegisteredNumber(array $args)
    {
        ValidatorModel::notEmpty($args, ['number']);
        ValidatorModel::stringType($args, ['number']);

        $number = trim($args['number'], ' ');
        if ($number[2] != ' ') {
            $number = substr_replace($number, ' ', 2, 0);
        }
        if ($number[6] != ' ') {
            $number = substr_replace($number, ' ', 6, 0);
        }
        if ($number[10] != ' ') {
            $number = substr_replace($number, ' ', 10, 0);
        }
        if ($number[15] != ' ') {
            $number = substr_replace($number, ' ', 15, 0);
        }

        return $number;
    }
}
