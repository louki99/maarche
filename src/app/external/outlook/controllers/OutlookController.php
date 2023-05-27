<?php

/**
 * Copyright Maarch since 2008 under licence GPLv3.
 * See LICENCE.txt file at the root folder for more details.
 * This file is part of Maarch software.
 *
 */

/**
 * @brief   Outlook Controller
 * @author  dev@maarch.org
 */

namespace Outlook\controllers;

use Attachment\models\AttachmentTypeModel;
use Configuration\models\ConfigurationModel;
use Doctype\models\DoctypeModel;
use Group\controllers\PrivilegeController;
use IndexingModel\models\IndexingModelModel;
use Respect\Validation\Validator;
use Slim\Psr7\Request;
use SrcCore\http\Response;
use SrcCore\controllers\LanguageController;
use SrcCore\models\CoreConfigModel;
use SrcCore\models\PasswordModel;
use Status\models\StatusModel;
use User\models\UserModel;

class OutlookController
{
    public function generateManifest(Request $request, Response $response)
    {
        $config = CoreConfigModel::getJsonLoaded(['path' => 'config/config.json']);
        $appName = $config['config']['applicationName'];
        $maarchUrl = $config['config']['maarchUrl'];

        if (strpos($maarchUrl, 'https://') === false) {
            return $response->withStatus(400)->withJson(['errors' => 'You cannot use the Outlook plugin because maarchUrl is not using https', 'lang' => 'addinOutlookUnavailable']);
        }

        $maarchUrl = str_replace('//', '/', $maarchUrl);
        $maarchUrl = str_replace('https:/', 'https://', $maarchUrl);

        $path = CoreConfigModel::getConfigPath();
        $hashedPath = md5($path);

        $uuid = substr_replace($hashedPath, '-', 8, 0);
        $uuid = substr_replace($uuid, '-', 13, 0);
        $uuid = substr_replace($uuid, '-', 18, 0);
        $uuid = substr_replace($uuid, '-', 23, 0);

        $appDomain = str_replace(CoreConfigModel::getCustomId(), '', $maarchUrl);
        $appDomain = str_replace('//', '/', $appDomain);


        $data = [
            'config.applicationName' => $appName,
            'config.instanceUrl'     => $maarchUrl,
            'config.applicationUrl'  => $appDomain,
            'config.applicationUuid' => $uuid
        ];

        $language = LanguageController::getLanguage(['language' => $config['config']['lang']]);
        foreach ($language['lang'] as $key => $lang) {
            $data['lang.' . $key] = $lang;
        }

        $manifestTemplate = file_get_contents('plugins/addin-outlook/src/config/manifest.xml.default');

        $newContent = $manifestTemplate;
        foreach ($data as $key => $value) {
            $newContent = str_replace('{' . $key . '}', $value, $newContent);
        }

        $response->write($newContent);
        $response = $response->withAddedHeader('Content-Disposition', 'attachment; filename="manifest.xml"');
        return $response->withHeader('Content-Type', 'application/xml');
    }

    public function getConfiguration(Request $request, Response $response)
    {
        $configuration = ConfigurationModel::getByPrivilege(['privilege' => 'admin_addin_outlook']);
        $configuration['value'] = json_decode($configuration['value'], true);

        if (!empty($configuration['value']['indexingModelId'])) {
            $model = IndexingModelModel::getById(['id' => $configuration['value']['indexingModelId'], 'select' => ['label']]);
            if (!empty($model)) {
                $configuration['value']['indexingModelLabel'] = $model['label'];
            }
        }

        if (!empty($configuration['value']['typeId'])) {
            $type = DoctypeModel::getById(['id' => $configuration['value']['typeId'], 'select' => ['description']]);
            if (!empty($type)) {
                $configuration['value']['typeLabel'] = $type['description'];
            }
        }

        if (!empty($configuration['value']['statusId'])) {
            $status = StatusModel::getByIdentifier(['identifier' => $configuration['value']['statusId'], 'select' => ['label_status', 'id']]);
            if (!empty($status)) {
                $configuration['value']['statusLabel'] = $status[0]['label_status'];
                $configuration['value']['status'] = $status[0]['id'];
            }
        }

        if (!empty($configuration['value']['attachmentTypeId'])) {
            $attachmentType = AttachmentTypeModel::getById(['id' => $configuration['value']['attachmentTypeId'], 'select' => ['label']]);
            if (!empty($attachmentType)) {
                $configuration['value']['attachmentTypeLabel'] = $attachmentType['label'];
            }
        }

        $user = UserModel::getById(['id' => $GLOBALS['id'], 'select' => ['preferences']]);
        $user['preferences'] = json_decode($user['preferences'], true);
        $configuration['value']['outlookPasswordSaved'] = !empty($user['preferences']['outlookPassword']);

        return $response->withJson(['configuration' => $configuration['value']]);
    }

    public function saveConfiguration(Request $request, Response $response)
    {
        if (!PrivilegeController::hasPrivilege(['privilegeId' => 'admin_parameters', 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }

        $body = $request->getParsedBody();

        if (!Validator::notEmpty()->intVal()->validate($body['indexingModelId'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body indexingModelId is empty or not an integer']);
        } elseif (!Validator::notEmpty()->intVal()->validate($body['typeId'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body typeId is empty or not an integer']);
        } elseif (!Validator::notEmpty()->intVal()->validate($body['statusId'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body statusId is empty or not an integer']);
        } elseif (!Validator::notEmpty()->intVal()->validate($body['attachmentTypeId'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body attachmentTypeId is empty or not an integer']);
        }

        $model = IndexingModelModel::getById(['id' => $body['indexingModelId'], 'select' => ['master']]);
        if (empty($model)) {
            return $response->withStatus(400)->withJson(['errors' => 'Indexing model does not exist']);
        } elseif (!empty($model['master'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Indexing model is not a master model']);
        }

        $type = DoctypeModel::getById(['id' => $body['typeId'], 'select' => [1]]);
        if (empty($type)) {
            return $response->withStatus(400)->withJson(['errors' => 'Document type does not exist']);
        }

        $status = StatusModel::getByIdentifier(['identifier' => $body['statusId'], 'select' => [1]]);
        if (empty($status)) {
            return $response->withStatus(400)->withJson(['errors' => 'Status does not exist']);
        }

        $attachmentType = AttachmentTypeModel::getById(['id' => $body['attachmentTypeId'], 'select' => [1]]);
        if (empty($attachmentType)) {
            return $response->withStatus(400)->withJson(['errors' => 'Attachment type does not exist']);
        }

        $data = ['indexingModelId' => $body['indexingModelId'], 'typeId' => $body['typeId'], 'statusId' => $body['statusId'], 'attachmentTypeId' => $body['attachmentTypeId']];
        $data = json_encode($data, JSON_UNESCAPED_SLASHES);
        if (empty(ConfigurationModel::getByPrivilege(['privilege' => 'admin_addin_outlook', 'select' => [1]]))) {
            ConfigurationModel::create(['value' => $data, 'privilege' => 'admin_addin_outlook']);
        } else {
            ConfigurationModel::update(['set' => ['value' => $data], 'where' => ['privilege = ?'], 'data' => ['admin_addin_outlook']]);
        }

        return $response->withStatus(204);
    }

    public function saveOutlookPassword(Request $request, Response $response)
    {
        $body = $request->getParsedBody();

        if (!Validator::notEmpty()->stringType()->validate($body['outlookPassword'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body outlookPassword is empty or not an integer']);
        }

        $preferences = UserModel::getById(['id' => $GLOBALS['id'], 'select' => ['preferences']]);
        $preferences = json_decode($preferences['preferences'], true);

        $preferences['outlookPassword'] = PasswordModel::encrypt(['password' => $body['outlookPassword']]);

        UserModel::update(['set' => ['preferences' => json_encode($preferences)], 'where' => ['id = ?'], 'data' => [$GLOBALS['id']]]);

        return $response->withStatus(204);
    }

    public function saveEmailAttachments(Request $request, Response $response, array $args)
    {
        $body = $request->getParsedBody();

        if (!Validator::notEmpty()->validate($body['attachments'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body messages is empty']);
        } elseif (!Validator::notEmpty()->stringType()->validate($body['emailId'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body emailId is empty or no a string']);
        } elseif (!Validator::notEmpty()->stringType()->validate($body['ewsUrl'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body ewsUrl is empty or no a string']);
        } elseif (!Validator::notEmpty()->stringType()->validate($body['ewsVersion'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body ewsVersion is empty or no a string']);
        } elseif (!Validator::notEmpty()->stringType()->validate($body['userId'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body userId is empty or no a string']);
        } elseif (!Validator::notEmpty()->intVal()->validate($body['resId'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body resId is empty or not an integer']);
        }

        $user = UserModel::getById(['id' => $GLOBALS['id'], 'select' => ['mail', 'preferences']]);
        $user['preferences'] = json_decode($user['preferences'], true);

        if (empty($user['preferences']['outlookPassword'])) {
            return $response->withStatus(400)->withJson(['errors' => 'You have not set your outlook password', 'lang' => 'outlookPasswordRequired']);
        }

        $configuration = ConfigurationModel::getByPrivilege(['privilege' => 'admin_addin_outlook']);
        $configuration['value'] = json_decode($configuration['value'], true);

        if (empty($configuration['value']['attachmentTypeId'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Attachment type configuration is missing']);
        }
        $attachmentType = AttachmentTypeModel::getById(['id' => $configuration['value']['attachmentTypeId'], 'select' => ['type_id']]);
        if (empty($attachmentType)) {
            return $response->withStatus(400)->withJson(['errors' => 'Attachment type does not exist']);
        }

        $config = [
            'url'            => $body['ewsUrl'],
            'mail'           => $body['userId'],
            'password'       => PasswordModel::decrypt(['cryptedPassword' => $user['preferences']['outlookPassword']]),
            'version'        => $body['ewsVersion'],
            'attachmentType' => $attachmentType['type_id']
        ];

        $errors = EWSController::getAttachments([
            'config'        => $config,
            'emailId'       => $body['emailId'],
            'attachmentIds' => $body['attachments'],
            'resId'         => $body['resId']
        ]);

        if (!empty($errors)) {
            if (!empty($errors['lang'])) {
                return $response->withStatus(400)->withJson(['errors' => $errors['errors'], 'lang' => $errors['lang']]);
            } else {
                return $response->withStatus(400)->withJson(['errors' => $errors]);
            }
        }

        return $response->withStatus(204);
    }
}
