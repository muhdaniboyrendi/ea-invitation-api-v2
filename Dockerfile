FROM dunglas/frankenphp:latest-php8.3

ARG APP_ENV=local

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    zip \
    unzip \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN install-php-extensions \
    pdo_pgsql \
    pgsql \
    pcntl \
    opcache \
    intl \
    zip \
    exif \
    bcmath \
    gd \
    redis

WORKDIR /app

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

COPY composer.json composer.lock ./

RUN if [ "$APP_ENV" = "production" ]; then \
        composer install --no-dev --no-scripts --no-autoloader --prefer-dist; \
    else \
        composer install --no-scripts --no-autoloader --prefer-dist; \
    fi

COPY app ./app
COPY bootstrap ./bootstrap
COPY config ./config
COPY database ./database
COPY public ./public
COPY resources ./resources
COPY routes ./routes
COPY artisan ./artisan
COPY docker-entrypoint.sh ./docker-entrypoint.sh
COPY Caddyfile /etc/caddy/Caddyfile

COPY storage/app/.gitignore ./storage/app/.gitignore
COPY storage/framework/.gitignore ./storage/framework/.gitignore
COPY storage/logs/.gitignore ./storage/logs/.gitignore

RUN mkdir -p storage/framework/{sessions,views,cache} \
    && mkdir -p storage/logs \
    && mkdir -p storage/app/public \
    && mkdir -p bootstrap/cache

RUN chown -R www-data:www-data /app/storage /app/bootstrap/cache \
    && chmod -R 775 /app/storage /app/bootstrap/cache

RUN composer dump-autoload --optimize --no-scripts

# Tingkatkan limits untuk file upload
# RUN echo "upload_max_filesize = 100M" > /usr/local/etc/php/conf.d/uploads.ini \
#     && echo "post_max_size = 100M" >> /usr/local/etc/php/conf.d/uploads.ini \
#     && echo "memory_limit = 512M" >> /usr/local/etc/php/conf.d/uploads.ini \
#     && echo "max_execution_time = 300" >> /usr/local/etc/php/conf.d/uploads.ini \
#     && echo "max_input_time = 300" >> /usr/local/etc/php/conf.d/uploads.ini
RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

# 2. Buat konfigurasi dengan nama 'zz-uploads.ini' agar di-load TERAKHIR (menimpa yang lain)
RUN echo "upload_max_filesize = 100M" > $PHP_INI_DIR/conf.d/zz-uploads.ini \
    && echo "post_max_size = 100M" >> $PHP_INI_DIR/conf.d/zz-uploads.ini \
    && echo "memory_limit = 512M" >> $PHP_INI_DIR/conf.d/zz-uploads.ini \
    && echo "max_execution_time = 600" >> $PHP_INI_DIR/conf.d/zz-uploads.ini \
    && echo "max_input_time = 600" >> $PHP_INI_DIR/conf.d/zz-uploads.ini \
    && echo "variables_order = EGPCS" >> $PHP_INI_DIR/conf.d/zz-uploads.ini

RUN chown -R www-data:www-data /app/public \
    && chmod -R 775 /app/public

RUN chmod +x /app/docker-entrypoint.sh

EXPOSE 8000

HEALTHCHECK --interval=30s --timeout=3s --start-period=40s \
    CMD php artisan inspire || exit 1

ENTRYPOINT ["/app/docker-entrypoint.sh"]