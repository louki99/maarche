<?php

/**
 * Copyright Maarch since 2008 under licence GPLv3.
 * See LICENCE.txt file at the root folder for more details.
 * This file is part of Maarch software.
 *
 */

/**
 * @brief Search Template Model
 * @author dev@maarch.org
 */

namespace Search\models;

use SrcCore\models\DatabaseModel;
use SrcCore\models\DatabasePDO;
use SrcCore\models\ValidatorModel;

class SearchModel
{
    public static function createTemporarySearchData(array $args)
    {
        $database = new DatabasePDO();
        
        $query = "DROP TABLE IF EXISTS search_tmp_".$GLOBALS['id'].";";
        $database->query($query);
        $query = "CREATE TEMPORARY TABLE search_tmp_".$GLOBALS['id']." (
            res_id bigint, 
            priority character varying(16), 
            type_id bigint,
            destination character varying(50), 
            status character varying(10), 
            category_id character varying(32),
            alt_identifier character varying(255),
            subject text,
            creation_date timestamp without time zone,
            dest_user INTEGER,
            process_limit_date timestamp without time zone,
            entity_label character varying(255),
            type_label character varying(255),
            firstname character varying(255),
            lastname character varying(255)
        ) ON COMMIT DROP;";
        $database->query($query);

        $joinDestOrder = '';
        $selectValues  = "res_id, priority, type_id, destination, status, category_id, alt_identifier, subject, creation_date, dest_user, process_limit_date, entity_label, type_label";
        if (!empty($args['order']) && $args['order'] == 'destUser') {
            $joinDestOrder = ' LEFT JOIN (SELECT firstname, lastname, id from users) AS us ON us.id = res_view_letterbox.dest_user ';
            $selectValues .= ', firstname, lastname';
        }

        $temporaryData = "SELECT " . $selectValues . " FROM res_view_letterbox " . $joinDestOrder . " WHERE " . implode(' AND ', $args['where']);
        $query         = "INSERT INTO search_tmp_".$GLOBALS['id']." (" . $selectValues . ") " . $temporaryData;
        $database->query($query, $args['data']);
    }

    public static function getTemporarySearchData(array $args)
    {
        ValidatorModel::arrayType($args, ['select', 'where', 'data', 'orderBy', 'groupBy']);

        $data = DatabaseModel::select([
            'select'   => $args['select'],
            'table'    => ['search_tmp_' . $GLOBALS['id']],
            'where'    => $args['where'] ?? [],
            'data'     => $args['data'] ?? [],
            'order_by' => $args['orderBy'] ?? [],
            'groupBy'  => $args['groupBy'] ?? [],
        ]);

        return $data;
    }
}
