<?php

declare(strict_types=1);

namespace app\tests\Unit\Services;

use app\models\Author;
use app\models\Subscription;
use app\services\SubscriptionService;

final class SubscriptionServiceTest extends \Codeception\Test\Unit
{
    public function testSubscribeSavesSubscription(): void
    {
        $author = $this->author();

        $result = $this->service()->subscribe($this->data($author->id, '+7 (999) 111-22-33'));

        verify($result->success)->true();
        verify($result->message)->equals('Подписка оформлена!');
        verify(Subscription::find()->where(['author_id' => $author->id])->count())->equals(1);
    }

    /**
     * Номер сохраняется нормализованным: иначе одна и та же подписка прошла бы дважды
     * в разном написании.
     */
    public function testSubscribeStoresNormalizedPhone(): void
    {
        $author = $this->author();

        $this->service()->subscribe($this->data($author->id, '8 (999) 111-22-33'));

        $subscription = Subscription::findOne(['author_id' => $author->id]);
        verify($subscription?->phone)->equals('79991112233');
    }

    public function testSubscribeRejectsDuplicate(): void
    {
        $author = $this->author();
        $service = $this->service();
        $service->subscribe($this->data($author->id, '79991112233'));

        $result = $service->subscribe($this->data($author->id, '79991112233'));

        verify($result->success)->false();
        verify($result->message)->equals('Вы уже подписаны на этого автора.');
    }

    /**
     * Тот же номер у другого автора — самостоятельная подписка, а не дубль.
     */
    public function testSubscribeAllowsSamePhoneForAnotherAuthor(): void
    {
        $service = $this->service();
        $service->subscribe($this->data($this->author()->id, '79991112233'));

        $result = $service->subscribe($this->data($this->author('Стругацкий')->id, '79991112233'));

        verify($result->success)->true();
    }

    public function testSubscribeRejectsInvalidPhone(): void
    {
        $result = $this->service()->subscribe($this->data($this->author()->id, '123'));

        verify($result->success)->false();
        verify($result->message)->notEmpty();
    }

    public function testSubscribeRejectsUnknownAuthor(): void
    {
        $result = $this->service()->subscribe($this->data(999999, '79991112233'));

        verify($result->success)->false();
        verify($result->message)->equals('Автор не найден.');
    }

    /**
     * Пустой запрос не должен ронять действие: load() ничего не заполнит, а модель
     * пожалуется на обязательные поля.
     */
    public function testSubscribeRejectsEmptyRequest(): void
    {
        $result = $this->service()->subscribe([]);

        verify($result->success)->false();
        verify($result->message)->notEmpty();
    }

    private function service(): SubscriptionService
    {
        return new SubscriptionService();
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function data(int $authorId, string $phone): array
    {
        return ['Subscription' => ['author_id' => $authorId, 'phone' => $phone]];
    }

    private function author(string $lastName = 'Лем'): Author
    {
        $author = new Author(['last_name' => $lastName, 'first_name' => 'Имя']);
        $author->save();

        return $author;
    }
}
