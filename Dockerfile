# ---- Stage 1: Composer ----
FROM dunglas/frankenphp:1-php8.3-alpine AS composer-builder
WORKDIR /app

COPY --from=composer:2.8 /usr/bin/composer /usr/bin/composer
RUN install-php-extensions pdo_sqlite intl zip xsl gmp

COPY composer.json composer.lock symfony.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --optimize-autoloader

COPY . .
RUN composer dump-autoload --optimize --classmap-authoritative

# ---- Stage 2: Runtime ----
FROM dunglas/frankenphp:1-php8.3-alpine
ENV APP_ENV=prod COMPOSER_ALLOW_SUPERUSER=1 SERVER_NAME=:80

WORKDIR /app
RUN install-php-extensions pdo_sqlite opcache intl zip gd xsl gmp apcu

COPY --from=composer-builder /app/vendor ./vendor
COPY . .

RUN mkdir -p var/cache var/log var/tmp public/uploads && \
    chown -R www-data:www-data var public/uploads && \
    chmod -R 775 var public/uploads

RUN echo "opcache.enable=1" >> /usr/local/etc/php/conf.d/opcache.ini && \
    echo "opcache.memory_consumption=256" >> /usr/local/etc/php/conf.d/opcache.ini && \
    echo "opcache.max_accelerated_files=20000" >> /usr/local/etc/php/conf.d/opcache.ini && \
    echo "opcache.validate_timestamps=0" >> /usr/local/etc/php/conf.d/opcache.ini

RUN php bin/console cache:clear --no-warmup --env=prod && php bin/console cache:warmup --env=prod || true

EXPOSE 80
HEALTHCHECK --interval=30s --timeout=3s CMD curl -f http://localhost/ || exit 1
