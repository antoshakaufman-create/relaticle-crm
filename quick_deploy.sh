#!/bin/bash

echo "=== Pull latest code ==="
cd /var/www/relaticle
git pull origin main

echo ""
echo "=== Fix permissions ==="
chown -R www-data:www-data /var/www/relaticle/storage
chown -R www-data:www-data /var/www/relaticle/bootstrap/cache
chmod -R 775 /var/www/relaticle/storage
chmod -R 775 /var/www/relaticle/bootstrap/cache

echo ""
echo "=== Clear all caches ==="
php8.5 artisan optimize:clear
php8.5 artisan view:clear
php8.5 artisan config:cache
php8.5 artisan route:cache
php8.5 artisan view:cache

echo ""
echo "=== Restart PHP-FPM ==="
systemctl restart php8.5-fpm

echo ""
echo "=== Test site ==="
curl -I https://crmvirtu.ru | head -n 5

echo ""
echo "=== DONE ==="
