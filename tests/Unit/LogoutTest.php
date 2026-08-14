<?php

declare(strict_types=1);

namespace app\tests\Unit;

use app\controllers\SiteController;
use app\models\User;
use Yii;
use yii\web\View;

final class LogoutTest extends \Codeception\Test\Unit
{
    public function testRenderLogoutLinkWhenUserIsLoggedIn(): void
    {
        $user = new User(['username' => 'tester']);
        $user->setPassword('secret');
        $user->generateAuthKey();
        $user->save();

        $controller = new SiteController('site', Yii::$app);

        $view = new View(['context' => $controller]);

        Yii::$app->user->login($user);

        $html = $view->render('//layouts/main.php', ['content' => 'Hello World°']);

        self::assertStringContainsString(
            'Выход (tester)',
            $html,
            'Failed asserting that the logout link is rendered for a logged-in user.',
        );
        self::assertStringContainsString(
            'data-method="post"',
            $html,
            'Failed asserting that the logout link uses POST method.',
        );

        $controller->actionLogout();

        $html = $view->render('//layouts/main.php', ['content' => 'Hello World°']);

        self::assertStringNotContainsString(
            'Выход (tester)',
            $html,
            'Failed asserting that the logout link is not rendered after logout.',
        );
    }
}
