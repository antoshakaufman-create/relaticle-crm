#!/bin/bash

# Fix file ownership and get real-time logs
echo "=== Fixing file ownership ==="
chown -R www-data:www-data /var/www/relaticle/app/Filament
chown -R www-data:www-data /var/www/relaticle/resources/views

echo ""
echo "=== Clearing view cache ==="
cd /var/www/relaticle
php8.5 artisan view:clear
php8.5 artisan config:clear

echo ""
echo "=== Tailing log (waiting for new errors) ==="
echo "Please open AI Import page now in your browser..."
sleep 2
tail -n 100 /var/www/relaticle/storage/logs/laravel.log | grep -A 30 "$(date +%Y-%m-%d)" || echo "No errors today"

echo ""  
echo "=== Nginx error log ==="
tail -n 20 /var/log/nginx/error.log | grep "$(date +%Y/%m/%d)"
