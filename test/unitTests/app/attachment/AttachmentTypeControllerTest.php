<?php

/**
* Copyright Maarch since 2008 under licence GPLv3.
* See LICENCE.txt file at the root folder for more details.
* This file is part of Maarch software.
*
*/

namespace MaarchCourrier\Tests\app\attachment;

use Attachment\controllers\AttachmentTypeController;
use Attachment\models\AttachmentTypeModel;
use SrcCore\http\Response;
use MaarchCourrier\Tests\CourrierTestCase;
use User\models\UserModel;

class AttachmentTypeControllerTest extends CourrierTestCase
{
    private static $id = null;

    public function testGetAttachmentTypes()
    {
        $attachmentTypeController = new AttachmentTypeController();

        $request = $this->createRequest('GET');

        $response = $attachmentTypeController->get($request, new Response());
        $response = json_decode((string)$response->getBody(), true);

        $this->assertNotNull($response['attachmentsTypes']);
        $this->assertIsArray($response['attachmentsTypes']);

        foreach ($response['attachmentsTypes'] as $value) {
            $this->assertIsInt($value['id']);
            $this->assertNotNull($value['typeId']);
            $this->assertNotNull($value['label']);
            $this->assertIsBool($value['visible']);
            $this->assertIsBool($value['emailLink']);
            $this->assertIsBool($value['signable']);
            $this->assertIsBool($value['signedByDefault']);
            $this->assertIsBool($value['chrono']);
            $this->assertIsBool($value['versionEnabled']);
            $this->assertIsBool($value['newVersionDefault']);
        }
    }

    public function testCreate()
    {
        $attachmentTypeController = new AttachmentTypeController();

        //  CREATE SUCCESS
        $body = [
            'typeId' => 'type_test',
            'label' => 'Type Test TU'
        ];
        $fullRequest = $this->createRequestWithBody('POST', $body);

        $response     = $attachmentTypeController->create($fullRequest, new Response());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertIsInt($responseBody['id']);
        self::$id = $responseBody['id'];

        // ERRORS
        $body = [];
        $fullRequest = $this->createRequestWithBody('POST', $body);

        $response     = $attachmentTypeController->create($fullRequest, new Response());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Body is not set or empty', $responseBody['errors']);
        $this->assertSame(400, $response->getStatusCode());

        $body = ['label' => 'Type TU'];
        $fullRequest = $this->createRequestWithBody('POST', $body);

        $response     = $attachmentTypeController->create($fullRequest, new Response());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Body typeId is empty or not a string', $responseBody['errors']);
        $this->assertSame(400, $response->getStatusCode());

        $body = ['typeId' => 'type_test'];
        $fullRequest = $this->createRequestWithBody('POST', $body);

        $response     = $attachmentTypeController->create($fullRequest, new Response());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Body label is empty or not a string', $responseBody['errors']);
        $this->assertSame(400, $response->getStatusCode());

        $body = [
            'typeId' => 'type_test',
            'label' => 'Type Test TU 2'
        ];
        $fullRequest = $this->createRequestWithBody('POST', $body);

        $response     = $attachmentTypeController->create($fullRequest, new Response());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Body typeId is already used by another type', $responseBody['errors']);
        $this->assertSame(400, $response->getStatusCode());

        $GLOBALS['login'] = 'bbain';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        $response     = $attachmentTypeController->create($fullRequest, new Response());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Service forbidden', $responseBody['errors']);
        $this->assertSame(403, $response->getStatusCode());

        $GLOBALS['login'] = 'superadmin';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];
    }

    public function testUpdate()
    {
        $attachmentTypeController = new AttachmentTypeController();

        //  UPDATE SUCCESS
        $body = [
            'typeId'            => 'type_test',
            'label'             => 'Type Test TU UP',
            'visible'           => true,
            'emailLink'         => false,
            'signable'          => true,
            'signedByDefault'   => false,
            'chrono'            => false,
            'versionEnabled'    => true,
            'newVersionDefault' => false,
            'icon'              => 'TU'
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $body);

        $response     = $attachmentTypeController->update($fullRequest, new Response(), ['id' => self::$id]);
        $this->assertSame(204, $response->getStatusCode());

        // ERRORS
        $body = [];
        $fullRequest = $this->createRequestWithBody('PUT', $body);

        $response     = $attachmentTypeController->update($fullRequest, new Response(), ['id' => self::$id]);
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Body is not set or empty', $responseBody['errors']);
        $this->assertSame(400, $response->getStatusCode());

        $body = ['typeId' => 'type_test'];
        $fullRequest = $this->createRequestWithBody('PUT', $body);

        $response     = $attachmentTypeController->update($fullRequest, new Response(), ['id' => self::$id]);
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Body label is empty or not a string', $responseBody['errors']);
        $this->assertSame(400, $response->getStatusCode());

        $body = ['label' => 'Type Test TU UP'];
        $fullRequest = $this->createRequestWithBody('PUT', $body);

        $response     = $attachmentTypeController->update($fullRequest, new Response(), ['id' => self::$id * 1000]);
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Attachment type not found or altered', $responseBody['errors']);
        $this->assertSame(400, $response->getStatusCode());

        $body = ['visible' => true, 'typeId' => 'signed_response', 'label' => 'Réponse signée UP'];
        $fullRequest = $this->createRequestWithBody('PUT', $body);

        $response     = $attachmentTypeController->update($fullRequest, new Response(), ['id' => 3]); // 3: 'signed_response' in data_fr.sql
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('This attachment type cannot be made visible', $responseBody['errors']);
        $this->assertSame(400, $response->getStatusCode());

        $body = ['signedByDefault' => false, 'typeId' => 'signed_response', 'label' => 'Réponse signée UP'];
        $fullRequest = $this->createRequestWithBody('PUT', $body);

        $response     = $attachmentTypeController->update($fullRequest, new Response(), ['id' => 3]);
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('This option cannot be disabled on this type', $responseBody['errors']);
        $this->assertSame(400, $response->getStatusCode());

        $GLOBALS['login'] = 'bbain';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        $response     = $attachmentTypeController->update($fullRequest, new Response(), ['id' => self::$id]);
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Service forbidden', $responseBody['errors']);
        $this->assertSame(403, $response->getStatusCode());

        $GLOBALS['login'] = 'superadmin';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];
    }

    public function testGetById()
    {
        $attachmentTypeController = new AttachmentTypeController();

        //  GET SUCCESS
        $request = $this->createRequest('GET');

        $response     = $attachmentTypeController->getById($request, new Response(), ['id' => self::$id]);
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame(200, $response->getStatusCode());

        $this->assertSame(self::$id, $responseBody['id']);
        $this->assertSame('type_test', $responseBody['typeId']);
        $this->assertSame('Type Test TU UP', $responseBody['label']);
        $this->assertSame(true, $responseBody['visible']);
        $this->assertSame(false, $responseBody['emailLink']);
        $this->assertSame(true, $responseBody['signable']);
        $this->assertSame(false, $responseBody['chrono']);
        $this->assertSame(true, $responseBody['versionEnabled']);
        $this->assertSame(false, $responseBody['newVersionDefault']);
        $this->assertSame('TU', $responseBody['icon']);

        $response     = $attachmentTypeController->getById($request, new Response(), ['id' => self::$id * 1000]);
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Attachment type does not exist', $responseBody['errors']);
        $this->assertSame(400, $response->getStatusCode());
    }

    public function testDelete()
    {
        $attachmentTypeController = new AttachmentTypeController();

        //  DELETE SUCCESS
        $request = $this->createRequest('DELETE');

        $response     = $attachmentTypeController->delete($request, new Response(), ['id' => self::$id]);
        $this->assertSame(204, $response->getStatusCode());

        //  DELETE ERRORS
        $response     = $attachmentTypeController->delete($request, new Response(), ['id' => self::$id]);
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Attachment type does not exist', $responseBody['errors']);
        $this->assertSame(400, $response->getStatusCode());

        $responseProjectType = AttachmentTypeModel::getByTypeId(['typeId' => 'response_project', 'select' => ['id']]);

        $response     = $attachmentTypeController->delete($request, new Response(), ['id' => $responseProjectType['id']]);
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Type is used in attachments', $responseBody['errors']);
        $this->assertSame(400, $response->getStatusCode());

        $response     = $attachmentTypeController->delete($request, new Response(), ['id' => 3]); // 3: 'signed_response' in data_fr.sql
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('This attachment type cannot be deleted', $responseBody['errors']);
        $this->assertSame(400, $response->getStatusCode());

        $response     = $attachmentTypeController->delete($request, new Response(), ['id' => 3]); // 3: 'signed_response' in data_fr.sql
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('This attachment type cannot be deleted', $responseBody['errors']);
        $this->assertSame(400, $response->getStatusCode());

        $GLOBALS['login'] = 'bbain';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        $response     = $attachmentTypeController->delete($request, new Response(), ['id' => self::$id]);
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Service forbidden', $responseBody['errors']);
        $this->assertSame(403, $response->getStatusCode());

        $GLOBALS['login'] = 'superadmin';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        //  READ
        $response = $attachmentTypeController->getById($request, new Response(), ['id' => self::$id]);
        $res = json_decode((string)$response->getBody(), true);
        $this->assertSame('Attachment type does not exist', $res['errors']);
        $this->assertSame(400, $response->getStatusCode());
    }
}
