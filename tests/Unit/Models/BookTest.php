<?php

declare(strict_types=1);

namespace app\tests\Unit\Models;

use app\models\Author;
use app\models\Book;
use yii\log\Logger;

final class BookTest extends \Codeception\Test\Unit
{
    public function testTitleYearAndIsbnAreRequired(): void
    {
        $book = new Book();

        verify($book->validate())->false();
        verify($book->hasErrors('title'))->true();
        verify($book->hasErrors('year'))->true();
        verify($book->hasErrors('isbn'))->true();
        // Описание и обложка не обязательны.
        verify($book->hasErrors('description'))->false();
        verify($book->hasErrors('cover_path'))->false();
    }

    public function testRejectsTooLongDescription(): void
    {
        $book = $this->makeBook([
            'description' => str_repeat('я', Book::MAX_DESCRIPTION_LENGTH + 1),
        ]);

        verify($book->validate())->false();
        verify($book->hasErrors('description'))->true();
    }

    public function testAcceptsDescriptionAtTheLimit(): void
    {
        $book = $this->makeBook([
            'description' => str_repeat('я', Book::MAX_DESCRIPTION_LENGTH),
        ]);

        verify($book->validate())->true();
    }

    public function testRejectsYearBeforePrinting(): void
    {
        $book = $this->makeBook(['year' => Book::MIN_YEAR - 1]);

        verify($book->validate())->false();
        verify($book->hasErrors('year'))->true();
    }

    public function testRejectsYearInFuture(): void
    {
        $book = $this->makeBook(['year' => Book::maxYear() + 1]);

        verify($book->validate())->false();
        verify($book->hasErrors('year'))->true();
    }

    public function testAcceptsCurrentYear(): void
    {
        $book = $this->makeBook(['year' => Book::maxYear()]);

        verify($book->validate())->true();
    }

    /**
     * Рядом с полем файла Yii рендерит скрытый input с тем же именем и пустым значением,
     * поэтому при отправке формы без файла в load() приходит пустая строка. Строгий тип
     * свойства ронял на этом сохранение книги.
     */
    public function testLoadAcceptsEmptyCoverFieldFromForm(): void
    {
        $book = new Book();

        $book->load([
            'Book' => [
                'title' => 'Солярис',
                'year' => 1961,
                'isbn' => '9785171183660',
                'coverFile' => '',
            ],
        ]);

        verify($book->validate())->true();
        verify($book->save())->true();
        verify($book->cover_path)->null();
    }

    public function testRejectsInvalidIsbn(): void
    {
        $book = $this->makeBook(['isbn' => '9785171183661']);

        verify($book->validate())->false();
        verify($book->hasErrors('isbn'))->true();
    }

    public function testStoresIsbnNormalized(): void
    {
        $book = $this->makeBook(['isbn' => '978-5-17-118366-0']);

        verify($book->save())->true();
        verify($book->isbn)->equals('9785171183660');
    }

    /**
     * Дубль должен отсекаться и тогда, когда записан с другими разделителями:
     * ради этого ISBN и хранится нормализованным.
     */
    public function testRejectsDuplicateIsbnWrittenDifferently(): void
    {
        verify($this->makeBook(['isbn' => '9785171183660'])->save())->true();

        $duplicate = $this->makeBook(['isbn' => '978-5-17-118366-0']);

        verify($duplicate->save())->false();
        verify($duplicate->hasErrors('isbn'))->true();
    }

    public function testLinksSeveralAuthors(): void
    {
        $first = $this->createAuthor('Стругацкий', 'Аркадий');
        $second = $this->createAuthor('Стругацкий', 'Борис');

        $book = $this->makeBook(['authorIds' => [$first->id, $second->id]]);
        verify($book->save())->true();

        /** @var Author[] $authors */
        $authors = $book->getAuthors()->orderBy('first_name')->all();

        verify($authors)->arrayCount(2);
        verify($authors[0]->fullName)->equals('Стругацкий Аркадий');
        verify($authors[1]->fullName)->equals('Стругацкий Борис');
    }

    public function testAuthorIdsAreLoadedForSavedBook(): void
    {
        $author = $this->createAuthor('Лем', 'Станислав');
        $book = $this->makeBook(['authorIds' => [$author->id]]);
        $book->save();

        /** @var Book $loaded */
        $loaded = Book::findOne($book->id);

        verify($loaded->authorIds)->equals([$author->id]);
    }

    public function testReplacesAuthorsOnUpdate(): void
    {
        $first = $this->createAuthor('Лем', 'Станислав');
        $second = $this->createAuthor('Дик', 'Филип');

        $book = $this->makeBook(['authorIds' => [$first->id]]);
        $book->save();

        $book->authorIds = [$second->id];
        verify($book->save())->true();

        /** @var Author[] $authors */
        $authors = $book->getAuthors()->all();

        verify($authors)->arrayCount(1);
        verify($authors[0]->id)->equals($second->id);
    }

    /**
     * Авторы книги не должны потеряться при сохранении, которое их не касается.
     */
    public function testKeepsAuthorsWhenSavingUnrelatedAttribute(): void
    {
        $author = $this->createAuthor('Лем', 'Станислав');
        $book = $this->makeBook(['authorIds' => [$author->id]]);
        $book->save();

        /** @var Book $loaded */
        $loaded = Book::findOne($book->id);
        $loaded->title = 'Другое название';
        verify($loaded->save())->true();

        verify($loaded->getAuthors()->count())->equals(1);
    }

    /**
     * Правка, не касающаяся авторов, не должна трогать таблицу связей: прежняя реализация
     * на каждом сохранении сносила все строки и вставляла их заново.
     */
    public function testUnchangedAuthorsDoNotTouchLinkTable(): void
    {
        $author = $this->createAuthor('Лем', 'Станислав');
        $book = $this->makeBook(['authorIds' => [$author->id]]);
        $book->save();

        $queries = $this->linkQueriesDuring(static function () use ($book): void {
            $book->title = 'Другое название';
            $book->save();
        });

        verify($queries)->equals([]);
    }

    /**
     * Несколько авторов записываются одним запросом, а не по одному на автора.
     */
    public function testSeveralAuthorsAreLinkedInOneInsert(): void
    {
        $ids = [
            $this->createAuthor('Стругацкий', 'Аркадий')->id,
            $this->createAuthor('Стругацкий', 'Борис')->id,
            $this->createAuthor('Лем', 'Станислав')->id,
        ];
        $book = $this->makeBook(['authorIds' => $ids]);

        $queries = $this->linkQueriesDuring(static function () use ($book): void {
            $book->save();
        });

        verify($queries)->arrayCount(1);
        verify($book->getAuthors()->count())->equals(3);
    }

    /**
     * Смена одного соавтора не должна переписывать связь со вторым.
     */
    public function testPartialAuthorChangeKeepsCommonAuthor(): void
    {
        $kept = $this->createAuthor('Стругацкий', 'Аркадий');
        $dropped = $this->createAuthor('Стругацкий', 'Борис');
        $added = $this->createAuthor('Лем', 'Станислав');

        $book = $this->makeBook(['authorIds' => [$kept->id, $dropped->id]]);
        $book->save();

        $book->authorIds = [$kept->id, $added->id];
        verify($book->save())->true();

        $ids = array_map('intval', $book->getAuthors()->select('id')->column());
        sort($ids);
        $expected = [$kept->id, $added->id];
        sort($expected);

        verify($ids)->equals($expected);
    }

    /**
     * Подделанное поле формы не должно ронять сохранение на внешнем ключе.
     */
    public function testIgnoresUnknownAuthorIds(): void
    {
        $author = $this->createAuthor('Лем', 'Станислав');
        $book = $this->makeBook(['authorIds' => [$author->id, 999999]]);

        verify($book->save())->true();
        verify(array_map('intval', $book->getAuthors()->select('id')->column()))
            ->equals([$author->id]);
    }

    public function testDeletingBookRemovesLinks(): void
    {
        $author = $this->createAuthor('Лем', 'Станислав');
        $book = $this->makeBook(['authorIds' => [$author->id]]);
        $book->save();
        $bookId = $book->id;

        verify($book->delete())->notFalse();

        $links = \Yii::$app->db
            ->createCommand('SELECT COUNT(*) FROM {{%book_author}} WHERE book_id = :id', [':id' => $bookId])
            ->queryScalar();

        verify((int) $links)->equals(0);
        // Автор при этом остаётся в каталоге.
        verify(Author::findOne($author->id))->notNull();
    }

    public function testDeletingAuthorRemovesLinksButKeepsBook(): void
    {
        $author = $this->createAuthor('Лем', 'Станислав');
        $book = $this->makeBook(['authorIds' => [$author->id]]);
        $book->save();

        verify($author->delete())->notFalse();

        verify(Book::findOne($book->id))->notNull();
        verify(Book::findOne($book->id)->getAuthors()->count())->equals(0);
    }

    /**
     * Запросы к таблице связей, выполненные за время действия.
     *
     * Codeception подменяет логгер Yii своим, который сообщения выбрасывает, поэтому
     * на время действия ставится обычный yii\log\Logger и читается уже он. Профилирование
     * запросов yii\db\Connection включает по умолчанию — этого достаточно.
     *
     * @return string[]
     */
    private function linkQueriesDuring(callable $action): array
    {
        $previous = \Yii::getLogger();
        $logger = new Logger();
        $logger->flushInterval = PHP_INT_MAX;
        \Yii::setLogger($logger);

        try {
            $action();
        } finally {
            \Yii::setLogger($previous);
        }

        $queries = [];

        foreach ($logger->messages as $message) {
            $text = $message[0];

            // Один запрос даёт три записи (info, начало и конец профилирования),
            // поэтому берём только начало профиля.
            if (
                $message[1] === Logger::LEVEL_PROFILE_BEGIN
                && is_string($text)
                && str_contains($text, 'book_author')
                && preg_match('/^\s*(INSERT|DELETE)\b/i', $text) === 1
            ) {
                $queries[] = $text;
            }
        }

        return $queries;
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function makeBook(array $attributes = []): Book
    {
        return new Book(array_merge([
            'title' => 'Солярис',
            'year' => 1961,
            'isbn' => '9785171183660',
        ], $attributes));
    }

    private function createAuthor(string $lastName, string $firstName): Author
    {
        $author = new Author(['last_name' => $lastName, 'first_name' => $firstName]);
        $author->save();

        return $author;
    }
}
