<?php

/**
* Copyright Maarch since 2008 under licence GPLv3.
* See LICENCE.txt file at the root folder for more details.
* This file is part of Maarch software.
*
*/

namespace MaarchCourrier\Tests\app\history;

use History\controllers\BatchHistoryController;
use History\controllers\HistoryController;
use MaarchCourrier\Tests\CourrierTestCase;
use Resource\models\ResModel;
use SrcCore\http\Response;
use SrcCore\models\DatabaseModel;
use User\models\UserModel;

class HistoryControllerTest extends CourrierTestCase
{
    public function testGetHistoryByUserId()
    {
        $request = $this->createRequest('GET');
        $history     = new HistoryController();

        $currentUser = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $response = $history->getByUserId($request, new Response(), ['userSerialId' => $currentUser['id']]);

        $responseBody = json_decode((string)$response->getBody());

        $this->assertNotEmpty($responseBody->histories);
    }

    public function testGetHistory()
    {
        $history     = new HistoryController();

        //  GET
        $request = $this->createRequest('GET');

        $userInfo = UserModel::getByLogin(['login' => 'superadmin', 'select' => ['id']]);

        $aArgs = [
            'startDate' => date('Y-m-d H:i:s', 1521100000),
            'endDate'   => date('Y-m-d H:i:s', time()),
            'users'     => [$userInfo['id']]
        ];
        $fullRequest = $request->withQueryParams($aArgs);

        $response = $history->get($fullRequest, new Response());
        $responseBody = json_decode((string)$response->getBody(), true);

        $this->assertIsArray($responseBody['history']);
        $this->assertNotEmpty($responseBody['history']);
    }

    public function testGetBatchHistory()
    {
        $batchHistory     = new BatchHistoryController();

        //  GET
        $request = $this->createRequest('GET');

        $aArgs = [
            'startDate' => date('Y-m-d H:i:s', 1521100000),
            'endDate'   => date('Y-m-d H:i:s', time())
        ];
        $fullRequest = $request->withQueryParams($aArgs);

        $response = $batchHistory->get($fullRequest, new Response());
        $responseBody = json_decode((string)$response->getBody(), true);

        $this->assertIsArray($responseBody['history']);
        $this->assertIsInt($responseBody['count']);
        $this->assertNotNull($responseBody['history']);
    }

    public function testGetBatchAvailableFilters()
    {
        $batchHistory = new BatchHistoryController();

        //  GET
        $request = $this->createRequest('GET');

        $response = $batchHistory->getAvailableFilters($request, new Response());
        $responseBody = json_decode((string)$response->getBody(), true);

        $this->assertIsArray($responseBody['modules']);
    }

    public function testGetAvailableFilters()
    {
        $historyController = new HistoryController();

        //  GET
        $request = $this->createRequest('GET');

        $response = $historyController->getAvailableFilters($request, new Response());
        $responseBody = json_decode((string)$response->getBody(), true);

        $this->assertIsArray($responseBody['actions']);
        $this->assertIsArray($responseBody['systemActions']);
        $this->assertIsArray($responseBody['users']);
    }

    public function testRealDelete()
    {
        $userInfo = UserModel::getByLogin(['login' => 'bbain', 'select' => ['id']]);

        $aResId = DatabaseModel::select([
            'select'    => ['res_id'],
            'table'     => ['res_letterbox'],
            'where'     => ['subject like ?','typist = ?', 'dest_user = ?'],
            'data'      => ['%Superman is alive - PHP unit', 19, $userInfo['id']],
            'order_by'  => ['res_id DESC']
        ]);

        $aNewResId = array_column($aResId, 'res_id');

        //  REAL DELETE
        DatabaseModel::delete([
            'table' => 'res_letterbox',
            'where' => ['res_id in (?)'],
            'data'  => [$aNewResId]
        ]);

        //  READ
        foreach ($aNewResId as $resId) {
            $res = ResModel::getById(['resId' => $resId, 'select' => ['*']]);
            $this->assertIsArray($res);
            $this->assertEmpty($res);
        }
    }
}
