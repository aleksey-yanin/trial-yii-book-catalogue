<?php

declare(strict_types=1);

namespace app\tests\Unit;

use Yii;
use app\components\BookNotifier;
use app\components\CoverStorage;

/**
 * Классы, настроенные компонентами приложения, обязаны приходить из контейнера теми же
 * экземплярами.
 *
 * Тест закрывает конкретную грабль: пока определений в container.singletons не было,
 * автовайринг конструктора собирал второй CoverStorage с дефолтным basePath. В тестовом
 * окружении компонент пишет в @runtime, поэтому код сохранял обложки в web/uploads, а вьюхи
 * искали их в runtime — и оба каталога совпадали только в проде, где расхождение незаметно.
 */
final class DiContainerTest extends \Codeception\Test\Unit
{
    public function testCoverStorageResolvesToApplicationComponent(): void
    {
        verify(Yii::$container->get(CoverStorage::class))->same(Yii::$app->get('coverStorage'));
    }

    /**
     * Тот же экземпляр — значит и та же очередь: подменив компонент в конфиге, мы подменяем
     * его и для всех, кто получает уведомитель через конструктор.
     */
    public function testBookNotifierResolvesToApplicationComponent(): void
    {
        verify(Yii::$container->get(BookNotifier::class))->same(Yii::$app->get('bookNotifier'));
    }

    /**
     * Хранилище тестов пишет в runtime, а не в web/uploads. Если определение в контейнере
     * потеряется, проверка выше ещё может пройти на кэше, а эта — нет.
     */
    public function testResolvedCoverStorageUsesTestBasePath(): void
    {
        /** @var CoverStorage $storage */
        $storage = Yii::$container->get(CoverStorage::class);

        verify($storage->basePath)->equals('@runtime/test-uploads/covers');
    }
}
