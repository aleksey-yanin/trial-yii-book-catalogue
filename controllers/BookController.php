<?php

declare(strict_types=1);

namespace app\controllers;

use Yii;
use app\components\BookNotifier;
use app\components\CoverStorage;
use app\components\specifications\UserCanEdit;
use app\models\Book;
use app\models\BookSearch;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use yii\web\UploadedFile;

/**
 * Каталог книг: просмотр, добавление, редактирование и удаление.
 *
 * Права доступа здесь не разграничиваются — это делается на шаге 5.
 */
class BookController extends Controller
{
    public function __construct(
        $id,
        $module,
        private readonly CoverStorage $coverStorage,
        $config = [],
    ) {
        parent::__construct($id, $module, $config);
    }

    public function behaviors(): array
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    [
                        'actions' => ['index', 'view'],
                        'allow' => true,
                    ],
                    [
                        'actions' => ['create', 'update', 'delete'],
                        'allow' => true,
                        // Та же спецификация решает и показ кнопок в представлениях,
                        // поэтому запрет действия не может разойтись с интерфейсом.
                        'matchCallback' => static fn (): bool => (new UserCanEdit())
                            ->isSatisfiedByCurrentUser(),
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    // Удаление по GET позволило бы стереть книгу простой ссылкой.
                    'delete' => ['post'],
                ],
            ],
        ];
    }

    public function actionIndex(): string
    {
        $searchModel = new BookSearch();
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
        $model = new Book();

        if ($model->load($this->request->post())) {
            $model->coverFile = UploadedFile::getInstance($model, 'coverFile');

            if ($model->validate()) {
                if ($model->coverFile !== null) {
                    $model->cover_path = $this->coverStorage->save($model->coverFile);
                }

                // Валидация уже прошла — второй раз её гонять незачем.
                if ($model->save(false)) {
                    $this->notifySubscribers($model);

                    return $this->redirect(['view', 'id' => $model->id]);
                }
            }
        }

        return $this->render('create', ['model' => $model]);
    }

    public function actionUpdate(int $id): Response|string
    {
        $model = $this->findModel($id);
        $previousCover = $model->cover_path;

        if ($model->load($this->request->post())) {
            $model->coverFile = UploadedFile::getInstance($model, 'coverFile');

            if ($model->validate()) {
                if ($model->coverFile !== null) {
                    $model->cover_path = $this->coverStorage->save($model->coverFile);
                }

                if ($model->save(false)) {
                    // Старый файл убираем только после успешного сохранения записи.
                    if ($model->coverFile !== null && $previousCover !== $model->cover_path) {
                        $this->coverStorage->delete($previousCover);
                    }

                    return $this->redirect(['view', 'id' => $model->id]);
                }
            }
        }

        return $this->render('update', ['model' => $model]);
    }

    public function actionDelete(int $id): Response
    {
        $model = $this->findModel($id);
        $cover = $model->cover_path;

        $model->delete();
        // Связи в book_author уберёт внешний ключ с ON DELETE CASCADE.
        $this->coverStorage->delete($cover);

        return $this->redirect(['index']);
    }

    /**
     * Уведомления ставятся в очередь здесь, а не в Book::afterSave(): сидер демо-данных
     * создаёт полсотни книг и забил бы очередь заданиями, которые никому не нужны.
     */
    private function notifySubscribers(Book $book): void
    {
        /** @var BookNotifier $notifier */
        $notifier = Yii::$app->get('bookNotifier');
        $notifier->notifyAboutNewBook($book);
    }

    private function findModel(int $id): Book
    {
        $model = Book::findOne($id);

        if ($model === null) {
            throw new NotFoundHttpException('Книга не найдена.');
        }

        return $model;
    }
}
