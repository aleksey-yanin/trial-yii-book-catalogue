<?php

declare(strict_types=1);

namespace app\components;

use app\models\Author;
use app\models\Book;

/**
 * Наполняет каталог демонстрационными данными, чтобы отчёт «ТОП-10 авторов»
 * было на чём смотреть.
 *
 * Распределение задано явно, а не случайностью: отчёт должен выглядеть одинаково
 * при каждом запуске, иначе его нечем проверять.
 */
class DemoDataSeeder
{
    /**
     * Год, за который строится показательное распределение.
     *
     * Он же самый свежий год каталога: отчёт по умолчанию открывается на последнем году,
     * и посетитель сразу видит показательную картину, а не хвост из одиночных книг.
     */
    public const REPORT_YEAR = 2024;

    /**
     * Сколько книг за REPORT_YEAR должно оказаться у авторов с 1-го по 10-е место.
     */
    private const DISTRIBUTION = [8, 6, 5, 4, 3, 3, 2, 2, 1, 1];

    /**
     * Книги прочих лет: год => сколько книг раздать авторам по кругу.
     * Нужны, чтобы было видно, что отчёт фильтрует по году, а не считает весь каталог.
     */
    private const OTHER_YEARS = [2021 => 6, 2022 => 7, 2023 => 7];

    /**
     * Пары соавторов (индексы авторов): их совместные книги засчитываются обоим
     * и уже учтены в DISTRIBUTION.
     */
    private const CO_AUTHORED = [[0, 2], [1, 3]];

    private const LAST_NAMES = [
        'Стругацкий', 'Лем', 'Дик', 'Азимов', 'Брэдбери',
        'Кларк', 'Хайнлайн', 'Симмонс', 'Гибсон', 'Ле Гуин',
        'Желязны', 'Воннегут', 'Пелевин', 'Сапковский', 'Мьевиль',
    ];

    private const FIRST_NAMES = [
        'Аркадий', 'Станислав', 'Филип', 'Айзек', 'Рэй',
        'Артур', 'Роберт', 'Дэн', 'Уильям', 'Урсула',
        'Роджер', 'Курт', 'Виктор', 'Анджей', 'Чайна',
    ];

    private int $_isbnCounter = 0;

    /**
     * @return array{authors: int, books: int}
     */
    public function seed(): array
    {
        $authors = $this->createAuthors();
        $books = $this->createReportYearBooks($authors) + $this->createOtherYearsBooks($authors);

        return ['authors' => count($authors), 'books' => $books];
    }

    /**
     * Есть ли в каталоге данные: сидер не должен затирать чужие книги.
     */
    public function catalogueIsEmpty(): bool
    {
        return !Book::find()->exists() && !Author::find()->exists();
    }

    public function clear(): void
    {
        // Связи в book_author уберёт внешний ключ с ON DELETE CASCADE.
        Book::deleteAll();
        Author::deleteAll();
    }

    /**
     * @return Author[]
     */
    private function createAuthors(): array
    {
        $authors = [];

        foreach (self::LAST_NAMES as $index => $lastName) {
            $author = new Author([
                'last_name' => $lastName,
                'first_name' => self::FIRST_NAMES[$index],
            ]);
            $author->save();

            $authors[] = $author;
        }

        return $authors;
    }

    /**
     * @param Author[] $authors
     */
    private function createReportYearBooks(array $authors): int
    {
        $remaining = self::DISTRIBUTION;
        $created = 0;

        // Сначала совместные книги: каждая уменьшает счётчик сразу двум авторам.
        foreach (self::CO_AUTHORED as $pair) {
            [$first, $second] = $pair;

            $this->createBook(
                sprintf('Совместная работа: %s и %s', $authors[$first]->last_name, $authors[$second]->last_name),
                self::REPORT_YEAR,
                [$authors[$first]->id, $authors[$second]->id],
            );

            $remaining[$first]--;
            $remaining[$second]--;
            $created++;
        }

        foreach ($remaining as $index => $count) {
            for ($number = 1; $number <= $count; $number++) {
                $this->createBook(
                    sprintf('%s: книга %d', $authors[$index]->last_name, $number),
                    self::REPORT_YEAR,
                    [$authors[$index]->id],
                );
                $created++;
            }
        }

        return $created;
    }

    /**
     * @param Author[] $authors
     */
    private function createOtherYearsBooks(array $authors): int
    {
        $created = 0;
        $authorIndex = 0;

        foreach (self::OTHER_YEARS as $year => $count) {
            for ($number = 1; $number <= $count; $number++) {
                $author = $authors[$authorIndex % count($authors)];
                $authorIndex++;

                $this->createBook(
                    sprintf('%s: издание %d года, №%d', $author->last_name, $year, $number),
                    $year,
                    [$author->id],
                );
                $created++;
            }
        }

        return $created;
    }

    /**
     * @param int[] $authorIds
     */
    private function createBook(string $title, int $year, array $authorIds): Book
    {
        $book = new Book([
            'title' => $title,
            'year' => $year,
            'isbn' => $this->nextIsbn(),
            'description' => 'Демонстрационная запись каталога.',
            'authorIds' => $authorIds,
        ]);

        // Сохраняем через модель, а не прямым insert: демо-данные обязаны проходить
        // те же правила, что и данные из формы.
        $book->save();

        return $book;
    }

    /**
     * Валидный ISBN-13 с настоящей контрольной цифрой — иначе IsbnValidator отклонит книгу.
     */
    private function nextIsbn(): string
    {
        $this->_isbnCounter++;
        $body = sprintf('978%09d', $this->_isbnCounter);

        $sum = 0;
        for ($position = 0; $position < 12; $position++) {
            $sum += (int) $body[$position] * ($position % 2 === 0 ? 1 : 3);
        }

        return $body . ((10 - $sum % 10) % 10);
    }
}
