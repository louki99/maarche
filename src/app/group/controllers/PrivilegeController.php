<?php

namespace Group\controllers;

use Basket\models\BasketModel;
use Basket\models\GroupBasketModel;
use Basket\models\RedirectBasketModel;
use Group\models\GroupModel;
use Group\models\PrivilegeModel;
use Resource\controllers\ResController;
use Resource\models\ResModel;
use Respect\Validation\Validator;
use SignatureBook\controllers\SignatureBookController;
use Slim\Psr7\Request;
use SrcCore\http\Response;
use SrcCore\controllers\PreparedClauseController;
use SrcCore\models\CoreConfigModel;
use SrcCore\models\DatabaseModel;
use SrcCore\models\ValidatorModel;
use User\controllers\UserController;
use User\models\UserGroupModel;
use User\models\UserModel;

class PrivilegeController
{
    public static function addPrivilege(Request $request, Response $response, array $args)
    {
        if (!PrivilegeController::hasPrivilege(['privilegeId' => 'admin_groups', 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }

        if (!Validator::notEmpty()->intVal()->validate($args['id'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Route id is empty or not an integer']);
        }
        if (!Validator::stringType()->notEmpty()->validate($args['privilegeId'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Route privilegeId is empty or not an integer']);
        }


        if (in_array($args['privilegeId'], ['create_custom', 'admin_update_control'])) {
            $config = CoreConfigModel::getJsonLoaded(['path' => 'config/config.json']);
            if (!empty($config['config']['lockAdvancedPrivileges'])) {
                return $response->withStatus(403)->withJson(['errors' => 'Privilege forbidden']);
            }
        } elseif ($args['privilegeId'] == 'admin_password_rules') {
            $loginMethod = CoreConfigModel::getLoggingMethod();
            if ($loginMethod['id'] != 'standard') {
                return $response->withStatus(403)->withJson(['errors' => 'Privilege forbidden']);
            }
        }

        $group = GroupModel::getById(['id' => $args['id']]);
        if (empty($group)) {
            return $response->withStatus(400)->withJson(['errors' => 'Group not found']);
        }

        if (PrivilegeModel::groupHasPrivilege(['privilegeId' => $args['privilegeId'], 'groupId' => $group['group_id']])) {
            return $response->withStatus(204);
        }

        PrivilegeModel::addPrivilegeToGroup(['privilegeId' => $args['privilegeId'], 'groupId' => $group['group_id']]);

        if ($args['privilegeId'] == 'admin_users') {
            $groups = GroupModel::get(['select' => ['id']]);
            $groups = array_column($groups, 'id');

            $parameters = json_encode(['groups' => $groups]);

            PrivilegeModel::updateParameters(['groupId' => $group['group_id'], 'privilegeId' => $args['privilegeId'], 'parameters' => $parameters]);
        }

        return $response->withStatus(204);
    }

    public static function removePrivilege(Request $request, Response $response, array $args)
    {
        if (!PrivilegeController::hasPrivilege(['privilegeId' => 'admin_groups', 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }

        if (!Validator::notEmpty()->intVal()->validate($args['id'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Route id is empty or not an integer']);
        }
        if (!Validator::stringType()->notEmpty()->validate($args['privilegeId'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Route privilegeId is empty or not an integer']);
        }

        $group = GroupModel::getById(['id' => $args['id']]);
        if (empty($group)) {
            return $response->withStatus(400)->withJson(['errors' => 'Group not found']);
        }

        if (!PrivilegeModel::groupHasPrivilege(['privilegeId' => $args['privilegeId'], 'groupId' => $group['group_id']])) {
            return $response->withStatus(204);
        }

        PrivilegeModel::removePrivilegeToGroup(['privilegeId' => $args['privilegeId'], 'groupId' => $group['group_id']]);

        return $response->withStatus(204);
    }

    public static function updateParameters(Request $request, Response $response, array $args)
    {
        if (!PrivilegeController::hasPrivilege(['privilegeId' => 'admin_groups', 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }

        if (!Validator::notEmpty()->intVal()->validate($args['id'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Route id is empty or not an integer']);
        }
        if (!Validator::stringType()->notEmpty()->validate($args['privilegeId'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Route privilegeId is empty or not an integer']);
        }

        $group = GroupModel::getById(['id' => $args['id']]);
        if (empty($group)) {
            return $response->withStatus(400)->withJson(['errors' => 'Group not found']);
        }

        $data = $request->getParsedBody();

        if (!Validator::arrayType()->validate($data['parameters'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body parameters is not an array']);
        }

        $parameters = json_encode($data['parameters']);

        PrivilegeModel::updateParameters(['groupId' => $group['group_id'], 'privilegeId' => $args['privilegeId'], 'parameters' => $parameters]);

        return $response->withStatus(204);
    }

    public static function getParameters(Request $request, Response $response, array $args)
    {
        if (!Validator::notEmpty()->intVal()->validate($args['id'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Route id is empty or not an integer']);
        }
        if (!Validator::stringType()->notEmpty()->validate($args['privilegeId'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Route privilegeId is empty or not an integer']);
        }

        $group = GroupModel::getById(['id' => $args['id']]);
        if (empty($group)) {
            return $response->withStatus(400)->withJson(['errors' => 'Group not found']);
        }

        $queryParams = $request->getQueryParams();

        $parameters = PrivilegeModel::getParametersFromGroupPrivilege(['groupId' => $group['group_id'], 'privilegeId' => $args['privilegeId']]);

        if (!empty($queryParams['parameter'])) {
            if (!isset($parameters[$queryParams['parameter']])) {
                return $response->withStatus(400)->withJson(['errors' => 'Parameter not found']);
            }

            $parameters = $parameters[$queryParams['parameter']];
        }

        return $response->withJson($parameters);
    }

    public static function hasPrivilege(array $args)
    {
        ValidatorModel::notEmpty($args, ['privilegeId', 'userId']);
        ValidatorModel::stringType($args, ['privilegeId']);
        ValidatorModel::intVal($args, ['userId']);

        if (in_array($args['privilegeId'], ['create_custom', 'admin_update_control'])) {
            $file = CoreConfigModel::getJsonLoaded(['path' => 'config/config.json']);
            if (!empty($file['config']['lockAdvancedPrivileges'])) {
                return false;
            }
        } elseif ($args['privilegeId'] == 'admin_password_rules') {
            $loginMethod = CoreConfigModel::getLoggingMethod();
            if ($loginMethod['id'] != 'standard') {
                return false;
            }
        }

        if (UserController::isRoot(['id' => $args['userId']])) {
            return true;
        }

        $hasPrivilege = DatabaseModel::select([
            'select'    => [1],
            'table'     => ['usergroup_content, usergroups_services, usergroups'],
            'where'     => [
                'usergroup_content.group_id = usergroups.id',
                'usergroups.group_id = usergroups_services.group_id',
                'usergroup_content.user_id = ?',
                'usergroups_services.service_id = ?'
            ],
            'data'      => [$args['userId'], $args['privilegeId']]
        ]);

        return !empty($hasPrivilege);
    }

    public static function getPrivilegesByUser(array $args)
    {
        ValidatorModel::notEmpty($args, ['userId']);
        ValidatorModel::intVal($args, ['userId']);

        if (UserController::isRoot(['id' => $args['userId']])) {
            return ['ALL_PRIVILEGES'];
        }

        $privilegesStoredInDB = PrivilegeModel::getByUser(['id' => $args['userId']]);
        $privilegesStoredInDB = array_column($privilegesStoredInDB, 'service_id');

        $file   = CoreConfigModel::getJsonLoaded(['path' => 'config/config.json']);
        $isLock = !empty($file['config']['lockAdvancedPrivileges']);
        foreach (['create_custom', 'admin_update_control'] as $advancedPrivilege) {
            $key = array_search($advancedPrivilege, $privilegesStoredInDB);
            if ($isLock && $key !== false) {
                unset($privilegesStoredInDB[$key]);
            }
        }
        $loginMethod = CoreConfigModel::getLoggingMethod();
        if ($loginMethod['id'] != 'standard') {
            $key = array_search('admin_password_rules', $privilegesStoredInDB);
            if ($key !== false) {
                unset($privilegesStoredInDB[$key]);
            }
        }

        $privilegesStoredInDB = array_values($privilegesStoredInDB);
        return $privilegesStoredInDB;
    }

    public static function getAssignableGroups(array $args)
    {
        ValidatorModel::notEmpty($args, ['userId']);
        ValidatorModel::intVal($args, ['userId']);

        $rawUserGroups = UserModel::getGroupsById(['id' => $args['userId']]);
        $userGroups = array_column($rawUserGroups, 'group_id');

        $assignable = [];
        foreach ($userGroups as $userGroup) {
            $groups = PrivilegeModel::getParametersFromGroupPrivilege(['groupId' => $userGroup, 'privilegeId' => 'admin_users']);
            if (!empty($groups)) {
                $groups = $groups['groups'];
                $assignable = array_merge($assignable, $groups);
            }
        }

        foreach ($assignable as $key => $group) {
            $assignable[$key] = GroupModel::getById(['id' => $group, 'select' => ['group_id', 'group_desc']]);
        }

        return $assignable;
    }

    public static function canAssignGroup(array $args)
    {
        ValidatorModel::notEmpty($args, ['userId', 'groupId']);
        ValidatorModel::intVal($args, ['userId', 'groupId']);

        if (UserController::isRoot(['id' => $args['userId']])) {
            return true;
        }

        $privileges = PrivilegeModel::getByUserAndPrivilege(['userId' => $args['userId'], 'privilegeId' => 'admin_users']);
        $privileges = array_column($privileges, 'parameters');

        if (empty($privileges)) {
            return false;
        }
        $assignable = [];

        foreach ($privileges as $groups) {
            $groups = json_decode($groups);
            $groups = $groups->groups;
            if ($groups != null) {
                $assignable = array_merge($assignable, $groups);
            }
        }

        if (count($assignable) == 0) {
            return false;
        }

        return in_array($args['groupId'], $assignable);
    }

    public static function canIndex(array $args)
    {
        ValidatorModel::notEmpty($args, ['userId']);
        ValidatorModel::intVal($args, ['userId']);

        $where = ['usergroup_content.user_id = ?', 'usergroups.can_index = ?'];
        $data  = [$args['userId'], true];

        if (!empty($args['groupId'])) {
            $where[] = 'usergroups.id = ?';
            $data[]  = $args['groupId'];
        }

        $canIndex = UserGroupModel::getWithGroups([
            'select'    => [1],
            'where'     => $where,
            'data'      => $data
        ]);

        return !empty($canIndex);
    }

    public static function canUpdateResource(array $args)
    {
        ValidatorModel::notEmpty($args, ['userId', 'resId']);
        ValidatorModel::intVal($args, ['userId', 'resId']);

        if (PrivilegeController::hasPrivilege(['privilegeId' => 'edit_resource', 'userId' => $args['userId']])) {
            return ResController::hasRightByResId(['resId' => [$args['resId']], 'userId' => $args['userId']]);
        }

        $canUpdateInProcess = PrivilegeController::isResourceInProcess(['userId' => $args['userId'], 'resId' => $args['resId'], 'canUpdateData' => true]);
        $canUpdateInSignatureBook = SignatureBookController::isResourceInSignatureBook(['userId' => $args['userId'], 'resId' => $args['resId'], 'canUpdateDocuments' => true]);

        return $canUpdateInProcess || $canUpdateInSignatureBook;
    }

    public static function isResourceInProcess(array $args)
    {
        ValidatorModel::notEmpty($args, ['resId', 'userId']);
        ValidatorModel::intVal($args, ['resId', 'userId']);

        $currentUser = UserModel::getById(['id' => $args['userId'], 'select' => ['id', 'user_id']]);

        $basketsClause = '';

        $groups = UserGroupModel::get(['select' => ['group_id'], 'where' => ['user_id = ?'], 'data' => [$currentUser['id']]]);
        $groups = array_column($groups, 'group_id');
        if (!empty($groups)) {
            $groups = GroupModel::get(['select' => ['group_id'], 'where' => ['id in (?)'], 'data' => [$groups]]);
            $groups = array_column($groups, 'group_id');

            $where = ['group_id in (?)', 'list_event = ?'];
            $data = [$groups, 'processDocument'];
            if (!empty($args['canUpdateData'])) {
                $where[] = "list_event_data->>'canUpdateData' = ?";
                $data[] = 'true';
            }
            if (!empty($args['canUpdateModel'])) {
                $where[] = "list_event_data->>'canUpdateModel' = ?";
                $data[] = 'true';
            }
            $baskets = GroupBasketModel::get(['select' => ['basket_id'], 'where' => $where, 'data' => $data]);
            $baskets = array_column($baskets, 'basket_id');
            if (!empty($baskets)) {
                $clauses = BasketModel::get(['select' => ['basket_clause'], 'where' => ['basket_id in (?)'], 'data' => [$baskets]]);

                foreach ($clauses as $clause) {
                    $basketClause = PreparedClauseController::getPreparedClause(['clause' => $clause['basket_clause'], 'userId' => $args['userId']]);
                    if (!empty($basketsClause)) {
                        $basketsClause .= ' or ';
                    }
                    $basketsClause .= "({$basketClause})";
                }
            }
        }

        $assignedBaskets = RedirectBasketModel::getAssignedBasketsByUserId(['userId' => $currentUser['id']]);
        foreach ($assignedBaskets as $basket) {
            $where = ['basket_id = ?', 'group_id = ?', 'list_event = ?'];
            $data = [$basket['basket_id'], $basket['oldGroupId'], 'processDocument'];
            if (!empty($args['canUpdateData'])) {
                $where[] = "list_event_data->>'canUpdateData' = ?";
                $data[] = 'true';
            }
            if (!empty($args['canUpdateModel'])) {
                $where[] = "list_event_data->>'canUpdateModel' = ?";
                $data[] = 'true';
            }
            $hasSB = GroupBasketModel::get(['select' => [1], 'where' => $where, 'data' => $data]);
            if (!empty($hasSB)) {
                $basketClause = PreparedClauseController::getPreparedClause(['clause' => $basket['basket_clause'], 'userId' => $basket['owner_user_id']]);
                if (!empty($basketsClause)) {
                    $basketsClause .= ' or ';
                }
                $basketsClause .= "({$basketClause})";
            }
        }

        try {
            $res = ResModel::getOnView(['select' => [1], 'where' => ['res_id = ?', "({$basketsClause})"], 'data' => [$args['resId']]]);
            if (empty($res)) {
                return false;
            }
        } catch (\Exception $e) {
            return false;
        }

        return true;
    }

    public static function isAdvancedPrivilegesLocked()
    {
        $file = CoreConfigModel::getJsonLoaded(['path' => 'config/config.json']);

        return !empty($file['config']['lockAdvancedPrivileges']);
    }
}
