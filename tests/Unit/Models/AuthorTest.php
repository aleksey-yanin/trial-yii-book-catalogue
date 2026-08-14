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

    private function createAuthor(): Author
    {
        $author = new Author(['last_name' => 'Стругацкий', 'first_name' => 'Аркадий']);
        $author->save();

        return $author;
    }
}
