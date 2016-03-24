<?php
define('ROOT_DIR', dirname(__FILE__));
require ROOT_DIR . '/vendor/autoload.php';
use \Qtm\QueryBuilder\Queryable;

$db = new Queryable('bigcoin_rebuild', 'root', 'quantm');

$r = $db->table('user_test')
    ->where('id', '>', 1)
    ->update(['username'=> 'fuck']);

echo "\n";
echo $db->getLastSql();
echo "\n";
print_r($r);
echo "\n";