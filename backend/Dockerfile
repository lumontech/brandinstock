# syntax=docker/dockerfile:1

# --- Dipendenze PHP (senza pacchetti di sviluppo) ---
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --ignore-platform-req=ext-*
COPY . .
RUN composer dump-autoload --optimize --classmap-authoritative --no-dev

# --- Runtime PHP-FPM ---
FROM php:8.5-fpm-alpine

RUN apk add --no-cache icu-libs libpq \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS icu-dev postgresql-dev \
    && docker-php-ext-install -j"$(nproc)" pdo_pgsql intl opcache bcmath \
    && apk del .build-deps

COPY docker/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/fpm.conf /usr/local/etc/php-fpm.d/zz-app.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint

WORKDIR /var/www/html
COPY --from=vendor --chown=root:root /app /var/www/html

# Il codice è di root e in sola lettura per il processo PHP: solo storage/ e cache sono scrivibili.
RUN chmod +x /usr/local/bin/entrypoint \
    && rm -f .env \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R u=rwX,g=rX,o= storage bootstrap/cache

USER www-data
ENTRYPOINT ["entrypoint"]
CMD ["php-fpm"]
