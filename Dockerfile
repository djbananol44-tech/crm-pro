# ═══════════════════════════════════════════════════════════════
# 🐳 CRM Pro — Production Dockerfile
# Multi-stage build для оптимального размера образа
# ═══════════════════════════════════════════════════════════════

# ─────────────────────────────────────────────────────────────
# Stage 1: Base PHP Image
# ─────────────────────────────────────────────────────────────
FROM php:8.3-fpm-alpine AS base

# Install system dependencies
RUN apk add --no-cache \
    postgresql-dev \
    libzip-dev \
    libpng-dev \
    jpeg-dev \
    freetype-dev \
    oniguruma-dev \
    icu-dev \
    libxml2-dev \
    curl \
    git \
    bash \
    supervisor \
    fcgi

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_pgsql \
        pgsql \
        zip \
        gd \
        exif \
        bcmath \
        opcache \
        pcntl \
        intl \
    && pecl install redis \
    && docker-php-ext-enable redis

# Configure PHP
COPY docker/php/local.ini /usr/local/etc/php/conf.d/local.ini

# PHP-FPM healthcheck script
RUN echo '#!/bin/sh' > /usr/local/bin/php-fpm-healthcheck \
    && echo 'SCRIPT_NAME=/ping SCRIPT_FILENAME=/ping REQUEST_METHOD=GET cgi-fcgi -bind -connect 127.0.0.1:9000 2>/dev/null | grep -q pong' >> /usr/local/bin/php-fpm-healthcheck \
    && chmod +x /usr/local/bin/php-fpm-healthcheck

# Configure PHP-FPM for healthcheck
RUN echo '[www]' >> /usr/local/etc/php-fpm.d/zz-docker.conf \
    && echo 'pm.status_path = /status' >> /usr/local/etc/php-fpm.d/zz-docker.conf \
    && echo 'ping.path = /ping' >> /usr/local/etc/php-fpm.d/zz-docker.conf \
    && echo 'ping.response = pong' >> /usr/local/etc/php-fpm.d/zz-docker.conf

WORKDIR /var/www/html

# ─────────────────────────────────────────────────────────────
# Stage 2: Composer Dependencies
# ─────────────────────────────────────────────────────────────
FROM base AS composer

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

# ─────────────────────────────────────────────────────────────
# Stage 3: Node.js Build
# ─────────────────────────────────────────────────────────────
FROM node:20-alpine AS node

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

COPY resources ./resources
COPY vite.config.js postcss.config.js tailwind.config.js ./
RUN npm run build

# ─────────────────────────────────────────────────────────────
# Stage 4: Final Application Image
# ─────────────────────────────────────────────────────────────
FROM base AS app

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copy vendor from composer stage
COPY --from=composer /var/www/html/vendor ./vendor

# Copy built assets from node stage
COPY --from=node /app/public/build ./public/build

# Copy application code
COPY --chown=www-data:www-data . .

# Generate optimized autoloader
RUN composer dump-autoload --optimize --no-dev

# Create required directories
RUN mkdir -p storage/logs \
    && mkdir -p storage/framework/cache/data \
    && mkdir -p storage/framework/sessions \
    && mkdir -p storage/framework/views \
    && mkdir -p bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# Set user
USER www-data

EXPOSE 9000

CMD ["php-fpm"]
