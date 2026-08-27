<?php

declare(strict_types=1);

namespace app\tests\Support;

use yii\web\UploadedFile;

/**
 * Загруженный файл для тестов: настоящий UploadedFile, у которого подменено только
 * перемещение файла.
 *
 * UploadedFile::saveAs() опирается на move_uploaded_file(), а тот вне настоящего
 * HTTP-запроса всегда возвращает false. Мок PHPUnit тут не годится: он подменяет и __get,
 * из-за чего $file->extension отдаёт null и правило `file` отвергает любой файл.
 */
final class FakeUploadedFile extends UploadedFile
{
    /**
     * Содержимое — настоящий PNG в один пиксель: правило `file` у книги проверяет
     * расширение по MIME-типу, и на произвольных байтах модель не прошла бы валидацию.
     */
    private const PNG_BASE64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    /**
     * Создаёт временный файл с картинкой и готовый к валидации объект загрузки.
     */
    public static function png(string $name = 'cover.png'): self
    {
        $temp = (string) tempnam(sys_get_temp_dir(), 'cover');
        file_put_contents($temp, (string) base64_decode(self::PNG_BASE64, true));

        return new self([
            'name' => $name,
            'tempName' => $temp,
            'type' => 'image/png',
            'size' => (int) filesize($temp),
            'error' => UPLOAD_ERR_OK,
        ]);
    }

    /**
     * @param string $file
     * @param bool $deleteTempFile
     */
    public function saveAs($file, $deleteTempFile = true): bool
    {
        return copy($this->tempName, $file);
    }
}
