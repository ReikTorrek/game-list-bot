FROM php:8.4-fpm

RUN apt-get update && apt-get install -y --no-install-recommends \
    git unzip libpq-dev libicu-dev libzip-dev $PHPIZE_DEPS \
  && docker-php-ext-install pdo_pgsql intl zip opcache \
  && pecl install redis xdebug \
  && docker-php-ext-enable redis xdebug \
  && apt-get purge -y --auto-remove $PHPIZE_DEPS \
  && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/app
COPY docker/php/conf.d/xdebug.ini /usr/local/etc/php/conf.d/99-xdebug.ini
