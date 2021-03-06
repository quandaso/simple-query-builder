<?php
/**
 * @author quantm
 * @date: 30/03/2016 15:16
 */

namespace QtmTest\QueryBuilder;

use Qtm\QueryBuilder\Queryable;
use Qtm\Helper as H;
use QtmTest\AppTestCase;

/**
 * @property Queryable $db
 */
class QueryableTest extends AppTestCase
{
    public function setUp()
    {
        parent::setUp(); // TODO: Change the autogenerated stub
        $this->db = new Queryable(array (
            'host' => 'localhost',
            'database' => 'bigcoin_rebuild',
            'username' => 'root',
            'password' => 'quantm'
        ));

    }

    public function testAggregate()
    {

        $db = $this->db;

        $db->table('users')->where('id', '>', 10)->count();
        $this->assertEquals('SELECT COUNT(*) FROM `users` WHERE `id` > ? LIMIT 1', $db->getLastSql());

        $db->table('users')->max('id');
        $this->assertEquals('SELECT MAX(`id`) FROM `users` LIMIT 1', $db->getLastSql());

        $db->table('users')->min('id');
        $this->assertEquals('SELECT MIN(`id`) FROM `users` LIMIT 1', $db->getLastSql());

        $db->table('users')->avg('id');
        $this->assertEquals('SELECT AVG(`id`) FROM `users` LIMIT 1', $db->getLastSql());

        $db->table('users')->sum($db->raw('`id` * `id`'));
        $this->assertEquals('SELECT SUM(`id` * `id`) FROM `users` LIMIT 1', $db->getLastSql());

    }

    public function testInsertAndFind()
    {
        $db = $this->db;
        $now = new \DateTime;

        $data = [
            'username' => 'test_username',
            'email' => 'quantm.tb@gmail.com',
            'password' => (object) ['id' => 'test'],
            'phone' => [1,2,3,4,5],
            'date' => $now,
            'datetime' => $now
        ];

        $id = $db->table('users')->insert($data);
       // ConsoleColor::yellow($db->getLastSql());
       // pr($db->getBindValues());

        $user = $db->table('users')->find($id);
       // ConsoleColor::yellow($db->getLastSql());
        //pr($db->getBindValues());


        $this->assertEquals($data['username'], $user['username']);
        $this->assertEquals($data['email'], $user['email']);
        $this->assertEquals(json_encode($data['password']), $user['password']);
        $this->assertEquals(json_encode($data['phone']), $user['phone']);
        $this->assertEquals($data['date']->format('Y-m-d'), $user['date']);
        $this->assertEquals($data['datetime']->format('Y-m-d H:i:s'), $user['datetime']);


    }

    public function testUpdate()
    {
        $db = $this->db;
        $data = [
            'username' => 'test_username',
            'email' => 'test@mail.com',
            'password' => 'testpassword',
            'phone' => 821991,
        ];

        $id = $db->table('users')->insert($data);
        //ConsoleColor::yellow($db->getLastSql());
       // pr($db->getBindValues());

        $rowCount =$db->table('users')->where('id', $id)
            ->update([
                'email' => 'mynewEmail@gmail.com',
                'username' => null,
                'phone' => $db->raw('`phone` + ?', [1000])
            ]);

       // ConsoleColor::yellow($db->getLastSql());
       // pr($db->getBindValues());

        $user = $db->from('users')->where('id', $id)->first();
        $this->assertTrue($rowCount > 0);
        $this->assertEquals(822991, $user['phone']);
        $this->assertEquals('mynewEmail@gmail.com', $user['email']);
        $this->assertEquals(null, $user['username']);
    }

    public function testDelete()
    {
        $db = $this->db;
        $data = [
            'username' => 'test_username',
            'email' => 'test@mail.com',
            'password' => 'testpassword',
            'phone' => null,
        ];

        $id = $db->table('users')->insert($data);
       // ConsoleColor::yellow($db->getLastSql());
       // pr($db->getBindValues());

        $rowCount = $db->table('users')->where('id', $id)->delete();
        //ConsoleColor::yellow($db->getLastSql());
        //pr($db->getBindValues());

        $this->assertTrue($rowCount > 0);
        $this->assertEquals(0, $db->from('users')->where('id', $id)->count());
    }

    public function testWhere()
    {
        $config = [
            'host' => 'localhost',
            'database' => 'queryable',
            'username' => 'root',
            'password' => 'quantm'
        ];

        $db = new Queryable($config);

        // Generate where query
        $this->assertEquals(
            'SELECT * FROM `users`',
            $db->from('users')->toSql()
        );

        $this->assertEquals(
            'SELECT * FROM `users` GROUP BY `id` ORDER BY `email` DESC LIMIT 10,10',
            $db->from('users')
                ->limit(10)
                ->offset(10)
                ->groupBy('id')
                ->orderBy('email', 'DESC')
                ->toSql()
        );

        $this->assertEquals(
            'SELECT `id`,`email` FROM `users` WHERE `id` = ? OR `id` >= ?',
            $db->from('users')->select('id', 'email')->where('id', 1)->orWhere('id', '>=', 3)->toSql()
        );

        $this->assertEquals([1, 3], $db->getBindValues());

        // whereIn, whereNotIn
        $this->assertEquals(
            'SELECT `id` FROM `users` WHERE `id` = ? AND `email` = ? OR `id` IN (?,?,?) AND `status` NOT IN (?,?,?)',

            $db->from('users')
                ->select('id')
                ->where('id', 1)
                ->where('email', 'test@mail.com')
                ->orWhereIn('id', [1, 2, 3])
                ->whereNotIn('status', [2, 3, 4])
                ->toSql()
        );

        $this->assertEquals([1, 'test@mail.com', 1, 2, 3, 2, 3, 4], $db->getBindValues());

        // whereBetween
        $this->assertEquals(
            'SELECT `id` FROM `users` WHERE `id` BETWEEN ? AND ? OR `id` BETWEEN ? AND ?',

            $db->from('users')
                ->select('id')
                ->whereBetween('id', 1, 5)
                ->orWhereBetween('id', 5, 7)
                ->toSql()
        );

        $this->assertEquals([1, 5, 5, 7], $db->getBindValues());

        // whereLIKE
        $this->assertEquals(
            'SELECT `id` FROM `users` WHERE `id` LIKE ? OR `id` LIKE ?',

            $db->from('users')
                ->select('id')
                ->whereLike('id', 1)
                ->orWhereLike('id', 3)
                ->toSql()
        );

        $this->assertEquals([1, 3], $db->getBindValues());

        // where null
        $this->assertEquals(
            'SELECT `id` FROM `users` WHERE `status` IS NOT NULL AND `activated` IS NULL OR `id` IS NOT NULL OR `test` IS NULL',

            $db->from('users')
                ->select('id')
                ->whereNotNull('status')
                ->whereNull('activated')
                ->orWhereNotNull('id')
                ->orWhereNull('test')
                ->toSql()
        );
        $this->assertEquals([], $db->getBindValues());

        // where Raw
        $this->assertEquals(
            'SELECT `id` FROM `users` WHERE `test` <> ? AND DATE(users.created) > ? OR `id` IN (SELECT `id` FROM `users` WHERE `status` IN (?,?,?)) AND `status` = ?',

            $db->from('users')
                ->select('id')
                ->where('test', '<>', 3)
                ->whereRaw('DATE(users.created) > ?', [1])
                ->orWhereRaw('`id` IN (SELECT `id` FROM `users` WHERE `status` IN (?,?,?))', [1, 2, 3])
                ->where('status', 3)
                ->toSql()
        );

        $this->assertEquals([3, 1, 1, 2, 3, 3], $db->getBindValues());
        // where callback
        $this->assertEquals(
            'SELECT `id` FROM `users` WHERE `activated` > ? AND (`status` IN (?,?,?) AND (`id` = ? OR `id` = ?)) GROUP BY `id` ORDER BY `id` ASC',

            $db->from('users')
                ->select('id')
                ->where('activated', '>', 0)
                ->where(function (Queryable $q) {
                    $q->whereIn('status', [1, 2, 3])
                        ->where(function (Queryable $q1) {
                            $q1->where('id', 3)->orWhere('id', 4);
                            return $q1;
                        });

                    return $q;
                })
                ->groupBy('id')
                ->orderBy('id')
                ->toSql()
        );
        $this->assertEquals([0, 1, 2, 3, 3, 4], $db->getBindValues());

        // joins
        $this->assertEquals(
            'SELECT `id` FROM `users` LEFT JOIN `groups` ON `groups`.`id` = `users`.`group_id` RIGHT JOIN `test` ON `test`.`user_id` = `users`.`id` WHERE `id` = ?',

            $db->from('users')
                ->select('id')
                ->leftJoin('groups', 'groups.id', '=', 'users.group_id')
                ->rightJoin('test', 'test.user_id', '=', 'users.id')
                ->where('id', 1)
                ->toSql()
        );

        $this->assertEquals([1], $db->getBindValues());

        // joins with binding
        $this->assertEquals(
            'SELECT `id` FROM `users` LEFT JOIN `groups` ON `groups`.`id` = `users`.`group_id` WHERE `id` = ? LIMIT 10,20',

            $db->from('users')
                ->select('id')
                ->limit(20)
                ->offset(10)
                ->leftJoin('groups', 'groups.id','=', 'users.group_id')
                ->where('id', 1)
                ->toSql()
        );

        //join raw query
        $this->assertEquals(
            'SELECT `id` FROM `users` LEFT JOIN `groups` ON `groups`.`id` = `users`.`group_id` RIGHT JOIN `test` ON `test`.`user_id` = `users`.`id` AND `status` = ? WHERE `id` = ?',

            $db->from('users')
                ->select('id')
                ->leftJoinRaw('groups', '`groups`.`id` = `users`.`group_id`')
                ->rightJoinRaw('test', '`test`.`user_id` = `users`.`id` AND `status` = ?', [1])
                ->where('id', 1)
                ->toSql()
        );


        $this->assertEquals([1,1], $db->getBindValues());

        // Raw test
        $now = H::now();

        $this->assertEquals(
            'SELECT `id`,`email`,SUM(`id`) AS `sum` FROM `users` WHERE DATE(`created`) <= ? AND `status` NOT IN (?,?,?)',

            $db->from('users')
                ->select('id', 'email', $db->raw('SUM(`id`) AS `sum`'))
                ->where($db->raw('DATE(`created`)'), '<=', $now)
                ->whereNotIn('status', [3, 4, 5])
                ->toSql()
        );
        $this->assertEquals([$now, 3, 4, 5], $db->getBindValues());

        // Test order by multi field
        $this->assertEquals(
            'SELECT `id` FROM `users` WHERE `username` IN (?,?) GROUP BY `email`,`id`,`date`,`password` ORDER BY `id` DESC,`email` ASC,`datetime` DESC',

            $db->from('users')
                ->select('id')
                ->whereIn('username', [1,2])
                ->groupBy('email', 'id', 'date')
                ->groupBy('password')
                ->orderBy('id', 'DESC')
                ->orderBy('email')
                ->orderBy('datetime', 'DESC')
                ->toSql()
        );

        $this->assertEquals([1, 2], $db->getBindValues());

        // Order tests
        $this->assertEquals('SELECT `id`,`sum_number`,`sum_value` FROM `log_user_money_exchange` WHERE `deviceID` = ? AND DATE(`date`) = ?',

        $db->table('log_user_money_exchange')
            ->select('id', 'sum_number', 'sum_value')
            ->where('deviceID', 1)
            ->whereRaw('DATE(`date`) = ?', ['2016-01-01'])->toSql()
        );

        //test $notBetweenQuerySql
        $notBetweenQuerySql = $db->table('users')
            ->where('id', 1)
            ->groupBy('id', 'date')
            ->orderBy('id', 'desc')
            ->limit(10)
            ->offset(100)
            ->where(function(Queryable $query) {
                return $query->where('email', 'test')->whereNotBetween('test', 97,98);
            })
            ->whereNotBetween('id', 1, 2)
            ->orWhereNotBetween('date', '2015-101', '2016-1-1')
            ->toSql();

        $this->assertEquals('SELECT * FROM `users` WHERE `id` = ? AND (`email` = ? AND `test` NOT BETWEEN ? AND ?) AND `id` NOT BETWEEN ? AND ? OR `date` NOT BETWEEN ? AND ? GROUP BY `id`,`date` ORDER BY `id` DESC LIMIT 100,10',
            $notBetweenQuerySql);

        $this->assertEquals([1, 'test', 97, 98, 1, 2, '2015-101', '2016-1-1' ], $db->getBindValues());

    }

    public function testHaving()
    {
        $db = $this->db;

        //Test basic having
        $basicHavingSql = $db->table('users')
            ->selectRaw('DATE(`datetime`) as `Date`')
            ->having('Date', '2011-1-1')
            ->toSql();

        $this->assertEquals('SELECT DATE(`datetime`) as `Date` FROM `users` HAVING `Date` = ?', $basicHavingSql);
        $this->assertEquals(['2011-1-1'], $db->getBindValues());

        //Test having with where together
        $havingWithWhereSql =  $db->table('users')
            ->selectRaw('DATE(`datetime`) as `Date`')
            ->where('id', '>', 1)
            ->whereNotNull('email')
            ->having('Date', '>', '2011-01-01')
            ->toSql();

        $this->assertEquals('SELECT DATE(`datetime`) as `Date` FROM `users` WHERE `id` > ? AND `email` IS NOT NULL HAVING `Date` > ?'
            , $havingWithWhereSql);
        $this->assertEquals([1, '2011-01-01'], $db->getBindValues());

        // Test having with group by, order By,limit, offset
        $sql =  $db->table('users')
            ->selectRaw('DATE(`datetime`) as `Date`')
            ->selectRaw('COUNT(`id`) as `CountID`')
            ->whereIn('status', [1,2,3])
            ->whereNotNull('email')
            ->having('Date', '>', '2011-01-01')
            ->groupBy('id')
            ->orderBy('id', 'desc')
            ->orHaving('Date', '<=', '2016-01-01')
            ->limit(10)
            ->offset(10)
            ->toSql();

        $this->assertEquals('SELECT DATE(`datetime`) as `Date`,COUNT(`id`) as `CountID` FROM `users` WHERE `status` IN (?,?,?) AND `email` IS NOT NULL GROUP BY `id` HAVING `Date` > ? OR `Date` <= ? ORDER BY `id` DESC LIMIT 10,10'
            , $sql);
        $this->assertEquals([1,2,3, '2011-01-01', '2016-01-01'], $db->getBindValues());

        //Test havingRaw, orHavingRaw
        $sql =  $db->table('users')
            ->selectRaw('DATE(`datetime`) as `Date`')
            ->selectRaw('COUNT(`id`) as `CountID`')
            ->whereId('>', 1)
            ->orWhereEmail('testemail')
            ->havingRaw('`Date` > ?', ['2009-01-01'])
            ->orHavingRaw('`Date` <= ?', ['2020-12-31'])
            ->groupBy('id', 'email', 'datetime')
            ->limit(10)
            ->orderBy('datetime')
            ->toSql();

        $this->assertEquals('SELECT DATE(`datetime`) as `Date`,COUNT(`id`) as `CountID` FROM `users` WHERE `id` > ? OR `email` = ? GROUP BY `id`,`email`,`datetime` HAVING `Date` > ? OR `Date` <= ? ORDER BY `datetime` ASC LIMIT 10', $sql);

        $this->assertEquals([1, 'testemail', '2009-01-01', '2020-12-31'], $db->getBindValues());

        //Test having Null, Not Null
        $sql =  $db->table('users')
            ->selectRaw('DATE(`datetime`) as `Date`')
            ->selectRaw('COUNT(`id`) as `CountID`')
            ->where('id', '>', 1)
            ->havingNull('Date')
            ->orHavingNull('CountID')
            ->havingNotNull('Date')
            ->orHavingNotNull('CountID')
            ->toSql();

        $this->assertEquals('SELECT DATE(`datetime`) as `Date`,COUNT(`id`) as `CountID` FROM `users` WHERE `id` > ? HAVING `Date` IS NULL OR `CountID` IS NULL AND `Date` IS NOT NULL OR `CountID` IS NOT NULL', $sql);
        $this->assertEquals([1], $db->getBindValues());

        // Test having between, having not between
        $sql =  $db->table('users')
            ->selectRaw('DATE(`datetime`) as `Date`')
            ->selectRaw('COUNT(`id`) as `CountID`')
            ->where('id', '>', 1)
            ->havingNull('Date')
            ->having('CountID', 'BETWEEN', [1,2])
            ->orHaving('CountID', 'NOT BETWEEN', [11,222])
            ->toSql();

        $this->assertEquals('SELECT DATE(`datetime`) as `Date`,COUNT(`id`) as `CountID` FROM `users` WHERE `id` > ? HAVING `Date` IS NULL AND `CountID` BETWEEN ? AND ? OR `CountID` NOT BETWEEN ? AND ?', $sql);
        $this->assertEquals([1,1,2,11,222], $db->getBindValues());

        // Test having in, having not in
        $sql =  $db->table('users')
            ->selectRaw('DATE(`datetime`) as `Date`')
            ->selectRaw('COUNT(`id`) as `CountID`')
            ->where('id', '>', 1)
            ->havingNull('Date')
            ->having('CountID', 'IN', [1,2])
            ->orHaving('CountID', 'NOT IN', [3,4,5,6])
            ->toSql();

        $this->assertEquals('SELECT DATE(`datetime`) as `Date`,COUNT(`id`) as `CountID` FROM `users` WHERE `id` > ? HAVING `Date` IS NULL AND `CountID` IN (?,?) OR `CountID` NOT IN (?,?,?,?)', $sql);
        $this->assertEquals([1, 1, 2, 3, 4, 5, 6], $db->getBindValues());

        //Test having callback
        $sql = $db->table('users')
            ->selectRaw('DATE(`datetime`) as `Date`')
            ->selectRaw('COUNT(`id`) as `CountID`')
            ->where('id', '>', 1)
            ->havingNull('Date')
            ->having('CountID', 'IN', [1,2])
            ->having(function (Queryable $query) {
                return $query->having('Date', '<>', '2017-01-01')
                    ->having(function (Queryable $q1){
                    return $q1->having('CountID', 99)->orHaving('CountID',1);
                });
            })->toSql();

        $this->assertEquals('SELECT DATE(`datetime`) as `Date`,COUNT(`id`) as `CountID` FROM `users` WHERE `id` > ? HAVING `Date` IS NULL AND `CountID` IN (?,?) AND (`Date` <> ? AND (`CountID` = ? OR `CountID` = ?))', $sql);
        $this->assertEquals([1,1,2, '2017-01-01', 99, 1], $db->getBindValues());


    }
}