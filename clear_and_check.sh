#!/bin/bash

cd /var/www/relaticle

echo "=== Clear all caches ==="
php8.5 artisan cache:clear
php8.5 artisan config:clear
php8.5 artisan view:clear
php8.5 artisan route:clear

echo ""
echo "=== Restart PHP-FPM ==="
systemctl restart php8.5-fpm

echo ""
echo "=== Check latest data ==="
php8.5 artisan tinker --execute="
use App\Models\People;

echo 'Total: ' . People::count() . \"\\n\";
echo 'With VK: ' . People::whereNotNull('vk_url')->count() . \"\\n\";
echo 'With SMM Analysis: ' . People::whereNotNull('smm_analysis')->count() . \"\\n\";

echo \"\\nLast updated contacts:\\n\";
\$latest = People::whereNotNull('smm_analysis')->latest('updated_at')->take(5)->get();
foreach(\$latest as \$p) {
    echo '- ' . \$p->name . ' (updated: ' . \$p->updated_at . ')' . \"\\n\";
}
"
