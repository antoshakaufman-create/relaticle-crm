#!/bin/bash

# Скрипт для получения логов и очистки кэша

echo "=== Получение последних логов ==="
tail -n 100 /var/www/relaticle/storage/logs/laravel.log

echo ""
echo "=== Очистка view кэша ==="
cd /var/www/relaticle
php8.5 artisan view:clear
php8.5 artisan config:clear
php8.5 artisan route:clear

echo ""
echo "=== Пересоздание кэша ==="
php8.5 artisan config:cache
php8.5 artisan route:cache
php8.5 artisan filament:assets

echo ""
echo "=== Готово ==="
