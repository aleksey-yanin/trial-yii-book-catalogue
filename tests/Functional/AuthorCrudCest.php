<?php

declare(strict_types=1);

namespace app\tests\Functional;

use app\models\Author;
use app\models\Book;
use app\models\User;
use app\tests\Support\FunctionalTester;
use yii\helpers\Url;

final class AuthorCrudCest
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
        $I->amOnRoute('author/index');
        $I->see('Авторы', 'h1');
    }

    public function seeAuthorInList(FunctionalTester $I): void
    {
        $this->createAuthor();

        $I->amOnRoute('author/index');
        $I->see('Лем');
    }

    public function openAuthorCard(FunctionalTester $I): void
    {
        $author = $this->createAuthor();

        $I->amOnRoute('author/view', ['id' => $author->id]);
        $I->see('Лем Станислав', 'h1');
    }

    public function cardShowsAuthorBooks(FunctionalTester $I): void
    {
        $author = $this->createAuthor();

        $book = new Book([
            'title' => 'Солярис',
            'year' => 1961,
            'isbn' => '9785171183660',
            'authorIds' => [$author->id],
        ]);
        $book->save();

        $I->amOnRoute('author/view', ['id' => $author->id]);
        $I->see('Солярис');
    }

    public function missingAuthorGivesNotFound(FunctionalTester $I): void
    {
        $I->amOnRoute('author/view', ['id' => 999999]);
        $I->seeResponseCodeIs(404);
    }

    public function createAuthorViaForm(FunctionalTester $I): void
    {
        $I->amOnRoute('author/create');
        $I->submitForm('#author-form', [
            'Author[last_name]' => 'Стругацкий',
            'Author[first_name]' => 'Аркадий',
            'Author[middle_name]' => 'Натанович',
        ]);

        $author = Author::find()->where(['last_name' => 'Стругацкий'])->one();

        $I->assertNotNull($author);
        $I->assertSame('Стругацкий Аркадий Натанович', $author->fullName);
    }

    public function emptyFormShowsErrors(FunctionalTester $I): void
    {
        $I->amOnRoute('author/create');
        $I->submitForm('#author-form', [
            'Author[last_name]' => '',
            'Author[first_name]' => '',
        ]);

        $I->see('Необходимо заполнить «Фамилия».');
        $I->see('Необходимо заполнить «Имя».');
    }

    public function updateAuthor(FunctionalTester $I): void
    {
        $author = $this->createAuthor();

        $I->amOnRoute('author/update', ['id' => $author->id]);
        $I->submitForm('#author-form', [
            'Author[last_name]' => 'Лем',
            'Author[first_name]' => 'Станислав',
            'Author[middle_name]' => 'Германович',
        ]);

        $I->assertSame('Германович', Author::findOne($author->id)->middle_name);
    }

    public function deleteAuthorKeepsBooks(FunctionalTester $I): void
    {
        $author = $this->createAuthor();
        $book = new Book([
            'title' => 'Солярис',
            'year' => 1961,
            'isbn' => '9785171183660',
            'authorIds' => [$author->id],
        ]);
        $book->save();

        $I->sendAjaxPostRequest(Url::to(['/author/delete', 'id' => $author->id]));

        $I->assertNull(Author::findOne($author->id));
        // Книга остаётся в каталоге, исчезает только связь.
        $I->assertNotNull(Book::findOne($book->id));
        $I->assertCount(0, Book::findOne($book->id)->authors);
    }

    private function createAuthor(): Author
    {
        $author = new Author(['last_name' => 'Лем', 'first_name' => 'Станислав']);
        $author->save();

        return $author;
    }
}
