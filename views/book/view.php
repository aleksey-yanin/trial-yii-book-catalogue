<?php

declare(strict_types=1);

use app\models\Author;
use app\components\specifications\GuestCanSubscribe;
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
$canSubscribe = (new GuestCanSubscribe())->isSatisfiedByCurrentUser();
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
                // Подписка именная, поэтому кнопка стоит у каждого автора книги.
                'value' => static function (Book $book) use ($canSubscribe): string {
                    $items = array_map(
                        static function (Author $author) use ($canSubscribe): string {
                            $link = Html::a(
                                Html::encode($author->fullName),
                                ['/author/view', 'id' => $author->id],
                            );

                            if (!$canSubscribe) {
                                return $link;
                            }

                            return $link . ' ' . Html::button('Подписаться', [
                                'class' => 'btn btn-sm btn-outline-primary',
                                'data' => [
                                    'subscription-author-id' => $author->id,
                                    'subscription-author-name' => $author->fullName,
                                ],
                            ]);
                        },
                        $book->authors,
                    );

                    return $items === [] ? '—' : implode('<br>', $items);
                },
            ],
            [
                'attribute' => 'created_at',
                'format' => ['datetime', 'php:d.m.Y H:i'],
            ],
        ],
    ]) ?>

    <?php if ($canSubscribe): ?>
        <?= $this->render('//subscription/_modal') ?>
    <?php endif ?>
</div>
