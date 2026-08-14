<?php

declare(strict_types=1);

namespace app\components\sms;

/**
 * Отправитель SMS.
 *
 * Интерфейс нужен, чтобы рассылку можно было проверять без обращения к внешнему сервису:
 * в тестах вместо клиента подставляется заглушка.
 */
interface SmsSenderInterface
{
    /**
     * @param string $phone Номер в каноническом виде — только цифры (см. PhoneValidator).
     *
     * @throws SmsSendException если сервис отказался принять сообщение.
     */
    public function send(string $phone, string $text): void;
}
