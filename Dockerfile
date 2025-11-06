# ---- Stage 1: Build (Composer + Node + Encore) ----
FROM dunglas/frankenphp:1-php8.3-alpine AS composer-builder

WORKDIR /app

# Installer Composer
COPY --from=composer:2.8 /usr/bin/composer /usr/bin/composer

# Installer outils de build
RUN apk add --no-cache git bash nodejs npm

# Installer extensions PHP nécessaires
RUN install-php-extensions \
    pdo_pgsql \
    pgsql \
    pdo_sqlite \
    intl \
    zip \
    xsl \
    gmp

# Copier uniquement les fichiers de dépendances
COPY composer.json composer.lock symfony.lock package*.json ./

# Installer dépendances PHP (sans dev)
RUN composer install \
    --no-dev \
    --no-scripts \
    --no-autoloader \
    --prefer-dist \
    --optimize-autoloader

# Installer dépendances JS
RUN npm install npm install daisyui@latest

# Copier le reste de l’application
COPY . .

# Compiler les assets Webpack Encore
RUN npm run build

# Optimiser autoload Symfony
RUN composer dump-autoload --optimize --classmap-authoritative


# ---- Stage 2: Runtime ----
FROM dunglas/frankenphp:1-php8.3-alpine

ENV APP_ENV=prod \
    COMPOSER_ALLOW_SUPERUSER=1 \
    SERVER_NAME=:80

WORKDIR /app

# Installer extensions PHP nécessaires pour la prod
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

# Copier vendor + build depuis le builder
COPY --from=composer-builder /app/vendor ./vendor
COPY --from=composer-builder /app/public/build ./public/build

# Copier le reste du code applicatif
COPY . .

# Préparer les dossiers d’exécution
RUN mkdir -p var/cache var/log var/tmp public/uploads && \
    chown -R www-data:www-data var public/uploads && \
    chmod -R 775 var public/uploads

# Configuration OPcache optimisée
RUN echo "opcache.enable=1" >> /usr/local/etc/php/conf.d/opcache.ini && \
    echo "opcache.memory_consumption=256" >> /usr/local/etc/php/conf.d/opcache.ini && \
    echo "opcache.max_accelerated_files=20000" >> /usr/local/etc/php/conf.d/opcache.ini && \
    echo "opcache.validate_timestamps=0" >> /usr/local/etc/php/conf.d/opcache.ini && \
    echo "upload_max_filesize=50M" > /usr/local/etc/php/conf.d/99-upload-size.ini && \
    echo "post_max_size=50M" >> /usr/local/etc/php/conf.d/99-upload-size.ini

RUN php bin/console tailwind:build --env=prod || true
# Warmup cache Symfony (prod)
RUN php bin/console cache:clear --no-warmup --env=prod && \
    php bin/console cache:warmup --env=prod || true

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=3s CMD curl -f http://localhost/ || exit 1
