#!/bin/bash
set -e

echo "Starting Laravel setup..."

# Debug: Cek apakah konfigurasi sudah masuk SEBELUM aplikasi jalan
echo "--- CHECKING PHP CONFIG ---"
php -i | grep "post_max_size"
php -i | grep "upload_max_filesize"
echo "--- END CHECK ---"

# Wait for database
echo "Waiting for database..."
until php artisan db:show 2>/dev/null; do
    echo "Database is unavailable - sleeping"
    sleep 2
done

echo "Database is ready!"

# Run migrations & storage link
php artisan migrate --force
php artisan storage:link --force

# Install Octane dependencies (jika belum)
php artisan octane:install --server=frankenphp --no-interaction 2>/dev/null || true

# Setup Caddyfile
cat > /etc/caddy/Caddyfile << 'EOF'
{
    frankenphp
    order php_server before file_server
}

:8000 {
    root * /app/public
    
    request_body {
        max_size 100MB
    }
    
    php_server
    encode gzip
    file_server
}
EOF

echo "Starting Octane..."

# Jalankan Octane secara normal
exec php artisan octane:frankenphp --host=0.0.0.0 --port=8000