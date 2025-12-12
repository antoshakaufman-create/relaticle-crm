#!/bin/bash

echo "=== AGGRESSIVE OPCACHE RESET ==="

# 1. Stop PHP-FPM completely
echo "Stopping PHP-FPM..."
systemctl stop php8.5-fpm

# 2. Clear PHP opcache files (if filesystem caching is enabled)
echo "Clearing PHP cache files..."
rm -rf /tmp/opcache/* 2>/dev/null || true
rm -rf /var/tmp/opcache/* 2>/dev/null || true

# 3. Clear Laravel bootstrap cache
echo "Clearing bootstrap cache..."
rm -rf /var/www/relaticle/bootstrap/cache/*.php

# 4. Clear Laravel storage caches
echo "Clearing storage caches..."
rm -rf /var/www/relaticle/storage/framework/cache/data/*
rm -rf /var/www/relaticle/storage/framework/views/*.php
rm -rf /var/www/relaticle/storage/framework/sessions/*

# 5. Fix permissions
echo "Fixing permissions..."
chown -R www-data:www-data /var/www/relaticle/storage
chown -R www-data:www-data /var/www/relaticle/bootstrap/cache
chmod -R 775 /var/www/relaticle/storage
chmod -R 775 /var/www/relaticle/bootstrap/cache

# 6. Start PHP-FPM
echo "Starting PHP-FPM..."
systemctl start php8.5-fpm

# 7. Wait a moment
sleep 2

# 8. Warm up by running artisan
echo "Warming up..."
cd /var/www/relaticle
php8.5 artisan about 2>&1 | head -5 || echo "Artisan returned error"

# 9. Test
echo ""
echo "=== Testing site ==="
curl -sI https://crmvirtu.ru | head -n 5

echo ""
echo "=== DONE ==="
