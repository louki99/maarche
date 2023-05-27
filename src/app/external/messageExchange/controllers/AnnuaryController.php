<?php

/**
* Copyright Maarch since 2008 under licence GPLv3.
* See LICENCE.txt file at the root folder for more details.
* This file is part of Maarch software.
*
*/

/**
* @brief Annuary Controller
* @author dev@maarch.org
*/

namespace MessageExchange\controllers;

use Entity\models\EntityModel;
use Group\controllers\PrivilegeController;
use Parameter\models\ParameterModel;
use Slim\Http\Request;
use Slim\Http\Response;
use SrcCore\models\CoreConfigModel;

class AnnuaryController
{
    public static function updateEntityToOrganization(Request $request, Response $response, array $args)
    {
        if (!PrivilegeController::hasPrivilege(['privilegeId' => 'manage_entities', 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }

        $entity = EntityModel::getById(['id' => $args['id'], 'select' => ['entity_id', 'entity_label']]);
        if (empty($entity)) {
            return $response->withStatus(400)->withJson(['errors' => 'Entity does not exist']);
        }

        $siret = ParameterModel::getById(['id' => 'siret', 'select' => ['param_value_string']]);
        if (empty($siret['param_value_string'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Parameter siret does not exist', 'lang' => 'invalidToken']);
        }

        $entitySiret = "{$siret['param_value_string']}/{$entity['entity_id']}";

        EntityModel::update(['set' => ['business_id' => $entitySiret], 'where' => ['id = ?'], 'data' => [$args['id']]]);

        $control = AnnuaryController::getAnnuaries();
        if (empty($control['annuaries'])) {
            if (isset($control['errors'])) {
                return $response->withStatus(400)->withJson(['errors' => $control['errors']]);
            } else {
                return $response->withJson(['entitySiret' => $entitySiret]);
            }
        }
        $organization = $control['organization'];
        $communicationMeans = $control['communicationMeans'];
        $annuaries = $control['annuaries'];

        foreach ($annuaries as $annuary) {
            $ldap = @ldap_connect($annuary['uri']);
            if ($ldap === false) {
                continue;
            }
            ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION, 3);
            ldap_set_option($ldap, LDAP_OPT_REFERRALS, 0);
            ldap_set_option($ldap, LDAP_OPT_NETWORK_TIMEOUT, 5);

            $search = @ldap_search($ldap, "{$annuary['baseDN']}", "(ou={$organization})", ['dn']);
            if ($search === false) {
                continue;
            }

            $authenticated = @ldap_bind($ldap, $annuary['login'], $annuary['password']);
            if (!$authenticated) {
                return $response->withStatus(400)->withJson(['errors' => 'Ldap authentication failed : ' . ldap_error($ldap)]);
            }

            $entries = ldap_get_entries($ldap, $search);
            if ($entries['count'] == 0) {
                $info = [];
                $info['ou'] = $organization;
                $info['destinationIndicator'] = $siret['param_value_string'];
                $info['objectclass'] = ['organizationalUnit', 'top', 'labeledURIObject'];
                if (!empty($communicationMeans['url'])) {
                    $info['labeledURI'] = rtrim($communicationMeans['url'], '/');
                }
                if (!empty($communicationMeans['email'])) {
                    $info['postOfficeBox'] = $communicationMeans['email'];
                }

                $added = @ldap_add($ldap, "ou={$organization},{$annuary['baseDN']}", $info);
                if (!$added) {
                    return $response->withStatus(400)->withJson(['errors' => 'Ldap add failed : ' . ldap_error($ldap)]);
                }
            }

            $search = @ldap_search($ldap, "ou={$organization},{$annuary['baseDN']}", "(initials={$entity['entity_id']})", ['dn']);
            if ($search === false) {
                return $response->withStatus(400)->withJson(['errors' => 'Ldap search failed : ' . ldap_error($ldap)]);
            }
            $entries = ldap_get_entries($ldap, $search);

            if ($entries['count'] > 0) {
                $renamed = @ldap_rename($ldap, $entries[0]['dn'], "cn={$entity['entity_label']}", "ou={$organization},{$annuary['baseDN']}", true);
                if (!$renamed) {
                    return $response->withStatus(400)->withJson(['errors' => 'Ldap rename failed : ' . ldap_error($ldap)]);
                }

                $replaced = @ldap_mod_replace($ldap, "cn={$entity['entity_label']},ou={$organization},{$annuary['baseDN']}", ['sn' => $entity['entity_label']]);
                if (!$replaced) {
                    return $response->withStatus(400)->withJson(['errors' => 'Ldap replace failed : ' . ldap_error($ldap)]);
                }
            } else {
                $info = [];
                $info['cn'] = $entity['entity_label'];
                $info['sn'] = $entity['entity_label'];
                $info['initials'] = $entity['entity_id'];
                $info['objectclass'] = ['top', 'inetOrgPerson'];

                $added = @ldap_add($ldap, "cn={$entity['entity_label']},ou={$organization},{$annuary['baseDN']}", $info);
                if (!$added) {
                    return $response->withStatus(400)->withJson(['errors' => 'Ldap add failed : ' . ldap_error($ldap)]);
                }
            }

            break;
        }

        return $response->withJson(['entitySiret' => $entitySiret, 'synchronized' => !empty($authenticated)]);
    }

    public static function deleteEntityToOrganization(array $args)
    {
        $control = AnnuaryController::getAnnuaries();
        if (!isset($control['annuaries'])) {
            return $control;
        }
        $organization = $control['organization'];
        $annuaries = $control['annuaries'];

        foreach ($annuaries as $annuary) {
            $ldap = @ldap_connect($annuary['uri']);
            if ($ldap === false) {
                continue;
            }
            ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION, 3);
            ldap_set_option($ldap, LDAP_OPT_REFERRALS, 0);
            ldap_set_option($ldap, LDAP_OPT_NETWORK_TIMEOUT, 5);

            $search = @ldap_search($ldap, "ou={$organization},{$annuary['baseDN']}", "(initials={$args['entityId']})", ['dn']);
            if ($search === false) {
                continue;
            }
            $entries = ldap_get_entries($ldap, $search);
            if ($entries['count'] == 0) {
                return ['success' => 'Entity does not exist in annuary'];
            }

            $authenticated = @ldap_bind($ldap, $annuary['login'], $annuary['password']);
            if (!$authenticated) {
                return ['errors' => 'Ldap authentication failed : ' . ldap_error($ldap)];
            }
            $deleted = @ldap_delete($ldap, $entries[0]['dn']);
            if (!$deleted) {
                return ['errors' => 'Ldap delete failed : ' . ldap_error($ldap)];
            }

            break;
        }

        return ['deleted' => !empty($deleted)];
    }

    public static function getAnnuaries()
    {
        $loadedXml = CoreConfigModel::getXmlLoaded(['path' => 'config/m2m_config.xml']);

        if (!$loadedXml) {
            return ['success' => _M2M_ANNUARY_IS_NOT_SET];
        }
        if (empty($loadedXml->annuaries) || $loadedXml->annuaries->enabled == 'false') {
            return ['success' => _NO_M2M_ANNUARY_AVAILABLE];
        }
        $organization = (string)$loadedXml->annuaries->organization;
        if (empty($organization)) {
            return ['errors' => 'Tag organization is empty'];
        }
        $annuaries = [];
        foreach ($loadedXml->annuaries->annuary as $annuary) {
            $uri = ((string)$annuary->ssl === 'true' ? "LDAPS://{$annuary->uri}" : (string)$annuary->uri);

            $annuaries[] = [
                'uri'       => $uri,
                'baseDN'    => (string)$annuary->baseDN,
                'login'     => (string)$annuary->login,
                'password'  => (string)$annuary->password,
                'ssl'       => (string)$annuary->ssl,
            ];
        }

        $rawCommunicationMeans = (string)$loadedXml->m2m_communication;
        if (empty($rawCommunicationMeans)) {
            return ['errors' => 'Tag m2m_communication is empty'];
        }
        $communicationMeans = [];
        $rawCommunicationMeans = explode(',', $rawCommunicationMeans);
        foreach ($rawCommunicationMeans as $value) {
            if (filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $communicationMeans['email'] = $value;
            } elseif (filter_var($value, FILTER_VALIDATE_URL)) {
                $communicationMeans['url'] = $value;
            }
        }
        if (empty($communicationMeans)) {
            return ['errors' => 'No communication means found'];
        }

        return ['annuaries' => $annuaries, 'organization' => $organization, 'communicationMeans' => $communicationMeans];
    }

    public static function addContact(array $args)
    {
        $control = AnnuaryController::getAnnuaries();
        if (empty($control['annuaries'])) {
            return ['errors' => _M2M_ANNUARY_IS_NOT_SET];
        }

        $annuaries          = $control['annuaries'];
        $organization       = $args['ouName'];
        $communicationMeans = $args['communicationValue'];
        $serviceName        = $args['serviceName'];

        $m2mId              = $args['m2mId'];
        $businessId         = explode("/", $m2mId);
        $siret              = $businessId[0];
        $entityId           = $businessId[1];

        foreach ($annuaries as $annuary) {
            $ldap = @ldap_connect($annuary['uri']);
            if ($ldap === false) {
                continue;
            }
            ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION, 3);
            ldap_set_option($ldap, LDAP_OPT_REFERRALS, 0);
            ldap_set_option($ldap, LDAP_OPT_NETWORK_TIMEOUT, 5);

            $authenticated = @ldap_bind($ldap, $annuary['login'], $annuary['password']);
            if (!$authenticated) {
                return ['errors' => _M2M_LDAP_AUTHENTICATION_FAILED . ' : ' . ldap_error($ldap)];
            }

            $search  = @ldap_search($ldap, "{$annuary['baseDN']}", "(destinationIndicator={$siret})", ['ou']);
            $entries = ldap_get_entries($ldap, $search);

            if ($entries['count'] > 0) {
                $organization = $entries[0]['ou'][0];
                $search       = @ldap_search($ldap, "ou={$entries[0]['ou'][0]},{$annuary['baseDN']}", "(initials={$entityId})", ['ou', 'entryUUID']);
                $entries      = ldap_get_entries($ldap, $search);
                if ($entries['count'] > 0) {
                    return ['entryUUID' => $entries[0]['entryuuid'][0]];
                }
            } else {
                $info = [];
                $info['ou'] = $organization;
                $info['destinationIndicator'] = $siret;
                if (filter_var($communicationMeans, FILTER_VALIDATE_EMAIL)) {
                    $info['postOfficeBox'] = $communicationMeans;
                } else {
                    $info['labeledURI'] = $communicationMeans;
                }

                $info['objectclass'] = ['organizationalUnit', 'top', 'labeledURIObject'];

                $added = @ldap_add($ldap, "ou={$organization},{$annuary['baseDN']}", $info);
                if (!$added) {
                    return ['errors' => _M2M_LDAP_ADD_FAILED . ' : ' . ldap_error($ldap)];
                }
            }
            $info = [];
            $info['cn'] = $serviceName;
            $info['sn'] = $serviceName;
            $info['initials'] = $entityId;
            $info['objectclass'] = ['top', 'inetOrgPerson'];

            $added = @ldap_add($ldap, "cn={$serviceName},ou={$organization},{$annuary['baseDN']}", $info);
            if (!$added) {
                return ['errors' => _M2M_LDAP_ADD_FAILED . ' : ' . ldap_error($ldap)];
            }

            $search  = @ldap_search($ldap, "ou={$organization},{$annuary['baseDN']}", "(initials={$entityId})", ['entryUUID']);
            $entries = ldap_get_entries($ldap, $search);
            return ['entryUUID' => $entries[0]['entryuuid'][0]];
        }

        return ['errors' => _NO_M2M_ANNUARY_AVAILABLE];
    }

    public static function isSiretNumber(array $args)
    {
        if (strlen($args['siret']) != 14) {
            return false;
        }
        if (!is_numeric($args['siret'])) {
            return false;
        }

        // on prend chaque chiffre un par un
        // si son index (position dans la chaîne en commence à 0 au premier caractère) est pair
        // on double sa valeur et si cette dernière est supérieure à 9, on lui retranche 9
        // on ajoute cette valeur à la somme totale

        $sum = 0;
        for ($index = 0; $index < 14; $index ++) {
            $number = (int) $args['siret'][$index];
            if (($index % 2) == 0) {
                if (($number *= 2) > 9) {
                    $number -= 9;
                }
            }
            $sum += $number;
        }

        // le numéro est valide si la somme des chiffres est multiple de 10
        if (($sum % 10) != 0) {
            return false;
        } else {
            return true;
        }
    }

    public static function getByUui(array $args)
    {
        $control = AnnuaryController::getAnnuaries();
        if (!isset($control['annuaries'])) {
            return $control;
        }
        $annuaries = $control['annuaries'];

        foreach ($annuaries as $annuary) {
            $ldap = @ldap_connect($annuary['uri']);
            if ($ldap === false) {
                continue;
            }
            ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION, 3);
            ldap_set_option($ldap, LDAP_OPT_REFERRALS, 0);
            ldap_set_option($ldap, LDAP_OPT_NETWORK_TIMEOUT, 10);

            $search = @ldap_search($ldap, "{$annuary['baseDN']}", "(entryUUID={$args['contactUuid']})", ['dn', 'initials', 'entryDN']);
            if ($search === false) {
                continue;
            }
            $entries = ldap_get_entries($ldap, $search);
            if ($entries['count'] > 0) {
                $departmentDestinationIndicator = $entries[0]['initials'][0];
                $entryDn  = $entries[0]['entrydn'][0];
                $pathDn   = explode(',', $entryDn);
                $parentOu = $pathDn[1];
                $search   = @ldap_search($ldap, "{$annuary['baseDN']}", "({$parentOu})", ['dn', 'destinationIndicator', 'postOfficeBox', 'labeledURI']);
                $entries  = ldap_get_entries($ldap, $search);

                return ['mail' => $entries[0]['postofficebox'][0], 'url' => $entries[0]['labeleduri'][0], 'businessId' => $entries[0]['destinationindicator'][0] . '/' . $departmentDestinationIndicator];
            }

            break;
        }

        return ['errors' => 'No annuary found or UUID does not exist'];
    }
}
