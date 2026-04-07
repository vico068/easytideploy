# Panel Dockerfile - Laravel + Filament

# Stage 0: Composer dependencies (used by frontend stage for Filament theme)
FROM composer:2 AS composer

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --ignore-platform-reqs

# Stage 1: PHP application
FROM debian:bookworm-slim AS base

ENV DEBIAN_FRONTEND=noninteractive

# Install PHP runtime and extensions from Debian packages to avoid heavy source compilation.
RUN apt-get update && apt-get install -y --no-install-recommends \
    ca-certificates \
    curl \
    zip \
    unzip \
    php8.2-cli \
    php8.2-pgsql \
    php8.2-zip \
    php8.2-mbstring \
    php8.2-xml \
    php8.2-curl \
    php8.2-bcmath \
    php8.2-intl \
    php8.2-redis \
    php8.2-opcache \
    && ln -sf /usr/bin/php8.2 /usr/local/bin/php \
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

RUN composer dump-autoload --optimize --no-scripts

# Publish Livewire assets (required for forms to work)
RUN php artisan livewire:publish --assets

# Set permissions
RUN chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# Copy PHP config
COPY docker/php.ini /etc/php/8.2/cli/conf.d/99-easydeploy.ini

EXPOSE 8000

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
