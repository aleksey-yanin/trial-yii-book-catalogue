<?php

declare(strict_types=1);

namespace app\tests\Support;

use yii\httpclient\Request;
use yii\httpclient\Response;
use yii\httpclient\Transport;

/**
 * Транспорт-заглушка для yii\httpclient: отдаёт заранее заданный ответ и запоминает запрос.
 *
 * Позволяет проверять обёртки над внешними API без обращения к сети.
 */
final class FakeHttpTransport extends Transport
{
    public ?Request $lastRequest = null;

    private mixed $_body = null;

    private int $_statusCode = 200;

    /**
     * @param mixed $body Массив будет отдан как JSON, строка — как есть.
     */
    public function respondWith(mixed $body, int $statusCode = 200): self
    {
        $this->_body = $body;
        $this->_statusCode = $statusCode;

        return $this;
    }

    public function send($request): Response
    {
        $this->lastRequest = $request;

        return $request->client->createResponse(
            is_string($this->_body) ? $this->_body : (string) json_encode($this->_body),
            ['http-code' => $this->_statusCode, 'Content-Type' => 'application/json'],
        );
    }
}
