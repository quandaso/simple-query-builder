<?php
define('ROOT_DIR', dirname(__FILE__));
require ROOT_DIR . '/vendor/autoload.php';
use \QtmTest\Model\User;
use \Qtm\QueryBuilder\Queryable;

$config = array (
    'host' => 'localhost',
    'database' => 'queryable',
    'username' => 'root',
    'password' => 'quantm'
);

$db = new Queryable($config);



echo $db->table('users')->where('id', '>', 100)->count() . "\n";

echo $db->getLastSql();
echo "\n";