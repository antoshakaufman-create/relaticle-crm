#!/bin/bash

cd /var/www/relaticle

php8.5 artisan tinker --execute="
use App\Models\People;

echo '=== SMM ANALYSIS STATS ===' . \"\\n\";
\$total = People::count();
\$withSmm = People::whereNotNull('smm_analysis')->count();
echo \"Total: {\$total}\\n\";
echo \"With SMM Analysis: {\$withSmm}\\n\\n\";

echo '=== Sample SMM Analysis ===' . \"\\n\";
\$sample = People::whereNotNull('smm_analysis')->first();
if (\$sample) {
    echo 'Name: ' . \$sample->name . \"\\n\";
    echo 'SMM Analysis: ' . \$sample->smm_analysis . \"\\n\";
}
"
