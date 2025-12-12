#!/bin/bash

echo "=== Pull and deploy ==="
cd /var/www/relaticle
git pull origin main

echo ""
echo "=== Delete cached files ==="
rm -rf bootstrap/cache/*.php

echo ""
echo "=== Restart PHP-FPM ==="
systemctl restart php8.5-fpm

echo ""
echo "=== Clear Laravel caches ==="
php8.5 artisan optimize:clear 2>&1 || true

echo ""
echo "=== Fix permissions ==="
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

echo ""
echo "=== Restart PHP-FPM again ==="
systemctl restart php8.5-fpm

echo ""
echo "=== Test ==="
curl -I https://crmvirtu.ru 2>/dev/null | head -n 5

echo ""
echo "=== DONE ==="
