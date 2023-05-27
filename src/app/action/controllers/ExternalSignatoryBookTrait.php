<?php

/**
* Copyright Maarch since 2008 under licence GPLv3.
* See LICENCE.txt file at the root folder for more details.
* This file is part of Maarch software.

* @brief   ExternalSignatoryBookTrait
* @author  dev <dev@maarch.org>
* @ingroup core
*/

namespace Action\controllers;

use Attachment\controllers\AttachmentController;
use Attachment\models\AttachmentModel;
use Convert\models\AdrModel;
use ExternalSignatoryBook\controllers\IxbusController;
use ExternalSignatoryBook\controllers\IParapheurController;
use ExternalSignatoryBook\controllers\FastParapheurController;
use ExternalSignatoryBook\controllers\MaarchParapheurController;
use ExternalSignatoryBook\controllers\XParaphController;
use Resource\models\ResModel;
use SrcCore\models\CoreConfigModel;
use SrcCore\models\ValidatorModel;

trait ExternalSignatoryBookTrait
{
    public static function sendExternalSignatoryBookAction(array $args)
    {
        ValidatorModel::notEmpty($args, ['resId']);
        ValidatorModel::intVal($args, ['resId']);
        ValidatorModel::arrayType($args, ['note']);

        $loadedXml = CoreConfigModel::getXmlLoaded(['path' => 'modules/visa/xml/remoteSignatoryBooks.xml']);
        $config = [];

        if (!empty($args['resources'])) {
            $hasMailing = AttachmentModel::get([
                'select' => ['res_id', 'status'],
                'where'  => ["res_id_master in (?)", "attachment_type not in (?)", "status = 'SEND_MASS'", "in_signature_book = 'true'"],
                'data'   => [$args['resources'], ['signed_response']]
            ]);
            if (count($args['resources']) > 1 || !empty($hasMailing)) {
                static $massData;
                if ($massData === null) {
                    $customId = CoreConfigModel::getCustomId();
                    $massData = [
                        'resources'     => [],
                        'successStatus' => $args['action']['parameters']['successStatus'],
                        'errorStatus'   => $args['action']['parameters']['errorStatus'],
                        'userId'        => $GLOBALS['id'],
                        'customId'      => $customId,
                        'action'        => 'sendExternalSignatoryBookAction'
                    ];
                }

                $massData['resources'][] = ['resId' => $args['resId'], 'data' => $args['data'], 'note' => $args['note']];

                return ['postscript' => 'src/app/action/scripts/MailingScript.php', 'args' => $massData];
            }
        }

        if (!empty($loadedXml)) {
            $config['id'] = (string)$loadedXml->signatoryBookEnabled;
            foreach ($loadedXml->signatoryBook as $value) {
                if ($value->id == $config['id']) {
                    $config['data'] = (array)$value;
                    break;
                }
            }

            if (!empty($config['id'])) {
                $attachments = AttachmentModel::get([
                    'select'    => [
                        'res_id', 'status'
                    ],
                    'where'     => ["res_id_master = ?", "attachment_type not in (?)", "status not in ('DEL', 'OBS', 'FRZ', 'TMP', 'SIGN')", "in_signature_book = 'true'"],
                    'data'      => [$args['resId'], ['signed_response']]
                ]);

                foreach ($attachments as $attachment) {
                    if ($attachment['status'] == 'SEND_MASS') {
                        $generated = AttachmentController::generateMailing(['id' => $attachment['res_id'], 'userId' => $GLOBALS['id']]);
                        if (!empty($generated['errors'])) {
                            return ['errors' => [$generated['errors']]];
                        }
                    }
                }
            }

            $integratedResource = ResModel::get([
                'select' => [1],
                'where'  => ['integrations->>\'inSignatureBook\' = \'true\'', 'external_id->>\'signatureBookId\' is null', 'res_id = ?'],
                'data'   => [$args['resId']]
            ]);
            $mainDocumentSigned = AdrModel::getConvertedDocumentById([
                'select' => [1],
                'resId'  => $args['resId'],
                'collId' => 'letterbox_coll',
                'type'   => 'SIGN'
            ]);
            if (!empty($mainDocumentSigned)) {
                $integratedResource = false;
            }

            if (empty($attachments) && empty($integratedResource) && $args['data']['objectSent'] == 'attachment') {
                $noAttachmentsResource = ResModel::getById(['resId' => $args['resId'], 'select' => ['alt_identifier']]);
                return ['errors' => ['No attachment for this mail : ' . $noAttachmentsResource['alt_identifier']]];
            }

            if ($config['id'] == 'maarchParapheur') {
                $sentInfo = MaarchParapheurController::sendDatas([
                    'config'      => $config,
                    'resIdMaster' => $args['resId'],
                    'objectSent'  => 'attachment',
                    'userId'      => $GLOBALS['login'],
                    'steps'       => $args['data']['steps'],
                    'note'        => $args['note']['content'] ?? null
                ]);
            } elseif ($config['id'] == 'fastParapheur') {
                $sentInfo = FastParapheurController::sendDatas([
                    'config'        => $config,
                    'resIdMaster'   => $args['resId'],
                    'steps'         => $args['data']['steps'] ?? [],
                    'workflowType'  => $args['data']['workflowType'] ?? null
                ]);
            } elseif ($config['id'] == 'iParapheur') {
                $sentInfo = IParapheurController::sendDatas([
                    'config'      => $config,
                    'resIdMaster' => $args['resId']
                ]);
            } elseif ($config['id'] == 'ixbus') {
                $sentInfo = IxbusController::sendDatas([
                    'config'        => $config,
                    'resIdMaster'   => $args['resId'],
                    'referent'      => $args['data']['ixbus']['userId'],
                    'natureId'      => $args['data']['ixbus']['nature'],
                    'messageModel'  => $args['data']['ixbus']['messageModel'],
                    'manSignature'  => $args['data']['ixbus']['signatureMode']
                ]);
            } elseif ($config['id'] == 'xParaph') {
                $sentInfo = XParaphController::sendDatas([
                    'config'      => $config,
                    'resIdMaster' => $args['resId'],
                    'info'        => $args['data']['info'],
                    'steps'       => $args['data']['steps'],
                ]);
            }
            if (!empty($sentInfo['error'])) {
                return ['errors' => [$sentInfo['error']]];
            } else {
                $attachmentToFreeze = $sentInfo['sended'];
            }

            $historyInfo = $sentInfo['historyInfos'];
        }

        if (!empty($attachmentToFreeze)) {
            if (!empty($attachmentToFreeze['letterbox_coll'])) {
                ResModel::update([
                    'postSet' => ['external_id' => "jsonb_set(external_id, '{signatureBookId}', '\"{$attachmentToFreeze['letterbox_coll'][$args['resId']]}\"'::text::jsonb)"],
                    'where'   => ['res_id = ?'],
                    'data'    => [$args['resId']]
                ]);
            }
            if (!empty($attachmentToFreeze['attachments_coll'])) {
                foreach ($attachmentToFreeze['attachments_coll'] as $resId => $externalId) {
                    AttachmentModel::freezeAttachment([
                        'resId' => $resId,
                        'externalId' => $externalId
                    ]);
                }
            }
        }

        return ['history' => $historyInfo];
    }
}
