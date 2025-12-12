#!/bin/bash

echo "=== Stopping PHP-FPM ==="
systemctl stop php8.5-fpm
sleep 2

echo "=== Starting PHP-FPM ==="
systemctl start php8.5-fpm
sleep 3

echo "=== Testing root URL with GET (not HEAD) ==="
response=$(curl -s -o /dev/null -w "%{http_code}" "https://crmvirtu.ru/")
echo "Root URL response code: $response"

echo ""
echo "=== Testing /app/register ==="
response2=$(curl -s -o /dev/null -w "%{http_code}" "https://crmvirtu.ru/app/register")
echo "/app/register response code: $response2"

echo ""
echo "=== PHP-FPM status ==="
systemctl status php8.5-fpm --no-pager | head -5

echo ""
echo "=== Check if the URL reaches HomeController ==="
cd /var/www/relaticle
php8.5 artisan route:list --path=/ 2>&1 | head -10
