<?php

declare(strict_types=1);

namespace app\services;

use app\models\Subscription;

/**
 * Оформление подписки гостя на новые книги автора.
 *
 * Операция отделена от контроллера, чтобы её можно было вызвать откуда угодно — из консоли,
 * из другого действия — не притаскивая с собой Response и формат ответа.
 */
final class SubscriptionService
{
    private const SUCCESS_MESSAGE = 'Подписка оформлена!';

    private const FAILURE_MESSAGE = 'Не удалось оформить подписку.';

    /**
     * @param array<string, mixed> $data Данные запроса целиком: имена полей разберёт load().
     */
    public function subscribe(array $data): SubscriptionResult
    {
        $subscription = new Subscription();
        $subscription->load($data);

        if ($subscription->save()) {
            return SubscriptionResult::success(self::SUCCESS_MESSAGE);
        }

        return SubscriptionResult::failure($this->firstError($subscription));
    }

    /**
     * Показываем первую ошибку, а не перечисление всех: в модальном окне одна внятная
     * фраза полезнее списка.
     */
    private function firstError(Subscription $subscription): string
    {
        $errors = $subscription->getFirstErrors();

        if ($errors === []) {
            return self::FAILURE_MESSAGE;
        }

        return (string) reset($errors);
    }
}
