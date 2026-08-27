<?php

declare(strict_types=1);

namespace app\controllers;

use app\components\specifications\GuestCanSubscribe;
use app\services\SubscriptionResult;
use app\services\SubscriptionService;
use yii\filters\AccessControl;
use yii\filters\ContentNegotiator;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\Response;

/**
 * Подписка гостя на новые книги автора.
 *
 * Отвечает JSON: форма живёт в модальном окне и отправляется через fetch,
 * перезагрузка страницы не нужна.
 */
final class SubscriptionController extends Controller
{
    public function __construct(
        $id,
        $module,
        private readonly SubscriptionService $subscriptions,
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
            // Формат объявляется фильтром, а не присваиванием внутри экшена: экшен возвращает
            // результат операции и о транспорте не знает. Фильтр идёт после verbs намеренно —
            // они срабатывают в порядке объявления, и запрет GET (405) обязан отработать
            // раньше согласования формата ответа.
            'contentNegotiator' => [
                'class' => ContentNegotiator::class,
                'formats' => ['application/json' => Response::FORMAT_JSON],
            ],
        ];
    }

    public function actionCreate(): SubscriptionResult
    {
        return $this->subscriptions->subscribe($this->request->post());
    }
}
