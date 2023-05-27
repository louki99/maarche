<?php

/**
 * Copyright Maarch since 2008 under licence GPLv3.
 * See LICENCE.txt file at the root folder for more details.
 * This file is part of Maarch software.
 *
 */

/**
 * @brief Localeo Script
 * @author dev@maarch.org
 */

chdir('../../..');

require 'vendor/autoload.php';

LocaleoScript::sendContact($argv);
LocaleoScript::updateContact($argv);
LocaleoScript::sendResource($argv);
LocaleoScript::sendAttachment($argv);
LocaleoScript::closeResource($argv);

class LocaleoScript
{
    const MAPPING_CONTACT = [
        'civility'          => 'civility',
        'company'           => 'company',
        'firstname'         => 'firstname',
        'familyName'        => 'lastname',
        'email'             => 'email',
        'phone'             => 'phone',
        'num'               => 'address_number',
        'street'            => 'address_street',
        'ZC'                => 'address_postcode',
        'additionalAddress' => 'address_additional1',
        'city'              => 'address_town',
        'country'           => 'address_country',
        'externalId'        => 'id'
    ];

    public static function sendContact(array $args)
    {
        $customId = null;
        if (!empty($args[1]) && $args[1] == '--customId' && !empty($args[2])) {
            $customId = $args[2];
        }

        $configuration = LocaleoScript::getXmlLoaded(['path' => 'bin/external/localeo/config.xml', 'customId' => $customId]);
        if (empty($configuration)) {
            self::writeLog(['message' => "[ERROR] [SEND_CONTACT] File bin/external/localeo/config.xml does not exist"]);
            exit();
        } elseif (empty($configuration->apiKey) || empty($configuration->appName) || empty($configuration->sendContact)) {
            self::writeLog(['message' => "[ERROR] [SEND_CONTACT] File bin/external/localeo/config.xml is not filled enough"]);
            return;
        }
        if ((string)$configuration->sendContact->enabled == 'false') {
            return;
        }

        $apiKey = (string)$configuration->apiKey;
        $appName = (string)$configuration->appName;
        $url = (string)$configuration->sendContact->url;
        if (empty($url)) {
            self::writeLog(['message' => "[ERROR] [SEND_CONTACT] File bin/external/localeo/config.xml url is empty"]);
            return;
        }

        $dataToMerge = [];
        if (!empty($configuration->sendContact->data)) {
            foreach ($configuration->sendContact->data as $value) {
                $dataToMerge[(string)$value->key] = (string)$value->value;
            }
        }

        \SrcCore\models\DatabasePDO::reset();
        new \SrcCore\models\DatabasePDO(['customId' => $customId]);

        $contacts = \Contact\models\ContactModel::get([
            'select'    => ['*'],
            'where'     => ['enabled = ?', "external_id->>'localeoId' is null"],
            'data'      => [true]
        ]);

        foreach ($contacts as $contact) {
            $body = [];
            foreach (self::MAPPING_CONTACT as $key => $value) {
                $body[$key] = $contact[$value] ?? '';
            }
            $body = array_merge($body, $dataToMerge);

            $response = \SrcCore\models\CurlModel::exec([
                'url'       => $url,
                'method'    => 'NO-METHOD',
                'headers'   => ["Api-Key: {$apiKey}", "appName: {$appName}"],
                'body'      => ['citoyen' => json_encode($body)],
                'noLogs'    => true
            ]);

            if (!empty($response['errors'])) {
                self::writeLog(['message' => "[ERROR] [SEND_CONTACT] Contact {$contact['id']} : curl call failed"]);
                self::writeLog(['message' => $response['errors']]);
                continue;
            } elseif (empty($response['response']['id'])) {
                self::writeLog(['message' => "[ERROR] [SEND_CONTACT] Contact {$contact['id']} : id is missing"]);
                self::writeLog(['message' => json_encode($response['response'])]);
                continue;
            }

            $externalId = json_decode($contact['external_id'], true);
            $externalId['localeoId'] = $response['response']['id'];
            \Contact\models\ContactModel::update(['set' => ['external_id' => json_encode($externalId)], 'where' => ['id = ?'], 'data' => [$contact['id']]]);

            self::writeLog(['message' => "[SUCCESS] [SEND_CONTACT] Contact {$contact['id']} : successfully sent to localeo"]);
        }
    }

    public static function updateContact(array $args)
    {
        $customId = null;
        if (!empty($args[1]) && $args[1] == '--customId' && !empty($args[2])) {
            $customId = $args[2];
        }

        $configuration = LocaleoScript::getXmlLoaded(['path' => 'bin/external/localeo/config.xml', 'customId' => $customId]);
        if (empty($configuration)) {
            self::writeLog(['message' => "[ERROR] [UPDATE_CONTACT] File bin/external/localeo/config.xml does not exist"]);
            exit();
        } elseif (empty($configuration->apiKey) || empty($configuration->appName) || empty($configuration->updateContact)) {
            self::writeLog(['message' => "[ERROR] [UPDATE_CONTACT] File bin/external/localeo/config.xml is not filled enough"]);
            return;
        }
        if ((string)$configuration->updateContact->enabled == 'false') {
            return;
        }

        $apiKey = (string)$configuration->apiKey;
        $appName = (string)$configuration->appName;
        $url = (string)$configuration->updateContact->url;
        if (empty($url)) {
            self::writeLog(['message' => "[ERROR] [UPDATE_CONTACT] File bin/external/localeo/config.xml url is empty"]);
            return;
        }

        if (!file_exists('bin/external/localeo/updateContact.timestamp')) {
            $file = fopen('bin/external/localeo/updateContact.timestamp', 'w');
            fwrite($file, time());
            fclose($file);
            return;
        }

        $dataToMerge = [];
        if (!empty($configuration->updateContact->data)) {
            foreach ($configuration->updateContact->data as $value) {
                $dataToMerge[(string)$value->key] = (string)$value->value;
            }
        }

        \SrcCore\models\DatabasePDO::reset();
        new \SrcCore\models\DatabasePDO(['customId' => $customId]);

        $time = file_get_contents('bin/external/localeo/updateContact.timestamp');

        $contacts = \Contact\models\ContactModel::get([
            'select'    => ['*'],
            'where'     => ['enabled = ?', "external_id->>'localeoId' is not null", 'modification_date > ?'],
            'data'      => [true, date('Y-m-d H:i:s', $time)]
        ]);

        $file = fopen('bin/external/localeo/updateContact.timestamp', 'w');
        fwrite($file, time());
        fclose($file);

        foreach ($contacts as $contact) {
            $externalId = json_decode($contact['external_id'], true);

            $body = [];
            foreach (self::MAPPING_CONTACT as $key => $value) {
                $body[$key] = $contact[$value] ?? '';
            }
            $body['id'] = $externalId['localeoId'];
            $body = array_merge($body, $dataToMerge);

            $response = \SrcCore\models\CurlModel::exec([
                'url'       => $url,
                'method'    => 'NO-METHOD',
                'headers'   => ["Api-Key: {$apiKey}", "appName: {$appName}"],
                'body'      => ['citoyen' => json_encode($body)],
                'noLogs'    => true
            ]);

            if (!empty($response['errors'])) {
                self::writeLog(['message' => "[ERROR] [UPDATE_CONTACT] Contact {$contact['id']} : curl call failed"]);
                self::writeLog(['message' => $response['errors']]);
                continue;
            } elseif (empty($response['response']['id'])) {
                self::writeLog(['message' => "[ERROR] [UPDATE_CONTACT] Contact {$contact['id']} : id is missing"]);
                self::writeLog(['message' => json_encode($response['response'])]);
                continue;
            }

            self::writeLog(['message' => "[SUCCESS] [UPDATE_CONTACT] Contact {$contact['id']} : successfully sent to localeo"]);
        }
    }

    public static function sendResource(array $args)
    {
        $customId = null;
        if (!empty($args[1]) && $args[1] == '--customId' && !empty($args[2])) {
            $customId = $args[2];
        }

        $configuration = LocaleoScript::getXmlLoaded(['path' => 'bin/external/localeo/config.xml', 'customId' => $customId]);
        if (empty($configuration)) {
            self::writeLog(['message' => "[ERROR] [SEND_RESOURCE] File bin/external/localeo/config.xml does not exist"]);
            exit();
        } elseif (empty($configuration->apiKey) || empty($configuration->appName) || empty($configuration->sendResource)) {
            self::writeLog(['message' => "[ERROR] [SEND_RESOURCE] File bin/external/localeo/config.xml is not filled enough"]);
            return;
        }
        if ((string)$configuration->sendResource->enabled == 'false') {
            return;
        }

        $apiKey = (string)$configuration->apiKey;
        $appName = (string)$configuration->appName;
        $url = (string)$configuration->sendResource->url;
        if (empty($url)) {
            self::writeLog(['message' => "[ERROR] [SEND_RESOURCE] File bin/external/localeo/config.xml url is empty"]);
            return;
        }

        $dataToMerge = [];
        if (!empty($configuration->sendResource->data)) {
            foreach ($configuration->sendResource->data as $value) {
                $dataToMerge[(string)$value->key] = (string)$value->value;
            }
        }

        \SrcCore\models\DatabasePDO::reset();
        new \SrcCore\models\DatabasePDO(['customId' => $customId]);

        $resources = \Resource\models\ResModel::get([
            'select'    => ['res_id', 'subject', 'format', 'path', 'filename', 'docserver_id', 'external_id'],
            'where'     => ["external_id->>'localeoId' is null", 'category_id = ?'],
            'data'      => ['incoming']
        ]);

        foreach ($resources as $resource) {
            $contact = \Resource\models\ResourceContactModel::get([
                'select'    => ['item_id'],
                'where'     => ['res_id = ?', 'type = ?', 'mode = ?'],
                'data'      => [$resource['res_id'], 'contact', 'sender'],
                'limit'     => 1
            ]);
            if (empty($contact[0])) {
                self::writeLog(['message' => "[INFO] [SEND_RESOURCE] Resource {$resource['res_id']} : Has no sender"]);
                continue;
            }
            $contact = \Contact\models\ContactModel::getById(['id' => $contact[0]['item_id'], 'select' => ['external_id', 'id']]);
            $contactExternalId = json_decode($contact['external_id'], true);
            if (empty($contactExternalId['localeoId'])) {
                self::writeLog(['message' => "[WARNING] [SEND_RESOURCE] Resource {$resource['res_id']} : Sender is not linked to localeo"]);
                continue;
            }

            $body = [];

            if (!empty($resource['filename'])) {
                $docserver = \Docserver\models\DocserverModel::getByDocserverId(['docserverId' => $resource['docserver_id'], 'select' => ['path_template']]);
                $path = $docserver['path_template'] . str_replace('#', '/', $resource['path']) . $resource['filename'];
                if (!is_file($path)) {
                    self::writeLog(['message' => "[ERROR] [SEND_RESOURCE] Resource {$resource['res_id']} : File is missing"]);
                    continue;
                }

                $body['filename'] = \SrcCore\models\CurlModel::makeCurlFile(['path' => $path]);
            }

            $requete = [
                'subject'       => $resource['subject'],
                'externalId'    => $resource['res_id']
            ];
            $requete = array_merge($requete, $dataToMerge);

            $citoyen = ['id' => $contactExternalId['localeoId'], 'externalId' => $contact['id']];

            $body['requete'] = json_encode($requete);
            $body['citoyen'] = json_encode($citoyen);

            $response = \SrcCore\models\CurlModel::exec([
                'url'       => $url,
                'method'    => 'NO-METHOD',
                'headers'   => ["Api-Key: {$apiKey}", "appName: {$appName}"],
                'body'      => $body,
                'noLogs'    => true
            ]);

            if (!empty($response['errors'])) {
                self::writeLog(['message' => "[ERROR] [SEND_RESOURCE] Resource {$resource['res_id']} : curl call failed"]);
                self::writeLog(['message' => $response['errors']]);
                continue;
            } elseif (empty($response['response']['id'])) {
                self::writeLog(['message' => "[ERROR] [SEND_RESOURCE] Resource {$resource['res_id']} : id is missing"]);
                self::writeLog(['message' => json_encode($response['response'])]);
                continue;
            }

            $externalId = json_decode($resource['external_id'], true);
            $externalId['localeoId'] = $response['response']['id'];
            \Resource\models\ResModel::update(['set' => ['external_id' => json_encode($externalId)], 'where' => ['res_id = ?'], 'data' => [$resource['res_id']]]);

            self::writeLog(['message' => "[SUCCESS] [SEND_RESOURCE] Resource {$resource['res_id']} : successfully sent to localeo"]);
        }
    }

    public static function sendAttachment(array $args)
    {
        $customId = null;
        if (!empty($args[1]) && $args[1] == '--customId' && !empty($args[2])) {
            $customId = $args[2];
        }

        $configuration = LocaleoScript::getXmlLoaded(['path' => 'bin/external/localeo/config.xml', 'customId' => $customId]);
        if (empty($configuration)) {
            self::writeLog(['message' => "[ERROR] [SEND_ATTACHMENT] File bin/external/localeo/config.xml does not exist"]);
            exit();
        } elseif (empty($configuration->apiKey) || empty($configuration->appName) || empty($configuration->sendAttachment)) {
            self::writeLog(['message' => "[ERROR] [SEND_ATTACHMENT] File bin/external/localeo/config.xml is not filled enough"]);
            return;
        }
        if ((string)$configuration->sendAttachment->enabled == 'false') {
            return;
        }

        $apiKey = (string)$configuration->apiKey;
        $appName = (string)$configuration->appName;
        $url = (string)$configuration->sendAttachment->url;
        if (empty($url)) {
            self::writeLog(['message' => "[ERROR] [SEND_ATTACHMENT] File bin/external/localeo/config.xml url is empty"]);
            return;
        }

        $dataToMerge = [];
        if (!empty($configuration->sendAttachment->data)) {
            foreach ($configuration->sendAttachment->data as $value) {
                $dataToMerge[(string)$value->key] = (string)$value->value;
            }
        }

        \SrcCore\models\DatabasePDO::reset();
        new \SrcCore\models\DatabasePDO(['customId' => $customId]);

        $attachments = \SrcCore\models\DatabaseModel::select([
            'select'    => [
                'res_attachments.res_id', 'res_attachments.res_id_master', 'res_attachments.title', 'res_attachments.format', 'res_attachments.path', 'res_attachments.filename',
                'res_attachments.docserver_id', 'res_attachments.external_id', "res_letterbox.external_id->>'localeoId' as \"localeoId\""
            ],
            'table'     => ['res_attachments, res_letterbox'],
            'where'     => [
                'res_attachments.res_id_master = res_letterbox.res_id', "res_letterbox.external_id->>'localeoId' is not null",
                "res_attachments.external_id->>'localeoId' is null", 'res_attachments.status not in (?)'
            ],
            'data'      => [['DEL', 'OBS']]
        ]);

        foreach ($attachments as $attachment) {
            $contact = \Resource\models\ResourceContactModel::get([
                'select'    => ['item_id'],
                'where'     => ['res_id = ?', 'type = ?', 'mode = ?'],
                'data'      => [$attachment['res_id_master'], 'contact', 'sender'],
                'limit'     => 1
            ]);
            if (empty($contact[0])) {
                self::writeLog(['message' => "[INFO] [SEND_ATTACHMENT] Resource {$attachment['res_id_master']} : Has no sender"]);
                continue;
            }
            $contact = \Contact\models\ContactModel::getById(['id' => $contact[0]['item_id'], 'select' => ['external_id', 'id']]);
            $contactExternalId = json_decode($contact['external_id'], true);
            if (empty($contactExternalId['localeoId'])) {
                self::writeLog(['message' => "[WARNING] [SEND_ATTACHMENT] Resource {$attachment['res_id_master']} : Sender is not linked to localeo"]);
                continue;
            }

            $body = [];

            $docserver = \Docserver\models\DocserverModel::getByDocserverId(['docserverId' => $attachment['docserver_id'], 'select' => ['path_template']]);
            $path = $docserver['path_template'] . str_replace('#', '/', $attachment['path']) . $attachment['filename'];
            if (!is_file($path)) {
                self::writeLog(['message' => "[ERROR] [SEND_ATTACHMENT] Attachment {$attachment['res_id']} : File is missing"]);
                continue;
            }
            $body['filename'] = \SrcCore\models\CurlModel::makeCurlFile(['path' => $path]);

            $requete = [
                'subject'       => $attachment['title'],
                'externalId'    => $attachment['res_id'],
                'parentQueryId' => $attachment['localeoId'],
            ];
            $requete = array_merge($requete, $dataToMerge);

            $citoyen = ['id' => $contactExternalId['localeoId'], 'externalId' => $contact['id']];

            $body['requete'] = json_encode($requete);
            $body['citoyen'] = json_encode($citoyen);

            $response = \SrcCore\models\CurlModel::exec([
                'url'       => $url,
                'method'    => 'NO-METHOD',
                'headers'   => ["Api-Key: {$apiKey}", "appName: {$appName}"],
                'body'      => $body,
                'noLogs'    => true
            ]);

            if (!empty($response['errors'])) {
                self::writeLog(['message' => "[ERROR] [SEND_ATTACHMENT] Attachment {$attachment['res_id']} : curl call failed"]);
                self::writeLog(['message' => $response['errors']]);
                continue;
            } elseif (empty($response['response']['id'])) {
                self::writeLog(['message' => "[ERROR] [SEND_ATTACHMENT] Attachment {$attachment['res_id']} : id is missing"]);
                self::writeLog(['message' => json_encode($response['response'])]);
                continue;
            }

            $externalId = json_decode($attachment['external_id'], true);
            $externalId['localeoId'] = $response['response']['id'];
            \Attachment\models\AttachmentModel::update(['set' => ['external_id' => json_encode($externalId)], 'where' => ['res_id = ?'], 'data' => [$attachment['res_id']]]);

            self::writeLog(['message' => "[SUCCESS] [SEND_ATTACHMENT] Attachment {$attachment['res_id']} : successfully sent to localeo"]);
        }
    }

    public static function closeResource(array $args)
    {
        $customId = null;
        if (!empty($args[1]) && $args[1] == '--customId' && !empty($args[2])) {
            $customId = $args[2];
        }

        $configuration = LocaleoScript::getXmlLoaded(['path' => 'bin/external/localeo/config.xml', 'customId' => $customId]);
        if (empty($configuration)) {
            self::writeLog(['message' => "[ERROR] [CLOSE_RESOURCE] File bin/external/localeo/config.xml does not exist"]);
            exit();
        } elseif (empty($configuration->apiKey) || empty($configuration->appName) || empty($configuration->closeResource)) {
            self::writeLog(['message' => "[ERROR] [CLOSE_RESOURCE] File bin/external/localeo/config.xml is not filled enough"]);
            return;
        }
        if ((string)$configuration->closeResource->enabled == 'false') {
            return;
        }

        $apiKey = (string)$configuration->apiKey;
        $appName = (string)$configuration->appName;
        $url = (string)$configuration->closeResource->url;
        $status = (string)$configuration->closeResource->status;
        if (empty($url) || empty($status)) {
            self::writeLog(['message' => "[ERROR] [CLOSE_RESOURCE] File bin/external/localeo/config.xml url or status is empty"]);
            return;
        }

        if (!file_exists('bin/external/localeo/closeResource.timestamp')) {
            $file = fopen('bin/external/localeo/closeResource.timestamp', 'w');
            fwrite($file, time());
            fclose($file);
            return;
        }

        $dataToMerge = [];
        if (!empty($configuration->closeResource->data)) {
            foreach ($configuration->closeResource->data as $value) {
                $dataToMerge[(string)$value->key] = (string)$value->value;
            }
        }

        \SrcCore\models\DatabasePDO::reset();
        new \SrcCore\models\DatabasePDO(['customId' => $customId]);

        $time = file_get_contents('bin/external/localeo/closeResource.timestamp');

        $resources = \Resource\models\ResModel::get([
            'select'    => ['res_id', "external_id->>'localeoId' as \"localeoId\""],
            'where'     => ["external_id->>'localeoId' is not null", 'status = ?', 'closing_date is not null', 'closing_date > ?'],
            'data'      => [$status, date('Y-m-d H:i:s', $time)]
        ]);

        $file = fopen('bin/external/localeo/closeResource.timestamp', 'w');
        fwrite($file, time());
        fclose($file);

        foreach ($resources as $resource) {
            $body = [];

            $requete = [
                'id'    => $resource['localeoId']
            ];
            $requete = array_merge($requete, $dataToMerge);

            $body['requete'] = json_encode($requete);

            $response = \SrcCore\models\CurlModel::exec([
                'url'       => $url,
                'method'    => 'NO-METHOD',
                'headers'   => ["Api-Key: {$apiKey}", "appName: {$appName}"],
                'body'      => $body,
                'noLogs'    => true
            ]);

            if (!empty($response['errors'])) {
                self::writeLog(['message' => "[ERROR] [CLOSE_RESOURCE] Resource {$resource['res_id']} : curl call failed"]);
                self::writeLog(['message' => $response['errors']]);
                continue;
            } elseif (empty($response['response']['requete']['id']) || empty($response['response']['statut']['id'])) {
                self::writeLog(['message' => "[ERROR] [CLOSE_RESOURCE] Resource {$resource['res_id']} : bad response"]);
                self::writeLog(['message' => json_encode($response['response'])]);
                continue;
            }

            self::writeLog(['message' => "[SUCCESS] [CLOSE_RESOURCE] Resource {$resource['res_id']} : successfully closed in localeo"]);
        }
    }

    public static function getXmlLoaded(array $args)
    {
        if (!empty($args['customId']) && file_exists("custom/{$args['customId']}/{$args['path']}")) {
            $path = "custom/{$args['customId']}/{$args['path']}";
        }
        if (empty($path)) {
            $path = $args['path'];
        }

        $xmlfile = null;
        if (file_exists($path)) {
            $xmlfile = simplexml_load_file($path);
        }

        return $xmlfile;
    }

    public static function writeLog(array $args)
    {
        $file = fopen('bin/external/localeo/localeoScript.log', 'a');
        fwrite($file, '[' . date('Y-m-d H:i:s') . '] ' . $args['message'] . PHP_EOL);
        fclose($file);

        if (strpos($args['message'], '[ERROR]') === 0) {
            \SrcCore\controllers\LogsController::add([
                'isTech'    => true,
                'moduleId'  => 'localeo',
                'level'     => 'ERROR',
                'tableName' => '',
                'recordId'  => 'Localeo',
                'eventType' => 'Localeo',
                'eventId'   => $args['message']
            ]);
        } else {
            \SrcCore\controllers\LogsController::add([
                'isTech'    => true,
                'moduleId'  => 'localeo',
                'level'     => 'INFO',
                'tableName' => '',
                'recordId'  => 'Localeo',
                'eventType' => 'Localeo',
                'eventId'   => $args['message']
            ]);
        }

        \History\models\BatchHistoryModel::create(['info' => $args['message'], 'module_name' => 'localeo']);
    }
}
