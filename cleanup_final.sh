#!/bin/bash

cd /var/www/relaticle

echo "=== Delete header row contact ==="
php8.5 artisan tinker --execute="
use App\Models\People;

// Delete the header row contact
\$deleted = People::where('name', 'Должность')
    ->orWhere('name', 'Компания')
    ->orWhere('industry', 'Отрасль')
    ->forceDelete();
echo \"Deleted: \$deleted\\n\";
"

echo ""
echo "=== Clear caches ==="
php8.5 artisan optimize:clear
php8.5 artisan filament:optimize-clear
systemctl restart php8.5-fpm

echo ""
echo "=== Final stats ==="
php8.5 artisan tinker --execute="
use App\Models\People;

echo 'Total: ' . People::count() . \"\\n\";
echo 'With notes: ' . People::whereNotNull('notes')->count() . \"\\n\";
echo 'With email: ' . People::whereNotNull('email')->where('email', '!=', '')->count() . \"\\n\";
echo 'With phone: ' . People::whereNotNull('phone')->count() . \"\\n\";

echo \"\\n=== Sample Contacts ===\\n\";
\$samples = People::take(3)->get();
foreach (\$samples as \$s) {
    echo '---' . \"\\n\";
    echo 'Name: ' . \$s->name . \"\\n\";
    echo 'Position: ' . (\$s->position ?: 'N/A') . \"\\n\";
    echo 'Industry: ' . (\$s->industry ?: 'N/A') . \"\\n\";
}
"
