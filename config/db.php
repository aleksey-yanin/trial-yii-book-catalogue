<?php

declare(strict_types=1);

// Реквизиты приходят из окружения контейнера (docker-compose.yml), а не из кода:
// один и тот же конфиг работает и в веб-контейнере, и в консоли.
return [
    'class' => \yii\db\Connection::class,
    'dsn' => getenv('DB_DSN') ?: 'mysql:host=mysql;port=3306;dbname=book_catalogue',
    'username' => getenv('DB_USERNAME') ?: 'catalogue',
    'password' => getenv('DB_PASSWORD') ?: 'catalogue',
    'charset' => 'utf8mb4',

    // Schema cache options (for production environment)
    //'enableSchemaCache' => true,
    //'schemaCacheDuration' => 60,
    //'schemaCache' => 'cache',
];
