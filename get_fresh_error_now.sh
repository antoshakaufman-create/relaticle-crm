#!/bin/bash

# Clear log file to get ONLY fresh errors
echo "" > /var/www/relaticle/storage/logs/laravel.log

echo "Log cleared. Please refresh browser NOW and wait 3 seconds..."
sleep 3

echo "=== FRESH ERROR (should be from just now) ==="
cat /var/www/relaticle/storage/logs/laravel.log | head -n 200

if [ ! -s /var/www/relaticle/storage/logs/laravel.log ]; then
    echo "No Laravel errors logged. Checking Nginx..."
    echo ""
    echo "=== Latest Nginx error ==="
    tail -n 5 /var/log/nginx/error.log
fi
