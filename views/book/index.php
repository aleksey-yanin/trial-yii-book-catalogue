<?php

declare(strict_types=1);

use app\models\Author;
use app\models\Book;
use app\components\specifications\UserCanEdit;
use app\models\BookSearch;
use yii\data\ActiveDataProvider;
use yii\grid\ActionColumn;
use yii\grid\GridView;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use yii\widgets\ActiveForm;

/** @var yii\web\View $this */
/** @var BookSearch $searchModel */
/** @var ActiveDataProvider $dataProvider */

$this->title = 'Книги';
$this->params['breadcrumbs'][] = $this->title;

$canEdit = (new UserCanEdit())->isSatisfiedByCurrentUser();

$authors = ArrayHelper::map(
    Author::find()->orderBy(['last_name' => SORT_ASC, 'first_name' => SORT_ASC])->all(),
    'id',
    fn (Author $author): string => $author->fullName,
);
?>
<div class="book-index">
    <h1><?= Html::encode($this->title) ?></h1>

    <?php if ($canEdit): ?>
        <p><?= Html::a('Добавить книгу', ['create'], ['class' => 'btn btn-success']) ?></p>
    <?php endif ?>

    <?php $form = ActiveForm::begin(['method' => 'get', 'action' => ['index']]) ?>
        <div class="row g-2 align-items-end mb-3">
            <div class="col-md-4">
                <?= $form->field($searchModel, 'title')->textInput(['placeholder' => 'Часть названия']) ?>
            </div>
            <div class="col-md-2">
                <?= $form->field($searchModel, 'year')->textInput(['type' => 'number']) ?>
            </div>
            <div class="col-md-4">
                <?= $form->field($searchModel, 'authorId')
                    ->dropDownList($authors, ['prompt' => 'Любой автор']) ?>
            </div>
            <div class="col-md-2 mb-3">
                <?= Html::submitButton('Найти', ['class' => 'btn btn-primary']) ?>
                <?= Html::a('Сбросить', ['index'], ['class' => 'btn btn-outline-secondary']) ?>
            </div>
        </div>
    <?php ActiveForm::end() ?>

    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        // Фильтры вынесены в форму выше, поэтому строка фильтров в шапке не нужна.
        'columns' => [
            [
                'attribute' => 'title',
                'value' => fn (Book $book): string => $book->title,
            ],
            [
                'attribute' => 'year',
                'contentOptions' => ['style' => 'width: 100px'],
            ],
            'isbn',
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
                'class' => ActionColumn::class,
                // Гостю доступен только просмотр — набор кнопок задаёт та же спецификация.
                'template' => $canEdit ? '{view} {update} {delete}' : '{view}',
                'urlCreator' => fn (string $action, Book $model): string => (string) yii\helpers\Url::to(
                    [$action, 'id' => $model->id],
                ),
            ],
        ],
    ]) ?>
</div>
