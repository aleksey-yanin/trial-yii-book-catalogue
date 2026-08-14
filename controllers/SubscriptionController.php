<?php

declare(strict_types=1);

namespace app\controllers;

use app\components\specifications\GuestCanSubscribe;
use app\models\Subscription;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\Response;

/**
 * Подписка гостя на новые книги автора.
 *
 * Отвечает JSON: форма живёт в модальном окне и отправляется через fetch,
 * перезагрузка страницы не нужна.
 */
class SubscriptionController extends Controller
{
    public function behaviors(): array
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    [
                        'actions' => ['create'],
                        'allow' => true,
                        // Та же спецификация прячет кнопку в представлениях: подписка —
                        // возможность гостя, и сокрытие кнопки не должно быть единственной защитой.
                        'matchCallback' => static fn (): bool => (new GuestCanSubscribe())
                            ->isSatisfiedByCurrentUser(),
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'create' => ['post'],
                ],
            ],
        ];
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function actionCreate(): array
    {
        $this->response->format = Response::FORMAT_JSON;

        $model = new Subscription();
        $model->load($this->request->post());

        if ($model->save()) {
            return ['success' => true, 'message' => 'Подписка оформлена!'];
        }

        return [
            'success' => false,
            // Первая ошибка понятнее пользователю, чем перечисление всех сразу.
            'message' => $model->getFirstErrors()[array_key_first($model->getFirstErrors())]
                ?? 'Не удалось оформить подписку.',
        ];
    }
}
