#!/bin/sh
set -e

echo "🚀 Starting EA Invitation API..."

# Tunggu database siap (jika menggunakan healthcheck sudah cukup)
echo "⏳ Waiting for database connection..."
php artisan db:show || true

# Jalankan migrations (opsional, uncomment jika ingin auto-migrate)
# echo "🔄 Running database migrations..."
# php artisan migrate --force --no-interaction

# Clear dan rebuild cache - dilakukan setiap container start
# untuk memastikan cache selalu fresh dengan environment variables yang benar
echo "🔧 Optimizing application..."

# Clear semua cache terlebih dahulu
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Rebuild cache dengan environment variables yang benar
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Opsional: Clear application cache
# php artisan cache:clear

echo "✅ Application optimized and ready!"
echo "🌐 Starting FrankenPHP server..."

# Jalankan command yang diberikan (dari CMD di Dockerfile)
exec "$@"