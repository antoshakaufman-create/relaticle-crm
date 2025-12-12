#!/bin/bash

echo "=== Clearing Laravel log for fresh capture ==="
> /var/www/relaticle/storage/logs/laravel.log

echo "=== Waiting for new request (please refresh browser NOW) ==="
sleep 5

echo ""
echo "=== Fresh Laravel errors ==="
cat /var/www/relaticle/storage/logs/laravel.log

echo ""
echo "=== Fresh Nginx errors ==="
tail -n 30 /var/log/nginx/error.log
