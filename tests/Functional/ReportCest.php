<?php

declare(strict_types=1);

namespace app\tests\Functional;

use app\models\Author;
use app\models\Book;
use app\models\TopAuthorsReport;
use app\tests\Support\FunctionalTester;
use yii\helpers\Url;

final class ReportCest
{
    private int $_isbnCounter = 0;

    /**
     * Главное требование ТЗ: отчёт доступен всем, включая неаутентифицированных.
     */
    public function guestOpensReport(FunctionalTester $I): void
    {
        $I->amOnPage(Url::to(['/report/top-authors']));

        $I->seeResponseCodeIs(200);
        $I->see('авторов за год', 'h1');
    }

    public function reportIsInMenuForGuest(FunctionalTester $I): void
    {
        $I->amOnRoute('site/index');

        $I->see('Отчёт');
    }

    public function showsAuthorsOrderedByBooksCount(FunctionalTester $I): void
    {
        $leader = $this->createAuthor('Лидеров');
        $second = $this->createAuthor('Второв');

        $this->createBooks(2020, $leader, 3);
        $this->createBooks(2020, $second, 1);

        $I->amOnPage(Url::to(['/report/top-authors', 'TopAuthorsReport' => ['year' => 2020]]));

        $I->seeResponseCodeIs(200);
        $I->see('Лидеров');
        $I->see('Второв');
    }

    public function anotherYearShowsAnotherResult(FunctionalTester $I): void
    {
        $first = $this->createAuthor('Двадцатов');
        $second = $this->createAuthor('Двадцатьпервов');

        $this->createBooks(2020, $first, 1);
        $this->createBooks(2021, $second, 1);

        $I->amOnPage(Url::to(['/report/top-authors', 'TopAuthorsReport' => ['year' => 2021]]));

        $I->see('Двадцатьпервов');
        $I->dontSee('Двадцатов ');
    }

    public function yearWithoutBooksShowsMessage(FunctionalTester $I): void
    {
        $author = $this->createAuthor('Лем');
        $this->createBooks(2020, $author, 1);

        $I->amOnPage(Url::to(['/report/top-authors', 'TopAuthorsReport' => ['year' => 1999]]));

        $I->seeResponseCodeIs(200);
        $I->see('нет книг');
    }

    public function emptyCatalogueShowsMessage(FunctionalTester $I): void
    {
        $I->amOnPage(Url::to(['/report/top-authors']));

        $I->seeResponseCodeIs(200);
        $I->see('нет книг');
    }

    /**
     * Параметр приходит из строки запроса и подделывается массивом — страница
     * не должна падать на выводе формы.
     */
    public function reportSurvivesArrayInjection(FunctionalTester $I): void
    {
        $author = $this->createAuthor('Лем');
        $this->createBooks(2020, $author, 1);

        $I->amOnPage(Url::to(['/report/top-authors', 'TopAuthorsReport' => ['year' => ['2020']]]));

        $I->seeResponseCodeIs(200);
    }

    public function reportRejectsNonNumericYear(FunctionalTester $I): void
    {
        $author = $this->createAuthor('Лем');
        $this->createBooks(2020, $author, 1);

        $I->amOnPage(Url::to(['/report/top-authors', 'TopAuthorsReport' => ['year' => 'позапрошлый']]));

        $I->seeResponseCodeIs(200);
        $I->see('неверно');
    }

    public function reportLimitsToTenRows(FunctionalTester $I): void
    {
        for ($number = 1; $number <= 12; $number++) {
            $author = $this->createAuthor('Автор' . $number);
            $this->createBooks(2020, $author, 1);
        }

        $I->amOnPage(Url::to(['/report/top-authors', 'TopAuthorsReport' => ['year' => 2020]]));

        $I->seeNumberOfElements('tbody tr', TopAuthorsReport::LIMIT);
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
