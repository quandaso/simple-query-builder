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
        $db = new Queryable('bigcoin_rebuild', 'root', 'quantm');

        // Generate where query
        $this->assertEquals(
            'SELECT * FROM `user_test`',
            $db->from('user_test')->toSql()
        );

        $this->assertEquals(
            'SELECT * FROM `user_test` LIMIT 10,10',
            $db->from('user_test')->limit(10)->offset(10)->toSql()
        );

        $this->assertEquals(
            'SELECT `id`,`email` FROM `user_test` WHERE `id` = ?',
            $db->from('user_test')->select('id', 'email')->where('id', 1)->toSql()
        );

        $this->assertEquals([1], $db->getBindValues());

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

        $this->assertEquals(
            'SELECT `id` FROM `user_test` WHERE `id` LIKE ? OR `id` LIKE ?',

            $db->from('user_test')
                ->select('id')
                ->whereLike('id', 1)
                ->orWhereLike('id', 3)
                ->toSql()
        );
        $this->assertEquals([1,3], $db->getBindValues());
    }
}