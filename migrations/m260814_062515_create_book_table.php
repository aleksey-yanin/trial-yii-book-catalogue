<?php

declare(strict_types=1);

use yii\db\Migration;

/**
 * Таблица книг.
 */
class m260814_062515_create_book_table extends Migration
{
    public function safeUp(): void
    {
        $this->createTable('{{%book}}', [
            'id' => $this->primaryKey(),
            'title' => $this->string(255)->notNull(),
            'year' => $this->smallInteger()->unsigned()->notNull(),
            'description' => $this->text()->null(),
            // ISBN хранится нормализованным (только цифры и завершающий X у ISBN-10):
            // иначе «978-5-17-118366-8» и «9785171183668» разойдутся как разные значения
            // и UNIQUE не защитит от дубля одной и той же книги.
            'isbn' => $this->string(13)->notNull(),
            // Относительный путь к загруженному файлу обложки, заполняется в CRUD.
            'cover_path' => $this->string(255)->null(),
            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
        ], $this->tableOptions());

        $this->createIndex('idx-book-isbn', '{{%book}}', 'isbn', true);
        // По году группируется отчёт «ТОП-10 авторов за год».
        $this->createIndex('idx-book-year', '{{%book}}', 'year');
    }

    public function safeDown(): void
    {
        $this->dropTable('{{%book}}');
    }

    private function tableOptions(): ?string
    {
        return $this->db->driverName === 'mysql'
            ? 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB'
            : null;
    }
}
