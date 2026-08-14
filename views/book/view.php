<?php

declare(strict_types=1);

use app\models\Author;
use app\components\specifications\UserCanEdit;
use app\models\Book;
use yii\helpers\Html;
use yii\widgets\DetailView;

/** @var yii\web\View $this */
/** @var Book $model */

$this->title = $model->title;
$this->params['breadcrumbs'][] = ['label' => 'Книги', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;

$canEdit = (new UserCanEdit())->isSatisfiedByCurrentUser();
$coverUrl = Yii::$app->coverStorage->getUrl($model->cover_path);
?>
<div class="book-view">
    <h1><?= Html::encode($this->title) ?></h1>

    <?php if ($canEdit): ?>
        <p>
            <?= Html::a('Редактировать', ['update', 'id' => $model->id], ['class' => 'btn btn-primary']) ?>
            <?= Html::a('Удалить', ['delete', 'id' => $model->id], [
                'class' => 'btn btn-danger',
                'data' => [
                    'confirm' => 'Удалить книгу «' . $model->title . '»?',
                    'method' => 'post',
                ],
            ]) ?>
        </p>
    <?php endif ?>

    <?php if ($coverUrl !== null): ?>
        <p><?= Html::img($coverUrl, ['alt' => 'Обложка', 'style' => 'max-height: 320px']) ?></p>
    <?php endif ?>

    <?= DetailView::widget([
        'model' => $model,
        'attributes' => [
            'title',
            'year',
            'isbn',
            [
                'attribute' => 'description',
                'value' => $model->description ?? '—',
            ],
            [
                'label' => 'Авторы',
                'format' => 'raw',
                'value' => static function (Book $book): string {
                    $links = array_map(
                        static fn (Author $author): string => Html::a(
                            Html::encode($author->fullName),
                            ['/author/view', 'id' => $author->id],
                        ),
                        $book->authors,
                    );

                    return $links === [] ? '—' : implode(', ', $links);
                },
            ],
            [
                'attribute' => 'created_at',
                'format' => ['datetime', 'php:d.m.Y H:i'],
            ],
        ],
    ]) ?>
</div>
