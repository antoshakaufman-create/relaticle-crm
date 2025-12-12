#!/bin/bash

# Get detailed Laravel logs
echo "=== Latest Laravel Error Logs ==="
tail -n 200 /var/www/relaticle/storage/logs/laravel.log | grep -A 20 "AiImport" || tail -n 200 /var/www/relaticle/storage/logs/laravel.log

echo ""
echo "=== PHP-FPM Error Logs ==="
tail -n 50 /var/log/php8.5-fpm.log 2>/dev/null || echo "No PHP-FPM logs found"

echo ""
echo "=== Nginx Error Logs ==="
tail -n 50 /var/log/nginx/error.log | grep "ai-import" || tail -n 20 /var/log/nginx/error.log
