<?php

declare(strict_types=1);

namespace app\tests\Unit\Filters;

use app\components\filters\RateLimitFilter;
use yii\base\Action;
use yii\caching\ArrayCache;
use yii\web\Controller;
use yii\web\Request;
use yii\web\TooManyRequestsHttpException;
use Yii;

final class RateLimitFilterTest extends \Codeception\Test\Unit
{
    private ArrayCache $_cache;

    protected function _before(): void
    {
        $this->_cache = new ArrayCache();
        $this->useAddress('203.0.113.1');
    }

    protected function _after(): void
    {
        unset($_SERVER['REMOTE_ADDR']);
    }

    public function testAllowsRequestsUpToLimit(): void
    {
        $filter = $this->makeFilter(['limit' => 3]);

        verify($filter->beforeAction($this->makeAction()))->true();
        verify($filter->beforeAction($this->makeAction()))->true();
        verify($filter->beforeAction($this->makeAction()))->true();
    }

    public function testRejectsRequestOverLimit(): void
    {
        $filter = $this->makeFilter(['limit' => 2]);
        $filter->beforeAction($this->makeAction());
        $filter->beforeAction($this->makeAction());

        $this->expectException(TooManyRequestsHttpException::class);

        $filter->beforeAction($this->makeAction());
    }

    /**
     * Один назойливый посетитель не должен закрывать подписку остальным.
     */
    public function testCountsAddressesSeparately(): void
    {
        $filter = $this->makeFilter(['limit' => 1]);
        $filter->beforeAction($this->makeAction());

        $this->useAddress('203.0.113.2');

        verify($filter->beforeAction($this->makeAction()))->true();
    }

    /**
     * Разные действия считаются по отдельности: фильтр общий и может стоять не только
     * на подписке.
     */
    public function testCountsActionsSeparately(): void
    {
        $filter = $this->makeFilter(['limit' => 1]);
        $filter->beforeAction($this->makeAction('create'));

        verify($filter->beforeAction($this->makeAction('update')))->true();
    }

    /**
     * Окно фиксированное: каждая новая попытка не должна отодвигать его конец,
     * иначе счётчик жил бы, пока в него стучатся.
     */
    public function testWindowDoesNotSlide(): void
    {
        $filter = $this->makeFilter(['limit' => 5, 'window' => 600]);

        $filter->beforeAction($this->makeAction());
        $first = $this->storedState()['expiresAt'];

        $filter->beforeAction($this->makeAction());

        verify($this->storedState()['expiresAt'])->equals($first);
    }

    /**
     * После окончания окна счёт начинается заново.
     */
    public function testCounterResetsAfterWindow(): void
    {
        $filter = $this->makeFilter(['limit' => 1]);

        // Просроченное состояние подкладывается напрямую: ждать окончания окна в тесте
        // нечем, а поведение проверить нужно.
        $this->_cache->set($this->cacheKey(), ['count' => 99, 'expiresAt' => time() - 1]);

        verify($filter->beforeAction($this->makeAction()))->true();
    }

    /**
     * @param array<string, mixed> $config
     */
    private function makeFilter(array $config = []): RateLimitFilter
    {
        return new RateLimitFilter(array_merge(['cache' => $this->_cache], $config));
    }

    /**
     * Меняет адрес обратившегося.
     *
     * Компонент пересоздаётся, потому что yii\web\Request кэширует адрес в свойстве:
     * в жизни на каждый запрос приходит новый объект, а в тесте его нужно завести руками.
     */
    private function useAddress(string $address): void
    {
        $_SERVER['REMOTE_ADDR'] = $address;

        Yii::$app->set('request', [
            'class' => Request::class,
            'cookieValidationKey' => 'test',
        ]);
    }

    private function makeAction(string $id = 'create'): Action
    {
        return new Action($id, new Controller('subscription', Yii::$app));
    }

    /**
     * @return array<int, string>
     */
    private function cacheKey(string $actionId = 'create'): array
    {
        return [
            RateLimitFilter::class,
            'subscription/' . $actionId,
            (string) $_SERVER['REMOTE_ADDR'],
        ];
    }

    /**
     * @return array{count: int, expiresAt: int}
     */
    private function storedState(): array
    {
        /** @var array{count: int, expiresAt: int} $state */
        $state = $this->_cache->get($this->cacheKey());

        return $state;
    }
}
