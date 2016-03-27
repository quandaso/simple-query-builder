<?php
define('ROOT_DIR', dirname(__FILE__));
require ROOT_DIR . '/vendor/autoload.php';
use \Qtm\Test\User;
use \Qtm\QueryBuilder\Queryable;

$users = User::whereNotNull('id')->limit(2)->get();

foreach ($users as $u) {
    $u->json_data = $u->id * 1000;
    $u->save();
}

