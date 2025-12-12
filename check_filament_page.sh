#!/bin/bash

echo "=== Check parent class navigationGroup property ==="
grep -n "navigationGroup" /var/www/relaticle/vendor/filament/filament/src/Pages/Page.php | head -n 10

echo ""
echo "=== Check parent class around line 20-40 ==="
sed -n '20,60p' /var/www/relaticle/vendor/filament/filament/src/Pages/Page.php
