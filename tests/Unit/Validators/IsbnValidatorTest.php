<?php

declare(strict_types=1);

namespace app\tests\Unit\Validators;

use app\components\validators\IsbnValidator;
use yii\base\DynamicModel;

final class IsbnValidatorTest extends \Codeception\Test\Unit
{
    /**
     * @dataProvider validIsbnProvider
     */
    public function testAcceptsValidIsbn(string $isbn): void
    {
        verify($this->validate($isbn)->hasErrors('isbn'))->false();
    }

    public static function validIsbnProvider(): array
    {
        return [
            'ISBN-13 без разделителей' => ['9785171183660'],
            'ISBN-13 с дефисами' => ['978-5-17-118366-0'],
            'ISBN-10 без разделителей' => ['5170624956'],
            'ISBN-10 с дефисами' => ['5-17-062495-6'],
            'ISBN-10 с контрольным X' => ['043942089X'],
            'ISBN-10 со строчным x' => ['043942089x'],
        ];
    }

    /**
     * @dataProvider invalidIsbnProvider
     */
    public function testRejectsInvalidIsbn(string $isbn): void
    {
        verify($this->validate($isbn)->hasErrors('isbn'))->true();
    }

    public static function invalidIsbnProvider(): array
    {
        return [
            'битая контрольная цифра ISBN-13' => ['9785171183661'],
            'битая контрольная цифра ISBN-10' => ['5170624957'],
            'слишком короткий' => ['12345'],
            'длина 12' => ['978517118366'],
            'X в середине ISBN-10' => ['0439X2089X'],
            'буквы' => ['девять-семь-восемь'],
        ];
    }

    /**
     * Пустое значение пропускается: за обязательность отвечает правило required,
     * а не валидатор формата. Иначе необязательное поле нельзя было бы оставить пустым.
     */
    public function testSkipsEmptyValue(): void
    {
        verify($this->validate('')->hasErrors('isbn'))->false();
    }

    /**
     * Значение сохраняется в каноническом виде — иначе уникальность ISBN
     * не защитила бы от дубля, записанного с другими разделителями.
     */
    public function testNormalizesValueAfterValidation(): void
    {
        $model = $this->validate('978-5-17-118366-0');

        verify($model->hasErrors('isbn'))->false();
        verify($model->isbn)->equals('9785171183660');
    }

    public function testUppercasesControlCharacter(): void
    {
        verify($this->validate('043942089x')->isbn)->equals('043942089X');
    }

    /**
     * Из формы приходит строка, но модель можно заполнить и программно —
     * валидатор не должен падать на неожиданном типе.
     */
    public function testRejectsNonStringValue(): void
    {
        verify($this->validate(9785171183660)->hasErrors('isbn'))->true();
    }

    private function validate(mixed $isbn): DynamicModel
    {
        $model = new DynamicModel(['isbn' => $isbn]);
        $model->addRule('isbn', IsbnValidator::class);
        $model->validate();

        return $model;
    }
}
