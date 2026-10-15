FROM php:8.3-fpm-alpine AS base
RUN apk add --no-cache icu-dev oniguruma-dev libxml2-dev $PHPIZE_DEPS \
    && docker-php-ext-install pdo_mysql mbstring intl dom xml xmlwriter \
    && apk del $PHPIZE_DEPS
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
WORKDIR /app
COPY backend/composer.json backend/composer.lock* ./
RUN composer install --no-interaction --prefer-dist --no-scripts --no-autoloader
COPY backend/ ./
RUN composer dump-autoload --optimize
ENV APP_ENV=prod APP_DEBUG=0
CMD ["sh", "-c", "php bin/console app:migrate && php bin/console cache:warmup && chown -R www-data:www-data var && php-fpm -F"]
