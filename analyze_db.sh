#!/bin/bash

cd /var/www/relaticle

echo "=== Current database analysis ==="

php8.5 artisan tinker --execute="
use App\Models\People;

echo '=== TOTALS ==='.\"\\n\";
echo 'Total contacts: ' . People::count() . \"\\n\";
echo 'With email: ' . People::whereNotNull('email')->where('email', '!=', '')->count() . \"\\n\";
echo 'With phone: ' . People::whereNotNull('phone')->where('phone', '!=', '')->count() . \"\\n\";
echo 'With position: ' . People::whereNotNull('position')->where('position', '!=', '')->count() . \"\\n\";
echo 'With industry: ' . People::whereNotNull('industry')->where('industry', '!=', '')->count() . \"\\n\";
echo 'With website: ' . People::whereNotNull('website')->where('website', '!=', '')->count() . \"\\n\";

echo \"\\n=== SAMPLE 10 CONTACTS ===\\n\";
\$contacts = People::take(10)->get();
foreach(\$contacts as \$c) {
    echo \"---\\n\";
    echo 'Name: ' . \$c->name . \"\\n\";
    echo 'Email: ' . (\$c->email ?: 'N/A') . \"\\n\";
    echo 'Phone: ' . (\$c->phone ?: 'N/A') . \"\\n\";
    echo 'Position: ' . (\$c->position ?: 'N/A') . \"\\n\";
    echo 'Industry: ' . (\$c->industry ?: 'N/A') . \"\\n\";
    echo 'Notes (first 100): ' . substr(\$c->notes ?? '', 0, 100) . \"\\n\";
}

echo \"\\n=== CONTACTS WITH NAME ISSUES ===\\n\";
\$bad = People::where('name', 'like', '%,%')
    ->orWhere('name', 'like', '%@%')
    ->orWhere('name', 'like', '%http%')
    ->orWhere('name', 'like', '%www%')
    ->take(10)->get();
foreach(\$bad as \$c) {
    echo 'BAD NAME: ' . \$c->name . \"\\n\";
}
"
