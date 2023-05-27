<?php

/**
* Copyright Maarch since 2008 under licence GPLv3.
* See LICENCE.txt file at the root folder for more details.
* This file is part of Maarch software.
*
*/

namespace MaarchCourrier\Tests\app\basket;

use Basket\controllers\BasketController;
use Group\models\GroupModel;
use SrcCore\http\Response;
use MaarchCourrier\Tests\CourrierTestCase;
use User\models\UserBasketPreferenceModel;

class BasketControllerTest extends CourrierTestCase
{
    public function testCreate()
    {
        $basketController = new BasketController();

        //  CREATE
        $args = [
            'id'                => 'TEST-BASKET123',
            'basket_name'       => 'TEST-BASKET123-NAME',
            'basket_desc'       => 'TEST BASKET123 DESCRIPTION',
            'clause'            => '1=2',
            'isSearchBasket'    => true,
            'color'             => '#123456'
        ];
        $fullRequest = $this->createRequestWithBody('POST', $args);

        $response     = $basketController->create($fullRequest, new Response());
        $responseBody = json_decode((string)$response->getBody());

        $this->assertSame('TEST-BASKET123', $responseBody->basket);

        //  READ
        $request = $this->createRequest('GET');
        $response       = $basketController->getById($request, new Response(), ['id' => 'TEST-BASKET123']);
        $responseBody   = json_decode((string)$response->getBody());

        $this->assertSame('TEST-BASKET123', $responseBody->basket->basket_id);
        $this->assertSame('TEST-BASKET123-NAME', $responseBody->basket->basket_name);
        $this->assertSame('TEST BASKET123 DESCRIPTION', $responseBody->basket->basket_desc);
        $this->assertSame('1=2', $responseBody->basket->basket_clause);
        $this->assertSame('N', $responseBody->basket->is_visible);
        $this->assertSame('N', $responseBody->basket->flag_notif);
        $this->assertSame('#123456', $responseBody->basket->color);
    }

    public function testUpdate()
    {
        $basketController = new BasketController();

        //  UPDATE
        $args = [
            'basket_name'       => 'TEST-BASKET123-UPDATED',
            'basket_desc'       => 'TEST BASKET123 DESCRIPTION UPDATED',
            'clause'            => '1=3',
            'isSearchBasket'    => false,
            'flagNotif'         => true,
            'color'             => '#111222'
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $args);

        $response     = $basketController->update($fullRequest, new Response(), ['id' => 'TEST-BASKET123']);
        $responseBody = json_decode((string)$response->getBody());

        $this->assertSame('success', $responseBody->success);

        //  READ
        $request = $this->createRequest('GET');
        $response       = $basketController->getById($request, new Response(), ['id' => 'TEST-BASKET123']);
        $responseBody   = json_decode((string)$response->getBody());

        $this->assertSame('TEST-BASKET123', $responseBody->basket->basket_id);
        $this->assertSame('TEST-BASKET123-UPDATED', $responseBody->basket->basket_name);
        $this->assertSame('TEST BASKET123 DESCRIPTION UPDATED', $responseBody->basket->basket_desc);
        $this->assertSame('1=3', $responseBody->basket->basket_clause);
        $this->assertSame('Y', $responseBody->basket->is_visible);
        $this->assertSame('Y', $responseBody->basket->flag_notif);
        $this->assertSame('#111222', $responseBody->basket->color);
    }

    public function testCreateGroup()
    {
        $basketController = new BasketController();

        //  CREATE
        $args = [
            'group_id'      => 'AGENT',
            'list_display'  => [
                'templateColumns' => 0,
                'subInfos' => []
            ],
            'groupActions'  => [
                [
                    'id'                    => '1',
                    'where_clause'          => '1=2',
                    'used_in_basketlist'    => false,
                    'used_in_action_page'   => true,
                    'default_action_list'   => true,
                    'checked'               => true,
                    'redirects'             => [
                        [
                            'entity_id'     => '',
                            'keyword'       => 'MY_ENTITIES',
                            'redirect_mode' => 'ENTITY'
                        ]
                    ]
                ]
            ]
        ];
        $fullRequest = $this->createRequestWithBody('POST', $args);

        $response = $basketController->createGroup($fullRequest, new Response(), ['id' => 'TEST-BASKET123']);

        $this->assertSame(204, $response->getStatusCode());

        //  READ
        $request = $this->createRequest('GET');
        $response       = $basketController->getGroups($request, new Response(), ['id' => 'TEST-BASKET123']);
        $responseBody   = json_decode((string)$response->getBody());

        $this->assertSame('AGENT', $responseBody->groups[0]->group_id);
        $this->assertSame('TEST-BASKET123', $responseBody->groups[0]->basket_id);
        $this->assertNotEmpty($responseBody->groups[0]->list_display);
        $this->assertIsArray($responseBody->groups[0]->list_display->subInfos);
        $this->assertSame(0, $responseBody->groups[0]->list_display->templateColumns);
        $this->assertEmpty($responseBody->groups[0]->list_display->subInfo);
        $this->assertIsArray($responseBody->groups[0]->groupActions);
        $this->assertNotNull($responseBody->groups[0]->groupActions);
        foreach ($responseBody->groups[0]->groupActions as $groupAction) {
            if ($groupAction->id == 1) {
                $this->assertSame(1, $groupAction->id);
                $this->assertSame('1=2', $groupAction->where_clause);
                $this->assertSame('N', $groupAction->used_in_basketlist);
                $this->assertSame('Y', $groupAction->used_in_action_page);
                $this->assertSame('Y', $groupAction->default_action_list);
                $this->assertIsArray($groupAction->redirects);
                $this->assertNotNull($groupAction->redirects);
                $this->assertSame('', $groupAction->redirects[0]->entity_id);
                $this->assertSame('MY_ENTITIES', $groupAction->redirects[0]->keyword);
                $this->assertSame('ENTITY', $groupAction->redirects[0]->redirect_mode);
            }
        }

        $this->assertIsArray($responseBody->allGroups);
        $this->assertNotNull($responseBody->allGroups);

        $users = GroupModel::getUsersById(['select' => ['id'], 'id' => 2]);
        $group = GroupModel::getByGroupId(['select' => ['id'], 'groupId' => 'AGENT']);
        foreach ($users as $user) {
            $preference = UserBasketPreferenceModel::get([
                'select'    => ['display'],
                'where'     => ['user_serial_id = ?', 'group_serial_id = ?', 'basket_id = ?'],
                'data'      => [$user['id'], $group['id'], 'TEST-BASKET123']
            ]);
            $this->assertNotNull($preference[0]);
            $this->assertSame(true, $preference[0]['display']);
        }
    }

    public function testUpdateGroup()
    {
        $basketController = new BasketController();

        //  CREATE
        $args = [
            'list_display' => [
                'templateColumns' => 2,
                'subInfos'        => [['value' => 'getPriority', 'cssClasses' => ['class1', 'class2']], ['value' => 'getCategory', 'cssClasses' => ['class3', 'class4']]]
            ],
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $args);

        $response     = $basketController->updateGroup($fullRequest, new Response(), ['id' => 'TEST-BASKET123', 'groupId' => 'AGENT']);
        $responseBody = json_decode((string)$response->getBody());

        $this->assertSame('success', $responseBody->success);

        //  READ
        $request = $this->createRequest('GET');
        $response       = $basketController->getGroups($request, new Response(), ['id' => 'TEST-BASKET123']);
        $responseBody   = json_decode((string)$response->getBody());

        $this->assertSame('AGENT', $responseBody->groups[0]->group_id);
        $this->assertSame('TEST-BASKET123', $responseBody->groups[0]->basket_id);
        $this->assertSame(2, $responseBody->groups[0]->list_display->templateColumns);
        $this->assertSame('getPriority', $responseBody->groups[0]->list_display->subInfos[0]->value);
        $this->assertSame('class1', $responseBody->groups[0]->list_display->subInfos[0]->cssClasses[0]);
        $this->assertSame('class2', $responseBody->groups[0]->list_display->subInfos[0]->cssClasses[1]);
        $this->assertSame('getCategory', $responseBody->groups[0]->list_display->subInfos[1]->value);
        $this->assertSame('class3', $responseBody->groups[0]->list_display->subInfos[1]->cssClasses[0]);
        $this->assertSame('class4', $responseBody->groups[0]->list_display->subInfos[1]->cssClasses[1]);
    }

    public function testUpdateGroupActions()
    {
        $basketController = new BasketController();

        //  CREATE
        $args = [
            'groupActions'  => [
                [
                    'id'                    => '1',
                    'where_clause'          => '1=1',
                    'used_in_basketlist'    => true,
                    'used_in_action_page'   => true,
                    'default_action_list'   => true,
                    'checked'               => true,
                    'redirects'             => [
                        [
                            'entity_id'     => '',
                            'keyword'       => 'ALL_ENTITIES',
                            'redirect_mode' => 'ENTITY'
                        ]
                    ]
                ],
                [
                    'id'                    => '4',
                    'where_clause'          => '1=4',
                    'used_in_basketlist'    => false,
                    'used_in_action_page'   => true,
                    'default_action_list'   => false,
                    'checked'               => true,
                    'redirects'             => [
                        [
                            'entity_id'     => 'PSO',
                            'keyword'       => '',
                            'redirect_mode' => 'ENTITY'
                        ],
                        [
                            'entity_id'     => 'PSF',
                            'keyword'       => '',
                            'redirect_mode' => 'USERS'
                        ]
                    ]
                ]
            ]
        ];
        $fullRequest = $this->createRequestWithBody('PUT', $args);

        $response     = $basketController->updateGroupActions($fullRequest, new Response(), ['id' => 'TEST-BASKET123', 'groupId' => 'AGENT']);
        $responseBody = json_decode((string)$response->getBody());

        $this->assertSame('success', $responseBody->success);

        //  READ
        $request = $this->createRequest('GET');
        $response       = $basketController->getGroups($request, new Response(), ['id' => 'TEST-BASKET123']);
        $responseBody   = json_decode((string)$response->getBody());

        $this->assertSame('AGENT', $responseBody->groups[0]->group_id);
        $this->assertSame('TEST-BASKET123', $responseBody->groups[0]->basket_id);
        $this->assertIsArray($responseBody->groups[0]->groupActions);
        $this->assertNotNull($responseBody->groups[0]->groupActions);

        foreach ($responseBody->groups[0]->groupActions as $groupAction) {
            if ($groupAction->id == 1) {
                $this->assertSame(1, $groupAction->id);
                $this->assertSame('1=1', $groupAction->where_clause);
                $this->assertSame('Y', $groupAction->used_in_basketlist);
                $this->assertSame('Y', $groupAction->used_in_action_page);
                $this->assertSame('Y', $groupAction->default_action_list);
                $this->assertIsArray($groupAction->redirects);
                $this->assertNotNull($groupAction->redirects);
                $this->assertSame('', $groupAction->redirects[0]->entity_id);
                $this->assertSame('ALL_ENTITIES', $groupAction->redirects[0]->keyword);
                $this->assertSame('ENTITY', $groupAction->redirects[0]->redirect_mode);
            } elseif ($groupAction->id == 4) {
                $this->assertSame(4, $groupAction->id);
                $this->assertSame('1=4', $groupAction->where_clause);
                $this->assertSame('N', $groupAction->used_in_basketlist);
                $this->assertSame('Y', $groupAction->used_in_action_page);
                $this->assertSame('N', $groupAction->default_action_list);
                $this->assertIsArray($groupAction->redirects);
                $this->assertNotNull($groupAction->redirects);
                $this->assertSame('PSO', $groupAction->redirects[0]->entity_id);
                $this->assertSame('', $groupAction->redirects[0]->keyword);
                $this->assertSame('ENTITY', $groupAction->redirects[0]->redirect_mode);
                $this->assertSame('PSF', $groupAction->redirects[1]->entity_id);
                $this->assertSame('', $groupAction->redirects[1]->keyword);
                $this->assertSame('USERS', $groupAction->redirects[1]->redirect_mode);
            }
        }
    }

    public function testDeleteGroup()
    {
        $basketController = new BasketController();

        //  DELETE
        $request = $this->createRequest('DELETE');
        $response       = $basketController->deleteGroup($request, new Response(), ['id' => 'TEST-BASKET123', 'groupId' => 'AGENT']);
        $responseBody   = json_decode((string)$response->getBody());

        $this->assertSame('success', $responseBody->success);

        //  READ
        $request = $this->createRequest('GET');
        $response       = $basketController->getGroups($request, new Response(), ['id' => 'TEST-BASKET123']);
        $responseBody   = json_decode((string)$response->getBody());

        $this->assertEmpty($responseBody->groups);
    }

    public function testGet()
    {
        $basketController = new BasketController();

        //  GET
        $request = $this->createRequest('GET');
        $response       = $basketController->get($request, new Response());
        $responseBody   = json_decode((string)$response->getBody());

        $this->assertIsArray($responseBody->baskets);
        $this->assertNotNull($responseBody->baskets);
    }

    public function testDelete()
    {
        $basketController = new BasketController();

        //  DELETE
        $request = $this->createRequest('DELETE');
        $response       = $basketController->delete($request, new Response(), ['id' => 'TEST-BASKET123']);
        $responseBody   = json_decode((string)$response->getBody());

        $this->assertIsArray($responseBody->baskets);

        //  READ
        $request = $this->createRequest('GET');
        $response       = $basketController->getById($request, new Response(), ['id' => 'TEST-BASKET123']);
        $responseBody   = json_decode((string)$response->getBody());

        $this->assertSame('Basket not found', $responseBody->errors);
    }

    public function testGetSorted()
    {
        $basketController = new BasketController();

        //  READ
        $request = $this->createRequest('GET');
        $response       = $basketController->getSorted($request, new Response());
        $responseBody   = json_decode((string)$response->getBody());

        $this->assertIsArray($responseBody->baskets);
    }

    public function testUpdateSort()
    {
        $basketController = new BasketController();

        //  READ
        $request = $this->createRequest('GET');
        $response = $basketController->getSorted($request, new Response());
        $responseBody = json_decode((string)$response->getBody(), true);
        $baskets = $responseBody['baskets'];
        $basketId = $baskets[0]['basket_id'];

        //  PUT

        // DOWN
        $firstBasket = $baskets[0];
        $baskets[0] = $baskets[1];
        $baskets[1] = $firstBasket;

        $args = $baskets;

        $fullRequest = $this->createRequestWithBody('PUT', $args);

        $response       = $basketController->updateSort($fullRequest, new Response(), ['id' => $basketId]);
        $responseBody   = json_decode((string)$response->getBody(), true);

        $this->assertIsArray($responseBody['baskets']);
        $this->assertSame($basketId, $responseBody['baskets'][1]['basket_id']);

        // UP
        $baskets = $responseBody['baskets'];

        $firstBasket = $baskets[0];
        $baskets[0] = $baskets[1];
        $baskets[1] = $firstBasket;

        $args = $baskets;

        $fullRequest = $this->createRequestWithBody('PUT', $args);

        $response       = $basketController->updateSort($fullRequest, new Response(), ['id' => $basketId]);
        $responseBody   = json_decode((string)$response->getBody(), true);

        $this->assertIsArray($responseBody['baskets']);
        $this->assertSame($basketId, $responseBody['baskets'][0]['basket_id']);
    }
}
