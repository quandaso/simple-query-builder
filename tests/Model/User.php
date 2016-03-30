<?php

/**
 * @author quantm
 * @date: 28/03/2016 12:43
 */
namespace QtmTest\Model;
use Qtm\Model\SimpleModel as Model;
use Qtm\QueryBuilder\Queryable;

class User extends Model
{
    public $table = 'users';

    private static $connection = null;

    public function __construct($id = null, $table = null)
    {
        // singleton pdo connection
        if (empty (self::$connection)) {

            $config = array (
                'host' => 'localhost',
                'database' => 'queryable',
                'username' => 'root',
                'password' => 'quantm'
            );

            $db = $config['database'];
            $host = $config['host'];
            $username = $config['username'];
            $port = isset ($config['port']) ? $config['port'] : 3306;
            $password = isset ($config['password']) ? $config['password'] : '';
            self::$connection = new \PDO("mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4", $username, $password);
            self::$connection->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        }

        $this->_db = new Queryable(self::$connection, static::class);
        parent::__construct($id, $table);

    }
}