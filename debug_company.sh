#!/bin/bash

cd /var/www/relaticle

php8.5 artisan tinker --execute="
echo '=== Companies table columns ===' . \"\\n\";
\$cols = \Illuminate\Support\Facades\Schema::getColumnListing('companies');
echo implode(', ', \$cols) . \"\\n\";

echo \"\\n=== Try creating company ===\";
try {
    \$c = \App\Models\Company::create([
        'team_id' => 1,
        'creator_id' => 1,
        'name' => 'Test Company X',
        'creation_source' => 'import',
    ]);
    echo \"\\nSuccess! ID: \" . \$c->id;
} catch (\Exception \$e) {
    echo \"\\nError: \" . \$e->getMessage();
}
"
