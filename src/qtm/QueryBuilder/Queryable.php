<?php

/**
 * MySQL Query Builder (query syntax is like Laravel Query Builder)
 * @author quantm
 * @date: 26/03/2016 11:13
 */
namespace Qtm\QueryBuilder;
use Qtm\Helper as H;


class Queryable
{
    private static $queryHistory = [];
    private $_lastSql = null;
    private $_pdo = null;
    private $_limit = null;
    private $_offset = null;
    private $_order = [];
    private $_group = [];
    private $_table = null;
    private $_stmt = null;
    private $fetchClass = 'array';

    private $fromStates = array();
    private $selectFields = array();
    private $whereStates = array();
    private $havingStates = array();
    private $joinStates  = array();
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
        'BETWEEN' => true,
        'NOT BETWEEN' => true,
        'NOT IN' => true,
        'IS NULL' => true,
        'IS NOT NULL' => true
    );

    private $joinTypes = array(
        'INNER' => true,
        'LEFT' => true,
        'RIGHT' => true
    );

    /**
     * Queryable constructor.
     * @param null $config
     * @param  $fetchClass
     */
    public function __construct($config = null, $fetchClass = 'array')
    {
        $this->fetchClass = $fetchClass;

        if ($config instanceof \PDO) {
            $this->_pdo = $config;
            $this->_pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        } else if (isset ($config['host'], $config['database'], $config['username'])) {
            $db = $config['database'];
            $host = $config['host'];
            $username = $config['username'];
            $port = isset ($config['port']) ? $config['port'] : 3306;
            $password = isset ($config['password']) ? $config['password'] : '';

            $this->_pdo = new \PDO("mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4", $username, $password);
            $this->_pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        }
    }

    /**
     * @param $args string | array
     * @param $_ string | array
     * @return $this
     */
    public function from($args, $_ = null)
    {
        $this->initQuery();
        $this->fromStates = H::flattenArray(func_get_args());
        $this->_table = $this->fromStates[0];
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
        $this->_order = array();
        $this->_group = array();
        $this->_table = null;
        $this->_stmt = null;

        $this->fromStates = array();
        $this->selectFields = array();
        $this->whereStates = array();
        $this->havingStates = array();
        $this->values = array();
        $this->joinStates = array();
    }

    /**
     * @param $table
     * @return $this
     * @throws \Exception
     */
    public function table($table)
    {
        if (!is_string($table)) {
            throw new \Exception('Table name must be a string');
        }

        $this->initQuery();
        $this->_table = $table;
        $this->fromStates = array($table);
        return $this;
    }

    /**
     * @param $field array|string
     * @param $_ array|string
     * @return $this
     */
    public function select($field, $_ = null)
    {
        $this->selectFields = array_merge($this->selectFields, H::flattenArray(func_get_args()));
        return $this;
    }

    /**
     * @param $sql
     * @param array $values
     * @return $this
     */
    public function selectRaw($sql, array $values = array())
    {
        $this->selectFields[] = $this->raw($sql, $values);
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
            if ($opt !== null) {
                throw new \Exception("$opt query can not be a callback");
            }

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

        $opt = trim(strtoupper($opt));

        $this->whereStates[] = array(
            'type' => $type,
            'field' => $field,
            'operator' => $opt,
            'value' => $value
        );

        return $this;
    }

    /**
     * @return $this
     */
    public function whereNotBetween($field, $fromValue, $toValue)
    {
        return $this->addWhereQuery('AND', $field, 'NOT BETWEEN', [$fromValue, $toValue]);
    }

    /**
     * @return $this
     */
    public function orWhereNotBetween($field, $fromValue, $toValue)
    {
        return $this->addWhereQuery('OR', $field, 'NOT BETWEEN', [$fromValue, $toValue]);
    }

    /**
     * @return $this
     */
    public function orWhereNotNull($field)
    {
        return $this->addWhereQuery('OR', $field, 'IS NOT NULL', null);
    }

    /**
     * @return $this
     */
    public function orWhereNull($field)
    {
        return $this->addWhereQuery('OR', $field, 'IS NULL', null);
    }

    /**
     * @return $this
     */
    public function whereNotNull($field)
    {
        return $this->addWhereQuery('AND', $field, 'IS NOT NULL', null);
    }

    /**
     * @return $this
     */
    public function whereNull($field)
    {
        return $this->addWhereQuery('AND', $field, 'IS NULL', null);
    }

    /**
     * @return $this
     */
    public function whereBetween($field, $fromValue, $toValue)
    {
        return $this->addWhereQuery('AND', $field, 'BETWEEN', [$fromValue, $toValue]);
    }

    /**
     * @return $this
     */
    public function orWhereBetween($field, $fromValue, $toValue)
    {
        return $this->addWhereQuery('OR', $field, 'BETWEEN', [$fromValue, $toValue]);
    }

    /**
     * @return $this
     */
    public function whereIn($field, array $values)
    {
        return $this->addWhereQuery('AND', $field, 'IN', $values);
    }

    /**
     * @return $this
     */
    public function whereNotIn($field, array $values)
    {
        return $this->addWhereQuery('AND', $field, 'NOT IN', $values);
    }

    /**
     * @return $this
     */
    public function whereNotEmpty($field)
    {
        return $this->whereNotNull($field)->where($field, '!=', '');
    }

    /**
     * @return $this
     */
    public function orWhereNotEmpty($field)
    {
        return $this->orWhere(function (Queryable $query) use($field) {
            return $query->whereNotNull($field)->where($field, '!=', '');
        });
    }

    /**
     * @return $this
     */
    public function orWhereNotIn($field, array $values)
    {
        return $this->addWhereQuery('OR', $field, 'NOT IN', $values);
    }

    /**
     * @return $this
     */
    public function whereLike($field, $value)
    {
        return $this->addWhereQuery('AND', $field, 'LIKE', $value);
    }

    /**
     * @return $this
     */
    public function orWhereLike($field, $value)
    {
        return $this->addWhereQuery('OR', $field, 'LIKE', $value);
    }

    /**
     * @return $this
     */
    public function orWhereIn($field, array $values)
    {
        return $this->addWhereQuery('OR', $field, 'IN', $values);
    }

    /**
     * @param $field
     * @param null $opt
     * @param null $value
     * @return $this
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
     * @return $this
     */
    public function orWhere($field, $opt = null, $value = null)
    {
        if (func_num_args() === 2) {
            return $this->addWhereQuery('OR', $field, $opt);
        }

        return $this->addWhereQuery('OR', $field, $opt, $value);
    }

    /**
     * @param $sql
     * @param array $values
     * @return $this
     */
    public function whereRaw($sql, array $values = array())
    {
        $this->whereStates[] = array(
            'type' => 'AND',
            'rawSql' => $this->raw($sql, $values)
        );

        return $this;
    }

    /**
     * @param $sql
     * @param array $values
     * @return $this
     */
    public function orWhereRaw($sql, array $values = array())
    {
        $this->whereStates[] = array(
            'type' => 'OR',
            'rawSql' => $this->raw($sql, $values)
        );

        return $this;
    }


    /**
     * @param $table
     * @param $onRawCondition
     * @param array $values
     * @param string $type
     * @return $this
     * @throws \Exception
     */
    public function joinRaw($table, $onRawCondition, array $values = array(), $type = 'INNER')
    {
        if (!isset ($this->joinTypes[strtoupper($type)])) {
            throw new \Exception('Invalid join type');
        }

        $this->joinStates[] = array(
            'type' => $type,
            'table' => $table,
            'onRaw' => $this->raw($onRawCondition, $values),
        );

        return $this;
    }

    /**
     * @return $this
     */
    public function innerJoinRaw($table, $onRawCondition, array $values = array())
    {
        return $this->joinRaw($table, $onRawCondition, $values, 'INNER');
    }

    /**
     * @return $this
     */
    public function leftJoinRaw($table, $onRawCondition, array $values = array())
    {
        return $this->joinRaw($table, $onRawCondition, $values, 'LEFT');
    }

    /**
     * @return $this
     */
    public function rightJoinRaw($table, $onRawCondition, array $values = array())
    {
        return $this->joinRaw($table, $onRawCondition, $values, 'RIGHT');
    }

    /**
     * @param $table
     * @param $key
     * @param $operator
     * @param $value
     * @param string $type
     * @return $this
     * @throws \Exception
     */
    public function join($table, $key, $operator, $value, $type = 'INNER')
    {
        if (!isset ($this->joinTypes[strtoupper($type)])) {
            throw new \Exception('Invalid join type');
        }

        if (!isset ($this->operators[$operator])) {
            throw new \Exception('Invalid operator: ' . $operator);
        }

        $this->joinStates[] = array(
            'type' => $type,
            'table' => $table,
            'key' => $key,
            'operator' => $operator,
            'value' => $value
        );

        return $this;
    }

    /**
     * @return $this
     */
    public function innerJoin($table, $key, $operator, $value)
    {
        return $this->join($table, $key, $operator,$value,'INNER');
    }

    /**
     * @return $this
     */
    public function leftJoin($table, $key, $operator, $value)
    {
        return $this->join($table, $key, $operator,$value,'LEFT');
    }

    /**
     * @return $this
     */
    public function rightJoin($table, $key, $operator, $value)
    {
        return $this->join($table, $key, $operator, $value, 'RIGHT');
    }

    /**
     * @param $limit
     * @return $this
     */
    public function limit($limit)
    {
        if ($limit !== null) {
            $this->_limit = (int) $limit;
        }

        return $this;
    }

    /**
     * @param $offset
     * @return $this
     */
    public function offset($offset)
    {
        if ($offset !== null) {
            $this->_offset = (int) $offset;
        }

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

        $this->_order[] = array(
            'orderBy' => $field,
            'direction' => $direction
        );

        return $this;
    }

    /**
     * @param $field array|string
     * @param $_ array|string
     * @return $this
     */
    public function groupBy($field = null, $_ = null)
    {

        $this->_group = array_merge($this->_group, H::flattenArray(func_get_args()));

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
    private function addHavingQuery($type = 'AND', $field, $opt = null, $value = null)
    {
        if ($field instanceof \Closure) {

            $callback = $field;

            $this->havingStates[] = array(
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

        $opt = trim(strtoupper($opt));

        $this->havingStates[] = array(
            'type' => $type,
            'field' => $field,
            'operator' => $opt,
            'value' => $value
        );

        return $this;
    }

    /**
     * @param $field
     * @param null $opt
     * @param null $value
     * @return $this
     * @throws \Exception
     */
    public function having($field, $opt = null, $value = null)
    {
        if (func_num_args() === 2) {
            return $this->addHavingQuery('AND', $field, $opt);
        }

        return $this->addHavingQuery('AND', $field, $opt, $value);
    }

    /**
     * @param $field
     * @return Queryable
     * @throws \Exception
     */
    public function havingNull($field)
    {
        return $this->addHavingQuery('AND', $field, 'IS NULL', null);
    }

    /**
     * @param $field
     * @return Queryable
     * @throws \Exception
     */
    public function orHavingNull($field)
    {
        return $this->addHavingQuery('OR', $field, 'IS NULL', null);
    }

    /**
     * @param $field
     * @return Queryable
     * @throws \Exception
     */
    public function havingNotNull($field)
    {
        return $this->addHavingQuery('AND', $field, 'IS NOT NULL', null);
    }

    /**
     * @param $field
     * @return Queryable
     * @throws \Exception
     */
    public function orHavingNotNull($field)
    {
        return $this->addHavingQuery('OR', $field, 'IS NOT NULL', null);
    }

    /**
     * @param $field
     * @param null $opt
     * @param null $value
     * @return $this
     * @throws \Exception
     */
    public function orHaving($field, $opt = null, $value = null)
    {
        if (func_num_args() === 2) {
            return $this->addHavingQuery('OR', $field, $opt);
        }

        return $this->addHavingQuery('OR', $field, $opt, $value);
    }

    /**
     * @param $sql
     * @param array $values
     * @return $this
     */
    public function havingRaw($sql, array $values = array())
    {
        $this->havingStates[] = array(
            'type' => 'AND',
            'rawSql' => $this->raw($sql, $values)
        );

        return $this;
    }

    /**
     * @param $sql
     * @param array $values
     * @return $this
     */
    public function orHavingRaw($sql, array $values = array())
    {
        $this->havingStates[] = array(
            'type' => 'OR',
            'rawSql' => $this->raw($sql, $values)
        );

        return $this;
    }

    /**
     * @param bool $hasHaving
     * @return string
     * @throws \Exception
     */
    private function getHavingState($hasHaving = true)
    {
        if (empty ($this->havingStates)) {
            return  '';
        }

        $havingStates = array();

        foreach ($this->havingStates as $i => $having) {
            $first = count($havingStates) === 0;

            if (isset ($having['field'])) {
                $having['field'] = $this->quoteColumn($having['field']);
            }

            $statement = '';

            if (isset ($having['rawSql'])) {
                $statement = $having['rawSql']->sql;
                $this->values = array_merge($this->values, $having['rawSql']->values);
            } else if (isset ($having['query'])) {
                $query = $having['query'](new static());

                if (!empty ($query->havingStates)) {
                    $statement = '(' . $query->getHavingState(false) . ')';
                    $this->values = array_merge($this->values, $query->values);
                }

            } else if ($having['operator'] === 'IS NULL' || $having['operator'] === 'IS NOT NULL') {
                $statement = $having['field'] . ' ' . $having['operator'];
            } else if ($having['operator'] === 'BETWEEN' || $having['operator'] === 'NOT BETWEEN') {
                if (count($having['value']) < 2) {
                    throw new \Exception ('Missing BETWEEN values');
                }

                $statement = $having['field'] . ' ' . $having['operator'] .' ? AND ?';
                $this->values[] = $having['value'][0];
                $this->values[] = $having['value'][1];

            } else if ($having['operator'] === 'IN' || $having['operator'] === 'NOT IN') {
                if (!isset ($having['value'])) {
                    throw new \Exception('Missing WHERE in values');
                }

                $having['value'] = H::flattenArray($having['value']);

                $inValueSet = array();

                foreach ($having['value'] as $v) {
                    $this->values[] = $v;
                    $inValueSet[] = '?';
                }

                $statement = $having['field'] . ' ' . $having['operator'] . ' (' . implode(',', $inValueSet) . ')';
            } else {
                $statement = $having['field'] . ' ' . $having['operator'] . ' ?';
                $this->values[] = $having['value'];
            }

            if (!$first) {
                $statement = $having['type'] . ' ' . $statement;
            }

            $havingStates[] = $statement;
        }

        return  $hasHaving ? 'HAVING ' . implode(' ', $havingStates) : implode(' ', $havingStates);
    }

    /**
     * @return string
     */
    private function getJoinState()
    {
        if (empty ($this->joinStates)) {
            return  '';
        }

        $joins = array();

        foreach ($this->joinStates as $join) {
            if (isset ($join['onRaw'])) {
                $raw = $join['onRaw'];
                $joins[] = $join['type'] . ' JOIN ' .$this->quoteColumn($join['table'])
                    .' ON ' . $raw->sql;
                $this->values = array_merge($this->values, $raw->values);
            } else {
                $joins[] = $join['type'] . ' JOIN '
                    . $this->quoteColumn($join['table'])
                    . ' ON '
                    .  $this->quoteColumn($join['key']) .' '
                    . $join['operator'] . ' ' . $this->quoteColumn($join['value']);
            }
        }

        return implode(' ', $joins);
    }

    /**
     * @return string
     */
    private function getOrderByState()
    {
        if (!empty ($this->_order)) {
            $orderByStates = array();

            foreach ($this->_order as $order) {
                $orderByStates[] = $this->quoteColumn($order['orderBy']) . ' ' . $order['direction'];
            }

            return 'ORDER BY ' . implode(',', $orderByStates);
        }

        return '';
    }

    /**
     * @return string
     */
    private function getLimitState()
    {
        if ($this->_limit !== null && $this->_offset === null) {
            return 'LIMIT ' . $this->_limit;
        }

        if ($this->_limit !== null && $this->_offset !== null) {
            return 'LIMIT ' . $this->_offset . ',' . $this->_limit;
        }

        return '';
    }

    /**
     * @return string
     * @throws \Exception
     */
    public function toSql()
    {
        if (empty ($this->fromStates)) {
            throw new \Exception('Missing FROM statement');
        }

        $query = array (
            $this->getSelectState(),
            $this->getFromState(),
            $this->getJoinState(),
            $this->getWhereState(),
            $this->getGroupByState(),
            $this->getHavingState(),
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

        if (empty ($data)) {
            throw new \Exception('Update data can not be empty');
        }

        $updateSetStates = array();
        $this->values = array();

        foreach ($data as $k => $v) {
            if (self::isRawObject($v)) {
                $updateSetStates[] = $this->quoteColumn($k) .'=' . $v->sql;
                $this->values = array_merge($this->values, $v->values);
            } else {
                $updateSetStates[] = $this->quoteColumn($k) .'=?';
                $this->values[] = $v;
            }
        }

        $query = array (
            'UPDATE ' . $this->quoteColumn($this->_table),
            'SET ' . implode(',', $updateSetStates),
            $this->getWhereState(),
        );

        $this->_lastSql = trim(implode(' ', $query));
        $this->query($this->_lastSql, $this->values);

        return $this->_stmt->rowCount();
    }

    /**
     * @param $data
     * @return int
     * @throws \Exception
     */
    public function insert(array $data)
    {
        if (empty ($this->_table)) {
            throw new \Exception('Table name is not specified');
        }

        if (empty ($data)) {
            throw new \Exception('Insert data can not be empty');
        }

        $insertStates = array();
        $valueStates = array();
        $this->values = array();

        foreach ($data as $k => $v) {
            $insertStates[] = $this->quoteColumn($k);
            $valueStates[] = '?';
            $this->values[] = $v;
        }

        $query = array (
            'INSERT INTO ' . $this->quoteColumn($this->_table) . '(' . implode(',', $insertStates) .')',
            'VALUES(' . implode(',', $valueStates) . ')',
        );

        $this->_lastSql = trim(implode(' ', $query));
        $this->query($this->_lastSql, $this->values);

        return $this->_pdo->lastInsertId();
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

        $this->values = array();

        $query = array (
            'DELETE FROM ' . $this->quoteColumn($this->_table),
            $this->getWhereState()
        );

        $this->_lastSql = trim(implode(' ', $query));
        $this->query($this->_lastSql, $this->values);
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

        $selectFields = array_map(array($this, 'quoteColumn'), $this->selectFields);

        return  'SELECT ' . implode(',', $selectFields);
    }

    /**
     * @return string
     */
    private function getFromState()
    {
        $fromTables = array_map(array($this, 'quoteColumn'), $this->fromStates);
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
                $where['field'] = $this->quoteColumn($where['field']);
            }

            $statement = '';

            if (isset ($where['rawSql'])) {
                $statement = $where['rawSql']->sql;
                $this->values = array_merge($this->values, $where['rawSql']->values);
            } else if (isset ($where['query'])) {
                $query = $where['query'](new static());

                if (!empty ($query->whereStates)) {
                    $statement = '(' . $query->getWhereState(false) . ')';
                    $this->values = array_merge($this->values, $query->values);
                }

            } else if ($where['operator'] === 'IS NULL' || $where['operator'] === 'IS NOT NULL') {
                $statement = $where['field'] . ' ' . $where['operator'];
            } else if ($where['operator'] === 'BETWEEN' || $where['operator'] === 'NOT BETWEEN') {
                if (count($where['value']) < 2) {
                    throw new \Exception ('Missing BETWEEN values');
                }

                $statement = $where['field'] . ' ' . $where['operator'] .' ? AND ?';
                $this->values[] = $where['value'][0];
                $this->values[] = $where['value'][1];

            } else if ($where['operator'] === 'IN' || $where['operator'] === 'NOT IN') {
                if (!isset ($where['value'])) {
                    throw new \Exception('Missing WHERE in values');
                }

                $where['value'] = H::flattenArray($where['value']);

                $inValueSet = array();

                foreach ($where['value'] as $v) {
                    $this->values[] = $v;
                    $inValueSet[] = '?';
                }

                $statement = $where['field'] . ' ' . $where['operator'] . ' (' . implode(',', $inValueSet) . ')';
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
            $groupByStates = array_map(array($this, 'quoteColumn'), $this->_group);

            return 'GROUP BY ' . implode(',', $groupByStates);
        }

        return '';
    }

    /**
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function get($limit = null, $offset = null)
    {
        $this->limit($limit);
        $this->offset($offset);

        return $this->fetchAll($this->fetchClass);
    }

    /**
     * @param string $fetchClass
     * @return null
     */
    public function fetchFirst($fetchClass = 'array')
    {
        $this->limit(1);
        $results = $this->fetchAll($fetchClass);
        return empty ($results) ? null : $results[0];
    }

    /**
     * @param $fetchClass 'array'|'stdClass'
     * @return array
     * @throws \Exception
     */
    public function fetchAll($fetchClass = 'array')
    {
        if ($this->_stmt === null) {
            $this->query($this->toSql(), $this->values);
        }

        if ($fetchClass === 'stdClass') {
            $entries = $this->_stmt->fetchAll(\PDO::FETCH_CLASS);
            $this->_stmt = null;
            return $entries;
        }

        $entries = $this->_stmt->fetchAll(\PDO::FETCH_ASSOC);

        $this->_stmt = null;

        if ($fetchClass === 'array') {
            return $entries;
        } else if (is_string($fetchClass)) {
            $result  = array();

            foreach ($entries as $entry) {
                $obj = new $fetchClass($entry);
                $result[] = $obj;
            }

            return $result;
        }

        return $entries;
    }

    /**
     * @return null | array | \stdClass
     * @throws \Exception
     */
    public function first()
    {
        $results = $this->get(1);
        return empty ($results) ? null : $results[0];
    }

    /**
     * Returns [key => value]
     * @param $value
     * @param $key
     * @return array
     */
    public function lists($key, $value = null)
    {
        $result = $this->fetchAll('array');
        $lists = array();

        if (!empty ($result)) {
            foreach ($result as $data) {
                $lists[$data[$key]] = ($value === null) ? $data : $data[$value];
            }
        }

        return $lists;
    }

    /**
     * @param $func
     * @param $field
     * @return int
     */
    private function aggregate($func, $field)
    {
        $this->limit(1);
        $raw = $func . '(' .$this->quoteColumn($field) . ')';
        $this->selectFields = array($this->raw($raw));
        $result = $this->fetchAll('array');
        return $result[0][$raw];
    }

    /**
     * @param $field
     * @return int
     */
    public function count($field = '*')
    {
        return (int) $this->aggregate('COUNT', $field);
    }

    /**
     * @param $field
     * @return int
     */
    public function max($field)
    {
        return $this->aggregate('MAX', $field);
    }

    /**
     * @param $field
     * @return int
     */
    public function min($field)
    {
        return $this->aggregate('MIN', $field);
    }

    /**
     * @param $field
     * @return int
     */
    public function avg($field)
    {
        return  $this->aggregate('AVG', $field);
    }

    /**
     * @param $field
     * @return int
     */
    public function sum($field)
    {
        return $this->aggregate('SUM', $field);
    }

    /**
     * Executes sql query, all query is call this method
     * @param $sql
     * @param array $values
     * @return $this
     */
    public function query($sql, $values = array())
    {
        $this->beforeQuery($sql, $values);
        $this->_lastSql = $sql;
        $this->_stmt = $this->_pdo->prepare($sql);
        $this->bindValues($values);
        $this->_stmt->execute();
        $this->afterQuery($sql, $values);
        self::$queryHistory[] = empty ($values) ? $sql : array($sql, $values);

        return $this;
    }

    /**
     * @return array
     */
    public static function getQueryHistory()
    {
        return self::$queryHistory;
    }

    /**
     * Before query callback
     * @param $sql
     * @param array $values
     */
    protected function beforeQuery($sql, $values = array())
    {

    }

    /**
     * After query callback
     * @param $sql
     * @param array $values
     */
    protected function afterQuery($sql, $values = array())
    {

    }

    /**
     * Iterates all table records
     * @param $callback
     * @param int $chunkSize
     * @throws \Exception
     */
    public function chunk($chunkSize = 200, $callback)
    {
        if (empty ($this->_table)) {
            throw new \Exception('Table name is not specified');
        }

        if (!is_callable($callback)) {
            throw new \Exception('Invalid $callback argument');
        }

        $offset = 0;
        $limit = $chunkSize;
        $entries = $this->table($this->_table)->get($limit, $offset);

        while (!empty ($entries)) {
            $callback($entries);

            $offset += $limit;
            $entries = $this->table($this->_table)->get($limit, $offset);
        }
    }

    /**
     * Iterates all table records
     * @param $callback
     * @param int $chunkSize Number of record for each query
     * @throws \Exception
     */
    public function each($callback, $chunkSize = 1000)
    {
        $i = 0;
        $this->chunk($chunkSize, function($entries) use ($callback, $i) {
            foreach ($entries as $e) {
                $callback($e, $i);
                $i++;
            }
        });
    }

    /**
     * @return null
     */
    public function getLastSql()
    {
        return $this->_lastSql;
    }

    /**
     * @return array
     */
    public function getBindValues()
    {
        return $this->values;
    }

    /**
     * @param $sql
     * @param array $values
     * @return object
     */
    public function raw($sql, array $values = array())
    {
        return (object) array(
            'rawSql' => true,
            'sql' => (string) $sql,
            'values' => $values
        );
    }

    /**
     * Binds values
     * @param array | null $values
     */
    private function bindValues(array $values = array())
    {
        $this->values = $values;

        foreach ($this->values as $i => &$value) {
            if (is_string($value)) {
                $this->_stmt->bindValue($i + 1, $value, \PDO::PARAM_STR);
            } else if (is_int($value)) {
                $this->_stmt->bindValue($i + 1, $value, \PDO::PARAM_INT);
            } else if ($value === null) {
                $this->_stmt->bindValue($i + 1, null, \PDO::PARAM_NULL);
            } else {
                if (is_array($value) || $value instanceof \stdClass) {
                    $value = json_encode($value);
                } else if ($value instanceof \DateTime) {
                    $value = $value->format('Y-m-d H:i:s');
                }

                $this->_stmt->bindValue($i + 1, $value, \PDO::PARAM_STR);
            }
        }
    }

    /**
     * @return \PDO
     */
    public function pdo()
    {
        return $this->_pdo;
    }

    /**
     * Begin transaction
     */
    public function beginTransaction()
    {
        $this->_pdo->beginTransaction();
    }

    /**
     * Commit
     */
    public function commit()
    {
        $this->_pdo->commit();
    }

    /**
     * rollback
     */
    public function rollBack()
    {
        $this->_pdo->rollBack();
    }

    /**
     * @param $callback
     */
    public function transaction($callback)
    {
        if (is_callable($callback)) {
            $this->beginTransaction();
            $callback($this);
            $this->commit();
        }
    }

    /**
     * @param $field
     * @return string
     */
    private function quoteColumn($field)
    {
        if (self::isRawObject($field)) {
            $this->values = array_merge($this->values, $field->values);
            return $field->sql;
        }

        if ($field === '*') {
            return $field;
        }

        if (strpos($field, '.') !== false) {
            list($table, $field) = explode('.', $field);
            return "`".str_replace("`","``",$table)."`." . "`".str_replace("`","``",$field)."`";
        }

        return "`".str_replace("`","``",$field)."`";
    }

    /**
     * @param $value
     * @param string $field
     * @return mixed
     * @throws \Exception
     */
    public function find($value, $field = 'id')
    {
        if (empty ($this->_table)) {
            throw new \Exception('Table name is not specified');
        }

        return $this->table($this->_table)->where($field, $value)->first();
    }

    /**
     * @param bool $fullScheme
     * @return array
     * @throws \Exception
     */
    public function scheme($fullScheme = false)
    {
        if (empty ($this->_table)) {
            throw new \Exception('Table name is not specified');
        }

        $scheme = $this->query('SHOW COLUMNS FROM ' . $this->quoteColumn($this->_table))->get();

        if ($fullScheme) {
            return $scheme;
        }

        return array_map(function($item) { return $item->Field; }, $scheme);
    }

    /**
     * @param $obj
     * @return bool
     */
    private static function isRawObject($obj)
    {
        return is_object($obj) && isset ($obj->rawSql, $obj->sql, $obj->values);
    }

    /**
     * Interpolate Query:  for debug only
     * @return string The interpolated query
     */
    public function toInterpolatedSql() {
        $query = $this->toSql();
        $params = $this->values;

        $keys = array();
        $values = $params;

        # build a regular expression for each parameter
        foreach ($params as $key => $value) {
            if (is_string($key)) {
                $keys[] = '/:'.$key.'/';
            } else {
                $keys[] = '/[?]/';
            }

            if (is_string($value))
                $values[$key] = "'" . self::quoteValue($value) . "'";

            if (is_array($value))
                $values[$key] = "'" . implode("','", self::quoteValue($value)) . "'";

            if (is_null($value))
                $values[$key] = 'NULL';
        }

        $query = preg_replace($keys, $values, $query, 1, $count);

        return $query;
    }

    /**
     * Quotes value, for debug only
     * @param $inp
     * @return array|mixed
     */
    public static function quoteValue($inp)
    {
        if (is_array($inp)){
            return array_map(__METHOD__, $inp);
        }

        if (!empty($inp) && is_string($inp)) {
            return str_replace(array('\\', "\0", "\n", "\r", "'", '"', "\x1a"), array('\\\\', '\\0', '\\n', '\\r', "\\'", '\\"', '\\Z'), $inp);
        }

        return $inp;
    }

    /**
     * @param $name
     * @param $arguments
     * @return mixed
     * @throws \Exception
     */
    public function __call($name, $arguments)
    {
        if (preg_match('/^(where|orWhere)(.+)/', $name, $match)) {
            if (count($arguments) === 0) {
                throw new \Exception('Missing argument');
            }

            $field = H::camelCaseToUnderscore($match[2]);
            $method = $match[1];

            if (count($arguments) > 1) {
                return $this->$method($field, $arguments[0], $arguments[1]);
            }

            return $this->$method($field, $arguments[0]);
        }

        throw new \Exception('Method ' . static::class . '::' . $name . ' does not exist');
    }

}