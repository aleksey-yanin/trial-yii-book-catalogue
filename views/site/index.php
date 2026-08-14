<?php

declare(strict_types=1);

use yii\helpers\Html;

/** @var yii\web\View $this */

$this->title = 'Каталог книг';
?>
<div class="site-index">
    <div class="p-5 mb-4 bg-body-tertiary rounded-3">
        <h1><?= Html::encode($this->title) ?></h1>
        <p class="lead">Книги, авторы и отчёт по количеству изданий за год.</p>
        <p>
            <?= Html::a('Перейти к книгам', ['/book/index'], ['class' => 'btn btn-primary btn-lg']) ?>
            <?= Html::a('Авторы', ['/author/index'], ['class' => 'btn btn-outline-secondary btn-lg']) ?>
        </p>
    </div>
</div>
