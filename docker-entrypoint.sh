#!/bin/bash
set -e

echo "Starting Laravel setup..."

# Run Laravel setup commands
php artisan storage:link --force
yes | php artisan octane:install --server=frankenphp 2>/dev/null || true

echo "Setup complete. Starting Octane..."

# Start Octane
exec php artisan octane:frankenphp --host=0.0.0.0 --port=8000