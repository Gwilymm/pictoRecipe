# ==========================================================
#  DOCKERFILE - PictoRecette (basé sur modèle Doc2Sail)
#  Symfony 7 + FrankenPHP + PostgreSQL + Tailwind + DaisyUI
# ==========================================================

# ---- Stage 1 : Builder (Composer + Node + Assets) ----
FROM dunglas/frankenphp:1-php8.3-alpine AS builder
WORKDIR /app

# Installer Composer et Node
COPY --from=composer:2.8 /usr/bin/composer /usr/bin/composer
RUN apk add --no-cache nodejs npm

# Installer extensions PHP nécessaires au build
RUN install-php-extensions pdo_pgsql pgsql intl zip xsl gmp

# Copier les fichiers de dépendances
COPY composer.json composer.lock symfony.lock ./

# Installer dépendances PHP (sans dev)
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Copier tout le reste du code
COPY . .

# Installer dépendances front-end
RUN npm ci

# Compiler Tailwind + DaisyUI + Stimulus avec Webpack Encore
RUN npm run build

# Nettoyer le cache et générer l’autoload optimisé
RUN composer dump-autoload --optimize --classmap-authoritative


# ---- Stage 2 : Runtime (FrankenPHP final) ----
FROM dunglas/frankenphp:1-php8.3-alpine
WORKDIR /app

ENV APP_ENV=prod \
    COMPOSER_ALLOW_SUPERUSER=1 \
    SERVER_NAME=:80

# Extensions PHP nécessaires en runtime
RUN install-php-extensions \
    pdo_pgsql \
    pgsql \
    opcache \
    intl \
    zip \
    gd \
    xsl \
    gmp \
    apcu

# Copier depuis le builder
COPY --from=builder /app/vendor ./vendor
COPY --from=builder /app/public ./public
COPY --from=builder /app/config ./config
COPY --from=builder /app/src ./src
COPY --from=builder /app/templates ./templates
COPY --from=builder /app/bin ./bin
COPY --from=builder /app/migrations ./migrations
COPY --from=builder /app/.env ./

# Préparer les dossiers et permissions
RUN mkdir -p var/cache var/log var/tmp public/uploads && \
    chown -R www-data:www-data var public/uploads && \
    chmod -R 775 var public/uploads

# Activer et configurer OPcache
RUN echo "opcache.enable=1" >> /usr/local/etc/php/conf.d/opcache.ini && \
    echo "opcache.memory_consumption=256" >> /usr/local/etc/php/conf.d/opcache.ini && \
    echo "opcache.max_accelerated_files=20000" >> /usr/local/etc/php/conf.d/opcache.ini && \
    echo "opcache.validate_timestamps=0" >> /usr/local/etc/php/conf.d/opcache.ini && \
    echo "upload_max_filesize=50M" > /usr/local/etc/php/conf.d/99-upload-size.ini && \
    echo "post_max_size=50M" >> /usr/local/etc/php/conf.d/99-upload-size.ini

# Warmup du cache Symfony
RUN php bin/console cache:clear --no-warmup --env=prod && \
    php bin/console cache:warmup --env=prod || true

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=3s CMD curl -f http://localhost/ || exit 1
