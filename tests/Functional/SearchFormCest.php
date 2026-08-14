<?php

declare(strict_types=1);

namespace app\tests\Functional;

use app\models\Author;
use app\models\Book;
use app\tests\Support\FunctionalTester;
use yii\helpers\Url;

/**
 * Форма поиска отправляется методом GET, поэтому значения фильтров приходят строками
 * и могут быть подделаны. Здесь проверяется весь путь запроса — вместе с отрисовкой
 * формы, на которой падало приведение массива к строке.
 */
final class SearchFormCest
{
    public function submitEmptyBookFilters(FunctionalTester $I): void
    {
        $this->createBook();

        // Ровно то, что отправляет кнопка «Найти» без заполненных полей.
        $I->amOnPage(Url::to([
            '/book/index',
            'BookSearch' => ['title' => '', 'year' => '', 'authorId' => ''],
        ]));

        $I->seeResponseCodeIs(200);
        $I->see('Солярис');
    }

    public function filterBooksByAuthor(FunctionalTester $I): void
    {
        $author = $this->createAuthor();
        $this->createBook([$author->id]);

        $I->amOnPage(Url::to(['/book/index', 'BookSearch' => ['authorId' => $author->id]]));

        $I->seeResponseCodeIs(200);
        $I->see('Солярис');
    }

    public function bookFiltersSurviveArrayInjection(FunctionalTester $I): void
    {
        $this->createBook();

        $I->amOnPage(Url::to([
            '/book/index',
            'BookSearch' => ['title' => ['x'], 'authorId' => ['1']],
        ]));

        $I->seeResponseCodeIs(200);
    }

    public function submitEmptyAuthorFilter(FunctionalTester $I): void
    {
        $this->createAuthor();

        $I->amOnPage(Url::to(['/author/index', 'AuthorSearch' => ['name' => '']]));

        $I->seeResponseCodeIs(200);
        $I->see('Лем');
    }

    public function authorFilterSurvivesArrayInjection(FunctionalTester $I): void
    {
        $this->createAuthor();

        $I->amOnPage(Url::to(['/author/index', 'AuthorSearch' => ['name' => ['Лем']]]));

        $I->seeResponseCodeIs(200);
    }

    /**
     * @param int[] $authorIds
     */
    private function createBook(array $authorIds = []): Book
    {
        $book = new Book([
            'title' => 'Солярис',
            'year' => 1961,
            'isbn' => '9785171183660',
            'authorIds' => $authorIds,
        ]);
        $book->save();

        return $book;
    }

    private function createAuthor(): Author
    {
        $author = new Author(['last_name' => 'Лем', 'first_name' => 'Станислав']);
        $author->save();

        return $author;
    }
}
