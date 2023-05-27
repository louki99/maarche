<?php
/**
* Copyright Maarch since 2008 under licence GPLv3.
* See LICENCE.txt file at the root folder for more details.
* This file is part of Maarch software.

*
* @brief   DiffusionTypesControllerTest
*
* @author  dev <dev@maarch.org>
* @ingroup notifications
*/

namespace MaarchCourrier\Tests\app\notification;

use MaarchCourrier\Tests\CourrierTestCase;
use Notification\controllers\DiffusionTypesController;

class DiffusionTypesControllerTest extends CourrierTestCase
{
    public function testGetRecipientsByContact()
    {
        $diffusionTypesController = new DiffusionTypesController();

        foreach ($GLOBALS['resources'] as $resId) {
            $args = [
                'notification' => [
                    'diffusion_type' => 'contact'
                ],
                'request' => 'recipients',
                'event' => [
                    'record_id' => $resId
                ]
            ];
    
            $response = $diffusionTypesController->getItemsToNotify($args);
            foreach ($response as $contact) {
                $this->assertNotEmpty($contact['user_id']);
                $this->assertIsInt($contact['user_id']);
                $this->assertNotEmpty($contact['mail']);
                $this->assertIsString($contact['mail']);
            }

            $args['request'] = 'others';
            $response = $diffusionTypesController->getItemsToNotify($args);
            $this->assertIsArray($response);
        }
    }

    public function testGetRecipientsByCopie()
    {
        $diffusionTypesController = new DiffusionTypesController();

        foreach ($GLOBALS['resources'] as $resId) {
            $args = [
                'notification' => [
                    'diffusion_type' => 'copy_list'
                ],
                'request' => 'recipients',
                'event' => [
                    'record_id' => $resId,
                    'table_name' => 'res_letterbox'
                ]
            ];

            $response = $diffusionTypesController->getItemsToNotify($args);
            foreach ($response as $user) {
                $this->assertSame(20, $user['id']);
                $this->assertSame('jjonasz', $user['user_id']);
                $this->assertSame('Jean', $user['firstname']);
                $this->assertSame('JONASZ', $user['lastname']);
                $this->assertEmpty($user['phone']);
                $this->assertSame('yourEmail@domain.com', $user['mail']);
                $this->assertSame('OK', $user['status']);
            }

            $args['event']['table_name'] = 'notes';
            $args['event']['record_id'] = 1;
            $response = $diffusionTypesController->getItemsToNotify($args);
            $this->assertIsArray($response);

            $args['event']['table_name'] = 'listinstance';
            $args['event']['user_id'] = 19;
            $response = $diffusionTypesController->getItemsToNotify($args);
            $this->assertIsArray($response);
        }

        foreach ($GLOBALS['resources'] as $resId) {
            $args = [
                'notification' => [
                    'diffusion_type' => 'copy_list'
                ],
                'request' => 'res_id',
                'event' => [
                    'record_id' => $resId,
                    'table_name' => 'res_letterbox'
                ]
            ];

            $response = $diffusionTypesController->getItemsToNotify($args);
            $this->assertSame($resId, $response);

            $args['event']['table_name'] = 'notes';
            $args['event']['record_id'] = 1;
            $response = $diffusionTypesController->getItemsToNotify($args);
            $this->assertIsNumeric($response);

            $args['event']['table_name'] = 'listinstance';
            $args['event']['user_id'] = 19;
            $response = $diffusionTypesController->getItemsToNotify($args);
            $this->assertIsNumeric($response);
        }
    }

    public function testGetRecipientsByDestUser()
    {
        $diffusionTypesController = new DiffusionTypesController();

        foreach ($GLOBALS['resources'] as $resId) {
            $args = [
                'notification' => [
                    'diffusion_type' => 'dest_user',
                    'diffusion_properties' => 'NEW,COU,CLO,END,ATT,VAL,INIT,ESIG'
                ],
                'request' => 'recipients',
                'event' => [
                    'record_id' => $resId,
                    'table_name' => 'res_letterbox'
                ]
            ];

            $response = $diffusionTypesController->getItemsToNotify($args);
            foreach ($response as $user) {
                $this->assertSame(19, $user['id']);
                $this->assertSame('bbain', $user['user_id']);
                $this->assertSame('Barbara', $user['firstname']);
                $this->assertSame('BAIN', $user['lastname']);
                $this->assertEmpty($user['phone']);
                $this->assertSame('yourEmail@domain.com', $user['mail']);
                $this->assertSame('OK', $user['status']);
            }

            $args['event']['table_name'] = 'notes';
            $args['event']['record_id'] = 1;
            $response = $diffusionTypesController->getItemsToNotify($args);
            foreach ($response as $user) {
                $this->assertSame(19, $user['id']);
                $this->assertSame('bbain', $user['user_id']);
                $this->assertSame('Barbara', $user['firstname']);
                $this->assertSame('BAIN', $user['lastname']);
                $this->assertEmpty($user['phone']);
                $this->assertSame('yourEmail@domain.com', $user['mail']);
                $this->assertSame('OK', $user['status']);
            }

            $args['event']['table_name'] = 'listinstance';
            $args['event']['user_id'] = 19;
            $response = $diffusionTypesController->getItemsToNotify($args);
            foreach ($response as $user) {
                $this->assertSame(11, $user['id']);
                $this->assertSame('aackermann', $user['user_id']);
                $this->assertSame('Amanda', $user['firstname']);
                $this->assertSame('ACKERMANN', $user['lastname']);
                $this->assertEmpty($user['phone']);
                $this->assertSame('yourEmail@domain.com', $user['mail']);
                $this->assertSame('OK', $user['status']);
            }
        }

        foreach ($GLOBALS['resources'] as $resId) {
            $args = [
                'notification' => [
                    'diffusion_type' => 'dest_user',
                    'diffusion_properties' => 'NEW,COU,CLO,END,ATT,VAL,INIT,ESIG'
                ],
                'request' => 'res_id',
                'event' => [
                    'record_id' => $resId,
                    'table_name' => 'res_letterbox'
                ]
            ];

            $response = $diffusionTypesController->getItemsToNotify($args);
            $this->assertSame($resId, $response);

            $args['event']['table_name'] = 'notes';
            $args['event']['record_id'] = 1;
            $response = $diffusionTypesController->getItemsToNotify($args);
            $this->assertIsInt($response);

            $args['event']['table_name'] = 'listinstance';
            $args['event']['user_id'] = 19;
            $response = $diffusionTypesController->getItemsToNotify($args);
            $this->assertEmpty($response);
        }
    }

    public function testGetRecipientsByDestEntity()
    {
        $diffusionTypesController = new DiffusionTypesController();

        foreach ($GLOBALS['resources'] as $resId) {
            $args = [
                'notification' => [
                    'diffusion_type' => 'dest_entity'
                ],
                'request' => 'recipients',
                'event' => [
                    'record_id' => $resId
                ]
            ];

            $response = $diffusionTypesController->getItemsToNotify($args);
            foreach ($response as $entity) {
                $this->assertSame('PJS', $entity['entity_id']);
                $this->assertSame('Y', $entity['enabled']);
                $this->assertSame('mairie@maarchlesbains.fr', $entity['mail']);
            }
        }

        foreach ($GLOBALS['resources'] as $resId) {
            $args = [
                'notification' => [
                    'diffusion_type' => 'dest_entity',
                    'diffusion_properties' => 'NEW,COU,CLO,END,ATT,VAL,INIT,ESIG'
                ],
                'request' => 'res_id',
                'event' => [
                    'record_id' => $resId,
                    'table_name' => 'res_letterbox'
                ]
            ];

            $response = $diffusionTypesController->getItemsToNotify($args);
            $this->assertSame($resId, $response);

            $args['event']['table_name'] = 'notes';
            $args['event']['record_id'] = 1;
            $response = $diffusionTypesController->getItemsToNotify($args);
            $this->assertIsInt($response);

            $args['event']['table_name'] = 'listinstance';
            $args['event']['user_id'] = 19;
            $response = $diffusionTypesController->getItemsToNotify($args);
            $this->assertEmpty($response);
        }
    }

    public function testGetRecipientsByDestUserSign()
    {
        $diffusionTypesController = new DiffusionTypesController();

        foreach ($GLOBALS['resources'] as $resId) {
            $args = [
                'notification' => [
                    'diffusion_type' => 'dest_user_sign',
                    'diffusion_properties' => 'NEW,COU,CLO,END,ATT,VAL,INIT,ESIG,EVIS'
                ],
                'request' => 'recipients',
                'event' => [
                    'record_id' => $resId,
                    'table_name' => 'res_letterbox'
                ]
            ];

            $response = $diffusionTypesController->getItemsToNotify($args);
            $this->assertIsArray($response);

            $args['event']['table_name'] = 'notes';
            $args['event']['record_id'] = 1;
            $response = $diffusionTypesController->getItemsToNotify($args);
            $this->assertIsArray($response);

            $args['event']['table_name'] = 'listinstance';
            $args['event']['user_id'] = 19;
            $response = $diffusionTypesController->getItemsToNotify($args);
            $this->assertIsArray($response);
        }

        foreach ($GLOBALS['resources'] as $resId) {
            $args = [
                'notification' => [
                    'diffusion_type' => 'dest_user_sign',
                    'diffusion_properties' => 'NEW,COU,CLO,END,ATT,VAL,INIT,ESIG'
                ],
                'request' => 'res_id',
                'event' => [
                    'record_id' => $resId,
                    'table_name' => 'res_letterbox'
                ]
            ];

            $response = $diffusionTypesController->getItemsToNotify($args);
            $this->assertSame($resId, $response);

            $args['event']['table_name'] = 'notes';
            $args['event']['record_id'] = 1;
            $response = $diffusionTypesController->getItemsToNotify($args);
            $this->assertIsInt($response);

            $args['event']['table_name'] = 'listinstance';
            $args['event']['user_id'] = 19;
            $response = $diffusionTypesController->getItemsToNotify($args);
            $this->assertEmpty($response);
        }
    }

    public function testGetRecipientsByDestUserVisa()
    {
        $diffusionTypesController = new DiffusionTypesController();

        foreach ($GLOBALS['resources'] as $resId) {
            $args = [
                'notification' => [
                    'diffusion_type' => 'dest_user_visa',
                    'diffusion_properties' => 'NEW,COU,CLO,END,ATT,VAL,INIT,ESIG,EVIS'
                ],
                'request' => 'recipients',
                'event' => [
                    'record_id' => $resId,
                    'table_name' => 'res_letterbox'
                ]
            ];

            $response = $diffusionTypesController->getItemsToNotify($args);
            $this->assertIsArray($response);

            $args['event']['table_name'] = 'notes';
            $args['event']['record_id'] = 1;
            $response = $diffusionTypesController->getItemsToNotify($args);
            $this->assertIsArray($response);

            $args['event']['table_name'] = 'listinstance';
            $args['event']['user_id'] = 19;
            $response = $diffusionTypesController->getItemsToNotify($args);
            $this->assertIsArray($response);
        }

        foreach ($GLOBALS['resources'] as $resId) {
            $args = [
                'notification' => [
                    'diffusion_type' => 'dest_user_visa',
                    'diffusion_properties' => 'NEW,COU,CLO,END,ATT,VAL,INIT,ESIG'
                ],
                'request' => 'res_id',
                'event' => [
                    'record_id' => $resId,
                    'table_name' => 'res_letterbox'
                ]
            ];

            $response = $diffusionTypesController->getItemsToNotify($args);
            $this->assertSame($resId, $response);

            $args['event']['table_name'] = 'notes';
            $args['event']['record_id'] = 1;
            $response = $diffusionTypesController->getItemsToNotify($args);
            $this->assertIsInt($response);

            $args['event']['table_name'] = 'listinstance';
            $args['event']['user_id'] = 19;
            $response = $diffusionTypesController->getItemsToNotify($args);
            $this->assertEmpty($response);
        }
    }

    public function testGetRecipientsByEntity()
    {
        $diffusionTypesController = new DiffusionTypesController();

        $args = [
            'notification' => [
                'diffusion_type' => 'entity',
                'diffusion_properties' => 'PJS,DGA'
            ],
            'request' => 'recipients'
        ];

        $user = $diffusionTypesController->getItemsToNotify($args);

        $this->assertSame(8, $user[0]['id']);
        $this->assertSame('kkaar', $user[0]['user_id']);
        $this->assertSame('Katy', $user[0]['firstname']);
        $this->assertSame('KAAR', $user[0]['lastname']);
        $this->assertEmpty($user[0]['phone']);
        $this->assertSame('yourEmail@domain.com', $user[0]['mail']);
        $this->assertSame('OK', $user[0]['status']);

        $this->assertSame(17, $user[1]['id']);
        $this->assertSame('mmanfred', $user[1]['user_id']);
        $this->assertSame('Martin', $user[1]['firstname']);
        $this->assertSame('MANFRED', $user[1]['lastname']);
        $this->assertEmpty($user[1]['phone']);
        $this->assertSame('yourEmail@domain.com', $user[1]['mail']);
        $this->assertSame('OK', $user[1]['status']);

        $this->assertSame(19, $user[2]['id']);
        $this->assertSame('bbain', $user[2]['user_id']);
        $this->assertSame('Barbara', $user[2]['firstname']);
        $this->assertSame('BAIN', $user[2]['lastname']);
        $this->assertEmpty($user[2]['phone']);
        $this->assertSame('yourEmail@domain.com', $user[2]['mail']);
        $this->assertSame('OK', $user[2]['status']);

        $args['request'] = 'others';
        $response = $diffusionTypesController->getItemsToNotify($args);
        $this->assertIsArray($response);
    }

    public function testGetRecipientsByGroup()
    {
        $diffusionTypesController = new DiffusionTypesController();

        $args = [
            'notification' => [
                'diffusion_type' => 'group',
                'diffusion_properties' => 'COURRIER,RESP_COURRIER'
            ],
            'request' => 'recipients'
        ];

        $user = $diffusionTypesController->getItemsToNotify($args);
        $this->assertSame(18, $user[0]['id']);
        $this->assertSame('ddaull', $user[0]['user_id']);
        $this->assertSame('Denis', $user[0]['firstname']);
        $this->assertSame('DAULL', $user[0]['lastname']);
        $this->assertEmpty($user[0]['phone']);
        $this->assertSame('yourEmail@domain.com', $user[0]['mail']);
        $this->assertSame('OK', $user[0]['status']);

        $this->assertSame(21, $user[1]['id']);
        $this->assertSame('bblier', $user[1]['user_id']);
        $this->assertSame('Bernard', $user[1]['firstname']);
        $this->assertSame('BLIER', $user[1]['lastname']);
        $this->assertEmpty($user[1]['phone']);
        $this->assertSame('yourEmail@domain.com', $user[1]['mail']);
        $this->assertSame('OK', $user[1]['status']);

        $args['request'] = 'others';
        $response = $diffusionTypesController->getItemsToNotify($args);
        $this->assertIsArray($response);
    }

    public function testGetRecipientsByUser()
    {
        $diffusionTypesController = new DiffusionTypesController();

        $args = [
            'notification' => [
                'diffusion_type' => 'user',
                'diffusion_properties' => '19,20'
            ],
            'request' => 'recipients'
        ];

        $user = $diffusionTypesController->getItemsToNotify($args);
        $this->assertSame(19, $user[0]['id']);
        $this->assertSame('bbain', $user[0]['user_id']);
        $this->assertSame('Barbara', $user[0]['firstname']);
        $this->assertSame('BAIN', $user[0]['lastname']);
        $this->assertEmpty($user[0]['phone']);
        $this->assertSame('yourEmail@domain.com', $user[0]['mail']);
        $this->assertSame('OK', $user[0]['status']);
        
        $this->assertSame(20, $user[1]['id']);
        $this->assertSame('jjonasz', $user[1]['user_id']);
        $this->assertSame('Jean', $user[1]['firstname']);
        $this->assertSame('JONASZ', $user[1]['lastname']);
        $this->assertEmpty($user[1]['phone']);
        $this->assertSame('yourEmail@domain.com', $user[1]['mail']);
        $this->assertSame('OK', $user[1]['status']);

        $args['request'] = 'others';
        $response = $diffusionTypesController->getItemsToNotify($args);
        $this->assertIsArray($response);
    }
}
