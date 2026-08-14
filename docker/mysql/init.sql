-- Отдельная база для тестов: Codeception чистит данные между тестами,
-- поэтому пускать его в рабочую базу нельзя.
CREATE DATABASE IF NOT EXISTS book_catalog_test
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON book_catalog_test.* TO 'catalog'@'%';
FLUSH PRIVILEGES;
