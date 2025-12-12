#!/bin/bash

echo "=== LARAVEL LOG (last 150 lines) ==="
tail -n 150 /var/www/relaticle/storage/logs/laravel.log

echo ""
echo "=== NGINX ERROR LOG (last 30 lines) ==="
tail -n 30 /var/log/nginx/error.log
