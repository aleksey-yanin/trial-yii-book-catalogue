<?php

declare(strict_types=1);

namespace app\tests\Functional;

use app\models\Author;
use app\models\Book;
use app\models\Subscription;
use app\models\User;
use app\tests\Support\FunctionalTester;
use yii\helpers\Url;

final class SubscriptionCest
{
    private const PHONE = '+7 (999) 123-45-67';

    public function guestSeesSubscribeButtonOnAuthorPage(FunctionalTester $I): void
    {
        $author = $this->createAuthor();

        $I->amOnRoute('author/view', ['id' => $author->id]);

        $I->see('Подписаться');
        $I->seeElement('[data-subscription-author-id="' . $author->id . '"]');
    }

    public function guestSeesSubscribeButtonForEveryAuthorOfBook(FunctionalTester $I): void
    {
        $first = $this->createAuthor('Стругацкий');
        $second = $this->createAuthor('Стругацкий2');
        $book = $this->createBook([$first->id, $second->id]);

        $I->amOnRoute('book/view', ['id' => $book->id]);

        $I->seeElement('[data-subscription-author-id="' . $first->id . '"]');
        $I->seeElement('[data-subscription-author-id="' . $second->id . '"]');
    }

    /**
     * Подписка — возможность гостя, авторизованному кнопка не нужна.
     */
    public function loggedInUserDoesNotSeeSubscribeButton(FunctionalTester $I): void
    {
        $author = $this->createAuthor();
        $I->amLoggedInAs($this->createUser());

        $I->amOnRoute('author/view', ['id' => $author->id]);

        $I->dontSeeElement('[data-subscription-author-id]');
    }

    public function guestSubscribes(FunctionalTester $I): void
    {
        $author = $this->createAuthor();

        $this->post($I, $author->id, self::PHONE);

        $I->seeResponseCodeIs(200);
        $I->assertSame(1, (int) Subscription::find()->count());
        // Номер сохраняется нормализованным.
        $I->assertSame('79991234567', Subscription::find()->one()->phone);
    }

    public function subscribeReturnsSuccessJson(FunctionalTester $I): void
    {
        $author = $this->createAuthor();

        $this->post($I, $author->id, self::PHONE);

        $I->assertStringContainsString('"success":true', $I->grabPageSource());
        $I->assertStringContainsString('Подписка оформлена!', $I->grabPageSource());
    }

    /**
     * Повторная подписка тем же номером в другом написании должна быть отклонена.
     */
    public function duplicateSubscriptionIsRejected(FunctionalTester $I): void
    {
        $author = $this->createAuthor();

        $this->post($I, $author->id, self::PHONE);
        $this->post($I, $author->id, '89991234567');

        $I->assertStringContainsString('"success":false', $I->grabPageSource());
        $I->assertStringContainsString('уже подписаны', $I->grabPageSource());
        $I->assertSame(1, (int) Subscription::find()->count());
    }

    public function invalidPhoneIsRejected(FunctionalTester $I): void
    {
        $author = $this->createAuthor();

        $this->post($I, $author->id, '123');

        $I->assertStringContainsString('"success":false', $I->grabPageSource());
        $I->assertSame(0, (int) Subscription::find()->count());
    }

    public function unknownAuthorIsRejected(FunctionalTester $I): void
    {
        $this->post($I, 999999, self::PHONE);

        $I->assertStringContainsString('"success":false', $I->grabPageSource());
        $I->assertSame(0, (int) Subscription::find()->count());
    }

    /**
     * Сокрытие кнопки не должно быть единственной защитой: прямой POST тоже отклоняется.
     */
    public function loggedInUserCannotSubscribe(FunctionalTester $I): void
    {
        $author = $this->createAuthor();
        $I->amLoggedInAs($this->createUser());

        $this->post($I, $author->id, self::PHONE);

        $I->seeResponseCodeIs(403);
        $I->assertSame(0, (int) Subscription::find()->count());
    }

    public function getRequestIsNotAllowed(FunctionalTester $I): void
    {
        $I->amOnPage(Url::to(['/subscription/create']));

        $I->seeResponseCodeIs(405);
    }

    private function post(FunctionalTester $I, int $authorId, string $phone): void
    {
        $I->sendAjaxPostRequest(Url::to(['/subscription/create']), [
            'Subscription' => ['author_id' => $authorId, 'phone' => $phone],
        ]);
    }

    private function createUser(): User
    {
        $user = new User(['username' => 'tester']);
        $user->setPassword('secret');
        $user->generateAuthKey();
        $user->save();

        return $user;
    }

    private function createAuthor(string $lastName = 'Лем'): Author
    {
        $author = new Author(['last_name' => $lastName, 'first_name' => 'Имя']);
        $author->save();

        return $author;
    }

    /**
     * @param int[] $authorIds
     */
    private function createBook(array $authorIds): Book
    {
        $book = new Book([
            'title' => 'Солярис',
            'year' => 1961,
            'isbn' => '9785171183660',
            'authorIds' => $authorIds,
        ]);
        $book->save();

        return $book;
    }
}
