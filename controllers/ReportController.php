<?php

declare(strict_types=1);

namespace app\controllers;

use app\models\TopAuthorsReport;
use yii\web\Controller;

/**
 * Отчёты каталога.
 *
 * Фильтра доступа здесь намеренно нет: по ТЗ отчёт открыт всем, включая гостей.
 * В контроллерах книг и авторов AccessControl стоит именно потому, что там есть что закрывать.
 */
final class ReportController extends Controller
{
    public function actionTopAuthors(): string
    {
        $model = new TopAuthorsReport();
        $model->load($this->request->queryParams);

        return $this->render('top-authors', [
            'model' => $model,
            'rows' => $model->rows(),
            'years' => TopAuthorsReport::yearOptions(),
        ]);
    }
}
