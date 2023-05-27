<?php

/**
* Copyright Maarch since 2008 under licence GPLv3.
* See LICENCE.txt file at the root folder for more details.
* This file is part of Maarch software.
*
*/

/**
* @brief Transfer Controller
* @author dev@maarch.org
*/

namespace ExportSeda\controllers;

use Configuration\models\ConfigurationModel;
use SrcCore\models\CoreConfigModel;

class TransferController
{
    public static function transfer($target, $messageId, $type = null)
    {
        $config = ConfigurationModel::getByPrivilege(['privilege' => 'admin_export_seda']);
        $config = !empty($config['value']) ? json_decode($config['value'], true) : [];
        $adapter        = '';
        $res['status']  = 0;
        $res['content'] = '';

        if ($target == 'maarchcourrier') {
            $adapter = new AdapterMaarchCourrierController();
        } else {
            $res['status'] = 0;
            $res['content'] = _UNKNOWN_TARGET;
            return $res;
        }

        // [0] = url, [1] = header, [2] = cookie, [3] = data
        if ($type) {
            $param = $adapter->getInformations($messageId, $type);
        } else {
            $param = $adapter->getInformations($messageId);
        }

        try {
            $curl = curl_init();

            curl_setopt($curl, CURLOPT_URL, $param[0]);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_HTTPHEADER, $param[1]);
            curl_setopt($curl, CURLOPT_COOKIE, $param[2]);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $param[3]);
            curl_setopt($curl, CURLOPT_FAILONERROR, false);
            curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 10);

            if (empty($config['exportSeda']['certificateSSL'])) {
                curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
            } else {
                curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, true);
                curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2);

                $certificateSSL = $config['exportSeda']['certificateSSL'];
                if (is_file($certificateSSL)) {
                    $ext = ['.crt','.pem'];

                    $filenameExt = strrchr($certificateSSL, '.');
                    if (in_array($filenameExt, $ext)) {
                        curl_setopt($curl, CURLOPT_CAINFO, $certificateSSL);
                    } else {
                        $res['status'] = 1;
                        $res['content'] = _ERROR_EXTENSION_CERTIFICATE;
                        return $res;
                    }
                } elseif (is_dir($certificateSSL)) {
                    curl_setopt($curl, CURLOPT_CAPATH, $certificateSSL);
                } else {
                    $res['status'] = 1;
                    $res['content'] = _ERROR_UNKNOW_CERTIFICATE;
                    return $res;
                }
            }

            $exec = curl_exec($curl);
            $data = json_decode($exec);

            if (!$data || !empty($data->errors) || !empty($data->error)) {
                $res['status'] = 1;
                $curlError = curl_error($curl);
                if ($curlError) {
                    $res['content'] = $curlError;
                } elseif (!empty($data->errors)) {
                    $res['content'] = $data->errors;
                } elseif (!empty($data->error)) {
                    $res['content'] = serialize($data->error);
                }
            } else {
                $res['content'] = $data;
            }
            curl_close($curl);
        } catch (\Exception $e) {
            return false;
        }

        return $res;
    }
}
