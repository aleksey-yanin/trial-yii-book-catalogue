<?php

declare(strict_types=1);

namespace app\tests\Unit\Models;

use app\models\Author;
use app\models\Subscription;

final class SubscriptionTest extends \Codeception\Test\Unit
{
    public function testAuthorAndPhoneAreRequired(): void
    {
        $subscription = new Subscription();

        verify($subscription->validate())->false();
        verify($subscription->hasErrors('author_id'))->true();
        verify($subscription->hasErrors('phone'))->true();
    }

    public function testRejectsUnknownAuthor(): void
    {
        $subscription = new Subscription(['author_id' => 999999, 'phone' => '79991234567']);

        verify($subscription->validate())->false();
        verify($subscription->hasErrors('author_id'))->true();
    }

    public function testStoresPhoneNormalized(): void
    {
        $subscription = $this->subscribe($this->createAuthor(), '+7 (999) 123-45-67');

        verify($subscription->phone)->equals('79991234567');
    }

    /**
     * Тот же номер в другом написании — это та же подписка.
     */
    public function testRejectsDuplicateWrittenDifferently(): void
    {
        $author = $this->createAuthor();
        $this->subscribe($author, '+7 999 123-45-67');

        $duplicate = new Subscription(['author_id' => $author->id, 'phone' => '89991234567']);

        verify($duplicate->save())->false();
        verify($duplicate->hasErrors('phone'))->true();
        verify(Subscription::find()->count())->equals(1);
    }

    /**
     * Подписка именная: один и тот же человек вправе подписаться на разных авторов.
     */
    public function testSamePhoneAllowedForAnotherAuthor(): void
    {
        $phone = '79991234567';
        $this->subscribe($this->createAuthor('Лем'), $phone);

        $second = new Subscription([
            'author_id' => $this->createAuthor('Дик')->id,
            'phone' => $phone,
        ]);

        verify($second->save())->true();
        verify(Subscription::find()->count())->equals(2);
    }

    public function testRejectsInvalidPhone(): void
    {
        $subscription = new Subscription([
            'author_id' => $this->createAuthor()->id,
            'phone' => '123',
        ]);

        verify($subscription->save())->false();
        verify($subscription->hasErrors('phone'))->true();
    }

    public function testDeletingAuthorRemovesSubscriptions(): void
    {
        $author = $this->createAuthor();
        $this->subscribe($author, '79991234567');

        $author->delete();

        verify(Subscription::find()->count())->equals(0);
    }

    public function testCreatedAtIsFilled(): void
    {
        verify($this->subscribe($this->createAuthor(), '79991234567')->created_at)->greaterThan(0);
    }

    public function testAuthorRelation(): void
    {
        $author = $this->createAuthor('Лем');

        verify($this->subscribe($author, '79991234567')->author->last_name)->equals('Лем');
    }

    /**
     * Список телефонов — то, что понадобится рассылке уведомлений.
     */
    public function testPhonesForAuthor(): void
    {
        $author = $this->createAuthor('Лем');
        $other = $this->createAuthor('Дик');

        $this->subscribe($author, '79991234567');
        $this->subscribe($author, '79997654321');
        $this->subscribe($other, '79990000000');

        $phones = Subscription::phonesForAuthor($author->id);

        sort($phones);

        verify($phones)->equals(['79991234567', '79997654321']);
    }

    private function createAuthor(string $lastName = 'Лем'): Author
    {
        $author = new Author(['last_name' => $lastName, 'first_name' => 'Имя']);
        $author->save();

        return $author;
    }

    private function subscribe(Author $author, string $phone): Subscription
    {
        $subscription = new Subscription(['author_id' => $author->id, 'phone' => $phone]);
        $subscription->save();

        return $subscription;
    }
}
