<?php


namespace Qtm\Model;
use Qtm\Helper as H;

class SimpleModel implements \ArrayAccess, \JsonSerializable
{
    protected $dbConfig = null;
    protected $_db = null;
    private $_data = array();
    private $_originalData = array();
    private $_id;
    private  $_fillableMap = array();

    protected $primaryKey = 'id';
    protected $fillable = null;
    protected $table = null;


    /**
     * Model constructor.
     * @param null|array|object $id Specified id or data
     * @param string|null $table
     * @throws \Exception
     */
    public function __construct($id = null, $table = null)
    {
        if (empty ($this->table)) {
            $this->table = (string) $table;
        }

        if (empty ($this->table)) {
            $classes = explode('\\', get_class($this));
            $this->table = H::camelCaseToUnderscore(end($classes));
        }

        if (!empty ($this->fillable)) {
            $this->_fillableMap = array_flip($this->fillable);
        }

        if (empty($this->_db)) {
            $this->_db = new Queryable($this->dbConfig, static::class);
        }

        if (is_object($id)) {
            $id = (array) $id;
        }

        if (is_array($id)) {
            $this->_data = $id;
            $this->_originalData = $id;

            if (isset ($this->_data[$this->primaryKey])) {
                $this->_id = $this->_data[$this->primaryKey];
            }

        } else {
            $this->_id = $id;

            if (isset ($id)) {
                $data = $this->_db->table($this->table)
                    ->where($this->primaryKey, $id)
                    ->fetchFirst('array');

                if (!empty ($data)) {
                    $this->_data = $data;
                    $this->_originalData = $data;
                }
            }
        }
    }

    /**
     * Sets record data
     * @param array $data
     * @throws \Exception
     */
    public function fill(array $data)
    {
        if ($this->fillable) {
            foreach ($data as $k => $v) {
                if (!isset ($this->_fillableMap[$k])) {
                    throw new \Exception('Could not fill: ' . $k);
                }
            }
        }

        if (isset ($data[$this->primaryKey])) {
            $this->_id = $data[$this->primaryKey];
        }


        if (empty ($this->_data)) {
            $this->_data = $data;
        } else {
            $this->_data = array_merge($this->_data, $data);
        }

    }

    /**
     * @param $name
     * @return null
     */
    public function getAttr($name)
    {
        $getMethod = '_' . H::strCamelCase('get_' . $name, '_');

        if (method_exists($this, $getMethod)) {
            return $this->$getMethod(@$this->_data[$name]);
        }

        if (isset ($this->_data[$name])) {
            return $this->_data[$name];
        }

        return null;
    }

    /**
     * @param $name
     * @param $value
     */
    public function setAttr($name, $value)
    {
        $setMethod = '_' . H::strCamelCase('set_' . $name, '_');

        if (method_exists($this, $setMethod)) {
            $value = $this->$setMethod($value);
        }

        if (is_array($value) || $value instanceof \stdClass) {
            $value = json_encode($value);
        } else if ($value instanceof \DateTime) {
            $value = $value->format('Y-m-d H:i:s');
        } else if (is_object($value)) {
            $value = (string) $value;
        }

        $this->_data[$name] = $value;
    }

    /**
     * Gets record attr
     * @param $name
     * @return null
     */
    public function __get($name)
    {
        return $this->getAttr($name);
    }

    /**
     * Sets record attr
     * @param $name
     * @return null
     * @throws \Exception
     */
    public function __set($name, $value)
    {
        $this->setAttr($name, $value);
    }

    /**
     * Implements __unset() method.
     * @param $name
     */
    public function __unset($name)
    {
        unset($this->_data[$name]);
    }

    /**
     * Implements __isset() method.
     * @param $name
     * @return boolean
     */
    public function __isset($name)
    {
        return isset ($this->_data[$name]);
    }

    /**
     * Gets changed data by diff $_originalData and $_data
     * @return array
     */
    public function getChangedData()
    {
        $changedData = array();

        foreach ($this->_data as $k => $v) {
            if (!array_key_exists($k, $this->_originalData) || $this->_originalData[$k] !== $v) {
                $changedData[$k] = $v;
            }
        }

        return $changedData;
    }

    /**
     * Saves data
     * @params string|array $args
     * @params string|array $_
     * @return int
     */
    public function save($args = null, $_ = null)
    {
        $fields = H::flattenArray(func_get_args());

        $_data = array();

        if (!empty ($fields)) {

            foreach ($fields as $field) {
                if (array_key_exists($field, $this->_data)) {
                    $_data[$field] = $this->_data[$field];
                }
            }

        } else {
            $_data = $this->getChangedData();
        }

        if (!empty ($_data)) {

            if (empty ($this->_id)) {
                $this->_id = $this->_db->table($this->table)->insert($_data);
                $this->_data[$this->primaryKey] = $this->_id;
            } else {
                $this->_db->table($this->table)
                    ->where($this->primaryKey, $this->_id)
                    ->update($_data);

                if (isset ($_data[$this->primaryKey])) {
                    $this->_id = $_data[$this->primaryKey];
                }
            }

            $this->_originalData = $this->_data;

        }

        return $this->_id;

    }

    /**
     * Deletes data
     */
    public function delete()
    {
        if (!empty ($this->_id)) {
            $this->_db->table($this->table)->where($this->primaryKey, $this->_id)->delete();
            unset ($this->_id);
            unset($this->_data[$this->primaryKey]);
            $this->_originalData = array();
            return true;
        }

        return false;
    }

    /**
     * Reloads data from db
     */
    public function reload()
    {
        $this->_data = $this->_db
            ->table($this->table)
            ->where($this->primaryKey, $this->_id)
            ->fetchFirst('array');

        $this->_originalData = $this->_data;
    }

    /**
     * @return array
     */
    public function toArray()
    {
        $_data = array();

        foreach ($this->_data as $name => $value) {
            $_data[$name] = $this->getAttr($name);
        }

        return $_data;
    }

    /**
     * @return array
     */
    public function getLastSql()
    {
        return array($this->_db->getLastSql(), $this->_db->getBindValues());
    }

    /**
     * Gets original data
     * @return array
     */
    public function getOriginalData()
    {
        return $this->_originalData;
    }

    /**
     * Gets dirty data
     * @return array
     */
    public function getDirtyData()
    {
        return $this->_data;
    }

    /**
     * @return null | string
     */
    public function getTable()
    {
        return $this->table;
    }

    /**
     * Implements ArrayAccess
     */
    public function offsetSet($offset, $value)
    {
        $this->setAttr($offset, $value);
    }

    /**
     * Implements ArrayAccess
     */
    public function offsetExists($offset)
    {
        return isset($this->_data[$offset]);
    }

    /**
     * Implements ArrayAccess
     */
    public function offsetUnset($offset)
    {
        unset($this->_data[$offset]);
    }

    /**
     * Implements ArrayAccess
     */
    public function offsetGet($offset)
    {
        return $this->getAttr($offset);
    }

    /**
     * Implements JsonSerializable
     */
    public function jsonSerialize ()
    {
        return $this->toArray();
    }


    /**
     * Gets table scheme
     * @param $table
     * @param string $dbConfig
     * @deprecated Use Queryable::scheme
     * @return array
     */
    public static function scheme($table, $dbConfig = 'master')
    {

        $scheme = (array) db($dbConfig)->query('SHOW COLUMNS FROM ' . $table)->get();

        return array_map(function($item) { return $item->Field; }, $scheme);
    }

    /**
     * Creates new instance from given data source
     * @param $data
     * @return static | null
     */
    public static function fromData($data = null)
    {
        if (empty ($data)) {
            return null;
        }

        $instance = new static();


        if (!is_array($data)) {
            $data = (array) $data;
        }

        $instance->fill($data);
        return $instance;
    }

    /**
     * @param $field
     * @param $value
     * @return mixed
     */
    public static function findBy($field, $value)
    {
        $obj = new static();
        return $obj->_db->from($obj->table)->where($field, $value)->first();
    }

    /**
     * @param $name
     * @param $arguments
     * @return mixed
     * @throws \Exception
     */
    public static function __callStatic($name, $arguments)
    {
        $obj = new static();

        if (preg_match('/^findBy(.+)/', $name, $m)) {

            if (count($arguments) === 0) {
                throw new \Exception('Missing argument');
            }

            $field = H::camelCaseToUnderscore($m[1]);
            return $obj->_db->table($obj->table)->where($field, $arguments[0])->first();
        }

        if (method_exists($obj->_db, $name) || preg_match('/^(where|orWhere)(.+)/', $name)) {
            $obj->_db->table($obj->table);

            return call_user_func_array([$obj->_db, $name], $arguments);
        }

        throw new \Exception('Method ' . static::class . '::' . $name . ' does not exist');
    }


}