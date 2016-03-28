<?php

namespace QtmTest\QueryBuilder;

use Qtm\QueryBuilder\Queryable;
use QtmTest\AppTestCase;

/**
 * @property Queryable $db
 */
class QueryableTest extends AppTestCase
{

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
            'SELECT `id` FROM `users` WHERE `status` IS NOT NULL AND `activated` IS NULL OR `id` IS NOT NULL',

            $db->from('users')
                ->select('id')
                ->whereNotNull('status')
                ->whereNull('activated')
                ->orWhereNotNull('id')
                ->toSql()
        );
        $this->assertEquals([], $db->getBindValues());

        // where callback
        $this->assertEquals(
            'SELECT `id` FROM `users` WHERE `activated` > ? AND (`status` IN (?,?,?) AND (`id` = ? OR `id` = ?))',

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
                })->toSql()
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


    }
}