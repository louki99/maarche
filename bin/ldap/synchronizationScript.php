<?php

/**
 * Copyright Maarch since 2008 under licence GPLv3.
 * See LICENCE.txt file at the root folder for more details.
 * This file is part of Maarch software.
 *
 */

/**
 * @brief Synchronization Script
 * @author dev@maarch.org
 */

chdir('../..');

require 'vendor/autoload.php';

$customId = null;
if (!empty($argv[1]) && $argv[1] == '--customId' && !empty($argv[2])) {
    $customId = $argv[2];
}

$xmlfile = initialize($customId);
$synchronizeUsers = false;
if (!empty($xmlfile->synchronizeUsers) && (string)$xmlfile->synchronizeUsers == 'true') {
    $synchronizeUsers = true;
}
$synchronizeEntities = false;
if (!empty($xmlfile->synchronizeEntities) && (string)$xmlfile->synchronizeEntities == 'true') {
    $synchronizeEntities = true;
}

if ($synchronizeUsers) {
    $maarchUsers = \User\models\UserModel::get(['select' => ['id', 'user_id', 'firstname', 'lastname', 'phone', 'mail', 'status', 'mode']]);
    $ldapUsers = getUsersEntries($xmlfile);
    if (!empty($ldapUsers['errors'])) {
        writeLog(['message' => "[ERROR] {$ldapUsers['errors']}"]);
        $synchronizeUsers = false;
        $ldapUsers = null;
    }
}
if ($synchronizeEntities) {
    $maarchEntities = \Entity\models\EntityModel::get(['select' => ['id', 'entity_id', 'entity_label', 'short_label', 'entity_type', 'parent_entity_id']]);
    $ldapEntities = getEntitiesEntries($xmlfile);
    if (!empty($ldapEntities['errors'])) {
        writeLog(['message' => "[ERROR] {$ldapEntities['errors']}"]);
        $synchronizeEntities = false;
        $ldapEntities = null;
    }
}

if (!empty($ldapUsers)) {
    foreach ($ldapUsers as $key => $ldapUser) {
        if (!empty($ldapUser['entityId'])) {
            if (!empty($ldapEntities)) {
                foreach ($ldapEntities as $ldapEntity) {
                    if ($ldapEntity['dn'] == $ldapUser['entityId']) {
                        $ldapUsers[$key]['entityId'] = $ldapEntity['entity_id'];
                        break;
                    }
                }
            } else {
                $ldapUsers[$key]['entityId'] = null;
            }
        }
    }
}

if ($synchronizeUsers) {
    synchronizeUsers($ldapUsers, $maarchUsers);
}
if ($synchronizeEntities) {
    synchronizeEntities($ldapEntities, $maarchEntities);
}

function initialize($customId)
{
    \SrcCore\models\DatabasePDO::reset();
    new \SrcCore\models\DatabasePDO(['customId' => $customId]);


    $path = 'modules/ldap/xml/config.xml';
    if (!empty($customId) && is_file("custom/{$customId}/{$path}")) {
        $path = "custom/{$customId}/{$path}";
    }
    if (!is_file($path)) {
        writeLog(['message' => "[ERROR] Ldap configuration file is missing"]);
        exit();
    }
    $xmlfile = simplexml_load_file($path);
    if (empty($xmlfile) || empty($xmlfile->config->ldap)) {
        writeLog(['message' => "[ERROR] No ldap configurations"]);
        exit();
    }
    if (empty((string)$xmlfile->userWS) || empty((string)$xmlfile->passwordWS)) {
        writeLog(['message' => "[ERROR] Rest user informations are missing"]);
        exit();
    }
    $GLOBALS['user'] = (string)$xmlfile->userWS;
    $GLOBALS['password'] = (string)$xmlfile->passwordWS;

    $path = 'config/config.json';
    if (!empty($customId)) {
        $path = "custom/{$customId}/config/config.json";
    }
    $file = file_get_contents($path);
    $file = json_decode($file, true);
    if (empty($file['config']['maarchUrl'])) {
        writeLog(['message' => "[ERROR] Tag maarchUrl is missing in config.json"]);
        exit();
    }
    $GLOBALS['maarchUrl'] = $file['config']['maarchUrl'];

    return $xmlfile;
}

function getUsersEntries($xmlfile)
{
    $domain = (string)$xmlfile->config->ldap->domain;
    $ssl = (string)$xmlfile->config->ldap->ssl;
    $uri = ($ssl == 'true' ? "LDAPS://{$domain}" : $domain);
    $prefix = (string)$xmlfile->config->ldap->prefix_login;
    $suffix = (string)$xmlfile->config->ldap->suffix_login;

    $login = (string)$xmlfile->config->ldap->login_admin;
    $password = (string)$xmlfile->config->ldap->pass;

    if (empty($xmlfile->mapping->user->user_id)) {
        return ['errors' => 'No mapping configurations (user_id)'];
    }
    $mapping = [
        'user_id'       => (string)$xmlfile->mapping->user->user_id,
        'firstname'     => (string)$xmlfile->mapping->user->firstname,
        'lastname'      => (string)$xmlfile->mapping->user->lastname,
        'phone'         => (string)$xmlfile->mapping->user->phone,
        'mail'          => (string)$xmlfile->mapping->user->mail,
        'entityId'      => (string)$xmlfile->mapping->user->user_entity ?? null
    ];
    $defaultEntity = (string)$xmlfile->mapping->user->defaultEntity ?? null;
    $arrayAttributes = ['entityId']; // other attributes are scalar: only first value is taken

    foreach ($xmlfile->filter->dn as $valueDN) {
        if ((string)$valueDN['type'] == 'users') {
            $dn = (string)$valueDN['id'];
            $filters = empty((string)$valueDN->user) ? null : (string)$valueDN->user;
        }
    }
    if (empty($dn)) {
        return ['errors' => 'No DN found'];
    }

    $ldap = @ldap_connect($uri);
    if ($ldap === false) {
        return ['errors' => 'Ldap connect failed : uri is maybe wrong'];
    }
    ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION, 3);
    ldap_set_option($ldap, LDAP_OPT_REFERRALS, 0);
    ldap_set_option($ldap, LDAP_OPT_NETWORK_TIMEOUT, 10);

    $ldapLogin = (!empty($prefix) ? $prefix . '\\' . $login : $login);
    $ldapLogin = (!empty($suffix) ? $ldapLogin . $suffix : $ldapLogin);

    $authenticated = @ldap_bind($ldap, $ldapLogin, $password);
    if (!$authenticated) {
        return ['errors' => 'Ldap bind failed : Authentication failed'];
    }

    $search = @ldap_search($ldap, $dn, ($filters ?? 'cn=*'));
    if ($search === false) {
        return ['errors' => 'Ldap search failed : ' . ldap_error($ldap)];
    }
    $entries = mb_convert_encoding(ldap_get_entries($ldap, $search), 'utf-8');

    $ldapEntries = [];
    foreach ($entries as $key => $entry) {
        if (!is_int($key)) {
            continue;
        }
        $user = [
            'defaultEntity' => $defaultEntity
        ];
        foreach ($mapping as $mcField => $ldapField) {
            if (empty($ldapField)) {
                continue;
            }
            if (isset($entry[$ldapField]) && is_array($entry[$ldapField])) {
                if (count($entry[$ldapField]) === 1 || (isset($entry[$ldapField]['count']) && $entry[$ldapField]['count'] === 1)) {
                    $user[$mcField] = $entry[$ldapField][0];
                } elseif (count($entry[$ldapField]) > 1) {
                    if (in_array($mcField, $arrayAttributes)) {
                        $user[$mcField] = $entry[$ldapField];
                    } else {
                        $user[$mcField] = $entry[$ldapField][0];
                    }
                } else {
                    $user[$mcField] = '';
                }
            } else {
                $user[$mcField] = '';
            }
        }
        $ldapEntries[$key] = $user;
    }
    ldap_unbind($ldap);

    return $ldapEntries;
}

function getEntitiesEntries($xmlfile)
{
    $domain = (string)$xmlfile->config->ldap->domain;
    $ssl = (string)$xmlfile->config->ldap->ssl;
    $uri = ($ssl == 'true' ? "LDAPS://{$domain}" : $domain);
    $prefix = (string)$xmlfile->config->ldap->prefix_login;
    $suffix = (string)$xmlfile->config->ldap->suffix_login;

    $login = (string)$xmlfile->config->ldap->login_admin;
    $password = (string)$xmlfile->config->ldap->pass;

    if (empty($xmlfile->mapping->entity->entity_id)) {
        return ['errors' => 'No mapping configurations (entity_id)'];
    }
    $mapping = [
        'entity_id'         => (string)$xmlfile->mapping->entity->entity_id,
        'entity_label'      => (string)$xmlfile->mapping->entity->entity_label,
        'parent_entity_id'  => (string)$xmlfile->mapping->entity->parent_entity_id
    ];

    foreach ($xmlfile->filter->dn as $valueDN) {
        if ((string)$valueDN['type'] == 'entities') {
            $dn = (string)$valueDN['id'];
            $filters = empty((string)$valueDN->user) ? null : (string)$valueDN->user;
        }
    }
    if (empty($dn)) {
        return ['errors' => 'No DN found'];
    }

    $ldap = @ldap_connect($uri);
    if ($ldap === false) {
        return ['errors' => 'Ldap connect failed : uri is maybe wrong'];
    }
    ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION, 3);
    ldap_set_option($ldap, LDAP_OPT_REFERRALS, 0);
    ldap_set_option($ldap, LDAP_OPT_NETWORK_TIMEOUT, 10);

    $ldapLogin = (!empty($prefix) ? $prefix . '\\' . $login : $login);
    $ldapLogin = (!empty($suffix) ? $ldapLogin . $suffix : $ldapLogin);

    $authenticated = @ldap_bind($ldap, $ldapLogin, $password);
    if (!$authenticated) {
        return ['errors' => 'Ldap bind failed : Authentication failed'];
    }

    $search = ldap_search($ldap, $dn, ($filters ?? 'cn=*'));
    $entries = mb_convert_encoding(ldap_get_entries($ldap, $search), 'utf-8');

    $ldapEntries = [];
    foreach ($entries as $key => $entry) {
        if (!is_int($key)) {
            continue;
        }
        $entity = ['dn' => $entry['dn']];

        foreach ($mapping as $mcField => $ldapField) {
            if (empty($ldapField)) {
                continue;
            }
            if (isset($entry[$ldapField]) && is_array($entry[$ldapField])) {
                if (count($entry[$ldapField]) === 1 || (isset($entry[$ldapField]['count']) && $entry[$ldapField]['count'] === 1)) {
                    $entity[$mcField] = $entry[$ldapField][0];
                } elseif (count($entry[$ldapField]) > 1) {
                    $entity[$mcField] = $entry[$ldapField];
                } else {
                    $entity[$mcField] = '';
                }
            } else {
                $entity[$mcField] = '';
            }
        }
        $ldapEntries[$key] = $entity;
    }
    ldap_unbind($ldap);

    if (!empty($mapping['parent_entity_id'])) {
        foreach ($ldapEntries as $key => $entry) {
            if (empty($entry['parent_entity_id'])) {
                unset($ldapEntries[$key]['parent_entity_id']);
                continue;
            }
            foreach ($ldapEntries as $parentKey => $parentEntry) {
                if ($entry['parent_entity_id'] === $parentEntry['dn']) {
                    $ldapEntries[$key]['parent_entity_id'] = $parentEntry['entity_id'];
                    break;
                }
            }
        }
    }

    return $ldapEntries;
}

function synchronizeUsers(array $ldapUsers, array $maarchUsers)
{
    $maarchUsersLogin = [];
    foreach ($maarchUsers as $maarchUser) {
        $maarchUsersLogin[$maarchUser['user_id']] = $maarchUser;
    }
    $ldapUsersLogin = [];
    foreach ($ldapUsers as $ldapUser) {
        $ldapUsersLogin[$ldapUser['user_id']] = $ldapUser;
    }

    foreach ($ldapUsers as $user) {
        $user['userId'] = $user['user_id'];
        if (!empty($maarchUsersLogin[$user['userId']]) && $maarchUsersLogin[$user['userId']]['status'] != 'DEL') {
            if ($maarchUsersLogin[$user['userId']]['status'] == 'SPD') {
                $curlResponse = \SrcCore\models\CurlModel::exec([
                    'url'           => rtrim($GLOBALS['maarchUrl'], '/') . '/rest/users/' . $maarchUsersLogin[$user['user_id']]['id'] . '/status',
                    'basicAuth'     => ['user' => $GLOBALS['user'], 'password' => $GLOBALS['password']],
                    'headers'       => ['content-type:application/json'],
                    'method'        => 'PUT',
                    'body'          => json_encode(['status' => 'OK'])
                ]);
                if ($curlResponse['code'] != 200) {
                    writeLog(['message' => "[ERROR] Update user [{$maarchUsersLogin[$user['user_id']]['user_id']}] status failed : {$curlResponse['response']['errors']}"]);
                    continue;
                }
            }
            if ($user['firstname'] != $maarchUsersLogin[$user['user_id']]['firstname']
                || $user['lastname'] != $maarchUsersLogin[$user['user_id']]['lastname']
                || $user['phone'] != $maarchUsersLogin[$user['user_id']]['phone']
                || $user['mail'] != $maarchUsersLogin[$user['user_id']]['mail']
            ) {
                $curlResponse = \SrcCore\models\CurlModel::exec([
                    'url'           => rtrim($GLOBALS['maarchUrl'], '/') . '/rest/users/' . $maarchUsersLogin[$user['user_id']]['id'],
                    'basicAuth'     => ['user' => $GLOBALS['user'], 'password' => $GLOBALS['password']],
                    'headers'       => ['content-type:application/json'],
                    'method'        => 'PUT',
                    'body'          => json_encode($user)
                ]);
                if ($curlResponse['code'] != 204) {
                    writeLog(['message' => "[ERROR] Update user [{$maarchUsersLogin[$user['user_id']]['user_id']}] failed : {$curlResponse['response']['errors']}"]);
                    continue;
                }
                userAddEntity($maarchUsersLogin[$user['user_id']]['id'], $user);
            }
        } else {
            $control = controlUser($user);
            if (!empty($control['errors'])) {
                writeLog(['message' => "[ERROR] Control create user [{$maarchUsersLogin[$user['user_id']]['user_id']}] failed : {$control['errors']}"]);
                continue;
            }

            $curlResponse = \SrcCore\models\CurlModel::exec([
                'url'           => rtrim($GLOBALS['maarchUrl'], '/') . '/rest/users',
                'basicAuth'     => ['user' => $GLOBALS['user'], 'password' => $GLOBALS['password']],
                'headers'       => ['content-type:application/json'],
                'method'        => 'POST',
                'body'          => json_encode($user)
            ]);
            if ($curlResponse['code'] != 200) {
                writeLog(['message' => "[ERROR] Create user [{$maarchUsersLogin[$user['user_id']]['user_id']}] failed : {$curlResponse['response']['errors']}"]);
                continue;
            }

            userAddEntity($curlResponse['response']['id'], $user);
        }
    }

    foreach ($maarchUsers as $user) {
        if (empty($ldapUsersLogin[$user['user_id']]) && $user['status'] != 'DEL' && $user['mode'] != 'rest') {
            $curlResponse = \SrcCore\models\CurlModel::exec([
                'url'           => rtrim($GLOBALS['maarchUrl'], '/') . '/rest/users/' . $user['id'] . '/suspend',
                'basicAuth'     => ['user' => $GLOBALS['user'], 'password' => $GLOBALS['password']],
                'headers'       => ['content-type:application/json'],
                'method'        => 'PUT'
            ]);
            if ($curlResponse['code'] != 204) {
                writeLog(['message' => "[ERROR] Suspend user [{$maarchUsersLogin[$user['user_id']]['user_id']}] failed  : {$curlResponse['response']['errors']}"]);
            }
        }
    }

    return true;
}

function synchronizeEntities(array $ldapEntities, array $maarchEntities)
{
    $maarchEntitiesId = [];
    foreach ($maarchEntities as $maarchEntity) {
        $maarchEntitiesId[$maarchEntity['entity_id']] = $maarchEntity;
    }
    $ldapEntitiesId = [];
    foreach ($ldapEntities as $ldapEntity) {
        $ldapEntitiesId[$ldapEntity['entity_id']] = $ldapEntity;
    }

    foreach ($ldapEntities as $entity) {
        if (!empty($maarchEntitiesId[$entity['entity_id']])) {
            if ($entity['entity_label'] != $maarchEntitiesId[$entity['entity_id']]['entity_label']
                || $entity['parent_entity_id'] != $maarchEntitiesId[$entity['entity_id']]['parent_entity_id']
            ) {
                $entity['short_label'] = $maarchEntitiesId[$entity['entity_id']]['short_label'];
                $entity['entity_type'] = $maarchEntitiesId[$entity['entity_id']]['entity_type'];
                $curlResponse = \SrcCore\models\CurlModel::exec([
                    'url'           => rtrim($GLOBALS['maarchUrl'], '/') . '/rest/entities/' . $entity['entity_id'],
                    'basicAuth'     => ['user' => $GLOBALS['user'], 'password' => $GLOBALS['password']],
                    'headers'       => ['content-type:application/json'],
                    'method'        => 'PUT',
                    'body'          => json_encode($entity)
                ]);
                if ($curlResponse['code'] != 200) {
                    writeLog(['message' => "[ERROR] Update entity failed : {$curlResponse['response']['errors']}"]);
                }
            }
        } else {
            $entity['short_label'] = $entity['entity_label'];
            $entity['entity_type'] = 'Service';
            $curlResponse = \SrcCore\models\CurlModel::exec([
                'url'           => rtrim($GLOBALS['maarchUrl'], '/') . '/rest/entities',
                'basicAuth'     => ['user' => $GLOBALS['user'], 'password' => $GLOBALS['password']],
                'headers'       => ['content-type:application/json'],
                'method'        => 'POST',
                'body'          => json_encode($entity)
            ]);
            if ($curlResponse['code'] != 200) {
                writeLog(['message' => "[ERROR] Create entity failed : {$curlResponse['response']['errors']}"]);
            }
        }
    }

    foreach ($maarchEntities as $entity) {
        if (empty($ldapEntitiesId[$entity['entity_id']])) {
            $curlResponse = \SrcCore\models\CurlModel::exec([
                'url'           => rtrim($GLOBALS['maarchUrl'], '/') . '/rest/entities/' . $entity['entity_id'],
                'basicAuth'     => ['user' => $GLOBALS['user'], 'password' => $GLOBALS['password']],
                'headers'       => ['content-type:application/json'],
                'method'        => 'DELETE'
            ]);
            if ($curlResponse['code'] != 200) {
                writeLog(['message' => "[ERROR] Delete entity failed : {$curlResponse['response']['errors']}"]);
            }
        }
    }

    return true;
}

function writeLog(array $args)
{
    if (strpos($args['message'], '[ERROR]') === 0) {
        \SrcCore\controllers\LogsController::add([
            'isTech'    => true,
            'moduleId'  => 'synchronizationLddap',
            'level'     => 'ERROR',
            'tableName' => '',
            'recordId'  => 'synchronizationLddap',
            'eventType' => 'synchronizationLddap',
            'eventId'   => $args['message']
        ]);
    } else {
        \SrcCore\controllers\LogsController::add([
            'isTech'    => true,
            'moduleId'  => 'synchronizationLddap',
            'level'     => 'INFO',
            'tableName' => '',
            'recordId'  => 'synchronizationLddap',
            'eventType' => 'synchronizationLddap',
            'eventId'   => $args['message']
        ]);
    }
}

function controlUser(array $user)
{
    if (!\Respect\Validation\Validator::stringType()->length(1, 128)->notEmpty()->validate($user['userId'] ?? null) || !preg_match("/^[\w.@-]*$/", $user['userId'])) {
        return ['errors' => 'Body userId is empty, not a string or not valid'];
    } elseif (!\Respect\Validation\Validator::stringType()->length(1, 255)->notEmpty()->validate($user['firstname'] ?? null)) {
        return ['errors' => 'Body firstname is empty or not a string'];
    } elseif (!\Respect\Validation\Validator::stringType()->length(1, 255)->notEmpty()->validate($user['lastname'] ?? null)) {
        return ['errors' => 'Body lastname is empty or not a string'];
    } elseif (!\Respect\Validation\Validator::stringType()->length(1, 255)->notEmpty()->validate($user['mail'] ?? null) || !filter_var($user['mail'], FILTER_VALIDATE_EMAIL)) {
        return ['errors' => 'Body mail is empty or not valid'];
    } elseif (!empty($user['phone']) && (!preg_match("/\+?((|\ |\.|\(|\)|\-)?(\d)*)*\d$/", $user['phone']) || !\Respect\Validation\Validator::stringType()->length(0, 32)->validate($user['phone'] ?? ''))) {
        return ['errors' => 'Body phone is not valid'];
    }

    return true;
}

function userAddEntity($userId, $user)
{
    $entityId = null;
    if (!empty($user['entityId'])) {
        $entityExists = \Entity\models\EntityModel::getByEntityId(['entityId' => $user['entityId'], 'select' => [1]]);
    }
    if (!empty($user['defaultEntity'])) {
        $defaultEntityExists = \Entity\models\EntityModel::getByEntityId(['entityId' => $user['defaultEntity'], 'select' => [1]]);
    }

    if(!empty($entityExists)) {
        $entityId = $user['entityId'];
    } elseif (!empty($defaultEntityExists)) {
        $entityId = $user['defaultEntity'];
    }

    if(!empty($entityId)) {
        $curlResponse = \SrcCore\models\CurlModel::exec([
            'url'           => rtrim($GLOBALS['maarchUrl'], '/') . '/rest/users/' . $userId . '/entities',
            'basicAuth'     => ['user' => $GLOBALS['user'], 'password' => $GLOBALS['password']],
            'headers'       => ['content-type:application/json'],
            'method'        => 'POST',
            'body'          => json_encode(['entityId' => $entityId])
        ]);
        if ($curlResponse['code'] != 200) {
            writeLog(['message' => "[ERROR] Add entity to user failed : {$curlResponse['response']['errors']}"]);
        }
    }
    else{
        writeLog(['message' => "[ERROR] Add entity to user failed : {Entity not found}"]);
    }
}
