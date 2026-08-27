<?php

declare(strict_types=1);

namespace app\components\filters;

use Yii;
use yii\base\Action;
use yii\base\ActionFilter;
use yii\caching\CacheInterface;
use yii\di\Instance;
use yii\web\Request;
use yii\web\TooManyRequestsHttpException;

/**
 * Ограничение числа запросов с одного адреса.
 *
 * Фильтр общий и о подписке ничего не знает: он считает обращения к действию и после
 * исчерпания лимита отвечает 429. Окно фиксированное — отсчитывается от первого запроса
 * в серии, поэтому счётчик не продлевается бесконечно самими же попытками.
 *
 * Счётчик живёт в кэше, а не в БД: точность здесь не нужна, а таблица ради него —
 * лишняя миграция и лишний запрос на каждое обращение.
 */
final class RateLimitFilter extends ActionFilter
{
    /**
     * Сколько запросов разрешено за окно.
     */
    public int $limit = 10;

    /**
     * Длина окна в секундах.
     */
    public int $window = 3600;

    public string $message = 'Слишком много запросов. Попробуйте позже.';

    /**
     * @var CacheInterface|string|array<string, mixed>
     */
    public $cache = 'cache';

    private CacheInterface $_cache;

    public function init(): void
    {
        parent::init();

        /** @var CacheInterface $cache */
        $cache = Instance::ensure($this->cache, CacheInterface::class);
        $this->_cache = $cache;
    }

    /**
     * @param Action $action
     * @throws TooManyRequestsHttpException
     */
    public function beforeAction($action): bool
    {
        $key = $this->buildKey($action->getUniqueId());
        $now = time();

        $state = $this->_cache->get($key);
        $expiresAt = is_array($state) ? (int) ($state['expiresAt'] ?? 0) : 0;
        $count = is_array($state) && $expiresAt > $now ? (int) ($state['count'] ?? 0) : 0;

        if ($expiresAt <= $now) {
            $expiresAt = $now + $this->window;
        }

        if ($count >= $this->limit) {
            throw new TooManyRequestsHttpException($this->message);
        }

        $this->_cache->set(
            $key,
            ['count' => $count + 1, 'expiresAt' => $expiresAt],
            $expiresAt - $now,
        );

        return true;
    }

    /**
     * Ключ счётчика: действие плюс адрес обратившегося.
     *
     * Вне веб-запроса адреса нет (консоль, функциональные тесты), и все обращения
     * ложатся на один ключ — для счётчика это допустимо.
     *
     * @return array<int, string>
     */
    private function buildKey(string $actionId): array
    {
        $request = Yii::$app->getRequest();
        $address = $request instanceof Request ? $request->getUserIP() : null;

        return [self::class, $actionId, $address ?? 'unknown'];
    }
}
