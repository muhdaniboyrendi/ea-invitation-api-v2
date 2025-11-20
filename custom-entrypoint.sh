#!/bin/bash
set -e

echo "Starting Laravel setup (CUSTOM ENTRYPOINT)..."

# Wait for database to be ready
echo "Waiting for database..."
until php artisan db:show 2>/dev/null; do
    echo "Database is unavailable - sleeping"
    sleep 2
done

echo "Database is ready!"

# Run migrations
php artisan migrate --force

# Run storage link
php artisan storage:link --force

# Install Octane (Ensure clean install)
php artisan octane:install --server=frankenphp --no-interaction 2>/dev/null || true

# Caddyfile setup
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

echo "Setup complete. Starting Octane with FORCED LIMITS..."

# --- PERUBAHAN KUNCI: KITA PAKSA FLAG -d DI SINI ---
exec php \
    -d variables_order=EGPCS \
    -d post_max_size=100M \
    -d upload_max_filesize=100M \
    -d memory_limit=512M \
    artisan octane:frankenphp --host=0.0.0.0 --port=8000