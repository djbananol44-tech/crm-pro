# ===========================================
# CRM App - PHP 8.3 + Nginx + Node.js
# ===========================================
FROM php:8.3-fpm-alpine

LABEL maintainer="CRM Team"
LABEL description="CRM Application with PHP 8.3, Nginx, and Node.js"

# Устанавливаем системные зависимости
RUN apk add --no-cache \
    nginx \
    supervisor \
    curl \
    zip \
    unzip \
    git \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    libzip-dev \
    icu-dev \
    oniguruma-dev \
    postgresql-dev \
    nodejs \
    npm

# Устанавливаем PHP расширения
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo \
        pdo_pgsql \
        pgsql \
        gd \
        zip \
        intl \
        mbstring \
        opcache \
        pcntl \
        bcmath

# Устанавливаем Redis расширение
RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps

# Устанавливаем Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Рабочая директория
WORKDIR /var/www/html

# Копируем конфигурации
COPY docker/nginx.conf /etc/nginx/http.d/default.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/custom.ini
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/opcache.ini /usr/local/etc/php/conf.d/opcache.ini

# Копируем исходники
COPY . .

# Устанавливаем права
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/storage \
    && chmod -R 755 /var/www/html/bootstrap/cache

# Устанавливаем зависимости PHP (без dev)
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-progress

# Устанавливаем зависимости Node.js и собираем фронтенд
RUN npm ci --production=false \
    && npm run build \
    && rm -rf node_modules

# Кэшируем конфигурацию Laravel
RUN php artisan config:clear \
    && php artisan route:clear \
    && php artisan view:clear

# Создаём health check endpoint
RUN echo '<?php echo "OK";' > /var/www/html/public/health.php

# Nginx директории
RUN mkdir -p /run/nginx

# Порт
EXPOSE 80

# Запуск через supervisor
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
