<?php

namespace QtmTest\QueryBuilder;

use Qtm\QueryBuilder\Queryable;
use QtmTest\AppTestCase;
use Qtm\Helper as H;

/**
 * @property Queryable $db
 */
class QueryableTest extends AppTestCase
{
    public function testAggregate()
    {
        $db = $this->db;

        $db->table('users')->where('id', '>', 10)->count();
        $this->assertEquals('SELECT COUNT(*) FROM `users` WHERE `id` > ? LIMIT 1', $db->getLastSql());

        $db->table('users')->max('id');
        $this->assertEquals('SELECT MAX(`id`) FROM `users` LIMIT 1' , $db->getLastSql());


    }

    public function testInsert()
    {
        $db = $this->db;
        $data = [
            'username' => 'test_username',
            'email' => 'test@mail.com',
            'password' => 'testpassword',
            'phone' => null,
        ];

        $id = $db->table('users')->insert($data);

        $user = $db->from('users')->where('id', $id)->first();

        foreach ($data as $k => $v) {
            $this->assertEquals($v, $user[$k]);
        }
    }

    public function testUpdate()
    {
        $db = $this->db;
        $data = [
            'username' => 'test_username',
            'email' => 'test@mail.com',
            'password' => 'testpassword',
            'phone' => null,
        ];

        $id = $db->table('users')->insert($data);

        $db->table('users')->where('id', $id)->update(['phone' => '821991', 'email' => 'mynewEmail@gmail.com', 'username' => null]);

        $user = $db->from('users')->where('id', $id)->first();

        $this->assertEquals('821991', $user['phone']);
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


        $db->table('users')->where('id', $id)->delete();

        $this->assertEquals(0,  $db->from('users')->where('id', $id)->count());
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
                ->orWhereIn('id', [1,2,3])
                ->whereNotIn('status', [2,3,4])
                ->toSql()
        );

        $this->assertEquals([1, 'test@mail.com', 1,2,3,2,3,4], $db->getBindValues());

        // whereBetween
        $this->assertEquals(
            'SELECT `id` FROM `users` WHERE `id` BETWEEN ? AND ? OR `id` BETWEEN ? AND ?',

            $db->from('users')
                ->select('id')
                ->whereBetween('id', 1 ,5)
                ->orWhereBetween('id', 5, 7)
                ->toSql()
        );

        $this->assertEquals([1,5,5,7], $db->getBindValues());

        // whereLIKE
        $this->assertEquals(
            'SELECT `id` FROM `users` WHERE `id` LIKE ? OR `id` LIKE ?',

            $db->from('users')
                ->select('id')
                ->whereLike('id', 1)
                ->orWhereLike('id', 3)
                ->toSql()
        );

        $this->assertEquals([1,3], $db->getBindValues());

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
                ->orWhereRaw('`id` IN (SELECT `id` FROM `users` WHERE `status` IN (?,?,?))', [1,2,3])
                ->where('status', 3)
                ->toSql()
        );

        $this->assertEquals([3, 1, 1,2,3,3], $db->getBindValues());
        // where callback
        $this->assertEquals(
            'SELECT `id` FROM `users` WHERE `activated` > ? AND (`status` IN (?,?,?) AND (`id` = ? OR `id` = ?)) GROUP BY `id` ORDER BY `id` ASC',

            $db->from('users')
                ->select('id')
                ->where('activated', '>', 0)
                ->where(function(Queryable $q) {
                    $q->whereIn('status', [1,2,3])
                        ->where(function(Queryable $q1) {
                            $q1->where('id', 3)->orWhere('id', 4);
                            return $q1;
                        });

                    return $q;
                })
                ->groupBy('id')
                ->orderBy('id')
                ->toSql()
        );
        $this->assertEquals([0, 1,2,3,3,4], $db->getBindValues());

        // joins
        $this->assertEquals(
            'SELECT `id` FROM `users` LEFT JOIN `groups` ON `groups`.`id` = `users`.`group_id` RIGHT JOIN `test` ON test.user_id=users.id WHERE `id` = ?',

            $db->from('users')
                ->select('id')
                ->leftJoin('groups', '`groups`.`id` = `users`.`group_id`')
                ->rightJoin('test', 'test.user_id=users.id')
                ->where('id', 1)
                ->toSql()
        );
        $this->assertEquals([1], $db->getBindValues());

        // joins with binding
        $this->assertEquals(
            'SELECT `id` FROM `users` LEFT JOIN `groups` ON `groups`.`id` = `users`.`group_id` AND `users`.`status`=? WHERE `id` = ? LIMIT 10,20',

            $db->from('users')
                ->select('id')
                ->limit(20)
                ->offset(10)
                ->leftJoin('groups', '`groups`.`id` = `users`.`group_id` AND `users`.`status`=?', [1])
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
                ->whereNotIn('status', [3,4,5])
                ->toSql()
        );

        $this->assertEquals([$now, 3,4,5], $db->getBindValues());
    }
}