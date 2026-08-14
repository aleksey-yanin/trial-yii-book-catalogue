<?php

declare(strict_types=1);

namespace app\components\sms;

use RuntimeException;

/**
 * Сервис не принял сообщение.
 *
 * Отдельный класс, чтобы обработчик очереди мог отличить отказ шлюза от ошибки в коде.
 */
class SmsSendException extends RuntimeException
{
}
