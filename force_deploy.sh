#!/bin/bash

echo "=== Force deploy ==="
cd /var/www/relaticle

# Remove conflicting files
rm -f import_clients.sh Клиенты-2.csv

# Reset and pull
git reset --hard HEAD
git pull origin main

# Run migration
php8.5 artisan migrate --force

# Clear caches
php8.5 artisan optimize:clear

# Restart PHP-FPM
systemctl restart php8.5-fpm

echo ""
echo "=== Check leads table migration ==="
sqlite3 /var/www/relaticle_data/database.sqlite "PRAGMA table_info(people);" | head -20

echo ""
echo "=== Done ==="
