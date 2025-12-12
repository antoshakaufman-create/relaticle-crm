#!/bin/bash

echo "=== Check PHP OPcache config ==="
php8.5 -i | grep -i "opcache\." | head -20

echo ""
echo "=== Check if opcache file_cache is set ==="
php8.5 -i | grep -i "opcache.file_cache"

echo ""  
echo "=== List OPcache cache directories ==="
ls -la /tmp/opcache* 2>/dev/null || echo "No /tmp/opcache"
ls -la /var/tmp/opcache* 2>/dev/null || echo "No /var/tmp/opcache"

echo ""
echo "=== Check PHP-FPM config for opcache ==="
grep -r "opcache" /etc/php/8.5/fpm/conf.d/ 2>/dev/null | head -10
