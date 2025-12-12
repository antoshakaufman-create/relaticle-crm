#!/bin/bash

# Get the actual error from Laravel logs
echo "=== REAL ERROR FROM LARAVEL LOG (last 100 lines) ==="
tail -n 100 /var/www/relaticle/storage/logs/laravel.log

echo ""
echo "=== NGINX ERROR (last 20 lines) ==="
tail -n 20 /var/log/nginx/error.log

echo ""
echo "=== PHP-FPM ERROR (if any) ==="
tail -n 20 /var/log/php8.5-fpm.log 2>/dev/null || echo "No PHP-FPM log"
