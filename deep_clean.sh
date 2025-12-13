#!/bin/bash

cd /var/www/relaticle

echo "=== 1. Clear ALL caches ==="
php8.5 artisan optimize:clear
php8.5 artisan filament:optimize-clear

echo ""
echo "=== 2. Re-cache config and routes ==="
php8.5 artisan config:cache
php8.5 artisan route:cache

echo ""
echo "=== 3. Restart PHP-FPM (Force code reload) ==="
systemctl restart php8.5-fpm

echo ""
echo "=== 4. Verify Data for 'Самсонов Михаил' (Expected: R-Pharm) ==="
php8.5 artisan tinker --execute="
\$p = \App\Models\People::where('name', 'like', '%Самсонов Михаил%')->first();
if (\$p) {
    echo 'Name: ' . \$p->name . \"\\n\";
    echo 'VK: ' . (\$p->vk_url ?: 'MISSING') . \"\\n\";
    echo 'Telegram: ' . (\$p->telegram_url ?: 'MISSING') . \"\\n\";
    echo 'SMM: ' . (strlen(\$p->smm_analysis ?? '') > 0 ? 'PRESENT' : 'MISSING') . \"\\n\";
} else {
    echo 'Contact NOT FOUND';
}
"
