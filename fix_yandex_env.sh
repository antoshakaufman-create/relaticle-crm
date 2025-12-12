#!/bin/bash

echo "=== Current .env Yandex settings ==="
grep -i yandex /var/www/relaticle_data/.env || echo "No yandex in .env"

echo ""
echo "=== Adding Yandex API Key ==="
# Check if YANDEX_API_KEY exists
if grep -q "YANDEX_API_KEY=" /var/www/relaticle_data/.env; then
    echo "Updating existing key..."
    sed -i 's/YANDEX_API_KEY=.*/YANDEX_API_KEY=ajetvrtcaq19kpik8cf6/' /var/www/relaticle_data/.env
else
    echo "Adding new key..."
    echo "YANDEX_API_KEY=ajetvrtcaq19kpik8cf6" >> /var/www/relaticle_data/.env
fi

if grep -q "YANDEX_FOLDER_ID=" /var/www/relaticle_data/.env; then
    echo "Folder ID already set"
else
    echo "Adding folder ID..."
    echo "YANDEX_FOLDER_ID=b1gn3qao39gb9uecn2c2" >> /var/www/relaticle_data/.env
fi

echo ""
echo "=== Updated .env Yandex settings ==="
grep -i yandex /var/www/relaticle_data/.env

echo ""
echo "=== Clearing config cache ==="
cd /var/www/relaticle
php8.5 artisan config:clear
systemctl restart php8.5-fpm

echo ""
echo "=== Done ==="
