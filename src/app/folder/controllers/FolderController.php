<?php

/**
 * Copyright Maarch since 2008 under licence GPLv3.
 * See LICENCE.txt file at the root folder for more details.
 * This file is part of Maarch software.
 */

/**
 * @brief Folder Controller
 *
 * @author dev@maarch.org
 */

namespace Folder\controllers;

use Attachment\models\AttachmentModel;
use Entity\models\EntityModel;
use Folder\models\EntityFolderModel;
use Folder\models\FolderModel;
use Folder\models\ResourceFolderModel;
use Folder\models\UserPinnedFolderModel;
use Group\controllers\PrivilegeController;
use History\controllers\HistoryController;
use Resource\controllers\ResController;
use Resource\controllers\ResourceListController;
use Resource\models\ResModel;
use Resource\models\ResourceListModel;
use Resource\models\UserFollowedResourceModel;
use Respect\Validation\Validator;
use Slim\Psr7\Request;
use SrcCore\http\Response;
use SrcCore\models\DatabaseModel;
use SrcCore\models\ValidatorModel;
use User\models\UserEntityModel;
use User\models\UserModel;

class FolderController
{
    public function get(Request $request, Response $response)
    {
        $folders = FolderController::getScopeFolders(['login' => $GLOBALS['login']]);

        $userEntities = EntityModel::getWithUserEntities(['select'  => ['entities.id'], 'where' => ['user_id = ?'], 'data' => [$GLOBALS['id']]]);

        $userEntities = array_column($userEntities, 'id');
        if (empty($userEntities)) {
            $userEntities = 0;
        }

        $foldersWithResources = FolderModel::getWithEntitiesAndResources([
            'select'   => ['COUNT(DISTINCT resources_folders.res_id)', 'resources_folders.folder_id'],
            'where'    => ['(entities_folders.entity_id in (?) OR folders.user_id = ? OR keyword = ?)'],
            'data'     => [$userEntities, $GLOBALS['id'], 'ALL_ENTITIES'],
            'groupBy'  => ['resources_folders.folder_id']
        ]);

        $tree = [];
        foreach ($folders as $folder) {
            $key = array_keys(array_column($foldersWithResources, 'folder_id'), $folder['id']);
            $count = 0;
            if (isset($key[0])) {
                $count = $foldersWithResources[$key[0]]['count'];
            }

            $isPinned = !empty(
                UserPinnedFolderModel::get([
                    'where' => ['folder_id = ?', 'user_id = ?'],
                    'data'  => [$folder['id'], $GLOBALS['id']]
                ])
            );

            $folderScope = FolderController::getScopeFolders(['login' => $GLOBALS['login'], 'folderId' => $folder['id'], 'edition' => true]);

            $insert = [
                'name'           => $folder['label'],
                'id'             => $folder['id'],
                'label'          => $folder['label'],
                'public'         => $folder['public'],
                'user_id'        => $folder['user_id'],
                'parent_id'      => $folder['parent_id'],
                'level'          => $folder['level'],
                'countResources' => $count,
                'pinned'         => $isPinned,
                'canEdit'        => !empty($folderScope)
            ];
            if ($folder['level'] == 0) {
                array_splice($tree, 0, 0, [$insert]);
            } else {
                $found = false;
                foreach ($tree as $key => $branch) {
                    if ($branch['id'] == $folder['parent_id']) {
                        array_splice($tree, $key + 1, 0, [$insert]);
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    $insert['level'] = 0;
                    $insert['parent_id'] = null;
                    $tree[] = $insert;
                }
            }
        }

        return $response->withJson(['folders' => $tree]);
    }

    public function getById(Request $request, Response $response, array $args)
    {
        if (!Validator::numericVal()->notEmpty()->validate($args['id'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Route id is not an integer']);
        }

        $folder = FolderController::getScopeFolders(['login' => $GLOBALS['login'], 'folderId' => $args['id']]);
        if (empty($folder[0])) {
            return $response->withStatus(400)->withJson(['errors' => 'Folder not found or out of your perimeter']);
        }

        $folder = $folder[0];
        $ownerInfo = UserModel::getById(['select' => ['firstname', 'lastname'], 'id' => $folder['user_id']]);
        $folder['ownerDisplayName'] = $ownerInfo['firstname'] . ' ' . $ownerInfo['lastname'];

        $userEntities = EntityModel::getWithUserEntities([
            'select' => ['id'],
            'where'  => ['user_id = ?'],
            'data'   => [$GLOBALS['id']]
        ]);
        $userEntities = array_column($userEntities, 'id');

        $folder['sharing']['entities'] = [];
        if ($folder['public']) {
            $entitiesFolder = EntityFolderModel::getEntitiesByFolderId(['folder_id' => $args['id'], 'select' => ['entities_folders.entity_id', 'entities_folders.edition', 'entities.entity_label']]);
            $canDeleteWithChildren = FolderController::areChildrenInPerimeter(['folderId' => $args['id'], 'entities' => $userEntities]);
            foreach ($entitiesFolder as $value) {
                $canDelete = $canDeleteWithChildren && $value['edition'] == true;
                $folder['sharing']['entities'][] = ['entity_id' => $value['entity_id'], 'edition' => $value['edition'], 'canDelete' => $canDelete, 'label' => $value['entity_label']];
            }

            $keywordsFolder = EntityFolderModel::getKeywordsByFolderId(['folder_id' => $args['id'], 'select' => ['edition', 'keyword']]);
            foreach ($keywordsFolder as $value) {
                $canDelete = $canDeleteWithChildren && $value['edition'] == true;
                $folder['sharing']['entities'][] = ['keyword' => $value['keyword'], 'edition' => $value['edition'], 'canDelete' => $canDelete];
            }
        }

        $folder['pinned'] = !empty(
            UserPinnedFolderModel::get([
                'where' => ['folder_id = ?', 'user_id = ?'],
                'data'  => [$folder['id'], $GLOBALS['id']]
            ])
        );

        return $response->withJson(['folder' => $folder]);
    }

    public function create(Request $request, Response $response)
    {
        $data = $request->getParsedBody();

        if (!Validator::stringType()->notEmpty()->validate($data['label'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body label is empty or not a string']);
        }
        if (!empty($data['parent_id']) && !Validator::intval()->validate($data['parent_id'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body parent_id is not an integer']);
        }

        if (empty($data['parent_id'])) {
            $data['parent_id'] = null;
            $owner  = $GLOBALS['id'];
            $public = false;
            $level  = 0;
        } else {
            $folder = FolderController::getScopeFolders(['login' => $GLOBALS['login'], 'folderId' => $data['parent_id'], 'edition' => true]);
            if (empty($folder[0])) {
                return $response->withStatus(400)->withJson(['errors' => 'Parent Folder not found or out of your perimeter']);
            }
            $owner  = $folder[0]['user_id'];
            $public = $folder[0]['public'];
            $level  = $folder[0]['level'] + 1;
        }

        $id = FolderModel::create([
            'label'     => $data['label'],
            'public'    => $public,
            'user_id'   => $owner,
            'parent_id' => $data['parent_id'],
            'level'     => $level
        ]);

        if (!empty($data['parent_id'])) {
            $parentSharing = EntityFolderModel::get([
                'select' => ['entity_id', 'edition', 'keyword'],
                'where'  => ['folder_id = ?'],
                'data'   => [$data['parent_id']]
            ]);

            foreach ($parentSharing as $sharing) {
                EntityFolderModel::create([
                    'folder_id' => $id,
                    'entity_id' => $sharing['entity_id'],
                    'edition'   => $sharing['edition'],
                    'keyword'   => $sharing['keyword']
                ]);
            }
        }

        UserPinnedFolderModel::create([
            'folder_id' => $id,
            'user_id'   => $GLOBALS['id']
        ]);

        if ($public && !empty($data['parent_id'])) {
            $entitiesSharing = EntityFolderModel::getEntitiesByFolderId(['folder_id' => $data['parent_id'], 'select' => ['entities.id', 'entities_folders.edition']]);
            foreach ($entitiesSharing as $entity) {
                EntityFolderModel::create([
                    'folder_id' => $id,
                    'entity_id' => $entity['id'],
                    'edition'   => $entity['edition'],
                ]);
            }
        }

        HistoryController::add([
            'tableName' => 'folders',
            'recordId'  => $id,
            'eventType' => 'ADD',
            'info'      => _FOLDER_CREATION . " : {$data['label']}",
            'moduleId'  => 'folder',
            'eventId'   => 'folderCreation',
        ]);

        return $response->withJson(['folder' => $id]);
    }

    public function update(Request $request, Response $response, array $args)
    {
        $data = $request->getParsedBody();

        if (!Validator::numericVal()->notEmpty()->validate($args['id'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Query id is empty or not an integer']);
        }
        if (!Validator::stringType()->notEmpty()->validate($data['label'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body label is empty or not a string']);
        }
        if (!empty($data['parent_id']) && !Validator::intval()->validate($data['parent_id'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body parent_id is not an integer']);
        }
        if ($data['parent_id'] == $args['id']) {
            return $response->withStatus(400)->withJson(['errors' => 'Parent_id and id can not be the same']);
        }
        if (!empty($data['parent_id']) && FolderController::isParentFolder(['parent_id' => $data['parent_id'], 'id' => $args['id']])) {
            return $response->withStatus(400)->withJson(['errors' => 'parent_id does not exist or Id is a parent of parent_id']);
        }

        $folder = FolderController::getScopeFolders(['login' => $GLOBALS['login'], 'folderId' => $args['id'], 'edition' => true]);
        if (empty($folder[0])) {
            return $response->withStatus(400)->withJson(['errors' => 'Folder not found or out of your perimeter']);
        }

        $folderOwner = $folder[0]['user_id'];
        if (empty($data['parent_id'])) {
            $data['parent_id'] = null;
            $level = 0;
        } else {
            $folderParent = FolderController::getScopeFolders(['login' => $GLOBALS['login'], 'folderId' => $data['parent_id'], 'edition' => true]);
            if (empty($folderParent[0])) {
                return $response->withStatus(400)->withJson(['errors' => 'Parent Folder not found or out of your perimeter']);
            }
            $level = $folderParent[0]['level'] + 1;
            $folderOwner = $folderParent[0]['user_id'];
        }

        if ($folder[0]['parent_id'] != $data['parent_id']) {
            $userEntities = EntityModel::getWithUserEntities([
                'select' => ['id'],
                'where'  => ['user_id = ?'],
                'data'   => [$GLOBALS['id']]
            ]);
            $userEntities = array_column($userEntities, 'id');

            $childrenInPerimeter = FolderController::areChildrenInPerimeter(['folderId' => $args['id'], 'entities' => $userEntities]);
            if (!$childrenInPerimeter && $folder[0]['user_id'] != $GLOBALS['id']) {
                return $response->withStatus(400)->withJson(['errors' => 'Cannot move folder because at least one folder is out of your perimeter']);
            }

            if (!empty($data['parent_id'])) {
                $parentEntities = EntityFolderModel::get([
                    'select' => ['entity_id', 'edition'],
                    'where'  => ['folder_id = ?', 'entity_id is not null'],
                    'data'   => [$data['parent_id']]
                ]);
                $entities = [];
                foreach ($parentEntities as $entity) {
                    $entities[] = ['entity_id' => $entity['entity_id'], 'edition' => $entity['edition']];
                }

                $keywordsList = EntityFolderModel::get([
                    'select'  => ['keyword', 'edition'],
                    'where'   => ['folder_id in (?)', 'keyword is not null'],
                    'data'    => [[$data['parent_id'], $args['id']]],
                    'groupBy' => ['keyword', 'edition'],
                    'orderBy' => ['edition DESC']
                ]);
                $keywords = [];
                foreach ($keywordsList as $keyword) {
                    $keywords[] = ['keyword' => $keyword['keyword'], 'edition' => $keyword['edition']];
                }

                DatabaseModel::beginTransaction();
                $sharing = FolderController::folderSharing([
                    'folderId' => $args['id'],
                    'public'   => true,
                    'remove'   => [],
                    'add'      => $entities,
                    'keywords' => $keywords
                ]);
                if (!$sharing) {
                    DatabaseModel::rollbackTransaction();
                    return $response->withStatus(400)->withJson(['errors' => 'Cannot share/unshare folder because at least one folder is out of your perimeter']);
                }
                DatabaseModel::commitTransaction();
            }

            FolderModel::update([
                'set'   => [
                    'parent_id' => $data['parent_id'],
                    'level'     => $level,
                    'user_id'   => $folderOwner
                ],
                'where' => ['id = ?'],
                'data'  => [$args['id']]
            ]);
        }

        FolderController::updateChildren($args['id'], $level, $folderOwner);

        FolderModel::update([
            'set'   => [
                'label' => $data['label']
            ],
            'where' => ['id = ?'],
            'data'  => [$args['id']]
        ]);

        HistoryController::add([
            'tableName' => 'folders',
            'recordId'  => $args['id'],
            'eventType' => 'UP',
            'info'      => _FOLDER_MODIFICATION . " : {$data['label']}",
            'moduleId'  => 'folder',
            'eventId'   => 'folderModification',
        ]);

        return $response->withStatus(200);
    }

    public function sharing(Request $request, Response $response, array $args)
    {
        $data = $request->getParsedBody();

        if (!Validator::numericVal()->notEmpty()->validate($args['id'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Query id is empty or not an integer']);
        }
        if (!Validator::boolType()->validate($data['public'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body public is empty or not a boolean']);
        }
        if ($data['public'] && !isset($data['sharing']['entities'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body sharing/entities does not exists']);
        }

        $keywords = [];
        $entities = [];
        foreach ($data['sharing']['entities'] as $item) {
            if (isset($item['entity_id'])) {
                $entities[] = $item;
            } elseif (isset($item['keyword'])) {
                $keywords[] = $item;
            }
        }

        $entitiesBefore = EntityFolderModel::getEntitiesByFolderId(['select' => ['entities_folders.entity_id, edition'], 'folder_id' => $args['id']]);
        $entitiesAfter = $entities;

        $entitiesToRemove = array_udiff($entitiesBefore, $entitiesAfter, function ($a, $b) {
            if ($a['entity_id'] == $b['entity_id'] && $a['edition'] != $b['edition']) {
                return 1;
            }
            if ($a["entity_id"] < $b["entity_id"]) {
                return -1;
            }
            if ($a["entity_id"] > $b["entity_id"]) {
                return 1;
            }
            return 0;
        });

        $entitiesToAdd = array_udiff($entitiesAfter, $entitiesBefore, function ($a, $b) {
            if ($a['entity_id'] == $b['entity_id'] && $a['edition'] != $b['edition']) {
                return 1;
            }
            if ($a["entity_id"] < $b["entity_id"]) {
                return -1;
            }
            if ($a["entity_id"] > $b["entity_id"]) {
                return 1;
            }
            return 0;
        });

        DatabaseModel::beginTransaction();
        $sharing = FolderController::folderSharing([
            'folderId' => $args['id'],
            'public'   => $data['public'],
            'remove'   => $entitiesToRemove,
            'add'      => $entitiesToAdd,
            'keywords' => $keywords
        ]);
        if (!$sharing) {
            DatabaseModel::rollbackTransaction();
            return $response->withStatus(400)->withJson(['errors' => 'Cannot share/unshare folder because at least one folder is out of your perimeter']);
        }
        DatabaseModel::commitTransaction();

        $folder = FolderModel::getById(['select' => ['label'], 'id' => $args['id']]);

        HistoryController::add([
            'tableName' => 'folders',
            'recordId'  => $args['id'],
            'eventType' => 'UP',
            'info'      => _FOLDER_SHARING_MODIFICATION . " : {$folder['label']}",
            'moduleId'  => 'folder',
            'eventId'   => 'folderModification',
        ]);

        return $response->withStatus(204);
    }

    public function folderSharing($args = [])
    {
        $folder = FolderController::getScopeFolders(['login' => $GLOBALS['login'], 'folderId' => $args['folderId'], 'edition' => true]);
        if (empty($folder[0])) {
            return false;
        }
        $entitiesToRemove = array_column($args['remove'], 'entity_id');

        FolderModel::update([
            'set'   => [
                'public' => empty($args['public']) ? 'false' : 'true',
            ],
            'where' => ['id = ?'],
            'data'  => [$args['folderId']]
        ]);

        $entitiesToAdd = array_column($args['add'], 'entity_id');
        if (!empty($entitiesToAdd)) {
            $alreadyPresentEntities = EntityFolderModel::get(['select' => ['entity_id'], 'where' => ['folder_id = ?', 'entity_id in (?)'], 'data' => [$args['folderId'], $entitiesToAdd]]);
            $alreadyPresentEntities = array_column($alreadyPresentEntities, 'entity_id');
            $entitiesToRemove = array_merge($entitiesToRemove, $alreadyPresentEntities);
        }
        if (!empty($entitiesToRemove)) {
            EntityFolderModel::delete(['where' => ['entity_id in (?)', 'folder_id = ?'], 'data' => [$entitiesToRemove, $args['folderId']]]);
        }
        if (!empty($args['add'])) {
            foreach ($args['add'] as $entity) {
                EntityFolderModel::create([
                    'folder_id' => $args['folderId'],
                    'entity_id' => $entity['entity_id'],
                    'edition'   => $entity['edition']
                ]);
            }
        }

        if (!empty($args['keywords'])) {
            $folderKeywords = EntityFolderModel::get([
                'select' => ['id'],
                'where'  => ['folder_id = ?', 'entity_id is null', 'keyword is not null'],
                'data'   => [$args['folderId']]
            ]);
            $folderKeywords = array_column($folderKeywords, 'id');

            if (!empty($folderKeywords)) {
                EntityFolderModel::delete([
                    'where' => ['id in (?)'],
                    'data'  => [$folderKeywords]
                ]);
            }

            foreach ($args['keywords'] as $keyword) {
                EntityFolderModel::create([
                    'folder_id' => $args['folderId'],
                    'entity_id' => null,
                    'edition'   => $keyword['edition'],
                    'keyword'   => $keyword['keyword']
                ]);
            }
        } else {
            EntityFolderModel::delete([
                'where' => ['folder_id = ?', 'entity_id is null', 'keyword is not null'],
                'data'  => [$args['folderId']]
            ]);
        }

        $entitiesOfFolder = EntityFolderModel::getEntitiesByFolderId([
            'select'    => ['entities.entity_id'],
            'folder_id' => $args['folderId']
        ]);
        $entitiesOfFolder = array_column($entitiesOfFolder, 'entity_id');

        $users = UserPinnedFolderModel::get([
            'select' => ['user_id'],
            'where'  => ['folder_id = ?'],
            'data'   => [$args['folderId']]
        ]);

        if (!empty($users) && empty($entitiesOfFolder)) {
            UserPinnedFolderModel::delete([
                'where' => ['folder_id = ?', 'user_id != ?'],
                'data'  => [$args['folderId'], $folder[0]['user_id']]
            ]);
        } else {
            foreach ($users as $user) {
                if ($user['user_id'] != $folder[0]['user_id']) {
                    $inEntities = UserEntityModel::getWithUsers([
                        'select' => ['users.id'],
                        'where'  => ['users.id = ?', 'entity_id in (?)'],
                        'data'   => [$user['user_id'], $entitiesOfFolder]
                    ]);
                    if (empty($inEntities)) {
                        UserPinnedFolderModel::delete([
                            'where' => ['folder_id = ?', 'user_id = ?'],
                            'data'  => [$args['folderId'], $user['user_id']]
                        ]);
                    }
                }
            }
        }

        $folderChild = FolderModel::getChild(['id' => $args['folderId'], 'select' => ['id']]);
        if (!empty($folderChild)) {
            foreach ($folderChild as $child) {
                FolderController::folderSharing([
                    'folderId' => $child['id'],
                    'public'   => $args['public'],
                    'remove'   => $args['remove'],
                    'add'      => $args['add'],
                    'keywords' => $args['keywords']
                ]);
            }
        }

        return true;
    }

    public function delete(Request $request, Response $response, array $aArgs)
    {
        if (!Validator::numericVal()->notEmpty()->validate($aArgs['id'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Query id is empty or not an integer']);
        }
        $userEntities = EntityModel::getWithUserEntities([
            'select' => ['id'],
            'where'  => ['user_id = ?'],
            'data'   => [$GLOBALS['id']]
        ]);
        $userEntities = array_column($userEntities, 'id');

        $canDelete = FolderController::areChildrenInPerimeter(['folderId' => $aArgs['id'], 'entities' => $userEntities]);

        if (!$canDelete) {
            return $response->withStatus(400)->withJson(['errors' => 'Cannot delete because at least one folder is out of your perimeter']);
        }

        $folder = FolderController::getScopeFolders(['login' => $GLOBALS['login'], 'folderId' => $aArgs['id'], 'edition' => true]);
        
        DatabaseModel::beginTransaction();
        $deletion = FolderController::folderDeletion(['folderId' => $aArgs['id']]);
        if (!$deletion) {
            DatabaseModel::rollbackTransaction();
            return $response->withStatus(400)->withJson(['errors' => 'Cannot delete because at least one folder is out of your perimeter']);
        }
        DatabaseModel::commitTransaction();

        HistoryController::add([
            'tableName' => 'folder',
            'recordId'  => $aArgs['id'],
            'eventType' => 'DEL',
            'info'      => _FOLDER_SUPPRESSION . " : {$folder[0]['label']}",
            'moduleId'  => 'folder',
            'eventId'   => 'folderSuppression',
        ]);

        return $response->withStatus(204);
    }

    public static function folderDeletion(array $args = [])
    {
        $folder = FolderController::getScopeFolders(['login' => $GLOBALS['login'], 'folderId' => $args['folderId'], 'edition' => true]);
        if (empty($folder[0])) {
            return false;
        }

        FolderModel::delete(['where' => ['id = ?'], 'data' => [$args['folderId']]]);
        EntityFolderModel::deleteByFolderId(['folder_id' => $args['folderId']]);
        ResourceFolderModel::delete(['where' => ['folder_id = ?'], 'data' => [$args['folderId']]]);
        UserPinnedFolderModel::delete([ 'where' => ['folder_id = ?'], 'data'  => [$args['folderId']] ]);

        $folderChild = FolderModel::getChild(['id' => $args['folderId'], 'select' => ['id']]);
        if (!empty($folderChild)) {
            foreach ($folderChild as $child) {
                $deletion = FolderController::folderDeletion(['folderId' => $child['id']]);
                if (!$deletion) {
                    return false;
                }
            }
        }
        return true;
    }

    public static function areChildrenInPerimeter(array $args = [])
    {
        ValidatorModel::notEmpty($args, ['folderId']);
        ValidatorModel::intVal($args, ['folderId']);
        ValidatorModel::arrayType($args, ['entities']);

        $folder = FolderController::getScopeFolders(['login' => $GLOBALS['login'], 'folderId' => $args['folderId'], 'edition' => true]);
        if (empty($folder[0])) {
            return false;
        }

        $folder = $folder[0];

        // All sub-folders of a folders have the same owner user -> if user is owner of folder, all children are in perimeter
        if ($folder['user_id'] == $GLOBALS['id']) {
            return true;
        }

        $children = [];
        if (!empty($args['entities'])) {
            $children = FolderModel::getWithEntities([
                'select' => ['distinct (folders.id)', 'edition', 'user_id', 'keyword', 'entity_id', 'parent_id'],
                'where'  => ['parent_id = ?', '(entity_id in (?) OR entity_id is null)'],
                'data'   => [$args['folderId'], $args['entities']]
            ]);
        }

        $allEntitiesCanDelete = true;
        foreach ($children as $child) {
            if (!($child['keyword'] == 'ALL_ENTITIES' && $child['edition'] == true)) {
                $allEntitiesCanDelete = false;
                break;
            }
        }


        if (!empty($children)) {
            foreach ($children as $child) {
                if (($child['edition'] == null || $child['edition'] == false) && (!$allEntitiesCanDelete)) {
                    return false;
                }
                if (!FolderController::areChildrenInPerimeter(['folderId' => $child['id'], 'entities' => $args['entities']])) {
                    return false;
                }
            }
        }
        return true;
    }

    public function getResourcesById(Request $request, Response $response, array $args)
    {
        if (!Validator::numericVal()->notEmpty()->validate($args['id'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Route id is not an integer']);
        }

        if (!FolderController::hasFolders(['folders' => [$args['id']], 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(400)->withJson(['errors' => 'Folder out of perimeter']);
        }

        $foldersResources = ResourceFolderModel::get(['select' => ['res_id'], 'where' => ['folder_id = ?'], 'data' => [$args['id']]]);
        $foldersResources = array_column($foldersResources, 'res_id');

        $formattedResources = [];
        $allResources = [];
        $count = 0;
        if (!empty($foldersResources)) {
            $queryParams = $request->getQueryParams();
            $queryParams['offset'] = (empty($queryParams['offset']) || !is_numeric($queryParams['offset']) ? 0 : (int)$queryParams['offset']);
            $queryParams['limit'] = (empty($queryParams['limit']) || !is_numeric($queryParams['limit']) ? 10 : (int)$queryParams['limit']);

            $allQueryData = ResourceListController::getResourcesListQueryData(['data' => $queryParams]);
            if (!empty($allQueryData['order'])) {
                $data['order'] = $allQueryData['order'];
            }

            $rawResources = ResourceListModel::getOnView([
                'select'    => ['res_id'],
                'table'     => $allQueryData['table'],
                'leftJoin'  => $allQueryData['leftJoin'],
                'where'     => array_merge(['res_id in (?)'], $allQueryData['where']),
                'data'      => array_merge([$foldersResources], $allQueryData['queryData']),
                'orderBy'   => empty($data['order']) ? ['creation_date'] : [$data['order']]
            ]);

            $resIds = ResourceListController::getIdsWithOffsetAndLimit(['resources' => $rawResources, 'offset' => $queryParams['offset'], 'limit' => $queryParams['limit']]);

            $allResources = array_column($rawResources, 'res_id');

            $formattedResources = [];
            if (!empty($resIds)) {
                $attachments = AttachmentModel::get([
                    'select'    => ['COUNT(res_id)', 'res_id_master'],
                    'where'     => ['res_id_master in (?)', 'status not in (?)', '((status = ? AND typist = ?) OR status != ?)', 'attachment_type <> ?'],
                    'data'      => [$resIds, ['DEL', 'OBS'], 'TMP', $GLOBALS['id'], 'TMP', 'summary_sheet'],
                    'groupBy'   => ['res_id_master']
                ]);

                $select = [
                    'res_letterbox.res_id', 'res_letterbox.subject', 'res_letterbox.barcode', 'res_letterbox.alt_identifier',
                    'status.label_status AS "status.label_status"', 'status.img_filename AS "status.img_filename"', 'priorities.color AS "priorities.color"',
                    'res_letterbox.filename as res_filename', 'res_letterbox.retention_frozen', 'res_letterbox.binding'
                ];
                $tableFunction = ['status', 'priorities'];
                $leftJoinFunction = ['res_letterbox.status = status.id', 'res_letterbox.priority = priorities.id'];

                $order = 'CASE res_letterbox.res_id ';
                foreach ($resIds as $key => $resId) {
                    $order .= "WHEN {$resId} THEN {$key} ";
                }
                $order .= 'END';

                $resources = ResourceListModel::getOnResource([
                    'select'    => $select,
                    'table'     => $tableFunction,
                    'leftJoin'  => $leftJoinFunction,
                    'where'     => ['res_letterbox.res_id in (?)'],
                    'data'      => [$resIds],
                    'orderBy'   => [$order]
                ]);

                $followedResources = UserFollowedResourceModel::get(['select' => ['res_id'], 'where' => ['user_id = ?'], 'data' => [$GLOBALS['id']]]);
                $followedResources = array_column($followedResources, 'res_id');

                $formattedResources = ResourceListController::getFormattedResources([
                    'resources'     => $resources,
                    'userId'        => $GLOBALS['id'],
                    'attachments'   => $attachments,
                    'checkLocked'   => false,
                    'trackedMails'  => $followedResources,
                    'listDisplay'   => ['folders']
                ]);

                $folderPrivilege = PrivilegeController::hasPrivilege(['privilegeId' => 'include_folders_and_followed_resources_perimeter', 'userId' => $GLOBALS['id']]);
                foreach ($formattedResources as $key => $formattedResource) {
                    if ($folderPrivilege) {
                        $formattedResources[$key]['allowed'] = true;
                    } else {
                        $formattedResources[$key]['allowed'] = ResController::hasRightByResId(['resId' => [$formattedResource['resId']], 'userId' => $GLOBALS['id']]);
                    }
                }
            }

            $count = count($rawResources);
        }

        return $response->withJson(['resources' => $formattedResources, 'countResources' => $count, 'allResources' => $allResources]);
    }

    public function addResourcesById(Request $request, Response $response, array $args)
    {
        if (!Validator::numericVal()->notEmpty()->validate($args['id'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Route id is not an integer']);
        }

        $body = $request->getParsedBody();
        if (!Validator::arrayType()->notEmpty()->validate($body['resources'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body resources is empty or not an array']);
        }

        if (!FolderController::hasFolders(['folders' => [$args['id']], 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(400)->withJson(['errors' => 'Folder out of perimeter']);
        }

        $foldersResources = ResourceFolderModel::get(['select' => ['res_id'], 'where' => ['folder_id = ?'], 'data' => [$args['id']]]);
        $foldersResources = array_column($foldersResources, 'res_id');

        $resourcesToClassify = array_diff($body['resources'], $foldersResources);
        if (empty($resourcesToClassify)) {
            return $response->withJson(['countResources' => count($foldersResources)]);
        }

        if (!ResController::hasRightByResId(['resId' => $resourcesToClassify, 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(400)->withJson(['errors' => 'Resources out of perimeter']);
        }

        foreach ($resourcesToClassify as $value) {
            ResourceFolderModel::create(['folder_id' => $args['id'], 'res_id' => $value]);
        }

        $folders             = FolderModel::getById(['select' => ['label'], 'id' => $args['id']]);
        $resourcesInfo       = ResModel::get(['select' => ['alt_identifier'], 'where' => ['res_id in (?)'], 'data' => [$resourcesToClassify]]);
        $resourcesIdentifier = array_column($resourcesInfo, 'alt_identifier');

        foreach ($resourcesToClassify as $resource) {
            HistoryController::add([
                'tableName' => 'res_letterbox',
                'recordId'  => $resource,
                'eventType' => 'UP',
                'info'      => _ADDED_TO_FOLDER . " \"" . $folders['label'] . "\"",
                'moduleId'  => 'resource',
                'eventId'   => 'resourceModification',
            ]);
        }

        HistoryController::add([
            'tableName' => 'resources_folders',
            'recordId'  => $args['id'],
            'eventType' => 'ADD',
            'info'      => _FOLDER_RESOURCES_ADDED . " : " . implode(", ", $resourcesIdentifier) . " " . _FOLDER_TO_FOLDER . " \"" . $folders['label'] . "\"",
            'moduleId'  => 'folder',
            'eventId'   => 'folderResourceAdded',
        ]);

        return $response->withJson(['countResources' => count($foldersResources) + count($resourcesToClassify)]);
    }

    public function removeResourcesById(Request $request, Response $response, array $args)
    {
        if (!Validator::numericVal()->notEmpty()->validate($args['id'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Route id is not an integer']);
        }

        if (!FolderController::hasFolders(['folders' => [$args['id']], 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(400)->withJson(['errors' => 'Folder out of perimeter']);
        }

        $foldersResources = ResourceFolderModel::get(['select' => ['res_id'], 'where' => ['folder_id = ?'], 'data' => [$args['id']]]);
        $foldersResources = array_column($foldersResources, 'res_id');

        $body = $request->getParsedBody();
        if (!Validator::arrayType()->notEmpty()->validate($body['resources'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body resources is empty or not an array']);
        }

        $resourcesToUnclassify = array_intersect($foldersResources, $body['resources']);
        if (empty($resourcesToUnclassify)) {
            return $response->withJson(['countResources' => count($foldersResources)]);
        }

        $folder = FolderModel::getById(['select' => ['label', 'public', 'user_id'], 'id' => $args['id']]);
        if ($folder['public'] || $folder['user_id'] != $GLOBALS['id']) {
            if (!ResController::hasRightByResId(['resId' => $resourcesToUnclassify, 'userId' => $GLOBALS['id']])) {
                return $response->withStatus(400)->withJson(['errors' => 'Resources out of perimeter']);
            }
        }

        foreach ($resourcesToUnclassify as $value) {
            ResourceFolderModel::delete(['where' => ['folder_id = ?', 'res_id = ?'], 'data' => [$args['id'], $value]]);
        }

        $resourcesInfo       = ResModel::get(['select' => ['alt_identifier'], 'where' => ['res_id in (?)'], 'data' => [$resourcesToUnclassify]]);
        $resourcesIdentifier = array_column($resourcesInfo, 'alt_identifier');

        foreach ($resourcesToUnclassify as $resource) {
            HistoryController::add([
                'tableName' => 'res_letterbox',
                'recordId'  => $resource,
                'eventType' => 'UP',
                'info'      => _REMOVED_TO_FOLDER . " \"" . $folder['label'] . "\"",
                'moduleId'  => 'resource',
                'eventId'   => 'resourceModification',
            ]);
        }

        HistoryController::add([
            'tableName' => 'resources_folders',
            'recordId'  => $args['id'],
            'eventType' => 'DEL',
            'info'      => _FOLDER_RESOURCES_REMOVED . " : " . implode(", ", $resourcesIdentifier) . " " . _FOLDER_TO_FOLDER . " \"" . $folder['label'] . "\"",
            'moduleId'  => 'folder',
            'eventId'   => 'folderResourceRemoved',
        ]);

        return $response->withJson(['countResources' => count($foldersResources) - count($resourcesToUnclassify)]);
    }

    public function getFilters(Request $request, Response $response, array $args)
    {
        if (!Validator::numericVal()->notEmpty()->validate($args['id'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Route id is not an integer']);
        }

        if (!FolderController::hasFolders(['folders' => [$args['id']], 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(400)->withJson(['errors' => 'Folder out of perimeter']);
        }

        $foldersResources = ResourceFolderModel::get(['select' => ['res_id'], 'where' => ['folder_id = ?'], 'data' => [$args['id']]]);
        $foldersResources = array_column($foldersResources, 'res_id');

        if (empty($foldersResources)) {
            return $response->withJson([
                'entities'         => [],
                'priorities'       => [],
                'categories'       => [],
                'statuses'         => [],
                'entitiesChildren' => [],
                'doctypes'         => [],
                'folders'          => []
            ]);
        }

        $where = ['(res_id in (?))'];
        $queryData = [$foldersResources];
        $queryParams = $request->getQueryParams();

        $filters = ResourceListController::getFormattedFilters(['where' => $where, 'queryData' => $queryData, 'queryParams' => $queryParams]);

        return $response->withJson($filters);
    }

    // login (string) : Login of user connected
    // folderId (integer) : Check specific folder
    // edition (boolean) : whether user can edit or not
    public static function getScopeFolders(array $args)
    {
        $login = $args['login'];

        $user = UserModel::getByLogin(['login' => $login, 'select' => ['id']]);
        $userEntities = EntityModel::getWithUserEntities(['select'  => ['entities.id'], 'where' => ['user_id = ?'], 'data' => [$user['id']]]);

        $userEntities = array_column($userEntities, 'id');
        if (empty($userEntities)) {
            $userEntities = [0];
        }

        $args['edition'] = $args['edition'] ?? false;
        if ($args['edition']) {
            $edition = [1];
        } else {
            $edition = [0, 1, null];
        }

        $where = ['keyword = ?', 'entities_folders.edition in (?)'];
        $data = ['ALL_ENTITIES', $edition];

        if (!empty($args['folderId'])) {
            $where[] = 'folder_id = ?';
            $data[]  = $args['folderId'];
        }

        $folderKeywords = EntityFolderModel::get([
            'select' => ['folder_id'],
            'where'  => $where,
            'data'   => $data
        ]);
        $folderKeywords = array_column($folderKeywords, 'folder_id');
        if (empty($folderKeywords)) {
            $folderKeywords = [0];
        }

        $where = ['(user_id = ? OR (entity_id in (?) AND entities_folders.edition in (?)) OR folders.id in (?))'];
        $data = [$user['id'], $userEntities, $edition, $folderKeywords];

        if (!empty($args['folderId'])) {
            $where[] = 'folders.id = ?';
            $data[]  = $args['folderId'];
        }

        $folders = FolderModel::getWithEntities([
            'select'    => ['distinct (folders.id)', 'folders.*'],
            'where'     => $where,
            'data'      => $data,
            'orderBy'   => ['level', 'label desc']
        ]);

        return $folders;
    }

    public function pinFolder(Request $request, Response $response, array $args)
    {
        if (!Validator::numericVal()->notEmpty()->validate($args['id'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Route id not found or is not an integer']);
        }

        if (!FolderController::hasFolders(['folders' => [$args['id']], 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(400)->withJson(['errors' => 'Folder not found or out of your perimeter']);
        }

        $alreadyPinned = UserPinnedFolderModel::get([
            'select'    => ['folder_id', 'user_id'],
            'where'     => ['folder_id = ?', 'user_id = ?'],
            'data'      => [$args['id'], $GLOBALS['id']]
        ]);

        if (!empty($alreadyPinned)) {
            return $response->withStatus(400)->withJson(['errors' => 'Folder is already pinned']);
        }

        UserPinnedFolderModel::create([
            'folder_id' => $args['id'],
            'user_id'   => $GLOBALS['id']
        ]);

        return $response->withStatus(204);
    }

    public function getPinnedFolders(Request $request, Response $response)
    {
        $folders = UserPinnedFolderModel::getById(['user_id' => $GLOBALS['id']]);
        if (empty($folders)) {
            return $response->withJson(['folders' => []]);
        }

        $foldersIds = array_column($folders, 'id');
        $foldersWithResources = FolderModel::getWithEntitiesAndResources([
            'select'   => ['COUNT(DISTINCT resources_folders.res_id)', 'resources_folders.folder_id'],
            'where'    => ['folders.id in (?)'],
            'data'     => [$foldersIds],
            'groupBy'  => ['resources_folders.folder_id']
        ]);

        $pinnedFolders = [];

        foreach ($folders as $folder) {
            $key = array_keys(array_column($foldersWithResources, 'folder_id'), $folder['id']);
            $count = 0;
            if (isset($key[0])) {
                $count = $foldersWithResources[$key[0]]['count'];
            }
            $pinnedFolders[] = [
                'name'       => $folder['label'],
                'id'         => $folder['id'],
                'label'      => $folder['label'],
                'public'     => $folder['public'],
                'user_id'    => $folder['user_id'],
                'parent_id'  => $folder['parent_id'],
                'level'      => $folder['level'],
                'countResources' => $count
            ];
        }

        return $response->withJson(['folders' => $pinnedFolders]);
    }

    public function unpinFolder(Request $request, Response $response, array $args)
    {
        if (!Validator::numericVal()->notEmpty()->validate($args['id'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Route id not found or is not an integer']);
        }

        if (!FolderController::hasFolders(['folders' => [$args['id']], 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(400)->withJson(['errors' => 'Folder not found or out of your perimeter']);
        }

        $alreadyPinned = UserPinnedFolderModel::get([
            'select'    => ['folder_id', 'user_id'],
            'where'     => ['folder_id = ?', 'user_id = ?'],
            'data'      => [$args['id'], $GLOBALS['id']]
        ]);

        if (empty($alreadyPinned)) {
            return $response->withStatus(400)->withJson(['errors' => 'Folder is not pinned']);
        }

        UserPinnedFolderModel::delete([
            'where' => ['folder_id = ?', 'user_id = ?'],
            'data'  => [$args['id'], $GLOBALS['id']]
        ]);

        return $response->withStatus(204);
    }

    public static function hasFolders(array $args)
    {
        ValidatorModel::notEmpty($args, ['folders', 'userId']);
        ValidatorModel::arrayType($args, ['folders']);
        ValidatorModel::intVal($args, ['userId']);

        $entities = UserModel::getEntitiesById(['id' => $args['userId'], 'select' => ['entities.id']]);
        $entities = array_column($entities, 'id');

        if (empty($entities)) {
            $entities = [0];
        }

        $folders = FolderModel::getWithEntities([
            'select'   => ['count(distinct folders.id)'],
            'where'    => ['folders.id in (?)', "(user_id = ? OR entity_id in (?) OR keyword = 'ALL_ENTITIES')"],
            'data'     => [$args['folders'], $args['userId'], $entities]
        ]);

        if ($folders[0]['count'] != count($args['folders'])) {
            return false;
        }

        return true;
    }

    private static function isParentFolder(array $args)
    {
        $parentInfo = FolderModel::getById(['id' => $args['parent_id'], 'select' => ['folders.id', 'parent_id']]);
        if (empty($parentInfo) || $parentInfo['id'] == $args['id']) {
            return true;
        } elseif (!empty($parentInfo['parent_id'])) {
            return FolderController::isParentFolder(['parent_id' => $parentInfo['parent_id'], 'id' => $args['id']]);
        }
        return false;
    }

    private static function updateChildren($parentId, $levelParent, $folderOwner)
    {
        $folderChild = FolderModel::getChild(['id' => $parentId]);
        if (!empty($folderChild)) {
            $level = $levelParent + 1;
            foreach ($folderChild as $child) {
                FolderController::updateChildren($child['id'], $level, $folderOwner);
            }

            $idsChildren = array_column($folderChild, 'id');

            FolderModel::update([
                'set' => [
                    'level' => $level,
                    'user_id' => $folderOwner
                ],
                'where' => ['id in (?)'],
                'data' => [$idsChildren]
            ]);
        }
    }
}
