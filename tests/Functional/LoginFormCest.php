<?php

declare(strict_types=1);

namespace app\tests\Functional;

use app\models\User;
use app\tests\Support\FunctionalTester;

final class LoginFormCest
{
    private const PASSWORD = 'secret';

    private ?User $_user = null;

    public function _before(FunctionalTester $I)
    {
        // Пользователи живут в базе, поэтому учётная запись создаётся перед каждым тестом.
        $this->_user = new User(['username' => 'tester']);
        $this->_user->setPassword(self::PASSWORD);
        $this->_user->generateAuthKey();
        $this->_user->save();

        $I->amOnRoute('site/login');
    }

    public function openLoginPage(FunctionalTester $I)
    {
        $I->see('Вход', 'h1');
    }

    // demonstrates `amLoggedInAs` method
    public function internalLoginById(FunctionalTester $I)
    {
        $I->amLoggedInAs($this->_user->id);
        $I->amOnPage('/');
        $I->see('Выход (tester)');
    }

    // demonstrates `amLoggedInAs` method
    public function internalLoginByInstance(FunctionalTester $I)
    {
        $I->amLoggedInAs(User::findByUsername('tester'));
        $I->amOnPage('/');
        $I->see('Выход (tester)');
    }

    public function loginWithEmptyCredentials(FunctionalTester $I)
    {
        $I->submitForm('#login-form', []);
        $I->expectTo('see validations errors');
        $I->see('Необходимо заполнить «Логин».');
        $I->see('Необходимо заполнить «Пароль».');
    }

    public function loginWithWrongCredentials(FunctionalTester $I)
    {
        $I->submitForm('#login-form', [
            'LoginForm[username]' => 'tester',
            'LoginForm[password]' => 'wrong',
        ]);
        $I->expectTo('see validations errors');
        $I->see('Неверный логин или пароль.');
    }

    public function loginSuccessfully(FunctionalTester $I)
    {
        $I->submitForm('#login-form', [
            'LoginForm[username]' => 'tester',
            'LoginForm[password]' => self::PASSWORD,
        ]);
        $I->see('Выход (tester)');
        $I->dontSeeElement('form#login-form');
    }
}
