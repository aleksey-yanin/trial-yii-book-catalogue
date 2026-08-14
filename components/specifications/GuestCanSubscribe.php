<?php

declare(strict_types=1);

namespace app\components\specifications;

use Yii;
use app\models\User;

/**
 * Право подписаться на новые книги автора.
 *
 * По ТЗ подписка — возможность именно гостя: аутентифицированный пользователь управляет
 * каталогом, а уведомления по SMS предназначены посетителям без учётной записи.
 * Поэтому спецификация выполняется ровно в обратном случае к UserCanEdit.
 */
final class GuestCanSubscribe implements SpecificationInterface
{
    public function isSatisfiedBy(mixed $candidate): bool
    {
        // Гость приходит сюда как null — Yii::$app->user->identity незалогиненного посетителя.
        return !$candidate instanceof User;
    }

    /**
     * Проверка для текущего посетителя — то, что нужно представлениям и фильтрам доступа.
     */
    public function isSatisfiedByCurrentUser(): bool
    {
        return $this->isSatisfiedBy(Yii::$app->user->identity);
    }
}
