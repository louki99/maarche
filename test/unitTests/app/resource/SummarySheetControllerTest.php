<?php

/**
* Copyright Maarch since 2008 under licence GPLv3.
* See LICENCE.txt file at the root folder for more details.
* This file is part of Maarch software.
*
*/

namespace MaarchCourrier\Tests\app\resource;

use Entity\models\ListInstanceModel;
use IndexingModel\models\IndexingModelFieldModel;
use MaarchCourrier\Tests\CourrierTestCase;
use Note\controllers\NoteController;
use Resource\controllers\SummarySheetController;
use SrcCore\http\Response;
use User\models\UserModel;

class SummarySheetControllerTest extends CourrierTestCase
{
    private static $noteId = null;

    public function testCreateList()
    {
        $GLOBALS['login'] = 'ddur';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        $noteController = new NoteController();

        $body = [
            'value'     => "Test d'ajout d'une note par php unit",
            'entities'  => ['COU', 'CAB', 'PJS'],
            'resId'     => $GLOBALS['resources'][0]
        ];
        $fullRequest = $this->createRequestWithBody('POST', $body);

        $response     = $noteController->create($fullRequest, new Response());
        $responseBody = json_decode((string)$response->getBody(), true);

        $this->assertIsInt($responseBody['noteId']);
        self::$noteId = $responseBody['noteId'];

        $GLOBALS['login'] = 'superadmin';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];
        $userInfo = UserModel::getByLogin(['login' => 'bbain', 'select' => ['id']]);

        IndexingModelFieldModel::create([
            'model_id'   => 1,
            'identifier' => 'indexingCustomField_4',
            'mandatory'  => 'false',
            'enabled'    => 'true',
            'unit'       => 'mail'
        ]);

        IndexingModelFieldModel::create([
            'model_id'   => 1,
            'identifier' => 'recipients',
            'mandatory'  => 'false',
            'enabled'    => 'true',
            'unit'       => 'mail'
        ]);

        ListInstanceModel::create([
            'res_id'          => $GLOBALS['resources'][0],
            'sequence'        => 0,
            'item_id'         => $userInfo['id'],
            'item_type'       => 'user_id',
            'item_mode'       => 'dest',
            'added_by_user'   => $GLOBALS['id'],
            'viewed'          => 0,
            'difflist_type'   => 'VISA_CIRCUIT'
        ]);

        ListInstanceModel::create([
            'res_id'          => $GLOBALS['resources'][0],
            'sequence'        => 0,
            'item_id'         => $userInfo['id'],
            'item_type'       => 'user_id',
            'item_mode'       => 'dest',
            'added_by_user'   => $GLOBALS['id'],
            'viewed'          => 0,
            'difflist_type'   => 'AVIS_CIRCUIT'
        ]);

        $GLOBALS['login'] = 'bbain';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];
        $myBasket = \Basket\models\BasketModel::getByBasketId(['basketId' => 'MyBasket', 'select' => ['id']]);

        $summarySheetController = new SummarySheetController();

        //  POST
        $body = [
            "resources" => $GLOBALS['resources'],
            "units" => [
                ['label' => 'Informations', 'unit' => 'primaryInformations'],
                ['label' => 'Informations Secondaires', 'unit' => 'secondaryInformations'],
                ["label" => "Informations de destination", "unit" => "senderRecipientInformations"],
                ['label' => 'Liste de diffusion', 'unit' => 'diffusionList'],
                ['label' => 'Ptit avis les potos.', 'unit' => 'freeField'],
                ['label' => 'Annotation(s)', 'unit' => 'notes'],
                ['label' => 'Circuit de visa', 'unit' => 'visaWorkflow'],
                ['label' => 'Circuit d\'avis', 'unit' => 'opinionWorkflow'],
                ['label' => 'Commentaires', 'unit' => 'freeField'],
                ['unit' => 'qrcode']
            ],
        ];
        $fullRequest = $this->createRequestWithBody('POST', $body);

        $response     = $summarySheetController->createList($fullRequest, new Response());
        $responseBody = json_decode((string)$response->getBody());

        $this->assertSame(null, $responseBody);


        //ERRORS
        unset($body['resources']);
        $fullRequest = $this->createRequestWithBody('POST', $body);
        $response = $summarySheetController->createList($fullRequest, new Response());
        $this->assertSame(403, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Resources is not set or empty', $responseBody['errors']);

        $GLOBALS['login'] = 'superadmin';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        IndexingModelFieldModel::delete([
            'where' => ['identifier in (?)', 'model_id = ?'],
            'data'  => [['indexingCustomField_4', 'recipients'], 1]
        ]);

        ListInstanceModel::delete([
            'where' => ['res_id = ?', 'difflist_type in (?)'],
            'data'  => [$GLOBALS['resources'][0], ['AVIS_CIRCUIT', 'VISA_CIRCUIT']]
        ]);
    }
}
