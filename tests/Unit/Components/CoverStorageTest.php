<?php

declare(strict_types=1);

namespace app\tests\Unit\Components;

use Yii;
use app\components\CoverStorage;
use yii\helpers\FileHelper;
use yii\web\UploadedFile;

final class CoverStorageTest extends \Codeception\Test\Unit
{
    private const BASE_PATH = '@runtime/cover-storage-test';

    protected function _after(): void
    {
        $path = Yii::getAlias(self::BASE_PATH);

        if (is_dir($path)) {
            FileHelper::removeDirectory($path);
        }
    }

    public function testSaveCreatesFileAndReturnsName(): void
    {
        $storage = $this->createStorage();

        $name = $storage->save($this->uploadedFile('cover.png'));

        verify($name)->stringEndsWith('.png');
        verify(is_file(Yii::getAlias(self::BASE_PATH) . '/' . $name))->true();
    }

    /**
     * Имена генерируются случайно: исходные имена у разных книг совпадают сплошь и рядом,
     * и второй файл затирал бы первый.
     */
    public function testSaveGivesDifferentNamesToSameFileName(): void
    {
        $storage = $this->createStorage();

        $first = $storage->save($this->uploadedFile('cover.png'));
        $second = $storage->save($this->uploadedFile('cover.png'));

        verify($first)->notEquals($second);
        verify(is_file(Yii::getAlias(self::BASE_PATH) . '/' . $first))->true();
        verify(is_file(Yii::getAlias(self::BASE_PATH) . '/' . $second))->true();
    }

    public function testSaveKeepsExtension(): void
    {
        $storage = $this->createStorage();

        verify($storage->save($this->uploadedFile('picture.jpg')))->stringEndsWith('.jpg');
    }

    public function testDeleteRemovesFile(): void
    {
        $storage = $this->createStorage();
        $name = $storage->save($this->uploadedFile('cover.png'));

        $storage->delete($name);

        verify(is_file(Yii::getAlias(self::BASE_PATH) . '/' . $name))->false();
    }

    /**
     * Запись в БД может пережить файл — удаление книги не должно из-за этого падать.
     */
    public function testDeleteIgnoresMissingFile(): void
    {
        $storage = $this->createStorage();

        $storage->delete('nothing-here.png');
        $storage->delete(null);
        $storage->delete('');

        verify(true)->true();
    }

    /**
     * Имя из БД не должно позволять добраться до файлов вне каталога загрузок.
     */
    public function testDeleteRefusesPathTraversal(): void
    {
        $storage = $this->createStorage();
        $outside = Yii::getAlias('@runtime') . '/do-not-touch.txt';
        file_put_contents($outside, 'важное');

        $storage->delete('../do-not-touch.txt');

        verify(is_file($outside))->true();
        unlink($outside);
    }

    public function testGetUrlReturnsNullWhenNoCover(): void
    {
        $storage = $this->createStorage();

        verify($storage->getUrl(null))->null();
        verify($storage->getUrl(''))->null();
        verify($storage->getUrl('missing.png'))->null();
    }

    public function testGetUrlPointsToSavedFile(): void
    {
        $storage = $this->createStorage();
        $name = $storage->save($this->uploadedFile('cover.png'));

        verify($storage->getUrl($name))->stringEndsWith('/uploads/covers/' . $name);
    }

    private function createStorage(): CoverStorage
    {
        return new CoverStorage(['basePath' => self::BASE_PATH]);
    }

    /**
     * UploadedFile::saveAs() опирается на move_uploaded_file(), который вне настоящего
     * HTTP-запроса всегда возвращает false. Поэтому файл подменяется двойником,
     * копирующим временный файл — проверяем логику хранилища, а не PHP.
     */
    private function uploadedFile(string $name): UploadedFile
    {
        $temp = tempnam(sys_get_temp_dir(), 'cover');
        file_put_contents($temp, 'fake image');

        $file = $this->createMock(UploadedFile::class);
        $file->name = $name;
        $file->tempName = $temp;
        $file->method('getExtension')->willReturn(pathinfo($name, PATHINFO_EXTENSION));
        $file->method('saveAs')->willReturnCallback(
            static fn (string $target): bool => copy($temp, $target),
        );

        return $file;
    }
}
