<?php

/**
* Copyright Maarch since 2008 under licence GPLv3.
* See LICENCE.txt file at the root folder for more details.
* This file is part of Maarch software.
*
*/

/**
* @brief Prepared Clause Controller
* @author dev@maarch.org
*/

namespace SrcCore\controllers;

use SrcCore\models\ValidatorModel;
use Entity\models\EntityModel;
use Resource\models\ResModel;
use User\models\UserModel;

class PreparedClauseController
{
    public static function getPreparedClause(array $aArgs)
    {
        ValidatorModel::notEmpty($aArgs, ['clause', 'userId']);
        ValidatorModel::stringType($aArgs, ['clause']);
        ValidatorModel::intVal($aArgs, ['userId']);

        $clause = $aArgs['clause'];
        $user = UserModel::getById(['id' => $aArgs['userId'], 'select' => ['user_id', 'mail']]);

        if (preg_match('/@user_id/', $clause)) {
            $clause = str_replace('@user_id', "{$aArgs['userId']}", $clause);
        }
        if (preg_match('/@user/', $clause)) {
            $clause = str_replace('@user', "'{$user['user_id']}'", $clause);
        }
        if (preg_match('/@email/', $clause)) {
            $clause = str_replace('@email', "'{$user['mail']}'", $clause);
        }
        if (preg_match('/@my_entities_id/', $clause)) {
            $entities = EntityModel::getByUserId(['userId' => $aArgs['userId'], 'select' => ['entity_id']]);
            $entities = array_column($entities, 'entity_id');
            if (!empty($entities)) {
                $entities = EntityModel::get(['select' => ['id'], 'where' => ['entity_id in (?)'], 'data' => [$entities]]);
            }

            $myEntitiesClause = '';
            foreach ($entities as $key => $entity) {
                if ($key > 0) {
                    $myEntitiesClause .= ", ";
                }
                $myEntitiesClause .= $entity['id'];
            }
            if (empty($myEntitiesClause)) {
                $myEntitiesClause = 0;
            }

            $clause = str_replace('@my_entities_id', $myEntitiesClause, $clause);
        }
        if (preg_match('/@my_entities/', $clause)) {
            $entities = EntityModel::getByUserId(['userId' => $aArgs['userId'], 'select' => ['entity_id']]);

            $myEntitiesClause = '';
            foreach ($entities as $key => $entity) {
                if ($key > 0) {
                    $myEntitiesClause .= ", ";
                }
                $myEntitiesClause .= "'{$entity['entity_id']}'";
            }
            if (empty($myEntitiesClause)) {
                $myEntitiesClause = "''";
            }

            $clause = str_replace('@my_entities', $myEntitiesClause, $clause);
        }
        if (preg_match('/@my_primary_entity_id/', $clause)) {
            $entity = UserModel::getPrimaryEntityById(['id' => $aArgs['userId'], 'select' => ['entities.id']]);

            if (empty($entity)) {
                $primaryEntity = 0;
            } else {
                $primaryEntity = $entity['id'];
            }

            $clause = str_replace('@my_primary_entity_id', $primaryEntity, $clause);
        }
        if (preg_match('/@my_primary_entity/', $clause)) {
            $entity = UserModel::getPrimaryEntityById(['id' => $aArgs['userId'], 'select' => ['entities.entity_id']]);

            if (empty($entity)) {
                $primaryEntity = "''";
            } else {
                $primaryEntity = "'" . $entity['entity_id'] . "'";
            }

            $clause = str_replace('@my_primary_entity', $primaryEntity, $clause);
        }
        if (preg_match('/@all_entities/', $clause)) {
            $allEntities = EntityModel::get(['select' => ['entity_id'], 'where' => ['enabled = ?'], 'data' => ['Y']]);

            $allEntitiesClause = '';
            foreach ($allEntities as $key => $allEntity) {
                if ($key > 0) {
                    $allEntitiesClause .= ", ";
                }
                $allEntitiesClause .= "'{$allEntity['entity_id']}'";
            }
            if (empty($allEntitiesClause)) {
                $allEntitiesClause = "''";
            }

            $clause = str_replace("@all_entities", $allEntitiesClause, $clause);
        }

        $total = preg_match_all("|@subentities_id\[([^\]]*)\]|", $clause, $subEntities, PREG_PATTERN_ORDER);
        if ($total > 0) {
            for ($i = 0; $i < $total; $i++) {
                $aEntities = [];
                $tmpSubEntities = str_replace("'", '', $subEntities[1][$i]);
                if (preg_match('/,/', $tmpSubEntities)) {
                    $aEntities = preg_split('/,/', $tmpSubEntities);
                } else {
                    $aEntities[] = $tmpSubEntities;
                }

                $allSubEntities = [];
                foreach ($aEntities as $entity) {
                    if (!empty($entity)) {
                        $subEntitiesForEntity = EntityModel::getEntityChildrenById(['id' => trim($entity)]);
                        unset($subEntitiesForEntity[0]);
                        $allSubEntities = array_merge($allSubEntities, $subEntitiesForEntity);
                    }
                }

                $allSubEntitiesClause = '';
                foreach ($allSubEntities as $key => $allSubEntity) {
                    if ($key > 0) {
                        $allSubEntitiesClause .= ", ";
                    }
                    $allSubEntitiesClause .= "'{$allSubEntity}'";
                }
                if (empty($allSubEntitiesClause)) {
                    $allSubEntitiesClause = "0";
                }

                $clause = preg_replace("|@subentities_id\[[^\]]*\]|", $allSubEntitiesClause, $clause, 1);
            }
        }

        $total = preg_match_all("|@subentities\[('[^\]]*')\]|", $clause, $subEntities, PREG_PATTERN_ORDER);
        if ($total > 0) {
            for ($i = 0; $i < $total; $i++) {
                $aEntities = [];
                $tmpSubEntities = str_replace("'", '', $subEntities[1][$i]);
                if (preg_match('/,/', $tmpSubEntities)) {
                    $aEntities = preg_split('/,/', $tmpSubEntities);
                } else {
                    $aEntities[] = $tmpSubEntities;
                }

                $allSubEntities = [];
                foreach ($aEntities as $entity) {
                    if (!empty($entity)) {
                        $subEntitiesForEntity = EntityModel::getEntityChildren(['entityId' => trim($entity)]);
                        unset($subEntitiesForEntity[0]);
                        $allSubEntities = array_merge($allSubEntities, $subEntitiesForEntity);
                    }
                }

                $allSubEntitiesClause = '';
                foreach ($allSubEntities as $key => $allSubEntity) {
                    if ($key > 0) {
                        $allSubEntitiesClause .= ", ";
                    }
                    $allSubEntitiesClause .= "'{$allSubEntity}'";
                }
                if (empty($allSubEntitiesClause)) {
                    $allSubEntitiesClause = "''";
                }

                $clause = preg_replace("|@subentities\['[^\]]*'\]|", $allSubEntitiesClause, $clause, 1);
            }
        }

        $total = preg_match_all("|@immediate_children\[('[^\]]*')\]|", $clause, $immediateChildrens, PREG_PATTERN_ORDER);
        if ($total > 0) {
            for ($i = 0; $i < $total; $i++) {
                $aEntities = [];
                $tmpImmediateChildrens = str_replace("'", '', $immediateChildrens[1][$i]);
                if (preg_match('/,/', $tmpImmediateChildrens)) {
                    $aEntities = preg_split('/,/', $tmpImmediateChildrens);
                } else {
                    $aEntities[] = $tmpImmediateChildrens;
                }

                $allImmediateChildrens = [];
                foreach ($aEntities as $entity) {
                    $immediateChildrensForEntity = EntityModel::get(['select' => ['entity_id'], 'where' => ['parent_entity_id = ?'], 'data' => [trim($entity)]]);
                    foreach ($immediateChildrensForEntity as $value) {
                        $allImmediateChildrens[] = $value['entity_id'];
                    }
                }

                $allImmediateChildrensClause = '';
                foreach ($allImmediateChildrens as $key => $allImmediateChild) {
                    if ($key > 0) {
                        $allImmediateChildrensClause .= ", ";
                    }
                    $allImmediateChildrensClause .= "'{$allImmediateChild}'";
                }
                if (empty($allImmediateChildrensClause)) {
                    $allImmediateChildrensClause = "''";
                }

                $clause = preg_replace("|@immediate_children\['[^\]]*'\]|", $allImmediateChildrensClause, $clause, 1);
            }
        }

        $total = preg_match_all("|@parent_entity\[('[^\]]*')\]|", $clause, $parentEntity, PREG_PATTERN_ORDER);
        if ($total > 0) {
            for ($i = 0; $i < $total; $i++) {
                $tmpParentEntity = trim(str_replace("'", '', $parentEntity[1][$i]));
                if (!empty($tmpParentEntity)) {
                    $entity = EntityModel::getByEntityId(['entityId' => $tmpParentEntity, 'select' => ['entity_id', 'parent_entity_id']]);
                }
                if (empty($entity['parent_entity_id'])) {
                    $parentEntityClause = "''";
                } else {
                    $parentEntityClause = "'{$entity['parent_entity_id']}'";
                }

                $clause = preg_replace("|@parent_entity\['[^\]]*'\]|", $parentEntityClause, $clause, 1);
            }
        }

        $total = preg_match_all("|@sisters_entities\[('[^\]]*')\]|", $clause, $sistersEntities, PREG_PATTERN_ORDER);
        if ($total > 0) {
            for ($i = 0; $i < $total; $i++) {
                $tmpSisterEntity = trim(str_replace("'", '', $sistersEntities[1][$i]));
                if (!empty($tmpSisterEntity)) {
                    $sisterEntity = EntityModel::getByEntityId(['entityId' => $tmpSisterEntity, 'select' => ['parent_entity_id']]);
                }
                $allSisterEntities = [];
                if (!empty($sisterEntity)) {
                    $allSisterEntities = EntityModel::get(['select' => ['entity_id'], 'where' => ['parent_entity_id = ?'], 'data' => [$sisterEntity['parent_entity_id']]]);
                }

                $allSisterEntitiesClause = '';
                foreach ($allSisterEntities as $key => $allSisterEntity) {
                    if ($key > 0) {
                        $allSisterEntitiesClause .= ", ";
                    }
                    $allSisterEntitiesClause .= "'{$allSisterEntity['entity_id']}'";
                }
                if (empty($allSisterEntitiesClause)) {
                    $allSisterEntitiesClause = "''";
                }

                $clause = preg_replace("|@sisters_entities\['[^\]]*'\]|", $allSisterEntitiesClause, $clause, 1);
            }
        }

        $total = preg_match_all("|@entity_type\[('[^\]]*')\]|", $clause, $entityType, PREG_PATTERN_ORDER);
        if ($total > 0) {
            for ($i = 0; $i < $total; $i++) {
                $tmpEntityType = trim(str_replace("'", '', $entityType[1][$i]));
                $allEntitiesType = EntityModel::get(['select' => ['entity_id'], 'where' => ['entity_type = ?'], 'data' => [$tmpEntityType]]);

                $allEntitiesTypeClause = '';
                foreach ($allEntitiesType as $key => $allEntityType) {
                    if ($key > 0) {
                        $allEntitiesTypeClause .= ", ";
                    }
                    $allEntitiesTypeClause .= "'{$allEntityType['entity_id']}'";
                }
                if (empty($allEntitiesTypeClause)) {
                    $allEntitiesTypeClause = "''";
                }

                $clause = preg_replace("|@entity_type\['[^\]]*'\]|", $allEntitiesTypeClause, $clause, 1);
            }
        }

        return "({$clause})";
    }

    public static function isRequestValid(array $aArgs)
    {
        ValidatorModel::notEmpty($aArgs, ['clause', 'userId']);
        ValidatorModel::stringType($aArgs, ['clause', 'userId']);
        ValidatorModel::arrayType($aArgs, ['select', 'orderBy']);
        ValidatorModel::intType($aArgs, ['limit']);

        $user = UserModel::getByLogin(['login' => $aArgs['userId'], 'select' => ['id']]);
        $clause = PreparedClauseController::getPreparedClause(['clause' => $aArgs['clause'], 'userId' => $user['id']]);

        $preg = preg_match('#\b(?:abort|alter|copy|create|delete|disgard|drop|execute|grant|insert|load|lock|move|reset|truncate|update)\b#i', $clause);
        if ($preg === 1) {
            return false;
        }

        if (!empty($aArgs['select'])) {
            $select = implode(" AND ", $aArgs['select']);
            $preg = preg_match('#\b(?:abort|alter|copy|create|delete|disgard|drop|execute|grant|insert|load|lock|move|reset|truncate|update|select)\b#i', $select);
            if ($preg === 1) {
                return false;
            }
        } else {
            $aArgs['select'] = [1];
        }

        try {
            ResModel::getOnView(['select' => $aArgs['select'], 'where' => [$clause, '1=1'], 'orderBy' => $aArgs['orderBy'] ?? [], 'limit' => $aArgs['limit'] ?? null]);
        } catch (\Exception $e) {
            return false;
        }

        return true;
    }
}
