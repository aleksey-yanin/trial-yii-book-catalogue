<?php

declare(strict_types=1);

namespace app\components\controllers;

use app\components\specifications\UserCanEdit;
use yii\db\ActiveRecord;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\NotFoundHttpException;

/**
 * Общая обвязка контроллеров каталога: правила доступа и поиск записи.
 *
 * Класс лежит вне app\controllers намеренно: Module::createControllerByID() проверяет только
 * наследование от Controller, но не abstract, поэтому в пространстве имён контроллеров
 * маршрут /catalogue/index попытался бы создать этот класс и отдал бы 500 вместо 404.
 */
abstract class CatalogueController extends Controller
{
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
                    // Удаление по GET позволило бы стереть запись простой ссылкой.
                    'delete' => ['post'],
                ],
            ],
        ];
    }

    /**
     * Запись каталога по идентификатору; отсутствие — 404, а не пустая страница.
     *
     * Класс и сообщение передаются параметрами, а не задаются абстрактными методами:
     * различаться должны только они, а сама проверка — одна на все контроллеры каталога.
     *
     * @template T of ActiveRecord
     * @param class-string<T> $class
     * @return T
     * @throws NotFoundHttpException
     */
    protected function findOrFail(string $class, int $id, string $message): ActiveRecord
    {
        $model = $class::findOne($id);

        if ($model === null) {
            throw new NotFoundHttpException($message);
        }

        return $model;
    }
}
