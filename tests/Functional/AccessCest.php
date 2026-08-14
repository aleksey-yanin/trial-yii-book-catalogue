<?php

declare(strict_types=1);

namespace app\tests\Functional;

use app\models\Author;
use app\models\Book;
use app\models\User;
use app\tests\Support\FunctionalTester;
use yii\helpers\Url;

/**
 * Разграничение доступа: гость только читает, авторизованный меняет каталог.
 */
final class AccessCest
{
    public function guestSeesBookList(FunctionalTester $I): void
    {
        $this->createBook();

        $I->amOnRoute('book/index');
        $I->seeResponseCodeIs(200);
        $I->see('Солярис');
    }

    public function guestSeesBookCard(FunctionalTester $I): void
    {
        $book = $this->createBook();

        $I->amOnRoute('book/view', ['id' => $book->id]);
        $I->seeResponseCodeIs(200);
    }

    public function guestSeesAuthorPages(FunctionalTester $I): void
    {
        $author = $this->createAuthor();

        $I->amOnRoute('author/index');
        $I->seeResponseCodeIs(200);

        $I->amOnRoute('author/view', ['id' => $author->id]);
        $I->seeResponseCodeIs(200);
    }

    public function guestIsSentToLoginFromBookCreate(FunctionalTester $I): void
    {
        $I->amOnRoute('book/create');
        $I->see('Вход', 'h1');
    }

    public function guestIsSentToLoginFromBookUpdate(FunctionalTester $I): void
    {
        $book = $this->createBook();

        $I->amOnRoute('book/update', ['id' => $book->id]);
        $I->see('Вход', 'h1');
    }

    public function guestIsSentToLoginFromAuthorCreate(FunctionalTester $I): void
    {
        $I->amOnRoute('author/create');
        $I->see('Вход', 'h1');
    }

    /**
     * Главное: запрет должен действовать не только на переходах, но и на самом действии.
     */
    public function guestCannotDeleteBook(FunctionalTester $I): void
    {
        $book = $this->createBook();

        $I->sendAjaxPostRequest(Url::to(['/book/delete', 'id' => $book->id]));

        $I->assertNotNull(Book::findOne($book->id));
    }

    public function guestCannotCreateBookByPost(FunctionalTester $I): void
    {
        $I->sendAjaxPostRequest(Url::to(['/book/create']), [
            'Book[title]' => 'Тайком',
            'Book[year]' => '2000',
            'Book[isbn]' => '9785171183660',
        ]);

        $I->assertNull(Book::find()->where(['title' => 'Тайком'])->one());
    }

    public function guestDoesNotSeeEditButtons(FunctionalTester $I): void
    {
        $book = $this->createBook();

        $I->amOnRoute('book/index');
        $I->dontSee('Добавить книгу');

        $I->amOnRoute('book/view', ['id' => $book->id]);
        $I->dontSee('Редактировать');
        $I->dontSee('Удалить');
    }

    public function loggedInUserSeesEditButtons(FunctionalTester $I): void
    {
        $book = $this->createBook();
        $I->amLoggedInAs($this->createUser());

        $I->amOnRoute('book/index');
        $I->see('Добавить книгу');

        $I->amOnRoute('book/view', ['id' => $book->id]);
        $I->see('Редактировать');
        $I->see('Удалить');
    }

    public function loggedInUserOpensCreateForms(FunctionalTester $I): void
    {
        $I->amLoggedInAs($this->createUser());

        $I->amOnRoute('book/create');
        $I->seeResponseCodeIs(200);
        $I->see('Добавить книгу', 'h1');

        $I->amOnRoute('author/create');
        $I->seeResponseCodeIs(200);
    }

    public function loggedInUserCreatesBook(FunctionalTester $I): void
    {
        $I->amLoggedInAs($this->createUser());

        $I->amOnRoute('book/create');
        $I->submitForm('#book-form', [
            'Book[title]' => 'Непобедимый',
            'Book[year]' => '1964',
            'Book[isbn]' => '9785171183660',
        ]);

        $I->assertNotNull(Book::find()->where(['title' => 'Непобедимый'])->one());
    }

    public function loggedInUserDeletesBook(FunctionalTester $I): void
    {
        $book = $this->createBook();
        $I->amLoggedInAs($this->createUser());

        $I->sendAjaxPostRequest(Url::to(['/book/delete', 'id' => $book->id]));

        $I->assertNull(Book::findOne($book->id));
    }

    private function createUser(): User
    {
        $user = new User(['username' => 'tester']);
        $user->setPassword('secret');
        $user->generateAuthKey();
        $user->save();

        return $user;
    }

    private function createBook(): Book
    {
        $book = new Book([
            'title' => 'Солярис',
            'year' => 1961,
            'isbn' => '9785171183660',
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
