#!/bin/bash

# Force refresh and clear all caches

cd /var/www/relaticle

echo "=== Checking current AiImport.php on server ==="
head -n 30 app/Filament/Pages/AiImport.php

echo ""
echo "=== Clearing all caches ===  "
php8.5 artisan cache:clear
php8.5 artisan config:clear
php8.5 artisan route:clear
php8.5 artisan view:clear
php8.5 artisan clear-compiled

echo ""
echo "=== Pulling latest code ==="
git fetch origin
git reset --hard origin/main

echo ""
echo "=== Checking updated file ==="
head -n 30 app/Filament/Pages/AiImport.php

echo ""
echo "=== Installing dependencies ==="
php8.5 /usr/bin/composer install --no-dev --optimize-autoloader --no-interaction

echo ""
echo "=== Rebuilding caches ==="
php8.5 artisan config:cache
php8.5 artisan route:cache

echo ""
echo "=== Restarting services ==="
systemctl restart php8.5-fpm
systemctl reload nginx

echo ""
echo "=== Done ==="
