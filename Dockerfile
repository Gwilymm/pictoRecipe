# ---- Stage 1: Composer ----
FROM dunglas/frankenphp:1-php8.3-alpine AS composer-builder
WORKDIR /app

# Installer Composer
COPY --from=composer:2.8 /usr/bin/composer /usr/bin/composer

# Installer les extensions nécessaires au build
RUN install-php-extensions \
    pdo_pgsql \
    pgsql \
    pdo_sqlite \
    intl \
    zip \
    xsl \
    gmp

# Copier uniquement les fichiers de dépendances
COPY composer.json composer.lock symfony.lock ./

# Installer les dépendances
RUN composer install \
    --no-dev \
    --no-scripts \
    --no-autoloader \
    --prefer-dist \
    --optimize-autoloader

# Copier le reste de l’application
COPY . .
RUN composer dump-autoload --optimize --classmap-authoritative


# ---- Stage 2: Runtime ----
FROM dunglas/frankenphp:1-php8.3-alpine
ENV APP_ENV=prod \
    COMPOSER_ALLOW_SUPERUSER=1 \
    SERVER_NAME=:80

WORKDIR /app

# ⚠️ INSTALLER LES EXTENSIONS POUR POSTGRES ICI AUSSI
RUN install-php-extensions \
    pdo_pgsql \
    pgsql \
    pdo_sqlite \
    opcache \
    intl \
    zip \
    gd \
    xsl \
    gmp \
    apcu

# Copier les dépendances installées depuis le builder
COPY --from=composer-builder /app/vendor ./vendor
COPY . .

# Préparer les dossiers
RUN mkdir -p var/cache var/log var/tmp public/uploads && \
    chown -R www-data:www-data var public/uploads && \
    chmod -R 775 var public/uploads

# Config OPcache
RUN echo "opcache.enable=1" >> /usr/local/etc/php/conf.d/opcache.ini && \
    echo "opcache.memory_consumption=256" >> /usr/local/etc/php/conf.d/opcache.ini && \
    echo "opcache.max_accelerated_files=20000" >> /usr/local/etc/php/conf.d/opcache.ini && \
    echo "opcache.validate_timestamps=0" >> /usr/local/etc/php/conf.d/opcache.ini && \
    echo "upload_max_filesize=50M" > /usr/local/etc/php/conf.d/99-upload-size.ini && \
    echo "post_max_size=50M" >> /usr/local/etc/php/conf.d/99-upload-size.ini

# Warmup cache Symfony
RUN php bin/console cache:clear --no-warmup --env=prod && php bin/console cache:warmup --env=prod || true

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=3s CMD curl -f http://localhost/ || exit 1
