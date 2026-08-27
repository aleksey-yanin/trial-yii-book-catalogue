<?php

declare(strict_types=1);

use app\models\Book;
use yii\helpers\Html;
use yii\widgets\ActiveForm;

/** @var yii\web\View $this */
/** @var Book $model */
/** @var array<int, string> $authors */

$coverUrl = Yii::$app->coverStorage->getUrl($model->cover_path);
?>
<div class="book-form">
    <?php $form = ActiveForm::begin([
        'id' => 'book-form',
        // Без multipart браузер отправит только имя файла, а не сам файл.
        'options' => ['enctype' => 'multipart/form-data'],
    ]) ?>

    <?= $form->field($model, 'title')->textInput(['maxlength' => true]) ?>

    <?= $form->field($model, 'year')->textInput([
        'type' => 'number',
        'min' => Book::MIN_YEAR,
        'max' => Book::maxYear(),
    ]) ?>

    <?= $form->field($model, 'isbn')->textInput([
        'maxlength' => 17,
        'placeholder' => '978-5-17-118366-0',
    ])->hint('ISBN-10 или ISBN-13; дефисы можно опустить') ?>

    <?= $form->field($model, 'description')->textarea(['rows' => 5]) ?>

    <?= $form->field($model, 'authorIds')->listBox($authors, [
        'multiple' => true,
        'size' => 8,
    ])->hint('Несколько авторов выбираются с Ctrl или Shift') ?>

    <?php if ($coverUrl !== null): ?>
        <div class="mb-3">
            <p class="mb-1">Текущая обложка:</p>
            <?= Html::img($coverUrl, ['alt' => 'Обложка', 'style' => 'max-height: 200px']) ?>
        </div>
    <?php endif ?>

    <?= $form->field($model, 'coverFile')->fileInput([
        'accept' => 'image/png,image/jpeg,image/webp',
    ])->hint('PNG, JPEG или WebP, не больше 5 МБ') ?>

    <div class="form-group">
        <?= Html::submitButton('Сохранить', ['class' => 'btn btn-success']) ?>
        <?= Html::a('Отмена', ['index'], ['class' => 'btn btn-outline-secondary']) ?>
    </div>

    <?php ActiveForm::end() ?>
</div>
