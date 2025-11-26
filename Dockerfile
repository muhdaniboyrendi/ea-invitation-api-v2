FROM dunglas/frankenphp:latest-php8.3

# Install dependencies
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

# ===== CRITICAL: Set PHP Configuration =====
# FrankenPHP worker mode doesn't read php.ini at runtime
# So we need to set it at build time AND use ini_set() at runtime

# Copy php.ini-production as base
RUN cp $PHP_INI_DIR/php.ini-production $PHP_INI_DIR/php.ini

# Create and copy custom php.ini
COPY docker/php.ini $PHP_INI_DIR/conf.d/zz-custom.ini

# IMPORTANT: Also set as environment variables for FrankenPHP
ENV PHP_UPLOAD_MAX_FILESIZE=25M \
    PHP_POST_MAX_SIZE=30M \
    PHP_MEMORY_LIMIT=512M \
    PHP_MAX_EXECUTION_TIME=300
# ===========================================

# Set working directory
WORKDIR /app

# Copy application
COPY . /app

# Install dependencies
RUN composer install --optimize-autoloader --no-interaction

# Set permissions
RUN chown -R www-data:www-data /app/storage /app/bootstrap/cache

# Expose port
EXPOSE 8000