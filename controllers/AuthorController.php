<?php

declare(strict_types=1);

namespace app\controllers;

use app\models\Author;
use app\models\AuthorSearch;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Авторы каталога.
 *
 * Права доступа здесь не разграничиваются — это делается на шаге 5.
 */
class AuthorController extends Controller
{
    public function behaviors(): array
    {
        return [
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'delete' => ['post'],
                ],
            ],
        ];
    }

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
        return $this->render('view', [
            'model' => $this->findModel($id),
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
        $model = Author::findOne($id);

        if ($model === null) {
            throw new NotFoundHttpException('Автор не найден.');
        }

        return $model;
    }
}
