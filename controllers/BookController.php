<?php

declare(strict_types=1);

namespace app\controllers;

use app\components\controllers\CatalogueController;
use app\models\Author;
use app\models\Book;
use app\models\BookSearch;
use app\services\BookService;
use yii\web\Response;
use yii\web\UploadedFile;

/**
 * Каталог книг: просмотр, добавление, редактирование и удаление.
 *
 * Порядок сохранения, работа с файлом обложки и постановка уведомлений живут
 * в BookService — контроллер только связывает запрос с моделью и выбирает, куда идти дальше.
 */
final class BookController extends CatalogueController
{
    public function __construct(
        $id,
        $module,
        private readonly BookService $books,
        $config = [],
    ) {
        parent::__construct($id, $module, $config);
    }

    public function actionIndex(): string
    {
        $searchModel = new BookSearch();
        $dataProvider = $searchModel->search($this->request->queryParams);

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
            'authors' => Author::optionList(),
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
        $model = new Book();

        if ($this->loadFromRequest($model) && $this->books->create($model)) {
            return $this->redirect(['view', 'id' => $model->id]);
        }

        return $this->render('create', [
            'model' => $model,
            'authors' => Author::optionList(),
        ]);
    }

    public function actionUpdate(int $id): Response|string
    {
        $model = $this->findModel($id);

        if ($this->loadFromRequest($model) && $this->books->update($model)) {
            return $this->redirect(['view', 'id' => $model->id]);
        }

        return $this->render('update', [
            'model' => $model,
            'authors' => Author::optionList(),
        ]);
    }

    public function actionDelete(int $id): Response
    {
        $this->books->delete($this->findModel($id));

        return $this->redirect(['index']);
    }

    /**
     * Переносит данные запроса в модель. Файл достаётся отдельно: в $_POST его нет,
     * браузер кладёт загрузку в $_FILES.
     */
    private function loadFromRequest(Book $model): bool
    {
        if (!$model->load($this->request->post())) {
            return false;
        }

        $model->coverFile = UploadedFile::getInstance($model, 'coverFile');

        return true;
    }

    private function findModel(int $id): Book
    {
        return $this->findOrFail(Book::class, $id, 'Книга не найдена.');
    }
}
