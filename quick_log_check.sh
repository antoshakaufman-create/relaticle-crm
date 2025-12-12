#!/bin/bash

echo "=== Last 50 lines of Laravel log ==="
tail -n 50 /var/www/relaticle/storage/logs/laravel.log

echo ""
echo "=== Last 20 lines of Nginx error log ==="
tail -n 20 /var/log/nginx/error.log
