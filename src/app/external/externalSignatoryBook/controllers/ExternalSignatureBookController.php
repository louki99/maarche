<?php

/**
* Copyright Maarch since 2008 under licence GPLv3.
* See LICENCE.txt file at the root folder for more details.
* This file is part of Maarch software.
*
*/

/**
* @brief   External Signature Book Controller
* @author  dev@maarch.org
*/

namespace ExternalSignatoryBook\controllers;

use Slim\Psr7\Request;
use SrcCore\http\Response;
use SrcCore\models\CoreConfigModel;

class ExternalSignatureBookController
{
    public function getEnabledSignatureBook(Request $request, Response $response)
    {
        $enabledSignatureBook = null;

        $loadedXml = CoreConfigModel::getXmlLoaded(['path' => 'modules/visa/xml/remoteSignatoryBooks.xml']);
        if (!empty($loadedXml)) {
            $enabledOne = (string)$loadedXml->signatoryBookEnabled;
            foreach ($loadedXml->signatoryBook as $value) {
                if ((string)$value->id == $enabledOne) {
                    if (!empty($value->url) && !empty($value->userId) && !empty($value->password)) {
                        $enabledSignatureBook = $enabledOne;
                    }
                    break;
                }
            }
        }

        return $response->withJson(['enabledSignatureBook' => $enabledSignatureBook]);
    }
}
