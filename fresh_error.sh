#!/bin/bash

# Clear log to get only fresh errors
echo "" > /var/www/relaticle/storage/logs/laravel.log 2>/dev/null || true

echo "Log cleared. Making a request to trigger error..."
curl -s https://crmvirtu.ru > /dev/null

sleep 1

echo "=== FRESH LARAVEL LOG ==="
cat /var/www/relaticle/storage/logs/laravel.log 2>/dev/null | head -n 100

echo ""
echo "=== NGINX ERROR (last 10 lines) ==="
tail -n 10 /var/log/nginx/error.log
