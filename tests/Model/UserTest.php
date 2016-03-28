<?php

/**
 * @author quantm
 * @date: 28/03/2016 12:44
 */
use QtmTest\Model\User;

class UserTest extends \QtmTest\AppTestCase
{
    public function testInsert()
    {
        $user = User::first();

        $this->assertTrue(is_object($user));
    }
}