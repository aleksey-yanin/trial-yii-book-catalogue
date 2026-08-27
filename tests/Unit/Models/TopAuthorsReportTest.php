<?php

declare(strict_types=1);

namespace app\tests\Unit\Models;

use app\models\Author;
use app\models\Book;
use app\models\TopAuthorsReport;

final class TopAuthorsReportTest extends \Codeception\Test\Unit
{
    private int $_isbnCounter = 0;

    public function testSortsByBooksCountDescending(): void
    {
        $lem = $this->createAuthor('Лем');
        $dick = $this->createAuthor('Дик');

        $this->createBooks(2020, $lem, 3);
        $this->createBooks(2020, $dick, 5);

        $rows = $this->report(2020);

        verify($rows[0]['fullName'])->stringContainsString('Дик');
        verify($rows[0]['booksCount'])->equals(5);
        verify($rows[1]['fullName'])->stringContainsString('Лем');
        verify($rows[1]['booksCount'])->equals(3);
    }

    public function testCountsOnlySelectedYear(): void
    {
        $lem = $this->createAuthor('Лем');

        $this->createBooks(2020, $lem, 2);
        $this->createBooks(2021, $lem, 7);

        $rows = $this->report(2020);

        verify($rows)->arrayCount(1);
        verify($rows[0]['booksCount'])->equals(2);
    }

    /**
     * Книга с несколькими авторами засчитывается каждому: соавторство — это участие
     * в выпуске книги.
     */
    public function testCountsCoAuthoredBookForEveryAuthor(): void
    {
        $first = $this->createAuthor('Стругацкий');
        $second = $this->createAuthor('Стругацкая');

        $book = new Book([
            'title' => 'Совместная',
            'year' => 2020,
            'isbn' => $this->nextIsbn(),
            'authorIds' => [$first->id, $second->id],
        ]);
        $book->save();

        $rows = $this->report(2020);

        verify($rows)->arrayCount(2);
        verify($rows[0]['booksCount'])->equals(1);
        verify($rows[1]['booksCount'])->equals(1);
    }

    public function testLimitsToTenAuthors(): void
    {
        for ($number = 1; $number <= 12; $number++) {
            $author = $this->createAuthor('Автор' . $number);
            $this->createBooks(2020, $author, 1);
        }

        verify($this->report(2020))->arrayCount(TopAuthorsReport::LIMIT);
    }

    public function testAuthorWithoutBooksInYearIsAbsent(): void
    {
        $lem = $this->createAuthor('Лем');
        $this->createAuthor('Без книг');
        $this->createBooks(2020, $lem, 1);

        $rows = $this->report(2020);

        verify($rows)->arrayCount(1);
        verify($rows[0]['fullName'])->stringContainsString('Лем');
    }

    public function testEmptyCatalogueGivesEmptyReport(): void
    {
        verify($this->report(2020))->arrayCount(0);
    }

    public function testYearWithoutBooksGivesEmptyReport(): void
    {
        $lem = $this->createAuthor('Лем');
        $this->createBooks(2020, $lem, 1);

        verify($this->report(1999))->arrayCount(0);
    }

    /**
     * Некорректный год должен давать пустой отчёт с ошибкой валидации,
     * а не падение и не полный список.
     */
    public function testInvalidYearIsRejected(): void
    {
        $lem = $this->createAuthor('Лем');
        $this->createBooks(2020, $lem, 1);

        $model = new TopAuthorsReport(['year' => 'позапрошлый']);

        verify($model->rows())->arrayCount(0);
        verify($model->hasErrors('year'))->true();
    }

    public function testYearOutOfRangeIsRejected(): void
    {
        $model = new TopAuthorsReport(['year' => Book::maxYear() + 1]);

        verify($model->rows())->arrayCount(0);
        verify($model->hasErrors('year'))->true();
    }

    /**
     * Значение из строки запроса можно подделать массивом.
     */
    public function testArrayYearIsRejected(): void
    {
        $model = new TopAuthorsReport();
        $model->year = ['2020'];

        verify($model->rows())->arrayCount(0);
    }

    public function testEmptyYearFallsBackToLatestYearOfCatalogue(): void
    {
        $lem = $this->createAuthor('Лем');
        $this->createBooks(2019, $lem, 1);
        $this->createBooks(2021, $lem, 2);

        $model = new TopAuthorsReport();
        $rows = $model->rows();

        // Самый свежий год каталога — 2021, и именно он подставляется в форму.
        verify((int) $model->year)->equals(2021);
        verify($rows[0]['booksCount'])->equals(2);
    }

    public function testAvailableYearsAreUniqueAndDescending(): void
    {
        $lem = $this->createAuthor('Лем');
        $this->createBooks(2019, $lem, 2);
        $this->createBooks(2021, $lem, 1);

        verify(TopAuthorsReport::availableYears())->equals([2021, 2019]);
    }

    public function testAvailableYearsOnEmptyCatalogue(): void
    {
        verify(TopAuthorsReport::availableYears())->equals([]);
        verify(TopAuthorsReport::defaultYear())->null();
    }

    /**
     * Выпадающий список получает пары значение → подпись; подписи строковые, иначе
     * dropDownList отрисует их иначе, чем ожидает представление.
     */
    public function testYearOptionsPairValueWithLabel(): void
    {
        $lem = $this->createAuthor('Лем');
        $this->createBooks(2019, $lem, 1);
        $this->createBooks(2021, $lem, 1);

        verify(TopAuthorsReport::yearOptions())->equals([2021 => '2021', 2019 => '2019']);
    }

    /**
     * @return array<int, array{authorId: int, fullName: string, booksCount: int}>
     */
    private function report(int $year): array
    {
        return (new TopAuthorsReport(['year' => $year]))->rows();
    }

    private function createAuthor(string $lastName): Author
    {
        $author = new Author(['last_name' => $lastName, 'first_name' => 'Имя']);
        $author->save();

        return $author;
    }

    private function createBooks(int $year, Author $author, int $count): void
    {
        for ($number = 1; $number <= $count; $number++) {
            $book = new Book([
                'title' => sprintf('%s %d/%d', $author->last_name, $year, $number),
                'year' => $year,
                'isbn' => $this->nextIsbn(),
                'authorIds' => [$author->id],
            ]);
            $book->save();
        }
    }

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
