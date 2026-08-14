<?php

declare(strict_types=1);

use app\components\specifications\GuestCanSubscribe;
use app\components\specifications\UserCanEdit;
use app\models\Author;
use app\models\Book;
use yii\helpers\Html;
use yii\widgets\DetailView;
use yii\widgets\ListView;
use yii\data\ActiveDataProvider;

/** @var yii\web\View $this */
/** @var Author $model */

$this->title = $model->fullName;
$this->params['breadcrumbs'][] = ['label' => 'Авторы', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;

$canEdit = (new UserCanEdit())->isSatisfiedByCurrentUser();
$canSubscribe = (new GuestCanSubscribe())->isSatisfiedByCurrentUser();

$books = new ActiveDataProvider([
    'query' => $model->getBooks()->orderBy(['year' => SORT_DESC]),
    'pagination' => ['pageSize' => 20],
]);
?>
<div class="author-view">
    <h1><?= Html::encode($this->title) ?></h1>

    <?php if ($canSubscribe): ?>
        <p>
            <?= Html::button('Подписаться', [
                'class' => 'btn btn-outline-primary',
                'data' => [
                    'subscription-author-id' => $model->id,
                    'subscription-author-name' => $model->fullName,
                ],
            ]) ?>
        </p>
    <?php endif ?>

    <?php if ($canEdit): ?>
        <p>
            <?= Html::a('Редактировать', ['update', 'id' => $model->id], ['class' => 'btn btn-primary']) ?>
            <?= Html::a('Удалить', ['delete', 'id' => $model->id], [
                'class' => 'btn btn-danger',
                'data' => [
                    'confirm' => 'Удалить автора «' . $model->fullName . '»? Книги останутся в каталоге.',
                    'method' => 'post',
                ],
            ]) ?>
        </p>
    <?php endif ?>

    <?= DetailView::widget([
        'model' => $model,
        'attributes' => [
            'last_name',
            'first_name',
            [
                'attribute' => 'middle_name',
                'value' => $model->middle_name ?? '—',
            ],
        ],
    ]) ?>

    <h2>Книги автора</h2>

    <?= ListView::widget([
        'dataProvider' => $books,
        'emptyText' => 'Книг пока нет',
        'itemOptions' => ['tag' => 'li'],
        'options' => ['tag' => 'ul'],
        'layout' => "{items}\n{pager}",
        'itemView' => fn (Book $book): string => Html::a(
            Html::encode($book->title),
            ['/book/view', 'id' => $book->id],
        ) . ' (' . $book->year . ')',
    ]) ?>

    <?php if ($canSubscribe): ?>
        <?= $this->render('//subscription/_modal') ?>
    <?php endif ?>
</div>
