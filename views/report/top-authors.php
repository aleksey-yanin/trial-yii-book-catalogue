<?php

declare(strict_types=1);

use app\models\TopAuthorsReport;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use yii\widgets\ActiveForm;

/** @var yii\web\View $this */
/** @var TopAuthorsReport $model */
/** @var array<int, array{authorId: int, fullName: string, booksCount: int}> $rows */

$this->title = 'ТОП-' . TopAuthorsReport::LIMIT . ' авторов за год';
$this->params['breadcrumbs'][] = $this->title;

$years = ArrayHelper::map(
    TopAuthorsReport::availableYears(),
    static fn (int $year): int => $year,
    static fn (int $year): string => (string) $year,
);
?>
<div class="report-top-authors">
    <h1><?= Html::encode($this->title) ?></h1>

    <p class="text-body-secondary">
        Книга засчитывается каждому из своих авторов.
    </p>

    <?php if ($years === []): ?>
        <p class="alert alert-info">В каталоге пока нет книг, поэтому отчёт строить не по чему.</p>
    <?php else: ?>
        <?php $form = ActiveForm::begin(['method' => 'get', 'action' => ['top-authors']]) ?>
            <div class="row g-2 align-items-end mb-3">
                <div class="col-md-4">
                    <?= $form->field($model, 'year')->dropDownList($years) ?>
                </div>
                <div class="col-md-4 mb-3">
                    <?= Html::submitButton('Показать', ['class' => 'btn btn-primary']) ?>
                </div>
            </div>
        <?php ActiveForm::end() ?>

        <?php if ($rows === []): ?>
            <p class="alert alert-warning">
                <?= $model->hasErrors('year')
                    ? 'Год указан неверно.'
                    : 'За выбранный год в каталоге нет книг.' ?>
            </p>
        <?php else: ?>
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th style="width: 60px">Место</th>
                        <th>Автор</th>
                        <th style="width: 120px">Книг за год</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $place => $row): ?>
                        <tr>
                            <td><?= $place + 1 ?></td>
                            <td>
                                <?= Html::a(
                                    Html::encode($row['fullName']),
                                    ['/author/view', 'id' => $row['authorId']],
                                ) ?>
                            </td>
                            <td><?= $row['booksCount'] ?></td>
                        </tr>
                    <?php endforeach ?>
                </tbody>
            </table>
        <?php endif ?>
    <?php endif ?>
</div>
