#!/bin/bash

cd /var/www/relaticle

echo "=== 1. Pulling latest code from GitHub ==="
git reset --hard origin/main

echo ""
echo "=== 2. Regenerating autoloader ==="
php8.5 /usr/bin/composer dump-autoload --optimize

echo ""
echo "=== 3. Clearing ALL caches ==="
php8.5 artisan optimize:clear

echo ""
echo "=== 4. Rebuilding caches ==="
php8.5 artisan config:cache
php8.5 artisan route:cache

echo ""
echo "=== 5. Fixing permissions ==="
chown -R www-data:www-data /var/www/relaticle
chmod -R 775 /var/www/relaticle/storage
chmod -R 775 /var/www/relaticle/bootstrap/cache

echo ""
echo "=== 6. Restarting PHP-FPM ==="
systemctl restart php8.5-fpm
sleep 2

echo ""
echo "=== 7. Current commit ==="
git log -1 --oneline

echo ""
echo "=== 8. Testing site ==="
curl -I https://crmvirtu.ru | head -n 3

echo ""
echo "=== DONE! Try site now ==="
