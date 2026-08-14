<?php

declare(strict_types=1);

/** @var yii\web\View $this */

use app\components\specifications\UserCanEdit;
use yii\bootstrap5\Nav;
use yii\bootstrap5\NavBar;
use yii\helpers\Html;

// Пункты меню, ведущие на изменение каталога, показываются по той же спецификации,
// что разрешает сами действия в контроллерах.
$canEdit = (new UserCanEdit())->isSatisfiedByCurrentUser();

$items = [
    [
        'label' => 'Главная',
        'url' => ['/site/index'],
    ],
    [
        'label' => 'Книги',
        'url' => ['/book/index'],
    ],
    [
        'label' => 'Авторы',
        'url' => ['/author/index'],
    ],
    [
        // Отчёт публичный — по ТЗ доступен всем, поэтому виден и гостю.
        'label' => 'Отчёт',
        'url' => ['/report/top-authors'],
    ],
    [
        'label' => 'Добавить книгу',
        'url' => ['/book/create'],
        'visible' => $canEdit,
    ],
    [
        'label' => 'Вход',
        'url' => ['/site/login'],
        // Здесь речь о состоянии сессии, а не о праве — спецификация не при чём.
        'visible' => Yii::$app->user->isGuest,
    ],
    [
        'label' => 'Выход (' . Html::encode(Yii::$app->user->identity?->username ?? '') . ')',
        'url' => ['/site/logout'],
        'linkOptions' => [
            'data-method' => 'post',
            'class' => 'nav-link logout',
        ],
        'visible' => !Yii::$app->user->isGuest,
    ],
];

?>
<header id="header">
    <?php NavBar::begin(
        [
            'brandLabel' => Yii::$app->name,
            'brandUrl' => Yii::$app->homeUrl,
            'options' => ['class' => 'navbar-expand-md navbar-dark bg-dark fixed-top']
        ],
    ) ?>
    <?= Nav::widget(
        [
            'options' => ['class' => 'navbar-nav me-auto'],
            'encodeLabels' => false,
            'items' => $items,
        ],
    ) ?>
    <?= Html::button(
        '&#127769;',
        [
            'id' => 'theme-toggle',
            'class' => 'btn btn-link nav-link fs-5',
            'aria-label' => 'Switch to dark mode',
        ],
    ) ?>
    <?php NavBar::end() ?>
</header>
