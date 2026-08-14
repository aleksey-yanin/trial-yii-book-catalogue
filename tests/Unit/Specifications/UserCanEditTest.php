<?php

declare(strict_types=1);

namespace app\tests\Unit\Specifications;

use app\components\specifications\UserCanEdit;
use app\models\Author;
use app\models\User;

final class UserCanEditTest extends \Codeception\Test\Unit
{
    public function testSatisfiedBySavedUser(): void
    {
        verify((new UserCanEdit())->isSatisfiedBy($this->createUser()))->true();
    }

    /**
     * Гость приходит как null — Yii::$app->user->identity незалогиненного посетителя.
     */
    public function testNotSatisfiedByGuest(): void
    {
        verify((new UserCanEdit())->isSatisfiedBy(null))->false();
    }

    /**
     * Незасохранённый объект — не учётная запись: такой мог бы появиться из формы
     * и не должен давать прав.
     */
    public function testNotSatisfiedByUnsavedUser(): void
    {
        verify((new UserCanEdit())->isSatisfiedBy(new User(['username' => 'кто-то'])))->false();
    }

    public function testNotSatisfiedByForeignObject(): void
    {
        $author = new Author(['last_name' => 'Лем', 'first_name' => 'Станислав']);
        $author->save();

        verify((new UserCanEdit())->isSatisfiedBy($author))->false();
        verify((new UserCanEdit())->isSatisfiedBy('admin'))->false();
    }

    public function testCurrentUserIsGuestByDefault(): void
    {
        verify((new UserCanEdit())->isSatisfiedByCurrentUser())->false();
    }

    public function testCurrentUserAfterLogin(): void
    {
        \Yii::$app->user->login($this->createUser());

        verify((new UserCanEdit())->isSatisfiedByCurrentUser())->true();
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
