<?php
define('ROOT_DIR', dirname(__FILE__));
require ROOT_DIR . '/vendor/autoload.php';

$db = new Qtm\QueryBuilder\Queryable();
echo $db->from('users')->select('id', 'email', 'full_name')->getSql();
echo "\n";