<?php

/**
* Copyright Maarch since 2008 under licence GPLv3.
* See LICENCE.txt file at the root folder for more details.
* This file is part of Maarch software.
*
*/

namespace MaarchCourrier\Tests\app\resource;

use AcknowledgementReceipt\models\AcknowledgementReceiptModel;
use Attachment\controllers\AttachmentController;
use Docserver\controllers\DocserverController;
use Email\controllers\EmailController;
use MaarchCourrier\Tests\CourrierTestCase;
use Note\controllers\NoteController;
use Resource\controllers\FolderPrintController;
use Resource\models\ResModel;
use SrcCore\http\Response;
use User\models\UserModel;

class FolderPrintControllerTest extends CourrierTestCase
{
    private static $noteId = null;
    private static $attachmentId = null;
    private static $attachmentIdLinked = null;
    private static $emailId = null;
    private static $acknowledgementReceiptId = null;

    public function testGenerateFile()
    {
        $GLOBALS['login'] = 'bbain';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        // CREATE NOTE
        $noteController = new NoteController();

        $body = [
            'value'     => "Test d'ajout d'une note par php unit",
            'entities'  => ['COU', 'CAB'],
            'resId'     => $GLOBALS['resources'][0]
        ];
        $fullRequest = $this->createRequestWithBody('POST', $body);

        $response     = $noteController->create($fullRequest, new Response());
        $responseBody = json_decode((string)$response->getBody());

        self::$noteId = $responseBody->noteId;
        $this->assertIsInt(self::$noteId);

        //  CREATE ATTACHMENT
        $attachmentController = new AttachmentController();

        $fileContent = file_get_contents('test/unitTests/samples/test.txt');
        $encodedFile = base64_encode($fileContent);

        $body = [
            'title'         => 'Nulle pierre ne peut être polie sans friction, nul homme ne peut parfaire son expérience sans épreuve.',
            'type'          => 'response_project',
            'chrono'        => 'MAARCH/2019D/14',
            'resIdMaster'   => $GLOBALS['resources'][0],
            'encodedFile'   => $encodedFile,
            'format'        => 'txt',
            'recipientId'   => 1,
            'recipientType' => 'contact'
        ];

        $fullRequest = $this->createRequestWithBody('POST', $body);

        $response     = $attachmentController->create($fullRequest, new Response());
        $responseBody = json_decode((string)$response->getBody());
        self::$attachmentId = $responseBody->id;
        $this->assertIsInt(self::$attachmentId);

        $body = [
            'title'         => 'Nulle pierre ne peut être polie sans friction, nul homme ne peut parfaire son expérience sans épreuve.',
            'type'          => 'response_project',
            'chrono'        => 'MAARCH/2019D/15',
            'resIdMaster'   => $GLOBALS['resources'][1],
            'encodedFile'   => $encodedFile,
            'format'        => 'txt',
            'recipientId'   => 1,
            'recipientType' => 'contact'
        ];

        $fullRequest = $this->createRequestWithBody('POST', $body);

        $response     = $attachmentController->create($fullRequest, new Response());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertIsInt($responseBody['id']);
        self::$attachmentIdLinked = $responseBody['id'];

        // CREATE EMAIL
        self::$emailId = EmailController::createEmail([
            'userId'    => $GLOBALS['id'],
            'data'      => [
                'sender'        => ['email' => 'yourEmail@domain.com'],
                'recipients'    => ['dev@maarch.org'],
                'object'        => 'TU Folder Print Email',
                'body'          => 'TU Folder Print Email' . '<a href="#">'._CLICK_HERE.'</a>',
                'document'      => ['id' => $GLOBALS['resources'][0], 'isLinked' => true, 'original' => true],
                'isHtml'        => true,
                'status'        => 'WAITING'
            ]
        ]);

        // CREATE ACKNOWLEDGEMENT RECEIPT
        $storeResult = DocserverController::storeResourceOnDocServer([
            'collId'            => 'letterbox_coll',
            'docserverTypeId'   => 'ACKNOWLEDGEMENT_RECEIPTS',
            'encodedResource'   => $encodedFile,
            'format'            => 'txt'
        ]);

        $this->assertEmpty($storeResult['errors']);

        self::$acknowledgementReceiptId = AcknowledgementReceiptModel::create([
            'resId'             => $GLOBALS['resources'][0],
            'type'              => 'simple',
            'format'            => 'html',
            'userId'            => $GLOBALS['id'],
            'contactId'         => 1,
            'docserverId'       => 'ACKNOWLEDGEMENT_RECEIPTS',
            'path'              => $storeResult['directory'],
            'filename'          => $storeResult['file_destination_name'],
            'fingerprint'       => $storeResult['fingerPrint']
        ]);

        //  CREATE LINK
        ResModel::update(['set' => ['linked_resources' => json_encode([$GLOBALS['resources'][1], $GLOBALS['resources'][1] * 1000])], 'where' => ['res_id = ?'], 'data' => [$GLOBALS['resources'][0]]]);

        // GENERATE FOLDER PRINT

        $folderPrintController = new FolderPrintController();

        // Errors
        $body = [
            "resources" => [ ]
        ];

        $fullRequest = $this->createRequestWithBody('POST', $body);

        $response     = $folderPrintController->generateFile($fullRequest, new Response());
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Body resources is empty', $responseBody['errors']);

        $body = [
            "resources" => [
                [
                    "resId"                   => $GLOBALS['resources'][0],
                    "document"                => true,
                    "attachments"             => [self::$attachmentId],
                    "notes"                   => [self::$noteId],
                    "acknowledgementReceipts" => [],
                    "emails"                  => [],
                    "linkedResources"         => [$GLOBALS['resources'][1]],
                ], [
                    "resId"                   => $GLOBALS['resources'][0] * 1000,
                    "document"                => true,
                    "attachments"             => [self::$attachmentId],
                    "notes"                   => [self::$noteId],
                    "acknowledgementReceipts" => [],
                    "emails"                  => [],
                    "linkedResources"         => [$GLOBALS['resources'][1]],
                ]
            ]
        ];

        $fullRequest = $this->createRequestWithBody('POST', $body);

        $response     = $folderPrintController->generateFile($fullRequest, new Response());
        $this->assertSame(403, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Document out of perimeter', $responseBody['errors']);

        $body = [
            "resources" => [
                [
                    "resId"                   => $GLOBALS['resources'][0],
                    "document"                => false,
                    "attachments"             => [],
                    "notes"                   => [],
                    "acknowledgementReceipts" => [],
                    "emails"                  => [],
                ]
            ]
        ];

        $fullRequest = $this->createRequestWithBody('POST', $body);
        $response     = $folderPrintController->generateFile($fullRequest, new Response());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame('No document to merge', $responseBody['errors']);

        // Attachment errors
        $body = [
            "resources" => [
                [
                    "resId"                   => $GLOBALS['resources'][0],
                    "document"                => true,
                    "attachments"             => [self::$attachmentId, 'wrong format'],
                    "notes"                   => [self::$noteId],
                    "acknowledgementReceipts" => [],
                    "emails"                  => [],
                    "linkedResources"         => [
                        [
                            'resId'    => $GLOBALS['resources'][1],
                            'document' => true
                        ]
                    ],
                ]
            ]
        ];

        $fullRequest = $this->createRequestWithBody('POST', $body);

        $response     = $folderPrintController->generateFile($fullRequest, new Response());
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Attachment id is not an integer', $responseBody['errors']);

        $body = [
            "resources" => [
                [
                    "resId"                   => $GLOBALS['resources'][0],
                    "document"                => true,
                    "attachments"             => [self::$attachmentId * 1000],
                    "notes"                   => [self::$noteId],
                    "acknowledgementReceipts" => [],
                    "emails"                  => [],
                    "linkedResources"         => [
                        [
                            'resId'    => $GLOBALS['resources'][1],
                            'document' => true
                        ]
                    ],
                ]
            ]
        ];

        $fullRequest = $this->createRequestWithBody('POST', $body);

        $response     = $folderPrintController->generateFile($fullRequest, new Response());
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Attachment(s) not found', $responseBody['errors']);

        $body = [
            "resources" => [
                [
                    "resId"                   => $GLOBALS['resources'][1],
                    "document"                => true,
                    "attachments"             => [self::$attachmentId],
                    "notes"                   => [self::$noteId],
                    "acknowledgementReceipts" => [],
                    "emails"                  => [],
                    "linkedResources"         => [
                        [
                            'resId'    => $GLOBALS['resources'][1],
                            'document' => true
                        ]
                    ],
                ]
            ]
        ];

        $fullRequest = $this->createRequestWithBody('POST', $body);

        $response     = $folderPrintController->generateFile($fullRequest, new Response());
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Attachment not linked to resource', $responseBody['errors']);

        // Note errors
        $body = [
            "resources" => [
                [
                    "resId"                   => $GLOBALS['resources'][0],
                    "document"                => true,
                    "attachments"             => [self::$attachmentId],
                    "notes"                   => [self::$noteId, 'wrong format'],
                    "acknowledgementReceipts" => [],
                    "emails"                  => [],
                    "linkedResources"         => [
                        [
                            'resId'    => $GLOBALS['resources'][1],
                            'document' => true
                        ]
                    ],
                ]
            ]
        ];

        $fullRequest = $this->createRequestWithBody('POST', $body);

        $response     = $folderPrintController->generateFile($fullRequest, new Response());
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Note id is not an integer', $responseBody['errors']);

        $body = [
            "resources" => [
                [
                    "resId"                   => $GLOBALS['resources'][0],
                    "document"                => true,
                    "attachments"             => [self::$attachmentId],
                    "notes"                   => [self::$noteId, self::$noteId * 1000],
                    "acknowledgementReceipts" => [],
                    "emails"                  => [],
                    "linkedResources"         => [
                        [
                            'resId'    => $GLOBALS['resources'][1],
                            'document' => true
                        ]
                    ],
                ]
            ]
        ];

        $fullRequest = $this->createRequestWithBody('POST', $body);

        $response     = $folderPrintController->generateFile($fullRequest, new Response());
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Note(s) not found', $responseBody['errors']);

        $body = [
            "resources" => [
                [
                    "resId"                   => $GLOBALS['resources'][1],
                    "document"                => false,
                    "attachments"             => [],
                    "notes"                   => [self::$noteId],
                    "acknowledgementReceipts" => [],
                    "emails"                  => [],
                    "linkedResources"         => []
                ]
            ]
        ];

        $fullRequest = $this->createRequestWithBody('POST', $body);

        $response     = $folderPrintController->generateFile($fullRequest, new Response());
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Note not linked to resource', $responseBody['errors']);

        // Linked resources errors
        $body = [
            "resources" => [
                [
                    "resId"                   => $GLOBALS['resources'][0],
                    "document"                => true,
                    "attachments"             => [self::$attachmentId],
                    "notes"                   => [self::$noteId],
                    "acknowledgementReceipts" => [],
                    "emails"                  => [],
                    "linkedResources"         => ['wrong format']
                ]
            ]
        ];

        $fullRequest = $this->createRequestWithBody('POST', $body);

        $response     = $folderPrintController->generateFile($fullRequest, new Response());
        $this->assertSame(403, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('LinkedResources out of perimeter', $responseBody['errors']);

        $body = [
            "resources" => [
                [
                    "resId"                   => $GLOBALS['resources'][0],
                    "document"                => true,
                    "attachments"             => [self::$attachmentId],
                    "notes"                   => [self::$noteId],
                    "acknowledgementReceipts" => [],
                    "emails"                  => [],
                    "linkedResources"         => [$GLOBALS['resources'][2]]
                ]
            ]
        ];

        $fullRequest = $this->createRequestWithBody('POST', $body);

        $response     = $folderPrintController->generateFile($fullRequest, new Response());
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('LinkedResources resId is not linked to resource', $responseBody['errors']);

        $body = [
            "resources" => [
                [
                    "resId"                   => $GLOBALS['resources'][0],
                    "document"                => false,
                    "attachments"             => [],
                    "notes"                   => [],
                    "acknowledgementReceipts" => [],
                    "emails"                  => [],
                    "linkedResources"         => [$GLOBALS['resources'][1] * 1000]
                ]
            ]
        ];

        $fullRequest = $this->createRequestWithBody('POST', $body);

        $response     = $folderPrintController->generateFile($fullRequest, new Response());
        $this->assertSame(403, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('LinkedResources out of perimeter', $responseBody['errors']);

        $GLOBALS['login'] = 'superadmin';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        $body = [
            "resources" => [
                [
                    "resId"                   => $GLOBALS['resources'][0],
                    "document"                => true,
                    "attachments"             => [],
                    "notes"                   => [],
                    "acknowledgementReceipts" => [],
                    "emails"                  => [],
                    "linkedResources"         => [$GLOBALS['resources'][1] * 1000]
                ]
            ]
        ];

        $fullRequest = $this->createRequestWithBody('POST', $body);

        $response     = $folderPrintController->generateFile($fullRequest, new Response());
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('LinkedResources Document does not exist', $responseBody['errors']);

        $GLOBALS['login'] = 'bbain';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        // Linked resources attachments errors
        $body = [
            "resources" => [
                [
                    "resId"                   => $GLOBALS['resources'][0],
                    "document"                => true,
                    "attachments"             => [],
                    "notes"                   => [],
                    "acknowledgementReceipts" => [],
                    "emails"                  => [],
                    "linkedResources" => [],
                    "linkedResourcesAttachments" => ['wrong format'],
                ]
            ]
        ];

        $fullRequest = $this->createRequestWithBody('POST', $body);

        $response     = $folderPrintController->generateFile($fullRequest, new Response());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame('LinkedResources attachment id is not an integer', $responseBody['errors']);

        $body = [
            "resources" => [
                [
                    "resId"                   => $GLOBALS['resources'][0],
                    "document"                => true,
                    "attachments"             => [],
                    "notes"                   => [],
                    "acknowledgementReceipts" => [],
                    "emails"                  => [],
                    "linkedResources" => [],
                    "linkedResourcesAttachments" => [self::$attachmentId * 1000],
                ]
            ]
        ];

        $fullRequest = $this->createRequestWithBody('POST', $body);

        $response     = $folderPrintController->generateFile($fullRequest, new Response());
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('LinkedResources attachments not found', $responseBody['errors']);

        // Acknowledgement receipt errors
        $body = [
            "resources" => [
                [
                    "resId"                   => $GLOBALS['resources'][0],
                    "document"                => false,
                    "attachments"             => [],
                    "notes"                   => [],
                    "acknowledgementReceipts" => ['wrong format'],
                    "emails"                  => [],
                    "linkedResources"         => []
                ]
            ]
        ];

        $fullRequest = $this->createRequestWithBody('POST', $body);

        $response     = $folderPrintController->generateFile($fullRequest, new Response());
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Acknowledgement Receipt id is not an integer', $responseBody['errors']);

        $body = [
            "resources" => [
                [
                    "resId"                   => $GLOBALS['resources'][0],
                    "document"                => false,
                    "attachments"             => [],
                    "notes"                   => [],
                    "acknowledgementReceipts" => [self::$acknowledgementReceiptId, self::$acknowledgementReceiptId * 1000],
                    "emails"                  => [],
                    "linkedResources"         => []
                ]
            ]
        ];

        $fullRequest = $this->createRequestWithBody('POST', $body);

        $response     = $folderPrintController->generateFile($fullRequest, new Response());
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Acknowledgement Receipt(s) not found', $responseBody['errors']);

        $body = [
            "resources" => [
                [
                    "resId"                   => $GLOBALS['resources'][1],
                    "document"                => false,
                    "attachments"             => [],
                    "notes"                   => [],
                    "acknowledgementReceipts" => [self::$acknowledgementReceiptId],
                    "emails"                  => [],
                    "linkedResources"         => []
                ]
            ]
        ];

        $fullRequest = $this->createRequestWithBody('POST', $body);

        $response     = $folderPrintController->generateFile($fullRequest, new Response());
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Acknowledgement Receipt not linked to resource', $responseBody['errors']);

        // Email errors
        $body = [
            "resources" => [
                [
                    "resId"                   => $GLOBALS['resources'][0],
                    "document"                => false,
                    "attachments"             => [],
                    "notes"                   => [],
                    "acknowledgementReceipts" => [],
                    "emails"                  => ['wrong format'],
                    "linkedResources"         => []
                ]
            ]
        ];

        $fullRequest = $this->createRequestWithBody('POST', $body);

        $response     = $folderPrintController->generateFile($fullRequest, new Response());
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Email id is not an integer', $responseBody['errors']);

        $body = [
            "resources" => [
                [
                    "resId"                   => $GLOBALS['resources'][0],
                    "document"                => false,
                    "attachments"             => [],
                    "notes"                   => [],
                    "acknowledgementReceipts" => [],
                    "emails"                  => [self::$emailId, self::$emailId * 1000],
                    "linkedResources"         => []
                ]
            ]
        ];

        $fullRequest = $this->createRequestWithBody('POST', $body);

        $response     = $folderPrintController->generateFile($fullRequest, new Response());
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Email(s) not found', $responseBody['errors']);

        $body = [
            "resources" => [
                [
                    "resId"                   => $GLOBALS['resources'][1],
                    "document"                => false,
                    "attachments"             => [],
                    "notes"                   => [],
                    "acknowledgementReceipts" => [],
                    "emails"                  => [self::$emailId],
                    "linkedResources"         => []
                ]
            ]
        ];

        $fullRequest = $this->createRequestWithBody('POST', $body);

        $response     = $folderPrintController->generateFile($fullRequest, new Response());
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Email not linked to resource', $responseBody['errors']);

        // Success
        $body = [
            "resources" => [
                [
                    "resId"                      => $GLOBALS['resources'][0],
                    "document"                   => true,
                    "attachments"                => [self::$attachmentId],
                    "notes"                      => [self::$noteId],
                    "acknowledgementReceipts"    => [self::$acknowledgementReceiptId],
                    "emails"                     => [self::$emailId],
                    "linkedResources"            => [$GLOBALS['resources'][1]],
                    "linkedResourcesAttachments" => 'ALL',
                ]
            ],
            "summarySheet" => [
                [
                    "unit" => "qrcode",
                    "label" => ""
                ],
                [
                    "unit" => "primaryInformations",
                    "label" => "Informations primaires"
                ],
                [
                    "unit" => "senderRecipientInformations",
                    "label" => "Informations de traitement"
                ],
                [
                    "unit" => "secondaryInformations",
                    "label" => "Informations secondaires"
                ],
                [
                    "unit" => "diffusionList",
                    "label" => "Liste de diffusion"
                ],
                [
                    "unit" => "opinionWorkflow",
                    "label" => "Liste d'avis"
                ],
                [
                    "unit" => "visaWorkflow",
                    "label" => "Circuit de visa"
                ]
            ],
            "withSeparator" => true,
        ];

        $fullRequest = $this->createRequestWithBody('POST', $body);

        $response     = $folderPrintController->generateFile($fullRequest, new Response());
        $this->assertSame(200, $response->getStatusCode());

        $headers = $response->getHeaders();
        $this->assertSame('application/pdf', $headers['Content-Type'][0]);

        // GENERATE FOLDER PRINT 2

        $folderPrintController = new FolderPrintController();

        $body = [
            "resources" => [[
                "resId"                   => $GLOBALS['resources'][0],
                "document"                => true,
                "attachments"             => true,
                "notes"                   => true,
                "acknowledgementReceipts" => true,
                "emails"                  => true,
            ]],
            "summarySheet" => [
                [
                    "unit" => "qrcode",
                    "label" => ""
                ],
                [
                    "unit" => "primaryInformations",
                    "label" => "Informations primaires"
                ],
                [
                    "unit" => "senderRecipientInformations",
                    "label" => "Informations de traitement"
                ],
                [
                    "unit" => "secondaryInformations",
                    "label" => "Informations secondaires"
                ],
                [
                    "unit" => "diffusionList",
                    "label" => "Liste de diffusion"
                ],
                [
                    "unit" => "opinionWorkflow",
                    "label" => "Liste d'avis"
                ],
                [
                    "unit" => "visaWorkflow",
                    "label" => "Circuit de visa"
                ]
            ],
            "withSeparator" => true,
        ];

        $fullRequest = $this->createRequestWithBody('POST', $body);

        $response     = $folderPrintController->generateFile($fullRequest, new Response());
        $this->assertSame(200, $response->getStatusCode());

        $headers = $response->getHeaders();
        $this->assertSame('application/pdf', $headers['Content-Type'][0]);

        // DELETE NOTE
        $request = $this->createRequest('DELETE');

        $noteController = new NoteController();
        $response         = $noteController->delete($request, new Response(), ['id' => self::$noteId]);

        $this->assertSame(204, $response->getStatusCode());

        // DELETE ATTACHMENT
        $request = $this->createRequest('DELETE');

        $response     = $attachmentController->delete($request, new Response(), ['id' => self::$attachmentId]);
        $this->assertSame(204, $response->getStatusCode());

        $response     = $attachmentController->delete($request, new Response(), ['id' => self::$attachmentIdLinked]);
        $this->assertSame(204, $response->getStatusCode());

        $GLOBALS['login'] = 'superadmin';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];
    }
}
