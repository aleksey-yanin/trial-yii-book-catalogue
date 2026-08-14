<?php

declare(strict_types=1);

use yii\db\Migration;

/**
 * Единственная учётная запись приложения. Интерфейса регистрации нет, поэтому
 * пользователь заводится при инициализации проекта.
 */
class m260814_082612_seed_admin_user extends Migration
{
    private const USERNAME = 'admin';

    public function safeUp(): void
    {
        $security = Yii::$app->security;
        // Пароль берётся из окружения: держать его в коде репозитория незачем.
        $password = getenv('ADMIN_PASSWORD') ?: 'admin';
        $now = time();

        $this->insert('{{%user}}', [
            'username' => self::USERNAME,
            'password_hash' => $security->generatePasswordHash($password),
            'auth_key' => $security->generateRandomString(),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function safeDown(): void
    {
        $this->delete('{{%user}}', ['username' => self::USERNAME]);
    }
}
