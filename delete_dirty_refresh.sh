#!/bin/bash

cd /var/www/relaticle

echo "=== Delete dirty contacts ==="
php8.5 artisan tinker --execute="
use App\Models\People;

// Delete the 2 dirty contacts
\$deleted = People::whereIn('id', [726, 757])->forceDelete();
echo \"Deleted: {\$deleted} contacts\\n\";
"

echo ""
echo "=== Clear caches ==="
php8.5 artisan optimize:clear
php8.5 artisan filament:optimize-clear
php8.5 artisan config:cache
php8.5 artisan route:cache

echo ""
echo "=== Restart PHP ==="
systemctl restart php8.5-fpm

echo ""
echo "=== Final stats ==="
php8.5 artisan tinker --execute="
use App\Models\People;
echo 'Total: ' . People::count() . \"\\n\";
echo 'With VK: ' . People::whereNotNull('vk_url')->count() . \"\\n\";
echo 'With SMM: ' . People::whereNotNull('smm_analysis')->where('smm_analysis', '!=', '')->count() . \"\\n\";
echo 'With Notes containing SMM: ' . People::where('notes', 'like', '%SMM-анализ%')->count() . \"\\n\";
"
