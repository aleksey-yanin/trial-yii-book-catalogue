<?php

declare(strict_types=1);

namespace app\tests\Unit;

use app\controllers\SiteController;
use Yii;
use yii\web\View;

final class LoginTest extends \Codeception\Test\Unit
{
    /**
     * Гостю показывается вход и не показывается выход.
     *
     * Раньше тест логинил пустой объект User; с пользователями из базы такой ситуации
     * не существует, поэтому проверяется то, что действительно важно, — вид меню для гостя.
     */
    public function testGuestSeesLoginLinkInsteadOfLogout(): void
    {
        $controller = new SiteController('site', Yii::$app);
        $view = new View(['context' => $controller]);

        // Вне HTTP-запроса Yii не может определить URI, а меню строит ссылки.
        Yii::$app->request->setUrl('/');

        $html = $view->render('//layouts/main.php', ['content' => 'Hello World°']);

        self::assertStringContainsString(
            'Вход',
            $html,
            'Failed asserting that the login link is rendered for a guest.',
        );
        self::assertStringNotContainsString(
            'Выход (',
            $html,
            'Failed asserting that the logout link is not rendered for a guest.',
        );
    }
}
