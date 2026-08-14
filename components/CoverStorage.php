<?php

declare(strict_types=1);

namespace app\components;

use Yii;
use yii\base\Component;
use yii\base\InvalidConfigException;
use yii\helpers\FileHelper;
use yii\web\UploadedFile;

/**
 * Хранилище обложек книг — единственное место, знающее о файловой системе.
 *
 * Модель хранит только относительный путь, а контроллеры не работают с файлами напрямую:
 * так замену хранилища (например, на S3) можно сделать в одном классе.
 */
class CoverStorage extends Component
{
    /**
     * Каталог загрузок. Задаётся алиасом, чтобы путь одинаково разрешался
     * в веб-приложении и в консоли.
     */
    public string $basePath = '@webroot/uploads/covers';

    /**
     * Базовый URL для отдачи обложек браузеру.
     */
    public string $baseUrl = '@web/uploads/covers';

    /**
     * Сохраняет загруженный файл и возвращает относительный путь для book.cover_path.
     *
     * Имя генерируется случайным: исходное может содержать кириллицу, пробелы и
     * повторяться у разных книг.
     */
    public function save(UploadedFile $file): string
    {
        $directory = $this->resolveBasePath();
        FileHelper::createDirectory($directory);

        $name = Yii::$app->security->generateRandomString(16) . '.' . $file->getExtension();

        if (!$file->saveAs($directory . DIRECTORY_SEPARATOR . $name)) {
            throw new \RuntimeException('Не удалось сохранить файл обложки.');
        }

        return $name;
    }

    /**
     * Удаляет файл обложки. Отсутствие файла ошибкой не считается: запись в БД могла
     * пережить файл, и это не повод ронять удаление книги.
     */
    public function delete(?string $name): void
    {
        $path = $this->resolvePath($name);

        if ($path !== null && is_file($path)) {
            @unlink($path);
        }
    }

    /**
     * URL обложки для представлений; null — если обложки нет.
     *
     * Наличие файла проверяется: запись в БД может пережить файл, и представление
     * не должно показывать битую картинку.
     */
    public function getUrl(?string $name): ?string
    {
        $path = $this->resolvePath($name);

        if ($path === null || !is_file($path)) {
            return null;
        }

        return Yii::getAlias($this->baseUrl) . '/' . $name;
    }

    /**
     * Полный путь к файлу или null, если имя пустое либо ведёт за пределы каталога загрузок.
     */
    private function resolvePath(?string $name): ?string
    {
        if ($name === null || $name === '') {
            return null;
        }

        // Имя приходит из БД, но обращаться к произвольному файлу по «../../» нельзя.
        if (basename($name) !== $name) {
            return null;
        }

        return $this->resolveBasePath() . DIRECTORY_SEPARATOR . $name;
    }

    private function resolveBasePath(): string
    {
        $path = Yii::getAlias($this->basePath, false);

        if ($path === false) {
            throw new InvalidConfigException("Не удалось разрешить алиас «{$this->basePath}».");
        }

        return $path;
    }
}
