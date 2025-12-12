#!/bin/bash

echo "=== Deploy and migrate ==="
cd /var/www/relaticle

# Pull latest
git pull origin main

# Run migration
php8.5 artisan migrate --force

# Clear caches
php8.5 artisan optimize:clear

# Restart PHP-FPM
systemctl restart php8.5-fpm

echo ""
echo "=== Test site ==="
curl -sI https://crmvirtu.ru | head -3

echo ""
echo "=== Done ==="
