<?php
/**
* Copyright Maarch since 2008 under licence GPLv3.
* See LICENCE.txt file at the root folder for more details.
* This file is part of Maarch software.

* @brief   ActionsControllerTest
* @author  dev <dev@maarch.org>
* @ingroup core
*/

namespace MaarchCourrier\Tests\core;

use SrcCore\controllers\AutoCompleteController;
use SrcCore\http\Response;
use MaarchCourrier\Tests\CourrierTestCase;

class AutocompleteControllerTest extends CourrierTestCase
{
    public function testGetContactsForGroups()
    {
        $autocompleteController = new AutoCompleteController();

        //  CREATE
        $request = $this->createRequest('GET');

        $aArgs = [
            'search'    => 'maarch',
            'type'      => 'all'
        ];
        $fullRequest = $request->withQueryParams($aArgs);

        $response     = $autocompleteController->getContacts($fullRequest, new Response());
        $responseBody = json_decode((string)$response->getBody());

        $this->assertIsArray($responseBody);
        $this->assertNotEmpty($responseBody);

        foreach ($responseBody as $value) {
            $this->assertIsInt($value->id);
            $this->assertIsString($value->contact);
            $this->assertIsString($value->address);
        }
    }

    public function testGetMaarchParapheurUsers()
    {
        $autocompleteController = new AutoCompleteController();

        //  GET (EMPTY BECAUSE USER ALREADY LINKED);
        $request = $this->createRequest('GET');

        $aArgs = [
            'search' => 'manfred',
            'exludeAlreadyConnected' => 'true'
        ];
        $fullRequest = $request->withQueryParams($aArgs);

        $response     = $autocompleteController->getMaarchParapheurUsers($fullRequest, new Response());
        $responseBody = json_decode((string)$response->getBody());

        $this->assertIsArray($responseBody);
        $this->assertEmpty($responseBody);

        $aArgs = [
            'search' => 'jane'
        ];
        $fullRequest = $request->withQueryParams($aArgs);

        $response     = $autocompleteController->getMaarchParapheurUsers($fullRequest, new Response());
        $responseBody = json_decode((string)$response->getBody());

        $this->assertIsArray($responseBody);
        foreach ($responseBody as $user) {
            $this->assertIsInt($user->id);
            $this->assertNotEmpty($user->firstname);
            $this->assertNotEmpty($user->lastname);
            $this->assertNotEmpty($user->email);
            $this->assertIsBool($user->substitute);
            $this->assertNotEmpty($user->idToDisplay);
            $this->assertIsInt($user->externalId->maarchParapheur);
        }
    }

    public function testGetCorrespondents()
    {
        $autocompleteController = new AutoCompleteController();

        //  GET
        $request = $this->createRequest('GET');

        $aArgs = [
            'search'    => 'maarch',
            'color'      => true
        ];
        $fullRequest = $request->withQueryParams($aArgs);

        $response     = $autocompleteController->getCorrespondents($fullRequest, new Response());
        $responseBody = json_decode((string)$response->getBody());

        foreach ($responseBody as $value) {
            $this->assertIsInt($value->id);
            $this->assertNotEmpty($value->type);
            $this->assertNotEmpty($value->id);
            if ($value->type == 'contact') {
                $this->assertNotEmpty($value->fillingRate->rate);
                $this->assertNotEmpty($value->fillingRate->thresholdLevel);
            }
        }
    }

    public function testGetUsers()
    {
        $autocompleteController = new AutoCompleteController();

        //  CREATE
        $request = $this->createRequest('GET');

        $aArgs = [
            'search'    => 'bain'
        ];
        $fullRequest = $request->withQueryParams($aArgs);

        $response     = $autocompleteController->getUsers($fullRequest, new Response());
        $responseBody = json_decode((string)$response->getBody());

        $this->assertIsArray($responseBody);
        $this->assertNotEmpty($responseBody);

        foreach ($responseBody as $value) {
            $this->assertSame('user', $value->type);
            $this->assertIsString($value->id);
            $this->assertNotEmpty($value->id);
            $this->assertIsString($value->idToDisplay);
            $this->assertNotEmpty($value->idToDisplay);
            $this->assertIsString($value->otherInfo);
        }
    }

    public function testGetUsersForAdministration()
    {
        $autocompleteController = new AutoCompleteController();

        //  GET
        $request = $this->createRequest('GET');

        $aArgs = [
            'search'    => 'bern',
        ];
        $fullRequest = $request->withQueryParams($aArgs);

        $response     = $autocompleteController->getUsersForAdministration($fullRequest, new Response());
        $responseBody = json_decode((string)$response->getBody());

        $this->assertIsArray($responseBody);
        $this->assertNotEmpty($responseBody);

        foreach ($responseBody as $value) {
            $this->assertSame('user', $value->type);
            $this->assertIsInt($value->id);
            $this->assertNotEmpty($value->id);
            $this->assertIsString($value->idToDisplay);
            $this->assertNotEmpty($value->idToDisplay);
        }

        // TEST WITH BBLIER
        $GLOBALS['login'] = 'bblier';
        $userInfo = \User\models\UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        $aArgs = [
            'search'    => 'blier',
        ];
        $fullRequest = $request->withQueryParams($aArgs);

        $response     = $autocompleteController->getUsersForAdministration($fullRequest, new Response());
        $responseBody = json_decode((string)$response->getBody());

        $this->assertIsArray($responseBody);
        $this->assertNotEmpty($responseBody);

        foreach ($responseBody as $value) {
            $this->assertSame('user', $value->type);
            $this->assertIsInt($value->id);
            $this->assertNotEmpty($value->id);
            $this->assertIsString($value->idToDisplay);
            $this->assertNotEmpty($value->idToDisplay);
        }

        $GLOBALS['login'] = 'superadmin';
        $userInfo = \User\models\UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];
    }

    public function testGetUsersForCircuit()
    {
        $autocompleteController = new AutoCompleteController();

        //  GET
        $request = $this->createRequest('GET');

        $aArgs = [
            'search'    => 'dau',
        ];
        $fullRequest = $request->withQueryParams($aArgs);

        $response     = $autocompleteController->getUsersForCircuit($fullRequest, new Response());
        $responseBody = json_decode((string)$response->getBody());

        $this->assertIsArray($responseBody);
        $this->assertNotEmpty($responseBody);

        foreach ($responseBody as $value) {
            $this->assertSame('user', $value->type);
            $this->assertIsInt($value->id);
            $this->assertNotEmpty($value->id);
            $this->assertIsString($value->idToDisplay);
            $this->assertNotEmpty($value->idToDisplay);
            $this->assertIsString($value->otherInfo);
        }
    }

    public function testGetContactsCompany()
    {
        $autocompleteController = new AutoCompleteController();

        //  GET
        $request = $this->createRequest('GET');

        $aArgs = [
            'search' => 'maar',
        ];
        $fullRequest = $request->withQueryParams($aArgs);

        $response     = $autocompleteController->getContactsCompany($fullRequest, new Response());
        $responseBody = json_decode((string)$response->getBody());

        $this->assertIsArray($responseBody);
        $this->assertNotEmpty($responseBody);

        $contact = $responseBody[0];
        $this->assertIsInt($contact->id);
        $this->assertNotEmpty($contact->company);
        $this->assertIsNumeric($contact->addressNumber);
        $this->assertNotEmpty($contact->addressStreet);
        $this->assertEmpty($contact->addressAdditional1);
        $this->assertEmpty($contact->addressAdditional2);
        $this->assertNotEmpty($contact->addressPostcode);
        $this->assertNotEmpty($contact->addressTown);
        $this->assertNotEmpty($contact->addressCountry);
    }

    public function testGetEntities()
    {
        $autocompleteController = new AutoCompleteController();

        //  GET
        $request = $this->createRequest('GET');

        $aArgs = [
            'search'    => 'mai',
        ];
        $fullRequest = $request->withQueryParams($aArgs);

        $response     = $autocompleteController->getEntities($fullRequest, new Response());
        $responseBody = json_decode((string)$response->getBody());

        $this->assertIsArray($responseBody);
        $this->assertNotEmpty($responseBody);

        foreach ($responseBody as $value) {
            $this->assertSame('entity', $value->type);
            $this->assertIsString($value->id);
            $this->assertNotEmpty($value->id);
            $this->assertIsString($value->idToDisplay);
            $this->assertNotEmpty($value->idToDisplay);
            $this->assertIsString($value->otherInfo);
        }
    }

    public function testGetStatuses()
    {
        $autocompleteController = new AutoCompleteController();

        //  GET
        $request = $this->createRequest('GET');

        $response     = $autocompleteController->getStatuses($request, new Response());
        $responseBody = json_decode((string)$response->getBody());

        $this->assertIsArray($responseBody);
        $this->assertNotEmpty($responseBody);

        foreach ($responseBody as $value) {
            $this->assertSame('status', $value->type);
            $this->assertIsString($value->id);
            $this->assertNotEmpty($value->id);
            $this->assertIsString($value->idToDisplay);
            $this->assertNotEmpty($value->idToDisplay);
            $this->assertIsString($value->otherInfo);
        }
    }

    public function testGetBanAddresses()
    {
        $autocompleteController = new AutoCompleteController();

        //  GET
        $request = $this->createRequest('GET');

        $aArgs = [
            'department'    => '75',
            'address'       => 'italie'
        ];
        $fullRequest = $request->withQueryParams($aArgs);

        $response     = $autocompleteController->getBanAddresses($fullRequest, new Response());
        $responseBody = json_decode((string)$response->getBody());

        $this->assertIsArray($responseBody);
        $this->assertNotEmpty($responseBody);

        foreach ($responseBody as $value) {
            $this->assertIsString($value->banId);
            $this->assertNotEmpty($value->banId);
            $this->assertIsString($value->number);
            $this->assertNotEmpty($value->number);
            $this->assertIsString($value->afnorName);
            $this->assertNotEmpty($value->afnorName);
            $this->assertIsString($value->postalCode);
            $this->assertNotEmpty($value->postalCode);
            $this->assertIsString($value->city);
            $this->assertNotEmpty($value->city);
            $this->assertIsString($value->address);
            $this->assertNotEmpty($value->address);
        }

        // Errors
        $aArgs = [
            'department'    => '100',
            'address'       => 'italie'
        ];
        $fullRequest = $request->withQueryParams($aArgs);

        $response     = $autocompleteController->getBanAddresses($fullRequest, new Response());
        $responseBody = json_decode((string)$response->getBody());

        $this->assertSame('Department indexes do not exist', $responseBody->errors);

        $response     = $autocompleteController->getBanAddresses($request, new Response());
        $responseBody = json_decode((string)$response->getBody());

        $this->assertSame('Bad Request', $responseBody->errors);
    }

    public function testGetAvailableContactsForM2M()
    {
        $autocompleteController = new AutoCompleteController();

        //  GET
        $request = $this->createRequest('GET');

        $aArgs = [
            'search' => 'Préfecture',
        ];
        $fullRequest = $request->withQueryParams($aArgs);

        $response     = $autocompleteController->getAvailableContactsForM2M($fullRequest, new Response());
        $responseBody = json_decode((string)$response->getBody());

        $this->assertIsArray($responseBody);
        $this->assertNotEmpty($responseBody);

        foreach ($responseBody as $contact) {
            $this->assertIsInt($contact->id);
            $this->assertNotEmpty($contact->m2m);
            $this->assertNotEmpty($contact->communicationMeans);
        }
    }

    public function testGetFolders()
    {
        $GLOBALS['login'] = 'bblier';
        $userInfo = \User\models\UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        $autocompleteController = new AutoCompleteController();

        //  GET
        $request = $this->createRequest('GET');

        $aArgs = [
            'search' => 'vie'
        ];
        $fullRequest = $request->withQueryParams($aArgs);

        $response     = $autocompleteController->getFolders($fullRequest, new Response());
        $responseBody = json_decode((string)$response->getBody());

        $this->assertIsArray($responseBody);
        $this->assertNotEmpty($responseBody);

        foreach ($responseBody as $value) {
            $this->assertIsInt($value->id);
            $this->assertNotEmpty($value->idToDisplay);
            $this->assertIsBool($value->isPublic);
            $this->assertEmpty($value->otherInfo);
        }

        $GLOBALS['login'] = 'superadmin';
        $userInfo = \User\models\UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];
    }

    public function testGetTags()
    {
        $autocompleteController = new AutoCompleteController();

        //  GET
        $request = $this->createRequest('GET');

        $aArgs = [
            'search'    => 'maa'
        ];
        $fullRequest = $request->withQueryParams($aArgs);

        $response     = $autocompleteController->getTags($fullRequest, new Response());
        $responseBody = json_decode((string)$response->getBody());

        $this->assertIsArray($responseBody);
        $this->assertNotEmpty($responseBody);

        foreach ($responseBody as $value) {
            $this->assertIsInt($value->id);
            $this->assertNotEmpty($value->id);
            $this->assertIsString($value->idToDisplay);
            $this->assertNotEmpty($value->idToDisplay);
        }
    }
}
