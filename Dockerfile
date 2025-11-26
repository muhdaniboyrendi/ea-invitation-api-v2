FROM dunglas/frankenphp:latest-php8.3

# Install dependencies yang diperlukan
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

# Install PHP extensions
RUN install-php-extensions \
    pdo_pgsql \
    pgsql \
    gd \
    intl \
    zip \
    opcache \
    pcntl \
    bcmath

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# ===== KONFIGURASI PHP =====
# Copy base php.ini dari template production
RUN cp $PHP_INI_DIR/php.ini-production $PHP_INI_DIR/php.ini

# Copy custom php.ini configuration
COPY docker/php.ini $PHP_INI_DIR/conf.d/zz-custom.ini
# Prefix 'zz-' memastikan file ini di-load terakhir, override config sebelumnya
# ===========================

# Set working directory
WORKDIR /app

# Copy existing application
COPY . /app

# Install dependencies Laravel
RUN composer install --optimize-autoloader --no-interaction

# Set permissions
RUN chown -R www-data:www-data /app/storage /app/bootstrap/cache

# Expose port
EXPOSE 8000