#!/bin/bash

cd /var/www/relaticle

echo "=== Clean and deploy ==="
rm -f companies.csv it_list.csv
git reset --hard HEAD
git pull origin main

echo ""
echo "=== Run migrations ==="
php8.5 artisan migrate --force

echo ""
echo "=== Check people table ==="
sqlite3 /var/www/relaticle_data/database.sqlite "PRAGMA table_info(people);"

echo ""
echo "=== Restart ==="
php8.5 artisan optimize:clear
systemctl restart php8.5-fpm

echo ""
echo "=== Done ==="
