<?php

declare(strict_types=1);

namespace app\tests\Unit\Components;

use app\components\sms\SmsPilotClient;
use app\components\sms\SmsSendException;
use app\tests\Support\FakeHttpTransport;
use yii\httpclient\Client;

final class SmsPilotClientTest extends \Codeception\Test\Unit
{
    public function testSendsExpectedParameters(): void
    {
        $transport = $this->transport(['send' => [['server_id' => '1', 'status' => '0']]]);
        $client = $this->client($transport);

        $client->send('79991234567', 'Новая книга');

        $data = $transport->lastRequest->getData();

        verify($data['to'])->equals('79991234567');
        verify($data['send'])->equals('Новая книга');
        verify($data['apikey'])->equals('test-key');
        verify($data['format'])->equals('json');
    }

    public function testUsesPostMethod(): void
    {
        $transport = $this->transport(['send' => []]);

        $this->client($transport)->send('79991234567', 'Текст');

        verify($transport->lastRequest->getMethod())->equals('POST');
    }

    public function testSuccessfulResponseDoesNotThrow(): void
    {
        $client = $this->client($this->transport(['send' => [['server_id' => '7', 'status' => '0']]]));

        $client->send('79991234567', 'Текст');

        verify(true)->true();
    }

    /**
     * Главная особенность smspilot: отказ приходит с HTTP 200 и полем error в теле.
     * Если судить только по коду ответа, несостоявшаяся отправка выглядела бы успешной.
     */
    public function testTreatsErrorInBodyAsFailure(): void
    {
        $client = $this->client($this->transport([
            'error' => ['code' => '104', 'description' => 'Неверный ключ'],
        ]));

        $this->expectException(SmsSendException::class);
        $this->expectExceptionMessage('Неверный ключ');

        $client->send('79991234567', 'Текст');
    }

    public function testIncludesServiceErrorCode(): void
    {
        $client = $this->client($this->transport([
            'error' => ['code' => '104', 'description' => 'Неверный ключ'],
        ]));

        try {
            $client->send('79991234567', 'Текст');
            self::fail('Ожидалось исключение.');
        } catch (SmsSendException $exception) {
            verify($exception->getMessage())->stringContainsString('104');
        }
    }

    public function testTreatsHttpErrorAsFailure(): void
    {
        $client = $this->client($this->transport([], 500));

        $this->expectException(SmsSendException::class);

        $client->send('79991234567', 'Текст');
    }

    public function testTreatsUnexpectedBodyAsFailure(): void
    {
        $client = $this->client($this->transport('не json'));

        $this->expectException(SmsSendException::class);

        $client->send('79991234567', 'Текст');
    }

    public function testSendsSenderNameWhenConfigured(): void
    {
        $transport = $this->transport(['send' => []]);
        $client = $this->client($transport);
        $client->from = 'CATALOGUE';

        $client->send('79991234567', 'Текст');

        verify($transport->lastRequest->getData()['from'])->equals('CATALOGUE');
    }

    private function client(FakeHttpTransport $transport): SmsPilotClient
    {
        $client = new SmsPilotClient(['apiKey' => 'test-key']);
        $client->setClient(new Client(['transport' => $transport]));

        return $client;
    }

    /**
     * Подменённый транспорт: сеть в тестах не нужна, а запрос можно рассмотреть.
     */
    private function transport(mixed $body, int $statusCode = 200): FakeHttpTransport
    {
        return (new FakeHttpTransport())->respondWith($body, $statusCode);
    }
}
