<?php

declare(strict_types=1);

namespace app\tests\Unit\Models;

use app\models\Author;
use app\models\Book;
use app\models\BookSearch;

final class BookSearchTest extends \Codeception\Test\Unit
{
    public function testWithoutFiltersReturnsEverything(): void
    {
        $this->createBook('Солярис', 1961, '9785171183660');
        $this->createBook('Эдем', 1959, '5170624956');

        verify((new BookSearch())->search([])->getTotalCount())->equals(2);
    }

    public function testFiltersByPartOfTitle(): void
    {
        $this->createBook('Солярис', 1961, '9785171183660');
        $this->createBook('Эдем', 1959, '5170624956');

        $models = $this->search(['title' => 'оляр']);

        verify($models)->arrayCount(1);
        verify($models[0]->title)->equals('Солярис');
    }

    /**
     * Форма поиска отправляется методом GET, поэтому незаполненные поля приходят
     * пустыми строками. Строгий тип свойства ронял на этом весь поиск.
     */
    public function testAcceptsEmptyFiltersFromForm(): void
    {
        $this->createBook('Солярис', 1961, '9785171183660');

        $models = $this->search(['title' => '', 'year' => '', 'authorId' => '']);

        verify($models)->arrayCount(1);
    }

    public function testFiltersByYear(): void
    {
        $this->createBook('Солярис', 1961, '9785171183660');
        $this->createBook('Эдем', 1959, '5170624956');

        $models = $this->search(['year' => 1959]);

        verify($models)->arrayCount(1);
        verify($models[0]->title)->equals('Эдем');
    }

    public function testFiltersByAuthor(): void
    {
        $lem = $this->createAuthor('Лем', 'Станислав');
        $dick = $this->createAuthor('Дик', 'Филип');

        $this->createBook('Солярис', 1961, '9785171183660', [$lem->id]);
        $this->createBook('Убик', 1969, '5170624956', [$dick->id]);

        $models = $this->search(['authorId' => $lem->id]);

        verify($models)->arrayCount(1);
        verify($models[0]->title)->equals('Солярис');
    }

    /**
     * У книги двух авторов фильтр по каждому из них должен её находить,
     * и ровно один раз — join по связи легко даёт дубли.
     */
    public function testFindsBookWithSeveralAuthorsOnce(): void
    {
        $first = $this->createAuthor('Стругацкий', 'Аркадий');
        $second = $this->createAuthor('Стругацкий', 'Борис');

        $this->createBook('Пикник на обочине', 1972, '9785171183660', [$first->id, $second->id]);

        verify($this->search(['authorId' => $first->id]))->arrayCount(1);
        verify($this->search(['authorId' => $second->id]))->arrayCount(1);
    }

    public function testCombinesFilters(): void
    {
        $lem = $this->createAuthor('Лем', 'Станислав');
        $this->createBook('Солярис', 1961, '9785171183660', [$lem->id]);
        $this->createBook('Солярис', 1970, '5170624956', [$lem->id]);

        $models = $this->search(['title' => 'Солярис', 'year' => 1970]);

        verify($models)->arrayCount(1);
        verify($models[0]->year)->equals(1970);
    }

    /**
     * Нечисловой год — это ошибка фильтра, а не повод показать весь каталог.
     */
    public function testInvalidFilterReturnsNothing(): void
    {
        $this->createBook('Солярис', 1961, '9785171183660');

        verify($this->search(['year' => 'позапрошлый']))->arrayCount(0);
    }

    /**
     * @param array<string, mixed> $filters
     * @return Book[]
     */
    private function search(array $filters): array
    {
        // Форма фильтра отправляет данные под именем модели.
        return (new BookSearch())->search(['BookSearch' => $filters])->getModels();
    }

    /**
     * @param int[] $authorIds
     */
    private function createBook(string $title, int $year, string $isbn, array $authorIds = []): Book
    {
        $book = new Book([
            'title' => $title,
            'year' => $year,
            'isbn' => $isbn,
            'authorIds' => $authorIds,
        ]);
        $book->save();

        return $book;
    }

    private function createAuthor(string $lastName, string $firstName): Author
    {
        $author = new Author(['last_name' => $lastName, 'first_name' => $firstName]);
        $author->save();

        return $author;
    }
}
