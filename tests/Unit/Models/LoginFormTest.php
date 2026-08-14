<?php

declare(strict_types=1);

namespace app\tests\Unit\Models;

use app\models\LoginForm;
use Yii;
use app\models\User;

final class LoginFormTest extends \Codeception\Test\Unit
{
    private $_model;

    protected function _after()
    {
        Yii::$app->user->logout();
    }

    public function testLoginNoUser()
    {
        $this->_model = new LoginForm([
            'username' => 'несуществующий',
            'password' => 'несуществующий',
        ]);

        verify($this->_model->login())->false();
        verify(Yii::$app->user->isGuest)->true();
    }

    public function testLoginWrongPassword()
    {
        $this->createUser();

        $this->_model = new LoginForm([
            'username' => 'tester',
            'password' => 'неверный пароль',
        ]);

        verify($this->_model->login())->false();
        verify(Yii::$app->user->isGuest)->true();
        verify($this->_model->errors)->arrayHasKey('password');
    }

    public function testLoginCorrect()
    {
        $this->createUser();

        $this->_model = new LoginForm([
            'username' => 'tester',
            'password' => 'secret',
        ]);

        verify($this->_model->login())->true();
        verify(Yii::$app->user->isGuest)->false();
        verify($this->_model->errors)->arrayHasNotKey('password');
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
