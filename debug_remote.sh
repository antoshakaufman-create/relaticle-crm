#!/bin/bash
# debug_remote.sh

echo '=== PHP Version ==='
php -v || echo 'PHP CLI not working'

echo '=== Nginx Config Test ==='
nginx -t

echo "Fixing Permissions for Cache (Just in Case)..."
chmod -R 777 /var/www/relaticle/bootstrap/cache
chmod -R 777 /var/www/relaticle/storage

echo '=== Last 20 lines of Nginx Error Log ==='
tail -n 20 /var/log/nginx/error.log

echo '=== Laravel Log ==='
tail -n 20 /var/www/relaticle/storage/logs/laravel.log

echo '=== SSL Cert Status ==='
if command -v certbot &> /dev/null; then
    certbot certificates
fi

echo '=== Service Status ==='
systemctl status php8.5-fpm --no-pager
systemctl status nginx --no-pager

echo "=== NGINX ACCESS LOGS (Last 20 lines) ==="
tail -n 20 /var/log/nginx/access.log

echo "=== LARAVEL LOGS (Last 50 lines) ==="
tail -n 50 /var/www/relaticle/storage/logs/laravel.log

echo "=== LARAVEL ROUTES (Grepping 'people') ==="
php8.5 /var/www/relaticle/artisan route:list | grep people

echo "Checking Policies on Server:"
ls -la /var/www/relaticle/app/Policies

echo '=== PHP 8.5 Availability ==='
apt-cache policy php8.5

