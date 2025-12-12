#!/bin/bash

cd /var/www/relaticle

echo "=== Delete old leads ==="
php8.5 artisan tinker --execute="
\$count = DB::table('leads')->count();
echo \"Found {\$count} leads to delete\n\";
DB::table('leads')->delete();
echo \"Deleted all leads\n\";
"

echo ""
echo "=== Check people count ==="
php8.5 artisan tinker --execute="
echo 'Total People: ' . App\Models\People::count() . \"\n\";
"

echo ""
echo "=== Restart PHP ==="
systemctl restart php8.5-fpm

echo ""
echo "=== Test site ==="
curl -sI https://crmvirtu.ru | head -3

echo ""
echo "=== Done ==="
