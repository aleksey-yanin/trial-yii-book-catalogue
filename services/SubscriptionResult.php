<?php

declare(strict_types=1);

namespace app\services;

use JsonSerializable;

/**
 * Итог попытки оформить подписку.
 *
 * Отдельный тип, а не массив: контроллер возвращает его как есть, и структура ответа
 * описана в одном месте, а не собирается заново в каждом экшене.
 */
final class SubscriptionResult implements JsonSerializable
{
    /**
     * Конструктор закрыт: `new SubscriptionResult(false, $text)` читается как случайный
     * порядок аргументов, а failure($text) — как намерение.
     */
    private function __construct(
        public readonly bool $success,
        public readonly string $message,
    ) {
    }

    public static function success(string $message): self
    {
        return new self(true, $message);
    }

    public static function failure(string $message): self
    {
        return new self(false, $message);
    }

    /**
     * Порядок ключей менять нельзя: клиентский скрипт и функциональные тесты читают
     * тело ответа как есть.
     *
     * @return array{success: bool, message: string}
     */
    public function jsonSerialize(): array
    {
        return [
            'success' => $this->success,
            'message' => $this->message,
        ];
    }
}
