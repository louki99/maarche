<?php

/**
* Copyright Maarch since 2008 under licence GPLv3.
* See LICENCE.txt file at the root folder for more details.
* This file is part of Maarch software.
*
*/

namespace MaarchCourrier\Tests\app\contact;

use Contact\controllers\ContactCivilityController;
use Contact\controllers\ContactController;
use Contact\models\ContactCustomFieldListModel;
use Contact\models\ContactModel;
use Contact\models\ContactParameterModel;
use Group\models\PrivilegeModel;
use Resource\controllers\ResController;
use Resource\models\ResModel;
use Resource\models\ResourceContactModel;
use SrcCore\http\Response;
use MaarchCourrier\Tests\CourrierTestCase;
use User\models\UserModel;

class ContactControllerTest extends CourrierTestCase
{
    private static $id = null;
    private static $id2 = null;
    private static $resId = null;
    private static $duplicateId = null;
    private static $customFieldId = null;

    public function testCreate()
    {
        $contactController = new ContactController();

        //  CREATE
        $args = [
            'civility'          => 1,
            'firstname'         => 'Hal',
            'lastname'          => 'Jordan',
            'company'           => 'Green Lantern Corps',
            'department'        => 'Sector 2814',
            'function'          => 'member',
            'addressNumber'     => '1',
            'addressStreet'     => 'somewhere',
            'addressPostcode'   => '99000',
            'addressTown'       => 'Bluehaven',
            'addressCountry'    => 'USA',
            'email'             => 'hal.jordan@glc.com',
            'phone'             => '911',
            'notes'             => 'In brightest day',
            'externalId'      => [
                'm2m' => '45239273100025/PJS'
            ]
        ];
        $fullRequest = $this->createRequestWithBody('POST', $args);

        $response     = $contactController->create($fullRequest, new Response());
        $this->assertSame(200, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);

        $this->assertIsInt($responseBody['id']);
        self::$id = $responseBody['id'];

        $GLOBALS['login'] = 'cchaplin';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        PrivilegeModel::addPrivilegeToGroup(['privilegeId' => 'create_contacts', 'groupId' => 'WEBSERVICE']);

        $args = [
            'civility'        => 1,
            'firstname'       => 'Hal',
            'lastname'        => 'Jordan',
            'company'         => 'Green Lantern Corps',
            'department'      => 'Sector 2814',
            'function'        => 'member',
            'addressNumber'   => '1',
            'addressStreet'   => 'somewhere',
            'addressPostcode' => '99000',
            'addressTown'     => 'Bluehaven',
            'addressCountry'  => 'USA',
            'email'           => 'hal.jordan@glc.com',
            'phone'           => '911',
            'notes'           => 'In brightest day'
        ];
        $fullRequest = $this->createRequestWithBody('POST', $args);

        $response = $contactController->create($fullRequest, new Response());

        PrivilegeModel::removePrivilegeToGroup(['privilegeId' => 'create_contacts', 'groupId' => 'WEBSERVICE']);

        $this->assertSame(200, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame(self::$id, $responseBody['id']);

        $GLOBALS['login'] = 'superadmin';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        $args2 = [
            'civility'           => 1,
            'firstname'          => 'Dwight',
            'lastname'           => 'Schrute',
            'company'            => 'Dunder Mifflin Paper Company Inc.',
            'department'         => 'Sales',
            'function'           => 'Salesman',
            'addressNumber'      => '1725',
            'addressStreet'      => 'Slough Avenue',
            'addressPostcode'    => '18505',
            'addressTown'        => 'Scranton',
            'addressCountry'     => 'USA',
            'phone'              => '5705551212',
            'notes'              => 'Assistant to the regional manager',
            'communicationMeans' => [
                'email'         => 'dschrute@dundermifflin.com'
            ],
            'externalId'         => [
                'schruteFarmId' => 1,
                'm2m'           => '45239273100025/PJS'
            ]
        ];
        $fullRequest = $this->createRequestWithBody('POST', $args2);

        $response     = $contactController->create($fullRequest, new Response());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertIsInt($responseBody['id']);
        self::$id2 = $responseBody['id'];

        self::$customFieldId = ContactCustomFieldListModel::create([
            'label'         => 'TEST CUSTOM',
            'type'          => 'integer',
            'values'        => '[]'
        ]);

        $args2['email'] = 'dschrute@dundermifflin.com';
        $args2['customFields'][self::$customFieldId] = 42;
        $fullRequest = $this->createRequestWithBody('POST', $args2);

        $response     = $contactController->create($fullRequest, new Response());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertIsInt($responseBody['id']);
        self::$duplicateId = $responseBody['id'];

        //  GET
        $request = $this->createRequest('GET');

        $response = $contactController->getById($request, new Response(), ['id' => self::$id]);
        $this->assertSame(200, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);

        $this->assertSame(self::$id, $responseBody['id']);
        $this->assertSame($args['civility'], $responseBody['civility']['id']);
        $this->assertSame($args['firstname'], $responseBody['firstname']);
        $this->assertSame($args['lastname'], $responseBody['lastname']);
        $this->assertSame($args['company'], $responseBody['company']);
        $this->assertSame($args['department'], $responseBody['department']);
        $this->assertSame($args['function'], $responseBody['function']);
        $this->assertSame($args['addressNumber'], $responseBody['addressNumber']);
        $this->assertSame($args['addressStreet'], $responseBody['addressStreet']);
        $this->assertSame($args['addressPostcode'], $responseBody['addressPostcode']);
        $this->assertSame($args['addressTown'], $responseBody['addressTown']);
        $this->assertSame($args['addressCountry'], $responseBody['addressCountry']);
        $this->assertSame($args['email'], $responseBody['email']);
        $this->assertSame($args['phone'], $responseBody['phone']);
        $this->assertSame($args['notes'], $responseBody['notes']);
        $this->assertSame(true, $responseBody['enabled']);
        $this->assertSame($GLOBALS['id'], $responseBody['creator']);
        $this->assertNotNull($responseBody['creatorLabel']);
        $this->assertNotNull($responseBody['creationDate']);
        $this->assertNull($responseBody['modificationDate']);


        //  ERRORS
        $args = [

        ];
        $fullRequest = $this->createRequestWithBody('POST', $args);

        $response     = $contactController->create($fullRequest, new Response());
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Body is not set or empty', $responseBody['errors']);

        $args = [
            'civility'          => 1,
            'firstname'         => 'Hal',
            'department'        => 'Sector 2814',
            'function'          => 'member',
            'addressNumber'     => '1',
            'addressStreet'     => 'somewhere',
            'addressPostcode'   => '99000',
            'addressTown'       => 'Bluehaven',
            'addressCountry'    => 'USA',
            'email'             => 'hal.jordan@glc.com',
            'phone'             => '911',
            'notes'             => 'In brightest day',
        ];
        $fullRequest = $this->createRequestWithBody('POST', $args);

        $response     = $contactController->create($fullRequest, new Response());
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Body lastname or company is mandatory', $responseBody['errors']);

        $args = [
            'civility'           => 1,
            'firstname'          => 'Dwight',
            'lastname'           => 'Schrute',
            'company'            => 'Dunder Mifflin Paper Company Inc.',
            'department'         => 'Sales',
            'function'           => 'Salesman',
            'addressNumber'      => '1725',
            'addressStreet'      => 'Slough Avenue',
            'addressPostcode'    => '18505',
            'addressTown'        => 'Scranton',
            'addressCountry'     => 'USA',
            'email'              => 'dschrute@schrutefarms.com',
            'phone'              => '5705551212',
            'notes'              => 'Assistant to the regional manager',
            'communicationMeans' => 'wrong format'
        ];
        $fullRequest = $this->createRequestWithBody('POST', $args);

        $response     = $contactController->create($fullRequest, new Response());
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame(_COMMUNICATION_MEANS_VALIDATOR, $responseBody['errors']);


        $args = [
            'civility'        => 1,
            'firstname'       => 'Dwight',
            'lastname'        => 'Schrute',
            'company'         => 'Dunder Mifflin Paper Company Inc.',
            'department'      => 'Sales',
            'function'        => 'Salesman',
            'addressNumber'   => '1725',
            'addressStreet'   => 'Slough Avenue',
            'addressPostcode' => '18505',
            'addressTown'     => 'Scranton',
            'addressCountry'  => 'USA',
            'email'           => 'wrong email format',
            'phone'           => '5705551212',
            'notes'           => 'Assistant to the regional manager',
            'customFields'    => 'wrong format'
        ];
        $fullRequest = $this->createRequestWithBody('POST', $args);

        $response     = $contactController->create($fullRequest, new Response());
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Body email is not valid', $responseBody['errors']);

        $args = [
            'civility'           => 1,
            'firstname'          => 'Dwight',
            'lastname'           => 'Schrute',
            'company'            => 'Dunder Mifflin Paper Company Inc.',
            'department'         => 'Sales',
            'function'           => 'Salesman',
            'addressNumber'      => '1725',
            'addressStreet'      => 'Slough Avenue',
            'addressPostcode'    => '18505',
            'addressTown'        => 'Scranton',
            'addressCountry'     => 'USA',
            'email'              => 'dschrute@schrutefarms.com',
            'phone'              => 'wrong format',
            'notes'              => 'Assistant to the regional manager',
            'communicationMeans' => 'wrong format'
        ];
        $fullRequest = $this->createRequestWithBody('POST', $args);

        $response     = $contactController->create($fullRequest, new Response());
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Body phone is not valid', $responseBody['errors']);

        $args = [
            'civility'        => 1,
            'firstname'       => 'Dwight',
            'lastname'        => 'Schrute',
            'company'         => 'Dunder Mifflin Paper Company Inc.',
            'department'      => 'Sales',
            'function'        => 'Salesman',
            'addressNumber'   => '1725',
            'addressStreet'   => 'Slough Avenue',
            'addressPostcode' => '18505',
            'addressTown'     => 'Scranton',
            'addressCountry'  => 'USA',
            'email'           => 'dschrute@schrutefarms.com',
            'phone'           => '5705551212',
            'notes'           => 'Assistant to the regional manager',
            'customFields'    => [1000 => 'wrong field']
        ];
        $fullRequest = $this->createRequestWithBody('POST', $args);

        $response     = $contactController->create($fullRequest, new Response());
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Body customFields : One or more custom fields do not exist', $responseBody['errors']);

        $args = [
            'civility'        => 1,
            'firstname'       => 'Dwight',
            'lastname'        => 'Schrute',
            'company'         => 'Dunder Mifflin Paper Company Inc.',
            'department'      => 'Sales',
            'function'        => 'Salesman',
            'addressNumber'   => '1725',
            'addressStreet'   => 'Slough Avenue',
            'addressPostcode' => '18505',
            'addressTown'     => 'Scranton',
            'addressCountry'  => 'USA',
            'email'           => 'dschrute@schrutefarms.com',
            'phone'           => '5705551212',
            'notes'           => 'Assistant to the regional manager',
            'customFields'    => 'wrong format'
        ];
        $fullRequest = $this->createRequestWithBody('POST', $args);

        $response     = $contactController->create($fullRequest, new Response());
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Body customFields is not an array', $responseBody['errors']);

        $args = [
            'civility'        => 1,
            'firstname'       => 'Dwight',
            'lastname'        => 'Schrute',
            'company'         => 'Dunder Mifflin Paper Company Inc. blablablablablablablablablablablablablablablablablablablablablablablablablablablablablablablablablablablablablablablablablablablablablablablablablablablablablablablablablablablablablablablablablablablablablablalablablablablablablablablablabla',
            'department'      => 'Sales',
            'function'        => 'Salesman',
            'addressNumber'   => '1725',
            'addressStreet'   => 'Slough Avenue',
            'addressPostcode' => '18505',
            'addressTown'     => 'Scranton',
            'addressCountry'  => 'USA',
            'email'           => 'dschrute@schrutefarms.com',
            'phone'           => '5705551212',
            'notes'           => 'Assistant to the regional manager',
            'customFields'    => 'wrong format'
        ];
        $fullRequest = $this->createRequestWithBody('POST', $args);

        $response     = $contactController->create($fullRequest, new Response());
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Body company length is not valid (1..256)', $responseBody['errors']);

        $GLOBALS['login'] = 'ddur';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        $response     = $contactController->create($fullRequest, new Response());
        $this->assertSame(403, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Service forbidden', $responseBody['errors']);

        $GLOBALS['login'] = 'superadmin';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];
    }

    public function testGetDuplicatedContacts()
    {
        $request = $this->createRequest('GET');

        $contactController = new ContactController();

        $queryParams = [
            'criteria' => ['company']
        ];
        $fullRequest = $request->withQueryParams($queryParams);
        $response = $contactController->getDuplicatedContacts($fullRequest, new Response());
        $this->assertSame(200, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);

        $this->assertSame(4, $responseBody['realCount']);
        $this->assertSame(4, $responseBody['returnedCount']);

        $this->assertIsArray($responseBody['contacts']);
        $this->assertNotEmpty($responseBody['contacts']);
        $this->assertSame(4, count($responseBody['contacts']));

        $dunderMifflinDuplicates = array_filter($responseBody['contacts'], function ($contact) {
            return $contact['company'] == 'Dunder Mifflin Paper Company Inc.';
        });

        $this->assertSame(2, count($dunderMifflinDuplicates));

        // Errors
        $response = $contactController->getDuplicatedContacts($request, new Response());
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Query criteria is empty or not an array', $responseBody['errors']);

        $queryParams = [
            'criteria' => ['contactCustomField_1000']
        ];
        $fullRequest = $request->withQueryParams($queryParams);
        $response = $contactController->getDuplicatedContacts($fullRequest, new Response());
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Custom criteria does not exist', $responseBody['errors']);

        $queryParams = [
            'criteria' => ['fieldThatDoesNotExist']
        ];
        $fullRequest = $request->withQueryParams($queryParams);
        $response = $contactController->getDuplicatedContacts($fullRequest, new Response());
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('criteria does not exist', $responseBody['errors']);

        $GLOBALS['login'] = 'bbain';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        $response = $contactController->getDuplicatedContacts($request, new Response());
        $this->assertSame(403, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Service forbidden', $responseBody['errors']);

        $GLOBALS['login'] = 'superadmin';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];
    }

    public function testMergeContacts()
    {
        $contactController = new ContactController();

        $body = [
            'duplicates' => [self::$duplicateId]
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $body);
        $response = $contactController->mergeContacts($fullRequest, new Response(), ['id' => self::$id2]);
        $this->assertSame(204, $response->getStatusCode());

        // Errors
        $request = $this->createRequest('PUT');
        $response = $contactController->mergeContacts($request, new Response(), ['id' => 'wrong format']);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Route id is not an integer', $responseBody['errors']);

        $response = $contactController->mergeContacts($request, new Response(), ['id' => self::$id2]);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Body duplicates is empty or not an array', $responseBody['errors']);

        $body = [
            'duplicates' => [self::$duplicateId * 1000]
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $body);
        $response = $contactController->mergeContacts($fullRequest, new Response(), ['id' => self::$id2 * 1000]);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('master does not exist', $responseBody['errors']);

        $body = [
            'duplicates' => [self::$duplicateId * 1000]
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $body);
        $response = $contactController->mergeContacts($fullRequest, new Response(), ['id' => self::$id2]);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('duplicates do not exist', $responseBody['errors']);

        $GLOBALS['login'] = 'bbain';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        $response = $contactController->mergeContacts($request, new Response(), ['id' => self::$id2]);
        $this->assertSame(403, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Service forbidden', $responseBody['errors']);

        $GLOBALS['login'] = 'superadmin';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];
    }

    public function testUpdate()
    {
        $contactController = new ContactController();

        //  UPDATE
        $args = [
            'civility'          => 1,
            'lastname'          => 'Sinestro',
            'company'           => 'Yellow Lantern Corps',
            'department'        => 'Sector 2813',
            'function'          => 'Head',
            'addressNumber'     => '666',
            'addressStreet'     => 'anywhere',
            'addressPostcode'   => '98000',
            'addressTown'       => 'Redhaven',
            'addressCountry'    => 'U.S.A',
            'email'             => 'sinestro@ylc.com',
            'phone'             => '919',
            'notes'             => 'In blackest day',
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $args);

        $response = $contactController->update($fullRequest, new Response(), ['id' => self::$id]);
        $this->assertSame(204, $response->getStatusCode());


        //  GET
        $request = $this->createRequest('GET');

        $response = $contactController->getById($request, new Response(), ['id' => self::$id]);
        $this->assertSame(200, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);

        $this->assertSame(self::$id, $responseBody['id']);
        $this->assertSame($args['civility'], $responseBody['civility']['id']);
        $this->assertNull($responseBody['firstname']);
        $this->assertSame($args['lastname'], $responseBody['lastname']);
        $this->assertSame($args['company'], $responseBody['company']);
        $this->assertSame($args['department'], $responseBody['department']);
        $this->assertSame($args['function'], $responseBody['function']);
        $this->assertSame($args['addressNumber'], $responseBody['addressNumber']);
        $this->assertSame($args['addressStreet'], $responseBody['addressStreet']);
        $this->assertSame($args['addressPostcode'], $responseBody['addressPostcode']);
        $this->assertSame($args['addressTown'], $responseBody['addressTown']);
        $this->assertSame($args['addressCountry'], $responseBody['addressCountry']);
        $this->assertSame($args['email'], $responseBody['email']);
        $this->assertSame($args['phone'], $responseBody['phone']);
        $this->assertSame($args['notes'], $responseBody['notes']);
        $this->assertSame(true, $responseBody['enabled']);
        $this->assertSame($GLOBALS['id'], $responseBody['creator']);
        $this->assertNotNull($responseBody['creatorLabel']);
        $this->assertNotNull($responseBody['creationDate']);
        $this->assertNotNull($responseBody['modificationDate']);

        $args = [
            'civility'        => 1,
            'firstname'       => 'Dwight',
            'lastname'        => 'Schrute',
            'company'         => 'Dunder Mifflin Paper Company Inc.',
            'department'      => 'Sales',
            'function'        => 'Salesman',
            'addressNumber'   => '1725',
            'addressStreet'   => 'Slough Avenue',
            'addressPostcode' => '18505',
            'addressTown'     => 'Scranton',
            'addressCountry'  => 'USA',
            'email'           => 'dschrute@schrutefarms.com',
            'phone'           => '5705551212',
            'notes'           => 'Assistant to the regional manager',
            'externalId'      => ['schruteFarmId' => 2]
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $args);

        $response = $contactController->update($fullRequest, new Response(), ['id' => self::$id2]);
        $this->assertSame(204, $response->getStatusCode());


        //  ERRORS
        $args = [
            'civility'          => 1,
            'firstname'         => 'Hal',
            'department'        => 'Sector 2814',
            'function'          => 'member',
            'addressNumber'     => '1',
            'addressStreet'     => 'somewhere',
            'addressPostcode'   => '99000',
            'addressTown'       => 'Bluehaven',
            'addressCountry'    => 'USA',
            'email'             => 'hal.jordan@glc.com',
            'phone'             => '911',
            'notes'             => 'In brightest day',
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $args);

        $response = $contactController->update($fullRequest, new Response(), ['id' => self::$id]);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Body lastname or company is mandatory', $responseBody['errors']);

        $args = [
            'civility'        => 1,
            'firstname'       => 'Dwight',
            'lastname'        => 'Schrute',
            'company'         => 'Dunder Mifflin Paper Company Inc.',
            'department'      => 'Sales',
            'function'        => 'Salesman',
            'addressNumber'   => '1725',
            'addressStreet'   => 'Slough Avenue',
            'addressPostcode' => '18505',
            'addressTown'     => 'Scranton',
            'addressCountry'  => 'USA',
            'email'           => 'dschrute@schrutefarms.com',
            'phone'           => '5705551212',
            'notes'           => 'Assistant to the regional manager'
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $args);

        $response = $contactController->update($fullRequest, new Response(), ['id' => self::$id2 * 1000]);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Contact does not exist', $responseBody['errors']);

        $response = $contactController->update($fullRequest, new Response(), ['id' => 'wrong format']);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Route id is not an integer', $responseBody['errors']);

        $GLOBALS['login'] = 'ddur';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        $response     = $contactController->update($fullRequest, new Response(), ['id' => self::$id2]);
        $this->assertSame(403, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Service forbidden', $responseBody['errors']);

        $GLOBALS['login'] = 'superadmin';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];
    }

    public function testGet()
    {
        $contactController = new ContactController();

        //  GET
        $request = $this->createRequest('GET');

        $queryParams = [
            'orderBy' => 'filling'
        ];
        $fullRequest = $request->withQueryParams($queryParams);

        $response = $contactController->get($fullRequest, new Response());
        $this->assertSame(200, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);

        $this->assertIsArray($responseBody['contacts']);
        $this->assertNotEmpty($responseBody['contacts']);

        $this->assertNotNull($responseBody['contacts'][0]['id']);
        $this->assertNotNull($responseBody['contacts'][0]['lastname']);
        $this->assertNotNull($responseBody['contacts'][0]['company']);

        $queryParams = [
            'search'  => 'Michael Scott',
            'orderBy' => 'filling'
        ];
        $fullRequest = $request->withQueryParams($queryParams);

        $response = $contactController->get($fullRequest, new Response());
        $this->assertSame(200, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);

        $this->assertIsArray($responseBody['contacts']);
        $this->assertEmpty($responseBody['contacts']);
        $this->assertSame(0, $responseBody['count']);

        $GLOBALS['login'] = 'bbain';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        $response     = $contactController->get($request, new Response());
        $this->assertSame(403, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Service forbidden', $responseBody['errors']);

        $GLOBALS['login'] = 'superadmin';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];
    }

    public function testControlLengthNameAfnor()
    {
        $name = ContactController::controlLengthNameAfnor(['civility' => 1, 'fullName' => 'Prénom NOM', 'strMaxLength' => 38]);

        $this->assertSame('Monsieur Prénom NOM', $name);

        $name = ContactController::controlLengthNameAfnor(['civility' => 3, 'fullName' => 'Prénom NOM TROP LOOOOOOOOOOOOONG', 'strMaxLength' => 38]);

        $this->assertSame('Mlle Prénom NOM TROP LOOOOOOOOOOOOONG', $name);
    }

    public function testGetContactAfnor()
    {
        $contactController = new ContactController();

        //  GET
        $contact = ContactModel::getById([
            'select' => [
                'company', 'firstname', 'lastname', 'civility', 'address_additional1', 'address_additional2', 'address_number',
                'address_street', 'address_postcode', 'address_town'
            ],
            'id' => self::$id2
        ]);

        $response = $contactController::getContactAfnor($contact);

        $this->assertIsArray($response);
        $this->assertNotEmpty($response);
        $this->assertSame('Afnor', $response[0]);
        $this->assertSame('Dunder Mifflin Paper Company Inc.', $response[1]);
        $this->assertSame('Monsieur Dwight Schrute', $response[2]);
        $this->assertEmpty($response[3]);
        $this->assertSame('1725 SLOUGH AVENUE', $response[4]);
        $this->assertEmpty($response[5]);
        $this->assertSame('18505 SCRANTON', $response[6]);
    }

    public function testGetByResId()
    {
        $resController = new ResController();

        //  CREATE
        $GLOBALS['login'] = 'cchaplin';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        $fileContent = file_get_contents('test/unitTests/samples/test.txt');
        $encodedFile = base64_encode($fileContent);

        $argsMailNew = [
            'modelId'          => 1,
            'status'           => 'NEW',
            'encodedFile'      => $encodedFile,
            'format'           => 'txt',
            'confidentiality'  => false,
            'documentDate'     => '2019-01-01 17:18:47',
            'arrivalDate'      => '2019-01-01 17:18:47',
            'processLimitDate' => '2029-01-01',
            'doctype'          => 102,
            'destination'      => 15,
            'initiator'        => 15,
            'subject'          => 'Breaking News : Superman is dead again - PHP unit',
            'typist'           => 19,
            'priority'         => 'poiuytre1357nbvc',
            'followed'         => true,
            'diffusionList'    => [
                [
                    'id'   => 11,
                    'type' => 'user',
                    'mode' => 'dest'
                ]
            ],
            'senders'          => [
                [
                    'id'   => self::$id2,
                    'type' => 'contact'
                ]
            ],
            'recipients'       => [
                [
                    'id'   => $GLOBALS['id'],
                    'type' => 'user'
                ],
                [
                    'id'   => 1,
                    'type' => 'entity'
                ],

            ]
        ];
        $fullRequest = $this->createRequestWithBody('POST', $argsMailNew);


        $response     = $resController->create($fullRequest, new Response());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertIsInt($responseBody['resId']);
        self::$resId = $responseBody['resId'];

        $GLOBALS['login'] = 'superadmin';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        $contactController = new ContactController();

        //  GET
        $request = $this->createRequest('GET');

        // Senders
        $queryParams = [
            'type' => 'senders'
        ];
        $fullRequest = $request->withQueryParams($queryParams);
        $response = $contactController->getByResId($fullRequest, new Response(), ['resId' => self::$resId]);
        $this->assertSame(200, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);

        $this->assertIsArray($responseBody['contacts']);
        $this->assertNotEmpty($responseBody['contacts']);

        $this->assertSame('contact', $responseBody['contacts'][0]['type']);
        $this->assertSame('Dwight', $responseBody['contacts'][0]['firstname']);
        $this->assertSame('Schrute', $responseBody['contacts'][0]['lastname']);
        $this->assertSame('Dunder Mifflin Paper Company Inc.', $responseBody['contacts'][0]['company']);
        $this->assertSame('Sales', $responseBody['contacts'][0]['department']);
        $this->assertSame('Salesman', $responseBody['contacts'][0]['function']);
        $this->assertSame('1725', $responseBody['contacts'][0]['addressNumber']);
        $this->assertSame('Slough Avenue', $responseBody['contacts'][0]['addressStreet']);
        $this->assertEmpty($responseBody['contacts'][0]['addressAdditional1']);
        $this->assertEmpty($responseBody['contacts'][0]['addressAdditional2']);
        $this->assertSame('18505', $responseBody['contacts'][0]['addressPostcode']);
        $this->assertSame('Scranton', $responseBody['contacts'][0]['addressTown']);
        $this->assertSame('USA', $responseBody['contacts'][0]['addressCountry']);
        $this->assertSame('dschrute@schrutefarms.com', $responseBody['contacts'][0]['email']);
        $this->assertSame('5705551212', $responseBody['contacts'][0]['phone']);
        $this->assertEmpty($responseBody['contacts'][0]['communicationMeans']);
        $this->assertSame('Assistant to the regional manager', $responseBody['contacts'][0]['notes']);
        $this->assertSame($GLOBALS['id'], $responseBody['contacts'][0]['creator']);
        $this->assertSame(true, $responseBody['contacts'][0]['enabled']);
        $this->assertEmpty($responseBody['contacts'][0]['customFields']);
        $this->assertIsArray($responseBody['contacts'][0]['externalId']);
        $this->assertSame(2, $responseBody['contacts'][0]['externalId']['schruteFarmId']);
        $this->assertIsArray($responseBody['contacts'][0]['fillingRate']);
        $this->assertSame(100, $responseBody['contacts'][0]['fillingRate']['rate']);
        $this->assertSame('third', $responseBody['contacts'][0]['fillingRate']['thresholdLevel']);

        // Recipients
        $queryParams = [
            'type' => 'recipients'
        ];
        $fullRequest = $request->withQueryParams($queryParams);
        $response = $contactController->getByResId($fullRequest, new Response(), ['resId' => self::$resId]);
        $this->assertSame(200, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);

        $this->assertIsArray($responseBody['contacts']);
        $this->assertNotEmpty($responseBody['contacts']);

        $this->assertSame('user', $responseBody['contacts'][0]['type']);
        $this->assertSame('Charlie', $responseBody['contacts'][0]['firstname']);
        $this->assertSame('CHAPLIN', $responseBody['contacts'][0]['lastname']);
        $this->assertEmpty($responseBody['contacts'][0]['company']);
        $this->assertSame('Ville de Maarch-les-Bains', $responseBody['contacts'][0]['department']);
        $this->assertEmpty($responseBody['contacts'][0]['function']);
        $this->assertEmpty($responseBody['contacts'][0]['addressNumber']);
        $this->assertEmpty($responseBody['contacts'][0]['addressStreet']);
        $this->assertEmpty($responseBody['contacts'][0]['addressAdditional1']);
        $this->assertEmpty($responseBody['contacts'][0]['addressAdditional2']);
        $this->assertEmpty($responseBody['contacts'][0]['addressPostcode']);
        $this->assertEmpty($responseBody['contacts'][0]['addressTown']);
        $this->assertEmpty($responseBody['contacts'][0]['addressCountry']);
        $this->assertSame('yourEmail@domain.com', $responseBody['contacts'][0]['email']);
        $this->assertEmpty($responseBody['contacts'][0]['phone']);
        $this->assertEmpty($responseBody['contacts'][0]['communicationMeans']);
        $this->assertEmpty($responseBody['contacts'][0]['notes']);
        $this->assertEmpty($responseBody['contacts'][0]['creator']);
        $this->assertSame(true, $responseBody['contacts'][0]['enabled']);
        $this->assertEmpty($responseBody['contacts'][0]['customFields']);
        $this->assertEmpty($responseBody['contacts'][0]['externalId']);
        $this->assertEmpty($responseBody['contacts'][0]['fillingRate']);

        $this->assertSame('entity', $responseBody['contacts'][1]['type']);
        $this->assertEmpty($responseBody['contacts'][1]['firstname']);
        $this->assertSame('Ville de Maarch-les-Bains', $responseBody['contacts'][1]['lastname']);
        $this->assertEmpty($responseBody['contacts'][1]['company']);
        $this->assertEmpty($responseBody['contacts'][1]['department']);
        $this->assertEmpty($responseBody['contacts'][1]['function']);
        $this->assertEmpty($responseBody['contacts'][1]['addressNumber']);
        $this->assertNotEmpty($responseBody['contacts'][1]['addressStreet']);
        $this->assertNotEmpty($responseBody['contacts'][1]['addressAdditional1']);
        $this->assertEmpty($responseBody['contacts'][1]['addressAdditional2']);
        $this->assertNotEmpty($responseBody['contacts'][1]['addressPostcode']);
        $this->assertNotEmpty($responseBody['contacts'][1]['addressTown']);
        $this->assertNotEmpty($responseBody['contacts'][1]['addressCountry']);
        $this->assertSame('mairie@maarchlesbains.fr', $responseBody['contacts'][1]['email']);
        $this->assertEmpty($responseBody['contacts'][1]['phone']);
        $this->assertEmpty($responseBody['contacts'][1]['communicationMeans']);
        $this->assertEmpty($responseBody['contacts'][1]['notes']);
        $this->assertEmpty($responseBody['contacts'][1]['creator']);
        $this->assertSame(true, $responseBody['contacts'][1]['enabled']);
        $this->assertEmpty($responseBody['contacts'][1]['customFields']);
        $this->assertEmpty($responseBody['contacts'][1]['externalId']);
        $this->assertEmpty($responseBody['contacts'][1]['fillingRate']);

        // No contact type provided
        $response = $contactController->getByResId($request, new Response(), ['resId' => self::$resId]);
        $this->assertSame(200, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertIsArray($responseBody['contacts']);
        $this->assertEmpty($responseBody['contacts']);

        $GLOBALS['login'] = 'bbain';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        $response = $contactController->getByResId($request, new Response(), ['resId' => self::$resId]);
        $this->assertSame(403, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Document out of perimeter', $responseBody['errors']);

        // Delete resource
        ResourceContactModel::delete(['where' => ['res_id = ?'], 'data' => [self::$resId]]);
        ResModel::delete([
            'where' => ['res_id = ?'],
            'data' => [self::$resId]
        ]);

        $res = ResModel::getById(['resId' => self::$resId, 'select' => ['*']]);
        $this->assertIsArray($res);
        $this->assertEmpty($res);

        $GLOBALS['login'] = 'superadmin';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];
    }

    public function testGetCivilities()
    {
        $contactCivilityController = new ContactCivilityController();

        //  GET
        $request = $this->createRequest('GET');

        $response = $contactCivilityController->get($request, new Response());
        $this->assertSame(200, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);

        $this->assertIsArray($responseBody['civilities']);
        $this->assertNotEmpty($responseBody['civilities']);
    }

    public function testGetAvailableDepartments()
    {
        $contactController = new ContactController();

        $request = $this->createRequest('GET');

        $response = $contactController->getAvailableDepartments($request, new Response());
        $responseBody      = json_decode((string)$response->getBody(), true);

        $this->assertIsArray($responseBody['departments']);
        $this->assertNotEmpty($responseBody['departments']);
    }

    public function testGetLightFormattedContact()
    {
        $contactController = new ContactController();

        $request = $this->createRequest('GET');

        $response = $contactController->getLightFormattedContact($request, new Response(), ['id' => self::$id2, 'type' => 'contact']);
        $this->assertSame(200, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);

        $this->assertSame('Dwight', $responseBody['contact']['firstname']);
        $this->assertSame('Schrute', $responseBody['contact']['lastname']);
        $this->assertSame('Dunder Mifflin Paper Company Inc.', $responseBody['contact']['company']);
        $this->assertSame('1725', $responseBody['contact']['addressNumber']);
        $this->assertSame('Slough Avenue', $responseBody['contact']['addressStreet']);
        $this->assertSame('18505', $responseBody['contact']['addressPostcode']);
        $this->assertSame('Scranton', $responseBody['contact']['addressTown']);
        $this->assertSame('USA', $responseBody['contact']['addressCountry']);

        $response = $contactController->getLightFormattedContact($request, new Response(), ['id' => 1, 'type' => 'user']);
        $this->assertSame(200, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);

        $this->assertIsString($responseBody['contact']['firstname']);
        $this->assertIsString($responseBody['contact']['lastname']);

        $response = $contactController->getLightFormattedContact($request, new Response(), ['id' => 1, 'type' => 'entity']);
        $this->assertSame(200, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);

        $this->assertIsString($responseBody['contact']['label']);

        // Fail
        $response = $contactController->getLightFormattedContact($request, new Response(), ['id' => 'wrong format']);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Query params id is not an integer', $responseBody['errors']);

        $response = $contactController->getLightFormattedContact($request, new Response(), ['id' => self::$id * 1000, 'type' => 'contact']);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Contact does not exist', $responseBody['errors']);

        $response = $contactController->getLightFormattedContact($request, new Response(), ['id' => self::$id * 1000, 'type' => 'user']);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Contact does not exist', $responseBody['errors']);

        $response = $contactController->getLightFormattedContact($request, new Response(), ['id' => self::$id * 1000, 'type' => 'entity']);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Contact does not exist', $responseBody['errors']);
    }

    public function testUpdateContactsParameters()
    {
        $contactController = new ContactController();

        // Success
        $body = [
            'contactsParameters' => [
                'id'          => 5, // department
                'label'       => null,
                'mandatory'   => true,
                'filling'     => true,
                'searchable'  => true,
                'displayable' => true
            ],
            'contactsFilling'    => [
                'enable'          => true,
                'first_threshold'  => 50,
                'second_threshold' => 80,
            ]
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $body);

        $response     = $contactController->updateContactsParameters($fullRequest, new Response());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('success', $responseBody['success']);

        // Fail
        $body = [ ];
        $fullRequest = $this->createRequestWithBody('PUT', $body);

        $response     = $contactController->updateContactsParameters($fullRequest, new Response());
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Bad Request', $responseBody['errors']);

        $GLOBALS['login'] = 'bbain';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        $response     = $contactController->updateContactsParameters($fullRequest, new Response());
        $this->assertSame(403, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Service forbidden', $responseBody['errors']);

        $GLOBALS['login'] = 'superadmin';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];
    }

    public function testGetContactsParameters()
    {
        $request = $this->createRequest('GET');

        $customParameterId = ContactParameterModel::create(['identifier' => 'contactCustomField_1000']);

        $contactController = new ContactController();
        $response          = $contactController->getContactsParameters($request, new Response());
        $responseBody      = json_decode((string)$response->getBody(), true);

        $this->assertIsArray($responseBody['contactsFilling']);
        $this->assertNotEmpty($responseBody['contactsFilling']);
        $this->assertNotEmpty($responseBody['contactsParameters']);
        $this->assertNotEmpty($responseBody['contactsParameters']);
        $this->assertSame(false, $responseBody['annuaryEnabled']);

        ContactParameterModel::delete([
            'where' => ['id = ?'],
            'data'  => [$customParameterId]
        ]);
    }

    public function testUpdateActivation()
    {
        $request = $this->createRequest('PUT');

        $contactController = new ContactController();

        // Success
        $response          = $contactController->updateActivation($request, new Response(), ['id' => self::$id]);
        $this->assertSame(204, $response->getStatusCode());

        $contact = ContactModel::getById([
            'select' => ['enabled'],
            'id'     => self::$id
        ]);
        $this->assertSame(false, $contact['enabled']);

        $body = [
            'enabled' => true
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $body);

        $response          = $contactController->updateActivation($fullRequest, new Response(), ['id' => self::$id]);
        $this->assertSame(204, $response->getStatusCode());

        $contact = ContactModel::getById([
            'select' => ['enabled'],
            'id'     => self::$id
        ]);
        $this->assertSame(true, $contact['enabled']);

        // Fail
        $response = $contactController->updateActivation($request, new Response(), ['id' => 'wrong format']);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Route id is not an integer', $responseBody['errors']);

        $response = $contactController->updateActivation($request, new Response(), ['id' => self::$id * 1000]);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Contact does not exist', $responseBody['errors']);

        $this->assertIsArray((array)$responseBody->contactsFilling);

        $GLOBALS['login'] = 'bbain';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        $response = $contactController->updateActivation($request, new Response(), ['id' => self::$id * 1000]);
        $this->assertSame(403, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Service forbidden', $responseBody['errors']);

        $GLOBALS['login'] = 'superadmin';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];
    }

    public function testExportContacts()
    {
        $contactController = new ContactController();

        // Success
        $body = [
            'delimiter' => ';',
            'data' => [
                ['value' => 'id', 'label' => 'ID'],
                ['value' => 'creatorLabel', 'label' => 'CreatorLabel'],
                ['value' => 'contactCustomField_' . self::$customFieldId, 'label' => 'Custom']
            ]
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $body);
        $response = $contactController->exportContacts($fullRequest, new Response());
        $this->assertSame(200, $response->getStatusCode());
        $headers = $response->getHeaders();
        $this->assertSame('application/vnd.ms-excel', $headers['Content-Type'][0]);

        // Fail
        $body = [
            'delimiter' => '$'
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $body);
        $response = $contactController->exportContacts($fullRequest, new Response());
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Delimiter is empty or not a string between [\',\', \';\', \'TAB\']', $responseBody['errors']);

        $body = [
            'delimiter' => ';'
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $body);
        $response = $contactController->exportContacts($fullRequest, new Response());
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Data data is empty or not an array', $responseBody['errors']);

        $body = [
            'delimiter' => ';',
            'data' => [
                []
            ]
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $body);
        $response = $contactController->exportContacts($fullRequest, new Response());
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('field value is empty or not a string', $responseBody['errors']);

        $body = [
            'delimiter' => ';',
            'data' => [
                ['value' => 'wrongValue']
            ]
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $body);
        $response = $contactController->exportContacts($fullRequest, new Response());
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('field label is empty or not a string', $responseBody['errors']);

        $body = [
            'delimiter' => ';',
            'data' => [
                ['value' => 'wrongValue', 'label' => 'WrongValue']
            ]
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $body);
        $response = $contactController->exportContacts($fullRequest, new Response());
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('field does not exist', $responseBody['errors']);

        $body = [
            'delimiter' => ';',
            'data' => [
                ['value' => 'contactCustomField_1000', 'label' => 'WrongValue']
            ]
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $body);
        $response = $contactController->exportContacts($fullRequest, new Response());
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Custom field does not exist', $responseBody['errors']);

        $GLOBALS['login'] = 'bbain';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        $response = $contactController->exportContacts($fullRequest, new Response());
        $this->assertSame(403, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Service forbidden', $responseBody['errors']);

        $GLOBALS['login'] = 'superadmin';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];
    }

    public function testDelete()
    {
        $contactController = new ContactController();

        //  DELETE

        //  ERRORS
        $request = $this->createRequest('DELETE');

        $response = $contactController->delete($request, new Response(), ['id' => self::$id * 1000]);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Contact does not exist', $responseBody['errors']);

        $response = $contactController->delete($request, new Response(), ['id' => ['wrong format']]);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Route id is not an integer', $responseBody['errors']);

        $queryParams = [
            'redirect' => 'wrong format'
        ];
        $fullRequest = $request->withQueryParams($queryParams);

        $response = $contactController->delete($fullRequest, new Response(), ['id' => self::$id]);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Query param redirect is not an integer', $responseBody['errors']);

        $queryParams = [
            'redirect' => self::$id
        ];
        $fullRequest = $request->withQueryParams($queryParams);

        $response = $contactController->delete($fullRequest, new Response(), ['id' => self::$id]);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Cannot redirect to contact you are deleting', $responseBody['errors']);

        $queryParams = [
            'redirect' => self::$id * 1000
        ];
        $fullRequest = $request->withQueryParams($queryParams);

        $response = $contactController->delete($fullRequest, new Response(), ['id' => self::$id]);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Contact does not exist', $responseBody['errors']);

        $GLOBALS['login'] = 'bbain';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        $response = $contactController->delete($fullRequest, new Response(), ['id' => self::$id2]);
        $this->assertSame(403, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Service forbidden', $responseBody['errors']);

        $GLOBALS['login'] = 'superadmin';
        $userInfo = UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        // Success
        $queryParams = [
            'redirect' => self::$id
        ];
        $fullRequest = $request->withQueryParams($queryParams);

        $response = $contactController->delete($fullRequest, new Response(), ['id' => self::$id2]);
        $this->assertSame(204, $response->getStatusCode());

        $response = $contactController->delete($request, new Response(), ['id' => self::$id]);
        $this->assertSame(204, $response->getStatusCode());

        //  GET
        $request = $this->createRequest('GET');

        $response = $contactController->getById($request, new Response(), ['id' => self::$id]);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);

        $this->assertSame('Contact does not exist', $responseBody['errors']);

        ContactCustomFieldListModel::delete([
            'where' => ['id = ?'],
            'data'  => [self::$customFieldId]
        ]);
    }

//    public function testUpdateFilling()
//    {
//        $environment    = \Slim\Http\Environment::mock(['REQUEST_METHOD' => 'PUT']);
//        $request        = \Slim\Http\Request::createFromEnvironment($environment);
//
//        $aArgs = [
//            "enable"            => true,
//            "rating_columns"    => ["society", "function"],
//            "first_threshold"   => 22,
//            "second_threshold"  => 85
//        ];
//        $fullRequest = \httpRequestCustom::addContentInBody($aArgs, $request);
//
//        $contactController = new \Contact\controllers\ContactController();
//        $response          = $contactController->updateFilling($fullRequest, new Response());
//        $responseBody      = json_decode((string)$response->getBody());
//
//        $this->assertSame('success', $responseBody->success);
//
//        $response          = $contactController->getFilling($request, new Response());
//        $responseBody      = json_decode((string)$response->getBody());
//
//        $this->assertSame(true, $responseBody->contactsFilling->enable);
//        $this->assertSame(22, $responseBody->contactsFilling->first_threshold);
//        $this->assertSame(85, $responseBody->contactsFilling->second_threshold);
//        $this->assertSame('society', $responseBody->contactsFilling->rating_columns[0]);
//        $this->assertSame('function', $responseBody->contactsFilling->rating_columns[1]);
//
//        $aArgs = [
//            "enable"            => true,
//            "first_threshold"   => 22,
//            "second_threshold"  => 85
//        ];
//        $fullRequest = \httpRequestCustom::addContentInBody($aArgs, $request);
//
//        $response          = $contactController->updateFilling($fullRequest, new Response());
//        $responseBody      = json_decode((string)$response->getBody());
//
//        $this->assertSame('Bad Request', $responseBody->errors);
//    }
}
