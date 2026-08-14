<?php

declare(strict_types=1);

namespace app\tests\Unit\Components;

use Yii;
use app\components\BookNotifier;
use app\models\Author;
use app\models\Book;

final class BookNotifierTest extends \Codeception\Test\Unit
{
    protected function _before(): void
    {
        Yii::$app->db->createCommand()->delete('{{%queue}}')->execute();
    }

    public function testQueuesJobForEveryAuthor(): void
    {
        $book = $this->createBook([
            $this->createAuthor('Стругацкий')->id,
            $this->createAuthor('Стругацкая')->id,
        ]);

        verify((new BookNotifier())->notifyAboutNewBook($book))->equals(2);
        verify($this->queueSize())->equals(2);
    }

    public function testQueuesSingleJobForSingleAuthor(): void
    {
        $book = $this->createBook([$this->createAuthor()->id]);

        verify((new BookNotifier())->notifyAboutNewBook($book))->equals(1);
        verify($this->queueSize())->equals(1);
    }

    /**
     * Уведомлять некого: подписка оформляется на автора, а его у книги нет.
     */
    public function testQueuesNothingForBookWithoutAuthors(): void
    {
        $book = $this->createBook([]);

        verify((new BookNotifier())->notifyAboutNewBook($book))->equals(0);
        verify($this->queueSize())->equals(0);
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

    /**
     * @param int[] $authorIds
     */
    private function createBook(array $authorIds): Book
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
}
