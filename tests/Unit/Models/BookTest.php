<?php

declare(strict_types=1);

namespace app\tests\Unit\Models;

use app\models\Author;
use app\models\Book;

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
