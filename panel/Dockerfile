# Panel Dockerfile - Laravel + Filament

# Stage 0: Composer dependencies (used by frontend stage for Filament theme)
FROM composer:2 AS composer

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

# Stage 1: Build frontend assets
FROM node:20-alpine AS frontend

WORKDIR /app

# Copy Composer vendor so Filament theme CSS can @import from vendor/
COPY --from=composer /app/vendor ./vendor

# Copy package files
COPY package.json package-lock.json* ./

# Install dependencies
RUN npm ci || npm install

# Copy source files needed for build
COPY resources ./resources
COPY tailwind.config.js vite.config.js ./
COPY postcss.config.js* ./

# Build assets
RUN npm run build

# Stage 2: PHP application
FROM php:8.3-fpm-alpine AS base

# Install system dependencies
RUN apk add --no-cache \
    postgresql-dev \
    libzip-dev \
    icu-dev \
    oniguruma-dev \
    linux-headers \
    curl \
    git \
    zip \
    unzip \
    supervisor \
    nginx

# Install PHP extensions
RUN docker-php-ext-install \
    pdo_pgsql \
    pgsql \
    zip \
    intl \
    mbstring \
    opcache \
    pcntl

# Install Redis extension
RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps

# Install Composer (used for dump-autoload and artisan commands)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Reuse vendor from composer stage
COPY --from=composer /app/vendor ./vendor

# Copy application
COPY . .

# Copy built assets from frontend stage
COPY --from=frontend /app/public/build ./public/build

RUN composer dump-autoload --optimize

# Publish Livewire assets (required for forms to work)
RUN php artisan livewire:publish --assets

# Set permissions
RUN chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# Copy PHP config
COPY docker/php.ini /usr/local/etc/php/conf.d/99-easydeploy.ini
COPY docker/nginx.conf /etc/nginx/http.d/default.conf
COPY docker/supervisord.conf /etc/supervisord.conf

EXPOSE 8000

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]
