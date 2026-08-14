<?php

declare(strict_types=1);

namespace app\tests\Unit\Components;

use app\components\DemoDataSeeder;
use app\components\validators\IsbnValidator;
use app\models\Author;
use app\models\Book;
use app\models\TopAuthorsReport;
use yii\base\DynamicModel;

final class DemoDataSeederTest extends \Codeception\Test\Unit
{
    /**
     * Ряд, ради которого сидер и написан: отчёт должен выглядеть показательно.
     */
    private const EXPECTED_DISTRIBUTION = [8, 6, 5, 4, 3, 3, 2, 2, 1, 1];

    public function testCreatesAuthorsAndBooks(): void
    {
        $result = (new DemoDataSeeder())->seed();

        verify($result['authors'])->equals(15);
        verify($result['books'])->greaterThanOrEqual(50);
        verify(Author::find()->count())->equals(15);
        verify((int) Book::find()->count())->equals($result['books']);
    }

    /**
     * Проверка сидера и отчёта на стыке: именно так данные увидит посетитель.
     */
    public function testReportOnSeededDataMatchesDistribution(): void
    {
        (new DemoDataSeeder())->seed();

        $rows = (new TopAuthorsReport(['year' => DemoDataSeeder::REPORT_YEAR]))->rows();

        verify(array_column($rows, 'booksCount'))->equals(self::EXPECTED_DISTRIBUTION);
    }

    public function testTopAuthorIsFirstInDistribution(): void
    {
        (new DemoDataSeeder())->seed();

        $rows = (new TopAuthorsReport(['year' => DemoDataSeeder::REPORT_YEAR]))->rows();

        verify($rows[0]['booksCount'])->equals(8);
        verify($rows[1]['booksCount'])->equals(6);
    }

    /**
     * Показательный год должен быть самым свежим, иначе отчёт по умолчанию
     * откроется на скучных данных.
     */
    public function testReportYearIsTheLatestInCatalogue(): void
    {
        (new DemoDataSeeder())->seed();

        verify(TopAuthorsReport::defaultYear())->equals(DemoDataSeeder::REPORT_YEAR);
    }

    /**
     * Книги других лет нужны, чтобы было видно: отчёт фильтрует по году.
     */
    public function testCatalogueHasBooksOfSeveralYears(): void
    {
        (new DemoDataSeeder())->seed();

        verify(count(TopAuthorsReport::availableYears()))->greaterThan(1);
    }

    /**
     * Книги создаются через модель, поэтому обязаны иметь корректный ISBN —
     * иначе часть записей молча не сохранилась бы.
     */
    public function testEveryIsbnIsValid(): void
    {
        (new DemoDataSeeder())->seed();

        foreach (Book::find()->select('isbn')->column() as $isbn) {
            $model = new DynamicModel(['isbn' => $isbn]);
            $model->addRule('isbn', IsbnValidator::class);
            $model->validate();

            verify($model->hasErrors('isbn'))->false();
        }
    }

    public function testAllBooksHaveAuthors(): void
    {
        (new DemoDataSeeder())->seed();

        foreach (Book::find()->with('authors')->all() as $book) {
            verify($book->authors)->notEmpty();
        }
    }

    public function testCatalogueIsEmptyReflectsState(): void
    {
        $seeder = new DemoDataSeeder();

        verify($seeder->catalogueIsEmpty())->true();

        $seeder->seed();

        verify($seeder->catalogueIsEmpty())->false();
    }

    public function testClearRemovesEverything(): void
    {
        $seeder = new DemoDataSeeder();
        $seeder->seed();

        $seeder->clear();

        verify(Book::find()->count())->equals(0);
        verify(Author::find()->count())->equals(0);
    }
}
