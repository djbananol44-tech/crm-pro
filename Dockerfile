# ═══════════════════════════════════════════════════════════════════════════════
# JGGL CRM — Production Dockerfile (Multi-stage Build)
# ═══════════════════════════════════════════════════════════════════════════════
# 
# Features:
#   - Multi-stage build for minimal final image
#   - Composer dependencies pre-installed
#   - Frontend assets pre-built
#   - OPcache optimized for production
#
# Build: docker build -t jggl-crm:latest .
# ═══════════════════════════════════════════════════════════════════════════════

# ─────────────────────────────────────────────────────────────────────────────
# Stage 1: Composer Dependencies
# ─────────────────────────────────────────────────────────────────────────────
FROM composer:2 AS composer-deps

WORKDIR /app

# Copy composer files first (for better caching)
COPY composer.json composer.lock ./

# Install dependencies (no dev, no scripts, optimize autoloader)
RUN composer install \
    --no-dev \
    --no-scripts \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader

# ─────────────────────────────────────────────────────────────────────────────
# Stage 2: Frontend Build (Node.js)
# ─────────────────────────────────────────────────────────────────────────────
FROM node:20-alpine AS frontend-build

WORKDIR /app

# Copy package files
COPY package.json package-lock.json* ./

# Install dependencies
RUN npm ci --silent

# Copy source files needed for build
COPY resources ./resources
COPY vite.config.js tailwind.config.js postcss.config.js ./

# Build assets
RUN npm run build

# ─────────────────────────────────────────────────────────────────────────────
# Stage 3: Final Production Image
# ─────────────────────────────────────────────────────────────────────────────
FROM php:8.3-fpm-alpine AS production

LABEL org.opencontainers.image.source="https://github.com/djbananol44-tech/crm-pro"
LABEL org.opencontainers.image.description="JGGL CRM - AI-Powered CRM for Meta & Telegram"
LABEL org.opencontainers.image.licenses="MIT"

# Install system dependencies
RUN apk add --no-cache \
    postgresql-dev \
    libzip-dev \
    libpng-dev \
    icu-dev \
    oniguruma-dev \
    freetype-dev \
    libjpeg-turbo-dev \
    curl \
    supervisor

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_pgsql \
        pgsql \
        zip \
        gd \
        bcmath \
        intl \
        pcntl \
        opcache

# Install Redis extension
RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps \
    && rm -rf /tmp/pear

# Configure OPcache for production
RUN echo "opcache.enable=1" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.enable_cli=1" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.memory_consumption=256" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.interned_strings_buffer=16" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.max_accelerated_files=20000" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.validate_timestamps=0" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.save_comments=1" >> /usr/local/etc/php/conf.d/opcache.ini

# Configure PHP for production
RUN echo "memory_limit=512M" >> /usr/local/etc/php/conf.d/php.ini \
    && echo "max_execution_time=60" >> /usr/local/etc/php/conf.d/php.ini \
    && echo "upload_max_filesize=64M" >> /usr/local/etc/php/conf.d/php.ini \
    && echo "post_max_size=64M" >> /usr/local/etc/php/conf.d/php.ini

WORKDIR /var/www/html

# Copy application code
COPY --chown=www-data:www-data . .

# Copy composer dependencies from stage 1
COPY --from=composer-deps --chown=www-data:www-data /app/vendor ./vendor

# Copy built frontend assets from stage 2
COPY --from=frontend-build --chown=www-data:www-data /app/public/build ./public/build

# Create storage directories and set permissions
RUN mkdir -p \
        storage/logs \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/app/public \
        bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

# Generate optimized autoloader
RUN php -d memory_limit=512M /usr/bin/composer dump-autoload --optimize --classmap-authoritative 2>/dev/null || true

# Cache config & routes (will be re-cached on deploy with actual env)
# RUN php artisan config:cache && php artisan route:cache

# Cleanup
RUN rm -rf \
    .git \
    .github \
    tests \
    node_modules \
    .env.example \
    phpunit.xml \
    README.md \
    docker-compose*.yml \
    Dockerfile \
    *.sh \
    *.md

# Copy composer binary for artisan commands
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Health check
HEALTHCHECK --interval=30s --timeout=10s --start-period=30s --retries=3 \
    CMD php -r 'exit(0);' || exit 1

EXPOSE 9000

USER www-data

CMD ["php-fpm"]
