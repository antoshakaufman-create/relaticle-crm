#!/bin/bash

cd /var/www/relaticle

echo "=== Clearing ALL caches including Filament ==="
php8.5 artisan optimize:clear
php8.5 artisan filament:clear-cached-components
php8.5 artisan cache:clear
php8.5 artisan view:clear
php8.5 artisan config:clear
php8.5 artisan route:clear

echo"" 
echo "=== Rebuilding caches ==="
php8.5 artisan config:cache
php8.5 artisan route:cache
php8.5 artisan filament:cache-components

echo ""
echo "=== Verifying AiImport page is registered ==="
php8.5 artisan route:list | grep ai-import

echo ""
echo "=== Final service restart ==="
systemctl restart php8.5-fpm
systemctl reload nginx

echo ""
echo "=== Done! Please try AI Import page now ==="
