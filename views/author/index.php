<?php

declare(strict_types=1);

use app\models\Author;
use app\components\specifications\UserCanEdit;
use app\models\AuthorSearch;
use yii\data\ActiveDataProvider;
use yii\grid\ActionColumn;
use yii\grid\GridView;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\widgets\ActiveForm;

/** @var yii\web\View $this */
/** @var AuthorSearch $searchModel */
/** @var ActiveDataProvider $dataProvider */

$canEdit = (new UserCanEdit())->isSatisfiedByCurrentUser();

$this->title = 'Авторы';
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="author-index">
    <h1><?= Html::encode($this->title) ?></h1>

    <?php if ($canEdit): ?>
        <p><?= Html::a('Добавить автора', ['create'], ['class' => 'btn btn-success']) ?></p>
    <?php endif ?>

    <?php $form = ActiveForm::begin(['method' => 'get', 'action' => ['index']]) ?>
        <div class="row g-2 align-items-end mb-3">
            <div class="col-md-6">
                <?= $form->field($searchModel, 'name')
                    ->textInput(['placeholder' => 'Фамилия, имя или отчество']) ?>
            </div>
            <div class="col-md-6 mb-3">
                <?= Html::submitButton('Найти', ['class' => 'btn btn-primary']) ?>
                <?= Html::a('Сбросить', ['index'], ['class' => 'btn btn-outline-secondary']) ?>
            </div>
        </div>
    <?php ActiveForm::end() ?>

    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'columns' => [
            'last_name',
            'first_name',
            [
                'attribute' => 'middle_name',
                'value' => fn (Author $author): string => $author->middle_name ?? '—',
            ],
            [
                'label' => 'Книг',
                'contentOptions' => ['style' => 'width: 80px'],
                'value' => fn (Author $author): int => (int) $author->booksCount,
            ],
            [
                'class' => ActionColumn::class,
                'template' => $canEdit ? '{view} {update} {delete}' : '{view}',
                'urlCreator' => fn (string $action, Author $model): string => (string) Url::to(
                    [$action, 'id' => $model->id],
                ),
            ],
        ],
    ]) ?>
</div>
