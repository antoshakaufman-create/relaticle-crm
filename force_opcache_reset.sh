#!/bin/bash

echo "=== Force OPcache reset ==="
# Method 1: Touch all PHP files to invalidate OPcache
find /var/www/relaticle -name "*.php" -exec touch {} \;

# Method 2: Clear the compiled files
rm -rf /var/www/relaticle/bootstrap/cache/*.php

# Method 3: Restart PHP-FPM (this clears OPcache)
systemctl restart php8.5-fpm

# Method 4: Also clear Laravel caches
cd /var/www/relaticle
php8.5 artisan optimize:clear 2>/dev/null || true
php8.5 artisan config:clear 2>/dev/null || true
php8.5 artisan route:clear 2>/dev/null || true
php8.5 artisan view:clear 2>/dev/null || true

echo ""
echo "=== Fix permissions after touch ==="
chown -R www-data:www-data /var/www/relaticle/storage
chown -R www-data:www-data /var/www/relaticle/bootstrap/cache
chmod -R 775 /var/www/relaticle/storage
chmod -R 775 /var/www/relaticle/bootstrap/cache

echo ""
echo "=== Restart PHP-FPM again ==="
systemctl restart php8.5-fpm

echo ""
echo "=== Test site ==="
curl -I https://crmvirtu.ru 2>/dev/null | head -n 5

echo ""
echo "=== DONE ==="
