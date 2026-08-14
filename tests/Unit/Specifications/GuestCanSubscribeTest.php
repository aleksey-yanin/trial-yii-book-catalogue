<?php

declare(strict_types=1);

namespace app\tests\Unit\Specifications;

use app\components\specifications\GuestCanSubscribe;
use app\models\User;

final class GuestCanSubscribeTest extends \Codeception\Test\Unit
{
    /**
     * Гость приходит как null — Yii::$app->user->identity незалогиненного посетителя.
     */
    public function testSatisfiedByGuest(): void
    {
        verify((new GuestCanSubscribe())->isSatisfiedBy(null))->true();
    }

    /**
     * Подписка — возможность именно гостя: авторизованный управляет каталогом.
     */
    public function testNotSatisfiedByAuthenticatedUser(): void
    {
        verify((new GuestCanSubscribe())->isSatisfiedBy($this->createUser()))->false();
    }

    public function testCurrentVisitorIsGuestByDefault(): void
    {
        verify((new GuestCanSubscribe())->isSatisfiedByCurrentUser())->true();
    }

    public function testCurrentVisitorAfterLogin(): void
    {
        \Yii::$app->user->login($this->createUser());

        verify((new GuestCanSubscribe())->isSatisfiedByCurrentUser())->false();
    }

    private function createUser(): User
    {
        $user = new User(['username' => 'tester']);
        $user->setPassword('secret');
        $user->generateAuthKey();
        $user->save();

        return $user;
    }
}
