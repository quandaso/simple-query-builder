<?php
define('ROOT_DIR', dirname(__FILE__));
require ROOT_DIR . '/vendor/autoload.php';
use \Qtm\QueryBuilder\Queryable;

$user = array(
    'username' => 'quantm@gmail.com',
    'phone' => rand(1000000000, 999999999)
);

$db = new Queryable('bigcoin_rebuild', 'root', 'quantm');
$db->table('user_test')->insert($user);
$r = $db->from('user_test')
    ->select('id')
    ->whereBetween('id', 1, 3)
    ->orWhereIn('id', [3,4,5,6,7,8])
    ->where('id', 'LIKE', 1)
    ->first();

echo "\n";
echo $db->getLastSql() . "\nValues:" . json_encode($db->getBindValues());
echo "\n";
print_r($r);
echo "\n";