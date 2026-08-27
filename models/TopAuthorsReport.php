<?php

declare(strict_types=1);

namespace app\models;

use yii\base\Model;
use yii\db\Query;

/**
 * Отчёт «ТОП-10 авторов по числу книг за год».
 *
 * У книги может быть несколько авторов, и каждому из них она засчитывается:
 * соавторство — это участие в выпуске книги.
 */
class TopAuthorsReport extends Model
{
    public const LIMIT = 10;

    /**
     * Год, за который строится отчёт.
     *
     * Без строгого типа: значение приходит из строки запроса, где пустое поле —
     * это пустая строка, а не null, а сам параметр подделывается массивом
     * (см. модели поиска). Приводит его к строке правило filter.
     *
     * @var int|string|array<mixed>|null
     */
    public $year = null;

    public function rules(): array
    {
        return [
            ['year', 'filter', 'filter' => static fn (mixed $value): string => is_scalar($value)
                ? trim((string) $value)
                : ''],
            ['year', 'integer', 'min' => Book::MIN_YEAR, 'max' => Book::maxYear()],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'year' => 'Год выпуска',
        ];
    }

    /**
     * Строки отчёта: ФИО автора и число выпущенных им книг за выбранный год.
     *
     * @return array<int, array{authorId: int, fullName: string, booksCount: int}>
     */
    public function rows(): array
    {
        $year = $this->resolveYear();

        if ($year === null) {
            return [];
        }

        $rows = (new Query())
            ->select([
                'authorId' => 'a.id',
                'last_name' => 'a.last_name',
                'first_name' => 'a.first_name',
                'middle_name' => 'a.middle_name',
                'booksCount' => 'COUNT(ba.book_id)',
            ])
            ->from(['a' => '{{%author}}'])
            ->innerJoin(['ba' => '{{%book_author}}'], 'ba.author_id = a.id')
            ->innerJoin(['b' => '{{%book}}'], 'b.id = ba.book_id')
            ->where(['b.year' => $year])
            ->groupBy('a.id')
            // Вторая сортировка по фамилии: без неё авторы с равным числом книг
            // выстраивались бы в непредсказуемом порядке и отчёт «прыгал» бы между запросами.
            ->orderBy(['booksCount' => SORT_DESC, 'a.last_name' => SORT_ASC])
            ->limit(self::LIMIT)
            ->all();

        return array_map(static fn (array $row): array => [
            'authorId' => (int) $row['authorId'],
            'fullName' => implode(' ', array_filter([
                $row['last_name'],
                $row['first_name'],
                $row['middle_name'],
            ])),
            'booksCount' => (int) $row['booksCount'],
        ], $rows);
    }

    /**
     * Год отчёта: null означает, что строить нечего — либо каталог пуст, либо год задан неверно.
     * Что именно произошло, представление различает по hasErrors().
     */
    public function resolveYear(): ?int
    {
        // Правило filter приводит значение к строке, поэтому подделанный массив
        // сюда уже не доходит.
        $this->validate();

        if ((string) $this->year === '') {
            // Год подставляется в саму модель, чтобы выпадающий список открылся
            // на том же годе, за который построен отчёт.
            $this->year = self::defaultYear();

            return $this->year;
        }

        if ($this->hasErrors('year')) {
            return null;
        }

        return (int) $this->year;
    }

    /**
     * Годы, за которые в каталоге есть книги, — для выпадающего списка.
     *
     * @return int[]
     */
    public static function availableYears(): array
    {
        $years = (new Query())
            ->select('year')
            ->distinct()
            ->from('{{%book}}')
            ->orderBy(['year' => SORT_DESC])
            ->column();

        return array_map('intval', $years);
    }

    /**
     * Годы для выпадающего списка: значение → подпись.
     *
     * @return array<int, string>
     */
    public static function yearOptions(): array
    {
        $options = [];

        foreach (self::availableYears() as $year) {
            $options[$year] = (string) $year;
        }

        return $options;
    }

    /**
     * По умолчанию показывается самый свежий год каталога: отчёт за год, в котором
     * заведомо нет книг, бесполезен.
     */
    public static function defaultYear(): ?int
    {
        return self::availableYears()[0] ?? null;
    }
}
