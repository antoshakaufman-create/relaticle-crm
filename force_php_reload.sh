#!/bin/bash

echo "=== Check if AiImport file has correct type ==="
grep -n "navigationGroup" /var/www/relaticle/app/Filament/Pages/AiImport.php

echo ""
echo "=== Check PHP-FPM status ==="
systemctl status php8.5-fpm --no-pager | head -5

echo ""
echo "=== Kill all PHP-FPM workers to force reload ==="
pkill -9 -f php-fpm
sleep 1
systemctl start php8.5-fpm
sleep 2

echo ""
echo "=== Regenerate composer autoload ==="
cd /var/www/relaticle
php8.5 /usr/bin/composer dump-autoload -o 2>&1

echo ""
echo "=== Test with curl ==="
curl -sI https://crmvirtu.ru | head -5

echo ""
echo "=== DONE ==="
