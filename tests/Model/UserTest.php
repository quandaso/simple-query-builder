<?php

/**
 * @author quantm
 * @date: 28/03/2016 12:44
 */
namespace QtmTest\Model;
use Qtm\QueryBuilder\Queryable;
use QtmTest\AppTestCase;
use Qtm\Helper as H;
/**
 * @property Queryable $db
 */
class UserTest extends AppTestCase
{
    private $phone = '+84123456789';

    public function testCreateNew()
    {
        $data = [
            'email' => 'test@' . H::strRandom(5) . '.com',
            'phone' => $this->phone,
            'username' => 'test' . H::strRandom(3)
        ];

        $u = new User($data);
        $u->save();

        $this->assertTrue(is_numeric($u->id));
        $u1 = new User($u->id);

        $this->assertEquals($u['email'], $u1['email']);
        $this->assertEquals($u['phone'], $u1->phone);
        $this->assertEquals($u['username'], $u1->username);

    }

    public function testFindByMagic()
    {
        $u2 = User::findBy('phone', $this->phone);
        $u3 = User::findByPhone($this->phone);
     
        $this->assertEquals($u2->toArray(), $u3->toArray());
    }

    public function testWhereMagic()
    {
        $this->assertEquals('SELECT * FROM `users` WHERE `phone` = ? AND `id` = ?',
            User::wherePhone('+841667208673')->whereId(1)->toSql()
            );

        // Generate where query
        $this->assertEquals(
            'SELECT * FROM `users`',
            User::toSql()
        );

        $this->assertEquals(
            'SELECT * FROM `users` GROUP BY `id` ORDER BY `email` DESC LIMIT 10,10',
            User::limit(10)
                ->offset(10)
                ->groupBy('id')
                ->orderBy('email', 'DESC')
                ->toSql()
        );

        $this->assertEquals(
            'SELECT `id`,`email` FROM `users` WHERE `id` = ? OR `id` >= ?',
            User::select('id', 'email')->where('id', 1)->orWhere('id', '>=', 3)->toSql()
        );


        // whereIn, whereNotIn
        $this->assertEquals(
            'SELECT `id` FROM `users` WHERE `id` = ? AND `email` = ? OR `id` IN (?,?,?) AND `status` NOT IN (?,?,?)',

            User::select('id')
                ->where('id', 1)
                ->whereEmail('test@mail.com')
                ->orWhereIn('id', [1,2,3])
                ->whereNotIn('status', [2,3,4])
                ->toSql()
        );

        // whereBetween
        $this->assertEquals(
            'SELECT `id` FROM `users` WHERE `id` BETWEEN ? AND ? OR `id` BETWEEN ? AND ?',

            User::select('id')
                ->whereBetween('id', 1 ,5)
                ->orWhereBetween('id', 5, 7)
                ->toSql()
        );

        // whereLIKE
        $this->assertEquals(
            'SELECT `id` FROM `users` WHERE `id` LIKE ? OR `id` LIKE ?',

            User::select('id')
                ->whereLike('id', 1)
                ->orWhereLike('id', 3)
                ->toSql()
        );


        // where null
        $this->assertEquals(
            'SELECT `id` FROM `users` WHERE `status` IS NOT NULL AND `activated` IS NULL OR `id` IS NOT NULL',

            User::select('id')
                ->whereNotNull('status')
                ->whereNull('activated')
                ->orWhereNotNull('id')
                ->toSql()
        );

        // where callback
        $this->assertEquals(
            'SELECT `id` FROM `users` WHERE `activated` > ? AND (`status` IN (?,?,?) AND (`id` = ? OR `id` = ?))',

            User::select('id')
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

        // joins
        $this->assertEquals(
            'SELECT `id` FROM `users` LEFT JOIN `groups` ON `groups`.`id` = `users`.`group_id` RIGHT JOIN `test` ON test.user_id=users.id WHERE `id` = ?',

            User::select('id')
                ->leftJoin('groups', '`groups`.`id` = `users`.`group_id`')
                ->rightJoin('test', 'test.user_id=users.id')
                ->where('id', 1)
                ->toSql()
        );

    }
}