<?php

declare(strict_types=1);

use yii\db\Migration;

/**
 * Таблица пользователей приложения.
 *
 * Гость в ней не хранится: по ТЗ гость — это неаутентифицированный посетитель.
 */
class m260814_082611_create_user_table extends Migration
{
    public function safeUp(): void
    {
        $this->createTable('{{%user}}', [
            'id' => $this->primaryKey(),
            'username' => $this->string(64)->notNull(),
            'password_hash' => $this->string(255)->notNull(),
            // Нужен для cookie «запомнить меня»: Yii сверяет его при автологине.
            'auth_key' => $this->string(32)->notNull(),
            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
        ], $this->tableOptions());

        $this->createIndex('idx-user-username', '{{%user}}', 'username', true);
    }

    public function safeDown(): void
    {
        $this->dropTable('{{%user}}');
    }

    private function tableOptions(): ?string
    {
        return $this->db->driverName === 'mysql'
            ? 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB'
            : null;
    }
}
