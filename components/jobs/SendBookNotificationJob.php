<?php

declare(strict_types=1);

namespace app\components\jobs;

use Yii;
use app\components\sms\SmsSendException;
use app\components\sms\SmsSenderInterface;
use app\models\Author;
use app\models\Book;
use app\models\Subscription;
use yii\base\BaseObject;
use yii\queue\RetryableJobInterface;

/**
 * Рассылка SMS подписчикам одного автора о его новой книге.
 *
 * Задание ставится по одному на автора: у книги их может быть несколько, а подписка
 * оформляется на конкретного автора.
 */
class SendBookNotificationJob extends BaseObject implements RetryableJobInterface
{
    /**
     * Больше трёх попыток смысла не имеет: если шлюз недоступен так долго,
     * уведомление всё равно устарело.
     */
    public const MAX_ATTEMPTS = 3;

    /**
     * Хранятся идентификаторы, а не модели: очередь сериализует задание, и объект,
     * положенный целиком, к моменту обработки успеет устареть.
     */
    public int $bookId = 0;

    public int $authorId = 0;

    /**
     * Сколько задание считается «в работе», прежде чем очередь вернёт его в строй.
     */
    public function getTtr(): int
    {
        return 300;
    }

    /**
     * Отказ шлюза — повод попробовать ещё раз: сервис мог быть временно недоступен.
     * Ошибки в коде повторять бессмысленно, поэтому повтор только для SmsSendException.
     */
    public function canRetry($attempt, $error): bool
    {
        return $attempt < self::MAX_ATTEMPTS && $error instanceof SmsSendException;
    }

    public function execute($queue): void
    {
        $book = Book::findOne($this->bookId);
        $author = Author::findOne($this->authorId);

        // Книгу или автора могли удалить, пока задание ждало обработки. Это не ошибка.
        if ($book === null || $author === null) {
            Yii::info(
                sprintf('Уведомление пропущено: книга %d или автор %d удалены.', $this->bookId, $this->authorId),
                __METHOD__,
            );

            return;
        }

        $text = self::buildText($author, $book);
        $phones = Subscription::phonesForAuthor($author->id);
        $failed = 0;

        foreach ($phones as $phone) {
            if (!$this->sendTo($phone, $text)) {
                $failed++;
            }
        }

        // Если не дошло ни одно сообщение, дело почти наверняка в шлюзе, а не в номерах:
        // задание должно упасть, чтобы очередь повторила попытку, иначе уведомление
        // потеряется молча.
        if ($phones !== [] && $failed === count($phones)) {
            throw new SmsSendException(
                sprintf('Ни одно из %d уведомлений не отправлено.', $failed),
            );
        }
    }

    public static function buildText(Author $author, Book $book): string
    {
        return sprintf('Новая книга %s: «%s»', $author->fullName, $book->title);
    }

    /**
     * Отказ шлюза по одному номеру не должен отменять рассылку остальным подписчикам.
     */
    private function sendTo(string $phone, string $text): bool
    {
        /** @var SmsSenderInterface $sender */
        $sender = Yii::$app->get('smsSender');

        try {
            $sender->send($phone, $text);

            return true;
        } catch (SmsSendException $exception) {
            Yii::error(
                sprintf('Не удалось отправить SMS на %s: %s', $phone, $exception->getMessage()),
                __METHOD__,
            );

            return false;
        }
    }
}
