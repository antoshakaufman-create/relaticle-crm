#!/bin/bash

cd /var/www/relaticle

echo "=== 1. Showing current AiImport.php file (lines 1-30) ==="
head -n 30 app/Filament/Pages/AiImport.php

echo ""
echo "=== 2. Regenerating composer autoloader (this clears class cache) ==="
php8.5 /usr/bin/composer dump-autoload --optimize

echo ""
echo "=== 3. Clearing ALL caches again ==="
php8.5 artisan optimize:clear
php8.5 artisan cache:clear  
php8.5 artisan view:clear
php8.5 artisan config:clear

echo ""
echo "=== 4. Rebuilding caches ==="
php8.5 artisan config:cache

echo ""
echo "=== 5. Restarting PHP-FPM (final) ==="
systemctl restart php8.5-fpm
sleep 2

echo ""
echo "=== 6. Testing site ==="
curl -I https://crmvirtu.ru | head -n 3

echo ""
echo "=== DONE - Try browser now! ==="
