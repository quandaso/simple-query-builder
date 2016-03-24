<?php

namespace Qtm\QueryBuilder;



class Queryable
{
    private $sql;
    private $fromStates = array();
    private $selectFields = array();
    private $whereStates = array();

    private $operators = array(
        '>' => true,
        '<' => true,
        '>=' => true,
        '<=' => true,
        '=' => true,
        '!=' => true
    );

    public function from()
    {
        $this->fromStates = self::flatArgs(func_get_args());
        return $this;
    }

    public function select()
    {
        $this->selectFields = self::flatArgs(func_get_args());
        return $this;

    }

    public function where($field, $opt = null, $value = null)
    {
        $this->whereStates[] = array(
            'type' => 'AND',
            'field' => $field,
            'operator' => $opt,
            'value' => $value
        );

        return $this;
    }

    public function orWhere($field, $opt = null, $value = null)
    {
        $this->whereStates[] = array(
            'type' => 'OR',
            'field' => $field,
            'operator' => $opt,
            'value' => $value
        );

        return $this;
    }

    public function getSql($type = 'SELECT')
    {
        return $this->getSelectSql();
    }

    private function getSelectSql()
    {
        $query = array($this->getSelectState(), $this->getFromState());
        return implode(' ', $query);
    }

    private function getSelectState()
    {
        if (empty ($this->selectFields)) {
            return 'SELECT *';
        }

        $selectFields = array_map(function($value) {
            return '`' . $value .'`';
        },$this->selectFields);
        $selectStates[] = 'SELECT ' . implode(',', $selectFields);

        return implode(' ', $selectStates);
    }

    private function getFromState()
    {
        $fromTables = array_map(function($value) {
            return '`' . $value .'`';
        },$this->fromStates);

        $fromStates[] = 'FROM ' . implode(',', $fromTables);

        return implode(' ', $fromStates);
    }

    private static function flatArgs($args)
    {
        $result = [];
        foreach ($args as $arg) {
            if (is_string($arg)) {
                $result[] = $arg;
            } else if (is_array($arg)) {
                foreach ($arg as $value) {
                    if (is_string($value)) {
                        $result[] = $value;
                    } else if (is_array($value)) {
                        foreach (self::flatArgs($value) as $v) {
                            $result[] = $v;
                        }
                    }
                }
            }
        }

        return $result;
    }



}