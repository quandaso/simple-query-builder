<?php
define('ROOT_DIR', dirname(__FILE__));
require ROOT_DIR . '/vendor/autoload.php';
use \Qtm\Test\User;
use \Qtm\QueryBuilder\Queryable;


$u = User::findByEmail('mynewEmail@gmail.com');
dd($u);