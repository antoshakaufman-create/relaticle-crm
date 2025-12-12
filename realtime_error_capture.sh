#!/bin/bash

# Очистим логи и захватим свежую ошибку
echo "=== Clearing logs for fresh capture ==="
> /var/www/relaticle/storage/logs/laravel.log

echo "Waiting 2 seconds - REFRESH BROWSER NOW!"
sleep 2

echo ""
echo "=== FRESH ERROR FROM LARAVEL ==="
cat /var/www/relaticle/storage/logs/laravel.log 2>/dev/null || echo "No Laravel errors"

echo ""
echo "=== FRESH ERROR FROM NGINX (last 10 lines) ==="
tail -n 10 /var/log/nginx/error.log 2>/dev/null

echo ""
echo "=== Checking if AiImport.php has correct code ==="
head -n 30 /var/www/relaticle/app/Filament/Pages/AiImport.php | grep -A 5 "navigationGroup"
