#!/bin/bash

echo "=== Clearing PHP OPcache ==="
cd /var/www/relaticle

# Method 1: Via artisan
php8.5 artisan optimize:clear

# Method 2: Direct clear via CLI
php8.5 -r "if (function_exists('opcache_reset')) { opcache_reset(); echo 'OPcache cleared via CLI\n'; }"

# Method 3: Restart PHP-FPM (most reliable)
echo ""
echo "=== Restarting PHP-FPM (clears all caches) ==="
systemctl restart php8.5-fpm
sleep 2

#  Verify
echo ""
echo "=== PHP-FPM Status ==="
systemctl status php8.5-fpm --no-pager | grep "Active:"

echo ""
echo "=== Testing site now ==="
curl -I https://crmvirtu.ru | head -n 3

echo ""
echo "=== DONE - try browser now ==="
