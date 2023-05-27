<?php

/**
* Copyright Maarch since 2008 under licence GPLv3.
* See LICENCE.txt file at the root folder for more details.
* This file is part of Maarch software.
*
*/

/**
* @brief Database Model
* @author dev@maarch.org
*/

namespace SrcCore\models;

class DatabaseModel
{
    /**
     * Database create a new sequence
     * @param array $args
     *
     * @return bool
     * @throws \Exception
     */
    public static function createSequence(array $args)
    {
        ValidatorModel::notEmpty($args, ['id', 'value']);
        ValidatorModel::stringType($args, ['id']);

        $query = "CREATE SEQUENCE \"{$args['id']}\" INCREMENT 1 MINVALUE 1 MAXVALUE 9223372036854775807 START {$args['value']} CACHE 1";

        $db = new DatabasePDO();
        $db->query($query);
        return true;
    }

    /**
     * Database update an existing sequence
     * @param array $args
     *
     * @return int
     * @throws \Exception
     */
    public static function updateSequence(array $args)
    {
        ValidatorModel::notEmpty($args, ['id', 'value']);
        ValidatorModel::stringType($args, ['id']);
        ValidatorModel::intType($args, ['value']);

        $query = "SELECT setval('\"" . $args['id'] . "\"', {$args['value']})";

        $db = new DatabasePDO();
        $stmt = $db->query($query);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row['setval'];
    }

    /**
     * Database delete an existing sequence
     * @param array $args
     *
     * @return bool
     * @throws \Exception
     */
    public static function deleteSequence(array $args)
    {
        ValidatorModel::notEmpty($args, ['id']);
        ValidatorModel::stringType($args, ['id']);

        $query = "DROP SEQUENCE IF EXISTS \"{$args['id']}\"";

        $db = new DatabasePDO();
        $db->query($query);
        return true;
    }

    /**
     * Database increase_chrono function
     * @param array $args
     *
     * @return int
     * @throws \Exception
     */
    public static function createOrIncreaseChrono(array $args)
    {
        ValidatorModel::notEmpty($args, ['chronoIdName', 'chronoSeqName']);
        ValidatorModel::stringType($args, ['chronoIdName', 'chronoSeqName']);

        $query = "SELECT increase_chrono('{$args['chronoSeqName']}' ,'{$args['chronoIdName']}')";

        $db = new DatabasePDO();
        $stmt = $db->query($query);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row['increase_chrono'];
    }

    /**
     * Database Nextval Sequence Function
     * @param array $args
     *
     * @return int
     * @throws \Exception
     */
    public static function getNextSequenceValue(array $args)
    {
        ValidatorModel::notEmpty($args, ['sequenceId']);
        ValidatorModel::stringType($args, ['sequenceId']);

        $query = "SELECT nextval('{$args['sequenceId']}')";

        $db = new DatabasePDO();
        $stmt = $db->query($query);

        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row['nextval'];
    }

    /**
    * Database Select Function
    * @param array $args
    * @throws \Exception if number of tables is different from number of joins
    *
    * @return array
    */
    public static function select(array $args)
    {
        ValidatorModel::notEmpty($args, ['select', 'table']);
        ValidatorModel::arrayType($args, ['select', 'table']);

        $tmpTable = $args['table'];
        $args['table'] = $args['table'][0];

        if (!empty($args['left_join'])) {
            ValidatorModel::arrayType($args, ['left_join']);
            if (count($tmpTable) - 1 != count($args['left_join'])) {
                throw new \Exception('Number of tables doesn\'t match with number of joins');
            }
            $i = 1;
            foreach ($args['left_join'] as $value) {
                $args['table'] .=  " LEFT JOIN {$tmpTable[$i]} ON {$value}";
                $i++;
            }
        }

        $select = implode(', ', $args['select']);

        if (empty($args['where'])) {
            $where = '';
        } else {
            ValidatorModel::arrayType($args, ['where']);
            $where = ' WHERE ' . implode(' AND ', $args['where']);
        }

        if (empty($args['groupBy'])) {
            $groupBy = '';
        } else {
            ValidatorModel::arrayType($args, ['groupBy']);
            $groupBy = ' GROUP BY ' . implode(', ', $args['groupBy']);
        }

        if (empty($args['order_by'])) {
            $orderBy = '';
        } else {
            ValidatorModel::arrayType($args, ['order_by']);
            $orderBy = ' ORDER BY ' . implode(', ', $args['order_by']);
        }

        if (empty($args['offset'])) {
            $offset = '';
        } else {
            ValidatorModel::intType($args, ['offset']);
            $offset = " OFFSET {$args['offset']}";
        }

        if (empty($args['limit'])) {
            $limit = '';
        } else {
            ValidatorModel::intType($args, ['limit']);
            $limit = $args['limit'];
        }

        if (empty($args['data'])) {
            $args['data'] = [];
        }
        ValidatorModel::arrayType($args, ['data']);


        $db = new DatabasePDO();

        if (!empty($limit)) {
            $limitData = $db->setLimit(['where' => $where, 'limit' => $limit]);
            $where = $limitData['where'];
            $limit = $limitData['limit'];
        }

        $query = "SELECT {$select} FROM {$args['table']}{$where}{$groupBy}{$orderBy}{$offset}{$limit}";

        $stmt = $db->query($query, $args['data']);

        $rowset = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $rowset[] = $row;
        }

        return $rowset;
    }

    /**
     * Database Insert Into Function
     * @param array $args
     *
     * @return bool
     * @throws \Exception
     */
    public static function insert(array $args)
    {
        ValidatorModel::notEmpty($args, ['table', 'columnsValues']);
        ValidatorModel::stringType($args, ['table']);
        ValidatorModel::arrayType($args, ['columnsValues']);

        $data = [];
        $columnsQuery = [];
        $valuesQuery = [];
        foreach ($args['columnsValues'] as $key => $value) {
            if ($value == 'SYSDATE' || $value == 'CURRENT_TIMESTAMP') {
                $valuesQuery[] = $value;
            } else {
                $valuesQuery[] = '?';
                $data[] = $value;
            }
            $columnsQuery[] = $key;
        }
        $columns = implode(', ', $columnsQuery);
        $values = implode(', ', $valuesQuery);

        $query = "INSERT INTO {$args['table']} ({$columns}) VALUES ({$values})";

        $db = new DatabasePDO();
        $db->query($query, $data);

        return true;
    }

    /**
     * Database Insert Multiple Function
     * @param array $args
     *
     * @return bool
     * @throws \Exception
     */
    public static function insertMultiple(array $args)
    {
        ValidatorModel::notEmpty($args, ['table', 'columns', 'values']);
        ValidatorModel::stringType($args, ['table']);
        ValidatorModel::arrayType($args, ['values', 'columns']);

        $data    = [];
        $aValues = [];

        foreach ($args['values'] as $values) {
            $aValue = [];
            foreach ($values as $value) {
                if ($value == 'SYSDATE' || $value == 'CURRENT_TIMESTAMP') {
                    $aValue[] = $value;
                } else {
                    $aValue[] = '?';
                    $data[]   = $value;
                }
            }
            $aValues[] = '(' . implode(',', $aValue) . ')';
        }

        $valuesString  = implode(', ', $aValues);
        $columns = implode(', ', $args['columns']);

        $query = "INSERT INTO {$args['table']} ({$columns}) VALUES {$valuesString}";

        $db = new DatabasePDO();
        $db->query($query, $data);

        return true;
    }

    /**
     * Database Update Function
     * @param array $args
     *
     * @return bool
     * @throws \Exception
     */
    public static function update(array $args)
    {
        ValidatorModel::notEmpty($args, ['table', 'where']);
        ValidatorModel::stringType($args, ['table']);
        ValidatorModel::arrayType($args, ['set', 'where', 'postSet']);

        if (empty($args['data'])) {
            $args['data'] = [];
        }
        ValidatorModel::arrayType($args, ['data']);

        $querySet  = [];
        $dataSet = [];
        if (!empty($args['set'])) {
            foreach ($args['set'] as $key => $value) {
                if ($value == 'SYSDATE' || (!empty($value) && strpos($value, 'CURRENT_TIMESTAMP') !== false)) {
                    $querySet[] = "{$key} = {$value}";
                } else {
                    $querySet[] = "{$key} = ?";
                    $dataSet[] = $value;
                }
            }
        }
        if (!empty($args['postSet'])) {
            foreach ($args['postSet'] as $key => $value) {
                $querySet[] = "{$key} = {$value}";
            }
        }
        $args['data'] = array_merge($dataSet, $args['data']);
        $set = implode(', ', $querySet);
        $where = implode(' AND ', $args['where']);

        $query = "UPDATE {$args['table']} SET {$set} WHERE {$where}";

        $db = new DatabasePDO();
        $db->query($query, $args['data']);

        return true;
    }

    /**
     * Database Delete From Function
     * @param array $args
     *
     * @return bool
     * @throws \Exception
     */
    public static function delete(array $args)
    {
        ValidatorModel::notEmpty($args, ['table', 'where']);
        ValidatorModel::stringType($args, ['table']);
        ValidatorModel::arrayType($args, ['where']);

        if (empty($args['data'])) {
            $args['data'] = [];
        }
        ValidatorModel::arrayType($args, ['data']);

        $where = implode(' AND ', $args['where']);
        $query = "DELETE FROM {$args['table']} WHERE {$where}";

        $db = new DatabasePDO();
        $db->query($query, $args['data']);

        return true;
    }

    /**
     * Database Begin Transaction Function
     *
     * @return bool
     * @throws \Exception
     */
    public static function beginTransaction()
    {
        $db = new DatabasePDO();

        return $db->beginTransaction();
    }

    /**
     * Database Commit Transaction Function
     *
     * @return bool
     * @throws \Exception
     */
    public static function commitTransaction()
    {
        $db = new DatabasePDO();

        return $db->commitTransaction();
    }

    /**
     * Database Rollback Transaction Function
     *
     * @return bool
     * @throws \Exception
     */
    public static function rollbackTransaction()
    {
        $db = new DatabasePDO();

        return $db->rollbackTransaction();
    }

    /**
     * Database Exec Function
     * @param string $query
     *
     * @return bool
     * @throws \Exception
     */
    public static function exec(string $query)
    {
        $db = new DatabasePDO();

        return $db->exec($query);
    }

    /**
     * Database Select column_name
     * @param array $args
     * @throws \Exception
     *
     * @return array
     */
    public static function getColumns(array $args)
    {
        ValidatorModel::notEmpty($args, ['table']);
        ValidatorModel::stringType($args, ['table']);

        $query = "SELECT column_name, data_type FROM information_schema.columns WHERE table_name = ?";

        $db = new DatabasePDO();
        $stmt = $db->query($query, [$args['table']]);

        $rowset = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $rowset[] = $row;
        }

        return $rowset;
    }
}
