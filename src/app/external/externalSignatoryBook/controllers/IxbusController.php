<?php

/**
 * Copyright Maarch since 2008 under licence GPLv3.
 * See LICENCE.txt file at the root folder for more details.
 * This file is part of Maarch software.
 *
 */

/**
 * @brief Ixbus Controller
 * @author dev@maarch.org
 */

namespace ExternalSignatoryBook\controllers;

use Attachment\models\AttachmentModel;
use Attachment\models\AttachmentTypeModel;
use Convert\controllers\ConvertPdfController;
use Docserver\models\DocserverModel;
use Docserver\models\DocserverTypeModel;
use Resource\controllers\StoreController;
use Resource\models\ResModel;
use SrcCore\models\CurlModel;
use SrcCore\models\TextFormatModel;
use Slim\Psr7\Request;
use SrcCore\http\Response;
use SrcCore\models\CoreConfigModel;

/**
    * @codeCoverageIgnore
*/
class IxbusController
{
    public static function getInitializeDatas($config)
    {
        $curlResponse = CurlModel::exec([
            'url'     => rtrim($config['data']['url'], '/') . '/api/parapheur/v1/nature',
            'headers' => ['IXBUS_API:' . $config['data']['tokenAPI']],
            'method'  => 'GET'
        ]);

        if (!empty($curlResponse['response']['error'])) {
            return ['error' => $curlResponse['message']];
        }

        foreach ($curlResponse['response']['payload'] as $key => $value) {
            unset($curlResponse['response']['payload'][$key]['motClefs']);
        }
        return ['natures' => $curlResponse['response']['payload']];
    }

    public function getNatureDetails(Request $request, Response $response, array $args)
    {
        $loadedXml = CoreConfigModel::getXmlLoaded(['path' => 'modules/visa/xml/remoteSignatoryBooks.xml']);
        if (empty($loadedXml)) {
            return $response->withStatus(500)->withJson(['errors' => 'remote signatory book: no configuration file found']);
        }
        $config = ['id' => (string)$loadedXml->signatoryBookEnabled];
        if ($config['id'] != 'ixbus') {
            return $response->withStatus(400)->withJson(['errors' => 'ixbus is disabled']);
        }
        foreach ($loadedXml->signatoryBook as $value) {
            if ($value->id == $config['id']) {
                $config['data'] = (array)$value;
                break;
            }
        }
        if (empty($config['data']['url'])) {
            return $response->withStatus(500)->withJson(['errors' => 'no ixbus url configured']);
        }

        $curlResponse = CurlModel::exec([
            'method'  => 'GET',
            'url'     => rtrim($config['data']['url'], '/') . '/api/parapheur/v1/circuit/' . $args['natureId'],
            'headers' => ['IXBUS_API:' . $config['data']['tokenAPI']]
        ]);

        if (empty($curlResponse['response']['payload']) || !empty($curlResponse['response']['error'])) {
            return $response->withStatus(500)->withJson(['errors' => $curlResponse['message'] ?? "HTTP {$curlResponse['code']} while contacting ixbus"]);
        }

        foreach ($curlResponse['response']['payload'] as $key => $value) {
            unset($curlResponse['response']['payload'][$key]['etapes']);
            unset($curlResponse['response']['payload'][$key]['options']);
        }

        $return = ['messageModels' => $curlResponse['response']['payload']];

        $curlResponse = CurlModel::exec([
            'url'     => rtrim($config['data']['url'], '/') . '/api/parapheur/v1/nature/' . $args['natureId'] . '/redacteur',
            'headers' => ['IXBUS_API:' . $config['data']['tokenAPI']],
            'method'  => 'GET'
        ]);

        if (empty($curlResponse['response']['payload']) || !empty($curlResponse['response']['error'])) {
            return $response->withStatus(500)->withJson(['errors' => $curlResponse['message'] ?? "HTTP {$curlResponse['code']} while contacting ixbus"]);
        }

        $return['users'] = $curlResponse['response']['payload'];

        return $response->withJson($return);
    }

    public static function sendDatas($aArgs)
    {
        $mainResource = ResModel::getById([
            'select' => ['res_id', 'path', 'filename', 'docserver_id', 'format', 'category_id', 'external_id', 'integrations', 'subject'],
            'resId'  => $aArgs['resIdMaster']
        ]);

        if (!empty($mainResource['docserver_id'])) {
            $adrMainInfo          = ConvertPdfController::getConvertedPdfById(['resId' => $aArgs['resIdMaster'], 'collId' => 'letterbox_coll']);
            $letterboxPath        = DocserverModel::getByDocserverId(['docserverId' => $adrMainInfo['docserver_id'], 'select' => ['path_template']]);
            $mainDocumentFilePath = $letterboxPath['path_template'] . str_replace('#', '/', $adrMainInfo['path']) . $adrMainInfo['filename'];
        }

        $attachments = AttachmentModel::get([
            'select' => [
                'res_id', 'title', 'identifier', 'attachment_type', 'status', 'typist', 'docserver_id', 'path', 'filename', 'creation_date',
                'validation_date', 'relation', 'origin_id', 'fingerprint', 'format'
            ],
            'where'  => ["res_id_master = ?", "attachment_type not in (?)", "status not in ('DEL', 'OBS', 'FRZ', 'TMP', 'SEND_MASS')", "in_signature_book = 'true'"],
            'data'   => [$aArgs['resIdMaster'], ['incoming_mail_attachment', 'signed_response']]
        ]);

        $annexesAttachments = [];
        $attachmentTypes    = AttachmentTypeModel::get(['select' => ['type_id', 'signable']]);
        $attachmentTypes    = array_column($attachmentTypes, 'signable', 'type_id');
        foreach ($attachments as $key => $value) {
            if (!$attachmentTypes[$value['attachment_type']]) {
                $adrInfo = ConvertPdfController::getConvertedPdfById(['resId' => $value['res_id'], 'collId' => 'attachments_coll']);
                if (empty($adrInfo['docserver_id']) || strtolower(pathinfo($adrInfo['filename'], PATHINFO_EXTENSION)) != 'pdf') {
                    return ['error' => 'Attachment ' . $value['res_id'] . ' is not converted in pdf'];
                }
                $docserverInfo = DocserverModel::getByDocserverId(['docserverId' => $adrInfo['docserver_id']]);
                if (empty($docserverInfo['path_template'])) {
                    return ['error' => 'Docserver does not exist ' . $adrInfo['docserver_id']];
                }
                $filePath      = $docserverInfo['path_template'] . str_replace('#', '/', $adrInfo['path']) . $adrInfo['filename'];
                $docserverType = DocserverTypeModel::getById(['id' => $docserverInfo['docserver_type_id'], 'select' => ['fingerprint_mode']]);
                $fingerprint   = StoreController::getFingerPrint(['filePath' => $filePath, 'mode' => $docserverType['fingerprint_mode']]);
                if ($adrInfo['fingerprint'] != $fingerprint) {
                    return ['error' => 'Fingerprints do not match'];
                }

                $annexesAttachments[] = ['filePath' => $filePath, 'fileName' => $value['title'] . '.pdf'];
                unset($attachments[$key]);
            }
        }

        $attachmentToFreeze = [];
        $mainResource = ResModel::getById([
            'resId'  => $aArgs['resIdMaster'],
            'select' => ['res_id', 'subject', 'path', 'filename', 'docserver_id', 'format', 'category_id', 'external_id', 'integrations', 'process_limit_date', 'fingerprint']
        ]);

        if (empty($mainResource['process_limit_date'])) {
            $processLimitDate = date('Y-m-d', strtotime(date("Y-m-d"). ' + 14 days'));
        } else {
            $processLimitDateTmp = explode(" ", $mainResource['process_limit_date']);
            $processLimitDate = $processLimitDateTmp[0];
        }

        $attachmentsData = [];
        if (!empty($mainDocumentFilePath)) {
            $attachmentsData = [[
                'filePath' => $mainDocumentFilePath,
                'fileName' => TextFormatModel::formatFilename(['filename' => $mainResource['subject'], 'maxLength' => 250]) . '.pdf'
            ]];
        }
        $attachmentsData = array_merge($annexesAttachments, $attachmentsData);

        $signature = $aArgs['manSignature'] == 'manual' ? 1 : 0;
        $bodyData = [
            'nature'     => $aArgs['natureId'],
            'referent'   => $aArgs['referent'],
            'circuit'    => $aArgs['messageModel'],
            'options'    => ['confidentiel' => false, 'dateLimite' => true, 'documentModifiable' => true, 'annexesSignables' => false, 'autoriserModificationAnnexes' => false, 'signature' => $signature],
            'dateLimite' => $processLimitDate,
        ];

        foreach ($attachments as $value) {
            $resId  = $value['res_id'];
            $collId = 'attachments_coll';

            $adrInfo       = ConvertPdfController::getConvertedPdfById(['resId' => $resId, 'collId' => $collId]);
            $docserverInfo = DocserverModel::getByDocserverId(['docserverId' => $adrInfo['docserver_id']]);
            $filePath      = $docserverInfo['path_template'] . str_replace('#', '/', $adrInfo['path']) . $adrInfo['filename'];

            $docserverType = DocserverTypeModel::getById(['id' => $docserverInfo['docserver_type_id'], 'select' => ['fingerprint_mode']]);
            $fingerprint   = StoreController::getFingerPrint(['filePath' => $filePath, 'mode' => $docserverType['fingerprint_mode']]);
            if ($adrInfo['fingerprint'] != $fingerprint) {
                return ['error' => 'Fingerprints do not match'];
            }

            $bodyData['nom'] = $value['title'];

            $createdFile = IxBusController::createFolder(['config' => $aArgs['config'], 'body' => $bodyData]);
            if (!empty($createdFile['error'])) {
                return ['error' => $createdFile['message']];
            }
            $folderId = $createdFile['folderId'];

            $addedFile = IxBusController::addFileToFolder([
                'config'   => $aArgs['config'],
                'folderId' => $folderId,
                'filePath' => $filePath,
                'fileName' => TextFormatModel::formatFilename(['filename' => $value['title'], 'maxLength' => 250]) . '.pdf',
                'fileType' => 'principal'
            ]);
            if (!empty($addedFile['error'])) {
                return ['error' => $addedFile['message']];
            }

            foreach ($attachmentsData as $attachmentData) {
                $addedFile = IxBusController::addFileToFolder([
                    'config'   => $aArgs['config'],
                    'folderId' => $folderId,
                    'filePath' => $attachmentData['filePath'],
                    'fileName' => $attachmentData['fileName'],
                    'fileType' => 'annexe'
                ]);
                if (!empty($addedFile['error'])) {
                    return ['error' => $addedFile['message']];
                }
            }

            $transmittedFolder = IxBusController::transmitFolder(['config'=> $aArgs['config'], 'folderId' => $folderId]);
            if (!empty($transmittedFolder['error'])) {
                return ['error' => $transmittedFolder['message']];
            }
            
            $attachmentToFreeze[$collId][$resId] = $folderId;
        }

        // Send main document if in signature book
        $mainDocumentIntegration = json_decode($mainResource['integrations'], true);
        $externalId              = json_decode($mainResource['external_id'], true);
        if ($mainDocumentIntegration['inSignatureBook'] && empty($externalId['signatureBookId'])) {
            $resId  = $mainResource['res_id'];
            $collId = 'letterbox_coll';

            $adrInfo       = ConvertPdfController::getConvertedPdfById(['resId' => $resId, 'collId' => $collId]);
            $docserverInfo = DocserverModel::getByDocserverId(['docserverId' => $adrInfo['docserver_id']]);
            $filePath      = $docserverInfo['path_template'] . str_replace('#', '/', $adrInfo['path']) . $adrInfo['filename'];

            $docserverType = DocserverTypeModel::getById(['id' => $docserverInfo['docserver_type_id'], 'select' => ['fingerprint_mode']]);
            $fingerprint = StoreController::getFingerPrint(['filePath' => $filePath, 'mode' => $docserverType['fingerprint_mode']]);
            if ($adrInfo['fingerprint'] != $fingerprint) {
                return ['error' => 'Fingerprints do not match'];
            }

            $bodyData['nom']  = $mainResource['subject'];

            $createdFile = IxBusController::createFolder(['config' => $aArgs['config'], 'body' => $bodyData]);
            if (!empty($createdFile['error'])) {
                return ['error' => $createdFile['message']];
            }
            $folderId = $createdFile['folderId'];

            $addedFile = IxBusController::addFileToFolder([
                'config'   => $aArgs['config'],
                'folderId' => $folderId,
                'filePath' => $filePath,
                'fileName' => TextFormatModel::formatFilename(['filename' => $mainResource['subject'], 'maxLength' => 250]) . '.pdf',
                'fileType' => 'principal'
            ]);
            if (!empty($addedFile['error'])) {
                return ['error' => $addedFile['message']];
            }

            foreach ($attachmentsData as $attachmentData) {
                $addedFile = IxBusController::addFileToFolder([
                    'config' => $aArgs['config'],
                    'folderId' => $folderId,
                    'filePath' => $attachmentData['filePath'],
                    'fileName' => $attachmentData['fileName'],
                    'fileType' => 'annexe'
                ]);
                if (!empty($addedFile['error'])) {
                    return ['error' => $addedFile['message']];
                }
            }

            $transmittedFolder = IxBusController::transmitFolder(['config'=> $aArgs['config'], 'folderId' => $folderId]);
            if (!empty($transmittedFolder['error'])) {
                return ['error' => $transmittedFolder['message']];
            }
            
            $attachmentToFreeze[$collId][$resId] = $folderId;
        }

        return ['sended' => $attachmentToFreeze];
    }

    public static function createFolder(array $aArgs)
    {
        $curlResponse = CurlModel::exec([
            'url'     => rtrim($aArgs['config']['data']['url'], '/') . '/api/parapheur/v1/dossier',
            'headers' => ['content-type:application/json', 'IXBUS_API:' . $aArgs['config']['data']['tokenAPI']],
            'method'  => 'POST',
            'body'    => json_encode($aArgs['body'])
        ]);
        if (!empty($curlResponse['response']['error'])) {
            return ['error' => $curlResponse['response']['message']];
        }

        return ['folderId' => $curlResponse['response']['payload']['identifiant']];
    }

    public static function addFileToFolder(array $aArgs)
    {
        $curlResponse = CurlModel::exec([
            'url'           => rtrim($aArgs['config']['data']['url'], '/') . '/api/parapheur/v1/document/' . $aArgs['folderId'],
            'headers'       => ['IXBUS_API:' . $aArgs['config']['data']['tokenAPI']],
            'customRequest' => 'POST',
            'method'        => 'CUSTOM',
            'body'          => ['fichier' => CurlModel::makeCurlFile(['path' => $aArgs['filePath'], 'name' => $aArgs['fileName']]), 'type' => $aArgs['fileType']]
        ]);
        if (!empty($curlResponse['response']['error'])) {
            return ['error' => $curlResponse['response']['message']];
        }

        return [];
    }

    public static function transmitFolder(array $aArgs)
    {
        $curlResponse = CurlModel::exec([
            'url'     => rtrim($aArgs['config']['data']['url'], '/') . '/api/parapheur/v1/dossier/' . $aArgs['folderId'] . '/transmettre',
            'headers' => ['IXBUS_API:' . $aArgs['config']['data']['tokenAPI']],
            'method'  => 'POST'
        ]);
        if (!empty($curlResponse['response']['error'])) {
            return ['error' => $curlResponse['response']['message']];
        }

        return [];
    }

    public static function retrieveSignedMails($aArgs)
    {
        $version = $aArgs['version'];
        foreach ($aArgs['idsToRetrieve'][$version] as $resId => $value) {
            $folderData = IxbusController::getDossier(['config' => $aArgs['config'], 'folderId' => $value['external_id']]);

            if (in_array($folderData['data']['etat'], ['Refusé', 'Terminé'])) {
                $aArgs['idsToRetrieve'][$version][$resId]['status'] = $folderData['data']['etat'] == 'Refusé' ? 'refused' : 'validated';
                $signedDocument = IxbusController::getDocument(['config' => $aArgs['config'], 'documentId' => $folderData['data']['documents']['principal']['identifiant']]);
                $aArgs['idsToRetrieve'][$version][$resId]['format']      = 'pdf';
                $aArgs['idsToRetrieve'][$version][$resId]['encodedFile'] = $signedDocument['encodedDocument'];
                if (!empty($folderData['data']['detailEtat'])) {
                    $aArgs['idsToRetrieve'][$version][$resId]['notes'][] = ['content' => $folderData['data']['detailEtat']];
                }
            } else {
                unset($aArgs['idsToRetrieve'][$version][$resId]);
            }
        }

        // retourner seulement les mails récupérés (validés ou refusé)
        return $aArgs['idsToRetrieve'];
    }

    public static function getDossier($aArgs)
    {
        $curlResponse = CurlModel::exec([
            'url'     => rtrim($aArgs['config']['data']['url'], '/') . '/api/parapheur/v1/dossier/' . $aArgs['folderId'],
            'headers' => ['content-type:application/json', 'IXBUS_API:' . $aArgs['config']['data']['tokenAPI']],
            'method'  => 'GET'
        ]);
        if (!empty($curlResponse['response']['error'])) {
            return ['error' => $curlResponse['response']['message']];
        }

        return ['data' => $curlResponse['response']['payload']];
    }

    public static function getDocument($aArgs)
    {
        $curlResponse = CurlModel::exec([
            'url'       => rtrim($aArgs['config']['data']['url'], '/') . '/api/parapheur/v1/document/contenu/' . $aArgs['documentId'],
            'headers'   => [
                'Accept: application/zip',
                'content-type:application/json',
                'IXBUS_API:' . $aArgs['config']['data']['tokenAPI']],
            'method'    => 'GET'
        ]);

        return ['encodedDocument' => base64_encode($curlResponse['response'])];
    }
}
