<?php
define('ROOT_DIR', dirname(__FILE__));
require ROOT_DIR . '/vendor/autoload.php';
use \QtmTest\Model\User;

use Qtm\QueryBuilder\Queryable;

function syncCode()
{
    $srcDir = '/var/www/html/slim/app/Helpers/';
    $dstDir = '/home/quantm/QueryBuilder/src/qtm/';
    $return = true;



    $return &=  copy($srcDir . 'Queryable.php', $dstDir . 'QueryBuilder/Queryable.php');
    $return &=  copy($srcDir . 'SimpleModel.php', $dstDir . 'Model/SimpleModel.php');
    $return &=  copy($srcDir . 'Helper.php', $dstDir . 'Helper.php');


    return $return;
}

return syncCode();

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