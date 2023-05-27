<?php

/**
* Copyright Maarch since 2008 under licence GPLv3.
* See LICENCE.txt file at the root folder for more details.
* This file is part of Maarch software.
*
*/

/**
* @brief Entity Model Abstract
* @author dev@maarch.org
*/

namespace Entity\models;

use SrcCore\models\ValidatorModel;
use SrcCore\models\CoreConfigModel;
use SrcCore\models\DatabaseModel;
use User\controllers\UserController;
use User\models\UserModel;

abstract class EntityModelAbstract
{
    public static function get(array $aArgs = [])
    {
        ValidatorModel::arrayType($aArgs, ['select', 'where', 'data', 'orderBy', 'table']);
        ValidatorModel::intType($aArgs, ['limit']);

        $aEntities = DatabaseModel::select([
            'select'    => empty($aArgs['select']) ? ['*'] : $aArgs['select'],
            'table'     => empty($aArgs['table']) ? ['entities'] : $aArgs['table'],
            'where'     => empty($aArgs['where']) ? [] : $aArgs['where'],
            'data'      => empty($aArgs['data']) ? [] : $aArgs['data'],
            'left_join' => empty($aArgs['left_join']) ? [] : $aArgs['left_join'],
            'order_by'  => empty($aArgs['orderBy']) ? [] : $aArgs['orderBy'],
            'limit'     => empty($aArgs['limit']) ? 0 : $aArgs['limit']
        ]);

        return $aEntities;
    }

    public static function getById(array $args)
    {
        ValidatorModel::notEmpty($args, ['id']);
        ValidatorModel::intVal($args, ['id']);

        $entity = DatabaseModel::select([
            'select'    => empty($args['select']) ? ['*'] : $args['select'],
            'table'     => ['entities'],
            'where'     => ['id = ?'],
            'data'      => [$args['id']]
        ]);

        if (empty($entity[0])) {
            return [];
        }

        return $entity[0];
    }

    public static function getByEntityId(array $aArgs)
    {
        ValidatorModel::notEmpty($aArgs, ['entityId']);
        ValidatorModel::stringType($aArgs, ['entityId']);

        $aEntity = DatabaseModel::select([
            'select'    => empty($aArgs['select']) ? ['*'] : $aArgs['select'],
            'table'     => ['entities'],
            'where'     => ['entity_id = ?'],
            'data'      => [$aArgs['entityId']]
        ]);

        if (empty($aEntity[0])) {
            return [];
        }

        return $aEntity[0];
    }

    public static function create(array $args)
    {
        ValidatorModel::notEmpty($args, ['entity_id', 'entity_label', 'short_label', 'entity_type']);
        ValidatorModel::stringType($args, [
            'entity_id', 'entity_label', 'short_label', 'entity_type', 'address_number', 'address_street', 'address_additional1',
            'address_postcode', 'address_town', 'address_country', 'email', 'business_id', 'parent_entity_id',
            'ldap_id', 'transferring_agency', 'entity_full_name', 'producer_service'
        ]);

        $nextSequenceId = DatabaseModel::getNextSequenceValue(['sequenceId' => 'entities_id_seq']);

        DatabaseModel::insert([
            'table'         => 'entities',
            'columnsValues' => [
                'id'                    => $nextSequenceId,
                'entity_id'             => $args['entity_id'],
                'entity_label'          => $args['entity_label'],
                'short_label'           => $args['short_label'],
                'address_number'        => $args['address_number'],
                'address_street'        => $args['address_street'],
                'address_additional1'   => $args['address_additional1'],
                'address_additional2'   => $args['address_additional2'],
                'address_postcode'      => $args['address_postcode'],
                'address_town'          => $args['address_town'],
                'address_country'       => $args['address_country'],
                'email'                 => $args['email'],
                'business_id'           => $args['business_id'],
                'parent_entity_id'      => $args['parent_entity_id'],
                'entity_type'           => $args['entity_type'],
                'ldap_id'               => $args['ldap_id'],
                'entity_full_name'      => $args['entity_full_name'],
                'producer_service'      => $args['producer_service'],
                'external_id'           => $args['external_id']
            ]
        ]);

        return $nextSequenceId;
    }

    public static function update(array $args)
    {
        ValidatorModel::notEmpty($args, ['where', 'data']);
        ValidatorModel::arrayType($args, ['set', 'postSet', 'where', 'data']);

        DatabaseModel::update([
            'table'     => 'entities',
            'set'       => $args['set'],
            'postSet'   => $args['postSet'] ?? null,
            'where'     => $args['where'],
            'data'      => $args['data']
        ]);

        return true;
    }

    public static function delete(array $aArgs)
    {
        ValidatorModel::notEmpty($aArgs, ['where', 'data']);
        ValidatorModel::arrayType($aArgs, ['where', 'data']);

        DatabaseModel::delete([
            'table' => 'entities',
            'where' => $aArgs['where'],
            'data'  => $aArgs['data']
        ]);

        return true;
    }

    public static function getByEmail(array $aArgs = [])
    {
        ValidatorModel::notEmpty($aArgs, ['email']);
        ValidatorModel::stringType($aArgs, ['email']);

        $aReturn = DatabaseModel::select([
            'select'    => empty($aArgs['select']) ? ['*'] : $aArgs['select'],
            'table'     => ['entities'],
            'where'     => ['email = ?', 'enabled = ?'],
            'data'      => [$aArgs['email'], 'Y'],
            'limit'     => 1,
        ]);

        return $aReturn;
    }

    public static function getByBusinessId(array $aArgs = [])
    {
        ValidatorModel::notEmpty($aArgs, ['businessId']);
        ValidatorModel::stringType($aArgs, ['businessId']);

        $aReturn = DatabaseModel::select([
            'select'    => empty($aArgs['select']) ? ['*'] : $aArgs['select'],
            'table'     => ['entities'],
            'where'     => ['business_id = ? and enabled = ?'],
            'data'      => [$aArgs['businessId'], 'Y'],
            'limit'     => 1,
        ]);

        return $aReturn;
    }

    public static function getByUserId(array $aArgs)
    {
        ValidatorModel::notEmpty($aArgs, ['userId']);
        ValidatorModel::intVal($aArgs, ['userId']);
        ValidatorModel::arrayType($aArgs, ['select']);

        $entities = DatabaseModel::select([
            'select'    => empty($aArgs['select']) ? ['*'] : $aArgs['select'],
            'table'     => ['users_entities'],
            'where'     => ['user_id = ?'],
            'data'      => [$aArgs['userId']]
        ]);

        return $entities;
    }

    public static function getWithUserEntities(array $args = [])
    {
        ValidatorModel::arrayType($args, ['select', 'where', 'data']);

        $entities = DatabaseModel::select([
            'select'    => empty($args['select']) ? ['*'] : $args['select'],
            'table'     => ['users_entities', 'entities'],
            'left_join' => ['users_entities.entity_id = entities.entity_id'],
            'where'     => empty($args['where']) ? [] : $args['where'],
            'data'      => empty($args['data']) ? [] : $args['data']
        ]);

        return $entities;
    }

    public static function getEntityRootById(array $aArgs = [])
    {
        ValidatorModel::notEmpty($aArgs, ['entityId']);
        ValidatorModel::stringType($aArgs, ['entityId']);

        $aReturn = entitymodel::getByEntityId([
            'select'   => ['entity_id', 'entity_label', 'parent_entity_id'],
            'entityId' => $aArgs['entityId']
        ]);

        if (!empty($aReturn['parent_entity_id'])) {
            $aReturn = EntityModel::getEntityRootById(['entityId' => $aReturn['parent_entity_id']]);
        }

        return $aReturn;
    }

    public static function getEntityChildren(array $aArgs)
    {
        ValidatorModel::notEmpty($aArgs, ['entityId']);
        ValidatorModel::stringType($aArgs, ['entityId']);

        $allEntities = DatabaseModel::select([
            'select'    => ['entity_id', 'parent_entity_id'],
            'table'     => ['entities'],
            'where'     => ['parent_entity_id IS NOT NULL AND parent_entity_id <> \'\''],
        ]);

        $orderedEntities = [];
        foreach ($allEntities as $value) {
            $orderedEntities[$value['parent_entity_id']][] = $value['entity_id'];
        }

        $entities = EntityModel::getEntityChildrenLoop(['entityId' => $aArgs['entityId'], 'entities' => $orderedEntities]);

        return $entities;
    }

    public static function getEntityChildrenLoop(array $aArgs)
    {
        $entities = [$aArgs['entityId']];
        if (!empty($aArgs['entities']) && array_key_exists($aArgs['entityId'], $aArgs['entities'])) {
            $childrenEntities = $aArgs['entities'][$aArgs['entityId']];
            unset($aArgs['entities'][$aArgs['entityId']]);
            foreach ($childrenEntities as $child) {
                $entities = array_merge($entities, EntityModel::getEntityChildrenLoop(['entityId' => $child, 'entities' => $aArgs['entities']]));
            }
        }

        return $entities;
    }

    public static function getEntityChildrenById(array $args)
    {
        ValidatorModel::notEmpty($args, ['id']);
        ValidatorModel::intVal($args, ['id']);

        $allEntities = DatabaseModel::select([
            'select'    => ['id', 'parent_entity_id'],
            'table'     => ['entities'],
            'where'     => ['parent_entity_id IS NOT NULL AND parent_entity_id <> \'\''],
        ]);

        $orderedEntities = [];
        foreach ($allEntities as $value) {
            $orderedEntities[$value['parent_entity_id']][] = $value['id'];
        }

        $entities = EntityModel::getEntityChildrenLoop(['entityId' => $args['id'], 'entities' => $orderedEntities]);

        return $entities;
    }

    public static function getEntityChildrenSubLevel(array $aArgs)
    {
        ValidatorModel::notEmpty($aArgs, ['entitiesId']);
        ValidatorModel::arrayType($aArgs, ['entitiesId']);

        $aReturn = DatabaseModel::select([
            'select'    => empty($aArgs['select']) ? ['*'] : $aArgs['select'],
            'table'     => ['entities'],
            'where'     => ['parent_entity_id in (?)', 'enabled = ?'],
            'data'      => [$aArgs['entitiesId'], 'Y'],
            'order_by'  => ['entity_label']
        ]);

        return $aReturn;
    }

    public static function getAllEntitiesByUserId(array $args)
    {
        ValidatorModel::notEmpty($args, ['userId']);
        ValidatorModel::intVal($args, ['userId']);

        $entities = [];

        if (UserController::isRoot(['id' => $args['userId']])) {
            $rawEntities = EntityModel::get(['select' => ['entity_id'], 'where' => ['enabled = ?'], 'data' => ['Y']]);
            foreach ($rawEntities as $value) {
                $entities[] = $value['entity_id'];
            }
            return $entities;
        }

        $aReturn = UserModel::getEntitiesById(['id' => $args['userId'], 'select' => ['users_entities.entity_id']]);
        foreach ($aReturn as $value) {
            $entities = array_merge($entities, EntityModel::getEntityChildren(['entityId' => $value['entity_id']]));
        }

        return array_unique($entities);
    }

    public static function getAvailableEntitiesForAdministratorByUserId(array $aArgs)
    {
        ValidatorModel::notEmpty($aArgs, ['userId', 'administratorUserId']);
        ValidatorModel::stringType($aArgs, ['userId', 'administratorUserId']);

        $administrator = UserModel::getByLogin(['login' => $aArgs['administratorUserId'], 'select' => ['id']]);

        if (UserController::isRoot(['id' => $administrator['id']])) {
            $rawEntitiesAllowedForAdministrator = EntityModel::get(['select' => ['entity_id'], 'where' => ['enabled = ?'], 'data' => ['Y'], 'orderBy' => ['entity_label']]);
            $entitiesAllowedForAdministrator = [];
            foreach ($rawEntitiesAllowedForAdministrator as $value) {
                $entitiesAllowedForAdministrator[] = $value['entity_id'];
            }
        } else {
            $entitiesAllowedForAdministrator = EntityModel::getAllEntitiesByUserId(['userId' => $administrator['id']]);
        }

        $user = UserModel::getByLogin(['login' => $aArgs['userId'], 'select' => ['id']]);
        $rawUserEntities = EntityModel::getByUserId(['userId' => $user['id'], 'select' => ['entity_id']]);

        $userEntities = [];
        foreach ($rawUserEntities as $value) {
            $userEntities[] = $value['entity_id'];
        }

        $allEntities = EntityModel::get(['select' => ['entity_id', 'entity_label', 'parent_entity_id'], 'where' => ['enabled = ?'], 'data' => ['Y'], 'orderBy' => ['entity_label']]);

        foreach ($allEntities as $key => $value) {
            $allEntities[$key]['id'] = $value['entity_id'];
            if (empty($value['parent_entity_id'])) {
                $allEntities[$key]['parent'] = '#';
                $allEntities[$key]['icon'] = "fa fa-building";
            } else {
                $allEntities[$key]['parent'] = $value['parent_entity_id'];
                $allEntities[$key]['icon'] = "fa fa-sitemap";
            }
            $allEntities[$key]['text'] = $value['entity_label'];
            if (in_array($value['entity_id'], $userEntities)) {
                $allEntities[$key]['state']['opened'] = true;
                $allEntities[$key]['state']['selected'] = true;
            }
            if (!in_array($value['entity_id'], $entitiesAllowedForAdministrator)) {
                $allEntities[$key]['state']['disabled'] = true;
            }
        }

        return $allEntities;
    }

    public static function getAllowedEntitiesByUserId(array $aArgs)
    {
        if (empty($aArgs['root'])) {
            ValidatorModel::notEmpty($aArgs, ['userId']);
            ValidatorModel::stringType($aArgs, ['userId']);

            $user = UserModel::getByLogin(['login' => $aArgs['userId'], 'select' => ['id']]);
        }

        if (!empty($aArgs['root']) || UserController::isRoot(['id' => $user['id']])) {
            $rawEntitiesAllowed = EntityModel::get(['select' => ['entity_id'], 'where' => ['enabled = ?'], 'data' => ['Y'], 'orderBy' => ['entity_label']]);
            $entitiesAllowed = array_column($rawEntitiesAllowed, 'entity_id');
        } else {
            $entitiesAllowed = EntityModel::getAllEntitiesByUserId(['userId' => $user['id']]);
        }

        $allEntities = EntityModel::get([
            'select'    => ['e1.id', 'e1.entity_id', 'e1.entity_label', 'e1.parent_entity_id', 'e2.id as parent_id'],
            'table'     => ['entities e1', 'entities e2'],
            'left_join' => ['e1.parent_entity_id = e2.entity_id'],
            'where'     => ['e1.enabled = ?'],
            'data'      => ['Y'],
            'orderBy'   => ['e1.parent_entity_id']
        ]);
        $allEntities = EntityModel::removeOrphanedEntities($allEntities);

        foreach ($allEntities as $key => $value) {
            $allEntities[$key]['serialId'] = $value['id'];
            $allEntities[$key]['id'] = $value['entity_id'];
            if (empty($value['parent_entity_id'])) {
                $allEntities[$key]['parentSerialId'] = '#';
                $allEntities[$key]['parent'] = '#';
                $allEntities[$key]['icon'] = "fa fa-building";
            } else {
                $allEntities[$key]['parentSerialId'] = $value['parent_id'];
                $allEntities[$key]['parent'] = $value['parent_entity_id'];
                $allEntities[$key]['icon'] = "fa fa-sitemap";
            }
            if (in_array($value['entity_id'], $entitiesAllowed)) {
                $allEntities[$key]['allowed'] = true;
            } else {
                $allEntities[$key]['allowed'] = false;
                $allEntities[$key]['state']['disabled'] = true;
            }
            $allEntities[$key]['state']['opened'] = true;
            $allEntities[$key]['text'] = $value['entity_label'];
        }

        return $allEntities;
    }

    public static function getUsersById(array $aArgs)
    {
        ValidatorModel::notEmpty($aArgs, ['id']);
        ValidatorModel::stringType($aArgs, ['id']);
        ValidatorModel::arrayType($aArgs, ['select']);

        $aUsers = DatabaseModel::select([
            'select'    => empty($aArgs['select']) ? ['*'] : $aArgs['select'],
            'table'     => ['users_entities, users'],
            'where'     => ['users_entities.entity_id = ?', 'users_entities.user_id = users.id', 'users.status != ?'],
            'data'      => [$aArgs['id'], 'DEL']
        ]);

        return $aUsers;
    }

    public static function getTypes()
    {
        $types = [];

        $loadedXml = CoreConfigModel::getXmlLoaded(['path' => 'config/typentity.xml']);
        if ($loadedXml) {
            foreach ($loadedXml->TYPE as $value) {
                $types[] = [
                    'id'        => (string)$value->id,
                    'label'     => (string)$value->label,
                    'typelevel' => (string)$value->typelevel
                ];
            }
        }

        return $types;
    }

    public static function getRoles()
    {
        $roles = [];
        $tmpRoles = DatabaseModel::select([
            'select'    => ['role_id', 'label', 'keep_in_list_instance'],
            'table'     => ['roles']
        ]);

        foreach ($tmpRoles as $tmpValue) {
            $roles[] = [
                'id'                    => $tmpValue['role_id'],
                'label'                 => defined($tmpValue['label']) ? constant($tmpValue['label']) : $tmpValue['label'],
                'keepInListInstance'    => $tmpValue['keep_in_list_instance']
            ];
        }

        return $roles;
    }

    public static function getEntityPathByEntityId(array $args)
    {
        ValidatorModel::notEmpty($args, ['entityId']);
        ValidatorModel::stringType($args, ['entityId', 'path']);

        $entity = EntityModel::getByEntityId([
            'select'   => ['entity_id', 'parent_entity_id'],
            'entityId' => $args['entityId']
        ]);

        if (!empty($args['path'])) {
            $args['path'] = "/{$args['path']}";
        }
        $args['path'] = $entity['entity_id'] . $args['path'];

        if (empty($entity['parent_entity_id'])) {
            return $args['path'];
        }

        return EntityModel::getEntityPathByEntityId(['entityId' => $entity['parent_entity_id'], 'path' => $args['path']]);
    }

    public static function removeOrphanedEntities(array $entities)
    {
        do {
            $entitiesCount = count($entities);
            $entitiesIds = array_column($entities, 'entity_id');
            if (empty($entitiesIds)) {
                return $entities;
            }
            $entities = array_values(array_filter($entities, function($entity) use ($entitiesIds) {
                return empty($entity['parent_entity_id']) || ($entity['parent_entity_id'] != $entity['entity_id'] && in_array($entity['parent_entity_id'], $entitiesIds));
            }));
        } while (count($entities) != $entitiesCount);

        return $entities;
    }
}
