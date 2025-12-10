#!/bin/bash
# debug_remote.sh
SERVER_IP="83.220.175.224"
USER="root"

echo "Checking remote server status..."
ssh $USER@$SERVER_IP "
echo '=== PHP Version ==='
php -v || echo 'PHP CLI not working'

echo '=== Nginx Config Test ==='
nginx -t

echo '=== Last 20 lines of Nginx Error Log ==='
tail -n 20 /var/log/nginx/error.log

echo '=== Laravel Log ==='
tail -n 20 /var/www/relaticle/storage/logs/laravel.log

echo '=== SSL Cert Status ==='
certbot certificates

echo '=== Service Status ==='
systemctl status php8.4-fpm --no-pager
systemctl status ng
echo "=== LARAVEL LOGS (Last 100 lines) ==="
tail -n 100 /var/www/relaticle/storage/logs/laravel.log
echo "=== END LOGS ==="
echo "Checking Policies on Server:"
ls -la /var/www/relaticle/app/Policies

echo '=== PHP 8.5 Availability ==='
apt-cache policy php8.5
"
