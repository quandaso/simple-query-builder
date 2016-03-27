<?php

namespace QtmTest\QueryBuilder;

use Qtm\QueryBuilder\Queryable;

/**
 * @property Queryable $db
 */
class QueryableTest extends \PHPUnit_Framework_TestCase
{

    public function testWhere()
    {
        $config = [
            'host' => 'localhost',
            'database' => 'bigcoin_rebuild',
            'username' => 'root',
            'password' => 'quantm'
        ];

        $db = new Queryable($config);

        // Generate where query
        $this->assertEquals(
            'SELECT * FROM `user_test`',
            $db->from('user_test')->toSql()
        );

        $this->assertEquals(
            'SELECT * FROM `user_test` GROUP BY `id` ORDER BY `email` DESC LIMIT 10,10',
            $db->from('user_test')
                ->limit(10)
                ->offset(10)
                ->groupBy('id')
                ->orderBy('email', 'DESC')
                ->toSql()
        );

        $this->assertEquals(
            'SELECT `id`,`email` FROM `user_test` WHERE `id` = ? OR `id` >= ?',
            $db->from('user_test')->select('id', 'email')->where('id', 1)->orWhere('id', '>=', 3)->toSql()
        );

        $this->assertEquals([1, 3], $db->getBindValues());

        // whereIn, whereNotIn
        $this->assertEquals(
            'SELECT `id` FROM `user_test` WHERE `id` = ? AND `email` = ? OR `id` IN (?,?,?) AND `status` NOT IN (?,?,?)',

            $db->from('user_test')
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
            'SELECT `id` FROM `user_test` WHERE `id` BETWEEN ? AND ? OR `id` BETWEEN ? AND ?',

            $db->from('user_test')
                ->select('id')
                ->whereBetween('id', 1 ,5)
                ->orWhereBetween('id', 5, 7)
                ->toSql()
        );

        $this->assertEquals([1,5,5,7], $db->getBindValues());

        // whereLIKE
        $this->assertEquals(
            'SELECT `id` FROM `user_test` WHERE `id` LIKE ? OR `id` LIKE ?',

            $db->from('user_test')
                ->select('id')
                ->whereLike('id', 1)
                ->orWhereLike('id', 3)
                ->toSql()
        );

        $this->assertEquals([1,3], $db->getBindValues());

        // where null
        $this->assertEquals(
            'SELECT `id` FROM `user_test` WHERE `status` IS NOT NULL AND `activated` IS NULL OR `id` IS NOT NULL',

            $db->from('user_test')
                ->select('id')
                ->whereNotNull('status')
                ->whereNull('activated')
                ->orWhereNotNull('id')
                ->toSql()
        );
        $this->assertEquals([], $db->getBindValues());

        // where callback
        $this->assertEquals(
            'SELECT `id` FROM `user_test` WHERE `activated` > ? AND (`status` IN (?,?,?) AND (`id` = ? OR `id` = ?))',

            $db->from('user_test')
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



    }
}