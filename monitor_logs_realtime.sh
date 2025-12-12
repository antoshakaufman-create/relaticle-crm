#!/bin/bash

# Tail the log file and show new errors
echo "=== Monitoring Laravel log for new errors ==="
echo "Please try to access AI Import page NOW..."
sleep 3

# Show last 20 lines of log
tail -n 20 /var/www/relaticle/storage/logs/laravel.log

echo ""
echo "=== Checking Nginx error log ==="
tail -n 10 /var/log/nginx/error.log
