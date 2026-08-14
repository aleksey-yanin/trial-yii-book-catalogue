<?php

declare(strict_types=1);

namespace app\tests\Unit\Models;

use app\models\Author;
use app\models\AuthorSearch;
use app\models\Book;

final class AuthorSearchTest extends \Codeception\Test\Unit
{
    public function testWithoutFilterReturnsEveryone(): void
    {
        $this->createAuthor('Лем', 'Станислав');
        $this->createAuthor('Дик', 'Филип');

        verify((new AuthorSearch())->search([])->getTotalCount())->equals(2);
    }

    public function testFindsByLastName(): void
    {
        $this->createAuthor('Лем', 'Станислав');
        $this->createAuthor('Дик', 'Филип');

        $models = $this->search('Лем');

        verify($models)->arrayCount(1);
        verify($models[0]->first_name)->equals('Станислав');
    }

    public function testFindsByFirstName(): void
    {
        $this->createAuthor('Лем', 'Станислав');
        $this->createAuthor('Дик', 'Филип');

        $models = $this->search('Филип');

        verify($models)->arrayCount(1);
        verify($models[0]->last_name)->equals('Дик');
    }

    public function testFindsByMiddleName(): void
    {
        $author = new Author([
            'last_name' => 'Толстой',
            'first_name' => 'Лев',
            'middle_name' => 'Николаевич',
        ]);
        $author->save();

        verify($this->search('Николаевич'))->arrayCount(1);
    }

    public function testFindsByPartOfName(): void
    {
        $this->createAuthor('Стругацкий', 'Аркадий');
        $this->createAuthor('Стругацкий', 'Борис');

        verify($this->search('Стругацк'))->arrayCount(2);
    }

    /**
     * Число книг приходит подзапросом — без него список делал бы запрос на каждую строку.
     */
    public function testFillsBooksCount(): void
    {
        $author = $this->createAuthor('Лем', 'Станислав');

        $book = new Book([
            'title' => 'Солярис',
            'year' => 1961,
            'isbn' => '9785171183660',
            'authorIds' => [$author->id],
        ]);
        $book->save();

        $models = $this->search('Лем');

        verify($models[0]->booksCount)->equals(1);
    }

    public function testBooksCountIsZeroWithoutBooks(): void
    {
        $this->createAuthor('Лем', 'Станислав');

        verify($this->search('Лем')[0]->booksCount)->equals(0);
    }

    /**
     * @return Author[]
     */
    private function search(string $name): array
    {
        return (new AuthorSearch())->search(['AuthorSearch' => ['name' => $name]])->getModels();
    }

    private function createAuthor(string $lastName, string $firstName): Author
    {
        $author = new Author(['last_name' => $lastName, 'first_name' => $firstName]);
        $author->save();

        return $author;
    }
}
