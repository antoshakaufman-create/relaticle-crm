#!/bin/bash

cd /var/www/relaticle

echo "=== Step 1: Clear all caches ==="
php8.5 artisan optimize:clear || true
php8.5 artisan cache:clear || true  
php8.5 artisan config:clear || true
php8.5 artisan route:clear || true
php8.5 artisan view:clear || true

echo ""
echo "=== Step 2: Clear Filament component cache ==="
php8.5 artisan filament:clear-cached-components || true

echo ""
echo "=== Step 3:  Rebuild caches ==="
php8.5 artisan config:cache
php8.5 artisan route:cache

echo ""
echo "=== Step 4: Cache Filament components ==="
php8.5 artisan filament:cache-components || true

echo ""
echo "=== Step 5: Restart PHP-FPM ==="
systemctl restart php8.5-fpm

echo ""
echo "=== Step 6: Check route registration ==="
php8.5 artisan route:list | grep "ai-import" || echo "Route not found!"

echo ""
echo "=== DONE - Try accessing AI Import page now ==="
