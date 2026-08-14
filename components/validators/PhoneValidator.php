<?php

declare(strict_types=1);

namespace app\components\validators;

use yii\validators\Validator;

/**
 * Проверяет номер телефона и приводит его к каноническому виду — только цифры.
 *
 * Нормализация нужна для уникальности подписки: «+7 (999) 123-45-67», «8 999 1234567»
 * и «79991234567» — один и тот же номер, и без приведения к общему виду один человек
 * подписался бы на автора трижды. По той же причине нормализуется ISBN
 * (см. IsbnValidator).
 */
final class PhoneValidator extends Validator
{
    /**
     * Международный формат: минимум 10 цифр (национальный номер), максимум 15 по E.164.
     */
    public const MIN_DIGITS = 10;
    public const MAX_DIGITS = 15;

    public function validateAttribute($model, $attribute): void
    {
        $value = $model->$attribute;

        if (!is_scalar($value)) {
            $this->addError($model, $attribute, 'Номер телефона указан неверно.');

            return;
        }

        $normalized = self::normalize((string) $value);
        $length = strlen($normalized);

        if ($length < self::MIN_DIGITS || $length > self::MAX_DIGITS) {
            $this->addError(
                $model,
                $attribute,
                'Номер телефона должен содержать от ' . self::MIN_DIGITS . ' до ' . self::MAX_DIGITS . ' цифр.',
            );

            return;
        }

        // Дальше по цепочке — в том числе в правило unique и в базу — уходит канонический вид.
        $model->$attribute = $normalized;
    }

    /**
     * Оставляет одни цифры и приводит российский номер с ведущей восьмёркой к виду с семёркой.
     */
    public static function normalize(string $value): string
    {
        $digits = preg_replace('/\D+/', '', $value) ?? '';

        // 8 999… и +7 999… — один и тот же номер; без этого он попал бы в базу дважды.
        if (strlen($digits) === 11 && str_starts_with($digits, '8')) {
            $digits = '7' . substr($digits, 1);
        }

        return $digits;
    }
}
