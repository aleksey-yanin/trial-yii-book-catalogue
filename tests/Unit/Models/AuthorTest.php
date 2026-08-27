<?php

declare(strict_types=1);

namespace app\tests\Unit\Models;

use app\models\Author;
use app\models\Book;

final class AuthorTest extends \Codeception\Test\Unit
{
    public function testLastNameAndFirstNameAreRequired(): void
    {
        $author = new Author();

        verify($author->validate())->false();
        verify($author->hasErrors('last_name'))->true();
        verify($author->hasErrors('first_name'))->true();
        // Отчество не обязательно.
        verify($author->hasErrors('middle_name'))->false();
    }

    public function testSavesWithoutMiddleName(): void
    {
        $author = new Author(['last_name' => 'Стругацкий', 'first_name' => 'Борис']);

        verify($author->save())->true();
        verify($author->middle_name)->null();
    }

    public function testFullNameJoinsPartsInOrder(): void
    {
        $author = new Author([
            'last_name' => 'Толстой',
            'first_name' => 'Лев',
            'middle_name' => 'Николаевич',
        ]);

        verify($author->fullName)->equals('Толстой Лев Николаевич');
    }

    public function testFullNameSkipsMissingMiddleName(): void
    {
        $author = new Author(['last_name' => 'Оруэлл', 'first_name' => 'Джордж']);

        // Без отчества не должно оставаться двойного пробела.
        verify($author->fullName)->equals('Оруэлл Джордж');
    }

    public function testNamesAreTrimmed(): void
    {
        $author = new Author(['last_name' => '  Гоголь ', 'first_name' => ' Николай ']);
        $author->validate();

        verify($author->last_name)->equals('Гоголь');
        verify($author->first_name)->equals('Николай');
    }

    public function testBooksRelation(): void
    {
        $author = $this->createAuthor();

        $book = new Book([
            'title' => 'Понедельник начинается в субботу',
            'year' => 1965,
            'isbn' => '9785171183660',
            'authorIds' => [$author->id],
        ]);
        verify($book->save())->true();

        /** @var Book[] $books */
        $books = $author->getBooks()->all();

        verify($books)->arrayCount(1);
        verify($books[0]->title)->equals('Понедельник начинается в субботу');
    }

    public function testTimestampsAreFilled(): void
    {
        $author = $this->createAuthor();

        verify($author->created_at)->greaterThan(0);
        verify($author->updated_at)->greaterThan(0);
    }

    /**
     * Список для выпадающих полей: идентификатор → ФИО, по фамилии.
     */
    public function testOptionListIsSortedByLastName(): void
    {
        $second = $this->createAuthor();
        $first = new Author(['last_name' => 'Лем', 'first_name' => 'Станислав']);
        $first->save();

        $options = Author::optionList();

        verify(array_key_first($options))->equals($first->id);
        verify($options[$second->id])->equals('Стругацкий Аркадий');
    }

    public function testBooksProviderSortsByYearDescending(): void
    {
        $author = $this->createAuthor();
        $this->createBook($author->id, 'Ранняя', 1960, '9785171183660');
        $this->createBook($author->id, 'Поздняя', 1985, '9785389089341');

        $models = $author->booksProvider()->getModels();

        verify($models)->arrayCount(2);
        verify($models[0]->title)->equals('Поздняя');
    }

    private function createAuthor(): Author
    {
        $author = new Author(['last_name' => 'Стругацкий', 'first_name' => 'Аркадий']);
        $author->save();

        return $author;
    }

    private function createBook(int $authorId, string $title, int $year, string $isbn): void
    {
        $book = new Book([
            'title' => $title,
            'year' => $year,
            'isbn' => $isbn,
            'authorIds' => [$authorId],
        ]);
        $book->save();
    }
}
