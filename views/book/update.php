<?php

declare(strict_types=1);

use app\models\Book;
use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var Book $model */
/** @var array<int, string> $authors */

$this->title = 'Редактирование: ' . $model->title;
$this->params['breadcrumbs'][] = ['label' => 'Книги', 'url' => ['index']];
$this->params['breadcrumbs'][] = ['label' => $model->title, 'url' => ['view', 'id' => $model->id]];
$this->params['breadcrumbs'][] = 'Редактирование';
?>
<div class="book-update">
    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render('_form', ['model' => $model, 'authors' => $authors]) ?>
</div>
