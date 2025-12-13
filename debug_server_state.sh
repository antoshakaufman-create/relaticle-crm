#!/bin/bash

cd /var/www/relaticle

echo "=== CHECK 1: Database Schema ==="
php8.5 artisan tinker --execute="
\$columns = \Illuminate\Support\Facades\Schema::getColumnListing('people');
echo 'People Table Columns: ' . implode(', ', \$columns) . \"\\n\";
"

echo ""
echo "=== CHECK 2: PeopleResource.php Content (Form Schema) ==="
# Check if social media fields are in the file
grep -n "vk_url" app/Filament/Resources/PeopleResource.php | head -5
grep -n "smm_analysis" app/Filament/Resources/PeopleResource.php | head -5

echo ""
echo "=== CHECK 3: People.php Model Content ==="
grep -n "vk_url" app/Models/People.php
grep -n "smm_analysis" app/Models/People.php

echo ""
echo "=== CHECK 4: Migration Status ==="
php8.5 artisan migrate:status

echo ""
echo "=== CHECK 5: Filament Cache ==="
# Sometimes Filament caches open icons or components
php8.5 artisan filament:optimize-clear
