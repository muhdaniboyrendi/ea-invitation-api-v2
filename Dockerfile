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

# Install PHP extensions (PENTING: tambahkan pcntl untuk Octane)
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

# Set working directory
WORKDIR /app

# Copy existing application
COPY . /app

# Copy Caddyfile ke dalam container
COPY Caddyfile /app/Caddyfile

# Install dependencies Laravel
RUN composer install --optimize-autoloader --no-interaction --no-dev

# Set permissions
RUN chown -R www-data:www-data /app/storage /app/bootstrap/cache

# Expose port
EXPOSE 8000