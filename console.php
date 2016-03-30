<?php
define('ROOT_DIR', dirname(__FILE__));
require ROOT_DIR . '/vendor/autoload.php';
use \QtmTest\Model\User;

use Qtm\QueryBuilder\Queryable;

$config = array (
    'host' => 'localhost',
    'database' => 'queryable',
    'username' => 'root',
    'password' => 'quantm'
);

$db = new Queryable($config, 'stdClass');

Qtm\Helper::dd([1,2,3]);

$r = $db->table('users')->get();

echo $db->getLastSql();
echo "\n";