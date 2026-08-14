<?php

declare(strict_types=1);

namespace app\components\sms;

use Yii;
use yii\base\Component;
use yii\httpclient\Client;
use yii\httpclient\CurlTransport;
use yii\httpclient\Exception as HttpClientException;

/**
 * Обёртка над API smspilot.ru.
 *
 * Ключ-эмулятор сервиса принимает запросы и отвечает как настоящий шлюз, но сообщений
 * не рассылает — именно он используется по умолчанию.
 *
 * @see https://smspilot.ru/apikey.php
 */
class SmsPilotClient extends Component implements SmsSenderInterface
{
    public string $baseUrl = 'https://smspilot.ru/api.php';

    /**
     * Ключ API. По умолчанию — эмулятор: реальная отправка не происходит.
     */
    public string $apiKey = 'XXXXXXXXXXXXYYYYYYYYYYYYZZZZZZZZZZZZ';

    /**
     * Имя отправителя; у эмулятора значения не имеет.
     */
    public ?string $from = null;

    /**
     * Зависший запрос не должен держать обработчик очереди.
     */
    public int $timeout = 10;

    private ?Client $_client = null;

    public function getClient(): Client
    {
        if ($this->_client === null) {
            $this->_client = new Client([
                'baseUrl' => $this->baseUrl,
                // Транспорт на потоках спотыкается о TLS и даёт невнятное
                // «Failed to enable crypto»; curl отдаёт осмысленную ошибку.
                'transport' => CurlTransport::class,
            ]);
        }

        return $this->_client;
    }

    public function setClient(Client $client): void
    {
        $this->_client = $client;
    }

    public function send(string $phone, string $text): void
    {
        $data = [
            'send' => $text,
            'to' => $phone,
            'apikey' => $this->apiKey,
            'format' => 'json',
        ];

        if ($this->from !== null) {
            $data['from'] = $this->from;
        }

        try {
            $response = $this->getClient()
                ->createRequest()
                ->setMethod('POST')
                ->setUrl($this->baseUrl)
                ->setData($data)
                ->setOptions(['timeout' => $this->timeout])
                ->send();
        } catch (HttpClientException $exception) {
            // Сетевая ошибка не должна выглядеть как сбой приложения: очередь
            // повторит задание, а вызывающий код ловит один тип исключения.
            throw new SmsSendException(
                'Не удалось связаться с SMS-шлюзом: ' . $exception->getMessage(),
                0,
                $exception,
            );
        }

        if (!$response->getIsOk()) {
            throw new SmsSendException(
                'SMS-шлюз ответил кодом ' . $response->getStatusCode() . '.',
            );
        }

        try {
            $body = $response->getData();
        } catch (\Throwable $exception) {
            // Вместо JSON шлюз может отдать HTML — заглушку провайдера или страницу ошибки.
            throw new SmsSendException(
                'SMS-шлюз вернул неразбираемый ответ: ' . $exception->getMessage(),
                0,
                $exception,
            );
        }

        $this->assertNoServiceError($body);
    }

    /**
     * Главная особенность smspilot: отказ приходит с HTTP 200 и полем error в теле,
     * поэтому по одному лишь коду ответа судить об успехе нельзя.
     *
     * @param mixed $body
     */
    private function assertNoServiceError($body): void
    {
        if (!is_array($body)) {
            throw new SmsSendException('SMS-шлюз вернул неожиданный ответ.');
        }

        if (isset($body['error'])) {
            $error = $body['error'];
            $description = is_array($error) ? ($error['description'] ?? 'без описания') : (string) $error;
            $code = is_array($error) ? ($error['code'] ?? '—') : '—';

            throw new SmsSendException(sprintf('SMS-шлюз отклонил сообщение (%s): %s', $code, $description));
        }

        Yii::info(
            'SMS отправлено: ' . json_encode($body['send'] ?? [], JSON_UNESCAPED_UNICODE),
            __METHOD__,
        );
    }
}
