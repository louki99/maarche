<?php

/**
* Copyright Maarch since 2008 under licence GPLv3.
* See LICENCE.txt file at the root folder for more details.
* This file is part of Maarch software.
*
*/

/**
* @brief DocerverType Controller
* @author dev@maarch.org
*/

namespace Docserver\controllers;

use Group\controllers\PrivilegeController;
use Docserver\models\DocserverTypeModel;
use Slim\Psr7\Request;
use SrcCore\http\Response;

class DocserverTypeController
{
    public function get(Request $request, Response $response)
    {
        if (!PrivilegeController::hasPrivilege(['privilegeId' => 'admin_docservers', 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }

        return $response->withJson(['docserverTypes' => DocserverTypeModel::get(['orderBy' => ['docserver_type_label']])]);
    }
}
