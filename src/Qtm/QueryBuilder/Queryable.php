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
        '!=' => true,
        '<>' => true,
        'IN' => true,
        'LIKE' => true,
        'BETWEEN' => true
    );

    /**
     * Queryable constructor.
     * @param null $db
     * @param null $username
     * @param null $password
     */
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
        $this->fromStates = self::flattenArray(func_get_args());
        return $this;
    }

    /**
     * Resets all query states
     */
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
        $this->selectFields = self::flattenArray(func_get_args());
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
    private function addWhereQuery($type = 'AND', $field, $opt = null, $value = null)
    {
        if ($field instanceof \Closure) {

            $callback = $field;

            $this->whereStates[] = array(
                'type' => $type,
                'query' => $callback
            );

            return $this;
        }

        if (func_num_args() === 3) {
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

    /**
     * @param $field
     * @return $this
     */
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

    /**
     * @param $field
     * @return $this
     */
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

    /**
     * @param $field
     * @return $this
     */
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

    /**
     * @param $field
     * @return $this
     */
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
     * @param $fromValue
     * @param $toValue
     * @return $this
     * @throws \Exception
     */
    public function whereBetween($field, $fromValue, $toValue)
    {
        $this->addWhereQuery('AND', $field, 'BETWEEN', [$fromValue, $toValue]);
        return $this;
    }


    /**
     * @param $field
     * @param $fromValue
     * @param $toValue
     * @return $this
     * @throws \Exception
     */
    public function orWhereBetween($field, $fromValue, $toValue)
    {
        $this->addWhereQuery('OR', $field, 'BETWEEN', [$fromValue, $toValue]);
        return $this;
    }

    /**
     * @param $field
     * @param array $values
     * @return $this
     * @throws \Exception
     */
    public function whereIn($field, array $values)
    {
        $this->addWhereQuery('AND', $field, 'IN', $values);
        return $this;
    }

    /**
     * @param $field
     * @param array $values
     * @return $this
     * @throws \Exception
     */
    public function orWhereIn($field, array $values)
    {
        $this->addWhereQuery('OR', $field, 'IN', $values);
        return $this;
    }

    /**
     * @param $field
     * @param null $opt
     * @param null $value
     * @return Queryable
     */
    public function where($field, $opt = null, $value = null)
    {
        if (func_num_args() === 2) {
            return $this->addWhereQuery('AND', $field, $opt);
        }

        return $this->addWhereQuery('AND', $field, $opt, $value);
    }

    /**
     * @param $field
     * @param null $opt
     * @param null $value
     * @return Queryable
     */
    public function orWhere($field, $opt = null, $value = null)
    {
        if (func_num_args() === 2) {
            return $this->addWhereQuery('OR', $field, $opt);
        }

        return $this->addWhereQuery('OR', $field, $opt, $value);
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
     * @throws \Exception
     */
    private function getWhereState($hasWhere = true)
    {
        if (empty ($this->whereStates)) {
            return '';
        }

        $whereStates = array();

        foreach ($this->whereStates as $i => $where) {
            $first = count($whereStates) === 0;

            if (isset ($where['field'])) {
                $where['field'] = self::quoteColumn($where['field']);
            }

            $statement = '';

            if (isset ($where['query'])) {
                $query = $where['query'](new static());

                if (!empty ($query->whereStates)) {
                    $statement = '(' . $query->getWhereState(false) . ')';

                    foreach ($query->values as $v) {
                        $this->values[] = $v;
                    }
                }

            } else if (isset ($where['nullQuery'])) {
                $statement = $where['field'] . ' ' . $where['operator'];
            } else if (strtoupper($where['operator']) === 'BETWEEN') {
                if (count($where['value']) < 2) {
                    throw new \Exception ('Missing BETWEEN values');
                }

                $statement = $where['field'] . ' BETWEEN ? AND ?';
                $this->values[] = $where['value'][0];
                $this->values[] = $where['value'][1];

            } else if (strtoupper($where['operator']) === 'IN') {
                if (!isset ($where['value'])) {
                    throw new \Exception('Missing WHERE in values');
                }

                $where['value'] = self::flattenArray($where['value']);

                $inValueSet = [];

                foreach ($where['value'] as $v) {
                    $this->values[] = $v;
                    $inValueSet[] = '?';
                }

                $statement = $where['field'] . ' IN (' . implode(',', $inValueSet) . ')';
            } else {
                $statement = $where['field'] . ' ' . $where['operator'] . ' ?';
                $this->values[] = $where['value'];
            }

            if (!$first) {
                $statement = $where['type'] . ' ' . $statement;
            }

            $whereStates[] = $statement;
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
        if ($this->_stmt === null) {
            $this->query($this->getSelectSql(), $this->values);
        }

        $results = $this->_stmt->fetchAll(\PDO::FETCH_ASSOC);
        $this->_stmt = null;

        return $results;
    }

    /**
     * @return null | array
     * @throws \Exception
     */
    public function first()
    {
        if ($this->_stmt === null) {
            $this->limit(1);
            $this->query($this->getSelectSql(), $this->values);
        }

        $results = $this->_stmt->fetchAll(\PDO::FETCH_ASSOC);

        $this->_stmt = null;
        return empty ($results) ? null : $results[0];
    }

    /**
     * @return int
     */
    public function count()
    {
        return count($this->get());
    }

    /**
     * @param $sql
     * @param array $values
     * @return $this
     */
    public function query($sql, $values = array())
    {
        $this->_lastSql = $sql;
        $this->_stmt = $this->_pdo->prepare($sql);
        $this->bindValues($values);
        $this->_stmt->execute();

        return $this;
    }


    /**
     * @return null
     */
    public function getLastSql()
    {
        return $this->_lastSql;
    }

    public function getBindValues()
    {
        return $this->values;
    }


    /**
     * Binds values
     * @param array | null $values
     */
    private function bindValues($values = null)
    {
        if (is_array($values)) {
            $_values = $values;
        } else {
            $_values = $this->values;
        }

        foreach ($_values as $i => $value) {
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
    private static function flattenArray($args)
    {
        if (!is_array($args)) {
            $args = [$args];
        }

        $result = array();
        foreach ($args as $arg) {
            if (is_array($arg)) {
                foreach (self::flattenArray($arg) as $v) {
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