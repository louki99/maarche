<?php

/**
* Copyright Maarch since 2008 under licence GPLv3.
* See LICENCE.txt file at the root folder for more details.
* This file is part of Maarch software.
*/

/**
 * @brief Tag Controller
 * @author dev@maarch.org
 */

namespace Tag\controllers;

use Group\controllers\PrivilegeController;
use History\controllers\HistoryController;
use Respect\Validation\Validator;
use Slim\Psr7\Request;
use SrcCore\http\Response;
use SrcCore\models\ValidatorModel;
use Tag\models\TagModel;
use Tag\models\ResourceTagModel;

class TagController
{
    public function get(Request $request, Response $response)
    {
        $tags = TagModel::get(['orderBy' => ['label']]);

        if (empty($tags)) {
            return $response->withJson(['tags' => []]);
        }

        $ids = array_column($tags, 'id');

        $countResources = ResourceTagModel::get([
            'select'  => ['count(res_id)', 'tag_id'],
            'where'   => ['tag_id in (?)'],
            'data'    => [$ids],
            'groupBy' => ['tag_id']
        ]);
        $countResources = array_column($countResources, 'count', 'tag_id');

        foreach ($tags as $key => $tag) {
            $tags[$key]['parentId'] = $tags[$key]['parent_id'];
            $tags[$key]['creationDate'] = $tags[$key]['creation_date'];
            unset($tags[$key]['parent_id']);
            unset($tags[$key]['creation_date']);
            $tags[$key]['countResources'] = $countResources[$tag['id']] ?? 0;
            $tags[$key]['links'] = json_decode($tags[$key]['links'], true);
        }

        return $response->withJson(['tags' => $tags]);
    }

    public function getById(Request $request, Response $response, array $args)
    {
        if (!Validator::notEmpty()->intVal()->validate($args['id'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Route id must be an integer val']);
        }

        $tag = TagModel::getById(['id' => $args['id']]);
        if (empty($tag)) {
            return $response->withStatus(404)->withJson(['errors' => 'id not found']);
        }

        $countResources = ResourceTagModel::get([
           'select' => ['count(1)'],
           'where'  => ['tag_id = ?'],
           'data'   => [$args['id']]
        ]);
        $tag['countResources'] = $countResources[0]['count'];
        $tag['links'] = json_decode($tag['links'], true);

        $childTags = TagModel::get([
            'select' => [1],
            'where'  => ['parent_id = ?'],
            'data'   => [$tag['id']]
        ]);

        $tag['canMerge'] = empty($tag['parent_id']) && empty($childTags);
        $tag['parentId'] = $tag['parent_id'];
        $tag['creationDate'] = $tag['creation_date'];

        unset($tag['parent_id']);
        unset($tag['creation_date']);

        return $response->withJson($tag);
    }

    public function create(Request $request, Response $response)
    {
        if (!PrivilegeController::hasPrivilege(['privilegeId' => 'admin_tag', 'userId' => $GLOBALS['id']])
            && !PrivilegeController::hasPrivilege(['privilegeId' => 'manage_tags_application', 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }

        $body = $request->getParsedBody();

        if (!Validator::stringType()->notEmpty()->validate($body['label'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body label is empty or not a string']);
        }

        if (!Validator::length(1, 128)->validate($body['label'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body label has more than 128 characters']);
        }

        $parent = null;
        if (!empty($body['parentId'])) {
            if (!Validator::intVal()->validate($body['parentId'])) {
                return $response->withStatus(400)->withJson(['errors' => 'Body parentId is not an integer']);
            }
            $parent = TagModel::getById(['id' => $body['parentId'], 'select' => ['id']]);
            if (empty($parent)) {
                return $response->withStatus(400)->withJson(['errors' => 'Parent tag does not exist']);
            }
            $parent = $parent['id'];
        }

        $links = json_encode([]);
        if (!empty($body['links'])) {
            if (!Validator::arrayType()->validate($body['links'])) {
                return $response->withStatus(400)->withJson(['errors' => 'Body links is not an array']);
            }
            $listTags = [];
            foreach ($body['links'] as $link) {
                if (!Validator::intVal()->validate($link)) {
                    return $response->withStatus(400)->withJson(['errors' => 'Body links element is not an integer']);
                }
                $listTags[] = (string)$link;
            }
            $tags = TagModel::get([
                'select' => ['count(1)'],
                'where'  => ['id in (?)'],
                'data'   => [$body['links']]
            ]);
            if ($tags[0]['count'] != count($body['links'])) {
                return $response->withStatus(400)->withJson(['errors' => 'Tag(s) not found']);
            }

            $links = json_encode($listTags);
        }

        $id = TagModel::create([
            'label'       => $body['label'],
            'description' => $body['description'] ?? null,
            'parentId'    => $parent,
            'links'       => $links,
            'usage'       => $body['usage'] ?? null
        ]);

        if (!empty($body['links'])) {
            TagModel::update([
                'postSet' => ['links' => "jsonb_insert(links, '{0}', '\"{$id}\"')"],
                'where'   => ['id in (?)', "(links @> ?) = false"],
                'data'    => [$body['links'], "\"{$id}\""]
            ]);
        }

        HistoryController::add([
            'tableName' => 'tags',
            'recordId'  => $id,
            'eventType' => 'ADD',
            'info'      =>  _TAG_ADDED . " : {$body['label']}",
            'eventId'   => 'tagCreation',
        ]);

        return $response->withJson(['id' => $id]);
    }

    public function update(Request $request, Response $response, array $args)
    {
        if (!PrivilegeController::hasPrivilege(['privilegeId' => 'admin_tag', 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }

        if (!Validator::notEmpty()->intVal()->validate($args['id'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Route id must be an integer val']);
        }

        $tag = TagModel::getById(['id' => $args['id']]);
        if (empty($tag)) {
            return $response->withStatus(400)->withJson(['errors' => 'Tag does not exist']);
        }

        $body = $request->getParsedBody();

        if (!Validator::stringType()->notEmpty()->validate($body['label'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body label is empty or not a string']);
        }

        if (!Validator::length(1, 128)->validate($body['label'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body label has more than 128 characters']);
        }

        $parent = null;
        if (!empty($body['parentId'])) {
            if (!Validator::intVal()->validate($body['parentId'])) {
                return $response->withStatus(400)->withJson(['errors' => 'Body parentId is not an integer']);
            }
            $parent = TagModel::getById(['id' => $body['parentId'], 'select' => ['id']]);
            if (empty($parent)) {
                return $response->withStatus(400)->withJson(['errors' => 'Parent tag does not exist']);
            }
            $parent = $parent['id'];

            if ($parent == $args['id']) {
                return $response->withStatus(400)->withJson(['errors' => 'Tag cannot be its own parent']);
            }
            
            $parentIsChildren = TagController::tagIsInChildren(['idToFind' => $parent, 'parentId' => $args['id']]);
            if ($parentIsChildren) {
                return $response->withStatus(400)->withJson(['errors' => 'Parent tag cannot also be a children']);
            }
        }

        $links = [];
        $tag['links'] = json_decode($tag['links']);

        if (!empty($body['links'])) {
            if (!Validator::arrayType()->validate($body['links'])) {
                return $response->withStatus(400)->withJson(['errors' => 'Body links is not an array']);
            } elseif (in_array($args['id'], $body['links'])) {
                return $response->withStatus(400)->withJson(['errors' => 'Body links contains tag']);
            }

            foreach ($body['links'] as $link) {
                $links[] = (string)$link;
            }

            $newLinks = array_diff($links, $tag['links']);
            if (!empty($newLinks)) {
                TagModel::update([
                    'postSet' => ['links' => "jsonb_insert(links, '{0}', '\"{$args['id']}\"')"],
                    'where'   => ['id in (?)', "(links @> ?) = false"],
                    'data'    => [$newLinks, "\"{$args['id']}\""]
                ]);
            }
        }

        if (!empty($tag['links'])) {
            $linksToDelete = array_diff($tag['links'], $links);

            if (!empty($linksToDelete)) {
                TagModel::update([
                    'postSet' => ['links' => "links - '{$tag['id']}'"],
                    'where'   => ['id in (?)'],
                    'data'    => [$linksToDelete]
                ]);
            }
        }

        TagModel::update([
            'set'   => [
                'label'       => $body['label'],
                'description' => $body['description'] ?? null,
                'parent_id'   => $parent,
                'usage'       => $body['usage'] ?? null,
                'links'       => json_encode($links)
            ],
            'where' => ['id = ?'],
            'data'  => [$args['id']]
        ]);

        HistoryController::add([
            'tableName' => 'tags',
            'recordId'  => $args['id'],
            'eventType' => 'UP',
            'info'      =>  _TAG_UPDATED . " : {$body['label']}",
            'eventId'   => 'tagModification',
        ]);

        return $response->withStatus(204);
    }

    public function delete(Request $request, Response $response, array $args)
    {
        if (!PrivilegeController::hasPrivilege(['privilegeId' => 'admin_tag', 'userId' => $GLOBALS['id']])
            && !PrivilegeController::hasPrivilege(['privilegeId' => 'manage_tags_application', 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }

        if (!Validator::notEmpty()->intVal()->validate($args['id'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Route id must be an integer val']);
        }

        $resourcesTags = ResourceTagModel::get([
            'where' => ['tag_id = ?'],
            'data'  => [$args['id']]
        ]);
        if (!empty($resourcesTags) && !PrivilegeController::hasPrivilege(['privilegeId' => 'admin_tag', 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }

        $tag = TagModel::getById(['select' => ['label', 'links'], 'id' => $args['id']]);
        if (empty($tag)) {
            return $response->withStatus(400)->withJson(['errors' => 'Tag does not exist']);
        }

        $children = TagModel::get([
           'select' => ['count(1)'],
           'where'  => ['parent_id = ?'],
           'data'   => [$args['id']]
        ]);
        if ($children[0]['count'] > 0) {
            return $response->withStatus(400)->withJson(['errors' => 'Tag has children']);
        }

        $links = json_decode($tag['links'], true);
        if (!empty($links)) {
            foreach ($links as $link) {
                TagModel::update([
                    'postSet'   => ['links' => "links - '{$args['id']}'"],
                    'where'     => ['id = ?'],
                    'data'      => [$link]
                ]);
            }
        }

        ResourceTagModel::delete([
            'where' => ['tag_id = ?'],
            'data'  => [$args['id']]
        ]);

        TagModel::delete([
            'where' => ['id = ?'],
            'data'  => [$args['id']]
        ]);

        HistoryController::add([
            'tableName' => 'tags',
            'recordId'  => $args['id'],
            'eventType' => 'DEL',
            'info'      =>  _TAG_DELETED . " : {$tag['label']}",
            'eventId'   => 'tagSuppression',
        ]);

        return $response->withStatus(204);
    }

    public function merge(Request $request, Response $response)
    {
        if (!PrivilegeController::hasPrivilege(['privilegeId' => 'admin_tag', 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }

        $body = $request->getParsedBody();

        if (!Validator::notEmpty()->intVal()->validate($body['idMaster'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body idMaster must be an integer val']);
        }
        if (!Validator::notEmpty()->intVal()->validate($body['idMerge'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body idMerge must be an integer val']);
        }
        if ($body['idMaster'] == $body['idMerge']) {
            return $response->withStatus(400)->withJson(['errors' => 'Cannot merge tag with itself']);
        }

        $tagMaster = TagModel::getById(['id' => $body['idMaster']]);
        if (empty($tagMaster)) {
            return $response->withStatus(404)->withJson(['errors' => 'Master tag not found']);
        }

        $tagMerge = TagModel::getById(['id' => $body['idMerge']]);
        if (empty($tagMerge)) {
            return $response->withStatus(404)->withJson(['errors' => 'Merge tag not found']);
        }

        if (!empty($tagMerge['parent_id'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Cannot merge tag : tag has a parent']);
        }

        $childTags = TagModel::get([
            'select' => ['count(1)'],
            'where'  => ['parent_id = ?'],
            'data'   => [$tagMerge['id']]
        ]);
        if ($childTags[0]['count'] > 0) {
            return $response->withStatus(400)->withJson(['errors' => 'Cannot merge tag : tag has a child']);
        }

        $tagResMaster = ResourceTagModel::get([
            'where' => ['tag_id = ?'],
            'data'  => [$tagMaster['id']]
        ]);
        $tagResMaster = array_column($tagResMaster, 'res_id');

        if (empty($tagResMaster)) {
            $tagResMaster = [0];
        }

        ResourceTagModel::update([
            'set'   => [
                'tag_id' => $tagMaster['id']
            ],
            'where' => ['tag_id = ?', 'res_id not in (?)'],
            'data'  => [$tagMerge['id'], $tagResMaster]
        ]);


        ResourceTagModel::delete([
           'where'  => ['tag_id = ?'],
           'data'   => [$tagMerge['id']]
        ]);

        TagModel::delete([
            'where' => ['id = ?'],
            'data'  => [$tagMerge['id']]
        ]);

        HistoryController::add([
            'tableName' => 'tags',
            'recordId'  => $tagMaster['id'],
            'eventType' => 'DEL',
            'info'      =>  _TAG_MERGED . " : {$tagMerge['label']} vers {$tagMaster['label']}",
            'eventId'   => 'tagSuppression',
        ]);

        return $response->withStatus(204);
    }

    private static function tagIsInChildren(array $args)
    {
        ValidatorModel::notEmpty($args, ['idToFind', 'parentId']);
        ValidatorModel::intVal($args, ['idToFind', 'parentId']);

        $children = TagModel::get([
            'select' => ['id'],
            'where'  => ['parent_id = ?'],
            'data'   => [$args['parentId']]
        ]);
        $children = array_column($children, 'id');
        if (in_array($args['idToFind'], $children)) {
            return true;
        }

        foreach ($children as $child) {
            $inChildren = TagController::tagIsInChildren(['idToFind' => $args['idToFind'], 'parentId' => $child]);
            if ($inChildren) {
                return true;
            }
        }
        return false;
    }
}
