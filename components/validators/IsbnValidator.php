<?php

declare(strict_types=1);

namespace app\components\validators;

use yii\validators\Validator;

/**
 * Проверяет ISBN-10 или ISBN-13 вместе с контрольной цифрой и приводит значение
 * к каноническому виду.
 *
 * Проверка регулярным выражением пропустила бы опечатку в любой цифре — контрольная
 * сумма ловит такие ошибки. Нормализация нужна для уникальности: «978-5-17-118366-8»
 * и «9785171183668» — один и тот же ISBN, и в базе они должны совпадать посимвольно.
 */
final class IsbnValidator extends Validator
{
    public function validateAttribute($model, $attribute): void
    {
        $value = $model->$attribute;

        if (!is_string($value)) {
            $this->addError($model, $attribute, 'ISBN должен быть строкой.');

            return;
        }

        $normalized = self::normalize($value);

        $isValid = match (strlen($normalized)) {
            10 => self::isValidIsbn10($normalized),
            13 => self::isValidIsbn13($normalized),
            default => false,
        };

        if (!$isValid) {
            $this->addError($model, $attribute, 'Значение «{attribute}» не является корректным ISBN.');

            return;
        }

        // Дальше по цепочке (в том числе в правило unique и в базу) уходит канонический вид.
        $model->$attribute = $normalized;
    }

    /**
     * Убирает разделители и приводит контрольный символ ISBN-10 к верхнему регистру.
     */
    public static function normalize(string $value): string
    {
        return strtoupper(str_replace([' ', '-', '—', '–'], '', trim($value)));
    }

    /**
     * ISBN-10: сумма цифр с весами 10…1 кратна 11, последний символ может быть X (=10).
     */
    private static function isValidIsbn10(string $isbn): bool
    {
        if (preg_match('/^\d{9}[\dX]$/', $isbn) !== 1) {
            return false;
        }

        $sum = 0;
        for ($i = 0; $i < 10; $i++) {
            $char = $isbn[$i];
            $digit = $char === 'X' ? 10 : (int) $char;
            $sum += $digit * (10 - $i);
        }

        return $sum % 11 === 0;
    }

    /**
     * ISBN-13: сумма цифр с чередующимися весами 1 и 3 кратна 10.
     */
    private static function isValidIsbn13(string $isbn): bool
    {
        if (preg_match('/^\d{13}$/', $isbn) !== 1) {
            return false;
        }

        $sum = 0;
        for ($i = 0; $i < 13; $i++) {
            $sum += (int) $isbn[$i] * ($i % 2 === 0 ? 1 : 3);
        }

        return $sum % 10 === 0;
    }
}
