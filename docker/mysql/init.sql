-- Отдельная база для тестов: Codeception чистит данные между тестами,
-- поэтому пускать его в рабочую базу нельзя.
CREATE DATABASE IF NOT EXISTS book_catalogue_test
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON book_catalogue_test.* TO 'catalogue'@'%';
FLUSH PRIVILEGES;
