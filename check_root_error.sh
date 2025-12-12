#!/bin/bash

echo "=== Clear log ==="
echo "" > /var/www/relaticle/storage/logs/laravel.log 2>/dev/null || true

echo "=== Making request to root URL ==="
curl -s https://crmvirtu.ru/ > /dev/null 2>&1

sleep 1

echo "=== FRESH LARAVEL LOG ==="
cat /var/www/relaticle/storage/logs/laravel.log 2>/dev/null | head -n 80

echo ""
echo "=== NGINX ERROR (last 5 lines) ==="
tail -n 5 /var/log/nginx/error.log
