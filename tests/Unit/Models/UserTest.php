<?php

declare(strict_types=1);

namespace app\tests\Unit\Models;

use app\models\User;
use yii\base\NotSupportedException;

final class UserTest extends \Codeception\Test\Unit
{
    public function testFindUserById(): void
    {
        $user = $this->createUser();

        verify(User::findIdentity($user->id))->notEmpty();
        verify(User::findIdentity(999999))->empty();
    }

    public function testFindUserByUsername(): void
    {
        $this->createUser();

        verify(User::findByUsername('tester'))->notEmpty();
        verify(User::findByUsername('не существует'))->empty();
    }

    public function testValidatesCorrectPassword(): void
    {
        verify($this->createUser()->validatePassword('secret'))->true();
    }

    public function testRejectsWrongPassword(): void
    {
        verify($this->createUser()->validatePassword('wrong'))->false();
    }

    /**
     * Пароль хранится хешем, а не открытым текстом.
     */
    public function testPasswordIsHashed(): void
    {
        $user = $this->createUser();

        verify($user->password_hash)->notEquals('secret');
        verify($user->password_hash)->stringStartsWith('$2y$');
    }

    public function testValidateAuthKey(): void
    {
        $user = $this->createUser();

        verify($user->validateAuthKey($user->auth_key))->true();
        verify($user->validateAuthKey('чужой ключ'))->false();
    }

    /**
     * API в приложении нет: вместо молчаливого null метод обязан сообщить об ошибке
     * конфигурации, иначе неверная настройка компонента user останется незамеченной.
     */
    public function testAccessTokenLoginIsNotSupported(): void
    {
        $this->expectException(NotSupportedException::class);

        User::findIdentityByAccessToken('any-token');
    }

    public function testUsernameIsUnique(): void
    {
        $this->createUser();

        $duplicate = new User(['username' => 'tester', 'auth_key' => 'x']);
        $duplicate->setPassword('secret');

        verify($duplicate->validate())->false();
        verify($duplicate->hasErrors('username'))->true();
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
