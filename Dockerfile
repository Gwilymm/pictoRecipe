# Dockerfile optimisé pour Raspberry Pi 5 (ARM64)
# Version production - Assets pré-compilés en local

# ============================================
# Stage 1: Composer - Install PHP dependencies
# ============================================
FROM dunglas/frankenphp:1-php8.3-alpine AS composer-builder

# Configurer les DNS pour le build
RUN echo "nameserver 8.8.8.8" > /etc/resolv.conf.override || true

WORKDIR /app

# Installer Composer
COPY --from=composer:2.8 /usr/bin/composer /usr/bin/composer

# Installer les extensions PHP nécessaires pour Composer
RUN install-php-extensions \
    pdo_pgsql \
    pgsql \
    gd \
    intl \
    zip \
    xsl \
    gmp

# Copier seulement les fichiers de dépendances
COPY composer.json composer.lock symfony.lock ./

# Installer les dépendances
# Utiliser --no-dev uniquement en production (via ARG)
ARG APP_ENV=prod
RUN if [ "$APP_ENV" = "dev" ]; then \
        composer install --no-scripts --no-autoloader --prefer-dist; \
    else \
        composer install --no-dev --no-scripts --no-autoloader --prefer-dist --optimize-autoloader; \
    fi

# Copier le reste et finaliser autoload
COPY . .

RUN composer dump-autoload --optimize --classmap-authoritative

# ============================================
# Stage 2: Runtime - Image finale légère
# ============================================
FROM dunglas/frankenphp:1-php8.3-alpine AS runtime

# Variables d'environnement pour production
ENV APP_ENV=prod \
    APP_DEBUG=0 \
    COMPOSER_ALLOW_SUPERUSER=1 \
    SERVER_NAME=:80

# ---- Browsershot / Puppeteer / Chromium ----
RUN apk add --no-cache \
    chromium \
    nss \
    freetype \
    harfbuzz \
    libstdc++ \
    ttf-freefont \
    nodejs \
    npm

ENV PUPPETEER_SKIP_DOWNLOAD=true \
    PUPPETEER_EXECUTABLE_PATH=/usr/bin/chromium-browser

WORKDIR /app

RUN npm install puppeteer

# Installer uniquement les extensions PHP nécessaires (version Alpine = plus rapide)
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



# Copier les dépendances Composer depuis le builder
COPY --from=composer-builder /app/vendor ./vendor

# Copier le code de l'application
COPY . .

# Les assets doivent être pré-compilés en local et copiés
# Vérifier que public/build existe

RUN  php bin/console tailwind:build --minify
RUN php bin/console asset-map:compile 



# Créer les répertoires nécessaires et définir les permissions
RUN mkdir -p var/cache var/log var/tmp public/uploads && \
    chown -R www-data:www-data var public/uploads && \
    chmod -R 775 var public/uploads


# Configuration OPcache pour production
RUN echo "opcache.enable=1" >> /usr/local/etc/php/conf.d/opcache.ini && \
    echo "opcache.memory_consumption=256" >> /usr/local/etc/php/conf.d/opcache.ini && \
    echo "opcache.max_accelerated_files=20000" >> /usr/local/etc/php/conf.d/opcache.ini && \
    echo "opcache.validate_timestamps=0" >> /usr/local/etc/php/conf.d/opcache.ini && \
    echo "opcache.revalidate_freq=0" >> /usr/local/etc/php/conf.d/opcache.ini && \
    echo "upload_max_filesize=50M" > /usr/local/etc/php/conf.d/99-upload-size.ini && \
    echo "post_max_size=50M" >> /usr/local/etc/php/conf.d/99-upload-size.ini

# Warmup cache Symfony
RUN php bin/console cache:clear --no-warmup --env=prod && php bin/console cache:warmup --env=prod || true

EXPOSE 80

# Healthcheck
HEALTHCHECK --interval=30s --timeout=3s --start-period=40s --retries=3 \
    CMD curl -f http://localhost/ || exit 1
