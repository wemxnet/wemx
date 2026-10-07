# syntax=docker/dockerfile:1.7
#
# WemX production image: Nginx + PHP-FPM + queue worker + scheduler in one container,
# managed by Supervisor. Guide: https://v3.wemx.net/docs/install-guides/docker
#
# Build:  docker build -f docker/Dockerfile -t wemx:local .
# Run:    docker compose up -d   (see docker-compose.yml)

ARG PHP_VERSION=8.3
ARG NODE_VERSION=20

############################
# 1. Frontend assets (Vite)
############################
FROM node:${NODE_VERSION}-alpine AS assets
WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund

# Tailwind scans Blade views in resources/ and extensions/, so copy the whole source tree.
COPY . .
RUN npm run build

############################
# 2. Runtime image
############################
FROM php:${PHP_VERSION}-fpm-alpine AS app

ARG WEMX_VERSION=""

# System packages: web server, process manager and tools used by the entrypoint.
RUN apk add --no-cache nginx supervisor bash curl tzdata unzip rsync su-exec

# PHP extensions required by WemX (ext-soap and ext-zip are hard requirements in composer.json).
COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions \
        bcmath exif gd intl opcache pcntl soap zip \
        pdo_mysql pdo_pgsql pdo_sqlite \
        redis

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

WORKDIR /var/www/html

# PHP dependencies first, for better layer caching.
COPY composer.json composer.lock ./
RUN composer install \
        --no-dev --no-interaction --no-progress --prefer-dist \
        --no-scripts --no-autoloader

# Application source and built assets.
COPY . .
COPY --from=assets /app/public/build ./public/build

# Optional: stamp the version shown in the admin area (e.g. --build-arg WEMX_VERSION=v3-beta-0.1.1).
RUN if [ -n "$WEMX_VERSION" ]; then \
        sed -i "s/^    'version' => '.*',/    'version' => '${WEMX_VERSION}',/" config/app.php; \
    fi

# Build-time Laravel steps run against a throwaway in-memory SQLite database and without an
# install flag, so no real database is needed while building the image.
RUN set -eux; \
    export APP_ENV=production APP_INSTALLED=false DB_CONNECTION=sqlite DB_DATABASE=:memory: \
           CACHE_STORE=array SESSION_DRIVER=array QUEUE_CONNECTION=sync; \
    composer dump-autoload --optimize --no-dev --no-scripts; \
    php artisan package:discover --ansi; \
    php artisan vendor:publish --tag=admin --force --ansi; \
    php artisan vendor:publish --tag=client --force --ansi; \
    rm -rf bootstrap/cache/config.php storage/logs/*.log

# Keep pristine copies of content that lives on volumes, so the entrypoint can seed or
# refresh them (bundled extensions, theme assets and default images) on every start.
RUN mkdir -p /opt/wemx \
    && cp -a extensions /opt/wemx/extensions \
    && cp -a public/assets /opt/wemx/public-assets \
    && cp -a storage /opt/wemx/storage

# Configuration for Nginx, PHP, PHP-FPM and Supervisor.
COPY docker/nginx.conf /etc/nginx/http.d/default.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/zz-wemx.ini
COPY docker/php-fpm.conf /usr/local/etc/php-fpm.d/zz-wemx.conf
COPY docker/supervisord.conf /etc/supervisord.conf
COPY docker/entrypoint.sh /usr/local/bin/wemx-entrypoint
RUN chmod +x /usr/local/bin/wemx-entrypoint \
    && rm -f /etc/nginx/http.d/default.conf.apk-new \
    && mkdir -p /config /run/nginx /var/log/supervisor \
    && chown -R www-data:www-data storage bootstrap/cache public/assets extensions /config

# Writable, persistent locations (declared again in docker-compose.yml as named volumes).
VOLUME ["/config", "/var/www/html/storage", "/var/www/html/extensions", "/var/www/html/public/assets"]

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=5s --start-period=60s --retries=3 \
    CMD curl -fsS http://127.0.0.1/up >/dev/null || exit 1

ENTRYPOINT ["wemx-entrypoint"]
CMD ["supervisord", "-c", "/etc/supervisord.conf"]
