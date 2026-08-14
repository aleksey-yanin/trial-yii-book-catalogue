<?php

declare(strict_types=1);

namespace app\tests\Functional;

use Yii;
use app\models\Author;
use app\models\Book;
use app\models\User;
use app\tests\Support\FunctionalTester;

/**
 * Уведомления ставятся в очередь при добавлении книги через форму.
 */
final class BookNotificationCest
{
    public function _before(FunctionalTester $I): void
    {
        Yii::$app->db->createCommand()->delete('{{%queue}}')->execute();

        $user = new User(['username' => 'tester']);
        $user->setPassword('secret');
        $user->generateAuthKey();
        $user->save();

        $I->amLoggedInAs($user);
    }

    public function creatingBookQueuesJobForEveryAuthor(FunctionalTester $I): void
    {
        $first = $this->createAuthor('Стругацкий');
        $second = $this->createAuthor('Стругацкая');

        $I->amOnRoute('book/create');
        $I->submitForm('#book-form', [
            'Book[title]' => 'Пикник на обочине',
            'Book[year]' => '1972',
            'Book[isbn]' => '9785171183660',
            'Book[authorIds]' => [$first->id, $second->id],
        ]);

        $I->assertNotNull(Book::find()->where(['title' => 'Пикник на обочине'])->one());
        $I->assertSame(2, $this->queueSize());
    }

    public function creatingBookWithoutAuthorsQueuesNothing(FunctionalTester $I): void
    {
        $I->amOnRoute('book/create');
        $I->submitForm('#book-form', [
            'Book[title]' => 'Без авторов',
            'Book[year]' => '2000',
            'Book[isbn]' => '9785171183660',
        ]);

        $I->assertNotNull(Book::find()->where(['title' => 'Без авторов'])->one());
        $I->assertSame(0, $this->queueSize());
    }

    /**
     * Редактирование — не появление новой книги, уведомлять не о чем.
     */
    public function updatingBookQueuesNothing(FunctionalTester $I): void
    {
        $author = $this->createAuthor();
        $book = new Book([
            'title' => 'Солярис',
            'year' => 1961,
            'isbn' => '9785171183660',
            'authorIds' => [$author->id],
        ]);
        $book->save();

        $I->amOnRoute('book/update', ['id' => $book->id]);
        $I->submitForm('#book-form', [
            'Book[title]' => 'Солярис (переиздание)',
            'Book[year]' => '1970',
            'Book[isbn]' => '9785171183660',
            'Book[authorIds]' => [$author->id],
        ]);

        $I->assertSame(0, $this->queueSize());
    }

    /**
     * Книга не сохранилась — уведомлять не о чем.
     */
    public function invalidFormQueuesNothing(FunctionalTester $I): void
    {
        $I->amOnRoute('book/create');
        $I->submitForm('#book-form', [
            'Book[title]' => '',
            'Book[year]' => '',
            'Book[isbn]' => '',
        ]);

        $I->assertSame(0, $this->queueSize());
    }

    private function queueSize(): int
    {
        return (int) Yii::$app->db
            ->createCommand('SELECT COUNT(*) FROM {{%queue}}')
            ->queryScalar();
    }

    private function createAuthor(string $lastName = 'Лем'): Author
    {
        $author = new Author(['last_name' => $lastName, 'first_name' => 'Имя']);
        $author->save();

        return $author;
    }
}
