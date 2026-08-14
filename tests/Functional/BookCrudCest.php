<?php

declare(strict_types=1);

namespace app\tests\Functional;

use app\models\Author;
use app\models\Book;
use app\models\User;
use app\tests\Support\FunctionalTester;
use yii\helpers\Url;

final class BookCrudCest
{
    /**
     * CRUD доступен только авторизованному — вход выполняется перед каждым тестом.
     */
    public function _before(FunctionalTester $I): void
    {
        $user = new User(['username' => 'tester']);
        $user->setPassword('secret');
        $user->generateAuthKey();
        $user->save();

        $I->amLoggedInAs($user);
    }

    public function openIndex(FunctionalTester $I): void
    {
        $I->amOnRoute('book/index');
        $I->see('Книги', 'h1');
    }

    public function seeBookInList(FunctionalTester $I): void
    {
        $this->createBook();

        $I->amOnRoute('book/index');
        $I->see('Солярис');
    }

    public function openBookCard(FunctionalTester $I): void
    {
        $book = $this->createBook();

        $I->amOnRoute('book/view', ['id' => $book->id]);
        $I->see('Солярис', 'h1');
        $I->see('9785171183660');
    }

    public function missingBookGivesNotFound(FunctionalTester $I): void
    {
        $I->amOnRoute('book/view', ['id' => 999999]);
        $I->seeResponseCodeIs(404);
    }

    public function createBookWithSeveralAuthors(FunctionalTester $I): void
    {
        $first = $this->createAuthor('Стругацкий', 'Аркадий');
        $second = $this->createAuthor('Стругацкий', 'Борис');

        $I->amOnRoute('book/create');
        $I->submitForm('#book-form', [
            'Book[title]' => 'Пикник на обочине',
            'Book[year]' => '1972',
            'Book[isbn]' => '978-5-17-118366-0',
            'Book[description]' => 'Повесть',
            'Book[authorIds]' => [$first->id, $second->id],
        ]);

        $book = Book::find()->where(['title' => 'Пикник на обочине'])->one();

        $I->assertNotNull($book);
        // ISBN сохраняется нормализованным, без дефисов.
        $I->assertSame('9785171183660', $book->isbn);
        $I->assertCount(2, $book->authors);
    }

    public function emptyFormShowsErrors(FunctionalTester $I): void
    {
        $I->amOnRoute('book/create');
        $I->submitForm('#book-form', [
            'Book[title]' => '',
            'Book[year]' => '',
            'Book[isbn]' => '',
        ]);

        $I->see('Добавить книгу', 'h1');
        $I->see('Необходимо заполнить «Название».');
        $I->see('Необходимо заполнить «Год выпуска».');
        $I->see('Необходимо заполнить «ISBN».');
    }

    public function invalidIsbnIsRejected(FunctionalTester $I): void
    {
        $I->amOnRoute('book/create');
        $I->submitForm('#book-form', [
            'Book[title]' => 'Солярис',
            'Book[year]' => '1961',
            // Испорчена контрольная цифра.
            'Book[isbn]' => '9785171183661',
        ]);

        $I->see('не является корректным ISBN');
        $I->assertNull(Book::find()->where(['title' => 'Солярис'])->one());
    }

    public function updateBook(FunctionalTester $I): void
    {
        $book = $this->createBook();

        $I->amOnRoute('book/update', ['id' => $book->id]);
        $I->submitForm('#book-form', [
            'Book[title]' => 'Солярис (переиздание)',
            'Book[year]' => '1970',
            'Book[isbn]' => '9785171183660',
        ]);

        $updated = Book::findOne($book->id);

        $I->assertSame('Солярис (переиздание)', $updated->title);
        $I->assertSame(1970, (int) $updated->year);
    }

    public function updateReplacesAuthors(FunctionalTester $I): void
    {
        $first = $this->createAuthor('Стругацкий', 'Аркадий');
        $second = $this->createAuthor('Стругацкий', 'Борис');
        $book = $this->createBook([$first->id, $second->id]);

        $I->amOnRoute('book/update', ['id' => $book->id]);
        $I->submitForm('#book-form', [
            'Book[title]' => 'Солярис',
            'Book[year]' => '1961',
            'Book[isbn]' => '9785171183660',
            'Book[authorIds]' => [$second->id],
        ]);

        $authors = Book::findOne($book->id)->authors;

        $I->assertCount(1, $authors);
        $I->assertSame($second->id, $authors[0]->id);
    }

    public function deleteBook(FunctionalTester $I): void
    {
        $book = $this->createBook();

        $I->sendAjaxPostRequest(Url::to(['/book/delete', 'id' => $book->id]));

        $I->assertNull(Book::findOne($book->id));
    }

    /**
     * Удаление по ссылке (GET) стёрло бы книгу переходом по адресу — VerbFilter это запрещает.
     */
    public function deleteRejectsGet(FunctionalTester $I): void
    {
        $book = $this->createBook();

        $I->amOnPage(Url::to(['/book/delete', 'id' => $book->id]));

        $I->seeResponseCodeIs(405);
        $I->assertNotNull(Book::findOne($book->id));
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

    private function createAuthor(string $lastName, string $firstName): Author
    {
        $author = new Author(['last_name' => $lastName, 'first_name' => $firstName]);
        $author->save();

        return $author;
    }
}
