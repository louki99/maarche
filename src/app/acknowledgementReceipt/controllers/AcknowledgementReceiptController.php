<?php

/**
* Copyright Maarch since 2008 under licence GPLv3.
* See LICENCE.txt file at the root folder for more details.
* This file is part of Maarch software.
*
*/

/**
* @brief Acknowledgement Receipt Controller
* @author dev@maarch.org
*/

namespace AcknowledgementReceipt\controllers;

use AcknowledgementReceipt\models\AcknowledgementReceiptModel;
use Contact\models\ContactModel;
use Docserver\models\DocserverModel;
use History\controllers\HistoryController;
use Resource\controllers\ResController;
use Resource\controllers\StoreController;
use Respect\Validation\Validator;
use setasign\Fpdi\Tcpdf\Fpdi;
use Slim\Psr7\Request;
use SrcCore\http\Response;
use User\models\UserModel;

class AcknowledgementReceiptController
{
    public function getByResId(Request $request, Response $response, array $args)
    {
        if (!Validator::intVal()->validate($args['resId']) || !ResController::hasRightByResId(['resId' => [$args['resId']], 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Document out of perimeter']);
        }

        $acknowledgementReceiptsModel = AcknowledgementReceiptModel::get([
            'select' => ['id', 'res_id', 'type', 'format', 'user_id', 'creation_date', 'send_date', 'contact_id', 'cc', 'cci'],
            'where'  => ['res_id = ?'],
            'data'   => [$args['resId']]
        ]);

        $acknowledgementReceipts = [];

        foreach ($acknowledgementReceiptsModel as $acknowledgementReceipt) {
            $contact = ContactModel::getById(['id' => $acknowledgementReceipt['contact_id'], 'select' => ['firstname', 'lastname', 'company', 'email']]);

            $userLabel = UserModel::getLabelledUserById(['id' => $acknowledgementReceipt['user_id']]);

            $acknowledgementReceipts[] = [
                'id'           => $acknowledgementReceipt['id'],
                'resId'        => $acknowledgementReceipt['res_id'],
                'type'         => $acknowledgementReceipt['type'],
                'format'       => $acknowledgementReceipt['format'],
                'userId'       => $acknowledgementReceipt['user_id'],
                'userLabel'    => $userLabel,
                'creationDate' => $acknowledgementReceipt['creation_date'],
                'sendDate'     => $acknowledgementReceipt['send_date'],
                'contact'      => $contact,
                'cc'           => json_decode($acknowledgementReceipt['cc'], true),
                'cci'          => json_decode($acknowledgementReceipt['cci'], true),
            ];
        }

        return $response->withJson($acknowledgementReceipts);
    }

    public function getById(Request $request, Response $response, array $args)
    {
        if (!Validator::intVal()->validate($args['id'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Route param id is not an integer']);
        }

        $acknowledgementReceipt = AcknowledgementReceiptModel::getByIds([
            'select'  => ['id', 'res_id', 'type', 'format', 'user_id', 'creation_date', 'send_date', 'contact_id', 'cc', 'cci'],
            'ids'     => [$args['id']]
        ]);

        if (empty($acknowledgementReceipt[0])) {
            return $response->withStatus(400)->withJson(['errors' => 'Acknowledgement receipt does not exist']);
        }
        $acknowledgementReceipt = $acknowledgementReceipt[0];

        if (!Validator::intVal()->validate($acknowledgementReceipt['res_id']) || !ResController::hasRightByResId(['resId' => [$acknowledgementReceipt['res_id']], 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Document out of perimeter']);
        }

        $contact = ContactModel::getById(['id' => $acknowledgementReceipt['contact_id'], 'select' => ['firstname', 'lastname', 'company', 'email']]);

        $userLabel = UserModel::getLabelledUserById(['id' => $acknowledgementReceipt['user_id']]);

        $acknowledgementReceipt = [
            'id'           => $acknowledgementReceipt['id'],
            'resId'        => $acknowledgementReceipt['res_id'],
            'type'         => $acknowledgementReceipt['type'],
            'format'       => $acknowledgementReceipt['format'],
            'userId'       => $acknowledgementReceipt['user_id'],
            'userLabel'    => $userLabel,
            'creationDate' => $acknowledgementReceipt['creation_date'],
            'sendDate'     => $acknowledgementReceipt['send_date'],
            'contact'      => $contact,
            'cc'           => json_decode($acknowledgementReceipt['cc'], true),
            'cci'          => json_decode($acknowledgementReceipt['cci'], true),
        ];

        return $response->withJson(['acknowledgementReceipt' => $acknowledgementReceipt]);
    }

    public function createPaperAcknowledgement(Request $request, Response $response)
    {
        $bodyData = $request->getParsedBody();

        if (!Validator::arrayType()->notEmpty()->validate($bodyData['resources'])) {
            return $response->withStatus(403)->withJson(['errors' => 'Resources is not set or empty']);
        }

        $bodyData['resources'] = array_slice($bodyData['resources'], 0, 500);

        $acknowledgements = AcknowledgementReceiptModel::getByIds([
            'select'  => ['res_id', 'docserver_id', 'path', 'filename', 'fingerprint', 'send_date', 'format'],
            'ids'     => $bodyData['resources'],
            'orderBy' => ['res_id']
        ]);

        $resourcesInBasket = array_column($acknowledgements, 'res_id');

        if (empty($resourcesInBasket) || !ResController::hasRightByResId(['resId' => $resourcesInBasket, 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Documents out of perimeter']);
        }

        $pdf = new Fpdi('P', 'pt');
        $pdf->setPrintHeader(false);

        foreach ($acknowledgements as $value) {
            if (empty($value['send_date']) && $value['format'] == 'pdf') {
                $docserver = DocserverModel::getByDocserverId(['docserverId' => $value['docserver_id'], 'select' => ['path_template', 'docserver_type_id']]);
                if (empty($docserver['path_template']) || !file_exists($docserver['path_template'])) {
                    return $response->withStatus(400)->withJson(['errors' => 'Docserver does not exist']);
                }
                $pathToDocument = $docserver['path_template'] . str_replace('#', DIRECTORY_SEPARATOR, $value['path']) . $value['filename'];
                if (!file_exists($pathToDocument)) {
                    return $response->withStatus(404)->withJson(['errors' => 'Document not found on docserver']);
                }

                $fingerprint = StoreController::getFingerPrint(['filePath' => $pathToDocument]);
                if (!empty($value['fingerprint']) && $value['fingerprint'] != $fingerprint) {
                    return $response->withStatus(400)->withJson(['errors' => 'Fingerprints do not match']);
                }

                $nbPages = $pdf->setSourceFile($pathToDocument);
                for ($i = 1; $i <= $nbPages; $i++) {
                    $page = $pdf->importPage($i, 'CropBox');
                    $size = $pdf->getTemplateSize($page);
                    $pdf->AddPage($size['orientation'], $size);
                    $pdf->useImportedPage($page);
                }
            }
        }

        $fileContent = $pdf->Output('', 'S');
        $finfo       = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType    = $finfo->buffer($fileContent);

        $response->write($fileContent);
        $response = $response->withAddedHeader('Content-Disposition', "inline; filename=maarch.pdf");

        return $response->withHeader('Content-Type', $mimeType);
    }

    public function getAcknowledgementReceipt(Request $request, Response $response, array $args)
    {
        $document = AcknowledgementReceiptModel::getByIds([
            'select'  => ['docserver_id', 'path', 'filename', 'fingerprint', 'res_id', 'format'],
            'ids'     => [$args['id']]
        ]);

        if (empty($document[0])) {
            return $response->withStatus(400)->withJson(['errors' => 'Acknowledgement receipt does not exist']);
        }
        $document = $document[0];

        if (!Validator::intVal()->validate($document['res_id']) || !ResController::hasRightByResId(['resId' => [$document['res_id']], 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Document out of perimeter']);
        }

        $docserver = DocserverModel::getByDocserverId(['docserverId' => $document['docserver_id'], 'select' => ['path_template', 'docserver_type_id']]);
        if (empty($docserver['path_template']) || !is_dir($docserver['path_template'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Docserver does not exist']);
        }

        $pathToDocument = $docserver['path_template'] . str_replace('#', DIRECTORY_SEPARATOR, $document['path']) . $document['filename'];

        if (!is_file($pathToDocument)) {
            return $response->withStatus(404)->withJson(['errors' => 'Document not found on docserver']);
        }

        $fingerprint = StoreController::getFingerPrint(['filePath' => $pathToDocument]);
        if (!empty($document['fingerprint']) && $document['fingerprint'] != $fingerprint) {
            return $response->withStatus(400)->withJson(['errors' => 'Fingerprints do not match']);
        }

        $fileContent = file_get_contents($pathToDocument);
        if ($fileContent === false) {
            return $response->withStatus(404)->withJson(['errors' => 'Document not found on docserver']);
        }

        HistoryController::add([
            'tableName' => 'acknowledgement_receipts',
            'recordId'  => $args['id'],
            'eventType' => 'VIEW',
            'info'      => _ACKNOWLEDGEMENT_RECEIPT_DISPLAYING . " : {$args['id']}",
            'moduleId'  => 'acknowledgementReceipt',
            'eventId'   => 'acknowledgementReceiptView',
        ]);

        return $response->withJson(['encodedDocument' => base64_encode($fileContent), 'format' => $document['format']]);
    }
}
