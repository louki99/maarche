<?php

/**
* Copyright Maarch since 2008 under licence GPLv3.
* See LICENCE.txt file at the root folder for more details.
* This file is part of Maarch software.
*
*/

use PHPUnit\Framework\TestCase;

class SearchControllerTest extends TestCase
{
    public function testGet()
    {
        $GLOBALS['login'] = 'bblier';
        $userInfo          = \User\models\UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id']     = $userInfo['id'];

        $searchController = new \Search\controllers\SearchController();

        // GET
        $environment = \Slim\Http\Environment::mock(['REQUEST_METHOD' => 'GET']);
        $request     = \Slim\Http\Request::createFromEnvironment($environment);

        $aArgs = [
            'resourceField' => 'Breaking News',
            'contactField'  => '',
            'limit'         => 2,
            'offset'        => 1,
            'order'         => 'desc',
            'orderBy'       => 'creationDate'
        ];

        $fullRequest = $request->withQueryParams($aArgs);
        $response     = $searchController->get($fullRequest, new \Slim\Http\Response());
        $this->assertSame(200, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody());

        $this->assertSame(3, $responseBody->count);

        $this->assertIsArray($responseBody->resources);
        $this->assertNotEmpty($responseBody->resources);
        $this->assertSame(3, count($responseBody->resources));
        foreach ($responseBody->resources as $resource) {
            $this->assertIsInt($resource->resId);
            $this->assertSame('incoming', $resource->category);
            $this->assertEmpty($resource->chrono);
            $this->assertEmpty($resource->barcode);
            $this->assertNotEmpty($resource->subject);
            $this->assertNotEmpty($resource->filename);
            $this->assertNotEmpty($resource->creationDate);
            $this->assertNotEmpty($resource->priority);
            $this->assertNotEmpty($resource->status);
            $this->assertNotEmpty($resource->destUser);
            $this->assertNotEmpty($resource->priorityColor);
            $this->assertNotEmpty($resource->statusLabel);
            $this->assertNotEmpty($resource->statusImage);
            $this->assertNotEmpty($resource->typeLabel);
            $this->assertNotEmpty($resource->destUserLabel);
            $this->assertIsBool($resource->hasDocument);
            $this->assertSame(true, $resource->hasDocument);
            $this->assertIsInt($resource->type);
            $this->assertIsArray($resource->senders);
            $this->assertIsArray($resource->recipients);
            $this->assertIsInt($resource->attachments);
        }
        
        $this->assertIsArray($responseBody->allResources);
        $this->assertNotEmpty($responseBody->allResources);
        $this->assertSame(3, count($responseBody->allResources));
        foreach ($responseBody->allResources as $resource) {
            $this->assertIsInt($resource);
        }

        $GLOBALS['login'] = 'superadmin';
        $userInfo          = \User\models\UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id']     = $userInfo['id'];

        // GET WITH SUPERADMIN
        $response     = $searchController->get($request, new \Slim\Http\Response());
        $this->assertSame(200, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody());

        $this->assertIsInt($responseBody->count);

        $this->assertIsArray($responseBody->resources);
        $this->assertNotEmpty($responseBody->resources);
        foreach ($responseBody->resources as $resource) {
            $this->assertIsInt($resource->resId);
            $this->assertNotEmpty($resource->category);
            $this->assertNotEmpty($resource->subject);
            $this->assertNotEmpty($resource->creationDate);
            $this->assertNotEmpty($resource->status);
            $this->assertNotEmpty($resource->typeLabel);
            $this->assertIsBool($resource->hasDocument);
            $this->assertIsInt($resource->type);
            $this->assertIsArray($resource->senders);
            $this->assertIsArray($resource->recipients);
            $this->assertIsInt($resource->attachments);
        }
        
        $this->assertIsArray($responseBody->allResources);
        $this->assertNotEmpty($responseBody->allResources);
        foreach ($responseBody->allResources as $resource) {
            $this->assertIsInt($resource);
        }

        // GET WITH CONTACT
        $aArgs = [
            'resourceField' => 'Breaking News',
            'contactField' => 'maarch'
        ];

        $fullRequest = $request->withQueryParams($aArgs);
        $response     = $searchController->get($fullRequest, new \Slim\Http\Response());
        $this->assertSame(200, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody());

        $this->assertIsInt($responseBody->count);

        $this->assertIsArray($responseBody->resources);
        $this->assertNotEmpty($responseBody->resources);
        foreach ($responseBody->resources as $resource) {
            $this->assertIsInt($resource->resId);
            $this->assertNotEmpty($resource->category);
            $this->assertNotEmpty($resource->subject);
            $this->assertNotEmpty($resource->creationDate);
            $this->assertNotEmpty($resource->status);
            $this->assertNotEmpty($resource->typeLabel);
            $this->assertIsBool($resource->hasDocument);
            $this->assertIsInt($resource->type);
            $this->assertIsArray($resource->senders);
            $this->assertNotEmpty($resource->senders);
            foreach ($resource->senders as $sender) {
                $this->assertNotEmpty($sender);
            }
            $this->assertIsArray($resource->recipients);
            $this->assertIsInt($resource->attachments);
        }
        $this->assertIsArray($responseBody->allResources);
        
        $this->assertNotEmpty($responseBody->allResources);
        foreach ($responseBody->allResources as $resource) {
            $this->assertIsInt($resource);
        }
    }
}
