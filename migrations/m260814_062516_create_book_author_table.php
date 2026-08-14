<?php

declare(strict_types=1);

use yii\db\Migration;

/**
 * Связь книг и авторов: у книги может быть несколько авторов, у автора — несколько книг.
 */
class m260814_062516_create_book_author_table extends Migration
{
    public function safeUp(): void
    {
        $this->createTable('{{%book_author}}', [
            'book_id' => $this->integer()->notNull(),
            'author_id' => $this->integer()->notNull(),
        ], $this->tableOptions());

        // Составной первичный ключ заодно исключает повторную привязку того же автора.
        $this->addPrimaryKey('pk-book_author', '{{%book_author}}', ['book_id', 'author_id']);

        // Отдельный индекс по author_id: отчёт считает книги в разрезе автора,
        // а составной PK для такого запроса не подходит — author_id в нём второй.
        $this->createIndex('idx-book_author-author_id', '{{%book_author}}', 'author_id');

        // Удаление книги или автора убирает связи, не оставляя висячих строк.
        $this->addForeignKey(
            'fk-book_author-book_id',
            '{{%book_author}}',
            'book_id',
            '{{%book}}',
            'id',
            'CASCADE',
        );
        $this->addForeignKey(
            'fk-book_author-author_id',
            '{{%book_author}}',
            'author_id',
            '{{%author}}',
            'id',
            'CASCADE',
        );
    }

    public function safeDown(): void
    {
        $this->dropTable('{{%book_author}}');
    }

    private function tableOptions(): ?string
    {
        return $this->db->driverName === 'mysql'
            ? 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB'
            : null;
    }
}
