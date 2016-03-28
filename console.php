<?php
define('ROOT_DIR', dirname(__FILE__));
require ROOT_DIR . '/vendor/autoload.php';
use \Qtm\Test\User;
use \Qtm\QueryBuilder\Queryable;

$config = [
    'host' => 'localhost',
    'database' => 'queryable',
    'username' => 'root',
    'password' => 'quantm'
];

$db = new Queryable($config);

dd( $db->from('user_test')
    ->selectRaw('`id` AS `user_id`, SUM(`id`)')
    ->select('email')
    ->get());

echo "\n";