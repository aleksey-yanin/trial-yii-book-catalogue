<?php

declare(strict_types=1);

use yii\db\Migration;

/**
 * Таблица авторов. ФИО разнесено на три поля: так работают сортировка по фамилии
 * и поиск по её части, которые понадобятся в списке авторов и отчёте.
 */
class m260814_062514_create_author_table extends Migration
{
    public function safeUp(): void
    {
        $this->createTable('{{%author}}', [
            'id' => $this->primaryKey(),
            'last_name' => $this->string(100)->notNull(),
            'first_name' => $this->string(100)->notNull(),
            // Отчество есть не у всех авторов.
            'middle_name' => $this->string(100)->null(),
            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
        ], $this->tableOptions());

        $this->createIndex('idx-author-last_name', '{{%author}}', 'last_name');
    }

    public function safeDown(): void
    {
        $this->dropTable('{{%author}}');
    }

    /**
     * Уникальности по ФИО намеренно нет: полные тёзки среди авторов встречаются.
     */
    private function tableOptions(): ?string
    {
        return $this->db->driverName === 'mysql'
            ? 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB'
            : null;
    }
}
