<?php

namespace Qtm\QueryBuilder;



class Queryable
{
    private $_lastSql = null;
    private $_pdo = null;
    private $_limit = null;
    private $_offset = null;
    private $_order = null;
    private $_group = null;
    private $_table = null;
    private $_stmt = null;
    private $_orderDirection = 'ASC';

    private $fromStates = array();
    private $selectFields = array();
    private $whereStates = array();
    private $values = array();

    private $operators = array(
        '>' => true,
        '<' => true,
        '>=' => true,
        '<=' => true,
        '=' => true,
        '!=' => true
    );

    public function __construct($db = null, $username = null, $password = null)
    {
        if ($db instanceof \PDO) {
            $this->_pdo = $db;
            $this->_pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        } else if (isset ($db, $username, $password)) {
            $this->_pdo = new \PDO("mysql:host=localhost;dbname=$db;charset=utf8mb4", $username, $password);
            $this->_pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        }
    }

    /**
     * @return static
     */
    public function from()
    {
        $this->initQuery();
        $this->fromStates = self::flatArgs(func_get_args());
        return $this;
    }

    private function initQuery()
    {
        $this->_lastSql = null;
        $this->_limit = null;
        $this->_offset = null;
        $this->_order = null;
        $this->_group = null;
        $this->_table = null;
        $this->_stmt = null;
        $this->_orderDirection = 'ASC';

        $this->fromStates = array();
        $this->selectFields = array();
        $this->whereStates = array();
        $this->values = array();
    }

    /**
     * @return static
     */
    public function table($table)
    {
        $this->initQuery();
        $this->_table = $table;
        return $this;
    }

    /**
     * @return $this
     */
    public function select()
    {
        $this->selectFields = self::flatArgs(func_get_args());
        return $this;
    }

    /**
     * @param string $type
     * @param $field
     * @param null $opt
     * @param null $value
     * @return $this
     * @throws \Exception
     */
    private function buildWhereQuery($type = 'AND', $field, $opt = null, $value = null)
    {
        if ($field instanceof \Closure) {

            $callback = $field;

            $this->whereStates[] = array(
                'type' => $type,
                'query' => $callback
            );

            return $this;
        }

        if ($value === null) {
            $value = $opt;
            $opt = '=';
        } else {
            if (!isset ($this->operators[$opt])) {
                throw new \Exception('Invalid operator: ' . $opt);
            }

        }

        $this->whereStates[] = array(
            'type' => $type,
            'field' => $field,
            'operator' => $opt,
            'value' => $value
        );

        return $this;
    }

    public function orWhereNotNull($field)
    {
        $this->whereStates[] = array(
            'type' => 'OR',
            'field' => $field,
            'operator' => 'IS NOT NULL',
            'value' => null,
            'nullQuery' => true
        );

        return $this;
    }

    public function orWhereNull($field)
    {
        $this->whereStates[] = array(
            'type' => 'OR',
            'field' => $field,
            'operator' => 'IS NULL',
            'value' => null,
            'nullQuery' => true
        );

        return $this;
    }

    public function whereNotNull($field)
    {
        $this->whereStates[] = array(
            'type' => 'AND',
            'field' => $field,
            'operator' => 'IS NOT NULL',
            'value' => null,
            'nullQuery' => true
        );

        return $this;
    }

    public function whereNull($field)
    {
        $this->whereStates[] = array(
            'type' => 'AND',
            'field' => $field,
            'operator' => 'IS NULL',
            'value' => null,
            'nullQuery' => true
        );

        return $this;
    }

    /**
     * @param $field
     * @param null $opt
     * @param null $value
     * @return Queryable
     * @throws \Exception
     */
    public function where($field, $opt = null, $value = null)
    {
        return $this->buildWhereQuery('AND', $field, $opt, $value);
    }

    /**
     * @param $field
     * @param null $opt
     * @param null $value
     * @return Queryable
     * @throws \Exception
     */
    public function orWhere($field, $opt = null, $value = null)
    {
        return $this->buildWhereQuery('OR', $field, $opt, $value);
    }

    /**
     * @param $limit
     * @return $this
     */
    public function limit($limit)
    {
        $this->_limit = (int) $limit;
        return $this;
    }

    /**
     * @param $offset
     * @return $this
     */
    public function offset($offset)
    {
        $this->_offset = (int) $offset;
        return $this;
    }

    /**
     * @param $field
     * @param string $direction
     * @throws \Exception
     * @return $this
     */
    public function orderBy($field, $direction = 'ASC')
    {
        $direction = strtoupper($direction);

        if ($direction !== 'ASC' && $direction !== 'DESC') {
            throw new \Exception('Invalid order direction');
        }

        $this->_order = $field;
        $this->_orderDirection = $direction;

        return $this;
    }

    /**
     * @param $group
     * @return $this
     */
    public function groupBy($group)
    {
        $this->_group = $group;

        return $this;
    }

    /**
     * @return string
     */
    private function getOrderByState()
    {
        if ($this->_order !== null) {
            return "ORDER BY " . self::quoteColumn($this->_order) . ' ' . $this->_orderDirection;
        }

        return '';
    }

    /**
     * @return string
     */
    private function getLimitState()
    {
        if ($this->_limit !== null && $this->_offset === null) {
            return "LIMIT " . $this->_limit;
        }

        if ($this->_limit !== null && $this->_offset !== null) {
            return "LIMIT " . $this->_offset . ',' . $this->_limit;
        }

        return '';
    }

    /**
     * @return string
     * @throws \Exception
     */
    public function getSelectSql()
    {
        if (empty ($this->fromStates)) {
            throw new \Exception('Missing FROM statement');
        }

        $query = array (
            $this->getSelectState(),
            $this->getFromState(),
            $this->getWhereState(),
            $this->getGroupByState(),
            $this->getOrderByState(),
            $this->getLimitState(),
        );

        return trim(implode(' ', array_filter($query)));
    }

    /**
     * @param array $data
     * @return int
     * @throws \Exception
     */
    public function update(array $data)
    {
        if (empty ($this->_table)) {
            throw new \Exception('Table name is not specified');
        }

        $updateSetStates = array();
        $this->values = array();

        foreach ($data as $k => $v) {
            $updateSetStates[] = self::quoteColumn($k) .'=?';
            $this->values[] = $v;
        }

        $query = array (
            'UPDATE ' . self::quoteColumn($this->_table),
            'SET ' . implode(',', $updateSetStates),
            $this->getWhereState(),
        );

        $this->_lastSql = trim(implode(' ', $query));
        $this->_stmt = $this->_pdo->prepare($this->_lastSql);
        $this->bindValues();
        $this->_stmt->execute();

        return $this->_stmt->rowCount();
    }

    /**
     * @param $data
     * @return int
     * @throws \Exception
     */
    public function insert($data)
    {
        if (empty ($this->_table)) {
            throw new \Exception('Table name is not specified');
        }

        $insertStates = array();
        $valueStates = array();
        $this->values = array();

        foreach ($data as $k => $v) {
            $insertStates[] = self::quoteColumn($k);
            $valueStates[] = '?';
            $this->values[] = $v;
        }

        $query = array (
            'INSERT INTO ' . self::quoteColumn($this->_table) . '(' . implode(',', $insertStates) .')',
            'VALUES(' . implode(',', $valueStates) . ')',
        );

        $this->_lastSql = trim(implode(' ', $query));
        $this->_stmt = $this->_pdo->prepare($this->_lastSql);
        $this->bindValues();
        $this->_stmt->execute();

        return $this->_stmt->rowCount();
    }

    /**
     * @return int
     * @throws \Exception
     */
    public function delete()
    {
        if (empty ($this->_table)) {
            throw new \Exception('Table name is not specified');
        }


        $query = array (
            'DELETE FROM ' . self::quoteColumn($this->_table),
            $this->getWhereState()
        );

        $this->_lastSql = trim(implode(' ', $query));
        $this->_stmt = $this->_pdo->prepare($this->_lastSql);
        $this->bindValues();
        $this->_stmt->execute();

        return $this->_stmt->rowCount();
    }

    /**
     * @return string
     */
    private function getSelectState()
    {
        if (empty ($this->selectFields)) {
            return 'SELECT *';
        }

        $selectFields = array_map('self::quoteColumn', $this->selectFields);

        return  'SELECT ' . implode(',', $selectFields);
    }

    /**
     * @return string
     */
    private function getFromState()
    {
        $fromTables = array_map('self::quoteColumn', $this->fromStates);
        return 'FROM ' . implode(',', $fromTables);
    }

    /**
     * @param $hasWhere
     * @return string
     */
    private function getWhereState($hasWhere = true)
    {
        if (empty ($this->whereStates)) {
            return '';
        }

        $whereStates = array();

        foreach ($this->whereStates as $i => $where) {
            $first = count($whereStates) === 0;

            if (isset ($where['query'])) {
                $query = $where['query'](new static());

                if (!empty ($query->whereStates)) {
                    if ($first) {
                        $whereStates[] = '(' . $query->getWhereState(false) . ')';
                    } else {
                        $whereStates[] = $where['type'] . ' (' . $query->getWhereState(false) . ')';
                    }

                    foreach ($query->values as $v) {
                        $this->values[] = $v;
                    }
                }

            } else if (isset ($where['nullQuery'])) {
                if ($first) {
                    $whereStates[] = self::quoteColumn($where['field']) . ' ' . $where['operator'];
                } else {
                    $whereStates[] = $where['type'] . ' ' . self::quoteColumn($where['field']) . ' ' . $where['operator'];
                }
            } else {
                $this->values[] = $where['value'];

                if ($first) {
                    $whereStates[] = self::quoteColumn($where['field']) . ' ' . $where['operator'] . ' ?';
                } else {
                    $whereStates[] = $where['type'] . ' ' . self::quoteColumn($where['field']) . ' ' . $where['operator'] . ' ?';
                }
            }
        }

        return  $hasWhere ? 'WHERE ' . implode(' ', $whereStates) : implode(' ', $whereStates);
    }

    /**
     * @return array
     */
    private function getGroupByState()
    {
        if (!empty ($this->_group)) {
            return 'GROUP BY ' . self::quoteColumn($this->_group);
        }

        return '';
    }

    /**
     * @return array
     * @throws \Exception
     */
    public function get()
    {
        $this->_lastSql = $this->getSelectSql();
        $this->_stmt = $this->_pdo->prepare($this->_lastSql);
        $this->bindValues();
        $this->_stmt->execute();
        $results = $this->_stmt->fetchAll(\PDO::FETCH_ASSOC);
        return $results;
    }

    /**
     * @return null
     */
    public function getLastSql()
    {
        return $this->_lastSql;
    }

    /**
     * Binds values
     */
    private function bindValues()
    {
        foreach ($this->values as $i => $value) {
            if (is_string($value)) {
                $this->_stmt->bindValue($i + 1, $value, \PDO::PARAM_STR);
            } else if (is_int($value)) {
                $this->_stmt->bindValue($i + 1, $value, \PDO::PARAM_INT);
            } else if ($value === null) {
                $this->_stmt->bindValue($i + 1, null, \PDO::PARAM_NULL);
            } else {
                $this->_stmt->bindValue($i + 1, $value, \PDO::PARAM_STR);
            }
        }
    }

    /**
     * @param $args
     * @return array
     */
    private static function flatArgs($args)
    {
        $result = array();
        foreach ($args as $arg) {
            if (is_array($arg)) {
                foreach (self::flatArgs($arg) as $v) {
                    $result[] = $v;
                }
            } else {
                $result[] = $arg;
            }
        }

        return $result;
    }

    /**
     * @param $field
     * @return string
     */
    private static function quoteColumn($field)
    {
        if ($field === '*') {
            return $field;
        }

        if (strpos($field, '.') !== false) {
            list($table, $field) = explode('.', $field);
            return "`".str_replace("`","``",$table)."`." . "`".str_replace("`","``",$field)."`";
        }

        return "`".str_replace("`","``",$field)."`";
    }
}