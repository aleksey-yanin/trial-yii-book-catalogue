<?php

declare(strict_types=1);

use yii\db\Migration;

/**
 * Подписки на новые книги автора.
 *
 * Подписчик не является пользователем приложения: по ТЗ подписывается гость,
 * и всё, что о нём известно, — номер телефона.
 */
class m260814_110103_create_subscription_table extends Migration
{
    public function safeUp(): void
    {
        $this->createTable('{{%subscription}}', [
            'id' => $this->primaryKey(),
            'author_id' => $this->integer()->notNull(),
            // Хранится нормализованным (только цифры) — см. PhoneValidator.
            'phone' => $this->string(20)->notNull(),
            'created_at' => $this->integer()->notNull(),
        ], $this->tableOptions());

        // Уникальность именно в базе: два одновременных запроса пройдут проверку
        // правилом unique и оба вставят строку — ограничение это исключает.
        $this->createIndex('idx-subscription-author_phone', '{{%subscription}}', ['author_id', 'phone'], true);

        // Рассылка на шаге 8 выбирает подписчиков по автору.
        $this->addForeignKey(
            'fk-subscription-author_id',
            '{{%subscription}}',
            'author_id',
            '{{%author}}',
            'id',
            'CASCADE',
        );
    }

    public function safeDown(): void
    {
        $this->dropTable('{{%subscription}}');
    }

    private function tableOptions(): ?string
    {
        return $this->db->driverName === 'mysql'
            ? 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB'
            : null;
    }
}
