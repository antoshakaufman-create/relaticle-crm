#!/bin/bash

echo "=== FRESH Laravel logs (last 100 lines) ==="
tail -n 100 /var/www/relaticle/storage/logs/laravel.log

echo ""
echo "=== Restarting PHP-FPM with OPcache reset ==="
systemctl restart php8.5-fpm

echo ""
echo "=== Checking PHP-FPM status ==="
systemctl status php8.5-fpm --no-pager | head -n 10

echo ""
echo "=== Testing artisan command ==="
cd /var/www/relaticle
php8.5 artisan about | head -n 20
