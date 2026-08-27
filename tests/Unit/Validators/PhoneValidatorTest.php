<?php

declare(strict_types=1);

namespace app\tests\Unit\Validators;

use app\components\validators\PhoneValidator;
use yii\base\DynamicModel;

final class PhoneValidatorTest extends \Codeception\Test\Unit
{
    /**
     * @dataProvider validPhoneProvider
     */
    public function testAcceptsValidPhone(string $phone): void
    {
        verify($this->validate($phone)->hasErrors('phone'))->false();
    }

    public static function validPhoneProvider(): array
    {
        return [
            'только цифры' => ['79991234567'],
            'с плюсом' => ['+79991234567'],
            'со скобками и дефисами' => ['+7 (999) 123-45-67'],
            'с восьмёркой' => ['89991234567'],
            'десять цифр' => ['9991234567'],
            'международный длинный' => ['+380 44 123 45 67'],
        ];
    }

    /**
     * @dataProvider invalidPhoneProvider
     */
    public function testRejectsInvalidPhone(string $phone): void
    {
        verify($this->validate($phone)->hasErrors('phone'))->true();
    }

    public static function invalidPhoneProvider(): array
    {
        return [
            'слишком короткий' => ['12345'],
            'девять цифр' => ['999123456'],
            'слишком длинный' => ['1234567890123456'],
            'буквы' => ['телефон'],
        ];
    }

    /**
     * Номер сохраняется в каноническом виде — иначе уникальность подписки не защитила бы
     * от повторной записи того же номера в другом написании.
     */
    public function testNormalizesValueAfterValidation(): void
    {
        verify($this->validate('+7 (999) 123-45-67')->phone)->equals('79991234567');
    }

    /**
     * 8 999… и +7 999… — один и тот же номер.
     */
    public function testConvertsLeadingEightToSeven(): void
    {
        verify($this->validate('8 999 123 45 67')->phone)->equals('79991234567');
    }

    /**
     * Восьмёрка заменяется только у одиннадцатизначного номера: в номерах другой длины
     * она может быть значащей цифрой.
     */
    public function testKeepsLeadingEightInOtherLengths(): void
    {
        verify($this->validate('8612345678')->phone)->equals('8612345678');
    }

    /**
     * За обязательность отвечает правило required, а не валидатор формата.
     */
    public function testSkipsEmptyValue(): void
    {
        verify($this->validate('')->hasErrors('phone'))->false();
    }

    public function testRejectsNonScalarValue(): void
    {
        verify($this->validate(['79991234567'])->hasErrors('phone'))->true();
    }

    /**
     * Телефон подписчика попадает в лог при неудачной отправке, и целиком его там
     * быть не должно: последних четырёх цифр хватает, чтобы узнать номер в списке.
     */
    public function testMaskKeepsOnlyLastFourDigits(): void
    {
        verify(PhoneValidator::mask('+7 (999) 123-45-67'))->equals('*******4567');
    }

    public function testMaskHidesShortNumberEntirely(): void
    {
        verify(PhoneValidator::mask('1234'))->equals('****');
    }

    private function validate(mixed $phone): DynamicModel
    {
        $model = new DynamicModel(['phone' => $phone]);
        $model->addRule('phone', PhoneValidator::class);
        $model->validate();

        return $model;
    }
}
