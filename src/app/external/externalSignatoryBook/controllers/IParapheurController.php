<?php

/**
 * Copyright Maarch since 2008 under licence GPLv3.
 * See LICENCE.txt file at the root folder for more details.
 * This file is part of Maarch software.
 *
 */

/**
 * @brief iParapheur Controller
 * @author nathan.cheval@edissyum.com
 * @author dev@maarch.org
 */

namespace ExternalSignatoryBook\controllers;

use Attachment\models\AttachmentModel;
use Attachment\models\AttachmentTypeModel;
use Convert\controllers\ConvertPdfController;
use Convert\models\AdrModel;
use Docserver\models\DocserverModel;
use Docserver\models\DocserverTypeModel;
use Entity\models\ListInstanceModel;
use Resource\controllers\StoreController;
use Resource\models\ResModel;
use SrcCore\models\CurlModel;
use SrcCore\models\DatabaseModel;
use SrcCore\models\TextFormatModel;
use User\models\UserModel;

/**
    * @codeCoverageIgnore
*/
class IParapheurController
{
    public static function returnCurl($xmlPostString, $config)
    {
        $option = [
            CURLOPT_SSLCERT         => $config['data']['certPath'],
            CURLOPT_SSLCERTTYPE     => $config['data']['certType'],
            CURLOPT_SSL_VERIFYPEER  => 'false',
            CURLOPT_USERPWD         => $config['data']['userId'] . ':' . $config['data']['password'],
        ];
        if (!empty($config['data']['certPass'])) {
            unset($option[CURLOPT_SSL_VERIFYPEER]);
            $option[CURLOPT_SSLCERTPASSWD] = $config['data']['certPass'];
        }

        $curlReturn = CurlModel::execSOAP([
            'xmlPostString' => $xmlPostString,
            'url'           => $config['data']['url'],
            'options'       => $option,
            'delete_header' => true
        ]);

        return $curlReturn;
    }

    public static function sendDatas($aArgs)
    {
        $config = $aArgs['config'];
        $signatory = DatabaseModel::select([
            'select'    => ['item_id'],
            'table'     => ['listinstance', ],
            'where'     => ['res_id = ?', 'item_mode = ?', 'process_date is null'],
            'data'      => [$aArgs['resIdMaster'], 'sign']
        ])[0];

        if (!empty($signatory['item_id'])) {
            $user = UserModel::getById(['id' => $signatory['item_id'], 'select' => ['user_id']]);
        }
        $sousType = IParapheurController::getSousType(['config' => $config, 'sousType' => $user['user_id']]);
        if (!empty($sousType['error'])) {
            return ['error' => $sousType['error']];
        }

        $type     = IParapheurController::getType(['config' => $config]);
        if (!empty($type['error'])) {
            return ['error' => $type['error']];
        }
        return IParapheurController::upload(['config' => $config, 'resIdMaster' => $aArgs['resIdMaster'], 'sousType' => $sousType ]);
    }

    public static function upload($aArgs)
    {
        $sousType       = $aArgs['sousType'];

        // Retrieve the annexes of the attachment to sign (other attachments and the original document)
        $annexes = [];
        $annexes['letterbox'] = ResModel::get([
            'select' => ['res_id', 'path', 'filename', 'docserver_id', 'format', 'category_id', 'external_id', 'integrations', 'subject'],
            'where'  => ['res_id = ?'],
            'data'   => [$aArgs['resIdMaster']]
        ]);

        if (!empty($annexes['letterbox'][0]['docserver_id'])) {
            $adrMainInfo = ConvertPdfController::getConvertedPdfById(['resId' => $aArgs['resIdMaster'], 'collId' => 'letterbox_coll']);
            $letterboxPath = DocserverModel::getByDocserverId(['docserverId' => $adrMainInfo['docserver_id'], 'select' => ['path_template']]);
            $annexes['letterbox'][0]['filePath'] = $letterboxPath['path_template'] . str_replace('#', '/', $adrMainInfo['path']) . $adrMainInfo['filename'];
        }

        $attachments = AttachmentModel::get([
            'select' => ['res_id', 'docserver_id', 'path', 'filename', 'format', 'attachment_type', 'fingerprint', 'title'],
            'where'  => ['res_id_master = ?', 'attachment_type not in (?)', "status NOT IN ('DEL','OBS', 'FRZ', 'TMP', 'SEND_MASS')", "in_signature_book = 'true'"],
            'data'   => [$aArgs['resIdMaster'], ['signed_response']]
        ]);

        $attachmentTypes = AttachmentTypeModel::get(['select' => ['type_id', 'signable']]);
        $attachmentTypes = array_column($attachmentTypes, 'signable', 'type_id');
        foreach ($attachments as $key => $value) {
            if (!$attachmentTypes[$value['attachment_type']]) {
                $adrInfo              = AdrModel::getConvertedDocumentById(['resId' => $value['res_id'], 'collId' => 'attachments_coll', 'type' => 'PDF']);
                $annexeAttachmentPath = DocserverModel::getByDocserverId(['docserverId' => $adrInfo['docserver_id'], 'select' => ['path_template']]);
                $value['filePath']    = $annexeAttachmentPath['path_template'] . str_replace('#', DIRECTORY_SEPARATOR, $adrInfo['path']) . $adrInfo['filename'];

                $docserverType = DocserverTypeModel::getById(['id' => $annexeAttachmentPath['docserver_type_id'], 'select' => ['fingerprint_mode']]);
                $fingerprint = StoreController::getFingerPrint(['filePath' => $value['filePath'], 'mode' => $docserverType['fingerprint_mode']]);
                if ($value['fingerprint'] != $fingerprint) {
                    return ['error' => 'Fingerprints do not match'];
                }

                unset($attachments[$key]);
                $annexes['attachments'][] = $value;
            }
        }
        // END annexes

        $attachmentToFreeze = [];
        foreach ($attachments as $attachment) {
            $resId     = $attachment['res_id'];
            $title     = $attachment['title'];
            $collId    = 'attachments_coll';
            $dossierId = $resId . '_' . rand(0001, 9999);

            $response = IParapheurController::uploadFile([
                'resId'        => $resId,
                'collId'       => $collId,
                'resIdMaster'  => $aArgs['resIdMaster'],
                'annexes'      => $annexes,
                'sousType'     => $sousType,
                'config'       => $aArgs['config'],
                'dossierId'    => $dossierId,
                'title'        => $title
            ]);

            if (!empty($response['error'])) {
                return $response;
            } else {
                $attachmentToFreeze[$collId][$resId] = $dossierId;
            }
        }

        // Send main document if in signature book
        if (!empty($annexes['letterbox'][0])) {
            $mainDocumentIntegration = json_decode($annexes['letterbox'][0]['integrations'], true);
            $externalId              = json_decode($annexes['letterbox'][0]['external_id'], true);
            if ($mainDocumentIntegration['inSignatureBook'] && empty($externalId['signatureBookId'])) {
                $resId     = $annexes['letterbox'][0]['res_id'];
                $title     = $annexes['letterbox'][0]['subject'];
                $collId    = 'letterbox_coll';
                $dossierId = $resId . '_' . rand(0001, 9999);
                unset($annexes['letterbox']);
    
                $response = IParapheurController::uploadFile([
                    'resId'        => $resId,
                    'collId'       => $collId,
                    'resIdMaster'  => $aArgs['resIdMaster'],
                    'annexes'      => $annexes,
                    'sousType'     => $sousType,
                    'config'       => $aArgs['config'],
                    'dossierId'    => $dossierId,
                    'title'        => $title
                ]);
    
                if (!empty($response['error'])) {
                    return $response;
                } else {
                    $attachmentToFreeze[$collId][$resId] = $dossierId;
                }
            }
        }
        return ['sended' => $attachmentToFreeze];
    }

    public static function uploadFile($aArgs)
    {
        $dossierId = $aArgs['dossierId'];

        $adrInfo = ConvertPdfController::getConvertedPdfById(['resId' => $aArgs['resId'], 'collId' => $aArgs['collId']]);
        if (empty($adrInfo['docserver_id']) || strtolower(pathinfo($adrInfo['filename'], PATHINFO_EXTENSION)) != 'pdf') {
            return ['error' => 'Document ' . $aArgs['resIdMaster'] . ' is not converted in pdf'];
        }
        $attachmentPath     = DocserverModel::getByDocserverId(['docserverId' => $adrInfo['docserver_id'], 'select' => ['path_template']]);
        $attachmentFilePath = $attachmentPath['path_template'] . str_replace('#', '/', $adrInfo['path']) . $adrInfo['filename'];
        $dossierTitre       = 'Courrier : '. TextFormatModel::formatFilename(['filename' => $aArgs['title'], 'maxLength' => 250]) .' Référence : '. $aArgs['resId'];

        $mainResource = ResModel::getById(['resId' => $aArgs['resIdMaster'], 'select' => ['process_limit_date']]);
        if (empty($mainResource['process_limit_date'])) {
            $processLimitDate = $mainResource['process_limit_date'] = date('Y-m-d', strtotime(date("Y-m-d"). ' + 14 days'));
        } else {
            $processLimitDateTmp = explode(" ", $mainResource['process_limit_date']);
            $processLimitDate = $processLimitDateTmp[0];
        }

        $b64Attachment = base64_encode(file_get_contents($attachmentFilePath));
        
        if (!empty($aArgs['annexes']['letterbox'][0]['filePath'])) {
            $annexLetterboxMimeType = mime_content_type($aArgs['annexes']['letterbox'][0]['filePath']);
            if ($annexLetterboxMimeType) {
                $b64AnnexesLetterbox = base64_encode(file_get_contents($aArgs['annexes']['letterbox'][0]['filePath']));
                $annexesXmlPostString = '<ns:DocAnnexe>
                                    <ns:nom>Fichier original</ns:nom>
                                    <ns:fichier xm:contentType="' . $annexLetterboxMimeType . '">' . $b64AnnexesLetterbox . '</ns:fichier>
                                    <ns:mimetype>' . $annexLetterboxMimeType . '</ns:mimetype>
                                    <ns:encoding>utf-8</ns:encoding>
                                </ns:DocAnnexe>';
            }
        }
        if (!empty($aArgs['annexes']['attachments'])) {
            for ($j = 0; $j < count($aArgs['annexes']['attachments']); $j++) {
                $b64AnnexesAttachment = base64_encode(file_get_contents($aArgs['annexes']['attachments'][$j]['filePath']));
                $annexesXmlPostString .= '<ns:DocAnnexe> 
                                <ns:nom>PJ_' . ($j + 1) . '</ns:nom> 
                                <ns:fichier xm:contentType="application/pdf">' . $b64AnnexesAttachment . '</ns:fichier> 
                                <ns:mimetype>application/pdf</ns:mimetype> 
                                <ns:encoding>utf-8</ns:encoding>
                            </ns:DocAnnexe>';
            }
        }
        $xmlPostString = '<?xml version="1.0" encoding="utf-8"?>
                            <soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ns="http://www.adullact.org/spring-ws/iparapheur/1.0" xmlns:xm="http://www.w3.org/2005/05/xmlmime">
                               <soapenv:Header/>
                               <soapenv:Body>
                                  <ns:CreerDossierRequest>
                                     <ns:TypeTechnique>' . $aArgs['config']['data']['defaultType'] . '</ns:TypeTechnique>
                                     <ns:SousType>' . $aArgs['sousType'] . '</ns:SousType>
                                     <ns:DossierID>' . $dossierId . '</ns:DossierID>
                                     <ns:DossierTitre>' . $dossierTitre . '_' . $dossierId . '</ns:DossierTitre>
                                     <ns:DocumentPrincipal xm:contentType="application/pdf">' . $b64Attachment . '</ns:DocumentPrincipal>
                                     <ns:DocumentsSupplementaires></ns:DocumentsSupplementaires>
                                     <ns:DocumentsAnnexes>' . $annexesXmlPostString . '</ns:DocumentsAnnexes>
                                     <ns:MetaData>
                                        
                                     </ns:MetaData>
                                     <ns:AnnotationPublique></ns:AnnotationPublique>
                                     <ns:AnnotationPrivee></ns:AnnotationPrivee>
                                     <ns:Visibilite>CONFIDENTIEL</ns:Visibilite>
                                     <ns:DateLimite>' . $processLimitDate . '</ns:DateLimite>
                                  </ns:CreerDossierRequest>
                               </soapenv:Body>
                            </soapenv:Envelope>';

        $curlReturn = IParapheurController::returnCurl($xmlPostString, $aArgs['config']);

        if (!empty($curlReturn['error'])) {
            return ['error' => $curlReturn['error']];
        }
        $response = $curlReturn['response']->children('http://schemas.xmlsoap.org/soap/envelope/')->Body->children('http://www.adullact.org/spring-ws/iparapheur/1.0')->CreerDossierResponse[0];

        if ($response->MessageRetour->codeRetour == $aArgs['config']['data']['errorCode'] || $curlReturn['infos']['http_code'] >= 500) {
            return ['error' => '[' . $response->MessageRetour->severite . ']' . $response->MessageRetour->message];
        }

        IParapheurController::processVisaWorkflow(['res_id_master' => $aArgs['resIdMaster'], 'processSignatory' => false]);
        return ['success' => $dossierId];
    }

    public static function download($aArgs)
    {
        $xmlPostString = '<?xml version="1.0" encoding="utf-8"?>
            <soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ns="http://www.adullact.org/spring-ws/iparapheur/1.0">
               <soapenv:Header/>
               <soapenv:Body>
                  <ns:GetDossierRequest>' . $aArgs['documentId'] . '</ns:GetDossierRequest>
               </soapenv:Body>
            </soapenv:Envelope>';

        $curlReturn = IParapheurController::returnCurl($xmlPostString, $aArgs['config']);

        $response = $curlReturn['response']->children('http://schemas.xmlsoap.org/soap/envelope/')->Body->children('http://www.adullact.org/spring-ws/iparapheur/1.0')->GetDossierResponse[0];
        if ($response->MessageRetour->codeRetour == $aArgs['config']['data']['errorCode']) {
            return ['error' => 'Error : [' . $response->MessageRetour->severite . ']' . $response->MessageRetour->message];
        } else {
            $returnedDocumentId = (string) $response->DossierID;
            if ($aArgs['documentId'] !== $returnedDocumentId) {
                return ['error' => 'documentId returned is incorrect'];
            } else {
                $b64FileContent = (string)$response->DocPrincipal;
                return ['b64FileContent' => $b64FileContent, 'documentId' => $returnedDocumentId];
            }
        }
    }

    public static function retrieveSignedMails($aArgs)
    {
        $version = $aArgs['version'];
        $aArgs['idsToRetrieve']['error'] = [$version => []];
        foreach ($aArgs['idsToRetrieve'][$version] as $resId => $value) {
            if (!empty($value['external_id'])) {
                $xmlPostString = '<?xml version="1.0" encoding="utf-8"?>
                <soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ns="http://www.adullact.org/spring-ws/iparapheur/1.0">
                    <soapenv:Header/> 
                    <soapenv:Body> 
                        <ns:GetHistoDossierRequest>' . $value['external_id'] . '</ns:GetHistoDossierRequest> 
                    </soapenv:Body> 
                </soapenv:Envelope>';

                $curlReturn = IParapheurController::returnCurl($xmlPostString, $aArgs['config']);

                if (!empty($curlReturn['error'])) {
                    $aArgs['idsToRetrieve']['error'][$version][$resId] = $curlReturn['error'];
                    unset($aArgs['idsToRetrieve'][$version][$resId]);
                    continue;
                }

                try {
                    if (is_bool($curlReturn['response']) === true) {
                        $aArgs['idsToRetrieve']['error'][$version][$resId] = 'Curl response is a boolean';
                        unset($aArgs['idsToRetrieve'][$version][$resId]);
                        continue;
                    }
                    $response = $curlReturn['response']->children('http://schemas.xmlsoap.org/soap/envelope/')->Body->children('http://www.adullact.org/spring-ws/iparapheur/1.0')->GetHistoDossierResponse[0];
                } catch (\Exception $e) {
                    $aArgs['idsToRetrieve']['error'][$version][$resId] = 'Exception : ' . $e->getMessage();
                    unset($aArgs['idsToRetrieve'][$version][$resId]);
                    continue;
                }

                if ($response->MessageRetour->codeRetour == $aArgs['config']['data']['errorCode']) {
                    $aArgs['idsToRetrieve']['error'][$version][$resId] = 'Error : [' . $response->MessageRetour->severite . ']' . $response->MessageRetour->message;
                    unset($aArgs['idsToRetrieve'][$version][$resId]);
                } else {
                    $noteContent = '';
                    foreach ($response->LogDossier as $res) {    // Loop on all steps of the documents (prepared, send to signature, signed etc...)
                        $status = $res->status;
                        if ($status == $aArgs['config']['data']['visaState'] || $status == $aArgs['config']['data']['signState']) {
                            $noteContent .= $res->nom . ' : ' . $res->annotation . PHP_EOL;

                            $response = IParapheurController::download([
                                'config'     => $aArgs['config'],
                                'documentId' => $value['external_id']
                            ]);
                            if (!empty($response['error'])) {
                                $aArgs['idsToRetrieve']['error'][$version][$resId] = $response['error'];
                                unset($aArgs['idsToRetrieve'][$version][$resId]);
                                continue 2;
                            }
                            $aArgs['idsToRetrieve'][$version][$resId]['status'] = 'validated';
                            $aArgs['idsToRetrieve'][$version][$resId]['format'] = 'pdf';
                            $aArgs['idsToRetrieve'][$version][$resId]['encodedFile'] = $response['b64FileContent'];
                            $aArgs['idsToRetrieve'][$version][$resId]['notes'][] = ['content' => $noteContent];
                            if ($status == $aArgs['config']['data']['signState']) {
                                IParapheurController::processVisaWorkflow(['res_id_master' => $value['res_id_master'], 'res_id' => $value['res_id'], 'processSignatory' => true]);
                                break;
                            }
                        } elseif ($status == $aArgs['config']['data']['refusedVisa'] || $status == $aArgs['config']['data']['refusedSign']) {
                            $noteContent .= $res->nom . ' : ' . $res->annotation . PHP_EOL;
                            $aArgs['idsToRetrieve'][$version][$resId]['status'] = 'refused';
                            $aArgs['idsToRetrieve'][$version][$resId]['notes'][] = ['content' => $noteContent];
                            break;
                        } else {
                            $aArgs['idsToRetrieve'][$version][$resId]['status'] = 'waiting';
                        }
                    }
                }
            } else {
                echo 'ExternalId is empty';
            }
        }
        
        return $aArgs['idsToRetrieve'];
    }

    public static function processVisaWorkflow($aArgs = [])
    {
        $resIdMaster = $aArgs['res_id_master'] ?? $aArgs['res_id'];

        $attachments = AttachmentModel::get(['select' => ['count(1)'], 'where' => ['res_id_master = ?', 'status = ?'], 'data' => [$resIdMaster, 'FRZ']]);
        if ((count($attachments) < 2 && $aArgs['processSignatory']) || !$aArgs['processSignatory']) {
            $visaWorkflow = ListInstanceModel::get([
                'select'  => ['listinstance_id', 'requested_signature'],
                'where'   => ['res_id = ?', 'difflist_type = ?', 'process_date IS NULL'],
                'data'    => [$resIdMaster, 'VISA_CIRCUIT'],
                'orderBY' => ['ORDER BY listinstance_id ASC']
            ]);
    
            if (!empty($visaWorkflow)) {
                foreach ($visaWorkflow as $listInstance) {
                    if ($listInstance['requested_signature']) {
                        // Stop to the first signatory user
                        if ($aArgs['processSignatory']) {
                            ListInstanceModel::update(['set' => ['signatory' => 'true', 'process_date' => 'CURRENT_TIMESTAMP'], 'where' => ['listinstance_id = ?'], 'data' => [$listInstance['listinstance_id']]]);
                        }
                        break;
                    }
                    ListInstanceModel::update(['set' => ['process_date' => 'CURRENT_TIMESTAMP'], 'where' => ['listinstance_id = ?'], 'data' => [$listInstance['listinstance_id']]]);
                }
            }
        }
    }

    public static function getType($aArgs)
    {
        $xmlPostString = '<?xml version="1.0" encoding="utf-8"?>
           <soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ns="http://www.adullact.org/spring-ws/iparapheur/1.0">
               <soapenv:Header/>
               <soapenv:Body>
                  <ns:GetListeTypesRequest></ns:GetListeTypesRequest>
               </soapenv:Body>
            </soapenv:Envelope>';

        $curlReturn = $curlReturn = IParapheurController::returnCurl($xmlPostString, $aArgs['config']);

        if (!empty($curlReturn['error'])) {
            return ['error' => $curlReturn['error']];
        }

        $response   = $curlReturn['response']->children('http://schemas.xmlsoap.org/soap/envelope/')->Body->children('http://www.adullact.org/spring-ws/iparapheur/1.0')->GetListeTypesResponse[0];

        $typeExist  = false;
        foreach ($response->TypeTechnique as $res) {
            if ($res == $aArgs['config']['data']['defaultType']) {
                $typeExist = true;
                break;
            }
        }
        if (!$typeExist) {
            return ['error' => 'Default Type does not exists'];
        } else {
            return true;
        }
    }

    public static function getSousType($aArgs)
    {
        $xmlPostString = '<?xml version="1.0" encoding="utf-8"?>
           <soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ns="http://www.adullact.org/spring-ws/iparapheur/1.0">
               <soapenv:Header/>
               <soapenv:Body>
                  <ns:GetListeSousTypesRequest>' . $aArgs['config']['data']['defaultType'] . '</ns:GetListeSousTypesRequest>
               </soapenv:Body>
            </soapenv:Envelope>';

        $curlReturn = $curlReturn = IParapheurController::returnCurl($xmlPostString, $aArgs['config']);

        if (!empty($curlReturn['error'])) {
            return ['error' => $curlReturn['error']];
        }

        $response = $curlReturn['response']->children('http://schemas.xmlsoap.org/soap/envelope/')->Body->children('http://www.adullact.org/spring-ws/iparapheur/1.0')->GetListeSousTypesResponse[0];

        $subTypeExist = false;
        foreach ($response->SousType as $res) {
            if ($res == $aArgs['sousType']) {
                $subTypeExist = true;
                break;
            }
        }

        if (!$subTypeExist) {
            return $aArgs['config']['data']['defaultSousType'];
        } else {
            return $aArgs['sousType'];
        }
    }
}
