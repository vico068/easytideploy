# Panel Dockerfile - Laravel + Filament

# Stage 0: Composer dependencies (used by frontend stage for Filament theme)
FROM composer:2 AS composer

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --ignore-platform-reqs

# Stage 1: PHP application
FROM php:8.3-fpm AS base

# Avoid parallel C compilation spikes when building PHP extensions.
ENV MAKEFLAGS="-j1"

# Install system dependencies
RUN apt-get update && apt-get install -y --no-install-recommends \
    libpq-dev \
    libzip-dev \
    libonig-dev \
    curl \
    git \
    zip \
    unzip \
    supervisor \
    nginx \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install -j1 \
    pdo_pgsql \
    pgsql \
    zip \
    pcntl

# Install Redis extension
RUN apt-get update && apt-get install -y --no-install-recommends $PHPIZE_DEPS \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apt-get purge -y --auto-remove $PHPIZE_DEPS \
    && rm -rf /var/lib/apt/lists/*

# Install Composer (used for dump-autoload and artisan commands)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Reuse vendor from composer stage
COPY --from=composer /app/vendor ./vendor

# Copy application
COPY . .

# Frontend assets are committed under public/build to keep image builds deterministic
# and avoid heavy Node/Vite compilation during production deploys.

# Provide dummy env vars so artisan commands work during build
ENV APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA= \
    REVERB_APP_ID=build \
    REVERB_APP_KEY=build \
    REVERB_APP_SECRET=build \
    BROADCAST_CONNECTION=log \
    DB_CONNECTION=sqlite \
    DB_DATABASE=/tmp/build.sqlite \
    SESSION_DRIVER=file \
    CACHE_STORE=file \
    QUEUE_CONNECTION=sync

RUN composer dump-autoload --optimize

# Publish Livewire assets (required for forms to work)
RUN php artisan livewire:publish --assets

# Set permissions
RUN chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# Copy PHP config
COPY docker/php.ini /usr/local/etc/php/conf.d/99-easydeploy.ini
COPY docker/nginx.conf /etc/nginx/conf.d/default.conf
COPY docker/supervisord.conf /etc/supervisord.conf

EXPOSE 8000

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]
