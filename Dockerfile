FROM dunglas/frankenphp:php8.4

RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpq-dev \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    zip \
    unzip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

RUN install-php-extensions \
    pdo_pgsql \
    pgsql \
    gd \
    intl \
    zip \
    opcache \
    pcntl \
    bcmath

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

RUN cp $PHP_INI_DIR/php.ini-production $PHP_INI_DIR/php.ini

COPY docker/php.ini $PHP_INI_DIR/conf.d/zz-custom.ini

ENV PHP_UPLOAD_MAX_FILESIZE=120M \
    PHP_POST_MAX_SIZE=125M \
    PHP_MEMORY_LIMIT=512M \
    PHP_MAX_EXECUTION_TIME=300

WORKDIR /app

COPY . /app

RUN composer install --optimize-autoloader --no-interaction

RUN chown -R www-data:www-data /app/storage /app/bootstrap/cache

EXPOSE 8000