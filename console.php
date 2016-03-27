<?php
define('ROOT_DIR', dirname(__FILE__));
require ROOT_DIR . '/vendor/autoload.php';
use \Qtm\Test\User;
use \Qtm\QueryBuilder\Queryable;

$config = [
    'host' => 'localhost',
    'database' => 'bigcoin_rebuild',
    'username' => 'root',
    'password' => 'quantm'
];

$db = new Queryable($config);
dd( $db->from('user_test')
    ->select($db->raw('`id` AS `user_id`, SUM(`id`)'), 'email')
    ->get());

echo "\n";