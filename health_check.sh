#!/bin/bash

echo "=== Checking site health ==="
cd /var/www/relaticle

echo "1. PHP version:"
php8.5 -v | head -n 1

echo ""
echo "2. Laravel health check:"
php8.5 artisan about | grep -E "(Environment|Debug Mode|URL)"

echo ""
echo "3. Routes available:"
php8.5 artisan route:list | grep -i "ai-import"

echo ""
echo "4. Storage permissions:"
ls -la storage/ | head -n 5

echo ""
echo "5. Recent errors (if any):"
tail -n 10 storage/logs/laravel.log 2>/dev/null | grep -i "error" || echo "No recent errors found"

echo ""
echo "6. PHP-FPM status:"
systemctl status php8.5-fpm --no-pager | grep "Active:"

echo ""
echo "=== Site health check complete ==="
