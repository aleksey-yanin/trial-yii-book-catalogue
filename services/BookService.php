<?php

declare(strict_types=1);

namespace app\services;

use app\components\BookNotifier;
use app\components\CoverStorage;
use app\models\Book;
use yii\web\UploadedFile;

/**
 * Жизненный цикл книги: обложка, порядок сохранения, уведомления подписчикам.
 *
 * Контроллер связывает запрос с моделью и на этом останавливается — всё, что происходит
 * дальше, собрано здесь: файл обложки нельзя сохранять до валидации, старый файл нельзя
 * удалять до успешной записи, а уведомления нельзя ставить раньше, чем у книги появится id.
 *
 * Уведомления ставятся именно тут, а не в Book::afterSave(): сидер демо-данных создаёт
 * полсотни книг через Book::save() напрямую, минуя сервис, и модельный триггер забил бы
 * очередь заданиями, которые никому не нужны.
 */
final class BookService
{
    public function __construct(
        private readonly CoverStorage $coverStorage,
        private readonly BookNotifier $notifier,
    ) {
    }

    /**
     * Сохраняет новую книгу и ставит уведомления подписчикам её авторов.
     */
    public function create(Book $book): bool
    {
        if (!$this->store($book)) {
            return false;
        }

        $this->notifier->notifyAboutNewBook($book);

        return true;
    }

    /**
     * Сохраняет изменения книги и убирает файл прежней обложки.
     *
     * Уведомления не ставятся: подписка обещает сообщать о новых книгах автора,
     * а не о правках в существующих.
     */
    public function update(Book $book): bool
    {
        // Прежнее имя берём из oldAttributes, а не из атрибута: так сервис не зависит
        // от того, в какой момент его вызвали и что успели сделать с моделью до него.
        $previous = $book->getOldAttribute('cover_path');
        $previousCover = is_string($previous) ? $previous : null;

        if (!$this->store($book)) {
            return false;
        }

        // Старый файл убираем только после успешной записи: упади save(), обложка
        // осталась бы нужна.
        if ($previousCover !== null && $previousCover !== $book->cover_path) {
            $this->coverStorage->delete($previousCover);
        }

        return true;
    }

    /**
     * Удаляет книгу вместе с файлом обложки.
     */
    public function delete(Book $book): void
    {
        $cover = $book->cover_path;

        $book->delete();
        // Связи в book_author уберёт внешний ключ с ON DELETE CASCADE.
        $this->coverStorage->delete($cover);
    }

    /**
     * Общая для создания и правки часть: проверить, положить файл, записать.
     */
    private function store(Book $book): bool
    {
        if (!$book->validate()) {
            return false;
        }

        // Проверка на instanceof, а не на null: свойство объявлено как UploadedFile|string|null,
        // потому что форма без выбранного файла присылает пустую строку.
        if ($book->coverFile instanceof UploadedFile) {
            $book->cover_path = $this->coverStorage->save($book->coverFile);
        }

        // Валидация уже прошла — второй раз её гонять незачем.
        return $book->save(false);
    }
}
