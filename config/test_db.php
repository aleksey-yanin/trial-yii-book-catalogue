<?php

declare(strict_types=1);

$db = require __DIR__ . '/db.php';

// Отдельная база: Codeception откатывает и чистит данные между тестами,
// запускать его на рабочей базе нельзя.
$db['dsn'] = getenv('DB_TEST_DSN') ?: 'mysql:host=mysql;port=3306;dbname=book_catalogue_test';

return $db;
