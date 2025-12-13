#!/bin/bash

cd /var/www/relaticle

php8.5 artisan tinker --execute="
use App\Models\People;

echo '=== Stats ===' . \"\\n\";
echo 'Total: ' . People::count() . \"\\n\";
echo 'With notes: ' . People::whereNotNull('notes')->count() . \"\\n\";
echo 'With email: ' . People::whereNotNull('email')->where('email', '!=', '')->count() . \"\\n\";
echo 'With phone: ' . People::whereNotNull('phone')->count() . \"\\n\";
echo 'With website: ' . People::whereNotNull('website')->count() . \"\\n\";

echo \"\\n=== Sample Contacts ===\\n\";
\$samples = People::take(3)->get();
foreach (\$samples as \$s) {
    echo '---' . \"\\n\";
    echo 'Name: ' . \$s->name . \"\\n\";
    echo 'Position: ' . (\$s->position ?: 'N/A') . \"\\n\";
    echo 'Industry: ' . (\$s->industry ?: 'N/A') . \"\\n\";
    echo 'Email: ' . (\$s->email ?: 'N/A') . \"\\n\";
    echo 'Phone: ' . (\$s->phone ?: 'N/A') . \"\\n\";
    echo 'Notes: ' . substr(\$s->notes ?? '', 0, 150) . \"\\n\";
}
"
