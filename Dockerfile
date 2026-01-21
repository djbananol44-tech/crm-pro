# === CRM Pro — Простой Dockerfile ===
FROM php:8.3-fpm-alpine

# Зависимости
RUN apk add --no-cache \
    postgresql-dev \
    libzip-dev \
    libpng-dev \
    icu-dev \
    oniguruma-dev \
    curl \
    git \
    nodejs \
    npm \
    supervisor

# PHP расширения
RUN docker-php-ext-install \
    pdo_pgsql \
    pgsql \
    zip \
    gd \
    bcmath \
    intl \
    pcntl \
    opcache

# Redis
RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Копируем код
COPY . .

# Права
RUN mkdir -p storage/logs storage/framework/cache storage/framework/sessions storage/framework/views bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache \
    && chown -R www-data:www-data .

EXPOSE 9000
CMD ["php-fpm"]
