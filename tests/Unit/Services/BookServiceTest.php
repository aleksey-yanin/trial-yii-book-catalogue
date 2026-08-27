<?php

declare(strict_types=1);

namespace app\tests\Unit\Services;

use Yii;
use app\components\BookNotifier;
use app\components\CoverStorage;
use app\models\Author;
use app\models\Book;
use app\services\BookService;
use app\tests\Support\FakeUploadedFile;
use yii\helpers\FileHelper;
use yii\web\UploadedFile;

/**
 * Функциональные тесты сюда не достают: InnerBrowser не отправляет файлы, поэтому весь
 * код обложек проверяется здесь, с двойником UploadedFile.
 */
final class BookServiceTest extends \Codeception\Test\Unit
{
    private const BASE_PATH = '@runtime/book-service-test';

    protected function _before(): void
    {
        Yii::$app->db->createCommand()->delete('{{%queue}}')->execute();
    }

    protected function _after(): void
    {
        $path = Yii::getAlias(self::BASE_PATH);

        if (is_dir($path)) {
            FileHelper::removeDirectory($path);
        }
    }

    public function testCreateSavesBookWithoutCover(): void
    {
        $book = $this->book();

        verify($this->service()->create($book))->true();
        verify($book->isNewRecord)->false();
        verify($book->cover_path)->null();
    }

    public function testCreateStoresCoverFile(): void
    {
        $book = $this->book();
        $book->coverFile = $this->uploadedFile();

        verify($this->service()->create($book))->true();
        verify($book->cover_path)->notNull();
        verify(is_file($this->path((string) $book->cover_path)))->true();
    }

    public function testCreateQueuesNotificationForEveryAuthor(): void
    {
        $book = $this->book([$this->author('Стругацкий')->id, $this->author('Стругацкая')->id]);

        $this->service()->create($book);

        verify($this->queueSize())->equals(2);
    }

    /**
     * Файл кладётся только после успешной валидации: иначе каталог загрузок копил бы
     * обложки книг, которых нет в базе.
     */
    public function testCreateStoresNothingWhenModelIsInvalid(): void
    {
        $book = $this->book();
        $book->title = '';
        $book->coverFile = $this->uploadedFile();

        verify($this->service()->create($book))->false();
        verify($book->cover_path)->null();
        verify($this->queueSize())->equals(0);
        verify(is_dir(Yii::getAlias(self::BASE_PATH)))->false();
    }

    public function testUpdateReplacesCoverAndRemovesPreviousFile(): void
    {
        $service = $this->service();
        $book = $this->book();
        $book->coverFile = $this->uploadedFile();
        $service->create($book);
        $previous = (string) $book->cover_path;

        $book->coverFile = $this->uploadedFile();
        verify($service->update($book))->true();

        verify($book->cover_path)->notEquals($previous);
        verify(is_file($this->path($previous)))->false();
        verify(is_file($this->path((string) $book->cover_path)))->true();
    }

    /**
     * Правка без новой обложки не должна стирать имеющуюся.
     */
    public function testUpdateKeepsCoverWhenNoFileUploaded(): void
    {
        $service = $this->service();
        $book = $this->book();
        $book->coverFile = $this->uploadedFile();
        $service->create($book);
        $cover = (string) $book->cover_path;

        $book->title = 'Другое название';
        $book->coverFile = null;
        verify($service->update($book))->true();

        verify($book->cover_path)->equals($cover);
        verify(is_file($this->path($cover)))->true();
    }

    /**
     * Подписка обещает сообщать о новых книгах автора, а не о правках в существующих.
     */
    public function testUpdateQueuesNothing(): void
    {
        $service = $this->service();
        $book = $this->book([$this->author()->id]);
        $service->create($book);
        Yii::$app->db->createCommand()->delete('{{%queue}}')->execute();

        $book->title = 'Другое название';
        $service->update($book);

        verify($this->queueSize())->equals(0);
    }

    /**
     * Упади сохранение — обложка ещё нужна, поэтому старый файл остаётся на месте.
     */
    public function testUpdateKeepsPreviousCoverWhenModelIsInvalid(): void
    {
        $service = $this->service();
        $book = $this->book();
        $book->coverFile = $this->uploadedFile();
        $service->create($book);
        $cover = (string) $book->cover_path;

        $book->title = '';
        $book->coverFile = $this->uploadedFile();
        verify($service->update($book))->false();

        verify(is_file($this->path($cover)))->true();
    }

    public function testDeleteRemovesBookAndCoverFile(): void
    {
        $service = $this->service();
        $book = $this->book();
        $book->coverFile = $this->uploadedFile();
        $service->create($book);
        $cover = (string) $book->cover_path;
        $id = $book->id;

        $service->delete($book);

        verify(Book::findOne($id))->null();
        verify(is_file($this->path($cover)))->false();
    }

    /**
     * cover_path снят с массового присваивания: подделанное поле формы не должно
     * ни подменять запись, ни приводить к удалению чужого файла.
     */
    public function testCoverPathIsNotMassAssignable(): void
    {
        $book = $this->book();
        $book->load(['Book' => ['cover_path' => 'forged.png']]);

        verify($book->cover_path)->null();
    }

    private function service(): BookService
    {
        return new BookService(
            new CoverStorage(['basePath' => self::BASE_PATH]),
            new BookNotifier(),
        );
    }

    private function path(string $name): string
    {
        return Yii::getAlias(self::BASE_PATH) . '/' . $name;
    }

    private function queueSize(): int
    {
        return (int) Yii::$app->db
            ->createCommand('SELECT COUNT(*) FROM {{%queue}}')
            ->queryScalar();
    }

    private function author(string $lastName = 'Лем'): Author
    {
        $author = new Author(['last_name' => $lastName, 'first_name' => 'Имя']);
        $author->save();

        return $author;
    }

    /**
     * @param int[] $authorIds
     */
    private function book(array $authorIds = []): Book
    {
        return new Book([
            'title' => 'Солярис',
            'year' => 1961,
            // Контрольная цифра настоящая: IsbnValidator её проверяет.
            'isbn' => '9785171183660',
            'authorIds' => $authorIds,
        ]);
    }

    private function uploadedFile(): UploadedFile
    {
        return FakeUploadedFile::png();
    }
}
