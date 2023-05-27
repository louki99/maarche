<?php

/**
* Copyright Maarch since 2008 under licence GPLv3.
* See LICENCE.txt file at the root folder for more details.
* This file is part of Maarch software.
*
*/

/**
* @brief XParaph Controller
* @author dev@maarch.org
*/

namespace ExternalSignatoryBook\controllers;

use Attachment\models\AttachmentModel;
use Convert\controllers\ConvertPdfController;
use Docserver\models\DocserverModel;
use Docserver\models\DocserverTypeModel;
use Exception;
use History\controllers\HistoryController;
use Resource\controllers\StoreController;
use setasign\Fpdi\Tcpdf\Fpdi;
use Slim\Psr7\Request;
use Smalot\PdfParser\Element\ElementArray;
use Smalot\PdfParser\Element\ElementMissing;
use Smalot\PdfParser\Element\ElementNull;
use Smalot\PdfParser\Element\ElementXRef;
use Smalot\PdfParser\Font;
use Smalot\PdfParser\Header;
use Smalot\PdfParser\Page;
use Smalot\PdfParser\Parser;
use Smalot\PdfParser\PDFObject;
use SrcCore\http\Response;
use SrcCore\models\CoreConfigModel;
use SrcCore\models\CurlModel;
use User\models\UserModel;

include_once('vendor/tinybutstrong/opentbs/tbs_plugin_opentbs.php');

/**
    * @codeCoverageIgnore
*/
class XParaphController
{
    public static function sendDatas($aArgs)
    {
        $attachments = AttachmentModel::get([
            'select'    => [
                'res_id', 'title', 'docserver_id', 'path', 'filename'],
            'where'     => ["res_id_master = ?", "attachment_type not in (?)", "status not in ('DEL', 'OBS', 'FRZ', 'TMP', 'SEND_MASS')", "in_signature_book = 'true'"],
            'data'      => [$aArgs['resIdMaster'], ['signed_response']]
        ]);

        $attachmentToFreeze = [];

        $userGeneric = [];
        if (isset($aArgs['config']['data']['userGeneric']->siret)) {
            if ($aArgs['config']['data']['userGeneric']->siret == $aArgs['info']['siret']) {
                $userGeneric = (array)$aArgs['config']['data']['userGeneric'];
            }
        } else {
            foreach ($aArgs['config']['data']['userGeneric'] as $userGenericXml) {
                if ($userGenericXml->siret == $aArgs['info']['siret']) {
                    $userGeneric = (array)$userGenericXml;
                    break;
                }
            }
        }

        if (empty($userGeneric)) {
            return ['error' => 'No user generic for this siret'];
        }

        foreach ($attachments as $value) {
            $resId      = $value['res_id'];
            $collId     = 'attachments_coll';
            
            $adrInfo       = ConvertPdfController::getConvertedPdfById(['resId' => $resId, 'collId' => $collId]);
            $docserverInfo = DocserverModel::getByDocserverId(['docserverId' => $adrInfo['docserver_id']]);
            $filePath      = $docserverInfo['path_template'] . str_replace('#', '/', $adrInfo['path']) . $adrInfo['filename'];

            $docserverType = DocserverTypeModel::getById(['id' => $docserverInfo['docserver_type_id'], 'select' => ['fingerprint_mode']]);
            $fingerprint = StoreController::getFingerPrint(['filePath' => $filePath, 'mode' => $docserverType['fingerprint_mode']]);
            if ($adrInfo['fingerprint'] != $fingerprint) {
                return ['error' => 'Fingerprints do not match'];
            }

            $documentToSend = XParaphController::replaceXParaphSignatureField(['pdf' => $filePath, 'attachmentInfo' => $value]);
            $filePath = $documentToSend['documentPath'];
            if ($documentToSend['remat']) {
                $pml = 1;
            } else {
                $pml = 0;
            }

            $filesize    = filesize($filePath);
            $fileContent = file_get_contents($filePath);
            
            $xmlStep = '';
            foreach ($aArgs['steps'] as $key => $step) {
                $order = $key + 1;
                $xmlStep .= '<EtapeDepot>
                                <user_siret xsi:type="xsd:string">'.$aArgs['info']['siret'].'</user_siret>
                                <user_login xsi:type="xsd:string">'.$step['login'].'</user_login>
                                <action xsi:type="xsd:string">'.$step['action'].'</action>
                                <contexte xsi:type="xsd:string">'.$step['contexte'].'</contexte>
                                <norejet xsi:type="xsd:string">0</norejet>
                                <ordre xsi:type="xsd:int">'.$order.'</ordre>
                            </EtapeDepot>';
            }

            $xmlPostString = '<?xml version="1.0" encoding="utf-8"?>
            <soapenv:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:urn="urn:parafwsdl" xmlns:soapenc="http://schemas.xmlsoap.org/soap/encoding/">
                <soapenv:Header/>
                <soapenv:Body>
                <urn:XPRF_Deposer soapenv:encodingStyle="http://schemas.xmlsoap.org/soap/encoding/">
                    <params xsi:type="urn:XPRF_Deposer_Param">
                        <reponse xsi:type="xsd:string">SOAP</reponse>
                        <siret xsi:type="xsd:string">'.$aArgs['info']['siret'].'</siret>
                        <login xsi:type="xsd:string">'.$aArgs['info']['login'].'</login>
                        <logingen xsi:type="xsd:string">'.$userGeneric['login'].'</logingen>
                        <password xsi:type="xsd:string">'.$userGeneric['password'].'</password>
                        <docutype xsi:type="xsd:string">'.$aArgs['config']['data']['docutype'].'</docutype>
                        <docustype xsi:type="xsd:string">'.$aArgs['config']['data']['docustype'].'</docustype>
                        <objet xsi:type="xsd:string">'.$value['title'].'</objet>
                        <contenu xsi:type="xsd:base64Binary">'.base64_encode($fileContent).'</contenu>
                        <nom xsi:type="xsd:string">'.$value['title'].'</nom>
                        <taille xsi:type="xsd:int">'.$filesize.'</taille>
                        <pml xsi:type="xsd:string">'.$pml.'</pml>
                        <avertir xsi:type="xsd:string">1</avertir>
                        <etapes xsi:type="urn:EtapeDepot" soapenc:arrayType="urn:EtapeDepotItem[]">'.$xmlStep.'</etapes>
                    </params>
                </urn:XPRF_Deposer>
                </soapenv:Body>
            </soapenv:Envelope>';

            $response = CurlModel::execSOAP([
                'soapAction'    => 'urn:parafwsdl#paraf',
                'url'           => $aArgs['config']['data']['url'],
                'xmlPostString' => $xmlPostString,
                'options'       => [CURLOPT_SSL_VERIFYPEER => false]
            ]);

            $isError = $response['response']->children('http://schemas.xmlsoap.org/soap/envelope/')->Body;
            if (!empty($isError->Fault[0])) {
                $error = $response['response']->children('http://schemas.xmlsoap.org/soap/envelope/')->Body->Fault[0]->children()->detail;
                return ['error' => (string)$error];
            } else {
                $depotId = $response['response']->children('SOAP-ENV', true)->Body->children('ns1', true)->XPRF_DeposerResponse->children()->return->children()->depotid;
                $attachmentToFreeze[$collId][$resId] = (string)$depotId;

                $aAttachment = AttachmentModel::getById([
                    'select'    => ['external_id'],
                    'id'        => $resId
                ]);
    
                $externalId = json_decode($aAttachment[0]['external_id'], true);
                $externalId['xparaphDepot'] = ['login' => $aArgs['info']['login'], 'siret' => $aArgs['info']['siret']];
        
                AttachmentModel::update([
                    'set'       => ['external_id' => json_encode($externalId)],
                    'where'     => ['res_id = ?'],
                    'data'      => [$resId]
                ]);
            }
        }

        return ['sended' => $attachmentToFreeze];
    }

    protected static function replaceXParaphSignatureField(array $aArgs)
    {
        $parser = new Parser();
        $pdf    = $parser->parseFile($aArgs['pdf']);
        $pages  = $pdf->getPages();

        $searchableArray = ["[xParaphSignature]"];
        $pageCount = 0;

        foreach ($pages as $page) {
            $pageCount++;
            foreach (XParaphController::getTextArrayWithCoordinates($page) as $text) {
                if (XParaphController::strposa($text['text'], $searchableArray) !== false) {
                    $detailText = '';
                    $originalYDetail = null;
                    foreach ($text['details'] as $detail) {
                        // Check if the complete text line has the wanted text
                        if (XParaphController::strposa($detail['text'], $searchableArray) !== false) {
                            $coordinates[] = [
                                'text' => trim($detail['text']),
                                'x'    => $detail['x'],
                                'y'    => $detail['y'],
                                'p'    => $pageCount,
                            ];
                            break;
                        } else {
                            // It is possible (the way PDF works) that the text is on the correct line but splitted into
                            // multi Tc objects, check if this is the case, if so return the first accurance of X and Y
                            $detailText .= $detail['text'];
                            if (is_null($originalYDetail)) {
                                $originalYDetail = $detail;
                            } elseif ($originalYDetail['y'] != $detail['y']) {
                                $originalYDetail = $detail;
                            }
                            if (XParaphController::strposa($detailText, $searchableArray) !== false) {
                                $coordinates[] = [
                                    'text' => trim($detailText),
                                    'x'    => $originalYDetail['x'],
                                    'y'    => $originalYDetail['y'],
                                    'p'    => $pageCount,
                                ];
                                break;
                            }
                        }
                    }
                }
            }
        }

        if (!empty($coordinates)) {
            $docserverInfo = DocserverModel::getByDocserverId(['docserverId' => $aArgs['attachmentInfo']['docserver_id']]);
            $filePath      = $docserverInfo['path_template'] . str_replace('#', '/', $aArgs['attachmentInfo']['path']) . $aArgs['attachmentInfo']['filename'];

            $tbs = new \clsTinyButStrong();
            $tbs->NoErr = true;
            $tbs->PlugIn(TBS_INSTALL, OPENTBS_PLUGIN);

            if (!empty($filePath)) {
                $pathInfo = pathinfo($filePath);
                $extension = $pathInfo['extension'];
            } else {
                $extension = 'unknow';
            }

            if (!empty($filePath)) {
                $tbs->LoadTemplate($filePath, OPENTBS_ALREADY_UTF8);
            }

            $tbs->MergeField('xParaphSignature', ' ');

            if (in_array($extension, ['odt', 'ods', 'odp', 'xlsx', 'pptx', 'docx', 'odf'])) {
                $tbs->Show(OPENTBS_STRING);
            } else {
                $tbs->Show(TBS_NOTHING);
            }

            $tmpPath = CoreConfigModel::getTmpPath();
            $filename = $tmpPath . $GLOBALS['login'] . '_' . rand() . '_xParaphSignature.';
            $pathFilename = $filename . $extension;
            file_put_contents($pathFilename, $tbs->Source);

            $documentConverted = ConvertPdfController::tmpConvert([
                'fullFilename' => $pathFilename,
            ]);
            unlink($pathFilename);
            $pdfToSend = $documentConverted['fullFilename'];
            
            $pdf = new Fpdi('P', 'pt');
            $pdf->setPrintHeader(false);
            $nbPages = $pdf->setSourceFile($pdfToSend);
            for ($i = 1; $i <= $nbPages; $i++) {
                $page = $pdf->importPage($i, 'CropBox');
                $size = $pdf->getTemplateSize($page);
                $pdf->AddPage($size['orientation'], $size);
                $pdf->useImportedPage($page);
                foreach ($coordinates as $key => $coordinate) {
                    if ($coordinate['p'] == $i) {
                        $signatureNb = $key + 1;
                        $pdf->SetXY($coordinate['x'], -$coordinate['y']-15);
                        $pdf->SetTextColor(255, 255, 255);
                        $pdf->Write(5, '[[[signature'.$signatureNb.']]]');
                    }
                }
            }

            $fileContent = $pdf->Output('', 'S');
            file_put_contents($filename . 'pdf', $fileContent);
            return ['remat' => true, 'documentPath' => $filename . 'pdf'];
        } else {
            return ['remat' => false, 'documentPath' => $aArgs['pdf']];
        }
    }

    private static function strposa(string $haystack, array $needle, int $offset = 0)
    {
        if (!is_array($needle)) {
            $needle = [$needle];
        }
        foreach ($needle as $query) {
            if (strpos($haystack, $query, $offset) !== false) {
                return true;
            }
        }
        return false;
    }

    public static function getWorkflow(Request $request, Response $response)
    {
        $data = $request->getQueryParams();
        foreach (['login', 'siret'] as $value) {
            if (empty($data[$value])) {
                return $response->withStatus(400)->withJson(['errors' => $value . ' is empty']);
            }
        }

        $loadedXml   = CoreConfigModel::getXmlLoaded(['path' => 'modules/visa/xml/remoteSignatoryBooks.xml']);
        $config      = [];
        $userGeneric = [];

        if (!empty($loadedXml)) {
            foreach ($loadedXml->signatoryBook as $value) {
                if ($value->id == "xParaph") {
                    $config['data'] = (array)$value;
                    foreach ($value->userGeneric as $userGenericXml) {
                        if ($userGenericXml->siret == (string)$data['siret']) {
                            $userGeneric = (array)$userGenericXml;
                            break;
                        }
                    }
                    break;
                }
            }
            if (empty($userGeneric)) {
                return $response->withStatus(403)->withJson(['error' => 'No user generic for this siret']);
            }
        } else {
            return $response->withStatus(403)->withJson(['errors' => 'xParaph is not enabled']);
        }

        $xmlPostString = '<?xml version="1.0" encoding="utf-8"?>
        <soapenv:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:urn="urn:parafwsdl">
            <soapenv:Header/>
            <soapenv:Body>
                <urn:XPRF_Initialisation_Deposer soapenv:encodingStyle="http://schemas.xmlsoap.org/soap/encoding/">
                    <params xsi:type="urn:XPRF_Initialisation_Deposer_Param">
                        <siret xsi:type="xsd:string">'.$data['siret'].'</siret>
                        <login xsi:type="xsd:string">'.$data['login'].'</login>
                        <logingen xsi:type="xsd:string">'.$userGeneric['login'].'</logingen>
                        <password xsi:type="xsd:string">'.$userGeneric['password'].'</password>
                        <action xsi:type="xsd:string">DETAIL</action>
                        <scenario xsi:type="xsd:string">' . $config['data']['docutype'] . '-' . $config['data']['docustype'].'</scenario>
                        <version xsi:type="xsd:string">2</version>
                    </params>
                </urn:XPRF_Initialisation_Deposer>
            </soapenv:Body>
        </soapenv:Envelope>';

        $curlResponse = CurlModel::execSOAP([
            'soapAction'    => 'urn:parafwsdl#paraf',
            'url'           => $config['data']['url'],
            'xmlPostString' => $xmlPostString,
            'options'       => [CURLOPT_SSL_VERIFYPEER => false]
        ]);

        $isError = $curlResponse['response']->children('http://schemas.xmlsoap.org/soap/envelope/')->Body;
        if (!empty($isError->Fault[0])) {
            $error = $curlResponse['response']->children('http://schemas.xmlsoap.org/soap/envelope/')->Body->Fault[0]->children()->detail;
            return $response->withStatus(403)->withJson(['errors' => $error]);
        } else {
            $details = $curlResponse['response']->children('SOAP-ENV', true)->Body->children('ns1', true)->XPRF_Initialisation_DeposerResponse->children()->return->children()->Retour_XML;
            $xmlData = simplexml_load_string($details);

            $userWorkflow = [];
            foreach ($xmlData->SCENARIO->AUTORISATIONS->VISEURS->VISEUR as $value) {
                $userWorkflow[(string)$value->ACTEUR_LOGIN]["userId"]      = (string)$value->ACTEUR_LOGIN;
                $userWorkflow[(string)$value->ACTEUR_LOGIN]["displayName"] = (string)$value->ACTEUR_NOM;
                $userWorkflow[(string)$value->ACTEUR_LOGIN]["roles"][]     = "visa";
            }
            foreach ($xmlData->SCENARIO->AUTORISATIONS->SIGNATAIRES->SIGNATAIRE as $value) {
                $userWorkflow[(string)$value->ACTEUR_LOGIN]["userId"]      = (string)$value->ACTEUR_LOGIN;
                $userWorkflow[(string)$value->ACTEUR_LOGIN]["displayName"] = (string)$value->ACTEUR_NOM;
                $userWorkflow[(string)$value->ACTEUR_LOGIN]["roles"][]     = "sign";
            }
            $workflow = [];
            foreach ($userWorkflow as $value) {
                $workflow[] = $value;
            }
    
            return $response->withJson(['workflow' => $workflow]);
        }
    }

    public static function retrieveSignedMails($aArgs)
    {
        $tmpPath = CoreConfigModel::getTmpPath();

        $version = $aArgs['version'];
        $depotsBySiret = [];
        foreach ($aArgs['idsToRetrieve'][$version] as $resId => $value) {
            $externalId = json_decode($value['xparaphdepot'], true);
            $depotsBySiret[$externalId['siret']][$value['external_id']] = ['resId' => $resId, 'login' => $externalId['login']];
        }

        foreach ($depotsBySiret as $siret => $depotids) {
            if (isset($aArgs['config']['data']['userGeneric']->siret)) {
                if ($aArgs['config']['data']['userGeneric']->siret == $siret) {
                    $userGeneric = (array)$aArgs['config']['data']['userGeneric'];
                }
            } else {
                foreach ($aArgs['config']['data']['userGeneric'] as $userGenericXml) {
                    if ($userGenericXml->siret == $siret) {
                        $userGeneric = (array)$userGenericXml;
                        break;
                    }
                }
            }

            if (!empty($depotids)) {
                $avancements = XParaphController::getAvancement(['config' => $aArgs['config'], 'depotsIds' => $depotids, 'userGeneric' => $userGeneric]);
            } else {
                unset($aArgs['idsToRetrieve'][$version]);
                continue;
            }

            foreach ($aArgs['idsToRetrieve'][$version] as $resId => $value) {
                $xParaphDepot = json_decode($value['xparaphdepot'], true);
                $avancement = $avancements[$value['external_id']];

                $state = XParaphController::getState(['avancement' => $avancement]);

                if ($state['id'] == 'refused') {
                    $aArgs['idsToRetrieve'][$version][$resId]['status'] = 'refused';
                    $aArgs['idsToRetrieve'][$version][$resId]['notes'][] = ['content' => $state['note']];

                    $processedFile = XParaphController::getFile(['config' => $aArgs['config'], 'depotId' => $value['external_id'], 'userGeneric' => $userGeneric, 'depotLogin' => $xParaphDepot['login']]);
                    if (!empty($processedFile['errors'])) {
                        unset($aArgs['idsToRetrieve'][$version][$resId]);
                        continue;
                    }
                    $file      = base64_decode($processedFile['zip']);
                    $unzipName = 'tmp_file_' .rand(). '_xParaph_' .rand();
                    $tmpName   = $unzipName . '.zip';
            
                    file_put_contents($tmpPath . $tmpName, $file);

                    $zip = new \ZipArchive();
                    $zip->open($tmpPath . $tmpName);
                    $zip->extractTo($tmpPath . $unzipName);

                    foreach (glob($tmpPath . $unzipName . '/*.xml') as $filename) {
                        $log = base64_encode(file_get_contents($filename));
                    }
                    unlink($tmpPath . $tmpName);

                    $aArgs['idsToRetrieve'][$version][$resId]['log']       = $log;
                    $aArgs['idsToRetrieve'][$version][$resId]['logFormat'] = 'xml';
                    $aArgs['idsToRetrieve'][$version][$resId]['logTitle']  = '[xParaph Log]';
                } elseif ($state['id'] == 'validateSignature' || $state['id'] == 'validateOnlyVisa') {
                    $processedFile = XParaphController::getFile(['config' => $aArgs['config'], 'depotId' => $value['external_id'], 'userGeneric' => $userGeneric, 'depotLogin' => $xParaphDepot['login']]);
                    if (!empty($processedFile['errors'])) {
                        unset($aArgs['idsToRetrieve'][$version][$resId]);
                        continue;
                    }
                    $aArgs['idsToRetrieve'][$version][$resId]['status'] = 'validated';
                    $aArgs['idsToRetrieve'][$version][$resId]['format'] = 'pdf';

                    $file      = base64_decode($processedFile['zip']);
                    $unzipName = 'tmp_file_' .rand(). '_xParaph_' .rand();
                    $tmpName   = $unzipName . '.zip';
            
                    file_put_contents($tmpPath . $tmpName, $file);

                    $zip = new \ZipArchive();
                    $zip->open($tmpPath . $tmpName);
                    $zip->extractTo($tmpPath . $unzipName);

                    foreach (glob($tmpPath . $unzipName . '/*.pdf') as $filename) {
                        $encodedFile = base64_encode(file_get_contents($filename));
                    }
                    foreach (glob($tmpPath . $unzipName . '/*.xml') as $filename) {
                        $log = base64_encode(file_get_contents($filename));
                    }
                    unlink($tmpPath . $tmpName);

                    $aArgs['idsToRetrieve'][$version][$resId]['encodedFile'] = $encodedFile;
                    $aArgs['idsToRetrieve'][$version][$resId]['notes'][] = ['content' => $state['note']];
                    if ($state['id'] == 'validateOnlyVisa') {
                        $aArgs['idsToRetrieve'][$version][$resId]['onlyVisa'] = true;
                    }
                    $aArgs['idsToRetrieve'][$version][$resId]['log']       = $log;
                    $aArgs['idsToRetrieve'][$version][$resId]['logFormat'] = 'xml';
                    $aArgs['idsToRetrieve'][$version][$resId]['logTitle']  = '[xParaph Log]';
                } else {
                    unset($aArgs['idsToRetrieve'][$version][$resId]);
                }
            }
        }

        // retourner seulement les mails récupérés (validés ou signés)
        return $aArgs['idsToRetrieve'];
    }

    public static function getState($aArgs)
    {
        // remove first step. Always deposit
        unset($aArgs['avancement'][0]);
        $state['id'] = 'validateOnlyVisa';
        $signature   = false;

        if (!empty($aArgs['avancement'])) {
            foreach ($aArgs['avancement'] as $step) {
                if ($step['etat'] == 'ACTIVE') {
                    $state['id'] = 'ACTIVE';
                    break;
                } elseif ($step['etat'] == 'KO') {
                    $state['id']   = 'refused';
                    $state['note'] = $step['note'];
                    break;
                }
                if ($step['typeEtape'] == 'SIGN') {
                    $signature = true;
                }
            }
        } else {
            $state['id'] = 'ACTIVE';
        }

        if ($signature) {
            $state['id']   = 'validateSignature';
        }
        $state['note'] = $step['note'];

        return $state;
    }

    public static function getAvancement($aArgs)
    {
        $depotIds = '';

        foreach ($aArgs['depotsIds'] as $key => $step) {
            $depotIds .= '<listDepotIds>'.$key.'</listDepotIds>';
        }

        // use $step['login'] because only need anyone who exist in xparaph

        $xmlPostString = '<?xml version="1.0" encoding="utf-8"?>
        <soapenv:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:urn="urn:parafwsdl" xmlns:soapenc="http://schemas.xmlsoap.org/soap/encoding/">
            <soapenv:Header/>
            <soapenv:Body>
                <urn:XPRF_AvancementDepot soapenv:encodingStyle="http://schemas.xmlsoap.org/soap/encoding/">
                    <params xsi:type="urn:XPRF_AvancementDepot_Param">
                        <siret xsi:type="xsd:string">'.$aArgs['userGeneric']['siret'].'</siret>
                        <login xsi:type="xsd:string">'.$step['login'].'</login>
                        <logingen xsi:type="xsd:string">'.$aArgs['userGeneric']['login'].'</logingen>
                        <password xsi:type="xsd:string">'.$aArgs['userGeneric']['password'].'</password>
                        <depotids xsi:type="urn:listDepotIds" soapenc:arrayType="xsd:string[]">' . $depotIds . '</depotids>
                        <withNote xsi:type="xsd:string">1</withNote>
                    </params>
                </urn:XPRF_AvancementDepot>
            </soapenv:Body>
        </soapenv:Envelope>';

        $curlResponse = CurlModel::execSOAP([
            'soapAction'    => 'urn:parafwsdl#paraf',
            'url'           => $aArgs['config']['data']['url'],
            'xmlPostString' => $xmlPostString,
            'options'       => [CURLOPT_SSL_VERIFYPEER => false]
        ]);

        $isError = $curlResponse['response']->children('http://schemas.xmlsoap.org/soap/envelope/')->Body;
        if (!empty($isError->Fault[0])) {
            $error = $curlResponse['response']->children('http://schemas.xmlsoap.org/soap/envelope/')->Body->Fault[0]->children()->detail;
            return ['errors' => $error];
        } else {
            $details = $curlResponse['response']->children('SOAP-ENV', true)->Body->children('ns1', true)->XPRF_AvancementDepotResponse->children()->return;
            return json_decode($details, true);
        }
    }

    public static function getFile($aArgs)
    {
        $xmlPostString = '<?xml version="1.0" encoding="utf-8"?>
        <soapenv:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:urn="urn:parafwsdl">
            <soapenv:Header/>
            <soapenv:Body>
                <urn:XPRF_getFiles soapenv:encodingStyle="http://schemas.xmlsoap.org/soap/encoding/">
                    <params xsi:type="urn:XPRF_getFiles_Param">
                        <siret xsi:type="xsd:string">'.$aArgs['userGeneric']['siret'].'</siret>
                        <login xsi:type="xsd:string">'.$aArgs['depotLogin'].'</login>
                        <logingen xsi:type="xsd:string">'.$aArgs['userGeneric']['login'].'</logingen>
                        <password xsi:type="xsd:string">'.$aArgs['userGeneric']['password'].'</password>
                        <depotid xsi:type="xsd:string">'.$aArgs['depotId'].'</depotid>
                    </params>
                </urn:XPRF_getFiles>
            </soapenv:Body>
        </soapenv:Envelope>';

        $curlResponse = CurlModel::execSOAP([
            'soapAction'    => 'urn:parafwsdl#paraf',
            'url'           => $aArgs['config']['data']['url'],
            'xmlPostString' => $xmlPostString,
            'options'       => [CURLOPT_SSL_VERIFYPEER => false]
        ]);

        $isError = $curlResponse['response']->children('http://schemas.xmlsoap.org/soap/envelope/')->Body;
        if (!empty($isError->Fault[0])) {
            $error = $curlResponse['response']->children('http://schemas.xmlsoap.org/soap/envelope/')->Body->Fault[0]->children()->detail;
            return ['errors' => $error];
        } else {
            $details = $curlResponse['response']->children('SOAP-ENV', true)->Body->children('ns1', true)->XPRF_getFilesResponse->children()->return;
            return json_decode($details, true);
        }
    }

    public function deleteXparaphAccount(Request $request, Response $response)
    {
        $data = $request->getQueryParams();
        foreach (['login', 'siret'] as $value) {
            if (empty($data[$value])) {
                return $response->withStatus(400)->withJson(['errors' => $value . ' is empty']);
            }
        }

        $user = UserModel::getById(['id' => $GLOBALS['id'], 'select' => ['external_id', 'id', 'firstname', 'lastname']]);
        if (empty($user)) {
            return $response->withStatus(400)->withJson(['errors' => 'User not found']);
        }

        $externalId = json_decode($user['external_id'], true);
        
        $accountFound = false;
        foreach ($externalId['xParaph'] as $key => $value) {
            if ($value['login'] == $data['login'] && $value['siret'] == $data['siret']) {
                unset($externalId['xParaph'][$key]);
                $externalId['xParaph'] = array_values($externalId['xParaph']);
                UserModel::updateExternalId(['id' => $user['id'], 'externalId' => json_encode($externalId)]);
                $accountFound = true;
                HistoryController::add([
                    'tableName'    => 'users',
                    'recordId'     => $GLOBALS['id'],
                    'eventType'    => 'UP',
                    'eventId'      => 'userModification',
                    'info'         => _USER_UPDATED . " {$user['firstname']} {$user['lastname']}. " . _XPARAPH_ACCOUNT_CREATED
                ]);

                break;
            }
        }

        if ($accountFound) {
            return $response->withStatus(204);
        } else {
            return $response->withStatus(400)->withJson(['errors' => 'Xparaph account not found']);
        }
    }

    public function createXparaphAccount(Request $request, Response $response)
    {
        $body = $request->getParsedBody();
        foreach (['login', 'siret'] as $value) {
            if (empty($body[$value])) {
                return $response->withStatus(400)->withJson(['errors' => $value . ' is empty']);
            }
        }

        $user = UserModel::getById(['id' => $GLOBALS['id'], 'select' => ['external_id', 'id', 'firstname', 'lastname']]);
        if (empty($user)) {
            return $response->withStatus(400)->withJson(['errors' => 'User not found']);
        }

        $externalId = json_decode($user['external_id'], true);
        $externalId['xParaph'][] = ["login" => $body['login'], "siret" => $body['siret']];
        
        UserModel::updateExternalId(['id' => $user['id'], 'externalId' => json_encode($externalId)]);

        HistoryController::add([
            'tableName'    => 'users',
            'recordId'     => $GLOBALS['id'],
            'eventType'    => 'UP',
            'eventId'      => 'userModification',
            'info'         => _USER_UPDATED . " {$user['firstname']} {$user['lastname']}. " . _XPARAPH_ACCOUNT_DELETED
        ]);

        return $response->withStatus(204);
    }

    /**
     * Copied the function used in the old library, to work with the new version of smalot/pdfparser
     *
     * If we can test XParaph, we should maybe refactor this code
     *
     * @param Page|null $page
     * @return array
     * @throws Exception
     */
    public static function getTextArrayWithCoordinates(Page $page = null)
    {
        if ($contents = $page->get('Contents')) {

            if ($contents instanceof ElementMissing) {
                return array();
            } elseif ($contents instanceof ElementNull) {
                return array();
            } elseif ($contents instanceof PDFObject) {
                $elements = $contents->getHeader()->getElements();

                if (is_numeric(key($elements))) {
                    $new_content = '';

                    /** @var PDFObject $element */
                    foreach ($elements as $element) {
                        if ($element instanceof ElementXRef) {
                            $new_content .= $element->getObject()->getContent();
                        } else {
                            $new_content .= $element->getContent();
                        }
                    }

                    $header   = new Header(array(), $page->getDocument());
                    $contents = new PDFObject($page->getDocument(), $header, $new_content);
                }
            } elseif ($contents instanceof ElementArray) {
                // Create a virtual global content.
                $new_content = '';

                /** @var PDFObject $content */
                foreach ($contents->getContent() as $content) {
                    $new_content .= $content->getContent() . "\n";
                }

                $header   = new Header(array(), $page->getDocument());
                $contents = new PDFObject($page->getDocument(), $header, $new_content);
            }

            return XParaphController::getTextArrayWithCoordinatesOnPdf($page, $contents);
        }

        return array();
    }

    /**
     * Copied the function used in the old library, to work with the new version of smalot/pdfparser
     *
     * If we can test XParaph, we should maybe refactor this code
     *
     * @param Page|null $page
     * @param PDFObject|null $pdfObject
     * @return array
     * @throws Exception
     */
    public static function getTextArrayWithCoordinatesOnPdf(Page $page = null, PDFObject $pdfObject = null)
    {
        $text                = array();
        $sections            = $pdfObject->getSectionsText($pdfObject->getContent());
        $current_font        = new Font($pdfObject->getDocument());

        $current_position_td = array('x' => false, 'y' => false);
        $current_position_tm = array('x' => false, 'y' => false);

        foreach ($sections as $section) {

            $commands = $pdfObject->getCommandsText($section);

            foreach ($commands as $command) {

                switch ($command[PDFObject::OPERATOR]) {
                    // set character spacing
                    case 'Tc':
                        break;

                    // move text current point
                    case 'Td':
                        $args = preg_split('/\s/s', $command[PDFObject::COMMAND]);
                        $y    = array_pop($args);
                        $x    = array_pop($args);

                        $current_position_tm = array('x' => $x, 'y' => $y);
                        break;

                    // move text current point and set leading
                    case 'TD':
                        break;

                    case 'Tf':
                        list($id,) = preg_split('/\s/s', $command[PDFObject::COMMAND]);
                        $id           = trim($id, '/');
                        $current_font = $page->getFont($id);
                        break;

                    case "'":
                    case 'Tj':
                    $command[PDFObject::COMMAND] = array($command);
                    case 'TJ':
                        // Skip if not previously defined, should never happened.
                        if (is_null($current_font)) {
                            // Fallback
                            // TODO : Improve
                            //$text[] = $command[\Smalot\PdfParser\PDFObject::COMMAND][0][\Smalot\PdfParser\PDFObject::COMMAND];
                            throw new Exception('Unknown font detected while decoding PDF string.');
                            continue;
                        }

                        $sub_text = $current_font->decodeText($command[PDFObject::COMMAND]);

                        if (isset($text[$current_position_tm['y']])) {
                            $text[$current_position_tm['y']]['text'] .= $sub_text;
                            $text[$current_position_tm['y']]['details'][] = [
                                'text' => $sub_text,
                                'x' =>  $current_position_tm['x'],
                                'y' =>  $current_position_tm['y']
                            ];
                        } else {
                            $text[$current_position_tm['y']] = [
                                'text' => $sub_text,
                                'x' =>  $current_position_tm['x'],
                                'y' =>  $current_position_tm['y'],
                                'details' => []
                            ];

                            $text[$current_position_tm['y']]['details'][] = [
                                'text' => $sub_text,
                                'x' =>  $current_position_tm['x'],
                                'y' =>  $current_position_tm['y']
                            ];
                        }
                        break;

                    // set leading
                    case 'TL':
                        break;

                    case 'Tm':
                        $args = preg_split('/\s/s', $command[PDFObject::COMMAND]);
                        $y    = array_pop($args);
                        $x    = array_pop($args);

                        $current_position_tm = array('x' => $x, 'y' => $y);
                        break;

                    // set super/subscripting text rise
                    case 'Ts':
                        break;

                    // set word spacing
                    case 'Tw':
                        break;

                    // set horizontal scaling
                    case 'Tz':
                        //$text .= "\n";
                        break;

                    // move to start of next line
                    case 'T*':
                        //$text .= "\n";
                        break;

                    case 'Da':
                        break;

                    case 'Do':
                        /* if (!is_null($page)) {
                            $args = preg_split('/\s/s', $command[self::COMMAND]);
                            $id   = trim(array_pop($args), '/ ');
                            if ($xobject = $page->getXObject($id)) {
                                $text[] = $xobject->getText($page);
                            }
                        } */
                        break;

                    case 'rg':
                    case 'RG':
                        break;

                    case 're':
                        break;

                    case 'co':
                        break;

                    case 'cs':
                        break;

                    case 'gs':
                        break;

                    case 'en':
                        break;

                    case 'sc':
                    case 'SC':
                        break;

                    case 'g':
                    case 'G':
                        break;

                    case 'V':
                        break;

                    case 'vo':
                    case 'Vo':
                        break;

                    default:
                }
            }
        }

        return $text;
    }
}
