FROM php:8.5-fpm

# UID/GID хоста, чтобы файлы, созданные внутри контейнера (миграции, генерация Gii,
# загруженные обложки), принадлежали пользователю хоста, а не root.
ARG UID=1000
ARG GID=1000

RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libicu-dev \
        libzip-dev \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
        default-mysql-client \
    && rm -rf /var/lib/apt/lists/*

# OPcache в php:8.5 уже вкомпилирован статически, ставить его отдельно нельзя —
# сборка падает на install-modules. Достаточно включить его в php.ini.
# Параллельная сборка (-j) не используется: libtool гонится за созданием .libs.
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_mysql intl zip gd

# Xdebug нужен для покрытия PHPUnit; по умолчанию выключен через XDEBUG_MODE=off,
# чтобы не замедлять обычные запросы (см. docker-compose.yml).
RUN pecl install xdebug && docker-php-ext-enable xdebug

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY php.ini /usr/local/etc/php/conf.d/99-app.ini

# www-data в образе имеет uid 33 — переводим на uid хоста.
RUN groupmod -g "${GID}" www-data && usermod -u "${UID}" -g "${GID}" www-data \
    && mkdir -p /var/www/.composer && chown -R www-data:www-data /var/www

WORKDIR /app
USER www-data
