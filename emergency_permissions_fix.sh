#!/bin/bash

echo "=== CRITICAL: Fixing storage permissions ==="
chown -R www-data:www-data /var/www/relaticle/storage
chown -R www-data:www-data /var/www/relaticle/bootstrap/cache
chmod -R 775 /var/www/relaticle/storage
chmod -R 775 /var/www/relaticle/bootstrap/cache

echo ""
echo "=== Clearing caches ==="
cd /var/www/relaticle
php8.5 artisan optimize:clear || true
php8.5 artisan view:clear || true

echo ""
echo "=== Restarting PHP-FPM ==="
systemctl restart php8.5-fpm

echo ""
echo "=== Testing site ==="
curl -I https://crmvirtu.ru | head -n 3

echo ""
echo "=== DONE! Try site now ==="
