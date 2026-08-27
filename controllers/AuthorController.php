<?php

declare(strict_types=1);

namespace app\controllers;

use app\components\controllers\CatalogueController;
use app\models\Author;
use app\models\AuthorSearch;
use yii\web\Response;

/**
 * Авторы каталога.
 *
 * Своего сервиса у автора нет и не нужно: ни файлов, ни очереди, ни порядка операций —
 * load() && save() и есть вся операция целиком.
 */
final class AuthorController extends CatalogueController
{
    public function actionIndex(): string
    {
        $searchModel = new AuthorSearch();
        $dataProvider = $searchModel->search($this->request->queryParams);

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    public function actionView(int $id): string
    {
        $model = $this->findModel($id);

        return $this->render('view', [
            'model' => $model,
            'books' => $model->booksProvider(),
        ]);
    }

    public function actionCreate(): Response|string
    {
        $model = new Author();

        if ($model->load($this->request->post()) && $model->save()) {
            return $this->redirect(['view', 'id' => $model->id]);
        }

        return $this->render('create', ['model' => $model]);
    }

    public function actionUpdate(int $id): Response|string
    {
        $model = $this->findModel($id);

        if ($model->load($this->request->post()) && $model->save()) {
            return $this->redirect(['view', 'id' => $model->id]);
        }

        return $this->render('update', ['model' => $model]);
    }

    public function actionDelete(int $id): Response
    {
        // Книги автора остаются в каталоге, удаляются только связи (ON DELETE CASCADE).
        $this->findModel($id)->delete();

        return $this->redirect(['index']);
    }

    private function findModel(int $id): Author
    {
        return $this->findOrFail(Author::class, $id, 'Автор не найден.');
    }
}
