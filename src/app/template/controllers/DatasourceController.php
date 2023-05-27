<?php

/**
 * Copyright Maarch since 2008 under licence GPLv3.
 * See LICENCE.txt file at the root folder for more details.
 * This file is part of Maarch software.
 *
 */

/**
 * @brief Datasource Controller
 * @author dev@maarch.org
 */

namespace Template\controllers;

use Basket\models\BasketModel;
use Contact\controllers\ContactController;
use Contact\models\ContactModel;
use Entity\models\EntityModel;
use Resource\models\ResourceContactModel;
use SrcCore\models\DatabaseModel;
use User\models\UserBasketPreferenceModel;
use Note\models\NoteModel;
use Resource\models\ResModel;
use SrcCore\models\TextFormatModel;
use User\models\UserModel;

class DatasourceController
{
    public static function notifEvents(array $aArgs)
    {
        $datasources['notification'][0] = $aArgs['params']['notification'];
        $datasources['recipient'][0]    = $aArgs['params']['recipient'];
        $datasources['events']          = [];
   
        foreach ($aArgs['params']['events'] as $event) {
            $datasources['events'][] = $event;
        }
        
        return $datasources;
    }

    public static function letterboxEvents(array $aArgs)
    {
        $datasources['recipient'][0]  = $aArgs['params']['recipient'];
        $datasources['res_letterbox'] = [];
        $datasources['sender']        = [];
        
        $basket = BasketModel::getByBasketId(['select' => ['id'], 'basketId' => 'MyBasket']);
        $preferenceBasket = UserBasketPreferenceModel::get([
            'select'  => ['group_serial_id'],
            'where'   => ['user_serial_id = ?', 'basket_id = ?'],
            'data'    => [$aArgs['params']['recipient']['id'], 'MyBasket']
        ]);
        
        foreach ($aArgs['params']['events'] as $event) {
            $table    = [$aArgs['params']['res_view'] . ' lb'];
            $leftJoin = [];
            $where    = [];
            $arrayPDO = [];
        
            switch ($event['table_name']) {
                case 'notes':
                    $table[]    = 'notes';
                    $leftJoin[] = 'notes.identifier = lb.res_id';
                    $where[]    = 'notes.id = ?';
                    $arrayPDO[] = $event['record_id'];
                    break;
        
                case 'listinstance':
                    $table[]    = 'listinstance li';
                    $leftJoin[] = 'lb.res_id = li.res_id';
                    $where[]    = 'listinstance_id = ?';
                    $arrayPDO[] = $event['record_id'];
                    break;
        
                case 'res_letterbox':
                case 'res_view_letterbox':
                default:
                    $where[]    = 'lb.res_id = ?';
                    $arrayPDO[] = $event['record_id'];
            }
        
            // Main document resource from view
            $res = DatabaseModel::select([
                'select'    => ['lb.*'],
                'table'     => $table,
                'left_join' => $leftJoin,
                'where'     => $where,
                'data'      => $arrayPDO,
            ])[0];
        
            // Lien vers la page detail
            $res['linktodoc']     = trim($aArgs['params']['maarchUrl'], '/') . '/dist/index.html#/resources/'.$res['res_id'].'/content';
            $res['linktodetail']  = trim($aArgs['params']['maarchUrl'], '/') . '/dist/index.html#/resources/'.$res['res_id'];
            if (!empty($res['res_id']) && !empty($preferenceBasket[0]['group_serial_id']) && !empty($basket['id']) && !empty($aArgs['params']['recipient']['id'])) {
                $res['linktoprocess'] = trim($aArgs['params']['maarchUrl'], '/') . '/dist/index.html#/process/users/'.$aArgs['params']['recipient']['id'].'/groups/'.$preferenceBasket[0]['group_serial_id'].'/baskets/'.$basket['id'].'/resId/'.$res['res_id'];
            }
        
            if (!empty($res['initiator'])) {
                $entityInfo = EntityModel::getByEntityId(['select' => ['*'], 'entityId' => $res['initiator']]);
                foreach ($entityInfo as $key => $value) {
                    $res['initiator_'.$key] = $value;
                }
            }

            if (!empty($res['typist'])) {
                $userInfo            = UserModel::getById(['select' => ['firstname', 'lastname'], 'id' => $res['typist']]);
                $res['typist_label'] = $userInfo['firstname'] . ' ' . $userInfo['lastname'];
            }
        
            $datasources['res_letterbox'][] = $res;
        
            $resourceContacts = ResourceContactModel::get([
                'where' => ['res_id = ?', "mode='sender'", "type='contact'"],
                'data'  => [$res['res_id']],
                'limit' => 1
            ]);
            $resourceContacts = $resourceContacts[0] ?? null;
        
            $contact = [];
            if (!empty($resourceContacts)) {
                $contact = ContactModel::getById(['id' => $resourceContacts['item_id'], 'select' => ['*']]);
        
                $postalAddress = ContactController::getContactAfnor($contact);
                unset($postalAddress[0]);
                $contact['postal_address'] = implode("\n", $postalAddress);
            }
            $datasources['sender'][] = $contact;
        }

        return $datasources;
    }

    public static function noteEvents(array $aArgs)
    {
        $datasources['recipient'][0] = $aArgs['params']['recipient'];
        $datasources['notes']        = [];
        
        $basket = BasketModel::getByBasketId(['select' => ['id'], 'basketId' => 'MyBasket']);
        $preferenceBasket = UserBasketPreferenceModel::get([
            'select'  => ['group_serial_id'],
            'where'   => ['user_serial_id = ?', 'basket_id = ?'],
            'data'    => [$aArgs['params']['recipient']['id'], 'MyBasket']
        ]);
        
        foreach ($aArgs['params']['events'] as $event) {
            if ($event['table_name'] != 'notes') {
                $note = DatabaseModel::select([
                    'select'    => ['mlb.*', 'notes.*', 'users.*'],
                    'table'     => ['listinstance li', $aArgs['params']['res_view'] . ' mlb', 'notes', 'users'],
                    'left_join' => ['mlb.res_id = li.res_id', 'notes.identifier = li.res_id', 'users.id = notes.user_id'],
                    'where'     => ['li.item_id = ?', 'li.item_mode = \'dest\'', 'li.item_type = \'user_id\'', 'li.res_id = ?'],
                    'data'      => [$aArgs['params']['recipient']['id'], $event['record_id']],
                ]);
                $note = !empty($note[0]) ? $note[0] : [];
                $resId = $note['identifier'] ?? null;
            } else {
                $note         = NoteModel::getById(['id' => $event['record_id']]);
                $resId        = $note['identifier'];
                $resLetterbox = ResModel::getById(['select' => ['*'], 'resId'  => $resId]);
                $datasources['res_letterbox'][] = $resLetterbox;
            }
        
            $note['linktodoc']     = trim($aArgs['params']['maarchUrl'], '/') . '/dist/index.html#/resources/'.$resId.'/content';
            $note['linktodetail']  = trim($aArgs['params']['maarchUrl'], '/') . '/dist/index.html#/resources/'.$resId;
        
            if (!empty($resId) && !empty($preferenceBasket[0]['group_serial_id']) && !empty($basket['id']) && !empty($aArgs['params']['recipient']['id'])) {
                $note['linktoprocess'] = trim($aArgs['params']['maarchUrl'], '/') . '/dist/index.html#/process/users/'.$aArgs['params']['recipient']['id'].'/groups/'.$preferenceBasket[0]['group_serial_id'].'/baskets/'.$basket['id'].'/resId/'.$resId;
            }

            if (!empty($resId)) {
                $resourceContacts = ResourceContactModel::get([
                    'where' => ['res_id = ?', "type = 'contact'", "mode = 'sender'"],
                    'data'  => [$resId],
                    'limit' => 1
                ]);
                $resourceContacts = $resourceContacts[0];
            }

            if ($event['table_name'] == 'notes') {
                $datasources['res_letterbox'][array_key_last($datasources['res_letterbox'])]['linktodoc']     = $note['linktodoc'];
                $datasources['res_letterbox'][array_key_last($datasources['res_letterbox'])]['linktodetail']  = $note['linktodetail'];
                $datasources['res_letterbox'][array_key_last($datasources['res_letterbox'])]['linktoprocess'] = $note['linktodoc'];
        
                $labelledUser = UserModel::getLabelledUserById(['id' => $note['user_id']]);
                $creationDate = TextFormatModel::formatDate($note['creation_date'], 'd/m/Y');
                $note = "{$labelledUser}  {$creationDate} : {$note['note_text']}\n";
            }
        
            $contact = [];
            if (!empty($resourceContacts)) {
                $contact = ContactModel::getById(['id' => $resourceContacts['item_id'], 'select' => ['*']]);
            }
            $datasources['sender'][] = $contact;
            
            $datasources['notes'][] = ['content' => $note];
        }

        return $datasources;
    }
}
