<?php

declare(strict_types=1);

namespace app\tests\Unit\Components;

use Yii;
use app\components\jobs\SendBookNotificationJob;
use app\components\sms\SmsSendException;
use app\components\sms\SmsSenderInterface;
use app\models\Author;
use app\models\Book;
use app\models\Subscription;
use yii\queue\Queue;

final class SendBookNotificationJobTest extends \Codeception\Test\Unit
{
    public function testSendsToEverySubscriberOfAuthor(): void
    {
        $author = $this->createAuthor();
        $book = $this->createBook($author);
        $this->subscribe($author, '79991111111');
        $this->subscribe($author, '79992222222');

        $sender = $this->fakeSender();

        $this->job($book, $author)->execute($this->queue());

        verify(array_column($sender->sent, 'phone'))->equals(['79991111111', '79992222222']);
    }

    /**
     * Подписка именная: подписчик другого автора уведомление получать не должен.
     */
    public function testDoesNotSendToSubscribersOfOtherAuthors(): void
    {
        $author = $this->createAuthor('Лем');
        $other = $this->createAuthor('Дик');
        $book = $this->createBook($author);

        $this->subscribe($author, '79991111111');
        $this->subscribe($other, '79993333333');

        $sender = $this->fakeSender();

        $this->job($book, $author)->execute($this->queue());

        verify(array_column($sender->sent, 'phone'))->equals(['79991111111']);
    }

    public function testTextContainsAuthorAndTitle(): void
    {
        $author = $this->createAuthor('Лем');
        $book = $this->createBook($author);
        $this->subscribe($author, '79991111111');

        $sender = $this->fakeSender();

        $this->job($book, $author)->execute($this->queue());

        verify($sender->sent[0]['text'])->stringContainsString('Лем');
        verify($sender->sent[0]['text'])->stringContainsString($book->title);
    }

    public function testDoesNothingWithoutSubscribers(): void
    {
        $author = $this->createAuthor();
        $book = $this->createBook($author);

        $sender = $this->fakeSender();

        $this->job($book, $author)->execute($this->queue());

        verify($sender->sent)->empty();
    }

    /**
     * Книгу могли удалить, пока задание ждало обработки, — это не ошибка.
     */
    public function testSilentlySkipsDeletedBook(): void
    {
        $author = $this->createAuthor();
        $this->subscribe($author, '79991111111');

        $sender = $this->fakeSender();

        $job = new SendBookNotificationJob(['bookId' => 999999, 'authorId' => $author->id]);
        $job->execute($this->queue());

        verify($sender->sent)->empty();
    }

    public function testSilentlySkipsDeletedAuthor(): void
    {
        $author = $this->createAuthor();
        $book = $this->createBook($author);

        $sender = $this->fakeSender();

        $job = new SendBookNotificationJob(['bookId' => $book->id, 'authorId' => 999999]);
        $job->execute($this->queue());

        verify($sender->sent)->empty();
    }

    /**
     * Отказ по одному номеру не должен отменять рассылку остальным.
     */
    public function testKeepsSendingAfterSingleFailure(): void
    {
        $author = $this->createAuthor();
        $book = $this->createBook($author);
        $this->subscribe($author, '79991111111');
        $this->subscribe($author, '79992222222');

        $sender = $this->fakeSender(failFor: '79991111111');

        $this->job($book, $author)->execute($this->queue());

        verify(array_column($sender->sent, 'phone'))->equals(['79992222222']);
    }

    /**
     * Если не дошло ни одно сообщение, дело в шлюзе: задание обязано упасть,
     * чтобы очередь повторила попытку, а не потерять уведомление молча.
     */
    public function testFailsWhenNothingWasDelivered(): void
    {
        $author = $this->createAuthor();
        $book = $this->createBook($author);
        $this->subscribe($author, '79991111111');

        $this->fakeSender(failFor: '79991111111');

        $this->expectException(SmsSendException::class);

        $this->job($book, $author)->execute($this->queue());
    }

    /**
     * Очередь джобе не нужна, но её требует сигнатура JobInterface.
     */
    private function queue(): Queue
    {
        /** @var Queue $queue */
        $queue = Yii::$app->get('smsQueue');

        return $queue;
    }

    private function job(Book $book, Author $author): SendBookNotificationJob
    {
        return new SendBookNotificationJob(['bookId' => $book->id, 'authorId' => $author->id]);
    }

    /**
     * Подставной отправитель: сеть в тестах не нужна, важно, кому и что ушло.
     */
    private function fakeSender(?string $failFor = null): object
    {
        $sender = new class ($failFor) implements SmsSenderInterface {
            /** @var array<int, array{phone: string, text: string}> */
            public array $sent = [];

            public function __construct(private ?string $failFor)
            {
            }

            public function send(string $phone, string $text): void
            {
                if ($phone === $this->failFor) {
                    throw new SmsSendException('Шлюз отклонил сообщение.');
                }

                $this->sent[] = ['phone' => $phone, 'text' => $text];
            }
        };

        Yii::$app->set('smsSender', $sender);

        return $sender;
    }

    private function createAuthor(string $lastName = 'Лем'): Author
    {
        $author = new Author(['last_name' => $lastName, 'first_name' => 'Имя']);
        $author->save();

        return $author;
    }

    private function createBook(Author $author): Book
    {
        $book = new Book([
            'title' => 'Солярис',
            'year' => 1961,
            'isbn' => '9785171183660',
            'authorIds' => [$author->id],
        ]);
        $book->save();

        return $book;
    }

    private function subscribe(Author $author, string $phone): void
    {
        $subscription = new Subscription(['author_id' => $author->id, 'phone' => $phone]);
        $subscription->save();
    }
}
