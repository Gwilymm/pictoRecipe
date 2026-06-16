# syntax=docker/dockerfile:1.7

# ============================================
# PictoRecette - Symfony + FrankenPHP
# Production image for ARM64 / Raspberry Pi 5
# Assets compiled inside Docker image
# ============================================

ARG PHP_VERSION=8.3
ARG FRANKENPHP_VERSION=1

# ============================================
# Base PHP image with shared extensions
# ============================================
FROM dunglas/frankenphp:${FRANKENPHP_VERSION}-php${PHP_VERSION}-alpine AS php-base

WORKDIR /app

ENV APP_ENV=prod \
    APP_DEBUG=0 \
    COMPOSER_ALLOW_SUPERUSER=1 \
    SERVER_NAME=:80 \
    DEFAULT_URI=http://localhost \
    APP_SECRET=build_time_secret_do_not_use_in_prod \
    DATABASE_URL="postgresql://app:app@database:5432/picto?serverVersion=16&charset=utf8"

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

# ============================================
# Composer builder
# ============================================
FROM php-base AS app-builder

COPY --from=composer:2.8 /usr/bin/composer /usr/bin/composer

RUN apk add --no-cache \
    git \
    unzip

# Copier uniquement les fichiers Composer d'abord pour profiter du cache Docker
COPY composer.json composer.lock symfony.lock ./

RUN composer install \
    --no-dev \
    --prefer-dist \
    --no-progress \
    --no-interaction \
    --no-scripts \
    --no-autoloader

# Copier le code complet
COPY . .

# Optimiser l'autoload maintenant que le code est présent
RUN composer dump-autoload \
    --no-dev \
    --optimize \
    --classmap-authoritative

# Compiler les assets Symfony
RUN php bin/console tailwind:build --minify \
    && php bin/console asset-map:compile

# Préparer le cache Symfony prod
RUN php bin/console cache:clear --no-warmup --env=prod \
    && php bin/console cache:warmup --env=prod

# ============================================
# Runtime image
# ============================================
FROM php-base AS runtime

# Chromium + Node nécessaires pour Browsershot/Puppeteer
RUN apk add --no-cache \
    curl \
    chromium \
    nss \
    freetype \
    harfbuzz \
    libstdc++ \
    ttf-freefont \
    nodejs \
    npm

ENV PUPPETEER_SKIP_DOWNLOAD=true \
    PUPPETEER_EXECUTABLE_PATH=/usr/bin/chromium-browser \
    NODE_PATH=/opt/node/node_modules

# Installer Puppeteer sans télécharger Chromium
ENV PUPPETEER_SKIP_DOWNLOAD=true \
    PUPPETEER_EXECUTABLE_PATH=/usr/bin/chromium-browser

RUN npm install -g --omit=dev --no-audit --no-fund puppeteer

WORKDIR /app

# Copier l'application déjà préparée
COPY --from=app-builder --chown=www-data:www-data /app /app

# Répertoires runtime
RUN mkdir -p var/cache var/log var/tmp public/uploads \
    && chown -R www-data:www-data var public/uploads \
    && chmod -R 775 var public/uploads

# Configuration PHP production
RUN echo "opcache.enable=1" > /usr/local/etc/php/conf.d/99-opcache.ini \
    && echo "opcache.memory_consumption=256" >> /usr/local/etc/php/conf.d/99-opcache.ini \
    && echo "opcache.max_accelerated_files=20000" >> /usr/local/etc/php/conf.d/99-opcache.ini \
    && echo "opcache.validate_timestamps=1">> /usr/local/etc/php/conf.d/99-opcache.ini \
    && echo "opcache.revalidate_freq=0" >> /usr/local/etc/php/conf.d/99-opcache.ini \
    && echo "upload_max_filesize=50M" > /usr/local/etc/php/conf.d/99-upload-size.ini \
    && echo "post_max_size=50M" >> /usr/local/etc/php/conf.d/99-upload-size.ini

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=3s --start-period=40s --retries=3 \
    CMD curl -f http://localhost/ || exit 1