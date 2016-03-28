<?php
define('ROOT_DIR', dirname(__FILE__));
require ROOT_DIR . '/vendor/autoload.php';
use \QtmTest\Model\User;
use \Qtm\QueryBuilder\Queryable;


$u = User::wherePhone('+841667208673')->whereId(1)->toSql();
;
dd($u);