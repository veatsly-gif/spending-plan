FROM php:8.4-fpm-alpine AS base
RUN apk add --no-cache icu-dev libzip-dev postgresql-dev postgresql-client openssh-client \
    && apk add --no-cache $PHPIZE_DEPS linux-headers \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del $PHPIZE_DEPS linux-headers \
    && docker-php-ext-install intl opcache pdo_pgsql zip
WORKDIR /var/www/app
COPY docker/php/conf.d/app.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/php/conf.d/opcache.ini /usr/local/etc/php/conf.d/zz-opcache.ini

FROM base AS dev
RUN apk add --no-cache $PHPIZE_DEPS linux-headers \
    && pecl install xdebug \
    && docker-php-ext-enable xdebug \
    && apk del $PHPIZE_DEPS linux-headers
COPY docker/php/conf.d/xdebug.ini /usr/local/etc/php/conf.d/zz-xdebug.ini
COPY docker/php/conf.d/dev.ini /usr/local/etc/php/conf.d/zzz-dev.ini
CMD ["php-fpm"]

FROM base AS prod
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY composer.json composer.lock symfony.lock /var/www/app/
RUN composer install --no-dev --no-interaction --no-progress --prefer-dist --no-scripts --optimize-autoloader
COPY . /var/www/app
RUN composer dump-autoload --classmap-authoritative --no-dev --no-interaction
RUN cp .env.example .env \
    && mkdir -p var/cache var/log \
    && chown -R www-data:www-data var \
    && APP_SECRET=build_only_not_used_at_runtime \
    DATABASE_URL="postgresql://build:build@127.0.0.1:5432/build?serverVersion=16&charset=utf8" \
    REDIS_DSN="redis://127.0.0.1:6379" \
    php bin/console cache:clear --env=prod --no-debug \
    && rm -f .env
USER www-data
CMD ["php-fpm"]
