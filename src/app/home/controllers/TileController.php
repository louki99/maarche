<?php

/**
 * Copyright Maarch since 2008 under licence GPLv3.
 * See LICENCE.txt file at the root folder for more details.
 * This file is part of Maarch software.
 *
 */

/**
 * @brief Tile Controller
 * @author dev@maarch.org
 */

namespace Home\controllers;

use Action\models\ActionModel;
use Attachment\models\AttachmentTypeModel;
use Basket\models\BasketModel;
use Basket\models\GroupBasketModel;
use Contact\controllers\ContactController;
use Contact\models\ContactModel;
use CustomField\models\CustomFieldModel;
use Docserver\models\DocserverModel;
use Doctype\models\DoctypeModel;
use Entity\models\EntityModel;
use Entity\models\ListTemplateModel;
use ExternalSignatoryBook\controllers\FastParapheurController;
use Folder\controllers\FolderController;
use Folder\models\FolderModel;
use Folder\models\ResourceFolderModel;
use Group\controllers\PrivilegeController;
use Group\models\GroupModel;
use History\controllers\HistoryController;
use Home\models\TileModel;
use IndexingModel\models\IndexingModelModel;
use Notification\models\NotificationModel;
use Priority\models\PriorityModel;
use Resource\models\ResModel;
use Resource\models\UserFollowedResourceModel;
use Respect\Validation\Validator;
use Search\controllers\SearchController;
use Search\models\SearchModel;
use Search\models\SearchTemplateModel;
use Shipping\models\ShippingTemplateModel;
use Slim\Psr7\Request;
use SrcCore\http\Response;
use SrcCore\controllers\PreparedClauseController;
use SrcCore\models\CoreConfigModel;
use SrcCore\models\CurlModel;
use SrcCore\models\DatabaseModel;
use Status\models\StatusModel;
use Tag\models\TagModel;
use Template\models\TemplateModel;
use User\controllers\UserController;
use User\models\UserEntityModel;
use User\models\UserModel;

class TileController
{
    const TYPES = ['myLastResources', 'basket', 'searchTemplate', 'followedMail', 'folder', 'externalSignatoryBook', 'shortcut'];
    const VIEWS = ['list', 'summary', 'chart'];

    public function get(Request $request, Response $response)
    {
        $tiles = TileModel::get([
            'select'    => ['*'],
            'where'     => ['user_id = ?'],
            'data'      => [$GLOBALS['id']]
        ]);

        foreach ($tiles as $key => $tile) {
            $tiles[$key]['userId'] = $tile['user_id'];
            unset($tiles[$key]['user_id']);
            $tiles[$key]['parameters'] = json_decode($tile['parameters'], true);
            TileController::getShortDetails($tiles[$key]);
        }

        return $response->withJson(['tiles' => $tiles]);
    }

    public function getById(Request $request, Response $response, array $args)
    {
        $tile = TileModel::getById([
            'select'    => ['*'],
            'id'        => $args['id']
        ]);
        if (empty($tile) || $tile['user_id'] != $GLOBALS['id']) {
            return $response->withStatus(400)->withJson(['errors' => 'Tile out of perimeter']);
        }

        $tile['parameters'] = json_decode($tile['parameters'], true);

        $control = TileController::getDetails($tile);
        if (!empty($control['errors'])) {
            return $response->withStatus(400)->withJson(['errors' => $control['errors']]);
        }

        return $response->withJson(['tile' => $tile]);
    }

    public function create(Request $request, Response $response)
    {
        $body = $request->getParsedBody();

        if (empty($body)) {
            return $response->withStatus(400)->withJson(['errors' => 'Body is empty']);
        } elseif (!Validator::stringType()->notEmpty()->validate($body['type'] ?? null) || !in_array($body['type'], TileController::TYPES)) {
            return $response->withStatus(400)->withJson(['errors' => 'Body type is empty, not a string or not valid']);
        } elseif (!Validator::stringType()->notEmpty()->validate($body['view'] ?? null) || !in_array($body['view'], TileController::VIEWS)) {
            return $response->withStatus(400)->withJson(['errors' => 'Body view is empty, not a string or not valid']);
        } elseif (!Validator::intVal()->validate($body['position'] ?? null)) {
            return $response->withStatus(400)->withJson(['errors' => 'Body position is not set or not an integer']);
        }

        $tiles = TileModel::get([
            'select'    => [1],
            'where'     => ['user_id = ?'],
            'data'      => [$GLOBALS['id']]
        ]);
        if (count($tiles) >= 6) {
            return $response->withStatus(400)->withJson(['errors' => 'Too many tiles (limited to 6)']);
        }
        $control = TileController::controlParameters($body);
        if (!empty($control['errors'])) {
            return $response->withStatus(400)->withJson(['errors' => $control['errors']]);
        }

        $id = TileModel::create([
            'user_id'       => $GLOBALS['id'],
            'type'          => $body['type'],
            'view'          => $body['view'],
            'position'      => $body['position'],
            'color'         => $body['color'] ?? null,
            'parameters'    => empty($body['parameters']) ? '{}' : json_encode($body['parameters'])
        ]);

        HistoryController::add([
            'tableName'    => 'tiles',
            'recordId'     => $id,
            'eventType'    => 'ADD',
            'eventId'      => 'tileCreation',
            'info'         => 'tile creation'
        ]);

        return $response->withJson(['id' => $id]);
    }

    public function update(Request $request, Response $response, array $args)
    {
        $tile = TileModel::getById(['select' => ['user_id'], 'id' => $args['id']]);
        if (empty($tile) || $tile['user_id'] != $GLOBALS['id']) {
            return $response->withStatus(400)->withJson(['errors' => 'Tile out of perimeter']);
        }

        $body = $request->getParsedBody();

        if (empty($body)) {
            return $response->withStatus(400)->withJson(['errors' => 'Body is empty']);
        } elseif (!Validator::stringType()->notEmpty()->validate($body['view'] ?? null) || !in_array($body['view'], TileController::VIEWS)) {
            return $response->withStatus(400)->withJson(['errors' => 'Body view is empty, not a string or not valid']);
        }

        if ($body['view'] != 'chart') {
            unset($body['parameters']['chartMode']);
        }

        TileModel::update([
            'set'   => [
                'view'          => $body['view'],
                'color'         => $body['color'] ?? null,
                'parameters'    => empty($body['parameters']) ? '{}' : json_encode($body['parameters'])
            ],
            'where' => ['id = ?'],
            'data'  => [$args['id']]
        ]);

        HistoryController::add([
            'tableName'    => 'tiles',
            'recordId'     => $args['id'],
            'eventType'    => 'UP',
            'eventId'      => 'tileModification',
            'info'         => 'tile modification'
        ]);

        return $response->withStatus(204);
    }

    public function delete(Request $request, Response $response, array $args)
    {
        $tile = TileModel::getById(['select' => ['user_id'], 'id' => $args['id']]);
        if (empty($tile) || $tile['user_id'] != $GLOBALS['id']) {
            return $response->withStatus(400)->withJson(['errors' => 'Tile out of perimeter']);
        }

        TileModel::delete([
            'where' => ['id = ?'],
            'data'  => [$args['id']]
        ]);

        HistoryController::add([
            'tableName'    => 'tiles',
            'recordId'     => $args['id'],
            'eventType'    => 'DEL',
            'eventId'      => 'tileSuppression',
            'info'         => 'tile suppression'
        ]);

        return $response->withStatus(204);
    }

    public function updatePositions(Request $request, Response $response)
    {
        $body = $request->getParsedBody();

        if (empty($body)) {
            return $response->withStatus(400)->withJson(['errors' => 'Body is empty']);
        } elseif (!Validator::arrayType()->notEmpty()->validate($body['tiles'] ?? null)) {
            return $response->withStatus(400)->withJson(['errors' => 'Body tiles is empty not not an array']);
        }

        $userTiles = TileModel::get(['select' => ['id'], 'where' => ['user_id = ?'], 'data' => [$GLOBALS['id']]]);
        if (count($userTiles) != count($body['tiles'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body tiles do not match user tiles']);
        }
        $allTiles = array_column($userTiles, 'id');
        foreach ($body['tiles'] as $tile) {
            if (!in_array($tile['id'], $allTiles)) {
                return $response->withStatus(400)->withJson(['errors' => 'Tiles out of perimeter']);
            }
        }

        foreach ($body['tiles'] as $tile) {
            TileModel::update([
                'set'   => [
                    'position' => $tile['position'],
                ],
                'where' => ['id = ?'],
                'data'  => [$tile['id']]
            ]);
        }

        return $response->withStatus(204);
    }

    private static function controlParameters(array $args)
    {
        if (in_array($args['type'], ['basket', 'folder', 'shortcut', 'searchTemplate'])) {
            if (!Validator::arrayType()->notEmpty()->validate($args['parameters'] ?? null)) {
                return ['errors' => 'Body parameters is empty or not an array'];
            }
        }
        if ($args['type'] == 'basket') {
            if (!Validator::intVal()->validate($args['parameters']['basketId'] ?? null)) {
                return ['errors' => 'Body[parameters] basketId is empty or not an integer'];
            } elseif (!Validator::intVal()->validate($args['parameters']['groupId'] ?? null)) {
                return ['errors' => 'Body[parameters] groupId is empty or not an integer'];
            }
            $basket = BasketModel::getById(['select' => ['basket_id'], 'id' => $args['parameters']['basketId']]);
            $group = GroupModel::getById(['select' => ['group_id'], 'id' => $args['parameters']['groupId']]);
            if (empty($basket) || empty($group)) {
                return ['errors' => 'Basket or group do not exist'];
            } elseif (!BasketModel::hasGroup(['id' => $basket['basket_id'], 'groupId' => $group['group_id']])) {
                return ['errors' => 'Basket is not linked to this group'];
            } elseif (!UserModel::hasGroup(['id' => $GLOBALS['id'], 'groupId' => $group['group_id']])) {
                return ['errors' => 'User is not linked to this group'];
            }
        } elseif ($args['type'] == 'folder') {
            if (!Validator::intVal()->validate($args['parameters']['folderId'] ?? null)) {
                return ['errors' => 'Body[parameters] folderId is empty or not an integer'];
            }

            $folder = FolderController::getScopeFolders(['login' => $GLOBALS['login'], 'folderId' => $args['parameters']['folderId']]);
            if (empty($folder[0])) {
                return ['errors' => 'Folder not found or out of your perimeter'];
            }
        } elseif ($args['type'] == 'shortcut') {
            if (!Validator::stringType()->validate($args['parameters']['privilegeId'] ?? null)) {
                return ['errors' => 'Body[parameters] privilegeId is empty or not a string'];
            } elseif ($args['view'] != 'summary') {
                return ['errors' => 'Shortcut tile must have summary view'];
            }
        } elseif ($args['type'] == 'searchTemplate') {
            if (!Validator::intVal()->validate($args['parameters']['searchTemplateId'] ?? null)) {
                return ['errors' => 'Body[parameters] searchTemplateId is empty or not an integer'];
            }

            $searchTemplate = SearchTemplateModel::get(['select' => [1], 'where' => ['id = ?', 'user_id = ?'], 'data' => [$args['parameters']['searchTemplateId'], $GLOBALS['id']]]);
            if (empty($searchTemplate)) {
                return ['errors' => 'Body[parameters] searchTemplateId is out of perimeter'];
            }
        }

        return true;
    }

    private static function getShortDetails(array &$tile)
    {
        if ($tile['type'] == 'basket') {
            $basket = BasketModel::getById(['select' => ['basket_name', 'basket_id'], 'id' => $tile['parameters']['basketId']]);
            $group  = GroupModel::getById(['select' => ['group_desc', 'group_id'], 'id' => $tile['parameters']['groupId']]);
            $tile['label'] = "{$basket['basket_name']} ({$group['group_desc']})";

            $groupBasket = GroupBasketModel::get([
                'select' => ['list_event'],
                'where'  => ['basket_id = ?', 'group_id = ?'],
                'data'   => [$basket['basket_id'], $group['group_id']]
            ]);

            $tile['basketRoute'] = null;
            if ($groupBasket[0]['list_event'] == 'processDocument') {
                $tile['basketRoute'] = '/process/users/:userId/groups/:groupId/baskets/:basketId/resId/:resId';
            } elseif ($groupBasket[0]['list_event'] == 'documentDetails') {
                $tile['basketRoute'] = '/resources/:resId';
            } elseif ($groupBasket[0]['list_event'] == 'signatureBookAction') {
                $tile['basketRoute'] = '/signatureBook/users/:userId/groups/:groupId/baskets/:basketId/resources/:resId';
            }
        } elseif ($tile['type'] == 'folder') {
            $folder = FolderModel::getById(['select' => ['label'], 'id' => $tile['parameters']['folderId']]);
            $tile['label'] = "{$folder['label']}";
        } elseif ($tile['type'] == 'externalSignatoryBook') {
            $loadedXml = CoreConfigModel::getXmlLoaded(['path' => 'modules/visa/xml/remoteSignatoryBooks.xml']);
            if (empty($loadedXml)) {
                return false;
            }
            $enabledExternalSignatoryBook = (string)$loadedXml->signatoryBookEnabled;
            if ($enabledExternalSignatoryBook == 'maarchParapheur') {
                $tile['externalSignatoryBookUrl'] = rtrim((string)($loadedXml->xpath('//signatoryBook[id=\'maarchParapheur\']/url')[0]), '/');
            } else if ($enabledExternalSignatoryBook == 'fastParapheur') {
                $fastParapheurUrl = str_replace('/parapheur-ws/rest/v1', '', (string)($loadedXml->xpath('//signatoryBook[id=\'fastParapheur\']/url')[0]));
                $tile['externalSignatoryBookUrl'] = rtrim($fastParapheurUrl, "/");
            }
        } elseif ($tile['type'] == 'searchTemplate') {
            $searchTemplate = SearchTemplateModel::get(['select' => ['label'], 'where' => ['id = ?', 'user_id = ?'], 'data' => [$tile['parameters']['searchTemplateId'], $GLOBALS['id']]]);
            $tile['label']  = "{$searchTemplate[0]['label']}";
        }

        return true;
    }

    private static function getDetails(array &$tile)
    {
        if ($tile['type'] == 'basket') {
            $control = TileController::getBasketDetails($tile);
            if (!empty($control['errors'])) {
                return ['errors' => $control['errors']];
            }
        } elseif ($tile['type'] == 'myLastResources') {
            TileController::getLastResourcesDetails($tile);
        } elseif ($tile['type'] == 'searchTemplate') {
            $control = TileController::getSearchTemplateDetails($tile);
            if (!empty($control['errors'])) {
                return ['errors' => $control['errors']];
            }
        } elseif ($tile['type'] == 'followedMail') {
            $followedResources = UserFollowedResourceModel::get([
                'select' => ['res_id'],
                'where'  => ['user_id = ?'],
                'data'   => [$GLOBALS['id']]
            ]);
            TileController::getResourcesDetails($tile, $followedResources);
        } elseif ($tile['type'] == 'folder') {
            if (!FolderController::hasFolders(['folders' => [$tile['parameters']['folderId']], 'userId' => $GLOBALS['id']])) {
                return ['errors' => 'Folder out of perimeter'];
            }
            $foldersResources = ResourceFolderModel::get(['select' => ['res_id'], 'where' => ['folder_id = ?'], 'data' => [$tile['parameters']['folderId']]]);
            TileController::getResourcesDetails($tile, $foldersResources);
        } elseif ($tile['type'] == 'externalSignatoryBook') {
            $loadedXml = CoreConfigModel::getXmlLoaded(['path' => 'modules/visa/xml/remoteSignatoryBooks.xml']);
            if (empty($loadedXml)) {
                return ['errors' => 'configuration file missing: remoteSignatoryBooks.xml'];
            }
            $enabledExternalSignatoryBook = (string)$loadedXml->signatoryBookEnabled;
            if ($enabledExternalSignatoryBook == 'maarchParapheur') {
                $control = TileController::getMaarchParapheurDetails($tile);
            } elseif ($enabledExternalSignatoryBook == 'fastParapheur') {
                // $control = TileController::getFastParapheurDetails($tile);
                return ['errors' => 'Cannot create a tile for Fast Parapheur'];
            }
            if (!empty($control['errors'])) {
                return ['errors' => $control['errors']];
            }
        } elseif ($tile['type'] == 'shortcut') {
            $control = TileController::getShortcutDetails($tile);
            if (!empty($control['errors'])) {
                return ['errors' => $control['errors']];
            }
        }

        return true;
    }

    private static function getBasketDetails(array &$tile)
    {
        $basket = BasketModel::getById(['select' => ['basket_clause', 'basket_id'], 'id' => $tile['parameters']['basketId']]);
        $group  = GroupModel::getById(['select' => ['group_id'], 'id' => $tile['parameters']['groupId']]);
        if (!BasketModel::hasGroup(['id' => $basket['basket_id'], 'groupId' => $group['group_id']])) {
            return ['errors' => 'Basket is not linked to this group'];
        } elseif (!UserModel::hasGroup(['id' => $GLOBALS['id'], 'groupId' => $group['group_id']])) {
            return ['errors' => 'User is not linked to this group'];
        }

        $limit = 0;
        if ($tile['view'] == 'list') {
            $limit = 5;
        }

        $resources = ResModel::getOnView([
            'select'    => ['res_id'],
            'where'     => [PreparedClauseController::getPreparedClause(['userId' => $GLOBALS['id'], 'clause' => $basket['basket_clause']])],
            'orderBy'   => ['modification_date'],
            'limit'     => $limit
        ]);

        TileController::getResourcesDetails($tile, $resources);

        return true;
    }

    private static function getResourcesDetails(array &$tile, $allResources, $order = '')
    {
        $allResources = array_column($allResources, 'res_id');
        if ($tile['view'] == 'summary') {
            $tile['resourcesNumber'] = count($allResources);
        } elseif ($tile['view'] == 'list') {
            $tile['resources'] = [];
            if (!empty($allResources)) {
                $requestOrder = !empty($order) ? $order : 'modification_date';
                $resources = ResModel::get([
                    'select'  => ['subject', 'creation_date', 'res_id', 'category_id'],
                    'where'   => ['res_id in (?)'],
                    'data'    => [$allResources],
                    'orderBy' => [$requestOrder],
                    'limit'   => 5
                ]);

                foreach ($resources as $resource) {
                    if ($resource['category_id'] == 'outgoing') {
                        $correspondents = ContactController::getFormattedContacts(['resId' => $resource['res_id'], 'mode' => 'recipient', 'onlyContact' => true]);
                    } else {
                        $correspondents = ContactController::getFormattedContacts(['resId' => $resource['res_id'], 'mode' => 'sender', 'onlyContact' => true]);
                    }

                    $tile['resources'][] = [
                        'resId'          => $resource['res_id'],
                        'subject'        => $resource['subject'],
                        'creationDate'   => $resource['creation_date'],
                        'correspondents' => $correspondents
                    ];
                }
            }
        } elseif ($tile['view'] == 'chart') {
            $tile['resources'] = [];
            if (!empty($allResources)) {
                if (!empty($tile['parameters']['chartMode']) && $tile['parameters']['chartMode'] == 'status') {
                    $type = 'status';
                } elseif (!empty($tile['parameters']['chartMode']) && $tile['parameters']['chartMode'] == 'destination') {
                    $type = 'destination';
                } elseif (!empty($tile['parameters']['chartMode']) && $tile['parameters']['chartMode'] == 'creationDate') {
                    $type = "date_trunc('day', creation_date)";
                } else {
                    $type = 'type_id';
                }

                $resources = [];
                $chunkedResources = array_chunk($allResources, 5000);
                foreach ($chunkedResources as $chunkedResource) {
                    $chunkResources = ResModel::get([
                        'select'    => ["COUNT({$type})", $type],
                        'where'     => ['res_id in (?)'],
                        'data'      => [$chunkedResource],
                        'groupBy'   => [$type],
                        'orderBy'   => [$type]
                    ]);

                    $resources = array_merge($resources, $chunkResources);
                }

                if (!empty($tile['parameters']['chartMode']) && $tile['parameters']['chartMode'] == 'creationDate') {
                    $type = "date_trunc";
                }

                $tmpResources = [];
                foreach ($resources as $resource) {
                    if (empty($tmpResources[$resource[$type]])) {
                        $tmpResources[$resource[$type]] = $resource['count'];
                    } else {
                        $tmpResources[$resource[$type]] += $resource['count'];
                    }
                }
                $resources = [];
                foreach ($tmpResources as $key => $tmpResource) {
                    $resources[] = [$type => $key, 'count' => $tmpResource];
                }

                $dataResources = array_column($resources, $type);
                if (!empty($dataResources)) {
                    if ($type == 'status') {
                        $statuses      = StatusModel::get(['select' => ['label_status', 'id'], 'where' => ['id in (?)'], 'data' => [$dataResources]]);
                        $dataResources = array_column($statuses, 'label_status', 'id');
                    } elseif ($type == 'destination') {
                        $destination   = EntityModel::get(['select' => ['short_label', 'entity_id'], 'where' => ['entity_id in (?)'], 'data' => [$dataResources]]);
                        $dataResources = array_column($destination, 'short_label', 'entity_id');
                    } elseif ($type == 'type_id') {
                        $doctypes      = DoctypeModel::get(['select' => ['description', 'type_id'], 'where' => ['type_id in (?)'], 'data' => [$dataResources]]);
                        $dataResources = array_column($doctypes, 'description', 'type_id');
                    }
                }

                $tile['resources'] = [];
                foreach ($resources as $resource) {
                    if ($type == 'status') {
                        $tile['resources'][] = ['name' => $dataResources[$resource['status']] ?? '', 'value' => $resource['count']];
                    } elseif ($type == 'destination') {
                        $tile['resources'][] = ['name' => $dataResources[$resource['destination']] ?? '', 'value' => $resource['count']];
                    } elseif (!empty($tile['parameters']['chartMode']) && $tile['parameters']['chartMode'] == 'creationDate') {
                        $date = new \DateTime($resource['date_trunc']);
                        $date = $date->format('d/m/Y');
                        $tile['resources'][] = ['name' => $date, 'value' => $resource['count']];
                    } else {
                        $tile['resources'][] = ['name' => $dataResources[$resource['type_id']], 'value' => $resource['count']];
                    }
                }
            }
        }

        return true;
    }

    private static function getLastResourcesDetails(array &$tile)
    {
        $resources = ResModel::getLastResources([
            'select' => ['res_letterbox.res_id'],
            'limit'  => 5,
            'userId' => $GLOBALS['id']
        ]);

        $allResources = array_column($resources, 'res_id');
        $order  = 'CASE res_id ';
        for ($i = 0; $i < 5; $i++) {
            if (empty($allResources[$i])) {
                break;
            }
            $order .= "WHEN {$allResources[$i]} THEN {$i} ";
        }
        $order .= 'END';

        TileController::getResourcesDetails($tile, $resources, $order);
    }

    private static function getMaarchParapheurDetails(array &$tile)
    {
        $user = UserModel::getById(['id' => $GLOBALS['id'], 'select' => ['external_id']]);

        $externalId = json_decode($user['external_id'], true);
        if (empty($externalId['maarchParapheur'])) {
            return ['errors' => 'User is not linked to Maarch Parapheur'];
        }

        $loadedXml = CoreConfigModel::getXmlLoaded(['path' => 'modules/visa/xml/remoteSignatoryBooks.xml']);
        if (empty($loadedXml)) {
            return ['errors' => 'SignatoryBooks configuration file missing'];
        }

        $url      = '';
        $userId   = '';
        $password = '';
        foreach ($loadedXml->signatoryBook as $value) {
            if ($value->id == "maarchParapheur") {
                $url      = rtrim($value->url, '/');
                $userId   = $value->userId;
                $password = $value->password;
                break;
            }
        }

        if (empty($url)) {
            return ['errors' => 'Maarch Parapheur configuration missing'];
        }

        $curlResponse = CurlModel::exec([
            'url'         => rtrim($url, '/') . '/rest/documents',
            'basicAuth'   => ['user' => $userId, 'password' => $password],
            'headers'     => ['content-type:application/json'],
            'method'      => 'GET',
            'queryParams' => ['userId' => $externalId['maarchParapheur'], 'limit' => 5]
        ]);

        if ($curlResponse['code'] != '200') {
            if (!empty($curlResponse['response']['errors'])) {
                $errors =  $curlResponse['response']['errors'];
            } else {
                $errors =  $curlResponse['errors'];
            }
            if (empty($errors)) {
                $errors = 'An error occured. Please check your configuration file.';
            }
            return ['errors' => $errors];
        }

        if ($tile['view'] == 'summary') {
            $tile['resourcesNumber'] = $curlResponse['response']['count']['visa'] + $curlResponse['response']['count']['sign'] + $curlResponse['response']['count']['note'];
        } elseif ($tile['view'] == 'list') {
            $tile['resources'] = [];
            foreach ($curlResponse['response']['documents'] as $resource) {
                $tile['resources'][] = [
                    'resId'          => $resource['id'],
                    'subject'        => $resource['title'],
                    'creationDate'   => $resource['creationDate'],
                    'correspondents' => [$resource['sender']]
                ];
            }
        }

        return true;
    }

    private static function getFastParapheurDetails(array &$tile)
    {
        if ($tile['view'] == 'summary') {
            $tile['resourcesNumber'] = FastParapheurController::getResourcesCount();
        } elseif ($tile['view'] == 'list') {
            $tile['resources'] = FastParapheurController::getResourcesDetails();
        }
    }

    private static function getShortcutDetails(array &$tile)
    {
        $tile['resourcesNumber'] = null;

        if (($tile['parameters']['privilegeId'] == 'indexing' && !PrivilegeController::canIndex(['userId' => $GLOBALS['id'], 'groupId' => $tile['parameters']['groupId']])) ||
            ($tile['parameters']['privilegeId'] != 'indexing' && !PrivilegeController::hasPrivilege(['privilegeId' => $tile['parameters']['privilegeId'], 'userId' => $GLOBALS['id']]))
        ) {
            return ['errors' => 'Service forbidden'];
        }
        if ($tile['parameters']['privilegeId'] == 'admin_users') {
            if (UserController::isRoot(['id' => $GLOBALS['id']])) {
                $users = UserModel::get([
                    'select' => [1],
                    'where'  => ['status != ?'],
                    'data'   => ['DEL']
                ]);
            } else {
                $entities = EntityModel::getAllEntitiesByUserId(['userId' => $GLOBALS['id']]);
                $users = [];
                if (!empty($entities)) {
                    $users = UserEntityModel::getWithUsers([
                        'select' => ['DISTINCT users.id', 'users.user_id', 'firstname', 'lastname', 'status', 'mail'],
                        'where'  => ['users_entities.entity_id in (?)', 'status != ?'],
                        'data'   => [$entities, 'DEL']
                    ]);
                }
                $usersNoEntities = UserEntityModel::getUsersWithoutEntities(['select' => ['id', 'users.user_id', 'firstname', 'lastname', 'status', 'mail']]);
                $users = array_merge($users, $usersNoEntities);
            }
            $tile['resourcesNumber'] = count($users);
        } elseif ($tile['parameters']['privilegeId'] == 'admin_groups') {
            $groups = GroupModel::get(['select' => [1]]);
            $tile['resourcesNumber'] = count($groups);
        } elseif ($tile['parameters']['privilegeId'] == 'manage_entities') {
            $entities = EntityModel::get(['select' => [1]]);
            $tile['resourcesNumber'] = count($entities);
        } elseif ($tile['parameters']['privilegeId'] == 'admin_listmodels') {
            $listTemplates = ListTemplateModel::get(['select' => [1], 'where'  => ['owner is null']]);
            $tile['resourcesNumber'] = count($listTemplates);
        } elseif ($tile['parameters']['privilegeId'] == 'admin_architecture') {
            $doctypes = DoctypeModel::get(['select' => [1], 'where' => ['enabled = ?'], 'data' => ['Y']]);
            $tile['resourcesNumber'] = count($doctypes);
        } elseif ($tile['parameters']['privilegeId'] == 'admin_tag') {
            $tags = TagModel::get(['select' => [1]]);
            $tile['resourcesNumber'] = count($tags);
        } elseif ($tile['parameters']['privilegeId'] == 'admin_baskets') {
            $baskets = BasketModel::get(['select' => [1]]);
            $tile['resourcesNumber'] = count($baskets);
        } elseif ($tile['parameters']['privilegeId'] == 'admin_status') {
            $status = StatusModel::get(['select' => [1]]);
            $tile['resourcesNumber'] = count($status);
        } elseif ($tile['parameters']['privilegeId'] == 'admin_actions') {
            $actions = ActionModel::get(['select' => [1]]);
            $tile['resourcesNumber'] = count($actions);
        } elseif ($tile['parameters']['privilegeId'] == 'admin_contacts') {
            $contacts = ContactModel::get(['select' => [1]]);
            $tile['resourcesNumber'] = count($contacts);
        } elseif ($tile['parameters']['privilegeId'] == 'admin_priorities') {
            $priority = PriorityModel::get(['select' => [1]]);
            $tile['resourcesNumber'] = count($priority);
        } elseif ($tile['parameters']['privilegeId'] == 'admin_templates') {
            $templates = TemplateModel::get(['select' => [1]]);
            $tile['resourcesNumber'] = count($templates);
        } elseif ($tile['parameters']['privilegeId'] == 'admin_indexing_models') {
            $models = IndexingModelModel::get(['select' => [1]]);
            $tile['resourcesNumber'] = count($models);
        } elseif ($tile['parameters']['privilegeId'] == 'admin_custom_fields') {
            $customFields = CustomFieldModel::get(['select' => [1]]);
            $tile['resourcesNumber'] = count($customFields);
        } elseif ($tile['parameters']['privilegeId'] == 'admin_notif') {
            $notifications = NotificationModel::get(['select' => [1]]);
            $tile['resourcesNumber'] = count($notifications);
        } elseif ($tile['parameters']['privilegeId'] == 'admin_docservers') {
            $docservers = DocserverModel::get(['select' => [1]]);
            $tile['resourcesNumber'] = count($docservers);
        } elseif ($tile['parameters']['privilegeId'] == 'admin_shippings') {
            $shippings = ShippingTemplateModel::get(['select' => [1]]);
            $tile['resourcesNumber'] = count($shippings);
        } elseif ($tile['parameters']['privilegeId'] == 'admin_alfresco') {
            $entities = EntityModel::get(['select' => ['external_id', 'short_label'], 'where' => ["external_id->>'alfresco' is not null"]]);

            $accounts = [];
            $alreadyAdded = [];
            foreach ($entities as $entity) {
                $alfresco = json_decode($entity['external_id'], true);
                if (!in_array($alfresco['alfresco']['id'], $alreadyAdded)) {
                    $accounts[] = [
                        'id'            => $alfresco['alfresco']['id'],
                        'label'         => $alfresco['alfresco']['label'],
                        'login'         => $alfresco['alfresco']['login'],
                        'entitiesLabel' => [$entity['short_label']]
                    ];
                    $alreadyAdded[] = $alfresco['alfresco']['id'];
                } else {
                    foreach ($accounts as $key => $value) {
                        if ($value['id'] == $alfresco['alfresco']['id']) {
                            $accounts[$key]['entitiesLabel'][] = $entity['short_label'];
                        }
                    }
                }
            }
            $tile['resourcesNumber'] = count($accounts);
        } elseif ($tile['parameters']['privilegeId'] == 'admin_attachments') {
            $attachmentsTypes = AttachmentTypeModel::get(['select' => [1]]);
            $tile['resourcesNumber'] = count($attachmentsTypes);
        }

        return true;
    }

    private static function getSearchTemplateDetails(array &$tile)
    {
        $searchTemplate = SearchTemplateModel::get(['select' => ['query'], 'where' => ['id = ?', 'user_id = ?'], 'data' => [$tile['parameters']['searchTemplateId'], $GLOBALS['id']]]);
        if (empty($searchTemplate)) {
            return ['errors' => 'SearchTemplateId is out of perimeter'];
        }
        ini_set('memory_limit', -1);

        $rawQuery = json_decode($searchTemplate[0]['query'], true);
        $query = [];
        foreach ($rawQuery as $value) {
            $definedVars = get_defined_vars();
            if (!empty($value['values'][0]) && is_array($value['values'][0]) && array_key_exists('id', $definedVars['value']['values'][0]) && !in_array($value['identifier'], ['recipients', 'senders']) && strpos($value['identifier'], 'role_') === false) {
                $value['values'] = array_column($value['values'], 'id');
            } else {
                if (!empty($value['values']['start'])) {
                    $date = new \DateTime($value['values']['start']);
                    $value['values']['start'] = $date->format('Y-m-d');
                }
                if (!empty($value['values']['end'])) {
                    $date = new \DateTime($value['values']['end']);
                    $value['values']['end'] = $date->format('Y-m-d');
                }
            }
            if (!empty($value['values'])) {
                $query[$value['identifier']] = ['values' => $value['values']];
            }
        }

        $userdataClause = SearchController::getUserDataClause(['userId' => $GLOBALS['id'], 'login' => $GLOBALS['login']]);
        $searchWhere    = $userdataClause['searchWhere'];
        $searchData     = $userdataClause['searchData'];

        if (!empty($query['meta']['values'])) {
            $query['meta']['values'] = trim($query['meta']['values']);
        }
        $searchClause = SearchController::getQuickFieldClause(['body' => $query, 'searchWhere' => $searchWhere, 'searchData' => $searchData]);
        $searchWhere  = $searchClause['searchWhere'];
        $searchData   = $searchClause['searchData'];

        $searchClause = SearchController::getMainFieldsClause(['body' => $query, 'searchWhere' => $searchWhere, 'searchData' => $searchData]);
        $searchWhere  = $searchClause['searchWhere'];
        $searchData   = $searchClause['searchData'];

        $searchClause = SearchController::getListFieldsClause(['body' => $query, 'searchWhere' => $searchWhere, 'searchData' => $searchData]);
        $searchWhere  = $searchClause['searchWhere'];
        $searchData   = $searchClause['searchData'];

        $searchClause = SearchController::getCustomFieldsClause(['body' => $query, 'searchWhere' => $searchWhere, 'searchData' => $searchData]);
        $searchWhere  = $searchClause['searchWhere'];
        $searchData   = $searchClause['searchData'];

        $searchClause = SearchController::getRegisteredMailsClause(['body' => $query, 'searchWhere' => $searchWhere, 'searchData' => $searchData]);
        $searchWhere  = $searchClause['searchWhere'];
        $searchData   = $searchClause['searchData'];

        $searchClause = SearchController::getFulltextClause(['body' => $query, 'searchWhere' => $searchWhere, 'searchData' => $searchData]);
        $searchWhere  = $searchClause['searchWhere'];
        $searchData   = $searchClause['searchData'];

        $searchableStatuses = StatusModel::get(['select' => ['id'], 'where' => ['can_be_searched = ?'], 'data' => ['Y']]);
        if (!empty($searchableStatuses)) {
            $searchableStatuses = array_column($searchableStatuses, 'id');
            $searchWhere[] = 'status in (?)';
            $searchData[]  = $searchableStatuses;
        }

        DatabaseModel::beginTransaction();
        SearchModel::createTemporarySearchData(['where' => $searchWhere, 'data' => $searchData]);

        $allResources = SearchModel::getTemporarySearchData([
            'select'  => ['res_id'],
            'where'   => [],
            'data'    => [],
            'orderBy' => ['creation_date']
        ]);
        DatabaseModel::commitTransaction();
        $allResources = array_column($allResources, 'res_id');

        $offset = 0;
        $limit  = count($allResources);
        if ($tile['view'] == 'list') {
            $limit = 5;
        }

        $resIds = [];
        $order  = 'CASE res_id ';
        for ($i = $offset; $i < ($offset + $limit); $i++) {
            if (empty($allResources[$i])) {
                break;
            }
            $order .= "WHEN {$allResources[$i]} THEN {$i} ";
            $resIds[] = ['res_id' => $allResources[$i]];
        }
        $order .= 'END';

        TileController::getResourcesDetails($tile, $resIds, $order);

        return true;
    }
}
