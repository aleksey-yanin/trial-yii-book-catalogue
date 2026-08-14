<?php

declare(strict_types=1);

namespace app\components;

use Yii;
use app\components\jobs\SendBookNotificationJob;
use app\models\Book;
use yii\queue\db\Queue;

/**
 * Ставит в очередь уведомления о новой книге.
 *
 * Вызывается из контроллера после успешного сохранения, а не из Book::afterSave():
 * сидер демо-данных создаёт полсотни книг и забил бы очередь заданиями, которые
 * никому не нужны.
 */
class BookNotifier extends \yii\base\Component
{
    /**
     * Ставит по заданию на каждого автора книги и возвращает их число.
     */
    public function notifyAboutNewBook(Book $book): int
    {
        /** @var Queue $queue */
        $queue = Yii::$app->get('smsQueue');
        $queued = 0;

        foreach ($book->authors as $author) {
            $queue->push(new SendBookNotificationJob([
                'bookId' => $book->id,
                'authorId' => $author->id,
            ]));
            $queued++;
        }

        return $queued;
    }
}
