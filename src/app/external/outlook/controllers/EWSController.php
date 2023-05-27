<?php

namespace Outlook\controllers;

use History\models\BatchHistoryModel;
use jamesiarmes\PhpEws\Client;
use jamesiarmes\PhpEws\Request\GetAttachmentType;
use jamesiarmes\PhpEws\Request\GetItemType;
use jamesiarmes\PhpEws\ArrayType\NonEmptyArrayOfBaseItemIdsType;
use jamesiarmes\PhpEws\ArrayType\NonEmptyArrayOfRequestAttachmentIdsType;
use jamesiarmes\PhpEws\Enumeration\DefaultShapeNamesType;
use jamesiarmes\PhpEws\Enumeration\ResponseClassType;
use jamesiarmes\PhpEws\Type\ItemResponseShapeType;
use jamesiarmes\PhpEws\Type\ItemIdType;
use jamesiarmes\PhpEws\Type\RequestAttachmentIdType;

use Convert\controllers\ConvertPdfController;
use Resource\controllers\StoreController;
use SrcCore\models\ValidatorModel;


class EWSController {

    public static function getAttachments(array $args)
    {
        ValidatorModel::notEmpty($args, ['attachmentIds', 'emailId', 'config', 'resId']);
        ValidatorModel::arrayType($args, ['attachmentIds', 'config']);
        ValidatorModel::stringType($args, ['emailId']);
        ValidatorModel::intVal($args, ['resId']);

        $client = new Client($args['config']['url'], $args['config']['mail'], $args['config']['password'], $args['config']['version']);

        // Some fixes on the message id from outlook js API, seen at :
        // https://blog.mastykarz.nl/office-365-unified-api-mail/
        $args['emailId'] = str_replace( '-', '/', $args['emailId'] );
        $args['emailId'] = str_replace( '_', '+', $args['emailId'] );

        // Build the get item request.
        $request = new GetItemType();
        $request->ItemShape = new ItemResponseShapeType();
        $request->ItemShape->BaseShape = DefaultShapeNamesType::ALL_PROPERTIES;
        $request->ItemIds = new NonEmptyArrayOfBaseItemIdsType();

        // Add the message id to the request.
        $item = new ItemIdType();
        $item->Id = $args['emailId'];
        $request->ItemIds->ItemId[] = $item;

        try {
            $response = $client->GetItem($request);
        } catch (\Exception $e) {
            if ($e->getCode() == 401) {
                return ['errors' => 'Outlook password is wrong', 'lang' => 'outlookPasswordWrong'];
            }
            BatchHistoryModel::create(['info' => 'Get outlook attachments error :' . $e->getMessage(), 'module_name' => 'outlook']);
            return ['errors' => 'Error when getting attachments', 'lang' => 'outlookGetAttachmentsImpossible'];
        }
        // Iterate over the results, printing any error messages or receiving attachments.
        $responseMessages = $response->ResponseMessages->GetItemResponseMessage;

        $errors = [];

        foreach ($responseMessages as $responseMessage) {
            // Make sure the request succeeded.
            if ($responseMessage->ResponseClass != ResponseClassType::SUCCESS) {
                $errors[] = 'Failed to get attachments list : '.$responseMessage->MessageText.' (' . $responseMessage->ResponseCode . ')';
                continue;
            }

            // Iterate over the messages, getting the attachments for each.
            $attachments = array();
            foreach ($responseMessage->Items->Message as $item) {
                // If there are no attachments for the item, move on to the next message.
                if (empty($item->Attachments)) {
                    continue;
                }

                // Iterate over the attachments for the message.
                foreach ($item->Attachments->FileAttachment as $attachment) {
                    // Filter only the attachments we want to get
                    if (in_array($attachment->AttachmentId->Id, $args['attachmentIds'])) {
                        $attachments[] = $attachment->AttachmentId->Id;
                    }
                }
            }

            if (empty($attachments)) {
                $errors[] = 'No attachments found';
                continue;
            }

            // Build the request to get the attachments.
            $request = new GetAttachmentType();
            $request->AttachmentIds = new NonEmptyArrayOfRequestAttachmentIdsType();

            // Iterate over the attachments for the message.
            foreach ( $attachments as $attachment_id ) {
                $id = new RequestAttachmentIdType();
                $id->Id = $attachment_id;
                $request->AttachmentIds->AttachmentId[] = $id;
            }

            $response = $client->GetAttachment($request);

            // Iterate over the response messages, printing any error messages or
            // saving the attachments.
            $attachmentResponseMessages = $response->ResponseMessages->GetAttachmentResponseMessage;
            foreach ($attachmentResponseMessages as $attachmentResponseMessage) {
                // Make sure the request succeeded.
                if ($attachmentResponseMessage->ResponseClass != ResponseClassType::SUCCESS) {
                    $errors[] = 'Failed to get attachment : '.$responseMessage->MessageText.' (' . $responseMessage->ResponseCode . ')';
                    continue;
                }

                // Iterate over the file attachments, saving each one.
                $attachments = $attachmentResponseMessage->Attachments->FileAttachment;
                foreach ($attachments as $attachment) {
                    $format = pathinfo($attachment->Name, PATHINFO_EXTENSION);
                    $store = StoreController::storeAttachment([
                        'encodedFile' => base64_encode($attachment->Content),
                        'title'       => $attachment->Name,
                        'type'        => $args['config']['attachmentType'],
                        'resIdMaster' => $args['resId'],
                        'format'      => $format
                    ]);
                    if (!empty($store['errors'])) {
                        $errors[] = 'Failed to store attachment : ' . $store['errors'];
                        continue;
                    }
                    ConvertPdfController::convert([
                        'resId'     => $store,
                        'collId'    => 'attachments_coll'
                    ]);
                }
            }
        }

        return $errors;
    }
}
